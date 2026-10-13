"""Native depot card layout and conditional page adoption through the WP parser."""
import json
from copy import deepcopy
import subprocess
from pathlib import Path

import pytest

from test_wordpress_back_button_adoption import database, execute
from test_wordpress_record_adoption import block
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
require 'wordpress/web/app/mu-plugins/bioco-import/includes/depot-adoption.php';
try {
 if(($state['mode']??'')==='layout')echo json_encode(parse_blocks(bioco_depot_layout_definition()['content']));
 elseif(($state['mode']??'')==='sql')echo json_encode(['sql'=>bioco_depot_adoption_sql($state['plan'],$state['expected'],$state['restore']??false)]);
 else {$record=$state['record'];if(isset($state['blocks']))$record['post_content']=serialize_blocks($state['blocks']);$plan=bioco_depot_adoption_plan($record,$state['snapshot_site']??$state['site']);echo json_encode(['plan'=>$plan,'blocks'=>parse_blocks($plan['after_content']),'before'=>parse_blocks($record['post_content']),'apply'=>bioco_depot_adoption_sql($plan,$plan['fingerprint']),'restore'=>bioco_depot_adoption_sql($plan,$plan['fingerprint'],true)]);}
} catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]);}
'''


def fixture(values=None):
    values = values or {'intro': 'Dienstag ab 16 Uhr', 'locations_heading': 'Unsere Depots',
                        'route_label': 'Weg zeigen', 'empty_message': 'Keine Depots & 100%',
                        'locations': [{'name': 'Old local row', 'description': 'Private old field'}],
                        'center_lat': 47.4, 'zoom': 12, 'interaction': 'full'}
    attrs = {'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {'id': 'depot-map-id'}}}},
                        'decoration': {'spacing': {'phone': {'value': {'padding': {'top': '32px'}}}}}}}
    for key, value in values.items(): attrs[key] = {'innerContent': {'desktop': {'value': value}}}
    leaf = block('bioco-divi/depot-map', attrs)
    section = block('divi/section', {'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {'id': 'section-id'}}}}}},
                    [block('divi/row', {}, [block('divi/column', {}, [leaf])])])
    geisshof = block('divi/section', {}, [block('divi/row', {}, [block('divi/column', {}, [block('bioco-divi/geisshof-map', {
        'locations': {'innerContent': {'desktop': {'value': [{'name': 'Geisshof', 'lat': 47.4, 'lng': 8.2}]}}}
    })])])])
    end = block('divi/section', {}, [block('divi/row', {}, [block('divi/column', {}, [block('divi/text', {'content': {'innerContent': {'desktop': {'value': '<p>Keep end</p>'}}}})])])])
    return [block('divi/placeholder', {}, [geisshof, section, end])]


def run(blocks=None, record=None, **state):
    record = record or {'ID': 19, 'post_type': 'page', 'post_status': 'publish', 'post_title': 'Unsere Depots',
                        'post_name': 'standorte-depots', 'post_content': '', 'post_modified_gmt': '2026-10-12 17:00:00'}
    payload = {'site': 'https://example.test/', 'record': record, **state}
    if blocks is not None: payload['blocks'] = blocks
    result = subprocess.run(['php', '-r', PHP.replace('WP_SERIALIZER', WP_SERIALIZER_STUB)], cwd=ROOT,
                            input=json.dumps(payload), text=True, capture_output=True)
    assert result.returncode == 0 and not result.stderr, result.stderr
    return json.loads(result.stdout)


def walk(nodes):
    for n in nodes:
        yield n
        yield from walk(n['innerBlocks'])


def test_native_starter_is_portable_and_uses_only_public_record_bindings():
    nodes = run(mode='layout')
    assert all(n['blockName'].startswith('divi/') for n in walk(nodes))
    source = json.dumps(nodes)
    for binding in ['loop_bioco_depot_name', 'loop_bioco_depot_address',
                    'loop_bioco_depot_website_label', 'loop_bioco_depot_website', 'loop_bioco_depot_route_url']:
        assert binding in source
    assert 'bioco-depot-loop' in source and 'bioco-loop-empty' in source
    assert 'gridTemplateColumns' in source and 'phone' in source
    assert 'depot_lat' not in source and 'depot_description' not in source


def test_map_leaf_id_settings_geisshof_and_opaque_neighbors_survive_adoption():
    blocks = fixture()
    blocks[0]['innerContent'] = [None, '<aside data-editor="owned">Keep this HTML</aside>',
                                 None, '<div>Keep trailing text</div>', None]
    result = run(blocks=blocks)
    assert 'error' not in result, result
    assert result['plan']['report'] == [{'path': [0, 1], 'action': 'insert-native-cards',
        'preserved_map_id': 'depot-map-id', 'archived_local_rows': 1}]
    before = result['before'][0]['innerBlocks']; after = result['blocks'][0]['innerBlocks']
    assert len(after) == 6
    assert after[0] == before[0] and after[-1] == before[-1]
    assert result['blocks'][0]['innerContent'] == [None, '<aside data-editor="owned">Keep this HTML</aside>',
        None, None, None, None, '<div>Keep trailing text</div>', None]
    assert 'Dienstag ab 16 Uhr' in json.dumps(after[1])
    assert 'Unsere Depots' in json.dumps(after[3])
    assert 'bioco-depot-loop' in json.dumps(after[4])
    old = before[1]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']
    new = after[2]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']
    for field in ['locations', 'center_lat', 'zoom', 'interaction', 'module']:
        assert new[field] == old[field]
    assert new['intro']['innerContent']['desktop']['value'] == ''
    assert new['show_address_list']['innerContent']['desktop']['value'] is False
    source = json.dumps(after, ensure_ascii=False)
    for text in ['Unsere Depots', 'Dienstag ab 16 Uhr', 'Weg zeigen', 'Keine Depots &amp; 100%']:
        assert text in source
    assert 'Private old field' in json.dumps(new)  # archival map field remains stored, not rendered as a card
    assert 'Private old field' not in json.dumps([after[1], *after[3:5]])
    assert all(n['blockName'].startswith('divi/') for n in walk([after[1], *after[3:5]]))


@pytest.mark.parametrize('change', ['extra-field', 'responsive', 'unknown-control', 'shared-section', 'authored-markup', 'wrong-page'])
def test_ambiguous_or_unowned_source_refuses_conversion(change):
    blocks = fixture(); leaf = blocks[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    record = None
    if change == 'extra-field': leaf['attrs']['future_field'] = 1
    if change == 'responsive': leaf['attrs']['intro']['innerContent']['phone'] = {'value': 'Different'}
    if change == 'unknown-control': leaf['attrs']['module']['advanced']['new_setting'] = {'desktop': {'value': 'x'}}
    if change == 'shared-section': blocks[0]['innerBlocks'][1]['innerBlocks'].append(block('divi/row')); blocks[0]['innerBlocks'][1]['innerContent'] = [None, None]
    if change == 'authored-markup': leaf['innerHTML'] = '<p>Hidden authored text</p>'; leaf['innerContent'] = [leaf['innerHTML']]
    if change == 'wrong-page': record = {'ID': 19, 'post_type': 'page', 'post_status': 'publish', 'post_title': 'Other', 'post_name': 'other', 'post_content': '', 'post_modified_gmt': '2026-10-12 17:00:00'}
    assert 'error' in run(blocks, record=record)


def test_depot_cas_apply_restore_and_edit_conflicts():
    result = run(blocks=fixture())
    plan = result['plan']; db = database(plan); before = db.execute('SELECT * FROM posts').fetchone()
    assert execute(db, result['apply']) == 1 and execute(db, result['apply']) == 0
    assert execute(db, result['restore']) == 1 and execute(db, result['restore']) == 0
    assert db.execute('SELECT * FROM posts').fetchone() == before
    for field, value in [('post_content', 'other'), ('post_title', 'Other title'), ('post_modified_gmt', '2026-10-12 17:01:00')]:
        db = database(plan); db.execute(f'UPDATE posts SET `{field}`=?', (value,))
        assert execute(db, result['apply']) == 0
    assert 'error' in run(mode='sql', plan=plan | {'after_content': 'tampered'}, expected=plan['fingerprint'])
    assert 'error' in run(blocks=fixture(), snapshot_site='https://wrong.test/')


def test_repeat_conversion_keeps_exact_native_body_and_has_no_sql():
    first = run(blocks=fixture())['plan']
    repeated = run(record=first['before'] | {'post_content': first['after_content']})
    assert 'error' not in repeated, repeated
    assert repeated['plan']['after_content'] == first['after_content']
    assert repeated['plan']['report'][0]['action'] == 'already-adopted'
    assert repeated['apply'] == repeated['restore'] == ''
    assert sum('bioco-native-depot-cards' in json.dumps(n['attrs']) for n in walk(repeated['blocks'])) == 1


def test_empty_intro_and_hidden_address_list_are_valid_first_adoption_values():
    source = fixture({'intro': '', 'show_address_list': False, 'locations_heading': 'Depots'})
    result = run(blocks=source)
    assert 'error' not in result, result
    assert result['plan']['report'][0]['action'] == 'insert-native-cards'
    repeated = run(record=result['plan']['before'] | {'post_content': result['plan']['after_content']})
    assert 'error' not in repeated, repeated
    assert repeated['plan']['after_content'] == result['plan']['after_content']
    assert repeated['apply'] == repeated['restore'] == ''


def test_pre_marker_completed_adoption_keeps_native_cards_and_source_bytes():
    native = run(blocks=fixture())['blocks']
    section = native[0]['innerBlocks'][2]
    del section['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class']
    result = run(blocks=native)
    assert 'error' not in result, result
    assert result['blocks'] == result['before']
    assert result['apply'] == result['restore'] == ''


def test_repeat_preserves_editor_copy_module_order_and_responsive_controls():
    native = run(blocks=fixture())['blocks']
    siblings = native[0]['innerBlocks']
    cards = siblings.pop(4)
    siblings.insert(1, cards)  # Native section order belongs to the editor.
    cards['attrs']['module']['decoration']['layout']['phone'] = {
        'value': {'gridTemplateColumns': 'repeat(2, minmax(0, 1fr))', 'rowGap': '41px'}}
    cards['attrs']['module']['decoration']['attributes'] = {'desktop': {'value': {'attributes': [{
        'name': 'class', 'value': 'editor-card-class bioco-native-depot-cards'}]}}}
    cards['innerBlocks'][0]['attrs']['module']['advanced']['loop']['desktop']['value'].update({
        'order': 'DESC', 'postPerPage': 7})
    column = cards['innerBlocks'][0]['innerBlocks'][0]
    column['innerBlocks'].reverse()
    column['innerBlocks'].insert(0, block('divi/text', {
        'content': {'innerContent': {'desktop': {'value': '<p>Editor introduction</p>'}}}}))
    column['innerContent'] = [None] * len(column['innerBlocks'])
    text = next(n for n in walk([cards]) if n['blockName'] == 'divi/button')
    text['attrs']['button']['innerContent']['desktop']['value']['text'] = 'Unsere neue Route'
    result = run(blocks=native)
    assert 'error' not in result, result
    assert result['blocks'] == result['before']
    assert result['plan']['report'][0]['action'] == 'already-adopted'
    assert result['apply'] == result['restore'] == ''


@pytest.mark.parametrize('change', [
    'duplicate-cards', 'missing-loop', 'missing-cards-marker', 'wrong-loop-type',
    'legacy-with-native-cards', 'missing-native-cards', 'map-presentation-restored',
    'different-parent', 'opaque-card-child', 'disabled-loop', 'wrong-loop-child',
])
def test_partial_or_ambiguous_native_adoption_refuses(change):
    native = run(blocks=fixture())['blocks']
    siblings = native[0]['innerBlocks']; cards = siblings[4]
    loop = cards['innerBlocks'][0]
    if change == 'duplicate-cards': siblings.append(deepcopy(cards))
    if change == 'missing-loop': loop['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] = ''
    if change == 'missing-cards-marker': cards['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] = 'bioco-native-depot-cards-copy'
    if change == 'wrong-loop-type': loop['attrs']['module']['advanced']['loop']['desktop']['value']['subTypes'][0]['value'] = 'event'
    if change == 'disabled-loop': loop['attrs']['module']['advanced']['loop']['desktop']['value']['enable'] = 'off'
    if change == 'wrong-loop-child': loop['innerBlocks'] = [block('divi/text')]; loop['innerContent'] = [None]
    if change == 'legacy-with-native-cards':
        native = fixture(); native[0]['innerBlocks'].append(cards)
    if change == 'missing-native-cards': siblings.pop(4)
    if change == 'map-presentation-restored':
        leaf = siblings[2]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
        leaf['attrs']['intro']['innerContent']['desktop']['value'] = 'A second introduction'
    if change == 'different-parent':
        siblings.pop(4); siblings.append(block('divi/section', {}, [cards]))
    if change == 'opaque-card-child':
        column = loop['innerBlocks'][0]
        column['innerBlocks'].append(block('bioco-divi/depot-map'))
        column['innerContent'].append(None)
    native[0]['innerContent'] = [None] * len(native[0]['innerBlocks'])
    assert 'error' in run(blocks=native)


def test_class_substring_and_editor_text_do_not_claim_an_adopted_card_set():
    legacy = fixture()
    other = block('divi/section', {'module': {'advanced': {'htmlAttributes': {'desktop': {
        'value': {'class': 'bioco-native-depot-cards-copy'}}}}}}, [block('divi/row', {}, [
            block('divi/column', {}, [block('divi/text', {'content': {'innerContent': {'desktop': {
                'value': '<p>bioco-native-depot-cards bioco-depot-loop</p>'}}}})])])])
    legacy[0]['innerBlocks'].append(other); legacy[0]['innerContent'].append(None)
    result = run(blocks=legacy)
    assert 'error' not in result, result
    assert result['plan']['report'][0]['action'] == 'insert-native-cards'
    assert result['blocks'][0]['innerBlocks'][-1] == result['before'][0]['innerBlocks'][-1]
