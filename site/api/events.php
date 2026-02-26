<?php namespace ProcessWire;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$pages = wire('pages');
$sanitizer = wire('sanitizer');

$response = [
    'success' => true,
    'generatedAt' => date(DATE_ATOM),
    'upcoming' => [],
    'past' => [],
];

// Safely find events with error handling
try {
    $events = $pages->find('template=event, sort=-created');
} catch (Exception $e) {
    error_log('Failed to query events: ' . $e->getMessage());
    echo json_encode($response);
    exit;
}

foreach($events as $event) {
    // Ensure status is always set, default to 'upcoming'
    $status = $event->event_status && in_array($event->event_status, ['upcoming', 'past'])
        ? $event->event_status
        : 'upcoming';
    $media = [];

    if($event->event_media) {
        foreach($event->event_media as $file) {
            /** @var Pagefile $file */
            $media[] = [
                'url' => $file->httpUrl(),
                'description' => $file->description,
                'type' => mediaTypeFromExtension($file->ext),
            ];
        }
    }

    // Only add events that have required fields
    if (!$event->title) {
        continue;
    }

    // Ensure past events don't have signup enabled
    $signupEnabled = ($status === 'upcoming') ? (bool) $event->event_signup_enabled : false;

    $response[$status][] = [
        'id' => $event->id,
        'title' => $event->title,
        'description' => $event->event_summary ?: ($event->body ? $sanitizer->truncate($event->body, 200) : ''),
        'fullDescription' => $event->body ?: '',
        'location' => $event->event_location ?: '',
        'time' => $event->event_time ?: '',
        'signupEnabled' => $signupEnabled,
        'signupNotes' => $event->event_signup_notes ?: '',
        'status' => $status,
        'media' => $media,
        'url' => $event->httpUrl(),
        'parentTitle' => $event->parent?->title ?: '',
    ];
}

echo json_encode($response);

/**
 * @param string $ext
 */
function mediaTypeFromExtension($ext): string
{
    $videoExtensions = ['mp4', 'mov', 'webm'];
    return in_array(strtolower($ext), $videoExtensions, true) ? 'video' : 'image';
}

