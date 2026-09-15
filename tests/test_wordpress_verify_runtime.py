"""Runtime-Prüfung: tests/README.md, Keep/Replace/Remove-Karte."""
import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
SEED_DIR = ROOT / "wordpress/content-seed"

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

# parse_blocks() is a WordPress core function. The suite loads the REAL
# WordPress 7.1 block parser from the attributed fixture instead of a
# reimplementation, so the runtime gate is proven against genuine parser
# behaviour (freeform merging, self-recovery of missing closers, …).
HARNESS = r'''<?php
// Model an installation without ext-dom while retaining the real verifier.
// Only the dedicated subprocess test disables the built-in class_exists.
if (!function_exists('class_exists')) {
    function class_exists($name, $autoload = true) {
        if ($name === 'DOMDocument') return false;
        try { new ReflectionClass($name); return true; }
        catch (ReflectionException $error) { return false; }
    }
}
define('ABSPATH', __DIR__ . '/');
error_reporting(E_ALL);

require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';

function parse_blocks($content) {
    return (new WP_Block_Parser())->parse((string) $content);
}

$GLOBALS['BIOCO_RENDER_CALLS'] = [];
$GLOBALS['BIOCO_RENDER_CONTEXT'] = [];

// render_block() is an external boundary: the harness returns the
// scenario-provided render_html verbatim (default: a meaningful paragraph)
// and records every call. The gate's interpretation of renderer output is
// what these tests prove — NOT that a fake renderer models real Divi. The
// REAL WordPress integration run proves actual module behaviour.
function render_block($block) {
    $GLOBALS['BIOCO_RENDER_CALLS'][] = (string) ($block['blockName'] ?? 'freeform');
    $GLOBALS['BIOCO_RENDER_CONTEXT'][] = $GLOBALS['post'] ?? null;
    $name = (string) ($block['blockName'] ?? '');
    if (!empty($GLOBALS['BIOCO_RENDER_EXCEPTION_FOR']) && $name === $GLOBALS['BIOCO_RENDER_EXCEPTION_FOR']) {
        throw new RuntimeException('Divi-Renderer-Absturz fuer ' . $name);
    }
    $index = count($GLOBALS['BIOCO_RENDER_CALLS']) - 1;
    if (isset($GLOBALS['BIOCO_RENDER_HTML_SEQUENCE'][$index])) {
        return (string) $GLOBALS['BIOCO_RENDER_HTML_SEQUENCE'][$index];
    }
    return (string) ($GLOBALS['BIOCO_RENDER_HTML'] ?? '<p>Abchnitt mit Inhalt gerendert.</p>');
}

function is_wp_error($value) { return false; }
function esc_html($value) { return (string) $value; }
function wp_slash($value) { return $value; }

$GLOBALS['BIOCO_TEST_PAGES'] = [];       // slug => content string
$GLOBALS['BIOCO_URL_TO_ATTACHMENT'] = [
    'https://example.com/hero.jpg' => 42,
    'https://example.com/feature.jpg' => 99,
];
$GLOBALS['BIOCO_ATTACHMENT_TO_URL'] = [
    42 => 'https://example.com/hero.jpg',
    99 => 'https://example.com/feature.jpg',
];
$GLOBALS['BIOCO_META'] = [];
$GLOBALS['BIOCO_NEXT_ATTACHMENT_ID'] = 1000;

function get_posts($args) {
    $type = $args['post_type'] ?? '';
    if ($type === 'page' && isset($args['name'])) {
        $slug = $args['name'];
        if (isset($GLOBALS['BIOCO_TEST_PAGES'][$slug])) {
            return [(object) ['ID' => 7, 'post_content' => $GLOBALS['BIOCO_TEST_PAGES'][$slug], 'post_status' => 'publish']];
        }
        return [];
    }
    if ($type === 'attachment' && isset($args['meta_value']) && isset($GLOBALS['BIOCO_URL_TO_ATTACHMENT'][$args['meta_value']])) {
        return [(object) ['ID' => $GLOBALS['BIOCO_URL_TO_ATTACHMENT'][$args['meta_value']]]];
    }
    // Documents are tagged via update_post_meta after sideload; the meta
    // store is the source of truth for their reuse on re-run.
    if ($type === 'attachment' && isset($args['meta_key']) && $args['meta_key'] === '_bioco_import_source_url') {
        foreach ($GLOBALS['BIOCO_META'] as $postId => $meta) {
            if (($meta['_bioco_import_source_url'] ?? null) === ($args['meta_value'] ?? null)) {
                return [(object) ['ID' => $postId]];
            }
        }
    }
    return [];
}

function get_post($id) {
    return (object) ['ID' => 7, 'post_content' => reset($GLOBALS['BIOCO_TEST_PAGES']) ?: '', 'post_status' => 'publish'];
}

function get_post_meta($post_id, $key, $single = true) {
    if (!$single) return isset($GLOBALS['BIOCO_META'][$post_id][$key]) ? [$GLOBALS['BIOCO_META'][$post_id][$key]] : [];
    return $GLOBALS['BIOCO_META'][$post_id][$key] ?? '';
}

function update_post_meta($post_id, $key, $value) {
    $GLOBALS['BIOCO_META'][$post_id][$key] = $value;
    return true;
}

function wp_get_attachment_image_url($id, $size = 'full') {
    return $GLOBALS['BIOCO_ATTACHMENT_TO_URL'][$id] ?? false;
}

function media_sideload_image($url, $post_id, $description, $return) {
    $id = $GLOBALS['BIOCO_NEXT_ATTACHMENT_ID']++;
    $GLOBALS['BIOCO_URL_TO_ATTACHMENT'][$url] = $id;
    $GLOBALS['BIOCO_ATTACHMENT_TO_URL'][$id] = $url;
    return $id;
}

function wp_tempnam($name) { return tempnam(sys_get_temp_dir(), 'bioco-doc-'); }
function media_handle_sideload($fileArray, $postId) {
    if (!is_file($fileArray['tmp_name'])) return (object) ['error' => ['no-file']];
    $id = $GLOBALS['BIOCO_NEXT_ATTACHMENT_ID']++;
    $GLOBALS['BIOCO_ATTACHMENT_TO_URL'][$id] = 'https://staging.bioco.test/wp-content/uploads/' . $fileArray['name'];
    return $id;
}
function wp_get_attachment_url($id) { return $GLOBALS['BIOCO_ATTACHMENT_TO_URL'][$id] ?? false; }
function wp_delete_file($path) { return @unlink($path); }

WP_SERIALIZER

require 'wordpress/web/app/mu-plugins/bioco-import/includes/report.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/documents.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/verify.php';

$scenario = json_decode(file_get_contents($argv[1]), true);
$GLOBALS['BIOCO_RENDER_HTML'] = $scenario['render_html'] ?? '<p>Abchnitt mit Inhalt gerendert.</p>';
$GLOBALS['BIOCO_RENDER_EXCEPTION_FOR'] = $scenario['render_exception_for'] ?? '';
$GLOBALS['BIOCO_RENDER_HTML_SEQUENCE'] = $scenario['render_html_sequence'] ?? [];

// Build the WordPress state exactly the way a real import would have.
if (!empty($scenario['build_seeds'])) {
    $seeds = bioco_import_load_seeds($scenario['seed_dir'], $scenario['only'] ?? []);
    foreach ($seeds as $seed) {
        if (($seed['slug'] ?? '') === 'kontakt' && !empty($scenario['edit_kontakt'])) {
            $seed['sections'][0]['section_text'] = str_replace(
                'Hast du Fragen zu biocò?',
                'Jetzt redaktionell gepflegt:',
                (string) $seed['sections'][0]['section_text']
            );
        }
        $buildReport = bioco_import_report_new();
        [$content, $labels] = bioco_import_build_desired_content($seed, 'apply', $buildReport);
        $GLOBALS['BIOCO_TEST_PAGES'][$seed['slug']] = $content;
    }
}

if (isset($scenario['pages_override'])) {
    foreach ($scenario['pages_override'] as $slug => $content) {
        $GLOBALS['BIOCO_TEST_PAGES'][$slug] = base64_decode($content);
    }
}

$seeds = bioco_import_load_seeds($scenario['seed_dir'], $scenario['only'] ?? []);
$report = bioco_import_report_new();
if (!empty($scenario['runtime'])) {
    bioco_import_run_runtime_verify($seeds, $report);
} else {
    bioco_import_run_verify($seeds, $report);
}
echo json_encode([
    'counts' => $report['counts'],
    'rows' => $report['rows'],
    'render_calls' => $GLOBALS['BIOCO_RENDER_CALLS'],
    'render_context' => $GLOBALS['BIOCO_RENDER_CONTEXT'],
    'post_after_run' => $GLOBALS['post'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
'''


def _b64(text: str) -> str:
    import base64
    return base64.b64encode(text.encode("utf-8")).decode("ascii")


def _run_scenario(tmp_path: Path, scenario: dict, php_options=()) -> dict:
    scenario = dict(scenario)
    scenario["seed_dir"] = str(SEED_DIR)
    scenario_path = tmp_path / "scenario.json"
    scenario_path.write_text(json.dumps(scenario, ensure_ascii=False), encoding="utf-8")
    harness_path = tmp_path / "harness-verify.php"
    harness_path.write_text(HARNESS.replace("WP_SERIALIZER", WP_SERIALIZER_STUB), encoding="utf-8")
    proc = subprocess.run(
        ["php", *php_options, "-d", "error_reporting=E_ALL", "-f", str(harness_path), str(scenario_path)],
        cwd=ROOT,
        text=True,
        capture_output=True,
    )
    assert proc.returncode == 0, proc.stdout + proc.stderr
    assert proc.stdout.strip(), proc.stderr
    return json.loads(proc.stdout)


def _edited_divi_content() -> str:
    # Hand-authored Divi markup with REAL Divi attrs (module bodies live in
    # attrs.<key>.innerContent.<device>.value, exactly like the composer and
    # the Divi editor save them): valid structure, editorially changed text
    # and layout, no bioco:section markers at all.
    text_module = (
        '<!-- wp:divi/text {"content":{"innerContent":{"desktop":'
        '{"value":"<p>Kontakt im Divi-Builder redaktionell ueberarbeitet.</p>"}}}} -->\n'
        "<!-- /wp:divi/text -->\n"
    )
    heading_module = (
        '<!-- wp:divi/heading {"title":{"innerContent":{"desktop":'
        '{"value":"Neue Reihenfolge"}}}} -->\n'
        "<!-- /wp:divi/heading -->\n"
    )
    return (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        + text_module
        + "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
        "\n"
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        + heading_module
        + "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )


# ---------------------------------------------------------------------------
# Runtime verification: every required page present, non-empty, valid Divi.
# ---------------------------------------------------------------------------


def test_runtime_verify_accepts_every_page_after_editorial_change(tmp_path):
    result = _run_scenario(tmp_path, {"build_seeds": True, "edit_kontakt": True, "runtime": True})

    counts = result["counts"]
    assert counts.get("runtime-ok", 0) == 22, counts
    for status in ("runtime-missing", "runtime-empty", "runtime-corrupt", "error"):
        assert counts.get(status, 0) == 0, counts
    # The output proof ran the real render path for every top-level section.
    assert len(result["render_calls"]) >= 22, result["render_calls"][:5]


def test_runtime_verify_reports_missing_dom_and_continues(tmp_path):
    result = _run_scenario(
        tmp_path,
        {"build_seeds": True, "runtime": True, "render_html": "<img src='/test.png'>"},
        php_options=("-d", "disable_functions=class_exists"),
    )
    assert result["counts"].get("runtime-corrupt") == 22
    assert len({row["page"] for row in result["rows"]}) == 22
    assert "ext-dom" in json.dumps(result["rows"])


def test_runtime_verify_accepts_valid_editorial_content_without_section_markers(tmp_path):
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True, "pages_override": {"kontakt": _b64(_edited_divi_content())}},
    )

    counts = result["counts"]
    assert counts.get("runtime-ok", 0) == 1, counts
    assert not any(status.startswith("runtime-") and status != "runtime-ok" for status in counts)


def test_runtime_verify_flags_a_missing_required_page(tmp_path):
    result = _run_scenario(tmp_path, {"only": ["kontakt"], "runtime": True})

    counts = result["counts"]
    assert counts.get("runtime-missing", 0) == 1, counts
    statuses = [r["status"] for r in result["rows"]]
    assert "runtime-ok" not in statuses


def test_runtime_verify_flags_empty_page_content(tmp_path):
    result = _run_scenario(
        tmp_path,
        {
            "only": ["kontakt"],
            "runtime": True,
            "pages_override": {"kontakt": _b64("   \n  ")},
        },
    )

    counts = result["counts"]
    assert counts.get("runtime-empty", 0) == 1, counts


def test_runtime_verify_flags_a_marker_without_a_following_section(tmp_path):
    # A bioco:section marker with no block behind it: the structure that the
    # marker promised is gone. Must fail, not be collected as a healthy marker.
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True, "pages_override": {"kontakt": _b64("<!-- bioco:section intro -->")}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    assert "runtime-ok" not in counts


def test_runtime_verify_flags_a_broken_block_comment_nested_inside_a_column(tmp_path):
    # The real WordPress parser does not tokenize a malformed wp: comment
    # nested inside a column (lowercase-name regex) — it stays as raw
    # innerHTML there. Top-level broken comments were already rejected; this
    # is the nested gap. Real-parser regression, no renderer modeling.
    content = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>Guter Text.</p>"}}}} -->\n'
        "<!-- /wp:divi/text -->\n"
        "<!-- wp:divi/BROKEN /-->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True, "pages_override": {"kontakt": _b64(content)}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    assert "runtime-ok" not in counts
    details = " | ".join(r["detail"] for r in result["rows"])
    assert "Block-Kommentar-Rest" in details, details
    assert "wp:divi/BROKEN" in details, details


def test_runtime_verify_accepts_a_specialty_shaped_section_with_direct_columns(tmp_path):
    # Divi specialty sections legitimately have DIRECT column children (no
    # row): SectionModule reads the direct inner blocks and synthesizes its
    # own row wrapper. The gate must accept this shape through the renderer —
    # no universal direct-row requirement, no row/module whitelist.
    content = (
        '<!-- wp:divi/section {"module":{"advanced":{"type":{"desktop":{"value":"specialty"}}}}} -->\n'
        "<!-- wp:divi/column -->\n"
        '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>Spalte Eins.</p>"}}}} -->\n'
        "<!-- /wp:divi/text -->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- wp:divi/column -->\n"
        '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>Spalte Zwei.</p>"}}}} -->\n'
        "<!-- /wp:divi/text -->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True, "pages_override": {"kontakt": _b64(content)}},
    )

    counts = result["counts"]
    assert counts.get("runtime-ok", 0) == 1, counts
    assert not any(status.startswith("runtime-") and status != "runtime-ok" for status in counts), counts
    assert result["render_calls"] == ["divi/section"], result["render_calls"]


def test_runtime_verify_flags_marker_plus_unmatched_closer_plus_text(tmp_path):
    # The real parser turns this into ONE freeform block containing the
    # marker, the stray closing comment and the text. The stray fragment
    # must be detected inside that merged block.
    content = "<!-- bioco:section intro -->\n<!-- /wp:divi/section -->\nKaputter Rest."
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True, "pages_override": {"kontakt": _b64(content)}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    assert "runtime-ok" not in counts


def test_runtime_verify_flags_missing_section_closer_despite_parser_recovery(tmp_path):
    # WordPress's parser auto-recovers from a missing closer at end of file
    # and hands back a perfectly formed section/row tree. The raw markup is
    # still unbalanced and must fail.
    content = "<!-- wp:divi/section -->\n<!-- wp:divi/row /-->"
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True, "pages_override": {"kontakt": _b64(content)}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    assert "runtime-ok" not in counts


def test_runtime_verify_flags_nested_empty_row_column_tree(tmp_path):
    # Section > row > column with no usable module anywhere: renders nothing.
    content = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column /-->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True,
            "render_html": '', "pages_override": {"kontakt": _b64(content)}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    assert "runtime-ok" not in counts


def test_runtime_verify_flags_a_self_closing_section(tmp_path):
    content = "<!-- wp:divi/section /-->"
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True, "pages_override": {"kontakt": _b64(content)}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) == 1, counts


def test_runtime_verify_flags_plain_text_without_divi_sections(tmp_path):
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True, "pages_override": {"kontakt": _b64("<p>Kein Divi-Abschnitt, nur ein Absatz.</p>")}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) == 1, counts



def test_runtime_verify_flags_mismatched_closer_order_despite_equal_counts(tmp_path):
    # section > row > text, but the section is closed before the row. Raw
    # opener/closer counts are equal; only delimiter ORDER sees the fault.
    content = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>X</p>"}}}} -->\n'
        "<!-- /wp:divi/text -->\n"
        "<!-- /wp:divi/section -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/column -->\n"
    )
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True, "pages_override": {"kontakt": _b64(content)}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    assert "runtime-ok" not in counts


def test_runtime_verify_flags_an_empty_supported_module(tmp_path):
    # A supported Divi module without any content renders nothing.
    content = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        "<!-- wp:divi/text /-->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True,
            "render_html": '', "pages_override": {"kontakt": _b64(content)}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    assert "runtime-ok" not in counts


def test_runtime_verify_flags_a_section_rendering_only_a_script(tmp_path):
    # core/html with only a script renders nothing visible, and arbitrary
    # nonstructural block names cannot prove output at all. Neither counts
    # as content, so the section is empty.
    content = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        '<!-- wp:core/html --><script>window.x = 1;</script><!-- /wp:core/html -->\n'
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True,
            "render_html": '<script>window.x = 1;</script>', "pages_override": {"kontakt": _b64(content)}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    assert "runtime-ok" not in counts


def test_runtime_verify_flags_an_unknown_module_without_content(tmp_path):
    content = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        "<!-- wp:bioco/missing-module /-->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    result = _run_scenario(
        tmp_path,
        {"only": ["kontakt"], "runtime": True,
            "render_html": '', "pages_override": {"kontakt": _b64(content)}},
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    assert "runtime-ok" not in counts


def test_runtime_verify_accepts_a_text_section_plus_a_decorative_divider_section(tmp_path):
    # Real Divi decorative modules (divider, spacer) render an EMPTY element;
    # their visible line/height lives in CSS (:before, spacer height). A text
    # section plus a decorative divider/spacer section is legitimate editorial
    # layout: the gate must judge meaningful output PER REQUIRED PAGE, not per
    # section, so the decorative section may render empty without failing.
    text_section = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>Redaktionell gepflegter Kontakttext.</p>"}}}} -->\n'
        "<!-- /wp:divi/text -->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    divider_section = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        "<!-- wp:divi/divider /-->\n"
        "<!-- wp:divi/spacer /-->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    result = _run_scenario(
        tmp_path,
        {
            "only": ["kontakt"],
            "runtime": True,
            "pages_override": {"kontakt": _b64(text_section + divider_section)},
            # Real Divi renderer behaviour: divider/spacer render an empty
            # element (their line/height lives in CSS, not in the HTML).
            "render_html_sequence": [
                "<p>Redaktionell gepflegter Kontakttext.</p>",
                '<div class="et_pb_divider_internal"></div><div class="et_pb_space"></div>',
            ],
        },
    )

    counts = result["counts"]
    assert counts.get("runtime-ok", 0) == 1, counts
    assert not any(status.startswith("runtime-") and status != "runtime-ok" for status in counts), counts
    # BOTH sections were rendered (the divider was not skipped); only the
    # page-level judgment decided the outcome.
    assert result["render_calls"] == ["divi/section", "divi/section"], result["render_calls"]


def test_runtime_verify_flags_a_page_that_renders_nothing_at_all(tmp_path):
    # The inverse regression: a required page made ONLY of decorative
    # sections shows a visitor nothing — the page-level aggregate must fail.
    divider_section = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        "<!-- wp:divi/divider /-->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    spacer_section = divider_section.replace("divider", "spacer")
    result = _run_scenario(
        tmp_path,
        {
            "only": ["kontakt"],
            "runtime": True,
            "pages_override": {"kontakt": _b64(divider_section + spacer_section)},
            "render_html_sequence": [
                '<div class="et_pb_divider_internal"></div>',
                '<div class="et_pb_space"></div>',
            ],
        },
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    assert "runtime-ok" not in counts
    details = " | ".join(r["detail"] for r in result["rows"])
    assert "insgesamt" in details, details


def test_runtime_verify_flags_invisible_only_text_output(tmp_path):
    # Output-adapter cases: &nbsp;/&#160; decode to NBSP, &#8203; to a
    # zero-width space, and <template> content never displays. None of them
    # is visible output; the page-level judgment must reject such a page.
    content = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>Platzhalter</p>"}}}} -->\n'
        "<!-- /wp:divi/text -->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    for invisible_html in (
        "<p>&nbsp;</p>",
        "<p>&#160;</p>",
        "<template><p>Invisible</p></template>",
        "<p>&#8203;</p>",
        # Hidden form controls are invisible for every quoting/casing variant.
        '<input type="hidden">',
        "<input type=hidden>",
        "<input type='hidden'>",
        '<input type = "hidden" >',
        '<INPUT TYPE="Hidden">',
        # A quoted value containing > must not truncate the tag before the
        # real type attribute: these are still hidden controls.
        '<input title="a>b" type="hidden">',
        '<input type=hidden title="a>b">',
        # Structural wrappers alone carry nothing visible.
        "<form></form>",
        "<table></table>",
        "<picture></picture>",
        '<source srcset="bild.jpg">',
        "<form><input type=\"hidden\"></form>",
        "<table><tbody></tbody></table>",
    ):
        result = _run_scenario(
            tmp_path,
            {
                "only": ["kontakt"],
                "runtime": True,
                "pages_override": {"kontakt": _b64(content)},
                "render_html": invisible_html,
            },
        )

        counts = result["counts"]
        assert counts.get("runtime-corrupt", 0) >= 1, invisible_html
        assert "runtime-ok" not in counts, invisible_html
        details = " | ".join(r["detail"] for r in result["rows"])
        assert "insgesamt" in details, invisible_html


def test_runtime_verify_accepts_visible_controls_and_media_inside_wrappers(tmp_path):
    # The wrapper rule is bounded: form/table/picture/source carry nothing
    # visible THEMSELVES, but their actual child media/text/visible controls
    # still count. No CSS visibility simulation, no module allowlist.
    content = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>Platzhalter</p>"}}}} -->\n'
        "<!-- /wp:divi/text -->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    for visible_html in (
        # A real media tag BEFORE an <inputx> non-tag occurrence: a scanner
        # that drops everything before the non-tag must not lose the image.
        '<img src="bild.jpg"><inputx>',
        '<form><input type="text"></form>',
        '<picture><img src="bild.jpg"></picture>',
        '<table><tr><td><img src="bild.jpg"></td></tr></table>',
        '<form><button>Absenden</button></form>',
        # Ordinary inputs stay visible: missing type, unknown type values
        # (browsers render them as text fields) and lookalike attributes that
        # are not the real type attribute.
        "<input>",
        '<input type="hiddenx">',
        '<input data-type="hidden">',
        '<input title="type=hidden">',
        # The lookalike attribute sits INSIDE a quoted value with words after
        # it: the old regex matched "type=hidden " there and wrongly stripped
        # a visible control. A scanner bug lost everything before a later
        # <input> occurrence ("<img src=x><inputx>"), so a preceding real
        # media tag vanished. The value " hidden " (padded) is NOT hidden:
        # browsers treat it as an unknown type and render a text field.
        '<input title="example type=hidden here">',
        '<input type=" hidden ">',
    ):
        result = _run_scenario(
            tmp_path,
            {
                "only": ["kontakt"],
                "runtime": True,
                "pages_override": {"kontakt": _b64(content)},
                "render_html": visible_html,
            },
        )

        counts = result["counts"]
        assert counts.get("runtime-ok", 0) == 1, visible_html
        assert "runtime-corrupt" not in counts, visible_html


def test_runtime_verify_accepts_a_populated_page_with_a_hidden_only_section(tmp_path):
    # Same page-level logic as for decorative divider/spacer sections: a
    # section that renders only a hidden control is allowed INDIVIDUALLY on a
    # populated page; a page made of ONLY hidden controls fails.
    text_section = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>Redaktionell gepflegter Text.</p>"}}}} -->\n'
        "<!-- /wp:divi/text -->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    hidden_section = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        '<!-- wp:core/html --><input type="hidden"><!-- /wp:core/html -->\n'
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    result = _run_scenario(
        tmp_path,
        {
            "only": ["kontakt"],
            "runtime": True,
            "pages_override": {"kontakt": _b64(text_section + hidden_section)},
            "render_html_sequence": [
                "<p>Redaktionell gepflegter Text.</p>",
                '<input type="hidden">',
            ],
        },
    )

    counts = result["counts"]
    assert counts.get("runtime-ok", 0) == 1, counts
    assert "runtime-corrupt" not in counts, counts



def test_runtime_verify_accepts_a_non_seed_module_that_renders_output(tmp_path):
    # A module outside the seed composer's vocabulary is legitimate editorial
    # content when the active renderer produces output for it.
    content = (
        "<!-- wp:divi/section -->\n"
        "<!-- wp:divi/row -->\n"
        "<!-- wp:divi/column -->\n"
        "<!-- wp:bioco/missing-module /-->\n"
        "<!-- /wp:divi/column -->\n"
        "<!-- /wp:divi/row -->\n"
        "<!-- /wp:divi/section -->\n"
    )
    result = _run_scenario(
        tmp_path,
        {
            "only": ["kontakt"],
            "runtime": True,
            "pages_override": {"kontakt": _b64(content)},
            "render_html": '<div class="accordion">Ausgeklappter Inhalt</div>',
        },
    )

    counts = result["counts"]
    assert counts.get("runtime-ok", 0) == 1, counts
    assert "runtime-corrupt" not in counts


def test_runtime_verify_flags_a_renderer_exception(tmp_path):
    result = _run_scenario(
        tmp_path,
        {
            "only": ["kontakt"],
            "runtime": True,
            "pages_override": {"kontakt": _b64(_edited_divi_content())},
            "render_exception_for": "divi/section",
        },
    )

    counts = result["counts"]
    assert counts.get("runtime-corrupt", 0) >= 1, counts
    details = " | ".join(r["detail"] for r in result["rows"])
    assert "Renderer-Fehler" in details, details



def test_runtime_render_runs_under_the_actual_page_context_and_restores_it(tmp_path):
    result = _run_scenario(
        tmp_path,
        {"build_seeds": True, "edit_kontakt": True, "runtime": True, "only": ["kontakt"]},
    )

    contexts = result["render_context"]
    assert contexts, "render_block was never called"
    for context in contexts:
        assert context is not None, "section rendered without page context"
        assert (context.get("ID") if isinstance(context, dict) else getattr(context, "ID", 0)) == 7, "section rendered for the wrong page"
    # The global post context is restored after verification.
    assert result["post_after_run"] in (None, ""), result["post_after_run"]


# ---------------------------------------------------------------------------
# Seed-parity verification stays exactly as it was: it must REJECT edited
# content. Runtime mode and seed mode are different contracts.
# ---------------------------------------------------------------------------


def test_seed_verify_still_rejects_editorially_changed_content(tmp_path):
    result = _run_scenario(
        tmp_path,
        {
            "build_seeds": True,
            "edit_kontakt": True,
            "runtime": False,
            "only": ["kontakt"],
        },
    )

    counts = result["counts"]
    assert counts.get("verify-mismatch", 0) >= 1, counts
