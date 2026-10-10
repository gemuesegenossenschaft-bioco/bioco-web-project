"""Exercise the real CLI fixture helper with fake WordPress storage boundaries.

Keep/Replace/Remove map: tests/README.md. Published content is never a fixture.
"""
import json
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]
SCRIPT = ROOT / 'wordpress/scripts/editor-verification-fixtures.php'

HARNESS = r'''
define('WP_CLI', true);
$state = json_decode(getenv('BIOCO_FIXTURE_STATE'), true);
$args = $state['args'];
$events = [];
class WP_CLI {
    static function line($text) { $GLOBALS['result'] = json_decode($text, true); }
    static function error($text) { throw new RuntimeException($text); }
}
function get_posts($query) {
    $posts = [];
    foreach ($GLOBALS['state']['posts'] as $post) {
        if (!in_array($post['post_type'], $query['post_type'], true)) continue;
        if (isset($query['meta_key']) && ($GLOBALS['state']['meta'][$post['ID']][$query['meta_key']][0] ?? '') !== $query['meta_value']) continue;
        $posts[] = (object) $post;
    }
    return $posts;
}
function get_post($id) {
    foreach ($GLOBALS['state']['posts'] as $post) if ($post['ID'] === $id) return (object) $post;
    return null;
}
function get_post_meta($id) { return $GLOBALS['state']['meta'][$id] ?? []; }
function wp_json_encode($value) { return json_encode($value); }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function get_post_stati() { return ['publish' => 'publish', 'draft' => 'draft', 'auto-draft' => 'auto-draft', 'trash' => 'trash']; }
function wp_insert_post($value, $error) { $GLOBALS['events'][] = ['insert', wp_unslash($value)]; return 900; }
function is_wp_error($value) { return false; }
function maybe_unserialize($value) {
    if (!is_string($value)) return $value;
    $decoded = @unserialize($value);
    return $decoded === false ? $value : $decoded;
}
function add_post_meta($id, $key, $value) { $GLOBALS['events'][] = ['meta', $key, wp_unslash($value)]; return 1; }
function get_object_taxonomies($type) { return []; }
function wp_delete_post($id, $force) { $GLOBALS['events'][] = ['delete', $id, $force]; return true; }
try { include getenv('BIOCO_FIXTURE_SCRIPT'); }
catch (RuntimeException $error) { $GLOBALS['result'] = ['error' => $error->getMessage()]; }
echo json_encode(['result' => $GLOBALS['result'] ?? null, 'events' => $events]);
'''


def post(id=5, type='page', status='publish', title='Original'):
    return dict(ID=id, post_type=type, post_status=status, post_title=title,
                post_name='original', post_content=r'<!-- wp:divi/heading {"text":"\u003cp\u003eText\u003c/p\u003e"} /-->',
                post_excerpt='', post_parent=0, menu_order=0)


def run(args, posts=None, meta=None):
    import os
    state = {'args': args, 'posts': [post()] if posts is None else posts, 'meta': meta or {}}
    result = subprocess.run(['php', '-r', HARNESS], check=True, capture_output=True,
                            text=True, env=os.environ | {
                                'BIOCO_FIXTURE_STATE': json.dumps(state),
                                'BIOCO_FIXTURE_SCRIPT': str(SCRIPT),
                            })
    return json.loads(result.stdout)


def test_copy_is_draft_and_preserves_builder_metadata():
    result = run(['create', '5', 'qa-20261009'], meta={'5': {
        '_et_pb_use_builder': ['on'], '_edit_lock': ['1:2'],
        '_bioco_editor_verification': ['previous-run'],
        'structured': ['a:1:{s:4:"rows";a:0:{}}'],
    }})
    inserted = result['events'][0][1]
    assert inserted['post_status'] == 'draft'
    assert inserted['post_parent'] == 0
    assert inserted['post_content'] == post()['post_content']
    assert inserted['meta_input']['_bioco_editor_verification'] == 'qa-20261009'
    assert ['meta', '_et_pb_use_builder', 'on'] in result['events']
    assert ['meta', 'structured', {'rows': []}] in result['events']
    assert not any(event[0] == 'meta' and event[1] in (
        '_edit_lock', '_bioco_editor_verification') for event in result['events'])


def test_global_assignment_can_never_be_duplicated_as_fixture():
    result = run(['create', '5', 'qa-20261009'], posts=[post(type='et_template')])
    assert 'never a global assignment' in result['result']['error']
    assert result['events'] == []


@pytest.mark.parametrize('type', ['event', 'group'])
def test_registered_record_types_are_inventoried_and_copied(type):
    assert run(['inventory'], posts=[post(type=type)])['result'][0]['type'] == type
    result = run(['create', '5', 'qa-20261009'], posts=[post(type=type)])
    assert result['events'][0][1]['post_type'] == type


def test_cleanup_validates_all_candidates_before_deleting_anything():
    title = 'BIOCO QA qa-20261009 '
    result = run(['cleanup', 'qa-20261009'], posts=[
        post(id=5, status='draft', title=title + '5'),
        post(id=6, status='publish', title=title + '6'),
    ], meta={str(id): {'_bioco_editor_verification': ['qa-20261009']} for id in (5, 6)})
    assert 'Refusing to delete' in result['result']['error']
    assert result['events'] == []


def test_cleanup_deletes_only_tagged_recognized_drafts():
    result = run(['cleanup', 'qa-20261009'], posts=[
        post(id=5, status='draft', title='BIOCO QA qa-20261009 5'),
        post(id=6, status='draft', title='Unrelated draft'),
        post(id=7, status='publish'),
    ], meta={'5': {'_bioco_editor_verification': ['qa-20261009']}})
    assert result['events'] == [['delete', 5, True]]


def test_inventory_covers_public_content_and_global_assignments():
    result = run(['inventory'], posts=[post(), post(id=6, status='draft'),
                                      post(id=7, type='et_template', status='draft')])
    assert [row['id'] for row in result['result']] == [5, 7]
    assert result['events'] == []


def test_inventory_detects_editorial_metadata_but_ignores_edit_locks():
    baseline = run(['inventory'], meta={'5': {'_et_pb_use_builder': ['on']}})['result']
    locked = run(['inventory'], meta={'5': {'_et_pb_use_builder': ['on'],
                                          '_edit_lock': ['1:2']}})['result']
    changed = run(['inventory'], meta={'5': {'_et_pb_use_builder': ['off']}})['result']
    assert baseline == locked
    assert baseline != changed


def test_inventory_keeps_cache_evidence_separate_from_editorial_changes():
    baseline = run(['inventory'], meta={'5': {'_et_pb_use_builder': ['on']}})['result'][0]
    cached = run(['inventory'], meta={'5': {'_et_pb_use_builder': ['on'],
        '_divi_dynamic_assets_canvases_used': ['compiled']}})['result'][0]
    changed = run(['inventory'], meta={'5': {'_et_pb_use_builder': ['on'],
        '_divi_off_canvas_data': ['edited canvas']}})['result'][0]
    assert baseline['editorial_hash'] == cached['editorial_hash']
    assert baseline['hash'] != cached['hash']
    assert '_divi_dynamic_assets_canvases_used' in cached['meta_hashes']
    assert baseline['editorial_hash'] != changed['editorial_hash']
