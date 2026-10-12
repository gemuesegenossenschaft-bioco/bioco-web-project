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

## Gallery

The Galerie module's Content panel owns image rows, captions, category keys,
filter labels/order, initial count, more/less labels and the empty-category text.
Images use Divi's Media Library picker; row controls add, remove and reorder them.
Category keys may contain lowercase letters, numbers, hyphens and underscores,
starting with a letter. `all` is reserved for the filter that shows every image.
Additional filters may remain empty without creating images.

The same panel controls desktop/tablet/mobile columns, image fit/ratio/corners
and grid gap. Standard Divi controls own the module's surrounding design.
A count of zero shows every image. Clearing the more label disables the limit;
clearing the less label permits expansion without a collapse action. An older
saved module that has no less-label field retains its existing collapse wording.
Media IDs and alt metadata remain unchanged. Captions are escaped as plain text.

For hosted acceptance, make an unpublished, unassigned copy of the Gemüse page.
Add a temporary image and category, edit its caption, reorder it and its filter,
change the count/labels and mobile columns, then save and reopen. Check the copy's
frontend at mobile and desktop widths, including keyboard focus and the empty
category. Restore the copy's values, then delete the copy. Preserve the public
page and active global templates. Local parser/control/browser tests do not
replace this licensed Divi save/reopen proof.

## Depot maps and cards

Depot records in WordPress own the public name, coordinates, approved address,
website and website label. The Depot-Karte module reads those records in their
WordPress menu order. Its old local location rows are no longer offered in Divi
because edits to them were overridden by the catalog. Geisshof-Karte keeps its
own editable location rows.

Both map modules expose center, zoom, marker fitting, interaction, height and
map/address arrangement. Existing Depot-Karte address cards stay visible until
an editor replaces them on a page copy. For a fully editable card layout, turn
off **Eingebaute Adressliste anzeigen**, add a native Divi Loop with class
`bioco-depot-loop`, and place ordinary Divi Heading, Text and Button modules
inside each card. Bind them to `loop_bioco_depot_name`, `_address`, `_website`,
`_website_label` and `_route_url`. Divi controls the field order, link text,
card spacing and responsive columns. Only published, public depot records enter
the loop. Invalid or unapproved website destinations yield no link. Keep the
website button hidden when its URL binding is empty.

For acceptance, use an unpublished, unassigned page copy and a temporary depot
record. Change its menu order, approved address and website label, then check
marker/pop-up order and the native card loop. Save and reopen the copy after
changing map center, zoom and layout. Remove the temporary record and copy.
The code tests cannot prove licensed Divi save/reopen.

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
