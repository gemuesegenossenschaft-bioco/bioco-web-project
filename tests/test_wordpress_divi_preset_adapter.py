"""Native preset compatibility and bootstrap behavior; see tests/README.md."""
import json
import hashlib
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"
JS = CORE / "assets/divi-preset-adapter.js"
PHP = CORE / "includes/divi-preset-adapter.php"
DEPENDENCIES = ROOT / "tests/fixtures/wp-dependencies"


@pytest.mark.parametrize("build", ["staging", "production"])
def test_native_namespace_behavior_and_boot_guards(build):
    result = subprocess.run(
        ["node", str(ROOT / "tests/fixtures/divi-preset-adapter.cjs"), str(JS), build],
        capture_output=True, text=True, timeout=30,
    )
    assert result.returncode == 0, result.stderr
    evidence = json.loads(result.stdout)
    assert evidence["build"] == build
    assert evidence["cases"] >= 15


def test_php_builder_authorization_fingerprint_and_dependency_order():
    code = r'''
    define('ABSPATH', '/');
    function add_action(...$args) {$GLOBALS['hook']=$args;}
    require $argv[1];
    $base = ['app'=>true,'allowed'=>true,'fingerprint'=>
      '6b977ad2a9adfffcce73584b13eb5f471e765db181a21efdc8f4afa2fd19ed7c',
      'registered'=>true,'printed'=>false];
    $production=$base;$production['fingerprint']='cbb43621ff0d4089a180c822b5ae0b2253246b4a3a6577947ea29fb88168d808';
    $results = ['valid'=>bioco_divi_preset_adapter_guard($base),
      'production'=>bioco_divi_preset_adapter_guard($production)];
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
    ], capture_output=True, text=True, timeout=30)
    assert result.returncode == 0, result.stderr
    evidence = json.loads(result.stdout)
    assert evidence["guards"]["valid"] == ""
    assert evidence["guards"]["production"] == ""
    assert all(evidence["guards"][key] for key in
               ("app", "allowed", "registered", "fingerprint", "printed"))
    assert evidence["hook"][0] == "divi_visual_builder_assets_after_enqueue_app_window_scripts"
    assert evidence["order"] == ["divi-module-utils", "divi-module", "divi-modal-library"]


def test_php_public_unauthorized_and_unknown_build_requests_stay_unmodified(tmp_path):
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
    function wp_scripts(){return $GLOBALS['scripts'];}
    function wp_script_is($handle,$status){return $status==='registered';}
    function wp_add_inline_script(...$args){$GLOBALS['inline'][]=$args;return true;}
    function do_action(...$args){$GLOBALS['diagnostics'][]=$args;}
    define('ET_BUILDER_5_DIR',$argv[2]);define('ET_BUILDER_5_URI','https://example.test/native-divi');
    $GLOBALS['scripts']=(object)['registered'=>['divi-module-utils'=>(object)['src'=>ET_BUILDER_5_URI.'/visual-builder/build/module-utils.js']]];
    require $argv[1];$GLOBALS['allowed']=true;$GLOBALS['logged']=true;
    $GLOBALS['role']=true;
    bioco_divi_preset_adapter_enqueue();
    $GLOBALS['app']=true;$GLOBALS['allowed']=false;
    bioco_divi_preset_adapter_enqueue();
    $GLOBALS['allowed']=true;$GLOBALS['logged']=false;bioco_divi_preset_adapter_enqueue();
    $GLOBALS['logged']=true;$GLOBALS['role']=false;bioco_divi_preset_adapter_enqueue();
    $GLOBALS['role']=true;bioco_divi_preset_adapter_enqueue();
    echo json_encode(['inline'=>$GLOBALS['inline'],'diagnostics'=>$GLOBALS['diagnostics']??[]]);
    }
    '''
    build = tmp_path / "visual-builder/build/module-utils.js"
    build.parent.mkdir(parents=True)
    build.write_text("fixture unknown vendor build")
    result = subprocess.run(["php", "-r", code, str(PHP), str(tmp_path)],
                            capture_output=True, text=True, timeout=30)
    assert result.returncode == 0, result.stderr
    evidence = json.loads(result.stdout)
    assert evidence["inline"] == []
    assert evidence["diagnostics"][-1][0] == "bioco_divi_preset_adapter_diagnostic"
    assert evidence["diagnostics"][-1][1] == "unsupported-build"


def test_native_registration_resolves_real_asset_hash_and_refuses_mismatch(tmp_path):
    asset = tmp_path / "visual-builder/build/module-utils.js"
    asset.parent.mkdir(parents=True)
    asset.write_text("/* owned temporary asset; not a licensed vendor build */")
    code = r'''
    define('ABSPATH','/');
    define('ET_BUILDER_5_DIR',$argv[2]);
    define('ET_BUILDER_5_URI','https://example.test/divi-builder');
    function add_action(...$args){}
    function wp_scripts(){return $GLOBALS['scripts'];}
    require $argv[1];require $argv[3];require $argv[4];
    $GLOBALS['scripts']=new WP_Dependencies();
    $src=ET_BUILDER_5_URI.'/visual-builder/build/module-utils.js';
    $missing=bioco_divi_preset_adapter_fingerprint();
    $GLOBALS['scripts']->add('divi-module-utils',$src);
    $valid=bioco_divi_preset_adapter_fingerprint();
    $GLOBALS['scripts']->registered['divi-module-utils']->src=$src.'?different-source=1';
    $mismatch=bioco_divi_preset_adapter_fingerprint();
    $GLOBALS['scripts']->registered['divi-module-utils']->src=$src;
    unlink(ET_BUILDER_5_DIR.'/visual-builder/build/module-utils.js');
    echo json_encode(['missing'=>$missing,'valid'=>$valid,'mismatch'=>$mismatch,
      'missing_file'=>bioco_divi_preset_adapter_fingerprint()]);
    '''
    expected = hashlib.sha256(asset.read_bytes()).hexdigest()
    result = subprocess.run([
        "php", "-r", code, str(PHP), str(tmp_path),
        str(DEPENDENCIES / "class-wp-dependency.php"),
        str(DEPENDENCIES / "class-wp-dependencies.php"),
    ], capture_output=True, text=True, timeout=30)
    assert result.returncode == 0, result.stderr
    assert json.loads(result.stdout) == {
        "missing": "", "valid": expected, "mismatch": "", "missing_file": "",
    }


def test_missing_native_integration_stays_unmodified():
    code = r'''
    define('ABSPATH','/');function add_action(...$args){}
    function wp_scripts(){throw new RuntimeException('Native registration must not be read');}
    require $argv[1];echo json_encode(bioco_divi_preset_adapter_fingerprint());
    '''
    result = subprocess.run(["php", "-r", code, str(PHP)],
                            capture_output=True, text=True, timeout=30)
    assert result.returncode == 0, result.stderr
    assert json.loads(result.stdout) == ""


@pytest.mark.parametrize("fingerprint", [
    "6b977ad2a9adfffcce73584b13eb5f471e765db181a21efdc8f4afa2fd19ed7c",
    "cbb43621ff0d4089a180c822b5ae0b2253246b4a3a6577947ea29fb88168d808",
])
def test_core_bootstrap_registered_callback_injects_supported_adapter_after_native(
    tmp_path, fingerprint,
):
    # This process alone replaces PHP's hash_file boundary: licensed vendor
    # bundles are not CI fixtures. Real registered path/hash behavior is tested
    # above; the captured SHA tests successful bootstrap and inline injection.
    asset = tmp_path / "visual-builder/build/module-utils.js"
    asset.parent.mkdir(parents=True)
    asset.write_text("controlled filesystem boundary for supported-build enqueue")
    code = r'''
    namespace ET\Builder\Framework\Utility {
      class Conditions {static function is_vb_app_window(){return true;}}
    }
    namespace ET\Builder\Framework\UserRole {
      class UserRole {static function can_current_user_use_visual_builder(){return true;}}
    }
    namespace {
    define('ABSPATH','/');define('ET_BUILDER_5_DIR',$argv[2]);
    define('ET_BUILDER_5_URI','https://example.test/native-divi');
    function hash_file($algorithm,$path){$GLOBALS['hash_calls'][]=[$algorithm,$path];return $GLOBALS['fixture_sha'];}
    function add_filter(...$args){return true;}
    function add_action($hook,$callback,$priority=10){$GLOBALS['actions'][$hook][]=[$priority,$callback];}
    function get_option($key,$default=false){return $default;}
    function wp_json_encode(...$args){return json_encode(...$args);}
    function is_user_logged_in(){return true;}
    function current_user_can($cap){return $cap==='edit_theme_options';}
    function wp_scripts(){return $GLOBALS['scripts'];}
    function wp_script_is($handle,$status){return $status==='registered' && isset(wp_scripts()->registered[$handle]);}
    function wp_add_inline_script($handle,$source,$position){$GLOBALS['inline'][]=[$handle,$source,$position];return true;}
    function do_action($hook,...$args){$callbacks=$GLOBALS['actions'][$hook]??[];usort($callbacks,fn($a,$b)=>$a[0]<=>$b[0]);foreach($callbacks as $entry)($entry[1])(...$args);}
    require $argv[4];require $argv[5];
    $GLOBALS['fixture_sha']=$argv[3];$GLOBALS['scripts']=new WP_Dependencies();
    $src=ET_BUILDER_5_URI.'/visual-builder/build/module-utils.js';
    $GLOBALS['scripts']->add('divi-module-utils',$src);
    $GLOBALS['scripts']->add('divi-module','module.js',['divi-module-utils']);
    $GLOBALS['scripts']->add('divi-modal-library','modal.js',['divi-module-utils']);
    require $argv[1];
    $hook='divi_visual_builder_assets_after_enqueue_app_window_scripts';
    do_action($hook);
    echo json_encode(['callbacks'=>array_column($GLOBALS['actions'][$hook]??[],1),
      'inline'=>$GLOBALS['inline']??[],'hash_calls'=>$GLOBALS['hash_calls']??[]]);
    }
    '''
    result = subprocess.run([
        "php", "-d", "disable_functions=hash_file", "-r", code,
        str(CORE / "bioco-core.php"), str(tmp_path), fingerprint,
        str(DEPENDENCIES / "class-wp-dependency.php"),
        str(DEPENDENCIES / "class-wp-dependencies.php"),
    ], capture_output=True, text=True, timeout=30)
    assert result.returncode == 0, result.stderr
    evidence = json.loads(result.stdout)
    assert evidence["callbacks"] == ["bioco_divi_preset_adapter_enqueue"]
    assert evidence["hash_calls"] == [["sha256", str(asset)]]
    assert evidence["inline"] == [["divi-module-utils", JS.read_text(), "after"]]
