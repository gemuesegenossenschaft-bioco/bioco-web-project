<?php
/** Pure native presentation preview for the saved homepage families. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/editorial-layouts.php';

/** Missing means absent: explicit blank, null, zero and false remain authored. */
function bioco_home_preview_missing(array $saved, array $defaults): array {
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $saved)) $saved[$key] = $value;
        elseif (is_array($saved[$key]) && is_array($value)) $saved[$key] = bioco_home_preview_missing($saved[$key], $value);
    }
    return $saved;
}

/** Do not create a responsive fallback which masks an authored inherited value. */
function bioco_home_preview_inherited(array $defaults, array $inherited): array {
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $inherited)) continue;
        if (is_array($value) && is_array($inherited[$key])) {
            $defaults[$key] = bioco_home_preview_inherited($value, $inherited[$key]);
            if (!$defaults[$key]) unset($defaults[$key]);
        } else unset($defaults[$key]);
    }
    return $defaults;
}

function bioco_home_preview_fill(array &$block, array $path, array $devices): void {
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
        $defaults = bioco_home_preview_inherited($defaults, $inherited);
        if (!$defaults) continue;
        if (!array_key_exists($device, $node)) $node[$device] = [];
        if (!is_array($node[$device])) throw new RuntimeException('Unsupported saved native breakpoint.');
        if (!array_key_exists('value', $node[$device])) $node[$device]['value'] = [];
        if (!is_array($node[$device]['value'])) throw new RuntimeException('Unsupported saved native value.');
        $node[$device]['value'] = bioco_home_preview_missing($node[$device]['value'], $defaults);
    }
}

/** Remove only exact reviewed tokens; retain attribute IDs, unrelated classes and IDs. */
function bioco_home_preview_remove(array &$block, array $remove): void {
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

function bioco_home_preview_presets(array $block): void {
    $hasPreset = static function(array $values) use (&$hasPreset): bool {
        foreach ($values as $key => $value) {
            if (in_array($key, ['modulePreset', 'groupPreset', 'presetId'], true) && !empty($value)) return true;
            if (is_array($value) && $hasPreset($value)) return true;
        }
        return false;
    };
    if ($hasPreset($block['attrs'] ?? [])) throw new RuntimeException('Effective shared preset capture is required before presentation conversion.');
    foreach ($block['innerBlocks'] ?? [] as $child) bioco_home_preview_presets($child);
}

/** The exact old composer block placeholder is not an authored layout choice. */
function bioco_home_preview_layout(array &$block, array $devices): void {
    if (!array_key_exists('layout', $block['attrs']['module']['decoration'] ?? [])) $block['attrs']['module']['decoration']['layout'] = [];
    $layout =& $block['attrs']['module']['decoration']['layout'];
    if ($layout === ['desktop' => ['value' => ['display' => 'block']]]) $layout = [];
    bioco_home_preview_fill($block, ['module', 'decoration', 'layout'], $devices);
}

function bioco_home_preview_has(array $block, string $class): bool {
    return in_array($class, preg_split('/\s+/', bioco_import_editorial_class($block), -1, PREG_SPLIT_NO_EMPTY), true);
}

function bioco_home_preview_one(array $blocks, string $name, string $class = ''): int {
    $matches = [];
    foreach ($blocks as $index => $block) {
        if (($block['blockName'] ?? '') === $name && ($class === '' || bioco_home_preview_has($block, $class))) $matches[] = $index;
    }
    if (count($matches) !== 1) throw new RuntimeException('Expected one saved ' . $name . ' ' . $class . '.');
    return $matches[0];
}

function bioco_home_preview_zero(): array {
    return array_fill_keys(['top', 'right', 'bottom', 'left'], '0px');
}

function bioco_home_preview_scalar(array &$block, array $path, string $value): void {
    $node =& $block['attrs'];
    foreach ($path as $key) {
        if (!is_array($node)) throw new RuntimeException('Unsupported saved scalar native control.');
        if (!array_key_exists($key, $node)) $node[$key] = [];
        $node =& $node[$key];
    }
    if (!is_array($node)) throw new RuntimeException('Unsupported saved scalar native control.');
    if (!array_key_exists('desktop', $node)) $node['desktop'] = [];
    if (!is_array($node['desktop'])) throw new RuntimeException('Unsupported saved scalar breakpoint.');
    if (!array_key_exists('value', $node['desktop'])) $node['desktop']['value'] = $value;
}

function bioco_home_preview_spacing(array &$block, array $desktop, array $phone = [], array $tablet = []): void {
    bioco_home_preview_fill($block, ['module', 'decoration', 'spacing'], ['desktop' => $desktop, 'tablet' => $tablet, 'phone' => $phone]);
}

function bioco_home_preview_row(array &$row, bool $sharedWidth = false): void {
    $maxWidth = $sharedWidth ? 'var(--gvid-bioco-' . substr(hash('sha256', '--wp--style--global--content-size'), 0, 16) . ')' : 'none';
    bioco_home_preview_fill($row, ['module', 'decoration', 'sizing'], ['desktop' => ['width' => '100%', 'maxWidth' => $maxWidth, 'alignment' => 'center']]);
    bioco_home_preview_spacing($row, ['padding' => bioco_home_preview_zero(), 'margin' => ['top' => '0px', 'bottom' => '0px']]);
}

function bioco_home_preview_column(array &$column, string $phoneBottom = '0px'): void {
    bioco_home_preview_fill($column, ['module', 'decoration', 'sizing'], ['desktop' => ['width' => 'auto', 'maxWidth' => 'none']]);
    bioco_home_preview_spacing($column, ['padding' => bioco_home_preview_zero(), 'margin' => bioco_home_preview_zero()],
        ['margin' => ['bottom' => $phoneBottom]]);
}

/** #220 matched-row correction uses the existing shared native content width. */
function bioco_home_preview_container(array &$section): void {
    bioco_home_preview_fill($section, ['module', 'decoration', 'sizing'], [
        'desktop' => ['width' => '100%', 'maxWidth' => 'none', 'alignment' => 'center'],
    ]);
    bioco_home_preview_spacing($section, ['padding' => ['top' => '0px', 'right' => '6vw', 'bottom' => '0px', 'left' => '6vw'],
        'margin' => ['left' => 'auto', 'right' => 'auto']]);
}

function bioco_home_preview_heading(array &$heading, array $desktop, array $phone, string $desktopPadding = '10px', string $phonePadding = '0px', array $tablet = [], ?string $tabletPadding = null): void {
    bioco_home_preview_fill($heading, ['title', 'decoration', 'font', 'font'], ['desktop' => $desktop, 'tablet' => $tablet, 'phone' => $phone]);
    // Divi's Heading module has no separate heading-element Design spacing group.
    bioco_home_preview_fill($heading, ['css'], ['desktop' => ['mainElement' => 'padding-bottom:' . $desktopPadding . ';'],
        'tablet' => $tabletPadding === null ? [] : ['mainElement' => 'padding-bottom:' . $tabletPadding . ';'],
        'phone' => ['mainElement' => 'padding-bottom:' . $phonePadding . ';']]);
}

function bioco_home_preview_text(array &$text, array $desktop, array $phone): void {
    bioco_home_preview_fill($text, ['content', 'decoration', 'bodyFont', 'body', 'font'], ['desktop' => $desktop, 'phone' => $phone]);
}

/** A generated fullwidth switch must not mask an authored Image width control. */
function bioco_home_preview_image(array &$image, array $desktop, array $phone): void {
    $authored = false;
    foreach ($image['attrs']['module']['advanced']['sizing'] ?? [] as $states) {
        if (!is_array($states)) throw new RuntimeException('Unsupported native Image sizing.');
        foreach ($states as $value) {
            if (!is_array($value)) throw new RuntimeException('Unsupported native Image sizing value.');
            foreach (['width', 'minWidth', 'maxWidth'] as $key) if (array_key_exists($key, $value)) $authored = true;
        }
    }
    if (!$authored) $desktop['forceFullwidth'] = 'on';
    bioco_home_preview_fill($image, ['module', 'advanced', 'sizing'], ['desktop' => $desktop, 'phone' => $phone]);
    bioco_home_preview_fill($image, ['image', 'decoration', 'fit'], ['desktop' => ['objectFit' => 'cover']]);
    bioco_home_preview_fill($image, ['module', 'advanced', 'spacing'], ['desktop' => ['margin' => bioco_home_preview_zero()]]);
}

/** Preserve the measured button palette, including the existing CTA secondary. */
function bioco_home_preview_button(array &$button, bool $feature, bool $secondary, bool $live = false): void {
    $outline = !$feature && $secondary && !$live;
    $font = ['size' => '16px', 'weight' => '700', 'lineHeight' => '1.5em', 'color' => $outline ? '#2E7D32' : '#FFFFFF'];
    bioco_home_preview_fill($button, ['button', 'decoration', 'font', 'font'], ['desktop' => $font]);
    bioco_home_preview_fill($button, ['button', 'decoration', 'background'], ['desktop' => ['color' => $outline ? '#FFFFFF' : '#2E7D32']]);
    bioco_home_preview_fill($button, ['button', 'decoration', 'border'], ['desktop' => [
        'radius' => ['topLeft' => '28px', 'topRight' => '22px', 'bottomLeft' => '18px', 'bottomRight' => '26px'],
        'styles' => ['all' => ['width' => '2px', 'style' => 'solid', 'color' => $outline ? 'transparent' : '#2E7D32']],
    ]]);
    bioco_home_preview_fill($button, ['module', 'decoration', 'spacing'], ['desktop' => ['padding' => ['top' => '12px', 'right' => '24px', 'bottom' => '12px', 'left' => '24px']]]);
    bioco_home_preview_fill($button, ['button', 'decoration', 'sizing'], ['desktop' => ['minHeight' => '52px'], 'phone' => $feature && !$live ? ['width' => '100%'] : []]);
    bioco_home_preview_fill($button, ['button', 'decoration', 'button'], ['desktop' => ['enable' => 'on', 'icon' => ['enable' => 'off']]]);
    bioco_home_preview_spacing($button, ['margin' => ['bottom' => '0px']], !$feature && !$secondary ? ['margin' => ['bottom' => '36px']] : []);
    bioco_home_preview_remove($button, ['bioco-home-button', 'bioco-home-button--primary', 'bioco-home-button--secondary']);
}

function bioco_home_preview_overlay(): array {
    $overlay = ['blockName' => 'divi/text', 'attrs' => [
        'module' => ['meta' => ['adminLabel' => ['desktop' => ['value' => 'Hero Verlauf']]],
            'decoration' => ['attributes' => ['desktop' => ['value' => ['attributes' => [
                ['id' => 'bioco-home-overlay-aria', 'name' => 'aria-hidden', 'value' => 'true', 'adminLabel' => 'Decorative layer'],
            ]]]]]],
        'content' => ['innerContent' => ['desktop' => ['value' => '<span aria-hidden="true"></span>']]],
    ], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => ["\n"]];
    bioco_home_preview_fill($overlay, ['module', 'decoration', 'position'], ['desktop' => ['mode' => 'absolute',
        'origin' => ['absolute' => 'top left'], 'offset' => ['horizontal' => '0px', 'vertical' => '0px']]]);
    bioco_home_preview_fill($overlay, ['module', 'decoration', 'sizing'], ['desktop' => ['width' => '100%', 'height' => '100%']]);
    bioco_home_preview_spacing($overlay, ['padding' => bioco_home_preview_zero(), 'margin' => bioco_home_preview_zero()]);
    bioco_home_preview_scalar($overlay, ['module', 'decoration', 'zIndex'], '1');
    bioco_home_preview_fill($overlay, ['module', 'decoration', 'background'], ['desktop' => ['gradient' => [
        'enabled' => 'on', 'type' => 'linear', 'direction' => '180deg', 'stops' => [
            ['color' => 'rgba(0,0,0,0.32)', 'position' => '0'], ['color' => 'rgba(0,0,0,0)', 'position' => '40'],
            ['color' => 'rgba(0,0,0,0)', 'position' => '65'], ['color' => 'rgba(0,0,0,0.2)', 'position' => '100'],
        ],
    ]]]);
    bioco_home_preview_fill($overlay, ['css'], ['desktop' => ['mainElement' => 'pointer-events:none;']]);
    return $overlay;
}

function bioco_home_preview_hero(array &$section): void {
    if (count($section['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unknown hero section structure.');
    $row =& $section['innerBlocks'][bioco_home_preview_one($section['innerBlocks'], 'divi/row', 'bioco-home-hero-row')];
    if (count($row['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unknown hero row structure.');
    $column =& $row['innerBlocks'][bioco_home_preview_one($row['innerBlocks'], 'divi/column', 'bioco-home-hero-column')];
    $imageIndex = bioco_home_preview_one($column['innerBlocks'], 'divi/image', 'bioco-home-hero-image');
    $titleIndex = bioco_home_preview_one($column['innerBlocks'], 'divi/heading', 'bioco-home-hero-title');
    $textIndex = bioco_home_preview_one($column['innerBlocks'], 'divi/text', 'bioco-home-hero-subtitle');
    bioco_home_preview_container($section);
    bioco_home_preview_fill($section, ['module', 'decoration', 'background'], ['desktop' => ['color' => 'transparent']]);
    bioco_home_preview_spacing($section, ['margin' => ['top' => '0px', 'bottom' => '0px']]);
    bioco_home_preview_row($row, true);
    bioco_home_preview_fill($row, ['module', 'decoration', 'sizing'], ['desktop' => ['height' => '585px', 'minHeight' => '585px'], 'phone' => ['height' => '552px', 'minHeight' => '552px']]);
    bioco_home_preview_fill($row, ['module', 'decoration', 'border'], ['desktop' => ['radius' => array_fill_keys(['topLeft', 'topRight', 'bottomLeft', 'bottomRight'], '24px')]]);
    bioco_home_preview_fill($row, ['module', 'decoration', 'overflow'], ['desktop' => ['x' => 'hidden', 'y' => 'hidden']]);
    bioco_home_preview_layout($column, ['desktop' => ['display' => 'flex', 'flexDirection' => 'column', 'justifyContent' => 'flex-end', 'columnGap' => '0px', 'rowGap' => '0px']]);
    bioco_home_preview_fill($column, ['module', 'decoration', 'position'], ['desktop' => ['mode' => 'relative']]);
    bioco_home_preview_fill($column, ['module', 'decoration', 'sizing'], ['desktop' => ['width' => '100%', 'height' => '585px', 'minHeight' => '585px'], 'phone' => ['height' => '552px', 'minHeight' => '552px']]);
    bioco_home_preview_spacing($column, ['padding' => ['top' => '48px', 'right' => '184.4px', 'bottom' => '128px', 'left' => '184.4px'], 'margin' => bioco_home_preview_zero()],
        ['padding' => array_fill_keys(['top', 'right', 'bottom', 'left'], '24px')]);
    $image =& $column['innerBlocks'][$imageIndex];
    bioco_home_preview_image($image, ['height' => '585px'], ['height' => '552px']);
    bioco_home_preview_fill($image, ['module', 'decoration', 'position'], ['desktop' => ['mode' => 'absolute', 'origin' => ['absolute' => 'top left'], 'offset' => ['horizontal' => '0px', 'vertical' => '0px']]]);
    bioco_home_preview_scalar($image, ['module', 'decoration', 'zIndex'], '0');
    bioco_home_preview_remove($image, ['bioco-home-hero-image']);
    $title =& $column['innerBlocks'][$titleIndex];
    bioco_home_preview_heading($title, ['size' => '40px', 'weight' => '700', 'lineHeight' => '1.3em', 'color' => '#FFFFFF'], [], '0px');
    bioco_home_preview_spacing($title, ['margin' => ['bottom' => '13px']], ['margin' => ['bottom' => '14px']]);
    $text =& $column['innerBlocks'][$textIndex];
    bioco_home_preview_text($text, ['size' => '20px', 'weight' => '400', 'lineHeight' => '1.7em', 'color' => '#FFFFFF'], ['size' => '16px']);
    bioco_home_preview_spacing($text, ['margin' => ['bottom' => '0px']]);
    foreach ([$titleIndex, $textIndex] as $index) {
        bioco_home_preview_fill($column['innerBlocks'][$index], ['module', 'decoration', 'sizing'], ['desktop' => ['maxWidth' => '520px'], 'phone' => ['maxWidth' => '100%']]);
        bioco_home_preview_fill($column['innerBlocks'][$index], ['module', 'decoration', 'position'], ['desktop' => ['mode' => 'relative']]);
        bioco_home_preview_scalar($column['innerBlocks'][$index], ['module', 'decoration', 'zIndex'], '2');
    }
    bioco_home_preview_remove($title, ['bioco-home-hero-title']);
    bioco_home_preview_remove($text, ['bioco-home-hero-subtitle']);
    // Insert one null at the original child boundary; opaque fragments stay exact.
    $nulls = array_keys(array_filter($column['innerContent'] ?? [], static fn($value): bool => $value === null));
    if (count($nulls) !== count($column['innerBlocks'])) throw new RuntimeException('Unsupported hero child placeholders.');
    array_splice($column['innerBlocks'], $imageIndex + 1, 0, [bioco_home_preview_overlay()]);
    array_splice($column['innerContent'], $nulls[$imageIndex] + 1, 0, [null]);
    bioco_home_preview_remove($column, ['bioco-home-hero-column']);
    bioco_home_preview_remove($row, ['bioco-home-hero-row']);
    bioco_home_preview_remove($section, ['bioco-home-hero']);
}

function bioco_home_preview_feature(array &$section, bool $followsHero): void {
    if (count($section['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unknown feature section structure.');
    $row =& $section['innerBlocks'][bioco_home_preview_one($section['innerBlocks'], 'divi/row', 'bioco-home-feature-row')];
    if (count($row['innerBlocks'] ?? []) !== 2) throw new RuntimeException('Unknown feature row structure.');
    $contentIndex = bioco_home_preview_one($row['innerBlocks'], 'divi/column', 'bioco-home-feature-content');
    $mediaIndex = bioco_home_preview_one($row['innerBlocks'], 'divi/column', 'bioco-home-feature-media');
    if ($contentIndex === $mediaIndex) throw new RuntimeException('Conflicting feature columns.');
    bioco_home_preview_container($section);
    bioco_home_preview_spacing($section, ['margin' => ['top' => $followsHero ? '142px' : '96px', 'bottom' => '96px']], ['margin' => ['top' => '48px', 'bottom' => '48px']]);
    bioco_home_preview_fill($section, ['module', 'decoration', 'background'], ['desktop' => ['color' => 'transparent']]);
    bioco_home_preview_row($row, true);
    bioco_home_preview_layout($row, ['desktop' => ['display' => 'grid', 'gridColumnWidths' => 'equal', 'gridColumnCount' => '2', 'alignItems' => 'center', 'columnGap' => '64px', 'rowGap' => '64px'],
        'phone' => ['gridColumnCount' => '1', 'columnGap' => '24px', 'rowGap' => '24px']]);
    $content =& $row['innerBlocks'][$contentIndex];
    $media =& $row['innerBlocks'][$mediaIndex];
    bioco_home_preview_column($content, '20px');
    bioco_home_preview_column($media, $mediaIndex === 0 ? '30px' : '0px');
    if ($contentIndex !== 0) bioco_home_preview_fill($content, ['module', 'decoration', 'order'], ['phone' => ['order' => '-1']]);
    $image =& $media['innerBlocks'][bioco_home_preview_one($media['innerBlocks'], 'divi/image', 'bioco-home-feature-image')];
    bioco_home_preview_image($image, ['height' => 'auto', 'aspectRatio' => ['width' => '4', 'height' => '3']], ['height' => '300px']);
    bioco_home_preview_fill($image, ['image', 'decoration', 'border'], ['desktop' => ['radius' => array_fill_keys(['topLeft', 'topRight', 'bottomLeft', 'bottomRight'], '24px')]]);
    bioco_home_preview_remove($image, ['bioco-home-feature-image']);
    $heading =& $content['innerBlocks'][bioco_home_preview_one($content['innerBlocks'], 'divi/heading', 'bioco-home-feature-title')];
    bioco_home_preview_heading($heading, ['size' => '26px', 'weight' => '500', 'lineHeight' => '1em', 'color' => '#333333'], ['size' => '24px', 'lineHeight' => '1.2em']);
    bioco_home_preview_spacing($heading, ['margin' => ['bottom' => '5.82%']], ['margin' => ['bottom' => '24px']]);
    bioco_home_preview_remove($heading, ['bioco-home-feature-title']);
    $text =& $content['innerBlocks'][bioco_home_preview_one($content['innerBlocks'], 'divi/text', 'bioco-home-feature-text')];
    bioco_home_preview_text($text, ['size' => '16px', 'weight' => '500', 'lineHeight' => '1.7em', 'color' => '#4A4A4A'], ['weight' => '400', 'lineHeight' => '1.6em']);
    bioco_home_preview_spacing($text, ['margin' => ['bottom' => '5.82%']], ['margin' => ['bottom' => '84px']]);
    bioco_home_preview_remove($text, ['bioco-home-feature-text']);
    foreach ($content['innerBlocks'] as &$child) if (($child['blockName'] ?? '') === 'divi/button' && bioco_home_preview_has($child, 'bioco-home-button')) {
        bioco_home_preview_button($child, true, bioco_home_preview_has($child, 'bioco-home-button--secondary'));
    }
    unset($child);
    bioco_home_preview_remove($content, ['bioco-home-feature-content']);
    bioco_home_preview_remove($media, ['bioco-home-feature-media']);
    bioco_home_preview_remove($row, ['bioco-home-feature-row']);
    bioco_home_preview_remove($section, ['bioco-home-feature']);
}

function bioco_home_preview_cta(array &$section): void {
    if (count($section['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unknown CTA section structure.');
    $row =& $section['innerBlocks'][bioco_home_preview_one($section['innerBlocks'], 'divi/row')];
    if (count($row['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unknown CTA row structure.');
    $column =& $row['innerBlocks'][bioco_home_preview_one($row['innerBlocks'], 'divi/column', 'bioco-home-cta-content')];
    bioco_home_preview_fill($section, ['module', 'decoration', 'sizing'], ['desktop' => ['width' => 'calc(100% - 416px)', 'maxWidth' => '1024px', 'height' => '184px', 'alignment' => 'center'],
        'phone' => ['width' => 'calc(100% - 46.8px)', 'height' => '421px']]);
    bioco_home_preview_spacing($section, ['padding' => bioco_home_preview_zero(), 'margin' => ['top' => '96px', 'right' => 'auto', 'bottom' => '96px', 'left' => 'auto']],
        ['margin' => ['top' => '64px', 'bottom' => '0px']]);
    bioco_home_preview_fill($section, ['module', 'decoration', 'background'], ['desktop' => ['color' => 'transparent']]);
    bioco_home_preview_row($row);
    bioco_home_preview_column($column);
    bioco_home_preview_layout($column, ['desktop' => ['display' => 'flex', 'flexDirection' => 'row', 'flexWrap' => 'wrap', 'alignContent' => 'flex-start', 'columnGap' => '12px', 'rowGap' => '12px'],
        'phone' => ['flexDirection' => 'column', 'flexWrap' => 'nowrap', 'alignItems' => 'flex-start', 'columnGap' => '0px', 'rowGap' => '0px']]);
    $heading =& $column['innerBlocks'][bioco_home_preview_one($column['innerBlocks'], 'divi/heading', 'bioco-home-cta-title')];
    bioco_home_preview_heading($heading, ['size' => '26px', 'weight' => '500', 'lineHeight' => '1em', 'color' => '#333333'], ['size' => '22.4px', 'weight' => '700', 'lineHeight' => '1.2em', 'color' => '#1A1A1A']);
    bioco_home_preview_fill($heading, ['module', 'decoration', 'sizing'], ['desktop' => ['width' => '100%']]);
    bioco_home_preview_spacing($heading, ['margin' => ['bottom' => '2.75%']], ['margin' => ['bottom' => '12px']]);
    bioco_home_preview_remove($heading, ['bioco-home-cta-title']);
    $text =& $column['innerBlocks'][bioco_home_preview_one($column['innerBlocks'], 'divi/text', 'bioco-home-cta-text')];
    bioco_home_preview_text($text, ['size' => '16px', 'weight' => '500', 'lineHeight' => '1.7em', 'color' => '#4A4A4A'], ['weight' => '400', 'lineHeight' => '1.6em']);
    bioco_home_preview_fill($text, ['module', 'decoration', 'sizing'], ['desktop' => ['width' => '100%']]);
    bioco_home_preview_spacing($text, ['margin' => ['bottom' => '2.75%']], ['margin' => ['bottom' => '28px']]);
    bioco_home_preview_remove($text, ['bioco-home-cta-text']);
    foreach ($column['innerBlocks'] as &$child) if (($child['blockName'] ?? '') === 'divi/button' && bioco_home_preview_has($child, 'bioco-home-button')) {
        bioco_home_preview_button($child, false, bioco_home_preview_has($child, 'bioco-home-button--secondary'));
    }
    unset($child);
    bioco_home_preview_remove($column, ['bioco-home-cta-content']);
    bioco_home_preview_remove($section, ['bioco-home-cta']);
}

/** Only these wrappers are adopted; native event branches retain their presets/data. */
function bioco_home_preview_live_events(array &$section, bool $live): void {
    $base = $live ? 'bioco-home-live' : 'bioco-dynamic';
    if (count($section['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unknown live/event section structure.');
    $row =& $section['innerBlocks'][bioco_home_preview_one($section['innerBlocks'], 'divi/row', $base . '-row')];
    if (count($row['innerBlocks'] ?? []) !== 1) throw new RuntimeException('Unknown live/event row structure.');
    $column =& $row['innerBlocks'][bioco_home_preview_one($row['innerBlocks'], 'divi/column', $base . '-column')];
    $titleClass = $live ? $base . '-title' : $base . '-section-title';
    $heading =& $column['innerBlocks'][bioco_home_preview_one($column['innerBlocks'], 'divi/heading', $titleClass)];
    if (!empty($heading['innerBlocks'])) throw new RuntimeException('Unknown live/event heading structure.');
    $owned = [$section, $row, $column, $heading];
    $buttonIndex = null;
    if ($live) {
        $buttonIndex = bioco_home_preview_one($column['innerBlocks'], 'divi/button', 'bioco-home-button');
        if (!empty($column['innerBlocks'][$buttonIndex]['innerBlocks'])) throw new RuntimeException('Unknown live button structure.');
        $owned[] = $column['innerBlocks'][$buttonIndex];
    }
    foreach ($owned as $part) {
        $part['innerBlocks'] = [];
        bioco_home_preview_presets($part);
        if (!empty($part['attrs']['css'])) throw new RuntimeException('Authored live/event CSS requires separate review.');
    }
    bioco_home_preview_fill($section, ['module', 'decoration', 'sizing'], [
        'desktop' => ['width' => $live ? 'min(calc(100% - 212.8px), 1227.2px)' : '100%', 'maxWidth' => 'none', 'alignment' => 'center'],
        'tablet' => $live ? ['width' => 'calc(100% - 46.8px)'] : [],
        'phone' => $live ? ['width' => 'calc(100% - 46.8px)'] : [],
    ]);
    bioco_home_preview_fill($section, ['module', 'decoration', 'background'], ['desktop' => ['color' => $live ? 'transparent' : '#F5F1E8']]);
    $mobileSpacing = [
        'margin' => ['top' => $live ? '48px' : '32px', 'bottom' => $live ? '48px' : '0px'],
        'padding' => $live ? ['top' => '48px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px'] : bioco_home_preview_zero(),
    ];
    bioco_home_preview_spacing($section, [
        'margin' => ['top' => '96px', 'right' => $live ? 'auto' : '0px', 'bottom' => $live ? '96px' : '0px', 'left' => $live ? 'auto' : '0px'],
        'padding' => $live ? ['top' => '96px', 'right' => '86.4px', 'bottom' => '0px', 'left' => '86.4px'] : bioco_home_preview_zero(),
    ], $mobileSpacing, $mobileSpacing);
    bioco_home_preview_fill($row, ['module', 'decoration', 'sizing'], [
        'desktop' => ['width' => $live ? '100%' : 'min(calc(100% - 212.8px), 1227.2px)', 'maxWidth' => 'none', 'alignment' => 'center'],
        'tablet' => $live ? [] : ['width' => 'calc(100% - 93.6px)'],
        'phone' => $live ? [] : ['width' => 'calc(100% - 93.6px)'],
    ]);
    $rowMargin = bioco_home_preview_zero();
    if (!$live) $rowMargin['left'] = $rowMargin['right'] = 'auto';
    bioco_home_preview_spacing($row, ['margin' => $rowMargin,
        'padding' => $live ? bioco_home_preview_zero() : ['top' => '0px', 'right' => '86.4px', 'bottom' => '0px', 'left' => '86.4px']],
        ['padding' => bioco_home_preview_zero()], ['padding' => bioco_home_preview_zero()]);
    bioco_home_preview_fill($column, ['module', 'decoration', 'sizing'], ['desktop' => ['width' => '100%', 'maxWidth' => 'none']]);
    bioco_home_preview_spacing($column, ['margin' => bioco_home_preview_zero(), 'padding' => bioco_home_preview_zero()]);
    $mobileFont = $live ? ['size' => '22.4px', 'weight' => '700', 'lineHeight' => '1.2em', 'color' => '#1A1A1A'] : [];
    bioco_home_preview_heading($heading, ['size' => '26px', 'weight' => '500', 'lineHeight' => '1em', 'color' => '#333333'],
        $mobileFont, '10px', $live ? '0px' : '10px', $mobileFont, $live ? '0px' : '10px');
    if (!$live) bioco_home_preview_spacing($heading, ['margin' => ['bottom' => '32px']]);
    if ($live) {
        $button =& $column['innerBlocks'][$buttonIndex];
        // Live secondary is green/white, unlike the separate CTA secondary.
        bioco_home_preview_button($button, false, true, true);
        // The measured live secondary stays green/white on hover; no lift.
        $font =& $button['attrs']['button']['decoration']['font']['font']['desktop'];
        $font = bioco_home_preview_missing($font, ['hover' => ['color' => '#FFFFFF']]);
        $background =& $button['attrs']['button']['decoration']['background']['desktop'];
        $background = bioco_home_preview_missing($background, ['hover' => ['color' => '#2E7D32']]);
        $border =& $button['attrs']['button']['decoration']['border']['desktop'];
        $border = bioco_home_preview_missing($border, ['hover' => ['styles' => ['all' => ['color' => '#2E7D32']]]]);
        bioco_home_preview_fill($button, ['button', 'decoration', 'boxShadow'], ['desktop' => [
            'style' => 'preset1', 'horizontal' => '0px', 'vertical' => '8px', 'blur' => '16px', 'spread' => '0px', 'color' => 'rgba(0,0,0,.12)',
        ]]);
        $class = trim(bioco_import_editorial_class($button) . ' bioco-home-live-shape');
        if (array_key_exists('class', $button['attrs']['module']['advanced']['htmlAttributes']['desktop']['value'] ?? [])) {
            $button['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] = $class;
        }
        foreach ($button['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [] as $index => $attribute) {
            if (($attribute['name'] ?? '') === 'class') $button['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][$index]['value'] = $class;
        }
    }
    bioco_home_preview_remove($heading, [$titleClass]);
    bioco_home_preview_remove($column, [$base . '-column']);
    bioco_home_preview_remove($row, [$base . '-row']);
    bioco_home_preview_remove($section, [$live ? $base : $base . '-section']);
}

/** Atomic per target section; unknown structures/presets remain unchanged. */
function bioco_home_presentation_preview(array $blocks): array {
    $report = ['hero' => 0, 'feature' => 0, 'cta' => 0, 'live' => 0, 'events' => 0, 'refused' => []];
    $walk = function(array $items, array $path = []) use (&$walk, &$report): array {
        $previousFamily = '';
        $homeContext = false;
        foreach ($items as $item) foreach (['hero', 'feature', 'cta', 'live'] as $marker) {
            if (($item['blockName'] ?? '') === 'divi/section' && bioco_home_preview_has($item, 'bioco-home-' . $marker)) $homeContext = true;
        }
        foreach ($items as $index => &$block) {
            $here = array_merge($path, [$index]);
            $family = '';
            if (($block['blockName'] ?? '') === 'divi/section') foreach (['hero', 'feature', 'cta', 'live'] as $possible) {
                if (bioco_home_preview_has($block, 'bioco-home-' . $possible)) {
                    if ($family !== '') throw new RuntimeException('Conflicting homepage section families.');
                    $family = $possible;
                }
            }
            if ($family === '' && $homeContext && ($block['blockName'] ?? '') === 'divi/section'
                && bioco_home_preview_has($block, 'bioco-dynamic-section')) $family = 'events';
            $followsHero = $previousFamily === 'hero';
            $previousFamily = $family;
            if ($family === '') {
                if ($block['innerBlocks'] ?? []) $block['innerBlocks'] = $walk($block['innerBlocks'], $here);
                continue;
            }
            $candidate = $block;
            try {
                if (!in_array($family, ['live', 'events'], true)) bioco_home_preview_presets($candidate);
                if ($family === 'hero') bioco_home_preview_hero($candidate);
                elseif ($family === 'feature') bioco_home_preview_feature($candidate, $followsHero);
                elseif ($family === 'cta') bioco_home_preview_cta($candidate);
                else bioco_home_preview_live_events($candidate, $family === 'live');
                $block = $candidate;
                $report[$family]++;
            } catch (RuntimeException $error) {
                $report['refused'][] = ['path' => $here, 'family' => $family, 'reason' => $error->getMessage()];
            }
        }
        unset($block);
        return $items;
    };
    return [$walk($blocks), $report];
}
