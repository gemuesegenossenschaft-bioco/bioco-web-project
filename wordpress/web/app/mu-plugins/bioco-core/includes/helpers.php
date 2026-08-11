<?php
/**
 * Shared block render helpers. Kept in one place so every block's render.php
 * sanitizes and detects headings identically, moved out of the bioco theme's
 * functions.php (#101) so they work under any active theme.
 *
 * Each function here is required before block registration; PHP function
 * definitions are global regardless of which mu-plugin defines them, so a
 * not-yet-migrated theme block (e.g. bioco/schnuppertage) calling
 * bioco_query_events() keeps working unchanged even though the theme no
 * longer defines it — mu-plugins load before the active theme.
 */

if (!defined('ABSPATH')) exit;

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

function bioco_kses_oembed_html($html) {
    $allowed = [
        'iframe' => [
            'src' => true,
            'width' => true,
            'height' => true,
            'title' => true,
            'frameborder' => true,
            'allow' => true,
            'allowfullscreen' => true,
            'loading' => true,
            'referrerpolicy' => true,
            'style' => true,
            'class' => true,
        ],
        'blockquote' => [
            'cite' => true,
            'class' => true,
            'style' => true,
            'data-instgrm-captioned' => true,
            'data-instgrm-permalink' => true,
            'data-instgrm-version' => true,
            'data-video-id' => true,
        ],
        'p' => ['class' => true, 'style' => true, 'lang' => true, 'dir' => true],
        'a' => ['href' => true, 'title' => true, 'target' => true, 'rel' => true, 'class' => true],
        'div' => ['class' => true, 'style' => true, 'lang' => true, 'dir' => true],
        'span' => ['class' => true, 'style' => true, 'lang' => true, 'dir' => true],
    ];
    return wp_kses((string) $html, $allowed);
}

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

/**
 * Event CPT helpers. Shared by bioco/events-feed and bioco/schnuppertage so
 * both blocks split/sort Veranstaltung posts the same way instead of
 * duplicating WP_Query args.
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
function bioco_render_events_list($query, $empty_message) {
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
    } elseif ($empty_message) {
        echo '<p style="color: var(--wp--preset--color--bioco-text-muted);">' . esc_html($empty_message) . '</p>';
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
function bioco_render_map_block($locations, $center_lat, $center_lng, $zoom, $locations_heading, $route_label, $empty_message) {
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
<?php if ($locations_heading) : ?>            <h4><?php echo esc_html($locations_heading); ?></h4><?php endif; ?>
            <div class="address-list">
                <?php foreach ($locations as $location) : ?>
                    <div class="address-item">
                        <strong><?php echo esc_html($location['name']); ?></strong>
                        <?php if (!empty($location['description'])) : ?>
                            <p><?php echo esc_html($location['description']); ?></p>
                        <?php endif; ?>
<?php if ($route_label) : ?>                        <a
                            href="<?php echo esc_url('https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($location['lat'] . ',' . $location['lng'])); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-secondary btn-organic"
                            style="margin-top: var(--wp--preset--spacing--20); display: inline-block;"
                        ><?php echo esc_html($route_label); ?></a><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($locations) && $empty_message) : ?>
                    <p style="color: var(--wp--preset--color--bioco-text-muted);"><?php echo esc_html($empty_message); ?></p>
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
