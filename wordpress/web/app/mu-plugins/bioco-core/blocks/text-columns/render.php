<?php
/**
 * Text columns block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's TextColumnsBlock: renders the WYSIWYG text with
 * CSS column-count, driven by the columns/gap options.
 */

if (!defined('ABSPATH')) exit;

$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$buttons = get_field('buttons');
$container_width = get_field('container_width') ?: 'lg';
$columns = get_field('columns') ?: '2';
$gap = get_field('gap') ?: 'lg';

if ($is_preview && !$title && !$text) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'text-columns';

$class_name = 'cms-section cms-text-columns';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>" data-container="<?php echo esc_attr($container_width); ?>">
    <?php if ($eyebrow) : ?>
        <p class="cms-section-eyebrow"><?php echo esc_html($eyebrow); ?></p>
    <?php endif; ?>
    <?php if ($title && !$heading_already_in_text) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if ($text) : ?>
        <div class="cms-section-text cms-text-columns-body" data-columns="<?php echo esc_attr($columns); ?>" data-gap="<?php echo esc_attr($gap); ?>"><?php echo bioco_kses_rich_text($text); ?></div>
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
</section>
