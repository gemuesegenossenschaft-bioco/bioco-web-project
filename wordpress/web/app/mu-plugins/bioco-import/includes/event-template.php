<?php
/** A saved native event body; values remain bound to the current event record. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/article-back-button.php';

function bioco_import_event_variable(string $field): string {
    return '$variable(' . wp_json_encode(['type' => 'content', 'value' => [
        'name' => 'bioco_event_' . $field, 'settings' => new stdClass(),
    ]], JSON_UNESCAPED_UNICODE) . ')$';
}

function bioco_import_event_template_blocks(): array {
    $module = static function (string $name, string $label, array $attrs, array $decoration = []): array {
        $attrs['module']['meta']['adminLabel']['desktop']['value'] = $label;
        if ($decoration) $attrs['module']['decoration'] = $decoration;
        return bioco_import_divi_block($name, $attrs);
    };
    $value = static fn($value): array => ['desktop' => ['value' => $value]];
    $font = static fn(array $values): array => ['font' => $value($values)];
    $spacing = static fn(array $values): array => $value($values);
    $title = $module('divi/heading', 'Veranstaltungstitel', ['title' => [
        'innerContent' => $value(bioco_import_event_variable('title')),
        'decoration' => ['font' => $font(['headingLevel' => 'h1', 'size' => '40px',
            'lineHeight' => '1.08em', 'letterSpacing' => '-1.2px', 'weight' => '700'])],
    ]]);
    $date = $module('divi/text', 'Veranstaltungsdatum', ['content' => [
        'innerContent' => $value(bioco_import_event_variable('date_time')),
        'decoration' => ['font' => $font(['size' => '16px', 'color' => '#4A4A4A'])],
    ]], ['spacing' => $spacing(['margin' => ['bottom' => '32px']])]);
    $image = $module('divi/image', 'Veranstaltungsbild', ['image' => [
        'innerContent' => $value(['src' => bioco_import_event_variable('image'),
            'alt' => bioco_import_event_variable('image_alt')]),
        'decoration' => ['border' => $value(['radius' => ['topLeft' => '16px',
            'topRight' => '16px', 'bottomLeft' => '16px', 'bottomRight' => '16px']])],
    ]], ['spacing' => $spacing(['margin' => ['bottom' => '40px']])]);
    $body = $module('divi/text', 'Veranstaltungsbeschreibung', ['content' => [
        'innerContent' => $value(bioco_import_event_variable('description')),
        'decoration' => ['font' => $font(['size' => '18px', 'lineHeight' => '1.8em'])],
    ]]);
    $back = bioco_import_article_back_button();
    $column = bioco_import_divi_block('divi/column', ['module' => [
        'advanced' => ['type' => $value('4_4')],
    ]], [$title, $date, $image, $body, $back]);
    $row = bioco_import_divi_block('divi/row', ['module' => [
        'advanced' => ['columnStructure' => $value('4_4')],
        'decoration' => ['sizing' => $value(['width' => 'calc(100% - 40px)',
            'maxWidth' => '760px', 'alignment' => 'center']),
            'spacing' => $spacing(['padding' => ['top' => '0px', 'bottom' => '0px', 'left' => '0px', 'right' => '0px']])],
    ]], [$column]);
    return [bioco_import_divi_block('divi/section', ['module' => [
        'meta' => ['adminLabel' => $value('Veranstaltung: Detailvorlage')],
        'decoration' => ['spacing' => [
            'desktop' => ['value' => ['padding' => ['top' => '112px', 'bottom' => '112px']]],
            'tablet' => ['value' => ['padding' => ['top' => '56px', 'bottom' => '56px']]],
            'phone' => ['value' => ['padding' => ['top' => '56px', 'bottom' => '56px']]],
        ], 'background' => $value(['color' => '#F5F1E8'])],
    ]], [$row])];
}
