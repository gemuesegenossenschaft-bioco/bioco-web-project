<?php
/**
 * Cards grid block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's CardsGridBlock, but with an explicit per-card
 * sub-repeater (title, text, image, href) instead of deriving card titles
 * from image alt text.
 */

if (!defined('ABSPATH')) exit;

$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$cards = get_field('cards');
$container_width = get_field('container_width') ?: 'xl';
$columns_desktop = get_field('columns_desktop') ?: '3';
$columns_mobile = get_field('columns_mobile') ?: '1';
$card_style = get_field('card_style') ?: 'soft';
$media_ratio = get_field('media_ratio') ?: '3:4';
$media_fit = get_field('media_fit') ?: 'cover';
$gap = get_field('gap') ?: 'lg';
$rounded = get_field('rounded') ?: 'md';

if ($is_preview && !$title && !$text && empty($cards)) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'cards-grid';

$class_name = 'cms-section cms-cards-grid';
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
        <div class="cms-section-text"><?php echo bioco_kses_rich_text($text); ?></div>
    <?php endif; ?>
    <?php if (!empty($cards)) : ?>
        <div
            class="cms-cards-grid-items"
            data-columns-desktop="<?php echo esc_attr($columns_desktop); ?>"
            data-columns-mobile="<?php echo esc_attr($columns_mobile); ?>"
            data-gap="<?php echo esc_attr($gap); ?>"
        >
            <?php foreach ($cards as $card) :
                $card_title = $card['title'] ?? '';
                $card_text = $card['text'] ?? '';
                $card_image = $card['image'] ?? null;
                $card_image_alt_override = $card['image_alt'] ?? '';
                $card_href = $card['href'] ?? '';
                $card_image_url = is_array($card_image) ? ($card_image['url'] ?? '') : '';
                $card_image_alt = $card_image_alt_override ?: (is_array($card_image) ? ($card_image['alt'] ?? '') : $card_title);
                if (!$card_title && !$card_image_url) continue;
                $card_tag = $card_href ? 'a' : 'div';
            ?>
                <<?php echo $card_tag; ?>
                    class="cms-card cms-card--<?php echo esc_attr($card_style); ?>"
                    data-media-ratio="<?php echo esc_attr($media_ratio); ?>"
                    data-media-fit="<?php echo esc_attr($media_fit); ?>"
                    data-rounded="<?php echo esc_attr($rounded); ?>"
                    <?php if ($card_href) : ?>href="<?php echo esc_url($card_href); ?>"<?php endif; ?>
                >
                    <?php if ($card_image_url) : ?>
                        <div class="cms-card-media">
                            <img
                                src="<?php echo esc_url($card_image_url); ?>"
                                alt="<?php echo esc_attr($card_image_alt); ?>"
                                loading="lazy"
                            />
                        </div>
                    <?php endif; ?>
                    <?php if ($card_title) : ?>
                        <h3 class="cms-card-title"><?php echo esc_html($card_title); ?></h3>
                    <?php endif; ?>
                    <?php if ($card_text) : ?>
                        <p class="cms-card-text"><?php echo esc_html($card_text); ?></p>
                    <?php endif; ?>
                </<?php echo $card_tag; ?>>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
