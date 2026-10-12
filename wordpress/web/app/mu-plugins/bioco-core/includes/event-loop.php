<?php
/** Native loop controls own selection and ordering; this adapter owns the date split. */
if (!defined('ABSPATH')) exit;

function bioco_event_loop_query(array $data, array $attrs): array {
    if (!Bioco_Native_Loop_Empty::has_class($attrs, 'bioco-event-loop')
        || ($data['query_type'] ?? '') !== 'post_types') return $data;
    $past = Bioco_Native_Loop_Empty::has_class($attrs, 'bioco-event-past');
    $upcoming = Bioco_Native_Loop_Empty::has_class($attrs, 'bioco-event-upcoming');
    // Ambiguous saved semantics fail closed instead of showing a mixed archive.
    $query = is_array($data['query_args'] ?? null) ? $data['query_args'] : [];
    $general = Bioco_Native_Loop_Empty::has_class($attrs, 'bioco-event-general');
    $visit = Bioco_Native_Loop_Empty::has_class($attrs, 'bioco-event-schnuppertag');
    $type = $general ? 'general' : ($visit ? 'schnuppertag' : null);
    $base = bioco_event_query_args($past ? 'past' : 'upcoming', $query['posts_per_page'] ?? 3, $type);
    $native_meta = $query['meta_query'] ?? [];
    $query['post_type'] = 'event';
    $query['post_status'] = 'publish';
    $query['meta_key'] = 'event_date';
    $query['meta_query'] = $native_meta ? ['relation' => 'AND', $base['meta_query'], $native_meta] : $base['meta_query'];
    // These values are sanitized by Divi before this hook. Preserve native count,
    // offset, search, taxonomy and inclusion filters, including explicit emptiness.
    $query['orderby'] = $query['orderby'] ?? $base['orderby'];
    $query['order'] = $query['order'] ?? $base['order'];
    $count = $attrs['module']['advanced']['loop']['desktop']['value']['postPerPage'] ?? null;
    if ($past === $upcoming || ($general && $visit)
        || ($count !== null && is_numeric($count) && (int) $count === 0)) $query['post__in'] = [-1];
    $data['query_args'] = $query;
    $data['post_type'] = ['event'];
    return $data;
}

add_filter('divi_loop_data_after_execution', 'bioco_event_loop_query', 30, 2);

/** The installed native selector needs an explicit event-date option. */
function bioco_event_loop_order_options(array $options): array {
    foreach ($options as $option) {
        if (($option['value'] ?? '') === 'meta_value') return $options;
    }
    $options[] = ['value' => 'meta_value', 'label' => 'Veranstaltungsdatum'];
    return $options;
}
add_filter('et_builder_loop_order_by_options_event', 'bioco_event_loop_order_options');
