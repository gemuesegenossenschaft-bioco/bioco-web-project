# bioco.ch

Swiss organic vegetable cooperative. Headless CMS: ProcessWire (PHP). Frontend: Next.js 14 (standalone). Both on Novatrend cPanel.

## Architecture

Single Novatrend cPanel server (193.33.128.160):
- **Next.js** standalone on port 49154, Apache `.htaccess` proxy via `RewriteRule [P]`
- **ProcessWire** via PHP-FPM (cms.bioco.ch)
- **MySQL** localhost:3306 (bioco_cms)
- **SMTP** mail.bioco.ch:465
- Cron job every 5min runs health check watchdog (`/home/bioco/bioco-frontend/healthcheck.sh`) which calls `start.sh` if needed

Next.js calls ProcessWire via `http://localhost/cms/api/*` (same server, no CORS).

## Server Setup (Novatrend cPanel / CloudLinux)

### Critical constraints
- **CloudLinux Passenger IS active** but unreliable for restarts. Use `pgrep -x next-server` + kill for process management.
- **PHP OPcache caches module files.** After rsyncing any `.php` file under `site/modules/`, the running PHP-FPM process may serve the old compiled bytecode. CLI `php -r "opcache_reset()"` does NOT affect the web PHP process. To force reload: place a PHP file accessible via `cms.bioco.ch` (outside PW's `.htaccess` routing) and call `opcache_invalidate($path, true)` or `opcache_reset()` from a web request. PW's `.htaccess` in `/cms/` intercepts all requests — put the reset file at the vhost root, not inside `/cms/`. Alternatively, wait for `opcache.revalidate_freq` to expire, or ask user to log out and back in to get a fresh PHP-FPM worker.
- **`npm run build` fails on server.** CloudLinux thread limits cause SWC/Rayon panics. Always build locally.
- **Node 18.20.8** (no Node 20+). Fine for Next.js 14.
- **Port 49154** used by Node.js. Apache proxies to it.
- **sharp needs two packages**: after `--delete` rsync, restore both `sharp-linux-x64` AND `sharp-libvips-linux-x64` from `/tmp/sharp-pkg/`. Remove darwin bindings.
- **`pgrep -f next-server`** in SSH will match the SSH command itself, killing the session. Always use `pgrep -x next-server`.
- **Cron race is real.** Even with `start.sh` guards, a restart window can leave a second `next-server`. After every deploy: `ps -eo pid,ppid,args | grep "next-server" | grep -v grep` and kill any extra PID.
- **`HTTP 200` is not enough.** Stale Next deploy mismatches can render the `Fehler` page with status 200 (`Failed to find Server Action`, `reading 'workers'`, `reading 'digest'`). Always run body smoke checks + log gate.
- **Cron PATH is minimal.** `start.sh` must set/export `PATH` before `flock` and use absolute `/usr/bin/flock`, `/usr/bin/curl`, `/usr/bin/pgrep`, `/bin/kill`. Otherwise cron can skip the guard checks and spawn duplicate workers.
- **CloudLinux Node Selector can spawn Passenger workers in parallel.** Check `~/.cl.selector/node-selector.json`. If an extra `next-server` has `IN_PASSENGER=1` in `/proc/<pid>/environ`, it came from Passenger/Selector, not `start.sh`.
- **Public route can bypass Next.** `.htaccess` checks for real files/dirs in `/home/bioco/public_html/` before proxying some slugs. A stray symlink like `/home/bioco/public_html/wir` can make external `/wir` fail while `http://127.0.0.1:49154/wir` still works.

### SSH access
```bash
ssh bioco@193.33.128.160
```

### Server paths
```
/home/bioco/
├── bioco-frontend/            # Deployed Next.js standalone
│   ├── server.js              # Next.js standalone entry
│   ├── start.sh               # Startup script with env vars (DO NOT rsync --delete)
│   ├── healthcheck.sh         # Watchdog: deep body check, calls start.sh (from repo)
│   ├── .next/                 # Compiled app + static assets
│   ├── public/                # Images, PDFs, fonts
│   └── node_modules/@img/     # sharp bindings (restore after deploy)
├── public_html/
│   ├── .htaccess              # Apache proxy: RewriteRule to 127.0.0.1:49154
│   ├── cms/                   # ProcessWire installation
│   │   └── site/templates/    # API + admin templates (deploy target for CMS files)
│   └── matomo/                # Matomo analytics
├── nodevenv/bioco-frontend/18/ # CloudLinux Node.js virtual env
└── logs/nextjs.log            # Node.js stdout/stderr
```

### Apache .htaccess proxy
```apache
RewriteRule ^(.*)$ http://127.0.0.1:49154/$1 [P,L]
```
`/cms` and `/matomo` excluded (served by Apache/PHP).

Current gotcha:
- `.htaccess` also short-circuits `/intranet`, `/statuten`, `/wir` if a matching file/dir exists in `public_html`.
- If external route != local app route, check:
```bash
ls -la /home/bioco/public_html/<slug>
curl -I -H "Host: bioco.ch" http://127.0.0.1:49154/<slug>
curl --resolve bioco.ch:443:193.33.128.160 -I https://bioco.ch/<slug>
```
- If the local app is `200` but external is not, remove the stray file/symlink in `public_html`.

### Health check watchdog
- `healthcheck.sh` (in repo at `scripts/healthcheck.sh`, deployed to `/home/bioco/bioco-frontend/healthcheck.sh`)
- Cron runs it every 5 min; it calls `start.sh` when the server needs starting or restarting
- Deep body check: detects error boundary content (`>Fehler</h1>` + `Etwas ist schiefgelaufen`) even when HTTP 200
- Double-check: curls twice with 2s gap to avoid false positives from transient errors
- Cooldown: skips restart if last restart was < 3 min ago (prevents restart loops)
- Logs to `/home/bioco/logs/healthcheck.log`

### Process management
- `start.sh` exports env vars, runs `node server.js` with `nohup`, uses flock to prevent duplicates
- Cron runs `healthcheck.sh` every 5 minutes, which calls `start.sh` as needed
- Safe restart: `for p in $(pgrep -a next-server | awk '{print $1}'); do kill "$p"; done; sleep 3; start.sh`
- Fallback restart if old workers remain: `for p in $(ps -eo pid,comm | awk '$2=="next-server"{print $1}'); do kill $p; done`
- Verify steady state after restart:
```bash
ps -eo pid,ppid,args | grep "next-server" | grep -v grep
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:49154/
```
- Expected: exactly one `next-server`, local health `200`

### Environment variables (set in start.sh)
`PORT`, `NODE_ENV`, `HOSTNAME`, `PROCESSWIRE_BASE_URL`, `PROCESSWIRE_API_KEY`, `PW_API_KEY`, `REVALIDATE_SECRET`, `NEXT_PUBLIC_PROCESSWIRE_BASE_URL`, `NEXT_PUBLIC_SITE_URL`, `NEXT_PUBLIC_MATOMO_URL`, `NEXT_PUBLIC_MATOMO_SITE_ID`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`, `NEXT_PUBLIC_TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`

**Critical ordering rule**: all `export` statements in `start.sh` MUST come before the `nohup node server.js` line. Anything exported after nohup is NOT inherited by the running process. This previously caused all forms to silently fail captcha verification (`TURNSTILE_SECRET_KEY is not configured` in logs) even though the keys appeared in `start.sh`. Verify: `tail /home/bioco/logs/nextjs.log | grep "is not configured"`.

`NEXT_PUBLIC_TURNSTILE_SITE_KEY` is also baked into the JS bundle at build time from `frontend/.env.production`. Both must stay in sync (Cloudflare dashboard > Turnstile > your site).

### CMS ISR settings (set in `site/config.php`)
`nextRevalidateSecret`, `nextRevalidateUrl`, `nextRevalidateDebounceSeconds`, `nextRevalidateMaxWaitSeconds`, `nextRevalidateQueueFile`

### Config contract (must stay in sync)
- `start.sh: REVALIDATE_SECRET` must equal `site/config.php: nextRevalidateSecret`.
- `site/config.php: nextRevalidateUrl` must be `http://127.0.0.1:49154/api/revalidate`.
- Debounce defaults: `nextRevalidateDebounceSeconds=10`, `nextRevalidateMaxWaitSeconds=45`.
- Queue file: `nextRevalidateQueueFile=/tmp/bioco-next-revalidate-state.json`.
- Do not use `.htaccess SetEnv` for these values on cPanel/lsapi.

### Caching + revalidate contract
- `frontend/middleware.ts`: security headers only; no `Cache-Control`, no framing headers (framing via `next.config.js` `headers()`).
- `frontend/next.config.js` `headers()`: CSP `frame-ancestors` for iframe protection. Do NOT use middleware for framing headers (ISR cache overwrites them).
- `frontend/lib/cmsClient.ts`: use `next.revalidate` + cache tags including `cms`.
- `frontend/components/AktuellesData.tsx`: client fetch uses `cache: 'no-store'`.
- `frontend/app/api/revalidate/route.ts`: accepts `path|paths|tag|tags|layout`, validates `REVALIDATE_SECRET`.
- `site/ready.php`: queue + debounce + trailing flush (`LazyCron::everyMinute`) for CMS save events.

## Deploy

Build locally, rsync frontend + CMS templates, restore sharp, restart.

**Full deploy (recommended):**
```bash
scripts/deploy.sh main
```

This script: builds frontend, rsyncs standalone/static/public, restores sharp bindings, rsyncs CMS templates (`admin.js`, `api.php`, `api-events.php`, `visual-editor.php`, `visual-editor-focus-fields.json`) + `site/ready.php`, restarts Node.js, verifies.

**Manual deploy:**
```bash
cd frontend && npm ci && npm run build
rsync -avzc --delete --exclude='start.sh' .next/standalone/ bioco@193.33.128.160:/home/bioco/bioco-frontend/
rsync -avzc --delete .next/static/ bioco@193.33.128.160:/home/bioco/bioco-frontend/.next/static/
rsync -avzc --delete public/ bioco@193.33.128.160:/home/bioco/bioco-frontend/public/
# Restore sharp (REQUIRED after standalone rsync)
ssh bioco@193.33.128.160 'cp -r /tmp/sharp-pkg/node_modules/@img/sharp-{linux-x64,libvips-linux-x64} /home/bioco/bioco-frontend/node_modules/@img/'
# CMS templates
rsync -avzc site/templates/{admin.js,api.php,api-events.php,visual-editor.php,visual-editor-focus-fields.json} bioco@193.33.128.160:/home/bioco/public_html/cms/site/templates/
# CMS hooks
rsync -avzc site/ready.php bioco@193.33.128.160:/home/bioco/public_html/cms/site/ready.php
# Restart
ssh bioco@193.33.128.160 'for p in $(pgrep -x next-server); do kill $p; done; sleep 3; rm -f /tmp/bioco-next-start.lock /tmp/bioco-next.pid; /home/bioco/bioco-frontend/start.sh'
# Fallback if old workers remain
ssh bioco@193.33.128.160 'for p in $(ps -eo pid,comm | awk '\''$2=="next-server"{print $1}'\''); do kill $p; done'
```

Post-deploy outage checks:
```bash
ssh bioco@193.33.128.160 'ps -eo pid,ppid,args | grep "next-server" | grep -v grep'
ssh bioco@193.33.128.160 'curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:49154/'
curl --resolve bioco.ch:443:193.33.128.160 -s -o /dev/null -w "%{http_code}\n" https://bioco.ch/
ssh bioco@193.33.128.160 'curl -s http://127.0.0.1:49154/ | rg -q "Fehler|Etwas ist schiefgelaufen" && echo "BAD: local error boundary rendered" || echo "OK: local html"'
curl --resolve bioco.ch:443:193.33.128.160 -s https://bioco.ch/ | rg -q "Fehler|Etwas ist schiefgelaufen" && echo "BAD: external error boundary rendered" || echo "OK: external html"
ssh bioco@193.33.128.160 'tail -n 200 /home/bioco/logs/nextjs.log | rg -q "Failed to find Server Action|reading '\''workers'\''|reading '\''digest'\''|Unexpected end of form" && echo "BAD: stale deploy errors in log" || echo "OK: no stale deploy errors in tail"'
curl --resolve bioco.ch:443:193.33.128.160 -s -o /dev/null -w "%{http_code}\n" https://bioco.ch/wir
ssh bioco@193.33.128.160 'ls -la /home/bioco/public_html/wir 2>/dev/null || true'
```
- If `/wir` external fails but local is healthy, remove `/home/bioco/public_html/wir` if it is a stale symlink.
- If two workers exist, kill the newer/stale extra PID and re-check health.

**CMS-only deploy** (no frontend build needed):
```bash
rsync -avzc site/templates/{admin.js,api.php,api-events.php,visual-editor.php,visual-editor-focus-fields.json} bioco@193.33.128.160:/home/bioco/public_html/cms/site/templates/
rsync -avzc site/ready.php bioco@193.33.128.160:/home/bioco/public_html/cms/site/ready.php
```

**Post-deploy checks (required):**
```bash
# Local app health
ssh bioco@193.33.128.160 'curl -s -o /dev/null -w "Local: HTTP %{http_code}\n" http://127.0.0.1:49154/'
# External app health
curl --resolve bioco.ch:443:193.33.128.160 -s -o /dev/null -w "External: HTTP %{http_code}\n" https://bioco.ch/
# Revalidate auth + route
ssh bioco@193.33.128.160 'CFG=/home/bioco/public_html/cms/site/config.php; SECRET=$(perl -nE '\''if (/nextRevalidateSecret\\s*=\\s*"([^"]+)"/) { say $1; exit }'\'' "$CFG"); PAYLOAD=$(printf "{\"secret\":\"%s\",\"path\":\"/\"}" "$SECRET"); curl -s -o /dev/null -w "Revalidate: HTTP %{http_code}\n" -X POST http://127.0.0.1:49154/api/revalidate -H "Content-Type: application/json" --data "$PAYLOAD"'
```

## URLs

- Production: https://www.bioco.ch
- CMS Admin: https://cms.bioco.ch/processwire/
- External test: `curl --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/`

## Key Files

### Frontend
| File | Purpose |
|------|---------|
| `frontend/app/*/page.tsx` | Next.js pages (SSG) |
| `frontend/components/AktuellesClient.tsx` | Aktuelles page: events grid, past events, modals |
| `frontend/components/AktuellesData.tsx` | Event data types, CMS API fetching, field mapping |
| `frontend/components/ItemDetailModal.tsx` | Event/aktuelles detail modal |
| `frontend/components/EventSignupForm.tsx` | Event signup form |
| `frontend/hooks/useEventsFeed.ts` | Client-side events hook |
| `frontend/lib/cmsClient.ts` | ProcessWire API client |
| `frontend/lib/processwire.ts` | PW page fetching, section rendering |
| `frontend/middleware.ts` | Security headers (no framing headers, see next.config.js) |
| `frontend/hooks/useVisualEditor.ts` | Visual editor: postMessage protocol, section click/highlight/update |
| `frontend/components/sections/VisualEditorWrapper.tsx` | Detects `?_visual=1`, wraps SectionRenderer with editor mode |
| `frontend/components/visual-editor/InlineVisualEditorRuntime.tsx` | Inline field editing runtime inside iframe |

### CMS (ProcessWire)
| File | Purpose |
|------|---------|
| `site/templates/api.php` | Unified API router, all endpoints |
| `site/templates/api-events.php` | Events API (upcoming/past split, cardImage) |
| `site/templates/admin.js` | Admin UI: media library, image editor, preview, recap button, visual editor link, focused PW edit mode |
| `site/templates/visual-editor.php` | Standalone visual editor shell: real-site nav in iframe, sidebar context, save/discard, CRUD, loading blocker |
| `site/templates/visual-editor-focus-fields.json` | Shared VE -> ProcessWire focus field map |
| `site/templates/admin.php` | Loads admin.js in PW admin |
| `site/config.php` | PW config (gitignored, secrets via getenv) |

### Scripts
| File | Purpose |
|------|---------|
| `scripts/deploy.sh` | Full deploy: build, rsync, sharp restore, restart |
| `scripts/healthcheck.sh` | Watchdog: deep body check, cooldown, calls start.sh |

## API Endpoints

### Public (GET)
| Endpoint | Description |
|----------|-------------|
| `/api/health` | Health check |
| `/api/content/hero` | Homepage hero data |
| `/api/content/homepage` | Homepage sections |
| `/api/content/sections/{slug}` | Page sections by page name / slug |
| `/api/content/page?path=` | Single page content |
| `/api/content/pages` | All pages list |
| `/api/content/navigation` | Site navigation tree |
| `/api/content/events` | Events (upcoming + past) |
| `/api/content/aktuelles` | News/aktuelles posts |
| `/api/content/instagram` | Instagram feed |
| `/api/content/settings` | Typography/design tokens |
| `/api/content/page-path?id=` | Page path lookup (preview) |
| `/api/media-files` | Media library file list |

### Admin-only (POST, requires PW session)
| Endpoint | Description |
|----------|-------------|
| `/api/content/event-to-recap` | Convert upcoming event to past recap |
| `/api/content-save` | Save section fields by `sectionPwId` |
| `/api/sections-reorder` | Reorder content_sections repeater items |
| `/api/sections-add` | Add new content_sections repeater item |
| `/api/sections-delete` | Delete content_sections repeater item |
| `/api/media-import` | Import single media file |
| `/api/media-import-batch` | Batch media import |
| `/api/media-usage` | Check media usage |

### Form submissions (POST)
| Endpoint | Description |
|----------|-------------|
| `/api/forms/contact` | Contact form |
| `/api/forms/subscribe` | Newsletter signup |
| `/api/forms/visit` | Open visit day signup |
| `/api/forms/waiting-list` | Waiting list signup |
| `/api/forms/event-signup` | Event signup |

## CMS Admin Enhancements (admin.js)

- **Media Library button**: on image/file fields, opens inline picker to import from MediaLibrary
- **Library-only mode**: hides native upload on page edit (forces MediaLibrary usage)
- **Image editor**: Filerobot-based editor on image thumbnails
- **Preview button**: opens Next.js draft preview for the current page
- **Rückblick button**: on upcoming events, converts to past status for recap editing
- **Visual Editor link**: native-looking masthead item, inserted after `Media` when possible, opens `/visual-editor/` in new tab
- **Focused ProcessWire mode**: VE can open a new PW tab for the selected field/section and hide unrelated inputs

## Visual Editor

Dedicated `/visual-editor/` screen. Parent shell in ProcessWire, direct inline editing inside the iframe.

**Architecture:** PW admin page (`/visual-editor/`, template `visual-editor.php`) embeds Next.js site in iframe with `?_visual=1`. Parent shell owns current page context, save/discard, section CRUD, media actions, and busy/loading state. Real site navigation inside the iframe drives page changes. Iframe runtime owns inline field selection/editing for homepage and CMS repeater pages.

**Key constraints:**
- `visual-editor.php` must call `while (ob_get_level()) ob_end_clean()` at top and `exit` at bottom to bypass PW admin chrome
- PW `has_field` is NOT a valid selector. Use `wire('templates')` iteration + `$t->hasField('content_sections')` to find pages
- Framing protection: `next.config.js` `headers()` sets CSP `frame-ancestors 'self' https://cms.bioco.ch`. Do NOT use middleware for framing headers (don't survive ISR cache hits in Next.js 14)
- `VisualEditorWrapper` requires `<Suspense>` boundary (uses `useSearchParams()`)
- `content-save` is `sectionPwId`-first. Keep `sectionId` only as temporary compatibility input.
- Homepage and CMS pages must both emit stable `data-ve-section-id` / `data-ve-field` markers
- `save-state` carries busy state; while busy, parent + iframe must block edits
- Do not reintroduce the old page picker. The VE sidebar is context only; route changes come from iframe navigation.
- `ready` messages from the iframe must include the current pathname so the parent can sync the active page.
- VE -> ProcessWire focus mapping is shared in `site/templates/visual-editor-focus-fields.json`.
- `admin.js` focused-mode matching must be suffix-aware because repeater field wrappers and input names may be prefixed.
- After deploy, kill ALL `next-server` PIDs. Zombie processes from prior deploys serve stale middleware/headers

## Running PW Migrations

1. Upload migration `.php` to `site/templates/`
2. Create bootstrap in CMS webroot (`/public_html/cms/`)
3. Run via `curl https://cms.bioco.ch/bootstrap-foo.php`
4. Delete bootstrap after use

## Installed PW Modules

- `MediaLibrary` (BitPoet): media library in CKEditor
- `InputfieldCKEditor`: rich text editor

## CKEditor Fields

`section_text`, `body`, `card_text`, `event_summary`, `event_signup_notes`

## Skills

See `SKILLS.md` for available slash commands: `/deploy`, `/deploy-cms`, `/server-status`.
