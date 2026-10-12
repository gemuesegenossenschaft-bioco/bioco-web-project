<?php
/** Preview-only saved-body conversion and conditional SQL; no writes or assignments. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/article-back-button.php';

function bioco_import_back_button_binding($value, string $field): bool {
    if (!is_string($value) || !str_starts_with($value, '$variable(') || !str_ends_with($value, ')$')) return false;
    $binding = json_decode(substr($value, 10, -2), true);
    return ($binding['type'] ?? '') === 'content' && ($binding['value']['name'] ?? '') === 'bioco_event_' . $field;
}

/** A known event-body column retains both record bindings, even after design edits. */
function bioco_import_back_button_event_column(array $block): bool {
    if (($block['blockName'] ?? '') !== 'divi/column') return false;
    $title = false; $description = false;
    foreach ($block['innerBlocks'] ?? [] as $child) {
        $title = $title || bioco_import_back_button_binding($child['attrs']['title']['innerContent']['desktop']['value'] ?? null, 'title');
        $description = $description || bioco_import_back_button_binding($child['attrs']['content']['innerContent']['desktop']['value'] ?? null, 'description');
    }
    return $title && $description;
}

/** Presets, style IDs and custom CSS anywhere in saved attributes are deliberate. */
function bioco_import_back_button_has_style(array $attrs): bool {
    foreach ($attrs as $key => $value) {
        if (preg_match('/preset|style|css/i', (string) $key) && !empty($value)) return true;
        if (is_array($value) && bioco_import_back_button_has_style($value)) return true;
    }
    return false;
}

function bioco_import_back_button_tree(array $blocks, array &$report, array $path = [], bool $event_column = false): array {
    foreach ($blocks as $index => &$block) {
        $at = [...$path, $index];
        $attrs = $block['attrs'] ?? [];
        $class = $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['class'] ?? '';
        $classes = is_string($class) ? preg_split('/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY) : ['unsupported'];
        $label = $attrs['module']['meta']['adminLabel']['desktop']['value'] ?? '';
        $content = $attrs['button']['innerContent']['desktop']['value'] ?? [];
        if ($event_column && ($block['blockName'] ?? '') === 'divi/button'
            && ($label === 'Zurück zu Aktuelles' || in_array('bioco-article-back', $classes, true))
            && is_array($content) && is_string($content['text'] ?? null) && is_string($content['linkUrl'] ?? null)) {
            $reason = '';
            if (bioco_import_back_button_has_style($attrs)) $reason = 'saved-preset-or-style';
            elseif (!empty($attrs['button']['decoration'])) $reason = 'saved-button-design';
            elseif (array_diff(array_keys($attrs['module']['decoration'] ?? []), ['spacing'])) $reason = 'saved-module-design';
            elseif (array_diff($classes, ['bioco-article-back'])) $reason = 'custom-css-class';
            elseif (isset($attrs['module']['advanced']['htmlAttributes']['desktop']['value']['class']) && $class === '') $reason = 'explicit-empty-class';
            elseif (array_diff(array_keys($attrs['module']['advanced']['htmlAttributes'] ?? []), ['desktop'])
                || array_diff(array_keys($attrs['module']['advanced']['htmlAttributes']['desktop']['value'] ?? []), ['class'])) $reason = 'authored-html-attributes';
            elseif (!empty($block['innerBlocks'])) $reason = 'nested-button-content';
            if ($reason !== '') {
                $report[] = ['path' => $at, 'action' => 'preserve', 'reason' => $reason];
            } else {
                // Saved data wins every merge. Only absent visual defaults are added.
                $native = bioco_import_article_back_button();
                $block['attrs'] = array_replace_recursive($native['attrs'], $attrs);
                $report[] = ['path' => $at, 'action' => 'convert'];
            }
        }
        if (!empty($block['innerBlocks'])) {
            $context = ($block['blockName'] ?? '') === 'divi/column'
                ? bioco_import_back_button_event_column($block) : $event_column;
            $block['innerBlocks'] = bioco_import_back_button_tree($block['innerBlocks'], $report, $at, $context);
        }
    }
    unset($block);
    return $blocks;
}

function bioco_import_back_button_content(string $content): array {
    $report = [];
    $blocks = bioco_import_back_button_tree(parse_blocks($content), $report);
    $changed = count(array_filter($report, static fn(array $row): bool => $row['action'] === 'convert'));
    return ['content' => $changed ? serialize_blocks($blocks) : $content, 'changed' => $changed, 'report' => $report];
}

/** One explicitly selected snapshot; never discover or update every matching body. */
function bioco_import_back_button_plan(array $record, string $site): array {
    $columns = ['ID', 'post_type', 'post_status', 'post_title', 'post_content', 'post_modified_gmt'];
    if (array_diff(array_keys($record), $columns) || array_diff($columns, array_keys($record))
        || !is_int($record['ID']) || $record['ID'] < 1 || $record['post_type'] !== 'et_body_layout') {
        throw new RuntimeException('Expected one complete saved body snapshot.');
    }
    foreach (array_diff($columns, ['ID']) as $column) {
        if (!is_string($record[$column])) throw new RuntimeException('Invalid saved body value.');
    }
    if ($site !== home_url('/')) throw new RuntimeException('Snapshot belongs to another site.');
    $converted = bioco_import_back_button_content($record['post_content']);
    $plan = ['version' => 1, 'site' => $site, 'before' => $record,
        'after_content' => $converted['content'], 'changed' => $converted['changed'], 'report' => $converted['report']];
    $plan['fingerprint'] = hash('sha256', json_encode($plan));
    return $plan;
}

/** Generate an atomic content-only apply/restore; the operator owns execution and cache refresh. */
function bioco_import_back_button_sql(array $plan, string $expected, bool $restore = false): string {
    global $wpdb;
    if (($plan['version'] ?? 0) !== 1 || !isset($plan['before']) || !is_array($plan['before'])) {
        throw new RuntimeException('Invalid back-button plan.');
    }
    $verified = bioco_import_back_button_plan($plan['before'], $plan['site'] ?? '');
    if ($plan !== $verified || !hash_equals($verified['fingerprint'], $expected)) {
        throw new RuntimeException('Back-button preview changed.');
    }
    if (!$plan['changed']) return '';
    $guards = [];
    foreach ($plan['before'] as $column => $value) {
        if ($restore && $column === 'post_content') $value = $plan['after_content'];
        $guards[] = $wpdb->prepare("BINARY `$column` = BINARY %s", (string) $value);
    }
    $content = $restore ? $plan['before']['post_content'] : $plan['after_content'];
    return $wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = %s WHERE ", $content) . implode(' AND ', $guards);
}
