"""Real native-part output and real lifecycle with every network request intercepted.

These tests prove HTML association and transport behavior. Licensed Divi save/reopen
and decoration remain separate hosted acceptance checks on unpublished copies.
Keep/Replace/Remove map: tests/README.md.
"""
import json
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / 'wordpress/web/app/mu-plugins/bioco-core'
ORIGIN = 'https://native-forms.test'

PHP = r'''
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function wp_unique_id($prefix) {static $n = 0;return $prefix . ++$n;}
function wp_json_encode($value) {return json_encode($value);}
function esc_attr($value) {return htmlspecialchars((string)$value, ENT_QUOTES);}
function esc_html($value) {return htmlspecialchars((string)$value, ENT_QUOTES);}
function rest_url($path) {return 'https://native-forms.test/wp-json/' . $path;}
function wp_enqueue_script($handle) {$GLOBALS['enqueued'][] = $handle;}
function wp_enqueue_style($handle) {}
function bioco_forms_turnstile_config() {return ['configured'=>true,'site_key'=>'test-key'];}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-composition.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-templates.php';
$copies = [];
foreach (['First','Second'] as $copy) {
  $fields=[];
  foreach (['subject','email','name','message','phone'] as $field) {
    $fields[$field]=bioco_form_render_part('form-field',['field'=>'contact.'.$field,'label'=>$copy.' '.$field,'help'=>$field==='email'?'Use your email address':''],'public');
  }
  $copies[]=['controller'=>bioco_form_render_part('form-controller',['form_type'=>'contact','success_message'=>$copy.' success','fallback_error'=>$copy.' failure','captcha_error'=>$copy.' captcha','transport_error'=>$copy.' transport'],'public'),'action'=>bioco_form_render_part('form-action',['label'=>$copy.' send','pending_label'=>$copy.' pending'],'public'),'fields'=>$fields];
}
$fields=[];
foreach (['email','subject','name','message'] as $name) $fields[]=['field'=>'contact.'.$name,'label'=>'Captured '.$name];
$candidate=bioco_form_compose('contact',['submit_label'=>'Captured send','success_message'=>'Captured success'], $fields);
$refused=[];
foreach ([array_slice($fields,1),array_merge($fields,[$fields[0]])] as $invalid) {
  try {bioco_form_compose('contact',['submit_label'=>'Send'],$invalid);$refused[]=false;}
  catch (InvalidArgumentException $error) {$refused[]=true;}
}
echo json_encode(['copies'=>$copies,'inert'=>bioco_form_render_part('form-controller',['form_type'=>'contact'],'inert'),'wrong'=>bioco_form_render_part('form-field',['field'=>'contact.invented','label'=>'Wrong'],'public'),'empty'=>bioco_form_render_part('form-field',['field'=>'contact.name','label'=>''],'public'),'metadata'=>bioco_form_metadata(),'enqueued'=>$GLOBALS['enqueued'],'candidate'=>$candidate,'refused'=>$refused]);
'''

@pytest.fixture(scope='module')
def rendered():
    result = subprocess.run(['php','-r',PHP],cwd=ROOT,text=True,capture_output=True)
    assert result.returncode == 0, result.stderr
    return json.loads(result.stdout)


def test_render_keeps_identities_constraints_labels_and_inert_contract(rendered):
    from html.parser import HTMLParser
    class Tags(HTMLParser):
        def __init__(self):
            super().__init__(); self.tags = []
        def handle_starttag(self, tag, attrs): self.tags.append((tag,dict(attrs)))
    ids = []
    for copy in rendered['copies']:
        for field, html in copy['fields'].items():
            parser = Tags(); parser.feed(html)
            input_tag, attrs = next(t for t in parser.tags if t[0] in ('input','textarea'))
            label = next(a for t,a in parser.tags if t == 'label')
            assert attrs['name'] == field
            assert attrs['data-bioco-field'] == 'contact.' + field
            assert 'disabled' in attrs
            assert ('required' in attrs) == (field != 'phone')
            assert label['for'] == attrs['id']
            ids.append(attrs['id'])
            if field == 'email': assert attrs['aria-describedby'] == attrs['id'] + '-help'
    assert len(ids) == len(set(ids))
    assert 'wp-json' not in rendered['inert'] and 'test-key' not in rendered['inert']
    assert 'data-form-captcha' not in rendered['inert']
    assert rendered['wrong'] == rendered['empty'] == ''
    assert rendered['enqueued'] == ['bioco-native-form-composition'] * 2
    assert {'form-controller','form-field','form-action'} <= set(rendered['metadata'])
    field = rendered['metadata']['form-field']['attributes']
    assert 'font' in field['control']['settings']['decoration']
    assert 'border' in field['control']['settings']['decoration']


def test_candidate_is_native_ordered_and_refuses_missing_or_duplicate_fields(rendered):
    candidate = rendered['candidate']
    assert candidate['blockName'] == 'divi/section'
    rows = candidate['innerBlocks']
    assert all(row['blockName'] == 'divi/row' for row in rows)
    controls = rows[0]['innerBlocks'][0]['innerBlocks']
    assert [part['attrs']['field']['innerContent']['desktop']['value'] for part in controls[:4]] == ['contact.email','contact.subject','contact.name','contact.message']
    assert [part['blockName'] for part in controls[-2:]] == ['bioco-divi/form-controller','bioco-divi/form-action']
    assert rows[1]['innerBlocks'][0]['innerBlocks'][0]['blockName'] == 'divi/text'
    assert rendered['refused'] == [True,True]


def test_style_callbacks_do_not_request_undefined_module_attributes():
    code = PHP[:PHP.index('$copies = [];')] + r'''
eval('namespace ET\\Builder\\FrontEnd\\Module; class Style {static function add($args) {}}');
class Elements {
 public $kind; public $calls=[];
 function __construct($kind) {$this->kind=$kind;}
 function style($args) {
  if(!isset(bioco_form_metadata()[$this->kind]['attributes'][$args['attrName']]['settings']['decoration']))throw new RuntimeException('Missing decoration metadata');
  $this->calls[]=$args['attrName'];return '';
 }
}
$result=[];
foreach(array_keys(bioco_form_metadata()) as $kind){
 $elements=new Elements($kind);bioco_form_module_styles(['name'=>'bioco-divi/'.$kind,'elements'=>$elements,'id'=>'fixture','orderIndex'=>0,'storeInstance'=>0]);$result[$kind]=$elements->calls;
}
echo json_encode($result);
'''
    result = subprocess.run(['php','-r',code],cwd=ROOT,text=True,capture_output=True)
    assert result.returncode == 0, result.stderr
    calls=json.loads(result.stdout)
    assert calls['form-controller']==calls['doi-controller']==['module']
    assert calls['form-field']==calls['form-action']==['module','control','label','help']
    assert calls['form-value']==['module','control','label']


@pytest.fixture()
def browser():
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        instance = p.chromium.launch()
        yield instance
        instance.close()


def html_page(rendered, change='', omit_runtime=False):
    parts = ['<!doctype html><html><head><meta charset="utf-8"><style>',(CORE/'form-composition/style.css').read_text(),'</style></head><body>']
    for index, copy in enumerate(rendered['copies']):
        fields = copy['fields']
        parts += [f'<section class="bioco-form-scope" id="copy-{index}">', copy['controller'],
                  '<div class="et_pb_row bioco-form-fields"><div class="et_pb_column">', fields['subject'], fields['email'], '</div><div class="et_pb_column">',
                  fields['name'], fields['message'], fields['phone'], copy['action'],'</div></div>',
                  '<div class="et_pb_row bioco-form-state-success" hidden><h2>Authored result '+str(index)+'</h2><p>Editable native text</p></div>',
                  '<div class="et_pb_row bioco-form-state-error" hidden><h2>Authored error '+str(index)+'</h2></div></section>']
    if not omit_runtime: parts += ['<script src="/assets/runtime.js"></script>']
    parts += ['<script src="/assets/native.js"></script></body></html>']
    return ''.join(parts).replace(*change) if change else ''.join(parts)


def open_fixture(browser, rendered, change='', omit_runtime=False):
    context = browser.new_context()
    context.add_init_script('''
    window.traffic=[];window.answers=[];window.widgets=[];
    window.turnstile={render:function(container,options){window.widgets.push(options);options.callback('fixture-token');return window.widgets.length-1;},reset:function(id){window.widgets[id].callback('retry-token');}};
    ''')
    page = context.new_page()
    def route(request):
        url = request.request.url
        if url == ORIGIN + '/': request.fulfill(status=200,content_type='text/html',body=html_page(rendered,change,omit_runtime))
        elif url == ORIGIN + '/assets/runtime.js': request.fulfill(status=200,content_type='text/javascript',body=(CORE/'assets/bioco-forms-lifecycle.js').read_text())
        elif url == ORIGIN + '/assets/native.js': request.fulfill(status=200,content_type='text/javascript',body=(CORE/'form-composition/view.js').read_text())
        elif url == ORIGIN + '/wp-json/bioco/v1/contact':
            payload = request.request.post_data_json
            page.evaluate('(payload)=>window.traffic.push(payload)',payload)
            answer = page.evaluate('window.answers.shift() || {status:200,body:{success:true}}')
            request.fulfill(status=answer['status'],content_type='application/json',body=json.dumps(answer['body']))
        else: request.abort()
    context.route('**/*', route)
    page.goto(ORIGIN + '/')
    return page, context


def fill(page, copy=0):
    for field in ('name','email','subject','message'):
        page.locator(f'#copy-{copy} [name="{field}"]').fill('test@example.test' if field == 'email' else field + ' value')


def test_reordered_columns_associate_and_isolate_same_type_instances(browser, rendered):
    page, context = open_fixture(browser,rendered)
    try:
        for index in (0,1):
            assert page.locator(f'#copy-{index} form').evaluate('(f)=>Array.from(f.elements).map(e=>e.name).filter(Boolean)') == ['subject','email','name','message','phone']
        fill(page,1)
        page.locator('#copy-1 button').click()
        page.wait_for_function('window.traffic.length===1 && document.querySelector("#copy-1").dataset.biocoFormState==="success"')
        assert page.evaluate('window.traffic[0]') == {'subject':'subject value','email':'test@example.test','name':'name value','message':'message value','phone':'','captchaToken':'fixture-token'}
        assert page.locator('#copy-1 .bioco-form-state-success').is_visible()
        assert page.locator('#copy-1 .bioco-form-fields').is_hidden()
        assert page.locator('#copy-0 .bioco-form-state-success').is_hidden()
        assert page.locator('#copy-0 [name="name"]').is_enabled()
        page.locator('#copy-1 form').evaluate('(form)=>form.dispatchEvent(new Event("submit",{bubbles:true,cancelable:true}))')
        assert page.evaluate('window.traffic.length') == 1
        page.add_script_tag(content=(CORE/'form-composition/view.js').read_text())
        assert page.locator('#copy-1 .bioco-form-state-success').is_visible()
        assert page.locator('#copy-1 button').is_disabled()
    finally: context.close()


def test_invalid_email_blocks_transport_before_pending(browser, rendered):
    page, context = open_fixture(browser, rendered)
    try:
        fill(page)
        page.locator('#copy-0 [name="email"]').fill('invalid')
        page.locator('#copy-0 button').click()
        assert page.evaluate('window.traffic.length') == 0
        assert page.locator('#copy-0').get_attribute('data-bioco-form-state') == 'idle'
        assert page.locator('#copy-0 button').is_enabled()
    finally: context.close()


def test_error_retry_keeps_values_and_accessible_field_errors(browser, rendered):
    page, context = open_fixture(browser,rendered)
    try:
        fill(page)
        page.evaluate('window.answers.push({status:400,body:{success:false,error:"Server failure",fieldErrors:{email:"Email rejected"}}})')
        page.locator('#copy-0 button').click()
        page.wait_for_function('window.traffic.length===1 && document.querySelector("#copy-0 button").disabled===false')
        email = page.locator('#copy-0 [name="email"]')
        assert email.input_value() == 'test@example.test'
        assert email.get_attribute('aria-invalid') == 'true'
        descriptions = email.get_attribute('aria-describedby').split()
        assert len(descriptions) == 2
        assert page.locator('#'+descriptions[1]).inner_text() == 'Email rejected'
        assert page.locator('#copy-0 .bioco-form-state-error').is_visible()
        assert page.locator('#copy-0 .form-message').is_hidden()
        assert page.locator('#copy-0 .bioco-form-state-error').inner_text() == 'Authored error 0'
        page.locator('#copy-0 button').click()
        page.wait_for_function('window.traffic.length===2 && document.querySelector("#copy-0").dataset.biocoFormState==="success"')
        assert email.get_attribute('aria-invalid') is None
        assert email.get_attribute('aria-describedby') == descriptions[0]
        assert page.evaluate('window.traffic[1].captchaToken') == 'retry-token'
    finally: context.close()


@pytest.mark.parametrize('change,omit_runtime',[
    (('', ''),True),
    (('name="email"','name="wrong"'),False),
    (('data-bioco-field="contact.email"','data-bioco-field="subscribe.email"'),False),
    (('name="email" data-native-form-control','name="email" data-native-form-control2'),False),
])
def test_missing_runtime_or_wrong_required_contract_stays_disabled(browser, rendered, change, omit_runtime):
    page, context = open_fixture(browser,rendered,change,omit_runtime)
    try:
        assert page.locator('#copy-0 button').is_disabled()
        page.locator('#copy-0 form').evaluate('(form)=>form.dispatchEvent(new Event("submit",{bubbles:true,cancelable:true}))')
        assert page.evaluate('window.traffic.length') == 0
    finally: context.close()
