<?php
/**
 * Diagnostic/Fix: Remove duplicate Media admin pages
 * Run via bootstrap: curl https://cms.bioco.ch/bootstrap-fix-media-tab.php
 */

namespace ProcessWire;

// Only allow from CLI or localhost
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Forbidden: run from localhost or CLI only');
}

require_once __DIR__ . '/../site/init.php';

$pages = wire('pages');

// Find all admin pages related to media
$mediaPages = $pages->find('parent=/processwire/, name*=media, include=all');

echo "Found " . $mediaPages->count() . " admin pages matching 'media':\n\n";

$canonical = null;
$duplicates = [];

foreach ($mediaPages as $mp) {
    $module = $mp->process ?: 'none';
    echo "  ID: {$mp->id} | Name: {$mp->name} | Title: {$mp->title} | Process: {$module} | Status: {$mp->status}\n";

    if ($mp->name === 'media' && !$canonical) {
        $canonical = $mp;
    } elseif ($mp->name !== 'media' && stripos($mp->name, 'media') !== false) {
        $duplicates[] = $mp;
    }
}

if (empty($duplicates)) {
    echo "\nNo duplicates found. Nothing to do.\n";
} else {
    echo "\nDuplicates to remove:\n";
    foreach ($duplicates as $dup) {
        echo "  Deleting: ID {$dup->id} ({$dup->name})\n";
        $pages->delete($dup, true);
    }
    echo "Done.\n";
}

// Also check for the MediaLibrary module config
$modules = wire('modules');
if ($modules->isInstalled('MediaLibrary')) {
    echo "\nMediaLibrary module is installed.\n";
    $ml = $modules->get('MediaLibrary');
    echo "Config: " . print_r($modules->getModuleConfigData('MediaLibrary'), true) . "\n";
} else {
    echo "\nMediaLibrary module is NOT installed.\n";
}
