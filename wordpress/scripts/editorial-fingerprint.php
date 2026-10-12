<?php
// Passed to `wp eval` by the production release wrapper. Emit only a digest.
global $wpdb;
$hash = hash_init('sha256');
$tables = [
    [$wpdb->posts, 'ID'],
    [$wpdb->postmeta, 'meta_id'],
    [$wpdb->terms, 'term_id'],
    [$wpdb->term_taxonomy, 'term_taxonomy_id'],
    [$wpdb->term_relationships, 'object_id, term_taxonomy_id'],
    [$wpdb->termmeta, 'meta_id'],
    [$wpdb->options, 'option_name'],
];
$volatileMeta = [
    '_edit_lock', '_edit_last',
    '_divi_dynamic_assets_cached_modules', '_divi_dynamic_assets_canvases_used',
    '_divi_dynamic_assets_cached_feature_used', '_et_builder_post_features_cache',
];
foreach ($tables as [$table, $order]) {
    // Table and column names come only from WordPress, never from release inputs.
    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY {$order}", ARRAY_A);
    if (!is_array($rows)) {
        throw new RuntimeException("Could not read editorial table {$table}");
    }
    hash_update($hash, $table . "\n");
    foreach ($rows as $row) {
        if ($table === $wpdb->postmeta && in_array($row['meta_key'], $volatileMeta, true)) {
            continue;
        }
        if ($table === $wpdb->options) {
            $name = $row['option_name'];
            if (!preg_match('/^(theme_mods_|widget_|options_|bioco_price_|bioco_catalog_)/', $name)
                && !in_array($name, [
                    'sidebars_widgets', 'nav_menu_options', 'et_divi', 'et_pb_builder_options',
                    'et_divi_builder_global_presets_d5', 'et_divi_builder_global_presets_ng',
                    'et_divi_global_variables', 'bioco_form_messages', 'bioco_membership_adapter',
                    'bioco_pricing_enabled', 'bioco_share_price', 'bioco_divi_form_message_groups',
                ], true)) {
                continue;
            }
            $row = ['option_name' => $name, 'option_value' => $row['option_value']];
        }
        hash_update($hash, serialize($row));
    }
}
echo hash_final($hash);
