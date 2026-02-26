# Novatrend Migration Finish: Executable Status Plan

## Goal

Finalize secure cutover from Vercel to Novatrend cPanel and remove migration leftovers.

## Status Snapshot (2026-02-26)

- Done: standalone build + deploy tooling (`.cpanel.yml`, `scripts/deploy.sh`, test scripts)
- Done: frontend migration artifacts (`frontend/server.js`, `frontend/middleware.ts`, redirects, favicon, error pages)
- Done: CORS allowlist behavior in `site/templates/api.php`
- Done now: removed plaintext secret fallbacks in `site/config.php`
- Done now: production debug controlled by env (`PW_DEBUG`)
- Done now: production frontend internal CMS base URL set to `http://localhost/cms`
- Pending (remote/manual): cPanel env vars, Node restart, DNS cutover, external verification
- Pending (optional cleanup): remove unused migration/bootstrap scripts from server after final validation

## Executed In This Pass

- `site/config.php`
  - `dbPass`, `apiKey`, `githubToken` fallback values removed
  - `debug` changed to env-driven: `PW_DEBUG=1` enables debug, default off
- `.env.example`
  - added `PW_DEBUG=0`
- `frontend/.env.production`
  - fixed `PROCESSWIRE_BASE_URL=http://localhost/cms`

## Remaining Plan (ordered)

1. cPanel production env sync
- Set: `PW_DB_PASS`, `PW_API_KEY`, `PW_GITHUB_TOKEN`, `PW_DEBUG=0`
- Set existing frontend/API/SMTP env vars in Node.js app

2. Deploy + restart
- Local build first
- Rsync in order: `.next/standalone/`, `.next/static/`, `public/`
- Restart Node.js: `pkill -f "node.*server.js"; sleep 2; /home/bioco/bioco-frontend/start.sh`

3. External verification
- Direct: `http://193.33.128.160:49152`
- Proxy path: `curl --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/`
- Playwright: `frontend/tests/go-live.spec.ts` and `frontend/tests/content-renders.spec.ts`

4. DNS cutover
- Point `bioco.ch` to `193.33.128.160`
- Re-verify `dig bioco.ch` and HTTPS responses

5. Post-cutover cleanup
- Delete no-longer-needed migration/bootstrap scripts from server
- Keep only reusable operational scripts in repo
