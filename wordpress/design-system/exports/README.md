# Divi Portability Exports

## Overview

Exports are intentionally absent from the repo until an authenticated staging
browser/admin session is available. They are generated on demand from staging
and committed only when needed for a release.

## Three Official Export Types

1. **Design Variables** — Divi Theme Options → Portability → Export
2. **Presets** — Divi Presets Library → Export
3. **Theme Builder** — Divi Theme Builder → Portability → Export

Each type is a separate `.json` file. Do not combine them.

## Capture Rules

- Capture **actual untouched JSON only** from staging Divi. Do not prettify,
  reorder keys, or inject values by hand.
- **Back up** the current staging state before any import.
- Keep **three separate exports** (variables, presets, theme-builder) and treat
  each as an independent artifact.

## Secret / License Handling

Exported JSON must remain byte-for-byte untouched. Never sanitize an export
containing secrets or license material and commit it. Instead:

1. Inspect every export for `api_key`, `et_license`, or similar fields.
2. If any are found, **reject** the export for commit.
3. Quarantine the file (e.g. move outside the repo or to a protected scratch
   area) and alert the team. Do not strip secrets and commit the result.

## Provenance

Record metadata for every export in a **separate metadata record** (e.g.
`exports/<name>.meta.json` or a dated manifest). Do not modify the exported JSON
file to embed provenance. Required fields:

- Divi version
- UTC capture timestamp
- Repository commit SHA at time of capture
- SHA256 of the exported file

## Release Gating

- `--require-exports` is the `#134` completion / `#142` release gate.
- Until that gate is enabled, the preflight script only runs a contract check
  (`python3 wordpress/scripts/check-divi-design-system.py`) without failing on
  missing exports.
