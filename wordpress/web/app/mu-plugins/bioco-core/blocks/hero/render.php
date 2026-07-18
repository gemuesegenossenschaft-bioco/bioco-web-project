<?php
/**
 * Hero block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 */

if (!defined('ABSPATH')) exit;

$headline = get_field('headline');
$subtitle = get_field('subtitle');
$image = get_field('image');
$image_alt_override = get_field('image_alt');
$buttons = get_field('buttons');

if ($is_preview && !$headline) {
    $headline = __('Headline eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'hero';

$class_name = 'hero';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$image_url = is_array($image) ? ($image['url'] ?? '') : '';
$image_alt = $image_alt_override ?: (is_array($image) ? ($image['alt'] ?? '') : '');
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <div class="hero-container">
        <div class="hero-text-card bento-card">
            <div class="hero-content">
                <div class="hero-text">
                    <?php if ($subtitle) : ?>
                        <p class="hero-subtitle"><?php echo esc_html($subtitle); ?></p>
                    <?php endif; ?>
                    <?php if ($headline) : ?>
                        <h1 class="hero-title"><?php echo esc_html($headline); ?></h1>
                    <?php endif; ?>
                    <?php if (!empty($buttons)) : ?>
                        <div class="hero-buttons">
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
            </div>
        </div>
        <?php if ($image_url) : ?>
            <div class="hero-image-card bento-card">
                <div class="hero-image-container">
                    <img
                        src="<?php echo esc_url($image_url); ?>"
                        alt="<?php echo esc_attr($image_alt); ?>"
                        loading="eager"
                        fetchpriority="high"
                    />
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
