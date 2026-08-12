<?php
/**
 * Two-tier navigation contract shared by the block theme and importer.
 */

if (!defined('ABSPATH')) exit;

function bioco_navigation_contract() {
    $path = dirname(__DIR__) . '/content/navigation.json';
    $seed = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
    if (!is_array($seed)) return ['utility' => [], 'primary' => [], 'cta' => null];
    return [
        'utility' => is_array($seed['utility'] ?? null) ? array_values($seed['utility']) : [],
        'primary' => is_array($seed['primary'] ?? null) ? array_values($seed['primary']) : [],
        'cta' => is_array($seed['cta'] ?? null) ? $seed['cta'] : null,
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
    return preg_match('#^https?://#i', $url) ? $url : home_url($url);
}

function bioco_navigation_links(array $items, $linkClass = '', $itemClass = '') {
    $markup = '';
    foreach ($items as $item) {
        if (empty($item['label']) || empty($item['url'])) continue;
        $class = $linkClass !== '' ? ' class="' . esc_attr($linkClass) . '"' : '';
        $item_class = $itemClass !== '' ? ' class="' . esc_attr($itemClass) . '"' : '';
        $markup .= '<li' . $item_class . '><a' . $class . ' href="' . esc_url(bioco_navigation_url($item['url'])) . '">' . esc_html($item['label']) . '</a></li>';
    }
    return $markup;
}

function bioco_render_primary_navigation() {
    $contract = bioco_navigation_contract();
    $logo = plugins_url('assets/bioco-logo.png', dirname(__DIR__) . '/bioco-core.php');
    $cta = $contract['cta'] ? bioco_navigation_links([$contract['cta']], 'bioco-primary-cta') : '';
    $mobile_utility = bioco_navigation_links($contract['utility'], '', 'bioco-mobile-utility');

    return '<div class="bioco-navigation-shell">'
        . '<nav class="bioco-utility-nav" aria-label="Hilfsnavigation"><ul>'
        . bioco_navigation_links($contract['utility'])
        . '</ul></nav>'
        . '<nav class="bioco-primary-nav" aria-label="Hauptnavigation">'
        . '<a class="bioco-logo" href="' . esc_url(home_url('/')) . '" aria-label="biocò Startseite"><img src="' . esc_url($logo) . '" alt="biocò"></a>'
        . '<ul id="bioco-primary-menu">' . bioco_navigation_links($contract['primary']) . $cta . $mobile_utility . '</ul>'
        . '<button class="bioco-menu-toggle" type="button" aria-label="Menü öffnen" aria-controls="bioco-primary-menu" aria-expanded="false"><span></span><span></span><span></span></button>'
        . '</nav></div>';
}
