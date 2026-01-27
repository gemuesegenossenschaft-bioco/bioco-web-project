<?php namespace ProcessWire;

/**
 * Configuration Example for bioco.ch MVP
 *
 * Copy relevant settings to your site/config.php file
 * This file serves as documentation and example only
 */

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
