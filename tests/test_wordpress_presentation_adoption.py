"""Native Button and intro saved-content conversion, without hosted writes.
See the Keep/Replace/Remove map in tests/README.md. Presentation preview, required fixture and separate captured content adoption gate.
"""
import copy
import json
import re
import subprocess
from pathlib import Path
import sqlite3
import pytest

from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
PHP = r'''
define('ABSPATH', '/');
function home_url($path) { return $GLOBALS['site'] . ltrim($path, '/'); }
class Database {
    public $posts = 'posts';
    function prepare($sql, ...$args) {
        foreach ($args as $arg) $sql = preg_replace_callback('/%s/', fn()=>"'" . str_replace("'", "''", (string)$arg) . "'", $sql, 1);
        return str_replace('%', '{test-percent}', $sql);
    }
    function remove_placeholder_escape($sql) { return str_replace('{test-percent}', '%', $sql); }
}
$wpdb = new Database();
function parse_blocks($v) { return (new WP_Block_Parser())->parse($v); }
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
WP_SERIALIZER
require 'wordpress/web/app/mu-plugins/bioco-import/includes/presentation-adoption.php';
$input = json_decode(stream_get_contents(STDIN), true);
$GLOBALS['site'] = $input['site'] ?? 'https://staging.example/';
try {
if (isset($input['plan'])) {
  $result = ['sql'=>bioco_import_presentation_sql($input['plan'], $input['expected'] ?? $input['plan']['fingerprint'])];
} elseif (isset($input['serialize'])) $result = ['content'=>serialize_blocks($input['blocks'])];
elseif (isset($input['record'])) {
  $plan = bioco_import_presentation_plan($input['record'], $input['snapshot_site'] ?? $GLOBALS['site']);
  $result = ['plan'=>$plan, 'apply'=>bioco_import_presentation_sql($plan,$plan['fingerprint']),
             'restore'=>bioco_import_presentation_sql($plan,$plan['fingerprint'],true)];
} elseif (isset($input['content'])) $result = bioco_import_presentation_content($input['content']);
else {
  $result = ['report'=>[]];
  $result['blocks'] = bioco_import_presentation_tree($input['blocks'], $result['report']);
}
} catch (RuntimeException $error) { $result = ['error'=>$error->getMessage()]; }
echo json_encode($result);
'''


def run(blocks=None, content=None, record=None, **extra):
    payload = {'record': record} if record is not None else ({'blocks': blocks} if content is None else {'content': content})
    payload.update(extra)
    process = subprocess.run(['php', '-r', PHP.replace('WP_SERIALIZER', WP_SERIALIZER_STUB)],
                             input=json.dumps(payload), text=True, capture_output=True, cwd=ROOT, check=True)
    assert process.stderr == ''
    return json.loads(process.stdout)


def serialize(blocks):
    return run(blocks, serialize=True)['content']


POST_COLUMNS = ['ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title',
                'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name',
                'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered',
                'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type', 'comment_count']


def raw_record(content=None):
    record = dict.fromkeys(POST_COLUMNS, '')
    record.update(ID='19', post_author='2', post_date='2026-10-12 14:00:00',
                  post_date_gmt='2026-10-12 12:00:00', post_content=content or serialize(fixture()),
                  post_title="Depots Ä ' %", post_status='publish', comment_status='closed',
                  ping_status='closed', post_name='standorte-depots', post_modified='2026-10-12 14:00:00',
                  post_modified_gmt='2026-10-12 12:00:00', post_parent='0',
                  guid='https://staging.example/?page_id=19', menu_order='0', post_type='page', comment_count='0')
    return record


def sqlite_statement(sql):
    return re.sub(r'BINARY (`[^`]+`) = BINARY (\'(?:\'\'|[^\'])*\')', r'\1 = \2 COLLATE BINARY', sql)


def saved_database(record):
    db = sqlite3.connect(':memory:')
    db.execute('CREATE TABLE posts (' + ', '.join(f'`{column}` TEXT' for column in POST_COLUMNS) + ')')
    db.execute('INSERT INTO posts VALUES (' + ','.join('?' for _ in POST_COLUMNS) + ')',
               tuple(record[column] for column in POST_COLUMNS))
    return db


def block(name, attrs=None, children=None):
    children = children or []
    return {'blockName': name, 'attrs': attrs or {}, 'innerBlocks': children,
            'innerHTML': '', 'innerContent': [None] * len(children) if children else ['\n']}


def classes(*names):
    return {'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {'class': '', 'id': ''}}}},
                       'decoration': {'attributes': {'desktop': {'value': {'attributes': [
                           {'id': 'class-id', 'name': 'class', 'value': ' '.join(names), 'adminLabel': 'CSS Class'}
                       ]}}}}}}


def tokens(b):
    return b['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][0]['value'].split()


def fixture():
    title = block('divi/heading', classes('bioco-divi-title') | {'title': {
        'innerContent': {'desktop': {'value': 'Authored Ä heading'}},
        'decoration': {'font': {'font': {'desktop': {'value': {'headingLevel': 'h1', 'color': '#123456'}},
                                               'phone': {'value': {'size': '24px'}}}}}}})
    text = block('divi/text', classes('bioco-divi-text') | {'content': {'innerContent': {
        'desktop': {'value': '<p>Authored body</p>'}}}})
    column = block('divi/column', classes('bioco-divi-content', 'bioco-divi-text-wide'), [title, text])
    row = block('divi/row', classes('bioco-divi-row'), [column])
    intro = block('divi/section', classes('bioco-divi-section', 'bioco-divi-page-intro',
                                         'bioco-divi-width-lg', 'bioco-divi-align-left'), [row])
    button = block('divi/button', classes('bioco-divi-button', 'bioco-divi-button--primary') | {
        'button': {'innerContent': {'desktop': {'value': {'text': 'Jetzt Ä',
            'linkUrl': '/anmeldung/?campaign=a%20b#form', 'linkTarget': 'off'}},
            'phone': {'value': {'text': 'Jetzt'}}},
            'decoration': {'font': {'font': {'phone': {'value': {'size': '14px'}}}}}}})
    other = block('divi/button', {'modulePreset': ['existing-native'], 'button': {'innerContent': {
        'desktop': {'value': {'text': 'Other', 'linkUrl': '/other', 'linkTarget': 'on'}}}}})
    return [intro, button, other]


def test_converts_exact_intro_and_button_preserving_authored_values_and_order():
    before = fixture()
    result = run(before)
    after = result['blocks']
    assert [b['blockName'] for b in after] == [b['blockName'] for b in before]
    assert result['report'] == [{'path': [0], 'action': 'intro'}, {'path': [1], 'action': 'button'}]
    assert after[2] == before[2]
    assert tokens(after[0]) == ['bioco-native-intro']
    section_decoration = after[0]['attrs']['module']['decoration']
    assert section_decoration['spacing']['desktop']['value']['padding'] == {
        'top': '54px', 'bottom': '54px', 'left': '0px', 'right': '0px'}
    assert section_decoration['spacing']['phone']['value']['padding'] == {
        'top': '32px', 'bottom': '32px', 'left': '6vw', 'right': '6vw'}
    row = after[0]['innerBlocks'][0]
    assert row['attrs']['module']['decoration']['sizing']['desktop']['value'] == {
        'width': '80%', 'maxWidth': '1080px'}
    assert row['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['desktop']['value']['maxWidth'] == '1160px'
    assert tokens(after[0]['innerBlocks'][0]) == []
    heading = after[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    assert heading['attrs']['title']['innerContent'] == before[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['title']['innerContent']
    assert heading['attrs']['title']['decoration']['font']['font']['desktop']['value']['color'] == '#123456'
    assert heading['attrs']['title']['decoration']['font']['font']['phone']['value']['size'] == '24px'
    assert heading['attrs']['module']['decoration']['spacing']['desktop']['value']['margin']['bottom'] == '2.75%'
    assert heading['attrs']['module']['decoration']['spacing']['phone']['value']['margin']['bottom'] == '0.5em'
    assert tokens(after[1]) == ['bioco-native-button']
    assert after[1]['attrs']['button']['innerContent'] == before[1]['attrs']['button']['innerContent']
    assert after[1]['attrs']['button']['decoration']['font']['font']['phone']['value']['size'] == '14px'
    assert 'clip-path: polygon(' in after[1]['attrs']['css']['desktop']['value']['mainElement']
    assert after[1]['attrs']['button']['decoration']['font']['font']['desktop']['value']['size'] == '16px'
    assert after[1]['attrs']['module']['decoration']['spacing']['desktop']['value']['padding'] == {
        'top': '12px', 'bottom': '12px', 'left': '24px', 'right': '24px'}
    assert 'spacing' not in after[1]['attrs']['button']['decoration']
    assert run(after)['blocks'] == after


def test_button_keeps_authored_native_padding_at_each_breakpoint():
    before = fixture()
    authored = {'desktop': {'value': {'padding': {'left': '37px'}}},
                'phone': {'value': {'padding': {'top': '9px', 'left': '13px'}}}}
    before[1]['attrs']['module']['decoration']['spacing'] = copy.deepcopy(authored)
    result = run(before)['blocks'][1]['attrs']['module']['decoration']['spacing']
    assert result['desktop']['value']['padding']['left'] == '37px'
    assert result['phone'] == authored['phone']


def test_existing_advanced_css_or_preset_keeps_legacy_button_intact():
    for extra in [{'css': {'desktop': {'value': {'mainElement': 'clip-path: none;'}}}},
                  {'modulePreset': ['authored-preset']}]:
        before = fixture()
        before[1]['attrs'].update(extra)
        result = run(before)
        assert result['blocks'][1] == before[1]
        assert result['report'][1]['action'] == 'preserve'


def test_nonstandard_intro_and_unrelated_classes_are_untouched():
    before = fixture()
    before[0]['innerBlocks'].append(block('divi/row', {'module': {'id': 'other'}}))
    before[1]['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][0]['value'] = 'other-button'
    result = run(before)
    assert result['blocks'] == before
    assert result['report'] == [{'path': [0], 'action': 'preserve', 'reason': 'intro-structure'}]


def test_single_page_sql_requires_exact_before_then_exact_after_for_restore():
    saved = serialize(run(blocks=fixture())['blocks'])
    record = raw_record(saved)
    result = run(record=record)
    plan = result['plan']
    assert plan['changed'] == 0  # already converted blocks are idempotent
    assert result['apply'] == result['restore'] == ''
    record['post_content'] = serialize(fixture())
    result = run(record=record)
    plan = result['plan']
    assert plan['changed'] == 2
    assert plan['version'] == 2
    assert list(plan['before']) == POST_COLUMNS
    db = saved_database(record)
    apply_sql, restore_sql = map(sqlite_statement, (result['apply'], result['restore']))
    assert db.execute(apply_sql).rowcount == 1
    assert db.execute(apply_sql).rowcount == 0
    assert db.execute('SELECT post_content FROM posts').fetchone()[0] == plan['after_content']
    assert db.execute(restore_sql).rowcount == 1
    assert db.execute(restore_sql).rowcount == 0
    db.execute("UPDATE posts SET post_title = 'Concurrent edit'")
    assert db.execute(apply_sql).rowcount == 0
    assert db.execute('SELECT post_content FROM posts').fetchone()[0] == record['post_content']


@pytest.mark.parametrize('column', POST_COLUMNS)
@pytest.mark.parametrize('restore', [False, True])
def test_every_raw_post_column_conflict_refuses_content_write(column, restore):
    record = raw_record()
    result = run(record=record)
    db = saved_database(record)
    if restore:
        assert db.execute(sqlite_statement(result['apply'])).rowcount == 1
    conflict = record[column] + ' concurrent'
    db.execute(f'UPDATE posts SET `{column}` = ?', (conflict,))
    before = db.execute('SELECT * FROM posts').fetchone()
    assert db.execute(sqlite_statement(result['restore' if restore else 'apply'])).rowcount == 0
    assert db.execute('SELECT * FROM posts').fetchone() == before


@pytest.mark.parametrize('column', POST_COLUMNS)
def test_plan_rejects_incomplete_and_nonraw_values(column):
    record = raw_record()
    del record[column]
    assert 'error' in run(record=record)
    record = raw_record()
    record[column] = 0
    assert 'error' in run(record=record)


def test_plan_rejects_extra_columns_wrong_site_and_changed_proposal():
    record = raw_record()
    assert 'error' in run(record=record | {'unknown': ''})
    assert 'error' in run(record=record, snapshot_site='https://production.example/')
    assert 'error' in run(record=record | {'post_status': 'draft'})
    result = run(record=record)
    plan = result['plan']
    assert 'error' in run(plan=plan, expected='0' * 64)
    for field, value in [('after_content', 'altered'), ('changed', 99), ('version', 1)]:
        tampered = copy.deepcopy(plan)
        tampered[field] = value
        assert 'error' in run(plan=tampered)


@pytest.mark.parametrize('key', ['modulePreset', 'groupPreset', 'presetId'])
@pytest.mark.parametrize('target', ['button', 'intro', 'row', 'column', 'heading', 'text'])
def test_nested_unresolved_presets_refuse_the_whole_target(key, target):
    before = fixture()
    intro = before[0]
    row = intro['innerBlocks'][0]
    column = row['innerBlocks'][0]
    selected = {'button': before[1], 'intro': intro, 'row': row, 'column': column,
                'heading': column['innerBlocks'][0], 'text': column['innerBlocks'][1]}[target]
    selected['attrs']['authored'] = {'deep': {key: 'shared-preset-17'}}
    result = run(before)
    index = 1 if target == 'button' else 0
    assert result['blocks'][index] == before[index]
    assert next(report for report in result['report'] if report['path'] == [index])['action'] == 'preserve'
    isolated = serialize([before[index]])
    assert run(content=isolated)['content'] == isolated
    assert run(content=isolated)['changed'] == 0


@pytest.mark.parametrize('group,control', [('advanced', 'htmlAttributes'), ('decoration', 'attributes')])
@pytest.mark.parametrize('device,state', [('tablet', 'value'), ('phone', 'value'),
                                        ('desktop', 'hover'), ('desktop', 'sticky')])
@pytest.mark.parametrize('index', [0, 1])
def test_responsive_or_state_classes_refuse_target_atomically(group, control, device, state, index):
    before = fixture()
    target = before[index]
    token = 'bioco-divi-title' if index == 0 else 'bioco-divi-button'
    if index == 0:
        target = target['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    value = {'class': token, 'id': 'authored-id'} if group == 'advanced' else {
        'attributes': [{'id': 'authored-class-id', 'name': 'class', 'value': token}]}
    target['attrs']['module'][group].setdefault(control, {}).setdefault(device, {})[state] = value
    result = run(before)
    assert result['blocks'][index] == before[index]
    assert next(report for report in result['report'] if report['path'] == [index])['action'] == 'preserve'


def test_intro_refusal_does_not_convert_a_button_inside_the_refused_subtree():
    before = fixture()
    column = before[0]['innerBlocks'][0]['innerBlocks'][0]
    column['innerBlocks'].append(copy.deepcopy(before[1]))
    column['innerContent'].append(None)
    column['attrs']['nested'] = {'groupPreset': 'unresolved'}
    result = run(before)
    assert result['blocks'][0] == before[0]
    assert result['blocks'][1] != before[1]
    assert not any(row['path'][:1] == [0] and row['action'] != 'preserve' for row in result['report'])


def test_intro_refuses_nested_button_responsive_class_retirement():
    before = fixture()
    child = copy.deepcopy(before[1])
    child['attrs']['module']['advanced']['htmlAttributes']['phone'] = {
        'value': {'class': 'bioco-divi-button--primary'}}
    column = before[0]['innerBlocks'][0]['innerBlocks'][0]
    column['innerBlocks'].append(child)
    column['innerContent'].append(None)
    result = run(before)
    assert result['blocks'][0] == before[0]
    assert result['report'][0]['action'] == 'preserve'


def test_retirement_handles_both_desktop_class_controls_without_changing_ids_or_other_tokens():
    before = fixture()
    attrs = before[1]['attrs']['module']
    attrs['advanced']['htmlAttributes']['desktop']['value'] = {
        'class': 'bioco-divi-button authored-advanced', 'id': 'keep-id'}
    attrs['decoration']['attributes']['desktop']['value']['attributes'].extend([
        {'name': 'id', 'value': 'keep-other-id', 'id': 'attribute-id'},
        {'name': 'class', 'value': 'bioco-divi-button--primary authored-extra', 'id': 'second-class'}])
    result = run(before)['blocks'][1]
    advanced = result['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']
    assert advanced == {'class': 'authored-advanced bioco-native-button', 'id': 'keep-id'}
    saved = result['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes']
    assert saved[0] == before[1]['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][0] | {'value': ''}
    assert saved[1] == {'name': 'id', 'value': 'keep-other-id', 'id': 'attribute-id'}
    assert saved[2] == {'name': 'class', 'value': 'authored-extra', 'id': 'second-class'}


def test_intro_defaults_preserve_desktop_and_tablet_inheritance_into_phone():
    before = fixture()
    before[0]['attrs']['module']['decoration']['spacing'] = {
        'desktop': {'value': {'padding': {'top': '77px'}}},
        'tablet': {'value': {'padding': {'bottom': '88px'}}},
        'phone': {'value': {'padding': {'left': '17px'}}}}
    title = before[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    title['attrs']['module']['decoration']['spacing'] = {
        'desktop': {'value': {'margin': {'bottom': '19px'}}}}
    result = run(before)['blocks'][0]
    spacing = result['attrs']['module']['decoration']['spacing']
    assert spacing['desktop']['value']['padding']['top'] == '77px'
    assert spacing['tablet'] == before[0]['attrs']['module']['decoration']['spacing']['tablet']
    assert spacing['phone']['value']['padding'] == {'left': '17px', 'right': '6vw'}
    title = result['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    assert title['attrs']['module']['decoration']['spacing'] == {'desktop': {'value': {'margin': {'bottom': '19px'}}}}


@pytest.mark.parametrize('value', ['', None, False, 0])
def test_explicit_authored_leaf_values_remain_and_are_not_masked_on_phone(value):
    before = fixture()
    before[0]['attrs']['module']['decoration']['spacing'] = {
        'desktop': {'value': {'padding': {'top': value}}}}
    result = run(before)['blocks'][0]['attrs']['module']['decoration']['spacing']
    assert result['desktop']['value']['padding']['top'] == value
    assert 'top' not in result['phone']['value']['padding']


def test_authored_default_color_is_not_masked_by_new_hover_color():
    before = fixture()
    before[1]['attrs']['button']['decoration']['font']['font']['desktop'] = {'value': {'color': '#123456'}}
    result = run(before)['blocks'][1]['attrs']['button']['decoration']['font']['font']
    assert result['desktop']['value']['color'] == '#123456'
    assert 'hover' not in result['desktop']


@pytest.mark.parametrize('saved', [None, {'desktop': None}, {'desktop': {'value': None}},
                                  {'desktop': {'value': {'padding': None}}}])
def test_unsupported_native_control_shape_refuses_intro_without_retiring_classes(saved):
    before = fixture()
    before[0]['attrs']['module']['decoration']['spacing'] = saved
    result = run(before)
    assert result['blocks'][0] == before[0]
    assert result['report'][0]['action'] == 'preserve'


def test_preview_keeps_opaque_markup_native_ids_and_unrelated_attributes():
    before = fixture()
    before[1]['attrs']['nativeModuleId'] = 'button-stable-id'
    before[1]['attrs']['authored'] = {'custom': ['Ä', False, None, 0]}
    before[0]['innerHTML'] = '<div data-authored="Ä">Keep exact bytes</div>'
    before[0]['innerContent'] = [before[0]['innerHTML'], None, '<!-- authored-tail -->']
    after = run(before)['blocks']
    assert after[1]['attrs']['nativeModuleId'] == 'button-stable-id'
    assert after[1]['attrs']['authored'] == before[1]['attrs']['authored']

    def structure(blocks):
        return [{key: structure(value) if key == 'innerBlocks' else value
                 for key, value in entry.items() if key != 'attrs'} for entry in blocks]

    assert structure(after) == structure(before)


def test_noncanonical_serialization_preserves_exact_source_without_adoption():
    source = serialize(fixture()).replace('<!-- wp:divi/section ', '<!-- wp:divi/section  ', 1)
    result = run(content=source)
    assert result == {'content': source, 'changed': 0, 'report': [
        {'path': [], 'action': 'preserve', 'reason': 'non-roundtrip-content'}]}
