import json
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
THEME_DIR = ROOT / "wordpress" / "web" / "app" / "themes" / "bioco-divi"
STYLE_CSS = THEME_DIR / "style.css"
FUNCTIONS_PHP = THEME_DIR / "functions.php"
FONT_FILE = THEME_DIR / "assets" / "fonts" / "dmsans-variable.woff2"
FONT_LICENSE = THEME_DIR / "assets" / "fonts" / "OFL.txt"


def _run_php(code: str):
    result = subprocess.run(
        ["php", "-r", code],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    )
    return result.stdout


def _json_php(code: str):
    return json.loads(_run_php(code))


def _style_css() -> str:
    return STYLE_CSS.read_text(encoding="utf-8")


def _rule_bodies(css: str, selector_substring: str) -> list:
    """Return the bodies of all top-level CSS rules whose selectors contain selector_substring."""
    # Remove comments.
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    bodies = []
    depth = 0
    body_start = 0
    selector_start = 0
    in_rule = False
    for i, ch in enumerate(css):
        if ch == "{":
            if depth == 0:
                in_rule = True
                body_start = i + 1
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0 and in_rule:
                selector = css[selector_start:body_start - 1].strip()
                if _selector_contains(selector, selector_substring):
                    # Strip any nested rule bodies from this top-level body.
                    body = _strip_nested(css[body_start:i].strip())
                    bodies.append(body)
                in_rule = False
                selector_start = i + 1
    return bodies


def _strip_nested(body: str) -> str:
    """Remove nested at-rules or selector blocks from a rule body, leaving top-level declarations."""
    out = []
    depth = 0
    for ch in body:
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
        elif depth == 0:
            out.append(ch)
    return "".join(out).strip()


def _selector_contains(selector: str, needle: str) -> bool:
    """Check that the CSS selector contains needle as a whole selector token."""
    idx = selector.find(needle)
    if idx == -1:
        return False
    end = idx + len(needle)
    # Needle must be followed by a non-identifier char (or end of selector)
    # to avoid matching a longer class like .bioco-home-hero-row.
    if end < len(selector) and re.match(r"[a-zA-Z0-9_-]", selector[end]):
        return False
    return True


def _media_block(css: str, width: str) -> str:
    """Return the body of the first @media block whose query contains width."""
    match = re.search(r"@media[^{]*" + re.escape(width) + r"[^{]*\{([^}]*)\}", css, re.S)
    return match.group(1) if match else ""


# ---------------------------------------------------------------------------
# Enqueue behavior
# ---------------------------------------------------------------------------


def test_child_theme_enqueues_parent_shell_then_child_stylesheet():
    """functions.php enqueues Divi, shared shell, then child presentation."""
    theme_dir = str(THEME_DIR)
    code = (
        r"""
define('ABSPATH', __DIR__);
$enqueued = [];
function add_action($hook, $callback) { call_user_func($callback); }
function add_filter($hook, $callback) {}
function get_template_directory_uri() { return 'https://example.com/divi'; }
function get_stylesheet_directory_uri() { return 'https://example.com/child'; }
function get_stylesheet_directory() { return '"""
        + theme_dir.replace("\\", "\\\\")
        + r"""'; }
function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
    global $enqueued;
    $enqueued[] = ['handle' => $handle, 'src' => $src, 'deps' => $deps, 'ver' => $ver];
}
require 'wordpress/web/app/themes/bioco-divi/functions.php';
echo json_encode($enqueued);
"""
    )
    result = _json_php(code)
    assert len(result) >= 2, result

    parent = result[0]
    assert parent["handle"] == "divi-parent-style"
    assert parent["src"] == "https://example.com/divi/style.css"

    shell = result[1]
    assert shell["handle"] == "bioco-shell"
    assert shell["src"] == "https://example.com/bioco/assets/app.css"
    assert shell["deps"] == ["bioco-tokens"]

    child = result[2]
    assert child["handle"] == "bioco-divi-style"
    assert child["src"] == "https://example.com/child/style.css"
    assert "divi-parent-style" in child["deps"]
    assert "bioco-tokens" in child["deps"]
    assert "bioco-shell" in child["deps"]
    assert isinstance(child["ver"], str) and child["ver"].isdigit(), "Version must be a filemtime string"


# ---------------------------------------------------------------------------
# Font asset & CSS contract
# ---------------------------------------------------------------------------


def test_dm_sans_font_face_is_self_hosted():
    """DM Sans is loaded from a local woff2 with a matching @font-face rule and license."""
    assert FONT_FILE.exists(), f"Missing font: {FONT_FILE}"
    assert FONT_LICENSE.exists(), f"Missing font license: {FONT_LICENSE}"

    css = _style_css()
    assert re.search(r"@font-face\s*{", css), "Missing @font-face block"
    assert "font-family: 'DM Sans'" in css or 'font-family: "DM Sans"' in css, "DM Sans font-family not declared"
    assert "dmsans-variable.woff2" in css, "Font src does not reference local woff2"
    assert "font-display: swap" in css, "Font should use swap display"


def test_no_broad_home_divi_layout_rules():
    """No broad .home .et_pb_section/.et_pb_row/.et_pb_column layout rules that would corrupt global chrome."""
    css = _style_css()
    layout_props = ("padding", "margin", "max-width", "width", "display", "grid-template-columns", "flex-direction")
    for selector in (".home .et_pb_section", ".home .et_pb_row", ".home .et_pb_column"):
        bodies = _rule_bodies(css, selector)
        for body in bodies:
            body_lower = body.lower()
            assert not any(prop in body_lower for prop in layout_props), (
                f"Broad layout rule {selector!r} contains layout property: {body[:120]}"
            )


def test_home_page_container_is_cream_and_width_constrained():
    """Homepage wrapper uses cream background and respects wide/content max widths."""
    css = _style_css()
    assert ".home" in css, "Styles must be scoped through .home"
    assert "--wp--preset--color--bioco-bg" in css or "#F5F1E8" in css, "Cream background token/value missing"
    assert "--wp--style--global--wide-size" in css or "1400px" in css, "Wide max-width missing"
    assert "--wp--style--global--content-size" in css or "1160px" in css, "Content max-width missing"


def test_home_desktop_geometry_tracks_live_site_baseline():
    """Critical desktop geometry stays aligned with the 1440px live baseline."""
    css = _style_css()
    for token in (
        ".home .bioco-site-header",
        "height: 120px",
        ".home .bioco-primary-nav",
        "top: 40px",
        ".home .bioco-home-hero-row",
        "height: 585px",
        ".home .bioco-home-hero + .bioco-home-feature",
        "margin-top: 142px",
        ".home .bioco-home-live",
        ".home .bioco-home-cta",
    ):
        assert token in css


def test_hero_photo_keeps_readable_text_without_full_image_darkening():
    css = _style_css()
    overlay = " ".join(_rule_bodies(css, ".home .bioco-home-hero-column::before"))
    heading = " ".join(_rule_bodies(css, ".home .bioco-home-hero-title h1"))

    assert "rgba(0, 0, 0, 0.32) 0%" in overlay
    assert "transparent 40%" in overlay
    assert "rgba(0, 0, 0, 0.20) 100%" in overlay
    assert "color: #FFFFFF" in heading or "color: #fff" in heading
    assert "font-size: 40px" in heading


def test_desktop_hero_copy_matches_reference_baseline():
    css = _style_css()
    assert "padding: 48px 184.4px 128px" in css
    assert "margin-bottom: 13px !important" in css


def test_hero_bitmap_targets_image_wrap_and_img():
    """Hero image module wrapper and inner bitmap both fill the card."""
    css = _style_css()
    wrap_bodies = _rule_bodies(css, ".home .bioco-home-hero-image .et_pb_image_wrap")
    assert wrap_bodies, "Missing rule for hero image wrapper"
    wrap = " ".join(wrap_bodies).lower()
    assert "width" in wrap and "height" in wrap, "Hero image wrapper must size width and height"

    img_bodies = _rule_bodies(css, ".home .bioco-home-hero-image img")
    assert img_bodies, "Missing rule for hero bitmap img"
    img = " ".join(img_bodies).lower()
    assert "width: 100%" in img or "width:100%" in img, "Hero img width must be 100%"
    assert "height: 100%" in img or "height:100%" in img, "Hero img height must be 100%"
    assert "object-fit: cover" in img, "Hero img must use object-fit: cover"

    module = " ".join(_rule_bodies(css, ".home .bioco-home-hero-column .bioco-home-hero-image")).lower()
    assert "margin: 0 !important" in module, "Deferred Divi module margin must not shorten the hero bitmap"


def test_hero_overlay_is_on_column_before():
    """Dark overlay sits on the column, between image (z0) and text modules (z2)."""
    css = _style_css()
    bodies = _rule_bodies(css, ".home .bioco-home-hero-column::before")
    assert bodies, "Hero overlay must be on .home .bioco-home-hero-column::before"
    body = " ".join(bodies).lower()
    assert "content" in body, "Overlay pseudo-element needs content"
    assert "linear-gradient" in body or "rgba" in body, "Overlay needs darkening background"


def test_hero_card_is_row_based():
    """Hero card shape and clipping live on the row, while section only provides scoped outer padding/background."""
    css = _style_css()
    row_bodies = _rule_bodies(css, ".home .bioco-home-hero-row")
    assert row_bodies, "Hero row must have layout rules"
    row = " ".join(row_bodies).lower()
    assert "border-radius" in row, "Hero row must define border-radius"
    assert "overflow" in row, "Hero row must clip overflow"
    assert "min-height" in row, "Hero row must define min-height"
    assert "max-width" in row, "Hero row must define max-width"

    section_bodies = _rule_bodies(css, ".home .bioco-home-hero")
    assert section_bodies, "Hero section must exist"
    section = " ".join(section_bodies).lower()
    assert "padding" in section, "Hero section must provide outer padding"
    assert "border-radius" not in section, "Hero section should not clip (row does)"


def test_hero_is_rounded_cover_overlay_white_text():
    """Hero block has rounded corners, responsive height, cover image, overlay and white text."""
    css = _style_css()
    assert ".home .bioco-home-hero" in css, "Hero selector missing"
    assert "border-radius" in css, "Hero border-radius missing"
    assert "24px" in css or "--wp--custom--radius--lg" in css, "24px radius token/value missing"
    assert "object-fit: cover" in css, "Hero image object-fit missing"
    assert "min-height" in css and "clamp" in css, "Responsive hero height missing"
    assert "color:" in css and ("#FFFFFF" in css or "#fff" in css or "white" in css), "White hero text missing"


def test_feature_rows_are_two_columns_then_stack():
    """Feature rows split into two columns on desktop and stack below 767px."""
    css = _style_css()
    assert ".home .bioco-home-feature" in css, "Feature selector missing"

    row_bodies = _rule_bodies(css, ".home .bioco-home-feature-row")
    assert row_bodies, "Feature row rules missing"
    row = " ".join(row_bodies).lower()
    assert "grid-template-columns" in row or "display: flex" in row, "Feature row needs two-column layout"

    mobile_css = _media_block(css, "767px")
    assert mobile_css, "Mobile breakpoint missing"
    assert (
        "grid-template-columns: 1fr" in mobile_css
        or "flex-direction: column" in mobile_css
    ), "Mobile feature row should stack"


def test_feature_bitmap_targets_wrap_and_img_with_clipping():
    """Feature image wrapper clips to 4/3 and rounds; inner bitmap fills and covers."""
    css = _style_css()
    wrap_bodies = _rule_bodies(css, ".home .bioco-home-feature-image .et_pb_image_wrap")
    assert wrap_bodies, "Missing rule for feature image wrapper"
    wrap = " ".join(wrap_bodies).lower()
    assert "aspect-ratio" in wrap, "Feature wrapper must set aspect-ratio"
    assert "overflow" in wrap, "Feature wrapper must clip overflow"
    assert "border-radius" in wrap, "Feature wrapper must round corners"

    img_bodies = _rule_bodies(css, ".home .bioco-home-feature-image img")
    assert img_bodies, "Missing rule for feature bitmap img"
    img = " ".join(img_bodies).lower()
    assert "width: 100%" in img or "width:100%" in img, "Feature img width must be 100%"
    assert "height: 100%" in img or "height:100%" in img, "Feature img height must be 100%"
    assert "object-fit: cover" in img, "Feature img must use object-fit: cover"


def test_buttons_use_organic_colors_and_meet_minimum_target():
    """Primary/secondary buttons use brand greens, have organic hover/focus, and 44px min touch target."""
    css = _style_css()
    anchor_bodies = _rule_bodies(css, ".home .bioco-home-button .et_pb_button")
    assert anchor_bodies, "Button styles must target the descendant .et_pb_button anchor"

    primary_bodies = _rule_bodies(css, ".home .bioco-home-button--primary .et_pb_button")
    assert primary_bodies, "Primary modifier must target its descendant anchor"

    secondary_bodies = _rule_bodies(css, ".home .bioco-home-button--secondary .et_pb_button")
    assert secondary_bodies, "Secondary modifier must target its descendant anchor"

    all_button_css = " ".join(anchor_bodies + primary_bodies + secondary_bodies).lower()
    assert "--wp--preset--color--bioco-green" in all_button_css or "#2e7d32" in all_button_css, "Brand green missing"
    assert any(token in all_button_css for token in ("min-height: 44px", "min-height: 48px", "min-height: 52px", "min-width: 44px")), "Minimum 44px target missing"

    full_css = _style_css().lower()
    assert ".bioco-home-button--primary .et_pb_button:hover" in full_css, "Primary button hover missing"
    assert ".bioco-home-button--secondary .et_pb_button:hover" in full_css, "Secondary button hover missing"
    assert ".bioco-home-button .et_pb_button:focus-visible" in full_css, "Button focus-visible missing"


def test_cta_is_scoped_and_responsive():
    """CTA section is scoped to .home and uses responsive spacing."""
    css = _style_css()
    assert ".home .bioco-home-cta" in css, "CTA selector missing"
    assert ".bioco-home-cta-content" in css, "CTA content selector missing"
    assert "clamp(" in css, "Responsive clamp() missing"


def test_mobile_geometry_is_wide_compact_and_keeps_header_outside_hero():
    css = _style_css()
    for token in (
        "height: 552px",
        "margin: 0 4px 0",
        "width: calc(100% - 46.8px)",
        "width: calc(100% - 93.6px)",
        "height: 421px",
        "margin: 32px 0 0",
        "font-size: 24px",
        "line-height: 28.8px",
    ):
        assert token in css


def test_mobile_home_buttons_and_copy_override_deferred_divi_sizes():
    css = _style_css()
    button = " ".join(_rule_bodies(css, ".home .bioco-home-button.et_pb_button")).lower()
    assert "font-size: 16px !important" in button
    assert "line-height: 24px !important" in button
    assert "padding: 12px 24px !important" in button
    assert "padding: 0" in css
    assert "margin-bottom: 84px" in css
    assert "font-weight: 400" in css


def test_css_has_no_global_hacks_or_forbidden_blocks():
    """Styles stay scoped and do not use global resets, sidebar hacks, or forbidden block types."""
    css = _style_css()
    assert not re.search(r"#sidebar\s*\{\s*display:\s*none", css), "No sidebar hide hack"
    assert "* {" not in css, "No global reset"
    assert "!important" not in css or css.count("!important") <= 16, "Avoid blanket !important"
    for forbidden in ("shortcode", "core/html", "divi/code"):
        assert forbidden not in css, f"Forbidden reference {forbidden!r} in CSS"
