# Staging mit Softaculous + Divi

> **Welcher Weg gilt?** Für Staging gilt der gewählte Weg **Softaculous + `wordpress/scripts/deploy-wp-code.sh`**. Softaculous verwaltet WordPress-Core, Datenbank und Admin-Zugang; aus dem Repo wird nur eigener Code nach `wp-content/` ausgeliefert. **Bedrock + GitHub-Workflow** bleibt als Alternative und CI-Pfad dokumentiert: [RUNBOOK-STAGING-DIVI.md](RUNBOOK-STAGING-DIVI.md).

Zielgruppe: nicht-technische Betreuung für cPanel, WordPress, Divi und Inhaltsimport; eine Person mit SSH-Zugang führt den Code-Deploy und WP-CLI aus. Das Repo ist öffentlich: keine Passwörter, Schlüssel oder Zugangsdaten in Git, Markdown, Tickets oder Chats speichern.

## 1. Subdomain und PHP in cPanel

### Subdomain und Docroot

In cPanel:

- [ ] `Domains` → `Domains` oder `Subdomains` öffnen
- [ ] Subdomain `staging.bioco.ch` anlegen
- [ ] Einen eigenen, leeren Document Root für Staging auswählen
- [ ] Kontrollieren, dass dieser Ordner **nicht** der Document Root der Live-Seite ist
- [ ] Den vollständigen Document-Root-Pfad aus cPanel in der sicheren Betriebsdokumentation notieren

Wichtig: Der genaue Pfad ist hostabhängig und wird nicht geraten oder im Repo festgeschrieben. Nach der Softaculous-Installation lautet der Deploy-Parameter:

```text
<STAGING-DOCUMENT-ROOT>/wp-content
```

### PHP 8.2 setzen

- [ ] `Software` → `MultiPHP Manager` öffnen
- [ ] `staging.bioco.ch` auswählen
- [ ] PHP-Version auf `PHP 8.2` setzen
- [ ] Einstellung übernehmen
- [ ] Staging-Domain aufrufen und prüfen, dass keine PHP-Fehlerseite erscheint

## 2. WordPress mit Softaculous installieren

In cPanel:

- [ ] `Softaculous Apps Installer` öffnen
- [ ] `Portale/CMS` → `WordPress` wählen
- [ ] `Installieren` starten
- [ ] Als Domain `staging.bioco.ch` wählen
- [ ] Das Installationsverzeichnis leer lassen, damit WordPress direkt in den Staging-Document-Root kommt
- [ ] Vor dem Bestätigen nochmals kontrollieren: **Nicht in den Document Root der Live-Seite installieren**
- [ ] Seitensprache `Deutsch` wählen
- [ ] Einen nicht offensichtlichen Admin-Benutzernamen wählen; niemals `admin`
- [ ] Ein generiertes, starkes Passwort verwenden und nur im Passwortmanager speichern
- [ ] Eine gültige Admin-E-Mail setzen
- [ ] Suchmaschinen-Indexierung für Staging deaktivieren, falls Softaculous diese Option anbietet
- [ ] Installation abschliessen

Danach:

- [ ] Staging-Startseite öffnen
- [ ] In `/wp-admin/` einloggen
- [ ] Den vollständigen Pfad zu `wp-content` notieren: `<STAGING-DOCUMENT-ROOT>/wp-content`
- [ ] Im cPanel-Dateimanager prüfen, dass `wp-includes/`, `wp-content/uploads/` und `wp-content/plugins/` vorhanden sind

Softaculous besitzt und aktualisiert WordPress-Core. Der bioco-Deploy darf weder Core noch Datenbank noch Admin-Benutzer verwalten.

## 3. Erforderliche Plugins installieren

### Secure Custom Fields — ersetzt ACF Pro

**Kein ACF-Pro-Kauf nötig.** Staging läuft auf **Secure Custom Fields (SCF)**, dem GPL-Fork von ACF auf wordpress.org. SCF liefert Repeater, Flexible Content, Options Pages, Gallery, Clone und ACF Blocks, also genau die Funktionen, die der Importer und die Blöcke brauchen.

- [ ] `Plugins` → `Installieren` öffnen
- [ ] Nach `Secure Custom Fields` suchen
- [ ] Plugin installieren und aktivieren
- [ ] Unter `Plugins` prüfen, dass SCF aktiv ist (Staging: Version 6.9.5)

**ACF und ACF Pro dürfen nicht gleichzeitig aktiv sein.** SCF deaktiviert passende ACF-Installationen selbst, und der Importer soll gegen genau ein Feld-Plugin laufen. Wer eine ACF-Pro-Lizenz besitzt, entscheidet sich für eine der beiden Seiten. Eine ACF-Lizenz gehört niemals in eine Datei im Repo.

### WP Mail SMTP

- [ ] `Plugins` → `Installieren` öffnen
- [ ] Nach `WP Mail SMTP` suchen
- [ ] Plugin installieren und aktivieren
- [ ] SMTP-Zugangsdaten nur in wp-admin bzw. der geschützten Hosting-Konfiguration eintragen
- [ ] Test-Mail senden und Empfang prüfen

### Divi

Divi ist kein Plugin, sondern ein lizenziertes Theme. Es wird später separat als Zip unter `Design` → `Themes` hochgeladen. Der Code-Deploy liefert Divi nie aus und löscht es nie.

## 4. Eigenen Code deployen

Der Deploy läuft lokal aus dem Git-Worktree. Host, SSH-User und Pfad sind Parameter; keine Werte werden im Script fest codiert.

### Parameter setzen

In einem lokalen Terminal Platzhalter durch die tatsächlichen Werte aus cPanel ersetzen:

```bash
export BIOCO_WP_HOST='<SSH-HOST>'
export BIOCO_WP_USER='<SSH-USER>'
export BIOCO_WP_CONTENT='<ABSOLUTER-PFAD-ZU-WP-CONTENT>'
export BIOCO_WP_SSH_PORT='22'
```

### Zuerst Probelauf

- [ ] Im Repository-Root stehen
- [ ] Dry-Run ausführen; ohne `--apply` schreibt das Script nichts

```bash
wordpress/scripts/deploy-wp-code.sh
```

- [ ] Ausgabe vollständig lesen
- [ ] Prüfen, dass das Script WordPress erkennt
- [ ] Prüfen, dass `uploads` und `plugins` ausdrücklich als **nicht berührt** gemeldet werden
- [ ] Prüfen, dass nur `bioco-*`, der mu-plugin-Loader und `content-seed` als Ziele erscheinen

### Danach anwenden

Nur wenn der Dry-Run sauber ist:

```bash
wordpress/scripts/deploy-wp-code.sh --apply
```

- [ ] Alle Post-Deploy-Zeilen zeigen `PASS` oder einen ausdrücklich erlaubten WP-CLI-Hinweis
- [ ] Unter `Plugins` → `Must-Use` sind die bioco-Komponenten sichtbar
- [ ] `wp-content/uploads/` ist weiterhin vorhanden
- [ ] Keine fremden Plugins oder Themes wurden verändert

## 5. Divi und Child Theme aktivieren

### Voraussetzungen: bereits erledigt auf staging.bioco.ch

Wer Divi installiert, braucht nur einen WordPress-Zugang, keinen SSH-Zugang. Der Stand auf Staging:

| Voraussetzung | Stand |
|---|---|
| WordPress erreichbar | `https://staging.bioco.ch/`, Login unter `/wp-login.php` |
| Rolle für den Upload | Administrator; ein zweiter Admin-Account existiert neben dem Betriebs-Account |
| Theme-Upload technisch möglich | Dateisystem-Methode `direct`, `wp-content/themes` und `wp-content/upgrade` beschreibbar |
| Upload-Grenze | 100 MB (`upload_max_filesize` und `post_max_size`), Divi-Zip liegt deutlich darunter |
| Child Theme vorhanden | `bioco-divi` liegt bereits unter `wp-content/themes/` und wartet auf das Parent-Theme |
| Suchmaschinen | Indexierung ist ausgeschaltet (`blog_public = 0`) |
| Divi-Schutz beim Deploy | `deploy-wp-code.sh` synchronisiert nur `bioco-*`; Divi wird nie ausgeliefert und nie gelöscht |

Solange Divi fehlt, bleibt das Block-Theme `bioco` aktiv und die Seite rendert vollständig. Divi ist ein Zusatz, keine Bedingung.

### Divi hochladen

- [ ] Divi-Zip direkt aus dem Elegant-Themes-Konto herunterladen
- [ ] In wp-admin `Design` → `Themes` → `Theme hinzufügen` öffnen
- [ ] `Theme hochladen` wählen
- [ ] Divi-Zip hochladen und installieren
- [ ] Divi **nicht** ins Repo kopieren

### Child Theme aktivieren

- [ ] Unter `Design` → `Themes` das Child Theme `bioco-divi` aktivieren
- [ ] Nicht das Parent-Theme `Divi` direkt aktivieren
- [ ] Startseite und wp-admin auf sichtbare Fehler prüfen
- [ ] Stichprobe der Blöcke: `/abos/`, `/wir/`, `/standorte-depots/` und `/kontakt/` aufrufen und prüfen, dass Inhalt und Formular weiterhin erscheinen

Rückweg, falls nach der Aktivierung etwas fehlt: unter `Design` → `Themes` wieder `bioco` aktivieren. Die Inhalte liegen in den mu-plugins und im `post_content`, nicht im Theme, gehen also nicht verloren.

### Divi-Lizenz eintragen

- [ ] `Divi` → `Theme Options` → `Updates` öffnen
- [ ] Lizenzdaten dort eintragen und speichern
- [ ] Update-Prüfung testen

Der Divi-Lizenzschlüssel gehört ausschliesslich über wp-admin in die WordPress-Datenbank. Er gehört **nie** ins Repo, in eine Markdown-Datei, `.env`, ein Ticket oder eine Chat-Nachricht.

## 6. Inhalte importieren und prüfen

WP-CLI-Befehle im WordPress-Root ausführen, also im Ordner direkt oberhalb von `wp-content/`.

### Zuerst: die zwei fehlenden Seiten exportieren

Für `/abos` und `/wir` gibt es noch keine Seed-Datei. Grund: die 17 vorhandenen Seeds halten Inhalt fest, der früher hart im Frontend stand — diese zwei Seiten waren von Anfang an CMS-getrieben, hatten also nie hart kodierten Inhalt. Ihr Inhalt liegt ausschliesslich im laufenden ProcessWire.

Ohne diesen Schritt fehlen nach dem Import zwei Seiten, und die Verweise darauf aus `/solawi` zeigen ins Leere.

Auf einem Rechner ausführen, der `cms.bioco.ch` erreicht:

```bash
php wordpress/scripts/fetch-cms-seed.php --slug=abos
php wordpress/scripts/fetch-cms-seed.php --slug=wir
```

- [ ] Beide Befehle gelaufen
- [ ] Falls die Warnung „nicht abgebildete Felder“ erscheint: prüfen, ob dort Inhalt steckt
- [ ] `php wordpress/scripts/check-seed-plan.php` ist grün und zählt jetzt 19 Seiten statt 17
- [ ] Die zwei neuen Dateien in `wordpress/content-seed/` committen

Ist das CMS nur von einem anderen Rechner erreichbar, nennt die Fehlermeldung den Umweg über `curl` und `--from-file`.

### Import-Probelauf

- [ ] Secure Custom Fields ist aktiv, ACF bzw. ACF Pro ist **nicht** aktiv
- [ ] `wp bioco import` ohne Schreibflag starten

```bash
wp bioco import
```

Der Standard ist Dry-Run. Es wird nichts geschrieben. Der Bericht zeigt, welche Seiten, Navigation und Einstellungen angelegt oder übersprungen würden.

Nützliche Einschränkungen:

```bash
wp bioco import --only=kontakt
wp bioco import --skip-collections
wp bioco import --skip-site-wiring
```

- [ ] Terminal-Bericht prüfen
- [ ] HTML-Bericht unter `wp-content/bioco-import-log/` öffnen und prüfen
- [ ] Fehler beheben, bevor geschrieben wird

### Import anwenden

```bash
wp bioco import --apply
```

- [ ] Import ohne Fehler abschliessen
- [ ] HTML-Bericht erneut prüfen
- [ ] Einige Seiten in wp-admin und im Frontend stichprobenartig öffnen

Der Import ist idempotent: Ein erneuter Lauf erzeugt denselben Zielzustand. Die No-Clobber-Regel schützt bestehende Inhalte: Nicht leere Seiten und bereits gepflegte Werte werden ohne `--force` übersprungen.

`--force` überschreibt bewusst vorhandene Inhalte und darf nur nach geprüftem Backup und ausdrücklicher Freigabe zusammen mit `--apply` verwendet werden:

```bash
wp bioco import --apply --force
```

### Verifikation

- [ ] Reine Leseprüfung ausführen

```bash
wp bioco verify
```

- [ ] Alle Fehler im Terminal- und HTML-Bericht klären
- [ ] Bei Bedarf eine Seite gezielt prüfen: `wp bioco verify --only=home`

## 7. Fehlerbehebung

### bioco-Blöcke erscheinen nicht

Mögliche Ursachen:

- [ ] `wp-content/mu-plugins/bioco-mu-loader.php` fehlt
- [ ] Einer der vier `bioco-*`-mu-plugin-Ordner fehlt oder ist unvollständig
- [ ] Secure Custom Fields ist nicht installiert oder nicht aktiv
- [ ] Deploy erneut zuerst als Dry-Run, dann mit `--apply` ausführen
- [ ] Unter `Plugins` → `Must-Use` kontrollieren, ob die bioco-Komponenten geladen sind

### `wp bioco` ist unbekannt

- [ ] Prüfen, ob WP-CLI auf dem Host installiert ist: `wp --info`
- [ ] Prüfen, ob `bioco-import/bioco-import.php` und der Loader deployt wurden
- [ ] Im WordPress-Root ausführen, nicht in einem beliebigen Verzeichnis
- [ ] Falls WP-CLI fehlt: Hosting-Support um Aktivierung bitten oder Seiten ausdrücklich als manuellen Divi-Fallback in wp-admin aufbauen

### Seiten bleiben leer

- [ ] Prüfen, ob nur `wp bioco import` ohne `--apply` gelaufen ist
- [ ] HTML-Bericht unter `wp-content/bioco-import-log/` lesen
- [ ] Nach geprüftem Dry-Run `wp bioco import --apply` ausführen
- [ ] Nicht reflexartig `--force` verwenden; zuerst prüfen, ob die Seite absichtlich bestehenden Inhalt besitzt

### PHP-Dateien wirken nach Deploy unverändert

PHP-FPM kann durch OPcache noch alten Bytecode ausliefern. Ein CLI-Aufruf von `opcache_reset()` leert nicht zuverlässig den Cache des Web-PHP-FPM-Prozesses.

- [ ] Zuerst Browser-Cache ausschliessen und Seite neu laden
- [ ] Falls der Host eine OPcache-Funktion in cPanel anbietet, diese verwenden
- [ ] Sonst ein einmaliges Reset-Script im Staging-vhost-Root ausserhalb einer Routing-Falle platzieren
- [ ] Reset per Web-Aufruf ausführen, mit `opcache_invalidate()` gezielt die geänderte Datei invalidieren oder `opcache_reset()` ausführen
- [ ] Reset-Script sofort wieder löschen; es darf nicht im Repo oder öffentlich erreichbar bleiben
- [ ] Wenn kein sicherer Reset möglich ist, auf Ablauf von `opcache.revalidate_freq` warten oder den Hosting-Support kontaktieren
