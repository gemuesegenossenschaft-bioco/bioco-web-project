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


def test_theme_font_sources_are_resolvable():
    theme_dir = ROOT / "wordpress/web/app/themes/bioco"
    theme = json.loads((theme_dir / "theme.json").read_text())
    families = theme["settings"]["typography"]["fontFamilies"]

    for family in families:
        for face in family.get("fontFace", []):
            for source in face.get("src", []):
                if source.startswith("file:./"):
                    assert (theme_dir / source.removeprefix("file:./")).is_file()
                else:
                    assert source.startswith("https://")

    assert (theme_dir / "assets/fonts/OFL.txt").is_file()
