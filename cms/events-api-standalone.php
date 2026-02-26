<?php namespace ProcessWire;

// Standalone events API - place in webroot
// URL: https://cms.bioco.ch/events-api.php

include('./index.php');

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

$events = $pages->find('template=event, sort=-created');

foreach($events as $event) {
    $status = $event->event_status && in_array($event->event_status, ['upcoming', 'past'])
        ? $event->event_status
        : 'upcoming';

    if (!$event->title) continue;

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

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
