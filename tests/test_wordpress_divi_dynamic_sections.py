import base64
import json
import re
import subprocess
from pathlib import Path

import pytest


ROOT = Path(__file__).parents[1]
DYNAMIC_SECTIONS = "wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php"
DIVI_BLOCKS = "wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php"
DIVI_COMPOSER = "wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php"
SCHNUPPERTAGE_RENDER = ROOT / "wordpress/web/app/mu-plugins/bioco-core/blocks/schnuppertage/render.php"

DYNAMIC_BLOCKS = {
    "contact-form": "contact_form",
    "membership-form": "membership_form",
    "subscribe-form": "subscribe_form",
    "visit-day-form": "visit_day_form",
    "waiting-list-form": "waiting_list_form",
    "event-signup-form": "event_signup_form",
    "doi-confirm": "doi_confirm",
    "gallery": "gallery",
    "pricing-calculator": "pricing_calculator",
    "events-feed": "events_feed",
    "schnuppertage": "schnuppertage",
    "group-cards": "group_cards",
    "saisonkalender": "saisonkalender",
    "depot-map": "depot_map",
    "geisshof-map": "geisshof_map",
}


def test_schnuppertage_card_display_reuses_shared_event_cards():
    source = SCHNUPPERTAGE_RENDER.read_text(encoding="utf-8")
    assert "bioco_field('display')" in source
    assert "$display === 'cards'" in source
    assert "bioco_render_events_list($termine_query, $empty_message)" in source


def _run_php(code: str) -> str:
    return subprocess.run(
        ["php", "-r", code],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout


def _json_php(code: str):
    return json.loads(_run_php(code))


def _composer_preamble() -> str:
    return (
        "define('ABSPATH', __DIR__);\n"
        f"require '{DYNAMIC_SECTIONS}';\n"
        f"require '{DIVI_BLOCKS}';\n"
        f"require '{DIVI_COMPOSER}';\n"
    )


def _render_preamble() -> str:
    return (
        "define('ABSPATH', __DIR__);\n"
        "function wp_enqueue_script($handle, ...$args) {}\n"
        "function wp_enqueue_style($handle, ...$args) {}\n"
        "function wp_localize_script($handle, $object, $values) {}\n"
        "function bioco_forms_view_script_handle($block_name) { return 'shared::' . $block_name; }\n"
        "function bioco_forms_localize_block($block_name, $object, $endpoint) {}\n"
        "function get_the_ID() { return 77; }\n"
        "function is_singular($post_type = '') { return false; }\n"
        "function __($text, $domain = '') { return $text; }\n"
        "function get_permalink($id = 0) { return 'https://example.test/page'; }\n"
        "function home_url($path = '') { return 'https://example.test' . $path; }\n"
        "function esc_attr($value) { return htmlspecialchars((string)$value, ENT_QUOTES); }\n"
        "function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES); }\n"
        "function esc_url($value) { return (string)$value; }\n"
        "function sanitize_title($value) { return strtolower(str_replace(' ', '-', (string)$value)); }\n"
        "function number_format_i18n($value) { return number_format((float)$value, 0, '.', \"'\"); }\n"
        "function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }\n"
        "function bioco_text_has_heading_html($html) { return false; }\n"
        "function bioco_kses_rich_text($html) { return (string)$html; }\n"
        "function bioco_navigation_url($url) { return (string)$url; }\n"
        "function bioco_render_events_list($query, $empty) { echo '<p>' . $empty . '</p>'; }\n"
        "function bioco_render_map_block($locations, $lat, $lng, $zoom, $heading, $route, $empty) {\n"
        "    echo '<div class=map-stub>' . $empty . '</div>';\n"
        "}\n"
        "class WP_Query {\n"
        "    public function __construct($args = []) {}\n"
        "    public function have_posts() { return false; }\n"
        "    public function the_post() {}\n"
        "}\n"
        "function bioco_query_events($status, $limit, $type = null) { return new WP_Query(); }\n"
        "function wp_reset_postdata() {}\n"
        "function serialize_block($block) {\n"
        "    $content = '';\n"
        "    if ($block['blockName'] === 'divi/text') {\n"
        "        $content = $block['attrs']['content']['innerContent']['desktop']['value'];\n"
        "    } elseif ($block['blockName'] === 'divi/heading') {\n"
        "        $content = json_encode($block['attrs']['title']['innerContent']['desktop']['value']);\n"
        "    } else {\n"
        "        foreach ($block['innerBlocks'] as $child) $content .= serialize_block($child);\n"
        "    }\n"
        "    return '<!-- wp:' . $block['blockName'] . ' -->' . $content\n"
        "        . '<!-- /wp:' . $block['blockName'] . ' -->';\n"
        "}\n"
        f"require '{DYNAMIC_SECTIONS}';\n"
        f"require '{DIVI_BLOCKS}';\n"
        f"require '{DIVI_COMPOSER}';\n"
    )


def _compose(block: str, values: dict):
    item = base64.b64encode(
        json.dumps({"block": block, "values": values}).encode()
    ).decode()
    return _json_php(
        _composer_preamble()
        + f"$item = json_decode(base64_decode('{item}'), true);\n"
        + "echo json_encode(Bioco_Import_Divi_Composer::section($item));"
    )


def _block_names(block: dict) -> list[str]:
    return [block["blockName"]] + [
        name
        for child in block.get("innerBlocks", [])
        for name in _block_names(child)
    ]


def test_every_seed_section_id_reaches_the_composed_dom_exactly_once():
    payload = _json_php(
        _render_preamble()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/seeds.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + "function wp_get_attachment_image_url($id, $size) { return 'https://example.test/image.jpg'; }\n"
        + "$result = [];\n"
        + "$seeds = bioco_import_load_seeds('wordpress/content-seed');\n"
        + "$dynamicBlocks = array_map(\n"
        + "    fn($name) => substr($name, strpos($name, '/') + 1),\n"
        + "    bioco_dynamic_components()\n"
        + ");\n"
        + "$collect = function ($block) use (&$collect) {\n"
        + "    $ids = [];\n"
        + "    $id = $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] ?? '';\n"
        + "    if ($id !== '') $ids[] = $id;\n"
        + "    foreach ($block['innerBlocks'] ?? [] as $child) {\n"
        + "        $ids = array_merge($ids, $collect($child));\n"
        + "    }\n"
        + "    return $ids;\n"
        + "};\n"
        + "foreach ($seeds as $seed) {\n"
        + "    $ids = [];\n"
        + "    foreach (bioco_import_build_page_plan($seed) as $item) {\n"
        + "        if (($item['type'] ?? '') !== 'block') continue;\n"
        + "        $tree = Bioco_Import_Divi_Composer::section($item);\n"
        + "        $treeIds = $collect($tree);\n"
        + "        if (in_array($item['block'], $dynamicBlocks, true)) {\n"
        + "            $rendered = bioco_dynamic_expand_markers(serialize_block($tree));\n"
        + "            if (preg_match('/\\sid=\"([^\"]+)\"/', $rendered, $match)) {\n"
        + "                $treeIds[] = html_entity_decode($match[1], ENT_QUOTES);\n"
        + "            }\n"
        + "        }\n"
        + "        $ids = array_merge($ids, $treeIds);\n"
        + "    }\n"
        + "    $expected = array_column($seed['sections'], 'section_id');\n"
        + "    $result[$seed['slug']] = ['expected' => $expected, 'actual' => $ids];\n"
        + "}\n"
        + "echo json_encode($result);"
    )

    for slug, anchors in payload.items():
        assert sorted(anchors["actual"]) == sorted(anchors["expected"]), slug
        assert len(anchors["actual"]) == len(set(anchors["actual"])), slug


@pytest.mark.parametrize(("plan_block", "component_key"), DYNAMIC_BLOCKS.items())
def test_dynamic_block_is_single_native_divi_text_marker(plan_block, component_key):
    values = {
        "title": f"Seed title for {plan_block}",
        "text": "Seed body",
        "nested": {"enabled": True, "items": [1, "zwei", None]},
    }

    section = _compose(plan_block, values)

    assert _block_names(section) == [
        "divi/section",
        "divi/row",
        "divi/column",
        "divi/text",
    ]
    row = section["innerBlocks"][0]
    column = row["innerBlocks"][0]
    text = column["innerBlocks"][0]
    class_path = ("module", "advanced", "htmlAttributes", "desktop", "value", "class")

    def block_class(block):
        value = block["attrs"]
        for key in class_path:
            value = value[key]
        return value

    assert [block_class(block) for block in (section, row, column)] == [
        "bioco-dynamic-section",
        "bioco-dynamic-row",
        "bioco-dynamic-column",
    ]
    marker = text["attrs"]["content"]["innerContent"]["desktop"]["value"]
    match = re.fullmatch(
        rf'<div class="bioco-dynamic" data-bioco-component="{component_key}" '
        r'data-bioco-props="([A-Za-z0-9+/=]*)"></div>',
        marker,
    )
    assert match is not None
    assert json.loads(base64.b64decode(match.group(1))) == values
    assert "divi/heading" not in _block_names(section)


@pytest.mark.parametrize(
    ("seed_slug", "plan_block", "section_class", "rendered_value"),
    [
        (
            "kontakt",
            "contact-form",
            "cms-contact-form",
            'data-form="contact"',
        ),
        (
            "bioco-werden",
            "pricing-calculator",
            "cms-pricing-calculator",
            'id="pricing-calculator"',
        ),
        (
            "aktuelles",
            "events-feed",
            "cms-events-feed",
            "Aktuell sind keine allgemeinen Events geplant.",
        ),
        (
            "standorte-depots",
            "depot-map",
            "cms-depot-map",
            "Abholzeiten: Dienstag und Freitag, ab 16:00 Uhr",
        ),
    ],
)
def test_serialized_dynamic_section_expands_to_real_ssr(
    seed_slug, plan_block, section_class, rendered_value
):
    payload = _json_php(
        _render_preamble()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + f"$seed = json_decode(file_get_contents('wordpress/content-seed/{seed_slug}.json'), true);\n"
        + "$item = null;\n"
        + "foreach (bioco_import_build_page_plan($seed) as $candidate) {\n"
        + f"    if ($candidate['block'] === '{plan_block}') $item = $candidate;\n"
        + "}\n"
        + "$serialized = serialize_block(Bioco_Import_Divi_Composer::section($item));\n"
        + "$expanded = bioco_dynamic_expand_markers($serialized);\n"
        + "echo json_encode(compact('serialized', 'expanded'));"
    )

    assert "divi/section" in payload["serialized"]
    assert "bioco-dynamic" in payload["serialized"]
    assert f'class="cms-section {section_class}"' in payload["expanded"]
    assert rendered_value in payload["expanded"]
    if plan_block == "pricing-calculator":
        assert 'data-pc-action="select-tier"' in payload["expanded"]
    assert "bioco-dynamic" not in payload["expanded"]


def test_composer_remains_final_with_one_public_method_and_runtime_is_wired():
    payload = _json_php(
        _composer_preamble()
        + "$reflection = new ReflectionClass('Bioco_Import_Divi_Composer');\n"
        + "$public = array_map(\n"
        + "    fn($method) => $method->getName(),\n"
        + "    $reflection->getMethods(ReflectionMethod::IS_PUBLIC)\n"
        + ");\n"
        + "echo json_encode(['final' => $reflection->isFinal(), 'public' => $public]);"
    )
    bootstrap = (
        ROOT / "wordpress/web/app/mu-plugins/bioco-import/bioco-import.php"
    ).read_text()

    assert payload == {"final": True, "public": ["section"]}
    assert "BIOCO_IMPORT_CORE_INCLUDES_DIR . '/dynamic-sections.php'" in bootstrap
    assert bootstrap.index("dynamic-sections.php") < bootstrap.index("divi-composer.php")


# ---------------------------------------------------------------------------
# Issue #148: exactly ONE events feed on the homepage
# ---------------------------------------------------------------------------


def test_homepage_plan_contains_exactly_one_events_feed():
    """The duplicate feed came from two paths: the seed's own events_feed
    section AND an unconditional second marker injected by homeChromeSection().
    The chrome keeps Beitraege + Schnuppertage only; the single feed is the
    CMS-driven seed section (it is the one with the past-events card)."""
    payload = _json_php(
        _render_preamble()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + "$seed = json_decode(file_get_contents('wordpress/content-seed/home.json'), true);\n"
        + "$plan = bioco_import_build_page_plan($seed);\n"
        + "$feeds = array_values(array_filter($plan, fn($i) => ($i['block'] ?? '') === 'events-feed'));\n"
        + "$chromeSerialized = '';\n"
        + "foreach ($plan as $i) {\n"
        + "    if (($i['block'] ?? '') === 'home-chrome') {\n"
        + "        $chromeSerialized = serialize_block(Bioco_Import_Divi_Composer::section($i));\n"
        + "    }\n"
        + "}\n"
        + "echo json_encode(['feeds' => $feeds, 'chrome' => $chromeSerialized]);"
    )

    assert len(payload["feeds"]) == 1, payload["feeds"]
    values = payload["feeds"][0]["values"]
    # general-only: the schnuppertage chrome block is the one place
    # Schnuppertage appear on the homepage, never duplicated into the feed.
    assert "respect_stored_status" not in values
    assert "include_schnuppertage" not in values

    chrome = payload["chrome"]
    assert 'data-bioco-component="events_feed"' not in chrome
    assert 'data-bioco-component="schnuppertage"' in chrome
    assert "respect_stored_status" not in chrome


# ---------------------------------------------------------------------------
# Issue #149: /aktuelles wires the schnuppertage subsection; /mitmachen keeps
# its section heading
# ---------------------------------------------------------------------------


def test_aktuelles_events_feed_wires_the_schnuppertage_subsection():
    """The seed carries schnuppertageTitle/schnuppertageEmptyMessage; the WP
    port used to drop them silently, so /aktuelles rendered no Schnuppertage
    at all. They now reach the block, mirroring AktuellesClient.tsx:
    h2 'Events', h3 'Kommende Events' (general), h3 'Schnuppertage'."""
    payload = _json_php(
        _render_preamble()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + "$seed = json_decode(file_get_contents('wordpress/content-seed/aktuelles.json'), true);\n"
        + "$item = null;\n"
        + "foreach (bioco_import_build_page_plan($seed) as $candidate) {\n"
        + "    if ($candidate['block'] === 'events-feed') $item = $candidate;\n"
        + "}\n"
        + "$serialized = serialize_block(Bioco_Import_Divi_Composer::section($item));\n"
        + "$expanded = bioco_dynamic_expand_markers($serialized);\n"
        + "echo json_encode(['values' => $item['values'], 'serialized' => $serialized, 'expanded' => $expanded]);"
    )

    values = payload["values"]
    assert values["schnuppertage_title"] == "Schnuppertage"
    assert values["schnuppertage_empty_message"] == "Aktuell sind keine Schnuppertage geplant."
    # h2 'Events' (seed config.title) becomes the composer's section heading,
    # which serializes as a divi/heading block (text lives in the attrs JSON).
    assert values["_section_heading"] == "Events"
    assert '"Events"' in payload["serialized"]

    expanded = payload["expanded"]
    assert "<h3>Schnuppertage</h3>" in expanded
    assert "Aktuell sind keine Schnuppertage geplant." in expanded
    # The general feed keeps its own empty text — no cross-contamination.
    assert "Aktuell sind keine allgemeinen Events geplant." in expanded


def _render_preamble_real_heading_detection() -> str:
    """_render_preamble() stubs bioco_text_has_heading_html() to always-false,
    which can never reproduce the suppressed-heading defect. Swap in the real
    implementation (verbatim from bioco-core/includes/helpers.php)."""
    stub = "function bioco_text_has_heading_html($html) { return false; }\n"
    real = (
        "function bioco_text_has_heading_html($html) {"
        r" return (bool) preg_match('/<h[1-6]\b[^>]*>/i', (string) $html); }"
        + "\n"
    )
    preamble = _render_preamble()
    assert stub in preamble
    return preamble.replace(stub, real)


def test_mitmachen_schnuppertage_keeps_its_h2_heading_above_the_h3_subheading():
    """The seed's section_text opens with an <h3>; suppressing the block title
    on that basis left /mitmachen with no visible 'Schnuppertage' heading.
    SchnuppertageSection.tsx (the reference component) renders title AND text
    unconditionally, so the block does too — h2 title + h3 from the text is
    the production design, not a duplicate."""
    payload = _json_php(
        _render_preamble_real_heading_detection()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + "$seed = json_decode(file_get_contents('wordpress/content-seed/mitmachen.json'), true);\n"
        + "$item = null;\n"
        + "foreach (bioco_import_build_page_plan($seed) as $candidate) {\n"
        + "    if ($candidate['block'] === 'schnuppertage') $item = $candidate;\n"
        + "}\n"
        + "$expanded = bioco_dynamic_expand_markers(serialize_block(Bioco_Import_Divi_Composer::section($item)));\n"
        + "echo json_encode(['expanded' => $expanded]);"
    )

    html = payload["expanded"]
    assert 'class="cms-section cms-schnuppertage"' in html
    assert "<h2>Schnuppertage</h2>" in html
    assert "Komm schnuppern" in html
    # One h2 heading, then the h3 subheading from the text — not two headings.
    assert html.count("<h2>Schnuppertage</h2>") == 1
    assert html.index("<h2>Schnuppertage</h2>") < html.index("Komm schnuppern")
