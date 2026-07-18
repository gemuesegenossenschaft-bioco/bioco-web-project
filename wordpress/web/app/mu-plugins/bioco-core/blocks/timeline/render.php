<?php
/**
 * Timeline block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Single block replacing the former timeline_header + timeline_item pair:
 * an optional header (eyebrow/title/text) followed by an items sub-repeater
 * (year/eyebrow badge, title, text, emphasis). Mirrors SectionRenderer's
 * TimelineHeaderBlock + TimelineItemBlock combined into one editable unit.
 */

if (!defined('ABSPATH')) exit;

$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$items = get_field('items');
$container_width = get_field('container_width') ?: 'lg';
$text_width = get_field('text_width') ?: 'normal';
$align = get_field('align') ?: 'left';

if ($is_preview && !$title && !$text && empty($items)) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'timeline';

$class_name = 'cms-section cms-timeline';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);
$has_header = $eyebrow || ($title && !$heading_already_in_text) || $text;
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>" data-container="<?php echo esc_attr($container_width); ?>">
    <?php if ($has_header) : ?>
        <div class="cms-timeline-header" data-text-width="<?php echo esc_attr($text_width); ?>" data-align="<?php echo esc_attr($align); ?>">
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
    <?php if (!empty($items)) : ?>
        <div class="cms-timeline-items">
            <?php foreach ($items as $item) :
                $item_year = $item['year_eyebrow'] ?? '';
                $item_title = $item['title'] ?? '';
                $item_text = $item['text'] ?? '';
                $item_emphasis = $item['emphasis'] ?? 'normal';
                if (!$item_year && !$item_title && !$item_text) continue;
            ?>
                <div class="cms-timeline-item" data-emphasis="<?php echo esc_attr($item_emphasis); ?>">
                    <div class="cms-timeline-badge-col">
                        <span class="cms-timeline-badge"><?php echo esc_html($item_year ?: '•'); ?></span>
                    </div>
                    <div class="cms-timeline-item-content">
                        <?php if ($item_title) : ?>
                            <h3><?php echo esc_html($item_title); ?></h3>
                        <?php endif; ?>
                        <?php if ($item_text) : ?>
                            <p><?php echo esc_html($item_text); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
