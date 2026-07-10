<?php
/**
 * Plugin Name: bioco Content
 * Description: Registers the 'event' and 'bioco_group' CPTs (W8, issue #95). ACF field groups for both live as Local JSON in the theme's acf-json/ directory, next to the block field groups.
 * Author: bioco
 */

if (!defined('ABSPATH')) exit;

/**
 * Events (Aktuelles/Veranstaltungen). Single events live under /aktuelles/<slug>
 * so URLs match the site's existing "Aktuelles" navigation entry.
 *
 * The "Rückblick" toggle mentioned in issue #95 is intentionally just the
 * event_status select field (Bevorstehend/Vergangen) on the ACF field group:
 * an editor flips a past event to "Vergangen" to move it into the recap grid.
 * No extra admin UI/button is needed for that.
 */
add_action('init', function () {
    register_post_type('event', [
        'labels' => [
            'name' => __('Veranstaltungen', 'bioco'),
            'singular_name' => __('Veranstaltung', 'bioco'),
            'add_new' => __('Neue Veranstaltung', 'bioco'),
            'add_new_item' => __('Neue Veranstaltung erstellen', 'bioco'),
            'edit_item' => __('Veranstaltung bearbeiten', 'bioco'),
            'new_item' => __('Neue Veranstaltung', 'bioco'),
            'view_item' => __('Veranstaltung ansehen', 'bioco'),
            'view_items' => __('Veranstaltungen ansehen', 'bioco'),
            'search_items' => __('Veranstaltungen durchsuchen', 'bioco'),
            'not_found' => __('Keine Veranstaltungen gefunden', 'bioco'),
            'not_found_in_trash' => __('Keine Veranstaltungen im Papierkorb gefunden', 'bioco'),
            'all_items' => __('Alle Veranstaltungen', 'bioco'),
            'menu_name' => __('Veranstaltungen', 'bioco'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title', 'editor', 'thumbnail'],
        'has_archive' => 'aktuelles',
        'rewrite' => ['slug' => 'aktuelles', 'with_front' => false],
    ]);

    register_post_type('bioco_group', [
        'labels' => [
            'name' => __('Gruppen', 'bioco'),
            'singular_name' => __('Gruppe', 'bioco'),
            'add_new' => __('Neue Gruppe', 'bioco'),
            'add_new_item' => __('Neue Gruppe erstellen', 'bioco'),
            'edit_item' => __('Gruppe bearbeiten', 'bioco'),
            'new_item' => __('Neue Gruppe', 'bioco'),
            'view_item' => __('Gruppe ansehen', 'bioco'),
            'view_items' => __('Gruppen ansehen', 'bioco'),
            'search_items' => __('Gruppen durchsuchen', 'bioco'),
            'not_found' => __('Keine Gruppen gefunden', 'bioco'),
            'not_found_in_trash' => __('Keine Gruppen im Papierkorb gefunden', 'bioco'),
            'all_items' => __('Alle Gruppen', 'bioco'),
            'menu_name' => __('Gruppen', 'bioco'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-groups',
        'supports' => ['title', 'thumbnail'],
        'has_archive' => false,
        'rewrite' => false,
    ]);
});
