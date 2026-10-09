<?php
/** CLI-only: wp eval-file wordpress/scripts/archive-cms-source.php /private/recovery/cms-api [apply] */
if (PHP_SAPI !== 'cli' || !defined('WP_CLI') || !WP_CLI) exit(1);

function bioco_archive_cms_source(string $directory, bool $apply): array {
    $directory = realpath($directory);
    if (!$directory || !is_dir($directory)) throw new RuntimeException('Explicit recovery directory required.');
    $plan = [];
    foreach (glob($directory . '/*.json') as $file) {
        $raw = file_get_contents($file);
        $source = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        // Content-only /content/sections/<page> shape from the supplied safe sample.
        if (!is_array($source) || !is_string($source['page'] ?? null)
            || !preg_match('~^[a-z0-9-]+(?:/[a-z0-9-]+)*$~D', $source['page'])
            || !is_array($source['sections'] ?? null) || !array_is_list($source['sections'])
            || !is_array($source['seo'] ?? null)) throw new RuntimeException('Unsupported CMS source shape.');
        foreach (['title', 'description'] as $field) {
            if (isset($source['seo'][$field]) && !is_string($source['seo'][$field])) throw new RuntimeException('Unsupported SEO shape.');
        }
        $post = get_page_by_path($source['page'], OBJECT, 'page');
        if (!$post) throw new RuntimeException('CMS page has no existing WordPress page.');
        $plan[] = [$post->ID, $raw, $source];
    }
    if (!$plan) throw new RuntimeException('No source JSON files.');
    // Validate the complete directory before any writes. Versions never overwrite.
    $counts = ['pages' => count($plan), 'archives_added' => 0, 'seo_filled' => 0];
    foreach ($plan as [$id, $raw, $source]) {
        $archive_key = '_bioco_cms_source_' . hash('sha256', $raw);
        if (!metadata_exists('post', $id, $archive_key)) {
            if ($apply && !add_post_meta($id, $archive_key, wp_slash($raw), true)) throw new RuntimeException('Archive write failed.');
            $counts['archives_added']++;
        }
        foreach (['title' => 'rank_math_title', 'description' => 'rank_math_description'] as $field => $key) {
            $value = $source['seo'][$field] ?? '';
            if (trim($value) === '' || get_post_meta($id, $key, true) !== '') continue;
            // Do not silently replace an existing empty metadata row. Only
            // fill it conditionally if it is still empty at write time.
            if ($apply) {
                if (metadata_exists('post', $id, $key)) {
                    global $wpdb;
                    $written = $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = %s AND meta_value = ''",
                        $value, $id, $key
                    ));
                    wp_cache_delete($id, 'post_meta');
                } else {
                    $written = add_post_meta($id, $key, wp_slash($value), true);
                }
                if (!$written) throw new RuntimeException('SEO write failed; inspect metadata before retry.');
            }
            $counts['seo_filled']++;
        }
    }
    return $counts;
}

if (isset($args)) {
    try {
        if (count($args) < 1 || count($args) > 2 || (isset($args[1]) && $args[1] !== 'apply')) throw new RuntimeException('Usage: <private cms-api directory> [apply]');
        $counts = bioco_archive_cms_source($args[0], ($args[1] ?? '') === 'apply');
        WP_CLI::log(wp_json_encode($counts));
    } catch (Throwable $error) {
        WP_CLI::error($error->getMessage());
    }
}
