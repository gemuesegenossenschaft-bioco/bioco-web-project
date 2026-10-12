<?php
/** Readable record routing and native collection query boundaries. */
if (!defined('ABSPATH')) exit;
require_once dirname(__DIR__, 2) . '/bioco-import/includes/record-layouts.php';

function bioco_record_native_has_class(array $attrs, string $class): bool {
    return in_array($class, preg_split('/\s+/', bioco_native_extract_wrapper($attrs)['className'], -1, PREG_SPLIT_NO_EMPTY), true);
}

function bioco_record_native_query(array $data, array $attrs): array {
    $type = bioco_record_native_has_class($attrs, 'bioco-group-loop') ? 'bioco_group'
        : (bioco_record_native_has_class($attrs, 'bioco-vegetable-loop') ? 'bioco_vegetable'
        : (bioco_record_native_has_class($attrs, 'bioco-depot-loop') ? 'bioco_depot' : ''));
    if ($type === '') return $data;
    $args = $data['query_args'] ?? [];
    $args['post_type'] = $type; $args['post_status'] = 'publish'; $args['ignore_sticky_posts'] = true;
    if ($type === 'bioco_depot') {
        // Match the map's public catalog, including its editor-defined order.
        $posts = get_posts(['post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1,
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']]);
        $public_ids = [];
        foreach ($posts as $post) {
            if (metadata_exists('post', $post->ID, 'depot_public') && !get_post_meta($post->ID, 'depot_public', true)) continue;
            $public_ids[] = (int) $post->ID;
        }
        $args['post__in'] = $public_ids ?: [0];
        $args['orderby'] = 'post__in';
        $args['posts_per_page'] = -1;
    }
    $ids = array_map('intval', (array) ($args['post__in'] ?? []));
    if ($ids && $type !== 'bioco_depot' && bioco_native_render_mode() === 'inert') {
        $readable = array_filter($ids, static fn($id) => get_post_type($id) === $type && current_user_can('read_post', $id));
        $args['post__in'] = $readable ? array_values($readable) : [0];
        $args['post_status'] = ['publish', 'draft', 'private'];
    }
    $data['query_type'] = 'post_types'; $data['query_args'] = $args;
    return $data;
}

/** Native Post Content consumes the shared saved detail only for an empty group body. */
function bioco_group_record_content(string $content): string {
    if (trim($content) !== '' || !is_singular('bioco_group')) return $content;
    $id = (int) get_queried_object_id();
    $post = get_post($id);
    if (!$post || $post->post_type !== 'bioco_group' || trim((string) $post->post_content) !== ''
        || post_password_required($id) || ($post->post_status !== 'publish' && !current_user_can('read_post', $id))) return $content;
    $layouts = bioco_record_library_posts('group-detail');
    return count($layouts) === 1 && $layouts[0]->post_status === 'publish' ? $layouts[0]->post_content : $content;
}

function bioco_record_native_wrapper(string $output, array $args): string {
    $attrs = $args['attrs'] ?? [];
    if (!class_exists('WP_HTML_Tag_Processor')) return $output;
    if (($args['name'] ?? '') === 'divi/button' && (bioco_record_native_has_class($attrs, 'bioco-group-contact-action')
        || bioco_record_native_has_class($attrs, 'bioco-depot-website-action')
        || bioco_record_native_has_class($attrs, 'bioco-depot-route-action'))) {
        $html = new WP_HTML_Tag_Processor($output);
        if (!$html->next_tag('a') || !preg_match('~^(?:https?://|mailto:|tel:|/(?!/))~i', (string) $html->get_attribute('href'))) return '';
    }
    if (bioco_record_native_has_class($attrs, 'bioco-native-calendar') && bioco_native_render_mode() === 'public') {
        $file = dirname(__DIR__) . '/assets/native-calendar.js';
        wp_enqueue_script('bioco-native-calendar', plugins_url('assets/native-calendar.js', dirname(__DIR__) . '/bioco-core.php'), [], filemtime($file), true);
    }
    return $output;
}

add_filter('divi_module_wrapper_render', 'bioco_record_native_wrapper', 20, 2);
add_filter('divi_loop_data_after_execution', 'bioco_record_native_query', 20, 2);
add_filter('the_content', 'bioco_group_record_content', 7);
add_filter('et_builder_third_party_post_types', static fn(array $types): array => array_values(array_unique(array_merge($types, ['bioco_vegetable', 'bioco_depot']))), 20);
