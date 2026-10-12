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

/** Validate captured identities without imposing a presentation order or group count. */
function bioco_form_validate_fields(string $type, array $fields): void {
    $contract = bioco_form_contracts()[$type] ?? null;
    if ($contract === null) throw new InvalidArgumentException('Unknown form type.');
    $seen = [];
    foreach ($fields as $field) {
        $identity = $field['field'] ?? '';
        $name = substr($identity, strlen($type) + 1);
        if (!str_starts_with($identity, $type . '.') || !isset($contract[$name]) || (isset($seen[$name]) && empty($contract[$name]['array'])) || bioco_form_render_part('form-field', $field) === '') throw new InvalidArgumentException('Invalid or duplicate form field.');
        $seen[$name] = ($seen[$name] ?? 0) + 1;
    }
    foreach ($contract as $name => $spec) {
        if ($spec['required'] && !isset($seen[$name])) throw new InvalidArgumentException('Missing required form field: ' . $name);
    }
}

function bioco_form_controller_values(string $type, array $values): array {
    $controller = ['form_type' => $type];
    foreach (['success_message', 'fallback_error', 'captcha_error', 'transport_error', 'thank_you_url', 'commitment_count'] as $key) $controller[$key] = $values[$key] ?? '';
    return $controller;
}

/** Read effective legacy copy before retirement; rendering new parts never calls this. */
function bioco_form_capture_presentation(string $group, array $values): array {
    if (!function_exists('bioco_forms_message_values') || in_array($group, bioco_forms_divi_message_groups(), true)) throw new InvalidArgumentException('Presentation group must be captured before adoption.');
    if (!isset(bioco_forms_message_schema()[$group]) || $group === 'shared') throw new InvalidArgumentException('Unknown presentation group.');
    return bioco_forms_message_values($group, $values);
}

function bioco_form_native_copy(array $values): array {
    $children = [];
    if (($values['title'] ?? '') !== '') $children[] = bioco_form_layout_block('divi/heading', ['title' => ['innerContent' => ['desktop' => ['value' => $values['title']]]]]);
    if (($values['text'] ?? '') !== '') $children[] = bioco_form_layout_block('divi/text', ['content' => ['innerContent' => ['desktop' => ['value' => $values['text']]]]]);
    return $children;
}

function bioco_form_outcome_rows(array $values): array {
    $rows = [];
    foreach (['success' => 'success_message', 'error' => 'fallback_error'] as $state => $key) {
        if (($values[$key] ?? '') === '') continue;
        $text = '<p>' . esc_html($values[$key]) . '</p>';
        $rows[] = bioco_form_layout_row([bioco_form_layout_block('divi/text', ['content' => ['innerContent' => ['desktop' => ['value' => $text]]]])], 'bioco-form-state-' . $state);
    }
    return $rows;
}

/** Values must come from a captured current form; missing required labels refuse composition. */
function bioco_form_compose(string $type, array $values, array $fields): array {
    if ($type === 'membership') throw new InvalidArgumentException('Membership requires captured step groups.');
    bioco_form_validate_fields($type, $fields);
    if (($values['submit_label'] ?? '') === '') throw new InvalidArgumentException('Missing submission action.');
    $rows = [];
    $intro = bioco_form_native_copy($values);
    if ($intro) $rows[] = bioco_form_layout_row($intro, 'bioco-form-intro');
    $controls = array_map(static fn($field) => bioco_form_layout_part('form-field', $field), $fields);
    $controls[] = bioco_form_layout_part('form-controller', bioco_form_controller_values($type, $values));
    $controls[] = bioco_form_layout_part('form-action', ['label' => $values['submit_label'], 'pending_label' => $values['submitting_label'] ?? '']);
    $rows[] = bioco_form_layout_row($controls, 'bioco-form-fields');
    $rows = array_merge($rows, bioco_form_outcome_rows($values));
    return bioco_form_layout_block('divi/section', bioco_form_layout_attrs('bioco-form-scope'), $rows);
}

/** Arbitrary ordinary native rows become steps; an information-only step is valid. */
function bioco_membership_compose(array $values, array $steps): array {
    if (!$steps) throw new InvalidArgumentException('Missing membership steps.');
    $fields = [];
    foreach ($steps as $step) $fields = array_merge($fields, $step['fields'] ?? []);
    bioco_form_validate_fields('membership', $fields);
    $count = count(array_filter($fields, static fn($field) => $field['field'] === 'membership.commitmentAccepted'));
    if ($count !== (int) ($values['commitment_count'] ?? 0) || $count < 1 || $count > 100) throw new InvalidArgumentException('Commitment count does not match captured fields.');
    foreach (['submit_label', 'next_label', 'back_label', 'progress_label'] as $key) {
        if (($values[$key] ?? '') === '') throw new InvalidArgumentException('Missing membership action: ' . $key);
    }
    $rows = [bioco_form_layout_row([bioco_form_layout_part('form-controller', bioco_form_controller_values('membership', $values))], 'bioco-form-controller')];
    foreach ($steps as $step) {
        $children = bioco_form_native_copy($step);
        foreach ($step['fields'] ?? [] as $field) $children[] = bioco_form_layout_part('form-field', $field);
        foreach ($step['review_fields'] ?? [] as $field) {
            if (bioco_form_render_part('form-value', $field) === '') throw new InvalidArgumentException('Unknown review field.');
            $children[] = bioco_form_layout_part('form-value', $field);
        }
        $rows[] = bioco_form_layout_row($children, 'bioco-form-step bioco-form-fields');
    }
    $actions = [];
    foreach (['progress' => 'progress_label', 'back' => 'back_label', 'next' => 'next_label', 'submit' => 'submit_label'] as $action => $label) {
        $actions[] = bioco_form_layout_part('form-action', ['action' => $action, 'label' => $values[$label], 'pending_label' => $values['submitting_label'] ?? '']);
    }
    $rows[] = bioco_form_layout_row($actions, 'bioco-form-fields');
    return bioco_form_layout_block('divi/section', bioco_form_layout_attrs('bioco-form-scope'), array_merge($rows, bioco_form_outcome_rows($values)));
}

/** Captured outcome children stay ordinary native content with explicit state classes. */
function bioco_doi_compose(array $layouts, string $button_label): array {
    if ($button_label === '') throw new InvalidArgumentException('Missing confirmation action.');
    $rows = [bioco_form_layout_row([bioco_form_layout_part('doi-controller', [])], 'bioco-doi-controller')];
    foreach (['missing', 'prompt', 'success', 'invalid', 'failure'] as $state) {
        if (empty($layouts[$state])) throw new InvalidArgumentException('Missing confirmation layout: ' . $state);
        $children = $layouts[$state];
        if ($state === 'prompt') $children[] = bioco_form_layout_part('form-action', ['action' => 'confirm', 'label' => $button_label]);
        $rows[] = bioco_form_layout_row($children, 'bioco-doi-state-' . $state);
    }
    return bioco_form_layout_block('divi/section', bioco_form_layout_attrs('bioco-doi-scope'), $rows);
}
