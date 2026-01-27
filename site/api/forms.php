<?php namespace ProcessWire;

/**
 * ProcessWire API Endpoint - Form Submission
 * Handles form submissions and initiates DOI
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Bootstrap ProcessWire (api/ is at root level)
require_once __DIR__ . '/../index.php';

$input = wire('input');
$modules = wire('modules');

// Get form type from URL segment or query parameter
$formType = $input->urlSegment1 ?: $input->get('form_type');

if(!in_array($formType, ['contact', 'subscribe', 'visit', 'waiting-list'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid form type']);
    exit;
}

// Get form processor
$formProcessor = $modules->get('FormProcessor');

// Process based on form type
$result = null;
$postData = json_decode(file_get_contents('php://input'), true);

switch($formType) {
    case 'contact':
        $result = $formProcessor->processContactForm((object)$postData);
        break;
    case 'subscribe':
        $result = $formProcessor->processSubscribeForm((object)$postData);
        break;
    case 'visit':
        $result = $formProcessor->processVisitDayForm((object)$postData);
        break;
    case 'waiting-list':
        $result = $formProcessor->processWaitingListForm((object)$postData);
        break;
}

if($result && $result['success']) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $result['error'] ?? 'Es ist ein Fehler aufgetreten.'
    ]);
}
