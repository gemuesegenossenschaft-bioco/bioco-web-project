<?php
/**
 * Plugin Name: bioco Forms
 * Description: REST handlers for the five public forms (contact, subscribe,
 * visit-day, waiting-list, event-signup) plus the multi-step membership
 * signup (W10, issue #97). Every handler verifies Cloudflare Turnstile
 * server-side, sanitizes input, and sends mail via wp_mail() — SMTP
 * transport itself is carried by the WP Mail SMTP plugin from .env, this
 * file never talks SMTP directly.
 * Author: bioco
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/membership.php';

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
 * Shared helpers
 */

// Mail recipient for admin-facing form notifications. Overridable via env
// since the reference Next.js lib/email.ts (not available in .wp-refs) is
// the source of truth for this address and was out of scope to read here —
// info@bioco.ch is the documented fallback used across the reference forms
// (see ContactForm.tsx's own error copy).
function bioco_forms_recipient() {
    $env = getenv('BIOCO_FORMS_RECIPIENT');
    return $env ? $env : 'info@bioco.ch';
}

function bioco_forms_client_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        $ip = trim($parts[0]);
        if ($ip) return $ip;
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    }
    return null;
}

// Verifies a Cloudflare Turnstile response token against siteverify.
// Fails closed: no secret configured, no token supplied, or a non-2xx /
// non-success response all reject the submission.
function bioco_forms_verify_turnstile($token, $remote_ip) {
    $config = bioco_forms_turnstile_config();
    $secret = $config['secret'];
    if (!$secret || !$token) {
        return false;
    }

    $body = [
        'secret' => $secret,
        'response' => $token,
    ];
    if ($remote_ip) {
        $body['remoteip'] = $remote_ip;
    }

    $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'timeout' => 5,
        'body' => $body,
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    return !empty($data['success']);
}

// Plain-text notification/confirmation mail. $reply_to is optional and
// mirrors the submitter's address so admins can reply directly from their
// inbox (the reference forms surface this via ContactForm.tsx's fallback
// "senden Sie uns eine E-Mail direkt an info@bioco.ch" copy).
function bioco_forms_send_mail($to, $subject, $body, $reply_to = '') {
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    if ($reply_to && is_email($reply_to)) {
        $headers[] = 'Reply-To: ' . $reply_to;
    }
    return wp_mail($to, $subject, $body, $headers);
}

function bioco_forms_lines($lines) {
    return implode("\n", array_filter($lines, function ($line) {
        return $line !== null;
    }));
}

// Handle for a block's auto-registered view.js, per WordPress core's
// generate_block_asset_handle(): "bioco/contact-form" -> "bioco-contact-form-view-script".
function bioco_forms_view_script_handle($block_name) {
    return str_replace('/', '-', $block_name) . '-view-script';
}

function bioco_forms_turnstile_config() {
    $site_key = (string) getenv('NEXT_PUBLIC_TURNSTILE_SITE_KEY');
    $secret = (string) getenv('TURNSTILE_SECRET_KEY');

    if ($site_key === '' && $secret === '' && wp_get_environment_type() === 'staging') {
        $site_key = '1x00000000000000000000AA';
        $secret = '1x0000000000000000000000000000000AA';
    }

    return [
        'site_key' => $site_key,
        'secret' => $secret,
        'configured' => $site_key !== '' && $secret !== '',
        'partial' => ($site_key === '') !== ($secret === ''),
    ];
}

add_action('admin_notices', function () {
    $config = bioco_forms_turnstile_config();
    if (!$config['partial'] || !current_user_can('manage_options')) return;

    $missing = $config['site_key'] === '' ? 'NEXT_PUBLIC_TURNSTILE_SITE_KEY' : 'TURNSTILE_SECRET_KEY';
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        esc_html(sprintf('bioco Forms: Turnstile is only partially configured. Set %s; public form submissions are currently blocked.', $missing))
    );
});

// Passes the REST endpoint and site key to a block's view.js. Called from
// each form block's render.php. The Turnstile script itself is NOT
// pre-enqueued here (#181): a parser-blocking api.js tag delays
// DOMContentLoaded, so the shared runtime would mount too late and the
// pre-#181 forms could leak personal fields via a native GET. The shared
// lifecycle runtime (assets/bioco-forms-lifecycle.js, dependency of every
// form view script in bioco-core.php) owns loading/retry and appends the
// script itself after mounting.
function bioco_forms_localize_block($block_name, $object_name, $endpoint, array $strings = []) {
    if (function_exists('bioco_dynamic_render_is_inert') && bioco_dynamic_render_is_inert()) return;

    $config = bioco_forms_turnstile_config();

    $group = substr($block_name, strpos($block_name, '/') + 1);
    foreach (['successMessage' => 'success_message', 'fallbackError' => 'fallback_error', 'captchaError' => 'captcha_error', 'transportError' => 'transport_error'] as $configKey => $field) {
        $strings[$configKey] = bioco_forms_message($group, $field, $strings[$configKey] ?? '');
    }
    $handle = bioco_forms_view_script_handle($block_name);
    wp_localize_script($handle, $object_name, [
        'restUrl' => esc_url_raw(rest_url('bioco/v1/' . $endpoint)),
        'turnstileSiteKey' => $config['configured'] ? $config['site_key'] : '',
    ] + $strings);
}

function bioco_forms_json_body(WP_REST_Request $request) {
    $params = $request->get_json_params();
    return is_array($params) ? $params : [];
}

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

/**
 * Membership: validation + intranet field mapping, ported from
 * .wp-refs/membership.ts (validateMembership / buildIntranetSignupPayload).
 */

function bioco_forms_validate_membership($data) {
    $errors = [];
    $required_fields = ['firstName', 'lastName', 'email', 'address', 'zip', 'city'];

    foreach ($required_fields as $field) {
        $value = isset($data[$field]) ? $data[$field] : null;
        if (!is_string($value) || trim($value) === '') {
            $errors[$field] = bioco_forms_message('shared', 'required_field');
        }
    }

    if (!isset($data['privacyAccept']) || $data['privacyAccept'] !== true) {
        $errors['privacyAccept'] = bioco_forms_message('shared', 'privacy');
    }

    if (isset($data['email']) && is_string($data['email']) && trim($data['email']) !== '' && !is_email(trim($data['email']))) {
        $errors['email'] = bioco_forms_message('shared', 'email');
    }

    $membership_type = isset($data['membershipType']) ? $data['membershipType'] : '';
    $abo_type = isset($data['aboType']) ? $data['aboType'] : '';
    $additional = bioco_forms_bounded_share_count($data['additionalShares'] ?? null, 0);
    $shares_only_value = array_key_exists('sharesOnly', $data)
        ? $data['sharesOnly']
        : ($membership_type === 'abo' ? 0 : null);
    $shares_only = bioco_forms_bounded_share_count($shares_only_value, 0);
    $valid_abo = $membership_type === 'abo'
        && in_array($abo_type, ['halb', 'standard', 'doppel'], true)
        && $additional !== null
        && $shares_only === 0;
    $valid_shares_only = $membership_type === 'shares-only'
        && $abo_type === 'none'
        && $additional === 0
        && $shares_only !== null
        && $shares_only >= 1;
    if (!$valid_abo && !$valid_shares_only) {
        $errors['membershipSelection'] = bioco_forms_message('shared', 'membership');
    }

    foreach (['phone', 'mobilePhone', 'birthday', 'comment', 'otherActivity', 'weitereProdukte', 'depot', 'paymentType'] as $field) {
        if (isset($data[$field]) && (!is_string($data[$field]) || strlen($data[$field]) > 5000)) $errors[$field] = bioco_forms_message('shared', 'generic');
    }
    foreach (['preferredDays', 'preferredTimes', 'activityAreas', 'zusatzabos'] as $field) {
        if (isset($data[$field]) && (!is_array($data[$field]) || !array_is_list($data[$field]) || count($data[$field]) > 100 || array_filter($data[$field], static fn($item) => !is_string($item)))) $errors[$field] = bioco_forms_message('shared', 'generic');
    }
    if (!empty($data['birthday']) && is_string($data['birthday'])) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['birthday']);
        if (!$date || $date->format('Y-m-d') !== $data['birthday'] || $date > new DateTimeImmutable('today')) $errors['birthday'] = bioco_forms_message('shared', 'generic');
    }
    $commitments = $data['commitmentAccepted'] ?? [];
    $count = bioco_forms_bounded_share_count($data['commitmentCount'] ?? null, 1);
    $signature = $data['commitmentSignature'] ?? '';
    $valid_signature = $count !== null && is_string($signature) && hash_equals(bioco_forms_commitment_signature($count), $signature);
    if (!$valid_signature || !is_array($commitments) || !array_is_list($commitments) || count($commitments) !== $count || array_filter($commitments, static fn($item) => $item !== true)) {
        $errors['commitmentAccepted'] = bioco_forms_message('shared', 'required_field');
    }

    return ['ok' => empty($errors), 'errors' => $errors];
}

function bioco_forms_bounded_share_count($value, $minimum) {
    if (is_int($value)) {
        $count = $value;
    } elseif (is_string($value) && preg_match('/^\d+$/', $value)) {
        $count = (int) $value;
    } else {
        return null;
    }
    return $count >= $minimum && $count <= 100 ? $count : null;
}

function bioco_forms_membership_total_shares($data) {
    $required_shares_by_abo_type = ['halb' => 1, 'standard' => 2, 'doppel' => 4, 'none' => 0];

    if (isset($data['membershipType']) && $data['membershipType'] === 'shares-only') {
        return isset($data['sharesOnly']) ? (int) $data['sharesOnly'] : 0;
    }

    $abo_type = isset($data['aboType']) ? $data['aboType'] : '';
    $required = isset($required_shares_by_abo_type[$abo_type]) ? $required_shares_by_abo_type[$abo_type] : 0;
    $additional = isset($data['additionalShares']) ? (int) $data['additionalShares'] : 0;
    return $required + $additional;
}

function bioco_forms_membership_notes($data) {
    $lines = [];
    if (!empty($data['comment']) && is_string($data['comment'])) $lines[] = trim($data['comment']);

    if (!empty($data['preferredDays']) && is_array($data['preferredDays'])) {
        $lines[] = 'Bevorzugte Tage: ' . implode(', ', $data['preferredDays']);
    }
    if (!empty($data['preferredTimes']) && is_array($data['preferredTimes'])) {
        $lines[] = 'Bevorzugte Zeiten: ' . implode(', ', $data['preferredTimes']);
    }
    if (!empty($data['activityAreas']) && is_array($data['activityAreas'])) {
        $lines[] = 'Tätigkeitsbereiche: ' . implode(', ', $data['activityAreas']);
    }
    if (!empty($data['otherActivity']) && is_string($data['otherActivity']) && trim($data['otherActivity']) !== '') {
        $lines[] = 'Andere Tätigkeit: ' . trim($data['otherActivity']);
    }
    if (!empty($data['zusatzabos']) && is_array($data['zusatzabos'])) {
        $lines[] = 'Zusatzabos: ' . implode(', ', $data['zusatzabos']);
    }
    if (!empty($data['weitereProdukte']) && is_string($data['weitereProdukte']) && trim($data['weitereProdukte']) !== '') {
        $lines[] = 'Weitere Produkte: ' . trim($data['weitereProdukte']);
    }

    return implode("\n", $lines);
}

// Current intranet field contract (#139); transport is isolated in membership.php.
function bioco_forms_build_intranet_payload($data) {
    $commitment_accepted = false;
    if (!empty($data['commitmentAccepted']) && is_array($data['commitmentAccepted'])) {
        $commitment_accepted = true;
        foreach ($data['commitmentAccepted'] as $item) {
            if (!$item) {
                $commitment_accepted = false;
                break;
            }
        }
    }
    $terms = $commitment_accepted && isset($data['privacyAccept']) && $data['privacyAccept'] === true;

    $payload = [
        'first_name' => isset($data['firstName']) ? $data['firstName'] : '',
        'last_name' => isset($data['lastName']) ? $data['lastName'] : '',
        'email' => isset($data['email']) ? $data['email'] : '',
        'phone' => isset($data['phone']) ? $data['phone'] : '',
        'addr_street' => isset($data['address']) ? $data['address'] : '',
        'addr_zipcode' => isset($data['zip']) ? $data['zip'] : '',
        'addr_location' => isset($data['city']) ? $data['city'] : '',
        'membership_type' => isset($data['membershipType']) ? $data['membershipType'] : '',
        'abo' => isset($data['aboType']) ? $data['aboType'] : '',
        'shares' => (string) bioco_forms_membership_total_shares($data),
        'depot' => isset($data['depot']) ? $data['depot'] : '',
        'payment_interval' => isset($data['paymentType']) ? $data['paymentType'] : '',
        'agb' => $terms ? 'on' : '',
        'mobile_phone' => isset($data['mobilePhone']) ? $data['mobilePhone'] : '',
        'birthday' => isset($data['birthday']) ? $data['birthday'] : '',
        'comment' => bioco_forms_membership_notes($data),
    ];
    return array_map(static fn($value) => sanitize_textarea_field(is_string($value) ? $value : ''), $payload);
}

/**
 * REST routes — bioco/v1 namespace. All POST endpoints are public
 * (permission_callback => __return_true): they are anonymous form
 * submissions gated by Turnstile, not authenticated API calls.
 */

add_action('rest_api_init', function () {

    register_rest_route('bioco/v1', '/contact', [
        'methods' => 'POST',
        'callback' => 'bioco_forms_handle_contact',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('bioco/v1', '/subscribe', [
        'methods' => 'POST',
        'callback' => 'bioco_forms_handle_subscribe',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('bioco/v1', '/visit-day', [
        'methods' => 'POST',
        'callback' => 'bioco_forms_handle_visit_day',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('bioco/v1', '/waiting-list', [
        'methods' => 'POST',
        'callback' => 'bioco_forms_handle_waiting_list',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('bioco/v1', '/event-signup', [
        'methods' => 'POST',
        'callback' => 'bioco_forms_handle_event_signup',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('bioco/v1', '/membership', [
        'methods' => 'POST',
        'callback' => 'bioco_forms_handle_membership',
        'permission_callback' => '__return_true',
    ]);
});

$GLOBALS['bioco_forms_captcha_error'] = bioco_forms_message('shared', 'captcha');
$GLOBALS['bioco_forms_generic_error'] = bioco_forms_message('shared', 'generic');
$GLOBALS['bioco_forms_missing_fields_error'] = bioco_forms_message('shared', 'missing_fields');

// Mirrors .wp-refs/api-forms/contact/route.ts — subject line is
// "Kontaktanfrage: {subject}", body lists all submitted fields.
function bioco_forms_handle_contact(WP_REST_Request $request) {
    $body = bioco_forms_json_body($request);

    $captcha_token = isset($body['captchaToken']) ? sanitize_text_field($body['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $name = isset($body['name']) ? sanitize_text_field($body['name']) : '';
    $email = isset($body['email']) ? sanitize_email($body['email']) : '';
    $phone = isset($body['phone']) ? sanitize_text_field($body['phone']) : '';
    $subject = isset($body['subject']) ? sanitize_text_field($body['subject']) : '';
    $message = isset($body['message']) ? sanitize_textarea_field($body['message']) : '';

    if (!$name || !$email || !is_email($email) || !$subject || !$message) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_missing_fields_error']], 400);
    }

    $lines = ['Name: ' . $name, 'E-Mail: ' . $email];
    if ($phone) {
        $lines[] = 'Telefon: ' . $phone;
    }
    $lines[] = 'Betreff: ' . $subject;
    $lines[] = '';
    $lines[] = 'Nachricht:';
    $lines[] = $message;

    $sent = bioco_forms_send_mail(bioco_forms_recipient(), 'Kontaktanfrage: ' . $subject, bioco_forms_lines($lines), $email);
    if (!$sent) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_generic_error']], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
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

// Visit-day and waiting-list send mail immediately (no DOI — see the module
// docblock above): die Erfolgsmeldung des Referenztexts (Anrede du, Issue #158)
// copy is specific to subscribe's real double-opt-in, so the ported block
// view.js uses adapted success text instead of a false promise.
function bioco_forms_handle_visit_day(WP_REST_Request $request) {
    $body = bioco_forms_json_body($request);

    $captcha_token = isset($body['captchaToken']) ? sanitize_text_field($body['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $name = isset($body['name']) ? sanitize_text_field($body['name']) : '';
    $email = isset($body['email']) ? sanitize_email($body['email']) : '';
    $phone = isset($body['phone']) ? sanitize_text_field($body['phone']) : '';
    $visit_date = isset($body['visit_date']) ? sanitize_text_field($body['visit_date']) : '';
    $participants = isset($body['participants']) ? (int) $body['participants'] : 0;
    $notes = isset($body['notes']) ? sanitize_textarea_field($body['notes']) : '';
    $privacy_accept = !empty($body['privacy_accept']);

    if (!$name || !$email || !is_email($email) || !$phone || !$visit_date || !$participants || !$privacy_accept) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_missing_fields_error']], 400);
    }

    $lines = [
        'Name: ' . $name,
        'E-Mail: ' . $email,
        'Telefon: ' . $phone,
        'Gewünschtes Datum: ' . $visit_date,
        'Anzahl Personen: ' . $participants,
    ];
    if ($notes) {
        $lines[] = '';
        $lines[] = 'Anmerkungen:';
        $lines[] = $notes;
    }

    $sent = bioco_forms_send_mail(bioco_forms_recipient(), 'Neue Anmeldung: Tag der offenen Tür', bioco_forms_lines($lines), $email);
    if (!$sent) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_generic_error']], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
}

function bioco_forms_handle_waiting_list(WP_REST_Request $request) {
    $body = bioco_forms_json_body($request);

    $captcha_token = isset($body['captchaToken']) ? sanitize_text_field($body['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $name = isset($body['name']) ? sanitize_text_field($body['name']) : '';
    $email = isset($body['email']) ? sanitize_email($body['email']) : '';
    $phone = isset($body['phone']) ? sanitize_text_field($body['phone']) : '';
    $interest = isset($body['interest']) ? sanitize_text_field($body['interest']) : '';
    $notes = isset($body['notes']) ? sanitize_textarea_field($body['notes']) : '';
    $privacy_accept = !empty($body['privacy_accept']);

    if (!$name || !$email || !is_email($email) || !$phone || !$interest || !$privacy_accept) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_missing_fields_error']], 400);
    }

    $interest_labels = ['program1' => 'Programm 1', 'program2' => 'Programm 2', 'program3' => 'Programm 3'];
    $interest_label = isset($interest_labels[$interest]) ? $interest_labels[$interest] : $interest;

    $lines = [
        'Name: ' . $name,
        'E-Mail: ' . $email,
        'Telefon: ' . $phone,
        'Interesse an: ' . $interest_label,
    ];
    if ($notes) {
        $lines[] = '';
        $lines[] = 'Anmerkungen:';
        $lines[] = $notes;
    }

    $sent = bioco_forms_send_mail(bioco_forms_recipient(), 'Neue Warteliste-Anmeldung', bioco_forms_lines($lines), $email);
    if (!$sent) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_generic_error']], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
}

// Mirrors .wp-refs/api-forms/event-signup/route.ts — subject uses the
// event title when present, falling back to a generic subject.
function bioco_forms_handle_event_signup(WP_REST_Request $request) {
    $body = bioco_forms_json_body($request);

    $captcha_token = isset($body['captchaToken']) ? sanitize_text_field($body['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $name = isset($body['name']) ? sanitize_text_field($body['name']) : '';
    $email = isset($body['email']) ? sanitize_email($body['email']) : '';
    $phone = isset($body['phone']) ? sanitize_text_field($body['phone']) : '';
    $notes = isset($body['notes']) ? sanitize_textarea_field($body['notes']) : '';
    $event_title = isset($body['eventTitle']) ? sanitize_text_field($body['eventTitle']) : '';
    $event_id = isset($body['eventId']) ? sanitize_text_field($body['eventId']) : '';

    if (!$name || !$email || !is_email($email)) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_missing_fields_error']], 400);
    }

    $lines = ['Name: ' . $name, 'E-Mail: ' . $email];
    if ($phone) {
        $lines[] = 'Telefon: ' . $phone;
    }
    if ($event_title) {
        $lines[] = 'Veranstaltung: ' . $event_title;
    }
    if ($event_id) {
        $lines[] = 'Veranstaltungs-ID: ' . $event_id;
    }
    if ($notes) {
        $lines[] = '';
        $lines[] = 'Bemerkungen:';
        $lines[] = $notes;
    }

    $subject = $event_title ? ('Event-Anmeldung: ' . $event_title) : 'Neue Event-Anmeldung';
    $sent = bioco_forms_send_mail(bioco_forms_recipient(), $subject, bioco_forms_lines($lines), $email);
    if (!$sent) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_generic_error']], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
}

// Mirrors .wp-refs/api-forms/membership/route.ts: validate -> email ->
// best-effort intranet forward. No Turnstile in the reference MembershipForm
// (no CaptchaField import there) — added here per issue #97's "every
// handler verifies Turnstile" requirement; the block's view.js renders a
// widget that the reference component lacks.
function bioco_forms_handle_membership(WP_REST_Request $request) {
    $data = bioco_forms_json_body($request);

    $captcha_token = isset($data['captchaToken']) ? sanitize_text_field($data['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $validation = bioco_forms_validate_membership($data);
    if (!$validation['ok']) {
        return new WP_REST_Response([
            'success' => false,
            'error' => $GLOBALS['bioco_forms_missing_fields_error'],
            'fieldErrors' => $validation['errors'],
        ], 400);
    }

    $acceptance = bioco_forms_membership_accept($data);
    if ($acceptance['status'] !== 'accepted') {
        return new WP_REST_Response([
            'success' => false,
            'error' => bioco_forms_message('shared', 'generic'),
            'fieldErrors' => $acceptance['fieldErrors'] ?? [],
        ], $acceptance['status'] === 'validation' ? 400 : 502);
    }
    if (!empty($acceptance['replayed'])) {
        return new WP_REST_Response(['success' => true, 'receipt' => $acceptance['receipt'], 'simulated' => !empty($acceptance['simulated'])], 200);
    }

    $first_name = sanitize_text_field($data['firstName']);
    $last_name = sanitize_text_field($data['lastName']);
    $email = sanitize_email($data['email']);
    $address = sanitize_text_field($data['address']);
    $zip = sanitize_text_field($data['zip']);
    $city = sanitize_text_field($data['city']);
    $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
    $depot = isset($data['depot']) ? sanitize_text_field($data['depot']) : '';
    $payment_type = isset($data['paymentType']) ? sanitize_text_field($data['paymentType']) : '';
    $abo_type = isset($data['aboType']) ? sanitize_text_field($data['aboType']) : '';
    $membership_type = isset($data['membershipType']) ? sanitize_text_field($data['membershipType']) : '';

    $lines = [
        'Vorname: ' . $first_name,
        'Name: ' . $last_name,
        'Adresse: ' . $address . ', ' . $zip . ' ' . $city,
        'E-Mail: ' . $email,
    ];
    if ($phone) {
        $lines[] = 'Telefon: ' . $phone;
    }
    $lines[] = '';
    $lines[] = 'Mitgliedschaft: ' . ($membership_type === 'shares-only' ? 'Nur Anteilsscheine' : 'Gemüseabo');
    if ($membership_type !== 'shares-only') {
        $lines[] = 'Gemüsekorb: ' . $abo_type;
    }
    $lines[] = 'Anteilsscheine: ' . bioco_forms_membership_total_shares($data);
    if ($depot) {
        $lines[] = 'Depot: ' . $depot;
    }
    if ($payment_type) {
        $lines[] = 'Zahlungsweise: ' . ($payment_type === 'quarterly' ? 'Quartalsweise' : 'Ganzes Jahr');
    }
    $notes = bioco_forms_membership_notes($data);
    if ($notes) {
        $lines[] = '';
        $lines[] = $notes;
    }

    // Acceptance is durable before notification. A failed notification must
    // never invite another submission. Fake staging signups send no real mail.
    if (empty($acceptance['simulated'])) {
        try {
            $subject = 'Neue Mitgliedschaftsanmeldung: ' . $first_name . ' ' . $last_name;
            bioco_forms_send_mail(bioco_forms_recipient(), $subject, bioco_forms_lines($lines), $email);
        } catch (Throwable $error) {
            error_log('bioco membership: accepted signup notification failed');
        }
    }
    return new WP_REST_Response(['success' => true, 'receipt' => $acceptance['receipt'], 'simulated' => !empty($acceptance['simulated'])], 200);
}
