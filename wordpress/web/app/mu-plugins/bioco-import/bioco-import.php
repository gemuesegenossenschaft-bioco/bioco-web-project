<?php
/**
 * Plugin Name: bioco Import
 * Description: Scripted, idempotent content importer for the WordPress migration (W11, issue #98). Replaces manual Divi page-authoring with `wp bioco import` / `wp bioco verify`. Reads wordpress/content-seed/*.json (same seeds that drove the ProcessWire content-freeze migration and the Next.js parity tests) and writes bioco/* ACF block markup into WP pages, never touching non-empty existing content unless --force.
 * Author: bioco
 */

if (!defined('ABSPATH')) exit;

define('BIOCO_IMPORT_DIR', __DIR__);
define('BIOCO_IMPORT_BLOCKS_DIR', dirname(__DIR__) . '/bioco-core/blocks');
define('BIOCO_IMPORT_CORE_INCLUDES_DIR', dirname(__DIR__) . '/bioco-core/includes');

// Seed directory resolution must work under BOTH supported layouts, because
// the same mu-plugin ships to both:
//
//   Bedrock          wordpress/web/app/mu-plugins/bioco-import  -> ../../../../content-seed
//   vanilla WP       wp-content/mu-plugins/bioco-import         -> no such tree
//
// The staging host (Tophost cPanel + Softaculous) installs vanilla WordPress,
// where walking four levels up lands outside the webroot entirely. So the
// deploy script copies the seeds into the plugin's own content-seed/ and that
// is probed first; the Bedrock-relative path stays as the checkout fallback so
// `wp bioco import` still works when run from a Bedrock tree. Both are checked
// at load time, and --seed-dir always overrides.
$biocoImportSeedCandidates = [
    __DIR__ . '/content-seed',
    dirname(dirname(dirname(dirname(__DIR__)))) . '/content-seed',
];
$biocoImportSeedDir = $biocoImportSeedCandidates[0];
foreach ($biocoImportSeedCandidates as $biocoImportSeedCandidate) {
    if (is_dir($biocoImportSeedCandidate)) {
        $biocoImportSeedDir = $biocoImportSeedCandidate;
        break;
    }
}
define('BIOCO_IMPORT_DEFAULT_SEED_DIR', $biocoImportSeedDir);
unset($biocoImportSeedCandidates, $biocoImportSeedCandidate, $biocoImportSeedDir);

require_once BIOCO_IMPORT_DIR . '/includes/seeds.php';
require_once BIOCO_IMPORT_DIR . '/includes/acf-fields.php';
require_once BIOCO_IMPORT_DIR . '/includes/section-map.php';
require_once BIOCO_IMPORT_DIR . '/includes/report.php';
require_once BIOCO_IMPORT_DIR . '/includes/pages.php';
require_once BIOCO_IMPORT_DIR . '/includes/verify.php';
require_once BIOCO_IMPORT_DIR . '/includes/collections.php';
require_once BIOCO_IMPORT_DIR . '/includes/site-wiring.php';
require_once BIOCO_IMPORT_CORE_INCLUDES_DIR . '/dynamic-sections.php';
require_once BIOCO_IMPORT_DIR . '/includes/divi-blocks.php';
require_once BIOCO_IMPORT_DIR . '/includes/divi-composer.php';

// WP-CLI is the only place this plugin registers a "command" — every include
// above is plain functions, callable/unit-reviewable without a WP-CLI
// bootstrap. This file just wires WP_CLI::add_command() to those functions.
if (defined('WP_CLI') && WP_CLI) {
    require_once BIOCO_IMPORT_DIR . '/includes/cli.php';
    WP_CLI::add_command('bioco', 'Bioco_Import_CLI_Command');
}
