<?php
/**
 * Waiting-list form block render template (W10, issue #97).
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Fields mirror .wp-refs/WaitingListForm.tsx. Submits to
 * POST /wp-json/bioco/v1/waiting-list, which mails the request immediately
 * (no double-opt-in — see the bioco-forms mu-plugin docblock).
 */

if (!defined('ABSPATH')) exit;

$title = bioco_field('title');
$text = bioco_field('text');
$name_label = bioco_field('name_label');
$email_label = bioco_field('email_label');
$phone_label = bioco_field('phone_label');
$interest_label = bioco_field('interest_label');
$interest_placeholder = bioco_field('interest_placeholder');
$interest_options = bioco_field('interest_options');
$notes_label = bioco_field('notes_label');
$privacy_label = bioco_field('privacy_label');
$submit_label = bioco_field('submit_label');
$submitting_label = bioco_field('submitting_label');
$formStrings = [
    'successMessage' => (string) bioco_field('success_message'),
    'fallbackError' => (string) bioco_field('fallback_error'),
    'captchaError' => (string) bioco_field('captcha_error'),
];
bioco_forms_localize_block('bioco/waiting-list-form', 'biocoWaitingListFormConfig', 'waiting-list', $formStrings);

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
            <?php if ($name_label) : ?><label for="waiting_name"><?php echo esc_html($name_label); ?></label><?php endif; ?>
            <input type="text" id="waiting_name" name="name" required>
        </div>

        <div class="form-group">
            <?php if ($email_label) : ?><label for="waiting_email"><?php echo esc_html($email_label); ?></label><?php endif; ?>
            <input type="email" id="waiting_email" name="email" required>
        </div>

        <div class="form-group">
            <?php if ($phone_label) : ?><label for="waiting_phone"><?php echo esc_html($phone_label); ?></label><?php endif; ?>
            <input type="tel" id="waiting_phone" name="phone" required>
        </div>

        <div class="form-group">
            <?php if ($interest_label) : ?><label for="waiting_interest"><?php echo esc_html($interest_label); ?></label><?php endif; ?>
            <select id="waiting_interest" name="interest" required>
<?php if ($interest_placeholder) : ?>                <option value=""><?php echo esc_html($interest_placeholder); ?></option><?php endif; ?>
                <?php foreach ((array) $interest_options as $interest_option) :
                    $interest_value = $interest_option['value'] ?? '';
                    $interest_option_label = $interest_option['label'] ?? '';
                    if (!$interest_value || !$interest_option_label) continue;
                ?>
                    <option value="<?php echo esc_attr($interest_value); ?>"><?php echo esc_html($interest_option_label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <?php if ($notes_label) : ?><label for="waiting_notes"><?php echo esc_html($notes_label); ?></label><?php endif; ?>
            <textarea id="waiting_notes" name="notes" rows="4"></textarea>
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
