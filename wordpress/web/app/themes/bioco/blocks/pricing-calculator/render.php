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
$work_suffix = get_field('work_suffix') ?: 'Arbeitseinsätze à 2 Stunden';
$signup_url = get_field('signup_url') ?: '/mitmachen';
$tiers = get_field('tiers');

if (empty($tiers)) {
    $tiers = [
        ['name' => 'Halb (1 Person)', 'price' => 750, 'shares' => 1, 'work' => 10, 'recommended' => false],
        ['name' => 'Standard (2-3 Personen)', 'price' => 1280, 'shares' => 2, 'work' => 20, 'recommended' => true],
        ['name' => 'Doppel (4-6 Personen)', 'price' => 2350, 'shares' => 4, 'work' => 40, 'recommended' => false],
    ];
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'pricing-calculator';
$class_name = 'cms-section cms-pricing-calculator';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);

$first_tier = $tiers[0];
$first_slug = sanitize_title($first_tier['name']);
$first_price = (int) $first_tier['price'];
$first_shares = (int) $first_tier['shares'];
$first_work = (int) $first_tier['work'];
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

    <div class="pricing-calculator" data-share-price="<?php echo esc_attr($share_price); ?>" data-signup-url="<?php echo esc_attr($signup_url); ?>" data-work-suffix="<?php echo esc_attr($work_suffix); ?>" data-additional-shares="0" data-active-tier="<?php echo esc_attr($first_slug); ?>">
        <div class="abo-selector">
            <div class="abo-buttons">
                <?php foreach ($tiers as $index => $tier) :
                    $tier_name = $tier['name'] ?? '';
                    if (!$tier_name) continue;
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
                        <?php if ($tier_recommended) : ?>
                            <span class="recommended-badge" style="position: absolute; top: -8px; right: -8px;">Empfohlen</span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
                <button
                    type="button"
                    class="abo-button"
                    data-pc-action="select-tier"
                    data-tier-slug="kein"
                    data-tier-name="Kein Abo"
                    data-tier-price="0"
                    data-tier-shares="0"
                    data-tier-work="0"
                >
                    Kein Abo<br />
                    <span class="price">Nur Anteilsscheine</span>
                </button>
            </div>
        </div>

        <div class="pricing-table">
            <table>
                <thead>
                    <tr>
                        <th>Artikel</th>
                        <th>Anzahl</th>
                        <th>Einzelpreis</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr data-pc-row="abo">
                        <td>
                            <span data-pc-field="abo-name"><?php echo esc_html($first_tier['name']); ?></span>
                            <span class="payment-type-label" style="display: block; margin-top: 4px;">
                                <strong style="color: var(--wp--preset--color--bioco-green); font-size: 0.875rem;">Jährlicher Beitrag</strong>
                            </span>
                            <span class="mitarbeit-info" style="display: block; margin-top: 8px; font-size: 0.875rem; color: var(--wp--preset--color--bioco-text-muted);">
                                <strong>Mitarbeit pro Jahr:</strong> <span data-pc-field="abo-work"><?php echo esc_html($first_work); ?></span> <?php echo esc_html($work_suffix); ?>
                            </span>
                        </td>
                        <td>1</td>
                        <td>CHF <span data-pc-field="abo-price"><?php echo esc_html(number_format_i18n($first_price)); ?></span>.-</td>
                        <td><strong>CHF <span data-pc-field="abo-price-total"><?php echo esc_html(number_format_i18n($first_price)); ?></span>.-</strong></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>
                            Anteilsscheine
                            <span class="payment-type-label" style="display: block; margin-top: 4px;">
                                <strong style="color: var(--wp--preset--color--bioco-beet); font-size: 0.875rem;">Einmalige Zahlung</strong>
                            </span>
                            <span class="text-sm" data-pc-field="shares-note" style="display: block; color: var(--wp--preset--color--bioco-text-muted); margin-top: 4px;">
                                (<span data-pc-field="shares-required"><?php echo esc_html($first_shares); ?></span> erforderlich)
                            </span>
                        </td>
                        <td data-pc-field="shares-count"><?php echo esc_html($first_shares); ?></td>
                        <td>CHF <span data-pc-field="share-price"><?php echo esc_html($share_price); ?></span>.-</td>
                        <td><strong>CHF <span data-pc-field="shares-total"><?php echo esc_html(number_format_i18n($shares_total)); ?></span>.-</strong></td>
                        <td>
                            <div class="share-buttons-container">
                                <div class="share-buttons-label"><strong>Zusätzliche Anteilsscheine hinzufügen:</strong></div>
                                <div class="share-buttons">
                                    <button type="button" class="btn-remove-share" data-pc-action="remove-share" aria-label="Ein Anteilsschein entfernen" title="Ein Anteilsschein entfernen" style="display: none;">-1</button>
                                    <button type="button" class="btn-add-share" data-pc-action="add-share" aria-label="Ein zusätzlicher Anteilsschein hinzufügen" title="Ein zusätzlicher Anteilsschein hinzufügen">+1</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3"><strong>Total</strong></td>
                        <td colspan="2"><strong class="total-amount" data-pc-field="total">CHF <?php echo esc_html(number_format_i18n($grand_total)); ?>.-</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pc-kein-info" data-pc-role="kein-info" style="margin-top: 16px; padding: 16px; background: var(--wp--preset--color--bioco-bg); border-radius: var(--wp--custom--radius--md); display: none;">
            <p style="margin: 0; font-size: 0.875rem;">
                <strong>💡 Info:</strong> Du kannst Anteilsscheine auch ohne Gemüsekorb erwerben. Genossenschafter/innen haben Vorrang auf der Warteliste für einen Gemüsekorb.
            </p>
        </div>

        <div class="pc-actions" data-pc-role="cta-block" style="margin-top: 24px; text-align: center;">
            <a
                class="btn btn-primary"
                data-pc-field="cta"
                href="<?php echo esc_url(add_query_arg(['abo' => $first_slug, 'shares' => $first_shares, 'additional' => 0], $signup_url)); ?>"
                style="display: inline-block; font-size: 1.125rem; padding: 16px 32px;"
            >Jetzt mitmachen!</a>
            <p style="margin-top: 12px; font-size: 0.875rem; color: var(--wp--preset--color--bioco-text-muted);">Deine Auswahl wird automatisch ins Anmeldeformular übernommen</p>
        </div>
    </div>
</section>
