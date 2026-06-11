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
    header('Access-Control-Allow-Credentials: true');
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

/**
 * GitHub Actions (or automation) may use X-Internal-Docs-Token instead of X-API-Key
 * for internal-docs-export and internal-docs-sync only.
 */
function biocoInternalDocsSyncTokenValid($endpoint) {
    if (!in_array($endpoint, ['internal-docs-export', 'internal-docs-sync'], true)) {
        return false;
    }
    $expected = wire('config')->internalDocsSyncToken ?? '';
    if ($expected === '' || !is_string($expected)) {
        return false;
    }
    $given = $_SERVER['HTTP_X_INTERNAL_DOCS_TOKEN'] ?? '';
    return is_string($given) && hash_equals($expected, $given);
}

// ============================================================================
// API Key Authentication
// ============================================================================

$apiKey = $config->apiKey ?? '';
$endpoint = $input->urlSegment1;
if ($apiKey) {
    $requestKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    $internalDocsTokenOk = biocoInternalDocsSyncTokenValid($endpoint);

    // Allow unauthenticated access to health and content (read-only) endpoints
    // media-* endpoints use ProcessWire session auth inside handlers
    // internal-docs-export|sync: X-Internal-Docs-Token matching $config->internalDocsSyncToken
    if (!in_array($endpoint, ['health', 'content', 'media-import', 'media-import-batch', 'media-usage', 'media-files', 'auth-check', 'content-save', 'content-publish', 'sections-reorder', 'sections-add', 'sections-delete', 'collection-create']) && $requestKey !== $apiKey && !$internalDocsTokenOk) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key', 'hint' => 'Set X-API-Key header or valid X-Internal-Docs-Token for internal-docs endpoints']);
        exit;
    }
}

// ============================================================================
// Preview / Draft Mode
// ============================================================================

$previewToken = $input->get('preview_token') ?: '';
$isPreview = $previewToken && $previewToken === getenv('PW_PREVIEW_TOKEN');

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Resize a Pageimage to max width for API delivery.
 * Returns the resized variation (or original if already small enough).
 */
function resizeForApi($image, $maxWidth = 1600) {
    if (!$image instanceof \ProcessWire\Pageimage) return $image;
    if ($image->width <= $maxWidth) return $image;
    return $image->size($maxWidth, 0, ['upscaling' => false, 'quality' => 85]);
}

/**
 * Get full image URL (required for Next.js Image component)
 */
function normalizeAssetUrl($url) {
    $raw = trim((string) $url);
    if ($raw === '') {
        return '';
    }

    $raw = preg_replace('#^\./#', '', $raw);

    if (preg_match('#^https?://#i', $raw)) {
        $parts = parse_url($raw);
        if (!$parts || empty($parts['host'])) {
            return $raw;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = rtrim((string) $parts['host'], '.');
        $path = '/' . ltrim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $path . $query;
    }

    $host = rtrim((string) ($_SERVER['HTTP_HOST'] ?? parse_url((string) wire('config')->urls->httpRoot, PHP_URL_HOST) ?? ''), '.');
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $path = '/' . ltrim($raw, '/');

    return $host !== '' ? ($scheme . '://' . $host . $path) : $path;
}

/**
 * Get full image URL (required for Next.js Image component)
 */
function getFirstImageFromField($page, $field) {
    $value = $page->get($field);
    if (!$value) return null;

    if ($value instanceof Pageimage || $value instanceof Pagefile) {
        return $value;
    }

    if (($value instanceof Pageimages || $value instanceof Pagefiles) && $value->count()) {
        return $value->first();
    }

    return null;
}

/**
 * Get all images from a field (supports single and multi image fields)
 */
function getAllImagesFromField($page, $field) {
    $value = $page->get($field);
    if (!$value) return [];

    if ($value instanceof Pageimage || $value instanceof Pagefile) {
        return [resizeForApi($value)];
    }

    if (($value instanceof Pageimages || $value instanceof Pagefiles) && $value->count()) {
        $items = [];
        foreach ($value as $img) {
            $items[] = resizeForApi($img);
        }
        return $items;
    }

    return [];
}

/**
 * Get full image URL (required for Next.js Image component)
 */
function getImageUrl($page, $field) {
    $image = getFirstImageFromField($page, $field);
    if ($image && !empty($image->url)) {
        $image = resizeForApi($image);
        return normalizeAssetUrl($image->url);
    }
    return null;
}

/**
 * Get image data with metadata
 */
function getImageData($page, $field) {
    $image = getFirstImageFromField($page, $field);
    if ($image && !empty($image->url)) {
        $image = resizeForApi($image);
        return [
            'url' => normalizeAssetUrl($image->url),
            'description' => $image->description ?: '',
            'width' => property_exists($image, 'width') ? $image->width : null,
            'height' => property_exists($image, 'height') ? $image->height : null,
        ];
    }
    return null;
}

/**
 * Get image data with alt fallback
 */
function getImageDataWithAlt($page, $field, $fallbackAlt = '') {
    $image = getFirstImageFromField($page, $field);
    if ($image && !empty($image->url)) {
        $image = resizeForApi($image);
        return [
            'url' => normalizeAssetUrl($image->url),
            'alt' => $image->description ?: $fallbackAlt,
            'width' => property_exists($image, 'width') ? $image->width : null,
            'height' => property_exists($image, 'height') ? $image->height : null,
        ];
    }
    return null;
}

function mediaItemToApiData($file) {
    if (!$file) {
        return null;
    }

    if ($file instanceof Pageimage) {
        $file = resizeForApi($file);
    }

    $filename = property_exists($file, 'filename') ? (string) $file->filename : '';
    if ($filename !== '' && !is_file($filename)) {
        return null;
    }

    $url = !empty($file->url) ? normalizeAssetUrl($file->url) : '';
    if ($url === '') {
        return null;
    }

    return [
        'url' => $url,
        'description' => $file->description ?: '',
        'type' => mediaTypeFromExtension($file->ext),
    ];
}

function getMediaItems($page, $field) {
    $value = $page->get($field);
    if (!$value) {
        return [];
    }

    $items = [];

    if ($value instanceof Pageimage || $value instanceof Pagefile) {
        $item = mediaItemToApiData($value);
        return $item ? [$item] : [];
    }

    if ($value instanceof Pageimages || $value instanceof Pagefiles) {
        foreach ($value as $file) {
            $item = mediaItemToApiData($file);
            if ($item) {
                $items[] = $item;
            }
        }
    }

    return $items;
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
 * Default typography tokens for global H1/H2 styling.
 */
function defaultTypographySettings() {
    return [
        'h1' => [
            'color' => '#1a1a1a',
            'fontSize' => [
                'mobile' => 'calc(1.375rem + 1.5vw)',
                'desktop' => '2.5rem',
            ],
            'lineHeight' => '1.2',
            'fontWeight' => '700',
            'letterSpacing' => '0em',
        ],
        'h2' => [
            'color' => '#1a1a1a',
            'fontSize' => [
                'mobile' => 'calc(1.125rem + 0.7vw)',
                'desktop' => '1.75rem',
            ],
            'lineHeight' => '1.2',
            'fontWeight' => '700',
            'letterSpacing' => '0em',
        ],
    ];
}

function sanitizeTypographyColor($value, $default) {
    $raw = trim((string) $value);
    if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $raw)) {
        return strtolower($raw);
    }
    return $default;
}

function sanitizeTypographySize($value, $default, $allowCalc = false) {
    $raw = trim((string) $value);
    if ($allowCalc && preg_match('/^calc\\([^;{}]+\\)$/', $raw)) {
        return $raw;
    }
    if (preg_match('/^\\d+(?:\\.\\d+)?(?:px|rem)$/', $raw)) {
        return $raw;
    }
    return $default;
}

function sanitizeTypographyLineHeight($value, $default) {
    $raw = trim((string) $value);
    if (!is_numeric($raw)) {
        return $default;
    }
    $num = (float) $raw;
    if ($num < 1.0 || $num > 2.0) {
        return $default;
    }
    return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');
}

function sanitizeTypographyWeight($value, $default) {
    $raw = trim((string) $value);
    if (!preg_match('/^\\d{3}$/', $raw)) {
        return $default;
    }
    $num = (int) $raw;
    if ($num < 100 || $num > 900) {
        return $default;
    }
    return (string) $num;
}

function sanitizeTypographyLetterSpacing($value, $default) {
    $raw = trim((string) $value);
    if (preg_match('/^-?\\d+(?:\\.\\d+)?(?:em|px)$/', $raw)) {
        return $raw;
    }
    return $default;
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
            'variant' => ((string) $section->get('button_variant')) ?: 'primary',
        ];
    }
    if ($section->hasField('button2_text') && $section->button2_text) {
        $buttons[] = [
            'text' => decodeText($section->button2_text),
            'href' => $section->get('button2_href') ?: '/',
            'variant' => ((string) $section->get('button2_variant')) ?: 'secondary',
        ];
    }
    return $buttons;
}

function parseSectionConfigValue($raw) {
    if (is_array($raw)) return $raw;
    $json = trim((string)$raw);
    if ($json === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function sanitizeSectionConfigValue($value, $depth = 0) {
    if ($depth > 6) return null;
    if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
        return $value;
    }
    if (is_string($value)) {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        return mb_substr((string)$clean, 0, 400);
    }
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $child) {
            $safeKey = is_int($key) ? $key : preg_replace('/[^a-z0-9_-]+/i', '', (string)$key);
            if ($safeKey === '') continue;
            $normalized[$safeKey] = sanitizeSectionConfigValue($child, $depth + 1);
        }
        return $normalized;
    }
    return null;
}

function encodeSectionConfigValue($value) {
    $normalized = sanitizeSectionConfigValue($value);
    if (!is_array($normalized) || !count($normalized)) {
        return '';
    }
    return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function normalizeFingerprintValue($value) {
    if (!is_array($value)) return $value;
    $isList = array_keys($value) === range(0, count($value) - 1);
    if ($isList) {
        return array_map('ProcessWire\\normalizeFingerprintValue', $value);
    }
    ksort($value);
    foreach ($value as $key => $child) {
        $value[$key] = normalizeFingerprintValue($child);
    }
    return $value;
}

/**
 * Build media array for a section
 */
function buildSectionMedia($section, $fallbackAlt = '') {
    $media = [];
    $seen = [];
    foreach (['section_image', 'image', 'section_images'] as $fieldName) {
        if (!$section->hasField($fieldName)) continue;
        foreach (getAllImagesFromField($section, $fieldName) as $img) {
            if (empty($img->url)) continue;
            $url = wire('config')->urls->httpRoot . ltrim($img->url, '/');
            if (isset($seen[$url])) continue;
            $seen[$url] = true;
            $media[] = [
                'url' => $url,
                'alt' => $img->description ?: $fallbackAlt,
                'width' => property_exists($img, 'width') ? $img->width : null,
                'height' => property_exists($img, 'height') ? $img->height : null,
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
function buildSectionData($section, $sortIndex = null) {
    $title = decodeText($section->get('section_title') ?: '');
    $text = $section->get('section_text') ?: '';
    $layout = $section->get('section_layout') ?: 'split_media_text';
    $theme = $section->get('section_theme') ?: 'default';
    $eyebrow = decodeText($section->get('section_eyebrow') ?: '');
    $componentKey = decodeText($section->get('section_component') ?: '');
    $imageAlt = decodeText($section->get('image_alt') ?: $title);

    $sectionData = [
        'id' => $section->get('section_id') ?: 'section-' . $section->id,
        'pwId' => (int) $section->id,
        'title' => $title,
        'text' => $text,
        'layout' => $layout,
        'theme' => $theme,
    ];
    if ($sortIndex !== null) {
        $sectionData['sort'] = (int) $sortIndex;
    }

    if (!empty($eyebrow)) {
        $sectionData['eyebrow'] = $eyebrow;
    }

    if (!empty($componentKey)) {
        $sectionData['component'] = $componentKey;
    }
    if ($section->hasField('section_config')) {
        $config = parseSectionConfigValue($section->get('section_config'));
        if (!empty($config)) {
            $sectionData['config'] = $config;
        }
    }

    $primaryImageField = null;
    foreach (['section_image', 'image'] as $candidate) {
        if ($section->hasField($candidate) && getFirstImageFromField($section, $candidate)) {
            $primaryImageField = $candidate;
            break;
        }
    }
    if ($primaryImageField) {
        $sectionData['image'] = getImageUrl($section, $primaryImageField);
        $sectionData['imageAlt'] = $imageAlt;
        $sectionData['imageData'] = getImageDataWithAlt($section, $primaryImageField, $imageAlt);
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

function buildHomepageHeroData(Page $homepage) {
    return [
        'headline' => decodeText($homepage->get('hero_headline') ?: $homepage->title),
        'subtitle' => decodeText($homepage->get('hero_subtitle') ?: ''),
        'image' => getImageUrl($homepage, 'hero_image'),
        'imageAlt' => decodeText($homepage->get('image_alt') ?: ''),
    ];
}

function buildVisualEditorSections(Page $page) {
    $sections = [];

    if ($page->hasField('content_sections') && $page->content_sections) {
        $sortedSections = $page->content_sections->sort('sort');
        $idx = 0;
        foreach ($sortedSections as $section) {
            $sections[] = buildSectionData($section, $idx++);
        }
    }

    if (empty($sections) && ($page->hasField('section_title') || $page->hasField('body'))) {
        $pageSection = [
            'id' => 'main',
            'title' => decodeText($page->get('section_title') ?: $page->title),
            'text' => $page->get('section_text') ?: $page->get('body') ?: '',
            'layout' => 'rich_text',
            'theme' => 'default',
        ];

        if ($page->hasField('section_image') && $page->section_image) {
            $pageSection['image'] = getImageUrl($page, 'section_image');
            $pageSection['imageAlt'] = decodeText($page->get('image_alt') ?: '');
            $pageSection['imageData'] = getImageDataWithAlt($page, 'section_image', $pageSection['title']);
        }

        $buttons = buildSectionButtons($page);
        if (!empty($buttons)) {
            $pageSection['buttons'] = $buttons;
        }

        $sections[] = $pageSection;
    }

    return $sections;
}

function normalizeVisualEditorFingerprintHero($hero) {
    if (!is_array($hero)) return null;
    return [
        'headline' => (string)($hero['headline'] ?? ''),
        'subtitle' => (string)($hero['subtitle'] ?? ''),
        'image' => (string)($hero['image'] ?? ''),
        'imageAlt' => (string)($hero['imageAlt'] ?? ''),
    ];
}

function normalizeVisualEditorFingerprintButtons($buttons) {
    if (!is_array($buttons)) return [];
    return array_values(array_map(function ($button) {
        return [
            'text' => (string)($button['text'] ?? ''),
            'href' => (string)($button['href'] ?? ''),
            'variant' => (string)($button['variant'] ?? ''),
        ];
    }, $buttons));
}

function normalizeVisualEditorFingerprintSections(array $sections) {
    return array_values(array_map(function ($section) {
        $video = is_array($section['video'] ?? null) ? $section['video'] : [];
        $media = is_array($section['media'] ?? null) ? $section['media'] : [];
        return [
            'id' => (string)($section['id'] ?? ''),
            'pwId' => isset($section['pwId']) ? (int)$section['pwId'] : 0,
            'title' => (string)($section['title'] ?? ''),
            'text' => (string)($section['text'] ?? ''),
            'layout' => (string)($section['layout'] ?? ''),
            'theme' => (string)($section['theme'] ?? ''),
            'eyebrow' => (string)($section['eyebrow'] ?? ''),
            'component' => (string)($section['component'] ?? ''),
            'bgColor' => (string)($section['bgColor'] ?? ''),
            'imageOverlay' => (string)($section['imageOverlay'] ?? ''),
            'image' => (string)($section['image'] ?? ''),
            'imageAlt' => (string)($section['imageAlt'] ?? ''),
            'imageBrightness' => array_key_exists('imageBrightness', $section) ? (float)$section['imageBrightness'] : null,
            'imageContrast' => array_key_exists('imageContrast', $section) ? (float)$section['imageContrast'] : null,
            'imageSaturate' => array_key_exists('imageSaturate', $section) ? (float)$section['imageSaturate'] : null,
            'videoUrl' => (string)($video['url'] ?? ''),
            'videoTitle' => (string)($video['title'] ?? ''),
            'media' => array_values(array_map(function ($item) {
                return [
                    'url' => (string)($item['url'] ?? ''),
                    'alt' => (string)($item['alt'] ?? ''),
                    'type' => (string)($item['type'] ?? 'image'),
                ];
            }, $media)),
            'buttons' => normalizeVisualEditorFingerprintButtons($section['buttons'] ?? []),
            'config' => normalizeFingerprintValue(is_array($section['config'] ?? null) ? $section['config'] : []),
        ];
    }, $sections));
}

function buildVisualEditorFingerprint($hero, array $sections) {
    return hash('sha256', json_encode([
        'hero' => normalizeVisualEditorFingerprintHero($hero),
        'sections' => normalizeVisualEditorFingerprintSections($sections),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function buildVisualEditorCanonicalState(Page $page, $path = '') {
    $isHomepage = $path === '/' || trim((string)$page->path, '/') === 'content/homepage' || trim((string)$page->path, '/') === '';
    $hero = $isHomepage ? buildHomepageHeroData($page) : null;
    $sections = buildVisualEditorSections($page);

    return [
        'hero' => $hero,
        'sections' => $sections,
        'fingerprint' => buildVisualEditorFingerprint($hero, $sections),
        'isHomepage' => $isHomepage,
    ];
}

function sanitizeDraftOption($value, $default = '') {
    $raw = trim((string)$value);
    $clean = preg_replace('/[^a-z0-9_-]+/i', '', $raw);
    return $clean !== '' ? $clean : $default;
}

function createPublishedSectionId($rawId = '') {
    $clean = sanitizeDraftOption($rawId, '');
    if ($clean !== '' && strpos($clean, 'draft') !== 0) {
        return $clean;
    }
    return 'section-' . substr(md5(uniqid('', true)), 0, 8);
}

function applyDraftSectionToRepeater(Page $item, array $payload) {
    $sanitizer = wire('sanitizer');
    $buttons = is_array($payload['buttons'] ?? null) ? array_values($payload['buttons']) : [];
    $button1 = $buttons[0] ?? [];
    $button2 = $buttons[1] ?? [];

    $item->of(false);
    if ($item->hasField('section_id')) {
        $existingId = (string)$item->get('section_id');
        $item->set('section_id', createPublishedSectionId($payload['id'] ?? $existingId ?: ''));
    }
    if ($item->hasField('section_title')) $item->set('section_title', $sanitizer->purify($payload['title'] ?? ''));
    if ($item->hasField('section_text')) $item->set('section_text', $sanitizer->purify($payload['text'] ?? ''));
    if ($item->hasField('section_eyebrow')) $item->set('section_eyebrow', $sanitizer->purify($payload['eyebrow'] ?? ''));
    if ($item->hasField('section_layout')) $item->set('section_layout', sanitizeDraftOption($payload['layout'] ?? 'rich_text', 'rich_text'));
    if ($item->hasField('section_theme')) $item->set('section_theme', sanitizeDraftOption($payload['theme'] ?? 'default', 'default'));
    if ($item->hasField('section_bg_color')) $item->set('section_bg_color', sanitizeDraftOption($payload['bgColor'] ?? 'none', 'none'));
    if ($item->hasField('section_image_overlay')) $item->set('section_image_overlay', sanitizeDraftOption($payload['imageOverlay'] ?? 'none', 'none'));
    if ($item->hasField('section_component')) $item->set('section_component', $sanitizer->purify($payload['component'] ?? ''));
    if ($item->hasField('section_config')) $item->set('section_config', encodeSectionConfigValue($payload['config'] ?? []));
    $videoPayload = is_array($payload['video'] ?? null) ? $payload['video'] : [];
    if ($item->hasField('section_video_url')) $item->set('section_video_url', $sanitizer->purify($videoPayload['url'] ?? ($payload['videoUrl'] ?? '')));
    if ($item->hasField('section_video_title')) $item->set('section_video_title', $sanitizer->purify($videoPayload['title'] ?? ($payload['videoTitle'] ?? '')));
    if ($item->hasField('image_alt')) $item->set('image_alt', $sanitizer->purify($payload['imageAlt'] ?? ''));
    if ($item->hasField('section_image_brightness')) $item->set('section_image_brightness', $payload['imageBrightness'] ?? 1);
    if ($item->hasField('section_image_contrast')) $item->set('section_image_contrast', $payload['imageContrast'] ?? 1);
    if ($item->hasField('section_image_saturate')) $item->set('section_image_saturate', $payload['imageSaturate'] ?? 1);
    if ($item->hasField('button_text')) $item->set('button_text', $sanitizer->purify($button1['text'] ?? ''));
    if ($item->hasField('button_href')) $item->set('button_href', $sanitizer->purify($button1['href'] ?? ''));
    if ($item->hasField('button_variant')) $item->set('button_variant', sanitizeDraftOption($button1['variant'] ?? 'primary', 'primary'));
    if ($item->hasField('button2_text')) $item->set('button2_text', $sanitizer->purify($button2['text'] ?? ''));
    if ($item->hasField('button2_href')) $item->set('button2_href', $sanitizer->purify($button2['href'] ?? ''));
    if ($item->hasField('button2_variant')) $item->set('button2_variant', sanitizeDraftOption($button2['variant'] ?? 'secondary', 'secondary'));
}

function clearMediaField(Page $targetPage, $fieldName) {
    if (!$targetPage->hasField($fieldName)) return;
    $files = $targetPage->get($fieldName);
    if (($files instanceof Pageimages || $files instanceof Pagefiles) && $files->count()) {
        foreach ($files as $existing) {
            $files->remove($existing);
        }
        $targetPage->save($fieldName);
    }
}

function importDraftMediaReference(Page $targetPage, array $draftMedia, $defaultTargetField) {
    if (empty($draftMedia['assetId']) || empty($draftMedia['fileField']) || empty($draftMedia['fileName'])) {
        return;
    }
    $httpCode = 200;
    $result = runMediaImport([
        'targetPageId' => (int)$targetPage->id,
        'targetField' => $draftMedia['targetField'] ?? $defaultTargetField,
        'assetId' => (int)$draftMedia['assetId'],
        'fileField' => (string)$draftMedia['fileField'],
        'fileName' => (string)$draftMedia['fileName'],
    ], $httpCode);
    if (empty($result['success'])) {
        throw new \RuntimeException($result['error'] ?? 'Draft media import failed');
    }
}

function importDraftMediaReferences(Page $targetPage, array $draftMediaItems, $defaultTargetField) {
    foreach ($draftMediaItems as $entry) {
        if (!is_array($entry)) continue;
        importDraftMediaReference($targetPage, $entry, $defaultTargetField);
    }
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

function optionFieldValue($value, $fallback = '') {
    if (!$value) return $fallback;

    if (is_string($value)) {
        return $value ?: $fallback;
    }

    if (is_object($value) && method_exists($value, 'count') && $value->count()) {
        $value = $value->first();
    }

    if (is_object($value)) {
        foreach (['value', 'name', 'title'] as $property) {
            if (isset($value->$property) && is_string($value->$property) && trim($value->$property) !== '') {
                return $value->$property;
            }
        }
    }

    return $fallback;
}

function normalizeEventTypeValue($value) {
    $normalized = strtolower(trim(optionFieldValue($value, 'general')));
    $normalized = str_replace(['_', ' '], '-', $normalized);

    if (in_array($normalized, ['schnuppertag', 'schnuppertage'], true)) {
        return 'schnuppertag';
    }

    if (in_array($normalized, ['general', 'allgemein', 'allgemeiner-event', 'allgemeiner'], true)) {
        return 'general';
    }

    return $normalized ?: 'general';
}

/**
 * Require logged-in ProcessWire editor/superuser for write/admin endpoints.
 */
function requireAdminSession() {
    $user = wire('user');
    if (!$user || $user->isGuest()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        return false;
    }
    if (!$user->hasRole('superuser') && !$user->hasRole('editor')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Editor or superuser role required']);
        return false;
    }
    return true;
}

function ensureVisualEditorDraftsTableExists() {
    static $ready = false;
    if ($ready) return;
    $ready = true;
    $db = wire('database');
    $db->exec("
        CREATE TABLE IF NOT EXISTS visual_editor_drafts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            page_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            path VARCHAR(255) NOT NULL,
            base_fingerprint VARCHAR(128) NOT NULL,
            base_sections_json LONGTEXT NULL,
            draft_sections_json LONGTEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_page_user_path (page_id, user_id, path),
            KEY idx_user (user_id),
            KEY idx_page (page_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function normalizeVisualEditorPath($path) {
    $raw = trim((string)$path);
    if ($raw === '') return '/';
    if ($raw[0] !== '/') $raw = '/' . $raw;
    return rtrim($raw, '/') ?: '/';
}

function getVisualEditorDraftRecord($pageId, $userId, $path) {
    ensureVisualEditorDraftsTableExists();
    $db = wire('database');
    $stmt = $db->prepare("
        SELECT page_id, user_id, path, base_fingerprint, base_sections_json, draft_sections_json, updated_at
        FROM visual_editor_drafts
        WHERE page_id = :page_id AND user_id = :user_id AND path = :path
        LIMIT 1
    ");
    $stmt->execute([
        ':page_id' => (int)$pageId,
        ':user_id' => (int)$userId,
        ':path' => (string)normalizeVisualEditorPath($path),
    ]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row) return null;

    $baseSections = json_decode((string)($row['base_sections_json'] ?? '[]'), true);
    $draftSections = json_decode((string)($row['draft_sections_json'] ?? '[]'), true);

    return [
        'pageId' => (int)$row['page_id'],
        'userId' => (int)$row['user_id'],
        'path' => (string)$row['path'],
        'baseFingerprint' => (string)$row['base_fingerprint'],
        'baseSections' => is_array($baseSections) ? $baseSections : [],
        'sections' => is_array($draftSections) ? $draftSections : [],
        'updatedAt' => (string)$row['updated_at'],
    ];
}

function upsertVisualEditorDraftRecord($pageId, $userId, $path, $baseFingerprint, array $baseSections, array $sections) {
    ensureVisualEditorDraftsTableExists();
    $db = wire('database');
    $stmt = $db->prepare("
        INSERT INTO visual_editor_drafts (page_id, user_id, path, base_fingerprint, base_sections_json, draft_sections_json)
        VALUES (:page_id, :user_id, :path, :base_fingerprint, :base_sections_json, :draft_sections_json)
        ON DUPLICATE KEY UPDATE
            base_fingerprint = VALUES(base_fingerprint),
            base_sections_json = VALUES(base_sections_json),
            draft_sections_json = VALUES(draft_sections_json),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':page_id' => (int)$pageId,
        ':user_id' => (int)$userId,
        ':path' => (string)normalizeVisualEditorPath($path),
        ':base_fingerprint' => (string)$baseFingerprint,
        ':base_sections_json' => json_encode(array_values($baseSections), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':draft_sections_json' => json_encode(array_values($sections), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function deleteVisualEditorDraftRecord($pageId, $userId, $path) {
    ensureVisualEditorDraftsTableExists();
    $db = wire('database');
    $stmt = $db->prepare("
        DELETE FROM visual_editor_drafts
        WHERE page_id = :page_id AND user_id = :user_id AND path = :path
    ");
    $stmt->execute([
        ':page_id' => (int)$pageId,
        ':user_id' => (int)$userId,
        ':path' => (string)normalizeVisualEditorPath($path),
    ]);
    return $stmt->rowCount() > 0;
}

function defaultVisualEditorPresets() {
    return [
        [
            'id' => 'preset-page-intro',
            'name' => 'Page Intro',
            'category' => 'Layout',
            'description' => 'Einführung mit flexibel einstellbarer Textbreite.',
            'thumbnailUrl' => '',
            'payload' => ['layout' => 'component', 'component' => 'page_intro', 'title' => 'Seiteneinstieg', 'text' => '<p></p>', 'theme' => 'default', 'config' => ['containerWidth' => 'lg', 'textWidth' => 'normal', 'align' => 'left']],
        ],
        [
            'id' => 'preset-media-text-left',
            'name' => 'Media + Text (Links)',
            'category' => 'Layout',
            'description' => 'Bild links, Text rechts.',
            'thumbnailUrl' => '',
            'payload' => ['layout' => 'component', 'component' => 'media_text', 'title' => 'Media + Text', 'text' => '<p></p>', 'theme' => 'default', 'config' => ['mediaSide' => 'left']],
        ],
        [
            'id' => 'preset-media-text-right',
            'name' => 'Media + Text (Rechts)',
            'category' => 'Layout',
            'description' => 'Bild rechts, Text links.',
            'thumbnailUrl' => '',
            'payload' => ['layout' => 'component', 'component' => 'media_text', 'title' => 'Media + Text', 'text' => '<p></p>', 'theme' => 'default', 'config' => ['mediaSide' => 'right']],
        ],
        [
            'id' => 'preset-cards-grid',
            'name' => 'Cards Grid',
            'category' => 'Media',
            'description' => 'Kartenraster für Bildinhalte.',
            'thumbnailUrl' => '',
            'payload' => ['layout' => 'component', 'component' => 'cards_grid', 'title' => 'Kartenraster', 'text' => '<p></p>', 'theme' => 'default', 'config' => ['columnsDesktop' => '3']],
        ],
        [
            'id' => 'preset-gallery-strip',
            'name' => 'Gallery Strip',
            'category' => 'Media',
            'description' => 'Galeriestreifen mit mehreren Bildern.',
            'thumbnailUrl' => '',
            'payload' => ['layout' => 'component', 'component' => 'gallery_strip', 'title' => 'Galerie', 'text' => '<p></p>', 'theme' => 'default', 'config' => ['columnsDesktop' => '3']],
        ],
        [
            'id' => 'preset-text-columns',
            'name' => 'Text Columns',
            'category' => 'Text',
            'description' => 'Mehrspaltiger Textblock.',
            'thumbnailUrl' => '',
            'payload' => ['layout' => 'component', 'component' => 'text_columns', 'title' => 'Textspalten', 'text' => '<p></p>', 'theme' => 'default', 'config' => ['columnsDesktop' => '2']],
        ],
        [
            'id' => 'preset-cta-band',
            'name' => 'CTA Band',
            'category' => 'CTA',
            'description' => 'Breiter CTA-Block.',
            'thumbnailUrl' => '',
            'payload' => ['layout' => 'component', 'component' => 'cta_band', 'title' => 'Mitmachen', 'text' => '<p></p>', 'theme' => 'default', 'buttons' => [['text' => 'Mehr erfahren', 'href' => '/', 'variant' => 'primary']]],
        ],
    ];
}

function readVisualEditorPresetsFromPages() {
    $pages = wire('pages');
    $config = wire('config');
    $items = [];
    $presetPages = $pages->find('template=component_preset, sort=sort');
    foreach ($presetPages as $preset) {
        $payload = [];
        if ($preset->hasField('preset_payload') && $preset->get('preset_payload')) {
            $decoded = json_decode((string)$preset->get('preset_payload'), true);
            if (is_array($decoded)) $payload = $decoded;
        }
        if (!count($payload)) {
            $payload = [
                'layout' => 'component',
                'component' => $preset->hasField('section_component') ? (string)$preset->get('section_component') : '',
                'title' => $preset->hasField('section_title') ? decodeText($preset->get('section_title')) : decodeText($preset->title),
                'text' => $preset->hasField('section_text') ? (string)$preset->get('section_text') : '<p></p>',
                'theme' => $preset->hasField('section_theme') ? (string)$preset->get('section_theme') : 'default',
            ];
            if ($preset->hasField('section_config')) {
                $payload['config'] = parseSectionConfigValue($preset->get('section_config'));
            }
        }

        $thumb = '';
        if ($preset->hasField('preset_screenshot')) {
            $image = getFirstImageFromField($preset, 'preset_screenshot');
            if ($image && $image->url) {
                $thumb = $config->urls->httpRoot . ltrim($image->url, '/');
            }
        }

        $items[] = [
            'id' => 'preset-' . (int)$preset->id,
            'name' => decodeText($preset->title),
            'category' => $preset->hasField('preset_category') ? decodeText($preset->get('preset_category') ?: 'General') : 'General',
            'description' => $preset->hasField('preset_description') ? decodeText($preset->get('preset_description') ?: '') : '',
            'thumbnailUrl' => $thumb,
            'payload' => $payload,
        ];
    }
    return $items;
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
// Internal handbook (ProcessWire auth-only; mirror for GitHub Actions)
// ============================================================================

function biocoIsInternalDocsPage(Page $page) {
    if (!$page || !$page->id) {
        return false;
    }
    $t = $page->template->name;
    if (in_array($t, ['internal-doc', 'internal_docs_root', 'internal_docs_container'], true)) {
        return true;
    }
    $path = (string) $page->path;
    return strpos($path, '/internal-docs') !== false;
}

function biocoInternalDocsMirrorRelativePath(Page $page) {
    $pages = wire('pages');
    $root = $pages->get('/internal-docs/');
    if (!$root->id || $page->template->name !== 'internal-doc') {
        return null;
    }
    $parts = [];
    $p = $page;
    while ($p->id && $p->id !== $root->id) {
        array_unshift($parts, $p->name);
        $p = $p->parent;
    }
    if (!$p->id || $p->id !== $root->id) {
        return null;
    }
    return implode('/', $parts) . '.md';
}

function biocoParseInternalDocFile(string $raw) {
    if (!preg_match('/^---[\r\n]+(.+?)[\r\n]+---[\r\n]+(.*)$/s', $raw, $m)) {
        return null;
    }
    $meta = [];
    foreach (preg_split('/\r\n|\r|\n/', $m[1]) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^([a-zA-Z0-9_]+):\s*(.+)$/', $line, $mm)) {
            $meta[$mm[1]] = trim($mm[2], " \t\"'");
        }
    }
    return ['meta' => $meta, 'body' => $m[2]];
}

function biocoBuildInternalDocExportFile(Page $page) {
    $body = $page->hasField('body') ? (string) $page->get('body') : '';
    $checksum = hash('sha256', $body);
    $lines = [
        '---',
        'pw_id: ' . (int) $page->id,
        'title: ' . json_encode(decodeText($page->title), JSON_UNESCAPED_UNICODE),
        'modified: ' . (int) $page->modified,
        'checksum: ' . $checksum,
        '---',
        '',
    ];
    return implode("\n", $lines) . $body;
}

function biocoHandleInternalDocsExport() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'GET required']);
        return;
    }
    $pages = wire('pages');
    $root = $pages->get('/internal-docs/');
    if (!$root->id) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'internal-docs root not found']);
        return;
    }
    $selector = 'template=internal-doc, has_parent=' . (int) $root->id . ', include=all, sort=path';
    $list = $pages->find($selector);
    $files = [];
    $manifest = [];
    foreach ($list as $page) {
        $rel = biocoInternalDocsMirrorRelativePath($page);
        if (!$rel) {
            continue;
        }
        $content = biocoBuildInternalDocExportFile($page);
        $files[$rel] = $content;
        $manifest[] = [
            'id' => (int) $page->id,
            'path' => $rel,
            'title' => decodeText($page->title),
            'modified' => (int) $page->modified,
            'checksum' => hash('sha256', (string) $page->get('body')),
        ];
    }
    echo json_encode([
        'success' => true,
        'generatedAt' => date(DATE_ATOM),
        'manifest' => $manifest,
        'files' => $files,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function biocoHandleInternalDocsSync() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'POST required']);
        return;
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        return;
    }
    $files = $data['files'] ?? null;
    if (!is_array($files)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'files object required']);
        return;
    }
    $force = !empty($data['force']);
    $pages = wire('pages');
    $updated = [];
    $skipped = [];
    $errors = [];

    foreach ($files as $relPath => $fileContent) {
        if (!is_string($relPath) || !is_string($fileContent)) {
            $errors[] = ['path' => (string) $relPath, 'error' => 'invalid entry'];
            continue;
        }
        if (!preg_match('/^[a-zA-Z0-9_./-]+\.md$/', $relPath)) {
            $errors[] = ['path' => $relPath, 'error' => 'invalid path'];
            continue;
        }
        $parsed = biocoParseInternalDocFile($fileContent);
        if (!$parsed) {
            $errors[] = ['path' => $relPath, 'error' => 'missing YAML front matter'];
            continue;
        }
        $pwId = (int) ($parsed['meta']['pw_id'] ?? 0);
        if (!$pwId) {
            $errors[] = ['path' => $relPath, 'error' => 'pw_id missing'];
            continue;
        }
        $page = $pages->get($pwId);
        if (!$page->id || $page->template->name !== 'internal-doc') {
            $errors[] = ['path' => $relPath, 'error' => 'not an internal-doc page'];
            continue;
        }
        $expectedRel = biocoInternalDocsMirrorRelativePath($page);
        if ($expectedRel !== $relPath) {
            $errors[] = ['path' => $relPath, 'error' => 'path does not match page tree'];
            continue;
        }
        $fileModified = (int) ($parsed['meta']['modified'] ?? 0);
        if (!$force && $fileModified && $page->modified > $fileModified) {
            $skipped[] = ['id' => $pwId, 'path' => $relPath, 'reason' => 'cms newer than file modified'];
            continue;
        }
        $newBody = $parsed['body'];
        $newTitle = isset($parsed['meta']['title']) ? (string) $parsed['meta']['title'] : '';
        $page->of(false);
        $page->set('body', $newBody);
        if ($newTitle !== '') {
            $page->set('title', $newTitle);
        }
        $page->save();
        $updated[] = ['id' => $pwId, 'path' => $relPath];
    }

    echo json_encode([
        'success' => count($errors) === 0,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE);
}

// ============================================================================
// Routing
// ============================================================================

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

    case 'auth-check':
        $user = wire('user');
        if ($user && !$user->isGuest() && ($user->hasRole('superuser') || $user->hasRole('editor'))) {
            echo json_encode(['loggedIn' => true, 'username' => $user->name]);
        } else {
            http_response_code(401);
            echo json_encode(['loggedIn' => false]);
        }
        break;

    case 'content-save':
        if (!requireAdminSession()) break;
        $data = json_decode(file_get_contents('php://input'), true);
        $sectionId = (int)($data['sectionPwId'] ?? ($data['sectionId'] ?? 0));
        $pageId = (int)($data['pageId'] ?? 0);
        $fields = $data['fields'] ?? [];
        if ((!$sectionId && !$pageId) || !$fields) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing sectionPwId|pageId or fields']);
            break;
        }
        $sanitizer = wire('sanitizer');
        if ($sectionId) {
            $section = wire('pages')->get($sectionId);
            if (!$section->id || strpos($section->template->name, 'repeater_') !== 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid section']);
                break;
            }
            $allowedFields = [
                'section_title', 'section_text', 'section_eyebrow',
                'section_layout', 'section_theme', 'section_bg_color',
                'section_component', 'section_config', 'section_image_overlay',
                'section_video_url', 'section_video_title',
                'image_alt',
                'section_image_brightness', 'section_image_contrast', 'section_image_saturate',
                'button_text', 'button_href', 'button_variant',
                'button2_text', 'button2_href', 'button2_variant',
            ];
            $section->of(false);
            foreach ($fields as $key => $value) {
                if (in_array($key, $allowedFields) && $section->hasField($key)) {
                    if ($key === 'section_config') {
                        $section->set($key, encodeSectionConfigValue($value));
                    } else {
                        $section->set($key, $sanitizer->purify($value));
                    }
                }
            }
            $section->save();
            echo json_encode(['success' => true, 'saved' => true, 'sectionId' => $sectionId]);
            break;
        }

        $page = wire('pages')->get($pageId);
        if (!$page->id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid page']);
            break;
        }
        $allowedPageFields = ['hero_headline', 'hero_subtitle', 'image_alt'];
        $page->of(false);
        foreach ($fields as $key => $value) {
            if (in_array($key, $allowedPageFields) && $page->hasField($key)) {
                $page->set($key, $sanitizer->purify($value));
            }
        }
        $page->save();
        echo json_encode(['success' => true, 'saved' => true, 'pageId' => $pageId]);
        break;

    case 'content-publish':
        if (!requireAdminSession()) break;
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $pageId = (int)($data['pageId'] ?? 0);
            $path = (string)($data['path'] ?? '');
            $baseFingerprint = (string)($data['baseFingerprint'] ?? '');
            $sectionsPayload = is_array($data['sections'] ?? null) ? array_values($data['sections']) : [];
            if (!$pageId || !$path || !$baseFingerprint || !count($sectionsPayload)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing pageId, path, baseFingerprint or sections']);
                break;
            }

            $page = wire('pages')->get($pageId);
            if (!$page->id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid page']);
                break;
            }
            if (!$page->hasField('content_sections')) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Page does not support visual editor publishing']);
                break;
            }

            $canonical = buildVisualEditorCanonicalState($page, $path);
            if ($baseFingerprint !== ($canonical['fingerprint'] ?? '')) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'Die Seite wurde zwischenzeitlich geändert. Entwurf neu laden oder verwerfen.',
                    'fingerprint' => $canonical['fingerprint'] ?? '',
                    'hero' => $canonical['hero'],
                    'sections' => $canonical['sections'],
                ]);
                break;
            }

            $page->of(false);
            $heroPayload = null;
            $normalSections = [];
            foreach ($sectionsPayload as $payloadSection) {
                if (!is_array($payloadSection)) continue;
                $isHero = (($payloadSection['id'] ?? '') === '__hero__') || (($payloadSection['layout'] ?? '') === 'hero');
                if ($isHero) {
                    $heroPayload = $payloadSection;
                    continue;
                }
                $normalSections[] = $payloadSection;
            }

            if ($canonical['isHomepage'] && $heroPayload) {
                $sanitizer = wire('sanitizer');
                if ($page->hasField('hero_headline')) $page->set('hero_headline', $sanitizer->purify($heroPayload['title'] ?? ''));
                if ($page->hasField('hero_subtitle')) $page->set('hero_subtitle', $sanitizer->purify($heroPayload['eyebrow'] ?? ''));
                if ($page->hasField('image_alt')) $page->set('image_alt', $sanitizer->purify($heroPayload['imageAlt'] ?? ''));
                $page->save();
                if (is_array($heroPayload['draftMedia'] ?? null)) {
                    importDraftMediaReference($page, $heroPayload['draftMedia'], 'hero_image');
                }
            }

            $existingById = [];
            foreach ($page->content_sections as $item) {
                $existingById[(int)$item->id] = $item;
            }

            $keptIds = [];
            $orderedItems = [];
            foreach ($normalSections as $index => $payloadSection) {
                $payloadPwId = (int)($payloadSection['pwId'] ?? 0);
                if ($payloadPwId && isset($existingById[$payloadPwId])) {
                    $item = $existingById[$payloadPwId];
                } else {
                    $item = $page->content_sections->getNew();
                }
                $item->of(false);
                applyDraftSectionToRepeater($item, $payloadSection);
                $item->sort = $index;
                $item->save();

                if (array_key_exists('mediaItems', $payloadSection) || array_key_exists('draftMediaItems', $payloadSection)) {
                    $draftMediaItems = is_array($payloadSection['draftMediaItems'] ?? null) ? $payloadSection['draftMediaItems'] : [];
                    $mediaItems = is_array($payloadSection['mediaItems'] ?? null) ? $payloadSection['mediaItems'] : [];
                    if (count($draftMediaItems)) {
                        clearMediaField($item, 'section_images');
                        clearMediaField($item, 'section_image');
                        importDraftMediaReferences($item, $draftMediaItems, 'section_images');
                    } elseif (count($mediaItems) === 0) {
                        // Explicit clear request from media manager.
                        clearMediaField($item, 'section_images');
                        clearMediaField($item, 'section_image');
                    } elseif (is_array($payloadSection['draftMedia'] ?? null)) {
                        clearMediaField($item, 'section_image');
                        importDraftMediaReference($item, $payloadSection['draftMedia'], 'section_image');
                    }
                }

                $keptIds[] = (int)$item->id;
                $orderedItems[] = $item;

                if (!array_key_exists('mediaItems', $payloadSection) && !array_key_exists('draftMediaItems', $payloadSection) && is_array($payloadSection['draftMedia'] ?? null)) {
                    importDraftMediaReference($item, $payloadSection['draftMedia'], 'section_image');
                }
            }

            foreach ($page->content_sections as $item) {
                if (!in_array((int)$item->id, $keptIds, true)) {
                    $page->content_sections->remove($item);
                }
            }

            foreach ($orderedItems as $index => $item) {
                $item->sort = $index;
                $item->save();
            }
            $page->save('content_sections');

            $published = buildVisualEditorCanonicalState($page, $path);
            $user = wire('user');
            if ($user && !$user->isGuest()) {
                deleteVisualEditorDraftRecord((int)$pageId, (int)$user->id, $path);
            }

            // Synchronously revalidate the published page so the Visual Editor can report
            // whether the change reached the live build (the Pages::saved hook also enqueues
            // an async revalidation; double-dispatch is idempotent in Next).
            $revalidate = ['ok' => false, 'status' => 0, 'error' => 'unavailable'];
            if (function_exists('ProcessWire\\biocoRevalidatePathsNow')) {
                $revalidatePaths = array_values(array_unique(array_filter([
                    biocoNextPathFromPage($page),
                    '/',
                ])));
                $revalidate = biocoRevalidatePathsNow($revalidatePaths, ['cms'], true);
            }

            echo json_encode([
                'success' => true,
                'fingerprint' => $published['fingerprint'],
                'hero' => $published['hero'],
                'sections' => $published['sections'],
                'revalidated' => (bool) ($revalidate['ok'] ?? false),
                'revalidateStatus' => (int) ($revalidate['status'] ?? 0),
                'revalidateError' => (string) ($revalidate['error'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Publish failed: ' . $e->getMessage()]);
        }
        break;

    // ====================================================================
    // Section CRUD (Visual Editor)
    // ====================================================================

    case 'sections-reorder':
        if (!requireAdminSession()) break;
        $data = json_decode(file_get_contents('php://input'), true);
        $pageId = (int)($data['pageId'] ?? 0);
        $order = $data['order'] ?? [];
        if (!$pageId || !is_array($order) || empty($order)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing pageId or order']);
            break;
        }
        $parentPage = wire('pages')->get($pageId);
        if (!$parentPage->id || !$parentPage->hasField('content_sections')) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid page or no content_sections field']);
            break;
        }
        $parentPage->of(false);
        $repeater = $parentPage->content_sections;
        $sortMap = [];
        foreach ($order as $idx => $pwId) {
            $sortMap[(int) $pwId] = $idx;
        }
        foreach ($repeater as $item) {
            if (isset($sortMap[$item->id])) {
                $item->sort = $sortMap[$item->id];
                $item->save();
            }
        }
        $parentPage->save('content_sections');
        echo json_encode(['success' => true, 'reordered' => count($order)]);
        break;

    case 'sections-add':
        if (!requireAdminSession()) break;
        $data = json_decode(file_get_contents('php://input'), true);
        $pageId = (int)($data['pageId'] ?? 0);
        $sanitizer = wire('sanitizer');
        $layout = $sanitizer->name($data['layout'] ?? 'rich_text');
        if (!$layout) {
            $layout = 'rich_text';
        }
        if (!$pageId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing pageId']);
            break;
        }
        $parentPage = wire('pages')->get($pageId);
        if (!$parentPage->id || !$parentPage->hasField('content_sections')) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid page or no content_sections field']);
            break;
        }
        $parentPage->of(false);
        $newItem = $parentPage->content_sections->getNew();
        $newItem->of(false);
        $newItem->set('section_title', 'Neuer Abschnitt');
        $newItem->set('section_text', '<p></p>');
        $newItem->set('section_layout', $layout);
        $newItem->save();
        $parentPage->save('content_sections');
        echo json_encode([
            'success' => true,
            'section' => buildSectionData($newItem, $parentPage->content_sections->count() - 1),
        ]);
        break;

    case 'sections-delete':
        if (!requireAdminSession()) break;
        $data = json_decode(file_get_contents('php://input'), true);
        $pageId = (int)($data['pageId'] ?? 0);
        $sectionPwId = (int)($data['sectionPwId'] ?? 0);
        if (!$pageId || !$sectionPwId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing pageId or sectionPwId']);
            break;
        }
        $parentPage = wire('pages')->get($pageId);
        if (!$parentPage->id || !$parentPage->hasField('content_sections')) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid page or no content_sections field']);
            break;
        }
        $parentPage->of(false);
        $found = false;
        foreach ($parentPage->content_sections as $item) {
            if ((int) $item->id === $sectionPwId) {
                $parentPage->content_sections->remove($item);
                $found = true;
                break;
            }
        }
        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => 'Section not found']);
            break;
        }
        $parentPage->save('content_sections');
        echo json_encode(['success' => true, 'deleted' => $sectionPwId]);
        break;

    case 'collection-create':
        // Create a new collection entry (event) under /aktuelles/ with a given date,
        // then return its PW edit URL so the Visual Editor can open it focused.
        if (!requireAdminSession()) break;
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $type = wire('sanitizer')->name($data['type'] ?? 'event');
            $dateStr = trim((string)($data['date'] ?? ''));
            $ts = $dateStr ? strtotime($dateStr) : time();
            if (!$ts) $ts = time();

            if ($type !== 'event') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nur Events werden derzeit unterstützt.']);
                break;
            }

            $parent = wire('pages')->get('/aktuelles/');
            if (!$parent->id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Eltern-Seite /aktuelles/ nicht gefunden.']);
                break;
            }

            $p = new Page();
            $p->template = wire('templates')->get('event');
            $p->parent = $parent;
            $p->of(false);
            $p->title = 'Neuer Event ' . date('d.m.Y', $ts);
            $p->name = 'event-' . date('Ymd', $ts) . '-' . substr(md5(uniqid('', true)), 0, 6);
            $p->set('event_status', 'upcoming');
            $p->set('event_start', $ts);
            $p->set('event_end', $ts);
            $p->set('event_location', '');
            $p->set('event_summary', '');

            // event_type is required + an options field: default to the first option.
            $etField = wire('fields')->get('event_type');
            if ($etField && $etField->type instanceof FieldtypeOptions) {
                $opts = $etField->type->getOptions($etField);
                if ($opts && $opts->count()) {
                    $p->set('event_type', $opts->first()->id);
                }
            }

            $p->save();

            if (function_exists('ProcessWire\\biocoRevalidatePathsNow')) {
                biocoRevalidatePathsNow(['/aktuelles', '/'], ['cms'], true);
            }

            echo json_encode([
                'success' => true,
                'pwId' => $p->id,
                'title' => $p->title,
                'editUrl' => wire('config')->urls->admin . 'page/edit/?id=' . $p->id,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erstellen fehlgeschlagen: ' . $e->getMessage()]);
        }
        break;

    case 'internal-docs-export':
        biocoHandleInternalDocsExport();
        break;

    case 'internal-docs-sync':
        biocoHandleInternalDocsSync();
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Endpoint not found',
            'available' => ['health', 'content', 'forms', 'doi', 'media-import', 'media-import-batch', 'media-usage', 'media-files', 'auth-check', 'content-save', 'content-publish', 'sections-reorder', 'sections-add', 'sections-delete', 'internal-docs-export', 'internal-docs-sync', 'content/draft', 'content/presets'],
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
        case 'draft':
            if (!requireAdminSession()) break;
            $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            if ($method === 'GET') {
                $pageId = (int)($input->get('pageId') ?: 0);
                $path = (string)($input->get('path') ?: '/');
                if (!$pageId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'pageId required']);
                    break;
                }
                $user = wire('user');
                $record = getVisualEditorDraftRecord($pageId, (int)$user->id, $path);
                echo json_encode([
                    'success' => true,
                    'draft' => $record,
                ]);
                break;
            }
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $pageId = (int)($data['pageId'] ?? 0);
                $path = (string)($data['path'] ?? '/');
                $baseFingerprint = (string)($data['baseFingerprint'] ?? '');
                $sections = is_array($data['sections'] ?? null) ? array_values($data['sections']) : [];
                $baseSections = is_array($data['baseSections'] ?? null) ? array_values($data['baseSections']) : [];
                if (!$pageId || !$baseFingerprint || !count($sections)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'pageId, baseFingerprint and sections required']);
                    break;
                }
                $user = wire('user');
                upsertVisualEditorDraftRecord($pageId, (int)$user->id, $path, $baseFingerprint, $baseSections, $sections);
                $record = getVisualEditorDraftRecord($pageId, (int)$user->id, $path);
                echo json_encode(['success' => true, 'draft' => $record]);
                break;
            }
            if ($method === 'DELETE') {
                $pageId = (int)($input->get('pageId') ?: 0);
                $path = (string)($input->get('path') ?: '/');
                if (!$pageId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'pageId required']);
                    break;
                }
                $user = wire('user');
                $deleted = deleteVisualEditorDraftRecord($pageId, (int)$user->id, $path);
                echo json_encode(['success' => true, 'deleted' => $deleted]);
                break;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;

        case 'presets':
            if (!requireAdminSession()) break;
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'GET method required']);
                break;
            }
            $presets = readVisualEditorPresetsFromPages();
            if (!count($presets)) {
                $presets = defaultVisualEditorPresets();
            }
            echo json_encode([
                'success' => true,
                'presets' => $presets,
            ]);
            break;

        // --------------------------------------------------------------------
        // Health check for content subsystem
        // --------------------------------------------------------------------
        case 'status':
            echo json_encode(['status' => 'ok', 'subsystem' => 'content']);
            break;

        // --------------------------------------------------------------------
        // Global site settings (typography tokens)
        // --------------------------------------------------------------------
        case 'settings':
            $defaults = defaultTypographySettings();
            $settingsPage = $pages->get('/content/design-settings/');
            if (!$settingsPage->id) {
                $settingsPage = $pages->get('template=site_settings, name=design-settings');
            }

            $h1Color = $defaults['h1']['color'];
            $h1Mobile = $defaults['h1']['fontSize']['mobile'];
            $h1Desktop = $defaults['h1']['fontSize']['desktop'];
            $h1LineHeight = $defaults['h1']['lineHeight'];
            $h1Weight = $defaults['h1']['fontWeight'];
            $h1LetterSpacing = $defaults['h1']['letterSpacing'];

            $h2Color = $defaults['h2']['color'];
            $h2Mobile = $defaults['h2']['fontSize']['mobile'];
            $h2Desktop = $defaults['h2']['fontSize']['desktop'];
            $h2LineHeight = $defaults['h2']['lineHeight'];
            $h2Weight = $defaults['h2']['fontWeight'];
            $h2LetterSpacing = $defaults['h2']['letterSpacing'];

            if ($settingsPage->id) {
                $h1Color = sanitizeTypographyColor($settingsPage->get('typography_h1_color'), $h1Color);
                $h1Mobile = sanitizeTypographySize($settingsPage->get('typography_h1_size_mobile'), $h1Mobile, true);
                $h1Desktop = sanitizeTypographySize($settingsPage->get('typography_h1_size_desktop'), $h1Desktop, true);
                $h1LineHeight = sanitizeTypographyLineHeight($settingsPage->get('typography_h1_line_height'), $h1LineHeight);
                $h1Weight = sanitizeTypographyWeight($settingsPage->get('typography_h1_font_weight'), $h1Weight);
                $h1LetterSpacing = sanitizeTypographyLetterSpacing($settingsPage->get('typography_h1_letter_spacing'), $h1LetterSpacing);

                $h2Color = sanitizeTypographyColor($settingsPage->get('typography_h2_color'), $h2Color);
                $h2Mobile = sanitizeTypographySize($settingsPage->get('typography_h2_size_mobile'), $h2Mobile, true);
                $h2Desktop = sanitizeTypographySize($settingsPage->get('typography_h2_size_desktop'), $h2Desktop, true);
                $h2LineHeight = sanitizeTypographyLineHeight($settingsPage->get('typography_h2_line_height'), $h2LineHeight);
                $h2Weight = sanitizeTypographyWeight($settingsPage->get('typography_h2_font_weight'), $h2Weight);
                $h2LetterSpacing = sanitizeTypographyLetterSpacing($settingsPage->get('typography_h2_letter_spacing'), $h2LetterSpacing);
            }

            echo json_encode([
                'success' => true,
                'settings' => [
                    'typography' => [
                        'h1' => [
                            'color' => $h1Color,
                            'fontSize' => [
                                'mobile' => $h1Mobile,
                                'desktop' => $h1Desktop,
                            ],
                            'lineHeight' => $h1LineHeight,
                            'fontWeight' => $h1Weight,
                            'letterSpacing' => $h1LetterSpacing,
                        ],
                        'h2' => [
                            'color' => $h2Color,
                            'fontSize' => [
                                'mobile' => $h2Mobile,
                                'desktop' => $h2Desktop,
                            ],
                            'lineHeight' => $h2LineHeight,
                            'fontWeight' => $h2Weight,
                            'letterSpacing' => $h2LetterSpacing,
                        ],
                    ],
                ],
            ]);
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
                'hero' => buildHomepageHeroData($homepage),
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
            
            $sections = buildVisualEditorSections($contentPage);
            $fingerprint = buildVisualEditorFingerprint(null, $sections);
            
            echo json_encode([
                'page' => $param,
                'seo' => getSeoData($contentPage),
                'sections' => $sections,
                'fingerprint' => $fingerprint,
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
                'hero' => buildHomepageHeroData($homepage),
                'seo' => getSeoData($homepage),
                'sections' => buildVisualEditorSections($homepage),
            ];
            $response['fingerprint'] = buildVisualEditorFingerprint($response['hero'], $response['sections']);
            
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

            if (biocoIsInternalDocsPage($page)) {
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
            
            // Page sections (Repeater, explicit sort order)
            if ($page->hasField('page_sections') && $page->page_sections && $page->page_sections->count()) {
                $pageData['sections'] = [];
                $sortedSections = $page->page_sections->sort('sort');
                $idx = 0;
                foreach ($sortedSections as $section) {
                    $pageData['sections'][] = buildSectionData($section, $idx++);
                }
            }

            if (empty($pageData['sections']) && $page->hasField('content_sections') && $page->content_sections && $page->content_sections->count()) {
                $pageData['sections'] = [];
                $sortedSections = $page->content_sections->sort('sort');
                $idx = 0;
                foreach ($sortedSections as $section) {
                    $pageData['sections'][] = buildSectionData($section, $idx++);
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
                if (biocoIsInternalDocsPage($page)) {
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
                    if (biocoIsInternalDocsPage($child)) {
                        continue;
                    }
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
                $media = $event->hasField('event_media') ? getMediaItems($event, 'event_media') : [];
                
                if (!$event->title) {
                    continue;
                }
                
                $signupEnabled = ($status === 'upcoming') ? (bool) $event->event_signup_enabled : false;
                $cardImage = $event->hasField('event_card_image') ? getImageData($event, 'event_card_image') : null;
                $startDate = null;
                $endDate = null;
                $dateLabel = '';
                $timeLabel = '';

                if ($event->event_start) {
                    $startTs = is_numeric($event->event_start) ? (int) $event->event_start : strtotime((string) $event->event_start);
                    if ($startTs) {
                        $startDate = date(DATE_ATOM, $startTs);
                        $dateLabel = date('d.m.Y', $startTs);
                    }
                }

                if ($event->event_end) {
                    $endTs = is_numeric($event->event_end) ? (int) $event->event_end : strtotime((string) $event->event_end);
                    if ($endTs) {
                        $endDate = date(DATE_ATOM, $endTs);
                    }
                }

                if (!empty($startTs) && !empty($endTs)) {
                    $timeLabel = date('H:i', $startTs) . ' - ' . date('H:i', $endTs) . ' Uhr';
                }
                
                $response[$status][] = [
                    'id' => $event->id,
                    'title' => decodeText($event->title),
                    'description' => $event->event_summary ?: ($event->body ? $sanitizer->truncate($event->body, 200) : ''),
                    'fullDescription' => $event->body ?: '',
                    'location' => $event->event_location ?: '',
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'dateLabel' => $dateLabel,
                    'timeLabel' => $timeLabel,
                    'signupEnabled' => $signupEnabled,
                    'signupNotes' => $event->event_signup_notes ?: '',
                    'status' => $status,
                    'media' => $media,
                    'cardImage' => $cardImage['url'] ?? '',
                    'cardImageAlt' => $cardImage['description'] ?? '',
                    'url' => $event->httpUrl(),
                    'parentTitle' => $event->parent?->title ?: '',
                    'eventType' => $event->hasField('event_type') ? normalizeEventTypeValue($event->event_type) : 'general',
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
            
        // ----------------------------------------------------------------
        // Page path lookup (for preview button)
        // ----------------------------------------------------------------
        case 'page-path':
            $id = (int)($input->get('id') ?: 0);
            if (!$id) {
                echo json_encode(['error' => 'Missing id parameter']);
                break;
            }
            $target = $pages->get($id);
            if (!$target->id) {
                echo json_encode(['error' => 'Page not found']);
                break;
            }
            if (biocoIsInternalDocsPage($target)) {
                echo json_encode(['error' => 'Page not found']);
                break;
            }
            $path = rtrim(str_replace('/content/', '/', $target->path), '/') ?: '/';
            $siteUrl = getenv('NEXT_PUBLIC_SITE_URL') ?: 'https://bioco.ch';
            $draftSecret = getenv('PW_PREVIEW_TOKEN') ?: '';
            echo json_encode([
                'path' => $path,
                'siteUrl' => $siteUrl,
                'draftSecret' => $draftSecret,
            ]);
            break;

        // ----------------------------------------------------------------
        // Convert upcoming event to past recap
        // ----------------------------------------------------------------
        case 'event-to-recap':
            if (!requireAdminSession()) break;
            $data = json_decode(file_get_contents('php://input'), true);
            $pageId = (int)($data['pageId'] ?? 0);
            if (!$pageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing pageId']);
                break;
            }
            $eventPage = $pages->get($pageId);
            if (!$eventPage->id || $eventPage->template->name !== 'event') {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Event not found']);
                break;
            }
            $eventPage->of(false);
            $eventPage->set('event_status', 'past');
            if ($eventPage->hasField('event_signup_enabled')) {
                $eventPage->set('event_signup_enabled', 0);
            }
            $eventPage->save();
            echo json_encode(['success' => true, 'pageId' => $pageId]);
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Content endpoint not found',
                'type' => $type,
                'available' => ['hero', 'homepage', 'sections', 'groups', 'page', 'pages', 'navigation', 'events', 'aktuelles', 'instagram', 'page-path', 'event-to-recap'],
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

function getTurnstileSecretKey() {
    $secret = trim((string)(getenv('TURNSTILE_SECRET_KEY') ?: ''));
    if ($secret !== '') return $secret;

    $config = wire('config');
    if (isset($config->turnstileSecretKey)) {
        return trim((string)$config->turnstileSecretKey);
    }

    return '';
}

function getRequestIpForTurnstile() {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $raw) {
        if (!is_string($raw) || $raw === '') continue;
        $first = trim(explode(',', $raw)[0] ?? '');
        if ($first !== '') return $first;
    }

    return '';
}

function verifyTurnstileToken($token) {
    if (!is_string($token) || trim($token) === '') {
        return ['ok' => false, 'errorCode' => 'captcha_missing'];
    }

    $secret = getTurnstileSecretKey();
    if ($secret === '') {
        wire('log')->save('api-forms', 'TURNSTILE_SECRET_KEY is missing for forms endpoint.');
        return ['ok' => false, 'errorCode' => 'captcha_unavailable'];
    }

    if (!function_exists('curl_init')) {
        wire('log')->save('api-forms', 'cURL extension missing, cannot verify Turnstile token.');
        return ['ok' => false, 'errorCode' => 'captcha_service_unreachable'];
    }

    $payload = [
        'secret' => $secret,
        'response' => trim($token),
    ];

    $remoteIp = getRequestIpForTurnstile();
    if ($remoteIp !== '') {
        $payload['remoteip'] = $remoteIp;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        wire('log')->save('api-forms', 'Turnstile verification request failed: ' . ($curlError ?: ('HTTP ' . $httpCode)));
        return ['ok' => false, 'errorCode' => 'captcha_service_error'];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded['success'])) {
        $codes = is_array($decoded['error-codes'] ?? null) ? $decoded['error-codes'] : [];
        return ['ok' => false, 'errorCode' => $codes[0] ?? 'captcha_invalid'];
    }

    return ['ok' => true];
}

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
    if (!is_array($postData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage.']);
        return;
    }

    $captcha = verifyTurnstileToken($postData['captchaToken'] ?? '');
    if (!$captcha['ok']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Bitte bestätigen Sie, dass Sie kein Roboter sind.']);
        return;
    }

    unset($postData['captchaToken']);
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
