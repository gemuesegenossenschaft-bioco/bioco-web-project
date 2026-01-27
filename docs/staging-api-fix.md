# Fix API Routing on Staging Server

## Problem

API endpoints at `staging.bioco.ch/api/*` return 404 because ProcessWire's main `.htaccess` routes all requests to `index.php` before the `site/api/.htaccess` rules can apply.

## Solution

Add API routing rules to the main `.htaccess` file on the staging server.

## Steps

### 1. Access the Server

1. Log in to cPanel
2. Navigate to **File Manager**
3. Go to the ProcessWire root directory (where `.htaccess` is located)

### 2. Edit .htaccess

Open the `.htaccess` file and add the following rules **AFTER** the `RewriteEngine On` line (around line 164) but **BEFORE** the access restriction rules (section 15):

```apache
# -----------------------------------------------------------------------------------------------
# API ROUTING - Direct access to site/api/ files
# Add this section after RewriteEngine On, before access restrictions
# -----------------------------------------------------------------------------------------------

# Route /api/events to site/api/events.php
RewriteCond %{REQUEST_URI} ^/api/events/?$
RewriteRule ^api/events/?$ site/api/events.php [L,QSA]

# Route /api/pages to site/api/pages.php  
RewriteCond %{REQUEST_URI} ^/api/pages/?$
RewriteRule ^api/pages/?$ site/api/pages.php [L,QSA]

# Route /api/navigation to site/api/navigation.php
RewriteCond %{REQUEST_URI} ^/api/navigation/?$
RewriteRule ^api/navigation/?$ site/api/navigation.php [L,QSA]

# Route /api/instagram to site/api/instagram.php
RewriteCond %{REQUEST_URI} ^/api/instagram/?$
RewriteRule ^api/instagram/?$ site/api/instagram.php [L,QSA]

# Route /api/forms/* to site/api/forms.php with form_type parameter
RewriteCond %{REQUEST_URI} ^/api/forms/([a-z-]+)/?$
RewriteRule ^api/forms/([a-z-]+)/?$ site/api/forms.php?form_type=$1 [L,QSA]

# Route /api/doi to site/api/doi.php
RewriteCond %{REQUEST_URI} ^/api/doi/?$
RewriteRule ^api/doi/?$ site/api/doi.php [L,QSA]

# END API ROUTING
# -----------------------------------------------------------------------------------------------
```

### 3. Where to Add in .htaccess

The rules should go in this location:

```apache
<IfModule mod_rewrite.c>

  RewriteEngine On
  
  # ===== ADD API ROUTING RULES HERE =====
  # (paste the rules from step 2)
  # ===== END API ROUTING RULES =====
  
  # ... rest of ProcessWire rules ...
```

### 4. Also Allow PHP Access in site/api/

Find this line (around line 390-391):

```apache
# Block access to any PHP, tpl or info.json files in /site/modules/ or /site-*/modules/
RewriteCond %{REQUEST_URI} (^|/)(site|site-[^/]+)/modules/.*\.(php|inc|tpl|module|info\.json)$ [NC,OR]
```

The site/api/ directory is NOT blocked by default, but verify there's no rule blocking it.

### 5. Test API Endpoints

After saving the `.htaccess` file, test the endpoints:

```bash
# Events API
curl https://staging.bioco.ch/api/events

# Pages API
curl "https://staging.bioco.ch/api/pages?path=/"

# Navigation API  
curl https://staging.bioco.ch/api/navigation
```

### Expected Results

- `/api/events` → Returns JSON: `{"success":true,"generatedAt":"...","upcoming":[],"past":[]}`
- `/api/pages?path=/` → Returns JSON with homepage data
- `/api/navigation` → Returns JSON with navigation structure

## Alternative: ProcessWire URL Segments

If the .htaccess approach doesn't work, an alternative is to create a ProcessWire page with URL segments enabled:

1. Create a page at `/api/` with a custom template
2. Enable URL segments on the template
3. Route requests in the template file based on URL segment

However, the .htaccess approach is simpler and doesn't require ProcessWire page creation.

## Troubleshooting

### Still Getting 404

1. Check if `.htaccess` was saved correctly
2. Verify Apache mod_rewrite is enabled
3. Check for syntax errors in `.htaccess` (will cause 500 error)
4. Check ProcessWire error logs: `site/assets/logs/errors.txt`

### Getting 500 Error

1. Check Apache error logs via cPanel
2. Remove the new rules to restore site
3. Add rules back one at a time to find the problematic rule

### PHP Errors in API Response

1. Enable debug mode: `$config->debug = true;` in `site/config.php`
2. Check `site/assets/logs/errors.txt`
3. Verify database connection in `site/config.php`
