<?php
/**
 * Migration: Add image filter fields (brightness, contrast, saturate) to section repeater
 * Run via bootstrap: curl https://cms.bioco.ch/bootstrap-add-image-filters.php
 */

namespace ProcessWire;

// Only allow from CLI or localhost
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Forbidden: run from localhost or CLI only');
}

require_once __DIR__ . '/../site/init.php';

$fields = wire('fields');
$templates = wire('templates');

$filterFields = [
    'section_image_brightness' => [
        'label' => 'Bild-Helligkeit',
        'description' => 'CSS brightness filter (0.5-1.5, Standard: 1.0)',
        'min' => 0.5,
        'max' => 1.5,
    ],
    'section_image_contrast' => [
        'label' => 'Bild-Kontrast',
        'description' => 'CSS contrast filter (0.5-1.5, Standard: 1.0)',
        'min' => 0.5,
        'max' => 1.5,
    ],
    'section_image_saturate' => [
        'label' => 'Bild-Sättigung',
        'description' => 'CSS saturate filter (0-2.0, Standard: 1.0)',
        'min' => 0,
        'max' => 2.0,
    ],
];

foreach ($filterFields as $name => $config) {
    $f = $fields->get($name);
    if (!$f) {
        $f = new Field();
        $f->type = wire('modules')->get('FieldtypeFloat');
        $f->name = $name;
        $f->label = $config['label'];
        $f->description = $config['description'];
        $f->min = $config['min'];
        $f->max = $config['max'];
        $f->defaultValue = 1.0;
        $f->precision = 2;
        $f->save();
        echo "Created field: {$name}\n";
    } else {
        echo "Field {$name} already exists\n";
    }
}

// Add to repeater_sections template (the sections repeater)
$repeaterTemplate = $templates->get('repeater_content_sections');
if ($repeaterTemplate) {
    $fg = $repeaterTemplate->fieldgroup;
    foreach ($filterFields as $name => $config) {
        $f = $fields->get($name);
        if (!$fg->hasField($name)) {
            $fg->add($f);
            echo "Added {$name} to repeater_sections\n";
        } else {
            echo "{$name} already in repeater_sections\n";
        }
    }
    $fg->save();
} else {
    echo "WARNING: repeater_sections template not found. Check your repeater template name.\n";
}

echo "\nDone.\n";
