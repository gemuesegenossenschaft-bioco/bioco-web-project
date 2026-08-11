<?php
/** staging.bioco.ch — like production but indexing disabled and debug logged. */
use Roots\WPConfig\Config;
Config::define('WP_DEBUG', true);
Config::define('WP_DEBUG_LOG', $root_dir . '/wordpress-debug.log');
Config::define('WP_DEBUG_DISPLAY', false);
Config::define('DISALLOW_INDEXING', true);
