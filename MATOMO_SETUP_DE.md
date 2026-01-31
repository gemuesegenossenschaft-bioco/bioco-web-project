# Matomo Analytics Einrichtung für bioco.ch

**Status:** ✅ Installiert und konfiguriert
**Instanz:** https://matomo.bioco.ch/
**Site ID:** 1
**Compliance:** Cookie-loses Tracking, Schweizer DSG konform

## Übersicht

Matomo ist für datenschutzfreundliches, cookie-loses Analytics sowohl im ProcessWire CMS als auch im Next.js Frontend konfiguriert. Kein Consent-Banner erforderlich.

## Architektur

**Matomo Instanz:**
- URL: https://matomo.bioco.ch/
- Hosting: Novatrend cPanel Subdomain
- Datenbank: Separate Matomo-Datenbank
- Version: Matomo 5.7.0

**Tracking-Methoden:**
1. **Client-seitig:** Next.js Frontend via MatomoScript Komponente
2. **Server-seitig:** ProcessWire Formulare via MatomoTracker Modul

**Datenschutz-Features:**
- IP-Anonymisierung (2 Bytes)
- Cookie-loses Tracking (keine Einwilligung erforderlich)
- Keine Speicherung personenbezogener Daten
- Schweizer DSG konform

## Installationsschritte

### 1. Matomo Instanz Einrichtung

**Download & Upload:**
1. Neueste Matomo-Version herunterladen: https://matomo.org/download/
2. Auf cPanel Subdomain hochladen: `matomo.bioco.ch`
3. Dateien im Document Root entpacken
4. Installationsassistent im Browser aufrufen

**Datenbank-Konfiguration:**
- Host: `localhost`
- Datenbank: `matomo` (bereits vorhanden)
- Benutzer: Datenbankbenutzer mit vollen Rechten
- Tabellen: Werden automatisch während Installation erstellt

**Initiale Website-Einrichtung:**
- Website-Name: `biocò`
- URL: `https://staging.bioco.ch` (oder Produktions-URL)
- Zeitzone: `Europe/Zurich`
- Site ID: `1` (automatisch vergeben)

### 2. Datenschutz-Einstellungen (DSG Konformität)

**In Matomo Admin → Privatsphäre → Daten anonymisieren:**
- ✅ "Besucher-IP-Adressen anonymisieren" aktivieren (2 Bytes)
- ✅ "Tracking ohne Cookies erzwingen" aktivieren
- Anonymisierungslevel: Mindestens 2 Bytes

**In Matomo Admin → Privatsphäre → Benutzer Opt-Out:**
- "Keine Einwilligung erforderlich" auswählen (cookie-loser Modus)
- Einstellungen speichern

**Ergebnis:** Kein Cookie-Banner nötig, volle DSG-Konformität

### 3. ProcessWire Backend-Konfiguration

**Datei: `/site/config.php`**

Matomo-Konfiguration hinzufügen (bereits enthalten):

```php
// Matomo Analytics (Cookie-los, Schweizer DSG konform)
$config->matomo_enabled = true;
$config->matomo_url = 'https://matomo.bioco.ch/'; // Trailing Slash erforderlich!
$config->matomo_site_id = 1;
```

**MatomoTracker Modul hochladen:**

1. Sicherstellen, dass Modul-Dateien in `/site/modules/MatomoTracker/` existieren
2. Via cPanel oder Deployment auf Server hochladen
3. ProcessWire Admin → Module → Aktualisieren
4. "MatomoTracker" finden und installieren
5. Modul lädt automatisch (als Autoload konfiguriert)

**Modul-Features:**
- Server-seitiges Event-Tracking
- Formular-Übermittlungs-Tracking
- Cookie-loser Modus aktiviert
- Session-basierte Event-Queue

### 4. Next.js Frontend-Konfiguration

**Datei: `/frontend/.env.local` (lokale Entwicklung)**

```env
NEXT_PUBLIC_PROCESSWIRE_BASE_URL=https://staging.bioco.ch

# Matomo Analytics
NEXT_PUBLIC_MATOMO_URL=https://matomo.bioco.ch/
NEXT_PUBLIC_MATOMO_SITE_ID=1
```

**Vercel Produktions-Umgebungsvariablen:**

In Vercel Projekteinstellungen → Environment Variables hinzufügen:
- `NEXT_PUBLIC_MATOMO_URL` = `https://matomo.bioco.ch/`
- `NEXT_PUBLIC_MATOMO_SITE_ID` = `1`

**Komponenten-Integration:**

Bereits konfiguriert in `/frontend/app/layout.tsx`:

```tsx
import { MatomoScript } from '@/components/MatomoScript'

export default function RootLayout({ children }) {
  return (
    <html lang="de">
      <body>
        {children}
        <MatomoScript />
      </body>
    </html>
  )
}
```

**MatomoScript Komponente** (`/frontend/components/MatomoScript.tsx`):
- Lädt Matomo-Tracking-Script
- Cookie-loser Modus aktiviert (`disableCookies`)
- Umgebungsvariablen-basierte Konfiguration
- Hilfsfunktionen: `trackEvent()`, `trackCTA()`

## Deployment Checkliste

### Produktions-Deployment

- [ ] `config.php` auf Produktionsserver hochladen (`/home/bioco/cms/site/config.php`)
- [ ] MatomoTracker Modul auf Produktion hochladen
- [ ] MatomoTracker im ProcessWire Admin installieren
- [ ] Matomo Umgebungsvariablen in Vercel hinzufügen (Produktionsumgebung)
- [ ] Frontend auf Vercel deployen
- [ ] Tracking auf Produktionsseite testen

### Staging-Deployment

- [ ] `config.php` auf Staging-Server hochladen
- [ ] MatomoTracker Modul hochladen
- [ ] Modul installieren
- [ ] Tracking auf Staging testen

## Testing & Verifizierung

### 1. Matomo Installation verifizieren

Aufrufen: https://matomo.bioco.ch/
- In Admin einloggen
- Dashboard lädt korrekt
- Website konfiguriert (Site ID: 1)

### 2. ProcessWire Tracking testen

**Formular-Übermittlungs-Test:**
1. Beliebiges Formular auf cms.bioco.ch absenden
2. In Matomo einloggen
3. Gehe zu: Verhalten → Ereignisse
4. Formular-Übermittlungs-Event sollte sichtbar sein

**Getrackte Events:**
- Formular-Übermittlungen (Kontakt, Newsletter, Warteliste)
- DOI-Bestätigungen
- Event-Anmeldungen

### 3. Next.js Tracking testen

**Seitenaufruf-Test:**
1. Frontend aufrufen (Staging oder Produktion)
2. Browser DevTools → Console öffnen
3. Ausführen: `window._paq`
4. Sollte Array mit Tracking-Befehlen zeigen

**Netzwerk-Test:**
1. DevTools → Netzwerk-Tab
2. Filter: `matomo`
3. Sollte Anfragen zeigen an:
   - `matomo.js` (Script-Laden)
   - `matomo.php` (Tracking-Beacon)

**Echtzeit-Verifizierung:**
1. Seiten im Frontend besuchen
2. In Matomo einloggen
3. Gehe zu: Besucher → Echtzeit
4. Sollte aktive Besuche innerhalb 5-10 Minuten zeigen

### 4. Cookie-losen Modus verifizieren

**Browser-Check:**
1. DevTools → Application → Cookies
2. Domain filtern: `bioco.ch`
3. Sollte **KEINE** Matomo-Cookies zeigen (keine `_pk_*` Cookies)

**Matomo-Einstellungs-Check:**
1. Matomo Admin → Einstellungen → Webseiten
2. Tracking-Code anzeigen
3. Sollte `disableCookies` Befehl enthalten

### 5. Event-Tracking testen

**CTA-Klick-Test:**
1. Beliebigen CTA-Button im Frontend klicken
2. Matomo → Verhalten → Ereignisse
3. Sollte zeigen: Kategorie "CTA", Aktion "Click"

**Custom-Event-Test:**
```typescript
import { trackEvent } from '@/components/MatomoScript'

// Custom Event tracken
trackEvent('Newsletter', 'Subscribe', 'Homepage CTA')
```

## Getrackte Metriken

### Seitenaufrufe
- Alle Frontend-Seiten (Next.js)
- Automatisch via MatomoScript Komponente
- Kein manuelles Tracking nötig

### Events

**CTA-Klicks:**
- Kategorie: `CTA`
- Aktion: `Click`
- Name: Button-Label/Identifier

**Formular-Übermittlungen:**
- Kategorie: `Form`
- Aktion: `Submit`
- Name: Formular-Typ (contact, subscribe, etc.)

**DOI-Bestätigungen:**
- Kategorie: `DOI`
- Aktion: `Confirm`
- Name: E-Mail-Adresse (gehasht)

**Event-Anmeldungen:**
- Kategorie: `Event`
- Aktion: `Signup`
- Name: Event-Titel

### Benutzerverhalten
- Sitzungsdauer
- Seiten pro Sitzung
- Absprungrate
- Ausstiegsseiten
- Einstiegsseiten

### Akquisition
- Referrer-URLs
- Suchbegriffe (falls verfügbar)
- Kampagnen-Tracking (UTM-Parameter)

## Datenschutz & Compliance

### Daten-Anonymisierung

**IP-Adressen:**
- Letzte 2 Bytes anonymisiert
- Beispiel: `192.168.xxx.xxx`
- Konfiguriert in Matomo-Datenschutz-Einstellungen

**Keine personenbezogenen Daten:**
- Keine Cookies gespeichert
- Keine Benutzer-IDs getrackt
- Keine E-Mail-Adressen in Roh-Logs

**Cookie-loses Tracking:**
- Nutzt `config_id` Randomisierung
- Keine Einwilligung nach Schweizer DSG erforderlich
- Konform mit Datenschutz-Vorschriften

### Datenaufbewahrung

**In Matomo konfigurieren:**
1. Admin → Privatsphäre → Daten anonymisieren
2. "Alte Besucherlogs löschen" einstellen
3. Empfohlen: 180 Tage (6 Monate)
4. "Alte Berichte löschen": Optional

### Nutzerrechte (DSG)

**Opt-out-Option:**
- Verfügbar unter Matomo Admin → Privatsphäre → Benutzer Opt-out
- Opt-out-Formular kann auf Datenschutzseite eingebettet werden
- Nutzer können Tracking komplett deaktivieren

**Datenlöschung:**
- Admin kann Besucherdaten löschen
- Tools → DSGVO-Tools
- Löschen nach IP, Benutzer-ID oder Datumsbereich

## Fehlerbehebung

### Matomo lädt nicht

**Problem:** Weißer Bildschirm oder 500-Fehler auf matomo.bioco.ch

**Lösungen:**
1. PHP-Version prüfen (benötigt 8.0+)
2. Verifizieren, dass alle Dateien korrekt entpackt wurden
3. `tmp/` und `config/` Ordner-Berechtigungen prüfen (755 oder 777)
4. Server-Fehlerprotokolle in cPanel prüfen

### Keine Tracking-Daten

**Problem:** Keine Seitenaufrufe oder Events in Matomo

**Prüfen:**
1. Umgebungsvariablen korrekt konfiguriert
2. `window._paq` existiert in Browser-Console
3. Netzwerk-Anfragen an `matomo.php` erfolgreich (keine CORS-Fehler)
4. Site-ID stimmt zwischen Config und Matomo überein
5. Matomo-URL hat Trailing Slash

### ProcessWire Events tracken nicht

**Problem:** Formular-Übermittlungen erscheinen nicht in Matomo

**Prüfen:**
1. MatomoTracker Modul installiert und aktiviert
2. `config.php` hat korrekte Matomo-Einstellungen
3. ProcessWire Session funktioniert (Events in Session gespeichert)
4. ProcessWire Fehlerprotokolle prüfen

### CORS-Fehler

**Problem:** Browser blockiert Matomo-Anfragen

**Lösung:**
1. Sicherstellen, dass Matomo-URL Trailing Slash hat
2. Matomo-Subdomain SSL-Zertifikat gültig prüfen
3. Gleiche Domain verifizieren (bioco.ch → matomo.bioco.ch)

### Cookies werden trotzdem gesetzt

**Problem:** Matomo-Cookies im Browser gefunden

**Prüfen:**
1. Matomo-Datenschutz-Einstellungen: "Tracking ohne Cookies erzwingen" aktiviert
2. MatomoScript.tsx hat `disableCookies` Aufruf
3. Browser-Cache und Cookies löschen
4. In Inkognito-/Privat-Fenster testen

## Wartung

### Regelmäßige Aufgaben

**Wöchentlich:**
- Matomo-Dashboard auf Anomalien prüfen
- Tracking-Fehler überprüfen (falls vorhanden)

**Monatlich:**
- Datenschutz-Konformität überprüfen
- Datenaufbewarungs-Einstellungen kontrollieren
- Matomo aktualisieren falls neue Version verfügbar

**Vierteljährlich:**
- Getrackte Events auditieren
- Tracking überprüfen und optimieren
- Tracking nach Deployments testen

### Updates

**Matomo-Updates:**
1. Matomo-Datenbank sichern
2. In Matomo-Admin einloggen
3. Update-Aufforderungen folgen
4. Tracking nach Update testen

**Modul-Updates:**
- ProcessWire-Modul-Updates prüfen
- Changelog überprüfen
- Via ProcessWire-Admin aktualisieren

## Support & Ressourcen

**Matomo Offizielle Dokumentation:**
- https://matomo.org/docs/
- https://developer.matomo.org/guides/tracking-javascript-guide

**ProcessWire Modul:**
- Pfad: `/site/modules/MatomoTracker/`
- Lädt automatisch bei jedem Request
- Server-seitiges Tracking für Formulare

**Next.js Komponente:**
- Pfad: `/frontend/components/MatomoScript.tsx`
- Client-seitiges Seitenaufruf-Tracking
- Hilfsfunktionen für Events

**Konfigurationsdateien:**
- ProcessWire: `/site/config.php`
- Next.js: `/frontend/.env.local` (lokal)
- Vercel: Environment Variables (Produktion)

## Sicherheitsüberlegungen

**Matomo-Admin-Zugang:**
- Starkes Passwort erforderlich
- Begrenzte Benutzerkonten
- Regelmäßige Passwort-Rotation

**Datenbank-Sicherheit:**
- Separate Matomo-Datenbank
- Begrenzte Berechtigungen
- Kein direkter öffentlicher Zugriff

**Tracking-Daten:**
- IP-Anonymisierung aktiviert
- Keine persönlichen Identifikatoren gespeichert
- Regelmäßige Datenbereinigung

**Config-Datei-Sicherheit:**
- `config.php` von Git ausgeschlossen
- Datei-Berechtigungen: 644
- Sensible Zugangsdaten sicher gespeichert

## Zusammenfassung

**Matomo Status:** ✅ Vollständig konfiguriert und betriebsbereit

**Hauptfunktionen:**
- Cookie-loses Tracking (kein Consent-Banner)
- Schweizer DSG konform
- Client + Server-seitiges Tracking
- Datenschutz-first Analytics

**Zugang:**
- Matomo Admin: https://matomo.bioco.ch/
- Site ID: 1
- Tracking: Automatisch auf allen Seiten

**Nächste Schritte:**
- Analytics im Matomo-Dashboard überwachen
- Tracking-Events überprüfen und optimieren
- Datenschutz-Konformität aufrechterhalten
