<?php
/**
 * Debug why MediaLibrary button not showing on image fields
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== MediaLibrary Button Debug ===\n";

    // Check module installation
    $mediaLib = $modules->get('MediaLibrary');
    if (!$mediaLib) {
        $errors[] = "MediaLibrary module not loaded";
        throw new \Exception("MediaLibrary not available");
    }

    $log[] = "✓ MediaLibrary module loaded";

    // Get module configuration
    $moduleConfig = $modules->getModuleConfigData('MediaLibrary');
    $log[] = "\nModule configuration:";
    $log[] = json_encode($moduleConfig, JSON_PRETTY_PRINT);

    // Check if module has settings for image field integration
    $log[] = "\nModule class: " . get_class($mediaLib);

    // Check hooks
    $log[] = "\nChecking module hooks...";
    $hooks = $mediaLib->getHooks();
    $log[] = "Hooks count: " . count($hooks);
    foreach ($hooks as $hook) {
        $log[] = "  - {$hook['method']}";
    }

    // Check one image field in detail
    $testField = $fields->get('section_image');
    if ($testField) {
        $log[] = "\nTest field: section_image";
        $log[] = "  Type: " . $testField->type->className();
        $log[] = "  InputfieldClass: " . $testField->get('inputfieldClass');

        // Get the actual inputfield instance
        $inputfield = $testField->getInputfield(new Page());
        $log[] = "  Inputfield class: " . get_class($inputfield);

        // Check if inputfield has media library button
        $log[] = "  Inputfield properties:";
        foreach ($inputfield->getArray() as $key => $value) {
            if (strpos(strtolower($key), 'media') !== false || strpos(strtolower($key), 'library') !== false) {
                $log[] = "    $key: $value";
            }
        }
    }

    // Check MediaLibrary template and pages
    $mediaPages = $pages->find("template=MediaLibrary");
    $log[] = "\nMedia Library pages: " . count($mediaPages);
    foreach ($mediaPages as $mp) {
        $log[] = "  - {$mp->path} (ID: {$mp->id})";
    }

    $log[] = "\n=== Solution ===";
    $log[] = "MediaLibrary module might need:";
    $log[] = "1. Module configuration: Setup → Modules → MediaLibrary → Configure";
    $log[] = "2. Check 'Enable for image fields' option";
    $log[] = "3. Or module might use different integration method";

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
