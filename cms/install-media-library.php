<?php
/**
 * Install BitPoet/MediaLibrary module + ensure CKEditor on all textareas
 *
 * Run via: https://cms.bioco.ch/run-migrations/?phase=media
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Install MediaLibrary + CKEditor Config ===\n";

    // ====================================================================================
    // 1. REFRESH MODULE CACHE
    // ====================================================================================

    $log[] = "Step 1: Refreshing module cache...";
    $modules->resetCache();
    $modules->refresh();
    $log[] = "Module cache refreshed";

    // ====================================================================================
    // 2. INSTALL MEDIALIBRARY MODULE
    // ====================================================================================

    $log[] = "\nStep 2: Installing MediaLibrary module...";

    if ($modules->isInstalled('MediaLibrary')) {
        $log[] = "MediaLibrary already installed, skipping";
    } else {
        // Check if module files are present
        $modulePath = $config->paths->siteModules . 'MediaLibrary/MediaLibrary.module';
        if (!file_exists($modulePath)) {
            $errors[] = "MediaLibrary.module not found at: $modulePath";
            throw new \Exception("Module files missing");
        }
        $log[] = "Module files found at: $modulePath";

        try {
            $modules->install('MediaLibrary');
            $log[] = "MediaLibrary module installed";
        } catch (\Exception $e) {
            $errors[] = "MediaLibrary install failed: " . $e->getMessage();
            throw $e;
        }
    }

    // ProcessMediaLibraries auto-installs with MediaLibrary (declared in "installs" array)
    if ($modules->isInstalled('ProcessMediaLibraries')) {
        $log[] = "ProcessMediaLibraries already installed";
    } else {
        try {
            $modules->install('ProcessMediaLibraries');
            $log[] = "ProcessMediaLibraries installed manually";
        } catch (\Exception $e) {
            $errors[] = "ProcessMediaLibraries install error: " . $e->getMessage();
        }
    }

    // ====================================================================================
    // 3. CONFIGURE MODULE SETTINGS
    // ====================================================================================

    $log[] = "\nStep 3: Configuring MediaLibrary settings...";

    $ml = $modules->get('MediaLibrary');
    if ($ml) {
        $configData = $modules->getModuleConfigData('MediaLibrary');
        $configData['medialibrarycollapsed'] = '1';
        $configData['medialibraryhidepages'] = false;
        $configData['medialibraryhidepagesadmin'] = false;
        $configData['medialibraryinput'] = 'InputfieldSelect';
        $modules->saveModuleConfigData('MediaLibrary', $configData);
        $log[] = "MediaLibrary config saved (collapsed: yes, hide pages: no)";
    } else {
        $errors[] = "Could not get MediaLibrary module instance";
    }

    // ====================================================================================
    // 4. ADD MEDIA-LIBRARY PERMISSION TO ALL ROLES
    // ====================================================================================

    $log[] = "\nStep 4: Setting up permissions...";

    $perm = $permissions->get('media-library');
    if (!$perm || !$perm->id) {
        $log[] = "media-library permission not found (module should have created it)";
    } else {
        // Grant to superuser role (should already have it) and any editor roles
        foreach (['superuser', 'editor', 'guest'] as $roleName) {
            $role = $roles->get($roleName);
            if ($role && $role->id && $roleName !== 'guest') {
                if (!$role->hasPermission($perm)) {
                    $role->addPermission($perm);
                    $role->save();
                    $log[] = "Granted media-library to role: $roleName";
                } else {
                    $log[] = "Role $roleName already has media-library permission";
                }
            }
        }
    }

    // ====================================================================================
    // 5. CREATE ROOT MEDIA LIBRARY PAGE (under /)
    // ====================================================================================

    $log[] = "\nStep 5: Creating root media library page...";

    $mlTemplate = $templates->get('MediaLibrary');
    if (!$mlTemplate) {
        $errors[] = "MediaLibrary template not found (module install may have failed)";
    } else {
        $rootLib = $pages->get("template=MediaLibrary, parent=/");
        if ($rootLib && $rootLib->id) {
            $log[] = "Root media library already exists: {$rootLib->title} (id: {$rootLib->id})";
        } else {
            $rootLib = new Page();
            $rootLib->template = $mlTemplate;
            $rootLib->parent = $pages->get(1);
            $rootLib->title = 'Medienbibliothek';
            $rootLib->name = 'medienbibliothek';
            $pages->save($rootLib);
            $log[] = "Created root media library: /medienbibliothek/ (id: {$rootLib->id})";
        }
    }

    // ====================================================================================
    // 6. CONFIGURE CKEDITOR ON ALL TEXTAREA FIELDS
    // ====================================================================================

    $log[] = "\nStep 6: Configuring CKEditor on textarea fields...";

    $ckeditor = $modules->get('InputfieldCKEditor');
    if (!$ckeditor) {
        $errors[] = "InputfieldCKEditor module not available";
    } else {
        $log[] = "InputfieldCKEditor module available";
    }

    $textareaFields = [
        'section_text' => 'Bereichstext',
        'body' => 'Vollständige Beschreibung',
        'card_text' => 'Kartentexte',
        'event_summary' => 'Kurzbeschreibung',
        'event_signup_notes' => 'Anmeldungshinweise',
    ];

    $toolbar = "Format, Bold, Italic, -, BulletedList, NumberedList, -, Link, Unlink, -, PWImage, PWLink, -, Source";

    foreach ($textareaFields as $fieldName => $label) {
        $field = $fields->get($fieldName);

        if (!$field) {
            $log[] = "  Field not found: $fieldName (skipping)";
            continue;
        }

        $changed = false;

        // Set inputfield class to CKEditor
        if ($field->inputfieldClass !== 'InputfieldCKEditor') {
            $field->inputfieldClass = 'InputfieldCKEditor';
            $changed = true;
        }

        // Set content type to HTML (1 = FieldtypeTextarea::contentTypeHTML)
        if ($field->contentType != 1) {
            $field->contentType = 1;
            $changed = true;
        }

        // Set toolbar
        $field->toolbar = $toolbar;
        // Enable PWImage and PWLink plugins for media library integration
        $field->extraPlugins = ['pwimage', 'pwlink', 'sourcedialog'];
        // Clean formatting on paste
        $field->toggles = [2, 4]; // removeRedundantNBSP, pasteFromWord
        $changed = true;

        if ($changed) {
            try {
                $fields->save($field);
                $log[] = "  Configured: $fieldName (CKEditor + toolbar + plugins)";
            } catch (\Exception $e) {
                $errors[] = "Failed to save $fieldName: " . $e->getMessage();
            }
        } else {
            $log[] = "  Already configured: $fieldName";
        }
    }

    // ====================================================================================
    // 7. VERIFY
    // ====================================================================================

    $log[] = "\nStep 7: Verification...";

    // Check MediaLibrary module
    $log[] = "  MediaLibrary installed: " . ($modules->isInstalled('MediaLibrary') ? 'YES' : 'NO');
    $log[] = "  ProcessMediaLibraries installed: " . ($modules->isInstalled('ProcessMediaLibraries') ? 'YES' : 'NO');

    // Check template
    $mlTpl = $templates->get('MediaLibrary');
    $log[] = "  MediaLibrary template: " . ($mlTpl ? 'EXISTS' : 'MISSING');

    // Check fields created by module
    $log[] = "  MediaImages field: " . ($fields->get('MediaImages') ? 'EXISTS' : 'MISSING');
    $log[] = "  MediaFiles field: " . ($fields->get('MediaFiles') ? 'EXISTS' : 'MISSING');

    // Check admin page
    $admin = $pages->get($config->adminRootPageID);
    $mediaAdminPage = $admin->child("name=media");
    $log[] = "  Admin media page: " . ($mediaAdminPage && $mediaAdminPage->id ? "EXISTS (id: {$mediaAdminPage->id})" : 'MISSING');

    // Check CKEditor on fields
    foreach ($textareaFields as $fieldName => $label) {
        $f = $fields->get($fieldName);
        if ($f) {
            $log[] = "  $fieldName inputfieldClass: " . $f->inputfieldClass;
        }
    }

    $log[] = "\n=== Complete ===";

    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT);
}
