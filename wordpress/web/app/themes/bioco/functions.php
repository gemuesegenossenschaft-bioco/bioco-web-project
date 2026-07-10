<?php
/**
 * bioco block theme bootstrap.
 * ACF Blocks + Local JSON are registered here as slices W4–W9 land.
 */

if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    load_theme_textdomain('bioco', get_template_directory() . '/languages');
});

// Front-end + editor styles: enqueue any bespoke component CSS layered on theme.json tokens.
add_action('wp_enqueue_scripts', function () {
    $rel = '/assets/app.css';
    $path = get_template_directory() . $rel;
    if (file_exists($path)) {
        wp_enqueue_style('bioco-app', get_template_directory_uri() . $rel, [], (string) filemtime($path));
    }
});

/**
 * ACF Local JSON — field-group definitions live in git (acf-json/), not the DB.
 * Save point + load point so every block's fields are version-controlled (W4+).
 */
add_filter('acf/settings/save_json', fn() => get_template_directory() . '/acf-json');
add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = get_template_directory() . '/acf-json';
    return $paths;
});

/**
 * Custom "bioco" block category — groups all bespoke section/ACF blocks
 * together in the inserter instead of falling under "Theme"/"Formatting".
 */
add_filter('block_categories_all', function (array $categories) {
    return array_merge(
        [
            [
                'slug' => 'bioco',
                'title' => __('biocò', 'bioco'),
                'icon' => 'admin-site-alt3',
            ],
        ],
        $categories
    );
});

/**
 * Section blocks: every block.json found one level under blocks/ is
 * auto-registered here (W4 tracer bullet onwards).
 */
add_action('init', function () {
    $blocks_dir = get_template_directory() . '/blocks';
    if (!is_dir($blocks_dir)) return;
    foreach (glob($blocks_dir . '/*/block.json') as $block_json) {
        register_block_type(dirname($block_json));
    }
});

/**
 * Shared block render helpers (W5 layout blocks, issue #92).
 * Kept in one place so every block's render.php sanitizes and
 * detects headings identically.
 */

// Mirrors the Next.js SectionRenderer hasHeadingHtml() check: suppress the
// separate block title when the WYSIWYG text already contains its own h1-h6.
function bioco_text_has_heading_html($html) {
    return (bool) preg_match('/<h[1-6]\b[^>]*>/i', (string) $html);
}

// wp_kses_post allowlist trimmed to the tags actually used across bioco's
// rich text fields (matches the sanitization already applied on the current site).
function bioco_kses_rich_text($html) {
    $allowed = [
        'a' => ['href' => true, 'target' => true, 'rel' => true, 'class' => true],
        'strong' => [],
        'em' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'details' => [],
        'summary' => [],
        'br' => [],
        'p' => ['class' => true, 'style' => true],
    ];
    return wp_kses((string) $html, $allowed);
}

/**
 * Event CPT helpers (W8 content model, issue #95). Shared by bioco/events-feed
 * and bioco/schnuppertage so both blocks split/sort Veranstaltung posts the
 * same way instead of duplicating WP_Query args.
 */

// $status: 'upcoming' | 'past'. $event_type: null (any) | 'general' | 'schnuppertag'.
// Sorts by event_date: soonest-first for upcoming, most-recent-first for past
// (mirrors api-events.php's event_status split; ordering is a WP-native choice
// since the ProcessWire reference sorts once by event_start ascending only).
function bioco_query_events($status, $limit, $event_type = null) {
    $meta_query = ['relation' => 'AND'];

    if ($status === 'past') {
        $meta_query[] = ['key' => 'event_status', 'value' => 'past', 'compare' => '='];
    } else {
        $meta_query[] = [
            'relation' => 'OR',
            ['key' => 'event_status', 'value' => 'past', 'compare' => '!='],
            ['key' => 'event_status', 'compare' => 'NOT EXISTS'],
        ];
    }

    if ($event_type) {
        $meta_query[] = ['key' => 'event_type', 'value' => $event_type, 'compare' => '='];
    }

    return new WP_Query([
        'post_type' => 'event',
        'post_status' => 'publish',
        'posts_per_page' => (int) $limit,
        'meta_key' => 'event_date',
        'orderby' => 'meta_value',
        'order' => $status === 'past' ? 'DESC' : 'ASC',
        'meta_query' => $meta_query,
    ]);
}

// Returns [url, alt] using the event's card_image field, falling back to the
// featured image, or null if neither is set.
function bioco_event_card_image($post_id) {
    $card_image = get_field('card_image', $post_id);
    if (is_array($card_image) && !empty($card_image['url'])) {
        return ['url' => $card_image['url'], 'alt' => $card_image['alt'] ?: get_the_title($post_id)];
    }
    $thumb_id = get_post_thumbnail_id($post_id);
    if ($thumb_id) {
        $url = wp_get_attachment_image_url($thumb_id, 'medium_large');
        if ($url) {
            $alt = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
            return ['url' => $url, 'alt' => $alt ?: get_the_title($post_id)];
        }
    }
    return null;
}

// Formats the event_date meta (stored 'Y-m-d H:i:s') as "d.m.Y" / "H:i Uhr".
function bioco_event_date_parts($post_id) {
    $raw = get_field('event_date', $post_id);
    $ts = $raw ? strtotime($raw) : false;
    if (!$ts) return ['dateLabel' => '', 'timeLabel' => ''];
    return [
        'dateLabel' => date_i18n('d.m.Y', $ts),
        'timeLabel' => date_i18n('H:i', $ts) . ' Uhr',
    ];
}

// Renders a .events-list of .event-item cards for a WP_Query of 'event' posts.
// Shared by bioco/events-feed and bioco/schnuppertage render.php, which can
// both appear more than once per page, so this must not be a render.php-local
// function (that would fatal on the second include with "Cannot redeclare").
function bioco_render_events_list($query) {
    echo '<div class="events-list">';
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $image = bioco_event_card_image($post_id);
            $date_parts = bioco_event_date_parts($post_id);
            $summary = get_field('event_summary', $post_id);
            $item_class = 'event-item' . ($image ? ' event-item-with-image' : '');
            ?>
            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="<?php echo esc_attr($item_class); ?>">
                <?php if ($image) : ?>
                    <div class="event-card-image">
                        <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy" />
                    </div>
                <?php endif; ?>
                <div class="event-card-content">
                    <h3><?php echo esc_html(get_the_title($post_id)); ?></h3>
                    <?php if ($date_parts['dateLabel']) : ?>
                        <p><?php echo esc_html($date_parts['dateLabel']); ?><?php if ($date_parts['timeLabel']) : ?> · <?php echo esc_html($date_parts['timeLabel']); ?><?php endif; ?></p>
                    <?php endif; ?>
                    <?php if ($summary) : ?>
                        <p><?php echo esc_html(wp_strip_all_tags($summary)); ?></p>
                    <?php endif; ?>
                </div>
            </a>
            <?php
        }
        wp_reset_postdata();
    } else {
        echo '<p style="color: var(--wp--preset--color--bioco-text-muted);">Aktuell sind keine Veranstaltungen geplant.</p>';
    }
    echo '</div>';
}

/**
 * Shared map-block renderer (W9 interactive blocks, issue #96). Used by both
 * bioco/depot-map and bioco/geisshof-map: a non-interactive Leaflet map
 * (progressive enhancement, see view.js) plus a static address list that
 * always renders so the block is fully usable without any map library.
 * $locations: array of ['name'=>, 'lat'=>, 'lng'=>, 'description'=>].
 */
function bioco_render_map_block($locations, $center_lat, $center_lng, $zoom) {
    $locations = is_array($locations) ? array_values(array_filter($locations, function ($l) {
        return !empty($l['name']) && isset($l['lat']) && isset($l['lng']) && $l['lat'] !== '' && $l['lng'] !== '';
    })) : [];

    $map_points = array_map(function ($l) {
        return [
            'name' => $l['name'],
            'lat' => (float) $l['lat'],
            'lng' => (float) $l['lng'],
            'description' => $l['description'] ?? '',
        ];
    }, $locations);
    ?>
    <div class="map-container">
        <div
            class="map-wrapper"
            data-center-lat="<?php echo esc_attr($center_lat); ?>"
            data-center-lng="<?php echo esc_attr($center_lng); ?>"
            data-zoom="<?php echo esc_attr($zoom); ?>"
            data-locations="<?php echo esc_attr(wp_json_encode($map_points)); ?>"
        ></div>
    </div>
    <div class="location-info-box">
        <div class="location-addresses">
            <h4><?php esc_html_e('Standorte', 'bioco'); ?></h4>
            <div class="address-list">
                <?php foreach ($locations as $location) : ?>
                    <div class="address-item">
                        <strong><?php echo esc_html($location['name']); ?></strong>
                        <?php if (!empty($location['description'])) : ?>
                            <p><?php echo esc_html($location['description']); ?></p>
                        <?php endif; ?>
                        <a
                            href="<?php echo esc_url('https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($location['lat'] . ',' . $location['lng'])); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-secondary btn-organic"
                            style="margin-top: var(--wp--preset--spacing--20); display: inline-block;"
                        >Route planen →</a>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($locations)) : ?>
                    <p style="color: var(--wp--preset--color--bioco-text-muted);">Noch keine Standorte hinterlegt.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

// Builds a CSS filter string from optional brightness/contrast/saturate
// numbers, matching SectionRenderer's getImageFilterStyle(): only non-null
// values that differ from the 1 (no-op) default are included.
function bioco_image_filter_style($brightness, $contrast, $saturate) {
    $parts = [];
    if ($brightness !== null && $brightness !== '' && (float) $brightness !== 1.0) {
        $parts[] = 'brightness(' . (float) $brightness . ')';
    }
    if ($contrast !== null && $contrast !== '' && (float) $contrast !== 1.0) {
        $parts[] = 'contrast(' . (float) $contrast . ')';
    }
    if ($saturate !== null && $saturate !== '' && (float) $saturate !== 1.0) {
        $parts[] = 'saturate(' . (float) $saturate . ')';
    }
    return $parts ? 'filter: ' . implode(' ', $parts) . ';' : '';
}
