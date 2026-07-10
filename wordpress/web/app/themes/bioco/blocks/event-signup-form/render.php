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

$title = get_field('title');
$text = get_field('text');

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
        <h2>Anmeldung für: <?php echo esc_html($event_title); ?></h2>
    <?php endif; ?>
    <?php if ($text) : ?>
        <div class="cms-section-text"><?php echo bioco_kses_rich_text($text); ?></div>
    <?php endif; ?>

    <div class="form-message" role="status" aria-live="polite" hidden></div>

    <form class="event-signup-form bioco-form" data-form="event-signup" data-config="biocoEventSignupFormConfig" novalidate>
        <input type="hidden" name="eventId" value="<?php echo esc_attr($event_id); ?>">
        <input type="hidden" name="eventTitle" value="<?php echo esc_attr($event_title); ?>">

        <div class="form-group">
            <label for="event_signup_name">Name *</label>
            <input type="text" id="event_signup_name" name="name" required>
        </div>

        <div class="form-group">
            <label for="event_signup_email">E-Mail *</label>
            <input type="email" id="event_signup_email" name="email" required>
        </div>

        <div class="form-group">
            <label for="event_signup_phone">Telefon (optional)</label>
            <input type="tel" id="event_signup_phone" name="phone">
        </div>

        <div class="form-group">
            <label for="event_signup_notes">Bemerkungen (optional)</label>
            <textarea id="event_signup_notes" name="notes" rows="4"></textarea>
        </div>

        <div class="form-group">
            <div class="cf-turnstile" data-form-captcha></div>
        </div>

        <button type="submit" class="btn btn-primary" data-submit-label="Anmelden" data-submitting-label="Wird gesendet …">Anmelden</button>
    </form>
</section>
