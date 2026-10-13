<?php
/** Correct the supported native preset helper before Divi consumers bind it. */
if (!defined('ABSPATH')) exit;

/** Empty string means supported; other values explain why native code stays unchanged. */
function bioco_divi_preset_adapter_guard(array $context): string {
    if (!$context['app'] || !$context['allowed']) return 'unauthorized-builder';
    // Captured module-utils builds: Divi 5.11.0 staging and 5.15.0 production.
    if (!in_array($context['fingerprint'], [
        '6b977ad2a9adfffcce73584b13eb5f471e765db181a21efdc8f4afa2fd19ed7c',
        'cbb43621ff0d4089a180c822b5ae0b2253246b4a3a6577947ea29fb88168d808',
    ], true)) {
        return 'unsupported-build';
    }
    if (!$context['registered'] || $context['printed']) return 'unsupported-script-order';
    return '';
}

/** Resolve the actual native registration through Divi's asset integration API. */
function bioco_divi_preset_adapter_fingerprint(): string {
    if (!defined('ET_BUILDER_5_DIR') || !defined('ET_BUILDER_5_URI')
        || !is_string(ET_BUILDER_5_DIR) || !is_string(ET_BUILDER_5_URI)) return '';
    $scripts = wp_scripts();
    $registered = $scripts->registered['divi-module-utils'] ?? null;
    $src = rtrim(ET_BUILDER_5_URI, '/') . '/visual-builder/build/module-utils.js';
    if (!$registered || $registered->src !== $src) return '';
    $build = rtrim(ET_BUILDER_5_DIR, '/\\') . '/visual-builder/build/module-utils.js';
    if (!is_file($build)) return '';
    $hash = hash_file('sha256', $build);
    return is_string($hash) ? $hash : '';
}

function bioco_divi_preset_adapter_enqueue(): void {
    $conditions = 'ET\\Builder\\Framework\\Utility\\Conditions';
    $roles = 'ET\\Builder\\Framework\\UserRole\\UserRole';
    if (!class_exists($conditions) || !$conditions::is_vb_app_window()
        || !is_user_logged_in() || !current_user_can('edit_theme_options')
        || !class_exists($roles) || !$roles::can_current_user_use_visual_builder()) return;

    $handles = ['divi-module-utils', 'divi-module', 'divi-modal-library'];
    $registered = true;
    $printed = false;
    foreach ($handles as $handle) {
        $registered = $registered && wp_script_is($handle, 'registered');
        $printed = $printed || wp_script_is($handle, 'done');
    }
    $reason = bioco_divi_preset_adapter_guard([
        'app' => true, 'allowed' => true,
        'fingerprint' => bioco_divi_preset_adapter_fingerprint(),
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
