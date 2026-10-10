<?php
/** Native shell modules consume saved Divi values, including explicit empty lists. */
if (!defined('ABSPATH')) exit;

function bioco_editable_shell_style(string $slot): string {
    $ranges = [
        'container_width' => [320, 2400], 'inline_padding' => [0, 200],
        'gap' => [0, 200], 'font_size' => [10, 48], 'font_size_mobile' => [10, 48], 'logo_width' => [24, 400],
        'toggle_size' => [44, 88], 'toggle_line_width' => [12, 48],
        'logo_width_mobile' => [24, 240], 'padding_vertical' => [0, 200],
        'padding_mobile' => [0, 100], 'columns_desktop' => [1, 6], 'columns_mobile' => [1, 3],
    ];
    $styles = [];
    foreach ($ranges as $name => [$min, $max]) {
        $value = bioco_field($name);
        if (!is_numeric($value)) continue;
        $number = max($min, min($max, (float) $value));
        if (str_starts_with($name, 'columns_')) $number = (int) $number;
        $styles[] = '--bioco-' . $slot . '-' . str_replace('_', '-', $name) . ':' . $number
            . (str_starts_with($name, 'columns_') ? '' : 'px');
    }
    foreach (['background_color', 'text_color', 'link_color', 'toggle_color'] as $name) {
        $value = bioco_field($name);
        if (!is_string($value) || !preg_match('/^(?:#[0-9a-f]{3,8}|var\(--[a-z0-9-]+\))$/iD', $value)) continue;
        $styles[] = '--bioco-' . $slot . '-' . str_replace('_', '-', $name) . ':' . $value;
    }
    return implode(';', $styles);
}

function bioco_render_editable_header(): string {
    $tag = empty($GLOBALS['bioco_theme_builder_shell_depth']['header']) ? 'header' : 'div';
    $site = [];
    foreach (['logo_alt' => 'logoAlt', 'home_label' => 'homeLabel', 'utility_label' => 'utilityLabel',
        'primary_label' => 'primaryLabel', 'menu_open_label' => 'menuOpenLabel', 'menu_close_label' => 'menuCloseLabel'] as $field => $key) {
        $site[$key] = (string) bioco_field($field, '');
    }
    $image = (int) bioco_field('logo', 0);
    $site['logoUrl'] = $image ? (string) wp_get_attachment_image_url($image, 'full') : (string) bioco_field('logo_url', '');
    $contract = ['site' => $site, 'utility' => bioco_field('utility', []), 'primary' => bioco_field('primary', []),
        'cta' => ['label' => bioco_field('cta_label', ''), 'url' => bioco_field('cta_url', ''), 'slug' => bioco_field('cta_slug', '')]];
    return '<' . $tag . ' class="cms-navigation-shell bioco-site-header bioco-editable-header" style="' . esc_attr(bioco_editable_shell_style('header')) . '">'
        . '<div class="bioco-page-shell bioco-hero-nav-overlay">' . bioco_render_primary_navigation($contract) . '</div></' . $tag . '>';
}

function bioco_editable_shell_links(array $links): string {
    $html = '';
    foreach ($links as $link) {
        $url = bioco_navigation_url((string) ($link['url'] ?? ''));
        if ($url === '') continue;
        $external = preg_match('#^https?://#i', (string) $link['url']) === 1;
        $html .= '<li><a href="' . esc_url($url) . '"' . ($external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>'
            . esc_html((string) ($link['label'] ?? '')) . '</a></li>';
    }
    return $html;
}

function bioco_render_editable_footer(): string {
    if (is_page('anmeldung')) return '';
    $tag = empty($GLOBALS['bioco_theme_builder_shell_depth']['footer']) ? 'footer' : 'div';
    $html = '<' . $tag . ' id="footer" class="cms-footer-shell bioco-site-footer bioco-editable-footer" style="' . esc_attr(bioco_editable_shell_style('footer')) . '"><div class="bioco-site-footer-inner">';
    foreach (bioco_field('columns', []) as $column) {
        $gap = max(0, min(200, (float) ($column['link_gap'] ?? 0)));
        $html .= '<div class="bioco-site-footer-column" style="--bioco-footer-link-gap:' . $gap . 'px"><h3>' . esc_html((string) ($column['heading'] ?? '')) . '</h3>'
            . wp_kses_post((string) ($column['text'] ?? '')) . '<ul>' . bioco_editable_shell_links($column['links'] ?? []) . '</ul></div>';
    }
    return $html . '</div><div class="bioco-site-footer-partners"><h3>' . esc_html((string) bioco_field('partners_title', ''))
        . '</h3><ul class="bioco-site-footer-partner-links">' . bioco_editable_shell_links(bioco_field('partners', [])) . '</ul><p>'
        . esc_html((string) bioco_field('region_text', '')) . '</p></div></' . $tag . '>';
}
