<?php
/** Explicit saved-shell preview and conditional SQL. Never writes or assigns layouts. */
if (!defined('ABSPATH')) exit;

function bioco_shell_wrapper_volatile_meta(): array {
    return ['_edit_lock', '_edit_last', '_divi_dynamic_assets_cached_modules',
        '_divi_dynamic_assets_canvases_used', '_divi_dynamic_assets_cached_feature_used',
        '_et_builder_post_features_cache'];
}

function bioco_shell_wrapper_preset_options(): array {
    return ['et_divi_builder_global_presets_d5', 'et_divi_builder_global_presets_ng', 'et_divi_global_variables'];
}

function bioco_shell_wrapper_post_columns(): array {
    return ['ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title',
        'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name',
        'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered',
        'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type', 'comment_count'];
}

function bioco_shell_wrapper_classes(array $attrs): array {
    $class = $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['class'] ?? '';
    foreach ($attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [] as $attribute) {
        if (($attribute['name'] ?? '') === 'class') $class .= ' ' . ($attribute['value'] ?? '');
    }
    return preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY);
}

function bioco_shell_wrapper_style_reference(array $attrs): bool {
    foreach ($attrs as $key => $value) {
        if (preg_match('/preset|style|css/i', (string) $key) && !empty($value)) return true;
        if (is_array($value) && bioco_shell_wrapper_style_reference($value)) return true;
    }
    return false;
}

function bioco_shell_wrapper_authored_selector(array $attrs, array $allowed): bool {
    foreach ($attrs['module']['advanced']['htmlAttributes'] ?? [] as $state) {
        $value = $state['value'] ?? [];
        if (!empty($value['id']) || array_diff(preg_split('/\s+/', trim($value['class'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), $allowed)) return true;
    }
    foreach ($attrs['module']['decoration']['attributes'] ?? [] as $state) {
        foreach ($state['value']['attributes'] ?? [] as $attribute) {
            if (($attribute['name'] ?? '') === 'id' && !empty($attribute['value'])) return true;
            if (($attribute['name'] ?? '') === 'class'
                && array_diff(preg_split('/\s+/', trim($attribute['value'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), $allowed)) return true;
        }
    }
    return false;
}

/** A layout domain is adopted only when no authored value/state exists in it. */
function bioco_shell_wrapper_defaults(array &$block, array $path, string $slot, array $presets, array &$report): void {
    $attrs = $block['attrs'] ?? [];
    $name = $block['blockName'];
    $allowed = $name === 'divi/section' ? ['bioco-global-layout', 'bioco-global-' . $slot] : [];
    $reason = '';
    if (bioco_shell_wrapper_style_reference($attrs)) $reason = 'authored-preset-or-style';
    elseif (!empty($presets['module'][$name]['default'])) $reason = 'global-default-preset';
    elseif (bioco_shell_wrapper_authored_selector($attrs, $allowed)) $reason = 'authored-selector';
    if ($reason !== '') {
        $report[] = ['path' => $path, 'action' => 'preserve', 'reason' => $reason];
        return;
    }
    foreach (['sizing' => ['width' => '100%', 'maxWidth' => 'none'],
        'spacing' => ['margin' => array_fill_keys(['top', 'right', 'bottom', 'left'], '0px'),
                      'padding' => array_fill_keys(['top', 'right', 'bottom', 'left'], '0px')]] as $domain => $values) {
        if (array_key_exists($domain, $attrs['module']['decoration'] ?? [])) {
            $report[] = ['path' => $path, 'domain' => $domain, 'action' => 'preserve', 'reason' => 'authored-domain'];
            continue;
        }
        $block['attrs']['module']['decoration'][$domain] = ['desktop' => ['value' => $values]];
        $report[] = ['path' => $path, 'domain' => $domain, 'action' => 'adopt', 'values' => $values];
    }
}

function bioco_shell_wrapper_tree(array $blocks, string $slot, array $presets, array &$report, array $path = []): array {
    foreach ($blocks as $index => &$section) {
        $at = [...$path, $index];
        $classes = bioco_shell_wrapper_classes($section['attrs'] ?? []);
        if (($section['blockName'] ?? '') !== 'divi/section'
            || !in_array('bioco-global-layout', $classes, true)
            || !in_array('bioco-global-' . $slot, $classes, true)) continue;
        $rows = $section['innerBlocks'] ?? [];
        $columns = $rows[0]['innerBlocks'] ?? [];
        $children = $columns[0]['innerBlocks'] ?? [];
        $shell_name = 'bioco-divi/' . ($slot === 'header' ? 'navigation-shell' : 'footer-shell');
        $shells = array_filter($children, static fn(array $child): bool => ($child['blockName'] ?? '') === $shell_name);
        if (count($rows) !== 1 || ($rows[0]['blockName'] ?? '') !== 'divi/row'
            || count($columns) !== 1 || ($columns[0]['blockName'] ?? '') !== 'divi/column' || count($shells) !== 1) {
            $report[] = ['path' => $at, 'action' => 'preserve', 'reason' => 'authored-shell-structure'];
            continue;
        }
        bioco_shell_wrapper_defaults($section, $at, $slot, $presets, $report);
        bioco_shell_wrapper_defaults($section['innerBlocks'][0], [...$at, 0], $slot, $presets, $report);
        bioco_shell_wrapper_defaults($section['innerBlocks'][0]['innerBlocks'][0], [...$at, 0, 0], $slot, $presets, $report);
    }
    unset($section);
    return $blocks;
}

/** One selected saved layout and its private metadata/preset snapshot. */
function bioco_shell_wrapper_plan(array $record, string $site, array $context): array {
    $columns = bioco_shell_wrapper_post_columns();
    if (array_diff(array_keys($record), $columns) || array_diff($columns, array_keys($record))
        || !is_int($record['ID']) || $record['ID'] < 1
        || !in_array($record['post_type'], ['et_header_layout', 'et_footer_layout'], true)) throw new RuntimeException('Expected one complete saved shell record.');
    foreach (array_diff($columns, ['ID']) as $column) if (!is_string($record[$column])) throw new RuntimeException('Invalid shell snapshot value.');
    $context_keys = ['site', 'presets', 'options', 'meta', 'assignments_sha256'];
    if (array_diff(array_keys($context), [...$context_keys, 'raw_post']) || array_diff($context_keys, array_keys($context))
        || ($context['site'] ?? '') !== $site || !filter_var($site, FILTER_VALIDATE_URL)
        || parse_url($site, PHP_URL_SCHEME) !== 'https'
        || !is_array($context['presets'] ?? null) || !is_array($context['options'] ?? null)
        || !is_array($context['meta'] ?? null) || !is_string($context['assignments_sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/', $context['assignments_sha256'])) throw new RuntimeException('Invalid shell context snapshot.');
    if (array_key_exists('raw_post', $context)) {
        if (!is_array($context['raw_post']) || array_keys($context['raw_post']) !== $columns) throw new RuntimeException('Invalid raw post context.');
        foreach ($columns as $column) {
            if (!is_string($context['raw_post'][$column]) || $context['raw_post'][$column] !== (string) $record[$column]) throw new RuntimeException('Raw post context differs from source record.');
        }
    }
    $seen = [];
    foreach ($context['options'] as $row) {
        if (array_keys($row) !== ['option_name', 'option_value'] || !is_string($row['option_value'])
            || !in_array($row['option_name'], bioco_shell_wrapper_preset_options(), true) || isset($seen[$row['option_name']])) throw new RuntimeException('Invalid preset option snapshot.');
        $seen[$row['option_name']] = true;
    }
    $stable_meta = [];
    $meta_ids = [];
    foreach ($context['meta'] as $row) {
        if (array_keys($row) !== ['meta_id', 'post_id', 'meta_key', 'meta_value']
            || !ctype_digit((string) $row['meta_id']) || (int) $row['meta_id'] < 1 || isset($meta_ids[$row['meta_id']])
            || !ctype_digit((string) $row['post_id']) || (int) $row['post_id'] !== $record['ID']
            || !is_string($row['meta_key']) || !is_string($row['meta_value'])) throw new RuntimeException('Invalid source metadata snapshot.');
        $meta_ids[$row['meta_id']] = true;
        if (!in_array($row['meta_key'], bioco_shell_wrapper_volatile_meta(), true)) $stable_meta[] = $row;
    }
    $context['meta'] = $stable_meta;
    $slot = $record['post_type'] === 'et_header_layout' ? 'header' : 'footer';
    $report = [];
    $blocks = bioco_shell_wrapper_tree(parse_blocks($record['post_content']), $slot, $context['presets'], $report);
    $changed = count(array_filter($report, static fn(array $row): bool => $row['action'] === 'adopt'));
    $after = $changed ? serialize_blocks($blocks) : $record['post_content'];
    $tag = 'qa-249-wrapper-' . substr(hash('sha256', json_encode([$site, $record])), 0, 16);
    $copy_meta = ['_bioco_editor_verification' => $tag];
    foreach ($stable_meta as $row) if (in_array($row['meta_key'], ['_et_pb_use_builder', '_et_pb_use_divi_5'], true)) $copy_meta[$row['meta_key']] = $row['meta_value'];
    $plan = ['version' => 2, 'site' => $site, 'before' => $record, 'context' => $context,
        'after_content' => $after, 'changed' => $changed, 'report' => $report,
        'copy_proposal' => ['post_type' => $record['post_type'], 'post_status' => 'draft',
            'post_title' => 'BIOCO QA ' . $tag . ' ' . $record['ID'], 'post_name' => 'bioco-qa-' . $tag . '-' . $record['ID'],
            'post_content' => $after, 'meta_input' => $copy_meta, 'assignments' => []]];
    $plan['fingerprint'] = hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR));
    return $plan;
}

/** Generate guarded content-only SQL; the release owner executes it separately. */
function bioco_shell_wrapper_sql(array $plan, string $expected, bool $restore = false): string {
    global $wpdb;
    $verified = bioco_shell_wrapper_plan($plan['before'] ?? [], $plan['site'] ?? '', $plan['context'] ?? []);
    if ($plan !== $verified || !hash_equals($verified['fingerprint'], $expected)) throw new RuntimeException('Shell preview changed.');
    if (!$plan['changed']) return '';
    $guards = [];
    foreach ($plan['before'] as $column => $value) {
        if ($restore && $column === 'post_content') $value = $plan['after_content'];
        $guards[] = $wpdb->prepare("BINARY `$column` = BINARY %s", (string) $value);
    }
    $volatile = implode(',', array_map(static fn(string $key): string => $wpdb->prepare('%s', $key), bioco_shell_wrapper_volatile_meta()));
    $guards[] = $wpdb->prepare("(SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=%d AND BINARY meta_key NOT IN ($volatile))=%d", $plan['before']['ID'], count($plan['context']['meta']));
    foreach ($plan['context']['meta'] as $row) $guards[] = $wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE meta_id=%d AND post_id=%d AND BINARY meta_key=BINARY %s AND BINARY meta_value=BINARY %s)", (int) $row['meta_id'], (int) $row['post_id'], $row['meta_key'], $row['meta_value']);
    $options = array_column($plan['context']['options'], 'option_value', 'option_name');
    foreach (bioco_shell_wrapper_preset_options() as $name) {
        $guards[] = isset($options[$name])
            ? $wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->options} WHERE BINARY option_name=BINARY %s AND BINARY option_value=BINARY %s)", $name, $options[$name])
            : $wpdb->prepare("NOT EXISTS (SELECT 1 FROM {$wpdb->options} WHERE BINARY option_name=BINARY %s)", $name);
    }
    $content = $restore ? $plan['before']['post_content'] : $plan['after_content'];
    $sql = $wpdb->prepare("UPDATE {$wpdb->posts} SET post_content=%s WHERE ", $content) . implode(' AND ', $guards);
    return $wpdb->remove_placeholder_escape($sql);
}
