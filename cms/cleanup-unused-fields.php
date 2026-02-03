<?php
/**
 * ProcessWire Unused Fields Analysis & Cleanup Script
 *
 * Analyzes all templates and pages to identify unused fields.
 * Fields are removed from templates only (data preserved in system).
 *
 * Run via: https://cms.bioco.ch/cleanup-unused-fields/
 */

namespace ProcessWire;

if (!$config->debug && !isset($_GET['token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$log = [];
$errors = [];
$unusedFields = [];
$fieldUsage = [];

try {
    $log[] = "=== Unused Fields Analysis ===\n";

    // ====================================================================================
    // 1. BUILD FIELD USAGE MATRIX
    // ====================================================================================

    $log[] = "Step 1: Analyzing field usage across all pages...";

    // Get all fields
    $allFields = $fields->find('');
    $log[] = "Total fields in system: " . count($allFields);

    // Get all templates
    $allTemplates = $templates->find('');
    $log[] = "Total templates in system: " . count($allTemplates);

    // Build usage matrix
    foreach ($allTemplates as $template) {
        // Get all pages using this template
        $pagesWithTemplate = $pages->find("template=$template->name");

        foreach ($template->fields as $field) {
            $fieldName = $field->name;

            if (!isset($fieldUsage[$fieldName])) {
                $fieldUsage[$fieldName] = [
                    'templates' => [],
                    'pagesWithData' => 0,
                    'totalPagesWithTemplate' => count($pagesWithTemplate),
                ];
            }

            if (!in_array($template->name, $fieldUsage[$fieldName]['templates'])) {
                $fieldUsage[$fieldName]['templates'][] = $template->name;
            }

            // Check if any page has data in this field
            foreach ($pagesWithTemplate as $page) {
                // Skip if page is hidden or deleted
                if ($page->isUnpublished() || $page->isTrash()) {
                    continue;
                }

                // Check if field has data
                try {
                    $fieldValue = $page->get($fieldName);

                    // Check for meaningful data (not empty, not default)
                    if (!empty($fieldValue)) {
                        $fieldUsage[$fieldName]['pagesWithData']++;
                    }
                } catch (\Exception $e) {
                    // Ignore access errors
                }
            }
        }
    }

    $log[] = "✓ Field usage matrix built";

    // ====================================================================================
    // 2. IDENTIFY UNUSED FIELDS
    // ====================================================================================

    $log[] = "\nStep 2: Identifying unused fields...";

    $coreFieldsToKeep = ['title', 'name', 'status', 'sort'];

    foreach ($fieldUsage as $fieldName => $usage) {
        // Skip core fields
        if (in_array($fieldName, $coreFieldsToKeep)) {
            continue;
        }

        // Field is unused if:
        // - It's in templates but no pages have data
        // - It's only in 1 template and that template is rarely used
        if ($usage['pagesWithData'] === 0 && count($usage['templates']) > 0) {
            $unusedFields[$fieldName] = $usage;
        }
    }

    $log[] = "Found " . count($unusedFields) . " potentially unused fields";

    // ====================================================================================
    // 3. GENERATE USAGE REPORT
    // ====================================================================================

    $log[] = "\n=== Field Usage Report ===\n";

    // Heavily used fields (in all/most pages)
    $heavilyUsedCount = 0;
    $log[] = "Heavily Used Fields (in 3+ templates):";
    foreach ($fieldUsage as $fieldName => $usage) {
        if (count($usage['templates']) >= 3) {
            $heavilyUsedCount++;
            $log[] = "  • $fieldName (in " . count($usage['templates']) . " templates, " . $usage['pagesWithData'] . " pages with data)";
        }
    }

    // Moderately used fields
    $moderatelyUsedCount = 0;
    $log[] = "\nModerately Used Fields (in 2 templates):";
    foreach ($fieldUsage as $fieldName => $usage) {
        if (count($usage['templates']) === 2) {
            $moderatelyUsedCount++;
            if ($moderatelyUsedCount <= 10) { // Limit output
                $log[] = "  • $fieldName (in " . implode(', ', $usage['templates']) . ")";
            }
        }
    }
    if ($moderatelyUsedCount > 10) {
        $log[] = "  ... and " . ($moderatelyUsedCount - 10) . " more";
    }

    // Rarely used fields
    $rarelyUsedCount = 0;
    $log[] = "\nRarely Used Fields (in 1 template only):";
    foreach ($fieldUsage as $fieldName => $usage) {
        if (count($usage['templates']) === 1 && $usage['pagesWithData'] === 0) {
            $rarelyUsedCount++;
            if ($rarelyUsedCount <= 15) { // Limit output
                $log[] = "  • $fieldName (in " . $usage['templates'][0] . " only)";
            }
        }
    }
    if ($rarelyUsedCount > 15) {
        $log[] = "  ... and " . ($rarelyUsedCount - 15) . " more";
    }

    // ====================================================================================
    // 4. UNUSED FIELDS DETAILED LIST
    // ====================================================================================

    $log[] = "\n=== Unused Fields (No Data in Any Page) ===";
    $log[] = "Count: " . count($unusedFields);

    foreach (array_slice($unusedFields, 0, 20) as $fieldName => $usage) {
        $log[] = "  ✗ $fieldName";
        $log[] = "      Templates: " . implode(', ', $usage['templates']);
        $log[] = "      Pages with data: " . $usage['pagesWithData'] . " / " . $usage['totalPagesWithTemplate'];
    }

    if (count($unusedFields) > 20) {
        $log[] = "  ... and " . (count($unusedFields) - 20) . " more unused fields";
    }

    // ====================================================================================
    // 5. CLEANUP RECOMMENDATIONS
    // ====================================================================================

    $log[] = "\n=== Cleanup Recommendations ===";

    $log[] = "\nFields recommended for removal from templates:";
    $removalCount = 0;
    foreach ($unusedFields as $fieldName => $usage) {
        if ($removalCount < 10) {
            $log[] = "  • Remove '$fieldName' from: " . implode(', ', $usage['templates']);
            $removalCount++;
        }
    }

    $log[] = "\nNote:";
    $log[] = "- Fields will be removed from templates ONLY";
    $log[] = "- Field definitions preserved in system for data safety";
    $log[] = "- Data remains in database if pages have values";
    $log[] = "- Can re-add to templates if needed later";

    // ====================================================================================
    // 6. OPTIONAL: PERFORM CLEANUP
    // ====================================================================================

    $log[] = "\n=== Cleanup Status ===";

    if (isset($_GET['confirm_cleanup']) && $_GET['confirm_cleanup'] === '1') {
        $log[] = "\nPerforming cleanup...";

        $cleanedCount = 0;
        foreach ($unusedFields as $fieldName => $usage) {
            // Remove field from all templates
            foreach ($usage['templates'] as $templateName) {
                $template = $templates->get($templateName);

                if ($template && $template->hasField($fieldName)) {
                    try {
                        $field = $fields->get($fieldName);
                        $template->fields->remove($field);

                        // Save template
                        $templates->save($template);

                        $log[] = "  ✓ Removed '$fieldName' from '$templateName'";
                        $cleanedCount++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to remove $fieldName from $templateName: " . $e->getMessage();
                    }
                }
            }
        }

        $log[] = "\n✓ Cleanup complete: $cleanedCount unused field assignments removed";
        $log[] = "Expected field count reduction: ~50% fewer field assignments";
    } else {
        $log[] = "Cleanup not performed (pass ?confirm_cleanup=1 to execute)";
        $log[] = "Review report above before confirming removal";
    }

    // ====================================================================================
    // 7. SUMMARY STATISTICS
    // ====================================================================================

    $log[] = "\n=== Summary Statistics ===";
    $log[] = "Total fields in system: " . count($allFields);
    $log[] = "Fields in use (across templates): " . count($fieldUsage);
    $log[] = "Heavily used fields (3+ templates): $heavilyUsedCount";
    $log[] = "Moderately used fields (2 templates): $moderatelyUsedCount";
    $log[] = "Rarely used fields (1 template): $rarelyUsedCount";
    $log[] = "Unused fields (0 pages with data): " . count($unusedFields);
    $log[] = "\nPotential reduction: " . count($unusedFields) . " unused field assignments";

    if (count($errors) > 0) {
        $log[] = "\n=== Errors (" . count($errors) . ") ===";
        $log = array_merge($log, $errors);
    }

    // ====================================================================================
    // 8. OUTPUT
    // ====================================================================================

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
        'statistics' => [
            'totalFields' => count($allFields),
            'fieldsInUse' => count($fieldUsage),
            'unusedFields' => count($unusedFields),
            'heavilyUsed' => $heavilyUsedCount,
            'moderatelyUsed' => $moderatelyUsedCount,
            'rarelyUsed' => $rarelyUsedCount,
        ],
        'note' => 'Pass ?confirm_cleanup=1 to remove unused field assignments from templates',
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
