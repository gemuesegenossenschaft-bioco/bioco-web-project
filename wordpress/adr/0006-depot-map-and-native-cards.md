# Depot map and native address cards

Status: accepted for #209 implementation. Production template adoption remains a separate editorial change.

The depot catalog owns public names, coordinates, addresses, approved website URLs and website labels. Saved location rows in the old Depot-Karte module are ignored after catalog migration, so the native editor no longer offers that control. Geisshof-Karte has no catalog and keeps its own rows.

We compared three designs:

1. Extend the PHP map block with more card toggles. This is small, but field order, actions and responsive card arrangement remain fixed in plugin markup.
2. Keep the map module for markers and provide a native Divi depot loop with dynamic public-record bindings for cards. Editors can arrange Heading, Text and Button modules and responsive columns in Divi. This uses the record and price loop seams already in the project.
3. Copy depot records into saved Divi rows. This makes each page editable but duplicates coordinates and links; later catalog edits can leave markers and cards inconsistent.

We chose option 2. The existing PHP address list remains the default until a page editor builds and verifies a native loop on an unpublished copy, then turns that list off. A code release never changes active templates. The loop selects only published, public depot records in WordPress menu order. Public bindings omit private descriptions and reject invalid websites. The map modules expose center, zoom, marker fitting, interaction, height and responsive map/list arrangement. Their default settings preserve the current public view.
