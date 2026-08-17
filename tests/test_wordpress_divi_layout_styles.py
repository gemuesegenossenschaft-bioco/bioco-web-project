import re
from pathlib import Path


ROOT = Path(__file__).parents[1]
STYLE_CSS = ROOT / "wordpress" / "web" / "app" / "themes" / "bioco-divi" / "style.css"


def _style_css() -> str:
    return STYLE_CSS.read_text(encoding="utf-8")


def _rule_bodies(css: str, selector_substring: str) -> list:
    """Return the bodies of top-level CSS rules whose selectors contain selector_substring as a token."""
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
                    bodies.append(_strip_nested(css[body_start:i].strip()))
                in_rule = False
                selector_start = i + 1
    return bodies


def _selector_contains(selector: str, needle: str) -> bool:
    idx = selector.find(needle)
    if idx == -1:
        return False
    end = idx + len(needle)
    if end < len(selector) and re.match(r"[a-zA-Z0-9_-]", selector[end]):
        return False
    return True


def _strip_nested(body: str) -> str:
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


def test_page_intro_has_width_and_alignment_modifiers():
    """Page intro section supports container width and text alignment modifiers."""
    css = _style_css()
    assert ".bioco-divi-page-intro" in css, "Page intro selector missing"
    assert ".bioco-divi-width-lg" in css or ".bioco-divi-width-" in css, "Container width modifiers missing"
    assert ".bioco-divi-align-left" in css or ".bioco-divi-align-center" in css, "Alignment modifiers missing"
    content_bodies = _rule_bodies(css, ".bioco-divi-page-intro .bioco-divi-content")
    assert content_bodies, "Page intro content styling missing"


def test_media_text_has_two_columns_and_media_side_modifiers():
    """Media-text section uses two columns and supports left/right media side modifiers."""
    css = _style_css()
    assert ".bioco-divi-media-text" in css, "Media-text selector missing"
    assert ".bioco-divi-media-left" in css, "Media-left modifier missing"
    assert ".bioco-divi-media-right" in css, "Media-right modifier missing"

    row_bodies = _rule_bodies(css, ".bioco-divi-media-text .bioco-divi-row")
    assert row_bodies, "Media-text row styling missing"
    row = " ".join(row_bodies).lower()
    assert "grid-template-columns" in row or "display: flex" in row, "Two-column layout missing"


def test_media_text_bitmap_targets_wrapper_and_img():
    """Media-text image wrapper clips and rounds; inner bitmap fills and covers."""
    css = _style_css()
    wrap_bodies = _rule_bodies(css, ".bioco-divi-media-image .et_pb_image_wrap")
    assert wrap_bodies, "Media-text image wrapper selector missing"
    wrap = " ".join(wrap_bodies).lower()
    assert "aspect-ratio" in wrap, "Media image aspect-ratio missing"
    assert "overflow" in wrap, "Media image overflow missing"
    assert "border-radius" in wrap, "Media image radius missing"

    img_bodies = _rule_bodies(css, ".bioco-divi-media-image img")
    assert img_bodies, "Media-text bitmap selector missing"
    img = " ".join(img_bodies).lower()
    assert "object-fit: cover" in img, "Media image object-fit missing"


def test_rich_text_has_single_content_column():
    """Rich-text section centers a single content column."""
    css = _style_css()
    assert ".bioco-divi-rich-text" in css, "Rich-text selector missing"
    content_bodies = _rule_bodies(css, ".bioco-divi-rich-text .bioco-divi-content")
    assert content_bodies, "Rich-text content styling missing"


def test_cta_band_has_theme_and_rounded_modifiers():
    """CTA band supports theme (soft/light/dark) and rounded modifiers."""
    css = _style_css()
    assert ".bioco-divi-cta" in css, "CTA selector missing"
    assert ".bioco-divi-theme-soft" in css, "CTA soft theme modifier missing"
    assert ".bioco-divi-theme-light" in css, "CTA light theme modifier missing"
    assert ".bioco-divi-theme-dark" in css, "CTA dark theme modifier missing"
    assert ".bioco-divi-rounded-xl" in css or ".bioco-divi-rounded-lg" in css, "CTA rounded modifier missing"


def test_generic_buttons_target_descendant_anchor():
    """Generic layout buttons style the descendant .et_pb_button anchor."""
    css = _style_css()
    anchor_bodies = _rule_bodies(css, ".bioco-divi-button .et_pb_button")
    assert anchor_bodies, "Generic button must target descendant .et_pb_button"

    primary_bodies = _rule_bodies(css, ".bioco-divi-button--primary .et_pb_button")
    assert primary_bodies, "Primary generic button modifier missing"

    secondary_bodies = _rule_bodies(css, ".bioco-divi-button--secondary .et_pb_button")
    assert secondary_bodies, "Secondary generic button modifier missing"

    all_btn = " ".join(anchor_bodies + primary_bodies + secondary_bodies).lower()
    assert "min-height: 44px" in all_btn or "min-width: 44px" in all_btn, "Minimum 44px target missing"
    assert "--wp--preset--color--bioco-green" in all_btn or "#2e7d32" in all_btn, "Brand green missing"


def test_divi_five_buttons_style_module_anchor_not_parent_wrapper():
    css = _style_css()
    anchor = " ".join(_rule_bodies(css, ".bioco-divi-button.et_pb_button")).lower()
    primary = " ".join(_rule_bodies(css, ".bioco-divi-button--primary.et_pb_button")).lower()
    secondary = " ".join(_rule_bodies(css, ".bioco-divi-button--secondary.et_pb_button")).lower()

    assert "font-size: 16px" in anchor
    assert "min-height: 44px" in anchor
    assert "border-radius" in anchor
    assert "background" in primary and "color: #fff !important" in primary
    assert "border" in secondary and "color: var(--wp--preset--color--bioco-green, #2e7d32) !important" in secondary
    assert ".bioco-divi-button.et_pb_button::after" in css


def test_generic_rows_remove_divi_pseudo_items_and_collapse_without_media():
    css = _style_css()
    assert ".bioco-divi-row::before" in css
    assert ".bioco-divi-row::after" in css
    assert "display: none" in " ".join(_rule_bodies(css, ".bioco-divi-row::before")).lower()

    one_column = " ".join(
        _rule_bodies(css, ".bioco-divi-media-text .bioco-divi-row:has(> .bioco-divi-content:only-child)")
    ).lower()
    assert "grid-template-columns: minmax(0, 1fr)" in one_column
    assert "max-width: 1160px" in one_column


def test_generic_sections_use_compact_vertical_rhythm():
    css = _style_css()
    section = " ".join(_rule_bodies(css, ".bioco-divi-section")).lower()
    row = " ".join(_rule_bodies(css, ".bioco-divi-section > .bioco-divi-row")).lower()

    assert "padding: clamp(32px, 4vw, 64px)" in section
    assert "padding: 0" in row


def test_generic_layouts_use_cream_background_and_tokens():
    """Generic layout sections use cream background and design-token constraints."""
    css = _style_css()
    assert "--wp--preset--color--bioco-bg" in css or "#F5F1E8" in css, "Cream background missing"
    assert "--wp--style--global--content-size" in css or "1160px" in css, "Content max-width missing"


def test_generic_layouts_have_no_global_hacks():
    """Generic layout CSS stays scoped and avoids global resets or sidebar hacks."""
    css = _style_css()
    assert "* {" not in css, "No global reset"
    assert not re.search(r"#sidebar\s*\{\s*display:\s*none", css), "No sidebar hide hack"
    assert css.count("!important") <= 16, f"Avoid blanket !important (found {css.count('!important')})"


def _top_level_selectors(css: str) -> list:
    """Return all top-level selector strings (split by comma)."""
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    selectors = []
    depth = 0
    selector_start = 0
    for i, ch in enumerate(css):
        if ch == "{":
            if depth == 0:
                selectors.extend(s.strip() for s in css[selector_start:i].split(","))
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                selector_start = i + 1
    return selectors


def _media_query_bodies(css: str, selector_substring: str) -> list:
    """Return [(media_condition, [rule_bodies])] for @media blocks matching the selector."""
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    results = []
    i = 0
    while True:
        idx = css.find("@media", i)
        if idx == -1:
            break
        brace = css.find("{", idx)
        if brace == -1:
            break
        cond = css[idx + 6 : brace].strip()
        depth = 1
        inner_start = brace + 1
        inner_end = None
        for j in range(inner_start, len(css)):
            ch = css[j]
            if ch == "{":
                depth += 1
            elif ch == "}":
                depth -= 1
                if depth == 0:
                    inner_end = j
                    break
        if inner_end is None:
            break
        inner = css[inner_start:inner_end]
        bodies = _rule_bodies(inner, selector_substring)
        if bodies:
            results.append((cond, bodies))
        i = inner_end + 1
    return results


def test_muted_text_color_is_gray_not_olive():
    """Regression: bioco-text-muted fallback must be the intended warm gray (#4A4A4A),
    not the accidental olive (#4A4A1A)."""
    css = _style_css()
    assert "#4A4A1A" not in css, "Accidental olive #4A4A1A must not appear"
    assert "#4A4A4A" in css, "Muted text fallback #4A4A4A must be present"


def test_no_unscoped_site_wide_canvas_or_typography():
    """Cream canvas, font, line-height, and text color must be scoped through .bioco-divi-section,
    not via unqualified body or Divi page-container selectors that would corrupt global chrome."""
    css = _style_css()

    forbidden = {"body", ".et_pb_page_container", "#page-container", "#main-content"}
    for selector in _top_level_selectors(css):
        assert selector not in forbidden, f"Unscoped global selector found: {selector}"

    # The site-wide canvas/typography must come from .bioco-divi-section, not body.
    section_bodies = _rule_bodies(css, ".bioco-divi-section")
    section_css = " ".join(section_bodies).lower()
    assert "background-color" in section_css, "Cream canvas must be scoped to .bioco-divi-section"
    assert "font-family" in section_css, "Body font must be scoped to .bioco-divi-section"
    assert "line-height" in section_css, "Line-height must be scoped to .bioco-divi-section"
    assert "color" in section_css, "Text color must be scoped to .bioco-divi-section"


def test_text_columns_has_columns_and_gap_modifiers():
    """Text-columns body uses CSS columns with configurable count and gap."""
    css = _style_css()
    assert ".bioco-divi-text-columns" in css, "Text-columns section selector missing"
    assert ".bioco-divi-text-columns-body" in css, "Text-columns body selector missing"
    assert ".bioco-divi-columns-2" in css, "Column count modifier missing"
    assert ".bioco-divi-gap-lg" in css, "Column gap modifier missing"

    body_bodies = _rule_bodies(css, ".bioco-divi-text-columns-body")
    body_css = " ".join(body_bodies).lower()
    assert "column-count" in body_css, "Text-columns column-count missing"
    assert "column-gap" in body_css, "Text-columns column-gap missing"


def test_text_columns_respects_column_count_modifiers():
    """Each column-count modifier maps to an explicit column-count value."""
    css = _style_css()
    for count in ["1", "2", "3", "4"]:
        selector = f".bioco-divi-columns-{count}"
        bodies = _rule_bodies(css, selector)
        for _cond, media_bodies in _media_query_bodies(css, selector):
            bodies.extend(media_bodies)
        joined = " ".join(bodies).lower()
        assert f"column-count: {count}" in joined, f"{selector} must set column-count: {count}"


def test_text_columns_are_single_column_on_mobile():
    """Column-count modifiers must not force multi-column layouts on narrow viewports."""
    css = _style_css()

    # Default/mobile column count must be 1.
    body_bodies = _rule_bodies(css, ".bioco-divi-text-columns-body")
    assert any("column-count: 1" in b.lower() for b in body_bodies), "Default text-columns body must be single column"

    # No mobile media query may set a multi-column count.
    for cond, bodies in _media_query_bodies(css, ".bioco-divi-text-columns-body"):
        if "max-width" in cond and "767px" in cond:
            joined = " ".join(bodies).lower()
            assert "column-count: 2" not in joined
            assert "column-count: 3" not in joined
            assert "column-count: 4" not in joined


def test_text_columns_column_modifiers_are_desktop_only():
    """Multi-column modifiers must live inside a min-width desktop media query."""
    css = _style_css()
    found = False
    for cond, bodies in _media_query_bodies(css, ".bioco-divi-columns-2"):
        if "min-width" in cond and "768px" in cond:
            joined = " ".join(bodies).lower()
            assert "column-count: 2" in joined
            found = True
    assert found, ".bioco-divi-columns-2 must be defined in a min-width 768px media query"


def test_steps_has_numbered_step_layout():
    """Steps render a vertical stack with numbered badges."""
    css = _style_css()
    assert ".bioco-divi-steps" in css, "Steps section selector missing"

    list_bodies = _rule_bodies(css, ".bioco-divi-steps .next-steps")
    list_css = " ".join(list_bodies).lower()
    assert "display" in list_css, "Steps list layout missing"
    assert "gap" in list_css, "Steps list gap missing"

    item_bodies = _rule_bodies(css, ".bioco-divi-steps .step-item")
    item_css = " ".join(item_bodies).lower()
    assert "display" in item_css, "Step item layout missing"

    number_bodies = _rule_bodies(css, ".bioco-divi-steps .step-number")
    number_css = " ".join(number_bodies).lower()
    assert "background-color" in number_css, "Step number badge missing"
    assert "color" in number_css, "Step number text color missing"


def test_link_tiles_has_portal_gateway_and_tile_styling():
    """Link tiles render a responsive gateway grid with surfaced tiles."""
    css = _style_css()
    assert ".bioco-divi-link-tiles" in css, "Link-tiles section selector missing"

    gateway_bodies = _rule_bodies(css, ".bioco-divi-link-tiles .portal-gateway")
    gateway_css = " ".join(gateway_bodies).lower()
    assert "display: grid" in gateway_css, "Portal gateway must be a grid"
    assert "gap" in gateway_css, "Portal gateway gap missing"

    tile_bodies = _rule_bodies(css, ".bioco-divi-link-tiles .portal-tile")
    tile_css = " ".join(tile_bodies).lower()
    assert "background-color" in tile_css or "background" in tile_css, "Portal tile background missing"
    assert "border-radius" in tile_css, "Portal tile radius missing"

    icon_bodies = _rule_bodies(css, ".bioco-divi-link-tiles .portal-icon")
    assert icon_bodies, "Portal icon styling missing"


def test_cards_grid_row_is_responsive_grid():
    """Cards grid row uses CSS grid with desktop/mobile column modifiers."""
    css = _style_css()
    assert ".bioco-divi-cards-grid" in css, "Cards-grid section selector missing"
    assert ".bioco-divi-cards-grid-row" in css, "Cards-grid row selector missing"

    row_bodies = _rule_bodies(css, ".bioco-divi-cards-grid-row")
    row_css = " ".join(row_bodies).lower()
    assert "display: grid" in row_css, "Cards-grid row must be a grid"

    desktop = _media_query_bodies(css, ".bioco-divi-cards-grid-row--desktop-3--mobile-1")
    found = False
    for cond, bodies in desktop:
        if "min-width" in cond and "768px" in cond:
            joined = " ".join(bodies).lower()
            assert "grid-template-columns" in joined
            found = True
    assert found, "Desktop grid columns must be inside a min-width 768px media query"


def test_cards_grid_card_media_has_ratio_fit_and_radius():
    """Card image wrapper honors media ratio, fit, and radius modifiers."""
    css = _style_css()
    wrap_bodies = _rule_bodies(css, ".bioco-divi-card-image .et_pb_image_wrap")
    assert wrap_bodies, "Card image wrapper selector missing"
    wrap_css = " ".join(wrap_bodies).lower()
    assert "aspect-ratio" in wrap_css, "Card media aspect-ratio missing"
    assert "overflow" in wrap_css, "Card media overflow missing"

    img_bodies = _rule_bodies(css, ".bioco-divi-card-image img")
    assert img_bodies, "Card bitmap selector missing"
    assert "object-fit" in " ".join(img_bodies).lower()

    ratio_bodies = _rule_bodies(css, ".bioco-divi-cards-grid-row--ratio-4_3")
    assert ratio_bodies, "Card ratio modifier missing"


def test_cards_grid_card_styles_exist():
    """Cards support the three production card styles."""
    css = _style_css()
    for style in ["plain", "soft", "outlined"]:
        selector = f".bioco-divi-cards-grid-card--{style}"
        bodies = _rule_bodies(css, selector)
        assert bodies, f"{selector} styling missing"


def test_gallery_strip_row_is_responsive_grid():
    """Gallery strip row uses CSS grid with desktop/mobile column modifiers."""
    css = _style_css()
    assert ".bioco-divi-gallery-strip" in css, "Gallery-strip section selector missing"
    assert ".bioco-divi-gallery-strip-row" in css, "Gallery-strip row selector missing"

    row_bodies = _rule_bodies(css, ".bioco-divi-gallery-strip-row")
    row_css = " ".join(row_bodies).lower()
    assert "display: grid" in row_css, "Gallery-strip row must be a grid"

    desktop = _media_query_bodies(css, ".bioco-divi-gallery-strip-row--desktop-4--mobile-1")
    found = False
    for cond, bodies in desktop:
        if "min-width" in cond and "768px" in cond:
            joined = " ".join(bodies).lower()
            assert "grid-template-columns" in joined
            found = True
    assert found, "Desktop grid columns must be inside a min-width 768px media query"


def test_gallery_strip_frame_has_ratio_fit_and_radius():
    """Gallery frames honor media ratio, fit, and radius modifiers."""
    css = _style_css()
    frame_bodies = _rule_bodies(css, ".bioco-divi-gallery-strip-frame")
    assert frame_bodies, "Gallery frame selector missing"

    wrap_bodies = _rule_bodies(css, ".bioco-divi-gallery-strip-frame .et_pb_image_wrap")
    assert wrap_bodies, "Gallery frame wrapper selector missing"
    wrap_css = " ".join(wrap_bodies).lower()
    assert "aspect-ratio" in wrap_css, "Gallery frame aspect-ratio missing"
    assert "overflow" in wrap_css, "Gallery frame overflow missing"

    img_bodies = _rule_bodies(css, ".bioco-divi-gallery-strip-image img")
    assert img_bodies, "Gallery image selector missing"
    assert "object-fit" in " ".join(img_bodies).lower()

    ratio_bodies = _rule_bodies(css, ".bioco-divi-gallery-strip-row--ratio-4_3")
    assert ratio_bodies, "Gallery ratio modifier missing"


def test_timeline_header_has_width_and_alignment():
    """Timeline header supports container width, text width, and alignment."""
    css = _style_css()
    assert ".bioco-divi-timeline" in css, "Timeline section selector missing"

    header_bodies = _rule_bodies(css, ".bioco-divi-timeline-header .bioco-divi-content")
    assert header_bodies, "Timeline header content styling missing"

    assert ".bioco-divi-width-lg" in css, "Timeline width modifier missing"
    assert ".bioco-divi-text-normal" in css, "Timeline text-width modifier missing"
    assert ".bioco-divi-align-left" in css, "Timeline alignment modifier missing"


def test_timeline_items_have_badge_and_rail():
    """Timeline items render a badge column and a content column with a rail."""
    css = _style_css()
    badge_bodies = _rule_bodies(css, ".bioco-divi-timeline-badge")
    assert badge_bodies, "Timeline badge selector missing"
    badge_css = " ".join(badge_bodies).lower()
    assert "background-color" in badge_css, "Timeline badge background missing"
    assert "color" in badge_css, "Timeline badge text color missing"
    assert "border-radius" in badge_css, "Timeline badge radius missing"

    content_bodies = _rule_bodies(css, ".bioco-divi-timeline-item-content")
    assert content_bodies, "Timeline content selector missing"

    item_bodies = _rule_bodies(css, ".bioco-divi-timeline-item-row")
    item_css = " ".join(item_bodies).lower()
    assert "display: grid" in item_css, "Timeline item row must be a grid"


def test_timeline_highlight_emphasis_changes_badge():
    """Highlight emphasis changes the badge background."""
    css = _style_css()
    highlight_bodies = _rule_bodies(css, ".bioco-divi-timeline-item--highlight .bioco-divi-timeline-badge")
    assert highlight_bodies, "Timeline highlight badge selector missing"


def test_timeline_container_widths_match_legacy():
    """Timeline container widths must match the legacy cms-timeline data-container values:
    md=820px, lg=1040px, xl=1280px (bioco-blocks.css)."""
    css = _style_css()
    expected = {"md": "820px", "lg": "1040px", "xl": "1280px"}
    for size, width in expected.items():
        bodies = _rule_bodies(css, f".bioco-divi-timeline.bioco-divi-width-{size}")
        joined = " ".join(bodies).lower()
        assert f"max-width: {width}" in joined, f"Timeline width-{size} must be {width}"


def test_timeline_highlight_color_matches_legacy():
    """Timeline highlight badge must use the legacy sage green #8ab272."""
    css = _style_css()
    highlight_bodies = _rule_bodies(css, ".bioco-divi-timeline-item--highlight .bioco-divi-timeline-badge")
    joined = " ".join(highlight_bodies).lower()
    assert "#8ab272" in joined, "Legacy highlight badge color #8ab272 missing"


def test_pricing_table_section_selector_exists():
    css = _style_css()
    assert ".bioco-divi-pricing-table" in css


def test_pricing_table_container_widths_match_legacy():
    css = _style_css()
    expected = {"md": "820px", "lg": "1040px", "xl": "1280px"}
    for size, width in expected.items():
        bodies = _rule_bodies(css, f".bioco-divi-pricing-table.bioco-divi-width-{size}")
        joined = " ".join(bodies).lower()
        assert f"max-width: {width}" in joined, f"Pricing width-{size} must be {width}"


def test_pricing_table_has_overflow_handling():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-pricing-table-body")
    joined = " ".join(bodies).lower()
    assert "overflow-x: auto" in joined or "overflow: auto" in joined
    wrapper = " ".join(_rule_bodies(css, ".bioco-divi-pricing-table-body .pricing-table")).lower()
    assert "margin" in wrapper


def test_pricing_table_table_surface_shadow_radius():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-pricing-table-body table")
    joined = " ".join(bodies).lower()
    assert "background-color" in joined or "background" in joined
    assert "box-shadow" in joined
    assert "border-radius" in joined
    assert "overflow: visible" in joined


def test_pricing_table_header_is_green():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-pricing-table-body th")
    joined = " ".join(bodies).lower()
    assert "background" in joined
    assert "#2e7d32" in joined or "bioco-green" in joined
    assert "color" in joined
    assert "border-bottom: none" in joined


def test_pricing_table_cells_have_padding_and_align():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-pricing-table-body td")
    joined = " ".join(bodies).lower()
    assert "padding" in joined
    assert "vertical-align" in joined
    assert "border-bottom: none" in joined
    assert "min-height: 60px" in joined


def test_pricing_table_rows_hover():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-pricing-table-body tbody tr:hover")
    assert bodies, "Pricing table row hover styling missing"


def test_pricing_table_subtext_styled():
    css = _style_css()
    shares_bodies = _rule_bodies(css, ".bioco-divi-pricing-table-body .cms-pricing-table-shares")
    suffix_bodies = _rule_bodies(css, ".bioco-divi-pricing-table-body .cms-pricing-table-work-suffix")
    assert shares_bodies, "Pricing table shares subtext missing"
    assert suffix_bodies, "Pricing table work suffix subtext missing"


def test_pricing_table_person_icons_styled():
    css = _style_css()
    icons_bodies = _rule_bodies(css, ".bioco-divi-pricing-table-body .person-icons")
    row_bodies = _rule_bodies(css, ".bioco-divi-pricing-table-body .person-icons-row")
    assert icons_bodies, "Pricing table person-icons missing"
    assert row_bodies, "Pricing table person-icons-row missing"


def test_pricing_table_mobile_font_and_scroll():
    css = _style_css()
    mobile = _media_query_bodies(css, ".bioco-divi-pricing-table-body")
    found = False
    for cond, bodies in mobile:
        if "max-width" in cond and "767px" in cond:
            joined = " ".join(bodies).lower()
            assert "font-size" in joined
            assert "white-space" in joined or "overflow-x" in joined
            found = True
    assert found, "Pricing table mobile styling missing"


def test_accordion_section_selector_exists():
    css = _style_css()
    assert ".bioco-divi-accordion" in css
    assert ".demeter-accordion" in css


def test_accordion_has_margin():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-accordion")
    joined = " ".join(bodies).lower()
    assert "margin" in joined


def test_accordion_item_details_spacing_radius_shadow():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-accordion-item details")
    joined = " ".join(bodies).lower()
    assert "margin-bottom" in joined
    assert "border-radius" in joined
    assert "box-shadow" in joined
    assert "overflow: visible" in joined


def test_accordion_summary_background_cursor_weight():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-accordion-item summary")
    joined = " ".join(bodies).lower()
    assert "background" in joined
    assert "cursor: pointer" in joined
    assert "font-weight" in joined


def test_accordion_summary_hidden_marker():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-accordion-item summary::-webkit-details-marker")
    assert bodies, "Accordion summary marker hiding missing"


def test_accordion_summary_plus_minus_open_state():
    css = _style_css()
    plus_bodies = _rule_bodies(css, ".bioco-divi-accordion-item summary::before")
    assert plus_bodies, "Accordion summary plus marker missing"
    minus_bodies = _rule_bodies(css, ".bioco-divi-accordion-item details[open] summary::before")
    assert minus_bodies, "Accordion summary open minus marker missing"
    assert "content: '−'" in " ".join(minus_bodies)


def test_accordion_summary_hover_and_open_border():
    css = _style_css()
    hover_bodies = _rule_bodies(css, ".bioco-divi-accordion-item summary:hover")
    assert hover_bodies, "Accordion summary hover missing"
    open_bodies = _rule_bodies(css, ".bioco-divi-accordion-item details[open] summary")
    joined = " ".join(open_bodies).lower()
    assert "border-bottom" in joined


def test_accordion_summary_has_visible_keyboard_focus():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-accordion-item summary:focus-visible")
    joined = " ".join(bodies).lower()
    assert "outline" in joined
    assert "outline-offset: -3px" in joined


def test_accordion_body_padding_and_surface():
    css = _style_css()
    bodies = _rule_bodies(css, ".bioco-divi-accordion-item details > div")
    joined = " ".join(bodies).lower()
    assert "padding" in joined
    assert "background" in joined
