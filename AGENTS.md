# AGENTS.md

## Deploy Agent

Deploys frontend and/or CMS to Novatrend cPanel.

**Rules:**
1. Always build locally. Server builds fail (CloudLinux thread limits).
2. Rsync three dirs in order: `.next/standalone/` (exclude start.sh), `.next/static/`, `public/`.
3. Restore sharp: copy `sharp-linux-x64` AND `sharp-libvips-linux-x64` from `/tmp/sharp-pkg/`. Remove darwin bindings.
4. Rsync CMS files: `admin.js`, `api.php`, `api-events.php`, `visual-editor.php`, `visual-editor-focus-fields.json` to `/home/bioco/public_html/cms/site/templates/` **and** `site/ready.php` to `/home/bioco/public_html/cms/site/ready.php`.
5. Restart primary: `for p in $(pgrep -a next-server | awk '{print $1}'); do kill "$p"; done; sleep 3; start.sh`. Never use `pgrep -f` (kills SSH session).
6. Restart fallback if primary misses old workers: `for p in $(ps -eo pid,comm | awk '$2=="next-server"{print $1}'); do kill $p; done`.
7. Verify: `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:49154/` from server.
8. External test: `curl --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/`
9. Verify revalidate auth from server: `POST http://127.0.0.1:49154/api/revalidate` must return `200` with secret from `site/config.php`.
10. HTML smoke gate: local + external response body must NOT contain `Fehler` / `Etwas ist schiefgelaufen.` (200 alone is not enough).
11. Log gate: after restart, check `/home/bioco/logs/nextjs.log` and ensure no fresh `Failed to find Server Action` entries.
12. If local repo has unrelated dirty files, deploy from a clean temp clone. Do not rsync accidental local-only changes to production.

**Common issues:**
- 503 after deploy: sharp bindings missing or process not started. Check `tail /home/bioco/logs/nextjs.log`.
- PW module changes not reflected after rsync: PHP OPcache is caching the old bytecode. CLI `opcache_reset()` does NOT affect web PHP-FPM. Place a reset script accessible via `cms.bioco.ch` OUTSIDE `/cms/` (PW `.htaccess` intercepts everything inside). The vhost root is `/home/bioco/public_html/cms/` — place file there and access as `cms.bioco.ch/<file>.php`. Call `opcache_invalidate('/path/to/file.php', true)` or `opcache_reset()` then delete the script. If vhost root is uncertain, check Apache config or test with a plain file.
- EADDRINUSE: old process still running. Kill with `pgrep -x next-server`, wait, retry.
- Zombie processes: after deploy, verify only ONE `next-server` is running. Old instances serve stale code (wrong headers, old middleware). Kill by PID if `pgrep -x` misses them.
- `Fehler` page with `HTTP 200`: stale client/server deploy mismatch (`Failed to find Server Action`, `reading 'workers'`, `reading 'digest'`). Fix by killing stale workers + restart + hard refresh (`?__fresh=<ts>`). Do not mark deploy healthy until HTML smoke + log gate pass.
- Cron respawns every 5min: during a restart window it can spawn a second `next-server`. Always run `ps -eo pid,ppid,args | grep "next-server" | grep -v grep` after deploy and kill any non-primary PID.
- Cron uses a minimal `PATH`. In `/home/bioco/bioco-frontend/start.sh`, use absolute paths or export `PATH` before `flock`/`curl`/`pgrep`. If not, the guard checks silently fail under cron and it spawns duplicate workers.
- CloudLinux Node Selector / Passenger can also spawn a second worker. Check `~/.cl.selector/node-selector.json` and inspect the extra PID environment for `IN_PASSENGER=1`. This is distinct from the standalone `start.sh` process.
- Public route `404` while local `127.0.0.1:49154` is `200`: check for stray files/symlinks under `/home/bioco/public_html/` matching the route slug. Example: broken `/home/bioco/public_html/wir` symlink bypassed Next via `.htaccess` file checks.

## Server Setup Agent

Configures Node.js on Novatrend/CloudLinux cPanel.

**Rules:**
- Apache proxies via `.htaccess` RewriteRule to port 49154. Passenger config exists but is unreliable.
- `.htaccess` currently short-circuits `/intranet`, `/statuten`, `/wir` when a matching file/dir exists in `public_html`. If external route differs from local app response, inspect `ls -la /home/bioco/public_html/<slug>` first.
- Env vars go in `start.sh` (not cPanel Passenger UI, not `.htaccess` SetEnv).
- Node venv: `source /home/bioco/nodevenv/bioco-frontend/18/bin/activate` before any node/npm commands.
- `127.0.0.1` does NOT resolve to bioco.ch vhost. Test externally with `--resolve`.
- `SetEnv` in .htaccess causes 500 (mod_env not available with lsapi handler).
- Keep ISR hook config in `site/config.php`: `nextRevalidateSecret`, `nextRevalidateUrl`, debounce/max-wait/queue-file keys.
- Keep secrets synced: `start.sh: REVALIDATE_SECRET` == `site/config.php: nextRevalidateSecret`.
- Required values: `nextRevalidateUrl=http://127.0.0.1:49154/api/revalidate`, debounce `10`, max-wait `45`, queue file `/tmp/bioco-next-revalidate-state.json`.
- **Cloudflare Turnstile captcha**: `NEXT_PUBLIC_TURNSTILE_SITE_KEY` (baked at build, also in `start.sh`) and `TURNSTILE_SECRET_KEY` (server-only, in `start.sh`) MUST both be exported BEFORE the `nohup node server.js` line. If placed after, the running process inherits empty strings. Verify with: `tail /home/bioco/logs/nextjs.log | grep "TURNSTILE_SECRET_KEY is not configured"`. Keys are in Cloudflare dashboard > Turnstile > site. The site key also lives in `frontend/.env.production` for build-time embedding.

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
- Visual Editor navbar link (native masthead item, inserted after `Media` when possible, opens `/visual-editor/` in new tab)
- Focused ProcessWire mode for Visual Editor deep-links (`veFocus=1`): hides unrelated fields, shows back-link to VE

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
- Parent shell owns current page context, save/discard, section CRUD, busy state
- Page switching happens through the real site navigation inside the iframe, not a PW-side page picker
- Iframe owns inline field selection/editing for homepage + CMS repeater pages
- Full-screen busy overlay blocks edits during load/save/refetch/import

**Files:**
- `site/templates/visual-editor.php`: PW admin page (standalone HTML output)
- `site/templates/visual-editor-focus-fields.json`: shared VE -> ProcessWire focus field map
- `frontend/components/visual-editor/InlineVisualEditorRuntime.tsx`: iframe inline editor runtime
- `frontend/components/visual-editor/fieldAttrs.ts`: field marker helpers
- `frontend/hooks/useVisualEditor.ts`: postMessage protocol, section click/highlight/update
- `frontend/lib/visualEditorProcessWire.ts`: shared focus-field mapping logic for tests/runtime
- `frontend/components/sections/VisualEditorWrapper.tsx`: detects `?_visual=1`, adds `data-section-id` attrs
- `frontend/components/sections/SectionRenderer.tsx`: CMS page field instrumentation
- `frontend/components/HomeClient.tsx`: homepage field instrumentation

**Rules:**
- `visual-editor.php` MUST call `while (ob_get_level()) ob_end_clean()` at top and `exit` at bottom (PW admin chrome bypass)
- PW `has_field` is NOT a valid selector. Iterate `wire('templates')` + check `$t->hasField()` instead
- Framing: CSP `frame-ancestors` in `next.config.js` `headers()`. Never use middleware for framing headers (ISR cache overwrites them in Next.js 14)
- `VisualEditorWrapper` uses `useSearchParams()`, requires `<Suspense>` boundary in parent page
- `content-save` persists by `sectionPwId`; keep legacy `sectionId` only for compatibility
- Section CRUD endpoints: `sections-reorder`, `sections-add`, `sections-delete` in `api.php`
- Repeater sort: always use `->sort('sort')` when iterating `content_sections`
- Do not reintroduce the old VE page dropdown. Sidebar shows current page title/path and PW type labels, but page changes come from iframe navigation.
- `ready` must include the current pathname so the parent shell can adopt route changes after in-iframe navigation.
- Focused ProcessWire deep-links rely on `veFields` from `visual-editor-focus-fields.json`.
- `admin.js` field matching for focused mode must stay suffix-aware because repeater field DOM ids/names can be prefixed.
- Keep one protocol. Parent -> iframe: `section-highlight`, `section-scroll`, `section-update`, `sections-replace`, `save-state`. Iframe -> parent: `ready`, `section-click`, `field-select`, `field-change`, `field-commit`, `media-request`, `open-processwire`

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
- Visual editor tests: `frontend/tests/visual-editor.test.tsx` (postMessage, inline edit selection, busy blocking, field attrs, highlight, security).
