<?php
/** Move imported timeline geometry into native controls without rereading seeds. */
if (!defined('ABSPATH')) exit;

function bioco_import_timeline_container(array $block, string $name, array $classes): void {
    if (($block['blockName'] ?? '') !== $name) throw new RuntimeException('Unrecognized saved timeline container.');
    bioco_import_editorial_split_safe($block);
    $saved = preg_split('/\s+/', bioco_import_editorial_class($block), -1, PREG_SPLIT_NO_EMPTY);
    if (array_diff($saved, $classes)) throw new RuntimeException('Timeline container has custom CSS classes; convert it in Divi.');
}

/** Retain saved values, responsive typography, links, anchors and module settings. */
function bioco_import_timeline_module(array $saved, array $native): array {
    $native['attrs'] = array_replace_recursive($native['attrs'], $saved['attrs']);
    $native['innerBlocks'] = $saved['innerBlocks'];
    $native['innerContent'] = $saved['innerContent'];
    $native['innerHTML'] = $saved['innerHTML'];
    $classes = preg_split('/\s+/', bioco_import_editorial_class($saved), -1, PREG_SPLIT_NO_EMPTY);
    $classes = array_map(static fn(string $class): string => str_replace('bioco-divi-timeline-', 'bioco-timeline-', $class), $classes);
    bioco_import_editorial_set_class($native, implode(' ', $classes));
    return $native;
}

function bioco_import_timeline_merge_container(array $saved, array $native): array {
    // Validation has excluded authored geometry. Replace only the imported
    // layout defaults; keep IDs and Divi's normalized attribute storage.
    $class = bioco_import_editorial_class($native);
    $attrs = array_replace_recursive($saved['attrs'], $native['attrs']);
    $attrs['module']['decoration'] = array_replace_recursive(
        $saved['attrs']['module']['decoration'] ?? [], $native['attrs']['module']['decoration'] ?? []
    );
    $native['attrs'] = $attrs;
    bioco_import_editorial_set_class($native, $class);
    $id = bioco_import_editorial_attribute($saved, 'id');
    if ($id !== '') $native['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = $id;
    return $native;
}

function bioco_import_timeline_section(array $section, int &$count): array {
    if (!bioco_import_editorial_has_class($section, 'bioco-divi-timeline')) return $section;
    $classes = bioco_import_editorial_class($section);
    preg_match('/\bbioco-divi-width-(md|lg|xl)\b/', $classes, $width);
    preg_match('/\bbioco-divi-align-(left|center)\b/', $classes, $align);
    bioco_import_timeline_container($section, 'divi/section', [
        'bioco-divi-section', 'bioco-divi-timeline', 'bioco-divi-width-md', 'bioco-divi-width-lg',
        'bioco-divi-width-xl', 'bioco-divi-align-left', 'bioco-divi-align-center',
    ]);
    $items = []; $header = null; $rows = []; $converted = []; $textWidth = 'normal'; $headerAlign = $align[1] ?? 'left';
    foreach ($section['innerBlocks'] as $row) {
        if (bioco_import_editorial_has_class($row, 'bioco-divi-timeline-header')) {
            if ($header !== null || $items) throw new RuntimeException('Timeline header is duplicated or reordered; convert it in Divi.');
            bioco_import_timeline_container($row, 'divi/row', ['bioco-divi-row', 'bioco-divi-timeline-header']);
            if (count($row['innerBlocks']) !== 1) throw new RuntimeException('Timeline header has extra columns.');
            $column = $row['innerBlocks'][0];
            bioco_import_timeline_container($column, 'divi/column', ['bioco-divi-content',
                'bioco-divi-text-narrow', 'bioco-divi-text-normal', 'bioco-divi-text-wide',
                'bioco-divi-align-left', 'bioco-divi-align-center']);
            preg_match('/\bbioco-divi-text-(narrow|normal|wide)\b/', bioco_import_editorial_class($column), $textMatch);
            preg_match('/\bbioco-divi-align-(left|center)\b/', bioco_import_editorial_class($column), $alignMatch);
            $textWidth = $textMatch[1] ?? 'normal';
            $headerAlign = $alignMatch[1] ?? $headerAlign;
            foreach ($column['innerBlocks'] as $module) {
                if (!in_array($module['blockName'] ?? '', ['divi/text', 'divi/heading'], true)) throw new RuntimeException('Unsupported timeline header module.');
            }
            $header = $row;
            continue;
        }
        bioco_import_timeline_container($row, 'divi/row', ['bioco-divi-row', 'bioco-divi-timeline-item-row', 'bioco-divi-timeline-item--normal', 'bioco-divi-timeline-item--highlight']);
        if (!bioco_import_editorial_has_class($row, 'bioco-divi-timeline-item-row') || count($row['innerBlocks']) !== 2) throw new RuntimeException('Unrecognized timeline row.');
        [$badge, $body] = $row['innerBlocks'];
        bioco_import_timeline_container($badge, 'divi/column', ['bioco-divi-timeline-badge-col']);
        bioco_import_timeline_container($body, 'divi/column', ['bioco-divi-timeline-item-content']);
        if (count($badge['innerBlocks']) !== 1 || ($badge['innerBlocks'][0]['blockName'] ?? '') !== 'divi/text'
            || !bioco_import_editorial_has_class($badge['innerBlocks'][0], 'bioco-divi-timeline-badge')) throw new RuntimeException('Unrecognized timeline badge.');
        foreach ($body['innerBlocks'] as $module) {
            if (!in_array($module['blockName'] ?? '', ['divi/text', 'divi/heading'], true)) throw new RuntimeException('Unsupported timeline body module.');
        }
        $items[] = ['year_eyebrow' => '_', 'title' => '_', 'text' => '_',
            'emphasis' => bioco_import_editorial_has_class($row, 'bioco-divi-timeline-item--highlight') ? 'highlight' : 'normal'];
        $rows[] = $row;
    }
    if (!$rows) throw new RuntimeException('Imported timeline has no event rows.');
    $values = ['container_width' => $width[1] ?? 'lg', 'align' => $align[1] ?? 'left', 'text_width' => $textWidth, 'items' => $items];
    if ($header !== null) $values['title'] = '_';
    $native = Bioco_Import_Divi_Composer::section(['block' => 'timeline', 'values' => $values]);
    $nativeRows = $native['innerBlocks'];
    if ($header !== null) {
        $newHeader = array_shift($nativeRows);
        $newColumn = $newHeader['innerBlocks'][0];
        $newColumn['attrs']['module']['decoration']['sizing']['desktop']['value']['alignment'] = $headerAlign;
        // Header modules already belong to Divi. Preserve their exact saved data.
        $newColumn['innerBlocks'] = $header['innerBlocks'][0]['innerBlocks'];
        $newColumn['innerContent'] = array_fill(0, count($newColumn['innerBlocks']), null);
        $newHeader['innerBlocks'] = [bioco_import_timeline_merge_container($header['innerBlocks'][0], $newColumn)];
        $converted[] = bioco_import_timeline_merge_container($header, $newHeader);
    }
    foreach ($rows as $index => $row) {
        $newRow = $nativeRows[$index];
        foreach ($row['innerBlocks'] as $columnIndex => $column) {
            $newColumn = $newRow['innerBlocks'][$columnIndex];
            $newModules = [];
            foreach ($column['innerBlocks'] as $module) {
                $prototype = $columnIndex === 0 ? $newColumn['innerBlocks'][0]
                    : $newColumn['innerBlocks'][($module['blockName'] ?? '') === 'divi/heading' ? 0 : 1];
                $newModules[] = bioco_import_timeline_module($module, $prototype);
            }
            $newColumn['innerBlocks'] = $newModules;
            $newColumn['innerContent'] = array_fill(0, count($newModules), null);
            $newRow['innerBlocks'][$columnIndex] = bioco_import_timeline_merge_container($column, $newColumn);
        }
        $converted[] = bioco_import_timeline_merge_container($row, $newRow);
    }
    $native['innerBlocks'] = $converted;
    $native['innerContent'] = array_fill(0, count($converted), null);
    $native = bioco_import_timeline_merge_container($section, $native);
    $count++;
    return $native;
}
