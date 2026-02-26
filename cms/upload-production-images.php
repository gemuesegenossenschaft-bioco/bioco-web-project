<?php
/**
 * Migration: Upload production images from public/images/ to CMS fields
 * Run via bootstrap: curl https://cms.bioco.ch/bootstrap-upload-images.php
 *
 * Reads images from /home/bioco/bioco-frontend/public/images/ and assigns
 * them to the correct CMS page+field.
 */

namespace ProcessWire;

// Only allow from CLI or localhost
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Forbidden: run from localhost or CLI only');
}

require_once __DIR__ . '/../site/init.php';

$pages = wire('pages');
$imgBase = '/home/bioco/bioco-frontend/public/images';

function addImageToField($page, $fieldName, $imagePath) {
    if (!file_exists($imagePath)) {
        echo "  MISSING: {$imagePath}\n";
        return false;
    }
    if (!$page->hasField($fieldName)) {
        echo "  NO FIELD: {$fieldName} on page '{$page->name}'\n";
        return false;
    }

    $page->of(false);
    $field = $page->get($fieldName);

    // Skip if field already has images
    if ($field && $field->count() > 0) {
        echo "  EXISTS: {$fieldName} on '{$page->name}' already has images\n";
        return true;
    }

    $page->get($fieldName)->add($imagePath);
    $page->save($fieldName);
    echo "  ADDED: " . basename($imagePath) . " → {$page->name}.{$fieldName}\n";
    return true;
}

function addImagesToField($page, $fieldName, $imagePaths) {
    if (!$page->hasField($fieldName)) {
        echo "  NO FIELD: {$fieldName} on page '{$page->name}'\n";
        return false;
    }

    $page->of(false);
    $field = $page->get($fieldName);
    if ($field && $field->count() > 0) {
        echo "  EXISTS: {$fieldName} on '{$page->name}' already has images\n";
        return true;
    }

    foreach ($imagePaths as $path) {
        if (!file_exists($path)) {
            echo "  MISSING: {$path}\n";
            continue;
        }
        $page->get($fieldName)->add($path);
        echo "  ADDED: " . basename($path) . " → {$page->name}.{$fieldName}\n";
    }
    $page->save($fieldName);
    return true;
}

// ---- Homepage hero ----
echo "=== HOMEPAGE ===\n";
$home = $pages->get('/');
if ($home->id) {
    addImageToField($home, 'hero_image', "{$imgBase}/FrontseiteStartseite.jpg");
}

// ---- Homepage sections (via repeater) ----
// Find section by section_id in repeater
function findSection($page, $sectionId) {
    if (!$page->hasField('sections')) return null;
    foreach ($page->sections as $section) {
        if ($section->section_id === $sectionId) return $section;
    }
    return null;
}

$willkommen = findSection($home, 'willkommen');
if ($willkommen) {
    addImageToField($willkommen, 'section_image', "{$imgBase}/mitmachen/zusammen-arbeiten.JPG");
}

$gemeinsam = findSection($home, 'gemeinsam');
if ($gemeinsam) {
    addImageToField($gemeinsam, 'section_image', "{$imgBase}/gemeinsamSolidarischFrisch.JPG");
}

// ---- Mitmachen ----
echo "\n=== MITMACHEN ===\n";
$mitmachen = $pages->get('/content/mitmachen/');
if ($mitmachen->id) {
    $familien = findSection($mitmachen, 'familien');
    if ($familien) {
        addImageToField($familien, 'section_image', "{$imgBase}/ernte/bioco_ernte-kuerbis-hoch.JPG");
    }
}

// ---- Wir ----
echo "\n=== WIR ===\n";
$wir = $pages->get('/content/wir/');
if ($wir->id) {
    // Wir main section images
    $wirSection = findSection($wir, 'wir');
    if ($wirSection) {
        addImagesToField($wirSection, 'section_images', [
            "{$imgBase}/team/alle-mitglieder-bioco.jpeg",
            "{$imgBase}/team/hofteam_matthias.JPG",
            "{$imgBase}/team/bioco_hofteam_christian.JPG",
        ]);
    }

    // Geisshof section
    $geisshof = findSection($wir, 'geisshof');
    if ($geisshof) {
        $hofImages = glob("{$imgBase}/hof/bioco_hof_luftaufnahme_*.JPG");
        $hofImages = array_merge([
            "{$imgBase}/DerHof1.jpg",
            "{$imgBase}/DerHof2.JPG",
        ], $hofImages ?: []);
        addImagesToField($geisshof, 'section_images', $hofImages);
    }
}

// ---- Gemuese gallery ----
echo "\n=== GEMUESE ===\n";
$gemuese = $pages->get('/content/gemuese/');
if ($gemuese->id) {
    $ernteImages = glob("{$imgBase}/ernte/*.JPG");
    if ($ernteImages) {
        // Find gallery section or add to page directly
        $gallerySection = findSection($gemuese, 'gallery');
        if ($gallerySection) {
            addImagesToField($gallerySection, 'section_images', $ernteImages);
        } else {
            addImagesToField($gemuese, 'gallery_images', $ernteImages);
        }
    }
}

echo "\nDone.\n";
