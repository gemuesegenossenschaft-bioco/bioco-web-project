"""#205–#206 native form payloads and arbitrary wizard rows.

Keep/Replace/Remove map: tests/README.md. Actual PHP parts and browser scripts
run without licensed Divi; all transport, mail, CAPTCHA and redirects stay local.
"""
import json
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT/'wordpress/web/app/mu-plugins/bioco-core'
ORIGIN = 'https://native-flows.test'
PHP = r'''
define('ABSPATH',__DIR__);
function add_action(...$args) {}
function wp_unique_id($prefix) {static $n=0;return $prefix.++$n;}
function wp_json_encode($v) {return json_encode($v);}
function esc_attr($v) {return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_html($v) {return htmlspecialchars((string)$v,ENT_QUOTES);}
function rest_url($path) {return 'https://native-flows.test/wp-json/'.$path;}
function wp_enqueue_script(...$args) {} function wp_enqueue_style(...$args) {}
function bioco_forms_turnstile_config() {return ['configured'=>true,'site_key'=>'fixture-key'];}
function bioco_forms_commitment_signature($count) {return hash_hmac('sha256',(string)$count,'fixture-salt');}
function is_singular($type) {return $type==='event';}
function get_the_ID() {return 987;}
function get_the_title() {return 'Fixture event';}
function bioco_price_catalog() {return $GLOBALS['catalog'] ?? null;}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-composition.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-form-templates.php';
$result=[];
foreach (['subscribe','visit-day','waiting-list','event-signup','membership'] as $type) {
 $fields=[];
 foreach (bioco_form_contracts()[$type] as $name=>$spec) {
   $repeat=$name==='commitmentAccepted'?2:1;
   for ($i=0;$i<$repeat;$i++) $fields[$name][] = bioco_form_render_part('form-field',['field'=>$type.'.'.$name,'label'=>'Captured '.$name.' '.$i,'choice_value'=>'captured-choice','options'=>[['value'=>'captured-option','label'=>'Captured option']]],'public');
 }
 $values=['form_type'=>$type,'success_message'=>'Captured success '.$type,'fallback_error'=>'Captured error '.$type,'captcha_error'=>'Captured captcha','transport_error'=>'Captured transport','commitment_count'=>'2','thank_you_url'=>'/custom-thanks/'];
 $parts=[];
 foreach (['submit','next','back','progress','summary'] as $action) $parts[$action]=bioco_form_render_part('form-action',['action'=>$action,'label'=>$action==='progress'?'Step {current} of {total}':$action,'pending_label'=>'pending'],'public');
 $reviews=[];foreach(['email','firstName'] as $name)$reviews[$name]=bioco_form_render_part('form-value',['field'=>'membership.'.$name,'label'=>'Review '.$name],'public');
 $result[$type]=['fields'=>$fields,'controller'=>bioco_form_render_part('form-controller',$values,'public'),'actions'=>$parts,'reviews'=>$reviews];
}
$GLOBALS['catalog']=['tiers'=>[['key'=>'renamed-stable-key','abo_type'=>'halb'],['key'=>'unsupported','abo_type'=>'invented']]];
$result['catalog-controller']=bioco_form_render_part('form-controller',['form_type'=>'membership','commitment_count'=>'2'],'public');
$GLOBALS['catalog']=['tiers'=>[]];
$result['empty-catalog-controller']=bioco_form_render_part('form-controller',['form_type'=>'membership','commitment_count'=>'2'],'public');
$captured=[];foreach(bioco_form_contracts()['membership'] as $name=>$spec){if(!$spec['required'])continue;for($i=0;$i<($name==='commitmentAccepted'?2:1);$i++)$captured[]=['field'=>'membership.'.$name,'label'=>'Captured '.$name.' '.$i];}
$steps=[['title'=>'Captured new information step','text'=>'<p>Native added copy</p>'],['title'=>'Captured fields','fields'=>$captured],['title'=>'Captured review','review_fields'=>[['field'=>'membership.email','label'=>'Captured review label']]]];
$values=['commitment_count'=>'2','next_label'=>'Next','back_label'=>'Back','progress_label'=>'{current} / {total}','submit_label'=>'Send'];
$result['membership-candidate']=bioco_membership_compose($values,$steps);
$refused=[];foreach([array_merge($values,['commitment_count'=>'1']),array_merge($values,['next_label'=>''])] as $invalid){try{bioco_membership_compose($invalid,$steps);$refused[]=false;}catch(InvalidArgumentException $error){$refused[]=true;}}
$result['refused-membership']=$refused;
echo json_encode($result);
'''

@pytest.fixture(scope='module')
def rendered():
    result=subprocess.run(['php','-r',PHP],cwd=ROOT,text=True,capture_output=True)
    assert result.returncode==0,result.stderr
    return json.loads(result.stdout)

@pytest.fixture()
def browser():
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        b=p.chromium.launch()
        yield b
        b.close()


def fixture_html(type, parts):
    fields=parts['fields']; actions=parts['actions']
    rows=[]
    if type=='membership':
        # The added information row and split personal groups make seven steps.
        groups=[[],['commitmentAccepted'],['email','firstName','lastName'],['address','zip','city'],['depot','paymentType'],['preferredDays','comment'],['privacyAccept']]
        for index,names in enumerate(groups):
            content=''.join(''.join(fields[name]) for name in names)
            if index==len(groups)-1: content+=actions['summary']+parts['reviews']['email']+parts['reviews']['firstName']
            rows.append('<div class="et_pb_row bioco-form-step"><div class="et_pb_column"><h3>Authored step '+str(index)+'</h3>'+content+'</div></div>')
        footer=actions['progress']+actions['back']+actions['next']+actions['submit']
    else:
        rows=['<div class="et_pb_row bioco-form-fields"><div class="et_pb_column">'+''.join(''.join(fields[name]) for name in reversed(list(fields)))+actions['submit']+'</div></div>']
        footer=''
    return '<!doctype html><html><head><style>'+ (CORE/'form-composition/style.css').read_text()+'</style></head><body><section class="bioco-form-scope">'+parts['controller']+''.join(rows)+footer+'<div class="bioco-form-state-success" hidden>Captured native success</div></section><script src="/runtime.js"></script><script src="/membership.js"></script><script src="/native.js"></script></body></html>'


def open_page(browser,rendered,type):
    ctx=browser.new_context()
    ctx.add_init_script('''window.traffic=[];window.answers=[];window.token=0;window.turnstile={render:function(el,opts){window.captcha=opts;opts.callback('fixture-token');return 1;},reset:function(){window.captcha.callback('retry-token');}};''')
    page=ctx.new_page()
    def route(r):
        url=r.request.url
        if url.startswith(ORIGIN+'/custom-thanks/'):
            r.fulfill(status=200,content_type='text/html',body='<h1>Captured thank-you page</h1>')
        elif url.startswith(ORIGIN+'/wp-json/bioco/v1/'):
            payload=r.request.post_data_json
            page.evaluate('(p)=>window.traffic.push(p)',payload)
            answer=page.evaluate('window.answers.shift()||{status:200,body:{success:true,forwarded:false}}')
            r.fulfill(status=answer['status'],content_type='application/json',body=json.dumps(answer['body']))
        elif url.startswith(ORIGIN+'/?') or url==ORIGIN+'/': r.fulfill(status=200,content_type='text/html',body=fixture_html(type,rendered[type]))
        elif url==ORIGIN+'/runtime.js': r.fulfill(status=200,content_type='text/javascript',body=(CORE/'assets/bioco-forms-lifecycle.js').read_text())
        elif url==ORIGIN+'/membership.js': r.fulfill(status=200,content_type='text/javascript',body=(CORE/'blocks/membership-form/view.js').read_text())
        elif url==ORIGIN+'/native.js': r.fulfill(status=200,content_type='text/javascript',body=(CORE/'form-composition/view.js').read_text())
        else: r.abort()
    ctx.route('**/*',route)
    page.goto(ORIGIN+'/?abo=standard-2-3-personen&shares=4&additional=2')
    return page,ctx


def fill_controls(page):
    for control in page.locator('[data-bioco-field]').all():
        type=control.get_attribute('type')
        if control.evaluate('(e)=>e.tagName')=='SELECT': control.select_option('captured-option')
        elif type=='checkbox': control.check()
        elif type=='email': control.fill('fixture@example.test')
        elif type=='date': control.fill('2027-01-02')
        elif type=='number': control.fill('2')
        else: control.fill('captured value')


@pytest.mark.parametrize('type',['subscribe','visit-day','waiting-list','event-signup'])
def test_each_native_type_preserves_payload_retry_and_success(browser,rendered,type):
    page,ctx=open_page(browser,rendered,type)
    try:
        fill_controls(page)
        page.evaluate('window.answers.push({status:503,body:{success:false,error:"Safe unavailable"}})')
        page.locator('[type="submit"]').click()
        page.wait_for_function('window.traffic.length===1 && document.querySelector("[type=submit]").disabled===false')
        payload=page.evaluate('window.traffic[0]')
        assert payload['email']=='fixture@example.test'
        if type in ('subscribe','visit-day','waiting-list'): assert payload['privacy_accept'] is True
        if type=='visit-day': assert payload['participants']==2 and payload['visit_date']=='2027-01-02'
        if type=='waiting-list': assert payload['interest']=='captured-option'
        if type=='event-signup': assert payload['eventId']=='987' and payload['eventTitle']=='Fixture event'
        page.locator('[type="submit"]').click()
        page.wait_for_function('window.traffic.length===2 && document.querySelector(".bioco-form-scope").dataset.biocoFormState==="success"')
        retry=page.evaluate('window.traffic[1]')
        assert {k:v for k,v in payload.items() if k!='captchaToken'}=={k:v for k,v in retry.items() if k!='captchaToken'}
        assert retry['captchaToken']=='retry-token'
        assert page.locator('.bioco-form-state-success').is_visible()
    finally: ctx.close()


def test_added_native_membership_step_back_prefill_summary_error_retry(browser,rendered):
    page,ctx=open_page(browser,rendered,'membership')
    try:
        assert page.locator('.bioco-form-step').count()==7
        assert page.locator('[data-wizard-progress]').inner_text()=='Step 1 of 7'
        page.locator('[data-wizard-next]').click()
        for field in page.locator('[name="commitmentAccepted[]"]').all(): field.check()
        page.locator('[data-wizard-next]').click()
        page.locator('[name="email"]').fill('fixture@example.test')
        page.locator('[name="firstName"]').fill('First')
        page.locator('[name="lastName"]').fill('Last')
        page.locator('[data-wizard-next]').click()
        for name in ('address','zip','city'): page.locator('[name="'+name+'"]').fill('001' if name=='zip' else name)
        page.locator('[data-wizard-back]').click()
        assert page.locator('[name="firstName"]').input_value()=='First'
        page.locator('[data-wizard-next]').click()
        for _ in range(3): page.locator('[data-wizard-next]').click()
        assert page.locator('[data-wizard-progress]').inner_text()=='Step 7 of 7'
        assert 'fixture@example.test' in page.locator('[data-membership-summary]').inner_text()
        assert page.locator('[data-bioco-review-field="membership.email"]').inner_text()=='fixture@example.test'
        assert page.locator('[data-bioco-review-field="membership.firstName"]').inner_text()=='First'
        page.locator('[name="privacyAccept"]').check()
        page.evaluate('window.answers.push({status:503,body:{success:false,error:"Safe unavailable",fieldErrors:{email:"Rejected"}}})')
        page.locator('[type="submit"]').click()
        page.wait_for_function('window.traffic.length===1 && document.querySelector("[type=submit]").disabled===false')
        assert page.locator('[data-wizard-progress]').inner_text()=='Step 3 of 7'
        original=page.evaluate('window.traffic[0]')
        assert original['membershipType']=='abo' and original['aboType']=='standard'
        assert original['additionalShares']=='2' and original['sharesOnly']=='0'
        assert original['commitmentAccepted']==[True,True]
        assert original['commitmentCount']=='2' and original['commitmentSignature']
        assert original['zip']=='001' and len(original['submissionId'])==32
        for _ in range(4): page.locator('[data-wizard-next]').click()
        # Verify retry identity before the terminal response navigates away.
        page.evaluate('window.answers.push({status:503,body:{success:false,error:"Safe retry"}})')
        page.locator('[type="submit"]').click()
        page.wait_for_function('window.traffic.length===2 && document.querySelector("[type=submit]").disabled===false')
        assert page.evaluate('window.traffic[1].submissionId')==original['submissionId']
        page.locator('[type="submit"]').click()
        page.wait_for_url(ORIGIN+'/custom-thanks/')
        assert page.locator('h1').inner_text()=='Captured thank-you page'
    finally: ctx.close()


def test_membership_candidate_allows_information_steps_and_independent_review_values(rendered):
    rows=rendered['membership-candidate']['innerBlocks']
    steps=[row for row in rows if 'bioco-form-step' in row['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class']]
    assert len(steps)==3
    assert [child['blockName'] for child in steps[0]['innerBlocks'][0]['innerBlocks']]==['divi/heading','divi/text']
    controls=steps[1]['innerBlocks'][0]['innerBlocks']
    assert sum(child['blockName']=='bioco-divi/form-field' and child['attrs']['field']['innerContent']['desktop']['value']=='membership.commitmentAccepted' for child in controls)==2
    assert steps[-1]['innerBlocks'][0]['innerBlocks'][-1]['blockName']=='bioco-divi/form-value'
    assert rendered['refused-membership']==[True,True]


def test_native_membership_catalog_mapping_preserves_explicit_empty_adoption(rendered):
    from html.parser import HTMLParser
    class Config(HTMLParser):
        def handle_starttag(self,tag,attrs):
            attrs=dict(attrs)
            if tag=='form': self.config=json.loads(attrs['data-bioco-instance-config'])
    populated=Config();populated.feed(rendered['catalog-controller'])
    empty=Config();empty.feed(rendered['empty-catalog-controller'])
    legacy=Config();legacy.feed(rendered['membership']['controller'])
    assert populated.config['aboTypes']=={'renamed-stable-key':'halb'}
    assert empty.config['aboTypes']=={}
    assert 'aboTypes' not in legacy.config
