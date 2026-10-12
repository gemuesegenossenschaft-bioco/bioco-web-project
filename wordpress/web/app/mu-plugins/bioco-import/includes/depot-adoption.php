<?php
/** Preview-only depot card insertion; map behavior remains in the saved module. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/depot-layouts.php';
require_once __DIR__ . '/record-layout-adoption.php';

function bioco_depot_adoption_source(array $leaf): array {
    if (($leaf['blockName'] ?? '') !== 'bioco-divi/depot-map'
        || !empty($leaf['innerBlocks']) || trim((string) ($leaf['innerHTML'] ?? '')) !== '') {
        throw new RuntimeException('Expected one unmodified depot map leaf.');
    }
    $metadata = json_decode(file_get_contents(dirname(__DIR__, 2) . '/bioco-core/native-modules/depot-map/module.json'), true, 512, JSON_THROW_ON_ERROR);
    $attrs = $leaf['attrs'] ?? [];
    // Existing saved maps retain the pre-catalog local rows even though the
    // current editor schema no longer exposes that field. Keep them intact.
    $allowed = array_unique([...array_diff(array_keys($metadata['attributes']), ['module']), 'locations']);
    if (array_diff(array_keys($attrs), array_merge($allowed, ['module', 'builderVersion']))) throw new RuntimeException('Unknown depot map field.');
    $module = $attrs['module'] ?? [];
    if (!is_array($module) || array_diff(array_keys($module), ['meta', 'advanced', 'decoration'])) throw new RuntimeException('Unknown depot map control group.');
    foreach ($module as $group => $settings) {
        if (!is_array($settings) || array_diff(array_keys($settings), array_keys($metadata['attributes']['module']['settings'][$group] ?? []))) {
            throw new RuntimeException('Unknown depot map control.');
        }
    }
    foreach ($allowed as $field) bioco_record_adoption_value($attrs, $field, null);
    $copy = [];
    foreach (['intro', 'locations_heading', 'route_label', 'empty_message', 'card_order'] as $field) {
        $copy[$field] = bioco_record_adoption_value($attrs, $field, null);
    }
    if ($copy['intro'] === null) $copy['intro'] = '';
    if ($copy['locations_heading'] === null) $copy['locations_heading'] = 'Standorte';
    if ($copy['route_label'] === null) $copy['route_label'] = 'Route planen →';
    if ($copy['empty_message'] === null) $copy['empty_message'] = 'Noch keine Standorte hinterlegt.';
    if ($copy['card_order'] === null) $copy['card_order'] = 'name_first';
    $show = bioco_record_adoption_value($attrs, 'show_address_list', true);
    if (!in_array($show, [true, false], true)) throw new RuntimeException('Invalid address-list state.');
    return $copy;
}

function bioco_depot_adoption_markers(array $content, int $children, array $insertions): array {
    $result = []; $child = 0;
    foreach ($content as $piece) {
        if ($piece === null) for ($i = 0; $i < ($insertions[$child]['before'] ?? 0); $i++) $result[] = null;
        $result[] = $piece;
        if ($piece !== null) continue;
        for ($i = 0; $i < ($insertions[$child]['after'] ?? 0); $i++) $result[] = null;
        $child++;
    }
    if ($child !== $children) throw new RuntimeException('Opaque parent block markers cannot be mapped.');
    return $result;
}

function bioco_depot_adoption_tree(array $blocks, array &$report, array $path = [], array &$insertions = []): array {
    $result = [];
    foreach ($blocks as $index => $block) {
        $at = [...$path, $index];
        if (($block['blockName'] ?? '') === 'divi/section' && bioco_record_adoption_contains($block, 'bioco-divi/depot-map')) {
            $rows = bioco_record_adoption_children($block);
            if (count($rows) !== 1 || ($rows[0]['blockName'] ?? '') !== 'divi/row') throw new RuntimeException('Depot map shares a section.');
            $columns = bioco_record_adoption_children($rows[0]);
            if (count($columns) !== 1 || ($columns[0]['blockName'] ?? '') !== 'divi/column') throw new RuntimeException('Depot map shares a row.');
            $leaves = bioco_record_adoption_children($columns[0]);
            if (count($leaves) !== 1) throw new RuntimeException('Depot map shares a column.');
            $copy = bioco_depot_adoption_source($leaves[0]);
            // Only presentation fragments move; functional marker/view settings and
            // the module's saved ID stay on this same map leaf.
            $leaves[0]['attrs']['intro'] = ['innerContent' => ['desktop' => ['value' => '']]];
            $leaves[0]['attrs']['show_address_list'] = ['innerContent' => ['desktop' => ['value' => false]]];
            $columns[0]['innerBlocks'] = $leaves;
            $rows[0]['innerBlocks'] = $columns;
            $block['innerBlocks'] = $rows;
            [$intro, $heading, $cards] = bioco_depot_card_blocks($copy);
            $result[] = $intro;
            $result[] = $block;
            $result[] = $heading;
            $result[] = $cards;
            $insertions[$index] = ['before' => 1, 'after' => 2];
            $report[] = ['path' => $at, 'action' => 'insert-native-cards',
                'preserved_map_id' => bioco_record_adoption_named_attribute($leaves[0]['attrs']['module'] ?? [], 'id'),
                'archived_local_rows' => count(bioco_record_adoption_value($leaves[0]['attrs'], 'locations', []))];
        } else {
            if (!empty($block['innerBlocks'])) {
                $nested = [];
                $original = count($block['innerBlocks']);
                $block['innerBlocks'] = bioco_depot_adoption_tree($block['innerBlocks'], $report, $at, $nested);
                if ($nested) $block['innerContent'] = bioco_depot_adoption_markers($block['innerContent'], $original, $nested);
            }
            $result[] = $block;
        }
    }
    return $result;
}

function bioco_depot_adoption_plan(array $record, string $site): array {
    if ($site !== home_url('/')) throw new RuntimeException('Snapshot belongs to another site.');
    $columns = ['ID', 'post_type', 'post_status', 'post_title', 'post_name', 'post_content', 'post_modified_gmt'];
    if (array_diff(array_keys($record), $columns) || array_diff($columns, array_keys($record))
        || !is_int($record['ID']) || $record['ID'] < 1 || $record['post_type'] !== 'page'
        || $record['post_status'] !== 'publish' || $record['post_name'] !== 'standorte-depots') {
        throw new RuntimeException('Expected one complete published depot page snapshot.');
    }
    foreach (array_diff($columns, ['ID']) as $column) if (!is_string($record[$column])) throw new RuntimeException('Invalid depot snapshot value.');
    $report = [];
    $blocks = bioco_depot_adoption_tree(parse_blocks($record['post_content']), $report);
    if (count($report) !== 1) throw new RuntimeException('Expected exactly one depot map section.');
    $plan = ['version' => 1, 'site' => $site, 'before' => $record, 'after_content' => serialize_blocks($blocks), 'report' => $report];
    $plan['fingerprint'] = hash('sha256', json_encode($plan));
    return $plan;
}

function bioco_depot_adoption_sql(array $plan, string $expected, bool $restore = false): string {
    global $wpdb;
    if (($plan['version'] ?? null) !== 1 || !isset($plan['before']) || !is_array($plan['before'])) throw new RuntimeException('Invalid depot adoption plan.');
    $verified = bioco_depot_adoption_plan($plan['before'], $plan['site'] ?? '');
    if ($verified !== $plan || !hash_equals($verified['fingerprint'], $expected)) throw new RuntimeException('Depot adoption preview changed.');
    $guards = [];
    foreach ($plan['before'] as $column => $value) {
        if ($restore && $column === 'post_content') $value = $plan['after_content'];
        $guards[] = $wpdb->prepare("BINARY `$column` = BINARY %s", (string) $value);
    }
    $content = $restore ? $plan['before']['post_content'] : $plan['after_content'];
    return $wpdb->remove_placeholder_escape($wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = %s WHERE ", $content) . implode(' AND ', $guards));
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('bioco depot-adoption', function ($args, $flags) {
        $id = filter_var($flags['id'] ?? '', FILTER_VALIDATE_INT);
        if (!$id) WP_CLI::error('Provide --id=<published-depot-page-id>.');
        $post = get_post($id);
        if (!$post) WP_CLI::error('Page does not exist.');
        $snapshot = [];
        foreach (['ID', 'post_type', 'post_status', 'post_title', 'post_name', 'post_content', 'post_modified_gmt'] as $field) $snapshot[$field] = $post->$field;
        try {
            $plan = bioco_depot_adoption_plan($snapshot, home_url('/'));
            WP_CLI::line(wp_json_encode(['plan' => $plan, 'apply_sql' => bioco_depot_adoption_sql($plan, $plan['fingerprint']),
                'restore_sql' => bioco_depot_adoption_sql($plan, $plan['fingerprint'], true)], JSON_UNESCAPED_UNICODE));
        } catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
    }, ['shortdesc' => 'Export a read-only depot map/card adoption plan.',
        'synopsis' => [['type' => 'assoc', 'name' => 'id', 'optional' => false]], 'when' => 'after_wp_load']);
}
