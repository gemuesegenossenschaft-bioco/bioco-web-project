<?php
/**
 * bioco block theme bootstrap.
 * ACF Blocks + Local JSON are registered here as slices W4–W9 land.
 */

if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    load_theme_textdomain('bioco', get_template_directory() . '/languages');
});

// Front-end + editor styles: enqueue any bespoke component CSS layered on theme.json tokens.
add_action('wp_enqueue_scripts', function () {
    $rel = '/assets/app.css';
    $path = get_template_directory() . $rel;
    if (file_exists($path)) {
        wp_enqueue_style('bioco-app', get_template_directory_uri() . $rel, [], (string) filemtime($path));
    }
});

/**
 * ACF Local JSON — field-group definitions live in git (acf-json/), not the DB.
 * Save point + load point so every block's fields are version-controlled (W4+).
 */
add_filter('acf/settings/save_json', fn() => get_template_directory() . '/acf-json');
add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = get_template_directory() . '/acf-json';
    return $paths;
});

/**
 * Custom "bioco" block category — groups all bespoke section/ACF blocks
 * together in the inserter instead of falling under "Theme"/"Formatting".
 */
add_filter('block_categories_all', function (array $categories) {
    return array_merge(
        [
            [
                'slug' => 'bioco',
                'title' => __('biocò', 'bioco'),
                'icon' => 'admin-site-alt3',
            ],
        ],
        $categories
    );
});

/**
 * Section blocks: every block.json found one level under blocks/ is
 * auto-registered here (W4 tracer bullet onwards).
 */
add_action('init', function () {
    $blocks_dir = get_template_directory() . '/blocks';
    if (!is_dir($blocks_dir)) return;
    foreach (glob($blocks_dir . '/*/block.json') as $block_json) {
        register_block_type(dirname($block_json));
    }
});
