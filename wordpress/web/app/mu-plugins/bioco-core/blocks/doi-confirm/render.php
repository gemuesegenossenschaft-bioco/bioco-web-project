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

$missing_token_message = get_field('missing_token_message');
$success_title = get_field('success_title');
$success_text = get_field('success_text');
$subscribe_text = get_field('subscribe_text');
$visit_text = get_field('visit_text');
$waiting_list_text = get_field('waiting_list_text');
$contact_text = get_field('contact_text');
$error_title = get_field('error_title');
$home_link_label = get_field('home_link_label');

$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

if ($is_preview) {
    $result = ['success' => true, 'form_type' => 'subscribe', 'error' => ''];
} elseif ($token) {
    $result = bioco_forms_doi_confirm_token($token);
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
$state_class = $result['success'] ? 'bioco-doi-confirm-success' : 'bioco-doi-confirm-error';
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <div class="bento-card bioco-doi-confirm <?php echo esc_attr($state_class); ?>">
        <?php if ($result['success']) : ?>
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
