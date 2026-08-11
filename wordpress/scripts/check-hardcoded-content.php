<?php
/**
 * Door-lock gate for the project's hard constraint: NO HARDCODED CONTENT,
 * NO FALLBACK CONTENT (see CLAUDE.md / AGENTS.md).
 * ============================================================================
 * Runs without WordPress. Scans the block render templates and flags:
 *
 *   (A) content fallbacks   get_field('title') ?: 'Nächste Events'
 *       A missing field must render nothing, never invented text. A fallback
 *       looks correct, so nobody ever notices the field was left empty.
 *
 *   (B) hardcoded prose     <h3>Vergangene Events &amp; Eindrücke</h3>
 *       German editorial copy written into PHP. If a human would want to
 *       reword it without a developer, it is content and belongs in an ACF
 *       field, editable in wp-admin.
 *
 * Presentation defaults (columns_desktop ?: '3', gap ?: 'lg') are NOT content
 * and are allowed — but the value belongs in the ACF field's default_value, so
 * they are listed as ALLOWED-DEFAULT rather than silently ignored.
 *
 * Every finding is either fixed or carried in the DEFERRED catalog below with
 * a reason, so a NEW violation always fails even while the backlog is open.
 * Same discipline as frontend/tests/no-raw-hex.test.ts.
 *
 * Usage:  php wordpress/scripts/check-hardcoded-content.php
 *         php wordpress/scripts/check-hardcoded-content.php --list
 * Exit:   0 = no new violations, 1 = new violation(s)
 */

$root = dirname(__DIR__);
$blocksDir = $root . '/web/app/mu-plugins/bioco-core/blocks';
$listMode = in_array('--list', $argv, true);

// Layout/style knobs: a default here is presentation, not content.
$PRESENTATION_KEYS = [
    'columns_desktop', 'columns_mobile', 'card_style', 'media_ratio', 'media_fit',
    'gap', 'rounded', 'container_width', 'align', 'theme', 'variant', 'limit',
    'columns', 'ratio', 'overlay', 'filter', 'width', 'size', 'style', 'mode',
];

// Attribute values and mechanics that carry no editorial meaning.
$MECHANICAL = '/^(submit|button|text|email|tel|number|checkbox|radio|hidden|post|get|on|off|true|false|none|auto|cover|contain|lazy|eager|_blank|_self|nofollow|noopener)$/i';

$violations = [];
$allowedDefaults = [];

$files = glob($blocksDir . '/*/render.php') ?: [];
$helpers = glob($root . '/web/app/mu-plugins/bioco-core/includes/*.php') ?: [];
$files = array_merge($files, $helpers);
sort($files);

if (!$files) {
    fwrite(STDERR, "FAIL: keine render.php gefunden unter {$blocksDir}\n");
    exit(1);
}

foreach ($files as $file) {
    $rel = ltrim(str_replace($root, '', $file), '/');
    $lines = file($file, FILE_IGNORE_NEW_LINES);

    foreach ($lines as $i => $line) {
        $lineNo = $i + 1;

        // ---- (A) fallback operators on a field read -------------------------
        // get_field('x') ?: 'literal'   /   get_field('x') ?? 'literal'
        if (preg_match_all("/get_field\(\s*'([a-z0-9_]+)'\s*\)\s*(?:\?:|\?\?)\s*'([^']*)'/i", $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                [$whole, $fieldName, $fallback] = $hit;
                if ($fallback === '') continue; // empty-string fallback renders nothing: fine
                if (in_array($fieldName, $PRESENTATION_KEYS, true)) {
                    $allowedDefaults[] = sprintf('%s:%d  %s ?: %s', $rel, $lineNo, $fieldName, $fallback);
                    continue;
                }
                if (preg_match($MECHANICAL, $fallback)) continue;
                $violations[] = [
                    'file' => $rel,
                    'line' => $lineNo,
                    'kind' => 'fallback-content',
                    'detail' => sprintf("get_field('%s') faellt auf \"%s\" zurueck", $fieldName, $fallback),
                ];
            }
        }

        // ---- (B) hardcoded German prose in markup ---------------------------
        // Text between tags, at least two words, containing a German letter or
        // a lowercase run — enough to be prose rather than a slug or number.
        if (preg_match_all('/>([^<>{}$?=]{8,120})</u', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $text = trim($hit[1]);
                if ($text === '') continue;
                if (!preg_match('/\p{L}{2,}\s+\p{L}{2,}/u', $text)) continue; // needs 2+ words
                if (preg_match('/^[\s\d.,:;|\/+\-()]*$/u', $text)) continue;
                if (strpos($text, 'php') !== false) continue;
                $violations[] = [
                    'file' => $rel,
                    'line' => $lineNo,
                    'kind' => 'hardcoded-prose',
                    'detail' => mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 80),
                ];
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Deferred backlog. Keyed "file:kind" => count of known findings in that file.
// A file may not exceed its recorded count: adding a violation fails the gate
// even while the existing ones are still being converted to ACF fields.
// Lower these numbers as blocks are converted; never raise one without a note.
// ---------------------------------------------------------------------------
$DEFERRED = [];
$deferredPath = __DIR__ . '/hardcoded-content-baseline.json';
if (is_file($deferredPath)) {
    $decoded = json_decode((string) file_get_contents($deferredPath), true);
    if (is_array($decoded)) $DEFERRED = $decoded;
}

$counts = [];
foreach ($violations as $v) {
    $key = $v['file'] . ':' . $v['kind'];
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}
ksort($counts);

if ($listMode || !$DEFERRED) {
    echo "Gefundene Treffer pro Datei/Art:\n";
    foreach ($counts as $key => $n) printf("  %-78s %d\n", $key, $n);
    printf("\nSumme: %d Treffer in %d Gruppen\n", count($violations), count($counts));
    echo "\nErlaubte Praesentations-Defaults (gehoeren in ACF default_value):\n";
    foreach ($allowedDefaults as $a) echo '  ' . $a . "\n";
    if ($listMode) {
        echo "\nDetails:\n";
        foreach ($violations as $v) {
            printf("  %-10s %s:%d  %s\n", $v['kind'], $v['file'], $v['line'], $v['detail']);
        }
        // --list is a reporting mode, not a gate: exit 0 so it can be piped.
        exit(0);
    }
    echo "\nHINWEIS: keine Baseline-Datei vorhanden. Zum Anlegen:\n";
    echo "  php wordpress/scripts/check-hardcoded-content.php --write-baseline\n";
    if (in_array('--write-baseline', $argv, true)) {
        file_put_contents($deferredPath, json_encode($counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        echo "Baseline geschrieben: " . $deferredPath . "\n";
        exit(0);
    }
    exit(count($violations) > 0 ? 1 : 0);
}

if (in_array('--write-baseline', $argv, true)) {
    file_put_contents($deferredPath, json_encode($counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo "Baseline aktualisiert: " . $deferredPath . "\n";
    exit(0);
}

$failures = [];
foreach ($counts as $key => $n) {
    $allowed = $DEFERRED[$key] ?? 0;
    if ($n > $allowed) {
        $failures[] = sprintf('%s: %d Treffer, erlaubt sind %d', $key, $n, $allowed);
    }
}
// A group that dropped to zero should be removed from the baseline, so the
// backlog cannot quietly stay "open" after the work is actually done.
foreach ($DEFERRED as $key => $allowed) {
    if (!isset($counts[$key]) && $allowed > 0) {
        $failures[] = sprintf('%s: keine Treffer mehr — Eintrag aus der Baseline entfernen (%d erwartet)', $key, $allowed);
    }
}

printf("Geprueft: %d Dateien, %d Treffer in %d Gruppen (Baseline: %d Gruppen)\n",
    count($files), count($violations), count($counts), count($DEFERRED));

if ($failures) {
    echo "\n" . str_repeat('=', 70) . "\nNEUE VERSTOESSE (" . count($failures) . "):\n";
    foreach ($failures as $f) echo '  - ' . $f . "\n";
    echo "\nInhalt gehoert in ein ACF-Feld, nicht in die render.php. Details:\n";
    echo "  php wordpress/scripts/check-hardcoded-content.php --list\n";
    echo "HARDCODED_CONTENT_CHECK: FAIL\n";
    exit(1);
}

echo "\nHARDCODED_CONTENT_CHECK: OK — keine neuen hartkodierten Inhalte oder Fallbacks.\n";
exit(0);
