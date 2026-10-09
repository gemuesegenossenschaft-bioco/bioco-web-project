"""Execute the generator and model its RewriteRule subset; actual Apache is an operator gate.

Keep/Replace/Remove map: tests/README.md. No network or server mutations.
"""
import re
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]
SCRIPT = ROOT / 'wordpress/scripts/generate-production-routing.py'


def generated(*args):
    return subprocess.run(['python3', str(SCRIPT), *args], check=True, capture_output=True, text=True).stdout


def route(path, host='bioco.ch', original=None, config=None, with_flags=False):
    """Evaluate emitted rules, including immutable THE_REQUEST and host conditions.

    This is deliberately not an Apache implementation: no symlink, PHP, directory,
    vhost, URL-decoding, or WordPress canonical behavior is claimed.
    """
    variables = {'HTTP_HOST': host, 'THE_REQUEST': f'GET {original or path} HTTP/1.1', 'REQUEST_URI': original or path, 'ENV:REDIRECT_STATUS': '200' if original is not None else ''}
    conditions = []
    for line in (config or generated()).splitlines():
        if line.startswith('RewriteCond '):
            _, variable, pattern, *flags = line.split()
            value = variables[variable[2:-1]]
            negate = pattern.startswith('!')
            matched = bool(re.search(pattern.lstrip('!'), value, re.I if '[NC]' in flags else 0))
            conditions.append(not matched if negate else matched)
        elif line.startswith('RewriteRule '):
            _, pattern, target, flags = line.split()
            match = re.search(pattern, path.lstrip('/'), re.I if 'NC' in flags else 0)
            allowed = all(conditions)
            conditions = []
            if not match or not allowed:
                continue
            if 'F,' in flags:
                return 403, path
            target = path if target == '-' else target
            for key, value in variables.items():
                target = target.replace('%{' + key + '}', value)
            target = re.sub(r'\$(\d)', lambda m: match.group(int(m[1])) or '', target)
            result = ((301 if 'R=301' in flags else 200), target)
            return (*result, flags) if with_flags else result
    raise AssertionError('No terminal rule')


@pytest.mark.parametrize('path,target', [
    ('/', '/_bioco_wp/index.php'), ('/wir/', '/_bioco_wp/index.php'),
    ('/wp-admin/', '/_bioco_wp/wp-admin/'), ('/wp-admin/admin-ajax.php', '/_bioco_wp/wp-admin/admin-ajax.php'),
    ('/wp-content/uploads/image.jpg', '/_bioco_wp/wp-content/uploads/image.jpg'),
    ('/wp-includes/js/jquery.js', '/_bioco_wp/wp-includes/js/jquery.js'),
    ('/wp-login.php', '/_bioco_wp/wp-login.php'), ('/wp-cron.php', '/_bioco_wp/wp-cron.php'),
    ('/index.php', '/_bioco_wp/index.php'), ('/xmlrpc.php', '/_bioco_wp/xmlrpc.php'),
    ('/robots.txt', '/_bioco_wp/index.php'), ('/sitemap_index.xml', '/_bioco_wp/index.php'),
    ('/wp-sitemap.xml', '/_bioco_wp/index.php'), ('/page-sitemap.xml', '/_bioco_wp/index.php'),
    ('/old-root.php', '/_bioco_wp/index.php'),
])
def test_public_routes_override_dormant_wp_files_and_symlinks(path, target):
    assert route(path) == (200, target)
    # Original request remains public during internal rewrite rounds.
    assert route(target, original=path) == (200, target)


@pytest.mark.parametrize('path', ['/cms/api/', '/matomo/index.php', '/cloud/', '/rezepte/test', '/wiki/', '/.well-known/acme-challenge/token', '/images/old.jpg', '/_next/static/legacy.js'])
def test_independent_paths_pass_through(path):
    assert route(path) == (200, path)
    assert route(path, with_flags=True)[2] == '[L]'


@pytest.mark.parametrize('host', ['cms.bioco.ch', 'matomo.bioco.ch', 'cloud.bioco.ch'])
def test_other_vhosts_are_not_captured(host):
    assert route('/index.php', host) == (200, '/index.php')
    assert route('/wp-admin', host) == (200, '/wp-admin')
    assert route('/index.php', host, with_flags=True)[2] == '[L]'


@pytest.mark.parametrize('path', ['/_bioco_wp/', '/_bioco_wp/wp-login.php', '/_bioco_wp%2fwp-login.php', '/.env', '/.git/config', '/wp-config.php', '/wp-config.php.bak', '/wp-content/uploads/db.sql.gz', '/private/data.json', '/backups/site.zip', '/composer.lock', '/cms-api/wir.json'])
def test_default_deny(path):
    assert route(path)[0] == 403


def test_canonical_admin_slash_and_www():
    assert route('/wp-admin') == (301, '/wp-admin/')
    assert route('/wp-admin/') == (200, '/_bioco_wp/wp-admin/')
    assert route('/wir/', 'www.bioco.ch') == (301, 'https://bioco.ch/wir/')


def test_php_handler_is_explicit_and_generator_only_prints(tmp_path):
    result = subprocess.run(['python3', str(SCRIPT)], cwd=tmp_path, check=True, capture_output=True, text=True)
    assert list(tmp_path.iterdir()) == []
    assert 'AddHandler application/x-httpd-ea-php81' in result.stdout
    assert 'AddHandler application/x-httpd-alt-php82' not in result.stdout
    assert 'AddHandler application/x-httpd-ea-php82' not in result.stdout
    assert 'Default deny policy v1' in result.stdout


def test_decoded_direct_internal_path_is_denied():
    assert route('/_bioco_wp/wp-login.php', original=None)[0] == 403
    assert route('/_bioco_wp/wp-login.php', original='/wp-login.php') == (200, '/_bioco_wp/wp-login.php')


def test_internal_wp_round_stops_child_rewrites():
    assert route('/_bioco_wp/index.php', original='/', with_flags=True)[2] == '[END]'


@pytest.mark.parametrize('path', ['/images/.env', '/_next/static/private/db.sql', '/images/site.zip'])
def test_legacy_asset_passthrough_does_not_bypass_denials(path):
    assert route(path)[0] == 403


LEAFLET_ROOT = '/wp-content/mu-plugins/bioco-core/assets/vendor/leaflet/'
LEAFLET_FILES = ['leaflet.css', 'leaflet.js', 'images/layers.png', 'images/layers-2x.png',
                 'images/marker-icon.png', 'images/marker-icon-2x.png', 'images/marker-shadow.png']


@pytest.mark.parametrize('name', LEAFLET_FILES)
def test_shipped_leaflet_assets_route_through_both_rewrite_rounds(name):
    path = LEAFLET_ROOT + name
    assert (ROOT / 'wordpress/web/app/mu-plugins/bioco-core/assets/vendor/leaflet' / name).is_file()
    target = '/_bioco_wp' + path
    assert route(path) == (200, target)
    assert route(target, original=path) == (200, target)
    assert route(target)[0] == 403
    assert route(path, 'www.bioco.ch') == (301, 'https://bioco.ch' + path)


@pytest.mark.parametrize('path', [
    LEAFLET_ROOT + 'LICENSE', LEAFLET_ROOT + 'leaflet.js.map',
    LEAFLET_ROOT + 'images/unknown.png', LEAFLET_ROOT + 'leaflet.js/config.php',
    LEAFLET_ROOT + '.env', LEAFLET_ROOT + '../private/config.php',
    '/wp-content/mu-plugins/other/assets/vendor/leaflet/leaflet.js',
    '/vendor/leaflet/leaflet.css', '/wp-content/vendor/config.php',
    '/wp-config.php', '/composer.json',
])
def test_leaflet_allowlist_preserves_vendor_and_config_denial(path):
    assert route(path)[0] == 403
    assert route('/_bioco_wp' + path, original=path)[0] == 403


def test_manifest_pdf_redirect_precedes_static_content_routing():
    import json
    manifest = ROOT / 'wordpress/web/app/mu-plugins/bioco-core/content/redirects.json'
    redirect = next(row for row in json.loads(manifest.read_text()) if row['source'].startswith('/wp-content/'))
    assert route(redirect['source']) == (301, redirect['destination'])
    # The dot is literal, and the rule only matches the entire legacy path.
    assert route(redirect['source'].replace('.pdf', 'Xpdf')) == (200, '/_bioco_wp' + redirect['source'].replace('.pdf', 'Xpdf'))
    assert route(redirect['source'] + '/extra') == (200, '/_bioco_wp' + redirect['source'] + '/extra')


def routing_module():
    import importlib.util
    spec = importlib.util.spec_from_file_location('production_routing', SCRIPT)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


@pytest.mark.parametrize('field,value', [
    ('source', '/wp-content/uploads/../bad.pdf'),
    ('source', '/wp-content/uploads/bad.pdf\nRewriteRule'),
    ('source', '/wp-content/uploads/bad%20.pdf'),
    ('source', '/wp-content/uploads/bad.php'),
    ('destination', '//evil.example/path'), ('destination', 'https://evil.example/'),
    ('destination', '/abos?x=1'), ('destination', '/abos [END]'),
    ('destination', '/abos/../config'), ('destination', '/abos\\config'),
])
def test_asset_redirect_rejects_unsafe_paths(tmp_path, field, value):
    import json
    module = routing_module()
    row = {'source': '/wp-content/uploads/legacy.pdf', 'destination': '/abos', 'permanent': True}
    row[field] = value
    module.REDIRECT_MANIFEST = tmp_path / 'redirects.json'
    module.REDIRECT_MANIFEST.write_text(json.dumps([row]))
    with pytest.raises(ValueError):
        module.generate()


def test_asset_redirect_regex_escapes_manifest_filename(tmp_path):
    import json
    module = routing_module()
    source = '/wp-content/uploads/legacy.v2.pdf'
    module.REDIRECT_MANIFEST = tmp_path / 'redirects.json'
    module.REDIRECT_MANIFEST.write_text(json.dumps([{'source': source, 'destination': '/abos', 'permanent': True}]))
    config = module.generate()
    assert route(source, config=config) == (301, '/abos')
    assert route(source.replace('.v2', 'Xv2'), config=config)[0] == 200
