# bioco WordPress (Bedrock) — `wordpress` branch → staging.bioco.ch

Self-hosted WordPress rebuild of bioco.ch: **Bedrock** + a native **block theme** + **ACF Blocks** (git-versioned via ACF Local JSON) + **theme.json** design tokens. Lives on the `wordpress` branch and deploys to **staging.bioco.ch**. Full plan: `../docs/prd-wordpress-migration.md` and epic **#73**. This is the skeleton (slices W2/W3); real blocks land from W4 onward.

> The `main` branch keeps the current Next.js + ProcessWire site untouched. Production cutover (`.htaccess` docroot flip) is a deliberate later step (W13) with instant rollback.

## Layout
```
wordpress/
├── composer.json          # WP core + plugins via Composer (core & plugins gitignored)
├── config/                # Bedrock config (secrets via .env, server/CI only)
├── web/                   # docroot (point the vhost here)
│   ├── wp/                # WP core (Composer-managed, gitignored)
│   └── app/               # wp-content: themes + mu-plugins in git; plugins/uploads gitignored
│       └── themes/bioco/  # the block theme (theme.json tokens, blocks/, acf-json/)
└── .env.example           # copy to .env on the server; never commit real .env
```

## Local dev
```bash
cd wordpress
composer install
cp .env.example .env   # fill DB + salts (https://roots.io/salts.html)
# DDEV (recommended): ddev config --docroot=web --project-type=wordpress && ddev start
```

## Deploy to staging
Pushing to the `wordpress` branch runs `.github/workflows/deploy-wordpress-staging.yml`: build off-server → rsync to the box (uploads never deleted, `.env` never shipped) → OPcache flush + smoke check.

**One-time server/admin setup required before the first successful deploy:**
1. Create the `staging.bioco.ch` subdomain with docroot at the Bedrock `web/` directory.
2. Create a `bioco_wp` MySQL DB + a least-privilege user; put credentials in the server-side `.env`.
3. Add the repo secrets: `STAGING_SSH_HOST`, `STAGING_SSH_USER`, `STAGING_SSH_KEY`, `STAGING_DEPLOY_PATH`, `ACF_PRO_KEY`. Until they exist the workflow no-ops with a warning instead of failing.
4. Generate salts and the server `.env` (`WP_ENV=staging`, `WP_HOME=https://staging.bioco.ch`, SMTP + Turnstile keys reused from the current stack).

## Design tokens
`web/app/themes/bioco/theme.json` mirrors the **current live** site values for pixel parity (post Phase-0 coherence, epic #72). Two value decisions are deliberately **deferred to design sign-off** (they change pixels): brand green (live `#2e7d32` vs logo `#39A933`) and the radius scale (`12/18/24` vs `6/12/18`). The real font is **DM Sans** (self-hosted `assets/fonts/dmsans-variable.woff2`), not Inter.

## Status
- [x] W2 Bedrock skeleton (this)
- [x] W3 (start) block theme shell + theme.json tokens
- [ ] W4 tracer bullet: Hero ACF block end-to-end + staging deploy proven
- [ ] W5–W13 remaining blocks, content, forms, SEO, cutover (see #88–#100)
