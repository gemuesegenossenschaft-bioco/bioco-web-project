"""Exercise the migration SQL guard against actual SQL rows.

SQLite verifies row conflicts with its binary string comparison. Operator validation
also executes the unchanged MySQL SQL on private temporary tables on the host.
"""
import json
import re
import sqlite3
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]
PHP = r'''
define('ABSPATH','/tmp/site/');define('ARRAY_A','ARRAY_A');
class Database {
    public $posts='posts'; public $postmeta='meta'; public $options='options';
    function prepare($sql,...$args) {
        foreach($args as $arg) $sql=preg_replace('/%[sd]/',"'".str_replace("'","''",(string)$arg)."'",$sql,1);
        return $sql;
    }
    function query($sql) {$GLOBALS['sql']=$sql;return 1;}
    function get_results($sql,$format) {$GLOBALS['queries'][]=$sql;return [];}
}
function clean_post_cache($id) {}
function get_post_meta($id,$key,$single) {
    if($id===1 && $key==='_et_template') return [2];
    return $id===2 && $key==='_et_body_layout_id' ? 3 : '';
}
$wpdb=new Database();
require 'wordpress/web/app/mu-plugins/bioco-import/includes/event-template-migration.php';
$state=json_decode(stream_get_contents(STDIN),true);
try {
    if(($state['mode'] ?? '')==='snapshot') {
        $snapshot=bioco_import_event_template_snapshot(1);
        echo json_encode(['snapshot'=>$snapshot,'queries'=>$GLOBALS['queries']]);
    } elseif(($state['mode'] ?? '')==='shell') {
        echo json_encode(['shell'=>bioco_import_event_template_shell($state)]);
    } elseif(isset($state['mode'])) {
        bioco_import_event_template_assignment($state,10,$state['mode']==='remove');
        echo json_encode(['sql'=>$GLOBALS['sql']]);
    } else echo json_encode(['guard'=>bioco_import_event_template_guard($state)]);
}
catch(Throwable $error){echo json_encode(['error'=>$error->getMessage()]);}
'''


def guard(state):
    result = subprocess.run(['php', '-r', PHP], cwd=ROOT, input=json.dumps(state),
                            capture_output=True, text=True, check=True)
    assert result.stderr == ''
    return json.loads(result.stdout)


def database():
    db = sqlite3.connect(':memory:')
    db.row_factory = sqlite3.Row
    db.execute('CREATE TABLE posts (ID INTEGER PRIMARY KEY, post_type TEXT, post_status TEXT, post_title TEXT, post_content TEXT)')
    db.execute('CREATE TABLE meta (meta_id INTEGER PRIMARY KEY, post_id INTEGER, meta_key TEXT, meta_value TEXT)')
    db.execute('CREATE TABLE options (option_name TEXT, option_value TEXT)')
    db.executemany('INSERT INTO posts VALUES (?,?,?,?,?)', [
        (1, 'et_theme_builder', 'publish', 'Theme Builder', ''),
        (2, 'et_template', 'publish', 'Default', ''),
        (3, 'et_body_layout', 'publish', 'Body', 'Editor Inhalt Ä'),
    ])
    db.executemany('INSERT INTO meta VALUES (?,?,?,?)', [
        (1, 1, '_et_template', '2'), (2, 2, '_et_default', '1'),
        (3, 2, '_et_body_layout_id', '3'), (4, 3, '_et_pb_use_builder', 'on'),
    ])
    return db


def snapshot(db):
    captured = guard({'mode': 'snapshot'})
    state = captured['snapshot']
    for key, query in zip(['posts', 'meta'], captured['queries'], strict=True):
        state[key] = [dict(r) for r in db.execute(re.sub(r'\bBINARY\s+', '', query))]
    return state


def append(db, state):
    sql = re.sub(r'\bBINARY\s+', '', guard(state | {'mode': 'append'})['sql']).replace(' FROM DUAL ', ' ')
    db.execute(sql)
    return db.execute('SELECT changes()').fetchone()[0]


def remove(db, state):
    sql = re.sub(r'\bBINARY\s+', '', guard(state | {'mode': 'remove'})['sql'])
    db.execute(sql.replace('LIMIT 18446744073709551615', 'LIMIT -1'))
    return db.execute('SELECT changes()').fetchone()[0]


def test_unchanged_registry_appends_one_assignment_without_replacing_any_rows():
    db = database()
    before = snapshot(db)
    assert append(db, before) == 1
    assert snapshot(db)['posts'] == before['posts']
    assert snapshot(db)['meta'][:-1] == before['meta']
    assert append(db, before) == 0  # The same captured registry cannot append twice.


@pytest.mark.parametrize('edit', [
    "UPDATE posts SET post_content='Editor inhalt Ä' WHERE ID=3",
    "UPDATE posts SET post_content='Editor Inhalt A' WHERE ID=3",
    "UPDATE posts SET post_title='Neue Vorlage' WHERE ID=2",
    "UPDATE posts SET post_status='draft' WHERE ID=3",
    "DELETE FROM posts WHERE ID=3",
    "UPDATE meta SET meta_value='off' WHERE meta_id=4",
    "UPDATE meta SET meta_key='_ET_default' WHERE meta_id=2",
    "DELETE FROM meta WHERE meta_id=3",
    "INSERT INTO meta VALUES (5,1,'_et_template','9')",
    "INSERT INTO meta VALUES (5,3,'editor_field','saved')",
    "INSERT INTO options VALUES ('et_tb_templates_backup_1','a:0:{}')",
])
def test_concurrent_content_or_assignment_edits_refuse_the_write(edit):
    db = database()
    before = snapshot(db)
    db.execute(edit)
    edited = snapshot(db)
    assert append(db, before) == 0
    assert snapshot(db) == edited


def test_unrelated_records_do_not_block_or_get_changed_by_the_assignment():
    db = database()
    state = snapshot(db)
    db.execute("INSERT INTO posts VALUES (99,'event','publish','Gonis neuer Anlass','Neuer Inhalt')")
    db.execute("INSERT INTO meta VALUES (99,99,'event_date','2027-04-30 14:00:00')")
    assert append(db, state) == 1
    assert db.execute('SELECT post_content FROM posts WHERE ID=99').fetchone()[0] == 'Neuer Inhalt'
    assert db.execute('SELECT meta_value FROM meta WHERE post_id=99').fetchone()[0] == '2027-04-30 14:00:00'


def test_guard_rejects_missing_records_or_unexpected_columns():
    db = database()
    state = snapshot(db)
    state['posts'].pop()
    assert guard(state)['error'] == 'Missing template or layout record.'
    state = snapshot(db)
    state['posts'][0]['bad_column'] = 'x'
    assert guard(state)['error'] == 'Invalid snapshot column.'


def test_restoration_removes_only_our_assignment_and_refuses_a_later_edit():
    db = database()
    before = snapshot(db)
    assert append(db, before) == 1
    applied = snapshot(db)
    db.execute("UPDATE posts SET post_content='New editorial body' WHERE ID=3")
    assert remove(db, applied) == 0
    assert db.execute("SELECT COUNT(*) FROM meta WHERE meta_value='10'").fetchone()[0] == 1
    db.execute("UPDATE posts SET post_content='Editor Inhalt Ä' WHERE ID=3")
    assert remove(db, applied) == 1
    assert snapshot(db) == before
    assert remove(db, applied) == 0


@pytest.mark.parametrize('refresh', [
    "DELETE FROM meta WHERE meta_key='_divi_dynamic_assets_canvases_used'",
    "UPDATE meta SET meta_value='new generated assets' WHERE meta_id=8",
    "INSERT INTO meta VALUES (9,2,'_divi_dynamic_assets_canvases_used','new generated assets')",
])
def test_native_asset_cache_changes_do_not_block_assignment_or_restoration(refresh):
    db = database()
    db.execute("INSERT INTO meta VALUES (8,3,'_divi_dynamic_assets_canvases_used','generated assets')")
    before = snapshot(db)
    assert all(row['meta_key'] != '_divi_dynamic_assets_canvases_used' for row in before['meta'])
    db.execute(refresh)
    assert append(db, before) == 1
    applied = snapshot(db)
    db.execute("DELETE FROM meta WHERE meta_key='_divi_dynamic_assets_canvases_used'")
    assert remove(db, applied) == 1
    assert snapshot(db) == before


def test_only_the_exact_generated_cache_key_is_exempt():
    db = database()
    state = snapshot(db)
    db.execute("INSERT INTO meta VALUES (8,3,'_divi_dynamic_assets_canvases_used_editor','Authored setting')")
    assert append(db, state) == 0


def shell_state():
    state = snapshot(database())
    state['posts'].extend([
        dict(ID=4, post_type='et_header_layout', post_status='publish'),
        dict(ID=5, post_type='et_footer_layout', post_status='publish'),
    ])
    state['meta'].extend([
        dict(post_id=2, meta_key='_et_enabled', meta_value='1'),
        dict(post_id=2, meta_key='_et_header_layout_id', meta_value='4'),
        dict(post_id=2, meta_key='_et_header_layout_enabled', meta_value='1'),
        dict(post_id=2, meta_key='_et_footer_layout_id', meta_value='5'),
        dict(post_id=2, meta_key='_et_footer_layout_enabled', meta_value='1'),
    ])
    return state


def test_event_assignment_reuses_the_saved_default_shell():
    state = shell_state()
    assert guard(state | {'mode': 'shell'}) == {
        'shell': {'header': {'id': 4, 'enabled': True}, 'footer': {'id': 5, 'enabled': True}}}
    # Explicitly disabled and empty slots are editorial choices, not defaults to replace.
    for row in state['meta']:
        if row['meta_key'] == '_et_footer_layout_enabled':
            row['meta_value'] = '0'
        if row['meta_key'] == '_et_header_layout_id':
            row['meta_value'] = '0'
    assert guard(state | {'mode': 'shell'})['shell'] == {
        'header': {'id': 0, 'enabled': True}, 'footer': {'id': 5, 'enabled': False}}


@pytest.mark.parametrize('change', ['disabled_default', 'draft_header', 'wrong_footer_type', 'missing_header', 'duplicate_slot', 'missing_default'])
def test_invalid_default_shell_refuses_migration(change):
    state = shell_state()
    if change == 'disabled_default':
        next(row for row in state['meta'] if row['meta_key'] == '_et_enabled')['meta_value'] = '0'
    elif change == 'draft_header':
        state['posts'][-2]['post_status'] = 'draft'
    elif change == 'wrong_footer_type':
        state['posts'][-1]['post_type'] = 'et_body_layout'
    elif change == 'missing_header':
        state['posts'].pop(-2)
    elif change == 'duplicate_slot':
        state['meta'].append(dict(post_id=2, meta_key='_et_footer_layout_id', meta_value='5'))
    else:
        state['templates'] = []
    assert 'error' in guard(state | {'mode': 'shell'})


def test_native_template_creation_receives_captured_shell_ids(tmp_path):
    site = tmp_path / 'site'
    site.mkdir()
    backups = tmp_path / 'backups'
    backups.mkdir(mode=0o700)
    receipt = backups / 'before.json'
    state = shell_state()
    harness = r'''
define('ABSPATH', $argv[1]);
function current_user_can($cap) { return true; }
function wp_json_encode($value) { return json_encode($value); }
function wp_slash($value) { return $value; }
function is_wp_error($value) { return false; }
function et_theme_builder_insert_layout($args) { return 9; }
function et_theme_builder_store_template($builder, $template, $default) {
    $GLOBALS['native_template'] = $template;
    throw new RuntimeException('Stop after capturing native arguments.');
}
require 'wordpress/web/app/mu-plugins/bioco-import/includes/event-template-migration.php';
$state = json_decode(stream_get_contents(STDIN), true);
$plan = ['state'=>$state, 'shell'=>bioco_import_event_template_shell($state),
    'content'=>'Native event body', 'existing'=>0, 'fingerprint'=>'confirmed'];
try { bioco_import_event_template_apply($plan, 'confirmed', $argv[2]); }
catch (RuntimeException $error) {
    if ($error->getMessage() !== 'Stop after capturing native arguments.') throw $error;
}
echo json_encode($GLOBALS['native_template']);
'''
    result = subprocess.run(['php', '-r', harness, str(site) + '/', str(receipt)],
                            cwd=ROOT, input=json.dumps(state), capture_output=True, text=True, check=True)
    assert result.stderr == ''
    assert json.loads(result.stdout)['layouts'] == {
        'header': {'id': 4, 'enabled': True}, 'body': {'id': 9, 'enabled': True},
        'footer': {'id': 5, 'enabled': True}}
