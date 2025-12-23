<?php namespace ProcessWire;

/**
 * InstagramSync Module
 * Synchronizes Instagram posts from @bioco_ch to ProcessWire
 * Runs daily via LazyCron
 */
class InstagramSync extends WireData implements Module, ConfigurableModule {

    public static function getModuleInfo() {
        return [
            'title' => 'Instagram Sync',
            'version' => 1,
            'summary' => 'Synchronizes Instagram posts from @bioco_ch to Aktuelles',
            'singular' => true,
            'autoload' => true,
            'requires' => ['LazyCron'],
        ];
    }

    public function init() {
        // Register daily cron job
        $this->addHook('LazyCron::everyDay', $this, 'syncInstagram');
    }

    public function ___install() {
        // Create Instagram posts page if it doesn't exist
        $parent = $this->pages->get('/aktuelles/');
        if (!$parent->id) {
            $parent = $this->pages->get('/');
        }
        
        // Create Instagram container page
        $instagramPage = $this->pages->get('template=instagram-container');
        if (!$instagramPage->id) {
            $instagramPage = new Page();
            $instagramPage->template = 'basic-page';
            $instagramPage->parent = $parent;
            $instagramPage->name = 'instagram-posts';
            $instagramPage->title = 'Instagram Posts';
            $instagramPage->save();
        }
    }

    /**
     * Daily sync job
     */
    public function syncInstagram(HookEvent $event) {
        $this->log("Starting Instagram sync...");
        
        try {
            $posts = $this->fetchInstagramPosts();
            $this->savePosts($posts);
            $this->log("Instagram sync completed. Fetched " . count($posts) . " posts.");
        } catch (\Exception $e) {
            $this->error("Instagram sync failed: " . $e->getMessage());
        }
    }

    /**
     * Fetch Instagram posts from API
     * TODO: Replace with actual Instagram API integration
     * For now, this is a placeholder structure
     */
    protected function fetchInstagramPosts() {
        $accessToken = $this->access_token;
        $username = 'bioco_ch';
        
        if (empty($accessToken)) {
            $this->log("No Instagram access token configured. Skipping sync.");
            return [];
        }

        // Instagram Graph API endpoint
        // Note: Requires Instagram Business Account and Facebook App
        $url = "https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp&access_token={$accessToken}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Instagram API returned HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        
        if (!isset($data['data'])) {
            throw new \Exception("Invalid Instagram API response");
        }

        return $data['data'];
    }

    /**
     * Save Instagram posts to ProcessWire pages
     */
    protected function savePosts($posts) {
        $parent = $this->pages->get('template=instagram-container');
        if (!$parent->id) {
            $parent = $this->pages->get('/aktuelles/');
        }
        if (!$parent->id) {
            $parent = $this->pages->get('/');
        }

        $existingPosts = [];
        foreach ($parent->children('template=instagram-post') as $page) {
            $existingPosts[$page->instagram_id] = $page;
        }

        foreach ($posts as $post) {
            $instagramId = $post['id'];
            
            // Skip if post already exists
            if (isset($existingPosts[$instagramId])) {
                continue;
            }

            // Create new page for Instagram post
            $page = new Page();
            $page->template = 'basic-page'; // Use basic-page or create custom template
            $page->parent = $parent;
            $page->name = 'instagram-' . $instagramId;
            $page->title = $this->extractTitle($post['caption'] ?? 'Instagram Post');
            $page->body = $post['caption'] ?? '';
            
            // Store Instagram data in custom fields (if template has them)
            if ($page->hasField('instagram_id')) {
                $page->instagram_id = $instagramId;
            }
            if ($page->hasField('instagram_url')) {
                $page->instagram_url = $post['permalink'] ?? '';
            }
            if ($page->hasField('instagram_image')) {
                // Download and save image
                $imageUrl = $post['media_url'] ?? ($post['thumbnail_url'] ?? '');
                if ($imageUrl) {
                    $this->saveInstagramImage($page, $imageUrl);
                }
            }
            if ($page->hasField('instagram_date')) {
                $page->instagram_date = date('Y-m-d H:i:s', strtotime($post['timestamp'] ?? 'now'));
            }
            
            $page->save();
        }
    }

    /**
     * Extract title from Instagram caption
     */
    protected function extractTitle($caption) {
        if (empty($caption)) {
            return 'Instagram Post';
        }
        
        // Take first line or first 50 characters
        $lines = explode("\n", $caption);
        $title = trim($lines[0]);
        
        if (strlen($title) > 100) {
            $title = substr($title, 0, 97) . '...';
        }
        
        return $title ?: 'Instagram Post';
    }

    /**
     * Download and save Instagram image
     */
    protected function saveInstagramImage($page, $imageUrl) {
        try {
            $imageData = @file_get_contents($imageUrl);
            if ($imageData === false) {
                return;
            }

            $filename = 'instagram-' . $page->instagram_id . '.jpg';
            $path = $this->config->paths->files . $filename;
            
            file_put_contents($path, $imageData);
            
            // Add image to page if field exists
            if ($page->hasField('images')) {
                $image = new Pageimage($page->images, $path);
                $page->images->add($image);
            }
        } catch (\Exception $e) {
            $this->log("Failed to download Instagram image: " . $e->getMessage());
        }
    }

    /**
     * Module configuration
     */
    public static function getModuleConfigInputfields(array $data) {
        $fields = new InputfieldWrapper();
        $modules = wire('modules');

        // Access Token
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'access_token');
        $f->label = 'Instagram Access Token';
        $f->description = 'Instagram Graph API Access Token (requires Instagram Business Account)';
        $f->value = $data['access_token'] ?? '';
        $f->required = false;
        $fields->add($f);

        // Username
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'username');
        $f->label = 'Instagram Username';
        $f->description = 'Instagram username (e.g., bioco_ch)';
        $f->value = $data['username'] ?? 'bioco_ch';
        $f->required = true;
        $fields->add($f);

        // Sync interval
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'sync_interval');
        $f->label = 'Sync Interval';
        $f->description = 'How often to sync Instagram posts';
        $f->addOption('everyDay', 'Daily');
        $f->addOption('every12Hours', 'Every 12 Hours');
        $f->addOption('every6Hours', 'Every 6 Hours');
        $f->value = $data['sync_interval'] ?? 'everyDay';
        $fields->add($f);

        return $fields;
    }
}




















