<?php
/** Pure structural preview of the measured three-item imported steps section. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/presentation-adoption.php';
require_once __DIR__ . '/divi-blocks.php';

/** Retain exact inline anchor bytes; decline other markup and unsafe destinations. */
function bioco_steps_native_inline(string $html): void {
    $plain = preg_replace_callback('~<a href="([^"]+)"(?: target="(?:_blank|_self)")?(?: rel="noopener noreferrer")?>[^<]*</a>~', static function(array $match): string {
        $href = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^(?:https?://[^\s<>]+|mailto:[^\s<>]+|/(?!/)[^\s<>]*|\#[^\s<>]*)$~iD', $href)) {
            throw new RuntimeException('Unsafe saved steps destination.');
        }
        return '';
    }, $html);
    if (preg_match('~[<>]~', $plain)) throw new RuntimeException('Unmeasured inline steps markup.');
}

function bioco_steps_native_items(string $html): array {
    if (!preg_match('~^<div class="next-steps">(.*)</div>$~sD', $html, $outer)) throw new RuntimeException('Unknown saved steps wrapper.');
    $remaining = $outer[1];
    $items = [];
    while ($remaining !== '') {
        if (!preg_match('~^<div class="step-item"><div class="step-number">([1-9][0-9]*)</div><div><h3>([^<>]*)</h3>(<p>(.*?)</p>)</div></div>~s', $remaining, $match)) {
            throw new RuntimeException('Unknown saved steps item.');
        }
        if ($match[1] !== (string) (count($items) + 1)) throw new RuntimeException('Saved step numbering is not sequential.');
        bioco_steps_native_inline($match[4]);
        $items[] = ['number' => $match[1], 'title' => $match[2], 'paragraph' => $match[3]];
        $remaining = substr($remaining, strlen($match[0]));
    }
    if (count($items) !== 3) throw new RuntimeException('Unmeasured steps item count.');
    return $items;
}

/** Splitting is limited to the normalized importer shape; authored design remains untouched. */
function bioco_steps_native_imported(array $block, string $name, array $classes): void {
    $attrs = $block['attrs'] ?? [];
    $module = $attrs['module'] ?? [];
    $decoration = $module['decoration'] ?? [];
    if (($block['blockName'] ?? '') !== $name
        || array_diff(bioco_import_presentation_tokens($attrs), $classes)
        || array_diff(array_keys($attrs), ['module', 'content', 'builderVersion'])
        || array_diff(array_keys($module), ['advanced', 'decoration'])
        || array_diff(array_keys($module['advanced'] ?? []), ['htmlAttributes', 'type', 'columnStructure'])
        || array_diff(array_keys($decoration), ['layout', 'attributes'])
        || (!empty($decoration['layout']) && $decoration['layout'] !== ['desktop' => ['value' => ['display' => 'block']]])) {
        throw new RuntimeException('Authored steps structure or design requires native review.');
    }
    bioco_import_presentation_class_states($attrs, $classes);
}

function bioco_steps_native_module(string $name, array $attrs, array $children = []): array {
    $attrs['module']['meta']['adminLabel']['desktop']['value'] ??= ucfirst(substr($name, 5));
    return bioco_import_divi_block($name, $attrs, $children);
}

/** Each item has a native grid row, number Text, Heading and body Text. */
function bioco_steps_native_card(array $item): array {
    $v = static fn(array $value): array => ['desktop' => ['value' => $value]];
    $zero = array_fill_keys(['top', 'right', 'bottom', 'left'], '0px');
    $spacing = $v(['padding' => $zero, 'margin' => $zero]);
    $number = bioco_steps_native_module('divi/text', [
        'content' => ['innerContent' => ['desktop' => ['value' => $item['number']]],
            'decoration' => ['bodyFont' => ['body' => ['font' => $v(['family' => 'DM Sans', 'size' => '16px', 'weight' => '700', 'lineHeight' => '1.7em', 'color' => '#FFFFFF'])]]]],
        'module' => ['decoration' => ['background' => $v(['color' => '#2E7D32']),
            'sizing' => $v(['width' => '32px', 'maxWidth' => '32px', 'height' => '32px']), 'spacing' => $spacing,
            'border' => $v(['radius' => array_fill_keys(['topLeft', 'topRight', 'bottomLeft', 'bottomRight'], '999px')]),
            'layout' => $v(['display' => 'flex', 'flexDirection' => 'row', 'alignItems' => 'center', 'justifyContent' => 'center', 'columnGap' => '0px', 'rowGap' => '0px'])]],
    ]);
    $heading = bioco_steps_native_module('divi/heading', [
        'title' => ['innerContent' => ['desktop' => ['value' => $item['title']]], 'decoration' => ['font' => ['font' => $v([
            'headingLevel' => 'h3', 'family' => 'DM Sans', 'size' => '18px', 'weight' => '500', 'lineHeight' => '1em', 'color' => '#333333',
        ])]]], 'module' => ['decoration' => ['spacing' => $v(['margin' => ['bottom' => '8px']])]],
    ]);
    $body = bioco_steps_native_module('divi/text', [
        'content' => ['innerContent' => ['desktop' => ['value' => $item['paragraph']]],
            'decoration' => ['bodyFont' => ['body' => ['font' => $v(['family' => 'DM Sans', 'size' => '16px', 'weight' => '500', 'lineHeight' => '1.7em', 'color' => '#4A4A4A'])]]]],
        'module' => ['decoration' => ['spacing' => $spacing]],
    ]);
    $column = static fn(array $children): array => bioco_steps_native_module('divi/column', [
        'module' => ['advanced' => ['type' => ['desktop' => ['value' => '4_4']]], 'decoration' => [
            'sizing' => $v(['width' => '100%', 'maxWidth' => '100%']), 'spacing' => $spacing,
            'layout' => $v(['display' => 'flex', 'flexDirection' => 'column', 'columnGap' => '0px', 'rowGap' => '0px']),
        ]],
    ], $children);
    return bioco_steps_native_module('divi/row', ['module' => [
        'advanced' => ['columnStructure' => ['desktop' => ['value' => '4_4,4_4']]],
        'decoration' => ['sizing' => $v(['width' => '100%', 'maxWidth' => 'none']), 'spacing' => $spacing,
            'layout' => $v(['display' => 'grid', 'gridColumnWidths' => 'manual', 'gridTemplateColumns' => '32px minmax(0, 1fr)', 'columnGap' => '24px', 'rowGap' => '0px', 'alignItems' => 'start'])],
    ]], [$column([$number]), $column([$heading, $body])]);
}

function bioco_steps_native_section(array $section): array {
    if (!bioco_steps_native_has($section, 'bioco-divi-steps')) return $section;
    bioco_import_presentation_presets($section);
    bioco_steps_native_imported($section, 'divi/section', ['bioco-divi-section', 'bioco-divi-steps']);
    if (count($section['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unmeasured steps row count.');
    $row =& $section['innerBlocks'][0];
    bioco_steps_native_imported($row, 'divi/row', ['bioco-divi-row']);
    if (count($row['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unmeasured steps column count.');
    $column =& $row['innerBlocks'][0];
    bioco_steps_native_imported($column, 'divi/column', ['bioco-divi-content']);
    if (count($column['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unmeasured steps module count.');
    $source = $column['innerBlocks'][0];
    bioco_steps_native_imported($source, 'divi/text', ['bioco-divi-steps-body']);
    if (!bioco_steps_native_has($source, 'bioco-divi-steps-body') || !empty($source['innerBlocks'])) throw new RuntimeException('Missing imported steps body.');
    $values = $source['attrs']['content']['innerContent'] ?? [];
    $html = $values['desktop']['value'] ?? null;
    if (!is_string($html)) throw new RuntimeException('Missing saved steps HTML.');
    foreach ($values as $device => $states) foreach ($states as $state => $value) {
        if ($value !== $html) throw new RuntimeException('Distinct responsive or state steps content requires native review.');
    }
    $items = bioco_steps_native_items($html);
    $v = static fn(array $value): array => ['desktop' => ['value' => $value]];
    $zero = array_fill_keys(['top', 'right', 'bottom', 'left'], '0px');
    $source['blockName'] = 'divi/row';
    unset($source['attrs']['content']);
    $source['attrs']['module']['advanced']['columnStructure']['desktop']['value'] = '4_4';
    bioco_import_presentation_classes($source['attrs'], ['bioco-divi-steps-body'], 'bioco-native-steps-list');
    $source['attrs'] = bioco_import_presentation_defaults(['module' => ['decoration' => [
        'sizing' => $v(['width' => '100%', 'maxWidth' => 'none']), 'spacing' => $v(['padding' => $zero, 'margin' => $zero]),
    ]]], $source['attrs']);
    $listColumn = bioco_steps_native_module('divi/column', ['module' => [
        'advanced' => ['type' => ['desktop' => ['value' => '4_4']]], 'decoration' => [
            'sizing' => $v(['width' => '100%', 'maxWidth' => '100%']), 'spacing' => $v(['padding' => $zero, 'margin' => $zero]),
            'layout' => $v(['display' => 'flex', 'flexDirection' => 'column', 'columnGap' => '0px', 'rowGap' => '24px']),
        ],
    ]], array_map('bioco_steps_native_card', $items));
    $source['innerBlocks'] = [$listColumn]; $source['innerContent'] = [null]; $source['innerHTML'] = '';
    $column['innerBlocks'][0] = $source;
    $section['attrs'] = bioco_import_presentation_defaults(['module' => ['decoration' => [
        'background' => $v(['color' => '#F5F1E8']),
        'spacing' => ['desktop' => ['value' => ['padding' => ['top' => '54px', 'bottom' => '54px', 'left' => '0px', 'right' => '0px']]],
            'phone' => ['value' => ['padding' => ['top' => '32px', 'bottom' => '32px', 'left' => '6vw', 'right' => '6vw']]]],
    ]]], $section['attrs']);
    $row['attrs'] = bioco_import_presentation_defaults(['module' => ['decoration' => [
        'sizing' => $v(['width' => '80%', 'maxWidth' => '1080px']), 'spacing' => $v(['padding' => $zero, 'margin' => ['top' => '24px', 'left' => 'auto', 'right' => 'auto']]),
    ]]], $row['attrs']);
    $column['attrs'] = bioco_import_presentation_defaults(['module' => ['decoration' => ['sizing' => $v(['width' => '100%', 'maxWidth' => '1160px'])]]], $column['attrs']);
    bioco_import_presentation_classes($section['attrs'], ['bioco-divi-section', 'bioco-divi-steps'], 'bioco-native-steps-section');
    bioco_import_presentation_classes($row['attrs'], ['bioco-divi-row']);
    bioco_import_presentation_classes($column['attrs'], ['bioco-divi-content']);
    return $section;
}

function bioco_steps_native_has(array $block, string $class): bool {
    return in_array($class, bioco_import_presentation_tokens($block['attrs'] ?? []), true);
}

function bioco_steps_native_preview(array $blocks, array $path = []): array {
    $report = [];
    foreach ($blocks as $index => &$block) {
        $at = [...$path, $index];
        if (($block['blockName'] ?? '') === 'divi/section' && bioco_steps_native_has($block, 'bioco-divi-steps')) {
            try { $block = bioco_steps_native_section($block); $report[] = ['path' => $at, 'action' => 'steps']; }
            catch (RuntimeException $error) { $report[] = ['path' => $at, 'action' => 'preserve', 'reason' => $error->getMessage()]; }
            continue;
        }
        if (!empty($block['innerBlocks'])) {
            [$block['innerBlocks'], $children] = bioco_steps_native_preview($block['innerBlocks'], $at);
            $report = [...$report, ...$children];
        }
    }
    unset($block);
    return [$blocks, $report];
}
