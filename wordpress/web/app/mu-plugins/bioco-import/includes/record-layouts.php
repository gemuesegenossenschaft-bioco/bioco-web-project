<?php
/** Saved native group and calendar starters. No records or assignments are written. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/utility-layouts.php';
require_once dirname(__DIR__, 2) . '/bioco-core/includes/record-library.php';

function bioco_record_variable(string $field): string {
    return '$variable(' . wp_json_encode(['type' => 'content', 'value' => [
        'name' => $field, 'settings' => new stdClass(),
    ]], JSON_UNESCAPED_UNICODE) . ')$';
}

function bioco_record_layout_class(array $block, string $class): array {
    $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] = $class;
    return $block;
}

function bioco_record_layout_role(array $block, string $role): array {
    $block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][] = [
        'id' => 'bioco-role-' . substr(hash('sha256', $role), 0, 12),
        'name' => 'role', 'value' => $role, 'adminLabel' => 'Role',
    ];
    return $block;
}

function bioco_record_layout_column(array $modules, string $class = ''): array {
    return bioco_record_layout_class(bioco_import_divi_block('divi/column', [
        'module' => ['advanced' => ['type' => ['desktop' => ['value' => '4_4']]],
            'decoration' => ['sizing' => ['desktop' => ['value' => ['width' => '100%', 'maxWidth' => '100%']]]]],
    ], $modules), $class);
}

function bioco_record_layout_row(array $columns, string $class = ''): array {
    return bioco_record_layout_class(bioco_import_divi_block('divi/row', [
        'modulePreset' => [bioco_utility_preset('BIOCO Content Row')],
        'module' => ['advanced' => ['columnStructure' => ['desktop' => ['value' => implode(',', array_fill(0, count($columns), '4_4'))]]],
            'decoration' => ['sizing' => ['desktop' => ['value' => ['width' => '100%', 'maxWidth' => '1080px']]]]],
    ], $columns), $class);
}

function bioco_record_layout_loop(array $row, string $type, string $label, string $id): array {
    $row['attrs']['module']['advanced']['loop']['desktop']['value'] = [
        'enable' => 'on', 'queryType' => 'post_types', 'subTypes' => [['value' => $type, 'label' => $label]],
        'postPerPage' => 100, 'orderBy' => 'menu_order', 'order' => 'ASC', 'loopId' => $id,
    ];
    return $row;
}

function bioco_record_layout_section(array $rows, string $class = ''): array {
    return bioco_record_layout_class(bioco_import_divi_block('divi/section', [
        'modulePreset' => [bioco_utility_preset('BIOCO Standard Section')],
    ], $rows), $class);
}

function bioco_group_native_modules(bool $loop): array {
    $prefix = $loop ? 'loop_bioco_group_' : 'bioco_group_';
    $image = bioco_import_divi_block('divi/image', ['image' => [
        'innerContent' => ['desktop' => ['value' => ['src' => bioco_record_variable($prefix . 'image'),
            'alt' => bioco_record_variable($prefix . 'image_alt')]]],
    ]]);
    return [
        $image, bioco_utility_heading(bioco_record_variable($prefix . 'title'), $loop ? 'h3' : 'h1'),
        bioco_utility_text(bioco_record_variable($prefix . 'description')),
        bioco_utility_text(bioco_record_variable($prefix . 'contact')),
        bioco_record_layout_class(bioco_utility_button('Kontakt aufnehmen', bioco_record_variable($prefix . 'contact_url')), 'bioco-group-contact-action'),
    ];
}

/** Toggle accepts nested modules/groups, not section/row/column structures. */
function bioco_calendar_nested_groups(array $block): array {
    if (in_array($block['blockName'], ['divi/section', 'divi/row', 'divi/column'], true)) {
        $block['blockName'] = 'divi/group';
        // Structural presets and column types do not belong to a Group module.
        unset($block['attrs']['modulePreset'], $block['attrs']['module']['advanced']['columnStructure'],
            $block['attrs']['module']['advanced']['type']);
        if (empty($block['attrs']['module']['advanced'])) unset($block['attrs']['module']['advanced']);
    }
    $block['innerBlocks'] = array_map('bioco_calendar_nested_groups', $block['innerBlocks']);
    return $block;
}

/** A separate starter: never replace an editor's existing flat calendar. */
function bioco_calendar_disclosure(array $calendar): array {
    $table = bioco_calendar_nested_groups($calendar);
    $table['attrs']['module']['decoration']['layout']['desktop']['value']['display'] = 'block';
    $table['innerBlocks'][1]['attrs']['module']['advanced']['loop']['desktop']['value']['orderBy'] = 'none';
    $table['innerBlocks'][1]['attrs']['module']['advanced']['loop']['desktop']['value']['order'] = 'ascending';
    $table['innerBlocks'][1] = bioco_record_layout_class($table['innerBlocks'][1],
        'bioco-record-loop bioco-vegetable-loop bioco-calendar-data bioco-calendar-catalog-order');
    $toggle = bioco_record_layout_class(bioco_import_divi_block('divi/toggle', [
        'module' => ['meta' => ['adminLabel' => ['desktop' => ['value' => 'Saisonkalender aufklappen']]],
            'advanced' => ['open' => ['desktop' => ['value' => 'off']]],
            'decoration' => ['layout' => ['desktop' => ['value' => ['display' => 'block']]]]],
        'title' => ['innerContent' => ['desktop' => ['value' => 'Saisonkalender']],
            'decoration' => ['font' => ['font' => ['desktop' => ['value' => ['headingLevel' => 'h2']]]]]],
    ], [bioco_utility_text('<p>●: in Saison</p>'), $table]), 'bioco-editorial-toggle bioco-calendar-disclosure');
    return bioco_utility_section([$toggle]);
}

function bioco_record_layout_definitions(): array {
    $group = bioco_group_native_modules(true);
    $group[] = bioco_utility_button('Mehr zur Gruppe', bioco_record_variable('loop_bioco_group_url'));
    $group[] = bioco_record_layout_class(bioco_utility_text('<p>Zurzeit sind keine Gruppen verfügbar.</p>'), 'bioco-loop-empty');
    $groupRow = bioco_record_layout_loop(bioco_record_layout_row([bioco_record_layout_column($group)],
        'bioco-record-loop bioco-group-loop'), 'bioco_group', 'Gruppen', 'bioco-groups');
    $cards = bioco_record_layout_section([$groupRow]);
    $cards['attrs']['module']['decoration']['layout'] = [
        'desktop' => ['value' => ['display' => 'grid', 'gridColumnWidths' => 'manual',
            'gridTemplateColumns' => 'repeat(3, minmax(0, 1fr))', 'columnGap' => '28px', 'rowGap' => '28px']],
        'tablet' => ['value' => ['gridTemplateColumns' => 'minmax(0, 1fr)']],
        'phone' => ['value' => ['gridTemplateColumns' => 'minmax(0, 1fr)']],
    ];
    $detail = bioco_group_native_modules(false);
    $detail[] = bioco_utility_button('Zurück zu Mitmachen', home_url('/mitmachen/'));

    $months = [1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez'];
    $headers = [bioco_record_layout_role(bioco_record_layout_column([bioco_utility_text('Gemüse')], 'bioco-calendar-name'), 'columnheader')];
    $empty = bioco_record_layout_class(bioco_utility_text('<p>Zurzeit ist kein Saisonkalender verfügbar.</p>'), 'bioco-loop-empty');
    $cells = [bioco_record_layout_role(bioco_record_layout_column([
        bioco_utility_text(bioco_record_variable('loop_bioco_vegetable_name')), $empty,
    ], 'bioco-calendar-name'), 'rowheader')];
    foreach ($months as $month => $label) {
        $class = 'bioco-calendar-month-' . $month;
        $headers[] = bioco_record_layout_role(bioco_record_layout_column([bioco_utility_text($label)], $class), 'columnheader');
        $cells[] = bioco_record_layout_role(bioco_record_layout_column([
            bioco_utility_text(bioco_record_variable('loop_bioco_vegetable_month_' . $month)),
        ], $class), 'cell');
    }
    $header = bioco_record_layout_role(bioco_record_layout_row($headers, 'bioco-calendar-head'), 'row');
    $body = bioco_record_layout_role(bioco_record_layout_loop(bioco_record_layout_row($cells,
        'bioco-record-loop bioco-vegetable-loop bioco-calendar-data'), 'bioco_vegetable', 'Gemüse', 'bioco-vegetables'), 'row');
    foreach ([&$header, &$body] as &$row) {
        $row['attrs']['module']['decoration']['layout']['desktop']['value'] = [
            'display' => 'grid', 'gridColumnWidths' => 'manual', 'gridTemplateColumns' => 'minmax(180px, 2fr) repeat(12, minmax(54px, 1fr))',
            'columnGap' => '8px', 'alignItems' => 'center',
        ];
        $row['attrs']['module']['decoration']['sizing']['desktop']['value']['minWidth'] = '1000px';
    }
    unset($row);
    $calendar = bioco_record_layout_role(bioco_record_layout_section([$header, $body], 'bioco-native-calendar'), 'table');
    $calendar['attrs']['module']['decoration']['overflow']['desktop']['value'] = ['x' => 'auto', 'y' => 'visible'];
    return [
        'group-cards' => ['title' => 'BIOCO Gruppen: Karten', 'content' => serialize_blocks([$cards])],
        'group-detail' => ['title' => 'BIOCO Gruppe: Detailseite', 'content' => serialize_blocks([
            bioco_record_layout_section([bioco_record_layout_row([bioco_record_layout_column($detail)])]),
        ])],
        'calendar' => ['title' => 'BIOCO Saisonkalender', 'content' => serialize_blocks([
            bioco_utility_section([bioco_utility_heading('Saisonkalender', 'h2'), bioco_utility_text('<p>●: in Saison</p>')]), $calendar,
        ])],
        'calendar-collapsible' => ['title' => 'BIOCO Saisonkalender: aufklappbar',
            'content' => serialize_blocks([bioco_calendar_disclosure($calendar)])],
    ];
}

/** Missing library entries only; existing drafts and editorial changes are kept. */
function bioco_import_record_layouts(bool $apply = false): array {
    if ($apply && !current_user_can('manage_options')) throw new RuntimeException('Use --user=<administrator>.');
    if (!post_type_exists('et_pb_layout') || !taxonomy_exists('layout_type')) throw new RuntimeException('Divi library is unavailable.');
    $report = []; $missing = [];
    foreach (bioco_record_layout_definitions() as $key => $definition) {
        $existing = bioco_record_library_posts($key);
        if (count($existing) > 1) throw new RuntimeException('Duplicate record layout: ' . $key);
        $report[$key] = ['key' => $key, 'action' => $existing ? 'preserve-existing' : 'would-create'];
        if (!$existing) $missing[$key] = $definition;
    }
    if (!$apply) return array_values($report);
    foreach ($missing as $key => $definition) {
        $id = wp_insert_post(wp_slash(['post_type' => 'et_pb_layout', 'post_status' => 'publish',
            'post_name' => 'bioco-record-' . $key, 'post_title' => $definition['title'], 'post_content' => $definition['content'],
            'meta_input' => ['_et_pb_use_builder' => 'on', '_et_pb_built_for_post_type' => $key === 'group-detail' ? 'bioco_group' : 'page', '_et_pb_page_layout' => 'et_full_width_page'],
        ]), true);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        if (!$id) throw new RuntimeException('Could not create record layout: ' . $key);
        $terms = wp_set_object_terms($id, ['layout'], 'layout_type');
        if (is_wp_error($terms)) { wp_delete_post($id, true); throw new RuntimeException($terms->get_error_message()); }
        $report[$key]['action'] = 'created';
    }
    return array_values($report);
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('bioco record-layouts', function ($args, $flags) {
        try { WP_CLI::success(wp_json_encode(bioco_import_record_layouts(isset($flags['apply'])), JSON_UNESCAPED_UNICODE)); }
        catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
    }, ['shortdesc' => 'Preview missing native group/calendar library layouts. Published group-detail layout fills only blank group record bodies.',
        'synopsis' => [['type' => 'flag', 'name' => 'apply', 'optional' => true]], 'when' => 'after_wp_load']);
}
