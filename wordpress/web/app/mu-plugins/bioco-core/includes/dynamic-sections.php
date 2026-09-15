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
        'event_signup_form' => 'bioco/event-signup-form',
        'doi_confirm' => 'bioco/doi-confirm',
        'gallery' => 'bioco/gallery',
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

function bioco_dynamic_view_style_handle(string $block_name, string $view_style): string {
    if (str_starts_with($view_style, 'file:')) {
        if (function_exists('generate_block_asset_handle')) {
            return generate_block_asset_handle($block_name, 'viewStyle');
        }

        return str_replace('/', '-', $block_name) . '-view-style';
    }

    return $view_style;
}

function bioco_render_dynamic_component(string $component, array $values, array $options = []): string {
    $components = bioco_dynamic_components();
    if (!isset($components[$component])) {
        throw new InvalidArgumentException('Unknown bioco dynamic component: ' . $component);
    }

    // Render mode: 'inert' = editor/preview context (read-only display, no
    // form scripts/localization/submit paths, DOI fixed preview branch),
    // 'public' = the real frontend render. The mode is always forced by the
    // caller from its own server-side context detection; native modules pass
    // bioco_native_render_mode(), the project preview endpoint forces 'inert'.
    $mode = ($options['mode'] ?? null) === 'inert' ? 'inert' : 'public';

    $block_name = $components[$component];
    $block_slug = substr($block_name, strpos($block_name, '/') + 1);
    if (function_exists('bioco_forms_message_values')) {
        $values = bioco_forms_message_values($block_slug, $values);
    }
    $block_dir = dirname(__DIR__) . '/blocks/' . $block_slug;
    $block_metadata = json_decode((string) file_get_contents($block_dir . '/block.json'), true);
    if ($mode === 'public' && !empty($block_metadata['viewScript'])) {
        // Form/filter view scripts mount submission listeners and captcha
        // loading; they are skipped in every editor/preview context.
        wp_enqueue_script(bioco_forms_view_script_handle($block_name));
    }
    if (!empty($block_metadata['viewStyle']) && is_string($block_metadata['viewStyle'])) {
        wp_enqueue_style(bioco_dynamic_view_style_handle($block_name, $block_metadata['viewStyle']));
    }

    $block = [
        'anchor' => $values['anchor'] ?? '',
        'className' => $values['className'] ?? '',
    ];
    $content = '';
    $is_preview = $mode === 'inert';
    $post_id = get_the_ID();
    $context = [];

    if (!isset($GLOBALS['bioco_dynamic_context_stack'])) {
        $GLOBALS['bioco_dynamic_context_stack'] = [];
    }
    $GLOBALS['bioco_dynamic_context_stack'][] = $values;

    // The inert flag must restore its previous value AND existence: nested
    // dynamic renders (outer inert -> inner public -> outer inert) each save
    // and restore their caller's exact state.
    $inert_key = 'bioco_dynamic_render_inert';
    $inert_had_prev = array_key_exists($inert_key, $GLOBALS);
    $inert_prev = $inert_had_prev ? $GLOBALS[$inert_key] : null;
    $GLOBALS[$inert_key] = $is_preview;

    $buffer_level = ob_get_level();
    ob_start();

    try {
        include $block_dir . '/render.php';
        $rendered = ob_get_clean();
        if ($is_preview) {
            // Preview forms must be structurally non-submitting, not merely
            // handler-guarded: inline onsubmit can be stripped/blocked and
            // cannot stop public form runtime listeners that coexist in an
            // editor canvas. In inert output only, form wrappers become
            // non-form containers that keep the original attributes/classes
            // (the .bioco-form CSS is class-based), plus an explicit preview
            // marker and the inert attribute; submit buttons are demoted to
            // type=button. Public HTML is never touched.
            $rendered = preg_replace(
                '~<form(?=[\s>])([^>]*)>~',
                '<div$1 data-bioco-preview="1" inert>',
                $rendered
            );
            $rendered = str_replace('</form>', '</div>', $rendered);
            $rendered = preg_replace('~type=(["\'])submit\1~', 'type=$1button$1', $rendered);
        }
        return $rendered;
    } catch (Throwable $error) {
        while (ob_get_level() > $buffer_level) {
            ob_end_clean();
        }
        throw $error;
    } finally {
        array_pop($GLOBALS['bioco_dynamic_context_stack']);
        if ($inert_had_prev) {
            $GLOBALS[$inert_key] = $inert_prev;
        } else {
            unset($GLOBALS[$inert_key]);
        }
    }
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
