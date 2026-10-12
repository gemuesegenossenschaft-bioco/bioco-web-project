<?php
/** Native defaults shared by the saved event body and request-only article fallback. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/divi-blocks.php';

function bioco_import_article_back_button(): array {
    $value = static fn(array $values): array => ['desktop' => ['value' => $values]];
    $token = static function (string $css_var, string $type = 'content'): string {
        $id = ($type === 'color' ? 'gcid-' : 'gvid-') . 'bioco-' . substr(hash('sha256', $css_var), 0, 16);
        // Divi resolves content bindings before styling; numeric globals use CSS variables.
        if ($type !== 'color') return 'var(--' . $id . ')';
        return '$variable(' . json_encode(['type' => $type, 'value' => [
            'name' => $id, 'settings' => new stdClass(),
        ]]) . ')$';
    };
    return bioco_import_divi_block('divi/button', [
        // The existing preset retains editor changes to the shared palette and typography.
        'modulePreset' => ['bioco-' . substr(hash('sha256', 'moduleBIOCO Secondary Button'), 0, 16)],
        'module' => [
            'meta' => ['adminLabel' => ['desktop' => ['value' => 'Zurück zu Aktuelles']]],
            'advanced' => ['htmlAttributes' => $value(['class' => 'bioco-article-back'])],
            'decoration' => ['spacing' => $value(['margin' => ['top' => '48px']])],
        ],
        'button' => [
            'innerContent' => $value(['text' => 'Zurück zu Aktuelles',
                'linkUrl' => home_url('/aktuelles/'), 'linkTarget' => 'off']),
            'decoration' => [
                'border' => $value([
                    'radius' => array_fill_keys(['topLeft', 'topRight', 'bottomLeft', 'bottomRight'], $token('--wp--custom--radius--md')),
                    'styles' => ['all' => ['width' => '2px', 'style' => 'solid', 'color' => 'transparent']],
                ]),
                'font' => ['font' => ['desktop' => [
                    'value' => ['lineHeight' => '1.5em'],
                    'hover' => ['color' => $token('--wp--preset--color--bioco-green-dark', 'color')],
                ]]],
                'background' => ['desktop' => ['hover' => ['color' => $token('--wp--preset--color--bioco-bg', 'color')]]],
                'sizing' => $value(['minHeight' => '44px', 'maxWidth' => '100%']),
            ],
        ],
    ]);
}
