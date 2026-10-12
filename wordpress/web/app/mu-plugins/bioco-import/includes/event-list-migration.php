<?php
/** Separate saved-content conversion. Callers preview/export and use the existing CAS writer. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/editorial-layouts.php';

function bioco_import_event_list_saved_values(array $module, string $component): array {
    if (!empty($module['innerBlocks']) || trim($module['innerHTML'] ?? '') !== '') throw new RuntimeException('Event module contains authored child markup.');
    $fields = bioco_native_field_map($component);
    $attrs = $module['attrs'];
    if (array_diff(array_keys($attrs), array_merge(array_keys($fields), ['module', 'builderVersion']))) throw new RuntimeException('Unsupported saved event module attributes.');
    foreach (array_keys($fields) as $field) {
        if (!isset($attrs[$field])) continue;
        $variants = $attrs[$field]['innerContent'] ?? [];
        $desktop = $variants['desktop']['value'] ?? null;
        foreach ($variants as $viewport => $variant) {
            if ($viewport !== 'desktop' && ($variant['value'] ?? null) !== $desktop) throw new RuntimeException('Distinct responsive event content requires native manual conversion.');
        }
    }
    $container = $module;
    $container['attrs'] = array_intersect_key($attrs, ['module' => true, 'builderVersion' => true]);
    bioco_import_editorial_split_safe($container);
    $classes = preg_split('/\s+/', bioco_import_editorial_class($module), -1, PREG_SPLIT_NO_EMPTY);
    if (array_diff($classes, ['bioco-native-component'])) throw new RuntimeException('Custom event module classes require native manual conversion.');
    $values = bioco_native_extract_values($component, $attrs);
    if (is_wp_error($values)) throw new RuntimeException($values->get_error_message());
    $values = bioco_native_validate_values($component, $values);
    if (is_wp_error($values)) throw new RuntimeException($values->get_error_message());
    return $values;
}

function bioco_import_event_list_section(array $section, int &$count): array {
    $rows = [];
    foreach ($section['innerBlocks'] ?? [] as $row) {
        $targets = [];
        foreach ($row['innerBlocks'] ?? [] as $column) foreach ($column['innerBlocks'] ?? [] as $module) {
            if (in_array($module['blockName'] ?? '', ['bioco-divi/events-feed', 'bioco-divi/schnuppertage'], true)) $targets[] = $module;
        }
        if (!$targets) { $rows[] = $row; continue; }
        if (count($targets) !== 1 || count($row['innerBlocks']) !== 1
            || count($row['innerBlocks'][0]['innerBlocks']) !== 1) throw new RuntimeException('Event module shares its row; convert it in Divi.');
        $column = $row['innerBlocks'][0];
        foreach ([$section, $row, $column] as $container) {
            if (trim($container['innerHTML'] ?? '') !== '') throw new RuntimeException('Event container contains authored child markup.');
        }
        foreach ([$row, $column] as $container) bioco_import_editorial_split_safe($container);
        if (($row['blockName'] ?? '') !== 'divi/row' || ($column['blockName'] ?? '') !== 'divi/column') throw new RuntimeException('Unsupported event containers.');
        foreach ([[$row, ['bioco-divi-row']], [$column, ['bioco-divi-content']]] as [$container, $allowed]) {
            if (array_diff(preg_split('/\s+/', bioco_import_editorial_class($container), -1, PREG_SPLIT_NO_EMPTY), $allowed)) throw new RuntimeException('Custom event container classes require native manual conversion.');
        }
        $module = $targets[0];
        $component = $module['blockName'] === 'bioco-divi/events-feed' ? 'events_feed' : 'schnuppertage';
        $values = bioco_import_event_list_saved_values($module, $component);
        $replacement = bioco_import_event_list_blocks($component, $values)[0]['innerBlocks'];
        // Preserve each former wrapper identity at the replacement's first path.
        foreach ([[$row, &$replacement[0]], [$column, &$replacement[0]['innerBlocks'][0]],
            [$module, &$replacement[0]['innerBlocks'][0]['innerBlocks'][0]]] as &$pair) {
            $id = bioco_import_editorial_attribute($pair[0], 'id');
            if ($id !== '') $pair[1]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = $id;
        }
        unset($pair);
        array_push($rows, ...$replacement);
        $count++;
    }
    $section['innerBlocks'] = $rows;
    $section['innerContent'] = array_fill(0, count($rows), null);
    return $section;
}

function bioco_import_event_list_tree(array $blocks, int &$count): array {
    foreach ($blocks as &$block) {
        if (($block['blockName'] ?? '') === 'divi/section') $block = bioco_import_event_list_section($block, $count);
        if (!empty($block['innerBlocks'])) $block['innerBlocks'] = bioco_import_event_list_tree($block['innerBlocks'], $count);
    }
    return $blocks;
}

function bioco_import_event_lists_content(string $content): array {
    $count = 0;
    $blocks = bioco_import_event_list_tree(parse_blocks($content), $count);
    return [$count ? serialize_blocks($blocks) : $content, $count];
}
