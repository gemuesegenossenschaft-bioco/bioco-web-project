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

function eventOptionValue($value, $fallback = '') {
    if (!$value) return $fallback;
    if (is_string($value)) return $value ?: $fallback;
    if (is_object($value) && method_exists($value, 'count') && $value->count()) {
        $value = $value->first();
    }
    if (is_object($value)) {
        foreach (['value', 'name', 'title'] as $property) {
            if (isset($value->$property) && is_string($value->$property) && trim($value->$property) !== '') {
                return $value->$property;
            }
        }
    }
    return $fallback;
}

function normalizeEventType($value) {
    $normalized = strtolower(trim(eventOptionValue($value, 'general')));
    $normalized = str_replace(['_', ' '], '-', $normalized);
    if (in_array($normalized, ['schnuppertag', 'schnuppertage'], true)) return 'schnuppertag';
    if (in_array($normalized, ['general', 'allgemein', 'allgemeiner-event', 'allgemeiner'], true)) return 'general';
    return $normalized ?: 'general';
}

function getEventCardImageData($event): array {
    if (!$event->hasField('event_card_image')) {
        return ['', ''];
    }

    $image = $event->event_card_image;
    if ($image instanceof Pageimage || $image instanceof Pagefile) {
        return [$image->httpUrl(), $image->description ?: ''];
    }
    if (($image instanceof Pageimages || $image instanceof Pagefiles) && $image->count()) {
        $first = $image->first();
        return [$first->httpUrl(), $first->description ?: ''];
    }

    return ['', ''];
}

// Safely find events with error handling
try {
    $events = $pages->find('template=event, sort=event_start');
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
    [$cardImage, $cardImageAlt] = getEventCardImageData($event);
    $startDate = null;
    $endDate = null;
    $dateLabel = '';
    $timeLabel = '';

    if ($event->event_start) {
        $startTs = is_numeric($event->event_start) ? (int) $event->event_start : strtotime($event->event_start);
        if ($startTs) {
            $startDate = date(DATE_ATOM, $startTs);
            $dateLabel = date('d.m.Y', $startTs);
        }
    }

    if ($event->event_end) {
        $endTs = is_numeric($event->event_end) ? (int) $event->event_end : strtotime($event->event_end);
        if ($endTs) {
            $endDate = date(DATE_ATOM, $endTs);
        }
    }

    if (!empty($startTs) && !empty($endTs)) {
        $timeLabel = date('H:i', $startTs) . ' - ' . date('H:i', $endTs) . ' Uhr';
    }

    $response[$status][] = [
        'id' => $event->id,
        'title' => $event->title,
        'description' => $event->event_summary ?: ($event->body ? $sanitizer->truncate($event->body, 200) : ''),
        'fullDescription' => $event->body ?: '',
        'location' => $event->event_location ?: '',
        'time' => $timeLabel,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'dateLabel' => $dateLabel,
        'timeLabel' => $timeLabel,
        'signupEnabled' => $signupEnabled,
        'signupNotes' => $event->event_signup_notes ?: '',
        'status' => $status,
        'media' => $media,
        'cardImage' => $cardImage,
        'cardImageAlt' => $cardImageAlt,
        'url' => $event->httpUrl(),
        'parentTitle' => $event->parent?->title ?: '',
        'eventType' => $event->hasField('event_type') ? normalizeEventType($event->event_type) : 'general',
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
