"""A draft builder must never load the site's active Theme Builder layouts."""
import json
import os
import subprocess
from pathlib import Path

import pytest

SCRIPT = Path(__file__).parents[1] / 'wordpress/web/app/mu-plugins/bioco-core/includes/editor-verification.php'
HARNESS = r'''
define('ABSPATH', '/');
$state = json_decode(getenv('BIOCO_ISOLATION_STATE'), true);
function add_filter($name, $callback, $priority) {}
function get_queried_object_id() { return 5; }
function get_post_meta($id, $key, $single) { return $GLOBALS['state']['run']; }
function get_post_status($id) { return $GLOBALS['state']['status']; }
function current_user_can($capability, $id) { return $GLOBALS['state']['can_edit']; }
include getenv('BIOCO_ISOLATION_SCRIPT');
echo json_encode(bioco_editor_verification_layouts($state['layouts'] ?? ['header' => ['id' => 299], 'footer' => ['id' => 303]]));
'''


@pytest.mark.parametrize('run,status,can_edit,isolated', [
    ('qa-20261009', 'draft', True, True),
    ('qa-20261009', 'auto-draft', True, True),
    ('qa-20261009', 'publish', True, False),
    ('qa-20261009', 'draft', False, False),
    ('', 'draft', True, False),
    ('invalid run', 'draft', True, False),
])
def test_only_tagged_authorized_drafts_hide_global_layouts(run, status, can_edit, isolated):
    result = subprocess.run(['php', '-r', HARNESS], check=True, capture_output=True, text=True,
                            env=os.environ | {'BIOCO_ISOLATION_SCRIPT': str(SCRIPT),
                                              'BIOCO_ISOLATION_STATE': json.dumps(dict(run=run, status=status, can_edit=can_edit))})
    layouts = json.loads(result.stdout)
    assert (layouts == []) is isolated


def test_standalone_layout_copy_keeps_only_its_own_builder_context():
    result = subprocess.run(['php', '-r', HARNESS], check=True, capture_output=True, text=True,
                            env=os.environ | {'BIOCO_ISOLATION_SCRIPT': str(SCRIPT),
                                              'BIOCO_ISOLATION_STATE': json.dumps(dict(
                                                  run='qa-20261009', status='draft', can_edit=True,
                                                  layouts={'header': {'id': 5}, 'footer': {'id': 303}, 'template': 299}))})
    assert json.loads(result.stdout) == {'header': {'id': 5}}
