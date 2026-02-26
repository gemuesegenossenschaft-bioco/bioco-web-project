<?php
/**
 * Migration: Enforce consistent CKEditor toolbar and settings
 * Run via bootstrap: curl https://cms.bioco.ch/bootstrap-enforce-ckeditor.php
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

$toolbar = 'Format, Bold, Italic, Underline, -, BulletedList, NumberedList, -, JustifyLeft, JustifyCenter, JustifyRight, -, PWLink, Unlink, -, PWImage, -, Source';
$formatTags = 'p;h1;h2;h3';
$contentsCss = '/site/templates/styles/ckeditor.css';

echo "Enforcing CKEditor config on all fields...\n\n";

foreach ($ckeditorFieldNames as $fieldName) {
    $field = $fields->get($fieldName);
    if (!$field) {
        echo "✗ {$fieldName}: FIELD NOT FOUND (skip)\n";
        continue;
    }

    $inputfield = $field->inputfieldClass ?: '';
    if ($inputfield !== 'InputfieldCKEditor') {
        // Upgrade to CKEditor
        $field->inputfieldClass = 'InputfieldCKEditor';
        echo "  Upgraded {$fieldName} to InputfieldCKEditor\n";
    }

    $field->toolbar = $toolbar;
    $field->formatTags = $formatTags;
    $field->contentsCss = $contentsCss;
    $field->save();

    echo "✓ {$fieldName}: toolbar, formatTags, contentsCss updated\n";
}

echo "\nDone.\n";
