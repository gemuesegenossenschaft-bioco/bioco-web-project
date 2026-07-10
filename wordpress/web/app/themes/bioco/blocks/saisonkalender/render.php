<?php
/**
 * Saisonkalender block render template.
 * ACF renderTemplate scope: $block, $content, $is_preview, $post_id, $context.
 * The data model shifts from Saisonkalender.tsx's tab-per-month vegetable
 * lists (SEASONAL_DATA) to a per-vegetable months multi-select, which renders
 * naturally as a static Gemüse × Monate grid — fully SSR, no tab-switching
 * JS needed (unlike the Next.js component's active-month tab state).
 */

if (!defined('ABSPATH')) exit;

$months = [1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez'];

$vegetables = get_field('vegetables');

// Small curated subset derived from Saisonkalender.tsx's SEASONAL_DATA,
// documented here so a fresh install isn't blank. Not the full ~50-vegetable
// reverse index — editors complete the list via the repeater above.
if (empty($vegetables)) {
    $vegetables = [
        ['name' => 'Rüebli', 'months' => [1, 2, 3, 4, 7, 8, 9, 10, 11, 12]],
        ['name' => 'Zwiebeln', 'months' => [1, 2, 3, 4, 8, 9, 10, 11, 12]],
        ['name' => 'Kartoffeln', 'months' => [1, 2, 3, 4, 5, 9, 10, 11, 12]],
        ['name' => 'Salate', 'months' => [3, 4, 5, 6, 7, 8, 9, 10, 11, 12]],
        ['name' => 'Lauch', 'months' => [1, 2, 3, 4, 9, 10, 11, 12]],
        ['name' => 'Kürbis', 'months' => [1, 10, 11, 12]],
    ];
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'saisonkalender';
$class_name = 'cms-section cms-saisonkalender';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if (!empty($vegetables)) : ?>
        <div class="cms-saisonkalender-grid">
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Gemüse', 'bioco'); ?></th>
                        <?php foreach ($months as $label) : ?>
                            <th><?php echo esc_html($label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vegetables as $vegetable) :
                        $name = $vegetable['name'] ?? '';
                        if (!$name) continue;
                        $active_months = array_map('intval', $vegetable['months'] ?? []);
                    ?>
                        <tr>
                            <td><?php echo esc_html($name); ?></td>
                            <?php foreach (array_keys($months) as $month_number) : ?>
                                <td class="<?php echo in_array($month_number, $active_months, true) ? 'cms-saisonkalender-active' : ''; ?>">
                                    <?php if (in_array($month_number, $active_months, true)) : ?>
                                        <span class="cms-saisonkalender-dot" aria-hidden="true"></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <p style="color: var(--wp--preset--color--bioco-text-muted);">Noch kein Saisonkalender hinterlegt.</p>
    <?php endif; ?>
</section>
