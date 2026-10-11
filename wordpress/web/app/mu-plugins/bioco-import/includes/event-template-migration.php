<?php
/** Separate, guarded assignment of the verified native event body. Never imports records. */
if (!defined('ABSPATH')) exit;

function bioco_import_event_template_snapshot(int $builder): array {
    global $wpdb;
    $templates = array_map('intval', get_post_meta($builder, '_et_template', false));
    $ids = [$builder];
    foreach ($templates as $id) {
        $ids[] = $id;
        foreach (['header', 'body', 'footer'] as $slot) {
            $layout = (int) get_post_meta($id, '_et_' . $slot . '_layout_id', true);
            if ($layout) $ids[] = $layout;
        }
    }
    $ids = array_values(array_unique($ids));
    sort($ids);
    $list = implode(',', $ids);
    return ['builder' => $builder, 'ids' => $ids, 'templates' => $templates,
        'posts' => $wpdb->get_results("SELECT ID, post_type, post_status, post_title, post_content FROM {$wpdb->posts} WHERE ID IN ($list) ORDER BY ID", ARRAY_A),
        'meta' => $wpdb->get_results("SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($list) ORDER BY meta_id", ARRAY_A)];
}

/** One SQL statement checks complete captured rows and appends/removes only our registry row. */
function bioco_import_event_template_guard(array $state): string {
    global $wpdb;
    $ids = implode(',', array_map('intval', $state['ids']));
    if (!$ids || count($state['posts']) !== count($state['ids'])) throw new RuntimeException('Missing template or layout record.');
    $guards = [];
    $guards[] = $wpdb->prepare("NOT EXISTS (SELECT 1 FROM {$wpdb->options} WHERE option_name = %s)", 'et_tb_templates_backup_' . (int) $state['builder']);
    foreach (['posts' => ['table' => $wpdb->posts, 'id' => 'ID'], 'meta' => ['table' => $wpdb->postmeta, 'id' => 'post_id']] as $kind => $spec) {
        $rows = [];
        foreach ($state[$kind] as $row) {
            $fields = [];
            foreach ($row as $key => $value) {
                // Column names come from the fixed SELECT above, never from a backup file.
                if (!in_array($key, ['ID', 'post_type', 'post_status', 'post_title', 'post_content', 'meta_id', 'post_id', 'meta_key', 'meta_value'], true)) throw new RuntimeException('Invalid snapshot column.');
                $fields[] = $wpdb->prepare("BINARY `$key` = BINARY %s", (string) $value);
            }
            $rows[] = '(' . implode(' AND ', $fields) . ')';
        }
        $scope = $spec['id'] . " IN ($ids)";
        $guards[] = "(SELECT COUNT(*) FROM {$spec['table']} WHERE $scope) = " . count($rows);
        $guards[] = "NOT EXISTS (SELECT 1 FROM {$spec['table']} WHERE $scope AND NOT (" . ($rows ? implode(' OR ', $rows) : '0') . '))';
    }
    return implode(' AND ', $guards);
}

function bioco_import_event_template_assignment(array $state, int $template, bool $remove = false): void {
    global $wpdb;
    $guard = bioco_import_event_template_guard($state);
    if ($remove) {
        $sql = $wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s AND BINARY meta_value = BINARY %s AND ", $state['builder'], '_et_template', (string) $template);
        // Materialization avoids MySQL's restriction on selecting a DELETE target.
        $guard = str_replace("FROM {$wpdb->postmeta} WHERE", "FROM (SELECT * FROM {$wpdb->postmeta} LIMIT 18446744073709551615) AS saved_meta WHERE", $guard);
    } else {
        $sql = $wpdb->prepare("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT %d, %s, %s FROM DUAL WHERE ", $state['builder'], '_et_template', (string) $template);
    }
    if ($wpdb->query($sql . $guard) !== 1) throw new RuntimeException('Concurrent template edit; assignment write refused.');
    clean_post_cache($state['builder']);
}

function bioco_import_event_template_plan(): array {
    foreach (['et_theme_builder_get_theme_builder_post_id', 'et_theme_builder_store_template', 'et_theme_builder_insert_layout'] as $function) {
        if (!function_exists($function)) throw new RuntimeException('Native Divi Theme Builder is required.');
    }
    $builder = (int) et_theme_builder_get_theme_builder_post_id(true, false);
    if (!$builder || get_option('et_tb_templates_backup_' . $builder, false) !== false) throw new RuntimeException('Missing live builder or a native save is in progress.');
    $state = bioco_import_event_template_snapshot($builder);
    bioco_import_event_template_guard($state);
    $existing = 0;
    foreach ($state['templates'] as $id) {
        if (get_post_meta($id, '_et_default', true) === '1') continue;
        // Refuse an existing custom assignment instead of guessing its priority or overwriting it.
        if (get_post_meta($id, '_bioco_event_template', true) !== '1'
            || get_post_meta($id, '_et_use_on', false) !== ['singular:post_type:event:all']
            || get_post_meta($id, '_et_exclude_from', false)
            || get_post_status($id) !== 'publish'
            || get_post_meta($id, '_et_enabled', true) !== '1'
            || get_post_meta($id, '_et_body_layout_enabled', true) !== '1'
            || get_post_type((int) get_post_meta($id, '_et_body_layout_id', true)) !== 'et_body_layout'
            || get_post_status((int) get_post_meta($id, '_et_body_layout_id', true)) !== 'publish'
            || $existing) throw new RuntimeException('Existing custom template assignment requires a separate migration.');
        $existing = $id;
    }
    $content = serialize_blocks(bioco_import_event_template_blocks());
    $plan = ['site' => home_url('/'), 'state' => $state, 'content' => $content, 'existing' => $existing];
    $plan['fingerprint'] = hash('sha256', wp_json_encode($plan));
    return $plan;
}

/** The parent must already be a private directory under the host's backups folder. */
function bioco_import_event_template_backup_path(string $path): string {
    $parent = realpath(dirname($path));
    $root = realpath(dirname(rtrim(ABSPATH, '/')) . '/backups');
    if (!$parent || !$root || ($parent !== $root && !str_starts_with($parent . '/', $root . '/'))
        || (fileperms($parent) & 0077) !== 0 || basename($path) === '.' || basename($path) === '..') {
        throw new RuntimeException('Backup requires an existing private directory outside the WordPress root.');
    }
    return $parent . '/' . basename($path);
}

function bioco_import_event_template_apply(array $plan, string $expected, string $backup): array {
    global $wpdb;
    if (!hash_equals($plan['fingerprint'], $expected)) throw new RuntimeException('Preview fingerprint changed.');
    if ($plan['existing']) return ['template' => $plan['existing'], 'already_applied' => true];
    if (!current_user_can('edit_others_posts')) throw new RuntimeException('An authorized WordPress user is required.');
    $backup = bioco_import_event_template_backup_path($backup);
    $stream = @fopen($backup, 'x');
    if (!$stream) throw new RuntimeException('Backup already exists or cannot be created.');
    if (!chmod($backup, 0600)) { fclose($stream); throw new RuntimeException('Cannot make backup private.'); }
    fclose($stream);
    $receipt = ['version' => 1, 'before' => $plan, 'body' => 0, 'template' => 0, 'applied' => false];
    $save = static function () use (&$receipt, $backup): void {
        $json = wp_json_encode($receipt);
        $next = $backup . '.next';
        $stream = @fopen($next, 'x');
        if (!$stream) throw new RuntimeException('Cannot prepare migration receipt.');
        try {
            if (!chmod($next, 0600) || fwrite($stream, $json) !== strlen($json) || !fflush($stream)) throw new RuntimeException('Cannot persist migration receipt.');
        } finally { fclose($stream); }
        if (!rename($next, $backup)) throw new RuntimeException('Cannot replace migration receipt.');
    };
    $save();
    $body = et_theme_builder_insert_layout(['post_type' => 'et_body_layout', 'post_title' => 'Veranstaltungen: Detailvorlage', 'post_content' => wp_slash($plan['content'])]);
    if (is_wp_error($body) || !$body) throw new RuntimeException('Cannot create unassigned native body.');
    $receipt['body'] = (int) $body;
    $save();
    $template = et_theme_builder_store_template($plan['state']['builder'], [
        'title' => 'Veranstaltungen', 'default' => '0', 'enabled' => '1', 'autogenerated_title' => '0',
        'layouts' => ['header' => ['id' => 0, 'enabled' => true], 'body' => ['id' => $body, 'enabled' => true], 'footer' => ['id' => 0, 'enabled' => true]],
        'use_on' => ['singular:post_type:event:all'], 'exclude_from' => [],
    ], false);
    if (!$template) throw new RuntimeException('Cannot create unassigned event template.');
    $receipt['template'] = (int) $template;
    update_post_meta($template, '_bioco_event_template', '1');
    $save();
    if (get_option('et_tb_templates_backup_' . $plan['state']['builder'], false) !== false) throw new RuntimeException('Native template save is in progress.');
    bioco_import_event_template_assignment($plan['state'], (int) $template);
    $receipt['applied'] = true;
    $receipt['after'] = bioco_import_event_template_snapshot($plan['state']['builder']);
    $save();
    return ['template' => (int) $template, 'body' => (int) $body, 'backup' => $backup];
}

/** Restore only the appended assignment; keep both layouts available for recovery. */
function bioco_import_event_template_restore(string $backup): array {
    $backup = bioco_import_event_template_backup_path($backup);
    if (is_link($backup) || (fileperms($backup) & 0077) !== 0) throw new RuntimeException('Receipt must be a private regular file.');
    $receipt = json_decode(file_get_contents($backup), true, 512, JSON_THROW_ON_ERROR);
    if (($receipt['version'] ?? 0) !== 1 || ($receipt['before']['site'] ?? '') !== home_url('/') || empty($receipt['applied']) || empty($receipt['after'])) throw new RuntimeException('Receipt is not an applied migration for this site.');
    bioco_import_event_template_assignment($receipt['after'], (int) $receipt['template'], true);
    return ['restored' => true, 'template' => $receipt['template'], 'body' => $receipt['body']];
}
