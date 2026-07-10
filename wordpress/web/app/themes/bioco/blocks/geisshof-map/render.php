<?php
/**
 * Geisshof-map block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors GeisshofMap.tsx: non-interactive Leaflet map + single address card,
 * reusing the same bioco_render_map_block() helper as bioco/depot-map (see
 * that block's render.php for the progressive-enhancement/Leaflet notes).
 */

if (!defined('ABSPATH')) exit;

$locations = get_field('locations');

if (empty($locations)) {
    $locations = [
        ['name' => 'Geisshof', 'lat' => 47.4741684, 'lng' => 8.2456318, 'description' => 'Geisslistrasse, Gebenstorf, Schweiz'],
    ];
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'geisshof-map';
$class_name = 'cms-section cms-geisshof-map';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php bioco_render_map_block($locations, 47.4741684, 8.2456318, 14); ?>
</section>
