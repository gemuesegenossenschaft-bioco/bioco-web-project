<?php
/** Native, independently arrangeable form controls. Transport stays in bioco-forms. */
if (!defined('ABSPATH')) exit;

function bioco_form_contracts(): array {
    return [
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
    foreach (['form_type', 'field', 'label', 'help', 'placeholder', 'pending_label', 'success_message', 'fallback_error', 'captcha_error', 'transport_error'] as $key) {
        $value = $attrs[$key]['innerContent']['desktop']['value'] ?? null;
        if (is_string($value)) $values[$key] = $value;
    }
    return $values;
}

/** This is also the fixture seam. Inert mode never contains endpoints or CAPTCHA configuration. */
function bioco_form_render_part(string $kind, array $values, string $mode = 'inert'): string {
    if ($mode === 'public') wp_enqueue_style('bioco-native-form-composition');
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
            wp_enqueue_script('bioco-native-form-composition');
        }
        $html .= '<form id="' . esc_attr($id) . '" class="bioco-form" data-form="' . esc_attr($type) . '" data-native-controller data-bioco-instance-config="' . esc_attr(wp_json_encode($config)) . '" method="post" novalidate>';
        if ($mode === 'public') $html .= '<div data-form-captcha></div>';
        return $html . '</form></div>';
    }
    if ($kind === 'form-action') {
        if (($values['label'] ?? '') === '') return '';
        return '<button type="submit" class="bioco-form-control bioco-form-action" data-native-form-control data-submit-label="' . esc_attr($values['label'] ?? '') . '" data-submitting-label="' . esc_attr($values['pending_label'] ?? '') . '" disabled>' . esc_html($values['label'] ?? '') . '</button>';
    }
    if ($kind !== 'form-field') return '';
    $identity = $values['field'] ?? '';
    $parts = explode('.', $identity, 2);
    $spec = bioco_form_contracts()[$parts[0] ?? ''][$parts[1] ?? ''] ?? null;
    $label = $values['label'] ?? '';
    if ($spec === null || $label === '') return '';
    $id = wp_unique_id('bioco-native-field-');
    $help = $values['help'] ?? '';
    $attributes = ' class="bioco-form-control" id="' . esc_attr($id) . '" name="' . esc_attr($parts[1]) . '" data-native-form-control data-bioco-field="' . esc_attr($identity) . '" disabled';
    if ($spec['required']) $attributes .= ' required';
    if ($help !== '') $attributes .= ' aria-describedby="' . esc_attr($id . '-help') . '"';
    if (($values['placeholder'] ?? '') !== '') $attributes .= ' placeholder="' . esc_attr($values['placeholder']) . '"';
    $html = '<div class="bioco-form-field"><label class="bioco-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
    $html .= $spec['type'] === 'textarea' ? '<textarea' . $attributes . ' rows="6"></textarea>' : '<input type="' . esc_attr($spec['type']) . '"' . $attributes . '>';
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
    foreach (['module', 'control', 'label', 'help'] as $attr) $styles[] = $args['elements']->style(['attrName' => $attr]);
    ET\Builder\FrontEnd\Module\Style::add([
        'id' => $args['id'], 'name' => $args['name'], 'orderIndex' => $args['orderIndex'],
        'storeInstance' => $args['storeInstance'], 'styles' => $styles,
    ]);
}

function bioco_form_metadata(): array {
    $modules = [];
    foreach (['form-controller', 'form-field', 'form-action'] as $kind) {
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
