<?php namespace ProcessWire;

/**
 * ProcessWire API Endpoint - Navigation
 * Returns navigation items as JSON
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Bootstrap ProcessWire (api/ is at root level)
require_once __DIR__ . '/../index.php';

$pages = wire('pages');
$home = $pages->get('/');

$navigation = [];

if($home->children->count()) {
    foreach($home->children as $child) {
        $navigation[] = [
            'id' => $child->id,
            'title' => $child->title,
            'url' => $child->url,
        ];
    }
}

echo json_encode($navigation);
