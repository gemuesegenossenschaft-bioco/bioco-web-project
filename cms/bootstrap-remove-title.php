<?php
namespace ProcessWire;
include('./index.php');
$wire = ProcessWire::getCurrentInstance();
$pages = $wire->wire('pages');
$fields = $wire->wire('fields');
$fieldgroups = $wire->wire('fieldgroups');
$config = $wire->wire('config');
$config->debug = true;
include $config->paths->templates . 'remove-title-from-repeater.php';
