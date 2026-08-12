<?php
/**
 * bioco block theme bootstrap — fallback/reference theme (#101).
 *
 * All blocks, ACF field groups, shared render helpers, and block CSS moved to
 * the bioco-core mu-plugin (web/app/mu-plugins/bioco-core/) so they work
 * under any active theme, including this one. This file now contains only
 * this theme's own presentation bootstrap — see this theme's README.md.
 */

if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    load_theme_textdomain('bioco', get_template_directory() . '/languages');
});

add_action('wp_enqueue_scripts', function () {
    $path = get_theme_file_path('assets/app.css');
    wp_enqueue_style('bioco-theme', get_theme_file_uri('assets/app.css'), [], file_exists($path) ? (string) filemtime($path) : null);
});
