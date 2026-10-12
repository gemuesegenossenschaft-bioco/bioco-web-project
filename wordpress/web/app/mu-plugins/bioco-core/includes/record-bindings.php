<?php
/** Group and seasonal data for ordinary native Divi modules. */
if (!defined('ABSPATH')) exit;

function bioco_record_binding_fields(array $options): array {
    static $definition;
    $definition ??= json_decode(file_get_contents(__DIR__ . '/record-bindings.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($definition as $kind => $entry) foreach ($entry['fields'] as $field => [$label, $type]) {
        foreach ($entry['groups'] as $prefix => $group) {
            $name = $prefix . 'bioco_' . $kind . '_' . $field;
            $options[$name] = ['id' => $name, 'label' => $group . ': ' . $label,
                'type' => $type, 'custom' => false, 'group' => $group];
        }
    }
    return $options;
}

function bioco_group_contact_destination(int $id): string {
    $url = (string) get_post_meta($id, 'group_contact_url', true);
    return preg_match('~^(?:https?://|mailto:|tel:|/(?!/))~i', $url) ? esc_url_raw($url) : '';
}

function bioco_record_binding_id(array $context, string $type, bool $loop): int {
    $loop_id = (int) ($context['loop_id'] ?? 0);
    $id = $loop_id ?: (int) ($context['post_id'] ?? 0);
    if ($loop && get_post_type($id) !== $type) return 0;
    if (!$loop && get_post_type($id) !== $type) {
        $queried = (int) get_queried_object_id();
        if (get_post_type($queried) === $type) $id = $queried;
        elseif ($type === 'bioco_group') {
            // A draft layout preview may resolve only its mutually linked draft QA group.
            $preview = (int) get_post_meta($id, '_bioco_editor_verification_group', true);
            $run = get_post_meta($id, '_bioco_editor_verification', true);
            if ($preview && is_string($run) && preg_match('/^[a-z0-9][a-z0-9-]{7,79}$/D', $run)
                && $run === get_post_meta($preview, '_bioco_editor_verification', true)
                && (int) get_post_meta($preview, '_bioco_editor_verification_body', true) === $id
                && in_array(get_post_type($id), ['et_pb_layout', 'et_body_layout'], true)
                && in_array(get_post_status($id), ['draft', 'auto-draft'], true)
                && in_array(get_post_status($preview), ['draft', 'auto-draft'], true)
                && current_user_can('edit_post', $id) && current_user_can('edit_post', $preview)) $id = $preview;
        }
    }
    if (get_post_type($id) !== $type || (get_post_status($id) !== 'publish' && !current_user_can('read_post', $id))) return 0;
    return $id;
}

function bioco_record_binding_value($value, array $context) {
    $name = $context['name'] ?? '';
    if (!is_string($name) || !preg_match('/^(loop_)?bioco_(group|vegetable|depot)_(.+)$/D', $name, $match)) return $value;
    [$all, $loop, $kind, $field] = $match;
    $allowed = $kind === 'group' ? ['title', 'description', 'image', 'image_alt', 'contact', 'contact_url', 'url']
        : ($kind === 'depot' ? ['name', 'address', 'website', 'website_label', 'route_url']
        : array_merge(['name'], array_map(static fn($n) => 'month_' . $n, range(1, 12))));
    if (!in_array($field, $allowed, true)) return $value;
    $id = bioco_record_binding_id($context, 'bioco_' . $kind, $loop !== '');
    if (!$id) return '';
    if ($kind === 'depot') {
        if (post_password_required($id)) return '';
        if (metadata_exists('post', $id, 'depot_public') && !get_post_meta($id, 'depot_public', true)) return '';
        if ($field === 'name') return esc_html(get_the_title($id));
        if ($field === 'address') return esc_html((string) get_post_meta($id, 'depot_public_address', true));
        if ($field === 'route_url') {
            $lat = get_post_meta($id, 'depot_lat', true); $lng = get_post_meta($id, 'depot_lng', true);
            if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) return '';
            return esc_url_raw('https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($lat . ',' . $lng));
        }
        $website = (string) get_post_meta($id, 'depot_website', true);
        if (!filter_var($website, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($website, PHP_URL_SCHEME)), ['http', 'https'], true)) return '';
        return $field === 'website' ? esc_url_raw($website)
            : esc_html((string) get_post_meta($id, 'depot_website_label', true) ?: $website);
    }
    if ($field === 'title' || $field === 'name') return esc_html(get_the_title($id));
    if ($kind === 'group' && $field === 'url') return esc_url_raw(get_permalink($id));
    if (post_password_required($id)) return '';
    if ($kind === 'vegetable') {
        $months = array_map('intval', (array) get_post_meta($id, 'vegetable_months', true));
        return in_array((int) substr($field, 6), $months, true) ? '●' : '';
    }
    if ($field === 'contact_url') return bioco_group_contact_destination($id);
    if ($field === 'contact') return esc_html((string) get_field('group_contact', $id));
    if ($field === 'description') return wp_kses_post((string) get_field('group_text', $id));
    $image = get_field('group_image', $id);
    return $field === 'image' ? esc_url_raw(is_array($image) ? ($image['url'] ?? '') : '')
        : esc_attr(is_array($image) && !empty($image['url']) ? (($image['alt'] ?? '') !== '' ? $image['alt'] : get_the_title($id)) : '');
}

add_filter('divi_module_dynamic_content_options', 'bioco_record_binding_fields', 20);
add_filter('divi_module_dynamic_content_resolved_value', 'bioco_record_binding_value', 20, 2);
