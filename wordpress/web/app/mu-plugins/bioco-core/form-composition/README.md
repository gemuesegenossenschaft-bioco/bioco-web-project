# Native contact form composition

The new parts are separate native Divi modules. The existing fixed Contact Form module remains available for recovery. Code deployment does not replace saved page content.

1. Add the CSS class `bioco-form-scope` to an ordinary Divi section.
2. Insert one Formular-Verbindung module. Choose `contact` and set its instance messages.
3. Insert a Formularfeld module for each required backend identity: `contact.name`, `contact.email`, `contact.subject`, `contact.message`. `contact.phone` is optional.
4. Arrange these modules in ordinary native rows/columns. Labels, help and placeholders belong to each field. Its Design controls style the label, help and input separately. Add headings, text and other native elements anywhere in the section.
5. Insert one Formular-Button module and edit its normal/pending label and decoration.
6. Mark field rows `bioco-form-fields`. A native row containing ordinary text/headings/buttons can be marked `bioco-form-state-success`, `bioco-form-state-error` or `bioco-form-state-pending` for that outcome.

The controller owns endpoint selection and CAPTCHA. Backend identities fix type and required semantics. Wrong, missing or duplicate required fields, multiple controllers or missing submission actions keep the scope inert. Fields remain disabled until the existing lifecycle listener mounts. Each scope receives its own configuration and form association, including when a section is duplicated. The old Formulartexte option does not override the new controller's saved messages. Shared outcome content can use native Divi global rows; a scope's saved row supplies its visible result layout.

`bioco_form_compose(type, capturedValues, capturedFields)` builds a candidate block tree without saving it. It refuses missing required fields and preserves the supplied order/copy. Content adoption remains a separate conditional migration with a private before export, conflict checks and restoration. Do not call seed import for this change.

Local Chromium tests use the actual new PHP output, actual lifecycle and intercepted traffic. They prove form association, instance isolation, validation, errors/retry and terminal success. They do not prove the installed Divi editor. Required hosted acceptance is native add/reorder/column changes, save/reopen and duplication on unpublished copies at desktop/mobile widths, followed by fixture cleanup and unchanged public fingerprints. Production acceptance makes no submission.
