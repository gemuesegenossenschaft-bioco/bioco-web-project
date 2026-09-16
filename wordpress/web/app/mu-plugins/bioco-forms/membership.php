<?php
/** Membership acceptance and retry contract. Production activation is #100. */
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

/** No live intranet requests are permitted by this staging implementation. */
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
