<?php
/** Preview native price adoption from two explicitly captured saved page bodies. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/price-layouts.php';

function bioco_price_adoption_value(array $attrs, string $name) {
    return $attrs[$name]['innerContent']['desktop']['value'] ?? null;
}

function bioco_price_adoption_text(array $block): string {
    return (string) bioco_price_adoption_value($block['attrs'] ?? [], 'content');
}

function bioco_price_adoption_table_spec(string $html): array {
    if (hash('sha256', $html) !== 'fdf620154ef55a91c70652025eb175c7f735c6c43c65f72aabaf226a01461c41') {
        throw new RuntimeException('Saved price table changed; inspect its labels and amounts again.');
    }
    $dom = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    try { if (!$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html)) throw new RuntimeException('Invalid price table.'); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
    $query = new DOMXPath($dom);
    $headers = [];
    foreach ($query->query('//thead/tr/th') as $node) $headers[] = trim($node->textContent);
    if ($headers !== ['Gemüsekorb', 'Personen', 'Jahrespreis', 'Anteilsscheine Kosten', 'Mitarbeit pro Jahr']) {
        throw new RuntimeException('Saved table headings changed.');
    }
    $rows = [];
    foreach ($query->query('//tbody/tr') as $row) {
        $cells = $query->query('./td', $row);
        if ($cells->length !== 5) throw new RuntimeException('Unexpected price column count.');
        $label = trim($query->query('.//strong', $cells->item(0))->item(0)->textContent ?? '');
        $persons = trim($query->query('.//*[@aria-label]', $cells->item(1))->item(0)->getAttribute('aria-label') ?? '');
        $icons = $query->query('.//svg', $cells->item(1))->length;
        $work_suffix = trim($query->query('.//*[contains(@class,"cms-pricing-table-work-suffix")]', $cells->item(4))->item(0)->textContent ?? '');
        $rows[] = ['label' => $label, 'persons' => $persons, 'icons' => $icons,
            'price' => trim($cells->item(2)->textContent), 'share_cost' => trim($cells->item(3)->textContent),
            'work' => trim($cells->item(4)->textContent), 'work_suffix' => $work_suffix];
    }
    if (array_column($rows, 'label') !== ['Halb', 'Standard', 'Doppel']
        || array_column($rows, 'persons') !== ['1 Person', '2 Personen', '4 Personen']
        || array_column($rows, 'icons') !== [1, 2, 4]
        || array_column($rows, 'price') !== ['CHF 750.-', "CHF 1'280.-", "CHF 2'350.-"]
        || array_column($rows, 'share_cost') !== ['CHF 250.-', 'CHF 500.-', "CHF 1'000.-"]
        || array_column($rows, 'work') !== ['10 Arbeitseinsätzeà 2 Stunden', '20 Arbeitseinsätzeà 2 Stunden', '40 Arbeitseinsätzeà 2 Stunden']
        || array_column($rows, 'work_suffix') !== ['à 2 Stunden', 'à 2 Stunden', 'à 2 Stunden']) {
        throw new RuntimeException('Saved price rows disagree with validated values.');
    }
    return ['headers' => $headers, 'rows' => $rows, 'work_suffix' => 'à 2 Stunden'];
}

function bioco_price_adoption_table_layout(array $spec): array {
    $labels = bioco_price_layout_section([
        bioco_price_layout_row(array_map('bioco_utility_heading', $spec['headers'])),
    ], 'bioco-price-table-headings');
    $icon = bioco_import_divi_block('divi/image', ['image' => ['innerContent' => ['desktop' => ['value' => [
        'src' => bioco_price_variable('loop_bioco_price_persons_icon'),
        'alt' => bioco_price_variable('loop_bioco_price_persons_accessible'),
    ]]]]]);
    $modules = [
        bioco_utility_heading(bioco_price_variable('loop_bioco_price_table_label'), 'h3'),
        bioco_utility_text(bioco_price_variable('loop_bioco_price_shares_label')),
        $icon,
        bioco_utility_text(bioco_price_variable('loop_bioco_price_persons_range')),
    ];
    foreach (['price' => $spec['headers'][2], 'share_cost' => $spec['headers'][3], 'work' => $spec['headers'][4]] as $field => $label) {
        $modules[] = bioco_utility_text('<p>' . esc_html($label) . '</p>');
        if ($field !== 'work') $modules[] = bioco_utility_text('<p>CHF</p>');
        $modules[] = bioco_utility_text(bioco_price_variable('loop_bioco_price_' . ($field === 'price' || $field === 'share_cost' ? $field . '_table' : $field)));
        $modules[] = bioco_utility_text('<p>' . ($field === 'work' ? 'Arbeitseinsätze ' . esc_html($spec['work_suffix']) : '.-') . '</p>');
    }
    $loop = bioco_price_layout_loop($modules, 'bioco-price-table');
    $section = bioco_price_layout_section([$loop], 'bioco-price-table');
    $section['attrs']['module']['decoration']['layout'] = [
        'desktop' => ['value' => ['display' => 'grid', 'gridColumnWidths' => 'manual',
            'gridTemplateColumns' => 'repeat(3, minmax(0, 1fr))', 'columnGap' => '28px', 'rowGap' => '28px']],
        'tablet' => ['value' => ['gridTemplateColumns' => 'minmax(0, 1fr)']],
        'phone' => ['value' => ['gridTemplateColumns' => 'minmax(0, 1fr)']],
    ];
    return [$labels, $section];
}

function bioco_price_adoption_calculator_layout(array $attrs): array {
    $required = ['work_suffix', 'signup_url', 'shares_only_label', 'annual_contribution_label', 'work_label',
        'one_time_payment_label', 'additional_shares_label', 'cta_label', 'cta_note', 'recommended_label',
        'no_subscription_label', 'article_label', 'quantity_label', 'unit_price_label',
        'share_certificates_label', 'required_label', 'shares_only_info', 'without_basket_label',
        'additional_label', 'total_label'];
    $copy = [];
    foreach ($required as $key) {
        $value = bioco_price_adoption_value($attrs, $key);
        if (!is_string($value)) throw new RuntimeException('Saved calculator copy is missing: ' . $key);
        $copy[$key] = $value;
    }
    $tiers = bioco_price_adoption_value($attrs, 'tiers');
    if (!is_array($tiers) || array_column($tiers, 'price') !== [750, 1280, 2350]
        || array_column($tiers, 'shares') !== [1, 2, 4] || array_column($tiers, 'work') !== [10, 20, 40]
        || bioco_price_adoption_value($attrs, 'share_price') !== 250) {
        throw new RuntimeException('Saved calculator amounts changed.');
    }
    $choice = bioco_utility_button(bioco_price_variable('loop_bioco_price_name'), bioco_price_variable('loop_bioco_price_select_url'));
    $summary = [];
    foreach (['name' => $copy['article_label'], 'price' => $copy['annual_contribution_label'],
        'work' => $copy['work_label'], 'shares' => $copy['share_certificates_label'],
        'additional' => $copy['additional_shares_label'], 'share-cost' => $copy['one_time_payment_label'],
        'total' => $copy['total_label']] as $field => $label) {
        $summary[] = bioco_utility_text('<p>' . esc_html($label) . '</p>');
        $summary[] = bioco_price_layout_class(bioco_utility_text('0'), 'bioco-price-value-' . $field);
    }
    foreach (['shares_only_label', 'quantity_label', 'unit_price_label', 'required_label', 'without_basket_label',
        'additional_label', 'work_suffix', 'recommended_label'] as $field) {
        $summary[] = bioco_price_layout_class(bioco_utility_text('<p>' . esc_html($copy[$field]) . '</p>'), 'bioco-price-copy-' . $field);
    }
    $summary[] = bioco_utility_button('+ Anteilsschein', '#bioco-share-add');
    $summary[] = bioco_price_layout_class(bioco_utility_button('− Anteilsschein', '#bioco-share-remove'), 'bioco-price-remove');
    $summary[] = bioco_price_layout_class(bioco_utility_text($copy['shares_only_info']), 'bioco-price-no-selection');
    $summary[] = bioco_price_layout_class(bioco_utility_button($copy['cta_label'], $copy['signup_url']), 'bioco-price-signup');
    $summary[] = bioco_utility_text('<p>' . esc_html($copy['cta_note']) . '</p>');
    return bioco_price_layout_section([
        bioco_price_layout_loop([$choice], 'bioco-price-calculator'),
        bioco_price_layout_row([bioco_utility_button($copy['no_subscription_label'], '#bioco-price-kein')]),
        bioco_price_layout_row($summary),
    ], 'bioco-price-calculator');
}

function bioco_price_adoption_replace_table(array $blocks, array &$report): array {
    $found = 0;
    foreach ($blocks as &$root) {
        if (($root['blockName'] ?? '') !== 'divi/placeholder') continue;
        $children = [];
        foreach ($root['innerBlocks'] ?? [] as $section) {
            $row = $section['innerBlocks'][0] ?? null;
            $column = $row['innerBlocks'][0] ?? null;
            $modules = $column['innerBlocks'] ?? [];
            $matches = array_keys(array_filter($modules, static fn($module) => ($module['blockName'] ?? '') === 'divi/text'
                && str_contains(bioco_price_adoption_text($module), '<table>')));
            if (!$matches) { $children[] = $section; continue; }
            if (count($matches) !== 1 || count($modules) !== 3 || $matches[0] !== 1
                || count($section['innerBlocks']) !== 1 || count($row['innerBlocks']) !== 1) {
                throw new RuntimeException('Authored price section shape changed.');
            }
            $spec = bioco_price_adoption_table_spec(bioco_price_adoption_text($modules[1]));
            $before_row = bioco_import_divi_block('divi/row', $row['attrs'], [
                bioco_import_divi_block('divi/column', $column['attrs'], [$modules[0]]),
            ]);
            $before = bioco_import_divi_block('divi/section', $section['attrs'], [$before_row]);
            $after = bioco_price_layout_section([bioco_price_layout_row([$modules[2]])]);
            $children = array_merge($children, [$before], bioco_price_adoption_table_layout($spec), [$after]);
            $found++;
            $report['table'] = $spec;
        }
        $root['innerBlocks'] = $children;
        $root['innerContent'] = array_fill(0, count($children), null);
    }
    unset($root);
    if ($found !== 1) throw new RuntimeException('Expected one authored table module.');
    return $blocks;
}

function bioco_price_adoption_bind_narrative(array $blocks, array &$report): array {
    $expected = ['8f9236978f5a62195a72464d565dcc1d2ac038e73c9a6048d1e6938d6cf6a78d',
        'baa4b79a5759dc65465dc43783bc2d2f3fafedc210c673ac1024588a8a32cb46'];
    $seen = [];
    $walk = function (array &$nodes) use (&$walk, &$seen): void {
        foreach ($nodes as &$node) {
            if (($node['blockName'] ?? '') === 'divi/text') {
                $content = bioco_price_adoption_text($node);
                $hash = hash('sha256', $content);
                if (in_array($hash, ['8f9236978f5a62195a72464d565dcc1d2ac038e73c9a6048d1e6938d6cf6a78d',
                    'baa4b79a5759dc65465dc43783bc2d2f3fafedc210c673ac1024588a8a32cb46'], true)) {
                    if (substr_count($content, 'CHF 250.-') !== 1 || isset($seen[$hash])) throw new RuntimeException('Share-price narrative changed.');
                    $node['attrs']['content']['innerContent']['desktop']['value'] = str_replace('CHF 250.-',
                        'CHF ' . bioco_price_variable('bioco_price_share_price') . '.-', $content);
                    $seen[$hash] = true;
                }
            }
            if (!empty($node['innerBlocks'])) $walk($node['innerBlocks']);
        }
    };
    $walk($blocks);
    if (count($seen) !== 2 || array_diff($expected, array_keys($seen))) throw new RuntimeException('Expected both authored share-price mentions.');
    $report['narratives'] = array_keys($seen);
    return $blocks;
}

function bioco_price_adoption_replace_calculator(array $blocks, array &$report): array {
    $count = 0;
    foreach ($blocks as &$root) {
        if (($root['blockName'] ?? '') !== 'divi/placeholder') continue;
        foreach ($root['innerBlocks'] as &$section) {
            $row = $section['innerBlocks'][0] ?? null;
            $column = $row['innerBlocks'][0] ?? null;
            $modules = $column['innerBlocks'] ?? [];
            if (count($modules) !== 1 || ($modules[0]['blockName'] ?? '') !== 'bioco-divi/pricing-calculator') continue;
            if (count($section['innerBlocks']) !== 1 || count($row['innerBlocks']) !== 1) {
                throw new RuntimeException('Saved calculator section shape changed.');
            }
            $native = bioco_price_adoption_calculator_layout($modules[0]['attrs'] ?? []);
            $native['attrs']['module'] = array_replace_recursive($section['attrs']['module'] ?? [], $native['attrs']['module']);
            $section = $native;
            $count++;
            $report['calculator'] = 'native-price-loop-and-saved-copy';
        }
        unset($section);
    }
    unset($root);
    if ($count !== 1) throw new RuntimeException('Expected one saved calculator module.');
    return $blocks;
}

function bioco_price_adoption_content(string $content, string $kind): array {
    $blocks = parse_blocks($content); $report = [];
    if ($kind === 'table') {
        $blocks = bioco_price_adoption_replace_table($blocks, $report);
        $blocks = bioco_price_adoption_bind_narrative($blocks, $report);
    } elseif ($kind === 'calculator') {
        $blocks = bioco_price_adoption_replace_calculator($blocks, $report);
    } else throw new RuntimeException('Unknown price adoption kind.');
    return ['after_content' => serialize_blocks($blocks), 'report' => $report];
}

/** One snapshot and one guarded update. There is no multi-page apply. */
function bioco_price_adoption_plan(array $source, string $kind, string $site, string $expected_body_hash): array {
    if ($site !== home_url('/')) throw new RuntimeException('Snapshot belongs to another site.');
    $columns = ['ID', 'post_type', 'post_status', 'post_title', 'post_content', 'post_modified_gmt'];
    foreach ($columns as $column) if (!array_key_exists($column, $source)) throw new RuntimeException('Incomplete saved page snapshot.');
    $record = array_intersect_key($source, array_fill_keys($columns, true));
    if (!is_int($record['ID']) || $record['ID'] < 1 || $record['post_type'] !== 'page'
        || !is_string($record['post_content']) || !is_string($record['post_status'])
        || !is_string($record['post_title']) || !is_string($record['post_modified_gmt'])) {
        throw new RuntimeException('Invalid saved page snapshot.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/D', $expected_body_hash)
        || !hash_equals(hash('sha256', $record['post_content']), $expected_body_hash)) {
        throw new RuntimeException('Saved page body changed.');
    }
    $converted = bioco_price_adoption_content($record['post_content'], $kind);
    $plan = ['version' => 1, 'kind' => $kind, 'site' => $site, 'body_sha256' => $expected_body_hash,
        'before' => $record] + $converted;
    $plan['fingerprint'] = hash('sha256', wp_json_encode($plan));
    return $plan;
}

/** SQL is exported for one selected page; guarded restore requires exact applied bytes. */
function bioco_price_adoption_sql(array $plan, string $expected, bool $restore = false): string {
    global $wpdb;
    if (($plan['version'] ?? 0) !== 1 || !isset($plan['before']) || !is_array($plan['before'])) throw new RuntimeException('Invalid price adoption plan.');
    $verified = bioco_price_adoption_plan($plan['before'], $plan['kind'] ?? '', $plan['site'] ?? '', $plan['body_sha256'] ?? '');
    if ($verified !== $plan || !hash_equals($verified['fingerprint'], $expected)) throw new RuntimeException('Price adoption preview changed.');
    $guards = [];
    foreach ($plan['before'] as $column => $value) {
        if ($restore && $column === 'post_content') $value = $plan['after_content'];
        $guards[] = $wpdb->prepare("BINARY `$column` = BINARY %s", (string) $value);
    }
    $content = $restore ? $plan['before']['post_content'] : $plan['after_content'];
    $sql = $wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = %s WHERE ", $content) . implode(' AND ', $guards);
    return $wpdb->remove_placeholder_escape($sql);
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('bioco price-adoption', function ($args, $flags) {
        try {
            $path = $flags['snapshot'] ?? '';
            $kind = $flags['kind'] ?? '';
            $id = $flags['post'] ?? '';
            $body_hash = $flags['body-sha256'] ?? '';
            if (!is_string($path) || !is_file($path) || !is_readable($path)
                || !in_array($kind, ['table', 'calculator'], true)
                || !ctype_digit((string) $id) || (int) $id < 1 || !is_string($body_hash)) {
                throw new RuntimeException('Provide --snapshot=<private-json> --kind=table|calculator --post=<id> --body-sha256=<sha>.');
            }
            $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($rows) || !array_is_list($rows)) throw new RuntimeException('Expected saved page snapshot list.');
            $selected = array_values(array_filter($rows, static fn($row) => is_array($row) && ($row['ID'] ?? null) === (int) $id));
            if (count($selected) !== 1) throw new RuntimeException('Snapshot must contain one matching page.');
            $plan = bioco_price_adoption_plan($selected[0], $kind, home_url('/'), $body_hash);
            if (!isset($flags['expect'])) {
                WP_CLI::log(wp_json_encode($plan, JSON_UNESCAPED_UNICODE));
                return;
            }
            WP_CLI::log(bioco_price_adoption_sql($plan, (string) $flags['expect'], isset($flags['restore'])));
        } catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
    }, ['shortdesc' => 'Preview one saved price page or export its conditional apply/restore SQL. Never writes to WordPress.']);
}
