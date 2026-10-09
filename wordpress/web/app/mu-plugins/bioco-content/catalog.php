<?php
/** Shared editor-owned vegetable and depot catalogs. */
if (!defined('ABSPATH')) exit;

function bioco_catalog_type(string $kind): string {
    $types = ['vegetables' => 'bioco_vegetable', 'depots' => 'bioco_depot'];
    if (!isset($types[$kind])) throw new InvalidArgumentException('Unknown catalog: ' . $kind);
    return $types[$kind];
}

add_action('init', function () {
    foreach (['vegetables' => ['Gemüse', 'Gemüse', 'carrot'], 'depots' => ['Depots', 'Depot', 'location']] as $kind => $labels) {
        register_post_type(bioco_catalog_type($kind), [
            'labels' => ['name' => __($labels[0], 'bioco'), 'singular_name' => __($labels[1], 'bioco'), 'edit_item' => __($labels[1] . ' bearbeiten', 'bioco')],
            'public' => false, 'show_ui' => true, 'show_in_rest' => false,
            'menu_icon' => 'dashicons-' . $labels[2], 'supports' => ['title'],
            'rewrite' => false,
        ]);
    }
});

/** null retains module-local data before migration; [] preserves an emptied catalog. */
function bioco_catalog_rows(string $kind): ?array {
    $type = bioco_catalog_type($kind);
    $posts = get_posts(['post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']]);
    if (!$posts && !get_option('bioco_catalog_initialized_' . $kind, false)) {
        $existing = get_posts(['post_type' => $type, 'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'], 'numberposts' => 1]);
        if (!$existing) return null;
    }
    $rows = [];
    foreach ($posts as $post) {
        $row = ['name' => $post->post_title];
        if ($kind === 'vegetables') {
            $months = array_map('intval', (array) get_post_meta($post->ID, 'vegetable_months', true));
            $row['months'] = array_values(array_unique(array_filter($months, static fn($month) => $month >= 1 && $month <= 12)));
        } else {
            $row += ['lat' => (float) get_post_meta($post->ID, 'depot_lat', true), 'lng' => (float) get_post_meta($post->ID, 'depot_lng', true), 'description' => (string) get_post_meta($post->ID, 'depot_description', true)];
        }
        $rows[] = $row;
    }
    return $rows;
}

/** Explicit, resumable initial import. Never overwrite editor changes or resurrect deletions. */
function bioco_catalog_seed(string $kind, bool $apply = false): array {
    $type = bioco_catalog_type($kind);
    $marker = 'bioco_catalog_initialized_' . $kind;
    $pending = 'bioco_catalog_pending_' . $kind;
    $lock = 'bioco_catalog_lock_' . $kind;
    $report = ['created' => 0, 'skipped' => 0, 'adopted' => 0];
    $rows = json_decode(file_get_contents(__DIR__ . '/seeds/' . $kind . '.json'), true, 512, JSON_THROW_ON_ERROR);
    if (get_option($marker, false)) { $report['skipped'] = count($rows); return $report; }
    if ($apply && !add_option($lock, time(), '', false)) throw new RuntimeException('Catalog import already locked: ' . $kind);
    try {
        $existing = get_posts(['post_type' => $type, 'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'], 'numberposts' => -1]);
        if ($existing && !get_option($pending, false)) {
            $report['adopted'] = count($existing);
            if ($apply) update_option($marker, true, false);
            return $report;
        }
        $imported = [];
        foreach ($existing as $post) $imported[(string) get_post_meta($post->ID, '_bioco_catalog_source', true)] = true;
        if ($apply) update_option($pending, true, false);
        foreach ($rows as $index => $row) {
            $source = hash('sha256', $kind . ':' . $row['name']);
            if (isset($imported[$source])) { $report['skipped']++; continue; }
            if ($apply) {
                $meta = ['_bioco_catalog_source' => $source];
                if ($kind === 'vegetables') $meta['vegetable_months'] = $row['months'];
                else $meta += ['depot_lat' => $row['lat'], 'depot_lng' => $row['lng'], 'depot_description' => $row['description']];
                $id = wp_insert_post(['post_type' => $type, 'post_status' => 'publish', 'post_title' => $row['name'], 'menu_order' => $index, 'meta_input' => $meta], true);
                if (is_wp_error($id) || !$id) throw new RuntimeException('Could not create catalog row: ' . $row['name']);
            }
            $report['created']++;
        }
        if ($apply) { update_option($marker, true, false); delete_option($pending); }
        return $report;
    } finally {
        if ($apply) delete_option($lock);
    }
}
