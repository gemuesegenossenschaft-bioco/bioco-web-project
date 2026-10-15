"""The editor loop response must carry the same fields as frontend rendering."""
import json
import subprocess

from test_wordpress_record_layouts import BASE, ROOT, group


def response(records=None, **state):
    source = BASE + r'''
function metadata_exists($type,$id,$key){return array_key_exists($key,$GLOBALS['records'][$id]['meta']??[]);}
class WP_REST_Response {
    function __construct(public $data, public $status=200) {}
    function get_data(){return $this->data;}
    function set_data($data){$this->data=$data;}
    function get_status(){return $this->status;}
}
class Request {
    function get_route(){global $state;return $state['route']??'/divi/v1/loop/query-results';}
    function get_param($name){global $state;return $name==='post_id'?99:null;}
}
$response=new WP_REST_Response(['items'=>$state['items']??[['id'=>1,'title'=>'Existing title']]],$state['status']??200);
if (isset($GLOBALS['hooks']['rest_post_dispatch'])) {
    [$callback,$args]=$GLOBALS['hooks']['rest_post_dispatch'];
    if ($args!==3) throw new Exception('REST hook must accept three arguments');
    $response=$callback($response,null,new Request());
}
echo json_encode($response->get_data());
'''
    result = subprocess.run(['php', '-r', source], cwd=ROOT, text=True, capture_output=True,
                            input=json.dumps(dict(records=records or {'1': group()}, **state)))
    assert result.returncode == 0 and not result.stderr, result.stderr
    return json.loads(result.stdout)


def test_editor_loop_fields_use_the_frontend_binding_values():
    item = response(allowed=[99])['items'][0]
    assert item['title'] == 'Existing title'
    assert item['bioco_group_title'] == 'Gruppe &amp; Titel'
    assert item['bioco_group_description'] == '<p>Beschreibung <strong>der Gruppe</strong></p>'
    assert item['bioco_group_image'] == 'https://example.test/group.jpg'
    assert item['bioco_group_contact_url'] == ''


def test_other_routes_errors_and_users_without_page_permission_are_unchanged():
    expected = {'items': [{'id': 1, 'title': 'Existing title'}]}
    assert response(allowed=[]) == expected
    assert response(allowed=[99], route='/wp/v2/posts') == expected
    assert response(allowed=[99], status=400) == expected


def test_unreadable_drafts_and_password_protected_content_do_not_leak():
    assert 'bioco_group_description' not in response(records={'1': group(status='draft')}, allowed=[99])['items'][0]
    assert response(records={'1': group(protected=True)}, allowed=[99])['items'][0]['bioco_group_description'] == ''


def test_calendar_months_and_nonpublic_depot_use_existing_binding_rules():
    vegetable = dict(type='bioco_vegetable', status='publish', title='Rüebli', meta={'vegetable_months': [1, 12]})
    item = response(records={'1': vegetable}, allowed=[99])['items'][0]
    assert item['bioco_vegetable_name'] == 'Rüebli'
    assert item['bioco_vegetable_month_1'] == '●'
    assert item['bioco_vegetable_month_2'] == ''
    depot = dict(type='bioco_depot', status='publish', title='Private depot', meta={'depot_public': False, 'depot_public_address': 'Hidden address'})
    item = response(records={'1': depot}, allowed=[99])['items'][0]
    assert item['bioco_depot_name'] == item['bioco_depot_address'] == ''


def test_unrelated_items_and_malformed_items_are_preserved():
    items = [{'id': 1, 'title': 'Unrelated'}, {'no_id': True}, 'invalid']
    assert response(records={'1': dict(type='post', status='publish')}, allowed=[99], items=items)['items'] == items


def test_missing_and_nonpositive_ids_cannot_resolve_a_record():
    items = [{'title': 'Missing ID'}, {'id': 0}, {'id': -1}]
    assert response(records={'0': group(), '-1': group()}, allowed=[99], items=items)['items'] == items
