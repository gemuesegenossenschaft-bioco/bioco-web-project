<?php
/** Saved native list trees. Only queries and conditional state remain in plugins. */
if (!defined('ABSPATH')) exit;

function bioco_import_event_loop_variable(string $field): string {
    return '$variable(' . wp_json_encode(['type' => 'content', 'value' => [
        'name' => 'loop_bioco_event_' . $field, 'settings' => new stdClass(),
    ]], JSON_UNESCAPED_UNICODE) . ')$';
}

function bioco_import_event_list_value($value): array { return ['desktop' => ['value' => $value]]; }

function bioco_import_event_list_element(string $name, string $label, array $attrs, array $design = []): array {
    $attrs['module']['meta']['adminLabel'] = bioco_import_event_list_value($label);
    if ($name === 'divi/button') {
        $attrs['modulePreset'] = ['bioco-' . substr(hash('sha256', 'moduleBIOCO Secondary Button'), 0, 16)];
        $attrs['button']['decoration']['sizing'] = bioco_import_event_list_value(['minHeight' => '44px', 'maxWidth' => '100%']);
    }
    if ($design) $attrs['module']['decoration'] = $design;
    return bioco_import_divi_block($name, $attrs);
}

function bioco_import_event_list_text(string $text, string $label, bool $heading = false): array {
    $field = $heading ? 'title' : 'content';
    $decoration = ['font' => ['font' => bioco_import_event_list_value($heading
        ? ['headingLevel' => 'h3', 'size' => $label === 'Veranstaltungstitel' ? '18px' : '24px', 'weight' => '700']
        : ['size' => '16px', 'lineHeight' => '1.6em'])]];
    return bioco_import_event_list_element($heading ? 'divi/heading' : 'divi/text', $label, [
        $field => ['innerContent' => bioco_import_event_list_value($text), 'decoration' => $decoration],
    ]);
}

function bioco_import_event_list_row(array $children, string $class = ''): array {
    $v = 'bioco_import_event_list_value';
    return bioco_import_divi_block('divi/row', ['module' => [
        'advanced' => ['columnStructure' => $v('4_4'), 'htmlAttributes' => $v(['class' => $class])],
        'decoration' => ['sizing' => $v(['width' => '100%', 'maxWidth' => '100%']),
            'spacing' => $v(['padding' => ['top' => '0px', 'bottom' => '0px', 'left' => '0px', 'right' => '0px']])],
    ]], [bioco_import_divi_block('divi/column', ['module' => ['advanced' => ['type' => $v('4_4')]]], $children)]);
}

/** One nested loop row per record inside an ordinary responsive native grid column. */
function bioco_import_event_list_loop(string $status, ?string $type, int $limit, string $empty,
    string $label, string $destination = '', bool $cards = true): array {
    $v = 'bioco_import_event_list_value';
    $binding = 'bioco_import_event_loop_variable';
    $children = [];
    if ($cards) $children[] = bioco_import_event_list_element('divi/image', 'Veranstaltungsbild', ['image' => [
        'innerContent' => $v(['src' => $binding('image'), 'alt' => $binding('image_alt'), 'linkUrl' => $binding('url'), 'linkTarget' => 'off']),
    ]]);
    $children[] = bioco_import_event_list_text($binding('date_time'), 'Veranstaltungsdatum');
    $children[] = bioco_import_event_list_text('<a href="' . $binding('url') . '">' . $binding('title') . '</a>', 'Veranstaltungstitel', true);
    if ($cards) $children[] = bioco_import_event_list_text($binding('summary'), 'Veranstaltungsbeschreibung');
    if ($label !== '') $children[] = bioco_import_event_list_element('divi/button', 'Veranstaltungslink', ['button' => [
        'innerContent' => $v(['text' => $label, 'linkUrl' => $destination !== '' ? $destination : $binding('url'), 'linkTarget' => 'off']),
    ]]);
    $emptyBlock = bioco_import_event_list_text(esc_html($empty), 'Hinweis ohne Veranstaltungen');
    $emptyBlock['attrs']['module']['advanced']['htmlAttributes'] = $v(['class' => 'bioco-loop-empty']);
    $children[] = $emptyBlock;
    $class = 'bioco-record-loop bioco-event-loop bioco-event-' . $status . ($type !== null ? ' bioco-event-' . $type : '');
    $row = bioco_import_event_list_row($children, $class);
    $row['attrs']['module']['advanced']['loop'] = $v([
        'enable' => 'on', 'queryType' => 'post_types', 'subTypes' => [['value' => 'event', 'label' => 'Veranstaltungen']],
        'postPerPage' => $limit, 'orderBy' => 'meta_value', 'order' => $status === 'past' ? 'DESC' : 'ASC',
        'loopId' => 'bioco-events-' . wp_generate_uuid4(),
    ]);
    $column = $row['innerBlocks'][0];
    $column['attrs']['module']['decoration'] = [
        'background' => $v(['color' => '#FFFFFF']),
        'spacing' => $v(['padding' => ['top' => '24px', 'bottom' => '24px', 'left' => '24px', 'right' => '24px']]),
        'border' => $v(['radius' => ['topLeft' => '16px', 'topRight' => '16px', 'bottomLeft' => '16px', 'bottomRight' => '16px']]),
    ];
    $row['innerBlocks'][0] = $column;
    $outer = bioco_import_event_list_row([$row]);
    $outer['innerBlocks'][0]['attrs']['module']['decoration']['layout'] = [
        'desktop' => ['value' => ['display' => 'grid', 'gridColumnWidths' => 'manual',
            'gridTemplateColumns' => $cards ? 'repeat(3, minmax(0, 1fr))' : 'minmax(0, 1fr)', 'columnGap' => '24px', 'rowGap' => '24px']],
        'tablet' => ['value' => ['gridTemplateColumns' => $cards ? 'repeat(2, minmax(0, 1fr))' : 'minmax(0, 1fr)']],
        'phone' => ['value' => ['gridTemplateColumns' => 'minmax(0, 1fr)']],
    ];
    return $outer;
}

/** Values must first pass the existing native field contract; false/zero/blank are retained. */
function bioco_import_event_list_blocks(string $component, array $values): array {
    if (!in_array($component, ['events_feed', 'schnuppertage'], true)) throw new InvalidArgumentException('Unknown event list');
    $v = 'bioco_import_event_list_value';
    $text = 'bioco_import_event_list_text';
    $row = 'bioco_import_event_list_row';
    $loop = 'bioco_import_event_list_loop';
    $limit = array_key_exists('limit', $values) ? (int) $values['limit'] : 3;
    $rows = [];
    $heading = static function (string $title, string $body = '') use (&$rows, $text, $row): void {
        $children = [];
        if ($title !== '') $children[] = $text(esc_html($title), 'Listenüberschrift', true);
        if ($body !== '') $children[] = $text($body, 'Listenbeschreibung');
        if ($children) $rows[] = $row($children);
    };
    if ($component === 'events_feed') {
        $banner = ($values['variant'] ?? 'standard') === 'banner';
        $heading((string) ($values[$banner ? 'title' : 'standard_title'] ?? ''));
        $include = $values['include_schnuppertage'] ?? false;
        $rows[] = $loop('upcoming', $include ? null : 'general', $limit,
            (string) ($values['empty_message'] ?? ''), '', '', !$banner);
        $link = (string) ($values[$banner ? 'banner_link_label' : 'standard_link_label'] ?? '');
        $url = (string) ($values['archive_url'] ?? '');
        if ($link !== '' && $url !== '') $rows[] = $row([bioco_import_event_list_element('divi/button', 'Archivlink', [
            'button' => ['innerContent' => $v(['text' => $link, 'linkUrl' => bioco_navigation_url($url), 'linkTarget' => 'off'])],
        ])]);
        if (!$banner && !$include && (string) ($values['schnuppertage_title'] ?? '') !== '') {
            $heading((string) $values['schnuppertage_title']);
            $rows[] = $loop('upcoming', 'schnuppertag', $limit, (string) ($values['schnuppertage_empty_message'] ?? ''), '', '', false);
        }
        if (!$banner && (string) ($values['past_title'] ?? '') !== '') {
            // A native one-record loop owns this heading's visibility. The shared
            // empty adapter suppresses the row when no past records exist.
            $header = $loop('past', null, 1, '', '');
            $header = $header['innerBlocks'][0]['innerBlocks'][0];
            $header['innerBlocks'][0]['attrs']['module']['decoration'] = [];
            $modules = [$text(esc_html((string) $values['past_title']), 'Rückblick-Titel', true)];
            if ((string) ($values['past_description'] ?? '') !== '') $modules[] = $text(esc_html((string) $values['past_description']), 'Rückblick-Beschreibung');
            $header['innerBlocks'][0]['innerBlocks'] = $modules;
            $header['innerBlocks'][0]['innerContent'] = array_fill(0, count($modules), null);
            $rows[] = $header;
            $rows[] = $loop('past', null, 4, '', (string) ($values['past_link_label'] ?? ''));
        }
    } else {
        $heading((string) ($values['title'] ?? ''), (string) ($values['text'] ?? ''));
        $information = [];
        if ((string) ($values['list_label'] ?? '') !== '') $information[] = $text(esc_html((string) $values['list_label']), 'Listenbezeichnung');
        foreach ($values['items'] ?? [] as $item) {
            $information[] = $text('<ul><li>' . esc_html((string) $item['text']) . '</li></ul>', 'Listenpunkt');
        }
        if ((string) ($values['closing'] ?? '') !== '') $information[] = $text((string) $values['closing'], 'Abschlusstext');
        if ($information) $rows[] = $row($information);
        $heading((string) ($values['schedule_title'] ?? ''));
        $type = (string) ($values['event_type'] ?? 'schnuppertag');
        $signup = (string) ($values['signup_anchor'] ?? '');
        $rows[] = $loop('upcoming', in_array($type, ['general', 'schnuppertag'], true) ? $type : 'schnuppertag', $limit,
            (string) ($values['empty_message'] ?? ''), $signup !== '' ? (string) ($values['signup_label'] ?? '') : '',
            $signup, ($values['display'] ?? '') === 'cards');
    }
    return [bioco_import_divi_block('divi/section', ['module' => [
        'meta' => ['adminLabel' => $v('Veranstaltungen: native Liste')],
        'advanced' => ['htmlAttributes' => $v(['id' => (string) ($values['anchor'] ?? ''), 'class' => (string) ($values['className'] ?? '')])],
        'decoration' => ['sizing' => $v(['width' => '100%', 'maxWidth' => '1080px', 'alignment' => 'center']),
            'spacing' => $v(['padding' => ['top' => '40px', 'bottom' => '40px', 'left' => '20px', 'right' => '20px']])],
    ]], $rows)];
}
