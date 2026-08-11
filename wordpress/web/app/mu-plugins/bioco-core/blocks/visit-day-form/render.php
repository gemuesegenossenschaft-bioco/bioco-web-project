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
$name_label = get_field('name_label');
$email_label = get_field('email_label');
$phone_label = get_field('phone_label');
$date_label = get_field('date_label');
$participants_label = get_field('participants_label');
$notes_label = get_field('notes_label');
$privacy_label = get_field('privacy_label');
$submit_label = get_field('submit_label');
$submitting_label = get_field('submitting_label');

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
            <?php if ($name_label) : ?><label for="visit_name"><?php echo esc_html($name_label); ?></label><?php endif; ?>
            <input type="text" id="visit_name" name="name" required>
        </div>

        <div class="form-group">
            <?php if ($email_label) : ?><label for="visit_email"><?php echo esc_html($email_label); ?></label><?php endif; ?>
            <input type="email" id="visit_email" name="email" required>
        </div>

        <div class="form-group">
            <?php if ($phone_label) : ?><label for="visit_phone"><?php echo esc_html($phone_label); ?></label><?php endif; ?>
            <input type="tel" id="visit_phone" name="phone" required>
        </div>

        <div class="form-group">
            <?php if ($date_label) : ?><label for="visit_date"><?php echo esc_html($date_label); ?></label><?php endif; ?>
            <input type="date" id="visit_date" name="visit_date" required>
        </div>

        <div class="form-group">
            <?php if ($participants_label) : ?><label for="visit_participants"><?php echo esc_html($participants_label); ?></label><?php endif; ?>
            <input type="number" id="visit_participants" name="participants" min="1" value="1" required>
        </div>

        <div class="form-group">
            <?php if ($notes_label) : ?><label for="visit_notes"><?php echo esc_html($notes_label); ?></label><?php endif; ?>
            <textarea id="visit_notes" name="notes" rows="4"></textarea>
        </div>

        <div class="form-group">
            <label class="checkbox-option">
                <input type="checkbox" name="privacy_accept" required>
<?php if ($privacy_label) : ?>                <?php echo esc_html($privacy_label); ?><?php endif; ?>
            </label>
        </div>

        <div class="form-group">
            <div class="cf-turnstile" data-form-captcha></div>
        </div>

        <?php if ($submit_label) : ?><button type="submit" class="btn btn-primary" data-submit-label="<?php echo esc_attr($submit_label); ?>"<?php if ($submitting_label) : ?> data-submitting-label="<?php echo esc_attr($submitting_label); ?>"<?php endif; ?>><?php echo esc_html($submit_label); ?></button><?php endif; ?>
    </form>
</section>
