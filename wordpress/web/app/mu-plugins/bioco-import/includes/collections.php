<?php
/**
 * Collections import: event + bioco_group CPT posts, with ACF fields set via
 * update_field() (page-level fields, not blocks — these two post types are
 * plain WP posts, never rendered as Gutenberg block markup).
 *
 * Both sources are OPTIONAL and default to "skip with a clear log line",
 * because this importer has no server-side credentials to reach ProcessWire
 * itself; the operator exports the live API responses first (the WP-CLI host
 * can reach https://cms.bioco.ch, this dev environment cannot):
 *   curl https://cms.bioco.ch/api/content/events    > events.json
 *   curl https://cms.bioco.ch/api/content/aktuelles > aktuelles.json   (optional, merged in too)
 * then re-run with --events-json=events.json (a file path OR an http(s) URL
 * both work). Field semantics follow site/templates/api-events.php.
 *
 * Groups have no equivalent live PW endpoint today (see CLAUDE.md's API
 * list) — --groups-json accepts a hand-authored JSON array of
 * {title, text, image, contact} objects (or {"groups": [...]}).
 */

if (!defined('ABSPATH')) exit;

// Reads JSON from a local file path or an http(s) URL. Returns a decoded
// array, or a WP_Error explaining what went wrong.
function bioco_import_fetch_json_source($source) {
    if (preg_match('#^https?://#i', $source)) {
        $resp = wp_remote_get($source, ['timeout' => 20]);
        if (is_wp_error($resp)) return $resp;
        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('bioco_import_http', "HTTP {$code} beim Abruf von {$source}.");
        }
        $body = wp_remote_retrieve_body($resp);
    } else {
        if (!file_exists($source)) {
            return new WP_Error('bioco_import_file_missing', "Datei nicht gefunden: {$source}");
        }
        $body = file_get_contents($source);
    }
    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        return new WP_Error('bioco_import_bad_json', "Ungültiges JSON in {$source} (" . json_last_error_msg() . ').');
    }
    return $data;
}

// event_date is stored return_format 'Y-m-d H:i:s' — parse whatever the
// export gives us (ISO-8601 from api-events.php's date() output) and
// reformat as a local wall-clock string (not UTC-shifted; the source is
// already a wall-clock time, not an instant).
function bioco_import_event_field_plan(array $item) {
    $plan = [];
    if (!empty($item['startDate'])) {
        $ts = strtotime((string) $item['startDate']);
        if ($ts) $plan['event_date'] = date('Y-m-d H:i:s', $ts);
    }
    if (!empty($item['status']) && in_array($item['status'], ['upcoming', 'past'], true)) {
        $plan['event_status'] = $item['status'];
    }
    if (!empty($item['eventType']) && in_array($item['eventType'], ['general', 'schnuppertag'], true)) {
        $plan['event_type'] = $item['eventType'];
    }
    if (!empty($item['description'])) $plan['event_summary'] = $item['description'];
    if (!empty($item['signupNotes'])) $plan['event_signup_notes'] = $item['signupNotes'];
    return $plan;
}

function bioco_import_write_acf_fields($postId, array $fieldPlan, $mode, $force, $label, array &$report) {
    if (!function_exists('update_field') || !function_exists('get_field')) {
        bioco_import_report_row($report, $label, '', '', 'error', 'ACF (update_field/get_field) nicht verfügbar.');
        return;
    }
    foreach ($fieldPlan as $field => $value) {
        if ($mode !== 'apply') {
            bioco_import_report_row($report, $label, '', $field, 'update', 'WÜRDE: setzen auf "' . bioco_import_excerpt((string) $value) . '".');
            continue;
        }
        $current = get_field($field, $postId);
        if ($current !== null && $current !== '' && !$force) {
            bioco_import_report_row($report, $label, '', $field, 'skip-existing', 'Bereits gesetzt — CMS gewinnt (--force zum Überschreiben).');
            continue;
        }
        update_field($field, $value, $postId);
        bioco_import_report_row($report, $label, '', $field, 'update', 'Gesetzt auf "' . bioco_import_excerpt((string) $value) . '".');
    }
}

function bioco_import_import_event_item(array $item, $mode, $force, array &$report) {
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        bioco_import_report_row($report, '(events)', '', '', 'warn', 'Event-Eintrag ohne title übersprungen.');
        return;
    }
    $slug = sanitize_title($title);
    $label = "event:{$slug}";
    $existing = get_posts(['post_type' => 'event', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1]);
    $post = $existing ? $existing[0] : null;
    $content = (string) ($item['fullDescription'] ?? ($item['description'] ?? ''));

    if (!$post) {
        if ($mode === 'apply') {
            $postId = wp_insert_post(['post_type' => 'event', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content], true);
            if (is_wp_error($postId)) {
                bioco_import_report_row($report, $label, '', '', 'error', 'Event konnte nicht angelegt werden: ' . $postId->get_error_message());
                return;
            }
            $post = get_post($postId);
            bioco_import_report_row($report, $label, '', '', 'create', 'Event angelegt (post_id=' . $postId . ').');
        } else {
            bioco_import_report_row($report, $label, '', '', 'create', 'WÜRDE: Event anlegen.');
        }
    } else {
        bioco_import_report_row($report, $label, '', '', 'ok-equal', 'Event existiert bereits (post_id=' . $post->ID . ') — Titel/Beitragsinhalt unverändert (CMS gewinnt, --force zum Überschreiben).');
        if ($force && $mode === 'apply') {
            wp_update_post(['ID' => $post->ID, 'post_title' => $title, 'post_content' => $content]);
            bioco_import_report_row($report, $label, '', '', 'update', 'FORCE: Titel/Beitragsinhalt aktualisiert.');
        }
    }

    $fieldPlan = bioco_import_event_field_plan($item);
    if ($post) {
        bioco_import_write_acf_fields($post->ID, $fieldPlan, $mode, $force, $label, $report);
    } else {
        foreach ($fieldPlan as $field => $value) {
            bioco_import_report_row($report, $label, '', $field, 'update', 'WÜRDE: setzen (nach Seiten-Erstellung).');
        }
    }

    if (empty($item['cardImage'])) return;
    $imageUrl = is_array($item['cardImage']) ? (string) ($item['cardImage']['url'] ?? '') : (string) $item['cardImage'];
    if ($imageUrl === '') return;
    if ($mode !== 'apply' || !$post) {
        bioco_import_report_row($report, $label, '', 'card_image', 'update', 'WÜRDE: Bild importieren von ' . $imageUrl . '.');
        return;
    }
    $attachmentId = bioco_import_resolve_attachment_for_url($imageUrl, $mode);
    if (!$attachmentId) {
        bioco_import_report_row($report, $label, '', 'card_image', 'error', 'Bild-Import fehlgeschlagen von ' . $imageUrl . '.');
        return;
    }
    if (function_exists('update_field')) {
        update_field('card_image', $attachmentId, $post->ID);
        bioco_import_report_row($report, $label, '', 'card_image', 'update', 'Bild gesetzt (attachment_id=' . $attachmentId . ').');
    }
}

function bioco_import_import_group_item(array $item, $mode, $force, array &$report) {
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        bioco_import_report_row($report, '(groups)', '', '', 'warn', 'Gruppen-Eintrag ohne title übersprungen.');
        return;
    }
    $slug = sanitize_title($title);
    $label = "group:{$slug}";
    $existing = get_posts(['post_type' => 'bioco_group', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1]);
    $post = $existing ? $existing[0] : null;

    if (!$post) {
        if ($mode === 'apply') {
            $postId = wp_insert_post(['post_type' => 'bioco_group', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug], true);
            if (is_wp_error($postId)) {
                bioco_import_report_row($report, $label, '', '', 'error', 'Gruppe konnte nicht angelegt werden: ' . $postId->get_error_message());
                return;
            }
            $post = get_post($postId);
            bioco_import_report_row($report, $label, '', '', 'create', 'Gruppe angelegt (post_id=' . $postId . ').');
        } else {
            bioco_import_report_row($report, $label, '', '', 'create', 'WÜRDE: Gruppe anlegen.');
        }
    } else {
        bioco_import_report_row($report, $label, '', '', 'ok-equal', 'Gruppe existiert bereits (post_id=' . $post->ID . ') — CMS gewinnt.');
    }

    $fieldPlan = [];
    if (!empty($item['text'])) $fieldPlan['group_text'] = (string) $item['text'];
    if (!empty($item['contact'])) $fieldPlan['group_contact'] = (string) $item['contact'];
    if ($post) {
        bioco_import_write_acf_fields($post->ID, $fieldPlan, $mode, $force, $label, $report);
    } else {
        foreach ($fieldPlan as $field => $value) {
            bioco_import_report_row($report, $label, '', $field, 'update', 'WÜRDE: setzen (nach Seiten-Erstellung).');
        }
    }

    if (empty($item['image'])) return;
    $imageUrl = (string) $item['image'];
    if ($mode !== 'apply' || !$post) {
        bioco_import_report_row($report, $label, '', 'group_image', 'update', 'WÜRDE: Bild importieren von ' . $imageUrl . '.');
        return;
    }
    $attachmentId = bioco_import_resolve_attachment_for_url($imageUrl, $mode);
    if (!$attachmentId) {
        bioco_import_report_row($report, $label, '', 'group_image', 'error', 'Bild-Import fehlgeschlagen von ' . $imageUrl . '.');
        return;
    }
    if (function_exists('update_field')) {
        update_field('group_image', $attachmentId, $post->ID);
        bioco_import_report_row($report, $label, '', 'group_image', 'update', 'Bild gesetzt (attachment_id=' . $attachmentId . ').');
    }
}

// Entry point used by the CLI command. $eventsSource/$groupsSource are
// nullable (--events-json / --groups-json); either or both may be omitted.
function bioco_import_run_collections($eventsSource, $groupsSource, $mode, $force, array &$report) {
    if (!$eventsSource) {
        bioco_import_report_row(
            $report, '(events)', '', '', 'info',
            'Kein --events-json angegeben — Events/Aktuelles-Import übersprungen. Auf dem Server exportieren: curl https://cms.bioco.ch/api/content/events > events.json (optional zusätzlich https://cms.bioco.ch/api/content/aktuelles), dann erneut mit --events-json=<Pfad-oder-URL> ausführen.'
        );
    } else {
        $data = bioco_import_fetch_json_source($eventsSource);
        if (is_wp_error($data)) {
            bioco_import_report_row($report, '(events)', '', '', 'error', $data->get_error_message());
        } else {
            $items = [];
            foreach (['upcoming', 'past', 'events'] as $key) {
                if (isset($data[$key]) && is_array($data[$key])) $items = array_merge($items, $data[$key]);
            }
            if (!$items) {
                bioco_import_report_row($report, '(events)', '', '', 'warn', "Keine Events in {$eventsSource} gefunden (erwartet 'upcoming'/'past'-Arrays wie /api/content/events).");
            }
            foreach ($items as $item) {
                if (is_array($item)) bioco_import_import_event_item($item, $mode, $force, $report);
            }
        }
    }

    if (!$groupsSource) {
        bioco_import_report_row(
            $report, '(groups)', '', '', 'info',
            'Kein --groups-json angegeben — Gruppen-Import übersprungen. Es gibt aktuell keinen /api/content/groups-Endpunkt in ProcessWire; entweder Gruppen manuell im wp-admin unter "Gruppen" anlegen, oder eine JSON-Datei mit einem Array aus {"title":...,"text":...,"image":<url>,"contact":...} (bzw. {"groups":[...]}) über --groups-json bereitstellen.'
        );
        return;
    }
    $data = bioco_import_fetch_json_source($groupsSource);
    if (is_wp_error($data)) {
        bioco_import_report_row($report, '(groups)', '', '', 'error', $data->get_error_message());
        return;
    }
    $items = isset($data['groups']) && is_array($data['groups']) ? $data['groups'] : (array_is_list($data) ? $data : []);
    if (!$items) {
        bioco_import_report_row($report, '(groups)', '', '', 'warn', "Keine Gruppen in {$groupsSource} gefunden (erwartet ein Array aus {title,text,image,contact} oder {\"groups\":[...]}).");
    }
    foreach ($items as $item) {
        if (is_array($item)) bioco_import_import_group_item($item, $mode, $force, $report);
    }
}
