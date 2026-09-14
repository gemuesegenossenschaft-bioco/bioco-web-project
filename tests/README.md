# WordPress test surface

Map, principles and limits of the WordPress project tests in `tests/test_wordpress*.py`.
Written for the Divi test-consolidation pass (2026-09); update it when the surface changes.

## Runtime map (what is actually live)

- **Production:** Next.js 14 frontend + ProcessWire CMS (out of scope of this test surface; covered
  by `frontend/tests/*` and the frontend deploy gates).
- **WordPress staging:** Softaculous-managed WordPress at `staging.bioco.ch` — Softaculous owns
  Core, database and admin; the repo ships only own code into `wp-content/`
  (`wordpress/scripts/release-wordpress-staging.sh`).
- **Active theme:** `bioco-divi`, a thin **Divi 5** child theme (presentation only). Divi itself is
  licensed and never checked in — every test must run without it.
- **Field/live-value layer:** **SCF (Secure Custom Fields)** for block-editor fields; the seed
  importer emits **native Divi blocks containing dynamic markers** that
  `bioco_dynamic_expand_markers` (`render_block` / `the_content`) fills at render time.
- **Shared core:** `bioco-core` (navigation/footer renderers, tokens, navigation JS, map assets) and
  `bioco-content`/`bioco-forms`/`bioco-import` are theme-agnostic mu-plugins. They stay the single
  owner of content, blocks, fields and forms regardless of theme.
- **Fallback theme:** `bioco` (block theme) remains a real supported adapter. The shared shell
  stylesheet is owned by `bioco-core` (`assets/bioco-shell.css`, enqueued front-end-only at hook
  priority 20 as `bioco-shell`, #180) — neither theme reads shell assets out of the other theme's
  directory, and the fallback theme enqueues no front-end assets itself. Both themes share
  `bioco-tokens.css`, `bioco-navigation.js`, the same shell stylesheet and the same navigation
  contract. Its static contracts are kept, not blanket-deleted.

## How to run

| Command | Needs | Gate |
| --- | --- | --- |
| `python3 -m pytest tests/ -q` | python3, php, node | full suite, all green required |
| `BIOCO_FORMS_BROWSER_TESTS=1 python3 -m pytest tests/test_wordpress_forms_lifecycle.py -q` | Playwright Chromium, local WP runtime at 127.0.0.1:8770 for the runtime proofs | six-form lifecycle in a real browser; opted in, a missing stack fails |
| `BIOCO_BUTTON_BROWSER_TESTS=1 python3 -m pytest tests/test_wordpress_button_readability.py -q` | Playwright Chromium | button readability/contrast in Chromium |
| `bash tests/wordpress-staging-render-gate.sh` | network access to staging | all 22 routes return 200 |
| `bash wordpress/scripts/release-wordpress-staging.sh --commit=<full-40-char-sha>` (dry run default) | repo + CI env | release pipeline preflight |

The suite shells out to real runtimes: **PHP** (`php -r`, one process per scenario) renders real
templates with only WordPress runtime functions stubbed; **Node** (`tests/js/navigation-scroll-harness.mjs`)
executes the real `bioco-navigation.js` against a deterministic DOM stub. Keep both binaries
available when running the suite. Real WordPress *dependency resolution* runs from the vendored
byte-for-byte core classes in `tests/fixtures/wp-dependencies/` (see its `ATTRIBUTION.md`; the
same pattern as `tests/fixtures/wp-block-parser/`).

**PHP prerequisites:** the PHP CLI must have **ext-dom** (`php -m | grep dom`) — the runtime verify
gate (`wp bioco verify --runtime`) judges rendered output through the real built-in `DOMDocument`
(`bioco_import_runtime_html_is_meaningful()` in `bioco-import/includes/verify.php`). A missing
extension fails closed with a clear error, not a false pass.

## Principles

1. **Behaviour over source reading.** Wherever a real renderer, template or script can be executed,
   tests execute it and assert the rendered output. Only WordPress runtime functions
   (`wp_head`, `home_url`, `is_page`, `esc_*`, enqueue APIs, …) are stubbed. The code under test is
   never mocked; the DOM/runtime around it is.
2. **One contract, both themes.** The navigation/footer contract lives in bioco-core
   (`content/navigation.json`, `includes/navigation.php`). Tests assert it behaviourally through the
   Divi shell render and keep only non-duplicating static contracts for the block theme.
3. **A test must be able to fail.** Vacuous or tautological assertions are findings. Consolidations
   prove the replacement tests catch controlled defects in a temporary fixture copy of the real
   sources before the old checks are removed (red/green evidence in the consolidation report).
4. **No production-source edits to make tests pass.** Red evidence comes from temporary copies in
   `/tmp`, never from the real `wordpress/` tree.
5. **Static CSS checks are static.** They pin contract-relevant values in stylesheets; they do not
   measure rendered layout. That is what the parity gate is for.

## Test map

Grouped by behaviour. Status `keep` (unchanged this pass), `trimmed` (redundant assertions removed,
replacements documented), `replaced` (rewritten as behavioural tests), `new` (added this pass).

### Shell, navigation & chrome

| File | Status | Behaviour |
| --- | --- | --- |
| `test_wordpress_divi_shell.py` | replaced | Behavioural integration test of the Divi shell: renders the real `header.php`/`footer.php` plus the real bioco-core navigation/footer renderers under PHP — structure, hook order, blank-template suppression, body-class filtering, canonical links, current routes, mobile-menu contract, footer content, and the core asset enqueues executed through their registered hooks in WordPress priority order. |
| `test_wordpress_navigation_scroll.py` | keep (+ new) | Executes the real `bioco-navigation.js` in Node: utility-row auto-hide scenarios (collapse/reveal/top/focus/reduced-motion/mobile/passive/single/duplicate shell) and the mobile-menu toggle (open, close, Escape, link-click, no-toggle inertness). |
| `test_wordpress_visual_contract.py` | trimmed (#101, #178, #180) | Fallback-theme adapter markup (header part, templates with `bioco-site-header`), approved palette/DM Sans/theme.json truth, nav labels + logo asset, shell-chrome CSS contracts against the core-owned `bioco-shell.css`, adapter-enqueues-nothing contract (#180), approved homepage seed, button AA contrast. |
| `test_wordpress_subpage_design.py` | keep | Shared section-frame system: one outer frame, `data-owned` page headings, section max-widths, primary-nav CTA colour/interactivity, systemic visual primitives, intranet h1 structure. Shell/chrome rules are read from the core-owned `bioco-shell.css` (since #180). |
| `test_wordpress_intranet_alignment.py` | keep | Intranet seam: seed mirrors live page, 22-page corpus + staging gate, utility nav links intranet (rendered with `aria-current`), external-link derivation via `bioco_link_target_attributes`. |

### Import, serialization, composer & Divi styles

| File | Status | Behaviour |
| --- | --- | --- |
| `test_wordpress_divi_home_styles.py` | keep (trimmed #180) | Divi child stylesheet and enqueue contract: parent then child, depending on `divi-parent-style`, `bioco-tokens`, `bioco-shell` — no second shell enqueue, no other-theme path. Plus the fallback-theme-absent fixture copy: hooks in WP priority order, order resolved by the vendored real `WP_Dependencies`. Also DM Sans, scoped home geometry, hero/button contracts. |
| `test_wordpress_divi_home.py` | keep | PHP-rendered Divi block renderers (hero, media-text, rich-text): structure, variants, missing images, unicode transport, unknown-block rejection. |
| `test_wordpress_divi_home_import.py` | keep | Import pipeline with mocked WP APIs: native Divi serialization, attachment resolution, `_divi_builder` meta writes, idempotent re-apply, verify-mode equality. |
| `test_wordpress_divi_block_serialization.py` | keep | The serializer stub and real block-serialization contract (self-closing, nesting, comment order, name validation). |
| `test_wordpress_divi_static_layouts.py` | keep | Composer output for every section component: native blocks, safe modifier fallbacks, empty-module suppression, per-instance content. |
| `test_wordpress_divi_layout_styles.py` | keep | `bioco-divi/style.css` contracts per section component (scoped cream canvas, button/module targeting, breakpoints, no global hacks). |
| `test_wordpress_divi_dynamic_sections.py` | keep | Seed→DOM section-id uniqueness, single events feed, marker→SSR expansion through the real composer/runtime wiring. |
| `test_wordpress_dynamic_sections.py` | keep | Marker format canonicality, round-trip through real render templates, context-stack safety, guarded filters, template targeting. |
| `test_wordpress_verify_duplicate_markers.py` | keep | Duplicate section markers preserve block order. |
| `test_wordpress_home_hero_plan.py` | keep | Homepage plan starts with the approved hero. |
| `test_wordpress_acf_content_seed.py` | keep | Seed/content gates: no unopted ACF JSON, byte-for-byte mirror of seed trees, no legacy ACF serialization path in importer. |
| `test_wordpress_image_seed.py` | keep | Image/gallery seed round-trips (plan, abort-before-write, temp-file cleanup, changed-attachment updates). |

### Content semantics & routes

| File | Status | Behaviour |
| --- | --- | --- |
| `test_wordpress_events_semantics.py` | keep | Event status semantics: wall-clock preservation under UTC/Zurich, date-based upcoming/past filtering, escape-hatch removal, type filter, ordering. |
| `test_wordpress_content_routes.py` | keep | Redirect contract vs Next config, query-string normalisation, event single routes, font sources, navigation contract living outside the theme. |

### Assets & integrations

| File | Status | Behaviour |
| --- | --- | --- |
| `test_wordpress_leaflet_assets.py` | keep | Vendored Leaflet files exist unmodified, registered handles used only by map blocks, marker images resolve inside the vendored dir. |
| `test_wordpress_membership_handoff.py` | keep (updated #181) | Pricing calculator → membership form selection handoff now runs through the real shared lifecycle runtime plus the real adapter in one minimal DOM context (Node VM); server-side tampering rejection unchanged. The full membership lifecycle matrix lives in the opt-in browser suite — no duplicated VM matrix. |
| `test_wordpress_forms_turnstile.py` | keep | Turnstile: official test keys only on unconfigured staging. |
| `test_wordpress_forms_assets.py` | new (#181) | Six form blocks reference pre-registered view-script handles with the shared runtime as (conditional) dependency (map-block pattern); the real `bioco_forms_localize_block()` localizes onto that handle and does NOT pre-enqueue the parser-blocking Turnstile script; adapters stay thin over the shared engine. The vendored real `WP_Dependencies` resolver proves the printed order (runtime before every adapter) AND that a missing runtime FILE still prints all six adapters without the dependency (CodeRabbit blocker: a missing dependency handle would otherwise silently omit the adapters' own fail-closed listeners). |
| `test_wordpress_forms_lifecycle.py` | new (#181, opt-in browser) | All six real PHP-rendered forms in Chromium with intercepted fetch/Turnstile (no real network, no submissions): tracer contact submit, in-flight duplicate guard, native validity gates, HTTP 400/200-false/malformed-JSON/network errors with per-adapter copy, retry with fresh token, expiry/widget-error isolation, two-instance/data-config isolation, terminal accepted response even when success display throws, six-adapter payload matrix (subscribe DOI, visit number, waiting CMS values, event hidden strings, membership redirect incl. explicit `forwarded:false`, selected checkbox arrays in DOM order), membership fieldErrors flattening + calculator matrix, missing-runtime fail-closed across all six forms (network abort AND emitted-without-runtime file case), loader branches (script error, spent pre-existing element, load-without-global, indefinitely hung transport). The hung transport is canonical-URL only: a bounded timeout latches an explicit German manual-reload copy, keeps values/caption, creates no further scripts, and a late-appearing global recovers without new transport — no URL workaround is claimed. Plus real local-runtime proofs: emitted runtime-before-adapter order without a blocking Turnstile tag, real mount, and the real-page hung-Turnstile no-native-GET/no-API-submit regression. |
| `test_wordpress_review_fixes.py` | keep | DOI confirmation has no public REST route, gallery filter validation, DOI nonce rules. |

### Design system & release infrastructure

| File | Status | Behaviour |
| --- | --- | --- |
| `test_wordpress_divi_design_system.py` | keep | Design-system checker contract (#134): token-only values, malformed manifest fail-closed behaviour, documented exceptions. |
| `test_wordpress_visual_parity.py` | keep | 95% visual parity gate semantics: fail-closed results, masking discipline, threshold validation. |
| `test_wordpress_release_pipeline.py` | keep | Release pipeline: dry-run non-mutation, step order/abort, input hygiene, CI parity, hash-pinned deps. |
| `test_wordpress_release_preservation.py` | new (#179) | Exact remote-command boundary, no import, fail-closed aborts, non-writing dry run. Database preservation requires the separate real release check. |
| `test_wordpress_import_cli.py` | new (#179) | Real CLI/importer with controlled WordPress storage: preview writes, no-clobber, forced apply, repeated apply, collection reports and exit statuses. |
| `test_wordpress_verify_runtime.py` | new (#179) | Real WordPress parser with controlled renderer output: edited layouts, malformed nested comments, empty output, renderer failures and page context. Actual Divi rendering is checked separately. |

## Consolidated in this pass (removed → replacement)

Removed assertions and where their coverage now lives:

| Removed (file · assertion) | Replacement |
| --- | --- |
| `test_wordpress_divi_shell.py` · all five source-string checks (renderer call counts in `header.php`/`footer.php`, `bioco-site-header`/`bioco-page-shell` markers, absence of `main-header`/`show_page_menu`, lifecycle-hook name lists, `main` wrapper strings, `body_class`/`et_fixed_nav`/`et_show_nav`/`array_values(array_diff`/`PHP_INT_MAX` in `functions.php`) | `tests/test_wordpress_divi_shell.py` behavioural render tests (structure, hook order, blank template, body-class filter incl. priority, canonical links, current-route, footer contract) |
| `test_wordpress_divi_shell.py` · `"$theme_root_uri . '/bioco/assets/app.css'"` / `bioco-shell` / `"bioco-navigation" in bioco-core.php` source checks | enqueue behaviour already behavioural in `test_wordpress_divi_home_styles.py::test_child_theme_enqueues_parent_then_child_stylesheet_depends_on_shared_shell`; mu-plugin side now behavioural in `test_wordpress_divi_shell.py::test_bioco_core_enqueues_tokens_blocks_navigation_and_shared_shell_in_order`, which executes the registered enqueue-hook callbacks in WordPress priority order instead of calling the implementation (a missing or wrongly named hook registration fails the test) |
| `test_wordpress_divi_shell.py` · footer contract titles read from `navigation.json` | approved German titles (`Navigation`, `Kontakt`, `Social Media`, `Partner & Zertifizierungen`) are asserted as pinned `<h3>` values in `test_site_footer_renders_the_approved_titles_and_internal_links` — the render must match the approved content, not just whatever navigation.json currently says |
| `test_wordpress_visual_contract.py` · `navigation.js` helper-name strings (`aria-expanded`, `aria-label`, `?.focus()`, `is-open`) | executed behaviourally by the new menu scenarios in `tests/js/navigation-scroll-harness.mjs` + `test_wordpress_navigation_scroll.py` (red evidence H1/H2/H3: removing the aria-expanded flip, the `?.focus()` call, or the Escape handler makes the new tests fail) |
| `test_wordpress_visual_contract.py` · utility auto-hide JS strings (`is-utility-hidden`, `addEventListener('scroll'`, `{ passive: true }`, `requestAnimationFrame`, `window.scrollY`, `anchorY`, `utility.contains(...)`, `initUtilityAutoHide`, ordering) | already executed by the existing harness scenarios (collapse/reveal/passive/single-listener/focus/top-zone) |
| `test_wordpress_visual_contract.py` · `bioco-mobile-utility` in `navigation.php` + markup-ordering `index()` checks | rendered-document assertions in `test_wordpress_divi_shell.py` (`test_mobile_utility_entries_are_duplicated_into_the_primary_menu`, structure order in `test_primary_navigation_renders_the_full_canonical_contract`) |
| `test_wordpress_divi_home_import.py` · first, shadowed `_page_import_payload` definition (dead duplicate of the sentinel version at the bottom) | removed; the sentinel version (`_NO_EXISTING_PAGE`) and all its tests are unchanged |

Shell tests proven against controlled defects (temporary fixture copies; production untouched):
missing `wp_footer`, duplicated navigation markup, removed `body_class` filter, removed
`aria-expanded`, removed `rel="noopener noreferrer"`, removed blank-template guard, disabled
current-route detection, missing `wp_enqueue_scripts` hook registration, hook renamed to `init`,
`page-container` never closed, `</main>` removed — every defect is caught by at least one of the
new tests; pristine and restored trees pass.

## #180 shared shell ownership (this pass)

The navigation/footer shell stylesheet moved from the bioco fallback theme into `bioco-core`
(`assets/bioco-shell.css`); the fallback theme's `assets/app.css` was removed. What changed in the
test surface:

| Changed | Replacement |
| --- | --- |
| `test_wordpress_divi_home_styles.py` · Divi enqueue order `parent → shell(../bioco/assets/app.css) → child` (cross-theme path) | the Divi adapter now enqueues only parent + child and *depends* on `bioco-shell` (`test_child_theme_enqueues_parent_then_child_stylesheet_depends_on_shared_shell`); the shell arrives from bioco-core, which is theme-independent |
| enqueue-call-order expectations | hook execution in real WordPress priority order (shell at priority 20, after the theme adapters' default-10 callbacks) + stylesheet order resolved by the REAL vendored `WP_Dependencies` (`tests/fixtures/wp-dependencies/`, attribution in place) → `tokens → parent → shell → child`. Red/green: under the old priority-10 mechanism the same test fails with `… bioco-shell, divi-parent-style …` (shell before parent) — the ordering regression root's spec review found is caught, not blessed |
| `test_wordpress_visual_contract.py` · 4 shell-chrome CSS reads from `themes/bioco/assets/app.css` | same assertions (none weakened) against `bioco-core/assets/bioco-shell.css`; plus the new adapter test: the real fallback-theme `functions.php` executed through its registered hooks enqueues nothing (`test_block_theme_adapter_enqueues_no_front_end_assets_itself`) |
| `tests/test_wordpress_subpage_design.py` · 3 shell reads from the theme's `app.css` | same assertions against `bioco-core/assets/bioco-shell.css` |
| `tests/fixtures/button-readability/*.html` shell stylesheet link | points at `bioco-core/assets/bioco-shell.css` (same link position in the cascade) |
| themes/bioco `assets/app.css` | deleted only after every consumer (Divi functions.php, fallback functions.php, tests, fixtures) used the core copy; the full suite runs green on the tree without the file |

## Known limits

- **Native block serialization is not the Divi editor.** Import/serialization tests run against the
  repo's serializer and PHP render paths. They do not prove a real Divi 5 editor load/save roundtrip
  of generated content — that stays a staging-checklist item.
- **The runtime gate judges pages, not components.** `wp bioco verify --runtime` accepts editorial
  change by design: it proves every required page exists, is non-empty, carries valid Divi markup and
  renders visible content in total (decorative divider/spacer sections may stay individually empty).
  Without declaring components required it cannot detect every missing individual component on an
  otherwise populated page; exact seed fidelity remains the separate acceptance gate of the explicit
  import (`wp bioco verify`). The unit suite does not model Divi rendering; actual Divi rendering is
  checked against a real local WordPress runtime.
- **Release-preservation tests assert the command boundary, not database state.** The shell release
  tests control the external commands only; actual editorial preservation is verified in a real
  staging release with data hashes taken before and after.
- **Render smoke is not mail delivery.** Form/renderer tests verify markup and handler wiring; no
  test sends actual mail (and none may — the suite must stay network-free).
- **Static CSS values are not computed contrast.** The AA checks compute contrast ratios from
  declared hex values; real rendering (gamma, subpixel, actual computed cascade) is not measured —
  the parity gate and manual review cover that.
- **Defect fixtures are ephemeral.** Red evidence lives in the consolidation report, not in the
  suite; nothing under `wordpress/` is modified to manufacture failures.

Not claimed: the remaining per-component CSS checks in `test_wordpress_divi_home_styles.py` /
`test_wordpress_divi_layout_styles.py` are functional tests — they are static contract pins. And the
suite is not refactor-proof everywhere; source-coupled checks are the documented follow-ups below.

## Follow-up candidates (do not delete behaviour blindly)

- `test_wordpress_divi_layout_styles.py` / `test_wordpress_divi_home_styles.py`: several
  per-selector value pins (exact px geometry, exact overlay gradients) are reference baselines.
  They can drift silently against design-token renames; consider rendering the token sheets or
  folding them into the parity gate before changing them.
- `test_wordpress_divi_design_system.py` contract could reuse the shared `navigation.json`
  loader instead of its own file reads if both grow.
- `test_wordpress_membership_handoff.py` server path relies on stubbed WP request plumbing;
  a real staging render check for the membership form would close the loop (needs the staging
  gate, not the unit suite).
- Button-contrast replacement in `test_wordpress_visual_contract.py` is intentionally untouched
  here — it is being revised in parallel with the button readability fix (#178).
