<?php
/** Shared post/article shell; native Divi modules own the article layout. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/includes/article.php';
get_header();
while (have_posts()) {
    the_post();
    echo bioco_divi_render_article();
}
get_footer();
