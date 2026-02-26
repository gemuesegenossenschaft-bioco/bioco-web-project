<?php
/**
 * Create /api/events/ page for events API
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Setup Events API Page ===\n";

    // Create api-events template if doesn't exist
    $apiTemplate = $templates->get('api-events');
    if (!$apiTemplate) {
        $fg = new Fieldgroup();
        $fg->name = 'api-events';
        $fg->add($fields->get('title'));
        $fieldgroups->save($fg);

        $apiTemplate = new Template();
        $apiTemplate->name = 'api-events';
        $apiTemplate->label = 'API: Events';
        $apiTemplate->fieldgroup = $fg;
        $apiTemplate->noChildren = 1;
        $apiTemplate->noParents = 1;
        $templates->save($apiTemplate);

        $log[] = "✓ Created api-events template";
    } else {
        $log[] = "✓ api-events template exists";
    }

    // Create /api/ parent if doesn't exist
    $apiParent = $pages->get("name=api");
    if (!$apiParent->id) {
        $apiParent = new Page();
        $apiParent->template = 'admin';
        $apiParent->parent = '/';
        $apiParent->name = 'api';
        $apiParent->title = 'API';
        $pages->save($apiParent);
        $log[] = "✓ Created /api/ parent page";
    }

    // Create /api/events/ page
    $eventsApi = $pages->get("name=events, parent=$apiParent");
    if (!$eventsApi->id) {
        $eventsApi = new Page();
        $eventsApi->template = $apiTemplate;
        $eventsApi->parent = $apiParent;
        $eventsApi->name = 'events';
        $eventsApi->title = 'Events API';
        $pages->save($eventsApi);
        $log[] = "✓ Created /api/events/ page";
    } else {
        $log[] = "✓ /api/events/ page exists";
    }

    $log[] = "\n=== API Ready ===";
    $log[] = "URL: https://cms.bioco.ch/api/events/";
    $log[] = "Template file needed: site/templates/api-events.php";

    echo json_encode([
        'success' => true,
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
