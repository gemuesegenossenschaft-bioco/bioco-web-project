<?php
/** Convert known imported editorial structures, using their saved content. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/timeline-migration.php';

function bioco_import_editorial_topics(string $html): array {
    if (!preg_match_all('~<h3>([^<]*)</h3>~', $html, $matches, PREG_OFFSET_CAPTURE)) return [];
    if (trim(substr($html, 0, $matches[0][0][1])) !== '') return [];
    $items = [];
    foreach ($matches[0] as $i => [$heading, $offset]) {
        $end = $offset + strlen($heading);
        $next = $matches[0][$i + 1][1] ?? strlen($html);
        $body = substr($html, $end, $next - $end);
        // A nested/styled heading is not a supported topic boundary.
        if (preg_match('~<h[1-6]\b~i', $body)) return [];
        $items[] = ['title' => html_entity_decode($matches[1][$i][0], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'text' => $body];
    }
    return $items;
}

function bioco_import_editorial_tiles(string $html): array {
    if (!preg_match('~^\s*<div class="portal-gateway">(.*)</div>\s*$~sD', $html, $gateway)) {
        throw new RuntimeException('Portal module contains unrecognized editorial markup.');
    }
    $remaining = trim($gateway[1]);
    $tiles = [];
    while ($remaining !== '') {
        $pattern = '~^<(div|a) class="portal-tile"((?:\s+(?:href|target|rel)="[^"]*")*)>\s*(?:<div class="portal-icon">([^<]*)</div>\s*)?<h3>([^<]*)</h3>\s*(?:<p>([^<]*)</p>\s*)?</\1>~s';
        if (!preg_match($pattern, $remaining, $tile)) throw new RuntimeException('Portal tile contains unrecognized editorial markup.');
        preg_match_all('~\s+(href|target|rel)="([^"]*)"~', $tile[2], $attrs, PREG_SET_ORDER);
        $attributes = [];
        foreach ($attrs as $attr) {
            if (isset($attributes[$attr[1]])) throw new RuntimeException('Duplicate portal tile attribute.');
            $attributes[$attr[1]] = html_entity_decode($attr[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $decode = static fn(string $value): string => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = $decode($tile[4]);
        $href = $attributes['href'] ?? '';
        // These two previously blank destinations were confirmed by the owner.
        if ($href === '' && in_array($title, ['Mitglieder-Portal', 'Einsatzplanung'], true)) $href = 'https://intranet.bioco.ch';
        if ($href !== '' && !preg_match('~^(?:https?://[^\s<>]+|/(?!/)[^\s<>]*)$~iD', $href)) throw new RuntimeException('Unsafe saved portal destination.');
        if (isset($attributes['target']) && !in_array($attributes['target'], ['', '_blank'], true)) throw new RuntimeException('Unsupported portal target.');
        if (isset($attributes['rel']) && $attributes['rel'] !== 'noopener noreferrer') throw new RuntimeException('Unsupported portal relation.');
        $tiles[] = ['title' => $title, 'text' => $decode($tile[5] ?? ''), 'icon' => $decode($tile[3] ?? ''), 'href' => $href];
        $remaining = ltrim(substr($remaining, strlen($tile[0])));
    }
    if (!$tiles) throw new RuntimeException('Portal gateway has no tiles.');
    return $tiles;
}

function bioco_import_editorial_attribute(array $block, string $name): string {
    $value = $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value'][$name] ?? '';
    foreach ($block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [] as $attribute) {
        if (($attribute['name'] ?? '') !== $name || ($attribute['value'] ?? '') === '') continue;
        if ($value !== '' && $value !== $attribute['value']) throw new RuntimeException('Conflicting saved editorial HTML attributes.');
        $value = $attribute['value'];
    }
    return $value;
}

function bioco_import_editorial_class(array $block): string {
    return bioco_import_editorial_attribute($block, 'class');
}

function bioco_import_editorial_set_class(array &$block, string $class): void {
    $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] = $class;
    if (isset($block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'])) {
        foreach ($block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] as &$attribute) {
            if (($attribute['name'] ?? '') === 'class') $attribute['value'] = $class;
        }
        unset($attribute);
    }
}

function bioco_import_editorial_clear_id(array &$block): void {
    unset($block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id']);
    if (isset($block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'])) {
        $attributes = $block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'];
        $block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] = array_values(array_filter($attributes, static fn(array $attr): bool => ($attr['name'] ?? '') !== 'id'));
    }
}

/** Only normalized import defaults can be safely split into several containers. */
function bioco_import_editorial_split_safe(array $block): void {
    $attrs = $block['attrs'];
    if (array_diff(array_keys($attrs), ['module', 'content', 'builderVersion'])) throw new RuntimeException('Editorial container has unsupported saved attributes.');
    $module = $attrs['module'] ?? [];
    if (array_diff(array_keys($module), ['advanced', 'decoration'])) throw new RuntimeException('Editorial container has custom module settings.');
    if (array_diff(array_keys($module['advanced'] ?? []), ['htmlAttributes', 'type', 'columnStructure'])) throw new RuntimeException('Editorial container has custom advanced settings.');
    $decoration = $module['decoration'] ?? [];
    $layout = $decoration['layout'] ?? [];
    if ($layout && $layout !== ['desktop' => ['value' => ['display' => 'block']]]) throw new RuntimeException('Editorial container has custom saved layout.');
    if (array_diff(array_keys($decoration), ['layout', 'attributes'])) throw new RuntimeException('Editorial container has custom saved design; convert it in Divi.');
    foreach ($decoration['attributes']['desktop']['value']['attributes'] ?? [] as $attribute) {
        if (!in_array($attribute['name'] ?? '', ['class', 'id'], true)) throw new RuntimeException('Editorial container has custom HTML attributes.');
    }
}

function bioco_import_editorial_has_class(array $block, string $class): bool {
    return in_array($class, preg_split('/\s+/', bioco_import_editorial_class($block), -1, PREG_SPLIT_NO_EMPTY), true);
}

function bioco_import_editorial_html(array $block): string {
    $values = $block['attrs']['content']['innerContent'] ?? [];
    $html = $values['desktop']['value'] ?? '';
    if (!is_string($html)) throw new RuntimeException('Editorial content is not a string.');
    foreach ($values as $viewport => $value) {
        if ($viewport !== 'desktop' && ($value['value'] ?? $html) !== $html) throw new RuntimeException('Editorial module contains distinct responsive content.');
    }
    if (!empty($block['innerBlocks'])) throw new RuntimeException('Editorial leaf contains nested blocks.');
    return $html;
}

/** Retain every sibling and wrapper; place the native cards at the original position. */
function bioco_import_editorial_section(array $section, int &$count): array {
    $targets = [];
    foreach ($section['innerBlocks'] ?? [] as $ri => $row) {
        foreach ($row['innerBlocks'] ?? [] as $ci => $column) {
            foreach ($column['innerBlocks'] ?? [] as $mi => $module) {
                if (($module['blockName'] ?? '') !== 'divi/text') continue;
                $kind = bioco_import_editorial_has_class($module, 'bioco-divi-link-tiles-body') ? 'link-tiles'
                    : (bioco_import_editorial_has_class($module, 'bioco-divi-text-columns-body') ? 'text-columns' : '');
                if ($kind !== '') $targets[] = [$ri, $ci, $mi, $kind, $module];
            }
        }
    }
    if (!$targets) return $section;
    if (count($targets) !== 1) throw new RuntimeException('Editorial section contains multiple legacy structures.');
    [$ri, $ci, $mi, $kind, $module] = $targets[0];
    $row = $section['innerBlocks'][$ri];
    if (($row['blockName'] ?? '') !== 'divi/row' || count($row['innerBlocks']) !== 1 || ($row['innerBlocks'][$ci]['blockName'] ?? '') !== 'divi/column') {
        throw new RuntimeException('Editorial structure shares a row with other columns.');
    }
    $html = bioco_import_editorial_html($module);
    if ($kind === 'link-tiles') {
        $values = ['tiles' => bioco_import_editorial_tiles($html)];
    } else {
        if (!bioco_import_editorial_topics($html)) throw new RuntimeException('Text columns contain unrecognized editorial topic markup.');
        $class = bioco_import_editorial_class($module);
        preg_match('/\bbioco-divi-columns-([1-4])\b/', $class, $columns);
        preg_match('/\bbioco-divi-gap-(sm|md|lg|xl)\b/', $class, $gap);
        $values = ['text' => $html, 'columns' => $columns[1] ?? '2', 'gap' => $gap[1] ?? 'lg'];
    }
    $replacement = Bioco_Import_Divi_Composer::section(['block' => $kind, 'values' => $values]);
    bioco_import_editorial_split_safe($module);
    bioco_import_editorial_split_safe($row);
    bioco_import_editorial_split_safe($row['innerBlocks'][$ci]);
    foreach ([[$row, ['bioco-divi-row']], [$row['innerBlocks'][$ci], ['bioco-divi-content']]] as [$container, $allowedClasses]) {
        if (array_diff(preg_split('/\s+/', bioco_import_editorial_class($container), -1, PREG_SPLIT_NO_EMPTY), $allowedClasses)) throw new RuntimeException('Editorial container has custom CSS classes.');
    }
    $allowedClasses = $kind === 'link-tiles' ? ['bioco-divi-link-tiles-body'] : ['bioco-divi-text', 'bioco-divi-text-columns-body', 'bioco-divi-columns-1', 'bioco-divi-columns-2', 'bioco-divi-columns-3', 'bioco-divi-columns-4', 'bioco-divi-gap-sm', 'bioco-divi-gap-md', 'bioco-divi-gap-lg', 'bioco-divi-gap-xl'];
    if (array_diff(preg_split('/\s+/', bioco_import_editorial_class($module), -1, PREG_SPLIT_NO_EMPTY), $allowedClasses)) throw new RuntimeException('Editorial module has custom CSS classes.');
    if (array_diff(array_keys($module['attrs']['content']['decoration'] ?? []), ['bodyFont'])) throw new RuntimeException('Editorial content has custom saved design.');
    $newRows = $replacement['innerBlocks'];
    $siblings = $row['innerBlocks'][$ci]['innerBlocks'];
    $wrap = static function(array $children, bool $keepIds) use ($row, $ci): array {
        $copy = $row;
        if (!$keepIds) {
            bioco_import_editorial_clear_id($copy);
            bioco_import_editorial_clear_id($copy['innerBlocks'][$ci]);
        }
        $copy['innerBlocks'][$ci]['innerBlocks'] = $children;
        $copy['innerBlocks'][$ci]['innerContent'] = array_fill(0, count($children), null);
        return $copy;
    };
    $prefix = array_slice($siblings, 0, $mi);
    $suffix = array_slice($siblings, $mi + 1);
    if ($prefix) array_unshift($newRows, $wrap($prefix, true));
    if ($suffix) $newRows[] = $wrap($suffix, false);
    if (!$prefix) {
        $rowId = bioco_import_editorial_attribute($row, 'id');
        if ($rowId !== '') $newRows[0]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = $rowId;
        $columnId = bioco_import_editorial_attribute($row['innerBlocks'][$ci], 'id');
        if ($columnId !== '') $newRows[0]['innerBlocks'][0]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = $columnId;
    }
    // Preserve the former module anchor and body typography on the new structure.
    $anchor = bioco_import_editorial_attribute($module, 'id');
    if ($anchor !== '') {
        $newRows[$prefix ? 1 : 0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = $anchor;
    }
    $copyBodyFont = static function(array &$blocks) use (&$copyBodyFont, $module): void {
        foreach ($blocks as &$child) {
            if (($child['blockName'] ?? '') === 'divi/blurb' && isset($module['attrs']['content']['decoration']['bodyFont'])) {
                $child['attrs']['content']['decoration']['bodyFont'] = $module['attrs']['content']['decoration']['bodyFont'];
            }
            if (!empty($child['innerBlocks'])) $copyBodyFont($child['innerBlocks']);
        }
    };
    $copyBodyFont($newRows);
    array_splice($section['innerBlocks'], $ri, 1, $newRows);
    $section['innerContent'] = array_fill(0, count($section['innerBlocks']), null);
    $count++;
    return $section;
}

function bioco_import_editorial_tree(array $blocks, int &$count): array {
    foreach ($blocks as &$block) {
        if (($block['blockName'] ?? '') === 'divi/section') $block = bioco_import_timeline_section($block, $count);
        if (($block['blockName'] ?? '') === 'divi/section') $block = bioco_import_editorial_section($block, $count);
        if (($block['blockName'] ?? '') === 'divi/text' && bioco_import_editorial_has_class($block, 'bioco-divi-accordion-item')) {
            $html = bioco_import_editorial_html($block);
            if (!preg_match('~^\s*<details><summary>([^<]*)</summary>(?:<div>(.*)</div>)?</details>\s*$~sD', $html, $match)) {
                throw new RuntimeException('Accordion item contains unrecognized editorial markup.');
            }
            if (trim($match[1]) === '' && trim($match[2] ?? '') === '') throw new RuntimeException('Empty saved accordion item.');
            $section = Bioco_Import_Divi_Composer::section(['block' => 'accordion', 'values' => ['items' => [[
                'title' => html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'body' => $match[2] ?? '',
            ]]]]);
            $replacement = $section['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0];
            $attrs = $block['attrs'];
            unset($attrs['content'], $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['class']);
            $replacement['attrs'] = array_replace_recursive($replacement['attrs'], $attrs);
            $classes = preg_split('/\s+/', bioco_import_editorial_class($block), -1, PREG_SPLIT_NO_EMPTY);
            $classes = array_diff($classes, ['bioco-divi-accordion-item']);
            bioco_import_editorial_set_class($replacement, trim('bioco-editorial-toggle ' . implode(' ', $classes)));
            if (isset($block['attrs']['content']['decoration'])) $replacement['attrs']['content']['decoration'] = $block['attrs']['content']['decoration'];
            $block = $replacement;
            $count++;
        }
        if (!empty($block['innerBlocks'])) $block['innerBlocks'] = bioco_import_editorial_tree($block['innerBlocks'], $count);
    }
    return $blocks;
}

function bioco_import_editorial_content(string $content): array {
    $count = 0;
    $blocks = bioco_import_editorial_tree(parse_blocks($content), $count);
    return [$count ? serialize_blocks($blocks) : $content, $count];
}
