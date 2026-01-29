<?php
/**
 * Add section_images field to content_sections repeater
 * 
 * Run once via: https://cms.bioco.ch/add-section-images-field.php
 * Delete after running.
 */

namespace ProcessWire;

require_once(__DIR__ . '/../index.php');

$fields = wire('fields');
$templates = wire('templates');

echo "<pre>\n";
echo "=== Adding section_images field ===\n\n";

// 1. Create section_images field (multiple images)
$fieldName = 'section_images';
$field = $fields->get($fieldName);

if (!$field) {
    $field = new Field();
    $field->type = wire('modules')->get('FieldtypeImage');
    $field->name = $fieldName;
    $field->label = 'Section Images';
    $field->description = 'Multiple images for this section (gallery, team members, etc.)';
    $field->maxFiles = 0; // Unlimited
    $field->extensions = 'jpg jpeg png gif webp';
    $field->outputFormat = FieldtypeFile::outputFormatArray;
    $field->descriptionRows = 1; // Use image description for alt text
    $field->tags = 'content';
    $field->save();
    echo "✓ Created field: $fieldName\n";
} else {
    echo "• Field already exists: $fieldName\n";
}

// 2. Add to repeater template (content_sections)
$repeaterTemplate = $templates->get('repeater_content_sections');
if ($repeaterTemplate) {
    $fg = $repeaterTemplate->fieldgroup;
    if (!$fg->hasField($fieldName)) {
        $fg->add($field);
        $fg->save();
        echo "✓ Added $fieldName to repeater_content_sections template\n";
    } else {
        echo "• Field already in repeater template\n";
    }
} else {
    echo "⚠ repeater_content_sections template not found\n";
    echo "  You may need to add section_images manually to the content_sections repeater\n";
}

echo "\n=== Done ===\n";
echo "\nNow in ProcessWire admin:\n";
echo "1. Go to Setup → Fields → content_sections (repeater)\n";
echo "2. Add 'section_images' field if not already there\n";
echo "3. Delete this file after running\n";
echo "</pre>";
