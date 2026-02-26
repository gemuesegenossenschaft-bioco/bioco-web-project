<?php
/**
 * Add Media Library page to ProcessWire admin navbar
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Add Media to Navbar ===\n";

    // Get the media page
    $mediaPage = $pages->get("name=media, parent=2");

    if (!$mediaPage->id) {
        $errors[] = "Media page not found at /processwire/media/";
    } else {
        $log[] = "Found media page (ID: {$mediaPage->id})";

        // Make sure it's visible
        $mediaPage->of(false);
        $mediaPage->status = Page::statusOn;

        // Remove hidden status if present
        $mediaPage->removeStatus(Page::statusHidden);

        // Save
        $pages->save($mediaPage);
        $log[] = "✓ Media page status updated";

        // Check template
        $template = $mediaPage->template;
        $log[] = "Template: {$template->name}";

        // Get ProcessMediaLibraries process module
        $processModule = $modules->getModuleInfo('ProcessMediaLibraries');
        if ($processModule) {
            $log[] = "ProcessMediaLibraries module info:";
            $log[] = "  Title: " . ($processModule['title'] ?? 'N/A');
            $log[] = "  Nav: " . (isset($processModule['nav']) ? json_encode($processModule['nav']) : 'not set');
        }
    }

    $log[] = "\n=== Note ===";
    $log[] = "Media Library nav visibility is controlled by ProcessMediaLibraries module.";
    $log[] = "Check: Setup → Modules → ProcessMediaLibraries → Configure";
    $log[] = "Or manually add via: Setup → Templates → admin → URLs tab";

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
