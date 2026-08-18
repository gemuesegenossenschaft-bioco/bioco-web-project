# Staging-Inbetriebnahme + Divi-Handbuch für `staging.bioco.ch`

> **Welcher Weg gilt?** Für Staging gilt **Softaculous + `wordpress/scripts/release-wordpress-staging.sh`**: [RUNBOOK-SOFTACULOUS.md](RUNBOOK-SOFTACULOUS.md). GitHub Actions ruft dieselbe Pipeline auf; der frühere Bedrock-Deploy ist entfernt.

Zielgruppe: Güney für Server/Deploy, Goni für WordPress, Divi und Inhalte. Keine Secrets in GitHub oder im Repo speichern. Das Repo ist öffentlich; echte Werte gehören nur in cPanel, Server-`.env` und GitHub-Secrets.

## 1. Einmaliges Server-Setup in cPanel

### Subdomain und Docroot

In Novatrend cPanel:

- [ ] `Domains` → `Subdomains` oder `Domains`
- [ ] Subdomain `staging.bioco.ch` und bestehende Softaculous-Installation prüfen
- [ ] Document Root bleibt der Softaculous-WordPress-Root:

```text
/home/bioco/staging.bioco.ch
```

Der GitHub-Workflow deployt nur eigenen Code in die bestehende Softaculous-Installation. Der Secret-Wert `STAGING_WP_CONTENT` zeigt auf deren absoluten `wp-content`-Ordner, z. B.:

```text
/home/bioco/staging.bioco.ch/wp-content
```

Nicht auf den Webroot und nicht auf `uploads`.

### PHP 8.2 setzen

In cPanel:

- [ ] `Software` → `MultiPHP Manager`
- [ ] Domain `staging.bioco.ch` auswählen
- [ ] PHP-Version auf `PHP 8.2` setzen
- [ ] Übernehmen

WordPress/Composer verlangt laut `wordpress/composer.json`:

```text
php >=8.2
```

Falls Novatrend nur 8.3 anbietet, ist das voraussichtlich ok. Nicht auf 8.1 oder tiefer setzen.

### MySQL-Datenbank und User

In cPanel:

- [ ] `Databases` → `MySQL Databases`
- [ ] Datenbank anlegen: `bioco_wp`
- [ ] Datenbank-User anlegen, z. B. `bioco_wp_user`
- [ ] Starkes Passwort generieren
- [ ] User der DB zuweisen

Rechte:

- Für Installation/Updates: alle Rechte auf diese eine DB.
- Least privilege heißt hier: Der User darf nur auf `bioco_wp`, nicht auf ProcessWire-, Matomo- oder andere Datenbanken.
- Nach dem Installer kann man härter werden, aber für WordPress/Plugin-Migrationen sind `CREATE`, `ALTER`, `INDEX`, `INSERT`, `UPDATE`, `DELETE`, `SELECT` praktisch nötig.

cPanel kann Namen automatisch prefixen, z. B. `bioco_bioco_wp`. In `.env` muss exakt der Name stehen, den cPanel anzeigt.

### Server-`.env` anlegen

Auf dem Server im Bedrock-Projektroot:

```bash
cd /home/bioco/.../wordpress
cp .env.example .env
```

Dann echte Werte eintragen:

```dotenv
DB_NAME='bioco_wp'
DB_USER='...'
DB_PASSWORD='...'
DB_HOST='localhost'

WP_ENV='staging'
WP_HOME='https://staging.bioco.ch'
WP_SITEURL="${WP_HOME}/wp"

SMTP_HOST='mail.bioco.ch'
SMTP_PORT='465'
SMTP_USER='...'
SMTP_PASS='...'
SMTP_SECURE='ssl'

NEXT_PUBLIC_TURNSTILE_SITE_KEY='...'
TURNSTILE_SECRET_KEY='...'

INTRANET_SIGNUP_URL='https://intranet.bioco.ch/my/signup/'
```

Salts generieren über:

```text
https://roots.io/salts.html
```

Alle acht Werte ausfüllen:

```dotenv
AUTH_KEY=''
SECURE_AUTH_KEY=''
LOGGED_IN_KEY=''
NONCE_KEY=''
AUTH_SALT=''
SECURE_AUTH_SALT=''
LOGGED_IN_SALT=''
NONCE_SALT=''
```

Wichtig:

- [ ] `.env` nie committen
- [ ] keine Secrets in README, Tickets oder Screenshots posten
- [ ] Turnstile Site Key und Secret Key müssen zusammenpassen
- [ ] SMTP läuft über `mail.bioco.ch:465` mit `ssl`

### GitHub Repo-Secrets

In GitHub:

- [ ] Repo öffnen
- [ ] `Settings` → `Secrets and variables` → `Actions`
- [ ] `New repository secret`

Diese Secrets setzen:

```text
STAGING_SSH_HOST
STAGING_SSH_USER
STAGING_SSH_KEY
STAGING_SSH_KNOWN_HOSTS
STAGING_WP_CONTENT
STAGING_SSH_PORT
```

Bedeutung:

- `STAGING_SSH_HOST`: Novatrend-Host, z. B. Server-IP oder SSH-Host
- `STAGING_SSH_USER`: cPanel/SSH-User, z. B. `bioco`
- `STAGING_SSH_KEY`: privater Deploy-Key, öffentlicher Key muss auf dem Server autorisiert sein
- `STAGING_SSH_KNOWN_HOSTS`: vorab geprüfte `known_hosts`-Zeile; nie dynamisch im Workflow vertrauen
- `STAGING_WP_CONTENT`: absoluter `wp-content`-Pfad der Softaculous-Installation
- `STAGING_SSH_PORT`: optional, Standard `22`

Fehlende Secrets brechen das Release ab; kein Schritt wird still übersprungen.

### OPcache-Gotcha

Novatrend/PHP-FPM kann nach rsync alte PHP-Bytecodes ausliefern. CLI:

```bash
php -r 'opcache_reset();'
```

hilft nicht immer für den Web-PHP-FPM-Prozess.

Wenn PHP-Dateien im Web alt wirken:

- [ ] Reset-Script im vhost-root außerhalb der WordPress-Routing-Falle platzieren
- [ ] per Browser/curl aufrufen
- [ ] `opcache_reset()` oder gezielt `opcache_invalidate('/voller/pfad/zur/datei.php', true)` ausführen
- [ ] Reset-Script sofort löschen

## 2. Erster Deploy

### Auslösen

Ein Push auf Branch `wordpress` triggert:

```text
.github/workflows/deploy-wordpress-staging.yml
```

Manuell geht es auch über GitHub:

- [ ] `Actions`
- [ ] `Release WordPress staging`
- [ ] `Run workflow`

### Was der Workflow tut

Der Workflow läuft bei Änderungen an:

```text
wordpress/**
cms/content-seed/**
tests/**
.github/workflows/deploy-wordpress-staging.yml
```

Schritte:

- [ ] Repo checkout
- [ ] prüft festen Commit und sauberen Checkout
- [ ] PHP 8.2 in GitHub Actions einrichten
- [ ] führt lokale Tests, Inhaltsgate, Seed-Plan und PHP-Lint aus
- [ ] erstellt das Datenbank-Backup ausserhalb des Webroots
- [ ] synchronisiert nur Repository-eigene Plugins, Themes und Seeds
- [ ] importiert Seeds, prüft 110 Blöcke und alle 22 Routen
- [ ] schreibt Commit und Backup-Pfad als Release-Marker
- [ ] lädt das vollständige Release-Log als Actions-Artefakt hoch

### WordPress-Installer

Nach dem ersten erfolgreichen Deploy öffnen:

```text
https://staging.bioco.ch/wp/wp-admin/install.php
```

Falls WordPress auf `/wp-admin/install.php` weiterleitet, dem Redirect folgen.

Installer:

- [ ] Sprache: `Deutsch`
- [ ] Seitentitel: `biocò Staging` oder ähnlich klar als Staging erkennbar
- [ ] Admin-User für Goni/Güney erstellen
- [ ] keinen User `admin` verwenden
- [ ] starkes Passwort
- [ ] Admin-Mail setzen
- [ ] Suchmaschinen-Indexierung für Staging deaktivieren

Danach einloggen:

```text
https://staging.bioco.ch/wp/wp-admin/
```

### Plugins aktivieren

In wp-admin:

- [ ] `Plugins` → `Installierte Plugins`
- [ ] `Advanced Custom Fields Pro` aktivieren
- [ ] `WP Mail SMTP` aktivieren

Diese mu-plugins sind automatisch aktiv und erscheinen nicht wie normale Plugins mit Aktivieren-Button:

```text
bioco-core
bioco-content
bioco-forms
```

Kontrolle:

- [ ] `Plugins` → `Must-Use`
- [ ] `bioco Core`, `bioco Content`, `bioco Forms` sichtbar

`bioco-content` registriert `Veranstaltungen` und `Gruppen`. `bioco-forms` stellt die Formular-REST-Endpunkte, Turnstile-Prüfung, DOI und `wp_mail()`-Versand bereit. SMTP selbst läuft über WP Mail SMTP.

### WP Mail SMTP konfigurieren

In wp-admin:

- [ ] `WP Mail SMTP` → `Settings`
- [ ] Mailer: SMTP
- [ ] SMTP Host: `mail.bioco.ch`
- [ ] Encryption: `SSL`
- [ ] SMTP Port: `465`
- [ ] Authentication: aktiv
- [ ] SMTP Username/Password aus Serverdaten
- [ ] From Email passend zur bioco-Mailadresse
- [ ] Test-Mail senden

## 3. Divi einrichten

Für Goni.

### Divi herunterladen und installieren

Divi ist lizenzpflichtig und wird nicht ins Repo committed.

- [ ] Bei Elegant Themes einloggen
- [ ] Divi-Theme-Zip herunterladen
- [ ] In wp-admin: `Design` → `Themes`
- [ ] `Theme hinzufügen`
- [ ] `Theme hochladen`
- [ ] Divi-Zip hochladen und installieren

Wichtig:

```text
web/app/themes/Divi
```

ist bewusst gitignored. Divi niemals ins Git committen.

### Child Theme aktivieren

Nach der Divi-Installation:

- [ ] `Design` → `Themes`
- [ ] `bioco Divi Child` / `bioco-divi` aktivieren
- [ ] Nicht das Parent-Theme `Divi` direkt aktivieren

Das Child Theme ist dünn: Es lädt Divi als Parent. Inhalte, Blöcke und Formulare kommen aus den mu-plugins bzw. den bioco-Blöcken, nicht aus Divi.

Falls `bioco-divi` noch nicht sichtbar ist, ist der Theme-Swap-Branch noch nicht vollständig deployed; dann zuerst Deploy/Branch prüfen.

### Divi-Lizenz eintragen

In wp-admin:

- [ ] `Divi` → `Theme Options`
- [ ] `Updates`
- [ ] Elegant-Themes-Benutzername/API-Key eintragen
- [ ] speichern
- [ ] Update-Prüfung testen

Keine Lizenzschlüssel in GitHub, `.env`, Markdown-Dateien oder Tickets schreiben.

### Divi-5-Hinweis

Für diese Site gilt: Divi als Präsentations-Theme verwenden, aber Inhalte nicht als Divi-Shortcode-Lock-in aufbauen.

Praktisch:

- [ ] Für freie Layoutseiten kann Goni Divi-Builder nutzen.
- [ ] Für bioco-Funktionen die vorhandenen `bioco/*`-Blöcke verwenden.
- [ ] Keine alten Divi-Shortcode-Layouts importieren.
- [ ] Inhalte so anlegen, dass Kernseiten auch ohne proprietären Shortcode-Ballast wartbar bleiben.

## 4. Inhalte skriptgesteuert importieren

Der primäre Weg ist der mitgelieferte WP-CLI-Importer. Er liest die versionierten Seed-Dateien, löst daraus den Section-Plan auf, erzeugt native Divi-Blöcke und richtet Startseite, Permalinks und Hauptmenü ein. SCF/ACF bleibt für Block-Editor und Renderer erforderlich, der Importer selbst serialisiert keine ACF-Blöcke mehr.

### Zuerst Probelauf

Im WordPress-Projektroot ausführen:

```bash
wp bioco import
```

Ohne `--apply` ist der Befehl immer ein Dry-Run und schreibt nichts.

- [ ] Terminal-Ausgabe vollständig prüfen
- [ ] HTML-Bericht unter `wp-content/bioco-import-log/` öffnen
- [ ] Fehler und Warnungen vor dem Schreiben klären
- [ ] Bei Bedarf einzelne Seiten testen: `wp bioco import --only=kontakt`
- [ ] Bei Bedarf Sammlungen auslassen: `wp bioco import --skip-collections`
- [ ] Bei Bedarf Startseite, Permalinks und Menü auslassen: `wp bioco import --skip-site-wiring`

Optionale Quellen für Events und Gruppen können als Datei oder URL übergeben werden:

```bash
wp bioco import --events-json='<DATEI-ODER-URL>' --groups-json='<DATEI-ODER-URL>'
```

### Import anwenden

Erst nach geprüftem Dry-Run:

```bash
wp bioco import --apply
```

- [ ] Import ohne Fehler abschliessen
- [ ] HTML-Bericht erneut prüfen
- [ ] Startseite, Navigation und mehrere Inhaltsseiten im Frontend kontrollieren

Der Import ist idempotent: Wiederholte Läufe führen zum gleichen Zielzustand. Die No-Clobber-Regel schützt redaktionelle Arbeit: Eine bereits nicht leere Seite oder ein bereits gepflegter Wert wird ohne `--force` gemeldet und übersprungen.

`--force` ist destruktiv für bestehende redaktionelle Inhalte. Nur nach Backup, geprüftem Bericht und ausdrücklicher Freigabe verwenden; es ist ausschliesslich zusammen mit `--apply` erlaubt:

```bash
wp bioco import --apply --force
```

### Import verifizieren

Nach dem schreibenden Lauf:

```bash
wp bioco verify
```

Die Verifikation ist ein reiner Lesevorgang und vergleicht den importierten Stand erneut mit den Seed-Dateien.

- [ ] `wp bioco verify` ohne Fehler abschliessen
- [ ] HTML-Bericht unter `wp-content/bioco-import-log/` prüfen
- [ ] Bei Bedarf gezielt prüfen: `wp bioco verify --only=home`

### Manueller Divi-Fallback

Manuelles Authoring ist nur ein bewusst gewählter Fallback für einzelne Seiten, die frei im Divi-Builder gestaltet werden sollen. Solche Seiten zuerst nach dem normalen Import bearbeiten; der Import überschreibt ihren nicht leeren Inhalt bei späteren Läufen ohne `--force` nicht. Die manuelle Neuerfassung aller Seiten ist nicht der Standardweg.

## 5. Konformitäts-Check vor dem Umschalten

Vor Bunny/Cutover muss Staging fachlich und technisch abgehakt sein.

### Pixel- und Inhaltsvergleich

- [ ] Referenz-Screenshots der aktuellen Live-Seite erstellen
- [ ] Staging-Screenshots erstellen
- [ ] Mobile, Tablet, Desktop vergleichen
- [ ] Alle ca. 20 Seiten prüfen
- [ ] Header, Navigation, Footer vergleichen
- [ ] Abstände, Farben, Typografie, Buttons, Karten, Bilder prüfen
- [ ] Abweichungen dokumentieren oder bewusst freigeben

### Formulare

Für jedes Formular testen:

- [ ] Kontakt
- [ ] Newsletter
- [ ] Schnuppertag / Besuchstag
- [ ] Warteliste
- [ ] Event-Anmeldung
- [ ] Mitgliedschaft

Pro Formular abhaken:

- [ ] Pflichtfelder validieren
- [ ] Turnstile erscheint
- [ ] Formular ohne gültiges Turnstile wird abgelehnt
- [ ] Erfolgszustand erscheint
- [ ] Mail kommt über `mail.bioco.ch:465` an
- [ ] Reply-To stimmt
- [ ] Fehlermeldungen sind verständlich
- [ ] Keine PHP-Fatals im Log

Newsletter zusätzlich:

- [ ] DOI-Mail kommt an
- [ ] Bestätigungslink funktioniert
- [ ] Bestätigter Eintrag erscheint unter Newsletter-Abonnenten
- [ ] Abgelaufener/falscher Token wird sauber behandelt

Mitgliedschaft zusätzlich:

- [ ] Rechner-Auswahl wird übernommen
- [ ] Weiterleitung/Forwarding zu `https://intranet.bioco.ch/my/signup/` funktioniert oder scheitert sichtbar kontrolliert
- [ ] Admin-Mail enthält alle nötigen Angaben

### Redirects und URLs

- [ ] Alle alten Live-URLs gegen Staging prüfen
- [ ] Alle Redirects aus dem alten Next.js-Setup nach WordPress portiert
- [ ] Legacy-Uploads-Regeln geprüft, insbesondere alte `/wp-content/uploads/...`-Links
- [ ] `/wir`, `/intranet`, `/statuten` besonders prüfen, weil Apache-Dateien/Symlinks Next bisher umgehen konnten
- [ ] Keine wichtigen Seiten mit 404
- [ ] Canonicals, Seitentitel und Descriptions prüfen

### Matomo

Matomo bleibt auf dem Server unter:

```text
/home/bioco/public_html/matomo
```

Prüfen:

- [ ] Tracking-Snippet ist in WordPress/Theme eingebunden
- [ ] Keine doppelte Einbindung
- [ ] Seitenaufrufe erscheinen in Matomo
- [ ] Cookie-/Datenschutztext passt weiterhin

### Technische Smoke-Checks

- [ ] `https://staging.bioco.ch/` liefert 200 oder erwarteten Redirect
- [ ] Kein White Screen
- [ ] Keine sichtbaren PHP-Warnings/Fatals
- [ ] wp-admin funktioniert
- [ ] Medien-Uploads funktionieren
- [ ] Permalinks funktionieren nach erneutem Speichern
- [ ] ACF-Feldgruppen sind synchronisiert
- [ ] `Plugins` → `Must-Use`: alle bioco-mu-plugins aktiv
- [ ] OPcache nach Deploy wirklich frisch

## 6. Umschalten auf `bioco.ch` später in W13

Produktion bleibt bis zum Cutover auf Next.js + ProcessWire. Beide bleiben auch direkt nach dem Cutover am Leben, damit Rollback sofort möglich ist.

### Prinzip

Heute läuft Produktion über Apache `.htaccess` als Proxy auf Next.js:

```apache
RewriteRule ^(.*)$ http://127.0.0.1:49154/$1 [P,L]
```

WordPress soll später direkt per Apache/PHP-FPM aus dem Bedrock-Webroot dienen.

Cutover ist kein Code-Deploy, sondern ein kontrollierter Webroot/`.htaccess`-Flip.

### Vorbereitung

- [ ] Finalen Staging-Stand deployen
- [ ] DB/Uploads für Produktion vorbereiten
- [ ] Vollständiges Backup von `.htaccess`, WordPress-DB, Uploads und aktuellem `public_html`
- [ ] Redirect-Matrix bereit
- [ ] DNS nicht unnötig anfassen, wenn derselbe Server bleibt
- [ ] Wartungsfenster festlegen
- [ ] Rollback-Datei für alte `.htaccess` bereithalten

### Cutover

- [ ] `bioco.ch` Docroot bzw. Apache-Regeln auf Bedrock `web/` zeigen lassen
- [ ] Proxy-Regel auf `127.0.0.1:49154` für Produktion deaktivieren
- [ ] `/cms` und `/matomo` nicht kaputtmachen
- [ ] Permalinks in wp-admin einmal speichern
- [ ] OPcache resetten
- [ ] Startseite, Kernseiten, Formulare, Matomo prüfen
- [ ] Redirects testen

### Sofort-Rollback

Wenn etwas kritisch ist:

- [ ] alte `.htaccess` wiederherstellen
- [ ] Docroot/Regel zurück auf Next.js-Proxy
- [ ] Next.js-Prozess auf Port `49154` muss deshalb weiterlaufen
- [ ] ProcessWire bleibt ebenfalls online, bis WordPress stabil abgenommen ist

Erst nach stabiler Produktion:

- [ ] Next.js-Watchdog/Cron deaktivieren
- [ ] `next-server` stoppen
- [ ] ProcessWire nur nach separater Freigabe stilllegen
- [ ] alte sharp-/Node-/ISR-Betriebsreste entfernen
- [ ] finale Backups dokumentieren
