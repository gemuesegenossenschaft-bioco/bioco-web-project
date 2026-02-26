<?php
namespace ProcessWire;
include('./index.php');
$wire = ProcessWire::getCurrentInstance();
$pages = $wire->wire('pages');
$fields = $wire->wire('fields');
$modules = $wire->wire('modules');
$config = $wire->wire('config');
$config->debug = true;
include $config->paths->templates . 'configure-media-library-access.php';
