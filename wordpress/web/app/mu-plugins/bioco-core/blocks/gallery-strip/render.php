<?php
/**
 * Gallery strip block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's GalleryStripBlock (configurable columns/ratio/
 * fit/gap/rounded over the shared Section Common gallery field).
 */

if (!defined('ABSPATH')) exit;

$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$gallery = get_field('gallery');
$buttons = get_field('buttons');
$columns_desktop = get_field('columns_desktop') ?: '3';
$columns_mobile = get_field('columns_mobile') ?: '1';
$media_ratio = get_field('media_ratio') ?: '4:3';
$media_fit = get_field('media_fit') ?: 'cover';
$gap = get_field('gap') ?: 'lg';
$rounded = get_field('rounded') ?: 'lg';

if ($is_preview && !$title && !$text && empty($gallery)) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'gallery-strip';

$class_name = 'cms-section cms-gallery-strip';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($eyebrow) : ?>
        <p class="cms-section-eyebrow"><?php echo esc_html($eyebrow); ?></p>
    <?php endif; ?>
    <?php if ($title && !$heading_already_in_text) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if ($text) : ?>
        <div class="cms-section-text"><?php echo bioco_kses_rich_text($text); ?></div>
    <?php endif; ?>
    <?php if (!empty($gallery)) : ?>
        <div
            class="cms-gallery-strip-items"
            data-columns-desktop="<?php echo esc_attr($columns_desktop); ?>"
            data-columns-mobile="<?php echo esc_attr($columns_mobile); ?>"
            data-media-ratio="<?php echo esc_attr($media_ratio); ?>"
            data-media-fit="<?php echo esc_attr($media_fit); ?>"
            data-gap="<?php echo esc_attr($gap); ?>"
            data-rounded="<?php echo esc_attr($rounded); ?>"
        >
            <?php foreach ($gallery as $item) :
                $item_url = $item['url'] ?? '';
                $item_alt = $item['alt'] ?: $title;
                if (!$item_url) continue;
            ?>
                <div class="cms-gallery-strip-frame">
                    <img
                        src="<?php echo esc_url($item_url); ?>"
                        alt="<?php echo esc_attr($item_alt); ?>"
                        loading="lazy"
                    />
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($buttons)) : ?>
        <div class="cms-section-actions">
            <?php foreach ($buttons as $button) :
                $button_text = $button['text'] ?? '';
                $button_href = $button['href'] ?? '';
                $button_variant = $button['variant'] ?? 'primary';
                if (!$button_text || !$button_href) continue;
            ?>
                <a href="<?php echo esc_url($button_href); ?>"<?php echo bioco_link_target_attributes($button_href); ?> class="btn btn-<?php echo esc_attr($button_variant); ?>"><?php echo esc_html($button_text); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
