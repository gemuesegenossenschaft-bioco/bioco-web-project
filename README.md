# bioco.ch Webprojekt

Website der Gemüsegenossenschaft biocò. Solidarische Landwirtschaft in der Region Baden, Brugg, Gebenstorf.

## Architektur

Alles auf einem Server (Novatrend cPanel, 193.33.128.160):

```
Apache + AutoSSL
├── Next.js 14 (bioco.ch)          ← Frontend, SSR, Formulare
│   └── Standalone Build auf Port 49152
│   └── Apache .htaccess Proxy → localhost:49152
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

Build lokal, Upload via rsync:

```bash
# Ganzes Deploy (build + upload + restart)
scripts/deploy.sh main

# Oder manuell:
cd frontend
npm ci && npm run build
rsync -avz --delete .next/standalone/ bioco@193.33.128.160:/home/bioco/bioco-frontend/
rsync -avz --delete .next/static/ bioco@193.33.128.160:/home/bioco/bioco-frontend/.next/static/
rsync -avz --delete public/ bioco@193.33.128.160:/home/bioco/bioco-frontend/public/
ssh bioco@193.33.128.160 'pkill -f "node.*server.js.*49152"; sleep 1; /home/bioco/bioco-frontend/start.sh'
```

Der Node.js Prozess startet automatisch via Cron (alle 5 Minuten Healthcheck).

## CMS Verwaltung

- **Admin Panel:** https://cms.bioco.ch/processwire/
- **Events bearbeiten:** Admin > Seiten > Events
- **Inhalte bearbeiten:** Admin > Seiten > (Seitenname)
- **Medien hochladen:** Admin > Media

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
- `site/config.php` : ProcessWire DB-Zugangsdaten (nicht im Git)
- `public_html/.htaccess` : Apache Proxy-Regeln zu Next.js
- `.env.example` : Alle Umgebungsvariablen dokumentiert

## Technologie

- **Frontend:** Next.js 14, React 18, TypeScript, Framer Motion
- **CMS:** ProcessWire 3.x (PHP 8.1)
- **Server:** Novatrend cPanel, Node.js 18, Apache, MySQL
- **Analytics:** Matomo (self-hosted)
- **Formulare:** Nodemailer via SMTP (mail.bioco.ch)
