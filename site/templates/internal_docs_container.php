<?php namespace ProcessWire;

/**
 * Section folder under internal-docs (no public render).
 */
if (!defined('PROCESSWIRE')) {
    die();
}
http_response_code(404);
echo 'Not found';
