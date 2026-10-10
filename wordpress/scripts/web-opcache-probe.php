<?php
/** Temporary authenticated probe, installed and removed by the staging release. */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !isset($biocoOpcacheToken)
    || !isset($biocoOpcacheExpires) || time() > $biocoOpcacheExpires
    || !hash_equals($biocoOpcacheToken, $_SERVER['HTTP_X_BIOCO_OPCACHE_TOKEN'] ?? '')) {
    http_response_code(403);
    exit;
}
header('Content-Type: application/json');
header('Cache-Control: no-store');
if (!unlink(__FILE__)) { http_response_code(500); exit; }
// The host can initialize the application's OPcache only after WordPress has
// bootstrapped. Inspect that cache, rather than an empty standalone-PHP cache.
require __DIR__ . '/wp-load.php';
$count = 0;
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(true);
    if ($status === false && ini_get('opcache.enable')) {
        http_response_code(500);
        exit;
    }
    $root = realpath(__DIR__ . '/wp-content');
    if (!$root) { http_response_code(500); exit; }
    $prefixes = [];
    foreach (['mu-plugins/bioco-core/', 'mu-plugins/bioco-content/', 'mu-plugins/bioco-forms/', 'mu-plugins/bioco-import/', 'themes/bioco/', 'themes/bioco-divi/'] as $relative) {
        $prefixes[] = $root . '/' . $relative;
    }
    foreach (($status['scripts'] ?? []) as $script) {
        $path = $script['full_path'];
        $owned = $path === $root . '/mu-plugins/bioco-mu-loader.php';
        foreach ($prefixes as $prefix) if (str_starts_with($path, $prefix)) $owned = true;
        if ($owned) {
            if (!opcache_invalidate($path, true)) { http_response_code(500); exit; }
            $count++;
        }
    }
}
echo json_encode(['ok' => true, 'invalidated' => $count,
    'security_hook' => function_exists('bioco_security_private_users')]);
