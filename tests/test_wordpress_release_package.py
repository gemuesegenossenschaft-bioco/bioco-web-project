"""Exercise package creation and trust checks through the operator command."""
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tarfile
import zipfile
from xml.etree import ElementTree as ET

import pytest


ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'wordpress/scripts/release-wordpress-package.py'
OWNED = [
    'wordpress/web/app/mu-plugins/bioco-core',
    'wordpress/web/app/mu-plugins/bioco-content',
    'wordpress/web/app/mu-plugins/bioco-forms',
    'wordpress/web/app/mu-plugins/bioco-import',
    'wordpress/web/app/themes/bioco',
    'wordpress/web/app/themes/bioco-divi',
    'wordpress/deploy/bioco-mu-loader.php',
    'wordpress/content-seed',
]


def git(repo, *args):
    return subprocess.check_output(['git', *args], cwd=repo, text=True).strip()


def fixture(tmp_path):
    repo = tmp_path / 'repo'
    repo.mkdir()
    git(repo, 'init', '-q')
    git(repo, 'config', 'user.name', 'Package test')
    git(repo, 'config', 'user.email', 'test@example.test')
    for source in OWNED:
        path = repo / source
        if path.suffix != '.php':
            path /= 'owned.php'
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text('<?php /* public owned code */\n')
    scripts = repo / 'wordpress/scripts'
    scripts.mkdir()
    if SCRIPT.exists():
        shutil.copy2(SCRIPT, scripts / SCRIPT.name)
    library = ROOT / 'wordpress/scripts/wordpress_release.py'
    if library.exists():
        shutil.copy2(library, scripts / library.name)
    (scripts / 'release-wordpress-preflight.sh').write_text(
        '#!/bin/sh\nset -eu\n'
        'echo checked >> "$TEST_GATE_CALLS"\n'
        'test "${TEST_FAIL_GATE:-0}" = 0\n'
        'for gate in pytest forms-browser required-tests hardcoded-content seed-plan '
        'php-lint divi-design-system-contract; do\n'
        '  echo "gate=$gate status=passed"\n'
        'done\n')
    (scripts / 'build-divi-design-system.py').write_text('print("generation passed")\n')
    (repo / '.github/workflows').mkdir(parents=True)
    (repo / '.github/workflows/requirements-ci.txt').write_text('# pinned fixture\n')
    (repo / '.github/workflows/build-wordpress-release.yml').write_text('name: fixture\n')
    (repo / '.gitignore').write_text('__pycache__/\noutput/\n')
    git(repo, 'add', '.')
    git(repo, 'commit', '-qm', 'fixture')
    commit = git(repo, 'rev-parse', 'HEAD')
    env = {k: v for k, v in os.environ.items() if not k.startswith(('BIOCO_', 'GITHUB_'))}
    env['TEST_GATE_CALLS'] = str(tmp_path / 'gate-calls')
    return repo, commit, env


def command(repo, env, *args):
    return subprocess.run([sys.executable, str(repo / 'wordpress/scripts' / SCRIPT.name), *args],
                          cwd=repo, env=env, text=True, capture_output=True)


def test_build_produces_repeatable_owned_bytes_and_a_bound_validation_receipt(tmp_path):
    repo, commit, env = fixture(tmp_path)
    first, second = tmp_path / 'first', tmp_path / 'second'
    for output in (first, second):
        result = command(repo, env, 'build', '--commit', commit, '--output', str(output))
        assert result.returncode == 0, result.stderr + result.stdout
    assert (first / 'package.tar.gz').read_bytes() == (second / 'package.tar.gz').read_bytes()
    receipt = json.loads((first / 'receipt.json').read_text())
    assert receipt['commit'] == commit
    assert receipt['package_sha256'] == hashlib.sha256((first / 'package.tar.gz').read_bytes()).hexdigest()
    assert receipt['source_tree'] == git(repo, 'rev-parse', commit + '^{tree}')
    assert receipt['checks']['required-tests'] == 'passed'
    assert receipt['checks']['generation'] == 'passed'
    assert receipt['checks']['shell-lint'] == 'passed'
    assert receipt['toolchain']['python'] and receipt['toolchain']['php'] and receipt['toolchain']['node']
    assert receipt['dependencies']['.github/workflows/requirements-ci.txt']
    assert receipt['provenance']['kind'] == 'local'
    result = command(repo, env, 'verify', '--commit', commit, '--directory', str(first))
    assert result.returncode == 0, result.stderr
    assert 'integrity-only' in result.stdout
    assert len(Path(env['TEST_GATE_CALLS']).read_text().splitlines()) == 2


def test_build_pins_preflight_to_the_packaged_checkout(tmp_path):
    repo, _, env = fixture(tmp_path)
    preflight = repo / 'wordpress/scripts/release-wordpress-preflight.sh'
    preflight.write_text(preflight.read_text().replace(
        'set -eu\n', 'set -eu\ncd "${BIOCO_RELEASE_REPO_ROOT:-.}"\npwd > "$TEST_VALIDATION_ROOT"\n'))
    git(repo, 'add', '.')
    git(repo, 'commit', '-qm', 'observe validation checkout')
    commit = git(repo, 'rev-parse', 'HEAD')
    other = tmp_path / 'other-checkout'
    other.mkdir()
    env['BIOCO_RELEASE_REPO_ROOT'] = str(other)
    env['TEST_VALIDATION_ROOT'] = str(tmp_path / 'validation-root')
    result = command(repo, env, 'build', '--commit', commit, '--output', str(tmp_path / 'package'))
    assert result.returncode == 0, result.stderr
    assert Path(env['TEST_VALIDATION_ROOT']).read_text().strip() == str(repo)


@pytest.mark.parametrize('scenario', ['missing', 'skipped'])
def test_required_coverage_rejects_absent_package_checks(tmp_path, scenario):
    policy = json.loads((ROOT / '.github/workflows/wordpress-required-tests.json').read_text())
    required_path = 'tests/test_wordpress_release_package.py'
    suite = ET.Element('testsuite')
    for path in policy['required_modules']:
        if path != required_path:
            ET.SubElement(suite, 'testcase', classname=path[:-3].replace('/', '.'), name='test_fixture')
    if scenario == 'skipped':
        case = ET.SubElement(suite, 'testcase', classname=required_path[:-3].replace('/', '.'),
                             name='test_package_fixture')
        ET.SubElement(case, 'skipped')
    report = tmp_path / 'report.xml'
    ET.ElementTree(suite).write(report, encoding='unicode')
    result = subprocess.run([sys.executable, str(ROOT / '.github/workflows/check-required-tests.py'),
                             str(report)], text=True, capture_output=True)
    assert result.returncode != 0, result.stdout
    assert required_path in result.stdout


@pytest.mark.parametrize('flag', ['--assume-unchanged', '--skip-worktree'])
def test_build_rejects_git_flags_that_hide_different_validation_inputs(tmp_path, flag):
    repo, commit, env = fixture(tmp_path)
    path = OWNED[0] + '/owned.php'
    git(repo, 'update-index', flag, path)
    (repo / path).write_text('<?php /* local substitute absent from the Git package */\n')
    assert git(repo, 'status', '--porcelain') == ''
    output = tmp_path / 'package'
    result = command(repo, env, 'build', '--commit', commit, '--output', str(output))
    assert result.returncode != 0
    assert not Path(env['TEST_GATE_CALLS']).exists()
    assert not output.exists()


@pytest.mark.parametrize('change', ['tracked-secret', 'ignored-source', 'symlink'])
def test_unsafe_inputs_are_rejected_before_validation(tmp_path, change):
    repo, commit, env = fixture(tmp_path)
    source = repo / OWNED[0]
    if change == 'tracked-secret':
        (source / '.env.production').write_text('example=private\n')
    elif change == 'ignored-source':
        (repo / '.gitignore').write_text((repo / '.gitignore').read_text() + '*.local\n')
        (source / 'extra.local').write_text('untracked release input\n')
    else:
        (source / 'linked.php').symlink_to('owned.php')
    git(repo, 'add', '.')
    git(repo, 'commit', '-qm', 'unsafe source')
    commit = git(repo, 'rev-parse', 'HEAD')
    result = command(repo, env, 'build', '--commit', commit, '--output', str(tmp_path / 'package'))
    assert result.returncode != 0
    assert not Path(env['TEST_GATE_CALLS']).exists()
    assert not (tmp_path / 'package').exists()


@pytest.mark.parametrize('change', ['failed-gate', 'missing-gate', 'dirty', 'wrong-commit'])
def test_build_never_issues_a_receipt_for_unverified_input(tmp_path, change):
    repo, commit, env = fixture(tmp_path)
    if change == 'failed-gate':
        env['TEST_FAIL_GATE'] = '1'
    elif change == 'missing-gate':
        preflight = repo / 'wordpress/scripts/release-wordpress-preflight.sh'
        preflight.write_text(preflight.read_text().replace('required-tests ', ''))
        git(repo, 'add', '.')
        git(repo, 'commit', '-qm', 'missing proof')
        commit = git(repo, 'rev-parse', 'HEAD')
    elif change == 'dirty':
        (repo / OWNED[0] / 'owned.php').write_text('<?php changed\n')
    else:
        commit = 'f' * 40
    output = tmp_path / 'package'
    result = command(repo, env, 'build', '--commit', commit, '--output', str(output))
    assert result.returncode != 0
    assert not (output / 'receipt.json').exists()


@pytest.mark.parametrize('change', ['bytes', 'commit', 'policy', 'dependencies', 'skipped',
                                    'toolchain', 'missing-toolchain'])
def test_offline_verification_rejects_tampering_and_stale_evidence(tmp_path, change):
    repo, commit, env = fixture(tmp_path)
    output = tmp_path / 'package'
    assert command(repo, env, 'build', '--commit', commit, '--output', str(output)).returncode == 0
    receipt = json.loads((output / 'receipt.json').read_text())
    if change == 'bytes':
        package = output / 'package.tar.gz'
        package.write_bytes(package.read_bytes() + b'changed')
    elif change == 'commit':
        receipt['commit'] = 'a' * 40
    elif change == 'skipped':
        receipt['checks']['required-tests'] = 'skipped'
    elif change == 'toolchain':
        receipt['toolchain']['php'] = 'different runtime'
    elif change == 'missing-toolchain':
        receipt.pop('toolchain')
    else:
        receipt[change] = {}
    (output / 'receipt.json').write_text(json.dumps(receipt))
    result = command(repo, env, 'verify', '--commit', commit, '--directory', str(output))
    assert result.returncode != 0


def ci_fixture(tmp_path):
    repo, commit, env = fixture(tmp_path)
    env.update(GITHUB_ACTIONS='true', GITHUB_REPOSITORY='gemuesegenossenschaft-bioco/bioco-web-project',
               GITHUB_SHA=commit, GITHUB_REF='refs/heads/wordpress', GITHUB_RUN_ID='123', GITHUB_RUN_ATTEMPT='2')
    output = tmp_path / 'built'
    assert command(repo, env, 'build', '--commit', commit, '--output', str(output)).returncode == 0
    blob = io.BytesIO()
    with zipfile.ZipFile(blob, 'w') as archive:
        for name in ('package.tar.gz', 'receipt.json'):
            archive.writestr(name, (output / name).read_bytes())
    (tmp_path / 'artifact.zip').write_bytes(blob.getvalue())
    run = {'id': 123, 'run_attempt': 2, 'head_sha': commit, 'head_branch': 'wordpress',
           'path': '.github/workflows/build-wordpress-release.yml', 'event': 'push',
           'status': 'completed', 'conclusion': 'success',
           'repository': {'full_name': 'gemuesegenossenschaft-bioco/bioco-web-project'},
           'head_repository': {'full_name': 'gemuesegenossenschaft-bioco/bioco-web-project'}}
    (tmp_path / 'run.json').write_text(json.dumps(run))
    artifact = {'id': 456, 'name': f'wordpress-package-{commit}-2', 'expired': False,
                'size_in_bytes': len(blob.getvalue()), 'digest': 'sha256:' + hashlib.sha256(blob.getvalue()).hexdigest(),
                'workflow_run': {'id': 123, 'head_sha': commit}}
    (tmp_path / 'artifacts.json').write_text(json.dumps({'artifacts': [artifact]}))
    gh = tmp_path / 'gh'
    gh.write_text('''#!/usr/bin/env python3
import sys
from pathlib import Path
root=Path(__file__).parent
endpoint=sys.argv[-1]
if '/artifacts/456/zip' in endpoint:
    sys.stdout.buffer.write((root/'artifact.zip').read_bytes())
elif '/artifacts' in endpoint:
    print((root/'artifacts.json').read_text())
elif '/actions/runs/123' in endpoint:
    print((root/'run.json').read_text())
else:
    raise SystemExit('unexpected GitHub request')
''')
    gh.chmod(0o755)
    env['BIOCO_PACKAGE_GH_BIN'] = str(gh)
    return repo, commit, env


def test_ci_verification_downloads_trusted_run_artifact_without_rerunning_tests(tmp_path):
    repo, commit, env = ci_fixture(tmp_path)
    output = tmp_path / 'verified'
    result = command(repo, env, 'verify-ci', '--commit', commit, '--run-id', '123', '--output', str(output))
    assert result.returncode == 0, result.stderr + result.stdout
    assert 'trusted-ci' in result.stdout
    assert (output / 'package.tar.gz').read_bytes() == (tmp_path / 'built/package.tar.gz').read_bytes()
    assert json.loads((output / 'receipt.json').read_text())['provenance']['run_attempt'] == 2
    assert len(Path(env['TEST_GATE_CALLS']).read_text().splitlines()) == 1


@pytest.mark.parametrize('change', ['failed', 'fork', 'different-sha', 'wrong-workflow', 'pr-run',
                                   'expired', 'digest', 'attempt'])
def test_ci_reuse_rejects_wrong_origin_or_failed_evidence(tmp_path, change):
    repo, commit, env = ci_fixture(tmp_path)
    run = json.loads((tmp_path / 'run.json').read_text())
    artifacts = json.loads((tmp_path / 'artifacts.json').read_text())
    if change == 'failed': run['conclusion'] = 'failure'
    elif change == 'fork': run['head_repository']['full_name'] = 'attacker/fork'
    elif change == 'different-sha': run['head_sha'] = 'b' * 40
    elif change == 'wrong-workflow': run['path'] = '.github/workflows/arbitrary.yml'
    elif change == 'pr-run': run['event'] = 'pull_request'
    elif change == 'expired': artifacts['artifacts'][0]['expired'] = True
    elif change == 'digest': artifacts['artifacts'][0]['digest'] = 'sha256:' + '0' * 64
    elif change == 'attempt': run['run_attempt'] = 3
    (tmp_path / 'run.json').write_text(json.dumps(run))
    (tmp_path / 'artifacts.json').write_text(json.dumps(artifacts))
    output = tmp_path / 'rejected'
    result = command(repo, env, 'verify-ci', '--commit', commit, '--run-id', '123', '--output', str(output))
    assert result.returncode != 0
    assert not output.exists()


@pytest.mark.parametrize('change', ['traversal', 'symlink', 'duplicate', 'modified-file'])
def test_archive_validation_does_not_trust_a_rewritten_checksum(tmp_path, change):
    repo, commit, env = fixture(tmp_path)
    output = tmp_path / 'package'
    assert command(repo, env, 'build', '--commit', commit, '--output', str(output)).returncode == 0
    path = output / 'package.tar.gz'
    original = []
    with tarfile.open(path, 'r:gz') as archive:
        for member in archive:
            original.append((member, archive.extractfile(member).read()))
    with tarfile.open(path, 'w:gz') as archive:
        for member, data in original:
            if change == 'modified-file' and member.name == original[0][0].name:
                data = b'x' * len(data)
            archive.addfile(member, io.BytesIO(data))
        if change != 'modified-file':
            member = tarfile.TarInfo('../escape' if change == 'traversal' else original[0][0].name)
            if change == 'symlink':
                member.type, member.linkname = tarfile.SYMTYPE, '/etc/passwd'
            archive.addfile(member)
    receipt = json.loads((output / 'receipt.json').read_text())
    receipt['package_sha256'] = hashlib.sha256(path.read_bytes()).hexdigest()
    (output / 'receipt.json').write_text(json.dumps(receipt))
    result = command(repo, env, 'verify', '--commit', commit, '--directory', str(output))
    assert result.returncode != 0
    assert not (tmp_path / 'escape').exists()


def test_ci_workflow_only_packages_the_integration_commit_without_deploy_credentials():
    workflow = (ROOT / '.github/workflows/build-wordpress-release.yml').read_text()
    assert "github.ref == 'refs/heads/wordpress'" in workflow
    assert 'persist-credentials: false' in workflow
    assert 'contents: read' in workflow
    assert 'release-wordpress-package.py build' in workflow
    assert '--commit="$GITHUB_SHA"' in workflow
    assert 'github.run_attempt' in workflow
    assert 'secrets.' not in workflow
    assert '--apply' not in workflow
    assert 'pull_request_target' not in workflow
