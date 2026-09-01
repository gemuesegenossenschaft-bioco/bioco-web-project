import base64
import json
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]

WP_SERIALIZER_STUB = r'''
function _wp_serialize_block($block) {
    $name = $block['blockName'];
    $attrs = empty($block['attrs']) ? '' : ' ' . json_encode($block['attrs'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $open = '<!-- wp:' . $name . $attrs . ' -->';

    $content = '';
    $child_idx = 0;
    $inner_content = $block['innerContent'] ?? [];
    foreach ($inner_content as $piece) {
        if ($piece === null) {
            if ($child_idx < count($block['innerBlocks'] ?? [])) {
                $content .= _wp_serialize_block($block['innerBlocks'][$child_idx]);
                $child_idx++;
            }
        } else {
            $content .= $piece;
        }
    }

    if ($content === '') {
        return '<!-- wp:' . $name . (empty($attrs) ? ' ' : $attrs . ' ') . '/-->';
    }
    return $open . $content . '<!-- /wp:' . $name . ' -->';
}
function serialize_blocks($blocks) {
    $out = '';
    foreach ($blocks as $b) {
        $out .= _wp_serialize_block($b);
    }
    return $out;
}
function serialize_block($block) {
    return _wp_serialize_block($block);
}
'''


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


def _base64_item(item: dict) -> str:
    return base64.b64encode(json.dumps(item).encode("utf-8")).decode("ascii")


def _preamble() -> str:
    return (
        "define('ABSPATH', __DIR__);\n"
        "function is_wp_error($value) { return false; }\n"
        "function esc_html($value) { return $value; }\n"
        "$GLOBALS['BIOCO_TEST_PAGES'] = [];\n"
        "$GLOBALS['BIOCO_URL_TO_ATTACHMENT'] = [\n"
        "    'https://example.com/hero.jpg' => 42,\n"
        "    'https://example.com/feature.jpg' => 99,\n"
        "    'https://example.com/feature2.jpg' => 100,\n"
        "];\n"
        "$GLOBALS['BIOCO_META'] = [];\n"
        "$GLOBALS['BIOCO_META_WRITES'] = [];\n"
        "function get_posts($args) {\n"
        "    if ($args['post_type'] === 'page' && isset($args['name']) && isset($GLOBALS['BIOCO_TEST_PAGES'][$args['name']])) {\n"
        "        $page = $GLOBALS['BIOCO_TEST_PAGES'][$args['name']];\n"
        "        $post_content = is_object($page) ? $page->post_content : $page;\n"
        "        return [(object) ['ID' => 7, 'post_content' => $post_content, 'post_status' => 'publish']];\n"
        "    }\n"
        "    if (isset($args['meta_value']) && isset($GLOBALS['BIOCO_URL_TO_ATTACHMENT'][$args['meta_value']])) {\n"
        "        return [(object) ['ID' => $GLOBALS['BIOCO_URL_TO_ATTACHMENT'][$args['meta_value']]]];\n"
        "    }\n"
        "    return [];\n"
        "}\n"
        "function wp_get_attachment_image_url($id, $size = 'full') {\n"
        "    static $map = [42 => 'https://example.com/hero.jpg', 99 => 'https://example.com/feature.jpg', 100 => 'https://example.com/feature2.jpg'];\n"
        "    return $map[$id] ?? false;\n"
        "}\n"
        "function get_post_meta($post_id, $key, $single = true) {\n"
        "    if (!$single) return isset($GLOBALS['BIOCO_META'][$post_id][$key]) ? [$GLOBALS['BIOCO_META'][$post_id][$key]] : [];\n"
        "    return $GLOBALS['BIOCO_META'][$post_id][$key] ?? '';\n"
        "}\n"
        "function update_post_meta($post_id, $key, $value) {\n"
        "    $GLOBALS['BIOCO_META_WRITES'][] = ['post_id' => $post_id, 'key' => $key, 'value' => $value];\n"
        "    $GLOBALS['BIOCO_META'][$post_id][$key] = $value;\n"
        "    return true;\n"
        "}\n"
        "function wp_slash($value) { return $value; }\n"
        "function wp_insert_post($data, $wp_error = false) {\n"
        "    $id = 7;\n"
        "    $GLOBALS['BIOCO_TEST_PAGES'][$data['post_name']] = (object) ['ID' => $id, 'post_content' => $data['post_content'], 'post_status' => $data['post_status']];\n"
        "    return $id;\n"
        "}\n"
        "function wp_update_post($data, $wp_error = false) {\n"
        "    if (isset($GLOBALS['BIOCO_TEST_PAGES']['home']) && $GLOBALS['BIOCO_TEST_PAGES']['home']->ID == $data['ID']) {\n"
        "        $GLOBALS['BIOCO_TEST_PAGES']['home']->post_content = $data['post_content'];\n"
        "    }\n"
        "    return $data['ID'];\n"
        "}\n"
        "function get_post($id) {\n"
        "    foreach ($GLOBALS['BIOCO_TEST_PAGES'] as $page) {\n"
        "        if ($page->ID == $id) return $page;\n"
        "    }\n"
        "    return null;\n"
        "}\n"
        + WP_SERIALIZER_STUB
    )


def _build_desired_content_payload(seed: dict) -> str:
    """Return PHP code invoking bioco_import_build_desired_content with mocked ACF."""
    seed_b64 = base64.b64encode(json.dumps(seed).encode("utf-8")).decode("ascii")
    return (
        _preamble()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/report.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/documents.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';\n"
        + f"$seed = json_decode(base64_decode('{seed_b64}'), true);\n"
        + "$report = bioco_import_report_new();\n"
        + "[$content, $labels] = bioco_import_build_desired_content($seed, 'verify', $report);\n"
        + "echo json_encode(['content' => $content, 'labels' => $labels]);"
    )


def _page_import_payload(
    seed: dict,
    mode: str = "apply",
    existing_content: str | None | bool = False,
    preseed_meta: dict | None = None,
) -> str:
    """Return PHP code invoking bioco_import_page_for_seed with mocked WP APIs."""
    seed_b64 = base64.b64encode(json.dumps(seed).encode("utf-8")).decode("ascii")
    preseed = json.dumps(preseed_meta or {})
    php = (
        _preamble()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/report.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/documents.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';\n"
        + "$seed = json_decode(base64_decode('" + seed_b64 + "'), true);\n"
        + "$mode = '" + mode + "';\n"
        + "$GLOBALS['BIOCO_META'] = json_decode('" + preseed + "', true);\n"
        + "$GLOBALS['BIOCO_META_WRITES'] = [];\n"
        + "$GLOBALS['BIOCO_TEST_PAGES'] = [];\n"
        + "$report = bioco_import_report_new();\n"
    )
    if existing_content is False:
        pass  # create path: no existing page
    elif existing_content is None:
        php += (
            "[$desired, $labels] = bioco_import_build_desired_content($seed, 'apply', $report);\n"
            + "$GLOBALS['BIOCO_TEST_PAGES'][(string)$seed['slug']] = (object) ['ID' => 7, 'post_content' => $desired, 'post_status' => 'publish'];\n"
            + "$GLOBALS['BIOCO_META_WRITES'] = [];\n"
            + "$report = bioco_import_report_new();\n"
        )
    else:
        escaped = json.dumps(existing_content)
        php += f"$GLOBALS['BIOCO_TEST_PAGES'][(string)$seed['slug']] = (object) ['ID' => 7, 'post_content' => {escaped}, 'post_status' => 'publish'];\n"
    php += (
        "bioco_import_page_for_seed($seed, $mode, false, $report);\n"
        + "echo json_encode(['meta_writes' => $GLOBALS['BIOCO_META_WRITES'], 'rows' => $report['rows'], 'meta' => $GLOBALS['BIOCO_META']]);"
    )
    return php


def _has_meta_write(writes: list, post_id: int, key: str, value: str) -> bool:
    return any(w["post_id"] == post_id and w["key"] == key and w["value"] == value for w in writes)


def _findall_block_comment_names(content: str):
    """Return every block name appearing in WordPress block comments."""
    return re.findall(r'<!--\s*wp:([^\s>]+)', content)


# Sentinel for "no existing page" in page-import tests.
_NO_EXISTING_PAGE = object()


def _page_import_payload(
    seed: dict,
    mode: str = "apply",
    existing_content=_NO_EXISTING_PAGE,
    preseed_meta: dict | None = None,
) -> str:
    """Return PHP code invoking bioco_import_page_for_seed with mocked WP API."""
    seed_b64 = base64.b64encode(json.dumps(seed).encode("utf-8")).decode("ascii")
    preseed_meta = preseed_meta or {}
    preseed_php = ""
    for key, value in preseed_meta.items():
        preseed_php += f"$GLOBALS['BIOCO_META'][7]['{key}'] = '{value}';\n"

    existing_php = ""
    if existing_content is _NO_EXISTING_PAGE:
        pass
    elif existing_content is None:
        # Compute desired content and use it as the existing page (ok-equal path).
        existing_php = (
            "$report = bioco_import_report_new();\n"
            "[$desired, $_] = bioco_import_build_desired_content($seed, 'apply', $report);\n"
            "$GLOBALS['BIOCO_TEST_PAGES']['home'] = (object) ['ID' => 7, 'post_content' => $desired, 'post_status' => 'publish'];\n"
            "$GLOBALS['BIOCO_META_WRITES'] = [];\n"
            "$report = bioco_import_report_new();\n"
        )
    else:
        escaped = json.dumps(existing_content)
        existing_php = (
            f"$GLOBALS['BIOCO_TEST_PAGES']['home'] = (object) ['ID' => 7, 'post_content' => {escaped}, 'post_status' => 'publish'];\n"
        )

    return (
        _preamble()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/report.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/documents.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';\n"
        + f"$seed = json_decode(base64_decode('{seed_b64}'), true);\n"
        + preseed_php
        + existing_php
        + f"$mode = '{mode}';\n"
        + "$report = bioco_import_report_new();\n"
        + "bioco_import_page_for_seed($seed, $mode, false, $report);\n"
        + "echo json_encode(['meta_writes' => $GLOBALS['BIOCO_META_WRITES'], 'rows' => $report['rows'], 'meta' => $GLOBALS['BIOCO_META']]);"
    )


def _home_seed() -> dict:
    return {
        "slug": "home",
        "title": "Home",
        "hero": {"hero_title": "H", "hero_subtitle": "S", "image_url": "", "image_alt": ""},
        "sections": [
            {
                "section_id": "intro",
                "section_title": "Intro",
                "section_text": "<p>T</p>",
                "section_layout": "rich_text",
                "section_config": {"styleVariant": "feature"},
                "buttons": [],
            },
        ],
    }


# ---------------------------------------------------------------------------
# Import / pages.php
# ---------------------------------------------------------------------------


def test_home_slug_uses_native_divi_sections_and_markers():
    """Home keeps code-owned live chrome between features and CTA."""
    seed = {
        "slug": "home",
        "title": "Home",
        "hero": {
            "hero_title": "Hero\nHeadline",
            "hero_subtitle": "Sub",
            "image_url": "https://example.com/hero.jpg",
            "image_alt": "Hero",
        },
        "sections": [
            {
                "section_id": "willkommen",
                "section_title": "Willkommen",
                "section_text": "<p>Text</p>",
                "section_layout": "split_text_media",
                "section_config": {"styleVariant": "feature"},
                "image_url": "https://example.com/feature.jpg",
                "image_alt": "Feature",
                "buttons": [{"text": "Mehr", "href": "/wir", "variant": "primary"}],
            },
            {
                "section_id": "gemeinsam",
                "section_title": "Gemeinsam",
                "section_text": "<p>Text</p>",
                "section_layout": "split_media_text",
                "section_config": {"styleVariant": "feature"},
                "image_url": "https://example.com/feature2.jpg",
                "image_alt": "Feature2",
                "buttons": [],
            },
            {
                "section_id": "kennenlernen",
                "section_title": "Kennenlernen",
                "section_text": "<p>Text</p>",
                "section_layout": "rich_text",
                "section_config": {"styleVariant": "feature"},
                "buttons": [{"text": "Kontakt", "href": "/kontakt", "variant": "primary"}],
            },
        ],
    }
    result = _json_php(_build_desired_content_payload(seed))
    content = result["content"]

    # Five markers in order, including the code-owned live homepage chrome.
    assert content.count("<!-- bioco:section __hero__ -->") == 1
    assert content.count("<!-- bioco:section willkommen -->") == 1
    assert content.count("<!-- bioco:section gemeinsam -->") == 1
    assert content.count("<!-- bioco:section __home_chrome__ -->") == 1
    assert content.count("<!-- bioco:section kennenlernen -->") == 1
    assert (
        content.index("__hero__")
        < content.index("willkommen")
        < content.index("gemeinsam")
        < content.index("__home_chrome__")
        < content.index("kennenlernen")
    )
    assert "Beiträge" in content or r"Beitr\u00e4ge" in content
    # #148: the chrome no longer injects an events feed — the homepage's single
    # events_feed comes from the seed's own section (covered by
    # test_home_seed_keeps_live_aktuelles_feed_after_cta). This synthetic seed
    # has no events_feed section, so none may appear at all.
    assert "events_feed" not in content
    assert "schnuppertage" in content

    marker_props = [
        json.loads(base64.b64decode(encoded))
        for encoded in re.findall(r'data-bioco-props=(?:"|\\u0022)([A-Za-z0-9+/=]+)(?:"|\\u0022)', content)
    ]
    assert any(props.get("display") == "cards" for props in marker_props)

    # Exactly five opening divi/section comments.
    names = _findall_block_comment_names(content)
    assert names.count("divi/section") == 5

    # Every block comment is divi/*; no ACF block names.
    assert all(name.startswith("divi/") for name in names), names
    assert "bioco/hero" not in names
    assert "bioco/media-text" not in names
    assert "bioco/rich-text" not in names

    # No forbidden blocks.
    for forbidden in ("core/html", "divi/code", "divi/shortcode-module"):
        assert forbidden not in names

def test_home_seed_keeps_live_aktuelles_feed_after_cta():
    """The current CMS-owned homepage feed remains after the CTA."""
    wp_seed = json.loads((ROOT / "wordpress/content-seed/home.json").read_text())
    cms_seed = json.loads((ROOT / "cms/content-seed/home.json").read_text())
    expected = {
        "section_id": "section-b597772b",
        "section_title": "Aktuelles",
        "section_text": "<h1>Aktuelles</h1>",
        "section_layout": "split_media_text",
        "section_theme": "default",
        "section_component": "events_feed",
        "section_config": {"archiveUrl": "/aktuelles"},
    }
    assert wp_seed["sections"][-1] == expected
    assert cms_seed == wp_seed

    result = _json_php(_build_desired_content_payload(wp_seed))
    content = result["content"]
    assert content.index("kennenlernen") < content.index("section-b597772b")
    updates = content[content.index("section-b597772b") :]
    assert "Aktuelles" in updates
    assert "events_feed" in updates
    # #148: exactly one events feed on the whole homepage (the seed section;
    # the __home_chrome__ block contributes Beiträge + Schnuppertage only).
    assert content.count("events_feed") == 1


def test_every_seed_serializes_only_native_divi_blocks():
    """Every content seed composes exclusively to the supported native Divi tree."""
    allowed = {
        "divi/section",
        "divi/row",
        "divi/column",
        "divi/text",
        "divi/heading",
        "divi/image",
        "divi/button",
    }
    seed_paths = sorted((ROOT / "wordpress/content-seed").glob("*.json"))
    assert seed_paths, "No content seeds found"

    seen_paths = []
    for seed_path in seed_paths:
        seed = json.loads(seed_path.read_text())
        result = _json_php(_build_desired_content_payload(seed))
        names = set(_findall_block_comment_names(result["content"]))
        assert names <= allowed, f"{seed_path.name}: unexpected blocks {sorted(names - allowed)}"
        seen_paths.append(seed_path)

    assert seen_paths == seed_paths


def test_home_resolved_attachment_id_reaches_image_attrs():
    """Resolved attachment IDs flow into divi/image src/id attrs for home."""
    seed = {
        "slug": "home",
        "title": "Home",
        "hero": {
            "hero_title": "H",
            "hero_subtitle": "S",
            "image_url": "https://example.com/hero.jpg",
            "image_alt": "Hero",
        },
        "sections": [
            {
                "section_id": "willkommen",
                "section_title": "T",
                "section_text": "<p>B</p>",
                "section_layout": "split_media_text",
                "section_config": {"styleVariant": "feature"},
                "image_url": "https://example.com/feature.jpg",
                "image_alt": "Feature",
                "buttons": [],
            },
        ],
    }
    result = _json_php(_build_desired_content_payload(seed))
    content = result["content"]

    # Hero image: id 42 + hero.jpg URL fragment.
    assert '"id":42' in content
    assert 'hero.jpg' in content

    # Feature image: id 99 + feature.jpg URL fragment.
    assert '"id":99' in content
    assert 'feature.jpg' in content

    # No unresolved image id placeholder should remain.
    assert '"id":0' not in content


def test_non_home_slug_uses_native_divi_serialization():
    """Non-home routes use the same native Divi composer as home."""
    seed = {
        "slug": "wir",
        "title": "Wir",
        "sections": [
            {
                "section_id": "intro",
                "section_title": "Wir",
                "section_text": "<p>Text</p>",
                "section_layout": "rich_text",
                "section_config": {"styleVariant": "feature"},
                "buttons": [],
            },
        ],
    }
    result = _json_php(_build_desired_content_payload(seed))
    names = set(_findall_block_comment_names(result["content"]))
    assert names == {"divi/section", "divi/row", "divi/column", "divi/heading", "divi/text"}
def test_home_emits_one_label_per_section():
    """Home emits ordered labels for the native Divi sections."""
    seed = {
        "slug": "home",
        "title": "Home",
        "hero": {"hero_title": "H", "hero_subtitle": "S", "image_url": "", "image_alt": ""},
        "sections": [
            {
                "section_id": "intro",
                "section_title": "Intro",
                "section_text": "<p>T</p>",
                "section_layout": "rich_text",
                "section_config": {"styleVariant": "feature"},
                "buttons": [],
            },
        ],
    }
    result = _json_php(_build_desired_content_payload(seed))
    assert result["labels"] == ["__hero__", "intro", "__home_chrome__"]


def test_native_divi_post_content_is_slashed_before_wordpress_writes():
    pages = ROOT / "wordpress/web/app/mu-plugins/bioco-import/includes/pages.php"
    collections = ROOT / "wordpress/web/app/mu-plugins/bioco-import/includes/collections.php"

    pages_php = pages.read_text()
    collections_php = collections.read_text()
    assert pages_php.count("'post_content' => wp_slash($desiredContent)") == 2
    assert collections_php.count("'post_content' => wp_slash($content)") == 1
    assert collections_php.count("$changed['post_content'] = wp_slash($content)") == 1
    assert "'post_content' => $content" not in collections_php


# ---------------------------------------------------------------------------
# Page shell metadata (home only)
# ---------------------------------------------------------------------------


def test_home_apply_create_sets_divi_builder_meta():
    """Creating home in apply mode sets Divi builder and full-width layout keys."""
    result = _json_php(_page_import_payload(_home_seed(), mode="apply"))
    writes = {(w["post_id"], w["key"]): w["value"] for w in result["meta_writes"]}
    assert writes[(7, "_et_pb_use_builder")] == "on"
    assert writes[(7, "_et_pb_page_layout")] == "et_full_width_page"


def test_home_apply_update_sets_divi_builder_meta():
    """Updating an existing home page still repairs the Divi shell metadata."""
    result = _json_php(_page_import_payload(_home_seed(), mode="apply", existing_content=""))
    writes = {(w["post_id"], w["key"]): w["value"] for w in result["meta_writes"]}
    assert writes[(7, "_et_pb_use_builder")] == "on"
    assert writes[(7, "_et_pb_page_layout")] == "et_full_width_page"


def test_home_apply_ok_equal_still_sets_divi_builder_meta():
    """Even when content is already equal, home metadata is idempotently repaired."""
    result = _json_php(_page_import_payload(_home_seed(), mode="apply", existing_content=None))
    writes = {(w["post_id"], w["key"]): w["value"] for w in result["meta_writes"]}
    assert writes[(7, "_et_pb_use_builder")] == "on"
    assert writes[(7, "_et_pb_page_layout")] == "et_full_width_page"


def test_home_dry_run_does_not_write_builder_meta():
    """Dry-run reports but never writes Divi shell metadata."""
    result = _json_php(_page_import_payload(_home_seed(), mode="report"))
    assert result["meta_writes"] == []


def test_non_home_slug_sets_divi_builder_meta():
    """Every imported route receives the Divi builder layout metadata."""
    seed = {
        "slug": "wir",
        "title": "Wir",
        "sections": [
            {
                "section_id": "intro",
                "section_title": "Wir",
                "section_text": "<p>Text</p>",
                "section_layout": "rich_text",
                "section_config": {"styleVariant": "feature"},
                "buttons": [],
            },
        ],
    }
    result = _json_php(_page_import_payload(seed, mode="apply", existing_content=""))
    writes = {(w["post_id"], w["key"]): w["value"] for w in result["meta_writes"]}
    assert writes[(7, "_et_pb_use_builder")] == "on"
    assert writes[(7, "_et_pb_page_layout")] == "et_full_width_page"


def test_home_apply_idempotent_meta_only_writes_when_different():
    """Home does not rewrite metadata when the desired values are already present."""
    result = _json_php(
        _page_import_payload(
            _home_seed(),
            mode="apply",
            existing_content=None,
            preseed_meta={
                "_et_pb_use_builder": "on",
                "_et_pb_page_layout": "et_full_width_page",
            },
        )
    )
    keys = {w["key"] for w in result["meta_writes"]}
    assert "_et_pb_use_builder" not in keys
    assert "_et_pb_page_layout" not in keys


# ---------------------------------------------------------------------------
# Verifier
# ---------------------------------------------------------------------------


def test_verify_non_home_matches_native_tree():
    """Non-home verifier compares full native Divi trees."""
    seed = {
        "slug": "wir",
        "title": "Wir",
        "hero": {"hero_title": "H", "hero_subtitle": "S", "image_url": "", "image_alt": ""},
        "sections": [
            {
                "section_id": "intro",
                "section_title": "Intro",
                "section_text": "<p>T</p>",
                "section_layout": "rich_text",
                "section_config": {"styleVariant": "feature"},
                "buttons": [],
            },
        ],
    }
    seed_b64 = base64.b64encode(json.dumps(seed).encode("utf-8")).decode("ascii")

    hero_b64 = _base64_item({"block": "hero", "values": {"headline": "H", "subtitle": "S", "image": 0, "image_alt": ""}})
    intro_b64 = _base64_item({"block": "rich-text", "values": {"anchor": "intro", "title": "Intro", "text": "<p>T</p>", "buttons": [], "style_variant": "feature"}})
    php = (
        _preamble()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/report.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/documents.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/verify.php';\n"
        + f"$seed = json_decode(base64_decode('{seed_b64}'), true);\n"
        + "$hero = json_decode(base64_decode('" + hero_b64 + "'), true);\n"
        + "$intro = json_decode(base64_decode('" + intro_b64 + "'), true);\n"
        + "$heroTree = Bioco_Import_Divi_Composer::section($hero);\n"
        + "$introTree = Bioco_Import_Divi_Composer::section($intro);\n"
        + "$postContent =\n"
        + "    '<!-- bioco:section __hero__ -->\\n' . "
        + "    bioco_import_serialize_divi_blocks([$heroTree]) . "
        + "    '<!-- bioco:section intro -->\\n' . "
        + "    bioco_import_serialize_divi_blocks([$introTree]);\n"
        + "function parse_blocks($content) {\n"
        + "    global $postContent, $heroTree, $introTree;\n"
        + "    if ($content !== $postContent) return [];\n"
        + "    return [\n"
        + "        ['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<!-- bioco:section __hero__ -->', 'innerContent' => ['<!-- bioco:section __hero__ -->']],\n"
        + "        $heroTree,\n"
        + "        ['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<!-- bioco:section intro -->', 'innerContent' => ['<!-- bioco:section intro -->']],\n"
        + "        $introTree,\n"
        + "    ];\n"
        + "}\n"
        + "$GLOBALS['BIOCO_TEST_PAGES']['wir'] = $postContent;\n"
        + "$report = bioco_import_report_new();\n"
        + "bioco_import_verify_seed($seed, $report);\n"
        + "echo json_encode(['rows' => $report['rows']]);"
    )
    result = _json_php(php)
    statuses = [r["status"] for r in result["rows"]]
    assert "verify-match" in statuses
    assert "verify-mismatch" not in statuses
    assert "verify-missing" not in statuses


def test_verify_home_detects_native_tree_mismatch():
    """Home verifier reports verify-mismatch when Divi trees differ."""
    seed = {
        "slug": "home",
        "title": "Home",
        "hero": {"hero_title": "H", "hero_subtitle": "S", "image_url": "", "image_alt": ""},
        "sections": [],
    }
    seed_b64 = base64.b64encode(json.dumps(seed).encode("utf-8")).decode("ascii")
    wrong_hero_b64 = _base64_item({"block": "hero", "values": {"headline": "WRONG", "subtitle": "S", "image": 0, "image_alt": ""}})

    php = (
        _preamble()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/report.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/documents.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/verify.php';\n"
        + f"$seed = json_decode(base64_decode('{seed_b64}'), true);\n"
        + "$wrongHero = json_decode(base64_decode('" + wrong_hero_b64 + "'), true);\n"
        + "$wrongHeroTree = Bioco_Import_Divi_Composer::section($wrongHero);\n"
        + "$postContent = '<!-- bioco:section __hero__ -->\\n' . bioco_import_serialize_divi_blocks([$wrongHeroTree]);\n"
        + "$GLOBALS['BIOCO_TEST_PAGES']['home'] = $postContent;\n"
        + "function parse_blocks($content) {\n"
        + "    global $postContent, $wrongHeroTree;\n"
        + "    if ($content !== $postContent) return [];\n"
        + "    return [\n"
        + "        ['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<!-- bioco:section __hero__ -->', 'innerContent' => ['<!-- bioco:section __hero__ -->']],\n"
        + "        $wrongHeroTree,\n"
        + "    ];\n"
        + "}\n"
        + "$report = bioco_import_report_new();\n"
        + "bioco_import_verify_seed($seed, $report);\n"
        + "echo json_encode(['rows' => $report['rows']]);"
    )
    result = _json_php(php)
    statuses = [r["status"] for r in result["rows"]]
    assert "verify-mismatch" in statuses
    assert "verify-match" not in statuses


def test_verify_duplicate_markers_still_use_fifo():
    """Existing duplicate-marker FIFO behavior remains for native sections."""
    seed = {
        "slug": "wir",
        "title": "Home",
        "hero": {"hero_title": "", "hero_subtitle": "", "image_url": "", "image_alt": ""},
        "sections": [
            {
                "section_id": "gruppen",
                "section_title": "Gruppen",
                "section_text": "<p>A</p>",
                "section_layout": "rich_text",
                "section_config": {"styleVariant": "feature"},
                "buttons": [],
            },
        ],
    }
    seed_b64 = base64.b64encode(json.dumps(seed).encode("utf-8")).decode("ascii")
    item_b64 = _base64_item({"block": "rich-text", "values": {"anchor": "gruppen", "title": "Gruppen", "text": "<p>A</p>", "buttons": [], "style_variant": "feature"}})

    php = (
        _preamble()
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/report.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/documents.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/verify.php';\n"
        + f"$seed = json_decode(base64_decode('{seed_b64}'), true);\n"
        + "$item = json_decode(base64_decode('" + item_b64 + "'), true);\n"
        + "$tree = Bioco_Import_Divi_Composer::section($item);\n"
        + "$serialized = bioco_import_serialize_divi_blocks([$tree]);\n"
        + "$postContent = '<!-- bioco:section gruppen -->\\n' . $serialized . '<!-- bioco:section gruppen -->\\n' . $serialized;\n"
        + "$GLOBALS['BIOCO_TEST_PAGES']['wir'] = $postContent;\n"
        + "function parse_blocks($content) {\n"
        + "    global $postContent, $tree;\n"
        + "    if ($content !== $postContent) return [];\n"
        + "    return [\n"
        + "        ['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<!-- bioco:section gruppen -->', 'innerContent' => ['<!-- bioco:section gruppen -->']],\n"
        + "        $tree,\n"
        + "        ['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<!-- bioco:section gruppen -->', 'innerContent' => ['<!-- bioco:section gruppen -->']],\n"
        + "        $tree,\n"
        + "    ];\n"
        + "}\n"
        + "$report = bioco_import_report_new();\n"
        + "bioco_import_verify_seed($seed, $report);\n"
        + "echo json_encode(['rows' => $report['rows']]);"
    )
    result = _json_php(php)
    # First gruppen matches, second is unconsumed (no missing/mismatch rows for it).
    statuses = [r["status"] for r in result["rows"]]
    assert statuses.count("verify-match") == 1
    assert "verify-mismatch" not in statuses
    assert "verify-missing" not in statuses
