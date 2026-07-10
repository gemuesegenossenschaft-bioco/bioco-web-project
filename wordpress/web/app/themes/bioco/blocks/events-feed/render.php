<?php
/**
 * Events-feed block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors EventsSection.tsx (variant=standard: bento card + past-events grid)
 * and EventsBanner.tsx (variant=banner: compact events-banner). Cards link
 * straight to the single event permalink instead of opening a JS modal — the
 * client-side ItemDetailModal/useEventsFeed behaviour is not ported here.
 */

if (!defined('ABSPATH')) exit;

$variant = get_field('variant') ?: 'standard';
$limit = (int) (get_field('limit') ?: 3);
$banner_title = get_field('title') ?: 'Nächste Events';

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'events-feed';
$class_name = 'cms-section cms-events-feed';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$upcoming_query = bioco_query_events('upcoming', $limit);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($variant === 'banner') : ?>
        <div class="events-banner">
            <h2><?php echo esc_html($banner_title); ?></h2>
            <?php bioco_render_events_list($upcoming_query); ?>
            <p style="margin-top: var(--wp--preset--spacing--30);">
                <a href="<?php echo esc_url(home_url('/aktuelles')); ?>">Alle Events ansehen →</a>
            </p>
        </div>
    <?php else :
        $past_query = bioco_query_events('past', 4);
    ?>
        <div class="bento-card events-card bento-card-fullwidth">
            <div class="card-header"><h3>Nächste Events</h3></div>
            <div class="card-body">
                <?php bioco_render_events_list($upcoming_query); ?>
                <a href="<?php echo esc_url(home_url('/aktuelles')); ?>" class="btn btn-primary" style="margin-top: 16px; display: inline-block;">Alle Events ansehen</a>
            </div>
        </div>
        <?php if ($past_query->have_posts()) : ?>
            <div class="bento-card past-events-card">
                <div class="card-header">
                    <h3>Vergangene Events & Eindrücke</h3>
                    <p style="margin-top: 4px; color: var(--wp--preset--color--bioco-text-muted);">Rückblicke mit Fotos und Videos aus unserer Community.</p>
                </div>
                <div class="card-body past-events-grid">
                    <?php while ($past_query->have_posts()) :
                        $past_query->the_post();
                        $post_id = get_the_ID();
                        $image = bioco_event_card_image($post_id);
                        $date_parts = bioco_event_date_parts($post_id);
                    ?>
                        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="past-event-tile">
                            <?php if ($image) : ?>
                                <div class="past-event-media">
                                    <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy" />
                                </div>
                            <?php endif; ?>
                            <div class="past-event-meta">
                                <p class="past-event-date"><?php echo esc_html($date_parts['dateLabel']); ?></p>
                                <p class="past-event-title"><?php echo esc_html(get_the_title($post_id)); ?></p>
                                <span class="past-event-cta">Rückblick ansehen →</span>
                            </div>
                        </a>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
