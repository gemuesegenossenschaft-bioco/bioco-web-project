# WordPress test surface

Map, principles and limits of the WordPress project tests in `tests/test_wordpress*.py`.
Written for the Divi test-consolidation pass (2026-09); update it when the surface changes.

## Runtime map (what is actually live)

- **Production:** WordPress with Divi since 9 October 2026. Next.js and ProcessWire
  are retained as rollback/recovery sources. This suite verifies repository behavior;
  passing it does not establish the deployed production code or database state.
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

Event template slice (#202): keep the generated fallback checks until migration;
add `test_wordpress_event_templates.py` for record context, image/date/body
behavior and real WordPress block round trips. Extend draft isolation checks to
matching unassigned event bodies. These tests run without licensed Divi. Native
edit/save/reopen and public desktop/mobile rendering remain required operator
evidence on staging and production. This slice does not complete event feeds.

The #217 back-button checks execute both native layout generators, round-trip
label/link/responsive style edits through the WordPress parser, and check the
child theme's focus/wrapping rules in Chromium. They require no server writes.
The licensed preset rendering and native save/reopen still need hosted proof.

The #214/#220 presentation previews preserve saved content and native controls
while replacing measured legacy classes. The required PR gate covers homepage,
cards, CTA, media/rich text, shell wrappers and button/intro conversions. These
checks use saved block fixtures and conditional SQL; they do not establish
hosted appearance, shared preset editing or production adoption.

Installed Divi checks are explicit, read-only operator runs. Configure
`BIOCO_DIVI_NATIVE_SSH` and `BIOCO_DIVI_NATIVE_WP_PATH`; the homepage also requires
`BIOCO_HOME_ACCEPTED_SHA`, and the shell wrapper requires a private
`BIOCO_SHELL_WRAPPER_SNAPSHOT`. Missing configuration fails. Run:

```sh
python3 -m pytest -q tests/operators/wordpress_home_native.py
python3 -m pytest -q tests/operators/wordpress_editorial_cards_native.py
python3 -m pytest -q tests/operators/wordpress_shell_wrapper_adoption.py
```

The homepage check calls actual ButtonModule and ColumnModule style pipelines,
including a negative unsupported button-padding group. Card and wrapper checks
exercise installed parsing and style APIs. Normal browser rendering and native
save/reopen remain separate checks on unpublished, unassigned copies in each
environment. Keep private before exports, full row/metadata conflict guards and
actual restoration receipts for any later content adoption.

| Command | Needs | Gate |
| --- | --- | --- |
| `python3 -m pytest tests/ -q` | python3, php, node, Playwright Chromium | full suite, all green required |
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

Required coverage includes `test_wordpress_record_loop_preview.py` (keep: execute
registered Divi loop options against the shared record bindings, including record
permissions and draft isolation) and `test_wordpress_image_delivery.py` (keep:
WordPress responsive candidates, conditional Divi filter removal, attachment-backed
galleries and the existing gallery fallback). Both modules run without licensed
Divi; hosted editor save/reopen and actual image rendering remain separate checks.

Grouped by behaviour. Status `keep` (unchanged this pass), `trimmed` (redundant assertions removed,
replacements documented), `replaced` (rewritten as behavioural tests), `new` (added this pass).

### Shell, navigation & chrome

| File | Status | Behaviour |
| --- | --- | --- |
| `test_wordpress_issue_completion.py` | new | Depot website links and safe map popups, signup-only minimal shell, Schnuppertag arrival guidance, and CI capture-browser prerequisites. |
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
| `test_wordpress_consent.py` | new (#154) | Real Chromium executes the consent and Matomo scripts with intercepted traffic: opt-in, category independence, persistence, withdrawal, map teardown, escaped editor copy and missing/expired configuration. No real tracking requests or live form submissions. |
| `test_wordpress_newsletter_operations.py` | new (#157) | Real PHP callbacks with captured WordPress storage and mail transport: administrator capability/nonce boundaries, confirmed-only CSV and formula neutralization, signed GET/POST unsubscribe, re-opt-in generations, queued batching, recipient rechecks, locking and duplicate-submit protection. Captured transport does not prove inbox delivery or DKIM. |
| `test_wordpress_security_controls.py` | new (#163) | Real PHP filters/actions prove anonymous users API restriction, authenticated editor access, connected-peer login limits, protection before password hashing and fixed lockout expiry. Successful logins do not reset the peer-wide counter. Production server configuration remains a separate acceptance gate. |
| `test_wordpress_leaflet_assets.py` | keep | Vendored Leaflet files exist unmodified, registered handles used only by map blocks, marker images resolve inside the vendored dir. |
| `test_wordpress_membership_handoff.py` | keep (updated #181) | Pricing calculator → membership form selection handoff now runs through the real shared lifecycle runtime plus the real adapter in one minimal DOM context (Node VM); server-side tampering rejection unchanged. The full membership lifecycle matrix lives in the opt-in browser suite — no duplicated VM matrix. |
| `test_wordpress_forms_turnstile.py` | keep | Turnstile: official test keys only on unconfigured staging. |
| `test_wordpress_forms_assets.py` | new (#181) | Six form blocks reference pre-registered view-script handles with the shared runtime as (conditional) dependency (map-block pattern); the real `bioco_forms_localize_block()` localizes onto that handle and does NOT pre-enqueue the parser-blocking Turnstile script; adapters stay thin over the shared engine. The vendored real `WP_Dependencies` resolver proves the printed order (runtime before every adapter) AND that a missing runtime FILE still prints all six adapters without the dependency (CodeRabbit blocker: a missing dependency handle would otherwise silently omit the adapters' own fail-closed listeners). |
| `test_wordpress_forms_lifecycle.py` | new (#181, opt-in browser) | All six real PHP-rendered forms in Chromium with intercepted fetch/Turnstile (no real network, no submissions): tracer contact submit, in-flight duplicate guard, native validity gates, HTTP 400/200-false/malformed-JSON/network errors with per-adapter copy, retry with fresh token, expiry/widget-error isolation, two-instance/data-config isolation, terminal accepted response even when success display throws, six-adapter payload matrix (subscribe DOI, visit number, waiting CMS values, event hidden strings, membership redirect incl. explicit `forwarded:false`, selected checkbox arrays in DOM order), membership fieldErrors flattening + calculator matrix, missing-runtime fail-closed across all six forms (network abort AND emitted-without-runtime file case), loader branches (script error, spent pre-existing element, load-without-global, indefinitely hung transport). The hung transport is canonical-URL only: a bounded timeout latches an explicit German manual-reload copy, keeps values/caption, creates no further scripts, and a late-appearing global recovers without new transport — no URL workaround is claimed. Plus real local-runtime proofs: emitted runtime-before-adapter order without a blocking Turnstile tag, real mount, and the real-page hung-Turnstile no-native-GET/no-API-submit regression. |
| `test_wordpress_review_fixes.py` | keep | DOI confirmation has no public REST route, gallery filter validation, DOI nonce rules. |

### Design system & release infrastructure

| File | Status | Behaviour |
| --- | --- | --- |
| `test_wordpress_web_opcache.py` | new (#142) | Real PHP OPcache proves invalidation is limited to owned staging code. Unauthorized/expired probes are rejected; the temporary authenticated endpoint deletes itself and the shell helper cleans up after HTTP success or failure. |
| `test_wordpress_divi_design_system.py` | keep | Design-system checker contract (#134): token-only values, malformed manifest fail-closed behaviour, documented exceptions. |
| `test_wordpress_divi_foundation.py` | new (#134) | Manifest bundle matches its source; live-value bridge (editor override, archived fallback, stylesheet-injection rejection); seed dry-run/apply semantics against a fake vendor REST boundary: label conflicts report instead of duplicate, conflicted apply performs no write, clean rerun skips satisfied writes, unknown manifest preset titles fail the run. |
| `test_wordpress_visual_parity.py` | keep | 95% visual parity gate semantics: fail-closed results, masking discipline, threshold validation. |
| `test_wordpress_release_pipeline.py` | keep | Release pipeline: dry-run non-mutation, step order/abort, input hygiene, CI parity, hash-pinned deps. |
| `test_wordpress_release_package.py` | new (#280) | Real package CLI against an isolated Git fixture: deterministic owned bytes, checkout binding, rejected source/archive/evidence changes and trusted-run selection. Preflight and GitHub transport are controlled boundaries. This does not prove a hosted workflow run, installed WordPress/Divi compatibility or release preservation. |
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

## Production readiness slice

| File | Keep/Replace/Remove | Evidence |
| --- | --- | --- |
| `test_wordpress_home_presentation_preview.py` | Keep, required (#214, #220) | Missing-only native homepage controls cover hero, feature, CTA, live and home event wrappers. Query branches stay exact; unknown variants refuse. The live shape hook has a Chromium cascade check. Installed styles and GUI save/reopen remain separate. |
| `test_wordpress_editorial_cards_preview.py` | Keep, required (#214) | Pure cards/gallery conversion preserves saved content, responsive values, preset conflicts and empty gallery actions columns. Installed styles and GUI persistence remain separate. |
| `test_wordpress_editorial_cta_preview.py` | Keep, required (#214) | Real pure CTA/topic conversion preserves exact content and refuses unmeasured class sets and presets. Native save/reopen remains separate. |
| `test_wordpress_editorial_presentation_preview.py` | Keep, required (#214) | Executed PHP media/rich preview guards preserve authored styles, content and responsive inheritance. Hosted GUI persistence remains separate. |
| `test_wordpress_presentation_adoption.py` | Keep, required (#214) | Presentation preview gate uses actual parser and conditional SQL to preserve source bytes, explicit values and editor conflicts. Full metadata/site guards and public adoption remain separate operator checks. |
| `test_wordpress_shell_wrapper_adoption.py` | Keep, required (#249) | Private header/footer and exact PostContent body plans preserve module settings, assignments, authored domains, preset defaults and global values. Installed style/operator and native GUI checks remain separate. |
| `test_wordpress_steps_native_preview.py` | Keep, required (#214) | Bounded structural conversion creates native step cards from measured saved HTML, preserves paragraph/anchor bytes and refuses authored variants. Installed module styles and GUI expansion remain separate. |
| `test_wordpress_divi_preset_adapter.py` | Keep, required (#214) | Captured Divi 5.11.0 staging and 5.15.0 production helpers reproduce the native bug in Node. Both supported builds avoid default dispatch for populated maps, delegate empty maps, preserve live namespace getters/symbols and refuse unknown helpers or late loading. PHP tests execute the core bootstrap hook, native registered URL/path/hash resolution, exact inline-after injection and authorization/build/order gates. Successful CI enqueue tests replace only the filesystem hash boundary in an isolated subprocess; licensed vendor files stay outside fixtures. Actual Divi save/reopen remains a hosted proof. |
| `test_wordpress_media_bindings.py` | Keep, required (#219) | Native Image Content metadata source controls preserve existing module text by default. Actual PHP/editor callbacks agree on selected attachment bindings, blank library alt, decorative uses, responsive inheritance and unrelated controls. Tests cover fresh library lookup and private image permissions. Installed renderer and native save/reopen remain separate hosted gates. |
| `test_wordpress_secondary_presentation_preview.py` | Keep, required (#214) | Measured accordion, portal and native timeline header defaults preserve modules, links, native values and controller classes; opaque steps are refused. Installed style and save/reopen checks remain separate. |
| `test_wordpress_navigation_layout.py` | Keep, new (#249) | Real PHP saved-header renderer and Chromium CSS/JS prove equal header geometry on home, inner pages and a CPT boundary; desktop/mobile overflow, logo bounds, sticky utility collapse, keyboard menu/focus and saved design variables. The later Divi list rule is a controlled boundary. Actual Divi save/reopen and staging/production route parity remain separate release proofs. Required PR coverage. |
| `test_wordpress_event_back_button.py` | Keep, new (#217) | Real saved-layout generators, WordPress parser and Chromium wrapping/focus checks. Licensed preset rendering and native save/reopen remain hosted checks. |
| `test_wordpress_back_button_adoption.py` | Keep, new (#217) | Actual converter and exported SQL exercise percent escaping, byte-exact conditional apply/restore, idempotence and editor-conflict refusal. SQLite replaces MySQL execution; installed WordPress and hosted adoption remain operator checks. |
| `test_wordpress_native_event_lists.py` | Keep, new (#202) | Native trees retain labels, image/summary/link bindings, default fragment targets, source wrappers, unrelated HTML and siblings. Actual query adapter retains native filters, counts and sorting. Hosted rendering and save/reopen remain separate checks. |
| `test_wordpress_native_loop_empty.py` | Keep, new (#202) | Actual adapter covers copied native branches, nested-loop ownership, editor visibility and exception recovery. Licensed rendering remains a separate read-only installed-renderer proof. |
| `test_wordpress_record_layouts.py` | Keep, new (#203, #210) | Actual native group/calendar trees, dynamic record bindings, readable draft isolation, query boundaries and accessible calendar ordering. Licensed native editing and published record routes remain hosted checks. |
| `test_wordpress_record_adoption.py` | Keep, new (#203, #210) | WordPress block parser verifies one-section native adoption with saved labels, month order, design and neighboring blocks intact; conditional SQL apply/restore and conflict refusal run locally. Installed Divi save/reopen and hosted CAS remain release checks. |
| `test_wordpress_depot_adoption.py` | Keep, new (#209) | Actual WordPress parser checks the native depot card starter, preserved saved map and Geisshof settings, opaque neighboring content, ambiguous-source refusal and conditional SQL apply/restore conflicts. Licensed Divi save/reopen remains a hosted check. |
| `test_wordpress_gallery_editor.py` | Keep, new (#211) | Actual gallery PHP/schema and Chromium filtering, arbitrary saved categories, limits, captions, empty states and responsive controls. Native Divi save/reopen remains a hosted check. |
| `test_wordpress_native_form_composition.py` | Keep, new (#204) | Actual PHP native form parts, native candidate trees and real lifecycle in intercepted Chromium: external form association across reordered columns, duplicate-instance isolation, email validity, accessible field errors, retry, terminal success and missing/wrong controls remaining disabled. No licensed Divi rendering, save/reopen, live mail or production submission claim. Hosted unpublished copies remain required. |
| `test_wordpress_native_form_flows.py` | Keep, new (#205, #206) | Actual PHP controls and intercepted Chromium cover four signup payloads, seven ordinary membership steps, calculator prefill, back navigation, independent review values, server-error step selection, retry identity and saved thank-you routing. Pure native builders and catalog adoption stay explicit. Hosted Divi editing remains required. |
| `test_wordpress_native_form_outcomes.py` | Keep, new (#207) | Real confirmation backend with local storage/mail adapters covers GET and nonce rejection, invalid/expired/used tokens, one-time confirmation, exception state, duplicate controllers, cache headers and explicit browser POST. Native state builders plus opt-in message capture, archive preservation and rollback. No hosted subscriber token or licensed save/reopen claim. |
| `test_wordpress_form_adoption.py` | Keep, new (#204–207) | Real block parser, native parts and intercepted Chromium exercise saved-instance conversion, six membership steps, rich commitments, radio default, zero/blank choices, calculator handoff, backend error/retry identity, typed DOI copy, exact-byte apply/restore and separate option CAS. Unsupported source, concurrent edits, remaining legacy forms and incomplete native scopes refuse. SQLite covers SQL guards; licensed Divi and hosted MySQL remain separate release checks. |
| `test_wordpress_form_adoption_operator.py` | Keep, new (#204–207) | Private preview permissions, overwrite/symlink rejection and administrator boundary. No hosted writes. |
| `test_wordpress_editorial_accessibility.py` | Keep, new (#201) | Real child-theme JS/CSS in Chromium: Tab, Enter, Space and click, native heading semantics, expanded/panel state, saved fonts/colours, duplicate initialization and builder exclusion. A title-click boundary replaces licensed Divi; staged and production previews prove actual Divi activation. Required PR coverage. |
| `test_wordpress_editorial_layouts.py` | Keep, new (#201) | Real PHP migration: saved Divi attribute formats, preserved rich text/documents/anchors/siblings/order, rejected mixed markup and custom designs, idempotence. Required PR coverage. Native rendering and edit/save/reopen need separate unpublished staging/production copies. |
| `test_wordpress_membership_adapter.py` | Keep, extended | Real PHP REST acceptance, complete local payload, insert-before-mail, overlapping replay, notification false/exception retention, conflicts, storage failure, staging gates. WP storage/mail/CAPTCHA are controlled; no real InnoDB or delivery claim. |
| `test_wordpress_membership_admin.py` | Keep, new | Real PHP review callback denies non-admins before querying and escapes private records. No public REST hooks. |
| `test_wordpress_production_routing.py` | keep | Real generator plus limited rule evaluator covers route precedence, internal rounds, deny policy, canonical slash, hosts, handler choice and print-only behavior. Actual Apache behavior is covered below. |
| `test_wordpress_editor_fixtures.py` | new (#199, #215) | Runs the actual WP-CLI fixture helper against storage boundaries: page, Divi library and content record drafts retain serialized content and metadata, assignment copies are rejected, cleanup validates the entire set before deleting, inventory detects editorial metadata changes. |
| `test_wordpress_editor_isolation.py` | new (#199) | Tagged drafts hide active Theme Builder layouts only for users allowed to edit the copy. Published pages and unauthorized requests keep their assigned layouts. |
| `test_wordpress_editor_routing_http.py` | new (#199) | Runs the generated routing under real Apache with a symlinked private WordPress fixture. Proves all 32 editor libraries are served, stale root assets lose, private/vendor files remain denied, and the original routing fails. Exercises the shared HTTP probe against real HTTP responses. Release preflight requires Apache; CI installs it. |
| `test_wordpress_cms_archive.py` | Keep, new | Real CLI helper archives complete JSON, preserves current content/SEO, fills empty fields, validates before writing, previews without writes, and repeats safely. WP metadata is controlled. |
| `test_wordpress_matomo.py` | Keep, new | Real PHP enqueue/config gating and real native JS command order, cookie disabling, async loading, and inline escaping. No tracker network requests. |
| `test_wordpress_matomo_requests.py` | Keep, new | Real Chromium executes consent and Matomo scripts against local transport doubles: sanitized URL fields and HTTP Referer, same-origin and cross-origin trackers, one pageview after withdrawal/regrant. No live tracker requests. |
| `test_wordpress_timeline.py` | Keep, new | Real PHP timeline composition and Chromium layout: seed content, native responsive controls, presets, editor changes, legacy compatibility, overflow and keyboard focus. Divi editor persistence requires separate QA. |
| `test_wordpress_timeline_migration.py` | Keep, new (#213) | Real saved-content PHP conversion: current modules and order, rich links, normalized Divi attributes, responsive typography, anchors, conflict refusal and idempotence. No seed read or Visual Builder persistence claim. |
| `test_wordpress_depot_public_fields.py` | Keep, new (#209) | Actual catalog projection, depot PHP renderer, embedded JSON and popup JS; approved public fields appear, private details and hidden records do not. Stored private data remains unchanged. External Leaflet is replaced at its boundary. |
| `test_wordpress_prices.py` | Keep, new (#208) | Actual price catalog and PHP table/calculator consumers; explicit activation, stable handoff keys, new tiers, preserved totals, invalid-source refusal, empty catalogs and meaningful divergent/zero-value regressions. Native table migration and save/reopen remain separate. |
| `test_wordpress_native_prices.py` | Keep, new (#208) | Real WordPress block parsing, missing-only library import, native binding payloads, scoped catalog loop query and actual browser controller for totals, new tiers, zero, optional controls and handoff. The native DOM adapter replaces Divi render/query transport; UI save/reopen and licensed styling remain release gates. |
| `test_wordpress_price_adoption.py` | Keep, required (#208) | Synthetic saved table/calculator conversion, parser round trips, preserved copy, conditional SQL and intercepted browser handoff. Table overflow tests include a centered flex parent matching the installed Section behavior and prove both the first column and the final column stay reachable in phone, tablet and narrow desktop containers. Invoice currency geometry uses candidate layout attributes with synthetic DOM and sample amounts; it does not prove licensed Divi rendering. The missing-capture regression executes the separate operator gate and requires four failures, never skips. |
| `operators/wordpress_price_native_shape.py` | Keep, operator (#208) | Explicit staging SSH/WP configuration, actual installed block parser and roundtrip serialization of candidate and owned QA plans. It detects malformed invoice cells and checks native attributes. Actual rendering, save/reopen and restoration require separate hosted browser evidence. |
| `operators/wordpress_price_captures.py` | Keep, operator only (#208) | Explicitly selected checks convert the private staging/production table and calculator snapshots. Captures are mandatory; these four cases are outside automatic CI discovery. |
| `test_wordpress_utility_layouts.py` | Keep, new | Real PHP utility layout import and rendering with the WordPress block parser: preview/apply/repeat, editor preservation, authorization, conflict/rollback handling, search/404 status and SEO. WP storage and queries are controlled. |
| `test_wordpress_pr_gates.py` | Keep, new | PR workflow contracts, executed Claude authorization and JUnit coverage gates: explicit writer requests, permission failures, missing/malformed reports and required tests skipped or failed. GitHub permission responses are controlled. |

## Shared catalogs

Run the private price-capture gate explicitly from the repository root:

```sh
BIOCO_PRICE_CAPTURE_DIR="$HOME/.cache/bioco-editor-202" \
  python3 -m pytest tests/operators/wordpress_price_captures.py -q
```

Both `staging-price-pages.json` and `production-price-pages.json` must contain the
captured saved records for table page 6 and calculator page 10. Keep these captures
private and outside git. Missing or malformed files, changed body hashes and
refused conversion fail the operator gate; it never skips. Its local parser and
converter checks do not establish licensed Divi save/reopen or hosted adoption.
The synthetic adoption module remains required in CI with no capture dependency.

`wp bioco catalog` reports the one-time vegetable/depot import without writing.
After a staging backup and code release, `wp bioco catalog --apply --user=<administrator>`
initializes the lists from existing source snapshots. Existing editor records are adopted;
repeat imports preserve edits and deletions. The public modules retain their local rows
until initialization. A catalog emptied by an editor stays empty.

`test_wordpress_catalog.py` covers registration, field definitions, source reconciliation,
dry run, adoption, partial-import recovery, and edit/delete preservation. Catalog field
changes use WordPress post metadata; this suite does not prove a deployed Divi editor session.
A failed process can leave `bioco_catalog_lock_<kind>` behind. Before removing that option,
verify that no import is running; retain `bioco_catalog_pending_<kind>` so retry resumes.

## Editor acceptance on staging and production (#199–#215)

`python3 wordpress/scripts/check-editor-assets.py --url https://staging.bioco.ch`
and the same command with `https://bioco.ch` check HTTP status, JavaScript/CSS MIME,
nonempty bodies, origin, internal-path leaks and private-resource denial. This gate
runs after the route render gate on every staging release. On macOS, set
`SSL_CERT_FILE` to the installed trusted CA bundle if Python lacks its own bundle.
Never disable TLS verification.
The gate checks 32 core libraries and Rank Math's two named CMB2 editor assets.
Other plugin vendor files, PHP source and source maps remain denied.

The asset gate proves dependencies can load. It does not prove the Divi editor
can initialize, edit, save, reopen, duplicate or expand a layout. Verify those
actions on temporary unpublished copies in both environments. Retain screenshots
and interaction traces, inspect required network failures and runtime exceptions,
then remove copies and revoke test sessions. Compare published content and active
global-template hashes before and after. Never submit production forms or consume
live DOI tokens. Safe adapter tests retain the backend submission contract.

Production must also install the generated private child guard outside WordPress's
rewrite markers. The real Apache suite reproduces how a regenerated child block
exposes internal URLs without that guard, and checks encoded paths and nested
rewrite rules with it installed. Rerun the HTTP gate after administration actions;
a pre-login check alone does not establish that routing stays protected.
The staging release installs its generated asset guard outside WordPress markers,
with a private `.htaccess` backup and atomic replacement. It retains cPanel handlers
and protects vendor sources even when a descendant adds rewrite rules.
`test_wordpress_editor_guard_install.py` checks real file preservation, dry runs,
idempotence, private backups and malformed-region rejection. The Apache suite
executes the shipped guard, including a nested vendor rewrite and encoded paths.

For each family, record its saved block/template identity, editor controls changed,
rendered desktop/mobile result and cleanup. Keep frozen parity thresholds. A source
scan, a 200 response, a mocked renderer or a preserved screenshot alone cannot
establish editor ownership.

Draft fixtures can be created without changing published content via
`wp eval-file /private/code/editor-verification-fixtures.php create <source-id> <run>`.
The core plugin excludes active Theme Builder layouts from these tagged drafts.
Divi can otherwise convert and save assigned global layouts while saving a page
copy. Verify that the builder shows only the copied layout before editing.
Run the helper's read-only `check` action before opening or saving a verification
copy, including after any release. Creation refuses a missing isolation hook;
inventory and cleanup remain available. A changed release marker requires
coordination and a fresh check before editing resumes.
Use `inventory` before/after and `cleanup <run>` after testing. This helper rejects
global-assignment sources and validates every cleanup candidate before deleting
any copy. Transfer it outside the document root and remove it after verification.

Inventories retain every metadata hash and a full hash, plus an `editorial_hash`
that excludes only Divi's three generated asset-cache fields. Page visits can
recompute those fields. Keep their raw differences as evidence, and investigate
any content, assignment or editorial-metadata change. Concurrent editor revisions
must be preserved; never restore a stale database to make a comparison pass.

`test_wordpress_native_loop_empty.py` exercises the shared conditional adapter's
branch cloning, active-loop scope, editor visibility and exception recovery at
external boundaries. It does not emulate licensed Divi rendering. Run the
read-only installed-renderer probe and native save/reopen checks separately.

`test_wordpress_native_event_lists.py` checks native tree round trips and guarded
conversion, saved labels/links/anchors, explicit blank/false/zero values, editable
count/order/filter preservation, date-based query semantics and conflict refusal.
Hosted native editing and generated responsive CSS remain operator checks.
# Installed numeric token gate

Run this explicit read-only operator test against installed Divi 5. It evaluates
the candidate's two default helpers in memory, runs the installed frontend block
parser in its `et_builder_render_layout` context, then generates CSS with the real
Divi `ElementStyle`. It does not render a page, write records or flush CSS caches.
Missing host/path or installed APIs fails; this operator is outside automatic CI.

```sh
BIOCO_DIVI_NATIVE_SSH=bioco@193.33.128.160 \
BIOCO_DIVI_NATIVE_WP_PATH=/home/bioco/staging.bioco.ch \
python3 -m pytest -q tests/operators/wordpress_numeric_tokens.py
```

To reproduce the dropped numeric defaults with the same installed APIs, add
`BIOCO_NUMERIC_SOURCE_REF=63914c0428e0cd5ba8edd1db1356d5f056a9a31d`.
Both cases must fail on empty radius values before the fix. Local adoption tests
verify payload and preservation contracts; they do not prove Divi CSS rendering.

The measured accordion and portal defaults have a separate pure preview in `secondary-presentation-preview.php`. Its required tests preserve explicit responsive values, controller classes, content and links, and refuse opaque step HTML. The installed native module style check and later save/reopen proof remain separate from fixture tests.
