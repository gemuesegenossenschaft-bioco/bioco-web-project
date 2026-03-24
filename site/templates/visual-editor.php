<?php namespace ProcessWire;

/**
 * Visual Editor: iframe-based WYSIWYG section editor.
 * Outputs a full HTML page and exits, bypassing PW admin chrome.
 */

// Bypass PW output buffering: clean all buffers, output directly, then exit.
while (ob_get_level()) ob_end_clean();

if (!$user->isLoggedin() || $user->isGuest()) {
    header('Location: ' . $config->urls->admin . 'login/');
    exit;
}

$siteUrl = rtrim(getenv('NEXT_PUBLIC_SITE_URL') ?: 'https://bioco.ch', '/');
$draftSecret = getenv('PW_PREVIEW_TOKEN') ?: '';

// Build page list: all pages with content_sections + homepage
$contentPages = [];
$home = $pages->get('/');
if ($home->id) {
    $contentPages[] = [
        'id' => $home->id,
        'title' => $home->title ?: 'Startseite',
        'path' => '/',
        'template' => $home->template->name,
    ];
}

// Find all pages that have content_sections field
$sectionPages = $pages->find("has_field=content_sections, template!=admin, id!={$home->id}, sort=sort");
foreach ($sectionPages as $p) {
    $path = '/' . trim($p->path, '/');
    $contentPages[] = [
        'id' => $p->id,
        'title' => $p->title,
        'path' => $path,
        'template' => $p->template->name,
    ];
}

$pagesJson = json_encode($contentPages, JSON_UNESCAPED_UNICODE);
$apiRoot = $config->urls->root . 'api/';

header('Content-Type: text/html; charset=UTF-8');

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Visual Editor</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    background: #1a1a2e;
    color: #e0e0e0;
    height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.ve-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    background: #16213e;
    border-bottom: 1px solid #0f3460;
    flex-shrink: 0;
    z-index: 100;
}

.ve-toolbar-logo {
    font-weight: 700;
    font-size: 14px;
    color: #4a7c59;
    white-space: nowrap;
}

.ve-toolbar select {
    background: #0f3460;
    color: #e0e0e0;
    border: 1px solid #1a1a4e;
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 13px;
    min-width: 220px;
}

.ve-toolbar-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
}

.ve-btn {
    background: #0f3460;
    color: #e0e0e0;
    border: 1px solid #1a1a4e;
    border-radius: 6px;
    padding: 6px 14px;
    font-size: 13px;
    cursor: pointer;
    transition: background 0.15s;
}
.ve-btn:hover { background: #1a1a4e; }
.ve-btn-primary { background: #4a7c59; border-color: #3a6c49; color: #fff; }
.ve-btn-primary:hover { background: #3a6c49; }
.ve-btn-danger { background: #8b2252; border-color: #6b1242; }
.ve-btn-danger:hover { background: #6b1242; }
.ve-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.ve-main {
    display: flex;
    flex: 1;
    overflow: hidden;
}

.ve-sidebar {
    width: 380px;
    background: #16213e;
    border-right: 1px solid #0f3460;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex-shrink: 0;
}

.ve-sidebar-header {
    padding: 12px 16px;
    border-bottom: 1px solid #0f3460;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ve-sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.ve-section-list {
    list-style: none;
}

.ve-section-item {
    padding: 10px 16px;
    border-bottom: 1px solid #0f3460;
    cursor: pointer;
    transition: background 0.1s;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ve-section-item:hover { background: #1a1a4e; }
.ve-section-item.is-active { background: #0f3460; border-left: 3px solid #4a7c59; }

.ve-section-drag {
    cursor: grab;
    color: #666;
    font-size: 16px;
    user-select: none;
}
.ve-section-drag:active { cursor: grabbing; }

.ve-section-info {
    flex: 1;
    min-width: 0;
}

.ve-section-title {
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ve-section-meta {
    font-size: 11px;
    color: #888;
    margin-top: 2px;
}

.ve-section-actions {
    display: flex;
    gap: 4px;
}

.ve-section-actions button {
    background: none;
    border: none;
    color: #888;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
}
.ve-section-actions button:hover { background: #1a1a4e; color: #e0e0e0; }

/* Field editor panel (shown when section selected) */
.ve-field-editor {
    padding: 16px;
}

.ve-field-group {
    margin-bottom: 16px;
}

.ve-field-group label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #888;
    margin-bottom: 4px;
}

.ve-field-group input,
.ve-field-group select,
.ve-field-group textarea {
    width: 100%;
    background: #0f3460;
    color: #e0e0e0;
    border: 1px solid #1a1a4e;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 13px;
    font-family: inherit;
}

.ve-field-group textarea {
    min-height: 120px;
    resize: vertical;
}

.ve-iframe-wrap {
    flex: 1;
    position: relative;
    background: #fff;
}

.ve-iframe-wrap iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.ve-status {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    background: #333;
}
.ve-status.is-ready { background: #2d5a3d; color: #8fdf8f; }
.ve-status.is-loading { background: #5a4a2d; color: #dfcf8f; }
.ve-status.is-error { background: #5a2d2d; color: #df8f8f; }

.ve-empty-state {
    padding: 40px 20px;
    text-align: center;
    color: #666;
    font-size: 13px;
}

/* Layout labels in German */
.ve-layout-badge {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 4px;
    background: #1a1a4e;
    color: #aaa;
    white-space: nowrap;
}
</style>
</head>
<body>

<div class="ve-toolbar">
    <span class="ve-toolbar-logo">bioco Visual Editor</span>
    <select id="ve-page-select">
        <option value="">Seite wählen...</option>
    </select>
    <span id="ve-status" class="ve-status">Nicht verbunden</span>
    <div class="ve-toolbar-actions">
        <button class="ve-btn" id="ve-btn-refresh" title="Vorschau neu laden">↻ Aktualisieren</button>
        <button class="ve-btn" id="ve-btn-pw" title="In ProcessWire öffnen">PW Admin</button>
        <a href="<?= $config->urls->admin ?>" class="ve-btn">← Zurück</a>
    </div>
</div>

<div class="ve-main">
    <div class="ve-sidebar">
        <div class="ve-sidebar-header">
            <span>Inhaltsbereiche</span>
            <button class="ve-btn ve-btn-primary" id="ve-btn-add" disabled>+ Neu</button>
        </div>
        <div class="ve-sidebar-content">
            <div class="ve-empty-state" id="ve-empty">Seite wählen, um Abschnitte zu sehen.</div>
            <ul class="ve-section-list" id="ve-section-list"></ul>
        </div>
    </div>
    <div class="ve-iframe-wrap">
        <iframe id="ve-iframe" src="about:blank"></iframe>
    </div>
</div>

<script>
(function() {
    var PREFIX = 'bioco:visual-editor:';
    var SITE_URL = <?= json_encode($siteUrl) ?>;
    var DRAFT_SECRET = <?= json_encode($draftSecret) ?>;
    var API_ROOT = <?= json_encode($apiRoot) ?>;
    var ALL_PAGES = <?= $pagesJson ?>;
    var LAYOUT_LABELS = {
        split_media_text: 'Bild + Text',
        split_text_media: 'Text + Bild',
        full_width_banner: 'Banner',
        media_grid: 'Bildergalerie',
        video_embed: 'Video',
        rich_text: 'Nur Text',
        component: 'Komponente',
    };

    var iframe = document.getElementById('ve-iframe');
    var pageSelect = document.getElementById('ve-page-select');
    var sectionList = document.getElementById('ve-section-list');
    var statusEl = document.getElementById('ve-status');
    var emptyEl = document.getElementById('ve-empty');
    var btnAdd = document.getElementById('ve-btn-add');
    var btnRefresh = document.getElementById('ve-btn-refresh');
    var btnPw = document.getElementById('ve-btn-pw');

    var currentPageId = null;
    var currentPath = null;
    var sections = [];
    var activeSectionId = null;
    var iframeReady = false;

    // Populate page selector
    ALL_PAGES.forEach(function(p) {
        var opt = document.createElement('option');
        opt.value = p.id + '|' + p.path;
        opt.textContent = p.title + ' (' + p.path + ')';
        pageSelect.appendChild(opt);
    });

    function setStatus(text, cls) {
        statusEl.textContent = text;
        statusEl.className = 've-status ' + (cls || '');
    }

    function loadPage(pageId, path) {
        currentPageId = pageId;
        currentPath = path;
        iframeReady = false;
        sections = [];
        activeSectionId = null;
        setStatus('Laden...', 'is-loading');
        btnAdd.disabled = true;

        var url = SITE_URL + path;
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        url += sep + '_visual=1';
        if (DRAFT_SECRET) {
            url += '&draft_secret=' + encodeURIComponent(DRAFT_SECRET);
        }
        iframe.src = url;
        renderSectionList();
    }

    pageSelect.addEventListener('change', function() {
        var val = this.value;
        if (!val) return;
        var parts = val.split('|');
        loadPage(parseInt(parts[0], 10), parts[1]);
    });

    btnRefresh.addEventListener('click', function() {
        if (currentPageId) loadPage(currentPageId, currentPath);
    });

    btnPw.addEventListener('click', function() {
        if (currentPageId) {
            window.open('<?= $config->urls->admin ?>page/edit/?id=' + currentPageId, '_blank');
        }
    });

    // Listen for postMessage from iframe
    window.addEventListener('message', function(event) {
        var data = event.data;
        if (!data || typeof data.type !== 'string' || data.type.indexOf(PREFIX) !== 0) return;
        var action = data.type.slice(PREFIX.length);

        switch (action) {
            case 'ready':
                iframeReady = true;
                setStatus('Verbunden', 'is-ready');
                btnAdd.disabled = false;
                // Fetch sections from API
                fetchSections();
                break;

            case 'section-click':
                activeSectionId = data.sectionId || null;
                renderSectionList();
                // Highlight in iframe
                sendToIframe('section-highlight', { sectionId: data.sectionId });
                break;
        }
    });

    function sendToIframe(action, data) {
        if (!iframeReady || !iframe.contentWindow) return;
        iframe.contentWindow.postMessage(
            Object.assign({ type: PREFIX + action }, data),
            '*'
        );
    }

    function fetchSections() {
        if (!currentPath) return;
        var pageName = currentPath === '/' ? 'homepage' : currentPath.replace(/^\//, '').replace(/\/$/, '');
        var endpoint = currentPath === '/'
            ? API_ROOT + 'content/homepage'
            : API_ROOT + 'content/sections/' + encodeURIComponent(pageName);

        fetch(endpoint)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                sections = data.sections || [];
                renderSectionList();
            })
            .catch(function() {
                setStatus('Fehler beim Laden', 'is-error');
            });
    }

    function renderSectionList() {
        sectionList.innerHTML = '';
        emptyEl.style.display = sections.length ? 'none' : 'block';

        sections.forEach(function(sec, idx) {
            var li = document.createElement('li');
            li.className = 've-section-item' + (sec.id === activeSectionId ? ' is-active' : '');
            li.setAttribute('data-pw-id', sec.pwId || '');
            li.setAttribute('draggable', 'true');

            var drag = document.createElement('span');
            drag.className = 've-section-drag';
            drag.textContent = '⠿';

            var info = document.createElement('div');
            info.className = 've-section-info';

            var title = document.createElement('div');
            title.className = 've-section-title';
            title.textContent = sec.title || '(kein Titel)';

            var meta = document.createElement('div');
            meta.className = 've-section-meta';

            var badge = document.createElement('span');
            badge.className = 've-layout-badge';
            badge.textContent = LAYOUT_LABELS[sec.layout] || sec.layout;
            meta.appendChild(badge);

            if (sec.component) {
                var compBadge = document.createElement('span');
                compBadge.className = 've-layout-badge';
                compBadge.style.marginLeft = '4px';
                compBadge.textContent = sec.component;
                meta.appendChild(compBadge);
            }

            info.appendChild(title);
            info.appendChild(meta);

            var actions = document.createElement('div');
            actions.className = 've-section-actions';

            var editBtn = document.createElement('button');
            editBtn.textContent = '✎';
            editBtn.title = 'In PW bearbeiten';
            editBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (sec.pwId) {
                    window.open('<?= $config->urls->admin ?>page/edit/?id=' + sec.pwId, '_blank');
                }
            });

            var delBtn = document.createElement('button');
            delBtn.textContent = '✕';
            delBtn.title = 'Löschen';
            delBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!confirm('Abschnitt "' + (sec.title || '') + '" wirklich löschen?')) return;
                deleteSection(sec.pwId);
            });

            actions.appendChild(editBtn);
            actions.appendChild(delBtn);

            li.appendChild(drag);
            li.appendChild(info);
            li.appendChild(actions);

            li.addEventListener('click', function() {
                activeSectionId = sec.id;
                renderSectionList();
                sendToIframe('section-highlight', { sectionId: sec.id });
                // Scroll to section in iframe
                sendToIframe('section-scroll', { sectionId: sec.id });
            });

            // Drag and drop reordering
            li.addEventListener('dragstart', function(e) {
                e.dataTransfer.setData('text/plain', idx.toString());
                li.style.opacity = '0.5';
            });
            li.addEventListener('dragend', function() {
                li.style.opacity = '1';
            });
            li.addEventListener('dragover', function(e) {
                e.preventDefault();
                li.style.borderTop = '2px solid #4a7c59';
            });
            li.addEventListener('dragleave', function() {
                li.style.borderTop = '';
            });
            li.addEventListener('drop', function(e) {
                e.preventDefault();
                li.style.borderTop = '';
                var fromIdx = parseInt(e.dataTransfer.getData('text/plain'), 10);
                if (isNaN(fromIdx) || fromIdx === idx) return;
                reorderSections(fromIdx, idx);
            });

            sectionList.appendChild(li);
        });
    }

    function reorderSections(fromIdx, toIdx) {
        if (!currentPageId) return;
        var item = sections.splice(fromIdx, 1)[0];
        sections.splice(toIdx, 0, item);
        renderSectionList();

        var order = sections.map(function(s) { return s.pwId; });
        fetch(API_ROOT + 'sections-reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ pageId: currentPageId, order: order }),
        }).then(function(r) { return r.json(); })
          .then(function(res) {
              if (res.success) {
                  // Reload iframe to reflect new order
                  if (currentPageId) loadPage(currentPageId, currentPath);
              }
          })
          .catch(function() { setStatus('Fehler beim Sortieren', 'is-error'); });
    }

    btnAdd.addEventListener('click', function() {
        if (!currentPageId) return;
        var layout = prompt('Layout wählen:\n\n1. Bild + Text\n2. Text + Bild\n3. Banner\n4. Bildergalerie\n5. Video\n6. Nur Text\n7. Komponente\n\n(Nummer eingeben)', '6');
        var layouts = {
            '1': 'split_media_text', '2': 'split_text_media',
            '3': 'full_width_banner', '4': 'media_grid',
            '5': 'video_embed', '6': 'rich_text', '7': 'component',
        };
        var chosen = layouts[layout] || 'rich_text';

        fetch(API_ROOT + 'sections-add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ pageId: currentPageId, layout: chosen }),
        }).then(function(r) { return r.json(); })
          .then(function(res) {
              if (res.success && res.section) {
                  sections.push(res.section);
                  renderSectionList();
                  loadPage(currentPageId, currentPath);
              }
          })
          .catch(function() { setStatus('Fehler beim Hinzufügen', 'is-error'); });
    });

    function deleteSection(pwId) {
        if (!currentPageId || !pwId) return;
        fetch(API_ROOT + 'sections-delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ pageId: currentPageId, sectionPwId: pwId }),
        }).then(function(r) { return r.json(); })
          .then(function(res) {
              if (res.success) {
                  sections = sections.filter(function(s) { return s.pwId !== pwId; });
                  renderSectionList();
                  loadPage(currentPageId, currentPath);
              }
          })
          .catch(function() { setStatus('Fehler beim Löschen', 'is-error'); });
    }
})();
</script>
</body>
</html>
<?php exit; // Prevent PW from wrapping output in admin chrome
