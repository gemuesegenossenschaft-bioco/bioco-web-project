# SEO Implementation Spec - biocò.ch

!!! info "Zweck dieses Dokuments"
    Dies ist die **Umsetzungscheckliste** für Entwickler und Content-Manager.
    Sie enthält alle technischen und inhaltlichen Anforderungen für die SEO-Optimierung.

---

## 📋 Vollständige Seitenübersicht

### Hauptseiten (in Navigation)

| URL Slug | Sichtbarer Name | Navigation | Status | 301 Redirect |
|:---------|:----------------|:-----------|:-------|:-------------|
| `/` | Home | Hauptmenü | ✅ Existing | - |
| `/gemuese` | Gemüse | Hauptmenü | ⚠️ Rename | `/ernte` → `/gemuese` |
| `/abos` | Abos | Hauptmenü | ✅ Existing | - |
| `/standorte-depots` | Standorte | Hauptmenü | ⚠️ Rename | `/depots` → `/standorte-depots` |
| `/wir` | Wir | Hauptmenü | ✅ Existing | - |

### Spezialseiten (nicht in Navigation)

| URL Slug | Sichtbarer Name | Link-Quelle | Status |
|:---------|:----------------|:------------|:-------|
| `/solawi` | Solidarische Landwirtschaft | Von `/wir` verlinkt | 🆕 Neu erstellen |

---

## 🏷️ Meta-Daten pro Seite

### Homepage (`/`)

```yaml
url: /
title: "biocò | Bio-Gemüse aus der Region Baden-Brugg"
description: "Gemüsegenossenschaft biocò: Frisches Demeter-Gemüse aus solidarischer Landwirtschaft. Wöchentliche Gemüsekörbe vom Geisshof in Gebenstorf."
h1: "Frisches Bio-Gemüse aus Baden-Brugg"
keywords: 
  - Gemüsegenossenschaft
  - Baden-Brugg
  - Geisshof
  - Bio Gemüse
```

**Content-Anforderungen:**
- Klare H1-Überschrift above the fold
- Kurzbeschreibung des Angebots (Gemüseabo, Solawi-Konzept)
- Trust-Elemente (Demeter-Logo, Mitgliederzahl)
- Call-to-Action (Abo bestellen, mehr erfahren)

---

### Gemüse (`/gemuese`)

```yaml
url: /gemuese
old_url: /ernte  # 301 Redirect erforderlich!
title: "Saisonales Demeter Gemüse | Was wächst gerade | biocò"
description: "Entdecke unser saisonales Bio-Gemüse in Demeter-Qualität. Frisch vom Geisshof in Gebenstorf für die Region Baden-Brugg."
h1: "Was wächst gerade auf dem Geisshof?"
keywords:
  - Saisonales Gemüse
  - Saisonkalender
  - Was wächst jetzt
  - Gemüse Saison
  - Demeter Gemüse
```

**Content-Anforderungen:**
- Saisonkalender (monatlich aktualisierbar)
- Liste aktuell verfügbares Gemüse
- Bilder vom Feld/Ernte
- Filter/Sortierung nach Monat (optional)

**SEO-Strategie:**
- Monatliche Updates signalisieren Google "Fresh Content"
- Long-tail Keywords: "Saisonales Gemüse März", "Was wächst im Frühling"

---

### Abos (`/abos`)

```yaml
url: /abos
title: "Gemüseabo Baden | Demeter Gemüse wöchentlich | biocò"
description: "Gemüseabo für die Region Baden-Brugg: Wöchentlich frisches Bio-Gemüse in Demeter-Qualität. Solidarische Landwirtschaft vom Geisshof Gebenstorf."
h1: "Dein wöchentliches Gemüseabo"
keywords:
  - Gemüseabo
  - Bio Gemüse
  - Gemüseabo Baden
  - Gemüseabo Brugg
  - Bio Gemüse Kiste
  - Bio Gemüse Lieferung
  - Biogemüse bestellen
```

**Content-Anforderungen:**
- Übersicht Abo-Modelle (Größen, Preise)
- Klare CTAs ("Jetzt Mitglied werden")
- Trust-Elemente (Testimonials, Fotos zufriedener Mitglieder)
- FAQ-Bereich (Wie funktioniert das Abo?)

**Keyword-Integration:**
- "Bio Gemüse Kiste" im Fließtext verwenden
- "Bestellen Sie Ihr Biogemüse direkt vom Hof"
- "Wöchentliche Bio Gemüse Lieferung an Ihr Depot"

---

### Standorte (`/standorte-depots`)

```yaml
url: /standorte-depots
old_url: /depots  # 301 Redirect erforderlich!
visible_name: "Standorte"  # Kurz im Menü
title: "Standorte & Depots Baden-Brugg | Gemüse abholen | biocò"
description: "Gemüseabholung in Baden, Brugg und Umgebung. Finde dein Depot für frisches Bio-Gemüse aus solidarischer Landwirtschaft vom Geisshof Gebenstorf."
h1: "Unsere Depots in der Region"
keywords:
  - Gemüse abholen Baden
  - Gemüse abholen Brugg
  - Depot Baden
  - Depot Brugg
  - Bio Bauernhof (Abholstationen)
```

**Content-Anforderungen:**
- Liste aller Depots mit Adressen (als TEXT, nicht nur Bild!)
- Google Maps Einbettung (optional)
- Öffnungszeiten/Abhol-Infos
- Fotos der Depots

**SEO-Strategie:**
- Adressen in strukturiertem Text für Local SEO
- Jeder Standort als einzelner Abschnitt mit H2 (z.B. "## Depot Baden")
- Keyword "Bio Bauernhof" im Kontext: "Holt euer Gemüse direkt vom Bio Bauernhof ab"

---

### Wir (`/wir`)

```yaml
url: /wir
title: "Über uns | Bio Bauernhof Baden | biocò Gemüsegenossenschaft"
description: "biocò Gemüsegenossenschaft: Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof Gebenstorf. Demeter-zertifiziertes Gemüse für Baden-Brugg."
h1: "Über uns: Die biocò Gemüsegenossenschaft"
keywords:
  - Bio Bauernhof
  - Genossenschaft
  - Team
  - Geisshof
  - Demeter
  - Solidarische Landwirtschaft
```

**Content-Anforderungen:**
- Geschichte der Genossenschaft (seit 2014)
- Vorstellung Team/Gärtner
- Fotos vom Hof
- **Wichtig:** Link zu `/solawi` einfügen (z.B. "→ Mehr über solidarische Landwirtschaft erfahren")

**Keyword-Integration:**
- "Wir bewirtschaften einen Bio Bauernhof in Baden..."
- "Auf dem Geisshof in Gebenstorf..."
- Natürliche Erwähnung "solidarische Landwirtschaft" mit Link

---

### Solawi (`/solawi`) - NEUE SEITE

```yaml
url: /solawi
in_navigation: false  # Orphaned Page!
linked_from: /wir
title: "Was ist Solidarische Landwirtschaft (SoLaWi)? | biocò"
description: "Solidarische Landwirtschaft (Solawi/SoLaWi): Gemeinsam Verantwortung tragen für regionales Bio-Gemüse. Erfahre mehr über unser Konzept auf dem Geisshof."
h1: "Was ist Solidarische Landwirtschaft?"
keywords:
  - Solidarische Landwirtschaft
  - Solawi
  - SoLaWi
  - Solawi Konzept
  - Wie funktioniert Solawi
```

**Content-Anforderungen:**
- Ausführliche Erklärung (500+ Wörter) = Long Form Content
- Was ist Solawi? (Definition)
- Wie funktioniert es? (Prinzipien)
- Warum Solawi? (Vorteile für Mitglieder & Umwelt)
- Solawi bei biocò (unser Modell)
- FAQ zu Solawi

**SEO-Strategie:**
- Ziel: Informational Intent ("Was ist...?", "Wie funktioniert...?")
- Verwende alle Varianten: "Solidarische Landwirtschaft", "Solawi", "SoLaWi"
- Strukturiere mit H2/H3 für Featured Snippets

**Wichtig:**
- Diese Seite erscheint NICHT im Hauptmenü
- Wird nur von `/wir` verlinkt (interner Link)
- Google kann die Seite trotzdem indexieren (via Sitemap + interner Link)

---

## 🔧 Technische Implementierung

### 1. URL-Migrationen (301 Redirects)

```nginx
# In .htaccess oder nginx.conf
Redirect 301 /ernte /gemuese
Redirect 301 /depots /standorte-depots
```

**Wichtig:**
- Nach Implementierung alle internen Links aktualisieren
- Externe Links (Social Media, etc.) informieren

### 2. robots.txt

```text
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /processwire/

Sitemap: https://bioco.ch/sitemap.xml
```

**Referenz:** [Google robots.txt Anleitung](https://developers.google.com/search/docs/crawling-indexing/robots/intro?hl=de)

### 3. sitemap.xml

Alle Seiten inkludieren:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://bioco.ch/</loc>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://bioco.ch/gemuese</loc>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://bioco.ch/abos</loc>
    <changefreq>monthly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://bioco.ch/standorte-depots</loc>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://bioco.ch/wir</loc>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
  <url>
    <loc>https://bioco.ch/solawi</loc>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
</urlset>
```

**Referenz:** [Google Sitemap Anleitung](https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview?hl=de)

### 4. Schema.org Strukturierte Daten

Auf Homepage und Kontaktseite als JSON-LD:

```json
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "biocò Gemüsegenossenschaft",
  "image": "https://bioco.ch/logo.png",
  "description": "Gemüsegenossenschaft für solidarische Landwirtschaft in Baden-Brugg",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Geisshof",
    "addressLocality": "Gebenstorf",
    "postalCode": "5412",
    "addressCountry": "CH"
  },
  "url": "https://bioco.ch",
  "telephone": "+41-XX-XXX-XX-XX",
  "priceRange": "$$"
}
```

**Referenz:** [Google Strukturierte Daten](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data?hl=de)

### 5. Bilder-SEO

**Dateinamen-Konvention:**
- ✅ `karotten-ernte-geisshof-2024.jpg`
- ❌ `IMG_1234.JPG`

**Alt-Text:**
- ✅ "Frisch geerntete Karotten vom Geisshof in Gebenstorf"
- ❌ "Karotten" oder leer

---

## ✅ Umsetzungs-Checkliste

### Phase 1: Content Updates

- [ ] **Homepage:** Keywords "Gemüsegenossenschaft", "Baden-Brugg" natürlich einbauen
- [ ] **`/gemuese`:** Saisonkalender mit aktuellen Gemüsesorten erstellen
- [ ] **`/abos`:** Keywords "Bio Gemüse Kiste", "Bio Gemüse Lieferung", "Biogemüse bestellen" einbauen
- [ ] **`/standorte-depots`:** Alle Depot-Adressen als Text (nicht nur Bild) hinterlegen
- [ ] **`/wir`:** "Bio Bauernhof" natürlich erwähnen + Link zu `/solawi` einfügen
- [ ] **`/solawi`:** Neue Seite erstellen (500+ Wörter, Long-Form Content)
- [ ] **Alle Bilder:** Sprechende Dateinamen + Alt-Texte hinzufügen

### Phase 2: Technische Umsetzung

- [ ] **301 Redirects:** `/ernte` → `/gemuese` und `/depots` → `/standorte-depots`
- [ ] **Interne Links:** Alle Links auf neue URLs aktualisieren
- [ ] **Navigation:** "Standorte" statt "Depots" im Menü
- [ ] **`/solawi`:** NICHT in Navigation aufnehmen, nur von `/wir` verlinken
- [ ] **robots.txt:** Erstellen und hochladen
- [ ] **sitemap.xml:** Generieren/aktualisieren (alle 6 Seiten inkl. `/solawi`)
- [ ] **Schema.org:** JSON-LD auf Homepage implementieren
- [ ] **Meta-Tags:** Alle Titel & Descriptions gemäß Spec setzen

### Phase 3: Monitoring

- [ ] **Google Search Console:** Domain verifizieren
- [ ] **Sitemap einreichen:** `https://bioco.ch/sitemap.xml` in Search Console
- [ ] **URL Inspection:** Alle 6 Seiten auf Crawlbarkeit prüfen
- [ ] **Mobile-Test:** [Google Mobile-Friendly Test](https://search.google.com/test/mobile-friendly?hl=de)
- [ ] **Matomo Analytics:** Tracking-Code auf allen Seiten

---

## 🔍 Verifikation

### Checkliste pro Seite

Für jede der 6 Seiten prüfen:

- [ ] Meta-Titel gesetzt (50-60 Zeichen)
- [ ] Meta-Beschreibung gesetzt (150-160 Zeichen)
- [ ] H1-Tag vorhanden und einzigartig
- [ ] Keywords natürlich im Text verwendet
- [ ] Bilder haben Alt-Texte
- [ ] Interne Verlinkung korrekt
- [ ] Mobile responsive
- [ ] Ladezeit < 3 Sekunden

### Technische Verifikation

- [ ] Alle URLs erreichbar (kein 404)
- [ ] 301 Redirects funktionieren
- [ ] robots.txt erreichbar: `https://bioco.ch/robots.txt`
- [ ] Sitemap erreichbar: `https://bioco.ch/sitemap.xml`
- [ ] Schema.org validiert: [Schema Validator](https://validator.schema.org/)
- [ ] Google Search Console zeigt keine Fehler

---

## 📚 Referenzen

| Thema | Google Dokumentation |
|:------|:---------------------|
| SEO Grundlagen | [SEO Starter Guide](https://developers.google.com/search/docs/fundamentals/seo-starter-guide?hl=de) |
| Hilfreiche Inhalte | [Creating Helpful Content](https://developers.google.com/search/docs/fundamentals/creating-helpful-content?hl=de) |
| Strukturierte Daten | [Structured Data Intro](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data?hl=de) |
| Sitemap | [Sitemaps Overview](https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview?hl=de) |
| robots.txt | [Robots.txt Intro](https://developers.google.com/search/docs/crawling-indexing/robots/intro?hl=de) |
| Mobile-First | [Mobile-First Indexing](https://developers.google.com/search/docs/crawling-indexing/mobile/mobile-sites-mobile-first-indexing?hl=de) |

---

## 📊 Konsistenz-Check

Dieses Dokument ist konsistent mit:
- ✅ [seo-plan.md](seo-plan.md) - Strategische Grundlage
- ✅ Alle URL-Slugs stimmen überein
- ✅ Alle Meta-Daten sind identisch
- ✅ Keyword-Zuordnung ist konsistent

