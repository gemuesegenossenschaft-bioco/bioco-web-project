<?php
/** Membership validation and the documented intranet field mapping. */
if (!defined('ABSPATH')) exit;

/**
 * Membership: validation + intranet field mapping, ported from
 * .wp-refs/membership.ts (validateMembership / buildIntranetSignupPayload).
 */

function bioco_forms_validate_membership($data) {
    $errors = [];
    $required_fields = ['firstName', 'lastName', 'email', 'address', 'zip', 'city'];
    $allowed = array_merge($required_fields, ['phone', 'mobilePhone', 'birthday', 'comment', 'otherActivity', 'weitereProdukte', 'depot', 'paymentType', 'preferredDays', 'preferredTimes', 'activityAreas', 'zusatzabos', 'privacyAccept', 'commitmentAccepted', 'commitmentCount', 'commitmentSignature', 'membershipType', 'aboType', 'additionalShares', 'sharesOnly', 'submissionId', 'captchaToken', 'cf-turnstile-response']);
    foreach (array_diff(array_keys($data), $allowed) as $field) $errors[$field] = bioco_forms_message('shared', 'generic');

    foreach ($required_fields as $field) {
        $value = isset($data[$field]) ? $data[$field] : null;
        if (!is_string($value) || trim($value) === '' || strlen($value) > 5000) {
            $errors[$field] = bioco_forms_message('shared', 'required_field');
        }
    }

    if (!isset($data['privacyAccept']) || $data['privacyAccept'] !== true) {
        $errors['privacyAccept'] = bioco_forms_message('shared', 'privacy');
    }

    if (isset($data['email']) && is_string($data['email']) && trim($data['email']) !== '' && !is_email(trim($data['email']))) {
        $errors['email'] = bioco_forms_message('shared', 'email');
    }

    $membership_type = isset($data['membershipType']) ? $data['membershipType'] : '';
    $abo_type = isset($data['aboType']) ? $data['aboType'] : '';
    $additional = bioco_forms_bounded_share_count($data['additionalShares'] ?? null, 0);
    $shares_only_value = array_key_exists('sharesOnly', $data)
        ? $data['sharesOnly']
        : ($membership_type === 'abo' ? 0 : null);
    $shares_only = bioco_forms_bounded_share_count($shares_only_value, 0);
    $valid_abo = $membership_type === 'abo'
        && in_array($abo_type, ['halb', 'standard', 'doppel'], true)
        && $additional !== null
        && $shares_only === 0;
    $valid_shares_only = $membership_type === 'shares-only'
        && $abo_type === 'none'
        && $additional === 0
        && $shares_only !== null
        && $shares_only >= 1;
    if (!$valid_abo && !$valid_shares_only) {
        $errors['membershipSelection'] = bioco_forms_message('shared', 'membership');
    }

    foreach (['phone', 'mobilePhone', 'birthday', 'comment', 'otherActivity', 'weitereProdukte', 'depot', 'paymentType'] as $field) {
        if (isset($data[$field]) && (!is_string($data[$field]) || strlen($data[$field]) > 5000)) $errors[$field] = bioco_forms_message('shared', 'generic');
    }
    foreach (['preferredDays', 'preferredTimes', 'activityAreas', 'zusatzabos'] as $field) {
        if (isset($data[$field]) && (!is_array($data[$field]) || !array_is_list($data[$field]) || count($data[$field]) > 100 || array_filter($data[$field], static fn($item) => !is_string($item) || strlen($item) > 5000))) $errors[$field] = bioco_forms_message('shared', 'generic');
    }
    if (!empty($data['birthday']) && is_string($data['birthday'])) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['birthday']);
        if (!$date || $date->format('Y-m-d') !== $data['birthday'] || $date > new DateTimeImmutable('today')) $errors['birthday'] = bioco_forms_message('shared', 'generic');
    }
    $commitments = $data['commitmentAccepted'] ?? [];
    $count = bioco_forms_bounded_share_count($data['commitmentCount'] ?? null, 1);
    $signature = $data['commitmentSignature'] ?? '';
    $valid_signature = $count !== null && is_string($signature) && hash_equals(bioco_forms_commitment_signature($count), $signature);
    if (!$valid_signature || !is_array($commitments) || !array_is_list($commitments) || count($commitments) !== $count || array_filter($commitments, static fn($item) => $item !== true)) {
        $errors['commitmentAccepted'] = bioco_forms_message('shared', 'required_field');
    }

    return ['ok' => empty($errors), 'errors' => $errors];
}

function bioco_forms_bounded_share_count($value, $minimum) {
    if (is_int($value)) {
        $count = $value;
    } elseif (is_string($value) && preg_match('/^\d+$/', $value)) {
        $count = (int) $value;
    } else {
        return null;
    }
    return $count >= $minimum && $count <= 100 ? $count : null;
}

function bioco_forms_membership_total_shares($data) {
    $required_shares_by_abo_type = ['halb' => 1, 'standard' => 2, 'doppel' => 4, 'none' => 0];

    if (isset($data['membershipType']) && $data['membershipType'] === 'shares-only') {
        return isset($data['sharesOnly']) ? (int) $data['sharesOnly'] : 0;
    }

    $abo_type = isset($data['aboType']) ? $data['aboType'] : '';
    $required = isset($required_shares_by_abo_type[$abo_type]) ? $required_shares_by_abo_type[$abo_type] : 0;
    $additional = isset($data['additionalShares']) ? (int) $data['additionalShares'] : 0;
    return $required + $additional;
}

function bioco_forms_membership_notes($data) {
    $lines = [];
    if (!empty($data['comment']) && is_string($data['comment'])) $lines[] = trim($data['comment']);

    if (!empty($data['preferredDays']) && is_array($data['preferredDays'])) {
        $lines[] = 'Bevorzugte Tage: ' . implode(', ', $data['preferredDays']);
    }
    if (!empty($data['preferredTimes']) && is_array($data['preferredTimes'])) {
        $lines[] = 'Bevorzugte Zeiten: ' . implode(', ', $data['preferredTimes']);
    }
    if (!empty($data['activityAreas']) && is_array($data['activityAreas'])) {
        $lines[] = 'Tätigkeitsbereiche: ' . implode(', ', $data['activityAreas']);
    }
    if (!empty($data['otherActivity']) && is_string($data['otherActivity']) && trim($data['otherActivity']) !== '') {
        $lines[] = 'Andere Tätigkeit: ' . trim($data['otherActivity']);
    }
    if (!empty($data['zusatzabos']) && is_array($data['zusatzabos'])) {
        $lines[] = 'Zusatzabos: ' . implode(', ', $data['zusatzabos']);
    }
    if (!empty($data['weitereProdukte']) && is_string($data['weitereProdukte']) && trim($data['weitereProdukte']) !== '') {
        $lines[] = 'Weitere Produkte: ' . trim($data['weitereProdukte']);
    }

    return implode("\n", $lines);
}

// Current intranet field contract (#139); transport is isolated in membership.php.
function bioco_forms_build_intranet_payload($data) {
    $commitment_accepted = false;
    if (!empty($data['commitmentAccepted']) && is_array($data['commitmentAccepted'])) {
        $commitment_accepted = true;
        foreach ($data['commitmentAccepted'] as $item) {
            if (!$item) {
                $commitment_accepted = false;
                break;
            }
        }
    }
    $terms = $commitment_accepted && isset($data['privacyAccept']) && $data['privacyAccept'] === true;

    $payload = [
        'first_name' => isset($data['firstName']) ? $data['firstName'] : '',
        'last_name' => isset($data['lastName']) ? $data['lastName'] : '',
        'email' => isset($data['email']) ? $data['email'] : '',
        'phone' => isset($data['phone']) ? $data['phone'] : '',
        'addr_street' => isset($data['address']) ? $data['address'] : '',
        'addr_zipcode' => isset($data['zip']) ? $data['zip'] : '',
        'addr_location' => isset($data['city']) ? $data['city'] : '',
        'membership_type' => isset($data['membershipType']) ? $data['membershipType'] : '',
        'abo' => isset($data['aboType']) ? $data['aboType'] : '',
        'shares' => (string) bioco_forms_membership_total_shares($data),
        'depot' => isset($data['depot']) ? $data['depot'] : '',
        'payment_interval' => isset($data['paymentType']) ? $data['paymentType'] : '',
        'agb' => $terms ? 'on' : '',
        'mobile_phone' => isset($data['mobilePhone']) ? $data['mobilePhone'] : '',
        'birthday' => isset($data['birthday']) ? $data['birthday'] : '',
        'comment' => bioco_forms_membership_notes($data),
    ];
    return array_map(static fn($value) => sanitize_textarea_field(is_string($value) ? $value : ''), $payload);
}

/**
 * REST routes — bioco/v1 namespace. All POST endpoints are public
 * (permission_callback => __return_true): they are anonymous form
 * submissions gated by Turnstile, not authenticated API calls.
 */
