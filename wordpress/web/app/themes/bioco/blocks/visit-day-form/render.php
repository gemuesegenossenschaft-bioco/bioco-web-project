<?php
/**
 * Visit-day form block render template (W10, issue #97).
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Fields mirror .wp-refs/VisitDayForm.tsx. Submits to
 * POST /wp-json/bioco/v1/visit-day, which mails the request immediately
 * (no double-opt-in here — see the bioco-forms mu-plugin docblock for why
 * this deviates from the reference's confirm-by-email success copy).
 */

if (!defined('ABSPATH')) exit;

$title = get_field('title');
$text = get_field('text');

bioco_forms_localize_block('bioco/visit-day-form', 'biocoVisitDayFormConfig', 'visit-day');

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'schnuppertag-anmeldung';
$class_name = 'cms-section cms-visit-day-form';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($title && !$heading_already_in_text) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if ($text) : ?>
        <div class="cms-section-text"><?php echo bioco_kses_rich_text($text); ?></div>
    <?php endif; ?>

    <div class="form-message" role="status" aria-live="polite" hidden></div>

    <form class="visit-form bioco-form" data-form="visit-day" data-config="biocoVisitDayFormConfig" novalidate>
        <div class="form-group">
            <label for="visit_name">Name *</label>
            <input type="text" id="visit_name" name="name" required>
        </div>

        <div class="form-group">
            <label for="visit_email">E-Mail *</label>
            <input type="email" id="visit_email" name="email" required>
        </div>

        <div class="form-group">
            <label for="visit_phone">Telefon *</label>
            <input type="tel" id="visit_phone" name="phone" required>
        </div>

        <div class="form-group">
            <label for="visit_date">Gewünschtes Datum *</label>
            <input type="date" id="visit_date" name="visit_date" required>
        </div>

        <div class="form-group">
            <label for="visit_participants">Anzahl Personen *</label>
            <input type="number" id="visit_participants" name="participants" min="1" value="1" required>
        </div>

        <div class="form-group">
            <label for="visit_notes">Anmerkungen</label>
            <textarea id="visit_notes" name="notes" rows="4"></textarea>
        </div>

        <div class="form-group">
            <label class="checkbox-option">
                <input type="checkbox" name="privacy_accept" required>
                Ich akzeptiere die Datenschutzbestimmungen *
            </label>
        </div>

        <div class="form-group">
            <div class="cf-turnstile" data-form-captcha></div>
        </div>

        <button type="submit" class="btn btn-primary" data-submit-label="Anmelden" data-submitting-label="Wird gesendet …">Anmelden</button>
    </form>
</section>
