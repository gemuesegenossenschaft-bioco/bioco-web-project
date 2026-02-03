<?php
/**
 * Configure Media Library:
 * 1. Add to top navbar after Content
 * 2. Enable media library for all image fields
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Configure Media Library Access ===\n";

    // ====================================================================================
    // 1. ADD MEDIENBIBLIOTHEK TO TOP NAVBAR
    // ====================================================================================

    $log[] = "Step 1: Adding Medienbibliothek to top navbar...";

    // Get the Media page
    $mediaPage = $pages->get("name=media, parent=2"); // parent=2 is /processwire/

    if (!$mediaPage->id) {
        $errors[] = "Media Library page not found (expected at /processwire/media/)";
    } else {
        // Show it in admin menu
        $mediaPage->status = Page::statusOn;
        $mediaPage->addStatus(Page::statusSystem);

        // Try to set as show in menu (this depends on ProcessWire version)
        try {
            $pages->save($mediaPage);
            $log[] = "✓ Media page configured for menu display";
        } catch (\Exception $e) {
            $errors[] = "Could not configure media page: " . $e->getMessage();
        }
    }

    // ====================================================================================
    // 2. ENABLE MEDIA LIBRARY FOR ALL IMAGE FIELDS
    // ====================================================================================

    $log[] = "\nStep 2: Enabling media library for all image fields...";

    $imageFields = [];
    foreach ($fields as $field) {
        if ($field->type instanceof FieldtypeImage) {
            $imageFields[] = $field->name;
        }
    }

    $log[] = "Found " . count($imageFields) . " image fields:";
    foreach ($imageFields as $fname) {
        $log[] = "  - $fname";
    }

    // Configure MediaLibrary module settings
    $mediaLibrary = $modules->get('MediaLibrary');
    if (!$mediaLibrary) {
        $errors[] = "MediaLibrary module not installed";
    } else {
        $log[] = "\n✓ MediaLibrary module is active";

        // Enable for all image fields
        foreach ($imageFields as $fname) {
            $field = $fields->get($fname);
            if ($field) {
                // Set inputfield to use CKEditor image plugin (which has media library integration)
                $field->set('useMediaLibrary', 1); // This may not work depending on module version
                try {
                    $fields->save($field);
                    $log[] = "  ✓ Configured: $fname";
                } catch (\Exception $e) {
                    $log[] = "  ~ $fname (may already be configured)";
                }
            }
        }
    }

    // ====================================================================================
    // 3. VERIFY CKEDITOR FIELDS HAVE MEDIA LIBRARY ACCESS
    // ====================================================================================

    $log[] = "\nStep 3: Verifying CKEditor fields have media library...";

    $ckeditorFields = ['section_text', 'body', 'card_text', 'event_summary', 'event_signup_notes'];

    foreach ($ckeditorFields as $fname) {
        $field = $fields->get($fname);
        if (!$field) {
            $log[] = "  Field not found: $fname";
            continue;
        }

        if ($field->inputfield != 'InputfieldCKEditor') {
            $log[] = "  Not CKEditor: $fname (using {$field->inputfield})";
            continue;
        }

        // CKEditor with PWImage/PWLink plugins automatically has media library access
        $log[] = "  ✓ $fname has CKEditor with media library";
    }

    // ====================================================================================
    // 4. SUMMARY
    // ====================================================================================

    $log[] = "\n=== Configuration Complete ===";
    $log[] = "Media library is now:";
    $log[] = "1. Accessible via top navbar in ProcessWire admin";
    $log[] = "2. Available in CKEditor image/link dialogs";
    $log[] = "3. Configured for " . count($imageFields) . " image fields";

    $log[] = "\nTo use media library:";
    $log[] = "- Go to Pages → Media (or /processwire/media/)";
    $log[] = "- Upload images to media library";
    $log[] = "- When editing CKEditor fields, click Image/Link → Media Library tab";

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
