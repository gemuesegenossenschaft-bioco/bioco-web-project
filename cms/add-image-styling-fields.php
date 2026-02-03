<?php
/**
 * ProcessWire Image Styling Fields Script
 *
 * Adds overlay/tint and background color options for sections:
 * 1. section_image_overlay - none, dark, green, orange
 * 2. section_bg_color - none, green, darkgreen, orange, gray, white
 *
 * Run via: https://cms.bioco.ch/add-image-styling-fields/
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
    $log[] = "=== Image Styling Fields Setup ===\n";

    // ====================================================================================
    // 1. CREATE IMAGE OVERLAY FIELD
    // ====================================================================================

    $log[] = "Step 1: Creating image overlay field...";

    $imageOverlayField = $fields->get('section_image_overlay');
    if (!$imageOverlayField) {
        $imageOverlayField = new Field();
        $imageOverlayField->type = $modules->get('FieldtypeOptions');
        $imageOverlayField->name = 'section_image_overlay';
        $imageOverlayField->label = 'Bild-Overlay';
        $imageOverlayField->description = 'Überlagern Sie das Bild mit einer Farbtönung';
        $imageOverlayField->options = "none|Kein Overlay\ndark|Dunkel\ngreen|Grün\norange|Orange";
        $imageOverlayField->optionColumns = 1;
        $imageOverlayField->defaultValue = 'none';

        try {
            $fields->save($imageOverlayField);
            $log[] = "✓ Created field: section_image_overlay";
        } catch (\Exception $e) {
            $errors[] = "Failed to create section_image_overlay field: " . $e->getMessage();
        }
    } else {
        $log[] = "✓ Field exists: section_image_overlay";
    }

    // ====================================================================================
    // 2. CREATE BACKGROUND COLOR FIELD
    // ====================================================================================

    $log[] = "\nStep 2: Creating background color field...";

    $bgColorField = $fields->get('section_bg_color');
    if (!$bgColorField) {
        $bgColorField = new Field();
        $bgColorField->type = $modules->get('FieldtypeOptions');
        $bgColorField->name = 'section_bg_color';
        $bgColorField->label = 'Hintergrundfarbe';
        $bgColorField->description = 'Hintergrundfarbe für diesen Abschnitt';
        $bgColorField->options = "none|Transparent\ngreen|Grün\ndarkgreen|Dunkelgrün\norange|Orange\ngray|Grau\nwhite|Weiss";
        $bgColorField->optionColumns = 1;
        $bgColorField->defaultValue = 'none';

        try {
            $fields->save($bgColorField);
            $log[] = "✓ Created field: section_bg_color";
        } catch (\Exception $e) {
            $errors[] = "Failed to create section_bg_color field: " . $e->getMessage();
        }
    } else {
        $log[] = "✓ Field exists: section_bg_color";
    }

    // ====================================================================================
    // 3. ADD FIELDS TO REPEATER TEMPLATE
    // ====================================================================================

    $log[] = "\nStep 3: Adding styling fields to repeater template...";

    $repeaterTemplate = $templates->get('repeater_content_sections');
    if ($repeaterTemplate) {
        // Add overlay field
        if ($imageOverlayField && !$repeaterTemplate->hasField($imageOverlayField)) {
            try {
                $repeaterTemplate->fields->add($imageOverlayField);
                $log[] = "  + Added 'section_image_overlay' to repeater";
            } catch (\Exception $e) {
                $errors[] = "Failed to add section_image_overlay to repeater: " . $e->getMessage();
            }
        } else if ($imageOverlayField) {
            $log[] = "  ✓ Field already in repeater: section_image_overlay";
        }

        // Add background color field
        if ($bgColorField && !$repeaterTemplate->hasField($bgColorField)) {
            try {
                $repeaterTemplate->fields->add($bgColorField);
                $log[] = "  + Added 'section_bg_color' to repeater";
            } catch (\Exception $e) {
                $errors[] = "Failed to add section_bg_color to repeater: " . $e->getMessage();
            }
        } else if ($bgColorField) {
            $log[] = "  ✓ Field already in repeater: section_bg_color";
        }

        // Save template
        try {
            $templates->save($repeaterTemplate);
            $log[] = "✓ Repeater template updated";
        } catch (\Exception $e) {
            $errors[] = "Failed to save repeater template: " . $e->getMessage();
        }
    } else {
        $errors[] = "Repeater template 'repeater_content_sections' not found";
    }

    // ====================================================================================
    // 4. DOCUMENTATION
    // ====================================================================================

    $log[] = "\n=== Setup Complete ===";
    $log[] = "\nNew fields available in content sections:";
    $log[] = "• section_image_overlay (Dropdown)";
    $log[] = "  - Kein Overlay";
    $log[] = "  - Dunkel (rgba(0,0,0,0.4))";
    $log[] = "  - Grün (rgba(76,111,68,0.3))";
    $log[] = "  - Orange (rgba(232,119,34,0.3))";
    $log[] = "\n• section_bg_color (Dropdown)";
    $log[] = "  - Transparent (none)";
    $log[] = "  - Grün (#4C6F44)";
    $log[] = "  - Dunkelgrün (#2D4A27)";
    $log[] = "  - Orange (#E87722)";
    $log[] = "  - Grau (#999999)";
    $log[] = "  - Weiss (#FFFFFF)";

    $log[] = "\n=== Frontend Implementation Required ===";
    $log[] = "1. Update /frontend/lib/processwire-types.ts:";
    $log[] = "   - Add image_overlay?: 'none' | 'dark' | 'green' | 'orange' to ContentSection";
    $log[] = "   - Add bg_color?: 'none' | 'green' | 'darkgreen' | 'orange' | 'gray' | 'white' to ContentSection";
    $log[] = "\n2. Update /frontend/components/sections/SectionRenderer.tsx:";
    $log[] = "   - Apply overlay className based on section.image_overlay";
    $log[] = "   - Apply background className based on section.bg_color";
    $log[] = "\n3. Update /site/templates/api.php:";
    $log[] = "   - Include 'section_image_overlay' and 'section_bg_color' in section data response";
    $log[] = "\n4. Add CSS classes to /frontend/app/globals.css:";
    $log[] = "   - .image-overlay-dark::after { background: rgba(0,0,0,0.4); }";
    $log[] = "   - .image-overlay-green::after { background: rgba(76,111,68,0.3); }";
    $log[] = "   - .image-overlay-orange::after { background: rgba(232,119,34,0.3); }";
    $log[] = "   - .bg-green { background-color: #4C6F44; }";
    $log[] = "   - .bg-darkgreen { background-color: #2D4A27; }";
    $log[] = "   - .bg-orange { background-color: #E87722; }";
    $log[] = "   - .bg-gray { background-color: #999999; }";
    $log[] = "   - .bg-white { background-color: #FFFFFF; }";

    if (count($errors) > 0) {
        $log[] = "\n=== Errors (" . count($errors) . ") ===";
        $log = array_merge($log, $errors);
    }

    // ====================================================================================
    // 5. OUTPUT
    // ====================================================================================

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
        'fields_created' => 2,
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
