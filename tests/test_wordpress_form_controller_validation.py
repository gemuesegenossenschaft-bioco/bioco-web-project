"""Invalid membership settings return before assets, CAPTCHA or unique IDs."""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).parents[1]


def test_membership_controller_validates_count_before_render_side_effects():
    php = r'''
define('ABSPATH', __DIR__);
$effects=[];$ids=0;
function add_action(...$args) {}
function wp_enqueue_style($handle) {$GLOBALS['effects'][]=$handle;}
function wp_enqueue_script($handle) {$GLOBALS['effects'][]=$handle;}
function wp_unique_id($prefix) {$GLOBALS['ids']++;return $prefix.$GLOBALS['ids'];}
function bioco_forms_turnstile_config() {$GLOBALS['effects'][]='captcha-config';return ['configured'=>false];}
function bioco_forms_commitment_signature($count) {return 'fixture-signature';}
function esc_attr($value) {return htmlspecialchars((string)$value, ENT_QUOTES);}
function rest_url($path) {return 'https://fixture.test/'.$path;}
function wp_json_encode($value) {return json_encode($value);}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-composition.php';
$invalid=[];foreach([0,101,-1,'','-1','invalid',[],false,true,null] as $count)$invalid[]=bioco_form_render_part('form-controller',['form_type'=>'membership','commitment_count'=>$count],'public');
$before=['invalid'=>$invalid,'effects'=>$effects,'ids'=>$ids];
$valid=bioco_form_render_part('form-controller',['form_type'=>'membership','commitment_count'=>'4'],'public');
echo json_encode(['before'=>$before,'valid'=>$valid,'effects'=>$effects,'ids'=>$ids]);
'''
    result = subprocess.run(['php', '-r', php], cwd=ROOT, capture_output=True, text=True)
    assert result.returncode == 0 and result.stderr == ''
    data = json.loads(result.stdout)
    assert data['before'] == {'invalid': [''] * 10, 'effects': [], 'ids': 0}
    assert data['ids'] == 1 and 'name="commitmentCount" value="4"' in data['valid']
    assert 'bioco-native-form-composition' in data['effects'] and 'captcha-config' in data['effects']
