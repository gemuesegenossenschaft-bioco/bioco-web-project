# Handoff: WordPress staging bring-up

**Version 2.** Supersedes the v1 handoff written by a sandboxed session with no `ssh` and no route to
`cms.bioco.ch`. This revision was written from a machine that has both, so the items v1 listed as
"needs a real machine" are now either done or newly scoped with real evidence.

**Read first:** `/CLAUDE.md`, `/AGENTS.md` (hard project rules), then `wordpress/RUNBOOK-SOFTACULOUS.md`
(the operator checklist). This file explains *state, boundaries and acceptance*.

---

## 1. What changed since v1

### 1.1 The environment questions are answered

| v1 open question | Answer |
|---|---|
| Which host serves staging? | **One box, not two.** `193.33.128.160` *is* `cpanel07.tophost.ch`. `bioco.ch`, `cms.bioco.ch` and `staging.bioco.ch` all resolve there. The Novatrend/Tophost split in v1 §4.1 was a false distinction. |
| Does `staging.bioco.ch` exist? | Yes, already a cPanel subdomain. It currently returns **503** — no working docroot behind it. |
| Is WordPress installed? | Not for this project. `~/public_html/` holds the **legacy WordPress** (DB `markuss_bioco`, themes `attitude_modified` / `bioco` / `twenty*`, ACF **free**). `~/public_html/bioco_staging/` is a ProcessWire copy, not WP. |
| ACF Pro licence? | **No longer needed.** See §1.2. |
| Divi licence? | **None exists.** Searched every theme dir and unpacked both UpdraftPlus theme backups (2022 + 2024). Divi has never been on this account. See §1.3. |

### 1.2 ACF Pro is replaced by Secure Custom Fields — no purchase

**Secure Custom Fields (SCF)** is WordPress.org's official fork of ACF, maintained by Automattic.
Version 6.9.5 (7 Aug 2026) ships Repeater, Flexible Content, Options Pages, Gallery, Clone and ACF
Blocks V3 — the whole Pro feature set, GPL, free.

The port's ACF surface is small enough to make this a drop-in rather than a rewrite:

| Call | Occurrences | In SCF |
|---|---|---|
| `get_field()` | 268 | yes |
| `acf_get_fields()` / `acf_get_field_group()` | 4 | yes |
| `acf/settings/load_json` + `save_json` | 2 | yes |
| `block.json` → `"acf": {"mode", "renderTemplate"}` | every block | yes (`acf_is_acf_block_json()`, `acf_handle_json_block_registration()`) |

**The one thing still to verify on a real install:** our blocks are `apiVersion 2` with `"mode": "auto"`,
while SCF has moved to blocks V3. `acf_rendered_block_v3()` was *added* in 6.8 alongside V2 rather than
replacing it, so V2 should still load — but this is unproven until something renders. If blocks come
out empty, check this before anything else.

Saves ~USD 249/yr. v1 §3.2 is closed.

### 1.3 Divi is not required, and is not being bought

Divi was planned as *a* front-end, never *the* front-end. Invariant **#101** already forces every
block, field, form and CPT into `bioco-core` / `bioco-content` / `bioco-forms` / `bioco-import` as
mu-plugins, with a gate banning `get_template_directory()` / `get_stylesheet_directory()`. The theme is
a thin shell, and the repo already ships **`wordpress/web/app/themes/bioco`** — our own free block theme
with `theme.json`.

**Decision (owner, this session): bring staging up on the free `bioco` theme first.** Divi gets bought
only if editors turn out to want its page builder. `wordpress/web/app/themes/bioco-divi` stays in the
repo as the alternative path — do not delete it.

Divi is commercial software from Elegant Themes, downloadable only from a logged-in members account.
Copies available anywhere else are nulled builds: a licence violation and a common malware vector. Do
not source it that way for a live cooperative's site.

### 1.4 The seed export ran, and it was the first real test

v1 §4.6 could not run. It runs now, and it immediately exposed **three defects that were invisible
while it couldn't run**. This is the pattern to expect from every remaining "first contact" step —
treat their output as evidence, not formality.

1. **Component mapping gap (fixed, PR #103).** The 17 original seeds came from hardcoded Next.js JSX
   and carry `section_layout`. `/abos` and `/wir` were CMS-native from the start and carry
   `section_component` in snake_case. The importer knew none of those names, although every target
   block already existed. 20 sections silently planned as "rebuild by hand in Divi".
2. **acf-json gate gap (surfaced, PR #103).** `check-hardcoded-content.php` scanned only block
   templates. Editorial content sitting in ACF `default_value` passed through no gate — which is
   exactly where the nine depot contacts were hiding.
3. **Dropped images (open, §3.1 below).** `fetch-cms-seed.php` never writes `image_url`.

---

## 2. Current state

| Branch | HEAD | State |
|---|---|---|
| `main` | `1ea9e4b` | unchanged |
| `wordpress` | `9c6278b` | unchanged |
| `review/seeds-mapping-acf-defaults` | `eae00bb` | **PR #103 → `wordpress`, awaiting CodeRabbit** |

### Gates on PR #103

| Gate | Result |
|---|---|
| `php wordpress/scripts/check-seed-plan.php` | OK — 19 pages, 97 blocks, **0 skips** |
| `php wordpress/scripts/check-hardcoded-content.php` | OK (templates); acf-json opt-in, red with 130 |
| `php -l` across mu-plugins | clean |
| `grep get_template_directory\|get_stylesheet_directory` | no hits (#101 OK) |
| `cd wordpress && bash scripts/conformance.sh` | `gate OK` |
| `diff wordpress/content-seed/… cms/content-seed/…` | identical (parity holds) |
| `cd frontend && npx vitest run` | 524/524 |

`npx tsc --noEmit` reports 4× TS2802 in `frontend/tests/page-shell-tokens.test.ts`. **Pre-existing** —
PR #103 touches no `frontend/` file. v1's claim of "tsc clean" on `main` did not hold up.

---

## 3. Open work, in order

### 3.1 Images are being dropped — fix before any import

**This blocks a correct import.** `section-map.php:127` sideloads `$section['image_url']`.
`fetch-cms-seed.php` never writes that key — it writes only `image_alt`. Result: on `/wir` the CMS has
images on 6 sections (11 files, including `hof_team` ×3 and the `geisshof` gallery ×4) and every one is
silently dropped, while `image_alt` still gets written — alt text describing an image that is not there.

The exporter *warns* about the plural `images` arrays but drops the singular `image` with no warning at
all.

Do not be misled the way this session initially was: grepping the existing 19 seeds shows `image_url`
zero times, which looks like proof that `image_alt`-only is the contract. It proves nothing — none of
the existing seeds happen to have images. **The importer is the contract, not the sample.**

**Acceptance:** re-export both seeds; `image_url` present for all 6 `/wir` sections; the `images`
arrays for `gallery_strip` and `cards_grid` mapped rather than warned about; seed-plan still 0 skips.

### 3.2 Migrate the 130 editorial ACF defaults

The recursive acf-json scan is implemented and correctly separates presentation defaults (`gap`,
`rounded`, `columns_desktop`, `media_fit`) from editorial content. It currently finds **130 editorial
defaults across 15 field groups**:

| Group | Hits |
|---|---|
| `membership_form` | 38 |
| `pricing_calculator` | 21 |
| `waiting_list_form` | 10 |
| `doi_confirm`, `visit_day_form` | 9 each |
| `events_feed` | 8 |
| `event_signup_form` | 7 |
| `pricing_table` | 6 |
| `gallery`, `geisshof_map`, `saisonkalender`, `schnuppertage` | 4 each |
| `contact_form` | 3 |
| `subscribe_form` | 2 |
| `group_cards` | 1 |

Mostly form labels — content by the project's own definition, since a human would want to reword them
without a developer.

It runs **opt-in** via `--acf-json` until they are migrated. The success message states explicitly that
acf-json was not checked; a gate claiming more than it verifies is worse than no gate. **Do not write
these into `hardcoded-content-baseline.json`** — it stays `{}`.

The hard part: `doi_confirm`, `event_signup_form` and `gallery` have **no page seed to migrate into**.
Those need a real content target inventing before the defaults can move.

**Acceptance:** `check-hardcoded-content.php --acf-json` green, the flag deleted, the scan
unconditional. Re-prove non-vacuity by planting a German sentence as a `default_value` and watching it
fail.

### 3.3 Depot description line breaks — review this decision

The nine depot descriptions carried `\n` separating address·day / contact·website / notes. `api.php`
strips control characters from `section_config`, so those line breaks cannot survive the seed contract.
The parity test caught it immediately.

Rather than weaken the test, the three parts are now joined with ` · `, the separator already used
inside those same strings. No information lost, but the line structure is gone. **Flagged in PR #103 as
worth a second opinion** — if the rendering needs real line breaks, the fix belongs in how the depot
description is stored, not in the test.

### 3.4 Bring staging up

Owner has confirmed: **create a fresh staging docroot.** Do not touch `~/public_html/` (legacy WP), and
do not touch `intranet.bioco.ch`.

1. **Install WordPress** into the new docroot via Softaculous (`RUNBOOK-SOFTACULOUS.md` §1–2). PHP 8.2,
   non-obvious admin username, German site language. `staging.bioco.ch` already resolves and currently
   503s — point it at the new docroot.
   *Acceptance:* wp-admin reachable; note the absolute `wp-content` path.
2. **Plugins:** Secure Custom Fields + WP Mail SMTP.
   *Acceptance:* `wp plugin list` shows SCF active.
3. **Theme:** activate `bioco` (§1.3). Not Divi.
4. **Deploy our code:**
   ```bash
   bash wordpress/scripts/deploy-wp-code.sh --host=<host> --user=<user> \
        --wp-content=<docroot>/wp-content
   ```
   Dry-run is the default and writes nothing. Review, then re-run with `--apply`.

   > **The trap this handles:** vanilla WordPress auto-loads only *files* at the top level of
   > `wp-content/mu-plugins` and **silently ignores subdirectories**. Bedrock papers over this with its
   > autoloader; Softaculous has none. Without `wordpress/deploy/bioco-mu-loader.php` (which the deploy
   > script installs) the entire bioco block layer is absent and every page renders empty, with no
   > error anywhere. If pages come out blank, check this first.

   *Acceptance:* the script's own verification prints PASS for the loader, all four mu-plugins, the
   seed count, `uploads` still present, and `wp bioco import --help`.
5. **Import:**
   ```bash
   wp bioco import          # dry-run, writes nothing
   wp bioco import --apply
   wp bioco verify
   ```
   Idempotent; a second `--apply` reports "unchanged". Existing non-empty pages are skipped, never
   overwritten. **Read the dry-run report row by row** — a clean exit code is necessary but the rows are
   the actual evidence.
   *Acceptance:* `wp bioco verify` matches every field; 19 pages in wp-admin.
6. **Live services (#97):** submit all five forms plus the membership route and DOI confirmation;
   confirm SMTP delivery and Turnstile verification.

   > Watch the half-configured Turnstile case: a missing **site** key while the **secret** is set makes
   > every form unusable — the widget never renders, so no token is produced, so verification rejects
   > everything. Handled explicitly now, but the keys still have to be right.

### 3.5 Then W12–W13
SEO/redirects and cutover. Both depend on a working staging site. Cutover is a separate step
(`RUNBOOK-STAGING-DIVI.md` §6) and nothing before it touches `bioco.ch`.

---

## 4. Hard constraints — do not violate these

1. **Repo is PUBLIC.** Never commit a secret, token, password, `.env`, or licence key.
2. **No hardcoded content, no fallback content** (`CLAUDE.md`). An empty field renders nothing, never
   invented text. Block templates are at **zero tolerance** — keep them there.
3. **Do not modify `intranet.bioco.ch`**, the legacy WordPress in `~/public_html/`, or `bioco.ch`.
4. **Never let `--delete` reach `wp-content/uploads`, `wp-content/plugins`, WP core, or a theme we do
   not own.** Media loss on a live host is unrecoverable. `deploy-wp-code.sh` enforces this with a hard
   allowlist — do not route around it.
5. **If Divi is ever bought: never commit it.** `web/app/themes/Divi` is gitignored and both deploy
   paths exclude it from `--delete`. The licence key goes into wp-admin only — never the repo, a
   Markdown file, `.env`, a ticket, or a chat message.
6. **PHP comment trap:** never write the two characters `*/` inside a PHP block comment (a glob like
   `blocks/<star>/block.json` breaks `php -l`). This has bitten three times.
7. Staging only.

---

## 5. Review workflow — hard rule

**CodeRabbit reviews before every commit and push, looped until all findings are resolved.** Owner's
standing instruction, not a suggestion.

Because CodeRabbit reviews pushed PRs (its direct API is unreachable from sandboxed environments,
403 CONNECT), the loop runs as: branch off `wordpress` → push → open a PR against `wordpress` → resolve
every finding → only then land it. Nothing unreviewed reaches `wordpress`.

`.coderabbit.yaml` must exist on **every** reviewed branch. A review of `wordpress` once ran with
"Configuration used: defaults" until the file was present there, and it found the two most serious bugs
on that branch. Copilot was also used; all 4 of its findings are fixed.

PR **#102** (`wordpress` → `main`) is **review-only, not for merge** — it collects reviews. Do not merge
it without the owner's explicit say-so.

---

## 6. Things that will bite you

- **Seeds are a parity contract.** `wordpress/content-seed/*.json` are byte-identical to
  `cms/content-seed/*.json` and pin the ProcessWire migration *and* the Next.js parity tests. Change
  one, change both, and `diff` them before pushing.
- **`section_config` cannot hold control characters.** `api.php` strips them. Multiline text in a
  config value will be silently mangled — see §3.3.
- **OPcache.** After rsyncing any `.php`, PHP-FPM may serve stale bytecode. `php -r 'opcache_reset()'`
  on the CLI does **not** affect the web process. Symptom: your change appears to have no effect.
- **`wp bioco verify` compares recursively.** It used to compare with `(string)`, turning any array
  into the literal `"Array"` — every repeater compared equal, so it reported clean runs on content it
  never checked. Keep it recursive, and keep `"3"` matching `3`.
- **The importer writes ACF `default_value` explicitly**, including repeater rows, for fields the plan
  does not supply. Deliberate: an ACF block stores values in serialized attributes, and an absent key
  would depend on ACF-internal default substitution. Because templates carry no fallbacks, an
  unresolved default renders as a *missing heading*, not placeholder text.
- **Presentation defaults vs content.** `gap`, `rounded`, `columns_desktop`, `media_fit`, `variant`,
  `limit` may carry defaults. Words a human would reword may not.
- **Absence of a pattern in the samples is not evidence of the contract.** See §3.1.

---

## 7. Commit conventions

German prose explaining *why*, including what was verified and what was deliberately left out. A
message that only says what changed loses the reasoning that makes it reviewable. Retry network
failures with backoff (2s, 4s, 8s, 16s).
