<?php
/** Native, independently arrangeable form controls. Transport stays in bioco-forms. */
if (!defined('ABSPATH')) exit;

function bioco_form_contracts(): array {
    $person = ['name' => ['type' => 'text', 'required' => true], 'email' => ['type' => 'email', 'required' => true]];
    $notes = ['type' => 'textarea', 'required' => false];
    $privacy = ['type' => 'checkbox', 'required' => true];
    return [
        'subscribe' => ['email' => ['type' => 'email', 'required' => true], 'name' => ['type' => 'text', 'required' => false], 'privacy_accept' => $privacy],
        'visit-day' => $person + ['phone' => ['type' => 'tel', 'required' => true], 'visit_date' => ['type' => 'date', 'required' => true], 'participants' => ['type' => 'number', 'required' => true, 'min' => 1, 'value' => '1'], 'notes' => $notes, 'privacy_accept' => $privacy],
        'waiting-list' => $person + ['phone' => ['type' => 'tel', 'required' => true], 'interest' => ['type' => 'select', 'required' => true], 'notes' => $notes, 'privacy_accept' => $privacy],
        'event-signup' => $person + ['phone' => ['type' => 'tel', 'required' => false], 'notes' => $notes],
        'membership' => [
            'commitmentAccepted' => ['type' => 'checkbox', 'required' => true, 'array' => true, 'boolean' => true],
            'firstName' => ['type' => 'text', 'required' => true], 'lastName' => ['type' => 'text', 'required' => true],
            'address' => ['type' => 'text', 'required' => true], 'zip' => ['type' => 'text', 'required' => true], 'city' => ['type' => 'text', 'required' => true],
            'email' => ['type' => 'email', 'required' => true], 'phone' => ['type' => 'tel', 'required' => false], 'mobilePhone' => ['type' => 'tel', 'required' => false], 'birthday' => ['type' => 'date', 'required' => false],
            'depot' => ['type' => 'select', 'required' => false], 'paymentType' => ['type' => 'select', 'required' => false],
            'preferredDays' => ['type' => 'checkbox', 'required' => false, 'array' => true], 'preferredTimes' => ['type' => 'checkbox', 'required' => false, 'array' => true],
            'activityAreas' => ['type' => 'checkbox', 'required' => false, 'array' => true], 'zusatzabos' => ['type' => 'checkbox', 'required' => false, 'array' => true],
            'otherActivity' => $notes, 'weitereProdukte' => $notes, 'comment' => $notes, 'privacyAccept' => $privacy,
        ],
        'contact' => [
            'name' => ['type' => 'text', 'required' => true],
            'email' => ['type' => 'email', 'required' => true],
            'phone' => ['type' => 'tel', 'required' => false],
            'subject' => ['type' => 'text', 'required' => true],
            'message' => ['type' => 'textarea', 'required' => true],
        ],
    ];
}

function bioco_form_part_values(array $attrs): array {
    $values = [];
    foreach (['form_type', 'field', 'label', 'help', 'placeholder', 'pending_label', 'success_message', 'fallback_error', 'captcha_error', 'transport_error', 'action', 'progress_label', 'thank_you_url', 'commitment_count', 'choice_value'] as $key) {
        $value = $attrs[$key]['innerContent']['desktop']['value'] ?? null;
        if (is_string($value)) $values[$key] = $value;
    }
    $options = $attrs['options']['innerContent']['desktop']['value'] ?? null;
    if (is_array($options) && array_is_list($options)) $values['options'] = $options;
    return $values;
}

/** This is also the fixture seam. Inert mode never contains endpoints or CAPTCHA configuration. */
function bioco_form_render_part(string $kind, array $values, string $mode = 'inert'): string {
    if ($kind === 'form-controller' && ($values['form_type'] ?? '') === 'membership') {
        $count = $values['commitment_count'] ?? '';
        if ((!is_string($count) && !is_int($count)) || !ctype_digit((string) $count) || (int) $count < 1 || (int) $count > 100) return '';
    }
    if ($mode === 'public') wp_enqueue_style('bioco-native-form-composition');
    if ($kind === 'doi-controller') return bioco_doi_render_controller($mode);
    if ($kind === 'form-controller') {
        $type = $values['form_type'] ?? '';
        if (!isset(bioco_form_contracts()[$type])) return '';
        $id = wp_unique_id('bioco-native-form-');
        $html = '<div class="bioco-native-controller"><div class="form-message" role="status" aria-live="polite" hidden></div>';
        $config = [];
        if ($mode === 'public') {
            $captcha = bioco_forms_turnstile_config();
            $config = ['restUrl' => rest_url('bioco/v1/' . $type), 'turnstileSiteKey' => $captcha['configured'] ? $captcha['site_key'] : '', 'fields' => bioco_form_contracts()[$type]];
            foreach (['successMessage' => 'success_message', 'fallbackError' => 'fallback_error', 'captchaError' => 'captcha_error', 'transportError' => 'transport_error'] as $key => $field) {
                $config[$key] = $values[$field] ?? '';
            }
            if ($type === 'membership') {
                $config['thankYouUrl'] = $values['thank_you_url'] ?? '';
                $catalog = function_exists('bioco_price_catalog') ? bioco_price_catalog() : null;
                if ($catalog !== null) {
                    $config['aboTypes'] = [];
                    foreach ($catalog['tiers'] ?? [] as $tier) {
                        if (is_string($tier['key'] ?? null) && in_array($tier['abo_type'] ?? null, ['halb', 'standard', 'doppel'], true)) $config['aboTypes'][$tier['key']] = $tier['abo_type'];
                    }
                    $config['aboTypes'] = (object) $config['aboTypes'];
                }
                wp_enqueue_script('bioco-membership-form-view-script');
            }
            wp_enqueue_script('bioco-native-form-composition');
        }
        $html .= '<form id="' . esc_attr($id) . '" class="bioco-form" data-form="' . esc_attr($type) . '" data-native-controller data-bioco-instance-config="' . esc_attr(wp_json_encode($config)) . '" method="post" novalidate>';
        if ($type === 'event-signup') {
            $event = is_singular('event');
            $html .= '<input type="hidden" name="eventId" value="' . esc_attr($event ? (string) get_the_ID() : '') . '"><input type="hidden" name="eventTitle" value="' . esc_attr($event ? get_the_title() : '') . '">';
        }
        if ($type === 'membership') {
            $hidden = ['submissionId' => '', 'commitmentCount' => $count, 'commitmentSignature' => bioco_forms_commitment_signature((int) $count), 'membershipType' => 'abo', 'aboType' => 'standard', 'additionalShares' => '0', 'sharesOnly' => '0'];
            foreach ($hidden as $key => $value) $html .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
        if ($mode === 'public') $html .= '<div data-form-captcha></div>';
        return $html . '</form></div>';
    }
    if ($kind === 'form-action') {
        $action = $values['action'] ?? 'submit';
        if ($action === 'confirm') return ($values['label'] ?? '') === '' ? '' : '<button type="submit" class="bioco-form-control bioco-form-action" data-bioco-doi-confirm disabled>' . esc_html($values['label']) . '</button>';
        if ($action === 'error-details') return '<p data-bioco-form-error-detail role="alert"></p>';
        if ($action === 'summary') return '<dl data-membership-summary></dl>';
        if ($action === 'progress') return '<p data-wizard-progress role="status" aria-live="polite" data-label="' . esc_attr($values['label'] ?? '') . '"></p>';
        if (!in_array($action, ['submit', 'next', 'back'], true) || ($values['label'] ?? '') === '') return '';
        $type = $action === 'submit' ? 'submit' : 'button';
        $hook = $action === 'submit' ? '' : ' data-wizard-' . $action;
        return '<button type="' . $type . '"' . $hook . ' class="bioco-form-control bioco-form-action" data-native-form-control data-submit-label="' . esc_attr($values['label'] ?? '') . '" data-submitting-label="' . esc_attr($values['pending_label'] ?? '') . '" disabled>' . esc_html($values['label'] ?? '') . '</button>';
    }
    if (!in_array($kind, ['form-field', 'form-value'], true)) return '';
    $identity = $values['field'] ?? '';
    $parts = explode('.', $identity, 2);
    $spec = bioco_form_contracts()[$parts[0] ?? ''][$parts[1] ?? ''] ?? null;
    $label = $values['label'] ?? '';
    if ($spec === null || ($kind === 'form-field' && $label === '')) return '';
    if ($kind === 'form-value') return '<div class="bioco-form-value">' . ($label === '' ? '' : '<span class="bioco-form-label">' . esc_html($label) . '</span>') . '<output class="bioco-form-control" data-bioco-review-field="' . esc_attr($identity) . '"></output></div>';
    $id = wp_unique_id('bioco-native-field-');
    $help = $values['help'] ?? '';
    $attributes = ' class="bioco-form-control" id="' . esc_attr($id) . '" name="' . esc_attr($parts[1] . (!empty($spec['array']) ? '[]' : '')) . '" data-native-form-control data-bioco-field="' . esc_attr($identity) . '" disabled';
    if ($spec['required']) $attributes .= ' required';
    if (isset($spec['min'])) $attributes .= ' min="' . esc_attr($spec['min']) . '"';
    if (!empty($spec['boolean'])) $attributes .= ' data-bool-array';
    if (isset($spec['value'])) $attributes .= ' value="' . esc_attr($spec['value']) . '"';
    elseif (($values['choice_value'] ?? '') !== '') $attributes .= ' value="' . esc_attr($values['choice_value']) . '"';
    if ($help !== '') $attributes .= ' aria-describedby="' . esc_attr($id . '-help') . '"';
    if (($values['placeholder'] ?? '') !== '') $attributes .= ' placeholder="' . esc_attr($values['placeholder']) . '"';
    $html = '<div class="bioco-form-field"><label class="bioco-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
    if ($spec['type'] === 'select') {
        $options = $values['options'] ?? [];
        if (!array_is_list($options)) return '';
        $html .= '<select' . $attributes . '><option value="">' . esc_html($values['placeholder'] ?? '') . '</option>';
        foreach ($options as $option) {
            if (!is_array($option) || !is_string($option['value'] ?? null) || !is_string($option['label'] ?? null) || $option['value'] === '' || $option['label'] === '') return '';
            $html .= '<option value="' . esc_attr($option['value']) . '">' . esc_html($option['label']) . '</option>';
        }
        if ($spec['required'] && !$options) return '';
        $html .= '</select>';
    } else {
        $html .= $spec['type'] === 'textarea' ? '<textarea' . $attributes . ' rows="6"></textarea>' : '<input type="' . esc_attr($spec['type']) . '"' . $attributes . '>';
    }
    if ($help !== '') $html .= '<p class="bioco-form-help" id="' . esc_attr($id . '-help') . '">' . esc_html($help) . '</p>';
    return $html . '</div>';
}

function bioco_form_render_block($attrs, $content, $block, $elements, $default_printed_style_attrs = []) {
    $name = $block->block_type->name;
    $kind = substr($name, strlen('bioco-divi/'));
    $html = bioco_form_render_part($kind, bioco_form_part_values($attrs), bioco_native_render_mode());
    if (!$elements || !class_exists('ET\\Builder\\Packages\\Module\\Module')) return $html;
    return ET\Builder\Packages\Module\Module::render([
        'name' => $name, 'attrs' => $attrs, 'elements' => $elements,
        'id' => $block->parsed_block['id'], 'orderIndex' => $block->parsed_block['orderIndex'],
        'storeInstance' => $block->parsed_block['storeInstance'],
        'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
        'moduleCategory' => $block->block_type->category,
        'stylesComponent' => 'bioco_form_module_styles',
        'scriptDataComponent' => static function ($args) { $args['elements']->script_data(['attrName' => 'module']); },
        'children' => $elements->style_components(['attrName' => 'module']) . $html,
    ]);
}

function bioco_form_module_styles(array $args): void {
    $styles = [];
    $kind = substr($args['name'], strlen('bioco-divi/'));
    $attributes = bioco_form_metadata()[$kind]['attributes'] ?? [];
    foreach (['module', 'control', 'label', 'help'] as $attr) {
        if (isset($attributes[$attr]['settings']['decoration'])) $styles[] = $args['elements']->style(['attrName' => $attr]);
    }
    ET\Builder\FrontEnd\Module\Style::add([
        'id' => $args['id'], 'name' => $args['name'], 'orderIndex' => $args['orderIndex'],
        'storeInstance' => $args['storeInstance'], 'styles' => $styles,
    ]);
}

function bioco_form_metadata(): array {
    static $modules;
    if ($modules !== null) return $modules;
    $modules = [];
    foreach (['form-controller', 'form-field', 'form-action', 'form-value', 'doi-controller'] as $kind) {
        $modules[$kind] = json_decode(file_get_contents(__DIR__ . '/../form-composition/' . $kind . '/module.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    return $modules;
}

add_action('init', function () {
    $base = content_url('mu-plugins/bioco-core/');
    wp_register_script('bioco-native-form-composition', $base . 'form-composition/view.js', ['bioco-forms-lifecycle'], (string) filemtime(__DIR__ . '/../form-composition/view.js'), true);
    wp_register_style('bioco-native-form-composition', $base . 'form-composition/style.css', [], (string) filemtime(__DIR__ . '/../form-composition/style.css'));
    if (!class_exists('ET\\Builder\\Packages\\ModuleLibrary\\ModuleRegistration')) return;
    foreach (array_keys(bioco_form_metadata()) as $kind) {
        ET\Builder\Packages\ModuleLibrary\ModuleRegistration::register_module(__DIR__ . '/../form-composition/' . $kind, ['render_callback' => 'bioco_form_render_block']);
    }
}, 21);

add_action('divi_visual_builder_assets_before_enqueue_scripts', function () {
    if (!class_exists('ET\\Builder\\VisualBuilder\\Assets\\PackageBuildManager')) return;
    ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build([
        'name' => 'bioco-form-composition', 'version' => (string) filemtime(__DIR__ . '/../form-composition/editor.js'),
        'script' => ['src' => content_url('mu-plugins/bioco-core/form-composition/editor.js'),
            'deps' => ['divi-vendor-react', 'divi-vendor-react-dom'], 'args' => ['in_footer' => true],
            'data_top_window' => [], 'data_app_window' => ['modules' => bioco_form_metadata(), 'contracts' => bioco_form_contracts()],
            'enqueue_top_window' => false, 'enqueue_app_window' => true],
        'style' => ['src' => content_url('mu-plugins/bioco-core/form-composition/style.css'), 'enqueue_top_window' => false, 'enqueue_app_window' => true],
    ]);
});
