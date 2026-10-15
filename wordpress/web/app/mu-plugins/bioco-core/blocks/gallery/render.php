<?php
/**
 * Gallery block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Saved native fields own categories, captions, count, labels and grid design.
 * The view script filters the resolved images and expands/collapses the grid.
 */

if (!defined('ABSPATH')) exit;

$items = bioco_field('items');
$show_more_label = bioco_field('show_more_label');
$show_less_label = bioco_field('show_less_label');
$filter_select_label = bioco_field('filter_select_label');
$empty_filter_label = bioco_field('empty_filter_label');
$initial_count = bioco_field('initial_count', 4);
$initial_count = is_numeric($initial_count) ? max(0, min(500, (int) $initial_count)) : 4;
$grid_styles = [];
foreach (['desktop' => 6, 'tablet' => 4, 'mobile' => 2] as $device => $maximum) {
    $columns = (int) bioco_field('columns_' . $device, 0);
    if ($columns >= 1 && $columns <= $maximum) {
        $grid_styles[] = '--bioco-gallery-columns-' . $device . ':repeat(' . $columns . ',minmax(0,1fr))';
    }
}
$gap = bioco_field('grid_gap');
if (is_numeric($gap)) $grid_styles[] = '--bioco-gallery-gap:' . max(0, min(96, (int) $gap)) . 'px';
$image_fit = bioco_field('image_fit') === 'contain' ? 'contain' : 'cover';
$ratios = ['4_3' => '4 / 3', '1_1' => '1 / 1', '16_9' => '16 / 9', 'original' => 'auto'];
$image_ratio = $ratios[(string) bioco_field('image_ratio')] ?? '4 / 3';
$radius = (string) bioco_field('image_radius');
if (in_array($radius, ['0', '8', '16', '24'], true)) $grid_styles[] = '--bioco-gallery-radius:' . $radius . 'px';
$grid_styles[] = '--bioco-gallery-fit:' . $image_fit;
$grid_styles[] = '--bioco-gallery-ratio:' . $image_ratio;
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
foreach ((array) $items as $item) {
    if (!is_array($item)) continue;
    $image = $item['image'] ?? null;
    $image_id = 0;
    $image_url = '';
    $image_alt = '';
    if (is_array($image)) {
        $image_id = (int) ($image['ID'] ?? $image['id'] ?? 0);
        $image_url = $image['url'] ?? '';
        $image_alt = $image['alt'] ?? '';
    } elseif (is_numeric($image) && (int) $image > 0) {
        $image_id = (int) $image;
        $image_url = wp_get_attachment_image_url((int) $image, 'large') ?: '';
        $image_alt = (string) get_post_meta((int) $image, '_wp_attachment_image_alt', true);
    }
    // WordPress supplies responsive sizes and image-format plugin filters.
    // URL-only legacy entries retain their existing fallback below.
    $image_html = $image_id > 0
        ? wp_get_attachment_image($image_id, 'large', false, ['alt' => $image_alt, 'loading' => 'lazy'])
        : '';
    if ($image_url === '' && $image_html === '') {
        continue;
    }
    $resolved_items[] = [
        'url' => $image_url,
        'html' => $image_html,
        'alt' => $image_alt,
        'category' => $item['category'] ?? 'feld',
        'caption' => $item['caption'] ?? '',
    ];
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'gallery';
$class_name = 'cms-section cms-gallery';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <div class="gallery-container" data-gallery-limit="<?php echo esc_attr($initial_count); ?>" data-gallery-more="<?php echo esc_attr($show_more_label); ?>"<?php if ($show_less_label !== null) : ?> data-gallery-less="<?php echo esc_attr($show_less_label); ?>"<?php endif; ?>>
        <?php if (!empty($resolved_items)) : ?>
            <?php if ($filters) : ?>
            <div class="gallery-filters">
                <?php foreach ($filters as $filter_key => $filter_label) : ?>
                    <button type="button" class="gallery-filter<?php echo $filter_key === 'all' ? ' active' : ''; ?>" aria-pressed="<?php echo $filter_key === 'all' ? 'true' : 'false'; ?>" data-gallery-filter="<?php echo esc_attr($filter_key); ?>"><?php echo esc_html($filter_label); ?></button>
                <?php endforeach; ?>
            </div>
            <select class="gallery-select" data-gallery-select aria-label="<?php echo esc_attr($filter_select_label ?: ($filters['all'] ?? reset($filters))); ?>">
                <?php foreach ($filters as $filter_key => $filter_label) : ?>
                    <option value="<?php echo esc_attr($filter_key); ?>" <?php if ($filter_key === 'all') echo 'selected'; ?>><?php echo esc_html($filter_label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <div class="gallery-grid" data-gallery-grid style="<?php echo esc_attr(implode(';', $grid_styles)); ?>">
                <?php foreach ($resolved_items as $index => $resolved) : ?>
                    <figure class="gallery-item" data-gallery-category="<?php echo esc_attr($resolved['category']); ?>" <?php if ($show_more_label && $initial_count > 0 && $index >= $initial_count) : ?>style="display: none;"<?php endif; ?>>
                        <div class="gallery-image"><?php if ($resolved['html'] !== '') : ?><?php echo $resolved['html']; ?><?php else : ?><img src="<?php echo esc_url($resolved['url']); ?>" alt="<?php echo esc_attr($resolved['alt']); ?>" loading="lazy" /><?php endif; ?></div>
                        <?php if ($resolved['caption'] !== '') : ?><figcaption><?php echo esc_html($resolved['caption']); ?></figcaption><?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
            <?php if ($empty_filter_label) : ?><p data-gallery-empty role="status" hidden><?php echo esc_html($empty_filter_label); ?></p><?php endif; ?>
            <?php if ($show_more_label && $initial_count > 0 && count($resolved_items) > $initial_count) : ?>
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
