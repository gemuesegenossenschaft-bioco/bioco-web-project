<?php
/**
 * Waiting-list form block render template (W10, issue #97).
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Fields mirror .wp-refs/WaitingListForm.tsx. Submits to
 * POST /wp-json/bioco/v1/waiting-list, which mails the request immediately
 * (no double-opt-in — see the bioco-forms mu-plugin docblock).
 */

if (!defined('ABSPATH')) exit;

$title = get_field('title');
$text = get_field('text');

bioco_forms_localize_block('bioco/waiting-list-form', 'biocoWaitingListFormConfig', 'waiting-list');

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'warteliste-anmeldung';
$class_name = 'cms-section cms-waiting-list-form';
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

    <form class="waiting-list-form bioco-form" data-form="waiting-list" data-config="biocoWaitingListFormConfig" novalidate>
        <div class="form-group">
            <label for="waiting_name">Name *</label>
            <input type="text" id="waiting_name" name="name" required>
        </div>

        <div class="form-group">
            <label for="waiting_email">E-Mail *</label>
            <input type="email" id="waiting_email" name="email" required>
        </div>

        <div class="form-group">
            <label for="waiting_phone">Telefon *</label>
            <input type="tel" id="waiting_phone" name="phone" required>
        </div>

        <div class="form-group">
            <label for="waiting_interest">Interesse an *</label>
            <select id="waiting_interest" name="interest" required>
                <option value="">Bitte wählen...</option>
                <option value="program1">Programm 1</option>
                <option value="program2">Programm 2</option>
                <option value="program3">Programm 3</option>
            </select>
        </div>

        <div class="form-group">
            <label for="waiting_notes">Anmerkungen</label>
            <textarea id="waiting_notes" name="notes" rows="4"></textarea>
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
