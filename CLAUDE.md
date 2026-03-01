# bioco.ch

Swiss organic vegetable cooperative. Headless CMS: ProcessWire (PHP). Frontend: Next.js 14 (standalone). Both on Novatrend cPanel.

## Architecture

Single Novatrend cPanel server (193.33.128.160):
- **Next.js** standalone on port 49154, Apache `.htaccess` proxy via `RewriteRule [P]`
- **ProcessWire** via PHP-FPM (cms.bioco.ch)
- **MySQL** localhost:3306 (bioco_cms)
- **SMTP** mail.bioco.ch:465
- Cron job every 5min keeps Node.js alive (`/home/bioco/bioco-frontend/start.sh`)

Next.js calls ProcessWire via `http://localhost/cms/api/*` (same server, no CORS).

## Server Setup (Novatrend cPanel / CloudLinux)

### Critical constraints
- **CloudLinux Passenger IS active** but unreliable for restarts. Use `pgrep -x next-server` + kill for process management.
- **`npm run build` fails on server.** CloudLinux thread limits cause SWC/Rayon panics. Always build locally.
- **Node 18.20.8** (no Node 20+). Fine for Next.js 14.
- **Port 49154** used by Node.js. Apache proxies to it.
- **sharp needs two packages**: after `--delete` rsync, restore both `sharp-linux-x64` AND `sharp-libvips-linux-x64` from `/tmp/sharp-pkg/`. Remove darwin bindings.
- **`pgrep -f next-server`** in SSH will match the SSH command itself, killing the session. Always use `pgrep -x next-server`.

### SSH access
```bash
ssh bioco@193.33.128.160
```

### Server paths
```
/home/bioco/
├── bioco-frontend/            # Deployed Next.js standalone
│   ├── server.js              # Next.js standalone entry
│   ├── start.sh               # Startup script with env vars (DO NOT rsync --delete)
│   ├── .next/                 # Compiled app + static assets
│   ├── public/                # Images, PDFs, fonts
│   └── node_modules/@img/     # sharp bindings (restore after deploy)
├── public_html/
│   ├── .htaccess              # Apache proxy: RewriteRule to 127.0.0.1:49154
│   ├── cms/                   # ProcessWire installation
│   │   └── site/templates/    # API + admin templates (deploy target for CMS files)
│   └── matomo/                # Matomo analytics
├── nodevenv/bioco-frontend/18/ # CloudLinux Node.js virtual env
└── logs/nextjs.log            # Node.js stdout/stderr
```

### Apache .htaccess proxy
```apache
RewriteRule ^(.*)$ http://127.0.0.1:49154/$1 [P,L]
```
`/cms` and `/matomo` excluded (served by Apache/PHP).

### Process management
- `start.sh` exports env vars, runs `node server.js` with `nohup`, uses flock to prevent duplicates
- Cron runs `start.sh` every 5 minutes (no-op if already running)
- Safe restart: `for p in $(pgrep -x next-server); do kill $p; done; sleep 3; start.sh`

### Environment variables (set in start.sh)
`PORT`, `NODE_ENV`, `HOSTNAME`, `PROCESSWIRE_BASE_URL`, `PROCESSWIRE_API_KEY`, `PW_API_KEY`, `NEXT_PUBLIC_PROCESSWIRE_BASE_URL`, `NEXT_PUBLIC_SITE_URL`, `NEXT_PUBLIC_MATOMO_URL`, `NEXT_PUBLIC_MATOMO_SITE_ID`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`

## Deploy

Build locally, rsync frontend + CMS templates, restore sharp, restart.

**Full deploy (recommended):**
```bash
scripts/deploy.sh main
```

This script: builds frontend, rsyncs standalone/static/public, restores sharp bindings, rsyncs CMS templates (admin.js, api.php, api-events.php), restarts Node.js, verifies.

**Manual deploy:**
```bash
cd frontend && npm ci && npm run build
rsync -avzc --delete --exclude='start.sh' .next/standalone/ bioco@193.33.128.160:/home/bioco/bioco-frontend/
rsync -avzc --delete .next/static/ bioco@193.33.128.160:/home/bioco/bioco-frontend/.next/static/
rsync -avzc --delete public/ bioco@193.33.128.160:/home/bioco/bioco-frontend/public/
# Restore sharp (REQUIRED after standalone rsync)
ssh bioco@193.33.128.160 'cp -r /tmp/sharp-pkg/node_modules/@img/sharp-{linux-x64,libvips-linux-x64} /home/bioco/bioco-frontend/node_modules/@img/'
# CMS templates
rsync -avzc site/templates/{admin.js,api.php,api-events.php} bioco@193.33.128.160:/home/bioco/public_html/cms/site/templates/
# Restart
ssh bioco@193.33.128.160 'for p in $(pgrep -x next-server); do kill $p; done; sleep 3; rm -f /tmp/bioco-next-start.lock /tmp/bioco-next.pid; /home/bioco/bioco-frontend/start.sh'
```

**CMS-only deploy** (no frontend build needed):
```bash
rsync -avzc site/templates/{admin.js,api.php,api-events.php} bioco@193.33.128.160:/home/bioco/public_html/cms/site/templates/
```

## URLs

- Production: https://www.bioco.ch
- CMS Admin: https://cms.bioco.ch/processwire/
- External test: `curl --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/`

## Key Files

### Frontend
| File | Purpose |
|------|---------|
| `frontend/app/*/page.tsx` | Next.js pages (SSG) |
| `frontend/components/AktuellesClient.tsx` | Aktuelles page: events grid, past events, modals |
| `frontend/components/AktuellesData.tsx` | Event data types, CMS API fetching, field mapping |
| `frontend/components/ItemDetailModal.tsx` | Event/aktuelles detail modal |
| `frontend/components/EventSignupForm.tsx` | Event signup form |
| `frontend/hooks/useEventsFeed.ts` | Client-side events hook |
| `frontend/lib/cmsClient.ts` | ProcessWire API client |
| `frontend/lib/processwire.ts` | PW page fetching, section rendering |
| `frontend/middleware.ts` | Security headers (CSP, X-Frame-Options) |

### CMS (ProcessWire)
| File | Purpose |
|------|---------|
| `site/templates/api.php` | Unified API router, all endpoints |
| `site/templates/api-events.php` | Events API (upcoming/past split, cardImage) |
| `site/templates/admin.js` | Admin UI: media library, image editor, preview, recap button |
| `site/templates/admin.php` | Loads admin.js in PW admin |
| `site/config.php` | PW config (gitignored, secrets via getenv) |

### Scripts
| File | Purpose |
|------|---------|
| `scripts/deploy.sh` | Full deploy: build, rsync, sharp restore, restart |

## API Endpoints

### Public (GET)
| Endpoint | Description |
|----------|-------------|
| `/api/health` | Health check |
| `/api/content/hero` | Homepage hero data |
| `/api/content/homepage` | Homepage sections |
| `/api/content/sections?path=` | Page sections by path |
| `/api/content/page?path=` | Single page content |
| `/api/content/pages` | All pages list |
| `/api/content/navigation` | Site navigation tree |
| `/api/content/events` | Events (upcoming + past) |
| `/api/content/aktuelles` | News/aktuelles posts |
| `/api/content/instagram` | Instagram feed |
| `/api/content/settings` | Typography/design tokens |
| `/api/content/page-path?id=` | Page path lookup (preview) |
| `/api/media-files` | Media library file list |

### Admin-only (POST, requires PW session)
| Endpoint | Description |
|----------|-------------|
| `/api/content/event-to-recap` | Convert upcoming event to past recap |
| `/api/content-save` | Save section content |
| `/api/media-import` | Import single media file |
| `/api/media-import-batch` | Batch media import |
| `/api/media-usage` | Check media usage |

### Form submissions (POST)
| Endpoint | Description |
|----------|-------------|
| `/api/forms/contact` | Contact form |
| `/api/forms/subscribe` | Newsletter signup |
| `/api/forms/visit` | Open visit day signup |
| `/api/forms/waiting-list` | Waiting list signup |
| `/api/forms/event-signup` | Event signup |

## CMS Admin Enhancements (admin.js)

- **Media Library button**: on image/file fields, opens inline picker to import from MediaLibrary
- **Library-only mode**: hides native upload on page edit (forces MediaLibrary usage)
- **Image editor**: Filerobot-based editor on image thumbnails
- **Preview button**: opens Next.js draft preview for the current page
- **Rückblick button**: on upcoming events, converts to past status for recap editing

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

## Skills

See `SKILLS.md` for available slash commands: `/deploy`, `/deploy-cms`, `/server-status`.
