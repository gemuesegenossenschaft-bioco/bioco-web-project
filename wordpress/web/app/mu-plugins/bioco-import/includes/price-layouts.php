<?php
/** Native price starters. Importing never assigns templates or rewrites pages. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/utility-layouts.php';

function bioco_price_variable(string $field): string {
    return '$variable(' . wp_json_encode(['type' => 'content', 'value' => [
        'name' => $field, 'settings' => new stdClass(),
    ]], JSON_UNESCAPED_UNICODE) . ')$';
}

function bioco_price_layout_class(array $block, string $class): array {
    $block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] = $class;
    return $block;
}

function bioco_price_layout_row(array $modules, string $class = ''): array {
    $column = bioco_import_divi_block('divi/column', [
        'module' => ['advanced' => ['type' => ['desktop' => ['value' => '4_4']]]],
    ], $modules);
    return bioco_price_layout_class(bioco_import_divi_block('divi/row', [
        'modulePreset' => [bioco_utility_preset('BIOCO Content Row')],
        'module' => ['advanced' => ['columnStructure' => ['desktop' => ['value' => '4_4']]],
            'decoration' => ['sizing' => ['desktop' => ['value' => ['width' => '100%', 'maxWidth' => '100%']]]]],
    ], [$column]), $class);
}

function bioco_price_layout_loop(array $modules, string $id): array {
    $empty = bioco_price_layout_class(bioco_utility_text('<p>Zurzeit sind keine Abopreise verfügbar.</p>'), 'bioco-loop-empty');
    $row = bioco_price_layout_row(array_merge($modules, [$empty]), 'bioco-record-loop bioco-price-loop');
    $row['attrs']['module']['advanced']['loop']['desktop']['value'] = [
        'enable' => 'on', 'queryType' => 'post_types',
        'subTypes' => [['value' => 'bioco_price', 'label' => 'Preise']],
        'postPerPage' => 20, 'orderBy' => 'menu_order', 'order' => 'ASC', 'loopId' => $id,
    ];
    return $row;
}

function bioco_price_layout_section(array $rows, string $class = ''): array {
    return bioco_price_layout_class(bioco_import_divi_block('divi/section', [
        'modulePreset' => [bioco_utility_preset('BIOCO Standard Section')],
    ], $rows), $class);
}

function bioco_price_layout_definitions(): array {
    $table = [bioco_utility_heading(bioco_price_variable('loop_bioco_price_table_label'), 'h3')];
    foreach (['price' => 'Jahresabo (CHF)', 'shares' => 'Anteilsscheine', 'share_cost' => 'Anteilsscheine Kosten (CHF)',
        'work' => 'Arbeitseinsätze', 'persons' => 'Personen'] as $field => $label) {
        $table[] = bioco_utility_text('<p>' . $label . '</p>');
        $table[] = bioco_utility_text(bioco_price_variable('loop_bioco_price_' . $field));
    }
    $tableSection = bioco_price_layout_section([bioco_price_layout_loop($table, 'bioco-price-table')]);
    $tableSection['attrs']['module']['decoration']['layout'] = [
        'desktop' => ['value' => ['display' => 'grid', 'gridColumnWidths' => 'manual',
            'gridTemplateColumns' => 'repeat(3, minmax(0, 1fr))', 'columnGap' => '28px', 'rowGap' => '28px']],
        'tablet' => ['value' => ['gridTemplateColumns' => 'minmax(0, 1fr)']],
        'phone' => ['value' => ['gridTemplateColumns' => 'minmax(0, 1fr)']],
    ];
    $choice = bioco_utility_button(bioco_price_variable('loop_bioco_price_name'), bioco_price_variable('loop_bioco_price_select_url'));
    $summary = [];
    foreach (['name' => 'Gewähltes Abo', 'price' => 'Jahresabo (CHF)', 'work' => 'Arbeitseinsätze',
        'shares' => 'Anteilsscheine', 'additional' => 'Zusätzliche Anteilsscheine',
        'share-cost' => 'Anteilsscheine Kosten (CHF)', 'total' => 'Total (CHF)'] as $field => $label) {
        $summary[] = bioco_utility_text('<p>' . $label . '</p>');
        $summary[] = bioco_price_layout_class(bioco_utility_text('0'), 'bioco-price-value-' . $field);
    }
    $summary[] = bioco_utility_button('+ Anteilsschein', '#bioco-share-add');
    $summary[] = bioco_price_layout_class(bioco_utility_button('− Anteilsschein', '#bioco-share-remove'), 'bioco-price-remove');
    $summary[] = bioco_price_layout_class(bioco_utility_text('<p>Wähle ein Abo oder zusätzliche Anteilsscheine.</p>'), 'bioco-price-no-selection');
    $summary[] = bioco_price_layout_class(bioco_utility_button('Jetzt mitmachen', home_url('/anmeldung/')), 'bioco-price-signup');
    $calculator = bioco_price_layout_section([
        bioco_price_layout_row([bioco_utility_heading('Abo und Anteilsscheine berechnen', 'h2')]),
        bioco_price_layout_loop([$choice], 'bioco-price-calculator'),
        bioco_price_layout_row([bioco_utility_button('Kein Abo', '#bioco-price-kein')]),
        bioco_price_layout_row($summary),
    ], 'bioco-price-calculator');
    return [
        'table' => ['title' => 'BIOCO Abo-Preise', 'content' => serialize_blocks([$tableSection])],
        'calculator' => ['title' => 'BIOCO Abo-Rechner', 'content' => serialize_blocks([$calculator])],
    ];
}

/** Create only missing library starters; authored or older layouts are preserved. */
function bioco_import_price_layouts(bool $apply = false): array {
    if ($apply && !current_user_can('manage_options')) throw new RuntimeException('Use --user=<administrator>.');
    if (!post_type_exists('et_pb_layout') || !taxonomy_exists('layout_type')) throw new RuntimeException('Divi library is unavailable.');
    $report = []; $missing = [];
    foreach (bioco_price_layout_definitions() as $key => $definition) {
        $existing = get_posts(['post_type' => 'et_pb_layout', 'name' => 'bioco-price-' . $key,
            'post_status' => array_values(get_post_stati()), 'posts_per_page' => 2]);
        if (count($existing) > 1) throw new RuntimeException('Duplicate price layout: ' . $key);
        $report[$key] = ['key' => $key, 'action' => $existing ? 'preserve-existing' : 'would-create'];
        if (!$existing) $missing[$key] = $definition;
    }
    if (!$apply) return array_values($report);
    foreach ($missing as $key => $definition) {
        $id = wp_insert_post(wp_slash(['post_type' => 'et_pb_layout', 'post_status' => 'publish',
            'post_name' => 'bioco-price-' . $key, 'post_title' => $definition['title'], 'post_content' => $definition['content'],
            'meta_input' => ['_et_pb_use_builder' => 'on', '_et_pb_built_for_post_type' => 'page', '_et_pb_page_layout' => 'et_full_width_page'],
        ]), true);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        if (!$id) throw new RuntimeException('Could not create price layout: ' . $key);
        $terms = wp_set_object_terms($id, ['layout'], 'layout_type');
        if (is_wp_error($terms)) { wp_delete_post($id, true); throw new RuntimeException($terms->get_error_message()); }
        $report[$key]['action'] = 'created';
    }
    return array_values($report);
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('bioco price-layouts', function ($args, $flags) {
        try { WP_CLI::success(wp_json_encode(bioco_import_price_layouts(isset($flags['apply'])), JSON_UNESCAPED_UNICODE)); }
        catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
    }, ['shortdesc' => 'Preview missing native Divi price starters. Existing pages and layouts are preserved.',
        'synopsis' => [['type' => 'flag', 'name' => 'apply', 'optional' => true]], 'when' => 'after_wp_load']);
}
