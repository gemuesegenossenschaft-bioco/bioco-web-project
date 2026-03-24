# AGENTS.md

## Deploy Agent

Deploys frontend and/or CMS to Novatrend cPanel.

**Rules:**
1. Always build locally. Server builds fail (CloudLinux thread limits).
2. Rsync three dirs in order: `.next/standalone/` (exclude start.sh), `.next/static/`, `public/`.
3. Restore sharp: copy `sharp-linux-x64` AND `sharp-libvips-linux-x64` from `/tmp/sharp-pkg/`. Remove darwin bindings.
4. Rsync CMS files: `admin.js`, `api.php`, `api-events.php`, `visual-editor.php` to `/home/bioco/public_html/cms/site/templates/` **and** `site/ready.php` to `/home/bioco/public_html/cms/site/ready.php`.
5. Restart primary: `for p in $(pgrep -x next-server); do kill $p; done; sleep 3; start.sh`. Never use `pgrep -f` (kills SSH session).
6. Restart fallback if primary misses old workers: `for p in $(ps -eo pid,comm | awk '$2=="next-server"{print $1}'); do kill $p; done`.
7. Verify: `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:49154/` from server.
8. External test: `curl --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/`
9. Verify revalidate auth from server: `POST http://127.0.0.1:49154/api/revalidate` must return `200` with secret from `site/config.php`.

**Common issues:**
- 503 after deploy: sharp bindings missing or process not started. Check `tail /home/bioco/logs/nextjs.log`.
- EADDRINUSE: old process still running. Kill with `pgrep -x next-server`, wait, retry.
- Zombie processes: after deploy, verify only ONE `next-server` is running. Old instances serve stale code (wrong headers, old middleware). Kill by PID if `pgrep -x` misses them.
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
- Keep secrets synced: `start.sh: REVALIDATE_SECRET` == `site/config.php: nextRevalidateSecret`.
- Required values: `nextRevalidateUrl=http://127.0.0.1:49154/api/revalidate`, debounce `10`, max-wait `45`, queue file `/tmp/bioco-next-revalidate-state.json`.

## CMS API Agent

Manages ProcessWire API endpoints in `site/templates/api.php`.

**Rules:**
- All endpoints routed through `api.php` switch statement.
- Content sub-endpoints in `handleContentRequest()`: hero, homepage, sections, page, pages, navigation, events, aktuelles, instagram, page-path, event-to-recap, sections-reorder, sections-add, sections-delete.
- Form sub-endpoints: contact, subscribe, visit, waiting-list, event-signup.
- Admin endpoints require `requireAdminSession()` (checks PW login).
- Events also served by standalone `api-events.php` template.
- Cache invalidation hook lives in `site/ready.php` (not `api.php`): queued + debounced + trailing flush via `LazyCron::everyMinute`.

## CMS Admin Agent

Manages ProcessWire admin UI enhancements in `site/templates/admin.js`.

**Features:**
- Media Library inline picker (browse + batch import)
- Library-only enforcement (hides native upload on page edits)
- Filerobot image editor on thumbnails
- Preview button (draft mode via Next.js)
- Rückblick button (converts upcoming event to past recap)
- Visual Editor navbar link (green button, opens `/visual-editor/`)

**Patterns:**
- `getEditedTemplateName()` to detect current template
- `getPageEditId()` to get current page ID
- Button injection via `$header.find('h1').after($btn)`
- API calls via `$.ajax()` to `/api/content/*`

## Visual Editor Agent

Manages the iframe + postMessage WYSIWYG section editor.

**Architecture:**
- PW page `/visual-editor/` uses template `visual-editor.php` (standalone HTML, bypasses admin chrome)
- Embeds Next.js site in iframe with `?_visual=1` query param
- Bidirectional postMessage with `bioco:visual-editor:` prefix
- Sidebar: section list, drag reorder, add/delete, field editing

**Files:**
- `site/templates/visual-editor.php`: PW admin page (standalone HTML output)
- `frontend/hooks/useVisualEditor.ts`: postMessage protocol, section click/highlight/update
- `frontend/components/sections/VisualEditorWrapper.tsx`: detects `?_visual=1`, adds `data-section-id` attrs
- `frontend/components/sections/SectionRenderer.tsx`: `visualEditor` prop adds data attributes

**Rules:**
- `visual-editor.php` MUST call `while (ob_get_level()) ob_end_clean()` at top and `exit` at bottom (PW admin chrome bypass)
- PW `has_field` is NOT a valid selector. Iterate `wire('templates')` + check `$t->hasField()` instead
- Framing: CSP `frame-ancestors` in `next.config.js` `headers()`. Never use middleware for framing headers (ISR cache overwrites them in Next.js 14)
- `VisualEditorWrapper` uses `useSearchParams()`, requires `<Suspense>` boundary in parent page
- Section CRUD endpoints: `sections-reorder`, `sections-add`, `sections-delete` in `api.php`
- Repeater sort: always use `->sort('sort')` when iterating `content_sections`

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
- Catch-all: `(cms)/[...slug]/page.tsx` handles CMS-driven pages (uses `VisualEditorWrapper` with Suspense)
- `app/icon.png` for favicon (catch-all intercepts `/favicon.ico`)

**Patterns:**
- Events split by `event_status`: upcoming vs past
- `mapEventFromApi()` normalizes API response to `AktuellesItem`
- `cardImage` field used as fallback thumbnail when no media attached
- Modal system: `ItemDetailModal` for event/aktuelles detail views
- Caching contract:
  - `frontend/middleware.ts` sets security headers only (no `Cache-Control`, no framing headers).
  - `frontend/next.config.js` `headers()` sets CSP `frame-ancestors` for iframe protection. Never use middleware for this (ISR cache overwrites).
  - CMS server fetches use `next.revalidate` + tag `cms`.
  - Client-side events fetch uses `cache: 'no-store'`.
  - `/api/revalidate` supports `path|paths|tag|tags|layout`, secret required.
- If touching cache/revalidate behavior, update tests: `frontend/tests/revalidate-route.test.ts`, `frontend/tests/middleware.test.ts`.
- Visual editor tests: `frontend/tests/visual-editor.test.tsx` (postMessage protocol, section attrs, highlight, security).
