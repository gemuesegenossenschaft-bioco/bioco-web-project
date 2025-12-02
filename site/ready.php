<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/** @var ProcessWire $wire */

require_once __DIR__ . '/classes/EventSetup.php';

(new EventSetup($wire))->bootstrap();
