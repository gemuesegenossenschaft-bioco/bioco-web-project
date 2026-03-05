# AGENTS.md

## Deploy Agent

Deploys frontend and/or CMS to Novatrend cPanel.

**Rules:**
1. Always build locally. Server builds fail (CloudLinux thread limits).
2. Rsync three dirs in order: `.next/standalone/` (exclude start.sh), `.next/static/`, `public/`.
3. Restore sharp: copy `sharp-linux-x64` AND `sharp-libvips-linux-x64` from `/tmp/sharp-pkg/`. Remove darwin bindings.
4. Rsync CMS files: `admin.js`, `api.php`, `api-events.php` to `/home/bioco/public_html/cms/site/templates/` **and** `site/ready.php` to `/home/bioco/public_html/cms/site/ready.php`.
5. Restart: `for p in $(pgrep -x next-server); do kill $p; done; sleep 3; start.sh`. Never use `pgrep -f` (kills SSH session).
6. Verify: `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:49154/` from server.
7. External test: `curl --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/`

**Common issues:**
- 503 after deploy: sharp bindings missing or process not started. Check `tail /home/bioco/logs/nextjs.log`.
- EADDRINUSE: old process still running. Kill with `pgrep -x next-server`, wait, retry.
- Cron respawns every 5min: if process keeps dying, fix root cause before restarting.

## Server Setup Agent

Configures Node.js on Novatrend/CloudLinux cPanel.

**Rules:**
- Apache proxies via `.htaccess` RewriteRule to port 49154. Passenger config exists but is unreliable.
- Env vars go in `start.sh` (not cPanel Passenger UI, not `.htaccess` SetEnv).
- Node venv: `source /home/bioco/nodevenv/bioco-frontend/18/bin/activate` before any node/npm commands.
- `127.0.0.1` does NOT resolve to bioco.ch vhost. Test externally with `--resolve`.
- `SetEnv` in .htaccess causes 500 (mod_env not available with lsapi handler).
- Keep ISR hook config in `site/config.php`: `nextRevalidateSecret`, `nextRevalidateUrl`, debounce/max-wait/queue-file keys.

## CMS API Agent

Manages ProcessWire API endpoints in `site/templates/api.php`.

**Rules:**
- All endpoints routed through `api.php` switch statement.
- Content sub-endpoints in `handleContentRequest()`: hero, homepage, sections, page, pages, navigation, events, aktuelles, instagram, page-path, event-to-recap.
- Form sub-endpoints: contact, subscribe, visit, waiting-list, event-signup.
- Admin endpoints require `requireAdminSession()` (checks PW login).
- Events also served by standalone `api-events.php` template.

## CMS Admin Agent

Manages ProcessWire admin UI enhancements in `site/templates/admin.js`.

**Features:**
- Media Library inline picker (browse + batch import)
- Library-only enforcement (hides native upload on page edits)
- Filerobot image editor on thumbnails
- Preview button (draft mode via Next.js)
- Rückblick button (converts upcoming event to past recap)

**Patterns:**
- `getEditedTemplateName()` to detect current template
- `getPageEditId()` to get current page ID
- Button injection via `$header.find('h1').after($btn)`
- API calls via `$.ajax()` to `/api/content/*`

## CMS Migration Agent

Runs ProcessWire database migrations.

**Rules:**
- Upload `.php` script to `site/templates/` via rsync
- Create bootstrap in `/public_html/cms/` that includes `index.php` + migration
- Run: `curl https://cms.bioco.ch/bootstrap-foo.php`
- Delete bootstrap immediately (security risk)

## Frontend Agent

Next.js 14 app router frontend.

**Structure:**
- Pages: `frontend/app/*/page.tsx` (SSG)
- CMS content: fetched via `lib/cmsClient.ts` and `lib/processwire.ts`
- Events: `AktuellesData.tsx` (types + API fetch), `AktuellesClient.tsx` (UI), `useEventsFeed.ts` (hook)
- Catch-all: `(cms)/[...slug]/page.tsx` handles CMS-driven pages
- `app/icon.png` for favicon (catch-all intercepts `/favicon.ico`)

**Patterns:**
- Events split by `event_status`: upcoming vs past
- `mapEventFromApi()` normalizes API response to `AktuellesItem`
- `cardImage` field used as fallback thumbnail when no media attached
- Modal system: `ItemDetailModal` for event/aktuelles detail views
