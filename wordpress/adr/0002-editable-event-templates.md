# Saved event templates and record bindings

## Design target

#202 requires editors to arrange event details and cards in Divi while WordPress
owns event records. Code releases must preserve saved content. #229 owns the
legacy feed value contract; #217 owns the back button's visual redesign.

## Alternatives considered before implementation

### A. Built-in dynamic fields only

Interface: `template(post_title, featured_image, post_content)` and native loops.
Caller: an editor selects WordPress fields from Divi's dynamic content menu.

This uses the smallest interface and needs no new resolver. It loses the existing
card_image fallback, local event date formatting and empty-body summary behavior.
Copying those values into new metadata would create competing record fields.

### B. Saved templates with a record adapter

Interface: `event_fields(options)`, `event_value(value, context)` and
`event_template_blocks()`. Caller: a native Image binds to "Veranstaltung: Bild";
an editor moves that Image inside the saved Theme Builder body.

Divi owns the module tree and styles. One resolver hides date formatting, image
fallback and description precedence. Native loops can reuse the same bindings.
The cost is dependence on Divi's dynamic-content hooks and an explicit template
migration. Runtime tests and actual save/reopen checks must cover that boundary.

### C. Copy a reusable layout into every event

Interface: `copy_layout(event_id, layout_id)`. Caller: an editor copies a library
layout whenever they create an event.

This needs less Theme Builder integration. It requires repeated editorial work,
stores presentation in record bodies and cannot reliably apply later template
edits to all events. New events would not inherit the template automatically.

## Choice and depth evaluation

Choose B. Its callers select named values and ordinary native modules; they do
not handle date parsing, image IDs or content-filter context. A reasonable
reviewer could prefer A's smaller maintenance cost. B sacrifices that simplicity
to preserve the existing authoritative fields without duplicating their values.

## Implementation steps

1. Register named event bindings and generate an unassigned native body layout.
2. Permit explicitly assigned event templates; retain the legacy fallback until
   migration. Keep the post/article fallback for #230.
3. Test resolver context, actual WordPress block round trips and draft isolation.
4. Verify native edit/save/reopen on unpublished records and an unassigned body.
5. Migrate the event assignment separately, with a private before export, expected
   state guard, idempotence and restoration evidence. Do not modify event records.
6. Replace feed/card internals in a following #202 slice. This first slice alone
   does not complete #202 or implement #229/#230.
