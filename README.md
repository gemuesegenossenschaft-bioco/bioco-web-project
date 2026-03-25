# bioco.ch Webprojekt

Website der Gemüsegenossenschaft biocò. Solidarische Landwirtschaft in der Region Baden, Brugg, Gebenstorf.

## Architektur

Alles auf einem Server (Novatrend cPanel, 193.33.128.160):

```
Apache + AutoSSL
├── Next.js 14 (bioco.ch)          ← Frontend, SSR, Formulare
│   └── Standalone Build auf Port 49154
│   └── Apache .htaccess Proxy → localhost:49154
├── ProcessWire 3.x (cms.bioco.ch) ← Headless CMS, JSON API
│   └── PHP 8.1, MySQL
├── Matomo (analytics.bioco.ch)    ← Webanalyse
└── Nextcloud (cloud.bioco.ch)     ← Dateiablage
```

Next.js ruft die ProcessWire API via `http://localhost/cms/api/` auf (gleicher Server, kein CORS nötig).

## Verzeichnisstruktur

```
/home/bioco/
├── bioco-web-project/         ← Git Repository
│   ├── frontend/              ← Next.js Quellcode
│   ├── site/                  ← ProcessWire Templates, API
│   ├── scripts/deploy.sh      ← Deploy-Skript
│   └── .cpanel.yml
├── bioco-frontend/            ← Next.js Standalone (deployed)
│   ├── server.js              ← Node.js Server
│   ├── start.sh               ← Startskript mit Env Vars
│   ├── .next/                 ← Kompilierte Seiten
│   └── public/                ← Statische Dateien (Bilder, PDFs)
└── public_html/
    ├── cms/                   ← ProcessWire Installation
    │   ├── site/templates/    ← PHP Templates + API
    │   └── processwire/       ← Admin Panel
    ├── matomo/                ← Matomo Installation
    └── .htaccess              ← Apache Proxy Regeln
```

## Lokale Entwicklung

```bash
cd frontend
npm install
npm run dev          # http://localhost:3000
```

Umgebungsvariablen für lokale Entwicklung in `frontend/.env.local`:
```
PROCESSWIRE_BASE_URL=https://cms.bioco.ch
PROCESSWIRE_API_KEY=bioco2026ready
NEXT_PUBLIC_SITE_URL=http://localhost:3000
```

## Deployment

Immer lokal bauen. Server-Builds schlagen auf CloudLinux oft fehl.

Build lokal, Upload via rsync:

```bash
# Ganzes Deploy (build + upload + restart)
scripts/deploy.sh main

# Oder manuell:
cd frontend
npm ci && npm run build
rsync -avzc --delete --exclude='start.sh' .next/standalone/ bioco@193.33.128.160:/home/bioco/bioco-frontend/
rsync -avzc --delete .next/static/ bioco@193.33.128.160:/home/bioco/bioco-frontend/.next/static/
rsync -avzc --delete public/ bioco@193.33.128.160:/home/bioco/bioco-frontend/public/
ssh bioco@193.33.128.160 'cp -r /tmp/sharp-pkg/node_modules/@img/sharp-linux-x64 /home/bioco/bioco-frontend/node_modules/@img/ && cp -r /tmp/sharp-pkg/node_modules/@img/sharp-libvips-linux-x64 /home/bioco/bioco-frontend/node_modules/@img/ && rm -rf /home/bioco/bioco-frontend/node_modules/@img/sharp-darwin-arm64 /home/bioco/bioco-frontend/node_modules/@img/sharp-libvips-darwin-arm64'
rsync -avzc site/templates/admin.js site/templates/api.php site/templates/api-events.php site/templates/visual-editor.php bioco@193.33.128.160:/home/bioco/public_html/cms/site/templates/
rsync -avzc site/ready.php bioco@193.33.128.160:/home/bioco/public_html/cms/site/ready.php
ssh bioco@193.33.128.160 'for p in $(pgrep -x next-server); do kill $p; done; sleep 3; /home/bioco/bioco-frontend/start.sh'
ssh bioco@193.33.128.160 'curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:49154/'
curl --resolve bioco.ch:443:193.33.128.160 -s -o /dev/null -w "%{http_code}\n" https://bioco.ch/
```

Der Node.js Prozess startet automatisch via Cron (alle 5 Minuten Healthcheck).

## CMS Verwaltung

- **Admin Panel:** https://cms.bioco.ch/processwire/
- **Events bearbeiten:** Admin > Seiten > Events
- **Inhalte bearbeiten:** Admin > Seiten > (Seitenname)
- **Medien hochladen:** Admin > Media
- **Visual Editor:** `https://cms.bioco.ch/visual-editor/` oder Admin-Navigation neben `Media`

Die CMS API liefert Events und Seiteninhalte als JSON an das Frontend.

## Seiten

| URL | Inhalt |
|-----|--------|
| `/` | Startseite mit Hero, Aktuelles, Schnuppertage |
| `/wir` | Team, Geisshof, Geschichte |
| `/gemuese` | Saisonales Gemüse, Erntekalender |
| `/mitmachen` | Schnuppertage, Mitarbeit |
| `/abos` | Abo-Modelle, Preise |
| `/aktuelles` | News und Veranstaltungen |
| `/bioco-werden` | Anmeldeformular |
| `/kontakt` | Kontaktformular |
| `/standorte-depots` | Depot-Standorte mit Karte |
| `/solawi` | Solidarische Landwirtschaft |
| `/intranet` | Dokumente (nur Mitglieder) |

## SSH Zugang

```bash
ssh bioco@193.33.128.160
```

SSH-Key muss in cPanel > Sicherheit > SSH-Zugang hinterlegt sein.

## Wichtige Dateien

- `frontend/next.config.js` : Next.js Konfiguration, Redirects, Env Vars
- `frontend/lib/cmsClient.ts` : CMS API Client (ProcessWire Anbindung)
- `frontend/middleware.ts` : Security Headers
- `frontend/components/visual-editor/InlineVisualEditorRuntime.tsx` : Inline-Feldbearbeitung im iframe
- `site/templates/visual-editor.php` : Standalone Visual Editor Shell
- `site/templates/admin.js` : ProcessWire Admin-Erweiterungen inkl. Visual-Editor-Link
- `site/config.php` : ProcessWire DB-Zugangsdaten (nicht im Git)
- `public_html/.htaccess` : Apache Proxy-Regeln zu Next.js
- `.env.example` : Alle Umgebungsvariablen dokumentiert

## Technologie

- **Frontend:** Next.js 14, React 18, TypeScript, Framer Motion
- **CMS:** ProcessWire 3.x (PHP 8.1)
- **Server:** Novatrend cPanel, Node.js 18, Apache, MySQL
- **Analytics:** Matomo (self-hosted)
- **Formulare:** Nodemailer via SMTP (mail.bioco.ch)
