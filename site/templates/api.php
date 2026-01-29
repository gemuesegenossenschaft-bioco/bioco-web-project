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
if (in_array($origin, $allowedOrigins) || strpos($origin, '.vercel.app') !== false) {
    header("Access-Control-Allow-Origin: $origin");
} elseif (empty($origin)) {
    // Allow requests without origin (direct API calls, curl, etc.)
    header('Access-Control-Allow-Origin: *');
}

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
    
    // Allow unauthenticated access to health endpoint
    $endpoint = $input->urlSegment1;
    if ($endpoint !== 'health' && $requestKey !== $apiKey) {
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
        
    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Endpoint not found',
            'available' => ['health', 'content', 'forms', 'doi'],
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
                    $sectionData = [
                        'id' => $section->get('section_id') ?: 'section-' . $section->id,
                        'title' => decodeText($section->get('section_title') ?: ''),
                        'text' => $section->get('section_text') ?: '',
                    ];
                    
                    if ($section->hasField('section_image') && $section->section_image) {
                        $sectionData['image'] = getImageUrl($section, 'section_image');
                        $sectionData['imageAlt'] = decodeText($section->get('image_alt') ?: $sectionData['title']);
                    }
                    
                    // Check for buttons
                    if ($section->hasField('button_text') && $section->button_text) {
                        $sectionData['buttons'] = [[
                            'text' => decodeText($section->button_text),
                            'href' => $section->get('button_href') ?: '/',
                            'variant' => $section->get('button_variant') ?: 'primary',
                        ]];
                        
                        // Check for secondary button
                        if ($section->hasField('button2_text') && $section->button2_text) {
                            $sectionData['buttons'][] = [
                                'text' => decodeText($section->button2_text),
                                'href' => $section->get('button2_href') ?: '/',
                                'variant' => $section->get('button2_variant') ?: 'secondary',
                            ];
                        }
                    }
                    
                    $sections[] = $sectionData;
                }
            }
            
            // Also include page-level fields as first section if no repeater
            if (empty($sections) && ($contentPage->hasField('section_title') || $contentPage->hasField('body'))) {
                $pageSection = [
                    'id' => 'main',
                    'title' => decodeText($contentPage->get('section_title') ?: $contentPage->title),
                    'text' => $contentPage->get('section_text') ?: $contentPage->get('body') ?: '',
                ];
                
                if ($contentPage->hasField('section_image') && $contentPage->section_image) {
                    $pageSection['image'] = getImageUrl($contentPage, 'section_image');
                    $pageSection['imageAlt'] = decodeText($contentPage->get('image_alt') ?: '');
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
                    $sectionData = [
                        'id' => $section->get('section_id') ?: 'section-' . $section->id,
                        'title' => decodeText($section->get('section_title') ?: ''),
                        'text' => $section->get('section_text') ?: '',
                    ];
                    
                    if ($section->hasField('section_image') && $section->section_image) {
                        $sectionData['image'] = getImageUrl($section, 'section_image');
                        $sectionData['imageAlt'] = decodeText($section->get('image_alt') ?: '');
                    }
                    
                    if ($section->hasField('button_text') && $section->button_text) {
                        $sectionData['buttons'] = [[
                            'text' => decodeText($section->button_text),
                            'href' => $section->get('button_href') ?: '/',
                            'variant' => $section->get('button_variant') ?: 'primary',
                        ]];
                        
                        if ($section->hasField('button2_text') && $section->button2_text) {
                            $sectionData['buttons'][] = [
                                'text' => decodeText($section->button2_text),
                                'href' => $section->get('button2_href') ?: '/',
                                'variant' => $section->get('button2_variant') ?: 'secondary',
                            ];
                        }
                    }
                    
                    $response['sections'][] = $sectionData;
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
                    $sectionData = [];
                    if ($section->hasField('section_id')) $sectionData['id'] = $section->section_id;
                    if ($section->hasField('section_title')) $sectionData['title'] = decodeText($section->section_title);
                    if ($section->hasField('section_content')) $sectionData['content'] = $section->section_content;
                    $pageData['sections'][] = $sectionData;
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
            
            $aktuellesParent = $pages->get('/aktuelles/');
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
                'available' => ['hero', 'homepage', 'sections', 'groups', 'page', 'navigation', 'events', 'aktuelles', 'instagram'],
            ]);
    }
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
