<?php
/**
 * Depot-map block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors DepotMap.tsx: non-interactive Leaflet map + address list of the
 * nine bioco depots. The full DepotLocation shape (day/contact/website/
 * notes/hideAddress) is collapsed into the simplified (name, lat, lng,
 * description) repeater from issue #96; the defaults below carry that
 * context forward as free text so a fresh install isn't blank.
 * Leaflet itself is not vendored yet (deferred to W11) — view.js only draws
 * a live map when window.L is already present, otherwise the address list
 * below is the whole UI (progressive enhancement).
 */

if (!defined('ABSPATH')) exit;

$intro = get_field('intro');
$locations = get_field('locations');

if (empty($locations)) {
    $locations = [
        ['name' => 'Depot Chrättli', 'lat' => 47.4725, 'lng' => 8.3030, 'description' => "Allmendstrasse 16, 5400 Baden · Dienstag\nKontakt: Corona Banky · https://www.xn--chrttli-7wa.ch/\nDas Depot befindet sich unter der Rampe rechts neben dem Quartierladen."],
        ['name' => 'Depot Ohne', 'lat' => 47.4735, 'lng' => 8.3075, 'description' => "Stadtturmstrasse 15, 5400 Baden · Dienstag\nKontakt: Tobias Kloter · https://www.ohne.ch/\nDie Körbe stehen unter den Tischen im hinteren Bereich."],
        ['name' => 'Depot Anixis', 'lat' => 47.4740, 'lng' => 8.3085, 'description' => "Oberstadtstrasse 10, Galerie Anixis, 5400 Baden · Dienstag\nKontakt: Josef Lindiridi · https://anixis.ch/\nHinter der Barriere auf dem Materiallager."],
        ['name' => 'Casa Flora', 'lat' => 47.4880, 'lng' => 8.2180, 'description' => "Zurzacherstrasse 171, 5200 Brugg · Dienstag\nKontakt: David Müller\nIn der Nische beim hinteren Eingang des Blumengeschäfts (Zufahrt via Hauptstrasse)."],
        ['name' => 'Depot Geisshof', 'lat' => 47.4741684, 'lng' => 8.2456318, 'description' => "Geisshof, Gebenstorf · Freitag\nKontakt: Matthias Müller\nDirekt auf dem Hof."],
        ['name' => 'Depot Kupperhaus', 'lat' => 47.4843986, 'lng' => 8.2069493, 'description' => "Schulthess-Allee 4, 5200 Brugg · Freitag\nKontakt: Brigitte Perren Henneck\nUnten an der Rampe (Zufahrt rückwärts neben dem Kupperhaus)."],
        ['name' => 'Depot Ennetbaden', 'lat' => 47.4802623, 'lng' => 8.3191206, 'description' => "Geissbergstrasse 17, 5408 Ennetbaden · Freitag\nKontakt: Nils und Armelle George\nBeim Wohnhaus."],
        ['name' => 'Depot Lemonia', 'lat' => 47.4705, 'lng' => 8.3164, 'description' => "Schartenstrasse 28, 5430 Wettingen · Freitag\nKontakt: Martin Gruchow · http://lemonia.ch/\nHinter dem Haus unter dem Tisch."],
        ['name' => 'Depot Lägernstrasse', 'lat' => 47.4611864, 'lng' => 8.3168081, 'description' => "Lägernstrasse 6, 5430 Wettingen · Freitag\nKontakt: Helen Matthäus\nBeim Wohnhaus."],
    ];
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'depot-map';
$class_name = 'cms-section cms-depot-map';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if ($intro) : ?>
        <div class="cms-map-intro"><p><?php echo esc_html($intro); ?></p></div>
    <?php endif; ?>
    <?php bioco_render_map_block($locations, 47.4734, 8.3089, 12); ?>
</section>
