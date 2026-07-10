<?php
/**
 * Pricing table block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * Mirrors SectionRenderer's PricingTableBlock: a tier repeater (name, shares,
 * persons, price, sharecost, work) plus a shared work_suffix, rendered as a
 * table with SSR person-count icons (matches components/PersonIcons.tsx:
 * 4 persons wrap into two rows of two, otherwise a single row).
 */

if (!defined('ABSPATH')) exit;

$eyebrow = get_field('eyebrow');
$title = get_field('title');
$text = get_field('text');
$buttons = get_field('buttons');
$container_width = get_field('container_width') ?: 'xl';
$work_suffix = get_field('work_suffix');
$tiers = get_field('tiers');

if ($is_preview && !$title && !$text && empty($tiers)) {
    $title = __('Titel eingeben …', 'bioco');
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'pricing-table';

$class_name = 'cms-section cms-pricing-table';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}

$heading_already_in_text = bioco_text_has_heading_html($text);

function bioco_render_person_icons($count) {
    $count = max(0, (int) $count);
    if (!$count) return;
    $icons_per_row = $count === 4 ? 2 : $count;
    $rows = $count === 4 ? 2 : 1;
    echo '<div class="person-icons">';
    for ($row = 0; $row < $rows; $row++) {
        echo '<div class="person-icons-row">';
        for ($col = 0; $col < $icons_per_row; $col++) {
            $icon_number = $row * $icons_per_row + $col;
            if ($icon_number >= $count) continue;
            ?>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="person-icon">
                <circle cx="12" cy="8" r="4" fill="var(--wp--preset--color--bioco-green)" stroke="var(--wp--preset--color--bioco-green-dark)" stroke-width="1"/>
                <path d="M6 20C6 16 8 14 12 14C16 14 18 16 18 20" stroke="var(--wp--preset--color--bioco-green)" stroke-width="2" stroke-linecap="round" fill="none"/>
            </svg>
            <?php
        }
        echo '</div>';
    }
    echo '</div>';
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>" data-container="<?php echo esc_attr($container_width); ?>">
    <?php if ($eyebrow) : ?>
        <p class="cms-section-eyebrow"><?php echo esc_html($eyebrow); ?></p>
    <?php endif; ?>
    <?php if ($title && !$heading_already_in_text) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if ($text) : ?>
        <div class="cms-section-text"><?php echo bioco_kses_rich_text($text); ?></div>
    <?php endif; ?>
    <?php if (!empty($tiers)) : ?>
        <div class="pricing-table">
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Gemüsekorb', 'bioco'); ?></th>
                        <th><?php esc_html_e('Personen', 'bioco'); ?></th>
                        <th><?php esc_html_e('Jahrespreis', 'bioco'); ?></th>
                        <th><?php esc_html_e('Anteilsscheine Kosten', 'bioco'); ?></th>
                        <th><?php esc_html_e('Mitarbeit pro Jahr', 'bioco'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tiers as $tier) :
                        $tier_name = $tier['name'] ?? '';
                        $tier_shares = $tier['shares'] ?? '';
                        $tier_persons = (int) ($tier['persons'] ?? 0);
                        $tier_price = $tier['price'] ?? '';
                        $tier_sharecost = $tier['sharecost'] ?? '';
                        $tier_work = $tier['work'] ?? '';
                        if (!$tier_name) continue;
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($tier_name); ?></strong>
                                <?php if ($tier_shares) : ?>
                                    <div class="cms-pricing-table-shares"><?php echo esc_html($tier_shares); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php bioco_render_person_icons($tier_persons); ?>
                            </td>
                            <td><?php echo esc_html($tier_price); ?></td>
                            <td><?php echo esc_html($tier_sharecost); ?></td>
                            <td>
                                <?php echo esc_html($tier_work); ?>
                                <?php if ($work_suffix) : ?>
                                    <br />
                                    <span class="cms-pricing-table-work-suffix"><?php echo esc_html($work_suffix); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php if (!empty($buttons)) : ?>
        <div class="cms-section-actions">
            <?php foreach ($buttons as $button) :
                $button_text = $button['text'] ?? '';
                $button_href = $button['href'] ?? '';
                $button_variant = $button['variant'] ?? 'primary';
                if (!$button_text || !$button_href) continue;
            ?>
                <a href="<?php echo esc_url($button_href); ?>" class="btn btn-<?php echo esc_attr($button_variant); ?>"><?php echo esc_html($button_text); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
