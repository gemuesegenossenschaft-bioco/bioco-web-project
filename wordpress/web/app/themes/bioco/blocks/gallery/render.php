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

$items = get_field('items');

$filters = [
    'all' => 'Alles',
    'koerbe' => 'Körbe',
    'feld' => 'Feld',
    'portraits' => 'Portraits',
];

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'gallery';
$class_name = 'cms-section cms-gallery';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <div class="gallery-container">
        <?php if (!empty($items)) : ?>
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
                <?php foreach ($items as $index => $item) :
                    $image = $item['image'] ?? null;
                    $category = $item['category'] ?? 'feld';
                    if (empty($image['url'])) continue;
                ?>
                    <div class="gallery-item" data-gallery-category="<?php echo esc_attr($category); ?>" <?php if ($index >= 4) : ?>style="display: none;"<?php endif; ?>>
                        <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt'] ?: ''); ?>" loading="lazy" style="object-fit: cover; width: 100%; height: 100%; border-radius: 8px;" />
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($items) > 4) : ?>
                <div style="margin-top: var(--wp--preset--spacing--40); text-align: center;">
                    <button type="button" class="btn btn-secondary" data-gallery-toggle>Mehr sehen</button>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="gallery-placeholder">
                <p>Galerie-Bilder werden geladen…</p>
                <p style="font-size: 0.875rem; color: var(--wp--preset--color--bioco-text-muted);">Die Galerie wird in Kürze mit Bildern gefüllt.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
