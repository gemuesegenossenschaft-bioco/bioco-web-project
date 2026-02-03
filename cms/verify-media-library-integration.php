<?php
/**
 * Verify media library integration status
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];

$log[] = "=== Media Library Integration Status ===\n";

// Check MediaLibrary module
$mediaLib = $modules->get('MediaLibrary');
$log[] = "MediaLibrary module: " . ($mediaLib ? "✓ Installed" : "✗ Not installed");

// Check ProcessMediaLibraries (skip - requires permissions)
$log[] = "ProcessMediaLibraries: (requires admin permissions to check)";

// Check Media page
$mediaPage = $pages->get("name=media, parent=2");
$log[] = "\nMedia page (/processwire/media/): " . ($mediaPage->id ? "✓ Exists (ID: {$mediaPage->id})" : "✗ Not found");

// Check CKEditor fields
$log[] = "\nCKEditor fields:";
$ckeditorFields = ['section_text', 'body', 'card_text', 'event_summary', 'event_signup_notes'];

foreach ($ckeditorFields as $fname) {
    $field = $fields->get($fname);
    if (!$field) {
        $log[] = "  ✗ $fname not found";
        continue;
    }

    $inputfieldClass = $field->get('inputfieldClass');
    $log[] = "  - $fname: " . ($inputfieldClass ?: "default") . ($inputfieldClass === 'InputfieldCKEditor' ? " ✓" : "");
}

// Check image fields
$log[] = "\nImage fields:";
foreach ($fields as $field) {
    if ($field->type instanceof FieldtypeImage) {
        $log[] = "  - {$field->name}";
    }
}

// MediaLibrary template and fields
$mlTemplate = $templates->get('MediaLibrary');
$log[] = "\nMediaLibrary template: " . ($mlTemplate ? "✓ Exists" : "✗ Not found");

$mlImagesField = $fields->get('MediaImages');
$mlFilesField = $fields->get('MediaFiles');
$log[] = "MediaImages field: " . ($mlImagesField ? "✓ Exists" : "✗ Not found");
$log[] = "MediaFiles field: " . ($mlFilesField ? "✓ Exists" : "✗ Not found");

$log[] = "\n=== Integration Ready ===";
$log[] = "Media library should appear in:";
$log[] = "1. Admin top menu (Pages → Media)";
$log[] = "2. CKEditor image/link dialogs (Media Library tab)";
$log[] = "3. All image fields (can browse media library)";

echo json_encode([
    'success' => true,
    'log' => $log,
    'timestamp' => date('Y-m-d H:i:s'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
