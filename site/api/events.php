<?php namespace ProcessWire;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Bootstrap ProcessWire (api/ is at root level)
require_once __DIR__ . '/../index.php';

$pages = wire('pages');

$response = [
    'success' => true,
    'generatedAt' => date(DATE_ATOM),
    'upcoming' => [],
    'past' => [],
];

try {
    $now = time();
    $events = $pages->find('template=event, sort=event_start');
    
    foreach ($events as $event) {
        $eventData = [
            'id' => $event->id,
            'title' => $event->title,
            'url' => $event->url,
            'start' => $event->event_start ? date(DATE_ATOM, $event->event_start) : null,
            'end' => $event->event_end ? date(DATE_ATOM, $event->event_end) : null,
            'location' => $event->event_location ?? '',
            'description' => $event->body ?? '',
            'signupEnabled' => (bool) $event->signup_enabled,
            'status' => $event->event_status ?? 'upcoming',
        ];
        
        $eventEnd = $event->event_end ?: $event->event_start;
        if ($eventEnd && $eventEnd < $now) {
            $response['past'][] = $eventData;
        } else {
            $response['upcoming'][] = $eventData;
        }
    }
} catch (\Exception $e) {
    error_log('Events API error: ' . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
