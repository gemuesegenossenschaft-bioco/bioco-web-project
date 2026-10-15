"""Native group/calendar record bindings and saved layouts.

The WordPress parser and browser controller are real. Storage, native render and
query execution remain transport boundaries; UI save/reopen is a release gate.
Keep/Replace/Remove evidence map: tests/README.md.
"""
import json
import subprocess
from pathlib import Path

import pytest
from playwright.sync_api import sync_playwright

from test_wordpress_event_templates import HARNESS as EVENT_HARNESS
from test_wordpress_utility_layouts import php as library_php

ROOT = Path(__file__).parents[1]
LOAD = "function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}require 'wordpress/web/app/mu-plugins/bioco-import/includes/record-layouts.php';"
BASE = EVENT_HARNESS.split("require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';")[0]
BASE = BASE.replace("function get_post($id) { return (object) ['ID'=>$id,'post_content'=>$GLOBALS['records'][$id]['body'] ?? '']; }", "function get_post($id) { return isset($GLOBALS['records'][$id]) ? (object) ['ID'=>$id,'post_content'=>$GLOBALS['records'][$id]['body'] ?? '', 'post_type'=>get_post_type($id),'post_status'=>get_post_status($id)] : null; }")
BASE += r'''
function add_action(...$args) {}
function get_post_stati(){return ['publish','draft','private','trash'];}
function get_posts($args){return array_map(fn($p)=>(object)$p,array_values(array_filter($GLOBALS['state']['layouts']??[],fn($p)=>$p['post_type']===$args['post_type']&&$p['post_name']===$args['name'])));}
function is_singular($type){return ($GLOBALS['state']['request']??true)&&get_post_type(get_queried_object_id())===$type;}
function wp_doing_ajax(){return false;}function is_admin(){return $GLOBALS['state']['inert']??false;}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-modules.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/record-bindings.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/record-layout-runtime.php';
'''


def run(code, records=None, **state):
    result = subprocess.run(['php', '-r', BASE + code], input=json.dumps(dict(records=records or {}, **state)), text=True, capture_output=True, cwd=ROOT)
    assert result.returncode == 0 and not result.stderr, result.stderr
    return json.loads(result.stdout)


def group(**values):
    return dict(type='bioco_group', status='publish', title='Gruppe & Titel', body='', group_text='<p>Beschreibung <strong>der Gruppe</strong></p>', group_contact='Kontaktperson', group_image={'url': 'https://example.test/group.jpg', 'alt': 'Gruppenbild'}, meta={}) | values


def resolve(field, records=None, prefix='bioco_group_', **state):
    context = state.pop('context', dict(post_id=1, name=prefix + field))
    return run("echo json_encode(['value'=>bioco_record_binding_value('original',$state['context']),'options'=>bioco_record_binding_fields(['keep'=>['id'=>'keep']])]);", records=records or {'1': group()}, context=context, **state)


def test_group_binding_preserves_record_fields_and_unresolved_contacts():
    for field, expected in [('title', 'Gruppe &amp; Titel'), ('description', '<p>Beschreibung <strong>der Gruppe</strong></p>'), ('contact', 'Kontaktperson'), ('contact_url', ''), ('image', 'https://example.test/group.jpg'), ('image_alt', 'Gruppenbild')]:
        assert resolve(field)['value'] == expected
    assert resolve('description', records={'1': group(group_text='<script>bad</script><p>Good</p>')})['value'] == '<p>Good</p>'
    assert resolve('contact_url', records={'1': group(meta={'group_contact_url': 'https://example.test/contact/'})})['value'] == 'https://example.test/contact/'
    assert resolve('contact_url', records={'1': group(meta={'group_contact_url': 'javascript:alert(1)'})})['value'] == ''
    assert resolve('image_alt', records={'1': group(group_image={'url': 'https://example.test/group.jpg', 'alt': ''})})['value'] == 'Gruppe &amp; Titel'
    assert 'keep' in resolve('title')['options']


def test_loop_record_wins_and_missing_loop_draft_and_password_do_not_leak():
    records = {'1': group(), '2': group(title='Andere Gruppe')}
    assert resolve('title', records=records, context=dict(name='loop_bioco_group_title', post_id=99, loop_id=2), queried=1)['value'] == 'Andere Gruppe'
    assert resolve('title', records=records, context=dict(name='loop_bioco_group_title', post_id=99), queried=1)['value'] == ''
    assert resolve('title', records={'1': group(status='draft')})['value'] == ''
    assert resolve('title', records={'1': group(status='draft')}, allowed=[1])['value'] == 'Gruppe &amp; Titel'
    assert resolve('description', records={'1': group(protected=True)})['value'] == ''
    assert resolve('contact', records={'1': group(protected=True)})['value'] == ''
    assert resolve('url', records={'1': group(protected=True)})['value'] == 'https://example.test/events/1/'


def test_draft_layout_preview_requires_mutual_marker_and_both_edit_permissions():
    records = {'2': group(status='draft', meta={'_bioco_editor_verification': 'qa-group-test', '_bioco_editor_verification_body': 99}),
               '99': dict(type='et_pb_layout', status='draft', meta={'_bioco_editor_verification': 'qa-group-test', '_bioco_editor_verification_group': 2})}
    context = dict(post_id=99, loop_id=0, name='bioco_group_title')
    assert resolve('title', records=records, context=context, allowed=[2, 99])['value'] == 'Gruppe &amp; Titel'
    assert resolve('title', records=records, context=context, allowed=[99])['value'] == ''
    records['2']['meta']['_bioco_editor_verification_body'] = 98
    assert resolve('title', records=records, context=context, allowed=[2, 99])['value'] == ''


def test_vegetable_bindings_use_the_record_months_and_never_local_module_rows():
    records = {'1': dict(type='bioco_vegetable', status='publish', title='Testgemüse', meta={'vegetable_months': ['1', 1, 12, 99]})}
    assert resolve('name', records=records, prefix='loop_bioco_vegetable_')['value'] == 'Testgemüse'
    for month in range(1, 13):
        assert resolve('month_' + str(month), records=records, prefix='loop_bioco_vegetable_')['value'] == ('●' if month in [1, 12] else '')
    assert resolve('month_13', records=records, prefix='loop_bioco_vegetable_')['value'] == 'original'


@pytest.mark.parametrize('change', ['body', 'draft-layout', 'duplicate', 'protected', 'other-request'])
def test_group_route_reads_saved_native_detail_only_for_blank_readable_group_bodies(change):
    record = group()
    layout = dict(ID=10, post_type='et_pb_layout', post_name='bioco-record-group-detail', post_status='publish', post_content='SAVED_NATIVE_GROUP_LAYOUT')
    if change == 'body': record['body'] = '<p>Eigener Inhalt</p>'
    if change == 'draft-layout': layout['post_status'] = 'draft'
    if change == 'protected': record['protected'] = True
    source = '<p>Eigener Inhalt</p>' if change == 'body' else ''
    out = run("echo json_encode(bioco_group_record_content($state['content']));", records={'1': record}, queried=1, layouts=[layout] * (2 if change == 'duplicate' else 1), content=source, request=change != 'other-request')
    assert out == source
    out = run("echo json_encode(bioco_group_record_content(''));", records={'1': group()}, queried=1, layouts=[layout | {'post_status': 'publish'}])
    assert out == 'SAVED_NATIVE_GROUP_LAYOUT'


def test_native_query_keeps_editor_order_and_limit_and_private_preview_is_explicit():
    code = r'''
$data=['query_args'=>['orderby'=>'title','order'=>'DESC','posts_per_page'=>2,'post__in'=>[1,2]],'query_type'=>'post_types'];
$attrs=['module'=>['advanced'=>['htmlAttributes'=>['desktop'=>['value'=>['class'=>'bioco-group-loop']]]]]];
$decorated=['module'=>['advanced'=>['htmlAttributes'=>['desktop'=>['value'=>['class'=>'']]]],
    'decoration'=>['attributes'=>['desktop'=>['value'=>['attributes'=>[['name'=>'class','value'=>'bioco-record-loop bioco-group-loop']]]]]]]];
echo json_encode(['scoped'=>bioco_record_native_query($data,$attrs),
    'decorated'=>bioco_record_native_query($data,$decorated),
    'ordinary'=>bioco_record_native_query($data,[])]);
'''
    records = {'1': group(), '2': group(status='draft')}
    public = run(code, records=records)
    assert public['scoped']['query_args']['post_status'] == 'publish'
    assert public['scoped']['query_args']['order'] == 'DESC' and public['scoped']['query_args']['posts_per_page'] == 2
    assert public['decorated'] == public['scoped']
    private = run(code, records=records, inert=True, allowed=[2])
    assert private['scoped']['query_args']['post__in'] == [2]
    assert private['scoped']['query_args']['post_status'] == ['publish', 'draft', 'private']
    assert private['decorated'] == private['scoped']
    assert 'post_status' not in public['ordinary']['query_args']


def walk(blocks):
    for block in blocks:
        yield block
        yield from walk(block['innerBlocks'])


def definitions():
    return library_php(LOAD + "echo json_encode(array_map(fn($v)=>parse_blocks($v['content']),bioco_record_layout_definitions()));")


def test_record_layout_import_preserves_authored_drafts_and_has_no_record_or_assignment_writes():
    out = library_php(LOAD + r'''
$dry=bioco_import_record_layouts();$before=$writes;$first=bioco_import_record_layouts(true);
$posts[1]->post_content.='EDITOR_CHANGE';$posts[2]->post_status='draft';$writes=[];$again=bioco_import_record_layouts(true);
echo json_encode(compact('dry','before','first','again','writes','posts'));
''')
    assert out['before'] == out['writes'] == []
    assert len(out['first']) == 4 and all(row['action'] == 'created' for row in out['first'])
    assert all(row['action'] == 'preserve-existing' for row in out['again'])
    assert {p['post_type'] for p in out['posts'].values()} == {'et_pb_layout'}
    assert out['posts']['1']['post_content'].endswith('EDITOR_CHANGE') and out['posts']['2']['post_status'] == 'draft'


def test_native_calendar_payload_has_headings_roles_bound_columns_and_contained_overflow():
    layouts = definitions()
    nodes = list(walk(layouts['calendar']))
    assert {n['blockName'] for n in nodes} <= {'divi/section', 'divi/row', 'divi/column', 'divi/text', 'divi/heading'}
    section = layouts['calendar'][1]
    assert section['attrs']['module']['decoration']['overflow']['desktop']['value']['x'] == 'auto'
    rows = section['innerBlocks']
    for row in rows:
        assert len(row['innerBlocks']) == 13
        assert row['attrs']['module']['decoration']['sizing']['desktop']['value']['minWidth'] == '1000px'
    source = json.dumps(layouts['calendar'])
    assert 'bioco_vegetable' in source
    for month in range(1, 13): assert 'loop_bioco_vegetable_month_' + str(month) in source
    assert not any('bioco-divi/' in n['blockName'] for n in nodes)


def test_collapsible_calendar_keeps_native_editable_cells_and_authoritative_bindings():
    layouts = definitions()
    disclosure = layouts['calendar-collapsible']
    nodes = list(walk(disclosure))
    toggle, = [n for n in nodes if n['blockName'] == 'divi/toggle']
    assert toggle['attrs']['module']['advanced']['open']['desktop']['value'] == 'off'
    assert toggle['attrs']['title']['innerContent']['desktop']['value'] == 'Saisonkalender'
    assert toggle['attrs']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] == 'h2'
    assert 'bioco-editorial-toggle' in json.dumps(toggle['attrs'])
    nested = list(walk(toggle['innerBlocks']))
    assert {n['blockName'] for n in nested} <= {'divi/group', 'divi/text'}
    table = toggle['innerBlocks'][1]
    assert table['attrs']['module']['decoration']['overflow']['desktop']['value']['x'] == 'auto'
    old_head, old_body = layouts['calendar'][1]['innerBlocks']
    head, body = table['innerBlocks']
    for old, new in [(old_head, head), (old_body, body)]:
        assert len(new['innerBlocks']) == 13
        assert new['attrs']['module']['decoration'] == old['attrs']['module']['decoration']
        assert [n['innerBlocks'] for n in new['innerBlocks']] == [n['innerBlocks'] for n in old['innerBlocks']]
    loop = body['attrs']['module']['advanced']['loop']['desktop']['value']
    assert loop['subTypes'] == [{'value': 'bioco_vegetable', 'label': 'Gemüse'}]
    assert loop['postPerPage'] == 100 and loop['orderBy'] == 'none' and loop['order'] == 'ascending'
    assert 'bioco-calendar-catalog-order' in json.dumps(body['attrs'])
    assert all('modulePreset' not in n['attrs'] for n in nested if n['blockName'] == 'divi/group')


def test_collapsible_catalog_order_uses_record_order_but_honors_explicit_editor_sort():
    code = r'''
$attrs=['module'=>['advanced'=>['htmlAttributes'=>['desktop'=>['value'=>[
    'class'=>'bioco-vegetable-loop bioco-calendar-catalog-order']]]]]];
$base=['query_type'=>'post_types','query_args'=>['orderby'=>'none','order'=>'ASC','posts_per_page'=>100]];
$explicit=$base;$explicit['query_args']['orderby']='title';$explicit['query_args']['order']='DESC';
echo json_encode(['catalog'=>bioco_record_native_query($base,$attrs),
    'explicit'=>bioco_record_native_query($explicit,$attrs),
    'unscoped'=>bioco_record_native_query($base,[])]);
'''
    out = run(code)
    assert out['catalog']['query_args']['orderby'] == {'menu_order': 'ASC', 'title': 'ASC'}
    assert out['catalog']['query_args']['post_status'] == 'publish'
    assert out['catalog']['query_args']['posts_per_page'] == 100
    assert out['explicit']['query_args']['orderby'] == 'title'
    assert out['explicit']['query_args']['order'] == 'DESC'
    assert out['unscoped']['query_args']['orderby'] == 'none'


def test_calendar_header_order_removal_duplicate_instances_and_accessible_references():
    def calendar(label):
        return '<div class="bioco-native-calendar" style="width:320px;overflow-x:auto"><div style="min-width:1000px"><div class="bioco-calendar-head"><div class="bioco-calendar-name">Gemüse</div><div class="bioco-calendar-month-12">' + label + '</div><div class="bioco-calendar-month-1">Jan</div></div><div class="bioco-calendar-data"><div class="bioco-calendar-name">Testgemüse</div><div class="bioco-calendar-month-1">●</div><div class="bioco-calendar-month-2" style="display:flex">●</div><div class="bioco-calendar-month-12">●</div></div></div></div>'
    with sync_playwright() as pw:
        browser = pw.chromium.launch();page = browser.new_page(viewport={'width': 375, 'height': 800})
        page.set_content(calendar('Dezember') + calendar('Dec'))
        page.add_script_tag(path=str(ROOT / 'wordpress/web/app/mu-plugins/bioco-core/assets/native-calendar.js'))
        for index, label in enumerate(['Dezember', 'Dec']):
            root = page.locator('.bioco-native-calendar').nth(index)
            assert root.get_attribute('role') == 'table'
            assert root.locator('.bioco-calendar-head').get_attribute('role') == 'row'
            assert root.locator('.bioco-calendar-head .bioco-calendar-name').get_attribute('role') == 'columnheader'
            assert root.locator('.bioco-calendar-data').get_attribute('role') == 'row'
            assert root.locator('.bioco-calendar-data .bioco-calendar-name').get_attribute('role') == 'rowheader'
            visible = root.locator('.bioco-calendar-data > div:visible')
            assert visible.all_text_contents() == ['Testgemüse', '●', '●']
            header = root.locator('.bioco-calendar-head .bioco-calendar-month-12')
            cell = root.locator('.bioco-calendar-data .bioco-calendar-month-12')
            assert cell.get_attribute('aria-describedby') == header.get_attribute('id')
            assert header.inner_text() == label
            assert root.locator('.bioco-calendar-data .bioco-calendar-month-2').is_hidden()
            assert root.evaluate('(el)=>el.scrollWidth>el.clientWidth')
        assert page.locator('.bioco-calendar-head .bioco-calendar-month-12').nth(0).get_attribute('id') != page.locator('.bioco-calendar-head .bioco-calendar-month-12').nth(1).get_attribute('id')
        page.add_script_tag(path=str(ROOT / 'wordpress/web/app/mu-plugins/bioco-core/assets/native-calendar.js'))
        assert page.locator('.bioco-native-calendar').count() == 2
        browser.close()


def test_calendar_editor_query_is_scoped_and_preserves_native_filters_and_limits():
    code = r'''
function remove_filter($name,$callback,$priority){unset($GLOBALS['hooks'][$name]);}
class CalendarRequest {
    function __construct(public $route,public $method,public $params){}
    function get_route(){return $this->route;}
    function get_method(){return $this->method;}
    function get_params(){return $this->params;}
}
$params=['query_type'=>'post_type','post_type'=>'bioco_vegetable','post_id'=>9,'order_by'=>'none'];
$args=['post_type'=>'any','posts_per_page'=>7,'offset'=>2,'tax_query'=>[['taxonomy'=>'keep']], 'post__not_in'=>[3],'orderby'=>'none','order'=>'ASC'];
$queries=[];
foreach ([9,'9',0,-1,'9evil','9.0',true,[],99] as $id) {
    $params['post_id']=$id;$queries[]=bioco_calendar_editor_query($args,$params);
}
$params['post_id']=9;
$explicit=bioco_calendar_editor_query($args+[],array_replace($params,['order_by'=>'title']));
$routes=[];
foreach ([['/else','GET'],['/divi/v1/loop/query-results','POST'],['/divi/v1/loop/query-results','GET']] as [$route,$method]) {
    $r=new CalendarRequest($route,$method,$params);
    bioco_calendar_editor_query_begin('unchanged',[], $r);
    $routes[]=isset($GLOBALS['hooks']['divi_module_options_loop_post_type_results_query_args']);
    bioco_calendar_editor_query_end('unchanged',[], $r);
}
$other=[];
foreach ([['post_type'=>'post'],['query_type'=>'terms'],['post_id'=>8]] as $changes) {
    $other[]=bioco_calendar_editor_query($args,array_replace($params,$changes));
}
echo json_encode(compact('args','queries','explicit','routes','other')+['clean'=>!isset($GLOBALS['hooks']['divi_module_options_loop_post_type_results_query_args'])]);
'''
    out = run(code, records={'9': group(), '8': group()}, allowed=[9])
    for query in out['queries'][:2]:
        assert query == out['args'] | dict(post_type='bioco_vegetable', post_status='publish', orderby={'menu_order': 'ASC', 'title': 'ASC'})
    assert out['queries'][2:] == [out['args']] * 7
    assert out['other'] == [out['args']] * 3
    assert out['explicit']['orderby'] == 'none'  # Native args already own explicit sorting.
    assert out['routes'] == [False, False, True] and out['clean']
