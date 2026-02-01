<?php
/**
 * ProcessWire Event Fields Migration Template
 *
 * Place as: site/templates/migrate-events.php
 * Create template in ProcessWire: Setup → Templates → migrate-events
 * Create page: Pages → Add New → Title: migrate-events → Template: migrate-events
 * Access via: https://cms.bioco.ch/migrate-events/
 *
 * This template file creates/updates all event-related fields and templates.
 */

namespace ProcessWire;

// Security: Only allow in debug mode or with valid token
if (!$config->debug) {
    if (!isset($_GET['token']) || $_GET['token'] !== md5($config->dbName . 'events')) {
        http_response_code(403);
        die('Access denied');
    }
}

header('Content-Type: application/json; charset=utf-8');

$result = [
    'success' => true,
    'log' => [],
    'warnings' => [],
    'errors' => [],
    'timestamp' => date('Y-m-d H:i:s'),
];

try {
    // ====================================================================================
    // FIELD DEFINITIONS
    // ====================================================================================

    $fieldsToCreate = [
        'event_card_image' => [
            'type' => 'FieldtypeImage',
            'label' => 'Kartenbild',
            'description' => 'Bild für Kartendarstellung',
            'extensions' => 'jpg jpeg png webp',
            'maxFiles' => 1,
            'outputFormat' => 'single',
            'skipList' => '',
        ],

        'event_card_image_alt' => [
            'type' => 'FieldtypeText',
            'label' => 'Kartenbild Alt-Text',
            'description' => 'Alternativtext für Barrierefreiheit',
        ],

        'event_status' => [
            'type' => 'FieldtypeOptions',
            'label' => 'Event-Status',
            'description' => 'Automatisch auf "past" gesetzt nach Event-Ende',
            'options' => "upcoming=Kommend|past=Vorbei",
            'optionColumns' => 1,
        ],

        'event_start' => [
            'type' => 'FieldtypeDateTime',
            'label' => 'Event-Startzeit',
            'description' => 'Wann beginnt das Event',
            'dateInputFormat' => 'd.m.Y',
            'timeInputFormat' => 'H:i',
            'defaultToday' => false,
        ],

        'event_end' => [
            'type' => 'FieldtypeDateTime',
            'label' => 'Event-Endzeit',
            'description' => 'Wann endet das Event (triggert Auto-Status-Änderung)',
            'dateInputFormat' => 'd.m.Y',
            'timeInputFormat' => 'H:i',
            'defaultToday' => false,
        ],

        'event_location' => [
            'type' => 'FieldtypeText',
            'label' => 'Veranstaltungsort',
            'description' => 'z.B. Geisshof, Geisslistrasse, 5412 Gebenstorf',
        ],

        'event_summary' => [
            'type' => 'FieldtypeTextarea',
            'label' => 'Kurzbeschreibung',
            'description' => 'Kurze Zusammenfassung für Kartenansicht (2-3 Sätze)',
            'rows' => 3,
            'inputfieldClass' => 'InputfieldTextarea',
        ],

        'event_media' => [
            'type' => 'FieldtypeFile',
            'label' => 'Event-Medien',
            'description' => 'Fotos und Videos vom Event',
            'extensions' => 'jpg jpeg png webp gif mp4 webm',
            'maxFiles' => 50,
            'maxFilesize' => 0, // unlimited
            'uploadOnlyUnpublished' => false,
            'overwrite' => true,
        ],

        'event_signup_enabled' => [
            'type' => 'FieldtypeCheckbox',
            'label' => 'Anmeldung aktivieren',
            'description' => 'Anmeldungsformular anzeigen',
            'checkedValue' => 1,
            'uncheckedValue' => 0,
        ],

        'event_signup_notes' => [
            'type' => 'FieldtypeTextarea',
            'label' => 'Anmeldungshinweise',
            'description' => 'Zusätzliche Informationen für die Anmeldung',
            'rows' => 5,
            'inputfieldClass' => 'InputfieldCKEditor',
            'contentType' => 'html',
        ],
    ];

    // ====================================================================================
    // CREATE/UPDATE FIELDS
    // ====================================================================================

    $result['log'][] = "Verarbeite " . count($fieldsToCreate) . " Felder...";

    foreach ($fieldsToCreate as $fieldName => $fieldConfig) {
        $field = $fields->get($fieldName);

        if (!$field) {
            // Create new field
            $field = new Field();
            $field->type = $modules->get($fieldConfig['type']);
            $field->name = $fieldName;
            $result['log'][] = "→ Erstelle Feld: $fieldName";
        } else {
            $result['log'][] = "→ Feld existiert: $fieldName (aktualisiere)";
        }

        // Apply all config settings
        foreach ($fieldConfig as $key => $value) {
            if ($key !== 'type') {
                try {
                    $field->set($key, $value);
                } catch (\Exception $e) {
                    $result['warnings'][] = "Warnung bei $fieldName.$key: " . $e->getMessage();
                }
            }
        }

        // Save field
        try {
            $fields->save($field);
            $result['log'][] = "✓ Feld gespeichert: $fieldName";
        } catch (\Exception $e) {
            $result['errors'][] = "Fehler beim Speichern von $fieldName: " . $e->getMessage();
            $result['success'] = false;
        }
    }

    // ====================================================================================
    // CREATE/UPDATE EVENT TEMPLATE
    // ====================================================================================

    $result['log'][] = "\nVerarbeite Event-Template...";

    $eventTemplate = $templates->get('event');

    if (!$eventTemplate) {
        $eventTemplate = new Template();
        $eventTemplate->name = 'event';
        $eventTemplate->label = 'Event';

        // Create fieldgroup for new template
        $fieldgroup = new Fieldgroup();
        $fieldgroup->name = 'event';
        $fieldgroups->save($fieldgroup);
        $eventTemplate->fieldgroup = $fieldgroup;

        $result['log'][] = "→ Erstelle Template: event";
    } else {
        $result['log'][] = "→ Template existiert: event (aktualisiere Felder)";
    }

    // Field assignment order
    $fieldOrder = [
        'title' => 'Titel',
        'event_card_image' => 'Kartenbild',
        'event_card_image_alt' => 'Kartenbild Alt-Text',
        'event_status' => 'Event-Status',
        'event_start' => 'Event-Startzeit',
        'event_end' => 'Event-Endzeit',
        'event_location' => 'Veranstaltungsort',
        'event_summary' => 'Kurzbeschreibung',
        'body' => 'Vollständige Beschreibung',
        'event_media' => 'Event-Medien',
        'event_signup_enabled' => 'Anmeldung aktivieren',
        'event_signup_notes' => 'Anmeldungshinweise',
    ];

    // Clear existing fields if overwrite requested
    if (isset($_GET['overwrite']) && $_GET['overwrite'] == '1') {
        $eventTemplate->fieldgroup->removeAll();
        $result['log'][] = "→ Alle Felder entfernt (Overwrite-Modus)";
    }

    // Assign fields to template
    $sort = 0;
    foreach ($fieldOrder as $fieldName => $label) {
        $field = $fields->get($fieldName);

        if (!$field) {
            $result['warnings'][] = "Feld nicht gefunden: $fieldName ($label)";
            continue;
        }

        // Check if field already in template
        if ($eventTemplate->hasField($field)) {
            $result['log'][] = "  • $label (bereits zugewiesen)";
        } else {
            try {
                $eventTemplate->fieldgroup->add($field);
                $result['log'][] = "  + $label (hinzugefügt)";
            } catch (\Exception $e) {
                $result['errors'][] = "Fehler beim Hinzufügen von $fieldName: " . $e->getMessage();
                $result['success'] = false;
                continue;
            }
        }

        // Update sort order
        try {
            $fieldContext = $eventTemplate->fieldgroup->getFieldContext($field);
            if ($fieldContext) {
                $fieldContext->sort = $sort;
                $eventTemplate->fieldgroup->saveContext($field, $fieldContext);
            }
        } catch (\Exception $e) {
            $result['warnings'][] = "Konnte Feldposition nicht speichern für $fieldName: " . $e->getMessage();
        }

        $sort++;
    }

    // Save fieldgroup after adding all fields
    try {
        $fieldgroups->save($eventTemplate->fieldgroup);
    } catch (\Exception $e) {
        $result['errors'][] = "Fehler beim Speichern der Feldgruppe: " . $e->getMessage();
        $result['success'] = false;
    }

    // Save template
    try {
        $templates->save($eventTemplate);
        $result['log'][] = "✓ Template gespeichert: event (" . count($fieldOrder) . " Felder)";
    } catch (\Exception $e) {
        $result['errors'][] = "Fehler beim Speichern des Templates: " . $e->getMessage();
        $result['success'] = false;
    }

    // ====================================================================================
    // CREATE EVENTS PARENT PAGE
    // ====================================================================================

    $result['log'][] = "\nVerarbeite Events-Seite...";

    $eventsPage = $pages->get("name=events");

    if (!$eventsPage->id) {
        try {
            $eventsPage = new Page();
            $eventsPage->template = $templates->get('basic-page') ?: $templates->get('home');
            $eventsPage->parent = $pages->get(1); // Root
            $eventsPage->name = 'events';
            $eventsPage->title = 'Events';
            $eventsPage->status = Page::statusHidden; // Hidden from navigation

            $pages->save($eventsPage);
            $result['log'][] = "✓ Events-Seite erstellt (versteckt)";
        } catch (\Exception $e) {
            $result['warnings'][] = "Konnte Events-Seite nicht erstellen: " . $e->getMessage();
        }
    } else {
        $result['log'][] = "✓ Events-Seite existiert bereits";
    }

    // ====================================================================================
    // SUMMARY
    // ====================================================================================

    $result['log'][] = "\n=== MIGRATION ABGESCHLOSSEN ===";
    $result['log'][] = "Felder: " . count($fieldsToCreate);
    $result['log'][] = "Template: event (bereit)";
    $result['log'][] = "Elternseite: /events/ (erstellt)";

    if (count($result['warnings']) > 0) {
        $result['log'][] = "\nWarnungen: " . count($result['warnings']);
    }

    if (count($result['errors']) > 0) {
        $result['log'][] = "\nFehler: " . count($result['errors']);
    }

    $result['log'][] = "\nNächste Schritte:";
    $result['log'][] = "1. Gehe zu Admin → Pages → Events";
    $result['log'][] = "2. Erstelle dein erstes Event: 'Schnuppertag'";
    $result['log'][] = "3. Fülle alle Felder aus";
    $result['log'][] = "4. Speichern & Veröffentlichen";
    $result['log'][] = "5. Überprüfe /api/events.php auf neue Events";

} catch (\Exception $e) {
    $result['success'] = false;
    $result['errors'][] = "Fatal Error: " . $e->getMessage();
    http_response_code(500);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
