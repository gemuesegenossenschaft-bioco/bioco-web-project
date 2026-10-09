<?php
/** Shared form transport, CAPTCHA configuration and browser contract. */
if (!defined('ABSPATH')) exit;

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

