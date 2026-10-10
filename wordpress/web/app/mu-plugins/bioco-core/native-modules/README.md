# Native Divi modules

The 17 dynamic components appear as individual modules in the Divi module picker.
Their Content panel edits the ACF-defined fields; repeatable lists support adding,
removing and reordering entries. Images use the WordPress media library.
Form messages link to the shared **Formulartexte** admin screen.

Public output uses the existing `blocks/*/render.php` templates. Builder and REST
previews are inert: no live forms, CAPTCHA setup, mail or DOI confirmation runs.
Divi owns module decoration styles, classes and anchors.

## Updating fields

Edit the corresponding ACF Local JSON, then run from the repository root:

```sh
python3 wordpress/scripts/build-native-modules.py
```

Commit the generated `fields.json` and module metadata with the ACF change.
Public text defaults belong in content seeds, not in module metadata or PHP.
For a scoped field change, use `--components navigation_shell footer_shell` to
rebuild those modules while preserving metadata for other components.

## Header and footer

Kopfzeile and Fusszeile expose logo, labels, link lists, nested footer groups and
desktop/mobile presentation controls. Saved module values own the visible shell.
The canonical navigation JSON supplies the initial migration values only.

Back up the database, then preview `wp bioco editable-shell`. It replaces pure
shell shortcode leaves in header/footer layouts while retaining wrapper styles,
editorial siblings and template assignments. `--post=<id>` limits verification to
an unpublished copy. Use `--apply` only after reviewing the plan. Mixed editorial
text aborts validation; concurrent changes prevent the corresponding save.
Production acceptance uses tagged draft copies and never saves active assignments.

## Existing pages

Fresh imports compose native modules. To convert existing marker leaves without
reimporting editorial content, back up the database and run:

```sh
wp bioco native-modules
wp bioco native-modules --apply
wp bioco verify --runtime
```

The command validates every candidate first and only changes marker leaves.
Mixed text/marker modules and unknown fields stop the migration. An atomic comparison
protects each save against concurrent editorial changes, including on MyISAM. Re-running after
conversion writes nothing. Keep the legacy reader until all old pages/revisions
have been considered; a code-only deployment does not migrate page content.
