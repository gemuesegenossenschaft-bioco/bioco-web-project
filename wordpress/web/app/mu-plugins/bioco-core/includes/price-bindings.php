<?php
/** Price record values for ordinary Divi headings, text and native loops. */
if (!defined('ABSPATH')) exit;

function bioco_price_binding_fields(array $options): array {
    static $definition;
    $definition ??= json_decode(file_get_contents(__DIR__ . '/price-bindings.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($definition['fields'] as $field => [$label, $type]) {
        foreach ($definition['groups'] as $prefix => $group) {
            $name = $prefix . 'bioco_price_' . $field;
            $options[$name] = ['id' => $name, 'label' => $group . ': ' . $label,
                'type' => $type, 'custom' => false, 'group' => $group];
        }
    }
    $name = 'bioco_price_share_price';
    $options[$name] = ['id' => $name, 'label' => $definition['share_price'][0],
        'type' => 'text', 'custom' => false, 'group' => $definition['share_price'][1]];
    return $options;
}

function bioco_price_binding_value($value, array $context) {
    $name = $context['name'] ?? '';
    if ($name === 'bioco_price_share_price') {
        $catalog = function_exists('bioco_price_catalog') ? bioco_price_catalog() : null;
        return $catalog !== null && empty($catalog['error']) ? esc_html(number_format_i18n($catalog['share_price'])) : '';
    }
    if (!is_string($name) || !preg_match('/^(loop_)?bioco_price_(name|table_label|key|select_url|price|price_table|shares|share_cost|share_cost_table|shares_label|work|persons|persons_range|persons_accessible|persons_icon)$/D', $name, $match)) return $value;
    $loop = (int) ($context['loop_id'] ?? 0);
    $id = $loop ?: (int) ($context['post_id'] ?? 0);
    // A missing loop record must not use the enclosing editorial page.
    if (!$loop && empty($match[1]) && get_post_type($id) !== 'bioco_price') $id = (int) get_queried_object_id();
    if (get_post_type($id) !== 'bioco_price'
        || (get_post_status($id) !== 'publish' && !current_user_can('read_post', $id))) return '';
    $catalog = function_exists('bioco_price_catalog') ? bioco_price_catalog() : null;
    if ($catalog === null || !empty($catalog['error'])
        || !in_array($id, array_column($catalog['tiers'], 'ID'), true)) return '';
    $field = $match[2];
    if ($field === 'name') return esc_html(get_the_title($id));
    if ($field === 'table_label') return esc_html((string) get_post_meta($id, 'price_table_label', true) ?: get_the_title($id));
    if ($field === 'key') return esc_html((string) get_post_meta($id, 'price_key', true));
    if ($field === 'select_url') {
        $key = (string) get_post_meta($id, 'price_key', true);
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $key) && $key !== 'kein' ? '#bioco-price-' . $key : '';
    }
    if (!function_exists('bioco_price_integer')) return '';
    if ($field === 'persons_range') {
        $range = trim((string) get_post_meta($id, 'price_persons_range', true));
        $persons = bioco_price_integer(get_post_meta($id, 'price_persons', true));
        if ($range === '' && preg_match('/\(([^()]+ Personen?)\)$/u', get_the_title($id), $matched)) $range = $matched[1];
        return esc_html($range !== '' ? $range : ($persons === 1 ? '1 Person' : (string) $persons . ' Personen'));
    }
    if ($field === 'persons_accessible') {
        $persons = bioco_price_integer(get_post_meta($id, 'price_persons', true));
        return $persons === null ? '' : esc_attr($persons === 1 ? '1 Person' : (string) $persons . ' Personen');
    }
    if ($field === 'persons_icon') {
        $persons = bioco_price_integer(get_post_meta($id, 'price_persons', true));
        return $persons !== null && $persons >= 1 && $persons <= 12
            ? esc_url_raw(plugins_url('assets/price-person-icons/persons-' . $persons . '.svg', dirname(__DIR__) . '/bioco-core.php')) : '';
    }
    if ($field === 'shares_label') {
        $shares = bioco_price_integer(get_post_meta($id, 'price_shares', true));
        return $shares === null ? '' : esc_html(number_format_i18n($shares) . ($shares === 1 ? ' Anteilsschein' : ' Anteilsscheine'));
    }
    if ($field === 'share_cost' || $field === 'share_cost_table') {
        $shares = bioco_price_integer(get_post_meta($id, 'price_shares', true));
        if ($shares !== null && !empty($catalog['share_price']) && $shares > intdiv(9007199254740991, $catalog['share_price'])) return '';
        return $shares !== null && $catalog !== null && empty($catalog['error'])
            ? esc_html($field === 'share_cost_table' ? number_format($shares * $catalog['share_price'], 0, '.', "'")
                : number_format_i18n($shares * $catalog['share_price'])) : '';
    }
    $number = bioco_price_integer(get_post_meta($id, $field === 'price_table' ? 'price_price' : 'price_' . $field, true));
    return $number === null ? '' : esc_html($field === 'price_table' ? number_format($number, 0, '.', "'") : number_format_i18n($number));
}

add_filter('divi_module_dynamic_content_options', 'bioco_price_binding_fields', 20);
add_filter('divi_module_dynamic_content_resolved_value', 'bioco_price_binding_value', 20, 2);
