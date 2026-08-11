<?php
/**
 * `wp bioco verify`: re-reads each seeded page's post_content, parses the
 * ACF block comments back out with WordPress core's parse_blocks(), and
 * compares the recovered field values against what the SAME plan builder
 * (section-map.php) says the seed should have produced — so import and
 * verify can never silently drift apart from each other.
 *
 * Blocks are matched back to seed section_id(s) via a plain HTML comment
 * marker ("<!-- bioco:section id1,id2 -->") written immediately before every
 * block by bioco_import_build_desired_content() — not by position, so
 * verify still works if a human reordered content in the block editor.
 * parse_blocks() itself does the HTML-comment/JSON parsing; this file never
 * hand-parses raw post_content.
 */

if (!defined('ABSPATH')) exit;

// section-label ("id" or "id1,id2") => ['blockName' => ..., 'data' => [...]]
function bioco_import_parse_marked_blocks($content) {
    $blocks = parse_blocks((string) $content);
    $result = [];
    $pendingLabel = null;

    foreach ($blocks as $block) {
        if ($block['blockName'] === null) {
            if (preg_match('/<!--\s*bioco:section\s+([^\s>]+)\s*-->/', (string) $block['innerHTML'], $m)) {
                $pendingLabel = $m[1];
            }
            continue;
        }
        if ($pendingLabel !== null) {
            $result[$pendingLabel] = [
                'blockName' => (string) $block['blockName'],
                'data' => is_array($block['attrs']['data'] ?? null) ? $block['attrs']['data'] : [],
            ];
            $pendingLabel = null;
        }
    }
    return $result;
}

function bioco_import_stringify_for_report($value) {
    return is_array($value) ? (string) wp_json_encode($value) : (string) $value;
}

// Normalises scalars to strings but preserves array STRUCTURE, so nested values
// stay comparable. Casting an array with (string) yields the literal "Array":
// two completely different galleries, repeaters or button lists compared that
// way would always look equal, and PHP would additionally emit an
// array-to-string conversion warning. Since most block content is now
// repeater-shaped, that flat cast would make `wp bioco verify` report a clean
// run for content it never actually checked — worse than having no verifier.
function bioco_import_normalize_for_compare($value) {
    if (!is_array($value)) {
        return (string) $value;
    }
    $normalized = [];
    foreach ($value as $key => $item) {
        $normalized[$key] = bioco_import_normalize_for_compare($item);
    }
    return $normalized;
}

function bioco_import_verify_data_map($slug, $sectionLabel, array $expected, array $actual, array &$report) {
    foreach ($expected as $key => $expectedValue) {
        if (!array_key_exists($key, $actual)) {
            bioco_import_report_row($report, $slug, $sectionLabel, $key, 'verify-missing', 'Feld fehlt im gespeicherten Block.');
            continue;
        }
        $actualValue = $actual[$key];
        // ACF/JSON round-tripping can turn "3" into 3 etc.; normalise scalars to
        // strings so type wobble alone never reports a false mismatch. Done
        // recursively — see bioco_import_normalize_for_compare().
        if (bioco_import_normalize_for_compare($expectedValue) === bioco_import_normalize_for_compare($actualValue)) {
            bioco_import_report_row($report, $slug, $sectionLabel, $key, 'verify-match', 'Übereinstimmung.');
            continue;
        }
        bioco_import_report_row(
            $report, $slug, $sectionLabel, $key, 'verify-mismatch',
            'erwartet: "' . bioco_import_excerpt(bioco_import_stringify_for_report($expectedValue)) . '" — gefunden: "' . bioco_import_excerpt(bioco_import_stringify_for_report($actualValue)) . '".'
        );
    }
}

function bioco_import_verify_seed(array $seed, array &$report) {
    $slug = (string) $seed['slug'];
    $post = bioco_import_find_page($slug);
    if (!$post) {
        bioco_import_report_row($report, $slug, '', '', 'verify-missing', 'Seite existiert nicht in WordPress.');
        return;
    }

    $actualBlocks = bioco_import_parse_marked_blocks((string) $post->post_content);
    $plan = bioco_import_build_page_plan($seed);

    foreach ($plan as $item) {
        if ($item['type'] === 'skip') continue; // already reported as a warn during import; nothing was ever written

        $sectionLabel = implode(',', $item['section_ids']);
        $values = $item['values'];
        $imageWarnings = [];
        // 'verify' is not 'apply', so this only ever reuses an already
        // sideloaded attachment (by source URL) or resolves to nothing —
        // it never downloads during verify.
        bioco_import_resolve_pending_images($values, 'verify', $imageWarnings);

        $fields = bioco_import_acf_group_fields($item['acf_group']);
        if (is_wp_error($fields)) {
            bioco_import_report_row($report, $slug, $sectionLabel, $item['block'], 'error', $fields->get_error_message());
            continue;
        }

        if (!isset($actualBlocks[$sectionLabel])) {
            bioco_import_report_row($report, $slug, $sectionLabel, $item['block'], 'verify-missing', 'Kein bioco:section-Marker + Block dafür in post_content gefunden.');
            continue;
        }

        $expectedBlockName = bioco_import_registered_block_name($item['block']);
        $found = $actualBlocks[$sectionLabel];
        if ($found['blockName'] !== $expectedBlockName) {
            bioco_import_report_row($report, $slug, $sectionLabel, 'name', 'verify-mismatch', "Block-Name: erwartet '{$expectedBlockName}', gefunden '{$found['blockName']}'.");
            continue;
        }

        $expectedData = bioco_import_acf_block_data($values, $fields);
        bioco_import_verify_data_map($slug, $sectionLabel, $expectedData, $found['data'], $report);
    }
}

// Entry point used by the CLI command for `wp bioco verify`.
function bioco_import_run_verify(array $seeds, array &$report) {
    foreach ($seeds as $seed) {
        bioco_import_verify_seed($seed, $report);
    }
}
