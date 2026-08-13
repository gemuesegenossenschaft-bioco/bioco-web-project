<?php
/**
 * Media grid block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's MediaGridSection (cms-media-grid).
 */

if (!defined('ABSPATH')) exit;

$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$gallery = get_field('gallery');
$buttons = get_field('buttons');

if ($is_preview && !$title && !$text && empty($gallery)) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'media-grid';

$class_name = 'cms-section cms-media-grid';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($eyebrow || ($title && !$heading_already_in_text) || $text) : ?>
        <div class="cms-media-grid-text">
            <?php if ($eyebrow) : ?>
                <p class="cms-section-eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <?php endif; ?>
            <?php if ($title && !$heading_already_in_text) : ?>
                <h2><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <div class="cms-section-text"><?php echo bioco_kses_rich_text($text); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($gallery)) : ?>
        <div class="cms-media-grid-items">
            <?php foreach ($gallery as $item) :
                $item_url = $item['url'] ?? '';
                $item_alt = $item['alt'] ?: $title;
                if (!$item_url) continue;
            ?>
                <div class="cms-media-frame">
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
