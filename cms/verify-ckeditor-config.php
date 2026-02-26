<?php
/**
 * Diagnostic: Verify all CKEditor fields use consistent config
 * Run via bootstrap: curl https://cms.bioco.ch/bootstrap-verify-ckeditor.php
 */

namespace ProcessWire;

// Only allow from CLI or localhost
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Forbidden: run from localhost or CLI only');
}

require_once __DIR__ . '/../site/init.php';

$fields = wire('fields');

$ckeditorFieldNames = [
    'section_text',
    'body',
    'card_text',
    'event_summary',
    'event_signup_notes',
];

echo "CKEditor Field Configuration Audit\n";
echo str_repeat('=', 50) . "\n\n";

foreach ($ckeditorFieldNames as $fieldName) {
    $field = $fields->get($fieldName);
    if (!$field) {
        echo "✗ {$fieldName}: FIELD NOT FOUND\n\n";
        continue;
    }

    $type = $field->type->className();
    echo "Field: {$fieldName}\n";
    echo "  Type: {$type}\n";

    if ($type !== 'FieldtypeTextarea') {
        echo "  ✗ NOT a textarea/CKEditor field\n\n";
        continue;
    }

    $inputfield = $field->inputfieldClass ?: 'InputfieldTextarea';
    echo "  Inputfield: {$inputfield}\n";

    if ($inputfield !== 'InputfieldCKEditor') {
        echo "  ✗ Not using CKEditor (using: {$inputfield})\n\n";
        continue;
    }

    // Read CKEditor settings
    $toolbar = $field->toolbar ?: '(not set)';
    $formatTags = $field->formatTags ?: '(not set)';
    $contentsCss = $field->contentsCss ?: '(not set)';
    $extraPlugins = $field->extraPlugins ?: '(not set)';

    echo "  Toolbar: {$toolbar}\n";
    echo "  Format tags: {$formatTags}\n";
    echo "  Contents CSS: {$contentsCss}\n";
    echo "  Extra plugins: {$extraPlugins}\n";
    echo "\n";
}

echo "Done.\n";
