#!/usr/bin/env python3
"""Build checked owned-code packages. Offline verification proves integrity only."""
import argparse
from datetime import datetime, timezone
import gzip
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import zipfile

from wordpress_release import OWNED, ROOT, digest, file_manifest, git, owned_files, sha256


PREFLIGHT_CHECKS = ('pytest', 'forms-browser', 'required-tests', 'hardcoded-content', 'seed-plan',
                    'php-lint', 'divi-design-system-contract')
CHECKS = PREFLIGHT_CHECKS + ('generation', 'shell-lint')
POLICY = ('wordpress/scripts/release-wordpress-preflight.sh',
          'wordpress/scripts/build-divi-design-system.py',
          '.github/workflows/build-wordpress-release.yml',
          '.github/workflows/check-required-tests.py',
          '.github/workflows/wordpress-required-tests.json')
DEPENDENCIES = ('.github/workflows/requirements-ci.txt', 'wordpress/composer.lock',
                'frontend/package-lock.json')
REPOSITORY = 'gemuesegenossenschaft-bioco/bioco-web-project'
WORKFLOW = '.github/workflows/build-wordpress-release.yml'
MAX_PACKAGE = 128 * 1024 * 1024
MAX_RECEIPT = 8 * 1024 * 1024
TOOLCHAIN = {'python', 'php', 'node', 'bash', 'python_packages'}


def inputs(commit, paths):
    tracked = set(git('ls-tree', '-r', '--name-only', commit).splitlines())
    return {path: sha256(git('show', commit + ':' + path, binary=True))
            for path in paths if path in tracked}


def clean_candidate(commit):
    if any(row[:1] != b'H' for row in git('ls-files', '-v', '-z', binary=True).split(b'\0') if row):
        raise ValueError('Build refuses index flags that can hide validation input changes.')
    if git('rev-parse', 'HEAD') != commit or git('status', '--porcelain'):
        raise ValueError('Build requires a clean checkout at the requested commit.')
    if git('ls-files', '--others', '--', *OWNED):
        raise ValueError('Untracked or ignored input inside owned release sources.')


def run_check(args):
    result = subprocess.run(args, cwd=ROOT, text=True, stdout=subprocess.PIPE,
                            stderr=subprocess.STDOUT,
                            env=os.environ | {'BIOCO_RELEASE_REPO_ROOT': str(ROOT)})
    print(result.stdout, end='', flush=True)
    if result.returncode:
        raise ValueError('Validation failed: ' + args[0])
    return result.stdout


def toolchain():
    commands = {'python': [sys.executable, '--version'], 'php': ['php', '--version'],
                'node': ['node', '--version'], 'bash': ['bash', '--version'],
                'python_packages': [sys.executable, '-c',
                    'import importlib.metadata as m,json; '
                    'print(json.dumps(sorted((d.metadata["Name"],d.version) for d in m.distributions())))']}
    return {name: subprocess.check_output(args, cwd=ROOT, text=True).strip()
            for name, args in commands.items()}


def validation_identity(receipt):
    versions = receipt.get('toolchain')
    if (not isinstance(versions, dict) or set(versions) != TOOLCHAIN
            or any(not isinstance(value, str) or not value.strip() for value in versions.values())):
        raise ValueError('Receipt lacks the required toolchain versions.')
    return digest({key: receipt[key] for key in
                   ('commit', 'source_tree', 'dependencies', 'policy', 'toolchain', 'checks')})


def provenance(commit):
    if os.environ.get('GITHUB_ACTIONS') != 'true':
        return {'kind': 'local'}
    if (os.environ.get('GITHUB_REPOSITORY') != REPOSITORY
            or os.environ.get('GITHUB_REF') != 'refs/heads/wordpress'
            or os.environ.get('GITHUB_SHA') != commit):
        raise ValueError('CI packaging requires the exact WordPress integration candidate.')
    run_id, attempt = int(os.environ['GITHUB_RUN_ID']), int(os.environ['GITHUB_RUN_ATTEMPT'])
    if run_id < 1 or attempt < 1:
        raise ValueError('Invalid CI run identity.')
    return {'kind': 'github-actions', 'repository': REPOSITORY, 'workflow': WORKFLOW,
            'run_id': run_id, 'run_attempt': attempt}


def output_guard(output):
    if output.exists():
        raise ValueError('Package output must be a new directory.')
    if any(output.resolve().is_relative_to((ROOT / source).resolve()) for source in OWNED):
        raise ValueError('Package output must be outside owned release sources.')


def build(commit, output):
    output_guard(output)
    clean_candidate(commit)
    files = owned_files(commit)
    origin = provenance(commit)
    if sum(len(item['data']) for item in files.values()) > MAX_PACKAGE:
        raise ValueError('Owned sources exceed the package size limit.')
    versions = toolchain()
    log = run_check(['bash', str(ROOT / 'wordpress/scripts/release-wordpress-preflight.sh')])
    for gate in PREFLIGHT_CHECKS:
        if not any(line.startswith('gate=' + gate + ' status=passed') for line in log.splitlines()):
            raise ValueError('Missing required passing gate: ' + gate)
    run_check([sys.executable, str(ROOT / 'wordpress/scripts/build-divi-design-system.py'), '--check'])
    for path in git('ls-files', '-z', 'scripts', 'wordpress', 'tests').split('\0'):
        if path.endswith('.sh'):
            run_check(['bash', '-n', str(ROOT / path)])
    clean_candidate(commit)
    package = io.BytesIO()
    with gzip.GzipFile(fileobj=package, mode='wb', filename='', mtime=0) as zipped:
        with tarfile.open(fileobj=zipped, mode='w', format=tarfile.USTAR_FORMAT) as archive:
            for path, item in files.items():
                member = tarfile.TarInfo(path)
                member.size = len(item['data'])
                member.mode = item['mode']
                archive.addfile(member, io.BytesIO(item['data']))
    data = package.getvalue()
    receipt = {'schema': 1, 'commit': commit, 'source_tree': git('rev-parse', commit + '^{tree}'),
               'package_sha256': sha256(data), 'files': file_manifest(files),
               'dependencies': inputs(commit, DEPENDENCIES), 'policy': inputs(commit, POLICY),
               'toolchain': versions, 'checks': {gate: 'passed' for gate in CHECKS},
               'checked_at': datetime.now(timezone.utc).isoformat(),
               'provenance': origin}
    receipt['validation_identity'] = validation_identity(receipt)
    output.mkdir(parents=True, exist_ok=False)
    (output / 'package.tar.gz').write_bytes(data)
    (output / 'receipt.json').write_text(json.dumps(receipt, indent=2) + '\n')
    print(json.dumps({'package': str(output), 'commit': commit, 'sha256': sha256(data)}))


def verify(commit, directory):
    if ((directory / 'receipt.json').stat().st_size > MAX_RECEIPT
            or (directory / 'package.tar.gz').stat().st_size > MAX_PACKAGE):
        raise ValueError('Package exceeds size limit.')
    receipt = json.loads((directory / 'receipt.json').read_text())
    data = (directory / 'package.tar.gz').read_bytes()
    expected = owned_files(commit)
    if (receipt.get('schema') != 1 or receipt.get('commit') != commit
            or receipt.get('source_tree') != git('rev-parse', commit + '^{tree}')
            or receipt.get('package_sha256') != sha256(data)
            or receipt.get('files') != file_manifest(expected)
            or receipt.get('dependencies') != inputs(commit, DEPENDENCIES)
            or receipt.get('policy') != inputs(commit, POLICY)
            or receipt.get('validation_identity') != validation_identity(receipt)
            or receipt.get('checks') != {gate: 'passed' for gate in CHECKS}):
        raise ValueError('Package or validation inputs do not match the requested candidate.')
    actual = {}
    with tarfile.open(fileobj=io.BytesIO(data), mode='r:gz') as archive:
        for member in archive:
            if not member.isfile() or member.name not in expected or member.name in actual:
                raise ValueError('Unsafe or unexpected package member: ' + member.name)
            if member.size != len(expected[member.name]['data']):
                raise ValueError('Package member size differs from the candidate.')
            actual[member.name] = {'mode': member.mode, 'data': archive.extractfile(member).read()}
    if actual != expected:
        raise ValueError('Package files differ from the Git candidate.')
    print(json.dumps({'verification': 'integrity-only', 'commit': commit,
                      'package_sha256': sha256(data), 'receipt_sha256': digest(receipt)}))
    return receipt


def github(endpoint, *, binary=False):
    response = subprocess.run([os.environ.get('BIOCO_PACKAGE_GH_BIN', 'gh'), 'api',
                               'repos/' + REPOSITORY + '/' + endpoint],
                              cwd=ROOT, check=True, capture_output=True).stdout
    return response if binary else json.loads(response)


def verify_ci(commit, run_id, output):
    """Trust the repository's successful workflow, not a caller-authored receipt."""
    output_guard(output)
    if run_id < 1:
        raise ValueError('CI run ID must be positive.')
    run = github(f'actions/runs/{run_id}')
    attempt = run.get('run_attempt')
    if (run.get('id') != run_id or run.get('head_sha') != commit
            or run.get('head_branch') != 'wordpress' or run.get('path') != WORKFLOW
            or run.get('event') not in ('push', 'workflow_dispatch')
            or run.get('status') != 'completed' or run.get('conclusion') != 'success'
            or (run.get('repository') or {}).get('full_name') != REPOSITORY
            or (run.get('head_repository') or {}).get('full_name') != REPOSITORY
            or type(attempt) is not int or attempt < 1):
        raise ValueError('CI run is not successful trusted evidence for this candidate.')
    name = f'wordpress-package-{commit}-{attempt}'
    matches = [item for item in github(f'actions/runs/{run_id}/artifacts?per_page=100')['artifacts']
               if item.get('name') == name]
    if len(matches) != 1:
        raise ValueError('Expected one package artifact from the current run attempt.')
    artifact = matches[0]
    if (artifact.get('expired') is not False
            or (artifact.get('workflow_run') or {}).get('id') != run_id
            or (artifact.get('workflow_run') or {}).get('head_sha') != commit
            or not 0 < artifact.get('size_in_bytes', 0) <= MAX_PACKAGE + MAX_RECEIPT):
        raise ValueError('Artifact is expired or belongs to another candidate.')
    blob = github(f'actions/artifacts/{artifact["id"]}/zip', binary=True)
    if (len(blob) > MAX_PACKAGE + MAX_RECEIPT
            or artifact.get('digest') != 'sha256:' + sha256(blob)):
        raise ValueError('Downloaded artifact differs from GitHub artifact digest.')
    with tempfile.TemporaryDirectory(prefix='bioco-verified-package-') as temporary:
        directory = Path(temporary)
        with zipfile.ZipFile(io.BytesIO(blob)) as archive:
            if sorted(archive.namelist()) != ['package.tar.gz', 'receipt.json']:
                raise ValueError('Unexpected artifact entries.')
            for member in archive.infolist():
                limit = MAX_RECEIPT if member.filename == 'receipt.json' else MAX_PACKAGE
                if member.file_size > limit:
                    raise ValueError('Artifact member exceeds size limit.')
                (directory / member.filename).write_bytes(archive.read(member))
        receipt = verify(commit, directory)
        expected_origin = {'kind': 'github-actions', 'repository': REPOSITORY, 'workflow': WORKFLOW,
                           'run_id': run_id, 'run_attempt': attempt}
        if receipt.get('provenance') != expected_origin:
            raise ValueError('Receipt does not identify the trusted run attempt.')
        # Recheck the run after download: a rerun must not silently replace its evidence.
        current = github(f'actions/runs/{run_id}')
        if any(current.get(key) != run.get(key) for key in
               ('id', 'run_attempt', 'head_sha', 'head_branch', 'path', 'event', 'status', 'conclusion')):
            raise ValueError('CI run changed while verifying its artifact.')
        output.mkdir(parents=True, exist_ok=False)
        for filename in ('package.tar.gz', 'receipt.json'):
            (output / filename).write_bytes((directory / filename).read_bytes())
    print(json.dumps({'verification': 'trusted-ci', 'commit': commit, 'run_id': run_id,
                      'run_attempt': attempt, 'package_sha256': receipt['package_sha256']}))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='command', required=True)
    create = sub.add_parser('build', help='Run validation and package a clean exact commit.')
    create.add_argument('--commit', required=True)
    create.add_argument('--output', type=Path, required=True)
    check = sub.add_parser('verify', help='Check bytes and inputs; does not establish trusted CI evidence.')
    check.add_argument('--commit', required=True)
    check.add_argument('--directory', type=Path, required=True)
    ci = sub.add_parser('verify-ci', help='Download and verify evidence from the trusted integration workflow.')
    ci.add_argument('--commit', required=True)
    ci.add_argument('--run-id', type=int, required=True)
    ci.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    if args.command == 'build':
        build(args.commit, args.output)
    elif args.command == 'verify':
        verify(args.commit, args.directory)
    else:
        verify_ci(args.commit, args.run_id, args.output)


if __name__ == '__main__':
    try:
        main()
    except (ValueError, KeyError, TypeError, OSError, subprocess.CalledProcessError,
            tarfile.TarError, zipfile.BadZipFile) as error:
        print('package-status=failed error=' + str(error), file=sys.stderr)
        raise SystemExit(1)
