"""Native preset namespace compatibility, without changing stored presets."""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"
JS = CORE / "assets/divi-preset-adapter.js"
PHP = CORE / "includes/divi-preset-adapter.php"


def test_native_namespace_behavior_and_boot_guards():
    result = subprocess.run(
        ["node", str(ROOT / "tests/fixtures/divi-preset-adapter.cjs"), str(JS)],
        capture_output=True, text=True,
    )
    assert result.returncode == 0, result.stderr
    assert json.loads(result.stdout)["cases"] >= 12


def test_php_builder_authorization_fingerprint_and_dependency_order():
    code = r'''
    define('ABSPATH', '/');
    function add_action(...$args) {$GLOBALS['hook']=$args;}
    require $argv[1];
    $base = ['app'=>true,'allowed'=>true,'fingerprint'=>
      '6b977ad2a9adfffcce73584b13eb5f471e765db181a21efdc8f4afa2fd19ed7c',
      'registered'=>true,'printed'=>false];
    $results = ['valid'=>bioco_divi_preset_adapter_guard($base)];
    foreach (['app','allowed','registered'] as $key) {
      $context=$base;$context[$key]=false;
      $results[$key]=bioco_divi_preset_adapter_guard($context);
    }
    foreach (['fingerprint'=>'unknown','printed'=>true] as $key=>$value) {
      $context=$base;$context[$key]=$value;
      $results[$key]=bioco_divi_preset_adapter_guard($context);
    }
    require $argv[2]; require $argv[3];
    $deps = new WP_Dependencies();
    $deps->add('divi-module-utils', 'utils.js');
    $deps->add('divi-module', 'module.js', ['divi-module-utils']);
    $deps->add('divi-modal-library', 'modal.js', ['divi-module-utils']);
    $deps->enqueue(['divi-module','divi-modal-library']);
    $deps->all_deps($deps->queue);
    echo json_encode(['guards'=>$results,'hook'=>$GLOBALS['hook'],
      'order'=>$deps->to_do]);
    '''
    result = subprocess.run([
        "php", "-r", code, str(PHP),
        str(ROOT / "tests/fixtures/wp-dependencies/class-wp-dependency.php"),
        str(ROOT / "tests/fixtures/wp-dependencies/class-wp-dependencies.php"),
    ], capture_output=True, text=True)
    assert result.returncode == 0, result.stderr
    evidence = json.loads(result.stdout)
    assert evidence["guards"]["valid"] == ""
    assert all(evidence["guards"][key] for key in
               ("app", "allowed", "registered", "fingerprint", "printed"))
    assert evidence["hook"][0] == "divi_visual_builder_assets_after_enqueue_app_window_scripts"
    assert evidence["order"] == ["divi-module-utils", "divi-module", "divi-modal-library"]


def test_php_public_unauthorized_and_unknown_build_requests_stay_unmodified():
    # Execute the actual enqueue function with native boundary stubs. The hash
    # is supplied to the pure guard above; a real unknown build must be refused.
    code = r'''
    namespace ET\Builder\Framework\Utility {
      class Conditions {static function is_vb_app_window(){return $GLOBALS['app'];}}
    }
    namespace ET\Builder\Framework\UserRole {
      class UserRole {static function can_current_user_use_visual_builder(){return $GLOBALS['role'];}}
    }
    namespace {
    define('ABSPATH','/');$GLOBALS['app']=false;$GLOBALS['inline']=[];
    function add_action(...$args){}
    function is_user_logged_in(){return $GLOBALS['logged'];}
    function current_user_can($cap){return $GLOBALS['allowed'];}
    function get_template_directory(){return $GLOBALS['theme'];}
    function wp_script_is($handle,$status){return $status==='registered';}
    function wp_add_inline_script(...$args){$GLOBALS['inline'][]=$args;return true;}
    function do_action(...$args){$GLOBALS['diagnostics'][]=$args;}
    require $argv[1];$GLOBALS['allowed']=true;$GLOBALS['logged']=true;
    $GLOBALS['role']=true;$GLOBALS['theme']=$argv[2];
    bioco_divi_preset_adapter_enqueue();
    $GLOBALS['app']=true;$GLOBALS['allowed']=false;
    bioco_divi_preset_adapter_enqueue();
    $GLOBALS['allowed']=true;$GLOBALS['logged']=false;bioco_divi_preset_adapter_enqueue();
    $GLOBALS['logged']=true;$GLOBALS['role']=false;bioco_divi_preset_adapter_enqueue();
    $GLOBALS['role']=true;bioco_divi_preset_adapter_enqueue();
    echo json_encode(['inline'=>$GLOBALS['inline'],'diagnostics'=>$GLOBALS['diagnostics']??[]]);
    }
    '''
    result = subprocess.run(["php", "-r", code, str(PHP), str(ROOT)],
                            capture_output=True, text=True)
    assert result.returncode == 0, result.stderr
    evidence = json.loads(result.stdout)
    assert evidence["inline"] == []
    assert evidence["diagnostics"][-1][0] == "bioco_divi_preset_adapter_diagnostic"
    assert evidence["diagnostics"][-1][1] == "unsupported-build"
    assert "'/includes/divi-preset-adapter.php'" in (CORE / "bioco-core.php").read_text()
