<?php
/**
 * Document import (issue #151). Seed hrefs starting with "documents/" point
 * at PDFs shipped in content-seed/documents/. Each such href — in button
 * hrefs as well as in rich-text HTML links — is rewritten to the
 * media-library attachment URL: the file is sideloaded once, tagged with
 * _bioco_import_source_url = "documents/<datei>" and reused on every re-run,
 * so an unchanged re-run stays write-free. Dry-run and verify never
 * download; verify only reuses an already-imported attachment.
 */

if (!defined('ABSPATH')) exit;

const BIOCO_IMPORT_DOCUMENTS_PREFIX = 'documents/';

function bioco_import_documents_dir(array $seed): string {
    return rtrim((string) ($seed['_bioco_seed_dir'] ?? ''), '/') . '/' . BIOCO_IMPORT_DOCUMENTS_PREFIX;
}

// Resolves one shipped document to an attachment ID. Reuses an attachment
// imported from the same documents path (tagged via _bioco_import_source_url)
// so re-runs never re-import the same PDF. Returns null when the attachment
// does not exist yet and $mode is not 'apply' (dry-run/verify never import).
function bioco_import_resolve_document_for_path(array $seed, string $relativePath, $mode) {
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'any',
        'numberposts' => 1,
        'meta_key' => '_bioco_import_source_url',
        'meta_value' => $relativePath,
    ]);
    if ($existing) return (int) $existing[0]->ID;

    if ($mode !== 'apply') return null;

    $file = bioco_import_documents_dir($seed) . basename($relativePath);
    if (!is_file($file)) return null;

    if (!function_exists('media_handle_sideload')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $tmp = wp_tempnam(basename($relativePath));
    if ($tmp === false || !copy($file, $tmp)) return null;
    $attachmentId = media_handle_sideload([
        'name' => basename($relativePath),
        'tmp_name' => $tmp,
        'size' => filesize($tmp),
    ], 0);
    if (is_wp_error($attachmentId)) {
        wp_delete_file($tmp);
        return null;
    }
    update_post_meta($attachmentId, '_bioco_import_source_url', $relativePath);
    return (int) $attachmentId;
}

// Rewrites every "documents/…" href inside $values (button hrefs and links
// in rich-text HTML) to the media-library attachment URL. Unresolved paths
// stay as-is so a dry-run plan remains deterministic and verify reports a
// clean mismatch instead of half-rewritten markup.
function bioco_import_resolve_pending_documents(array &$values, array $seed, $mode, array &$warnings) {
    foreach ($values as $key => $value) {
        if (is_array($value)) {
            $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
            bioco_import_resolve_pending_documents($value, $seed, $mode, $warnings);
            if ($isList) $value = array_values($value);
            $values[$key] = $value;
            continue;
        }
        if (!is_string($value)) continue;

        $rewrite = static function (array $m) use ($seed, $mode, &$warnings) {
            $path = BIOCO_IMPORT_DOCUMENTS_PREFIX . basename($m[1]);
            $attachmentId = bioco_import_resolve_document_for_path($seed, $path, $mode);
            if ($attachmentId === null) {
                $warnings[] = ($mode === 'apply')
                    ? "Dokument-Import fehlgeschlagen: {$path} — Verweis bleibt unverändert."
                    : "WÜRDE: Dokument importieren: {$path} (Feld '{$m[0]}').";
                return $m[0];
            }
            return str_replace($m[1], (string) wp_get_attachment_url($attachmentId), $m[0]);
        };

        if ($key === 'href' && strpos($value, BIOCO_IMPORT_DOCUMENTS_PREFIX) === 0) {
            $values[$key] = preg_replace_callback('/^(documents\/[A-Za-z0-9._-]+)$/', $rewrite, $value) ?? $value;
            continue;
        }
        if (strpos($value, 'href="' . BIOCO_IMPORT_DOCUMENTS_PREFIX) !== false) {
            $values[$key] = preg_replace_callback('/href="(documents\/[A-Za-z0-9._-]+)"/', $rewrite, $value) ?? $value;
        }
    }
}