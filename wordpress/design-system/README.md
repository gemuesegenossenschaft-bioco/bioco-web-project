# Bioco WordPress design system

Divi global variables own the live design values. Shared option-group presets
own reusable module styles. Theme Builder owns the default header, body and
footer. Page content stays in its existing Divi modules.

## Seed and preserve edits

The initial contract is `v1/manifest.json`. Its bundled copy ships inside
`bioco-core`, so the command works with both vanilla WordPress and Bedrock.
After changing the contract, run:

```sh
python3 wordpress/scripts/build-divi-design-system.py
```

On a Divi 5 installation, using an existing administrator:

```sh
wp bioco design-system --user=<administrator>
wp bioco design-system --apply --user=<administrator>
```

The first command reports missing definitions. The second adds them through
Divi's REST interfaces. It preserves existing IDs, values, presets, populated
layouts and other templates. Run setup while no editor is changing global
settings. A second run reports zero missing definitions.

The command does not reimport pages. A pre-existing default template keeps every
occupied slot. Empty slots receive BIOCO layouts; review those assignments in
Divi → Theme Builder before further editing.

## Editing

- Change named values in Divi's variable manager.
- Change shared styles in Divi's preset library. Module presets reference the
  option-group presets, so those changes propagate.
- Edit shared layout placement in Divi → Theme Builder.
- Header/footer shortcodes keep links and contact data in the existing WordPress
  navigation settings. They are small adapters inside native Divi text modules.
- The body uses Divi's Post Content module and leaves each page editable.

`bioco-core/includes/design-system.php` maps live values onto the CSS properties
already used by forms and dynamic components. `bioco-tokens.css` and the fallback
block theme's `theme.json` provide initial values before Divi setup. CSS injection
and inactive variable values are ignored by the bridge.

## Verification and transfer

```sh
python3 wordpress/scripts/build-divi-design-system.py --check
python3 wordpress/scripts/check-divi-design-system.py
python3 wordpress/scripts/check-divi-design-system.py --require-exports
```

The last command is the completion gate. A passing contract check alone does
not prove the staging exports exist or that pages meet visual parity.

Capture the three untouched staging exports and their separate provenance
records using [exports/README.md](exports/README.md). Import through the matching
Divi portability surface, then rerun setup in dry-run mode and the rendered
checks. Export files containing credentials or license data must not enter Git.
