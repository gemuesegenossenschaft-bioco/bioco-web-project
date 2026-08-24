<?php
/**
 * Permanent redirects retained from the former Next.js frontend.
 */

if (!defined('ABSPATH')) exit;

function bioco_permanent_redirects() {
    $path = dirname(__DIR__) . '/content/redirects.json';
    $rules = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
    return is_array($rules) ? array_values($rules) : [];
}

function bioco_redirect_destination($request_uri) {
    $request_path = parse_url((string) $request_uri, PHP_URL_PATH);
    if (!is_string($request_path)) return null;
    $request_path = rawurldecode($request_path);
    if ($request_path !== '/' && str_ends_with($request_path, '/')) {
        $request_path = substr($request_path, 0, -1);
    }

    foreach (bioco_permanent_redirects() as $rule) {
        if (($rule['permanent'] ?? false) !== true) continue;
        if (rawurldecode((string) ($rule['source'] ?? '')) !== $request_path) continue;
        $destination = $rule['destination'] ?? null;
        if (!is_string($destination) || !str_starts_with($destination, '/')) return null;

        $query = parse_url((string) $request_uri, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $destination .= str_contains($destination, '?') ? '&' : '?';
            $destination .= $query;
        }
        return $destination;
    }

    return null;
}

function bioco_handle_permanent_redirects() {
    $destination = bioco_redirect_destination($_SERVER['REQUEST_URI'] ?? '');
    if ($destination === null) return;
    wp_safe_redirect(home_url($destination), 301);
    exit;
}

add_action('template_redirect', 'bioco_handle_permanent_redirects', 1);
