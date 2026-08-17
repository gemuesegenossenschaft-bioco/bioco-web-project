# Theme-agnostic restructure — hard cases (GitHub #101)

One row per non-mechanical case. Read together with `PORTING-THEME-SWAP.md` (the mechanical
pattern catalogue); this file is where the mechanical patterns don't cleanly apply.

> **Joint review note:** cross-checked against `PORTING-THEME-SWAP.md` after both were drafted
> (bunny-loop Step 3). Finding: pattern 5 in that document restated its own "when" rule for helper
> moves, duplicating this file's Hard Case 2 table. Fixed there by making pattern 5 defer to Hard
> Case 2's table below as the single source of truth for per-function move timing. No other
> conflicts found on cross-check.

## Hard Case 1 — token vars only exist because the block theme's theme.json generates them

**Problem:** `assets/app.css` and several `render.php` inline `style="..."` attributes reference
`--wp--preset--color--bioco-*`, `--wp--preset--font-family--*`, `--wp--preset--font-size--*`,
`--wp--preset--spacing--*`, `--wp--custom--radius--*`, `--wp--custom--shadow--*`, and
`--wp--style--global--wide-size`. WordPress core only emits these as real CSS custom properties on
`:root` when the **active theme** has a `theme.json` that declares them (global styles output,
`wp_enqueue_global_styles`). Divi is a classic theme with no matching `theme.json` — under Divi none
of these properties exist, so every migrated block silently loses all color/spacing/radius/shadow/
font styling (not a crash — CSS custom property fallbacks aside, browsers just treat the property as
`unset`, and none of the current rules use a fallback value except one: `hero-container`'s
`var(--wp--style--global--wide-size, 1400px)`).

**Mapping:** `bioco-core` ships `assets/bioco-tokens.css`, declaring every one of these custom
properties on `:root` as **static, literal CSS values** — no `var()`, no theme.json dependency —
extracted 1:1 from `web/app/themes/bioco/theme.json`. It is enqueued as a hard dependency of
`bioco-blocks.css` (pattern 7 in the other doc), so it always loads first, everywhere (front end and
block editor). Under the block theme this exactly duplicates values WP core already generates
(harmless — same value, same specificity tier, last-defined wins and they're identical anyway).
Under Divi it is the *only* source of these values.

**Extraction (1:1, from `theme.json` as of this porting run):**

```css
:root {
  /* settings.color.palette[].slug -> --wp--preset--color--{slug} */
  --wp--preset--color--bioco-green: #2e7d32;
  --wp--preset--color--bioco-green-dark: #1b5e20;
  --wp--preset--color--bioco-carrot: #F29200;
  --wp--preset--color--bioco-beet: #87213D;
  --wp--preset--color--bioco-bg: #F6F9F5;
  --wp--preset--color--bioco-surface: #FFFFFF;
  --wp--preset--color--bioco-text: #1F2A1B;
  --wp--preset--color--bioco-text-muted: #4A4A4A;
  --wp--preset--color--bioco-border: #E1E4E8;

  /* settings.typography.fontFamilies[].slug -> --wp--preset--font-family--{slug} */
  --wp--preset--font-family--body: 'DM Sans', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;

  /* settings.typography.fontSizes[].slug -> --wp--preset--font-size--{slug} */
  --wp--preset--font-size--sm: 0.875rem;
  --wp--preset--font-size--base: 1rem;
  --wp--preset--font-size--md: 1.125rem;
  --wp--preset--font-size--lg: 1.25rem;
  --wp--preset--font-size--xl: 1.5rem;
  --wp--preset--font-size--2xl: 2rem;
  --wp--preset--font-size--3xl: 2.5rem;

  /* settings.spacing.spacingSizes[].slug -> --wp--preset--spacing--{slug} */
  --wp--preset--spacing--20: 8px;
  --wp--preset--spacing--30: 16px;
  --wp--preset--spacing--40: 24px;
  --wp--preset--spacing--50: 32px;
  --wp--preset--spacing--60: 48px;
  --wp--preset--spacing--70: 64px;
  --wp--preset--spacing--80: 96px;

  /* settings.custom.radius/shadow (nested) -> --wp--custom--{key}--{subkey} */
  --wp--custom--radius--sm: 12px;
  --wp--custom--radius--md: 18px;
  --wp--custom--radius--lg: 24px;
  --wp--custom--radius--pill: 999px;
  --wp--custom--shadow--sm: 0 1px 3px rgba(31,42,27,0.08);
  --wp--custom--shadow--md: 0 4px 12px rgba(31,42,27,0.12);
  --wp--custom--shadow--lg: 0 8px 24px rgba(31,42,27,0.16);

  /* settings.layout -> --wp--style--global--{content,wide}-size (WP global-styles layout vars) */
  --wp--style--global--content-size: 1160px;
  --wp--style--global--wide-size: 1400px;
}
```

**Conformance check:** `scripts/conformance.sh` greps every `--wp--[a-z-]+` reference in
`*.css`/`render.php` (any location) and diffs it against the set defined above; the "undefined vars"
list must be empty after the move (and in fact is already empty before the move, since the same
extraction covers the theme.json-generated set — the check only starts to matter once Divi drops the
generated source). If a future block introduces a *new* `--wp--preset--*`/`--wp--custom--*` reference
that isn't in `theme.json`, the check fails loudly instead of silently rendering unstyled under Divi.

Font loading: the literal `@font-face` for DM Sans (`theme.json` -> `assets/fonts/dmsans-variable.woff2`)
is generated by WP core's global-styles output the same way the custom properties are, and has the
same "only exists under the block theme" problem. It is solved by the Divi child theme
(`web/app/themes/bioco-divi/`), which bundles the woff2 + OFL license under
`assets/fonts/` and declares an explicit `@font-face` in its `style.css`.

## Hard Case 2 — functions.php split: block-shared vs theme-presentation

Every function/filter in the original theme `functions.php`, classified, and **when** it moves:

| Symbol | Classification | Moves to | When |
|---|---|---|---|
| `after_setup_theme` closure (`wp-block-styles`, `responsive-embeds`, `editor-styles`, textdomain load) | theme-presentation | stays in theme | never (theme's own bootstrap) |
| `wp_enqueue_scripts` closure enqueuing `assets/app.css` | block-CSS loader | **replaced** by `bioco-core`'s `bioco_core_enqueue_block_assets()` (pattern 7); theme's copy is deleted once `app.css` has no non-block chrome left to enqueue (Hard Case 5) | fleet completion |
| `acf/settings/save_json` / `acf/settings/load_json` filters | block-shared infra | `bioco-core.php`; after fleet completion `load_json` returns ONLY the `bioco-core/acf-json` path. Additive theme+plugin loading was a migration-window state, not the end state. | tracer bullet adds the plugin's copy; theme's copy deleted and plugin `load_json` made exclusive at fleet completion |
| `block_categories_all` filter | block-shared infra | `bioco-core.php`, verbatim | tracer bullet |
| `init` block-registration glob | block-shared infra | `bioco-core.php` (new copy over `bioco-core/blocks`); theme **keeps its own copy** globbing its own `blocks/` dir until empty | tracer bullet adds the plugin's copy; theme's copy deleted at fleet completion |
| `bioco_text_has_heading_html()` | block-shared helper (rich-text) | `bioco-core/includes/helpers.php` | tracer bullet (rich-text) |
| `bioco_kses_rich_text()` | block-shared helper (rich-text) | `bioco-core/includes/helpers.php` | tracer bullet (rich-text) |
| `bioco_query_events()` | block-shared helper (events-feed, schnuppertage) | `bioco-core/includes/helpers.php` | tracer bullet (events-feed's move is safe for the not-yet-migrated schnuppertage caller — see "Why this works" in the other doc: mu-plugin functions are globally available before theme render time) |
| `bioco_event_card_image()` | block-shared helper (events-feed, schnuppertage) | `bioco-core/includes/helpers.php` | tracer bullet |
| `bioco_event_date_parts()` | block-shared helper (events-feed, schnuppertage) | `bioco-core/includes/helpers.php` | tracer bullet |
| `bioco_render_events_list()` | block-shared helper (events-feed, schnuppertage) | `bioco-core/includes/helpers.php` | tracer bullet |
| `bioco_render_map_block()` | block-shared helper (depot-map, geisshof-map only) | `bioco-core/includes/helpers.php` | fleet (moves alongside depot-map/geisshof-map) |
| `bioco_image_filter_style()` | block-shared helper (media-text only) | `bioco-core/includes/helpers.php` | fleet (moves alongside media-text) |
| `bioco_render_person_icons()` | block-shared helper (pricing-table); render templates must not declare functions because repeated blocks on one page can include the same template twice and fatal on redeclare | `bioco-core/includes/helpers.php` | fleet (moves alongside pricing-table) |

**Rule for future additions:** a function is "block-shared" (-> plugin) unless it configures the
*theme's own* template/asset bootstrap (`after_setup_theme`, theme-only enqueues that are not block
CSS). Shared functions live in `includes/helpers.php`, never inside render templates. By the end of
the fleet step, the theme's `functions.php` contains **only** the `after_setup_theme` closure —
everything else in the table above has moved.

## Hard Case 3 — mu-plugin subdir loading

`bioco-core/bioco-core.php` relies on the exact same loading mechanism as `bioco-content` and
`bioco-forms`: **Bedrock's `roots/bedrock-autoloader`** (declared in `composer.json`, vendored at
`web/app/mu-plugins/bedrock-autoloader.php`, gitignored — see root `.gitignore`). It scans
`web/app/mu-plugins/*/*.php` for files carrying a standard plugin header comment
(`/** * Plugin Name: ... */`) one level deep and `require`s each one, exactly like a regular plugin
header — but for files that WordPress core itself would *not* auto-load (core only auto-loads
mu-plugin files directly inside `mu-plugins/`, not one level down in a subdirectory).

Verified by mirroring `bioco-content/bioco-content.php`'s header shape exactly:

```php
<?php
/**
 * Plugin Name: bioco Core
 * Description: Theme-agnostic block/ACF/helper infrastructure for all bioco blocks (#101). Moved out of the bioco block theme so the presentation theme (Divi) can be swapped without losing content or block behavior.
 * Author: bioco
 */

if (!defined('ABSPATH')) exit;
```

No other loader wiring is needed — dropping the file at `web/app/mu-plugins/bioco-core/bioco-core.php`
is sufficient, same as the two existing mu-plugins.

## Hard Case 4 — Divi child theme

`web/app/themes/bioco-divi/` is created with:

- `style.css` — theme header plus homepage layout styling: DM Sans `@font-face`, cream page
  background, scoped hero/feature/CTA layout, and responsive breakpoint. No design-token duplication
  (uses `--wp--preset--*`/`--wp--custom--*` values already defined by `bioco-tokens.css`).
- `functions.php` — enqueues the parent Divi stylesheet first, then the child theme's own
  `style.css` with `divi-parent-style` and `bioco-tokens` as dependencies and a filemtime version.
  (`get_template_directory_uri()` for the parent stylesheet is correct and intentional — this is the
  one place in the whole restructure where a theme-relative helper is *right*: it targets the **parent
  Divi theme's** stylesheet, which is a theme concern, not a block/content concern.)
- `README.md` in that folder, documenting: Divi is a **licensed, commercial product**; it is
  installed via `wp-admin > Themes > Add New > Upload Theme` (or a licensed deploy pipeline outside
  this repo); it is **never committed**. `web/app/themes/Divi` is added to the root `.gitignore` for
  exactly this reason. Root `README.md` and `composer.json`'s `description` are updated to mention
  the Divi dependency and where to get a license (documented as a manual step; no license key or URL
  is invented or committed here).

## Hard Case 5 — block theme demotion

`web/app/themes/bioco` stays in the repo as a **fallback/reference** theme:

- Its `functions.php` shrinks to only the `after_setup_theme` closure (Hard Case 2) once the fleet
  move completes — it becomes a normal, standalone, fully working native block theme with **zero**
  blocks/ACF-groups/helpers of its own, because all of those are supplied by the always-on
  `bioco-core`/`bioco-content`/`bioco-forms` mu-plugins regardless of which theme is active. Its
  `theme.json` and `assets/fonts/` stay untouched — they remain the canonical value source that
  `bioco-tokens.css` was extracted from (Hard Case 1), and the theme is still fully usable
  standalone (e.g. to verify a block visually, or as an instant rollback target if Divi is ever
  deactivated).
- A new `web/app/themes/bioco/README.md` documents this role: "This theme is a fallback/reference
  implementation. All blocks, ACF field groups, and shared render helpers live in the
  `bioco-core` mu-plugin (`web/app/mu-plugins/bioco-core/`) and work under **any** active theme,
  including this one. This theme now supplies only `theme.json` token values (used as the source of
  truth for `bioco-core/assets/bioco-tokens.css`) and minimal `after_setup_theme` presentation
  support. Do not add blocks, ACF groups, or shared helpers here — add them to `bioco-core`."

## Hard Case 6 — shared block-base CSS doesn't align to per-block comment sections

**Not in the original known-hard-cases list; found during the tracer bullet.** `assets/app.css` is
organized with one `/* {block} block */` comment per block, but several rule blocks are genuinely
**shared across many blocks**, not scoped to the block whose comment they happen to sit under:

- `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-orange` (defined in the hero section, lines 109-166)
  are used by 21 of the 31 blocks' `render.php` files (buttons, CTAs, event cards, form submits).
- `.cms-section`, `.cms-section-eyebrow`, `.cms-section-text`, `.cms-section-caption`,
  `.cms-section-actions` (defined right after hero, lines 237-265, under the shared "W5 layout
  blocks" comment) are used by 29 of the 31 blocks — effectively the base "section" chrome class.
- `.bento-card`, `.card-header`, `.card-header h3`, `.card-body`,
  `.bento-card > .card-header ~ *:not(.card-body):not(.card-footer)`, `.bento-card-fullwidth`
  (defined under the "link-tiles block" comment, lines 905-932) are used by `hero`, `events-feed`,
  `doi-confirm`, and `group-cards` — four unrelated blocks.

**Mapping:** these rule groups are treated as **shared block-base CSS**, not any single block's CSS.
They move to `bioco-core/assets/bioco-blocks.css` in the tracer bullet step (since `hero` needs
`.btn`/`.bento-card` and `rich-text` needs `.cms-section-*`), immediately alongside the tracer trio,
rather than waiting for their comment-adjacent block (`link-tiles`) to migrate in the fleet step.
Copying them into the plugin stylesheet early and leaving the theme's copy in `app.css` untouched
until fleet completion is the same "harmless duplication, single source of truth once cutover
finishes" pattern as Hard Case 1 — the not-yet-migrated blocks that still read from `app.css`
(`link-tiles`, `doi-confirm`, `group-cards`, and everything using `.btn`/`.cms-section-*`) keep
working unchanged throughout. At fleet completion, `app.css` is emptied entirely (Hard Case 5), so
the duplication resolves to a single copy living only in `bioco-blocks.css`.

## Joint review

See the note at the top of this file and the matching note at the end of `PORTING-THEME-SWAP.md`.
