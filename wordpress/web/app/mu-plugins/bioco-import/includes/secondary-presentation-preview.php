<?php
/** Pure preview for the measured native accordion and two-tile portal families. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/presentation-adoption.php';
require_once __DIR__ . '/editorial-native-preview-values.php';

function bioco_secondary_preview_has(array $block, string $class): bool {
    return in_array($class, bioco_import_presentation_tokens($block['attrs'] ?? []), true);
}

function bioco_secondary_preview_known(array $block, string $name, array $classes): void {
    if (($block['blockName'] ?? '') !== $name
        || array_diff(bioco_import_presentation_tokens($block['attrs'] ?? []), $classes)) {
        throw new RuntimeException('Unmeasured native structure or authored CSS class.');
    }
    if (!empty($block['attrs']['css'])) throw new RuntimeException('Authored Advanced CSS requires separate review.');
    bioco_import_presentation_class_states($block['attrs'] ?? [], $classes);
}

function bioco_secondary_preview_fill(array &$block, array $defaults): void {
    $block['attrs'] = bioco_import_presentation_defaults($defaults, $block['attrs'] ?? []);
}

function bioco_secondary_preview_font(array &$block, string $part, array $font): void {
    $path = $part === 'content' ? ['content', 'decoration', 'bodyFont', 'body', 'font']
        : ['title', 'decoration', 'font', 'font'];
    $defaults = ['desktop' => ['value' => $font]];
    foreach (array_reverse($path) as $key) $defaults = [$key => $defaults];
    bioco_secondary_preview_fill($block, $defaults);
}

/** Shared wrappers match the measured 1440/390 public geometry, without typography CSS. */
function bioco_secondary_preview_wrappers(array &$section, bool $accordion): void {
    $spacing = ['desktop' => ['value' => ['padding' => ['top' => '54px', 'bottom' => '54px', 'left' => '0px', 'right' => '0px']]],
        'phone' => ['value' => ['padding' => ['top' => '32px', 'bottom' => '32px', 'left' => '6vw', 'right' => '6vw']]]];
    if ($accordion) $spacing['desktop']['value']['margin']['top'] = '24px';
    bioco_secondary_preview_fill($section, ['module' => ['decoration' => [
        'background' => ['desktop' => ['value' => ['color' => '#F5F1E8']]],
        'sizing' => ['desktop' => ['value' => ['width' => '100%', 'maxWidth' => 'none']]],
        'spacing' => $spacing,
    ]]]);
    foreach ($section['innerBlocks'] as &$row) {
        // The existing native portal grid has its own 100% width and responsive layout.
        if (bioco_secondary_preview_has($row, 'bioco-editorial-tiles-row')) continue;
        bioco_secondary_preview_fill($row, ['module' => ['decoration' => [
            'sizing' => ['desktop' => ['value' => ['width' => '80%', 'maxWidth' => '1080px']]],
            'spacing' => ['desktop' => ['value' => ['margin' => ['left' => 'auto', 'right' => 'auto'],
                'padding' => ['top' => '0px', 'bottom' => '0px', 'left' => '0px', 'right' => '0px']]]],
        ]]]);
        bioco_import_presentation_classes($row['attrs'], ['bioco-divi-row']);
        foreach ($row['innerBlocks'] as &$column) {
            bioco_secondary_preview_fill($column, ['module' => ['decoration' => [
                'sizing' => ['desktop' => ['value' => ['width' => '100%', 'maxWidth' => '1160px']]],
            ]]]);
            bioco_import_presentation_classes($column['attrs'], ['bioco-divi-content']);
        }
        unset($column);
    }
    unset($row);
    bioco_import_presentation_classes($section['attrs'], $accordion
        ? ['bioco-divi-section', 'bioco-divi-accordion', 'demeter-accordion']
        : ['bioco-divi-section', 'bioco-divi-link-tiles'], $accordion ? 'bioco-native-accordion-section' : 'bioco-native-portal-section');
}

function bioco_secondary_preview_accordion(array &$section): void {
    bioco_secondary_preview_known($section, 'divi/section', ['bioco-divi-section', 'bioco-divi-accordion', 'demeter-accordion']);
    if (count($section['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unmeasured accordion row count.');
    $row =& $section['innerBlocks'][0];
    bioco_secondary_preview_known($row, 'divi/row', ['bioco-divi-row']);
    if (count($row['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unmeasured accordion column count.');
    $column =& $row['innerBlocks'][0];
    bioco_secondary_preview_known($column, 'divi/column', ['bioco-divi-content']);
    if (count($column['innerBlocks'] ?? []) !== 4) throw new RuntimeException('Unmeasured accordion item count.');
    foreach ($column['innerBlocks'] as &$toggle) {
        bioco_secondary_preview_known($toggle, 'divi/toggle', ['bioco-editorial-toggle']);
        if (!bioco_secondary_preview_has($toggle, 'bioco-editorial-toggle') || !empty($toggle['innerBlocks'])) {
            throw new RuntimeException('Unmeasured accordion item.');
        }
        bioco_secondary_preview_font($toggle, 'title', ['family' => 'DM Sans', 'size' => '16px', 'weight' => '600', 'lineHeight' => '1em', 'color' => '#1A1A1A']);
        bioco_secondary_preview_font($toggle, 'content', ['family' => 'DM Sans', 'size' => '16px', 'weight' => '500', 'lineHeight' => '1.7em', 'color' => '#1A1A1A']);
    }
    unset($toggle);
    bioco_secondary_preview_wrappers($section, true);
    // bioco-editorial-toggle remains: the native accessibility controller owns its behavior.
}

function bioco_secondary_preview_portal(array &$section): void {
    bioco_secondary_preview_known($section, 'divi/section', ['bioco-divi-section', 'bioco-divi-link-tiles']);
    if (count($section['innerBlocks'] ?? []) !== 2) throw new RuntimeException('Unmeasured portal row count.');
    $header =& $section['innerBlocks'][0];
    bioco_secondary_preview_known($header, 'divi/row', ['bioco-divi-row']);
    if (count($header['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unmeasured portal heading column count.');
    $column =& $header['innerBlocks'][0];
    bioco_secondary_preview_known($column, 'divi/column', ['bioco-divi-content']);
    if (count($column['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unmeasured portal heading structure.');
    $heading =& $column['innerBlocks'][0];
    bioco_secondary_preview_known($heading, 'divi/heading', ['bioco-divi-link-tiles-title']);
    if (!bioco_secondary_preview_has($heading, 'bioco-divi-link-tiles-title')) throw new RuntimeException('Missing portal heading marker.');
    // The legacy wrapper's 32/24px did not style the actual h2. Its measured native h2 is 26px.
    bioco_secondary_preview_font($heading, 'title', ['family' => 'DM Sans', 'size' => '26px', 'weight' => '500', 'lineHeight' => '1em', 'color' => '#333333']);
    bioco_secondary_preview_fill($heading, ['module' => ['decoration' => ['spacing' => ['desktop' => ['value' => ['margin' => ['bottom' => '0px']]]]]]]);
    bioco_import_presentation_classes($heading['attrs'], ['bioco-divi-link-tiles-title']);
    $grid =& $section['innerBlocks'][1];
    bioco_secondary_preview_known($grid, 'divi/row', ['bioco-editorial-tiles-row']);
    if (!bioco_secondary_preview_has($grid, 'bioco-editorial-tiles-row') || count($grid['innerBlocks'] ?? []) !== 2) {
        throw new RuntimeException('Unmeasured native portal grid.');
    }
    foreach ($grid['innerBlocks'] as &$tile) {
        bioco_secondary_preview_known($tile, 'divi/column', ['bioco-editorial-tile']);
        if (!bioco_secondary_preview_has($tile, 'bioco-editorial-tile') || count($tile['innerBlocks'] ?? []) !== 2) {
            throw new RuntimeException('Unmeasured native portal tile.');
        }
        $icon =& $tile['innerBlocks'][0];
        $blurb =& $tile['innerBlocks'][1];
        bioco_secondary_preview_known($icon, 'divi/text', ['bioco-editorial-tile-icon']);
        bioco_secondary_preview_known($blurb, 'divi/blurb', ['bioco-editorial-card']);
        if (!bioco_secondary_preview_has($icon, 'bioco-editorial-tile-icon') || !bioco_secondary_preview_has($blurb, 'bioco-editorial-card')) {
            throw new RuntimeException('Unmeasured native portal content.');
        }
        bioco_secondary_preview_font($icon, 'content', ['family' => 'DM Sans', 'size' => '32px', 'weight' => '500', 'lineHeight' => '27.2px', 'color' => '#1A1A1A']);
        bioco_secondary_preview_font($blurb, 'title', ['family' => 'DM Sans', 'size' => '22px', 'weight' => '700', 'lineHeight' => '1em', 'color' => '#333333']);
        bioco_secondary_preview_font($blurb, 'content', ['family' => 'DM Sans', 'size' => '16px', 'weight' => '500', 'lineHeight' => '1.7em', 'color' => '#4A4A4A']);
    }
    unset($tile);
    bioco_secondary_preview_wrappers($section, false);
}

/** A heading fallback must not mask a saved or inherited font/preset domain. */
function bioco_secondary_preview_timeline_presets(array $ancestors, ?array $presets): void {
    if ($presets === null || !is_array($presets['module'] ?? null) || !is_array($presets['group'] ?? null)) {
        throw new RuntimeException('Effective inherited timeline preset capture is required.');
    }
    $seen = [];
    $inspect = null;
    $resolve = static function(string $kind, string $name, $ids) use (&$seen, $presets, &$inspect): void {
        if (is_string($ids)) $ids = [$ids];
        if (!is_array($ids)) throw new RuntimeException('Unsupported inherited timeline preset reference.');
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') throw new RuntimeException('Unsupported inherited timeline preset ID.');
            $key = $kind . '/' . $name . '/' . $id;
            if (isset($seen[$key])) throw new RuntimeException('Cyclic inherited timeline preset reference.');
            $preset = $presets[$kind][$name]['items'][$id] ?? null;
            if (!is_array($preset)) throw new RuntimeException('Effective inherited timeline preset capture is required.');
            $seen[$key] = true;
            $inspect($preset, $name);
            unset($seen[$key]);
        }
    };
    $inspect = static function(array $node, string $name) use (&$inspect, &$resolve): void {
        foreach ($node as $key => $value) {
            if (stripos((string) $key, 'font') !== false
                || (in_array($key, ['groupName', 'groupId'], true) && is_string($value) && stripos($value, 'font') !== false)) {
                throw new RuntimeException('Inherited timeline font domain requires native review.');
            }
            if ($key === 'modulePreset' && !empty($value)) $resolve('module', $name, $value);
            if ($key === 'presetId' && !empty($value)) {
                if (!is_string($node['groupName'] ?? null)) throw new RuntimeException('Unknown inherited timeline group preset.');
                $resolve('group', $node['groupName'], $value);
            }
            if (($key === 'css' || $key === 'styleIds') && !empty($value)) throw new RuntimeException('Authored inherited timeline style requires native review.');
            if (is_array($value)) $inspect($value, $node['moduleName'] ?? $name);
        }
    };
    foreach ($ancestors as $block) {
        $name = $block['blockName'];
        $inspect($block['attrs'] ?? [], $name);
        if (!empty($presets['module'][$name]['default'])) $resolve('module', $name, $presets['module'][$name]['default']);
    }
    if (!empty($presets['module']['divi/heading']['default'])) $resolve('module', 'divi/heading', $presets['module']['divi/heading']['default']);
    foreach ($presets['group'] ?? [] as $name => $group) {
        if (!empty($group['default']) && stripos((string) $name, 'font') !== false) throw new RuntimeException('Default timeline font domain requires native review.');
    }
}

/** The already native timeline's header is the only remaining legacy heading. */
function bioco_secondary_preview_timeline_heading(array &$section, ?array $presets): bool {
    $headers = [];
    foreach ($section['innerBlocks'] ?? [] as $index => $row) {
        if (bioco_secondary_preview_has($row, 'bioco-timeline-header')) $headers[] = $index;
    }
    if (count($headers) !== 1) throw new RuntimeException('Unmeasured native timeline header.');
    $row =& $section['innerBlocks'][$headers[0]];
    if (($row['blockName'] ?? '') !== 'divi/row' || count($row['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unmeasured native timeline header row.');
    $column =& $row['innerBlocks'][0];
    if (($column['blockName'] ?? '') !== 'divi/column' || !bioco_secondary_preview_has($column, 'bioco-timeline-header-content')
        || count($column['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unmeasured native timeline header column.');
    $heading =& $column['innerBlocks'][0];
    if (!bioco_secondary_preview_has($heading, 'bioco-divi-title')) return false;
    bioco_secondary_preview_timeline_presets([$section, $row, $column], $presets);
    bioco_secondary_preview_known($heading, 'divi/heading', ['bioco-divi-title']);
    if (!empty($heading['innerBlocks'])) throw new RuntimeException('Unmeasured native timeline heading.');
    bioco_editorial_native_presets($heading);
    bioco_editorial_native_fill($heading, ['title', 'decoration', 'font', 'font'], ['desktop' => [
        'size' => '26px', 'weight' => '500', 'lineHeight' => '1em', 'color' => '#333333',
    ]]);
    bioco_editorial_native_fill($heading, ['css'], ['desktop' => ['mainElement' => 'padding-bottom:10px;']]);
    bioco_editorial_native_remove($heading, ['bioco-divi-title']);
    return true;
}

/** Atomic per recognized section. Opaque steps and other families remain unchanged. */
function bioco_secondary_presentation_preview(array $blocks, array $path = [], ?array $presets = null): array {
    $report = [];
    foreach ($blocks as $index => &$block) {
        $at = [...$path, $index];
        if (($block['blockName'] ?? '') === 'divi/section') {
            if (bioco_secondary_preview_has($block, 'bioco-timeline-section')) {
                $candidate = $block;
                try {
                    if (bioco_secondary_preview_timeline_heading($candidate, $presets)) {
                        $block = $candidate;
                        $report[] = ['path' => $at, 'action' => 'timeline-heading'];
                    }
                } catch (RuntimeException $error) {
                    $report[] = ['path' => $at, 'action' => 'preserve', 'reason' => $error->getMessage()];
                }
                continue;
            }
            $family = bioco_secondary_preview_has($block, 'bioco-divi-accordion') ? 'accordion'
                : (bioco_secondary_preview_has($block, 'bioco-divi-link-tiles') ? 'portal' : '');
            if (bioco_secondary_preview_has($block, 'bioco-divi-steps')) {
                $report[] = ['path' => $at, 'action' => 'preserve', 'reason' => 'Steps are handled by bioco_steps_native_preview in steps-native-preview.php.'];
                continue;
            }
            if ($family !== '') {
                $candidate = $block;
                try {
                    bioco_import_presentation_presets($candidate);
                    if ($family === 'accordion') bioco_secondary_preview_accordion($candidate);
                    else bioco_secondary_preview_portal($candidate);
                    $block = $candidate;
                    $report[] = ['path' => $at, 'action' => $family];
                } catch (RuntimeException $error) {
                    $report[] = ['path' => $at, 'action' => 'preserve', 'reason' => $error->getMessage()];
                }
                continue;
            }
        }
        if (!empty($block['innerBlocks'])) {
            [$block['innerBlocks'], $children] = bioco_secondary_presentation_preview($block['innerBlocks'], $at, $presets);
            $report = [...$report, ...$children];
        }
    }
    unset($block);
    return [$blocks, $report];
}
