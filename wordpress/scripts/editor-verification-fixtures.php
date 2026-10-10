<?php
/**
 * WP-CLI-only draft fixtures and published-content hashes for editor acceptance.
 * wp eval-file /private/code/editor-verification-fixtures.php inventory
 * wp eval-file /private/code/editor-verification-fixtures.php check
 * wp eval-file /private/code/editor-verification-fixtures.php create <source-id> <run>
 * wp eval-file /private/code/editor-verification-fixtures.php cleanup <run>
 * Never submits forms, creates tokens, uploads media or assigns global templates.
 */
if (!defined('WP_CLI') || !WP_CLI) {
    exit;
}

$action = $args[0] ?? '';
$types = ['page', 'post', 'event', 'group', 'et_header_layout', 'et_footer_layout', 'et_body_layout'];
$template_types = ['et_template', 'et_theme_builder'];
$ignored_meta = ['_edit_lock', '_edit_last'];
// Keep the full metadata hashes too. These fields are recomputed on page visits,
// so distinguish their changes from editorial drift without hiding the evidence.
$derived_meta = ['_divi_dynamic_assets_cached_modules', '_divi_dynamic_assets_canvases_used',
    '_divi_dynamic_assets_cached_feature_used', '_et_builder_post_features_cache'];

if (in_array($action, ['check', 'create'], true)) {
    if (!function_exists('bioco_editor_verification_layouts')
        || has_filter('et_theme_builder_template_layouts', 'bioco_editor_verification_layouts') !== 100) {
        WP_CLI::error('Draft isolation is not loaded; stop editor verification and reconcile the deployed release.');
    }
    if ($action === 'check') {
        WP_CLI::line(wp_json_encode(['isolation' => 'loaded']));
        return;
    }
}

if ($action === 'inventory') {
    $posts = get_posts([
        'post_type' => array_merge($types, $template_types), 'post_status' => 'any',
        'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC',
    ]);
    $result = [];
    foreach ($posts as $post) {
        if ($post->post_status !== 'publish' && !in_array($post->post_type, $template_types, true)) {
            continue;
        }
        $meta = get_post_meta($post->ID);
        foreach ($ignored_meta as $key) {
            unset($meta[$key]);
        }
        ksort($meta);
        $editorial_meta = array_diff_key($meta, array_flip($derived_meta));
        $result[] = [
            'id' => $post->ID, 'type' => $post->post_type, 'status' => $post->post_status,
            'content_hash' => hash('sha256', $post->post_content),
            'meta_hashes' => array_map(static fn($value) => hash('sha256', serialize($value)), $meta),
            'editorial_hash' => hash('sha256', serialize([
                $post->post_title, $post->post_name, $post->post_content,
                $post->post_excerpt, $post->post_parent, $post->menu_order, $editorial_meta,
            ])),
            'hash' => hash('sha256', serialize([
                $post->post_title, $post->post_name, $post->post_content,
                $post->post_excerpt, $post->post_parent, $post->menu_order, $meta,
            ])),
        ];
    }
    WP_CLI::line(wp_json_encode($result));
    return;
}

$run = $action === 'create' ? ($args[2] ?? '') : ($args[1] ?? '');
if (!preg_match('/^[a-z0-9][a-z0-9-]{7,79}$/D', $run)) {
    WP_CLI::error('A unique lowercase verification run identifier is required.');
}

if ($action === 'create') {
    $source = get_post((int) ($args[1] ?? 0));
    if (!$source || !in_array($source->post_type, $types, true)) {
        WP_CLI::error('Source must be content or a layout, never a global assignment.');
    }
    $copy = wp_insert_post([
        'post_type' => $source->post_type, 'post_status' => 'draft',
        'post_title' => 'BIOCO QA ' . $run . ' ' . $source->ID,
        'post_name' => 'bioco-qa-' . $run . '-' . $source->ID,
        'post_content' => wp_slash($source->post_content), 'post_excerpt' => wp_slash($source->post_excerpt),
        'post_parent' => 0, 'comment_status' => 'closed', 'ping_status' => 'closed',
        'meta_input' => ['_bioco_editor_verification' => $run],
    ], true);
    if (is_wp_error($copy)) {
        WP_CLI::error($copy->get_error_message());
    }
    foreach (get_post_meta($source->ID) as $key => $values) {
        if (in_array($key, array_merge($ignored_meta, ['_bioco_editor_verification', '_wp_old_slug']), true)) {
            continue;
        }
        foreach ($values as $value) {
            add_post_meta($copy, $key, wp_slash(maybe_unserialize($value)));
        }
    }
    foreach (get_object_taxonomies($source->post_type) as $taxonomy) {
        $terms = wp_get_object_terms($source->ID, $taxonomy, ['fields' => 'ids']);
        if (!is_wp_error($terms)) {
            wp_set_object_terms($copy, $terms, $taxonomy);
        }
    }
    WP_CLI::line(wp_json_encode(['id' => $copy, 'source' => $source->ID, 'status' => 'draft', 'run' => $run]));
    return;
}

if ($action === 'cleanup') {
    $copies = get_posts([
        'post_type' => $types, 'post_status' => array_keys(get_post_stati()), 'posts_per_page' => -1,
        'meta_key' => '_bioco_editor_verification', 'meta_value' => $run,
    ]);
    // Validate the entire set before deleting anything.
    foreach ($copies as $copy) {
        if (!in_array($copy->post_status, ['draft', 'auto-draft', 'trash'], true)
            || !str_starts_with($copy->post_title, 'BIOCO QA ' . $run . ' ')) {
            WP_CLI::error('Refusing to delete a published or unrecognized verification copy.');
        }
    }
    $deleted = [];
    foreach ($copies as $copy) {
        if (!wp_delete_post($copy->ID, true)) {
            WP_CLI::error('Could not delete verification copy ' . $copy->ID);
        }
        $deleted[] = $copy->ID;
    }
    WP_CLI::line(wp_json_encode(['deleted' => $deleted, 'run' => $run]));
    return;
}

WP_CLI::error('Expected check, inventory, create or cleanup.');
