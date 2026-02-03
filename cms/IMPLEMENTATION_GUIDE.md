# ProcessWire CMS Enhancement Implementation Guide

All migration and setup scripts have been created in `/cms/`. Follow this guide to implement each phase in order.

## Prerequisites

- ProcessWire CMS instance running (https://cms.bioco.ch)
- Access to ProcessWire admin panel
- Database backup recommended before running any migrations
- Next.js frontend at `/frontend/`

## Phase 1: Central Media Library

**File**: `/cms/setup-media-library.php`

1. Create media library page at `/medien/` for central image storage
2. Configure existing image fields with tag support
3. Enable grid selection from existing images

**Access**: https://cms.bioco.ch/setup-media-library/

**Result**: Admin UI shows "Upload Files" or "Select from Library" buttons

---

## Phase 2: Create Per-Page Templates

**File**: `/cms/create-page-templates.php`

Creates 12 dedicated templates:
- home, wir, gemuese, mitmachen, abos, solawi
- standorte_depots, aktuelles_page, bioco_werden, kontakt, newsletter, warteliste

Each template includes:
- Hero section fields (headline, subtitle, image)
- Content sections repeater for flexible layout
- SEO fields
- Optional page-specific fields

**Access**: https://cms.bioco.ch/create-page-templates/

**Result**: All templates created with per-page content structure

---

## Phase 3: Consolidate Image Fields

**File**: `/cms/consolidate-image-fields.php`

Consolidates 4 image fields → 2 consolidated fields:
- `hero_image` (for hero sections only)
- `image` (for all other contexts: section, card, event)
- Single `image_alt` field for all contexts

**Access**: https://cms.bioco.ch/consolidate-image-fields/

**Result**: 50% fewer image field definitions (6 → 3)

---

## Phase 4: Add Image Styling Features

**File**: `/cms/add-image-styling-fields.php`

Adds two new fields to content sections repeater:

1. **section_image_overlay** (Options):
   - none, dark, green, orange

2. **section_bg_color** (Options):
   - none, green, darkgreen, orange, gray, white

**Access**: https://cms.bioco.ch/add-image-styling-fields/

**Frontend Updates Already Done**:
- ✅ Type definitions updated (`processwire-types.ts`)
- ✅ SectionRenderer component updated to apply classes
- ✅ CSS styles added to `globals.css`

**Result**: Overlay and background color options available in admin, rendered on frontend

---

## Phase 5: Configure CKEditor

**File**: `/cms/configure-ckeditor-fields.php`

Configures rich text editor with specific toolbar:
- Format: H1, H2, H3, Paragraph
- Font size: 12px, 16px, 20px, 24px
- Text color: Grün, Dunkelgrün, Orange, Grau, Schwarz
- Formatting: Bold, Italic, Underline
- Lists, Links, Alignment

**Access**: https://cms.bioco.ch/configure-ckeditor-fields/

**Manual Setup Required**:
In ProcessWire Admin:
1. Setup → Modules → InputfieldCKEditor → Configure
2. Create/edit profile "bioco_standard"
3. Add toolbar elements as listed above
4. Apply profile to: section_text, body, card_text, event_summary, event_signup_notes

**Result**: All textarea fields use consistent rich editor with German labels

---

## Phase 6: Update Button Labels

**File**: `/cms/update-button-labels.php`

Converts button variant fields from FieldtypeText → FieldtypeOptions:
- Admin shows: "Grün" or "Weiss"
- Database stores: "primary" or "secondary"
- Frontend code unchanged

**Access**: https://cms.bioco.ch/update-button-labels/

**Result**: Dropdown menu in admin, data integrity preserved

---

## Phase 7: Verify Eyebrow Field

**File**: `/cms/verify-eyebrow-field.php`

Verifies `section_eyebrow` field:
- Exists in repeater template
- Has German label: "Bereichs-Etikett"
- Already integrated in API and frontend

**Access**: https://cms.bioco.ch/verify-eyebrow-field/

**Result**: Eyebrow field ready for use, styling enhanced

---

## Phase 8: Enhance Navigation Auto-Generation

**File**: `/cms/add-navigation-field.php`

Adds `include_in_nav` checkbox field:
- Control which pages appear in navigation
- Respect page sort order from tree
- New pages auto-appear when checked

**Access**: https://cms.bioco.ch/add-navigation-field/

**How It Works**:
1. Create page under root (/)
2. Check "In Navigation anzeigen"
3. Set sort order via drag-drop
4. Page fetched via `/api/content/navigation`
5. Next.js ISR revalidates every 30min
6. Page appears on frontend

**Reserved Static Routes** (won't use dynamic routing):
- /, /wir, /gemuese, /mitmachen, /abos, /solawi, /standorte-depots
- /aktuelles, /bioco-werden, /kontakt, /newsletter, /warteliste

**Result**: Dynamic page creation with auto-navigation

---

## Phase 9a: Translate Labels to German

**File**: `/cms/translate-labels-german.php`

Translates all admin labels to German:
- Field labels and descriptions
- Layout options
- Theme options
- Template names

**Access**: https://cms.bioco.ch/translate-labels-german/

**Example Translations**:
- title → Titel
- section_layout → Layout
- section_eyebrow → Bereichs-Etikett
- split_media_text → Geteiltes Layout: Bild links, Text rechts
- button_variant → Button-Stil

**Result**: Fully German admin interface

---

## Phase 9b: Clean Up Unused Fields

**File**: `/cms/cleanup-unused-fields.php`

Analyzes field usage across all pages:
- Generates usage report
- Identifies fields with no data
- Removes unused fields from templates
- Preserves field definitions for data safety

**Access**: https://cms.bioco.ch/cleanup-unused-fields/

**To Execute Cleanup**:
https://cms.bioco.ch/cleanup-unused-fields/?confirm_cleanup=1

**Result**: Expected 40-50% reduction in template field assignments

---

## Phase 10: Frontend Updates

**Already Completed** ✅

### Updated Files

1. **processwire-types.ts**:
   - Added `imageOverlay` field to ContentSection
   - Added `bgColor` field to ContentSection
   - Added `sort` field to NavigationItem

2. **SectionRenderer.tsx**:
   - Added overlay class application
   - Added background color class application
   - Preserved eyebrow rendering

3. **globals.css**:
   - Added `.image-overlay-*` classes (dark, green, orange)
   - Added `.bg-*` color utilities (green, darkgreen, orange, gray, white)
   - Enhanced `.cms-section-eyebrow` styling

---

## Implementation Sequence

1. **Start**: Database backup recommended
2. **Phase 1**: Media Library setup
3. **Phase 2**: Page templates creation
4. **Phase 3**: Image field consolidation
5. **Phase 4**: Image styling fields (frontend already done)
6. **Phase 5**: CKEditor configuration (manual + script)
7. **Phase 6**: Button label conversion
8. **Phase 7**: Eyebrow field verification
9. **Phase 8**: Navigation field setup
10. **Phase 9a**: German label translation
11. **Phase 9b**: Field cleanup (optional, analysis first)
12. **Test**: Run through verification steps

---

## Testing Checklist

### Backend Testing

- [ ] Media library: Upload and select from existing both work
- [ ] Templates: Each page has dedicated template with correct fields
- [ ] Image styling: Overlay and background color options appear in admin
- [ ] CKEditor: All formatting options available in text fields
- [ ] Buttons: Dropdown shows "Grün" and "Weiss"
- [ ] Eyebrow: Field available in content sections repeater
- [ ] Navigation: New pages appear automatically with sort order
- [ ] German labels: All admin fields show German labels

### API Testing

- [ ] `/api/content/homepage` returns hero + sections
- [ ] `/api/content/sections/wir` returns page sections
- [ ] `/api/content/navigation` returns sorted nav items
- [ ] `/api/content/events` returns events
- [ ] New fields (imageOverlay, bgColor, sort) in API responses

### Frontend Testing

- [ ] SectionRenderer applies image overlays correctly
- [ ] Background colors render on sections
- [ ] Eyebrow labels display above titles
- [ ] Buttons render as green (primary) or white (secondary)
- [ ] Navigation includes all pages in correct order
- [ ] New pages created in ProcessWire appear automatically
- [ ] ISR revalidation works (60s content, 1800s navigation)
- [ ] Type safety: No TypeScript errors

### End-to-End

1. Create new page in ProcessWire with new template
2. Add content sections with all new features
3. Check "In Navigation anzeigen"
4. Wait for ISR or trigger revalidation
5. Verify page appears on frontend with correct styling

---

## API Contract Preservation

All changes are backward compatible:

- ✅ `/api/content/homepage` unchanged
- ✅ `/api/content/sections/{page}` unchanged (new fields optional)
- ✅ `/api/content/events` unchanged
- ✅ `/api/content/navigation` enhanced with sort (backward compatible)

New fields are optional in TypeScript interfaces - existing code continues to work.

---

## Rollback Strategy

Each phase is independent and reversible:

1. **Media Library**: Existing images unaffected
2. **Templates**: Can be deleted; data preserved in system
3. **Field Consolidation**: Old fields remain; can re-add to templates
4. **Styling Fields**: Remove from templates if unused
5. **Labels**: Original English field names unchanged in database
6. **Field Cleanup**: Only removes from templates; data preserved

---

## Performance Notes

- **ISR Revalidation**:
  - Content: 60s
  - Navigation: 1800s (30min)
  - Can force revalidation if needed

- **Image Optimization**:
  - Overlays use CSS (no server processing)
  - Tag support enables better image library organization
  - Next.js Image component handles optimization

---

## German Text Examples

Visible to admin users:

- Titel, Inhalt, Bereichstitel
- Bereichs-Etikett, Bereichsbild, Button-Stil
- Geteiltes Layout, Volle Breite Banner, Bild-Raster
- In Navigation anzeigen, Hintergrundfarbe, Bild-Overlay

Backend data unchanged:
- Field names remain in English (title, section_text, etc.)
- Option values unchanged (primary/secondary, etc.)
- API responses unchanged

---

## Support & Troubleshooting

**Field Not Found Errors**:
- Ensure base fields created via api-setup.php first
- Run migrations in order

**Template Not Saving**:
- Check field permissions in ProcessWire admin
- Verify all assigned fields exist

**CKEditor Not Showing**:
- Ensure InputfieldCKEditor module is installed
- Create profile and apply to fields manually

**API Not Returning New Fields**:
- Check /site/templates/api.php includes new field names
- Verify API response format matches types.ts

**ISR Not Updating**:
- Check Next.js deployment settings
- Manually trigger revalidation if needed

---

## Next Steps After Implementation

1. Create content in new page templates
2. Populate all sections with content
3. Set sort order for navigation
4. Add image overlays and backgrounds as needed
5. Configure CKEditor profiles per text field type
6. Test all pages on frontend
7. Monitor API response times
8. Adjust ISR revalidation times if needed

---

## Documentation Files

- This guide: `/cms/IMPLEMENTATION_GUIDE.md`
- Individual scripts: `/cms/*.php`
- Plan document: ProcessWire CMS Enhancement Plan (full requirements)
- Frontend types: `/frontend/lib/processwire-types.ts`
- API endpoint: `/site/templates/api.php`
