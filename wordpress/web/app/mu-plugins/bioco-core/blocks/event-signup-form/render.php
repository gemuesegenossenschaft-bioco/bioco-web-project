<?php
/**
 * Event-signup form block render template (W10, issue #97).
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Fields mirror .wp-refs/EventSignupForm.tsx. When placed on an 'event'
 * singular page (the CPT from W8, issue #95) the event title/ID hidden
 * inputs are filled automatically; otherwise they stay empty and the
 * submission is still accepted (a generic signup with no event context).
 * Submits to POST /wp-json/bioco/v1/event-signup.
 */

if (!defined('ABSPATH')) exit;

$title = bioco_field('title');
$text = bioco_field('text');
$event_title_prefix = bioco_field('event_title_prefix');
$name_label = bioco_field('name_label');
$email_label = bioco_field('email_label');
$phone_label = bioco_field('phone_label');
$notes_label = bioco_field('notes_label');
$submit_label = bioco_field('submit_label');
$submitting_label = bioco_field('submitting_label');

bioco_forms_localize_block('bioco/event-signup-form', 'biocoEventSignupFormConfig', 'event-signup');

$event_id = '';
$event_title = '';
if (is_singular('event')) {
    $event_id = (string) get_the_ID();
    $event_title = get_the_title();
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'event-anmeldung';
$class_name = 'cms-section cms-event-signup-form';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($title && !$heading_already_in_text) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php elseif ($event_title) : ?>
        <?php if ($event_title_prefix) : ?><h2><?php echo esc_html($event_title_prefix); ?> <?php echo esc_html($event_title); ?></h2><?php endif; ?>
    <?php endif; ?>
    <?php if ($text) : ?>
        <div class="cms-section-text"><?php echo bioco_kses_rich_text($text); ?></div>
    <?php endif; ?>

    <div class="form-message" role="status" aria-live="polite" hidden></div>

    <form class="event-signup-form bioco-form" data-form="event-signup" data-config="biocoEventSignupFormConfig" novalidate>
        <input type="hidden" name="eventId" value="<?php echo esc_attr($event_id); ?>">
        <input type="hidden" name="eventTitle" value="<?php echo esc_attr($event_title); ?>">

        <div class="form-group">
            <?php if ($name_label) : ?><label for="event_signup_name"><?php echo esc_html($name_label); ?></label><?php endif; ?>
            <input type="text" id="event_signup_name" name="name" required>
        </div>

        <div class="form-group">
            <?php if ($email_label) : ?><label for="event_signup_email"><?php echo esc_html($email_label); ?></label><?php endif; ?>
            <input type="email" id="event_signup_email" name="email" required>
        </div>

        <div class="form-group">
            <?php if ($phone_label) : ?><label for="event_signup_phone"><?php echo esc_html($phone_label); ?></label><?php endif; ?>
            <input type="tel" id="event_signup_phone" name="phone">
        </div>

        <div class="form-group">
            <?php if ($notes_label) : ?><label for="event_signup_notes"><?php echo esc_html($notes_label); ?></label><?php endif; ?>
            <textarea id="event_signup_notes" name="notes" rows="4"></textarea>
        </div>

        <div class="form-group">
            <div class="cf-turnstile" data-form-captcha></div>
        </div>

        <?php if ($submit_label) : ?><button type="submit" class="btn btn-primary" data-submit-label="<?php echo esc_attr($submit_label); ?>"<?php if ($submitting_label) : ?> data-submitting-label="<?php echo esc_attr($submitting_label); ?>"<?php endif; ?>><?php echo esc_html($submit_label); ?></button><?php endif; ?>
    </form>
</section>
