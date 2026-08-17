<?php
/**
 * Core page import: find-or-create a WP page per seed, build its desired
 * post_content from the section plan (section-map.php), and write it under
 * a whole-page "CMS/Divi wins" rule — WordPress pages are a single
 * post_content blob, not a per-item repeater like ProcessWire's
 * content_sections, so (unlike migrate-content-freeze.php) the write
 * decision is made once per page. Per-section rows are still reported for
 * every block in the plan so the report reads the same way either way.
 *
 * Statuses used: create | update | skip-existing | ok-equal | warn | error | info.
 */

if (!defined('ABSPATH')) exit;

function bioco_import_find_page($slug) {
    $posts = get_posts([
        'post_type' => 'page',
        'name' => $slug,
        'post_status' => 'any',
        'numberposts' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => false,
    ]);
    return $posts ? $posts[0] : null;
}

function bioco_import_ensure_divi_shell($post_id, $mode) {
    if ($mode !== 'apply' || !$post_id) return;
    $builder = (string) get_post_meta($post_id, '_et_pb_use_builder', true);
    if ($builder !== 'on') {
        update_post_meta($post_id, '_et_pb_use_builder', 'on');
    }
    $layout = (string) get_post_meta($post_id, '_et_pb_page_layout', true);
    if ($layout !== 'et_full_width_page') {
        update_post_meta($post_id, '_et_pb_page_layout', 'et_full_width_page');
    }
}

function bioco_import_excerpt($value, $len = 140) {
    $v = preg_replace('/\s+/u', ' ', (string) $value);
    $v = trim((string) $v);
    if (function_exists('mb_strlen') && mb_strlen($v) > $len) {
        return mb_substr($v, 0, $len) . '…';
    }
    return strlen($v) > $len ? substr($v, 0, $len) . '…' : $v;
}

// Reuses an attachment already imported from this exact URL (tagged via
// _bioco_import_source_url) so re-running the importer never re-downloads
// the same image — required for the "zero writes on an unchanged re-run"
// idempotency guarantee. Returns an attachment ID, or null if nothing exists
// yet and $mode is not 'apply' (dry-run never downloads).
function bioco_import_resolve_attachment_for_url($url, $mode) {
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'any',
        'numberposts' => 1,
        'meta_key' => '_bioco_import_source_url',
        'meta_value' => $url,
    ]);
    if ($existing) return (int) $existing[0]->ID;

    if ($mode !== 'apply') return null;

    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    $attachmentId = media_sideload_image($url, 0, null, 'id');
    if (is_wp_error($attachmentId)) return null;
    update_post_meta($attachmentId, '_bioco_import_source_url', $url);
    return (int) $attachmentId;
}

// Replaces every bioco_import_pending_image() marker in $values with a real
// attachment ID (apply) or drops it while logging what WOULD happen
// (dry-run / failed sideload) — never leaves the sentinel array in $values,
// which would otherwise get serialized as garbage ACF data.
function bioco_import_resolve_pending_images(array &$values, $mode, array &$warnings) {
    foreach ($values as $key => $value) {
        if (is_array($value) && !bioco_import_is_pending_image($value)) {
            $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
            bioco_import_resolve_pending_images($value, $mode, $warnings);
            if ($isList) $value = array_values($value);
            $values[$key] = $value;
            continue;
        }
        if (!bioco_import_is_pending_image($value)) continue;
        $url = $value['__bioco_pending_image__'];
        $alt = (string) ($value['__bioco_pending_image_alt__'] ?? '');
        $attachmentId = bioco_import_resolve_attachment_for_url($url, $mode);
        if ($attachmentId === null) {
            unset($values[$key]);
            $warnings[] = ($mode === 'apply')
                ? "Bild-Import fehlgeschlagen von {$url} — Feld '{$key}' bleibt leer."
                : "WÜRDE: Bild importieren von {$url} (Feld '{$key}').";
            continue;
        }
        // Only fill an empty attachment alt: an alt already in the media
        // library wins (same CMS-wins rule as page content) and re-runs stay
        // write-free.
        if ($alt !== '' && $mode === 'apply' && (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true) === '') {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
        }
        $values[$key] = $attachmentId;
    }
}

function bioco_import_seo_plugin_meta_keys() {
    if (defined('WPSEO_VERSION')) {
        return ['title' => '_yoast_wpseo_title', 'description' => '_yoast_wpseo_metadesc', 'plugin' => 'Yoast SEO'];
    }
    if (defined('RANK_MATH_VERSION')) {
        return ['title' => 'rank_math_title', 'description' => 'rank_math_description', 'plugin' => 'Rank Math'];
    }
    // AIOSEO has no supported public post-meta contract here; omit the fake integration.
    return null;
}

// SEO title/description: only written if a supported SEO plugin is active
// (its own meta keys), and only ever into an empty field unless $force —
// same CMS-wins rule as the page content itself.
function bioco_import_seed_seo($slug, $post, array $seed, $mode, $force, array &$report) {
    if (empty($seed['seo']) || !is_array($seed['seo']) || !$post) return;

    $plugin = bioco_import_seo_plugin_meta_keys();
    if (!$plugin) {
        bioco_import_report_row($report, $slug, '(seo)', '', 'info', 'SEO-Daten im Seed vorhanden, aber kein unterstütztes SEO-Plugin (Yoast SEO / Rank Math) aktiv — Titel/Beschreibung nicht importiert.');
        return;
    }

    foreach (['title' => 'title', 'description' => 'description'] as $seedKey => $slot) {
        $desired = (string) ($seed['seo'][$seedKey] ?? '');
        if ($desired === '') continue;
        $metaKey = $plugin[$slot];
        $current = (string) get_post_meta($post->ID, $metaKey, true);

        if ($current === $desired) {
            bioco_import_report_row($report, $slug, '(seo)', $metaKey, 'ok-equal', 'Bereits identisch.');
            continue;
        }
        if (trim($current) !== '' && !$force) {
            bioco_import_report_row($report, $slug, '(seo)', $metaKey, 'skip-existing', 'Bereits gesetzt — CMS gewinnt (--force zum Überschreiben). Vorhanden: "' . bioco_import_excerpt($current) . '"');
            continue;
        }
        if ($mode === 'apply') {
            update_post_meta($post->ID, $metaKey, $desired);
            bioco_import_report_row($report, $slug, '(seo)', $metaKey, 'update', 'Gesetzt auf "' . bioco_import_excerpt($desired) . '" (' . $plugin['plugin'] . ').');
        } else {
            bioco_import_report_row($report, $slug, '(seo)', $metaKey, 'update', 'WÜRDE: Setzen auf "' . bioco_import_excerpt($desired) . '" (' . $plugin['plugin'] . ').');
        }
    }
}

// Builds the plan's block markups + reports per-section warn/error/info rows
// that are independent of the eventual page-level create/update decision.
// Returns [desiredContent, sectionLabels] where sectionLabels lists one
// label per block actually included in $desiredContent (used to emit the
// page-level create/update/skip-existing/ok-equal row per section).
function bioco_import_build_desired_content(array $seed, $mode, array &$report) {
    $slug = (string) $seed['slug'];
    $plan = bioco_import_build_page_plan($seed);

    $blockMarkups = [];
    $sectionLabels = [];

    foreach ($plan as $item) {
        if ($item['type'] === 'skip') {
            bioco_import_report_row($report, $slug, implode(',', $item['section_ids']), '', 'warn', $item['reason']);
            continue;
        }

        $values = $item['values'];
        $warnings = $item['warnings'] ?? [];
        $sectionLabel = implode(',', $item['section_ids']);

        bioco_import_resolve_pending_images($values, $mode, $warnings);

        $composerItem = $item;
        $composerItem['values'] = $values;
        $markup = bioco_import_serialize_divi_blocks([
            Bioco_Import_Divi_Composer::section($composerItem)
        ]);

        foreach ($warnings as $w) {
            bioco_import_report_row($report, $slug, $sectionLabel, $item['block'], 'warn', $w);
        }

        if (!empty($item['note'])) {
            bioco_import_report_row($report, $slug, $sectionLabel, $item['block'], 'info', $item['note']);
        }

        $marker = '<!-- bioco:section ' . esc_html($sectionLabel) . ' -->';
        $blockMarkups[] = $marker . "\n" . $markup;
        $sectionLabels[] = $sectionLabel;
    }

    $content = implode("\n\n", $blockMarkups);
    if ($blockMarkups) $content .= "\n";
    return [$content, $sectionLabels];
}

// Imports (or reports the plan for) a single seed's page. $report is
// mutated in place; nothing is returned — see bioco_import_run().
function bioco_import_page_for_seed(array $seed, $mode, $force, array &$report) {
    $slug = (string) $seed['slug'];
    [$desiredContent, $sectionLabels] = bioco_import_build_desired_content($seed, $mode, $report);

    $existing = bioco_import_find_page($slug);

    if (!$existing) {
        if ($mode === 'apply') {
            $postId = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => (string) $seed['title'],
                'post_name' => $slug,
                'post_content' => wp_slash($desiredContent),
            ], true);
            if (is_wp_error($postId)) {
                bioco_import_report_row($report, $slug, '', '', 'error', 'Seite konnte nicht angelegt werden: ' . $postId->get_error_message());
                return;
            }
            bioco_import_report_row($report, $slug, '', '', 'create', 'Seite angelegt (post_id=' . $postId . ', ' . count($sectionLabels) . ' Block(e)).');
            foreach ($sectionLabels as $label) {
                bioco_import_report_row($report, $slug, $label, '', 'create', 'Block geschrieben.');
            }
            $existing = get_post($postId);
            bioco_import_ensure_divi_shell($postId, $mode);
        } else {
            bioco_import_report_row($report, $slug, '', '', 'create', 'WÜRDE: Seite anlegen (' . count($sectionLabels) . ' Block(e)).');
            foreach ($sectionLabels as $label) {
                bioco_import_report_row($report, $slug, $label, '', 'create', 'WÜRDE: Block schreiben.');
            }
            $existing = null; // no post yet — SEO comparison below is skipped in dry-run for new pages
        }
        bioco_import_seed_seo($slug, $existing, $seed, $mode, $force, $report);
        return;
    }

    $currentContent = (string) $existing->post_content;

    if ($currentContent === $desiredContent) {
        bioco_import_ensure_divi_shell($existing->ID, $mode);
        bioco_import_report_row($report, $slug, '', '', 'ok-equal', 'Seiteninhalt bereits identisch (post_id=' . $existing->ID . ').');
        foreach ($sectionLabels as $label) {
            bioco_import_report_row($report, $slug, $label, '', 'ok-equal', 'Bereits identisch.');
        }
        bioco_import_seed_seo($slug, $existing, $seed, $mode, $force, $report);
        return;
    }

    $isEmpty = trim($currentContent) === '';

    if (!$isEmpty && !$force) {
        $excerpt = bioco_import_excerpt($currentContent);
        bioco_import_report_row($report, $slug, '', '', 'skip-existing', "Seite hat bereits Inhalt (post_id={$existing->ID}) — CMS/Divi gewinnt (--force zum Überschreiben). Vorhanden: \"{$excerpt}\"");
        foreach ($sectionLabels as $label) {
            bioco_import_report_row($report, $slug, $label, '', 'skip-existing', 'Seite hat bereits Inhalt — nicht überschrieben.');
        }
        bioco_import_seed_seo($slug, $existing, $seed, $mode, $force, $report);
        return;
    }

    $detail = $isEmpty
        ? 'Seite war leer — Inhalt geschrieben (post_id=' . $existing->ID . ').'
        : 'FORCE: bestehender Inhalt überschrieben (post_id=' . $existing->ID . ').';

    if ($mode === 'apply') {
        $updated = wp_update_post(['ID' => $existing->ID, 'post_content' => wp_slash($desiredContent)], true);
        if (is_wp_error($updated)) {
            bioco_import_report_row($report, $slug, '', '', 'error', 'Seite konnte nicht aktualisiert werden: ' . $updated->get_error_message());
            return;
        }
        bioco_import_ensure_divi_shell($existing->ID, $mode);
        bioco_import_report_row($report, $slug, '', '', 'update', $detail);
    } else {
        bioco_import_report_row($report, $slug, '', '', 'update', 'WÜRDE: ' . $detail);
    }
    foreach ($sectionLabels as $label) {
        bioco_import_report_row($report, $slug, $label, '', 'update', ($mode === 'apply' ? '' : 'WÜRDE: ') . 'Block geschrieben.');
    }
    bioco_import_seed_seo($slug, $existing, $seed, $mode, $force, $report);
}

// Entry point used by the CLI command for `wp bioco import`.
function bioco_import_run(array $seeds, $mode, $force, array &$report) {
    foreach ($seeds as $seed) {
        bioco_import_page_for_seed($seed, $mode, $force, $report);
    }
}
