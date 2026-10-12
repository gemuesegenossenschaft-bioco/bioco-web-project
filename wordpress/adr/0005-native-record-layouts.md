# Native group and calendar layouts

The current record catalog is authoritative. Editors need visible controls for layout and responsive design without changing queries or duplicating record data.

Three options were considered:

- Extend the custom group/calendar modules with more fields. This retains opaque PHP-owned card/table structure and local row fields that the catalog ignores.
- Use ordinary native modules, record bindings and native loops, with saved library layouts. A blank group body can consume its saved detail library through the existing Post Content route; authored record bodies stay intact. Calendar headings and record columns remain native modules.
- Create another Theme Builder assignment per record type. This offers native presentation, but requires a separate migration of shared assignment priority and shell ownership beside the existing event template work.

Use the second option. Backend code resolves readable records and published collection queries. Saved Divi modules own fields, headings, labels, columns, responsive widths, overflow and empty copy. The group route reads one published saved library; it creates no assignment and overwrites no record body. Unknown group contact destinations remain empty.

Calendar header order is the presentation source for repeated record cells. A scoped browser adapter aligns their columns and accessible header references; it adds no visible copy or CSS. Native row/column sizing and contained overflow remain editable saved attributes. The template has no local vegetable-data form: WordPress's Gemüse records own names, order and months.

Code and parser/browser checks do not establish licensed native rendering or UI persistence. The release owner must verify an unpublished copy before adopting public presentation.
