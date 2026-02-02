# ProcessWire CMS Setup Guide

Complete setup for events API and email configuration with newsletter@bioco.ch

---

## Step 1: Upload API Endpoint

**File:** `api-events.php` → Upload to server as `site/api/events.php`

```bash
# Via SCP
scp cms/api-events.php user@cms.bioco.ch:/path/to/processwire/site/api/events.php

# Or via cPanel File Manager:
# 1. Navigate to site/api/
# 2. Upload api-events.php
# 3. Rename to events.php
```

**Verify:**
- Visit: `https://cms.bioco.ch/api/events.php`
- Should return JSON with events

---

## Step 2: Configure Email Settings

**Edit:** `site/config.php`

Add/update these lines:

```php
// Email configuration
$config->email_from = 'hallo@bioco.ch';
$config->email_from_name = 'biocò Gemüsegenossenschaft';
$config->admin_email = 'admin@bioco.ch';

// Newsletter-specific configuration
$config->newsletter_email = 'newsletter@bioco.ch';
```

**Purpose:**
- General admin emails → `admin@bioco.ch`
- Newsletter subscriptions ONLY → `newsletter@bioco.ch`
- Event signups → `admin@bioco.ch` (or keep existing config)

---

## Step 3: Update Form Processor (if exists)

**File:** `site/modules/FormProcessor/FormProcessor.module.php`

Update newsletter subscription handler:

```php
public function processNewsletterSubscription($post) {
    // ... validation code ...

    // Send to newsletter@bioco.ch for newsletter signups ONLY
    $mail = wireMail();
    $mail->to($this->config->newsletter_email ?: 'newsletter@bioco.ch');
    $mail->from($this->config->email_from, $this->config->email_from_name);
    $mail->subject('Neue Newsletter-Anmeldung');
    $mail->body($this->buildNewsletterEmail($data));
    $mail->send();

    return ['success' => true];
}
```

**Event signups remain unchanged:**
```php
public function processEventSignup($post) {
    // Send to admin_email (default)
    $mail->to($this->config->admin_email);
    // ...
}
```

---

## Step 4: Test Event Signup Flow

1. **Create test event:**
   - Admin → Pages → Events → Add New
   - Enable signup
   - Save & Publish

2. **Submit signup form:**
   - Visit event on frontend
   - Fill out signup form
   - Submit

3. **Check email:**
   - newsletter@bioco.ch should receive notification
   - Contains: event name, user details, date/time

---

## Step 5: File Permissions

Ensure correct permissions:

```bash
chmod 644 site/api/events.php
chmod 644 site/config.php
chmod 755 site/api/
```

---

## Step 6: Test API Endpoint

### Test 1: Basic Response

```bash
curl https://cms.bioco.ch/api/events.php
```

Should return:
```json
{
  "success": true,
  "generatedAt": "2026-02-01T...",
  "upcoming": [...],
  "past": [...]
}
```

### Test 2: With Event Data

Create an event in admin, then:

```bash
curl https://cms.bioco.ch/api/events.php | jq '.upcoming[0]'
```

Should show your event details.

---

## Troubleshooting

### API returns empty arrays

**Check:**
1. Events exist? (Admin → Pages → Events)
2. Events published? (not draft)
3. Template is `event`? (not basic-page)
4. event_start and event_end filled?

### Email not sending

**Check:**
1. `site/config.php` has email settings?
2. PHP mail() function working on server?
3. Check ProcessWire logs: Admin → Setup → Logs
4. Test with simple PHP mail:
   ```php
   mail('newsletter@bioco.ch', 'Test', 'Test message');
   ```

### White screen on /api/events.php

**Check:**
1. File exists at `site/api/events.php`?
2. File permissions `644`?
3. Enable debug: `$config->debug = true;` in config.php
4. Check error logs in cPanel

### Events not showing on frontend

**Check:**
1. API endpoint works?
2. Frontend env vars set in Vercel?
   - `PROCESSWIRE_API_URL=https://cms.bioco.ch/api`
3. Wait 5 minutes (cache)
4. Frontend redeployed after env var change?

---

## Summary Checklist

- [ ] Upload `api-events.php` to `site/api/events.php`
- [ ] Update `site/config.php` with email settings
- [ ] Set file permissions (644 for PHP files)
- [ ] Test API: `https://cms.bioco.ch/api/events.php`
- [ ] Create test event in admin
- [ ] Verify event appears in API response
- [ ] Test event signup form
- [ ] Verify email received at newsletter@bioco.ch
- [ ] Update FormProcessor module (if needed)
- [ ] Test frontend integration

---

## Quick Commands

```bash
# SSH to server
ssh user@cms.bioco.ch

# Upload API file
scp cms/api-events.php user@cms.bioco.ch:/path/to/site/api/events.php

# Set permissions
chmod 644 site/api/events.php

# Test API
curl https://cms.bioco.ch/api/events.php | jq .

# Check ProcessWire logs
tail -f site/assets/logs/errors.txt
```

---

## Email Configuration Reference

Newsletter subscriptions ONLY go to: **newsletter@bioco.ch**

Email routing:
- **Newsletter subscriptions** → newsletter@bioco.ch
- **Event signups** → admin@bioco.ch
- **Contact form** → admin@bioco.ch
- **Waiting list** → admin@bioco.ch
- **Other forms** → admin@bioco.ch

Configure in `site/config.php`:
```php
$config->email_from = 'hallo@bioco.ch';
$config->admin_email = 'admin@bioco.ch';
$config->newsletter_email = 'newsletter@bioco.ch';  // Newsletter only
```

---

**Need help?** Check ProcessWire logs at Admin → Setup → Logs

