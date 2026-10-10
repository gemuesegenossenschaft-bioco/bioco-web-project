"""Exercise the real staging guard installer against a filesystem boundary."""
import json
import os
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]
INSTALLER = ROOT / 'wordpress/web/app/mu-plugins/bioco-import/includes/editor-routing.php'
GUARD = ROOT / 'wordpress/web/app/mu-plugins/bioco-core/content/editor-asset-guard.conf'


def install(root, backup, apply=True):
    code = "define('ABSPATH', '/'); require getenv('BIOCO_INSTALLER'); try {echo json_encode(bioco_import_install_editor_guard(getenv('BIOCO_TEST_ROOT'),getenv('BIOCO_TEST_BACKUP'),getenv('BIOCO_TEST_APPLY') === '1'));} catch (RuntimeException $e) {echo json_encode(['error'=>$e->getMessage()]);}"
    result = subprocess.run(['php', '-r', code], check=True, text=True, capture_output=True,
                            env=os.environ | {'BIOCO_INSTALLER': str(INSTALLER), 'BIOCO_TEST_ROOT': str(root),
                            'BIOCO_TEST_BACKUP': str(backup), 'BIOCO_TEST_APPLY': '1' if apply else '0'})
    return json.loads(result.stdout)


@pytest.fixture
def site(tmp_path):
    root = tmp_path / 'wp'
    root.mkdir()
    original = b'# BEGIN WordPress\nRewriteEngine On\n# END WordPress\n\n# cPanel\nAddHandler application/x-httpd-alt-php82 .php\n'
    (root / '.htaccess').write_bytes(original)
    (root / '.htaccess').chmod(0o640)
    return root, tmp_path / 'before.htaccess', original


def test_dry_run_does_not_mutate_config_or_create_a_backup(site):
    root, backup, original = site
    assert install(root, backup, False) == {'changed': True, 'applied': False, 'backup': None}
    assert (root / '.htaccess').read_bytes() == original
    assert not backup.exists()


def test_apply_preserves_handlers_and_wordpress_bytes_and_is_idempotent(site):
    root, backup, original = site
    assert install(root, backup)['applied']
    expected = GUARD.read_bytes() + b'\n' + original
    assert (root / '.htaccess').read_bytes() == expected
    assert backup.read_bytes() == original
    assert backup.stat().st_mode & 0o777 == 0o600
    assert (root / '.htaccess').stat().st_mode & 0o777 == 0o640
    assert install(root, backup) == {'changed': False, 'applied': False, 'backup': None}
    assert not list(root.glob('.htaccess.bioco-editor-*'))


@pytest.mark.parametrize('bad_config', [
    '# BEGIN bioco editor asset guard\nmissing end',
    '# END bioco editor asset guard\n',
    '# BEGIN bioco editor asset guard\n# END bioco editor asset guard\n' * 2,
])
def test_malformed_managed_regions_fail_without_writes(site, bad_config):
    root, backup, _ = site
    (root / '.htaccess').write_text(bad_config)
    assert 'Malformed' in install(root, backup)['error']
    assert (root / '.htaccess').read_text() == bad_config
    assert not backup.exists()


def test_backup_must_be_private_and_existing_backups_are_never_replaced(site):
    root, backup, original = site
    assert 'outside' in install(root, root / 'public-backup')['error']
    backup.write_text('existing')
    assert 'Cannot create' in install(root, backup)['error']
    assert backup.read_text() == 'existing'
    assert (root / '.htaccess').read_bytes() == original
