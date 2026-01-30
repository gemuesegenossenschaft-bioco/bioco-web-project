<?php
/**
 * Page Migration Script
 * 
 * Migrates existing hardcoded pages to CMS-editable pages.
 * 
 * Usage:
 * 1. Create a page with template 'api-setup' (or basic-page) in ProcessWire admin
 * 2. Visit the page to run migration
 * 3. Delete the page after migration completes
 */

namespace ProcessWire;

if (!defined('PROCESSWIRE')) {
    die('This file must be run within ProcessWire');
}

$pages = wire('pages');
$templates = wire('templates');
$fields = wire('fields');

$output = [];
$output[] = "=== Page Migration ===\n";

// Get content parent
$contentParent = $pages->get('/content/');
if (!$contentParent->id) {
    $output[] = "ERROR: /content/ page not found. Run api-setup.php first.";
    echo "<pre>" . implode("\n", $output) . "</pre>";
    exit;
}

$pageContentTemplate = $templates->get('page_content');
if (!$pageContentTemplate) {
    $output[] = "ERROR: page_content template not found. Run api-setup.php first.";
    echo "<pre>" . implode("\n", $output) . "</pre>";
    exit;
}

// Helper to create or get page
function getOrCreatePage($parent, $name, $title, $template) {
    $pages = wire('pages');
    $templates = wire('templates');
    
    $page = $pages->get("parent={$parent}, name={$name}");
    if ($page->id) {
        return ['page' => $page, 'created' => false];
    }
    
    $page = wire(new Page());
    $page->template = $templates->get($template);
    $page->parent = $parent;
    $page->name = $name;
    $page->title = $title;
    $page->save();
    
    return ['page' => $page, 'created' => true];
}

// Helper to add section
function addSection($page, $sectionId, $title, $text, $layout = 'rich_text', $theme = 'default') {
    if (!$page->hasField('content_sections')) {
        return false;
    }
    
    $section = $page->content_sections->getNew();
    $section->section_id = $sectionId;
    $section->section_title = $title;
    $section->section_text = $text;
    if ($section->hasField('section_layout')) {
        $section->section_layout = $layout;
    }
    if ($section->hasField('section_theme')) {
        $section->section_theme = $theme;
    }
    $section->save();
    $page->content_sections->add($section);
    $page->save();
    
    return true;
}

$output[] = "\n--- Creating Pages ---";

// 1. Mitmachen
$result = getOrCreatePage($contentParent, 'mitmachen', 'Mitmachen', 'page_content');
$mitmachen = $result['page'];
if ($result['created']) {
    $output[] = "Created /content/mitmachen/";
    
    addSection($mitmachen, 'mitarbeit', 'Was es braucht, damit wir gesundes Gemüse haben', 
        '<p>Jedes Mitglied bringt sich ein und unterstützt die Genossenschaft aktiv. Die Mitarbeit ist ein wichtiger Teil unserer solidarischen Landwirtschaft.</p>',
        'rich_text');
    
    addSection($mitmachen, 'gruppen', 'Gruppen & Gemeinschaft', 
        '<p>Bei biocò gibt es verschiedene Arbeitsgruppen und Gemeinschaftsaktivitäten, die das Herzstück unserer Genossenschaft bilden.</p>',
        'rich_text');
    
    addSection($mitmachen, 'familien', 'Familien & Kinder auf dem Geisshof', 
        '<p>Familien und Kinder sind sehr regelmässige Helfer auf dem Geisshof. Die Einbindung von Kindern in den Prozess des Gemüseanbaus ist ein zentraler Bestandteil der biocò-Kultur.</p>',
        'split_media_text');
    
    $output[] = "  Added 3 sections to mitmachen";
} else {
    $output[] = "Page /content/mitmachen/ already exists";
}

// 2. Gemüse
$result = getOrCreatePage($contentParent, 'gemuese', 'Gemüse', 'page_content');
$gemuese = $result['page'];
if ($result['created']) {
    $output[] = "Created /content/gemuese/";
    
    addSection($gemuese, 'intro', 'Unser Gemüse', 
        '<p>Auf dem Geisshof in Gebenstorf bauen wir nach biologisch-dynamischen Prinzipien (Demeter) eine grosse Vielfalt an saisonalem Gemüse an. Jede Woche gibt es eine frische Auswahl, die unsere Mitglieder in den Depots abholen.</p>',
        'rich_text');
    
    addSection($gemuese, 'saisonkalender', 'Saisonkalender', 
        '<p>Was gerade wächst und geerntet wird, variiert mit den Jahreszeiten.</p>',
        'rich_text');
    
    $output[] = "  Added 2 sections to gemuese";
} else {
    $output[] = "Page /content/gemuese/ already exists";
}

// 3. Solawi
$result = getOrCreatePage($contentParent, 'solawi', 'Solawi', 'page_content');
$solawi = $result['page'];
if ($result['created']) {
    $output[] = "Created /content/solawi/";
    
    addSection($solawi, 'intro', 'Solidarische Landwirtschaft', 
        '<p>Solidarische Landwirtschaft (Solawi) ist ein alternatives Wirtschaftsmodell, bei dem Produzent*innen und Konsument*innen eine direkte Partnerschaft eingehen. Bei biocò teilen wir nicht nur die Ernte, sondern auch die Verantwortung, das Risiko und die Freude an der gemeinsamen Arbeit.</p>',
        'rich_text');
    
    addSection($solawi, 'prinzipien', 'Die Prinzipien der Solawi', 
        '<ul><li><strong>Gemeinsame Verantwortung:</strong> Alle tragen zum Gelingen bei</li><li><strong>Faire Preise:</strong> Bauern erhalten einen gerechten Lohn</li><li><strong>Saisonal & regional:</strong> Frisches Gemüse aus der Region</li><li><strong>Transparenz:</strong> Jeder weiss, woher das Essen kommt</li></ul>',
        'rich_text');
    
    addSection($solawi, 'praxis', 'So funktioniert unsere Solawi in der Praxis', 
        '<p>Mitglieder bezahlen zu Jahresbeginn ihren Anteil und erhalten wöchentlich frisches Gemüse aus der Region Baden-Brugg.</p>',
        'split_text_media');
    
    $output[] = "  Added 3 sections to solawi";
} else {
    $output[] = "Page /content/solawi/ already exists";
}

// 4. Abos
$result = getOrCreatePage($contentParent, 'abos', 'Abos', 'page_content');
$abos = $result['page'];
if ($result['created']) {
    $output[] = "Created /content/abos/";
    
    addSection($abos, 'intro', 'Dein wöchentliches Gemüseabo', 
        '<p>Wöchentlich frisches Demeter-Gemüse direkt vom Geisshof in deinen Gemüsekorb. Mit deinem Gemüseabo unterstützt du unsere solidarische Landwirtschaft und wirst Teil unserer Gemüsegenossenschaft.</p>',
        'rich_text');
    
    addSection($abos, 'pricing', 'Gemüse-Abos', 
        '<p>Das Gemüseabo läuft vom 1. Januar bis zum 31. Dezember. Ohne Kündigung verlängert sich das Gemüseabo jeweils um ein Kalenderjahr.</p>',
        'rich_text');
    
    $output[] = "  Added 2 sections to abos";
} else {
    $output[] = "Page /content/abos/ already exists";
}

// 5. Aktuelles
$result = getOrCreatePage($contentParent, 'aktuelles', 'Aktuelles', 'page_content');
$aktuelles = $result['page'];
if ($result['created']) {
    $output[] = "Created /content/aktuelles/";
    
    addSection($aktuelles, 'intro', 'Aktuelles', 
        '<p>Neuigkeiten, Veranstaltungen und Updates aus der biocò Gemüsegenossenschaft.</p>',
        'rich_text');
    
    $output[] = "  Added 1 section to aktuelles";
} else {
    $output[] = "Page /content/aktuelles/ already exists";
}

// 6. Kontakt
$result = getOrCreatePage($contentParent, 'kontakt', 'Kontakt', 'page_content');
$kontakt = $result['page'];
if ($result['created']) {
    $output[] = "Created /content/kontakt/";
    
    addSection($kontakt, 'kontakt-form', 'Kontakt', 
        '<p>Hast du Fragen? Nimm Kontakt mit uns auf.</p>',
        'component', 'default');
    
    // Add component field
    if ($kontakt->content_sections->count() > 0) {
        $section = $kontakt->content_sections->last();
        if ($section->hasField('section_component')) {
            $section->section_component = 'contact_form';
            $section->save();
        }
    }
    
    $output[] = "  Added 1 section to kontakt (with contact form component)";
} else {
    $output[] = "Page /content/kontakt/ already exists";
}

// 7. Standorte & Depots
$result = getOrCreatePage($contentParent, 'standorte-depots', 'Standorte & Depots', 'page_content');
$standorte = $result['page'];
if ($result['created']) {
    $output[] = "Created /content/standorte-depots/";
    
    addSection($standorte, 'intro', 'Standorte & Depots', 
        '<p>Unser Gemüse kannst du an verschiedenen Depots in der Region Baden-Brugg abholen.</p>',
        'rich_text');
    
    addSection($standorte, 'map', 'Karte', 
        '<p>Hier findest du unsere Depots:</p>',
        'component', 'default');
    
    // Add component field
    if ($standorte->content_sections->count() > 0) {
        $section = $standorte->content_sections->last();
        if ($section->hasField('section_component')) {
            $section->section_component = 'depot_map';
            $section->save();
        }
    }
    
    $output[] = "  Added 2 sections to standorte-depots (with map component)";
} else {
    $output[] = "Page /content/standorte-depots/ already exists";
}

$output[] = "\n=== Migration Complete ===";
$output[] = "\nNext steps:";
$output[] = "1. Edit each page in ProcessWire to add more content and images";
$output[] = "2. Visit bioco.ch/{pagename} to verify pages render correctly";
$output[] = "3. Update hardcoded pages to use CMS content via getPageSections()";
$output[] = "4. Delete this migration page when done";

echo "<pre>" . implode("\n", $output) . "</pre>";
