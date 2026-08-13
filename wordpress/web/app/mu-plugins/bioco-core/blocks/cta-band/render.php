<?php
/**
 * CTA band block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's CtaBandBlock. Standalone per-page block: every
 * instance (e.g. each kennenlernen-cta) carries its own title/text/buttons,
 * there is no shared/global CTA content.
 */

if (!defined('ABSPATH')) exit;

$title = get_field('title');
$text = get_field('text');
$buttons = get_field('buttons');
$container_width = get_field('container_width') ?: 'lg';
$align = get_field('align') ?: 'left';
$theme = get_field('theme') ?: 'soft';
$rounded = get_field('rounded') ?: 'xl';

if ($is_preview && !$title && !$text) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'cta-band';

$class_name = 'cms-section cms-cta-band';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>" data-container="<?php echo esc_attr($container_width); ?>">
    <div class="cms-cta-band-inner" data-theme="<?php echo esc_attr($theme); ?>" data-rounded="<?php echo esc_attr($rounded); ?>" data-align="<?php echo esc_attr($align); ?>">
        <?php if ($title && !$heading_already_in_text) : ?>
            <h2><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php if ($text) : ?>
            <div class="cms-section-text"><?php echo bioco_kses_rich_text($text); ?></div>
        <?php endif; ?>
        <?php if (!empty($buttons)) : ?>
            <div class="cms-section-actions cms-cta-band-actions">
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
