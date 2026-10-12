# Adopt saved form instances

## Design target

The release operator needs a preview and an exact apply/restore guard for each saved form. Native Divi modules must own the captured headings, fields, choices, steps and outcome layouts. Backend identities, validation and external adapters stay unchanged.

## Alternatives

| Approach | Methods and caller | Benefit | Cost |
| --- | --- | --- | --- |
| A. Map saved attributes | `compose(group, attributes)`; `compose('membership', attrs)` | Short implementation | Misses hardcoded labels, rich commitments and effective message overrides |
| B. Capture inert output | `plan(record, options)`; `sql(plan, fingerprint, restore)` | Preserves the presentation that editors currently see | Needs a guarded DOM reader and refuses unfamiliar markup |
| C. Save opaque HTML | `wrap(renderedHtml)`; `wrap(currentForm)` | Closest initial appearance | Editors cannot arrange individual controls or native steps |

## Decision

Use B. The operator selects one record, captures its effective inert rendering, and reviews a native tree before any write. This preserves more knowledge behind a small interface, at the cost of stricter conversion guards than A. Unrecognized controls, responsive content differences and authored leaf markup require manual conversion.

Apply changes only `post_content` at the original leaf position. Full record and raw message-option comparisons prevent stale previews from overwriting edits. Retirement of the old presentation settings uses a separate option preview and CAS after every saved instance in the chosen group has been adopted. Restore retirement before restoring page content. Archived options and shared backend messages remain intact.

## Implementation

1. Capture current production and staging read-only.
2. Convert supported output into native content and independently arranged controls.
3. Exercise field defaults, arbitrary steps, DOI branches and concurrent edits locally.
4. Export private operator previews. Hosted Divi save/reopen and responsive checks remain release gates.
