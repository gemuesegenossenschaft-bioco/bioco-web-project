<?php
/**
 * Unified API Template for ProcessWire
 * 
 * Consolidates all API endpoints into a single router.
 * Configure in ProcessWire admin:
 * - Setup → Templates → api → Files: Disable _init.php and _main.php
 * - Setup → Templates → api → URLs: Enable URL segments (max 4)
 * - Create page at /api/ with this template
 */

namespace ProcessWire;

// ============================================================================
// Headers
// ============================================================================

header('Content-Type: application/json');

// CORS - Allow configured domains + Vercel previews
$allowedOrigins = $config->allowedOrigins ?? [
    'https://bioco.ch',
    'https://www.bioco.ch',
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}
// No CORS header for unknown origins or server-to-server (empty origin)

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
header('Access-Control-Max-Age: 86400');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================================
// API Key Authentication
// ============================================================================

$apiKey = $config->apiKey ?? '';
if ($apiKey) {
    $requestKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    // Allow unauthenticated access to health and content (read-only) endpoints
    // media-* endpoints use ProcessWire session auth inside handlers
    $endpoint = $input->urlSegment1;
    if (!in_array($endpoint, ['health', 'content', 'media-import', 'media-import-batch', 'media-usage', 'media-files']) && $requestKey !== $apiKey) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key', 'hint' => 'Set X-API-Key header']);
        exit;
    }
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get full image URL (required for Next.js Image component)
 */
function getImageUrl($page, $field) {
    $image = $page->get($field);
    if ($image && $image->url) {
        return wire('config')->urls->httpRoot . ltrim($image->url, '/');
    }
    return null;
}

/**
 * Get image data with metadata
 */
function getImageData($page, $field) {
    $image = $page->get($field);
    if ($image && $image->url) {
        return [
            'url' => wire('config')->urls->httpRoot . ltrim($image->url, '/'),
            'description' => $image->description ?: '',
            'width' => $image->width,
            'height' => $image->height,
        ];
    }
    return null;
}

/**
 * Get image data with alt fallback
 */
function getImageDataWithAlt($page, $field, $fallbackAlt = '') {
    $image = $page->get($field);
    if ($image && $image->url) {
        return [
            'url' => wire('config')->urls->httpRoot . ltrim($image->url, '/'),
            'alt' => $image->description ?: $fallbackAlt,
            'width' => $image->width,
            'height' => $image->height,
        ];
    }
    return null;
}

/**
 * Get SEO data for a page
 */
function getSeoData($page) {
    $config = wire('config');
    
    // Get OG image URL (og_image field, fallback to hero_image)
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
    
    // Build robots data
    $robotsIndex = !($page->hasField('robots_noindex') && $page->robots_noindex);
    $robotsFollow = !($page->hasField('robots_nofollow') && $page->robots_nofollow);
    
    $seo = [
        'title' => $page->hasField('seo_title') && $page->seo_title 
            ? decodeText($page->seo_title) 
            : decodeText($page->title),
        'description' => $page->hasField('seo_description') 
            ? decodeText($page->seo_description ?: '') 
            : '',
        'canonical' => $page->hasField('canonical_url') && $page->canonical_url 
            ? $page->canonical_url 
            : $page->httpUrl,
        'robots' => [
            'index' => $robotsIndex,
            'follow' => $robotsFollow,
        ],
    ];
    
    // Add OG image if available
    if ($ogImageUrl) {
        $seo['ogImage'] = [
            'url' => $ogImageUrl,
            'width' => $ogImageWidth,
            'height' => $ogImageHeight,
        ];
    }
    
    return $seo;
}

/**
 * Decode HTML entities for plain text fields
 * ProcessWire stores & as &amp; etc. - decode for JSON output
 */
function decodeText($text) {
    if (empty($text)) {
        return $text;
    }
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Build buttons array for a section
 */
function buildSectionButtons($section) {
    $buttons = [];
    if ($section->hasField('button_text') && $section->button_text) {
        $buttons[] = [
            'text' => decodeText($section->button_text),
            'href' => $section->get('button_href') ?: '/',
            'variant' => $section->get('button_variant') ?: 'primary',
        ];
    }
    if ($section->hasField('button2_text') && $section->button2_text) {
        $buttons[] = [
            'text' => decodeText($section->button2_text),
            'href' => $section->get('button2_href') ?: '/',
            'variant' => $section->get('button2_variant') ?: 'secondary',
        ];
    }
    return $buttons;
}

/**
 * Build media array for a section
 */
function buildSectionMedia($section, $fallbackAlt = '') {
    $media = [];
    if ($section->hasField('section_image') && $section->section_image) {
        $images = $section->section_image;
        if ($images instanceof Pageimage) {
            $images = [$images];
        }
        foreach ($images as $img) {
            $media[] = [
                'url' => wire('config')->urls->httpRoot . ltrim($img->url, '/'),
                'alt' => $img->description ?: $fallbackAlt,
                'width' => $img->width,
                'height' => $img->height,
                'type' => 'image',
            ];
        }
    }
    if ($section->hasField('section_images') && $section->section_images && $section->section_images->count()) {
        foreach ($section->section_images as $img) {
            $media[] = [
                'url' => wire('config')->urls->httpRoot . ltrim($img->url, '/'),
                'alt' => $img->description ?: $fallbackAlt,
                'width' => $img->width,
                'height' => $img->height,
                'type' => 'image',
            ];
        }
    }
    return $media;
}

/**
 * Build video data for a section
 */
function buildSectionVideo($section) {
    if ($section->hasField('section_video_url') && $section->section_video_url) {
        return [
            'url' => $section->section_video_url,
            'title' => decodeText($section->get('section_video_title') ?: ''),
        ];
    }
    return null;
}

/**
 * Build section data for API response
 */
function buildSectionData($section) {
    $title = decodeText($section->get('section_title') ?: '');
    $text = $section->get('section_text') ?: '';
    $layout = $section->get('section_layout') ?: 'split_media_text';
    $theme = $section->get('section_theme') ?: 'default';
    $eyebrow = decodeText($section->get('section_eyebrow') ?: '');
    $componentKey = decodeText($section->get('section_component') ?: '');
    $imageAlt = decodeText($section->get('image_alt') ?: $title);

    $sectionData = [
        'id' => $section->get('section_id') ?: 'section-' . $section->id,
        'title' => $title,
        'text' => $text,
        'layout' => $layout,
        'theme' => $theme,
    ];

    if (!empty($eyebrow)) {
        $sectionData['eyebrow'] = $eyebrow;
    }

    if (!empty($componentKey)) {
        $sectionData['component'] = $componentKey;
    }

    if ($section->hasField('section_image') && $section->section_image) {
        $sectionData['image'] = getImageUrl($section, 'section_image');
        $sectionData['imageAlt'] = $imageAlt;
        $sectionData['imageData'] = getImageDataWithAlt($section, 'section_image', $imageAlt);
    }

    $media = buildSectionMedia($section, $imageAlt);
    if (!empty($media)) {
        $sectionData['media'] = $media;
        $images = [];
        foreach ($media as $item) {
            if (($item['type'] ?? '') !== 'image') continue;
            $images[] = [
                'url' => $item['url'],
                'alt' => $item['alt'] ?? $imageAlt,
            ];
        }
        if (!empty($images)) {
            $sectionData['images'] = $images;
        }
    }

    $video = buildSectionVideo($section);
    if (!empty($video)) {
        $sectionData['video'] = $video;
    }

    $buttons = buildSectionButtons($section);
    if (!empty($buttons)) {
        $sectionData['buttons'] = $buttons;
    }

    $overlay = $section->get('section_image_overlay');
    if ($overlay && $overlay !== 'none') {
        $sectionData['imageOverlay'] = $overlay;
    }
    $bgColor = $section->get('section_bg_color');
    if ($bgColor && $bgColor !== 'none') {
        $sectionData['bgColor'] = $bgColor;
    }

    $brightness = $section->get('section_image_brightness');
    if ($brightness !== null && $brightness !== '' && (float) $brightness != 1.0) {
        $sectionData['imageBrightness'] = (float) $brightness;
    }
    $contrast = $section->get('section_image_contrast');
    if ($contrast !== null && $contrast !== '' && (float) $contrast != 1.0) {
        $sectionData['imageContrast'] = (float) $contrast;
    }
    $saturate = $section->get('section_image_saturate');
    if ($saturate !== null && $saturate !== '' && (float) $saturate != 1.0) {
        $sectionData['imageSaturate'] = (float) $saturate;
    }

    return $sectionData;
}

/**
 * Format page data with automatic field handling
 */
function formatPage($page, $fields = []) {
    $data = [
        'id' => $page->id,
        'name' => $page->name,
        'title' => decodeText($page->title),
        'path' => $page->path,
        'url' => $page->url,
    ];
    
    // Fields that contain HTML (from TinyMCE/CKEditor) - don't decode
    $htmlFields = ['body', 'summary_text', 'section_text', 'headline', 'quote', 'card_text'];
    
    foreach ($fields as $field) {
        if ($page->hasField($field)) {
            $value = $page->get($field);
            if ($value instanceof Pageimage) {
                $data[$field] = getImageUrl($page, $field);
            } elseif ($value instanceof Pageimages && $value->count()) {
                $data[$field] = [];
                foreach ($value as $img) {
                    $data[$field][] = [
                        'url' => wire('config')->urls->httpRoot . ltrim($img->url, '/'),
                        'description' => $img->description ?: '',
                    ];
                }
            } else {
                // Decode plain text, keep HTML fields as-is
                $data[$field] = (is_string($value) && !in_array($field, $htmlFields)) 
                    ? decodeText($value) 
                    : $value;
            }
        }
    }
    
    return $data;
}

/**
 * Get media type from file extension
 */
function mediaTypeFromExtension($ext) {
    $videoExtensions = ['mp4', 'mov', 'webm'];
    return in_array(strtolower($ext), $videoExtensions, true) ? 'video' : 'image';
}

/**
 * Require logged-in ProcessWire admin/editor for admin-only endpoints.
 */
function requireAdminSession() {
    $user = wire('user');
    if (!$user || $user->isGuest()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        return false;
    }
    return true;
}

/**
 * Parse "asset-123" marker from image tags.
 */
function parseAssetIdFromTags($tags) {
    if (!$tags) return 0;
    if (preg_match('/(?:^|\\s)asset-(\\d+)(?:\\s|$)/', (string)$tags, $m)) {
        return (int)$m[1];
    }
    return 0;
}

/**
 * Resolve target media field (image/file) from a possibly suffixed Inputfield hint.
 */
function resolveMediaFieldName(Page $page, $fieldHint) {
    $hint = (string)$fieldHint;
    $candidates = [];
    foreach ($page->template->fieldgroup as $field) {
        if ($field->type instanceof FieldtypeImage || $field->type instanceof FieldtypeFile) {
            $candidates[] = $field->name;
        }
    }
    if (in_array($hint, $candidates, true)) {
        return $hint;
    }
    foreach ($candidates as $name) {
        if (strpos($hint, $name) === 0 || strpos($hint, '_' . $name) !== false || strpos($hint, $name . '_') !== false) {
            return $name;
        }
    }
    return '';
}

function mediaUsageContextFromPage(Page $page) {
    $ctx = [
        'pageId' => (int)$page->id,
        'repeaterItemId' => null,
    ];
    if (strpos($page->template->name, 'repeater_') === 0 && method_exists($page, 'getForPage')) {
        $forPage = $page->getForPage();
        if ($forPage && $forPage->id) {
            $ctx['pageId'] = (int)$forPage->id;
            $ctx['repeaterItemId'] = (int)$page->id;
        }
    }
    return $ctx;
}

function ensureMediaUsageTableExists() {
    static $ready = false;
    if ($ready) return;
    $ready = true;
    $db = wire('database');
    $db->exec("
        CREATE TABLE IF NOT EXISTS media_asset_usage (
            asset_id INT UNSIGNED NOT NULL,
            page_id INT UNSIGNED NOT NULL,
            field VARCHAR(128) NOT NULL,
            repeater_item_id INT UNSIGNED NULL,
            file_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (asset_id, page_id, field, file_name),
            KEY idx_asset (asset_id),
            KEY idx_page (page_id),
            KEY idx_repeater (repeater_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function upsertMediaUsageRow($assetId, Page $page, $field, $fileName) {
    ensureMediaUsageTableExists();
    $ctx = mediaUsageContextFromPage($page);
    $db = wire('database');
    $stmt = $db->prepare("
        INSERT INTO media_asset_usage (asset_id, page_id, field, repeater_item_id, file_name)
        VALUES (:asset_id, :page_id, :field, :repeater_item_id, :file_name)
        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':asset_id' => (int)$assetId,
        ':page_id' => (int)$ctx['pageId'],
        ':field' => (string)$field,
        ':repeater_item_id' => $ctx['repeaterItemId'],
        ':file_name' => (string)$fileName,
    ]);
}

// ============================================================================
// Routing
// ============================================================================

$endpoint = $input->urlSegment1;
$subEndpoint = $input->urlSegment2;
$param1 = $input->urlSegment3;

switch ($endpoint) {
    case 'health':
        echo json_encode([
            'status' => 'ok',
            'timestamp' => time(),
            'version' => '2.0',
        ]);
        break;
        
    case 'content':
        handleContentRequest($subEndpoint, $param1);
        break;
        
    case 'forms':
        handleFormsRequest($subEndpoint);
        break;
        
    case 'doi':
        handleDoiRequest($subEndpoint);
        break;
        
    case 'media-import':
        handleMediaImportRequest();
        break;

    case 'media-import-batch':
        handleMediaImportBatchRequest();
        break;

    case 'media-usage':
        handleMediaUsageRequest();
        break;

    case 'media-files':
        handleMediaFilesRequest();
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Endpoint not found',
            'available' => ['health', 'content', 'forms', 'doi', 'media-import', 'media-import-batch', 'media-usage', 'media-files'],
        ]);
}

// ============================================================================
// Content Handlers
// ============================================================================

function handleContentRequest($type, $param = null) {
    $pages = wire('pages');
    $input = wire('input');
    $sanitizer = wire('sanitizer');
    
    switch ($type) {
        // --------------------------------------------------------------------
        // Health check for content subsystem
        // --------------------------------------------------------------------
        case 'status':
            echo json_encode(['status' => 'ok', 'subsystem' => 'content']);
            break;
            
        // --------------------------------------------------------------------
        // Homepage Hero
        // --------------------------------------------------------------------
        case 'hero':
            $homepage = $pages->get('/content/homepage/');
            if (!$homepage->id) {
                // Fallback to home page
                $homepage = $pages->get('/');
            }
            
            echo json_encode([
                'hero' => [
                    'headline' => decodeText($homepage->get('hero_headline') ?: $homepage->title),
                    'subtitle' => decodeText($homepage->get('hero_subtitle') ?: ''),
                    'image' => getImageUrl($homepage, 'hero_image'),
                    'imageAlt' => decodeText($homepage->get('image_alt') ?: $homepage->title),
                ],
            ]);
            break;
            
        // --------------------------------------------------------------------
        // Page sections by page name
        // --------------------------------------------------------------------
        case 'sections':
            if (!$param) {
                http_response_code(400);
                echo json_encode(['error' => 'Page name required']);
                return;
            }
            
            $contentPage = $pages->get("/content/{$param}/");
            if (!$contentPage->id) {
                // Try to find by name anywhere
                $contentPage = $pages->get("name={$param}");
            }
            
            if (!$contentPage->id) {
                http_response_code(404);
                echo json_encode(['error' => 'Page not found', 'page' => $param]);
                return;
            }
            
            $sections = [];
            
            // Check for content_sections repeater
            if ($contentPage->hasField('content_sections') && $contentPage->content_sections) {
                foreach ($contentPage->content_sections as $section) {
                    $sections[] = buildSectionData($section);
                }
            }
            
            // Also include page-level fields as first section if no repeater
            if (empty($sections) && ($contentPage->hasField('section_title') || $contentPage->hasField('body'))) {
                $pageSection = [
                    'id' => 'main',
                    'title' => decodeText($contentPage->get('section_title') ?: $contentPage->title),
                    'text' => $contentPage->get('section_text') ?: $contentPage->get('body') ?: '',
                    'layout' => 'rich_text',
                    'theme' => 'default',
                ];

                if ($contentPage->hasField('section_image') && $contentPage->section_image) {
                    $pageSection['image'] = getImageUrl($contentPage, 'section_image');
                    $pageSection['imageAlt'] = decodeText($contentPage->get('image_alt') ?: '');
                    $pageSection['imageData'] = getImageDataWithAlt($contentPage, 'section_image', $pageSection['title']);
                }

                $sections[] = $pageSection;
            }
            
            echo json_encode([
                'page' => $param,
                'seo' => getSeoData($contentPage),
                'sections' => $sections,
            ]);
            break;
            
        // --------------------------------------------------------------------
        // Group cards (for Mitmachen page)
        // --------------------------------------------------------------------
        case 'groups':
            $groupsParent = $pages->get('/content/gruppen/');
            $groups = [];
            
            if ($groupsParent->id && $groupsParent->numChildren > 0) {
                foreach ($groupsParent->children('template=group_card') as $group) {
                    $groups[] = [
                        'id' => $group->name,
                        'title' => decodeText($group->title),
                        'text' => $group->get('card_text') ?: $group->get('body') ?: '',
                        'image' => getImageUrl($group, 'card_image'),
                        'imageAlt' => decodeText($group->get('image_alt') ?: $group->title),
                    ];
                }
            }
            
            echo json_encode(['groups' => $groups]);
            break;
            
        // --------------------------------------------------------------------
        // Homepage content (combined hero + sections)
        // --------------------------------------------------------------------
        case 'homepage':
            $homepage = $pages->get('/content/homepage/');
            if (!$homepage->id) {
                $homepage = $pages->get('/');
            }
            
            $response = [
                'hero' => [
                    'headline' => decodeText($homepage->get('hero_headline') ?: $homepage->title),
                    'subtitle' => decodeText($homepage->get('hero_subtitle') ?: ''),
                    'image' => getImageUrl($homepage, 'hero_image'),
                    'imageAlt' => decodeText($homepage->get('image_alt') ?: ''),
                ],
                'seo' => getSeoData($homepage),
                'sections' => [],
            ];
            
            // Get sections
            if ($homepage->hasField('content_sections') && $homepage->content_sections) {
                foreach ($homepage->content_sections as $section) {
                    $response['sections'][] = buildSectionData($section);
                }
            }
            
            echo json_encode($response);
            break;
            
        // --------------------------------------------------------------------
        // Generic page content (migrated from pages.php)
        // --------------------------------------------------------------------
        case 'page':
            $path = $input->get('path') ?: '/';
            if ($path === '/') {
                $page = $pages->get('/');
            } else {
                $page = $pages->get($path);
            }
            
            if (!$page || !$page->id) {
                http_response_code(404);
                echo json_encode(['error' => 'Page not found']);
                return;
            }
            
            $pageData = [
                'id' => $page->id,
                'title' => decodeText($page->title),
                'url' => $page->url,
                'template' => $page->template->name,
                'seo' => getSeoData($page),
            ];
            
            // Text fields
            $textFields = ['body', 'hero_title', 'hero_subtitle', 'summary', 'sidebar_content', 'footer_content', 'css_variant', 'cta_text', 'cta_url'];
            foreach ($textFields as $field) {
                if ($page->hasField($field) && $page->$field) {
                    $pageData[$field] = $page->$field;
                }
            }
            
            // Image fields
            $imageFields = ['logo_image', 'hero_image'];
            foreach ($imageFields as $field) {
                if ($page->hasField($field) && $page->$field) {
                    $pageData[$field] = getImageData($page, $field);
                }
            }
            
            // Gallery images
            if ($page->hasField('gallery_images') && $page->gallery_images && $page->gallery_images->count()) {
                $pageData['gallery_images'] = [];
                foreach ($page->gallery_images as $img) {
                    $pageData['gallery_images'][] = [
                        'url' => wire('config')->urls->httpRoot . ltrim($img->url, '/'),
                        'description' => $img->description ?: '',
                    ];
                }
            }
            
            // Page sections (Repeater)
            if ($page->hasField('page_sections') && $page->page_sections && $page->page_sections->count()) {
                $pageData['sections'] = [];
                foreach ($page->page_sections as $section) {
                    $pageData['sections'][] = buildSectionData($section);
                }
            }

            if (empty($pageData['sections']) && $page->hasField('content_sections') && $page->content_sections && $page->content_sections->count()) {
                $pageData['sections'] = [];
                foreach ($page->content_sections as $section) {
                    $pageData['sections'][] = buildSectionData($section);
                }
            }
            
            // Child pages
            if ($page->numChildren > 0) {
                $pageData['children'] = [];
                foreach ($page->children('limit=50') as $child) {
                    $childData = [
                        'id' => $child->id,
                        'title' => decodeText($child->title),
                        'url' => $child->url,
                    ];
                    if ($child->hasField('summary') && $child->summary) {
                        $childData['summary'] = $child->summary;
                    }
                    $pageData['children'][] = $childData;
                }
            }
            
            echo json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;

        // --------------------------------------------------------------------
        // Page index for static params
        // --------------------------------------------------------------------
        case 'pages':
            $items = [];
            $query = "template!=admin, template!=api, status<" . Page::statusUnpublished;
            $pagesList = $pages->find($query);

            foreach ($pagesList as $page) {
                if (!$page->id || !$page->url) {
                    continue;
                }
                // Skip internal paths
                $path = $page->path;
                if (strpos($path, '/content/') === 0 
                    || strpos($path, '/admin/') === 0 
                    || strpos($path, '/api/') === 0
                    || strpos($path, '/processwire/') === 0
                    || strpos($path, '/http404') === 0
                    || strpos($path, '/setup') === 0) {
                    continue;
                }
                $items[] = [
                    'id' => $page->id,
                    'title' => decodeText($page->title),
                    'path' => $path,
                    'url' => $page->url,
                    'template' => $page->template->name,
                    'seo' => getSeoData($page),
                ];
            }

            echo json_encode([
                'success' => true,
                'items' => $items,
                'count' => count($items),
            ]);
            break;
            
        // --------------------------------------------------------------------
        // Navigation (migrated from navigation.php)
        // --------------------------------------------------------------------
        case 'navigation':
            $home = $pages->get('/');
            $navigation = [];
            
            if ($home->children->count()) {
                foreach ($home->children as $child) {
                    $navigation[] = [
                        'id' => $child->id,
                        'title' => decodeText($child->title),
                        'url' => $child->url,
                    ];
                }
            }
            
            echo json_encode($navigation);
            break;
            
        // --------------------------------------------------------------------
        // Events (migrated from events.php)
        // --------------------------------------------------------------------
        case 'events':
            $response = [
                'success' => true,
                'generatedAt' => date(DATE_ATOM),
                'upcoming' => [],
                'past' => [],
            ];
            
            try {
                $events = $pages->find('template=event, sort=event_start');
            } catch (\Exception $e) {
                error_log('Failed to query events: ' . $e->getMessage());
                echo json_encode($response);
                return;
            }
            
            foreach ($events as $event) {
                $status = $event->event_status && in_array($event->event_status, ['upcoming', 'past']) 
                    ? $event->event_status 
                    : 'upcoming';
                $media = [];
                
                if ($event->hasField('event_media') && $event->event_media) {
                    foreach ($event->event_media as $file) {
                        $media[] = [
                            'url' => $file->httpUrl(),
                            'description' => $file->description,
                            'type' => mediaTypeFromExtension($file->ext),
                        ];
                    }
                }
                
                if (!$event->title || !$event->event_start) {
                    continue;
                }
                
                $signupEnabled = ($status === 'upcoming') ? (bool) $event->event_signup_enabled : false;
                
                $response[$status][] = [
                    'id' => $event->id,
                    'title' => decodeText($event->title),
                    'description' => $event->event_summary ?: ($event->body ? $sanitizer->truncate($event->body, 200) : ''),
                    'fullDescription' => $event->body ?: '',
                    'location' => $event->event_location ?: '',
                    'startDate' => $event->event_start ? $event->event_start->format(DATE_ATOM) : null,
                    'endDate' => $event->event_end ? $event->event_end->format(DATE_ATOM) : null,
                    'dateLabel' => $event->event_start ? $event->event_start->format('d.m.Y') : '',
                    'timeLabel' => $event->event_start && $event->event_end
                        ? $event->event_start->format('H:i') . ' - ' . $event->event_end->format('H:i') . ' Uhr'
                        : '',
                    'signupEnabled' => $signupEnabled,
                    'signupNotes' => $event->event_signup_notes ?: '',
                    'status' => $status,
                    'media' => $media,
                    'url' => $event->httpUrl(),
                    'parentTitle' => $event->parent?->title ?: '',
                    'eventType' => $event->event_type ?: 'general',
                ];
            }
            
            echo json_encode($response);
            break;
            
        // --------------------------------------------------------------------
        // Aktuelles / News items
        // --------------------------------------------------------------------
        case 'aktuelles':
            $limit = (int) ($input->get('limit') ?: 10);
            $limit = min($limit, 50);
            
            $aktuellesParent = $pages->get('/content/aktuelles/');
            if (!$aktuellesParent->id) {
                $aktuellesParent = $pages->get('/aktuelles/');
            }
            $items = [];
            
            if ($aktuellesParent->id) {
                $newsItems = $pages->find("parent={$aktuellesParent}, template=news_item|basic-page, sort=-created, limit={$limit}");
                
                foreach ($newsItems as $item) {
                    $items[] = [
                        'id' => $item->id,
                        'title' => decodeText($item->title),
                        'summary' => $item->get('summary') ?: ($item->body ? $sanitizer->truncate($item->body, 200) : ''),
                        'body' => $item->body ?: '',
                        'date' => date('d.m.Y', $item->created),
                        'image' => getImageUrl($item, 'hero_image') ?: getImageUrl($item, 'card_image'),
                        'url' => $item->url,
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'items' => $items,
                'count' => count($items),
            ]);
            break;
            
        // --------------------------------------------------------------------
        // Instagram (migrated from instagram.php)
        // --------------------------------------------------------------------
        case 'instagram':
            $limit = (int) ($input->get('limit') ?: 10);
            $limit = min($limit, 50);
            
            $parent = $pages->get('template=instagram-container');
            if (!$parent->id) {
                $parent = $pages->get('/aktuelles/');
            }
            if (!$parent->id) {
                $parent = $pages->get('/');
            }
            
            $posts = [];
            $instagramPages = $pages->find("parent={$parent}, name^=instagram-, sort=-created, limit={$limit}");
            
            foreach ($instagramPages as $page) {
                $post = [
                    'id' => $page->id,
                    'title' => decodeText($page->title),
                    'body' => $page->body ?: '',
                    'date' => date('d.m.Y', $page->created),
                    'url' => $page->url,
                ];
                
                if ($page->hasField('instagram_id')) {
                    $post['instagram_id'] = $page->instagram_id;
                }
                if ($page->hasField('instagram_url')) {
                    $post['instagram_url'] = $page->instagram_url;
                }
                if ($page->hasField('instagram_date')) {
                    $post['date'] = date('d.m.Y', strtotime($page->instagram_date));
                }
                
                if ($page->hasField('images') && $page->images->count()) {
                    $image = $page->images->first();
                    $post['imageUrl'] = wire('config')->urls->httpRoot . ltrim($image->url, '/');
                } elseif ($page->hasField('instagram_image') && $page->instagram_image) {
                    $post['imageUrl'] = wire('config')->urls->httpRoot . ltrim($page->instagram_image->url, '/');
                }
                
                $posts[] = $post;
            }
            
            echo json_encode([
                'success' => true,
                'posts' => $posts,
                'count' => count($posts),
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Content endpoint not found',
                'type' => $type,
                'available' => ['hero', 'homepage', 'sections', 'groups', 'page', 'pages', 'navigation', 'events', 'aktuelles', 'instagram'],
            ]);
    }
}

// ============================================================================
// Media Admin Handlers
// ============================================================================

function runMediaImport(array $input, &$httpCode = 200) {
    $pages = wire('pages');
    $config = wire('config');
    $targetPageId = (int)($input['targetPageId'] ?? 0);
    $repeaterItemId = (int)($input['repeaterItemId'] ?? 0);
    $assetId = (int)($input['assetId'] ?? 0);
    $fieldHint = (string)($input['targetField'] ?? '');
    $fileField = (string)($input['fileField'] ?? '');
    $fileName = (string)($input['fileName'] ?? '');

    $missing = [];
    if (!$targetPageId && !$repeaterItemId) $missing[] = 'targetPageId|repeaterItemId';
    if (!$assetId) $missing[] = 'assetId';
    if (!$fieldHint) $missing[] = 'targetField';
    if (!$fileField) $missing[] = 'fileField';
    if (!$fileName) $missing[] = 'fileName';
    if (count($missing)) {
        $httpCode = 400;
        return [
            'success' => false,
            'error' => 'Missing required fields',
            'missing' => $missing,
        ];
    }

    $targetPage = $repeaterItemId ? $pages->get($repeaterItemId) : $pages->get($targetPageId);
    if (!$targetPage->id) {
        $httpCode = 404;
        return ['success' => false, 'error' => 'Target page not found'];
    }

    $fieldName = resolveMediaFieldName($targetPage, $fieldHint);
    if (!$fieldName || !$targetPage->hasField($fieldName)) {
        $httpCode = 400;
        return ['success' => false, 'error' => 'Target media field not found'];
    }

    $assetPage = $pages->get($assetId);
    if (!$assetPage->id) {
        $httpCode = 404;
        return ['success' => false, 'error' => 'Media asset page not found'];
    }
    if ($assetPage->template->name !== 'MediaLibrary') {
        $httpCode = 400;
        return ['success' => false, 'error' => 'Asset is not a media library item'];
    }
    if (!$assetPage->hasField($fileField)) {
        $httpCode = 400;
        return ['success' => false, 'error' => 'Invalid media file field'];
    }

    $assetFile = null;
    foreach ($assetPage->get($fileField) as $candidate) {
        if ((string)$candidate->name === $fileName) {
            $assetFile = $candidate;
            break;
        }
    }
    if (!$assetFile || !$assetFile->filename || !is_file($assetFile->filename)) {
        $httpCode = 404;
        return ['success' => false, 'error' => 'Selected media file not found'];
    }

    $targetField = $targetPage->getField($fieldName);
    if (
        !$targetField
        || !($targetField->type instanceof FieldtypeImage || $targetField->type instanceof FieldtypeFile)
    ) {
        $httpCode = 400;
        return ['success' => false, 'error' => 'Target field is not a media field'];
    }

    $targetPage->of(false);
    // Allow this request path to add files to page media fields.
    $config->biocoMediaImportInProgress = true;
    $targetFiles = $targetPage->get($fieldName);
    if (!$targetFiles) {
        $httpCode = 500;
        return ['success' => false, 'error' => 'Target media collection unavailable'];
    }

    // Avoid duplicate imports of the same source file into the same field.
    $existingByName = null;
    foreach ($targetFiles as $candidate) {
        if ((string)$candidate->name === $fileName) {
            $existingByName = $candidate;
            break;
        }
    }
    if ($existingByName && $existingByName->id) {
        $imported = $existingByName;
    } else {
        try {
            if ((int)$targetField->maxFiles === 1 && $targetFiles->count() >= 1) {
                foreach ($targetFiles as $existing) {
                    $targetFiles->remove($existing);
                }
                $targetPage->save($fieldName);
            }
            $targetFiles->add($assetFile->filename);
            $targetPage->save($fieldName);
            $imported = $targetFiles->last();
        } catch (\Exception $e) {
            $httpCode = 500;
            return ['success' => false, 'error' => 'Import failed: ' . $e->getMessage()];
        }
    }

    if ($imported) {
        try {
            $tags = trim((string)$imported->tags);
            $assetTag = 'asset-' . $assetId;
            if (strpos(" $tags ", " $assetTag ") === false) {
                $imported->tags = trim($tags . ' ' . $assetTag);
                $targetPage->save($fieldName);
            }
        } catch (\Throwable $e) {
            // Some file fields may not support tags; usage is still tracked via DB row.
        }
    }

    $url = $imported ? ($config->urls->httpRoot . ltrim($imported->url, '/')) : null;
    if ($imported) {
        try {
            upsertMediaUsageRow($assetId, $targetPage, $fieldName, (string)$imported->name);
        } catch (\Throwable $e) {
            // Usage tracking is best-effort and must not block media import.
        }
    }

    return [
        'success' => true,
        'targetPageId' => $targetPageId,
        'resolvedPageId' => $targetPage->id,
        'repeaterItemId' => $repeaterItemId ?: null,
        'targetField' => $fieldName,
        'assetId' => $assetId,
        'imported' => [
            'name' => $imported ? $imported->name : $fileName,
            'url' => $url,
            'tags' => $imported ? ((string)($imported->tags ?? '')) : '',
        ],
    ];
}

function handleMediaImportRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'POST method required']);
        return;
    }
    if (!requireAdminSession()) return;

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $httpCode = 200;
    try {
        $result = runMediaImport($input, $httpCode);
        http_response_code($httpCode);
        echo json_encode($result);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Import failed: ' . $e->getMessage()]);
    }
}

function handleMediaImportBatchRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'POST method required']);
        return;
    }
    if (!requireAdminSession()) return;

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $targetPageId = (int)($input['targetPageId'] ?? 0);
    $repeaterItemId = (int)($input['repeaterItemId'] ?? 0);
    $targetField = (string)($input['targetField'] ?? '');
    $items = is_array($input['items'] ?? null) ? $input['items'] : [];
    if (!$targetPageId && !$repeaterItemId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'targetPageId|repeaterItemId required']);
        return;
    }
    if (!$targetField) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'targetField required']);
        return;
    }
    if (!count($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'items required']);
        return;
    }

    $imported = [];
    $failed = [];
    foreach ($items as $item) {
        $payload = [
            'targetPageId' => $targetPageId,
            'repeaterItemId' => $repeaterItemId,
            'targetField' => $targetField,
            'assetId' => (int)($item['assetId'] ?? 0),
            'fileField' => (string)($item['fileField'] ?? ''),
            'fileName' => (string)($item['fileName'] ?? ''),
        ];
        $itemCode = 200;
        try {
            $res = runMediaImport($payload, $itemCode);
            if (!empty($res['success'])) {
                $imported[] = $res['imported'] ?? $payload;
            } else {
                $failed[] = [
                    'item' => $payload,
                    'error' => $res['error'] ?? 'Import failed',
                    'status' => $itemCode,
                ];
            }
        } catch (\Throwable $e) {
            $failed[] = [
                'item' => $payload,
                'error' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    $ok = count($imported) > 0;
    $error = null;
    if (!$ok) {
        $first = $failed[0] ?? null;
        $firstMsg = $first['error'] ?? 'Import failed';
        $error = 'Batch import failed: ' . $firstMsg;
    }

    http_response_code($ok ? 200 : 400);
    echo json_encode([
        'success' => $ok,
        'error' => $error,
        'importedCount' => count($imported),
        'failedCount' => count($failed),
        'imported' => $imported,
        'failed' => $failed,
    ]);
}

function handleMediaUsageRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'GET method required']);
        return;
    }
    if (!requireAdminSession()) return;

    $database = wire('database');
    $pages = wire('pages');
    ensureMediaUsageTableExists();
    $assetId = (int)(wire('input')->get('assetId') ?: 0);
    if (!$assetId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'assetId required']);
        return;
    }

    try {
        $stmt = $database->prepare("SELECT asset_id, page_id, field, repeater_item_id, file_name, updated_at FROM media_asset_usage WHERE asset_id = :asset ORDER BY page_id, field");
        $stmt->execute([':asset' => $assetId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        echo json_encode([
            'success' => true,
            'assetId' => $assetId,
            'count' => 0,
            'items' => [],
            'warning' => 'usage_table_unavailable',
        ]);
        return;
    }

    $items = [];
    foreach ($rows as $row) {
        $page = $pages->get((int)$row['page_id']);
        $items[] = [
            'assetId' => (int)$row['asset_id'],
            'pageId' => (int)$row['page_id'],
            'pageTitle' => $page->id ? decodeText($page->title) : '(deleted page)',
            'pagePath' => $page->id ? $page->path : '',
            'pageEditUrl' => $page->id ? wire('config')->urls->admin . "page/edit/?id={$page->id}" : '',
            'field' => $row['field'],
            'repeaterItemId' => $row['repeater_item_id'] !== null ? (int)$row['repeater_item_id'] : null,
            'fileName' => $row['file_name'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    echo json_encode([
        'success' => true,
        'assetId' => $assetId,
        'count' => count($items),
        'items' => $items,
    ]);
}

function handleMediaFilesRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'GET method required']);
        return;
    }
    if (!requireAdminSession()) return;

    $pages = wire('pages');
    $config = wire('config');
    $assetId = (int)(wire('input')->get('assetId') ?: 0);

    if ($assetId) {
        $asset = $pages->get($assetId);
        if (!$asset->id || $asset->template->name !== 'MediaLibrary') {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Media asset not found']);
            return;
        }
        $assets = [$asset];
    } else {
        $assets = $pages->find('template=MediaLibrary');
        if (!$assets->count()) {
            echo json_encode(['success' => true, 'files' => []]);
            return;
        }
    }

    $files = [];
    foreach ($assets as $asset) {
        foreach (['MediaImages', 'MediaFiles'] as $field) {
            if (!$asset->hasField($field)) continue;
            foreach ($asset->get($field) as $file) {
                $files[] = [
                    'assetId' => $asset->id,
                    'assetTitle' => decodeText($asset->title),
                    'fileField' => $field,
                    'fileName' => $file->name,
                    'url' => $config->urls->httpRoot . ltrim($file->url, '/'),
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'files' => $files,
    ]);
}

// ============================================================================
// Forms Handler (migrated from forms.php)
// ============================================================================

function handleFormsRequest($formType) {
    $modules = wire('modules');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST method required']);
        return;
    }
    
    if (!in_array($formType, ['contact', 'subscribe', 'visit', 'waiting-list', 'event-signup'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid form type']);
        return;
    }
    
    $formProcessor = $modules->get('FormProcessor');
    if (!$formProcessor) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'FormProcessor module not installed']);
        return;
    }
    
    $postData = json_decode(file_get_contents('php://input'), true);
    $result = null;
    
    switch ($formType) {
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
        case 'event-signup':
            if (method_exists($formProcessor, 'processEventSignupForm')) {
                $result = $formProcessor->processEventSignupForm((object)$postData);
            } else {
                $result = ['success' => false, 'error' => 'Event signup not implemented'];
            }
            break;
    }
    
    if ($result && $result['success']) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Es ist ein Fehler aufgetreten.',
        ]);
    }
}

// ============================================================================
// DOI Handler (migrated from doi.php)
// ============================================================================

function handleDoiRequest($action) {
    $input = wire('input');
    $modules = wire('modules');
    
    if ($action === 'confirm') {
        $token = $input->get('token');
        
        if (!$token) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Kein Token angegeben.']);
            return;
        }
        
        $formProcessor = $modules->get('FormProcessor');
        if (!$formProcessor) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'FormProcessor module not installed']);
            return;
        }
        
        $result = $formProcessor->finalizeSubmission($token);
        
        if ($result['success']) {
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
        echo json_encode(['success' => false, 'error' => 'Invalid action', 'available' => ['confirm']]);
    }
}
