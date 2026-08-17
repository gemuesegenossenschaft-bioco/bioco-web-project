import base64
import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]


def _compose(item: dict) -> dict:
    payload = base64.b64encode(json.dumps(item).encode("utf-8")).decode("ascii")
    code = (
        "define('ABSPATH', __DIR__);\n"
        "if (!function_exists('wp_get_attachment_image_url')) {\n"
        "    function wp_get_attachment_image_url($id, $size = 'full') {\n"
        "        return $id ? 'https://example.com/image.jpg' : false;\n"
        "    }\n"
        "}\n"
        "if (!function_exists('get_post_meta')) {\n"
        "    function get_post_meta($id, $key, $single = false) {\n"
        "        if ($key !== '_wp_attachment_image_alt') return '';\n"
        "        $alts = [42 => 'Hof von oben', 44 => 'Packraum'];\n"
        "        return $alts[$id] ?? '';\n"
        "    }\n"
        "}\n"
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        f"$item = json_decode(base64_decode('{payload}'), true);\n"
        "echo json_encode(Bioco_Import_Divi_Composer::section($item));"
    )
    result = subprocess.run(
        ["php", "-r", code],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    )
    return json.loads(result.stdout)


def _class(block: dict) -> str:
    return block["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"]


def _all_names(block: dict):
    yield block["blockName"]
    for child in block.get("innerBlocks", []):
        yield from _all_names(child)


def _column(tree: dict, index: int = 0) -> dict:
    return tree["innerBlocks"][0]["innerBlocks"][index]


def _row(tree: dict, index: int = 0) -> dict:
    return tree["innerBlocks"][index]


def _rows(tree: dict) -> list:
    return tree["innerBlocks"]


def _row_column(row: dict, index: int = 0) -> dict:
    return row["innerBlocks"][index]


def test_page_intro_uses_native_heading_text_and_layout_modifiers():
    tree = _compose(
        {
            "block": "page-intro",
            "values": {
                "eyebrow": "Genossenschaft",
                "title": "Unser Gemüse",
                "text": "<p>Frisch vom Hof.</p>",
                "container_width": "lg",
                "text_width": "wide",
                "align": "left",
                "heading_level": "1",
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-page-intro bioco-divi-width-lg bioco-divi-align-left"
    column = _column(tree)
    assert _class(column) == "bioco-divi-content bioco-divi-text-wide"
    eyebrow, heading, text = column["innerBlocks"]
    assert [eyebrow["blockName"], heading["blockName"], text["blockName"]] == [
        "divi/text",
        "divi/heading",
        "divi/text",
    ]
    assert heading["attrs"]["title"]["decoration"]["font"]["font"]["desktop"]["value"]["headingLevel"] == "h1"
    assert set(_all_names(tree)) <= {
        "divi/section",
        "divi/row",
        "divi/column",
        "divi/heading",
        "divi/text",
    }


def test_page_intro_invalid_modifiers_fall_back_to_safe_defaults():
    tree = _compose(
        {
            "block": "page-intro",
            "values": {
                "title": "Titel",
                "container_width": "<script>",
                "text_width": "huge",
                "align": "diagonal",
                "heading_level": "9",
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-page-intro bioco-divi-width-lg bioco-divi-align-left"
    assert _class(_column(tree)) == "bioco-divi-content bioco-divi-text-normal"
    heading = _column(tree)["innerBlocks"][0]
    assert heading["attrs"]["title"]["decoration"]["font"]["font"]["desktop"]["value"]["headingLevel"] == "h2"


def test_generic_rich_text_suppresses_duplicate_heading_and_omits_empty_modules():
    tree = _compose(
        {
            "block": "rich-text",
            "values": {
                "title": "Duplicate",
                "text": "<h2>Own heading</h2><p>Body stays exact.</p>",
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-rich-text"
    children = _column(tree)["innerBlocks"]
    assert [child["blockName"] for child in children] == ["divi/text"]
    assert children[0]["attrs"]["content"]["innerContent"]["desktop"]["value"] == "<h2>Own heading</h2><p>Body stays exact.</p>"


def test_generic_rich_text_buttons_skip_incomplete_rows_and_target_external_links():
    tree = _compose(
        {
            "block": "rich-text",
            "values": {
                "buttons": [
                    {"text": "Extern", "href": "https://example.org", "variant": "primary"},
                    {"text": "Missing URL", "variant": "secondary"},
                    {"href": "/missing-label", "variant": "secondary"},
                ]
            },
        }
    )

    children = _column(tree)["innerBlocks"]
    assert [child["blockName"] for child in children] == ["divi/button"]
    button = children[0]
    value = button["attrs"]["button"]["innerContent"]["desktop"]["value"]
    assert value == {
        "text": "Extern",
        "linkUrl": "https://example.org",
        "linkTarget": "on",
    }
    assert _class(button) == "bioco-divi-button bioco-divi-button--primary"


def test_generic_media_text_keeps_media_first_and_uses_class_for_desktop_side():
    tree = _compose(
        {
            "block": "media-text",
            "values": {
                "title": "Text left on desktop",
                "text": "<p>Body</p>",
                "image": 42,
                "image_alt": "Field",
                "media_side": "right",
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-media-text bioco-divi-media-right"
    row = tree["innerBlocks"][0]
    media, content = row["innerBlocks"]
    assert _class(media) == "bioco-divi-media"
    assert media["innerBlocks"][0]["blockName"] == "divi/image"
    assert _class(content) == "bioco-divi-content"


def test_generic_media_text_without_image_is_one_column_and_has_no_empty_media():
    tree = _compose(
        {
            "block": "media-text",
            "values": {"title": "Only text", "text": "<p>Body</p>"},
        }
    )

    row = tree["innerBlocks"][0]
    assert row["attrs"]["module"]["advanced"]["columnStructure"]["desktop"]["value"] == "4_4"
    assert len(row["innerBlocks"]) == 1
    assert _class(row["innerBlocks"][0]) == "bioco-divi-content"
    assert "divi/image" not in set(_all_names(tree))


def test_cta_band_is_native_and_preserves_per_instance_content():
    tree = _compose(
        {
            "block": "cta-band",
            "values": {
                "title": "Mach mit",
                "text": "<p>Deine CTA.</p>",
                "align": "left",
                "theme": "soft",
                "rounded": "xl",
                "buttons": [{"text": "Los", "href": "/mitmachen", "variant": "primary"}],
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-cta bioco-divi-align-left bioco-divi-theme-soft bioco-divi-rounded-xl"
    assert [child["blockName"] for child in _column(tree)["innerBlocks"]] == [
        "divi/heading",
        "divi/text",
        "divi/button",
    ]
    assert set(_all_names(tree)) <= {
        "divi/section",
        "divi/row",
        "divi/column",
        "divi/heading",
        "divi/text",
        "divi/button",
    }


def test_existing_home_feature_contract_remains_home_specific():
    tree = _compose(
        {
            "block": "rich-text",
            "values": {
                "title": "Home CTA",
                "text": "<p>Body</p>",
                "style_variant": "feature",
            },
        }
    )

    assert _class(tree) == "bioco-home-cta"
    assert _class(_column(tree)) == "bioco-home-cta-content"


ALLOWED_BLOCKS = {
    "divi/section",
    "divi/row",
    "divi/column",
    "divi/heading",
    "divi/text",
    "divi/image",
    "divi/button",
}


def test_text_columns_is_native_and_preserves_content_order():
    tree = _compose(
        {
            "block": "text-columns",
            "values": {
                "eyebrow": "Mission",
                "title": "Solidarität",
                "text": "<h3>Solidarität</h3><p>Wir teilen Arbeit.</p><h3>Nachhaltigkeit</h3><p>Demeter.</p>",
                "image": 42,
                "image_alt": "Mission",
                "container_width": "lg",
                "columns": "2",
                "gap": "lg",
                "buttons": [
                    {"text": "Mehr", "href": "/solawi", "variant": "primary"}
                ],
            },
        }
    )

    assert _class(tree) == (
        "bioco-divi-section bioco-divi-text-columns bioco-divi-width-lg"
    )
    children = _column(tree)["innerBlocks"]
    assert [child["blockName"] for child in children] == [
        "divi/text",
        "divi/text",
        "divi/image",
        "divi/button",
    ]

    eyebrow = children[0]
    assert _class(eyebrow) == "bioco-divi-eyebrow"

    body = children[1]
    assert _class(body) == (
        "bioco-divi-text bioco-divi-text-columns-body bioco-divi-columns-2 bioco-divi-gap-lg"
    )
    assert body["attrs"]["content"]["innerContent"]["desktop"]["value"] == (
        "<h3>Solidarität</h3><p>Wir teilen Arbeit.</p><h3>Nachhaltigkeit</h3><p>Demeter.</p>"
    )

    image = children[2]
    assert image["blockName"] == "divi/image"
    assert _class(image) == "bioco-divi-text-columns-image"

    assert set(_all_names(tree)) <= ALLOWED_BLOCKS


def test_text_columns_invalid_modifiers_fall_back_and_omit_empty_modules():
    tree = _compose(
        {
            "block": "text-columns",
            "values": {
                "title": "Own",
                "text": "<h2>Own</h2><p>Body</p>",
                "container_width": "xxl",
                "columns": "5",
                "gap": "xxl",
                "buttons": [
                    {"text": "No URL", "variant": "primary"},
                    {"href": "/no-label", "variant": "secondary"},
                ],
            },
        }
    )

    assert _class(tree) == (
        "bioco-divi-section bioco-divi-text-columns bioco-divi-width-lg"
    )
    children = _column(tree)["innerBlocks"]
    assert [child["blockName"] for child in children] == ["divi/text"]
    body = children[0]
    assert "bioco-divi-columns-2" in _class(body)
    assert "bioco-divi-gap-lg" in _class(body)
    assert "divi/image" not in set(_all_names(tree))
    assert "divi/button" not in set(_all_names(tree))


def test_pricing_table_is_native_and_uses_only_allowlist_blocks():
    tree = _compose(
        {
            "block": "pricing-table",
            "values": {
                "title": "Gemüse-Abos",
                "text": "<p>Unsere Abo-Modelle.</p>",
                "container_width": "xl",
                "work_suffix": "à 2 Stunden",
                "tiers": [
                    {
                        "name": "Halb",
                        "shares": "1 Anteilsschein",
                        "persons": 1,
                        "price": "CHF 750.-",
                        "sharecost": "CHF 250.-",
                        "work": "10 Arbeitseinsätze",
                    },
                    {
                        "name": "Standard",
                        "shares": "2 Anteilsscheine",
                        "persons": 2,
                        "price": "CHF 1'280.-",
                        "sharecost": "CHF 500.-",
                        "work": "20 Arbeitseinsätze",
                    },
                ],
                "buttons": [
                    {"text": "Jetzt Abo wählen", "href": "/bioco-werden", "variant": "primary"}
                ],
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-pricing-table bioco-divi-width-xl"
    assert set(_all_names(tree)) <= ALLOWED_BLOCKS


def test_pricing_table_header_uses_header_blocks_and_suppresses_duplicate_heading():
    tree = _compose(
        {
            "block": "pricing-table",
            "values": {
                "eyebrow": "Abo",
                "title": "Gemüse-Abos",
                "text": "<h2>Gemüse-Abos</h2><p>Intro text.</p>",
                "tiers": [{"name": "Halb", "persons": 1}],
            },
        }
    )

    children = _column(tree)["innerBlocks"]
    assert [child["blockName"] for child in children] == ["divi/text", "divi/text", "divi/text"]
    assert children[0]["attrs"]["content"]["innerContent"]["desktop"]["value"] == "Abo"
    assert children[1]["attrs"]["content"]["innerContent"]["desktop"]["value"] == "<h2>Gemüse-Abos</h2><p>Intro text.</p>"


def test_pricing_table_renders_default_german_column_labels():
    tree = _compose(
        {
            "block": "pricing-table",
            "values": {
                "tiers": [{"name": "Halb", "persons": 1}],
            },
        }
    )

    html = _column(tree)["innerBlocks"][0]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    assert "<th scope=\"col\">Gemüsekorb</th>" in html
    assert "<th scope=\"col\">Personen</th>" in html
    assert "<th scope=\"col\">Jahrespreis</th>" in html
    assert "<th scope=\"col\">Anteilsscheine Kosten</th>" in html
    assert "<th scope=\"col\">Mitarbeit pro Jahr</th>" in html


def test_pricing_table_preserves_tier_order_and_omits_untitled():
    tree = _compose(
        {
            "block": "pricing-table",
            "values": {
                "tiers": [
                    {"name": "Erst", "persons": 1},
                    {"name": "", "persons": 2},
                    {"name": "Dritt", "persons": 4},
                ],
            },
        }
    )

    html = _column(tree)["innerBlocks"][0]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    tbody_start = html.find("<tbody>")
    tbody_end = html.find("</tbody>", tbody_start)
    tbody = html[tbody_start:tbody_end]
    assert tbody.count("<tr>") == 2
    assert "<strong>Erst</strong>" in tbody
    assert "<strong>Dritt</strong>" in tbody


def test_pricing_table_escapes_scalar_cells_and_titles():
    tree = _compose(
        {
            "block": "pricing-table",
            "values": {
                "tiers": [
                    {
                        "name": "<script>alert(1)</script>",
                        "shares": "<b>shares</b>",
                        "persons": 2,
                        "price": "<em>price</em>",
                        "sharecost": "<span>cost</span>",
                        "work": "<strong>work</strong>",
                    }
                ],
                "work_suffix": "<i>suffix</i>",
            },
        }
    )

    html = _column(tree)["innerBlocks"][0]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    assert "<script>" not in html
    assert "&lt;script&gt;alert(1)&lt;/script&gt;" in html
    assert "&lt;b&gt;shares&lt;/b&gt;" in html
    assert "&lt;em&gt;price&lt;/em&gt;" in html
    assert "&lt;span&gt;cost&lt;/span&gt;" in html
    assert "&lt;strong&gt;work&lt;/strong&gt;" in html
    assert "&lt;i&gt;suffix&lt;/i&gt;" in html


def test_pricing_table_person_icons_match_one_two_four_svg_and_are_aria_hidden():
    tree = _compose(
        {
            "block": "pricing-table",
            "values": {
                "tiers": [
                    {"name": "Eins", "persons": 1},
                    {"name": "Zwei", "persons": 2},
                    {"name": "Vier", "persons": 4},
                ],
            },
        }
    )

    html = _column(tree)["innerBlocks"][0]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    # One person: one row with one svg; two persons: one row with two svgs;
    # four persons: two rows with two svgs each -> 1 + 1 + 2 = 4 rows total.
    assert html.count('class="person-icons-row"') == 4
    # Total svgs rendered: 1 + 2 + 4 = 7.
    assert html.count('aria-hidden="true"') == 7
    assert html.count('focusable="false"') == 7
    assert html.count('role="img"') == 3
    assert 'aria-label="1 Person"' in html
    assert 'aria-label="2 Personen"' in html
    assert 'aria-label="4 Personen"' in html
    # Verify 4-person split: two rows of two (and no other rows for that tier).
    vier_pos = html.find("<strong>Vier</strong>")
    vier_end = html.find("</tr>", vier_pos)
    vier_row = html[vier_pos:vier_end]
    assert vier_row.count('class="person-icons-row"') == 2


def test_pricing_table_work_suffix_and_shares_preserved():
    tree = _compose(
        {
            "block": "pricing-table",
            "values": {
                "work_suffix": "à 2 Stunden",
                "tiers": [
                    {
                        "name": "Halb",
                        "shares": "1 Anteilsschein",
                        "persons": 1,
                        "work": "10 Arbeitseinsätze",
                    }
                ],
            },
        }
    )

    html = _column(tree)["innerBlocks"][0]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    assert "10 Arbeitseinsätze" in html
    assert "à 2 Stunden" in html
    assert "1 Anteilsschein" in html


def test_pricing_table_actions_render_once_after_table():
    tree = _compose(
        {
            "block": "pricing-table",
            "values": {
                "title": "Titel",
                "text": "<p>Text</p>",
                "tiers": [{"name": "Halb", "persons": 1}],
                "buttons": [
                    {"text": "A", "href": "/a", "variant": "primary"},
                    {"text": "B", "href": "https://example.org", "variant": "secondary"},
                ],
            },
        }
    )

    children = _column(tree)["innerBlocks"]
    assert [child["blockName"] for child in children] == [
        "divi/heading", "divi/text", "divi/text", "divi/button", "divi/button"
    ]
    # Buttons come after the table text module; only the external one targets _blank.
    first = children[-2]
    second = children[-1]
    assert first["attrs"]["button"]["innerContent"]["desktop"]["value"]["text"] == "A"
    assert first["attrs"]["button"]["innerContent"]["desktop"]["value"]["linkTarget"] == "off"
    assert second["attrs"]["button"]["innerContent"]["desktop"]["value"]["text"] == "B"
    assert second["attrs"]["button"]["innerContent"]["desktop"]["value"]["linkTarget"] == "on"


def test_pricing_table_container_width_modifiers():
    for width, expected in [("md", "md"), ("lg", "lg"), ("xl", "xl"), ("full", "xl")]:
        tree = _compose(
            {
                "block": "pricing-table",
                "values": {"container_width": width, "tiers": [{"name": "Halb", "persons": 1}]},
            }
        )
        assert _class(tree) == f"bioco-divi-section bioco-divi-pricing-table bioco-divi-width-{expected}"


def test_pricing_table_empty_tiers_omit_table_module():
    tree = _compose(
        {
            "block": "pricing-table",
            "values": {
                "title": "Leer",
                "text": "<p>Keine Tiers.</p>",
                "tiers": [],
            },
        }
    )

    children = _column(tree)["innerBlocks"]
    assert [child["blockName"] for child in children] == ["divi/heading", "divi/text"]


def test_accordion_is_native_and_uses_only_allowlist_blocks():
    tree = _compose(
        {
            "block": "accordion",
            "values": {
                "items": [
                    {"title": "Erstes", "body": "<p>Body eins.</p>"},
                    {"title": "Zweites", "body": "<p>Body zwei.</p>"},
                ],
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-accordion demeter-accordion"
    assert set(_all_names(tree)) <= ALLOWED_BLOCKS


def test_accordion_one_text_module_per_item():
    tree = _compose(
        {
            "block": "accordion",
            "values": {
                "items": [
                    {"title": "A", "body": "<p>a</p>"},
                    {"title": "B", "body": "<p>b</p>"},
                    {"title": "C", "body": "<p>c</p>"},
                ],
            },
        }
    )

    children = _column(tree)["innerBlocks"]
    assert [child["blockName"] for child in children] == ["divi/text", "divi/text", "divi/text"]


def test_accordion_each_module_has_semantic_details_summary_div():
    tree = _compose(
        {
            "block": "accordion",
            "values": {
                "items": [
                    {"title": "Titel", "body": "<p>Rich body.</p>"},
                ],
            },
        }
    )

    html = _column(tree)["innerBlocks"][0]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    assert html.startswith("<details>")
    assert "<summary>" in html
    assert "</summary>" in html
    assert "<div>" in html and "</div>" in html
    assert "</details>" in html


def test_accordion_preserves_group_order():
    tree = _compose(
        {
            "block": "accordion",
            "values": {
                "items": [
                    {"title": "Alpha", "body": "<p>1</p>"},
                    {"title": "Beta", "body": "<p>2</p>"},
                    {"title": "Gamma", "body": "<p>3</p>"},
                ],
            },
        }
    )

    children = _column(tree)["innerBlocks"]
    htmls = [
        child["attrs"]["content"]["innerContent"]["desktop"]["value"]
        for child in children
    ]
    assert "Alpha" in htmls[0]
    assert "Beta" in htmls[1]
    assert "Gamma" in htmls[2]


def test_accordion_title_is_escaped_and_body_html_preserved():
    tree = _compose(
        {
            "block": "accordion",
            "values": {
                "items": [
                    {
                        "title": "<script>alert(1)</script>",
                        "body": "<p><strong>Bold</strong> und <a href=\"/link\">Link</a>.</p>",
                    }
                ],
            },
        }
    )

    html = _column(tree)["innerBlocks"][0]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    assert "<script>" not in html
    assert "&lt;script&gt;alert(1)&lt;/script&gt;" in html
    assert "<p><strong>Bold</strong> und <a href=\"/link\">Link</a>.</p>" in html


def test_accordion_omits_blank_items():
    tree = _compose(
        {
            "block": "accordion",
            "values": {
                "items": [
                    {"title": "", "body": ""},
                    {"title": "Gültig", "body": "<p>Ja.</p>"},
                    {"title": "   ", "body": "   "},
                ],
            },
        }
    )

    children = _column(tree)["innerBlocks"]
    assert len(children) == 1
    html = children[0]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    assert "Gültig" in html


def test_accordion_empty_items_omit_section_content():
    tree = _compose(
        {
            "block": "accordion",
            "values": {
                "items": [
                    {"title": "", "body": ""},
                ],
            },
        }
    )

    children = _column(tree)["innerBlocks"]
    assert children == []


def test_accordion_has_legacy_editable_class_names():
    tree = _compose(
        {
            "block": "accordion",
            "values": {
                "items": [{"title": "Titel", "body": "<p>Body</p>"}],
            },
        }
    )

    section_class = _class(tree)
    assert "bioco-divi-accordion" in section_class
    assert "demeter-accordion" in section_class
    body_class = _class(_column(tree)["innerBlocks"][0])
    assert "bioco-divi-accordion-item" in body_class


def test_steps_are_numbered_and_skip_blank_items():
    tree = _compose(
        {
            "block": "steps",
            "values": {
                "title": "Nächste Schritte",
                "items": [
                    {"title": "Eins", "text": "Erster Schritt."},
                    {"title": "", "text": ""},
                    {"title": "Zwei", "text": "Zweiter Schritt."},
                    {"title": "Drei", "text": "Dritter Schritt."},
                ],
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-steps"
    children = _column(tree)["innerBlocks"]
    assert [child["blockName"] for child in children] == ["divi/heading", "divi/text"]
    assert children[0]["attrs"]["title"]["decoration"]["font"]["font"]["desktop"]["value"]["headingLevel"] == "h2"

    html = children[1]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    assert '<div class="next-steps">' in html
    assert '<div class="step-number">1</div>' in html
    assert '<div class="step-number">2</div>' in html
    assert '<div class="step-number">3</div>' in html
    assert html.count('class="step-item"') == 3
    assert '<h3>Eins</h3>' in html
    assert '<p>Dritter Schritt.</p>' in html
    assert set(_all_names(tree)) <= ALLOWED_BLOCKS


def test_link_tiles_render_portal_markup_and_preserve_href_or_div():
    tree = _compose(
        {
            "block": "link-tiles",
            "values": {
                "title": "Gateway",
                "tiles": [
                    {
                        "title": "Mitglieder-Portal",
                        "text": "Extern",
                        "href": "https://portal.example.com",
                        "icon": "🦆",
                    },
                    {
                        "title": "Einsatzplanung",
                        "text": "Extern",
                        "href": "",
                        "icon": "🦆",
                    },
                    {"text": "No title", "href": "/nowhere"},
                ],
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-link-tiles"
    children = _column(tree)["innerBlocks"]
    assert [child["blockName"] for child in children] == ["divi/heading", "divi/text"]

    html = children[1]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    assert '<div class="portal-gateway">' in html
    assert (
        '<a class="portal-tile" href="https://portal.example.com" target="_blank" rel="noopener noreferrer">'
        in html
    )
    assert '<div class="portal-tile">' in html
    assert '<div class="portal-icon">🦆</div>' in html
    assert '<h3>Mitglieder-Portal</h3>' in html
    assert '<p>Extern</p>' in html
    assert 'No title' not in html
    assert set(_all_names(tree)) <= ALLOWED_BLOCKS


def test_link_tiles_reject_unsafe_hrefs_and_mark_external_links_safe():
    tree = _compose(
        {
            "block": "link-tiles",
            "values": {
                "title": "Safe gateway",
                "tiles": [
                    {
                        "title": "Unsafe javascript",
                        "href": "javascript:alert(1)",
                    },
                    {
                        "title": "Relative path",
                        "href": "/kundenportal",
                    },
                    {
                        "title": "External https",
                        "href": "https://example.org",
                    },
                ],
            },
        }
    )

    html = _column(tree)["innerBlocks"][1]["attrs"]["content"]["innerContent"]["desktop"]["value"]
    assert '<div class="portal-tile"><h3>Unsafe javascript</h3>' in html
    assert 'href="javascript:alert(1)"' not in html
    relative_start = html.find('<a class="portal-tile" href="/kundenportal">')
    assert relative_start != -1
    relative_end = html.find('</a>', relative_start) + 4
    relative_tile = html[relative_start:relative_end]
    assert 'target=' not in relative_tile
    assert 'rel=' not in relative_tile

    assert (
        '<a class="portal-tile" href="https://example.org" target="_blank" rel="noopener noreferrer">'
        in html
    )


def test_composer_exposes_only_one_public_method():
    code = (
        "define('ABSPATH', __DIR__);\n"
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        "$ref = new ReflectionClass('Bioco_Import_Divi_Composer');\n"
        "$public = array_filter(\n"
        "    $ref->getMethods(ReflectionMethod::IS_PUBLIC),\n"
        "    fn($m) => $m->class === 'Bioco_Import_Divi_Composer'\n"
        ");\n"
        "echo json_encode(array_map(fn($m) => $m->getName(), $public));"
    )
    result = subprocess.run(
        ["php", "-r", code],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    )
    assert json.loads(result.stdout) == ["section"]


def test_composer_rejects_unsupported_dynamic_block():
    try:
        _compose({"block": "events-feed", "values": {}})
    except subprocess.CalledProcessError as exc:
        assert "Unsupported Divi section block: events-feed" in exc.stderr
    else:
        raise AssertionError("Expected subprocess failure for unsupported block")


def test_gallery_strip_is_native_and_preserves_images_and_buttons():
    tree = _compose(
        {
            "block": "gallery-strip",
            "values": {
                "eyebrow": "Ort",
                "title": "Der Geisshof",
                "text": "<h2>Der Geisshof</h2><p>Unser Hof.</p>",
                "gallery": [42, 43, 44, 45],
                "columns_desktop": "4",
                "columns_mobile": "1",
                "media_ratio": "4:3",
                "media_fit": "cover",
                "gap": "lg",
                "rounded": "lg",
                "buttons": [
                    {"text": "Anfahrt", "href": "/standorte-depots", "variant": "primary"}
                ],
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-gallery-strip"
    rows = _rows(tree)
    assert len(rows) == 3
    assert _class(rows[0]) == "bioco-divi-row bioco-divi-gallery-strip-header"
    assert "bioco-divi-gallery-strip-row" in _class(rows[1])
    assert "bioco-divi-gallery-strip-row--desktop-4--mobile-1" in _class(rows[1])
    assert "bioco-divi-gallery-strip-row--ratio-4_3" in _class(rows[1])
    assert "bioco-divi-gallery-strip-row--fit-cover" in _class(rows[1])
    assert "bioco-divi-gallery-strip-row--rounded-lg" in _class(rows[1])
    assert _class(rows[2]) == "bioco-divi-row bioco-divi-gallery-strip-actions"

    gallery_row = rows[1]
    assert len(gallery_row["innerBlocks"]) == 4
    for i in range(4):
        col = _row_column(gallery_row, i)
        assert _class(col) == "bioco-divi-gallery-strip-frame"
        assert col["innerBlocks"][0]["blockName"] == "divi/image"
        assert _class(col["innerBlocks"][0]) == "bioco-divi-gallery-strip-image"

    actions_col = _row_column(rows[2], 0)
    assert [child["blockName"] for child in actions_col["innerBlocks"]] == ["divi/button"]

    assert set(_all_names(tree)) <= ALLOWED_BLOCKS


def test_gallery_strip_invalid_modifiers_and_empty_frames_are_safe():
    tree = _compose(
        {
            "block": "gallery-strip",
            "values": {
                "title": "Gallery",
                "text": "<p>Body</p>",
                "gallery": [0, 42, None],
                "columns_desktop": "5",
                "columns_mobile": "3",
                "media_ratio": "9:16",
                "media_fit": "stretch",
                "gap": "xxl",
                "rounded": "round",
            },
        }
    )

    gallery_row = _row(tree, 1)
    row_class = _class(gallery_row)
    assert "bioco-divi-gallery-strip-row--desktop-3--mobile-1" in row_class
    assert "bioco-divi-gallery-strip-row--ratio-4_3" in row_class
    assert "bioco-divi-gallery-strip-row--fit-cover" in row_class
    assert "bioco-divi-gallery-strip-row--rounded-lg" in row_class
    assert len(gallery_row["innerBlocks"]) == 1


def test_gallery_strip_images_use_attachment_alt_with_title_fallback():
    tree = _compose(
        {
            "block": "gallery-strip",
            "values": {
                "title": "Der Geisshof",
                "gallery": [42, 43, 44],
                "columns_desktop": "3",
                "columns_mobile": "1",
                "media_ratio": "4:3",
                "media_fit": "cover",
                "gap": "lg",
                "rounded": "lg",
            },
        }
    )

    gallery_row = _row(tree, 1)
    alts = []
    for i in range(3):
        frame = _row_column(gallery_row, i)
        image = frame["innerBlocks"][0]
        alts.append(image["attrs"]["image"]["innerContent"]["desktop"]["value"]["alt"])

    assert alts == ["Hof von oben", "Der Geisshof", "Packraum"]


def test_gallery_strip_button_is_not_duplicated_in_header():
    tree = _compose(
        {
            "block": "gallery-strip",
            "values": {
                "title": "Der Geisshof",
                "text": "<p>Beschreibung</p>",
                "gallery": [42],
                "columns_desktop": "1",
                "columns_mobile": "1",
                "buttons": [
                    {"text": "Anfahrt", "href": "/standorte-depots", "variant": "primary"}
                ],
            },
        }
    )

    header_row = _row(tree, 0)
    header_col = _row_column(header_row, 0)
    assert "divi/button" not in [child["blockName"] for child in header_col["innerBlocks"]]

    all_blocks = list(_all_names(tree))
    assert all_blocks.count("divi/button") == 1


def test_timeline_is_native_and_preserves_header_and_items():
    tree = _compose(
        {
            "block": "timeline",
            "values": {
                "eyebrow": "Geschichte",
                "title": "Timeline",
                "text": "<h2>Timeline</h2><p>Unsere Geschichte.</p>",
                "container_width": "lg",
                "text_width": "normal",
                "align": "left",
                "items": [
                    {"year_eyebrow": "2013", "title": "Gründung", "text": "Gegründet.", "emphasis": "normal"},
                    {"year_eyebrow": "2014", "title": "Erste Saison", "text": "Ernte.", "emphasis": "highlight"},
                    {"year_eyebrow": "2025", "title": "Neue Website", "text": "Launch.", "emphasis": "normal"},
                ],
            },
        }
    )

    assert _class(tree) == (
        "bioco-divi-section bioco-divi-timeline bioco-divi-width-lg bioco-divi-align-left"
    )

    header_row = _row(tree, 0)
    assert _class(header_row) == "bioco-divi-row bioco-divi-timeline-header"
    header_col = _row_column(header_row, 0)
    header_class = _class(header_col)
    assert "bioco-divi-content" in header_class
    assert "bioco-divi-text-normal" in header_class
    assert "bioco-divi-align-left" in header_class
    assert [child["blockName"] for child in header_col["innerBlocks"]] == [
        "divi/text",
        "divi/text",
    ]

    rows = _rows(tree)
    assert len(rows) == 4
    for i in range(3):
        item_row = _row(tree, i + 1)
        row_class = _class(item_row)
        assert "bioco-divi-timeline-item-row" in row_class
        emphasis = "highlight" if i == 1 else "normal"
        assert f"bioco-divi-timeline-item--{emphasis}" in row_class

        badge_col = _row_column(item_row, 0)
        assert "bioco-divi-timeline-badge-col" in _class(badge_col)
        badge = badge_col["innerBlocks"][0]
        assert _class(badge) == "bioco-divi-timeline-badge"
        year = ["2013", "2014", "2025"][i]
        assert badge["attrs"]["content"]["innerContent"]["desktop"]["value"] == year

        content_col = _row_column(item_row, 1)
        assert "bioco-divi-timeline-item-content" in _class(content_col)
        assert [child["blockName"] for child in content_col["innerBlocks"]] == [
            "divi/heading",
            "divi/text",
        ]

    assert set(_all_names(tree)) <= ALLOWED_BLOCKS


def test_timeline_item_only_with_empty_badge_fallback():
    tree = _compose(
        {
            "block": "timeline",
            "values": {
                "items": [
                    {"year_eyebrow": "", "title": "Milestone", "text": "Text.", "emphasis": "highlight"}
                ],
            },
        }
    )

    rows = _rows(tree)
    assert len(rows) == 1
    item_row = rows[0]
    assert "bioco-divi-timeline-item--highlight" in _class(item_row)
    badge = _row_column(item_row, 0)["innerBlocks"][0]
    assert badge["attrs"]["content"]["innerContent"]["desktop"]["value"] == "•"


def test_cards_grid_is_native_and_preserves_cards():
    tree = _compose(
        {
            "block": "cards-grid",
            "values": {
                "eyebrow": "Team",
                "title": "Hof-Team",
                "text": "<h2>Hof-Team</h2><p>Unser Team.</p>",
                "columns_desktop": "3",
                "columns_mobile": "1",
                "card_style": "soft",
                "media_ratio": "4:3",
                "media_fit": "cover",
                "gap": "lg",
                "rounded": "md",
                "cards": [
                    {"title": "Matthias", "image": 42, "image_alt": "Matthias", "text": "Bauer"},
                    {"title": "Michael", "image": 43, "image_alt": "Michael"},
                    {
                        "title": "Tino",
                        "image": 44,
                        "image_alt": "Tino",
                        "href": "https://example.com/tino",
                    },
                ],
            },
        }
    )

    assert _class(tree) == "bioco-divi-section bioco-divi-cards-grid"

    header_row = _row(tree, 0)
    assert _class(header_row) == "bioco-divi-row bioco-divi-cards-grid-header"
    header_column = _row_column(header_row, 0)
    assert [child["blockName"] for child in header_column["innerBlocks"]] == [
        "divi/text",
        "divi/text",
    ]
    assert header_column["innerBlocks"][0]["attrs"]["content"]["innerContent"]["desktop"]["value"] == "Team"
    assert header_column["innerBlocks"][1]["attrs"]["content"]["innerContent"]["desktop"]["value"] == "<h2>Hof-Team</h2><p>Unser Team.</p>"

    grid_row = _row(tree, 1)
    row_class = _class(grid_row)
    assert "bioco-divi-cards-grid-row" in row_class
    assert "bioco-divi-cards-grid-row--desktop-3--mobile-1" in row_class
    assert "bioco-divi-cards-grid-row--ratio-4_3" in row_class
    assert "bioco-divi-cards-grid-row--fit-cover" in row_class
    assert "bioco-divi-cards-grid-row--rounded-md" in row_class
    assert len(grid_row["innerBlocks"]) == 3

    names = set(_all_names(tree))
    assert names <= ALLOWED_BLOCKS

    first_card = _row_column(grid_row, 0)
    assert "bioco-divi-cards-grid-card" in _class(first_card)
    assert "bioco-divi-cards-grid-card--soft" in _class(first_card)
    card_children = first_card["innerBlocks"]
    assert [child["blockName"] for child in card_children] == [
        "divi/image",
        "divi/heading",
        "divi/text",
    ]
    assert _class(card_children[0]) == "bioco-divi-card-image"

    linked_card = _row_column(grid_row, 2)
    assert [child["blockName"] for child in linked_card["innerBlocks"]] == [
        "divi/image",
        "divi/heading",
        "divi/button",
    ]
    button = linked_card["innerBlocks"][2]
    assert _class(button) == "bioco-divi-button bioco-divi-button--primary"
    assert (
        button["attrs"]["button"]["innerContent"]["desktop"]["value"]["linkTarget"]
        == "on"
    )


def test_cards_grid_invalid_modifiers_and_incomplete_cards_are_safe():
    tree = _compose(
        {
            "block": "cards-grid",
            "values": {
                "title": "Grid",
                "text": "<p>Body</p>",
                "columns_desktop": "5",
                "columns_mobile": "3",
                "card_style": "fancy",
                "media_ratio": "9:16",
                "media_fit": "stretch",
                "gap": "xxl",
                "rounded": "round",
                "cards": [
                    {"title": "No image"},
                    {"title": "", "image": 42, "image_alt": "No title"},
                    {"title": "Unsafe link", "image": 43, "href": "javascript:alert(1)"},
                ],
            },
        }
    )

    grid_row = _row(tree, 1)
    row_class = _class(grid_row)
    assert "bioco-divi-cards-grid-row--desktop-3--mobile-1" in row_class
    assert "bioco-divi-cards-grid-row--ratio-3_4" in row_class
    assert "bioco-divi-cards-grid-row--fit-cover" in row_class
    assert "bioco-divi-cards-grid-row--rounded-md" in row_class

    # Titled cards render even without an image; only the blank-title card is omitted.
    assert len(grid_row["innerBlocks"]) == 2
    first = _row_column(grid_row, 0)
    assert [child["blockName"] for child in first["innerBlocks"]] == ["divi/heading"]
    second = _row_column(grid_row, 1)
    assert [child["blockName"] for child in second["innerBlocks"]] == [
        "divi/image",
        "divi/heading",
    ]
    assert "divi/button" not in set(_all_names(tree))
