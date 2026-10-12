<?php
/** Shared post/article shell; native Divi modules own the article layout. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/includes/article.php';
get_header();
while (have_posts()) {
    the_post();
    if (get_post_type(get_the_ID()) === 'bioco_group') {
        // Native group bodies, including the blank-body saved-layout fallback,
        // pass through the content filters once without the article shell.
        the_content();
    } else {
        echo bioco_divi_render_article();
    }
}
get_footer();
