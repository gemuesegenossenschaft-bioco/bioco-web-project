# Bioco CMS Integration Guide

This guide explains how to complete the ProcessWire CMS integration for the Bioco website.

## Architecture Overview

```
┌─────────────────────┐     HTTPS/JSON      ┌─────────────────────┐
│                     │ ◄─────────────────► │                     │
│   ProcessWire CMS   │    X-API-Key Auth   │   Next.js Frontend  │
│   (cms.bioco.ch)    │                     │   (Vercel)          │
│                     │                     │                     │
│   ┌───────────────┐ │                     │ ┌─────────────────┐ │
│   │ api.php       │ │                     │ │ processwire.ts  │ │
│   │ (Unified API) │ │                     │ │ (API Client)    │ │
│   └───────────────┘ │                     │ └─────────────────┘ │
└─────────────────────┘                     └─────────────────────┘
```

## Step 1: Configure cms.bioco.ch Subdomain

1. **DNS Setup**: Add DNS record for `cms.bioco.ch` pointing to your hosting
2. **SSL Certificate**: Install Let's Encrypt certificate for `cms.bioco.ch`
3. **ProcessWire**: Install or move ProcessWire to the subdomain

## Step 2: Run the Setup Script

1. In ProcessWire admin, go to Setup → Templates → Add New
2. Create a template named `api-setup` 
3. Create a page with this template (e.g., `/setup/`)
4. Visit the page to run the setup script
5. Delete the page after setup completes

The setup script (`site/templates/api-setup.php`) will create:
- All required fields (hero_headline, section_title, etc.)
- All required templates (api, homepage_content, page_content, etc.)
- Base page structure (/api/, /content/, /content/homepage/, etc.)

## Step 3: Configure the API Template

After running setup, manually configure the `api` template:

1. Go to Setup → Templates → api → Files
   - Check "Disable automatic prepend of file: `_init.php`"
   - Check "Disable automatic append of file: `_main.php`"

2. Go to Setup → Templates → api → URLs
   - Check "Allow URL Segments"
   - Set maximum segments to 4

## Step 4: Add API Key to Config

Add to `site/config.php`:

```php
// API Key for authentication
$config->apiKey = 'bioco_2026_YOUR_SECURE_KEY_HERE';

// Allowed CORS origins
$config->allowedOrigins = [
    'https://bioco.ch',
    'https://www.bioco.ch',
    'http://localhost:3000',
];
```

Generate a secure key: `php -r "echo bin2hex(random_bytes(32));"`

## Step 5: Run Content Migration Script

The migration script automatically creates all content pages with pre-populated sections.

### Setup Migration Template

1. Rename file on server:
   ```bash
   mv site/templates/migrate-pages.php site/templates/migrate.php
   ```

2. In ProcessWire Admin:
   - Go to **Setup** → **Templates** → **Add New Template**
   - Select `migrate` from dropdown
   - Save

3. Create migration page:
   - Go to **Pages** → **Add New** (under root)
   - Title: `migrate`
   - Template: `migrate`
   - Save + Publish

### Run Migration

**First run (create pages):**
```
https://cms.bioco.ch/migrate/
```

**Refresh existing pages:**
```
https://cms.bioco.ch/migrate/?overwrite=1
```

### Pages Created (15 total)

| Page | Sections | Components |
|------|----------|------------|
| mitmachen | 6 | schnuppertage |
| gemuese | 5 | saisonkalender, gallery |
| solawi | 6 | - |
| abos | 6 | - |
| aktuelles | 4 | schnuppertage, events_feed (Neuigkeiten: child pages with template news_item) |
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

**Note:** Homepage is managed separately via `homepage_content` template.

### After Migration

1. Delete the `migrate` page in ProcessWire admin
2. Edit pages to add images and fine-tune content
3. Test pages on frontend: `https://bioco.ch/{pagename}`

## Step 6: Manual Content Editing

### Homepage Content
1. Go to Pages → content → homepage
2. Fill in:
   - `hero_headline`: Main hero title
   - `hero_subtitle`: Subtitle text
   - `hero_image`: Hero background image
   - `content_sections`: Add sections for Willkommen, Gemeinsam, Kennenlernen

### Neuigkeiten (News Items) on Aktuelles Page
1. Go to Pages → content → aktuelles
2. Click **Add New** (child page)
3. Select template `news_item`
4. Fill in: `title`, `summary` (teaser), `body` (full content, HTML allowed)
5. Optional: `hero_image` or `card_image` for preview
6. Save. News items appear in Neuigkeiten section on /aktuelles and homepage

### Page Content (Post-Migration)
1. Go to Pages → content → {pagename}
2. Edit sections using the `content_sections` repeater
3. Add images to sections

### Flexible Section Layouts
Each `content_sections` item supports flexible layouts and media:

- `section_layout`: split_media_text, split_text_media, full_width_banner, media_grid, video_embed, rich_text, component
- `section_theme`: default, muted, accent, dark
- `section_eyebrow`: small label above the title
- `section_images`: multi image grid
- `section_video_url`: YouTube, Vimeo, or MP4 URL
- `section_video_title`: optional caption
- `section_component`: special component key, e.g. contact_form, membership_form, events_feed

### Group Cards (for Mitmachen)
1. Go to Pages → content → gruppen
2. Add child pages with `group_card` template for each group

## Step 7: Configure Vercel Environment Variables

In Vercel Dashboard → Project → Settings → Environment Variables:

| Variable | Value | Environments |
|----------|-------|--------------|
| `PROCESSWIRE_BASE_URL` | `https://cms.bioco.ch` | Production, Preview |
| `PROCESSWIRE_API_KEY` | Your API key | Production, Preview |

## Step 8: Test the Integration

### Test API endpoints:
```bash
# Health check (no auth required)
curl https://cms.bioco.ch/api/health

# Content endpoints (with auth)
curl -H "X-API-Key: YOUR_KEY" https://cms.bioco.ch/api/content/hero
curl -H "X-API-Key: YOUR_KEY" https://cms.bioco.ch/api/content/homepage
curl -H "X-API-Key: YOUR_KEY" https://cms.bioco.ch/api/content/sections/mitmachen
curl -H "X-API-Key: YOUR_KEY" https://cms.bioco.ch/api/content/groups
```

### Verify in browser:
1. Deploy to Vercel
2. Visit the site and check that content loads
3. Edit content in ProcessWire
4. Wait 60 seconds (ISR revalidation)
5. Refresh and verify changes appear

## API Endpoint Reference

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/health` | GET | Health check (no auth) |
| `/api/content/hero` | GET | Homepage hero data |
| `/api/content/homepage` | GET | Full homepage (hero + sections) |
| `/api/content/sections/{page}` | GET | Sections for a specific page |
| `/api/content/pages` | GET | All public pages for static params |
| `/api/content/groups` | GET | Group cards for Mitmachen |
| `/api/content/page?path=/path` | GET | Generic page data |
| `/api/content/navigation` | GET | Site navigation |
| `/api/content/events` | GET | Events (upcoming + past) |
| `/api/content/aktuelles` | GET | News items |
| `/api/forms/{type}` | POST | Form submissions |
| `/api/doi/confirm` | GET | DOI confirmation |

## Dynamic Page Discovery

New pages added in ProcessWire automatically appear on the website:

1. **On-demand rendering**: Pages not pre-built are rendered on first request
2. **ISR caching**: After first render, pages are cached for 60 seconds
3. **Sitemap updates**: The sitemap fetches pages from CMS dynamically

No code changes or rebuilds required when adding new pages in ProcessWire.

## Fallback Behavior

If the CMS is unavailable or returns an error:
- Pages display hardcoded fallback content from `lib/fallback-content.ts`
- The site remains functional with static content
- No user-facing errors are shown

## Troubleshooting

### Images not loading
- Verify `cms.bioco.ch` is in `next.config.js` image domains
- Check image URLs are absolute (include full domain)

### API returns 401 Unauthorized
- Verify `PROCESSWIRE_API_KEY` environment variable is set
- Check the key matches `$config->apiKey` in ProcessWire

### Content not updating
- ISR revalidation is 60 seconds by default
- Force refresh: redeploy or use Vercel's "Redeploy" function

### SSL certificate errors
- Ensure Let's Encrypt certificate is valid for `cms.bioco.ch`
- Test with: `curl -I https://cms.bioco.ch`

## Files Reference

### ProcessWire
- `site/templates/api.php` - Unified API router
- `site/templates/api-setup.php` - Field/template setup script
- `site/templates/migrate.php` - Content migration script (15 pages)
- `site/config-example.php` - Configuration reference

### Next.js
- `frontend/lib/cmsClient.ts` - API client utilities
- `frontend/lib/processwire.ts` - Content fetch functions
- `frontend/lib/processwire-types.ts` - TypeScript types
- `frontend/lib/fallback-content.ts` - Fallback data
- `frontend/next.config.js` - Image domains, env vars
