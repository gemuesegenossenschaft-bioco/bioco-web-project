<?php
/**
 * Migration: Add event_type field to event template
 * Run via bootstrap: curl https://cms.bioco.ch/bootstrap-add-event-type.php
 */

namespace ProcessWire;

// Only allow from CLI or localhost
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Forbidden: run from localhost or CLI only');
}

require_once __DIR__ . '/../site/init.php';

$fields = wire('fields');
$templates = wire('templates');
$pages = wire('pages');

// Create event_type field if not exists
$f = $fields->get('event_type');
if (!$f) {
    $f = new Field();
    $f->type = wire('modules')->get('FieldtypeOptions');
    $f->name = 'event_type';
    $f->label = 'Event-Typ';
    $f->description = 'Kategorisierung des Events';
    $f->save();

    // Set options
    $manager = new SelectableOptionManager();
    $manager->setOptionsString($f, "general|Allgemeiner Event\nschnuppertag|Schnuppertag");
    $f->save();

    echo "Created field: event_type\n";
} else {
    echo "Field event_type already exists\n";
}

// Add to event template
$t = $templates->get('event');
if ($t) {
    $fg = $t->fieldgroup;
    if (!$fg->hasField('event_type')) {
        $fg->add($f);
        $fg->save();
        echo "Added event_type to event template\n";
    } else {
        echo "event_type already in event template\n";
    }
} else {
    echo "ERROR: event template not found\n";
}

// Migrate existing events: set schnuppertag where title contains it
$events = $pages->find('template=event');
$migrated = 0;
foreach ($events as $event) {
    if (stripos($event->title, 'schnuppertag') !== false) {
        $event->of(false);
        $event->event_type = 'schnuppertag';
        $event->save();
        $migrated++;
        echo "Set schnuppertag: {$event->title}\n";
    } elseif (!$event->event_type) {
        $event->of(false);
        $event->event_type = 'general';
        $event->save();
    }
}

echo "\nDone. Migrated {$migrated} events to schnuppertag.\n";
