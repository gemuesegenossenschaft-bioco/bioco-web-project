<?php
/** Native saved-value preservation shared by presentation previews. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/editorial-layouts.php';

/** Missing means absent: explicit blank, null, zero and false remain authored. */
function bioco_editorial_native_missing(array $saved, array $defaults): array {
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $saved)) $saved[$key] = $value;
        elseif (is_array($saved[$key]) && is_array($value)) $saved[$key] = bioco_editorial_native_missing($saved[$key], $value);
    }
    return $saved;
}

/** Do not create a responsive fallback which masks an authored inherited value. */
function bioco_editorial_native_inherited(array $defaults, array $inherited): array {
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $inherited)) continue;
        if (is_array($value) && is_array($inherited[$key])) {
            $defaults[$key] = bioco_editorial_native_inherited($value, $inherited[$key]);
            if (!$defaults[$key]) unset($defaults[$key]);
        } else unset($defaults[$key]);
    }
    return $defaults;
}

function bioco_editorial_native_fill(array &$block, array $path, array $devices): void {
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
        $defaults = bioco_editorial_native_inherited($defaults, $inherited);
        if (!$defaults) continue;
        if (!array_key_exists($device, $node)) $node[$device] = [];
        if (!is_array($node[$device])) throw new RuntimeException('Unsupported saved native breakpoint.');
        if (!array_key_exists('value', $node[$device])) $node[$device]['value'] = [];
        if (!is_array($node[$device]['value'])) throw new RuntimeException('Unsupported saved native value.');
        $node[$device]['value'] = bioco_editorial_native_missing($node[$device]['value'], $defaults);
    }
}

/** Remove only exact reviewed tokens; retain attribute IDs, unrelated classes and IDs. */
function bioco_editorial_native_remove(array &$block, array $remove): void {
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

function bioco_editorial_native_presets(array $block): void {
    $check = static function (array $values) use (&$check): void {
        foreach ($values as $key => $value) {
            if (in_array($key, ['modulePreset', 'groupPreset', 'presetId'], true) && !empty($value)) {
                throw new RuntimeException('Effective shared preset capture is required before presentation conversion.');
            }
            if (is_array($value)) $check($value);
        }
    };
    $check($block['attrs'] ?? []);
    foreach ($block['innerBlocks'] ?? [] as $child) bioco_editorial_native_presets($child);
}
