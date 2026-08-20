# Bioco WordPress Design System

## Scope

Canonical WordPress token source: `wordpress/web/app/themes/bioco/theme.json`.
The block theme is the single source of truth for colors, typography, spacing,
radius, and shadow tokens. `bioco-tokens.css` mirrors these as static CSS
custom properties so Divi (classic theme, no theme.json) can consume the same
values.

Canonical Divi contract manifest: `wordpress/design-system/v1/manifest.json`.
This manifest defines the expected token contract, option-group presets, element
presets, and theme-builder templates against which staging exports are validated.

## Staging-only Workflow

All Divi portability exports are produced from the **staging** environment only.
Never export from local or production. The canonical manifest lives in Git;
exports are ephemeral artifacts derived from a known staging state.

## Exports

See `exports/README.md` for the three official export types, capture rules, and
gating logic.
