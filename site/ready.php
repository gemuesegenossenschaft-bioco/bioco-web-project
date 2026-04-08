<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

// TEMP: bust opcache for module update
if (\function_exists('opcache_invalidate')) {
    foreach ([
        __DIR__ . '/modules/ProcessContentPlanning/ProcessContentPlanning.module.php',
        __DIR__ . '/modules/ProcessContentPlanning.module.php',
    ] as $path) {
        if (\is_file($path)) {
            \opcache_invalidate($path, true);
        }
    }
}

/** @var ProcessWire $wire */

/**
 * Ensure usage index table exists.
 */
function biocoEnsureMediaUsageTable() {
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

function biocoParseAssetIdFromTags($tags) {
    if (!$tags) return 0;
    if (preg_match('/(?:^|\\s)asset-(\\d+)(?:\\s|$)/', (string)$tags, $m)) {
        return (int) $m[1];
    }
    return 0;
}

function biocoComponentRegistryPath() {
    return __DIR__ . '/templates/component-registry.json';
}

function biocoComponentRegistryEntries() {
    static $entries = null;
    if ($entries !== null) return $entries;

    $entries = [];
    $path = biocoComponentRegistryPath();
    if (!is_file($path)) return $entries;

    $json = @file_get_contents($path);
    if ($json === false || $json === '') return $entries;

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) return $entries;

    foreach ($decoded as $entry) {
        if (!is_array($entry) || empty($entry['key']) || empty($entry['label'])) continue;
        $entries[] = $entry;
    }

    return $entries;
}

function biocoNormalizeComponentLookupKey($value) {
    return strtolower(trim((string) $value));
}

function biocoComponentRegistryLookup() {
    static $lookup = null;
    if ($lookup !== null) return $lookup;

    $lookup = [];
    foreach (biocoComponentRegistryEntries() as $entry) {
        $lookup[biocoNormalizeComponentLookupKey($entry['key'])] = $entry;
        if (!empty($entry['aliases']) && is_array($entry['aliases'])) {
            foreach ($entry['aliases'] as $alias) {
                $lookup[biocoNormalizeComponentLookupKey($alias)] = $entry;
            }
        }
    }
    return $lookup;
}

function biocoResolveComponentRegistryEntry($rawKey) {
    $lookupKey = biocoNormalizeComponentLookupKey($rawKey);
    if ($lookupKey === '') return null;
    $lookup = biocoComponentRegistryLookup();
    return $lookup[$lookupKey] ?? null;
}

function biocoComponentDisplayLabel($rawKey) {
    $raw = trim((string) $rawKey);
    if ($raw === '') return '';
    $entry = biocoResolveComponentRegistryEntry($raw);
    if (!$entry) return $raw;
    return $raw === $entry['key'] ? "{$entry['label']} ({$entry['key']})" : "{$entry['label']} ({$raw})";
}

function biocoEnsureField($name, $type, array $settings = []) {
    $fields = wire('fields');
    $modules = wire('modules');
    $field = $fields->get($name);
    if (!$field) {
        $field = new Field();
        $field->name = $name;
        $field->type = $modules->get($type);
    }
    foreach ($settings as $property => $value) {
        $field->$property = $value;
    }
    $fields->save($field);
    return $field;
}

function biocoEnsureSectionConfigField() {
    static $done = false;
    if ($done) return;
    $done = true;

    $field = biocoEnsureField('section_config', 'FieldtypeTextarea', [
        'label' => 'Komponenten-Konfiguration (JSON)',
        'description' => 'Wird primär durch den Visual Editor gepflegt.',
        'notes' => 'JSON-Konfiguration für Layout- und Komponentenoptionen.',
        'rows' => 8,
        'collapsed' => Inputfield::collapsedYes,
    ]);

    foreach (wire('templates') as $template) {
        if (strpos((string)$template->name, 'repeater_content_sections') !== 0) continue;
        $fieldgroup = $template->fieldgroup;
        if (!$fieldgroup || $fieldgroup->hasField($field)) continue;
        $fieldgroup->add($field);
        $fieldgroup->save();
    }
}

function biocoRenderComponentHelperMarkup($rawKey, $datalistId) {
    $sanitizer = wire('sanitizer');
    $raw = trim((string) $rawKey);
    $entry = biocoResolveComponentRegistryEntry($raw);
    $entries = biocoComponentRegistryEntries();
    $json = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $json = str_replace('</script', '<\/script', (string) $json);
    $status = $raw === '' ? 'Leer' : ($entry ? 'Mapped' : 'Unmapped');
    $statusClass = $entry ? 'is-mapped' : ($raw === '' ? 'is-empty' : 'is-unmapped');
    $name = $entry['label'] ?? 'Keine bekannte Zuordnung';
    $target = $entry
        ? (($entry['frontendTarget']['file'] ?? '') . ' · ' . ($entry['frontendTarget']['export'] ?? ''))
        : 'Keine bekannte Frontend-Zuordnung';
    $fields = !empty($entry['cmsFields']) && is_array($entry['cmsFields'])
        ? implode(', ', $entry['cmsFields'])
        : 'Keine Feldzuordnung hinterlegt';
    $notes = $entry['notes'] ?? 'Freitext-Key ohne bekannte Registry-Zuordnung.';

    $options = '';
    foreach ($entries as $registryEntry) {
        $value = $sanitizer->entities((string) ($registryEntry['key'] ?? ''));
        if ($value === '') continue;
        $label = $sanitizer->entities((string) ($registryEntry['label'] ?? ''));
        $options .= '<option value="' . $value . '">' . $label . '</option>';
    }

    return
        '<div class="bioco-component-helper" data-bioco-component-helper>' .
            '<script type="application/json" class="bioco-component-registry-json">' . $json . '</script>' .
            '<datalist id="' . $sanitizer->entities($datalistId) . '">' . $options . '</datalist>' .
            '<div class="uk-card uk-card-default" style="padding:12px;border:1px solid #d9d9d9;background:#fbfbfb">' .
                '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px">' .
                    '<strong>Frontend-Mapping</strong>' .
                    '<span class="bioco-component-helper-status ' . $statusClass . '" style="font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;background:' . ($entry ? '#dff5e3' : ($raw === '' ? '#eef2f7' : '#fde7e7')) . ';color:' . ($entry ? '#1c6b2d' : ($raw === '' ? '#475569' : '#9f1239')) . '">' . $sanitizer->entities($status) . '</span>' .
                '</div>' .
                '<div style="display:grid;gap:6px">' .
                    '<div><strong>Name:</strong> <span class="bioco-component-helper-name">' . $sanitizer->entities($name) . '</span></div>' .
                    '<div><strong>Raw key:</strong> <code class="bioco-component-helper-key">' . $sanitizer->entities($raw !== '' ? $raw : '—') . '</code></div>' .
                    '<div><strong>Frontend:</strong> <code class="bioco-component-helper-target">' . $sanitizer->entities($target) . '</code></div>' .
                    '<div><strong>CMS-Felder:</strong> <span class="bioco-component-helper-fields">' . $sanitizer->entities($fields) . '</span></div>' .
                    '<div class="bioco-component-helper-notes" style="color:#5f6b7a">' . $sanitizer->entities($notes) . '</div>' .
                '</div>' .
            '</div>' .
        '</div>';
}

function biocoUsageContext(Page $page) {
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

function biocoClearUsageRowsForPage(Page $page) {
    biocoEnsureMediaUsageTable();
    $db = wire('database');
    $ctx = biocoUsageContext($page);
    if ($ctx['repeaterItemId']) {
        $stmt = $db->prepare("DELETE FROM media_asset_usage WHERE repeater_item_id = :rid");
        $stmt->execute([':rid' => $ctx['repeaterItemId']]);
        return;
    }
    $stmt = $db->prepare("DELETE FROM media_asset_usage WHERE page_id = :pid AND repeater_item_id IS NULL");
    $stmt->execute([':pid' => $ctx['pageId']]);
}

function biocoExistingUsageMap($ctx) {
    biocoEnsureMediaUsageTable();
    $db = wire('database');
    if ($ctx['repeaterItemId']) {
        $stmt = $db->prepare("SELECT asset_id, field, file_name FROM media_asset_usage WHERE repeater_item_id = :rid");
        $stmt->execute([':rid' => $ctx['repeaterItemId']]);
    } else {
        $stmt = $db->prepare("SELECT asset_id, field, file_name FROM media_asset_usage WHERE page_id = :pid AND repeater_item_id IS NULL");
        $stmt->execute([':pid' => $ctx['pageId']]);
    }
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $map = [];
    foreach ($rows as $row) {
        $map[$row['field'] . '|' . $row['file_name']] = (int)$row['asset_id'];
    }
    return $map;
}

function biocoReindexUsageForPage(Page $page) {
    biocoEnsureMediaUsageTable();
    if (!$page->id) return;

    $db = wire('database');
    $ctx = biocoUsageContext($page);
    $existingMap = biocoExistingUsageMap($ctx);
    biocoClearUsageRowsForPage($page);
    $insert = $db->prepare("
        INSERT INTO media_asset_usage (asset_id, page_id, field, repeater_item_id, file_name)
        VALUES (:asset_id, :page_id, :field, :repeater_item_id, :file_name)
        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
    ");

    foreach ($page->template->fieldgroup as $field) {
        if (!($field->type instanceof FieldtypeImage || $field->type instanceof FieldtypeFile)) continue;
        $files = $page->get($field->name);
        if (!$files) continue;
        foreach ($files as $file) {
            $assetId = biocoParseAssetIdFromTags($file->tags ?? '');
            if (!$assetId) {
                $key = $field->name . '|' . (string)$file->name;
                $assetId = $existingMap[$key] ?? 0;
            }
            if (!$assetId) continue;
            $insert->execute([
                ':asset_id' => $assetId,
                ':page_id' => $ctx['pageId'],
                ':field' => $field->name,
                ':repeater_item_id' => $ctx['repeaterItemId'],
                ':file_name' => (string)$file->name,
            ]);
        }
    }
}

function biocoMediaUsageRows($assetId) {
    biocoEnsureMediaUsageTable();
    $db = wire('database');
    $stmt = $db->prepare("SELECT page_id, field, repeater_item_id, file_name, updated_at FROM media_asset_usage WHERE asset_id = :asset ORDER BY page_id, field");
    $stmt->execute([':asset' => (int)$assetId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
}

function biocoUsageRowExistsForFile(Page $page, $fieldName, $fileName) {
    biocoEnsureMediaUsageTable();
    $db = wire('database');
    $ctx = biocoUsageContext($page);
    if ($ctx['repeaterItemId']) {
        $stmt = $db->prepare("SELECT 1 FROM media_asset_usage WHERE page_id = :pid AND repeater_item_id = :rid AND field = :field AND file_name = :file LIMIT 1");
        $stmt->execute([
            ':pid' => $ctx['pageId'],
            ':rid' => $ctx['repeaterItemId'],
            ':field' => $fieldName,
            ':file' => $fileName,
        ]);
    } else {
        $stmt = $db->prepare("SELECT 1 FROM media_asset_usage WHERE page_id = :pid AND repeater_item_id IS NULL AND field = :field AND file_name = :file LIMIT 1");
        $stmt->execute([
            ':pid' => $ctx['pageId'],
            ':field' => $fieldName,
            ':file' => $fileName,
        ]);
    }
    return (bool)$stmt->fetchColumn();
}

function biocoIsImportedMediaFile($file, Page $page, $fieldName) {
    $assetId = biocoParseAssetIdFromTags($file->tags ?? '');
    if ($assetId > 0) return true;
    return biocoUsageRowExistsForFile($page, $fieldName, (string)$file->name);
}

function biocoRenderUsageMarkup($assetId) {
    $rows = biocoMediaUsageRows($assetId);
    if (!count($rows)) {
        return "<div class='uk-text-muted'>Used in: none</div>";
    }
    $adminUrl = wire('config')->urls->admin;
    $pages = wire('pages');
    $items = [];
    foreach ($rows as $row) {
        $page = $pages->get((int)$row['page_id']);
        $title = $page->id ? htmlspecialchars($page->title, ENT_QUOTES, 'UTF-8') : '(deleted page)';
        $path = $page->id ? htmlspecialchars($page->path, ENT_QUOTES, 'UTF-8') : '';
        $field = htmlspecialchars($row['field'], ENT_QUOTES, 'UTF-8');
        $repeater = $row['repeater_item_id'] ? " repeater#" . (int)$row['repeater_item_id'] : '';
        $link = $page->id ? "<a href='{$adminUrl}page/edit/?id={$page->id}' target='_blank'>{$title}</a>" : $title;
        $items[] = "<li>{$link} <small>{$path}</small> <code>{$field}{$repeater}</code></li>";
    }
    return "<div><strong>Used in:</strong><ul>" . implode('', $items) . "</ul></div>";
}

function biocoExtractSectionTitleFromHtml($html) {
    $source = (string) $html;
    if ($source === '') return '';

    // Prefer first heading to keep repeater labels aligned with authored structure.
    if (preg_match('/<h[1-3]\\b[^>]*>(.*?)<\\/h[1-3]>/is', $source, $m)) {
        $heading = trim(preg_replace('/\\s+/u', ' ', strip_tags($m[1])));
        if ($heading !== '') return mb_substr($heading, 0, 120);
    }

    $plain = trim(preg_replace('/\\s+/u', ' ', strip_tags(html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    if ($plain === '') return '';

    $sentences = preg_split('/(?<=[\\.!\\?])\\s+/u', $plain) ?: [];
    $first = trim((string) ($sentences[0] ?? ''));
    if ($first !== '') return mb_substr($first, 0, 120);

    return mb_substr($plain, 0, 120);
}

function biocoDomInnerHtml(\DOMNode $node) {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    return $html;
}

function biocoSetDomNodeText(\DOMNode $node, $text) {
    while ($node->firstChild) {
        $node->removeChild($node->firstChild);
    }
    $node->appendChild($node->ownerDocument->createTextNode($text));
}

function biocoMutateAdminNavMarkup($markup) {
    $markup = (string) $markup;
    if (trim($markup) === '') return $markup;

    $config = wire('config');
    $visualEditorUrl = $config->urls->root . 'visual-editor/';

    $previous = libxml_use_internal_errors(true);
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $html = '<!DOCTYPE html><html><body><ul id="bioco-nav-root">' . $markup . '</ul></body></html>';
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) return $markup;

    $xpath = new \DOMXPath($dom);
    $root = $xpath->query('//ul[@id="bioco-nav-root"]')->item(0);
    if (!$root) return $markup;

    $mediaItem = null;
    $mediaAnchor = null;
    $hasVisualEditor = false;

    foreach ($xpath->query('./li', $root) as $item) {
        $anchor = $xpath->query('.//a[1]', $item)->item(0);
        if (!$anchor) continue;

        $href = strtolower((string) $anchor->getAttribute('href'));
        $text = trim(preg_replace('/\s+/u', ' ', (string) $anchor->textContent));
        $textLower = mb_strtolower($text);

        if (strpos($href, '/visual-editor/') !== false) {
            $hasVisualEditor = true;
            $anchor->setAttribute('class', trim($anchor->getAttribute('class') . ' bioco-visual-editor-link'));
            biocoSetDomNodeText($anchor, 'Visual Editor');
        }

        if (!$mediaItem && (strpos($href, '/media/') !== false || $textLower === 'media' || $textLower === 'medien')) {
            $mediaItem = $item;
            $mediaAnchor = $anchor;
        }

        if (strpos($href, '/content-planning/') !== false || $textLower === 'content planning' || $textLower === 'plan & bugs') {
            biocoSetDomNodeText($anchor, 'Plan & Bugs');
        }
    }

    if (!$hasVisualEditor) {
        $visualItem = $dom->createElement('li');
        $visualItem->setAttribute('class', 'page-bioco-visual-editor');

        $visualAnchor = $dom->createElement('a', 'Visual Editor');
        $visualAnchor->setAttribute('href', $visualEditorUrl);
        $visualAnchor->setAttribute('target', '_blank');
        $visualAnchor->setAttribute('rel', 'noopener noreferrer');
        $visualAnchor->setAttribute('class', 'bioco-visual-editor-link');

        if ($mediaAnchor && $mediaAnchor->hasAttribute('class')) {
            $visualAnchor->setAttribute('class', trim($mediaAnchor->getAttribute('class') . ' bioco-visual-editor-link'));
        }

        $visualItem->appendChild($visualAnchor);

        if ($mediaItem && $mediaItem->parentNode === $root) {
            if ($mediaItem->nextSibling) {
                $root->insertBefore($visualItem, $mediaItem->nextSibling);
            } else {
                $root->appendChild($visualItem);
            }
        } else {
            $root->appendChild($visualItem);
        }
    }

    return biocoDomInnerHtml($root);
}

biocoEnsureSectionConfigField();

// Load custom admin JavaScript
$wire->addHookAfter('Page::render', function($event) {
    if($this->wire('page')->template == 'admin') {
        $user = $this->wire('user');
        $process = $this->wire('process');
        if (!$user || $user->isGuest()) return;
        if ($process && $process instanceof ProcessLogin) return;
        $adminJsPath = $this->wire('config')->paths->templates . 'admin.js';
        $adminJsUrl = $this->wire('config')->urls->templates . 'admin.js';
        if (is_file($adminJsPath)) {
            $adminJsUrl .= '?v=' . filemtime($adminJsPath);
        }
        $event->return = str_replace(
            '</body>',
            '<script src="' . $adminJsUrl . '"></script></body>',
            $event->return
        );
    }
});

$wire->addHookAfter('AdminThemeFramework::renderPrimaryNavItems', function($event) {
    $event->return = biocoMutateAdminNavMarkup($event->return);
});

$wire->addHookAfter('AdminThemeFramework::renderSidebarNavItems', function($event) {
    $event->return = biocoMutateAdminNavMarkup($event->return);
});

$wire->addHookBefore('ProcessPageEdit::execute', function($event) {
    $input = wire('input');
    $pageId = (int) ($input->get('id') ?: 0);
    if (!$pageId) return;

    $target = wire('pages')->get($pageId);
    if (!$target->id || !$target->template || $target->template->name !== 'visual-editor') return;

    wire('session')->redirect($target->url);
});

// Keep usage index updated on content edits.
$wire->addHookBefore('Pages::saveReady', function($event) {
    $page = $event->arguments(0);
    if (!$page instanceof Page) return;
    if (strpos($page->template->name, 'repeater_content_sections') !== 0) return;
    if (!$page->hasField('section_text') || !$page->hasField('section_title')) return;

    $nextTitle = biocoExtractSectionTitleFromHtml((string) $page->get('section_text'));
    if ($nextTitle === '') return;

    $currentTitle = trim((string) $page->get('section_title'));
    if ($currentTitle === $nextTitle) return;

    $page->of(false);
    $page->set('section_title', $nextTitle);
});

$wire->addHookBefore('Pages::saveReady', function($event) {
    if (!empty(wire('config')->biocoMediaImportInProgress)) return;

    $page = $event->arguments(0);
    if (!$page instanceof Page || !$page->id) return;
    if ($page->template->name === 'admin' || $page->template->name === 'MediaLibrary') return;

    $original = wire('pages')->get((int)$page->id);
    if (!$original->id) return;

    foreach ($page->template->fieldgroup as $field) {
        if (!($field->type instanceof FieldtypeImage || $field->type instanceof FieldtypeFile)) continue;

        $currentFiles = $page->get($field->name);
        if (!$currentFiles) continue;
        $oldFiles = $original->get($field->name);

        $oldByName = [];
        if ($oldFiles) {
            foreach ($oldFiles as $oldFile) {
                $oldByName[(string)$oldFile->name] = true;
            }
        }

        foreach ($currentFiles as $file) {
            $name = (string)$file->name;
            if (!$name || isset($oldByName[$name])) continue;
            if (biocoIsImportedMediaFile($file, $page, $field->name)) continue;
            throw new WireException("Direct upload is disabled for page media fields. Upload in Media Library and select it via 'Media Library'. Field '{$field->name}', file '{$name}'.");
        }
    }
});

$wire->addHookAfter('Pages::saved', function($event) {
    $page = $event->arguments(0);
    if (!$page instanceof Page || !$page->id) return;
    if ($page->template->name === 'admin') return;
    biocoReindexUsageForPage($page);
});

$wire->addHookAfter('Pages::deleted', function($event) {
    $page = $event->arguments(0);
    if (!$page instanceof Page) return;
    biocoClearUsageRowsForPage($page);
});

// Block deletion of referenced media assets.
$wire->addHookBefore('Pages::deleteReady', function($event) {
    $page = $event->arguments(0);
    if (!$page instanceof Page || !$page->id) return;
    if ($page->template->name !== 'MediaLibrary') return;
    $rows = biocoMediaUsageRows((int)$page->id);
    if (!count($rows)) return;
    throw new WireException("Cannot delete media asset #{$page->id}. It is referenced in " . count($rows) . " location(s).");
});

// Show usage on media asset edit screen.
$wire->addHookAfter('ProcessPageEdit::buildForm', function($event) {
    $process = $event->object;
    $edited = $process->getPage();
    if (!$edited instanceof Page || !$edited->id) return;
    if ($edited->template->name !== 'MediaLibrary') return;
    $form = $event->return;
    if (!$form) return;

    $modules = wire('modules');
    $f = $modules->get('InputfieldMarkup');
    if (!$f) return;
    $f->attr('name', 'bioco_media_usage');
    $f->label = 'Usage';
    $f->value = biocoRenderUsageMarkup((int)$edited->id);
    $form->add($f);
});

// Collapse advanced fields in repeater items for cleaner editor UX.
$wire->addHookAfter('ProcessPageEdit::buildForm', function($event) {
    $form = $event->return;
    if (!$form) return;
    $process = $event->object;
    $page = $process->getPage();
    if (!$page instanceof Page || !$page->id) return;
    if (strpos($page->template->name, 'repeater_content_sections') !== 0) return;

    $modules = wire('modules');
    $componentField = $form->getChildByName('section_component');
    if ($componentField) {
        $componentField->label = 'Frontend-Komponente';
        $componentField->notes = 'Freier Key. Bekannte Komponenten und ihre Frontend-Zuordnung werden unten angezeigt.';
        $datalistId = 'bioco-component-registry-' . (int) $page->id;
        $componentField->attr('list', $datalistId);

        $helper = $modules->get('InputfieldMarkup');
        if ($helper) {
            $helper->attr('name', '_bioco_component_helper');
            $helper->label = '';
            $helper->value = biocoRenderComponentHelperMarkup((string) $page->get('section_component'), $datalistId);
            if (method_exists($form, 'insertAfter')) {
                $form->insertAfter($helper, $componentField);
            } else {
                $form->add($helper);
            }
        }
    }

    $configField = $form->getChildByName('section_config');
    if ($configField) {
        $configField->label = 'Komponenten-Konfiguration (JSON)';
        $configField->notes = 'Wird primär durch den Visual Editor gepflegt.';
    }

    $advancedFields = [
        'section_id', 'section_config', 'section_bg_color', 'section_image_overlay',
        'section_image_brightness', 'section_image_contrast', 'section_image_saturate',
        'section_video_url', 'section_video_title',
        'button2_text', 'button2_href', 'button2_variant',
    ];
    $fieldset = $modules->get('InputfieldFieldset');
    $fieldset->label = 'Erweitert';
    $fieldset->collapsed = \ProcessWire\Inputfield::collapsedYes;
    $fieldset->attr('name', '_bioco_advanced');

    $moved = false;
    foreach ($advancedFields as $name) {
        $f = $form->getChildByName($name);
        if ($f) {
            $form->remove($f);
            $fieldset->add($f);
            $moved = true;
        }
    }
    if ($moved) {
        $form->add($fieldset);
    }
});

function biocoNextPathFromPage(Page $page): string {
    $path = '/' . trim((string)$page->path, '/');
    if ($path === '/') return '/';

    if (strpos($path, '/content/') === 0) {
        $path = '/' . ltrim(substr($path, strlen('/content/')), '/');
    }

    $slug = trim($path, '/');
    if ($slug === '' || $slug === 'home' || $slug === 'homepage') return '/';
    return '/' . $slug;
}

function biocoNextRevalidateConfig(): array {
    static $cfg = null;
    if (is_array($cfg)) return $cfg;

    $config = wire('config');
    $secret = trim((string)($config->nextRevalidateSecret ?? ''));
    if ($secret === '') {
        $secret = trim((string)(getenv('NEXT_REVALIDATE_SECRET') ?: getenv('REVALIDATE_SECRET') ?: ''));
    }

    $url = trim((string)($config->nextRevalidateUrl ?? ''));
    if ($url === '') {
        $url = trim((string)(getenv('NEXT_REVALIDATE_URL') ?: 'http://127.0.0.1:49154/api/revalidate'));
    }

    $debounceSeconds = (int)($config->nextRevalidateDebounceSeconds ?? 10);
    if ($debounceSeconds < 0) $debounceSeconds = 0;

    $maxWaitSeconds = (int)($config->nextRevalidateMaxWaitSeconds ?? 45);
    if ($maxWaitSeconds < 1) $maxWaitSeconds = 1;
    if ($maxWaitSeconds < $debounceSeconds) $maxWaitSeconds = $debounceSeconds;

    $queueFile = trim((string)($config->nextRevalidateQueueFile ?? '/tmp/bioco-next-revalidate-state.json'));
    if ($queueFile === '') {
        $queueFile = '/tmp/bioco-next-revalidate-state.json';
    }

    $cfg = [
        'secret' => $secret,
        'url' => $url,
        'debounceSeconds' => $debounceSeconds,
        'maxWaitSeconds' => $maxWaitSeconds,
        'queueFile' => $queueFile,
    ];
    return $cfg;
}

function biocoNextRevalidateDefaultState(): array {
    return [
        'lastDispatchAt' => 0,
        'firstPendingAt' => 0,
        'pendingPaths' => [],
        'pendingTags' => [],
        'pendingLayout' => false,
    ];
}

function biocoNextRevalidateSanitizePaths($paths): array {
    if (!is_array($paths)) return [];
    $seen = [];
    $clean = [];
    foreach ($paths as $rawPath) {
        if (!is_string($rawPath)) continue;
        $path = trim($rawPath);
        if ($path === '') continue;
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
            if ($path === '') $path = '/';
        }
        if (isset($seen[$path])) continue;
        $seen[$path] = true;
        $clean[] = $path;
    }
    return $clean;
}

function biocoNextRevalidateSanitizeTags($tags): array {
    if (!is_array($tags)) return [];
    $seen = [];
    $clean = [];
    foreach ($tags as $rawTag) {
        if (!is_string($rawTag)) continue;
        $tag = trim($rawTag);
        if ($tag === '' || isset($seen[$tag])) continue;
        $seen[$tag] = true;
        $clean[] = $tag;
    }
    return $clean;
}

function biocoNextRevalidateNormalizeState($state): array {
    $defaults = biocoNextRevalidateDefaultState();
    if (!is_array($state)) return $defaults;
    return [
        'lastDispatchAt' => max(0, (int)($state['lastDispatchAt'] ?? 0)),
        'firstPendingAt' => max(0, (int)($state['firstPendingAt'] ?? 0)),
        'pendingPaths' => biocoNextRevalidateSanitizePaths($state['pendingPaths'] ?? []),
        'pendingTags' => biocoNextRevalidateSanitizeTags($state['pendingTags'] ?? []),
        'pendingLayout' => (bool)($state['pendingLayout'] ?? false),
    ];
}

function biocoNextRevalidateReadState($fh): array {
    rewind($fh);
    $raw = stream_get_contents($fh);
    if (!is_string($raw) || trim($raw) === '') {
        return biocoNextRevalidateDefaultState();
    }
    $decoded = json_decode($raw, true);
    return biocoNextRevalidateNormalizeState($decoded);
}

function biocoNextRevalidateWriteState($fh, array $state): void {
    $normalized = biocoNextRevalidateNormalizeState($state);
    $json = json_encode($normalized, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        $json = json_encode(biocoNextRevalidateDefaultState(), JSON_UNESCAPED_SLASHES);
    }
    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, $json ?: '{}');
    fflush($fh);
}

function biocoDispatchRevalidatePayload(array $payload): bool {
    $cfg = biocoNextRevalidateConfig();
    if ($cfg['secret'] === '' || $cfg['url'] === '') return false;

    $payload['secret'] = $cfg['secret'];
    $ch = curl_init($cfg['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_RETURNTRANSFER => true,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        wire('log')->save('next-revalidate', 'cURL failed: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400) {
        wire('log')->save('next-revalidate', "HTTP {$status} from {$cfg['url']}: {$response}");
        return false;
    }

    return true;
}

function biocoNextRevalidateFlushQueue(bool $force = false): bool {
    $cfg = biocoNextRevalidateConfig();
    if ($cfg['secret'] === '') return false;

    $dir = dirname($cfg['queueFile']);
    if ($dir && !is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $fh = @fopen($cfg['queueFile'], 'c+');
    if (!$fh) {
        wire('log')->save('next-revalidate', "Cannot open queue file: {$cfg['queueFile']}");
        return false;
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return false;
    }

    $state = biocoNextRevalidateReadState($fh);
    $now = time();
    $hasPending = count($state['pendingPaths']) > 0 || count($state['pendingTags']) > 0 || $state['pendingLayout'];
    if (!$hasPending) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return false;
    }

    $sinceLastDispatch = $now - (int)$state['lastDispatchAt'];
    $sinceFirstPending = $state['firstPendingAt'] > 0 ? $now - (int)$state['firstPendingAt'] : 0;
    $isDue = $force
        || $cfg['debounceSeconds'] === 0
        || $sinceLastDispatch >= $cfg['debounceSeconds']
        || ($state['firstPendingAt'] > 0 && $sinceFirstPending >= $cfg['maxWaitSeconds']);

    if (!$isDue) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return false;
    }

    $payload = [
        'paths' => $state['pendingPaths'],
        'tags' => $state['pendingTags'],
        'layout' => (bool)$state['pendingLayout'],
    ];
    $ok = biocoDispatchRevalidatePayload($payload);
    $state['lastDispatchAt'] = $now;
    if ($ok) {
        $state['firstPendingAt'] = 0;
        $state['pendingPaths'] = [];
        $state['pendingTags'] = [];
        $state['pendingLayout'] = false;
    }

    biocoNextRevalidateWriteState($fh, $state);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $ok;
}

function biocoQueueRevalidateRequest(array $paths, array $tags = ['cms'], bool $layout = true): void {
    $cfg = biocoNextRevalidateConfig();
    if ($cfg['secret'] === '') {
        static $secretWarningLogged = false;
        if (!$secretWarningLogged) {
            wire('log')->save('next-revalidate', 'Missing next revalidate secret, skipping queue.');
            $secretWarningLogged = true;
        }
        return;
    }

    $dir = dirname($cfg['queueFile']);
    if ($dir && !is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $fh = @fopen($cfg['queueFile'], 'c+');
    if (!$fh) {
        wire('log')->save('next-revalidate', "Cannot open queue file: {$cfg['queueFile']}");
        return;
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return;
    }

    $state = biocoNextRevalidateReadState($fh);
    $now = time();
    $hadPending = count($state['pendingPaths']) > 0 || count($state['pendingTags']) > 0 || $state['pendingLayout'];

    $state['pendingPaths'] = biocoNextRevalidateSanitizePaths(array_merge($state['pendingPaths'], $paths));
    $state['pendingTags'] = biocoNextRevalidateSanitizeTags(array_merge($state['pendingTags'], $tags));
    $state['pendingLayout'] = (bool)$state['pendingLayout'] || $layout;

    $hasPending = count($state['pendingPaths']) > 0 || count($state['pendingTags']) > 0 || $state['pendingLayout'];
    if ($hasPending && (!$hadPending || $state['firstPendingAt'] <= 0)) {
        $state['firstPendingAt'] = $now;
    }
    if (!$hasPending) {
        $state['firstPendingAt'] = 0;
    }

    biocoNextRevalidateWriteState($fh, $state);
    flock($fh, LOCK_UN);
    fclose($fh);

    biocoNextRevalidateFlushQueue(false);
}

// On-demand ISR revalidation: enqueue CMS changes, coalesce bursts, flush trailing.
$wire->addHookAfter('Pages::saved', function($event) {
    $page = $event->arguments(0);
    if (!$page instanceof Page || !$page->id) return;
    $skip = ['admin', 'api', 'MediaLibrary'];
    if (in_array($page->template->name, $skip)) return;

    // For repeater items, revalidate the parent page.
    if (strpos($page->template->name, 'repeater_') === 0) {
        if (method_exists($page, 'getForPage')) {
            $parent = $page->getForPage();
            if ($parent && $parent->id) $page = $parent;
            else return;
        } else return;
    }

    $paths = array_values(array_unique(array_filter([
        biocoNextPathFromPage($page),
        '/',
    ])));

    biocoQueueRevalidateRequest($paths, ['cms'], true);
}, ['priority' => 100]);

$wire->addHookAfter('LazyCron::everyMinute', function() {
    biocoNextRevalidateFlushQueue(false);
});
