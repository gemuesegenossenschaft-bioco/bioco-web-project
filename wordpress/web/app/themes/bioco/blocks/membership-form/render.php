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
 * membershipType/aboType/additionalShares are fixed hidden fields: the
 * reference itself notes "Always abo, no choice" / "Always standard,
 * pre-selected" / "Always 0, no choice" for a form reached from the
 * mitmachen page — this block matches that default, sharesOnly is unused.
 */

if (!defined('ABSPATH')) exit;

$title = get_field('title');
$text = get_field('text');

bioco_forms_localize_block('bioco/membership-form', 'biocoMembershipFormConfig', 'membership');

$depots = [
    'Depot Chrättli', 'Depot Ohne', 'Depot Anixis', 'Casa Flora', 'Depot Geisshof',
    'Depot Kupperhaus', 'Depot Ennetbaden', 'Depot Lemonia', 'Depot Lägernstrasse',
];
$days = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
$times = ['morgens', 'nachmittags', 'abends'];
$activity_areas = ['Feld/Anbau', 'Logistik/Verteilung', 'Administration', 'Events/Organisation', 'Andere'];
$zusatzabos = ['Milch', 'Fleisch', 'Käse', 'Kräutersalz'];

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

        <div class="form-step">
            <h3>Lies das und bestätige bevor du weiterklickst</h3>
            <p>Bevor du dich anmeldest, überprüfe bitte diese Punkte:</p>
            <div class="commitment-checklist">
                <label class="commitment-item">
                    <input type="checkbox" name="commitmentAccepted[]" data-bool-array>
                    <div>
                        <h4>Anteile &amp; Beitrag</h4>
                        <p>Jedes Mitglied erwirbt <strong>Anteilsscheine zu je CHF 250.- (einmalige Zahlung)</strong>. Die Anzahl der Anteile hängt von deinem gewählten Abo ab. Der <strong>Jahresbeitrag für dein Gemüse-Abo (jährlicher Beitrag) wird per 31. Januar fällig</strong> und kann quartalsweise oder jährlich bezahlt werden.</p>
                    </div>
                </label>
                <label class="commitment-item">
                    <input type="checkbox" name="commitmentAccepted[]" data-bool-array>
                    <div>
                        <h4>Bindung &amp; Kündigung</h4>
                        <p>Das Gemüseabo läuft <strong>vom 1. Januar bis zum 31. Dezember</strong>. Ohne Kündigung verlängert es sich jeweils um ein Kalenderjahr. Die <strong>Kündigungsfrist beträgt zwei Monate auf Ende eines Kalenderjahres</strong>.</p>
                    </div>
                </label>
                <label class="commitment-item">
                    <input type="checkbox" name="commitmentAccepted[]" data-bool-array>
                    <div>
                        <h4>Mitarbeit</h4>
                        <p>Wir sind eine Mitmach-Genossenschaft! Jedes Mitglied leistet pro Jahr <strong>10 Arbeitseinsätze à 2 Stunden (bei halbem Korb) bzw. 20 Arbeitseinsätze à 2 Stunden (bei ganzem Korb) Mitarbeit</strong>. Dies kann auf dem Feld, in der Logistik oder bei Events sein.</p>
                    </div>
                </label>
                <label class="commitment-item">
                    <input type="checkbox" name="commitmentAccepted[]" data-bool-array>
                    <div>
                        <h4>Wetterbedingte Ertragsschwankungen</h4>
                        <p>Mir ist bewusst, dass es zu Ernteausfällen kommen kann und mein wöchentlicher Gemüsekorb nicht immer gleich voll sein kann.</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="form-step">
            <h3>Persönliche Daten</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="membership_first_name">Vorname *</label>
                    <input type="text" id="membership_first_name" name="firstName" required>
                </div>
                <div class="form-group">
                    <label for="membership_last_name">Name *</label>
                    <input type="text" id="membership_last_name" name="lastName" required>
                </div>
            </div>
            <div class="form-group">
                <label for="membership_address">Adresse *</label>
                <input type="text" id="membership_address" name="address" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="membership_zip">PLZ *</label>
                    <input type="text" id="membership_zip" name="zip" required>
                </div>
                <div class="form-group">
                    <label for="membership_city">Ort *</label>
                    <input type="text" id="membership_city" name="city" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="membership_phone">Telefon</label>
                    <input type="tel" id="membership_phone" name="phone">
                </div>
                <div class="form-group">
                    <label for="membership_email">E-Mail *</label>
                    <input type="email" id="membership_email" name="email" required>
                </div>
            </div>
        </div>

        <div class="form-step">
            <h3>Depot &amp; Zahlungsweise</h3>
            <div class="form-group">
                <label for="membership_depot">Depot-Auswahl *</label>
                <select id="membership_depot" name="depot" required>
                    <option value="">Bitte wählen...</option>
                    <?php foreach ($depots as $depot) : ?>
                        <option value="<?php echo esc_attr($depot); ?>"><?php echo esc_html($depot); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Zahlungsweise *</label>
                <p class="form-hint">Die erste Rechnung wird per 31. Januar fällig.</p>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="paymentType" value="quarterly">
                        <span>Quartalsweise (vierteljährlich)</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="paymentType" value="yearly" checked>
                        <span>Ganzes Jahr (einmalig)</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-step">
            <h3>Mitarbeit</h3>
            <p>Jede(r) Mitglied bringt sich ein. Bitte teile uns deine Präferenzen mit:</p>
            <div class="form-group">
                <label>Bevorzugte Tage</label>
                <div class="checkbox-group">
                    <?php foreach ($days as $day) : ?>
                        <label class="checkbox-option">
                            <input type="checkbox" name="preferredDays[]" value="<?php echo esc_attr($day); ?>">
                            <span><?php echo esc_html($day); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label>Bevorzugte Zeiten</label>
                <div class="checkbox-group">
                    <?php foreach ($times as $time) : ?>
                        <label class="checkbox-option">
                            <input type="checkbox" name="preferredTimes[]" value="<?php echo esc_attr($time); ?>">
                            <span><?php echo esc_html($time); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label>Tätigkeitsbereiche (Mehrfachauswahl möglich)</label>
                <div class="checkbox-group">
                    <?php foreach ($activity_areas as $area) : ?>
                        <label class="checkbox-option">
                            <input type="checkbox" name="activityAreas[]" value="<?php echo esc_attr($area); ?>">
                            <span><?php echo esc_html($area); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="membership_other_activity">Andere Tätigkeitsbereiche (bitte beschreiben)</label>
                <textarea id="membership_other_activity" name="otherActivity" rows="3"></textarea>
            </div>
        </div>

        <div class="form-step">
            <h3>Zusatzabos</h3>
            <p>Ich bin interessiert, zusätzlich:</p>
            <div class="form-group">
                <div class="checkbox-group">
                    <?php foreach ($zusatzabos as $product) : ?>
                        <label class="checkbox-option">
                            <input type="checkbox" name="zusatzabos[]" value="<?php echo esc_attr($product); ?>">
                            <span><?php echo esc_html($product); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="membership_weitere_produkte">Weitere Produkte von Partnern aus der Region</label>
                <p class="form-hint">Hast du Wünsche für weitere Produkte? Teile uns deine Ideen mit:</p>
                <textarea id="membership_weitere_produkte" name="weitereProdukte" rows="4" placeholder="z.B. Eier, Brot, Tofu, Honig..."></textarea>
            </div>
        </div>

        <div class="form-step">
            <h3>Bestätigung</h3>
            <div class="form-group">
                <label class="checkbox-option">
                    <input type="checkbox" name="privacyAccept" required>
                    <span>Ich akzeptiere die Datenschutzbestimmungen *</span>
                </label>
            </div>
            <div class="form-group">
                <div class="cf-turnstile" data-form-captcha></div>
            </div>
        </div>

        <div class="form-navigation">
            <button type="submit" class="btn btn-primary" data-submit-label="Anmeldung einreichen" data-submitting-label="Wird gesendet …">Anmeldung einreichen</button>
        </div>
    </form>
</section>
