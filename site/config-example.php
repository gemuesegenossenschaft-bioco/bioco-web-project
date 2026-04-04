<?php namespace ProcessWire;

/**
 * Configuration Example for bioco.ch MVP
 *
 * Copy relevant settings to your site/config.php file
 * This file serves as documentation and example only
 */

// =============================================================================
// CMS API CONFIGURATION (for cms.bioco.ch)
// =============================================================================

// API Key for authentication (X-API-Key header)
// Generate a secure random key: bin2hex(random_bytes(32))
$config->apiKey = 'bioco_2026_CHANGE_THIS_TO_SECURE_KEY';

// Allowed origins for CORS
$config->allowedOrigins = [
    'https://bioco.ch',
    'https://www.bioco.ch',
    'http://localhost:3000',  // Local development
];

// =============================================================================
// EMAIL CONFIGURATION
// =============================================================================

// Sender addresses
$config->email_from = 'noreply@bioco.ch';
$config->email_from_name = 'biocò';
$config->admin_email = 'admin@bioco.ch'; // Receives form notifications

// WireMail SMTP Configuration (requires WireMailSmtp module)
// Install: Admin → Modules → Install → WireMailSmtp
// Then configure in: Admin → Modules → Configure → WireMailSmtp
$config->wireMail = [
    'smtp_host' => 'mail.bioco.ch',      // Novatrend SMTP server
    'smtp_port' => 465,                   // SSL port
    'smtp_ssl' => true,                   // Use SSL
    'smtp_user' => 'noreply@bioco.ch',   // SMTP username
    'smtp_password' => 'YOUR_PASSWORD',   // SMTP password (set in config.php)
];

// =============================================================================
// MATOMO ANALYTICS (Cookieless, Swiss DSG compliant)
// =============================================================================

$config->matomo_enabled = true;
$config->matomo_url = 'https://your-matomo-instance.com/'; // Trailing slash required
$config->matomo_site_id = 1;

// =============================================================================
// GITHUB INTEGRATION (Content Planning Dashboard)
// =============================================================================

// Fine-grained PAT with Issues write permission
// Create at: GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens
$config->githubToken = 'github_pat_xxxxxxxxxxxx';

// Repo for creating issues (owner/repo format)
$config->githubRepo = 'wgusta/bioco-web-project';

// Deprecated: handbook lives in ProcessWire /internal-docs/; commit feed removed from Plan & Bugs.
$config->githubDocsRepo = 'wgusta/bioco-doku';

// Token for GitHub Actions: GET /api/internal-docs-export and POST /api/internal-docs-sync
// Header: X-Internal-Docs-Token (prefer env PW_INTERNAL_DOCS_SYNC_TOKEN on server)
$config->internalDocsSyncToken = getenv('PW_INTERNAL_DOCS_SYNC_TOKEN') ?: '';

// =============================================================================
// REQUIRED MODULES (install via ProcessWire admin)
// =============================================================================
//
// 1. WireMailSmtp - Required for SMTP email sending
//    Download: https://processwire.com/modules/wire-mail-smtp/
//    Install: Admin → Modules → Site → Add New → Upload module
//    Configure: Admin → Modules → Configure → WireMailSmtp
//    Test: Send test email from module config page
//
// 2. FormProcessor - Custom module (already in site/modules/)
// 3. DOIManager - Custom module (already in site/modules/)
// 4. MatomoTracker - Custom module (already in site/modules/)

// =============================================================================
// PROCESSWIRE FIELD SETUP (configure in Admin → Setup → Fields)
// =============================================================================
//
// General page fields:
// - hero_image (Image) - Max 1 file, jpg/png/webp
// - hero_subtitle (Text) - Plain text
// - logo_image (Image) - Max 1 file, svg/png
// - sidebar_content (Textarea) - CKEditor or plain
// - gallery_images (Images) - Multiple files allowed
// - footer_content (Textarea) - Plain text
// - css_variant (Text) - For CSS class names
// - body (Textarea) - Page content
//
// Event fields (template=event):
// - event_status (Options) - Values: upcoming, past
// - event_start (Datetime) - Event start date/time
// - event_end (Datetime) - Event end date/time
// - event_location (Text) - Location name
// - event_summary (Textarea) - Short description
// - event_media (Files) - Images/videos
// - event_signup_enabled (Checkbox) - Allow signups
// - event_signup_notes (Textarea) - Signup instructions

// =============================================================================
// PROCESSWIRE TEMPLATES (configure in Admin → Setup → Templates)
// =============================================================================
//
// Required templates:
// - home: hero_image, hero_subtitle, sidebar_content, gallery_images, footer_content
// - basic-page: hero_image, hero_subtitle, sidebar_content, gallery_images
// - event: all event_* fields
// - doi-confirm: (no special fields)
//
// Form templates (optional):
// - form-contact, form-subscribe, form-open-visit-day, form-waiting-list

// =============================================================================
// REQUIRED PAGES (configure in Admin → Pages)
// =============================================================================
//
// - Homepage (/) - template: home
// - DOI confirmation page (/doi-confirm/) - template: doi-confirm
// - Events parent (/veranstaltungen/) - for event pages

// =============================================================================
// USER ROLES
// =============================================================================
//
// - redaktion (Editor role) - Can edit content
// - admin (Admin role) - Full access

// =============================================================================
// CMS API TEMPLATES (for headless CMS)
// =============================================================================
//
// Template: api
// - Purpose: Unified API endpoint router
// - Files: Disable _init.php and _main.php auto-append
// - URLs: Enable URL segments (max 4)
// - Create page: /api/ with template=api
//
// Template: homepage_content
// - Purpose: Homepage content management
// - Fields: hero_headline, hero_subtitle, hero_image, image_alt, content_sections
// - Create page: /content/homepage/
//
// Template: page_content
// - Purpose: Generic content pages (mitmachen, gemuese, solawi, etc.)
// - Fields: section_title, section_text, section_image, image_alt, content_sections, body
// - Create pages: /content/mitmachen/, /content/gemuese/, /content/solawi/
//
// Template: content_parent
// - Purpose: Parent container for content pages
// - Fields: title only
// - Create page: /content/
//
// Template: group_card
// - Purpose: Group cards for Mitmachen page
// - Fields: title, card_text, card_image, image_alt
// - Create pages: /content/gruppen/elki/, /content/gruppen/kraeutergruppe/, etc.
//
// Template: news_item
// - Purpose: News/Aktuelles items
// - Fields: title, summary, body, hero_image, card_image
// - Parent: /aktuelles/

// =============================================================================
// CMS API FIELDS (for headless CMS)
// =============================================================================
//
// Text fields (plain text, decoded for JSON):
// - hero_headline (Text) - Main hero title
// - hero_subtitle (Text) - Hero subtitle
// - section_title (Text) - Section headings
// - section_id (Text) - Section identifier for CSS/JS targeting
// - button_text (Text) - Primary CTA button label
// - button_href (Text) - Primary CTA button URL
// - button_variant (Text) - Button style variant (primary/secondary)
// - button2_text (Text) - Secondary CTA button label
// - button2_href (Text) - Secondary CTA button URL
// - button2_variant (Text) - Secondary button style variant
// - card_title (Text) - Card titles
// - image_alt (Text) - Alt text for images
//
// HTML/Rich text fields (CKEditor, not decoded):
// - section_text (Textarea/CKEditor) - Section body content
// - card_text (Textarea/CKEditor) - Card descriptions
//
// Image fields:
// - hero_image (Image) - Hero background, max 1 file
// - section_image (Image) - Section images, max 1 file
// - card_image (Image) - Card images, max 1 file
//
// Repeater fields:
// - content_sections (Repeater) - Repeatable content sections
//   Contains: section_id, section_title, section_text, section_image, image_alt,
//             button_text, button_href, button_variant, button2_text, button2_href, button2_variant

// =============================================================================
// API ENDPOINT REFERENCE
// =============================================================================
//
// Base URL: https://cms.bioco.ch/api/
// Authentication: X-API-Key header
//
// Health:
// - GET /api/health - Health check (no auth required)
//
// Content:
// - GET /api/content/hero - Homepage hero data
// - GET /api/content/homepage - Full homepage content (hero + sections)
// - GET /api/content/sections/{pagename} - Sections for specific page
// - GET /api/content/groups - Group cards for Mitmachen
// - GET /api/content/page?path=/path - Generic page data
// - GET /api/content/navigation - Site navigation
// - GET /api/content/events - Events (upcoming + past)
// - GET /api/content/aktuelles?limit=10 - News items
// - GET /api/content/instagram?limit=10 - Instagram posts
//
// Forms (POST):
// - All form POST payloads must include `captchaToken` (Cloudflare Turnstile)
// - POST /api/forms/contact - Contact form
// - POST /api/forms/subscribe - Newsletter subscription
// - POST /api/forms/visit - Visit day registration
// - POST /api/forms/waiting-list - Waiting list signup
// - POST /api/forms/event-signup - Event signup
//
// DOI:
// - GET /api/doi/confirm?token=xxx - Confirm double opt-in
