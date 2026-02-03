<?php
/**
 * ProcessWire Page Templates Creation Script
 *
 * Creates dedicated templates for each page with flexible content sections:
 * home, wir, gemuese, mitmachen, abos, solawi, standorte_depots,
 * aktuelles_page, bioco_werden, kontakt, newsletter, warteliste
 *
 * Run via: https://cms.bioco.ch/create-page-templates/
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
    $log[] = "=== Page Templates Creation ===\n";

    // ====================================================================================
    // 1. ENSURE BASE FIELDS EXIST
    // ====================================================================================

    $log[] = "Step 1: Checking base fields...";

    $baseFields = [
        'title',
        'hero_headline',
        'hero_subtitle',
        'hero_image',
        'section_title',
        'section_text',
        'section_image',
        'section_eyebrow',
        'section_layout',
        'section_theme',
        'image_alt',
        'content_sections',
        'body',
        'seo_title',
        'seo_description',
    ];

    foreach ($baseFields as $fieldName) {
        $field = $fields->get($fieldName);
        if (!$field && $fieldName !== 'title') { // title is always core
            $errors[] = "Missing base field: $fieldName (create it first in api-setup.php)";
        }
    }

    if (count($errors) > 0) {
        throw new \Exception("Missing base fields. Run api-setup.php first.");
    }
    $log[] = "✓ All base fields present";

    // ====================================================================================
    // 2. DEFINE PAGE TEMPLATES
    // ====================================================================================

    $log[] = "\nStep 2: Defining templates...";

    $templates_config = [
        'home' => [
            'label' => 'Homepage',
            'fields' => ['hero_headline', 'hero_subtitle', 'hero_image', 'image_alt', 'content_sections', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Homepage mit Hero-Sektion und Inhaltsbereichen',
        ],
        'wir' => [
            'label' => 'Über uns (Wir)',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Seite über das Unternehmen/Team',
        ],
        'gemuese' => [
            'label' => 'Gemüse',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Gemüse und Produktinformationen',
        ],
        'mitmachen' => [
            'label' => 'Mitmachen',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Partizipationsmöglichkeiten',
        ],
        'abos' => [
            'label' => 'Abos',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Abonnementoptionen',
        ],
        'solawi' => [
            'label' => 'Solidarische Landwirtschaft',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Solawi Informationen',
        ],
        'standorte_depots' => [
            'label' => 'Standorte & Depots',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Depot- und Standortinformationen',
        ],
        'aktuelles_page' => [
            'label' => 'Aktuelles (Seite)',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Aktuelle Nachrichten und Updates',
        ],
        'bioco_werden' => [
            'label' => 'biocò werden',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Informationen zur Mitgliedschaft',
        ],
        'kontakt' => [
            'label' => 'Kontakt',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'section_component', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Kontaktinformationen und Formulare',
        ],
        'newsletter' => [
            'label' => 'Newsletter',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'section_component', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Newsletter Anmeldungsseite',
        ],
        'warteliste' => [
            'label' => 'Warteliste',
            'fields' => ['hero_headline', 'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'section_component', 'seo_title', 'seo_description'],
            'noChildren' => 1,
            'description' => 'Waiting list signup page',
        ],
    ];

    // ====================================================================================
    // 3. CREATE/UPDATE TEMPLATES
    // ====================================================================================

    $log[] = "\nStep 3: Creating templates...";

    foreach ($templates_config as $templateName => $config) {
        $template = $templates->get($templateName);

        if (!$template) {
            // Create new template
            $template = new Template();
            $template->name = $templateName;
            $template->label = $config['label'];
            $template->flags = 0;

            // Create fieldgroup
            $fieldgroup = new Fieldgroup();
            $fieldgroup->name = $templateName;

            // Add title field first (required)
            $titleField = $fields->get('title');
            if ($titleField) {
                $fieldgroup->add($titleField);
            }

            // Add configured fields
            $sort = 1;
            foreach ($config['fields'] as $fieldName) {
                $field = $fields->get($fieldName);
                if ($field) {
                    $fieldgroup->add($field);
                    $sort++;
                } else {
                    $errors[] = "Field not found for $templateName: $fieldName";
                }
            }

            // Save fieldgroup and template
            try {
                $fieldgroups->save($fieldgroup);
                $template->fieldgroup = $fieldgroup;
                $template->noChildren = isset($config['noChildren']) ? $config['noChildren'] : 0;

                if (isset($config['description'])) {
                    $template->description = $config['description'];
                }

                $templates->save($template);
                $log[] = "✓ Created template: $templateName";
            } catch (\Exception $e) {
                $errors[] = "Failed to create template $templateName: " . $e->getMessage();
            }
        } else {
            $log[] = "✓ Template exists: $templateName";
        }
    }

    // ====================================================================================
    // 4. CREATE PAGE STRUCTURE
    // ====================================================================================

    $log[] = "\nStep 4: Creating page structure...";

    $pageStructure = [
        'home' => [
            'name' => 'home',
            'title' => 'Startseite',
            'template' => 'home',
            'parent' => '/',
            'hidden' => false,
        ],
        'wir' => [
            'name' => 'wir',
            'title' => 'Über uns',
            'template' => 'wir',
            'parent' => '/',
            'hidden' => false,
        ],
        'gemuese' => [
            'name' => 'gemuese',
            'title' => 'Gemüse',
            'template' => 'gemuese',
            'parent' => '/',
            'hidden' => false,
        ],
        'mitmachen' => [
            'name' => 'mitmachen',
            'title' => 'Mitmachen',
            'template' => 'mitmachen',
            'parent' => '/',
            'hidden' => false,
        ],
        'abos' => [
            'name' => 'abos',
            'title' => 'Abos',
            'template' => 'abos',
            'parent' => '/',
            'hidden' => false,
        ],
        'solawi' => [
            'name' => 'solawi',
            'title' => 'Solidarische Landwirtschaft',
            'template' => 'solawi',
            'parent' => '/',
            'hidden' => false,
        ],
        'standorte-depots' => [
            'name' => 'standorte-depots',
            'title' => 'Standorte & Depots',
            'template' => 'standorte_depots',
            'parent' => '/',
            'hidden' => false,
        ],
        'aktuelles' => [
            'name' => 'aktuelles',
            'title' => 'Aktuelles',
            'template' => 'aktuelles_page',
            'parent' => '/',
            'hidden' => false,
        ],
        'bioco-werden' => [
            'name' => 'bioco-werden',
            'title' => 'biocò werden',
            'template' => 'bioco_werden',
            'parent' => '/',
            'hidden' => false,
        ],
        'kontakt' => [
            'name' => 'kontakt',
            'title' => 'Kontakt',
            'template' => 'kontakt',
            'parent' => '/',
            'hidden' => false,
        ],
        'newsletter' => [
            'name' => 'newsletter',
            'title' => 'Newsletter',
            'template' => 'newsletter',
            'parent' => '/',
            'hidden' => false,
        ],
        'warteliste' => [
            'name' => 'warteliste',
            'title' => 'Warteliste',
            'template' => 'warteliste',
            'parent' => '/',
            'hidden' => false,
        ],
    ];

    foreach ($pageStructure as $key => $pageConfig) {
        $page = $pages->get("name=" . $pageConfig['name']);

        if (!$page->id) {
            try {
                $page = new Page();
                $page->template = $templates->get($pageConfig['template']);
                $page->parent = $pages->get($pageConfig['parent']);
                $page->name = $pageConfig['name'];
                $page->title = $pageConfig['title'];

                if ($pageConfig['hidden']) {
                    $page->status = Page::statusHidden;
                }

                $pages->save($page);
                $log[] = "✓ Created page: /" . $pageConfig['name'] . "/";
            } catch (\Exception $e) {
                $errors[] = "Failed to create page {$pageConfig['name']}: " . $e->getMessage();
            }
        } else {
            $log[] = "✓ Page exists: /" . $pageConfig['name'] . "/";
        }
    }

    // ====================================================================================
    // 5. SUMMARY
    // ====================================================================================

    $log[] = "\n=== Setup Complete ===";
    $log[] = "Created " . count($templates_config) . " templates";
    $log[] = "Created " . count($pageStructure) . " pages";
    $log[] = "\nNext steps:";
    $log[] = "1. Go to ProcessWire admin Pages";
    $log[] = "2. Edit each page to add content";
    $log[] = "3. Verify content_sections repeater works";
    $log[] = "4. Test API endpoints to confirm data retrieval";

    if (count($errors) > 0) {
        $log[] = "\nWarnings/Errors (" . count($errors) . "):";
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
        'templates_created' => count($templates_config),
        'pages_created' => count($pageStructure),
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
