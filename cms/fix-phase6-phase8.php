<?php
/**
 * Fix Phase 6 (Button Labels) + Phase 8 (Navigation Page Values)
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    // ====================================================================================
    // FIX PHASE 6: Button Labels
    // ====================================================================================

    $log[] = "=== Fix Phase 6: Button Labels ===\n";

    $buttonFields = [
        'button_variant' => [
            'label' => 'Button-Stil',
            'description' => 'Grün (primär) oder Weiss (sekundär)',
            'default' => 'primary',
        ],
        'button2_variant' => [
            'label' => 'Sekundärer Button-Stil',
            'description' => 'Grün (primär) oder Weiss (sekundär)',
            'default' => 'secondary',
        ],
    ];

    foreach ($buttonFields as $fieldName => $config) {
        $field = $fields->get($fieldName);

        if (!$field) {
            $log[] = "  Field not found: $fieldName, creating fresh...";
        } else if ($field->type instanceof FieldtypeOptions) {
            $log[] = "✓ Already FieldtypeOptions: $fieldName";
            continue;
        } else {
            // Step 1: Remove from ALL fieldgroups (not just templates)
            $log[] = "  Converting $fieldName from Text to Options...";

            foreach ($fieldgroups as $fg) {
                if ($fg->hasField($field)) {
                    $fg->remove($field);
                    $fg->save();
                    $log[] = "  Removed from fieldgroup: $fg->name";
                }
            }

            // Step 2: Delete old field
            try {
                $fields->delete($field);
                $log[] = "  Deleted old FieldtypeText: $fieldName";
            } catch (\Exception $e) {
                $errors[] = "Cannot delete $fieldName: " . $e->getMessage();
                continue;
            }
        }

        // Step 3: Create new Options field
        $newField = new Field();
        $newField->type = $modules->get('FieldtypeOptions');
        $newField->name = $fieldName;
        $newField->label = $config['label'];
        $newField->description = $config['description'];
        $fields->save($newField);

        // Step 4: Set options
        $manager = new \ProcessWire\SelectableOptionManager();
        $manager->setOptionsString($newField, "primary=Grün\nsecondary=Weiss", false);
        $fields->save($newField);
        $log[] = "  Created FieldtypeOptions: $fieldName";

        // Step 5: Re-add to repeater_content_sections fieldgroup
        $repeaterFg = $fieldgroups->get('repeater_content_sections');
        if ($repeaterFg) {
            $repeaterFg->add($newField);
            $repeaterFg->save();
            $log[] = "  Re-added to repeater_content_sections";
        }

        $log[] = "✓ Fixed: $fieldName";
    }

    // Clean up backup fields
    foreach (['button_variant_old_backup', 'button2_variant_old_backup'] as $backupName) {
        $backupField = $fields->get($backupName);
        if ($backupField) {
            foreach ($fieldgroups as $fg) {
                if ($fg->hasField($backupField)) {
                    $fg->remove($backupField);
                    $fg->save();
                }
            }
            try {
                $fields->delete($backupField);
                $log[] = "  Cleaned up: $backupName";
            } catch (\Exception $e) {
                $log[] = "  Could not delete backup $backupName: " . $e->getMessage();
            }
        }
    }

    // ====================================================================================
    // FIX PHASE 8: Set include_in_nav on existing pages
    // ====================================================================================

    $log[] = "\n=== Fix Phase 8: Navigation Values ===\n";

    $mainPages = [
        'home', 'wir', 'gemuese', 'mitmachen', 'abos', 'solawi',
        'standorte-depots', 'aktuelles', 'bioco-werden', 'kontakt',
        'newsletter', 'warteliste',
    ];

    foreach ($mainPages as $pageName) {
        $page = $pages->get("name=$pageName");

        if (!$page->id) {
            $log[] = "  Page not found: /$pageName/";
            continue;
        }

        if ($page->hasField('include_in_nav')) {
            try {
                $page->of(false);
                $page->include_in_nav = 1;
                $pages->save($page);
                $log[] = "  ✓ Enabled nav for: /$pageName/";
            } catch (\Exception $e) {
                $errors[] = "Failed: /$pageName/: " . $e->getMessage();
            }
        } else {
            $log[] = "  No include_in_nav field on: /$pageName/";
        }
    }

    // ====================================================================================
    // SUMMARY
    // ====================================================================================

    $log[] = "\n=== Fixes Complete ===";

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
