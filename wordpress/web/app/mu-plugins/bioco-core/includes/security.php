<?php
/** Narrow public endpoint and login protection. Editor REST access stays intact. */
if (!defined('ABSPATH')) exit;

add_filter('rest_pre_dispatch', 'bioco_security_private_users', 10, 3);
function bioco_security_private_users($result, $server, $request) {
    if (!is_user_logged_in() && preg_match('#^/wp/v2/users(?:/|$)#', $request->get_route())) {
        return new WP_Error('bioco_private_users', 'Anmeldung erforderlich.', ['status' => 401]);
    }
    return $result;
}

add_filter('xmlrpc_enabled', '__return_false');
add_action('send_headers', function () {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
});

/** Use the connected peer only. Forwarded headers are not a trusted IP source. */
function bioco_security_login_key(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return 'bioco_login_' . hash_hmac('sha256', $ip, wp_salt('auth'));
}

add_filter('authenticate', 'bioco_security_limit_login', 99, 3);
function bioco_security_limit_login($user, $username, $password) {
    if ($username === '' || $password === '') return $user;
    $attempts = get_transient(bioco_security_login_key());
    if (is_array($attempts) && ($attempts['count'] ?? 0) >= 10) {
        return new WP_Error('bioco_login_limited', 'Zu viele Anmeldeversuche. Bitte in 15 Minuten erneut versuchen.');
    }
    return $user;
}

add_action('wp_login_failed', function () {
    $key = bioco_security_login_key();
    $attempts = get_transient($key);
    if (!is_array($attempts)) $attempts = ['count' => 0, 'until' => time() + 15 * MINUTE_IN_SECONDS];
    $attempts['count']++;
    set_transient($key, $attempts, max(1, $attempts['until'] - time()));
});
add_action('wp_login', function () { delete_transient(bioco_security_login_key()); });
