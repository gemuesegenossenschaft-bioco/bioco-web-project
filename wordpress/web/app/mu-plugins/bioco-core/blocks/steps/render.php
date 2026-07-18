<?php
/**
 * Steps block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's StepsBlock: up to 4 numbered steps, numbering
 * rendered at render time (1..n) rather than stored, .next-steps/.step-item/
 * .step-number markup kept identical to the Next.js CSS.
 */

if (!defined('ABSPATH')) exit;

$title = get_field('title');
$items = get_field('items');

if ($is_preview && !$title && empty($items)) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'steps';

$class_name = 'cms-section cms-steps';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$steps = [];
if (!empty($items)) {
    foreach ($items as $item) {
        $step_title = trim($item['title'] ?? '');
        $step_text = trim($item['text'] ?? '');
        if (!$step_title && !$step_text) continue;
        $steps[] = ['title' => $step_title, 'text' => $step_text];
    }
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($title) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if (!empty($steps)) : ?>
        <div class="next-steps">
            <?php foreach ($steps as $index => $step) : ?>
                <div class="step-item">
                    <div class="step-number"><?php echo esc_html((string) ($index + 1)); ?></div>
                    <div>
                        <?php if ($step['title']) : ?>
                            <h3><?php echo esc_html($step['title']); ?></h3>
                        <?php endif; ?>
                        <?php if ($step['text']) : ?>
                            <p><?php echo esc_html($step['text']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
