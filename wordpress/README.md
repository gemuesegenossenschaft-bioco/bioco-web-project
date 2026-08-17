# bioco WordPress (Bedrock) — `wordpress` branch → staging.bioco.ch

> **Inbetriebnahme:** Schritt-für-Schritt-Anleitung für staging.bioco.ch inkl. Divi-Lizenz und manuellem Inhalts-Import: [`RUNBOOK-STAGING-DIVI.md`](RUNBOOK-STAGING-DIVI.md). Architektur-Umbau (Theme austauschbar): [`PORTING-THEME-SWAP.md`](PORTING-THEME-SWAP.md) + [`HARDCASES.md`](HARDCASES.md) (#101).


Self-hosted WordPress rebuild of bioco.ch: **Bedrock** + theme-agnostic mu-plugins for blocks/ACF/
content/forms + a **Divi 5** presentation theme + **theme.json**-derived design tokens. Lives on the
`wordpress` branch and deploys to **staging.bioco.ch**. Full plan: `../docs/prd-wordpress-migration.md`,
epic **#73**, and **#101** (theme-agnostic restructure — see `PORTING-THEME-SWAP.md` /
`HARDCASES.md`).

> The `main` branch keeps the current Next.js + ProcessWire site untouched. Production cutover (`.htaccess` docroot flip) is a deliberate later step (W13) with instant rollback.

## Layout
```
wordpress/
├── PORTING-THEME-SWAP.md        # #101: theme -> plugin porting pattern catalogue
├── HARDCASES.md                 # #101: non-mechanical porting cases
├── composer.json                # WP core + plugins via Composer (core & plugins gitignored)
├── config/                      # Bedrock config (secrets via .env, server/CI only)
├── web/                         # docroot (point the vhost here)
│   ├── wp/                      # WP core (Composer-managed, gitignored)
│   └── app/                     # wp-content: themes + mu-plugins in git; plugins/uploads gitignored
│       ├── mu-plugins/
│       │   ├── bioco-core/      # theme-agnostic blocks + ACF + shared helpers + block CSS (#101)
│       │   ├── bioco-content/   # CPTs (event, bioco_group)
│       │   ├── bioco-forms/     # form REST handlers, Turnstile, DOI, mail
│       │   └── bioco-import/    # JSON seeds -> section plan -> native Divi blocks
│       └── themes/
│           ├── bioco/           # fallback/reference block theme (theme.json token source only)
│           ├── bioco-divi/      # active theme: thin Divi 5 child (presentation only)
│           └── Divi/            # gitignored — licensed, installed via wp-admin, see bioco-divi/README.md
└── .env.example                 # copy to .env on the server; never commit real .env
```

## Theme architecture (#101)

Blocks, ACF field groups, shared render helpers, and block CSS are **theme-agnostic** — they live in
the `bioco-core` mu-plugin and work under any active theme. The active presentation theme is
`bioco-divi` (Divi 5, licensed separately — see `web/app/themes/bioco-divi/README.md` for the manual
install steps). The original `bioco` block theme is kept as a fallback/reference (see its own
`README.md`) and remains fully functional on its own if Divi is ever deactivated. See
`PORTING-THEME-SWAP.md` for the porting patterns and `HARDCASES.md` for the non-mechanical cases
(most notably: design tokens are duplicated as static CSS in `bioco-core/assets/bioco-tokens.css`
since Divi has no `theme.json` to generate them).

## Content import

`content-seed/*.json` and `content-seed/block-content/defaults.json` are the editorial source.
`bioco-import` resolves them into a section plan and composes native Divi blocks. The verifier
compares that same composed tree with stored page content. The retired ACF block serializer and
ACF JSON default fallback are not part of the import path.

## Local dev
```bash
cd wordpress
composer install
cp .env.example .env   # fill DB + salts (https://roots.io/salts.html)
# DDEV (recommended): ddev config --docroot=web --project-type=wordpress && ddev start
```

## Deploy to staging
The primary staging path is the existing Softaculous WordPress installation plus `scripts/deploy-wp-code.sh`; follow `RUNBOOK-SOFTACULOUS.md`. It deploys only repository-owned code into `wp-content/` and leaves WordPress core, the database, uploads, and admin access under Softaculous control.

Bedrock plus `.github/workflows/deploy-wordpress-staging.yml` remains the alternative/CI path: build off-server → rsync to the box (uploads never deleted, `.env` never shipped) → OPcache flush + smoke check.

**One-time Bedrock server/admin setup required before the first workflow deploy:**
1. Create the `staging.bioco.ch` subdomain with docroot at the Bedrock `web/` directory.
2. Create a `bioco_wp` MySQL DB + a least-privilege user; put credentials in the server-side `.env`.
3. Add the repo secrets: `STAGING_SSH_HOST`, `STAGING_SSH_USER`, `STAGING_SSH_KEY`, `STAGING_DEPLOY_PATH`, `ACF_PRO_KEY`. Until they exist the workflow no-ops with a warning instead of failing.
4. Generate salts and the server `.env` (`WP_ENV=staging`, `WP_HOME=https://staging.bioco.ch`, SMTP + Turnstile keys reused from the current stack).

## Design tokens
`web/app/themes/bioco/theme.json` mirrors the **current live** site values for pixel parity (post Phase-0 coherence, epic #72) and remains the canonical source of these values. Two value decisions are deliberately **deferred to design sign-off** (they change pixels): brand green (live `#2e7d32` vs logo `#39A933`) and the radius scale (`12/18/24` vs `6/12/18`). The real font is **DM Sans** (self-hosted `assets/fonts/dmsans-variable.woff2`), not Inter.

`web/app/mu-plugins/bioco-core/assets/bioco-tokens.css` duplicates these same values as static
`--wp--*` CSS custom properties (#101, `HARDCASES.md` Hard Case 1) — required because Divi has no
`theme.json` to generate them the way the block theme does. If a token value ever changes, update
both files.

## Status
- [x] W2 Bedrock skeleton (this)
- [x] W3 (start) block theme shell + theme.json tokens
- [ ] W4 tracer bullet: Hero ACF block end-to-end + staging deploy proven
- [ ] W5–W13 remaining blocks, content, forms, SEO, cutover (see #88–#100)
