<?php
/**
 * Seed loading + validation. Mirrors the shape/rules already battle-tested by
 * site/templates/migrate-content-freeze.php (the ProcessWire content-freeze
 * migration) so both importers agree on what a valid seed looks like.
 */

if (!defined('ABSPATH')) exit;

// Loads and validates every *.json in $seedDir (optionally filtered to
// $onlySlugs). Throws RuntimeException on any structural problem — an
// importer that silently accepts a malformed seed is worse than one that
// refuses to run.
function bioco_import_load_seeds($seedDir, array $onlySlugs = []) {
    $seedDir = rtrim($seedDir, '/');
    if (!is_dir($seedDir)) {
        throw new RuntimeException("Seed-Verzeichnis nicht gefunden: {$seedDir}");
    }
    $files = glob($seedDir . '/*.json') ?: [];
    if (!$files) {
        throw new RuntimeException("Keine Seed-Dateien in {$seedDir} gefunden.");
    }

    $seeds = [];
    foreach ($files as $file) {
        $raw = file_get_contents($file);
        $seed = json_decode((string) $raw, true);
        if (!is_array($seed)) {
            throw new RuntimeException('Seed ist kein gültiges JSON: ' . basename($file) . ' (' . json_last_error_msg() . ')');
        }
        bioco_import_validate_seed($seed, basename($file));
        $seed['_bioco_seed_dir'] = $seedDir;
        $seeds[] = $seed;
    }

    if ($onlySlugs) {
        $seeds = array_values(array_filter($seeds, function ($s) use ($onlySlugs) {
            return in_array($s['slug'], $onlySlugs, true);
        }));
        if (!$seeds) {
            throw new RuntimeException('Kein Seed passt auf --only=' . implode(',', $onlySlugs));
        }
    }

    // Home first (it is also the front-page candidate for site wiring),
    // then alphabetically by slug for a stable, reviewable report order.
    usort($seeds, function ($a, $b) {
        if ($a['slug'] === 'home') return -1;
        if ($b['slug'] === 'home') return 1;
        return strcmp($a['slug'], $b['slug']);
    });

    return $seeds;
}

// Editorial values shared by every instance of a block live beside the page
// seeds, not in ACF field definitions. Page-specific values are merged later
// and always win over these values.
function bioco_import_load_block_content_defaults($seedDir) {
    $path = rtrim((string) $seedDir, '/') . '/block-content/defaults.json';
    static $cache = [];
    if (array_key_exists($path, $cache)) return $cache[$path];
    if (!is_file($path)) {
        throw new RuntimeException("Block-Inhaltsseed nicht gefunden: {$path}");
    }

    $document = json_decode((string) file_get_contents($path), true);
    if (!is_array($document) || ($document['version'] ?? null) !== 1 || !is_array($document['blocks'] ?? null)) {
        throw new RuntimeException("Block-Inhaltsseed ist ungültig: {$path}");
    }
    foreach ($document['blocks'] as $block => $values) {
        if (!is_string($block) || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $block) || !is_array($values)) {
            throw new RuntimeException("Block-Inhaltsseed enthält einen ungültigen Eintrag: {$path}");
        }
    }
    return $cache[$path] = $document['blocks'];
}

function bioco_import_validate_seed(array $seed, $file) {
    foreach (['path', 'slug', 'template', 'title', 'sections'] as $key) {
        if (empty($seed[$key])) {
            throw new RuntimeException("Seed {$file}: Pflichtfeld '{$key}' fehlt oder ist leer.");
        }
    }
    if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $seed['slug'])) {
        throw new RuntimeException("Seed {$file}: 'slug' ist kein gültiger Seiten-Slug: '{$seed['slug']}'.");
    }
    if (!is_array($seed['sections'])) {
        throw new RuntimeException("Seed {$file}: 'sections' muss ein Array sein.");
    }

    $seen = [];
    foreach ($seed['sections'] as $i => $section) {
        $sid = (string) ($section['section_id'] ?? '');
        if ($sid === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $sid)) {
            throw new RuntimeException("Seed {$file}: sections[{$i}] hat keine gültige section_id.");
        }
        if (isset($seen[$sid])) {
            throw new RuntimeException("Seed {$file}: doppelte section_id '{$sid}'.");
        }
        $seen[$sid] = true;
        if (isset($section['buttons']) && (!is_array($section['buttons']) || count($section['buttons']) > 2)) {
            throw new RuntimeException("Seed {$file}#{$sid}: 'buttons' muss ein Array mit max. 2 Einträgen sein.");
        }
        if (isset($section['section_config']) && !is_array($section['section_config'])) {
            throw new RuntimeException("Seed {$file}#{$sid}: 'section_config' muss ein JSON-Objekt sein.");
        }
    }
}
