<?php
/**
 * Pricing-calculator block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * SSR shell mirrors PricingCalculator.tsx's markup/classes for the first
 * (non-"kein") tier; view.js reimplements the tier-select + additional-shares
 * arithmetic in vanilla JS and keeps the DOM in sync via data-pc-* hooks.
 * A fourth "Kein Abo" (shares only) option is always appended — it is a
 * fixed zero-price case in the reference component, not editorial content.
 */

if (!defined('ABSPATH')) exit;

$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$share_price = (int) (get_field('share_price') ?: 250);
$work_suffix = get_field('work_suffix');
$signup_url = get_field('signup_url');
$shares_only_label = get_field('shares_only_label');
$annual_contribution_label = get_field('annual_contribution_label');
$work_label = get_field('work_label');
$one_time_payment_label = get_field('one_time_payment_label');
$additional_shares_label = get_field('additional_shares_label');
$cta_label = get_field('cta_label');
$cta_note = get_field('cta_note');
$recommended_label = get_field('recommended_label');
$no_subscription_label = get_field('no_subscription_label');
$article_label = get_field('article_label');
$quantity_label = get_field('quantity_label');
$unit_price_label = get_field('unit_price_label');
$total_label = get_field('total_label');
$share_certificates_label = get_field('share_certificates_label');
$required_label = get_field('required_label');
$shares_only_info = get_field('shares_only_info');
$without_basket_label = get_field('without_basket_label');
$additional_label = get_field('additional_label');
$tiers = get_field('tiers');
$tiers = is_array($tiers) ? array_values(array_filter($tiers, function ($tier) {
    return !empty($tier['name']);
})) : [];

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'pricing-calculator';
$class_name = 'cms-section cms-pricing-calculator';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);

$first_tier = !empty($tiers) ? $tiers[0] : [];
$first_name = $first_tier['name'] ?? '';
$first_slug = sanitize_title($first_name);
$first_price = (int) ($first_tier['price'] ?? 0);
$first_shares = (int) ($first_tier['shares'] ?? 0);
$first_work = (int) ($first_tier['work'] ?? 0);
$shares_total = $first_shares * $share_price;
$grand_total = $first_price + $shares_total;
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($eyebrow) : ?>
        <p class="cms-section-eyebrow"><?php echo esc_html($eyebrow); ?></p>
    <?php endif; ?>
    <?php if ($title && !$heading_already_in_text) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if ($text) : ?>
        <div class="cms-section-text"><?php echo bioco_kses_rich_text($text); ?></div>
    <?php endif; ?>

    <?php if (!empty($tiers)) : ?>
        <div class="pricing-calculator" data-share-price="<?php echo esc_attr($share_price); ?>" data-signup-url="<?php echo esc_attr($signup_url); ?>" data-work-suffix="<?php echo esc_attr($work_suffix); ?>" data-additional-shares="0" data-active-tier="<?php echo esc_attr($first_slug); ?>" data-required-label="<?php echo esc_attr($required_label); ?>" data-without-basket-label="<?php echo esc_attr($without_basket_label); ?>" data-additional-label="<?php echo esc_attr($additional_label); ?>">
        <div class="abo-selector">
            <div class="abo-buttons">
                <?php foreach ($tiers as $index => $tier) :
                    $tier_name = $tier['name'] ?? '';
                    $tier_slug = sanitize_title($tier_name);
                    $tier_price = (int) ($tier['price'] ?? 0);
                    $tier_shares = (int) ($tier['shares'] ?? 0);
                    $tier_work = (int) ($tier['work'] ?? 0);
                    $tier_recommended = !empty($tier['recommended']);
                ?>
                    <button
                        type="button"
                        class="abo-button<?php echo $index === 0 ? ' active' : ''; ?>"
                        data-pc-action="select-tier"
                        data-tier-slug="<?php echo esc_attr($tier_slug); ?>"
                        data-tier-name="<?php echo esc_attr($tier_name); ?>"
                        data-tier-price="<?php echo esc_attr($tier_price); ?>"
                        data-tier-shares="<?php echo esc_attr($tier_shares); ?>"
                        data-tier-work="<?php echo esc_attr($tier_work); ?>"
                        style="position: relative;"
                    >
                        <?php echo esc_html($tier_name); ?><br />
                        <span class="price">CHF <?php echo esc_html(number_format_i18n($tier_price)); ?>.-</span>
                        <?php if ($tier_recommended && $recommended_label) : ?>
                            <span class="recommended-badge" style="position: absolute; top: -8px; right: -8px;"><?php echo esc_html($recommended_label); ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
<?php if ($no_subscription_label) : ?>                <button
                    type="button"
                    class="abo-button"
                    data-pc-action="select-tier"
                    data-tier-slug="kein"
                    data-tier-name="<?php echo esc_attr($no_subscription_label); ?>"
                    data-tier-price="0"
                    data-tier-shares="0"
                    data-tier-work="0"
                >
                    <?php echo esc_html($no_subscription_label); ?><br />
<?php if ($shares_only_label) : ?>                    <span class="price"><?php echo esc_html($shares_only_label); ?></span><?php endif; ?>
                </button><?php endif; ?>
            </div>
        </div>

        <div class="pricing-table">
            <table>
                <thead>
                    <tr>
<?php if ($article_label) : ?>                        <th><?php echo esc_html($article_label); ?></th><?php endif; ?>
<?php if ($quantity_label) : ?>                        <th><?php echo esc_html($quantity_label); ?></th><?php endif; ?>
<?php if ($unit_price_label) : ?>                        <th><?php echo esc_html($unit_price_label); ?></th><?php endif; ?>
                        <?php if ($total_label) : ?><th><?php echo esc_html($total_label); ?></th><?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr data-pc-row="abo">
                        <td>
                            <span data-pc-field="abo-name"><?php echo esc_html($first_name); ?></span>
                            <span class="payment-type-label" style="display: block; margin-top: 4px;">
<?php if ($annual_contribution_label) : ?>                                <strong style="color: var(--wp--preset--color--bioco-green); font-size: 0.875rem;"><?php echo esc_html($annual_contribution_label); ?></strong><?php endif; ?>
                            </span>
                            <span class="mitarbeit-info" style="display: block; margin-top: 8px; font-size: 0.875rem; color: var(--wp--preset--color--bioco-text-muted);">
<?php if ($work_label) : ?>                                <strong><?php echo esc_html($work_label); ?></strong><?php endif; ?> <span data-pc-field="abo-work"><?php echo esc_html($first_work); ?></span> <?php echo esc_html($work_suffix); ?>
                            </span>
                        </td>
                        <td>1</td>
                        <td>CHF <span data-pc-field="abo-price"><?php echo esc_html(number_format_i18n($first_price)); ?></span>.-</td>
                        <td><strong>CHF <span data-pc-field="abo-price-total"><?php echo esc_html(number_format_i18n($first_price)); ?></span>.-</strong></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>
<?php if ($share_certificates_label) : ?>                            <?php echo esc_html($share_certificates_label); ?><?php endif; ?>
                            <span class="payment-type-label" style="display: block; margin-top: 4px;">
<?php if ($one_time_payment_label) : ?>                                <strong style="color: var(--wp--preset--color--bioco-beet); font-size: 0.875rem;"><?php echo esc_html($one_time_payment_label); ?></strong><?php endif; ?>
                            </span>
                            <span class="text-sm" data-pc-field="shares-note" style="display: block; color: var(--wp--preset--color--bioco-text-muted); margin-top: 4px;">
                                (<span data-pc-field="shares-required"><?php echo esc_html($first_shares); ?></span><?php if ($required_label) : ?> <?php echo esc_html($required_label); ?><?php endif; ?>)
                            </span>
                        </td>
                        <td data-pc-field="shares-count"><?php echo esc_html($first_shares); ?></td>
                        <td>CHF <span data-pc-field="share-price"><?php echo esc_html($share_price); ?></span>.-</td>
                        <td><strong>CHF <span data-pc-field="shares-total"><?php echo esc_html(number_format_i18n($shares_total)); ?></span>.-</strong></td>
                        <td>
                            <div class="share-buttons-container">
<?php if ($additional_shares_label) : ?>                                <div class="share-buttons-label"><strong><?php echo esc_html($additional_shares_label); ?></strong></div><?php endif; ?>
                                <div class="share-buttons">
                                    <button type="button" class="btn-remove-share" data-pc-action="remove-share" aria-label="Ein Anteilsschein entfernen" title="Ein Anteilsschein entfernen" style="display: none;">-1</button>
                                    <button type="button" class="btn-add-share" data-pc-action="add-share" aria-label="Ein zusätzlicher Anteilsschein hinzufügen" title="Ein zusätzlicher Anteilsschein hinzufügen">+1</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3"><?php if ($total_label) : ?><strong><?php echo esc_html($total_label); ?></strong><?php endif; ?></td>
                        <td colspan="2"><strong class="total-amount" data-pc-field="total">CHF <?php echo esc_html(number_format_i18n($grand_total)); ?>.-</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

<?php if ($shares_only_info) : ?>        <div class="pc-kein-info" data-pc-role="kein-info" style="margin-top: 16px; padding: 16px; background: var(--wp--preset--color--bioco-bg); border-radius: var(--wp--custom--radius--md); display: none;">
            <p style="margin: 0; font-size: 0.875rem;">
                <?php echo bioco_kses_rich_text($shares_only_info); ?>
            </p>
        </div><?php endif; ?>

        <div class="pc-actions" data-pc-role="cta-block" style="margin-top: 24px; text-align: center;">
<?php if ($signup_url && $cta_label) : ?>            <a
                class="btn btn-primary"
                data-pc-field="cta"
                href="<?php echo esc_url(add_query_arg(['abo' => $first_slug, 'shares' => $first_shares, 'additional' => 0], $signup_url)); ?>"
                style="display: inline-block; font-size: 1.125rem; padding: 16px 32px;"
            ><?php echo esc_html($cta_label); ?></a><?php endif; ?>
<?php if ($cta_note) : ?>            <p style="margin-top: 12px; font-size: 0.875rem; color: var(--wp--preset--color--bioco-text-muted);"><?php echo esc_html($cta_note); ?></p><?php endif; ?>
        </div>
        </div>
    <?php endif; ?>
</section>
