# AGENTS.md

## Repository, hosting & secrets

- GitHub org `gemuesegenossenschaft-bioco`: repos `bioco-web-project` + `bioco-docs`. `origin` and `$config->githubRepo` point at the org.
- **Repo is PUBLIC.** Never commit secrets/tokens/`.env`. `site/config.php` is gitignored (secrets via `getenv()`); never track it.
- `docs.bioco.ch` = MkDocs on **GitHub Pages** from `bioco-docs` (`gh-pages`, `docs/CNAME`). DNS at Tophost. Deploy: `gh workflow run deploy.yml -R gemuesegenossenschaft-bioco/bioco-docs` (org workflow write is enabled). Not hosted on Novatrend.
- The CMS->git `internal-docs-mirror` was removed; do not reintroduce it. docs.bioco.ch is the single source of truth.
- Many `app/*/page.tsx` are still hardcoded JSX, not CMS-driven. `/abos` is the converted reference. CMS/VE edits to a hardcoded page do nothing until it renders via `SectionRenderer`.

## HARD CONSTRAINT: no hardcoded content, no fallback content

Non-negotiable, every agent, every branch. **Content never lives in code.** Full rationale in `CLAUDE.md`.

1. No German prose, heading, label, button text, price or editorial copy in `.tsx`/`.php`/`.js`. If a human would reword it without a developer, it is content and belongs in the CMS.
2. No fallback content: no `get_field('x') ?: 'Text'`, no `title || 'Nächste Events'`, no placeholder copy in a render template. A missing value renders nothing, never invented text — a fallback looks correct, so nobody notices the field was never filled.
3. After the WordPress migration: WordPress elements exclusively. ACF field / block attribute / CPT entry / menu item, editable in wp-admin.
4. Only exception, presentation defaults (`gap`, `rounded`, `columns_desktop`): allowed, but as the ACF field's `default_value`, not as a `?:` in PHP.
5. Enforce mechanically: `frontend/tests/no-raw-hex.test.ts`, `wordpress/scripts/check-hardcoded-content.php`, `wordpress/scripts/check-seed-plan.php`. A violation must fail a gate, not depend on review.
6. When unsure whether something is content or code, treat it as content and make it a field.

## Deploy Agent

Deploys frontend and/or CMS to Novatrend cPanel.

**Rules:**
1. Always build locally. Server builds fail (CloudLinux thread limits).
2. Prefer `scripts/deploy.sh` for deploys. No args builds current worktree; passing a branch checks out/pulls that branch.
3. Rsync three dirs in order: `.next/standalone/`, `.next/static/`, `public/`. Protect/exclude `start.sh` and `healthcheck.sh` from standalone `--delete`.
4. Always upload `/home/bioco/bioco-frontend/healthcheck.sh` from `scripts/healthcheck.sh`, `chmod +x`, and `bash -n` it.
5. Verify `start.sh` stale-PID guard: it must kill `$oldpid` instead of exiting when local HTTP is down.
6. Restore sharp: copy `sharp-linux-x64` AND `sharp-libvips-linux-x64` from `/tmp/sharp-pkg/`. Remove darwin bindings.
7. Rsync CMS files: `admin.js`, `api.php`, `api-events.php`, `visual-editor.php`, `visual-editor-focus-fields.json` to `/home/bioco/public_html/cms/site/templates/` **and** `site/ready.php` to `/home/bioco/public_html/cms/site/ready.php`.
8. Restart primary: `for p in $(pgrep -a next-server | awk '{print $1}'); do kill "$p"; done; sleep 3; start.sh`. Never use `pgrep -f` (kills SSH session).
9. Restart fallback if primary misses old workers: `for p in $(ps -eo pid,comm | awk '$2=="next-server"{print $1}'); do kill $p; done`.
10. Verify local: `curl -s -o /tmp/root.html -w "%{http_code}" http://127.0.0.1:49154/` from server must be `200`.
11. External test: `curl --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/`
12. Verify watchdog: `test -x /home/bioco/bioco-frontend/healthcheck.sh && /home/bioco/bioco-frontend/healthcheck.sh`.
13. Verify worker count with `ps`, not only `pgrep`: exactly one `next-server` row from `ps -eo pid,ppid,comm,args | awk '$3 ~ /^next-server/ {print}'`.
14. Verify revalidate auth from server: `POST http://127.0.0.1:49154/api/revalidate` must return `200` with secret from `site/config.php`.
15. HTML smoke gate: local + external response body must NOT contain `Fehler` / `Etwas ist schiefgelaufen.` (200 alone is not enough).
16. Log gate: after restart, check `/home/bioco/logs/nextjs.log` and ensure no fresh `Failed to find Server Action` entries.
17. If local repo has unrelated dirty files, deploy from a clean temp clone. Do not rsync accidental local-only changes to production.

**Common issues:**
- 503 after deploy: process not started, missing `healthcheck.sh`, or sharp bindings missing. Check `curl http://127.0.0.1:49154/`, `ls -l /home/bioco/bioco-frontend/healthcheck.sh`, and `tail /home/bioco/logs/nextjs.log`.
- Missing watchdog: if health log says `/home/bioco/bioco-frontend/healthcheck.sh: No such file or directory`, rsync `scripts/healthcheck.sh` there, chmod it, then run it once.
- PW module changes not reflected after rsync: PHP OPcache is caching the old bytecode. CLI `opcache_reset()` does NOT affect web PHP-FPM. Place a reset script accessible via `cms.bioco.ch` OUTSIDE `/cms/` (PW `.htaccess` intercepts everything inside). The vhost root is `/home/bioco/public_html/cms/` — place file there and access as `cms.bioco.ch/<file>.php`. Call `opcache_invalidate('/path/to/file.php', true)` or `opcache_reset()` then delete the script. If vhost root is uncertain, check Apache config or test with a plain file.
- EADDRINUSE: old process still running. Kill with `pgrep -x next-server`, wait, retry.
- `pgrep -x next-server` can miss the worker on this host. Always use the `ps ... awk '$3 ~ /^next-server/'` fallback before deciding there is no process.
- Zombie processes: after deploy, verify only ONE `next-server` is running. Old instances serve stale code (wrong headers, old middleware). Kill by PID if `pgrep` misses them.
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
- `collection-create` (admin): creates an `event` page under `/aktuelles/` (id 1740) by date, defaults `event_status=upcoming` + first `event_type` option, fires `biocoRevalidatePathsNow`, returns `pwId` + `editUrl`. Events/blog are page lists (template `event`; `news_item` unused), not section repeaters.
- Form sub-endpoints: contact, subscribe, visit, waiting-list, event-signup.
- Admin endpoints require `requireAdminSession()` (checks PW login).
- Events also served by standalone `api-events.php` template.
- Cache invalidation hook lives in `site/ready.php` (not `api.php`): queued + debounced + trailing flush via `LazyCron::everyMinute`.
- `content-publish` saves the VE draft then revalidates synchronously via `biocoRevalidatePathsNow($paths)` (in `ready.php`, returns `{ok,status,error}`) and includes `revalidated` + `revalidateStatus` in its JSON. Keep this contract: the VE pill depends on it.

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
- Collection mode: when the iframe navigates to a `COLLECTIONS` path (`/aktuelles`), the sidebar shows a collection panel (entry list + date-picker "Neuer Event" → `collection-create` → opens PW edit) instead of the section editor. Entries deep-link to PW via `PAGE_EDIT_URL?id=`.
- Field-ownership panel: the field editor splits each section's fields into Visual Editor (inline) vs ProcessWire (deep-link "→ In PW öffnen"). Keep the two groups consistent with what the iframe runtime can actually edit.
- Structured component config is edited inline via `section_config` (field types `select`/`range`/`text`/`number`); `pricing_table` is the reference.
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

**Structured components (section_component + section_config):**
- Registry `site/templates/component-registry.json` -> renderers `frontend/lib/componentRenderers.tsx` + `frontend/components/sections/RegisteredSectionComponents.tsx`.
- New component = registry entry (key, `frontendTarget`, `cmsFields`, `defaultConfig`, `configSchema`) + renderer + add to `componentRenderers` and (if it owns its layout) `layoutOwnedKeys`.
- `configSchema` field types: `select`, `range`, `text`, `number` (the VE inline config grid renders these). `pricing_table` (Abo table, 3 fixed tiers) is the reference; test in `frontend/tests/section-renderer.test.tsx`.
- `/abos` (`app/abos/page.tsx`) renders fully from CMS via `<SectionRenderer>`; use it as the template when converting other hardcoded pages.

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
