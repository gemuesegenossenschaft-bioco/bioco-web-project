import json
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"
THEME = ROOT / "wordpress/web/app/themes/bioco"


def _css_rule(css, selector):
    match = re.search(rf"{re.escape(selector)}\s*\{{(?P<body>[^}}]+)\}}", css)
    assert match, f"missing CSS rule: {selector}"
    return match.group("body")


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
            if not section.get("section_component")
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


def test_section_outer_frames_share_a_single_content_max():
    """The shared .cms-section wrapper provides one outer frame. Explicit
    data-container variants keep their semantic widths and are centred within
    it. Inner text max-widths stay untouched. No route-specific tweaks."""
    blocks = (CORE / "assets/bioco-blocks.css").read_text()
    shell = (THEME / "assets/app.css").read_text()

    section_rule = _css_rule(blocks, ".cms-section")
    assert "max-width: var(--wp--style--global--content-size, 1160px)" in section_rule
    assert "--bioco-section-offset: max(0px, calc((100% - var(--wp--style--global--content-size, 1160px)) / 2))" in section_rule
    assert "width: calc(100% - var(--bioco-section-offset))" in section_rule
    assert "margin-left: var(--bioco-section-offset)" in section_rule
    assert "margin-right: 0" in section_rule

    split_rule = _css_rule(blocks, ".cms-split")
    assert "margin-left: var(--bioco-section-offset)" in split_rule
    assert "margin-right: 0" in split_rule

    # Split variants must keep their explicit widths and be centred.
    split_frames = {
        ".cms-split[data-container-width='sm']": "640px",
        ".cms-split[data-container-width='md']": "820px",
        ".cms-split[data-container-width='lg']": "1040px",
        ".cms-split[data-container-width='xl']": "1280px",
        ".cms-split[data-container-width='full']": "100%",
    }
    for selector, width in split_frames.items():
        rule = _css_rule(blocks, selector)
        assert f"max-width: {width}" in rule, selector

    # Grouped container variants must keep their explicit widths (semantic
    # controls, not flattened to a single value).
    grouped_rules = [
        ".cms-page-intro[data-container='sm'],\n.cms-text-columns[data-container='sm'] { max-width: 640px; }",
        ".cms-page-intro[data-container='md'],\n.cms-cta-band[data-container='md'],\n.cms-text-columns[data-container='md'],\n.cms-timeline[data-container='md'] { max-width: 820px; }",
        ".cms-page-intro[data-container='lg'],\n.cms-cta-band[data-container='lg'],\n.cms-text-columns[data-container='lg'],\n.cms-timeline[data-container='lg'] { max-width: 1040px; }",
        ".cms-page-intro[data-container='xl'],\n.cms-cta-band[data-container='xl'],\n.cms-text-columns[data-container='xl'],\n.cms-timeline[data-container='xl'] { max-width: 1280px; }",
        ".cms-page-intro[data-container='full'],\n.cms-cta-band[data-container='full'],\n.cms-text-columns[data-container='full'] { max-width: 100%; }",
    ]
    for rule in grouped_rules:
        assert rule in blocks, rule

    # Narrower editorial frames keep the common left edge rather than being
    # independently centred and drifting away from surrounding sections.
    assert ".cms-page-intro,\n.cms-cta-band,\n.cms-text-columns,\n.cms-timeline {\n  margin-right: 0;\n}" in blocks

    # Inner text widths must remain intentionally narrower than the outer frame.
    assert "max-width: 74rem" in _css_rule(
        blocks, ".cms-page-intro-inner[data-text-width='wide']"
    )
    assert "max-width: 74rem" in _css_rule(
        blocks, ".cms-split.is-text-only .cms-split-content"
    )

    # No page/route-specific selectors are allowed to tweak alignment.
    assert ".home .cms-" not in blocks
    assert ".mitmachen .cms-" not in blocks
    assert re.search(r"\.(page|single|archive|category)-\w+\s+\.cms-", blocks) is None
    assert re.search(r"\.(page|single|archive|category)-\w+\s+\.cms-", shell) is None


def test_primary_nav_cta_is_green_white_organic_and_interactive():
    shell = (THEME / "assets/app.css").read_text()

    cta = _css_rule(shell, ".bioco-primary-nav .bioco-primary-cta")
    assert "--bioco-nav-cta-background:" in cta
    assert "var(--wp--preset--color--bioco-green)" in cta
    assert "color: #fff" in cta or "color: #ffffff" in cta
    assert "clip-path:" not in cta
    cta_paint = _css_rule(shell, ".bioco-primary-nav .bioco-primary-cta::before")
    assert "clip-path:" in cta_paint
    assert "background: var(--bioco-nav-cta-background)" in cta_paint

    hover = _css_rule(shell, ".bioco-primary-nav .bioco-primary-cta:hover")
    assert "--bioco-nav-cta-background: var(--wp--preset--color--bioco-green-dark)" in hover
    assert "color: #fff" in hover or "color: #ffffff" in hover

    focus = _css_rule(shell, ".bioco-primary-nav .bioco-primary-cta:focus-visible")
    assert "--bioco-nav-cta-background: var(--wp--preset--color--bioco-green-dark)" in focus
    assert "color: #fff" in focus or "color: #ffffff" in focus
    assert "outline:" in focus

    mobile = _css_rule(shell, ".bioco-primary-nav.is-open .bioco-primary-cta")
    assert "background: transparent" in mobile


def test_intranet_seed_page_is_a_single_h1_with_structured_blocks():
    wp_path = ROOT / "wordpress/content-seed/intranet.json"
    cms_path = ROOT / "cms/content-seed/intranet.json"
    assert wp_path.exists(), "WordPress intranet seed missing"
    assert cms_path.exists(), "CMS intranet seed mirror missing"

    wp = json.loads(wp_path.read_text())
    cms = json.loads(cms_path.read_text())
    assert wp == cms, "wordpress/cms intranet seeds must stay identical"

    assert wp["slug"] == "intranet"
    assert wp["path"] == "/intranet/"
    assert wp["title"] == "Intranet"

    headings = [
        section for section in wp["sections"]
        if section.get("section_component") == "page_intro"
        and section.get("section_config", {}).get("headingLevel") == "1"
    ]
    assert len(headings) == 1, "intranet must have exactly one page_intro H1"
    assert headings[0].get("section_title") == "Intranet"

    for section in wp["sections"]:
        text = section.get("section_text", "")
        assert "<h1" not in text.lower(), section["section_id"]
        assert not re.search(
            r"<a\b[^>]*class=[\"'][^\"']*\bbtn\b",
            text,
            re.IGNORECASE,
        ), ("hardcoded anchor button in", section["section_id"])

    # At least one section uses the structured buttons array (no editorial PHP).
    assert any(section.get("buttons") for section in wp["sections"])


def test_shared_visual_primitives_are_systemic():
    blocks = (CORE / "assets/bioco-blocks.css").read_text()
    shell = (THEME / "assets/app.css").read_text()
    assert ".cms-split.is-text-only" in blocks
    desktop_media = blocks[blocks.index("@media (min-width: 900px)"):blocks.index("/* banner block */")]
    assert ".cms-split.is-text-only { grid-template-columns: 1fr; }" in desktop_media
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
    assert "bioco_field('archive_url')" in events
