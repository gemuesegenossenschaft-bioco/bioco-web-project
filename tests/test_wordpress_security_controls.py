"""Narrow REST denial and login limiting at real hook callbacks."""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PREAMBLE = r'''
define('ABSPATH','/');define('MINUTE_IN_SECONDS',60);
$GLOBALS['hooks']=[];$GLOBALS['store']=[];
class WP_Error {public function __construct(public $code,public $message,public $data=[]) {}}
function add_filter($key,$callback,...$args) {$GLOBALS['hooks'][$key]=$callback;}
function add_action($key,$callback,...$args) {$GLOBALS['hooks'][$key]=$callback;}
function is_user_logged_in() {return $GLOBALS['logged_in']??false;}
function wp_salt($scheme) {return 'test-only';}
function get_transient($key) {return $GLOBALS['store'][$key]??false;}
function set_transient($key,$value,$ttl) {$GLOBALS['store'][$key]=$value;$GLOBALS['ttl']=$ttl;}
function delete_transient($key) {unset($GLOBALS['store'][$key]);}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/security.php';
'''


def php(code):
    run = subprocess.run(['php', '-r', PREAMBLE + code], cwd=ROOT, capture_output=True, text=True)
    assert run.returncode == 0, run.stderr
    return json.loads(run.stdout)


def test_only_anonymous_user_rest_routes_are_restricted():
    results = php(r'''
    $results=[];
    foreach([false,true] as $logged){$GLOBALS['logged_in']=$logged;
      foreach(['/wp/v2/users','/wp/v2/users/1','/wp/v2/users/me','/wp/v2/pages','/bioco/v1/contact'] as $route){
        $request=new class($route){public function __construct(private $route){} public function get_route(){return $this->route;}};
        $result=bioco_security_private_users('unchanged',null,$request);
        $results[]=is_object($result)?$result->data['status']:$result;
      }
    }
    echo json_encode($results);
    ''')
    assert results == [401, 401, 401, 'unchanged', 'unchanged'] + ['unchanged'] * 5


def test_login_limit_is_peer_scoped_and_success_clears_it():
    result = php(r'''
    $_SERVER['REMOTE_ADDR']='192.0.2.1';$_SERVER['HTTP_X_FORWARDED_FOR']='attacker-controlled';
    for($i=0;$i<10;$i++)$GLOBALS['hooks']['wp_login_failed']();
    $limited=bioco_security_limit_login('user','name','password');
    $_SERVER['HTTP_X_FORWARDED_FOR']='changed';$same=bioco_security_limit_login('user','name','password');
    $_SERVER['REMOTE_ADDR']='192.0.2.2';$other=bioco_security_limit_login('user','name','password');
    $_SERVER['REMOTE_ADDR']='192.0.2.1';$GLOBALS['hooks']['wp_login']();
    echo json_encode([$limited->code,$same->code,$other,bioco_security_limit_login('user','name','password'),$GLOBALS['ttl']]);
    ''')
    assert result[:4] == ['bioco_login_limited', 'bioco_login_limited', 'user', 'user']
    assert 1 <= result[4] <= 900
