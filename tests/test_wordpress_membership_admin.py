"""Real private review callback; WP plumbing stubbed. See tests/README.md."""
import json
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]


@pytest.mark.parametrize('allowed', [False, True])
def test_admin_access_guard_and_escaped_records(allowed):
    php = r'''
    error_reporting(E_ALL);
    set_error_handler(function($severity,$message) {throw new ErrorException($message, 0, $severity);});
    define('ABSPATH', __DIR__);
    $allowed = $argv[1] === 'true'; $hooks=[]; $queries=0; $menus=[];
    function add_action($name,$callback) {$GLOBALS['hooks'][$name]=$callback;}
    function add_management_page(...$args) {$GLOBALS['menus'][]=$args;}
    function current_user_can($cap) {if($cap!=='manage_options')throw new Exception('wrong cap');return $GLOBALS['allowed'];}
    function wp_die(...$args) {echo 'DENIED';}
    function esc_html($s) {return htmlspecialchars($s, ENT_QUOTES);}
    function esc_url($s) {return htmlspecialchars($s, ENT_QUOTES);}
    function admin_url($s) {return '/wp-admin/'.$s;}
    function wp_json_encode(...$args) {return json_encode(...$args);}
    function maybe_unserialize($s) {return unserialize($s);}
    class DB {
        public $options='prefix_options';
        function prepare($sql,...$args) {return [$sql,$args];}
        function get_results($query) {
            $GLOBALS['queries']++;
            if($query[1]!==['^bioco_membership_[a-f0-9]{64}$',0])throw new Exception('bad pagination');
            return [(object)['option_value'=>serialize(['adapter'=>'local','receipt'=>'<b>local-receipt</b>','status'=>'<i>accepted</i>','notification'=>'<em>failed</em>','data'=>['comment'=>'<script>secret</script>']])], (object)['option_value'=>serialize(['adapter'=>'local','data'=>['comment'=>'<script>incomplete</script>']])]];
        }
    }
    $wpdb=new DB;
    require 'wordpress/web/app/mu-plugins/bioco-forms/membership.php';
    $hooks['admin_menu']();
    ob_start();bioco_forms_membership_admin();$html=ob_get_clean();
    echo json_encode(['html'=>$html,'queries'=>$queries,'cap'=>$menus[0][2],'hooks'=>array_keys($hooks)]);
    '''
    result = subprocess.run(['php', '-r', php, json.dumps(allowed)], cwd=ROOT, check=True, capture_output=True, text=True)
    assert result.stderr == ''
    data = json.loads(result.stdout)
    assert data['cap'] == 'manage_options'
    assert data['hooks'] == ['admin_menu']
    if allowed:
        assert data['queries'] == 1
        assert 'failed' in data['html']
        assert '<script>' not in data['html']
        assert '&lt;script&gt;' in data['html']
        assert r'&lt;script&gt;incomplete&lt;\/script&gt;' in data['html']
        assert '<summary> |  |  | Mail: </summary>' in data['html']
        assert '<summary> | &lt;b&gt;local-receipt&lt;/b&gt; | &lt;i&gt;accepted&lt;/i&gt; | Mail: &lt;em&gt;failed&lt;/em&gt;</summary>' in data['html']
    else:
        assert data['queries'] == 0
        assert data['html'] == 'DENIED'
