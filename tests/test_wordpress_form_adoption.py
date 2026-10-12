"""Saved-form conversion, actual native controls and executable CAS regressions.

Fixtures are synthetic. Private hosted captures are not committed. Core's real
block parser is used; rendering wrappers and SQLite do not prove licensed Divi
save/reopen or MySQL behavior. Those remain separate release checks.
"""
import copy
import json
import re
import sqlite3
import subprocess
from pathlib import Path

import pytest

from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
CORE = ROOT / 'wordpress/web/app/mu-plugins/bioco-core'
ORIGIN = 'https://native-flows.test'
PHP = r'''
define('ABSPATH', __DIR__);
$state=json_decode(stream_get_contents(STDIN),true);
function home_url($path) {return $GLOBALS['state']['site'].ltrim($path,'/');}
function add_action(...$args) {} function add_filter(...$args) {}
function esc_attr($v) {return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_html($v) {return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_url($v) {return esc_attr($v);}
function wp_json_encode($v,...$args) {return json_encode($v,...$args);}
function wp_unique_id($prefix) {static $n=0;return $prefix.++$n;}
// KSES itself is verified read-only against installed WordPress, not this seam.
function wp_kses_post($v) {return $v;}
function wp_enqueue_script(...$args) {} function wp_enqueue_style(...$args) {}
function rest_url($p) {return 'https://native-flows.test/wp-json/'.$p;}
function bioco_forms_turnstile_config() {return ['configured'=>true,'site_key'=>'fixture-key'];}
function bioco_forms_commitment_signature($n) {return hash_hmac('sha256',(string)$n,'fixture-salt');}
function is_singular($type) {return false;}
class WP_Error {function __construct(public $code,public $message,...$args){} function get_error_message(){return $this->message;}}
function is_wp_error($v) {return $v instanceof WP_Error;}
function bioco_forms_message_schema() {return array_fill_keys(array_keys(bioco_import_form_groups()),[]);}
function bioco_forms_divi_message_groups() {return [];}
function bioco_forms_message_values($group,$values) {return array_merge($values,$GLOBALS['state']['effective_messages']??[]);}
function bioco_render_dynamic_component($component,$values,$context) {$GLOBALS['captured_context']=$context;return $GLOBALS['state']['capture_html']??'';}
function has_block($name,$content) {return str_contains($content,'wp:'.$name);}
function parse_blocks($value) {return (new WP_Block_Parser())->parse($value);}
class Database {
 public $posts='posts'; public $options='options';
 function prepare($sql,...$args) {foreach($args as $arg)$sql=preg_replace_callback('/%[sd]/',fn()=>"'".str_replace("'","''",(string)$arg)."'",$sql,1);return str_replace('%','{wpdb-test}',$sql);}
 function remove_placeholder_escape($sql) {return str_replace('{wpdb-test}','%',$sql);}
 function esc_like($s) {return addcslashes($s,'_%\\');}
}
$wpdb=new Database();
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
WP_SERIALIZER
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-composition.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-templates.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-modules.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/form-adoption.php';
function render_native_fixture(array $blocks): string {
 $html='';foreach($blocks as $b){$name=$b['blockName']??'';$attrs=$b['attrs'];if($name===''){$html.=$b['innerHTML'];continue;}
  if(str_starts_with($name,'bioco-divi/')){$kind=substr($name,11);if($kind==='doi-controller')continue;$v=bioco_form_part_values($attrs);$html.=bioco_form_render_part($kind,$v,($v['action']??'')==='doi-error-details'?'inert':'public');}
  elseif($name==='divi/text')$html.=$attrs['content']['innerContent']['desktop']['value'];
  elseif($name==='divi/heading'){$level=$attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'];$html.='<'.$level.'>'.$attrs['title']['innerContent']['desktop']['value'].'</'.$level.'>';}
  else{$value=$attrs['module']['advanced']['htmlAttributes']['desktop']['value']??[];$html.='<div id="'.esc_attr($value['id']??'').'" class="'.esc_attr($value['class']??'').'">'.render_native_fixture($b['innerBlocks']).'</div>';}
 }return $html;
}
try {
 if(($state['mode']??'')==='capture'){
  $capture=bioco_import_form_capture($state['group'],$state['block']);echo json_encode(['capture'=>$capture,'context'=>$GLOBALS['captured_context']]);
 }elseif(($state['mode']??'')==='sql'){
  $p=$state['plan'];$fn=$p['kind']==='form-content'?'bioco_import_form_sql':'bioco_import_form_retirement_sql';
  echo json_encode(['sql'=>$fn($p,$state['expected'],$state['restore']??false)]);
 }elseif(($state['mode']??'')==='retirement'){
  $p=bioco_import_form_retirement_plan($state['groups'],$state['records'],$state['options'],$state['snapshot_site']??$state['site']);
  echo json_encode(['plan'=>$p,'apply'=>bioco_import_form_retirement_sql($p,$p['fingerprint']),'restore'=>bioco_import_form_retirement_sql($p,$p['fingerprint'],true)]);
 }else{
  $record=$state['record'];if(isset($state['blocks']))$record['post_content']=serialize_blocks($state['blocks']);
  $capture=static function($group,$block){$v=$GLOBALS['state']['captures'][$group];$v['source']=hash('sha256',wp_json_encode($block));return $v;};
  $p=bioco_import_form_plan($record,$state['snapshot_site']??$state['site'],$state['options'],$capture);
  echo json_encode(['plan'=>$p,'before_blocks'=>parse_blocks($record['post_content']),'blocks'=>parse_blocks($p['after_content']),
   'html'=>render_native_fixture(parse_blocks($p['after_content'])), 'apply'=>bioco_import_form_sql($p,$p['fingerprint']),'restore'=>bioco_import_form_sql($p,$p['fingerprint'],true)]);
 }
}catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]);}
'''


def run(**state):
    state.setdefault('site', ORIGIN + '/')
    state.setdefault('options', {'bioco_form_messages': None, 'bioco_divi_form_message_groups': None})
    state.setdefault('record', {'ID': 42, 'post_type': 'page', 'post_status': 'publish',
                               'post_title': 'Kontakt 100% Ä', 'post_content': '',
                               'post_modified_gmt': '2026-10-12 05:00:00'})
    result = subprocess.run(['php', '-r', PHP.replace('WP_SERIALIZER', WP_SERIALIZER_STUB)],
                            cwd=ROOT, input=json.dumps(state), text=True, capture_output=True)
    assert result.returncode == 0, result.stderr
    assert result.stderr == '', result.stderr
    return json.loads(result.stdout)


def block(name, attrs=None, children=None):
    children = children or []
    return {'blockName': name, 'attrs': attrs or {}, 'innerBlocks': children, 'innerHTML': '',
            'innerContent': [None] * len(children) if children else ['\n']}


def contact():
    html = '<section><h2>Kontakt</h2><p>Mit <strong>Hinweis</strong></p><div data-bioco-preview="1">'
    for name, type in [('name', 'text'), ('email', 'email'), ('subject', 'text'), ('message', 'textarea'), ('phone', 'tel')]:
        html += f'<div class="form-group"><label for="{name}">{name} *</label>'
        html += f'<textarea id="{name}" name="{name}" required></textarea>' if type == 'textarea' else f'<input id="{name}" name="{name}" type="{type}" {"required" if name != "phone" else ""}>'
        html += '</div>'
    html += '<button data-submit-label="Senden" data-submitting-label="Warten">Senden</button></div></section>'
    captures = {'contact-form': {'values': {'anchor': 'captured-contact', 'className': 'authored-class', 'success_message': '100% Erfolg', 'fallback_error': 'Captured failure'}, 'html': html}}
    legacy = block('bioco-divi/contact-form', {'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {'id': '', 'class': 'preserved'}}}}, 'decoration': {'spacing': {'phone': {'value': {'padding': {'top': '17px'}}}}}}})
    sibling = block('divi/text', {'content': {'innerContent': {'desktop': {'value': '<p>Sibling</p>'}}}})
    column = block('divi/column', {'module': {'id': 'column-id'}}, [sibling, legacy, sibling])
    column['innerContent'] = ['<div>opaque before</div>', None, '<!-- opaque middle -->', None, 'opaque after', None]
    return [block('divi/section', {}, [block('divi/row', {}, [column])])], captures


def walk(blocks):
    for value in blocks:
        yield value
        yield from walk(value['innerBlocks'])


def values(value):
    return {key: item['innerContent']['desktop']['value'] for key, item in value['attrs'].items() if key not in ('module', 'builderVersion') and 'innerContent' in item}


def test_conversion_preserves_exact_parent_siblings_geometry_and_native_copy():
    blocks, captures = contact()
    result = run(blocks=blocks, captures=captures)
    assert 'error' not in result, result
    assert result['plan']['changed'] == 1
    expected = copy.deepcopy(result['before_blocks'])
    expected[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][1] = result['blocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][1]
    assert result['blocks'] == expected
    converted = expected[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][1]
    assert converted['attrs']['module']['decoration']['spacing']['phone']['value']['padding']['top'] == '17px'
    html = converted['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']
    assert html['id'] == 'captured-contact' and all(word in html['class'] for word in ['preserved', 'authored-class', 'bioco-form-scope'])
    assert '<h2>Kontakt</h2>' in result['html'] and '<strong>Hinweis</strong>' in result['html']
    fields = [values(b) for b in walk(result['blocks']) if b['blockName'] == 'bioco-divi/form-field']
    assert [f['field'] for f in fields] == ['contact.name', 'contact.email', 'contact.subject', 'contact.message', 'contact.phone']
    again = run(record=result['plan']['before'] | {'post_content': result['plan']['after_content']}, captures={})
    assert again['plan']['changed'] == 0 and again['plan']['after_content'] == result['plan']['after_content']


def test_actual_saved_value_capture_preserves_blank_overrides_and_refuses_unknown_or_responsive_content():
    legacy = block('bioco-divi/contact-form', {'phone_label': {'innerContent': {'desktop': {'value': 'Old phone'}}}})
    captured = run(mode='capture', group='contact-form', block=legacy, capture_html='<section></section>', effective_messages={'phone_label': ''})
    assert captured['capture']['values']['phone_label'] == '' and captured['context'] == {'mode': 'inert'}
    for invalid in [legacy | {'innerHTML': '<p>Authored child</p>'}, legacy | {'innerBlocks': [block('divi/text')]},
                    legacy | {'attrs': {'unsupported': {}}}, legacy | {'attrs': {'module': {'unsupported': {}}}},
                    legacy | {'attrs': {'phone_label': {'innerContent': {'desktop': {'value': 'Desktop'}, 'phone': {'value': ''}}}}}]:
        assert 'error' in run(mode='capture', group='contact-form', block=invalid)


def database(plan):
    db = sqlite3.connect(':memory:')
    db.execute('CREATE TABLE posts (ID INTEGER PRIMARY KEY, post_type TEXT, post_status TEXT, post_title TEXT, post_content TEXT, post_modified_gmt TEXT)')
    records = plan.get('records', [plan.get('before')])
    for record in records: db.execute('INSERT INTO posts VALUES (?,?,?,?,?,?)', list(record.values()))
    db.execute('CREATE TABLE options (option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, option_value TEXT, autoload TEXT)')
    for option in plan['options'].values():
        if option: db.execute('INSERT INTO options VALUES (?,?,?,?)', list(option.values()))
    return db


def execute(db, sql):
    sql = re.sub(r'BINARY (`[^`]+`|option_name|option_value|autoload) = BINARY ', r'\1 COLLATE BINARY = ', sql)
    sql = sql.replace(' LIMIT 18446744073709551615', '')
    db.execute(sql)
    return db.execute('SELECT changes()').fetchone()[0]


def test_content_cas_restores_exact_bytes_and_refuses_repeat_edits_and_option_changes():
    blocks, captures = contact()
    result = run(blocks=blocks, captures=captures)
    assert '{wpdb-test}' not in result['apply'] and '100%' in result['apply']
    db = database(result['plan'])
    before = db.execute('SELECT * FROM posts').fetchall()
    assert execute(db, result['apply']) == 1 and execute(db, result['apply']) == 0
    assert execute(db, result['restore']) == 1 and execute(db, result['restore']) == 0
    assert db.execute('SELECT * FROM posts').fetchall() == before
    for key, new in [('post_content', 'Concurrent editor content'), ('post_title', 'Kontakt 100% A'), ('post_status', 'draft'), ('post_modified_gmt', 'later')]:
        db = database(result['plan']); db.execute(f'UPDATE posts SET {key}=?', (new,))
        assert execute(db, result['apply']) == 0
    db = database(result['plan']); db.execute("INSERT INTO options VALUES (1,'bioco_form_messages','changed','no')")
    assert execute(db, result['apply']) == 0
    assert 'error' in run(mode='sql', plan=result['plan'] | {'after_content': 'tampered'}, expected=result['plan']['fingerprint'])
    assert 'error' in run(mode='sql', plan=result['plan'], expected='wrong')


@pytest.mark.parametrize('mutation', ['unknown', 'missing', 'populated'])
def test_capture_refuses_unknown_controls_missing_required_fields_and_personal_defaults(mutation):
    blocks, captures = contact()
    html = captures['contact-form']['html']
    html = html.replace('name="name"', 'name="unrecognized"') if mutation == 'unknown' else html
    html = html.replace('name="name" type="text" required', 'name="name" type="hidden"') if mutation == 'missing' else html
    html = html.replace('name="name" type="text" required', 'name="name" type="text" value="Private name" required') if mutation == 'populated' else html
    captures['contact-form']['html'] = html
    assert 'error' in run(blocks=blocks, captures=captures)


def membership():
    html = '<section><div><p data-wizard-progress data-label="Schritt {current} von {total}"></p>'
    html += '<div class="form-step"><h3>Commitments</h3>'
    for i in range(4): html += f'<label><input name="commitmentAccepted[]" type="checkbox" required><div><h4>Heading {i}</h4><p>Read <strong>rich {i}</strong></p></div></label>'
    html += '</div><div class="form-step"><h3>Person</h3>'
    for name, type in [('firstName','text'), ('lastName','text'), ('address','text'), ('zip','text'), ('city','text'), ('email','email')]:
        html += f'<label for="{name}">{name}</label><input id="{name}" name="{name}" type="{type}" required>'
    html += '</div><div class="form-step"><h3>Payment</h3><div class="form-group"><label>Payment label</label><p>Payment hint</p><div class="radio-group">'
    for value, selected in [('quarterly', ''), ('yearly', 'checked')]: html += f'<label><input name="paymentType" type="radio" value="{value}" {selected}><span>{value} label</span></label>'
    html += '</div></div></div><div class="form-step"><h3>Added information only</h3><p>New native information</p></div>'
    html += '<div class="form-step"><h3>Choice</h3><label><input name="preferredDays[]" type="checkbox" value="0"><span>Zero choice</span></label></div>'
    html += '<div class="form-step"><h3>Confirm</h3><dl data-membership-summary></dl><label><input name="privacyAccept" type="checkbox" required><span>Consent</span></label></div>'
    html += '<button data-wizard-back>Back</button><button data-wizard-next>Next</button><button data-submit-label="Submit" data-submitting-label="Pending">Submit</button></div></section>'
    return {'membership-form': {'values': {'anchor': 'membership-captured', 'fallback_error': 'Safe failure'}, 'html': html}}


def test_membership_adoption_preserves_steps_rich_commitments_radio_default_and_zero_choice():
    result = run(blocks=[block('bioco-divi/membership-form')], captures=membership())
    assert 'error' not in result, result
    all_blocks = list(walk(result['blocks']))
    assert sum('bioco-form-step' in b['attrs'].get('module', {}).get('advanced', {}).get('htmlAttributes', {}).get('desktop', {}).get('value', {}).get('class', '') for b in all_blocks) == 6
    fields = [values(b) for b in all_blocks if b['blockName'] == 'bioco-divi/form-field']
    assert sum(f['field'] == 'membership.commitmentAccepted' for f in fields) == 4
    payment = next(f for f in fields if f['field'] == 'membership.paymentType')
    assert payment['choice_layout'] == 'radio' and payment['default_value'] == 'yearly'
    assert '<h4>Heading 0</h4>' in result['html'] and '<strong>rich 3</strong>' in result['html']
    assert 'value="0"' in result['html'] and 'value="yearly" checked' in result['html']
    controller = next(values(b) for b in all_blocks if b['blockName'] == 'bioco-divi/form-controller')
    assert controller['commitment_count'] == '4' and controller['thank_you_url'] == '/anmeldung-danke/'


def test_converted_membership_mounts_radio_default_handoff_arbitrary_step_and_retry():
    from playwright.sync_api import sync_playwright
    result = run(blocks=[block('bioco-divi/membership-form')], captures=membership())
    with sync_playwright() as p:
        browser = p.chromium.launch(); context = browser.new_context()
        context.add_init_script("window.traffic=[];window.turnstile={render:function(e,o){window.captcha=o;o.callback('fixture-token');return 1;},reset:function(){window.captcha.callback('retry-token');}}")
        page = context.new_page()

        def route(r):
            url = r.request.url
            if url.startswith(ORIGIN + '/wp-json/'):
                page.evaluate('(p)=>window.traffic.push(p)', r.request.post_data_json)
                r.fulfill(status=503, content_type='application/json', body=json.dumps({'success': False, 'error': 'Preserved backend error'}))
            elif url.endswith('/runtime.js'): r.fulfill(content_type='text/javascript', body=(CORE / 'assets/bioco-forms-lifecycle.js').read_text())
            elif url.endswith('/membership.js'): r.fulfill(content_type='text/javascript', body=(CORE / 'blocks/membership-form/view.js').read_text())
            elif url.endswith('/native.js'): r.fulfill(content_type='text/javascript', body=(CORE / 'form-composition/view.js').read_text())
            elif url.startswith(ORIGIN + '/?'):
                r.fulfill(content_type='text/html', body='<style>' + (CORE / 'form-composition/style.css').read_text() + '</style>' + result['html'] + '<script src="/runtime.js"></script><script src="/membership.js"></script><script src="/native.js"></script>')
            else: r.abort()

        context.route('**/*', route)
        page.goto(ORIGIN + '/?abo=halb-1-person&shares=2&additional=1')
        assert page.locator('[data-wizard-progress]').inner_text() == 'Schritt 1 von 6'
        for checkbox in page.locator('[name="commitmentAccepted[]"]').all(): checkbox.check()
        page.locator('[data-wizard-next]').click()
        for name in ['firstName', 'lastName', 'address', 'zip', 'city', 'email']:
            page.locator(f'[name="{name}"]').fill('fixture@example.test' if name == 'email' else '001' if name == 'zip' else name)
        page.locator('[data-wizard-next]').click()
        assert page.locator('[name="paymentType"][value="yearly"]').is_checked()
        page.locator('[data-wizard-next]').click()
        assert page.locator('h3').filter(has_text='Added information only').is_visible()
        page.locator('[data-wizard-next]').click()
        page.locator('[name="preferredDays[]"]').check()
        page.locator('[data-wizard-next]').click()
        page.locator('[name="privacyAccept"]').check()
        assert 'yearly label' in page.locator('[data-membership-summary]').inner_text()
        assert 'Zero choice' in page.locator('[data-membership-summary]').inner_text()
        page.locator('[type="submit"]').click()
        page.wait_for_function('window.traffic.length===1 && !document.querySelector("[type=submit]").disabled')
        payload = page.evaluate('window.traffic[0]')
        assert payload['paymentType'] == 'yearly' and payload['aboType'] == 'halb'
        assert payload['additionalShares'] == '1' and payload['zip'] == '001'
        assert payload['preferredDays'] == ['0'] and payload['commitmentAccepted'] == [True] * 4
        assert page.locator('[data-bioco-form-error-detail]').inner_text() == 'Preserved backend error'
        page.locator('[type="submit"]').click()
        page.wait_for_function('window.traffic.length===2 && !document.querySelector("[type=submit]").disabled')
        retry = page.evaluate('window.traffic[1]')
        assert retry['submissionId'] == payload['submissionId'] and retry['captchaToken'] == 'retry-token'
        assert {k: v for k, v in retry.items() if k != 'captchaToken'} == {k: v for k, v in payload.items() if k != 'captchaToken'}
        browser.close()


def test_doi_adoption_keeps_all_outcomes_all_typed_copy_and_saved_home_destination():
    captured = {'missing_token_message': 'Missing captured', 'confirmation_prompt': 'Prompt captured',
                'confirmation_button_label': 'Confirm captured', 'success_title': 'Success captured',
                'success_text': 'Thanks captured', 'subscribe_text': 'Subscribed captured', 'visit_text': 'Visit captured',
                'waiting_list_text': 'Waiting captured', 'contact_text': 'Contact captured',
                'error_title': 'Error captured', 'home_link_label': 'Home captured'}
    result = run(blocks=[block('bioco-divi/doi-confirm')], captures={'doi-confirm': {'values': captured, 'html': ''}})
    assert 'error' not in result, result
    classes = [b['attrs'].get('module', {}).get('advanced', {}).get('htmlAttributes', {}).get('desktop', {}).get('value', {}).get('class', '') for b in walk(result['blocks']) if isinstance(b['attrs'], dict)]
    assert all('bioco-doi-state-' + state in classes for state in ['missing', 'prompt', 'success', 'invalid', 'failure'])
    assert all('bioco-doi-type-' + type in classes for type in ['subscribe', 'visit', 'waiting_list', 'contact'])
    assert f'href="{ORIGIN}/"' in result['html'] and 'Subscribed captured' in result['html']
    assert result['blocks'][0]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] == 'newsletter-bestaetigung'


def test_exact_select_options_preserve_empty_labels_zero_values_order_and_waiting_placeholders():
    html = '<section><div>'
    for name in ['name', 'email', 'phone']:
        html += f'<label for="{name}">{name}</label><input id="{name}" name="{name}" type="{dict(name="text", email="email", phone="tel")[name]}" required>'
    html += '<label for="interest">Choice</label><select id="interest" name="interest" required><option value=""></option><option value="0" selected></option><option value="program1">Programm 1</option></select>'
    html += '<label><input name="privacy_accept" type="checkbox" required>Consent</label><button data-submit-label="Submit">Submit</button></div></section>'
    captures = {'waiting-list-form': {'html': html, 'values': {'interest_options': [{'value': 'program1', 'label': 'Programm 1'}]}}}
    result = run(blocks=[block('bioco-divi/waiting-list-form')], captures=captures)
    assert 'error' not in result, result
    field = next(values(b) for b in walk(result['blocks']) if b['blockName'] == 'bioco-divi/form-field' and values(b)['field'] == 'waiting-list.interest')
    assert field['options'] == [{'value': '', 'label': ''}, {'value': '0', 'label': ''}, {'value': 'program1', 'label': 'Programm 1'}]
    assert field['default_value'] == '0' and '<option value="0" selected></option>' in result['html']
    assert result['plan']['report'][0]['unconfirmed_options'] == captures['waiting-list-form']['values']['interest_options']


def test_event_record_heading_keeps_dynamic_title_while_generic_page_stays_blank():
    html = '<section><div>'
    for name in ['name', 'email']:
        html += f'<label for="{name}">{name}</label><input id="{name}" name="{name}" type="{"email" if name == "email" else "text"}" required>'
    html += '<button data-submit-label="Submit">Submit</button></div></section>'
    captures = {'event-signup-form': {'html': html, 'values': {'event_title_prefix': 'Anmeldung für:'}}}
    selected = [block('bioco-divi/event-signup-form')]
    generic = run(blocks=selected, captures=captures)
    assert not any(b['blockName'] == 'divi/heading' for b in walk(generic['blocks']))
    event = run(blocks=selected, captures=captures, record=generic['plan']['before'] | {'post_type': 'event'})
    heading = next(b for b in walk(event['blocks']) if b['blockName'] == 'divi/heading')
    title = heading['attrs']['title']['innerContent']['desktop']['value']
    assert title.startswith('Anmeldung für: $variable(') and 'bioco_event_title' in title
    assert 'error' in run(blocks=selected, captures=captures, record=generic['plan']['before'] | {'post_type': 'et_body_layout'})


def test_native_editor_rich_labels_preserve_copy_and_strip_executable_attributes():
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        browser = p.chromium.launch(); page = browser.new_page()
        page.set_content('<h1>Local editor seam</h1>')
        metadata = json.loads((CORE / 'form-composition/form-field/module.json').read_text())
        page.evaluate('''metadata=>{
            window.editors={};window.React={useRef:()=>({current:null}),createElement:(type,props,...children)=>({type:typeof type==='string'?type:'module',props:props||{},children})};
            window.BiocoFormCompositionData={modules:{'form-field':metadata},contracts:{membership:{commitmentAccepted:{type:'checkbox'}}}};
            window.divi={module:{ModuleContainer:function(){}},moduleLibrary:{registerModule:(m,options)=>window.editors[m.name]=options.renderers.edit},fieldLibrary:{registerFieldComponent:function(){}}};
        }''', metadata)
        page.add_script_tag(content=(CORE / 'form-composition/editor.js').read_text())
        tree = page.evaluate('''()=>window.editors['bioco-divi/form-field']({attrs:{field:{innerContent:{desktop:{value:'membership.commitmentAccepted'}}},label_html:{innerContent:{desktop:{value:'<h4>Keep rich</h4><p><strong>Keep strong</strong><img src="x" onerror="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)">Keep link label</a></p>'}}}},elements:{styleComponents:()=>null}})''')

        def nodes(value):
            if not isinstance(value, dict): return
            yield value
            for child in value.get('children', []):
                if isinstance(child, list):
                    for part in child: yield from nodes(part)
                else: yield from nodes(child)

        html = next(n['props']['dangerouslySetInnerHTML']['__html'] for n in nodes(tree) if 'dangerouslySetInnerHTML' in n['props'])
        assert '<h4>Keep rich</h4>' in html and '<strong>Keep strong</strong>' in html
        assert all(unsafe not in html for unsafe in ['<script', 'onerror', 'javascript:'])
        browser.close()


@pytest.mark.parametrize('existing', [False, True])
def test_separate_retirement_cas_preserves_archived_messages_blocks_new_legacy_and_restores_option(existing):
    blocks, captures = contact(); converted = run(blocks=blocks, captures=captures)['plan']
    record = converted['before'] | {'post_content': converted['after_content']}
    archived = {'option_id': '1', 'option_name': 'bioco_form_messages', 'option_value': 'archived 100% Ä FROM options WHERE', 'autoload': 'yes'}
    retired = {'option_id': '2', 'option_name': 'bioco_divi_form_message_groups', 'option_value': 'a:0:{}', 'autoload': 'yes'} if existing else None
    options = {'bioco_form_messages': archived, 'bioco_divi_form_message_groups': retired}
    result = run(mode='retirement', groups=['contact-form'], records=[record], options=options)
    assert 'error' not in result, result
    db = database(result['plan']); before = db.execute('SELECT * FROM options').fetchall()
    assert execute(db, result['apply']) == 1 and execute(db, result['apply']) == 0
    assert db.execute('SELECT option_value FROM options WHERE option_id=1').fetchone()[0] == archived['option_value']
    assert execute(db, result['restore']) == 1
    assert db.execute('SELECT * FROM options').fetchall() == before
    db = database(result['plan']); db.execute('INSERT INTO posts VALUES (?,?,?,?,?,?)', [77, 'page', 'draft', 'New legacy', '<!-- wp:bioco-divi/contact-form /-->', 'now'])
    assert execute(db, result['apply']) == 0
    assert 'error' in run(mode='retirement', groups=['contact-form'], records=[converted['before']], options=options)
    assert 'error' in run(mode='retirement', groups=['shared'], records=[record], options=options)


def test_retirement_refuses_orphan_and_incomplete_native_scopes():
    blocks, captures = contact(); converted = run(blocks=blocks, captures=captures)['plan']
    controller = block('bioco-divi/form-controller', {'form_type': {'innerContent': {'desktop': {'value': 'contact'}}}})
    for content in ['<!-- wp:bioco-divi/form-controller {"form_type":{"innerContent":{"desktop":{"value":"contact"}}}} /-->',
                    converted['after_content'].replace('contact.name', 'contact.invented')]:
        result = run(mode='retirement', groups=['contact-form'], records=[converted['before'] | {'post_content': content}])
        assert 'error' in result
