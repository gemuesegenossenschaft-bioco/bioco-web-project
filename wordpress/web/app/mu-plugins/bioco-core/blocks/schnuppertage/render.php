<?php
/**
 * Schnuppertage block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SchnuppertageSection.tsx: editorial title/text + a "Was dich
 * erwartet" list (list_label/items/closing) stay CMS-driven, the Nächste-
 * Termine list is live from the Veranstaltung CPT (event_type=schnuppertag).
 * The signup modal (EventSignupForm) is deferred to W10 (forms) — each
 * upcoming date's "Jetzt anmelden" button links to signup_anchor instead.
 */

if (!defined('ABSPATH')) exit;

$title = bioco_field('title');
$text = bioco_field('text');
$list_label = bioco_field('list_label');
$items = bioco_field('items');
$closing = bioco_field('closing');
$signup_anchor = bioco_field('signup_anchor');
$limit = (int) (bioco_field('limit') ?: 3);
$schedule_title = bioco_field('schedule_title');
$signup_label = bioco_field('signup_label');
$empty_message = bioco_field('empty_message');
$display = bioco_field('display');

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'schnuppertage';
$class_name = 'cms-section cms-schnuppertage';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);

$respect_stored_status = (bool) bioco_field('respect_stored_status');
$termine_query = bioco_query_events('upcoming', $limit, 'schnuppertag', $respect_stored_status);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($title && !$heading_already_in_text) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if ($text) : ?>
        <div class="cms-section-text" style="font-size: 1.05rem; line-height: 1.7; color: var(--wp--preset--color--bioco-text-muted); margin-bottom: 16px;"><?php echo bioco_kses_rich_text($text); ?></div>
    <?php endif; ?>

    <?php if ($list_label || !empty($items) || $closing) : ?>
        <div class="cms-schnuppertage-info-box">
            <?php if ($list_label) : ?>
                <p class="cms-schnuppertage-list-label"><strong><?php echo esc_html($list_label); ?></strong></p>
            <?php endif; ?>
            <?php if (!empty($items)) : ?>
                <ul class="cms-schnuppertage-items">
                    <?php foreach ($items as $item) :
                        $item_text = $item['text'] ?? '';
                        if (!$item_text) continue;
                    ?>
                        <li><?php echo esc_html($item_text); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($closing) : ?>
                <div class="cms-schnuppertage-closing"><?php echo bioco_kses_rich_text($closing); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php if ($schedule_title) : ?>    <h4 class="cms-schnuppertage-termine-heading"><?php echo esc_html($schedule_title); ?></h4><?php endif; ?>
    <?php if ($display === 'cards') : ?>
        <?php bioco_render_events_list($termine_query, $empty_message); ?>
    <?php else : ?>
    <div class="cms-schnuppertage-termine">
        <?php if ($termine_query->have_posts()) : ?>
            <?php while ($termine_query->have_posts()) :
                $termine_query->the_post();
                $post_id = get_the_ID();
                $date_parts = bioco_event_date_parts($post_id);
            ?>
                <div class="cms-schnuppertage-termin">
                    <div class="cms-schnuppertage-termin-meta">
                        <strong><?php echo esc_html(get_the_title($post_id)); ?></strong>
                        <span>
                            <?php echo esc_html($date_parts['dateLabel']); ?>
                            <?php if ($date_parts['timeLabel']) : ?> · <?php echo esc_html($date_parts['timeLabel']); ?><?php endif; ?>
                        </span>
                    </div>
<?php if ($signup_anchor && $signup_label) : ?>                    <a href="<?php echo esc_url($signup_anchor); ?>" class="btn btn-primary btn-organic"><?php echo esc_html($signup_label); ?></a><?php endif; ?>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
        <?php else : ?>
<?php if ($empty_message) : ?>            <p style="color: var(--wp--preset--color--bioco-text-muted);"><?php echo esc_html($empty_message); ?></p><?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
