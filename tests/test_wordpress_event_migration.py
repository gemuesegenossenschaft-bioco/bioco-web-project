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
define('ABSPATH','/tmp/site/');
class Database {
    public $posts='posts'; public $postmeta='meta'; public $options='options';
    function prepare($sql,...$args) {
        foreach($args as $arg) $sql=preg_replace('/%[sd]/',"'".str_replace("'","''",(string)$arg)."'",$sql,1);
        return $sql;
    }
    function query($sql) {$GLOBALS['sql']=$sql;return 1;}
}
function clean_post_cache($id) {}
$wpdb=new Database();
require 'wordpress/web/app/mu-plugins/bioco-import/includes/event-template-migration.php';
$state=json_decode(stream_get_contents(STDIN),true);
try {
    if(isset($state['mode'])) {
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
    return dict(builder=1, ids=[1, 2, 3], templates=[2],
                posts=[dict(r) for r in db.execute('SELECT * FROM posts ORDER BY ID')],
                meta=[dict(r) for r in db.execute('SELECT * FROM meta ORDER BY meta_id')])


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
