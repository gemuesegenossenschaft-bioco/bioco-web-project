<?php
namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];
$changed = [];
$unchanged = [];

try {
    $log[] = "=== Enable Multiple Image Fields ===";

    foreach ($fields as $field) {
        if (!($field->type instanceof FieldtypeImage)) continue;

        $name = (string)$field->name;
        $current = (int)$field->maxFiles;

        // Keep existing multi/unlimited settings; upgrade only strict single-file fields.
        if ($current === 1) {
            $field->maxFiles = 50;
            if (!$field->inputfieldClass) {
                $field->inputfieldClass = 'InputfieldImage';
            }
            $fields->save($field);
            $changed[] = [
                'field' => $name,
                'oldMaxFiles' => $current,
                'newMaxFiles' => (int)$field->maxFiles,
            ];
            continue;
        }

        $unchanged[] = [
            'field' => $name,
            'maxFiles' => $current,
        ];
    }

    $log[] = "Updated single-file image fields to maxFiles=50";
} catch (\Throwable $e) {
    $errors[] = $e->getMessage();
}

echo json_encode([
    'success' => count($errors) === 0,
    'summary' => [
        'changedCount' => count($changed),
        'unchangedCount' => count($unchanged),
    ],
    'changed' => $changed,
    'unchanged' => $unchanged,
    'log' => $log,
    'errors' => $errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
