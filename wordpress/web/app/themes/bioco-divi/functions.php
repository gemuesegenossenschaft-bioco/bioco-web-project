<?php
/**
 * bioco Divi child theme bootstrap (#101).
 *
 * Intentionally minimal: enqueues the parent Divi stylesheet, nothing else.
 * Content, blocks, and forms come from the mu-plugins (bioco-core,
 * bioco-content, bioco-forms) which are theme-agnostic and work regardless
 * of which theme is active — see ../bioco/README.md and the repo-root
 * PORTING-THEME-SWAP.md / HARDCASES.md.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('divi-parent-style', get_template_directory_uri() . '/style.css');
});
