<?php
/**
 * Two-tier navigation contract shared by the block theme and importer.
 */

if (!defined('ABSPATH')) exit;

function bioco_navigation_contract() {
    $path = dirname(__DIR__) . '/content/navigation.json';
    $seed = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
    if (!is_array($seed)) return ['site' => [], 'utility' => [], 'primary' => [], 'cta' => null, 'legal' => [], 'footer' => []];
    return [
        'site' => is_array($seed['site'] ?? null) ? $seed['site'] : [],
        'utility' => is_array($seed['utility'] ?? null) ? array_values($seed['utility']) : [],
        'primary' => is_array($seed['primary'] ?? null) ? array_values($seed['primary']) : [],
        'cta' => is_array($seed['cta'] ?? null) ? $seed['cta'] : null,
        'legal' => is_array($seed['legal'] ?? null) ? array_values($seed['legal']) : [],
        'footer' => is_array($seed['footer'] ?? null) ? $seed['footer'] : [],
    ];
}

function bioco_primary_navigation_items() {
    $contract = bioco_navigation_contract();
    $items = $contract['primary'];
    if ($contract['cta']) $items[] = $contract['cta'];
    return $items;
}

function bioco_navigation_url($url) {
    $url = (string) $url;
    if ($url === '' || $url !== trim($url) || preg_match('/[\\x00-\\x20\\\\]/', $url)) return '';
    if (str_starts_with($url, '//')) return '';
    if (preg_match('#^https?://#i', $url)) {
        $parts = parse_url($url);
        return is_array($parts) && !empty($parts['host']) ? $url : '';
    }
    if (!str_starts_with($url, '/')) return '';
    return home_url($url);
}

function bioco_navigation_item_is_current(array $item) {
    $slug = (string) ($item['slug'] ?? '');
    if ($slug === '') return false;
    if (function_exists('is_page') && is_page($slug)) return true;
    if ($slug === 'aktuelles') {
        if (function_exists('is_singular') && is_singular('event')) return true;
        if (function_exists('is_post_type_archive') && is_post_type_archive('event')) return true;
    }
    return false;
}

function bioco_navigation_links(array $items, $linkClass = '', $itemClass = '') {
    $markup = '';
    foreach ($items as $item) {
        if (empty($item['label']) || empty($item['url'])) continue;
        $href = bioco_navigation_url($item['url']);
        if ($href === '') continue;
        $current = bioco_navigation_item_is_current($item);
        $link_classes = trim($linkClass . ($current ? ' is-current' : ''));
        $class = $link_classes !== '' ? ' class="' . esc_attr($link_classes) . '"' : '';
        $item_classes = trim($itemClass . ($current ? ' is-current' : ''));
        $item_class = $item_classes !== '' ? ' class="' . esc_attr($item_classes) . '"' : '';
        $current_attr = $current ? ' aria-current="page"' : '';
        $markup .= '<li' . $item_class . '><a' . $class . $current_attr . ' href="' . esc_url($href) . '">' . esc_html($item['label']) . '</a></li>';
    }
    return $markup;
}

function bioco_render_primary_navigation() {
    $contract = bioco_navigation_contract();
    $site = $contract['site'];
    $logo = plugins_url((string) ($site['logo'] ?? 'assets/bioco-logo.png'), dirname(__DIR__) . '/bioco-core.php');
    $home_label = (string) ($site['homeLabel'] ?? '');
    $logo_alt = (string) ($site['logoAlt'] ?? '');
    $utility_label = (string) ($site['utilityLabel'] ?? '');
    $primary_label = (string) ($site['primaryLabel'] ?? '');
    $menu_open_label = (string) ($site['menuOpenLabel'] ?? '');
    $menu_close_label = (string) ($site['menuCloseLabel'] ?? '');
    $cta = $contract['cta'] ? bioco_navigation_links([$contract['cta']], 'bioco-primary-cta') : '';
    $mobile_utility = bioco_navigation_links($contract['utility'], '', 'bioco-mobile-utility');

    return '<div class="bioco-navigation-shell">'
        . '<nav class="bioco-utility-nav" aria-label="' . esc_attr($utility_label) . '"><ul>'
        . bioco_navigation_links($contract['utility'])
        . '</ul></nav>'
        . '<nav class="bioco-primary-nav" aria-label="' . esc_attr($primary_label) . '">'
        . '<a class="bioco-logo" href="' . esc_url(home_url('/')) . '" aria-label="' . esc_attr($home_label) . '"><img src="' . esc_url($logo) . '" alt="' . esc_attr($logo_alt) . '"></a>'
        . '<ul id="bioco-primary-menu">' . bioco_navigation_links($contract['primary']) . $cta . $mobile_utility . '</ul>'
        . '<button class="bioco-menu-toggle" type="button" aria-label="' . esc_attr($menu_open_label) . '" data-open-label="' . esc_attr($menu_open_label) . '" data-close-label="' . esc_attr($menu_close_label) . '" aria-controls="bioco-primary-menu" aria-expanded="false"><span></span><span></span><span></span></button>'
        . '</nav></div>';
}

function bioco_render_site_footer() {
    $contract = bioco_navigation_contract();
    $site = $contract['site'];
    $footer = $contract['footer'];
    $logo = plugins_url((string) ($site['logo'] ?? 'assets/bioco-logo.png'), dirname(__DIR__) . '/bioco-core.php');
    $home_label = (string) ($site['homeLabel'] ?? '');
    $logo_alt = (string) ($site['logoAlt'] ?? '');

    return '<div class="bioco-site-footer"><div class="bioco-site-footer-inner">'
        . '<div class="bioco-site-footer-brand"><a href="' . esc_url(home_url('/')) . '" aria-label="' . esc_attr($home_label) . '"><img src="' . esc_url($logo) . '" alt="' . esc_attr($logo_alt) . '"></a><p>' . esc_html((string) ($footer['slogan'] ?? '')) . '</p></div>'
        . '<nav aria-label="' . esc_attr((string) ($footer['primaryLabel'] ?? '')) . '"><ul>' . bioco_navigation_links($contract['primary']) . '</ul></nav>'
        . '<nav aria-label="' . esc_attr((string) ($footer['utilityLabel'] ?? '')) . '"><ul>' . bioco_navigation_links($contract['utility']) . '</ul></nav>'
        . '<nav aria-label="' . esc_attr((string) ($footer['legalLabel'] ?? '')) . '"><ul>' . bioco_navigation_links($contract['legal']) . '</ul></nav>'
        . '</div><p class="bioco-site-footer-meta">&copy; ' . esc_html((string) gmdate('Y')) . ' ' . esc_html((string) ($footer['copyright'] ?? '')) . '</p></div>';
}
