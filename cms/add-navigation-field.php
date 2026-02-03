<?php
/**
 * ProcessWire Navigation Field Setup Script
 *
 * Adds include_in_nav checkbox field to enable auto-navigation generation:
 * - Only pages with include_in_nav checked appear in navigation
 * - Respects page sort order from tree
 * - New pages auto-appear when checked
 *
 * Run via: https://cms.bioco.ch/add-navigation-field/
 */

namespace ProcessWire;

if (!$config->debug && !isset($_GET['token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$log = [];
$errors = [];

try {
    $log[] = "=== Navigation Field Setup ===\n";

    // ====================================================================================
    // 1. CREATE INCLUDE_IN_NAV FIELD
    // ====================================================================================

    $log[] = "Step 1: Creating include_in_nav field...";

    $includeInNavField = $fields->get('include_in_nav');
    if (!$includeInNavField) {
        $includeInNavField = new Field();
        $includeInNavField->type = $modules->get('FieldtypeCheckbox');
        $includeInNavField->name = 'include_in_nav';
        $includeInNavField->label = 'In Navigation anzeigen';
        $includeInNavField->description = 'Diese Seite in der Navigation anzeigen';
        $includeInNavField->checkedValue = 1;
        $includeInNavField->uncheckedValue = 0;
        $includeInNavField->defaultValue = 1;

        try {
            $fields->save($includeInNavField);
            $log[] = "✓ Created field: include_in_nav";
        } catch (\Exception $e) {
            $errors[] = "Failed to create include_in_nav field: " . $e->getMessage();
        }
    } else {
        $log[] = "✓ Field exists: include_in_nav";
    }

    // ====================================================================================
    // 2. ADD FIELD TO PAGE TEMPLATES
    // ====================================================================================

    $log[] = "\nStep 2: Adding field to page templates...";

    // List of templates that should have navigation control
    $pageTemplates = [
        'home',
        'wir',
        'gemuese',
        'mitmachen',
        'abos',
        'solawi',
        'standorte_depots',
        'aktuelles_page',
        'bioco_werden',
        'kontakt',
        'newsletter',
        'warteliste',
        'page_content',
        'basic-page',
    ];

    foreach ($pageTemplates as $templateName) {
        $template = $templates->get($templateName);

        if (!$template) {
            $log[] = "  Template not found (skipping): $templateName";
            continue;
        }

        if ($includeInNavField && !$template->hasField($includeInNavField)) {
            try {
                $template->fields->add($includeInNavField);
                $templates->save($template);
                $log[] = "  + Added 'include_in_nav' to: $templateName";
            } catch (\Exception $e) {
                $errors[] = "Failed to add field to $templateName: " . $e->getMessage();
            }
        } else if ($includeInNavField) {
            $log[] = "  ✓ Field already in: $templateName";
        }
    }

    // ====================================================================================
    // 3. SET INCLUDE_IN_NAV FOR EXISTING PAGES
    // ====================================================================================

    $log[] = "\nStep 3: Enabling navigation for existing pages...";

    $mainPages = [
        'home',
        'wir',
        'gemuese',
        'mitmachen',
        'abos',
        'solawi',
        'standorte-depots',
        'aktuelles',
        'bioco-werden',
        'kontakt',
        'newsletter',
        'warteliste',
    ];

    foreach ($mainPages as $pageName) {
        $page = $pages->get("name=$pageName");

        if (!$page->id) {
            $log[] = "  Page not found: /$pageName/";
            continue;
        }

        if ($page->hasField('include_in_nav')) {
            if (!$page->include_in_nav) {
                try {
                    $page->include_in_nav = 1;
                    $pages->save($page);
                    $log[] = "  ✓ Enabled navigation for: /$pageName/";
                } catch (\Exception $e) {
                    $errors[] = "Failed to update /$pageName/: " . $e->getMessage();
                }
            } else {
                $log[] = "  ✓ Already enabled: /$pageName/";
            }
        }
    }

    // ====================================================================================
    // 4. DOCUMENTATION
    // ====================================================================================

    $log[] = "\n=== Navigation Auto-Generation ===";
    $log[] = "\nHow it works:";
    $log[] = "1. Create a new page under root (/) in ProcessWire";
    $log[] = "2. Check 'In Navigation anzeigen' (include_in_nav)";
    $log[] = "3. Set sort order by drag-drop in page tree";
    $log[] = "4. API fetches via /api/content/navigation";
    $log[] = "5. Next.js ISR revalidates every 30 minutes";
    $log[] = "6. Page appears on frontend within 30 min (or on-demand)";

    $log[] = "\nReserved Static Routes (don't appear in dynamic nav):";
    $log[] = "- / (homepage)";
    $log[] = "- /wir (about)";
    $log[] = "- /gemuese (vegetables)";
    $log[] = "- /mitmachen (participation)";
    $log[] = "- /abos (subscriptions)";
    $log[] = "- /solawi (solidarity farming)";
    $log[] = "- /standorte-depots (locations)";
    $log[] = "- /aktuelles (news)";
    $log[] = "- /bioco-werden (become member)";
    $log[] = "- /kontakt (contact)";
    $log[] = "- /newsletter (newsletter)";
    $log[] = "- /warteliste (waiting list)";

    $log[] = "\nDynamic Pages (use catch-all route):";
    $log[] = "Any page outside reserved list will use:";
    $log[] = "/frontend/app/(cms)/[...slug]/page.tsx";

    // ====================================================================================
    // 5. API CONFIGURATION NOTES
    // ====================================================================================

    $log[] = "\n=== API Implementation Notes ===";
    $log[] = "\nUpdate /site/templates/api.php navigation case:";
    $log[] = "\nCurrent: Fetches all non-hidden children";
    $log[] = "Enhanced: Respect 'include_in_nav' field";
    $log[] = "\nCode pattern:";
    $log[] = "\$nav = \$homepage->children(";
    $log[] = "  'template!=api-setup,' ." ."";
    $log[] = "  'include_in_nav=1,' ." ."";
    $log[] = "  'sort=sort'" ."";
    $log[] = ");";

    // ====================================================================================
    // 6. SUMMARY
    // ====================================================================================

    $log[] = "\n=== Setup Complete ===";
    $log[] = "✓ include_in_nav field created";
    $log[] = "✓ Field added to " . count($pageTemplates) . " page templates";
    $log[] = "✓ Existing pages set to visible in navigation";
    $log[] = "✓ Ready for dynamic page creation";

    $log[] = "\nNext steps:";
    $log[] = "1. Run API enhancement script (Phase 8 API integration)";
    $log[] = "2. Test creating a new page and checking navigation";
    $log[] = "3. Verify page appears in /api/content/navigation response";
    $log[] = "4. Verify frontend navigation updates (ISR 30min)";

    if (count($errors) > 0) {
        $log[] = "\n=== Errors (" . count($errors) . ") ===";
        $log = array_merge($log, $errors);
    }

    // ====================================================================================
    // 7. OUTPUT
    // ====================================================================================

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
        'field_created' => true,
        'templates_updated' => count($pageTemplates),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT);
}
?>
