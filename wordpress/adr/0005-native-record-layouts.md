# Native group and calendar layouts

The current record catalog is authoritative. Editors need visible controls for layout and responsive design without changing queries or duplicating record data.

Three options were considered:

- Extend the custom group/calendar modules with more fields. This retains opaque PHP-owned card/table structure and local row fields that the catalog ignores.
- Use ordinary native modules, record bindings and native loops, with saved library layouts. A blank group body can consume its saved detail library through the existing Post Content route; authored record bodies stay intact. Calendar headings and record columns remain native modules.
- Create another Theme Builder assignment per record type. This offers native presentation, but requires a separate migration of shared assignment priority and shell ownership beside the existing event template work.

Use the second option. Backend code resolves readable records and published collection queries. Saved Divi modules own fields, headings, labels, columns, responsive widths, overflow and empty copy. The group route reads one published saved library; it creates no assignment and overwrites no record body. Unknown group contact destinations remain empty.

Calendar header order is the presentation source for repeated record cells. A scoped browser adapter aligns their columns and accessible header references; it adds no visible copy or CSS. Native row/column sizing and contained overflow remain editable saved attributes. The template has no local vegetable-data form: WordPress's Gemüse records own names, order and months.

Code and parser/browser checks do not establish licensed native rendering or UI persistence. The release owner must verify an unpublished copy before adopting public presentation.

## Collapsible calendar starter

The flat calendar remains available and existing Library entries are preserved. Add a separate closed native Toggle containing the same record-bound grid as native Groups. Divi 5.15 accepts Groups and modules inside Toggle; its insertion controls do not offer structural sections, rows or columns there. The new starter therefore converts only the copied structural containers to Groups, retaining cell content, grid sizing, table roles and contained overflow.

A single Toggle can close the whole calendar. Accordion would add panel management and start an item open. A rich-text table inside Toggle would lose the native cell controls and duplicate the authoritative vegetable data. Use the single Toggle and the existing keyboard-access adapter, with an H2 title and editable native open state.

The native Loop's `none` ordering choice retains the Gemüse catalog's `menu_order`, then title, for this marked calendar only. Explicit native sorts remain effective. This avoids storing the unsupported `menu_order`/`ASC` UI choices in the new starter. The original flat starter and its guarded adoption remain unchanged. New code creates no public assignment and updates no existing Library entry or vegetable record. Licensed rendering, keyboard/mobile behavior and ordinary save/reopen remain acceptance gates before adoption.

The runtime's saved-layout lookup lives in `bioco-core/includes/record-library.php`, shared with the importer. Loading all import composers on each public request created an unnecessary dependency and broke core-only asset verification. Keeping the full import package as a runtime dependency, deferring that dependency until rendering, and sharing only the lookup were compared. The shared lookup keeps runtime reads independent of presentation generation.
