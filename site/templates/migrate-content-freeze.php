<?php namespace ProcessWire;

/**
 * Content-Freeze-Migration: Seedet ProcessWire aus cms/content-seed/*.json
 * =========================================================================
 *
 * Überträgt den bisher im Next.js-Frontend hartkodierten Seiteninhalt
 * byte-genau in ProcessWire (content_sections-Repeater + SEO-Felder),
 * damit jede Seite CMS-getrieben werden kann — mit EXAKT dem heutigen Inhalt.
 *
 * WICHTIG: Dieses Skript läuft NUR innerhalb eines ProcessWire-Bootstraps.
 * Es wird per Bootstrap-Datei im CMS-Webroot eingebunden (siehe
 * docs/content-freeze-migration.md):
 *
 *   <?php
 *   // /home/bioco/public_html/cms/bootstrap-content-freeze.php — NACH GEBRAUCH LÖSCHEN
 *   define('BIOCO_CONTENT_FREEZE_TOKEN', '<langer-zufälliger-token>');
 *   require __DIR__ . '/index.php';
 *   require \ProcessWire\wire('config')->paths->templates . 'migrate-content-freeze.php';
 *
 * Aufruf:
 *   https://cms.bioco.ch/bootstrap-content-freeze.php?token=...&mode=dry-run
 *
 * Query-Parameter:
 *   mode=dry-run   (Default) Nur Bericht: was WÜRDE passieren, keine Writes.
 *   mode=apply     Führt die Migration aus.
 *   mode=verify    Vergleicht PW-Inhalt byte-genau mit den Seeds (nach apply).
 *   force=1        Nur mit mode=apply: überschreibt auch NICHT-leere PW-Felder.
 *                  Ohne force gilt: "CMS gewinnt" — vorhandene Inhalte bleiben.
 *   page=slug[,..] Nur diese Seed-Slugs verarbeiten (z.B. page=solawi,gemuese).
 *   format=json    JSON- statt HTML-Bericht.
 *   token=...      Muss BIOCO_CONTENT_FREEZE_TOKEN entsprechen (alternativ:
 *                  eingeloggter Superuser, dann ist token optional).
 *
 * Sicherheitsmodell:
 *   - Läuft nie ohne ProcessWire-Bootstrap (PROCESSWIRE-Konstante).
 *   - Verweigert Ausführung über unverschlüsseltes HTTP (ausser CLI).
 *   - Verlangt eingeloggten Superuser ODER gültigen Einmal-Token (>= 20 Zeichen,
 *     hash_equals-Vergleich).
 *   - Default-Modus ist dry-run; apply/force müssen explizit gesetzt werden.
 *   - Additiv & idempotent: Sections werden über section_id gematcht, bestehende
 *     nicht-leere Felder werden ohne force NIE überschrieben, es wird NIE etwas
 *     gelöscht oder umsortiert, Seiten ausserhalb der Seeds werden NIE berührt.
 *
 * Feld-Speicherung (reverse-engineered aus site/templates/api.php):
 *   - Sections: Repeater "content_sections", Items mit section_id, section_title,
 *     section_eyebrow, section_text, section_layout, section_theme,
 *     section_component, section_config (JSON-String), image_alt
 *     (api.php buildSectionData, ~Zeile 526).
 *   - Buttons: flache Felder button_text/button_href/button_variant und
 *     button2_text/button2_href/button2_variant auf dem Repeater-Item
 *     (api.php buildSectionButtons, ~Zeile 416).
 *   - Bild: PW-Bildfeld section_image (Fallback: image) auf dem Item
 *     (api.php buildSectionData ~Zeile 561, buildSectionMedia ~Zeile 488).
 *     api.php kennt KEIN URL-Textfeld für Bilder; image_url aus dem Seed wird
 *     deshalb (falls vorhanden) in das PW-Bildfeld importiert.
 *   - SEO: seo_title / seo_description auf der Seite (api.php getSeoData ~Zeile 295).
 *   - Hero (nur Home): hero_headline / hero_subtitle / hero_image / image_alt
 *     (api.php buildHomepageHeroData ~Zeile 625). Seed-Key hero_title wird auf
 *     hero_headline gemappt.
 *
 * Seed-Verzeichnis: standardmässig site/templates/content-seed/ (per rsync aus
 * cms/content-seed/ des Repos befüllt). Überschreibbar via Konstante
 * BIOCO_CONTENT_FREEZE_SEED_DIR in der Bootstrap-Datei.
 *
 * Log: PW-Log "content-freeze".
 */

if (!defined('PROCESSWIRE')) {
    http_response_code(500);
    die('migrate-content-freeze.php muss über einen ProcessWire-Bootstrap eingebunden werden (siehe docs/content-freeze-migration.md).');
}

if (!class_exists('ProcessWire\\BiocoContentFreezeMigration')) {

class BiocoContentFreezeMigration
{
    const LOG_NAME = 'content-freeze';
    const REPEATER_FIELD = 'content_sections';
    const REPEATER_TPL_PREFIX = 'repeater_content_sections';

    /** Seed-Section-Key => PW-Feld (1:1 Textfelder) */
    const SECTION_TEXT_FIELDS = [
        'section_title'     => 'section_title',
        'section_eyebrow'   => 'section_eyebrow',
        'section_text'      => 'section_text',
        'section_layout'    => 'section_layout',
        'section_theme'     => 'section_theme',
        'section_component' => 'section_component',
        'image_alt'         => 'image_alt',
    ];

    /** Button-Slot => PW-Felder (api.php buildSectionButtons) */
    const BUTTON_FIELDS = [
        0 => ['text' => 'button_text',  'href' => 'button_href',  'variant' => 'button_variant'],
        1 => ['text' => 'button2_text', 'href' => 'button2_href', 'variant' => 'button2_variant'],
    ];

    /** Defaults, die api.php beim Lesen leerer Felder ausgibt (für verify). */
    const API_DEFAULTS = [
        'section_layout'  => 'split_media_text', // api.php ~529
        'section_theme'   => 'default',          // api.php ~530
        'button_variant'  => 'primary',          // api.php ~422
        'button2_variant' => 'secondary',        // api.php ~430
    ];

    private string $mode;
    private bool $force;
    private array $onlySlugs;
    private string $seedDir;
    private string $assetBase;

    private array $rows = [];
    private array $logLines = [];
    private array $counts = [];
    /** Im dry-run geplante Seitenpfade (Eltern-Auflösung für verschachtelte Seeds). */
    private array $plannedPagePaths = [];

    public function __construct(string $mode, bool $force, array $onlySlugs, string $seedDir, string $assetBase)
    {
        $this->mode = $mode;
        $this->force = $force && $mode === 'apply';
        $this->onlySlugs = $onlySlugs;
        $this->seedDir = rtrim($seedDir, '/') . '/';
        $this->assetBase = rtrim($assetBase, '/');
    }

    // ------------------------------------------------------------------
    // Reporting helpers
    // ------------------------------------------------------------------

    private function row(string $page, string $section, string $field, string $status, string $detail = ''): void
    {
        $this->rows[] = [
            'page' => $page,
            'section' => $section,
            'field' => $field,
            'status' => $status,
            'detail' => $detail,
        ];
        $this->counts[$status] = ($this->counts[$status] ?? 0) + 1;
        if (in_array($status, ['error', 'warn', 'page-create', 'section-create', 'field-update', 'image-import', 'verify-mismatch', 'verify-missing'], true)) {
            $this->logLines[] = strtoupper($status) . " [{$page}" . ($section !== '' ? "#{$section}" : '') . ($field !== '' ? "/{$field}" : '') . "] {$detail}";
        }
    }

    private function excerpt($value, int $len = 120): string
    {
        $v = (string) $value;
        $v = preg_replace('/\s+/u', ' ', $v);
        if (function_exists('mb_strlen') && mb_strlen($v) > $len) {
            return mb_substr($v, 0, $len) . '…';
        }
        return strlen($v) > $len ? substr($v, 0, $len) . '…' : $v;
    }

    private function isApply(): bool
    {
        return $this->mode === 'apply';
    }

    /** Präfix für geplante vs. ausgeführte Aktionen. */
    private function verb(): string
    {
        return $this->isApply() ? '' : 'WÜRDE: ';
    }

    // ------------------------------------------------------------------
    // Seed loading
    // ------------------------------------------------------------------

    private function loadSeeds(): array
    {
        if (!is_dir($this->seedDir)) {
            throw new WireException("Seed-Verzeichnis nicht gefunden: {$this->seedDir} (rsync cms/content-seed/ dorthin, siehe Runbook).");
        }
        $files = glob($this->seedDir . '*.json') ?: [];
        $seeds = [];
        foreach ($files as $file) {
            $json = file_get_contents($file);
            $seed = json_decode((string) $json, true);
            if (!is_array($seed)) {
                throw new WireException('Seed ist kein gültiges JSON: ' . basename($file));
            }
            $this->validateSeed($seed, basename($file));
            $seeds[] = $seed;
        }
        if (!count($seeds)) {
            throw new WireException("Keine Seed-Dateien in {$this->seedDir} gefunden.");
        }
        if ($this->onlySlugs) {
            $seeds = array_values(array_filter($seeds, fn($s) => in_array($s['slug'], $this->onlySlugs, true)));
            if (!count($seeds)) {
                throw new WireException('Kein Seed passt auf page=' . implode(',', $this->onlySlugs));
            }
        }
        // Eltern vor Kindern verarbeiten (/anmeldung/ vor /anmeldung/danke/)
        usort($seeds, function ($a, $b) {
            $da = substr_count(trim($a['path'], '/'), '/');
            $db = substr_count(trim($b['path'], '/'), '/');
            return $da === $db ? strcmp($a['path'], $b['path']) : $da <=> $db;
        });
        return $seeds;
    }

    private function validateSeed(array $seed, string $file): void
    {
        foreach (['path', 'slug', 'template', 'title', 'sections'] as $key) {
            if (empty($seed[$key])) {
                throw new WireException("Seed {$file}: Pflichtfeld '{$key}' fehlt oder ist leer.");
            }
        }
        if (!is_array($seed['sections'])) {
            throw new WireException("Seed {$file}: 'sections' muss ein Array sein.");
        }
        $seen = [];
        foreach ($seed['sections'] as $i => $section) {
            $sid = (string) ($section['section_id'] ?? '');
            if ($sid === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $sid)) {
                throw new WireException("Seed {$file}: sections[{$i}] hat keine gültige section_id.");
            }
            if (isset($seen[$sid])) {
                throw new WireException("Seed {$file}: doppelte section_id '{$sid}'.");
            }
            $seen[$sid] = true;
            if (isset($section['buttons']) && (!is_array($section['buttons']) || count($section['buttons']) > 2)) {
                throw new WireException("Seed {$file}#{$sid}: 'buttons' muss ein Array mit max. 2 Einträgen sein.");
            }
            if (isset($section['section_config']) && !is_array($section['section_config'])) {
                throw new WireException("Seed {$file}#{$sid}: 'section_config' muss ein JSON-Objekt sein.");
            }
        }
    }

    // ------------------------------------------------------------------
    // Value helpers
    // ------------------------------------------------------------------

    /** Roh-Wert eines Feldes als String, ohne Output-Formatting (byte-genau). */
    private function rawString(Page $item, string $fieldName): string
    {
        $item->of(false);
        $v = $item->get($fieldName);
        if ($v === null) return '';
        if ($v instanceof Pageimages || $v instanceof Pagefiles) {
            return $v->count() ? '[' . $v->count() . ' Datei(en)]' : '';
        }
        if ($v instanceof Pageimage || $v instanceof Pagefile) {
            return '[Datei]';
        }
        if (is_object($v)) {
            // FieldtypeOptions: Wert (nicht Titel) vergleichen
            if (class_exists('ProcessWire\\SelectableOptionArray') && $v instanceof SelectableOptionArray) {
                $first = $v->first();
                return $first ? (string) $first->value : '';
            }
            if (class_exists('ProcessWire\\SelectableOption') && $v instanceof SelectableOption) {
                return (string) $v->value;
            }
        }
        return (string) $v;
    }

    private function isEmptyValue(Page $item, string $fieldName): bool
    {
        $item->of(false);
        $v = $item->get($fieldName);
        if ($v instanceof Pageimages || $v instanceof Pagefiles) return $v->count() === 0;
        return trim($this->rawString($item, $fieldName)) === '';
    }

    /**
     * section_config exakt wie api.php speichern: gleiche Sanitisierung
     * (sanitizeSectionConfigValue, api.php ~Zeile 443: Keys auf [a-z0-9_-],
     * Strings max. 400 Zeichen, Kontrollzeichen entfernt, Tiefe max. 6) und
     * gleiche json_encode-Flags (encodeSectionConfigValue, api.php ~Zeile 464).
     * So ist der gespeicherte String byte-identisch mit dem, was content-save/
     * content-publish später schreiben würden (stabile VE-Fingerprints).
     */
    private function sanitizeConfigValue($value, int $depth = 0)
    {
        if ($depth > 6) return null;
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        if (is_string($value)) {
            $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
            return mb_substr((string) $clean, 0, 400);
        }
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $child) {
                $safeKey = is_int($key) ? $key : preg_replace('/[^a-z0-9_-]+/i', '', (string) $key);
                if ($safeKey === '') continue;
                $normalized[$safeKey] = $this->sanitizeConfigValue($child, $depth + 1);
            }
            return $normalized;
        }
        return null;
    }

    private function encodeConfig(array $config): string
    {
        $normalized = $this->sanitizeConfigValue($config);
        if (!is_array($normalized) || !count($normalized)) return '';
        return (string) json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Kanonische JSON-Form (rekursiv sortierte Keys) für äquivalenz-Vergleiche. */
    private function canonicalJson($value): string
    {
        $normalize = function ($v) use (&$normalize) {
            if (!is_array($v)) return $v;
            $isList = array_keys($v) === range(0, count($v) - 1);
            if (!$isList) ksort($v);
            foreach ($v as $k => $child) $v[$k] = $normalize($child);
            return $v;
        };
        return (string) json_encode($normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Erste Byte-Position, an der zwei Strings divergieren (für Mismatch-Berichte). */
    private function firstDiff(string $a, string $b): string
    {
        $len = min(strlen($a), strlen($b));
        for ($i = 0; $i < $len; $i++) {
            if ($a[$i] !== $b[$i]) break;
        }
        if ($i >= $len && strlen($a) === strlen($b)) return '';
        $ctxA = substr($a, max(0, $i - 20), 60);
        $ctxB = substr($b, max(0, $i - 20), 60);
        return "Byte {$i}: PW=»" . $this->excerpt($ctxA, 70) . "« Seed=»" . $this->excerpt($ctxB, 70) . '«';
    }

    // ------------------------------------------------------------------
    // Schema: Felder, Repeater, deutsche Labels
    // ------------------------------------------------------------------

    /** Deutsche Labels/Beschreibungen für die Redaktions-UI (idempotent). */
    private function fieldLabelPlan(): array
    {
        return [
            'content_sections'  => ['Inhaltsbereiche', 'Wiederholbare Inhaltsbereiche dieser Seite. Reihenfolge = Reihenfolge auf der Website.'],
            'section_id'        => ['Bereichs-ID', 'Stabile Kennung (kleinbuchstaben-mit-bindestrich). Nicht ändern — wird vom Frontend und Visual Editor referenziert.'],
            'section_title'     => ['Titel', 'Überschrift dieses Bereichs (wird als H2 gerendert). Wird unterdrückt, wenn der Text bereits eine Überschrift enthält.'],
            'section_eyebrow'   => ['Eyebrow (Dachzeile)', 'Kleine Zeile über dem Titel.'],
            'section_text'      => ['Text', 'Inhalt als HTML (Editor). Links, Listen und Überschriften sind erlaubt.'],
            'section_image'     => ['Bild', 'Bild für diesen Bereich.'],
            'section_images'    => ['Bilder', 'Mehrere Bilder für Galerien oder Raster.'],
            'image_alt'         => ['Bild Alt-Text', 'Alternativtext für Barrierefreiheit und SEO.'],
            'section_layout'    => ['Layout', 'Anordnung von Text und Bild. Erlaubte Werte: rich_text (Fliesstext), split_media_text (Bild links, Text rechts), split_text_media (Text links, Bild rechts), full_width_banner (Banner, volle Breite), media_grid (Bild-Raster), video_embed (Video), component (Komponenten-Block).'],
            'section_theme'     => ['Farbschema', 'Farbstimmung des Bereichs. Erlaubte Werte: default (Standard), muted (Gedimmt), accent (Akzent), dark (Dunkel).'],
            'section_component' => ['Komponente', 'Registrierte Frontend-Komponente (z.B. contact_form). Leer lassen für normale Text-/Bild-Bereiche.'],
            'section_config'    => ['Komponenten-Einstellungen (JSON)', 'Konfiguration der Komponente. Wird primär durch den Visual Editor gepflegt.'],
            'button_text'       => ['Button 1: Text', 'Beschriftung des ersten Buttons. Leer = kein Button.'],
            'button_href'       => ['Button 1: Link', 'Ziel-URL, z.B. /kontakt'],
            'button_variant'    => ['Button 1: Stil', 'Primär (grün) oder Sekundär (weiss).'],
            'button2_text'      => ['Button 2: Text', 'Beschriftung des zweiten Buttons. Leer = kein Button.'],
            'button2_href'      => ['Button 2: Link', 'Ziel-URL, z.B. /standorte-depots'],
            'button2_variant'   => ['Button 2: Stil', 'Primär (grün) oder Sekundär (weiss).'],
            'seo_title'         => ['SEO-Titel', 'Seitentitel für Suchmaschinen (<title>). Leer = Seitentitel.'],
            'seo_description'   => ['SEO-Beschreibung', 'Meta-Beschreibung für Suchmaschinen.'],
            'hero_headline'     => ['Hero: Überschrift', 'Haupttitel der Startseiten-Herosektion.'],
            'hero_subtitle'     => ['Hero: Untertitel', 'Untertitel unter der Hero-Überschrift.'],
            'hero_image'        => ['Hero: Bild', 'Bild der Startseiten-Herosektion.'],
        ];
    }

    /** Deutsche Options-Labels (nur wenn das Feld eine Optionsliste besitzt). */
    private function optionLabelPlan(): array
    {
        return [
            'section_layout' => [
                'rich_text'         => 'Fliesstext',
                'split_media_text'  => 'Geteilt: Bild links, Text rechts',
                'split_text_media'  => 'Geteilt: Text links, Bild rechts',
                'full_width_banner' => 'Banner (volle Breite)',
                'media_grid'        => 'Bild-Raster',
                'video_embed'       => 'Video einbetten',
                'component'         => 'Komponenten-Block',
            ],
            'section_theme' => [
                'default' => 'Standard',
                'muted'   => 'Gedimmt',
                'accent'  => 'Akzent',
                'dark'    => 'Dunkel',
            ],
            'button_variant' => [
                'primary'   => 'Primär (grün)',
                'secondary' => 'Sekundär (weiss)',
            ],
            'button2_variant' => [
                'primary'   => 'Primär (grün)',
                'secondary' => 'Sekundär (weiss)',
            ],
        ];
    }

    /** Fehlt ein Repeater-Subfeld, wird es mit diesem Typ angelegt. */
    private function repeaterFieldTypes(): array
    {
        return [
            'section_id'        => 'FieldtypeText',
            'section_title'     => 'FieldtypeText',
            'section_eyebrow'   => 'FieldtypeText',
            'section_text'      => 'FieldtypeTextarea',
            'section_layout'    => 'FieldtypeText',
            'section_theme'     => 'FieldtypeText',
            'section_component' => 'FieldtypeText',
            'section_config'    => 'FieldtypeTextarea',
            'image_alt'         => 'FieldtypeText',
            'button_text'       => 'FieldtypeText',
            'button_href'       => 'FieldtypeText',
            'button_variant'    => 'FieldtypeText',
            'button2_text'      => 'FieldtypeText',
            'button2_href'      => 'FieldtypeText',
            'button2_variant'   => 'FieldtypeText',
        ];
    }

    private function ensureSchema(): void
    {
        $fields = wire('fields');

        if (!$fields->get(self::REPEATER_FIELD)) {
            // Repeater programmatisch anzulegen ist riskant (Repeater-Template +
            // Fieldgroup + Parent-Verdrahtung); in Produktion existiert das Feld.
            throw new WireException("Feld 'content_sections' (Repeater) existiert nicht — Abbruch. Bitte zuerst das Basis-Setup prüfen.");
        }

        // 1) Fehlende Subfelder anlegen + in alle repeater_content_sections-Fieldgroups hängen
        foreach ($this->repeaterFieldTypes() as $name => $type) {
            $field = $fields->get($name);
            if (!$field) {
                if ($this->isApply()) {
                    $field = new Field();
                    $field->type = wire('modules')->get($type);
                    $field->name = $name;
                    if ($name === 'section_text' && wire('modules')->isInstalled('InputfieldCKEditor')) {
                        $field->inputfieldClass = 'InputfieldCKEditor';
                    }
                    $fields->save($field);
                }
                $this->row('(Schema)', '', $name, 'schema-field-add', $this->verb() . "Feld anlegen ({$type})");
                if (!$this->isApply()) continue;
            }
            foreach (wire('templates') as $template) {
                if (strpos((string) $template->name, self::REPEATER_TPL_PREFIX) !== 0) continue;
                $fg = $template->fieldgroup;
                if (!$fg || !$field || $fg->hasField($field)) continue;
                if ($this->isApply()) {
                    $fg->add($field);
                    $fg->save();
                }
                $this->row('(Schema)', '', $name, 'schema-field-add', $this->verb() . "Feld zu {$template->name} hinzufügen");
            }
        }

        // 2) SEO-Felder global sicherstellen (getSeoData, api.php ~295)
        foreach (['seo_title' => 'FieldtypeText', 'seo_description' => 'FieldtypeTextarea'] as $name => $type) {
            if (!$fields->get($name)) {
                if ($this->isApply()) {
                    $field = new Field();
                    $field->type = wire('modules')->get($type);
                    $field->name = $name;
                    $fields->save($field);
                }
                $this->row('(Schema)', '', $name, 'schema-field-add', $this->verb() . "Feld anlegen ({$type})");
            }
        }

        // 3) Deutsche Labels/Beschreibungen (idempotent)
        foreach ($this->fieldLabelPlan() as $name => [$label, $description]) {
            $field = $fields->get($name);
            if (!$field) continue;
            $changes = [];
            if ((string) $field->label !== $label) $changes[] = "Label »{$field->label}« → »{$label}«";
            if ((string) $field->description !== $description) $changes[] = 'Beschreibung aktualisieren';
            if (!$changes) continue;
            if ($this->isApply()) {
                $field->label = $label;
                $field->description = $description;
                $fields->save($field);
            }
            $this->row('(Schema)', '', $name, 'label-update', $this->verb() . implode('; ', $changes));
        }

        // 4) Deutsche Options-Labels (nur für Options-/Select-Felder)
        foreach ($this->optionLabelPlan() as $name => $optionMap) {
            $field = $fields->get($name);
            if (!$field) continue;
            $this->ensureOptionLabels($field, $optionMap);
        }
    }

    /**
     * Options-Labels deutsch setzen. Unterstützt FieldtypeOptions (setOptions,
     * Muster aus site/classes/EventSetup.php) und Felder mit "value|Label"-
     * Optionszeilen (Muster aus cms/translate-labels-german.php). Freitextfelder
     * haben keine Optionsliste und werden übersprungen.
     */
    private function ensureOptionLabels(Field $field, array $optionMap): void
    {
        $type = $field->type;

        if (method_exists($type, 'getOptions') && method_exists($type, 'setOptions')) {
            $options = $type->getOptions($field);
            $changed = false;
            $seen = [];
            foreach ($options as $option) {
                $value = (string) $option->value;
                if (!array_key_exists($value, $optionMap)) continue;
                $seen[$value] = true;
                if ((string) $option->title !== $optionMap[$value]) {
                    if ($this->isApply()) $option->title = $optionMap[$value];
                    $changed = true;
                }
            }
            foreach ($optionMap as $value => $title) {
                if (isset($seen[$value])) continue;
                if ($this->isApply()) {
                    $option = new SelectableOption();
                    $option->value = $value;
                    $option->title = $title;
                    $options->add($option);
                }
                $changed = true;
            }
            if ($changed) {
                if ($this->isApply()) $type->setOptions($field, $options);
                $this->row('(Schema)', '', $field->name, 'option-labels', $this->verb() . 'Options-Titel deutsch setzen (FieldtypeOptions)');
            }
            return;
        }

        $existing = (string) $field->get('options');
        $isSelectish = $existing !== '' || stripos((string) $field->get('inputfieldClass'), 'select') !== false;
        if (!$isSelectish) {
            // Freitextfeld (so legt diese Migration section_layout/section_theme/
            // button_variant an): eine echte Optionsliste gibt es nicht, daher
            // können keine Options-Titel gesetzt werden. Die deutschen
            // Werte-Bedeutungen stehen stattdessen in der Feldbeschreibung
            // (fieldLabelPlan). Wir melden das transparent statt still zu überspringen.
            $this->row('(Schema)', '', $field->name, 'option-labels-skip', 'Freitextfeld — deutsche Werte stehen in der Feldbeschreibung (keine Optionsliste zu übersetzen)');
            return;
        }

        $lines = [];
        $seen = [];
        foreach (preg_split('/\r?\n/', $existing) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = explode('|', $line, 2);
            $value = trim($parts[0]);
            $label = isset($optionMap[$value]) ? $optionMap[$value] : (isset($parts[1]) ? trim($parts[1]) : $value);
            $lines[] = "{$value}|{$label}";
            $seen[$value] = true;
        }
        foreach ($optionMap as $value => $label) {
            if (!isset($seen[$value])) $lines[] = "{$value}|{$label}";
        }
        $next = implode("\n", $lines);
        if ($next === $existing) return;
        if ($this->isApply()) {
            $field->set('options', $next);
            wire('fields')->save($field);
        }
        $this->row('(Schema)', '', $field->name, 'option-labels', $this->verb() . 'Options-Labels deutsch setzen (value|Label-Zeilen)');
    }

    // ------------------------------------------------------------------
    // Page resolution / creation
    // ------------------------------------------------------------------

    /**
     * Zielseite exakt so auflösen, wie api.php sie liest:
     * - home:   /content/homepage/ dann / (api.php 'homepage', ~Zeile 2090)
     * - andere: /content/{slug}/ dann name={slug} (api.php 'sections', ~Zeile 2041),
     *           zusätzlich Fallback auf den Seed-Pfad (für verschachtelte Pfade).
     */
    private function resolvePage(array $seed): ?Page
    {
        $pages = wire('pages');
        $slug = (string) $seed['slug'];

        if ($slug === 'home') {
            $p = $pages->get('/content/homepage/');
            if ($p->id) return $p;
            $p = $pages->get('/');
            return $p->id ? $p : null;
        }

        $candidates = [];
        $candidates[] = $pages->get("/content/{$slug}/");
        $candidates[] = $pages->get('name=' . wire('sanitizer')->pageName($slug));
        $candidates[] = $pages->get((string) $seed['path']);

        foreach ($candidates as $p) {
            if (!$p || !$p->id) continue;
            if (method_exists($p, 'isTrash') && $p->isTrash()) continue;
            $tplName = $p->template ? (string) $p->template->name : '';
            if (in_array($tplName, ['admin', 'api'], true)) continue;
            if (strpos($tplName, 'repeater_') === 0) continue; // nie Repeater-Items matchen
            return $p;
        }
        return null;
    }

    private function ensureFamilyAllows(Template $parentTpl, Template $childTpl, string $slug): void
    {
        if ((int) $parentTpl->noChildren === 1) {
            if ($this->isApply()) {
                $parentTpl->noChildren = 0;
                wire('templates')->save($parentTpl);
            }
            $this->row($slug, '', '', 'schema-field-add', $this->verb() . "Template '{$parentTpl->name}': noChildren aufheben (Kindseite nötig)");
        }
        $childTemplates = is_array($parentTpl->childTemplates) ? array_map('intval', $parentTpl->childTemplates) : $parentTpl->childTemplates;
        if (is_array($childTemplates) && count($childTemplates) && !in_array((int) $childTpl->id, $childTemplates, true)) {
            if ($this->isApply()) {
                $childTemplates[] = $childTpl->id;
                $parentTpl->childTemplates = $childTemplates;
                wire('templates')->save($parentTpl);
            }
            $this->row($slug, '', '', 'schema-field-add', $this->verb() . "Template '{$parentTpl->name}': '{$childTpl->name}' als Kind-Template erlauben");
        }
        $parentTemplates = is_array($childTpl->parentTemplates) ? array_map('intval', $childTpl->parentTemplates) : $childTpl->parentTemplates;
        if (is_array($parentTemplates) && count($parentTemplates) && !in_array((int) $parentTpl->id, $parentTemplates, true)) {
            if ($this->isApply()) {
                $parentTemplates[] = $parentTpl->id;
                $childTpl->parentTemplates = $parentTemplates;
                wire('templates')->save($childTpl);
            }
            $this->row($slug, '', '', 'schema-field-add', $this->verb() . "Template '{$childTpl->name}': '{$parentTpl->name}' als Eltern-Template erlauben");
        }
    }

    private function createPage(array $seed): ?Page
    {
        $pages = wire('pages');
        $templates = wire('templates');
        $slug = (string) $seed['slug'];
        $path = '/' . trim((string) $seed['path'], '/') . '/';
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $name = end($segments) ?: $slug;
        $parentPath = count($segments) > 1 ? '/' . implode('/', array_slice($segments, 0, -1)) . '/' : '/';

        $template = $templates->get((string) $seed['template']);
        if (!$template) {
            $this->row($slug, '', '', 'error', "Template '{$seed['template']}' existiert nicht — Seite kann nicht angelegt werden.");
            return null;
        }
        $parent = $pages->get($parentPath);
        if (!$parent || !$parent->id) {
            // dry-run: Elternseite wird evtl. von einem früheren Seed erst angelegt
            if (!$this->isApply() && in_array($parentPath, $this->plannedPagePaths, true)) {
                $this->row($slug, '', '', 'page-create', $this->verb() . "Seite {$path} anlegen (Template {$template->name}, Titel »{$seed['title']}«; Elternseite {$parentPath} wird zuvor angelegt)");
                $this->plannedPagePaths[] = $path;
                $this->warnIfSlugUnresolvable($slug, $name, $path);
                return null;
            }
            $this->row($slug, '', '', 'error', "Elternseite {$parentPath} existiert nicht — Seite kann nicht angelegt werden.");
            return null;
        }

        $this->ensureFamilyAllows($parent->template, $template, $slug);

        $this->row($slug, '', '', 'page-create', $this->verb() . "Seite {$path} anlegen (Template {$template->name}, Titel »{$seed['title']}«)");
        $this->warnIfSlugUnresolvable($slug, $name, $path);
        if (!$this->isApply()) {
            $this->plannedPagePaths[] = $path;
            return null;
        }

        $page = new Page();
        $page->template = $template;
        $page->parent = $parent;
        $page->name = $name;
        $page->title = (string) $seed['title'];
        $page->of(false);
        $pages->save($page);
        return $page;
    }

    /**
     * api.php 'sections' löst NUR /content/{slug}/ oder name={slug} auf
     * (api.php ~Zeile 2041). Bei verschachtelten Seed-Pfaden (z.B.
     * /anmeldung/danke/ mit Slug anmeldung-danke) heisst die PW-Seite nach dem
     * letzten Pfadsegment ('danke') — das Frontend (getPageSections('anmeldung-danke'))
     * findet sie dann NICHT. Der Seed-Pfad bleibt massgeblich; das Follow-up
     * (PW-Seitennamen-Alias oder api.php-Pfadauflösung) wird hier nur gemeldet.
     */
    private function warnIfSlugUnresolvable(string $slug, string $name, string $path): void
    {
        if ($name === $slug) return;
        $this->row($slug, '', '', 'warn',
            "Seitenname '{$name}' ≠ Slug '{$slug}': api.php /api/content/sections/{$slug} löst nur /content/{$slug}/ oder name={$slug} auf — "
            . "die Seite unter {$path} wird vom Frontend so nicht gefunden. Follow-up nötig (Seiten-Alias oder api.php-Erweiterung).");
    }

    /**
     * content_sections + SEO-Felder auf dem Template der Zielseite sicherstellen.
     * Gibt (bei apply nach Fieldgroup-Änderungen) eine frisch geladene Seite
     * zurück, damit hasField()/get() die neuen Felder sicher sehen.
     */
    private function ensurePageTemplateFields(Page $page, array $seed): Page
    {
        $fields = wire('fields');
        $slug = (string) $seed['slug'];
        $needed = [self::REPEATER_FIELD];
        if (!empty($seed['seo'])) {
            $needed[] = 'seo_title';
            $needed[] = 'seo_description';
        }
        $changed = false;
        foreach ($needed as $name) {
            $field = $fields->get($name);
            if (!$field) continue; // ensureSchema hat bereits berichtet
            if ($page->template->fieldgroup->hasField($field)) continue;
            if ($this->isApply()) {
                $fg = $page->template->fieldgroup;
                $fg->add($field);
                $fg->save();
                $changed = true;
            }
            $this->row($slug, '', $name, 'schema-field-add', $this->verb() . "Feld zu Template '{$page->template->name}' hinzufügen");
        }
        if ($changed) {
            wire('pages')->uncache($page);
            $fresh = wire('pages')->get($page->id);
            if ($fresh && $fresh->id) return $fresh;
        }
        return $page;
    }

    // ------------------------------------------------------------------
    // Section seeding
    // ------------------------------------------------------------------

    private function findSection(Page $page, string $sectionId): ?Page
    {
        $repeater = $page->get(self::REPEATER_FIELD);
        if (!$repeater) return null;
        $matches = [];
        foreach ($repeater as $item) {
            if ((string) $item->get('section_id') === $sectionId) $matches[] = $item;
        }
        if (count($matches) > 1) {
            $this->row((string) $page->name, $sectionId, 'section_id', 'warn', 'Mehrere Items mit derselben section_id — es wird das erste verwendet.');
        }
        return $matches[0] ?? null;
    }

    /** Seed-Section => Ziel-Feldwerte (PW-Feld => String), exakt wie api.php sie liest. */
    private function sectionFieldValues(array $section): array
    {
        $values = [];
        foreach (self::SECTION_TEXT_FIELDS as $seedKey => $pwField) {
            if (!isset($section[$seedKey])) continue;
            $v = (string) $section[$seedKey];
            if ($v === '') continue; // leere Seed-Werte werden nie geschrieben
            $values[$pwField] = $v;
        }
        if (isset($section['section_config']) && is_array($section['section_config']) && count($section['section_config'])) {
            $values['section_config'] = $this->encodeConfig($section['section_config']);
        }
        $buttons = isset($section['buttons']) && is_array($section['buttons']) ? array_values($section['buttons']) : [];
        foreach (self::BUTTON_FIELDS as $slot => $map) {
            if (!isset($buttons[$slot]) || !is_array($buttons[$slot])) continue;
            foreach (['text', 'href', 'variant'] as $key) {
                $v = (string) ($buttons[$slot][$key] ?? '');
                if ($v === '') continue;
                $values[$map[$key]] = $v;
            }
        }
        return $values;
    }

    private function seedSections(Page $page, array $seed): void
    {
        $slug = (string) $seed['slug'];

        if (!$page->hasField(self::REPEATER_FIELD)) {
            if (!$this->isApply()) {
                // dry-run: ensurePageTemplateFields hat das Hinzufügen des Feldes
                // bereits geplant — geplante Sections auflisten statt Fehler melden.
                foreach ($seed['sections'] as $section) {
                    $values = $this->sectionFieldValues($section);
                    $this->row($slug, (string) $section['section_id'], '', 'section-create',
                        $this->verb() . 'Neue Section anlegen (Felder: section_id, ' . implode(', ', array_keys($values)) . ')');
                }
                return;
            }
            $this->row($slug, '', self::REPEATER_FIELD, 'error', "Seite hat kein content_sections-Feld (Template '{$page->template->name}').");
            return;
        }

        $page->of(false);
        foreach ($seed['sections'] as $section) {
            $sid = (string) $section['section_id'];
            $values = $this->sectionFieldValues($section);
            $item = $this->findSection($page, $sid);

            if ($item) {
                $this->updateSectionItem($page, $item, $sid, $values, $slug);
            } else {
                $this->createSectionItem($page, $sid, $values, $slug);
                $item = $this->isApply() ? $this->findSection($page, $sid) : null;
            }

            if (!empty($section['image_url']) && is_string($section['image_url'])) {
                $this->importSectionImage($item, $sid, (string) $section['image_url'], (string) ($section['image_alt'] ?? ''), $slug);
            }
        }

        // Informativ: bestehende Sections ausserhalb des Seeds (werden NIE angefasst)
        $seedIds = array_map(fn($s) => (string) $s['section_id'], $seed['sections']);
        $extra = [];
        $repeater = $page->get(self::REPEATER_FIELD);
        if ($repeater) {
            foreach ($repeater as $item) {
                $id = (string) $item->get('section_id');
                if ($id !== '' && !in_array($id, $seedIds, true)) $extra[] = $id;
                if ($id === '') $extra[] = 'pwId:' . $item->id;
            }
        }
        if ($extra) {
            $this->row($slug, '', '', 'info', 'Nicht im Seed, bleiben unberührt: ' . implode(', ', $extra));
        }
    }

    private function updateSectionItem(Page $page, Page $item, string $sid, array $values, string $slug): void
    {
        $item->of(false);
        $changes = [];
        foreach ($values as $field => $desired) {
            if (!$this->itemHasField($item, $field)) {
                $this->row($slug, $sid, $field, 'warn', 'Feld existiert nicht auf dem Repeater-Item — übersprungen.');
                continue;
            }
            $current = $this->rawString($item, $field);
            if ($current === $desired) {
                $this->row($slug, $sid, $field, 'ok-equal', 'Bereits identisch.');
                continue;
            }
            if (trim($current) !== '' && !$this->force) {
                $this->row($slug, $sid, $field, 'skip-cms-wins', 'PW-Feld ist nicht leer — CMS gewinnt (force=1 zum Überschreiben). PW: »' . $this->excerpt($current) . '«');
                continue;
            }
            $detail = trim($current) === ''
                ? 'Leeres Feld füllen: »' . $this->excerpt($desired) . '«'
                : 'FORCE: »' . $this->excerpt($current) . '« → »' . $this->excerpt($desired) . '«';
            $this->row($slug, $sid, $field, 'field-update', $this->verb() . $detail);
            if ($this->isApply()) {
                $item->set($field, $desired);
                $changes[] = $field;
            }
        }
        if ($this->isApply() && $changes) {
            $item->save();
        }
    }

    private function createSectionItem(Page $page, string $sid, array $values, string $slug): void
    {
        $fieldsDesc = implode(', ', array_keys($values));
        $this->row($slug, $sid, '', 'section-create', $this->verb() . "Neue Section anlegen (Felder: section_id, {$fieldsDesc}) — wird ans Ende angehängt.");
        if (!$this->isApply()) return;

        // Muster aus cms/migrate-wir-sections.php::createSection()
        $page->of(false);
        $item = $page->get(self::REPEATER_FIELD)->getNew();
        $item->of(false);
        $item->set('section_id', $sid);
        foreach ($values as $field => $value) {
            $item->set($field, $value);
        }
        $item->save();
        $page->get(self::REPEATER_FIELD)->add($item);
        $page->save(self::REPEATER_FIELD);

        // Programmatisch erzeugte Repeater-Items können "unpublished" bleiben —
        // dann würde api.php sie nicht ausliefern.
        if ($item->hasStatus(Page::statusUnpublished)) {
            $item->removeStatus(Page::statusUnpublished);
            $item->save();
        }
    }

    /** hasField() ist auf Repeater-Items im Bootstrap unzuverlässig — Fallback über Template-Fieldgroup. */
    private function itemHasField(Page $item, string $fieldName): bool
    {
        if ($item->hasField($fieldName)) return true;
        $fg = $item->template ? $item->template->fieldgroup : null;
        return $fg ? (bool) $fg->hasField($fieldName) : false;
    }

    /**
     * image_url aus dem Seed in das PW-Bildfeld importieren (section_image,
     * Fallback image — genau die Felder, die api.php buildSectionData liest).
     * api.php besitzt KEIN URL-Textfeld als Bild-Fallback, deshalb Import.
     * Relative Pfade (/images/…) werden gegen assetBase aufgelöst.
     * "CMS gewinnt": nur importieren, wenn das Bildfeld leer ist (oder force).
     */
    private function importSectionImage(?Page $item, string $sid, string $imageUrl, string $imageAlt, string $slug): void
    {
        $url = preg_match('#^https?://#i', $imageUrl) ? $imageUrl : $this->assetBase . '/' . ltrim($imageUrl, '/');

        if (!$item) {
            $this->row($slug, $sid, 'section_image', 'image-import', $this->verb() . "Bild importieren von {$url}");
            return;
        }

        $targetField = null;
        foreach (['section_image', 'image'] as $candidate) {
            if ($this->itemHasField($item, $candidate)) { $targetField = $candidate; break; }
        }
        if (!$targetField) {
            $this->row($slug, $sid, 'section_image', 'warn', 'Kein Bildfeld (section_image/image) auf dem Repeater-Item — Import übersprungen.');
            return;
        }

        $item->of(false);
        $existing = $item->get($targetField);
        $hasImage = ($existing instanceof Pageimages || $existing instanceof Pagefiles) ? $existing->count() > 0 : (bool) $existing;
        if ($hasImage && !$this->force) {
            $this->row($slug, $sid, $targetField, 'skip-cms-wins', 'Bildfeld ist nicht leer — CMS gewinnt.');
            return;
        }

        $this->row($slug, $sid, $targetField, 'image-import', $this->verb() . "Bild importieren von {$url}");
        if (!$this->isApply()) return;

        try {
            $files = $item->get($targetField);
            if (!($files instanceof Pageimages) && !($files instanceof Pagefiles)) {
                $this->row($slug, $sid, $targetField, 'error', 'Feld ist kein Bild-/Dateifeld.');
                return;
            }
            $files->add($url);
            $item->save($targetField);
            $img = $item->get($targetField)->last();
            if ($img && $imageAlt !== '' && (string) $img->description === '') {
                $img->description = $imageAlt;
                $item->save($targetField);
            }
        } catch (\Exception $e) {
            $this->row($slug, $sid, $targetField, 'error', 'Bild-Import fehlgeschlagen: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Hero + SEO
    // ------------------------------------------------------------------

    /** Seed-Hero-Key => PW-Feld (api.php buildHomepageHeroData, ~Zeile 625). */
    const HERO_FIELD_MAP = [
        'hero_title'    => 'hero_headline',
        'hero_subtitle' => 'hero_subtitle',
        'image_alt'     => 'image_alt',
    ];

    private function seedHero(Page $page, array $seed): void
    {
        if (empty($seed['hero']) || !is_array($seed['hero'])) return;
        $slug = (string) $seed['slug'];
        foreach (self::HERO_FIELD_MAP as $seedKey => $pwField) {
            $v = (string) ($seed['hero'][$seedKey] ?? '');
            if ($v === '') continue; // leerer Seed-Wert = produktiven Hero nie überschreiben
            $this->writePageField($page, $pwField, $v, $slug, '(hero)');
        }
    }

    private function seedSeo(Page $page, array $seed): void
    {
        if (empty($seed['seo']) || !is_array($seed['seo'])) return;
        $slug = (string) $seed['slug'];
        $map = ['title' => 'seo_title', 'description' => 'seo_description'];
        foreach ($map as $seedKey => $pwField) {
            $v = (string) ($seed['seo'][$seedKey] ?? '');
            if ($v === '') continue;
            // SEO-Felder werden von ensurePageTemplateFields sichergestellt —
            // im dry-run fehlt das Feld evtl. noch, apply würde es ergänzen.
            $this->writePageField($page, $pwField, $v, $slug, '(seo)', true);
        }
    }

    private function writePageField(Page $page, string $field, string $desired, string $slug, string $group, bool $plannedIfMissing = false): void
    {
        if (!$page->hasField($field)) {
            if ($plannedIfMissing && !$this->isApply()) {
                $this->row($slug, $group, $field, 'field-update', $this->verb() . 'Setzen auf »' . $this->excerpt($desired) . '« (Feld wird bei apply ergänzt)');
                return;
            }
            $this->row($slug, $group, $field, 'warn', "Feld fehlt auf Template '{$page->template->name}' — übersprungen.");
            return;
        }
        $page->of(false);
        $current = $this->rawString($page, $field);
        if ($current === $desired) {
            $this->row($slug, $group, $field, 'ok-equal', 'Bereits identisch.');
            return;
        }
        if (trim($current) !== '' && !$this->force) {
            $this->row($slug, $group, $field, 'skip-cms-wins', 'PW-Feld ist nicht leer — CMS gewinnt. PW: »' . $this->excerpt($current) . '«');
            return;
        }
        $this->row($slug, $group, $field, 'field-update', $this->verb() . 'Setzen auf »' . $this->excerpt($desired) . '«');
        if ($this->isApply()) {
            $page->set($field, $desired);
            $page->save($field);
        }
    }

    // ------------------------------------------------------------------
    // Verify
    // ------------------------------------------------------------------

    private function verifyPage(?Page $page, array $seed): void
    {
        $slug = (string) $seed['slug'];
        if (!$page) {
            $this->row($slug, '', '', 'verify-missing', 'Seite existiert nicht in ProcessWire.');
            return;
        }
        $page->of(false);

        if (!empty($seed['seo']) && is_array($seed['seo'])) {
            foreach (['title' => 'seo_title', 'description' => 'seo_description'] as $seedKey => $pwField) {
                $v = (string) ($seed['seo'][$seedKey] ?? '');
                if ($v === '') continue;
                $this->verifyValue($slug, '(seo)', $pwField, $page->hasField($pwField) ? $this->rawString($page, $pwField) : null, $v);
            }
        }
        if (!empty($seed['hero']) && is_array($seed['hero'])) {
            foreach (self::HERO_FIELD_MAP as $seedKey => $pwField) {
                $v = (string) ($seed['hero'][$seedKey] ?? '');
                if ($v === '') continue;
                $this->verifyValue($slug, '(hero)', $pwField, $page->hasField($pwField) ? $this->rawString($page, $pwField) : null, $v);
            }
        }

        if (!$page->hasField(self::REPEATER_FIELD)) {
            $this->row($slug, '', self::REPEATER_FIELD, 'verify-missing', "Seite hat kein content_sections-Feld.");
            return;
        }

        // Reihenfolge wie api.php buildVisualEditorSections: sort('sort')
        $order = [];
        foreach ($page->get(self::REPEATER_FIELD)->sort('sort') as $item) {
            $order[] = (string) $item->get('section_id');
        }

        foreach ($seed['sections'] as $section) {
            $sid = (string) $section['section_id'];
            $item = $this->findSection($page, $sid);
            if (!$item) {
                $this->row($slug, $sid, '', 'verify-missing', 'Section fehlt in ProcessWire.');
                continue;
            }
            foreach ($this->sectionFieldValues($section) as $field => $desired) {
                $current = $this->itemHasField($item, $field) ? $this->rawString($item, $field) : null;
                if ($field === 'section_config' && $current !== null && $current !== $desired) {
                    // JSON-äquivalent (gleiche Werte, andere Serialisierung) gilt als Treffer
                    $a = json_decode($current, true);
                    $b = json_decode($desired, true);
                    if (is_array($a) && is_array($b) && $this->canonicalJson($a) === $this->canonicalJson($b)) {
                        $this->row($slug, $sid, $field, 'verify-match', 'JSON-äquivalent (Serialisierung abweichend).');
                        continue;
                    }
                }
                $this->verifyValue($slug, $sid, $field, $current, $desired);
            }
        }

        // Reihenfolge-Check: Seed-Sections müssen als Teilfolge in Seed-Reihenfolge vorkommen
        $seedIds = array_map(fn($s) => (string) $s['section_id'], $seed['sections']);
        $positions = [];
        foreach ($seedIds as $sid) {
            $pos = array_search($sid, $order, true);
            if ($pos !== false) $positions[] = $pos;
        }
        $sorted = $positions;
        sort($sorted);
        if ($positions !== $sorted) {
            $this->row($slug, '', '', 'warn', 'Reihenfolge weicht vom Seed ab (PW: ' . implode(' → ', $order) . '). Neue Sections werden nur angehängt — bei Bedarf im Visual Editor umsortieren.');
        }
    }

    private function verifyValue(string $slug, string $sid, string $field, ?string $current, string $desired): void
    {
        if ($current === null) {
            $this->row($slug, $sid, $field, 'verify-missing', 'Feld existiert nicht.');
            return;
        }
        if ($current === $desired) {
            $this->row($slug, $sid, $field, 'verify-match', 'Byte-genau identisch.');
            return;
        }
        if (trim($current) === '' && isset(self::API_DEFAULTS[$field]) && self::API_DEFAULTS[$field] === $desired) {
            $this->row($slug, $sid, $field, 'verify-match', 'Leer, aber api.php-Default entspricht dem Seed-Wert (äquivalente API-Ausgabe).');
            return;
        }
        $this->row($slug, $sid, $field, 'verify-mismatch', $this->firstDiff($current, $desired));
    }

    // ------------------------------------------------------------------
    // Run + output
    // ------------------------------------------------------------------

    public function run(): void
    {
        $seeds = $this->loadSeeds();
        $this->logLines[] = 'START mode=' . $this->mode . ($this->force ? ' force=1' : '') . ' seeds=' . count($seeds);

        if ($this->mode !== 'verify') {
            $this->ensureSchema();
        }

        foreach ($seeds as $seed) {
            $slug = (string) $seed['slug'];
            try {
                $page = $this->resolvePage($seed);

                if ($this->mode === 'verify') {
                    $this->verifyPage($page, $seed);
                    continue;
                }

                if (!$page) {
                    $page = $this->createPage($seed);
                    if (!$page) {
                        if (!$this->isApply()) {
                            // dry-run: geplante Sections trotzdem auflisten
                            foreach ($seed['sections'] as $section) {
                                $values = $this->sectionFieldValues($section);
                                $this->row($slug, (string) $section['section_id'], '', 'section-create',
                                    $this->verb() . 'Neue Section anlegen (Felder: section_id, ' . implode(', ', array_keys($values)) . ')');
                            }
                            $this->seedDryRunPageMeta($seed);
                        }
                        continue;
                    }
                } else {
                    $this->row($slug, '', '', 'info', "Zielseite: {$page->path} (id {$page->id}, Template {$page->template->name})");
                }

                $page = $this->ensurePageTemplateFields($page, $seed);
                $this->seedHero($page, $seed);
                $this->seedSeo($page, $seed);
                $this->seedSections($page, $seed);
            } catch (\Exception $e) {
                $this->row($slug, '', '', 'error', $e->getMessage());
            }
        }

        $summary = $this->summaryLine();
        $this->logLines[] = 'DONE ' . $summary;
        $log = wire('log');
        foreach ($this->logLines as $line) {
            $log->save(self::LOG_NAME, $this->excerpt($line, 900));
        }
    }

    /** dry-run-Bericht für Hero/SEO einer noch nicht existierenden Seite. */
    private function seedDryRunPageMeta(array $seed): void
    {
        $slug = (string) $seed['slug'];
        if (!empty($seed['seo']['title'])) {
            $this->row($slug, '(seo)', 'seo_title', 'field-update', $this->verb() . 'Setzen auf »' . $this->excerpt((string) $seed['seo']['title']) . '«');
        }
        if (!empty($seed['seo']['description'])) {
            $this->row($slug, '(seo)', 'seo_description', 'field-update', $this->verb() . 'Setzen auf »' . $this->excerpt((string) $seed['seo']['description']) . '«');
        }
    }

    public function summaryLine(): string
    {
        $parts = [];
        foreach ($this->counts as $status => $n) {
            $parts[] = "{$status}={$n}";
        }
        sort($parts);
        return 'mode=' . $this->mode . ($this->force ? ' force=1' : '') . ' | ' . implode(', ', $parts);
    }

    public function render(string $format): void
    {
        $hasErrors = !empty($this->counts['error']) || !empty($this->counts['verify-mismatch']) || !empty($this->counts['verify-missing']);

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => !$hasErrors,
                'mode' => $this->mode,
                'force' => $this->force,
                'summary' => $this->counts,
                'rows' => $this->rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (PHP_SAPI === 'cli' || $format === 'text') {
            header('Content-Type: text/plain; charset=utf-8');
            echo "Content-Freeze-Migration — " . $this->summaryLine() . "\n\n";
            foreach ($this->rows as $r) {
                echo str_pad($r['status'], 18) . ' ' . str_pad($r['page'], 20) . ' ' . str_pad($r['section'], 30) . ' ' . str_pad($r['field'], 20) . ' ' . $r['detail'] . "\n";
            }
            echo "\n" . ($hasErrors ? 'MIT FEHLERN/ABWEICHUNGEN beendet.' : 'OK.') . "\n";
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $badge = function (string $status) use ($h): string {
            $colors = [
                'error' => '#9f1239', 'verify-mismatch' => '#9f1239', 'verify-missing' => '#b45309',
                'warn' => '#b45309', 'skip-cms-wins' => '#475569', 'ok-equal' => '#1c6b2d',
                'verify-match' => '#1c6b2d', 'field-update' => '#1d4ed8', 'section-create' => '#1d4ed8',
                'page-create' => '#7c3aed', 'image-import' => '#1d4ed8', 'info' => '#475569',
                'label-update' => '#0e7490', 'option-labels' => '#0e7490', 'schema-field-add' => '#0e7490',
            ];
            $c = $colors[$status] ?? '#334155';
            return '<span style="color:' . $c . ';font-weight:600;white-space:nowrap">' . $h($status) . '</span>';
        };

        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Content-Freeze-Migration</title>';
        echo '<style>body{font:14px/1.5 -apple-system,Segoe UI,sans-serif;margin:24px;color:#1a1a1a}table{border-collapse:collapse;width:100%}th,td{border:1px solid #e2e8f0;padding:4px 8px;text-align:left;vertical-align:top}th{background:#f1f5f9}tr:nth-child(even){background:#fafafa}code{background:#f1f5f9;padding:1px 4px;border-radius:3px}</style></head><body>';
        echo '<h1>Content-Freeze-Migration</h1>';
        echo '<p><strong>' . $h($this->summaryLine()) . '</strong></p>';
        if ($this->mode === 'dry-run') {
            echo '<p>Nur Bericht — es wurde <strong>nichts geschrieben</strong>. Ausführen mit <code>mode=apply</code>.</p>';
        }
        echo '<table><thead><tr><th>Seite</th><th>Section</th><th>Feld</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
        foreach ($this->rows as $r) {
            echo '<tr><td>' . $h($r['page']) . '</td><td>' . $h($r['section']) . '</td><td><code>' . $h($r['field']) . '</code></td><td>' . $badge($r['status']) . '</td><td>' . $h($r['detail']) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p style="margin-top:16px">' . ($hasErrors ? '<strong style="color:#9f1239">Mit Fehlern/Abweichungen beendet.</strong>' : '<strong style="color:#1c6b2d">OK.</strong>') . ' Log: ProcessWire → Setup → Logs → <code>content-freeze</code></p>';
        echo '</body></html>';
    }
}

} // class_exists guard

// ============================================================================
// Entrypoint (Guards + Parameter)
// ============================================================================

if (!defined('BIOCO_CONTENT_FREEZE_RAN')) {
    define('BIOCO_CONTENT_FREEZE_RAN', true);

    (function () {
        $isCli = PHP_SAPI === 'cli';

        // CLI: Parameter im Stil key=value akzeptieren (php bootstrap.php mode=apply force=1)
        if ($isCli && !empty($GLOBALS['argv'])) {
            foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
                if (strpos($arg, '=') !== false) {
                    [$k, $v] = explode('=', $arg, 2);
                    $_GET[$k] = $v;
                }
            }
        }

        // --- Guard 1: kein unverschlüsseltes HTTP ---
        // Nur vertrauenswürdige Server-Signale prüfen. HTTP_X_FORWARDED_PROTO
        // ist ein client-gesetzter Request-Header und darf die HTTPS-Pflicht
        // NICHT aufheben (sonst genügt `X-Forwarded-Proto: https` auf einer
        // Klartext-Verbindung). Novatrend/cPanel terminiert TLS direkt in
        // Apache, daher reichen HTTPS + SERVER_PORT.
        if (!$isCli) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') === '443');
            if (!$https) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=utf-8');
                die('Abgelehnt: Migration nur über HTTPS ausführen.');
            }
        }

        // --- Guard 2: Superuser ODER Einmal-Token ---
        $user = wire('user');
        $isSuperuser = $user && $user->isSuperuser();
        $tokenOk = false;
        if (defined('BIOCO_CONTENT_FREEZE_TOKEN')) {
            $expected = (string) constant('BIOCO_CONTENT_FREEZE_TOKEN');
            $given = (string) ($_GET['token'] ?? '');
            $tokenOk = strlen($expected) >= 20 && $given !== '' && hash_equals($expected, $given);
        }
        if (!$isSuperuser && !$tokenOk) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            die('Abgelehnt: Superuser-Login oder gültiger token-Parameter erforderlich (BIOCO_CONTENT_FREEZE_TOKEN, min. 20 Zeichen).');
        }

        // --- Parameter ---
        $mode = (string) ($_GET['mode'] ?? 'dry-run');
        if (!in_array($mode, ['dry-run', 'apply', 'verify'], true)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            die("Ungültiger mode '{$mode}'. Erlaubt: dry-run | apply | verify");
        }
        $force = (string) ($_GET['force'] ?? '') === '1';
        $onlySlugs = array_values(array_filter(array_map('trim', explode(',', (string) ($_GET['page'] ?? '')))));
        $format = (string) ($_GET['format'] ?? 'html');

        $seedDir = defined('BIOCO_CONTENT_FREEZE_SEED_DIR')
            ? (string) constant('BIOCO_CONTENT_FREEZE_SEED_DIR')
            : wire('config')->paths->templates . 'content-seed/';
        $assetBase = defined('BIOCO_CONTENT_FREEZE_ASSET_BASE')
            ? (string) constant('BIOCO_CONTENT_FREEZE_ASSET_BASE')
            : 'https://www.bioco.ch';

        $migration = new BiocoContentFreezeMigration($mode, $force, $onlySlugs, $seedDir, $assetBase);
        try {
            $migration->run();
        } catch (\Exception $e) {
            wire('log')->save(BiocoContentFreezeMigration::LOG_NAME, 'FATAL: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            die('FATAL: ' . $e->getMessage());
        }
        $migration->render($format);
    })();
}
