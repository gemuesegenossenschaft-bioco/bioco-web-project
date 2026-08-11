<?php
/**
 * Group-cards block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors GroupCardsSection.tsx, SSR from the Gruppe CPT instead of the
 * client-side /api/content/groups fetch. GroupCardsSection has no class names
 * (inline styles only), so this introduces new .cms-group-card* classes
 * matching those inline style values (see W5's portal-tile precedent).
 */

if (!defined('ABSPATH')) exit;

$limit = (int) (get_field('limit') ?: -1);
$empty_message = get_field('empty_message');

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'group-cards';
$class_name = 'cms-section cms-group-cards';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$groups_query = new WP_Query([
    'post_type' => 'bioco_group',
    'post_status' => 'publish',
    'posts_per_page' => $limit,
    'orderby' => 'menu_order title',
    'order' => 'ASC',
]);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($groups_query->have_posts()) : ?>
        <div class="cms-group-cards-items">
            <?php while ($groups_query->have_posts()) :
                $groups_query->the_post();
                $post_id = get_the_ID();
                $title = get_the_title($post_id);
                $text = get_field('group_text', $post_id);
                $image = get_field('group_image', $post_id);
                $contact = get_field('group_contact', $post_id);
            ?>
                <div class="cms-group-card">
                    <div class="cms-group-card-media">
                        <?php if (!empty($image['url'])) : ?>
                            <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt'] ?: $title); ?>" loading="lazy" />
                        <?php else : ?>
                            <span class="cms-group-card-media-fallback">🌿</span>
                        <?php endif; ?>
                    </div>
                    <div class="cms-group-card-body">
                        <?php if ($title) : ?><h3 class="cms-group-card-title"><?php echo esc_html($title); ?></h3><?php endif; ?>
                        <?php if ($text) : ?><div class="cms-group-card-text"><?php echo bioco_kses_rich_text($text); ?></div><?php endif; ?>
                        <?php if ($contact) : ?><p class="cms-group-card-contact"><?php echo esc_html($contact); ?></p><?php endif; ?>
                    </div>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    <?php else : ?>
<?php if ($empty_message) : ?>        <p style="color: var(--wp--preset--color--bioco-text-muted);"><?php echo esc_html($empty_message); ?></p><?php endif; ?>
    <?php endif; ?>
</section>
