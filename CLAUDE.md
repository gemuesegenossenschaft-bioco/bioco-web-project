# bioco.ch

Swiss organic vegetable cooperative. Headless CMS: ProcessWire (PHP). Frontend: Next.js 14 (standalone). Both on Novatrend cPanel.

## Architecture

Everything runs on one Novatrend cPanel server (193.33.128.160):
- **Next.js** via Phusion Passenger (bioco.ch, www.bioco.ch)
- **ProcessWire** via PHP-FPM (cms.bioco.ch)
- **MySQL** localhost:3306 (bioco_cms)
- **SMTP** mail.bioco.ch:465

Next.js calls ProcessWire via `http://localhost/cms/api/*` (same server, no CORS).

## Deploy

```
git push → cPanel Git pulls → .cpanel.yml builds standalone → Passenger restarts
```

Manual fallback: `ssh bioco@193.33.128.160` then `bash /home/bioco/bioco-web-project/scripts/deploy.sh develop`

## URLs

- Production: https://www.bioco.ch
- CMS Admin: https://cms.bioco.ch/processwire/

## Key Files

- `frontend/server.js`: Passenger startup
- `frontend/middleware.ts`: security headers
- `frontend/.env.production`: production env defaults
- `.cpanel.yml`: auto-deploy config
- `scripts/deploy.sh`: manual deploy
- `site/config.php`: PW config (env var overrides for secrets)

## Running PW Migrations

1. Upload migration `.php` to `site/templates/`
2. Create bootstrap in CMS webroot (`/public_html/cms/`)
3. Run via `curl https://cms.bioco.ch/bootstrap-foo.php`
4. Delete bootstrap after use

## Installed PW Modules

- `MediaLibrary` (BitPoet): media library in CKEditor
- `InputfieldCKEditor`: rich text editor

## CKEditor Fields

`section_text`, `body`, `card_text`, `event_summary`, `event_signup_notes`
