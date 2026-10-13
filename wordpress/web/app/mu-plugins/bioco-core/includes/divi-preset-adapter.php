<?php
/** Correct the supported native preset helper before Divi consumers bind it. */
if (!defined('ABSPATH')) exit;

/** Empty string means supported; other values explain why native code stays unchanged. */
function bioco_divi_preset_adapter_guard(array $context): string {
    if (!$context['app'] || !$context['allowed']) return 'unauthorized-builder';
    if ($context['fingerprint'] !== '6b977ad2a9adfffcce73584b13eb5f471e765db181a21efdc8f4afa2fd19ed7c') {
        return 'unsupported-build';
    }
    if (!$context['registered'] || $context['printed']) return 'unsupported-script-order';
    return '';
}

function bioco_divi_preset_adapter_enqueue(): void {
    $conditions = 'ET\\Builder\\Framework\\Utility\\Conditions';
    $roles = 'ET\\Builder\\Framework\\UserRole\\UserRole';
    if (!class_exists($conditions) || !$conditions::is_vb_app_window()
        || !is_user_logged_in() || !current_user_can('edit_theme_options')
        || !class_exists($roles) || !$roles::can_current_user_use_visual_builder()) return;

    $build = get_template_directory() . '/includes/builder-5/visual-builder/build/module-utils.js';
    $handles = ['divi-module-utils', 'divi-module', 'divi-modal-library'];
    $registered = true;
    $printed = false;
    foreach ($handles as $handle) {
        $registered = $registered && wp_script_is($handle, 'registered');
        $printed = $printed || wp_script_is($handle, 'done');
    }
    $reason = bioco_divi_preset_adapter_guard([
        'app' => true, 'allowed' => true,
        'fingerprint' => is_file($build) ? hash_file('sha256', $build) : '',
        'registered' => $registered, 'printed' => $printed,
    ]);
    if ($reason !== '') {
        do_action('bioco_divi_preset_adapter_diagnostic', $reason);
        error_log('[bioco Divi preset adapter] ' . $reason);
        return;
    }
    $source = file_get_contents(__DIR__ . '/../assets/divi-preset-adapter.js');
    if ($source === false || !wp_add_inline_script('divi-module-utils', $source, 'after')) {
        do_action('bioco_divi_preset_adapter_diagnostic', 'inline-script-unavailable');
    }
}

// Divi has registered the iframe handles here, but WordPress has not printed
// them. Inline-after runs before each consumer's module-utils dependency ends.
add_action('divi_visual_builder_assets_after_enqueue_app_window_scripts', 'bioco_divi_preset_adapter_enqueue');
