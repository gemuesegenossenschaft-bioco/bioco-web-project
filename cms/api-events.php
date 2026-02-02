<?php namespace ProcessWire;

/**
 * Events API Endpoint
 *
 * Upload to: site/api/events.php
 * Access via: https://cms.bioco.ch/api/events.php
 *
 * Returns all events (upcoming and past) in JSON format
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    // Find all event pages, sorted by start date (newest first)
    $events = $pages->find("template=event, sort=-event_start");

    $upcoming = [];
    $past = [];
    $now = time();

    foreach($events as $event) {
        if(!$event->id) continue;

        $startTime = $event->event_start ? strtotime($event->event_start) : 0;
        $endTime = $event->event_end ? strtotime($event->event_end) : 0;

        // Build event data
        $item = [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->event_summary ?: '',
            'fullDescription' => $event->body ?: '',
            'location' => $event->event_location ?: '',
            'startDate' => $event->event_start ? date('c', $startTime) : null,
            'endDate' => $event->event_end ? date('c', $endTime) : null,
            'dateLabel' => $event->event_start ? strftime('%e. %B %Y', $startTime) : '',
            'timeLabel' => $event->event_start && $event->event_end
                ? strftime('%H:%M', $startTime) . ' - ' . strftime('%H:%M', $endTime) . ' Uhr'
                : '',
            'signupEnabled' => (bool)$event->event_signup_enabled,
            'signupNotes' => $event->event_signup_notes ?: '',
            'status' => $event->event_status ?: 'upcoming',
            'media' => [],
            'url' => $event->httpUrl,
            'parentTitle' => $event->parent->title ?: 'Events',
        ];

        // Add media files (images and videos)
        if($event->event_media && count($event->event_media)) {
            foreach($event->event_media as $file) {
                $isVideo = in_array($file->ext, ['mp4', 'webm', 'mov', 'avi']);

                $item['media'][] = [
                    'url' => $file->httpUrl,
                    'type' => $isVideo ? 'video' : 'image',
                    'description' => $file->description ?: '',
                ];
            }
        }

        // Add card image if exists
        if($event->event_card_image && $event->event_card_image->url) {
            array_unshift($item['media'], [
                'url' => $event->event_card_image->httpUrl,
                'type' => 'image',
                'description' => $event->event_card_image_alt ?: $event->title,
            ]);
        }

        // Categorize by status
        if($endTime > $now || $item['status'] === 'upcoming') {
            $upcoming[] = $item;
        } else {
            $past[] = $item;
        }
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'generatedAt' => date('c'),
        'upcoming' => $upcoming,
        'past' => $past,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch(\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'upcoming' => [],
        'past' => [],
    ]);
}
