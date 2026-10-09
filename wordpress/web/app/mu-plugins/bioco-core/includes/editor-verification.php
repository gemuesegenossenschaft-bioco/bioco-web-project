<?php
/** Keep temporary editor checks away from the site's assigned global layouts. */
if (!defined('ABSPATH')) exit;

function bioco_editor_verification_layouts(array $layouts): array {
    $id = (int) get_queried_object_id();
    $run = get_post_meta($id, '_bioco_editor_verification', true);
    if ($id && is_string($run) && preg_match('/^[a-z0-9][a-z0-9-]{7,79}$/D', $run)
        && in_array(get_post_status($id), ['draft', 'auto-draft'], true)
        && current_user_can('edit_post', $id)) {
        // A standalone header/footer copy must retain its own editing context.
        return array_filter($layouts, static fn($layout) => is_array($layout)
            && (int) ($layout['id'] ?? 0) === $id);
    }
    return $layouts;
}

add_filter('et_theme_builder_template_layouts', 'bioco_editor_verification_layouts', 100);
