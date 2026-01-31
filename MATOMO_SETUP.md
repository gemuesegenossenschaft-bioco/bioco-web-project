# Matomo Analytics Setup for bioco.ch

**Status:** ✅ Installed and configured
**Instance:** https://matomo.bioco.ch/
**Site ID:** 1
**Compliance:** Cookieless tracking, Swiss DSG compliant

## Overview

Matomo is configured for privacy-first, cookieless analytics across both ProcessWire CMS and Next.js frontend. No consent banner required.

## Architecture

**Matomo Instance:**
- URL: https://matomo.bioco.ch/
- Hosted: Novatrend cPanel subdomain
- Database: Separate matomo database
- Version: Matomo 5.7.0

**Tracking Methods:**
1. **Client-side:** Next.js frontend via MatomoScript component
2. **Server-side:** ProcessWire forms via MatomoTracker module

**Privacy Features:**
- IP anonymization (2 bytes)
- Cookieless tracking (no consent required)
- No personal data storage
- Swiss DSG compliant

## Installation Steps

### 1. Matomo Instance Setup

**Download & Upload:**
1. Download latest Matomo: https://matomo.org/download/
2. Upload to cPanel subdomain: `matomo.bioco.ch`
3. Extract files to document root
4. Visit installation wizard in browser

**Database Configuration:**
- Host: `localhost`
- Database: `matomo` (pre-existing)
- User: Database user with full privileges
- Tables: Auto-created during install

**Initial Site Setup:**
- Website name: `biocò`
- URL: `https://staging.bioco.ch` (or production URL)
- Timezone: `Europe/Zurich`
- Site ID: `1` (auto-assigned)

### 2. Privacy Settings (DSG Compliance)

**In Matomo Admin → Privacy → Anonymize Data:**
- ✅ Enable "Anonymize Visitor IP addresses" (2 bytes)
- ✅ Enable "Force tracking without cookies"
- Set anonymization level: At least 2 bytes

**In Matomo Admin → Privacy → Users opt-out:**
- Select "No consent required" (cookieless mode)
- Save settings

**Result:** No cookie banner needed, full DSG compliance

### 3. ProcessWire Backend Configuration

**File: `/site/config.php`**

Add Matomo configuration (already included):

```php
// Matomo Analytics (Cookieless, Swiss DSG compliant)
$config->matomo_enabled = true;
$config->matomo_url = 'https://matomo.bioco.ch/'; // Trailing slash required!
$config->matomo_site_id = 1;
```

**Upload MatomoTracker Module:**

1. Ensure module files exist in `/site/modules/MatomoTracker/`
2. Upload to server via cPanel or deployment
3. ProcessWire Admin → Modules → Refresh
4. Find and Install "MatomoTracker"
5. Module auto-loads (configured as autoload)

**Module Features:**
- Server-side event tracking
- Form submission tracking
- Cookieless mode enabled
- Session-based event queue

### 4. Next.js Frontend Configuration

**File: `/frontend/.env.local` (local development)**

```env
NEXT_PUBLIC_PROCESSWIRE_BASE_URL=https://staging.bioco.ch

# Matomo Analytics
NEXT_PUBLIC_MATOMO_URL=https://matomo.bioco.ch/
NEXT_PUBLIC_MATOMO_SITE_ID=1
```

**Vercel Production Environment Variables:**

Add in Vercel Project Settings → Environment Variables:
- `NEXT_PUBLIC_MATOMO_URL` = `https://matomo.bioco.ch/`
- `NEXT_PUBLIC_MATOMO_SITE_ID` = `1`

**Component Integration:**

Already configured in `/frontend/app/layout.tsx`:

```tsx
import { MatomoScript } from '@/components/MatomoScript'

export default function RootLayout({ children }) {
  return (
    <html lang="de">
      <body>
        {children}
        <MatomoScript />
      </body>
    </html>
  )
}
```

**MatomoScript Component** (`/frontend/components/MatomoScript.tsx`):
- Loads Matomo tracking script
- Cookieless mode enabled (`disableCookies`)
- Environment variable based configuration
- Helper functions: `trackEvent()`, `trackCTA()`

## Deployment Checklist

### Production Deployment

- [ ] Upload `config.php` to production server (`/home/bioco/cms/site/config.php`)
- [ ] Upload MatomoTracker module to production
- [ ] Install MatomoTracker in ProcessWire admin
- [ ] Add Matomo env vars to Vercel (Production environment)
- [ ] Deploy frontend to Vercel
- [ ] Test tracking on production site

### Staging Deployment

- [ ] Upload `config.php` to staging server
- [ ] Upload MatomoTracker module
- [ ] Install module
- [ ] Test tracking on staging

## Testing & Verification

### 1. Verify Matomo Installation

Visit: https://matomo.bioco.ch/
- Login to admin
- Check Dashboard loads
- Verify site configured (Site ID: 1)

### 2. Test ProcessWire Tracking

**Form Submission Test:**
1. Submit any form on cms.bioco.ch
2. Login to Matomo
3. Go to: Behaviour → Events
4. Should see form submission event

**Events Tracked:**
- Form submissions (contact, newsletter, waiting list)
- DOI confirmations
- Event signups

### 3. Test Next.js Tracking

**Page View Test:**
1. Visit frontend (staging or production)
2. Open browser DevTools → Console
3. Run: `window._paq`
4. Should see array of tracking commands

**Network Test:**
1. DevTools → Network tab
2. Filter: `matomo`
3. Should see requests to:
   - `matomo.js` (script load)
   - `matomo.php` (tracking beacon)

**Real-time Verification:**
1. Visit pages on frontend
2. Login to Matomo
3. Go to: Visitors → Real-time
4. Should see active visits within 5-10 minutes

### 4. Verify Cookieless Mode

**Browser Check:**
1. DevTools → Application → Cookies
2. Filter domain: `bioco.ch`
3. Should see **NO** Matomo cookies (no `_pk_*` cookies)

**Matomo Settings Check:**
1. Matomo Admin → Settings → Websites
2. View tracking code
3. Should include `disableCookies` command

### 5. Test Event Tracking

**CTA Click Test:**
1. Click any CTA button on frontend
2. Matomo → Behaviour → Events
3. Should see: Category "CTA", Action "Click"

**Custom Event Test:**
```typescript
import { trackEvent } from '@/components/MatomoScript'

// Track custom event
trackEvent('Newsletter', 'Subscribe', 'Homepage CTA')
```

## Tracked Metrics

### Page Views
- All frontend pages (Next.js)
- Automatic via MatomoScript component
- No manual tracking needed

### Events

**CTA Clicks:**
- Category: `CTA`
- Action: `Click`
- Name: Button label/identifier

**Form Submissions:**
- Category: `Form`
- Action: `Submit`
- Name: Form type (contact, subscribe, etc.)

**DOI Confirmations:**
- Category: `DOI`
- Action: `Confirm`
- Name: Email address (hashed)

**Event Signups:**
- Category: `Event`
- Action: `Signup`
- Name: Event title

### User Behavior
- Session duration
- Pages per session
- Bounce rate
- Exit pages
- Entry pages

### Acquisition
- Referrer URLs
- Search keywords (if available)
- Campaign tracking (UTM parameters)

## Privacy & Compliance

### Data Anonymization

**IP Addresses:**
- Last 2 bytes anonymized
- Example: `192.168.xxx.xxx`
- Configured in Matomo privacy settings

**No Personal Data:**
- No cookies stored
- No user IDs tracked
- No email addresses in raw logs

**Cookieless Tracking:**
- Uses `config_id` randomization
- No consent required under Swiss DSG
- Compliant with privacy regulations

### Data Retention

**Configure in Matomo:**
1. Admin → Privacy → Anonymize Data
2. Set "Delete old visitor logs"
3. Recommended: 180 days (6 months)
4. Set "Delete old reports": Optional

### User Rights (DSG)

**Opt-out Option:**
- Available at Matomo Admin → Privacy → Users opt-out
- Can embed opt-out form on privacy page
- Users can disable tracking completely

**Data Deletion:**
- Admin can delete visitor data
- Tools → GDPR Tools
- Delete by IP, User ID, or date range

## Troubleshooting

### Matomo Not Loading

**Issue:** White screen or 500 error on matomo.bioco.ch

**Solutions:**
1. Check PHP version (requires 8.0+)
2. Verify all files extracted correctly
3. Check `tmp/` and `config/` folder permissions (755 or 777)
4. Check server error logs in cPanel

### No Tracking Data

**Issue:** No page views or events in Matomo

**Check:**
1. Environment variables configured correctly
2. `window._paq` exists in browser console
3. Network requests to `matomo.php` succeed (no CORS errors)
4. Site ID matches between config and Matomo
5. Matomo URL has trailing slash

### ProcessWire Events Not Tracking

**Issue:** Form submissions not appearing in Matomo

**Check:**
1. MatomoTracker module installed and enabled
2. `config.php` has correct Matomo settings
3. ProcessWire session working (events stored in session)
4. Check ProcessWire error logs

### CORS Errors

**Issue:** Browser blocks Matomo requests

**Solution:**
1. Ensure Matomo URL has trailing slash
2. Check Matomo subdomain SSL certificate valid
3. Verify same domain (bioco.ch → matomo.bioco.ch)

### Cookies Still Being Set

**Issue:** Matomo cookies found in browser

**Check:**
1. Matomo privacy settings: "Force tracking without cookies" enabled
2. MatomoScript.tsx has `disableCookies` call
3. Clear browser cache and cookies
4. Test in incognito/private window

## Maintenance

### Regular Tasks

**Weekly:**
- Check Matomo dashboard for anomalies
- Review tracking errors (if any)

**Monthly:**
- Review privacy compliance
- Check data retention settings
- Update Matomo if new version available

**Quarterly:**
- Audit tracked events
- Review and optimize tracking
- Test tracking after deployments

### Updates

**Matomo Updates:**
1. Backup Matomo database
2. Login to Matomo admin
3. Follow update prompts
4. Test tracking after update

**Module Updates:**
- Check for ProcessWire module updates
- Review changelog
- Update via ProcessWire admin

## Support & Resources

**Matomo Official Docs:**
- https://matomo.org/docs/
- https://developer.matomo.org/guides/tracking-javascript-guide

**ProcessWire Module:**
- Location: `/site/modules/MatomoTracker/`
- Auto-loads on every request
- Server-side tracking for forms

**Next.js Component:**
- Location: `/frontend/components/MatomoScript.tsx`
- Client-side page view tracking
- Helper functions for events

**Configuration Files:**
- ProcessWire: `/site/config.php`
- Next.js: `/frontend/.env.local` (local)
- Vercel: Environment Variables (production)

## Security Considerations

**Matomo Admin Access:**
- Strong password required
- Limited user accounts
- Regular password rotation

**Database Security:**
- Separate matomo database
- Limited privileges
- No direct public access

**Tracking Data:**
- IP anonymization enabled
- No personal identifiers stored
- Regular data cleanup

**Config File Security:**
- `config.php` excluded from git
- File permissions: 644
- Sensitive credentials stored securely

## Summary

**Matomo Status:** ✅ Fully configured and operational

**Key Features:**
- Cookieless tracking (no consent banner)
- Swiss DSG compliant
- Client + server-side tracking
- Privacy-first analytics

**Access:**
- Matomo Admin: https://matomo.bioco.ch/
- Site ID: 1
- Tracking: Automatic on all pages

**Next Steps:**
- Monitor analytics in Matomo dashboard
- Review and optimize tracking events
- Maintain privacy compliance
