# ProcessWire Staging Rebuild - Implementation Guide

**Date:** January 2026  
**Environment:** staging.bioco.ch  
**Purpose:** Complete rebuild of ProcessWire CMS with proper component connections

## Quick Start Checklist

Use this checklist to track progress through each phase:

- [ ] **Phase 1: Backup Everything**
- [ ] **Phase 2: Complete Wipe**
- [ ] **Phase 3: Fresh Installation**
- [ ] **Phase 4: Restore Custom Code**
- [ ] **Phase 5: Configure ProcessWire**
- [ ] **Phase 6: Restore Content**
- [ ] **Phase 7: Configure .htaccess**
- [ ] **Phase 8: Test API Endpoints**
- [ ] **Phase 9: Verify Frontend Connection**
- [ ] **Phase 10: Final Verification**
- [ ] **Phase 11: Documentation Update**

---

## Phase 1: Backup Everything

### Step 1.1: Database Backup

**Location:** cPanel → phpMyAdmin

1. Log in to cPanel
2. Navigate to **phpMyAdmin**
3. Select database: `bioco_staging` (left sidebar)
4. Click **Export** tab
5. Select **Custom** method
6. Configure export:
   - **Format:** SQL
   - **Structure:**
     - ✓ Add DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT / TRIGGER statement
     - ✓ Add IF NOT EXISTS
     - ✓ Add AUTO_INCREMENT value
   - **Data:**
     - ✓ Add INSERT statement
     - ✓ Add columns names
   - **Object creation options:**
     - ✓ Add CREATE TABLE
     - ✓ Add CREATE PROCEDURE / FUNCTION / EVENT / TRIGGER
7. Click **Go**
8. Save file: `bioco_staging_backup_YYYY-MM-DD_HH-MM.sql`
9. **Verify backup:**
   - Check file size (should be > 0 KB)
   - Open SQL file, verify it contains `CREATE TABLE` statements
   - Verify it contains `INSERT INTO` statements with data

**Backup Location:** Store on local machine and cloud storage

### Step 1.2: File System Backup

**Location:** cPanel → File Manager

1. Log in to cPanel
2. Navigate to **File Manager**
3. Identify ProcessWire installation directory:
   - Check for `public_html/bioco_staging/` or
   - Check for `public_html/staging/` or
   - Check for `public_html/` (if staging is subdomain root)

4. **Backup these directories:**
   - `site/assets/files/` - All uploaded media
   - `site/assets/images/` - Image variations
   - `site/config.php` - Current configuration
   - `site/modules/` - Custom modules
   - `site/api/` - API endpoints
   - `site/classes/` - Custom classes
   - `site/templates/` - Template files

**Method: Compress and Download**

1. Navigate to ProcessWire root directory
2. Select `site/` directory
3. Right-click → **Compress**
4. Choose **Zip Archive**
5. Name: `bioco_staging_files_backup_YYYY-MM-DD.zip`
6. Click **Compress File(s)**
7. Wait for compression to complete
8. Right-click ZIP file → **Download**
9. Save to local machine

**Alternative: FTP/SFTP Backup**

If File Manager is slow, use FTP client:

1. Connect via FTP/SFTP to server
2. Navigate to ProcessWire installation
3. Download entire `site/` directory
4. Preserve directory structure

**Verify Backup:**
- Extract ZIP file locally
- Verify `site/assets/files/` contains media files
- Verify `site/config.php` exists
- Verify `site/modules/` contains custom modules

### Step 1.3: Content Export (Optional)

**Location:** ProcessWire Admin

1. Access: `https://staging.bioco.ch/processwire/`
2. Log in with admin credentials
3. Navigate to **Tools** → **Export/Import** (if available)
4. Export all pages
5. Save export file

**Note:** Database backup includes all content, but ProcessWire export can be useful for reference.

---

## Phase 2: Complete Wipe of Staging

### Step 2.1: Identify Installation Directory

**Location:** cPanel → File Manager

1. Navigate to `public_html/`
2. Look for ProcessWire installation:
   - Check for `wire/` directory
   - Check for `site/` directory
   - Check for `index.php` (ProcessWire entry point)

**Common Locations:**
- `public_html/bioco_staging/`
- `public_html/staging/`
- `public_html/` (if staging is subdomain root)

**Document the path:** _________________________

### Step 2.2: Backup Current Installation (Safety)

**Before deleting, create a complete backup:**

1. Navigate to parent directory (e.g., `public_html/`)
2. Select entire ProcessWire directory (e.g., `bioco_staging/`)
3. Right-click → **Rename**
4. Rename to: `bioco_staging_backup_YYYY-MM-DD`
5. This preserves everything as backup

### Step 2.3: Delete ProcessWire Core Files

**⚠️ WARNING: Ensure backups are complete before proceeding**

**Files/Directories to DELETE:**

1. **ProcessWire Core:**
   - `wire/` directory (entire directory)
   - `index.php`
   - `install.php` (if exists)
   - `.htaccess` (backup content first if custom rules exist)

**Files/Directories to KEEP:**
- `site/assets/files/` - User uploads (we'll restore from backup)
- `site/modules/` - Custom modules (will be restored from Git)
- `site/api/` - API endpoints (will be restored from Git)
- `site/classes/` - Custom classes (will be restored from Git)
- `site/templates/` - Template files (will be restored from Git)

**If you created backup directory in Step 2.2, skip this step** - we'll restore from Git instead.

### Step 2.4: Database Wipe Decision

**Option A: Keep Database (Recommended for First Attempt)**

- Leave database as-is
- ProcessWire installer will detect existing tables
- Safer approach

**Option B: Wipe Database (Clean Slate)**

**Location:** cPanel → phpMyAdmin

1. Select database: `bioco_staging`
2. Click **Structure** tab
3. Check "Select All" checkbox
4. From dropdown: **With selected:** → **Drop**
5. Confirm deletion
6. Database is now empty

**⚠️ Only do this if you have database backup from Phase 1.1**

---

## Phase 3: Fresh ProcessWire Installation

### Step 3.1: Download ProcessWire

1. Visit: https://processwire.com/download/
2. Download latest ProcessWire 3.x (ZIP file)
3. Save to local machine
4. Extract ZIP file
5. Note the extracted directory structure

### Step 3.2: Upload Fresh ProcessWire

**Method 1: Git (Recommended)**

If using cPanel Git Version Control:

1. Ensure `develop` branch has ProcessWire core files
2. cPanel → **Git Version Control**
3. Select repository for staging
4. Click **Pull or Deploy**
5. Select `develop` branch
6. Click **Deploy**
7. This ensures code matches repository

**Method 2: cPanel File Manager**

1. Navigate to staging directory (e.g., `public_html/bioco_staging/`)
2. Upload ProcessWire files:
   - Upload `wire/` directory (upload entire directory)
   - Upload `index.php`
   - Upload `install.php`
   - Upload `.htaccess` (from ProcessWire package)

**Upload Instructions:**
- Select files in File Manager
- Click **Upload**
- Wait for upload to complete
- Verify files are in correct location

**Method 3: FTP/SFTP**

1. Connect via FTP client
2. Navigate to staging directory
3. Upload entire ProcessWire directory structure
4. Preserve file permissions

### Step 3.3: Set File Permissions

**Location:** cPanel → File Manager

**Required Permissions:**

- Directories: `755` (rwxr-xr-x)
- PHP files: `644` (rw-r--r--)
- `.htaccess`: `644`

**Set Permissions:**

1. Right-click directory/file
2. Select **Change Permissions**
3. Set numeric value:
   - Directories: `755`
   - Files: `644`
4. Check **Recursive** for directories
5. Click **Change Permissions**

**Temporary (for installation only):**

1. Set `site/` directory to `777` (temporarily)
2. This allows installer to create files
3. **Important:** Revert to `755` after installation

### Step 3.4: Run ProcessWire Installer

**Access Installer:**

1. Navigate to: `https://staging.bioco.ch/install.php`
2. Or: `https://staging.bioco.ch/` (if index.php redirects)

**Installation Steps:**

1. **Language Selection:**
   - Choose German or English
   - Click **Continue**

2. **System Requirements Check:**
   - Verify PHP version (8.2 required)
   - Check required extensions are installed
   - Verify file permissions
   - If all green, click **Continue**

3. **Database Configuration:**
   - **Database host:** `localhost`
   - **Database name:** `bioco_staging`
   - **Database user:** `bioco_YOURUSERNAME` (replace with actual username)
   - **Database password:** (enter password)
   - **Database port:** `3306`
   - **Table prefix:** (leave empty or use `pw_`)
   - Click **Continue**

4. **Admin Account:**
   - **Username:** (create new admin username)
   - **Password:** (strong password, save securely)
   - **Email:** (admin email address)
   - Click **Continue**

5. **Installation:**
   - Review settings
   - Click **Install ProcessWire**
   - Wait for installation to complete
   - Note admin URL (usually `/processwire/`)

**After Installation:**

1. **Delete install.php:**
   - Navigate to File Manager
   - Delete `install.php` (security)

2. **Revert Permissions:**
   - Set `site/` directory back to `755` (from `777`)

3. **Verify Admin:**
   - Access: `https://staging.bioco.ch/processwire/`
   - Log in with admin credentials
   - Verify admin dashboard loads

---

## Phase 4: Restore Custom Code

### Step 4.1: Restore from Git (Recommended)

**Location:** cPanel → Git Version Control

1. Navigate to **Git Version Control**
2. Select repository for staging
3. Ensure `develop` branch has all custom code
4. Click **Pull or Deploy**
5. Select `develop` branch
6. Click **Deploy**
7. This restores:
   - `site/modules/` - Custom modules
   - `site/api/` - API endpoints
   - `site/classes/` - Custom classes
   - `site/templates/` - Template files
   - `site/ready.php` - Bootstrap file
   - `site/init.php` - Hooks file

**Verify Files Restored:**

1. Navigate to File Manager
2. Check `site/modules/` contains:
   - `DOIManager/`
   - `FormProcessor/`
   - `InstagramSync/`
   - `MatomoTracker/`
3. Check `site/api/` contains:
   - `events.php`
   - `forms.php`
   - `pages.php`
   - `navigation.php`
   - `doi.php`
   - `instagram.php`
4. Check `site/classes/` contains:
   - `EventSetup.php`

### Step 4.2: Manual Restore (If Git Not Available)

**Upload custom files via File Manager or FTP:**

1. **Custom Modules:**
   - Upload `site/modules/DOIManager/DOIManager.module.php`
   - Upload `site/modules/FormProcessor/FormProcessor.module.php`
   - Upload `site/modules/InstagramSync/InstagramSync.module.php`
   - Upload `site/modules/MatomoTracker/MatomoTracker.module.php`

2. **API Endpoints:**
   - Upload all files from `site/api/` directory

3. **Custom Classes:**
   - Upload `site/classes/EventSetup.php`

4. **Bootstrap Files:**
   - Upload `site/ready.php`
   - Upload `site/init.php` (if exists)

5. **Templates:**
   - Upload template files from `site/templates/`

**Set Permissions:**
- All PHP files: `644`
- All directories: `755`

---

## Phase 5: Configure ProcessWire

### Step 5.1: Create Configuration File

**Location:** `site/config.php`

**Method 1: Restore from Backup**

1. Extract backup ZIP file (from Phase 1.2)
2. Locate `site/config.php` in backup
3. Copy to `site/config.php` on server
4. Update database credentials if changed
5. Verify all settings

**Method 2: Create from Template**

1. Copy `site/config-example.php` to `site/config.php`
2. Edit `site/config.php` with required settings (see below)
3. Save file

**Required Configuration:**

```php
<?php namespace ProcessWire;

// Database
$config->dbHost = 'localhost';
$config->dbName = 'bioco_staging';
$config->dbUser = 'bioco_YOURUSERNAME'; // Replace with actual username
$config->dbPass = 'YOUR_PASSWORD'; // Replace with actual password
$config->dbPort = '3306';

// Site URLs
$config->httpHost = 'staging.bioco.ch';
$config->urls->root = '/';

// Email
$config->email_from = 'noreply@bioco.ch';
$config->email_from_name = 'biocò';
$config->admin_email = 'admin@bioco.ch';
$config->info_email = 'info@bioco.ch';
$config->intranet_email = 'intranet@bioco.ch';

// WireMail SMTP (if using WireMailSmtp module)
$config->wireMail = [
    'smtp_host' => 'mail.bioco.ch',
    'smtp_port' => 465,
    'smtp_ssl' => true,
    'smtp_user' => 'noreply@bioco.ch',
    'smtp_password' => 'YOUR_SMTP_PASSWORD', // Get from Novatrend email settings
];

// Matomo
$config->matomo_enabled = true;
$config->matomo_url = 'https://matomo.bioco.ch/';
$config->matomo_site_id = 1; // 1 for staging

// Instagram (optional)
$config->instagram_access_token = 'YOUR_TOKEN'; // If using Instagram sync
$config->instagram_user_id = 'YOUR_USER_ID'; // If using Instagram sync

// Security
$config->userAuthSalt = 'GENERATE_RANDOM_STRING_HERE'; // Generate 32+ character random string
$config->tableSalt = 'GENERATE_RANDOM_STRING_HERE'; // Generate 32+ character random string
$config->chmodDir = '0755';
$config->chmodFile = '0644';
$config->debug = true; // true for staging

// Payment slip configuration (for membership forms)
$config->payment_account = 'CH80 0839 0032 9330 1010 5';
$config->payment_recipient_name = 'Gemüsegenossenschaft biocò';
$config->payment_recipient_address = 'Allmendstrasse 39b';
$config->payment_recipient_zip = '5400';
$config->payment_recipient_city = 'Baden';
```

**Generate Random Strings:**

Use one of these methods:

1. **Terminal/Command Line:**
   ```bash
   openssl rand -base64 32
   ```

2. **Online Generator:**
   - Visit: https://www.random.org/strings/
   - Generate 32+ character random string
   - Use alphanumeric characters

3. **PHP (if you have access):**
   ```php
   echo bin2hex(random_bytes(16));
   ```

**File Permissions:**
- Set `site/config.php` to `644`
- Ensure it's NOT accessible via web (check `.htaccess` protection)

### Step 5.2: Install Custom Modules

**Location:** ProcessWire Admin → Modules

1. Access admin: `https://staging.bioco.ch/processwire/`
2. Log in with admin credentials
3. Navigate to: **Setup → Modules**
4. Click **Refresh** button (top right)
5. Custom modules should appear in module list

**Install Each Module:**

1. **DOIManager:**
   - Find "DOI Manager" in module list
   - Click **Install**
   - Module creates `doi_tokens` table automatically
   - Verify installation success (should show "Installed")

2. **FormProcessor:**
   - Find "Form Processor" in module list
   - Click **Install**
   - Verify installation success

3. **InstagramSync:**
   - Find "Instagram Sync" in module list
   - Click **Install**
   - Configure Instagram API credentials (if needed) via module settings
   - Verify installation success

4. **MatomoTracker:**
   - Find "Matomo Tracker" in module list
   - Click **Install**
   - Verify installation success

**Verify Module Status:**
- All custom modules should show "Installed" status
- Check for any error messages
- Review module logs if issues occur (Setup → Logs)

### Step 5.3: Initialize EventSetup

**Location:** `site/ready.php`

**Verify Bootstrap File:**

1. Navigate to File Manager
2. Open `site/ready.php`
3. Verify it contains:

```php
<?php namespace ProcessWire;

require_once __DIR__ . '/classes/EventSetup.php';
(new EventSetup($wire))->bootstrap();
```

**Trigger Bootstrap:**

1. Access any page on staging site: `https://staging.bioco.ch/`
2. EventSetup runs automatically on first page load
3. Creates event fields and template if they don't exist

**Verify in Admin:**

1. **Setup → Fields:**
   - Check for: `event_status`, `event_start`, `event_end`, `event_location`, `event_summary`, `event_media`, `event_signup_enabled`, `event_signup_notes`
   - All should exist

2. **Setup → Templates:**
   - Check for: `event` template
   - Should exist

3. **Pages:**
   - Check for: `/events/` parent page
   - Should be created automatically

---

## Phase 6: Restore Content

### Step 6.1: Restore Database

**⚠️ Only if you wiped the database in Phase 2**

**Location:** cPanel → phpMyAdmin

**Option A: If Database is Empty**

1. Select database: `bioco_staging`
2. Click **Import** tab
3. Click **Choose File**
4. Select backup SQL file from Phase 1.1
5. Click **Go**
6. Wait for import to complete
7. Verify tables created

**Option B: If Database Has ProcessWire Tables from Fresh Install**

1. Select database: `bioco_staging`
2. Click **Structure** tab
3. Check "Select All" for ProcessWire tables
4. **Keep:** `doi_tokens` table (if exists, from DOIManager)
5. From dropdown: **With selected:** → **Drop**
6. Confirm deletion
7. Click **Import** tab
8. Import backup SQL file
9. This restores all content

**After Import:**

1. Click **Structure** tab
2. Verify tables exist:
   - `pages` - Should have rows
   - `fields` - Should have field definitions
   - `templates` - Should have template definitions
   - `fieldgroups` - Should have field-to-template mappings
3. Click **Browse** on `pages` table
4. Verify pages exist (should see homepage, etc.)

### Step 6.2: Restore Media Files

**Location:** cPanel → File Manager

1. Extract backup ZIP file (from Phase 1.2) on local machine
2. Navigate to `site/assets/files/` in extracted backup
3. **Upload to server:**
   - Navigate to `site/assets/files/` on server
   - Upload all files and directories
   - Preserve directory structure
4. **Set File Permissions:**
   - Directories: `755`
   - Files: `644`

**Alternative: FTP/SFTP**

1. Connect via FTP client
2. Navigate to `site/assets/files/` in backup
3. Upload entire directory structure to server
4. Preserve directory structure

**Verify Media:**

1. Access ProcessWire admin
2. Navigate to **Pages**
3. Edit a page that has images
4. Verify images display correctly in admin
5. Check image fields show uploaded files

### Step 6.3: Verify Content

**Location:** ProcessWire Admin

1. **Pages:**
   - Navigate to **Pages**
   - Verify page tree structure
   - Check homepage exists
   - Check event pages exist (if any)
   - Click on pages, verify content displays

2. **Templates:**
   - Navigate to **Setup → Templates**
   - Verify all templates exist:
     - `home`
     - `basic-page`
     - `event`
     - `doi-confirm`
     - Form templates (if any)

3. **Fields:**
   - Navigate to **Setup → Fields**
   - Verify custom fields exist
   - Check event fields created by EventSetup
   - Verify field types are correct

---

## Phase 7: Configure .htaccess

### Step 7.1: Verify .htaccess

**Location:** Root of ProcessWire installation

**Required Configuration:**

```apache
# Force PHP 8.2
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php82 .php .php8 .phtml
</IfModule>

# ProcessWire URL rewriting
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?it=$1 [L,QSA]

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"

# Protect config.php
<FilesMatch "config\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

**Verify:**

1. Navigate to File Manager
2. Open `.htaccess` file in root directory
3. Verify configuration matches above
4. If missing or incorrect, update file
5. **File Permissions:** `644`

**Test URL Rewriting:**

1. Access: `https://staging.bioco.ch/api/events`
2. Should return JSON (not 404)
3. If 404, check `.htaccess` configuration

---

## Phase 8: Test API Endpoints

### Step 8.1: Test Each Endpoint

**Use browser or command line to test:**

1. **Events API:**
   ```
   https://staging.bioco.ch/api/events
   ```
   - Should return JSON with `upcoming` and `past` arrays
   - Verify events from database appear
   - Check browser console or use curl:
     ```bash
     curl https://staging.bioco.ch/api/events
     ```

2. **Pages API:**
   ```
   https://staging.bioco.ch/api/pages?path=/
   ```
   - Should return homepage data as JSON
   - Test with curl:
     ```bash
     curl "https://staging.bioco.ch/api/pages?path=/"
     ```

3. **Navigation API:**
   ```
   https://staging.bioco.ch/api/navigation
   ```
   - Should return navigation structure
   - Test with curl:
     ```bash
     curl https://staging.bioco.ch/api/navigation
     ```

4. **Forms API:**
   ```
   https://staging.bioco.ch/api/forms/contact
   ```
   - Test POST request:
     ```bash
     curl -X POST https://staging.bioco.ch/api/forms/contact \
       -H "Content-Type: application/json" \
       -d '{"name":"Test","email":"test@example.com","message":"Test message"}'
     ```
   - Should return success response

5. **DOI API:**
   ```
   https://staging.bioco.ch/api/doi?token=TEST_TOKEN
   ```
   - Test with valid token if available
   - Should return confirmation or error message

6. **Instagram API:**
   ```
   https://staging.bioco.ch/api/instagram
   ```
   - Should return Instagram posts (if configured)
   - Test with curl:
     ```bash
     curl https://staging.bioco.ch/api/instagram
     ```

**Verify Responses:**

- All endpoints return JSON
- Status codes are correct (200 for success)
- No PHP errors in response
- CORS headers present (if needed for frontend)
- Response structure matches expected format

**Common Issues:**

- **404 Error:** Check `.htaccess` URL rewriting
- **500 Error:** Check PHP error logs, verify file permissions
- **Empty Response:** Check database connection, verify content exists

---

## Phase 9: Verify Frontend Connection

### Step 9.1: Check Vercel Environment Variables

**Location:** Vercel Dashboard → Project → Settings → Environment Variables

**Verify Staging Variables:**

1. Log in to Vercel Dashboard
2. Select bioco project
3. Navigate to **Settings** → **Environment Variables**
4. Verify these variables exist for **Preview** scope (develop branch):
   - `NEXT_PUBLIC_PROCESSWIRE_API_URL=https://staging.bioco.ch/api`
   - `PROCESSWIRE_API_URL=https://staging.bioco.ch/api`
   - `NEXT_PUBLIC_SITE_URL=https://staging.bioco.ch`
   - `NEXT_PUBLIC_MATOMO_URL=https://matomo.bioco.ch/`
   - `NEXT_PUBLIC_MATOMO_SITE_ID=1`
   - SMTP credentials (if used by frontend)

5. **If variables are missing or incorrect:**
   - Add/Edit variables
   - Set scope to **Preview** (for develop branch)
   - Save changes
   - Redeploy frontend (or wait for next deployment)

### Step 9.2: Test Frontend

1. **Access Staging Site:**
   - Navigate to: `https://staging.bioco.ch`
   - Verify homepage loads
   - Check browser console (F12) for errors

2. **Test API Integration:**
   - Open browser DevTools → **Network** tab
   - Navigate through site
   - Verify API calls to `/api/events`, `/api/pages`, etc.
   - Check responses are successful (200 status)
   - Verify JSON responses are valid

3. **Test Forms:**
   - Navigate to contact form (if available)
   - Submit test form
   - Verify form submission works
   - Check email delivery (if configured)
   - Verify success message displays

4. **Test Events:**
   - Navigate to events section
   - Verify events display
   - Test event signup form (if available)
   - Verify event data loads from API

**Common Issues:**

- **API calls fail:** Check Vercel environment variables
- **CORS errors:** Verify CORS headers in API endpoints
- **404 on API:** Check API endpoint URLs
- **Empty data:** Verify database content restored

---

## Phase 10: Final Verification Checklist

### ProcessWire Admin

- [ ] Admin login works
- [ ] All custom modules installed (DOIManager, FormProcessor, InstagramSync, MatomoTracker)
- [ ] Event fields created (event_status, event_start, event_end, etc.)
- [ ] Event template exists
- [ ] Pages tree structure correct
- [ ] Media files accessible
- [ ] User roles configured (if needed)

### API Endpoints

- [ ] `/api/events` returns data
- [ ] `/api/pages` returns data
- [ ] `/api/navigation` returns data
- [ ] `/api/forms` accepts submissions
- [ ] `/api/doi` validates tokens
- [ ] `/api/instagram` returns posts (if configured)
- [ ] All endpoints return proper JSON
- [ ] No PHP errors in responses

### Database

- [ ] All tables exist
- [ ] Content restored (pages, fields, templates)
- [ ] Custom table `doi_tokens` exists
- [ ] No database errors in logs
- [ ] Pages have content

### File System

- [ ] Custom modules in place
- [ ] API endpoints in place
- [ ] Media files restored
- [ ] File permissions correct (755 for dirs, 644 for files)
- [ ] `.htaccess` configured
- [ ] `site/config.php` exists and configured

### Frontend Integration

- [ ] Frontend loads staging site
- [ ] API calls succeed
- [ ] Forms submit successfully
- [ ] Events display correctly
- [ ] No console errors
- [ ] Images load correctly

### Email System

- [ ] SMTP configuration correct
- [ ] Test email sends successfully
- [ ] Form notifications work
- [ ] DOI emails send
- [ ] Email recipients receive messages

### Automation

- [ ] EventSetup bootstrap runs
- [ ] LazyCron configured (for Instagram sync, event automation)
- [ ] Scheduled tasks work (test by waiting for cron trigger)

---

## Phase 11: Documentation Update

### Step 11.1: Update Configuration Documentation

**Update files if needed:**

1. **`site/config-example.php`:**
   - Reflect actual configuration used
   - Add any new settings discovered

2. **`HANDOFF.md`:**
   - Update with any changes made during rebuild
   - Document any issues encountered

3. **`docs/processwire-migration.md`:**
   - Add notes about rebuild process
   - Document any deviations from standard migration

### Step 11.2: Document Issues Encountered

**Create notes:**

1. **Problems encountered:**
   - List any issues during rebuild
   - Document error messages
   - Note which phase had issues

2. **Solutions applied:**
   - Document how issues were resolved
   - Note any workarounds used

3. **Configuration changes:**
   - Document any settings changed
   - Note any custom configurations

4. **Lessons learned:**
   - What worked well
   - What could be improved
   - Recommendations for production rebuild

---

## Rollback Plan

**If rebuild fails at any point:**

### Option 1: Restore from Backup

1. **Restore Database:**
   - cPanel → phpMyAdmin
   - Select `bioco_staging` database
   - Import backup SQL file from Phase 1.1

2. **Restore Files:**
   - Extract backup ZIP from Phase 1.2
   - Upload files to server
   - Restore directory structure

3. **Verify Site Works:**
   - Access staging site
   - Verify pages load
   - Check admin access

### Option 2: Use Backup Directory

1. **If you created backup directory in Phase 2.2:**
   - Rename current installation (e.g., `bioco_staging_broken`)
   - Rename backup directory back to original (e.g., `bioco_staging`)
   - Site should work as before

2. **Verify:**
   - Access staging site
   - Verify everything works
   - Investigate what went wrong before retrying

---

## Timeline Estimate

- **Phase 1 (Backup):** 30 minutes
- **Phase 2 (Wipe):** 15 minutes
- **Phase 3 (Fresh Install):** 30 minutes
- **Phase 4 (Restore Code):** 15 minutes
- **Phase 5 (Configure):** 30 minutes
- **Phase 6 (Restore Content):** 30 minutes
- **Phase 7 (htaccess):** 10 minutes
- **Phase 8 (Test API):** 20 minutes
- **Phase 9 (Frontend):** 20 minutes
- **Phase 10 (Verification):** 30 minutes
- **Phase 11 (Documentation):** 15 minutes

**Total Estimated Time:** 3-4 hours

**Allow extra time for:**
- Troubleshooting issues
- Waiting for file uploads
- Testing thoroughly

---

## Next Steps After Staging

Once staging rebuild is successful and verified:

1. **Document Results:**
   - Update documentation with lessons learned
   - Note any issues and solutions

2. **Plan Production Rebuild:**
   - Create similar plan for production
   - Schedule during maintenance window
   - Prepare team communication

3. **Execute Production Rebuild:**
   - Follow similar process
   - Use lessons learned from staging
   - Ensure all backups are in place

---

## Important Notes

- **Always backup first** - Never skip Phase 1
- **Test thoroughly** - Don't rush Phase 10 verification
- **Document everything** - Keep notes for production rebuild
- **Keep backups** - Don't delete backups until production is verified
- **Communicate** - Inform team of staging rebuild schedule
- **Work during low-traffic hours** - Minimize impact on testing

---

## Support Resources

- **ProcessWire Documentation:** https://processwire.com/docs/
- **ProcessWire Forums:** https://processwire.com/talk/
- **cPanel Documentation:** https://docs.cpanel.net/
- **Vercel Documentation:** https://vercel.com/docs

---

**End of Implementation Guide**
