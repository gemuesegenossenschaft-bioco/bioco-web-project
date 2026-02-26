# bioco.ch

Swiss organic vegetable cooperative. Headless CMS: ProcessWire (PHP). Frontend: Next.js 14 (standalone). Both on Novatrend cPanel.

## Architecture

Everything runs on one Novatrend cPanel server (193.33.128.160):
- **Next.js** standalone on port 49152, Apache `.htaccess` proxy via `RewriteRule [P]`
- **ProcessWire** via PHP-FPM (cms.bioco.ch)
- **MySQL** localhost:3306 (bioco_cms)
- **SMTP** mail.bioco.ch:465
- Cron job every 5min keeps Node.js alive (`/home/bioco/bioco-frontend/start.sh`)

Note: CloudLinux mod_passenger is NOT loaded in Apache. Passenger directives in .htaccess are ignored. We use mod_proxy via RewriteRule instead. Build on server also fails (CloudLinux thread limits for SWC/Rayon). Build locally, deploy via rsync.

Next.js calls ProcessWire via `http://localhost/cms/api/*` (same server, no CORS).

## Deploy

Build locally, upload via rsync:
```
scripts/deploy.sh main
```

Or manually: `cd frontend && npm ci && npm run build`, then rsync `.next/standalone/`, `.next/static/`, `public/` to server, restart Node.js.

## URLs

- Production: https://www.bioco.ch
- CMS Admin: https://cms.bioco.ch/processwire/

## Key Files

- `frontend/server.js`: Next.js standalone startup (used by build output, not custom)
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
