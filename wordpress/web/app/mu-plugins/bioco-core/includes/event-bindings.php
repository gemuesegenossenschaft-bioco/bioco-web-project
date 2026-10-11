<?php
/** Event values for ordinary Divi modules. Presentation remains in saved layouts. */
if (!defined('ABSPATH')) exit;

function bioco_event_binding_fields(array $options): array {
    static $definition;
    $definition ??= json_decode(file_get_contents(__DIR__ . '/event-bindings.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($definition['fields'] as $field => [$label, $type]) {
        foreach ($definition['groups'] as $prefix => $group) {
            $name = $prefix . 'bioco_event_' . $field;
            $options[$name] = ['id' => $name, 'label' => $group . ': ' . $label,
                'type' => $type, 'custom' => false, 'group' => $group];
        }
    }
    return $options;
}

/** Resolve only a readable event, never a layout's own content as its description. */
function bioco_event_binding_record(array $context): int {
    $loop = (int) ($context['loop_id'] ?? 0);
    $id = $loop ?: (int) ($context['post_id'] ?? 0);
    if (!$loop && get_post_type($id) !== 'event') {
        $queried = (int) get_queried_object_id();
        if (get_post_type($queried) === 'event') $id = $queried;
        else {
            // An unassigned draft template can preview only its matching QA event.
            $preview = (int) get_post_meta($id, '_bioco_editor_verification_event', true);
            $run = get_post_meta($id, '_bioco_editor_verification', true);
            if ($preview && is_string($run) && preg_match('/^[a-z0-9][a-z0-9-]{7,79}$/D', $run)
                && $run === get_post_meta($preview, '_bioco_editor_verification', true)
                && (int) get_post_meta($preview, '_bioco_editor_verification_body', true) === $id
                && get_post_type($id) === 'et_body_layout'
                && in_array(get_post_status($id), ['draft', 'auto-draft'], true)
                && in_array(get_post_status($preview), ['draft', 'auto-draft'], true)
                && current_user_can('edit_post', $id) && current_user_can('edit_post', $preview)) $id = $preview;
        }
    }
    if (get_post_type($id) !== 'event') return 0;
    if (get_post_status($id) !== 'publish' && !current_user_can('read_post', $id)) return 0;
    return $id;
}

function bioco_event_binding_value($value, array $context) {
    $name = $context['name'] ?? '';
    if (!is_string($name) || !preg_match('/^(loop_)?bioco_event_(title|date|time|date_time|image|image_alt|description|summary|url)$/D', $name, $match)) return $value;
    // Frontend loop expansion supplies loop_id; editor requests may instead
    // supply the loop record as post_id. Never substitute the enclosing page.
    if (!empty($match[1]) && empty($context['loop_id'])
        && get_post_type((int) ($context['post_id'] ?? 0)) !== 'event') return '';
    $id = bioco_event_binding_record($context);
    if (!$id) return '';
    $field = $match[2];
    // Password protection must apply to record body and summary alike.
    if (in_array($field, ['description', 'summary'], true) && post_password_required($id)) return '';
    if ($field === 'title') return esc_html(get_the_title($id));
    if ($field === 'url') return esc_url_raw(get_permalink($id));
    if ($field === 'image' || $field === 'image_alt') {
        $image = bioco_event_card_image($id);
        return $field === 'image' ? esc_url_raw($image['url'] ?? '') : esc_attr($image['alt'] ?? '');
    }
    if (in_array($field, ['date', 'time', 'date_time'], true)) {
        $parts = bioco_event_date_parts($id);
        $date = $parts['dateLabel'];
        $time = $parts['timeLabel'];
        return esc_html($field === 'date' ? $date : ($field === 'time' ? $time : trim($date . ' ' . $time)));
    }
    if ($field === 'summary') return esc_html(wp_strip_all_tags((string) get_field('event_summary', $id)));
    $record = get_post($id);
    if (trim((string) $record->post_content) === '') return wp_kses_post((string) get_field('event_summary', $id));
    // A record may itself contain a binding. Stop that cycle and restore globals
    // even when a content filter throws; other cards must keep their own context.
    static $rendering = [];
    if (isset($rendering[$id])) return '';
    $rendering[$id] = true;
    $had_post = array_key_exists('post', $GLOBALS);
    $previous = $GLOBALS['post'] ?? null;
    try {
        $GLOBALS['post'] = $record;
        return wp_kses_post((string) apply_filters('the_content', $record->post_content));
    } finally {
        if ($had_post) $GLOBALS['post'] = $previous;
        else unset($GLOBALS['post']);
        unset($rendering[$id]);
    }
}

add_filter('divi_module_dynamic_content_options', 'bioco_event_binding_fields', 20);
add_filter('divi_module_dynamic_content_resolved_value', 'bioco_event_binding_value', 20, 2);
