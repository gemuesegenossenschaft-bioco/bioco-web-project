# Content-Seed: Kanonischer Seiteninhalt für die CMS-Migration

Diese JSON-Dateien enthalten den **exakten** Inhalt, der bisher hart im
Next.js-Frontend kodiert war. Sie sind die einzige Quelle für:

1. die idempotente ProcessWire-Migration (`cms/migrations/`), die daraus
   `content_sections` auf den jeweiligen Seiten anlegt bzw. aktualisiert,
2. die Paritäts-Tests im Frontend (`frontend/tests/content-seed-*.test.ts`),
   die sicherstellen, dass bei der Umstellung kein Inhalt verändert wurde.

**Inhalt darf hier nur geändert werden, wenn sich der Website-Text bewusst
ändern soll.** Für die Umstellung auf CMS-Rendering gilt: Byte-genau das
übernehmen, was vorher im JSX stand (inkl. Umlaute, `<strong>`, Links).

## Datei pro Route

`<slug>.json` (Homepage: `home.json`). Schema:

```jsonc
{
  "path": "/impressum/",          // ProcessWire-Seitenpfad
  "slug": "impressum",            // = Frontend-Route und Sections-API-Slug
  "template": "basic-page",       // PW-Template (Home: "home")
  "title": "Impressum",           // PW-Seitentitel
  "seo": {
    "title": "…",                 // <title> — exakt der bisherige metadata.title
    "description": "…"            // exakt die bisherige metadata.description
  },
  "sections": [
    {
      "section_id": "intro",      // stabile ID (kebab-case, pro Seite eindeutig)
      "section_title": "…",       // Überschrift (Plaintext)
      "section_eyebrow": "…",     // optionale Dachzeile
      "section_text": "<p>…</p>", // HTML, exakt wie bisher gerendert
      "section_layout": "rich_text", // rich_text | split_media_text |
                                     // split_text_media | full_width_banner |
                                     // media_grid | video_embed
      "section_theme": "default",
      "section_component": "",    // registrierte Komponente (z.B. contact_form),
                                  // dann zählt section_layout nicht
      "section_config": {},       // Komponenten-Konfiguration (JSON)
      "image_url": "",            // absolute URL zum Import ins PW-Bildfeld
      "image_alt": "",
      "buttons": [                 // max. 2
        { "text": "…", "href": "/mitmachen", "variant": "primary" }
      ]
    }
  ]
}
```

Nur belegte Felder angeben; leere Strings/Arrays weglassen.

## Regeln

- `section_text` ist HTML wie es `SectionRenderer` per
  `dangerouslySetInnerHTML` ausgibt: `<p>`, `<ul>/<li>`, `<h3>`, `<strong>`,
  `<a href>`, `<details>/<summary>` sind erlaubt.
- Interaktive Widgets (Formulare, Karten, Feeds, Kalender) werden NICHT als
  Text abgebildet, sondern als `section_component`
  (siehe `site/templates/component-registry.json`).
- Der wiederkehrende Block «Möchtest du uns kennenlernen?» wird auf jeder
  Seite als eigene Section mit `section_id: "kennenlernen-cta"` geführt,
  damit Redaktion ihn pro Seite anpassen kann.
- Bilder: `image_url` zeigt auf die produktive URL
  (`https://www.bioco.ch/images/…`); die Migration importiert die Datei in
  das ProcessWire-Bildfeld der Section.
