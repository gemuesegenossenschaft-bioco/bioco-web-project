# Membership adapters

The wizard submits a stable `submissionId` across retries. Turnstile and server
validation run on every request. The workflow is email plus manual registration.
There is no intranet transport.

Staging stays fake:

```sh
wp option update bioco_membership_adapter fake
wp option update bioco_membership_fake_result accepted
```

Fake mode accepts only test environments. Outcomes `accepted`, `validation`, and
`unavailable` return 200, 400, and 502. Fake mode sends no mail.

Production must explicitly enable local mode in its own WordPress database:

```sh
wp --path=/home/bioco/wordpress-production option update bioco_membership_adapter local
```

Local mode also requires environment `production` and `home` host `bioco.ch`.
It cannot accept on staging. Configure real Turnstile keys and the existing mail
transport privately. `BIOCO_FORMS_RECIPIENT` overrides the `info@bioco.ch` default.
`disabled` blocks new submissions; receipts replay only when their adapter is active.

Local acceptance inserts one non-autoloaded option containing the receipt,
acceptance status, complete normalized validated form data, creation time, and
notification status. CAPTCHA, the browser-added `cf-turnstile-response`, and checklist signature are
excluded. The browser transport field is accepted for compatibility. Lists,
consents, share counts, mobile phone, birthday, and all activity/product fields
survive. Unknown fields fail validation rather than disappearing silently.

The unique option name atomically binds each identity to its complete normalized
payload. Same-data retries return the same receipt. Changed data returns 400.
Acceptance precedes email. Only the insert winner attempts mail; retries never
resend. Mail false/exception leaves success with a receipt and `failed` for admins.
A crash before mail or a failed status write leaves `pending`. Review either state
manually; there is no automatic mail retry. Transport acceptance does not prove
inbox delivery.

Administrators with `manage_options` review registrations under Tools >
Mitgliedschaftsanmeldungen. The page is read-only, shows 50 records per page, and
escapes payload output. Data has no public REST route and is not logged. Protect
private database backups because they contain registration data. The production
options table must be its own InnoDB table under `bcp20261009_`; never point this
installation at staging tables.

Existing fake mode retains its mapped payload/receipt contract. Existing pending
fake claims remain closed when their outcome is unknown.
