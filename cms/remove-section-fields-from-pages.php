<?php
/**
 * Remove section_* fields from page templates
 * These should only exist inside content_sections repeater
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Remove Section Fields from Pages ===\n";

    // Fields to remove from page level (they belong only in repeater)
    $fieldsToRemove = ['section_title', 'section_text', 'section_image', 'section_eyebrow'];

    // Page templates to clean up
    $pageTemplates = [
        'home', 'wir', 'gemuese', 'mitmachen', 'abos', 'solawi',
        'standorte_depots', 'aktuelles_page', 'bioco_werden',
        'kontakt', 'newsletter', 'warteliste'
    ];

    $log[] = "Removing fields: " . implode(', ', $fieldsToRemove);
    $log[] = "From templates: " . implode(', ', $pageTemplates);
    $log[] = "";

    $totalRemoved = 0;

    foreach ($pageTemplates as $templateName) {
        $template = $templates->get($templateName);

        if (!$template) {
            $log[] = "Template not found: $templateName";
            continue;
        }

        $fg = $template->fieldgroup;
        $removed = [];

        foreach ($fieldsToRemove as $fieldName) {
            $field = $fields->get($fieldName);

            if ($field && $fg->hasField($field)) {
                $fg->remove($field);
                $removed[] = $fieldName;
                $totalRemoved++;
            }
        }

        if (count($removed) > 0) {
            $fg->save();
            $log[] = "✓ $templateName: removed " . implode(', ', $removed);
        } else {
            $log[] = "  $templateName: already clean";
        }
    }

    // Verify fields still exist in repeater
    $log[] = "\n=== Verify Repeater Still Has Fields ===";

    $repeaterFg = $fieldgroups->get('repeater_content_sections');
    if ($repeaterFg) {
        $log[] = "repeater_content_sections fields:";
        foreach ($fieldsToRemove as $fieldName) {
            $field = $fields->get($fieldName);
            if ($field && $repeaterFg->hasField($field)) {
                $log[] = "  ✓ $fieldName (still in repeater)";
            }
        }
    }

    $log[] = "\n=== Cleanup Complete ===";
    $log[] = "Removed $totalRemoved field instances from page templates";
    $log[] = "Section fields now only in content_sections repeater";
    $log[] = "\nPage structure now:";
    $log[] = "- title (page title)";
    $log[] = "- hero_headline (hero section)";
    $log[] = "- content_sections (repeater)";
    $log[] = "    └─ section_title (section title)";
    $log[] = "    └─ section_text (section content)";
    $log[] = "    └─ section_image (section image)";
    $log[] = "    └─ ... (other section fields)";

    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
        'removed_count' => $totalRemoved,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT);
}
