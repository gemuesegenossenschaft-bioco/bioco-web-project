<?php
/**
 * Exports a live ProcessWire page into a content-seed JSON file.
 * ============================================================================
 * WHY THIS EXISTS
 * -------------------------------------------------------------------------
 * The 17 files in wordpress/content-seed/ captured content that used to be
 * hardcoded in the Next.js JSX. Two live routes never went through that step
 * because they were CMS-native from the start — `/abos` (the converted
 * reference page) and `/wir`. They therefore have NO seed, and
 * `wp bioco import` would produce a site missing both, while
 * content-seed/solawi.json links to them.
 *
 * The content for those pages exists only in ProcessWire, so it has to be
 * exported from the live CMS rather than reconstructed. This script does that
 * export and nothing else: it never invents content, and it refuses to write a
 * seed it cannot fully map.
 *
 * USAGE (needs network access to cms.bioco.ch — run it where that resolves)
 *
 *   php wordpress/scripts/fetch-cms-seed.php --slug=abos
 *   php wordpress/scripts/fetch-cms-seed.php --slug=wir
 *
 * Options:
 *   --slug=<slug>       required; also the API slug and the frontend route
 *   --api=<base>        default https://cms.bioco.ch/api
 *   --title=<text>      page title; defaults to the first h1/h2 found, else the slug
 *   --template=<name>   default basic-page
 *   --out=<dir>         default wordpress/content-seed
 *   --from-file=<path>  read a previously saved API response instead of fetching
 *                       (useful when the CMS is only reachable from another host:
 *                        curl the endpoint there, copy the JSON over, pass it here)
 *   --print             write nothing, dump the seed to stdout for review
 *
 * AFTER RUNNING, the result must pass the mapping gate — that is the proof the
 * new page will actually import:
 *
 *   php wordpress/scripts/check-seed-plan.php
 */

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    }
}

$slug = isset($args['slug']) && is_string($args['slug']) ? trim($args['slug']) : '';
if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    fwrite(STDERR, "FEHLER: --slug=<slug> fehlt oder ist ungueltig (erlaubt: a-z, 0-9, Bindestrich).\n");
    fwrite(STDERR, "Beispiel: php wordpress/scripts/fetch-cms-seed.php --slug=abos\n");
    exit(2);
}

$apiBase = isset($args['api']) && is_string($args['api']) ? rtrim($args['api'], '/') : 'https://cms.bioco.ch/api';
$canonicalSource = $apiBase . '/content/sections/' . rawurlencode($slug);
$template = isset($args['template']) && is_string($args['template']) ? $args['template'] : 'basic-page';
$outDir = isset($args['out']) && is_string($args['out']) ? rtrim($args['out'], '/') : dirname(__DIR__) . '/content-seed';

// ---------------------------------------------------------------------------
// 1. Obtain the API payload
// ---------------------------------------------------------------------------
if (!empty($args['from-file'])) {
    $path = (string) $args['from-file'];
    if (!is_file($path)) {
        fwrite(STDERR, "FEHLER: --from-file nicht gefunden: {$path}\n");
        exit(1);
    }
    $raw = (string) file_get_contents($path);
    $source = $path;
} else {
    $url = $canonicalSource;
    $source = $url;
    $ctx = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        fwrite(STDERR, "FEHLER: {$url} nicht erreichbar.\n");
        fwrite(STDERR, "Wenn das CMS nur von einem anderen Rechner erreichbar ist:\n");
        fwrite(STDERR, "  curl -s '{$url}' > /tmp/{$slug}.api.json\n");
        fwrite(STDERR, "  php wordpress/scripts/fetch-cms-seed.php --slug={$slug} --from-file=/tmp/{$slug}.api.json\n");
        exit(1);
    }
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    fwrite(STDERR, "FEHLER: Antwort von {$source} ist kein gueltiges JSON (" . json_last_error_msg() . ").\n");
    exit(1);
}

// The endpoint has returned sections under a couple of shapes over time; accept
// each explicitly rather than guessing, so a changed response fails loudly.
$sections = null;
foreach (['sections', 'data'] as $key) {
    if (isset($payload[$key]) && is_array($payload[$key])) {
        $sections = $payload[$key];
        break;
    }
}
if ($sections === null && isset($payload[0]) && is_array($payload[0])) {
    $sections = $payload; // bare array of sections
}
if (!is_array($sections) || !$sections) {
    fwrite(STDERR, "FEHLER: keine Sections in der Antwort gefunden. Erwartet wurde ein 'sections'-Array.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// 2. Map API section shape -> seed section shape
// ---------------------------------------------------------------------------
// buildSectionData() in site/templates/api.php emits these keys. Anything not
// listed here is reported as unmapped instead of being dropped silently — a
// dropped key is exactly how content goes missing without anyone noticing.
$MAP = [
    'id' => 'section_id',
    'title' => 'section_title',
    'text' => 'section_text',
    'layout' => 'section_layout',
    'theme' => 'section_theme',
    'eyebrow' => 'section_eyebrow',
    'component' => 'section_component',
    'config' => 'section_config',
    'buttons' => 'buttons',
    'image' => 'image_url',
    'images' => 'images',
    'imageAlt' => 'image_alt',
];
// Runtime/derived keys that legitimately do not belong in a seed.
$IGNORE = ['pwId', 'sort', 'imageData', 'media', 'video', 'anchor'];

$seedSections = [];
$unmapped = [];
$heading = '';

foreach ($sections as $i => $s) {
    if (!is_array($s)) continue;
    $out = [];
    foreach ($s as $k => $v) {
        if ($k === 'images' && !in_array(($s['component'] ?? ''), ['cards_grid', 'gallery_strip'], true)) continue;
        if (in_array($k, $IGNORE, true)) continue;
        if (!isset($MAP[$k])) {
            $unmapped[$k] = ($unmapped[$k] ?? 0) + 1;
            continue;
        }
        // Drop empties and the API's own defaults so the seed stays as terse as
        // the hand-written ones (they omit theme=default, empty titles, etc).
        if ($v === null || $v === '' || $v === []) continue;
        if ($k === 'theme' && $v === 'default') continue;
        $out[$MAP[$k]] = $v;
    }
    if (empty($out['section_id'])) {
        $out['section_id'] = 'section-' . ($i + 1);
    }
    if ($heading === '' && !empty($out['section_text'])
        && preg_match('/<h[12][^>]*>(.*?)<\/h[12]>/is', (string) $out['section_text'], $hm)) {
        $heading = trim(html_entity_decode(strip_tags($hm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    // Seed key order matches the hand-written files for a readable diff.
    $ordered = [];
    foreach (['section_id', 'section_layout', 'section_component', 'section_eyebrow',
              'section_title', 'section_text', 'section_theme', 'section_config',
              'image_url', 'image_alt', 'images', 'buttons'] as $k) {
        if (array_key_exists($k, $out)) $ordered[$k] = $out[$k];
    }
    $seedSections[] = $ordered;
}

if (!$seedSections) {
    fwrite(STDERR, "FEHLER: keine verwertbaren Sections — es wurde nichts geschrieben.\n");
    exit(1);
}

$title = isset($args['title']) && is_string($args['title']) ? $args['title'] : ($heading !== '' ? $heading : $slug);

$seed = [
    'path' => '/' . $slug . '/',
    'slug' => $slug,
    'template' => $template,
    'title' => $title,
    'sections' => $seedSections,
    'conversion_notes' => 'Exportiert aus dem laufenden ProcessWire via ' . $canonicalSource
        . ' mit wordpress/scripts/fetch-cms-seed.php. Diese Seite war von Anfang an CMS-getrieben und hatte deshalb '
        . 'nie eine Seed-Datei (die Seeds erfassen zuvor hart kodierten JSX-Inhalt). SEO-Werte sind hier NICHT '
        . 'enthalten und muessen bei Bedarf aus der Seite ergaenzt werden.',
];

$json = json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

if ($unmapped) {
    fwrite(STDERR, "FEHLER — nicht abgebildete Felder (bitte pruefen, ob dort Inhalt steckt):\n");
    foreach ($unmapped as $k => $n) fprintf(STDERR, "  %-20s %dx\n", $k, $n);
    fwrite(STDERR, "Diese Felder wurden NICHT in den Seed geschrieben.\n");
    exit(1);
}

echo "Slug:      {$slug}\n";
echo "Quelle:    {$source}\n";
echo "Titel:     {$title}\n";
echo "Sections:  " . count($seedSections) . "\n";

if (!empty($args['print'])) {
    echo "\n" . $json;
    exit(0);
}

if (!is_dir($outDir)) {
    fwrite(STDERR, "FEHLER: Zielverzeichnis fehlt: {$outDir}\n");
    exit(1);
}
$outFile = $outDir . '/' . $slug . '.json';
if (is_file($outFile)) {
    fwrite(STDERR, "FEHLER: {$outFile} existiert bereits. Vorhandene Seeds sind der Paritaets-Vertrag und werden nicht ueberschrieben.\n");
    exit(1);
}
$tempFile = tempnam($outDir, '.bioco-seed-');
if ($tempFile === false) {
    fwrite(STDERR, "FEHLER: {$outFile} konnte nicht geschrieben werden.\n");
    exit(1);
}
$written = file_put_contents($tempFile, $json, LOCK_EX);
if ($written !== strlen($json)) {
    @unlink($tempFile);
    fwrite(STDERR, "FEHLER: {$outFile} konnte nicht geschrieben werden.\n");
    exit(1);
}
if (!@rename($tempFile, $outFile)) {
    @unlink($tempFile);
    fwrite(STDERR, "FEHLER: {$outFile} konnte nicht veroeffentlicht werden.\n");
    exit(1);
}
echo "\nGeschrieben: {$outFile}\n";
echo "Jetzt pruefen: php wordpress/scripts/check-seed-plan.php\n";
exit(0);
