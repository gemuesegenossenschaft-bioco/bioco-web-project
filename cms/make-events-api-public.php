<?php
/**
 * Make events API publicly accessible
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];

try {
    $eventsApi = $pages->get("name=events, template=api-events");

    if ($eventsApi->id) {
        $eventsApi->of(false);
        $eventsApi->status = Page::statusOn;
        $eventsApi->removeStatus(Page::statusHidden);
        $eventsApi->addStatus(Page::statusSystem);
        $eventsApi->viewable = true;
        $pages->save($eventsApi);
        $log[] = "✓ Events API page is now public";
    }

    // Make /api/ parent public too
    $apiParent = $pages->get("name=api");
    if ($apiParent->id) {
        $apiParent->of(false);
        $apiParent->status = Page::statusOn;
        $apiParent->removeStatus(Page::statusHidden);
        $apiParent->viewable = true;
        $pages->save($apiParent);
        $log[] = "✓ API parent page is now public";
    }

    // Make template guest-accessible
    $template = $templates->get('api-events');
    if ($template) {
        $template->guestAccess = true;
        $templates->save($template);
        $log[] = "✓ api-events template allows guest access";
    }

    $log[] = "\nAPI should now work at: https://cms.bioco.ch/api/events/";

    echo json_encode([
        'success' => true,
        'log' => $log,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
    ], JSON_PRETTY_PRINT);
}
