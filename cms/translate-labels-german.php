<?php
/**
 * ProcessWire German Labels Translation Script
 *
 * Translates all admin field labels and descriptions to German.
 *
 * Run via: https://cms.bioco.ch/translate-labels-german/
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
    $log[] = "=== German Labels Translation ===\n";

    // ====================================================================================
    // 1. FIELD LABEL TRANSLATIONS
    // ====================================================================================

    $log[] = "Step 1: Translating field labels...";

    $fieldTranslations = [
        // Core/Basic fields
        'title' => [
            'label' => 'Titel',
            'description' => 'Seitentitel',
        ],
        'body' => [
            'label' => 'Inhalt',
            'description' => 'Hauptinhaltsbereich',
        ],

        // Hero fields
        'hero_image' => [
            'label' => 'Hero-Bild',
            'description' => 'Bild für Hero-Sektion',
        ],
        'hero_headline' => [
            'label' => 'Hero Überschrift',
            'description' => 'Haupttitel für Hero-Sektion',
        ],
        'hero_subtitle' => [
            'label' => 'Hero Untertitel',
            'description' => 'Untertitel unter der Hero-Überschrift',
        ],

        // Section fields
        'section_id' => [
            'label' => 'Bereichs-ID',
            'description' => 'Eindeutige Kennung für CSS/JS-Targeting',
        ],
        'section_title' => [
            'label' => 'Bereichstitel',
            'description' => 'Überschrift für diesen Bereich',
        ],
        'section_text' => [
            'label' => 'Bereichstext',
            'description' => 'Textinhalt (HTML mit Editor)',
        ],
        'section_image' => [
            'label' => 'Bereichsbild',
            'description' => 'Bild für diesen Bereich',
        ],
        'section_images' => [
            'label' => 'Bereichsbilder',
            'description' => 'Mehrere Bilder für Galerien oder Raster',
        ],
        'section_eyebrow' => [
            'label' => 'Bereichs-Etikett',
            'description' => 'Kleine Bezeichnung über dem Titel',
        ],
        'section_layout' => [
            'label' => 'Layout',
            'description' => 'Layouttyp für diesen Bereich',
        ],
        'section_theme' => [
            'label' => 'Thema',
            'description' => 'Farbe oder Stil für diesen Bereich',
        ],
        'section_video_url' => [
            'label' => 'Video-URL',
            'description' => 'YouTube, Vimeo oder MP4 Video-URL',
        ],
        'section_video_title' => [
            'label' => 'Video-Titel',
            'description' => 'Optionaler Titel oder Beschreibung',
        ],
        'section_component' => [
            'label' => 'Komponenten-Schlüssel',
            'description' => 'z.B. contact_form, membership_form',
        ],
        'section_image_overlay' => [
            'label' => 'Bild-Overlay',
            'description' => 'Überlagern Sie das Bild mit einer Farbtönung',
        ],
        'section_bg_color' => [
            'label' => 'Hintergrundfarbe',
            'description' => 'Hintergrundfarbe für diesen Bereich',
        ],

        // Button fields
        'button_text' => [
            'label' => 'Button-Text',
            'description' => 'Hauptbutton Beschriftung',
        ],
        'button_href' => [
            'label' => 'Button-Link',
            'description' => 'Ziel-URL (z.B. /kontakt)',
        ],
        'button_variant' => [
            'label' => 'Button-Stil',
            'description' => 'Grün (primär) oder Weiss (sekundär)',
        ],
        'button2_text' => [
            'label' => 'Sekundärer Button-Text',
            'description' => 'Zweiter Button Beschriftung',
        ],
        'button2_href' => [
            'label' => 'Sekundärer Button-Link',
            'description' => 'Zweiter Button Ziel-URL',
        ],
        'button2_variant' => [
            'label' => 'Sekundärer Button-Stil',
            'description' => 'Grün (primär) oder Weiss (sekundär)',
        ],

        // Card fields
        'card_text' => [
            'label' => 'Kartentexte',
            'description' => 'Kartenbeschreibung',
        ],
        'card_image' => [
            'label' => 'Kartenbild',
            'description' => 'Bild für die Kartenansicht',
        ],

        // Image alt text
        'image_alt' => [
            'label' => 'Bild Alt-Text',
            'description' => 'Alternativtext für Barrierefreiheit',
        ],
        'image' => [
            'label' => 'Bild',
            'description' => 'Bild für Abschnitte, Karten und Events',
        ],

        // Content sections repeater
        'content_sections' => [
            'label' => 'Inhaltsbereiche',
            'description' => 'Wiederholbare Inhaltsbereiche für die Seite',
        ],

        // Navigation
        'include_in_nav' => [
            'label' => 'In Navigation anzeigen',
            'description' => 'Diese Seite in der Navigation anzeigen',
        ],

        // SEO fields
        'seo_title' => [
            'label' => 'SEO-Titel',
            'description' => 'Benutzerdefinierter Seitentitel für Suchmaschinen',
        ],
        'seo_description' => [
            'label' => 'SEO-Beschreibung',
            'description' => 'Meta-Beschreibung für Suchmaschinen',
        ],
        'og_image' => [
            'label' => 'Open Graph Bild',
            'description' => 'Bild für soziale Medien',
        ],

        // Event fields
        'event_start' => [
            'label' => 'Event-Startzeit',
            'description' => 'Wann beginnt das Event',
        ],
        'event_end' => [
            'label' => 'Event-Endzeit',
            'description' => 'Wann endet das Event',
        ],
        'event_location' => [
            'label' => 'Veranstaltungsort',
            'description' => 'z.B. Geisshof, Geisslistrasse, 5412 Gebenstorf',
        ],
        'event_summary' => [
            'label' => 'Kurzbeschreibung',
            'description' => 'Kurze Zusammenfassung für Kartenansicht',
        ],
        'event_card_image' => [
            'label' => 'Event-Kartenbild',
            'description' => 'Bild für die Event-Kartenansicht',
        ],
        'event_status' => [
            'label' => 'Event-Status',
            'description' => 'Kommend oder vorbei',
        ],
        'event_media' => [
            'label' => 'Event-Medien',
            'description' => 'Fotos und Videos vom Event',
        ],
        'event_signup_enabled' => [
            'label' => 'Anmeldung aktivieren',
            'description' => 'Anmeldungsformular anzeigen',
        ],
        'event_signup_notes' => [
            'label' => 'Anmeldungshinweise',
            'description' => 'Zusätzliche Informationen für die Anmeldung',
        ],
    ];

    foreach ($fieldTranslations as $fieldName => $translations) {
        $field = $fields->get($fieldName);

        if (!$field) {
            $log[] = "  Field not found (skipping): $fieldName";
            continue;
        }

        // Update label
        if (isset($translations['label'])) {
            $field->label = $translations['label'];
        }

        // Update description
        if (isset($translations['description'])) {
            $field->description = $translations['description'];
        }

        try {
            $fields->save($field);
            $log[] = "  ✓ Translated: $fieldName";
        } catch (\Exception $e) {
            $errors[] = "Failed to translate $fieldName: " . $e->getMessage();
        }
    }

    // ====================================================================================
    // 2. LAYOUT OPTIONS TRANSLATION
    // ====================================================================================

    $log[] = "\nStep 2: Translating layout options...";

    $layoutField = $fields->get('section_layout');
    if ($layoutField) {
        $layoutField->options = "split_media_text|Geteiltes Layout: Bild links, Text rechts\n"
                              . "split_text_media|Geteiltes Layout: Text links, Bild rechts\n"
                              . "full_width_banner|Volle Breite Banner\n"
                              . "media_grid|Bild-Raster\n"
                              . "video_embed|Video einbetten\n"
                              . "rich_text|Fliesstext\n"
                              . "component|Komponenten-Block";

        try {
            $fields->save($layoutField);
            $log[] = "✓ Layout options translated";
        } catch (\Exception $e) {
            $errors[] = "Failed to translate layout options: " . $e->getMessage();
        }
    } else {
        $log[] = "Layout field not found (skipping)";
    }

    // ====================================================================================
    // 3. THEME OPTIONS TRANSLATION
    // ====================================================================================

    $log[] = "\nStep 3: Translating theme options...";

    $themeField = $fields->get('section_theme');
    if ($themeField) {
        $themeField->options = "default|Standardwert\n"
                             . "muted|Gedimmt\n"
                             . "accent|Akzent\n"
                             . "dark|Dunkel";

        try {
            $fields->save($themeField);
            $log[] = "✓ Theme options translated";
        } catch (\Exception $e) {
            $errors[] = "Failed to translate theme options: " . $e->getMessage();
        }
    } else {
        $log[] = "Theme field not found (skipping)";
    }

    // ====================================================================================
    // 4. TEMPLATE LABEL TRANSLATIONS
    // ====================================================================================

    $log[] = "\nStep 4: Translating template labels...";

    $templateTranslations = [
        'home' => 'Startseite',
        'wir' => 'Über uns',
        'gemuese' => 'Gemüse',
        'mitmachen' => 'Mitmachen',
        'abos' => 'Abos',
        'solawi' => 'Solidarische Landwirtschaft',
        'standorte_depots' => 'Standorte & Depots',
        'aktuelles_page' => 'Aktuelles',
        'bioco_werden' => 'biocò werden',
        'kontakt' => 'Kontakt',
        'newsletter' => 'Newsletter',
        'warteliste' => 'Warteliste',
        'event' => 'Event',
        'page_content' => 'Seiteninhalt',
        'group_card' => 'Gruppenkarte',
        'news_item' => 'Nachrichtenelement',
    ];

    foreach ($templateTranslations as $templateName => $label) {
        $template = $templates->get($templateName);

        if (!$template) {
            $log[] = "  Template not found (skipping): $templateName";
            continue;
        }

        $template->label = $label;

        try {
            $templates->save($template);
            $log[] = "  ✓ Translated: $templateName → $label";
        } catch (\Exception $e) {
            $errors[] = "Failed to translate template $templateName: " . $e->getMessage();
        }
    }

    // ====================================================================================
    // 5. SUMMARY
    // ====================================================================================

    $log[] = "\n=== Translation Complete ===";
    $log[] = "\n✓ Field labels translated to German";
    $log[] = "✓ Field descriptions translated to German";
    $log[] = "✓ Layout options translated";
    $log[] = "✓ Theme options translated";
    $log[] = "✓ Template labels translated";

    $log[] = "\nAll admin interface now displays in German:";
    $log[] = "- Field names and descriptions";
    $log[] = "- Option dropdowns";
    $log[] = "- Template names";
    $log[] = "- Page titles";

    if (count($errors) > 0) {
        $log[] = "\n=== Errors (" . count($errors) . ") ===";
        $log = array_merge($log, $errors);
    }

    // ====================================================================================
    // 6. OUTPUT
    // ====================================================================================

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
        'fields_translated' => count($fieldTranslations),
        'templates_translated' => count($templateTranslations),
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
