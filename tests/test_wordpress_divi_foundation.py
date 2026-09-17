"""Live Divi values are safe CSS, and the deploy bundle matches its seed source."""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / 'wordpress/web/app/mu-plugins/bioco-core'


def test_deployed_manifest_matches_source():
    subprocess.run(['python3', str(ROOT / 'wordpress/scripts/build-divi-design-system.py'), '--check'], check=True)


def run_bridge(value, status='active'):
    # Real bridge, fake vendor storage boundary. Test runtime semantics rather
    # than searching PHP source for a particular implementation.
    code = r'''<?php
namespace ET\Builder\Packages\GlobalData {
class GlobalData {
    public static function get_global_colors() {return $GLOBALS['colors'];}
    public static function get_global_variables() {return ['numbers'=>(object) [],'strings'=>(object) [],'fonts'=>(object) []];}
}}
namespace {
define('ABSPATH','/'); define('BIOCO_CORE_DIR', %s);
function add_action(...$args) {} function add_shortcode(...$args) {}
require BIOCO_CORE_DIR.'/includes/design-system.php';
$token=bioco_divi_design_manifest()['tokens']['colors'][0];
$GLOBALS['colors']=[bioco_divi_token_id($token,'colors')=>['color'=>%s,'status'=>%s]];
echo bioco_divi_token_css();
}'''
    return subprocess.check_output(['php'], input=(code % (json.dumps(str(CORE)), json.dumps(value), json.dumps(status))).encode()).decode()


def test_editor_value_overrides_seed_without_rebuild():
    assert '--wp--preset--color--bioco-green:#123456' in run_bridge('#123456')


def test_archived_value_keeps_static_fallback():
    assert run_bridge('#123456', 'archived') == ''


def test_stylesheet_injection_is_rejected():
    for value in ['red;}body{display:none', '</style><script>alert(1)</script>', 'url(https://example.test/track)', 'expression(alert(1))', 'red;--bad:1']:
        assert run_bridge(value) == ''


IMPORT = ROOT / 'wordpress/web/app/mu-plugins/bioco-import/includes/design-system.php'

# Real seed(), fake vendor storage and REST boundary. The REST fake records
# every write route so tests can assert what an apply would have touched.
SEED_HARNESS = r'''<?php
namespace ET\Builder\Packages\GlobalData {
class GlobalData {
    public static function get_global_colors() {return $GLOBALS['colors'];}
    public static function get_global_variables() {return ['numbers'=>(object) [],'strings'=>(object) [],'fonts'=>(object) []];}
}
class GlobalPreset {public static function get_data() {return ['module'=>[],'group'=>[]];}}
}
namespace {
define('ABSPATH','/'); define('BIOCO_CORE_DIR', %s); define('ET_BUILDER_VERSION','5.0.0');
$GLOBALS['REST_CALLS'] = [];
function add_action(...$a) {} function add_shortcode(...$a) {}
function current_user_can(...$a) {return true;}
function wp_create_nonce($a) {return 'nonce';}
function wp_json_encode($v) {return json_encode($v);}
class WP_REST_Request {public $route; public function __construct($m=null, $r=null) {$this->route = $r;} public function set_header(...$a) {} public function set_body(...$a) {} public function get_route() {return $this->route;}}
class WP_REST_Response {private $d; public function __construct($d=[]) {$this->d=$d;} public function get_status() {return 200;} public function get_data() {return $this->d;}}
function rest_get_server() {
    return new class {public function get_routes() {return ['/global-data/global-colors' => [], '/global-data/global-variables' => [], '/global-data/global-preset/sync' => [], '/outside-vb/theme-builder/list-templates' => []];}};
}
function rest_do_request($r) {
    $GLOBALS['REST_CALLS'][] = $r->get_route();
    return new WP_REST_Response(['templates' => [['default' => true, 'enabled' => true, 'layouts' => ['header'=>['id'=>1,'enabled'=>true],'body'=>['id'=>2,'enabled'=>true],'footer'=>['id'=>3,'enabled'=>true]]]]]);
}
/*COLORS*/
require BIOCO_CORE_DIR.'/includes/design-system.php';
require %s;
$report = Bioco_Divi_Foundation::seed(%s);
echo json_encode(['report' => $report, 'restCalls' => $GLOBALS['REST_CALLS']]);
}'''


def php_array(value):
    if isinstance(value, dict):
        return '[' + ', '.join(f'{php_array(k)} => {php_array(v)}' for k, v in value.items()) + ']'
    if isinstance(value, list):
        return '[' + ', '.join(php_array(v) for v in value) + ']'
    return json.dumps(value)


def run_seed(colors, apply=False):
    env = f"$GLOBALS['colors'] = {php_array(colors)};\n"
    code = SEED_HARNESS.replace('/*COLORS*/', env) % (json.dumps(str(CORE)), json.dumps(str(IMPORT)), 'true' if apply else 'false')
    out = subprocess.check_output(['php'], input=code.encode()).decode()
    return json.loads(out)


def test_seed_reports_existing_label_as_conflict_instead_of_duplicate():
    result = run_seed({'native-123': {'label': 'BIOCO Green', 'color': '#000000', 'status': 'active'}})
    assert any(line.startswith('conflict BIOCO Green') for line in result['report']), result
    assert 'would-add BIOCO Green' not in result['report'] and 'added BIOCO Green' not in result['report']


def test_seed_without_label_match_still_would_add():
    result = run_seed({'native-123': {'label': 'Editor Green', 'color': '#000000', 'status': 'active'}})
    assert 'would-add BIOCO Green' in result['report'], result
    assert not any(line.startswith('conflict BIOCO Green') for line in result['report']), result


def test_apply_with_conflict_performs_no_writes():
    result = run_seed({'native-123': {'label': 'BIOCO Green', 'color': '#000000', 'status': 'active'}}, apply=True)
    assert any(line.startswith('conflict BIOCO Green') for line in result['report']), result
    # The read-only plan request may run; no write route may fire.
    assert result['restCalls'] == ['/outside-vb/theme-builder/list-templates'], result


def test_apply_without_conflict_writes_through_divi_routes():
    result = run_seed({}, apply=True)
    assert 'added BIOCO Green' in result['report'], result
    assert 'added group preset: BIOCO Heading Typography' in result['report'], result
    assert '/global-data/global-colors' in result['restCalls'], result
    assert '/global-data/global-variables' in result['restCalls'], result
    assert '/global-data/global-preset/sync' in result['restCalls'], result
    # The harness fills every Theme Builder slot, so no Theme Builder write.
    assert '/outside-vb/theme-builder/update-template' not in result['restCalls'], result


def test_unknown_manifest_preset_title_fails_the_run(tmp_path):
    core = tmp_path / 'core'
    (core / 'includes').mkdir(parents=True)
    (core / 'assets').mkdir()
    manifest = json.loads((CORE / 'assets/design-system.json').read_text())
    manifest['optionGroupPresets'].append({'title': 'BIOCO Renamed Preset', 'groups': ['x', 'y']})
    (core / 'assets/design-system.json').write_text(json.dumps(manifest))
    import shutil
    shutil.copy(CORE / 'includes/design-system.php', core / 'includes/design-system.php')
    code = SEED_HARNESS.replace('/*COLORS*/', "$GLOBALS['colors'] = [];\n") % (json.dumps(str(core)), json.dumps(str(IMPORT)), 'false')
    result = subprocess.run(['php'], input=code.encode(), capture_output=True)
    assert result.returncode != 0
    assert b'No Divi mapping for option-group preset: BIOCO Renamed Preset' in result.stdout + result.stderr
