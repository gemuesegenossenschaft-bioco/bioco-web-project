# ProcessWire Enhancement Execution Checklist

Quick reference for running all migration scripts in order.

## Prerequisites

- [ ] Database backup created
- [ ] ProcessWire admin access available (https://cms.bioco.ch)
- [ ] Frontend code pulled and updated
- [ ] Git branch: main (or feature branch)

---

## Execution Order

### Phase 1: Central Media Library ✅ SCRIPT READY

```bash
Access: https://cms.bioco.ch/setup-media-library/
File: /cms/setup-media-library.php
```

- [ ] Run setup-media-library script
- [ ] Verify media library page created at /medien/
- [ ] Check image fields have tag support enabled
- [ ] Test: Upload new image and verify it appears

**Duration**: ~5 minutes
**Data Impact**: Additive only, no existing data modified

---

### Phase 2: Create Per-Page Templates ✅ SCRIPT READY

```bash
Access: https://cms.bioco.ch/create-page-templates/
File: /cms/create-page-templates.php
```

- [ ] Run create-page-templates script
- [ ] Verify 12 templates created
- [ ] Verify 12 pages created under root
- [ ] Check each template has correct fields
- [ ] Test: Edit a page and add content section

**Duration**: ~10 minutes
**Data Impact**: Creates new pages (old pages unaffected)

---

### Phase 3: Consolidate Image Fields ✅ SCRIPT READY

```bash
Access: https://cms.bioco.ch/consolidate-image-fields/
File: /cms/consolidate-image-fields.php
```

- [ ] Run consolidate-image-fields script
- [ ] Review field mapping in response
- [ ] Verify new 'image' field created
- [ ] Check templates updated
- [ ] Note: Old fields preserved for manual migration if needed

**Duration**: ~5 minutes
**Data Impact**: Adds new fields, preserves old fields for safety

---

### Phase 4: Add Image Styling Fields ✅ SCRIPT READY

```bash
Access: https://cms.bioco.ch/add-image-styling-fields/
File: /cms/add-image-styling-fields.php
```

- [ ] Run add-image-styling-fields script
- [ ] Verify section_image_overlay field created
- [ ] Verify section_bg_color field created
- [ ] Check both fields in repeater template
- [ ] Frontend updates: Already done ✅

**Duration**: ~3 minutes
**Data Impact**: Adds optional styling fields only

---

### Phase 5: Configure CKEditor ✅ SCRIPT READY

```bash
Access: https://cms.bioco.ch/configure-ckeditor-fields/
File: /cms/configure-ckeditor-fields.php
```

- [ ] Run configure-ckeditor-fields script
- [ ] Manual setup in ProcessWire Admin:
  - [ ] Go to Setup → Modules → InputfieldCKEditor
  - [ ] Click Configure
  - [ ] Create profile 'bioco_standard'
  - [ ] Add toolbar: Format, FontSize, Bold/Italic/Underline, Color, Lists, Links, Alignment, Source
  - [ ] Set font sizes: 12/Klein;16/Normal;20/Gross;24/Sehr Gross
  - [ ] Set text colors: Grün, Dunkelgrün, Orange, Grau, Schwarz
  - [ ] Save configuration
- [ ] Apply profile to text fields:
  - [ ] section_text
  - [ ] body
  - [ ] card_text
  - [ ] event_summary
  - [ ] event_signup_notes
- [ ] Test: Edit field, verify toolbar shows

**Duration**: ~15 minutes (mostly manual)
**Data Impact**: Field configuration only, no data changes

---

### Phase 6: Update Button Labels ✅ SCRIPT READY

```bash
Access: https://cms.bioco.ch/update-button-labels/
File: /cms/update-button-labels.php
```

- [ ] Run update-button-labels script
- [ ] Verify button_variant field converted to Options
- [ ] Verify button2_variant field converted to Options
- [ ] Test: Edit a section, check button style dropdown shows "Grün" / "Weiss"
- [ ] Verify database still stores "primary" / "secondary"

**Duration**: ~3 minutes
**Data Impact**: Field type conversion, data preserved

---

### Phase 7: Verify Eyebrow Field ✅ SCRIPT READY

```bash
Access: https://cms.bioco.ch/verify-eyebrow-field/
File: /cms/verify-eyebrow-field.php
```

- [ ] Run verify-eyebrow-field script
- [ ] Confirm section_eyebrow field exists
- [ ] Check field in repeater template
- [ ] Verify API integration (already done)
- [ ] Verify frontend rendering (already done)

**Duration**: ~2 minutes
**Data Impact**: None (verification only)

---

### Phase 8: Enhance Navigation Auto-Generation ✅ SCRIPT READY

```bash
Access: https://cms.bioco.ch/add-navigation-field/
File: /cms/add-navigation-field.php
```

- [ ] Run add-navigation-field script
- [ ] Verify include_in_nav field created
- [ ] Check field added to all page templates
- [ ] Enable navigation for existing pages
- [ ] Test: Create new page, check "In Navigation anzeigen", verify appears in nav

**Duration**: ~5 minutes
**Data Impact**: Adds control field, enables dynamic navigation

---

### Phase 9a: Translate Labels to German ✅ SCRIPT READY

```bash
Access: https://cms.bioco.ch/translate-labels-german/
File: /cms/translate-labels-german.php
```

- [ ] Run translate-labels-german script
- [ ] Verify all field labels translated
- [ ] Verify layout options translated
- [ ] Verify template labels translated
- [ ] Test: Log out and back in, check admin shows German

**Duration**: ~3 minutes
**Data Impact**: Label translations only, no data changes

---

### Phase 9b: Clean Up Unused Fields (OPTIONAL) ✅ SCRIPT READY

```bash
Access: https://cms.bioco.ch/cleanup-unused-fields/
File: /cms/cleanup-unused-fields.php
```

**First run**: Analysis only (no changes)
- [ ] Run cleanup-unused-fields script
- [ ] Review field usage report
- [ ] Identify unused fields (0 pages with data)
- [ ] Note: This is analysis only

**Second run** (if approved): Cleanup
- [ ] Run with ?confirm_cleanup=1
- [ ] Verify unused fields removed from templates
- [ ] Confirm data still preserved in system

**Duration**: ~5 minutes (analysis) + 5 minutes (cleanup if approved)
**Data Impact**: Removes from templates only (data preserved)

---

### Phase 10: Frontend Updates ✅ ALREADY DONE

Frontend code already updated:

Files modified:
- [x] `/frontend/lib/processwire-types.ts` - New fields added
- [x] `/frontend/components/sections/SectionRenderer.tsx` - Overlay & color classes
- [x] `/frontend/app/globals.css` - Styling for overlays and colors

No manual work needed. Changes deployed with frontend.

---

## Post-Implementation Testing

### Quick Smoke Test

1. **Create Test Page**:
   - [ ] In ProcessWire, create new page "test-seite"
   - [ ] Use template "wir" (or any)
   - [ ] Add hero image
   - [ ] Add content section with all new fields

2. **Test Styling Features**:
   - [ ] Set image overlay to "green"
   - [ ] Set background color to "orange"
   - [ ] Add eyebrow label "Test Bereich"
   - [ ] Save page

3. **Test API**:
   - [ ] Fetch `/api/content/sections/test-seite`
   - [ ] Verify overlay field in response
   - [ ] Verify bgColor field in response
   - [ ] Verify eyebrow field in response

4. **Test Frontend**:
   - [ ] Visit page on frontend (might need ISR wait or manual revalidation)
   - [ ] Verify image has green overlay
   - [ ] Verify section has orange background
   - [ ] Verify eyebrow label displays
   - [ ] Verify styling looks correct

5. **Test Navigation**:
   - [ ] Check "In Navigation anzeigen" on test page
   - [ ] Fetch `/api/content/navigation`
   - [ ] Verify test page appears
   - [ ] Check sort order is correct

6. **Test CKEditor**:
   - [ ] Edit section text
   - [ ] Verify toolbar shows
   - [ ] Test heading formats (H1, H2, H3)
   - [ ] Test font sizes (12px, 16px, 20px, 24px)
   - [ ] Test text colors

7. **Test Button Labels**:
   - [ ] Edit button_variant field
   - [ ] Verify dropdown shows "Grün" / "Weiss"
   - [ ] Select one, save, verify selection persists

8. **Clean Up**:
   - [ ] Delete test page
   - [ ] Verify deletion works

---

## Rollback Procedures

If something goes wrong:

### Revert Phase (Any)

1. Delete pages created by that phase (if needed)
2. Remove fields from templates
3. Fields remain in system (data preserved)
4. Can re-run script if needed

### Full Rollback

1. Restore database from backup
2. Re-run only needed phases
3. No data loss with this approach

### Partial Rollback

1. Run individual cleanup scripts
2. Remove specific fields from specific templates
3. Keep unaffected phases in place

---

## Estimated Timeline

| Phase | Duration | Difficulty |
|-------|----------|------------|
| 1. Media Library | 5 min | Easy |
| 2. Page Templates | 10 min | Medium |
| 3. Image Consolidation | 5 min | Easy |
| 4. Image Styling | 3 min | Easy |
| 5. CKEditor Config | 15 min | Medium (manual) |
| 6. Button Labels | 3 min | Easy |
| 7. Eyebrow Verify | 2 min | Easy |
| 8. Navigation Field | 5 min | Easy |
| 9a. German Labels | 3 min | Easy |
| 9b. Field Cleanup | 5-10 min | Easy |
| Testing | 20 min | Medium |
| **Total** | **~90 min** | **Moderate** |

---

## Support Commands

### Check CMS is Running

```bash
curl https://cms.bioco.ch/api/health
```

### View Script Output

Each script returns JSON with:
- `success`: boolean
- `log`: array of log messages
- `errors`: array of error messages
- `timestamp`: when script ran

### Manual Testing

ProcessWire Console (Setup → Utilities → Console):

```php
// Check field exists
$fields->get('section_image_overlay');

// Check template has field
$templates->get('home')->hasField('section_image_overlay');

// Check page has field
$pages->get('/')->section_image_overlay;
```

---

## Final Checklist

- [ ] All 9 phases completed
- [ ] All tests pass
- [ ] No TypeScript errors in frontend
- [ ] No console errors in browser
- [ ] Admin interface shows German labels
- [ ] New styling features visible in ProcessWire
- [ ] Navigation auto-generation working
- [ ] CKEditor configured with German labels
- [ ] Backup taken before and after implementation
- [ ] Team notified of changes
- [ ] Documentation updated
- [ ] Ready for deployment

---

## Questions?

Refer to:
- `/cms/IMPLEMENTATION_GUIDE.md` - Detailed guide
- Individual script files for specific phase details
- ProcessWire documentation: https://processwire.com/docs/
