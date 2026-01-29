<?php namespace ProcessWire;
/**
 * SEO Fields Install Script
 * 
 * Run this once via browser: https://yoursite.com/site/install-seo-fields.php
 * Then DELETE this file for security!
 */

// Bootstrap ProcessWire
require_once(__DIR__ . '/index.php');

// Security: only allow logged-in superusers
if (!wire('user')->isSuperuser()) {
    die('Access denied. Login as superuser first.');
}

echo "<h1>Installing SEO Fields</h1><pre>";

$fields = wire('fields');
$templates = wire('templates');

// Field definitions
$seoFields = [
    'seo_title' => [
        'type' => 'FieldtypeText',
        'label' => 'SEO Title',
        'description' => 'Custom page title for Google (50-60 chars). Leave empty to use page title.',
        'maxlength' => 70,
    ],
    'seo_description' => [
        'type' => 'FieldtypeTextarea',
        'label' => 'Meta Description', 
        'description' => 'Page description for search engines (150-160 chars).',
        'rows' => 3,
    ],
    'og_image' => [
        'type' => 'FieldtypeImage',
        'label' => 'Social Share Image',
        'description' => 'Image for social media sharing. Recommended: 1200x630px.',
        'maxFiles' => 1,
        'extensions' => 'jpg jpeg png webp',
    ],
    'canonical_url' => [
        'type' => 'FieldtypeURL',
        'label' => 'Canonical URL',
        'description' => 'Override canonical URL (leave empty to use page URL).',
    ],
    'robots_noindex' => [
        'type' => 'FieldtypeCheckbox',
        'label' => 'Hide from Search Engines (noindex)',
        'description' => 'Check to prevent this page from appearing in Google.',
    ],
    'robots_nofollow' => [
        'type' => 'FieldtypeCheckbox', 
        'label' => 'No Follow Links (nofollow)',
        'description' => 'Check to prevent search engines from following links on this page.',
    ],
];

// Create fields
foreach ($seoFields as $name => $config) {
    if ($fields->get($name)) {
        echo "Field '$name' already exists, skipping.\n";
        continue;
    }
    
    $f = new Field();
    $f->name = $name;
    $f->type = wire('modules')->get($config['type']);
    $f->label = $config['label'];
    $f->description = $config['description'] ?? '';
    
    if (isset($config['maxlength'])) $f->maxlength = $config['maxlength'];
    if (isset($config['rows'])) $f->rows = $config['rows'];
    if (isset($config['maxFiles'])) $f->maxFiles = $config['maxFiles'];
    if (isset($config['extensions'])) $f->extensions = $config['extensions'];
    
    $f->save();
    echo "Created field: $name\n";
}

// Templates to add fields to
$targetTemplates = ['home', 'basic-page'];

// Add fields to templates
foreach ($targetTemplates as $tplName) {
    $tpl = $templates->get($tplName);
    if (!$tpl) {
        echo "Template '$tplName' not found, skipping.\n";
        continue;
    }
    
    $fg = $tpl->fieldgroup;
    $added = [];
    
    foreach (array_keys($seoFields) as $fieldName) {
        $field = $fields->get($fieldName);
        if (!$field) continue;
        
        if (!$fg->hasField($field)) {
            $fg->add($field);
            $added[] = $fieldName;
        }
    }
    
    if ($added) {
        $fg->save();
        echo "Added to '$tplName': " . implode(', ', $added) . "\n";
    } else {
        echo "Template '$tplName': fields already present.\n";
    }
}

echo "\n<strong>DONE!</strong>\n";
echo "\nNOW DELETE THIS FILE: site/install-seo-fields.php\n";
echo "</pre>";
