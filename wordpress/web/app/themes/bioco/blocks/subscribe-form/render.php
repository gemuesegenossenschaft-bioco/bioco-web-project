<?php
/**
 * Subscribe (newsletter) form block render template (W10, issue #97).
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Fields mirror .wp-refs/SubscribeForm.tsx. Submits to
 * POST /wp-json/bioco/v1/subscribe, which starts the double-opt-in flow
 * (see bioco-forms mu-plugin) — a confirmation email is sent, the
 * bioco_subscriber entry is only created once /newsletter-bestaetigen/
 * (bioco/doi-confirm block) validates the token.
 *
 * Turnstile is required here even though the reference SubscribeForm.tsx
 * has no CaptchaField: issue #97 asks every handler to verify Turnstile
 * server-side, so this block adds the widget the reference lacks.
 */

if (!defined('ABSPATH')) exit;

$title = get_field('title');
$text = get_field('text');

bioco_forms_localize_block('bioco/subscribe-form', 'biocoSubscribeFormConfig', 'subscribe');

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'newsletter-anmeldung';
$class_name = 'cms-section cms-subscribe-form';
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

    <form class="subscribe-form bioco-form" data-form="subscribe" data-config="biocoSubscribeFormConfig" novalidate>
        <div class="form-group">
            <label for="subscribe_email">E-Mail-Adresse *</label>
            <input type="email" id="subscribe_email" name="email" required>
        </div>

        <div class="form-group">
            <label for="subscribe_name">Name (optional)</label>
            <input type="text" id="subscribe_name" name="name">
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

        <button type="submit" class="btn btn-primary" data-submit-label="Abonnieren" data-submitting-label="Wird gesendet …">Abonnieren</button>
    </form>
</section>
