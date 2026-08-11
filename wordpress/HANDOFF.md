# Handoff: WordPress staging bring-up

**For:** a Claude Code instance (or developer) with **server access** — SSH to the hosting, and network
that resolves `cms.bioco.ch`. The remote session that produced this branch had neither, which is why
the remaining work is exactly the work that touches a real machine.

**Read first:** `/CLAUDE.md`, `/AGENTS.md` (hard project rules), then `wordpress/RUNBOOK-SOFTACULOUS.md`
(the operator checklist). This file explains *state, boundaries and acceptance* — the runbook has the
click-by-click steps.

---

## 1. Where things stand

| Branch | HEAD | State |
|---|---|---|
| `main` | `1ea9e4b` | tsc clean, 560/560 vitest, `next build` 32/32 |
| `wordpress` | `ac61384` | 16 commits ahead of `main`. All gates green (§5) |

The WordPress code is **complete and gated but has never run against a live WordPress** — there is no
WP install, no MySQL and no PHP-FPM in the environment that wrote it. Everything below is verified
statically (lint, JSON validity, ACF key uniqueness, seed→block→field-group resolution) and nothing is
verified dynamically. **The first `wp bioco import` dry-run is the first real test.** Treat its output
as evidence, not as a formality.

### What is deliberately in mu-plugins, not the theme
Invariant **#101**: Divi must be swappable. Content, blocks, ACF fields and forms live in
`bioco-core` / `bioco-content` / `bioco-forms` / `bioco-import`. mu-plugin code must never call
`get_template_directory()` or `get_stylesheet_directory()` — there is a gate for that (§5).

---

## 2. Hard constraints — do not violate these

1. **Repo is PUBLIC.** Never commit a secret, token, password, `.env`, or licence key.
2. **Divi is licensed. Never commit it.** `web/app/themes/Divi` is gitignored, and both deploy paths
   exclude it from `--delete`. Do not "fix" that exclusion away.
3. **No hardcoded content, no fallback content** (`CLAUDE.md`). An empty field renders nothing, never
   invented text. The block templates are currently at **zero tolerance** — keep them there.
4. **Do not modify `intranet.bioco.ch`** in any way.
5. **Never let `--delete` reach `wp-content/uploads`, `wp-content/plugins`, WP core, or a theme we do
   not own.** Media loss on a live host is unrecoverable. `deploy-wp-code.sh` enforces this with a hard
   allowlist — don't route around it.
6. **PHP comment trap:** never write the two characters `*/` inside a PHP block comment (a glob like
   `blocks/<star>/block.json` breaks `php -l`). This has bitten twice.
7. Staging only. **Nothing in this handoff touches `bioco.ch`.** Cutover is a separate, later step
   (`RUNBOOK-STAGING-DIVI.md` §6).

---

## 3. Two decisions that belong to the repo owner

Do **not** resolve these unilaterally.

### 3.1 Depot contact names in a public repo
`bioco-core/acf-json/group_bioco_block_depot_map.json` — the `locations` repeater's `default_value`
carries **nine real contact persons' names plus addresses**.

- It is **pre-existing on `main`**: `frontend/components/DepotMap.tsx:25` has hardcoded the same nine
  contacts for a long time. The WordPress port reproduced it faithfully; it was not introduced here.
- The data is already published on bioco.ch, so it is not secret. What changes is that a GDPR
  erasure/correction request becomes a code deploy, and git history retains it regardless.
- It is also *fallback content*, which the project rule forbids.

Options put to the owner: (a) leave it, (b) move the depots into
`content-seed/standorte-depots.json` so the importer writes them as real content and the ACF default
goes away — **note this edits a parity-contract seed, see §6**, (c) drop only the contact names.

**Until the owner answers, leave that file untouched.**

### 3.2 ACF Pro licence
`wp bioco import` **cannot run without ACF Pro active.** The importer resolves ACF field keys through
ACF's own API (`acf_get_field_group()` / `acf_get_fields()`) rather than reimplementing ACF's internal
clone-key algorithm — that was a deliberate choice, because guessing those keys silently produces
values WordPress does not recognise. The CLI refuses up front if ACF is missing.

If the cooperative does not hold an ACF Pro licence, that is a purchase and it blocks everything from
§4.4 onward.

---

## 4. The work, in order

Each step has an acceptance check. Do not proceed past a failing one.

### 4.1 Confirm the target host
The team has hosting at more than one provider. A screenshot confirmed **Softaculous on
`cpanel07.tophost.ch`** with *no* WordPress installed, while `CLAUDE.md` documents the Novatrend box
(`193.33.128.160`) for the current site. **Confirm with the owner which host serves
`staging.bioco.ch`** before deploying. Nothing here hardcodes a host — every path is a parameter.

### 4.2 Softaculous install
`RUNBOOK-SOFTACULOUS.md` §1–2. Subdomain, PHP 8.2, install into the staging docroot (**not** the live
docroot), non-obvious admin username, German site language.

> **Why Softaculous and not Bedrock:** Softaculous installs *vanilla* WordPress (`wp-content` layout,
> no Composer, no `.env`). Our code is layout-agnostic, so Softaculous owns core/DB/admin user and we
> ship only our own code. The Bedrock tree and `.github/workflows/deploy-wordpress-staging.yml` remain
> as the alternative path — do not delete them.

**Acceptance:** wp-admin reachable; note the absolute `wp-content` path.

### 4.3 Plugins
ACF **Pro** (see §3.2) and WP Mail SMTP. Divi is a *theme*, installed in §4.5.

**Acceptance:** `wp plugin list` shows ACF Pro **active**.

### 4.4 Deploy our code
```bash
bash wordpress/scripts/deploy-wp-code.sh --host=<host> --user=<user> \
     --wp-content=<docroot>/wp-content
```
Dry-run is the default and writes nothing. Review, then re-run with `--apply`.

> **The trap this handles:** vanilla WordPress auto-loads only *files* at the top level of
> `wp-content/mu-plugins` and **silently ignores subdirectories**. Bedrock papers over this with its
> autoloader; Softaculous has none. Without `wordpress/deploy/bioco-mu-loader.php` (which the deploy
> script installs) the entire bioco block layer would be absent and every page would render empty —
> with no error anywhere. If pages come out blank, check this first.

**Acceptance:** the script's own post-deploy verification prints PASS for the loader, all four
mu-plugins, the seed count, `uploads` still present, and `wp bioco import --help`.

### 4.5 Divi + child theme
Upload the Divi zip via `Design → Themes → Theme hochladen`. Activate the **child** theme
`bioco-divi`, never `Divi` itself. Enter the licence under `Divi → Theme Options → Updates`.

**The licence key goes into the WordPress database via wp-admin only** — never into the repo, a
Markdown file, `.env`, a ticket, or a chat message. Do not ask the owner to paste it anywhere.

**Acceptance:** `bioco-divi` active with `Divi` as parent; Divi update check succeeds.

### 4.6 Export the two missing pages — do this BEFORE importing
`/abos` and `/wir` are live pages with **no seed file**. Cause: the 17 seeds capture content that used
to be hardcoded in the Next.js JSX; those two were CMS-native from the start (`/abos` is the reference
page), so they never had hardcoded content and never got a seed. Their content exists **only in
ProcessWire**.

Skip this and the import produces a site missing two pages, with dead internal links from `/solawi`.

```bash
php wordpress/scripts/fetch-cms-seed.php --slug=abos
php wordpress/scripts/fetch-cms-seed.php --slug=wir
```
Needs network to `cms.bioco.ch`. If only another machine reaches it, the error message prints the
`curl` + `--from-file` detour. The script never invents content, refuses to overwrite an existing
seed, and **reports** any unmapped API field instead of dropping it — if you see that warning,
check whether content is hiding in it.

**Acceptance:** `php wordpress/scripts/check-seed-plan.php` is green and reports **19 pages** (not 17).
Commit the two new seeds.

### 4.7 Import
```bash
wp bioco import                 # dry-run, writes nothing
wp bioco import --apply
wp bioco verify
```
Report: CLI table + `wp-content/bioco-import-log/`. Idempotent — a second `--apply` reports
"unchanged". Existing non-empty pages are skipped, never overwritten; `--force` overrides and is
rejected without `--apply`. Any error row exits non-zero.

**Read the dry-run report properly.** This is the importer's first contact with a real WordPress; a
clean exit code is necessary but the row-level output is the actual evidence.

**Acceptance:** `wp bioco verify` reports match for every field, and 19 pages exist in wp-admin.

### 4.8 Live-service verification (issue #97)
Only now, with real credentials on the server: submit each of the five forms plus the membership route
and DOI confirmation, and confirm SMTP delivery and Turnstile verification.

> Watch for the half-configured Turnstile case: a missing **site** key while the **secret** is set
> makes every form unusable — the widget never renders, so no token is produced, so verification
> rejects every submission. That case is now handled explicitly, but the keys still have to be right.

---

## 5. Gates — the definition of "green"

Run from the repo root unless noted. All must pass before you push.

```bash
# WordPress
find wordpress/web/app/mu-plugins -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v 'No syntax errors' || echo PHP_LINT_CLEAN
php wordpress/scripts/check-seed-plan.php          # SEED_PLAN_CHECK: OK — 17 pages / 75 blocks (19 after §4.6)
php wordpress/scripts/check-hardcoded-content.php  # zero tolerance; --list to see detail
grep -rn 'get_template_directory\|get_stylesheet_directory' wordpress/web/app/mu-plugins/ && echo VIOLATION_101 || echo OK_101
cd wordpress && bash scripts/conformance.sh        # manifest + F-SEED-PLAN block
bash -n wordpress/scripts/deploy-wp-code.sh

# Frontend (main)
cd frontend && npx tsc --noEmit && npx vitest run  # 560 passing
```

Two gates are **proven non-vacuous** — a planted bad block name fails `check-seed-plan.php`, and a
planted German sentence fails `check-hardcoded-content.php`. If you extend either, re-prove it the
same way; a gate that cannot fail is worse than no gate.

`check-hardcoded-content.php` has a baseline file. It is currently `{}` = **zero tolerance**. If you
widen the detector and new findings appear, **fix them** rather than writing them into the baseline.
An empty baseline is the goal state, not a missing one.

---

## 6. Things that will bite you

- **Seeds are a parity contract.** `wordpress/content-seed/*.json` are byte-identical to
  `cms/content-seed/*.json` and pin the ProcessWire migration *and* the Next.js parity tests. Changing
  one changes the meaning of those tests. New seeds from §4.6 are additions, which is fine.
- **OPcache.** After rsyncing any `.php`, PHP-FPM may serve stale bytecode. `php -r 'opcache_reset()'`
  on the CLI does **not** affect the web process — this was a real bug in the CI workflow, now replaced
  by an HTTP-triggered reset guarded by a secret. Symptom: your change appears to have no effect.
- **`wp bioco verify` compares recursively.** It used to compare with `(string)`, which turns any array
  into the literal `"Array"` — every repeater compared equal, so it reported clean runs on content it
  never checked. If you touch that comparison, keep it recursive and keep `"3"` vs `3` matching.
- **The importer writes ACF `default_value` explicitly**, including repeater rows, for fields the plan
  does not supply. This is deliberate: an ACF block stores values in its serialized attributes, and an
  absent key would depend on ACF-internal default substitution. Because the templates carry no
  fallbacks, an unresolved default renders as a *missing heading*, not placeholder text.
- **Presentation defaults vs content.** `gap`, `rounded`, `columns_desktop`, `media_fit`, `variant`,
  `limit` may carry defaults. Words a human would reword may not — those are fields.

---

## 7. Review tooling

- **CodeRabbit** is active on the org (Pro Plus) and reviews PRs to `main` and `wordpress`.
  `.coderabbit.yaml` exists on **both** branches — it must, because a review of the `wordpress` branch
  ran with "Configuration used: defaults" until the file was present there. It found the two most
  serious bugs on this branch.
- **Copilot** review was used; all 4 of its findings are fixed.
- PR **#102** (`wordpress` → `main`) is **review-only, not for merge**. It exists to collect reviews.
  Do not merge it without the owner's explicit say-so — the branch is meant to ship to staging first.
- CodeRabbit's own API is unreachable from a sandboxed environment (403 CONNECT); the GitHub App is
  the working channel.

---

## 8. What was NOT done, and why

| Item | Reason |
|---|---|
| Any deploy, import, or WP-CLI run | No `ssh` binary and no keys in the environment |
| Export of `/abos` + `/wir` seeds | `cms.bioco.ch` blocked by egress policy |
| Live SMTP / Turnstile verification (#97) | Needs the server and real credentials |
| Remaining slices W12–W13 (SEO/redirects, cutover) | Depend on a working staging site |
| Depot PII change | Owner decision (§3.1) |
| `link-tiles` tag-name escaping | False positive. `$tile_tag = $tile['href'] ? 'a' : 'div'` can only ever be one of those two literals — no input reaches it. Escaping it would be noise. Re-check only if that ternary changes |
| `/abos`, `/wir` internal links in `solawi.json` | Left as-is — inventing targets would have been fabricated content |

---

## 9. Commit and push conventions

- Develop on `wordpress`; push with `git push -u origin wordpress`. Retry network failures with
  backoff (2s, 4s, 8s, 16s).
- Do **not** open a PR unless asked. #102 already exists for review.
- Commit messages in this branch are German prose explaining *why*, including what was verified and
  what was deliberately left out. Match that — a message that only says what changed loses the
  reasoning that makes the change reviewable.
