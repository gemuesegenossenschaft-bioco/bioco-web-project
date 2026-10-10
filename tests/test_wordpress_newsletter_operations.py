"""Execute newsletter handlers with captured transport and isolated WordPress storage."""
import csv
import io
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PREAMBLE = r'''
define('ABSPATH', '/');
$GLOBALS['hooks']=[]; $GLOBALS['posts']=[]; $GLOBALS['meta']=[]; $GLOBALS['options']=[]; $GLOBALS['mail']=[]; $GLOBALS['scheduled']=[];
function add_action($key,$callback,...$args) {$GLOBALS['hooks'][$key]=$callback;}
function get_post($id) {return $GLOBALS['posts'][$id]??null;}
function get_post_meta($id,$key,$single=true) {return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value) {$GLOBALS['meta'][$id][$key]=$value;return true;}
function delete_post_meta($id,$key) {unset($GLOBALS['meta'][$id][$key]);}
function add_post_meta($id,$key,$value,$unique=false) {if($unique&&isset($GLOBALS['meta'][$id][$key]))return false;return update_post_meta($id,$key,$value);}
function add_option($key,$value,...$args) {if(isset($GLOBALS['options'][$key]))return false;$GLOBALS['options'][$key]=$value;return true;}
function delete_option($key) {unset($GLOBALS['options'][$key]);}
function is_email($email) {return filter_var($email,FILTER_VALIDATE_EMAIL);}
function wp_salt($scheme) {return 'isolated-test-salt';}
function current_time($format) {return $GLOBALS['clock']??'2026-10-09 22:00:00';}
function home_url($path) {return 'https://example.test'.$path;}
function add_query_arg($args,$url) {return $url.'?'.http_build_query($args);}
function wp_mail(...$args) {$GLOBALS['mail'][]=$args;return $GLOBALS['mail_ok']??true;}
function wp_schedule_single_event($time,$hook,$args) {$GLOBALS['scheduled'][]=[$time,$hook,$args];return $GLOBALS['schedule_ok']??true;}
function is_wp_error($value) {return false;}
function wp_insert_post($post,$error=false) {
 $id=count($GLOBALS['posts'])+1;$meta=$post['meta_input']??[];unset($post['meta_input']);
 $GLOBALS['posts'][$id]=(object)(['ID'=>$id]+$post);$GLOBALS['meta'][$id]=$meta;return $id;
}
function get_posts($args) {return array_values(array_filter($GLOBALS['posts'],function($post)use($args){
 return $post->post_type===$args['post_type']&&$post->post_status===$args['post_status']
 && (!isset($args['meta_key'])||get_post_meta($post->ID,$args['meta_key'])===$args['meta_value']);
}));}
function current_user_can($capability) {return $GLOBALS['authorized']??false;}
function check_admin_referer($action) {throw new RuntimeException('nonce-required');}
function wp_die($message,...$args) {throw new RuntimeException($message);}
function absint($value) {return abs((int)$value);}
function wp_unslash($value) {return $value;}
function wp_slash($value) {return $value;}
function nocache_headers() {}
function esc_url($value) {return htmlspecialchars($value,ENT_QUOTES);}
function subscriber($email='test@example.test',$confirmed='2026-10-01 12:00:00',$unsubscribed='',$name='Test') {
 return wp_insert_post(['post_type'=>'bioco_subscriber','post_status'=>'publish','post_title'=>$email,'meta_input'=>[
 'subscriber_email'=>$email,'subscriber_name'=>$name,'confirmed_at'=>$confirmed,'unsubscribed_at'=>$unsubscribed]]);
}
require 'wordpress/web/app/mu-plugins/bioco-forms/newsletter-admin.php';
'''


def php(code):
    run = subprocess.run(['php', '-r', PREAMBLE + code], cwd=ROOT, text=True, capture_output=True)
    assert run.returncode == 0, run.stderr + run.stdout
    return json.loads(run.stdout)


def test_unsubscribe_validates_identity_and_is_idempotent():
    result = php(r'''
    $a=subscriber();$b=subscriber('other@example.test');$token=bioco_newsletter_token($a);
    $before=bioco_newsletter_active($a);
    $wrong=bioco_newsletter_unsubscribe($b,$token);
    $tampered=bioco_newsletter_unsubscribe($a,str_repeat('a',64));
    $valid=bioco_newsletter_unsubscribe($a,$token);$repeat=bioco_newsletter_unsubscribe($a,$token);
    echo json_encode([$before,$wrong,$tampered,$valid,$repeat,bioco_newsletter_active($a),bioco_newsletter_active($b)]);
    ''')
    assert result == [True, False, False, True, True, False, True]


def test_get_link_does_not_unsubscribe_and_post_confirms_without_pii():
    result = php(r'''
    $id=subscriber();$_GET=['bioco_unsubscribe'=>$id,'token'=>bioco_newsletter_token($id)];
    try {$GLOBALS['hooks']['template_redirect']();}catch(RuntimeException $e){$prompt=$e->getMessage();}
    $active=bioco_newsletter_active($id);
    $_SERVER['REQUEST_METHOD']='POST';$_POST=['List-Unsubscribe'=>'One-Click'];
    try {$GLOBALS['hooks']['template_redirect']();}catch(RuntimeException $e){$success=$e->getMessage();}
    echo json_encode([$prompt,$active,$success,bioco_newsletter_active($id)]);
    ''')
    assert 'method="post"' in result[0] and 'test@example.test' not in result[0]
    assert result[1] is True
    assert 'abgemeldet' in result[2] and result[3] is False


def test_new_confirmation_invalidates_old_unsubscribe_link():
    result = php(r'''
    $id=subscriber();$old=bioco_newsletter_token($id);
    update_post_meta($id,'_bioco_confirmation_id','new-confirmation-in-the-same-second');
    echo json_encode([bioco_newsletter_valid_link($id,$old),bioco_newsletter_valid_link($id,bioco_newsletter_token($id))]);
    ''')
    assert result == [False, True]


def test_actual_doi_resubscribe_reactivates_and_rotates_generation_in_same_second():
    result = php(r'''
    function sanitize_email($value){return $value;}
    function sanitize_text_field($value){return $value;}
    function bioco_forms_recipient(){return 'admin@example.test';}
    function bioco_forms_lines($lines){return implode("\n",$lines);}
    function bioco_forms_send_mail(...$args){return wp_mail(...$args);}
    class WP_Query {
      public $posts;
      function __construct($args){$this->posts=array_map(fn($post)=>$post->ID,get_posts(['post_type'=>'bioco_subscriber','post_status'=>'publish','meta_key'=>'subscriber_email','meta_value'=>$args['meta_query'][0]['value']]));}
      function have_posts(){return count($this->posts)>0;}
    }
    require 'wordpress/web/app/mu-plugins/bioco-forms/newsletter.php';
    $id=subscriber('test@example.test','2026-10-09 22:00:00');$old=bioco_newsletter_token($id);
    bioco_newsletter_unsubscribe($id,$old);
    bioco_forms_doi_on_confirm('subscribe',['email'=>'test@example.test','name'=>'Confirmed again']);
    echo json_encode([bioco_newsletter_active($id),bioco_newsletter_valid_link($id,$old),get_post_meta($id,'subscriber_name',true),count($GLOBALS['posts'])]);
    ''')
    assert result == [True, False, 'Confirmed again', 1]


def test_export_is_confirmed_only_and_neutralizes_csv_formulas():
    raw = php(r'''
    subscriber('valid@example.test','2026-10-01 12:00:00','',' =HYPERLINK("evil")');
    subscriber('pending@example.test','');subscriber('gone@example.test','2026-10-01','2026-10-02');
    $stream=fopen('php://memory','w+');bioco_newsletter_export($stream);rewind($stream);echo json_encode(stream_get_contents($stream));
    ''')
    rows = list(csv.reader(io.StringIO(raw)))
    assert rows[0] == ['email', 'name', 'confirmed_at']
    assert len(rows) == 2 and rows[1][0] == 'valid@example.test'
    assert rows[1][1].startswith("' =HYPERLINK")


def test_send_excludes_unconfirmed_and_unsubscribed_and_adds_unsubscribe_headers():
    result = php(r'''
    $a=subscriber();$b=subscriber('pending@example.test','');$c=subscriber('gone@example.test','2026-10-01','2026-10-02');
    $accepted=bioco_newsletter_send($a,'Subject','Body');
    $pending=bioco_newsletter_send($b,'Subject','Body');$gone=bioco_newsletter_send($c,'Subject','Body');
    $GLOBALS['mail_ok']=false;$failure=bioco_newsletter_send($a,'Subject','Body');
    echo json_encode([$accepted,$pending,$gone,$failure,$GLOBALS['mail']]);
    ''')
    assert result[:4] == [True, False, False, False]
    assert len(result[4]) == 2
    mail = result[4][0]
    assert mail[0] == 'test@example.test'
    assert 'Newsletter abbestellen: https://' in mail[2]
    assert any(h.startswith('List-Unsubscribe: <https://') for h in mail[3])
    assert 'List-Unsubscribe-Post: List-Unsubscribe=One-Click' in mail[3]


def test_campaign_rechecks_unsubscribe_and_never_replays_a_sent_message():
    result = php(r'''
    $a=subscriber();$b=subscriber('gone@example.test');$campaign=bioco_newsletter_queue('Subject','Body');
    bioco_newsletter_unsubscribe($b,bioco_newsletter_token($b));
    bioco_newsletter_batch($campaign);bioco_newsletter_batch($campaign);
    echo json_encode([$GLOBALS['mail'],$GLOBALS['meta'][$campaign],$GLOBALS['options']]);
    ''')
    assert len(result[0]) == 1
    assert result[1]['_bioco_delivery_1'] == 'sent'
    assert result[1]['_bioco_delivery_2'] == 'skipped'
    assert result[1]['_bioco_cursor'] == 2 and result[2] == []


def test_failed_and_uncertain_deliveries_are_not_automatically_retried():
    result = php(r'''
    $a=subscriber();$b=subscriber('uncertain@example.test');$campaign=bioco_newsletter_queue('Subject','Body');
    update_post_meta($campaign,'_bioco_delivery_'.$b,'sending');$GLOBALS['mail_ok']=false;
    bioco_newsletter_batch($campaign);bioco_newsletter_batch($campaign);
    echo json_encode([$GLOBALS['mail'],$GLOBALS['meta'][$campaign]]);
    ''')
    assert len(result[0]) == 1
    assert result[1]['_bioco_delivery_1'] == 'failed'
    assert result[1]['_bioco_delivery_2'] == 'sending'


def test_queue_batches_twenty_and_honors_worker_lock():
    result = php(r'''
    for($i=0;$i<21;$i++)subscriber('test'.$i.'@example.test');
    $campaign=bioco_newsletter_queue('Subject','Body');
    add_option('bioco_newsletter_lock_'.$campaign,time());bioco_newsletter_batch($campaign);$locked=count($GLOBALS['mail']);
    delete_option('bioco_newsletter_lock_'.$campaign);bioco_newsletter_batch($campaign);$first=count($GLOBALS['mail']);
    bioco_newsletter_batch($campaign);
    echo json_encode([$locked,$first,count($GLOBALS['mail']),count($GLOBALS['scheduled'])]);
    ''')
    assert result == [0, 20, 21, 2]


def test_send_and_export_require_admin_permission_and_nonce():
    result = php(r'''
    $errors=[];
    foreach(['admin_post_bioco_newsletter_send','admin_post_bioco_newsletter_export'] as $hook){
      foreach([false,true] as $authorized){$GLOBALS['authorized']=$authorized;
        try{$GLOBALS['hooks'][$hook]();}catch(RuntimeException $e){$errors[]=$e->getMessage();}
      }
    }
    echo json_encode([$errors,$GLOBALS['mail'],$GLOBALS['posts']]);
    ''')
    assert result == [['Keine Berechtigung.', 'nonce-required'] * 2, [], []]
