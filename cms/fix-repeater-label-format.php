<?php
/**
 * Fix repeater label format to show actual section_title value
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Fix Repeater Label Format ===\n";

    $contentSectionsField = $fields->get('content_sections');
    if (!$contentSectionsField) {
        throw new \Exception("Field 'content_sections' not found");
    }

    // Set correct label format with curly braces
    $contentSectionsField->set('repeaterTitle', '{section_title}');
    $fields->save($contentSectionsField);

    $log[] = "✓ Repeater label format updated";
    $log[] = "  Format: {section_title}";
    $log[] = "  Now shows actual title value instead of field name";

    echo json_encode([
        'success' => true,
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
