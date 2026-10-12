# Adopt one saved form

The release owner uses `wp bioco form-adoption`. Preview is the default. No import, form submission, token confirmation or external adapter runs.

Choose an existing private directory below the host's `backups` directory, outside WordPress. A preview contains the complete old record and raw message options. Keep it private; the command requires directory mode 0700 and creates each new preview with mode 0600.

```sh
wp bioco form-adoption --post=14 --out=/home/bioco/backups/editor/contact.json
wp bioco form-adoption --action=apply --preview=/home/bioco/backups/editor/contact.json --expect=<preview-fingerprint> --user=<administrator>
wp bioco form-adoption --action=restore --preview=/home/bioco/backups/editor/contact.json --expect=<preview-fingerprint> --user=<administrator>
```

Review `after_content`, captured labels/options/defaults and `report` first. Waiting-list program1/2/3 remain unchanged and appear in `unconfirmed_options`. A new preview is required after any record or message-option edit. Apply/restore changes only the selected record's content. A second apply refuses safely instead of writing again.

After every saved instance in a group has native fields and hosted Divi save/reopen, responsive appearance and fake-integration regressions pass, preview retirement separately.

```sh
wp bioco form-adoption --groups=contact-form --out=/home/bioco/backups/editor/contact-retirement.json
wp bioco form-adoption --action=apply --preview=/home/bioco/backups/editor/contact-retirement.json --expect=<preview-fingerprint> --user=<administrator>
```

The inventory includes drafts and saved layouts. Remaining legacy instances block retirement. The separate option CAS retains archived `bioco_form_messages` and all shared backend texts. Rollback retirement first, then restore page previews. User edits made after adoption block page rollback and must be reconciled explicitly.

Native controls preserve backend identities. Membership preserves arbitrary step rows, calculator handoff, checkbox choices, payment default, summary and same-origin thank-you destination. DOI preserves all five outcomes and per-form success copy. Link-scanner GETs remain inert; only explicit nonce-protected POST confirms a token. Public token/subscriber tests are forbidden. Local fake adapters cover transport and lifecycle; licensed hosted editor checks remain separate evidence.
