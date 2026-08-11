<?php
/**
 * Door-lock gate for the project's hard constraint: NO HARDCODED CONTENT,
 * NO FALLBACK CONTENT (see CLAUDE.md / AGENTS.md).
 * ============================================================================
 * Runs without WordPress. Scans block render templates and ACF JSON fields:
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
 *   (C) ACF content defaults  "default_value": "Nächste Events"
 *       Editorial defaults are fallback content too. Nested sub-fields and
 *       flexible-content layouts are checked recursively.
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
$acfJsonDir = $root . '/web/app/mu-plugins/bioco-core/acf-json';
$listMode = in_array('--list', $argv, true);

// Staged rollout of the acf-json scan. The recursive default_value check is
// implemented and proven non-vacuous, but 130 pre-existing editorial defaults
// across 15 field groups (mostly form labels) still have to be migrated into
// real seed content before it can gate. Until that migration lands, the scan
// runs opt-in via --acf-json and is always included in --list reporting, so
// the findings stay visible instead of being buried in the baseline.
// Follow-up: migrate those defaults, then make this unconditional and delete
// the flag. Do NOT write the findings into hardcoded-content-baseline.json.
$acfJsonMode = $listMode || in_array('--acf-json', $argv, true);

// Layout/style knobs: a default here is presentation, not content.
$PRESENTATION_KEYS = [
    'columns_desktop', 'columns_mobile', 'card_style', 'media_ratio', 'media_fit',
    'gap', 'rounded', 'container_width', 'align', 'theme', 'variant', 'limit',
    'columns', 'ratio', 'overlay', 'filter', 'width', 'size', 'style', 'mode',
    'text_width', 'media_side', 'media_width', 'vertical_align', 'image_overlay',
    'image_brightness', 'image_contrast', 'image_saturate', 'emphasis',
];

// Attribute values and mechanics that carry no editorial meaning.
$MECHANICAL = '/^(submit|button|text|email|tel|number|checkbox|radio|hidden|post|get|on|off|true|false|none|auto|cover|contain|lazy|eager|_blank|_self|nofollow|noopener)$/i';

$looksLikeContent = function ($literal) use ($MECHANICAL) {
    $text = trim(html_entity_decode(strip_tags((string) $literal), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '' || preg_match($MECHANICAL, $text)) return false;
    if (preg_match('/^(?:https?:|mailto:|tel:|\/|#|--wp--)/i', $text)) return false;
    if (preg_match('/^[a-z0-9_-]+$/', $text)) return false;
    if (preg_match('/^[A-Z0-9_:-]+$/', $text)) return false;
    if (preg_match('/[ÄÖÜäöüß]/u', $text)) return true;
    return (bool) preg_match('/^\p{Lu}\p{Ll}{2,}(?:[\s.,:;!?()–—-]+\p{L}{2,})*$/u', $text);
};

$CONTENT_KEYS = '/(?:^|_)(?:title|heading|label|text|intro|description|message|name|address|contact|option|suffix|note|placeholder)(?:_|$)/i';
$PRICE_KEYS = '/(?:^|_)(?:price|cost|fee|amount)(?:_|$)/i';

$findDefaultContent = function ($value, $fieldName) use (&$findDefaultContent, $looksLikeContent, $CONTENT_KEYS, $PRICE_KEYS, $PRESENTATION_KEYS) {
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $hit = $findDefaultContent($item, is_string($key) ? $key : $fieldName);
            if ($hit !== null) return $hit;
        }
        return null;
    }
    if ((is_int($value) || is_float($value)) && $value != 0 && preg_match($PRICE_KEYS, $fieldName)) {
        return (string) $value;
    }
    if (!is_string($value)) return null;

    $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '') return null;
    if (in_array($fieldName, $PRESENTATION_KEYS, true) && preg_match('/^[a-z0-9_.:-]+$/i', $text)) return null;
    if (preg_match('/^(?:https?:|mailto:|tel:|\/|#|--wp--)/i', $text)) return null;
    if (preg_match($CONTENT_KEYS, $fieldName) || $looksLikeContent($text) || preg_match('/\s/u', $text)) {
        return mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 80);
    }
    return null;
};

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

        // ---- (B) hardcoded German content in markup --------------------------
        // Include clear single-word labels, not only multi-word prose.
        if (preg_match_all('/>([^<>{}$?=]{2,120})</u', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $text = trim($hit[1]);
                if ($text === '') continue;
                if (preg_match('/^[\s\d.,:;|\/+\-()]*$/u', $text)) continue;
                if (strpos($text, 'php') !== false) continue;
                if (!$looksLikeContent($text)) continue;
                $violations[] = [
                    'file' => $rel,
                    'line' => $lineNo,
                    'kind' => 'hardcoded-prose',
                    'detail' => mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 80),
                ];
            }
        }

        // ---- (C) content literals assigned into PHP variables/arrays --------
        // Catch labels and rows assembled before markup, including one-word
        // German content such as category names.
        if (preg_match_all('/(?:=>|\$[A-Za-z_][A-Za-z0-9_]*\s*=)\s*([\'\"])(.*?)\1/u', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $text = trim($hit[2]);
                if (!$looksLikeContent($text)) continue;
                $violations[] = [
                    'file' => $rel,
                    'line' => $lineNo,
                    'kind' => 'hardcoded-php-string',
                    'detail' => mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 80),
                ];
            }
        }
        if (preg_match('/\$[A-Za-z_][A-Za-z0-9_]*\s*=\s*\[/', $line)
            && preg_match_all('/([\'\"])(.*?)\1/u', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $text = trim($hit[2]);
                if (!$looksLikeContent($text)) continue;
                $violations[] = [
                    'file' => $rel,
                    'line' => $lineNo,
                    'kind' => 'hardcoded-php-string',
                    'detail' => mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 80),
                ];
            }
        }
        if (preg_match('/^\s*([\'\"])([^\'\"]*)\1\s*,?\s*$/u', $line, $hit)) {
            $text = trim($hit[2]);
            if ($looksLikeContent($text)) {
                $violations[] = [
                    'file' => $rel,
                    'line' => $lineNo,
                    'kind' => 'hardcoded-php-string',
                    'detail' => mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 80),
                ];
            }
        }
    }
}

$acfFiles = $acfJsonMode ? (glob($acfJsonDir . '/*.json') ?: []) : [];
sort($acfFiles);
foreach ($acfFiles as $file) {
    $rel = ltrim(str_replace($root, '', $file), '/');
    $raw = (string) file_get_contents($file);
    $group = json_decode($raw, true);
    if (!is_array($group)) {
        $violations[] = ['file' => $rel, 'line' => 1, 'kind' => 'acf-json-invalid', 'detail' => 'ungueltiges JSON'];
        continue;
    }

    $walkFields = function ($fields, $path = '') use (&$walkFields, &$violations, &$allowedDefaults, $findDefaultContent, $raw, $rel) {
        foreach ($fields as $field) {
            if (!is_array($field)) continue;
            // Clone fields carry "name": "" — a set-but-empty value, which ??
            // does NOT fall through. Without the explicit empty check the path
            // degenerates to "group..sub" and the content heuristic receives an
            // empty field name.
            $name = (string) ($field['name'] ?? '');
            if ($name === '') $name = (string) ($field['key'] ?? '');
            if ($name === '') $name = '?';
            $fieldPath = $path === '' ? $name : $path . '.' . $name;
            if (array_key_exists('default_value', $field) && $field['default_value'] !== '' && $field['default_value'] !== [] && $field['default_value'] !== null) {
                $keyNeedle = '"key": ' . json_encode((string) ($field['key'] ?? ''), JSON_UNESCAPED_UNICODE);
                $keyOffset = strpos($raw, $keyNeedle);
                $defaultOffset = $keyOffset === false ? false : strpos($raw, '"default_value"', $keyOffset);
                $line = $defaultOffset === false ? 1 : substr_count(substr($raw, 0, $defaultOffset), "\n") + 1;
                $hit = $findDefaultContent($field['default_value'], $name);
                if ($hit !== null) {
                    $violations[] = [
                        'file' => $rel,
                        'line' => $line,
                        'kind' => 'acf-default-content',
                        'detail' => sprintf('%s hat redaktionellen Default "%s"', $fieldPath, $hit),
                    ];
                } else {
                    $allowedDefaults[] = sprintf('%s:%d  %s', $rel, $line, $fieldPath);
                }
            }
            if (is_array($field['sub_fields'] ?? null)) $walkFields($field['sub_fields'], $fieldPath);
            foreach (($field['layouts'] ?? []) as $layout) {
                if (!is_array($layout)) continue;
                $layoutName = (string) ($layout['name'] ?? '');
                if ($layoutName === '') $layoutName = (string) ($layout['key'] ?? '');
                if ($layoutName === '') $layoutName = 'layout';
                if (is_array($layout['sub_fields'] ?? null)) $walkFields($layout['sub_fields'], $fieldPath . '.' . $layoutName);
            }
        }
    };
    $walkFields($group['fields'] ?? []);
}

$files = array_merge($files, $acfFiles);

// ---------------------------------------------------------------------------
// Deferred backlog. Keyed "file:kind" => count of known findings in that file.
// A file may not exceed its recorded count: adding a violation fails the gate
// even while the existing ones are still being converted to ACF fields.
// Lower these numbers as blocks are converted; never raise one without a note.
// ---------------------------------------------------------------------------
$DEFERRED = [];
$deferredPath = __DIR__ . '/hardcoded-content-baseline.json';
// An EMPTY baseline ({}) is the goal state, not a missing baseline: it means
// zero tolerance, every finding is a new violation. Distinguish the two, or the
// gate reports "no baseline" once the backlog is actually cleared.
$baselineExists = false;
if (is_file($deferredPath)) {
    $decoded = json_decode((string) file_get_contents($deferredPath), true);
    if (is_array($decoded)) {
        $DEFERRED = $decoded;
        $baselineExists = true;
    }
}

$counts = [];
foreach ($violations as $v) {
    $key = $v['file'] . ':' . $v['kind'];
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}
ksort($counts);

if ($listMode || !$baselineExists) {
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
    echo "\nInhalt gehoert in echte CMS-/Seed-Daten, nicht in Templates oder ACF default_value. Details:\n";
    echo "  php wordpress/scripts/check-hardcoded-content.php --list\n";
    echo "HARDCODED_CONTENT_CHECK: FAIL\n";
    exit(1);
}

if (!$DEFERRED) {
    if ($acfJsonMode) {
        echo "\nHARDCODED_CONTENT_CHECK: OK — Nulltoleranz erreicht, kein hartkodierter Inhalt und kein redaktioneller ACF-Default.\n";
    } else {
        echo "\nHARDCODED_CONTENT_CHECK: OK — Nulltoleranz in den Block-Templates erreicht.\n";
        echo "HINWEIS: acf-json wurde NICHT geprueft. Der Scan ist implementiert, aber noch opt-in:\n";
        echo "  php wordpress/scripts/check-hardcoded-content.php --acf-json   (aktuell rot, 130 offene Defaults)\n";
    }
    exit(0);
}

echo "\nHARDCODED_CONTENT_CHECK: OK — keine neuen hartkodierten Inhalte oder Fallbacks.\n";
exit(0);
