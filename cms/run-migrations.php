<?php
/**
 * Migration Runner
 *
 * Run via: https://cms.bioco.ch/run-migrations/?phase=1
 * Phases: 1-10 (or "all" to run sequentially)
 *
 * Must be accessed as a ProcessWire template page.
 * Create page: /run-migrations/ with template that uses this file.
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$phase = isset($input) ? $input->get('phase', 'text') : ($_GET['phase'] ?? '');

if (!$phase) {
    echo json_encode([
        'error' => 'Missing ?phase= parameter',
        'usage' => 'Add ?phase=1 through ?phase=10',
        'phases' => [
            '1' => 'Media Library',
            '2' => 'Page Templates',
            '3' => 'Consolidate Image Fields',
            '4' => 'Image Styling Fields',
            '5' => 'CKEditor Configuration',
            '6' => 'Button Labels',
            '7' => 'Eyebrow Field Verification',
            '8' => 'Navigation Field',
            '9' => 'German Labels Translation',
            '10' => 'Cleanup Unused Fields',
        ],
    ], JSON_PRETTY_PRINT);
    return;
}

$scriptMap = [
    '1' => 'setup-media-library.php',
    '2' => 'create-page-templates.php',
    '3' => 'consolidate-image-fields.php',
    '4' => 'add-image-styling-fields.php',
    '5' => 'configure-ckeditor-fields.php',
    '6' => 'update-button-labels.php',
    '7' => 'verify-eyebrow-field.php',
    '8' => 'add-navigation-field.php',
    '9' => 'translate-labels-german.php',
    '10' => 'cleanup-unused-fields.php',
    'fix' => 'fix-phase6-phase8.php',
    'populate' => 'populate-all-pages.php',
    'media' => 'install-media-library.php',
];

if (!isset($scriptMap[$phase])) {
    echo json_encode(['error' => "Invalid phase: $phase. Use 1-10."]);
    return;
}

$scriptFile = __DIR__ . '/' . $scriptMap[$phase];

if (!file_exists($scriptFile)) {
    echo json_encode(['error' => "Script not found: " . $scriptMap[$phase]]);
    return;
}

// Override the security check in scripts (we're already in ProcessWire context)
$config->debug = true;

include $scriptFile;
