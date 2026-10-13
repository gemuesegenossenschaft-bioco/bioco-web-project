<?php
/** Search fallback. Saved Divi library layouts own all visible modules. */
if (!defined('ABSPATH')) exit;
require_once dirname(__DIR__, 2) . '/mu-plugins/bioco-core/includes/utility-queries.php';
// Divi replaces both partials when either shell layout overrides. Those
// native partials omit the main landmark supplied by our fallback partials.
$native_shell = function_exists('et_theme_builder_overrides_layout')
    && (et_theme_builder_overrides_layout(ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE)
        || et_theme_builder_overrides_layout(ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE));
get_header();
if ($native_shell) echo '<main id="bioco-main-content">';
echo bioco_utility_render();
if ($native_shell) echo '</main>';
get_footer();
