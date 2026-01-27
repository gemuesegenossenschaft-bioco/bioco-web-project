# Staging Connection Implementation Guide

**Date:** January 2026  
**Status:** In Progress

## Current Findings

### What's Working

| Component | Status | Details |
|-----------|--------|---------|
| ProcessWire Admin | Working | `staging.bioco.ch/processwire/` shows login page |
| Homepage | Working | `staging.bioco.ch/` returns HTTP 200 |
| Module Files (repo) | Present | All 4 custom modules exist in `site/modules/` |
| API Files (repo) | Present | All 6 API endpoints exist in `site/api/` |
| EventSetup Bootstrap | Configured | `site/ready.php` has correct code |

### What's NOT Working

| Component | Status | Issue |
|-----------|--------|-------|
| API Endpoints | 404 Error | `/api/events`, `/api/pages`, etc. all return 404 |

### Root Cause

The API endpoints return 404 because ProcessWire's main `.htaccess` routes ALL requests to `index.php`. The `site/api/*.php` files exist but cannot be reached directly.

## Required Actions

### Step 1: Fix API Routing (CRITICAL)

**Must be done on staging server via cPanel**

See detailed instructions in: `docs/staging-api-fix.md`

**Quick Summary:**
1. Log in to cPanel → File Manager
2. Edit `.htaccess` in ProcessWire root
3. Add API routing rules after `RewriteEngine On`:

```apache
# API ROUTING
RewriteCond %{REQUEST_URI} ^/api/events/?$
RewriteRule ^api/events/?$ site/api/events.php [L,QSA]

RewriteCond %{REQUEST_URI} ^/api/pages/?$
RewriteRule ^api/pages/?$ site/api/pages.php [L,QSA]

RewriteCond %{REQUEST_URI} ^/api/navigation/?$
RewriteRule ^api/navigation/?$ site/api/navigation.php [L,QSA]

RewriteCond %{REQUEST_URI} ^/api/instagram/?$
RewriteRule ^api/instagram/?$ site/api/instagram.php [L,QSA]

RewriteCond %{REQUEST_URI} ^/api/forms/([a-z-]+)/?$
RewriteRule ^api/forms/([a-z-]+)/?$ site/api/forms.php?form_type=$1 [L,QSA]

RewriteCond %{REQUEST_URI} ^/api/doi/?$
RewriteRule ^api/doi/?$ site/api/doi.php [L,QSA]
```

4. Save and test: `curl https://staging.bioco.ch/api/events`

### Step 2: Verify/Install Custom Modules

**Do this in ProcessWire Admin**

1. Log in to `staging.bioco.ch/processwire/`
2. Go to **Modules → Refresh**
3. Check if these modules appear:
   - DOIManager
   - FormProcessor
   - InstagramSync
   - MatomoTracker
4. If any are missing, click **Install** for each
5. Verify all show "Installed" status

### Step 3: Verify Configuration

**Check site/config.php on server (via cPanel File Manager)**

Ensure these settings:

```php
// Database
$config->dbName = 'bioco_staging';

// Site URL (CRITICAL)
$config->httpHost = 'staging.bioco.ch';

// Matomo (staging = ID 1)
$config->matomo_enabled = true;
$config->matomo_site_id = 1;

// Debug (enabled for staging)
$config->debug = true;
```

### Step 4: Test API Endpoints

After fixing .htaccess, test each endpoint:

```bash
# Events API
curl https://staging.bioco.ch/api/events
# Expected: {"success":true,"generatedAt":"...","upcoming":[],"past":[]}

# Pages API
curl "https://staging.bioco.ch/api/pages?path=/"
# Expected: {"id":1,"title":"Home",...}

# Navigation API
curl https://staging.bioco.ch/api/navigation
# Expected: {"success":true,"navigation":[...]}

# Forms API (POST)
curl -X POST https://staging.bioco.ch/api/forms/contact \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"test@example.com","message":"Test"}'
# Expected: {"success":true} or validation error
```

### Step 5: Verify Vercel Environment Variables

**Do this in Vercel Dashboard**

1. Go to Vercel Dashboard → bioco project
2. Settings → Environment Variables
3. Verify for **Preview** scope (develop branch):
   - `NEXT_PUBLIC_PROCESSWIRE_API_URL=https://staging.bioco.ch/api`
   - `PROCESSWIRE_API_URL=https://staging.bioco.ch/api`
   - `NEXT_PUBLIC_SITE_URL=https://staging.bioco.ch`
   - `NEXT_PUBLIC_MATOMO_URL=https://matomo.bioco.ch/`
   - `NEXT_PUBLIC_MATOMO_SITE_ID=1`

4. If changed, redeploy: Deployments → ... → Redeploy

### Step 6: Test Frontend Connection

1. Visit `https://staging.bioco.ch` (frontend via Vercel)
2. Open DevTools (F12) → Network tab
3. Check API calls go to `staging.bioco.ch/api/*`
4. Verify no CORS errors in Console
5. Check content loads correctly

### Step 7: Create Test Content (Optional)

**In ProcessWire Admin:**

1. Create a test event:
   - Pages → events → Add New
   - Template: event
   - Add title, dates, location
   
2. Verify event appears in `/api/events` response

## Verification Checklist

### ProcessWire Admin
- [ ] Login works at `/processwire/`
- [ ] DOIManager module installed
- [ ] FormProcessor module installed
- [ ] InstagramSync module installed
- [ ] MatomoTracker module installed
- [ ] Event fields exist (Setup → Fields)
- [ ] Event template exists (Setup → Templates)

### API Endpoints
- [ ] `/api/events` returns JSON
- [ ] `/api/pages?path=/` returns JSON
- [ ] `/api/navigation` returns JSON
- [ ] `/api/forms/contact` accepts POST

### Frontend Connection
- [ ] Vercel env vars point to staging.bioco.ch
- [ ] Frontend loads without errors
- [ ] API calls succeed (Network tab)
- [ ] No CORS errors

### Configuration
- [ ] `httpHost` is `staging.bioco.ch`
- [ ] Debug mode enabled
- [ ] Matomo site ID is 1

## Files in Repository

All required files exist in the repository and should be deployed via Git:

```
site/
├── api/
│   ├── .htaccess
│   ├── events.php
│   ├── forms.php
│   ├── pages.php
│   ├── navigation.php
│   ├── doi.php
│   └── instagram.php
├── modules/
│   ├── DOIManager/DOIManager.module.php
│   ├── FormProcessor/FormProcessor.module.php
│   ├── InstagramSync/InstagramSync.module.php
│   └── MatomoTracker/MatomoTracker.module.php
├── classes/
│   └── EventSetup.php
├── ready.php (EventSetup bootstrap)
└── config-example.php
```

## Troubleshooting

### API Still Returns 404 After .htaccess Fix

1. Clear browser cache
2. Verify .htaccess was saved correctly
3. Check for syntax errors (would cause 500 error)
4. Verify file exists: `site/api/events.php`

### 500 Error After .htaccess Change

1. Check Apache error logs in cPanel
2. Remove new rules to restore site
3. Add rules back one at a time

### Modules Not Appearing in Admin

1. Verify files deployed via Git pull in cPanel
2. Check file permissions (644 for .php files)
3. Click Modules → Refresh in admin

### CORS Errors in Frontend

Check API files have CORS headers:

```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
```

All API files in `site/api/` already include these headers.
