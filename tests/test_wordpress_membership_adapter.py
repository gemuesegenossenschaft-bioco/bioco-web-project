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


def requests(items, mode='fake', environment='staging', host='staging.bioco.ch'):
    scenario = dict(items=items, mode=mode, environment=environment, host=host)
    php = r'''
    define('ABSPATH', __DIR__);
    $scenario = json_decode($argv[1], true);
    $options = ['bioco_membership_adapter'=>$scenario['mode']];
    $adapter_calls=0; $mail=0; $network=[];
    function wp_salt($scheme) {return 'test-salt';}
    function add_action(...$args) {}
    function get_option($key,$default=false) {
        if ($key==='bioco_membership_fake_result') $GLOBALS['adapter_calls']++;
        return $GLOBALS['options'][$key] ?? $default;
    }
    function add_option($key,$value,...$args) {if(array_key_exists($key,$GLOBALS['options']))return false; $GLOBALS['options'][$key]=$value; return true;}
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
    function wp_mail(...$args) {$GLOBALS['mail']++; throw new Exception('Real mail forbidden');}
    class WP_REST_Request {function __construct(public $data){} function get_json_params(){return $this->data;}}
    class WP_REST_Response {function __construct(public $data,public $status){}}
    putenv('TURNSTILE_SECRET_KEY=mock'); putenv('NEXT_PUBLIC_TURNSTILE_SITE_KEY=mock');
    require 'wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php';
    $GLOBALS['bioco_forms_missing_fields_error']='missing'; $GLOBALS['bioco_forms_captcha_error']='captcha';
    $responses=[];
    foreach($scenario['items'] as $item) {
        $options['bioco_membership_fake_result']=$item['outcome'] ?? 'accepted';
        $r=bioco_forms_handle_membership(new WP_REST_Request($item['data']));
        $responses[]=['status'=>$r->status,'data'=>$r->data];
    }
    echo json_encode(['responses'=>$responses,'adapter_calls'=>$adapter_calls,'mail'=>$mail,'network'=>$network,'payload'=>bioco_forms_build_intranet_payload($scenario['items'][0]['data'])]);
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
