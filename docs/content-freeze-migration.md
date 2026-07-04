# Content-Freeze-Migration: Seeds → ProcessWire (Runbook)

Überträgt den bisher im Next.js-Frontend hartkodierten Seiteninhalt aus
`cms/content-seed/*.json` byte-genau nach ProcessWire (`content_sections`-Repeater
+ SEO-Felder), damit die Seiten CMS-getrieben werden können.

Skript: `site/templates/migrate-content-freeze.php`
Seeds: `cms/content-seed/*.json` (17 Dateien, read-only — Inhalt nur ändern,
wenn sich der Website-Text bewusst ändern soll)
Schema-Test: `frontend/tests/content-seed-schema.test.ts`

## Eigenschaften (Sicherheitsmodell)

- **dry-run ist Default.** Ohne `mode=apply` wird nichts geschrieben.
- **Idempotent**, gematcht über `section_id` pro Seite.
- **"CMS gewinnt":** nicht-leere PW-Felder werden ohne `force=1` NIE
  überschrieben. Relevante Seiten mit bereits vorhandenem Live-CMS-Inhalt:
  `gemuese`, `mitmachen`, `solawi`, `aktuelles`, `home` (siehe
  `conversion_notes` in den Seeds).
- **Additiv:** neue Sections werden in Seed-Reihenfolge ans Ende angehängt;
  es wird nie etwas gelöscht oder umsortiert; nur Seiten aus den Seeds werden
  berührt.
- **Guards:** läuft nur im PW-Bootstrap, nur über HTTPS, und nur mit
  Superuser-Session ODER gültigem Token (≥ 20 Zeichen, `hash_equals`).
- **Log:** ProcessWire → Setup → Logs → `content-freeze`.

## 1. Dateien auf den Server bringen

Migrationsskript + Seeds per rsync (Muster: CMS-only deploy aus CLAUDE.md):

```bash
# Skript in die PW-Templates
rsync -avzc site/templates/migrate-content-freeze.php \
  bioco@193.33.128.160:/home/bioco/public_html/cms/site/templates/

# Seeds in das Standard-Seed-Verzeichnis des Skripts
rsync -avzc --delete cms/content-seed/ \
  bioco@193.33.128.160:/home/bioco/public_html/cms/site/templates/content-seed/
```

Hinweis: `--delete` betrifft hier nur `content-seed/` (eigenes Verzeichnis) —
NIE auf `site/templates/` selbst anwenden.

## 2. Bootstrap-Datei anlegen (CMS-Webroot)

Muster aus CLAUDE.md „Running PW Migrations“: Bootstrap im CMS-Webroot
(`/home/bioco/public_html/cms/`), nach Gebrauch löschen.

Token generieren (lokal):

```bash
openssl rand -hex 24
```

Datei `/home/bioco/public_html/cms/bootstrap-content-freeze.php` — Inhalt
wörtlich (Token ersetzen):

```php
<?php
// /home/bioco/public_html/cms/bootstrap-content-freeze.php — NACH GEBRAUCH LÖSCHEN
define('BIOCO_CONTENT_FREEZE_TOKEN', '<hier-den-generierten-token-einsetzen>');
// Optional: abweichendes Seed-Verzeichnis / Asset-Basis für Bild-Importe
// define('BIOCO_CONTENT_FREEZE_SEED_DIR', '/home/bioco/public_html/cms/site/templates/content-seed/');
// define('BIOCO_CONTENT_FREEZE_ASSET_BASE', 'https://www.bioco.ch');
require __DIR__ . '/index.php';
require \ProcessWire\wire('config')->paths->templates . 'migrate-content-freeze.php';
```

Anlegen z.B. per:

```bash
ssh bioco@193.33.128.160 'cat > /home/bioco/public_html/cms/bootstrap-content-freeze.php' <<'EOF'
<?php
define('BIOCO_CONTENT_FREEZE_TOKEN', '<hier-den-generierten-token-einsetzen>');
require __DIR__ . '/index.php';
require \ProcessWire\wire('config')->paths->templates . 'migrate-content-freeze.php';
EOF
```

## 3. OPcache-Gotcha (WICHTIG, aus CLAUDE.md)

PHP-FPM cached kompilierte `.php`-Dateien. Nach dem rsync von
`migrate-content-freeze.php` serviert der laufende Web-PHP-Prozess u.U. noch
alten Bytecode — der dry-run zeigt dann den Stand der ALTEN Skript-Version.

- **CLI `php -r "opcache_reset()"` hilft NICHT** — das resettet nur den
  CLI-Prozess, nicht den Web-PHP-FPM-Prozess.
- Reset per **Web-Request**: eine PHP-Datei mit
  `<?php opcache_reset(); echo 'ok';` **im Vhost-Root** ablegen — **NICHT
  innerhalb von `/cms/`**, denn PWs `.htaccess` in `/cms/` fängt dort die
  Requests ab. Datei per Browser/curl aufrufen, danach sofort löschen.
  Alternativ gezielt
  `opcache_invalidate('/home/bioco/public_html/cms/site/templates/migrate-content-freeze.php', true)`.
- Alternativen ohne Reset-Datei: `opcache.revalidate_freq` abwarten, oder im
  PW-Admin aus- und wieder einloggen (frischer PHP-FPM-Worker).

Kontrolle, dass die neue Version läuft: der dry-run-Bericht enthält die
Zeile „Content-Freeze-Migration“ mit Summary — bei Zweifel eine erkennbare
Änderung (z.B. `format=text`) testen.

## 4. Ablauf: dry-run → Review → apply → verify → Bootstrap löschen

Alle Aufrufe über HTTPS; `format=json` liefert maschinenlesbaren Bericht,
`page=slug1,slug2` schränkt auf einzelne Seeds ein.

### 4.1 dry-run (Default — nichts wird geschrieben)

```
https://cms.bioco.ch/bootstrap-content-freeze.php?token=<TOKEN>&mode=dry-run
```

### 4.2 Bericht prüfen (Review)

Statusspalten im Bericht:

| Status | Bedeutung |
|---|---|
| `page-create` | Seite fehlt und würde/wurde angelegt (Template + deutscher Titel aus Seed) |
| `section-create` | Section (per `section_id`) fehlt und wird ans Ende angehängt |
| `field-update` | leeres PW-Feld wird mit Seed-Wert gefüllt (bzw. `FORCE:`-Überschreibung) |
| `skip-cms-wins` | PW-Feld ist nicht leer → bleibt unverändert (nur `force=1` überschreibt) |
| `ok-equal` | PW-Wert ist bereits byte-identisch mit dem Seed |
| `schema-field-add` / `label-update` / `option-labels` | Schema: fehlende Felder, deutsche Labels/Options-Titel |
| `warn` / `error` | Prüfen! (z.B. Seitenname ≠ Slug bei `/anmeldung/danke/`, fehlende Templates) |

Erwartete Punkte beim Review:

- Für `gemuese`, `mitmachen`, `solawi`, `aktuelles`, `home` viele
  `skip-cms-wins`/`ok-equal` — das ist korrekt („CMS gewinnt“).
- `warn` bei `anmeldung-danke`: die PW-Seite unter `/anmeldung/danke/` heisst
  `danke`; `api.php` löst `sections/anmeldung-danke` nur über
  `/content/anmeldung-danke/` oder `name=anmeldung-danke` auf → Follow-up
  nötig, bevor diese eine Seite CMS-getrieben rendert.
- Keine `error`-Zeilen.

### 4.3 apply

```
https://cms.bioco.ch/bootstrap-content-freeze.php?token=<TOKEN>&mode=apply
```

Nur wenn ein bewusstes Überschreiben nötig ist (Vorsicht, überschreibt
Redaktionsinhalte!):

```
...&mode=apply&force=1&page=<slug>
```

### 4.4 verify (byte-genauer Abgleich PW ↔ Seeds)

```
https://cms.bioco.ch/bootstrap-content-freeze.php?token=<TOKEN>&mode=verify
```

- `verify-match` für jedes Feld = OK (JSON-äquivalente `section_config`s und
  api.php-Default-Werte für leere Layout/Theme/Variant-Felder zählen als
  Match).
- `verify-mismatch` zeigt die erste abweichende Byte-Position mit Kontext.
  Bei Seiten mit Live-CMS-Inhalt sind Mismatches erwartbar und korrekt —
  dort gilt der CMS-Stand, nicht der Seed.
- `verify-missing` = Seite/Section/Feld fehlt.

Zusätzlich Frontend-Gegenprobe (liest über dieselbe api.php-Logik):

```bash
curl -s "https://cms.bioco.ch/api/content/sections/solawi" | head -c 600
curl -s "https://cms.bioco.ch/api/content/homepage" | head -c 600
```

### 4.5 Bootstrap löschen (Pflicht)

```bash
ssh bioco@193.33.128.160 'rm /home/bioco/public_html/cms/bootstrap-content-freeze.php'
```

Damit ist der Token verbraucht (Einmal-Token-Modell).

## 5. Rollback

Das Skript löscht/überschreibt ohne `force=1` nichts Vorhandenes — Rollback
heisst also: von der Migration NEU angelegte Inhalte entfernen bzw. Stand
zurückspielen.

1. **Vor dem apply:** DB-Snapshot ziehen (cPanel → Backup oder):
   ```bash
   ssh bioco@193.33.128.160 'mysqldump bioco_cms > /home/bioco/bioco_cms-pre-content-freeze.sql'
   ```
   Wiederherstellen: `mysql bioco_cms < /home/bioco/bioco_cms-pre-content-freeze.sql`
   (stellt ALLE CMS-Inhalte auf den Snapshot zurück, auch spätere
   Redaktionsänderungen — nur unmittelbar nach fehlgeschlagenem apply nutzen).
2. **Chirurgisch:** Der Bericht + das PW-Log `content-freeze` listen jede
   angelegte Seite/Section (`page-create`/`section-create`) und jedes gefüllte
   Feld (`field-update`). Neue Sections lassen sich im Visual Editor oder im
   PW-Admin (Seite → Inhaltsbereiche) einzeln löschen; neu angelegte Seiten im
   PW-Seitenbaum in den Papierkorb verschieben.
3. **Frontend unkritisch:** Solange eine Seite noch hartkodiert rendert bzw.
   Code-Fallbacks hat, ändert ein CMS-Rollback am Live-Rendering nichts —
   erst die `SectionRenderer`-Umstellung macht die Inhalte sichtbar.
4. Nach Rollback ggf. Next.js-Cache aktualisieren:
   ```bash
   ssh bioco@193.33.128.160 'CFG=/home/bioco/public_html/cms/site/config.php; SECRET=$(perl -nE '\''if (/nextRevalidateSecret\s*=\s*"([^"]+)"/) { say $1; exit }'\'' "$CFG"); curl -s -X POST http://127.0.0.1:49154/api/revalidate -H "Content-Type: application/json" --data "{\"secret\":\"$SECRET\",\"tag\":\"cms\"}"'
   ```

## 6. Referenz: Query-Parameter

| Parameter | Werte | Bedeutung |
|---|---|---|
| `mode` | `dry-run` (Default) / `apply` / `verify` | Bericht / Ausführen / Byte-Abgleich |
| `force` | `1` | nur mit `mode=apply`: überschreibt auch nicht-leere PW-Felder |
| `page` | `slug[,slug…]` | nur diese Seeds verarbeiten (z.B. `page=solawi,gemuese`) |
| `format` | `html` (Default) / `json` / `text` | Berichtsformat |
| `token` | String | muss `BIOCO_CONTENT_FREEZE_TOKEN` entsprechen (entfällt bei Superuser-Session) |

## 7. Feld-Mapping (Kurzfassung, Quelle: site/templates/api.php)

| Seed | ProcessWire | api.php liest in |
|---|---|---|
| `sections[].section_id/…title/…eyebrow/…text/…layout/…theme/…component`, `image_alt` | gleichnamige Felder auf dem `content_sections`-Repeater-Item | `buildSectionData()` (~Zeile 526) |
| `sections[].section_config` (Objekt) | JSON-String in `section_config`, sanitisiert + encodiert exakt wie `encodeSectionConfigValue()` | `parseSectionConfigValue()` (~435) |
| `sections[].buttons[0]` / `[1]` | `button_text/button_href/button_variant` bzw. `button2_*` (flache Item-Felder) | `buildSectionButtons()` (~416) |
| `sections[].image_url` | Import in PW-Bildfeld `section_image` (Fallback `image`) | `buildSectionMedia()` (~488) |
| `seo.title` / `seo.description` | `seo_title` / `seo_description` auf der Seite | `getSeoData()` (~295) |
| `hero.hero_title` / `hero.hero_subtitle` / `hero.image_alt` (nur Home) | `hero_headline` / `hero_subtitle` / `image_alt` | `buildHomepageHeroData()` (~625) |
