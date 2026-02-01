# Event Fields Migration Guide

Setup tinyMCE editor fields for Aktuelles/Events in ProcessWire CMS.

## Quick Start

### Option 1: Automatic Migration (Recommended)

1. **Upload migration script to server:**
   ```bash
   scp cms/migrate-event-fields.php user@cms.bioco.ch:/path/to/processwire/site/templates/
   ```

2. **Create migration template in ProcessWire:**
   - Admin → Setup → Templates → Add New
   - Name: `migrate-events`
   - Save

3. **Create migration page:**
   - Admin → Pages → Add New
   - Title: `migrate-events`
   - Template: `migrate-events`
   - Save

4. **Run migration:**
   - Visit: `https://cms.bioco.ch/migrate-events/`
   - See JSON response with results

### Option 2: Manual Setup (If script fails)

Follow the manual field setup below.

---

## Manual Field Setup

### Step 1: Create Fields

Log in to ProcessWire Admin → Setup → Fields

Create these fields in order:

#### 1. event_card_image
- **Type:** Image
- **Label:** Kartenbild
- **Description:** Bild für Kartendarstellung
- **Extensions:** jpg, jpeg, png, webp
- **Max Files:** 1

#### 2. event_card_image_alt
- **Type:** Text
- **Label:** Kartenbild Alt-Text
- **Description:** Alternativtext für Barrierefreiheit

#### 3. event_status
- **Type:** Options
- **Label:** Event-Status
- **Options:**
  ```
  upcoming=Kommend
  past=Vorbei
  ```

#### 4. event_start
- **Type:** DateTime
- **Label:** Event-Startzeit
- **Description:** Wann beginnt das Event
- **Date Format:** d.m.Y
- **Time Format:** H:i

#### 5. event_end
- **Type:** DateTime
- **Label:** Event-Endzeit
- **Description:** Wann endet das Event
- **Date Format:** d.m.Y
- **Time Format:** H:i

#### 6. event_location
- **Type:** Text
- **Label:** Veranstaltungsort
- **Description:** z.B. Geisshof, Geisslistrasse, 5412 Gebenstorf

#### 7. event_summary
- **Type:** Textarea
- **Label:** Kurzbeschreibung
- **Description:** Kurze Zusammenfassung für Kartenansicht (2-3 Sätze)
- **Rows:** 3

#### 8. body (Update existing)
- **Type:** Textarea
- **Label:** Vollständige Beschreibung
- **Description:** Detaillierte Eventbeschreibung (HTML mit Editor)
- **Rows:** 10
- **Content Type:** HTML
- **Inputfield Class:** InputfieldCKEditor

#### 9. event_media
- **Type:** File
- **Label:** Event-Medien
- **Description:** Fotos und Videos vom Event
- **Extensions:** jpg, jpeg, png, webp, gif, mp4, webm
- **Max Files:** 50

#### 10. event_signup_enabled
- **Type:** Checkbox
- **Label:** Anmeldung aktivieren
- **Description:** Anmeldungsformular anzeigen
- **Checked Value:** 1
- **Unchecked Value:** 0

#### 11. event_signup_notes
- **Type:** Textarea
- **Label:** Anmeldungshinweise
- **Description:** Zusätzliche Informationen für die Anmeldung
- **Rows:** 5
- **Content Type:** HTML
- **Inputfield Class:** InputfieldCKEditor

### Step 2: Create Event Template

1. Go to Setup → Templates → Add New
2. Name: `event`
3. Label: `Event`
4. Save

### Step 3: Assign Fields to Template

1. Edit `event` template
2. In "Fields" tab, add all fields created above in this order:
   - title
   - event_card_image
   - event_card_image_alt
   - event_status
   - event_start
   - event_end
   - event_location
   - event_summary
   - body
   - event_media
   - event_signup_enabled
   - event_signup_notes

3. Save template

### Step 4: Create Events Parent Page

1. Go to Pages → Add New
2. Title: `Events`
3. Template: `basic-page` (or any template)
4. Parent: Root
5. Under Settings → Advanced:
   - Check "Hidden from Navigation"
6. Save

---

## Field Descriptions

### Card Display Fields
- **event_card_image:** Featured image shown on event cards/listings
- **event_card_image_alt:** Alt text for accessibility

### Event Details
- **title:** Event name (e.g., "Schnuppertag April")
- **event_status:** `upcoming` or `past` (auto-updated daily)
- **event_start:** When event begins
- **event_end:** When event ends (triggers auto-status change)
- **event_location:** Where event happens
- **event_summary:** 2-3 sentence teaser for cards

### Content
- **body:** Full description with tinyMCE editor (HTML formatted)
- **event_media:** Photos and videos (shown in gallery)

### Registration
- **event_signup_enabled:** Show signup form
- **event_signup_notes:** Instructions (e.g., "Bring comfortable shoes")

---

## Creating Your First Event

1. **Go to Pages → Events → Add New**

2. **Fill required fields:**
   - **Title:** "Schnuppertag April"
   - **Status:** upcoming
   - **Start:** 28.04.2026, 14:00
   - **End:** 28.04.2026, 17:00
   - **Location:** Geisshof, Geisslistrasse, 5412 Gebenstorf
   - **Card Image:** Upload JPG/PNG (500x300px recommended)
   - **Card Image Alt:** "Schnuppertag Teaser"
   - **Summary:** "Lerne biocò und den Geisshof kennen"
   - **Body:** Full description (use visual editor)

3. **Configure signup:**
   - Check "Anmeldung aktivieren"
   - Add notes (e.g., "Treffpunkt 13:45 am Hoftor")

4. **Save & Publish**

Event now appears on:
- Homepage → "Nächste Events"
- `/aktuelles` → Events tab
- `/aktuelles` → Schnuppertage section

---

## Frontend Integration

### Data Flow

```
ProcessWire Event Pages
  ↓
/api/events.php (PHP endpoint)
  ↓
frontend/app/api/events/route.ts (Next.js proxy)
  ↓
frontend/components/AktuellesData.tsx (Type definitions)
  ↓
homepage, /aktuelles (displayed)
```

### Expected JSON Response

From `/api/events`:

```json
{
  "success": true,
  "generatedAt": "2026-02-01T10:00:00+01:00",
  "upcoming": [
    {
      "id": 1234,
      "title": "Schnuppertag April",
      "description": "Lerne biocò kennen",
      "fullDescription": "<p>Komm vorbei...</p>",
      "location": "Geisshof",
      "startDate": "2026-04-28T14:00:00+02:00",
      "endDate": "2026-04-28T17:00:00+02:00",
      "dateLabel": "28. April 2026",
      "timeLabel": "14:00 - 17:00 Uhr",
      "signupEnabled": true,
      "signupNotes": "Treffpunkt 13:45",
      "status": "upcoming",
      "media": [...],
      "url": "https://www.bioco.ch/events/schnuppertag-april/"
    }
  ],
  "past": [...]
}
```

---

## Automation

### Event Status Automation

ProcessWire's LazyCron automatically:
- Changes status to "past" the day after `event_end`
- Disables signup form for past events
- Runs once per day when site is accessed after midnight

**Manual Override:**
- Edit event → change status to "past"
- Save

### Static Fallback

If API fails, frontend uses static fallback from:
`frontend/components/AktuellesData.tsx`

Update this file to add/remove test events.

---

## Troubleshooting

### Events don't appear on frontend

1. **Check API endpoint:** Visit `https://cms.bioco.ch/api/events.php`
   - Should return JSON with your events

2. **Check frontend route:** Visit `https://www.bioco.ch/api/events`
   - Should proxy to ProcessWire API

3. **Check NextJS env vars:**
   - `NEXT_PUBLIC_PROCESSWIRE_API_URL` set?
   - `PROCESSWIRE_API_URL` set?

4. **Check revalidation:** Frontend caches events for 5 minutes
   - Wait 5 minutes or rebuild

### tinyMCE not showing in body field

1. ProcessWire → Setup → Modules → Search "CKEditor"
2. If not installed: Install CKEditor module
3. Edit `body` field → set "Inputfield Class" to "InputfieldCKEditor"

### Media not uploading

1. Check file permissions on `site/assets/files/`
   - Should be `755`
2. Check file extensions allowed in `event_media` field
3. Check file size limits in PHP config

---

## Verification Checklist

- [ ] All 11 fields created
- [ ] Fields assigned to `event` template in correct order
- [ ] `event` template exists
- [ ] `/events/` parent page created (hidden)
- [ ] Can access ProcessWire admin
- [ ] Can create new event page under `/events/`
- [ ] All fields visible in event edit form
- [ ] body field has tinyMCE editor (not plain textarea)
- [ ] Can visit `/api/events.php` and see JSON response
- [ ] Frontend `/api/events` returns data
- [ ] Events display on `/aktuelles` page

---

## Migration Script Details

**File:** `cms/migrate-event-fields.php`

**What it does:**
1. Creates all 11 fields if they don't exist
2. Updates field settings (idempotent - safe to run multiple times)
3. Creates `event` template
4. Assigns fields in correct order
5. Creates `/events/` parent page

**How to run:**
```bash
# Copy to server
scp cms/migrate-event-fields.php user@cms.bioco.ch:/path/to/processwire/site/templates/

# Create template & page in ProcessWire admin
# Visit migration endpoint

# Check JSON response for success
```

**Rollback:**
- Script is read-only, doesn't delete anything
- To undo: Delete fields and template manually in ProcessWire admin

---

## API Reference

### GET /api/events.php

Returns all events (upcoming and past).

**Response Structure:**
```json
{
  "success": true,
  "generatedAt": "ISO 8601 timestamp",
  "upcoming": [EventItem...],
  "past": [EventItem...]
}
```

**EventItem Fields:**
```typescript
{
  id: number
  title: string
  description: string
  fullDescription: string
  location: string
  startDate: ISO 8601 | null
  endDate: ISO 8601 | null
  dateLabel: string
  timeLabel: string
  signupEnabled: boolean
  signupNotes: string
  status: 'upcoming' | 'past'
  media: {
    url: string
    type: 'image' | 'video'
    description: string
  }[]
  url: string
  parentTitle: string
}
```

---

## Next Steps

1. Run migration script
2. Create first test event
3. Verify on `/aktuelles` page
4. Test signup form
5. Monitor `/api/events` endpoint
6. Update static fallback if needed

Need help? Check ProcessWire docs: https://processwire.com/docs/

