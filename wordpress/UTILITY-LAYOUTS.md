# Editable utility and page layouts (#212)

The Divi child theme loads `bioco-core/includes/utility-queries.php` after theme
setup. That adapter loads the new importer include. No integration line is
needed in `bioco-import.php`, `cli.php`, or the global shell importer.

## Import

With Divi 5 and the bioco Divi child theme active, initialize the existing shared
design presets first using `wp bioco design-system`. Review that command's plan
before applying it, following `design-system/README.md`.

Preview the seven missing library layouts:

```sh
wp bioco utility-layouts --user=ADMINISTRATOR_LOGIN
```

Add them only after reviewing the plan:

```sh
wp bioco utility-layouts --apply --user=ADMINISTRATOR_LOGIN
```

This command writes only `et_pb_layout` library posts and their `layout_type`
classification. It never assigns Theme Builder templates or changes pages,
posts, Rank Math settings, or the public starter post. Duplicate library slugs
stop the entire plan before any write. Run while editors are not changing these
library entries.

Every existing entry is preserved. A saved title/content fingerprint reports
`unchanged`, `preserve-editor` or `preserve-version`; even an untouched older
seed is retained. Private, draft and trashed entries reserve their slugs and are
preserved. A rerun adds only missing layouts. A classification failure rolls
back only the library post created in that attempt, allowing a clean retry.

## Editing and reuse

Divi Library contains:

- BIOCO Suchergebnisse
- BIOCO Suche ohne Treffer
- BIOCO Seite nicht gefunden
- BIOCO Einzelner Suchtreffer
- BIOCO Inhaltsseite
- BIOCO Rechtliches und Dokumente
- BIOCO Intranet-Information

Headings, body text, action labels/targets, native Search modules, section order,
responsive settings and shared preset references are saved Divi modules. The
PDF URL in the document starter is a placeholder; replace it before publishing.
The intranet starter provides a link, not authentication or an integration.

The search-result item layout owns the arrangement of each result. Keep
`{{bioco_result_title}}`, `{{bioco_result_excerpt}}` and `{{bioco_result_url}}`
where the current result's data should appear. The query adapter substitutes
escaped data in parsed attributes and renders through the normal content
filters. It uses the WordPress main query without advancing the loop or
changing the global post. Pagination labels live in the saved shortcode
attributes. Search and 404 requests select their published library body;
a missing/non-published library entry uses the bundled native starter without
writing anything.

A saved custom Theme Builder body takes precedence. Only the known
`bioco-global-body` containing exclusively the Post Content passthrough and
container modules is bypassed for search/404. That request uses the existing
child-theme shared header/footer. Saved global templates remain unchanged.
An edited default body is also preserved and may need an editor to provide its
own search/404 body.

WordPress keeps 404 responses at HTTP 404, including a missing paginated search
request. The adapter reinforces 404 status before headers and uses no redirects.
Rank Math is the sole SEO emitter: its robots filter enforces noindex for search
and 404 while retaining other directives. Rank Math's [404 paper](https://github.com/rankmath/seo-by-rank-math/blob/master/includes/frontend/paper/class-error-404.php)
already returns noindex; its [search paper](https://github.com/rankmath/seo-by-rank-math/blob/master/includes/frontend/paper/class-search.php)
depends on `titles.noindex_search`. No additional robots tag, canonical tag,
`wp_head` emitter or robots HTTP header is introduced. Rank Math must remain
active on the target site.

## Code evidence

```sh
python3 -m pytest tests/test_wordpress_utility_layouts.py -q
python3 -m pytest tests -q
```

The PHP harness uses WordPress's real block parser and native serialization.
It executes the importer, query adapters, child templates, registered hooks,
and existing legal/document/intranet seed composer. It verifies preview,
idempotency, edit preservation, native presets, escaped results, pagination,
status, SEO policy and a draft page's add/reorder/save/reopen round trip.
WordPress storage and Divi rendering are harness seams; this does not prove
licensed Visual Builder behavior, generated CSS or production persistence.

## Follow-up on temporary unpublished copies

A human operator must perform these checks after the batch is reviewed. This
lane made no production/staging writes and did not contact bioco.ch.

1. Check the deployed revision against the batch's intended release. Verify
   Divi 5, the child theme, shared presets and Rank Math are active.
2. Preview the library import. Review and apply it only as an approved release
   step. Leave active global template assignments unchanged during acceptance.
3. Create temporary unpublished copies of utility layouts and legal/document/
   intranet pages. Confirm editable headings, body, action URLs, download URLs,
   link targets, section ordering and responsive settings in Visual Builder.
   Check utility bodies through a local preview fixture with a populated and
   empty main search query; do not assign copies to active global templates.
4. Create a new draft page using BIOCO Rechtliches und Dokumente. Change its H1,
   replace the PDF placeholder, add a text module, reorder sections, save and
   reopen. Check desktop/mobile appearance and link targets. Remove all QA
   copies afterward.
5. Verify rendered search, empty search and 404 responses, pagination, one
   robots emitter and noindex. A missing URL must return 404, not a redirect or
   a soft 404. Review any preserved custom Theme Builder body with its editor.
6. Record the public starter-post decision with its owner: retain, revise,
   unpublish or redirect. No disposition has been agreed or implemented here.

Form outcomes and submissions belong to the separate form lane.
