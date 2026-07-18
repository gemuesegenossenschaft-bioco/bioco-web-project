<?php
/**
 * Accordion block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * A single block groups all entries in one .demeter-accordion wrapper
 * (matching /gemuese, where several details share one accordion context),
 * replacing the former one-details-per-block accordion_item pattern.
 */

if (!defined('ABSPATH')) exit;

$items = get_field('items');

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'accordion';

$class_name = 'cms-section demeter-accordion';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

if (empty($items)) {
    if (!$is_preview) {
        return;
    }
    ?>
    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
        <p class="cms-section-caption"><?php esc_html_e('Akkordeon-Einträge hinzufügen …', 'bioco'); ?></p>
    </section>
    <?php
    return;
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php foreach ($items as $item) :
        $item_title = $item['title'] ?? '';
        $item_body = $item['body'] ?? '';
        if (!$item_title && !$item_body) continue;
    ?>
        <details>
            <summary><?php echo esc_html($item_title); ?></summary>
            <?php if ($item_body) : ?>
                <div><?php echo bioco_kses_rich_text($item_body); ?></div>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>
