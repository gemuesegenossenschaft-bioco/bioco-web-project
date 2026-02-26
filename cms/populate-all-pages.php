<?php
/**
 * Populate All Pages (22 total)
 *
 * 12 content pages with sections + 8 basic-page body pages + 2 structural/hidden
 * Run via: https://cms.bioco.ch/run-migrations/?phase=populate
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Populate All Pages (22) ===\n";

    // ==================================================================================
    // HELPERS
    // ==================================================================================

    function ensurePage($name, $title, $templateName, $parentPath = '/', $hidden = false) {
        $pages = wire('pages');
        $templates = wire('templates');

        $parent = $pages->get($parentPath);
        if (!$parent->id) return null;

        $page = $pages->get("parent=$parent, name=$name");

        if (!$page->id) {
            $template = $templates->get($templateName);
            if (!$template) return null;

            $page = new Page();
            $page->template = $template;
            $page->parent = $parent;
            $page->name = $name;
            $page->title = $title;
            if ($hidden) $page->addStatus(Page::statusHidden);
            $pages->save($page);
        } else {
            $page->of(false);
            $template = $templates->get($templateName);
            if ($template && $page->template->name !== $templateName) {
                $page->template = $template;
            }
            $page->title = $title;
            if ($hidden && !$page->isHidden()) {
                $page->addStatus(Page::statusHidden);
            }
            $pages->save($page);
        }

        return $page;
    }

    function addSection($page, $sectionData) {
        if (!$page || !$page->id) return false;
        if (!$page->hasField('content_sections')) return false;

        $page->of(false);
        $section = $page->content_sections->getNew();

        if (isset($sectionData['id'])) $section->section_id = $sectionData['id'];
        if (isset($sectionData['title'])) $section->section_title = $sectionData['title'];
        if (isset($sectionData['text'])) $section->section_text = $sectionData['text'];
        if (isset($sectionData['layout'])) $section->section_layout = $sectionData['layout'];
        if (isset($sectionData['theme'])) $section->section_theme = $sectionData['theme'];
        if (isset($sectionData['eyebrow'])) $section->section_eyebrow = $sectionData['eyebrow'];
        if (isset($sectionData['component'])) $section->section_component = $sectionData['component'];

        if (isset($sectionData['button_text'])) $section->button_text = $sectionData['button_text'];
        if (isset($sectionData['button_href'])) $section->button_href = $sectionData['button_href'];
        if (isset($sectionData['button2_text'])) $section->button2_text = $sectionData['button2_text'];
        if (isset($sectionData['button2_href'])) $section->button2_href = $sectionData['button2_href'];

        $section->save();
        $page->content_sections->add($section);
        $page->save();

        return true;
    }

    function setHero($page, $headline, $subtitle = '') {
        if (!$page || !$page->id) return;
        $page->of(false);
        if ($page->hasField('hero_headline')) $page->hero_headline = $headline;
        if ($page->hasField('hero_subtitle')) $page->hero_subtitle = $subtitle;
        wire('pages')->save($page);
    }

    function setNav($page, $value = 1) {
        if (!$page || !$page->id) return;
        if (!$page->hasField('include_in_nav')) return;
        $page->of(false);
        $page->include_in_nav = $value;
        wire('pages')->save($page);
    }

    function clearSections($page) {
        if (!$page || !$page->id) return;
        if (!$page->hasField('content_sections')) return;
        $page->of(false);
        foreach ($page->content_sections as $s) {
            $page->content_sections->remove($s);
        }
        $page->save();
    }

    function setBody($page, $html) {
        if (!$page || !$page->id) return;
        if (!$page->hasField('body')) return;
        $page->of(false);
        $page->body = $html;
        wire('pages')->save($page);
    }

    // ==================================================================================
    // Allow children on basic-page (needed for /anmeldung/danke/)
    // ==================================================================================

    $log[] = "Configuring basic-page template to allow children...";
    $basicPageTemplate = $templates->get('basic-page');
    if ($basicPageTemplate) {
        $basicPageTemplate->noChildren = 0;
        $basicPageTemplate->save();
        $log[] = "  OK basic-page allows children";
    }

    // ==================================================================================
    // 1. HOMEPAGE
    // ==================================================================================

    $log[] = "\nStep 1: Homepage...";

    $homePage = $pages->get('/');
    if ($homePage->id) {
        $homePage->of(false);
        $homePage->title = 'Startseite';
        $homeTemplate = $templates->get('home');
        if ($homeTemplate && $homePage->template->name !== 'home') {
            $homePage->template = $homeTemplate;
        }
        $pages->save($homePage);
    }

    if ($homePage && $homePage->id) {
        setHero($homePage, 'Gemeinsam Gemüse anbauen und geniessen', 'Solidarische Landwirtschaft in der Region Baden-Brugg');
        clearSections($homePage);

        addSection($homePage, [
            'id' => 'willkommen',
            'title' => 'Willkommen bei biocò',
            'text' => '<p>biocò ist eine <strong>Gemüsegenossenschaft</strong> in der Region Baden-Brugg. Gemeinsam bauen wir auf dem Geisshof in Gebenstorf saisonales Demeter-Gemüse an. Als Mitglied erhältst du wöchentlich einen frischen Gemüsekorb und bist Teil einer lebendigen Gemeinschaft.</p><p>Erfahre mehr über unser <a href="/solawi">Solawi-Modell</a> oder finde dein nächstes <a href="/standorte-depots">Abholdepot</a>.</p>',
            'layout' => 'split_media_text',
            'button_text' => 'Lerne uns kennen',
            'button_href' => '/wir',
        ]);

        addSection($homePage, [
            'id' => 'gemeinsam',
            'title' => 'Gemeinsam, solidarisch, frisch',
            'text' => '<p>Seit 2014 bewirtschaften wir den Geisshof in Gebenstorf nach <strong>Demeter-Richtlinien</strong>. Über 40 verschiedene Gemüsesorten wachsen auf unseren Feldern, gepflegt von einem engagierten Team aus Gärtner:innen und Mitgliedern.</p>',
            'layout' => 'split_text_media',
            'button_text' => 'Was gerade wächst',
            'button_href' => '/gemuese',
        ]);

        addSection($homePage, [
            'id' => 'kennenlernen',
            'title' => 'Möchtest du uns kennenlernen?',
            'text' => '<p>Komm an einem unserer Schnuppertage vorbei und erlebe die solidarische Landwirtschaft hautnah. Oder nimm einfach Kontakt mit uns auf.</p>',
            'layout' => 'rich_text',
            'button_text' => 'Nimm Kontakt auf',
            'button_href' => '/kontakt',
            'button2_text' => 'Zu uns finden',
            'button2_href' => '/standorte-depots',
        ]);

        addSection($homePage, [
            'id' => 'aktuelles',
            'title' => 'Aktuelles',
            'text' => '',
            'layout' => 'component',
            'component' => 'events_feed',
        ]);

        addSection($homePage, [
            'id' => 'schnuppertage',
            'title' => 'Schnuppertage',
            'text' => '',
            'layout' => 'component',
            'component' => 'schnuppertage',
        ]);

        $log[] = "  OK Homepage (5 sections)";
    }

    // ==================================================================================
    // 2. WIR
    // ==================================================================================

    $log[] = "\nStep 2: Wir...";

    $wirPage = ensurePage('wir', 'Über uns', 'wir', '/');
    if ($wirPage) {
        setHero($wirPage, 'biocò: Die Gemüsegenossenschaft');
        setNav($wirPage);
        clearSections($wirPage);

        addSection($wirPage, [
            'id' => 'wir',
            'title' => 'Wir',
            'text' => '<p>biocò ist mehr als eine Gemüsegenossenschaft. Wir sind eine Gemeinschaft von Menschen, die gemeinsam biologisches Gemüse anbauen und geniessen. Unser Ziel: frische, saisonale Lebensmittel für alle, fair und nachhaltig produziert.</p>',
            'layout' => 'split_media_text',
        ]);

        addSection($wirPage, [
            'id' => 'geisshof',
            'title' => 'Der Geisshof',
            'text' => '<p>Der Geisshof liegt auf einem Hügel über Gebenstorf AG. Seit 2014 bewirtschaften wir den Hof nach Demeter-Richtlinien. Auf unseren Feldern wachsen über 40 verschiedene Gemüsesorten, die wir wöchentlich an unsere Mitglieder verteilen.</p>',
            'layout' => 'split_text_media',
        ]);

        addSection($wirPage, [
            'id' => 'mission',
            'title' => 'Mission & Leitbild',
            'text' => '<p>Wir stehen für solidarische Landwirtschaft, Demeter-Qualität und Gemeinschaft. Unser Leitbild verbindet nachhaltige Produktion mit sozialem Engagement und der Freude am gemeinsamen Schaffen.</p>',
            'layout' => 'rich_text',
        ]);

        addSection($wirPage, [
            'id' => 'geschichte',
            'title' => 'Geschichte',
            'text' => '<p>2014 gegründet in Gebenstorf, ist biocò über die Jahre zu einer lebendigen Genossenschaft gewachsen, die heute die Region Baden-Brugg mit frischem Demeter-Gemüse versorgt.</p>',
            'layout' => 'rich_text',
        ]);

        $log[] = "  OK Wir (4 sections)";
    }

    // ==================================================================================
    // 3. GEMÜSE
    // ==================================================================================

    $log[] = "\nStep 3: Gemüse...";

    $gemusePage = ensurePage('gemuese', 'Gemüse', 'gemuese', '/');
    if ($gemusePage) {
        setHero($gemusePage, 'Saisonales Demeter Gemüse');
        setNav($gemusePage);
        clearSections($gemusePage);

        addSection($gemusePage, [
            'id' => 'B-04',
            'title' => 'Saisonkalender',
            'text' => '<p>Wann ist welches Gemüse verfügbar? Entdecke unsere saisonale Vielfalt.</p>',
            'layout' => 'component',
            'component' => 'saisonkalender',
        ]);

        addSection($gemusePage, [
            'id' => 'B-05',
            'title' => 'Demeter-Qualität',
            'text' => '<h3>Warum Demeter?</h3><p>Demeter steht für die höchsten Bio-Standards. Biologisch-dynamische Landwirtschaft geht über konventionellen Bio-Anbau hinaus und fördert die Bodenfruchtbarkeit, Biodiversität und nachhaltige Kreislaufwirtschaft.</p><ul><li>Biologisch-dynamische Landwirtschaft</li><li>Kein Einsatz von synthetischen Mitteln</li><li>Kreislaufwirtschaft</li><li>Biodiversität</li></ul><p><a href="https://www.demeter.ch" target="_blank">Mehr über Demeter erfahren</a></p>',
            'layout' => 'rich_text',
        ]);

        addSection($gemusePage, [
            'id' => 'B-02',
            'title' => 'Was wir anbauen',
            'text' => '<p>Einblicke in unsere Ernte, den Anbau und die Gemeinschaft.</p>',
            'layout' => 'component',
            'component' => 'gallery',
        ]);

        addSection($gemusePage, [
            'id' => 'B-06',
            'title' => 'Möchtest du uns kennenlernen?',
            'text' => '',
            'layout' => 'rich_text',
            'button_text' => 'Nimm Kontakt auf',
            'button_href' => '/kontakt',
            'button2_text' => 'Zu uns finden',
            'button2_href' => '/standorte-depots',
        ]);

        $log[] = "  OK Gemüse (4 sections)";
    }

    // ==================================================================================
    // 4. MITMACHEN
    // ==================================================================================

    $log[] = "\nStep 4: Mitmachen...";

    $mitmachenPage = ensurePage('mitmachen', 'Mitmachen', 'mitmachen', '/');
    if ($mitmachenPage) {
        setHero($mitmachenPage, 'Mitmachen bei biocò', 'Werde Teil unserer Gemüsegenossenschaft');
        setNav($mitmachenPage);
        clearSections($mitmachenPage);

        addSection($mitmachenPage, [
            'id' => 'D-01',
            'title' => 'Was es braucht, damit wir gesundes Gemüse haben',
            'text' => '<h3>Mitarbeit bei biocò</h3><p><strong>Tätigkeitsbereiche:</strong></p><ul><li>Feld/Anbau</li><li>Logistik</li><li>Administration</li><li>Events/Organisation</li><li>Andere</li></ul><p><strong>Planung:</strong> Über unser Intranet kannst du Tage und Zeiten flexibel wählen.</p>',
            'layout' => 'rich_text',
        ]);

        addSection($mitmachenPage, [
            'id' => 'D-02',
            'title' => 'Gruppen & Gemeinschaft',
            'text' => '<p>Bei biocò gibt es verschiedene Arbeitsgruppen, in denen du dich engagieren kannst:</p><ul><li><strong>Elki:</strong> Familienaktivitäten und gemeinsame Anlässe</li><li><strong>Kräutergruppe:</strong> Spezialisiert auf Kräuter und Gewürze</li><li><strong>BG (Betriebsgruppe):</strong> Aktive Mitarbeit in der Betriebsorganisation</li></ul>',
            'layout' => 'rich_text',
        ]);

        addSection($mitmachenPage, [
            'id' => 'schnuppertage',
            'title' => 'Schnuppertage',
            'text' => '<p>Komm schnuppern: So geht solidarischer Gemüseanbau.</p>',
            'layout' => 'component',
            'component' => 'schnuppertage',
        ]);

        addSection($mitmachenPage, [
            'id' => 'D-03',
            'title' => 'Familien & Kinder auf dem Geisshof',
            'text' => '<p>Der Geisshof ist ein Ort für die ganze Familie. Kinder können die Natur erleben, beim Ernten helfen und lernen, wo unser Essen herkommt.</p>',
            'layout' => 'split_media_text',
        ]);

        addSection($mitmachenPage, [
            'id' => 'B-06',
            'title' => 'Möchtest du uns kennenlernen?',
            'text' => '',
            'layout' => 'rich_text',
            'button_text' => 'Nimm Kontakt auf',
            'button_href' => '/kontakt',
            'button2_text' => 'Zu uns finden',
            'button2_href' => '/standorte-depots',
        ]);

        $log[] = "  OK Mitmachen (5 sections)";
    }

    // ==================================================================================
    // 5. ABOS
    // ==================================================================================

    $log[] = "\nStep 5: Abos...";

    $abosPage = ensurePage('abos', 'Abos', 'abos', '/');
    if ($abosPage) {
        setHero($abosPage, 'Dein wöchentliches Gemüseabo', 'Wöchentlich frisches Demeter-Gemüse direkt vom Feld');
        setNav($abosPage);
        clearSections($abosPage);

        addSection($abosPage, [
            'id' => 'C-01',
            'title' => 'Gemüse-Abos',
            'text' => '<p>Das Gemüseabo läuft vom 1. Januar bis zum 31. Dezember.</p><h3>Unsere Abos</h3><ul><li><strong>Halb</strong> (1 Anteilschein): CHF 750/Jahr, 1 Person, 10 Halbtage Mitarbeit</li><li><strong>Standard</strong> (2 Anteilscheine): CHF 1\'280/Jahr, 2 Personen, 20 Halbtage Mitarbeit</li><li><strong>Doppel</strong> (4 Anteilscheine): CHF 2\'350/Jahr, 4 Personen, 40 Halbtage Mitarbeit</li></ul><p><strong>Anteilsscheine:</strong> Der Anteilsschein ist dein Beitrag an die Genossenschaft.</p><p>Tipp: Geteilte Körbe sparen CHF 110.</p>',
            'layout' => 'rich_text',
            'button_text' => 'Jetzt Abo wählen',
            'button_href' => '/bioco-werden',
        ]);

        addSection($abosPage, [
            'id' => 'C-01b',
            'title' => 'Was ist im Gemüsekorb?',
            'text' => '<ul><li>Wöchentlicher Gemüsekorb mit saisonalem Inhalt</li><li>Demeter-Qualität</li><li>Frisch vom Feld</li><li>Abholung an verschiedenen Depot-Standorten</li></ul><p><a href="/gemuese">Mehr über unsere Ernte erfahren</a></p>',
            'layout' => 'split_media_text',
        ]);

        addSection($abosPage, [
            'id' => 'C-01c',
            'title' => 'Zahlungsweise',
            'text' => '<p>Du kannst vierteljährlich oder jährlich bezahlen.</p>',
            'layout' => 'rich_text',
        ]);

        addSection($abosPage, [
            'id' => 'C-02',
            'title' => 'Probe-Abo',
            'text' => '<p>Möchtest du biocò erst einmal kennenlernen? Unser Probe-Abo bietet dir die Möglichkeit, 3 Monate lang dabei zu sein.</p><ul><li>3 Monate Laufzeit</li><li>Proportionaler Preis</li><li>Flexible Verlängerung oder Upgrade</li></ul>',
            'layout' => 'rich_text',
            'button_text' => 'Probe-Abo testen',
            'button_href' => '/mitmachen',
        ]);

        addSection($abosPage, [
            'id' => 'C-03',
            'title' => 'Anteilsscheine ohne Gemüsekorb',
            'text' => '<p>Du kannst die Genossenschaft auch ohne Gemüsekorb unterstützen.</p><ul><li>Genossenschaft unterstützen</li><li>Wartelisten-Priorität</li><li>Stimmrecht</li></ul><p>Kosten: CHF 250 pro Anteilsschein</p>',
            'layout' => 'rich_text',
            'button_text' => 'Anteilsscheine erwerben',
            'button_href' => '/mitmachen',
        ]);

        addSection($abosPage, [
            'id' => 'events',
            'title' => '',
            'text' => '',
            'layout' => 'component',
            'component' => 'events_feed',
        ]);

        addSection($abosPage, [
            'id' => 'C-04',
            'title' => 'Zusatz-Abos',
            'text' => '<p>In Planung: Partnerangebote wie Eier, Brot, Tofu und mehr.</p>',
            'layout' => 'rich_text',
        ]);

        addSection($abosPage, [
            'id' => 'B-06',
            'title' => 'Möchtest du uns kennenlernen?',
            'text' => '',
            'layout' => 'rich_text',
            'button_text' => 'Nimm Kontakt auf',
            'button_href' => '/kontakt',
            'button2_text' => 'Zu uns finden',
            'button2_href' => '/standorte-depots',
        ]);

        $log[] = "  OK Abos (8 sections)";
    }

    // ==================================================================================
    // 6. SOLAWI
    // ==================================================================================

    $log[] = "\nStep 6: Solawi...";

    $solawiPage = ensurePage('solawi', 'Solidarische Landwirtschaft', 'solawi', '/');
    if ($solawiPage) {
        setHero($solawiPage, 'Was ist Solidarische Landwirtschaft (SoLaWi)?');
        setNav($solawiPage);
        clearSections($solawiPage);

        addSection($solawiPage, [
            'id' => 'definition',
            'title' => 'Was ist Solawi?',
            'text' => '<p>Solidarische Landwirtschaft (SoLaWi) ist ein Modell, bei dem Konsument:innen und Produzent:innen eine verbindliche Partnerschaft eingehen. Mitglieder finanzieren die Landwirtschaft direkt und teilen Ernte, Risiko und Verantwortung. Im Zentrum stehen Vertrauen, Transparenz und Gemeinschaft.</p>',
            'layout' => 'rich_text',
        ]);

        addSection($solawiPage, [
            'id' => 'funktionsweise',
            'title' => 'Wie funktioniert Solidarische Landwirtschaft?',
            'text' => '<ul><li><strong>Gemeinsame Finanzierung:</strong> Mitglieder tragen die Kosten gemeinsam.</li><li><strong>Wöchentliche Ernte-Anteile:</strong> Jede Woche gibt es frisches Gemüse.</li><li><strong>Mitarbeit und Teilhabe:</strong> Mitglieder helfen aktiv mit.</li><li><strong>Teilen von Risiko und Ertrag:</strong> Bei guter Ernte gibt es mehr, bei schlechter weniger.</li></ul>',
            'layout' => 'rich_text',
        ]);

        addSection($solawiPage, [
            'id' => 'vorteile',
            'title' => 'Warum Solawi?',
            'text' => '<h3>Vorteile für Konsument:innen</h3><ul><li>Frisches, saisonales Bio-Gemüse</li><li>Transparenz über Herkunft und Anbau</li><li>Gemeinschaftserlebnis</li><li>Faire Preise</li><li>Bildung und Naturerfahrung</li></ul><h3>Vorteile für Produzent:innen</h3><ul><li>Planungssicherheit</li><li>Faire Entlohnung</li><li>Direkter Kontakt zu Konsument:innen</li><li>Gemeinschaftliche Unterstützung</li></ul><h3>Vorteile für die Umwelt</h3><ul><li>Biodiversität</li><li>Kurze Transportwege</li><li>Nachhaltige Anbaumethoden</li><li>Kein Food Waste</li></ul>',
            'layout' => 'rich_text',
        ]);

        addSection($solawiPage, [
            'id' => 'bioco',
            'title' => 'Solidarische Landwirtschaft bei biocò',
            'text' => '<p>biocò bewirtschaftet den Geisshof in Gebenstorf nach Demeter-Richtlinien.</p><ul><li>Jahresbeitrag (CHF 750 bis CHF 2\'350)</li><li>Wöchentlicher Gemüsekorb</li><li>Mitarbeit (10 bis 40 Halbtage/Jahr)</li><li>Demeter-Qualität</li><li>Genossenschaftsmodell</li></ul><p>Neben dem Gemüseanbau pflegen wir eine lebendige Gemeinschaft mit Events wie Open-Air-Kino, Fondue-Abende und mehr.</p>',
            'layout' => 'rich_text',
            'button_text' => 'Jetzt Mitglied werden',
            'button_href' => '/mitmachen',
        ]);

        addSection($solawiPage, [
            'id' => 'faq',
            'title' => 'Häufige Fragen zu Solawi',
            'text' => '<p><strong>Was bedeutet Solawi?</strong><br>Solidarische Landwirtschaft: Konsument:innen und Produzent:innen teilen Kosten, Risiko und Ernte.</p><p><strong>Wie unterscheidet sich Solawi vom Abo-Gemüse?</strong><br>Bei Solawi bist du Teil der Gemeinschaft und arbeitest aktiv mit.</p><p><strong>Muss ich zwingend mitarbeiten?</strong><br>Mitarbeit ist Teil des Modells, aber es gibt verschiedene Tätigkeitsbereiche.</p><p><strong>Was passiert bei Ernteausfällen?</strong><br>Das Risiko wird solidarisch geteilt. Bei schlechter Ernte gibt es weniger Gemüse.</p><p><strong>Kann ich selbst entscheiden, welches Gemüse ich bekomme?</strong><br>Der Inhalt des Korbes richtet sich nach der Saison und dem aktuellen Ertrag.</p><p><strong>Wie kann ich mitmachen?</strong><br>Besuche unsere Seite <a href="/mitmachen">Mitmachen</a> oder <a href="/kontakt">kontaktiere uns</a>.</p>',
            'layout' => 'rich_text',
        ]);

        addSection($solawiPage, [
            'id' => 'cta',
            'title' => 'Bereit für solidarische Landwirtschaft?',
            'text' => '',
            'layout' => 'rich_text',
            'button_text' => 'Jetzt Mitglied werden',
            'button_href' => '/mitmachen',
            'button2_text' => 'Nimm Kontakt auf',
            'button2_href' => '/kontakt',
        ]);

        $log[] = "  OK Solawi (6 sections)";
    }

    // ==================================================================================
    // 7. STANDORTE-DEPOTS
    // ==================================================================================

    $log[] = "\nStep 7: Standorte-Depots...";

    $standortePage = ensurePage('standorte-depots', 'Standorte & Depots', 'standorte_depots', '/');
    if ($standortePage) {
        setHero($standortePage, 'Unsere Standorte & Depots');
        setNav($standortePage);
        clearSections($standortePage);

        addSection($standortePage, [
            'id' => 'E-01',
            'title' => 'Anfahrt zum Geisshof',
            'text' => '<p>Der Geisshof liegt auf einem Hügel über Gebenstorf AG.</p><h4>Anreise & Parken</h4><p>Bitte komm wenn möglich mit dem Velo oder Bus! Nicht den Hügel hochfahren, unten parkieren und den Wendeplatz freihalten.</p>',
            'layout' => 'component',
            'component' => 'geisshof_map',
        ]);

        addSection($standortePage, [
            'id' => 'E-02',
            'title' => 'Depot-Standorte für Gemüseabholung',
            'text' => '<p>Hier findest du alle Depot-Standorte, an denen du deinen wöchentlichen Gemüsekorb abholen kannst.</p><ul><li><strong>Depot Baden:</strong> Zentral in der Stadt</li><li><strong>Depot Brugg:</strong> Für Brugg und Umgebung</li><li><strong>Depot Gebenstorf:</strong> Direkt beim Geisshof</li><li><strong>Depot Wettingen:</strong> Für Wettingen und Umgebung</li></ul><p><strong>Abholzeiten:</strong> Dienstag und Freitag, ab 16:00 Uhr</p>',
            'layout' => 'component',
            'component' => 'depot_map',
        ]);

        addSection($standortePage, [
            'id' => 'B-06',
            'title' => 'Möchtest du uns kennenlernen?',
            'text' => '',
            'layout' => 'rich_text',
            'button_text' => 'Nimm Kontakt auf',
            'button_href' => '/kontakt',
            'button2_text' => 'Zu uns finden',
            'button2_href' => '/standorte-depots',
        ]);

        $log[] = "  OK Standorte-Depots (3 sections)";
    }

    // ==================================================================================
    // 8. AKTUELLES
    // ==================================================================================

    $log[] = "\nStep 8: Aktuelles...";

    $aktuellesPage = ensurePage('aktuelles', 'Aktuelles', 'aktuelles_page', '/');
    if ($aktuellesPage) {
        setHero($aktuellesPage, 'Aktuelles');
        setNav($aktuellesPage);
        clearSections($aktuellesPage);

        addSection($aktuellesPage, [
            'id' => 'events',
            'title' => '',
            'text' => '',
            'layout' => 'component',
            'component' => 'events_feed',
        ]);

        $log[] = "  OK Aktuelles (1 section)";
    }

    // ==================================================================================
    // 9. BIOCÒ WERDEN
    // ==================================================================================

    $log[] = "\nStep 9: biocò werden...";

    $biocoPage = ensurePage('bioco-werden', 'biocò werden', 'bioco_werden', '/');
    if ($biocoPage) {
        setHero($biocoPage, 'biocò werden', 'Wähle dein Gemüseabo und werde Teil unserer Genossenschaft');
        setNav($biocoPage);
        clearSections($biocoPage);

        addSection($biocoPage, [
            'id' => 'pricing',
            'title' => '',
            'text' => '',
            'layout' => 'component',
            'component' => 'pricing_calculator',
        ]);

        addSection($biocoPage, [
            'id' => 'membership',
            'title' => '',
            'text' => '',
            'layout' => 'component',
            'component' => 'membership_form',
        ]);

        $log[] = "  OK biocò werden (2 sections)";
    }

    // ==================================================================================
    // 10. KONTAKT
    // ==================================================================================

    $log[] = "\nStep 10: Kontakt...";

    $kontaktPage = ensurePage('kontakt', 'Kontakt', 'kontakt', '/');
    if ($kontaktPage) {
        setHero($kontaktPage, 'Kontakt', 'Hast du Fragen zu biocò? Wir freuen uns auf deine Nachricht!');
        setNav($kontaktPage);
        clearSections($kontaktPage);

        addSection($kontaktPage, [
            'id' => 'mitglied',
            'title' => 'Du bist bereits Mitglied?',
            'text' => '<p>Nutze unser Intranet für interne Kommunikation und Planung.</p>',
            'layout' => 'rich_text',
            'button_text' => 'Zum Intranet',
            'button_href' => '/intranet',
        ]);

        addSection($kontaktPage, [
            'id' => 'werden',
            'title' => 'Möchtest du Mitglied werden?',
            'text' => '<p>Informiere dich über unsere Abos und werde Teil von biocò.</p>',
            'layout' => 'rich_text',
            'button_text' => 'biocò werden',
            'button_href' => '/bioco-werden',
        ]);

        addSection($kontaktPage, [
            'id' => 'kontakt-form',
            'title' => 'Allgemeine Anfragen',
            'text' => '<p>Für allgemeine Fragen nutze bitte das Kontaktformular.</p>',
            'layout' => 'component',
            'component' => 'contact_form',
        ]);

        $log[] = "  OK Kontakt (3 sections)";
    }

    // ==================================================================================
    // 11. NEWSLETTER
    // ==================================================================================

    $log[] = "\nStep 11: Newsletter...";

    $newsletterPage = ensurePage('newsletter', 'Newsletter', 'newsletter', '/');
    if ($newsletterPage) {
        setHero($newsletterPage, 'Newsletter abonnieren');
        setNav($newsletterPage);
        clearSections($newsletterPage);

        addSection($newsletterPage, [
            'id' => 'subscribe',
            'title' => '',
            'text' => '',
            'layout' => 'component',
            'component' => 'subscribe_form',
        ]);

        $log[] = "  OK Newsletter (1 section)";
    }

    // ==================================================================================
    // 12. WARTELISTE
    // ==================================================================================

    $log[] = "\nStep 12: Warteliste...";

    $wartelistePage = ensurePage('warteliste', 'Warteliste', 'warteliste', '/');
    if ($wartelistePage) {
        setHero($wartelistePage, 'Warteliste');
        setNav($wartelistePage);
        clearSections($wartelistePage);

        addSection($wartelistePage, [
            'id' => 'waiting-list',
            'title' => '',
            'text' => '',
            'layout' => 'component',
            'component' => 'waiting_list_form',
        ]);

        $log[] = "  OK Warteliste (1 section)";
    }

    // ==================================================================================
    // 13. ANMELDUNG (basic-page with body)
    // ==================================================================================

    $log[] = "\nStep 13: Anmeldung...";

    $anmeldungPage = ensurePage('anmeldung', 'Anmeldung', 'basic-page', '/');
    if ($anmeldungPage) {
        setBody($anmeldungPage, '<h2>Anmeldung zur Gemüsegenossenschaft biocò</h2>

<p>Werde Mitglied bei biocò und erhalte wöchentlich frisches Demeter-Gemüse direkt vom Geisshof in Gebenstorf.</p>

<h3>Bevor du dich anmeldest</h3>

<ul>
<li><strong>Anteile & Beitrag:</strong> Jedes Mitglied erwirbt Anteilsscheine zu je CHF 250 (einmalige Zahlung). Die Anzahl der Anteile hängt von deinem gewählten Abo ab. Der Jahresbeitrag für dein Gemüse-Abo wird per 31. Januar fällig und kann quartalsweise oder jährlich bezahlt werden.</li>
<li><strong>Bindung & Kündigung:</strong> Das Gemüseabo läuft vom 1. Januar bis zum 31. Dezember. Ohne Kündigung verlängert es sich jeweils um ein Kalenderjahr. Die Kündigungsfrist beträgt zwei Monate auf Ende eines Kalenderjahres.</li>
<li><strong>Mitarbeit:</strong> Wir sind eine Mitmach-Genossenschaft! Jedes Mitglied leistet pro Jahr 10 Arbeitseinsätze à 2 Stunden (bei halbem Korb) bzw. 20 Arbeitseinsätze à 2 Stunden (bei ganzem Korb). Dies kann auf dem Feld, in der Logistik oder bei Events sein.</li>
<li><strong>Wetterbedingte Ertragsschwankungen:</strong> Es kann zu Ernteausfällen kommen und der wöchentliche Gemüsekorb kann nicht immer gleich voll sein.</li>
</ul>

<p>Bereit? Dann melde dich über unser <a href="/bioco-werden">Anmeldeformular</a> an.</p>');

        $log[] = "  OK Anmeldung";
    }

    // ==================================================================================
    // 14. ANMELDUNG/DANKE (child of anmeldung)
    // ==================================================================================

    $log[] = "\nStep 14: Anmeldung/Danke...";

    $dankePage = ensurePage('danke', 'Vielen Dank für deine Anmeldung!', 'basic-page', '/anmeldung/');
    if ($dankePage) {
        setBody($dankePage, '<h2>Vielen Dank für deine Anmeldung!</h2>

<p>Wir prüfen deine Anmeldung. In der Zwischenzeit kannst du unseren Mitgliederbereich schon anschauen. Klicke dafür oben rechts auf die Ente.</p>

<h3>So geht es weiter</h3>

<ol>
<li><strong>Bestätigungs-E-Mail:</strong> Du erhältst eine E-Mail mit Bestätigungslink (Double Opt-In). Bitte bestätige deine Anmeldung.</li>
<li><strong>Rechnung:</strong> Nach Bestätigung erhältst du eine Rechnung per 31. Januar. Du kannst quartalsweise oder das ganze Jahr bezahlen.</li>
<li><strong>Start:</strong> Du erhältst Zugang zum Intranet und kannst deine Arbeitseinsätze planen. Ab Januar startet die Gemüseverteilung!</li>
</ol>

<h3>Fragen?</h3>
<p>Bei Fragen zur Anmeldung oder zu biocò kannst du uns jederzeit kontaktieren: <a href="mailto:info@bioco.ch">info@bioco.ch</a></p>

<p><a href="/">Zurück zur Startseite</a></p>');

        $log[] = "  OK Anmeldung/Danke";
    }

    // ==================================================================================
    // 15. TAG DER OFFENEN TÜR (basic-page with body)
    // ==================================================================================

    $log[] = "\nStep 15: Tag der offenen Tür...";

    $tagPage = ensurePage('tag-der-offenen-tuer', 'Tag der offenen Tür', 'basic-page', '/');
    if ($tagPage) {
        setBody($tagPage, '<h2>Tag der offenen Tür</h2>

<p>Komm vorbei und lerne den Geisshof und die Gemüsegenossenschaft biocò kennen! An unseren Tagen der offenen Tür zeigen wir dir unsere Felder, erklären das Solawi-Modell und beantworten alle deine Fragen.</p>

<h3>Anmeldung</h3>
<p>Melde dich für einen unserer Besuchstage an. Wir freuen uns auf dich!</p>

<p>Für allgemeine Anfragen: <a href="/kontakt">Kontaktformular</a></p>');

        $log[] = "  OK Tag der offenen Tür";
    }

    // ==================================================================================
    // 16. KUNDENPORTAL (basic-page with body)
    // ==================================================================================

    $log[] = "\nStep 16: Kundenportal...";

    $portalPage = ensurePage('kundenportal', 'Kundenportal', 'basic-page', '/');
    if ($portalPage) {
        setBody($portalPage, '<h2>Kundenportal</h2>

<p>Willkommen im Kundenportal von biocò. Hier findest du Zugang zu den internen Bereichen für Mitglieder.</p>

<h3>Mitglieder-Portal</h3>
<p>Zugang zum externen Mitglieder-Portal für Kontoinformationen und Einstellungen.</p>

<h3>Einsatzplanung</h3>
<p>Plane deine Arbeitseinsätze und sieh dir den aktuellen Einsatzplan an.</p>

<p>Bei Problemen mit dem Zugang: <a href="mailto:info@bioco.ch">info@bioco.ch</a></p>');

        $log[] = "  OK Kundenportal";
    }

    // ==================================================================================
    // 17. INTRANET (basic-page with body)
    // ==================================================================================

    $log[] = "\nStep 17: Intranet...";

    $intranetPage = ensurePage('intranet', 'Intranet', 'basic-page', '/');
    if ($intranetPage) {
        setBody($intranetPage, '<h2>Intranet</h2>

<p>Das Intranet von biocò ist der interne Bereich für alle Mitglieder der Genossenschaft. Hier findest du wichtige Dokumente, Informationen und Tools für die tägliche Arbeit mit biocò.</p>

<h3>Was findest du im Intranet?</h3>
<ul>
<li><strong>Verteilplan:</strong> Dienstag und Freitag Abholpläne</li>
<li><strong>Fahrspesen-Rückforderungsformular:</strong> Für Fahrspesen-Rückerstattungen</li>
<li><strong>Interne Dokumente:</strong> Alle wichtigen Unterlagen</li>
<li><strong>Mitgliederbereich:</strong> Persönliche Informationen und Einstellungen</li>
</ul>

<h3>Zugang zum Intranet</h3>
<p>Das Intranet ist nur für Mitglieder der Genossenschaft zugänglich. Du benötigst einen Zugang, um dich anzumelden.</p>
<p><a href="https://intranet.bioco.ch">Zum Intranet</a></p>

<h3>Dokumente</h3>
<ul>
<li>Verteilplan Dienstag und Freitag (PDF)</li>
<li>Fahrspesen Rückforderungsformular (PDF)</li>
</ul>

<h3>Fragen?</h3>
<p>Hast du Fragen zum Intranet oder benötigst du Hilfe beim Zugang? Dann kontaktiere uns unter <a href="mailto:info@bioco.ch">info@bioco.ch</a>.</p>');

        $log[] = "  OK Intranet";
    }

    // ==================================================================================
    // 18. DATENSCHUTZ (basic-page with body)
    // ==================================================================================

    $log[] = "\nStep 18: Datenschutz...";

    $datenschutzPage = ensurePage('datenschutz', 'Datenschutzerklärung', 'basic-page', '/');
    if ($datenschutzPage) {
        setBody($datenschutzPage, '<h2>Datenschutzerklärung</h2>

<h3>1. Datenschutz auf einen Blick</h3>
<p>Die folgenden Hinweise geben einen Überblick darüber, was mit deinen personenbezogenen Daten passiert, wenn du diese Website besuchst. Personenbezogene Daten sind alle Daten, mit denen du persönlich identifiziert werden kannst.</p>

<h3>2. Verantwortliche Stelle</h3>
<p>Gemüsegenossenschaft biocò<br>
Geisshof<br>
5412 Gebenstorf<br>
Schweiz<br>
E-Mail: <a href="mailto:info@bioco.ch">info@bioco.ch</a></p>

<h3>3. Datenerfassung auf dieser Website</h3>
<h4>Kontaktformular</h4>
<p>Wenn du uns per Kontaktformular Anfragen zukommen lässt, werden deine Angaben aus dem Anfrageformular inklusive der von dir dort angegebenen Kontaktdaten zwecks Bearbeitung der Anfrage und für den Fall von Anschlussfragen bei uns gespeichert.</p>

<h4>Double Opt-In (DOI)</h4>
<p>Bei allen Formularen verwenden wir ein Double Opt-In Verfahren. Du erhältst nach dem Absenden eine E-Mail mit einem Bestätigungslink. Erst nach Klick auf diesen Link wird deine Anfrage/Anmeldung bearbeitet.</p>

<h3>4. Cookies</h3>
<p>Diese Website verwendet keine Cookies. Wir verwenden Matomo Analytics im cookieless Modus, wodurch keine persönlichen Daten in deinem Browser gespeichert werden.</p>

<h3>5. Deine Rechte</h3>
<p>Du hast jederzeit das Recht auf unentgeltliche Auskunft über deine gespeicherten personenbezogenen Daten, deren Herkunft und Empfänger und den Zweck der Datenverarbeitung sowie ein Recht auf Berichtigung, Sperrung oder Löschung dieser Daten. Hierzu sowie zu weiteren Fragen zum Thema personenbezogene Daten kannst du dich jederzeit unter der im Impressum angegebenen Adresse an uns wenden.</p>

<h3>6. SSL-Verschlüsselung</h3>
<p>Diese Seite nutzt aus Sicherheitsgründen und zum Schutz der Übertragung vertraulicher Inhalte eine SSL-Verschlüsselung. Eine verschlüsselte Verbindung erkennst du daran, dass die Adresszeile des Browsers von "http://" auf "https://" wechselt und an dem Schloss-Symbol in deiner Browserzeile.</p>');

        $log[] = "  OK Datenschutz";
    }

    // ==================================================================================
    // 19. IMPRESSUM (basic-page with body)
    // ==================================================================================

    $log[] = "\nStep 19: Impressum...";

    $impressumPage = ensurePage('impressum', 'Impressum', 'basic-page', '/');
    if ($impressumPage) {
        setBody($impressumPage, '<h2>Impressum</h2>

<h3>Kontakt</h3>
<p>Gemüsegenossenschaft biocò<br>
Geisshof<br>
5412 Gebenstorf<br>
Schweiz<br>
E-Mail: <a href="mailto:info@bioco.ch">info@bioco.ch</a></p>

<h3>Vertretungsberechtigte Personen</h3>
<p>Betriebsgruppe der Gemüsegenossenschaft biocò</p>

<h3>Haftungsausschluss</h3>
<p>Der Inhalt dieser Website wurde mit grösster Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen.</p>

<h3>Urheberrecht</h3>
<p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem schweizerischen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung ausserhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.</p>');

        $log[] = "  OK Impressum";
    }

    // ==================================================================================
    // 20. STATUTEN (basic-page with body)
    // ==================================================================================

    $log[] = "\nStep 20: Statuten...";

    $statutenPage = ensurePage('statuten', 'Statuten', 'basic-page', '/');
    if ($statutenPage) {
        setBody($statutenPage, '<h2>Statuten</h2>

<p>Die Statuten der Gemüsegenossenschaft biocò regeln die Struktur und Organisation der Genossenschaft.</p>

<h3>Dokumente zum Download</h3>
<ul>
<li><a href="/statuten/13-11-15_Statuten_bioco.pdf">Statuten (PDF)</a></li>
<li><a href="/statuten/2212_Betriebsreglement.pdf">Reglement (PDF)</a></li>
</ul>

<h3>Über die Genossenschaft</h3>
<p>Die Gemüsegenossenschaft biocò ist eine Genossenschaft nach schweizerischem Recht. Sie wurde 2014 gegründet und betreibt solidarische Landwirtschaft (Community Supported Agriculture) auf dem Geisshof in Gebenstorf.</p>

<p>Die Genossenschaft basiert auf dem Prinzip der Solidarität: Mitglieder teilen sich Arbeit und Ertrag gemeinsam.</p>

<h3>Mitgliedschaft</h3>
<p>Um Mitglied zu werden, benötigst du Anteilsscheine der Genossenschaft (CHF 250 pro Anteil).</p>
<ul>
<li>Halb Gemüsekorb: 1 Anteil</li>
<li>Standard Gemüsekorb: 2 Anteile</li>
<li>Doppel Gemüsekorb: 4 Anteile</li>
</ul>

<p><a href="/mitmachen">Jetzt Mitglied werden</a></p>

<h3>Weitere Informationen</h3>
<p>Bei Fragen zu den Statuten oder zur Genossenschaft kontaktiere uns: <a href="mailto:info@bioco.ch">info@bioco.ch</a> oder nutze unser <a href="/kontakt">Kontaktformular</a>.</p>');

        $log[] = "  OK Statuten";
    }

    // ==================================================================================
    // 21. MEDIEN (hidden, media library parent)
    // ==================================================================================

    $log[] = "\nStep 21: Medien (hidden)...";

    $medienPage = ensurePage('medien', 'Medien', 'basic-page', '/', true);
    if ($medienPage) {
        setBody($medienPage, '<p>Medienbibliothek: Bilder und Dateien für die Website.</p>');
        $log[] = "  OK Medien (hidden)";
    }

    // ==================================================================================
    // 22. EVENTS (hidden, parent for event items)
    // ==================================================================================

    $log[] = "\nStep 22: Events (hidden)...";

    $eventsPage = ensurePage('events', 'Events', 'basic-page', '/', true);
    if ($eventsPage) {
        setBody($eventsPage, '<p>Übergeordnete Seite für Event-Einträge.</p>');
        $log[] = "  OK Events (hidden)";
    }

    // ==================================================================================
    // Set include_in_nav = 0 on utility/legal/hidden pages
    // ==================================================================================

    $log[] = "\nSetting navigation flags...";
    $noNavPages = [$anmeldungPage, $dankePage, $tagPage, $portalPage, $intranetPage,
                   $datenschutzPage, $impressumPage, $statutenPage, $medienPage, $eventsPage];
    foreach ($noNavPages as $p) {
        if ($p) setNav($p, 0);
    }
    $log[] = "  OK nav flags set (10 pages excluded from nav)";

    // ==================================================================================
    // SUMMARY
    // ==================================================================================

    $log[] = "\n=== Population Complete ===";
    $log[] = "22 pages total: 12 content + 8 basic-page + 2 hidden/structural";
    $log[] = "All hero sections, content sections, body text set";
    $log[] = "/anmeldung/danke/ nested under /anmeldung/";
    $log[] = "/medien/ and /events/ hidden";

    if (count($errors) > 0) {
        $log[] = "\nErrors (" . count($errors) . "):";
        $log = array_merge($log, $errors);
    }

    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'pages_created' => 22,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT);
}
