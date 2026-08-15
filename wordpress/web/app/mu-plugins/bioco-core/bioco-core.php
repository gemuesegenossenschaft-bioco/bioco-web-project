<?php
/**
 * Plugin Name: bioco Core
 * Description: Theme-agnostic block/ACF/helper infrastructure for bioco blocks (#101). Moved out of the bioco block theme so the presentation theme (Divi) can be swapped without losing content or block behavior. See PORTING-THEME-SWAP.md and HARDCASES.md at the repo root.
 * Author: bioco
 */

if (!defined('ABSPATH')) exit;

define('BIOCO_CORE_DIR', __DIR__);

require_once BIOCO_CORE_DIR . '/includes/helpers.php';
require_once BIOCO_CORE_DIR . '/includes/navigation.php';

/**
 * ACF Local JSON — this plugin's own acf-json/ dir. The fleet move is complete,
 * so only bioco-core may load field groups; theme JSON paths were a temporary
 * migration-window state (see PORTING-THEME-SWAP.md pattern 3).
 */
add_filter('acf/settings/save_json', fn() => BIOCO_CORE_DIR . '/acf-json');
add_filter('acf/settings/load_json', fn() => [BIOCO_CORE_DIR . '/acf-json']);

/**
 * Gallery filter validation: the repeater must contain exactly one "all" row
 * and every technical key must be unique. These invariants are enforced by the
 * UI choices, but server-side validation guards against import/JSON-sync edge
 * cases and keeps the filter JavaScript from receiving ambiguous configuration.
 */
add_filter('acf/validate_value/key=field_bioco_gallery_filters', function ($valid, $value) {
    if ($valid !== true) {
        return $valid;
    }

    $allowed_keys = ['all', 'koerbe', 'feld', 'portraits'];
    if ($value === false || $value === null || $value === '') {
        return __('Es muss genau ein Filter mit dem Schlüssel "all" vorhanden sein.', 'bioco');
    }
    if (!is_array($value)) {
        return __('Die Filterkonfiguration ist ungültig.', 'bioco');
    }

    $rows = array_values($value);
    $seen = [];
    $all_count = 0;

    foreach ($rows as $row) {
        if (!is_array($row)) {
            return __('Die Filterkonfiguration ist ungültig.', 'bioco');
        }
        $raw_key = $row['field_bioco_gallery_filter_key'] ?? ($row['key'] ?? '');
        $key = is_string($raw_key) ? $raw_key : '';

        if (!in_array($key, $allowed_keys, true)) {
            return __('Filter-Schlüssel müssen all, koerbe, feld oder portraits sein.', 'bioco');
        }
        if (isset($seen[$key])) {
            return __('Jeder Filter-Schlüssel darf nur einmal vorkommen.', 'bioco');
        }
        $seen[$key] = true;

        if ($key === 'all') {
            $all_count++;
        }
    }

    if ($all_count !== 1) {
        return __('Es muss genau ein Filter mit dem Schlüssel "all" vorhanden sein.', 'bioco');
    }

    return $valid;
}, 10, 2);

/**
 * Custom "bioco" block category. Moved here in full (not duplicated in the
 * theme) — unlike the ACF path filter and the block-registration glob below,
 * this filter isn't scoped to a directory of not-yet-migrated blocks, so a
 * second copy in the theme would register the category twice.
 */
add_filter('block_categories_all', function (array $categories) {
    return array_merge(
        [
            [
                'slug' => 'bioco',
                'title' => __('biocò', 'bioco'),
                'icon' => 'admin-site-alt3',
            ],
        ],
        $categories
    );
});

/**
 * Block registration: every block.json found one level under this plugin's
 * blocks/ dir. Additive with the block theme's own (shrinking) glob over its
 * own blocks/ dir — disjoint directories, no double registration as long as
 * a given block only lives in one of the two at a time.
 */
add_action('init', function () {
    register_block_type('bioco/primary-navigation', [
        'api_version' => 2,
        'title' => __('biocò Primärnavigation', 'bioco'),
        'category' => 'theme',
        'supports' => ['html' => false],
        'render_callback' => 'bioco_render_primary_navigation',
    ]);
    register_block_type('bioco/site-footer', [
        'api_version' => 2,
        'title' => __('biocò Fusszeile', 'bioco'),
        'category' => 'theme',
        'supports' => ['html' => false],
        'render_callback' => 'bioco_render_site_footer',
    ]);

    $blocks_dir = BIOCO_CORE_DIR . '/blocks';
    if (!is_dir($blocks_dir)) return;
    foreach (glob($blocks_dir . '/*/block.json') as $block_json) {
        register_block_type(dirname($block_json));
    }
});

/**
 * Block CSS: bioco-tokens.css (static --wp--* custom properties, see
 * HARDCASES.md Hard Case 1) enqueued as a hard dependency of bioco-blocks.css
 * so it always loads first — required under Divi, harmless duplication under
 * the block theme. Enqueued on both the front end and the block editor canvas
 * (the theme's original app.css enqueue only covered the front end).
 */
add_action('wp_enqueue_scripts', 'bioco_core_enqueue_block_assets');
add_action('enqueue_block_editor_assets', 'bioco_core_enqueue_block_assets');

function bioco_core_enqueue_block_assets() {
    $tokens_path = BIOCO_CORE_DIR . '/assets/bioco-tokens.css';
    $blocks_path = BIOCO_CORE_DIR . '/assets/bioco-blocks.css';

    if (file_exists($tokens_path)) {
        wp_enqueue_style('bioco-tokens', plugin_dir_url(__FILE__) . 'assets/bioco-tokens.css', [], (string) filemtime($tokens_path));
    }
    if (file_exists($blocks_path)) {
        wp_enqueue_style('bioco-blocks', plugin_dir_url(__FILE__) . 'assets/bioco-blocks.css', ['bioco-tokens'], (string) filemtime($blocks_path));
    }
    $navigation_path = BIOCO_CORE_DIR . '/assets/bioco-navigation.js';
    if (file_exists($navigation_path)) {
        wp_enqueue_script('bioco-navigation', plugin_dir_url(__FILE__) . 'assets/bioco-navigation.js', [], (string) filemtime($navigation_path), true);
    }
}
