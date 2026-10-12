<?php
/** Ordinary Divi depot cards. The map and public record catalog stay separate. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/record-layouts.php';

function bioco_depot_card_blocks(array $copy = []): array {
    $copy = array_replace(['intro' => 'Abholzeiten und Standorte', 'locations_heading' => 'Standorte',
        'route_label' => 'Route planen →', 'empty_message' => 'Noch keine Standorte hinterlegt.',
        'card_order' => 'name_first'], $copy);
    foreach (['intro', 'locations_heading', 'route_label', 'empty_message'] as $key) {
        if (!is_string($copy[$key])) throw new RuntimeException('Invalid depot card text: ' . $key);
    }
    if (!in_array($copy['card_order'], ['name_first', 'address_first'], true)) throw new RuntimeException('Invalid depot card order.');
    $name = bioco_utility_heading(bioco_record_variable('loop_bioco_depot_name'), 'h3');
    $address = bioco_utility_text(bioco_record_variable('loop_bioco_depot_address'));
    $fields = $copy['card_order'] === 'address_first' ? [$address, $name] : [$name, $address];
    $fields[] = bioco_record_layout_class(bioco_utility_button(
        bioco_record_variable('loop_bioco_depot_website_label'), bioco_record_variable('loop_bioco_depot_website')),
        'bioco-depot-website-action');
    $fields[] = bioco_record_layout_class(bioco_utility_button(
        $copy['route_label'], bioco_record_variable('loop_bioco_depot_route_url')), 'bioco-depot-route-action');
    $fields[] = bioco_record_layout_class(bioco_utility_text('<p>' . esc_html($copy['empty_message']) . '</p>'), 'bioco-loop-empty');
    $loop = bioco_record_layout_loop(bioco_record_layout_row([bioco_record_layout_column($fields)],
        'bioco-record-loop bioco-depot-loop'), 'bioco_depot', 'Depots', 'bioco-depots');
    $cards = bioco_record_layout_section([$loop], 'bioco-native-depot-cards');
    $cards['attrs']['module']['decoration']['layout'] = [
        'desktop' => ['value' => ['display' => 'grid', 'gridColumnWidths' => 'manual',
            'gridTemplateColumns' => 'repeat(3, minmax(0, 1fr))', 'columnGap' => '28px', 'rowGap' => '28px']],
        'tablet' => ['value' => ['gridTemplateColumns' => 'repeat(2, minmax(0, 1fr))']],
        'phone' => ['value' => ['gridTemplateColumns' => 'minmax(0, 1fr)']],
    ];
    $intro = bioco_utility_text('<p>' . esc_html($copy['intro']) . '</p>');
    $heading = bioco_utility_heading($copy['locations_heading'], 'h2');
    return [bioco_utility_section([$intro]), bioco_utility_section([$heading]), $cards];
}

function bioco_depot_layout_definition(): array {
    return ['title' => 'BIOCO Depots: Karten', 'content' => serialize_blocks(bioco_depot_card_blocks())];
}

function bioco_import_depot_layout(bool $apply = false): array {
    if ($apply && !current_user_can('manage_options')) throw new RuntimeException('Use --user=<administrator>.');
    if (!post_type_exists('et_pb_layout') || !taxonomy_exists('layout_type')) throw new RuntimeException('Divi library is unavailable.');
    $existing = bioco_record_library_posts('depot-cards');
    if (count($existing) > 1) throw new RuntimeException('Duplicate depot card layout.');
    if ($existing) return ['action' => 'preserve-existing', 'id' => $existing[0]->ID];
    if (!$apply) return ['action' => 'would-create'];
    $definition = bioco_depot_layout_definition();
    $id = wp_insert_post(wp_slash(['post_type' => 'et_pb_layout', 'post_status' => 'publish',
        'post_name' => 'bioco-record-depot-cards', 'post_title' => $definition['title'], 'post_content' => $definition['content'],
        'meta_input' => ['_et_pb_use_builder' => 'on', '_et_pb_built_for_post_type' => 'page', '_et_pb_page_layout' => 'et_full_width_page'],
    ]), true);
    if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
    if (!$id) throw new RuntimeException('Could not create depot card layout.');
    $terms = wp_set_object_terms($id, ['layout'], 'layout_type');
    if (is_wp_error($terms)) { wp_delete_post($id, true); throw new RuntimeException($terms->get_error_message()); }
    return ['action' => 'created', 'id' => $id];
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('bioco depot-layout', function ($args, $flags) {
        try { WP_CLI::line(wp_json_encode(bioco_import_depot_layout(isset($flags['apply'])), JSON_UNESCAPED_UNICODE)); }
        catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
    }, ['shortdesc' => 'Preview/create the unassigned native depot card layout.',
        'synopsis' => [['type' => 'flag', 'name' => 'apply', 'optional' => true]], 'when' => 'after_wp_load']);
}
