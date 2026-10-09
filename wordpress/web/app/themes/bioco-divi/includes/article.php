<?php
/** Request-only native Divi layout; never replaces the stored editor body. */
if (!defined('ABSPATH')) exit;

require_once dirname(__DIR__, 3) . '/mu-plugins/bioco-import/includes/divi-blocks.php';

function bioco_divi_render_article(): string {
    $post_id = get_the_ID();
    $is_event = get_post_type($post_id) === 'event';
    $class_attrs = static function (string $class): array {
        return ['module' => ['advanced' => ['htmlAttributes' => ['desktop' => ['value' => ['class' => $class]]]]]];
    };
    $text = static function (string $html, string $class) use ($class_attrs): array {
        return bioco_import_divi_block('divi/text', $class_attrs($class) + [
            'content' => ['innerContent' => ['desktop' => ['value' => $html]]],
        ]);
    };
    $modules = [bioco_import_divi_block('divi/post-title', $class_attrs('bioco-article-title') + [
        'title' => [
            'advanced' => ['showTitle' => ['desktop' => ['value' => 'on']]],
            'decoration' => ['font' => ['font' => ['desktop' => ['value' => ['headingLevel' => 'h1']]]]],
        ],
        'meta' => ['advanced' => ['showMeta' => ['desktop' => ['value' => 'off']]]],
        'image' => ['advanced' => ['enabled' => ['desktop' => ['value' => 'off']]]],
    ])];
    $date = $is_event ? bioco_event_date_parts($post_id) : [
        'dateLabel' => get_the_date('d.m.Y', $post_id), 'timeLabel' => '',
    ];
    if ($date['dateLabel'] !== '') {
        $modules[] = $text('<p>' . esc_html(trim($date['dateLabel'] . ' ' . $date['timeLabel'])) . '</p>', 'bioco-article-date');
    }
    // card_image can differ from the featured image. Posts use their thumbnail.
    $image = $is_event ? bioco_event_card_image($post_id) : null;
    if (!$is_event && has_post_thumbnail($post_id)) {
        $image = ['url' => get_the_post_thumbnail_url($post_id, 'large'),
            'alt' => get_post_meta(get_post_thumbnail_id($post_id), '_wp_attachment_image_alt', true)];
    }
    if ($image && !empty($image['url'])) {
        $modules[] = bioco_import_divi_block('divi/image', $class_attrs('bioco-article-image') + [
            'image' => ['innerContent' => ['desktop' => ['value' => [
                'src' => esc_url_raw($image['url']), 'alt' => (string) $image['alt'],
            ]]]],
        ]);
    }
    if (trim((string) get_post_field('post_content', $post_id)) !== '') {
        ob_start();
        the_content();
        $body = ob_get_clean();
    } else {
        $body = $is_event ? wp_kses_post((string) get_field('event_summary', $post_id)) : '';
    }
    $modules[] = $text($body, 'bioco-article-body');
    $modules[] = bioco_import_divi_block('divi/button', $class_attrs('bioco-article-back') + [
        'button' => ['innerContent' => ['desktop' => ['value' => [
            'text' => 'Zurück zu Aktuelles', 'linkUrl' => home_url('/aktuelles/'), 'linkTarget' => 'off',
        ]]]],
    ]);
    $column = bioco_import_divi_block('divi/column', $class_attrs('bioco-article-column'), $modules);
    $column['attrs']['module']['advanced']['type'] = ['desktop' => ['value' => '4_4']];
    $row = bioco_import_divi_block('divi/row', $class_attrs('bioco-article-row'), [$column]);
    $row['attrs']['module']['advanced']['columnStructure'] = ['desktop' => ['value' => '4_4']];
    $section = bioco_import_divi_block('divi/section', $class_attrs('bioco-article-section'), [$row]);
    // Use the same frontend content filters as native Divi pages. CLI module
    // registration is not evidence of frontend availability.
    return apply_filters('the_content', bioco_import_serialize_divi_blocks([$section]));
}
