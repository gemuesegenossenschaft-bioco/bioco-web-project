"""Focused contracts for the intranet page, the outer frame seam and the
data-derived external-link behaviour of structured CTAs.

These sit next to test_wordpress_subpage_design.py rather than inside it: they
cover the *seam* between theme templates, the navigation contract and the seed
corpus, which is a different axis from the per-block visual contract.
"""

import json
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"
THEME = ROOT / "wordpress/web/app/themes/bioco"
WP_SEEDS = ROOT / "wordpress/content-seed"
CMS_SEEDS = ROOT / "cms/content-seed"

PDFS = (
    "documents/gemuese_ausliefertour_dienstag_2026_04.pdf",
    "documents/gemuese_ausliefertour_freitag_2026_04.pdf",
    "documents/2510_fahrspesenrueckforderung.pdf",
)


def run_php(source):
    return subprocess.run(
        ["php", "-r", source], cwd=ROOT, text=True, capture_output=True, check=True
    ).stdout


def test_page_templates_do_not_nest_constrained_layouts():
    """One outer frame only. `main` + `post-content` both declaring a
    constrained layout stacks theme.json's contentSize inside itself, which is
    what pushed page-intro headings out of line with the section frames."""
    for name in ("page", "index", "single"):
        markup = (THEME / f"templates/{name}.html").read_text()
        assert '"type":"constrained"' not in markup, name
        assert "wp:post-content" in markup, name


def test_intranet_seed_mirrors_the_live_page_content():
    wp = json.loads((WP_SEEDS / "intranet.json").read_text())
    cms = json.loads((CMS_SEEDS / "intranet.json").read_text())
    assert wp == cms

    assert wp["slug"] == "intranet"
    assert wp["path"] == "/intranet/"
    assert wp["title"] == "Intranet"

    section_ids = [section["section_id"] for section in wp["sections"]]
    text = " ".join(section.get("section_text", "") for section in wp["sections"])
    titles = [section.get("section_title", "") for section in wp["sections"]]

    assert "Was findest du im Intranet?" in titles
    for label in ("Verteilplan", "Fahrspesen", "Interne Dokumente", "Mitgliederbereich"):
        assert label in text, label
    assert text.count("<li>") >= 7

    for url in PDFS:
        assert url in text, url

    fragen = next(
        section for section in wp["sections"]
        if section.get("section_title") == "Fragen?"
    )
    assert 'href="mailto:info@bioco.ch"' in fragen["section_text"]

    cta = next(
        button
        for section in wp["sections"]
        for button in section.get("buttons", [])
        if button["href"] == "https://intranet.bioco.ch"
    )
    assert cta["text"] == "Zum Intranet"
    assert cta["variant"] == "primary"

    assert len(section_ids) == len(set(section_ids)), "section ids must be unique"


def test_seed_corpus_and_staging_gate_cover_twenty_two_pages():
    wp = sorted(path.name for path in WP_SEEDS.glob("*.json"))
    cms = sorted(path.name for path in CMS_SEEDS.glob("*.json"))
    assert wp == cms
    assert len(wp) == 22
    assert "intranet.json" in wp

    gate = (ROOT / "tests/wordpress-staging-render-gate.sh").read_text()
    routes = re.search(r"routes=\((?P<body>.*?)\)", gate, re.S).group("body").split()
    assert "/intranet/" in routes
    assert len(routes) == 22


def test_utility_navigation_links_the_internal_intranet_page():
    navigation = json.loads((CORE / "content/navigation.json").read_text())
    intranet = next(
        item for item in navigation["utility"] if item["slug"] == "intranet"
    )
    assert intranet["url"] == "/intranet/"

    php = r'''
    define('ABSPATH', __DIR__);
    function home_url($path = '/') { return 'https://staging.example' . $path; }
    function plugins_url($path, $plugin = null) { return 'https://staging.example/' . $path; }
    function esc_url($value) { return $value; }
    function esc_attr($value) { return $value; }
    function esc_html($value) { return $value; }
    function is_page($slug) { return $slug === 'intranet'; }
    function is_singular($type) { return false; }
    function is_post_type_archive($type) { return false; }
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/navigation.php';
    echo bioco_render_primary_navigation();
    '''
    markup = run_php(php)
    assert 'aria-current="page" href="https://staging.example/intranet/"' in markup
    assert "https://intranet.bioco.ch/" not in markup


def test_external_links_are_derived_from_the_href_not_hardcoded_per_block():
    php = r'''
    define('ABSPATH', __DIR__);
    function home_url($path = '/') { return 'https://staging.example' . $path; }
    function wp_kses($v, $a) { return $v; }
    function current_time($f) { return ''; }
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';
    $hrefs = [
        'https://intranet.bioco.ch',
        'https://cms.bioco.ch/site/assets/files/1804/x.pdf',
        '/intranet/',
        'mailto:info@bioco.ch',
        '#kontakt-formular',
        'https://staging.example/wir/',
        '',
    ];
    $out = [];
    foreach ($hrefs as $href) $out[] = bioco_link_target_attributes($href);
    echo json_encode($out);
    '''
    external = ' target="_blank" rel="noopener noreferrer"'
    assert json.loads(run_php(php)) == [external, external, "", "", "", "", ""]

    # Every block that renders the shared buttons repeater must go through the
    # helper — otherwise a new block silently reintroduces unsafe target usage.
    for block in (
        "banner", "cta-band", "gallery-strip", "hero", "media-grid",
        "media-text", "page-intro", "pricing-table", "rich-text",
        "text-columns", "video-embed",
    ):
        render = (CORE / f"blocks/{block}/render.php").read_text()
        assert "bioco_link_target_attributes($button_href)" in render, block


def test_wir_section_puts_text_left_and_image_right():
    for root in (WP_SEEDS, CMS_SEEDS):
        seed = json.loads((root / "wir.json").read_text())
        sides = {
            section["section_id"]: section.get("section_config", {}).get("mediaSide")
            for section in seed["sections"]
            if "mediaSide" in section.get("section_config", {})
        }
        assert sides == {
            "wir": "right",
            "alle_mitglieder": "left",
            "betriebsgruppe": "right",
            "gotti": "left",
        }, root
