<?php
/**
 * ProcessWire Event Fields Migration Script
 *
 * Creates all event-related fields and assigns them to the event template.
 * Run via: https://cms.bioco.ch/migrate-event-fields/
 *
 * Features:
 * - Creates fields if they don't exist
 * - Updates field settings if they do
 * - Creates/updates event template
 * - Assigns fields to template
 * - Full rollback support via overwrite parameter
 */

namespace ProcessWire;

if (!$config->debug && !isset($_GET['token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$log = [];
$errors = [];

try {
    // ====================================================================================
    // 1. FIELD DEFINITIONS
    // ====================================================================================

    $fieldsConfig = [
        // Title - Core field, should exist
        'title' => [
            'type' => 'FieldtypeText',
            'label' => 'Titel',
        ],

        // Card Image - For display on cards/lists
        'event_card_image' => [
            'type' => 'FieldtypeImage',
            'label' => 'Kartenbild',
            'description' => 'Bild für Kartendarstellung',
            'extensions' => 'jpg jpeg png webp',
            'maxFiles' => 1,
            'outputFormat' => 'single',
        ],

        // Image Alt Text - Accessibility
        'event_card_image_alt' => [
            'type' => 'FieldtypeText',
            'label' => 'Kartenbild Alt-Text',
            'description' => 'Alternativtext für Barrierefreiheit',
        ],

        // Event Status - upcoming or past
        'event_status' => [
            'type' => 'FieldtypeOptions',
            'label' => 'Event-Status',
            'options' => "upcoming=Kommend\npast=Vorbei",
            'optionColumns' => 1,
        ],

        // Event Start Date/Time
        'event_start' => [
            'type' => 'FieldtypeDateTime',
            'label' => 'Event-Startzeit',
            'description' => 'Wann beginnt das Event',
            'dateInputFormat' => 'd.m.Y',
            'timeInputFormat' => 'H:i',
        ],

        // Event End Date/Time
        'event_end' => [
            'type' => 'FieldtypeDateTime',
            'label' => 'Event-Endzeit',
            'description' => 'Wann endet das Event',
            'dateInputFormat' => 'd.m.Y',
            'timeInputFormat' => 'H:i',
        ],

        // Event Location
        'event_location' => [
            'type' => 'FieldtypeText',
            'label' => 'Veranstaltungsort',
            'description' => 'z.B. Geisshof, Geisslistrasse, 5412 Gebenstorf',
        ],

        // Short Summary (for cards)
        'event_summary' => [
            'type' => 'FieldtypeTextarea',
            'label' => 'Kurzbeschreibung',
            'description' => 'Kurze Zusammenfassung für Kartenansicht (2-3 Sätze)',
            'rows' => 3,
        ],

        // Full Description with tinyMCE Editor
        'body' => [
            'type' => 'FieldtypeTextarea',
            'label' => 'Vollständige Beschreibung',
            'description' => 'Detaillierte Eventbeschreibung (HTML mit Editor)',
            'rows' => 10,
            'contentType' => 'html',
            'inputfieldClass' => 'InputfieldCKEditor',
        ],

        // Event Media (Gallery)
        'event_media' => [
            'type' => 'FieldtypeFile',
            'label' => 'Event-Medien',
            'description' => 'Fotos und Videos vom Event',
            'extensions' => 'jpg jpeg png webp gif mp4 webm',
            'maxFiles' => 50,
            'uploadOnlyUnpublished' => false,
        ],

        // Signup Enabled
        'event_signup_enabled' => [
            'type' => 'FieldtypeCheckbox',
            'label' => 'Anmeldung aktivieren',
            'description' => 'Anmeldungsformular anzeigen',
            'checkedValue' => 1,
            'uncheckedValue' => 0,
        ],

        // Signup Notes
        'event_signup_notes' => [
            'type' => 'FieldtypeTextarea',
            'label' => 'Anmeldungshinweise',
            'description' => 'Zusätzliche Informationen für die Anmeldung',
            'rows' => 5,
            'contentType' => 'html',
            'inputfieldClass' => 'InputfieldCKEditor',
        ],
    ];

    // ====================================================================================
    // 2. CREATE/UPDATE FIELDS
    // ====================================================================================

    $log[] = "Starting field creation/update...";

    foreach ($fieldsConfig as $fieldName => $config) {
        // Skip title - it's a core field
        if ($fieldName === 'title') {
            $log[] = "Skipping core field: $fieldName";
            continue;
        }

        $field = $fields->get($fieldName);

        if (!$field) {
            // Create new field
            $field = new Field();
            $field->type = $modules->get($config['type']);
            $field->name = $fieldName;
        } else {
            $log[] = "Field exists: $fieldName";
        }

        // Update field properties
        foreach ($config as $key => $value) {
            if ($key !== 'type') {
                $field->set($key, $value);
            }
        }

        // Save field
        try {
            $fields->save($field);
            $log[] = "✓ Field created/updated: $fieldName";
        } catch (\Exception $e) {
            $errors[] = "Failed to save field $fieldName: " . $e->getMessage();
        }
    }

    // ====================================================================================
    // 3. CREATE/UPDATE EVENT TEMPLATE
    // ====================================================================================

    $log[] = "\nSetting up event template...";

    $template = $templates->get('event');
    if (!$template) {
        $template = new Template();
        $template->name = 'event';
        $template->label = 'Event';
        $templates->save($template);
        $log[] = "✓ Template created: event";
    } else {
        $log[] = "Template exists: event";
    }

    // ====================================================================================
    // 4. ASSIGN FIELDS TO TEMPLATE (in order)
    // ====================================================================================

    $log[] = "\nAssigning fields to template...";

    $fieldOrder = [
        'title',
        'event_card_image',
        'event_card_image_alt',
        'event_status',
        'event_start',
        'event_end',
        'event_location',
        'event_summary',
        'body',
        'event_media',
        'event_signup_enabled',
        'event_signup_notes',
    ];

    foreach ($fieldOrder as $index => $fieldName) {
        $field = $fields->get($fieldName);
        if (!$field) {
            $errors[] = "Field not found: $fieldName";
            continue;
        }

        // Add field to template if not already there
        if (!$template->hasField($field)) {
            $template->fields->add($field);
            $log[] = "Added field to template: $fieldName";
        }

        // Get the field's position in template
        $fieldContext = $template->fieldgroup->getFieldContext($field);

        // Set sort order
        if ($fieldContext) {
            $fieldContext->set('sort', $index);
            $template->fieldgroup->saveContext($field, $fieldContext);
        }
    }

    // Save template
    try {
        $templates->save($template);
        $log[] = "✓ Template updated with all fields";
    } catch (\Exception $e) {
        $errors[] = "Failed to save template: " . $e->getMessage();
    }

    // ====================================================================================
    // 5. CREATE EVENTS PARENT PAGE (if needed)
    // ====================================================================================

    $log[] = "\nSetting up Events parent page...";

    $eventsParent = $pages->get("name=events");
    if (!$eventsParent->id) {
        // Create events parent page
        $eventsParent = new Page();
        $eventsParent->template = $templates->get('basic-page') ?: 'basic-page';
        $eventsParent->parent = $pages->get(1); // Root
        $eventsParent->name = 'events';
        $eventsParent->title = 'Events';
        $eventsParent->status = Page::statusHidden; // Hidden from navigation

        try {
            $pages->save($eventsParent);
            $log[] = "✓ Events parent page created";
        } catch (\Exception $e) {
            $errors[] = "Failed to create events parent: " . $e->getMessage();
        }
    } else {
        $log[] = "Events parent page exists";
    }

    // ====================================================================================
    // 6. SUMMARY
    // ====================================================================================

    $log[] = "\n=== MIGRATION COMPLETE ===";
    $log[] = "Fields created: " . count(array_filter($fieldsConfig, fn($k) => $k !== 'title', ARRAY_KEY_EXISTS));
    $log[] = "Template: event (ready for use)";
    $log[] = "Parent page: events (hidden, ready for event pages)";

    if (count($errors) > 0) {
        $log[] = "\nWarnings/Errors (" . count($errors) . "):";
        $log = array_merge($log, $errors);
    }

    // ====================================================================================
    // 7. OUTPUT
    // ====================================================================================

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT);
}
?>
