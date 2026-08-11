<?php
/**
 * Depot-map block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors DepotMap.tsx: non-interactive Leaflet map + address list of the
 * nine bioco depots. The full DepotLocation shape (day/contact/website/
 * notes/hideAddress) is collapsed into the simplified (name, lat, lng,
 * description) repeater from issue #96; the defaults below carry that
 * context forward as free text so a fresh install isn't blank.
 * Leaflet itself is not vendored yet (deferred to W11) — view.js only draws
 * a live map when window.L is already present, otherwise the address list
 * below is the whole UI (progressive enhancement).
 */

if (!defined('ABSPATH')) exit;

$intro = get_field('intro');
$locations = get_field('locations');
$locations_heading = get_field('locations_heading');
$route_label = get_field('route_label');
$empty_message = get_field('empty_message');

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
    <?php bioco_render_map_block($locations, 47.4734, 8.3089, 12, $locations_heading, $route_label, $empty_message); ?>
</section>
