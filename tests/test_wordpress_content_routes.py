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


def test_primary_navigation_contract_lives_outside_the_theme():
    header = (
        ROOT / "wordpress/web/app/themes/bioco/parts/header.html"
    ).read_text()
    navigation_php = (
        ROOT / "wordpress/web/app/mu-plugins/bioco-core/includes/navigation.php"
    ).read_text()
    intended = json.loads((
        ROOT / "wordpress/web/app/mu-plugins/bioco-core/content/navigation.json"
    ).read_text())

    assert [item["label"] for item in intended["primary"]] == [
        "Wir", "Gemüse", "Mitmachen", "Abos", "Aktuelles",
    ]
    assert intended["cta"]["label"] == "BIOCÒ WERDEN"
    assert all(
        item["label"] not in navigation_php
        for item in intended["utility"] + intended["primary"] + [intended["cta"]]
    )
    php = r'''
    define('ABSPATH', __DIR__);
    function home_url($path) { return 'https://staging.example' . $path; }
    function plugins_url($path, $plugin = null) { return 'https://staging.example/wp-content/mu-plugins/bioco-core/' . $path; }
    function esc_url($value) { return $value; }
    function esc_attr($value) { return $value; }
    function esc_html($value) { return $value; }
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/navigation.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/site-wiring.php';
    echo json_encode([
        'contract' => bioco_navigation_contract(),
        'import_slugs' => bioco_import_primary_nav_slugs(),
        'import_labels' => bioco_import_nav_labels(),
        'rendered' => bioco_render_primary_navigation(),
    ]);
    '''
    contract = json.loads(subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout)

    assert header.count("<!-- wp:bioco/primary-navigation /-->") == 1
    assert "wp:navigation-link" not in header
    menu_items = intended["primary"] + [intended["cta"]]
    assert contract["contract"] == intended
    assert contract["import_slugs"] == [item["slug"] for item in menu_items]
    assert contract["import_labels"] == {
        item["slug"]: item["label"] for item in menu_items
    }
    assert 'class="bioco-utility-nav"' in contract["rendered"]
    assert 'class="bioco-primary-nav"' in contract["rendered"]
    assert 'class="bioco-logo"' in contract["rendered"]
    assert 'class="bioco-primary-cta"' in contract["rendered"]

    core = (
        ROOT / "wordpress/web/app/mu-plugins/bioco-core/bioco-core.php"
    ).read_text()
    assert "register_block_type('bioco/primary-navigation'" in core
    assert "'render_callback' => 'bioco_render_primary_navigation'" in core
