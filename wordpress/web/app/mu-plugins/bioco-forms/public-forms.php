<?php
/** Contact, visit, waiting-list and event signup handlers. */
if (!defined('ABSPATH')) exit;

// Mirrors .wp-refs/api-forms/contact/route.ts — subject line is
// "Kontaktanfrage: {subject}", body lists all submitted fields.
function bioco_forms_handle_contact(WP_REST_Request $request) {
    $body = bioco_forms_json_body($request);

    $captcha_token = isset($body['captchaToken']) ? sanitize_text_field($body['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $name = isset($body['name']) ? sanitize_text_field($body['name']) : '';
    $email = isset($body['email']) ? sanitize_email($body['email']) : '';
    $phone = isset($body['phone']) ? sanitize_text_field($body['phone']) : '';
    $subject = isset($body['subject']) ? sanitize_text_field($body['subject']) : '';
    $message = isset($body['message']) ? sanitize_textarea_field($body['message']) : '';

    if (!$name || !$email || !is_email($email) || !$subject || !$message) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_missing_fields_error']], 400);
    }

    $lines = ['Name: ' . $name, 'E-Mail: ' . $email];
    if ($phone) {
        $lines[] = 'Telefon: ' . $phone;
    }
    $lines[] = 'Betreff: ' . $subject;
    $lines[] = '';
    $lines[] = 'Nachricht:';
    $lines[] = $message;

    $sent = bioco_forms_send_mail(bioco_forms_recipient(), 'Kontaktanfrage: ' . $subject, bioco_forms_lines($lines), $email);
    if (!$sent) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_generic_error']], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
}

// Visit-day and waiting-list send mail immediately (no DOI — see the module
// docblock above): die Erfolgsmeldung des Referenztexts (Anrede du, Issue #158)
// copy is specific to subscribe's real double-opt-in, so the ported block
// view.js uses adapted success text instead of a false promise.
function bioco_forms_handle_visit_day(WP_REST_Request $request) {
    $body = bioco_forms_json_body($request);

    $captcha_token = isset($body['captchaToken']) ? sanitize_text_field($body['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $name = isset($body['name']) ? sanitize_text_field($body['name']) : '';
    $email = isset($body['email']) ? sanitize_email($body['email']) : '';
    $phone = isset($body['phone']) ? sanitize_text_field($body['phone']) : '';
    $visit_date = isset($body['visit_date']) ? sanitize_text_field($body['visit_date']) : '';
    $participants = isset($body['participants']) ? (int) $body['participants'] : 0;
    $notes = isset($body['notes']) ? sanitize_textarea_field($body['notes']) : '';
    $privacy_accept = !empty($body['privacy_accept']);

    if (!$name || !$email || !is_email($email) || !$phone || !$visit_date || !$participants || !$privacy_accept) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_missing_fields_error']], 400);
    }

    $lines = [
        'Name: ' . $name,
        'E-Mail: ' . $email,
        'Telefon: ' . $phone,
        'Gewünschtes Datum: ' . $visit_date,
        'Anzahl Personen: ' . $participants,
    ];
    if ($notes) {
        $lines[] = '';
        $lines[] = 'Anmerkungen:';
        $lines[] = $notes;
    }

    $sent = bioco_forms_send_mail(bioco_forms_recipient(), 'Neue Anmeldung: Tag der offenen Tür', bioco_forms_lines($lines), $email);
    if (!$sent) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_generic_error']], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
}

function bioco_forms_handle_waiting_list(WP_REST_Request $request) {
    $body = bioco_forms_json_body($request);

    $captcha_token = isset($body['captchaToken']) ? sanitize_text_field($body['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $name = isset($body['name']) ? sanitize_text_field($body['name']) : '';
    $email = isset($body['email']) ? sanitize_email($body['email']) : '';
    $phone = isset($body['phone']) ? sanitize_text_field($body['phone']) : '';
    $interest = isset($body['interest']) ? sanitize_text_field($body['interest']) : '';
    $notes = isset($body['notes']) ? sanitize_textarea_field($body['notes']) : '';
    $privacy_accept = !empty($body['privacy_accept']);

    if (!$name || !$email || !is_email($email) || !$phone || !$interest || !$privacy_accept) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_missing_fields_error']], 400);
    }

    $interest_labels = ['program1' => 'Programm 1', 'program2' => 'Programm 2', 'program3' => 'Programm 3'];
    $interest_label = isset($interest_labels[$interest]) ? $interest_labels[$interest] : $interest;

    $lines = [
        'Name: ' . $name,
        'E-Mail: ' . $email,
        'Telefon: ' . $phone,
        'Interesse an: ' . $interest_label,
    ];
    if ($notes) {
        $lines[] = '';
        $lines[] = 'Anmerkungen:';
        $lines[] = $notes;
    }

    $sent = bioco_forms_send_mail(bioco_forms_recipient(), 'Neue Warteliste-Anmeldung', bioco_forms_lines($lines), $email);
    if (!$sent) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_generic_error']], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
}

// Mirrors .wp-refs/api-forms/event-signup/route.ts — subject uses the
// event title when present, falling back to a generic subject.
function bioco_forms_handle_event_signup(WP_REST_Request $request) {
    $body = bioco_forms_json_body($request);

    $captcha_token = isset($body['captchaToken']) ? sanitize_text_field($body['captchaToken']) : '';
    if (!bioco_forms_verify_turnstile($captcha_token, bioco_forms_client_ip())) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_captcha_error']], 400);
    }

    $name = isset($body['name']) ? sanitize_text_field($body['name']) : '';
    $email = isset($body['email']) ? sanitize_email($body['email']) : '';
    $phone = isset($body['phone']) ? sanitize_text_field($body['phone']) : '';
    $notes = isset($body['notes']) ? sanitize_textarea_field($body['notes']) : '';
    $event_title = isset($body['eventTitle']) ? sanitize_text_field($body['eventTitle']) : '';
    $event_id = isset($body['eventId']) ? sanitize_text_field($body['eventId']) : '';

    if (!$name || !$email || !is_email($email)) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_missing_fields_error']], 400);
    }

    $lines = ['Name: ' . $name, 'E-Mail: ' . $email];
    if ($phone) {
        $lines[] = 'Telefon: ' . $phone;
    }
    if ($event_title) {
        $lines[] = 'Veranstaltung: ' . $event_title;
    }
    if ($event_id) {
        $lines[] = 'Veranstaltungs-ID: ' . $event_id;
    }
    if ($notes) {
        $lines[] = '';
        $lines[] = 'Bemerkungen:';
        $lines[] = $notes;
    }

    $subject = $event_title ? ('Event-Anmeldung: ' . $event_title) : 'Neue Event-Anmeldung';
    $sent = bioco_forms_send_mail(bioco_forms_recipient(), $subject, bioco_forms_lines($lines), $email);
    if (!$sent) {
        return new WP_REST_Response(['success' => false, 'error' => $GLOBALS['bioco_forms_generic_error']], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
}

// Membership keeps the email/manual workflow; local acceptance precedes mail.
