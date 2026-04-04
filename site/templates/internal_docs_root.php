<?php namespace ProcessWire;

/**
 * Root container for /internal-docs/ (no public render).
 */
if (!defined('PROCESSWIRE')) {
    die();
}
http_response_code(404);
echo 'Not found';
