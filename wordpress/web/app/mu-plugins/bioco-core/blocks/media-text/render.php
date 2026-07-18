<?php
/**
 * Media + text block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Merges the former split_media_text / split_text_media Next.js layouts into
 * a single block with a media-side toggle (see SectionRenderer SplitSection).
 */

if (!defined('ABSPATH')) exit;

$media_side = get_field('media_side') ?: 'left';
$image = get_field('image');
$image_alt_override = get_field('image_alt');
$image_overlay = get_field('image_overlay');
$image_brightness = get_field('image_brightness');
$image_contrast = get_field('image_contrast');
$image_saturate = get_field('image_saturate');
$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$buttons = get_field('buttons');

if ($is_preview && !$title && !$text) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'media-text';

$class_name = 'cms-section cms-split';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$image_url = is_array($image) ? ($image['url'] ?? '') : '';
$image_alt = $image_alt_override ?: (is_array($image) ? ($image['alt'] ?? '') : '');

// DOM order always renders media before content, matching SectionRenderer's
// SplitSection: mobile always stacks media on top; the is-first/is-last
// class only reorders columns at the >=900px breakpoint (see app.css).
$media_first = $media_side !== 'right';
$side_class = $media_first ? 'is-first' : 'is-last';
$overlay_class = $image_overlay && $image_overlay !== 'none' ? 'image-overlay-' . $image_overlay : '';
$filter_style = bioco_image_filter_style($image_brightness, $image_contrast, $image_saturate);
$heading_already_in_text = bioco_text_has_heading_html($text);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <div class="cms-split-media <?php echo esc_attr(trim($side_class . ' ' . $overlay_class)); ?>">
        <?php if ($image_url) : ?>
            <div class="cms-media-frame" style="<?php echo esc_attr($filter_style); ?>">
                <img
                    src="<?php echo esc_url($image_url); ?>"
                    alt="<?php echo esc_attr($image_alt); ?>"
                    loading="lazy"
                />
            </div>
        <?php endif; ?>
    </div>
    <div class="cms-split-content">
        <?php if ($eyebrow) : ?>
            <p class="cms-section-eyebrow"><?php echo esc_html($eyebrow); ?></p>
        <?php endif; ?>
        <?php if ($title && !$heading_already_in_text) : ?>
            <h2><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php if ($text) : ?>
            <div class="cms-section-text"><?php echo bioco_kses_rich_text($text); ?></div>
        <?php endif; ?>
        <?php if (!empty($buttons)) : ?>
            <div class="cms-section-actions">
                <?php foreach ($buttons as $button) :
                    $button_text = $button['text'] ?? '';
                    $button_href = $button['href'] ?? '';
                    $button_variant = $button['variant'] ?? 'primary';
                    if (!$button_text || !$button_href) continue;
                ?>
                    <a href="<?php echo esc_url($button_href); ?>" class="btn btn-<?php echo esc_attr($button_variant); ?>"><?php echo esc_html($button_text); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
