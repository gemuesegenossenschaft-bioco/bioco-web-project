<?php
/**
 * Add dedicated team sections to /wir and migrate existing team images.
 *
 * Usage (via ProcessWire bootstrap):
 * - include this file from a bootstrap script under /public_html/cms/
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

function findSectionById(Page $page, string $fieldName, string $sectionId): ?Page
{
    if (!$page->hasField($fieldName) || !$page->$fieldName) {
        return null;
    }

    foreach ($page->$fieldName as $section) {
        if ((string) $section->get('section_id') === $sectionId) {
            return $section;
        }
    }

    return null;
}

function ensureSection(Page $page, string $fieldName, string $sectionId, string $title, string $text, string $layout, array &$log): Page
{
    $existing = findSectionById($page, $fieldName, $sectionId);
    if ($existing && $existing->id) {
        $log[] = "EXISTS: {$sectionId}";
        return $existing;
    }

    $page->of(false);
    $section = $page->$fieldName->getNew();
    if ($section->hasField('section_id')) $section->section_id = $sectionId;
    if ($section->hasField('section_title')) $section->section_title = $title;
    if ($section->hasField('section_text')) $section->section_text = $text;
    if ($section->hasField('section_layout')) $section->section_layout = $layout;
    if ($section->hasField('section_theme')) $section->section_theme = 'default';

    $section->save();
    $page->$fieldName->add($section);
    $page->save($fieldName);

    $log[] = "ADDED: {$sectionId}";
    return $section;
}

function getSupportedImageFields(Page $page): array
{
    $fields = [];
    foreach (['image', 'section_images', 'section_image'] as $fieldName) {
        if ($page->hasField($fieldName)) {
            $fields[] = $fieldName;
        }
    }
    return $fields;
}

function hasAnyImages(Page $page, string $fieldName): bool
{
    if (!$page->hasField($fieldName)) return false;
    $value = $page->get($fieldName);
    if ($value instanceof Pageimage || $value instanceof Pagefile) {
        return !empty($value->url);
    }
    if (($value instanceof Pageimages || $value instanceof Pagefiles) && $value->count()) {
        return true;
    }
    return false;
}

function copySectionImages(Page $source, Page $target, array &$log): int
{
    $targetFields = getSupportedImageFields($target);
    if (!count($targetFields)) {
        return 0;
    }

    $targetField = $targetFields[0];
    foreach ($targetFields as $candidate) {
        if ($candidate === 'image') {
            $targetField = $candidate;
            break;
        }
    }

    if (hasAnyImages($target, $targetField)) return 0;

    $sourceFields = getSupportedImageFields($source);
    if (!count($sourceFields)) {
        return 0;
    }

    $copied = 0;
    $target->of(false);
    foreach ($sourceFields as $sourceField) {
        $value = $source->get($sourceField);
        $images = [];
        if ($value instanceof Pageimage || $value instanceof Pagefile) {
            $images = [$value];
        } elseif (($value instanceof Pageimages || $value instanceof Pagefiles) && $value->count()) {
            foreach ($value as $img) {
                $images[] = $img;
            }
        }
        foreach ($images as $img) {
            if (!is_file($img->filename) || empty($img->url)) continue;
            $target->get($targetField)->add($img->filename);
            $copied++;
        }
        if ($copied > 0) break;
    }

    if ($copied > 0) {
        $target->save($targetField);
        $log[] = "COPIED_IMAGES: {$copied} -> {$targetField}";
    }

    return $copied;
}

try {
    $pages = wire('pages');
    $fields = wire('fields');

    $wir = $pages->get('/content/wir/');
    if (!$wir->id) {
        $wir = $pages->get('/wir/');
    }
    if (!$wir->id) {
        throw new \RuntimeException("Page '/content/wir/' not found");
    }

    $sectionsFieldName = null;
    if ($wir->hasField('content_sections')) $sectionsFieldName = 'content_sections';
    if (!$sectionsFieldName && $wir->hasField('sections')) $sectionsFieldName = 'sections';
    if (!$sectionsFieldName) {
        throw new \RuntimeException("No repeater field found on /wir (expected content_sections or sections)");
    }

    $sectionImagesField = $fields->get('section_images');
    if ($sectionImagesField && (int) $sectionImagesField->maxFiles === 1) {
        $sectionImagesField->maxFiles = 50;
        $fields->save($sectionImagesField);
        $log[] = "UPDATED_FIELD: section_images maxFiles=50";
    }

    ensureSection(
        $wir,
        $sectionsFieldName,
        'alle_mitglieder',
        'Alle Mitglieder',
        '<p>Jede(r) Genossenschafter/in bringt sich ein – ob bei der Feldarbeit, in der Logistik oder bei Events.</p>',
        'split_media_text',
        $log
    );

    ensureSection(
        $wir,
        $sectionsFieldName,
        'betriebsgruppe',
        'Betriebsgruppe (BG)',
        '<p>Die Betriebsgruppe koordiniert den Anbau, die Logistik und die Organisation der Genossenschaft.</p>',
        'split_media_text',
        $log
    );

    $hofTeam = ensureSection(
        $wir,
        $sectionsFieldName,
        'hof_team',
        'Hof-Team',
        '<p>Lade hier alle Teamfotos hoch. Diese Bilder werden auf der Seite /wir im Hof-Team-Block angezeigt.</p>',
        'media_grid',
        $log
    );

    $wirSection = findSectionById($wir, $sectionsFieldName, 'wir');
    if ($wirSection && $hofTeam && $hofTeam->id) {
        copySectionImages($wirSection, $hofTeam, $log);
    }

    $sectionIds = [];
    foreach ($wir->$sectionsFieldName as $section) {
        $sectionIds[] = (string) $section->get('section_id');
    }

    echo json_encode([
        'success' => true,
        'pageId' => $wir->id,
        'sectionsField' => $sectionsFieldName,
        'sectionIds' => $sectionIds,
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    $errors[] = $e->getMessage();
    echo json_encode([
        'success' => false,
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
