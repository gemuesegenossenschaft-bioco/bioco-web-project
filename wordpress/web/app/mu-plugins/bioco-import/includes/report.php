<?php
/**
 * Shared report accumulator + renderers. Same spirit as
 * site/templates/migrate-content-freeze.php's report: one row per
 * page/section/field with a status, a running count per status, a plain-text
 * log, and an HTML file for a human to review — just emitted for WP-CLI
 * (stdout table) instead of an HTTP response.
 */

if (!defined('ABSPATH')) exit;

// Fresh, empty report accumulator. Passed by reference into every import/
// verify/collections/site-wiring function below.
function bioco_import_report_new() {
    return ['rows' => [], 'counts' => []];
}

function bioco_import_report_row(array &$report, $page, $section, $field, $status, $detail = '') {
    $report['rows'][] = [
        'page' => (string) $page,
        'section' => (string) $section,
        'field' => (string) $field,
        'status' => (string) $status,
        'detail' => (string) $detail,
    ];
    $report['counts'][$status] = ($report['counts'][$status] ?? 0) + 1;
}

function bioco_import_report_has_failures(array $report) {
    foreach (['error', 'verify-mismatch', 'verify-missing'] as $failStatus) {
        if (!empty($report['counts'][$failStatus])) return true;
    }
    return false;
}

function bioco_import_report_summary_line(array $report, $mode, $force = false) {
    $parts = [];
    foreach ($report['counts'] as $status => $n) {
        $parts[] = "{$status}={$n}";
    }
    sort($parts);
    return 'mode=' . $mode . ($force ? ' force=1' : '') . ' | ' . implode(', ', $parts);
}

// Prints the report as a WP-CLI table plus a summary line. Kept deliberately
// plain (WP_CLI::log / a manual table) rather than WP_CLI\Utils\format_items
// so column widths stay readable for long detail strings in a terminal.
function bioco_import_report_print_cli(array $report, $mode, $force = false) {
    WP_CLI::log('');
    foreach ($report['rows'] as $r) {
        $line = sprintf(
            '%-16s %-20s %-28s %-14s %s',
            $r['status'],
            mb_strimwidth($r['page'], 0, 20, ''),
            mb_strimwidth($r['section'], 0, 28, ''),
            mb_strimwidth($r['field'], 0, 14, ''),
            $r['detail']
        );
        WP_CLI::log($line);
    }
    WP_CLI::log('');
    WP_CLI::log(bioco_import_report_summary_line($report, $mode, $force));
}

// Writes an HTML report (same badge/table style as migrate-content-freeze.php)
// under wp-content/bioco-import-log/ and returns the file path, or null if it
// could not be written (never fatal — the CLI table output is authoritative).
function bioco_import_report_write_html(array $report, $mode, $force = false) {
    $dir = WP_CONTENT_DIR . '/bioco-import-log';
    if (!is_dir($dir)) {
        if (!wp_mkdir_p($dir)) return null;
    }
    $stamp = gmdate('Y-m-d_His');
    $path = $dir . "/{$mode}-{$stamp}.html";

    $h = function ($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    };
    $colors = [
        'error' => '#9f1239', 'verify-mismatch' => '#9f1239', 'verify-missing' => '#b45309',
        'warn' => '#b45309', 'skip-existing' => '#475569', 'ok-equal' => '#1c6b2d',
        'verify-match' => '#1c6b2d', 'update' => '#1d4ed8', 'create' => '#7c3aed',
        'info' => '#475569',
    ];
    $badge = function ($status) use ($h, $colors) {
        $c = $colors[$status] ?? '#334155';
        return '<span style="color:' . $c . ';font-weight:600;white-space:nowrap">' . $h($status) . '</span>';
    };

    $html = '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>bioco Import</title>';
    $html .= '<style>body{font:14px/1.5 -apple-system,Segoe UI,sans-serif;margin:24px;color:#1a1a1a}table{border-collapse:collapse;width:100%}th,td{border:1px solid #e2e8f0;padding:4px 8px;text-align:left;vertical-align:top}th{background:#f1f5f9}tr:nth-child(even){background:#fafafa}code{background:#f1f5f9;padding:1px 4px;border-radius:3px}</style></head><body>';
    $html .= '<h1>bioco Import</h1>';
    $html .= '<p><strong>' . $h(bioco_import_report_summary_line($report, $mode, $force)) . '</strong></p>';
    if ($mode === 'dry-run') {
        $html .= '<p>Nur Bericht — es wurde <strong>nichts geschrieben</strong>. Ausführen mit <code>--mode=apply</code>.</p>';
    }
    $html .= '<table><thead><tr><th>Seite</th><th>Section</th><th>Feld</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
    foreach ($report['rows'] as $r) {
        $html .= '<tr><td>' . $h($r['page']) . '</td><td>' . $h($r['section']) . '</td><td><code>' . $h($r['field']) . '</code></td><td>' . $badge($r['status']) . '</td><td>' . $h($r['detail']) . '</td></tr>';
    }
    $html .= '</tbody></table>';
    $hasFailures = bioco_import_report_has_failures($report);
    $html .= '<p style="margin-top:16px">' . ($hasFailures ? '<strong style="color:#9f1239">Mit Fehlern/Abweichungen beendet.</strong>' : '<strong style="color:#1c6b2d">OK.</strong>') . '</p>';
    $html .= '</body></html>';

    if (file_put_contents($path, $html) === false) return null;
    return $path;
}

// Writes every row to the WordPress debug log (same idea as PW's
// wire('log')->save('content-freeze', ...)) so a run is auditable even
// without keeping the HTML report file around.
function bioco_import_report_write_log(array $report, $mode, $force = false) {
    if (!function_exists('error_log')) return;
    error_log('[bioco-import] START mode=' . $mode . ($force ? ' force=1' : ''));
    foreach ($report['rows'] as $r) {
        if (!in_array($r['status'], ['error', 'warn', 'create', 'update', 'verify-mismatch', 'verify-missing'], true)) continue;
        error_log(sprintf(
            '[bioco-import] %s [%s%s%s] %s',
            strtoupper($r['status']),
            $r['page'],
            $r['section'] !== '' ? "#{$r['section']}" : '',
            $r['field'] !== '' ? "/{$r['field']}" : '',
            $r['detail']
        ));
    }
    error_log('[bioco-import] DONE ' . bioco_import_report_summary_line($report, $mode, $force));
}
