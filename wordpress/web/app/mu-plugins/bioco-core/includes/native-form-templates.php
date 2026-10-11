<?php
/** Build a candidate native layout. This file performs no database writes. */
if (!defined('ABSPATH')) exit;

function bioco_form_layout_block(string $name, array $attrs = [], array $children = []): array {
    return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $children, 'innerHTML' => '', 'innerContent' => $children ? array_fill(0, count($children), null) : ["\n"]];
}

function bioco_form_layout_attrs(string $class): array {
    return ['module' => ['advanced' => ['htmlAttributes' => ['desktop' => ['value' => ['class' => $class]]]]]];
}

function bioco_form_layout_part(string $kind, array $values): array {
    $attrs = [];
    foreach ($values as $key => $value) $attrs[$key] = ['innerContent' => ['desktop' => ['value' => $value]]];
    return bioco_form_layout_block('bioco-divi/' . $kind, $attrs);
}

function bioco_form_layout_row(array $children, string $class): array {
    $attrs = bioco_form_layout_attrs($class);
    $attrs['module']['advanced']['columnStructure']['desktop']['value'] = '4_4';
    $column = bioco_form_layout_block('divi/column', ['module' => ['advanced' => ['type' => ['desktop' => ['value' => '4_4']]]]], $children);
    return bioco_form_layout_block('divi/row', $attrs, [$column]);
}

/** Values must come from a captured current form; missing required labels refuse composition. */
function bioco_form_compose(string $type, array $values, array $fields): array {
    $contract = bioco_form_contracts()[$type] ?? null;
    if ($contract === null) throw new InvalidArgumentException('Unknown form type.');
    $seen = [];
    $controls = [];
    foreach ($fields as $field) {
        $identity = $field['field'] ?? '';
        $name = substr($identity, strlen($type) + 1);
        if (!str_starts_with($identity, $type . '.') || !isset($contract[$name]) || isset($seen[$name]) || ($field['label'] ?? '') === '') throw new InvalidArgumentException('Invalid or duplicate form field.');
        $seen[$name] = true;
        $controls[] = bioco_form_layout_part('form-field', $field);
    }
    foreach ($contract as $name => $spec) {
        if ($spec['required'] && !isset($seen[$name])) throw new InvalidArgumentException('Missing required form field: ' . $name);
    }
    if (($values['submit_label'] ?? '') === '') throw new InvalidArgumentException('Missing submission action.');
    $rows = [];
    $intro = [];
    if (($values['title'] ?? '') !== '') $intro[] = bioco_form_layout_block('divi/heading', ['title' => ['innerContent' => ['desktop' => ['value' => $values['title']]]]]);
    if (($values['text'] ?? '') !== '') $intro[] = bioco_form_layout_block('divi/text', ['content' => ['innerContent' => ['desktop' => ['value' => $values['text']]]]]);
    if ($intro) $rows[] = bioco_form_layout_row($intro, 'bioco-form-intro');
    $controller = ['form_type' => $type];
    foreach (['success_message', 'fallback_error', 'captcha_error', 'transport_error'] as $key) $controller[$key] = $values[$key] ?? '';
    $controls[] = bioco_form_layout_part('form-controller', $controller);
    $controls[] = bioco_form_layout_part('form-action', ['label' => $values['submit_label'], 'pending_label' => $values['submitting_label'] ?? '']);
    $rows[] = bioco_form_layout_row($controls, 'bioco-form-fields');
    if (($values['success_message'] ?? '') !== '') {
        $text = '<p>' . esc_html($values['success_message']) . '</p>';
        $rows[] = bioco_form_layout_row([bioco_form_layout_block('divi/text', ['content' => ['innerContent' => ['desktop' => ['value' => $text]]]])], 'bioco-form-state-success');
    }
    return bioco_form_layout_block('divi/section', bioco_form_layout_attrs('bioco-form-scope'), $rows);
}
