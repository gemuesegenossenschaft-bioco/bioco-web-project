"""The converter must not replace Divi sizing or unrelated image filters."""
import json
import subprocess
from pathlib import Path

import pytest


CALLBACK = (Path(__file__).parents[1] / 'wordpress/web/app/mu-plugins/bioco-core/includes/image-delivery.php')


@pytest.mark.parametrize('plugin,admin,removed', [(True, False, True), (False, False, False), (True, True, False)])
def test_late_theme_filters_are_removed_only_for_frontend_conversion(plugin, admin, removed):
    code = '''
define('ABSPATH', '/fixture/');
$actions = []; $filters = [];
function add_action($hook, $callback, $priority = 10) { $GLOBALS['actions'][$hook][] = $callback; }
function remove_filter($hook, $callback, $priority = 10) { unset($GLOBALS['filters'][$hook][$callback]); }
function is_admin() { return ADMIN_REQUEST; }
'''
    code += f"define('ADMIN_REQUEST', {'true' if admin else 'false'});\n"
    if plugin:
        code += "function webp_uploads_get_image_output_format() { return 'webp'; }\n"
    code += f"require '{CALLBACK}';\n"
    # Divi registers these after after_setup_theme. Exercise the lifecycle,
    # including another plugin's filter that must remain in the chain.
    code += '''
foreach ($actions['after_setup_theme'] ?? [] as $callback) { $callback(); }
$filters = [
 'wp_calculate_image_srcset' => ['et_filter_wp_calculate_image_srcset'=>true, 'other_plugin'=>true],
 'wp_calculate_image_sizes' => ['et_filter_wp_calculate_image_sizes'=>true, 'other_plugin'=>true],
];
foreach ($actions['wp_loaded'] ?? [] as $callback) { $callback(); }
echo json_encode($filters);
'''
    result = subprocess.run(['php', '-r', code], text=True, capture_output=True, check=True)
    assert not result.stderr
    filters = json.loads(result.stdout)
    for hook in ('srcset', 'sizes'):
        values = filters['wp_calculate_image_' + hook]
        assert values['other_plugin']
        assert ('et_filter_wp_calculate_image_' + hook not in values) == removed
