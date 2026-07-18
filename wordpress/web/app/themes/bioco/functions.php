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
