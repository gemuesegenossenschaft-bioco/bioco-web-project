<?php
/**
 * Banner block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's full_width_banner layout (cms-banner /
 * cms-banner-inner), extended with a full-bleed background image since the
 * Next.js BannerSection itself has no image field.
 */

if (!defined('ABSPATH')) exit;

$image = get_field('image');
$image_alt_override = get_field('image_alt');
$image_overlay = get_field('image_overlay');
$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$buttons = get_field('buttons');

if ($is_preview && !$title && !$text) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'banner';

$image_url = is_array($image) ? ($image['url'] ?? '') : '';
$image_alt = $image_alt_override ?: (is_array($image) ? ($image['alt'] ?? '') : '');

$class_name = 'cms-section cms-banner';
if ($image_url) {
    $class_name .= ' cms-banner--media';
}
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$overlay_class = $image_overlay && $image_overlay !== 'none' ? 'cms-banner-overlay-' . $image_overlay : '';
$heading_already_in_text = bioco_text_has_heading_html($text);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($image_url) : ?>
        <div class="cms-banner-media <?php echo esc_attr($overlay_class); ?>">
            <img
                src="<?php echo esc_url($image_url); ?>"
                alt="<?php echo esc_attr($image_alt); ?>"
                loading="lazy"
            />
        </div>
    <?php endif; ?>
    <div class="cms-banner-inner">
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
                    <a href="<?php echo esc_url($button_href); ?>"<?php echo bioco_link_target_attributes($button_href); ?> class="btn btn-<?php echo esc_attr($button_variant); ?>"><?php echo esc_html($button_text); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
