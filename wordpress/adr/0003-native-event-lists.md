# Native event lists

## Design target

#202 needs saved Divi card trees with editable labels, element order, limits,
query ordering, empty/recap states and responsive layout. WordPress owns records.
Keep date-based upcoming/past semantics and the legacy general-event fallback.
Retain legacy readers until every active list has a verified replacement.

## Alternatives before implementation

### A. Add controls to the existing event modules

Interface: `event_module(values, styles)`. An editor chooses card fields from a
fixed set and adjusts grouped styles.

This preserves the current markup and value contract with little migration
work. It leaves card internals and the supported arrangements in PHP. Adding a
new ordinary visible element would still require source changes.

### B. Native loop rows and native card elements with a query adapter

Interfaces: `event_list_blocks(values)`, `event_loop_query(data, attrs)` and
`event_empty_output(output, attrs, block)`. An editor adds or moves a native
Heading, Image, Text or Button inside the saved loop row and changes the native
loop's count, sort direction or metadata filters.

The adapter handles date semantics and general-event compatibility. Native Divi
owns the saved tree and typography. Empty-state text is an ordinary saved Text
module, projected into the native empty-results path. Conditional state may
hide that text when records exist; it supplies no fixed editorial copy.

This requires actual proof of native loop save/reopen, shared bindings and the
empty-results rendering seam. More native framework behavior must be checked
than in A. The migration must preserve instance values, links and containers.

### C. Register a custom parent with editable child card modules

Interfaces: `event_container(query, children)`, `event_child(fields, design)`.
An editor builds the card using the new plugin's child modules.

This offers one focused editor surface but creates a second module hierarchy
and more custom builder code. It duplicates normal Divi controls and needs its
own nested save and render protocol.

## Choice and depth evaluation

Choose B. The installed native renderer has passed a read-only empty/populated
projection probe with the shared conditional adapter. Callers use normal
Divi modules and native query controls. The adapter hides the local date split,
manual early recap and missing legacy event-type behavior. A is smaller and
preserves markup more directly; B trades that simplicity for editable module
arrangement. A reviewer could reasonably prefer A if the native loop seam fails.

## Implementation steps

1. Prove native loop rows and empty-result projection on unpublished copies.
2. Share existing event query argument construction; do not duplicate date logic.
3. Generate native list/card trees from saved values, with exact legacy value
   preservation or explicit refusal for unsupported/conflicting values.
4. Test limits, order, filters, empty states, query context and block round trips.
5. Review and release code separately from content migration.
6. Migrate saved lists with preview, backups and conditional writes; verify native
   edit/save/reopen/add/reorder and new-record inheritance on both environments.
7. Compare records, URLs, editorial fingerprints and frozen desktop/mobile views.

## Evidence and remaining work

The native final-output hook can render selected saved empty branches through
Divi's own Row callback after removing its runtime no-results flag. The earlier
no-results hook alone fails because Divi replaces those children again. The
shared adapter clones complete branches and ancestor paths, preserves context,
and hides marked empty branches only inside active marked record loops. It
keeps every branch visible in the native editor.

Four boundary tests cover branch selection, scope, editor behavior and exception
recovery. A read-only installed-renderer probe confirmed native columns, authored
empty text with no cards, and populated cards with no empty text. It wrote no
content or global assignment. Query adaptation and saved-list conversion now pass boundary and real WordPress
parser checks. A read-only installed-renderer probe resolves actual published
event titles/links, suppresses the empty message when records exist, and hides
the recap heading with no records. Native grid classes are emitted; generated
responsive CSS and hosted save/reopen evidence remain required. This does not complete #202 or #229.
