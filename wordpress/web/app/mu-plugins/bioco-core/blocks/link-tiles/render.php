<?php
/**
 * Link tiles block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's LinkTilesBlock: up to 4 tiles (title, text,
 * href, emoji icon), only tiles with a title render, and a tile with an
 * empty href renders a plain .portal-tile div instead of a link (matches
 * /kundenportal behavior).
 */

if (!defined('ABSPATH')) exit;

$title = get_field('title');
$items = get_field('tiles');

if ($is_preview && !$title && empty($items)) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'link-tiles';

$class_name = 'cms-section cms-link-tiles';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$tiles = [];
if (!empty($items)) {
    foreach ($items as $item) {
        $tile_title = trim($item['title'] ?? '');
        if (!$tile_title) continue;
        $tiles[] = [
            'title' => $tile_title,
            'text' => trim($item['text'] ?? ''),
            'href' => trim($item['href'] ?? ''),
            'icon' => trim($item['icon'] ?? ''),
        ];
    }
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($title) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if (!empty($tiles)) : ?>
        <div class="portal-gateway">
            <?php foreach ($tiles as $tile) :
                $tile_tag = $tile['href'] ? 'a' : 'div';
            ?>
                <<?php echo $tile_tag; ?>
                    class="portal-tile"
                    <?php if ($tile['href']) : ?>href="<?php echo esc_url($tile['href']); ?>"<?php endif; ?>
                >
                    <?php if ($tile['icon']) : ?>
                        <div class="portal-icon"><?php echo esc_html($tile['icon']); ?></div>
                    <?php endif; ?>
                    <h3><?php echo esc_html($tile['title']); ?></h3>
                    <?php if ($tile['text']) : ?>
                        <p><?php echo esc_html($tile['text']); ?></p>
                    <?php endif; ?>
                </<?php echo $tile_tag; ?>>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
