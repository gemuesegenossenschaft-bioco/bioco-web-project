<?php
/** Pure preview of native typography for legacy media/rich editorial sections. */
if (!defined('ABSPATH')) exit;

/** The old composer stores classes in Divi's editable HTML attributes list. */
function bioco_editorial_preview_classes(array $block): array {
    $classes = [];
    foreach ($block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [] as $attribute) {
        if (($attribute['name'] ?? '') === 'class' && is_string($attribute['value'] ?? null)) {
            $classes = array_merge($classes, preg_split('/\s+/', trim($attribute['value'])) ?: []);
        }
    }
    $advanced = $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] ?? '';
    if (is_string($advanced)) $classes = array_merge($classes, preg_split('/\s+/', trim($advanced)) ?: []);
    return array_values(array_unique(array_filter($classes)));
}

function bioco_editorial_preview_without_class(array &$block, string $class): void {
    foreach (['tablet', 'phone'] as $device) {
        foreach ($block['attrs']['module']['decoration']['attributes'][$device]['value']['attributes'] ?? [] as $attribute) {
            if (($attribute['name'] ?? '') === 'class' && is_string($attribute['value'] ?? null) &&
                in_array($class, preg_split('/\s+/', trim($attribute['value'])) ?: [], true)) {
                throw new RuntimeException('Responsive legacy class requires separate review.');
            }
        }
        $responsive = $block['attrs']['module']['advanced']['htmlAttributes'][$device]['value']['class'] ?? '';
        if (is_string($responsive) && in_array($class, preg_split('/\s+/', trim($responsive)) ?: [], true)) {
            throw new RuntimeException('Responsive legacy class requires separate review.');
        }
    }
    if (isset($block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'])) {
        $attributes =& $block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'];
        foreach ($attributes as &$attribute) {
            if (($attribute['name'] ?? '') !== 'class' || !is_string($attribute['value'] ?? null)) continue;
            $parts = preg_split('/\s+/', trim($attribute['value'])) ?: [];
            $attribute['value'] = implode(' ', array_values(array_filter($parts, static fn($part) => $part !== $class)));
        }
        unset($attribute);
    }
    if (isset($block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'])) {
        $advanced =& $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'];
    } else {
        return;
    }
    if (is_string($advanced)) {
        $parts = preg_split('/\s+/', trim($advanced)) ?: [];
        $advanced = implode(' ', array_values(array_filter($parts, static fn($part) => $part !== $class)));
    }
}

/** Presets can supply styles absent from instance attrs; their effective values are not available here. */
function bioco_editorial_preview_has_preset(array $value): bool {
    foreach ($value as $key => $child) {
        if (in_array($key, ['modulePreset', 'groupPreset', 'presetId'], true) && $child !== null && $child !== '' && $child !== []) return true;
        if (is_array($child) && bioco_editorial_preview_has_preset($child)) return true;
    }
    return false;
}

/** Fill only missing native controls; authored desktop, tablet and phone values stay intact. */
function bioco_editorial_preview_fill(array &$block, array $path, array $defaults): void {
    $node =& $block['attrs'];
    foreach ($path as $key) {
        if ($node !== null && !is_array($node)) throw new RuntimeException('Unsupported native attribute shape.');
        if (is_array($node) && array_key_exists($key, $node) && !is_array($node[$key])) throw new RuntimeException('Unsupported native attribute shape.');
        $node =& $node[$key];
    }
    if ($node !== null && !is_array($node)) throw new RuntimeException('Unsupported native attribute shape.');
    if (!is_array($node)) $node = [];
    if (array_key_exists('desktop', $node) && !is_array($node['desktop'])) throw new RuntimeException('Unsupported desktop attribute shape.');
    if (array_key_exists('value', $node['desktop'] ?? []) && !is_array($node['desktop']['value'])) throw new RuntimeException('Unsupported desktop value.');
    if (!isset($node['desktop']['value'])) $node['desktop']['value'] = [];
    $node['desktop']['value'] = array_replace_recursive($defaults, $node['desktop']['value']);
}

/**
 * Return a changed block tree and a report. Never reads or writes WordPress data.
 * Geometry and images remain on their legacy compatibility classes pending GUI proof.
 */
function bioco_editorial_media_rich_visit(array &$blocks, array &$report): void {
    foreach ($blocks as &$section) {
        if (($section['blockName'] ?? '') !== 'divi/section') {
            if (!empty($section['innerBlocks'])) bioco_editorial_media_rich_visit($section['innerBlocks'], $report);
            continue;
        }
        $classes = bioco_editorial_preview_classes($section);
        $family = in_array('bioco-divi-media-text', $classes, true) ? 'media' :
            (in_array('bioco-divi-rich-text', $classes, true) ? 'rich' : null);
        if ($family === null) {
            if (!empty($section['innerBlocks'])) bioco_editorial_media_rich_visit($section['innerBlocks'], $report);
            continue;
        }
        if (bioco_editorial_preview_has_preset($section)) throw new RuntimeException('Preset-backed media/rich section needs effective style capture.');
        $rows = $section['innerBlocks'] ?? [];
        if (count($rows) !== 1 || ($rows[0]['blockName'] ?? '') !== 'divi/row') {
            $report['skipped'][] = "$family: unexpected row structure";
            continue;
        }
        $columns = $rows[0]['innerBlocks'] ?? [];
        $contentColumns = [];
        foreach ($columns as $index => $column) {
            if (($column['blockName'] ?? '') === 'divi/column' &&
                in_array('bioco-divi-content', bioco_editorial_preview_classes($column), true)) $contentColumns[] = $index;
        }
        if (count($contentColumns) !== 1) {
            $report['skipped'][] = "$family: expected one content column";
            continue;
        }
        $index = $contentColumns[0];
        $leaves =& $section['innerBlocks'][0]['innerBlocks'][$index]['innerBlocks'];
        foreach ($leaves as &$leaf) {
            $leafClasses = bioco_editorial_preview_classes($leaf);
            if (($leaf['blockName'] ?? '') === 'divi/heading' && in_array('bioco-divi-title', $leafClasses, true)) {
                bioco_editorial_preview_fill($leaf, ['title', 'decoration', 'font', 'font'], [
                    'size' => 'clamp(1.5rem, 3vw, 2rem)', 'weight' => '700',
                    'color' => '#1A1A1A', 'lineHeight' => '1.7em',
                ]);
                bioco_editorial_preview_fill($leaf, ['module', 'decoration', 'spacing'], [
                    'margin' => ['bottom' => 'clamp(12px, 1.5vw, 16px)'],
                ]);
                // HeadingModule passes top-level attrs.css to CssStyle; module.decoration.css is inert.
                $savedCss = $leaf['attrs']['css']['desktop']['value']['mainElement'] ?? null;
                if ($savedCss !== null && $savedCss !== 'padding-bottom:0px;') {
                    throw new RuntimeException('Authored heading CSS needs review.');
                }
                bioco_editorial_preview_fill($leaf, ['css'], [
                    'mainElement' => 'padding-bottom:0px;',
                ]);
                bioco_editorial_preview_without_class($leaf, 'bioco-divi-title');
                $report['headings']++;
            } elseif (($leaf['blockName'] ?? '') === 'divi/text' && in_array('bioco-divi-text', $leafClasses, true)) {
                bioco_editorial_preview_fill($leaf, ['content', 'decoration', 'bodyFont', 'body', 'font'], [
                    'color' => '#4A4A4A',
                ]);
                bioco_editorial_preview_without_class($leaf, 'bioco-divi-text');
                $report['texts']++;
            }
        }
        unset($leaf);
        $report['sections']++;
    }
    unset($section);
}

function bioco_editorial_media_rich_preview(array $blocks): array {
    $report = ['sections' => 0, 'headings' => 0, 'texts' => 0, 'skipped' => []];
    bioco_editorial_media_rich_visit($blocks, $report);
    return [$blocks, $report];
}

/** Candidate geometry for an owned draft. It must pass Divi GUI/render proof before adoption. */
function bioco_editorial_media_rich_geometry_visit(array &$blocks, array &$report): void {
    foreach ($blocks as &$section) {
        $classes = bioco_editorial_preview_classes($section);
        $media = ($section['blockName'] ?? '') === 'divi/section' && in_array('bioco-divi-media-text', $classes, true);
        $rich = ($section['blockName'] ?? '') === 'divi/section' && in_array('bioco-divi-rich-text', $classes, true);
        if (!$media && !$rich) {
            if (!empty($section['innerBlocks'])) bioco_editorial_media_rich_geometry_visit($section['innerBlocks'], $report);
            continue;
        }
        if (bioco_editorial_preview_has_preset($section)) throw new RuntimeException('Preset-backed media/rich section needs effective style capture.');
        $rows = $section['innerBlocks'] ?? [];
        if (count($rows) !== 1 || ($rows[0]['blockName'] ?? '') !== 'divi/row') throw new RuntimeException('Unexpected media/rich row structure.');
        $columns = $rows[0]['innerBlocks'] ?? [];
        if (count($columns) < 1 || count($columns) > ($media ? 2 : 1)) throw new RuntimeException('Unexpected media/rich columns.');
        $contentIndex = null;
        $mediaIndex = null;
        foreach ($columns as $index => $column) {
            if (($column['blockName'] ?? '') !== 'divi/column') throw new RuntimeException('Unexpected media/rich child.');
            $columnClasses = bioco_editorial_preview_classes($column);
            if (in_array('bioco-divi-content', $columnClasses, true)) $contentIndex = $index;
            elseif ($media && in_array('bioco-divi-media', $columnClasses, true)) $mediaIndex = $index;
            else throw new RuntimeException('Unknown media/rich column.');
        }
        if ($contentIndex === null || ($mediaIndex === null && count($columns) !== 1) ||
            ($rich && $mediaIndex !== null)) throw new RuntimeException('Ambiguous media/rich columns.');
        if ($mediaIndex !== null) {
            $images = $columns[$mediaIndex]['innerBlocks'] ?? [];
            if (count($images) !== 1 || ($images[0]['blockName'] ?? '') !== 'divi/image' ||
                !in_array('bioco-divi-media-image', bioco_editorial_preview_classes($images[0]), true)) {
                throw new RuntimeException('Unexpected media image structure.');
            }
        }

        bioco_editorial_preview_fill($section, ['module', 'decoration', 'background'], ['color' => '#F5F1E8']);
        bioco_editorial_preview_fill_device($section, ['module', 'decoration', 'spacing'], 'phone', [
            'padding' => ['top' => '32px', 'bottom' => '32px', 'left' => '6vw', 'right' => '6vw'],
        ]);
        bioco_editorial_preview_fill($section, ['module', 'decoration', 'spacing'], [
            'padding' => ['top' => '54px', 'bottom' => '54px', 'left' => '0px', 'right' => '0px'],
        ]);
        foreach (['bioco-divi-section', 'bioco-divi-rich-text', 'bioco-divi-media-text', 'bioco-divi-media-left', 'bioco-divi-media-right'] as $class) {
            bioco_editorial_preview_without_class($section, $class);
        }

        $row =& $section['innerBlocks'][0];
        bioco_editorial_preview_fill($row, ['module', 'decoration', 'sizing'], [
            'width' => '80%', 'maxWidth' => $media && $mediaIndex === null ? '1160px' : '1080px', 'alignment' => 'center',
        ]);
        bioco_editorial_preview_fill($row, ['module', 'decoration', 'spacing'], [
            'padding' => ['top' => '0px', 'bottom' => '0px', 'left' => '0px', 'right' => '0px'],
        ]);
        if ($media) {
            // The legacy composer saved display:block on every row while its
            // CSS class supplied the actual grid. Replace only that known default.
            $savedDisplay = $row['attrs']['module']['decoration']['layout']['desktop']['value']['display'] ?? null;
            if ($savedDisplay !== null && !in_array($savedDisplay, ['block', 'grid'], true)) {
                throw new RuntimeException('Unknown authored media row display.');
            }
            foreach (['tablet', 'phone'] as $device) {
                $responsiveDisplay = $row['attrs']['module']['decoration']['layout'][$device]['value']['display'] ?? null;
                if ($responsiveDisplay !== null && $responsiveDisplay !== 'grid') {
                    throw new RuntimeException('Unknown responsive media row display.');
                }
            }
            if ($savedDisplay === 'block') $row['attrs']['module']['decoration']['layout']['desktop']['value']['display'] = 'grid';
            bioco_editorial_preview_fill_device($row, ['module', 'decoration', 'layout'], 'phone', [
                'gridTemplateColumns' => 'minmax(0, 1fr)', 'columnGap' => '24px', 'rowGap' => '24px',
            ]);
            bioco_editorial_preview_fill($row, ['module', 'decoration', 'layout'], [
                'display' => 'grid', 'gridColumnWidths' => 'manual',
                'gridTemplateColumns' => $mediaIndex === null ? 'minmax(0, 1fr)' : 'repeat(2, minmax(0, 1fr))',
                'columnGap' => 'clamp(32px, 5vw, 64px)', 'rowGap' => 'clamp(32px, 5vw, 64px)',
                'alignItems' => 'center',
            ]);
        }
        bioco_editorial_preview_without_class($row, 'bioco-divi-row');

        $content =& $row['innerBlocks'][$contentIndex];
        bioco_editorial_preview_fill($content, ['module', 'decoration', 'sizing'], [
            'width' => '100%', 'maxWidth' => '1160px', 'alignment' => $rich ? 'center' : 'left',
        ]);
        if ($mediaIndex !== null) {
            $right = in_array('bioco-divi-media-right', $classes, true);
            bioco_editorial_preview_fill_device($content, ['module', 'decoration', 'order'], 'phone', ['order' => '0']);
            bioco_editorial_preview_fill($content, ['module', 'decoration', 'order'], ['order' => $right ? '1' : '2']);
        } elseif ($media) {
            bioco_editorial_preview_fill($content, ['module', 'decoration', 'order'], ['order' => '1']);
        }
        bioco_editorial_preview_without_class($content, 'bioco-divi-content');
        $contentLeaves =& $content['innerBlocks'];
        foreach ($contentLeaves as &$leaf) {
            if (($leaf['blockName'] ?? '') === 'divi/text') {
                bioco_editorial_preview_fill($leaf, ['content', 'decoration', 'bodyFont', 'body', 'font'], ['lineHeight' => '1.7em']);
            }
        }
        unset($leaf);

        if ($mediaIndex !== null) {
            $mediaColumn =& $row['innerBlocks'][$mediaIndex];
            bioco_editorial_preview_fill_device($mediaColumn, ['module', 'decoration', 'order'], 'phone', ['order' => '0']);
            bioco_editorial_preview_fill($mediaColumn, ['module', 'decoration', 'order'], ['order' => $right ? '2' : '1']);
            bioco_editorial_preview_fill_device($mediaColumn, ['module', 'decoration', 'sizing'], 'phone', ['width' => '100%']);
            bioco_editorial_preview_fill($mediaColumn, ['module', 'decoration', 'sizing'], ['width' => '94.5%']);
            bioco_editorial_preview_fill_device($mediaColumn, ['module', 'decoration', 'spacing'], 'phone', [
                'margin' => ['bottom' => '30px'],
            ]);
            bioco_editorial_preview_without_class($mediaColumn, 'bioco-divi-media');
            $image =& $mediaColumn['innerBlocks'][0];
            bioco_editorial_preview_fill($image, ['module', 'advanced', 'sizing'], [
                'width' => '100%', 'maxWidth' => '100%', 'aspectRatio' => ['width' => '4', 'height' => '3'],
            ]);
            bioco_editorial_preview_fill($image, ['image', 'decoration', 'fit'], ['objectFit' => 'cover']);
            bioco_editorial_preview_fill($image, ['image', 'decoration', 'border'], [
                'radius' => ['topLeft' => '24px', 'topRight' => '24px', 'bottomLeft' => '24px', 'bottomRight' => '24px'],
            ]);
            bioco_editorial_preview_without_class($image, 'bioco-divi-media-image');
            $report['images']++;
        }
        $report['sections']++;
    }
    unset($section);
}

/** Fill a breakpoint only where no instance value exists at it or an inherited breakpoint. */
function bioco_editorial_preview_fill_device(array &$block, array $path, string $device, array $defaults): void {
    $node =& $block['attrs'];
    foreach ($path as $key) {
        if ($node !== null && !is_array($node)) throw new RuntimeException('Unsupported native attribute shape.');
        if (is_array($node) && array_key_exists($key, $node) && !is_array($node[$key])) throw new RuntimeException('Unsupported native attribute shape.');
        $node =& $node[$key];
    }
    if ($node !== null && !is_array($node)) throw new RuntimeException('Unsupported native attribute shape.');
    if (!is_array($node)) $node = [];
    if (array_key_exists($device, $node) && !is_array($node[$device])) throw new RuntimeException('Unsupported responsive attribute shape.');
    if (array_key_exists('value', $node[$device] ?? []) && !is_array($node[$device]['value'])) throw new RuntimeException('Unsupported responsive value.');
    if (!isset($node[$device]['value'])) $node[$device]['value'] = [];
    $inherited = $node['desktop']['value'] ?? [];
    if ($device === 'phone') $inherited = array_replace_recursive($inherited, $node['tablet']['value'] ?? []);
    $fill = static function (array &$target, array $candidates, array $upstream) use (&$fill): void {
        foreach ($candidates as $key => $default) {
            if (array_key_exists($key, $target)) {
                if (is_array($default) && is_array($target[$key])) $fill($target[$key], $default, is_array($upstream[$key] ?? null) ? $upstream[$key] : []);
                continue;
            }
            if (array_key_exists($key, $upstream)) {
                if (is_array($default) && is_array($upstream[$key])) {
                    $target[$key] = [];
                    $fill($target[$key], $default, $upstream[$key]);
                    if ($target[$key] === []) unset($target[$key]);
                }
                continue;
            }
            $target[$key] = $default;
        }
    };
    $fill($node[$device]['value'], $defaults, is_array($inherited) ? $inherited : []);
}

function bioco_editorial_media_rich_full_preview(array $blocks): array {
    [$blocks, $typeReport] = bioco_editorial_media_rich_preview($blocks);
    $geometryReport = ['sections' => 0, 'images' => 0];
    bioco_editorial_media_rich_geometry_visit($blocks, $geometryReport);
    return [$blocks, ['geometry' => $geometryReport, 'typography' => $typeReport]];
}
