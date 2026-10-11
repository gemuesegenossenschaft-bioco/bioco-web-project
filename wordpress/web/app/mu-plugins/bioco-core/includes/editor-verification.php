<?php
/** Keep temporary editor checks away from the site's assigned global layouts. */
if (!defined('ABSPATH')) exit;

function bioco_editor_verification_layouts(array $layouts): array {
    $id = (int) get_queried_object_id();
    $run = get_post_meta($id, '_bioco_editor_verification', true);
    if ($id && is_string($run) && preg_match('/^[a-z0-9][a-z0-9-]{7,79}$/D', $run)
        && in_array(get_post_status($id), ['draft', 'auto-draft'], true)
        && current_user_can('edit_post', $id)) {
        $body = (int) get_post_meta($id, '_bioco_editor_verification_body', true);
        $preview = (int) get_post_meta($body, '_bioco_editor_verification_event', true);
        // Several owned events may share one draft body to test inheritance.
        // Its designated editor preview must still be a reciprocal, readable
        // event in this run; a dangling or foreign preview cannot supply values.
        if (get_post_type($id) === 'event' && $body
            && get_post_type($body) === 'et_body_layout'
            && in_array(get_post_status($body), ['draft', 'auto-draft'], true)
            && get_post_meta($body, '_bioco_editor_verification', true) === $run
            && $preview && get_post_type($preview) === 'event'
            && in_array(get_post_status($preview), ['draft', 'auto-draft'], true)
            && get_post_meta($preview, '_bioco_editor_verification', true) === $run
            && (int) get_post_meta($preview, '_bioco_editor_verification_body', true) === $body
            && current_user_can('edit_post', $preview)
            && current_user_can('edit_post', $body)) {
            return ['et_template' => 0,
                'et_header_layout' => ['id' => 0, 'enabled' => false, 'override' => false],
                'et_body_layout' => ['id' => $body, 'enabled' => true, 'override' => true],
                'et_footer_layout' => ['id' => 0, 'enabled' => false, 'override' => false]];
        }
        // A standalone header/footer copy must retain its own editing context.
        return array_filter($layouts, static fn($layout) => is_array($layout)
            && (int) ($layout['id'] ?? 0) === $id);
    }
    return $layouts;
}

add_filter('et_theme_builder_template_layouts', 'bioco_editor_verification_layouts', 100);
