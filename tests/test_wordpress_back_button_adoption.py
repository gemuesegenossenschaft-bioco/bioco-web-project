"""Execute saved-body conversion and apply/restore guards without hosted writes.

The legacy button shape matches the private staging/production snapshots. The
test content is synthetic. SQLite exercises the generated SQL with binary
comparison; hosted MySQL/operator verification remains a separate release check.
Keep/Replace/Remove evidence map: tests/README.md.
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
$state = json_decode(stream_get_contents(STDIN), true);
function home_url($path) { return $GLOBALS['state']['site'] . ltrim($path, '/'); }
class Database {
    public $posts = 'posts';
    function prepare($sql, ...$args) {
        foreach ($args as $arg) $sql = preg_replace_callback('/%s/', fn()=>"'" . str_replace("'", "''", (string) $arg) . "'", $sql, 1);
        return str_replace('%', '{wpdb-placeholder-test}', $sql);
    }
    function remove_placeholder_escape($sql) { return str_replace('{wpdb-placeholder-test}', '%', $sql); }
}
$wpdb = new Database();
function parse_blocks($value) { return (new WP_Block_Parser())->parse($value); }
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
WP_SERIALIZER
require 'wordpress/web/app/mu-plugins/bioco-import/includes/article-back-button-adoption.php';
try {
    if (($state['mode'] ?? '') === 'sql') {
        echo json_encode(['sql' => bioco_import_back_button_sql($state['plan'], $state['expected'], $state['restore'] ?? false)]);
    } else {
        $record = $state['record'];
        if (isset($state['blocks'])) $record['post_content'] = serialize_blocks($state['blocks']);
        $plan = bioco_import_back_button_plan($record, $state['snapshot_site'] ?? $state['site']);
        echo json_encode(['plan' => $plan, 'before_blocks' => parse_blocks($record['post_content']), 'blocks' => parse_blocks($plan['after_content']),
            'apply' => bioco_import_back_button_sql($plan, $plan['fingerprint']),
            'restore' => bioco_import_back_button_sql($plan, $plan['fingerprint'], true)]);
    }
} catch (Throwable $error) { echo json_encode(['error' => $error->getMessage()]); }
'''


def run(blocks=None, record=None, site='https://staging.example/', **state):
    record = record or {'ID': 42, 'post_type': 'et_body_layout', 'post_status': 'draft',
                        'post_title': "Editor's Detailvorlage Ä", 'post_content': '',
                        'post_modified_gmt': '2026-10-12 05:00:00'}
    payload = dict(site=site, record=record, **state)
    if blocks is not None:
        payload['blocks'] = blocks
    result = subprocess.run(['php', '-r', PHP.replace('WP_SERIALIZER', WP_SERIALIZER_STUB)],
                            cwd=ROOT, input=json.dumps(payload), text=True, capture_output=True, check=True)
    assert result.stderr == ''
    return json.loads(result.stdout)


def block(name, attrs=None, children=None):
    children = children or []
    return {'blockName': name, 'attrs': attrs or {}, 'innerBlocks': children,
            'innerHTML': '', 'innerContent': [None] * len(children) if children else ['\n']}


def binding(name):
    return '$variable(' + json.dumps({'type': 'content', 'value': {'name': 'bioco_event_' + name,
                                                                'settings': {}}}, separators=(',', ':')) + ')$'


def fixture():
    # Exact observed legacy attribute structure, with synthetic URLs/content.
    back = block('divi/button', {
        'button': {'innerContent': {'desktop': {'value': {'text': 'Zurück zu Aktuelles',
            'linkUrl': 'https://staging.example/aktuelles/', 'linkTarget': 'off'}}}},
        'module': {'meta': {'adminLabel': {'desktop': {'value': 'Zurück zu Aktuelles'}}},
                   'decoration': {'spacing': {'desktop': {'value': {'margin': {'top': '48px'}}}}}},
    })
    title = block('divi/heading', {'title': {'innerContent': {'desktop': {'value': binding('title')}}}})
    body = block('divi/text', {'content': {'innerContent': {'desktop': {'value': binding('description')}}}})
    column = block('divi/column', {'module': {'id': 'native-column-id', 'decoration': {
        'sizing': {'desktop': {'value': {'width': '87%'}}}}}}, [title, body, back])
    row = block('divi/row', {'modulePreset': ['authored-row-preset']}, [column])
    return [block('divi/section', {'module': {'meta': {'adminLabel': {'desktop': {'value': 'Authored section'}}}}}, [row])]


def back(blocks):
    return blocks[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][-1]


def merge(target, saved):
    for key, value in saved.items():
        if isinstance(value, dict) and isinstance(target.get(key), dict):
            merge(target[key], value)
        else:
            target[key] = copy.deepcopy(value)


def assert_preserved(before, after):
    for key, value in before.items():
        if isinstance(value, dict):
            assert_preserved(value, after[key])
        else:
            assert after[key] == value


def test_only_the_known_unstyled_button_changes_and_original_attributes_survive():
    blocks = fixture()
    attrs = back(blocks)['attrs']
    attrs['builderVersion'] = '5.11.0'
    attrs['module']['id'] = 'native-button-id'
    attrs['button']['innerContent']['desktop']['value'].update(text='Alle Rückblicke & Termine', linkUrl='/andere-seite/?x=1&y=2')
    attrs['button']['innerContent']['phone'] = {'value': {'text': 'Zurück', 'linkTarget': 'on'}}
    attrs['module']['decoration']['spacing']['desktop']['value']['margin']['top'] = '12px'
    attrs['module']['decoration']['spacing']['phone'] = {'value': {'margin': {'top': '24px'}}}
    result = run(blocks)
    assert result['plan']['changed'] == 1
    assert result['plan']['report'] == [{'path': [0, 0, 0, 2], 'action': 'convert'}]
    expected = copy.deepcopy(result['before_blocks'])
    expected_back = back(expected)
    expected_back['attrs'] = back(result['blocks'])['attrs']
    assert result['blocks'] == expected
    saved = back(result['blocks'])['attrs']
    assert_preserved(attrs, saved)
    assert saved['button']['innerContent'] == attrs['button']['innerContent']
    assert saved['module']['decoration']['spacing'] == attrs['module']['decoration']['spacing']
    assert saved['module']['id'] == 'native-button-id' and saved['builderVersion'] == '5.11.0'
    assert saved['modulePreset'] == ['bioco-5f12512a732ddec5']
    assert back(result['blocks'])['innerContent'] == back(blocks)['innerContent']
    assert run(record=result['plan']['before'] | {'post_content': result['plan']['after_content']})['plan']['changed'] == 0


@pytest.mark.parametrize('design', [
    {'modulePreset': ['native-saved-preset']},
    {'groupPreset': {'button.decoration.button': {'presetId': ['native-group-id'], 'groupName': 'divi/button'}}},
    {'styleIds': ['native-style-id']},
    {'button': {'decoration': {'font': {'font': {'desktop': {'value': {'color': '#2ea3f2'}}}}}}},
    {'button': {'decoration': {'background': {'phone': {'value': {'color': '#123456'}}}}}},
    {'module': {'decoration': {'css': {'desktop': {'value': {'mainElement': 'color:red'}}}}}},
    {'module': {'decoration': {'sizing': {'desktop': {'value': {'width': '60%'}}}}}},
    {'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {'class': 'authored-button'}}}}}},
    {'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {'class': ''}}}}}},
    {'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {'id': 'authored-blue-button'}}}}}},
    {'module': {'advanced': {'htmlAttributes': {'phone': {'value': {'class': 'authored-mobile-button'}}}}}},
])
def test_deliberate_presets_style_ids_and_even_blue_designs_are_preserved(design):
    blocks = fixture()
    merge(back(blocks)['attrs'], design)
    result = run(blocks)
    assert result['plan']['changed'] == 0
    assert result['apply'] == result['restore'] == ''
    assert result['plan']['report'][0]['action'] == 'preserve'
    assert back(result['blocks'])['attrs'] == back(blocks)['attrs']
    assert result['plan']['after_content'] == result['plan']['before']['post_content']


def test_a_matching_label_outside_a_bound_event_body_is_not_adopted():
    blocks = fixture()
    blocks[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['title']['innerContent']['desktop']['value'] = 'A normal heading'
    result = run(blocks)
    assert result['plan']['changed'] == 0 and result['plan']['report'] == []
    assert result['plan']['after_content'] == result['plan']['before']['post_content']


def database(plan):
    db = sqlite3.connect(':memory:')
    # Exercise case/accent conflicts under a representative MySQL CI comparison.
    # This tests the explicit binary guards, not hosted MySQL collation support.
    def normalized(value):
        return ''.join(char for char in unicodedata.normalize('NFD', value.casefold())
                       if not unicodedata.combining(char))
    db.create_collation('MYSQL_CI', lambda left, right:
                        (normalized(left) > normalized(right)) - (normalized(left) < normalized(right)))
    columns = ', '.join('`' + name + '` ' + ('INTEGER PRIMARY KEY' if name == 'ID' else 'TEXT COLLATE MYSQL_CI') for name in plan['before'])
    db.execute('CREATE TABLE posts (' + columns + ')')
    db.execute('INSERT INTO posts VALUES (' + ','.join('?' for _ in plan['before']) + ')', list(plan['before'].values()))
    return db


def execute(db, sql):
    # Translate MySQL's byte comparison to SQLite's explicit binary collation.
    sql = re.sub(r'BINARY (`[^`]+`) = BINARY ', r'\1 COLLATE BINARY = ', sql)
    db.execute(sql)
    return db.execute('SELECT changes()').fetchone()[0]


def test_atomic_apply_and_restore_are_idempotent_and_restore_exact_original_bytes():
    result = run(fixture())
    plan = result['plan']
    db = database(plan)
    before = db.execute('SELECT * FROM posts').fetchone()
    assert execute(db, result['apply']) == 1 and execute(db, result['apply']) == 0
    applied = db.execute('SELECT * FROM posts').fetchone()
    index = list(plan['before']).index('post_content')
    assert applied[index] == plan['after_content']
    assert applied[:index] + applied[index + 1:] == before[:index] + before[index + 1:]
    assert execute(db, result['restore']) == 1 and execute(db, result['restore']) == 0
    assert db.execute('SELECT * FROM posts').fetchone() == before


def test_exported_sql_preserves_literal_percent_values_and_guards():
    blocks = fixture()
    back(blocks)['attrs']['button']['innerContent']['desktop']['value']['text'] = '100% aktuell'
    result = run(blocks, record={'ID': 42, 'post_type': 'et_body_layout', 'post_status': 'draft',
                                'post_title': '100% Ä', 'post_content': '',
                                'post_modified_gmt': '2026-10-12 05:00:00'})
    assert '{wpdb-placeholder-test}' not in result['apply'] + result['restore']
    assert '100%' in result['apply'] and '87%' in result['apply']
    db = database(result['plan'])
    assert execute(db, result['apply']) == 1
    assert db.execute('SELECT post_content FROM posts').fetchone()[0] == result['plan']['after_content']
    assert execute(db, result['restore']) == 1
    assert db.execute('SELECT post_content FROM posts').fetchone()[0] == result['plan']['before']['post_content']


@pytest.mark.parametrize('column,value', [
    ('ID', 43),
    ('post_content', None),
    ('post_content', 'Later editor body'), ('post_content', 'later editor body'),
    ('post_title', "Editor's Detailvorlage A"), ('post_title', "editor's Detailvorlage Ä"),
    ('post_status', 'publish'), ('post_type', 'event'), ('post_modified_gmt', '2026-10-12 05:00:01'),
])
def test_concurrent_edits_block_both_apply_and_restore(column, value):
    result = run(fixture())
    for operation in ['apply', 'restore']:
        db = database(result['plan'])
        if operation == 'restore':
            assert execute(db, result['apply']) == 1
        changed = value if value is not None else db.execute('SELECT post_content FROM posts').fetchone()[0].lower()
        db.execute(f'UPDATE posts SET `{column}`=?', (changed,))
        before = db.execute('SELECT * FROM posts').fetchone()
        assert execute(db, result[operation]) == 0
        assert db.execute('SELECT * FROM posts').fetchone() == before


def test_plan_tampering_site_mixups_and_wrong_record_types_fail_closed():
    result = run(fixture())
    plan = result['plan']
    assert 'error' in run(mode='sql', plan=plan, expected='wrong')
    tampered = plan | {'after_content': 'Overwritten'}
    assert 'error' in run(mode='sql', plan=tampered, expected=plan['fingerprint'])
    assert 'error' in run(fixture(), snapshot_site='https://production.example/')
    assert 'error' in run(record=plan['before'] | {'post_type': 'event'})
    assert 'error' in run(record=plan['before'] | {'unknown_column': 'data'})
