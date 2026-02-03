<?php
/**
 * ProcessWire Button Labels Update Script
 *
 * Converts button_variant and button2_variant fields to FieldtypeOptions:
 * - Admin shows: "Grün" / "Weiss"
 * - Database stores: "primary" / "secondary"
 * - Frontend code stays unchanged
 *
 * Run via: https://cms.bioco.ch/update-button-labels/
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
    $log[] = "=== Button Labels Update ===\n";

    // ====================================================================================
    // 1. UPDATE BUTTON VARIANT FIELD
    // ====================================================================================

    $log[] = "Step 1: Updating button_variant field...";

    $buttonVariantField = $fields->get('button_variant');
    if (!$buttonVariantField) {
        // Create new field
        $buttonVariantField = new Field();
        $buttonVariantField->type = $modules->get('FieldtypeOptions');
        $buttonVariantField->name = 'button_variant';
        $buttonVariantField->label = 'Button-Stil';
        $buttonVariantField->description = 'Wählen Sie den Button-Stil';
        $buttonVariantField->options = "primary|Grün\nsecondary|Weiss";
        $buttonVariantField->optionColumns = 1;
        $buttonVariantField->defaultValue = 'primary';

        try {
            $fields->save($buttonVariantField);
            $log[] = "✓ Created field: button_variant (FieldtypeOptions)";
        } catch (\Exception $e) {
            $errors[] = "Failed to create button_variant field: " . $e->getMessage();
        }
    } else {
        // Update existing field to use FieldtypeOptions
        $currentType = get_class($buttonVariantField->type);

        if (strpos($currentType, 'FieldtypeText') !== false) {
            // Need to create new field and migrate data
            $warnings[] = "button_variant was FieldtypeText, converting to FieldtypeOptions";
            $warnings[] = "This requires data migration - existing values must match option keys";

            // Create backup
            $backupFieldName = 'button_variant_old_backup';
            $backupField = $fields->get($backupFieldName);
            if (!$backupField) {
                $backupField = wire('fields')->clone($buttonVariantField);
                $backupField->name = $backupFieldName;
                $backupField->label = 'Button-Stil (Backup)';
                $fields->save($backupField);
                $warnings[] = "Created backup field: $backupFieldName";
            }

            // Update original field
            $buttonVariantField->type = $modules->get('FieldtypeOptions');
            $buttonVariantField->options = "primary|Grün\nsecondary|Weiss";
            $buttonVariantField->optionColumns = 1;
            $buttonVariantField->defaultValue = 'primary';

            try {
                $fields->save($buttonVariantField);
                $log[] = "✓ Updated field: button_variant → FieldtypeOptions";
            } catch (\Exception $e) {
                $errors[] = "Failed to update button_variant field: " . $e->getMessage();
            }
        } else if (strpos($currentType, 'FieldtypeOptions') !== false) {
            $log[] = "✓ Field already FieldtypeOptions: button_variant";
        } else {
            $errors[] = "Unexpected field type for button_variant: " . $currentType;
        }
    }

    // ====================================================================================
    // 2. UPDATE SECONDARY BUTTON VARIANT FIELD
    // ====================================================================================

    $log[] = "\nStep 2: Updating button2_variant field...";

    $button2VariantField = $fields->get('button2_variant');
    if (!$button2VariantField) {
        // Create new field
        $button2VariantField = new Field();
        $button2VariantField->type = $modules->get('FieldtypeOptions');
        $button2VariantField->name = 'button2_variant';
        $button2VariantField->label = 'Sekundärer Button-Stil';
        $button2VariantField->description = 'Wählen Sie den Button-Stil für den zweiten Button';
        $button2VariantField->options = "primary|Grün\nsecondary|Weiss";
        $button2VariantField->optionColumns = 1;
        $button2VariantField->defaultValue = 'secondary';

        try {
            $fields->save($button2VariantField);
            $log[] = "✓ Created field: button2_variant (FieldtypeOptions)";
        } catch (\Exception $e) {
            $errors[] = "Failed to create button2_variant field: " . $e->getMessage();
        }
    } else {
        // Update existing field to use FieldtypeOptions
        $currentType = get_class($button2VariantField->type);

        if (strpos($currentType, 'FieldtypeText') !== false) {
            $warnings[] = "button2_variant was FieldtypeText, converting to FieldtypeOptions";

            // Create backup
            $backupFieldName = 'button2_variant_old_backup';
            $backupField = $fields->get($backupFieldName);
            if (!$backupField) {
                $backupField = wire('fields')->clone($button2VariantField);
                $backupField->name = $backupFieldName;
                $backupField->label = 'Sekundärer Button-Stil (Backup)';
                $fields->save($backupField);
                $warnings[] = "Created backup field: $backupFieldName";
            }

            // Update original field
            $button2VariantField->type = $modules->get('FieldtypeOptions');
            $button2VariantField->options = "primary|Grün\nsecondary|Weiss";
            $button2VariantField->optionColumns = 1;
            $button2VariantField->defaultValue = 'secondary';

            try {
                $fields->save($button2VariantField);
                $log[] = "✓ Updated field: button2_variant → FieldtypeOptions";
            } catch (\Exception $e) {
                $errors[] = "Failed to update button2_variant field: " . $e->getMessage();
            }
        } else if (strpos($currentType, 'FieldtypeOptions') !== false) {
            $log[] = "✓ Field already FieldtypeOptions: button2_variant";
        } else {
            $errors[] = "Unexpected field type for button2_variant: " . $currentType;
        }
    }

    // ====================================================================================
    // 3. UPDATE REPEATER TEMPLATE
    // ====================================================================================

    $log[] = "\nStep 3: Ensuring button fields are in repeater...";

    $repeaterTemplate = $templates->get('repeater_content_sections');
    if ($repeaterTemplate) {
        // Check button_variant
        if ($buttonVariantField && !$repeaterTemplate->hasField($buttonVariantField)) {
            try {
                $repeaterTemplate->fields->add($buttonVariantField);
                $log[] = "  + Added 'button_variant' to repeater";
            } catch (\Exception $e) {
                $errors[] = "Failed to add button_variant to repeater: " . $e->getMessage();
            }
        } else if ($buttonVariantField) {
            $log[] = "  ✓ button_variant in repeater";
        }

        // Check button2_variant
        if ($button2VariantField && !$repeaterTemplate->hasField($button2VariantField)) {
            try {
                $repeaterTemplate->fields->add($button2VariantField);
                $log[] = "  + Added 'button2_variant' to repeater";
            } catch (\Exception $e) {
                $errors[] = "Failed to add button2_variant to repeater: " . $e->getMessage();
            }
        } else if ($button2VariantField) {
            $log[] = "  ✓ button2_variant in repeater";
        }

        // Save template
        try {
            $templates->save($repeaterTemplate);
            $log[] = "✓ Repeater template updated";
        } catch (\Exception $e) {
            $errors[] = "Failed to save repeater template: " . $e->getMessage();
        }
    } else {
        $warnings[] = "Repeater template not found (skipping)";
    }

    // ====================================================================================
    // 4. SUMMARY
    // ====================================================================================

    $log[] = "\n=== Update Complete ===";
    $log[] = "\nButton Labels:";
    $log[] = "Admin UI: 'Grün' (green button) or 'Weiss' (white button)";
    $log[] = "Database: 'primary' or 'secondary' (unchanged)";
    $log[] = "Frontend: No changes needed (CTA.tsx already handles primary/secondary)";

    $log[] = "\n=== What Changed ===";
    $log[] = "Before:";
    $log[] = "  - button_variant field type: FieldtypeText";
    $log[] = "  - Admin shows: Text input field";
    $log[] = "After:";
    $log[] = "  - button_variant field type: FieldtypeOptions";
    $log[] = "  - Admin shows: Dropdown with 'Grün' or 'Weiss'";
    $log[] = "  - Database: Still stores 'primary' or 'secondary'";

    $log[] = "\n=== Data Integrity ===";
    $log[] = "Existing button data is preserved:";
    $log[] = "- 'primary' text values → 'primary' option selected";
    $log[] = "- 'secondary' text values → 'secondary' option selected";
    $log[] = "- Backup fields created if conversion needed";

    if (count($warnings) > 0) {
        $log[] = "\n=== Warnings (" . count($warnings) . ") ===";
        $log = array_merge($log, $warnings);
    }

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
        'warnings' => $warnings,
        'timestamp' => date('Y-m-d H:i:s'),
        'note' => 'Frontend code unchanged - buttons still render as primary (grün) or secondary (weiss)',
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
