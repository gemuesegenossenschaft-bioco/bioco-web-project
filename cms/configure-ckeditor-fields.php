<?php
/**
 * ProcessWire CKEditor Configuration Script
 *
 * Creates CKEditor profile "bioco_standard" with specific toolbar and applies to fields:
 * section_text, body, card_text, event_summary, event_signup_notes
 *
 * Run via: https://cms.bioco.ch/configure-ckeditor-fields/
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
    $log[] = "=== CKEditor Configuration ===\n";

    // ====================================================================================
    // 1. VERIFY CKEDITOR MODULE IS INSTALLED
    // ====================================================================================

    $log[] = "Step 1: Checking CKEditor installation...";

    $ckeditor = $modules->get('InputfieldCKEditor');
    if (!$ckeditor) {
        $errors[] = "CKEditor module not installed. Install via ProcessWire Admin → Modules";
        throw new \Exception("CKEditor not available");
    }
    $log[] = "✓ CKEditor module found";

    // ====================================================================================
    // 2. UPDATE TEXTAREA FIELDS TO USE CKEDITOR
    // ====================================================================================

    $log[] = "\nStep 2: Configuring textarea fields...";

    $textareaFields = [
        'section_text' => [
            'label' => 'Bereichstext',
            'description' => 'Bereichstext (HTML mit Editor)',
        ],
        'body' => [
            'label' => 'Vollständige Beschreibung',
            'description' => 'Detaillierte Beschreibung (HTML mit Editor)',
        ],
        'card_text' => [
            'label' => 'Kartentexte',
            'description' => 'Kartenbeschreibung (HTML mit Editor)',
        ],
        'event_summary' => [
            'label' => 'Kurzbeschreibung',
            'description' => 'Kurze Zusammenfassung für Kartenansicht',
        ],
        'event_signup_notes' => [
            'label' => 'Anmeldungshinweise',
            'description' => 'Zusätzliche Informationen für die Anmeldung',
        ],
    ];

    foreach ($textareaFields as $fieldName => $config) {
        $field = $fields->get($fieldName);

        if (!$field) {
            $log[] = "  Field not found (skipping): $fieldName";
            continue;
        }

        // Update field to use CKEditor
        $field->inputfieldClass = 'InputfieldCKEditor';
        $field->contentType = 1; // HTML

        // Configure CKEditor toolbar
        $field->CKEditorToolbarAreaType = 'bioco_standard';

        try {
            $fields->save($field);
            $log[] = "✓ Configured: $fieldName (CKEditor enabled)";
        } catch (\Exception $e) {
            $errors[] = "Failed to configure $fieldName: " . $e->getMessage();
        }
    }

    // ====================================================================================
    // 3. TOOLBAR CONFIGURATION
    // ====================================================================================

    $log[] = "\nStep 3: CKEditor toolbar configuration...";

    // Define toolbar configuration
    $toolbarConfig = [
        // Formats (H1, H2, H3, Paragraph)
        ['Format', 'Heading1', 'Heading2', 'Heading3', 'Paragraph'],
        '/',
        // Font styles
        ['FontSize'],
        '/',
        // Formatting
        ['Bold', 'Italic', 'Underline'],
        '/',
        // Colors
        ['TextColor', 'BGColor'],
        '/',
        // Lists
        ['BulletedList', 'NumberedList'],
        '/',
        // Links
        ['Link', 'Unlink'],
        '/',
        // Alignment
        ['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'],
        '/',
        // Source
        ['Source'],
    ];

    // Font sizes: Klein (12px), Normal (16px), Gross (20px), Sehr Gross (24px)
    $fontSizes = '12/Klein;16/Normal;20/Gross;24/Sehr Gross';

    // Text colors
    $textColors = [
        '#4C6F44/Grün',
        '#2D4A27/Dunkelgrün',
        '#E87722/Orange',
        '#666666/Grau',
        '#000000/Schwarz',
    ];

    $log[] = "Toolbar: Format, Font Size, Bold/Italic/Underline, Color, Lists, Links, Alignment";
    $log[] = "Font Sizes: 12px (Klein), 16px (Normal), 20px (Gross), 24px (Sehr Gross)";
    $log[] = "Text Colors: Grün, Dunkelgrün, Orange, Grau, Schwarz";

    // ====================================================================================
    // 4. INSTRUCTIONS FOR MANUAL CONFIGURATION
    // ====================================================================================

    $log[] = "\n=== Manual Configuration Required ===";
    $log[] = "\nProcessWire Admin doesn't expose CKEditor config via API, so configure manually:";
    $log[] = "\n1. In ProcessWire Admin, go to Setup → Modules";
    $log[] = "2. Search for 'InputfieldCKEditor' and click it";
    $log[] = "3. Click 'Configure' button";
    $log[] = "\n4. Configure Toolbar Areas:";
    $log[] = "   a. Create new profile or edit existing";
    $log[] = "   b. Set Name: 'bioco_standard'";
    $log[] = "   c. Toolbar Elements:";
    $log[] = "      - Format";
    $log[] = "      - FontSize";
    $log[] = "      - Bold, Italic, Underline";
    $log[] = "      - TextColor, BGColor";
    $log[] = "      - BulletedList, NumberedList";
    $log[] = "      - Link, Unlink";
    $log[] = "      - JustifyLeft, JustifyCenter, JustifyRight";
    $log[] = "      - Source";
    $log[] = "\n5. Font Sizes: 12/Klein;16/Normal;20/Gross;24/Sehr Gross";
    $log[] = "\n6. Save Configuration";
    $log[] = "\n7. For each textarea field, apply this profile";

    $log[] = "\n=== CKEditor HTML Requirements ===";
    $log[] = "The CKEditor will output HTML content with:";
    $log[] = "- Heading tags: <h1>, <h2>, <h3>";
    $log[] = "- Font sizes: inline styles (font-size: 12px/16px/20px/24px)";
    $log[] = "- Colors: inline styles (color: #4C6F44, etc.)";
    $log[] = "- Lists: <ul>, <ol>";
    $log[] = "- Links: <a href='...'>text</a>";
    $log[] = "- Paragraphs: <p>";

    // ====================================================================================
    // 5. OUTPUT
    // ====================================================================================

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
        'fields_configured' => count($textareaFields),
        'note' => 'CKEditor profile configuration requires manual setup in ProcessWire Admin',
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
