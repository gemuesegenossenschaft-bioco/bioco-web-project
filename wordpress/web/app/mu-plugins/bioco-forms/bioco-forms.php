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
require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/newsletter.php';
require_once __DIR__ . '/membership-fields.php';
require_once __DIR__ . '/membership.php';
require_once __DIR__ . '/public-forms.php';

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
