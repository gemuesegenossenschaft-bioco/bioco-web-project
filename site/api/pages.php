<?php namespace ProcessWire;

/**
 * ProcessWire API Endpoint - Pages
 * Returns page data as JSON for headless CMS
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
$pages = wire('pages');

$path = isset($_GET['path']) ? $_GET['path'] : '/';
if($path === '/') {
    $page = $pages->get('/');
} else {
    $page = $pages->get($path);
}

if(!$page || !$page->id) {
    http_response_code(404);
    echo json_encode(['error' => 'Page not found']);
    exit;
}

// Helper function for SEO data
function getSeoDataLegacy($page) {
    $config = wire('config');
    
    // Get OG image URL
    $ogImageUrl = null;
    $ogImageWidth = null;
    $ogImageHeight = null;
    
    if ($page->hasField('og_image') && $page->og_image) {
        $ogImageUrl = $config->urls->httpRoot . ltrim($page->og_image->url, '/');
        $ogImageWidth = $page->og_image->width;
        $ogImageHeight = $page->og_image->height;
    } elseif ($page->hasField('hero_image') && $page->hero_image) {
        $ogImageUrl = $config->urls->httpRoot . ltrim($page->hero_image->url, '/');
        $ogImageWidth = $page->hero_image->width;
        $ogImageHeight = $page->hero_image->height;
    }
    
    $robotsIndex = !($page->hasField('robots_noindex') && $page->robots_noindex);
    $robotsFollow = !($page->hasField('robots_nofollow') && $page->robots_nofollow);
    
    $seo = [
        'title' => $page->hasField('seo_title') && $page->seo_title 
            ? $page->seo_title 
            : $page->title,
        'description' => $page->hasField('seo_description') 
            ? ($page->seo_description ?: '') 
            : '',
        'canonical' => $page->hasField('canonical_url') && $page->canonical_url 
            ? $page->canonical_url 
            : $page->httpUrl,
        'robots' => [
            'index' => $robotsIndex,
            'follow' => $robotsFollow,
        ],
    ];
    
    if ($ogImageUrl) {
        $seo['ogImage'] = [
            'url' => $ogImageUrl,
            'width' => $ogImageWidth,
            'height' => $ogImageHeight,
        ];
    }
    
    return $seo;
}

// Build page data
$pageData = [
    'id' => $page->id,
    'title' => $page->title,
    'url' => $page->url,
    'template' => $page->template->name,
    'seo' => getSeoDataLegacy($page),
];

// Text fields
$textFields = ['body', 'hero_title', 'hero_subtitle', 'summary', 'sidebar_content', 'footer_content', 'css_variant', 'cta_text', 'cta_url'];
foreach($textFields as $field) {
    if($page->hasField($field) && $page->$field) {
        $pageData[$field] = $page->$field;
    }
}

// Image fields
$imageFields = ['logo_image', 'hero_image'];
foreach($imageFields as $field) {
    if($page->hasField($field) && $page->$field) {
        $img = $page->$field;
        $pageData[$field] = [
            'url' => $img->url,
            'description' => $img->description ?: '',
            'width' => $img->width,
            'height' => $img->height,
        ];
    }
}

// Gallery images
if($page->hasField('gallery_images') && $page->gallery_images && $page->gallery_images->count()) {
    $pageData['gallery_images'] = [];
    foreach($page->gallery_images as $img) {
        $pageData['gallery_images'][] = [
            'url' => $img->url,
            'description' => $img->description ?: '',
        ];
    }
}

// Page sections (Repeater)
if($page->hasField('page_sections') && $page->page_sections && $page->page_sections->count()) {
    $pageData['sections'] = [];
    foreach($page->page_sections as $section) {
        $sectionData = [];
        if($section->hasField('section_id')) $sectionData['id'] = $section->section_id;
        if($section->hasField('section_title')) $sectionData['title'] = $section->section_title;
        if($section->hasField('section_content')) $sectionData['content'] = $section->section_content;
        $pageData['sections'][] = $sectionData;
    }
}

// Child pages (for navigation/listing)
if($page->numChildren > 0) {
    $pageData['children'] = [];
    foreach($page->children('limit=50') as $child) {
        $childData = [
            'id' => $child->id,
            'title' => $child->title,
            'url' => $child->url,
        ];
        if($child->hasField('summary') && $child->summary) {
            $childData['summary'] = $child->summary;
        }
        $pageData['children'][] = $childData;
    }
}

echo json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
