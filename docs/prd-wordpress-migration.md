# PRD: Migration bioco.ch von Headless (Next.js + ProcessWire) zu selbst-gehostetem WordPress

> **Status:** Entwurf zur Diskussion · **Owner:** Technik-Team der Genossenschaft · **Zielsystem:** WordPress (Bedrock + natives Block-Theme + ACF Blocks) auf dem bestehenden Novatrend-cPanel-Server

---

**TL;DR (English):** This PRD proposes replacing bioco.ch's current headless stack — a Next.js SSG frontend and a ProcessWire CMS glued together by a bespoke Visual Editor — with a single self-hosted, full WordPress site built on Roots Bedrock, a hand-written native FSE block theme, ACF Blocks with Local JSON for git-versioned content *structure*, `theme.json` design tokens, and a GitHub Actions build-and-rsync pipeline deploying to the same Novatrend cPanel box. The goal is a pixel-perfect rebuild of the *cleaned* design that a volunteer cooperative can develop through GitHub, edit visually in Gutenberg (where what an editor sees is literally what ships), and own end-to-end on Swiss infrastructure — trading the Next.js/Passenger/ISR/watchdog operational surface for WordPress's security-patch treadmill. We are deliberately honest about the costs: a large one-time rebuild that discards a working frontend and the entire custom Visual Editor, an ongoing update burden on a public PHP login surface, the fact that only content *structure* (not the database of content *values*) can live in git, and a hard prerequisite that the design must first be cleaned into a coherent system before any pixel-perfect block work can begin.

---

## 1. Problemstellung (Problem Statement)

bioco.ch läuft heute als **Headless-Architektur**: ein statisch generiertes Next.js-Frontend (Port 49154, via Apache-Proxy) rendert die Seite, ein separates ProcessWire-CMS (`cms.bioco.ch`, PHP-FPM) hält die Inhalte. Beide liegen auf demselben Novatrend-cPanel-Server. Diese Trennung erzeugt für eine **kostensensible, ehrenamtlich betriebene Schweizer Genossenschaft** strukturell zu viel laufenden Aufwand:

1. **Zwei Render-Engines, verbunden durch eine selbstgebaute Brücke.** Das gesamte Visual-Editor-Subsystem (`visual-editor.php`, `visual-editor-app.js`, `useVisualEditor.ts`, `InlineVisualEditorRuntime.tsx`, die `?_visual=1`-iframe-Brücke, das postMessage-Protokoll, die Endpunkte `content-save`/`content-publish`/`sections-reorder`) existiert **einzig**, um einem Headless-CMS WYSIWYG zu verschaffen, dessen Editier-Oberfläche (ProcessWire) eine *andere* Engine ist als seine Render-Oberfläche (Next.js). Diese Brücke ist teuer, fragil und muss dauerhaft gepflegt werden.

2. **Hohe Node-Betriebslast.** CLAUDE.md dokumentiert einen ganzen Betriebs-Zoo: doppelte `next-server`-Prozesse durch Passenger/CloudLinux, `start.sh`/`healthcheck.sh`-Watchdog per Cron alle 5 Minuten, Wiederherstellung der `sharp`-Bindings nach jedem Deploy, der ISR-Revalidate-Vertrag (`ready.php` Debounce/Flush, `REVALIDATE_SECRET`-Sync), sowie „HTTP 200 mit Fehler-Boundary"-Erkennung bei veralteten Deploys. `npm run build` läuft auf dem Server gar nicht (CloudLinux-Thread-Limits) — es muss immer lokal gebaut und gersynct werden.

3. **Redaktion ist teilweise wirkungslos.** Inhalte sind nur dort CMS-gesteuert, wo eine Seite bereits auf `SectionRenderer` umgestellt wurde. Viele `app/*/page.tsx` sind weiterhin hartkodiertes JSX; Änderungen im CMS/Visual Editor ändern dort **nichts**. Für Redakteur:innen ist das intransparent und frustrierend.

4. **Datensouveränität & Kosten.** Die Genossenschaft will die volle Kontrolle über Inhalte und Infrastruktur auf Schweizer Hosting, ohne SaaS-Lock-in, und mit einem Stack, den auch wechselnde Freiwillige verstehen und via GitHub weiterentwickeln können.

**Kern des Problems:** Die Architektur ist für die Größe (ca. 20 Seiten) und das Team (Freiwillige) **überdimensioniert und wartungsintensiv**. Der teuerste Eigenbau (Visual Editor) kompensiert nur einen selbst verursachten Nachteil des Headless-Ansatzes.

---

## 2. Lösung (Solution)

Wir ersetzen den Headless-Stack durch ein **vollständiges, selbst-gehostetes WordPress**, das die Seite direkt via PHP-FPM/Apache ausliefert — **kein React-Frontend, keine Headless-API, kein ISR**.

- **Gutenberg ist der Visual Editor *und* die Render-Engine.** Was die Redaktion im Editor sieht, ist exakt das, was ausgeliefert wird. Der gesamte Visual-Editor-Eigenbau wird nicht portiert, sondern **gelöscht** — er wird zu nativer Plattform-Funktion.
- **Projektgerüst: Roots Bedrock.** `web/`-Docroot, WP-Core Composer-verwaltet, Secrets via `.env` (gitignored) — dieselbe Sicherheitsdisziplin, die das aktuelle Repo bereits pflegt (public Repo, `site/config.php` gitignored).
- **Theme: handgebautes natives Block-Theme (FSE)** mit **ACF Blocks**, Feld-Definitionen als **ACF Local JSON** (git-versioniert) und Design-Tokens in **`theme.json`**. Kein Page-Builder (Bricks/Elementor), kein Sage.
- **Deploy: GitHub Actions** baut in CI (Composer + Theme-Assets) und rsynct das fertige Artefakt per SSH auf denselben cPanel-Server — dieselbe „build off-server, ship artifact"-Philosophie wie `scripts/deploy.sh` heute.
- **Koexistenz & reversibler Cutover.** WordPress wird zuerst auf dem bestehenden Subdomain `staging.bioco.ch` (eigene DB) pixelgenau aufgebaut und gegen die Live-Seite QA-geprüft. Produktion bleibt unangetastet auf Next.js. Der Umschalter ist eine einzige `.htaccess`-Änderung; Next bleibt für sofortiges Rollback lauffähig.

**Harte Voraussetzung (Design-Kohärenz):** „Pixel-perfect" ist nur gegen ein **bereinigtes, kohärentes Design** definierbar. Bevor Blöcke gebaut werden, müssen Farben, Radien, Abstände, Schatten, Typografie und Komponentenvarianten zu einem konsistenten Design-System konsolidiert sein. Ein inkonsistentes Ist-Design lässt sich nicht sinnvoll in `theme.json` + ACF-Blöcke gießen. Diese Bereinigung ist **Vorbedingung**, nicht Teil dieser Migration (siehe *Out of Scope*).

---

## 3. User Stories

**Als Redakteur:in (Freiwillige:r ohne Code-Kenntnisse)** möchte ich Inhalte direkt auf einer WYSIWYG-Vorschau bearbeiten, die exakt der Live-Seite entspricht, damit ich sofort sehe, was ich veröffentliche, und keine „meine Änderung erscheint nicht"-Situationen mehr entstehen.

**Als Redakteur:in** möchte ich nur markenkonforme Farben, Abstände und Bausteine auswählen können, damit ich das Design nicht versehentlich breche und keine technische Freigabe für einfache Text-/Bildänderungen brauche.

**Als Entwickler:in (Freiwillige:r)** möchte ich die gesamte Seitenstruktur — Theme, Blöcke, Feld-Definitionen (ACF Local JSON), Design-Tokens, Redirects, CPT-Registrierung — als Code im GitHub-Repo reviewen und versionieren, damit Änderungen nachvollziehbar, per Pull Request prüfbar und zwischen Umgebungen reproduzierbar sind.

**Als Entwickler:in** möchte ich lokal in einer prod-nahen Umgebung (Apache + PHP-FPM + MySQL) arbeiten und per Git-Push automatisiert deployen, damit ich mich nicht mit fragilen Node-Prozessen, sharp-Bindings oder ISR-Verträgen auseinandersetzen muss.

**Als Betreiber:in / Ops** möchte ich einen zustandslosen PHP-Stack unter demselben PHP-FPM betreiben, der `cms.bioco.ch` heute schon zuverlässig bedient, damit der Passenger-/Watchdog-/`next-server`-Betriebsaufwand vollständig entfällt.

**Als Vorstand der Genossenschaft** möchte ich einen kostengünstigen, weit verbreiteten Open-Source-Stack ohne SaaS-Lock-in auf Schweizer Hosting, damit wir Datensouveränität behalten, künftige Freiwillige leicht einarbeiten können und keine proprietären Lizenz-/Abo-Fallen eingehen.

**Als Websitebesucher:in** möchte ich eine schnelle, pixelgenau gestaltete, barrierearme Seite ohne externe Tracker/CDN-Fonts, damit die Nutzung angenehm und datenschutzkonform (self-hosted Inter, keine Google-Fonts) ist.

**Als für SEO Verantwortliche:r** möchte ich, dass jede bestehende URL und jeder Redirect (inkl. der Legacy-`/wp-content/uploads/...`-Regel) erhalten bleibt, damit bestehende Einbindungen und Rankings nicht brechen.

---

## 4. Implementation Decisions

Die folgenden Entscheidungen (ID-1 … ID-9) basieren auf der Architektur- und Content-Modell-Recherche und sind bewusst konkret.

### ID-1 — Render-Modell: volles WordPress, kein Headless

WordPress liefert `bioco.ch` direkt via PHP-FPM/Apache aus. Kein React-Frontend, keine Headless-API, kein ISR. **Begründung:** Visual Editing lohnt sich nur, wenn Editier- und Render-Oberfläche **dieselbe Engine** sind. Headless erzwang zwei Engines plus Brücke; volles WordPress macht Gutenberg zugleich zum Editor und zum Produktions-Renderer. **Operativer Bonus:** Die gesamte Node-Ops-Fläche (Passenger-Duplikate, Watchdog-Cron, sharp, ISR-Vertrag, „200-mit-Fehler-Boundary") entfällt. **Ehrlicher Preis:** Ein funktionierendes, schnelles SSG-Frontend und der Visual-Editor-Eigenbau werden weggeworfen; ca. 20 Seiten werden pixelgenau als Blöcke neu gebaut — das ist der Hauptaufwand.

### ID-2 — Projektgerüst: Roots Bedrock

Bedrock als Struktur: `web/`-Docroot, WP-Core in `web/wp` (Composer-verwaltet), App-Content in `web/app` (`themes/`, `plugins/`, `mu-plugins/`, `uploads/`), Config in `config/` + Root-`.env`. Neues **public** Repo `gemuesegenossenschaft-bioco/bioco-wordpress`; public ist zulässig, **sofern** ACF-Pro-Lizenzschlüssel und Composer-Auth ausschließlich in CI-Secrets und Server-`.env` liegen (nie getrackt). Composer-Pinning (`composer.lock`) macht Patches auditierbar (Dependabot/Renovate) — wichtig angesichts der WP-CVE-Kadenz.

```
bioco-wordpress/
├── composer.json / composer.lock      # WP-Core + Plugins gepinnt (wpackagist + ACF Pro)
├── .env.example                        # dokumentiert Keys; echte .env nur Server (gitignored)
├── config/                             # Bedrock application.php + env/*.php
└── web/
    ├── wp/                             # WP-Core — Composer, gitignored
    └── app/
        ├── themes/bioco/               # das Block-Theme (IN GIT)
        │   ├── theme.json              # Design-Tokens
        │   ├── acf-json/*.json         # ACF-Felddefinitionen (IN GIT — Local JSON)
        │   ├── blocks/*/               # ACF Blocks: block.json + render.php
        │   ├── templates/*.html        # FSE-Block-Templates
        │   ├── parts/*.html            # Header/Footer-Template-Parts
        │   ├── assets/{fonts,js,css}/  # self-hosted Inter, Block-View-Scripts
        │   └── src/ + Build-Tooling    # Vite/esbuild für interaktive Blöcke
        ├── mu-plugins/bioco-core/      # CPTs, Formular-Handler, Redirects (IN GIT)
        ├── plugins/                    # Composer/wpackagist — gitignored
        └── uploads/                    # Medien — server-owned, gitignored
```

### ID-3 — Theme: natives Block-Theme + ACF Blocks + ACF Local JSON + theme.json

Handgebautes **FSE-Block-Theme**. Bespoke-Sektionen werden **ACF Blocks** (je `block.json` + PHP-`render.php` + ACF-Feldgruppe). Felddefinitionen als **ACF Local JSON** in `acf-json/*.json` (git-getrackt, Auto-Sync beim Admin-Load). Design-Tokens in **`theme.json`**.

- **ACF Local JSON ist der Dreh- und Angelpunkt von „git-entwickelbar":** Standardmäßig speichert ACF Felddefinitionen in der DB (nicht git, nicht reviewbar, driftet zwischen Umgebungen). Local JSON schreibt jede Feldgruppe auf Platte und synct auf anderen Umgebungen automatisch. Damit steht die Inhalts-*Struktur* unter Versionskontrolle; Inhalts-*Werte* bleiben in der DB (siehe ID-9).
- **`theme.json` für Tokens (pixelgenau):** Eine Quelle speist Block-Editor-UI *und* Frontend. Mapping von `tokens.css` in `settings.color.palette` / `settings.custom` (Radien, Schatten, Spacing) / `settings.typography.fontFamilies` gibt Redakteur:innen nur markenkonforme Optionen und lässt die Editor-Vorschau der Produktion entsprechen. Bestehendes `tokens.css` bleibt zusätzlich verbatim eingebunden (Belt-and-Suspenders für Pixel-Parität). **Inter self-hosten** via `fontFace` (woff2 in `assets/fonts/`) — kein Google-Fonts-CDN (CH/DSGVO + strengere CSP).
- **Kein Bricks/Elementor:** Page-Builder serialisieren Layout als proprietäre Blobs in `post_content`/postmeta — nicht diffbar, nicht reviewbar, Lock-in. Das ist das DB-not-in-git-Problem im Extrem. Scheitert an „git-entwickelbar" und „kein Lock-in".
- **Kein Sage:** Sage ist gut, legt aber Blade + Acorn (Laravel) über WP (mehr Abstraktion, schwererer Build). Der Auftrag ist minimaler Lock-in + Git-Entwickelbarkeit + Pixelgenauigkeit + natives Visual Editing. Ein reines Block-Theme hält Template-Markup als HTML + `block.json` (geringste Framework-Fläche) und maximiert den ID-1-Vorteil.
- **ACF Blocks statt Core-Custom-Blocks (JS/React):** Layout-/Content-Blöcke sind als ACF Blocks (PHP-Render + ACF-Felder) deutlich günstiger, Felddefinitionen automatisch git-getrackt. Ein JS-Build (Vite/esbuild) bleibt nur den echt interaktiven View-Scripts vorbehalten.

### ID-4 — Lokale Entwicklung: DDEV (primär), wp-env als Fallback

**DDEV** als kanonische lokale Umgebung, weil sie den Prod-Stack (Apache + PHP-FPM + echtes MariaDB/MySQL) mit erstklassiger **Bedrock-Unterstützung** (Docroot `web/`, `.env`, Composer, WP-CLI, Xdebug, HTTPS) reproduziert. `wp db import/export` gegen echtes MySQL entspricht dem bestehenden `mysqldump`-Workflow (`scripts/sync-staging.sh`); ein Prod-DB-Dump wird lokal eingespielt, um Inhalte zu reproduzieren. **wp-env** nur als leichter Fallback für isoliertes Block-Hacking, nicht als Source-of-Truth.

### ID-5 — Deploy: GitHub Actions → in CI bauen, per rsync-über-SSH ausliefern

`deploy.yml` spiegelt die etablierte „off-server bauen, Artefakt shippen, verifizieren"-Ethik — aus demselben Grund, aus dem `npm run build` auf der Box scheitert (CloudLinux-Thread-Limits).

**Pipeline:** `checkout` → `setup-php@8.2` + `composer install --no-dev --optimize-autoloader` (ACF-Pro/Composer-Auth aus CI-Secret `COMPOSER_AUTH`/`ACF_PRO_KEY`) → `setup-node` + `npm ci && npm run build` im Theme (`web/app/themes/bioco`) → `rsync -avzc --delete` über SSH (Deploy-Key `secrets.SSH_PRIVATE_KEY`). Dadurch muss **auf dem Server weder Composer noch npm laufen** — wie beim Shippen von `.next/standalone`.

- **rsync shippt:** `web/wp/` (Core), gebautes `web/app/themes/bioco/` (inkl. `acf-json/` + Assets), `web/app/mu-plugins/`, Composer-aufgelöstes `web/app/plugins/`, `vendor/`, `config/`.
- **rsync MUSS ausschließen** (server-owned, wie `deploy.sh` `start.sh` schützt): `web/app/uploads/` (Medien — **nie** `--delete`, WP-Analogon zu `site/assets/files/`), `.env`, server-eigene Drop-ins/Cache-Verzeichnisse.
- **Nie in Git:** WP-Core, Plugins, `vendor/`, Uploads, `.env`, **die Datenbank** (ID-9). Getrackt sind nur Theme, mu-plugins, `acf-json`, `theme.json`, `composer.json/.lock`, Config.
- **Post-Deploy — kein Prozess zum Neustarten** (großer Vorteil ggü. Next; kein `next-server`). **Aber die OPcache-Falle aus CLAUDE.md gilt für WP genauso:** Nach rsync kann PHP-FPM alten Bytecode ausliefern. Mitigation: `-c` frischt mtimes; danach einen winzigen web-erreichbaren `opcache_reset()`-Endpunkt aufrufen (die von CLAUDE.md dokumentierte Technik — Reset-Datei am vhost-Root, außerhalb der WP-`.htaccess`-Routung), oder PHP-FPM reloaden (falls cPanel erlaubt), oder `opcache.revalidate_freq` abwarten. Danach `wp cache flush`. Verifikation via Body-Smoke-Checks analog `deploy.sh` (curl auf PHP-Fatals/White-Screen, `wp option get home`, extern `curl --resolve bioco.ch:443:...`).
- **Branch/Env:** `develop` → `staging.bioco.ch` (bestehende Subdomain + eigene DB), `main` → Produktion — derselbe Zweistufen-Flow wie in HANDOFF.md.

### ID-6 — Runtime: PHP-Version, MySQL-Wiederverwendung, Koexistenz & Cutover

- **PHP 8.2** anvisieren (sicher, gut getestet für WP 6.x + ACF Pro; 8.3 akzeptabel). Minimum 8.1. **Aktion:** In cPanel → MultiPHP Manager prüfen, was Novatrend anbietet, und den `bioco.ch`-vhost darauf setzen. MultiPHP ist pro Domain — `bioco.ch` (WP, 8.2) und `cms.bioco.ch` (ProcessWire) koexistieren auf verschiedenen PHP-Versionen während der Migration.
- **MySQL wiederverwenden** (localhost:3306, cPanel-Tooling), aber **neue DB** (`bioco_wp` + least-privilege-User). **PW-Schema NICHT wiederverwenden** — `wp_*` ist ein völlig anderes Datenmodell; Content-Migration ist eine Daten-Transformation (ID-8), keine Schema-Übernahme. Bestehende `mysqldump`-/`sync-staging.sh`-Muster portieren direkt.
- **Koexistenz (keine Kollision):** WP wird direkt von Apache/PHP-FPM bedient; Next.js ist ein Port-49154-Proxy — sie konkurrieren nicht. Während der Migration steht WP auf `staging.bioco.ch` (eigene DB), wird auf Pixel-Parität gebaut und gegen Live QA-geprüft; Produktion läuft unangetastet auf Next (volle Rollback-Sicherheit).
- **Cutover (ein reversibler Schalter):** `/home/bioco/public_html/.htaccess` umstellen — die `RewriteRule ^(.*)$ http://127.0.0.1:49154/$1 [P,L]` entfernen und den Docroot auf Bedrock `web/` zeigen (bzw. Bedrock an Ort und Stelle bringen). **`next-server` auf :49154 für sofortiges Rollback weiterlaufen lassen** (nur alte `.htaccess` zurückspielen). `cms.bioco.ch` (PW) bis zur vollständigen WP-Übernahme aktiv halten, dann PW + Next + Watchdog-Cron + sharp + `start.sh` außer Betrieb nehmen.
- **SEO-kritisch:** **Jeden** Redirect aus `next.config.js redirects()` nach WP portieren (`.htaccess` oder Code-Redirect-Map im mu-plugin) — inkl. der Legacy-`/wp-content/uploads/2017/07/...`-Regel (passenderweise zurück zu WordPress).

### ID-7 — Design-Tokens → theme.json (pixelgenau)

- `--bioco-green #39A933`, `--bioco-orange #F29200`, `--bioco-beet #87213D`, `--bioco-dark-green #285A19`, `--bioco-bg #F6F9F5`, `--bioco-bg-alt #E2E8E0`, `--bioco-text #1F2A1B`, `--bioco-grey #A4B1A0` → `settings.color.palette` (`--wp--preset--color--bioco-green` etc.).
- Radien (6/12/18), Spacing-Skala (8→64), die drei weichen Schatten → `settings.custom` (`--wp--custom--…`).
- Inter (Heading + Body) → `settings.typography.fontFamilies` mit self-hosted `fontFace`.
- Bestehendes `tokens.css` verbatim als kanonische Variablen-Ebene einbinden, damit handgebaute Blöcke exakt dieselben Werte referenzieren → keine visuelle Drift Editor↔Frontend.

### ID-8 — Content-Modell-Migration (das Blockset)

Das heutige Modell: pro Seite eine ProcessWire-Repeater-Feldgruppe `content_sections` (nach `sort` geordnet), je Item ein gemeinsames Feldset, gerendert über zwei diskriminierte Pfade (`section_component` gewinnt über `section_layout`).

**Architektur-Empfehlung — Option A: ein ACF-Gutenberg-Block pro Sektionstyp.** Reihenfolge = native Gutenberg-Blockliste (in `post_content`). Registrierung via `acf_register_block_type(..., render_callback)` → **server-seitiges PHP-Render**. Sauberste Entsprechung zum Visual Editor und zu `data-ve-section-id`-Markern. (Option B — ein ACF-Flexible-Content-Feld pro Seite — bleibt als 1:1-Nachbau des heutigen Datenschemas dokumentiert, aber A ist die Empfehlung.)

**`section_config`-JSON wird in echte ACF-Felder aufgelöst.** Registry-`configSchema` mappt 1:1: `select→Select, range→Range, number→Number, text→Text`. Kein JSON-Blob nach WP tragen — genau das verhindert ACF.

**Gemeinsamer Wrapper → eine „Section Common"-Clone-Gruppe** (ACF Clone), von jedem Block wiederverwendet:

| Heute (PW) | ACF-Feld | Typ |
|---|---|---|
| section_id | `anchor` (bzw. Block-`anchor`-Support) | Text |
| section_title | `title` | Text |
| section_eyebrow | `eyebrow` | Text |
| section_text | `text` | WYSIWYG |
| section_theme / section_bg_color | `theme` / `bg_color` | Select |
| button_/button2_ (max 2) | `buttons` | Repeater (text, url, variant) |
| section_image / image_alt | `image` | Image |
| section_images | `images` | Gallery |
| section_image_overlay | `image_overlay` | Select |
| brightness/contrast/saturate | `img_*` | Range |

Die Heading-Unterdrückung (`hasHeadingHtml` — `title` ausblenden, wenn `text` bereits `<h1>-<h6>` enthält) muss im PHP-Render nachgebaut werden; mehrere Seeds (`solawi`, `datenschutz`, `gemuese`, `mitmachen`, `standorte-depots`) hängen davon ab.

**Layout-Sektionen (kein Component) → ACF-Blöcke, rein SSR:**

| `section_layout` | WP-Block | Verdikt |
|---|---|---|
| `rich_text` | `acf/bioco-rich-text` | Clean (nah an Core Paragraph/Group) |
| `split_media_text` / `split_text_media` | `acf/bioco-media-text` (`media_side`) | Clean (Core Media & Text ohne Overlay/Filter) |
| `full_width_banner` | `acf/bioco-banner` | Clean |
| `media_grid` | `acf/bioco-media-grid` | Clean (Core Gallery) |
| `video_embed` | `acf/bioco-video` | Sauberer via WP-oEmbed statt Custom-URL-Parsing |

**Strukturierte, layout-ownende Components (`ownsLayout=true`) → ACF-Blöcke, SSR, Config als ACF-Felder:** `page_intro`, `media_text`, `cards_grid`, `gallery_strip`, `text_columns`, `timeline_header`, `timeline_item`, `cta_band`, `pricing_table` (3-Tier-Repeater statt 18 Flat-Felder; `PersonIcons` = PHP-Icon-Repeat), `accordion_item` (**Achtung:** in WP ein Accordion-Block mit Items-Sub-Repeater bevorzugen, da `/gemuese` mehrere `<details>` gruppiert), `steps` (Nummern render-time), `link_tiles` (leere hrefs → `<div>` statt `<a>` beibehalten). Alle sonst „clean".

**Interaktive Components → SSR-Shell + `wp_enqueue_script` (bzw. Formular-Plugin):**

| Component | WP-Block | Bedarf | Aufwand |
|---|---|---|---|
| `contact_form`, `subscribe_form`, `visit_day_form`, `waiting_list_form`, `membership_form` | je `acf/bioco-*-form` | **Formular-Plugin** (Gravity/WPForms/Fluent) **oder** Custom `register_rest_route` mit Turnstile-Verify + SMTP; `membership_form` (~992 Zeilen, mehrstufig, empfängt Rechner-Auswahl) als kompilierte JS-App gegen Custom-REST | hoch (Formular am höchsten) |
| `pricing_calculator` | `acf/bioco-pricing-calculator` | Enqueued JS, übergibt Tier an Membership-Form (Query-Param/Shared State) | mittel-hoch |
| `events_feed` | `acf/bioco-events-feed` | SSR via `WP_Query` auf `event`-CPT; `variant`+`limit` als ACF; optional Client-Filter/Modals | mittel-hoch |
| `group_cards` | `acf/bioco-group-cards` | SSR `WP_Query` (`group`-CPT/ACF-Repeater) statt Client-Fetch | mittel |
| `depot_map`, `geisshof_map` | `acf/bioco-*-map` | SSR-Shell + Karten-JS (Leaflet); `depotLocations` → ACF-Repeater/`wp_localize_script` | hoch |
| `saisonkalender`, `gallery` | `acf/bioco-*` | SSR-Grid + leichtes JS | mittel |
| `schnuppertage` | `acf/bioco-schnuppertage` | Hybrid: editoriale Felder SSR-ACF, „Nächste Termine"-Liste + Signup-Modal interaktiv | mittel-hoch |

**Collections → Custom Post Types:** `event`-CPT (ACF: Datum, `event_summary` WYSIWYG, `card_image`, `event_signup_notes`, Status) — Upcoming/Past-Split aus `api-events.php` = `WP_Query` mit `meta_query` auf Datum; „Rückblick/Recap" = Status-/Meta-Toggle. `group`-CPT und aktuelles/news (Core-`post` oder CPT). CKEditor-Felder (`section_text, body, card_text, event_summary, event_signup_notes`) → ACF-WYSIWYG (dieselbe TinyMCE-Engine).

**17 Seeds → WP-Pages:** `home` = statische Startseite (Hero-Felder als Page-Level-ACF/Hero-Block); Inhaltsseiten (`solawi, gemuese, mitmachen, standorte-depots, aktuelles`) als Pages; Formular-/Rechner-Seiten (`kontakt, bioco-werden, anmeldung, newsletter, warteliste, tag-der-offenen-tuer`) mit Formular-Block; `anmeldung`/`anmeldung-danke` als Page-Template-Variante (MinimalHeader/kein Footer, `steps`-Block); `kundenportal` (`link_tiles` + `events_feed` Banner-Variante); `datenschutz, impressum, statuten` reines rich_text (`statuten`-PDFs via Media Library + Buttons-Feld). `seo.title/description` → Yoast/RankMath oder ACF-SEO-Felder. **`kennenlernen-cta`** (auf ~6 Seiten, bewusst pro Seite editierbar) → `cta_band`-Instanz je Seite oder synced Pattern mit Per-Instance-Overrides — **nicht** zentralisieren. Anker (`kontakt-formular`, Depot-`E-02`, `#anmelden`) via Block-`anchor`.

**Querschnitts-Checkliste „needs care":** (1) Heading-Suppression in PHP nachbauen; (2) HTML in `section_text` nicht strippen (`<a class="btn">`, `<details>`, `mailto:`, inline `style`) — `wp_kses`/ACF-WYSIWYG-Tags konfigurieren; (3) `target=_blank/rel` auf PDFs/externen Links behalten; (4) Accordion-Gruppierung als ein Block mit Items-Sub-Repeater; (5) Turnstile-Verify + SMTP (`mail.bioco.ch:465`) reproduzieren; (6) `events_feed` als **ein** SSR-`event`-Block für `/aktuelles` und `/kundenportal`; (7) code-owned Daten (`depotLocations`, Saisonkalender, Pricing-Tiers) nach ACF/CPT verschieben, falls Redaktion sie ändern soll — sonst in PHP als developer-owned belassen.

**Netto:** ca. 20 ACF-Blöcke (6 Layout + 12 strukturiert + interaktive Shells) auf einer „Section Common"-Clone-Gruppe, ein `event`-CPT (optional `group`), `section_config` in native ACF-Felder aufgelöst, Reihenfolge via Gutenberg-Blockliste. Alles außer den 5 Formularen, 2 Karten, dem Rechner und der Schnuppertage-Terminliste/-Modal mappt sauber auf server-seitiges PHP.

### ID-9 — Ehrliche Tradeoffs (in Implementation eingepreist)

Siehe *Further Notes → Risiken* für die vollständige Diskussion; die Kernentscheidungen oben nehmen bereits an: (a) höhere WP-Patch-Last statt Node-Ops-Last; (b) nur *Struktur* in git, DB bleibt stateful; (c) einmaliger Rebuild-Aufwand als Preis für dauerhafte Ops-Vereinfachung; (d) ACF-Pro-Abhängigkeit als weiches Lock-in.

---

## 5. Testing Decisions

- **Pixel-Parität (Kernkriterium).** Visuelle Regressions-Tests: automatisiertes Screenshot-Diffing (Playwright oder BackstopJS) zwischen der Live-Next-Seite und WP-Staging über alle ~20 Seiten × Breakpoints (Mobile/Tablet/Desktop). Abnahme-Schwelle definieren; verbleibende Diffs manuell begründen. **Voraussetzung:** Baseline gegen das *bereinigte* Design, nicht gegen das inkonsistente Ist.
- **Cross-Browser & Responsive.** Manuelle + automatisierte Prüfung in aktuellen Chrome/Firefox/Safari; Fokus auf die interaktiven Blöcke (Karten, Rechner, Accordion, Schnuppertage, Formulare).
- **Formular-Tests (kritisch).** Für jedes der 5 Formulare: (a) Turnstile-Server-Verifikation greift (Positiv/Negativ), (b) SMTP-Zustellung über `mail.bioco.ch:465` real getestet, (c) Validierung/UX identisch zum Ist, (d) Spam-/Fehlerpfade. Der heutige Env-Ordering-Bug (`TURNSTILE_SECRET_KEY`) muss durch WP-`.env` + korrektes Laden verifiziert ausgeschlossen sein.
- **Redirect-Matrix.** Automatisierte curl-Suite, die **jeden** aus `next.config.js` portierten Redirect prüft (inkl. Legacy-`/wp-content/uploads/...`), Status + Ziel. Ergänzt um `--resolve bioco.ch:443:...`-Checks für externe vs. lokale Route (die `.htaccess`-Kurzschluss-Fallen `/wir`, `/intranet`, `/statuten`).
- **ACF-Local-JSON-Sync.** Verifizieren, dass Feldgruppen aus `acf-json/` auf einer frischen Umgebung (DDEV + Prod-DB-Dump) automatisch syncen und kein manueller DB-Import nötig ist — das ist die „git-entwickelbar"-Kernbehauptung.
- **Deploy-Smoke-Tests (in `deploy.yml`).** Nach rsync: OPcache-Reset ausgelöst und wirksam (Bytecode-Frische verifiziert), `wp option get home`, curl auf PHP-Fatals/White-Screen, lokaler + externer HTTP-200-Body-Check (kein Fehlertext). Rollback-Pfad (alte `.htaccess`) einmal end-to-end geübt.
- **Performance / Core Web Vitals.** Lighthouse gegen Staging vs. Live-Next; Ziel: keine spürbare Regression bei LCP/CLS. Page-Caching-Strategie (z. B. statisches Cache-Plugin/Full-Page-Cache) testen, da WP dynamischer rendert als SSG.
- **Barrierefreiheit.** Automatisiert (axe) + Tastatur-/Screenreader-Stichprobe auf Kernseiten und Formularen.
- **Sicherheit.** `DISALLOW_FILE_MODS`/`DISALLOW_FILE_EDIT` aktiv; `wp-login.php` gehärtet (2FA/Limit-Login/Wordfence); `uploads/` ohne PHP-Ausführung (verifiziert via Test-Upload); least-privilege-DB-User; Secret-Scanning des public Repos (keine Lizenz/Keys getrackt).
- **Content-Integritäts-Review.** Redaktionelle Abnahme, dass Import von Events/News (aus den noch laufenden `/api/content/events` + `/api/content/aktuelles`) vollständig und korrekt in die CPTs übernommen wurde.

---

## 6. Out of Scope

- **Design-Redesign.** Diese Migration setzt ein bereits **bereinigtes, kohärentes** Design voraus. Die Design-Konsolidierung selbst ist ein separates, vorgelagertes Arbeitspaket (harte Vorbedingung, siehe *Further Notes*).
- **Neue Inhalte / Content-Rewrite.** Es werden bestehende Inhalte migriert, keine neuen Texte/Seiten erstellt.
- **Neue Funktionalität** über die heutige Seite hinaus (keine neuen Formulare, Rechner, Mehrsprachigkeit, E-Commerce, Mitglieder-Login/Portal-Auth jenseits des Ist-Standes).
- **Beibehaltung des Visual Editors / einer Headless-API.** Der Eigenbau wird gelöscht, nicht portiert.
- **Wiederverwendung des ProcessWire-Schemas.** Neue `bioco_wp`-DB; PW wird nach Cutover außer Betrieb genommen.
- **Matomo-Migration.** `/matomo` bleibt Apache-served und unverändert; nur der Tracking-Snippet wandert ins Theme.
- **docs.bioco.ch.** Bleibt MkDocs auf GitHub Pages (DNS bei Tophost); kein CMS→git-Docs-Mirror.
- **Server-/Hoster-Wechsel.** Migration bleibt auf demselben Novatrend-cPanel-Server.

---

## 7. Further Notes

### Harte Vorbedingung: Design-Kohärenz

„Pixel-perfect" ist nur gegen ein definiertes Ziel messbar. Vor Blockbau müssen Farben, Radien (6/12/18), Spacing (8→64), die drei Schatten, Typografie (Inter) und Komponentenvarianten zu einem konsistenten Design-System konsolidiert sein — genau die Werte, die dann in `theme.json` (ID-7) und in die „Section Common"-Guardrails (ID-8) einfließen. Ohne diese Bereinigung produziert der Rebuild einen pixelgenauen Nachbau von *Inkonsistenzen*, und die Editor-Guardrails werden beliebig. **Ampel:** Migration startet erst, wenn das bereinigte Design als Referenz (Tokens + Komponenten) vorliegt.

### Risiken (ID-9, ausführlich)

- **Sicherheits- & Update-Last (größter laufender Preis).** Die öffentliche Seite läuft künftig als PHP mit Login und großer Plugin-Oberfläche statt als quasi-statisches SSG-Frontend + Headless-PW. WP/ACF/Plugin-CVEs sind häufig; eine Patch-Kadenz wird zur Pflicht. Mitigation, alles im Code: Bedrock + Composer + Dependabot/Renovate auf `composer.lock`; `DISALLOW_FILE_MODS`/`DISALLOW_FILE_EDIT`; Auto-Updates via Composer statt DB; `wp-login.php` schützen (2FA, Limit-Login/Wordfence, IP-/Basic-Auth-Gate); least-privilege-DB-User; `uploads/` ohne PHP-Ausführung; Admin nur über HTTPS. **Netto-Tausch:** WP-Patch-Tretmühle *dazu*, aber die gesamte Node-Watchdog-/Passenger-/sharp-/ISR-Fläche *weg* — für dieses Team vermutlich ein Wash oder Gewinn; die *Natur* der Last verschiebt sich von „flakigen Node-Prozess am Leben halten" zu „WordPress gepatcht halten".
- **DB nicht in git (inhärent, unvermeidbar).** Nur *Struktur* ist versioniert (Theme, Blöcke, `acf-json`, `theme.json`, CPT-Registrierung, `composer.lock`). Alle *Inhalte* (Posts, Pages, Menüs, ACF-Optionswerte, Medien-Metadaten) liegen in MySQL. **Die laufende Seite lässt sich nicht aus git allein reproduzieren — es braucht zusätzlich einen DB-Dump.** Mitigation: geplantes `mysqldump` (bestehendes `~/backups/`-/`sync-staging.sh`-Muster), WP-CLI `db export` per Cron, DB als autoritative stateful Daten behandeln. Wer „git = ganze Seite" erwartet, muss jetzt korrigiert werden.
- **Wegwerfen von Next.js (Sunk Cost + Rebuild-Risiko).** Ein funktionierendes, performantes SSG-Frontend und der Visual-Editor-Eigenbau werden verworfen. Pixelgenauigkeit erfordert Neubau von ~20 Seiten und jeder Bespoke-Komponente als Blöcke; die interaktiven React-Widgets (Karten, Rechner, Saisonkalender, Basket-Viz) sind der teure Long-Pole und das reale Risiko für das Pixel-Versprechen, falls unterschätzt. **Gegenargument:** Der VE existierte *nur*, weil Headless-PW kein WYSIWYG bot (ID-1); volles WP macht dieses Subsystem obsolet statt portierbar, und die gelöschte Node-Ops-Komplexität ist dauerhafte Ersparnis. Der Rebuild ist einmaliger Front-Load; Ops-Vereinfachung und natives Visual Editing sind laufende Gewinne.
- **ACF-Pro-Abhängigkeit.** ACF Blocks + Local JSON hängen an ACF Pro (kostenpflichtig). Lizenzschlüssel nur als CI/Server-Secret (Composer-Auth), nie im public Repo. Weiches Lock-in, da Block-Content weiterhin als portables HTML-Kommentar-Markup in `post_content` liegt (WXR-exportierbar) — kein Builder-Blob-Hard-Lock-in.
- **OPcache-Staleness beim Deploy.** Dieselbe PHP-FPM-Bytecode-Falle wie bei PW-Modulen (CLAUDE.md) gilt für WP-Theme/Plugins nach jedem rsync — vhost-Root-`opcache_reset()` in `deploy.yml` einplanen oder `revalidate_freq`-Verzögerung akzeptieren.

### Vor Baubeginn am Server zu verifizieren

1. cPanel-MultiPHP: verfügbare PHP-Versionen für den `bioco.ch`-vhost (benötigt 8.2/8.3).
2. Ob PHP-FPM-Reload für sauberes OPcache-Busting erlaubt ist — oder ob der Web-Hit-Reset-Trick nötig ist.
3. SSH-Deploy-Key-Zugang für den Actions-Runner (derselbe `bioco@193.33.128.160`-Account).
4. Frische MySQL-DB + User-Quota für `bioco_wp` neben den bestehenden drei DBs (`bioco_live`, `bioco_staging`, matomo).

### Grobe Phasierung (Vorschlag)

1. **Phase 0 — Voraussetzung:** Design-Bereinigung abgeschlossen; Server-Checks (oben) grün.
2. **Phase 1 — Gerüst:** Bedrock-Repo, DDEV, `theme.json`-Tokens, „Section Common"-Clone, CI-Skeleton, `staging.bioco.ch` steht.
3. **Phase 2 — Blöcke:** Layout- und strukturierte Blöcke (ID-8), inhaltsstarke Seiten pixelgenau nachbauen.
4. **Phase 3 — Interaktiv & Collections:** Karten, Rechner, Schnuppertage, `event`/`group`-CPTs, `events_feed`; die 5 Formulare inkl. Turnstile + SMTP.
5. **Phase 4 — Migration & QA:** Content-Import, Redirect-Portierung, Visual-Regression, Performance/Security/A11y-Tests gegen Live.
6. **Phase 5 — Cutover:** `.htaccess`-Umschaltung, Next für Rollback laufen lassen, Monitoring; nach Stabilisierung PW + Next + Watchdog/sharp/`start.sh` außer Betrieb nehmen.

### Offene Fragen

- Formulare: dediziertes Plugin (Gravity/WPForms/Fluent, mit Turnstile-/SMTP-Add-ons) **oder** git-getrackte Custom-REST-Handler? Empfehlung tendiert zu Custom (exakte UX + kein Lock-in), Kosten-/Aufwandsabwägung offen.
- `depotLocations`/Saisonkalender/Pricing-Tiers: redaktionell editierbar (ACF/CPT) oder developer-owned (PHP)? Pro Datensatz entscheiden.
- Full-Page-Cache-Strategie für WP (Plugin vs. serverseitig), um SSG-nahe Performance zu halten.