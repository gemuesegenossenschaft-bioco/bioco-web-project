<?php
/**
 * Plugin Name: bioco mu-plugin loader
 * Description: Loads the bioco mu-plugins that live in subdirectories. Required on vanilla WordPress installs (e.g. installed via Softaculous), which only auto-load FILES at the top level of wp-content/mu-plugins and silently ignore subdirectories.
 * Author: bioco
 *
 * WHY THIS FILE EXISTS
 * ============================================================================
 * WordPress scans wp-content/mu-plugins for *.php at the top level only. A
 * mu-plugin in a subdirectory (bioco-core/bioco-core.php) is never loaded —
 * no error, no warning, it just does not run. Bedrock papers over this with
 * its bedrock-autoloader.php; a Softaculous/vanilla install has no autoloader,
 * so without this file the entire bioco block/field/form layer would be
 * silently absent and every page would render empty.
 *
 * Deployed to: wp-content/mu-plugins/bioco-mu-loader.php (see
 * wordpress/scripts/deploy-wp-code.sh). It is kept OUT of the repo's own
 * mu-plugins directory on purpose, so it cannot double-register under Bedrock,
 * where the autoloader already handles subdirectories.
 *
 * Load order is explicit rather than a glob: bioco-core defines the block
 * registration, shared helpers and the ACF JSON path that the other plugins
 * build on, so it must load first. bioco-import is CLI-only and last.
 */

if (!defined('ABSPATH')) exit;

$bioco_mu_plugins = [
    'bioco-core/bioco-core.php',
    'bioco-content/bioco-content.php',
    'bioco-forms/bioco-forms.php',
    'bioco-import/bioco-import.php',
];

foreach ($bioco_mu_plugins as $bioco_mu_plugin) {
    $bioco_mu_path = __DIR__ . '/' . $bioco_mu_plugin;

    // A missing file must not fatal the whole site. If a deploy only shipped
    // part of the tree, admins still need wp-admin reachable to fix it, so
    // this surfaces as a dashboard notice instead of a white screen.
    if (is_readable($bioco_mu_path)) {
        require_once $bioco_mu_path;
        continue;
    }

    add_action('admin_notices', function () use ($bioco_mu_plugin) {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(sprintf(
                'bioco: mu-plugin "%s" fehlt in wp-content/mu-plugins/. Der Deploy ist unvollstaendig — bitte wordpress/scripts/deploy-wp-code.sh erneut ausfuehren.',
                $bioco_mu_plugin
            ))
        );
    });
}

unset($bioco_mu_plugins, $bioco_mu_plugin, $bioco_mu_path);
