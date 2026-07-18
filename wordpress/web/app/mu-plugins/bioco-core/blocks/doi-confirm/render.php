<?php
/**
 * Double-opt-in confirmation block render template (W10, issue #97).
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * No editable ACF fields — this block reads ?token= from the query string
 * server-side and confirms it directly via bioco_forms_doi_confirm_token()
 * (bioco-forms mu-plugin), no REST round-trip needed since we're already
 * server-side. Mirrors .wp-refs/page.tsx's DOIConfirmContent result states.
 * Place on /newsletter-bestaetigen/ (the link sent by the subscribe form).
 */

if (!defined('ABSPATH')) exit;

$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

if ($is_preview) {
    $result = ['success' => true, 'form_type' => 'subscribe', 'error' => ''];
} elseif ($token) {
    $result = bioco_forms_doi_confirm_token($token);
} else {
    $result = ['success' => false, 'form_type' => '', 'error' => 'Kein Bestätigungstoken angegeben.'];
}

// Mirrors the per-form_type copy in .wp-refs/page.tsx's DOIConfirmContent.
// Only 'subscribe' is actually wired up to create pending entries in this
// slice (see the bioco-forms mu-plugin docblock) — the others are kept here
// so this block already matches the reference's full copy set if a future
// slice extends DOI to more form types.
$confirmed_copy_by_form_type = [
    'subscribe' => 'Du erhältst ab sofort unseren Newsletter.',
    'visit' => 'Wir haben deine Anmeldung für den Tag der offenen Tür erhalten und melden uns bald bei dir.',
    'waiting_list' => 'Wir haben dich auf die Warteliste gesetzt und melden uns bei dir, sobald ein Platz frei wird.',
    'contact' => 'Wir haben deine Nachricht erhalten und melden uns bald bei dir.',
];

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'newsletter-bestaetigung';
$class_name = 'cms-section cms-doi-confirm';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
$state_class = $result['success'] ? 'bioco-doi-confirm-success' : 'bioco-doi-confirm-error';
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <div class="bento-card bioco-doi-confirm <?php echo esc_attr($state_class); ?>">
        <?php if ($result['success']) : ?>
            <div class="card-header">
                <h3>Anmeldung bestätigt</h3>
            </div>
            <div class="card-body">
                <p class="card-text">Vielen Dank! Deine Anmeldung wurde erfolgreich bestätigt.</p>
                <?php if (!empty($confirmed_copy_by_form_type[$result['form_type']])) : ?>
                    <p class="card-text"><?php echo esc_html($confirmed_copy_by_form_type[$result['form_type']]); ?></p>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="card-header">
                <h3>Bestätigung fehlgeschlagen</h3>
            </div>
            <div class="card-body">
                <p class="card-text"><?php echo esc_html($result['error']); ?></p>
                <p class="card-text"><a href="<?php echo esc_url(home_url('/')); ?>">Zurück zur Startseite</a></p>
            </div>
        <?php endif; ?>
    </div>
</section>
