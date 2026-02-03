<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/** @var ProcessWire $wire */

// Load custom admin JavaScript
$wire->addHookAfter('Page::render', function($event) {
    if($this->wire('page')->template == 'admin') {
        $event->return = str_replace(
            '</body>',
            '<script src="' . $this->wire('config')->urls->templates . 'admin.js"></script></body>',
            $event->return
        );
    }
});
