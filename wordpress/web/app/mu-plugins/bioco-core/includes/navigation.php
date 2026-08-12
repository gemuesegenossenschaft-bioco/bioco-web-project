<?php
/**
 * Primary navigation contract shared by themes and the importer.
 */

if (!defined('ABSPATH')) exit;

function bioco_primary_navigation_items() {
    $path = dirname(__DIR__) . '/content/navigation.json';
    $seed = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
    return is_array($seed['items'] ?? null) ? $seed['items'] : [];
}

function bioco_primary_navigation_block() {
    $links = [];
    foreach (bioco_primary_navigation_items() as $item) {
        $links[] = [
            'blockName' => 'core/navigation-link',
            'attrs' => [
                'label' => $item['label'],
                'url' => home_url($item['url']),
                'kind' => 'custom',
                'isTopLevelLink' => true,
            ],
            'innerBlocks' => [],
            'innerHTML' => '',
            'innerContent' => [],
        ];
    }

    return [
        'blockName' => 'core/navigation',
        'attrs' => ['layout' => ['type' => 'flex', 'justifyContent' => 'right']],
        'innerBlocks' => $links,
        'innerHTML' => '',
        'innerContent' => array_fill(0, count($links), null),
    ];
}

function bioco_primary_navigation_markup() {
    return serialize_block(bioco_primary_navigation_block());
}

function bioco_render_primary_navigation() {
    return do_blocks(bioco_primary_navigation_markup());
}
