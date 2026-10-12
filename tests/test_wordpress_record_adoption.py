"""Native group/calendar adoption with the WordPress block parser and guarded SQL."""
import copy
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_back_button_adoption import database, execute
from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
PHP = r'''
define('ABSPATH','/');
$state=json_decode(stream_get_contents(STDIN),true);
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function home_url($path){return $GLOBALS['state']['site'].ltrim($path,'/');}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
class Database { public $posts='posts'; function prepare($sql,...$args){foreach($args as $arg)$sql=preg_replace_callback('/%s/',fn()=>"'".str_replace("'","''",(string)$arg)."'",$sql,1);return str_replace('%','{wpdb-placeholder-test}',$sql);} function remove_placeholder_escape($sql){return str_replace('{wpdb-placeholder-test}','%',$sql);} }
$wpdb=new Database();
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
function parse_blocks($v){return (new WP_Block_Parser())->parse($v);}
WP_SERIALIZER
require 'wordpress/web/app/mu-plugins/bioco-import/includes/record-layout-adoption.php';
try {
 if(($state['mode']??'')==='sql')echo json_encode(['sql'=>bioco_record_adoption_sql($state['plan'],$state['expected'],$state['restore']??false)]);
 else {$record=$state['record'];if(isset($state['blocks']))$record['post_content']=serialize_blocks($state['blocks']);$plan=bioco_record_adoption_plan($record,$state['snapshot_site']??$state['site'],$state['kind']);echo json_encode(['plan'=>$plan,'blocks'=>parse_blocks($plan['after_content']),'before'=>parse_blocks($record['post_content']),'apply'=>bioco_record_adoption_sql($plan,$plan['fingerprint']),'restore'=>bioco_record_adoption_sql($plan,$plan['fingerprint'],true)]);}
} catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]);}
'''


def block(name, attrs=None, children=None):
    children = children or []
    return {'blockName': name, 'attrs': attrs or {}, 'innerBlocks': children,
            'innerHTML': '', 'innerContent': [None] * len(children) if children else ['\n']}


def fixture(kind='group', values=None):
    values = values or {}
    attrs = {'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {'id': 'old-leaf-id', 'class': 'old-leaf-class'}}}},
                        'decoration': {'background': {'desktop': {'value': {'color': '#123456'}}}}},
             'builderVersion': '5.0.0'}
    for key, value in values.items():
        attrs[key] = {'innerContent': {'desktop': {'value': value}}}
    leaf = block('bioco-divi/group-cards' if kind == 'group' else 'bioco-divi/saisonkalender', attrs)
    column = block('divi/column', {'module': {'meta': {'adminLabel': {'desktop': {'value': 'Original column'}}}}}, [leaf])
    row = block('divi/row', {'module': {'meta': {'adminLabel': {'desktop': {'value': 'Original row'}}}}}, [column])
    section = block('divi/section', {'module': {'decoration': {'layout': {'desktop': {'value': {'display': 'block'}}},
                'spacing': {'phone': {'value': {'padding': {'top': '17px'}}}}}}}, [row])
    sibling = block('divi/section', {'module': {'meta': {'adminLabel': {'desktop': {'value': 'Keep sibling'}}}}},
                    [block('divi/row', {}, [block('divi/column', {}, [block('divi/text', {'content': {'innerContent': {'desktop': {'value': '<p>Keep me</p>'}}}})])])])
    return [sibling, section]


def run(kind='group', blocks=None, record=None, **state):
    record = record or {'ID': 16 if kind == 'group' else 12, 'post_type': 'page', 'post_status': 'publish',
                        'post_title': 'Ä Original', 'post_name': 'mitmachen' if kind == 'group' else 'gemuese',
                        'post_content': '', 'post_modified_gmt': '2026-10-12 16:00:00'}
    payload = {'kind': kind, 'site': 'https://example.test/', 'record': record, **state}
    if blocks is not None: payload['blocks'] = blocks
    result = subprocess.run(['php', '-r', PHP.replace('WP_SERIALIZER', WP_SERIALIZER_STUB)], cwd=ROOT,
                            input=json.dumps(payload), text=True, capture_output=True)
    assert result.returncode == 0 and not result.stderr, result.stderr
    return json.loads(result.stdout)


def walk(nodes):
    for node in nodes:
        yield node
        yield from walk(node['innerBlocks'])


def value(node):
    return node['attrs'].get('content', {}).get('innerContent', {}).get('desktop', {}).get('value')


def test_group_conversion_preserves_saved_empty_copy_limit_design_and_siblings():
    blocks = fixture(values={'limit': 2, 'empty_message': 'Keine Gruppen & 100%'})
    blocks[1]['attrs']['module']['decoration']['attributes'] = {'desktop': {'value': {'attributes': [
        {'name': 'class', 'value': 'original-section', 'id': 'old-style'},
    ]}}}
    result = run(blocks=blocks)
    assert 'error' not in result, result
    assert result['plan']['report'] == [{'path': [1], 'action': 'convert', 'kind': 'group', 'archived_vegetable_rows': 0}]
    assert result['blocks'][0] == result['before'][0]
    section = result['blocks'][1]
    assert section['attrs']['module']['decoration']['spacing']['phone']['value']['padding']['top'] == '17px'
    assert section['attrs']['module']['decoration']['background']['desktop']['value']['color'] == '#123456'
    assert section['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] == 'old-leaf-id'
    assert 'original-section old-leaf-class bioco-record-groups' in json.dumps(section['attrs'])
    row = section['innerBlocks'][0]
    assert row['attrs']['module']['meta']['adminLabel']['desktop']['value'] == 'Original row'
    assert row['innerBlocks'][0]['attrs']['module']['meta']['adminLabel']['desktop']['value'] == 'Original column'
    assert row['attrs']['module']['advanced']['loop']['desktop']['value']['postPerPage'] == 2
    assert any(value(n) == '<p>Keine Gruppen &amp; 100%</p>' for n in walk([section]))
    assert 'bioco-record-loop' in json.dumps(row)
    assert all(n['blockName'].startswith('divi/') for n in walk(result['blocks']))


def test_calendar_uses_saved_month_order_and_labels_and_archives_stale_rows():
    vals = {'vegetables': [{'name': 'old row', 'months': [1]}], 'empty_message': 'Nichts da',
            'vegetable_column_label': 'Sorte', 'month_columns': [{'number': 12, 'label': 'Dezember'}, {'number': 1, 'label': 'Januar'}]}
    result = run('calendar', fixture('calendar', vals))
    assert 'error' not in result, result
    assert result['plan']['report'][0]['archived_vegetable_rows'] == 1
    assert result['blocks'][0] == result['before'][0]
    section = result['blocks'][1]
    assert 'bioco-native-calendar' in json.dumps(section['attrs'])
    head, body = section['innerBlocks']
    assert len(head['innerBlocks']) == len(body['innerBlocks']) == 3
    assert head['attrs']['module']['advanced']['columnStructure']['desktop']['value'] == '4_4,4_4,4_4'
    assert 'bioco-calendar-name' in json.dumps(head['innerBlocks'][0]['attrs'])
    assert [value(n['innerBlocks'][0]) for n in head['innerBlocks']] == ['Sorte', 'Dezember', 'Januar']
    assert 'month_12' in json.dumps(body['innerBlocks'][1]) and 'month_1' in json.dumps(body['innerBlocks'][2])
    assert 'month_2' not in json.dumps(body)
    assert any(value(n) == '<p>Nichts da</p>' for n in walk([body]))
    assert section['attrs']['module']['decoration']['overflow']['desktop']['value']['x'] == 'auto'
    assert section['attrs']['module']['decoration']['spacing']['phone']['value']['padding']['top'] == '17px'
    assert 'role' in json.dumps(section['attrs'])


@pytest.mark.parametrize('change', ['extra-field', 'responsive', 'unknown-control', 'authored-markup', 'shared-column', 'custom-layout', 'duplicate-month', 'anchor-conflict', 'calendar-row-design'])
def test_unsupported_or_ambiguous_source_refuses_whole_plan(change):
    kind = 'calendar' if change in ['duplicate-month', 'calendar-row-design'] else 'group'
    blocks = fixture(kind)
    section = blocks[1]
    leaf = section['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    if change == 'extra-field': leaf['attrs']['new_field'] = 1
    if change == 'responsive': leaf['attrs']['empty_message'] = {'innerContent': {'desktop': {'value': 'A'}, 'phone': {'value': 'B'}}}
    if change == 'unknown-control': leaf['attrs']['module']['advanced']['future_control'] = {'desktop': {'value': 'x'}}
    if change == 'authored-markup': leaf['innerContent'] = ['<p>Own markup</p>']; leaf['innerHTML'] = '<p>Own markup</p>'
    if change == 'shared-column': section['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'].append(block('divi/text')); section['innerBlocks'][0]['innerBlocks'][0]['innerContent'] = [None, None]
    if change == 'custom-layout': section['attrs']['module']['decoration']['layout']['desktop']['value']['display'] = 'flex'
    if change == 'duplicate-month': leaf['attrs']['month_columns'] = {'innerContent': {'desktop': {'value': [{'number': 1, 'label': 'Jan'}, {'number': 1, 'label': 'Again'}]}}}
    if change == 'anchor-conflict': section['attrs']['module']['decoration']['attributes'] = {'desktop': {'value': {'attributes': [{'name': 'id', 'value': 'section-id'}]}}}
    if change == 'calendar-row-design': section['innerBlocks'][0]['attrs']['module'].setdefault('decoration', {})['spacing'] = {'phone': {'value': {'padding': {'top': '20px'}}}}
    assert 'error' in run(kind, blocks)


def test_cas_apply_restore_and_conflicts_preserve_other_columns():
    result = run(blocks=fixture(values={'empty_message': '100% Ä'}))
    plan = result['plan']; db = database(plan)
    before = db.execute('SELECT * FROM posts').fetchone()
    assert execute(db, result['apply']) == 1 and execute(db, result['apply']) == 0
    assert execute(db, result['restore']) == 1 and execute(db, result['restore']) == 0
    assert db.execute('SELECT * FROM posts').fetchone() == before
    for field, changed in [('post_content', 'edited'), ('post_title', 'changed'), ('post_modified_gmt', '2026-10-12 16:00:01')]:
        db = database(plan)
        db.execute(f'UPDATE posts SET `{field}`=?', (changed,))
        assert execute(db, result['apply']) == 0
    assert '{wpdb-placeholder-test}' not in result['apply']
    assert '100%' in result['apply']
    assert 'error' in run(mode='sql', plan=plan | {'after_content': 'bad'}, expected=plan['fingerprint'])
    assert 'error' in run(blocks=fixture(), snapshot_site='https://other.example/')
