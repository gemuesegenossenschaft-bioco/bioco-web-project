<?php
/**
 * Double-opt-in confirmation block render template (W10, issue #97).
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 *
 * Security model:
 *   - GET with ?token= renders an explicit confirmation form and NEVER consumes
 *     the token. This protects against link scanners, mail previews, and page
 *     caches accidentally confirming a one-time token.
 *   - POST with the token and a valid WordPress nonce consumes the token via
 *     bioco_forms_doi_confirm_token() (bioco-forms mu-plugin).
 *   - Token-bearing pages stay explicitly non-cacheable.
 *
 * Mirrors .wp-refs/page.tsx's DOIConfirmContent result states.
 * Place on /newsletter-bestaetigen/ (the link sent by the subscribe form).
 */

if (!defined('ABSPATH')) exit;

$missing_token_message = bioco_field('missing_token_message');
$confirmation_prompt = __('Bitte bestätige deine Anmeldung.', 'bioco');
$confirmation_button_label = __('Anmeldung bestätigen', 'bioco');
$success_title = bioco_field('success_title');
$success_text = bioco_field('success_text');
$subscribe_text = bioco_field('subscribe_text');
$visit_text = bioco_field('visit_text');
$waiting_list_text = bioco_field('waiting_list_text');
$contact_text = bioco_field('contact_text');
$error_title = bioco_field('error_title');
$home_link_label = bioco_field('home_link_label');

$request_method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$token = '';
if ($request_method === 'POST' && isset($_POST['bioco_doi_token']) && is_string($_POST['bioco_doi_token'])) {
    $token = sanitize_text_field(wp_unslash($_POST['bioco_doi_token']));
} elseif ($request_method === 'GET' && isset($_GET['token']) && is_string($_GET['token'])) {
    $token = sanitize_text_field(wp_unslash($_GET['token']));
}

$has_token = $token !== '';

// Token-specific confirmation results must never be reused for another visitor by a page cache.
if ($has_token && !$is_preview) {
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    nocache_headers();
}

$result = null;
$show_form = false;
$form_action = get_permalink($post_id) ?: home_url('/');

if ($is_preview) {
    $result = ['success' => true, 'form_type' => 'subscribe', 'error' => ''];
} elseif ($request_method === 'POST' && $has_token) {
    $nonce = isset($_POST['bioco_doi_nonce']) && is_string($_POST['bioco_doi_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['bioco_doi_nonce']))
        : '';
    if (!wp_verify_nonce($nonce, 'bioco_doi_confirm_' . $token)) {
        $result = ['success' => false, 'form_type' => '', 'error' => ''];
    } else {
        $result = bioco_forms_doi_confirm_token($token);
    }
} elseif ($has_token) {
    $show_form = true;
} else {
    $result = ['success' => false, 'form_type' => '', 'error' => $missing_token_message];
}

// Mirrors the per-form_type copy in .wp-refs/page.tsx's DOIConfirmContent.
// Only 'subscribe' is actually wired up to create pending entries in this
// slice (see the bioco-forms mu-plugin docblock) — the others are kept here
// so this block already matches the reference's full copy set if a future
// slice extends DOI to more form types.
$confirmed_copy_by_form_type = [
    'subscribe' => $subscribe_text,
    'visit' => $visit_text,
    'waiting_list' => $waiting_list_text,
    'contact' => $contact_text,
];

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'newsletter-bestaetigung';
$class_name = 'cms-section cms-doi-confirm';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
$state_class = $result && $result['success'] ? 'bioco-doi-confirm-success' : 'bioco-doi-confirm-error';
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <div class="bento-card bioco-doi-confirm <?php echo $result ? esc_attr($state_class) : ''; ?>">
        <?php if ($show_form) : ?>
            <div class="card-body">
                <p class="card-text"><?php echo esc_html($confirmation_prompt); ?></p>
                <form method="post" action="<?php echo esc_url($form_action); ?>">
                    <?php wp_nonce_field('bioco_doi_confirm_' . $token, 'bioco_doi_nonce'); ?>
                    <input type="hidden" name="bioco_doi_token" value="<?php echo esc_attr($token); ?>" />
                    <button type="submit" class="btn btn-primary"><?php echo esc_html($confirmation_button_label); ?></button>
                </form>
            </div>
        <?php elseif ($result && $result['success']) : ?>
<?php if ($success_title) : ?>            <div class="card-header">
                <h3><?php echo esc_html($success_title); ?></h3>
            </div><?php endif; ?>
            <div class="card-body">
<?php if ($success_text) : ?>                <p class="card-text"><?php echo esc_html($success_text); ?></p><?php endif; ?>
                <?php if (!empty($confirmed_copy_by_form_type[$result['form_type']])) : ?>
                    <p class="card-text"><?php echo esc_html($confirmed_copy_by_form_type[$result['form_type']]); ?></p>
                <?php endif; ?>
            </div>
        <?php else : ?>
<?php if ($error_title) : ?>            <div class="card-header">
                <h3><?php echo esc_html($error_title); ?></h3>
            </div><?php endif; ?>
            <div class="card-body">
<?php if (!empty($result['error'])) : ?>                <p class="card-text"><?php echo esc_html($result['error']); ?></p><?php endif; ?>
<?php if ($home_link_label) : ?>                <p class="card-text"><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html($home_link_label); ?></a></p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
