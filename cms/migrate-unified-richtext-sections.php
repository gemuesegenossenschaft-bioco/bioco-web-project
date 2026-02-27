<?php
/**
 * Migration: unify content_sections text editing into one rich-text field.
 * - section_text becomes canonical editor field (title + body)
 * - section_title/section_eyebrow hidden in repeater UI context
 * - section_title auto-label support improved by prepending heading where missing
 * - CKEditor toolbar hardened (no free font size/color controls)
 *
 * Run via ProcessWire bootstrap, then delete bootstrap file.
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
$fieldgroups = wire('fieldgroups');
$pages = wire('pages');
$sanitizer = wire('sanitizer');

$log = [];
$errors = [];

function startsWithHeadingTag($html): bool {
    return preg_match('/^\s*<h[1-3]\b[^>]*>/i', (string) $html) === 1;
}

try {
    $log[] = '=== Unified Rich-Text Sections Migration ===';

    $sectionText = $fields->get('section_text');
    if (!$sectionText) {
        throw new \RuntimeException("Field 'section_text' not found");
    }

    // 1) Canonical editor field config
    $sectionText->label = 'Inhalt (Titel + Text)';
    $sectionText->description = 'Titel als H1/H2/H3 direkt im Inhalt erfassen.';
    $sectionText->inputfieldClass = 'InputfieldCKEditor';
    $sectionText->contentType = 1; // HTML
    $sectionText->toolbar = 'Format, Bold, Italic, Blockquote, -, BulletedList, NumberedList, -, PWLink, Unlink, -, Undo, Redo, -, Source';
    $sectionText->formatTags = 'p;h1;h2;h3';
    $sectionText->contentsCss = '/site/templates/styles/ckeditor.css';
    $fields->save($sectionText);
    $log[] = "Configured field: section_text";

    // 2) Hide legacy split fields from repeater UI context (keep DB fields)
    $repeaterFg = $fieldgroups->get('repeater_content_sections');
    if ($repeaterFg && $repeaterFg->id) {
        foreach (['section_title', 'section_eyebrow'] as $fieldName) {
            $field = $fields->get($fieldName);
            if (!$field || !$repeaterFg->hasField($field)) continue;
            $ctx = $repeaterFg->getFieldContextArray($field->id);
            $ctx['collapsed'] = Inputfield::collapsedHidden;
            $repeaterFg->setFieldContextArray($field->id, $ctx);
            $log[] = "Hidden in repeater UI: {$fieldName}";
        }
        $repeaterFg->saveContext();
    } else {
        $errors[] = "Fieldgroup 'repeater_content_sections' not found";
    }

    // Keep repeater row labels stable.
    $contentSections = $fields->get('content_sections');
    if ($contentSections) {
        $contentSections->set('repeaterTitle', '{section_title}');
        $fields->save($contentSections);
        $log[] = 'Configured content_sections repeaterTitle: {section_title}';
    }

    // 3) Data migration: prepend heading from section_title when heading missing at start
    $touched = 0;
    $updated = 0;
    $parents = $pages->find('include=all');
    foreach ($parents as $parent) {
        if (!$parent->hasField('content_sections') || !$parent->content_sections) continue;

        foreach ($parent->content_sections as $section) {
            if (!$section->hasField('section_text') || !$section->hasField('section_title')) continue;
            $title = trim((string) $section->get('section_title'));
            $text = (string) $section->get('section_text');
            if ($title === '' || trim($text) === '') continue;

            $touched++;
            if (startsWithHeadingTag($text)) continue;

            $section->of(false);
            $section->set('section_text', '<h2>' . $sanitizer->entities($title) . '</h2>' . "\n" . $text);
            $section->save('section_text');
            $updated++;
        }
    }

    $log[] = "Sections checked: {$touched}";
    $log[] = "Sections updated: {$updated}";

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
