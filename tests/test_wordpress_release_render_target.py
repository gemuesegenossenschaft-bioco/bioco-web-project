"""Execute the shared route gate with production URLs and controlled curl traffic."""

import os
import subprocess
import sys
from pathlib import Path

import pytest
import yaml


ROOT = Path(__file__).resolve().parents[1]
GATE = ROOT / 'tests/wordpress-staging-render-gate.sh'


@pytest.mark.parametrize('resolve', [None, '', 'bioco.ch:443:127.0.0.1'])
def test_production_render_gate_never_uses_staging_resolve(tmp_path, resolve):
    stub_dir = tmp_path / 'bin'
    stub_dir.mkdir()
    curl = stub_dir / 'curl'
    curl.write_text('''#!/usr/bin/env bash
printf 'CALL %s\\n' "$*" >> "$BIOCO_TEST_CURL_LOG"
while (($#)); do
  if [[ "$1" == -o ]]; then shift; output=$1; fi
  shift
done
printf '<main>Production content</main>' > "$output"
printf 200
''')
    curl.chmod(0o755)
    python = stub_dir / 'python3'
    python.write_text('''#!/usr/bin/env bash
if [[ "$1" == */wordpress/scripts/check-editor-assets.py ]]; then
  printf '%s\\n' "$*" >> "$BIOCO_TEST_ASSET_LOG"
  exit 0
fi
exec "$BIOCO_TEST_PYTHON" "$@"
''')
    python.chmod(0o755)
    curl_log, asset_log = tmp_path / 'curl.log', tmp_path / 'assets.log'
    env = os.environ.copy()
    env.update(PATH=str(stub_dir) + os.pathsep + env['PATH'],
               BIOCO_TEST_PYTHON=sys.executable, BIOCO_TEST_CURL_LOG=str(curl_log),
               BIOCO_TEST_ASSET_LOG=str(asset_log), BIOCO_RELEASE_RENDER_URL='https://bioco.ch',
               BIOCO_STAGING_URL='https://staging.bioco.ch',
               BIOCO_STAGING_RESOLVE='staging.bioco.ch:443:192.0.2.1')
    env.pop('BIOCO_RELEASE_RENDER_RESOLVE', None)
    if resolve is not None:
        env['BIOCO_RELEASE_RENDER_RESOLVE'] = resolve
    result = subprocess.run(['bash', str(GATE)], cwd=ROOT, env=env,
                            capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
    calls = curl_log.read_text().splitlines()
    assert len(calls) == 22
    assert all('https://bioco.ch/' in call and 'staging.bioco.ch' not in call for call in calls)
    if resolve:
        assert all('--resolve ' + resolve in call for call in calls)
    else:
        assert all('--resolve' not in call for call in calls)
    assert asset_log.read_text().strip().endswith('--url https://bioco.ch')
    assert 'WORDPRESS_RENDER_GATE: PASS (22 routes, editor assets)' in result.stdout


def test_production_workflow_can_read_required_check_results():
    workflow = yaml.safe_load((ROOT / '.github/workflows/deploy-wordpress-production.yml').read_text())
    assert workflow['permissions']['checks'] == 'read'
