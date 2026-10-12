<?php
/** Preview a native record replacement and export content-only CAS SQL. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/record-layouts.php';

function bioco_record_adoption_value(array $attrs, string $key, $default) {
    if (!isset($attrs[$key])) return $default;
    $field = $attrs[$key];
    if (!is_array($field) || array_diff(array_keys($field), ['innerContent'])) throw new RuntimeException('Unsupported record field: ' . $key);
    $variants = $field['innerContent'] ?? null;
    if (!is_array($variants) || !array_key_exists('desktop', $variants)) throw new RuntimeException('Missing desktop record field: ' . $key);
    $value = $variants['desktop']['value'] ?? null;
    foreach ($variants as $variant) {
        if (!is_array($variant) || array_diff(array_keys($variant), ['value']) || ($variant['value'] ?? null) !== $value) {
            throw new RuntimeException('Distinct responsive record content: ' . $key);
        }
    }
    return $value;
}

function bioco_record_adoption_source(array $leaf, string $kind): array {
    if (!empty($leaf['innerBlocks']) || trim((string) ($leaf['innerHTML'] ?? '')) !== '') throw new RuntimeException('Record leaf has authored markup.');
    $allowed = $kind === 'group' ? ['limit', 'empty_message'] : ['vegetables', 'empty_message', 'vegetable_column_label', 'month_columns'];
    $attrs = $leaf['attrs'] ?? [];
    if (array_diff(array_keys($attrs), array_merge($allowed, ['module', 'builderVersion']))) throw new RuntimeException('Unknown record module field.');
    $metadata = json_decode(file_get_contents(dirname(__DIR__, 2) . '/bioco-core/native-modules/'
        . ($kind === 'group' ? 'group-cards' : 'saisonkalender') . '/module.json'), true, 512, JSON_THROW_ON_ERROR);
    $module = $attrs['module'] ?? [];
    if (!is_array($module) || array_diff(array_keys($module), ['meta', 'advanced', 'decoration'])) throw new RuntimeException('Unknown native module group.');
    foreach ($module as $group => $settings) {
        if (!is_array($settings) || array_diff(array_keys($settings), array_keys($metadata['attributes']['module']['settings'][$group] ?? []))) {
            throw new RuntimeException('Unknown native module control.');
        }
    }
    $values = [];
    foreach ($allowed as $field) $values[$field] = bioco_record_adoption_value($attrs, $field, null);
    if ($kind === 'group' && $values['limit'] !== null && (!is_int($values['limit']) || $values['limit'] < 1 || $values['limit'] > 100)) {
        throw new RuntimeException('Unsupported group limit.');
    }
    if ($kind === 'calendar' && $values['vegetables'] !== null && !is_array($values['vegetables'])) throw new RuntimeException('Invalid archived vegetable rows.');
    foreach (['empty_message', 'vegetable_column_label'] as $field) {
        if (isset($values[$field]) && !is_string($values[$field])) throw new RuntimeException('Invalid text field: ' . $field);
    }
    return ['values' => $values, 'module' => $module];
}

function bioco_record_adoption_class(array &$attrs, string $class): void {
    $attributes =& $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'];
    if (is_array($attributes ?? null)) {
        foreach ($attributes as &$attribute) {
            if (($attribute['name'] ?? '') !== 'class') continue;
            $attribute['value'] = trim((string) ($attribute['value'] ?? '') . ' ' . $class);
            return;
        }
        unset($attribute);
    }
    $html =& $attrs['module']['advanced']['htmlAttributes']['desktop']['value'];
    if (!is_array($html ?? null)) $html = [];
    $html['class'] = trim((string) ($html['class'] ?? '') . ' ' . $class);
}

function bioco_record_adoption_children(array $block): array {
    if (trim((string) ($block['innerHTML'] ?? '')) !== '') throw new RuntimeException('Authored wrapper HTML cannot be moved.');
    foreach ($block['innerContent'] ?? [] as $piece) {
        if ($piece !== null && trim((string) $piece) !== '') throw new RuntimeException('Authored wrapper content cannot be moved.');
    }
    return $block['innerBlocks'] ?? [];
}

function bioco_record_adoption_contains(array $block, string $name): bool {
    if (($block['blockName'] ?? '') === $name) return true;
    foreach ($block['innerBlocks'] ?? [] as $child) if (bioco_record_adoption_contains($child, $name)) return true;
    return false;
}

function bioco_record_adoption_role(array &$attrs, string $role): void {
    $attributes =& $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'];
    if (!is_array($attributes ?? null)) $attributes = [];
    foreach ($attributes as $attribute) if (($attribute['name'] ?? '') === 'role') return;
    $attributes[] = ['id' => 'bioco-adoption-role-' . $role, 'name' => 'role', 'value' => $role, 'adminLabel' => 'Role'];
}

function bioco_record_adoption_named_attribute(array $module, string $name): string {
    foreach ($module['decoration']['attributes']['desktop']['value']['attributes'] ?? [] as $attribute) {
        if (($attribute['name'] ?? '') === $name) return (string) ($attribute['value'] ?? '');
    }
    return (string) ($module['advanced']['htmlAttributes']['desktop']['value'][$name] ?? '');
}

/** Keep both container and old leaf design controls; refuse conflicting anchors. */
function bioco_record_adoption_merge_module(array $container, array $leaf): array {
    foreach (['id'] as $name) {
        $left = bioco_record_adoption_named_attribute($container, $name);
        $right = bioco_record_adoption_named_attribute($leaf, $name);
        if ($left !== '' && $right !== '' && $left !== $right) throw new RuntimeException('Conflicting section and module anchors.');
    }
    $leafAttributes = $leaf['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
    $leafHtml = $leaf['advanced']['htmlAttributes']['desktop']['value'] ?? [];
    unset($leaf['decoration']['attributes'], $leaf['advanced']['htmlAttributes']);
    $merged = array_replace_recursive($container, $leaf);
    $attributes =& $merged['decoration']['attributes']['desktop']['value']['attributes'];
    if (!is_array($attributes ?? null)) $attributes = [];
    foreach ($leafAttributes as $attribute) {
        $name = $attribute['name'] ?? '';
        if (!is_string($name) || $name === '') throw new RuntimeException('Invalid authored module attribute.');
        $existing = null;
        foreach ($attributes as $index => $candidate) if (($candidate['name'] ?? '') === $name) { $existing = $index; break; }
        if ($existing === null) { $attributes[] = $attribute; continue; }
        if ($name === 'class') $attributes[$existing]['value'] = trim((string) ($attributes[$existing]['value'] ?? '') . ' ' . (string) ($attribute['value'] ?? ''));
        elseif ($name !== 'id' && $attributes[$existing]['value'] !== ($attribute['value'] ?? null)) throw new RuntimeException('Conflicting authored module attribute.');
        elseif ($name === 'id' && empty($attributes[$existing]['value'])) $attributes[$existing]['value'] = $attribute['value'] ?? '';
    }
    $html =& $merged['advanced']['htmlAttributes']['desktop']['value'];
    if (!is_array($html ?? null)) $html = [];
    foreach ($leafHtml as $name => $value) {
        if (!isset($html[$name]) || $html[$name] === '') { $html[$name] = $value; continue; }
        if ($name === 'class') $html[$name] = trim((string) $html[$name] . ' ' . (string) $value);
        elseif ($html[$name] !== $value) throw new RuntimeException('Conflicting authored HTML attribute.');
    }
    if (!empty($html['class'])) {
        foreach ($attributes as &$attribute) {
            if (($attribute['name'] ?? '') === 'class') {
                $attribute['value'] = trim((string) $attribute['value'] . ' ' . (string) $html['class']);
                break;
            }
        }
        unset($attribute);
    }
    return $merged;
}

function bioco_record_adoption_replace(array $section, string $kind, array &$report, array $path): array {
    if (($section['blockName'] ?? '') !== 'divi/section') return $section;
    $rows = bioco_record_adoption_children($section);
    if (count($rows) !== 1 || ($rows[0]['blockName'] ?? '') !== 'divi/row') return $section;
    $columns = bioco_record_adoption_children($rows[0]);
    if (count($columns) !== 1 || ($columns[0]['blockName'] ?? '') !== 'divi/column') return $section;
    $leaves = bioco_record_adoption_children($columns[0]);
    if (count($leaves) !== 1 || ($leaves[0]['blockName'] ?? '') !== ($kind === 'group' ? 'bioco-divi/group-cards' : 'bioco-divi/saisonkalender')) return $section;
    $source = bioco_record_adoption_source($leaves[0], $kind);
    $values = $source['values'];
    $empty = $values['empty_message'];
    if ($empty !== null && !is_string($empty)) throw new RuntimeException('Invalid empty message.');
    $definitions = bioco_record_layout_definitions();
    $starter = parse_blocks($definitions[$kind === 'group' ? 'group-cards' : 'calendar']['content']);
    $native = $kind === 'group' ? $starter[0] : $starter[1];
    $nativeRows = $native['innerBlocks'];
    if ($kind === 'group') {
        $row = $nativeRows[0];
        if ($values['limit'] !== null) $row['attrs']['module']['advanced']['loop']['desktop']['value']['postPerPage'] = $values['limit'];
        $nativeEmpty =& $row['innerBlocks'][0]['innerBlocks'][count($row['innerBlocks'][0]['innerBlocks']) - 1];
        if ($empty !== null) $nativeEmpty['attrs']['content']['innerContent']['desktop']['value'] = '<p>' . esc_html($empty) . '</p>';
        $row['attrs'] = array_replace_recursive($row['attrs'], $rows[0]['attrs']);
        bioco_record_adoption_class($row['attrs'], 'bioco-record-loop bioco-group-loop');
        $row['innerBlocks'][0]['attrs'] = array_replace_recursive($row['innerBlocks'][0]['attrs'], $columns[0]['attrs']);
        $nativeRows = [$row];
    } else {
        // One former wrapper becomes two table rows. Only its known imported
        // geometry can be mapped without silently losing a saved row design.
        foreach ([$rows[0], $columns[0]] as $wrapper) {
            $module = $wrapper['attrs']['module'] ?? [];
            if (array_diff(array_keys($wrapper['attrs'] ?? []), ['module', 'builderVersion'])
                || array_diff(array_keys($module), ['meta', 'advanced', 'decoration'])
                || array_diff(array_keys($module['decoration'] ?? []), ['layout', 'attributes'])) {
                throw new RuntimeException('Authored calendar wrapper requires manual conversion.');
            }
            $layout = $module['decoration']['layout']['desktop']['value'] ?? null;
            if ($layout !== null && $layout !== ['display' => 'block']) throw new RuntimeException('Authored calendar wrapper layout requires manual conversion.');
        }
        $headings = $values['month_columns'];
        if ($headings === null) $headings = array_map(static fn($n) => ['number' => $n, 'label' => [1=>'Jan',2=>'Feb',3=>'Mär',4=>'Apr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Dez'][$n]], range(1, 12));
        if (!is_array($headings) || !$headings || !array_is_list($headings)) throw new RuntimeException('Invalid month columns.');
        $numbers = [];
        foreach ($headings as $heading) {
            if (!is_array($heading) || array_diff(array_keys($heading), ['number', 'label'])
                || !is_int($heading['number'] ?? null) || $heading['number'] < 1 || $heading['number'] > 12
                || isset($numbers[$heading['number']]) || !is_string($heading['label'] ?? null)) throw new RuntimeException('Unsupported month heading.');
            $numbers[$heading['number']] = $heading['label'];
        }
        foreach ($nativeRows as $index => &$row) {
            $all = $row['innerBlocks'];
            $ordered = [$all[0]];
            foreach ($numbers as $number => $label) {
                $cell = $all[$number];
                if ($index === 0) $cell['innerBlocks'][0]['attrs']['content']['innerContent']['desktop']['value'] = esc_html($label);
                $ordered[] = $cell;
            }
            $row['innerBlocks'] = $ordered;
            $row['innerContent'] = array_fill(0, count($ordered), null);
            $row['attrs']['module']['advanced']['columnStructure']['desktop']['value'] = implode(',', array_fill(0, count($ordered), '4_4'));
            $row['attrs']['module']['decoration']['layout']['desktop']['value']['gridTemplateColumns'] = 'minmax(180px, 2fr) repeat(' . count($numbers) . ', minmax(54px, 1fr))';
        }
        unset($row);
        if ($values['vegetable_column_label'] !== null) $nativeRows[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['content']['innerContent']['desktop']['value'] = esc_html($values['vegetable_column_label']);
        if ($empty !== null) $nativeRows[1]['innerBlocks'][0]['innerBlocks'][1]['attrs']['content']['innerContent']['desktop']['value'] = '<p>' . esc_html($empty) . '</p>';
        $headerLayout = $nativeRows[0]['attrs']['module']['decoration']['layout']['desktop']['value'];
        $nativeRows[0]['attrs'] = array_replace_recursive($nativeRows[0]['attrs'], $rows[0]['attrs']);
        $headLayout =& $nativeRows[0]['attrs']['module']['decoration']['layout']['desktop']['value'];
        $headLayout = array_replace($headLayout, $headerLayout);
        $nativeRows[0]['attrs']['module']['advanced']['columnStructure']['desktop']['value'] = implode(',', array_fill(0, count($nativeRows[0]['innerBlocks']), '4_4'));
        bioco_record_adoption_class($nativeRows[0]['attrs'], 'bioco-calendar-head');
        bioco_record_adoption_role($nativeRows[0]['attrs'], 'row');
        $nativeRows[0]['innerBlocks'][0]['attrs'] = array_replace_recursive($nativeRows[0]['innerBlocks'][0]['attrs'], $columns[0]['attrs']);
        bioco_record_adoption_class($nativeRows[0]['innerBlocks'][0]['attrs'], 'bioco-calendar-name');
        bioco_record_adoption_role($nativeRows[0]['innerBlocks'][0]['attrs'], 'columnheader');
    }
    $savedLayout = $section['attrs']['module']['decoration']['layout']['desktop']['value'] ?? null;
    $section['attrs'] = array_replace_recursive($native['attrs'], $section['attrs']);
    if ($kind === 'group') {
        if ($savedLayout !== null && $savedLayout !== ['display' => 'block'] && $savedLayout !== $native['attrs']['module']['decoration']['layout']['desktop']['value']) {
            throw new RuntimeException('Authored group section layout requires manual conversion.');
        }
        $section['attrs']['module']['decoration']['layout'] = $native['attrs']['module']['decoration']['layout'];
    }
    // Module-level Divi design and anchor settings belonged to the old visual root.
    $section['attrs']['module'] = bioco_record_adoption_merge_module($section['attrs']['module'] ?? [], $source['module']);
    bioco_record_adoption_class($section['attrs'], $kind === 'group' ? 'bioco-record-groups' : 'bioco-native-calendar');
    if ($kind === 'calendar') bioco_record_adoption_role($section['attrs'], 'table');
    $section['innerBlocks'] = $nativeRows;
    $section['innerContent'] = array_fill(0, count($nativeRows), null);
    $section['innerHTML'] = '';
    $report[] = ['path' => $path, 'action' => 'convert', 'kind' => $kind, 'archived_vegetable_rows' => $kind === 'calendar' ? count($values['vegetables'] ?? []) : 0];
    return $section;
}

function bioco_record_adoption_tree(array $blocks, string $kind, array &$report, array $path = []): array {
    foreach ($blocks as $index => &$block) {
        $at = [...$path, $index];
        $target = $kind === 'group' ? 'bioco-divi/group-cards' : 'bioco-divi/saisonkalender';
        if (($block['blockName'] ?? '') === 'divi/section' && bioco_record_adoption_contains($block, $target)) {
            $converted = count($report);
            $block = bioco_record_adoption_replace($block, $kind, $report, $at);
            if (count($report) !== $converted + 1) throw new RuntimeException('Record leaf shares an unsupported section.');
        }
        elseif (!empty($block['innerBlocks'])) $block['innerBlocks'] = bioco_record_adoption_tree($block['innerBlocks'], $kind, $report, $at);
    }
    unset($block);
    return $blocks;
}

function bioco_record_adoption_plan(array $record, string $site, string $kind): array {
    if (!in_array($kind, ['group', 'calendar'], true) || $site !== home_url('/')) throw new RuntimeException('Invalid site or record migration kind.');
    $columns = ['ID', 'post_type', 'post_status', 'post_title', 'post_name', 'post_content', 'post_modified_gmt'];
    if (array_diff(array_keys($record), $columns) || array_diff($columns, array_keys($record))
        || !is_int($record['ID']) || $record['ID'] < 1 || $record['post_type'] !== 'page' || $record['post_status'] !== 'publish') throw new RuntimeException('Expected one complete published page snapshot.');
    foreach (array_diff($columns, ['ID']) as $column) if (!is_string($record[$column])) throw new RuntimeException('Invalid page snapshot.');
    if ($record['post_name'] !== ($kind === 'group' ? 'mitmachen' : 'gemuese')) throw new RuntimeException('Unexpected page slug.');
    $report = [];
    $blocks = bioco_record_adoption_tree(parse_blocks($record['post_content']), $kind, $report);
    if (count($report) !== 1) throw new RuntimeException('Expected exactly one owned record section.');
    $plan = ['version' => 1, 'site' => $site, 'kind' => $kind, 'before' => $record,
        'after_content' => serialize_blocks($blocks), 'report' => $report];
    $plan['fingerprint'] = hash('sha256', json_encode($plan));
    return $plan;
}

function bioco_record_adoption_sql(array $plan, string $expected, bool $restore = false): string {
    global $wpdb;
    if (($plan['version'] ?? null) !== 1 || !isset($plan['before']) || !is_array($plan['before'])) throw new RuntimeException('Invalid record adoption plan.');
    $verified = bioco_record_adoption_plan($plan['before'], $plan['site'] ?? '', $plan['kind'] ?? '');
    if ($verified !== $plan || !hash_equals($verified['fingerprint'], $expected)) throw new RuntimeException('Record adoption preview changed.');
    $guards = [];
    foreach ($plan['before'] as $column => $value) {
        if ($restore && $column === 'post_content') $value = $plan['after_content'];
        $guards[] = $wpdb->prepare("BINARY `$column` = BINARY %s", (string) $value);
    }
    $content = $restore ? $plan['before']['post_content'] : $plan['after_content'];
    return $wpdb->remove_placeholder_escape($wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = %s WHERE ", $content) . implode(' AND ', $guards));
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('bioco record-adoption', function ($args, $flags) {
        $kind = $flags['kind'] ?? '';
        $id = filter_var($flags['id'] ?? '', FILTER_VALIDATE_INT);
        if (!$id || !in_array($kind, ['group', 'calendar'], true)) WP_CLI::error('Provide --kind=group|calendar and --id=<published-page-id>.');
        $post = get_post($id);
        if (!$post) WP_CLI::error('Page does not exist.');
        $snapshot = [];
        foreach (['ID', 'post_type', 'post_status', 'post_title', 'post_name', 'post_content', 'post_modified_gmt'] as $field) $snapshot[$field] = $post->$field;
        try {
            $plan = bioco_record_adoption_plan($snapshot, home_url('/'), $kind);
            WP_CLI::line(wp_json_encode(['plan' => $plan, 'apply_sql' => bioco_record_adoption_sql($plan, $plan['fingerprint']),
                'restore_sql' => bioco_record_adoption_sql($plan, $plan['fingerprint'], true)], JSON_UNESCAPED_UNICODE));
        } catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
    }, ['shortdesc' => 'Export a read-only, conditional native record adoption plan. Redirect output to a private file.',
        'synopsis' => [['type' => 'assoc', 'name' => 'kind', 'optional' => false],
            ['type' => 'assoc', 'name' => 'id', 'optional' => false]], 'when' => 'after_wp_load']);
}
