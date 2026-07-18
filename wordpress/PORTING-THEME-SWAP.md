# Theme-agnostic restructure — porting guide (GitHub #101)

Goal: the group chose **Divi 5** as the presentation theme, but the theme itself must stay swappable.
Everything that is *content/behavior* (blocks, ACF field groups, shared render helpers, block CSS,
design tokens) moves out of `web/app/themes/bioco` into a new always-on mu-plugin,
**`web/app/mu-plugins/bioco-core`**. The block theme (`bioco`) is demoted to a fallback/reference
theme. A thin Divi child theme (`bioco-divi`) supplies only presentation (Divi itself + a stylesheet
enqueue).

This document is the pattern catalogue: every recurring theme-owned idiom and its plugin-owned
replacement. `HARDCASES.md` covers the trickier, non-mechanical cases (token CSS vars, the
functions.php split, mu-plugin subdir loading, the Divi child theme, theme demotion).

> **Joint review note:** this document and `HARDCASES.md` were cross-checked against each other
> after both were drafted (bunny-loop Step 3) to remove conflicting rules. See the "Joint review"
> section at the end of each file.

## Why this works: mu-plugins load before the theme

Bedrock's `roots/bedrock-autoloader` (already used by `bioco-content` and `bioco-forms`, see
Hard Case 3) requires every `web/app/mu-plugins/*/*.php` file that has a `Plugin Name:` header,
**before** the active theme's `functions.php` runs and before `init`/block-render time. That means:

- A PHP function defined in a plugin is globally callable from theme code (and vice versa) — there
  is no load-order hazard when a shared helper "moves" from the theme to the plugin.
- ACF Local JSON `load_json` paths from the theme and the plugin are **merged**, not exclusive, during
  the migration window only. Once the fleet move is complete, `acf/settings/load_json` returns ONLY
  the `bioco-core/acf-json` path; additive loading is not an end state.
- Block registration is likewise additive: the theme's `init` glob and the plugin's `init` glob
  register blocks from two different directories. As long as a given `block.json` exists in exactly
  one of those directories at any point in time (a real move, not a copy), there is no duplicate
  `register_block_type()` call.

This is why blocks/ACF-groups/functions can be moved one at a time (tracer bullet, then fleet)
without a big-bang cutover: at every intermediate commit, the theme's not-yet-moved blocks keep
working off shared plugin infrastructure exactly as they did before the move.

## Pattern catalogue

### 1. Theme-relative filesystem path -> plugin-relative filesystem path

**Theme (before):**
```php
$blocks_dir = get_template_directory() . '/blocks';
```

**Plugin (after), `bioco-core.php`:**
```php
define('BIOCO_CORE_DIR', __DIR__);
// ...
$blocks_dir = BIOCO_CORE_DIR . '/blocks';
```

`get_template_directory()` resolves to the *active theme's* directory — meaningless (and wrong) once
Divi is active. A plugin never has an "active theme" concept, so every internal path is anchored to
`__DIR__`/`__FILE__` of the plugin's own main file instead.

### 2. Theme asset URL -> plugin asset URL

**Theme (before):**
```php
wp_enqueue_style('bioco-app', get_template_directory_uri() . '/assets/app.css', [], $ver);
```

**Plugin (after):**
```php
wp_enqueue_style('bioco-blocks', plugin_dir_url(__FILE__) . 'assets/bioco-blocks.css', [], $ver);
```

Verified against WP core (`wp-includes/link-template.php` `plugins_url()`): it inspects the
filesystem path of `$file` and picks `WPMU_PLUGIN_URL` when the path is under `WPMU_PLUGIN_DIR`
(Bedrock's `web/app/mu-plugins`) or `WP_PLUGIN_URL` otherwise — i.e. `plugin_dir_url(__FILE__)`
**just works** from a file inside `web/app/mu-plugins/bioco-core/`, no special-casing needed. It
resolves to the same URL as the equivalent `content_url('mu-plugins/bioco-core/')`, but doesn't hard-code
the plugin's folder name, so prefer `plugin_dir_url(__FILE__)`.

`register_block_type()` / block.json's own `"viewScript": "file:./view.js"` resolution is **already
content-root-relative in WP core**, not theme-relative (`wp-includes/blocks.php`
`register_block_script_handle()` maps the metadata file's absolute path to a URL by swapping the
`WP_CONTENT_DIR` filesystem prefix for `content_url()`). So block.json `file:./...` references need
**zero changes** when a block folder moves from `themes/bioco/blocks/x` to
`mu-plugins/bioco-core/blocks/x` — this one auto-relocates for free.

### 3. ACF Local JSON save/load paths -> plugin acf-json dir

**Theme (before):**
```php
add_filter('acf/settings/save_json', fn() => get_template_directory() . '/acf-json');
add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = get_template_directory() . '/acf-json';
    return $paths;
});
```

**Plugin (after):**
```php
add_filter('acf/settings/save_json', fn() => __DIR__ . '/acf-json');
add_filter('acf/settings/load_json', function ($paths) {
    return [__DIR__ . '/acf-json'];
});
```

The theme kept its own (shrinking) copy of this filter pair pointed at its own `acf-json/` only while
it still owned un-migrated block field groups. During that migration window, additive loading was
safe because ACF merged registered `load_json` paths. After fleet completion, `load_json` must return
ONLY the `bioco-core` path; theme paths must not load.

### 4. Block category filter -> plugin

**Theme (before):** `add_filter('block_categories_all', ...)` in theme `functions.php`.

**Plugin (after):** identical closure, verbatim, in `bioco-core.php`. This filter isn't
path-dependent at all — it only needed to move because it's "block infrastructure", not theme
presentation (Hard Case 2 classification).

### 5. Shared helper functions -> plugin `includes/helpers.php`, loaded before block registration

**Theme (before):** `bioco_text_has_heading_html()`, `bioco_kses_rich_text()`, `bioco_query_events()`,
etc. defined directly in theme `functions.php`.

**Plugin (after):** moved verbatim into `bioco-core/includes/helpers.php`, `require`d at the top of
`bioco-core.php` (before the `init` hook that registers blocks, so render.php files calling them at
render time always find them defined — mu-plugins load in alphabetical filename order at
`muplugins_loaded`, well before any block renders).

```php
// bioco-core.php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/includes/helpers.php';
```

A helper moves alongside its **first migrated caller**, not only once every caller has moved — safe
because of the global-function load-order guarantee in "Why this works" above (a not-yet-migrated
theme block can still call a helper that now lives in the plugin). `HARDCASES.md` Hard Case 2 is the
single source of truth for the function-by-function classification and the exact tracer-bullet/fleet
step each one moves in; this pattern only states the general mechanism.

Shared functions live in `includes/helpers.php`, never declared inside render templates. A repeated
block on one page includes the same `render.php` more than once, so a render-local function
declaration causes a redeclare fatal.

### 6. Block registration glob -> plugin

**Theme (before):**
```php
add_action('init', function () {
    $blocks_dir = get_template_directory() . '/blocks';
    if (!is_dir($blocks_dir)) return;
    foreach (glob($blocks_dir . '/*/block.json') as $block_json) {
        register_block_type(dirname($block_json));
    }
});
```

**Plugin (after):** the same closure, `BIOCO_CORE_DIR . '/blocks'` instead of
`get_template_directory() . '/blocks'`. The theme **keeps its own copy of this exact glob** (pointed
at its own, shrinking `blocks/` dir) for as long as it still owns any blocks — see Hard Case 5. Once
every block has moved, the theme's copy has nothing left to glob and is deleted.

### 7. `app.css` block CSS -> plugin `assets/bioco-blocks.css`, enqueued on both hooks

**Theme (before):** one `wp_enqueue_scripts` hook only (front end); the block editor got no
equivalent enqueue, so editor canvas preview never matched the front end pixel-for-pixel.

**Plugin (after):**
```php
add_action('wp_enqueue_scripts', 'bioco_core_enqueue_block_assets');
add_action('enqueue_block_editor_assets', 'bioco_core_enqueue_block_assets');

function bioco_core_enqueue_block_assets() {
    $tokens = BIOCO_CORE_DIR . '/assets/bioco-tokens.css';
    $blocks = BIOCO_CORE_DIR . '/assets/bioco-blocks.css';
    wp_enqueue_style('bioco-tokens', plugin_dir_url(__FILE__) . 'assets/bioco-tokens.css', [], (string) filemtime($tokens));
    wp_enqueue_style('bioco-blocks', plugin_dir_url(__FILE__) . 'assets/bioco-blocks.css', ['bioco-tokens'], (string) filemtime($blocks));
}
```

`bioco-tokens` is declared as a dependency of `bioco-blocks` so WP always prints it first — required
by Hard Case 1 (the vars must exist before anything references them; harmless if it doesn't
matter, but keeps intent explicit and correct under any enqueue-order edge case).

### 8. The `--wp--*` custom-property dependency -> static token stylesheet

Block CSS and `render.php` inline `style="..."` attributes reference `--wp--preset--color--bioco-*`,
`--wp--preset--spacing--*`, `--wp--preset--font-*`, `--wp--custom--radius--*`,
`--wp--custom--shadow--*`, and `--wp--style--global--wide-size`. These only exist on the page because
the **active block theme's** `theme.json` makes WP core generate them into `:root` (or
`.wp-site-blocks`) global-styles CSS. Once Divi (a classic theme with no relevant `theme.json`) is
active, none of that generated CSS exists and every block goes visually blank/unstyled. Full mapping
and value-extraction rule: see `HARDCASES.md` Hard Case 1.

## Directory shape after the full move

```
web/app/mu-plugins/bioco-core/
├── bioco-core.php              # loader: ACF paths, category, block glob, CSS enqueue, helpers require
├── includes/
│   └── helpers.php             # every bioco_* shared render helper
├── acf-json/                   # every group_bioco_block_*.json, group_bioco_cpt_*.json, group_bioco_section_common.json
├── blocks/                     # all 31 block dirs (block.json + render.php + view.js), unchanged internals
└── assets/
    ├── bioco-tokens.css        # static --wp--* custom properties, 1:1 from theme.json (Hard Case 1)
    └── bioco-blocks.css        # all block-scoped CSS, moved wholesale from theme app.css

web/app/themes/bioco/           # DEMOTED: fallback/reference theme, see its own README.md
├── functions.php               # only after_setup_theme supports (Hard Case 5)
├── theme.json                  # kept as the canonical source theme.json (Hard Case 1 extracts FROM this file)
├── assets/app.css              # empty / non-block chrome only once fleet move completes
└── blocks/, acf-json/          # empty once fleet move completes

web/app/themes/bioco-divi/      # NEW: thin Divi child theme, see Hard Case 4
```

## Joint review

Reviewed together with `HARDCASES.md` after both were drafted (Step 3 of the bunny loop). Finding:
pattern 5 originally restated its own "when does a helper move" timing rule, duplicating (and risking
drift from) `HARDCASES.md` Hard Case 2's per-function table. Fixed by making pattern 5 state only the
general mechanism and defer to Hard Case 2 as the single source of truth for timing. No other
conflicts found; the remaining Hard Case cross-references (1, 2, 3, 4, 5, 6) were checked against
their corresponding pattern numbers here and point at matching content. The orchestrator's
independent adversarial review runs after this note was added.
