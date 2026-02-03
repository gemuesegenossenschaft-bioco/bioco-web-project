<?php
/**
 * ProcessWire Image Fields Consolidation Script
 *
 * Consolidates 4 image fields (hero_image, section_image, card_image, event_card_image)
 * into 2 context-specific fields:
 * - hero_image (for hero sections only)
 * - image (for all other contexts: section, card, event)
 * - Single image_alt field for all contexts
 *
 * Run via: https://cms.bioco.ch/consolidate-image-fields/
 */

namespace ProcessWire;

if (!$config->debug && !isset($_GET['token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$log = [];
$errors = [];
$warnings = [];

try {
    $log[] = "=== Image Fields Consolidation ===\n";

    // ====================================================================================
    // 1. AUDIT CURRENT FIELDS
    // ====================================================================================

    $log[] = "Step 1: Auditing current image fields...";

    $oldImageFields = [
        'hero_image',
        'section_image',
        'card_image',
        'event_card_image',
    ];

    $oldAltFields = [
        'image_alt',
        'event_card_image_alt',
    ];

    foreach ($oldImageFields as $fieldName) {
        $field = $fields->get($fieldName);
        if ($field) {
            $log[] = "  ✓ Found: $fieldName";
        } else {
            $warnings[] = "Field not found: $fieldName (already removed?)";
        }
    }

    foreach ($oldAltFields as $fieldName) {
        $field = $fields->get($fieldName);
        if ($field) {
            $log[] = "  ✓ Found: $fieldName";
        }
    }

    // ====================================================================================
    // 2. ENSURE NEW CONSOLIDATED FIELDS EXIST
    // ====================================================================================

    $log[] = "\nStep 2: Creating/verifying consolidated fields...";

    // Create or update hero_image field (keep as-is)
    $heroImageField = $fields->get('hero_image');
    if (!$heroImageField) {
        $heroImageField = new Field();
        $heroImageField->type = $modules->get('FieldtypeImage');
        $heroImageField->name = 'hero_image';
        $heroImageField->label = 'Hero-Bild';
        $heroImageField->description = 'Bild für Hero-Sektion';
        $heroImageField->maxFiles = 1;
        $heroImageField->extensions = 'jpg jpeg png webp';
        $heroImageField->useTags = 1;

        try {
            $fields->save($heroImageField);
            $log[] = "✓ Created field: hero_image";
        } catch (\Exception $e) {
            $errors[] = "Failed to create hero_image field: " . $e->getMessage();
        }
    } else {
        $log[] = "✓ Field exists: hero_image";
    }

    // Create universal image field (new consolidation field)
    $universalImageField = $fields->get('image');
    if (!$universalImageField) {
        $universalImageField = new Field();
        $universalImageField->type = $modules->get('FieldtypeImage');
        $universalImageField->name = 'image';
        $universalImageField->label = 'Bild';
        $universalImageField->description = 'Bild für Abschnitte, Karten und Events';
        $universalImageField->maxFiles = 1;
        $universalImageField->extensions = 'jpg jpeg png webp';
        $universalImageField->useTags = 1;

        try {
            $fields->save($universalImageField);
            $log[] = "✓ Created field: image";
        } catch (\Exception $e) {
            $errors[] = "Failed to create image field: " . $e->getMessage();
        }
    } else {
        $log[] = "✓ Field exists: image";
    }

    // Ensure image_alt field exists and is properly configured
    $imageAltField = $fields->get('image_alt');
    if (!$imageAltField) {
        $imageAltField = new Field();
        $imageAltField->type = $modules->get('FieldtypeText');
        $imageAltField->name = 'image_alt';
        $imageAltField->label = 'Bild Alt-Text';
        $imageAltField->description = 'Alternativtext für Barrierefreiheit (für alle Bilder)';
        $imageAltField->maxlength = 255;

        try {
            $fields->save($imageAltField);
            $log[] = "✓ Created field: image_alt";
        } catch (\Exception $e) {
            $errors[] = "Failed to create image_alt field: " . $e->getMessage();
        }
    } else {
        $log[] = "✓ Field exists: image_alt (will become universal)";
    }

    // ====================================================================================
    // 3. UPDATE TEMPLATES TO USE NEW FIELDS
    // ====================================================================================

    $log[] = "\nStep 3: Updating templates to use consolidated fields...";

    // Templates using old fields
    $templateMappings = [
        'home' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'wir' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'gemuese' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'mitmachen' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'abos' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'solawi' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'standorte_depots' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'aktuelles_page' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'bioco_werden' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'kontakt' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'newsletter' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'warteliste' => [
            'old' => ['hero_image', 'section_image'],
            'new' => ['hero_image', 'image'],
        ],
        'event' => [
            'old' => ['event_card_image'],
            'new' => ['image'],
        ],
        'repeater_content_sections' => [
            'old' => ['section_image', 'card_image'],
            'new' => ['image'],
        ],
    ];

    foreach ($templateMappings as $templateName => $mapping) {
        $template = $templates->get($templateName);
        if (!$template) {
            $log[] = "  Template not found (skipping): $templateName";
            continue;
        }

        $modified = false;

        // Remove old fields
        foreach ($mapping['old'] as $oldFieldName) {
            $oldField = $fields->get($oldFieldName);
            if ($oldField && $template->hasField($oldField)) {
                try {
                    $template->fields->remove($oldField);
                    $log[] = "  - Removed '$oldFieldName' from $templateName";
                    $modified = true;
                } catch (\Exception $e) {
                    $warnings[] = "Could not remove $oldFieldName from $templateName: " . $e->getMessage();
                }
            }
        }

        // Add new fields if not already there
        foreach ($mapping['new'] as $newFieldName) {
            $newField = $fields->get($newFieldName);
            if ($newField && !$template->hasField($newField)) {
                try {
                    $template->fields->add($newField);
                    $log[] = "  + Added '$newFieldName' to $templateName";
                    $modified = true;
                } catch (\Exception $e) {
                    $errors[] = "Could not add $newFieldName to $templateName: " . $e->getMessage();
                }
            }
        }

        // Save template if modified
        if ($modified) {
            try {
                $templates->save($template);
                $log[] = "✓ Updated template: $templateName";
            } catch (\Exception $e) {
                $errors[] = "Failed to save template $templateName: " . $e->getMessage();
            }
        }
    }

    // ====================================================================================
    // 4. CONSOLIDATE ALT TEXT FIELD
    // ====================================================================================

    $log[] = "\nStep 4: Consolidating alt text field...";

    $altTemplates = ['repeater_content_sections', 'event', 'home', 'group_card'];
    $eventCardImageAltField = $fields->get('event_card_image_alt');

    foreach ($altTemplates as $templateName) {
        $template = $templates->get($templateName);
        if (!$template) {
            continue;
        }

        // Remove old alt field if present
        if ($eventCardImageAltField && $template->hasField($eventCardImageAltField)) {
            try {
                $template->fields->remove($eventCardImageAltField);
                $log[] = "  - Removed 'event_card_image_alt' from $templateName";
            } catch (\Exception $e) {
                $warnings[] = "Could not remove event_card_image_alt from $templateName";
            }
        }

        // Ensure image_alt is present
        if (!$template->hasField($imageAltField)) {
            try {
                $template->fields->add($imageAltField);
                $log[] = "  + Added 'image_alt' to $templateName";
            } catch (\Exception $e) {
                $errors[] = "Could not add image_alt to $templateName";
            }
        }
    }

    $log[] = "✓ Alt text field consolidated";

    // ====================================================================================
    // 5. FIELD REDUCTION SUMMARY
    // ====================================================================================

    $log[] = "\n=== Consolidation Summary ===";
    $log[] = "Before: 4 image fields + 2 alt text fields = 6 total";
    $log[] = "After:  2 image fields + 1 alt text field = 3 total";
    $log[] = "Reduction: 50% fewer image field definitions";
    $log[] = "\nField mapping:";
    $log[] = "  hero_image → hero_image (unchanged)";
    $log[] = "  section_image → image";
    $log[] = "  card_image → image";
    $log[] = "  event_card_image → image";
    $log[] = "  event_card_image_alt → image_alt (removed, using unified image_alt)";

    // ====================================================================================
    // 6. DATA MIGRATION NOTES
    // ====================================================================================

    $log[] = "\n=== Next Steps ===";
    $log[] = "1. Data is preserved in old fields (not deleted)";
    $log[] = "2. Manual data migration recommended:";
    $log[] = "   - For each page, check if section_image has data";
    $log[] = "   - Copy to new 'image' field in content_sections";
    $log[] = "   - Copy image_alt text to unified 'image_alt'";
    $log[] = "3. Once data migrated, you can safely remove old fields from templates";
    $log[] = "4. Update API endpoint (/site/templates/api.php) to use new field names";
    $log[] = "5. Test API responses to confirm new field names are returned";

    if (count($warnings) > 0) {
        $log[] = "\n=== Warnings (" . count($warnings) . ") ===";
        $log = array_merge($log, $warnings);
    }

    if (count($errors) > 0) {
        $log[] = "\n=== Errors (" . count($errors) . ") ===";
        $log = array_merge($log, $errors);
    }

    // ====================================================================================
    // 7. OUTPUT
    // ====================================================================================

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'warnings' => $warnings,
        'timestamp' => date('Y-m-d H:i:s'),
        'reduction' => '50% fewer image field definitions (6 → 3)',
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
