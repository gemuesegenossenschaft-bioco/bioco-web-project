<?php
/** Request adapters for Divi-owned utility layouts. No stored page/global-template writes. */
if (!defined('ABSPATH')) exit;
require_once dirname(__DIR__, 2) . '/bioco-import/includes/utility-layouts.php';

function bioco_utility_content(string $key): string {
    $posts = bioco_utility_library_posts($key);
    // A non-published editor copy must never leak through a public request.
    if (count($posts) === 1 && $posts[0]->post_status === 'publish') return $posts[0]->post_content;
    return bioco_utility_layout_definitions()[$key]['content'] ?? '';
}

function bioco_utility_render(): string {
    $key = is_404() ? '404' : (is_search() ? (have_posts() ? 'search' : 'search-empty') : '');
    return $key === '' ? '' : apply_filters('the_content', bioco_utility_content($key));
}

function bioco_utility_plain_text(string $value): string {
    // Titles/excerpts are data even when they contain registered shortcodes.
    return str_replace(['[', ']'], ['&#91;', '&#93;'], esc_html($value));
}

/** Escape values first, then substitute only attribute data, not block delimiters. */
function bioco_utility_result_blocks(array $blocks, array $values): array {
    $replace = static function ($value) use (&$replace, $values) {
        if (is_array($value)) return array_map($replace, $value);
        return is_string($value) ? strtr($value, $values) : $value;
    };
    foreach ($blocks as &$block) {
        $block['attrs'] = $replace($block['attrs']);
        $block['innerBlocks'] = bioco_utility_result_blocks($block['innerBlocks'], $values);
    }
    return $blocks;
}

function bioco_utility_results(): string {
    global $wp_query;
    static $rendering = false;
    if (!is_search() || is_404() || $rendering) return '';
    $rendering = true;
    try {
        $item = parse_blocks(bioco_utility_content('search-item'));
        $content = '';
        // WordPress's main search query controls filtering, ordering and pagination.
        // Do not run a second query, advance the loop, or change the global post.
        foreach ($wp_query->posts as $post) {
            $values = [
                '{{bioco_result_title}}' => bioco_utility_plain_text(get_the_title($post)),
                '{{bioco_result_excerpt}}' => bioco_utility_plain_text(wp_strip_all_tags(get_the_excerpt($post))),
                '{{bioco_result_url}}' => esc_url_raw(get_permalink($post)),
            ];
            $content .= serialize_blocks(bioco_utility_result_blocks($item, $values));
        }
        return $content === '' ? '' : apply_filters('the_content', $content);
    } finally { $rendering = false; }
}

add_action('init', function () {
    add_shortcode('bioco_utility_query', function () {
        return is_search() ? bioco_utility_plain_text(get_search_query(false)) : '';
    });
    add_shortcode('bioco_utility_results', 'bioco_utility_results');
    add_shortcode('bioco_utility_pagination', function ($attrs) {
        global $wp_query;
        if (!is_search() || is_404()) return '';
        $labels = shortcode_atts(['previous' => '', 'next' => ''], $attrs, 'bioco_utility_pagination');
        return (string) paginate_links([
            'current' => max(1, (int) get_query_var('paged')), 'total' => (int) $wp_query->max_num_pages,
            'prev_text' => esc_html($labels['previous']), 'next_text' => esc_html($labels['next']),
        ]);
    });
});

// Rank Math's 404 paper already uses noindex; search depends on titles.noindex_search.
// Enforce the issue's policy inside its sole emitter, preserving other directives.
add_filter('rank_math/frontend/robots', function ($robots) {
    if (is_search() || is_404()) $robots['index'] = 'noindex';
    return $robots;
}, 99);

add_action('template_redirect', function () {
    if (is_404()) { status_header(404); nocache_headers(); }
});

/** Recognize only an unedited Post Content passthrough, never an editor's body. */
function bioco_utility_is_passthrough(array $blocks): bool {
    $leaf = ['blockName' => 'divi/post-content', 'attrs' => [], 'innerBlocks' => [],
        'innerHTML' => '', 'innerContent' => ["\n"]];
    $column = bioco_import_divi_block('divi/column', [
        'module' => ['advanced' => ['type' => ['desktop' => ['value' => '4_4']]]],
    ], [$leaf]);
    $row = bioco_import_divi_block('divi/row', [
        'module' => ['advanced' => ['columnStructure' => ['desktop' => ['value' => '4_4']]]],
    ], [$column]);
    $section = bioco_import_divi_block('divi/section', [
        'module' => ['advanced' => ['htmlAttributes' => ['desktop' => ['value' => [
            'class' => 'bioco-global-layout bioco-global-body',
        ]]]]],
    ], [$row]);
    // Compare the seed's complete attributes, including container styling.
    // Even an editor's spacing/preset change keeps their Theme Builder body.
    return $blocks == parse_blocks(serialize_blocks([$section]));
}

add_filter('et_theme_builder_template_layouts', function ($layouts) {
    if (!is_search() && !is_404()) return $layouts;
    // Divi keys template layouts by post type, not by area name.
    $key = defined('ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE') ? ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE : 'et_body_layout';
    $body = $layouts[$key] ?? [];
    if (empty($body['id']) || empty($body['enabled'])) return $layouts;
    $post = get_post($body['id']);
    // Default Post Content has no queried post for search/404. Use the child
    // template and existing shared shell only for that known fallback body.
    if ($post && $post->post_name === 'bioco-global-body'
        && bioco_utility_is_passthrough(parse_blocks($post->post_content))) return [];
    return $layouts;
}, 20);

add_filter('et_builder_should_load_framework', function ($load) {
    return is_search() || is_404() ? true : $load;
});
