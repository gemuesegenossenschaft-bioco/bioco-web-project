# Aktuelles/Events Quick Start

Get Schnuppertage and Events editable in ProcessWire CMS.

---

## In 5 Minutes

### 1. Copy Migration Script

```bash
scp cms/site-templates-migrate-events.php user@cms.bioco.ch:/path/to/processwire/site/templates/migrate-events.php
```

### 2. Create Migration Page in ProcessWire Admin

- Go to: Setup → Templates
- Click "Add New" → Name: `migrate-events` → Save
- Go to: Pages → Add New
  - Title: `migrate-events`
  - Template: `migrate-events`
  - Save

### 3. Run Migration

Visit: `https://cms.bioco.ch/migrate-events/`

You should see:
```json
{
  "success": true,
  "log": [
    "Verarbeite 11 Felder...",
    "✓ Feld gespeichert: event_card_image",
    ...
    "=== MIGRATION ABGESCHLOSSEN ==="
  ]
}
```

### 4. Create Your First Event

1. Admin → Pages → Events → Add New
2. Fill these fields:
   - **Title:** "Schnuppertag April"
   - **Status:** upcoming
   - **Start:** 28.04.2026, 14:00
   - **End:** 28.04.2026, 17:00
   - **Location:** Geisshof
   - **Summary:** "Lerne biocò kennen"
   - **Description:** Write full text (use editor)
   - **Card Image:** Upload JPG/PNG
   - **Enable Signup:** ✓
3. Save & Publish

### 5. Verify

- Frontend: `https://www.bioco.ch/aktuelles` → Should show your event
- API: `https://cms.bioco.ch/api/events.php` → Should return JSON with event

Done! 🎉

---

## Field Reference

| Field | Type | Required | Purpose |
|-------|------|----------|---------|
| **title** | Text | ✓ | Event name |
| **event_card_image** | Image | - | Featured image |
| **event_card_image_alt** | Text | - | Image alt text |
| **event_status** | Options | ✓ | upcoming/past |
| **event_start** | DateTime | ✓ | Start time |
| **event_end** | DateTime | ✓ | End time |
| **event_location** | Text | ✓ | Where it is |
| **event_summary** | Textarea | ✓ | Short description |
| **body** | Textarea | ✓ | Full description (tinyMCE) |
| **event_media** | File | - | Photos/videos |
| **event_signup_enabled** | Checkbox | - | Show signup form |
| **event_signup_notes** | Textarea | - | Signup instructions |

---

## Files Provided

1. **cms/site-templates-migrate-events.php**
   - Migration script, rename to `migrate-events.php` in `site/templates/`

2. **docs/EVENT-FIELDS-MIGRATION.md**
   - Detailed setup guide with manual steps

3. **docs/AKTUELLES-DEPLOYMENT-CHECKLIST.md**
   - Full deployment checklist (5 phases, 50+ checks)

4. **docs/AKTUELLES-QUICKSTART.md**
   - This file (quick reference)

---

## Common Issues

### "Access denied" when running migration
- Debug mode must be ON, or add token:
- `https://cms.bioco.ch/migrate-events/?token=YOUR_TOKEN`

### tinyMCE editor not showing
- ProcessWire → Setup → Modules → Install "CKEditor"
- Edit `body` field → set Inputfield Class to "InputfieldCKEditor"

### Events don't appear on frontend
- Check API: `https://cms.bioco.ch/api/events.php`
- Check env vars in Vercel (PROCESSWIRE_API_URL)
- Wait 5 minutes for cache to expire

### Media uploads fail
- Check permissions: `site/assets/files/` should be `755`
- Check allowed extensions in field settings

---

## Next Steps

1. ✓ Run migration script
2. ✓ Create first event
3. Check it displays on `/aktuelles`
4. Delete migration page (optional)
5. Create remaining Schnuppertage dates
6. Update static fallback if needed: `frontend/components/AktuellesData.tsx`
7. Deploy frontend with updated env vars
8. Monitor `/api/events.php` response

---

## Need Help?

See detailed docs:
- **Setup:** EVENT-FIELDS-MIGRATION.md
- **Deployment:** AKTUELLES-DEPLOYMENT-CHECKLIST.md
- **Troubleshooting:** Bottom of EVENT-FIELDS-MIGRATION.md

