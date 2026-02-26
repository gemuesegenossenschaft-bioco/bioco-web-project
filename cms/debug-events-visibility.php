<?php
/**
 * Debug why events aren't showing up
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Debug Events Visibility ===\n";

    // Find all event pages
    $events = $pages->find("template=event, include=all");
    $log[] = "Found " . count($events) . " event pages (including hidden/unpublished):\n";

    foreach ($events as $event) {
        $status = [];
        if ($event->isUnpublished()) $status[] = 'unpublished';
        if ($event->isHidden()) $status[] = 'hidden';
        if ($event->isTrash()) $status[] = 'trash';
        if (count($status) === 0) $status[] = 'published';

        $log[] = "  - {$event->title} (ID: {$event->id})";
        $log[] = "    Path: {$event->path}";
        $log[] = "    Status: " . implode(', ', $status);
        $log[] = "    Parent: {$event->parent->path}";
        $log[] = "";
    }

    // Check API endpoint
    $log[] = "=== API Check ===";
    $publicEvents = $pages->find("template=event");
    $log[] = "Public events (API would return): " . count($publicEvents);

    foreach ($publicEvents as $event) {
        $log[] = "  ✓ {$event->title}";
    }

    // Check aktuelles page
    $log[] = "\n=== Aktuelles Page ===";
    $aktuelles = $pages->get("name=aktuelles");
    if ($aktuelles->id) {
        $log[] = "Path: {$aktuelles->path}";
        $log[] = "Children: " . count($aktuelles->children());
        foreach ($aktuelles->children() as $child) {
            $log[] = "  - {$child->title} (template: {$child->template->name})";
        }
    }

    // Suggestions
    $log[] = "\n=== Solutions ===";
    if (count($events) > count($publicEvents)) {
        $log[] = "Some events are unpublished or hidden.";
        $log[] = "To publish: Edit event → Settings tab → Check 'Published'";
    }

    if (count($events) === 0) {
        $log[] = "No events found. Create one:";
        $log[] = "1. Go to Pages → Aktuelles";
        $log[] = "2. Click '+ Add New'";
        $log[] = "3. Choose 'Event' template";
    }

    echo json_encode([
        'success' => true,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
        'total_events' => count($events),
        'public_events' => count($publicEvents),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT);
}
