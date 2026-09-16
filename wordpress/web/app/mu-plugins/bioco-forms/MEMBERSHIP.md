# Membership staging contract

The six-step wizard stays on the WordPress site. It checks required fields per
step, retains input when moving back or when a request fails, and submits one
stable random `submissionId` across retries. The server validates again.

The adapter accepts only fake mode on a non-production environment or the
explicit staging hostname. No live intranet transport is shipped in this slice;
production activation belongs to #100. Fake submissions send no notification
mail and do not create production memberships.

Staging setup after the code release:

```sh
wp option update bioco_membership_adapter fake
wp option update bioco_membership_fake_result accepted
```

`bioco_membership_fake_result` supports `accepted`, `validation`, and
`unavailable`. These settings are server-side options, never request parameters.
The REST response is respectively 200, 400, or 502. Return the fixture to
`accepted` after testing.

Install missing editable labels with `bioco_forms_seed_messages()` and the
released `content-seed/block-content/defaults.json`. This preserves existing
values, including intentionally empty ones. Labels are edited under Formulartexte.

An atomic option insertion claims each request identity. Accepted receipts and
a payload hash are retained before notification handling; retrying returns the
same receipt without another adapter call. Reusing an accepted identity with
different data fails validation. Pending claims fail closed if acceptance is
unknown. The receipt store contains no names, addresses or raw form payloads.

The server maps address fields to `addr_street`, `addr_zipcode`, `addr_location`,
and includes `phone`, `mobile_phone`, `email`, `birthday`, `comment`, and `agb`.
