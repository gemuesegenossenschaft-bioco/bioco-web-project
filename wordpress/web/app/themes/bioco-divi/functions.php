<?php
/**
 * bioco Divi child theme bootstrap (#101).
 *
 * Minimal: enqueues the parent Divi stylesheet first, then the child theme's
 * own style.css. The child style depends on both the parent stylesheet and
 * the shared bioco-core handles so the token custom properties and the shared
 * navigation/footer shell stylesheet (#180, `bioco-shell` from bioco-core)
 * are already defined when it loads — regardless of whether the bioco
 * fallback theme is installed.
 *
 * Content, blocks, forms, tokens and the shared shell assets come from the
 * mu-plugins (bioco-core, bioco-content, bioco-forms) which are
 * theme-agnostic and work regardless of which theme is active — see
 * ../bioco/README.md and the repo-root PORTING-THEME-SWAP.md / HARDCASES.md.
 */

if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    // Asset-only bootstraps may load the theme without WordPress's content API.
    if (function_exists('add_shortcode')) {
        require_once dirname(__DIR__, 2) . '/mu-plugins/bioco-core/includes/utility-queries.php';
    }
});

// Posts retain their recovery layout until #230. Events accept an explicit
// native assignment; the legacy default body is not an event detail template.
add_filter('et_theme_builder_template_layouts', function ($layouts) {
    if (is_singular('post')) return [];
    if (is_singular('event')) {
        $template = (int) ($layouts['et_template'] ?? 0);
        if (!$template || get_post_meta($template, '_et_default', true) === '1') return [];
    }
    return $layouts;
});

// The shared single template supplies native event/article and group layouts at
// render time. Divi needs the builder marker on the queried record to add its
// layout wrappers and generate the saved module styles. Keep metadata untouched.
add_filter('get_post_metadata', function ($value, $post_id, $key, $single) {
    if (is_singular(['event', 'post', 'bioco_group']) && (int) $post_id === (int) get_queried_object_id()) {
        if ($key === '_et_pb_use_builder') return $single ? 'on' : ['on'];
        if ($key === '_et_pb_page_layout') return $single ? 'et_full_width_page' : ['et_full_width_page'];
    }
    return $value;
}, 10, 4);

add_filter('body_class', function (array $classes): array {
    return array_values(array_diff($classes, ['et_fixed_nav', 'et_show_nav']));
}, PHP_INT_MAX);

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('divi-parent-style', get_template_directory_uri() . '/style.css');

    $child_style_path = get_stylesheet_directory() . '/style.css';
    $child_version = file_exists($child_style_path)
        ? (string) filemtime($child_style_path)
        : wp_get_theme()->get('Version');

    wp_enqueue_style(
        'bioco-divi-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['divi-parent-style', 'bioco-tokens', 'bioco-shell'],
        $child_version
    );
    $editorial_path = get_stylesheet_directory() . '/editorial.js';
    wp_enqueue_script('bioco-divi-editorial', get_stylesheet_directory_uri() . '/editorial.js', [], (string) filemtime($editorial_path), true);
});
