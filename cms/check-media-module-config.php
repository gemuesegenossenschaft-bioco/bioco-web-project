<?php
/**
 * Check and configure ProcessMediaLibraries module for navbar display
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Media Module Configuration ===\n";

    // Check if ProcessMediaLibraries is installed
    if (!$modules->isInstalled('ProcessMediaLibraries')) {
        $errors[] = "ProcessMediaLibraries module not installed";
        $log[] = "Install it via: Setup → Modules → New → ProcessMediaLibraries";
    } else {
        $log[] = "✓ ProcessMediaLibraries is installed";

        // Get module info
        $moduleInfo = $modules->getModuleInfo('ProcessMediaLibraries', ['verbose' => true]);
        $log[] = "\nModule Info:";
        $log[] = "  Title: " . ($moduleInfo['title'] ?? 'N/A');
        $log[] = "  Version: " . ($moduleInfo['version'] ?? 'N/A');
        $log[] = "  Autoload: " . (isset($moduleInfo['autoload']) ? ($moduleInfo['autoload'] ? 'yes' : 'no') : 'N/A');
        $log[] = "  Singular: " . (isset($moduleInfo['singular']) ? ($moduleInfo['singular'] ? 'yes' : 'no') : 'N/A');

        if (isset($moduleInfo['nav'])) {
            $log[] = "  Nav: " . json_encode($moduleInfo['nav']);
        } else {
            $log[] = "  Nav: not configured in module info";
        }

        // Check media page
        $mediaPage = $pages->get(1768); // We know the ID from earlier
        if ($mediaPage->id) {
            $log[] = "\nMedia Page:";
            $log[] = "  Path: " . $mediaPage->path;
            $log[] = "  Process: " . $mediaPage->process;
            $log[] = "  Status: " . ($mediaPage->isHidden() ? 'hidden' : 'visible');
        }
    }

    $log[] = "\n=== Manual Fix ===";
    $log[] = "ProcessWire admin navbar is controlled by Process modules.";
    $log[] = "ProcessMediaLibraries should auto-register in Setup menu.";
    $log[] = "\nIf not visible:";
    $log[] = "1. Go to Setup → Modules";
    $log[] = "2. Find ProcessMediaLibraries";
    $log[] = "3. Click Refresh to rebuild module cache";
    $log[] = "4. Check if 'Media' appears under Setup or Pages menu";
    $log[] = "\nAlternative: Access directly at /processwire/media/";

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
