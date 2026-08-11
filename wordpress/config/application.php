<?php
/**
 * Bedrock base configuration. Secrets come from .env (server/CI only).
 * Docroot is web/; WP core lives in web/wp; content in web/app.
 */
use Roots\WPConfig\Config;
use function Env\env;

define('WP_ENV_TYPES', ['development', 'staging', 'production']);

$root_dir = dirname(__DIR__);
$webroot_dir = $root_dir . '/web';

if (file_exists($root_dir . '/.env')) {
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable($root_dir);
    $dotenv->load();
}

$required_env = [
    'WP_HOME', 'WP_SITEURL', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
    'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
    'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
];
$missing_env = array_values(array_filter($required_env, function ($name) {
    $value = env($name);
    return $value === null || $value === '';
}));
if ($missing_env) {
    throw new RuntimeException('Missing required environment variables: ' . implode(', ', $missing_env));
}

define('WP_ENV', env('WP_ENV') ?: 'production');

Config::define('WP_HOME', env('WP_HOME'));
Config::define('WP_SITEURL', env('WP_SITEURL'));

Config::define('DB_NAME', env('DB_NAME'));
Config::define('DB_USER', env('DB_USER'));
Config::define('DB_PASSWORD', env('DB_PASSWORD'));
Config::define('DB_HOST', env('DB_HOST') ?: 'localhost');
Config::define('DB_CHARSET', 'utf8mb4');
Config::define('DB_COLLATE', '');
$table_prefix = env('DB_PREFIX') ?: 'wp_';

Config::define('AUTH_KEY', env('AUTH_KEY'));
Config::define('SECURE_AUTH_KEY', env('SECURE_AUTH_KEY'));
Config::define('LOGGED_IN_KEY', env('LOGGED_IN_KEY'));
Config::define('NONCE_KEY', env('NONCE_KEY'));
Config::define('AUTH_SALT', env('AUTH_SALT'));
Config::define('SECURE_AUTH_SALT', env('SECURE_AUTH_SALT'));
Config::define('LOGGED_IN_SALT', env('LOGGED_IN_SALT'));
Config::define('NONCE_SALT', env('NONCE_SALT'));

Config::define('WP_CONTENT_DIR', $webroot_dir . '/app');
Config::define('WP_CONTENT_URL', Config::get('WP_HOME') . '/app');

// Security hardening (epic #73 / W12)
Config::define('DISALLOW_FILE_EDIT', true);
Config::define('DISALLOW_FILE_MODS', true);
Config::define('FORCE_SSL_ADMIN', true);
Config::define('AUTOMATIC_UPDATER_DISABLED', true);

if (env('WP_ENV') === 'development') {
    Config::define('WP_DEBUG', true);
    Config::define('WP_DEBUG_DISPLAY', true);
} else {
    Config::define('WP_DEBUG', false);
    Config::define('WP_DEBUG_DISPLAY', false);
    ini_set('display_errors', '0');
}

$env_config = __DIR__ . '/environments/' . WP_ENV . '.php';
if (file_exists($env_config)) {
    require_once $env_config;
}

Config::apply();

if (!defined('ABSPATH')) {
    define('ABSPATH', $webroot_dir . '/wp/');
}
