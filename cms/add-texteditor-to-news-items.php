<?php
/**
 * Add full-featured text editor (CKEditor) to news_item template
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Add Text Editor to News Items ===\n";

    // Get news_item template
    $newsTemplate = $templates->get('news_item');
    if (!$newsTemplate) {
        $errors[] = "news_item template not found";
        throw new \Exception("Template required");
    }

    $fg = $newsTemplate->fieldgroup;
    $log[] = "Current fields in news_item:";
    foreach ($fg as $field) {
        $log[] = "  - {$field->name}";
    }

    // Ensure body field uses CKEditor
    $bodyField = $fields->get('body');
    if (!$bodyField) {
        $errors[] = "body field not found";
    } else {
        // Configure for CKEditor
        $bodyField->set('inputfieldClass', 'InputfieldCKEditor');
        $bodyField->set('contentType', 1); // HTML
        $bodyField->label = 'Inhalt';
        $bodyField->description = 'Vollständiger Nachrichteninhalt mit Formatierung';
        $bodyField->set('rows', 20);
        $fields->save($bodyField);
        $log[] = "\n✓ body field configured with CKEditor";
    }

    // Ensure event_summary is also there (for short description)
    $summaryField = $fields->get('event_summary');
    if ($summaryField) {
        $summaryField->set('inputfieldClass', 'InputfieldCKEditor');
        $summaryField->set('contentType', 1);
        $summaryField->label = 'Kurzbeschreibung';
        $summaryField->description = 'Kurze Zusammenfassung für Kartenansicht';
        $summaryField->set('rows', 5);
        $fields->save($summaryField);
        $log[] = "✓ event_summary configured with CKEditor";
    }

    // Add fields to template if missing
    $fieldsToAdd = [
        ['field' => 'event_summary', 'position' => 3],
        ['field' => 'body', 'position' => 4],
    ];

    $log[] = "\nAdding missing fields to news_item...";
    foreach ($fieldsToAdd as $item) {
        $field = $fields->get($item['field']);
        if ($field && !$fg->hasField($field)) {
            $fg->add($field);
            $log[] = "  + Added: {$item['field']}";
        }
    }

    $fg->save();

    // Final field list
    $log[] = "\n=== Final news_item fields ===";
    $fg = $newsTemplate->fieldgroup; // Reload
    foreach ($fg as $field) {
        $inputfield = $field->get('inputfieldClass') ?: 'default';
        $log[] = "  - {$field->name} ({$field->label}) [$inputfield]";
    }

    $log[] = "\n✓ News items now have full CKEditor for content";
    $log[] = "Fields: title, card_image, image_alt, event_summary (short), body (full content)";

    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT);
}
