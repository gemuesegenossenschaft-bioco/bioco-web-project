# bioco (fallback / reference theme)

This theme is a **fallback/reference implementation**, not the site's active presentation theme
(#101 — see `../../../PORTING-THEME-SWAP.md` and `../../../HARDCASES.md` at the repo root). The
active theme is `bioco-divi` (Divi 5).

All blocks, ACF field groups, and shared render helpers live in the **bioco-core** mu-plugin
(`web/app/mu-plugins/bioco-core/`) and work under **any** active theme, including this one — they
are not theme-specific. `blocks/` and `acf-json/` in this theme are intentionally empty.

This theme now supplies only:

- `theme.json` — the canonical source of the design token *values* (colors, spacing, radius, shadow,
  font). `bioco-core/assets/bioco-tokens.css` was extracted 1:1 from it and must be kept in sync if
  these values ever change (see `HARDCASES.md` Hard Case 1).
- `functions.php` — minimal `after_setup_theme` presentation support only.

**Do not** add blocks, ACF field groups, or shared render helpers here — add them to `bioco-core`
instead. This theme stays fully functional standalone (e.g. to visually verify a block, or as an
instant rollback target if Divi is ever deactivated), since bioco-core's blocks render identically
under it.
