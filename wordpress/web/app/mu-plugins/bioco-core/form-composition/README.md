# Native form composition

Code deployment adds these parts. It does not replace saved pages. The fixed modules remain available until guarded adoption and hosted checks pass.

1. Give an ordinary Divi section the CSS class `bioco-form-scope`.
2. Insert one Formular-Verbindung module. Choose `contact`, `subscribe`, `visit-day`, `waiting-list`, `event-signup` or `membership` and save its instance messages.
3. Add Formularfeld modules for the required identities shown in the controller's backend contract. Labels, help, placeholders and selection rows belong to each field. A backend identity fixes input type and required rules. Selection values must come from confirmed current content; there are no invented waiting-list defaults.
4. Arrange fields in native rows and columns. Use their Design controls for input, label and help. Ordinary native headings, text, images and buttons can be added anywhere.
5. Add one submission Formular-Button. Its normal and pending labels and decoration are saved in Divi.
6. Mark field and action rows `bioco-form-fields`. Native rows marked `bioco-form-state-success`, `bioco-form-state-error` or `bioco-form-state-pending` own the outcome layout. A saved error row takes precedence over the controller's plain message. An optional `error-details` action shows backend error detail inside that layout. Field errors still identify and describe the affected input.

Each section owns its configuration. The runtime associates its independently arranged controls with one HTML form. Missing or wrong identities, duplicate scalar fields, missing actions and ambiguous controllers keep the section disabled. Fields are enabled only after the existing submission listener mounts. The endpoint, CAPTCHA, validation, storage and integrations stay in the existing plugins.

## Membership

Give each ordinary native step row `bioco-form-step`. Steps may contain information only, and the count is unrestricted. Keep each membership field inside a step and avoid nested step rows. Add one action each for `back`, `next`, `progress` and `submit`; progress copy uses `{current}` and `{total}`. Configure the controller's commitment count to match its required commitment checkbox modules.

Formularwert modules show selected field values in independently arranged review elements. Their labels and decoration belong to Divi. The aggregate `summary` action remains available. Back navigation preserves values; server field errors reveal their step; retries keep the same submission ID. A saved thank-you URL controls navigation, or the section can show a native success row. Shared price-catalog keys map to the existing backend subscription types. Before catalog adoption, the confirmed legacy calculator keys remain supported; an adopted empty catalog stays empty. The built-in `kein` handoff selects shares-only membership.

## Newsletter confirmation

Use a native section marked `bioco-doi-scope` with one Newsletter-Bestätigung controller. Add ordinary native rows for `bioco-doi-state-missing`, `bioco-doi-state-prompt`, `bioco-doi-state-success`, `bioco-doi-state-invalid` and `bioco-doi-state-failure`. The prompt row contains one `confirm` Formular-Button. Native text, headings, links and layout controls own each result, including its destination links.

GET renders a confirmation prompt without consuming the token. Only explicit nonce-protected POST calls the existing confirmation backend. Invalid, expired and already-used tokens retain its shared invalid result. Duplicate controllers evaluate the result once per request. Token-bearing pages disable caching before theme output. Editor renders never evaluate a token or emit an active nonce form.

## Candidate preparation and adoption

These helpers return native block arrays and perform no database writes:

- `bioco_form_capture_presentation(group, currentValues)` captures effective saved legacy text, including deliberate empty values, before adoption.
- `bioco_form_compose(type, capturedValues, capturedFields)` preserves supplied field order and refuses incomplete contracts.
- `bioco_membership_compose(capturedValues, capturedSteps)` supports information-only steps, field groups and independently arranged review fields.
- `bioco_doi_compose(capturedStateChildren, capturedButtonLabel)` preserves ordinary native children for every confirmation state.

The release owner must export the current page and effective message values, build a private candidate, verify native save/reopen and compare current fingerprints before replacing content. Do not use seed import.

Only after every saved instance of a presentation group has been adopted may the guarded migration add that group to `bioco_divi_form_message_groups`. This retires its Formulartexte controls and overrides. Archived text stays stored, survives later backend-message edits and reseeding, and resumes on rollback when the group is removed from the opt-in. `shared` validation and email messages cannot be retired through this option. Native controller messages already take precedence over legacy overrides.

Local Chromium tests execute actual PHP parts and browser scripts with intercepted traffic. They prove association, payloads, wizard traversal, values, errors, retries and terminal states. Local token fixtures execute the real backend against fake storage and mail. Hosted acceptance still requires add/reorder, columns, decoration, save/reopen and duplication on unpublished copies at desktop/mobile widths, cleanup and unchanged public fingerprints. Production checks make no submission and consume no real token.
