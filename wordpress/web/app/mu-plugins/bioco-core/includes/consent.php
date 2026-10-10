<?php
/** Explicit opt-in for external maps and analytics. No third-party consent service. */
if (!defined('ABSPATH')) exit;

function bioco_consent_texts(): array {
    $defaults = array_fill_keys(['title', 'text', 'analytics', 'maps', 'accept', 'reject', 'save', 'settings', 'map_prompt'], '');
    $saved = get_option('bioco_consent_texts', []);
    foreach ($defaults as $key => $value) {
        if (is_array($saved) && is_string($saved[$key] ?? null)) $defaults[$key] = $saved[$key];
    }
    return $defaults;
}

/** Explicit setup only. Saved values, including empty values, are never replaced. */
function bioco_consent_seed_texts(array $seed, bool $apply = false): array {
    $saved = get_option('bioco_consent_texts', []);
    if (!is_array($saved)) $saved = [];
    foreach (bioco_consent_texts() as $key => $value) {
        if (!array_key_exists($key, $saved)) {
            if (!is_string($seed[$key] ?? null) || trim($seed[$key]) === '') throw new RuntimeException('Missing consent text: ' . $key);
            $saved[$key] = $seed[$key];
        }
    }
    if ($apply) update_option('bioco_consent_texts', $saved, false);
    return $saved;
}

add_action('init', function () {
    $path = dirname(__DIR__) . '/assets/bioco-consent.js';
    wp_register_script('bioco-consent', plugin_dir_url(dirname(__DIR__) . '/bioco-core.php') . 'assets/bioco-consent.js', [], (string) filemtime($path), true);
});
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('bioco-consent');
    wp_add_inline_script('bioco-consent', 'window.biocoConsentTexts = ' . wp_json_encode(bioco_consent_texts(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
    wp_enqueue_style('bioco-consent', plugin_dir_url(dirname(__DIR__) . '/bioco-core.php') . 'assets/bioco-consent.css', [], (string) filemtime(dirname(__DIR__) . '/assets/bioco-consent.css'));
}, 25);

add_action('admin_menu', function () {
    add_menu_page('Datenschutz-Texte', 'Datenschutz-Texte', 'edit_pages', 'bioco-consent', 'bioco_consent_settings_page', 'dashicons-privacy');
});
function bioco_consent_settings_page(): void {
    if (!current_user_can('edit_pages')) wp_die('Keine Berechtigung.', '', ['response' => 403]);
    echo '<div class="wrap"><h1>' . esc_html(get_admin_page_title()) . '</h1><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="bioco_consent_save">';
    wp_nonce_field('bioco_consent_save');
    foreach (bioco_consent_texts() as $key => $value) {
        echo '<p><label>' . esc_html($key) . '<br><textarea class="large-text" name="texts[' . esc_attr($key) . ']" required>' . esc_textarea($value) . '</textarea></label></p>';
    }
    submit_button('Texte speichern');
    echo '</form></div>';
}
add_action('admin_post_bioco_consent_save', function () {
    if (!current_user_can('edit_pages')) wp_die('Keine Berechtigung.', '', ['response' => 403]);
    check_admin_referer('bioco_consent_save');
    $input = wp_unslash($_POST['texts'] ?? []);
    $texts = [];
    foreach (bioco_consent_texts() as $key => $value) {
        $text = is_array($input) && is_string($input[$key] ?? null) ? sanitize_textarea_field($input[$key]) : '';
        if (trim($text) === '') wp_die('Alle Texte ausfüllen.', '', ['response' => 400]);
        $texts[$key] = $text;
    }
    update_option('bioco_consent_texts', $texts, false);
    wp_safe_redirect(admin_url('admin.php?page=bioco-consent'));
    exit;
});
