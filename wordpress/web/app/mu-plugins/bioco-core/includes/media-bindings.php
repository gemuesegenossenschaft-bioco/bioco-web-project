<?php
/** Opt-in Media Library metadata for ordinary native Divi Image modules. */
if (!defined('ABSPATH')) exit;

function bioco_media_binding_fields(array $options): array {
    foreach (['alt' => __('Alternativtext', 'bioco'), 'title' => __('Titel', 'bioco')] as $field => $label) {
        $name = 'bioco_media_' . $field;
        $options[$name] = ['id' => $name, 'label' => 'Mediathek: ' . $label,
            'type' => 'text', 'custom' => false, 'group' => __('Mediathek', 'bioco'),
            'fields' => ['image' => ['label' => __('Bild', 'bioco'), 'type' => 'upload']]];
    }
    return $options;
}

function bioco_media_binding_value($value, array $context) {
    $name = $context['name'] ?? '';
    if (!in_array($name, ['bioco_media_alt', 'bioco_media_title'], true)) return $value;
    $setting = $context['settings']['image'] ?? '';
    $image = is_string($setting) ? json_decode($setting, true) : null;
    $rawId = is_array($image) ? ($image['id'] ?? null) : null;
    if ((!is_int($rawId) && !is_string($rawId)) || !preg_match('/^[1-9][0-9]*$/D', (string) $rawId)) return '';
    $id = (int) $rawId;
    $post = get_post($id);
    if (!$post || $post->post_type !== 'attachment' || !wp_attachment_is_image($id)) return '';
    if ($post->post_status !== 'inherit' && !current_user_can('read_post', $id)) return '';
    if ($post->post_parent && get_post_status($post->post_parent) !== 'publish'
        && !current_user_can('read_post', $post->post_parent)) return '';
    if ($post->post_parent && post_password_required($post->post_parent)
        && !current_user_can('edit_post', $post->post_parent)) return '';
    // The native image renderer escapes attribute values. Keep the underlying
    // text intact, including an intentional empty alt and ordinary ampersands.
    return $name === 'bioco_media_alt' ? (string) get_post_meta($id, '_wp_attachment_image_alt', true)
        : (string) $post->post_title;
}

function bioco_media_binding_token(string $field, array $image): string {
    return '$variable(' . wp_json_encode(['type' => 'content', 'value' => [
        'name' => 'bioco_media_' . $field, 'settings' => ['image' => wp_json_encode($image, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
    ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ')$';
}

/** Project runtime attrs only; saved literals remain recoverable when switched off. */
function bioco_media_binding_attrs(array $attrs, array $context): array {
    if (($context['name'] ?? '') !== 'divi/image') return $attrs;
    $sources = $attrs['image']['advanced']['biocoMetadata']['desktop']['value'] ?? [];
    $selected = [];
    foreach (['alt' => 'decorative', 'title' => 'none'] as $field => $emptyMode) {
        $mode = $sources[$field . 'Source'] ?? 'module';
        if (in_array($mode, ['library', $emptyMode], true)) $selected[$field] = $mode;
    }
    if (!$selected) return $attrs;
    $content = $attrs['image']['innerContent'] ?? [];
    if (!is_array($content)) return $attrs;
    $base = $content['desktop']['value'] ?? [];
    if (!is_array($base)) return $attrs;
    foreach ($content as $device => &$states) {
        if (!is_array($states)) continue;
        $normal = array_replace($base, is_array($states['value'] ?? null) ? $states['value'] : []);
        foreach ($states as $state => &$value) {
            if (!is_array($value)) continue;
            // Let native breakpoint/state inheritance carry metadata alongside
            // the image when this branch changes spacing or other fields only.
            if (!($device === 'desktop' && $state === 'value') && !isset($value['src']) && !isset($value['id'])) {
                foreach ($selected as $field => $mode) unset($value[$field === 'title' ? 'titleText' : 'alt']);
                continue;
            }
            $image = array_replace($normal, $value);
            // A URL-only replacement must not reuse a stale attachment identity.
            if (isset($value['src']) && $value['src'] !== ($normal['src'] ?? null) && !isset($value['id'])) unset($image['id']);
            if ($state === 'value' && $device !== 'desktop' && isset($value['src'])
                && $value['src'] !== ($base['src'] ?? null) && !isset($value['id'])) unset($image['id']);
            $id = $image['id'] ?? 0;
            $id = (is_int($id) || is_string($id)) && preg_match('/^[1-9][0-9]*$/D', (string) $id) ? (string) $id : '0';
            foreach ($selected as $field => $mode) {
                $value[$field === 'title' ? 'titleText' : 'alt'] = bioco_media_binding_token($field,
                    ['id' => $mode === 'library' ? $id : '0', 'src' => is_string($image['src'] ?? null) ? $image['src'] : '']);
            }
        }
        unset($value);
    }
    unset($states);
    $attrs['image']['innerContent'] = $content;
    // Native pickers materialize both locations. Remove only the selected
    // image's opted-in metadata rows from the runtime projection.
    if (is_array($attrs['module']['decoration']['attributes'] ?? null)) {
        $attributes =& $attrs['module']['decoration']['attributes'];
        $remove = static fn($row) => !is_array($row) || ($row['targetElement'] ?? 'main') !== 'image'
            || !is_string($row['name'] ?? null) || !isset($selected[$row['name']]);
        if (is_array($attributes['attributes'] ?? null)) $attributes['attributes'] = array_values(array_filter($attributes['attributes'], $remove));
        foreach ($attributes as $device => &$states) {
            if ($device === 'attributes' || !is_array($states)) continue;
            foreach ($states as &$value) if (is_array($value['attributes'] ?? null)) $value['attributes'] = array_values(array_filter($value['attributes'], $remove));
            unset($value);
        }
        unset($states);
        unset($attributes);
    }
    return $attrs;
}

function bioco_media_binding_enqueue(): void {
    $conditions = 'ET\\Builder\\Framework\\Utility\\Conditions';
    $roles = 'ET\\Builder\\Framework\\UserRole\\UserRole';
    if (!class_exists($conditions) || !$conditions::is_vb_app_window() || !is_user_logged_in()
        || !class_exists($roles) || !$roles::can_current_user_use_visual_builder()) return;
    if (!wp_script_is('divi-module-library', 'registered') || wp_script_is('divi-module-library', 'done')) return;
    $source = file_get_contents(__DIR__ . '/../assets/divi-media-bindings.js');
    if ($source !== false) wp_add_inline_script('divi-module-library', $source, 'before');
}

add_filter('divi_module_dynamic_content_options', 'bioco_media_binding_fields', 20);
add_filter('divi_module_dynamic_content_resolved_value', 'bioco_media_binding_value', 20, 2);
add_filter('divi_module_library_register_module_attrs', 'bioco_media_binding_attrs', 20, 2);
add_action('divi_visual_builder_assets_after_enqueue_app_window_scripts', 'bioco_media_binding_enqueue');
