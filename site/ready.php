<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/** @var ProcessWire $wire */

// Add Media Library to admin navbar under Pages
$wire->addHookAfter('ProcessController::getNavigationArray', function($event) {
    $nav = $event->return;

    // Add Media under page (Pages) menu
    if (!isset($nav['page'])) {
        $nav['page'] = [];
    }

    if (!isset($nav['page']['children'])) {
        $nav['page']['children'] = [];
    }

    $nav['page']['children']['media'] = [
        'id' => 1768,
        'parent_id' => 2,
        'title' => 'Medienbibliothek',
        'name' => 'media',
        'url' => $event->wire('config')->urls->admin . 'media/',
    ];

    $event->return = $nav;
});
