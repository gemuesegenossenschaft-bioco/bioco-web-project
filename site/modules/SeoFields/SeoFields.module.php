<?php namespace ProcessWire;

/**
 * SEO Fields Module
 * 
 * Creates and manages SEO fields for all pages.
 * Run install() to create fields, uninstall() to remove.
 */
class SeoFields extends WireData implements Module {

    public static function getModuleInfo() {
        return [
            'title' => 'SEO Fields',
            'version' => 100,
            'summary' => 'Adds SEO fields (meta title, description, OG image, robots) to pages.',
            'author' => 'biocò',
            'autoload' => false,
            'singular' => true,
        ];
    }

    /**
     * Field definitions for SEO
     */
    protected function getSeoFieldDefinitions() {
        return [
            'seo_title' => [
                'type' => 'FieldtypeText',
                'label' => 'SEO Title',
                'description' => 'Custom page title for Google/browser (50-60 characters recommended). Leave empty to use page title.',
                'maxlength' => 70,
                'collapsed' => Inputfield::collapsedBlank,
                'notes' => 'Displayed in browser tab and Google search results.',
            ],
            'seo_description' => [
                'type' => 'FieldtypeTextarea',
                'label' => 'Meta Description',
                'description' => 'Page description for search engines (150-160 characters recommended).',
                'rows' => 3,
                'collapsed' => Inputfield::collapsedBlank,
                'notes' => 'Displayed below title in Google search results.',
            ],
            'og_image' => [
                'type' => 'FieldtypeImage',
                'label' => 'Social Share Image',
                'description' => 'Image shown when page is shared on social media. Recommended: 1200x630px.',
                'maxFiles' => 1,
                'extensions' => 'jpg jpeg png webp',
                'collapsed' => Inputfield::collapsedBlank,
                'notes' => 'If empty, hero_image will be used as fallback.',
            ],
            'canonical_url' => [
                'type' => 'FieldtypeURL',
                'label' => 'Canonical URL',
                'description' => 'Override canonical URL (leave empty to use page URL).',
                'collapsed' => Inputfield::collapsedBlank,
                'notes' => 'Use only if this page content exists at another URL.',
            ],
            'robots_noindex' => [
                'type' => 'FieldtypeCheckbox',
                'label' => 'Hide from Search Engines',
                'description' => 'Check to add noindex meta tag (page will not appear in Google).',
                'collapsed' => Inputfield::collapsedBlank,
            ],
            'robots_nofollow' => [
                'type' => 'FieldtypeCheckbox',
                'label' => 'No Follow Links',
                'description' => 'Check to add nofollow meta tag (links on this page will not be followed by search engines).',
                'collapsed' => Inputfield::collapsedBlank,
            ],
        ];
    }

    /**
     * Templates to add SEO fields to
     */
    protected function getTargetTemplates() {
        return [
            'home',
            'basic-page',
            'content-page',
            'event',
            'news_item',
        ];
    }

    /**
     * Install module: create fields and add to templates
     */
    public function install() {
        $fields = wire('fields');
        $templates = wire('templates');
        
        $this->message("Installing SEO Fields module...");
        
        // Create each field
        foreach ($this->getSeoFieldDefinitions() as $fieldName => $config) {
            if ($fields->get($fieldName)) {
                $this->message("Field '{$fieldName}' already exists, skipping.");
                continue;
            }
            
            $field = new Field();
            $field->name = $fieldName;
            $field->type = wire('modules')->get($config['type']);
            $field->label = $config['label'];
            $field->description = $config['description'] ?? '';
            $field->notes = $config['notes'] ?? '';
            $field->collapsed = $config['collapsed'] ?? Inputfield::collapsedNo;
            
            // Set type-specific options
            if (isset($config['maxlength'])) $field->maxlength = $config['maxlength'];
            if (isset($config['rows'])) $field->rows = $config['rows'];
            if (isset($config['maxFiles'])) $field->maxFiles = $config['maxFiles'];
            if (isset($config['extensions'])) $field->extensions = $config['extensions'];
            
            $field->save();
            $this->message("Created field: {$fieldName}");
        }
        
        // Add fields to templates
        $seoFieldNames = array_keys($this->getSeoFieldDefinitions());
        
        foreach ($this->getTargetTemplates() as $templateName) {
            $template = $templates->get($templateName);
            if (!$template) {
                $this->warning("Template '{$templateName}' not found, skipping.");
                continue;
            }
            
            $fieldgroup = $template->fieldgroup;
            $fieldsAdded = [];
            
            foreach ($seoFieldNames as $fieldName) {
                $field = $fields->get($fieldName);
                if (!$field) continue;
                
                if ($fieldgroup->hasField($field)) {
                    continue; // Already in template
                }
                
                $fieldgroup->add($field);
                $fieldsAdded[] = $fieldName;
            }
            
            if (!empty($fieldsAdded)) {
                $fieldgroup->save();
                $this->message("Added SEO fields to template '{$templateName}': " . implode(', ', $fieldsAdded));
            }
        }
        
        $this->message("SEO Fields installation complete!");
    }

    /**
     * Uninstall module: remove fields from templates and delete fields
     */
    public function uninstall() {
        $fields = wire('fields');
        $templates = wire('templates');
        
        $this->message("Uninstalling SEO Fields module...");
        
        $seoFieldNames = array_keys($this->getSeoFieldDefinitions());
        
        // Remove fields from all templates
        foreach ($templates as $template) {
            $fieldgroup = $template->fieldgroup;
            foreach ($seoFieldNames as $fieldName) {
                $field = $fields->get($fieldName);
                if ($field && $fieldgroup->hasField($field)) {
                    $fieldgroup->remove($field);
                    $fieldgroup->save();
                    $this->message("Removed {$fieldName} from template '{$template->name}'");
                }
            }
        }
        
        // Delete fields
        foreach ($seoFieldNames as $fieldName) {
            $field = $fields->get($fieldName);
            if ($field) {
                $fields->delete($field);
                $this->message("Deleted field: {$fieldName}");
            }
        }
        
        $this->message("SEO Fields uninstallation complete!");
    }

    /**
     * Get SEO data for a page (used by API)
     */
    public static function getSeoData(Page $page) {
        $config = wire('config');
        
        // Get OG image URL
        $ogImageUrl = null;
        if ($page->hasField('og_image') && $page->og_image) {
            $ogImageUrl = $config->urls->httpRoot . ltrim($page->og_image->url, '/');
        } elseif ($page->hasField('hero_image') && $page->hero_image) {
            // Fallback to hero image
            $ogImageUrl = $config->urls->httpRoot . ltrim($page->hero_image->url, '/');
        }
        
        // Build robots string
        $robotsIndex = !($page->hasField('robots_noindex') && $page->robots_noindex);
        $robotsFollow = !($page->hasField('robots_nofollow') && $page->robots_nofollow);
        
        return [
            'title' => $page->hasField('seo_title') && $page->seo_title 
                ? $page->seo_title 
                : $page->title,
            'description' => $page->hasField('seo_description') 
                ? ($page->seo_description ?: '') 
                : '',
            'canonical' => $page->hasField('canonical_url') && $page->canonical_url 
                ? $page->canonical_url 
                : $page->httpUrl,
            'ogImage' => $ogImageUrl,
            'robots' => [
                'index' => $robotsIndex,
                'follow' => $robotsFollow,
            ],
        ];
    }
}
