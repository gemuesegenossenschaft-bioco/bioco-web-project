<?php
/** 404 fallback. WordPress keeps its error status; Divi owns the body. */
if (!defined('ABSPATH')) exit;
require_once dirname(__DIR__, 2) . '/mu-plugins/bioco-core/includes/utility-queries.php';
get_header();
echo bioco_utility_render();
get_footer();
