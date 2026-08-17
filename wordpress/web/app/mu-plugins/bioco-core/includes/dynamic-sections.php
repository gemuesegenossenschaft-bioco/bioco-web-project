<?php
/**
 * Runtime seam for dynamic components embedded in native Divi text blocks.
 */

if (!defined('ABSPATH')) exit;

function bioco_dynamic_components(): array {
    return [
        'contact_form' => 'bioco/contact-form',
        'membership_form' => 'bioco/membership-form',
        'subscribe_form' => 'bioco/subscribe-form',
        'visit_day_form' => 'bioco/visit-day-form',
        'waiting_list_form' => 'bioco/waiting-list-form',
        'pricing_calculator' => 'bioco/pricing-calculator',
        'events_feed' => 'bioco/events-feed',
        'schnuppertage' => 'bioco/schnuppertage',
        'group_cards' => 'bioco/group-cards',
        'saisonkalender' => 'bioco/saisonkalender',
        'depot_map' => 'bioco/depot-map',
        'geisshof_map' => 'bioco/geisshof-map',
    ];
}

function bioco_dynamic_marker_html(string $component, array $values): string {
    if (!isset(bioco_dynamic_components()[$component])) {
        throw new InvalidArgumentException('Unknown bioco dynamic component: ' . $component);
    }

    return '<div class="bioco-dynamic" data-bioco-component="' . $component
        . '" data-bioco-props="' . base64_encode(json_encode($values)) . '"></div>';
}

function bioco_dynamic_expand_markers(string $html): string {
    if (strpos($html, 'bioco-dynamic') === false) {
        return $html;
    }

    return preg_replace_callback(
        '~<div class="bioco-dynamic" data-bioco-component="([a-z0-9_]+)" data-bioco-props="([A-Za-z0-9+/=]*)"></div>~',
        function (array $matches): string {
            if (!isset(bioco_dynamic_components()[$matches[1]])) {
                return $matches[0];
            }

            $json = base64_decode($matches[2], true);
            $values = $json === false ? null : json_decode($json, true);
            if (!is_array($values)) {
                return $matches[0];
            }

            return bioco_render_dynamic_component($matches[1], $values);
        },
        $html
    );
}

function bioco_render_dynamic_component(string $component, array $values): string {
    $components = bioco_dynamic_components();
    if (!isset($components[$component])) {
        throw new InvalidArgumentException('Unknown bioco dynamic component: ' . $component);
    }

    $block_name = $components[$component];
    $block_slug = substr($block_name, strpos($block_name, '/') + 1);
    $block_dir = dirname(__DIR__) . '/blocks/' . $block_slug;
    $block_metadata = json_decode((string) file_get_contents($block_dir . '/block.json'), true);
    if (!$values) {
        $values = bioco_dynamic_default_values($block_slug);
    }

    if (!empty($block_metadata['viewScript'])) {
        wp_enqueue_script(bioco_forms_view_script_handle($block_name));
    }

    $block = [
        'anchor' => $values['anchor'] ?? '',
        'className' => $values['className'] ?? '',
    ];
    $content = '';
    $is_preview = false;
    $post_id = get_the_ID();
    $context = [];

    if (!isset($GLOBALS['bioco_dynamic_context_stack'])) {
        $GLOBALS['bioco_dynamic_context_stack'] = [];
    }
    $GLOBALS['bioco_dynamic_context_stack'][] = $values;
    $buffer_level = ob_get_level();
    ob_start();

    try {
        include $block_dir . '/render.php';
        return ob_get_clean();
    } catch (Throwable $error) {
        while (ob_get_level() > $buffer_level) {
            ob_end_clean();
        }
        throw $error;
    } finally {
        array_pop($GLOBALS['bioco_dynamic_context_stack']);
    }
}

function bioco_dynamic_default_values(string $block_slug): array {
    static $cache = [];
    if (isset($cache[$block_slug])) return $cache[$block_slug];

    $group_path = dirname(__DIR__) . '/acf-json/group_bioco_block_'
        . str_replace('-', '_', $block_slug) . '.json';
    $group = is_readable($group_path)
        ? json_decode((string) file_get_contents($group_path), true)
        : null;
    $defaults = [];
    foreach (is_array($group['fields'] ?? null) ? $group['fields'] : [] as $field) {
        $name = (string) ($field['name'] ?? '');
        if ($name !== '' && array_key_exists('default_value', $field)) {
            $defaults[$name] = $field['default_value'];
        }
    }
    return $cache[$block_slug] = $defaults;
}

function bioco_field($name, $default = null) {
    $stack = $GLOBALS['bioco_dynamic_context_stack'] ?? [];
    if ($stack) {
        $values = $stack[array_key_last($stack)];
        return array_key_exists($name, $values) ? $values[$name] : $default;
    }

    return function_exists('get_field') ? get_field($name) : $default;
}

if (function_exists('add_filter')) {
    add_filter('render_block', 'bioco_dynamic_expand_markers', 10, 2);
    add_filter('the_content', 'bioco_dynamic_expand_markers', 99, 1);
}
