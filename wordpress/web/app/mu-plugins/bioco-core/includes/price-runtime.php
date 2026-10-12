<?php
/** Arithmetic and record selection for saved native Divi price layouts. */
if (!defined('ABSPATH')) exit;

function bioco_price_native_has_class(array $attrs, string $class): bool {
    $classes = bioco_native_extract_wrapper($attrs)['className'];
    return in_array($class, preg_split('/\s+/', $classes, -1, PREG_SPLIT_NO_EMPTY), true);
}

/** Divi lists show_ui private records only when explicitly allowlisted. */
function bioco_price_native_types(array $types): array {
    return array_values(array_unique(array_merge($types, ['bioco_price'])));
}

/** The loop reads exactly the validated published catalog, including an empty one. */
function bioco_price_native_query(array $data, array $attrs): array {
    if (!bioco_price_native_has_class($attrs, 'bioco-price-loop')) return $data;
    $catalog = bioco_price_catalog();
    $ids = array_column($catalog['tiers'] ?? [], 'ID');
    $data['query_type'] = 'post_types';
    $data['query_args'] = [
        'post_type' => 'bioco_price', 'post_status' => 'publish',
        'post__in' => $ids ?: [0], 'orderby' => 'post__in', 'order' => 'ASC',
        'posts_per_page' => -1, 'offset' => 0, 'paged' => 1,
        'ignore_sticky_posts' => true, 'no_found_rows' => true,
    ];
    return $data;
}

/** Attach data only. All visible controls and copy are saved ordinary Divi modules. */
function bioco_price_native_wrapper(string $output, array $args): string {
    if (!bioco_price_native_has_class($args['attrs'] ?? [], 'bioco-price-calculator')
        || bioco_native_render_mode() !== 'public' || !class_exists('WP_HTML_Tag_Processor')) return $output;
    $catalog = bioco_price_catalog();
    if ($catalog === null || !empty($catalog['error'])) return $output;
    $html = new WP_HTML_Tag_Processor($output);
    if (!$html->next_tag(['class_name' => 'bioco-price-calculator'])) return $output;
    $html->set_attribute('data-bioco-prices', wp_json_encode($catalog));
    $file = dirname(__DIR__) . '/assets/native-pricing.js';
    wp_enqueue_script('bioco-native-pricing', plugins_url('assets/native-pricing.js', dirname(__DIR__) . '/bioco-core.php'), [], filemtime($file), true);
    return $html->get_updated_html();
}

add_filter('divi_loop_data_after_execution', 'bioco_price_native_query', 20, 2);
add_filter('divi_module_wrapper_render', 'bioco_price_native_wrapper', 20, 2);
add_filter('et_builder_third_party_post_types', 'bioco_price_native_types', 20);
