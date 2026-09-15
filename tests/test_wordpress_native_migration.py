"""Native migration refuses to overwrite a concurrent editorial save."""
import json
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]


@pytest.mark.parametrize('updated,expected,error', [
    (1, ['update', 'cache', 'revision'], None),
    (0, ['update'], 'Concurrent edit: kontakt'),
    (False, ['update'], 'Cannot save migrated page: kontakt'),
])
def test_atomic_save_preserves_concurrent_edits(updated, expected, error):
    code = r'''
    define('ABSPATH', __DIR__);
    $updated = json_decode($argv[1]);
    $calls = [];
    class FakeDatabase {
        public $posts = 'wp_posts';
        function prepare($sql, ...$args) {
            if (!str_contains($sql, 'WHERE ID = %d AND BINARY post_content = BINARY %s')) throw new Exception('Missing atomic comparison');
            if ($args !== ["new 'content'", 'local-time', 'utc-time', 42, 'original']) throw new Exception('Invalid update');
            return $sql;
        }
        function query($sql) { $GLOBALS['calls'][] = 'update'; return $GLOBALS['updated']; }
    }
    $wpdb = new FakeDatabase();
    function current_time($format, $gmt=false) { return $gmt ? 'utc-time' : 'local-time'; }
    function clean_post_cache($id) { $GLOBALS['calls'][] = 'cache'; }
    function wp_revisions_enabled($page) { return true; }
    function _wp_put_post_revision($page) {
        if ($page->post_content !== 'original') throw new Exception('Lost original revision');
        $GLOBALS['calls'][] = 'revision'; return 43;
    }
    function is_wp_error($value) { return false; }
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/native-migration.php';
    try { bioco_import_native_save((object)['ID'=>42, 'post_content'=>'original', 'post_name'=>'kontakt'], "new 'content'"); }
    catch (Throwable $error) { $message = $error->getMessage(); }
    echo json_encode(['calls'=>$calls, 'error'=>$message ?? null]);
    '''
    result = subprocess.run(['php', '-r', code, json.dumps(updated)], cwd=ROOT, capture_output=True, text=True, check=True)
    assert json.loads(result.stdout) == {'calls': expected, 'error': error}
