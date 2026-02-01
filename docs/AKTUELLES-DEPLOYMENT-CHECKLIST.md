# Aktuelles/Events Deployment Checklist

Complete checklist to enable Aktuelles/Events from ProcessWire CMS.

---

## Phase 1: ProcessWire Backend Setup

### A. Run Event Fields Migration

- [ ] SSH into server
  ```bash
  ssh user@cms.bioco.ch
  cd public_html/bioco_staging  # or bioco_live for production
  ```

- [ ] Copy migration script:
  ```bash
  cp /path/to/site-templates-migrate-events.php site/templates/migrate-events.php
  ```

- [ ] In ProcessWire Admin:
  - [ ] Setup → Templates → Add New
    - Name: `migrate-events`
    - Leave fields empty (template file will handle it)
    - Save

  - [ ] Pages → Add New
    - Title: `migrate-events`
    - Template: `migrate-events`
    - Parent: Root
    - Save & Publish

- [ ] Run migration:
  - [ ] Visit: `https://cms.bioco.ch/migrate-events/`
  - [ ] Check for `"success": true` in JSON response
  - [ ] Review log for any warnings

- [ ] Delete migration page:
  - [ ] Admin → Pages → migrate-events
  - [ ] Delete page
  - [ ] (Keep template, may be useful later)

### B. Verify Fields & Template

- [ ] Admin → Setup → Fields
  - [ ] Verify all 11 fields exist:
    - [ ] event_card_image
    - [ ] event_card_image_alt
    - [ ] event_status
    - [ ] event_start
    - [ ] event_end
    - [ ] event_location
    - [ ] event_summary
    - [ ] body (with tinyMCE)
    - [ ] event_media
    - [ ] event_signup_enabled
    - [ ] event_signup_notes

- [ ] Admin → Setup → Templates → event
  - [ ] All 12 fields assigned in correct order (title first, event_signup_notes last)
  - [ ] body field has "InputfieldCKEditor" as Inputfield Class
  - [ ] Verify field positions/sort order

### C. Create Events Parent Page

- [ ] Admin → Pages → Home
  - [ ] Add New
  - [ ] Title: `Events`
  - [ ] Template: `basic-page` (or `home`)
  - [ ] Under "Advanced Settings":
    - [ ] Check "Hidden from navigation"
  - [ ] Save & Publish
  - [ ] Verify page name is `events` (slugified)
  - [ ] Note the page ID from URL (e.g., `/events/` = ID 1234)

---

## Phase 2: Create Sample Events

### Create First Event: Schnuppertag April

- [ ] Admin → Pages → Events → Add New
  - [ ] Title: `Schnuppertag April`
  - [ ] Status: Published
  - [ ] Fill fields:
    - [ ] **event_card_image:** Upload image (500×300px recommended)
    - [ ] **event_card_image_alt:** "Schnuppertag im April"
    - [ ] **event_status:** `upcoming`
    - [ ] **event_start:** 28.04.2026, 14:00
    - [ ] **event_end:** 28.04.2026, 17:00
    - [ ] **event_location:** Geisshof, Geisslistrasse, 5412 Gebenstorf
    - [ ] **event_summary:** "Lerne biocò und den Geisshof kennen"
    - [ ] **body:** Use editor to write full description (HTML formatted)
    - [ ] **event_media:** (optional) Upload photos/videos
    - [ ] **event_signup_enabled:** Check ✓
    - [ ] **event_signup_notes:** "Treffpunkt 13:45 am Hoftor"
  - [ ] Save & Publish

- [ ] Create remaining Schnuppertage (May, June, July, August, September, October)
  - [ ] Clone first event or create manually
  - [ ] Update dates and titles

### Verify API Response

- [ ] Visit: `https://cms.bioco.ch/api/events.php`
  - [ ] Check for `"success": true`
  - [ ] Verify events appear in `"upcoming"` array
  - [ ] Check field mappings:
    - [ ] `event_title` → title
    - [ ] `event_location` → location
    - [ ] `event_start` → startDate (ISO format)
    - [ ] `event_end` → endDate (ISO format)
    - [ ] `event_summary` → description
    - [ ] `body` → fullDescription
    - [ ] `event_signup_enabled` → signupEnabled
    - [ ] `event_signup_notes` → signupNotes
    - [ ] `event_media` → media (with url, type, description)

---

## Phase 3: Frontend Integration

### A. Verify Environment Variables

Check Vercel dashboard → Settings → Environment Variables

#### Staging (`staging.bioco.ch`)

- [ ] `NEXT_PUBLIC_PROCESSWIRE_API_URL`
  - [ ] Value: `https://staging.bioco.ch/api`
  - [ ] Scope: Preview (develop)

- [ ] `PROCESSWIRE_API_URL`
  - [ ] Value: `https://staging.bioco.ch/api`
  - [ ] Scope: Preview (develop)

#### Production (`www.bioco.ch`)

- [ ] `NEXT_PUBLIC_PROCESSWIRE_API_URL`
  - [ ] Value: `https://www.bioco.ch/api`
  - [ ] Scope: Production (main)

- [ ] `PROCESSWIRE_API_URL`
  - [ ] Value: `https://www.bioco.ch/api`
  - [ ] Scope: Production (main)

### B. Verify Frontend API Route

Check: `frontend/app/api/events/route.ts`

- [ ] Should call `cmsApiUrl('/events.php')`
- [ ] Should return JSON with `upcoming` and `past` arrays
- [ ] Should gracefully fallback if API unreachable

Code should look like:
```typescript
const response = await fetch(
  cmsApiUrl('/events.php'),
  {
    ...cmsFetchOptions(revalidate),
    headers: buildCmsHeaders(),
  }
)
```

### C. Deploy Frontend

- [ ] Ensure all changes are committed to git
  ```bash
  cd frontend
  git status  # Should be clean
  ```

- [ ] Push to develop/main branch
  ```bash
  git push origin develop  # or main for production
  ```

- [ ] Monitor Vercel deployment
  - [ ] Vercel dashboard → Deployments
  - [ ] Wait for build to complete
  - [ ] Check for build errors in logs

- [ ] Clear any local caches (if applicable)

---

## Phase 4: Testing

### Test on Staging (`staging.bioco.ch`)

#### Aktuelles Page (`/aktuelles`)
- [ ] Page loads without 404
- [ ] "Aktuelles" tab shows any non-event items
- [ ] "Events" tab shows upcoming events
- [ ] Event cards display:
  - [ ] Title
  - [ ] Date
  - [ ] Description
  - [ ] Card image (if provided)
  - [ ] "Mehr erfahren" link

#### Event Modal/Detail
- [ ] Click event card → modal opens
- [ ] Modal shows:
  - [ ] Full title and date
  - [ ] Full description (HTML rendered)
  - [ ] Location
  - [ ] Time
  - [ ] Media gallery (if photos/videos uploaded)
  - [ ] Signup form (if enabled)

#### Signup Form
- [ ] Form appears only for upcoming events
- [ ] Form fields load correctly
- [ ] Can submit form without errors
- [ ] Confirmation message displays

#### Homepage
- [ ] "Nächste Events" section shows events
- [ ] Up to 3 latest upcoming events displayed
- [ ] Cards show title, date, description
- [ ] Can click to open detail modal

#### Schnuppertage Section (`/mitmachen`)
- [ ] Section displays upcoming Schnuppertage
- [ ] Cards show title and date
- [ ] "Jetzt anmelden" button works
- [ ] Modal opens with event details

### Browser Console
- [ ] No JavaScript errors
- [ ] No 404 errors for API calls
- [ ] No CORS errors

### API Responses
- [ ] `https://staging.bioco.ch/api/events`
  - [ ] Returns 200 status
  - [ ] JSON structure correct
  - [ ] Data matches ProcessWire

---

## Phase 5: Production Deployment

### Before Deploying to Production

- [ ] All staging tests pass ✓
- [ ] Team approval obtained
- [ ] Backup created:
  ```bash
  # Backup ProcessWire database
  mysqldump -u user -p bioco_live > backup_$(date +%Y%m%d).sql

  # Backup site files
  tar -czf site-backup-$(date +%Y%m%d).tar.gz site/
  ```

### Deploy ProcessWire to Production

- [ ] SSH to production server
- [ ] Copy event fields migration to production
- [ ] Run migration on production
- [ ] Create events on production
- [ ] Verify `/api/events.php` works

### Deploy Frontend to Production

- [ ] Merge `develop` → `main` branch (via GitHub PR)
- [ ] Vercel auto-deploys to `www.bioco.ch`
- [ ] Wait for build completion
- [ ] Check build logs for errors

### Post-Production Verification

- [ ] Test on `www.bioco.ch`
  - [ ] All aktuelles/event features work
  - [ ] No console errors
  - [ ] API endpoints respond
  - [ ] Signup forms functional

- [ ] Monitor error logs
  - [ ] ProcessWire: Admin → Setup → Logs
  - [ ] Vercel: Dashboard → Logs
  - [ ] cPanel: Error Logs
  - [ ] Check for 24+ hours

- [ ] Verify Matomo tracking
  - [ ] Matomo dashboard showing traffic
  - [ ] Form submissions tracked
  - [ ] Event signups tracked

---

## Phase 6: Ongoing Maintenance

### Weekly
- [ ] Check for failed form submissions
  - [ ] Admin → Setup → Logs → forms
- [ ] Review event signup list
- [ ] Monitor error logs

### Monthly
- [ ] Verify event status automation
  - [ ] Old events marked as "past"
  - [ ] Signup disabled for past events
- [ ] Check media galleries loading
- [ ] Review API response times

### Quarterly
- [ ] Update event dates for next quarter
- [ ] Archive old events
- [ ] Review event performance metrics
- [ ] Update documentation if needed

---

## Troubleshooting

### Events Not Showing on Frontend

**Check ProcessWire:**
1. Admin → Pages → Events
   - [ ] Events exist?
   - [ ] Published?
   - [ ] Correct template assigned?

2. Visit API endpoint
   - [ ] `https://cms.bioco.ch/api/events.php`
   - [ ] Returns 200?
   - [ ] JSON valid?
   - [ ] Events in response?

**Check Frontend:**
1. Vercel environment variables set?
2. Frontend build includes env vars?
3. Cache cleared after deployment?
   - [ ] Wait 5 minutes (revalidate cache)
   - [ ] Or rebuild with `npm run build`

### tinyMCE Editor Not Showing

1. ProcessWire Admin → Setup → Modules
   - [ ] Search "CKEditor"
   - [ ] Installed?
   - [ ] If not: Install it

2. Edit `body` field
   - [ ] Inputfield Class: `InputfieldCKEditor`
   - [ ] Content Type: `html`

### Media Not Uploading

1. Check server permissions
   - [ ] `site/assets/files/` → `755`
   - [ ] `site/assets/` → `755`

2. Check allowed extensions
   - [ ] `event_media` field has correct extensions
   - [ ] Allowed: jpg, jpeg, png, webp, gif, mp4, webm

3. Check PHP limits
   - [ ] `upload_max_filesize` in php.ini
   - [ ] `post_max_size` in php.ini

### Signup Form Not Appearing

1. Check event status
   - [ ] Edit event → `event_signup_enabled` checked?

2. Check frontend code
   - [ ] EventSignupForm component rendering?
   - [ ] No console errors?

---

## Rollback Plan

If something goes wrong:

### Frontend Rollback
```bash
# In GitHub, revert the commit
git revert <commit-hash>
git push origin main
# Vercel auto-deploys old version
```

### ProcessWire Rollback
```bash
# Restore database backup
mysql -u user -p bioco_live < backup_20260201.sql
# or via phpMyAdmin import
```

---

## Deployment Complete!

- [ ] All checklist items completed
- [ ] Team notified
- [ ] Documentation updated
- [ ] Backups verified

Events are now live on bioco.ch 🎉

