<?php
/**
 * ProcessWire Media Library Setup Script
 *
 * Configures image fields to support a central media library with:
 * - Grid selection from existing images
 * - Tag support for organization
 * - Upload or select options
 *
 * Run via: https://cms.bioco.ch/setup-media-library/
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
    $log[] = "=== Media Library Setup ===\n";

    // ====================================================================================
    // 1. IDENTIFY OR CREATE MEDIA LIBRARY PAGE
    // ====================================================================================

    $log[] = "Step 1: Setting up media library page...";

    $mediaLibrary = $pages->get("name=medien");
    if (!$mediaLibrary->id) {
        // Create media library parent page
        $basicPageTemplate = $templates->get('basic-page');
        if (!$basicPageTemplate) {
            $errors[] = "basic-page template not found. Create it first in ProcessWire admin.";
            throw new \Exception("Cannot create media library without basic-page template");
        }

        $mediaLibrary = new Page();
        $mediaLibrary->template = $basicPageTemplate;
        $mediaLibrary->parent = $pages->get(1); // Root
        $mediaLibrary->name = 'medien';
        $mediaLibrary->title = 'Medienbibliothek';
        $mediaLibrary->status = Page::statusHidden; // Hidden from navigation

        try {
            $pages->save($mediaLibrary);
            $log[] = "✓ Media library page created at /medien/";
        } catch (\Exception $e) {
            $errors[] = "Failed to create media library page: " . $e->getMessage();
        }
    } else {
        $log[] = "✓ Media library page exists at /medien/";
    }

    // ====================================================================================
    // 2. CONFIGURE EXISTING IMAGE FIELDS
    // ====================================================================================

    $log[] = "\nStep 2: Configuring image fields for media library...";

    // List of image fields to configure
    $imageFieldNames = [
        'section_image',
        'card_image',
        'event_card_image',
        'hero_image',
        'og_image'
    ];

    foreach ($imageFieldNames as $fieldName) {
        $field = $fields->get($fieldName);

        if (!$field) {
            $log[] = "Field not found (skipping): $fieldName";
            continue;
        }

        // Enable tag support
        $field->set('useTags', 1);
        $log[] = "  - Enabled tags for: $fieldName";

        // Save field
        try {
            $fields->save($field);
            $log[] = "✓ Updated field: $fieldName";
        } catch (\Exception $e) {
            $errors[] = "Failed to update field $fieldName: " . $e->getMessage();
        }
    }

    // ====================================================================================
    // 3. CREATE OR UPDATE IMAGE_ALT FIELD FOR ACCESSIBILITY
    // ====================================================================================

    $log[] = "\nStep 3: Ensuring image_alt field exists...";

    $imageAltField = $fields->get('image_alt');
    if (!$imageAltField) {
        $imageAltField = new Field();
        $imageAltField->type = $modules->get('FieldtypeText');
        $imageAltField->name = 'image_alt';
        $imageAltField->label = 'Bild Alt-Text';
        $imageAltField->description = 'Alternativtext für Barrierefreiheit';
        $imageAltField->maxlength = 255;

        try {
            $fields->save($imageAltField);
            $log[] = "✓ Created field: image_alt";
        } catch (\Exception $e) {
            $errors[] = "Failed to create image_alt field: " . $e->getMessage();
        }
    } else {
        $log[] = "✓ Field exists: image_alt";
    }

    // ====================================================================================
    // 4. DOCUMENTATION
    // ====================================================================================

    $log[] = "\n=== Setup Complete ===";
    $log[] = "\nNext steps in ProcessWire Admin:";
    $log[] = "1. Navigate to Setup → Fields";
    $log[] = "2. For each image field (section_image, card_image, event_card_image, hero_image):";
    $log[] = "   a. Click the field name to edit";
    $log[] = "   b. Under 'Input' tab, enable 'Grid mode' for better display";
    $log[] = "   c. Under 'Details' tab, set 'Input field type': InputfieldImage";
    $log[] = "   d. Enable 'Multiple files' if needed (maxFiles > 1)";
    $log[] = "3. Optional: Create a new 'Files' page in ProcessWire admin";
    $log[] = "   a. Name: 'medien' (already created at /medien/)";
    $log[] = "   b. Use any template with a Files field";
    $log[] = "   c. Use as central repository for images";
    $log[] = "\nHow to use:";
    $log[] = "- In page edit forms, click 'Upload Files' to add new images";
    $log[] = "- Or use the grid selector to choose from existing images";
    $log[] = "- Add tags to images for easy filtering and reuse";
    $log[] = "- Alt text field ensures accessibility compliance";

    // ====================================================================================
    // 5. OUTPUT
    // ====================================================================================

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
        'mediaLibraryPath' => '/medien/',
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
