<?php
/** Explicit saved-instance previews and atomic apply/restore guards. */
if (!defined('ABSPATH')) exit;

function bioco_import_form_groups(): array {
    return ['contact-form' => 'contact', 'subscribe-form' => 'subscribe', 'visit-day-form' => 'visit-day',
        'waiting-list-form' => 'waiting-list', 'event-signup-form' => 'event-signup', 'membership-form' => 'membership', 'doi-confirm' => 'doi'];
}

/** Divi adds these changing parser values; saved attributes and markup stay exact. */
function bioco_import_form_source_hash(array $block): string {
    unset($block['orderIndex'], $block['index'], $block['id'], $block['parentId'], $block['storeInstance']);
    return hash('sha256', wp_json_encode($block));
}

function bioco_import_form_capture(string $group, array $block): array {
    if (!isset(bioco_import_form_groups()[$group])) throw new RuntimeException('Unknown form group.');
    $component = str_replace('-', '_', $group);
    if (!empty($block['innerBlocks']) || trim($block['innerHTML'] ?? '') !== '') throw new RuntimeException('Authored form leaf markup requires manual conversion.');
    $fields = bioco_native_field_map($component);
    $attrs = $block['attrs'] ?? [];
    if (!is_array($attrs) || !is_array($attrs['module'] ?? [])) throw new RuntimeException('Invalid saved form controls.');
    if (array_diff(array_keys($attrs), [...array_keys($fields), 'module', 'builderVersion'])) throw new RuntimeException('Unsupported form attributes.');
    foreach (array_keys($fields) as $field) {
        $variants = $attrs[$field]['innerContent'] ?? [];
        foreach ($variants as $viewport => $variant) {
            if ($viewport !== 'desktop' && ($variant['value'] ?? null) !== ($variants['desktop']['value'] ?? null)) throw new RuntimeException('Distinct responsive form content requires manual conversion.');
        }
    }
    $metadata = json_decode(file_get_contents(dirname(__DIR__, 2) . '/bioco-core/native-modules/' . $group . '/module.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($attrs['module'] ?? [] as $kind => $settings) {
        if (!isset($metadata['attributes']['module']['settings'][$kind]) || !is_array($settings) || array_diff(array_keys($settings), array_keys($metadata['attributes']['module']['settings'][$kind]))) throw new RuntimeException('Unsupported form module controls.');
    }
    $values = bioco_native_extract_values($component, $attrs);
    if (is_wp_error($values)) throw new RuntimeException($values->get_error_message());
    $values = bioco_form_capture_presentation($group, array_merge($values, bioco_native_extract_wrapper($attrs)));
    return ['source' => bioco_import_form_source_hash($block), 'values' => $values,
        'html' => bioco_render_dynamic_component($component, $values, ['mode' => 'inert'])];
}

function bioco_import_form_inner(DOMNode $node): string {
    $html = '';
    foreach ($node->childNodes as $child) $html .= $node->ownerDocument->saveHTML($child);
    return trim($html);
}

function bioco_import_form_text(string $html): array {
    return bioco_form_layout_block('divi/text', ['content' => ['innerContent' => ['desktop' => ['value' => $html]]]]);
}

function bioco_import_form_heading(string $html, string $level): array {
    return bioco_form_layout_block('divi/heading', ['title' => ['innerContent' => ['desktop' => ['value' => $html]],
        'decoration' => ['font' => ['font' => ['desktop' => ['value' => ['headingLevel' => $level]]]]]]]);
}

function bioco_import_form_query(DOMNode $node, string $query): array {
    return iterator_to_array((new DOMXPath($node->ownerDocument))->query($query, $node));
}

/** Capture a control from its rendered label, never synthesize copy from its name. */
function bioco_import_form_control(DOMElement $control, string $type): array {
    $name = preg_replace('/\[\]$/', '', $control->getAttribute('name'));
    $spec = bioco_form_contracts()[$type][$name] ?? null;
    $input_type = $control->tagName === 'input' ? $control->getAttribute('type') : $control->tagName;
    if (!$spec || $input_type !== $spec['type'] || $control->hasAttribute('required') !== $spec['required']
        || $control->getAttribute('name') !== $name . (!empty($spec['array']) ? '[]' : '')) throw new RuntimeException('Unrecognized control or backend constraint.');
    $label = null;
    foreach ($control->ownerDocument->getElementsByTagName('label') as $candidate) {
        if ($control->getAttribute('id') !== '' && $candidate->getAttribute('for') === $control->getAttribute('id')) { $label = $candidate; break; }
    }
    for ($parent = $control->parentNode; !$label && $parent instanceof DOMElement; $parent = $parent->parentNode) {
        if ($parent->tagName === 'label') $label = $parent;
    }
    $values = ['field' => $type . '.' . $name, 'label' => ''];
    if ($label) {
        $copy = $label->cloneNode(true);
        foreach (bioco_import_form_query($copy, './/input|.//select|.//textarea') as $input) $input->parentNode->removeChild($input);
        $values['label'] = trim($copy->textContent);
        $html = bioco_import_form_inner($copy);
        if (preg_match('/<\w/', $html)) $values['label_html'] = $html;
    }
    if ($values['label'] === '') $values['allow_blank_label'] = 'on';
    $values['placeholder'] = $control->getAttribute('placeholder');
    if ($input_type === 'select') {
        $values['exact_options'] = 'on'; $values['options'] = []; $values['default_value'] = '';
        foreach ($control->getElementsByTagName('option') as $option) {
            if (!$option->hasAttribute('value')) throw new RuntimeException('Implicit option values require manual conversion.');
            $values['options'][] = ['value' => $option->getAttribute('value'), 'label' => $option->textContent];
            if ($option->hasAttribute('selected')) $values['default_value'] = $option->getAttribute('value');
        }
    } elseif ($input_type === 'checkbox') {
        if ($control->hasAttribute('value')) $values['choice_value'] = $control->getAttribute('value');
        if ($control->hasAttribute('checked')) $values['checked'] = 'on';
    } elseif ($input_type === 'textarea' ? trim($control->textContent) !== '' : ($control->getAttribute('value') !== '' && $control->getAttribute('value') !== ($spec['value'] ?? ''))) {
        throw new RuntimeException('Populated personal controls cannot become public defaults.');
    }
    if (bioco_form_render_part('form-field', $values) === '') throw new RuntimeException('Captured field cannot render.');
    return bioco_form_layout_part('form-field', $values);
}

function bioco_import_form_children(DOMNode $node, string $type): array {
    $blocks = [];
    foreach ($node->childNodes as $child) $blocks = array_merge($blocks, bioco_import_form_node($child, $type));
    return $blocks;
}

/** Supported legacy structure only. Unknown markup refuses the complete instance. */
function bioco_import_form_node(DOMNode $node, string $type): array {
    if ($node instanceof DOMText) {
        if (trim($node->textContent) !== '') return [bioco_import_form_text(esc_html($node->textContent))];
        return [];
    }
    if (!($node instanceof DOMElement)) {
        if ($node instanceof DOMComment) throw new RuntimeException('Authored comments require manual conversion.');
        return [];
    }
    $tag = $node->tagName;
    $classes = preg_split('/\s+/', $node->getAttribute('class'), -1, PREG_SPLIT_NO_EMPTY);
    if (in_array('form-message', $classes, true) || $node->hasAttribute('data-form-captcha')) {
        if (trim($node->textContent) !== '') throw new RuntimeException('Unexpected backend container content.');
        return [];
    }
    if ($tag === 'input' && $node->getAttribute('type') === 'hidden') return [];
    if ($node->hasAttribute('data-wizard-progress')) return [bioco_form_layout_part('form-action', ['action' => 'progress', 'label' => $node->getAttribute('data-label')])];
    if ($node->hasAttribute('data-membership-summary')) return [bioco_form_layout_part('form-action', ['action' => 'summary', 'label' => ''])];
    if ($tag === 'button') {
        $action = $node->hasAttribute('data-wizard-back') ? 'back' : ($node->hasAttribute('data-wizard-next') ? 'next' : 'submit');
        if ($action === 'submit' && !$node->hasAttribute('data-submit-label')) throw new RuntimeException('Unknown form action.');
        return [bioco_form_layout_part('form-action', ['action' => $action, 'label' => trim($node->textContent), 'pending_label' => $node->getAttribute('data-submitting-label')])];
    }
    if ($tag === 'label') {
        $controls = bioco_import_form_query($node, './/input|.//select|.//textarea');
        if (count($controls) === 1) return [bioco_import_form_control($controls[0], $type)];
        if ($controls) throw new RuntimeException('Multiple controls in one label.');
        if ($node->hasAttribute('for')) return [];
        return [bioco_import_form_text('<p>' . bioco_import_form_inner($node) . '</p>')];
    }
    if (in_array($tag, ['input', 'select', 'textarea'], true)) return [bioco_import_form_control($node, $type)];
    if (preg_match('/^h[1-6]$/D', $tag)) return [bioco_import_form_heading(bioco_import_form_inner($node), $tag)];
    if (in_array($tag, ['p', 'ul', 'ol', 'blockquote'], true)) return [bioco_import_form_text($node->ownerDocument->saveHTML($node))];
    if (!in_array($tag, ['div', 'section', 'form'], true)) throw new RuntimeException('Unsupported form markup: ' . $tag);
    if (in_array('form-group', $classes, true)) {
        $radios = bioco_import_form_query($node, './/input[@type="radio"]');
        if ($radios) {
            if ($type !== 'membership' || count($radios) !== 2) throw new RuntimeException('Unknown radio contract.');
            $labels = bioco_import_form_query($node, './label');
            $values = ['field' => 'membership.paymentType', 'label' => $labels ? trim($labels[0]->textContent) : '', 'options' => [], 'choice_layout' => 'radio', 'default_value' => ''];
            foreach ($radios as $radio) {
                if ($radio->getAttribute('name') !== 'paymentType' || $radio->hasAttribute('required')) throw new RuntimeException('Unknown payment contract.');
                $copy = $radio->parentNode->cloneNode(true);
                foreach (bioco_import_form_query($copy, './/input') as $input) $input->parentNode->removeChild($input);
                $values['options'][] = ['value' => $radio->getAttribute('value'), 'label' => trim($copy->textContent)];
                if ($radio->hasAttribute('checked')) $values['default_value'] = $radio->getAttribute('value');
            }
            if ($values['label'] === '') $values['allow_blank_label'] = 'on';
            $blocks = [bioco_form_layout_part('form-field', $values)];
            foreach (bioco_import_form_query($node, './p') as $hint) $blocks[] = bioco_import_form_text($node->ownerDocument->saveHTML($hint));
            return $blocks;
        }
    }
    $children = bioco_import_form_children($node, $type);
    if (in_array('form-step', $classes, true)) return [bioco_form_layout_row($children, 'bioco-form-step bioco-form-fields')];
    if (in_array('form-row', $classes, true)) {
        $columns = [];
        foreach ($node->childNodes as $child) {
            if (!($child instanceof DOMElement)) continue;
            $columns[] = bioco_form_layout_block('divi/column', ['module' => ['advanced' => ['type' => ['desktop' => ['value' => '1_2']]]]], bioco_import_form_node($child, $type));
        }
        if (count($columns) !== 2) throw new RuntimeException('Unknown legacy field columns.');
        $attrs = bioco_form_layout_attrs('bioco-form-fields'); $attrs['module']['advanced']['columnStructure']['desktop']['value'] = '1_2,1_2';
        return [bioco_form_layout_block('divi/row', $attrs, $columns)];
    }
    return $children;
}

function bioco_import_form_doi(array $values): array {
    $heading = static fn(string $key): array => ($values[$key] ?? '') === '' ? [] : [bioco_import_form_heading(esc_html($values[$key]), 'h3')];
    $copy = static fn(string $key): array => ($values[$key] ?? '') === '' ? [] : [bioco_import_form_text('<p>' . esc_html($values[$key]) . '</p>')];
    $home = ($values['home_link_label'] ?? '') === '' ? [] : [bioco_import_form_text('<p><a href="' . esc_url(home_url('/')) . '">' . esc_html($values['home_link_label']) . '</a></p>')];
    $layouts = ['missing' => [...$heading('error_title'), ...$copy('missing_token_message'), ...$home],
        'prompt' => $copy('confirmation_prompt'), 'success' => [...$heading('success_title'), ...$copy('success_text')]];
    foreach (['subscribe' => 'subscribe_text', 'visit' => 'visit_text', 'waiting_list' => 'waiting_list_text', 'contact' => 'contact_text'] as $type => $key) {
        foreach ($copy($key) as $block) {
            $block['attrs'] = array_replace_recursive($block['attrs'], bioco_form_layout_attrs('bioco-doi-type-' . $type));
            $layouts['success'][] = $block;
        }
    }
    foreach (['invalid', 'failure'] as $state) $layouts[$state] = [...$heading('error_title'), bioco_form_layout_part('form-action', ['action' => 'doi-error-details', 'label' => '']), ...$home];
    foreach ($layouts as &$children) if (!$children) $children[] = bioco_import_form_text('');
    unset($children);
    return bioco_doi_compose($layouts, $values['confirmation_button_label'] ?? '');
}

/** Initial legacy defaults become saved controls; authored wrapper design wins. */
function bioco_import_form_design(array $blocks): array {
    $token = static function (string $css, string $type = 'content'): string {
        // A content token is emptied by Divi's frontend parser before design CSS is generated.
        if ($type !== 'color') return 'var(--gvid-bioco-' . substr(hash('sha256', $css), 0, 16) . ')';
        return '$variable(' . wp_json_encode(['type' => $type, 'value' => ['name' => ($type === 'color' ? 'gcid-' : 'gvid-') . 'bioco-' . substr(hash('sha256', $css), 0, 16), 'settings' => new stdClass()]]) . ')$';
    };
    $desktop = static fn(array $value): array => ['desktop' => ['value' => $value]];
    foreach ($blocks as &$block) {
        $name = $block['blockName'];
        if ($name === 'divi/row') {
            $block['attrs']['module']['decoration'] = ['sizing' => $desktop(['width' => '100%', 'maxWidth' => '100%']),
                'spacing' => $desktop(['padding' => array_fill_keys(['top', 'bottom', 'left', 'right'], '0px')])];
        } elseif ($name === 'bioco-divi/form-field') {
            $values = bioco_form_part_values($block['attrs']);
            $identity = explode('.', $values['field']); $spec = bioco_form_contracts()[$identity[0]][$identity[1]];
            $block['attrs']['module']['decoration']['spacing'] = $desktop(['margin' => ['bottom' => $token('--wp--preset--spacing--40')]]);
            if (empty($values['label_html'])) $block['attrs']['label']['decoration']['font']['font'] = $desktop(['weight' => '600', 'color' => $token('--wp--preset--color--bioco-text', 'color')]);
            if ($spec['type'] !== 'checkbox' && ($values['choice_layout'] ?? '') !== 'radio') {
                $block['attrs']['control']['decoration'] = ['font' => ['font' => $desktop(['size' => $token('--wp--preset--font-size--base'), 'color' => $token('--wp--preset--color--bioco-text', 'color')])],
                    'background' => $desktop(['color' => $token('--wp--preset--color--bioco-surface', 'color')]),
                    'spacing' => $desktop(['padding' => ['top' => '10px', 'bottom' => '10px', 'left' => $token('--wp--preset--spacing--30'), 'right' => $token('--wp--preset--spacing--30')]]),
                    'border' => $desktop(['radius' => array_fill_keys(['topLeft', 'topRight', 'bottomLeft', 'bottomRight'], $token('--wp--custom--radius--sm')),
                        'styles' => ['all' => ['width' => '1px', 'style' => 'solid', 'color' => $token('--wp--preset--color--bioco-border', 'color')]]])];
            }
        } elseif ($name === 'bioco-divi/form-action') {
            $action = bioco_form_part_values($block['attrs'])['action'] ?? 'submit';
            if (in_array($action, ['submit', 'back', 'next', 'confirm'], true)) {
                $block['attrs']['control']['decoration'] = ['font' => ['font' => $desktop(['weight' => '700', 'lineHeight' => '1.5em', 'color' => $action === 'back' ? $token('--wp--preset--color--bioco-green', 'color') : '#ffffff'])],
                    'background' => $desktop(['color' => $token($action === 'back' ? '--wp--preset--color--bioco-surface' : '--wp--preset--color--bioco-green', 'color')]),
                    'spacing' => $desktop(['padding' => ['top' => '12px', 'bottom' => '12px', 'left' => $token('--wp--preset--spacing--40'), 'right' => $token('--wp--preset--spacing--40')]]),
                    'border' => $desktop(['radius' => array_fill_keys(['topLeft', 'topRight', 'bottomLeft', 'bottomRight'], $token('--wp--custom--radius--md')),
                        'styles' => ['all' => ['width' => '2px', 'style' => 'solid', 'color' => $token('--wp--preset--color--bioco-green', 'color')]]])];
            }
        }
        if ($block['innerBlocks']) $block['innerBlocks'] = bioco_import_form_design($block['innerBlocks']);
    }
    unset($block); return $blocks;
}

function bioco_import_form_layout(string $group, array $capture, array $saved, bool $event_context = false): array {
    $type = bioco_import_form_groups()[$group]; $values = $capture['values'];
    if (empty($values['anchor'])) {
        $defaults = ['contact-form' => 'kontakt-formular', 'subscribe-form' => 'newsletter-anmeldung', 'visit-day-form' => 'schnuppertag-anmeldung',
            'waiting-list-form' => 'warteliste-anmeldung', 'event-signup-form' => 'event-anmeldung', 'membership-form' => 'mitgliedschaft-anmeldung', 'doi-confirm' => 'newsletter-bestaetigung'];
        $values['anchor'] = $defaults[$group];
    }
    if ($type === 'doi') $layout = bioco_import_form_doi($values);
    else {
        $document = new DOMDocument(); $previous = libxml_use_internal_errors(true);
        try {
            if (!$document->loadHTML('<?xml encoding="UTF-8"><div id="bioco-adoption-capture">' . $capture['html'] . '</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) throw new RuntimeException('Cannot parse captured form.');
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $root = $document->getElementById('bioco-adoption-capture');
        if (!$root) throw new RuntimeException('Missing capture root.');
        $children = bioco_import_form_children($root, $type);
        if ($type === 'event-signup' && $event_context && ($values['event_title_prefix'] ?? '') !== ''
            && !(($values['title'] ?? '') !== '' && !preg_match('/<h[1-6]\b/i', $values['text'] ?? ''))) {
            $binding = '$variable(' . wp_json_encode(['type' => 'content', 'value' => ['name' => 'bioco_event_title', 'settings' => new stdClass()]]) . ')$';
            array_unshift($children, bioco_import_form_heading(esc_html($values['event_title_prefix']) . ' ' . $binding, 'h2'));
        }
        $fields = []; $steps = 0; $actions = [];
        $inspect = function (array $blocks) use (&$inspect, &$fields, &$steps, &$actions): void {
            foreach ($blocks as $block) {
                if (($block['blockName'] ?? '') === 'bioco-divi/form-field') $fields[] = bioco_form_part_values($block['attrs']);
                if (($block['blockName'] ?? '') === 'bioco-divi/form-action') $actions[] = bioco_form_part_values($block['attrs'])['action'];
                if (str_contains($block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] ?? '', 'bioco-form-step')) $steps++;
                $inspect($block['innerBlocks'] ?? []);
            }
        };
        $inspect($children); bioco_form_validate_fields($type, $fields);
        if (count(array_filter($actions, static fn($a) => $a === 'submit')) !== 1) throw new RuntimeException('Exactly one captured submit action is required.');
        if ($type === 'membership') {
            if (!$steps) throw new RuntimeException('Missing membership steps.');
            foreach (['progress', 'back', 'next', 'summary'] as $action) if (count(array_filter($actions, static fn($a) => $a === $action)) !== 1) throw new RuntimeException('Missing membership action: ' . $action);
            $values['commitment_count'] = (string) count(array_filter($fields, static fn($f) => $f['field'] === 'membership.commitmentAccepted'));
            $values['thank_you_url'] = '/anmeldung-danke/';
        }
        $rows = [bioco_form_layout_row([bioco_form_layout_part('form-controller', bioco_form_controller_values($type, $values))], 'bioco-form-controller')];
        $pending = [];
        foreach ($children as $child) {
            if ($child['blockName'] === 'divi/row') {
                if ($pending) { $rows[] = bioco_form_layout_row($pending, 'bioco-form-fields'); $pending = []; }
                $rows[] = $child;
            } else $pending[] = $child;
        }
        if ($pending) $rows[] = bioco_form_layout_row($pending, 'bioco-form-fields');
        if (($values['success_message'] ?? '') !== '') $rows[] = bioco_form_layout_row([bioco_import_form_text('<p>' . esc_html($values['success_message']) . '</p>')], 'bioco-form-state-success');
        $rows[] = bioco_form_layout_row([bioco_form_layout_part('form-action', ['action' => 'error-details', 'label' => $values['fallback_error'] ?? ''])], 'bioco-form-state-error');
        $layout = bioco_form_layout_block('divi/section', [], $rows);
    }
    // A row can replace a module within an existing column. Keep its parent,
    // sibling markers, geometry, custom attributes and effective anchor intact.
    $scope = $type === 'doi' ? 'bioco-doi-scope' : 'bioco-form-scope';
    $wrapper = bioco_form_layout_row(bioco_import_form_design($layout['innerBlocks']), $scope);
    $wrapper['attrs']['module']['decoration']['sizing']['desktop']['value'] = ['width' => '100%', 'maxWidth' => '640px', 'alignment' => 'left'];
    $wrapper['attrs']['module'] = array_replace_recursive($wrapper['attrs']['module'], $saved['attrs']['module'] ?? []);
    $html = $wrapper['attrs']['module']['advanced']['htmlAttributes']['desktop']['value'] ?? [];
    if (is_string($html)) $html = json_decode($html, true, 512, JSON_THROW_ON_ERROR);
    $html['class'] = trim(($html['class'] ?? '') . ' ' . ($values['className'] ?? '') . ' ' . $scope);
    $html['id'] = $values['anchor'] ?? '';
    $wrapper['attrs']['module']['advanced']['htmlAttributes']['desktop']['value'] = $html;
    foreach ($wrapper['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [] as $index => $attribute) {
        if (($attribute['name'] ?? '') === 'class') $wrapper['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][$index]['value'] = trim(($attribute['value'] ?? '') . ' ' . $scope);
    }
    if (isset($saved['attrs']['builderVersion'])) $wrapper['attrs']['builderVersion'] = $saved['attrs']['builderVersion'];
    return $wrapper;
}

function bioco_import_form_plan(array $record, string $site, array $options, ?callable $capture = null): array {
    $columns = ['ID', 'post_type', 'post_status', 'post_title', 'post_content', 'post_modified_gmt'];
    if (array_diff(array_keys($record), $columns) || array_diff($columns, array_keys($record)) || !is_int($record['ID']) || $record['ID'] < 1 || in_array($record['post_type'], ['revision', 'attachment'], true)) throw new RuntimeException('Expected a complete selected saved record.');
    foreach (array_diff($columns, ['ID']) as $key) if (!is_string($record[$key])) throw new RuntimeException('Invalid saved record.');
    if ($site !== home_url('/')) throw new RuntimeException('Form snapshot belongs to another site.');
    bioco_import_form_option_guard($options);
    $capture ??= 'bioco_import_form_capture';
    $captures = []; $report = [];
    $walk = function (array $blocks, array $path = []) use (&$walk, &$captures, &$report, $capture, $record): array {
        foreach ($blocks as $index => &$block) {
            $at = [...$path, $index]; $group = substr($block['blockName'] ?? '', strlen('bioco-divi/'));
            if (str_starts_with($block['blockName'] ?? '', 'bioco-divi/') && isset(bioco_import_form_groups()[$group])) {
                if ($group === 'event-signup-form' && in_array($record['post_type'], ['et_body_layout', 'et_pb_layout'], true)) throw new RuntimeException('Event signup in a reusable layout requires explicit manual event-context conversion.');
                $current = $capture($group, $block);
                if (!is_array($current['values'] ?? null) || !is_string($current['html'] ?? null)) throw new RuntimeException('Invalid form capture.');
                if (($current['source'] ?? '') !== bioco_import_form_source_hash($block)) throw new RuntimeException('Capture does not match saved source.');
                $captures[implode('.', $at)] = $current;
                $block = bioco_import_form_layout($group, $current, $block, $record['post_type'] === 'event');
                $report[] = ['path' => $at, 'group' => $group, 'action' => 'convert'];
                if ($group === 'waiting-list-form') {
                    $warnings = [];
                    foreach ($current['values']['interest_options'] ?? [] as $option) if (preg_match('/^program[123]$/D', $option['value'] ?? '')) $warnings[] = $option;
                    if ($warnings) $report[count($report) - 1]['unconfirmed_options'] = $warnings;
                }
            } elseif (!empty($block['innerBlocks'])) $block['innerBlocks'] = $walk($block['innerBlocks'], $at);
        }
        unset($block); return $blocks;
    };
    $blocks = $walk(parse_blocks($record['post_content']));
    $plan = ['version' => 1, 'kind' => 'form-content', 'site' => $site, 'before' => $record, 'options' => $options,
        'captures' => $captures, 'after_content' => $report ? serialize_blocks($blocks) : $record['post_content'], 'changed' => count($report), 'report' => $report];
    $plan['fingerprint'] = hash('sha256', wp_json_encode($plan)); return $plan;
}

/** Both raw options are guarded, including absence, IDs and autoload semantics. */
function bioco_import_form_option_guard(array $options, bool $materialize = false): string {
    global $wpdb;
    $table = $materialize ? "(SELECT * FROM {$wpdb->options} LIMIT 18446744073709551615) AS guarded_options" : $wpdb->options;
    if (array_keys($options) !== ['bioco_form_messages', 'bioco_divi_form_message_groups']) throw new RuntimeException('Invalid form option snapshot.');
    $guards = [];
    foreach ($options as $name => $row) {
        $scope = $wpdb->prepare('BINARY option_name = BINARY %s', $name);
        if ($row === null) { $guards[] = "NOT EXISTS (SELECT 1 FROM {$table} WHERE $scope)"; continue; }
        if (!is_array($row) || array_keys($row) !== ['option_id', 'option_name', 'option_value', 'autoload'] || $row['option_name'] !== $name) throw new RuntimeException('Invalid raw option row.');
        $fields = [];
        foreach ($row as $key => $value) {
            if (!is_string($value)) throw new RuntimeException('Invalid raw option value.');
            $fields[] = $wpdb->prepare("BINARY `$key` = BINARY %s", $value);
        }
        $guards[] = "(SELECT COUNT(*) FROM {$table} WHERE $scope) = 1";
        $guards[] = "EXISTS (SELECT 1 FROM {$table} WHERE " . implode(' AND ', $fields) . ')';
    }
    return implode(' AND ', $guards);
}

function bioco_import_form_sql(array $plan, string $expected, bool $restore = false): string {
    global $wpdb;
    if (($plan['kind'] ?? '') !== 'form-content') throw new RuntimeException('Invalid form content plan.');
    $unused = $plan['captures'] ?? [];
    $capture = static function (string $group, array $block) use (&$unused): array {
        $source = bioco_import_form_source_hash($block);
        foreach ($unused as $path => $value) if (($value['source'] ?? '') === $source) { unset($unused[$path]); return $value; }
        throw new RuntimeException('Missing saved capture.');
    };
    $verified = bioco_import_form_plan($plan['before'], $plan['site'], $plan['options'], $capture);
    if ($unused || $plan !== $verified || !hash_equals($verified['fingerprint'], $expected)) throw new RuntimeException('Form preview changed.');
    if (!$plan['changed']) return '';
    $guards = [];
    foreach ($plan['before'] as $column => $value) {
        if ($restore && $column === 'post_content') $value = $plan['after_content'];
        $guards[] = $wpdb->prepare("BINARY `$column` = BINARY %s", (string) $value);
    }
    $guards[] = bioco_import_form_option_guard($plan['options']);
    $content = $restore ? $plan['before']['post_content'] : $plan['after_content'];
    return $wpdb->remove_placeholder_escape($wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = %s WHERE ", $content) . implode(' AND ', $guards));
}

function bioco_import_form_options(): array {
    global $wpdb; $options = [];
    foreach (['bioco_form_messages', 'bioco_divi_form_message_groups'] as $name) {
        $options[$name] = $wpdb->get_row($wpdb->prepare("SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name), ARRAY_A);
    }
    return $options;
}

/** Exact block-related inventory; subscriber/submission records are excluded. */
function bioco_import_form_inventory_scope(): string {
    global $wpdb; $matches = [];
    foreach ([...array_keys(bioco_import_form_groups()), 'form-controller', 'doi-controller'] as $name) {
        $matches[] = $wpdb->prepare('post_content LIKE %s', '%' . $wpdb->esc_like('bioco-divi/' . $name) . '%');
    }
    return "post_type NOT IN ('revision','attachment') AND post_status NOT IN ('trash','auto-draft') AND (" . implode(' OR ', $matches) . ')';
}

function bioco_import_form_inventory(): array {
    global $wpdb; $scope = bioco_import_form_inventory_scope();
    $rows = $wpdb->get_results("SELECT ID, post_type, post_status, post_title, post_content, post_modified_gmt FROM {$wpdb->posts} WHERE $scope ORDER BY ID", ARRAY_A);
    foreach ($rows as &$row) $row['ID'] = (int) $row['ID'];
    unset($row); return $rows;
}

function bioco_import_form_inventory_guard(array $records): string {
    global $wpdb; $rows = []; $ids = [];
    foreach ($records as $record) {
        if (array_keys($record) !== ['ID', 'post_type', 'post_status', 'post_title', 'post_content', 'post_modified_gmt'] || !is_int($record['ID']) || $record['ID'] < 1 || isset($ids[$record['ID']])) throw new RuntimeException('Invalid form inventory.');
        $ids[$record['ID']] = true; $fields = [];
        foreach ($record as $key => $value) {
            if ($key !== 'ID' && !is_string($value)) throw new RuntimeException('Invalid form inventory value.');
            $fields[] = $wpdb->prepare("BINARY `$key` = BINARY %s", (string) $value);
        }
        $rows[] = '(' . implode(' AND ', $fields) . ')';
    }
    $scope = bioco_import_form_inventory_scope();
    return "(SELECT COUNT(*) FROM {$wpdb->posts} WHERE $scope) = " . count($rows)
        . " AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} WHERE $scope AND NOT (" . ($rows ? implode(' OR ', $rows) : '0') . '))';
}

/** Retirement cannot hide settings behind an incomplete or orphan native form. */
function bioco_import_form_native_inventory(array $blocks, array $selected): array {
    $adopted = []; $controllers = [];
    $class = static fn(array $block): array => preg_split('/\s+/', $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
    $owned = function (array $children) use (&$owned, $class): array {
        $result = [];
        foreach ($children as $block) {
            if (array_intersect($class($block), ['bioco-form-scope', 'bioco-doi-scope'])) continue;
            $result[] = $block; $result = array_merge($result, $owned($block['innerBlocks'] ?? []));
        }
        return $result;
    };
    $walk = function (array $children) use (&$walk, &$adopted, &$controllers, $owned, $class, $selected): void {
        foreach ($children as $block) {
            $name = $block['blockName'] ?? '';
            if (in_array($name, ['bioco-divi/form-controller', 'bioco-divi/doi-controller'], true)) {
                $type = $name === 'bioco-divi/doi-controller' ? 'doi' : (bioco_form_part_values($block['attrs'])['form_type'] ?? '');
                if (in_array($type, $selected, true)) $controllers[$type] = ($controllers[$type] ?? 0) + 1;
            }
            if (array_intersect($class($block), ['bioco-form-scope', 'bioco-doi-scope'])) {
                $parts = $owned($block['innerBlocks'] ?? []);
                $control = array_values(array_filter($parts, static fn($part) => in_array($part['blockName'] ?? '', ['bioco-divi/form-controller', 'bioco-divi/doi-controller'], true)));
                $types = array_map(static fn($part) => $part['blockName'] === 'bioco-divi/doi-controller' ? 'doi' : (bioco_form_part_values($part['attrs'])['form_type'] ?? ''), $control);
                if (array_intersect($types, $selected)) {
                    if (count($control) !== 1) throw new RuntimeException('Native scope must contain exactly one controller.');
                    $type = $types[0]; $fields = []; $actions = []; $states = []; $steps = 0;
                    foreach ($parts as $part) {
                        if ($part['blockName'] === 'bioco-divi/form-field') $fields[] = bioco_form_part_values($part['attrs']);
                        if ($part['blockName'] === 'bioco-divi/form-action') $actions[] = bioco_form_part_values($part['attrs'])['action'] ?? 'submit';
                        foreach ($class($part) as $marker) {
                            if ($marker === 'bioco-form-step') $steps++;
                            if (str_starts_with($marker, 'bioco-doi-state-')) $states[] = substr($marker, strlen('bioco-doi-state-'));
                        }
                    }
                    if ($type === 'doi') {
                        foreach (['missing', 'prompt', 'success', 'invalid', 'failure'] as $state) if (count(array_filter($states, static fn($s) => $s === $state)) !== 1) throw new RuntimeException('Incomplete native DOI states.');
                        if (count(array_filter($actions, static fn($a) => $a === 'confirm')) !== 1) throw new RuntimeException('Missing native DOI confirmation action.');
                    } else {
                        bioco_form_validate_fields($type, $fields);
                        if (count(array_filter($actions, static fn($a) => $a === 'submit')) !== 1) throw new RuntimeException('Missing native submission action.');
                        if ($type === 'membership') {
                            $count = bioco_form_part_values($control[0]['attrs'])['commitment_count'] ?? '';
                            if (!$steps || !ctype_digit($count) || (int) $count < 1 || (int) $count > 100 || (int) $count !== count(array_filter($fields, static fn($f) => $f['field'] === 'membership.commitmentAccepted'))) throw new RuntimeException('Incomplete native membership steps or commitments.');
                            $check_steps = function (array $children, int $depth = 0) use (&$check_steps, $class): void {
                                foreach ($children as $child) {
                                    if (array_intersect($class($child), ['bioco-form-scope', 'bioco-doi-scope'])) continue;
                                    $inside = $depth + (in_array('bioco-form-step', $class($child), true) ? 1 : 0);
                                    if ($inside > 1 || (($child['blockName'] ?? '') === 'bioco-divi/form-field' && $inside !== 1)) throw new RuntimeException('Membership fields need one un-nested native step.');
                                    $check_steps($child['innerBlocks'] ?? [], $inside);
                                }
                            };
                            $check_steps($block['innerBlocks']);
                            foreach (['back', 'next', 'progress'] as $action) if (count(array_filter($actions, static fn($a) => $a === $action)) !== 1) throw new RuntimeException('Missing native wizard action.');
                        }
                    }
                    $adopted[$type] = ($adopted[$type] ?? 0) + 1;
                }
            }
            $walk($block['innerBlocks'] ?? []);
        }
    };
    $walk($blocks);
    ksort($controllers); ksort($adopted);
    if ($controllers !== $adopted) throw new RuntimeException('Orphan or incomplete native controller.');
    return $adopted;
}

/** Separate adoption gate. Every non-trash saved instance, including drafts, counts. */
function bioco_import_form_retirement_plan(array $groups, array $records, array $options, string $site): array {
    if (!$groups || !array_is_list($groups) || count(array_unique($groups)) !== count($groups) || array_diff($groups, array_keys(bioco_import_form_groups()))) throw new RuntimeException('Select explicit form groups; shared messages cannot retire.');
    if ($site !== home_url('/')) throw new RuntimeException('Retirement snapshot belongs to another site.');
    bioco_import_form_option_guard($options); bioco_import_form_inventory_guard($records);
    $adopted = [];
    foreach ($records as $record) {
        foreach ($groups as $group) if (has_block('bioco-divi/' . $group, $record['post_content'])) throw new RuntimeException('A saved legacy instance remains: ' . $group . ' #' . $record['ID']);
        $native = bioco_import_form_native_inventory(parse_blocks($record['post_content']), array_map(static fn($group) => bioco_import_form_groups()[$group], $groups));
        foreach ($native as $type => $count) $adopted[$type] = ($adopted[$type] ?? 0) + $count;
    }
    foreach ($groups as $group) if (empty($adopted[bioco_import_form_groups()[$group]])) throw new RuntimeException('Missing native instance: ' . $group);
    $row = $options['bioco_divi_form_message_groups'];
    $before = $row === null ? [] : @unserialize($row['option_value'], ['allowed_classes' => false]);
    if (!is_array($before) || !array_is_list($before) || array_diff($before, array_keys(bioco_import_form_groups()))) throw new RuntimeException('Invalid existing retired groups.');
    $after = array_values(array_unique([...$before, ...$groups]));
    $plan = ['version' => 1, 'kind' => 'form-retirement', 'site' => $site, 'groups' => $groups, 'records' => $records,
        'options' => $options, 'after_value' => serialize($after), 'changed' => $row === null || $row['option_value'] !== serialize($after)];
    $plan['fingerprint'] = hash('sha256', wp_json_encode($plan)); return $plan;
}

function bioco_import_form_retirement_sql(array $plan, string $expected, bool $restore = false): string {
    global $wpdb;
    if (($plan['kind'] ?? '') !== 'form-retirement') throw new RuntimeException('Invalid retirement plan.');
    $verified = bioco_import_form_retirement_plan($plan['groups'], $plan['records'], $plan['options'], $plan['site']);
    if ($plan !== $verified || !hash_equals($verified['fingerprint'], $expected)) throw new RuntimeException('Retirement preview changed.');
    if (!$plan['changed']) return '';
    $name = 'bioco_divi_form_message_groups'; $before = $plan['options'][$name]; $options = $plan['options'];
    if ($restore) {
        // When adoption inserted this option, its generated ID is unknown to
        // the old preview. Match its exact value/autoload and unique name.
        $options[$name] = $before === null ? null : array_replace($before, ['option_value' => $plan['after_value']]);
    }
    $guard = bioco_import_form_inventory_guard($plan['records']);
    if ($restore && $before === null) {
        $archived = bioco_import_form_option_guard(array_replace($options, [$name => null]), true);
        // Guard the other option, but replace the expected absence of our row.
        $suffix = " AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM {$wpdb->options} LIMIT 18446744073709551615) AS guarded_options WHERE " . $wpdb->prepare('BINARY option_name = BINARY %s', $name) . ')';
        if (!str_ends_with($archived, $suffix)) throw new RuntimeException('Invalid absent-option guard.');
        $guard .= ' AND ' . substr($archived, 0, -strlen($suffix));
        $sql = $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE BINARY option_name = BINARY %s AND BINARY option_value = BINARY %s AND BINARY autoload = BINARY %s AND ", $name, $plan['after_value'], 'no') . $guard;
    } else {
        $guard .= ' AND ' . bioco_import_form_option_guard($options, true);
        if ($before === null) $sql = $wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) SELECT %s, %s, %s WHERE ", $name, $plan['after_value'], 'no') . $guard;
        else $sql = $wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE BINARY option_name = BINARY %s AND ", $restore ? $before['option_value'] : $plan['after_value'], $name) . $guard;
    }
    return $wpdb->remove_placeholder_escape($sql);
}
