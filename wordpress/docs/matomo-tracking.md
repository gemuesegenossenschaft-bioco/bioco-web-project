# Safe Matomo pageviews (#223)

The WordPress integration records one pageview per document navigation after
analytics consent. It keeps #154's default-off state, local storage, withdrawal
and cross-tab notifications. Withdrawing before the tracker drains its queue
cancels the pending pageview. Re-granting after a sent pageview does not count it
again. Cookies stay disabled. This policy supersedes the automatic link-tracking
description in `wordpress/PRODUCTION-CUTOVER.md`. No form events or automatic outlink/download
tracking are enabled, because those links can carry unreviewed query values.

## URL policy

Before tracker loading, `setCustomUrl` and `setReferrerUrl` receive sanitized
absolute HTTP(S) URLs. Empty or invalid referrers become an explicit empty value.
Credentials, fragments and unreviewed query keys disappear. Email-bearing or
nested percent-encoded paths collapse to the host root. Page paths use one
trailing slash; surviving query keys sort alphabetically. This changes new
tracking data only, not the visitor's address bar or historical records.

The reviewed query allowlist is:

| Use | Keys | Accepted values |
| --- | --- | --- |
| Public campaigns | `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `utm_id`; `mtm_source`, `mtm_medium`, `mtm_campaign`, `mtm_keyword`, `mtm_kwd`, `mtm_content`, `mtm_cid`, `mtm_group`, `mtm_placement` | Public labels, 1-80 ASCII letters/digits or `.`, `_`, `~`, `-`; first character letter/digit |
| Calculator tier | `abo` | `kein`, `halb-1-person`, `standard-2-3-personen`, `doppel-4-6-personen` |
| Calculator shares | `shares`, `additional` | 0-100, decimal integers; leading zeros removed |

Repeated keys are dropped. `utm_*` and `mtm_*` are not wildcard permissions.
Email addresses, encoded addresses, confirmation/unsubscribe tokens, click IDs,
search text and unknown parameters are discarded. Operators must use public
campaign labels shared by an audience, never recipient names or secret IDs.
The calculator keys and tier values come from
`blocks/pricing-calculator/view.js` and `blocks/membership-form/view.js`.

The tracker script always has `referrerPolicy="no-referrer"`. A `no-referrer` meta
policy is installed before that script only when the configured tracker URL
shares the page's origin, to prevent full page URLs leaking through HTTP `Referer`
headers. In that case, the meta policy also suppresses HTTP referrers on later
resource requests and outgoing navigation. Cross-origin trackers, including
production `https://matomo.bioco.ch`, leave the page policy unchanged. The server's
`Referrer-Policy: strict-origin-when-cross-origin` header limits their tracking
requests to the page origin. The sanitized `urlref` still supplies analytics attribution.
The command contract follows the
[Matomo JavaScript API](https://developer.matomo.org/api-reference/tracking-javascript).

## Exclusions and QA marker

The server does not enqueue Matomo for anyone with `edit_posts`, for WordPress
previews or Customizer previews. Ordinary logged-in members/subscribers still
qualify for tracking with analytics consent.

Both PHP and the browser exclude requests containing `et_fb`, `et_pb_preview`,
`preview_id`, `preview_nonce`, `customize_changeset_uuid`, `customize_theme`,
`customize_messenger_channel`, `release_check` or `release-check`. The last two
names provide compatibility spellings for release verification URLs; the repo
gates do not currently emit them. `preview=true` and `preview=1` are also
excluded; `preview=false` remains public.

Use **`bioco_qa=1`** as the standard QA marker. For a header-only HTTP gate, use
`X-Bioco-QA: 1`, which PHP also excludes. The query marker protects browser
verification even if anonymous HTML comes from a shared cache. The header must
reach PHP, and caches must bypass header-marked requests rather than share their
untracked response with public users. Editor responses must likewise bypass
shared caches. This marker is traffic classification, not authentication.

`tests/wordpress-staging-render-gate.sh` currently requests plain route URLs with
curl. `wordpress/scripts/release-wordpress-staging.sh` invokes that gate; neither
has a pageview QA marker. The opcache probe's `X-Bioco-Opcache-Token` is an
unrelated secret authentication header and must not become an analytics marker.
These scripts are outside this lane and were not edited. Staging remains excluded
by the existing production environment and canonical home-host gate.

Follow-up: add `bioco_qa=1` to future browser/render verification URLs, or the
QA header to uncached curl requests. Verify cache handling before using the
header on production. Do not add an unmarked production smoke visit to audience
counts.

## Isolated proof

Run `python3 -m pytest tests/test_wordpress_matomo.py tests/test_wordpress_matomo_requests.py tests/test_wordpress_consent.py -q`.

The PHP tests exercise the actual enqueue hook and capability/query/header
exclusions. Node executes the actual adapter against a fake `_paq` and consent
API and asserts exact commands. Synthetic newsletter confirmation and referrer
URLs contain distinct tokens and an email; none survive into those commands.

The HTTP tests execute the actual consent and adapter scripts in Chromium. A
local tracker transport double consumes the command API and sends browser fetch
requests. Every request is intercepted and fulfilled locally under `.example.test`.
Assertions cover exact `url`/`urlref` fields, no HTTP `Referer` for the script or
same-origin tracking requests, origin-only `Referer` for cross-origin tracking,
default off and no second pageview after withdrawal/re-grant. The Node tests also
verify that only same-origin trackers receive a meta policy, before the script.
This proves the adapter and browser transport boundaries;
verification against the deployed Matomo version remains an operator follow-up.
No production token, form submission or live analytics request is used.

## Operator follow-ups: hosts and historical reporting

No Matomo admin configuration, host reassignment or historical deletion is part
of this change. The existing WordPress gate still requires production and
`home_url()` host `bioco.ch`; it makes no new decision about other integrations.

Before restricting additional hosts in site 1, record this inventory from Matomo
admin settings and a host-grouped URL report. Do not export full token-bearing
URLs. Use host names and counts only.

| Inventory item | Evidence available in repo | Decision still needed |
| --- | --- | --- |
| Canonical `https://bioco.ch/` | WordPress enqueue gate and production routing | Confirm site 1 ownership and save the canonical-host report |
| Recipe site | Routing reserves `/rezepte`; issue #223 reports additional hosts | Identify its actual host(s), tracker owner/site ID and whether sharing is intentional |
| `www`, staging, previews and every other host in site 1 | WordPress excludes noncanonical configured home hosts and staging | Inventory other tracker installations and choose separate future site IDs or retained sharing |

Save a segment named `bioco.ch canonical production` with Page URL **starts with**
`https://bioco.ch/`. API equivalent:
`pageUrl=^https%3A%2F%2Fbioco.ch%2F`.
[Matomo segment operators](https://developer.matomo.org/api-reference/reporting-api-segmentation)
document the prefix operator and value encoding. Review the Pages report within
that segment: visit-level summaries can still include visits that span shared
hosts. Confirm the deployed segment/report behavior before treating it as an
exclusive count of canonical-host actions. Keep separate reports for recipe and
other intentional hosts and record the owner's decision before changing their
future tracking configuration.

For old `/page` and `/page/` variants, group their pageview totals in an exported
report without altering stored logs. Retain both raw and grouped totals and the
report date range. New pageviews already use the same slash convention.

Annotate **9-10 October 2026** as the audit/QA spike in site 1 and in any exported
report. Counts for that period include internal verification; they must not be
presented as entirely external visitors. These exclusions cannot classify old
unmarked requests retrospectively. Record the rollout date so reports distinguish
old mixed traffic from newly filtered pageviews.

Follow-up: verify one consented public pageview, no editor/builder/marked-QA
pageview, and synthetic confirmation sanitization using an isolated Matomo test
site. Verify the installed Matomo version and any server-side campaign/query
plugins before production acceptance. Do not use a live DOI token or send mail.
