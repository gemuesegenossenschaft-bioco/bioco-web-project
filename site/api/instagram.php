<?php namespace ProcessWire;

/**
 * Instagram API Endpoint
 * Returns Instagram posts as JSON for frontend
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$pages = wire('pages');
$input = wire('input');

// Get limit (default: 10)
$limit = (int) $input->get('limit', 10);
$limit = min($limit, 50); // Max 50 posts

// Find Instagram posts
$parent = $pages->get('template=instagram-container');
if (!$parent->id) {
    $parent = $pages->get('/aktuelles/');
}
if (!$parent->id) {
    $parent = $pages->get('/');
}

$posts = [];

// Try to find posts by template or by name pattern
$instagramPages = $pages->find("parent={$parent}, name^=instagram-, sort=-created, limit={$limit}");

foreach ($instagramPages as $page) {
    $post = [
        'id' => $page->id,
        'title' => $page->title,
        'body' => $page->body ?: '',
        'date' => date('d.m.Y', $page->created),
        'url' => $page->url,
    ];

    // Get Instagram-specific data
    if ($page->hasField('instagram_id')) {
        $post['instagram_id'] = $page->instagram_id;
    }
    if ($page->hasField('instagram_url')) {
        $post['instagram_url'] = $page->instagram_url;
    }
    if ($page->hasField('instagram_date')) {
        $post['date'] = date('d.m.Y', strtotime($page->instagram_date));
    }

    // Get image
    if ($page->hasField('images') && $page->images->count()) {
        $image = $page->images->first();
        $post['imageUrl'] = $image->url;
    } elseif ($page->hasField('instagram_image') && $page->instagram_image) {
        $post['imageUrl'] = $page->instagram_image->url;
    }

    $posts[] = $post;
}

// If no ProcessWire posts found, return empty array
// Frontend will fall back to static data

echo json_encode([
    'success' => true,
    'posts' => $posts,
    'count' => count($posts)
]);










