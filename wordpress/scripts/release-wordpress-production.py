#!/usr/bin/env python3
"""Fixed-profile, code-only WordPress production promotion. Never imports content."""
import argparse
import contextlib
from datetime import datetime, timezone
import hashlib
import io
import json
import os
from pathlib import Path
import re
import selectors
import shlex
import subprocess
import sys
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parents[2]
SITE = 'https://bioco.ch'
STAGE = 'https://staging.bioco.ch'
REPO = 'gemuesegenossenschaft-bioco/bioco-web-project'
SHA = re.compile(r'^[0-9a-f]{40}$')
SAFE_HOST = re.compile(r'^[A-Za-z0-9._-]+$')
SAFE_PATH = re.compile(r'^/[A-Za-z0-9._/-]+/wp-content$')
OWNED = {
    'wordpress/web/app/mu-plugins/bioco-core': 'mu-plugins/bioco-core',
    'wordpress/web/app/mu-plugins/bioco-content': 'mu-plugins/bioco-content',
    'wordpress/web/app/mu-plugins/bioco-forms': 'mu-plugins/bioco-forms',
    'wordpress/web/app/mu-plugins/bioco-import': 'mu-plugins/bioco-import',
    'wordpress/web/app/themes/bioco': 'themes/bioco',
    'wordpress/web/app/themes/bioco-divi': 'themes/bioco-divi',
    'wordpress/deploy/bioco-mu-loader.php': 'mu-plugins/bioco-mu-loader.php',
    'wordpress/content-seed': 'mu-plugins/bioco-import/content-seed',
}


def fail(message):
    raise RuntimeError(message)


def run(argv, *, env=None, input=None):
    return subprocess.run(argv, cwd=ROOT, env=env, input=input, text=True,
                          capture_output=True, check=True).stdout.strip()


def git(*args):
    return run(['git', *args])


def q(value):
    return shlex.quote(value)


def remote_config(prefix):
    values = {key: os.environ.get(prefix + key, '') for key in ('HOST', 'USER', 'CONTENT', 'SSH_PORT')}
    if not SAFE_HOST.fullmatch(values['HOST']) or not SAFE_HOST.fullmatch(values['USER']) \
            or not SAFE_PATH.fullmatch(values['CONTENT']) \
            or not re.fullmatch(r'[0-9]{1,5}', values['SSH_PORT'] or '22'):
        fail('Invalid ' + prefix + ' target.')
    values['SSH_PORT'] = values['SSH_PORT'] or '22'
    if not 1 <= int(values['SSH_PORT']) <= 65535:
        fail('Invalid SSH port.')
    return values


def ssh_args(target):
    return [os.environ.get('BIOCO_PRODUCTION_SSH_BIN', 'ssh'), '-p', target['SSH_PORT'],
            '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10',
            target['USER'] + '@' + target['HOST']]


def ssh(target, command):
    return run(ssh_args(target) + ['set -eu; ' + command])


def identity(target, expected):
    root = target['CONTENT'].removesuffix('/wp-content')
    output = ssh(target, f'cd {q(root)}; wp option get siteurl; wp option get home').splitlines()
    if len(output) != 2 or any(value.rstrip('/') != expected for value in output):
        fail('WordPress identity mismatch for ' + expected)


def marker(target, missing_ok=False):
    root = target['CONTENT'].removesuffix('/wp-content')
    suffix = " 2>/dev/null || printf 'null\\n'" if missing_ok else ''
    raw = ssh(target, f'cd {q(root)}; wp option get bioco_release_marker --format=json' + suffix)
    data = json.loads(raw)
    if data is None and missing_ok: return None
    if not isinstance(data, dict): fail('Invalid release marker.')
    return data


def source_manifest(root):
    result = {}
    for source, target in OWNED.items():
        path = root / source
        if path.is_file():
            result[target] = hashlib.sha256(path.read_bytes()).hexdigest()
        elif path.is_dir():
            for file in path.rglob('*'):
                if file.is_symlink(): fail('Symlink in owned source: ' + source)
                if file.is_file() and not (file.name.endswith('.log') or file.name == '.DS_Store'
                        or file.name == '.env' or file.name.startswith('.env.')):
                    result[target + '/' + file.relative_to(path).as_posix()] = hashlib.sha256(file.read_bytes()).hexdigest()
        else: fail('Missing owned source: ' + source)
    if not result: fail('Empty code manifest.')
    return dict(sorted(result.items()))


def archive_manifest(commit):
    with tempfile.TemporaryDirectory() as directory:
        extract_commit(commit, Path(directory), *OWNED)
        return source_manifest(Path(directory))


def extract_commit(commit, destination, *paths):
    archive = subprocess.run(['git', 'archive', '--format=tar', commit, *paths], cwd=ROOT,
                             capture_output=True, check=True).stdout
    with tarfile.open(fileobj=io.BytesIO(archive)) as tar:
        for item in tar.getmembers():
            if item.issym() or item.islnk() or '..' in Path(item.name).parts:
                fail('Unsafe archive member.')
        tar.extractall(destination, filter='data')


def remote_manifest(target):
    sources = [path for path in OWNED.values() if path != 'mu-plugins/bioco-import/content-seed']
    cmd = 'cd ' + q(target['CONTENT']) + '; find ' + ' '.join(q(path) for path in sources) \
        + ' -type f -exec sha256sum {} +'
    output = ssh(target, cmd)
    found = {}
    for line in output.splitlines():
        match = re.fullmatch(r'([0-9a-f]{64})  (.+)', line)
        if not match or match[2] in found: fail('Invalid remote code manifest.')
        found[match[2]] = match[1]
    return dict(sorted(found.items()))


def digest(manifest):
    return hashlib.sha256(json.dumps(manifest, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def review_evidence(path, commit, baseline, bootstrap=False):
    if path.stat().st_mode & 0o077:
        fail('Private review evidence must be mode 0600.')
    data = json.loads(path.read_text())
    match = re.fullmatch(
        r'https://github\.com/gemuesegenossenschaft-bioco/bioco-web-project/pull/([0-9]+)#(issuecomment|pullrequestreview|discussion_r)-([0-9]+)',
        data.get('review_url', ''))
    if data.get('commit') != commit or data.get('accepted_baseline') != baseline \
            or data.get('coderabbit_agreed') is not True or data.get('blocking_findings') != [] \
            or not match or not isinstance(data.get('review_pr'), int) \
            or int(match[1]) != data['review_pr']:
        fail('Review evidence does not match this candidate and accepted baseline.')
    if bootstrap and data.get('bootstrap_baseline') is not True:
        fail('Initial unmarked baseline requires explicit review evidence.')
    pr = json.loads(run([os.environ.get('BIOCO_PRODUCTION_GH_BIN', 'gh'), 'pr', 'view', str(data['review_pr']),
                         '-R', REPO, '--json', 'state,baseRefName,mergeCommit,statusCheckRollup']))
    if pr.get('state') != 'MERGED' or pr.get('baseRefName') != 'wordpress' \
            or (pr.get('mergeCommit') or {}).get('oid') != commit:
        fail('Reviewed PR does not identify this merged WordPress commit.')
    checks = {row.get('name'): row.get('conclusion') for row in pr.get('statusCheckRollup', [])
              if row.get('__typename') == 'CheckRun'}
    required = {'WordPress tests', 'WordPress PHP and shell lint',
                'WordPress generation and conformance'}
    if any(checks.get(name) != 'SUCCESS' for name in required):
        fail('Required reviewed PR checks did not all pass.')
    kind, identifier = match[2], match[3]
    endpoint = ('issues/comments/' + identifier if kind == 'issuecomment' else
                'pulls/' + str(data['review_pr']) + '/reviews/' + identifier if kind == 'pullrequestreview' else
                'pulls/comments/' + identifier)
    review = json.loads(run([os.environ.get('BIOCO_PRODUCTION_GH_BIN', 'gh'), 'api',
                             'repos/' + REPO + '/' + endpoint]))
    if (review.get('user') or {}).get('login', '').lower() not in ('coderabbitai[bot]', 'coderabbitai'):
        fail('Linked CodeRabbit evidence is not authored by CodeRabbit.')
    return digest(data)


def local_guard(commit, baseline):
    if git('rev-parse', 'HEAD') != commit or git('status', '--porcelain'):
        fail('Candidate checkout must be the clean requested commit.')
    if git('rev-parse', 'refs/remotes/origin/wordpress') != commit:
        fail('Candidate is not the known origin/wordpress tip; fetch and review again.')
    run(['git', 'merge-base', '--is-ancestor', baseline, commit])
    tracked = set(git('ls-files', '-z').split('\0'))
    for source in OWNED:
        path = ROOT / source
        for file in ([path] if path.is_file() else path.rglob('*')):
            if file.is_file() and str(file.relative_to(ROOT)) not in tracked:
                fail('Untracked or ignored file in owned release source.')


@contextlib.contextmanager
def production_lock(target):
    command = 'flock -n /tmp/bioco-wordpress-production.lock sh -c ' + q("printf 'READY\\n'; cat >/dev/null")
    process = subprocess.Popen(ssh_args(target) + [command], stdin=subprocess.PIPE,
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    try:
        selector = selectors.DefaultSelector()
        selector.register(process.stdout, selectors.EVENT_READ)
        if not selector.select(timeout=15) or process.stdout.readline().strip() != 'READY':
            fail('Could not acquire production server lease.')
        yield lambda: process.poll() is None
    finally:
        process.stdin.close()
        try: process.wait(timeout=10)
        except subprocess.TimeoutExpired: process.kill(); process.wait()


def require_lock(alive):
    if not alive(): fail('Production server lease was lost.')


def fingerprint(target):
    root = target['CONTENT'].removesuffix('/wp-content')
    code = (ROOT / 'wordpress/scripts/editorial-fingerprint.php').read_text().removeprefix('<?php')
    value = ssh(target, f'cd {q(root)}; wp eval {q(code)}')
    if not re.fullmatch(r'[0-9a-f]{64}', value): fail('Invalid editorial fingerprint.')
    return value


def step(name, argv, env):
    print('step=' + name, flush=True)
    subprocess.run(argv, cwd=ROOT, env=env, check=True)


def rollback(prod, candidate_sha, baseline_sha, baseline_manifest, candidate_manifest, env, apply, bootstrap=False):
    identity(prod, SITE)
    previous = marker(prod, bootstrap)
    current = remote_manifest(prod)
    if (previous or {}).get('commit') not in ((None, candidate_sha, baseline_sha) if bootstrap
                                               else (candidate_sha, baseline_sha)):
        fail('Rollback marker is unrelated to the reviewed candidate and baseline.')
    if any(value not in (baseline_manifest.get(path), candidate_manifest.get(path))
           for path, value in current.items()) or not set(current) <= set(baseline_manifest) | set(candidate_manifest):
        fail('Rollback refuses code outside the candidate/baseline manifests.')
    print(json.dumps({'mode': 'rollback-apply' if apply else 'rollback-dry-run',
        'target': SITE, 'current_marker': previous, 'current_manifest': digest(current),
        'rollback_to': baseline_sha, 'baseline_manifest': digest(baseline_manifest)}, sort_keys=True), flush=True)
    if not apply:
        print('rollback-dry-run-complete remote-mutations=0')
        return
    root = prod['CONTENT'].removesuffix('/wp-content')
    with production_lock(prod) as alive:
        require_lock(alive)
        identity(prod, SITE)
        if marker(prod, bootstrap) != previous or remote_manifest(prod) != current:
            fail('Production changed while acquiring rollback lease.')
        before = fingerprint(prod)
        with tempfile.TemporaryDirectory() as directory:
            extract_commit(baseline_sha, Path(directory))
            restore = Path(directory) / 'wordpress/scripts/deploy-wp-code.sh'
            if not restore.is_file(): fail('Baseline has no owned-code deploy tool.')
            rollback_env = env | {'BIOCO_RELEASE_ROLLBACK': '1'}
            step('rollback-code-sync', [str(restore), '--host=' + prod['HOST'],
                '--user=' + prod['USER'], '--wp-content=' + prod['CONTENT'],
                '--ssh-port=' + prod['SSH_PORT'], '--apply'], rollback_env)
        require_lock(alive)
        if remote_manifest(prod) != baseline_manifest: fail('Rollback code manifest differs from baseline.')
        ssh(prod, f'cd {q(root)}; wp cache flush')
        step('rollback-web-cache', [str(ROOT / 'wordpress/scripts/flush-wordpress-web-opcache.sh')], env)
        ssh(prod, f'cd {q(root)}; wp bioco verify --runtime')
        step('rollback-smoke', [str(ROOT / 'tests/wordpress-staging-render-gate.sh')], env)
        require_lock(alive)
        if fingerprint(prod) != before: fail('Editorial data changed during rollback.')
        if remote_manifest(prod) != baseline_manifest: fail('Rollback code changed during verification.')
        receipt = {'commit': baseline_sha, 'rolled_back_from': candidate_sha,
            'manifest': digest(baseline_manifest), 'editorial_fingerprint': before,
            'verify': 'passed', 'smoke': 'passed'}
        ssh(prod, f'cd {q(root)}; wp option update bioco_release_marker {q(json.dumps(receipt,separators=(",",":")))} --format=json >/dev/null')
        require_lock(alive)
        if marker(prod).get('commit') != baseline_sha: fail('Rollback marker write was not retained.')
        print('rollback-complete commit=' + baseline_sha + ' manifest=' + digest(baseline_manifest))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--commit', required=True)
    parser.add_argument('--expected-baseline', required=True)
    parser.add_argument('--review-evidence', type=Path, required=True)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--rollback', action='store_true', help='Restore only owned code to the expected baseline.')
    parser.add_argument('--bootstrap-baseline', action='store_true',
                        help='Accept an absent production marker only when owned code matches the baseline.')
    args = parser.parse_args()
    if not SHA.fullmatch(args.commit) or not SHA.fullmatch(args.expected_baseline):
        fail('Both commit values must be full lowercase SHAs.')
    local_guard(args.commit, args.expected_baseline)
    prod, stage = remote_config('BIOCO_PROD_'), remote_config('BIOCO_STAGE_')
    if prod['HOST'] == stage['HOST'] and prod['CONTENT'] == stage['CONTENT']:
        fail('Production and staging target the same installation.')
    evidence_hash = review_evidence(args.review_evidence, args.commit, args.expected_baseline,
                                    args.bootstrap_baseline)
    step('preflight', [str(ROOT / 'wordpress/scripts/release-wordpress-preflight.sh')], os.environ.copy())
    candidate = source_manifest(ROOT)
    baseline = archive_manifest(args.expected_baseline)
    identity(stage, STAGE)
    staged = marker(stage)
    if staged.get('commit') != args.commit or staged.get('verify') != 'passed' or staged.get('smoke') != 'passed':
        fail('Staging release receipt is not successful for the exact candidate.')
    if remote_manifest(stage) != candidate:
        fail('Staging code differs from the reviewed candidate.')
    env = os.environ.copy()
    env.update(BIOCO_WP_HOST=prod['HOST'], BIOCO_WP_USER=prod['USER'],
               BIOCO_WP_CONTENT=prod['CONTENT'], BIOCO_WP_SSH_PORT=prod['SSH_PORT'],
               BIOCO_RELEASE_URL=SITE, BIOCO_RELEASE_RENDER_URL=SITE,
               BIOCO_RELEASE_RENDER_RESOLVE='', BIOCO_RELEASE_SSH_BIN=ssh_args(prod)[0])
    if args.rollback:
        rollback(prod, args.commit, args.expected_baseline, baseline, candidate, env, args.apply,
                 args.bootstrap_baseline)
        return
    identity(prod, SITE)
    previous = marker(prod, args.bootstrap_baseline)
    if ((previous is None and not args.bootstrap_baseline)
            or (previous is not None and previous.get('commit') != args.expected_baseline)):
        fail('Production marker differs from accepted baseline.')
    if remote_manifest(prod) != baseline:
        fail('Production code differs from accepted baseline.')
    print(json.dumps({'mode': 'apply' if args.apply else 'dry-run', 'target': SITE,
        'candidate': args.commit, 'expected_baseline': args.expected_baseline,
        'previous_marker': previous, 'staging_marker': staged, 'candidate_manifest': digest(candidate),
        'baseline_manifest': digest(baseline), 'owned_changed': sorted(set(candidate) ^ set(baseline) |
            {p for p in candidate.keys() & baseline.keys() if candidate[p] != baseline[p]}),
        'review_evidence_sha256': evidence_hash}, sort_keys=True), flush=True)
    if not args.apply:
        print('release-dry-run-complete remote-mutations=0')
        return
    backup_dir = os.environ.get('BIOCO_PROD_BACKUP_DIR', '')
    if not re.fullmatch(r'/[A-Za-z0-9._/-]+', backup_dir) or '..' in Path(backup_dir).parts \
            or backup_dir == prod['CONTENT'].removesuffix('/wp-content') \
            or backup_dir.startswith(prod['CONTENT'].removesuffix('/wp-content') + '/'):
        fail('BIOCO_PROD_BACKUP_DIR must be a private path outside WordPress.')
    timestamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    backup = backup_dir.rstrip('/') + '/' + timestamp + '-' + args.commit + '.sql'
    with production_lock(prod) as alive:
        require_lock(alive)
        identity(prod, SITE)
        if marker(prod, args.bootstrap_baseline) != previous or remote_manifest(prod) != baseline:
            fail('Production changed while acquiring lease.')
        before = fingerprint(prod)
        root = prod['CONTENT'].removesuffix('/wp-content')
        ssh(prod, f'umask 077; mkdir -p {q(backup_dir)}; cd {q(root)}; wp db export {q(backup)} --quiet; test -s {q(backup)}')
        require_lock(alive)
        step('code-sync', [str(ROOT / 'wordpress/scripts/deploy-wp-code.sh'),
            '--host=' + prod['HOST'], '--user=' + prod['USER'], '--wp-content=' + prod['CONTENT'],
            '--ssh-port=' + prod['SSH_PORT'], '--apply'], env)
        require_lock(alive)
        if remote_manifest(prod) != candidate: fail('Deployed code manifest differs from candidate.')
        ssh(prod, f'cd {q(root)}; wp cache flush')
        step('web-cache', [str(ROOT / 'wordpress/scripts/flush-wordpress-web-opcache.sh')], env)
        ssh(prod, f'cd {q(root)}; wp bioco verify --runtime')
        step('smoke', [str(ROOT / 'tests/wordpress-staging-render-gate.sh')], env)
        require_lock(alive)
        if fingerprint(prod) != before: fail('Editorial data changed during code promotion.')
        if remote_manifest(prod) != candidate: fail('Deployed code changed during verification.')
        receipt = {'commit': args.commit, 'previous': args.expected_baseline,
            'staging_commit': staged['commit'], 'manifest': digest(candidate),
            'editorial_fingerprint': before, 'backup': backup,
            'review_evidence_sha256': evidence_hash, 'verify': 'passed', 'smoke': 'passed'}
        ssh(prod, f'cd {q(root)}; wp option update bioco_release_marker {q(json.dumps(receipt,separators=(",",":")))} --format=json >/dev/null')
        require_lock(alive)
        if marker(prod).get('commit') != args.commit: fail('Production marker write was not retained.')
        print('release-complete commit=' + args.commit + ' manifest=' + digest(candidate)
              + ' backup=' + backup)


if __name__ == '__main__':
    try: main()
    except (RuntimeError, OSError, subprocess.CalledProcessError, json.JSONDecodeError) as error:
        print('release-status=failed error=' + str(error), file=sys.stderr)
        raise SystemExit(1)
