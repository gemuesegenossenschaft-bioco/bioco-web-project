"""Drive the canonical staging entry point against an isolated fake host."""
import json
import hashlib
import os
from pathlib import Path
import shutil
import subprocess
import sys
import time

import pytest

from test_wordpress_release_package import fixture as package_fixture, ci_fixture, git


ROOT = Path(__file__).resolve().parents[1]


def fixture(tmp_path):
    repo, _, env = package_fixture(tmp_path)
    scripts = repo / 'wordpress/scripts'
    for plugin in ('bioco-core', 'bioco-content', 'bioco-forms', 'bioco-import'):
        (repo / 'wordpress/web/app/mu-plugins' / plugin / (plugin + '.php')).write_text('<?php /* plugin */\n')
    for name in ('release-wordpress-staging.sh', 'release-wordpress-staging.py',
                 'stage-wordpress-code.sh', 'wordpress_staging_lease.py',
                 'deploy-wp-code.sh', 'editorial-fingerprint.php'):
        source = ROOT / 'wordpress/scripts' / name
        if source.exists():
            shutil.copy2(source, scripts / name)
    git(repo, 'add', '.')
    git(repo, 'commit', '-qm', 'staging fixture')
    baseline = git(repo, 'rev-parse', 'HEAD')
    (repo / 'wordpress/web/app/mu-plugins/bioco-core/owned.php').write_text('<?php /* candidate */\n')
    git(repo, 'add', '.')
    git(repo, 'commit', '-qm', 'candidate')
    candidate = git(repo, 'rev-parse', 'HEAD')
    git(repo, 'update-ref', 'refs/remotes/origin/wordpress', candidate)
    env.update(BIOCO_WP_HOST='staging.example.test', BIOCO_WP_USER='deploy',
               BIOCO_WP_CONTENT='/srv/staging/wp-content',
               BIOCO_RELEASE_URL='https://staging.bioco.ch')
    host = tmp_path / 'host'
    content = host / 'site/wp-content'
    content.mkdir(parents=True)
    (host / 'marker.json').write_text(json.dumps({'commit': baseline, 'verify': 'passed', 'smoke': 'passed'}))
    (host / 'editorial').write_text('editor-authored content\n')
    for source, target in {
        'wordpress/web/app/mu-plugins': 'mu-plugins',
        'wordpress/web/app/themes': 'themes',
        'wordpress/content-seed': 'mu-plugins/bioco-import/content-seed',
    }.items():
        shutil.copytree(repo / source, content / target, dirs_exist_ok=True)
    (content / 'mu-plugins/bioco-core/owned.php').write_text('<?php /* public owned code */\n')
    shutil.copy2(repo / 'wordpress/deploy/bioco-mu-loader.php', content / 'mu-plugins/bioco-mu-loader.php')
    (content / 'uploads').mkdir()
    (content / 'uploads/editor.txt').write_text('editor media')
    (content / 'plugins').mkdir()
    (host / 'site/wp-includes').mkdir()
    (host / 'site/wp-includes/version.php').touch()
    bins = tmp_path / 'bin'
    bins.mkdir()
    shutil.copy2(ROOT / 'tests/fixtures/staging-host.py', bins / 'host.py')
    for mode in ('ssh', 'rsync', 'wp'):
        path = bins / mode
        path.write_text('#!/bin/sh\nexec ' + sys.executable + ' "' + str(bins / 'host.py') + '" ' + mode + ' "$@"\n')
        path.chmod(0o755)
    for name, event in [('render', 'smoke'), ('web-opcache', 'web-opcache')]:
        path = bins / name
        path.write_text('#!/bin/sh\nprintf "%s\\n" ' + event + ' >> "$BIOCO_TEST_HOST/events"\n'
                        + ('printf "%s\\n" "$BIOCO_RELEASE_RENDER_URL" "$BIOCO_RELEASE_RENDER_RESOLVE" >> "$BIOCO_TEST_HOST/render-env"\n' if name == 'render' else ''))
        path.chmod(0o755)
    env.update(PATH=str(bins) + os.pathsep + env['PATH'], BIOCO_TEST_HOST=str(host),
               BIOCO_WP_CONTENT=str(content), BIOCO_RELEASE_BACKUP_DIR=str(host / 'backups'),
               BIOCO_RELEASE_RENDER_GATE=str(bins / 'render'),
               BIOCO_RELEASE_WEB_OPCACHE_COMMAND=str(bins / 'web-opcache'))
    return repo, baseline, candidate, env


def command(repo, baseline, candidate, env, *args):
    return subprocess.run([str(repo / 'wordpress/scripts/release-wordpress-staging.sh'),
                           '--commit=' + candidate, '--expected-baseline=' + baseline, *args],
                          cwd=repo, env=env, text=True, capture_output=True, timeout=30)


def wait_for(path, process):
    deadline = time.monotonic() + 15
    while not path.exists() and time.monotonic() < deadline:
        assert process.poll() is None, process.communicate()
        time.sleep(0.02)
    assert path.exists(), 'fixture did not reach the release rendezvous'


def test_canonical_staging_refuses_production_before_any_transport(tmp_path):
    repo, baseline, candidate, env = fixture(tmp_path)
    env['BIOCO_RELEASE_URL'] = 'https://bioco.ch'
    result = command(repo, baseline, candidate, env, '--apply')
    assert result.returncode != 0
    assert 'fixed staging identity' in result.stdout + result.stderr
    assert not Path(env['TEST_GATE_CALLS']).exists()


def test_dry_run_builds_verified_package_without_remote_mutations(tmp_path):
    repo, baseline, candidate, env = fixture(tmp_path)
    host = Path(env['BIOCO_TEST_HOST'])
    before = (host / 'marker.json').read_bytes()
    result = command(repo, baseline, candidate, env)
    assert result.returncode == 0, result.stdout + result.stderr
    assert 'remote-mutations=0' in result.stdout
    assert (host / 'marker.json').read_bytes() == before
    assert not (host / 'events').exists()
    assert not (host / 'lock').exists()
    assert Path(env['TEST_GATE_CALLS']).read_text().splitlines() == ['checked']


def test_apply_installs_package_and_publishes_verified_receipt(tmp_path):
    repo, baseline, candidate, env = fixture(tmp_path)
    result = command(repo, baseline, candidate, env, '--apply')
    assert result.returncode == 0, result.stdout + result.stderr
    host = Path(env['BIOCO_TEST_HOST'])
    marker = json.loads((host / 'marker.json').read_text())
    assert marker['commit'] == candidate
    assert marker['previous'] == baseline
    assert len(marker['package_sha256']) == len(marker['validation_identity']) == 64
    assert marker['compatibility']['wordpress'] == '6.8.3'
    assert marker['compatibility']['divi'] == '5.0.0'
    assert marker['verify'] == marker['smoke'] == 'passed'
    assert (host / 'events').read_text().splitlines()[-1] == 'marker-under-lease'
    assert (host / 'site/wp-content/uploads/editor.txt').read_text() == 'editor media'
    assert (host / 'editorial').read_text() == 'editor-authored content\n'
    receipt = json.loads(next((repo / 'output/releases').glob('*.json')).read_text())
    assert receipt['status'] == 'success'
    assert receipt['installed_manifest']
    durable = Path(receipt['remote_receipt'])
    assert json.loads(durable.read_text())['status'] == 'success'
    assert durable.stat().st_mode & 0o077 == 0
    assert next((host / 'backups').glob('*.sql')).stat().st_mode & 0o077 == 0
    assert all(file.stat().st_mode & 0o077 == 0 for file in (repo / 'output/releases').iterdir())


@pytest.mark.parametrize('scenario', ['TEST_PARTIAL_SYNC', 'TEST_LOSE_LEASE', 'TEST_FAIL_RUNTIME',
                                     'TEST_EDIT_DURING_RUNTIME', 'TEST_TAMPER_AFTER_RUNTIME',
                                     'TEST_FAIL_MARKER', 'TEST_CHANGE_BASELINE'])
def test_failed_release_keeps_success_marker_and_records_installed_state(tmp_path, scenario):
    repo, baseline, candidate, env = fixture(tmp_path)
    env[scenario] = '1'
    host = Path(env['BIOCO_TEST_HOST'])
    result = command(repo, baseline, candidate, env, '--apply')
    assert result.returncode != 0, result.stdout
    assert 'marker-under-lease' not in (host / 'events').read_text() if (host / 'events').exists() else True
    marker = json.loads((host / 'marker.json').read_text())
    assert marker['commit'] == ('b' * 40 if scenario == 'TEST_CHANGE_BASELINE' else baseline)
    receipt = json.loads(next((repo / 'output/releases').glob('*.json')).read_text())
    assert receipt['status'] == 'failed'
    assert receipt['installed_manifest']
    assert receipt['error']
    if scenario != 'TEST_CHANGE_BASELINE':
        durable = json.loads(Path(receipt['remote_receipt']).read_text())
        assert durable['status'] == 'failed'
        assert durable['installed_manifest'] == receipt['installed_manifest']
    if scenario == 'TEST_CHANGE_BASELINE':
        assert 'changed while acquiring' in receipt['error']
        assert not (host / 'events').exists()
    if scenario == 'TEST_EDIT_DURING_RUNTIME':
        assert (host / 'editorial').read_text() == 'later editor revision'
    if scenario == 'TEST_PARTIAL_SYNC':
        assert receipt['installed_manifest']['mu-plugins/bioco-core/owned.php'] != hashlib.sha256(b'<?php /* public owned code */\n').hexdigest()
        retry = command(repo, baseline, candidate, {k: v for k, v in env.items() if k != scenario}, '--apply')
        assert retry.returncode != 0
        assert 'accepted baseline' in retry.stderr


def test_competing_callers_share_one_host_lease(tmp_path):
    repo, baseline, candidate, env = fixture(tmp_path)
    host = Path(env['BIOCO_TEST_HOST'])
    argv = [str(repo / 'wordpress/scripts/release-wordpress-staging.sh'),
            '--commit=' + candidate, '--expected-baseline=' + baseline, '--apply']
    first = subprocess.Popen(argv, cwd=repo, env=env | {'TEST_BLOCK_BACKUP': '1'},
                             stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    try:
        wait_for(host / 'backup-started', first)
        second = command(repo, baseline, candidate, env, '--apply')
        assert second.returncode != 0, second.stdout
        assert 'acquire staging server lease' in second.stderr
    finally:
        (host / 'continue').touch()
        stdout, stderr = first.communicate(timeout=20)
    assert first.returncode == 0, stdout + stderr
    assert (host / 'events').read_text().splitlines().count('sync') == 8
    assert (host / 'events').read_text().splitlines().count('marker-under-lease') == 1


def test_failed_holder_releases_lease_and_repeat_does_not_reinstall(tmp_path):
    repo, baseline, candidate, env = fixture(tmp_path)
    host = Path(env['BIOCO_TEST_HOST'])
    failed = command(repo, baseline, candidate, env | {'TEST_FAIL_BACKUP': '1'}, '--apply')
    assert failed.returncode != 0
    installed = command(repo, baseline, candidate, env, '--apply')
    assert installed.returncode == 0, installed.stdout + installed.stderr
    events = (host / 'events').read_text()
    marker = (host / 'marker.json').read_bytes()
    repeat = command(repo, baseline, candidate, env, '--apply')
    assert repeat.returncode == 0, repeat.stdout + repeat.stderr
    assert 'release-already-staged' in repeat.stdout
    assert (host / 'marker.json').read_bytes() == marker
    assert (host / 'events').read_text() == events + 'runtime\nsmoke\n'


def test_killed_coordinator_keeps_lease_until_inflight_worker_stops(tmp_path):
    repo, baseline, candidate, env = fixture(tmp_path)
    host = Path(env['BIOCO_TEST_HOST'])
    argv = [str(repo / 'wordpress/scripts/release-wordpress-staging.sh'),
            '--commit=' + candidate, '--expected-baseline=' + baseline, '--apply']
    with (tmp_path / 'orphan.log').open('w') as output:
        first = subprocess.Popen(argv, cwd=repo, env=env | {'TEST_BLOCK_BACKUP': '1'},
                                 stdout=output, stderr=output)
        try:
            wait_for(host / 'backup-started', first)
            first.kill()
            first.wait(timeout=5)
            second = command(repo, baseline, candidate, env, '--apply')
            assert second.returncode != 0, second.stdout
            assert 'acquire staging server lease' in second.stderr
        finally:
            (host / 'continue').touch()
            if first.poll() is None:
                first.kill()
                first.wait(timeout=5)


@pytest.mark.parametrize('scenario', ['valid', 'failed-run', 'altered-artifact'])
def test_canonical_staging_consumes_only_trusted_exact_ci_package(tmp_path, scenario):
    repo, baseline, candidate, env = fixture(tmp_path)
    _, _, env = ci_fixture(tmp_path, (repo, candidate, env))
    if scenario == 'failed-run':
        run = json.loads((tmp_path / 'run.json').read_text())
        run['conclusion'] = 'failure'
        (tmp_path / 'run.json').write_text(json.dumps(run))
    elif scenario == 'altered-artifact':
        (tmp_path / 'artifact.zip').write_bytes(b'changed')
    result = command(repo, baseline, candidate, env, '--package-run-id=123', '--apply')
    host = Path(env['BIOCO_TEST_HOST'])
    assert Path(env['TEST_GATE_CALLS']).read_text().splitlines() == ['checked']
    if scenario == 'valid':
        assert result.returncode == 0, result.stdout + result.stderr
        assert json.loads((host / 'marker.json').read_text())['validation'] == 'trusted-ci'
    else:
        assert result.returncode != 0
        assert not (host / 'events').exists()
        assert not (host / 'lock').exists()


@pytest.mark.parametrize('scenario', ['stale-marker', 'different-code', 'divergent-candidate'])
def test_stale_or_divergent_release_stops_before_validation_or_install(tmp_path, scenario):
    repo, baseline, candidate, env = fixture(tmp_path)
    host = Path(env['BIOCO_TEST_HOST'])
    if scenario == 'stale-marker':
        (host / 'marker.json').write_text(json.dumps({'commit': 'c' * 40}))
    elif scenario == 'different-code':
        (host / 'site/wp-content/mu-plugins/bioco-core/owned.php').write_text('<?php /* other accepted change */')
    else:
        git(repo, 'checkout', '-q', '--orphan', 'divergent')
        git(repo, 'commit', '-qm', 'unrelated candidate')
        candidate = git(repo, 'rev-parse', 'HEAD')
        git(repo, 'update-ref', 'refs/remotes/origin/wordpress', candidate)
    result = command(repo, baseline, candidate, env, '--apply')
    assert result.returncode != 0
    assert not Path(env['TEST_GATE_CALLS']).exists()
    assert not (host / 'events').exists()


@pytest.mark.parametrize('repeat', [False, True])
def test_smoke_target_cannot_inherit_production_origin_or_resolve(tmp_path, repeat):
    repo, baseline, candidate, env = fixture(tmp_path)
    if repeat:
        result = command(repo, baseline, candidate, env, '--apply')
        assert result.returncode == 0, result.stdout + result.stderr
    env.update(BIOCO_RELEASE_RENDER_URL='https://bioco.ch',
               BIOCO_RELEASE_RENDER_RESOLVE='bioco.ch:443:192.0.2.1',
               BIOCO_STAGING_URL='https://other.example.test')
    result = command(repo, baseline, candidate, env, '--apply')
    assert result.returncode == 0, result.stdout + result.stderr
    assert (Path(env['BIOCO_TEST_HOST']) / 'render-env').read_text().splitlines()[-2:] == ['https://staging.bioco.ch', '']
