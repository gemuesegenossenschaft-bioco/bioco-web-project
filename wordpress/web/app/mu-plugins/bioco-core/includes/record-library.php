<?php
/** Shared saved-layout lookup; runtime reads need no import/composer package. */
if (!defined('ABSPATH')) exit;

function bioco_record_library_posts(string $key): array {
    return get_posts(['post_type' => 'et_pb_layout', 'name' => 'bioco-record-' . $key,
        'post_status' => array_values(get_post_stati()), 'posts_per_page' => 2]);
}
