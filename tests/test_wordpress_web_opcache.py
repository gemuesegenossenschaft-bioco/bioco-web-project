"""Scoped web OPcache and temporary-file cleanup. Coverage map: tests/README.md."""
import json
import os
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
PROBE = ROOT / 'wordpress/scripts/web-opcache-probe.php'
HELPER = ROOT / 'wordpress/scripts/flush-wordpress-web-opcache.sh'


@pytest.mark.parametrize('method,token,expires', [('GET', 'valid', 300), ('POST', 'wrong', 300), ('POST', 'valid', -1)])
def test_probe_rejects_unauthorized_or_expired_requests(tmp_path, method, token, expires):
    probe = tmp_path / 'probe.php'
    probe.write_text(PROBE.read_text())
    code = f"$biocoOpcacheToken='valid';$biocoOpcacheExpires=time()+({expires});$_SERVER['REQUEST_METHOD']='{method}';$_SERVER['HTTP_X_BIOCO_OPCACHE_TOKEN']='{token}';"
    code += f"register_shutdown_function(function(){{echo json_encode(['status'=>http_response_code()]);}});include '{probe}';"
    run = subprocess.run(['php', '-n', '-r', code], capture_output=True, text=True, check=True)
    assert json.loads(run.stdout)['status'] == 403
    assert probe.exists()


def test_probe_invalidates_only_owned_staging_code_and_deletes_itself(tmp_path):
    probe = tmp_path / 'probe.php'
    probe.write_text(PROBE.read_text())
    content = tmp_path / 'wp-content'
    content.mkdir()
    (tmp_path / 'wp-load.php').write_text('<?php // WordPress bootstrap boundary.\n')
    own = str(content / 'mu-plugins/bioco-core/bioco-core.php')
    unrelated = [str(content / 'plugins/vendor/plugin.php'), str(tmp_path / 'wp-includes/user.php'), str(tmp_path / 'production/wp-content/mu-plugins/bioco-core/bioco-core.php')]
    for path in [own] + unrelated:
        file = Path(path)
        file.parent.mkdir(parents=True, exist_ok=True)
        file.write_text('<?php return 1;')
    paths = json.dumps([own] + unrelated).replace("'", "\\'")
    code = "$biocoOpcacheToken='valid';$biocoOpcacheExpires=time()+300;$_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_X_BIOCO_OPCACHE_TOKEN']='valid';"
    code += f"$paths=json_decode('{paths}',true);foreach($paths as $path){{if(!opcache_compile_file($path))exit(1);}}"
    code += f"include '{probe}';fwrite(STDERR,json_encode(array_map('opcache_is_script_cached',$paths)));"
    extensions = []
    available = subprocess.run(['php', '-n', '-r', 'echo function_exists("opcache_get_status")?1:0;'], capture_output=True, text=True, check=True)
    if available.stdout != '1':
        extensions = ['-d', 'zend_extension=opcache']
    run = subprocess.run(['php', '-n', *extensions, '-d', 'opcache.enable_cli=1', '-d', 'opcache.file_update_protection=0', '-r', code], capture_output=True, text=True)
    assert run.returncode == 0, run.stdout + run.stderr
    assert json.loads(run.stdout) == {'ok': True, 'invalidated': 1, 'security_hook': False}
    assert json.loads(run.stderr) == [False, True, True, True]
    assert not probe.exists()


@pytest.mark.parametrize('http_fails', [False, True])
def test_helper_cleans_temporary_file_after_success_or_http_failure(tmp_path, http_fails):
    wp_root = tmp_path / 'wp'
    wp_root.mkdir()
    ssh = tmp_path / 'ssh'
    ssh.write_text('#!/usr/bin/env bash\nset -euo pipefail\nbash -c "${@: -1}"\n')
    ssh.chmod(0o755)
    curl = tmp_path / 'curl'
    curl.write_text('#!/usr/bin/env bash\nset -euo pipefail\n'
                    'if [[ "${@: -1}" == */wp-json/wp/v2/users ]]; then printf 401; exit 0; fi\n'
                    'probe=("$BIOCO_TEST_WP_ROOT"/bioco-opcache-*.php)\n'
                    'php -l "${probe[0]}" >/dev/null\n'
                    'result="$(php -r \'register_shutdown_function(function(){echo json_encode(["status"=>http_response_code()]);}); include $argv[1];\' "${probe[0]}")"\n'
                    '[[ "$result" == \'{"status":403}\' ]]\n'
                    + ('exit 22\n' if http_fails else 'printf \'{"ok":true,"invalidated":2}\'\n'))
    curl.chmod(0o755)
    env = os.environ | {'BIOCO_WP_HOST': 'staging.example.test', 'BIOCO_WP_USER': 'deploy', 'BIOCO_WP_CONTENT': str(wp_root / 'wp-content'), 'BIOCO_RELEASE_URL': 'https://staging.example.test', 'BIOCO_RELEASE_SSH_BIN': str(ssh), 'BIOCO_RELEASE_CURL_BIN': str(curl), 'BIOCO_TEST_WP_ROOT': str(wp_root)}
    run = subprocess.run([str(HELPER)], env=env, capture_output=True, text=True)
    assert (run.returncode != 0) is http_fails
    assert list(wp_root.glob('bioco-opcache-*.php')) == []
    if not http_fails:
        assert 'web-opcache invalidated=2' in run.stdout
