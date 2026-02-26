<?php
/**
 * API endpoint: Save edited image back to ProcessWire
 * Receives base64 image data, replaces the original file.
 *
 * POST /cms/api/image-edit
 * Body: { image: "data:image/png;base64,...", originalUrl: "...", pageId: 123 }
 */

namespace ProcessWire;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']);
    return;
}

// Require admin session
$user = wire('user');
if (!$user || $user->isGuest()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    return;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['image']) || empty($input['originalUrl'])) {
    echo json_encode(['success' => false, 'error' => 'Missing image or originalUrl']);
    return;
}

$base64 = $input['image'];
$originalUrl = $input['originalUrl'];
$pageId = (int) ($input['pageId'] ?? 0);

// Strip data URI prefix if present
if (strpos($base64, ',') !== false) {
    $base64 = substr($base64, strpos($base64, ',') + 1);
}

$imageData = base64_decode($base64);
if (!$imageData) {
    echo json_encode(['success' => false, 'error' => 'Invalid base64 data']);
    return;
}

// Resolve original file path from URL
$config = wire('config');
$rootUrl = $config->urls->root;
$rootPath = $config->paths->root;

// Convert URL to filesystem path
$relativePath = str_replace($rootUrl, '', parse_url($originalUrl, PHP_URL_PATH));
$filePath = $rootPath . $relativePath;

if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'Original file not found']);
    return;
}

// Ensure the file is within PW's assets directory
$assetsPath = $config->paths->assets;
if (strpos(realpath($filePath), realpath($assetsPath)) !== 0) {
    echo json_encode(['success' => false, 'error' => 'File outside assets directory']);
    return;
}

// Write the edited image
$written = file_put_contents($filePath, $imageData);
if (!$written) {
    echo json_encode(['success' => false, 'error' => 'Failed to write file']);
    return;
}

// Clear PW image cache for this file
$pages = wire('pages');
if ($pageId) {
    $page = $pages->get($pageId);
    if ($page->id) {
        // Find and rebuild image variations
        $dir = dirname($filePath);
        $base = pathinfo($filePath, PATHINFO_FILENAME);
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $variations = glob("{$dir}/{$base}.*x*.{$ext}");
        if ($variations) {
            foreach ($variations as $var) {
                unlink($var);
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'url' => $originalUrl,
]);
