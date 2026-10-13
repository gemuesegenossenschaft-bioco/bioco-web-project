<?php
/** Preview-only native Divi defaults for the legacy button and page-intro classes. */
if (!defined('ABSPATH')) exit;

function bioco_import_presentation_tokens(array $attrs): array {
    $class = $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['class'] ?? '';
    $items = $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
    foreach ($items as $item) {
        if (($item['name'] ?? '') === 'class' && is_string($item['value'] ?? null)) {
            $class .= ' ' . $item['value'];
        }
    }
    return array_values(array_unique(preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY)));
}

function bioco_import_presentation_class_states(array $attrs, array $remove): void {
    foreach (['advanced' => 'htmlAttributes', 'decoration' => 'attributes'] as $group => $control) {
        foreach ($attrs['module'][$group][$control] ?? [] as $device => $states) {
            foreach ($states as $state => $value) {
                $classes = $group === 'advanced' ? [$value['class'] ?? ''] : array_column(array_filter($value['attributes'] ?? [], static fn($a) => ($a['name'] ?? '') === 'class'), 'value');
                foreach ($classes as $class) {
                    if (!is_string($class)) throw new RuntimeException('Unsupported saved class value.');
                    if (($device !== 'desktop' || $state !== 'value')
                        && array_intersect($remove, preg_split('/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY))) {
                        throw new RuntimeException('Responsive or state-specific legacy classes require separate review.');
                    }
                }
            }
        }
    }
}

function bioco_import_presentation_classes(array &$attrs, array $remove, string $marker = ''): void {
    bioco_import_presentation_class_states($attrs, $remove);
    $retire = static function(string $class) use ($remove, &$marker): string {
        $before = preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY);
        if (!array_intersect($before, $remove)) return $class;
        $tokens = array_values(array_diff($before, $remove));
        if ($marker !== '' && !in_array($marker, $tokens, true)) $tokens[] = $marker;
        $marker = '';
        return implode(' ', $tokens);
    };
    if (array_key_exists('class', $attrs['module']['advanced']['htmlAttributes']['desktop']['value'] ?? [])) {
        $class =& $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['class'];
        $class = $retire($class);
        unset($class);
    }
    foreach ($attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [] as $index => $item) {
        if (($item['name'] ?? '') === 'class') {
            $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'][$index]['value'] = $retire($item['value']);
        }
    }
}

/** Remove defaults that would mask authored values inherited at this breakpoint/state. */
function bioco_import_presentation_inherited(array $defaults, array $inherited): array {
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $inherited)) continue;
        if (is_array($value) && is_array($inherited[$key])) {
            $defaults[$key] = bioco_import_presentation_inherited($value, $inherited[$key]);
            if (!$defaults[$key]) unset($defaults[$key]);
        } else unset($defaults[$key]);
    }
    return $defaults;
}

/** Only originally saved values participate in inheritance; inserted defaults do not. */
function bioco_import_presentation_defaults(array $defaults, array $saved): array {
    $devices = ['desktop', 'tablet', 'phone'];
    if (array_intersect($devices, array_keys($defaults))) {
        foreach ($defaults as $device => &$states) {
            if (!in_array($device, $devices, true) || !is_array($states)) continue;
            $inherited = [];
            foreach ($devices as $ancestor) {
                if ($ancestor === $device) break;
                if (array_key_exists($ancestor, $saved) && !is_array($saved[$ancestor])) {
                    throw new RuntimeException('Unsupported saved native breakpoint.');
                }
                if (array_key_exists('value', $saved[$ancestor] ?? []) && !is_array($saved[$ancestor]['value'])) {
                    throw new RuntimeException('Unsupported inherited native value.');
                }
                if (is_array($saved[$ancestor]['value'] ?? null)) $inherited = array_replace_recursive($inherited, $saved[$ancestor]['value']);
            }
            foreach ($states as $state => &$value) {
                if (!is_array($value)) continue;
                $from = $inherited;
                if ($state !== 'value') {
                    if (is_array($saved[$device]['value'] ?? null)) $from = array_replace_recursive($from, $saved[$device]['value']);
                    foreach ($devices as $ancestor) {
                        if ($ancestor === $device) break;
                        if (is_array($saved[$ancestor][$state] ?? null)) $from = array_replace_recursive($from, $saved[$ancestor][$state]);
                    }
                }
                $value = bioco_import_presentation_inherited($value, $from);
                if (!$value) unset($states[$state]);
            }
            unset($value);
            if (!$states) unset($defaults[$device]);
        }
        unset($states);
    }
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $saved)) $saved[$key] = $value;
        elseif (is_array($saved[$key]) && is_array($value)) {
            $saved[$key] = bioco_import_presentation_defaults($value, $saved[$key]);
        } elseif (is_array($value)) throw new RuntimeException('Unsupported saved native control shape.');
    }
    return $saved;
}

function bioco_import_presentation_presets(array $block): void {
    $hasPreset = static function(array $values) use (&$hasPreset): bool {
        foreach ($values as $key => $value) {
            if (in_array($key, ['modulePreset', 'groupPreset', 'presetId'], true) && !empty($value)) return true;
            if (is_array($value) && $hasPreset($value)) return true;
        }
        return false;
    };
    if ($hasPreset($block['attrs'] ?? [])) throw new RuntimeException('Effective shared preset capture is required before presentation conversion.');
    foreach ($block['innerBlocks'] ?? [] as $child) bioco_import_presentation_presets($child);
}

function bioco_import_presentation_intro_states(array $block): void {
    bioco_import_presentation_class_states($block['attrs'] ?? [], ['bioco-divi-section', 'bioco-divi-page-intro',
        'bioco-divi-width-lg', 'bioco-divi-align-left', 'bioco-divi-row', 'bioco-divi-content',
        'bioco-divi-text-wide', 'bioco-divi-text-normal', 'bioco-divi-title', 'bioco-divi-text',
        'bioco-divi-button', 'bioco-divi-button--primary', 'bioco-divi-button--secondary']);
    foreach ($block['innerBlocks'] ?? [] as $child) bioco_import_presentation_intro_states($child);
}

/** Stable Divi global-color reference, matching the existing design-system seed. */
function bioco_import_presentation_color(string $css_var): string {
    return '$variable(' . json_encode(['type' => 'color', 'value' => [
        'name' => 'gcid-bioco-' . substr(hash('sha256', $css_var), 0, 16),
        'settings' => new stdClass(),
    ]]) . ')$';
}

function bioco_import_presentation_button_defaults(bool $primary): array {
    $v = static fn(array $value): array => ['desktop' => ['value' => $value]];
    $green = bioco_import_presentation_color('--wp--preset--color--bioco-green');
    $white = bioco_import_presentation_color('--wp--preset--color--bioco-surface');
    $radius = ['topLeft' => '28px', 'topRight' => '22px', 'bottomRight' => '26px', 'bottomLeft' => '18px'];
    return [
        'button' => ['decoration' => [
            'button' => $v(['enable' => 'on', 'icon' => ['enable' => 'off']]),
            'font' => ['font' => ['desktop' => [
                'value' => ['family' => 'DM Sans', 'size' => '16px', 'weight' => '700', 'lineHeight' => '24px', 'color' => $primary ? $white : $green],
                'hover' => ['color' => $primary ? $white : bioco_import_presentation_color('--wp--preset--color--bioco-green-dark')],
            ]]],
            'background' => ['desktop' => [
                'value' => ['color' => $primary ? $green : $white],
                'hover' => ['color' => $primary ? bioco_import_presentation_color('--wp--preset--color--bioco-green-dark') : bioco_import_presentation_color('--wp--preset--color--bioco-bg')],
            ]],
            'border' => $v(['radius' => $radius, 'styles' => ['all' => ['width' => '2px', 'style' => 'solid', 'color' => $primary ? $green : 'transparent']]]),
            'sizing' => $v(['minHeight' => '44px']),
            'boxShadow' => $v(['style' => 'preset1', 'horizontal' => '0px', 'vertical' => '8px', 'blur' => '16px', 'spread' => '0px', 'color' => 'rgba(0,0,0,.12)']),
        ]],
        // ButtonModule sanitizes button.decoration against its supported groups;
        // its anchor padding comes from the native module spacing control.
        'module' => ['decoration' => ['spacing' => $v(['padding' => [
            'top' => '12px', 'bottom' => '12px', 'left' => '24px', 'right' => '24px',
        ]])]],
        // Installed Divi Button conversion-outline maps Advanced CSS Main Element
        // to css.*.mainElement; CssStyle consumes css.desktop.value.mainElement.
        'css' => $v(['mainElement' => 'clip-path: polygon(4% 8%, 12% 2%, 26% 6%, 36% 3%, 48% 7%, 60% 2%, 74% 6%, 86% 3%, 96% 10%, 98% 24%, 96% 42%, 99% 58%, 96% 76%, 98% 90%, 90% 98%, 74% 94%, 60% 98%, 46% 94%, 32% 98%, 18% 94%, 8% 96%, 2% 86%, 4% 70%, 2% 54%, 6% 36%, 2% 20%);']),
    ];
}

function bioco_import_presentation_button(array &$block, array &$report, array $path): void {
    if (($block['blockName'] ?? '') !== 'divi/button') return;
    $attrs = $block['attrs'] ?? [];
    $classes = bioco_import_presentation_tokens($attrs);
    if (!in_array('bioco-divi-button', $classes, true)) return;
    $primary = in_array('bioco-divi-button--primary', $classes, true);
    $secondary = in_array('bioco-divi-button--secondary', $classes, true);
    if ($primary === $secondary || !isset($attrs['button']['innerContent']['desktop']['value']['text'], $attrs['button']['innerContent']['desktop']['value']['linkUrl'])) {
        $report[] = ['path' => $path, 'action' => 'preserve', 'reason' => 'ambiguous-button']; return;
    }
    if (!empty($attrs['css'])) {
        $report[] = ['path' => $path, 'action' => 'preserve', 'reason' => 'authored-style-or-preset']; return;
    }
    bioco_import_presentation_presets($block);
    $attrs = bioco_import_presentation_defaults(bioco_import_presentation_button_defaults($primary), $attrs);
    bioco_import_presentation_classes($attrs, ['bioco-divi-button', 'bioco-divi-button--primary', 'bioco-divi-button--secondary'], 'bioco-native-button');
    $block['attrs'] = $attrs;
    $report[] = ['path' => $path, 'action' => 'button'];
}

function bioco_import_presentation_intro(array &$block, array &$report, array $path): void {
    if (($block['blockName'] ?? '') !== 'divi/section') return;
    $classes = bioco_import_presentation_tokens($block['attrs'] ?? []);
    if (!in_array('bioco-divi-page-intro', $classes, true)) return;
    $rows = $block['innerBlocks'] ?? [];
    if (count($rows) !== 1 || ($rows[0]['blockName'] ?? '') !== 'divi/row'
        || count($rows[0]['innerBlocks'] ?? []) !== 1 || ($rows[0]['innerBlocks'][0]['blockName'] ?? '') !== 'divi/column') {
        $report[] = ['path' => $path, 'action' => 'preserve', 'reason' => 'intro-structure']; return;
    }
    bioco_import_presentation_presets($block);
    bioco_import_presentation_intro_states($block);
    $row =& $block['innerBlocks'][0];
    $column =& $row['innerBlocks'][0];
    $sectionDefaults = ['module' => ['decoration' => [
        'background' => ['desktop' => ['value' => ['color' => bioco_import_presentation_color('--wp--preset--color--bioco-bg')]]],
        'spacing' => ['desktop' => ['value' => ['padding' => ['top' => '54px', 'bottom' => '54px', 'left' => '0px', 'right' => '0px']]],
            'phone' => ['value' => ['padding' => ['top' => '32px', 'bottom' => '32px', 'left' => '6vw', 'right' => '6vw']]]],
    ]]];
    $rowDefaults = ['module' => ['decoration' => [
        'sizing' => ['desktop' => ['value' => ['width' => '80%', 'maxWidth' => '1080px']]],
        'spacing' => ['desktop' => ['value' => ['margin' => ['left' => 'auto', 'right' => 'auto'], 'padding' => ['top' => '0px', 'bottom' => '0px', 'left' => '0px', 'right' => '0px']]]],
    ]]];
    $columnDefaults = ['module' => ['decoration' => ['sizing' => ['desktop' => ['value' => ['width' => '100%', 'maxWidth' => '1160px']]]]]];
    $block['attrs'] = bioco_import_presentation_defaults($sectionDefaults, $block['attrs'] ?? []);
    bioco_import_presentation_classes($block['attrs'], ['bioco-divi-section', 'bioco-divi-page-intro', 'bioco-divi-width-lg', 'bioco-divi-align-left'], 'bioco-native-intro');
    $row['attrs'] = bioco_import_presentation_defaults($rowDefaults, $row['attrs'] ?? []);
    bioco_import_presentation_classes($row['attrs'], ['bioco-divi-row']);
    $column['attrs'] = bioco_import_presentation_defaults($columnDefaults, $column['attrs'] ?? []);
    bioco_import_presentation_classes($column['attrs'], ['bioco-divi-content', 'bioco-divi-text-wide', 'bioco-divi-text-normal']);
    foreach ($column['innerBlocks'] as &$child) {
        $childClasses = bioco_import_presentation_tokens($child['attrs'] ?? []);
        if (($child['blockName'] ?? '') === 'divi/heading' && in_array('bioco-divi-title', $childClasses, true)) {
            $defaults = ['title' => ['decoration' => ['font' => ['font' => ['desktop' => ['value' => [
                'family' => 'DM Sans', 'size' => 'clamp(1.75rem, 4vw, 2.5rem)', 'weight' => '700', 'lineHeight' => '1.2em', 'color' => bioco_import_presentation_color('--wp--preset--color--bioco-text'),
            ]]]]]], 'module' => ['decoration' => ['spacing' => ['desktop' => ['value' => ['margin' => ['bottom' => '2.75%']]], 'phone' => ['value' => ['margin' => ['bottom' => '0.5em']]]]]]];
            $child['attrs'] = bioco_import_presentation_defaults($defaults, $child['attrs'] ?? []);
            bioco_import_presentation_classes($child['attrs'], ['bioco-divi-title']);
        } elseif (($child['blockName'] ?? '') === 'divi/text' && in_array('bioco-divi-text', $childClasses, true)) {
            $defaults = ['content' => ['decoration' => ['bodyFont' => ['body' => ['font' => ['desktop' => ['value' => ['color' => bioco_import_presentation_color('--wp--preset--color--bioco-text-muted')]]]]]]]];
            $child['attrs'] = bioco_import_presentation_defaults($defaults, $child['attrs'] ?? []);
            bioco_import_presentation_classes($child['attrs'], ['bioco-divi-text']);
        }
    }
    unset($child);
    $report[] = ['path' => $path, 'action' => 'intro'];
}

function bioco_import_presentation_tree(array $blocks, array &$report, array $path = []): array {
    foreach ($blocks as $index => &$block) {
        $at = [...$path, $index];
        $candidate = $block;
        $local = [];
        try {
            bioco_import_presentation_intro($candidate, $local, $at);
            bioco_import_presentation_button($candidate, $local, $at);
        } catch (RuntimeException $error) {
            $report[] = ['path' => $at, 'action' => 'preserve', 'reason' => $error->getMessage()];
            continue;
        }
        $report = [...$report, ...$local];
        if (count(array_filter($local, static fn($row) => $row['action'] === 'preserve'))) continue;
        $block = $candidate;
        if (!empty($block['innerBlocks'])) $block['innerBlocks'] = bioco_import_presentation_tree($block['innerBlocks'], $report, $at);
    }
    unset($block);
    return $blocks;
}

function bioco_import_presentation_content(string $content): array {
    $report = [];
    $blocks = bioco_import_presentation_tree(parse_blocks($content), $report);
    $changed = count(array_filter($report, static fn($row) => in_array($row['action'], ['button', 'intro'], true)));
    return ['content' => $changed ? serialize_blocks($blocks) : $content, 'changed' => $changed, 'report' => $report];
}

/** One selected published page. The operator must capture its exact saved row. */
function bioco_import_presentation_plan(array $record, string $site): array {
    $columns = bioco_import_presentation_post_columns();
    if (array_diff(array_keys($record), $columns) || array_diff($columns, array_keys($record))
        || !is_string($record['ID']) || !preg_match('/^[1-9][0-9]*$/', $record['ID']) || $record['post_type'] !== 'page' || $record['post_status'] !== 'publish'
        || $site !== home_url('/')) throw new RuntimeException('Expected one complete published page snapshot from this site.');
    foreach ($columns as $column) {
        if (!is_string($record[$column])) throw new RuntimeException('Invalid saved page value.');
    }
    $record = array_replace(array_fill_keys($columns, ''), $record);
    $converted = bioco_import_presentation_content($record['post_content']);
    $plan = ['version' => 2, 'site' => $site, 'before' => $record,
        'after_content' => $converted['content'], 'changed' => $converted['changed'], 'report' => $converted['report']];
    $plan['fingerprint'] = hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR));
    return $plan;
}

/** Export conditional SQL; never execute it from a normal code release. */
function bioco_import_presentation_sql(array $plan, string $expected, bool $restore = false): string {
    global $wpdb;
    if (($plan['version'] ?? 0) !== 2 || !isset($plan['before']) || !is_array($plan['before'])) throw new RuntimeException('Invalid presentation plan.');
    $verified = bioco_import_presentation_plan($plan['before'], $plan['site'] ?? '');
    if ($plan !== $verified || !hash_equals($verified['fingerprint'], $expected)) throw new RuntimeException('Presentation preview changed.');
    if (!$plan['changed']) return '';
    $guards = [];
    foreach ($plan['before'] as $column => $value) {
        if ($restore && $column === 'post_content') $value = $plan['after_content'];
        $guards[] = $wpdb->prepare("BINARY `$column` = BINARY %s", (string) $value);
    }
    $content = $restore ? $plan['before']['post_content'] : $plan['after_content'];
    $sql = $wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = %s WHERE ", $content) . implode(' AND ', $guards);
    return $wpdb->remove_placeholder_escape($sql);
}

/** Exact raw wp_posts values, as returned by a database ARRAY_A capture. */
function bioco_import_presentation_post_columns(): array {
    return ['ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title',
        'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name',
        'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered',
        'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type', 'comment_count'];
}
