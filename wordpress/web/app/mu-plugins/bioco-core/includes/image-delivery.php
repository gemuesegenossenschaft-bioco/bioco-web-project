<?php
/** Keep Modern Image Formats on WordPress's aspect-ratio-aware candidates. */
if (!defined('ABSPATH')) exit;

function bioco_image_delivery_use_core_responsive_sizes(): void {
    if (is_admin() || !function_exists('webp_uploads_get_image_output_format')) {
        return;
    }

    // Divi creates breakpoint images on demand. Regenerating them through a
    // format plugin can leave source mappings for an earlier crop. Core's
    // responsive candidates use the registered attachment sizes instead.
    remove_filter('wp_calculate_image_srcset', 'et_filter_wp_calculate_image_srcset', 10);
    remove_filter('wp_calculate_image_sizes', 'et_filter_wp_calculate_image_sizes', 10);
}
add_action('wp_loaded', 'bioco_image_delivery_use_core_responsive_sizes', 100);
