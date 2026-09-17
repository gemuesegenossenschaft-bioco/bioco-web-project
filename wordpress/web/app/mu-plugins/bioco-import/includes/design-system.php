<?php
/** Seed through supported Divi REST routes. Never write private Divi options. */
if (!defined('ABSPATH')) exit;

final class Bioco_Divi_Foundation {
    private static function request(string $suffix, array $data): array {
        $routes = array_keys(rest_get_server()->get_routes());
        $matches = array_values(array_filter($routes, static fn($route) => str_ends_with($route, $suffix)));
        if (count($matches) !== 1) throw new RuntimeException('Expected one Divi route: ' . $suffix);
        $request = new WP_REST_Request('POST', $matches[0]);
        $request->set_header('Content-Type', 'application/json');
        $request->set_header('X-ET-Nonce', wp_create_nonce($matches[0] . '--POST'));
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_body(wp_json_encode($data));
        $response = rest_do_request($request);
        if ($response->get_status() >= 300) throw new RuntimeException($suffix . ': ' . wp_json_encode($response->get_data()));
        return (array) $response->get_data();
    }

    public static function seed(bool $apply): array {
        $api = '\\ET\\Builder\\Packages\\GlobalData\\GlobalData';
        if (!class_exists($api)) throw new RuntimeException('Divi 5 must be active.');
        if (!current_user_can('manage_options')) throw new RuntimeException('Use --user=ADMINISTRATOR_LOGIN.');
        // Validate the whole plan before any write: manifest sections, preset
        // definitions (unknown titles throw) and the read-only plan all run
        // first. No route is called while a conflict is open, so a failed run
        // never leaves partial seed data behind; a mid-apply REST failure is
        // repaired by rerunning, which only adds what is still missing and
        // never overwrites.
        $manifest = bioco_divi_design_manifest();
        foreach (['tokens', 'optionGroupPresets', 'elementPresets', 'themeBuilder'] as $section) {
            if (!is_array($manifest[$section] ?? null)) throw new RuntimeException('Invalid design-system manifest section: ' . $section);
        }
        if (!is_array($manifest['themeBuilder']['templates'] ?? null)) throw new RuntimeException('Invalid design-system manifest section: themeBuilder.templates');
        $definitions = self::preset_definitions();
        $colors = $api::get_global_colors();
        $variables = array_map(static fn($items) => (array) $items, $api::get_global_variables());
        $report = [];
        $conflicts = [];
        $missing_colors = [];
        $missing_variables = [];
        foreach (bioco_divi_design_manifest()['tokens'] as $category => $tokens) {
            foreach ($tokens as $token) {
                $id = bioco_divi_token_id($token, $category);
                if ($category === 'colors') {
                    if (isset($colors[$id])) continue;
                    if ($conflict = self::label_conflict($colors, $token['title'], $id)) {
                        $conflicts[] = 'conflict ' . $token['title'] . ' already exists as "' . $conflict . '"; resolve in Divi';
                        continue;
                    }
                    $missing_colors[$id] = ['label' => $token['title'], 'color' => $token['value'], 'status' => 'active', 'usedInPosts' => [], 'lastUpdated' => gmdate('c')];
                } else {
                    $type = bioco_divi_token_type($category);
                    if (isset($variables[$type][$id])) continue;
                    if ($conflict = self::label_conflict($variables[$type] ?? [], $token['title'], $id)) {
                        $conflicts[] = 'conflict ' . $token['title'] . ' already exists as "' . $conflict . '"; resolve in Divi';
                        continue;
                    }
                    $missing_variables[$type][$id] = ['label' => $token['title'], 'value' => $token['value'], 'status' => 'active', 'order' => count($variables[$type] ?? []) + count($missing_variables[$type] ?? [])];
                }
                $report[] = 'would-add ' . $token['title'];
            }
        }
        [$preset_report, $preset_data] = self::presets($definitions);
        [$layout_report, $missing_layouts] = self::layouts();
        $report = array_merge($conflicts, $report, $preset_report, $layout_report);
        if ($conflicts || !$apply) return $report;
        if ($missing_colors) self::request('/global-data/global-colors', ['global_colors' => $colors + $missing_colors]);
        if ($missing_variables) self::request('/global-data/global-variables', ['global_variables' => self::merge_variables($variables, $missing_variables)]);
        self::presets_apply($preset_data);
        self::layouts_apply($missing_layouts);
        // Every planned line is now written; 'would-add ' is 10 characters.
        return array_map(static fn($line) => 'added ' . substr($line, 10), $report);
    }

    private static function merge_variables(array $existing, array $missing): array {
        foreach ($missing as $type => $items) {
            $existing[$type] = $existing[$type] ?? [];
            foreach ($items as $id => $item) $existing[$type][$id] = $item;
        }
        return $existing;
    }

    /** Same-label entry under another ID: a duplicate would silently fork the value. */
    private static function label_conflict(array $entries, string $label, string $id): ?string {
        foreach ($entries as $existingId => $entry) {
            if ($existingId !== $id && ($entry['label'] ?? '') === $label) return (string) $existingId;
        }
        return null;
    }

    private static function variable(string $css): string {
        foreach (bioco_divi_design_manifest()['tokens'] as $category => $tokens) {
            foreach ($tokens as $token) {
                if ($token['cssVar'] === $css) {
                    return '$variable(' . wp_json_encode(['type' => $category === 'colors' ? 'color' : 'content', 'value' => ['name' => bioco_divi_token_id($token, $category), 'settings' => (object) []]]) . ')$';
                }
            }
        }
        throw new RuntimeException('Unknown token ' . $css);
    }

    private static function value(array $value): array {
        return ['desktop' => ['value' => $value]];
    }

    private static function preset_definitions(): array {
        $v = static fn($name) => self::variable($name);
        $body = ['content' => ['decoration' => ['bodyFont' => ['body' => ['font' => self::value([
            'family' => 'var(--wp--preset--font-family--body)', 'size' => $v('--wp--preset--font-size--base'), 'color' => $v('--wp--preset--color--bioco-text'), 'lineHeight' => '1.6em',
        ])]]]]];
        $heading = ['title' => ['decoration' => ['font' => ['font' => self::value([
            'family' => 'var(--wp--preset--font-family--body)', 'size' => $v('--wp--preset--font-size--3xl'), 'color' => $v('--wp--preset--color--bioco-text'), 'weight' => '700', 'lineHeight' => '1.2em',
        ])]]]];
        $spacing = ['module' => ['decoration' => ['spacing' => self::value(['padding' => ['top' => $v('--wp--preset--spacing--60'), 'bottom' => $v('--wp--preset--spacing--60')]])]]];
        $border = ['module' => ['decoration' => ['border' => self::value([
            'radius' => ['topLeft' => $v('--wp--custom--radius--sm'), 'topRight' => $v('--wp--custom--radius--sm'), 'bottomLeft' => $v('--wp--custom--radius--sm'), 'bottomRight' => $v('--wp--custom--radius--sm')],
            'styles' => ['all' => ['width' => '1px', 'style' => 'solid', 'color' => $v('--wp--preset--color--bioco-border')]],
        ])]]];
        $shadow = ['module' => ['decoration' => ['boxShadow' => self::value(['style' => 'preset1', 'horizontal' => '0px', 'vertical' => '4px', 'blur' => '12px', 'spread' => '0px', 'color' => 'rgba(31,42,27,0.12)'])]]];
        $button = static function(bool $secondary) use ($v): array {
            return ['button' => ['decoration' => [
                'button' => self::value(['enable' => 'on', 'icon' => ['enable' => 'off']]),
                'font' => ['font' => self::value(['family' => 'var(--wp--preset--font-family--body)', 'size' => $v('--wp--preset--font-size--base'), 'weight' => '700', 'color' => $v($secondary ? '--wp--preset--color--bioco-green' : '--wp--preset--color--bioco-surface')])],
                'background' => self::value(['color' => $v($secondary ? '--wp--preset--color--bioco-surface' : '--wp--preset--color--bioco-green')]),
                'spacing' => self::value(['padding' => ['top' => $v('--wp--preset--spacing--20'), 'bottom' => $v('--wp--preset--spacing--20'), 'left' => $v('--wp--preset--spacing--40'), 'right' => $v('--wp--preset--spacing--40')]]),
            ]]];
        };
        $primary = $button(false);
        $secondary = $button(true);
        // The manifest owns preset names and module types; this map only adds
        // Divi's payload schema. A renamed or new manifest preset fails the
        // run instead of silently seeding stale names.
        $groupMapping = [
            'BIOCO Body Typography' => ['divi/text', 'divi/font-body', 'content.decoration.bodyFont', $body],
            'BIOCO Heading Typography' => ['divi/heading', 'divi/font', 'title.decoration.font', $heading],
            'BIOCO Section Spacing' => ['divi/section', 'divi/spacing', 'module.decoration.spacing', $spacing],
            'BIOCO Card Border' => ['divi/column', 'divi/border', 'module.decoration.border', $border],
            'BIOCO Card Shadow' => ['divi/column', 'divi/box-shadow', 'module.decoration.boxShadow', $shadow],
            'BIOCO Primary Button' => ['divi/button', 'divi/button', 'button.decoration.button', $primary],
            'BIOCO Secondary Button' => ['divi/button', 'divi/button', 'button.decoration.button', $secondary],
        ];
        $moduleMapping = [
            'BIOCO Standard Section' => ['divi/section', $spacing],
            'BIOCO Content Row' => ['divi/row', ['module' => ['decoration' => ['sizing' => self::value(['width' => '100%', 'maxWidth' => $v('--wp--style--global--content-size')])]]]],
            'BIOCO Display Heading' => ['divi/heading', $heading],
            'BIOCO Primary Button' => ['divi/button', $primary],
            'BIOCO Secondary Button' => ['divi/button', $secondary],
            'BIOCO Surface Card' => ['divi/column', array_replace_recursive($border, $shadow, ['module' => ['decoration' => ['background' => self::value(['color' => $v('--wp--preset--color--bioco-surface')])]]])],
        ];
        $manifest = bioco_divi_design_manifest();
        $definitions = [];
        foreach ($manifest['optionGroupPresets'] as $manifestPreset) {
            $title = $manifestPreset['title'];
            if (!isset($groupMapping[$title])) throw new RuntimeException('No Divi mapping for option-group preset: ' . $title);
            [$module, $group, $attr, $attrs] = $groupMapping[$title];
            $definitions[] = ['type' => 'group', 'name' => $title, 'moduleName' => $module, 'groupName' => $group, 'groupId' => $attr, 'primaryAttrName' => $attr, 'attrs' => $attrs, 'styleAttrs' => $attrs];
        }
        $shared = [
            'BIOCO Standard Section' => ['BIOCO Section Spacing'],
            'BIOCO Display Heading' => ['BIOCO Heading Typography'],
            'BIOCO Primary Button' => ['BIOCO Primary Button'],
            'BIOCO Secondary Button' => ['BIOCO Secondary Button'],
            'BIOCO Surface Card' => ['BIOCO Card Border', 'BIOCO Card Shadow'],
        ];
        foreach ($manifest['elementPresets'] as $manifestPreset) {
            $title = $manifestPreset['title'];
            if (!isset($moduleMapping[$title])) throw new RuntimeException('No Divi mapping for module preset: ' . $title);
            [$module, $attrs] = $moduleMapping[$title];
            $refs = [];
            foreach ($groupMapping as $group_title => [$unused, $group, $attr]) {
                if (in_array($group_title, $shared[$title] ?? [], true)) {
                    $refs[$attr] = ['presetId' => ['bioco-' . substr(hash('sha256', 'group' . $group_title), 0, 16)], 'groupName' => $group];
                }
            }
            if ($refs) {
                // Group presets own these styles; do not duplicate them in the
                // module preset, where they would override subsequent edits.
                $attrs = $title === 'BIOCO Surface Card' ? ['module' => ['decoration' => ['background' => self::value(['color' => $v('--wp--preset--color--bioco-surface')])]]] : [];
            }
            $definition = ['type' => 'module', 'name' => $title, 'moduleName' => $module];
            if ($attrs) $definition += ['attrs' => $attrs, 'styleAttrs' => $attrs];
            if ($refs) $definition['groupPresets'] = $refs;
            $definitions[] = $definition;
        }
        return $definitions;
    }

    /** Read-only preset plan: [report lines, preset data with planned additions]. */
    private static function presets(array $definitions): array {
        $data = \ET\Builder\Packages\GlobalData\GlobalPreset::get_data();
        $report = [];
        foreach ($definitions as $preset) {
            $type = $preset['type'];
            $key = $preset[$type === 'module' ? 'moduleName' : 'groupName'];
            $id = 'bioco-' . substr(hash('sha256', $type . $preset['name']), 0, 16);
            if (isset($data[$type][$key]['items'][$id])) continue;
            $preset += ['id' => $id, 'created' => time(), 'updated' => time(), 'version' => ET_BUILDER_VERSION, 'priority' => 10];
            if (!isset($data[$type][$key])) $data[$type][$key] = ['default' => '', 'items' => []];
            $data[$type][$key]['items'][$id] = $preset;
            $report[] = 'would-add ' . $type . ' preset: ' . $preset['name'];
        }
        return [$report, $data];
    }

    private static function presets_apply(array $data): void {
        $payload = [];
        foreach ($data as $type => $groups) {
            if (!in_array($type, ['module', 'group'], true)) continue;
            $payload[$type] = [];
            foreach ($groups as $group) $payload[$type][] = ['default' => $group['default'], 'items' => array_values($group['items'])];
        }
        self::request('/global-data/global-preset/sync', ['presets' => $payload]);
    }

    private static function layout_content(string $slot): string {
        $attrs = ['module' => ['advanced' => ['htmlAttributes' => self::value(['class' => 'bioco-global-layout bioco-global-' . $slot])]]];
        if ($slot === 'body') {
            $leaf = ['blockName' => 'divi/post-content', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => ["\n"]];
        } else {
            $leaf = bioco_import_divi_block('divi/text', ['content' => ['innerContent' => ['desktop' => ['value' => '[bioco_global_' . $slot . ']']]]]);
        }
        $column = bioco_import_divi_block('divi/column', ['module' => ['advanced' => ['type' => ['desktop' => ['value' => '4_4']]]]], [$leaf]);
        $row = bioco_import_divi_block('divi/row', ['module' => ['advanced' => ['columnStructure' => ['desktop' => ['value' => '4_4']]]]], [$column]);
        return serialize_blocks([bioco_import_divi_block('divi/section', $attrs, [$row])]);
    }

    /** Read-only layout plan: [report lines, missing slots]. */
    private static function layouts(): array {
        $state = self::request('/outside-vb/theme-builder/list-templates', ['live' => true]);
        $templates = $state['templates'] ?? [];
        $default = null;
        foreach ($templates as $template) {
            if (!empty($template['default'])) {
                if ($default) throw new RuntimeException('Multiple default Theme Builder templates; resolve in Divi first.');
                $default = $template;
            }
        }
        $report = [];
        $missing = [];
        foreach (bioco_divi_design_manifest()['themeBuilder']['templates'] as $definition) {
            $slot = $definition['slot'];
            if (!empty($default['layouts'][$slot]['id'])) continue;
            $missing[$slot] = $definition['title'];
            $report[] = 'would-add ' . $definition['title'];
        }
        return [$report, ['missing' => $missing, 'default' => $default]];
    }

    private static function layouts_apply(array $plan): void {
        $missing = $plan['missing'];
        if (!$missing) return;
        $default = $plan['default'];
        if (!$default) {
            $created = self::request('/outside-vb/theme-builder/create-template', ['live' => true, 'title' => 'BIOCO Default Website']);
            $default = $created['template'];
        }
        $layouts = $default['layouts'] ?? [];
        foreach ($missing as $slot => $title) {
            $type = 'et_' . $slot . '_layout';
            $slug = 'bioco-global-' . $slot;
            $post = get_page_by_path($slug, OBJECT, $type);
            if (!$post) {
                $id = wp_insert_post(['post_type' => $type, 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title], true);
                if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
                $post = get_post($id);
            }
            // A failed previous attempt may have created an empty layout. Never
            // replace a populated layout, including edits made in Theme Builder.
            if (trim($post->post_content) === '') {
                self::request('/outside-vb/posts/set-layout', ['post_id' => $post->ID, 'layout_content' => self::layout_content($slot)]);
            }
            $layouts[$slot] = ['id' => $post->ID, 'enabled' => true];
        }
        self::request('/outside-vb/theme-builder/update-template', ['live' => true, 'template_id' => $default['id'], 'template' => ['default' => true, 'enabled' => true, 'layouts' => $layouts]]);
    }

}
