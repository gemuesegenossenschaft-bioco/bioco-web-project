# Roadmap: Volle CMS-Editierbarkeit + Visual-Editor-Neubau (Tracks F & G)

> Stand 2026-07-03. Als Repo-Doc geführt, weil die GitHub-App keine
> Issues-Schreibrechte hat (403). Struktur nach vertikalen Slices.

## Ziel (Goal)

Jedes sichtbare Inhaltselement auf bioco.ch ist in ProcessWire editierbar.
Kein deutscher Text lebt in JSX; kein hartkodierter Fallback verdeckt das CMS.
Inhalte laufen über einen kleinen Satz wiederverwendbarer, deutsch
beschrifteter `content_sections`-Elemente; interaktive Widgets (Formulare,
Karten, Feeds, Rechner) bleiben Code, werden aber als registrierte
`section_component` platziert. Der Visual Editor wird nach First Principles
neu gebaut. Der gerenderte Inhalt ändert sich dabei NICHT (Paritätstests).

## Ausgangslage (Audit 2026-07-03)

- Nur `/abos`, `/wir` und der `(cms)/[...slug]`-Catch-all sind CMS-getrieben.
- 17 Routen enthalten hartkodierten Inhalt; `HomeClient.tsx` rendert
  hartkodierte Fallback-Texte (`willkommen`/`gemeinsam`/`kennenlernen`);
  `app/page.tsx` fällt auf hartkodierte Aktuelles-Items zurück.
- `visual-editor.php`: 3782 Zeilen, ein untypisiertes ~2850-Zeilen-IIFE,
  duplizierte Feldänderungs-Semantik (Drift-Gefahr), 3-schichtige
  Draft-Persistenz.

## Track F — Inhalt vollständig ins CMS

- **F.1** Content-Seed-Schema + byte-genaue Extraktion des gesamten
  hartkodierten Inhalts nach `cms/content-seed/*.json`.
- **F.2** Neue wiederverwendbare Blöcke (minimal): `accordion_item`,
  `steps`, `link_tiles` — Registry + Renderer, deutsche Labels.
- **F.3** Idempotente PW-Migration: deutsche Feld-/Template-Labels,
  Section-Seeding aus den Seeds (`cms/migrations/`).
- **F.4** Einfache Seiten auf Thin-Page-Muster (`/wir`-Vorbild):
  impressum, datenschutz, statuten, anmeldung/danke, newsletter,
  warteliste, tag-der-offenen-tuer, kundenportal.
- **F.5** Hybride/inhaltsreiche Seiten: solawi, gemuese, mitmachen,
  standorte-depots, kontakt, bioco-werden, anmeldung, aktuelles.
- **F.6** Homepage: alle hartkodierten Fallbacks entfernen
  (HomeClient, AktuellesData), nur noch CMS.
- **F.7** Editierbarkeits-Audit kippt auf `cms` für alle Routen;
  `/doi-confirm` als einzige code-eigene Funktionsroute dokumentiert.

Invarianten: keine Inhaltsänderung (Seeds = exakte Extraktion), kein
hartkodierter Fallback, Elemente wiederverwendbar + deutsch beschriftet,
pro Slice erst roter Test, dann Implementierung.

## Track G — Visual Editor Neubau

Behalten (bewährt + getestet): `bioco:visual-editor:*`-Protokollvokabular,
`visualEditorContract.ts`, `visual-editor-focus-fields.json`,
`getVeFieldAttrs`-Marker, `content-publish` mit Fingerprint-Konkurrenz,
Collections-Abstraktion, PW-Fokus-Deeplinks, alle CLAUDE.md-Constraints.

- **G.1** Typisiertes Protokollmodul (Discriminated Unions +
  Origin-Validierung) + purer, unit-getesteter Shell-State-Reducer,
  der `visualEditorContract` importiert (löscht die duplizierte
  Feldänderungs-Switch im PHP-Shell-JS).
- **G.2** Shell-UI auf dem Reducer; `visual-editor.php` wird dünner
  Bootstrap (Auth, Config-JSON, Skelett, `ob_end_clean`/`exit`);
  esbuild-Bundle `site/templates/visual-editor-app.js` im Deploy.
- **G.3** Iframe-Runtime konsolidieren, Homepage-Marker vereinheitlichen,
  tote Endpunkte/Nachrichten entfernen.

## Flankierend

- **C.4** `/abos` linksbündig auf Logo-Kante (Page-Shell-Tokens), keine
  neuen hartkodierten Radien/Schatten.
- **Docs** docs.bioco.ch (Repo `bioco-docs`) aktualisieren.
- **Deploy** via Novatrend gemäss CLAUDE.md; Migration läuft VOR dem
  Build auf dem Server (Bootstrap-Datei, danach löschen). Aus der
  Sandbox ist kein SSH möglich — Deploy-Schritt bleibt manuell
  (`scripts/deploy.sh main`).
