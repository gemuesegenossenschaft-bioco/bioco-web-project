# ProcessWire Migration Guide

Complete deployment guide for migrating the bioco.ch website from local development → `staging.bioco.ch` → `www.bioco.ch`.

## Table of Contents

1. [Overview](#overview)
2. [ProcessWire Backend Components](#processwire-backend-components)
3. [Frontend (Next.js/Vercel) Configuration](#frontend-nextjsvercel-configuration)
4. [Database & Content Migration](#database--content-migration)
5. [Member Onboarding Process](#member-onboarding-process)
6. [Staging Migration Checklist](#staging-migration-checklist)
7. [Production Migration Checklist](#production-migration-checklist)
8. [Rollback Procedures](#rollback-procedures)
9. [Monitoring & Maintenance](#monitoring--maintenance)
10. [Security Considerations](#security-considerations)
11. [Event Management System](#event-management-system)
12. [Reference Links](#reference-links)

---

## Overview

The bioco.ch website uses a **Git-first deployment workflow**:

- **Local (Development):** Code is written on local machines
- **GitHub (Version Control):** Code is stored in `bioco-web-project` repository
- **Server Environments:**
  - `develop` branch → `staging.bioco.ch` (testing)
  - `main` branch → `www.bioco.ch` (production)

**Technology Stack:**

- **Backend:** ProcessWire CMS (PHP 8.2) hosted on Novatrend cPanel
- **Frontend:** Next.js 14 (React) deployed on Vercel
- **Database:** MySQL (3 databases: live, staging, matomo)
- **Analytics:** Matomo (cookieless, Swiss DSG compliant)

---

## ProcessWire Backend Components

All custom modules and configurations that must be deployed to the server.

### Custom Modules

#### DOIManager

**Purpose:** Manages double opt-in (DOI) email confirmation for newsletter subscriptions and form submissions.

**Key Features:**

- Creates `doi_tokens` database table automatically
- Generates secure tokens with 24-hour expiration
- Tracks confirmation status and timestamps
- Integrates with FormProcessor for newsletter flow

**Installation:**

1. Upload to `site/modules/DOIManager/`
2. Install via ProcessWire admin → Modules → Refresh
3. Module creates database table on first init

#### FormProcessor

**Purpose:** Handles all form submissions with validation, storage, and email notifications.

**Supported Form Types:**

- `contact` - Contact form
- `subscribe` - Newsletter subscription (triggers DOI flow)
- `visit` - Open visit day registration (Schnuppertag)
- `waiting-list` - Waiting list for full subscription slots
- `event-signup` - Event registration
- `membership` - New member onboarding (full registration with payment slip)

**Key Features:**

- Input sanitization and validation
- Spam protection (honeypot fields)
- Email notifications to admins
- Form data stored in ProcessWire pages
- DOI integration for newsletter signups
- Swiss QR payment slip generation (Einzahlungsschein) for member onboarding

**Configuration:**

- Set `$config->admin_email` in `site/config.php`
- Set `$config->email_from` and `$config->email_from_name`
- Set `$config->info_email = 'info@bioco.ch'` (member onboarding notifications)
- Set `$config->intranet_email = 'intranet@bioco.ch'` (member onboarding notifications)

#### InstagramSync

**Purpose:** Synchronizes Instagram posts from @bioco_ch to the Aktuelles section.

**Key Features:**

- Daily sync via LazyCron
- Creates/updates posts automatically
- Downloads images to ProcessWire assets
- Maps Instagram data to ProcessWire fields

**Configuration:**

- Requires Instagram API credentials (Graph API)
- Set `$config->instagram_access_token` in `site/config.php`
- Set `$config->instagram_user_id`

**Cron Dependency:**

- Requires `LazyCron` module (core ProcessWire)
- Runs automatically when site is accessed after midnight

#### MatomoTracker

**Purpose:** Provides cookieless analytics tracking compliant with Swiss DSG.

**Key Features:**

- Server-side event tracking
- Client-side tracking script generation
- Session-based event queue
- No cookies required (privacy-friendly)

**Configuration:**

```php
$config->matomo_enabled = true;
$config->matomo_url = 'https://matomo.bioco.ch/'; // Trailing slash required
$config->matomo_site_id = 1; // Site ID from Matomo
```

#### EventSetup

**Purpose:** Manages event lifecycle, including automatic status changes and signup toggles.

**Key Features:**

- Creates event fields and template on bootstrap
- Daily automation via LazyCron (changes events to "past" status)
- Auto-disables signup forms for past events
- Supports media galleries (images/videos)

**Fields Created:**

| Field                  | Type     | Purpose                                   |
| ---------------------- | -------- | ----------------------------------------- |
| `event_status`         | Options  | `upcoming` or `past`                      |
| `event_start`          | DateTime | Event start time                          |
| `event_end`            | DateTime | Event end time                            |
| `event_location`       | Text     | Location name                             |
| `event_summary`        | Textarea | Short description for cards               |
| `body`                 | Textarea | Full description (existing field)         |
| `event_media`          | File     | Photos/videos (jpg, png, webp, mp4, webm) |
| `event_signup_enabled` | Checkbox | Show/hide signup form                     |
| `event_signup_notes`   | Textarea | Instructions for signup form              |

**See [Event Management System](#event-management-system) section for full details.**

### API Endpoints

All API endpoints are located in `site/api/` and return JSON.

| Endpoint         | Purpose                                           | HTTP Method |
| ---------------- | ------------------------------------------------- | ----------- |
| `/api/events`    | Event feed (upcoming & past)                      | GET         |
| `/api/forms`     | Form submission handler                           | POST        |
| `/api/forms/membership` | Member onboarding with payment slip       | POST        |
| `/api/instagram` | Instagram posts feed                              | GET         |
| `/api/doi`       | Double opt-in confirmation                        | GET         |
| `/api/navigation`| Site navigation structure                         | GET         |
| `/api/pages`     | Page content retrieval                            | GET         |

**Security:**

- All endpoints validate and sanitize inputs
- CORS headers configured for Vercel domains
- API tokens required for write operations

### Templates & Fields

**Templates:**

- `home` - Homepage template
- `basic-page` - Standard content pages
- `event` - Event pages (under "Events" parent)
- `form-contact` - Contact form
- `form-subscribe` - Newsletter subscription
- `form-open-visit-day` - Visit day registration
- `form-waiting-list` - Waiting list
- `doi-confirm` - DOI confirmation page

**Key Fields:**

- `logo_image` (Image) - Site logo
- `hero_image` (Image) - Hero banner images
- `hero_subtitle` (Text) - Hero subtitle text
- `body` (Textarea) - Main content
- `gallery_images` (Image, multiple) - Image galleries
- Event fields (see EventSetup section)

### Configuration Files

#### site/config.php

Environment-specific configuration (NOT tracked in Git - ignored by `.gitignore`).

**Required Settings:**

```php
<?php namespace ProcessWire;

// Database
$config->dbHost = 'localhost';
$config->dbName = 'bioco_staging'; // or bioco_live
$config->dbUser = 'bioco_YOURUSERNAME';
$config->dbPass = 'YOUR_PASSWORD';
$config->dbPort = '3306';

// Site URLs
$config->httpHost = 'staging.bioco.ch'; // or www.bioco.ch
$config->urls->root = '/';

// Email
$config->email_from = 'hallo@bioco.ch';
$config->email_from_name = 'biocò Gemüsegenossenschaft';
$config->admin_email = 'admin@bioco.ch';

// Member onboarding notifications
$config->info_email = 'info@bioco.ch';
$config->intranet_email = 'intranet@bioco.ch';

// Payment slip configuration (Swiss QR-Bill)
$config->payment_account = 'CH80 0839 0032 9330 1010 5';
$config->payment_recipient_name = 'Gemüsegenossenschaft biocò';
$config->payment_recipient_address = 'Allmendstrasse 39b';
$config->payment_recipient_zip = '5400';
$config->payment_recipient_city = 'Baden';

// Matomo
$config->matomo_enabled = true;
$config->matomo_url = 'https://matomo.bioco.ch/';
$config->matomo_site_id = 1; // 1 for staging, 2 for production

// Instagram (optional)
$config->instagram_access_token = 'YOUR_TOKEN';
$config->instagram_user_id = 'YOUR_USER_ID';

// Security
$config->userAuthSalt = 'GENERATE_RANDOM_STRING';
$config->tableSalt = 'GENERATE_RANDOM_STRING';
$config->chmodDir = '0755';
$config->chmodFile = '0644';
$config->debug = false; // true for staging, false for production
```

#### site/ready.php

Bootstrap file that initializes custom modules.

**Content:**

```php
<?php namespace ProcessWire;

// Initialize EventSetup (creates fields/template if needed)
require_once __DIR__ . '/classes/EventSetup.php';
(new EventSetup($wire))->bootstrap();
```

#### site/init.php

Global initialization hooks (if needed).

#### .htaccess

**Location:** Root of ProcessWire installation (e.g., `public_html/.htaccess`)

**Key Requirements:**

```apacheconfig
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
```

**File Permissions:**

- `.htaccess` must be `644`
- Directories must be `755`
- PHP files must be `644`

---

## Frontend (Next.js/Vercel) Configuration

### Environment Variables

Environment variables must be configured in Vercel dashboard for each environment.

#### Staging Environment (`staging.bioco.ch`)

```bash
# ProcessWire API
NEXT_PUBLIC_PROCESSWIRE_API_URL=https://staging.bioco.ch/api
PROCESSWIRE_API_URL=https://staging.bioco.ch/api
PROCESSWIRE_API_TOKEN=your_staging_token_here

# Site
NEXT_PUBLIC_SITE_URL=https://staging.bioco.ch

# Matomo Analytics
NEXT_PUBLIC_MATOMO_URL=https://matomo.bioco.ch/
NEXT_PUBLIC_MATOMO_SITE_ID=1

# Build
NODE_ENV=production
```

#### Production Environment (`www.bioco.ch`)

```bash
# ProcessWire API
NEXT_PUBLIC_PROCESSWIRE_API_URL=https://www.bioco.ch/api
PROCESSWIRE_API_URL=https://www.bioco.ch/api
PROCESSWIRE_API_TOKEN=your_production_token_here

# Site
NEXT_PUBLIC_SITE_URL=https://www.bioco.ch

# Matomo Analytics
NEXT_PUBLIC_MATOMO_URL=https://matomo.bioco.ch/
NEXT_PUBLIC_MATOMO_SITE_ID=2

# Build
NODE_ENV=production
```

**Security Notes:**

- `PROCESSWIRE_API_TOKEN` - Server-side only (not prefixed with `NEXT_PUBLIC_`)
- All `NEXT_PUBLIC_*` variables are exposed to the browser

### Vercel Configuration

#### Branch Deployment Strategy

Configure in Vercel project settings:

- **Production Branch:** `main` → `www.bioco.ch`
- **Preview Branch:** `develop` → `staging.bioco.ch`

#### Build Settings

```json
{
  "buildCommand": "cd frontend && npm run build",
  "outputDirectory": "frontend/.next",
  "installCommand": "cd frontend && npm install",
  "devCommand": "cd frontend && npm run dev"
}
```

#### Custom Domains

1. **Staging:**
   - Domain: `staging.bioco.ch`
   - Branch: `develop`
   - SSL: Auto (Let's Encrypt)

2. **Production:**
   - Domain: `www.bioco.ch`
   - Branch: `main`
   - SSL: Auto (Let's Encrypt)
   - Redirect `bioco.ch` → `www.bioco.ch`

#### Preview Deployments

- Every push to `develop` triggers a preview deployment
- PR deployments can be enabled for feature branches
- Preview URLs: `bioco-web-project-<git-hash>.vercel.app`

### Build Process

#### Build Command

```bash
npm run build
```

#### Requirements

- Node.js 18+ (specified in Vercel project settings)
- All environment variables must be set before build
- API endpoints must be accessible during build (for static generation)

#### Static Generation vs. Server-Side Rendering

**Static Pages (SSG):**

- `/` (Homepage)
- `/wir` (About)
- `/gemuese` (Vegetables/Seasonal calendar)
- `/abos` (Subscriptions)
- `/mitmachen` (Get Involved)
- `/standorte-depots` (Locations/Depots)
- `/solawi` (Solidarische Landwirtschaft)

**Dynamic Pages (SSR/ISR):**

- `/aktuelles` (News & Events) - ISR with 5-minute revalidation
- `/api/events` (API proxy) - On-demand

**Revalidation Strategies:**

- Event data cached for 5 minutes (`revalidate: 300`)
- Static pages regenerated on deployment
- ISR allows updates without full redeploy

---

## Database & Content Migration

### Initial Setup

**Database Creation:**

Already completed per [existing setup docs](https://wgusta.github.io/bioco-doku/technical_setup_processwire/):

1. Three databases created via cPanel → MySQL® Databases:
   - `bioco_live` - Production
   - `bioco_staging` - Staging
   - `bioco_matomo` - Analytics

2. Database user: `bioco_YOURUSERNAME` (replace with actual cPanel username)

3. User granted ALL PRIVILEGES on all three databases

**ProcessWire Installation:**

- Use web installer at `http://staging.bioco.ch` (for staging)
- Use web installer at `http://www.bioco.ch` (for production)
- Create admin user (save credentials securely)
- Installer creates core tables and initial pages

### Content Migration

#### Exporting Content from Staging to Production

**Method 1: Database Export/Import**

1. **Export Staging Database:**
   - cPanel → phpMyAdmin
   - Select `bioco_staging`
   - Export → Custom → SQL format
   - Save as `bioco_staging_YYYY-MM-DD.sql`

2. **Import to Production:**
   - cPanel → phpMyAdmin
   - Select `bioco_live`
   - Import → Choose file
   - Execute import

3. **Update URLs:**
   - After import, run SQL to update URLs:

   ```sql
   UPDATE pages SET name = REPLACE(name, 'staging.bioco.ch', 'www.bioco.ch');
   UPDATE field_data SET data = REPLACE(data, 'staging.bioco.ch', 'www.bioco.ch');
   ```

**Method 2: Manual Content Entry**

- Recommended for initial launch
- Ensures clean content without test data
- Use staging as reference

#### Media File Transfers

**Transfer `site/assets/files/` directory:**

1. **Download from Staging:**
   - cPanel → File Manager
   - Navigate to `public_html/bioco_staging/site/assets/files/`
   - Select all → Compress → Download ZIP

2. **Upload to Production:**
   - Extract ZIP locally
   - cPanel → File Manager
   - Navigate to `public_html/site/assets/files/`
   - Upload all files (maintain directory structure)

3. **Set Permissions:**
   - Directories: `755`
   - Files: `644`

**Important:** The `files/` directory contains:

- Page images
- Event media (photos/videos)
- Uploaded documents
- Image variations (resized versions)

#### User Accounts

**Admin Users:**

1. Create admin account during ProcessWire installation
2. Create additional editor accounts:
   - ProcessWire admin → Access → Users → Add New
   - Assign `redaktion` role (limited permissions)

**Roles:**

- `superuser` - Full admin access
- `admin` - Admin access without module management
- `redaktion` - Editor role (content only, no settings)

### Content Migration Script

The migration script (`site/templates/migrate.php`) automates content page creation.

#### Setup

1. Rename file on server:
   ```bash
   mv site/templates/migrate-pages.php site/templates/migrate.php
   ```

2. Create template in ProcessWire:
   - Setup → Templates → Add New → select `migrate`

3. Create page:
   - Pages → Add New → Title: `migrate` → Template: `migrate`

#### Run Migration

```bash
# Create new pages (skip existing)
https://cms.bioco.ch/migrate/

# Overwrite existing pages
https://cms.bioco.ch/migrate/?overwrite=1
```

#### Pages Created

| Page | Sections | Components |
|------|----------|------------|
| mitmachen | 6 | schnuppertage |
| gemuese | 5 | saisonkalender, gallery |
| solawi | 6 | - |
| abos | 6 | - |
| aktuelles | 4 | schnuppertage, events_feed |
| kontakt | 4 | contact_form |
| standorte-depots | 4 | geisshof_map, depot_map |
| wir | 20 | - |
| bioco-werden | 2 | pricing_calculator |
| datenschutz | 6 | - |
| impressum | 4 | - |
| statuten | 4 | - |
| newsletter | 2 | subscribe_form |
| warteliste | 2 | waiting_list_form |
| anmeldung | 2 | membership_form |
| tag-der-offenen-tuer | 2 | visit_day_form |

**Note:** Homepage managed separately via `homepage_content` template.

#### Events Sync

Events are fetched dynamically from `/api/events`:
- `schnuppertage` component: mitmachen, aktuelles pages
- `events_feed` component: aktuelles page

#### After Migration

1. Delete `migrate` page
2. Add images to sections via ProcessWire admin
3. Test pages on frontend

### Data Seeding

#### Static Event Data

**Schnuppertag Events for 2026:**

Create manually in ProcessWire admin or via API:

- 28.04.2026, 14:00-17:00 Uhr
- 29.05.2026, 14:00-17:00 Uhr
- 26.06.2026, 14:00-17:00 Uhr
- 31.07.2026, 14:00-17:00 Uhr
- 28.08.2026, 14:00-17:00 Uhr
- 25.09.2026, 14:00-17:00 Uhr
- 30.10.2026, 14:00-17:00 Uhr

**Event Template:**

- Template: `event`
- Parent: `/events/`
- Title: "Schnuppertag"
- Location: "Geisshof"
- Summary: "Erlebe einen Tag auf dem Geisshof..."
- Signup enabled: ✓
- Status: `upcoming`

#### Sample Content for Testing

**Staging Only:**

- Test events with various dates (past/upcoming)
- Sample contact form submissions
- Test newsletter signups (use test email addresses)
- Sample Instagram posts (if sync not yet configured)

#### Aktuelles/Instagram Integration

**Initial Setup:**

1. Configure Instagram API credentials in `site/config.php`
2. Run manual sync: ProcessWire admin → Modules → InstagramSync → Sync Now
3. Verify posts appear in `/aktuelles/`
4. Daily sync runs automatically via LazyCron

---

## Member Onboarding Process

Complete workflow for new member registration with payment slip generation and multi-recipient email notifications.

### Overview

When a new member completes the membership registration form (`/anmeldung`), the system:

1. Validates all form data (6-step form)
2. Generates a Swiss QR payment slip (Einzahlungsschein) as PDF
3. Sends confirmation email to the new member with payment slip attached
4. Sends notification emails to internal recipients with registration details and payment slip
5. Stores registration data in ProcessWire for admin review

### Form Data Collected

**Step 1: Personal Information**
- First name, Last name
- Address, ZIP, City
- Phone, Email

**Step 2: Membership & Abo Type**
- Membership type: `abo` (subscription) or `shares-only` (shares only)
- Abo type: `halb` (750 CHF, 1 share), `standard` (1400 CHF, 2 shares), `doppel` (2700 CHF, 4 shares)
- Additional shares (optional)

**Step 3: Depot Selection**
- Preferred depot location for vegetable pickup

**Step 4: Payment Type**
- `quarterly` (4× per year) or `yearly` (1× per year)

**Step 5: Mitarbeit (Work Contribution)**
- Preferred work days (Mon-Sun)
- Preferred work times (morning, afternoon, evening)
- Activity areas (field work, harvest, distribution, events, etc.)
- Other activities (free text)

**Step 6: Additional Products (Zusatzabos)**
- Optional: eggs, bread, cheese, etc.
- Further products (free text)

**Step 7: Commitment & Privacy**
- Acceptance of 4 commitment statements
- Privacy policy acceptance

### Payment Slip Generation (Einzahlungsschein)

**Swiss QR-Bill Format:**

```
Account (IBAN): CH80 0839 0032 9330 1010 5
Recipient:
  Gemüsegenossenschaft biocò
  Allmendstrasse 39b
  5400 Baden
  
Payer: [Member Name & Address]
Amount: [Based on Abo Type + Additional Shares]
Currency: CHF
```

**Calculation Examples:**

- **Halb-Abo:** 750 CHF (1 share included)
- **Standard-Abo:** 1400 CHF (2 shares included)
- **Doppel-Abo:** 2700 CHF (4 shares included)
- **Additional Shares:** +300 CHF per share

**Payment Schedule:**

- **Yearly:** Full amount due once per year
- **Quarterly:** Amount divided by 4, due 4 times per year

**PDF Template Location:**

- ProcessWire template: `site/templates/pdf/einzahlungsschein.php`
- Uses TCPDF library (included in ProcessWire)
- Generates QR code for Swiss QR-Bill standard
- Attached to all notification emails

### Email Notifications

#### 1. Confirmation Email to New Member

**Recipient:** Member's email address (from form)

**Subject:** Willkommen bei biocò – Deine Anmeldung

**Content:**

- Welcome message
- Summary of selected abo and depot
- Payment information and due dates
- **Attachment:** Einzahlungsschein PDF
- Next steps (payment, first pickup date, work day scheduling)

**Template:** `site/templates/emails/member-confirmation.php`

#### 2. Notification to info@bioco.ch

**Recipient:** `info@bioco.ch`

**Subject:** Neue Mitgliedsanmeldung: [Member Name]

**Content:**

- Complete registration details:
  - Personal information
  - Abo type and depot
  - Payment type and amount
  - Work preferences (days, times, areas)
  - Additional products
- **Attachment:** Einzahlungsschein PDF
- Link to ProcessWire admin to review submission

**Template:** `site/templates/emails/member-notification-info.php`

#### 3. Notification to intranet@bioco.ch

**Recipient:** `intranet@bioco.ch`

**Subject:** Neue Mitgliedsanmeldung: [Member Name]

**Content:** (Same as info@bioco.ch email)

- Complete registration details
- **Attachment:** Einzahlungsschein PDF
- This email is for internal team coordination and intranet system updates

**Template:** `site/templates/emails/member-notification-intranet.php`

### ProcessWire Implementation

#### FormProcessor Extension

Add to `site/modules/FormProcessor/FormProcessor.module.php`:

```php
/**
 * Process membership form submission
 */
public function processMembershipForm($post) {
    // Validate all fields
    $firstName = $this->sanitizer->text($post->firstName ?? '');
    $lastName = $this->sanitizer->text($post->lastName ?? '');
    $email = $this->sanitizer->email($post->email ?? '');
    // ... (validate all fields)
    
    // Calculate total amount
    $aboType = $post->aboType ?? 'standard';
    $additionalShares = (int)($post->additionalShares ?? 0);
    $amount = $this->calculateMembershipAmount($aboType, $additionalShares);
    
    // Generate payment slip PDF
    $pdfPath = $this->generatePaymentSlip([
        'firstName' => $firstName,
        'lastName' => $lastName,
        'address' => $post->address,
        'zip' => $post->zip,
        'city' => $post->city,
        'amount' => $amount,
        'paymentType' => $post->paymentType,
    ]);
    
    // Store form data
    $formData = [
        'form_type' => 'membership',
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => $email,
        // ... all other fields
        'amount' => $amount,
        'created' => time(),
    ];
    
    // Send confirmation to member
    $this->sendMemberConfirmation($formData, $pdfPath);
    
    // Send notification to info@bioco.ch
    $this->sendNotificationEmail($this->config->info_email, $formData, $pdfPath);
    
    // Send notification to intranet@bioco.ch
    $this->sendNotificationEmail($this->config->intranet_email, $formData, $pdfPath);
    
    // Track event
    if($this->modules->isInstalled('MatomoTracker')) {
        $this->modules->get('MatomoTracker')->trackEvent('Form', 'Membership', 'Submit');
    }
    
    return ['success' => true];
}

/**
 * Calculate membership amount based on abo type and additional shares
 */
private function calculateMembershipAmount($aboType, $additionalShares) {
    $amounts = [
        'halb' => 750,
        'standard' => 1400,
        'doppel' => 2700,
        'kein' => 0, // shares-only
    ];
    
    $baseAmount = $amounts[$aboType] ?? 1400;
    $sharePrice = 300;
    $totalAmount = $baseAmount + ($additionalShares * $sharePrice);
    
    return $totalAmount;
}

/**
 * Generate Swiss QR payment slip as PDF
 */
private function generatePaymentSlip($data) {
    require_once($this->config->paths->root . 'vendor/tcpdf/tcpdf.php');
    
    // Generate PDF with QR code
    // (Implementation uses TCPDF library)
    // Returns path to generated PDF file
    
    $pdfFile = $this->config->paths->cache . 'einzahlungsschein_' . time() . '.pdf';
    // ... PDF generation logic
    
    return $pdfFile;
}
```

#### API Endpoint

Add to `site/api/forms.php`:

```php
// Add 'membership' to allowed form types
if(!in_array($formType, ['contact', 'subscribe', 'visit', 'waiting-list', 'membership'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid form type']);
    exit;
}

// Add case for membership
case 'membership':
    $result = $formProcessor->processMembershipForm((object)$postData);
    break;
```

#### Configuration Required

Add to `site/config.php`:

```php
// Member onboarding email recipients
$config->info_email = 'info@bioco.ch';
$config->intranet_email = 'intranet@bioco.ch';

// Payment slip configuration
$config->payment_account = 'CH80 0839 0032 9330 1010 5';
$config->payment_recipient_name = 'Gemüsegenossenschaft biocò';
$config->payment_recipient_address = 'Allmendstrasse 39b';
$config->payment_recipient_zip = '5400';
$config->payment_recipient_city = 'Baden';
```

### Frontend Components

**Membership Form:** `frontend/components/forms/MembershipForm.tsx`

- Multi-step form (7 steps)
- Client-side validation
- Submits to `/api/forms/membership`
- Redirects to `/anmeldung/danke` on success

**API Route:** `frontend/app/api/forms/membership/route.ts`

- Proxies request to ProcessWire `/api/forms/membership`
- Returns success/error response

**Thank You Page:** `frontend/app/anmeldung/danke/page.tsx`

- Confirms submission
- Explains next steps (payment, pickup, work days)

### Admin Workflow

**After Receiving Member Registration:**

1. Check email at `info@bioco.ch` and `intranet@bioco.ch`
2. Review member details and payment slip
3. Log in to ProcessWire admin → Pages → Member Registrations
4. Verify member information
5. Wait for payment confirmation (bank account monitoring)
6. Once paid:
   - Mark member as "active" in ProcessWire
   - Add to internal member database/intranet
   - Send welcome package (depot instructions, work schedule, etc.)
7. Schedule first work day with member

**ProcessWire Admin Page:**

- Template: `member-registration`
- Fields: All form data + status field (`pending`, `paid`, `active`)
- Admin can update status and add internal notes

### Testing Checklist

**Staging Environment:**

- [ ] Test membership form with all abo types
- [ ] Verify payment slip PDF generates correctly
- [ ] Check QR code scans with banking app
- [ ] Confirm email to member arrives with PDF attachment
- [ ] Confirm email to info@bioco.ch arrives with PDF
- [ ] Confirm email to intranet@bioco.ch arrives with PDF
- [ ] Test with different payment types (yearly/quarterly)
- [ ] Verify amount calculation for additional shares
- [ ] Test validation for all required fields
- [ ] Verify thank you page displays correctly

**Production Validation:**

- [ ] Monitor first real registration closely
- [ ] Verify all 3 emails arrive (member, info, intranet)
- [ ] Test payment slip with real bank transfer
- [ ] Confirm ProcessWire admin page shows registration

---

## Staging Migration Checklist

Complete checklist for deploying to `staging.bioco.ch`.

### Pre-Migration

- [ ] **Backup current staging database**
  - cPanel → phpMyAdmin → `bioco_staging` → Export → Save SQL file
  - Store backup with date: `bioco_staging_backup_YYYY-MM-DD.sql`

- [ ] **Backup staging files**
  - cPanel → Backups → Download Home Directory Backup
  - Or use File Manager to download `public_html/bioco_staging/`

- [ ] **Review Git branches**
  - Ensure `develop` branch is clean and up to date
  - All commits pushed to GitHub
  - No uncommitted changes locally

- [ ] **Test build locally**
  - `cd frontend && npm run build`
  - Verify build completes without errors
  - Check for TypeScript errors, missing imports, etc.

- [ ] **Verify environment variables documented**
  - Review staging env vars list above
  - Prepare API tokens and credentials
  - Have Matomo site ID ready

### ProcessWire Deployment

- [ ] **Deploy code via cPanel Git**
  - cPanel → Git™ Version Control → Manage → Bioco Staging
  - Click "Update from Remote"
  - Or use "Pull or Deploy" → HEAD → develop

- [ ] **Run `composer install`** (if PHP dependencies were updated)
  - cPanel → Terminal
  - `cd public_html/bioco_staging`
  - `composer install --no-dev`

- [ ] **Verify `.htaccess`**
  - Check PHP 8.2 handler is present (see Configuration Files section)
  - Verify URL rewriting rules
  - Check security headers

- [ ] **Set correct file permissions**
  - cPanel → File Manager
  - `bioco_staging/` directory: `755`
  - `bioco_staging/.htaccess` file: `644`
  - All `.php` files: `644`
  - `bioco_staging/site/` directory: `755` (temporarily `777` during install, then back to `755`)

- [ ] **Update `site/config.php`**
  - Verify database credentials (staging database)
  - Check site URL: `staging.bioco.ch`
  - Verify email settings
  - Confirm Matomo settings (site ID = 1)
  - Set `$config->debug = true;` for staging

- [ ] **Test all API endpoints**
  - `/api/events` - Returns JSON with upcoming/past events
  - `/api/forms` - POST test (use Postman or curl)
  - `/api/instagram` - Returns Instagram posts
  - `/api/doi` - GET with test token
  - `/api/navigation` - Returns site structure
  - `/api/pages` - Returns page content

- [ ] **Verify custom modules load**
  - ProcessWire admin → Modules → Refresh
  - Check for error messages
  - Verify all custom modules show "Installed" status:
    - DOIManager
    - FormProcessor
    - InstagramSync
    - MatomoTracker
    - EventSetup

- [ ] **Run EventSetup bootstrap**
  - Should run automatically on first page load
  - Verify in ProcessWire admin → Setup → Fields:
    - `event_status`, `event_start`, `event_end`, etc. exist
  - Verify in ProcessWire admin → Setup → Templates:
    - `event` template exists

- [ ] **Test ProcessWire admin login**
  - Navigate to `https://staging.bioco.ch/processwire/`
  - Log in with admin credentials
  - Verify dashboard loads
  - Check for error messages in admin

### Frontend Deployment

- [ ] **Merge to develop branch**
  - GitHub Desktop or `git checkout develop && git merge feature-branch`
  - Push to GitHub: `git push origin develop`

- [ ] **Trigger Vercel deployment**
  - Deployment triggers automatically on push to `develop`
  - Or manually trigger in Vercel dashboard → Deployments → Redeploy

- [ ] **Verify environment variables in Vercel**
  - Vercel dashboard → Project → Settings → Environment Variables
  - Check all staging variables are set (see Frontend Configuration section)
  - Verify scope: "Preview (develop)" branch only

- [ ] **Test build logs**
  - Vercel dashboard → Deployments → Latest → View Details
  - Check for build errors or warnings
  - Verify all API calls during build succeed

- [ ] **Verify custom domain routing**
  - Vercel dashboard → Project → Settings → Domains
  - Confirm `staging.bioco.ch` is assigned to `develop` branch
  - Check DNS records in cPanel (CNAME should exist)

- [ ] **Test SSL certificate**
  - Navigate to `https://staging.bioco.ch`
  - Check browser shows secure connection (padlock icon)
  - Verify certificate is valid (Let's Encrypt)

### Integration Testing

- [ ] **Homepage loads and displays events**
  - Visit `https://staging.bioco.ch`
  - Verify hero banner loads
  - Check "Nächste Events" section shows upcoming events
  - Verify event cards have images, dates, and descriptions

- [ ] **Saisonkalender displays correctly**
  - Navigate to `/gemuese`
  - Verify seasonal calendar renders
  - Check vegetable images load
  - Verify current month is highlighted

- [ ] **All forms submit successfully**
  - Test contact form: Fill out and submit
  - Test newsletter: Subscribe with test email
  - Test visit day: Register for Schnuppertag
  - Test waiting list: Submit with test data
  - **Test membership form:** Complete all 7 steps and submit
  - Check admin receives notification emails

- [ ] **Membership onboarding flow works**
  - Complete membership registration at `/anmeldung`
  - Verify payment slip PDF is generated
  - Check member receives confirmation email with PDF attachment
  - Verify info@bioco.ch receives notification with PDF
  - Verify intranet@bioco.ch receives notification with PDF
  - Confirm payment slip has correct IBAN and member details

- [ ] **DOI email flow works**
  - Submit newsletter subscription with real email address
  - Check email inbox for DOI confirmation email
  - Click confirmation link
  - Verify confirmation page displays success message

- [ ] **Event signup form appears for upcoming events**
  - Click on an upcoming event
  - Verify signup form is visible in modal
  - Test form submission

- [ ] **Event signup form hidden for past events**
  - Manually change an event to "past" status in ProcessWire admin
  - Reload event modal on frontend
  - Verify signup form is hidden

- [ ] **Instagram feed loads**
  - Navigate to `/aktuelles`
  - Check Instagram posts display
  - Verify images load
  - Click "Auf Instagram ansehen" link

- [ ] **Matomo tracking fires**
  - Open browser dev tools → Network tab
  - Navigate through site
  - Look for requests to `matomo.bioco.ch`
  - Verify Matomo dashboard shows pageviews

- [ ] **Navigation works**
  - Test utility navigation (Standorte, Kontakt, Intranet)
  - Test primary navigation (Wir, Gemüse, Mitmachen, Abos, Aktuelles)
  - Verify sticky behavior on scroll
  - Check all links resolve correctly

- [ ] **Mobile menu functions**
  - Resize browser to mobile width (< 768px)
  - Click hamburger icon
  - Verify menu opens with all items
  - Test all links

- [ ] **Depot map displays correctly**
  - Navigate to `/standorte-depots`
  - Verify Leaflet map loads
  - Check all depot markers are placed correctly
  - Click markers, verify popups show correct info
  - Test "Zur Website" links

- [ ] **All internal links resolve**
  - Use browser dev tools to check for 404 errors
  - Click through all navigation items
  - Test footer links
  - Verify breadcrumbs (if implemented)

### Content Validation

- [ ] **All pages render without 404s**
  - Manually visit each main page:
    - `/` (Homepage)
    - `/wir` (About)
    - `/gemuese` (Vegetables)
    - `/abos` (Subscriptions)
    - `/mitmachen` (Get Involved)
    - `/standorte-depots` (Locations)
    - `/aktuelles` (News & Events)
    - `/solawi` (Solidarische Landwirtschaft)
  - Check browser console for errors

- [ ] **Images load correctly**
  - Verify hero images display
  - Check team member photos (Hofteam)
  - Verify depot images
  - Check event images in modals

- [ ] **Videos play in event galleries**
  - Open an event with uploaded videos
  - Click video thumbnail
  - Verify video plays in browser

- [ ] **Meta tags and SEO data present**
  - View page source for each page
  - Check `<title>` tag is correct
  - Verify `<meta name="description">` exists
  - Check Open Graph tags (`og:title`, `og:description`, `og:image`)

- [ ] **Structured data validates**
  - Use [Google Rich Results Test](https://search.google.com/test/rich-results)
  - Test homepage for LocalBusiness schema
  - Verify no errors or warnings

- [ ] **Sitemap.xml generates**
  - Visit `https://staging.bioco.ch/sitemap.xml`
  - Verify all main pages are listed
  - Check `<lastmod>` dates are recent
  - Verify `<priority>` and `<changefreq>` are set

- [ ] **Robots.txt configured**
  - Visit `https://staging.bioco.ch/robots.txt`
  - Verify content:
    ```
    User-agent: *
    Allow: /
    Disallow: /admin/
    Disallow: /processwire/

    Sitemap: https://staging.bioco.ch/sitemap.xml
    ```

---

## Production Migration Checklist

Repeat staging checklist with production-specific considerations.

### Additional Production Steps

- [ ] **Schedule maintenance window**
  - If migrating from existing site: coordinate downtime
  - Communicate schedule to team
  - Prepare "Under Maintenance" page if needed

- [ ] **Notify team of deployment**
  - Send email to stakeholders
  - Provide deployment timeline
  - List expected changes

- [ ] **Merge develop → main**
  - Only after staging is fully tested and approved
  - Use GitHub pull request for code review
  - Merge via GitHub Desktop or command line:
    ```bash
    git checkout main
    git merge develop
    git push origin main
    ```

- [ ] **Deploy ProcessWire to `public_html/`**
  - cPanel → Git™ Version Control → Manage → Bioco Live
  - Click "Update from Remote" → main branch
  - Follow all ProcessWire Deployment steps from staging checklist

- [ ] **Update `site/config.php` with production credentials**
  - Database: `bioco_live`
  - URL: `www.bioco.ch`
  - Matomo site ID: `2`
  - **Disable debug mode:** `$config->debug = false;`

- [ ] **Deploy frontend** (Vercel auto-deploys main → www.bioco.ch)
  - Merge triggers automatic deployment
  - Monitor Vercel dashboard for build completion
  - Verify environment variables for production scope

- [ ] **Verify production environment variables**
  - Vercel dashboard → Settings → Environment Variables
  - Ensure production variables are set for "Production (main)" branch
  - Check API URLs point to `www.bioco.ch`

- [ ] **Test all critical user flows**
  - Repeat Integration Testing checklist (above)
  - Focus on forms, payments (if applicable), and signup flows
  - Test from multiple devices and browsers

- [ ] **Monitor error logs for 24 hours**
  - ProcessWire admin → Setup → Logs
  - cPanel → Error Logs
  - Vercel dashboard → Logs
  - Watch for unexpected errors

- [ ] **Update DNS if needed**
  - Should already be configured per [existing docs](https://wgusta.github.io/bioco-doku/technical_setup_processwire/)
  - Verify A record or CNAME for `www.bioco.ch`
  - Verify CNAME for `bioco.ch` → `www.bioco.ch`

### Post-Production

- [ ] **Verify Google Search Console integration**
  - Add property for `www.bioco.ch` in Search Console
  - Submit sitemap: `https://www.bioco.ch/sitemap.xml`
  - Request indexing for key pages

- [ ] **Confirm Matomo tracking in production**
  - Visit Matomo dashboard
  - Check site ID 2 (production) is receiving traffic
  - Verify events are tracked correctly

- [ ] **Test performance**
  - Use [Lighthouse](https://developers.google.com/web/tools/lighthouse) (Chrome DevTools)
  - Target scores:
    - Performance: 90+
    - Accessibility: 95+
    - Best Practices: 95+
    - SEO: 100
  - Address any critical issues

- [ ] **Verify Vercel analytics**
  - Vercel dashboard → Analytics
  - Check traffic is being recorded
  - Monitor performance metrics

- [ ] **Document any issues encountered**
  - Update this migration guide with lessons learned
  - Document any workarounds or fixes applied
  - Share with team

---

## Rollback Procedures

Emergency procedures if deployment fails.

### Frontend Rollback (Vercel)

**Option 1: Revert in Vercel Dashboard**

1. Vercel dashboard → Deployments
2. Find last known good deployment
3. Click "⋯" → "Promote to Production"
4. Confirm promotion

**Option 2: Git Revert**

1. Identify problematic commit:
   ```bash
   git log --oneline
   ```

2. Revert commit:
   ```bash
   git revert <commit-hash>
   git push origin main
   ```

3. Vercel auto-deploys reverted code

**Time to Rollback:** ~2-5 minutes

### Backend Rollback (ProcessWire)

**Step 1: Restore Database**

1. cPanel → phpMyAdmin
2. Select production database (`bioco_live`)
3. Import backup SQL file created before migration
4. Execute import

**Step 2: Revert Code**

1. cPanel → Git™ Version Control → Manage → Bioco Live
2. Use "Reset HEAD" to specific commit
3. Or manually revert via Terminal:
   ```bash
   cd public_html
   git reset --hard <previous-commit-hash>
   ```

**Step 3: Restore Files**

If critical files were modified outside Git:

1. cPanel → Backups
2. Download backup from before migration
3. Extract and replace affected files via File Manager

**Time to Rollback:** ~10-20 minutes (depending on database size)

### Database Rollback

**Pre-Migration Requirements:**

- Always create timestamped SQL dumps before each migration:
  ```
  bioco_live_pre-migration_2025-12-02_14-30.sql
  ```

**Restore Procedure:**

1. cPanel → phpMyAdmin
2. Select database
3. SQL tab → Run:
   ```sql
   DROP DATABASE bioco_live;
   CREATE DATABASE bioco_live;
   ```
4. Import → Choose backup file
5. Execute import
6. Restart PHP-FPM (if available) or wait for cache clear

**Alternative (Command Line):**

```bash
mysql -u bioco_YOURUSERNAME -p bioco_live < backup.sql
```

**Time to Rollback:** ~5-15 minutes (depending on database size)

---

## Monitoring & Maintenance

### Health Checks

Regular checks to ensure system is functioning correctly.

- [ ] **`/api/events` returns 200**
  - Curl test: `curl -I https://www.bioco.ch/api/events`
  - Verify HTTP 200 status
  - Check response time (should be < 500ms)

- [ ] **Frontend builds succeed**
  - Monitor Vercel dashboard for build failures
  - Set up Vercel Slack/email notifications

- [ ] **No console errors on key pages**
  - Periodically check browser console on:
    - Homepage, Aktuelles, Forms
  - Fix any JavaScript errors immediately

- [ ] **Forms submit without errors**
  - Test each form monthly
  - Verify emails arrive
  - Check ProcessWire logs for submission records

- [ ] **Cron jobs running**
  - ProcessWire LazyCron for event automation
  - Check ProcessWire admin → Setup → Logs → LazyCron
  - Verify events change to "past" status automatically

### Regular Tasks

**Weekly:**

- Review error logs
  - ProcessWire: Admin → Setup → Logs
  - cPanel: Error Logs
  - Vercel: Dashboard → Logs
- Check form submissions for spam
- Monitor site uptime (use UptimeRobot or similar)

**Monthly:**

- Review event status automation
  - Verify past events are correctly marked
  - Check signup forms are disabled for past events
- Test backup restoration (on staging)
- Review Matomo analytics
- Check for ProcessWire core updates

**Quarterly:**

- Update ProcessWire core (if updates available)
  - Test on staging first
- Update Node.js dependencies (`npm outdated && npm update`)
- Review and update documentation
- Security audit (check for vulnerabilities)

**Annually:**

- Renew domain registration
- Review SSL certificates (auto-renew via Let's Encrypt)
- Update content (team photos, Schnuppertag dates)
- Review and archive old events

---

## Security Considerations

### .gitignore Configuration

**Critical Files to Ignore:**

```
# ProcessWire
/site/config.php
/site/assets/
/site/sessions/
/site/cache/
/site/logs/

# Environment
.env
.env.local
.env.production

# Backups
*.sql
*.sql.gz
*.backup
```

**Why:**

- `site/config.php` contains database credentials
- `site/assets/` contains user uploads (large files, not needed in Git)
- Env files contain API keys and secrets

### API Security

- **Input Validation:** All API endpoints sanitize inputs using ProcessWire's Sanitizer
- **CORS Headers:** Configured to allow requests only from Vercel domains
- **Rate Limiting:** Consider implementing for production (prevent abuse)
- **API Tokens:** Required for write operations (forms, etc.)

### Deploy Keys

- GitHub deploy key has **read-only** access by default
- For cPanel Git pulling: enable "Allow write access" (required for cPanel tool)
- Key is stored only on server (`~/.ssh/id_rsa`)
- Never commit private keys to Git

### Database Users

- Database user has minimal privileges (only on bioco_* databases)
- Separate users for staging and production (if possible)
- Strong passwords (20+ characters, random)

### SSL Certificates

- Automatically provisioned by Let's Encrypt (via Vercel for frontend, cPanel for backend)
- Auto-renewal enabled (no manual intervention required)
- Verify renewal every 60 days

### ProcessWire Admin

- **Strong Passwords:** 16+ characters, mixed case, numbers, symbols
- **Two-Factor Authentication:** Enable if available (via module)
- **Admin URL:** Consider renaming `/processwire/` to obscure path
- **User Roles:** Use `redaktion` role for editors (limited permissions)

### File Permissions

**Correct Permissions:**

- Directories: `755` (rwxr-xr-x)
- PHP files: `644` (rw-r--r--)
- `.htaccess`: `644`
- **Never use `777`** except temporarily during ProcessWire installation (then revert to `755`)

---

## Event Management System

Complete guide to the event lifecycle automation.

### Overview

Events automatically transition from "upcoming" to "past" status based on their end date. The signup form is hidden for past events, and editors can upload recap media (photos/videos).

### Data Model

**Event Template:** `event`

**Parent Page:** `/events/` (auto-created by EventSetup)

**Fields:**

| Field                  | Type     | Required | Purpose                             |
| ---------------------- | -------- | -------- | ----------------------------------- |
| `title`                | Text     | ✓        | Event name (e.g., "Schnuppertag")   |
| `event_status`         | Options  | ✓        | `upcoming` or `past`                |
| `event_start`          | DateTime | ✓        | Event start time                    |
| `event_end`            | DateTime | ✓        | Event end time (triggers automation)|
| `event_location`       | Text     | ✓        | Location name (e.g., "Geisshof")    |
| `event_summary`        | Textarea | ✓        | Short teaser (shown on cards)       |
| `body`                 | Textarea | ✓        | Full description (shown in modal)   |
| `event_media`          | File     | -        | Photos/videos (jpg, png, webp, mp4) |
| `event_signup_enabled` | Checkbox | -        | Show/hide signup form               |
| `event_signup_notes`   | Textarea | -        | Instructions for signup             |

### API Contract

**Endpoint:** `/api/events`

**Method:** GET

**Response:**

```json
{
  "success": true,
  "generatedAt": "2025-12-02T10:00:00+01:00",
  "upcoming": [
    {
      "id": 1234,
      "title": "Schnuppertag",
      "description": "Kurzbeschreibung für Karte",
      "fullDescription": "<p>Vollständige Beschreibung mit HTML</p>",
      "location": "Geisshof",
      "startDate": "2026-04-28T12:00:00Z",
      "endDate": "2026-04-28T15:00:00Z",
      "dateLabel": "28.04.2026",
      "timeLabel": "14:00 - 17:00 Uhr",
      "signupEnabled": true,
      "signupNotes": "Treffpunkt 13:45 am Hoftor",
      "status": "upcoming",
      "media": [],
      "url": "https://www.bioco.ch/events/schnuppertag-28-04-2026/",
      "parentTitle": "Events"
    }
  ],
  "past": [
    {
      "id": 1233,
      "title": "Schnuppertag",
      "description": "...",
      "fullDescription": "...",
      "location": "Geisshof",
      "startDate": "2025-10-24T12:00:00Z",
      "endDate": "2025-10-24T15:00:00Z",
      "dateLabel": "24.10.2025",
      "timeLabel": "14:00 - 17:00 Uhr",
      "signupEnabled": false,
      "signupNotes": "",
      "status": "past",
      "media": [
        {
          "url": "https://www.bioco.ch/site/assets/files/1234/schnuppertag-2025-01.jpg",
          "type": "image",
          "description": "Erntearbeit"
        },
        {
          "url": "https://www.bioco.ch/site/assets/files/1234/schnuppertag-2025-video.mp4",
          "type": "video",
          "description": ""
        }
      ],
      "url": "https://www.bioco.ch/events/schnuppertag-24-10-2025/"
    }
  ]
}
```

### Automation Hooks

**EventSetup Class:** `site/classes/EventSetup.php`

**LazyCron Hook:**

```php
$this->addHook('LazyCron::everyDay', $this, 'updateEventStatuses');
```

**Runs:** Once per day when the site is accessed after midnight.

**Logic:**

1. Find all events with `event_status = "upcoming"` and `event_end < now()`
2. Change status to `"past"`
3. Disable signup: `event_signup_enabled = false`
4. Save event

**Manual Trigger:**

Editors can manually change status at any time via ProcessWire admin.

### Frontend Behavior

**Upcoming Events:**

- Displayed in "Nächste Events" block (homepage, Aktuelles page)
- Show signup form in modal (if `signupEnabled = true`)
- Display date, time, location, summary
- No media gallery (media is optional)

**Past Events:**

- Displayed in "Vergangene Events" block (full width, below upcoming)
- No signup form (always hidden)
- Display recap media gallery (if media uploaded)
- Cards show "Mehr erfahren" link instead of "Anmelden"

**Event Modal:**

- Full description (HTML from `body` field)
- Media gallery (images in grid, videos with play button)
- Signup form (conditionally rendered)

**Data Fetching:**

- `useEventsFeed` hook in Next.js
- Fetches from `/api/events` (proxied to ProcessWire)
- 5-minute cache (`revalidate: 300`)
- Fallback to empty arrays if API fails

### Editor Workflow

**Creating a New Event:**

1. ProcessWire admin → Pages → Events → Add New
2. Select template: `event`
3. Fill required fields:
   - Title: "Schnuppertag" (or other event name)
   - Status: `upcoming`
   - Start Date: `28.04.2026 14:00`
   - End Date: `28.04.2026 17:00`
   - Location: `Geisshof`
   - Summary: Brief description (2-3 sentences)
   - Body: Full description (use WYSIWYG editor)
4. Enable signup:
   - Check "Signup Enabled"
   - Add signup notes (optional)
5. Save

**Adding Media (Before or After Event):**

1. Edit event page
2. Upload images/videos to "Event Media" field
3. Add descriptions (alt text)
4. Save

**After Event (Manual):**

1. Edit event page
2. Upload recap photos/videos
3. Change status to `past` (or wait for automatic change)
4. Save

**After Event (Automatic):**

- System automatically changes status the day after `event_end`
- Editor only needs to upload media

---

## Reference Links

- **Existing Setup Docs:** https://wgusta.github.io/bioco-doku/technical_setup_processwire/
- **ProcessWire Forums:** https://processwire.com/talk/
- **ProcessWire Docs:** https://processwire.com/docs/
- **Vercel Deployment Docs:** https://vercel.com/docs
- **Next.js Docs:** https://nextjs.org/docs
- **Project Repository:** https://github.com/wgusta/bioco-web-project (replace with actual URL)
- **cPanel Documentation:** https://docs.cpanel.net/
- **Let's Encrypt:** https://letsencrypt.org/

---

## Maintenance Notes

Keep this document updated after each migration or when new features are added.

**Last Updated:** 2025-12-02

**Change Log:**

- 2025-12-02: Initial comprehensive migration guide created
- (Future updates here)

**Contributors:**

- Initial documentation based on ProcessWire Events setup
- Expanded with full migration checklists and backend documentation

---

**End of Migration Guide**

