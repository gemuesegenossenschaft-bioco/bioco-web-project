<?php
/** Pure native preview for saved cards-grid/gallery-strip presentation. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/editorial-layouts.php';

/** Missing means absent: explicit blank, null, zero and false remain authored. */
function bioco_cards_preview_missing(array $saved, array $defaults): array {
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $saved)) $saved[$key] = $value;
        elseif (is_array($saved[$key]) && is_array($value)) $saved[$key] = bioco_cards_preview_missing($saved[$key], $value);
    }
    return $saved;
}

/** Do not create a responsive fallback which masks an authored inherited value. */
function bioco_cards_preview_inherited(array $defaults, array $inherited): array {
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $inherited)) continue;
        if (is_array($value) && is_array($inherited[$key])) {
            $defaults[$key] = bioco_cards_preview_inherited($value, $inherited[$key]);
            if (!$defaults[$key]) unset($defaults[$key]);
        } else unset($defaults[$key]);
    }
    return $defaults;
}

function bioco_cards_preview_fill(array &$block, array $path, array $devices): void {
    $node =& $block['attrs'];
    foreach ($path as $key) {
        if (!is_array($node)) throw new RuntimeException('Unsupported saved native attribute shape.');
        if (!array_key_exists($key, $node)) $node[$key] = [];
        $node =& $node[$key];
    }
    if (!is_array($node)) throw new RuntimeException('Unsupported saved native control.');
    $original = $node;
    foreach ($devices as $device => $defaults) {
        $inherited = [];
        foreach (['desktop', 'tablet', 'phone'] as $ancestor) {
            if ($ancestor === $device) break;
            if (isset($original[$ancestor]['value'])) {
                if (!is_array($original[$ancestor]['value'])) throw new RuntimeException('Unsupported responsive native value.');
                $inherited = array_replace_recursive($inherited, $original[$ancestor]['value']);
            }
        }
        $defaults = bioco_cards_preview_inherited($defaults, $inherited);
        if (!$defaults) continue;
        if (!array_key_exists($device, $node)) $node[$device] = [];
        if (!is_array($node[$device])) throw new RuntimeException('Unsupported saved native breakpoint.');
        if (!array_key_exists('value', $node[$device])) $node[$device]['value'] = [];
        if (!is_array($node[$device]['value'])) throw new RuntimeException('Unsupported saved native value.');
        $node[$device]['value'] = bioco_cards_preview_missing($node[$device]['value'], $defaults);
    }
}

/** Remove only exact reviewed tokens; retain attribute IDs, unrelated classes and IDs. */
function bioco_cards_preview_remove(array &$block, array $remove): void {
    foreach (['advanced' => 'htmlAttributes', 'decoration' => 'attributes'] as $group => $control) {
        foreach ($block['attrs']['module'][$group][$control] ?? [] as $device => $states) {
            foreach ($states as $state => $value) {
                if ($device === 'desktop' && $state === 'value') continue;
                $classes = $group === 'advanced' ? [$value['class'] ?? ''] : array_column(array_filter($value['attributes'] ?? [], static fn($a) => ($a['name'] ?? '') === 'class'), 'value');
                foreach ($classes as $class) {
                    if (!is_string($class)) throw new RuntimeException('Unsupported saved class value.');
                    if (array_intersect($remove, preg_split('/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY))) throw new RuntimeException('Responsive or state-specific legacy classes require separate review.');
                }
            }
        }
    }
    $classes = preg_split('/\s+/', bioco_import_editorial_class($block), -1, PREG_SPLIT_NO_EMPTY);
    $class = implode(' ', array_values(array_diff($classes, $remove)));
    // Do not manufacture a second class control when only the editable list exists.
    if (array_key_exists('class', $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value'] ?? [])) {
        $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] = $class;
    }
    foreach ($block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [] as $index => $attribute) {
        if (($attribute['name'] ?? '') === 'class') $block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][$index]['value'] = $class;
    }
}

function bioco_cards_preview_presets(array $block): void {
    $hasPreset = static function(array $values) use (&$hasPreset): bool {
        foreach ($values as $key => $value) {
            if (in_array($key, ['modulePreset', 'groupPreset', 'presetId'], true) && !empty($value)) return true;
            if (is_array($value) && $hasPreset($value)) return true;
        }
        return false;
    };
    if ($hasPreset($block['attrs'] ?? [])) throw new RuntimeException('Effective shared preset capture is required before presentation conversion.');
    foreach ($block['innerBlocks'] ?? [] as $child) bioco_cards_preview_presets($child);
}

/** The exact old composer block placeholder is not an authored layout choice. */
function bioco_cards_preview_layout(array &$block, array $devices): void {
    if (!array_key_exists('layout', $block['attrs']['module']['decoration'] ?? [])) $block['attrs']['module']['decoration']['layout'] = [];
    $layout =& $block['attrs']['module']['decoration']['layout'];
    if ($layout === ['desktop' => ['value' => ['display' => 'block']]]) $layout = [];
    bioco_cards_preview_fill($block, ['module', 'decoration', 'layout'], $devices);
}

function bioco_cards_preview_row(array &$row): void {
    if (($row['blockName'] ?? '') !== 'divi/row') throw new RuntimeException('Expected a native family row.');
    bioco_cards_preview_fill($row, ['module', 'decoration', 'sizing'], ['desktop' => ['width' => '80%', 'maxWidth' => '1080px', 'alignment' => 'center']]);
    bioco_cards_preview_fill($row, ['module', 'decoration', 'spacing'], ['desktop' => [
        'padding' => ['top' => '0px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px'],
        'margin' => ['top' => '0px', 'bottom' => '0px'],
    ]]);
    bioco_cards_preview_remove($row, ['bioco-divi-row']);
}

function bioco_cards_preview_image(array &$image, array $ratio, string $radius, bool $card): void {
    if (($image['blockName'] ?? '') !== 'divi/image' || !empty($image['innerBlocks'])) throw new RuntimeException('Expected a native family image leaf.');
    // Installed Image uses advanced.sizing (not module.decoration.sizing).
    // Its sizing styler emits the crop on img; forceFullwidth sizes img and wrap.
    $sizing = ['forceFullwidth' => 'on', 'height' => 'auto', 'aspectRatio' => $ratio];
    foreach ($image['attrs']['module']['advanced']['sizing'] ?? [] as $states) {
        foreach ($states as $value) {
            if (is_array($value) && array_intersect(['width', 'maxWidth', 'minWidth'], array_keys($value))) unset($sizing['forceFullwidth']);
        }
    }
    bioco_cards_preview_fill($image, ['module', 'advanced', 'sizing'], ['desktop' => $sizing]);
    bioco_cards_preview_fill($image, ['image', 'decoration', 'fit'], ['desktop' => ['objectFit' => 'cover']]);
    bioco_cards_preview_fill($image, ['image', 'decoration', 'border'], ['desktop' => ['radius' => [
        'topLeft' => $radius, 'topRight' => $radius, 'bottomLeft' => $radius, 'bottomRight' => $radius,
    ]]]);
    $bottom = $card ? ['desktop' => ['margin' => ['bottom' => '9.27%']], 'tablet' => ['margin' => ['bottom' => '30px']]] : ['desktop' => ['margin' => ['bottom' => '0px']]];
    // Image's native spacing control also lives under advanced.
    bioco_cards_preview_fill($image, ['module', 'advanced', 'spacing'], $bottom);
    bioco_cards_preview_remove($image, [$card ? 'bioco-divi-card-image' : 'bioco-divi-gallery-strip-image']);
}

function bioco_cards_preview_section(array $section, string $family): array {
    bioco_cards_preview_presets($section);
    $base = 'bioco-divi-' . $family;
    $grids = [];
    foreach ($section['innerBlocks'] ?? [] as $index => $row) if (bioco_import_editorial_has_class($row, $base . '-row')) $grids[] = $index;
    if (count($grids) !== 1) throw new RuntimeException('Expected exactly one family grid row.');
    $gridIndex = $grids[0];
    $grid =& $section['innerBlocks'][$gridIndex];
    $classes = preg_split('/\s+/', bioco_import_editorial_class($grid), -1, PREG_SPLIT_NO_EMPTY);
    $match = static function(string $pattern) use ($classes): array {
        $matches = [];
        foreach ($classes as $class) if (preg_match($pattern, $class, $m)) $matches[] = $m;
        if (count($matches) !== 1) throw new RuntimeException('Missing or conflicting family presentation modifiers.');
        return $matches[0];
    };
    $columns = $match('/^' . preg_quote($base, '/') . '-row--desktop-([2-4])--mobile-([1-2])$/');
    $ratioMatch = $match('/^' . preg_quote($base, '/') . '-row--ratio-(1_1|4_3|' . ($family === 'cards-grid' ? '3_4' : '16_9') . ')$/');
    $fit = $match('/^' . preg_quote($base, '/') . '-row--fit-(cover|contain)$/');
    if ($fit[1] !== 'cover') throw new RuntimeException('Contain requires a separately verified native image background wrapper.');
    $round = $match('/^' . preg_quote($base, '/') . '-row--rounded-(none|sm|md|lg|xl)$/');
    $gap = $match('/^bioco-divi-gap-(sm|md|lg|xl)$/');
    [$rw, $rh] = explode('_', $ratioMatch[1]);
    $radius = ['none' => '0px', 'sm' => '8px', 'md' => '16px', 'lg' => '24px', 'xl' => '32px'][$round[1]];
    $gapValue = ['sm' => '16px', 'md' => '24px', 'lg' => '32px', 'xl' => '48px'][$gap[1]];
    if (empty($grid['innerBlocks'])) throw new RuntimeException('Family grid contains no columns.');
    bioco_cards_preview_layout($grid, [
        'desktop' => ['display' => 'grid', 'gridColumnWidths' => 'equal', 'gridColumnCount' => $columns[1], 'columnGap' => $gapValue, 'rowGap' => $gapValue],
        'tablet' => ['gridColumnCount' => $columns[1]], 'phone' => ['gridColumnCount' => $columns[2]],
    ]);
    foreach ($grid['innerBlocks'] as $index => &$column) {
        if (($column['blockName'] ?? '') !== 'divi/column') throw new RuntimeException('Family grid contains a non-column block.');
        $card = $family === 'cards-grid';
        $columnClass = $card ? $base . '-card' : $base . '-frame';
        if (!bioco_import_editorial_has_class($column, $columnClass)) throw new RuntimeException('Missing family column marker.');
        $images = 0;
        foreach ($column['innerBlocks'] as &$leaf) {
            if (bioco_import_editorial_has_class($leaf, $card ? 'bioco-divi-card-image' : $base . '-image')) {
                bioco_cards_preview_image($leaf, ['width' => $rw, 'height' => $rh], $radius, $card); ++$images;
            } elseif ($card && bioco_import_editorial_has_class($leaf, 'bioco-divi-card-title')) {
                if (($leaf['blockName'] ?? '') !== 'divi/heading') throw new RuntimeException('Expected a native card Heading.');
                bioco_cards_preview_fill($leaf, ['title', 'decoration', 'font', 'font'], ['desktop' => ['size' => '20px', 'weight' => '500', 'lineHeight' => '1em', 'color' => '#333333']]);
                bioco_cards_preview_fill($leaf, ['module', 'decoration', 'spacing'], ['desktop' => ['margin' => ['top' => '14px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px']]]);
                bioco_cards_preview_remove($leaf, ['bioco-divi-card-title']);
            } elseif ($card && bioco_import_editorial_has_class($leaf, 'bioco-divi-card-text')) {
                if (($leaf['blockName'] ?? '') !== 'divi/text') throw new RuntimeException('Expected native card Text.');
                bioco_cards_preview_fill($leaf, ['content', 'decoration', 'bodyFont', 'body', 'font'], ['desktop' => ['color' => '#4a4a4a']]);
                bioco_cards_preview_fill($leaf, ['module', 'decoration', 'spacing'], ['desktop' => ['margin' => ['top' => '6px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px']]]);
                bioco_cards_preview_remove($leaf, ['bioco-divi-card-text']);
            }
        }
        unset($leaf);
        if ($images !== 1) throw new RuntimeException('Expected one marked native image per family column.');
        bioco_cards_preview_fill($column, ['module', 'decoration', 'sizing'], ['desktop' => ['width' => 'auto']]);
        bioco_cards_preview_fill($column, ['module', 'decoration', 'spacing'], [
            'desktop' => ['margin' => ['left' => '0px', 'right' => $index === array_key_last($grid['innerBlocks']) ? '0px' : '5.5%', 'top' => '0px', 'bottom' => '0px']],
            'tablet' => ['margin' => ['right' => '0px']],
        ]);
        $remove = [$columnClass];
        if ($card) {
            $variants = array_values(array_intersect(['plain', 'soft', 'outlined'], array_map(static fn($c) => str_starts_with($c, 'bioco-divi-cards-grid-card--') ? substr($c, 28) : '', preg_split('/\s+/', bioco_import_editorial_class($column), -1, PREG_SPLIT_NO_EMPTY))));
            if (count($variants) !== 1) throw new RuntimeException('Missing or conflicting card surface modifier.');
            $variant = $variants[0]; $remove[] = $columnClass . '--' . $variant;
            bioco_cards_preview_layout($column, ['desktop' => ['display' => 'flex', 'flexDirection' => 'column', 'columnGap' => '0px', 'rowGap' => '0px']]);
            bioco_cards_preview_fill($column, ['module', 'decoration', 'background'], ['desktop' => ['color' => $variant === 'plain' ? 'transparent' : '#ffffff']]);
            $padding = $variant === 'plain' ? '0px' : '14px';
            bioco_cards_preview_fill($column, ['module', 'decoration', 'spacing'], ['desktop' => ['padding' => array_fill_keys(['top', 'right', 'bottom', 'left'], $padding)]]);
            if ($variant === 'outlined') bioco_cards_preview_fill($column, ['module', 'decoration', 'border'], ['desktop' => ['styles' => ['all' => ['width' => '1px', 'style' => 'solid', 'color' => '#e1e4e8']]]]);
        }
        bioco_cards_preview_remove($column, $remove);
    }
    unset($column);
    foreach ($section['innerBlocks'] as $index => &$row) {
        bioco_cards_preview_row($row);
        if ($index === $gridIndex) bioco_cards_preview_remove($row, [$base . '-row', $columns[0], $ratioMatch[0], $fit[0], $round[0], $gap[0]]);
        else {
            $roles = array_intersect([$base . '-header', $base . '-actions'], preg_split('/\s+/', bioco_import_editorial_class($row), -1, PREG_SPLIT_NO_EMPTY));
            if (count($roles) !== 1) throw new RuntimeException('Unrecognized family sibling row.');
            bioco_cards_preview_remove($row, array_values($roles));
        }
    }
    unset($row);
    bioco_cards_preview_remove($section, [$base]);
    return $section;
}

/** No WordPress reads/writes, render filters, enqueue or public registration. */
function bioco_editorial_cards_preview(array $blocks): array {
    $report = ['cards-grid' => 0, 'gallery-strip' => 0, 'refused' => []];
    $visit = static function(array $items, array $path) use (&$visit, &$report): array {
        foreach ($items as $index => &$block) {
            $family = null;
            if (($block['blockName'] ?? '') === 'divi/section') {
                foreach (['cards-grid', 'gallery-strip'] as $candidate) if (bioco_import_editorial_has_class($block, 'bioco-divi-' . $candidate)) {
                    if ($family !== null) throw new RuntimeException('Conflicting editorial family markers.');
                    $family = $candidate;
                }
            }
            if ($family !== null) {
                try { $block = bioco_cards_preview_section($block, $family); ++$report[$family]; }
                catch (RuntimeException $error) { $report['refused'][] = ['path' => [...$path, $index], 'family' => $family, 'reason' => $error->getMessage()]; }
            } elseif (!empty($block['innerBlocks'])) $block['innerBlocks'] = $visit($block['innerBlocks'], [...$path, $index]);
        }
        unset($block);
        return $items;
    };
    return [$visit($blocks, []), $report];
}
