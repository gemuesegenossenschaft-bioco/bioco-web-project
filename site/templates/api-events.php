<?php namespace ProcessWire;

// Prevent ProcessWire layout rendering
$config->prependTemplateFile = null;
$config->appendTemplateFile = null;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

// Find all events, sorted by start date.
$events = $pages->find('template=event, sort=event_start');

foreach($events as $event) {
    $status = $event->event_status && in_array($event->event_status, ['upcoming', 'past'])
        ? $event->event_status
        : 'upcoming';

    $media = [];
    if($event->event_media) {
        foreach($event->event_media as $file) {
            $media[] = [
                'url' => $file->httpUrl(),
                'description' => $file->description,
                'type' => (in_array(strtolower($file->ext), ['mp4', 'mov', 'webm']) ? 'video' : 'image'),
            ];
        }
    }

    if (!$event->title) continue;

    $signupEnabled = ($status === 'upcoming') ? (bool) $event->event_signup_enabled : false;

    $dateLabel = '';
    $startDate = null;
    $endDate = null;
    if ($event->event_start) {
        $ts = is_numeric($event->event_start) ? (int)$event->event_start : strtotime($event->event_start);
        if ($ts) {
            $dateLabel = date('d.m.Y', $ts);
            $startDate = date(DATE_ATOM, $ts);
        }
    }
    if ($event->event_end) {
        $ts = is_numeric($event->event_end) ? (int)$event->event_end : strtotime($event->event_end);
        if ($ts) {
            $endDate = date(DATE_ATOM, $ts);
        }
    }
    $timeLabel = '';
    if ($event->event_start && $event->event_end) {
        $startTs = is_numeric($event->event_start) ? (int)$event->event_start : strtotime($event->event_start);
        $endTs = is_numeric($event->event_end) ? (int)$event->event_end : strtotime($event->event_end);
        if ($startTs && $endTs) {
            $timeLabel = date('H:i', $startTs) . ' - ' . date('H:i', $endTs) . ' Uhr';
        }
    }

    $eventType = $event->hasField('event_type') ? normalizeEventType($event->event_type) : 'general';

    // Card image for grid thumbnails
    $cardImage = '';
    $cardImageAlt = '';
    $cardImg = $event->hasField('event_card_image') ? $event->event_card_image : null;
    if ($cardImg && !($cardImg instanceof \Countable && $cardImg->count() === 0)) {
        if (is_object($cardImg) && method_exists($cardImg, 'httpUrl')) {
            $cardImage = $cardImg->httpUrl();
            $cardImageAlt = $cardImg->description ?: '';
        } elseif ($cardImg instanceof \Countable && $cardImg->count()) {
            $first = $cardImg->first();
            $cardImage = $first->httpUrl();
            $cardImageAlt = $first->description ?: '';
        }
    }

    $response[$status][] = [
        'id' => $event->id,
        'title' => $event->title,
        'description' => $event->event_summary ?: ($event->body ? $sanitizer->truncate($event->body, 200) : ''),
        'fullDescription' => $event->body ?: '',
        'location' => $event->event_location ?: '',
        'time' => $timeLabel,
        'dateLabel' => $dateLabel,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'timeLabel' => $timeLabel,
        'signupEnabled' => $signupEnabled,
        'signupNotes' => $event->event_signup_notes ?: '',
        'status' => $status,
        'media' => $media,
        'cardImage' => $cardImage,
        'cardImageAlt' => $cardImageAlt,
        'url' => $event->httpUrl(),
        'parentTitle' => $event->parent?->title ?: '',
        'eventType' => $eventType,
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
