# Divi foundation (#134)

## Design target

Editors change shared visual values in Divi. Deploys preserve those edits. The
import command adds missing named variables, presets and global layouts using
Divi's supported interfaces. Existing page content stays intact.

## Approach A: generated CSS only

`build_tokens(manifest) -> css`; caller runs the generator before deployment.
Small interface, deterministic builds, but changes require Git and do not give
editors Divi presets or Theme Builder ownership.

## Approach B: manual builder setup

No runtime API; an editor creates variables and presets, then exports each set.
Uses only vendor UI, but repeated setup is difficult to verify and drift is easy.

## Approach C: seed through Divi, read through a CSS bridge

`Bioco_Divi_Foundation::seed(bool $apply): array` adds missing definitions.
`bioco_divi_token_css(): string` maps live Divi values to existing CSS properties.
Caller: `wp bioco design-system --apply --user=<administrator>`.
Theme Builder owns shared shell layouts. Menu and footer data keep their existing
WordPress editing surfaces through small render adapters.

## Tradeoff list

- A has the least runtime coupling but fails editor ownership.
- B needs no custom setup code but carries the highest repeated operator effort.
- C hides Divi payload details from the command caller and preserves edits, but
  needs integration checks when Divi changes its API.

## Depth evaluation

C has one setup operation and one rendering operation. It hides token IDs,
REST schemas, preset grouping and layout assignment. It is larger internally
than A and more version-sensitive than B.

## Chosen design

C, with actual untouched staging portability exports for transfer and audit.
The seed does not replace existing definitions or silently reset editor values.
CSS fallback values allow pages to render before setup.

## Shadow ownership boundary

Divi's box-shadow UI stores decomposed primitives (horizontal, vertical, blur,
spread, color) and cannot reference a composite CSS variable. The "BIOCO Card
Shadow" preset therefore seeds a copy of the manifest shadow value. After
seeding, two sources exist by necessity: Divi modules follow the preset in the
preset library; forms and dynamic CSS follow the live shadow variables through
the bridge. They are linked only at seed time. Changing a shadow in Divi's
variable manager updates CSS consumers, not the preset; change the preset for
Divi modules. A later Divi version that supports composite references in
box-shadow fields removes this split.

## Rationale

Editors need live Divi control; release automation needs repeatable verification.
The supported REST routes validate vendor data without direct writes to private
Divi option structures.

## Implementation steps

1. Seed named values and bridge them to current CSS properties.
2. Seed shared module and option-group presets.
3. Create and assign global shell and post-content layouts through Divi APIs.
4. Verify local editing and repeat setup, then release and export from staging.
5. Check exports and rendered route parity separately.
