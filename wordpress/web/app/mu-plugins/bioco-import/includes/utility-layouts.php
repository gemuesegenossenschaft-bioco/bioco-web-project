<?php
/** Native Divi library starters for #212. No Theme Builder assignments or page writes. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/divi-blocks.php';

function bioco_utility_preset(string $name, string $type = 'module'): string {
    // Same stable IDs as Bioco_Divi_Foundation, including editor-customized presets.
    return 'bioco-' . substr(hash('sha256', $type . $name), 0, 16);
}

function bioco_utility_heading(string $text, string $level = 'h1'): array {
    return bioco_import_divi_block('divi/heading', [
        'modulePreset' => [bioco_utility_preset('BIOCO Display Heading')],
        'title' => [
            'innerContent' => ['desktop' => ['value' => $text]],
            'decoration' => ['font' => ['font' => ['desktop' => ['value' => ['headingLevel' => $level]]]]],
        ],
    ]);
}

function bioco_utility_text(string $html): array {
    return bioco_import_divi_block('divi/text', [
        'groupPreset' => ['content.decoration.bodyFont' => [
            'presetId' => [bioco_utility_preset('BIOCO Body Typography', 'group')],
            'groupName' => 'divi/font-body',
        ]],
        'content' => ['innerContent' => ['desktop' => ['value' => $html]]],
    ]);
}

function bioco_utility_button(string $label, string $url): array {
    return bioco_import_divi_block('divi/button', [
        'modulePreset' => [bioco_utility_preset('BIOCO Secondary Button')],
        'button' => ['innerContent' => ['desktop' => ['value' => [
            'text' => $label, 'linkUrl' => $url, 'linkTarget' => 'off',
        ]]]],
    ]);
}

function bioco_utility_section(array $modules): array {
    $column = bioco_import_divi_block('divi/column', [
        'module' => ['advanced' => ['type' => ['desktop' => ['value' => '4_4']]]],
    ], $modules);
    $row = bioco_import_divi_block('divi/row', [
        'modulePreset' => [bioco_utility_preset('BIOCO Content Row')],
        'module' => ['advanced' => ['columnStructure' => ['desktop' => ['value' => '4_4']]]],
    ], [$column]);
    return bioco_import_divi_block('divi/section', [
        'modulePreset' => [bioco_utility_preset('BIOCO Standard Section')],
    ], [$row]);
}

/** Search is a native Divi module; the shared serializer whitelist predates it. */
function bioco_utility_search_module(): array {
    return [
        'blockName' => 'divi/search',
        'attrs' => [
            'searchPlaceholder' => ['innerContent' => ['desktop' => ['value' => 'Suchbegriff eingeben']]],
            'search' => ['advanced' => ['showButton' => ['desktop' => ['value' => 'on']]]],
        ],
        'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => ["\n"],
    ];
}

function bioco_utility_layout_definitions(): array {
    $actions = [
        bioco_utility_button('Startseite', home_url('/')),
        bioco_utility_button('Aktuelles', home_url('/aktuelles/')),
        bioco_utility_button('Kontakt', home_url('/kontakt/')),
    ];
    $layouts = [];
    foreach ([
        'search' => ['BIOCO Suchergebnisse', 'Suchergebnisse', '<p>Deine Suche: [bioco_utility_query]</p>',
            [bioco_utility_text('[bioco_utility_results]'), bioco_utility_text('[bioco_utility_pagination previous="Zurück" next="Weiter"]')]],
        'search-empty' => ['BIOCO Suche ohne Treffer', 'Keine Treffer gefunden', '<p>Für deine Suche nach [bioco_utility_query] haben wir nichts gefunden. Versuche einen anderen Begriff oder schau unter Aktuelles nach.</p>', []],
        '404' => ['BIOCO Seite nicht gefunden', 'Seite nicht gefunden', '<p>Diese Seite gibt es hier nicht. Prüfe die Adresse oder suche nach dem gewünschten Inhalt.</p>', []],
    ] as $key => [$title, $heading, $body, $extra]) {
        $layouts[$key] = ['title' => $title, 'content' => serialize_blocks([
            bioco_utility_section(array_merge([
                bioco_utility_heading($heading), bioco_utility_text($body), bioco_utility_search_module(),
            ], $extra)),
            bioco_utility_section($actions),
        ])];
    }
    // Tokens are replaced in parsed attributes, never in serialized JSON/HTML.
    $layouts['search-item'] = ['title' => 'BIOCO Einzelner Suchtreffer', 'content' => serialize_blocks([
        bioco_utility_section([
            bioco_utility_heading('{{bioco_result_title}}', 'h2'),
            bioco_utility_text('<p>{{bioco_result_excerpt}}</p>'),
            bioco_utility_button('Seite öffnen', '{{bioco_result_url}}'),
        ]),
    ])];
    foreach ([
        'page-basic' => ['BIOCO Inhaltsseite', 'Seitentitel', '<p>Hier steht der Inhalt deiner Seite.</p>',
            'Weitere Informationen', '<p>Ergänze diesen Abschnitt und verschiebe ihn im Divi Builder an die passende Stelle.</p>'],
        'page-documents' => ['BIOCO Rechtliches und Dokumente', 'Dokumente', '<p>Beschreibe hier, für wen diese Dokumente bestimmt sind.</p>',
            'Dokumente zum Download', '<p><a href="/documents/dokument.pdf" target="_blank" rel="noopener noreferrer">Dokument (PDF)</a></p>'],
        'page-intranet' => ['BIOCO Intranet-Information', 'Intranet', '<p>Informationen und Dokumente für Mitglieder.</p>',
            'Zugang zum Intranet', '<p><a href="https://intranet.bioco.ch" target="_blank" rel="noopener noreferrer">Zum Intranet</a></p>'],
    ] as $key => [$title, $heading, $intro, $subheading, $body]) {
        $layouts[$key] = ['title' => $title, 'content' => serialize_blocks([
            bioco_utility_section([bioco_utility_heading($heading), bioco_utility_text($intro)]),
            bioco_utility_section([bioco_utility_heading($subheading, 'h2'), bioco_utility_text($body), $actions[2]]),
        ])];
    }
    return $layouts;
}

function bioco_utility_library_posts(string $key): array {
    return get_posts([
        'post_type' => 'et_pb_layout', 'name' => 'bioco-utility-' . $key,
        'post_status' => array_values(get_post_stati()), 'posts_per_page' => 2,
        'orderby' => 'ID', 'order' => 'ASC',
    ]);
}

function bioco_utility_fingerprint(string $title, string $content): string {
    return hash('sha256', $title . "\0" . $content);
}

/** Add only missing library layouts. Fingerprints distinguish edits from seed drift. */
function bioco_import_utility_layouts(bool $apply = false): array {
    if ($apply && !current_user_can('manage_options')) throw new RuntimeException('Use --user=<administrator>.');
    if (!post_type_exists('et_pb_layout') || !taxonomy_exists('layout_type')) {
        throw new RuntimeException('Divi must register its library and layout_type taxonomy first.');
    }
    $report = []; $missing = [];
    foreach (bioco_utility_layout_definitions() as $key => $definition) {
        $existing = bioco_utility_library_posts($key);
        if (count($existing) > 1) throw new RuntimeException('Duplicate utility layout: ' . $key);
        $fingerprint = bioco_utility_fingerprint($definition['title'], $definition['content']);
        if (!$existing) {
            $missing[$key] = $definition + ['fingerprint' => $fingerprint];
            $action = 'would-create';
        } else {
            $post = $existing[0];
            $current = bioco_utility_fingerprint($post->post_title, $post->post_content);
            $recorded = (string) get_post_meta($post->ID, '_bioco_utility_fingerprint', true);
            $action = $post->post_status !== 'publish' || $recorded === '' || !hash_equals($recorded, $current)
                ? 'preserve-editor' : (hash_equals($fingerprint, $current) ? 'unchanged' : 'preserve-version');
        }
        $report[$key] = ['key' => $key, 'action' => $action];
    }
    if (!$apply) return array_values($report);
    foreach ($missing as $key => $definition) {
        $id = wp_insert_post(wp_slash([
            'post_type' => 'et_pb_layout', 'post_status' => 'publish', 'post_name' => 'bioco-utility-' . $key,
            'post_title' => $definition['title'], 'post_content' => $definition['content'],
            'meta_input' => [
                '_et_pb_use_builder' => 'on', '_et_pb_built_for_post_type' => 'page',
                '_et_pb_page_layout' => 'et_full_width_page',
                '_bioco_utility_fingerprint' => $definition['fingerprint'],
            ],
        ]), true);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        if (!$id) throw new RuntimeException('Could not create utility layout: ' . $key);
        $terms = wp_set_object_terms($id, ['layout'], 'layout_type');
        if (is_wp_error($terms)) {
            // Roll back only the library post created by this attempt. A retry
            // must not mistake an unclassified layout for a completed import.
            wp_delete_post($id, true);
            throw new RuntimeException($terms->get_error_message());
        }
        $report[$key]['action'] = 'created';
    }
    return array_values($report);
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('bioco utility-layouts', function ($args, $flags) {
        try {
            WP_CLI::success(json_encode(bioco_import_utility_layouts(isset($flags['apply'])), JSON_UNESCAPED_UNICODE));
        } catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
    }, [
        'shortdesc' => 'Preview missing editable Divi utility/page layouts. Existing layouts are preserved.',
        'synopsis' => [['type' => 'flag', 'name' => 'apply', 'optional' => true, 'description' => 'Add missing library layouts.']],
        'when' => 'after_wp_load',
    ]);
}
