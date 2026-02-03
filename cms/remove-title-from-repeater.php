<?php
/**
 * Remove redundant 'title' field from content_sections repeater
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Remove Title Field from Repeater ===\n";

    $repeaterFg = $fieldgroups->get('repeater_content_sections');
    if (!$repeaterFg) {
        throw new \Exception("Fieldgroup 'repeater_content_sections' not found");
    }

    $titleField = $fields->get('title');
    if (!$titleField) {
        throw new \Exception("Title field not found");
    }

    if ($repeaterFg->hasField($titleField)) {
        $repeaterFg->remove($titleField);
        $repeaterFg->save();
        $log[] = "✓ Removed 'title' field from content_sections repeater";
        $log[] = "  Now using section_title only (clearer, no confusion)";
    } else {
        $log[] = "  'title' field not in repeater (already removed)";
    }

    $log[] = "\nFinal fields in repeater:";
    foreach ($repeaterFg as $field) {
        $log[] = "  - " . $field->name . " (" . $field->label . ")";
    }

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
