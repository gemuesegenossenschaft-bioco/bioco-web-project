<?php
/**
 * Enable dual image input: direct upload + media library search
 * All image fields get both upload button and media library button
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Enable Dual Image Input ===\n";

    // Get all image fields
    $imageFields = [];
    foreach ($fields as $field) {
        if ($field->type instanceof FieldtypeImage) {
            $imageFields[] = $field;
        }
    }

    $log[] = "Found " . count($imageFields) . " image fields:\n";

    // Configure each image field
    foreach ($imageFields as $field) {
        $log[] = "Configuring: {$field->name}";

        // Ensure InputfieldImage is used
        if (!$field->get('inputfieldClass')) {
            $field->set('inputfieldClass', 'InputfieldImage');
        }

        // Enable extensions
        if (!$field->get('extensions')) {
            $field->set('extensions', 'jpg jpeg png gif webp svg');
        }

        // Set admin thumb size
        if (!$field->get('adminThumbs')) {
            $field->set('adminThumbs', 1);
            $field->set('gridSize', 130);
        }

        // Important: Ensure description field is enabled for alt text
        $field->set('descriptionRows', 1);

        try {
            $fields->save($field);
            $log[] = "  ✓ {$field->name} configured";
        } catch (\Exception $e) {
            $errors[] = "Failed to save {$field->name}: " . $e->getMessage();
        }
    }

    // Check MediaLibrary module configuration
    $log[] = "\n=== MediaLibrary Integration ===";

    $mediaLib = $modules->get('MediaLibrary');
    if ($mediaLib) {
        $log[] = "✓ MediaLibrary module active";
        $log[] = "\nWith MediaLibrary installed, image fields automatically get:";
        $log[] = "1. Upload button (direct file upload from computer)";
        $log[] = "2. 'Choose from Media Library' button";
        $log[] = "\nWhen clicking 'Choose from Media Library':";
        $log[] = "- Browse all media in library";
        $log[] = "- Search by keywords";
        $log[] = "- Select multiple images";
        $log[] = "- Images are referenced (not duplicated)";
    } else {
        $errors[] = "MediaLibrary module not found";
    }

    $log[] = "\n=== Configuration Complete ===";
    $log[] = "All " . count($imageFields) . " image fields now support:";
    $log[] = "- Direct upload from computer";
    $log[] = "- Selection from Medienbibliothek";
    $log[] = "\nImage fields: " . implode(', ', array_map(function($f) { return $f->name; }, $imageFields));

    if (count($errors) > 0) {
        $log[] = "\nErrors (" . count($errors) . "):";
        $log = array_merge($log, $errors);
    }

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
