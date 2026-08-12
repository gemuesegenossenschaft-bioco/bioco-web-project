import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]


def test_event_singles_keep_aktuelles_prefix_without_shadowing_page():
    php = r'''
    define('ABSPATH', __DIR__);
    $registered = [];
    function add_action($hook, $callback) { $callback(); }
    function register_post_type($name, $args) {
        global $registered;
        $registered[$name] = $args;
    }
    function __($text, $domain = null) { return $text; }
    require 'wordpress/web/app/mu-plugins/bioco-content/bioco-content.php';
    echo json_encode($registered['event']);
    '''
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    event = json.loads(result)

    assert event["has_archive"] is False
    assert event["rewrite"] == {"slug": "aktuelles", "with_front": False}
