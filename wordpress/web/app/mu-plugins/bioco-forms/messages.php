<?php
// Editable public form messages. Seed installation is an explicit operation;
// rendering never reads seed files or replaces a saved empty value.
if (!defined('ABSPATH')) exit;

function bioco_forms_message_schema() {
    $form = ['success_message', 'fallback_error', 'captcha_error', 'transport_error'];
    return [
        'contact-form' => $form,
        'subscribe-form' => $form,
        'visit-day-form' => $form,
        'waiting-list-form' => $form,
        'event-signup-form' => $form,
        'membership-form' => ['fallback_error', 'captcha_error', 'transport_error', 'next_label', 'previous_label', 'progress_label', 'birthday_label', 'mobile_phone_label', 'comment_label'],
        'doi-confirm' => ['missing_token_message', 'confirmation_prompt', 'confirmation_button_label', 'success_title', 'success_text', 'subscribe_text', 'visit_text', 'waiting_list_text', 'contact_text', 'error_title', 'home_link_label'],
        'shared' => ['missing_token', 'invalid_token', 'required_field', 'privacy', 'email', 'membership', 'captcha', 'generic', 'missing_fields', 'mail_failed', 'newsletter_intro', 'newsletter_expiry', 'newsletter_ignore', 'newsletter_subject', 'newsletter_greeting', 'newsletter_greeting_anonymous'],
    ];
}

function bioco_forms_message($group, $key, $existing = '') {
    $messages = function_exists('get_option') ? get_option('bioco_form_messages', []) : [];
    $values = is_array($messages) && isset($messages[$group]) && is_array($messages[$group]) ? $messages[$group] : [];
    return array_key_exists($key, $values) && is_string($values[$key]) ? $values[$key] : (string) $existing;
}

function bioco_forms_message_values($group, array $values) {
    foreach (bioco_forms_message_schema()[$group] ?? [] as $key) {
        $values[$key] = bioco_forms_message($group, $key, $values[$key] ?? '');
    }
    return $values;
}

function bioco_forms_sanitize_messages($input) {
    $clean = [];
    foreach (bioco_forms_message_schema() as $group => $keys) {
        foreach ($keys as $key) {
            $value = is_array($input) ? ($input[$group][$key] ?? '') : '';
            $clean[$group][$key] = is_string($value) ? sanitize_textarea_field($value) : '';
        }
    }
    return $clean;
}

// Fill missing keys only. Existing editor values, including empty ones, win.
function bioco_forms_seed_messages(array $defaults) {
    $saved = get_option('bioco_form_messages', []);
    if (!is_array($saved)) $saved = [];
    foreach (bioco_forms_message_schema() as $group => $keys) {
        if (!isset($saved[$group]) || !is_array($saved[$group])) $saved[$group] = [];
        $source = $group === 'shared' ? ($defaults['formMessages']['shared'] ?? []) : ($defaults['blocks'][$group] ?? []);
        foreach ($keys as $key) {
            if (!array_key_exists($key, $saved[$group]) && isset($source[$key]) && is_string($source[$key])) {
                $saved[$group][$key] = $source[$key];
            }
        }
    }
    update_option('bioco_form_messages', $saved, false);
}

add_action('admin_menu', function () {
    add_menu_page('Formulartexte', 'Formulartexte', 'edit_pages', 'bioco-form-messages', 'bioco_forms_messages_page', 'dashicons-editor-paragraph');
});

function bioco_forms_messages_page() {
    if (!current_user_can('edit_pages')) wp_die('Keine Berechtigung.');
    $groups = ['contact-form' => 'Kontakt', 'subscribe-form' => 'Newsletter', 'visit-day-form' => 'Tag der offenen Tür', 'waiting-list-form' => 'Warteliste', 'event-signup-form' => 'Event-Anmeldung', 'membership-form' => 'Mitgliedschaft', 'doi-confirm' => 'Bestätigungsseite', 'shared' => 'Validierung und Bestätigungs-E-Mail'];
    $labels = ['next_label' => 'Weiter-Button', 'previous_label' => 'Zurück-Button', 'progress_label' => 'Fortschritt ({current}, {total})', 'birthday_label' => 'Geburtsdatum', 'mobile_phone_label' => 'Mobiltelefon', 'comment_label' => 'Bemerkungen', 'success_message' => 'Erfolgsmeldung', 'fallback_error' => 'Fehler beim Senden', 'captcha_error' => 'Sicherheitsprüfung fehlt', 'transport_error' => 'Sicherheitsprüfung nicht geladen', 'missing_token_message' => 'Bestätigungslink fehlt', 'confirmation_prompt' => 'Bestätigungsaufforderung', 'confirmation_button_label' => 'Beschriftung des Bestätigungsbuttons', 'success_title' => 'Titel nach Bestätigung', 'success_text' => 'Text nach Bestätigung', 'subscribe_text' => 'Newsletter bestätigt', 'visit_text' => 'Besuch bestätigt', 'waiting_list_text' => 'Warteliste bestätigt', 'contact_text' => 'Kontakt bestätigt', 'error_title' => 'Fehlertitel', 'home_link_label' => 'Link zur Startseite', 'missing_token' => 'Fehlender Bestätigungscode', 'invalid_token' => 'Ungültiger Bestätigungscode', 'required_field' => 'Pflichtfeld fehlt', 'privacy' => 'Datenschutz nicht akzeptiert', 'email' => 'Ungültige E-Mail-Adresse', 'membership' => 'Ungültige Mitgliedschaft', 'captcha' => 'Fehlende Sicherheitsprüfung', 'generic' => 'Allgemeiner Fehler', 'missing_fields' => 'Pflichtfelder fehlen', 'mail_failed' => 'E-Mail-Versand fehlgeschlagen', 'newsletter_intro' => 'E-Mail-Text vor dem Bestätigungslink', 'newsletter_expiry' => 'Gültigkeitsdauer des Links', 'newsletter_ignore' => 'Hinweis bei fremder Anmeldung', 'newsletter_subject' => 'Betreff der Bestätigungs-E-Mail', 'newsletter_greeting' => 'Anrede der Bestätigungs-E-Mail', 'newsletter_greeting_anonymous' => 'Anrede ohne Namen'];
    echo '<div class="wrap"><h1>Formulartexte</h1><p>Leere Felder zeigen keine Meldung. In der E-Mail-Anrede steht {name} für den Namen.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="bioco_save_form_messages">';
    wp_nonce_field('bioco_save_form_messages');
    foreach (bioco_forms_message_schema() as $group => $keys) {
        echo '<h2>' . esc_html($groups[$group]) . '</h2><table class="form-table">';
        foreach ($keys as $key) {
            $id = $group . '-' . $key;
            echo '<tr><th><label for="' . esc_attr($id) . '">' . esc_html(($labels[$key] ?? $key)) . '</label></th><td>';
            $required = $group === 'doi-confirm' && in_array($key, ['confirmation_prompt', 'confirmation_button_label'], true) ? ' required' : '';
            echo '<textarea' . $required . ' class="large-text" rows="2" id="' . esc_attr($id) . '" name="messages[' . esc_attr($group) . '][' . esc_attr($key) . ']">' . esc_textarea(bioco_forms_message($group, $key)) . '</textarea></td></tr>';
        }
        echo '</table>';
    }
    submit_button('Texte speichern');
    echo '</form></div>';
}

add_action('admin_post_bioco_save_form_messages', function () {
    if (!current_user_can('edit_pages')) wp_die('Keine Berechtigung.', '', ['response' => 403]);
    check_admin_referer('bioco_save_form_messages');
    $messages = bioco_forms_sanitize_messages(wp_unslash($_POST['messages'] ?? []));
    foreach (['confirmation_prompt', 'confirmation_button_label'] as $key) {
        if (trim($messages['doi-confirm'][$key]) === '') {
            wp_die('Bestätigungsaufforderung und Button-Text dürfen nicht leer sein.', '', ['response' => 400]);
        }
    }
    update_option('bioco_form_messages', $messages, false);
    wp_safe_redirect(admin_url('admin.php?page=bioco-form-messages&saved=1'));
    exit;
});
