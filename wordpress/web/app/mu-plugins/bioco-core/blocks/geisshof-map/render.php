<?php
/**
 * Geisshof-map block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Local location rows own the Geisshof markers and address cards. The shared
 * map helper applies the editor's view and layout settings.
 */

if (!defined('ABSPATH')) exit;

$locations = bioco_field('locations');
$locations_heading = bioco_field('locations_heading');
$route_label = bioco_field('route_label');
$empty_message = bioco_field('empty_message');
$center_lat = bioco_field('center_lat', 47.4741684);
$center_lng = bioco_field('center_lng', 8.2456318);
$zoom = bioco_field('zoom', 14);
$center_lat = is_numeric($center_lat) && $center_lat >= -90 && $center_lat <= 90 ? (float) $center_lat : 47.4741684;
$center_lng = is_numeric($center_lng) && $center_lng >= -180 && $center_lng <= 180 ? (float) $center_lng : 8.2456318;
$zoom = is_numeric($zoom) ? max(1, min(19, (int) $zoom)) : 14;
$options = ['map_view' => bioco_field('map_view', 'fixed'), 'interaction' => bioco_field('interaction', 'static'),
    'map_height' => bioco_field('map_height', 300), 'arrangement' => bioco_field('arrangement', 'stacked'),
    'card_order' => bioco_field('card_order', 'name_first')];

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'geisshof-map';
$class_name = 'cms-section cms-geisshof-map';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php bioco_render_map_block($locations, $center_lat, $center_lng, $zoom, $locations_heading, $route_label, $empty_message, $options); ?>
</section>
