<?php
/**
 * Fix content_sections repeater:
 * 1. Rename seitentitel → bereichs_titel
 * 2. Use bereichs_titel as repeater item label
 * 3. Remove redundant title fields
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Fix content_sections Repeater ===\n";

    // ====================================================================================
    // 1. CHECK CURRENT STATE
    // ====================================================================================

    $log[] = "Step 1: Checking current state...";

    $repeaterFg = $fieldgroups->get('repeater_content_sections');
    if (!$repeaterFg) {
        throw new \Exception("Fieldgroup 'repeater_content_sections' not found");
    }

    $log[] = "Current fields in repeater:";
    foreach ($repeaterFg as $field) {
        $log[] = "  - " . $field->name . " (" . $field->label . ")";
    }

    // ====================================================================================
    // 2. RENAME seitentitel → bereichs_titel
    // ====================================================================================

    $log[] = "\nStep 2: Renaming seitentitel → bereichs_titel...";

    $oldField = $fields->get('seitentitel');
    $newFieldExists = $fields->get('bereichs_titel');

    if ($newFieldExists) {
        $log[] = "✓ bereichs_titel already exists, skipping rename";
    } else if ($oldField) {
        // Rename the field
        $oldField->name = 'bereichs_titel';
        $oldField->label = 'Bereichstitel';
        $oldField->description = 'Überschrift für diesen Inhaltsbereich';
        $fields->save($oldField);
        $log[] = "✓ Renamed seitentitel → bereichs_titel";
    } else {
        // Field doesn't exist, check for section_title
        $sectionTitleField = $fields->get('section_title');
        if ($sectionTitleField && $repeaterFg->hasField($sectionTitleField)) {
            $log[] = "  Found section_title in repeater, using it as bereichs_titel";
        } else {
            $errors[] = "No title field found in repeater (checked: seitentitel, section_title)";
        }
    }

    // ====================================================================================
    // 3. CONFIGURE REPEATER LABEL
    // ====================================================================================

    $log[] = "\nStep 3: Configuring repeater to use bereichs_titel as label...";

    $contentSectionsField = $fields->get('content_sections');
    if (!$contentSectionsField) {
        throw new \Exception("Field 'content_sections' not found");
    }

    // Check which title field exists
    $titleFieldName = null;
    if ($fields->get('bereichs_titel')) {
        $titleFieldName = 'bereichs_titel';
    } else if ($fields->get('section_title') && $repeaterFg->hasField($fields->get('section_title'))) {
        $titleFieldName = 'section_title';
    }

    if ($titleFieldName) {
        // Set repeater label format
        $contentSectionsField->set('repeaterTitle', "#{n}: {$titleFieldName}");
        $contentSectionsField->set('repeaterDepth', 1);
        $fields->save($contentSectionsField);
        $log[] = "✓ Repeater now uses '$titleFieldName' for item labels";
        $log[] = "  Format: #1: [title], #2: [title], etc.";
    } else {
        $errors[] = "No title field found to use for repeater labels";
    }

    // ====================================================================================
    // 4. REMOVE REDUNDANT TITLE FIELDS
    // ====================================================================================

    $log[] = "\nStep 4: Checking for redundant title fields in repeater...";

    $titleFields = ['title', 'seitentitel', 'bereichs_titel', 'section_title'];
    $foundTitles = [];

    foreach ($titleFields as $tfName) {
        $tf = $fields->get($tfName);
        if ($tf && $repeaterFg->hasField($tf)) {
            $foundTitles[] = $tfName;
        }
    }

    $log[] = "  Found title fields: " . implode(', ', $foundTitles);

    // Keep only one title field
    if (count($foundTitles) > 1) {
        // Priority: bereichs_titel > section_title > seitentitel > title
        $keepField = null;
        if (in_array('bereichs_titel', $foundTitles)) {
            $keepField = 'bereichs_titel';
        } else if (in_array('section_title', $foundTitles)) {
            $keepField = 'section_title';
        } else if (in_array('seitentitel', $foundTitles)) {
            $keepField = 'seitentitel';
        } else {
            $keepField = 'title';
        }

        $log[] = "  Keeping: $keepField";
        $log[] = "  Removing redundant fields...";

        foreach ($foundTitles as $tfName) {
            if ($tfName !== $keepField && $tfName !== 'title') { // Never remove core title field
                $tf = $fields->get($tfName);
                if ($tf && $repeaterFg->hasField($tf)) {
                    $repeaterFg->remove($tf);
                    $repeaterFg->save();
                    $log[] = "  ✓ Removed $tfName from repeater";
                }
            }
        }
    } else {
        $log[] = "  No redundant title fields found";
    }

    // ====================================================================================
    // 5. SUMMARY
    // ====================================================================================

    $log[] = "\n=== Fix Complete ===";
    $log[] = "Final state:";

    $repeaterFg = $fieldgroups->get('repeater_content_sections');
    foreach ($repeaterFg as $field) {
        $log[] = "  - " . $field->name . " (" . $field->label . ")";
    }

    $contentSectionsField = $fields->get('content_sections');
    $labelFormat = $contentSectionsField->get('repeaterTitle');
    $log[] = "\nRepeater label format: " . ($labelFormat ?: "default");

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
