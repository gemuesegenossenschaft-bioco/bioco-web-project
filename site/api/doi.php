<?php namespace ProcessWire;

/**
 * ProcessWire API Endpoint - DOI Confirmation
 * Handles double opt-in confirmation
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

$input = wire('input');
$modules = wire('modules');

$action = $input->urlSegment1;

if($action === 'confirm') {
    $token = $input->get('token');
    
    if(!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Kein Token angegeben.']);
        exit;
    }
    
    $formProcessor = $modules->get('FormProcessor');
    $result = $formProcessor->finalizeSubmission($token);
    
    if($result['success']) {
        echo json_encode([
            'success' => true,
            'form_type' => $result['form_type'],
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Ungültiger oder abgelaufener Bestätigungslink.',
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
