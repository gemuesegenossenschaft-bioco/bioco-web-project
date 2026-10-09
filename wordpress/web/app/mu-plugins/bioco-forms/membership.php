<?php
/** Membership acceptance and retry contract. Fake staging and private local production registrations. */
if (!defined('ABSPATH')) exit;

/** Bind validation to the checklist actually rendered, not a client-supplied count. */
function bioco_forms_commitment_signature(int $count): string {
    return hash_hmac('sha256', 'membership-commitments:' . $count, wp_salt('nonce'));
}

function bioco_forms_membership_test_environment(): bool {
    $host = strtolower((string) parse_url(home_url('/'), PHP_URL_HOST));
    return wp_get_environment_type() !== 'production'
        || $host === 'staging.bioco.ch'
        || in_array($host, ['localhost', '127.0.0.1'], true);
}

/** Local acceptance is opt-in and restricted to the real production host. */
function bioco_forms_membership_local_enabled(): bool {
    return get_option('bioco_membership_adapter', 'disabled') === 'local'
        && wp_get_environment_type() === 'production'
        && strtolower((string) parse_url(home_url('/'), PHP_URL_HOST)) === 'bioco.ch';
}

/** All validated form fields, without ephemeral verification material. */
function bioco_forms_membership_normalize(array $data): array {
    unset($data['captchaToken'], $data['commitmentSignature'], $data['cf-turnstile-response']);
    foreach ($data as $field => $value) {
        if (in_array($field, ['additionalShares', 'sharesOnly', 'commitmentCount'], true)) {
            $data[$field] = (int) $value;
        } elseif ($field === 'email') {
            $data[$field] = sanitize_email(trim($value));
        } elseif (is_string($value)) {
            $data[$field] = sanitize_textarea_field($value);
        } elseif (is_array($value) && $field !== 'commitmentAccepted') {
            $data[$field] = array_map('sanitize_textarea_field', $value);
        }
    }
    ksort($data);
    return $data;
}

function bioco_forms_membership_local_accept(array $data, string $request_id, string $key): array {
    $data = bioco_forms_membership_normalize($data);
    $fingerprint = hash('sha256', wp_json_encode($data));
    $accepted = [
        'status' => 'accepted', 'adapter' => 'local',
        'receipt' => 'local-' . hash('sha256', $request_id),
        'fingerprint' => $fingerprint, 'data' => $data,
        'created_at' => gmdate('c'), 'notification' => 'pending',
    ];
    // One unique option insertion durably stores the entire registration.
    // No record/mail side effects occur unless this request wins the insert.
    if (add_option($key, $accepted, '', false)) return $accepted + ['replayed' => false];
    $existing = get_option($key, []);
    if (!$existing) return ['status' => 'unavailable'];
    if (($existing['fingerprint'] ?? '') !== $fingerprint) {
        return ['status' => 'validation', 'fieldErrors' => ['submissionId' => bioco_forms_message('shared', 'generic')]];
    }
    if (($existing['status'] ?? '') !== 'accepted' || ($existing['adapter'] ?? '') !== 'local') {
        return ['status' => 'unavailable'];
    }
    return $existing + ['replayed' => true];
}

function bioco_forms_membership_notification(string $request_id, bool $sent): void {
    $key = 'bioco_membership_' . hash('sha256', $request_id);
    $record = get_option($key, []);
    if (($record['adapter'] ?? '') !== 'local') return;
    $record['notification'] = $sent ? 'sent' : 'failed';
    update_option($key, $record, false);
    // A failed update leaves pending visible for manual followup. Never resend.
}

add_action('admin_menu', function () {
    add_management_page('Mitgliedschaftsanmeldungen', 'Mitgliedschaftsanmeldungen', 'manage_options', 'bioco-memberships', 'bioco_forms_membership_admin');
});

/** Read-only, paginated review. No public REST registration or payload logging. */
function bioco_forms_membership_admin(): void {
    if (!current_user_can('manage_options')) {
        wp_die('Zugriff verweigert.', '', ['response' => 403]);
        return;
    }
    global $wpdb;
    $page = max(1, (int) ($_GET['paged'] ?? 1));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name REGEXP %s ORDER BY option_id DESC LIMIT 50 OFFSET %d",
        '^bioco_membership_[a-f0-9]{64}$', ($page - 1) * 50
    ));
    echo '<div class="wrap"><h1>Mitgliedschaftsanmeldungen</h1><p>Manuell bearbeiten. failed oder pending: Benachrichtigung prüfen und nachfassen.</p>';
    foreach ($rows as $row) {
        $record = maybe_unserialize($row->option_value);
        if (!is_array($record) || ($record['adapter'] ?? '') !== 'local') continue;
        echo '<details><summary>' . esc_html(($record['created_at'] ?? '') . ' | ' . ($record['receipt'] ?? '') . ' | ' . ($record['status'] ?? '') . ' | Mail: ' . ($record['notification'] ?? '')) . '</summary><pre>';
        echo esc_html(wp_json_encode($record['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo '</pre></details>';
    }
    echo '<p><a href="' . esc_url(admin_url('tools.php?page=bioco-memberships&paged=' . ($page + 1))) . '">Weitere Anmeldungen</a></p></div>';
}

/** No live intranet requests are permitted. */
function bioco_forms_membership_adapter(array $payload, string $request_id): array {
    $mode = get_option('bioco_membership_adapter', 'disabled');
    if (!bioco_forms_membership_test_environment() || $mode !== 'fake') {
        return ['status' => 'unavailable'];
    }
    $result = get_option('bioco_membership_fake_result', 'accepted');
    if ($result === 'validation') {
        return ['status' => 'validation', 'fieldErrors' => ['email' => bioco_forms_message('shared', 'email')]];
    }
    if ($result !== 'accepted') return ['status' => 'unavailable'];
    return ['status' => 'accepted', 'receipt' => 'fake-' . hash('sha256', $request_id), 'simulated' => true];
}

/** Atomic option insertion prevents two requests from calling the adapter. */
function bioco_forms_membership_accept(array $data): array {
    $request_id = $data['submissionId'] ?? '';
    if (!is_string($request_id) || !preg_match('/^[a-f0-9-]{32,64}$/D', $request_id)) {
        return ['status' => 'validation', 'fieldErrors' => ['submissionId' => bioco_forms_message('shared', 'generic')]];
    }
    $key = 'bioco_membership_' . hash('sha256', $request_id);
    if (bioco_forms_membership_local_enabled()) return bioco_forms_membership_local_accept($data, $request_id, $key);
    if (!bioco_forms_membership_test_environment() || get_option('bioco_membership_adapter', 'disabled') !== 'fake') return ['status' => 'unavailable'];
    $payload = bioco_forms_build_intranet_payload($data);
    $fingerprint = hash('sha256', wp_json_encode($payload));
    $key = 'bioco_membership_' . hash('sha256', $request_id);
    $pending = ['status' => 'pending', 'fingerprint' => $fingerprint];
    if (!add_option($key, $pending, '', false)) {
        $existing = get_option($key, []);
        if (($existing['fingerprint'] ?? '') !== $fingerprint) {
            return ['status' => 'validation', 'fieldErrors' => ['submissionId' => bioco_forms_message('shared', 'generic')]];
        }
        if (($existing['status'] ?? '') === 'accepted') return $existing + ['replayed' => true];
        return ['status' => 'unavailable'];
    }
    try {
        $result = bioco_forms_membership_adapter($payload, $request_id);
    } catch (Throwable $error) {
        // The acceptance outcome is unknown. Keep the claim to prevent duplicates.
        return ['status' => 'unavailable'];
    }
    if ($result['status'] !== 'accepted') {
        // This adapter contract guarantees these outcomes have no side effects.
        delete_option($key);
        return $result;
    }
    $accepted = $result + ['fingerprint' => $fingerprint];
    if (!update_option($key, $accepted, false)) return ['status' => 'unavailable'];
    return $accepted + ['replayed' => false];
}

function bioco_forms_handle_membership(WP_REST_Request $request) {
    $data = bioco_forms_json_body($request);

    $captcha_token = isset($data['captchaToken']) && is_string($data['captchaToken']) ? sanitize_text_field($data['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $validation = bioco_forms_validate_membership($data);
    if (!$validation['ok']) {
        return new WP_REST_Response([
            'success' => false,
            'error' => $GLOBALS['bioco_forms_missing_fields_error'],
            'fieldErrors' => $validation['errors'],
        ], 400);
    }

    $acceptance = bioco_forms_membership_accept($data);
    if ($acceptance['status'] !== 'accepted') {
        return new WP_REST_Response([
            'success' => false,
            'error' => bioco_forms_message('shared', 'generic'),
            'fieldErrors' => $acceptance['fieldErrors'] ?? [],
        ], $acceptance['status'] === 'validation' ? 400 : 502);
    }
    if (!empty($acceptance['replayed'])) {
        return new WP_REST_Response(['success' => true, 'receipt' => $acceptance['receipt'], 'simulated' => !empty($acceptance['simulated'])], 200);
    }

    $first_name = sanitize_text_field($data['firstName']);
    $last_name = sanitize_text_field($data['lastName']);
    $email = sanitize_email($data['email']);
    $address = sanitize_text_field($data['address']);
    $zip = sanitize_text_field($data['zip']);
    $city = sanitize_text_field($data['city']);
    $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
    $mobile_phone = isset($data['mobilePhone']) ? sanitize_text_field($data['mobilePhone']) : '';
    $birthday = isset($data['birthday']) ? sanitize_text_field($data['birthday']) : '';
    $depot = isset($data['depot']) ? sanitize_text_field($data['depot']) : '';
    $payment_type = isset($data['paymentType']) ? sanitize_text_field($data['paymentType']) : '';
    $abo_type = isset($data['aboType']) ? sanitize_text_field($data['aboType']) : '';
    $membership_type = isset($data['membershipType']) ? sanitize_text_field($data['membershipType']) : '';

    $lines = [
        'Vorname: ' . $first_name,
        'Name: ' . $last_name,
        'Adresse: ' . $address . ', ' . $zip . ' ' . $city,
        'E-Mail: ' . $email,
    ];
    if ($phone) {
        $lines[] = 'Telefon: ' . $phone;
    }
    if ($mobile_phone) {
        $lines[] = 'Mobiltelefon: ' . $mobile_phone;
    }
    if ($birthday) {
        $lines[] = 'Geburtsdatum: ' . $birthday;
    }
    $lines[] = '';
    $lines[] = 'Mitgliedschaft: ' . ($membership_type === 'shares-only' ? 'Nur Anteilsscheine' : 'Gemüseabo');
    if ($membership_type !== 'shares-only') {
        $lines[] = 'Gemüsekorb: ' . $abo_type;
    }
    $lines[] = 'Anteilsscheine: ' . bioco_forms_membership_total_shares($data);
    if ($depot) {
        $lines[] = 'Depot: ' . $depot;
    }
    if ($payment_type) {
        $lines[] = 'Zahlungsweise: ' . ($payment_type === 'quarterly' ? 'Quartalsweise' : 'Ganzes Jahr');
    }
    $notes = bioco_forms_membership_notes($data);
    if ($notes) {
        $lines[] = '';
        $lines[] = $notes;
    }

    // Acceptance is durable before notification. A failed notification must
    // never invite another submission. Fake staging signups send no real mail.
    if (empty($acceptance['simulated'])) {
        $sent = false;
        try {
            $subject = 'Neue Mitgliedschaftsanmeldung: ' . $first_name . ' ' . $last_name;
            $sent = bioco_forms_send_mail(bioco_forms_recipient(), $subject, bioco_forms_lines(array_merge(['Receipt: ' . $acceptance['receipt']], $lines)), $email);
        } catch (Throwable $error) {
            // Retained registration remains successful; admins see failure.
        }
        bioco_forms_membership_notification($data['submissionId'], (bool) $sent);
    }
    return new WP_REST_Response(['success' => true, 'receipt' => $acceptance['receipt'], 'simulated' => !empty($acceptance['simulated'])], 200);
}
