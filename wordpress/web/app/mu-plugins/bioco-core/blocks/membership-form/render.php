<?php
/**
 * Membership form block render template (W10, issue #97).
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 *
 * DEFERRAL (explicitly allowed by issue #97): .wp-refs/MembershipForm.tsx is
 * a 6-step JS wizard (commitment checklist -> personal -> depot/payment ->
 * mitarbeit -> zusatzabos -> summary) with client-side step validation and a
 * sticky live price summary. Porting that step machine to dependency-free
 * ES5 was judged too large for this slice, so this block instead renders a
 * SINGLE-PAGE long-form capturing the exact same fields, sectioned with the
 * same headings/copy. No live price calculator (see bioco/pricing-calculator
 * for that, already shipped in W9) and no per-step validation — the whole
 * form is validated by the browser's native required-field handling plus
 * bioco_forms_validate_membership() server-side, which mirrors
 * .wp-refs/membership.ts validateMembership() exactly (only
 * firstName/lastName/email/address/zip/city/privacyAccept are required
 * there — depot/paymentType/mitarbeit are not server-validated in the
 * reference either).
 *
 * membershipType/aboType/additionalShares/sharesOnly default to the reference
 * form's standard tier. view.js replaces them from the pricing calculator's
 * ?abo=&shares=&additional= handoff when those parameters are present.
 */

if (!defined('ABSPATH')) exit;

$title = get_field('title');
$text = get_field('text');
$commitment_title = get_field('commitment_title');
$commitment_intro = get_field('commitment_intro');
$commitments = get_field('commitments');
$personal_title = get_field('personal_title');
$first_name_label = get_field('first_name_label');
$last_name_label = get_field('last_name_label');
$address_label = get_field('address_label');
$zip_label = get_field('zip_label');
$city_label = get_field('city_label');
$phone_label = get_field('phone_label');
$email_label = get_field('email_label');
$depot_payment_title = get_field('depot_payment_title');
$depot_label = get_field('depot_label');
$depot_placeholder = get_field('depot_placeholder');
$depots = get_field('depots');
$payment_label = get_field('payment_label');
$payment_hint = get_field('payment_hint');
$quarterly_label = get_field('quarterly_label');
$annual_label = get_field('annual_label');
$participation_title = get_field('participation_title');
$participation_intro = get_field('participation_intro');
$preferred_days_label = get_field('preferred_days_label');
$preferred_days = get_field('preferred_days');
$preferred_times_label = get_field('preferred_times_label');
$preferred_times = get_field('preferred_times');
$activity_areas_label = get_field('activity_areas_label');
$activity_areas = get_field('activity_areas');
$other_activity_label = get_field('other_activity_label');
$additional_subscriptions_title = get_field('additional_subscriptions_title');
$additional_subscriptions_intro = get_field('additional_subscriptions_intro');
$additional_subscriptions = get_field('additional_subscriptions');
$additional_products_label = get_field('additional_products_label');
$additional_products_hint = get_field('additional_products_hint');
$additional_products_placeholder = get_field('additional_products_placeholder');
$confirmation_title = get_field('confirmation_title');
$privacy_label = get_field('privacy_label');
$submit_label = get_field('submit_label');
$submitting_label = get_field('submitting_label');

bioco_forms_localize_block('bioco/membership-form', 'biocoMembershipFormConfig', 'membership');

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'mitgliedschaft-anmeldung';
$class_name = 'cms-section cms-membership-form';
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

    <form class="membership-form bioco-form" data-form="membership" data-config="biocoMembershipFormConfig" novalidate>
        <input type="hidden" name="membershipType" value="abo">
        <input type="hidden" name="aboType" value="standard">
        <input type="hidden" name="additionalShares" value="0">
        <input type="hidden" name="sharesOnly" value="0">

        <div class="form-step">
<?php if ($commitment_title) : ?>            <h3><?php echo esc_html($commitment_title); ?></h3><?php endif; ?>
<?php if ($commitment_intro) : ?>            <p><?php echo esc_html($commitment_intro); ?></p><?php endif; ?>
            <?php if (!empty($commitments)) : ?>
                <div class="commitment-checklist">
                    <?php foreach ($commitments as $commitment) :
                        $commitment_heading = $commitment['heading'] ?? '';
                        $commitment_text = $commitment['text'] ?? '';
                        if (!$commitment_heading && !$commitment_text) continue;
                    ?>
                        <label class="commitment-item">
                            <input type="checkbox" name="commitmentAccepted[]" data-bool-array>
                            <div>
<?php if ($commitment_heading) : ?>                                <h4><?php echo esc_html($commitment_heading); ?></h4><?php endif; ?>
<?php if ($commitment_text) : ?>                                <p><?php echo bioco_kses_rich_text($commitment_text); ?></p><?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-step">
            <?php if ($personal_title) : ?><h3><?php echo esc_html($personal_title); ?></h3><?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <?php if ($first_name_label) : ?><label for="membership_first_name"><?php echo esc_html($first_name_label); ?></label><?php endif; ?>
                    <input type="text" id="membership_first_name" name="firstName" required>
                </div>
                <div class="form-group">
                    <?php if ($last_name_label) : ?><label for="membership_last_name"><?php echo esc_html($last_name_label); ?></label><?php endif; ?>
                    <input type="text" id="membership_last_name" name="lastName" required>
                </div>
            </div>
            <div class="form-group">
                <?php if ($address_label) : ?><label for="membership_address"><?php echo esc_html($address_label); ?></label><?php endif; ?>
                <input type="text" id="membership_address" name="address" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <?php if ($zip_label) : ?><label for="membership_zip"><?php echo esc_html($zip_label); ?></label><?php endif; ?>
                    <input type="text" id="membership_zip" name="zip" required>
                </div>
                <div class="form-group">
                    <?php if ($city_label) : ?><label for="membership_city"><?php echo esc_html($city_label); ?></label><?php endif; ?>
                    <input type="text" id="membership_city" name="city" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <?php if ($phone_label) : ?><label for="membership_phone"><?php echo esc_html($phone_label); ?></label><?php endif; ?>
                    <input type="tel" id="membership_phone" name="phone">
                </div>
                <div class="form-group">
                    <?php if ($email_label) : ?><label for="membership_email"><?php echo esc_html($email_label); ?></label><?php endif; ?>
                    <input type="email" id="membership_email" name="email" required>
                </div>
            </div>
        </div>

        <div class="form-step">
            <?php if ($depot_payment_title) : ?><h3><?php echo esc_html($depot_payment_title); ?></h3><?php endif; ?>
            <div class="form-group">
                <?php if ($depot_label) : ?><label for="membership_depot"><?php echo esc_html($depot_label); ?></label><?php endif; ?>
                <select id="membership_depot" name="depot" required>
                    <?php if ($depot_placeholder) : ?><option value=""><?php echo esc_html($depot_placeholder); ?></option><?php endif; ?>
                    <?php foreach ((array) $depots as $depot) :
                        $depot_option = $depot['option'] ?? '';
                        if (!$depot_option) continue;
                    ?>
                        <option value="<?php echo esc_attr($depot_option); ?>"><?php echo esc_html($depot_option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <?php if ($payment_label) : ?><label><?php echo esc_html($payment_label); ?></label><?php endif; ?>
                <?php if ($payment_hint) : ?><p class="form-hint"><?php echo esc_html($payment_hint); ?></p><?php endif; ?>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="paymentType" value="quarterly">
                        <?php if ($quarterly_label) : ?><span><?php echo esc_html($quarterly_label); ?></span><?php endif; ?>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="paymentType" value="yearly" checked>
                        <?php if ($annual_label) : ?><span><?php echo esc_html($annual_label); ?></span><?php endif; ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-step">
            <?php if ($participation_title) : ?><h3><?php echo esc_html($participation_title); ?></h3><?php endif; ?>
            <?php if ($participation_intro) : ?><p><?php echo esc_html($participation_intro); ?></p><?php endif; ?>
            <div class="form-group">
                <?php if ($preferred_days_label) : ?><label><?php echo esc_html($preferred_days_label); ?></label><?php endif; ?>
                <div class="checkbox-group">
                    <?php foreach ((array) $preferred_days as $day) :
                        $day = $day['option'] ?? '';
                        if (!$day) continue;
                    ?>
                        <label class="checkbox-option">
                            <input type="checkbox" name="preferredDays[]" value="<?php echo esc_attr($day); ?>">
                            <span><?php echo esc_html($day); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <?php if ($preferred_times_label) : ?><label><?php echo esc_html($preferred_times_label); ?></label><?php endif; ?>
                <div class="checkbox-group">
                    <?php foreach ((array) $preferred_times as $time) :
                        $time = $time['option'] ?? '';
                        if (!$time) continue;
                    ?>
                        <label class="checkbox-option">
                            <input type="checkbox" name="preferredTimes[]" value="<?php echo esc_attr($time); ?>">
                            <span><?php echo esc_html($time); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <?php if ($activity_areas_label) : ?><label><?php echo esc_html($activity_areas_label); ?></label><?php endif; ?>
                <div class="checkbox-group">
                    <?php foreach ((array) $activity_areas as $area) :
                        $area = $area['option'] ?? '';
                        if (!$area) continue;
                    ?>
                        <label class="checkbox-option">
                            <input type="checkbox" name="activityAreas[]" value="<?php echo esc_attr($area); ?>">
                            <span><?php echo esc_html($area); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <?php if ($other_activity_label) : ?><label for="membership_other_activity"><?php echo esc_html($other_activity_label); ?></label><?php endif; ?>
                <textarea id="membership_other_activity" name="otherActivity" rows="3"></textarea>
            </div>
        </div>

        <div class="form-step">
            <?php if ($additional_subscriptions_title) : ?><h3><?php echo esc_html($additional_subscriptions_title); ?></h3><?php endif; ?>
            <?php if ($additional_subscriptions_intro) : ?><p><?php echo esc_html($additional_subscriptions_intro); ?></p><?php endif; ?>
            <div class="form-group">
                <div class="checkbox-group">
                    <?php foreach ((array) $additional_subscriptions as $product) :
                        $product = $product['option'] ?? '';
                        if (!$product) continue;
                    ?>
                        <label class="checkbox-option">
                            <input type="checkbox" name="zusatzabos[]" value="<?php echo esc_attr($product); ?>">
                            <span><?php echo esc_html($product); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <?php if ($additional_products_label) : ?><label for="membership_weitere_produkte"><?php echo esc_html($additional_products_label); ?></label><?php endif; ?>
                <?php if ($additional_products_hint) : ?><p class="form-hint"><?php echo esc_html($additional_products_hint); ?></p><?php endif; ?>
                <textarea id="membership_weitere_produkte" name="weitereProdukte" rows="4"<?php if ($additional_products_placeholder) : ?> placeholder="<?php echo esc_attr($additional_products_placeholder); ?>"<?php endif; ?>></textarea>
            </div>
        </div>

        <div class="form-step">
            <?php if ($confirmation_title) : ?><h3><?php echo esc_html($confirmation_title); ?></h3><?php endif; ?>
            <div class="form-group">
                <label class="checkbox-option">
                    <input type="checkbox" name="privacyAccept" required>
                    <?php if ($privacy_label) : ?><span><?php echo esc_html($privacy_label); ?></span><?php endif; ?>
                </label>
            </div>
            <div class="form-group">
                <div class="cf-turnstile" data-form-captcha></div>
            </div>
        </div>

        <div class="form-navigation">
            <?php if ($submit_label) : ?><button type="submit" class="btn btn-primary" data-submit-label="<?php echo esc_attr($submit_label); ?>"<?php if ($submitting_label) : ?> data-submitting-label="<?php echo esc_attr($submitting_label); ?>"<?php endif; ?>><?php echo esc_html($submit_label); ?></button><?php endif; ?>
        </div>
    </form>
</section>
