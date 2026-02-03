# ProcessWire Enhancement Scripts Overview

All migration and setup scripts created for the biocò CMS enhancement plan.

## Quick Reference

| Phase | Script | URL | Purpose | Status |
|-------|--------|-----|---------|--------|
| 1 | setup-media-library.php | `/setup-media-library/` | Central media library | ✅ Ready |
| 2 | create-page-templates.php | `/create-page-templates/` | Per-page templates | ✅ Ready |
| 3 | consolidate-image-fields.php | `/consolidate-image-fields/` | Field consolidation | ✅ Ready |
| 4 | add-image-styling-fields.php | `/add-image-styling-fields/` | Image overlays & colors | ✅ Ready |
| 5 | configure-ckeditor-fields.php | `/configure-ckeditor-fields/` | Rich text editor config | ✅ Ready |
| 6 | update-button-labels.php | `/update-button-labels/` | Button style labels | ✅ Ready |
| 7 | verify-eyebrow-field.php | `/verify-eyebrow-field/` | Eyebrow field verify | ✅ Ready |
| 8 | add-navigation-field.php | `/add-navigation-field/` | Navigation auto-gen | ✅ Ready |
| 9a | translate-labels-german.php | `/translate-labels-german/` | German translations | ✅ Ready |
| 9b | cleanup-unused-fields.php | `/cleanup-unused-fields/` | Field cleanup analysis | ✅ Ready |

---

## Detailed Script Descriptions

### Phase 1: setup-media-library.php

**Purpose**: Set up central media library for reusable images

**What it does**:
- Creates media library page at `/medien/`
- Enables tag support on image fields
- Configures grid display for image selection
- Documents media library setup steps

**Fields modified**:
- section_image
- card_image
- event_card_image
- hero_image
- og_image

**Access**: https://cms.bioco.ch/setup-media-library/

**Output**: JSON response with log, errors, media library path

**Data Impact**: Additive only, no existing data modified

---

### Phase 2: create-page-templates.php

**Purpose**: Create dedicated templates for each page

**Templates created** (12 total):
- home (Homepage with hero)
- wir (About us)
- gemuese (Vegetables)
- mitmachen (Participate)
- abos (Subscriptions)
- solawi (Solidarity farming)
- standorte_depots (Locations)
- aktuelles_page (Current news)
- bioco_werden (Become member)
- kontakt (Contact)
- newsletter (Newsletter)
- warteliste (Waiting list)

**What it does**:
- Creates all templates with appropriate field sets
- Creates corresponding pages under root
- Each page uses content_sections repeater
- Page-specific fields added as needed

**Access**: https://cms.bioco.ch/create-page-templates/

**Output**: JSON with templates created, pages created

**Data Impact**: Creates new pages (existing pages unchanged)

---

### Phase 3: consolidate-image-fields.php

**Purpose**: Consolidate duplicate image fields

**Consolidation**:
- OLD: hero_image, section_image, card_image, event_card_image (4 fields)
- NEW: hero_image, image (2 fields)
- ALT: image_alt (unified, was image_alt + event_card_image_alt)

**What it does**:
- Creates new universal `image` field
- Updates template field lists
- Preserves old fields for safety
- Maps old fields to new fields

**Field reduction**: 50% fewer image field definitions (6 → 3)

**Access**: https://cms.bioco.ch/consolidate-image-fields/

**Output**: JSON with consolidation summary, mapping

**Data Impact**: Adds new fields, preserves old for migration

**Manual migration needed**: Copy data from old fields to new fields per page

---

### Phase 4: add-image-styling-fields.php

**Purpose**: Add overlay and background color options

**New fields** (added to repeater):
1. section_image_overlay (Options)
   - none, dark, green, orange

2. section_bg_color (Options)
   - none, green, darkgreen, orange, gray, white

**What it does**:
- Creates FieldtypeOptions fields
- Adds to repeater_content_sections template
- Documents frontend implementation needed

**Access**: https://cms.bioco.ch/add-image-styling-fields/

**Frontend**: Already implemented ✅
- CSS classes in globals.css
- SectionRenderer applies classes
- Types defined in processwire-types.ts

**Output**: JSON with fields created, implementation guide

**Data Impact**: Adds optional styling fields only

---

### Phase 5: configure-ckeditor-fields.php

**Purpose**: Configure rich text editor with biocò toolbar

**What it does**:
- Updates textarea fields to use CKEditor
- Documents toolbar configuration needed
- Lists fields to apply config to

**Fields configured**:
- section_text
- body
- card_text
- event_summary
- event_signup_notes

**Toolbar includes**:
- Format: H1, H2, H3, Paragraph
- Font Size: 12px, 16px, 20px, 24px
- Text Color: Grün, Dunkelgrün, Orange, Grau, Schwarz
- Formatting: Bold, Italic, Underline
- Lists: Bullet, Numbered
- Links: Insert, Unlink
- Alignment: Left, Center, Right, Block
- Source: HTML source view

**Access**: https://cms.bioco.ch/configure-ckeditor-fields/

**Manual setup required**: ProcessWire Admin → Modules → InputfieldCKEditor → Configure

**Output**: JSON with configuration guide

**Data Impact**: Field configuration only, no data changes

---

### Phase 6: update-button-labels.php

**Purpose**: Convert button variant fields to options with German labels

**What it does**:
- Converts button_variant to FieldtypeOptions
- Converts button2_variant to FieldtypeOptions
- Sets options: primary=Grün, secondary=Weiss
- Creates backup fields if needed
- Updates repeater template

**Display vs Database**:
- Admin UI: "Grün" or "Weiss"
- Database: "primary" or "secondary"
- Frontend code: Unchanged

**Access**: https://cms.bioco.ch/update-button-labels/

**Output**: JSON with conversion summary

**Data Impact**: Field type conversion, data preserved

---

### Phase 7: verify-eyebrow-field.php

**Purpose**: Verify eyebrow field is properly set up

**What it does**:
- Checks section_eyebrow field exists
- Adds to repeater if missing
- Confirms API integration (already done)
- Confirms frontend rendering (already done)
- Suggests CSS enhancements

**Field details**:
- Name: section_eyebrow
- Type: FieldtypeText
- Label: Bereichs-Etikett
- Max length: 120 characters
- Purpose: Small label above section titles

**Access**: https://cms.bioco.ch/verify-eyebrow-field/

**Output**: JSON with verification results

**Data Impact**: None (verification only)

---

### Phase 8: add-navigation-field.php

**Purpose**: Enable dynamic page navigation with visibility control

**What it does**:
- Creates include_in_nav checkbox field
- Adds to all page templates
- Enables navigation for existing pages
- Documents auto-generation process

**New field**:
- Name: include_in_nav
- Type: FieldtypeCheckbox
- Label: In Navigation anzeigen
- Default: 1 (checked)

**How it works**:
1. New pages auto-appear when include_in_nav is checked
2. Sort order from ProcessWire page tree
3. API fetches via /api/content/navigation
4. ISR revalidates every 1800s (30 min)

**Reserved static routes** (won't use dynamic nav):
- /, /wir, /gemuese, /mitmachen, /abos, /solawi
- /standorte-depots, /aktuelles, /bioco-werden, /kontakt
- /newsletter, /warteliste

**Access**: https://cms.bioco.ch/add-navigation-field/

**Output**: JSON with field setup summary

**Data Impact**: Adds control field, enables dynamic pages

---

### Phase 9a: translate-labels-german.php

**Purpose**: Translate all admin labels to German

**What it does**:
- Updates field labels (Titel, Bereichstitel, etc.)
- Updates field descriptions
- Updates layout options
- Updates theme options
- Updates template labels

**Example translations**:
```
title → Titel
section_layout → Layout
section_eyebrow → Bereichs-Etikett
section_image_overlay → Bild-Overlay
section_bg_color → Hintergrundfarbe
button_variant → Button-Stil
split_media_text → Geteiltes Layout: Bild links, Text rechts
full_width_banner → Volle Breite Banner
```

**Access**: https://cms.bioco.ch/translate-labels-german/

**Output**: JSON with translation summary (40+ fields)

**Data Impact**: Label translations only, no data changes

---

### Phase 9b: cleanup-unused-fields.php

**Purpose**: Analyze and clean up unused fields

**What it does**:
- Analyzes all templates and pages
- Builds field usage matrix
- Generates usage report
- Identifies fields with zero pages containing data
- Optionally removes unused fields from templates

**Three levels of field usage**:
1. Heavily used: 3+ templates
2. Moderately used: 2 templates
3. Rarely used: 1 template only

**Unused fields**: Fields in templates but 0 pages with data

**Access**:
- Analysis: https://cms.bioco.ch/cleanup-unused-fields/
- Execute: https://cms.bioco.ch/cleanup-unused-fields/?confirm_cleanup=1

**Output**: JSON with usage statistics and field list

**Data Impact**:
- Analysis phase: None
- Cleanup phase: Removes from templates only (data preserved)
- Expected reduction: 40-50% fewer field assignments

---

## Frontend Updates (Already Done)

All frontend changes completed alongside script development.

### Updated Files

1. **processwire-types.ts**
   - Added `imageOverlay?: 'none' | 'dark' | 'green' | 'orange'` to ContentSection
   - Added `bgColor?: 'none' | 'green' | 'darkgreen' | 'orange' | 'gray' | 'white'` to ContentSection
   - Added `sort?: number` to NavigationItem

2. **SectionRenderer.tsx**
   - Updated SplitSection to apply image overlay classes
   - Updated main render function to apply background color classes
   - Preserved eyebrow rendering

3. **globals.css**
   - Added `.image-overlay-dark::after` (rgba(0,0,0,0.4))
   - Added `.image-overlay-green::after` (rgba(76,111,68,0.3))
   - Added `.image-overlay-orange::after` (rgba(232,119,34,0.3))
   - Added `.bg-green` through `.bg-white` color classes
   - Enhanced `.cms-section-eyebrow` styling

---

## How to Access Scripts

All scripts are PHP files in `/cms/` directory. Access via URLs:

```
https://cms.bioco.ch/{script-name}/
```

Examples:
- https://cms.bioco.ch/setup-media-library/
- https://cms.bioco.ch/create-page-templates/
- https://cms.bioco.ch/add-image-styling-fields/

### Security

Each script checks:
- `$config->debug` enabled OR
- `$_GET['token']` parameter provided

For production, recommend:
- Running via ProcessWire console (Setup → Utilities)
- Or via cron job with token
- Or via SSH with WireShell

---

## Script Structure

All scripts follow same pattern:

```php
1. Security check (debug mode or token)
2. Initialize variables ($fields, $templates, $pages, etc.)
3. Step 1: Do something
4. Step 2: Do something else
5. Summary and statistics
6. Error handling
7. JSON output with log, errors, timestamp
```

### Response Format

```json
{
  "success": true/false,
  "log": ["message 1", "message 2", ...],
  "errors": ["error 1", "error 2", ...],
  "timestamp": "2026-02-03 12:34:56",
  "custom_fields": { ... }
}
```

---

## Execution Order

Run in this order (don't skip phases):

1. Phase 1: Media Library (foundational)
2. Phase 2: Page Templates (creates structure)
3. Phase 3: Image Consolidation (reorganizes fields)
4. Phase 4: Image Styling (adds new options)
5. Phase 5: CKEditor (configures editor)
6. Phase 6: Button Labels (improves UX)
7. Phase 7: Eyebrow Verify (confirms existing feature)
8. Phase 8: Navigation (enables dynamic pages)
9. Phase 9a: German Labels (user interface)
9. Phase 9b: Cleanup (optimization)

---

## Rollback

Each script is mostly reversible:

- **Media Library**: Pages can be deleted
- **Templates**: Can be removed (data in system preserved)
- **Consolidation**: Old fields remain, can restore
- **Styling**: Fields can be removed from templates
- **Labels**: Field names unchanged in database
- **Cleanup**: Only removes from templates (data safe)

**Best rollback method**: Restore from database backup

---

## Testing Each Script

After running each script:

1. Check JSON response for `"success": true`
2. Review log messages for status
3. Check ProcessWire admin for created items
4. Verify affected templates and pages
5. Test in API responses if applicable

---

## Common Issues

### "Access denied"

**Cause**: Debug mode off and no token

**Fix**:
- Enable `$config->debug = true;` in config.php OR
- Pass `?token=your_token` in URL

### "Field not found"

**Cause**: Running phases out of order

**Fix**: Run phases sequentially, don't skip

### "Template not found"

**Cause**: Dependencies not met

**Fix**: Ensure previous phases completed first

### API not returning new fields

**Cause**: API endpoint not updated

**Fix**: Check /site/templates/api.php includes field names

---

## Support Files

- **IMPLEMENTATION_GUIDE.md**: Detailed step-by-step guide
- **EXECUTION_CHECKLIST.md**: Quick checklist with timings
- **SCRIPTS_OVERVIEW.md**: This file, script reference

---

## Documentation

- Full plan: ProcessWire CMS Enhancement Plan document
- API docs: Site /site/templates/api.php
- Types: Frontend /frontend/lib/processwire-types.ts
- Components: Frontend /frontend/components/sections/

---

## Created Date

February 3, 2026

## Version

Phase 1-10 Complete, All Scripts Ready for Execution
