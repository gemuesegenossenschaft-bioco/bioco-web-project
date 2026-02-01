# Newsletter Setup & Operations (ProcessWire)

Status: February 1, 2026
Scope: In-house newsletter using ProcessNewsletter module with double opt-in and Novatrend SMTP.
Sender: `hallo@bioco.ch` (Bioco)

## Prerequisites
- PHP CLI on the server (to run the setup script) or run via ProcessWire bootstrap elsewhere.
- WireMailSmtp module installed in ProcessWire admin.
- SMTP credentials for Novatrend (`hallo@bioco.ch` mailbox or alias must exist).
- Existing DOIManager + FormProcessor (already in repo).
- `doi-confirm` template and `/doi-confirm/` page must exist in ProcessWire.
- `form-subscribe` template and subscribe page must exist in ProcessWire.

## One-time installation
1) Install WireMailSmtp: Admin → Modules → Install → New → search "WireMailSmtp" → Download & Install.
2) Create `hallo@bioco.ch` mailbox (or alias forwarding to admin@bioco.ch) in Novatrend panel.
3) From project root run:
`php scripts/setup-newsletter.php`
This installs the ProcessNewsletter module, creates the `unsubscribe` template, and adds the `/unsubscribe/` page.
4) In Admin → Modules → Refresh; ensure "Newsletter" appears as a top-level tab.
5) Verify `doi-confirm` template + `/doi-confirm/` page exist. If not, create them manually (template file: doi-confirm.php, page under Home).
6) Set up DNS records for deliverability (see Maintenance tips).

## Email transport (Novatrend SMTP)
- Admin → Modules → WireMailSmtp:
  - Host: `mail.bioco.ch`
  - Port: `465` (SSL)
  - User: `hallo@bioco.ch`
  - Password: (set in UI)
  - Force from: leave blank (we set from in config).
- Confirm `site/config.php` has sender defaults (already set):
  - `$config->email_from = 'hallo@bioco.ch';`
  - `$config->email_from_name = 'Bioco';`
  - `$config->admin_email = 'admin@bioco.ch';`
- Create `hallo@bioco.ch` mailbox (or alias → admin@bioco.ch) in Novatrend panel.

## Cron / LazyCron
- Sending is processed every 5 minutes via LazyCron. If site traffic is low, add a cron on the server:  
  `*/5 * * * * curl -s https://cms.bioco.ch/?LazyCron=every5Minutes > /dev/null`
- Throttling: `$config->newsletter_batch_size = 50` (emails per 5‑minute window). Lower if Novatrend rate limits.

## Subscriber flow (double opt-in)
- Public subscribe form → FormProcessor → DOIManager handles confirmation email.  
- After confirmation, FormProcessor calls `ProcessNewsletter::recordSubscriber()` → subscribers stored in `newsletter_subscribers` with `status=confirmed`.  
- Unsubscribe uses tokenized links: `/unsubscribe/?token=...` (template at `site/templates/unsubscribe.php`).

## Creating & sending a campaign
1) Admin → Newsletter → “Neue Kampagne”.  
2) Fill Title (internal), Subject, optional Preheader, Intro text.  
3) Select content blocks: checkboxes list latest **Aktuelles** (`template=news_item`) and upcoming **Events** (`template=event`).  
   - Each selected page is snapshotted (title, summary, image, URL, event date) so later page edits don’t change the email.  
4) Set “Sendezeit”:  
   - Empty = Draft (editable).  
   - Future datetime = Scheduled (queue builds automatically when time passes).  
5) “Speichern” keeps draft/schedule. “Jetzt senden” (action link) builds queue immediately.  
6) Queue sends in batches via LazyCron; campaign status flips to `sent` when queue is empty.

## Templates & branding
- HTML email template: `site/templates/emails/newsletter-campaign.php`.  
  - Contains preheader, intro, cards with image/summary/CTA, and unsubscribe link.  
  - Safe to adjust colors, typography, add logo. Keep unsubscribe link intact.  
- Unsubscribe page template: `site/templates/unsubscribe.php` (simple success/error messaging).

## Data tables (MySQL)
- `newsletter_subscribers` — confirmed/pending/unsubscribed, tokens, audit fields.
- `newsletter_campaigns` — metadata, body intro, serialized content blocks, schedule.
- `newsletter_send_queue` — per-recipient send status, attempts, timestamps.

## Smoke test checklist
1) Run setup script; verify `/unsubscribe/` page loads.
2) Configure WireMailSmtp; send test email from its config screen.
3) Submit newsletter subscribe form with a test address → receive DOI email → click confirmation link → subscriber appears in Admin → Newsletter → Abonnenten with status=confirmed.
4) Create a test campaign with one Aktuelles item; click "Jetzt senden"; confirm email arrives.
5) Verify unsubscribe link in received email uses full URL (https://cms.bioco.ch/unsubscribe/?token=...) and works.
6) Verify DOI confirmation link uses full URL (https://cms.bioco.ch/doi-confirm/?token=...).
7) Check email headers contain `List-Unsubscribe` header.

## DNS (deliverability)
Verify these records exist for bioco.ch:
- **SPF:** `v=spf1 include:_spf.novatrend.ch ~all` (adjust to Novatrend requirements)
- **DKIM:** Set up through Novatrend control panel
- **DMARC:** `v=DMARC1; p=quarantine; rua=mailto:admin@bioco.ch`
Check with: `dig bioco.ch TXT`

## Maintenance tips
- If Novatrend limits are hit, lower `$config->newsletter_batch_size` or add pauses in WireMailSmtp config.
- If traffic is very low, ensure cron ping is active to avoid stuck queues.
- Keep SPF/DKIM/DMARC records in DNS aligned with `hallo@bioco.ch` for deliverability.
- Expired DOI tokens are cleaned up automatically every hour via LazyCron.
- To disable sending temporarily, disable LazyCron trigger cron or set batch size to 0 (not recommended long-term).
