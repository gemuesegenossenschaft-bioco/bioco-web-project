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
.ve-mode-switch {
    display: flex;
    gap: 6px;
}
.ve-mode-btn.is-active {
    background: #4a7c59;
    border-color: #4a7c59;
    color: #fff;
}
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
.ve-info-card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 12px;
    padding: 12px;
}
.ve-info-card + .ve-info-card {
    margin-top: 12px;
}
.ve-info-card strong {
    display: block;
    font-size: 12px;
    margin-bottom: 4px;
}
.ve-info-card p {
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
}
.ve-media-modal {
    align-items: stretch;
    background: rgba(15, 23, 42, 0.72);
    display: none;
    inset: 0;
    justify-content: flex-end;
    position: fixed;
    z-index: 30;
}
.ve-media-modal.is-open {
    display: flex;
}
.ve-media-panel {
    background: #0f172a;
    border-left: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 460px;
    width: 100%;
}
.ve-media-header {
    align-items: center;
    border-bottom: 1px solid #1f2937;
    display: flex;
    justify-content: space-between;
    padding: 14px;
}
.ve-media-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    overflow-y: auto;
    padding: 14px;
}
.ve-media-card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 12px;
    cursor: pointer;
    overflow: hidden;
    padding: 0;
    text-align: left;
}
.ve-media-card img {
    display: block;
    height: 120px;
    object-fit: cover;
    width: 100%;
}
.ve-media-card-body {
    padding: 10px;
}
.ve-media-card-body strong {
    display: block;
    font-size: 12px;
    margin-bottom: 4px;
}
.ve-media-card-body span {
    color: #94a3b8;
    display: block;
    font-size: 11px;
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
    <div class="ve-mode-switch">
        <button class="ve-btn ve-mode-btn is-active" id="ve-mode-edit" type="button">Edit</button>
        <button class="ve-btn ve-mode-btn" id="ve-mode-browse" type="button">Browse</button>
    </div>
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
                <div class="ve-empty-state">Wähle einen Abschnitt oder ein Feld direkt in der Vorschau.</div>
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

<div class="ve-media-modal" id="ve-media-modal">
    <div class="ve-media-panel">
        <div class="ve-media-header">
            <strong>Mediathek</strong>
            <button class="ve-btn" id="ve-media-close" type="button">Schliessen</button>
        </div>
        <div class="ve-empty-state" id="ve-media-empty">Medien werden geladen…</div>
        <div class="ve-media-grid" id="ve-media-grid"></div>
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
    var btnModeEdit = document.getElementById('ve-mode-edit');
    var btnModeBrowse = document.getElementById('ve-mode-browse');
    var mediaModal = document.getElementById('ve-media-modal');
    var mediaClose = document.getElementById('ve-media-close');
    var mediaEmpty = document.getElementById('ve-media-empty');
    var mediaGrid = document.getElementById('ve-media-grid');

    var currentPageId = null;
    var currentPath = null;
    var sections = [];
    var activeSectionId = null;
    var activeField = null;
    var pendingSelectId = null;
    var iframeReady = false;
    var dirtySectionIds = {};
    var isSaving = false;
    var editorMode = 'edit';
    var mediaFiles = [];
    var mediaRequest = null;

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

    function getActiveSection() {
        return getSectionById(activeSectionId);
    }

    function getDirtySectionIds() {
        return Object.keys(dirtySectionIds);
    }

    function hasDirtySections() {
        return getDirtySectionIds().length > 0;
    }

    function isSectionDirty(sectionId) {
        return !!dirtySectionIds[sectionId];
    }

    function markSectionDirty(sectionId, nextDirty) {
        if (!sectionId) return;
        if (nextDirty) {
            dirtySectionIds[sectionId] = true;
        } else {
            delete dirtySectionIds[sectionId];
        }
    }

    function clearDirtySections() {
        dirtySectionIds = {};
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

    function updateModeButtons() {
        btnModeEdit.classList.toggle('is-active', editorMode === 'edit');
        btnModeBrowse.classList.toggle('is-active', editorMode === 'browse');
    }

    function syncIframeState(message) {
        sendToIframe('save-state', {
            mode: editorMode,
            dirty: hasDirtySections(),
            saving: isSaving,
            message: message || '',
            selectedSectionId: activeSectionId
        });
    }

    function updateActions() {
        var hasActive = !!getSectionById(activeSectionId);
        btnAdd.disabled = !currentPageId || isSaving;
        btnPw.disabled = !currentPageId;
        btnSave.disabled = !hasDirtySections() || isSaving;
        btnReset.disabled = !hasDirtySections() || isSaving;
        if (isSaving) {
            btnSave.textContent = 'Speichert...';
        } else {
            btnSave.textContent = 'Speichern';
        }
        if (!hasActive) {
            btnReset.disabled = !hasDirtySections() || isSaving;
        }
        updateModeButtons();
    }

    function confirmDiscardChanges() {
        if (!hasDirtySections()) return true;
        return window.confirm('Ungespeicherte Änderungen verwerfen?');
    }

    function blockWhileDirty(actionLabel) {
        if (!hasDirtySections()) return false;
        window.alert('Vor "' + actionLabel + '" zuerst speichern oder zurücksetzen.');
        return true;
    }

    function normalizeChoice(value, fallback) {
        if (!value || value === 'none') return fallback;
        return value;
    }

    function fieldLabel(field) {
        if (!field) return 'Feld';
        if (field.kind === 'button') {
            return 'Button ' + ((field.buttonIndex || 0) + 1);
        }
        switch (field.field) {
            case 'title': return 'Titel';
            case 'eyebrow': return 'Eyebrow';
            case 'text': return 'Text';
            case 'media': return 'Bild / Medien';
            case 'component': return 'Komponente';
            default: return field.field;
        }
    }

    function fieldHint(field) {
        if (!field) return 'Klicke ein Feld in der Vorschau an, um inline zu bearbeiten.';
        switch (field.field) {
            case 'text':
                return 'Rich Text wird direkt im iframe bearbeitet. Änderungen bleiben lokal bis zum Speichern.';
            case 'media':
                return 'Alt-Text und Medienauswahl laufen über das Overlay direkt im iframe.';
            case 'component':
                return 'Komponentenname wird inline geändert. Komponentenspezifische Optionen sind in V1 noch begrenzt.';
            case 'button':
                return 'Text, Link und Variante werden inline im Button-Overlay geändert.';
            default:
                return 'Dieses Feld wird direkt in der Vorschau bearbeitet.';
        }
    }

    function renderFieldEditor() {
        var section = getActiveSection();
        var page = getPageDescriptor(currentPageId, currentPath);
        var dirtyCount = getDirtySectionIds().length;

        if (!currentPageId || !page) {
            fieldEditor.innerHTML =
                '<div class="ve-empty-state">Seite wählen, dann direkt in der Vorschau bearbeiten.</div>';
            updateActions();
            return;
        }

        if (!section) {
            fieldEditor.innerHTML =
                '<div class="ve-info-card">' +
                    '<strong>Seite</strong>' +
                    '<p>' + escapeHtml(page.title) + ' (' + escapeHtml(page.path) + ')</p>' +
                '</div>' +
                '<div class="ve-info-card">' +
                    '<strong>Modus</strong>' +
                    '<p>' + (editorMode === 'edit' ? 'Edit: Felder klicken, inline ändern, explizit speichern.' : 'Browse: Seite verhält sich wie normale Vorschau.') + '</p>' +
                '</div>' +
                '<div class="ve-info-card">' +
                    '<strong>Status</strong>' +
                    '<p>' + (dirtyCount ? dirtyCount + ' Abschnitt(e) mit ungespeicherten Änderungen.' : 'Keine offenen Änderungen.') + '</p>' +
                '</div>';
            updateActions();
            return;
        }

        fieldEditor.innerHTML =
            '<div class="ve-info-card">' +
                '<strong>Aktiver Abschnitt</strong>' +
                '<p>' + escapeHtml(section.title || '(kein Titel)') + '</p>' +
                '<p>Layout: ' + escapeHtml(LAYOUT_LABELS[section.layout] || section.layout || 'Abschnitt') + (section.component ? ' · Komponente: ' + escapeHtml(section.component) : '') + '</p>' +
            '</div>' +
            '<div class="ve-info-card">' +
                '<strong>Aktives Feld</strong>' +
                '<p>' + escapeHtml(fieldLabel(activeField)) + '</p>' +
                '<p>' + escapeHtml(fieldHint(activeField)) + '</p>' +
            '</div>' +
            '<div class="ve-info-card">' +
                '<strong>Offene Änderungen</strong>' +
                '<p>' + (dirtyCount ? dirtyCount + ' Abschnitt(e) warten auf Speichern.' : 'Keine offenen Änderungen.') + '</p>' +
            '</div>' +
            '<div class="ve-info-card">' +
                '<strong>Hinweis</strong>' +
                '<p>Abschnitt-CRUD bleibt in der linken Spalte. Inline-Änderungen werden erst mit "Speichern" dauerhaft.</p>' +
            '</div>';

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
                    activeField = null;
                }
                pendingSelectId = null;
                renderSectionList();
                renderFieldEditor();
                updateActions();
                if (iframeReady) {
                    sendToIframe('sections-replace', { sections: sections });
                    sendToIframe('section-highlight', { sectionId: activeSectionId });
                    if (activeField && activeSectionId === activeField.sectionId) {
                        sendToIframe('field-highlight', activeField);
                    } else {
                        activeField = null;
                        sendToIframe('field-reset', {});
                    }
                    syncIframeState();
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
        activeField = null;
        clearDirtySections();
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

    function notifySectionUpdate(sectionId, field, value) {
        sendToIframe('section-update', { sectionId: sectionId, field: field, value: value });
    }

    function applyFieldChange(payload) {
        var section = getSectionById(payload.sectionId);
        if (!section) return;

        switch (payload.field) {
            case 'title':
                section.title = payload.value || '';
                notifySectionUpdate(section.id, 'title', section.title);
                break;
            case 'text':
                section.text = payload.value || '';
                notifySectionUpdate(section.id, 'text', section.text);
                break;
            case 'eyebrow':
                section.eyebrow = payload.value || '';
                notifySectionUpdate(section.id, 'eyebrow', section.eyebrow);
                break;
            case 'layout':
                section.layout = payload.value || 'rich_text';
                notifySectionUpdate(section.id, 'layout', section.layout);
                break;
            case 'theme':
                section.theme = payload.value || 'default';
                notifySectionUpdate(section.id, 'theme', section.theme);
                break;
            case 'bgColor':
                section.bgColor = normalizeChoice(payload.value, undefined);
                notifySectionUpdate(section.id, 'bgColor', section.bgColor);
                break;
            case 'imageOverlay':
                section.imageOverlay = normalizeChoice(payload.value, undefined);
                notifySectionUpdate(section.id, 'imageOverlay', section.imageOverlay);
                break;
            case 'component':
                section.component = payload.value || '';
                notifySectionUpdate(section.id, 'component', section.component);
                break;
            case 'imageAlt':
                section.imageAlt = payload.value || '';
                break;
            case 'button_text':
                setButtons(section, payload.buttonIndex || 0, { text: payload.value || '' });
                notifySectionUpdate(section.id, 'buttons', section.buttons);
                break;
            case 'button_href':
                setButtons(section, payload.buttonIndex || 0, { href: payload.value || '' });
                notifySectionUpdate(section.id, 'buttons', section.buttons);
                break;
            case 'button_variant':
                setButtons(section, payload.buttonIndex || 0, {
                    variant: payload.value || ((payload.buttonIndex || 0) === 0 ? 'primary' : 'secondary')
                });
                notifySectionUpdate(section.id, 'buttons', section.buttons);
                break;
            default:
                return;
        }
        markSectionDirty(section.id, true);
        renderSectionList();
        renderFieldEditor();
        syncIframeState();
        updateActions();
    }

    function selectSection(sectionId, options) {
        options = options || {};
        if (!getSectionById(sectionId)) return;
        activeSectionId = sectionId;
        if (options.clearField !== false) {
            activeField = null;
        }
        renderSectionList();
        renderFieldEditor();
        updateActions();
        sendToIframe('section-highlight', { sectionId: sectionId });
        if (options.clearField !== false) {
            sendToIframe('field-reset', {});
        } else if (activeField) {
            sendToIframe('field-highlight', activeField);
        }
        if (options.scroll !== false) {
            sendToIframe('section-scroll', { sectionId: sectionId });
        }
        syncIframeState();
    }

    function selectField(field, options) {
        options = options || {};
        if (!field || !field.sectionId || !getSectionById(field.sectionId)) return;
        activeSectionId = field.sectionId;
        activeField = {
            sectionId: field.sectionId,
            field: field.field,
            kind: field.kind,
            inline: field.inline !== false,
            buttonIndex: typeof field.buttonIndex === 'number' ? field.buttonIndex : undefined,
            targetField: typeof field.targetField === 'string' ? field.targetField : undefined
        };
        renderSectionList();
        renderFieldEditor();
        updateActions();
        sendToIframe('section-highlight', { sectionId: field.sectionId });
        sendToIframe('field-highlight', activeField);
        if (options.scroll !== false) {
            sendToIframe('section-scroll', { sectionId: field.sectionId });
        }
        syncIframeState();
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

            if (isSectionDirty(section.id)) {
                var dirty = document.createElement('span');
                dirty.className = 've-dirty-pill';
                dirty.textContent = 'UNGESPEICHERT';
                meta.appendChild(dirty);
            }

            info.appendChild(meta);

            var actions = document.createElement('div');
            actions.className = 've-section-actions';

            var deleteBtn = document.createElement('button');
            deleteBtn.className = 've-icon-btn';
            deleteBtn.type = 'button';
            deleteBtn.title = 'Abschnitt löschen';
            deleteBtn.textContent = '✕';
            deleteBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                if (blockWhileDirty('Löschen')) return;
                if (!window.confirm('Abschnitt "' + (section.title || '') + '" wirklich löschen?')) return;
                deleteSection(section);
            });

            actions.appendChild(deleteBtn);

            item.appendChild(drag);
            item.appendChild(info);
            item.appendChild(actions);

            item.addEventListener('click', function () {
                selectSection(section.id);
            });

            item.addEventListener('dragstart', function (event) {
                if (blockWhileDirty('Sortieren')) {
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
            image_alt: section.imageAlt || '',
            button_text: button1.text || '',
            button_href: button1.href || '',
            button_variant: button1.variant || 'primary',
            button2_text: button2.text || '',
            button2_href: button2.href || '',
            button2_variant: button2.variant || 'secondary'
        };
    }

    function parseJson(response) {
        return response.json().catch(function () { return {}; });
    }

    function postJson(url, body, fallbackError) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(body)
        })
            .then(function (response) {
                return parseJson(response).then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error((data && data.error) || fallbackError);
                    }
                    return data;
                });
            });
    }

    function saveSection(section) {
        if (!section || !section.pwId) return Promise.resolve();
        return postJson(API_ROOT + 'content-save', {
            sectionPwId: section.pwId,
            fields: buildSavePayload(section)
        }, 'Speichern fehlgeschlagen').then(function () {
            markSectionDirty(section.id, false);
        });
    }

    function saveDirtySections() {
        if (isSaving || !hasDirtySections()) return;
        var ordered = sections.filter(function (section) {
            return isSectionDirty(section.id);
        });

        isSaving = true;
        updateActions();
        setStatus('Speichert...', 'is-loading');
        syncIframeState();

        ordered.reduce(function (promise, section) {
            return promise.then(function () {
                return saveSection(section);
            });
        }, Promise.resolve())
            .then(function () {
                clearDirtySections();
                return fetchSections({ keepStatus: true });
            })
            .then(function () {
                setStatus('Gespeichert', 'is-ready');
                sendToIframe('save-result', { success: true });
            })
            .catch(function (error) {
                setStatus(error.message || 'Speichern fehlgeschlagen', 'is-error');
                sendToIframe('save-result', {
                    success: false,
                    error: error.message || 'Speichern fehlgeschlagen'
                });
            })
            .finally(function () {
                isSaving = false;
                updateActions();
                syncIframeState();
            });
    }

    function resetChanges() {
        if (!confirmDiscardChanges()) return;
        clearDirtySections();
        fetchSections({ keepStatus: true }).then(function () {
            setStatus('Zurückgesetzt', 'is-ready');
            syncIframeState();
        });
    }

    function reorderSections(fromIndex, toIndex) {
        if (!currentPageId || isSaving || blockWhileDirty('Sortieren')) return;
        var order = sections.slice();
        var moved = order.splice(fromIndex, 1)[0];
        order.splice(toIndex, 0, moved);

        setStatus('Sortierung speichern...', 'is-loading');
        postJson(API_ROOT + 'sections-reorder', {
            pageId: currentPageId,
            order: order.map(function (section) { return section.pwId; })
        }, 'Sortieren fehlgeschlagen')
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
        if (!currentPageId || isSaving || blockWhileDirty('Hinzufügen')) return;
        setStatus('Abschnitt anlegen...', 'is-loading');
        postJson(API_ROOT + 'sections-add', { pageId: currentPageId, layout: layout }, 'Abschnitt konnte nicht angelegt werden')
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
        postJson(API_ROOT + 'sections-delete', { pageId: currentPageId, sectionPwId: section.pwId }, 'Löschen fehlgeschlagen')
            .then(function () {
                if (activeSectionId === section.id) {
                    activeSectionId = null;
                    activeField = null;
                }
                markSectionDirty(section.id, false);
                return fetchSections({ keepStatus: true });
            })
            .then(function () {
                setStatus('Abschnitt gelöscht', 'is-ready');
            })
            .catch(function (error) {
                setStatus(error.message || 'Löschen fehlgeschlagen', 'is-error');
            });
    }

    function moveSection(sectionId, direction) {
        if (!currentPageId || isSaving || blockWhileDirty('Verschieben')) return;
        var index = sections.findIndex(function (section) { return section.id === sectionId; });
        if (index === -1) return;
        var targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= sections.length) return;
        reorderSections(index, targetIndex);
    }

    function duplicateSection(section) {
        if (!section || !currentPageId || isSaving || blockWhileDirty('Duplizieren')) return;
        var copyPayload = buildSavePayload(section);
        copyPayload.section_title = (section.title || 'Neuer Abschnitt') + ' (Kopie)';

        setStatus('Abschnitt duplizieren...', 'is-loading');
        postJson(API_ROOT + 'sections-add', {
            pageId: currentPageId,
            layout: section.layout || 'rich_text'
        }, 'Duplizieren fehlgeschlagen')
            .then(function (data) {
                var newSection = data.section || null;
                if (!newSection || !newSection.pwId) {
                    throw new Error('Neuer Abschnitt konnte nicht erstellt werden');
                }
                pendingSelectId = newSection.id || null;
                return postJson(API_ROOT + 'content-save', {
                    sectionPwId: newSection.pwId,
                    fields: copyPayload
                }, 'Kopie speichern fehlgeschlagen').then(function () {
                    return newSection;
                });
            })
            .then(function (newSection) {
                var order = sections.map(function (item) { return item.pwId; }).filter(Boolean);
                var sourceIndex = order.indexOf(section.pwId);
                if (sourceIndex === -1) {
                    order.push(newSection.pwId);
                } else {
                    order.splice(sourceIndex + 1, 0, newSection.pwId);
                }
                return postJson(API_ROOT + 'sections-reorder', {
                    pageId: currentPageId,
                    order: order
                }, 'Kopie einsortieren fehlgeschlagen');
            })
            .then(function () {
                return fetchSections({ keepStatus: true });
            })
            .then(function () {
                if (pendingSelectId && getSectionById(pendingSelectId)) {
                    selectSection(pendingSelectId);
                    pendingSelectId = null;
                }
                setStatus('Abschnitt dupliziert. Medien prüfen.', 'is-ready');
            })
            .catch(function (error) {
                setStatus(error.message || 'Duplizieren fehlgeschlagen', 'is-error');
            });
    }

    function openMediaModal(request) {
        if (!request || !request.sectionId) return;
        mediaRequest = {
            sectionId: request.sectionId,
            targetField: request.targetField || 'section_image'
        };
        mediaFiles = [];
        mediaGrid.innerHTML = '';
        mediaEmpty.textContent = 'Medien werden geladen…';
        mediaEmpty.style.display = 'block';
        mediaModal.classList.add('is-open');

        fetch(API_ROOT + 'media-files', { credentials: 'include' })
            .then(function (response) {
                return parseJson(response).then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error((data && data.error) || 'Medien konnten nicht geladen werden');
                    }
                    return data;
                });
            })
            .then(function (data) {
                mediaFiles = Array.isArray(data.files) ? data.files : [];
                renderMediaGrid();
            })
            .catch(function (error) {
                mediaGrid.innerHTML = '';
                mediaEmpty.textContent = error.message || 'Medien konnten nicht geladen werden';
                mediaEmpty.style.display = 'block';
            });
    }

    function closeMediaModal() {
        mediaRequest = null;
        mediaModal.classList.remove('is-open');
    }

    function renderMediaGrid() {
        mediaGrid.innerHTML = '';
        if (!mediaFiles.length) {
            mediaEmpty.textContent = 'Keine Medien gefunden.';
            mediaEmpty.style.display = 'block';
            return;
        }

        mediaEmpty.style.display = 'none';
        mediaFiles.forEach(function (file) {
            var card = document.createElement('button');
            card.type = 'button';
            card.className = 've-media-card';
            card.innerHTML =
                '<img src="' + escapeHtml(file.url || '') + '" alt="' + escapeHtml(file.assetTitle || file.fileName || 'Medium') + '">' +
                '<div class="ve-media-card-body">' +
                    '<strong>' + escapeHtml(file.assetTitle || file.fileName || 'Medium') + '</strong>' +
                    '<span>' + escapeHtml(file.fileName || '') + '</span>' +
                '</div>';
            card.addEventListener('click', function () {
                importMediaFile(file);
            });
            mediaGrid.appendChild(card);
        });
    }

    function importMediaFile(file) {
        var section = mediaRequest ? getSectionById(mediaRequest.sectionId) : null;
        if (!section || !section.pwId || !mediaRequest) return;

        mediaEmpty.textContent = 'Medium wird importiert…';
        mediaEmpty.style.display = 'block';

        postJson(API_ROOT + 'media-import', {
            repeaterItemId: section.pwId,
            targetField: mediaRequest.targetField || 'section_image',
            assetId: file.assetId,
            fileField: file.fileField,
            fileName: file.fileName
        }, 'Import fehlgeschlagen')
            .then(function () {
                closeMediaModal();
                return fetchSections({ keepStatus: true });
            })
            .then(function () {
                if (activeField) {
                    selectField(activeField, { scroll: false });
                } else if (section.id) {
                    selectSection(section.id, { scroll: false });
                }
                setStatus('Medium importiert', 'is-ready');
            })
            .catch(function (error) {
                mediaEmpty.textContent = error.message || 'Import fehlgeschlagen';
                mediaEmpty.style.display = 'block';
            });
    }

    function handleSectionAction(sectionId, action) {
        var section = getSectionById(sectionId);
        if (!section) return;
        switch (action) {
            case 'delete':
                if (!window.confirm('Abschnitt "' + (section.title || '') + '" wirklich löschen?')) return;
                deleteSection(section);
                break;
            case 'move-up':
                moveSection(sectionId, -1);
                break;
            case 'move-down':
                moveSection(sectionId, 1);
                break;
            case 'duplicate':
                duplicateSection(section);
                break;
        }
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
        if (blockWhileDirty('Hinzufügen')) return;
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
        saveDirtySections();
    });

    btnReset.addEventListener('click', function () {
        resetChanges();
    });

    btnModeEdit.addEventListener('click', function () {
        editorMode = 'edit';
        updateActions();
        syncIframeState();
    });

    btnModeBrowse.addEventListener('click', function () {
        editorMode = 'browse';
        updateActions();
        syncIframeState();
    });

    mediaClose.addEventListener('click', closeMediaModal);
    mediaModal.addEventListener('click', function (event) {
        if (event.target === mediaModal) {
            closeMediaModal();
        }
    });

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || typeof data.type !== 'string' || data.type.indexOf(PREFIX) !== 0) return;

        var action = data.type.slice(PREFIX.length);

        if (action === 'ready') {
            iframeReady = true;
            setStatus('Verbunden', 'is-ready');
            syncIframeState();
            if (currentPath) {
                fetchSections();
            }
            return;
        }

        if (action === 'section-click') {
            selectSection(data.sectionId, { scroll: false });
            return;
        }

        if (action === 'field-select') {
            selectField(data, { scroll: false });
            return;
        }

        if (action === 'field-change' || action === 'field-commit') {
            applyFieldChange(data);
            return;
        }

        if (action === 'media-request') {
            openMediaModal(data);
            return;
        }

        if (action === 'section-action') {
            handleSectionAction(data.sectionId, data.action);
        }
    });

    window.addEventListener('beforeunload', function (event) {
        if (!hasDirtySections()) return;
        event.preventDefault();
        event.returnValue = '';
    });

    updateActions();
})();
</script>
</body>
</html>
<?php exit;
