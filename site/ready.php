<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

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

// Load custom admin JavaScript
$wire->addHookAfter('Page::render', function($event) {
    if($this->wire('page')->template == 'admin') {
        $user = $this->wire('user');
        $process = $this->wire('process');
        if (!$user || $user->isGuest()) return;
        if ($process && $process instanceof ProcessLogin) return;
        $event->return = str_replace(
            '</body>',
            '<script src="' . $this->wire('config')->urls->templates . 'admin.js"></script></body>',
            $event->return
        );
    }
});

// Keep usage index updated on content edits.
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
