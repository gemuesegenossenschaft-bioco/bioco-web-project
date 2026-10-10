<?php
/** Cookieless page tracking. Private environment config; no form events. */
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', 'bioco_core_enqueue_matomo', 30);
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
    $path = dirname(__DIR__) . '/assets/bioco-matomo.js';
    wp_enqueue_script('bioco-matomo', plugin_dir_url(dirname(__DIR__) . '/bioco-core.php') . 'assets/bioco-matomo.js', ['bioco-consent'], (string) filemtime($path), true);
    wp_add_inline_script('bioco-matomo', 'window.biocoMatomoConfig = ' . wp_json_encode(
        ['url' => $url . '/', 'siteId' => $id],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) . ';', 'before');
}
