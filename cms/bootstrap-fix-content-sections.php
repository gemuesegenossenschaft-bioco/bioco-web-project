<?php
namespace ProcessWire;
include('./index.php');
$wire = ProcessWire::getCurrentInstance();
$pages = $wire->wire('pages');
$templates = $wire->wire('templates');
$fields = $wire->wire('fields');
$fieldgroups = $wire->wire('fieldgroups');
$modules = $wire->wire('modules');
$config = $wire->wire('config');
$config->debug = true;
include $config->paths->templates . 'fix-content-sections-title.php';
