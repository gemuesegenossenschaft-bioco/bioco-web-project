"""#207 native confirmation states with local one-time-token/storage/mail adapters.

Keep/Replace/Remove map: tests/README.md. These execute the real newsletter
confirmation backend and native PHP parts; they never consume hosted tokens.
"""
import json
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / 'wordpress/web/app/mu-plugins/bioco-core'
ORIGIN = 'https://native-doi.test'
PHP = r'''
define('ABSPATH', __DIR__);
$GLOBALS['hooks'] = []; $GLOBALS['transients'] = []; $GLOBALS['mail'] = [];
$GLOBALS['inserts'] = []; $GLOBALS['meta'] = []; $GLOBALS['lookups'] = 0;
$GLOBALS['admin'] = false; $GLOBALS['native_post'] = true; $GLOBALS['cache'] = 0;
function add_action($name, $callback, ...$args) {$GLOBALS['hooks'][$name][] = $callback;}
function add_filter(...$args) {}
function wp_unique_id($prefix) {static $n = 0; return $prefix . ++$n;}
function esc_attr($v) {return htmlspecialchars((string)$v, ENT_QUOTES);}
function esc_html($v) {return htmlspecialchars((string)$v, ENT_QUOTES);}
function esc_url($v) {return esc_attr($v);}
function wp_json_encode($v) {return json_encode($v);}
function wp_unslash($v) {return $v;}
function sanitize_text_field($v) {return strip_tags($v);}
function sanitize_email($v) {return filter_var($v,FILTER_SANITIZE_EMAIL);}
function wp_doing_ajax() {return false;}
function is_admin() {return $GLOBALS['admin'];}
function get_post() {return (object) ['post_content' => $GLOBALS['native_post'] ? '<!-- wp:bioco-divi/doi-controller /-->' : 'other'];}
function has_block($name, $content) {return strpos($content,$name) !== false;}
function get_permalink() {return 'https://native-doi.test/confirm/';}
function nocache_headers() {$GLOBALS['cache']++;}
function wp_enqueue_script(...$args) {} function wp_enqueue_style(...$args) {}
function wp_nonce_field($action,$name,$referer,$echo) {return '<input type="hidden" name="'.$name.'" value="fixture-nonce">';}
function wp_verify_nonce($nonce,$action) {return $nonce === 'fixture-nonce' && str_starts_with($action,'bioco_doi_confirm_');}
function get_transient($key) {$GLOBALS['lookups']++;return $GLOBALS['transients'][$key] ?? false;}
function delete_transient($key) {unset($GLOBALS['transients'][$key]);}
function bioco_forms_message($scope,$key) {return 'Captured '.$key;}
function bioco_forms_send_mail($to,$subject,$body) {$GLOBALS['mail'][]=[$to,$subject,$body];return true;}
function bioco_forms_recipient() {return 'admin@example.test';}
function bioco_forms_lines($lines) {return implode("\n",$lines);}
class WP_Query {public $posts=[]; function __construct($args) {} function have_posts() {return false;}}
function wp_insert_post($post) {$GLOBALS['inserts'][]=$post;return 42;}
function is_wp_error($v) {return false;}
function update_post_meta($id,$key,$value) {$GLOBALS['meta'][$key]=$value;}
function current_time($format) {return '2026-10-12 12:00:00';}
require 'wordpress/web/app/mu-plugins/bioco-forms/newsletter.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-modules.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-composition.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-templates.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-outcomes.php';
function fixture_pending($token) {$GLOBALS['transients'][bioco_forms_doi_transient_key(hash('sha256',$token))]=['form_type'=>'subscribe','data'=>['email'=>'fixture@example.test','name'=>'Fixture']];}
function fixture_post($token,$nonce='fixture-nonce') {return ['method'=>'POST','post'=>['bioco_doi_token'=>$token,'bioco_doi_nonce'=>$nonce]];}
'''


def php(code):
    result = subprocess.run(['php', '-r', PHP + code], cwd=ROOT, text=True, capture_output=True)
    assert result.returncode == 0, result.stderr
    return json.loads(result.stdout)


def test_link_scanner_get_and_invalid_nonce_do_not_consume_or_write():
    data = php(r'''
$token=str_repeat('a',64);fixture_pending($token);
$missing=bioco_doi_outcome(['method'=>'GET','get'=>[]],'wp_verify_nonce','bioco_forms_doi_confirm_token');
$get=bioco_doi_outcome(['method'=>'GET','get'=>['token'=>$token]],'wp_verify_nonce','bioco_forms_doi_confirm_token');
$rejected=bioco_doi_outcome(fixture_post($token,'bad'),'wp_verify_nonce','bioco_forms_doi_confirm_token');
$method=bioco_doi_outcome(['method'=>'PUT','get'=>['token'=>$token]],'wp_verify_nonce','bioco_forms_doi_confirm_token');
echo json_encode(['states'=>[$missing['state'],$get['state'],$rejected['state'],$method['state']],'pending'=>count($GLOBALS['transients']),'lookups'=>$GLOBALS['lookups'],'writes'=>$GLOBALS['inserts'],'mail'=>$GLOBALS['mail']]);
''')
    assert data == {'states': ['missing', 'prompt', 'invalid', 'invalid'], 'pending': 1, 'lookups': 0, 'writes': [], 'mail': []}


def test_invalid_expired_and_already_used_tokens_keep_backend_semantics():
    data = php(r'''
$expired=str_repeat('b',64);$valid=str_repeat('c',64);fixture_pending($valid);
$states=[];
foreach (['not-hex',$expired,$valid,$valid] as $token) $states[]=bioco_doi_outcome(fixture_post($token),'wp_verify_nonce','bioco_forms_doi_confirm_token')['state'];
echo json_encode(['states'=>$states,'pending'=>count($GLOBALS['transients']),'writes'=>$GLOBALS['inserts'],'mail'=>$GLOBALS['mail'],'meta'=>$GLOBALS['meta']]);
''')
    assert data['states'] == ['invalid', 'invalid', 'success', 'invalid']
    assert data['pending'] == 0
    assert len(data['writes']) == len(data['mail']) == 1
    assert data['meta']['subscriber_email'] == 'fixture@example.test'
    assert data['meta']['subscriber_name'] == 'Fixture'
    assert len(data['meta']['_bioco_confirmation_id']) == 32


def test_backend_exception_uses_failure_layout_without_consuming_another_token():
    data = php(r'''
$calls=0;$token=str_repeat('d',64);
$result=bioco_doi_outcome(fixture_post($token),'wp_verify_nonce',function($actual) use(&$calls,$token) {if($actual!==$token)throw new RuntimeException('wrong token');$calls++;throw new RuntimeException('Fixture storage failure');});
echo json_encode(['state'=>$result['state'],'calls'=>$calls,'writes'=>$GLOBALS['inserts'],'mail'=>$GLOBALS['mail']]);
''')
    assert data == {'state': 'failure', 'calls': 1, 'writes': [], 'mail': []}


def test_duplicated_controllers_evaluate_current_post_once_and_filter_native_rows():
    data = php(r'''
$token=str_repeat('e',64);fixture_pending($token);$_SERVER['REQUEST_METHOD']='POST';$_POST=fixture_post($token)['post'];$_GET=[];
$controllers=[bioco_doi_render_controller('public'),bioco_doi_render_controller('public')];
$layouts=[];
foreach(['missing','prompt','success','invalid','failure'] as $state) $layouts[$state]=bioco_doi_visible_layout('Captured '.$state,['attrs'=>bioco_form_layout_attrs('bioco-doi-state-'.$state)]);
$GLOBALS['admin']=true;$inert=bioco_doi_visible_layout('Editable native invalid',['attrs'=>bioco_form_layout_attrs('bioco-doi-state-invalid')]);
$GLOBALS['admin']=false;$GLOBALS['native_post']=false;$other=bioco_doi_visible_layout('Other page',['attrs'=>bioco_form_layout_attrs('bioco-doi-state-invalid')]);
echo json_encode(['controllers'=>$controllers,'layouts'=>$layouts,'inert'=>$inert,'other'=>$other,'writes'=>count($GLOBALS['inserts']),'mail'=>count($GLOBALS['mail']),'lookups'=>$GLOBALS['lookups'],'no_cache'=>DONOTCACHEPAGE]);
''')
    assert all('data-bioco-doi-state="success"' in html for html in data['controllers'])
    assert data['layouts'] == {'missing': '', 'prompt': '', 'success': 'Captured success', 'invalid': '', 'failure': ''}
    assert data['inert'] == 'Editable native invalid' and data['other'] == 'Other page'
    assert data['writes'] == data['mail'] == data['lookups'] == 1
    assert data['no_cache'] is True


def test_native_preview_is_inert_and_cache_headers_precede_render_without_confirming():
    data = php(r'''
$token=str_repeat('f',64);fixture_pending($token);$_SERVER['REQUEST_METHOD']='GET';$_GET=['token'=>$token];$_POST=[];
$inert=bioco_form_render_part('doi-controller',[],'inert');
$before=['cache'=>$GLOBALS['cache'],'lookups'=>$GLOBALS['lookups']];
foreach($GLOBALS['hooks']['template_redirect'] as $callback)$callback();
$after=['cache'=>$GLOBALS['cache'],'lookups'=>$GLOBALS['lookups']];
$public=bioco_form_render_part('doi-controller',[],'public');
$empty=bioco_form_render_part('form-action',['action'=>'confirm','label'=>''],'public');
echo json_encode(['inert'=>$inert,'before'=>$before,'after'=>$after,'public'=>$public,'empty'=>$empty,'pending'=>count($GLOBALS['transients']),'writes'=>$GLOBALS['inserts'],'mail'=>$GLOBALS['mail']]);
''')
    assert data['before'] == {'cache': 0, 'lookups': 0}
    assert data['after'] == {'cache': 1, 'lookups': 0}
    assert 'form' not in data['inert'] and 'fixture-nonce' not in data['inert']
    assert 'method="post"' in data['public'] and 'name="bioco_doi_nonce"' in data['public']
    assert data['empty'] == '' and data['pending'] == 1
    assert data['writes'] == data['mail'] == []


@pytest.fixture(scope='module')
def rendered_prompt():
    return php(r'''
$_SERVER['REQUEST_METHOD']='GET';$_GET=['token'=>str_repeat('a',64)];$_POST=[];
$copies=[];
foreach(['First','Second'] as $copy)$copies[]=['controller'=>bioco_form_render_part('doi-controller',[],'public'),'action'=>bioco_form_render_part('form-action',['action'=>'confirm','label'=>$copy.' confirm'],'public')];
$layouts=[];foreach(['missing','prompt','success','invalid','failure'] as $state)$layouts[$state]=[bioco_form_layout_block('divi/text',['content'=>['innerContent'=>['desktop'=>['value'=>'<p>Captured '.$state.'</p>']]]])];
echo json_encode(['copies'=>$copies,'candidate'=>bioco_doi_compose($layouts,'Captured confirm')]);
''')


def test_candidate_preserves_native_state_content_and_confirmation_action(rendered_prompt):
    rows = rendered_prompt['candidate']['innerBlocks']
    assert len(rows) == 6
    classes = [row['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] for row in rows]
    assert classes == ['bioco-doi-controller'] + ['bioco-doi-state-' + state for state in ('missing', 'prompt', 'success', 'invalid', 'failure')]
    assert rows[2]['innerBlocks'][0]['innerBlocks'][-1]['blockName'] == 'bioco-divi/form-action'
    assert all(row['innerBlocks'][0]['innerBlocks'][0]['blockName'] == 'divi/text' for row in rows[1:])


def test_confirm_button_posts_associated_nonce_only_after_explicit_click(rendered_prompt):
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context()
        page = context.new_page()
        traffic = []
        html = '<!doctype html><html><body>'
        for index, copy in enumerate(rendered_prompt['copies']):
            html += '<section class="bioco-doi-scope" id="copy-' + str(index) + '"><div class="et_pb_row">' + copy['action'] + '</div><div class="et_pb_row">' + copy['controller'] + '</div></section>'
        html += '<script src="/native.js"></script></body></html>'
        def route(r):
            if r.request.method == 'POST':
                traffic.append((r.request.method, r.request.url, r.request.post_data))
                r.fulfill(status=200, content_type='text/html', body='<h1>Fixture confirmed</h1>')
            elif r.request.url == ORIGIN + '/native.js':
                r.fulfill(status=200, content_type='text/javascript', body=(CORE/'form-composition/view.js').read_text())
            elif r.request.url == ORIGIN + '/':
                r.fulfill(status=200, content_type='text/html', body=html)
            else: r.abort()
        context.route('**/*', route)
        try:
            page.goto(ORIGIN + '/')
            assert traffic == []
            ids = page.locator('form').evaluate_all('(forms)=>forms.map(f=>f.id)')
            assert len(set(ids)) == 2
            assert page.locator('#copy-1 button').get_attribute('form') == ids[1]
            page.locator('#copy-1 button').click()
            page.wait_for_url(ORIGIN + '/confirm/')
            assert len(traffic) == 1 and traffic[0][:2] == ('POST', ORIGIN + '/confirm/')
            assert 'bioco_doi_nonce=fixture-nonce' in traffic[0][2]
            assert 'bioco_doi_token=' + 'a'*64 in traffic[0][2]
        finally:
            context.close()
            browser.close()


def test_guarded_message_retirement_preserves_capture_archive_and_rollback():
    code = r'''
define('ABSPATH',__DIR__);$GLOBALS['options']=[];
function add_action(...$args) {}
function get_option($name,$default=false) {return $GLOBALS['options'][$name]??$default;}
function update_option($name,$value,$autoload=null) {$GLOBALS['options'][$name]=$value;}
function sanitize_textarea_field($v) {return strip_tags($v);}
require 'wordpress/web/app/mu-plugins/bioco-forms/messages.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-templates.php';
$GLOBALS['options']['bioco_form_messages']=['contact-form'=>['success_message'=>'Effective saved copy','captcha_error'=>''],'shared'=>['generic'=>'Backend saved copy']];
$before=bioco_form_capture_presentation('contact-form',['success_message'=>'Old marker','captcha_error'=>'Old marker']);
$before_schema=array_keys(bioco_forms_editor_message_schema());
$GLOBALS['options']['bioco_divi_form_message_groups']=['contact-form','doi-confirm','shared','invented'];
$groups=bioco_forms_divi_message_groups();
$active=bioco_forms_message('contact-form','success_message','Accepted native copy');
$backend=bioco_forms_message('shared','generic','Old backend');
$schema=array_keys(bioco_forms_editor_message_schema());
$clean=bioco_forms_sanitize_messages(['contact-form'=>['success_message'=>'Injected replacement'],'shared'=>['generic'=>'Updated backend']]);
$archive=$clean['contact-form'];update_option('bioco_form_messages',$clean);
bioco_forms_seed_messages(['blocks'=>['contact-form'=>['success_message'=>'New seed','fallback_error'=>'New seed']]]);
$seed_archive=$GLOBALS['options']['bioco_form_messages']['contact-form'];
$refused=false;try{bioco_form_capture_presentation('contact-form',[]);}catch(InvalidArgumentException $error){$refused=true;}
$GLOBALS['options']['bioco_divi_form_message_groups']=[];
$rollback=bioco_forms_message('contact-form','success_message','Marker');
echo json_encode(compact('before','before_schema','groups','active','backend','schema','archive','seed_archive','refused','rollback'));
'''
    result = subprocess.run(['php', '-r', code], cwd=ROOT, text=True, capture_output=True)
    assert result.returncode == 0, result.stderr
    data = json.loads(result.stdout)
    assert data['before']['success_message'] == 'Effective saved copy'
    assert data['before']['captcha_error'] == ''
    assert 'contact-form' in data['before_schema']
    assert data['groups'] == ['contact-form', 'doi-confirm']
    assert data['active'] == 'Accepted native copy' and data['backend'] == 'Backend saved copy'
    assert 'contact-form' not in data['schema'] and 'doi-confirm' not in data['schema'] and 'shared' in data['schema']
    assert data['archive'] == data['seed_archive']
    assert data['archive']['success_message'] == data['rollback'] == 'Effective saved copy'
    assert data['archive']['captcha_error'] == '' and data['refused'] is True
