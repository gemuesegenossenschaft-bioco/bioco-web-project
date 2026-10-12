<?php
/** Replace one saved leaf at its exact position. Callers export and use the CAS writer. */
if (!defined('ABSPATH')) exit;

function bioco_import_event_list_saved_values(array $module, string $component): array {
    if (!empty($module['innerBlocks']) || trim($module['innerHTML'] ?? '') !== '') throw new RuntimeException('Event module contains authored child markup.');
    $fields = bioco_native_field_map($component);
    $attrs = $module['attrs'];
    if (array_diff(array_keys($attrs), array_merge(array_keys($fields), ['module', 'builderVersion']))) throw new RuntimeException('Unsupported saved event module attributes.');
    foreach (array_keys($fields) as $field) {
        if (!isset($attrs[$field]) || !is_array($attrs[$field])) continue;
        $variants = $attrs[$field]['innerContent'] ?? [];
        $desktop = $variants['desktop']['value'] ?? null;
        foreach ($variants as $viewport => $variant) {
            if ($viewport !== 'desktop' && ($variant['value'] ?? null) !== $desktop) throw new RuntimeException('Distinct responsive event content requires native manual conversion.');
        }
    }
    // Native rows support these common module controls. Preserve their values
    // intact, rather than splitting or flattening authored container geometry.
    $metadata = json_decode(file_get_contents(dirname(__DIR__, 2) . '/bioco-core/native-modules/'
        . str_replace('_', '-', $component) . '/module.json'), true, 512, JSON_THROW_ON_ERROR);
    $common = $attrs['module'] ?? [];
    if (!is_array($common) || array_diff(array_keys($common), ['meta', 'advanced', 'decoration'])) throw new RuntimeException('Unsupported saved event module controls.');
    foreach ($common as $group => $settings) {
        if (!is_array($settings) || array_diff(array_keys($settings), array_keys($metadata['attributes']['module']['settings'][$group] ?? []))) throw new RuntimeException('Unsupported saved event module controls.');
    }
    $values = bioco_native_extract_values($component, $attrs);
    if (is_wp_error($values)) throw new RuntimeException($values->get_error_message());
    $values = bioco_native_validate_values($component, $values);
    if (is_wp_error($values)) throw new RuntimeException($values->get_error_message());
    return $values;
}

function bioco_import_event_list_tree(array $blocks, int &$count): array {
    foreach ($blocks as &$block) {
        if (in_array($block['blockName'] ?? '', ['bioco-divi/events-feed', 'bioco-divi/schnuppertage'], true)) {
            $saved = $block;
            $component = $saved['blockName'] === 'bioco-divi/events-feed' ? 'events_feed' : 'schnuppertage';
            $values = bioco_import_event_list_saved_values($saved, $component);
            $rows = bioco_import_event_list_blocks($component, $values)[0]['innerBlocks'];
            // This static wrapper retains the former module ID even when the
            // first generated child is a repeated native loop row.
            $block = bioco_import_event_list_row($rows);
            $block['attrs']['module'] = array_replace_recursive($block['attrs']['module'], $saved['attrs']['module'] ?? []);
            $html = $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value'] ?? [];
            if (is_string($html)) $html = json_decode($html, true) ?: [];
            if (empty($html['id'])) $html['id'] = str_replace('_', '-', $component);
            $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value'] = $html;
            $count++;
        }
        if (!empty($block['innerBlocks'])) $block['innerBlocks'] = bioco_import_event_list_tree($block['innerBlocks'], $count);
        // Parent innerContent strings/markers and every sibling stay untouched.
    }
    unset($block);
    return $blocks;
}

function bioco_import_event_lists_content(string $content): array {
    $count = 0;
    $blocks = bioco_import_event_list_tree(parse_blocks($content), $count);
    return [$count ? serialize_blocks($blocks) : $content, $count];
}
