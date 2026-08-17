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

$vegetables = bioco_field('vegetables');
$vegetable_column_label = bioco_field('vegetable_column_label');
$month_columns = bioco_field('month_columns');
$empty_message = bioco_field('empty_message');

$months = [];
foreach ((array) $month_columns as $month_column) {
    $month_number = (int) ($month_column['number'] ?? 0);
    $month_label = $month_column['label'] ?? '';
    if ($month_number < 1 || $month_number > 12 || !$month_label) continue;
    $months[$month_number] = $month_label;
}

$anchor = !empty($block['anchor']) ? $block['anchor'] : 'saisonkalender';
$class_name = 'cms-section cms-saisonkalender';
if (!empty($block['className'])) {
    $class_name .= ' ' . $block['className'];
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
    <?php if (!empty($vegetables) && !empty($months)) : ?>
        <div class="cms-saisonkalender-grid">
            <table>
                <thead>
                    <tr>
                        <th><?php echo esc_html($vegetable_column_label); ?></th>
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
<?php if ($empty_message) : ?>        <p style="color: var(--wp--preset--color--bioco-text-muted);"><?php echo esc_html($empty_message); ?></p><?php endif; ?>
    <?php endif; ?>
</section>
