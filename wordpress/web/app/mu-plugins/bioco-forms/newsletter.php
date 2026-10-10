<?php
/** Newsletter subscription storage and double-opt-in. */
if (!defined('ABSPATH')) exit;

/**
 * bioco_subscriber CPT — confirmed (double-opt-in complete) newsletter
 * subscribers. Not public: this is a data store, not a front-end archive.
 */
add_action('init', function () {
    register_post_type('bioco_subscriber', [
        'labels' => [
            'name' => __('Newsletter-Abonnenten', 'bioco'),
            'singular_name' => __('Abonnent', 'bioco'),
            'add_new' => __('Neuer Abonnent', 'bioco'),
            'add_new_item' => __('Neuen Abonnenten erstellen', 'bioco'),
            'edit_item' => __('Abonnent bearbeiten', 'bioco'),
            'new_item' => __('Neuer Abonnent', 'bioco'),
            'view_item' => __('Abonnent ansehen', 'bioco'),
            'view_items' => __('Abonnenten ansehen', 'bioco'),
            'search_items' => __('Abonnenten durchsuchen', 'bioco'),
            'not_found' => __('Keine Abonnenten gefunden', 'bioco'),
            'not_found_in_trash' => __('Keine Abonnenten im Papierkorb gefunden', 'bioco'),
            'all_items' => __('Alle Abonnenten', 'bioco'),
            'menu_name' => __('Newsletter', 'bioco'),
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => false,
        'menu_icon' => 'dashicons-email-alt',
        'supports' => ['title'],
        'has_archive' => false,
        'rewrite' => false,
        'capability_type' => 'post',
    ]);
});

/**
 * Double-opt-in (DOI). Pending signups are stored as WP transients keyed by
 * a sha256 hash of a random token (the raw token only ever leaves the
 * server inside the confirmation email link), 24h expiry. Mirrors the
 * subscribe -> /newsletter-bestaetigen/?token=... -> confirm flow in
 * .wp-refs/page.tsx. Scoped to the "subscribe" form per issue #97; contact/
 * visit-day/waiting-list/event-signup send mail immediately instead (see
 * the individual REST handlers below).
 */

function bioco_forms_doi_transient_key($token_hash) {
    return 'bioco_doi_' . $token_hash;
}

function bioco_forms_doi_create_token($form_type, $data) {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    set_transient(bioco_forms_doi_transient_key($hash), [
        'form_type' => $form_type,
        'data' => $data,
        'created' => time(),
    ], DAY_IN_SECONDS);
    return $token;
}

// Runs once a pending signup's token is confirmed. Only "subscribe" is
// wired up (issue #97 scope): creates/updates the confirmed bioco_subscriber
// entry and notifies the admin recipient.
function bioco_forms_doi_on_confirm($form_type, $data) {
    if ($form_type !== 'subscribe') {
        return;
    }

    $email = isset($data['email']) ? sanitize_email($data['email']) : '';
    $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
    if (!$email) {
        return;
    }

    $existing = new WP_Query([
        'post_type' => 'bioco_subscriber',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            ['key' => 'subscriber_email', 'value' => $email, 'compare' => '='],
        ],
    ]);

    if (!$existing->have_posts()) {
        $post_id = wp_insert_post([
            'post_type' => 'bioco_subscriber',
            'post_title' => $email,
            'post_status' => 'publish',
        ]);
        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, 'subscriber_email', $email);
            update_post_meta($post_id, 'subscriber_name', $name);
            update_post_meta($post_id, 'confirmed_at', current_time('mysql'));
            update_post_meta($post_id, '_bioco_confirmation_id', bin2hex(random_bytes(16)));
        }
    } else {
        $post_id = (int) $existing->posts[0];
        if (get_post_meta($post_id, 'unsubscribed_at', true) !== '') {
            update_post_meta($post_id, 'subscriber_name', $name);
            update_post_meta($post_id, 'confirmed_at', current_time('mysql'));
            update_post_meta($post_id, '_bioco_confirmation_id', bin2hex(random_bytes(16)));
            delete_post_meta($post_id, 'unsubscribed_at');
        }
    }

    $lines = ['E-Mail: ' . $email];
    if ($name) {
        $lines[] = 'Name: ' . $name;
    }
    bioco_forms_send_mail(bioco_forms_recipient(), 'Neuer Newsletter-Abonnent bestätigt', bioco_forms_lines($lines));
}

// Used by the bioco/doi-confirm block's nonce-protected server-side POST flow.
function bioco_forms_doi_confirm_token($token) {
    $token = is_string($token) ? sanitize_text_field($token) : '';

    if ($token === '') {
        return ['success' => false, 'form_type' => '', 'error' => bioco_forms_message('shared', 'missing_token')];
    }

    if (!ctype_xdigit($token)) {
        return ['success' => false, 'form_type' => '', 'error' => bioco_forms_message('shared', 'invalid_token')];
    }
    $hash = hash('sha256', $token);
    $key = bioco_forms_doi_transient_key($hash);
    $entry = get_transient($key);

    if (!is_array($entry) || empty($entry['form_type'])) {
        return ['success' => false, 'form_type' => '', 'error' => bioco_forms_message('shared', 'invalid_token')];
    }

    delete_transient($key);
    bioco_forms_doi_on_confirm($entry['form_type'], isset($entry['data']) ? $entry['data'] : []);

    return ['success' => true, 'form_type' => $entry['form_type'], 'error' => ''];
}

// Newsletter double-opt-in start: stores a pending signup + emails the
// confirmation link. The bioco_subscriber entry is only created once the
// link in that email is opened (bioco_forms_doi_on_confirm above).
function bioco_forms_handle_subscribe(WP_REST_Request $request) {
    $body = bioco_forms_json_body($request);

    $captcha_token = isset($body['captchaToken']) ? sanitize_text_field($body['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $email = isset($body['email']) ? sanitize_email($body['email']) : '';
    $name = isset($body['name']) ? sanitize_text_field($body['name']) : '';
    $privacy_accept = !empty($body['privacy_accept']);

    if (!$email || !is_email($email) || !$privacy_accept) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_missing_fields_error']], 400);
    }

    $token = bioco_forms_doi_create_token('subscribe', ['email' => $email, 'name' => $name]);
    $confirm_url = trailingslashit(home_url('/newsletter-bestaetigen')) . '?token=' . rawurlencode($token);

    $greeting = $name !== ''
        ? str_replace('{name}', $name, bioco_forms_message('shared', 'newsletter_greeting'))
        : bioco_forms_message('shared', 'newsletter_greeting_anonymous');
    $body_lines = [
        $greeting,
        '',
        bioco_forms_message('shared', 'newsletter_intro'),
        $confirm_url,
        '',
        bioco_forms_message('shared', 'newsletter_expiry'),
        '',
        bioco_forms_message('shared', 'newsletter_ignore'),
    ];

    $sent = bioco_forms_send_mail($email, bioco_forms_message('shared', 'newsletter_subject'), bioco_forms_lines($body_lines));
    if (!$sent) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_generic_error']], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
}
