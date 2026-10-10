<?php
/** Cookieless page tracking. Private environment config; no form events. */
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', 'bioco_core_enqueue_matomo', 30);
/** Capabilities exclude editors, not ordinary logged-in members. */
function bioco_core_matomo_excluded_request(): bool {
    if (current_user_can('edit_posts') || is_preview() || is_customize_preview()) return true;
    foreach (['et_fb', 'et_pb_preview', 'preview_id', 'preview_nonce',
        'customize_changeset_uuid', 'customize_theme', 'customize_messenger_channel',
        'release_check', 'release-check'] as $key) {
        if (array_key_exists($key, $_GET)) return true;
    }
    return in_array($_GET['preview'] ?? '', ['true', '1'], true)
        || ($_GET['bioco_qa'] ?? '') === '1'
        || ($_SERVER['HTTP_X_BIOCO_QA'] ?? '') === '1';
}

function bioco_core_enqueue_matomo(): void {
    $url = rtrim(trim((string) getenv('BIOCO_MATOMO_URL')), '/');
    $id = trim((string) getenv('BIOCO_MATOMO_SITE_ID'));
    if ($url === '' || $id === '') return;
    if (wp_get_environment_type() !== 'production'
        || strtolower((string) parse_url(home_url('/'), PHP_URL_HOST)) !== 'bioco.ch') return;
    $parts = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https'
        || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])
        || !preg_match('/^[1-9][0-9]*$/D', $id)) return;
    if (bioco_core_matomo_excluded_request()) return;
    $path = dirname(__DIR__) . '/assets/bioco-matomo.js';
    wp_enqueue_script('bioco-matomo', plugin_dir_url(dirname(__DIR__) . '/bioco-core.php') . 'assets/bioco-matomo.js', ['bioco-consent'], (string) filemtime($path), true);
    wp_add_inline_script('bioco-matomo', 'window.biocoMatomoConfig = ' . wp_json_encode(
        ['url' => $url . '/', 'siteId' => $id],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) . ';', 'before');
}
