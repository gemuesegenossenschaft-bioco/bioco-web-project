<?php
/**
 * Migration: Populate all CMS text content from fallback-content.ts
 * Run via bootstrap: curl https://cms.bioco.ch/bootstrap-populate-content.php
 *
 * Populates sections for: Homepage, Mitmachen, Wir, Solawi, Gemuese, Aktuelles
 */

namespace ProcessWire;

// Only allow from CLI or localhost
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Forbidden: run from localhost or CLI only');
}

require_once __DIR__ . '/../site/init.php';

$pages = wire('pages');

function ensureSection($page, $sectionId, $data) {
    if (!$page->hasField('sections')) {
        echo "  SKIP: page '{$page->name}' has no sections field\n";
        return;
    }

    // Check if section already exists
    foreach ($page->sections as $section) {
        if ($section->section_id === $sectionId) {
            echo "  EXISTS: section '{$sectionId}' on page '{$page->name}'\n";
            return;
        }
    }

    $page->of(false);
    $section = $page->sections->getNew();
    $section->section_id = $sectionId;

    if (!empty($data['title'])) $section->section_title = $data['title'];
    if (!empty($data['text'])) $section->section_text = $data['text'];
    if (!empty($data['layout'])) $section->section_layout = $data['layout'];
    if (!empty($data['theme'])) $section->section_theme = $data['theme'];
    if (!empty($data['eyebrow'])) $section->section_eyebrow = $data['eyebrow'];
    if (!empty($data['component'])) $section->section_component = $data['component'];

    $section->save();
    $page->sections->add($section);
    $page->save();
    echo "  ADDED: section '{$sectionId}' to page '{$page->name}'\n";

    // Add buttons if specified
    if (!empty($data['buttons']) && $section->hasField('section_buttons')) {
        foreach ($data['buttons'] as $btn) {
            $button = $section->section_buttons->getNew();
            $button->button_text = $btn['text'];
            $button->button_url = $btn['href'];
            $button->button_variant = $btn['variant'] ?? 'primary';
            $button->save();
            $section->section_buttons->add($button);
        }
        $section->save();
    }
}

// ---- Homepage ----
echo "=== HOMEPAGE ===\n";
$home = $pages->get('/');
if ($home->id) {
    // Hero fields
    $home->of(false);
    if (!$home->hero_title) {
        $home->hero_title = 'Gemeinsam Gemüse anbauen und geniessen';
        echo "  SET hero_title\n";
    }
    if (!$home->hero_subtitle) {
        $home->hero_subtitle = 'Solidarische Landwirtschaft in der Region Baden-Brugg';
        echo "  SET hero_subtitle\n";
    }
    $home->save();

    ensureSection($home, 'willkommen', [
        'title' => 'Willkommen bei biocò',
        'text' => '<p>Bei der biocò Gemüsegenossenschaft teilen wir nicht nur die Ernte, sondern auch die Verantwortung und die Freude an der Arbeit. Das ist <a href="/solawi">solidarische Landwirtschaft</a> in der Region Baden: Produzentinnen und Konsumentinnen arbeiten Hand in Hand, gestalten gemeinsam den Anbau und erleben, wie aus einem Samen frisches Bio-Gemüse wird, das wöchentlich in den <a href="/standorte-depots">Depots in Baden, Brugg und Gebenstorf</a> abgeholt werden kann.</p>',
        'layout' => 'split_media_text',
        'buttons' => [['text' => 'Lerne uns kennen', 'href' => '/wir', 'variant' => 'primary']],
    ]);

    ensureSection($home, 'gemeinsam', [
        'title' => 'Gemeinsam, solidarisch, frisch',
        'text' => '<p>Seit 2014 bewirtschaften wir den <a href="/wir">Geisshof in Gebenstorf</a> nach biologisch-dynamischen Prinzipien und liefern <a href="/gemuese">Demeter-Gemüse</a> in höchster Bio-Qualität. Hier wächst Woche für Woche eine vielfältige Auswahl an saisonalem Gemüse aus <a href="/solawi">solidarischer Landwirtschaft</a>, das wir gemeinsam anbauen, pflegen und ernten. Jedes Mitglied bringt sich ein, ob auf dem Feld, in der Logistik oder bei der Organisation.</p>',
        'layout' => 'split_text_media',
        'buttons' => [['text' => 'Was gerade wächst', 'href' => '/gemuese', 'variant' => 'secondary']],
    ]);

    ensureSection($home, 'kennenlernen', [
        'title' => 'Möchtest du uns kennenlernen?',
        'text' => '<p>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>',
        'layout' => 'rich_text',
        'buttons' => [
            ['text' => 'Nimm Kontakt auf', 'href' => '/kontakt', 'variant' => 'primary'],
            ['text' => 'Zu uns finden', 'href' => '/standorte-depots', 'variant' => 'secondary'],
        ],
    ]);
}

// ---- Mitmachen ----
echo "\n=== MITMACHEN ===\n";
$mitmachen = $pages->get('/content/mitmachen/');
if ($mitmachen->id) {
    $mitmachen->of(false);
    if (!$mitmachen->body) {
        $mitmachen->body = '<p>Werde Teil unserer Gemüsegenossenschaft und erlebe <a href="/solawi">solidarische Landwirtschaft</a> hautnah. Hier erfährst du, wie du dich einbringen kannst und was Mitarbeit bei biocò bedeutet.</p>';
        $mitmachen->save();
        echo "  SET intro body\n";
    }

    ensureSection($mitmachen, 'mitarbeit', [
        'title' => 'Was es braucht, damit wir gesundes Gemüse haben',
        'text' => '<p>Jedes Mitglied bringt sich ein und unterstützt die Genossenschaft aktiv. Die Mitarbeit ist ein wichtiger Teil unserer <a href="/solawi">solidarischen Landwirtschaft</a>.</p>',
        'layout' => 'rich_text',
    ]);

    ensureSection($mitmachen, 'familien', [
        'title' => 'Familien & Kinder auf dem Geisshof',
        'text' => '<p>Familien und Kinder sind sehr regelmässige Helfer auf dem Geisshof. Die Einbindung von Kindern in den Prozess des Gemüseanbaus ist ein zentraler Bestandteil der biocò-Kultur.</p><p>Auf dem Geisshof erleben Kinder hautnah, wie Gemüse wächst, gepflegt wird und geerntet wird. Sie lernen spielerisch den Kreislauf der Natur kennen und entwickeln ein tiefes Verständnis für die Herkunft ihrer Nahrung.</p>',
        'layout' => 'split_media_text',
    ]);
}

// ---- Wir ----
echo "\n=== WIR ===\n";
$wir = $pages->get('/content/wir/');
if ($wir->id) {
    $wir->of(false);
    if (!$wir->body) {
        $wir->body = '<p>Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof in Gebenstorf. Lerne unser Team, unsere Geschichte und die Werte kennen, die unsere <a href="/solawi">solidarische Landwirtschaft</a> prägen.</p>';
        $wir->save();
        echo "  SET intro body\n";
    }

    ensureSection($wir, 'wir', [
        'title' => 'Wir',
        'text' => '<p>biocò ist eine Gemeinschaft von engagierten Menschen, die gemeinsam für frisches, regionales <a href="/gemuese">Demeter-Gemüse</a> sorgen.</p>',
        'layout' => 'rich_text',
    ]);

    ensureSection($wir, 'geisshof', [
        'title' => 'Der Geisshof',
        'text' => '<p>Wir bewirtschaften einen Bio Bauernhof in Baden – genauer gesagt den Geisshof in Gebenstorf im Aargau. Seit 2014 ist dieser Ort das Herzstück von biocò, wo wir Bio-Gemüse in Demeter-Qualität anbauen. Zentral gelegen zwischen Baden und Brugg versorgen wir die Region mit frischem, saisonalem Gemüse.</p>',
        'layout' => 'split_media_text',
    ]);

    ensureSection($wir, 'mission', [
        'title' => 'Mission & Leitbild',
        'text' => '',
        'layout' => 'rich_text',
    ]);

    ensureSection($wir, 'geschichte', [
        'title' => 'Geschichte',
        'text' => '<p>Die Gemüsegenossenschaft biocò wurde 2014 in Gebenstorf im Aargau gegründet. Aus einer kleinen Gruppe engagierter Menschen aus Baden, Brugg und der Region wurde eine lebendige Gemeinschaft, die solidarische Landwirtschaft lebt.</p><p>Gestartet wurde auf dem Geisshof in Gebenstorf, wo wir bis heute unser Gemüse anbauen. Über die Jahre haben wir die Anbaufläche erweitert, neue Standorte (Depots) für die Gemüseabholung geschaffen und die Strukturen der Genossenschaft weiterentwickelt.</p><p>Heute versorgen wir Mitglieder in der Region Baden-Brugg wöchentlich mit frischem, saisonalem Demeter-Gemüse.</p>',
        'layout' => 'rich_text',
    ]);
}

// ---- Solawi ----
echo "\n=== SOLAWI ===\n";
$solawi = $pages->get('/content/solawi/');
if ($solawi->id) {
    $solawi->of(false);
    if (!$solawi->body) {
        $solawi->body = '<p>Solidarische Landwirtschaft (Solawi) ist ein alternatives Wirtschaftsmodell, bei dem Produzent*innen und Konsument*innen eine direkte Partnerschaft eingehen. Bei biocò teilen wir nicht nur die Ernte, sondern auch die Verantwortung, das Risiko und die Freude an der gemeinsamen Arbeit.</p>';
        $solawi->save();
        echo "  SET intro body\n";
    }

    ensureSection($solawi, 'prinzipien', [
        'title' => 'Die Prinzipien der Solawi',
        'text' => '<ul><li><strong>Gemeinsame Verantwortung:</strong> Alle tragen zum Gelingen bei</li><li><strong>Faire Preise:</strong> Bauern erhalten einen gerechten Lohn</li><li><strong>Saisonal & regional:</strong> Frisches Gemüse aus der Region</li><li><strong>Transparenz:</strong> Jeder weiss, woher das Essen kommt</li></ul>',
        'layout' => 'rich_text',
    ]);
}

// ---- Gemuese ----
echo "\n=== GEMUESE ===\n";
$gemuese = $pages->get('/content/gemuese/');
if ($gemuese->id) {
    $gemuese->of(false);
    if (!$gemuese->body) {
        $gemuese->body = '<p>Auf dem Geisshof in Gebenstorf bauen wir nach biologisch-dynamischen Prinzipien (Demeter) eine grosse Vielfalt an saisonalem Gemüse an. Jede Woche gibt es eine frische Auswahl, die unsere Mitglieder in den Depots abholen.</p>';
        $gemuese->save();
        echo "  SET intro body\n";
    }
}

// ---- Aktuelles ----
echo "\n=== AKTUELLES ===\n";
$aktuelles = $pages->get('/content/aktuelles/');
if ($aktuelles->id) {
    $aktuelles->of(false);
    if (!$aktuelles->body) {
        $aktuelles->body = '<p>Neuigkeiten, Veranstaltungen und Updates aus der biocò Gemüsegenossenschaft.</p>';
        $aktuelles->save();
        echo "  SET intro body\n";
    }
}

echo "\nDone.\n";
