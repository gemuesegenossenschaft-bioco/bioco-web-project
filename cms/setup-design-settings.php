<?php
/**
 * Migration: create global Design Settings singleton with typography tab.
 * Creates:
 * - template: site_settings
 * - page: /content/design-settings/
 * - tab: Typografie
 * - editable H1/H2 typography fields with validation patterns
 */

namespace ProcessWire;

if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Forbidden: run from localhost or CLI only');
}

if (!function_exists(__NAMESPACE__ . '\\wire')) {
    require_once dirname(__DIR__, 2) . '/index.php';
}

$fields = wire('fields');
$templates = wire('templates');
$fieldgroups = wire('fieldgroups');
$pages = wire('pages');

$log = [];
$errors = [];

function ensureTextField(string $name, string $label, string $description, string $pattern, string $defaultValue, array &$log): Field {
    $fields = wire('fields');
    $field = $fields->get($name);
    if (!$field) {
        $field = new Field();
        $field->type = wire('modules')->get('FieldtypeText');
        $field->name = $name;
        $log[] = "Created field: {$name}";
    } else {
        $log[] = "Field exists: {$name}";
    }

    $field->label = $label;
    $field->description = $description;
    $field->inputfieldClass = 'InputfieldText';
    $field->set('pattern', $pattern);
    $field->set('defaultValue', $defaultValue);
    $fields->save($field);

    return $field;
}

function ensureFieldsetField(string $name, string $typeClass, string $label, array &$log): Field {
    $fields = wire('fields');
    $field = $fields->get($name);
    if (!$field) {
        $field = new Field();
        $field->type = wire('modules')->get($typeClass);
        $field->name = $name;
        $log[] = "Created field: {$name}";
    } else {
        $log[] = "Field exists: {$name}";
    }
    $field->label = $label;
    $fields->save($field);
    return $field;
}

try {
    $log[] = '=== Setup Design Settings ===';

    $typographyTabOpen = ensureFieldsetField('typography_tab', 'FieldtypeFieldsetTabOpen', 'Typografie', $log);
    $typographyTabClose = ensureFieldsetField('typography_tab_end', 'FieldtypeFieldsetClose', 'Ende Typografie', $log);

    $fieldMap = [
        'typography_h1_color' => ['H1 Farbe', 'Hex-Farbe für H1 (z.B. #1a1a1a)', '^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$', '#1a1a1a'],
        'typography_h1_size_mobile' => ['H1 Größe Mobile', 'H1 Größe für Mobile (z.B. 2rem oder calc(...))', '^(?:\\d+(?:\\.\\d+)?(?:rem|px)|calc\\([^;{}]+\\))$', 'calc(1.375rem + 1.5vw)'],
        'typography_h1_size_desktop' => ['H1 Größe Desktop', 'H1 Größe für Desktop (z.B. 2.5rem oder calc(...))', '^(?:\\d+(?:\\.\\d+)?(?:rem|px)|calc\\([^;{}]+\\))$', '2.5rem'],
        'typography_h1_line_height' => ['H1 Zeilenhöhe', 'Wert zwischen 1.0 und 2.0', '^(?:1(?:\\.\\d+)?|2(?:\\.0+)?)$', '1.2'],
        'typography_h1_font_weight' => ['H1 Schriftstärke', 'Zulässig: 100-900', '^[1-9]00$', '700'],
        'typography_h1_letter_spacing' => ['H1 Laufweite', 'z.B. 0em oder 0.02em', '^-?\\d+(?:\\.\\d+)?(?:em|px)$', '0em'],

        'typography_h2_color' => ['H2 Farbe', 'Hex-Farbe für H2 (z.B. #1a1a1a)', '^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$', '#1a1a1a'],
        'typography_h2_size_mobile' => ['H2 Größe Mobile', 'H2 Größe für Mobile (z.B. 1.5rem oder calc(...))', '^(?:\\d+(?:\\.\\d+)?(?:rem|px)|calc\\([^;{}]+\\))$', 'calc(1.125rem + 0.7vw)'],
        'typography_h2_size_desktop' => ['H2 Größe Desktop', 'H2 Größe für Desktop (z.B. 1.75rem oder calc(...))', '^(?:\\d+(?:\\.\\d+)?(?:rem|px)|calc\\([^;{}]+\\))$', '1.75rem'],
        'typography_h2_line_height' => ['H2 Zeilenhöhe', 'Wert zwischen 1.0 und 2.0', '^(?:1(?:\\.\\d+)?|2(?:\\.0+)?)$', '1.2'],
        'typography_h2_font_weight' => ['H2 Schriftstärke', 'Zulässig: 100-900', '^[1-9]00$', '700'],
        'typography_h2_letter_spacing' => ['H2 Laufweite', 'z.B. 0em oder 0.02em', '^-?\\d+(?:\\.\\d+)?(?:em|px)$', '0em'],
    ];

    $typographyFields = [];
    foreach ($fieldMap as $name => [$label, $description, $pattern, $default]) {
        $typographyFields[] = ensureTextField($name, $label, $description, $pattern, $default, $log);
    }

    $template = $templates->get('site_settings');
    if (!$template) {
        $template = new Template();
        $template->name = 'site_settings';
        $template->label = 'Site Settings';

        $fieldgroup = new Fieldgroup();
        $fieldgroup->name = 'site_settings';
        $fieldgroups->save($fieldgroup);
        $template->fieldgroup = $fieldgroup;
        $template->noChildren = 1;
        $templates->save($template);
        $log[] = "Created template: site_settings";
    } else {
        $log[] = "Template exists: site_settings";
    }

    $fg = $template->fieldgroup;
    $orderedFields = array_merge(
        [$fields->get('title'), $typographyTabOpen],
        $typographyFields,
        [$typographyTabClose]
    );
    foreach ($orderedFields as $f) {
        if ($f && !$fg->hasField($f)) {
            $fg->add($f);
        }
    }
    $fieldgroups->save($fg);
    $templates->save($template);
    $log[] = "Updated fieldgroup for template: site_settings";

    $contentRoot = $pages->get('/content/');
    if (!$contentRoot->id) {
        $containerTemplate = $templates->get('basic-page');
        if (!$containerTemplate || !$containerTemplate->id) {
            $containerTemplate = $templates->get('basic_page');
        }

        if ($containerTemplate && $containerTemplate->id) {
            $contentRoot = new Page();
            $contentRoot->template = $containerTemplate;
            $contentRoot->parent = $pages->get(1);
            $contentRoot->name = 'content';
            $contentRoot->title = 'Content';
            $contentRoot->status = Page::statusHidden;
            $contentRoot->save();
            $log[] = "Created container page: /content/";
        } else {
            $contentRoot = $pages->get(1);
            $log[] = "No suitable container template found; using home as parent";
        }
    }

    $settingsPage = $pages->get('template=site_settings,name=design-settings,include=all');
    if (!$settingsPage->id) {
        $settingsPage = new Page();
        $settingsPage->template = $template;
        $settingsPage->parent = $contentRoot;
        $settingsPage->name = 'design-settings';
        $settingsPage->title = 'Design Settings';
        $settingsPage->status = Page::statusHidden;
        $settingsPage->save();
        $log[] = "Created page: /content/design-settings/";
    } else {
        $settingsPage->of(false);
        if ($settingsPage->template->name !== 'site_settings') {
            $settingsPage->template = $template;
            $settingsPage->save();
        }
        $log[] = "Page exists: /content/design-settings/";
    }

    // Seed defaults where empty.
    $settingsPage->of(false);
    foreach ($fieldMap as $name => $cfg) {
        $default = $cfg[3];
        if (!$settingsPage->get($name)) {
            $settingsPage->set($name, $default);
        }
    }
    $settingsPage->save();
    $log[] = 'Seeded default typography values';

    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    $errors[] = $e->getMessage();
    echo json_encode([
        'success' => false,
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
