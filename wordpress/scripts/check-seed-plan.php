<?php
/**
 * Standalone seed-to-block conformance gate. Runs WITHOUT WordPress.
 * ============================================================================
 * The importer's weakest link is the mapping layer: a seed section whose
 * layout/component key is not mapped, a plan item naming a block directory
 * that does not exist, or an ACF group key with a typo. On a live site all
 * three fail quietly — the page just renders without that section — and the
 * only way to notice is a human comparing 20 pages by eye.
 *
 * `includes/seeds.php` and `includes/section-map.php` are deliberately free of
 * WordPress calls, so the whole seed -> plan pipeline can be exercised here
 * with nothing but the PHP CLI, and every referenced block + field group can be
 * checked against what bioco-core actually ships.
 *
 * Usage:  php wordpress/scripts/check-seed-plan.php
 * Exit:   0 = all seeds fully mapped and every reference resolves
 *         1 = at least one failure (printed above the summary)
 */

define('ABSPATH', __DIR__);

$root = dirname(__DIR__);
$importDir = $root . '/web/app/mu-plugins/bioco-import';
$coreDir = $root . '/web/app/mu-plugins/bioco-core';
$seedDir = $root . '/content-seed';

require_once $importDir . '/includes/seeds.php';
require_once $importDir . '/includes/section-map.php';

$failures = [];
$skips = [];
$blocksUsed = [];
$sectionsPlanned = 0;

try {
    $seeds = bioco_import_load_seeds($seedDir);
    $blockContent = bioco_import_load_block_content_defaults($seedDir);
} catch (RuntimeException $e) {
    fwrite(STDERR, "FAIL: Seeds konnten nicht geladen werden: " . $e->getMessage() . "\n");
    exit(1);
}

printf("%d Seed-Dateien geladen aus %s\n\n", count($seeds), $seedDir);
printf("Block-Inhaltsseeds: %d\n", count($blockContent));
foreach ($blockContent as $block => $values) {
    printf("  %-22s %d Felder\n", $block, count($values));
    $blockJsonPath = $coreDir . '/blocks/' . $block . '/block.json';
    if (!is_file($blockJsonPath)) {
        $failures[] = "Block-Inhaltsseed {$block}: blocks/{$block}/block.json fehlt.";
        continue;
    }
    $groupKey = 'group_bioco_block_' . str_replace('-', '_', $block);
    $groupPath = $coreDir . '/acf-json/' . $groupKey . '.json';
    $group = is_file($groupPath) ? json_decode((string) file_get_contents($groupPath), true) : null;
    if (!is_array($group)) {
        $failures[] = "Block-Inhaltsseed {$block}: acf-json/{$groupKey}.json fehlt oder ist ungültig.";
        continue;
    }
    $fieldNames = [];
    foreach ($group['fields'] ?? [] as $field) {
        $name = is_array($field) ? (string) ($field['name'] ?? '') : '';
        if ($name !== '') $fieldNames[$name] = true;
    }
    foreach (array_keys($values) as $fieldName) {
        if (!isset($fieldNames[$fieldName])) {
            $failures[] = "Block-Inhaltsseed {$block}: Feld {$fieldName} existiert nicht in {$groupKey}.";
        }
    }
}
echo "\n";

foreach ($seeds as $seed) {
    $slug = $seed['slug'];
    $plan = bioco_import_build_page_plan($seed);

    // Every section must be accounted for exactly once: the plan's section_ids
    // (union across items) must equal the seed's section ids. A section that
    // silently falls out of the plan is the failure mode this catches.
    $seedIds = array_map(function ($s) { return (string) $s['section_id']; }, $seed['sections']);
    $hero = is_array($seed['hero'] ?? null) ? $seed['hero'] : [];
    if ((string) ($hero['hero_title'] ?? '') !== '' || (string) ($hero['hero_subtitle'] ?? '') !== '' || (string) ($hero['image_url'] ?? '') !== '') {
        $seedIds[] = '__hero__';
    }
    // Code-owned homepage chrome is a synthetic native-Divi section, not CMS data.
    if ($slug === 'home') $seedIds[] = '__home_chrome__';
    $plannedIds = [];
    foreach ($plan as $item) {
        foreach ($item['section_ids'] as $sid) $plannedIds[$sid] = true;
    }
    $seedIdCounts = array_count_values($seedIds);
    $plannedIdCounts = array_count_values(array_keys($plannedIds));
    ksort($seedIdCounts);
    ksort($plannedIdCounts);
    if ($seedIdCounts !== $plannedIdCounts) {
        $failures[] = sprintf(
            '%s: Section-ID-Haeufigkeiten stimmen nicht (Seed: %s; Plan: %s)',
            $slug,
            json_encode($seedIdCounts, JSON_UNESCAPED_SLASHES),
            json_encode($plannedIdCounts, JSON_UNESCAPED_SLASHES)
        );
    }

    foreach ($plan as $item) {
        if ($item['type'] === 'skip') {
            $skips[] = sprintf('%s / %s: %s', $slug, implode('+', $item['section_ids']), $item['reason']);
            continue;
        }
        $sectionsPlanned++;

        $block = $item['block'];
        $group = $item['acf_group'];
        $blocksUsed[$block] = ($blocksUsed[$block] ?? 0) + 1;

        if (isset($blockContent[$block])) {
            foreach (array_keys($blockContent[$block]) as $fieldName) {
                if (!array_key_exists($fieldName, $item['values'])) {
                    $failures[] = "{$slug}: Block-Inhaltsseed {$block}.{$fieldName} fehlt im Importplan.";
                }
            }
        }

        // Synthetic composer-only sections serialize directly to divi/* and
        // intentionally have neither a bioco block directory nor an ACF group.
        if ($block === 'home-chrome') continue;

        // The block directory must exist and its block.json must be valid,
        // because that is what bioco-core registers and what the serialized
        // block comment name is derived from.
        $blockJsonPath = $coreDir . '/blocks/' . $block . '/block.json';
        if (!is_file($blockJsonPath)) {
            $failures[] = sprintf('%s: Block-Verzeichnis fehlt: blocks/%s/block.json', $slug, $block);
        } else {
            $decoded = json_decode((string) file_get_contents($blockJsonPath), true);
            if (!is_array($decoded)) {
                $failures[] = sprintf('%s: blocks/%s/block.json ist kein gueltiges JSON', $slug, $block);
            } elseif (($decoded['name'] ?? '') !== 'bioco/' . $block) {
                $failures[] = sprintf(
                    '%s: blocks/%s/block.json hat name "%s" statt "bioco/%s"',
                    $slug,
                    $block,
                    $decoded['name'] ?? '',
                    $block
                );
            }
        }

        // The ACF field group the plan writes into must actually ship, or every
        // value lands under a field key that WordPress will not recognise.
        $groupPath = $coreDir . '/acf-json/' . $group . '.json';
        if (!is_file($groupPath)) {
            $failures[] = sprintf('%s: ACF-Feldgruppe fehlt: acf-json/%s.json (Block %s)', $slug, $group, $block);
        } else {
            $decodedGroup = json_decode((string) file_get_contents($groupPath), true);
            if (!is_array($decodedGroup) || ($decodedGroup['key'] ?? '') !== $group) {
                $failures[] = sprintf(
                    '%s: acf-json/%s.json hat nicht den erwarteten key "%s" (gefunden: "%s")',
                    $slug, $group, $group, is_array($decodedGroup) ? ($decodedGroup['key'] ?? '') : 'ungueltiges JSON'
                );
            }
        }

        if (!empty($item['warnings'])) {
            foreach ($item['warnings'] as $w) {
                printf("  WARN  %-22s %-18s %s\n", $slug, $block, $w);
            }
        }
    }
}

echo "\nVerwendete Bloecke:\n";
ksort($blocksUsed);
foreach ($blocksUsed as $block => $count) {
    printf("  %-22s %d\n", $block, $count);
}

// The current 17 seeds map completely: ZERO sections are skipped. Pinning this
// at 0 turns the gate from "roughly covered" into a real door lock — any newly
// unmapped layout/component fails the build instead of quietly landing in a
// list nobody reads. Raise this only with a note saying which section and why.
$EXPECTED_MAX_SKIPS = 0;

echo "\nManuell nachzubauende Sections (skip): " . count($skips) . "\n";
foreach ($skips as $s) {
    echo "  SKIP  " . $s . "\n";
}
if (count($skips) > $EXPECTED_MAX_SKIPS) {
    $failures[] = sprintf(
        'Mehr uebersprungene Sections als erwartet (%d > %d). Entweder einen Block/Mapping ergaenzen '
        . 'oder EXPECTED_MAX_SKIPS in dieser Datei bewusst anheben.',
        count($skips), $EXPECTED_MAX_SKIPS
    );
}

printf("\nGeplante Bloecke: %d, Seiten: %d\n", $sectionsPlanned, count($seeds));

if ($failures) {
    echo "\n" . str_repeat('=', 70) . "\nFEHLER (" . count($failures) . "):\n";
    foreach ($failures as $f) echo "  - " . $f . "\n";
    echo "SEED_PLAN_CHECK: FAIL\n";
    exit(1);
}

echo "\nSEED_PLAN_CHECK: OK — jede Section ist geplant, jeder Block und jede ACF-Gruppe existiert.\n";
exit(0);
