<?php
/** Confirmed subscriber administration, CSV export and queued plain-text mail. */
if (!defined('ABSPATH')) exit;

function bioco_newsletter_active(int $id): bool {
    $post = get_post($id);
    return $post && $post->post_type === 'bioco_subscriber' && $post->post_status === 'publish'
        && get_post_meta($id, 'confirmed_at', true) !== ''
        && get_post_meta($id, 'unsubscribed_at', true) === ''
        && is_email(get_post_meta($id, 'subscriber_email', true));
}

/** A new confirmation generation invalidates old links after a new opt-in. */
function bioco_newsletter_token(int $id): string {
    return hash_hmac('sha256', $id . ':' . get_post_meta($id, 'subscriber_email', true)
        . ':' . get_post_meta($id, 'confirmed_at', true)
        . ':' . get_post_meta($id, '_bioco_confirmation_id', true), wp_salt('auth'));
}

function bioco_newsletter_valid_link(int $id, string $token): bool {
    $post = get_post($id);
    return $post && $post->post_type === 'bioco_subscriber' && $post->post_status === 'publish'
        && get_post_meta($id, 'confirmed_at', true) !== ''
        && preg_match('/^[a-f0-9]{64}$/D', $token)
        && hash_equals(bioco_newsletter_token($id), $token);
}

function bioco_newsletter_unsubscribe(int $id, string $token): bool {
    if (!bioco_newsletter_valid_link($id, $token)) return false;
    if (get_post_meta($id, 'unsubscribed_at', true) === '') {
        update_post_meta($id, 'unsubscribed_at', current_time('mysql'));
    }
    return get_post_meta($id, 'unsubscribed_at', true) !== '';
}

function bioco_newsletter_url(int $id): string {
    return add_query_arg(['bioco_unsubscribe' => $id, 'token' => bioco_newsletter_token($id)], home_url('/'));
}

/** GET only displays a form; scanners cannot unsubscribe by fetching a link. */
add_action('template_redirect', function () {
    if (!isset($_GET['bioco_unsubscribe'])) return;
    $id = absint($_GET['bioco_unsubscribe']);
    $token = is_string($_GET['token'] ?? null) ? wp_unslash($_GET['token']) : '';
    nocache_headers();
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    if (!bioco_newsletter_valid_link($id, $token)) wp_die('Dieser Abmeldelink ist ungültig.', 'Newsletter', ['response' => 400]);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (($_POST['List-Unsubscribe'] ?? '') !== 'One-Click') wp_die('Ungültige Anfrage.', 'Newsletter', ['response' => 400]);
        if (!bioco_newsletter_unsubscribe($id, $token)) wp_die('Abmeldung fehlgeschlagen. Bitte erneut versuchen.', 'Newsletter', ['response' => 500]);
        wp_die('Du bist vom Newsletter abgemeldet.', 'Newsletter', ['response' => 200]);
    }
    $html = '<p>Möchtest du den biocò Newsletter abbestellen?</p><form method="post" action="' . esc_url(bioco_newsletter_url($id))
        . '"><button type="submit" name="List-Unsubscribe" value="One-Click">Newsletter abbestellen</button></form>';
    wp_die($html, 'Newsletter', ['response' => 200]);
});

/** Formula prefixes are neutralized even if preceded by whitespace. */
function bioco_newsletter_csv_cell(string $value): string {
    return preg_match('/^[\s]*[=+@-]/u', $value) ? "'" . $value : $value;
}

function bioco_newsletter_export($stream): void {
    fputcsv($stream, ['email', 'name', 'confirmed_at'], ',', '"', '');
    foreach (get_posts(['post_type' => 'bioco_subscriber', 'post_status' => 'publish', 'numberposts' => -1]) as $post) {
        if (!bioco_newsletter_active($post->ID)) continue;
        $row = [];
        foreach (['subscriber_email', 'subscriber_name', 'confirmed_at'] as $key) {
            $row[] = bioco_newsletter_csv_cell((string) get_post_meta($post->ID, $key, true));
        }
        fputcsv($stream, $row, ',', '"', '');
    }
}

/** Check eligibility again immediately before each message, including queued mail. */
function bioco_newsletter_send(int $id, string $subject, string $body): bool {
    if (!bioco_newsletter_active($id)) return false;
    $url = bioco_newsletter_url($id);
    if (parse_url($url, PHP_URL_SCHEME) !== 'https') return false;
    return wp_mail(get_post_meta($id, 'subscriber_email', true), $subject,
        $body . "\n\nNewsletter abbestellen: " . $url,
        ['Content-Type: text/plain; charset=UTF-8', 'List-Unsubscribe: <' . $url . '>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click']);
}

/** Private campaign state. A failed/unknown delivery is never silently retried. */
add_action('init', function () {
    register_post_type('bioco_newsletter', ['public' => false, 'show_ui' => false, 'show_in_rest' => false, 'supports' => ['title', 'editor']]);
    add_filter('manage_bioco_subscriber_posts_columns', function ($columns) {
        return ['cb' => $columns['cb'] ?? '', 'title' => 'E-Mail', 'bioco_name' => 'Name', 'bioco_confirmed' => 'Bestätigt am', 'bioco_status' => 'Status'];
    });
});
add_action('manage_bioco_subscriber_posts_custom_column', function ($column, $id) {
    $keys = ['bioco_name' => 'subscriber_name', 'bioco_confirmed' => 'confirmed_at'];
    if (isset($keys[$column])) echo esc_html(get_post_meta($id, $keys[$column], true));
    if ($column === 'bioco_status') echo bioco_newsletter_active($id) ? 'Bestätigt' : 'Abgemeldet oder unbestätigt';
}, 10, 2);

function bioco_newsletter_queue(string $subject, string $body): int {
    $ids = [];
    foreach (get_posts(['post_type' => 'bioco_subscriber', 'post_status' => 'publish', 'numberposts' => -1]) as $post) {
        if (bioco_newsletter_active($post->ID)) $ids[] = $post->ID;
    }
    if (!$ids) throw new RuntimeException('Keine bestätigten Abonnenten vorhanden.');
    $id = wp_insert_post(wp_slash(['post_type' => 'bioco_newsletter', 'post_status' => 'private', 'post_title' => $subject,
        'post_content' => $body, 'meta_input' => ['_bioco_recipients' => $ids, '_bioco_cursor' => 0]]), true);
    if (!$id || is_wp_error($id)) throw new RuntimeException('Newsletter konnte nicht gespeichert werden.');
    if (!wp_schedule_single_event(time() + 5, 'bioco_newsletter_batch', [$id])) {
        update_post_meta($id, '_bioco_queue_error', 'schedule_failed');
        throw new RuntimeException('Versand konnte nicht geplant werden.');
    }
    return $id;
}

add_action('bioco_newsletter_batch', 'bioco_newsletter_batch');
function bioco_newsletter_batch(int $id): void {
    $campaign = get_post($id);
    if (!$campaign || $campaign->post_type !== 'bioco_newsletter' || $campaign->post_status !== 'private') return;
    $lock = 'bioco_newsletter_lock_' . $id;
    // Do not steal an expired lock: the original worker might still be sending.
    if (!add_option($lock, time(), '', false)) return;
    try {
        $ids = (array) get_post_meta($id, '_bioco_recipients', true);
        $cursor = (int) get_post_meta($id, '_bioco_cursor', true);
        $end = min(count($ids), $cursor + 20);
        for (; $cursor < $end; $cursor++) {
            $recipient = (int) $ids[$cursor];
            $key = '_bioco_delivery_' . $recipient;
            if (get_post_meta($id, $key, true) === '') {
                // Persist before transport. A crashed send stays "sending" for manual review.
                if (!add_post_meta($id, $key, 'sending', true)) throw new RuntimeException('Versandstatus konnte nicht gespeichert werden.');
                $status = bioco_newsletter_active($recipient)
                    ? (bioco_newsletter_send($recipient, $campaign->post_title, $campaign->post_content) ? 'sent' : 'failed') : 'skipped';
                update_post_meta($id, $key, $status);
            }
            update_post_meta($id, '_bioco_cursor', $cursor + 1);
        }
        if ($cursor < count($ids)) {
            if (!wp_schedule_single_event(time() + 60, 'bioco_newsletter_batch', [$id])) update_post_meta($id, '_bioco_queue_error', 'schedule_failed');
        } else update_post_meta($id, '_bioco_completed_at', current_time('mysql'));
    } finally { delete_option($lock); }
}

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=bioco_subscriber', 'Newsletter versenden', 'Versenden / Export', 'manage_options', 'bioco-newsletter', 'bioco_newsletter_page');
});
function bioco_newsletter_page(): void {
    if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.', '', ['response' => 403]);
    echo '<div class="wrap"><h1>Newsletter</h1><p>Versand nur an bestätigte Abonnenten. Test-E-Mails gehen ebenfalls an bestätigte Test-Abonnenten.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="bioco_newsletter_send">';
    echo '<input type="hidden" name="dispatch_id" value="' . esc_attr(wp_generate_uuid4()) . '">';
    wp_nonce_field('bioco_newsletter_send');
    echo '<p><label>Betreff<br><input class="large-text" name="subject" required maxlength="200"></label></p>';
    echo '<p><label>Nachricht<br><textarea class="large-text" name="body" rows="12" required></textarea></label></p>';
    echo '<p><label>Test-E-Mail<br><input type="email" name="test_email"></label></p>';
    echo '<button class="button" name="mode" value="test">Test senden</button> ';
    echo '<button class="button button-primary" name="mode" value="all">An bestätigte Abonnenten senden</button></form>';
    echo '<p><a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=bioco_newsletter_export'), 'bioco_newsletter_export')) . '">Bestätigte Abonnenten als CSV exportieren</a></p>';
    echo '<h2>Letzte Sendungen</h2><ul>';
    foreach (get_posts(['post_type' => 'bioco_newsletter', 'post_status' => 'private', 'numberposts' => 10]) as $campaign) {
        $counts = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'sending' => 0, 'pending' => 0];
        foreach ((array) get_post_meta($campaign->ID, '_bioco_recipients', true) as $recipient) {
            $state = get_post_meta($campaign->ID, '_bioco_delivery_' . (int) $recipient, true) ?: 'pending';
            if (isset($counts[$state])) $counts[$state]++;
        }
        echo '<li>' . esc_html($campaign->post_title . ': ' . $counts['sent'] . ' angenommen, ' . $counts['failed'] . ' fehlgeschlagen, '
            . $counts['skipped'] . ' übersprungen, ' . $counts['pending'] . ' ausstehend, ' . $counts['sending'] . ' unklar') . '</li>';
    }
    echo '</ul><p>Angenommen bedeutet vom Mailtransport angenommen. Fehlgeschlagene oder unklare Sendungen bitte vor einem erneuten Versand prüfen.</p></div>';
}

/** Both administration boundaries require capability and nonce before reading data. */
add_action('admin_post_bioco_newsletter_export', function () {
    if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.', '', ['response' => 403]);
    check_admin_referer('bioco_newsletter_export');
    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bioco-newsletter.csv"');
    bioco_newsletter_export(fopen('php://output', 'w'));
    exit;
});
add_action('admin_post_bioco_newsletter_send', function () {
    if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.', '', ['response' => 403]);
    check_admin_referer('bioco_newsletter_send');
    $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
    $body = sanitize_textarea_field(wp_unslash($_POST['body'] ?? ''));
    if (trim($subject) === '' || trim($body) === '' || strlen($subject) > 200 || strlen($body) > 100000) wp_die('Betreff und Nachricht prüfen.', '', ['response' => 400]);
    $dispatch = is_string($_POST['dispatch_id'] ?? null) ? $_POST['dispatch_id'] : '';
    if (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D', $dispatch)) wp_die('Formular neu laden.', '', ['response' => 400]);
    if (!add_option('bioco_newsletter_dispatch_' . hash('sha256', $dispatch), time(), '', false)) {
        wp_safe_redirect(admin_url('admin.php?page=bioco-newsletter'));
        exit;
    }
    try {
        if (($_POST['mode'] ?? '') === 'test') {
            $email = sanitize_email(wp_unslash($_POST['test_email'] ?? ''));
            $posts = get_posts(['post_type' => 'bioco_subscriber', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => 'subscriber_email', 'meta_value' => $email]);
            if (!$email || !$posts || !bioco_newsletter_send($posts[0]->ID, $subject, $body)) throw new RuntimeException('Test-Abonnent fehlt oder Versand fehlgeschlagen.');
        } elseif (($_POST['mode'] ?? '') === 'all') bioco_newsletter_queue($subject, $body);
        else wp_die('Versandart wählen.', '', ['response' => 400]);
    } catch (RuntimeException $error) { wp_die(esc_html($error->getMessage()), 'Newsletter', ['response' => 400]); }
    wp_safe_redirect(admin_url('admin.php?page=bioco-newsletter'));
    exit;
});
