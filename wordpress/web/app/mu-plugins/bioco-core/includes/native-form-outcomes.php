<?php
/** Native DOI rows own visible copy; this module owns request/token semantics. */
if (!defined('ABSPATH')) exit;

/** Caller and tests use the same seam. GET never invokes the confirmation adapter. */
function bioco_doi_outcome(array $request, callable $verify_nonce, callable $confirm): array {
    $method = strtoupper((string) ($request['method'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST'], true)) return ['state' => 'invalid', 'token' => '', 'form_type' => ''];
    $token = $method === 'POST' ? ($request['post']['bioco_doi_token'] ?? '') : ($request['get']['token'] ?? '');
    $token = is_string($token) ? sanitize_text_field(wp_unslash($token)) : '';
    if ($token === '') return ['state' => 'missing', 'token' => '', 'form_type' => ''];
    if ($method !== 'POST') return ['state' => 'prompt', 'token' => $token, 'form_type' => ''];
    $nonce = $request['post']['bioco_doi_nonce'] ?? '';
    if (!is_string($nonce) || !$verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'bioco_doi_confirm_' . $token)) return ['state' => 'invalid', 'token' => $token, 'form_type' => ''];
    try { $result = $confirm($token); }
    catch (Throwable $error) { return ['state' => 'failure', 'token' => $token, 'form_type' => '']; }
    return ['state' => !empty($result['success']) ? 'success' : 'invalid', 'token' => $token, 'form_type' => is_string($result['form_type'] ?? null) ? $result['form_type'] : ''];
}

function bioco_doi_disable_cache(): void {
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    nocache_headers();
}

// Set headers before the theme emits markup, without evaluating or consuming a token.
add_action('template_redirect', function () {
    $post = get_post();
    if (!$post || !has_block('bioco-divi/doi-controller', $post->post_content)) return;
    $token = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? ($_POST['bioco_doi_token'] ?? '') : ($_GET['token'] ?? '');
    if (is_string($token) && $token !== '') bioco_doi_disable_cache();
}, 1);

/** One evaluation per request prevents duplicated controllers from consuming twice. */
function bioco_doi_current_outcome(): array {
    static $outcome;
    if ($outcome !== null) return $outcome;
    $outcome = bioco_doi_outcome(['method' => $_SERVER['REQUEST_METHOD'] ?? 'GET', 'get' => $_GET, 'post' => $_POST], 'wp_verify_nonce', 'bioco_forms_doi_confirm_token');
    if ($outcome['token'] !== '') {
        bioco_doi_disable_cache();
    }
    return $outcome;
}

function bioco_doi_render_controller(string $mode): string {
    if ($mode !== 'public') return '<div data-bioco-doi-controller data-bioco-doi-state="prompt"></div>';
    $outcome = bioco_doi_current_outcome();
    $html = '<div data-bioco-doi-controller data-bioco-doi-state="' . esc_attr($outcome['state']) . '">';
    if ($outcome['state'] === 'prompt') {
        $id = wp_unique_id('bioco-doi-form-');
        $html .= '<form id="' . esc_attr($id) . '" method="post" action="' . esc_url(get_permalink()) . '" data-bioco-doi-form>'
            . wp_nonce_field('bioco_doi_confirm_' . $outcome['token'], 'bioco_doi_nonce', false, false)
            . '<input type="hidden" name="bioco_doi_token" value="' . esc_attr($outcome['token']) . '"></form>';
    }
    wp_enqueue_script('bioco-native-form-composition');
    return $html . '</div>';
}

/** Keep each state an ordinary Divi row/section whose content and routing are saved natively. */
function bioco_doi_visible_layout(string $html, array $block): string {
    if (bioco_native_render_mode() !== 'public') return $html;
    $class = bioco_native_extract_wrapper($block['attrs'] ?? [])['className'];
    if (!preg_match('/(?:^|\s)bioco-doi-state-(missing|prompt|success|invalid|failure)(?:\s|$)/', $class, $matches)) return $html;
    $post = get_post();
    if (!$post || !has_block('bioco-divi/doi-controller', $post->post_content)) return $html;
    return bioco_doi_current_outcome()['state'] === $matches[1] ? $html : '';
}
add_filter('render_block', 'bioco_doi_visible_layout', 25, 2);
