"""Real membership REST handler, with network restricted to mocked CAPTCHA."""
import json
import hmac
import hashlib
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]


def valid_data(**extra):
    return dict(firstName='Stage', lastName='Test', email='stage@example.test', address='Testweg 1', zip='5400', city='Baden', phone='0561234567', mobilePhone='0791234567', birthday='1990-01-02', comment='Test', privacyAccept=True, commitmentAccepted=[True]*4, commitmentCount="4", commitmentSignature=hmac.new(b"test-salt", b"membership-commitments:4", hashlib.sha256).hexdigest(), membershipType='abo', aboType='standard', additionalShares=0, sharesOnly=0, submissionId='a'*32, captchaToken='mock', **extra)


def requests(items, mode='fake', environment='staging', host='staging.bioco.ch', mail_result='throw', storage_failure=False):
    scenario = dict(items=items, mode=mode, environment=environment, host=host, mail_result=mail_result, storage_failure=storage_failure)
    php = r'''
    define('ABSPATH', __DIR__);
    $scenario = json_decode($argv[1], true);
    $options = ['bioco_membership_adapter'=>$scenario['mode']];
    $adapter_calls=0; $mail=0; $network=[]; $mail_record=null; $mail_args=[]; $overlap=null;
    function wp_salt($scheme) {return 'test-salt';}
    function add_action(...$args) {}
    function get_option($key,$default=false) {
        if ($key==='bioco_membership_fake_result') $GLOBALS['adapter_calls']++;
        return $GLOBALS['options'][$key] ?? $default;
    }
    function add_option($key,$value,...$args) {if($GLOBALS['scenario']['storage_failure'])return false; if(array_key_exists($key,$GLOBALS['options']))return false; $GLOBALS['options'][$key]=$value; return true;}
    function update_option($key,$value,...$args) {$GLOBALS['options'][$key]=$value; return true;}
    function delete_option($key) {unset($GLOBALS['options'][$key]);}
    function home_url($path='') {return 'https://'.$GLOBALS['scenario']['host'].$path;}
    function wp_get_environment_type() {return $GLOBALS['scenario']['environment'];}
    function wp_json_encode($value) {return json_encode($value);}
    function sanitize_text_field($value) {return trim(strip_tags($value));}
    function sanitize_textarea_field($value) {return trim(strip_tags($value));}
    function sanitize_email($value) {return $value;}
    function is_email($value) {return filter_var($value,FILTER_VALIDATE_EMAIL);}
    function wp_remote_post($url,$args) {
        $GLOBALS['network'][]=$url;
        if($url!=='https://challenges.cloudflare.com/turnstile/v0/siteverify')throw new Exception('Live network forbidden');
        return ['code'=>200,'body'=>'{"success":true}'];
    }
    function wp_remote_retrieve_response_code($r) {return $r['code'];}
    function wp_remote_retrieve_body($r) {return $r['body'];}
    function is_wp_error($v) {return false;}
    function wp_mail(...$args) {
        $GLOBALS['mail']++;
        $GLOBALS['mail_args'][]=['to'=>$args[0],'subject'=>$args[1],'body'=>$args[2],'headers'=>$args[3] ?? ''];
        $records=array_values(array_filter($GLOBALS['options'],fn($v)=>is_array($v)&&($v['adapter']??'')==='local'));
        $GLOBALS['mail_record']=$records[0]??null;
        // Interleave a retry while the winning request is notifying.
        $retry=bioco_forms_handle_membership(new WP_REST_Request($GLOBALS['scenario']['items'][0]['data']));
        $GLOBALS['overlap']=['status'=>$retry->status,'data'=>$retry->data]; if($GLOBALS['scenario']['mail_result']==='throw')throw new Exception('Real mail forbidden'); return $GLOBALS['scenario']['mail_result']==='sent';}
    class WP_REST_Request {function __construct(public $data){} function get_json_params(){return $this->data;}}
    class WP_REST_Response {function __construct(public $data,public $status){}}
    putenv('TURNSTILE_SECRET_KEY=mock'); putenv('NEXT_PUBLIC_TURNSTILE_SITE_KEY=mock'); putenv('BIOCO_FORMS_RECIPIENT');
    require 'wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php';
    $GLOBALS['bioco_forms_missing_fields_error']='missing'; $GLOBALS['bioco_forms_captcha_error']='captcha';
    $responses=[];
    foreach($scenario['items'] as $item) {
        $options['bioco_membership_fake_result']=$item['outcome'] ?? 'accepted';
        $r=bioco_forms_handle_membership(new WP_REST_Request($item['data']));
        $responses[]=['status'=>$r->status,'data'=>$r->data];
    }
    echo json_encode(['responses'=>$responses,'adapter_calls'=>$adapter_calls,'mail'=>$mail,'mail_args'=>$mail_args,'network'=>$network,'mail_record'=>$mail_record,'overlap'=>$overlap,'records'=>array_values(array_filter($options,fn($v)=>is_array($v)&&($v['adapter']??'')==='local')),'payload'=>bioco_forms_build_intranet_payload($scenario['items'][0]['data'])]);
    '''
    result = subprocess.run(['php', '-r', php, json.dumps(scenario)], cwd=ROOT, text=True, capture_output=True, check=True)
    assert 'Warning' not in result.stderr
    return json.loads(result.stdout)


def test_accepted_retry_is_terminal_without_mail_or_live_network():
    data = valid_data()
    result = requests([{'data': data}, {'data': data}])
    assert [r['status'] for r in result['responses']] == [200, 200]
    assert result['responses'][0]['data']['receipt'] == result['responses'][1]['data']['receipt']
    assert result['adapter_calls'] == 1
    assert result['mail'] == 0
    assert result['network']
    assert set(result['network']) == {'https://challenges.cloudflare.com/turnstile/v0/siteverify'}
    payload = result['payload']
    assert {key: payload[key] for key in ['addr_street','addr_zipcode','addr_location','phone','mobile_phone','email','birthday','comment','agb']} == dict(addr_street='Testweg 1',addr_zipcode='5400',addr_location='Baden',phone='0561234567',mobile_phone='0791234567',email='stage@example.test',birthday='1990-01-02',comment='Test',agb='on')
    assert not {'street','postal_code','city','notes','terms'} & payload.keys()


@pytest.mark.parametrize('outcome,status', [('validation',400),('unavailable',502)])
def test_recoverable_adapter_failures_allow_safe_retry(outcome,status):
    data=valid_data()
    result=requests([{'data':data,'outcome':outcome},{'data':data}])
    assert [r['status'] for r in result['responses']] == [status,200]
    assert result['adapter_calls'] == 2


def test_changed_payload_cannot_reuse_accepted_identity():
    data=valid_data()
    result=requests([{'data':data},{'data':dict(data,email='different@example.test')}])
    assert [r['status'] for r in result['responses']] == [200,400]
    assert result['adapter_calls'] == 1


@pytest.mark.parametrize('mode,environment,host', [('live','staging','staging.bioco.ch'),('live','production','staging.bioco.ch'),('fake','production','bioco.ch'),('disabled','staging','staging.bioco.ch')])
def test_staging_cannot_call_live_adapter_and_production_cannot_fake_accept(mode,environment,host):
    result=requests([{'data':valid_data()}],mode,environment,host)
    assert result['responses'][0]['status'] == 502
    assert result['adapter_calls'] == 0
    assert result['mail'] == 0


@pytest.mark.parametrize('changes', [{'commitmentAccepted':[True,True]}, {'commitmentCount':'2','commitmentAccepted':[True,True]}, {'preferredDays':{'unexpected':'Monday'}}, {'commitmentAccepted':{'unexpected':True}}, {'commitmentAccepted':[False]}, {'birthday':'2026-02-31'}, {'mobilePhone':[]}, {'submissionId':''}])
def test_invalid_data_never_reaches_adapter(changes):
    result=requests([{'data':dict(valid_data(),**changes)}])
    assert result['responses'][0]['status'] == 400
    assert result['adapter_calls'] == 0


@pytest.mark.parametrize('mail_result,notification', [('sent', 'sent'), ('false', 'failed'), ('throw', 'failed')])
def test_local_retains_full_registration_before_mail_and_replays(mail_result, notification):
    data = valid_data()
    data.update(preferredDays=['Montag'], preferredTimes=['Morgen'], activityAreas=['Ernte'],
                otherActivity='Andere Arbeit', zusatzabos=['Brot'], weitereProdukte='Eier',
                depot='Baden', paymentType='yearly', additionalShares='3', sharesOnly='0')
    result = requests([{'data': data}, {'data': dict(data, captchaToken='new-token')}],
                      'local', 'production', 'bioco.ch', mail_result)
    assert [r['status'] for r in result['responses']] == [200, 200]
    assert result['responses'][0]['data'] == result['responses'][1]['data']
    assert result['responses'][0]['data']['simulated'] is False
    assert result['mail'] == 1
    assert len(result['records']) == 1
    record = result['records'][0]
    assert record['status'] == 'accepted'
    assert record['notification'] == notification
    assert result['mail_record']['status'] == 'accepted'
    assert result['mail_record']['notification'] == 'pending'
    assert result['mail_record']['data'] == record['data']
    assert result['overlap'] == result['responses'][0]
    assert record['receipt'] == result['responses'][0]['data']['receipt']
    expected = {k: v for k, v in data.items() if k not in ('captchaToken', 'commitmentSignature')}
    expected.update(additionalShares=3, sharesOnly=0, commitmentCount=4)
    assert record['data'] == expected
    assert set(result['network']) == {'https://challenges.cloudflare.com/turnstile/v0/siteverify'}


def test_local_conflict_checks_fields_missing_from_intranet_mapping():
    data = valid_data()
    result = requests([{'data': data}, {'data': dict(data, commitmentCount='3',
        commitmentAccepted=[True]*3, commitmentSignature=hmac.new(b'test-salt', b'membership-commitments:3', hashlib.sha256).hexdigest())}],
        'local', 'production', 'bioco.ch', 'sent')
    assert [r['status'] for r in result['responses']] == [200, 400]
    assert result['mail'] == 1
    assert len(result['records']) == 1


@pytest.mark.parametrize('environment,host', [('staging', 'staging.bioco.ch'), ('production', 'staging.bioco.ch'), ('staging', 'bioco.ch'), ('production', 'localhost'), ('production', 'www.bioco.ch')])
def test_local_cannot_accept_outside_production_apex(environment, host):
    result = requests([{'data': valid_data()}], 'local', environment, host)
    assert result['responses'][0]['status'] == 502
    assert result['mail'] == 0
    assert result['records'] == []


def test_local_storage_failure_never_accepts_or_sends_mail():
    result = requests([{'data': valid_data()}], 'local', 'production', 'bioco.ch', storage_failure=True)
    assert result['responses'][0]['status'] == 502
    assert result['mail'] == 0
    assert result['records'] == []


def test_local_rejects_unvalidated_extra_fields():
    result = requests([{'data': dict(valid_data(), extra={'secret': 'no'})}], 'local', 'production', 'bioco.ch')
    assert result['responses'][0]['status'] == 400
    assert result['records'] == []


def test_backup_mail_reaches_info_with_complete_signup_summary_across_replay():
    data = valid_data()
    data.update(preferredDays=['Montag'], preferredTimes=['Morgen'], activityAreas=['Ernte'],
                otherActivity='Andere Arbeit', zusatzabos=['Brot'], weitereProdukte='Eier',
                depot='Baden', paymentType='yearly', additionalShares='3', sharesOnly='0')
    result = requests([{'data': data}, {'data': dict(data, captchaToken='new-token')}],
                      'local', 'production', 'bioco.ch', 'sent')
    assert [r['status'] for r in result['responses']] == [200, 200]
    assert result['responses'][0]['data'] == result['responses'][1]['data']
    # Exactly one backup notification attempt, even across the accepted
    # identical replay (interleaved retry included by the harness).
    assert result['mail'] == 1
    assert len(result['mail_args']) == 1
    mail = result['mail_args'][0]
    assert mail['to'] == 'info@bioco.ch'
    assert mail['subject'] == 'Neue Mitgliedschaftsanmeldung: Stage Test'
    assert mail['body'].startswith(f"Receipt: {result['responses'][0]['data']['receipt']}\n")
    expected_lines = [
        'Vorname: Stage',
        'Name: Test',
        'Adresse: Testweg 1, 5400 Baden',
        'E-Mail: stage@example.test',
        'Telefon: 0561234567',
        'Mobiltelefon: 0791234567',
        'Geburtsdatum: 1990-01-02',
        'Mitgliedschaft: Gemüseabo',
        'Gemüsekorb: standard',
        'Anteilsscheine: 5',
        'Depot: Baden',
        'Zahlungsweise: Ganzes Jahr',
        'Bevorzugte Tage: Montag',
        'Bevorzugte Zeiten: Morgen',
        'Tätigkeitsbereiche: Ernte',
        'Andere Tätigkeit: Andere Arbeit',
        'Zusatzabos: Brot',
        'Weitere Produkte: Eier',
    ]
    for line in expected_lines:
        assert line in mail['body'], line
    assert 'Reply-To: stage@example.test' in mail['headers']
    assert 'Content-Type: text/plain; charset=UTF-8' in mail['headers']


def test_actual_browser_transport_field_is_accepted_but_never_persisted():
    # Expose the real private serializer in the VM harness, without replacing it.
    node = r'''const fs=require('fs'),vm=require('vm');
    const input=JSON.parse(process.argv[1]);
    const elements=[];
    for(const [name,value] of Object.entries(input)) {
      for(const item of (Array.isArray(value)?value:[value])) {
        elements.push({name:name+(Array.isArray(value)?'[]':''),type:typeof item==='boolean'?'checkbox':'hidden',value:String(item),checked:item===true,hasAttribute:()=>name==='commitmentAccepted'});
      }
    }
    elements.push({name:'cf-turnstile-response',type:'hidden',value:'ephemeral-browser-token'});
    const context=vm.createContext({window:{}});
    let source=fs.readFileSync('wordpress/web/app/mu-plugins/bioco-core/assets/bioco-forms-lifecycle.js','utf8');
    source=source.replace('window.BiocoForms = { mount: mount };','window.BiocoForms = { mount: mount, serializeForm: serializeForm };');
    vm.runInContext(source,context);
    const data=context.window.BiocoForms.serializeForm({elements});
    data.captchaToken='mock';
    process.stdout.write(JSON.stringify(data));'''
    serialized = subprocess.run(['node', '-e', node, json.dumps(valid_data())], cwd=ROOT, check=True, capture_output=True, text=True)
    data = json.loads(serialized.stdout)
    assert data['cf-turnstile-response'] == 'ephemeral-browser-token'
    result = requests([{'data': data}, {'data': dict(data, **{'cf-turnstile-response': 'fresh-browser-token'})}],
                      'local', 'production', 'bioco.ch', 'sent')
    assert [r['status'] for r in result['responses']] == [200, 200]
    assert result['mail'] == 1
    assert 'cf-turnstile-response' not in result['records'][0]['data']
    assert 'ephemeral-browser-token' not in json.dumps(result['records'])
