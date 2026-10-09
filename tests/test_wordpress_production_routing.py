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
