<?php
/**
 * Page Migration Script
 * 
 * Migrates existing hardcoded pages to CMS-editable pages.
 * Supports overwriting existing pages (clears and re-populates sections).
 * 
 * Usage:
 * 1. Create a page with template 'api-setup' (or basic-page) in ProcessWire admin
 * 2. Visit the page to run migration
 * 3. Delete the page after migration completes
 * 
 * Add ?overwrite=1 to URL to refresh existing pages
 */

namespace ProcessWire;

if (!defined('PROCESSWIRE')) {
    die('This file must be run within ProcessWire');
}

$pages = wire('pages');
$templates = wire('templates');
$fields = wire('fields');
$input = wire('input');

// Check for overwrite flag
$overwrite = $input->get('overwrite') == '1';

$output = [];
$output[] = "=== Page Migration ===";
$output[] = "Overwrite mode: " . ($overwrite ? "ON (existing pages will be refreshed)" : "OFF (existing pages will be skipped)");
$output[] = "";
$output[] = "Note: Homepage is managed separately via homepage_content template.";
$output[] = "";

// Get content parent
$contentParent = $pages->get('/content/');
if (!$contentParent->id) {
    $output[] = "ERROR: /content/ page not found. Run api-setup.php first.";
    echo "<pre>" . implode("\n", $output) . "</pre>";
    exit;
}

$pageContentTemplate = $templates->get('page_content');
if (!$pageContentTemplate) {
    $output[] = "ERROR: page_content template not found. Run api-setup.php first.";
    echo "<pre>" . implode("\n", $output) . "</pre>";
    exit;
}

// Helper to create or get page (with optional overwrite)
function getOrCreatePage($parent, $name, $title, $template, $overwrite = false) {
    $pages = wire('pages');
    $templates = wire('templates');
    
    $page = $pages->get("parent={$parent}, name={$name}");
    if ($page->id) {
        // Disable output formatting before modifying
        $page->of(false);
        if ($overwrite && $page->hasField('content_sections')) {
            // Clear existing sections for overwrite
            $page->content_sections->removeAll();
            $page->save();
            return ['page' => $page, 'created' => false, 'overwritten' => true];
        }
        return ['page' => $page, 'created' => false, 'overwritten' => false];
    }
    
    $page = wire(new Page());
    $page->template = $templates->get($template);
    $page->parent = $parent;
    $page->name = $name;
    $page->title = $title;
    $page->of(false); // Disable output formatting
    $page->save();
    
    return ['page' => $page, 'created' => true, 'overwritten' => false];
}

// Helper to add section
function addSection($page, $sectionId, $title, $text, $layout = 'rich_text', $theme = 'default', $component = null) {
    if (!$page->hasField('content_sections')) {
        return false;
    }
    
    // Ensure output formatting is disabled
    $page->of(false);
    
    $section = $page->content_sections->getNew();
    $section->of(false);
    $section->section_id = $sectionId;
    $section->section_title = $title;
    $section->section_text = $text;
    if ($section->hasField('section_layout')) {
        $section->section_layout = $layout;
    }
    if ($section->hasField('section_theme')) {
        $section->section_theme = $theme;
    }
    if ($component && $section->hasField('section_component')) {
        $section->section_component = $component;
    }
    $section->save();
    $page->content_sections->add($section);
    $page->save();
    
    return true;
}

$output[] = "--- Creating Pages ---";

// Helper function to check if we should populate
function shouldPopulate($result) {
    return $result['created'] || $result['overwritten'];
}

function getStatusText($result, $name) {
    if ($result['created']) {
        return "Created /content/{$name}/";
    } elseif ($result['overwritten']) {
        return "Overwritten /content/{$name}/";
    } else {
        return "Skipped /content/{$name}/ (already exists)";
    }
}

// 1. Mitmachen
$result = getOrCreatePage($contentParent, 'mitmachen', 'Mitmachen', 'page_content', $overwrite);
$mitmachen = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'mitmachen');

    $mitmachenIntro = <<<HTML
<p>Werde Teil unserer Gemüsegenossenschaft und erlebe <a href="/solawi">solidarische Landwirtschaft</a> hautnah. Hier erfährst du, wie du dich einbringen kannst und was Mitarbeit bei biocò bedeutet.</p>
HTML;

    $mitarbeitText = <<<HTML
<p>Jedes Mitglied bringt sich ein und unterstützt die Genossenschaft aktiv. Die Mitarbeit ist ein wichtiger Teil unserer <a href="/solawi">solidarischen Landwirtschaft</a>.</p>
<h3>Mitarbeit bei biocò</h3>
<h4>Tätigkeitsbereiche</h4>
<p>Du kannst dich in verschiedenen Bereichen einbringen:</p>
<ul>
  <li><strong>Feld/Anbau:</strong> Säen, Pflanzen, Jäten, Ernten, Unkraut bekämpfen</li>
  <li><strong>Logistik:</strong> Gemüse waschen, sortieren, packen, verteilen</li>
  <li><strong>Administration:</strong> Büroarbeit, Rechnungen, Kommunikation</li>
  <li><strong>Events/Organisation:</strong> Schnuppertage, Veranstaltungen, Gemeinschaftsanlässe</li>
  <li><strong>Andere:</strong> Nach Absprache kannst du auch andere Fähigkeiten einbringen</li>
</ul>
<h4>Planung</h4>
<p>Nach der Anmeldung erhältst du Zugang zum Intranet. Dort kannst du:</p>
<ul>
  <li>Deine bevorzugten Tage angeben (Mo-Sa)</li>
  <li>Deine bevorzugten Zeiten wählen (morgens, nachmittags, abends)</li>
  <li>Tätigkeitsbereiche auswählen</li>
  <li>Arbeitseinsätze planen und buchen</li>
</ul>
HTML;

    $gruppenText = <<<HTML
<p>Bei biocò gibt es verschiedene Arbeitsgruppen und Gemeinschaftsaktivitäten, die das Herzstück unserer Genossenschaft bilden.</p>
<p>Diese Gruppen ermöglichen es, sich nach eigenen Interessen und Fähigkeiten einzubringen und die Genossenschaft aktiv mitzugestalten. Jede Gruppe trägt auf ihre Weise zum Erfolg und zur Gemeinschaft bei biocò bei.</p>
HTML;

    $schnuppertageText = <<<HTML
<p>Regelmässige Schnuppertage geben dir einen Einblick in unsere Arbeit und das Leben auf dem Geisshof.</p>
HTML;

    $familienText = <<<HTML
<h3>Kinder sind willkommen</h3>
<p>Familien und Kinder sind sehr regelmässige Helfer auf dem Geisshof. Die Einbindung von Kindern in den Prozess des Gemüseanbaus ist ein zentraler Bestandteil der biocò-Kultur.</p>
<p>Auf dem Geisshof erleben Kinder hautnah, wie Gemüse wächst, gepflegt wird und geerntet wird. Sie lernen spielerisch den Kreislauf der Natur kennen und entwickeln ein tiefes Verständnis für die Herkunft ihrer Nahrung. Diese praktische Erfahrung prägt nicht nur ihr Verhältnis zu Lebensmitteln, sondern stärkt auch das Gemeinschaftsgefühl zwischen den Generationen.</p>
<p>Die Elki-Gruppe organisiert spezielle Aktivitäten für Familien, bei denen Kinder aktiv mithelfen können – sei es beim Säen, Jäten, Ernten oder beim gemeinsamen Verarbeiten des Gemüses. Diese gemeinsamen Erlebnisse schaffen bleibende Erinnerungen und fördern das Verständnis für nachhaltige Landwirtschaft von klein auf.</p>
HTML;

    $kennenlernenText = <<<HTML
<p>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
<p><a href="/kontakt">Nimm Kontakt auf</a> · <a href="/standorte-depots">Zu uns finden</a></p>
HTML;

    addSection($mitmachen, 'intro', 'Mitmachen bei biocò', $mitmachenIntro, 'rich_text');
    addSection($mitmachen, 'mitarbeit', 'Was es braucht, damit wir gesundes Gemüse haben', $mitarbeitText, 'rich_text');
    addSection($mitmachen, 'gruppen', 'Gruppen & Gemeinschaft', $gruppenText, 'rich_text');
    addSection($mitmachen, 'schnuppertage', 'Schnuppertage', $schnuppertageText, 'component', 'default', 'schnuppertage');
    addSection($mitmachen, 'familien', 'Familien & Kinder auf dem Geisshof', $familienText, 'split_media_text');
    addSection($mitmachen, 'kennenlernen', 'Möchtest du uns kennenlernen?', $kennenlernenText, 'rich_text');

    $output[] = "  Added 6 sections to mitmachen";
} else {
    $output[] = getStatusText($result, 'mitmachen');
}

// 2. Gemüse
$result = getOrCreatePage($contentParent, 'gemuese', 'Gemüse', 'page_content', $overwrite);
$gemuese = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'gemuese');

    $gemueseIntro = <<<HTML
<p>Unser saisonales Demeter-Gemüse wächst in der Region Baden-Brugg. Hier erfährst du, welche Gemüsesorten gerade Saison haben und in deinem Gemüsekorb landen.</p>
HTML;

    $saisonkalenderText = <<<HTML
<p>Wann ist welches Gemüse verfügbar? Entdecke unsere saisonale Vielfalt.</p>
HTML;

    $demeterText = <<<HTML
<h3>Warum Demeter?</h3>
<p>Demeter ist die höchste Qualitätsstufe im biologischen Landbau. Als Demeter-zertifizierter Betrieb gehen wir über die Anforderungen von Bio Suisse hinaus und arbeiten nach den strengsten biologisch-dynamischen Richtlinien.</p>
<p>Unser Gemüse wächst auf dem Geisshof in Gebenstorf im Rahmen unserer <a href="/solawi">solidarischen Landwirtschaft (Solawi)</a> – direkt aus der Region Baden-Brugg.</p>
<ul>
  <li><strong>Biologisch-dynamische Landwirtschaft:</strong> Der Hof als lebendiger Organismus, Präparate fördern Bodenfruchtbarkeit und Pflanzengesundheit.</li>
  <li><strong>Kein Einsatz von synthetischen Mitteln:</strong> Wir verzichten auf synthetische Dünger, Pestizide und Herbizide.</li>
  <li><strong>Kreislaufwirtschaft:</strong> Kompost, Gründüngung und Fruchtfolgen sorgen für gesunde Böden.</li>
  <li><strong>Biodiversität:</strong> Hecken, Blumenstreifen und vielfältige Fruchtfolgen fördern Artenvielfalt.</li>
</ul>
<p><a href="https://www.demeter.ch" target="_blank" rel="noopener noreferrer">Mehr über Demeter erfahren →</a></p>
HTML;

    $galleryText = <<<HTML
<p>Einblicke in unsere Ernte, den Anbau und die Gemeinschaft.</p>
HTML;

    $kennenlernenText = <<<HTML
<p>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
<p><a href="/kontakt">Nimm Kontakt auf</a> · <a href="/standorte-depots">Zu uns finden</a></p>
HTML;

    addSection($gemuese, 'intro', 'Unser Gemüse', $gemueseIntro, 'rich_text');
    addSection($gemuese, 'saisonkalender', 'Saisonkalender', $saisonkalenderText, 'component', 'default', 'saisonkalender');
    addSection($gemuese, 'demeter', 'Demeter-Qualität', $demeterText, 'rich_text');
    addSection($gemuese, 'gallery', 'Was wir anbauen', $galleryText, 'component', 'default', 'gallery');
    addSection($gemuese, 'kennenlernen', 'Möchtest du uns kennenlernen?', $kennenlernenText, 'rich_text');

    $output[] = "  Added 5 sections to gemuese";
} else {
    $output[] = getStatusText($result, 'gemuese');
}

// 3. Solawi
$result = getOrCreatePage($contentParent, 'solawi', 'Solawi', 'page_content', $overwrite);
$solawi = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'solawi');

    $solawiIntro = <<<HTML
<p>Eine Solawi – Solidarische Landwirtschaft – ist eine gemeinschaftliche Form des Wirtschaftens, bei der Verbraucherinnen und Produzentinnen eine Partnerschaft eingehen. Die Mitglieder tragen gemeinsam die Kosten der landwirtschaftlichen Produktion und erhalten im Gegenzug einen regelmässigen Anteil an frischem Bio-Gemüse aus lokalem Anbau.</p>
<p>Statt anonym einzukaufen, entsteht eine direkte, verlässliche Verbindung zu dem Hof, der uns ernährt.</p>
HTML;

    $solawiDefinition = <<<HTML
<p>In einer Solawi tragen alle Beteiligten die Verantwortung für die Lebensmittelproduktion gemeinsam. Die Mitglieder finanzieren nicht einzelne Produkte, sondern unterstützen den landwirtschaftlichen Betrieb als Ganzes – mit ihren Beiträgen und oft auch mit ihrer Zeit. Im Gegenzug teilen sie die Ernte: was auf dem Feld wächst, landet direkt im gemeinsamen Gemüsekorb.</p>
<p>Solidarische Landwirtschaft lebt von <strong>Vertrauen</strong>, <strong>Transparenz</strong> und <strong>Gemeinschaft</strong>. Statt anonymer Märkte stehen Menschen und Beziehungen im Zentrum: Mitglieder wissen, wer ihr Gemüse anbaut – und der Hof weiss, für wen er arbeitet.</p>
HTML;

    $solawiHow = <<<HTML
<h3>1. Gemeinsame Finanzierung</h3>
<p>Zu Beginn des Jahres kalkuliert der Betrieb die voraussichtlichen Kosten (Saatgut, Arbeitskräfte, Maschinen, etc.). Diese Kosten werden auf alle Mitglieder aufgeteilt. Jedes Mitglied bezahlt einen jährlichen Beitrag und erwirbt damit einen oder mehrere Anteile.</p>
<h3>2. Wöchentliche Ernte-Anteile</h3>
<p>Im Gegenzug erhalten die Mitglieder wöchentlich ihren Anteil am Ernteertrag. Was gerade auf dem Feld wächst und reif ist, landet im Gemüsekorb – saisonal, frisch und vielfältig.</p>
<h3>3. Mitarbeit und Teilhabe</h3>
<p>Ein zentrales Element der Solawi ist die <strong>Mitarbeit</strong>. Mitglieder helfen bei Feldarbeiten, der Ernte oder der Logistik. Durch diese Beteiligung entsteht eine direkte Verbindung zur Landwirtschaft und ein tiefes Verständnis für die Arbeit, die hinter unserem Essen steckt.</p>
<h3>4. Teilen von Risiko und Ertrag</h3>
<p>In der Solawi tragen alle gemeinsam das Risiko: Hagelt es die Tomaten weg oder gibt es eine besonders gute Karottenernte? Alle Mitglieder profitieren oder verzichten gemeinsam.</p>
HTML;

    $solawiBenefits = <<<HTML
<h3>Vorteile für Konsument:innen</h3>
<ul>
  <li><strong>Frisches, regionales Gemüse:</strong> Kurze Wege vom Feld zum Teller, maximale Frische</li>
  <li><strong>Transparenz:</strong> Du weisst genau, wo und wie dein Gemüse angebaut wird</li>
  <li><strong>Saisonalität erleben:</strong> Entdecke die Vielfalt saisonaler Gemüsesorten</li>
  <li><strong>Mitbestimmung:</strong> Mitglieder haben Mitspracherecht in der Genossenschaft</li>
  <li><strong>Gemeinschaft:</strong> Gemeinsam gärtnern, feiern und lernen</li>
</ul>
<h3>Vorteile für Produzent:innen</h3>
<ul>
  <li><strong>Planungssicherheit:</strong> Finanzierung ist zu Jahresbeginn gesichert</li>
  <li><strong>Unabhängigkeit:</strong> Keine Abhängigkeit von Grossverteilern oder Marktpreisen</li>
  <li><strong>Direkter Kontakt:</strong> Persönlicher Austausch mit den Konsument:innen</li>
  <li><strong>Ökologischer Anbau:</strong> Fokus auf Nachhaltigkeit statt Gewinnmaximierung</li>
</ul>
<h3>Vorteile für die Umwelt</h3>
<ul>
  <li><strong>Biologischer Anbau:</strong> Keine synthetischen Pestizide oder Dünger</li>
  <li><strong>Biodiversität:</strong> Förderung der Artenvielfalt durch vielfältige Fruchtfolgen</li>
  <li><strong>Kurze Transportwege:</strong> Regionale Versorgung statt globaler Lieferketten</li>
  <li><strong>Ressourcenschonung:</strong> Kreislaufwirtschaft und nachhaltige Bodenpflege</li>
</ul>
HTML;

    $solawiBioco = <<<HTML
<p>Auch bei bioco leben wir dieses Prinzip: Unsere Solawi ist Teil des Geisshofs in Gebenstorf AG, der nach strengen biologisch-dynamischen Grundsätzen arbeitet. Das bedeutet geschlossene Kreisläufe, schonende Bodenbewirtschaftung und Gemüse, das wirklich aus der Region kommt.</p>
<p>Mit deinem Anteil und deiner Mitarbeit unterstützt du nicht nur den Anbau hochwertiger Demeter-Gemüsekisten, sondern auch eine Landwirtschaft, die sozial, ökologisch und langfristig tragfähig ist. Als lokale Gemüsegenossenschaft im Aargau leben wir Transparenz, Beteiligung und echte Nähe.</p>
<p>Bei bioco bist du nicht nur Abnehmer:in, sondern Teil des Ganzen: Als Mitglied hilfst du bei der Feldarbeit mit, erlebst die Jahreszeiten auf dem Acker und siehst, wie echtes Bio-Gemüse aus solidarischer Landwirtschaft entsteht.</p>
<h3>So funktioniert unsere Solawi in der Praxis</h3>
<ul>
  <li><strong>Jahresbeitrag:</strong> Mitglieder bezahlen zu Jahresbeginn ihren Anteil (je nach Korbgrösse CHF 750 – CHF 2&apos;350)</li>
  <li><strong>Wöchentlicher Gemüsekorb:</strong> Abholung in <a href=\"/standorte-depots\">Depots in Baden, Brugg oder Gebenstorf</a></li>
  <li><strong>Mitarbeit:</strong> Je nach Abo-Grösse 10–40 Halbtage pro Jahr (<a href=\"/mitmachen\">mehr zu Mitarbeit</a>)</li>
  <li><strong>Demeter-Qualität:</strong> Strengste Bio-Standards (<a href=\"/gemuese\">mehr zu unserem Gemüse</a>)</li>
  <li><strong>Genossenschaftsmodell:</strong> Mitbestimmung bei Entscheidungen</li>
</ul>
<p>Als Teil von bioco wirst du zudem Mitglied einer lebendigen, offenen Gemeinschaft rund um den Geisshof. Anlässe wie Open-Air-Kino, Fondue über dem Feuer oder Kräutergruppen-Treffen machen unsere Solawi zu einem Ort, an dem man sich schnell zuhause fühlt.</p>
HTML;

    $solawiFaq = <<<HTML
<h3>Was bedeutet Solawi?</h3>
<p>Solawi ist die Abkürzung für &quot;Solidarische Landwirtschaft&quot;. Auch die Schreibweise &quot;SoLaWi&quot; ist verbreitet. International spricht man von &quot;Community Supported Agriculture&quot; (CSA).</p>
<h3>Wie unterscheidet sich Solawi vom Abo-Gemüse?</h3>
<p>Bei einem klassischen Gemüseabo kauft man eine Dienstleistung: X Gemüse für Y Franken. Bei der Solawi finanziert man gemeinsam einen Betrieb und teilt Risiko und Ertrag. Mitarbeit und Genossenschaftsmodell sind zentral.</p>
<h3>Muss ich zwingend mitarbeiten?</h3>
<p>Bei biocò ist Mitarbeit Teil des Konzepts. Je nach Abo-Grösse arbeitet ihr 10–40 Halbtage pro Jahr mit. Die Mitarbeit ist zentral für das Verständnis und die Verbindung zur Landwirtschaft.</p>
<h3>Was passiert bei Ernteausfällen?</h3>
<p>Bei der Solawi tragen alle das Risiko gemeinsam. Gibt es weniger Ernte (z.B. durch Hagel), gibt es auch weniger im Gemüsekorb. Umgekehrt profitieren alle von einer besonders guten Saison.</p>
HTML;

    addSection($solawi, 'intro', 'Solidarische Landwirtschaft', $solawiIntro, 'rich_text');
    addSection($solawi, 'definition', 'Was ist Solawi? – Definition', $solawiDefinition, 'rich_text');
    addSection($solawi, 'how', 'Wie funktioniert Solidarische Landwirtschaft?', $solawiHow, 'rich_text');
    addSection($solawi, 'benefits', 'Warum Solawi? – Vorteile für Mitglieder & Umwelt', $solawiBenefits, 'rich_text');
    addSection($solawi, 'bioco', 'Solidarische Landwirtschaft bei biocò', $solawiBioco, 'rich_text');
    addSection($solawi, 'faq', 'Häufige Fragen zu Solawi', $solawiFaq, 'rich_text');

    $output[] = "  Added 6 sections to solawi";
} else {
    $output[] = getStatusText($result, 'solawi');
}

// 4. Abos
$result = getOrCreatePage($contentParent, 'abos', 'Abos', 'page_content', $overwrite);
$abos = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'abos');

    $abosIntro = <<<HTML
<p>Wöchentlich frisches Demeter-Gemüse direkt vom Geisshof in deinen Gemüsekorb. Mit deinem Gemüseabo unterstützt du unsere <a href="/solawi">solidarische Landwirtschaft (Solawi)</a> und wirst Teil unserer Gemüsegenossenschaft. Hier erfährst du alles über unsere Abo-Modelle, Preise und wie du Mitglied werden kannst.</p>
HTML;

    $abosPricing = <<<HTML
<p>Das Gemüseabo läuft vom 1. Januar bis zum 31. Dezember. Ohne Kündigung verlängert sich das Gemüseabo jeweils um ein Kalenderjahr. Die Kündigungsfrist beträgt zwei Monate auf Ende eines Kalenderjahres.</p>
<table>
  <thead>
    <tr>
      <th>Gemüsekorb</th>
      <th>Personen</th>
      <th>Jahrespreis</th>
      <th>Anteilsscheine Kosten</th>
      <th>Mitarbeit pro Jahr</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Halb</strong><br />1 Anteilsschein</td>
      <td>1</td>
      <td>CHF 750.-</td>
      <td>CHF 250.-</td>
      <td>10 Arbeitseinsätze<br />à 2 Stunden</td>
    </tr>
    <tr>
      <td><strong>Standard</strong><br />2 Anteilsscheine</td>
      <td>2-3</td>
      <td>CHF 1&apos;280.-</td>
      <td>CHF 500.-</td>
      <td>20 Arbeitseinsätze<br />à 2 Stunden</td>
    </tr>
    <tr>
      <td><strong>Doppel</strong><br />4 Anteilsscheine</td>
      <td>4-6</td>
      <td>CHF 2&apos;350.-</td>
      <td>CHF 1&apos;000.-</td>
      <td>40 Arbeitseinsätze<br />à 2 Stunden</td>
    </tr>
  </tbody>
</table>
<p><strong>Anteilsscheine:</strong> Jeder Anteilsschein kostet CHF 250.- und ist eine Bedingung für den Bezug eines Gemüsekorbes. Du kannst zusätzliche Anteilsscheine erwerben, um die Genossenschaft stärker zu unterstützen.</p>
<p><strong>💡 Tipp:</strong> Geteilte Körbe sparen CHF 110 pro Jahr und reduzieren Logistikaufwand. Wir empfehlen, Körbe zu teilen.</p>
<h3>Was ist im Gemüsekorb?</h3>
<p>Bestellen Sie Ihr Biogemüse direkt vom Hof: Unsere Bio Gemüse Kiste kommt wöchentlich frisch vom Geisshof. Die wöchentliche Bio Gemüse Lieferung landet in einem unserer Depots, wo Sie Ihren Gemüsekorb abholen können.</p>
<ul>
  <li>Wöchentlicher Gemüsekorb mit saisonalem Gemüse</li>
  <li>Demeter-Qualität – höchste Bio-Standards</li>
  <li>Frisch vom Geisshof in Gebenstorf</li>
  <li>Abholung in einem der <a href="/standorte-depots">Standorte</a> (ab 16:00 Uhr)</li>
</ul>
<p><a href="/gemuese">Mehr über unsere Ernte erfahren →</a></p>
<h3>Zahlungsweise</h3>
<p>Die erste Rechnung wird per 31. Januar fällig. Du kannst wählen:</p>
<ul>
  <li><strong>Quartalsweise:</strong> Du bezahlst vierteljährlich</li>
  <li><strong>Ganzes Jahr:</strong> Du bezahlst den gesamten Jahresbeitrag einmalig</li>
</ul>
HTML;

    $abosProbe = <<<HTML
<p>Möchtest du biocò erst einmal kennenlernen? Teste unser Gemüseabo für 3 Monate.</p>
<p><strong>Details:</strong></p>
<ul>
  <li>3 Monate Gemüsekorb</li>
  <li>Proportionaler Anteil am Jahrespreis</li>
  <li>Flexible Umstellung auf Jahresabo möglich</li>
</ul>
HTML;

    $abosShares = <<<HTML
<p>Du möchtest biocò unterstützen, ohne ein Gemüseabo zu beziehen? Das ist möglich.</p>
<p><strong>Vorteile:</strong></p>
<ul>
  <li>Unterstützung der Genossenschaft</li>
  <li>Vorrang auf der Warteliste für einen Gemüsekorb</li>
  <li>Mitspracherecht in der Genossenschaft</li>
</ul>
<p><strong>Kosten:</strong> CHF 250.- pro Anteilsschein</p>
HTML;

    $abosExtras = <<<HTML
<p>In Planung: Partnerangebote wie Eier, Brot, Tofu und weitere regionale Produkte.</p>
<p>Diese werden in Zukunft zusätzlich zum Gemüsekorb angeboten.</p>
HTML;

    $abosKennenlernen = <<<HTML
<p>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
<p><a href="/kontakt">Nimm Kontakt auf</a> · <a href="/standorte-depots">Zu uns finden</a></p>
HTML;

    addSection($abos, 'intro', 'Dein wöchentliches Gemüseabo', $abosIntro, 'rich_text');
    addSection($abos, 'pricing', 'Gemüse-Abos', $abosPricing, 'rich_text');
    addSection($abos, 'probe-abo', 'Probe-Abo', $abosProbe, 'split_media_text');
    addSection($abos, 'anteilsscheine', 'Anteilsscheine ohne Gemüsekorb', $abosShares, 'split_text_media');
    addSection($abos, 'zusatz', 'Zusatz-Abos', $abosExtras, 'rich_text');
    addSection($abos, 'kennenlernen', 'Möchtest du uns kennenlernen?', $abosKennenlernen, 'rich_text');

    $output[] = "  Added 6 sections to abos";
} else {
    $output[] = getStatusText($result, 'abos');
}

// 5. Aktuelles
$result = getOrCreatePage($contentParent, 'aktuelles', 'Aktuelles', 'page_content', $overwrite);
$aktuelles = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'aktuelles');

    $aktuellesIntro = <<<HTML
<p>Neuigkeiten, Schnuppertage und Events der biocò Gemüsegenossenschaft. Erlebe solidarische Landwirtschaft auf dem Geisshof.</p>
HTML;

    $schnuppertageIntro = <<<HTML
<p>Neugierig? Schau vorbei und mach mit. Erlebe an unseren Schnuppertagen, wie solidarische Landwirtschaft funktioniert, direkt auf dem Geisshof in Gebenstorf, umgeben von Natur, Wildpflanzen, auf unserem sonnigen Feld. Als Dank für deine Mithilfe erhältst du eine Tasche frisch geerntetes Demeter-Gemüse und ein kleines zVieri spendiert.</p>
HTML;

    $eventsIntro = <<<HTML
<p>Weitere Veranstaltungen und Anlässe der Gemüsegenossenschaft.</p>
HTML;

    $kennenlernenText = <<<HTML
<p>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
<p><a href="/kontakt">Nimm Kontakt auf</a> · <a href="/standorte-depots">Zu uns finden</a></p>
HTML;

    addSection($aktuelles, 'intro', 'Aktuelles', $aktuellesIntro, 'rich_text');
    addSection($aktuelles, 'schnuppertage', 'Schnuppertage', $schnuppertageIntro, 'component', 'default', 'schnuppertage');
    addSection($aktuelles, 'events', 'Nächste Events', $eventsIntro, 'component', 'default', 'events_feed');
    addSection($aktuelles, 'kennenlernen', 'Möchtest du uns kennenlernen?', $kennenlernenText, 'rich_text');

    $output[] = "  Added 4 sections to aktuelles (with schnuppertage + events_feed components)";
} else {
    $output[] = getStatusText($result, 'aktuelles');
}

// 6. Kontakt
$result = getOrCreatePage($contentParent, 'kontakt', 'Kontakt', 'page_content', $overwrite);
$kontakt = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'kontakt');

    $kontaktIntro = <<<HTML
<p>Hast du Fragen zu biocò? Wir freuen uns auf deine Nachricht. Wir melden uns in der Regel innerhalb von 2-3 Werktagen bei dir zurück.</p>
HTML;

    $kontaktMember = <<<HTML
<h3>Du bist bereits Mitglied?</h3>
<p>Als Mitglied hast du Zugang zum Intranet, wo du alle wichtigen Informationen, Dokumente und Tools findest.</p>
<p><a href="/intranet">Zum Intranet →</a></p>
HTML;

    $kontaktBeitritt = <<<HTML
<h3>Möchtest du Mitglied werden?</h3>
<p>Interessierst du dich für ein Gemüseabo oder möchtest du mehr über die Mitgliedschaft erfahren? Hier findest du alle Informationen zur Anmeldung.</p>
<p><a href="/bioco-werden">biocò werden →</a></p>
HTML;

    $kontaktFormText = <<<HTML
<h3>Allgemeine Anfragen</h3>
<p>Für alle anderen Fragen nutze bitte das Kontaktformular unten. Wir beantworten deine Anfrage gerne persönlich.</p>
HTML;

    addSection($kontakt, 'intro', 'Kontakt', $kontaktIntro, 'rich_text');
    addSection($kontakt, 'mitglied', 'Mitglied?', $kontaktMember, 'rich_text');
    addSection($kontakt, 'beitritt', 'Mitglied werden', $kontaktBeitritt, 'rich_text');
    addSection($kontakt, 'kontakt-formular', 'Kontaktformular', $kontaktFormText, 'component', 'default', 'contact_form');

    $output[] = "  Added 4 sections to kontakt (with contact form component)";
} else {
    $output[] = getStatusText($result, 'kontakt');
}

// 7. Standorte & Depots
$result = getOrCreatePage($contentParent, 'standorte-depots', 'Standorte & Depots', 'page_content', $overwrite);
$standorte = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'standorte-depots');

    $standorteIntro = <<<HTML
<p>Wir unterscheiden zwei Arten von Standorten:</p>
<ul>
  <li><strong>Geisshof:</strong> Hier bauen wir unser Gemüse an und arbeiten gemeinsam.</li>
  <li><strong>Depots:</strong> Hier stehen die Gemüsekörbe jeweils dienstags oder freitags zur Abholung bereit.</li>
</ul>
HTML;

    $geisshofText = <<<HTML
<p>Der Geisshof ist unser Bio Bauernhof in Gebenstorf im Aargau, wo wir Bio-Gemüse in Demeter-Qualität anbauen. Zentral gelegen zwischen Baden und Brugg kannst du hier auch direkt vorbeikommen und die solidarische Landwirtschaft kennenlernen.</p>
<h4>Anreise & Parken</h4>
<p><strong>Bitte komm wenn möglich mit dem Velo oder Bus.</strong></p>
<p>Falls du mit dem Auto kommst:</p>
<ul>
  <li>Bitte <strong>nicht auf den Hof hinauffahren</strong></li>
  <li>Parkiere unten an der Strasse</li>
  <li>Halte den <strong>Wendeplatz zwingend frei</strong> (für landwirtschaftliche Fahrzeuge)</li>
</ul>
<p><em>Danke für deine Rücksichtnahme.</em></p>
HTML;

    $depotsText = <<<HTML
<p>Hier findest du alle Depot-Standorte, an denen du dein Gemüse abholen kannst. Wähle das Depot, das für dich am besten gelegen ist.</p>
<h3>Depot Baden</h3>
<p><strong>Gemüse abholen Baden:</strong> Das Depot Baden befindet sich zentral in der Stadt und ist ideal für alle, die in Baden und Umgebung wohnen.</p>
<h3>Depot Brugg</h3>
<p><strong>Gemüse abholen Brugg:</strong> Unser Depot Brugg bietet eine bequeme Abholmöglichkeit für Mitglieder aus Brugg und der umliegenden Region.</p>
<h3>Depot Gebenstorf</h3>
<p>Direkt beim Geisshof könnt ihr euer Gemüse in Gebenstorf abholen – ideal für alle, die den Hof besuchen möchten.</p>
<h3>Depot Wettingen</h3>
<p>Das Depot Wettingen ermöglicht eine einfache Abholung für Mitglieder aus Wettingen und der näheren Umgebung.</p>
<p><strong>Abholzeiten:</strong> Dienstag und Freitag, ab 16:00 Uhr</p>
HTML;

    $kennenlernenText = <<<HTML
<p>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
<p><a href="/kontakt">Nimm Kontakt auf</a> · <a href="/standorte-depots">Zu uns finden</a></p>
HTML;

    addSection($standorte, 'intro', 'Unsere Standorte & Depots', $standorteIntro, 'rich_text');
    addSection($standorte, 'geisshof', 'Anfahrt zum Geisshof', $geisshofText, 'component', 'default', 'geisshof_map');
    addSection($standorte, 'depots', 'Depot-Standorte für Gemüseabholung', $depotsText, 'component', 'default', 'depot_map');
    addSection($standorte, 'kennenlernen', 'Möchtest du uns kennenlernen?', $kennenlernenText, 'rich_text');

    $output[] = "  Added 4 sections to standorte-depots (with map components)";
} else {
    $output[] = getStatusText($result, 'standorte-depots');
}

// 8. Wir (About Us)
$result = getOrCreatePage($contentParent, 'wir', 'Über uns', 'page_content', $overwrite);
$wir = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'wir');

    $wirIntro = <<<HTML
<p>Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof in Gebenstorf. Lerne unser Team, unsere Geschichte und die Werte kennen, die unsere <a href="/solawi">solidarische Landwirtschaft</a> prägen.</p>
HTML;

    $wirTeam = <<<HTML
<p>biocò ist eine Gemeinschaft von engagierten Menschen, die gemeinsam für frisches, regionales <a href="/gemuese">Demeter-Gemüse</a> sorgen.</p>
HTML;

    $alleMitglieder = <<<HTML
<p>Jede(r) Genossenschafter/in bringt sich ein, ob bei der Feldarbeit, in der Logistik oder bei Events.</p>
HTML;

    $betriebsgruppe = <<<HTML
<p>Die Betriebsgruppe koordiniert den Anbau, die Logistik und die Organisation der Genossenschaft.</p>
HTML;

    $hofTeam = <<<HTML
<p>Das Hof-Team kümmert sich um den täglichen Betrieb auf dem Geisshof.</p>
HTML;

    $geisshof = <<<HTML
<p>Wir bewirtschaften einen Bio Bauernhof in Baden, genauer gesagt den Geisshof in Gebenstorf im Aargau. Seit 2014 ist dieser Ort das Herzstück von biocò, wo wir Bio-Gemüse in Demeter-Qualität anbauen. Zentral gelegen zwischen Baden und Brugg versorgen wir die Region mit frischem, saisonalem Gemüse.</p>
HTML;

    $mission = <<<HTML
<p>Unsere Mission ist es, hochwertige, regionale Lebensmittel auf nachhaltige und gemeinschaftliche Weise zu produzieren.</p>
HTML;

    $solidaritaet = <<<HTML
<p>Wir teilen Arbeit und Ertrag. Solidarische Landwirtschaft bedeutet, dass Produzentinnen und Konsumentinnen zusammenarbeiten.</p>
HTML;

    $nachhaltigkeit = <<<HTML
<p>Wir arbeiten nach biologisch-dynamischen Prinzipien (Demeter) und fördern Biodiversität, Kreislaufwirtschaft und gesunde Böden.</p>
HTML;

    $gemeinschaft = <<<HTML
<p>biocò lebt von der Gemeinschaft. Jede(r) bringt sich ein, lernt voneinander und gestaltet die Genossenschaft aktiv mit.</p>
HTML;

    $regionalitaet = <<<HTML
<p>Unser Gemüse wächst direkt in der Region Baden-Brugg. Kurze Wege, frische Ernte, lokale Verbundenheit.</p>
HTML;

    $gotti = <<<HTML
<p>Neumitglieder werden von einem &quot;Gotti&quot; oder &quot;Götti&quot; (Paten) begleitet. Dieses System hilft neuen Mitgliedern, sich in der Genossenschaft zurechtzufinden.</p>
HTML;

    $geschichte = <<<HTML
<p>Die Gemüsegenossenschaft biocò wurde 2014 in Gebenstorf im Aargau gegründet. Aus einer kleinen Gruppe engagierter Menschen aus Baden, Brugg und der Region wurde eine lebendige Gemeinschaft, die solidarische Landwirtschaft lebt.</p>
<p>Gestartet wurde auf dem Geisshof in Gebenstorf, wo wir bis heute unser Gemüse anbauen. Über die Jahre haben wir die Anbaufläche erweitert, neue Standorte (Depots) für die Gemüseabholung geschaffen und die Strukturen der Genossenschaft weiterentwickelt.</p>
<p>Heute versorgen wir Mitglieder in der Region Baden-Brugg wöchentlich mit frischem, saisonalem Demeter-Gemüse.</p>
HTML;

    $timeline = <<<HTML
<p>Wichtige Meilensteine in der Geschichte von biocò.</p>
HTML;

    addSection($wir, 'intro', 'biocò: Die Gemüsegenossenschaft', $wirIntro, 'rich_text');
    addSection($wir, 'wir', 'Wir', $wirTeam, 'rich_text');
    addSection($wir, 'alle_mitglieder', 'Alle Mitglieder', $alleMitglieder, 'split_media_text');
    addSection($wir, 'betriebsgruppe', 'Betriebsgruppe (BG)', $betriebsgruppe, 'split_media_text');
    addSection($wir, 'hof_team', 'Hof-Team', $hofTeam, 'rich_text');
    addSection($wir, 'geisshof', 'Der Geisshof', $geisshof, 'rich_text');
    addSection($wir, 'mission', 'Mission & Leitbild', $mission, 'rich_text');
    addSection($wir, 'solidaritaet', 'Solidarität', $solidaritaet, 'rich_text');
    addSection($wir, 'nachhaltigkeit', 'Nachhaltigkeit', $nachhaltigkeit, 'rich_text');
    addSection($wir, 'gemeinschaft', 'Gemeinschaft', $gemeinschaft, 'rich_text');
    addSection($wir, 'regionalitaet', 'Regionalität', $regionalitaet, 'rich_text');
    addSection($wir, 'gotti', 'Gotti-System', $gotti, 'rich_text');
    addSection($wir, 'geschichte', 'Geschichte', $geschichte, 'rich_text');
    addSection($wir, 'timeline', 'Timeline', $timeline, 'rich_text');
    addSection($wir, 'timeline_2013', 'Gründung', 'Die Gründung von biocò fand am 15.11.2013 statt. Da war die Betriebsgruppe bereits sehr, sehr aktiv.', 'rich_text');
    addSection($wir, 'timeline_2014', 'Erste Gartensaison', 'War dann die erste Gartensaison und ab da gab es die ersten Depots.', 'rich_text');
    addSection($wir, 'timeline_2016', 'Packraum', 'Der Packraum wird erstellt und in Betrieb genommen. Ein wichtiger Schritt für die Genossenschaft.', 'rich_text');
    addSection($wir, 'timeline_2019-2023', 'Mitgliederwachstum', 'Weiteres Wachstum der Mitgliederzahl, Optimierung der Anbauplanung und Logistik.', 'rich_text');
    addSection($wir, 'timeline_2023', 'Laufenten', 'Wir haben zwei Pärchen Laufenten auf dem Hof, die uns bei der Schneckenjagd unterstützen.', 'rich_text');
    addSection($wir, 'timeline_2025', 'Neue Website', 'Launch der neuen Website mit modernem Design und verbesserter Benutzerführung.', 'rich_text');

    $output[] = "  Added 20 sections to wir";
} else {
    $output[] = getStatusText($result, 'wir');
}

// 9. biocò werden (Membership signup)
$result = getOrCreatePage($contentParent, 'bioco-werden', 'biocò werden', 'page_content', $overwrite);
$biocoWerden = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'bioco-werden');

    $biocoWerdenIntro = <<<HTML
<p>Wähle dein Gemüseabo und werde Teil unserer Gemüsegenossenschaft. Deine Auswahl wird automatisch ins Anmeldeformular übernommen.</p>
HTML;

    addSection($biocoWerden, 'intro', 'biocò werden', $biocoWerdenIntro, 'rich_text');
    addSection($biocoWerden, 'calculator', 'Abo-Rechner', '', 'component', 'default', 'pricing_calculator');

    $output[] = "  Added 2 sections to bioco-werden (with pricing calculator)";
} else {
    $output[] = getStatusText($result, 'bioco-werden');
}

// 10. Datenschutz (Privacy Policy)
$result = getOrCreatePage($contentParent, 'datenschutz', 'Datenschutzerklärung', 'page_content', $overwrite);
$datenschutz = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'datenschutz');

    $datenschutzIntro = <<<HTML
<h2>1. Datenschutz auf einen Blick</h2>
<h3>Allgemeine Hinweise</h3>
<p>Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen. Personenbezogene Daten sind alle Daten, mit denen Sie persönlich identifiziert werden können.</p>
HTML;

    $datenschutzVerantwortlich = <<<HTML
<h2>2. Verantwortliche Stelle</h2>
<p>Die verantwortliche Stelle für die Datenverarbeitung auf dieser Website ist:</p>
<p><strong>Gemüsegenossenschaft biocò</strong><br />
Geisshof<br />
5412 Gebenstorf<br />
Schweiz<br />
E-Mail: <a href="mailto:info@bioco.ch">info@bioco.ch</a></p>
HTML;

    $datenschutzErfassung = <<<HTML
<h2>3. Datenerfassung auf dieser Website</h2>
<h3>Kontaktformular</h3>
<p>Wenn Sie uns per Kontaktformular Anfragen zukommen lassen, werden Ihre Angaben aus dem Anfrageformular inklusive der von Ihnen dort angegebenen Kontaktdaten zwecks Bearbeitung der Anfrage und für den Fall von Anschlussfragen bei uns gespeichert. Diese Daten geben wir nicht ohne Ihre Einwilligung weiter.</p>
<h3>Double Opt-In (DOI)</h3>
<p>Bei allen Formularen verwenden wir ein Double Opt-In Verfahren. Das bedeutet, dass Sie nach der Anmeldung eine E-Mail erhalten, in der Sie um Bestätigung Ihrer Anmeldung gebeten werden. Erst nach Bestätigung werden Ihre Daten gespeichert.</p>
HTML;

    $datenschutzCookies = <<<HTML
<h2>4. Cookies</h2>
<p>Diese Website verwendet keine Cookies. Wir verwenden Matomo Analytics im cookieless Modus, um die Nutzung der Website zu analysieren, ohne personenbezogene Daten zu speichern.</p>
HTML;

    $datenschutzRechte = <<<HTML
<h2>5. Ihre Rechte</h2>
<p>Sie haben jederzeit das Recht, Auskunft über Ihre bei uns gespeicherten personenbezogenen Daten zu erhalten. Sie haben ausserdem ein Recht auf Berichtigung, Sperrung oder Löschung dieser Daten. Hierzu sowie zu weiteren Fragen zum Thema Datenschutz können Sie sich jederzeit unter der im Impressum angegebenen Adresse an uns wenden.</p>
HTML;

    $datenschutzSsl = <<<HTML
<h2>6. SSL-Verschlüsselung</h2>
<p>Diese Seite nutzt aus Sicherheitsgründen und zum Schutz der Übertragung vertraulicher Inhalte eine SSL-Verschlüsselung.</p>
HTML;

    addSection($datenschutz, 'intro', 'Datenschutzerklärung', $datenschutzIntro, 'rich_text');
    addSection($datenschutz, 'verantwortlich', 'Verantwortliche Stelle', $datenschutzVerantwortlich, 'rich_text');
    addSection($datenschutz, 'erfassung', 'Datenerfassung', $datenschutzErfassung, 'rich_text');
    addSection($datenschutz, 'cookies', 'Cookies', $datenschutzCookies, 'rich_text');
    addSection($datenschutz, 'rechte', 'Ihre Rechte', $datenschutzRechte, 'rich_text');
    addSection($datenschutz, 'ssl', 'SSL-Verschlüsselung', $datenschutzSsl, 'rich_text');

    $output[] = "  Added 6 sections to datenschutz";
} else {
    $output[] = getStatusText($result, 'datenschutz');
}

// 11. Impressum (Legal Notice)
$result = getOrCreatePage($contentParent, 'impressum', 'Impressum', 'page_content', $overwrite);
$impressum = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'impressum');

    $impressumKontakt = <<<HTML
<h2>Kontakt</h2>
<p><strong>Gemüsegenossenschaft biocò</strong><br />
Geisshof<br />
5412 Gebenstorf<br />
Schweiz</p>
<p><strong>E-Mail:</strong> <a href="mailto:info@bioco.ch">info@bioco.ch</a></p>
HTML;

    $impressumVertretung = <<<HTML
<h2>Vertretungsberechtigte Personen</h2>
<p>Betriebsgruppe der Gemüsegenossenschaft biocò</p>
HTML;

    $impressumHaftung = <<<HTML
<h2>Haftungsausschluss</h2>
<p>Der Inhalt dieser Website wurde mit grösster Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen.</p>
HTML;

    $impressumUrheberrecht = <<<HTML
<h2>Urheberrecht</h2>
<p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem schweizerischen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung ausserhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.</p>
HTML;

    addSection($impressum, 'kontakt', 'Impressum', $impressumKontakt, 'rich_text');
    addSection($impressum, 'vertretung', 'Vertretung', $impressumVertretung, 'rich_text');
    addSection($impressum, 'haftung', 'Haftung', $impressumHaftung, 'rich_text');
    addSection($impressum, 'urheberrecht', 'Urheberrecht', $impressumUrheberrecht, 'rich_text');

    $output[] = "  Added 4 sections to impressum";
} else {
    $output[] = getStatusText($result, 'impressum');
}

// 12. Statuten (Statutes)
$result = getOrCreatePage($contentParent, 'statuten', 'Statuten', 'page_content', $overwrite);
$statuten = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'statuten');

    $statutenIntro = <<<HTML
<p>Die Statuten der Gemüsegenossenschaft biocò regeln die Struktur und Organisation der Genossenschaft.</p>
<h3>Dokumente zum Download</h3>
<p><a href="/statuten/13-11-15_Statuten_bioco.pdf" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">Statuten (PDF)</a> <a href="/statuten/2212_Betriebsreglement.pdf" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">Reglement (PDF)</a></p>
HTML;

    $statutenUeber = <<<HTML
<h2>Über die Genossenschaft</h2>
<p>Die Gemüsegenossenschaft biocò ist eine Genossenschaft nach schweizerischem Recht. Sie wurde 2014 gegründet und betreibt solidarische Landwirtschaft (Community Supported Agriculture) auf dem Geisshof in Gebenstorf.</p>
<p>Die Genossenschaft basiert auf dem Prinzip der Solidarität: Mitglieder teilen sich Arbeit und Ertrag gemeinsam. Jede(r) Genossenschafter/in bringt sich aktiv ein und trägt zur Gemeinschaft bei.</p>
HTML;

    $statutenMitgliedschaft = <<<HTML
<h2>Mitgliedschaft</h2>
<p>Um Mitglied zu werden, benötigst du Anteilsscheine der Genossenschaft (CHF 250 pro Anteil). Die Anzahl der erforderlichen Anteile hängt vom gewählten Gemüsekorb ab:</p>
<ul>
<li>Halb Gemüsekorb: 1 Anteil</li>
<li>Standard Gemüsekorb: 2 Anteile</li>
<li>Doppel Gemüsekorb: 4 Anteile</li>
</ul>
<p><a href="/mitmachen" class="btn btn-primary">Jetzt Mitglied werden</a></p>
HTML;

    $statutenInfo = <<<HTML
<h2>Weitere Informationen</h2>
<p>Für weitere Fragen zu den Statuten oder der Genossenschaft, kontaktiere uns unter <a href="mailto:info@bioco.ch">info@bioco.ch</a> oder nutze das <a href="/kontakt">Kontaktformular</a>.</p>
HTML;

    addSection($statuten, 'intro', 'Statuten', $statutenIntro, 'rich_text');
    addSection($statuten, 'ueber', 'Über die Genossenschaft', $statutenUeber, 'rich_text');
    addSection($statuten, 'mitgliedschaft', 'Mitgliedschaft', $statutenMitgliedschaft, 'rich_text');
    addSection($statuten, 'info', 'Weitere Informationen', $statutenInfo, 'rich_text');

    $output[] = "  Added 4 sections to statuten";
} else {
    $output[] = getStatusText($result, 'statuten');
}

// 13. Newsletter
$result = getOrCreatePage($contentParent, 'newsletter', 'Newsletter', 'page_content', $overwrite);
$newsletter = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'newsletter');

    $newsletterIntro = <<<HTML
<p>Abonniere unseren Newsletter und bleibe über Neuigkeiten, Events und Aktuelles aus der Gemüsegenossenschaft informiert.</p>
HTML;

    addSection($newsletter, 'intro', 'Newsletter abonnieren', $newsletterIntro, 'rich_text');
    addSection($newsletter, 'form', 'Anmeldung', '', 'component', 'default', 'subscribe_form');

    $output[] = "  Added 2 sections to newsletter (with subscribe form)";
} else {
    $output[] = getStatusText($result, 'newsletter');
}

// 14. Warteliste (Waiting List)
$result = getOrCreatePage($contentParent, 'warteliste', 'Warteliste', 'page_content', $overwrite);
$warteliste = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'warteliste');

    $wartelisteIntro = <<<HTML
<p>Unsere Gemüsekörbe sind derzeit vollständig vergeben. Trage dich auf die Warteliste ein und wir melden uns, sobald ein Platz frei wird.</p>
HTML;

    addSection($warteliste, 'intro', 'Warteliste', $wartelisteIntro, 'rich_text');
    addSection($warteliste, 'form', 'Anmeldung', '', 'component', 'default', 'waiting_list_form');

    $output[] = "  Added 2 sections to warteliste (with waiting list form)";
} else {
    $output[] = getStatusText($result, 'warteliste');
}

// 15. Anmeldung (Membership Registration)
$result = getOrCreatePage($contentParent, 'anmeldung', 'Anmeldung', 'page_content', $overwrite);
$anmeldung = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'anmeldung');

    $anmeldungIntro = <<<HTML
<p>Fülle das Anmeldeformular aus, um Mitglied der Gemüsegenossenschaft biocò zu werden.</p>
HTML;

    addSection($anmeldung, 'intro', 'Anmeldung', $anmeldungIntro, 'rich_text');
    addSection($anmeldung, 'form', 'Mitgliedschaftsformular', '', 'component', 'default', 'membership_form');

    $output[] = "  Added 2 sections to anmeldung (with membership form)";
} else {
    $output[] = getStatusText($result, 'anmeldung');
}

// 16. Tag der offenen Tür (Open Day)
$result = getOrCreatePage($contentParent, 'tag-der-offenen-tuer', 'Tag der offenen Tür', 'page_content', $overwrite);
$openDay = $result['page'];
if (shouldPopulate($result)) {
    $output[] = getStatusText($result, 'tag-der-offenen-tuer');

    $openDayIntro = <<<HTML
<p>Besuche uns am Tag der offenen Tür auf dem Geisshof und erlebe solidarische Landwirtschaft hautnah.</p>
HTML;

    addSection($openDay, 'intro', 'Tag der offenen Tür', $openDayIntro, 'rich_text');
    addSection($openDay, 'form', 'Anmeldung', '', 'component', 'default', 'visit_day_form');

    $output[] = "  Added 2 sections to tag-der-offenen-tuer (with visit day form)";
} else {
    $output[] = getStatusText($result, 'tag-der-offenen-tuer');
}

$output[] = "";
$output[] = "=== Migration Complete ===";
$output[] = "";
$output[] = "Summary: 15 content pages processed (homepage managed separately)";
$output[] = "";
$output[] = "Events sync:";
$output[] = "- Schnuppertage component on: mitmachen, aktuelles";
$output[] = "- Events feed component on: aktuelles";
$output[] = "- Events are fetched dynamically from /api/events";
$output[] = "";
$output[] = "Next steps:";
$output[] = "1. Edit each page in ProcessWire to add images and fine-tune content";
$output[] = "2. Visit bioco.ch/{pagename} to verify pages render correctly";
$output[] = "3. Add images to sections via ProcessWire admin";
$output[] = "4. Delete this migration page when done";
$output[] = "";
$output[] = "To re-run with overwrite: add ?overwrite=1 to URL";

echo "<pre>" . implode("\n", $output) . "</pre>";
