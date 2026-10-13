"""Pure saved-shell preview and local CAS/restore proof; no hosted mutations.

Content is synthetic. Installed Divi parser/style proof is an explicit operator
check; SQLite's binary comparison is not evidence of a hosted MySQL write.

See tests/README.md Keep/Replace/Remove map: shell wrappers, private snapshot and installed operator gate.
"""
import copy
import json
import re
import sqlite3
import subprocess
import unicodedata
from pathlib import Path

import pytest

from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
PHP = r'''
define('ABSPATH', '/');
$state=json_decode(stream_get_contents(STDIN),true);
class Database {
    public $posts='posts', $postmeta='postmeta', $options='options';
    function prepare($sql,...$args) {
        foreach($args as $arg) $sql=preg_replace_callback('/%[sd]/',static function($match)use($arg){
            return $match[0]==='%d'?(string)(int)$arg:"'".str_replace("'","''",(string)$arg)."'";
        },$sql,1);
        return str_replace('%','{wpdb-placeholder-test}',$sql);
    }
    function remove_placeholder_escape($sql){return str_replace('{wpdb-placeholder-test}','%',$sql);}
}
$wpdb=new Database();
function parse_blocks($value){return(new WP_Block_Parser())->parse($value);}
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
WP_SERIALIZER
require 'wordpress/web/app/mu-plugins/bioco-import/includes/shell-wrapper-adoption.php';
try {
    if(($state['mode']??'')==='sql') echo json_encode(['sql'=>bioco_shell_wrapper_sql($state['plan'],$state['expected'],$state['restore']??false)]);
    else {
        $record=$state['record'];
        if(isset($state['blocks'])) $record['post_content']=serialize_blocks($state['blocks']);
        $plan=bioco_shell_wrapper_plan($record,$state['site'],$state['context']);
        echo json_encode(['plan'=>$plan,'before_blocks'=>parse_blocks($record['post_content']),
            'blocks'=>parse_blocks($plan['after_content']),
            'apply'=>bioco_shell_wrapper_sql($plan,$plan['fingerprint']),
            'restore'=>bioco_shell_wrapper_sql($plan,$plan['fingerprint'],true)]);
    }
}catch(Throwable $error){echo json_encode(['error'=>$error->getMessage()]);}
'''


def block(name, attrs=None, children=None):
    children = children or []
    return dict(blockName=name, attrs=attrs or {}, innerBlocks=children,
                innerHTML='', innerContent=[None] * len(children) if children else ['\n'])


def fixture(slot='header'):
    name = 'navigation-shell' if slot == 'header' else 'footer-shell'
    leaf = block('bioco-divi/' + name, {'module': {'id': 'native-shell-id'},
        'label': "100% Gemüse & Rückblicke", 'url': 'https://intranet.example/?x=1&y=2'})
    column = block('divi/column', {'module': {'advanced': {'type': {'desktop': {'value': '4_4'}}}}}, [leaf])
    row = block('divi/row', {'module': {'advanced': {'columnStructure': {'desktop': {'value': '4_4'}}}}}, [column])
    section = block('divi/section', {'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {
        'class': 'bioco-global-layout bioco-global-' + slot}}}}}}, [row])
    return [section]


def wrappers(blocks):
    section = blocks[0]
    row = section['innerBlocks'][0]
    return [section, row, row['innerBlocks'][0]]


def context():
    return {'site': 'https://staging.example/',
            'presets': {'module': {name: {'default': ''} for name in ['divi/section', 'divi/row', 'divi/column']}},
            'options': [{'option_name': 'et_divi_builder_global_presets_d5', 'option_value': '{"Native":"Ä 100%"}'}],
            'meta': [{'meta_id': '7', 'post_id': '42', 'meta_key': '_et_pb_use_builder', 'meta_value': 'on'},
                     {'meta_id': '8', 'post_id': '42', 'meta_key': 'authored', 'meta_value': 'Ä 100%'}],
            'assignments_sha256': 'a' * 64}


def post_record(**saved):
    record = dict(ID=42, post_author='7', post_date='2026-10-13 05:00:00',
        post_date_gmt='2026-10-13 05:00:00', post_content='',
        post_title="Editor's Header Ä 100%", post_excerpt='', post_status='publish',
        comment_status='closed', ping_status='closed', post_password='',
        post_name='public-header', to_ping='', pinged='', post_modified='2026-10-13 05:00:00',
        post_modified_gmt='2026-10-13 05:00:00', post_content_filtered='',
        post_parent='0', guid='https://staging.example/?p=42', menu_order='0',
        post_type='et_header_layout', post_mime_type='', comment_count='0')
    record.update(saved)
    return record


def run(blocks=None, record=None, ctx=None, **state):
    record = record or post_record()
    payload = dict(record=record, context=ctx or context(), site='https://staging.example/', **state)
    if blocks is not None:
        payload['blocks'] = blocks
    result = subprocess.run(['php', '-r', PHP.replace('WP_SERIALIZER', WP_SERIALIZER_STUB)],
                            cwd=ROOT, input=json.dumps(payload), text=True, capture_output=True, check=True)
    assert not result.stderr, result.stderr
    return json.loads(result.stdout)


@pytest.mark.parametrize('slot', ['header', 'footer'])
def test_only_missing_native_wrapper_controls_change_with_all_existing_content_preserved(slot):
    record = post_record(post_type='et_' + slot + '_layout', post_title='Authored Ä 100%', post_name='authored')
    blocks = fixture(slot)
    wrappers(blocks)[1]['attrs']['module'].update(id='saved-row-id', meta={'adminLabel': {'desktop': {'value': 'Anita row'}}})
    wrappers(blocks)[2]['innerBlocks'].append(block('divi/text', {'content': 'An authored sibling'}))
    wrappers(blocks)[2]['innerContent'].append(None)
    result = run(blocks, record=record)
    assert result['plan']['changed'] == 6
    expected = copy.deepcopy(result['before_blocks'])
    for node in wrappers(expected):
        node['attrs']['module']['decoration'] = {
            'sizing': {'desktop': {'value': {'width': '100%', 'maxWidth': 'none'}}},
            'spacing': {'desktop': {'value': {key: dict.fromkeys(['top', 'right', 'bottom', 'left'], '0px')
                                           for key in ['margin', 'padding']}}}}
    assert result['blocks'] == expected
    assert result['plan']['before'] == record | {'post_content': result['plan']['before']['post_content']}
    copy_plan = result['plan']['copy_proposal']
    assert copy_plan['post_status'] == 'draft' and copy_plan['assignments'] == [] and 'ID' not in copy_plan
    assert copy_plan['meta_input']['_et_pb_use_builder'] == 'on'
    assert re.fullmatch(r'qa-249-wrapper-[a-f0-9]{16}', copy_plan['meta_input']['_bioco_editor_verification'])
    repeated = run(record=result['plan']['before'] | {'post_content': result['plan']['after_content']})
    assert repeated['plan']['changed'] == 0
    assert repeated['plan']['after_content'] == result['plan']['after_content']
    assert repeated['apply'] == repeated['restore'] == ''


@pytest.mark.parametrize('domain,value', [
    ('sizing', {'desktop': {'value': {'height': '250px', 'width': '75%'}}, 'phone': {'value': {'width': '62%'}}}),
    ('spacing', {'desktop': {'value': {'padding': {'left': '17px'}}, 'hover': {'value': {'margin': {'top': '3px'}}}}}),
    ('sizing', {}), ('spacing', None),
])
def test_authored_domain_including_partial_empty_responsive_and_hover_values_is_preserved(domain, value):
    blocks = fixture()
    wrappers(blocks)[1]['attrs']['module']['decoration'] = {domain: value}
    result = run(blocks)
    assert result['plan']['changed'] == 5
    # WordPress decodes JSON empty objects as PHP arrays before our plan runs.
    assert wrappers(result['blocks'])[1]['attrs']['module']['decoration'][domain] == wrappers(result['before_blocks'])[1]['attrs']['module']['decoration'][domain]
    assert {'path': [0, 0], 'domain': domain, 'action': 'preserve', 'reason': 'authored-domain'} in result['plan']['report']


@pytest.mark.parametrize('owned', [
    {'modulePreset': ['author-preset']},
    {'groupPreset': {'module.decoration.sizing': {'presetId': ['author-group'], 'groupName': 'divi/row'}}},
    {'styleIds': ['author-style']},
    {'module': {'decoration': {'css': {'phone': {'value': {'mainElement': 'width:80%'}}}}}},
    {'module': {'advanced': {'htmlAttributes': {'phone': {'value': {'id': 'author-mobile'}}}}}},
    {'module': {'decoration': {'attributes': {'desktop': {'value': {'attributes': [{'name': 'id', 'value': 'author-row'}]}}}}}},
    {'module': {'decoration': {'attributes': {'phone': {'value': {'attributes': [{'name': 'class', 'value': 'author-mobile'}]}}}}}},
])
def test_authored_preset_style_and_selectors_preserve_entire_wrapper(owned):
    blocks = fixture()
    wrappers(blocks)[1]['attrs'].update(owned)
    result = run(blocks)
    assert result['plan']['changed'] == 4
    assert wrappers(result['blocks'])[1]['attrs'] == wrappers(result['before_blocks'])[1]['attrs']


def test_global_default_preset_is_preserved_without_expansion_or_registry_changes():
    ctx = context()
    ctx['presets']['module']['divi/row'] = {'default': 'authored-row', 'items': {'authored-row': {'width': '80%'}}}
    result = run(fixture(), ctx=ctx)
    assert result['plan']['changed'] == 4
    assert wrappers(result['blocks'])[1]['attrs'] == wrappers(result['before_blocks'])[1]['attrs']
    assert result['plan']['context']['presets'] == ctx['presets']


@pytest.mark.parametrize('domain', ['sizing', 'spacing'])
def test_group_default_preserves_inherited_domain_without_blocking_other_defaults(domain):
    ctx = context()
    preset_id = 'qa-' + domain
    inherited = {'width': '80%'} if domain == 'sizing' else {'padding': {'top': '28px'}}
    ctx['presets']['group'] = {'divi/' + domain: {
        'default': preset_id,
        'items': {preset_id: {'type': 'group', 'name': 'QA ' + domain,
                              'moduleName': 'divi/section', 'groupName': 'divi/' + domain,
                              'groupId': 'module.decoration.' + domain,
                              'attrs': {'module': {'decoration': {domain: {'desktop': {'value': inherited}}}}}}}
    }}
    result = run(fixture(), ctx=ctx)
    assert result['plan']['changed'] == 3
    other = 'spacing' if domain == 'sizing' else 'sizing'
    for path, node in zip(([0], [0, 0], [0, 0, 0]), wrappers(result['blocks'])):
        assert domain not in node['attrs']['module']['decoration']
        assert other in node['attrs']['module']['decoration']
        assert {'path': path, 'domain': domain, 'action': 'preserve',
                'reason': 'global-default-group-preset'} in result['plan']['report']
    assert result['plan']['context']['presets'] == ctx['presets']


@pytest.mark.parametrize('mismatch', ['missing-marker', 'wrong-leaf', 'second-row', 'second-column'])
def test_unrecognized_or_authored_structure_is_never_flattened(mismatch):
    blocks = fixture()
    nodes = wrappers(blocks)
    if mismatch == 'missing-marker':
        nodes[0]['attrs'] = {}
    elif mismatch == 'wrong-leaf':
        nodes[2]['innerBlocks'][0]['blockName'] = 'bioco-divi/footer-shell'
    elif mismatch == 'second-row':
        nodes[0]['innerBlocks'].append(block('divi/row'))
        nodes[0]['innerContent'].append(None)
    else:
        nodes[1]['innerBlocks'].append(block('divi/column'))
        nodes[1]['innerContent'].append(None)
    result = run(blocks)
    assert result['plan']['changed'] == 0
    assert result['plan']['after_content'] == result['plan']['before']['post_content']


def database(plan):
    db = sqlite3.connect(':memory:')
    def normalized(value):
        return ''.join(c for c in unicodedata.normalize('NFD', value.casefold()) if not unicodedata.combining(c))
    db.create_collation('MYSQL_CI', lambda left, right: (normalized(left) > normalized(right)) - (normalized(left) < normalized(right)))
    columns = ','.join('`' + key + '` ' + ('INTEGER PRIMARY KEY' if key == 'ID' else 'TEXT COLLATE MYSQL_CI') for key in plan['before'])
    db.execute('CREATE TABLE posts (' + columns + ')')
    db.execute('INSERT INTO posts VALUES (' + ','.join('?' for _ in plan['before']) + ')', list(plan['before'].values()))
    db.execute('CREATE TABLE postmeta (meta_id INTEGER PRIMARY KEY,post_id INTEGER,meta_key TEXT COLLATE MYSQL_CI,meta_value TEXT COLLATE MYSQL_CI)')
    db.executemany('INSERT INTO postmeta VALUES (?,?,?,?)', [list(row.values()) for row in plan['context']['meta']])
    db.execute('CREATE TABLE options (option_name TEXT COLLATE MYSQL_CI,option_value TEXT COLLATE MYSQL_CI)')
    db.executemany('INSERT INTO options VALUES (?,?)', [list(row.values()) for row in plan['context']['options']])
    return db


def execute(db, sql):
    # Translate explicit MySQL byte guards to SQLite's binary collation.
    sql = re.sub(r'BINARY (`[^`]+`|[a-z_]+)', r'\1 COLLATE BINARY', sql)
    sql = re.sub(r'BINARY (?=\')', '', sql)
    db.execute(sql)
    return db.execute('SELECT changes()').fetchone()[0]


def test_conditional_apply_restore_are_idempotent_and_leave_other_rows_untouched():
    result = run(fixture())
    db = database(result['plan'])
    before = db.execute('SELECT * FROM posts').fetchall()
    meta = db.execute('SELECT * FROM postmeta').fetchall()
    options = db.execute('SELECT * FROM options').fetchall()
    assert execute(db, result['apply']) == 1 and execute(db, result['apply']) == 0
    assert db.execute('SELECT post_content FROM posts').fetchone()[0] == result['plan']['after_content']
    assert execute(db, result['restore']) == 1 and execute(db, result['restore']) == 0
    assert db.execute('SELECT * FROM posts').fetchall() == before
    assert db.execute('SELECT * FROM postmeta').fetchall() == meta
    assert db.execute('SELECT * FROM options').fetchall() == options
    assert '100%' in result['apply'] and '{wpdb-placeholder-test}' not in result['apply'] + result['restore']


@pytest.mark.parametrize('column', list(post_record()))
def test_every_raw_post_column_blocks_a_concurrent_edit_during_apply_and_restore(column):
    result = run(fixture())
    assert len(result['plan']['before']) == 23
    for operation in ['apply', 'restore']:
        db = database(result['plan'])
        if operation == 'restore':
            assert execute(db, result['apply']) == 1
        if column == 'ID':
            value = 43
        elif column in ['post_author', 'post_parent', 'menu_order', 'comment_count']:
            value = str(int(result['plan']['before'][column]) + 1)
        elif 'date' in column or 'modified' in column:
            value = '2026-10-13 05:00:01'
        else:
            value = 'Concurrent editor change'
        db.execute('UPDATE posts SET `' + column + '`=?', (value,))
        before = db.execute('SELECT * FROM posts').fetchall()
        assert execute(db, result[operation]) == 0
        assert db.execute('SELECT * FROM posts').fetchall() == before


def test_preserved_raw_record_context_is_validated_against_all_captured_fields():
    seed = run(fixture())
    ctx = context()
    ctx['raw_post'] = {key: str(value) for key, value in seed['plan']['before'].items()}
    result = run(record=seed['plan']['before'], ctx=ctx)
    assert result['plan']['context']['raw_post'] == ctx['raw_post']
    ctx['raw_post']['post_author'] = '8'
    assert run(record=seed['plan']['before'], ctx=ctx)['error'] == 'Raw post context differs from source record.'


def test_a_partial_seven_field_export_is_refused():
    record = {key: value for key, value in post_record().items() if key in [
        'ID', 'post_type', 'post_status', 'post_title', 'post_name', 'post_content', 'post_modified_gmt']}
    assert run(fixture(), record=record)['error'] == 'Expected one complete saved shell record.'


def test_author_race_would_escape_if_the_new_raw_field_guard_were_removed():
    result = run(fixture())
    db = database(result['plan'])
    db.execute("UPDATE posts SET post_author='8'")
    assert execute(db, result['apply']) == 0
    weakened = re.sub(r" AND BINARY `post_author` = BINARY '[^']*'", '', result['apply'])
    assert weakened != result['apply']
    assert execute(db, weakened) == 1


@pytest.mark.parametrize('edit', [
    "UPDATE posts SET post_title=lower(post_title)",
    "UPDATE posts SET post_title=replace(post_title,'Ä','A')",
    "UPDATE posts SET post_name='other'", "UPDATE posts SET post_status='draft'",
    "UPDATE posts SET post_content=lower(post_content)",
    "UPDATE posts SET post_modified_gmt='2026-10-13 06:00:00'",
    "UPDATE postmeta SET meta_value='ON' WHERE meta_id=7",
    "UPDATE postmeta SET meta_key='AUTHORED' WHERE meta_id=8",
    "DELETE FROM postmeta WHERE meta_id=8",
    "INSERT INTO postmeta VALUES (9,42,'new-native-setting','value')",
    "UPDATE options SET option_value=lower(option_value)",
    "UPDATE options SET option_value=replace(option_value,'Ä','A')",
    "DELETE FROM options",
    "INSERT INTO options VALUES ('et_divi_builder_global_presets_ng','new preset')",
])
def test_concurrent_source_metadata_or_preset_changes_block_apply_and_restore(edit):
    result = run(fixture())
    for operation in ['apply', 'restore']:
        db = database(result['plan'])
        if operation == 'restore':
            assert execute(db, result['apply']) == 1
        db.execute(edit)
        before = db.execute('SELECT * FROM posts').fetchall()
        assert execute(db, result[operation]) == 0
        assert db.execute('SELECT * FROM posts').fetchall() == before


def test_volatile_editor_lock_and_unrelated_layout_changes_do_not_block_source_cas():
    result = run(fixture())
    db = database(result['plan'])
    db.execute("INSERT INTO postmeta VALUES (9,42,'_edit_lock','fresh'),(10,99,'author','other')")
    assert execute(db, result['apply']) == 1


def test_case_conflict_would_escape_without_binary_guards():
    result = run(fixture())
    db = database(result['plan'])
    db.execute('UPDATE posts SET post_title=lower(post_title)')
    assert execute(db, result['apply']) == 0
    assert execute(db, result['apply'].replace('BINARY ', '')) == 1


@pytest.mark.parametrize('tamper', ['content', 'fingerprint', 'copy', 'assignment'])
def test_modified_preview_or_expected_fingerprint_is_rejected(tamper):
    result = run(fixture())
    plan = copy.deepcopy(result['plan'])
    expected = plan['fingerprint']
    if tamper == 'content':
        plan['after_content'] += 'editor text'
    elif tamper == 'fingerprint':
        expected = 'f' * 64
    elif tamper == 'copy':
        plan['copy_proposal']['assignments'] = [123]
    else:
        plan['context']['assignments_sha256'] = 'b' * 64
    assert run(mode='sql', plan=plan, expected=expected)['error'] == 'Shell preview changed.'


@pytest.mark.parametrize('bad', ['site', 'hash', 'duplicate-meta', 'duplicate-option', 'wrong-post'])
def test_incomplete_or_ambiguous_context_fails_closed(bad):
    ctx = context()
    if bad == 'site':
        ctx['site'] = 'https://other.example/'
    elif bad == 'hash':
        ctx['assignments_sha256'] = 'invalid'
    elif bad == 'duplicate-meta':
        ctx['meta'].append(ctx['meta'][0])
    elif bad == 'duplicate-option':
        ctx['options'].append(ctx['options'][0])
    else:
        ctx['meta'][0]['post_id'] = '99'
    assert 'error' in run(fixture(), ctx=ctx)
