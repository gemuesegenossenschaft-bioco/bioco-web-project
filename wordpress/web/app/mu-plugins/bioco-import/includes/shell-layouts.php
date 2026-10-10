<?php
/** Capture the current shell contract into ordinary editable native attributes. */
if (!defined('ABSPATH')) exit;

function bioco_import_shell_block(string $slot): array {
    if (!in_array($slot, ['header', 'footer'], true)) throw new InvalidArgumentException('Unknown shell slot.');
    $contract = bioco_navigation_contract();
    $values = [];
    if ($slot === 'header') {
        $site = $contract['site'];
        foreach (['logoAlt' => 'logo_alt', 'homeLabel' => 'home_label', 'utilityLabel' => 'utility_label',
            'primaryLabel' => 'primary_label', 'menuOpenLabel' => 'menu_open_label', 'menuCloseLabel' => 'menu_close_label'] as $key => $field) {
            $values[$field] = $site[$key] ?? '';
        }
        $values['logo_url'] = plugins_url($site['logo'] ?? 'assets/bioco-logo.png', dirname(__DIR__, 2) . '/bioco-core/bioco-core.php');
        foreach (['utility', 'primary'] as $key) $values[$key] = $contract[$key];
        foreach (['label', 'url', 'slug'] as $key) $values['cta_' . $key] = $contract['cta'][$key] ?? '';
    } else {
        $footer = $contract['footer'];
        $contact = '<p><strong>' . esc_html($footer['contactName']) . '</strong><br>'
            . implode('<br>', array_map('esc_html', $footer['contactAddress'])) . '</p><p><a href="mailto:'
            . esc_attr($footer['contactEmail']) . '">' . esc_html($footer['contactEmail']) . '</a></p>';
        $values['columns'] = [
            ['heading' => $footer['navigationTitle'], 'text' => '', 'links' => $footer['navigation']],
            ['heading' => $footer['contactTitle'], 'text' => $contact, 'links' => []],
            ['heading' => $footer['socialTitle'], 'text' => '', 'links' => $footer['social'], 'link_gap' => 16],
        ];
        $values += ['partners_title' => $footer['partnersTitle'], 'partners' => $footer['partners'], 'region_text' => $footer['regionText']];
    }
    $attrs = [];
    foreach ($values as $key => $value) $attrs[$key] = ['innerContent' => ['desktop' => ['value' => $value]]];
    return bioco_import_divi_block('bioco-divi/' . ($slot === 'header' ? 'navigation-shell' : 'footer-shell'), $attrs);
}

function bioco_import_shell_tree(array $blocks, int &$count): array {
    foreach ($blocks as &$block) {
        if (($block['blockName'] ?? '') === 'divi/text') {
            $content = $block['attrs']['content']['innerContent']['desktop']['value'] ?? '';
            if (is_string($content) && preg_match('/^\s*\[bioco_global_(header|footer)\]\s*$/D', $content, $match)) {
                $replacement = bioco_import_shell_block($match[1]);
                $attrs = $block['attrs'];
                unset($attrs['content']);
                $replacement['attrs'] = array_replace_recursive($attrs, $replacement['attrs']);
                $block = $replacement;
                $count++;
            } elseif (is_string($content) && preg_match('/\[bioco_global_(?:header|footer)\]/', $content)) {
                throw new RuntimeException('Shell shortcode shares a text module with editorial content.');
            }
        }
        if (!empty($block['innerBlocks'])) $block['innerBlocks'] = bioco_import_shell_tree($block['innerBlocks'], $count);
    }
    return $blocks;
}

function bioco_import_shell_content(string $content): array {
    $count = 0;
    $blocks = bioco_import_shell_tree(parse_blocks($content), $count);
    return [$count ? serialize_blocks($blocks) : $content, $count];
}
