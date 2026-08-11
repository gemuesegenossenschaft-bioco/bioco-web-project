<?php
/**
 * Contact form block render template (W10, issue #97).
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Fields mirror .wp-refs/ContactForm.tsx; submit goes to
 * POST /wp-json/bioco/v1/contact (see bioco-forms mu-plugin).
 */

if (!defined('ABSPATH')) exit;

$title = get_field('title');
$text = get_field('text');
$phone_label = get_field('phone_label');
$submit_label = get_field('submit_label');
$submitting_label = get_field('submitting_label');

bioco_forms_localize_block('bioco/contact-form', 'biocoContactFormConfig', 'contact');

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'kontakt-formular';
$class_name = 'cms-section cms-contact-form';
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

    <form class="contact-form bioco-form" data-form="contact" data-config="biocoContactFormConfig" novalidate>
        <div class="form-group">
            <label for="contact_name">Name *</label>
            <input type="text" id="contact_name" name="name" required>
        </div>

        <div class="form-group">
            <label for="contact_email">E-Mail *</label>
            <input type="email" id="contact_email" name="email" required>
        </div>

        <div class="form-group">
            <?php if ($phone_label) : ?><label for="contact_phone"><?php echo esc_html($phone_label); ?></label><?php endif; ?>
            <input type="tel" id="contact_phone" name="phone">
        </div>

        <div class="form-group">
            <label for="contact_subject">Betreff *</label>
            <input type="text" id="contact_subject" name="subject" required>
        </div>

        <div class="form-group">
            <label for="contact_message">Nachricht *</label>
            <textarea id="contact_message" name="message" rows="6" required></textarea>
        </div>

        <div class="form-group">
            <div class="cf-turnstile" data-form-captcha></div>
        </div>

        <button type="submit" class="btn btn-primary btn-organic" data-submit-label="<?php echo esc_attr($submit_label); ?>" data-submitting-label="<?php echo esc_attr($submitting_label); ?>"><?php echo esc_html($submit_label); ?></button>
    </form>
</section>
