"""Real Apache routing and HTTP gate tests. See tests/README.md.

No WordPress/Divi installation is needed. Private files are sentinel fixtures;
Apache executes the actual generated .htaccess, including its internal rewrites.
"""
import importlib.util
import os
import shutil
import socket
import subprocess
import time
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

import pytest

ROOT = Path(__file__).parents[1]


def load_script(name):
    spec = importlib.util.spec_from_file_location(name, ROOT / 'wordpress/scripts' / name)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


@pytest.fixture(scope='module')
def apache_site(tmp_path_factory):
    binary = shutil.which('httpd') or shutil.which('apache2')
    modules = next((path for path in (
        Path('/usr/libexec/apache2'), Path('/usr/lib/apache2/modules'),
    ) if (path / 'mod_rewrite.so').exists()), None)
    if not binary or not modules:
        if os.environ.get('BIOCO_APACHE_TESTS') == '1':
            pytest.fail('Apache and mod_rewrite are required for the release gate')
        pytest.skip('Install Apache or set BIOCO_APACHE_TESTS=1 to require it')
    root = tmp_path_factory.mktemp('apache-editor')
    docroot = root / 'public'
    docroot.mkdir()
    private_wp = root / 'wordpress'
    private_wp.mkdir()
    (docroot / '_bioco_wp').symlink_to(private_wp, target_is_directory=True)
    gate = load_script('check-editor-assets.py')
    config = gate.routing.generate()
    (docroot / '.htaccess').write_text(config)
    for path in gate.PUBLIC_ASSETS:
        asset = private_wp / path.lstrip('/')
        asset.parent.mkdir(parents=True, exist_ok=True)
        asset.write_text('window.biocoRoutingSentinel = true;\n')
    for path in ('vendor/autoload.php', 'wp-config.php', '.env',
                 'wp-includes/js/dist/vendor/config.php',
                 'wp-includes/js/dist/vendor/react.min.js.map'):
        asset = private_wp / path
        asset.parent.mkdir(parents=True, exist_ok=True)
        asset.write_text('PRIVATE_SENTINEL_MUST_NOT_BE_SERVED')
    # A stale root file must not win over the routed WordPress asset.
    stale = docroot / 'wp-includes/js/dist/vendor/react.min.js'
    stale.parent.mkdir(parents=True)
    stale.write_text('STALE_ROOT_ASSET')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    module_names = ['mpm_event', 'authz_core', 'authz_host', 'mime', 'rewrite']
    # macOS ships unixd as a DSO; Ubuntu compiles it into the server.
    if (modules / 'mod_unixd.so').exists():
        module_names.append('unixd')
    # CI may run as root; give Apache's worker traverse access to pytest's parents.
    identity = ''
    if os.geteuid() == 0:
        identity = 'User nobody\nGroup nogroup\n'
        for path in (root, *root.parents):
            if path == Path('/tmp') or path == Path('/'):
                break
            path.chmod(path.stat().st_mode | 0o055)
    apache_config = root / 'httpd.conf'
    apache_config.write_text(
        f'ServerRoot "{root}"\nPidFile "{root}/httpd.pid"\n'
        f'Listen 127.0.0.1:{port}\nServerName bioco.ch\n' + identity
        + ''.join(f'LoadModule {name}_module "{modules}/mod_{name}.so"\n'
                  for name in module_names)
        + f'ErrorLog "{root}/error.log"\nLogLevel warn\n'
        + f'DocumentRoot "{docroot}"\nTypesConfig /dev/null\n'
        + 'AddType application/javascript .js\n'
        + f'<Directory "{root}">\nRequire all granted\n'
          'Options FollowSymLinks\nAllowOverride All\n</Directory>\n')
    command = [binary, '-f', str(apache_config)]
    syntax = subprocess.run([*command, '-t'], capture_output=True, text=True)
    assert syntax.returncode == 0, syntax.stderr
    process = subprocess.Popen([*command, '-DFOREGROUND'], stdout=subprocess.PIPE,
                               stderr=subprocess.PIPE)
    base_url = f'http://127.0.0.1:{port}'
    try:
        for _ in range(100):
            if process.poll() is not None:
                pytest.fail(process.communicate()[1].decode())
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1):
                    break
            except OSError:
                time.sleep(.05)
        else:
            pytest.fail('Apache did not start')
        yield base_url, docroot, config, gate
    finally:
        process.terminate()
        try:
            process.communicate(timeout=10)
        except subprocess.TimeoutExpired:
            process.kill()
            process.communicate()


def get(base_url, path, host='bioco.ch'):
    request = Request(base_url + path, headers={'Host': host})
    try:
        with urlopen(request, timeout=5) as response:
            return response.status, response.read(), response.headers
    except HTTPError as error:
        return error.code, error.read(), error.headers


def test_apache_serves_every_editor_dependency_from_private_clone(apache_site):
    base, _, _, gate = apache_site
    for path in gate.PUBLIC_ASSETS:
        status, body, headers = get(base, path + '?ver=7.1.3')
        assert status == 200, path
        assert body == b'window.biocoRoutingSentinel = true;\n', path
        assert headers.get_content_type() == 'application/javascript'


@pytest.mark.parametrize('path', [
    '/_bioco_wp/wp-includes/js/dist/vendor/react.min.js',
    '/_bioco_wp%2fwp-includes/js/dist/vendor/react.min.js',
    '/wp-config.php', '/.env', '/vendor/autoload.php',
    '/wp-includes/js/dist/vendor/config.php',
    '/wp-includes/js/dist/vendor/react.min.js.map',
    '/wp-includes/js/dist/vendor/react.min.js/config.php',
    '/wp-includes/js/dist/vendor/%2eenv',
])
def test_apache_keeps_private_vendor_resources_denied(apache_site, path):
    status, body, _ = get(apache_site[0], path)
    # With AllowEncodedSlashes off Apache rejects encoded '/' before rewrite.
    assert status == (404 if '%2f' in path else 403), path
    assert b'PRIVATE_SENTINEL' not in body


def test_original_vendor_denial_breaks_editor_under_real_apache(apache_site):
    base, docroot, config, gate = apache_site
    original = '\n'.join(line for line in config.splitlines()
                         if gate.routing.CORE_VENDOR_PATTERN not in line)
    # Remove the orphaned www condition along with the removed rule.
    original = original.replace(
        'RewriteCond %{HTTP_HOST} ^www\\.bioco\\.ch(?::[0-9]+)?$ [NC]\n'
        'RewriteRule (^|/)', 'RewriteRule (^|/)')
    try:
        (docroot / '.htaccess').write_text(original)
        assert get(base, '/wp-includes/js/dist/vendor/react.min.js')[0] == 403
    finally:
        (docroot / '.htaccess').write_text(config)
    assert get(base, '/wp-includes/js/dist/vendor/react.min.js')[0] == 200


def test_asset_probe_rejects_html_and_private_file_success():
    from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
    from threading import Thread
    gate = load_script('check-editor-assets.py')

    class Handler(BaseHTTPRequestHandler):
        def do_GET(self):
            self.send_response(404 if self.path == '/denied' else 200)
            self.send_header('Content-Type', 'text/html' if self.path == '/html'
                             else 'application/javascript')
            self.end_headers()
            self.wfile.write(b'<html>Login</html>' if self.path == '/html'
                             else b'window.React = {};')

        def log_message(self, *args):
            pass

    with ThreadingHTTPServer(('127.0.0.1', 0), Handler) as server:
        worker = Thread(target=server.serve_forever, daemon=True)
        worker.start()
        base = f'http://127.0.0.1:{server.server_port}'
        try:
            assert gate.probe(base, '/react.js', True)['ok']
            assert not gate.probe(base, '/html', True)['ok']
            assert not gate.probe(base, '/react.js', False)['ok']
            assert gate.probe(base, '/denied', False)['ok']
            assert not gate.probe(base, '/denied', True)['ok']
        finally:
            server.shutdown()
            worker.join(timeout=5)
