"""Execute the same PHP digest used by production promotion against row fixtures."""

import copy
import json
import subprocess
from pathlib import Path

import pytest


SOURCE = Path(__file__).resolve().parents[1] / 'wordpress/scripts/editorial-fingerprint.php'
TABLES = ('posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'options')
HARNESS = '''
define('ARRAY_A', 'ARRAY_A');
class FakeDB {
    public $posts='posts', $postmeta='postmeta', $terms='terms';
    public $term_taxonomy='term_taxonomy', $term_relationships='term_relationships';
    public $termmeta='termmeta', $options='options';
    private $rows;
    function __construct($rows) {$this->rows=$rows;}
    function get_results($sql, $format) {
        if (!preg_match('/SELECT \\* FROM ([a-z_]+) ORDER BY /', $sql, $match)) {
            throw new Exception('Unexpected query');
        }
        return $this->rows[$match[1]] ?? null;
    }
}
$wpdb=new FakeDB(json_decode(file_get_contents($argv[1]), true));
'''


def base_rows():
    rows = {table: [] for table in TABLES}
    rows['posts'] = [
        {'ID': 1, 'post_type': 'page', 'post_status': 'publish', 'post_content': 'Page'},
        {'ID': 2, 'post_type': 'bioco_depot', 'post_status': 'publish', 'post_content': 'Depot'},
        {'ID': 3, 'post_type': 'event', 'post_status': 'draft', 'post_content': 'Draft'},
        {'ID': 4, 'post_type': 'page', 'post_status': 'trash', 'post_content': 'Trash'},
        {'ID': 5, 'post_type': 'attachment', 'post_status': 'inherit', 'post_content': 'Image'},
    ]
    rows['postmeta'] = [
        {'meta_id': 1, 'post_id': 2, 'meta_key': 'public_address', 'meta_value': 'Before'},
        {'meta_id': 2, 'post_id': 2, 'meta_key': '_edit_lock', 'meta_value': 'old'},
    ]
    rows['terms'] = [{'term_id': 1, 'name': 'Region'}]
    rows['term_taxonomy'] = [{'term_taxonomy_id': 1, 'term_id': 1, 'taxonomy': 'region'}]
    rows['term_relationships'] = [{'object_id': 2, 'term_taxonomy_id': 1}]
    rows['termmeta'] = [{'meta_id': 1, 'term_id': 1, 'meta_key': 'caption', 'meta_value': 'Before'}]
    rows['options'] = [
        {'option_name': 'theme_mods_bioco-divi', 'option_value': 'Before'},
        {'option_name': 'bioco_form_messages', 'option_value': 'Before'},
        {'option_name': 'bioco_release_marker', 'option_value': 'Before'},
    ]
    return rows


def digest(tmp_path, rows):
    fixture = tmp_path / 'rows.json'
    fixture.write_text(json.dumps(rows))
    result = subprocess.run(['php', '-r', HARNESS + SOURCE.read_text().removeprefix('<?php'),
                             str(fixture)], text=True, capture_output=True)
    assert result.returncode == 0, result.stderr
    assert len(result.stdout) == 64
    return result.stdout


@pytest.mark.parametrize('table,index,key,value', [
    ('posts', 1, 'post_content', 'Updated depot'),
    ('posts', 2, 'post_content', 'Updated draft'),
    ('posts', 3, 'post_content', 'Updated trash'),
    ('posts', 4, 'post_content', 'Updated attachment'),
    ('postmeta', 0, 'meta_value', 'Updated address'),
    ('terms', 0, 'name', 'Updated term'),
    ('term_relationships', 0, 'term_taxonomy_id', 2),
    ('termmeta', 0, 'meta_value', 'Updated taxonomy meta'),
    ('options', 0, 'option_value', 'Updated theme settings'),
    ('options', 1, 'option_value', 'Updated form copy'),
])
def test_editorial_fingerprint_catches_owned_data_changes(tmp_path, table, index, key, value):
    original = base_rows()
    changed = copy.deepcopy(original)
    changed[table][index][key] = value
    assert digest(tmp_path, changed) != digest(tmp_path, original)


@pytest.mark.parametrize('table,index,key,value', [
    ('postmeta', 1, 'meta_value', 'new lock'),
    ('options', 2, 'option_value', 'new marker'),
])
def test_editorial_fingerprint_ignores_only_known_release_noise(tmp_path, table, index, key, value):
    original = base_rows()
    changed = copy.deepcopy(original)
    changed[table][index][key] = value
    assert digest(tmp_path, changed) == digest(tmp_path, original)
