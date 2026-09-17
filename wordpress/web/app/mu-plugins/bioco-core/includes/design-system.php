<?php
/** Live Divi variables feed the existing theme and component CSS contract. */
if (!defined('ABSPATH')) exit;

function bioco_divi_design_manifest(): array {
    static $manifest;
    if ($manifest === null) {
        $raw = @file_get_contents(BIOCO_CORE_DIR . '/assets/design-system.json');
        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $loadError) {
                $decoded = null;
            }
        }
        // Fail closed: the bridge runs on every front-end request. Without a
        // structurally valid manifest it contributes no CSS instead of raising.
        $manifest = is_array($decoded) && is_array($decoded['tokens'] ?? null) ? $decoded : ['tokens' => []];
    }
    return $manifest;
}

function bioco_divi_token_id(array $token, string $category): string {
    return ($category === 'colors' ? 'gcid-' : 'gvid-') . 'bioco-' . substr(hash('sha256', $token['cssVar']), 0, 16);
}

function bioco_divi_token_type(string $category): string {
    if ($category === 'fonts') return 'fonts';
    return in_array($category, ['typography', 'spacing', 'radii'], true) ? 'numbers' : 'strings';
}

function bioco_divi_token_css(): string {
    $api = '\\ET\\Builder\\Packages\\GlobalData\\GlobalData';
    if (!class_exists($api)) return '';
    $colors = $api::get_global_colors();
    $variables = array_map(static fn($items) => (array) $items, $api::get_global_variables());
    $declarations = [];
    foreach (bioco_divi_design_manifest()['tokens'] as $category => $tokens) {
        if (!is_array($tokens)) continue;
        foreach ($tokens as $token) {
            if (!is_array($token) || !is_string($token['cssVar'] ?? null)) continue;
            $id = bioco_divi_token_id($token, $category);
            $item = $category === 'colors' ? ($colors[$id] ?? []) : ($variables[bioco_divi_token_type($category)][$id] ?? []);
            $value = $item[$category === 'colors' ? 'color' : 'value'] ?? null;
            // Treat vendor/editor values as data, never as a stylesheet fragment.
            if (($item['status'] ?? '') !== 'active' || !is_string($value) || $value === '' || preg_match('/[;{}<>\\\\]|url\s*\(|expression\s*\(/i', $value)) continue;
            $property = $token['cssVar'];
            if (!preg_match('/^--[a-z0-9-]+$/', $property)) continue;
            // Divi can omit variable declarations used only through nested group
            // presets. Publish their stable IDs from the same live value.
            $declarations[] = '--' . $id . ':' . $value;
            $declarations[] = $property . ':' . $value;
        }
    }
    return $declarations ? ':root{' . implode(';', $declarations) . '}' : '';
}

add_action('wp_enqueue_scripts', function () {
    $css = bioco_divi_token_css();
    if ($css !== '') wp_add_inline_style('bioco-tokens', $css);
}, 30);

// Theme Builder owns placement. The existing WordPress navigation settings
// remain the source of labels, links and contact data.
add_action('init', function () {
add_shortcode('bioco_global_header', function () {
    return '<div class="bioco-site-header"><div class="bioco-page-shell bioco-hero-nav-overlay">' . bioco_render_primary_navigation() . '</div></div>';
});
add_shortcode('bioco_global_footer', function () {
    return preg_replace(['/<footer\b/', '~</footer>$~'], ['<div', '</div>'], bioco_render_site_footer());
});
});
add_action('et_theme_builder_template_before_body', function ($id, $enabled) {
    if ($id && $enabled) echo '<main id="bioco-main-content">';
}, 10, 2);
add_action('et_theme_builder_template_after_body', function ($id, $enabled) {
    if ($id && $enabled) echo '</main>';
}, 10, 2);
