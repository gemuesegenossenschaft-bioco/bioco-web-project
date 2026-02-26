# bioco.ch

Swiss organic vegetable cooperative. Headless CMS: ProcessWire (PHP). Frontend: Next.js 14 (standalone). Both on Novatrend cPanel.

## Architecture

Everything runs on one Novatrend cPanel server (193.33.128.160):
- **Next.js** standalone on port 49152, Apache `.htaccess` proxy via `RewriteRule [P]`
- **ProcessWire** via PHP-FPM (cms.bioco.ch)
- **MySQL** localhost:3306 (bioco_cms)
- **SMTP** mail.bioco.ch:465
- Cron job every 5min keeps Node.js alive (`/home/bioco/bioco-frontend/start.sh`)

Next.js calls ProcessWire via `http://localhost/cms/api/*` (same server, no CORS).

## Server Setup (Novatrend cPanel / CloudLinux)

### Critical constraints
- **mod_passenger NOT loaded in Apache.** Passenger directives in `.htaccess` are silently ignored. Use `RewriteRule [P]` (mod_proxy) instead.
- **`npm run build` fails on server.** CloudLinux thread limits cause SWC/Rayon panics. Workaround: `RAYON_NUM_THREADS=1` or build locally.
- **Node 18.20.8** is the latest available (no Node 20+). Fine for Next.js 14.
- **Port 49152** is open externally (not firewalled). Used for direct testing: `http://193.33.128.160:49152`

### SSH access
```bash
ssh bioco@193.33.128.160
```
SSH key must be imported via cPanel > Sicherheit > SSH-Zugang. Username: `bioco`.

### Server paths
```
/home/bioco/
├── bioco-web-project/         # Git repo (reference, not for deploy)
├── bioco-frontend/            # Deployed Next.js standalone
│   ├── server.js              # Next.js standalone entry (from build output)
│   ├── start.sh               # Startup script with env vars
│   ├── .next/                 # Compiled app + static assets
│   ├── public/                # Images, PDFs, fonts
│   └── node_modules/          # Minimal standalone deps
├── public_html/
│   ├── .htaccess              # Apache proxy rules + Passenger config (ignored)
│   ├── cms/                   # ProcessWire installation
│   └── matomo/                # Matomo analytics
├── nodevenv/bioco-frontend/18/ # CloudLinux Node.js virtual env
└── logs/
    ├── nextjs.log             # Node.js stdout/stderr
    └── passenger.log          # Empty (Passenger not active)
```

### Apache .htaccess proxy
Requests to `bioco.ch` are proxied to Node.js via:
```apache
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/cms(/|$) [NC]
RewriteCond %{REQUEST_URI} !^/matomo(/|$) [NC]
RewriteRule ^(.*)$ http://127.0.0.1:49152/$1 [P,L]
```
`/cms` and `/matomo` are excluded (served by Apache/PHP directly).

### Process management
- `start.sh` exports all env vars and runs `node server.js` with `nohup`
- Cron runs `start.sh` every 5 minutes (no-op if already running)
- To restart: `pkill -f "node.*server.js"; sleep 2; /home/bioco/bioco-frontend/start.sh`

### Environment variables (set in start.sh)
`PROCESSWIRE_BASE_URL`, `PROCESSWIRE_API_KEY`, `NEXT_PUBLIC_PROCESSWIRE_BASE_URL`, `NEXT_PUBLIC_SITE_URL`, `NEXT_PUBLIC_MATOMO_URL`, `NEXT_PUBLIC_MATOMO_SITE_ID`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`, `NODE_ENV`, `PORT`

## Deploy

Build locally, upload via rsync. **Always rsync all three directories:**
```bash
scripts/deploy.sh main
```

Manual deploy:
```bash
cd frontend && npm ci && npm run build
rsync -avz --delete .next/standalone/ bioco@193.33.128.160:/home/bioco/bioco-frontend/
rsync -avz --delete .next/static/ bioco@193.33.128.160:/home/bioco/bioco-frontend/.next/static/
rsync -avz --delete public/ bioco@193.33.128.160:/home/bioco/bioco-frontend/public/
ssh bioco@193.33.128.160 'pkill -f "node.*server.js"; sleep 2; /home/bioco/bioco-frontend/start.sh'
```

**Warning:** The standalone rsync with `--delete` removes `public/` and `.next/static/` from the target. Always run all three rsyncs together.

## URLs

- Production: https://www.bioco.ch (DNS currently on Vercel, pending cutover)
- Direct test: http://193.33.128.160:49152
- CMS Admin: https://cms.bioco.ch/processwire/

## Key Files

- `frontend/middleware.ts`: security headers (X-Frame-Options, CSP, etc.)
- `frontend/.env.production`: production env defaults (no secrets)
- `.env.example`: all env vars documented
- `scripts/deploy.sh`: full deploy script (build + rsync + restart)
- `site/config.php`: PW config with `getenv()` overrides for secrets (gitignored)
- `public_html/.htaccess`: Apache proxy rules (managed by cPanel, do not delete Passenger/env sections)

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
