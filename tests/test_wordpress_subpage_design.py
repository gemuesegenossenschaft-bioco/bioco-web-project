import json
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"
THEME = ROOT / "wordpress/web/app/themes/bioco"


def run_php(source):
    return subprocess.run(
        ["php", "-r", source], cwd=ROOT, text=True, capture_output=True, check=True
    ).stdout


def test_every_seed_has_one_data_owned_page_heading():
    for path in sorted((ROOT / "wordpress/content-seed").glob("*.json")):
        seed = json.loads(path.read_text())
        if seed["slug"] == "home":
            assert seed["hero"]["hero_title"]
            continue
        page_headings = [
            section for section in seed["sections"]
            if section.get("section_component") == "page_intro"
            and section.get("section_config", {}).get("headingLevel") == "1"
        ]
        assert len(page_headings) == 1, path.name
        assert page_headings[0].get("section_title"), path.name
        assert not re.search(
            r"<h[1-6]\b", page_headings[0].get("section_text", ""), re.IGNORECASE
        ), path.name
        assert all("<h1" not in section.get("section_text", "").lower()
                   for section in seed["sections"]), path.name

    aktuelles = json.loads(
        (ROOT / "wordpress/content-seed/aktuelles.json").read_text()
    )
    intro = next(
        section for section in aktuelles["sections"]
        if section.get("section_component") == "page_intro"
    )
    assert intro["section_title"] == "Beiträge"


def test_page_intro_heading_level_is_shared_with_processwire_frontend():
    registry = json.loads((ROOT / "site/templates/component-registry.json").read_text())
    page_intro = next(item for item in registry if item["key"] == "page_intro")
    assert page_intro["defaultConfig"]["headingLevel"] == "2"
    heading_schema = next(
        item for item in page_intro["configSchema"] if item["key"] == "headingLevel"
    )
    assert [option["value"] for option in heading_schema["options"]] == ["1", "2"]

    frontend = (
        ROOT / "frontend/components/sections/RegisteredSectionComponents.tsx"
    ).read_text()
    assert "configValue(config, 'headingLevel', '2')" in frontend
    assert "renderHeader(section, visualEditor, headingLevel)" in frontend


def test_page_intro_heading_level_and_text_only_media_render_behaviour():
    page_intro = run_php(r'''
    define('ABSPATH', __DIR__);
    $fields = ['title'=>'Seitentitel','text'=>'<p>Text</p>','heading_level'=>'1'];
    function get_field($name) { global $fields; return $fields[$name] ?? null; }
    function __($v,$d=null){return $v;} function esc_attr($v){return $v;}
    function esc_html($v){return $v;} function esc_url($v){return $v;}
    function bioco_text_has_heading_html($v){return false;}
    function bioco_kses_rich_text($v){return $v;}
    $block=[]; $is_preview=false;
    ob_start(); require 'wordpress/web/app/mu-plugins/bioco-core/blocks/page-intro/render.php'; echo ob_get_clean();
    ''')
    assert "<h1>Seitentitel</h1>" in page_intro

    text_only = run_php(r'''
    define('ABSPATH', __DIR__);
    $fields = ['title'=>'Text','text'=>'<p>Breit</p>'];
    function get_field($name) { global $fields; return $fields[$name] ?? null; }
    function __($v,$d=null){return $v;} function esc_attr($v){return $v;}
    function esc_html($v){return $v;} function esc_url($v){return $v;}
    function bioco_text_has_heading_html($v){return false;}
    function bioco_kses_rich_text($v){return $v;}
    function bioco_image_filter_style(){return '';}
    $block=[]; $is_preview=false;
    ob_start(); require 'wordpress/web/app/mu-plugins/bioco-core/blocks/media-text/render.php'; echo ob_get_clean();
    ''')
    assert "is-text-only" in text_only
    assert "cms-split-media" not in text_only


def test_navigation_footer_and_editorial_media_contracts():
    navigation = json.loads((CORE / "content/navigation.json").read_text())
    assert [item["slug"] for item in navigation["legal"]] == [
        "datenschutz", "impressum", "statuten"
    ]
    assert navigation["footer"]["slogan"]
    assert navigation["site"]["menuOpenLabel"]
    assert navigation["site"]["menuCloseLabel"]

    footer = (THEME / "parts/footer.html").read_text()
    assert footer.count("<!-- wp:bioco/site-footer /-->") == 1
    assert "Datenschutz" not in footer

    core = (CORE / "bioco-core.php").read_text()
    assert "register_block_type('bioco/site-footer'" in core
    assert "bioco_render_site_footer" in core

    for name in ("wir", "mitmachen"):
        wp = json.loads((ROOT / f"wordpress/content-seed/{name}.json").read_text())
        cms = json.loads((ROOT / f"cms/content-seed/{name}.json").read_text())
        assert wp == cms
    wir = json.loads((ROOT / "wordpress/content-seed/wir.json").read_text())
    team = next(s for s in wir["sections"] if s["section_id"] == "hof_team")
    assert team["section_config"]["columnsDesktop"] == "3"
    assert team["section_config"]["mediaRatio"] == "4:3"
    mitmachen = json.loads((ROOT / "wordpress/content-seed/mitmachen.json").read_text())
    kinder = next(s for s in mitmachen["sections"] if s["section_id"] == "familien")
    assert kinder["image_url"] == "https://cms.bioco.ch/site/assets/files/1770/bioco_kinder.jpg"
    assert "Kinder" in kinder["image_alt"]

    for root in ("wordpress", "cms"):
        home = json.loads((ROOT / f"{root}/content-seed/home.json").read_text())
        assert all(
            section.get("section_config", {}).get("styleVariant") == "feature"
            for section in home["sections"]
        )
        for seed_path in sorted((ROOT / f"{root}/content-seed").glob("*.json")):
            seed = json.loads(seed_path.read_text())
            for section in seed["sections"]:
                assert not re.search(
                    r"<a\b[^>]*class=[\"'][^\"']*\bbtn\b",
                    section.get("section_text", ""),
                    re.IGNORECASE,
                ), (root, seed_path.name, section["section_id"])
                if section.get("section_component") == "events_feed":
                    assert section.get("section_config", {}).get("archiveUrl") == "/aktuelles"

    wp_seeds = {
        path.stem: json.loads(path.read_text())
        for path in (ROOT / "wordpress/content-seed").glob("*.json")
    }
    expected_buttons = {
        ("anmeldung-danke", "fragen"): ("info@bioco.ch", "mailto:info@bioco.ch", "secondary"),
        ("gemuese", "demeter-link"): ("Mehr über Demeter erfahren →", "https://www.demeter.ch", "secondary"),
        ("kontakt", "intranet-box"): ("Zum Intranet →", "/intranet", "primary"),
        ("kontakt", "mitglied-werden-box"): ("biocò werden →", "/bioco-werden", "primary"),
    }
    for (slug, section_id), expected in expected_buttons.items():
        section = next(
            item for item in wp_seeds[slug]["sections"] if item["section_id"] == section_id
        )
        button = section["buttons"][0]
        assert (button["text"], button["href"], button["variant"]) == expected
    intranet = next(
        item for item in wp_seeds["kontakt"]["sections"] if item["section_id"] == "intranet-box"
    )
    assert intranet["section_config"]["buttonNavigation"] == "document"

    php = r'''
    define('ABSPATH', __DIR__);
    $current = 'wir'; $event = false;
    function home_url($path) { return 'https://staging.example' . $path; }
    function plugins_url($path, $plugin = null) { return 'https://staging.example/' . $path; }
    function esc_url($value) { return $value; }
    function esc_attr($value) { return $value; }
    function esc_html($value) { return $value; }
    function is_page($slug) { global $current; return $slug === $current; }
    function is_singular($type) { global $event; return $event && $type === 'event'; }
    function is_post_type_archive($type) { return false; }
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/navigation.php';
    $page = bioco_render_primary_navigation();
    $current = ''; $event = true;
    $single = bioco_render_primary_navigation();
    echo json_encode([$page, $single, bioco_render_site_footer()]);
    '''
    page, event, rendered_footer = json.loads(run_php(php))
    assert 'aria-current="page" href="https://staging.example/wir/"' in page
    assert 'aria-current="page" href="https://staging.example/aktuelles/"' in event
    assert 'class="bioco-site-footer"' in rendered_footer
    assert all(item["label"] in rendered_footer for item in navigation["legal"])

    php_urls = r'''
    define('ABSPATH', __DIR__);
    function home_url($path) { return 'https://staging.example' . $path; }
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/navigation.php';
    echo json_encode(array_map('bioco_navigation_url', [
        '/aktuelles', 'https://example.org/path', 'javascript:alert(1)',
        'data:text/html,x', '//evil.example', "/bad\\path", "/bad path",
    ]));
    '''
    urls = json.loads(run_php(php_urls))
    assert urls == [
        "https://staging.example/aktuelles",
        "https://example.org/path",
        "", "", "", "", "",
    ]


def test_shared_visual_primitives_are_systemic():
    blocks = (CORE / "assets/bioco-blocks.css").read_text()
    shell = (THEME / "assets/app.css").read_text()
    assert ".cms-split.is-text-only" in blocks
    assert ".btn:focus-visible" in blocks
    assert ".home .cms-" not in blocks
    assert "[data-style-variant='feature']" in blocks
    button_rule = blocks[blocks.index(".btn,"):blocks.index(".btn:focus-visible")]
    assert "clip-path:" in button_rule
    assert ".btn::before" in button_rule
    assert "clip-path:" not in blocks[blocks.index(".btn,"):blocks.index(".btn::before")]
    secondary_organic = blocks[
        blocks.index(".btn-secondary.btn-organic {"):
        blocks.index("/* group-cards block")
    ]
    assert not re.search(r"(?m)^\s*background:", secondary_organic)
    assert "--bioco-button-background:" in secondary_organic
    assert "--bioco-frame-inline" in shell
    assert "aria-current" in shell
    events = (CORE / "blocks/events-feed/render.php").read_text()
    assert "home_url('/aktuelles')" not in events
    assert "get_field('archive_url')" in events
