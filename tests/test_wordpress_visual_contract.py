"""Visual contracts for the fallback block theme (bioco), the shared shell
assets and the approved content/palette.

The former monolithic source check for shell navigation markup and
bioco-navigation.js helper names was consolidated: script behaviour (mobile
menu toggle, utility-row auto-hide) is executed in
tests/test_wordpress_navigation_scroll.py, and the rendered navigation/footer
contract is exercised end-to-end through the real renderers and theme
templates in tests/test_wordpress_divi_shell.py. What remains here are the
contracts that still need the static sources: the block theme's own adapter
markup, the shared shell CSS asset contract (now owned by bioco-core,
#180), and the approved content/palette data.

The full keep/replace/remove map of the WordPress test surface lives in
tests/README.md.
"""

import json
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
THEME = ROOT / "wordpress/web/app/themes/bioco"
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"


APPROVED_COLORS = {
    "bioco-green": "#2e7d32",
    "bioco-green-dark": "#1b5e20",
    "bioco-carrot": "#ff8c00",
    "bioco-beet": "#87213d",
    "bioco-bg": "#f5f1e8",
    "bioco-surface": "#ffffff",
    "bioco-text": "#1a1a1a",
    "bioco-text-muted": "#4a4a4a",
    "bioco-border": "#e1e4e8",
}


def _hex_rgb(value):
    value = value.lstrip("#")
    return tuple(int(value[index:index + 2], 16) / 255 for index in (0, 2, 4))


def _contrast_ratio(foreground, background):
    def luminance(color):
        channels = [
            channel / 12.92 if channel <= 0.04045
            else ((channel + 0.055) / 1.055) ** 2.4
            for channel in _hex_rgb(color)
        ]
        return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]

    lighter, darker = sorted(
        (luminance(foreground), luminance(background)), reverse=True
    )
    return (lighter + 0.05) / (darker + 0.05)


def _css_rule(css, selector):
    match = re.search(rf"{re.escape(selector)}\s*\{{(?P<body>[^}}]+)\}}", css)
    assert match, f"missing CSS rule: {selector}"
    return match.group("body")


def test_approved_palette_and_bundled_dm_sans_are_the_theme_source_of_truth():
    theme = json.loads((THEME / "theme.json").read_text())
    families = theme["settings"]["typography"]["fontFamilies"]
    dm_sans = next(family for family in families if family["slug"] == "body")
    font_source = dm_sans["fontFace"][0]["src"][0]

    assert dm_sans["name"] == "DM Sans"
    assert font_source.startswith("file:./")
    assert (THEME / font_source.removeprefix("file:./")).is_file()

    palette = {
        color["slug"]: color["color"].lower()
        for color in theme["settings"]["color"]["palette"]
    }
    token_css = (CORE / "assets/bioco-tokens.css").read_text().lower()

    assert {
        slug: palette[slug] for slug in APPROVED_COLORS
    } == APPROVED_COLORS
    assert [
        slug
        for slug, expected in APPROVED_COLORS.items()
        if f"--wp--preset--color--{slug}: {expected};" not in token_css
    ] == []


def test_block_theme_header_part_wires_the_shared_navigation_contract():
    header = (THEME / "parts/header.html").read_text()

    assert "wp:bioco/primary-navigation" in header
    assert [
        marker
        for marker in ("bioco-page-shell", "bioco-hero-nav-overlay")
        if marker not in header
    ] == []

    # Every block-theme template renders the shared bioco-site-header block.
    templates = sorted((THEME / "templates").glob("*.html"))
    assert templates
    assert [
        template.name
        for template in templates
        if '"slug":"header","tagName":"header","className":"bioco-site-header"'
        not in template.read_text()
    ] == []

    # Approved content contract (the rendered side of it is exercised
    # behaviourally in tests/test_wordpress_divi_shell.py).
    navigation = json.loads((CORE / "content/navigation.json").read_text())
    assert [item["label"] for item in navigation["utility"]] == [
        "Standorte", "Kontakt", "Intranet",
    ]
    assert [item["label"] for item in navigation["primary"]] == [
        "Wir",
        "Gemüse",
        "Mitmachen",
        "Abos",
        "Aktuelles",
    ]
    assert navigation["cta"]["label"] == "BIOCÒ WERDEN"
    assert (CORE / "assets/bioco-logo.png").is_file()


def test_block_theme_adapter_enqueues_no_front_end_assets_itself():
    """The fallback theme owns no front-end asset enqueue anymore (#180).

    Tokens, block CSS, the shared shell stylesheet and the navigation script
    all arrive from bioco-core's real wp_enqueue_scripts registrations (see
    tests/test_wordpress_divi_shell.py). Executing the real theme
    functions.php through its registered hook must therefore enqueue nothing
    at all — otherwise the shared assets would load twice under this theme.
    """
    php = (
        "define('ABSPATH', __DIR__);\n"
        "$GLOBALS['BIOCO_ENQUEUED'] = [];\n"
        "$GLOBALS['BIOCO_ACTIONS'] = [];\n"
        "function add_theme_support($feature, ...$args) { return true; }\n"
        "function load_theme_textdomain($domain, $path) { return true; }\n"
        "function get_template_directory() { return 'wordpress/web/app/themes/bioco'; }\n"
        "function get_theme_file_path($file = '') { return 'wordpress/web/app/themes/bioco/' . ltrim((string) $file, '/'); }\n"
        "function get_theme_file_uri($file = '') { return 'https://staging.example/wp-content/themes/bioco/' . ltrim((string) $file, '/'); }\n"
        "function add_action($hook, $callback, $priority = 10) {\n"
        "    $GLOBALS['BIOCO_ACTIONS'][$hook][] = [(int) $priority, $callback];\n"
        "    return true;\n"
        "}\n"
        "function add_filter($hook, $callback, $priority = 10) { return true; }\n"
        "function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false) {\n"
        "    $GLOBALS['BIOCO_ENQUEUED'][] = ['type' => 'style', 'handle' => $handle, 'src' => $src, 'deps' => $deps, 'ver' => $ver];\n"
        "}\n"
        "function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $footer = false) {\n"
        "    $GLOBALS['BIOCO_ENQUEUED'][] = ['type' => 'script', 'handle' => $handle, 'src' => $src, 'deps' => $deps, 'ver' => $ver];\n"
        "}\n"
        "require 'wordpress/web/app/themes/bioco/functions.php';\n"
        "foreach (['after_setup_theme', 'wp_enqueue_scripts'] as $hook) {\n"
        "    foreach ($GLOBALS['BIOCO_ACTIONS'][$hook] ?? [] as [, $callback]) {\n"
        "        call_user_func($callback);\n"
        "    }\n"
        "}\n"
        "echo json_encode([\n"
        "    'hooks' => array_keys($GLOBALS['BIOCO_ACTIONS']),\n"
        "    'enqueued' => $GLOBALS['BIOCO_ENQUEUED'],\n"
        "]);"
    )
    result = json.loads(subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout)

    assert result["hooks"] == ["after_setup_theme"]
    assert result["enqueued"] == []


def test_shell_chrome_css_defines_the_two_tier_navigation_chrome():
    chrome_css = (CORE / "assets/bioco-shell.css").read_text()

    markers = ("bioco-page-shell", "bioco-utility-nav", "bioco-primary-nav", "bioco-hero-nav-overlay")
    assert [marker for marker in markers if f".{marker}" not in chrome_css] == []

    # Mobile open state: the menu list becomes visible inside the open nav.
    assert ".bioco-primary-nav.is-open ul" in chrome_css
    assert "box-sizing: border-box" in chrome_css

    # No horizontal overflow escape hatches in the sticky chrome.
    assert "white-space: nowrap" in _css_rule(chrome_css, ".bioco-primary-nav li a")


def test_shell_chrome_keeps_the_logo_full_colour():
    chrome_css = (CORE / "assets/bioco-shell.css").read_text()

    # Full-colour logo: no monochrome/invert filter may survive anywhere.
    assert "filter" not in _css_rule(chrome_css, ".bioco-logo img")
    assert "invert(" not in chrome_css


def test_shell_chrome_sticky_contract_avoids_overlap_and_scroll_containers():
    chrome_css = (CORE / "assets/bioco-shell.css").read_text()

    # Stickiness must sit on the <header>, whose containing block spans the
    # whole page. A sticky .bioco-navigation-shell would be a no-op: its
    # parent .bioco-page-shell is exactly as tall as the navigation itself.
    shell_rule = _css_rule(chrome_css, ".bioco-navigation-shell")
    assert "sticky" not in shell_rule
    assert "fixed" not in shell_rule
    sticky_rule = _css_rule(chrome_css, "header:has(.bioco-navigation-shell)")
    assert "position: sticky" in sticky_rule
    assert "top: 0" in sticky_rule
    assert ".bioco-site-header," in chrome_css

    # The primary row stays in normal flow, so it reserves its own height and
    # can never overlap page content on any subpage.
    primary_rule = _css_rule(chrome_css, ".bioco-primary-nav")
    assert "position: absolute" not in primary_rule
    assert "position: fixed" not in primary_rule
    assert ".home .bioco-primary-nav" not in chrome_css

    # Sticky positioning breaks when an ancestor becomes a scroll container:
    # `overflow-x: hidden` on <body> must be superseded by `clip`.
    body_rule = _css_rule(chrome_css, "body")
    assert body_rule.index("overflow-x: hidden") < body_rule.index("overflow-x: clip")
    assert "overflow-x: clip" in body_rule


def test_shell_chrome_utility_collapse_css_contract():
    chrome_css = (CORE / "assets/bioco-shell.css").read_text()

    # Utility row stays keyboard reachable while collapsed and expands on focus.
    # The script side of
    # this contract is executed in tests/test_wordpress_navigation_scroll.py.
    hidden_rule = _css_rule(
        chrome_css, ".bioco-navigation-shell.is-utility-hidden .bioco-utility-nav"
    )
    assert "height: 0" in hidden_rule
    assert "visibility: hidden" not in hidden_rule
    assert "overflow: hidden" in _css_rule(chrome_css, ".bioco-utility-nav")
    assert "pointer-events: none" in hidden_rule
    assert ".bioco-navigation-shell.is-utility-hidden .bioco-utility-nav:focus-within" in chrome_css
    assert "prefers-reduced-motion: reduce" in chrome_css


def test_homepage_seed_contains_approved_hero_section_images_and_cta_labels():
    home = json.loads((ROOT / "wordpress/content-seed/home.json").read_text())

    sections = {section["section_id"]: section for section in home["sections"]}
    actual = {
        "hero_title": home["hero"].get("hero_title"),
        "hero_subtitle": home["hero"].get("hero_subtitle"),
        "hero_image": home["hero"].get("image_url", "").rsplit("/", 1)[-1],
        "willkommen_image": sections["willkommen"].get("image_url", "").rsplit("/", 1)[-1],
        "gemeinsam_image": sections["gemeinsam"].get("image_url", "").rsplit("/", 1)[-1],
    }
    assert actual == {
        "hero_title": "Gemeinsam\nGemüse anbauen",
        "hero_subtitle": "Solidarische Landwirtschaft\nin Baden",
        "hero_image": "frontseitestartseite.jpg",
        "willkommen_image": "zusammen-arbeiten-2.1600x0.jpg",
        "gemeinsam_image": "gemeinsamsolidarischfrisch-1.1600x0.jpg",
    }

    labels = [
        button["text"].strip()
        for section in home["sections"]
        for button in section.get("buttons", [])
    ]
    assert labels == [
        "Lerne uns kennen",
        "Was gerade wächst",
        "Nimm Kontakt auf",
        "Zu uns finden",
    ]
    assert all(labels)


def test_primary_and_secondary_button_labels_have_explicit_aa_contrast():
    css = (CORE / "assets/bioco-blocks.css").read_text().lower()
    primary = _css_rule(css, ".btn-primary")
    secondary = _css_rule(css, ".btn-secondary")

    assert "color: #ffffff" in primary
    assert "--bioco-button-background: var(--wp--preset--color--bioco-green)" in primary
    assert _contrast_ratio("#ffffff", APPROVED_COLORS["bioco-green"]) >= 4.5

    assert "color: var(--wp--preset--color--bioco-green)" in secondary
    assert "--bioco-button-background: var(--wp--preset--color--bioco-surface)" in secondary
    assert _contrast_ratio(
        APPROVED_COLORS["bioco-green"], APPROVED_COLORS["bioco-surface"]
    ) >= 4.5

    hero_shade = _css_rule(css, ".hero-shade")
    alpha_match = re.search(
        r"rgba\(\s*0\s*,\s*0\s*,\s*0\s*,\s*(0?\.\d+|1(?:\.0+)?)\s*\)",
        hero_shade,
    )
    assert alpha_match, "Hero overlay must contain a valid alpha value"
    alpha = float(alpha_match.group(1))
    channel = round(255 * (1 - alpha))
    darkest_light_image = f"#{channel:02x}{channel:02x}{channel:02x}"
    assert _contrast_ratio("#ffffff", darkest_light_image) >= 4.5
