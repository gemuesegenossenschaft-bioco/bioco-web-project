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
    public $last_error='';
    private $rows;
    function __construct($rows) {$this->rows=$rows;}
    function get_results($sql, $format) {
        if (!preg_match('/SELECT \\* FROM ([a-z_]+) ORDER BY (.+) LIMIT (\\d+) OFFSET (\\d+)/', $sql, $match)) {
            throw new Exception('Unexpected query');
        }
        $this->last_error='';
        if (($this->rows['__error_table'] ?? null) === $match[1]) {
            $this->last_error='synthetic database error';
            return [];
        }
        $rows=$this->rows[$match[1]] ?? [];
        if ($match[1] === 'term_relationships') {
            if ($match[2] !== 'object_id, term_taxonomy_id') throw new Exception('Wrong relationship order');
            usort($rows, fn($a,$b) => ($a['object_id'] <=> $b['object_id'])
                ?: ($a['term_taxonomy_id'] <=> $b['term_taxonomy_id']));
        }
        return array_slice($rows, (int)$match[4], (int)$match[3]);
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
        {'option_name': 'bioco_pricing_enabled', 'option_value': '0'},
        {'option_name': 'bioco_share_price', 'option_value': '250'},
        {'option_name': 'bioco_divi_form_message_groups', 'option_value': '[]'},
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


def test_large_table_matches_single_pass_digest(tmp_path):
    rows = {table: [] for table in TABLES}
    rows['posts'] = [
        {'ID': index, 'post_type': 'bioco_depot', 'post_status': 'publish',
         'post_content': f'Depot {index}'} for index in range(1, 1004)
    ]
    rows['term_relationships'] = [
        {'object_id': index // 3, 'term_taxonomy_id': index % 3}
        for index in reversed(range(1, 1004))
    ]
    fixture = tmp_path / 'rows.json'
    fixture.write_text(json.dumps(rows))
    reference = '''
    $rows=json_decode(file_get_contents($argv[1]),true);
    $hash=hash_init('sha256');
    foreach (['posts','postmeta','terms','term_taxonomy','term_relationships','termmeta','options'] as $table) {
        hash_update($hash,$table."\\n");
        foreach ($rows[$table] as $row) hash_update($hash,serialize($row));
    }
    echo hash_final($hash);
    '''
    reference_rows = copy.deepcopy(rows)
    reference_rows['term_relationships'].sort(
        key=lambda row: (row['object_id'], row['term_taxonomy_id']))
    fixture.write_text(json.dumps(reference_rows))
    expected = subprocess.run(['php', '-r', reference, str(fixture)],
                              check=True, capture_output=True, text=True).stdout
    assert digest(tmp_path, rows) == expected


def test_empty_result_with_database_error_aborts_fingerprint(tmp_path):
    rows = base_rows() | {'__error_table': 'terms'}
    fixture = tmp_path / 'rows.json'
    fixture.write_text(json.dumps(rows))
    result = subprocess.run(['php', '-r', HARNESS + SOURCE.read_text().removeprefix('<?php'),
                             str(fixture)], text=True, capture_output=True)
    assert result.returncode != 0
    assert 'Could not read editorial table terms' in result.stderr
    assert len(result.stdout) != 64


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
    ('options', 3, 'option_value', '1'),
    ('options', 4, 'option_value', '300'),
    ('options', 5, 'option_value', '["contact-form"]'),
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
