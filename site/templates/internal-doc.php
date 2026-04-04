<?php namespace ProcessWire;

/**
 * Internal handbook page (auth-only, not served on public site).
 */
if (!defined('PROCESSWIRE')) {
    die();
}
http_response_code(404);
echo 'Not found';
