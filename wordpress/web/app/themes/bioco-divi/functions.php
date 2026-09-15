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
});
