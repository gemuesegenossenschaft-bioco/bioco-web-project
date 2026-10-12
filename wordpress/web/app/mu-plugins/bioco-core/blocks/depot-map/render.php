<?php
/**
 * Depot-map block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Public depot records own marker content. The built-in address list remains
 * available until a native Divi depot loop replaces it on an editorial page.
 * Both maps render a static address fallback when Leaflet is unavailable.
 */

if (!defined('ABSPATH')) exit;

$intro = bioco_field('intro');
$locations = bioco_field('locations');
$catalog_rows = function_exists('bioco_catalog_rows') ? bioco_catalog_rows('depots') : null;
if ($catalog_rows !== null) $locations = $catalog_rows;
elseif (is_array($locations)) {
    // An unmigrated local row is not approval to republish recovered details.
    $locations = array_map(static function ($row) {
        if (is_array($row)) $row['description'] = '';
        return $row;
    }, $locations);
}
$locations_heading = bioco_field('locations_heading');
$route_label = bioco_field('route_label');
$empty_message = bioco_field('empty_message');
$center_lat = bioco_field('center_lat', 47.4734);
$center_lng = bioco_field('center_lng', 8.3089);
$zoom = bioco_field('zoom', 12);
$center_lat = is_numeric($center_lat) && $center_lat >= -90 && $center_lat <= 90 ? (float) $center_lat : 47.4734;
$center_lng = is_numeric($center_lng) && $center_lng >= -180 && $center_lng <= 180 ? (float) $center_lng : 8.3089;
$zoom = is_numeric($zoom) ? max(1, min(19, (int) $zoom)) : 12;
$options = ['map_view' => bioco_field('map_view', 'fit'), 'interaction' => bioco_field('interaction', 'static'),
    'map_height' => bioco_field('map_height', 300), 'arrangement' => bioco_field('arrangement', 'stacked'),
    'card_order' => bioco_field('card_order', 'name_first'), 'show_address_list' => bioco_field('show_address_list', true)];

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'depot-map';
$class_name = 'cms-section cms-depot-map';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($intro) : ?>
        <div class="cms-map-intro"><p><?php echo esc_html($intro); ?></p></div>
    <?php endif; ?>
    <?php bioco_render_map_block($locations, $center_lat, $center_lng, $zoom, $locations_heading, $route_label, $empty_message, $options); ?>
</section>
