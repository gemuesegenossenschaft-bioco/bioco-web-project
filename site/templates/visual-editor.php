<?php namespace ProcessWire;

/**
 * Visual Editor: iframe-based live preview editor for content sections.
 * Outputs a standalone HTML page and exits before ProcessWire admin chrome renders.
 */

while (ob_get_level()) ob_end_clean();

if (!$user->isLoggedin() || $user->isGuest()) {
    header('Location: ' . $config->urls->admin . 'login/');
    exit;
}

$siteUrl = rtrim(getenv('NEXT_PUBLIC_SITE_URL') ?: 'https://bioco.ch', '/');
$draftSecret = getenv('PW_PREVIEW_TOKEN') ?: '';
$apiRoot = $config->urls->root . 'api/';

$pagesById = [];
$home = $pages->get('/');
if ($home->id) {
    $pagesById[$home->id] = [
        'id' => (int) $home->id,
        'title' => $home->title ?: 'Startseite',
        'path' => '/',
        'template' => $home->template->name,
    ];
}

foreach (wire('templates') as $template) {
    if (!$template->hasField('content_sections')) continue;
    foreach ($pages->find("template={$template->name}, sort=sort") as $page) {
        if (!$page->id) continue;
        if (isset($pagesById[$page->id])) continue;
        $pagesById[$page->id] = [
            'id' => (int) $page->id,
            'title' => $page->title ?: $page->name,
            'path' => '/' . trim($page->path, '/'),
            'template' => $page->template->name,
        ];
    }
}

$contentPages = array_values($pagesById);
usort($contentPages, function ($a, $b) {
    if ($a['path'] === '/') return -1;
    if ($b['path'] === '/') return 1;
    return strcmp($a['path'], $b['path']);
});

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Visual Editor</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    background: #111827;
    color: #e5e7eb;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ve-toolbar {
    align-items: center;
    background: #0f172a;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 12px;
    padding: 10px 14px;
    z-index: 10;
}
.ve-toolbar-logo {
    color: #8ab272;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
}
.ve-toolbar select,
.ve-toolbar button,
.ve-toolbar a,
.ve-field-editor input,
.ve-field-editor select,
.ve-field-editor textarea {
    background: #111827;
    border: 1px solid #334155;
    border-radius: 8px;
    color: #e5e7eb;
    font: inherit;
}
.ve-toolbar select {
    min-width: 260px;
    padding: 7px 10px;
}
.ve-toolbar-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
}
.ve-btn {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 7px 12px;
    text-decoration: none;
    transition: background 0.15s, border-color 0.15s, opacity 0.15s;
}
.ve-btn:hover { background: #1f2937; }
.ve-btn-primary {
    background: #4a7c59;
    border-color: #4a7c59;
    color: #fff;
}
.ve-btn-primary:hover { background: #3f6c4e; }
.ve-btn-danger {
    background: #7f1d1d;
    border-color: #7f1d1d;
    color: #fff;
}
.ve-btn-danger:hover { background: #991b1b; }
.ve-btn:disabled { cursor: not-allowed; opacity: 0.55; }
.ve-status {
    background: #1f2937;
    border-radius: 999px;
    font-size: 11px;
    padding: 4px 10px;
    white-space: nowrap;
}
.ve-status.is-ready { background: #17321f; color: #9ae6b4; }
.ve-status.is-loading { background: #3b2f17; color: #f6e05e; }
.ve-status.is-error { background: #3b1717; color: #feb2b2; }
.ve-main {
    display: flex;
    flex: 1;
    min-height: 0;
}
.ve-sidebar {
    background: #0f172a;
    border-right: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    width: 430px;
}
.ve-sidebar-header {
    align-items: center;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    padding: 12px 14px;
}
.ve-sidebar-title {
    font-size: 13px;
    font-weight: 600;
}
.ve-section-list-wrap {
    border-bottom: 1px solid #1f2937;
    max-height: 42%;
    min-height: 180px;
    overflow-y: auto;
}
.ve-section-list {
    list-style: none;
}
.ve-section-item {
    align-items: center;
    border-left: 3px solid transparent;
    border-bottom: 1px solid #1f2937;
    cursor: pointer;
    display: flex;
    gap: 10px;
    padding: 10px 14px;
}
.ve-section-item:hover { background: #111827; }
.ve-section-item.is-active {
    background: #111827;
    border-left-color: #4a7c59;
}
.ve-section-drag {
    color: #64748b;
    cursor: grab;
    font-size: 15px;
    user-select: none;
}
.ve-section-info {
    flex: 1;
    min-width: 0;
}
.ve-section-title {
    font-size: 13px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-section-meta {
    color: #94a3b8;
    display: flex;
    gap: 4px;
    margin-top: 3px;
}
.ve-layout-badge {
    background: #1e293b;
    border-radius: 999px;
    font-size: 10px;
    padding: 2px 7px;
}
.ve-section-actions {
    display: flex;
    gap: 4px;
}
.ve-icon-btn {
    align-items: center;
    background: transparent;
    border: none;
    border-radius: 6px;
    color: #94a3b8;
    cursor: pointer;
    display: inline-flex;
    height: 28px;
    justify-content: center;
    width: 28px;
}
.ve-icon-btn:hover {
    background: #1f2937;
    color: #e5e7eb;
}
.ve-field-editor {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 0;
}
.ve-editor-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 14px;
}
.ve-empty-state {
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.5;
    padding: 18px 14px;
}
.ve-field-group {
    margin-bottom: 14px;
}
.ve-field-group label {
    color: #94a3b8;
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    margin-bottom: 5px;
    text-transform: uppercase;
}
.ve-field-group input,
.ve-field-group select,
.ve-field-group textarea {
    padding: 8px 10px;
    width: 100%;
}
.ve-field-group textarea {
    min-height: 110px;
    resize: vertical;
}
.ve-form-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.ve-form-grid .ve-field-group-full {
    grid-column: 1 / -1;
}
.ve-actions-bar {
    border-top: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    padding: 12px 14px;
}
.ve-help {
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
    margin-top: 4px;
}
.ve-dirty-pill {
    background: #3b2f17;
    border-radius: 999px;
    color: #f6e05e;
    font-size: 10px;
    margin-left: 6px;
    padding: 2px 6px;
}
.ve-iframe-wrap {
    background: #fff;
    flex: 1;
    min-width: 0;
    position: relative;
}
.ve-iframe-wrap iframe {
    border: none;
    height: 100%;
    width: 100%;
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
        <button class="ve-btn" id="ve-btn-refresh" type="button">Neu laden</button>
        <button class="ve-btn" id="ve-btn-pw" type="button">PW Admin</button>
        <a href="<?= $config->urls->admin ?>" class="ve-btn">Zurück</a>
    </div>
</div>

<div class="ve-main">
    <aside class="ve-sidebar">
        <div class="ve-sidebar-header">
            <span class="ve-sidebar-title">Inhaltsbereiche</span>
            <button class="ve-btn ve-btn-primary" id="ve-btn-add" type="button" disabled>Abschnitt hinzufügen</button>
        </div>

        <div class="ve-section-list-wrap">
            <div class="ve-empty-state" id="ve-empty-list">Seite wählen, um Abschnitte zu laden.</div>
            <ul class="ve-section-list" id="ve-section-list"></ul>
        </div>

        <div class="ve-field-editor">
            <div class="ve-editor-scroll" id="ve-field-editor">
                <div class="ve-empty-state">Wähle einen Abschnitt aus der Liste oder direkt in der Vorschau.</div>
            </div>
            <div class="ve-actions-bar">
                <button class="ve-btn" id="ve-btn-reset" type="button" disabled>Zurücksetzen</button>
                <button class="ve-btn ve-btn-primary" id="ve-btn-save" type="button" disabled>Speichern</button>
            </div>
        </div>
    </aside>

    <div class="ve-iframe-wrap">
        <iframe id="ve-iframe" src="about:blank"></iframe>
    </div>
</div>

<script>
(function () {
    var PREFIX = 'bioco:visual-editor:';
    var SITE_URL = <?= json_encode($siteUrl) ?>;
    var DRAFT_SECRET = <?= json_encode($draftSecret) ?>;
    var API_ROOT = <?= json_encode($apiRoot) ?>;
    var ALL_PAGES = <?= json_encode($contentPages, JSON_UNESCAPED_UNICODE) ?>;
    var LAYOUT_LABELS = {
        split_media_text: 'Bild + Text',
        split_text_media: 'Text + Bild',
        full_width_banner: 'Banner',
        media_grid: 'Bildergalerie',
        video_embed: 'Video',
        rich_text: 'Nur Text',
        component: 'Komponente'
    };
    var THEME_OPTIONS = ['default', 'light', 'dark', 'green'];
    var BG_OPTIONS = ['none', 'green', 'darkgreen', 'orange', 'gray', 'white'];
    var OVERLAY_OPTIONS = ['none', 'dark', 'green', 'orange'];
    var BUTTON_VARIANTS = ['primary', 'secondary'];

    var iframe = document.getElementById('ve-iframe');
    var pageSelect = document.getElementById('ve-page-select');
    var statusEl = document.getElementById('ve-status');
    var sectionList = document.getElementById('ve-section-list');
    var emptyList = document.getElementById('ve-empty-list');
    var fieldEditor = document.getElementById('ve-field-editor');
    var btnAdd = document.getElementById('ve-btn-add');
    var btnRefresh = document.getElementById('ve-btn-refresh');
    var btnPw = document.getElementById('ve-btn-pw');
    var btnSave = document.getElementById('ve-btn-save');
    var btnReset = document.getElementById('ve-btn-reset');

    var currentPageId = null;
    var currentPath = null;
    var sections = [];
    var activeSectionId = null;
    var pendingSelectId = null;
    var iframeReady = false;
    var isDirty = false;
    var isSaving = false;

    ALL_PAGES.forEach(function (page) {
        var option = document.createElement('option');
        option.value = page.id + '|' + page.path;
        option.textContent = page.title + ' (' + page.path + ')';
        pageSelect.appendChild(option);
    });

    function setStatus(text, cls) {
        statusEl.textContent = text;
        statusEl.className = 've-status' + (cls ? ' ' + cls : '');
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getPageDescriptor(pageId, path) {
        for (var i = 0; i < ALL_PAGES.length; i++) {
            if (ALL_PAGES[i].id === pageId && ALL_PAGES[i].path === path) return ALL_PAGES[i];
        }
        return null;
    }

    function getSectionById(sectionId) {
        for (var i = 0; i < sections.length; i++) {
            if (sections[i].id === sectionId) return sections[i];
        }
        return null;
    }

    function getButton(section, index) {
        if (!section || !Array.isArray(section.buttons)) {
            return { text: '', href: '', variant: index === 0 ? 'primary' : 'secondary' };
        }
        return section.buttons[index] || { text: '', href: '', variant: index === 0 ? 'primary' : 'secondary' };
    }

    function setButtons(section, buttonIndex, patch) {
        var buttons = Array.isArray(section.buttons) ? section.buttons.slice() : [];
        var current = buttons[buttonIndex] || { text: '', href: '', variant: buttonIndex === 0 ? 'primary' : 'secondary' };
        buttons[buttonIndex] = Object.assign({}, current, patch);
        while (buttons.length > 0) {
            var last = buttons[buttons.length - 1];
            if ((last.text || '').trim() || (last.href || '').trim()) break;
            buttons.pop();
        }
        section.buttons = buttons;
    }

    function sendToIframe(action, data) {
        if (!iframeReady || !iframe.contentWindow) return;
        iframe.contentWindow.postMessage(Object.assign({ type: PREFIX + action }, data || {}), '*');
    }

    function updateActions() {
        var hasActive = !!getSectionById(activeSectionId);
        btnAdd.disabled = !currentPageId || isSaving;
        btnPw.disabled = !currentPageId;
        btnSave.disabled = !hasActive || !isDirty || isSaving;
        btnReset.disabled = !hasActive || !isDirty || isSaving;
        if (isSaving) {
            btnSave.textContent = 'Speichert...';
        } else {
            btnSave.textContent = 'Speichern';
        }
    }

    function confirmDiscardChanges() {
        if (!isDirty) return true;
        return window.confirm('Ungespeicherte Änderungen verwerfen?');
    }

    function markDirty(nextDirty) {
        isDirty = !!nextDirty;
        updateActions();
    }

    function sectionEndpointForCurrentPath() {
        if (!currentPath) return '';
        if (currentPath === '/') return API_ROOT + 'content/homepage';
        return API_ROOT + 'content/sections/' + encodeURIComponent(currentPath.replace(/^\/|\/$/g, ''));
    }

    function fetchSections(options) {
        options = options || {};
        var endpoint = sectionEndpointForCurrentPath();
        if (!endpoint) return Promise.resolve();

        if (!options.keepStatus) {
            setStatus('Abschnitte laden...', 'is-loading');
        }

        return fetch(endpoint, { credentials: 'include' })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error((data && data.error) || 'Fehler beim Laden');
                    }
                    return data;
                });
            })
            .then(function (data) {
                sections = Array.isArray(data.sections) ? data.sections : [];
                if (pendingSelectId && getSectionById(pendingSelectId)) {
                    activeSectionId = pendingSelectId;
                } else if (activeSectionId && !getSectionById(activeSectionId)) {
                    activeSectionId = null;
                }
                pendingSelectId = null;
                renderSectionList();
                renderFieldEditor();
                updateActions();
                if (iframeReady) {
                    sendToIframe('sections-replace', { sections: sections });
                    if (activeSectionId) {
                        sendToIframe('section-highlight', { sectionId: activeSectionId });
                    }
                }
                if (!options.keepStatus) {
                    setStatus('Verbunden', 'is-ready');
                }
            })
            .catch(function (error) {
                setStatus(error.message || 'Fehler beim Laden', 'is-error');
            });
    }

    function loadPage(pageId, path, options) {
        options = options || {};
        currentPageId = pageId;
        currentPath = path;
        iframeReady = false;
        sections = [];
        pendingSelectId = null;
        activeSectionId = null;
        isDirty = false;
        isSaving = false;

        pageSelect.value = pageId + '|' + path;
        renderSectionList();
        renderFieldEditor();
        updateActions();
        setStatus('Vorschau laden...', 'is-loading');

        var url = SITE_URL + path;
        url += (url.indexOf('?') === -1 ? '?' : '&') + '_visual=1';
        if (DRAFT_SECRET) {
            url += '&draft_secret=' + encodeURIComponent(DRAFT_SECRET);
        }
        iframe.src = url;
    }

    function applySectionField(section, name, value) {
        switch (name) {
            case 'title':
                section.title = value;
                sendToIframe('section-update', { sectionId: section.id, field: 'title', value: value });
                break;
            case 'text':
                section.text = value;
                sendToIframe('section-update', { sectionId: section.id, field: 'text', value: value });
                break;
            case 'eyebrow':
                section.eyebrow = value;
                sendToIframe('section-update', { sectionId: section.id, field: 'eyebrow', value: value });
                break;
            case 'layout':
                section.layout = value || 'rich_text';
                sendToIframe('section-update', { sectionId: section.id, field: 'layout', value: section.layout });
                break;
            case 'theme':
                section.theme = value || 'default';
                sendToIframe('section-update', { sectionId: section.id, field: 'theme', value: section.theme });
                break;
            case 'bgColor':
                section.bgColor = value === 'none' ? undefined : value;
                sendToIframe('section-update', { sectionId: section.id, field: 'bgColor', value: section.bgColor });
                break;
            case 'imageOverlay':
                section.imageOverlay = value === 'none' ? undefined : value;
                sendToIframe('section-update', { sectionId: section.id, field: 'imageOverlay', value: section.imageOverlay });
                break;
            case 'component':
                section.component = value;
                sendToIframe('section-update', { sectionId: section.id, field: 'component', value: value });
                break;
            case 'button0_text':
                setButtons(section, 0, { text: value });
                sendToIframe('section-update', { sectionId: section.id, field: 'buttons', value: section.buttons });
                break;
            case 'button0_href':
                setButtons(section, 0, { href: value });
                sendToIframe('section-update', { sectionId: section.id, field: 'buttons', value: section.buttons });
                break;
            case 'button0_variant':
                setButtons(section, 0, { variant: value || 'primary' });
                sendToIframe('section-update', { sectionId: section.id, field: 'buttons', value: section.buttons });
                break;
            case 'button1_text':
                setButtons(section, 1, { text: value });
                sendToIframe('section-update', { sectionId: section.id, field: 'buttons', value: section.buttons });
                break;
            case 'button1_href':
                setButtons(section, 1, { href: value });
                sendToIframe('section-update', { sectionId: section.id, field: 'buttons', value: section.buttons });
                break;
            case 'button1_variant':
                setButtons(section, 1, { variant: value || 'secondary' });
                sendToIframe('section-update', { sectionId: section.id, field: 'buttons', value: section.buttons });
                break;
        }
    }

    function selectSection(sectionId, options) {
        options = options || {};
        if (!getSectionById(sectionId)) return;
        activeSectionId = sectionId;
        renderSectionList();
        renderFieldEditor();
        updateActions();
        sendToIframe('section-highlight', { sectionId: sectionId });
        if (options.scroll !== false) {
            sendToIframe('section-scroll', { sectionId: sectionId });
        }
    }

    function renderSectionList() {
        sectionList.innerHTML = '';
        emptyList.style.display = sections.length ? 'none' : 'block';

        sections.forEach(function (section, index) {
            var item = document.createElement('li');
            item.className = 've-section-item' + (section.id === activeSectionId ? ' is-active' : '');
            item.draggable = true;

            var drag = document.createElement('span');
            drag.className = 've-section-drag';
            drag.textContent = '⠿';

            var info = document.createElement('div');
            info.className = 've-section-info';

            var title = document.createElement('div');
            title.className = 've-section-title';
            title.textContent = section.title || '(kein Titel)';
            info.appendChild(title);

            var meta = document.createElement('div');
            meta.className = 've-section-meta';

            var layout = document.createElement('span');
            layout.className = 've-layout-badge';
            layout.textContent = LAYOUT_LABELS[section.layout] || section.layout || 'Abschnitt';
            meta.appendChild(layout);

            if (section.component) {
                var component = document.createElement('span');
                component.className = 've-layout-badge';
                component.textContent = section.component;
                meta.appendChild(component);
            }

            if (section.id === activeSectionId && isDirty) {
                var dirty = document.createElement('span');
                dirty.className = 've-dirty-pill';
                dirty.textContent = 'UNGESPEICHERT';
                meta.appendChild(dirty);
            }

            info.appendChild(meta);

            var actions = document.createElement('div');
            actions.className = 've-section-actions';

            var editBtn = document.createElement('button');
            editBtn.className = 've-icon-btn';
            editBtn.type = 'button';
            editBtn.title = 'In ProcessWire bearbeiten';
            editBtn.textContent = '↗';
            editBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                if (section.pwId) {
                    window.open('<?= $config->urls->admin ?>page/edit/?id=' + section.pwId, '_blank');
                }
            });

            var deleteBtn = document.createElement('button');
            deleteBtn.className = 've-icon-btn';
            deleteBtn.type = 'button';
            deleteBtn.title = 'Abschnitt löschen';
            deleteBtn.textContent = '✕';
            deleteBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                if (!confirmDiscardChanges()) return;
                if (!window.confirm('Abschnitt "' + (section.title || '') + '" wirklich löschen?')) return;
                deleteSection(section);
            });

            actions.appendChild(editBtn);
            actions.appendChild(deleteBtn);

            item.appendChild(drag);
            item.appendChild(info);
            item.appendChild(actions);

            item.addEventListener('click', function () {
                if (activeSectionId !== section.id && isDirty && !confirmDiscardChanges()) return;
                markDirty(false);
                selectSection(section.id);
            });

            item.addEventListener('dragstart', function (event) {
                if (isDirty && !confirmDiscardChanges()) {
                    event.preventDefault();
                    return;
                }
                event.dataTransfer.setData('text/plain', String(index));
                item.style.opacity = '0.5';
            });
            item.addEventListener('dragend', function () {
                item.style.opacity = '1';
                item.style.borderTop = '';
            });
            item.addEventListener('dragover', function (event) {
                event.preventDefault();
                item.style.borderTop = '2px solid #4a7c59';
            });
            item.addEventListener('dragleave', function () {
                item.style.borderTop = '';
            });
            item.addEventListener('drop', function (event) {
                event.preventDefault();
                item.style.borderTop = '';
                var fromIndex = parseInt(event.dataTransfer.getData('text/plain'), 10);
                if (isNaN(fromIndex) || fromIndex === index) return;
                reorderSections(fromIndex, index);
            });

            sectionList.appendChild(item);
        });
    }

    function optionMarkup(options, current) {
        return options.map(function (option) {
            return '<option value="' + escapeHtml(option) + '"' + (option === current ? ' selected' : '') + '>' + escapeHtml(option) + '</option>';
        }).join('');
    }

    function renderFieldEditor() {
        var section = getSectionById(activeSectionId);
        if (!section) {
            fieldEditor.innerHTML = '<div class="ve-empty-state">Wähle einen Abschnitt aus der Liste oder direkt in der Vorschau.</div>';
            updateActions();
            return;
        }

        var button1 = getButton(section, 0);
        var button2 = getButton(section, 1);
        var dirtyBadge = isDirty ? '<span class="ve-dirty-pill">Ungespeichert</span>' : '';

        fieldEditor.innerHTML =
            '<div class="ve-field-group">' +
                '<div class="ve-sidebar-title">Abschnitt bearbeiten' + dirtyBadge + '</div>' +
                '<div class="ve-help">Live-Vorschau aktualisiert sofort. Dauerhaft gespeichert wird erst mit "Speichern".</div>' +
            '</div>' +
            '<div class="ve-form-grid">' +
                '<div class="ve-field-group ve-field-group-full">' +
                    '<label for="ve-field-title">Titel</label>' +
                    '<input id="ve-field-title" name="title" type="text" value="' + escapeHtml(section.title || '') + '">' +
                '</div>' +
                '<div class="ve-field-group ve-field-group-full">' +
                    '<label for="ve-field-eyebrow">Eyebrow</label>' +
                    '<input id="ve-field-eyebrow" name="eyebrow" type="text" value="' + escapeHtml(section.eyebrow || '') + '">' +
                '</div>' +
                '<div class="ve-field-group">' +
                    '<label for="ve-field-layout">Layout</label>' +
                    '<select id="ve-field-layout" name="layout">' + optionMarkup(Object.keys(LAYOUT_LABELS), section.layout || 'rich_text') + '</select>' +
                '</div>' +
                '<div class="ve-field-group">' +
                    '<label for="ve-field-theme">Theme</label>' +
                    '<select id="ve-field-theme" name="theme">' + optionMarkup(THEME_OPTIONS, section.theme || 'default') + '</select>' +
                '</div>' +
                '<div class="ve-field-group">' +
                    '<label for="ve-field-bg">Hintergrund</label>' +
                    '<select id="ve-field-bg" name="bgColor">' + optionMarkup(BG_OPTIONS, section.bgColor || 'none') + '</select>' +
                '</div>' +
                '<div class="ve-field-group">' +
                    '<label for="ve-field-overlay">Bild-Overlay</label>' +
                    '<select id="ve-field-overlay" name="imageOverlay">' + optionMarkup(OVERLAY_OPTIONS, section.imageOverlay || 'none') + '</select>' +
                '</div>' +
                '<div class="ve-field-group ve-field-group-full">' +
                    '<label for="ve-field-component">Komponente</label>' +
                    '<input id="ve-field-component" name="component" type="text" value="' + escapeHtml(section.component || '') + '">' +
                '</div>' +
                '<div class="ve-field-group ve-field-group-full">' +
                    '<label for="ve-field-text">Text</label>' +
                    '<textarea id="ve-field-text" name="text">' + escapeHtml(section.text || '') + '</textarea>' +
                '</div>' +
                '<div class="ve-field-group">' +
                    '<label for="ve-field-button0-text">Button 1 Text</label>' +
                    '<input id="ve-field-button0-text" name="button0_text" type="text" value="' + escapeHtml(button1.text || '') + '">' +
                '</div>' +
                '<div class="ve-field-group">' +
                    '<label for="ve-field-button0-href">Button 1 Link</label>' +
                    '<input id="ve-field-button0-href" name="button0_href" type="text" value="' + escapeHtml(button1.href || '') + '">' +
                '</div>' +
                '<div class="ve-field-group">' +
                    '<label for="ve-field-button0-variant">Button 1 Stil</label>' +
                    '<select id="ve-field-button0-variant" name="button0_variant">' + optionMarkup(BUTTON_VARIANTS, button1.variant || 'primary') + '</select>' +
                '</div>' +
                '<div class="ve-field-group"></div>' +
                '<div class="ve-field-group">' +
                    '<label for="ve-field-button1-text">Button 2 Text</label>' +
                    '<input id="ve-field-button1-text" name="button1_text" type="text" value="' + escapeHtml(button2.text || '') + '">' +
                '</div>' +
                '<div class="ve-field-group">' +
                    '<label for="ve-field-button1-href">Button 2 Link</label>' +
                    '<input id="ve-field-button1-href" name="button1_href" type="text" value="' + escapeHtml(button2.href || '') + '">' +
                '</div>' +
                '<div class="ve-field-group">' +
                    '<label for="ve-field-button1-variant">Button 2 Stil</label>' +
                    '<select id="ve-field-button1-variant" name="button1_variant">' + optionMarkup(BUTTON_VARIANTS, button2.variant || 'secondary') + '</select>' +
                '</div>' +
            '</div>';
    }

    function buildSavePayload(section) {
        var button1 = getButton(section, 0);
        var button2 = getButton(section, 1);
        return {
            section_title: section.title || '',
            section_text: section.text || '',
            section_eyebrow: section.eyebrow || '',
            section_layout: section.layout || 'rich_text',
            section_theme: section.theme || 'default',
            section_bg_color: section.bgColor || 'none',
            section_image_overlay: section.imageOverlay || 'none',
            section_component: section.component || '',
            button_text: button1.text || '',
            button_href: button1.href || '',
            button_variant: button1.variant || 'primary',
            button2_text: button2.text || '',
            button2_href: button2.href || '',
            button2_variant: button2.variant || 'secondary'
        };
    }

    function saveActiveSection() {
        var section = getSectionById(activeSectionId);
        if (!section || !section.pwId || isSaving) return;

        isSaving = true;
        updateActions();
        setStatus('Speichert...', 'is-loading');

        fetch(API_ROOT + 'content-save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                sectionPwId: section.pwId,
                fields: buildSavePayload(section)
            })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error((data && data.error) || 'Speichern fehlgeschlagen');
                    }
                    return data;
                });
            })
            .then(function () {
                isDirty = false;
                return fetchSections({ keepStatus: true });
            })
            .then(function () {
                setStatus('Gespeichert', 'is-ready');
                renderSectionList();
                renderFieldEditor();
            })
            .catch(function (error) {
                setStatus(error.message || 'Speichern fehlgeschlagen', 'is-error');
            })
            .finally(function () {
                isSaving = false;
                updateActions();
            });
    }

    function resetActiveSection() {
        if (!confirmDiscardChanges()) return;
        isDirty = false;
        fetchSections({ keepStatus: true }).then(function () {
            setStatus('Zurückgesetzt', 'is-ready');
        });
    }

    function reorderSections(fromIndex, toIndex) {
        if (!currentPageId || isSaving) return;
        var order = sections.slice();
        var moved = order.splice(fromIndex, 1)[0];
        order.splice(toIndex, 0, moved);

        setStatus('Sortierung speichern...', 'is-loading');
        fetch(API_ROOT + 'sections-reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                pageId: currentPageId,
                order: order.map(function (section) { return section.pwId; })
            })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error((data && data.error) || 'Sortieren fehlgeschlagen');
                    }
                    return data;
                });
            })
            .then(function () {
                return fetchSections({ keepStatus: true });
            })
            .then(function () {
                setStatus('Sortierung aktualisiert', 'is-ready');
            })
            .catch(function (error) {
                setStatus(error.message || 'Sortieren fehlgeschlagen', 'is-error');
            });
    }

    function addSection(layout) {
        if (!currentPageId || isSaving) return;
        setStatus('Abschnitt anlegen...', 'is-loading');
        fetch(API_ROOT + 'sections-add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ pageId: currentPageId, layout: layout })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error((data && data.error) || 'Abschnitt konnte nicht angelegt werden');
                    }
                    return data;
                });
            })
            .then(function (data) {
                pendingSelectId = data.section && data.section.id ? data.section.id : null;
                return fetchSections({ keepStatus: true });
            })
            .then(function () {
                if (pendingSelectId && getSectionById(pendingSelectId)) {
                    selectSection(pendingSelectId);
                    pendingSelectId = null;
                }
                setStatus('Abschnitt angelegt', 'is-ready');
            })
            .catch(function (error) {
                setStatus(error.message || 'Abschnitt konnte nicht angelegt werden', 'is-error');
            });
    }

    function deleteSection(section) {
        if (!currentPageId || !section || !section.pwId || isSaving) return;
        setStatus('Abschnitt löschen...', 'is-loading');
        fetch(API_ROOT + 'sections-delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ pageId: currentPageId, sectionPwId: section.pwId })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error((data && data.error) || 'Löschen fehlgeschlagen');
                    }
                    return data;
                });
            })
            .then(function () {
                if (activeSectionId === section.id) {
                    activeSectionId = null;
                    isDirty = false;
                }
                return fetchSections({ keepStatus: true });
            })
            .then(function () {
                setStatus('Abschnitt gelöscht', 'is-ready');
            })
            .catch(function (error) {
                setStatus(error.message || 'Löschen fehlgeschlagen', 'is-error');
            });
    }

    pageSelect.addEventListener('change', function () {
        if (!this.value) return;
        var parts = this.value.split('|');
        var nextPageId = parseInt(parts[0], 10);
        var nextPath = parts[1];
        if (!nextPageId || !nextPath) return;
        if (currentPageId === nextPageId && currentPath === nextPath) return;
        if (!confirmDiscardChanges()) {
            if (currentPageId && currentPath) {
                pageSelect.value = currentPageId + '|' + currentPath;
            } else {
                pageSelect.value = '';
            }
            return;
        }
        loadPage(nextPageId, nextPath);
    });

    btnRefresh.addEventListener('click', function () {
        if (!currentPageId || !currentPath) return;
        if (!confirmDiscardChanges()) return;
        loadPage(currentPageId, currentPath, { force: true });
    });

    btnPw.addEventListener('click', function () {
        if (!currentPageId) return;
        window.open('<?= $config->urls->admin ?>page/edit/?id=' + currentPageId, '_blank');
    });

    btnAdd.addEventListener('click', function () {
        if (!currentPageId) return;
        if (!confirmDiscardChanges()) return;
        var choice = window.prompt(
            'Layout wählen:\n\n1. Bild + Text\n2. Text + Bild\n3. Banner\n4. Bildergalerie\n5. Video\n6. Nur Text\n7. Komponente\n\nNummer eingeben:',
            '6'
        );
        if (choice === null) return;
        var layoutMap = {
            '1': 'split_media_text',
            '2': 'split_text_media',
            '3': 'full_width_banner',
            '4': 'media_grid',
            '5': 'video_embed',
            '6': 'rich_text',
            '7': 'component'
        };
        addSection(layoutMap[choice] || 'rich_text');
    });

    btnSave.addEventListener('click', function () {
        saveActiveSection();
    });

    btnReset.addEventListener('click', function () {
        resetActiveSection();
    });

    fieldEditor.addEventListener('input', function (event) {
        var target = event.target;
        if (!target || !target.name) return;
        var section = getSectionById(activeSectionId);
        if (!section) return;
        applySectionField(section, target.name, target.value);
        markDirty(true);
        renderSectionList();
    });

    fieldEditor.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || !target.name) return;
        var section = getSectionById(activeSectionId);
        if (!section) return;
        applySectionField(section, target.name, target.value);
        markDirty(true);
        renderSectionList();
    });

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || typeof data.type !== 'string' || data.type.indexOf(PREFIX) !== 0) return;

        var action = data.type.slice(PREFIX.length);

        if (action === 'ready') {
            iframeReady = true;
            setStatus('Verbunden', 'is-ready');
            fetchSections();
            return;
        }

        if (action === 'section-click') {
            if (activeSectionId !== data.sectionId && isDirty && !confirmDiscardChanges()) return;
            isDirty = false;
            selectSection(data.sectionId, { scroll: false });
        }
    });

    window.addEventListener('beforeunload', function (event) {
        if (!isDirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    updateActions();
})();
</script>
</body>
</html>
<?php exit;
