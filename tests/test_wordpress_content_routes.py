import json
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]


def next_redirects():
    config = (ROOT / "frontend/next.config.js").read_text()
    redirect_block = config.split("async redirects()", 1)[1]
    return [
        {
            "source": source,
            "destination": destination,
            "permanent": permanent == "true",
        }
        for source, destination, permanent in re.findall(
            r"source:\s*'([^']+)'\s*,\s*"
            r"destination:\s*'([^']+)'\s*,\s*"
            r"permanent:\s*(true|false)",
            redirect_block,
        )
    ]


def test_wordpress_redirect_contract_matches_next_config_exactly():
    wordpress_redirects = json.loads((
        ROOT / "wordpress/web/app/mu-plugins/bioco-core/content/redirects.json"
    ).read_text())
    frontend_redirects = next_redirects()
    core = (
        ROOT / "wordpress/web/app/mu-plugins/bioco-core/bioco-core.php"
    ).read_text()

    assert len(frontend_redirects) == 14
    assert wordpress_redirects == frontend_redirects
    assert "require_once BIOCO_CORE_DIR . '/includes/redirects.php';" in core


def test_wordpress_redirects_are_permanent_and_normalize_encoded_paths():
    sources = [rule["source"] for rule in next_redirects()]
    php = r'''
    define('ABSPATH', __DIR__);
    $requests = json_decode($argv[1], true);
    $results = [];
    function add_action($hook, $callback) {}
    function home_url($path) { return 'https://example.test' . $path; }
    function wp_safe_redirect($location, $status) {
        throw new Exception(json_encode([$location, $status]));
    }
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/redirects.php';
    foreach ($requests as $request) {
        $_SERVER['REQUEST_URI'] = $request;
        try {
            bioco_handle_permanent_redirects();
            $results[$request] = null;
        } catch (Exception $exception) {
            $results[$request] = json_decode($exception->getMessage(), true);
        }
    }
    echo json_encode($results);
    '''
    requests = sources + [
        "/ernte/",
        "/wp-content/uploads/2017/07/1704_Gem%C3%BCseabo.pdf",
        "/wp-content/uploads/2017/07/1704_Gem%C3%BCseabo.pdf/",
        "/",
        "/not-in-the-redirect-map",
    ]
    result = subprocess.run(
        ["php", "-r", php, json.dumps(requests)],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    redirects = json.loads(result)

    expected = {rule["source"]: rule["destination"] for rule in next_redirects()}
    for source, destination in expected.items():
        assert redirects[source] == [f"https://example.test{destination}", 301]
    assert redirects["/wp-content/uploads/2017/07/1704_Gem%C3%BCseabo.pdf"] == [
        "https://example.test/abos",
        301,
    ]
    assert redirects["/ernte/"] == ["https://example.test/gemuese", 301]
    assert redirects["/wp-content/uploads/2017/07/1704_Gem%C3%BCseabo.pdf/"] == [
        "https://example.test/abos",
        301,
    ]
    assert redirects["/"] is None
    assert redirects["/not-in-the-redirect-map"] is None


def test_wordpress_redirects_preserve_nonempty_query_strings_only():
    php = r'''
    define('ABSPATH', __DIR__);
    $requests = json_decode($argv[1], true);
    $results = [];
    function add_action($hook, $callback) {}
    function home_url($path) { return 'https://example.test' . $path; }
    function wp_safe_redirect($location, $status) {
        throw new Exception(json_encode([$location, $status]));
    }
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/redirects.php';
    foreach ($requests as $request) {
        $_SERVER['REQUEST_URI'] = $request;
        try {
            bioco_handle_permanent_redirects();
            $results[$request] = null;
        } catch (Exception $exception) {
            $results[$request] = json_decode($exception->getMessage(), true);
        }
    }
    echo json_encode($results);
    '''
    requests = ["/ernte", "/ernte?", "/ernte?utm_source=newsletter"]
    redirects = json.loads(subprocess.run(
        ["php", "-r", php, json.dumps(requests)],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout)

    assert redirects["/ernte"] == ["https://example.test/gemuese", 301]
    assert redirects["/ernte?"] == ["https://example.test/gemuese", 301]
    assert redirects["/ernte?utm_source=newsletter"] == [
        "https://example.test/gemuese?utm_source=newsletter",
        301,
    ]


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
