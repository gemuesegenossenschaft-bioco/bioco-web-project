<?php
/** The release owner invokes these explicit, private-preview operations. */
if (!defined('ABSPATH')) exit;

function bioco_import_form_read_preview(string $path): array {
    $path = bioco_import_event_template_backup_path($path);
    if (!is_file($path) || is_link($path) || (fileperms($path) & 0077) !== 0) throw new RuntimeException('Preview must be a private regular file.');
    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function bioco_import_form_write_preview(string $path, array $plan): void {
    $path = bioco_import_event_template_backup_path($path);
    $stream = @fopen($path, 'x');
    if (!$stream) throw new RuntimeException('Preview exists or cannot be created.');
    try {
        $json = wp_json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!chmod($path, 0600) || fwrite($stream, $json) !== strlen($json) || !fflush($stream)) throw new RuntimeException('Cannot persist private preview.');
    } finally { fclose($stream); }
}

function bioco_import_form_operator(array $args, array $assoc): void {
    global $wpdb;
    try {
        $action = $assoc['action'] ?? 'preview';
        if (!in_array($action, ['preview', 'apply', 'restore'], true)) throw new RuntimeException('Use preview, apply or restore.');
        if ($action === 'preview') {
            $options = bioco_import_form_options();
            if (isset($assoc['groups'])) {
                $plan = bioco_import_form_retirement_plan(explode(',', $assoc['groups']), bioco_import_form_inventory(), $options, home_url('/'));
            } else {
                $id = $assoc['post'] ?? '';
                if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) throw new RuntimeException('Select one --post ID.');
                $record = $wpdb->get_row($wpdb->prepare("SELECT ID, post_type, post_status, post_title, post_content, post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", (int) $id), ARRAY_A);
                if (!$record) throw new RuntimeException('Selected record does not exist.');
                $record['ID'] = (int) $record['ID'];
                $plan = bioco_import_form_plan($record, home_url('/'), $options);
            }
            bioco_import_form_write_preview($assoc['out'] ?? '', $plan);
            WP_CLI::line(wp_json_encode(['fingerprint' => $plan['fingerprint'], 'kind' => $plan['kind'], 'changed' => $plan['changed'], 'report' => $plan['report'] ?? []]));
            return;
        }
        if (!current_user_can('edit_others_posts') || !current_user_can('manage_options')) throw new RuntimeException('Use an authorized WordPress administrator.');
        $plan = bioco_import_form_read_preview($assoc['preview'] ?? '');
        $expected = $assoc['expect'] ?? '';
        $restore = $action === 'restore';
        $sql = ($plan['kind'] ?? '') === 'form-retirement'
            ? bioco_import_form_retirement_sql($plan, $expected, $restore)
            : bioco_import_form_sql($plan, $expected, $restore);
        if ($sql === '') { WP_CLI::success('Preview contains no changes.'); return; }
        if ($wpdb->query($sql) !== 1) throw new RuntimeException('Concurrent edit or already-applied state; guarded write refused.');
        if ($plan['kind'] === 'form-content') clean_post_cache($plan['before']['ID']);
        else {
            wp_cache_delete('bioco_divi_form_message_groups', 'options');
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
        }
        WP_CLI::success($restore ? 'Selected preview restored.' : 'Selected preview applied.');
    } catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
}

WP_CLI::add_command('bioco form-adoption', 'bioco_import_form_operator');
