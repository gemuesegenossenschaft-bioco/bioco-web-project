"""Isolated production promotion transport and code-only guards."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess

import pytest

ROOT = Path(__file__).parents[1]
SCRIPT = ROOT / 'wordpress/scripts/release-wordpress-production.py'
SOURCES = {
    'wordpress/web/app/mu-plugins/bioco-core': 'mu-plugins/bioco-core',
    'wordpress/web/app/mu-plugins/bioco-content': 'mu-plugins/bioco-content',
    'wordpress/web/app/mu-plugins/bioco-forms': 'mu-plugins/bioco-forms',
    'wordpress/web/app/mu-plugins/bioco-import': 'mu-plugins/bioco-import',
    'wordpress/web/app/themes/bioco': 'themes/bioco',
    'wordpress/web/app/themes/bioco-divi': 'themes/bioco-divi',
    'wordpress/deploy/bioco-mu-loader.php': 'mu-plugins/bioco-mu-loader.php',
    'wordpress/content-seed': 'mu-plugins/bioco-import/content-seed',
}


def command(args, cwd):
    return subprocess.run(args, cwd=cwd, check=True, text=True, capture_output=True).stdout.strip()


def manifest(repo, version):
    result = {}
    for source, target in SOURCES.items():
        path = repo / source
        for file in ([path] if path.is_file() else path.rglob('*')):
            if file.is_file():
                data = file.read_bytes() if version == 'candidate' else subprocess.run(
                    ['git', 'show', f'{version}:{file.relative_to(repo)}'], cwd=repo,
                    check=True, capture_output=True).stdout
                result[target + ('' if path.is_file() else '/' + file.relative_to(path).as_posix())] = hashlib.sha256(data).hexdigest()
    return result


def fixture(tmp_path):
    repo = tmp_path / 'repo'
    repo.mkdir()
    command(['git', 'init', '-q'], repo)
    command(['git', 'config', 'user.email', 'test@example.test'], repo)
    command(['git', 'config', 'user.name', 'Test'], repo)
    for source in SOURCES:
        path = repo / source
        if path.suffix == '.php': path.parent.mkdir(parents=True, exist_ok=True); path.write_text('<?php baseline\n')
        else: path.mkdir(parents=True, exist_ok=True); (path / 'owned.txt').write_text('baseline\n')
    script = repo / 'wordpress/scripts/release-wordpress-production.py'
    script.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(SCRIPT, script)
    shutil.copy2(ROOT / 'wordpress/scripts/editorial-fingerprint.php', script.parent / 'editorial-fingerprint.php')
    for name in ['release-wordpress-preflight.sh', 'deploy-wp-code.sh', 'flush-wordpress-web-opcache.sh']:
        path = script.parent / name
        path.write_text('#!/bin/sh\n'
            'echo ' + name + ' >> "$BIOCO_TEST_EVENTS"\n'
            + ('if [ "${BIOCO_RELEASE_ROLLBACK:-0}" = 1 ]; then cp "$BIOCO_TEST_BASELINE" "$BIOCO_TEST_PROD_MANIFEST"; else cp "$BIOCO_TEST_CANDIDATE" "$BIOCO_TEST_PROD_MANIFEST"; fi\n' if name == 'deploy-wp-code.sh' else ''))
        path.chmod(0o755)
    smoke = repo / 'tests/wordpress-staging-render-gate.sh'
    smoke.parent.mkdir(parents=True)
    smoke.write_text('#!/bin/sh\necho smoke >> "$BIOCO_TEST_EVENTS"\n')
    smoke.chmod(0o755)
    command(['git', 'add', '.'], repo)
    command(['git', 'commit', '-qm', 'baseline'], repo)
    baseline = command(['git', 'rev-parse', 'HEAD'], repo)
    (repo / 'wordpress/web/app/mu-plugins/bioco-core/owned.txt').write_text('candidate\n')
    command(['git', 'add', '.'], repo)
    command(['git', 'commit', '-qm', 'candidate'], repo)
    candidate = command(['git', 'rev-parse', 'HEAD'], repo)
    command(['git', 'update-ref', 'refs/remotes/origin/wordpress', candidate], repo)
    baseline_manifest = manifest(repo, baseline)
    candidate_manifest = manifest(repo, 'candidate')
    prod_manifest_file = tmp_path / 'prod-manifest.json'
    prod_manifest_file.write_text(json.dumps(baseline_manifest))
    stage_manifest_file = tmp_path / 'stage-manifest.json'
    stage_manifest_file.write_text(json.dumps(candidate_manifest))
    candidate_file = tmp_path / 'candidate-manifest.json'
    candidate_file.write_text(json.dumps(candidate_manifest))
    baseline_file = tmp_path / 'baseline-manifest.json'
    baseline_file.write_text(json.dumps(baseline_manifest))
    prod_marker = tmp_path / 'prod-marker.json'
    prod_marker.write_text(json.dumps({'commit': baseline}))
    stage_marker = tmp_path / 'stage-marker.json'
    stage_marker.write_text(json.dumps({'commit': candidate, 'verify': 'passed', 'smoke': 'passed'}))
    review = tmp_path / 'review.json'
    review.write_text(json.dumps({'commit': candidate, 'accepted_baseline': baseline,
        'coderabbit_agreed': True, 'blocking_findings': [], 'review_pr': 258,
        'review_url': 'https://github.com/gemuesegenossenschaft-bioco/bioco-web-project/pull/258#issuecomment-123'}))
    review.chmod(0o600)
    ssh = tmp_path / 'ssh'
    ssh.write_text('''#!/usr/bin/env python3
import json,os,sys,shlex
from pathlib import Path
args=sys.argv[1:]; target=next(arg for arg in args if '@' in arg); cmd=args[-1]
events=Path(os.environ['BIOCO_TEST_EVENTS'])
with events.open('a') as file:file.write(('stage:' if 'stage.test' in target else 'prod:')+cmd+'\\n')
if 'flock -n' in cmd:
 print('READY',flush=True)
 sys.stdin.read()
 sys.exit(0)
if 'wp option get siteurl' in cmd:
 url='https://staging.bioco.ch' if 'stage.test' in target else 'https://bioco.ch'
 if os.getenv('BIOCO_TEST_WRONG_IDENTITY')=='1' and 'prod.test' in target:url='https://staging.bioco.ch'
 print(url+'\\n'+url)
elif 'wp option get bioco_release_marker' in cmd:
 print(Path(os.environ['BIOCO_TEST_STAGE_MARKER' if 'stage.test' in target else 'BIOCO_TEST_PROD_MARKER']).read_text())
elif 'sha256sum' in cmd:
 data=json.loads(Path(os.environ['BIOCO_TEST_STAGE_MANIFEST' if 'stage.test' in target else 'BIOCO_TEST_PROD_MANIFEST']).read_text())
 for name,sha in sorted(data.items()):print(sha+'  '+name)
elif 'wp eval ' in cmd:print('a'*64)
elif 'wp option update bioco_release_marker' in cmd:
 words=shlex.split(cmd)
 data=json.loads(words[words.index('bioco_release_marker')+1])
 Path(os.environ['BIOCO_TEST_PROD_MARKER']).write_text(json.dumps(data))
elif 'wp db export' in cmd or 'wp cache flush' in cmd or 'wp bioco verify --runtime' in cmd:pass
else:print('unexpected ssh command: '+cmd,file=sys.stderr);sys.exit(1)
''')
    ssh.chmod(0o755)
    gh = tmp_path / 'gh'
    gh.write_text('#!/usr/bin/env python3\nimport json,os,sys\nif sys.argv[1]=="api":print(json.dumps({"user":{"login":os.getenv("BIOCO_TEST_REVIEW_AUTHOR","coderabbitai[bot]")}}))\nelse:print(json.dumps({"state":"MERGED","baseRefName":"wordpress","mergeCommit":{"oid":os.environ["BIOCO_TEST_CANDIDATE_SHA"]},"statusCheckRollup":[{"__typename":"CheckRun","name":n,"conclusion":"FAILURE" if os.getenv("BIOCO_TEST_BAD_CHECK")==n else "SUCCESS"} for n in ["WordPress tests","WordPress PHP and shell lint","WordPress generation and conformance"]]}))\n')
    gh.chmod(0o755)
    events = tmp_path / 'events'
    env = {k:v for k,v in os.environ.items() if not k.startswith('BIOCO_')}
    env.update(BIOCO_PROD_HOST='prod.test', BIOCO_PROD_USER='deploy', BIOCO_PROD_CONTENT='/srv/prod/wp-content',
        BIOCO_PROD_BACKUP_DIR='/srv/private/backups',
        BIOCO_STAGE_HOST='stage.test', BIOCO_STAGE_USER='deploy', BIOCO_STAGE_CONTENT='/srv/stage/wp-content',
        BIOCO_PRODUCTION_SSH_BIN=str(ssh), BIOCO_PRODUCTION_GH_BIN=str(gh), BIOCO_TEST_EVENTS=str(events),
        BIOCO_TEST_PROD_MANIFEST=str(prod_manifest_file), BIOCO_TEST_STAGE_MANIFEST=str(stage_manifest_file),
        BIOCO_TEST_CANDIDATE=str(candidate_file), BIOCO_TEST_PROD_MARKER=str(prod_marker),
        BIOCO_TEST_STAGE_MARKER=str(stage_marker), BIOCO_TEST_CANDIDATE_SHA=candidate)
    env['BIOCO_TEST_BASELINE'] = str(baseline_file)
    return script, env, candidate, baseline, review, events, prod_marker


def promote(script, env, candidate, baseline, review, apply=False, rollback=False, bootstrap=False):
    return subprocess.run([str(script), '--commit='+candidate, '--expected-baseline='+baseline,
        '--review-evidence='+str(review), *(['--apply'] if apply else []),
        *(['--rollback'] if rollback else []), *(['--bootstrap-baseline'] if bootstrap else [])], env=env,
        text=True, capture_output=True)


def test_dry_run_reads_identity_receipts_and_manifests_without_remote_write(tmp_path):
    script, env, candidate, baseline, review, events, marker = fixture(tmp_path)
    result = promote(script, env, candidate, baseline, review)
    assert result.returncode == 0, result.stderr
    assert 'release-dry-run-complete remote-mutations=0' in result.stdout
    assert json.loads(marker.read_text()) == {'commit': baseline}
    assert not any(word in events.read_text() for word in ['flock -n', 'wp db export', 'wp option update', 'wp cache flush'])


def test_apply_keeps_lease_through_backup_sync_verify_and_marker(tmp_path):
    script, env, candidate, baseline, review, events, marker = fixture(tmp_path)
    result = promote(script, env, candidate, baseline, review, apply=True)
    assert result.returncode == 0, result.stderr + result.stdout
    text = events.read_text()
    assert text.index('flock -n') < text.index('wp db export') < text.index('deploy-wp-code.sh') < text.index('wp bioco verify --runtime') < text.index('wp option update bioco_release_marker')
    assert json.loads(marker.read_text())['commit'] == candidate
    assert 'wp bioco import' not in text and 'editor-routing-guard' not in text


def test_code_only_rollback_restores_baseline_without_database_restore(tmp_path):
    script, env, candidate, baseline, review, events, marker = fixture(tmp_path)
    assert promote(script, env, candidate, baseline, review, apply=True).returncode == 0
    before = events.read_text()
    result = promote(script, env, candidate, baseline, review, apply=True, rollback=True)
    assert result.returncode == 0, result.stderr + result.stdout
    assert json.loads(marker.read_text())['commit'] == baseline
    assert json.loads(Path(env['BIOCO_TEST_PROD_MANIFEST']).read_text()) == json.loads(Path(env['BIOCO_TEST_BASELINE']).read_text())
    rollback_events = events.read_text()[len(before):]
    assert 'flock -n' in rollback_events and 'rollback-code-sync' not in rollback_events
    assert 'wp db export' not in rollback_events and 'wp db import' not in rollback_events


def test_first_unmarked_promotion_requires_explicit_bootstrap_and_exact_baseline(tmp_path):
    script, env, candidate, baseline, review, events, marker = fixture(tmp_path)
    marker.write_text('null')
    assert promote(script, env, candidate, baseline, review).returncode != 0
    review.write_text(json.dumps(json.loads(review.read_text()) | {'bootstrap_baseline': True}))
    assert promote(script, env, candidate, baseline, review).returncode != 0
    result = promote(script, env, candidate, baseline, review, bootstrap=True)
    assert result.returncode == 0, result.stderr
    assert 'remote-mutations=0' in result.stdout


@pytest.mark.parametrize('change', ['identity', 'review', 'staging', 'manifest', 'check', 'author'])
def test_conflicts_stop_before_production_lease_or_backup(tmp_path, change):
    script, env, candidate, baseline, review, events, marker = fixture(tmp_path)
    if change == 'identity': env['BIOCO_TEST_WRONG_IDENTITY'] = '1'
    if change == 'review': review.write_text(review.read_text().replace('"blocking_findings": []', '"blocking_findings": ["open"]'))
    if change == 'staging': Path(env['BIOCO_TEST_STAGE_MARKER']).write_text(json.dumps({'commit':baseline,'verify':'passed','smoke':'passed'}))
    if change == 'manifest': Path(env['BIOCO_TEST_PROD_MANIFEST']).write_text('{}')
    if change == 'check': env['BIOCO_TEST_BAD_CHECK'] = 'WordPress tests'
    if change == 'author': env['BIOCO_TEST_REVIEW_AUTHOR'] = 'other-user'
    result = promote(script, env, candidate, baseline, review, apply=True)
    assert result.returncode != 0
    assert json.loads(marker.read_text()) == {'commit': baseline}
    if events.exists(): assert 'flock -n' not in events.read_text() and 'wp db export' not in events.read_text()


def test_workflow_has_fixed_profile_separate_secrets_and_no_caller_url():
    workflow = (ROOT / '.github/workflows/deploy-wordpress-production.yml').read_text()
    assert 'environment: production' in workflow
    assert 'BIOCO_PROD_HOST: ${{ secrets.PRODUCTION_SSH_HOST }}' in workflow
    assert 'BIOCO_STAGE_HOST: ${{ secrets.STAGING_SSH_HOST }}' in workflow
    assert 'release-wordpress-production.py' in workflow
    assert 'review_evidence:' in workflow and 'expected_baseline:' in workflow
    assert 'BIOCO_RELEASE_URL:' not in workflow and 'wp bioco import' not in workflow
    assert 'github.ref == \'refs/heads/wordpress\'' in workflow
