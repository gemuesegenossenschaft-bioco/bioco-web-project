<?php
/**
 * Setup Events and News (Aktuelles) as sortable children
 * - Events as children of /aktuelles/events/
 * - News items as children of /aktuelles/
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Setup Events & News Structure ===\n";

    // ====================================================================================
    // 1. CONFIGURE AKTUELLES PAGE TO ALLOW CHILDREN
    // ====================================================================================

    $log[] = "Step 1: Configure Aktuelles page...";

    $aktuelles = $pages->get("name=aktuelles");
    if (!$aktuelles->id) {
        $errors[] = "Aktuelles page not found";
    } else {
        $log[] = "✓ Found: {$aktuelles->path}";

        // Get aktuelles_page template
        $aktuellesTemplate = $templates->get('aktuelles_page');
        if ($aktuellesTemplate) {
            // Allow children
            $aktuellesTemplate->noChildren = 0;
            $aktuellesTemplate->childTemplates = ['event', 'news_item'];
            $templates->save($aktuellesTemplate);
            $log[] = "✓ Aktuelles can now have events and news items as children";
        }
    }

    // ====================================================================================
    // 2. CREATE NEWS_ITEM TEMPLATE (if needed)
    // ====================================================================================

    $log[] = "\nStep 2: Creating news_item template...";

    $newsItemTemplate = $templates->get('news_item');
    if (!$newsItemTemplate) {
        // Create fieldgroup
        $fg = new Fieldgroup();
        $fg->name = 'news_item';
        $fg->add($fields->get('title'));
        $fg->add($fields->get('event_summary')); // Short summary
        $fg->add($fields->get('body')); // Full content
        $fg->add($fields->get('card_image')); // Card image
        $fg->add($fields->get('image_alt')); // Image alt text
        $fieldgroups->save($fg);

        // Create template
        $newsItemTemplate = new Template();
        $newsItemTemplate->name = 'news_item';
        $newsItemTemplate->label = 'Nachrichtenelement';
        $newsItemTemplate->fieldgroup = $fg;
        $newsItemTemplate->noChildren = 1;
        $newsItemTemplate->noParents = 0;
        $newsItemTemplate->parentTemplates = ['aktuelles_page'];
        $templates->save($newsItemTemplate);

        $log[] = "✓ Created news_item template";
    } else {
        $log[] = "✓ news_item template exists";
    }

    // ====================================================================================
    // 3. CONFIGURE EVENT TEMPLATE
    // ====================================================================================

    $log[] = "\nStep 3: Configure event template...";

    $eventTemplate = $templates->get('event');
    if ($eventTemplate) {
        $eventTemplate->noChildren = 1;
        $eventTemplate->noParents = 0;
        $eventTemplate->parentTemplates = ['aktuelles_page'];
        $templates->save($eventTemplate);
        $log[] = "✓ Events can be children of Aktuelles";
    } else {
        $errors[] = "Event template not found";
    }

    // ====================================================================================
    // 4. ENABLE PAGE SORTING
    // ====================================================================================

    $log[] = "\nStep 4: Enable drag-drop sorting...";

    if ($aktuelles->id) {
        $aktuelles->of(false);
        $aktuelles->sortfield = 'sort'; // Manual sorting
        $pages->save($aktuelles);
        $log[] = "✓ Aktuelles page: drag-drop sorting enabled";
    }

    // ====================================================================================
    // 5. SUMMARY
    // ====================================================================================

    $log[] = "\n=== Setup Complete ===";
    $log[] = "\nPage structure:";
    $log[] = "/aktuelles/ (Aktuelles)";
    $log[] = "  ├─ Event 1 (template: event)";
    $log[] = "  ├─ Event 2 (template: event)";
    $log[] = "  ├─ News Item 1 (template: news_item)";
    $log[] = "  └─ News Item 2 (template: news_item)";

    $log[] = "\nHow to create/edit:";
    $log[] = "1. Go to Pages → Aktuelles";
    $log[] = "2. Click 'Add New' → Choose 'Event' or 'Nachrichtenelement'";
    $log[] = "3. Fill in details and save";
    $log[] = "4. Drag pages up/down to reorder";

    $log[] = "\nHow to modify:";
    $log[] = "1. Go to Pages → Aktuelles";
    $log[] = "2. Click on any event or news item";
    $log[] = "3. Edit fields and save";

    if (count($errors) > 0) {
        $log[] = "\nErrors (" . count($errors) . "):";
        $log = array_merge($log, $errors);
    }

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
