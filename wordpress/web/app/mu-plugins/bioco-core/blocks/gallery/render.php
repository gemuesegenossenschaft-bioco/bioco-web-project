<?php
/**
 * Gallery block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors Gallery.tsx's markup/classes (gallery-filters/gallery-select/
 * gallery-grid/gallery-item). view.js reimplements the category filter +
 * "Mehr sehen" (show more than 4) toggle in vanilla JS; the click-to-enlarge
 * lightbox is deferred (not part of Gallery.tsx today either — it only
 * renders a plain grid — but noting it per issue #96's W9 scope).
 */

if (!defined('ABSPATH')) exit;

$items = bioco_field('items');
$show_more_label = bioco_field('show_more_label');
$loading_title = bioco_field('loading_title');
$loading_text = bioco_field('loading_text');

// Filter labels come from the ACF repeater field_bioco_gallery_filters, whose
// default rows carry today's wording. Flattened to slug => label so the two
// loops further down stay unchanged. The slug itself is mechanical (view.js
// matches it against each item's category), only the label is editorial.
$filters = [];
foreach ((array) bioco_field('filters') as $filter_row) {
    if (!is_array($filter_row)) continue;
    $filter_slug = isset($filter_row['key']) ? (string) $filter_row['key'] : '';
    $filter_text = isset($filter_row['label']) ? (string) $filter_row['label'] : '';
    if ($filter_slug === '' || $filter_text === '') continue;
    $filters[$filter_slug] = $filter_text;
}

// Resolve images up front so only items with a usable URL drive the grid, the
// display limit, the "show more" button and the empty state. The importer
// resolves seed images to bare attachment IDs, ACF hands back an array.
$resolved_items = [];
foreach ($items as $item) {
    $image = $item['image'] ?? null;
    $image_url = '';
    $image_alt = '';
    if (is_array($image)) {
        $image_url = $image['url'] ?? '';
        $image_alt = $image['alt'] ?? '';
    } elseif (is_numeric($image) && (int) $image > 0) {
        $image_url = wp_get_attachment_image_url((int) $image, 'large') ?: '';
        $image_alt = (string) get_post_meta((int) $image, '_wp_attachment_image_alt', true);
    }
    if ($image_url === '') {
        continue;
    }
    $resolved_items[] = [
        'url' => $image_url,
        'alt' => $image_alt,
        'category' => $item['category'] ?? 'feld',
    ];
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'gallery';
$class_name = 'cms-section cms-gallery';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <div class="gallery-container">
        <?php if (!empty($resolved_items)) : ?>
            <div class="gallery-filters">
                <?php foreach ($filters as $filter_key => $filter_label) : ?>
                    <button type="button" class="gallery-filter<?php echo $filter_key === 'all' ? ' active' : ''; ?>" data-gallery-filter="<?php echo esc_attr($filter_key); ?>"><?php echo esc_html($filter_label); ?></button>
                <?php endforeach; ?>
            </div>
            <select class="gallery-select" data-gallery-select>
                <?php foreach ($filters as $filter_key => $filter_label) : ?>
                    <option value="<?php echo esc_attr($filter_key); ?>"><?php echo esc_html($filter_label); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="gallery-grid" data-gallery-grid>
                <?php foreach ($resolved_items as $index => $resolved) : ?>
                    <div class="gallery-item" data-gallery-category="<?php echo esc_attr($resolved['category']); ?>" <?php if ($show_more_label && $index >= 4) : ?>style="display: none;"<?php endif; ?>>
                        <img src="<?php echo esc_url($resolved['url']); ?>" alt="<?php echo esc_attr($resolved['alt']); ?>" loading="lazy" style="object-fit: cover; width: 100%; height: 100%; border-radius: 8px;" />
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($show_more_label && count($resolved_items) > 4) : ?>
                <div style="margin-top: var(--wp--preset--spacing--40); text-align: center;">
                    <button type="button" class="btn btn-secondary" data-gallery-toggle><?php echo esc_html($show_more_label); ?></button>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="gallery-placeholder">
<?php if ($loading_title) : ?>                <p><?php echo esc_html($loading_title); ?></p><?php endif; ?>
<?php if ($loading_text) : ?>                <p style="font-size: 0.875rem; color: var(--wp--preset--color--bioco-text-muted);"><?php echo esc_html($loading_text); ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
