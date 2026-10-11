<?php
/** The shared price source. Presentation and labels remain in Divi layouts. */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_post_type('bioco_price', [
        'labels' => ['name' => __('Preise', 'bioco'), 'singular_name' => __('Abo-Preis', 'bioco')],
        'public' => false, 'show_ui' => true, 'show_in_rest' => true,
        'menu_icon' => 'dashicons-money-alt', 'supports' => ['title', 'page-attributes'],
        'rewrite' => false,
    ]);
});

/** Null means absent/invalid, and a stored integer zero stays zero. */
function bioco_price_integer($value): ?int {
    if (is_int($value)) return $value >= 0 ? $value : null;
    if (!is_string($value) || !preg_match('/^(?:0|[1-9][0-9]*)$/D', $value)) return null;
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return $number === false ? null : $number;
}

/** Null preserves legacy values until the operator explicitly activates this source. */
function bioco_price_catalog(): ?array {
    if (!get_option('bioco_pricing_enabled', false)) return null;
    $share = bioco_price_integer(get_option('bioco_share_price', 250));
    if ($share === null) return ['share_price' => 0, 'tiers' => [], 'error' => 'invalid-share-price'];
    $tiers = []; $keys = [];
    foreach (get_posts(['post_type' => 'bioco_price', 'post_status' => 'publish', 'numberposts' => -1,
        'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']]) as $record) {
        $key = (string) get_post_meta($record->ID, 'price_key', true);
        $type = (string) get_post_meta($record->ID, 'price_abo_type', true);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $key) || $key === 'kein' || isset($keys[$key])
            || !in_array($type, ['halb', 'standard', 'doppel'], true)) {
            return ['share_price' => $share, 'tiers' => [], 'error' => 'invalid-tier-key'];
        }
        $tier = ['ID' => (int) $record->ID, 'key' => $key, 'abo_type' => $type,
            'name' => $record->post_title, 'recommended' => (bool) get_post_meta($record->ID, 'price_recommended', true)];
        foreach (['price', 'shares', 'work', 'persons'] as $field) {
            $number = bioco_price_integer(get_post_meta($record->ID, 'price_' . $field, true));
            if ($number === null) return ['share_price' => $share, 'tiers' => [], 'error' => 'invalid-tier-amount'];
            $tier[$field] = $number;
        }
        $keys[$key] = true;
        $tiers[] = $tier;
    }
    return ['share_price' => $share, 'tiers' => $tiers];
}

/** Stable handoff keys map to existing membership types, never to price labels. */
function bioco_price_abo_type(string $key): ?string {
    if ($key === 'kein') return 'none';
    $catalog = bioco_price_catalog();
    foreach ($catalog['tiers'] ?? [] as $tier) {
        if ($tier['key'] === $key) return $tier['abo_type'];
    }
    return null;
}

function bioco_price_sanitize_share_price($value) {
    $number = bioco_price_integer($value);
    if ($number !== null) return $number;
    add_settings_error('bioco_share_price', 'invalid-share-price', __('Bitte einen Betrag ab 0 CHF eingeben.', 'bioco'));
    return get_option('bioco_share_price', 250);
}

add_action('admin_init', function () {
    register_setting('bioco_pricing', 'bioco_share_price', ['type' => 'integer',
        'sanitize_callback' => 'bioco_price_sanitize_share_price', 'default' => 250]);
    register_setting('bioco_pricing', 'bioco_pricing_enabled', ['type' => 'boolean',
        'sanitize_callback' => static fn($value): bool => (string) $value === '1', 'default' => false]);
});

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=bioco_price', __('Gemeinsame Preise', 'bioco'),
        __('Gemeinsame Preise', 'bioco'), 'manage_options', 'bioco-pricing', 'bioco_price_settings_page');
});

function bioco_price_settings_page(): void {
    if (!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>' . esc_html__('Gemeinsame Preise', 'bioco') . '</h1>';
    echo '<p>' . esc_html__('Abo-Beträge und Anteilsscheine werden hier einmal gepflegt. Beschriftung und Gestaltung stehen in Divi.', 'bioco') . '</p>';
    echo '<form method="post" action="options.php">';
    settings_fields('bioco_pricing');
    echo '<p><label>' . esc_html__('Preis pro Anteilsschein (CHF)', 'bioco') . ' <input type="number" min="0" step="1" name="bioco_share_price" value="' . esc_attr(get_option('bioco_share_price', 250)) . '"></label></p>';
    echo '<p><label><input type="checkbox" name="bioco_pricing_enabled" value="1" ' . checked(get_option('bioco_pricing_enabled', false), true, false) . '> ' . esc_html__('Gemeinsamen Preis-Katalog verwenden', 'bioco') . '</label></p>';
    echo '<p>' . esc_html__('Erst nach dem geprüften Übernehmen der bestehenden Preise aktivieren. Ein leerer aktivierter Katalog bleibt leer.', 'bioco') . '</p>';
    submit_button();
    echo '</form></div>';
}
