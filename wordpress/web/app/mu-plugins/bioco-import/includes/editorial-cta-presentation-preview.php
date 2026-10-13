<?php
/** Pure preview of native CTA and already-expanded text-column presentation. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/editorial-native-preview-values.php';

function bioco_editorial_cta_fill(array &$block, array $path, array $desktop, array $phone = []): void {
    $devices = ['desktop' => $desktop];
    if ($phone) $devices['phone'] = $phone;
    bioco_editorial_native_fill($block, $path, $devices);
}

function bioco_editorial_cta_remove(array &$block, string $class): void {
    bioco_editorial_native_remove($block, [$class]);
}

/** Bound conversion to the measured imported structures, including native topics. */
function bioco_editorial_cta_structure(array $section, bool $cta): void {
    $rows = $section['innerBlocks'] ?? [];
    if (count($rows) !== ($cta ? 1 : 2)) throw new RuntimeException('Unexpected CTA/text-column rows.');
    foreach ($rows as $row) {
        if (($row['blockName'] ?? '') !== 'divi/row') throw new RuntimeException('Expected native row.');
        foreach ($row['innerBlocks'] ?? [] as $column) {
            if (($column['blockName'] ?? '') !== 'divi/column') throw new RuntimeException('Expected native column.');
        }
    }
    $contentRow = $rows[$cta ? 0 : 1];
    if (count($contentRow['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Expected one measured content column.');
    $column = $contentRow['innerBlocks'][0];
    if (!in_array('bioco-divi-content', preg_split('/\s+/', bioco_import_editorial_class($column), -1, PREG_SPLIT_NO_EMPTY), true)) {
        throw new RuntimeException('Expected marked content column.');
    }
    $leaves = $column['innerBlocks'] ?? [];
    $names = array_column($leaves, 'blockName');
    $allowed = $cta ? [['divi/heading','divi/text','divi/button'], ['divi/heading','divi/text','divi/button','divi/button']]
                    : [['divi/image','divi/button']];
    if (!in_array($names, $allowed, true)) throw new RuntimeException('Unmeasured CTA/text-column modules.');
    if (!$cta) {
        $columns = $rows[0]['innerBlocks'] ?? [];
        if (count($columns) !== 2) throw new RuntimeException('Expected measured native topic columns.');
        foreach ($columns as $nativeColumn) {
            if (array_column($nativeColumn['innerBlocks'] ?? [], 'blockName') !== ['divi/blurb','divi/blurb']) {
                throw new RuntimeException('Unmeasured native topic modules.');
            }
        }
    }
}

function bioco_editorial_cta_native_leaf(array &$leaf): void {
    $classes = preg_split('/\s+/', bioco_import_editorial_class($leaf), -1, PREG_SPLIT_NO_EMPTY);
    if (($leaf['blockName'] ?? '') === 'divi/heading' && in_array('bioco-divi-title', $classes, true)) {
        $css = $leaf['attrs']['css']['desktop']['value']['mainElement'] ?? null;
        if ($css !== null && $css !== 'padding-bottom:0px;') {
            throw new RuntimeException('Authored heading CSS requires a measured preview.');
        }
        bioco_editorial_cta_fill($leaf, ['title', 'decoration', 'font', 'font'], [
            'size'=>'clamp(1.5rem, 3vw, 2rem)', 'lineHeight'=>'1.7em', 'weight'=>'700', 'color'=>'#1A1A1A',
        ]);
        bioco_editorial_cta_fill($leaf, ['module', 'decoration', 'spacing'], ['margin'=>['bottom'=>'2.75%']], ['margin'=>['bottom'=>'12px']]);
        bioco_editorial_cta_fill($leaf, ['css'], ['mainElement'=>'padding-bottom:0px;']);
        bioco_editorial_cta_remove($leaf, 'bioco-divi-title');
    } elseif (($leaf['blockName'] ?? '') === 'divi/text' && in_array('bioco-divi-text', $classes, true)) {
        bioco_editorial_cta_fill($leaf, ['content', 'decoration', 'bodyFont', 'body', 'font'], [
            'size'=>'16px', 'lineHeight'=>'1.7em', 'weight'=>'500', 'color'=>'#1A1A1A',
        ]);
        bioco_editorial_cta_fill($leaf, ['module', 'decoration', 'spacing'], ['margin'=>['bottom'=>'2.75%']], ['margin'=>['bottom'=>'24px']]);
        bioco_editorial_cta_remove($leaf, 'bioco-divi-text');
    }
}

function bioco_editorial_cta_native_visit(array &$blocks, array &$report): void {
    foreach ($blocks as &$section) {
        $classes = preg_split('/\s+/', bioco_import_editorial_class($section), -1, PREG_SPLIT_NO_EMPTY);
        $cta = ($section['blockName'] ?? '') === 'divi/section' && in_array('bioco-divi-cta', $classes, true);
        $topics = ($section['blockName'] ?? '') === 'divi/section' && in_array('bioco-divi-text-columns', $classes, true);
        if (!$cta && !$topics) {
            if (!empty($section['innerBlocks'])) bioco_editorial_cta_native_visit($section['innerBlocks'], $report);
            continue;
        }
        bioco_editorial_native_presets($section);
        bioco_editorial_cta_structure($section, $cta);
        // The current public inventory has exactly these three known structures.
        // Other imported variants require a measured preview rather than guesses.
        $expected = $cta ? ['bioco-divi-section','bioco-divi-cta','bioco-divi-align-left','bioco-divi-theme-soft','bioco-divi-rounded-xl']
                         : ['bioco-divi-section','bioco-divi-text-columns','bioco-divi-width-lg'];
        foreach ($classes as $class) {
            if (str_starts_with($class, 'bioco-divi-') && !in_array($class, $expected, true)) {
                throw new RuntimeException('Unmeasured CTA/text-column variant.');
            }
        }
        if (array_diff($expected, $classes)) throw new RuntimeException('Incomplete measured CTA/text-column variant.');
        $rows =& $section['innerBlocks'];
        if (!is_array($rows) || !$rows || ($cta && count($rows) !== 1)) throw new RuntimeException('Unexpected CTA/text-column rows.');
        bioco_editorial_cta_fill($section, ['module','decoration','background'], ['color'=>$cta ? '#FFFFFF' : '#F5F1E8']);
        bioco_editorial_cta_fill($section, ['module','decoration','spacing'], ['padding'=>['top'=>'54px','bottom'=>'54px','left'=>'0px','right'=>'0px']], ['padding'=>['top'=>'32px','bottom'=>'32px','left'=>'6vw','right'=>'6vw']]);
        if ($cta) bioco_editorial_cta_fill($section, ['module','decoration','border'], ['radius'=>array_fill_keys(['topLeft','topRight','bottomLeft','bottomRight'],'32px')]);
        foreach ($expected as $class) bioco_editorial_cta_remove($section, $class);
        foreach ($rows as &$row) {
            if (($row['blockName'] ?? '') !== 'divi/row') throw new RuntimeException('Expected native row.');
            bioco_editorial_cta_fill($row, ['module','decoration','sizing'], ['width'=>'80%','maxWidth'=>'1080px','alignment'=>'center']);
            bioco_editorial_cta_fill($row, ['module','decoration','spacing'], ['padding'=>['top'=>'0px','bottom'=>'0px','left'=>'0px','right'=>'0px']]);
            bioco_editorial_cta_remove($row, 'bioco-divi-row');
            foreach ($row['innerBlocks'] as &$column) {
                if (($column['blockName'] ?? '') !== 'divi/column') throw new RuntimeException('Expected native column.');
                if (in_array('bioco-divi-content', preg_split('/\s+/', bioco_import_editorial_class($column), -1, PREG_SPLIT_NO_EMPTY), true)) {
                    bioco_editorial_cta_fill($column, ['module','decoration','sizing'], ['width'=>'100%','maxWidth'=>'1160px']);
                    bioco_editorial_cta_remove($column, 'bioco-divi-content');
                }
                foreach ($column['innerBlocks'] as &$leaf) {
                    bioco_editorial_cta_native_leaf($leaf);
                    if (($leaf['blockName'] ?? '') !== 'divi/image' || !in_array('bioco-divi-text-columns-image', preg_split('/\s+/', bioco_import_editorial_class($leaf), -1, PREG_SPLIT_NO_EMPTY), true)) continue;
                    bioco_editorial_cta_fill($leaf, ['module','advanced','sizing'], ['width'=>'100%','maxWidth'=>'100%','aspectRatio'=>['width'=>'4','height'=>'3']]);
                    bioco_editorial_cta_fill($leaf, ['image','decoration','fit'], ['objectFit'=>'cover']);
                    bioco_editorial_cta_fill($leaf, ['image','decoration','border'], ['radius'=>array_fill_keys(['topLeft','topRight','bottomLeft','bottomRight'],'24px')]);
                    // Compare the installed Image margin with the old collapsing
                    // crop-wrapper margin before accepting a saved draft.
                    bioco_editorial_cta_fill($leaf, ['module','advanced','spacing'], ['margin'=>['top'=>'24px']]);
                    bioco_editorial_cta_remove($leaf, 'bioco-divi-text-columns-image');
                    $report['images']++;
                }
                unset($leaf);
            }
            unset($column);
        }
        unset($row, $rows);
        $report[$cta ? 'cta' : 'topics']++;
    }
    unset($section);
}

function bioco_editorial_cta_native_preview(array $blocks): array {
    $report = ['cta'=>0,'topics'=>0,'images'=>0];
    bioco_editorial_cta_native_visit($blocks, $report);
    return [$blocks, $report];
}
