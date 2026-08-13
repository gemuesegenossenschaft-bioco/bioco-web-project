<?php
/**
 * Video embed block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's VideoSection (cms-video / cms-video-frame), but
 * uses WordPress' built-in oEmbed instead of hand-rolled YouTube/Vimeo
 * URL parsing.
 */

if (!defined('ABSPATH')) exit;

$video_url = get_field('video_url');
$video_title = get_field('video_title');
$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$buttons = get_field('buttons');

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'video';

$class_name = 'cms-section cms-video';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

// Graceful empty state: on the live site, render nothing when there is no
// usable video (mirrors VideoSection returning null). In the block editor
// preview, show a gentle placeholder instead of a blank block.
if (!$video_url) {
    if (!$is_preview) {
        return;
    }
    ?>
    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
        <p class="cms-section-caption"><?php esc_html_e('Video-URL eingeben …', 'bioco'); ?></p>
    </section>
    <?php
    return;
}

$embed_html = wp_oembed_get($video_url, ['width' => 1200]);

if (!$embed_html) {
    if (!$is_preview) {
        return;
    }
    ?>
    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
        <p class="cms-section-caption"><?php esc_html_e('Für diese URL konnte kein Video gefunden werden.', 'bioco'); ?></p>
    </section>
    <?php
    return;
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
    <?php if ($video_title) : ?>
        <p class="cms-section-caption"><?php echo esc_html($video_title); ?></p>
    <?php endif; ?>
    <div class="cms-video-frame">
        <?php echo bioco_kses_oembed_html($embed_html); ?>
    </div>
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
</section>
