# ProcessWire Events/Aktuelles Setup

Complete solution for making Schnuppertage and Events editable in ProcessWire CMS.

---

## What's Included

### 1. **Migration Script** (`cms/site-templates-migrate-events.php`)
Automated setup that:
- Creates 11 event fields with correct types
- Sets up `event` template
- Assigns fields in logical order
- Creates `/events/` parent page
- Safe to run multiple times

### 2. **Documentation**
- **AKTUELLES-QUICKSTART.md** - Get started in 5 minutes
- **EVENT-FIELDS-MIGRATION.md** - Detailed setup guide with manual steps
- **AKTUELLES-DEPLOYMENT-CHECKLIST.md** - Complete 5-phase deployment plan

---

## What Gets Created

### Fields (11 total)

#### Display Fields
- `event_card_image` - Featured image for cards (Image)
- `event_card_image_alt` - Alt text for image (Text)

#### Event Details
- `title` - Event name (Text, core field)
- `event_status` - upcoming or past (Options)
- `event_start` - Start date/time (DateTime)
- `event_end` - End date/time (DateTime)
- `event_location` - Where it happens (Text)
- `event_summary` - Short description for cards (Textarea)

#### Content
- `body` - Full description with **tinyMCE editor** (Textarea with HTML)
- `event_media` - Photos and videos (File gallery)

#### Registration
- `event_signup_enabled` - Show signup form (Checkbox)
- `event_signup_notes` - Instructions for signup (Textarea with HTML)

### Template
- `event` - Template for event pages

### Pages
- `/events/` - Parent page (hidden from navigation)

---

## How to Deploy

### Step 1: Copy Migration Script to Server

```bash
scp cms/site-templates-migrate-events.php user@cms.bioco.ch:/path/to/processwire/site/templates/migrate-events.php
```

### Step 2: Create Migration Page in ProcessWire

In ProcessWire Admin:

1. **Setup → Templates → Add New**
   - Name: `migrate-events`
   - Save

2. **Pages → Add New**
   - Title: `migrate-events`
   - Template: `migrate-events`
   - Save

### Step 3: Run Migration

Visit: `https://cms.bioco.ch/migrate-events/`

✓ Should show JSON response with `"success": true`

### Step 4: Delete Migration Page (Optional)

- Admin → Pages
- Find `migrate-events` page
- Delete page (keep template for future use)

### Step 5: Create Your First Event

1. Admin → Pages → Events → Add New
2. Fill required fields:
   - Title, Status, Start, End, Location, Summary, Description
3. Upload card image
4. Enable signup (if desired)
5. Save & Publish

### Step 6: Verify on Frontend

- Frontend: `https://www.bioco.ch/aktuelles` → Should show event
- API: `https://cms.bioco.ch/api/events.php` → Should return JSON

---

## Field Mapping to Frontend

These ProcessWire fields map to the frontend event display:

| ProcessWire Field | Frontend Use | Display |
|-------------------|--------------|---------|
| title | Event name | Cards, modal |
| event_summary | Short teaser | Cards |
| body | Full description | Modal, detail |
| event_card_image | Featured image | Cards, banner |
| event_start | Event date | Cards, modal, modal header |
| event_end | End time | Triggers "past" status |
| event_location | Where it is | Modal details |
| event_media | Gallery | Modal gallery |
| event_signup_enabled | Show form | Modal, forms section |
| event_status | upcoming/past | Determines which section |

---

## Event Lifecycle

1. **Create Event** in ProcessWire
   - All fields filled, published

2. **Appears on Frontend** (after 5min cache)
   - Homepage: "Nächste Events" section
   - `/aktuelles`: Events tab
   - `/mitmachen`: Schnuppertage section

3. **Signup Form** (if enabled)
   - Users can register
   - Form data sent to email

4. **Event Happens**
   - Occurs at `event_start` time

5. **Auto Status Change** (next day)
   - ProcessWire LazyCron changes to "past"
   - Signup form hidden on frontend

6. **Archive** (optional)
   - Upload photos/videos as media
   - Event stays visible in "past" section

---

## tinyMCE Editor Setup

The `body` and `event_signup_notes` fields use **CKEditor** (ProcessWire's tinyMCE alternative).

### Verify it's working:
1. Create an event
2. Click `body` field
3. Should see rich text editor with:
   - Bold, italic, underline
   - Lists, links, formatting
   - HTML view option

### If not showing:
1. ProcessWire → Setup → Modules
2. Search "CKEditor"
3. If not installed: Click "Install"
4. Edit field → Inputfield Class: `InputfieldCKEditor`

---

## API Endpoint

### GET /api/events.php

Returns all events in JSON:

```json
{
  "success": true,
  "generatedAt": "2026-02-01T10:00:00+01:00",
  "upcoming": [
    {
      "id": 1234,
      "title": "Schnuppertag",
      "description": "Lerne biocò kennen",
      "fullDescription": "<p>Vollständiger Text...</p>",
      "location": "Geisshof",
      "startDate": "2026-04-28T14:00:00+02:00",
      "endDate": "2026-04-28T17:00:00+02:00",
      "dateLabel": "28. April 2026",
      "timeLabel": "14:00 - 17:00 Uhr",
      "signupEnabled": true,
      "signupNotes": "Treffpunkt 13:45",
      "status": "upcoming",
      "media": [
        {
          "url": "https://cms.bioco.ch/site/assets/files/1234/photo.jpg",
          "type": "image",
          "description": "Schnuppertag Foto"
        }
      ],
      "url": "https://cms.bioco.ch/events/schnuppertag-april/"
    }
  ],
  "past": [...]
}
```

---

## Frontend Integration

### Environment Variables (Vercel)

Set in Vercel dashboard → Settings → Environment Variables:

**Staging:**
```
NEXT_PUBLIC_PROCESSWIRE_API_URL=https://staging.bioco.ch/api
PROCESSWIRE_API_URL=https://staging.bioco.ch/api
```

**Production:**
```
NEXT_PUBLIC_PROCESSWIRE_API_URL=https://www.bioco.ch/api
PROCESSWIRE_API_URL=https://www.bioco.ch/api
```

### Frontend Routes Affected

- `/aktuelles` - Events tab, Schnuppertage section
- `/` (homepage) - "Nächste Events" banner
- `/mitmachen` - Schnuppertage section

---

## Troubleshooting

### Events not showing on frontend?

1. **Check ProcessWire:**
   ```
   Admin → Pages → Events → (event listed?)
   Published? (check status)
   Correct template? (should be "event")
   ```

2. **Check API endpoint:**
   ```
   https://cms.bioco.ch/api/events.php
   Status 200? JSON valid? Events in response?
   ```

3. **Check Frontend:**
   - Environment variables set in Vercel?
   - Frontend redeployed after env var change?
   - Wait 5 minutes (cache expiration)

### tinyMCE editor not showing?

1. ProcessWire → Modules → Search "CKEditor"
2. Is it installed?
3. Edit `body` field → Inputfield Class: `InputfieldCKEditor`

### Media won't upload?

1. Check file permissions: `site/assets/files/` = `755`
2. Check extensions allowed in field settings
3. Check PHP upload limits in cPanel

### Frontend gets 404 for `/api/events`?

1. Check Vercel routes configuration
2. Check `frontend/app/api/events/route.ts` exists
3. Check environment variables are set
4. Check build includes env vars

---

## File Structure

```
bioco-web-project/
├── cms/
│   └── site-templates-migrate-events.php      ← Migration script
├── docs/
│   ├── README-EVENTS-SETUP.md                 ← This file
│   ├── AKTUELLES-QUICKSTART.md                ← 5-minute guide
│   ├── EVENT-FIELDS-MIGRATION.md              ← Detailed setup
│   └── AKTUELLES-DEPLOYMENT-CHECKLIST.md      ← Deployment plan
├── frontend/
│   ├── app/api/events/route.ts                ← API proxy
│   ├── app/aktuelles/page.tsx                 ← Events page
│   └── components/
│       ├── AktuellesData.tsx                  ← Type definitions
│       ├── AktuellesItem.tsx                  ← Event card
│       └── SchnuppertageSection.tsx           ← Trial days section
└── processwire/
    └── site/
        ├── api/
        │   └── events.php                     ← CMS API endpoint
        ├── templates/
        │   └── migrate-events.php             ← Migration template
        └── classes/
            └── EventSetup.php                 ← Auto-status updates
```

---

## Quick Reference

| Task | Location |
|------|----------|
| Run migration | `https://cms.bioco.ch/migrate-events/` |
| Create event | ProcessWire Admin → Pages → Events |
| View API | `https://cms.bioco.ch/api/events.php` |
| Edit frontend | `/aktuelles` page in `frontend/` |
| Check env vars | Vercel dashboard |
| View frontend | `https://www.bioco.ch/aktuelles` |

---

## Next Steps

1. ✓ Copy migration script to server
2. ✓ Run migration script
3. ✓ Create first Schnuppertag event
4. ✓ Verify on `/aktuelles` page
5. ✓ Create remaining Schnuppertage dates
6. Check frontend event signup flow
7. Test email notifications
8. Monitor `/api/events.php` response

---

## Support

- **Setup Help:** See EVENT-FIELDS-MIGRATION.md
- **Deployment Help:** See AKTUELLES-DEPLOYMENT-CHECKLIST.md
- **Quick Reference:** See AKTUELLES-QUICKSTART.md
- **ProcessWire Docs:** https://processwire.com/docs/

---

## What's Different Now?

Before: Events were hardcoded in frontend (`AktuellesData.tsx`)
After: Events editable in ProcessWire CMS via `/aktuelles` page

Before: Schnuppertage manually updated in code
After: Schnuppertage created/edited in ProcessWire admin

Before: Static fallback for all events
After: Dynamic events from CMS, fallback for offline mode

---

**Status:** Ready to deploy ✓

