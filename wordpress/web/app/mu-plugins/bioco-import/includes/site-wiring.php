<?php
/**
 * Site-level wiring done once import has created the pages: static front
 * page, permalink structure, and a primary navigation menu. Apply-mode only
 * (dry-run reports what WOULD happen); every step is idempotent and safe to
 * re-run.
 */

if (!defined('ABSPATH')) exit;

function bioco_import_wire_timezone($mode, array &$report) {
    $desired = 'Europe/Zurich';
    $current = (string) get_option('timezone_string');
    if ($current === $desired) {
        bioco_import_report_row($report, '(site)', 'timezone', '', 'ok-equal', "Zeitzone bereits {$desired}.");
        return;
    }
    if ($mode !== 'apply') {
        bioco_import_report_row($report, '(site)', 'timezone', '', 'update', "WÜRDE: Zeitzone auf {$desired} setzen.");
        return;
    }
    update_option('timezone_string', $desired);
    bioco_import_report_row($report, '(site)', 'timezone', '', 'update', "Zeitzone auf {$desired} gesetzt.");
}

function bioco_import_wire_front_page(array $seeds, $mode, array &$report) {
    $home = bioco_import_find_page('home');
    if (!$home) {
        bioco_import_report_row($report, '(site)', 'front-page', '', 'warn', "Seite mit Slug 'home' existiert nicht — Startseite kann nicht gesetzt werden (Seiten-Import zuerst ausführen).");
        return;
    }
    $currentShowOnFront = get_option('show_on_front');
    $currentPageOnFront = (int) get_option('page_on_front');
    if ($currentShowOnFront === 'page' && $currentPageOnFront === (int) $home->ID) {
        bioco_import_report_row($report, '(site)', 'front-page', '', 'ok-equal', "Startseite bereits auf 'home' (post_id={$home->ID}) gesetzt.");
        return;
    }
    if ($mode !== 'apply') {
        bioco_import_report_row($report, '(site)', 'front-page', '', 'update', "WÜRDE: Startseite auf 'home' (post_id={$home->ID}) setzen.");
        return;
    }
    update_option('show_on_front', 'page');
    update_option('page_on_front', $home->ID);
    bioco_import_report_row($report, '(site)', 'front-page', '', 'update', "Startseite auf 'home' (post_id={$home->ID}) gesetzt.");
}

function bioco_import_wire_permalinks($mode, array &$report) {
    $desired = '/%postname%/';
    $current = get_option('permalink_structure');
    if ($current === $desired) {
        bioco_import_report_row($report, '(site)', 'permalinks', '', 'ok-equal', 'Permalink-Struktur bereits /%postname%/.');
        return;
    }
    if ($mode !== 'apply') {
        bioco_import_report_row($report, '(site)', 'permalinks', '', 'update', 'WÜRDE: Permalink-Struktur auf /%postname%/ setzen.');
        return;
    }
    update_option('permalink_structure', $desired);
    if (function_exists('flush_rewrite_rules')) flush_rewrite_rules();
    bioco_import_report_row($report, '(site)', 'permalinks', '', 'update', 'Permalink-Struktur auf /%postname%/ gesetzt (Rewrite Rules geflusht).');
}

// Deliberate, documented editorial choice — NOT derivable from the seed
// schema (seeds don't carry a nav-order field). Mirrors bioco.ch's current
// main navigation as of this migration; utility/legal/form pages
// (Datenschutz, Impressum, Statuten, Anmeldung*, Newsletter, Warteliste, Tag
// der offenen Tür, Kundenportal, biocò werden) stay out of the primary nav,
// same as today. Adjust this list here if the live nav changes.
function bioco_import_primary_nav_slugs() {
    return array_column(bioco_primary_navigation_items(), 'slug');
}

function bioco_import_nav_labels() {
    return array_column(bioco_primary_navigation_items(), 'label', 'slug');
}

function bioco_import_wire_primary_menu(array $seeds, $mode, $force, array &$report) {
    $menuName = 'Hauptnavigation';
    $menu = wp_get_nav_menu_object($menuName) ?: null;
    $exists = (bool) $menu;

    if ($exists && !$force) {
        bioco_import_report_row($report, '(site)', 'menu', '', 'skip-existing', "Menü '{$menuName}' existiert bereits (term_id={$menu->term_id}) — Einträge nicht verändert (--force zum Neuaufbau).");
    } elseif ($mode !== 'apply') {
        bioco_import_report_row($report, '(site)', 'menu', '', $exists ? 'update' : 'create',
            'WÜRDE: ' . ($exists ? "Menü '{$menuName}' neu aufbauen (--force)." : "Menü '{$menuName}' anlegen und mit " . count(bioco_import_primary_nav_slugs()) . ' Einträgen befüllen.'));
    } else {
        if (!$exists) {
            $menuId = wp_create_nav_menu($menuName);
            if (is_wp_error($menuId)) {
                bioco_import_report_row($report, '(site)', 'menu', '', 'error', 'Menü konnte nicht angelegt werden: ' . $menuId->get_error_message());
                return;
            }
            $menu = wp_get_nav_menu_object($menuId);
            bioco_import_report_row($report, '(site)', 'menu', '', 'create', "Menü '{$menuName}' angelegt (term_id={$menuId}).");
        } else {
            foreach ((array) wp_get_nav_menu_items($menu->term_id) as $existingItem) {
                wp_delete_post($existingItem->ID, true);
            }
            bioco_import_report_row($report, '(site)', 'menu', '', 'update', "FORCE: bestehende Einträge in '{$menuName}' entfernt, wird neu befüllt.");
        }

        $labels = bioco_import_nav_labels();
        $position = 1;
        foreach (bioco_import_primary_nav_slugs() as $slug) {
            $page = bioco_import_find_page($slug);
            if (!$page) {
                bioco_import_report_row($report, '(site)', 'menu', $slug, 'warn', "Seite '{$slug}' existiert nicht — nicht ins Menü aufgenommen.");
                continue;
            }
            wp_update_nav_menu_item($menu->term_id, 0, [
                'menu-item-title' => $labels[$slug] ?? $page->post_title,
                'menu-item-object-id' => $page->ID,
                'menu-item-object' => 'page',
                'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish',
                'menu-item-position' => $position++,
            ]);
        }
        bioco_import_report_row($report, '(site)', 'menu', '', 'update', "Menü '{$menuName}' befüllt.");
    }

    $locations = get_registered_nav_menus();
    if (!array_key_exists('primary', $locations)) {
        bioco_import_report_row($report, '(site)', 'menu-location', '', 'warn', 'Aktives Theme registriert keine Nav-Menü-Position "primary" (z. B. bevor Divi aktiv ist) — Menü wurde nicht zugewiesen. Nach Aktivierung eines Themes mit "primary"-Position `wp bioco import --mode=apply` erneut ausführen, oder in wp-admin unter Design > Menüs manuell zuweisen.');
        return;
    }
    if (!$menu) return;
    if ($mode !== 'apply') {
        bioco_import_report_row($report, '(site)', 'menu-location', '', 'update', "WÜRDE: Menü der Position 'primary' zuweisen.");
        return;
    }
    $themeLocations = get_theme_mod('nav_menu_locations', []);
    if (!is_array($themeLocations)) $themeLocations = [];
    if (isset($themeLocations['primary']) && (int) $themeLocations['primary'] === (int) $menu->term_id) {
        bioco_import_report_row($report, '(site)', 'menu-location', '', 'ok-equal', "Menü bereits der Position 'primary' zugewiesen.");
        return;
    }
    $themeLocations['primary'] = $menu->term_id;
    set_theme_mod('nav_menu_locations', $themeLocations);
    bioco_import_report_row($report, '(site)', 'menu-location', '', 'update', "Menü der Position 'primary' zugewiesen (term_id={$menu->term_id}).");
}

// Entry point used by the CLI command. Runs after the page import so
// bioco_import_find_page() can resolve the pages the menu links to.
function bioco_import_run_site_wiring(array $seeds, $mode, $force, array &$report) {
    bioco_import_wire_timezone($mode, $report);
    bioco_import_wire_front_page($seeds, $mode, $report);
    bioco_import_wire_permalinks($mode, $report);
    bioco_import_wire_primary_menu($seeds, $mode, $force, $report);
}
