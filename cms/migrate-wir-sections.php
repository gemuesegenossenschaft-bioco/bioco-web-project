<?php
/**
 * One-time migration: restore /wir page sections after visual editor migration.
 *
 * - Deletes junk test sections
 * - Creates missing sections with original fallback content
 * - Updates all sections to proper component types
 * - Reorders sections to match original page layout
 *
 * Idempotent: safe to run multiple times.
 *
 * Usage: upload to /public_html/cms/, run via curl, delete after.
 *   curl -s "https://cms.bioco.ch/migrate-wir-sections.php"
 */

namespace ProcessWire;

// Bootstrap ProcessWire
require_once __DIR__ . '/index.php';

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

// ---------------------------------------------------------------------------
// Ensure required fields exist on repeater template
// ---------------------------------------------------------------------------

function ensureFieldOnRepeater(string $fieldName, string $fieldType, array $settings, array &$log): void
{
    $fields = wire('fields');
    $field = $fields->get($fieldName);

    if (!$field) {
        $field = new Field();
        $field->type = wire('modules')->get($fieldType);
        $field->name = $fieldName;
        foreach ($settings as $k => $v) $field->set($k, $v);
        $fields->save($field);
        $log[] = "FIELD CREATED: {$fieldName}";
    }

    foreach (wire('templates') as $template) {
        if (strpos((string)$template->name, 'repeater_content_sections') !== 0) continue;
        $fg = $template->fieldgroup;
        if (!$fg || $fg->hasField($field)) continue;
        $fg->add($field);
        $fg->save();
        $log[] = "FIELD ADDED to {$template->name}: {$fieldName}";
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function findSectionById(Page $page, string $sectionId): ?Page
{
    foreach ($page->content_sections as $section) {
        if ((string) $section->get('section_id') === $sectionId) {
            return $section;
        }
    }
    return null;
}

function setField(Page $section, string $field, $value): void
{
    // Use set() directly; skip hasField check which fails on repeater sub-items
    // when bootstrapping outside normal PW request cycle
    $section->set($field, $value);
}

function createSection(Page $page, array $data, array &$log): ?Page
{
    $existing = findSectionById($page, $data['section_id']);
    if ($existing && $existing->id) {
        // Update existing section with provided data
        updateSection($existing, $data, $log);
        return $existing;
    }

    $page->of(false);
    $section = $page->content_sections->getNew();

    foreach ($data as $field => $value) {
        setField($section, $field, $value);
    }

    $section->save();
    $page->content_sections->add($section);
    $page->save('content_sections');
    $log[] = "CREATED: {$data['section_id']}";
    return $section;
}

function updateSection(Page $section, array $fields, array &$log): void
{
    $section->of(false);
    $changed = false;

    foreach ($fields as $field => $value) {
        $current = (string) $section->get($field);
        if ($current !== (string) $value) {
            setField($section, $field, $value);
            $changed = true;
        }
    }

    if ($changed) {
        $section->save();
        $id = $section->get('section_id') ?: "pwId:{$section->id}";
        $log[] = "UPDATED: {$id}";
    }
}

function encodeSectionConfig(array $config): string
{
    return json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// ---------------------------------------------------------------------------
// Step 0: Ensure required fields exist
// ---------------------------------------------------------------------------

ensureFieldOnRepeater('section_config', 'FieldtypeTextarea', [
    'label' => 'Komponenten-Konfiguration (JSON)',
    'rows' => 8,
    'collapsed' => Inputfield::collapsedYes,
], $log);

ensureFieldOnRepeater('section_component', 'FieldtypeText', [
    'label' => 'Komponenten-Schlüssel',
    'collapsed' => Inputfield::collapsedYes,
], $log);

ensureFieldOnRepeater('section_eyebrow', 'FieldtypeText', [
    'label' => 'Eyebrow',
    'collapsed' => Inputfield::collapsedYes,
], $log);

// ---------------------------------------------------------------------------
// Find /wir page
// ---------------------------------------------------------------------------

$wirPage = wire('pages')->get('name=wir, template=wir');
if (!$wirPage || !$wirPage->id) {
    $wirPage = wire('pages')->get('/wir/');
}
if (!$wirPage || !$wirPage->id) {
    $wirPage = wire('pages')->get('/content/wir/');
}
if (!$wirPage || !$wirPage->id) {
    echo json_encode(['error' => '/wir page not found'], JSON_PRETTY_PRINT);
    exit;
}

$log[] = "Found /wir page: id={$wirPage->id}, template={$wirPage->template}";

if (!$wirPage->hasField('content_sections')) {
    echo json_encode(['error' => '/wir page has no content_sections field'], JSON_PRETTY_PRINT);
    exit;
}

// ---------------------------------------------------------------------------
// Step 1: Delete junk sections
// ---------------------------------------------------------------------------

$junkIds = ['section-cd45390e', 'section-f19e105b'];
foreach ($junkIds as $junkId) {
    $junk = findSectionById($wirPage, $junkId);
    if ($junk && $junk->id) {
        $wirPage->of(false);
        $wirPage->content_sections->remove($junk);
        $wirPage->save('content_sections');
        wire('pages')->delete($junk);
        $log[] = "DELETED: {$junkId} (pwId:{$junk->id})";
    } else {
        $log[] = "SKIP DELETE: {$junkId} not found";
    }
}

// ---------------------------------------------------------------------------
// Step 2: Create missing sections with original fallback content
// ---------------------------------------------------------------------------

$newSections = [
    [
        'section_id' => 'intro',
        'section_title' => 'bioco: Die Gemüsegenossenschaft',
        'section_text' => '<p>Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof in Gebenstorf. Lerne unser Team, unsere Geschichte und die Werte kennen, die unsere solidarische Landwirtschaft prägen.</p>',

        'section_component' => 'page_intro',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'lg',
            'textWidth' => 'normal',
            'align' => 'left',
        ]),
        'section_theme' => 'default',
    ],
    [
        'section_id' => 'gotti',
        'section_title' => 'Gotti-System',
        'section_text' => '<h2>Gotti-System</h2><p>Neumitglieder werden von einem "Gotti" oder "Götti" (Paten) begleitet. Dieses System hilft neuen Mitgliedern, sich in der Genossenschaft zurechtzufinden.</p>',

        'section_component' => 'media_text',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'xl',
            'mediaSide' => 'left',
            'mediaWidth' => '50',
            'mediaRatio' => '4:3',
            'mediaFit' => 'cover',
            'verticalAlign' => 'center',
            'gap' => 'lg',
            'rounded' => 'lg',
        ]),
        'section_theme' => 'default',
    ],
    [
        'section_id' => 'mitmachen_cta',
        'section_title' => 'Mitmachen?',
        'section_text' => '<p>Werde Teil unserer Gemeinschaft und unterstütze die solidarische Landwirtschaft.</p>',

        'section_component' => 'cta_band',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'lg',
            'align' => 'left',
            'theme' => 'soft',
            'rounded' => 'xl',
        ]),
        'section_theme' => 'default',
        'button_text' => 'Jetzt Mitglied werden',
        'button_href' => '/mitmachen',
        'button_variant' => 'primary',
    ],
    [
        'section_id' => 'kennenlernen_cta',
        'section_title' => 'Möchtest du uns kennenlernen?',
        'section_text' => '<p>Es können viele Fragen auftauchen. Du hast die Möglichkeit, den Hof und uns an den Schnuppertagen kennenzulernen oder dich via Kontaktformular bei uns zu melden.</p>',

        'section_component' => 'cta_band',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'lg',
            'align' => 'left',
            'theme' => 'soft',
            'rounded' => 'xl',
        ]),
        'section_theme' => 'default',
        'button_text' => 'Nimm Kontakt auf',
        'button_href' => '/kontakt',
        'button_variant' => 'primary',
        'button2_text' => 'Zu uns finden',
        'button2_href' => '/standorte-depots',
        'button2_variant' => 'secondary',
    ],
];

foreach ($newSections as $sectionData) {
    createSection($wirPage, $sectionData, $log);
}

// ---------------------------------------------------------------------------
// Step 3: Update existing sections to proper component types
// ---------------------------------------------------------------------------

$componentMap = [
    'wir' => [

        'section_component' => 'media_text',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'xl',
            'mediaSide' => 'left',
            'mediaWidth' => '50',
            'mediaRatio' => '4:3',
            'mediaFit' => 'cover',
            'verticalAlign' => 'center',
            'gap' => 'lg',
            'rounded' => 'lg',
        ]),
    ],
    'alle_mitglieder' => [

        'section_component' => 'media_text',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'xl',
            'mediaSide' => 'left',
            'mediaWidth' => '50',
            'mediaRatio' => '4:3',
            'mediaFit' => 'cover',
            'verticalAlign' => 'center',
            'gap' => 'lg',
            'rounded' => 'lg',
        ]),
    ],
    'betriebsgruppe' => [

        'section_component' => 'media_text',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'xl',
            'mediaSide' => 'right',
            'mediaWidth' => '50',
            'mediaRatio' => '4:3',
            'mediaFit' => 'cover',
            'verticalAlign' => 'center',
            'gap' => 'lg',
            'rounded' => 'lg',
        ]),
    ],
    'hof_team' => [

        'section_component' => 'cards_grid',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'xl',
            'columnsDesktop' => '2',
            'columnsMobile' => '1',
            'cardStyle' => 'soft',
            'mediaRatio' => '3:4',
            'mediaFit' => 'cover',
            'gap' => 'lg',
            'rounded' => 'md',
        ]),
    ],
    'geisshof' => [

        'section_component' => 'gallery_strip',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'xl',
            'columnsDesktop' => '4',
            'columnsMobile' => '1',
            'mediaRatio' => '4:3',
            'mediaFit' => 'cover',
            'gap' => 'lg',
            'rounded' => 'lg',
        ]),
        'button_text' => 'Anfahrtsweg zum Geisshof',
        'button_href' => '/standorte-depots',
        'button_variant' => 'secondary',
    ],
    'mission' => [
        'section_title' => 'Mission & Leitbild',
        'section_text' => '<h3>Solidarität</h3><p>Wir teilen Arbeit und Ertrag. Solidarische Landwirtschaft bedeutet, dass Produzentinnen und Konsumentinnen zusammenarbeiten.</p><h3>Nachhaltigkeit</h3><p>Wir arbeiten nach biologisch-dynamischen Prinzipien (Demeter) und fördern Biodiversität, Kreislaufwirtschaft und gesunde Böden.</p><h3>Gemeinschaft</h3><p>biocò lebt von der Gemeinschaft. Jede(r) bringt sich ein, lernt voneinander und gestaltet die Genossenschaft aktiv mit.</p><h3>Regionalität</h3><p>Unser Gemüse wächst direkt in der Region Baden-Brugg. Kurze Wege, frische Ernte, lokale Verbundenheit.</p>',

        'section_component' => 'text_columns',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'lg',
            'columnsDesktop' => '2',
            'columnsMobile' => '1',
            'gap' => 'lg',
        ]),
        'button_text' => 'Mehr über solidarische Landwirtschaft',
        'button_href' => '/solawi',
        'button_variant' => 'secondary',
    ],
    'geschichte' => [

        'section_component' => 'media_text',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'lg',
            'columnsDesktop' => '2',
            'columnsMobile' => '1',
            'gap' => 'lg',
            'cardStyle' => 'plain',
        ]),
    ],
    'timeline' => [

        'section_component' => 'timeline_header',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'lg',
            'textWidth' => 'normal',
            'align' => 'left',
        ]),
    ],
];

// Timeline items: set component + eyebrow year
$timelineYears = [
    'timeline_2013' => '2013',
    'timeline_2014' => '2014',
    'timeline_2016' => '2016',
    'timeline_2019-2023' => '2019-2023',
    'timeline_2023' => '2023',
    'timeline_2025' => '2025',
];

foreach ($timelineYears as $sectionId => $year) {
    $componentMap[$sectionId] = [

        'section_component' => 'timeline_item',
        'section_config' => encodeSectionConfig([
            'containerWidth' => 'lg',
            'emphasis' => 'normal',
        ]),
        'section_eyebrow' => $year,
    ];
}

foreach ($componentMap as $sectionId => $fields) {
    $section = findSectionById($wirPage, $sectionId);
    if ($section && $section->id) {
        updateSection($section, $fields, $log);
    } else {
        $log[] = "SKIP UPDATE: {$sectionId} not found";
    }
}

// ---------------------------------------------------------------------------
// Step 4: Reorder sections
// ---------------------------------------------------------------------------

$targetOrder = [
    'intro',
    'wir',
    'alle_mitglieder',
    'betriebsgruppe',
    'hof_team',
    'geisshof',
    'mission',
    'gotti',
    'geschichte',
    'timeline',
    'timeline_2013',
    'timeline_2014',
    'timeline_2016',
    'timeline_2019-2023',
    'timeline_2023',
    'timeline_2025',
    'mitmachen_cta',
    'kennenlernen_cta',
];

// Build id-to-section map
$sectionMap = [];
foreach ($wirPage->content_sections as $section) {
    $id = (string) $section->get('section_id');
    if ($id) $sectionMap[$id] = $section;
}

// Set sort values
$sort = 0;
foreach ($targetOrder as $sectionId) {
    if (isset($sectionMap[$sectionId])) {
        $s = $sectionMap[$sectionId];
        $s->of(false);
        $s->sort = $sort;
        $s->save();
        $sort++;
        unset($sectionMap[$sectionId]);
    } else {
        $log[] = "SKIP REORDER: {$sectionId} not found";
    }
}

// Append any remaining sections at the end
foreach ($sectionMap as $id => $s) {
    $s->of(false);
    $s->sort = $sort;
    $s->save();
    $sort++;
    $log[] = "APPENDED: {$id} at sort={$sort}";
}

$log[] = "REORDER: done, {$sort} sections";

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------

// Reload and report final state
$wirPage = wire('pages')->get($wirPage->id);
$finalSections = [];
foreach ($wirPage->content_sections as $section) {
    $finalSections[] = [
        'id' => (string) $section->get('section_id'),
        'pwId' => $section->id,
        'title' => (string) $section->get('section_title'),
        'component' => (string) $section->get('section_component'),
        'layout' => (string) $section->get('section_layout'),
        'sort' => $section->sort,
    ];
}

echo json_encode([
    'success' => true,
    'log' => $log,
    'errors' => $errors,
    'sections' => $finalSections,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
