<?php
/**
 * Page intro block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's PageIntroBlock (containerWidth/textWidth/align).
 */

if (!defined('ABSPATH')) exit;

$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$buttons = get_field('buttons');
$container_width = get_field('container_width') ?: 'lg';
$text_width = get_field('text_width');
$align = get_field('align') ?: 'left';
$heading_level = (string) (get_field('heading_level') ?: '2');
$heading_level = in_array($heading_level, ['1', '2'], true) ? $heading_level : '2';

if ($is_preview && !$title && !$text) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'page-intro';

$class_name = 'cms-section cms-page-intro';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>" data-container="<?php echo esc_attr($container_width); ?>" data-align="<?php echo esc_attr($align); ?>">
    <div class="cms-page-intro-inner" data-text-width="<?php echo esc_attr($text_width); ?>">
        <?php if ($eyebrow) : ?>
            <p class="cms-section-eyebrow"><?php echo esc_html($eyebrow); ?></p>
        <?php endif; ?>
        <?php if ($title && !$heading_already_in_text) : ?>
            <<?php echo $heading_level === '1' ? 'h1' : 'h2'; ?>><?php echo esc_html($title); ?></<?php echo $heading_level === '1' ? 'h1' : 'h2'; ?>>
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
