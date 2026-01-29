<?php
/**
 * CMS API Setup Script
 * 
 * Creates all necessary templates and fields for the headless CMS API.
 * Run once via ProcessWire admin or directly.
 * 
 * Usage:
 * 1. Create a page with template 'api-setup' in ProcessWire admin
 * 2. Visit the page to run setup
 * 3. Delete the page after setup is complete
 * 
 * Or run from command line:
 * php site/templates/api-setup.php
 */

namespace ProcessWire;

// Bootstrap ProcessWire if running from CLI
if (!defined('PROCESSWIRE')) {
    require_once __DIR__ . '/../index.php';
}

$fields = wire('fields');
$templates = wire('templates');
$fieldgroups = wire('fieldgroups');
$modules = wire('modules');
$pages = wire('pages');

$output = [];
$output[] = "=== CMS API Setup ===\n";

// ============================================================================
// Helper Functions
// ============================================================================

function createField($name, $type, $label, $options = []) {
    $fields = wire('fields');
    $modules = wire('modules');
    
    $field = $fields->get($name);
    if ($field) {
        return "Field '$name' already exists";
    }
    
    $field = wire(new Field());
    $field->type = $modules->get($type);
    $field->name = $name;
    $field->label = $label;
    
    foreach ($options as $key => $value) {
        $field->set($key, $value);
    }
    
    $field->save();
    return "Created field '$name'";
}

function createTemplate($name, $label, $fieldNames = [], $options = []) {
    $templates = wire('templates');
    $fieldgroups = wire('fieldgroups');
    $fields = wire('fields');
    
    $template = $templates->get($name);
    if ($template) {
        return "Template '$name' already exists";
    }
    
    // Create fieldgroup
    $fg = wire(new Fieldgroup());
    $fg->name = $name;
    $fg->save();
    
    // Add title field (required)
    $fg->add($fields->get('title'));
    
    // Add specified fields
    foreach ($fieldNames as $fieldName) {
        $field = $fields->get($fieldName);
        if ($field) {
            $fg->add($field);
        }
    }
    
    $fg->save();
    
    // Create template
    $template = wire(new Template());
    $template->name = $name;
    $template->label = $label;
    $template->fieldgroup = $fg;
    
    foreach ($options as $key => $value) {
        $template->set($key, $value);
    }
    
    $template->save();
    return "Created template '$name'";
}

// ============================================================================
// Create Fields
// ============================================================================

$output[] = "\n--- Creating Fields ---";

// Hero fields
$output[] = createField('hero_headline', 'FieldtypeText', 'Hero Headline', [
    'maxlength' => 255,
    'description' => 'Main headline for hero section',
]);

$output[] = createField('hero_subtitle', 'FieldtypeText', 'Hero Subtitle', [
    'maxlength' => 500,
    'description' => 'Subtitle text below the headline',
]);

// Section fields
$output[] = createField('section_id', 'FieldtypeText', 'Section ID', [
    'maxlength' => 100,
    'description' => 'Unique identifier for CSS/JS targeting (e.g., willkommen, gemeinsam)',
]);

$output[] = createField('section_title', 'FieldtypeText', 'Section Title', [
    'maxlength' => 255,
    'description' => 'Section heading',
]);

$output[] = createField('section_text', 'FieldtypeTextarea', 'Section Text', [
    'inputfieldClass' => 'InputfieldCKEditor',
    'contentType' => 1, // HTML
    'description' => 'Section body content (supports HTML)',
]);

$output[] = createField('section_image', 'FieldtypeImage', 'Section Image', [
    'maxFiles' => 1,
    'extensions' => 'jpg jpeg png webp',
    'description' => 'Image for this section',
]);

// Button fields
$output[] = createField('button_text', 'FieldtypeText', 'Button Text', [
    'maxlength' => 100,
    'description' => 'Primary CTA button label',
]);

$output[] = createField('button_href', 'FieldtypeText', 'Button URL', [
    'maxlength' => 255,
    'description' => 'Primary CTA button link (e.g., /kontakt)',
]);

$output[] = createField('button_variant', 'FieldtypeText', 'Button Variant', [
    'maxlength' => 50,
    'description' => 'Button style: primary or secondary',
]);

$output[] = createField('button2_text', 'FieldtypeText', 'Secondary Button Text', [
    'maxlength' => 100,
    'description' => 'Secondary CTA button label',
]);

$output[] = createField('button2_href', 'FieldtypeText', 'Secondary Button URL', [
    'maxlength' => 255,
    'description' => 'Secondary CTA button link',
]);

$output[] = createField('button2_variant', 'FieldtypeText', 'Secondary Button Variant', [
    'maxlength' => 50,
    'description' => 'Secondary button style: primary or secondary',
]);

// Card fields
$output[] = createField('card_text', 'FieldtypeTextarea', 'Card Text', [
    'inputfieldClass' => 'InputfieldCKEditor',
    'contentType' => 1, // HTML
    'description' => 'Card description content',
]);

$output[] = createField('card_image', 'FieldtypeImage', 'Card Image', [
    'maxFiles' => 1,
    'extensions' => 'jpg jpeg png webp',
    'description' => 'Image for card display',
]);

// Image alt field
$output[] = createField('image_alt', 'FieldtypeText', 'Image Alt Text', [
    'maxlength' => 255,
    'description' => 'Alternative text for accessibility',
]);

// ============================================================================
// Create Repeater Field for Content Sections
// ============================================================================

$output[] = "\n--- Creating Repeater Field ---";

$repeaterName = 'content_sections';
if (!$fields->get($repeaterName)) {
    // Create the repeater field
    $repeater = wire(new Field());
    $repeater->type = $modules->get('FieldtypeRepeater');
    $repeater->name = $repeaterName;
    $repeater->label = 'Content Sections';
    $repeater->description = 'Repeatable content sections for the page';
    $repeater->save();
    
    // Create the repeater template
    $repeaterTemplateName = 'repeater_' . $repeaterName;
    if (!$templates->get($repeaterTemplateName)) {
        $fg = wire(new Fieldgroup());
        $fg->name = $repeaterTemplateName;
        $fg->save();
        
        // Add fields to repeater
        $repeaterFields = ['section_id', 'section_title', 'section_text', 'section_image', 'image_alt', 
                          'button_text', 'button_href', 'button_variant', 'button2_text', 'button2_href', 'button2_variant'];
        foreach ($repeaterFields as $fieldName) {
            $field = $fields->get($fieldName);
            if ($field) {
                $fg->add($field);
            }
        }
        $fg->save();
        
        $repeaterTemplate = wire(new Template());
        $repeaterTemplate->name = $repeaterTemplateName;
        $repeaterTemplate->fieldgroup = $fg;
        $repeaterTemplate->flags = Template::flagSystem;
        $repeaterTemplate->noChildren = 1;
        $repeaterTemplate->noParents = 1;
        $repeaterTemplate->save();
        
        // Configure repeater field
        $repeater->template_id = $repeaterTemplate->id;
        $repeater->parent_id = $pages->get('name=for-field-' . $repeater->id)->id ?: 0;
        $repeater->save();
        
        $output[] = "Created repeater field '$repeaterName'";
    }
} else {
    $output[] = "Repeater field '$repeaterName' already exists";
}

// ============================================================================
// Create Templates
// ============================================================================

$output[] = "\n--- Creating Templates ---";

// API template
$output[] = createTemplate('api', 'API Endpoint', [], [
    'noChildren' => 1,
    'urlSegments' => 1,
    'slashUrls' => 1,
]);

// Content parent template
$output[] = createTemplate('content_parent', 'Content Parent', [], [
    'childTemplates' => [], // Will be set after other templates exist
]);

// Homepage content template
$output[] = createTemplate('homepage_content', 'Homepage Content', [
    'hero_headline', 'hero_subtitle', 'hero_image', 'image_alt', 'content_sections'
], [
    'noChildren' => 0,
]);

// Page content template
$output[] = createTemplate('page_content', 'Page Content', [
    'section_title', 'section_text', 'section_image', 'image_alt', 'content_sections', 'body'
], [
    'noChildren' => 1,
]);

// Group card template
$output[] = createTemplate('group_card', 'Group Card', [
    'card_text', 'card_image', 'image_alt'
], [
    'noChildren' => 1,
]);

// News item template
$output[] = createTemplate('news_item', 'News Item', [
    'summary', 'body', 'hero_image', 'card_image', 'image_alt'
], [
    'noChildren' => 1,
]);

// ============================================================================
// Create Page Structure
// ============================================================================

$output[] = "\n--- Creating Page Structure ---";

// Create /api page
$apiPage = $pages->get('/api/');
if (!$apiPage->id) {
    $apiTemplate = $templates->get('api');
    if ($apiTemplate) {
        $apiPage = wire(new Page());
        $apiPage->template = $apiTemplate;
        $apiPage->parent = $pages->get('/');
        $apiPage->name = 'api';
        $apiPage->title = 'API';
        $apiPage->save();
        $output[] = "Created page /api/";
    }
} else {
    $output[] = "Page /api/ already exists";
}

// Create /content parent page
$contentParent = $pages->get('/content/');
if (!$contentParent->id) {
    $contentTemplate = $templates->get('content_parent');
    if ($contentTemplate) {
        $contentParent = wire(new Page());
        $contentParent->template = $contentTemplate;
        $contentParent->parent = $pages->get('/');
        $contentParent->name = 'content';
        $contentParent->title = 'Content';
        $contentParent->addStatus(Page::statusHidden);
        $contentParent->save();
        $output[] = "Created page /content/";
    }
} else {
    $output[] = "Page /content/ already exists";
}

// Create /content/homepage page
$homepagePage = $pages->get('/content/homepage/');
if (!$homepagePage->id && $contentParent->id) {
    $homepageTemplate = $templates->get('homepage_content');
    if ($homepageTemplate) {
        $homepagePage = wire(new Page());
        $homepagePage->template = $homepageTemplate;
        $homepagePage->parent = $contentParent;
        $homepagePage->name = 'homepage';
        $homepagePage->title = 'Homepage Content';
        $homepagePage->save();
        $output[] = "Created page /content/homepage/";
    }
} else {
    $output[] = "Page /content/homepage/ already exists";
}

// Create /content/gruppen parent
$gruppenParent = $pages->get('/content/gruppen/');
if (!$gruppenParent->id && $contentParent->id) {
    $gruppenTemplate = $templates->get('content_parent');
    if ($gruppenTemplate) {
        $gruppenParent = wire(new Page());
        $gruppenParent->template = $gruppenTemplate;
        $gruppenParent->parent = $contentParent;
        $gruppenParent->name = 'gruppen';
        $gruppenParent->title = 'Gruppen';
        $gruppenParent->save();
        $output[] = "Created page /content/gruppen/";
    }
} else {
    $output[] = "Page /content/gruppen/ already exists";
}

// ============================================================================
// Output Results
// ============================================================================

$output[] = "\n=== Setup Complete ===";
$output[] = "\nNext steps:";
$output[] = "1. Go to Setup → Templates → api → Files";
$output[] = "   - Check 'Disable automatic prepend of file: _init.php'";
$output[] = "   - Check 'Disable automatic append of file: _main.php'";
$output[] = "2. Go to Setup → Templates → api → URLs";
$output[] = "   - Check 'Allow URL Segments'";
$output[] = "   - Set maximum segments to 4";
$output[] = "3. Add API key to site/config.php:";
$output[] = "   \$config->apiKey = 'your_secure_key_here';";
$output[] = "4. Test the API: curl https://cms.bioco.ch/api/health";
$output[] = "5. Delete this setup page when done";

// Output as JSON for API calls, HTML for browser
if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'log' => $output]);
} else {
    echo "<pre>" . implode("\n", $output) . "</pre>";
}
