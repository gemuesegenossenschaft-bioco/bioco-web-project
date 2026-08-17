<?php
/**
 * `wp bioco verify`: re-reads each seeded page's post_content, parses the
 * native Divi block comments back out with WordPress core's parse_blocks(),
 * and compares each recovered tree against what the SAME plan builder
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

// section-label ("id" or "id1,id2") => ordered list of matching blocks.
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
            $result[$pendingLabel][] = [
                'blockName' => (string) $block['blockName'],
                'data' => is_array($block['attrs']['data'] ?? null) ? $block['attrs']['data'] : [],
                'block' => $block,
            ];
            $pendingLabel = null;
        }
    }
    return $result;
}

function bioco_import_take_marked_block(array &$blocks, $sectionLabel) {
    if (empty($blocks[$sectionLabel])) return null;
    return array_shift($blocks[$sectionLabel]);
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

        $found = bioco_import_take_marked_block($actualBlocks, $sectionLabel);
        if ($found === null) {
            bioco_import_report_row($report, $slug, $sectionLabel, $item['block'], 'verify-missing', 'Kein bioco:section-Marker + Block dafür in post_content gefunden.');
            continue;
        }

        if ($found['blockName'] !== 'divi/section') {
            bioco_import_report_row($report, $slug, $sectionLabel, $item['block'], 'verify-mismatch', "Block-Name: erwartet 'divi/section', gefunden '{$found['blockName']}'.");
            continue;
        }

        $composerItem = $item;
        $composerItem['values'] = $values;
        $expectedMarkup = serialize_block(Bioco_Import_Divi_Composer::section($composerItem));
        $actualMarkup = serialize_block($found['block']);
        if ($expectedMarkup === $actualMarkup) {
            bioco_import_report_row($report, $slug, $sectionLabel, $item['block'], 'verify-match', 'Native Divi-Struktur stimmt überein.');
        } else {
            bioco_import_report_row(
                $report, $slug, $sectionLabel, $item['block'], 'verify-mismatch',
                'Native Divi-Struktur weicht ab — erwartet: ' . bioco_import_excerpt($expectedMarkup) . ' — gefunden: ' . bioco_import_excerpt($actualMarkup) . '.'
            );
        }
    }
}

// Entry point used by the CLI command for `wp bioco verify`.
function bioco_import_run_verify(array $seeds, array &$report) {
    foreach ($seeds as $seed) {
        bioco_import_verify_seed($seed, $report);
    }
}
