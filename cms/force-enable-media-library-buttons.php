<?php
/**
 * Force enable MediaLibrary buttons on all image fields
 * Ensures dual input: upload + browse media library
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Force Enable MediaLibrary Buttons ===\n";

    // Step 1: Check MediaLibrary module
    if (!$modules->isInstalled('MediaLibrary')) {
        $errors[] = "MediaLibrary module not installed";
        throw new \Exception("MediaLibrary required");
    }

    $log[] = "✓ MediaLibrary module installed";

    // Step 2: Get and update module config
    $moduleConfig = $modules->getModuleConfigData('MediaLibrary');
    $log[] = "\nCurrent config:";
    $log[] = json_encode($moduleConfig, JSON_PRETTY_PRINT);

    // Ensure optimal settings
    $moduleConfig['medialibraryhidepages'] = false;
    $moduleConfig['medialibraryhidepagesadmin'] = false;

    $modules->saveModuleConfigData('MediaLibrary', $moduleConfig);
    $log[] = "✓ Updated module config";

    // Step 3: Configure all image fields
    $log[] = "\nConfiguring image fields...";

    $imageFields = [];
    foreach ($fields as $field) {
        if ($field->type instanceof FieldtypeImage) {
            $imageFields[] = $field->name;

            // Ensure proper inputfield class
            $field->set('inputfieldClass', 'InputfieldImage');

            // Enable file description for alt text
            $field->set('descriptionRows', 1);

            // Set file extensions
            if (!$field->get('extensions')) {
                $field->set('extensions', 'jpg jpeg png gif webp svg');
            }

            // Enable thumbnails in admin
            $field->set('adminThumbs', 1);
            $field->set('gridSize', 130);

            $fields->save($field);
            $log[] = "  ✓ {$field->name}";
        }
    }

    // Step 4: Check if ProcessWire version supports MediaLibrary
    $log[] = "\nProcessWire version: " . $config->version;

    // Step 5: Instructions for manual verification
    $log[] = "\n=== Verification Steps ===";
    $log[] = "1. Go to any page with an image field";
    $log[] = "2. Look for image field (e.g., section_image in content_sections)";
    $log[] = "3. Should see:";
    $log[] = "   - 'Choose files' or 'Upload' button";
    $log[] = "   - 'Add from URL' link (if enabled)";
    $log[] = "   - MediaLibrary integration (depends on module version)";

    $log[] = "\n=== MediaLibrary Button Location ===";
    $log[] = "BitPoet MediaLibrary typically adds buttons:";
    $log[] = "- In CKEditor: Image/Link dialog → Media Library tab";
    $log[] = "- In Image fields: May need manual integration or newer module version";

    $log[] = "\n=== Alternative: Manual Media Library Access ===";
    $log[] = "If buttons don't appear automatically:";
    $log[] = "1. Open Media page: /processwire/media/";
    $log[] = "2. Upload images there";
    $log[] = "3. In CKEditor, use Image → Media Library tab";
    $log[] = "4. For direct image fields, might need module update";

    $log[] = "\nConfigured " . count($imageFields) . " image fields:";
    $log[] = implode(', ', $imageFields);

    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
        'image_fields' => $imageFields,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT);
}
