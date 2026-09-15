<?php
/**
 * Native Divi 5 module registration for the bioco dynamic components (#146).
 *
 * Registers `bioco-divi/<slug>` modules through Divi's ModuleRegistration so
 * editors insert and edit the real components inside the Visual Builder, while
 * public/preview output keeps flowing through the existing renderer seam in
 * includes/dynamic-sections.php (bioco_render_dynamic_component). This file is
 * the shared thin adapter: it owns
 *   - the native block name -> component registry (bioco_native_components()),
 *   - the typed extraction from Divi resolved attrs into the flat $values
 *     contract the block render templates already consume,
 *   - the inert render boundary for editor/REST contexts (VB canvas, generic
 *     block render routes, this plugin's preview endpoint),
 *   - one authenticated read-only preview REST endpoint.
 *
 * Two name domains are kept deliberately distinct:
 *   - Native block names (`bioco-divi/contact-form`): registry/module keys only.
 *   - Component keys (`contact_form`): the request/validation/render domain,
 *     identical to the ACF seam's keys in bioco_dynamic_components().
 *
 * The ACF `bioco/<slug>` blocks, the legacy marker reader and all public form
 * behavior are untouched; native modules are additive.
 */

if (!defined('ABSPATH')) exit;

/**
 * Native block names mapped to the existing dynamic component keys. Distinct
 * namespace from the ACF `bioco/*` blocks; one entry per module.
 */
function bioco_native_schema(): array {
    static $schema;
    if ($schema === null) {
        $schema = json_decode(file_get_contents(__DIR__ . '/../native-modules/fields.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    return $schema;
}

function bioco_native_components(): array {
    $components = [];
    foreach (array_keys(bioco_native_schema()) as $component) {
        $components['bioco-divi/' . str_replace('_', '-', $component)] = $component;
    }
    return $components;
}

function bioco_native_module_slugs(): array {
    return array_map(static fn($component) => str_replace('_', '-', $component), bioco_native_components());
}

function bioco_native_field_map(string $component): array {
    return bioco_native_schema()[$component] ?? [];
}

/**
 * Editor render mode for the current request. This is decided by the server
 * environment only — never by client-provided flags — so the native block
 * callback stays inert through every editor render path, including Divi's
 * generic /module-render REST route (which runs callbacks before hiding
 * modules) and wp-admin canvases.
 */
function bioco_native_render_mode(): string {
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return 'inert';
    }
    if (wp_doing_ajax()) {
        return 'inert';
    }
    if (is_admin()) {
        return 'inert';
    }
    if (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled()) {
        return 'inert';
    }

    return 'public';
}

/**
 * True while the dynamic component seam renders in an editor/preview context.
 * Used by bioco-forms to suppress form localization (live endpoints, captcha
 * config) outside the public render path.
 */
function bioco_dynamic_render_is_inert(): bool {
    return !empty($GLOBALS['bioco_dynamic_render_inert']);
}

/**
 * Register all native modules through Divi's ModuleRegistration. No-ops when
 * the Divi 5 SDK is unavailable (non-Divi themes keep the legacy seam only).
 */
function bioco_native_register_modules() {
    if (!class_exists('ET\Builder\Packages\ModuleLibrary\ModuleRegistration')) {
        return;
    }

    foreach (bioco_native_module_slugs() as $block_name => $slug) {
        $metadata_folder = __DIR__ . '/../native-modules/' . $slug;
        if (!is_dir($metadata_folder)) {
            continue;
        }

        ET\Builder\Packages\ModuleLibrary\ModuleRegistration::register_module(
            $metadata_folder,
            [
                'render_callback' => 'bioco_native_render_block',
            ]
        );
    }
}

/**
 * Extract wrapper-level anchor/className from Divi's IdClasses attributes
 * (module.advanced.htmlAttributes.desktop.value.{id,class}).
 */
function bioco_native_extract_wrapper(array $attrs): array {
    $html_attrs = $attrs['module']['advanced']['htmlAttributes']['desktop']['value'] ?? [];
    foreach ($attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [] as $attribute) {
        if (in_array($attribute['name'] ?? '', ['id', 'class'], true)) {
            $html_attrs[$attribute['name']] = $attribute['value'] ?? '';
        }
    }

    return [
        'anchor' => is_array($html_attrs) && isset($html_attrs['id']) ? (string) $html_attrs['id'] : '',
        'className' => is_array($html_attrs) && isset($html_attrs['class']) ? (string) $html_attrs['class'] : '',
    ];
}

function bioco_native_attr_value(array $attrs, string $field) {
    $attr = $attrs[$field] ?? null;
    if (is_array($attr)) {
        return $attr['innerContent']['desktop']['value'] ?? null;
    }
    return $attr;
}

/**
 * Convert one Divi-stored scalar into the typed editorial value. Returns null
 * only for values that cannot be represented (null sentinels, wrong types,
 * non-numeric numbers) — preserving false/zero/'' exactly.
 */
function bioco_native_convert_value($value, array $spec) {
    if ($value === null) {
        return null;
    }

    if (isset($spec['choices']) && ($spec['type'] ?? '') !== 'choices' && !is_scalar($value)) return null;
    if (isset($spec['choices']) && ($spec['type'] ?? '') !== 'choices' && $value !== '' && !array_key_exists((string) $value, $spec['choices'])) return null;
    switch ($spec['type'] ?? 'text') {
        case 'choices':
            if (!is_array($value) || !array_is_list($value)) return null;
            foreach ($value as $choice) {
                if ((!is_string($choice) && !is_int($choice)) || !array_key_exists((string) $choice, $spec['choices'] ?? [])) return null;
            }
            return array_map('strval', $value);
        case 'text':
        case 'richtext':
        case 'textarea':
            if (is_array($value)) {
                return null;
            }
            return (string) $value;
        case 'toggle':
            if ($value === true || $value === 'on') return true;
            if ($value === false || $value === 'off') return false;
            return null;
        case 'number':
            if (is_string($value) || is_int($value) || is_float($value)) {
                return is_numeric((string) $value) ? (float) $value + 0 : null;
            }
            return null;
        case 'int':
            if (is_int($value)) return $value;
            if (is_string($value) && preg_match('/^-?\d+$/', $value)) return (int) $value;
            return null;
        default:
            return null;
    }
}

/**
 * Extract the flat editorial $values array for a component from Divi's
 * resolved attribute tree. Pure extraction: values within the schema are
 * preserved exactly (no truncation, no dropping, order intact); fields the
 * editor never set are omitted so render templates apply their own defaults.
 * Malformed rows fail with a WP_Error so invalid saved content fails closed
 * instead of losing individual editorial values.
 */
function bioco_native_extract_values(string $component, array $attrs): array|WP_Error {
    $values = [];
    foreach (bioco_native_field_map($component) as $field => $spec) {
        if (($spec['type'] ?? '') === 'rows') {
            $rows = bioco_native_extract_rows($attrs, $field, $spec);
            if ($rows instanceof WP_Error) {
                return $rows;
            }
            if ($rows !== null) {
                $values[$field] = $rows;
            }
            continue;
        }

        if (!array_key_exists($field, $attrs)) {
            continue;
        }
        $converted = bioco_native_convert_value(bioco_native_attr_value($attrs, $field), $spec);
        if ($converted === null) {
            return new WP_Error('bioco_native_invalid_value', "Invalid saved value for field: {$field}", ['status' => 400]);
        }
        $values[$field] = $converted;
    }

    return $values;
}

/**
 * Extract a rows attribute (array of typed rows) from Divi's resolved attrs.
 * Every row runs through the shared row validator; a malformed row fails the
 * whole attribute — never a silently dropped row or truncated list. An absent
 * field yields null so render templates fall back to their own defaults; an
 * explicitly saved empty list stays an empty list.
 */
function bioco_native_extract_rows(array $attrs, string $field, array $spec): array|WP_Error|null {
    if (!array_key_exists($field, $attrs) || !is_array($attrs[$field])) {
        return null;
    }

    $raw = bioco_native_attr_value($attrs, $field);
    if ($raw === null) {
        return null;
    }
    if (!is_array($raw)) {
        return new WP_Error('bioco_native_invalid_value', "Field must be a list: {$field}", ['status' => 400]);
    }

    $rows = [];
    foreach ($raw as $row) {
        $normalized = bioco_native_validate_row($row, $spec['row'] ?? []);
        if ($normalized instanceof WP_Error) {
            return $normalized;
        }
        $rows[] = $normalized;
    }

    return $rows;
}

/**
 * Shared typed per-component validation used by BOTH the preview endpoint and
 * the saved native public render, so the two paths can never diverge. Valid
 * values pass through normalized; every violation is an error object — no
 * silent coercion, no dropping or truncation of legitimate editorial values.
 * Preview translates errors into 400s; rendering fails closed ('') instead.
 */
function bioco_native_validate_values(string $component, array $values): array|WP_Error {
    if (!array_key_exists($component, bioco_dynamic_components())) {
        return new WP_Error('bioco_native_unknown_component', 'Unknown bioco dynamic component.', ['status' => 400]);
    }

    $field_map = bioco_native_field_map($component);
    foreach ($values as $field => $value) {
        if (!isset($field_map[$field])) {
            return new WP_Error('bioco_native_unknown_field', "Unknown field: {$field}", ['status' => 400]);
        }
    }

    $normalized = [];
    foreach ($field_map as $field => $spec) {
        if (!array_key_exists($field, $values)) {
            continue;
        }
        $value = $values[$field];

        if (($spec['type'] ?? '') === 'rows') {
            if (!is_array($value)) {
                return new WP_Error('bioco_native_invalid_value', "Field must be a list: {$field}", ['status' => 400]);
            }
            $rows = [];
            foreach ($value as $row) {
                $row_result = bioco_native_validate_row($row, $spec['row'] ?? []);
                if ($row_result instanceof WP_Error) {
                    return $row_result;
                }
                $rows[] = $row_result;
            }
            $normalized[$field] = $rows;
            continue;
        }

        $converted = bioco_native_convert_value($value, $spec);
        if ($converted === null) {
            return new WP_Error('bioco_native_invalid_value', "Invalid value for field: {$field}", ['status' => 400]);
        }
        $normalized[$field] = $converted;
    }

    return bioco_native_validate_component_rules($component, $normalized);
}

function bioco_native_validate_row($row, array $row_spec) {
    if (!is_array($row)) {
        return new WP_Error('bioco_native_invalid_value', 'Row must be an object.', ['status' => 400]);
    }

    $normalized = [];
    foreach ($row_spec as $row_field => $row_spec_item) {
        if (!array_key_exists($row_field, $row)) {
            if (!empty($row_spec_item['required'])) {
                return new WP_Error('bioco_native_invalid_value', "Missing row field: {$row_field}", ['status' => 400]);
            }
            continue;
        }
        $converted = bioco_native_convert_value($row[$row_field], $row_spec_item);
        if ($converted === null) {
            return new WP_Error('bioco_native_invalid_value', "Invalid row field: {$row_field}", ['status' => 400]);
        }
        $normalized[$row_field] = $converted;
    }

    return $normalized;
}

/**
 * Cross-field invariants per component, mirroring the existing ACF gallery
 * validation (bioco-core.php): item categories and filter keys stay in their
 * enum sets, filter keys are unique, and exactly one "all" row exists.
 */
function bioco_native_validate_component_rules(string $component, array $values) {
    if ($component !== 'gallery') {
        return $values;
    }

    $allowed_categories = ['koerbe', 'feld', 'portraits'];
    foreach ($values['items'] ?? [] as $row) {
        if (!in_array($row['category'] ?? null, $allowed_categories, true)) {
            return new WP_Error('bioco_native_invalid_value', 'Invalid item category.', ['status' => 400]);
        }
        // Attachment usability is the renderer's resolution concern: an
        // attachment id that resolves to no image is skipped by the existing
        // renderer (a row picked but not yet completed behaves the same as an
        // attachment that was deleted from the media library).
        if (!is_int($row['image'] ?? null)) {
            return new WP_Error('bioco_native_invalid_value', 'Item image must be an attachment ID (integer).', ['status' => 400]);
        }
    }

    if (array_key_exists('filters', $values)) {
        // An empty filter list is legitimate (the renderer shows no filter
        // buttons, matching the ACF empty-repeater behavior); the invariants
        // apply once filters are configured.
        if (empty($values['filters'])) {
            return $values;
        }

        $allowed_keys = ['all', 'koerbe', 'feld', 'portraits'];
        $seen = [];
        $all_count = 0;
        foreach ($values['filters'] as $row) {
            if (!in_array($row['key'], $allowed_keys, true)) {
                return new WP_Error('bioco_native_invalid_value', 'Invalid filter key.', ['status' => 400]);
            }
            if (isset($seen[$row['key']])) {
                return new WP_Error('bioco_native_invalid_value', 'Duplicate filter key.', ['status' => 400]);
            }
            $seen[$row['key']] = true;
            if ($row['key'] === 'all') $all_count++;
        }
        if ($all_count !== 1) {
            return new WP_Error('bioco_native_invalid_value', 'Exactly one "all" filter is required.', ['status' => 400]);
        }
    }

    return $values;
}

/**
 * Render callback wired into ModuleRegistration::register_module. Called with
 * (attrs, content, WP_Block, ModuleElements, defaultPrintedStyleAttrs) after
 * Divi merged defaults/presets. Editorial values pass through the same shared
 * validation as the preview endpoint; valid values render through the project
 * seam in the server-decided mode, invalid saved content fails closed instead
 * of silently losing individual editorial fields. The Divi wrapper/styles
 * stay in the Divi pipeline.
 */
function bioco_native_render_block($attrs, $content, $block, $elements, $default_printed_style_attrs = []) {
    $name = is_object($block) && isset($block->block_type->name) ? $block->block_type->name : '';
    $components = bioco_native_components();
    if (!isset($components[$name])) {
        return '';
    }
    $component = $components[$name];

    $extracted = bioco_native_extract_values($component, is_array($attrs) ? $attrs : []);
    if ($extracted instanceof WP_Error) {
        return '';
    }
    $validated = bioco_native_validate_values($component, $extracted);
    if ($validated instanceof WP_Error) {
        return '';
    }

    $values = array_merge($validated, bioco_native_extract_wrapper(is_array($attrs) ? $attrs : []));

    $html = bioco_render_dynamic_component($component, $values, ['mode' => bioco_native_render_mode()]);
    if (!$elements || !class_exists('ET\Builder\Packages\Module\Module')) return $html;
    if ($values['anchor'] !== '') {
        $tags = new WP_HTML_Tag_Processor($html);
        if ($tags->next_tag()) $tags->remove_attribute('id');
        $html = $tags->get_updated_html();
    }
    return ET\Builder\Packages\Module\Module::render([
        'name' => $name,
        'attrs' => $attrs,
        'elements' => $elements,
        'id' => $block->parsed_block['id'],
        'orderIndex' => $block->parsed_block['orderIndex'],
        'storeInstance' => $block->parsed_block['storeInstance'],
        'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
        'moduleCategory' => $block->block_type->category,
        'stylesComponent' => 'bioco_native_module_styles',
        'scriptDataComponent' => static function ($args) { $args['elements']->script_data(['attrName' => 'module']); },
        'children' => $elements->style_components(['attrName' => 'module']) . $html,
    ]);
}

/**
 * One project preview endpoint: named component key (contact_form/gallery) +
 * editable target post + validated typed values -> rendered HTML. Inert by
 * construction: the seam forces read-only mode server-side (never from a
 * client flag), the render never runs form/captcha/DOI side effects, and no
 * editorial input is persisted or cached here. Request size is bounded by an
 * explicit documented limit instead of silent truncation.
 */
function bioco_native_register_rest_route() {
    register_rest_route('bioco/v1', '/native-preview', [
        'methods' => 'POST',
        'permission_callback' => 'bioco_native_preview_permission',
        'callback' => 'bioco_native_preview_render',
        'args' => [
            'component' => ['type' => 'string', 'required' => true],
            'post_id' => ['type' => 'integer', 'required' => true],
            'values' => ['type' => 'object', 'required' => false, 'default' => []],
        ],
    ]);
}

/**
 * WP REST cookie auth verifies X-WP-Nonce automatically; a nonce alone never
 * replaces the per-target edit_post capability, so both are checked here
 * against an existing target post. Nonexistent and uneditable targets are
 * rejected before any rendering.
 */
function bioco_native_preview_permission(WP_REST_Request $request) {
    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '' || wp_verify_nonce($nonce, 'wp_rest') === false) {
        return false;
    }

    $post_id = (int) $request->get_param('post_id');
    if ($post_id <= 0 || get_post($post_id) === null) {
        return false;
    }

    return current_user_can('edit_post', $post_id);
}

function bioco_native_preview_render(WP_REST_Request $request) {
    $component = (string) $request->get_param('component');
    if (!array_key_exists($component, bioco_dynamic_components())) {
        return new WP_Error('bioco_native_unknown_component', 'Unknown bioco dynamic component.', ['status' => 400]);
    }

    $post_id = (int) $request->get_param('post_id');
    if ($post_id <= 0 || get_post($post_id) === null) {
        return new WP_Error('bioco_native_unknown_post', 'Unknown preview target post.', ['status' => 400]);
    }

    $validated = bioco_native_validate_values($component, (array) $request->get_param('values'));
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    // setup_postdata() establishes the loop globals but does not assign
    // global $post; set the actual target post and restore the caller's exact
    // prior state (post + loop globals) in finally, including on throw.
    $post = get_post($post_id);
    $loop_globals = ['post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages'];
    $saved_globals = [];
    foreach ($loop_globals as $key) {
        if (array_key_exists($key, $GLOBALS)) {
            $saved_globals[$key] = $GLOBALS[$key];
        }
    }
    $GLOBALS['post'] = $post;
    setup_postdata($post);

    try {
        $html = bioco_render_dynamic_component($component, $validated, ['mode' => 'inert']);
        return ['data' => $html];
    } finally {
        wp_reset_postdata();
        foreach ($loop_globals as $key) {
            if (array_key_exists($key, $saved_globals)) {
                $GLOBALS[$key] = $saved_globals[$key];
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }
}

/**
 * Editor bundle data: one entry per native module carrying its module.json
 * metadata (single source of truth on disk) and its request component key.
 * Consumed by native-modules/editor.js to register the modules in Divi's
 * module library store.
 */
function bioco_native_editor_modules(): array {
    $modules = [];
    foreach (bioco_native_module_slugs() as $slug) {
        $metadata_file = __DIR__ . '/../native-modules/' . $slug . '/module.json';
        if (!file_exists($metadata_file)) {
            continue;
        }
        $metadata = json_decode((string) file_get_contents($metadata_file), true);
        if (!is_array($metadata) || empty($metadata['name'])) {
            continue;
        }
        $modules[] = [
            'slug' => $slug,
            'component' => str_replace('-', '_', $slug),
            'fields' => bioco_native_field_map(str_replace('-', '_', $slug)),
            'metadata' => $metadata,
        ];
    }

    return $modules;
}

/**
 * Register the editor bundle in the Visual Builder app window via Divi's
 * PackageBuildManager. Runs on the before-enqueue hook inside the VB request
 * only, so the public site never loads editor assets. The metadata travels
 * through the localized script data (biocoNativeModulesData).
 */
function bioco_native_register_editor_assets() {
    if (!class_exists('ET\Builder\VisualBuilder\Assets\PackageBuildManager')) {
        return;
    }

    $script_path = __DIR__ . '/../native-modules/editor.js';
    $version = (string) (file_exists($script_path) ? filemtime($script_path) : '1');

    ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build([
        'name' => 'bioco-native-modules',
        'version' => $version,
        'script' => [
            'src' => content_url('mu-plugins/bioco-core/native-modules/editor.js'),
            'deps' => ['divi-vendor-react', 'divi-vendor-react-dom'],
            'args' => ['in_footer' => true],
            'data_top_window' => [],
            'data_app_window' => [
                'modules' => bioco_native_editor_modules(),
                'messageEditorUrl' => admin_url('admin.php?page=bioco-form-messages'),
            ],
            'enqueue_top_window' => false,
            'enqueue_app_window' => true,
        ],
        'style' => [
            'src' => '',
            'enqueue_top_window' => false,
            'enqueue_app_window' => false,
        ],
    ]);
}

/**
 * Divi strips empty arrays from saved attributes by default, which would
 * revert intentionally emptied rows lists (gallery items/filters) back to
 * module defaults on reload. Preserve empty arrays exactly at our rows
 * attributes' innerContent value paths (all breakpoints).
 */
function bioco_native_preserve_empty_array_attrs($pre_filter, $value, $key, $path) {
    $is_empty_array = is_array($value) && empty($value);
    if (!$is_empty_array) {
        return $pre_filter;
    }

    foreach (bioco_native_components() as $component) {
        foreach (bioco_native_field_map($component) as $field => $spec) {
            if (($spec['type'] ?? '') !== 'rows') {
                continue;
            }
            if (isset($path[0]) && $path[0] === $field && (string) $key === 'value') {
                return true;
            }
        }
    }

    return $pre_filter;
}

if (function_exists('add_filter')) {
    add_action('init', 'bioco_native_register_modules', 20);
    add_action('rest_api_init', 'bioco_native_register_rest_route');
    add_action('divi_visual_builder_assets_before_enqueue_scripts', 'bioco_native_register_editor_assets');
    add_filter('divi_module_utils_remove_empty_array_attributes_pre_filter', 'bioco_native_preserve_empty_array_attrs', 10, 4);
}

function bioco_native_module_styles(array $args): void {
    ET\Builder\FrontEnd\Module\Style::add([
        'id' => $args['id'], 'name' => $args['name'], 'orderIndex' => $args['orderIndex'],
        'storeInstance' => $args['storeInstance'],
        'styles' => [$args['elements']->style(['attrName' => 'module'])],
    ]);
}
