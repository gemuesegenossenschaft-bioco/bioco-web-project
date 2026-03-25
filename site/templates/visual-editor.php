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
if (!$user->hasRole('superuser') && !$user->hasRole('editor')) {
    http_response_code(403);
    echo 'Editor or superuser role required';
    exit;
}

$siteUrl = rtrim(getenv('NEXT_PUBLIC_SITE_URL') ?: 'https://bioco.ch', '/');
$draftSecret = getenv('PW_PREVIEW_TOKEN') ?: '';
$apiRoot = $config->urls->root . 'api/';
$componentRegistryJson = json_encode(biocoComponentRegistryEntries(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$pagesById = [];
$home = $pages->get('/content/homepage/');
if (!$home->id) {
    $home = $pages->get('/');
}
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
        if (trim($page->path, '/') === 'content/homepage') continue;
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
.ve-preset-modal {
    align-items: stretch;
    background: rgba(15, 23, 42, 0.72);
    display: none;
    inset: 0;
    justify-content: flex-end;
    position: fixed;
    z-index: 40;
}
.ve-preset-modal.is-open {
    display: flex;
}
.ve-preset-panel {
    background: #0f172a;
    border-left: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 520px;
    width: 100%;
}
.ve-preset-header {
    align-items: center;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    padding: 14px;
}
.ve-preset-controls {
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr 160px;
    padding: 12px 14px;
}
.ve-preset-list {
    display: grid;
    gap: 10px;
    overflow-y: auto;
    padding: 0 14px 14px;
}
.ve-preset-item {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 10px;
    padding: 10px;
}
.ve-preset-item strong {
    display: block;
    font-size: 13px;
}
.ve-preset-item p {
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.45;
    margin-top: 6px;
}
.ve-preset-item .ve-inline-actions {
    margin-top: 10px;
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
.ve-busy-overlay {
    align-items: center;
    background: rgba(15, 23, 42, 0.78);
    display: none;
    inset: 0;
    justify-content: center;
    position: fixed;
    z-index: 80;
}
.ve-busy-overlay.is-visible {
    display: flex;
}
.ve-busy-dialog {
    align-items: center;
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 18px;
    box-shadow: 0 28px 70px rgba(15, 23, 42, 0.45);
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 360px;
    padding: 28px 24px;
    text-align: center;
    width: calc(100vw - 32px);
}
.ve-busy-dialog strong {
    font-size: 18px;
    font-weight: 700;
}
.ve-busy-dialog p {
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.5;
}
.ve-busy-spinner {
    animation: ve-spin 0.9s linear infinite;
    border: 5px solid rgba(148, 163, 184, 0.22);
    border-radius: 999px;
    border-top-color: #8ab272;
    height: 54px;
    width: 54px;
}
@keyframes ve-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
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
        <button class="ve-btn" id="ve-btn-presets" type="button">Vorlagen</button>
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
                <button class="ve-btn" id="ve-btn-reset" type="button" disabled>Entwurf verwerfen</button>
                <button class="ve-btn ve-btn-primary" id="ve-btn-save" type="button" disabled>Publizieren</button>
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

<div class="ve-preset-modal" id="ve-preset-modal">
    <div class="ve-preset-panel">
        <div class="ve-preset-header">
            <strong>Abschnitt-Vorlagen</strong>
            <button class="ve-btn" id="ve-preset-close" type="button">Schliessen</button>
        </div>
        <div class="ve-preset-controls">
            <input id="ve-preset-search" type="text" placeholder="Suche...">
            <select id="ve-preset-category">
                <option value="">Alle Kategorien</option>
            </select>
        </div>
        <div class="ve-empty-state" id="ve-preset-empty">Vorlagen werden geladen…</div>
        <div class="ve-preset-list" id="ve-preset-list"></div>
    </div>
</div>

<div class="ve-busy-overlay" id="ve-busy-overlay" aria-hidden="true">
    <div class="ve-busy-dialog">
        <div class="ve-busy-spinner"></div>
        <strong id="ve-busy-label">Bitte warten…</strong>
        <p>Der Editor verarbeitet gerade deine Aktion. Andere Interaktionen sind kurz gesperrt.</p>
    </div>
</div>

<script>
(function () {
    var PREFIX = 'bioco:visual-editor:';
    var SITE_URL = <?= json_encode($siteUrl) ?>;
    var DRAFT_SECRET = <?= json_encode($draftSecret) ?>;
    var API_ROOT = <?= json_encode($apiRoot) ?>;
    var ALL_PAGES = <?= json_encode($contentPages, JSON_UNESCAPED_UNICODE) ?>;
    var COMPONENT_REGISTRY = <?= $componentRegistryJson ?: '[]' ?>;
    var LAYOUT_LABELS = {
        hero: 'Hero',
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
    var btnPresets = document.getElementById('ve-btn-presets');
    var btnPw = document.getElementById('ve-btn-pw');
    var btnSave = document.getElementById('ve-btn-save');
    var btnReset = document.getElementById('ve-btn-reset');
    var btnModeEdit = document.getElementById('ve-mode-edit');
    var btnModeBrowse = document.getElementById('ve-mode-browse');
    var mediaModal = document.getElementById('ve-media-modal');
    var mediaClose = document.getElementById('ve-media-close');
    var mediaEmpty = document.getElementById('ve-media-empty');
    var mediaGrid = document.getElementById('ve-media-grid');
    var presetModal = document.getElementById('ve-preset-modal');
    var presetClose = document.getElementById('ve-preset-close');
    var presetSearch = document.getElementById('ve-preset-search');
    var presetCategory = document.getElementById('ve-preset-category');
    var presetEmpty = document.getElementById('ve-preset-empty');
    var presetList = document.getElementById('ve-preset-list');
    var busyOverlay = document.getElementById('ve-busy-overlay');
    var busyLabel = document.getElementById('ve-busy-label');

    var currentPageId = null;
    var currentPath = null;
    var sections = [];
    var canonicalSections = [];
    var canonicalFingerprint = '';
    var activeSectionId = null;
    var activeField = null;
    var pendingSelectId = null;
    var iframeReady = false;
    var dirtySectionIds = {};
    var draftSavedAt = 0;
    var isSaving = false;
    var editorMode = 'edit';
    var mediaFiles = [];
    var mediaRequest = null;
    var presetItems = [];
    var presetTagsByComponent = {};
    var busyDepth = 0;
    var busyVisible = false;
    var busyTimer = null;
    var busyText = '';
    var waitingForIframeReady = false;
    var iframeReadyTimer = null;
    var draftAutosaveTimer = null;
    var statusTimer = null;
    var BUSY_DELAY = 320;
    var IFRAME_READY_TIMEOUT = 10000;
    var DRAFT_AUTOSAVE_DELAY = 180;
    var DRAFT_STORAGE_PREFIX = 'bioco-ve-draft:v1:';
    var HISTORY_LIMIT = 120;
    var historyPast = [];
    var historyFuture = [];
    var applyingHistory = false;

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

    function isHeroSection(sectionOrId) {
        if (!sectionOrId) return false;
        if (typeof sectionOrId === 'string') return sectionOrId === '__hero__';
        return sectionOrId.id === '__hero__' || sectionOrId.layout === 'hero';
    }

    function buildHomepageHeroSection(hero) {
        hero = hero || {};
        return {
            id: '__hero__',
            pwId: currentPageId,
            title: hero.headline || 'Hero',
            eyebrow: hero.subtitle || '',
            image: hero.image || '',
            imageAlt: hero.imageAlt || '',
            layout: 'hero',
            theme: 'default'
        };
    }

    function getSortableSections() {
        return sections.filter(function (section) {
            return !isHeroSection(section);
        });
    }

    function cloneJson(value, fallback) {
        if (value == null) return fallback;
        try {
            return JSON.parse(JSON.stringify(value));
        } catch (error) {
            return fallback;
        }
    }

    function createDraftId() {
        return 'draft:' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }

    function getDraftStorageKey(pageId, path) {
        if (!pageId || !path) return '';
        return DRAFT_STORAGE_PREFIX + String(pageId) + ':' + String(path);
    }

    function clearStatusLater() {
        if (statusTimer) {
            clearTimeout(statusTimer);
        }
        statusTimer = window.setTimeout(function () {
            if (!isSaving && !isBusy()) {
                if (hasDraftChanges()) {
                    setStatus('Entwurf lokal gespeichert', 'is-loading');
                } else {
                    setStatus('Verbunden', 'is-ready');
                }
            }
        }, 2600);
    }

    function setTransientStatus(text, cls) {
        setStatus(text, cls);
        clearStatusLater();
    }

    function normalizeDraftMedia(draftMedia, fallbackTargetField) {
        if (!draftMedia || !draftMedia.assetId || !draftMedia.fileField || !draftMedia.fileName || !draftMedia.url) {
            return null;
        }
        return {
            assetId: Number(draftMedia.assetId),
            fileField: String(draftMedia.fileField),
            fileName: String(draftMedia.fileName),
            targetField: String(draftMedia.targetField || fallbackTargetField || 'section_image'),
            url: String(draftMedia.url),
            assetTitle: draftMedia.assetTitle ? String(draftMedia.assetTitle) : ''
        };
    }

    function cloneConfigValue(value) {
        return cloneJson(value, value);
    }

    function getComponentDefaultConfig(rawKey) {
        var meta = resolveComponentMeta(rawKey);
        return cloneJson(meta && meta.defaultConfig ? meta.defaultConfig : {}, {});
    }

    function mergeSectionConfig(rawKey, config) {
        var merged = getComponentDefaultConfig(rawKey);
        var next = cloneJson(config, {});
        if (!next || typeof next !== 'object' || Array.isArray(next)) {
            return merged || {};
        }
        Object.keys(next).forEach(function (key) {
            merged[key] = cloneConfigValue(next[key]);
        });
        return merged;
    }

    function normalizeDraftSection(section) {
        if (!section || !section.id) return null;
        var normalized = {
            id: String(section.id),
            title: String(section.title || ''),
            text: String(section.text || ''),
            layout: String(section.layout || 'rich_text'),
            theme: String(section.theme || 'default')
        };
        if (section.pwId) normalized.pwId = Number(section.pwId);
        if (typeof section.sort === 'number') normalized.sort = section.sort;
        if (section.eyebrow) normalized.eyebrow = String(section.eyebrow);
        if (section.component) normalized.component = String(section.component);
        if (section.component || section.config) normalized.config = mergeSectionConfig(section.component, section.config);
        if (section.bgColor) normalized.bgColor = String(section.bgColor);
        if (section.imageOverlay) normalized.imageOverlay = String(section.imageOverlay);
        if (section.imageBrightness != null) normalized.imageBrightness = Number(section.imageBrightness);
        if (section.imageContrast != null) normalized.imageContrast = Number(section.imageContrast);
        if (section.imageSaturate != null) normalized.imageSaturate = Number(section.imageSaturate);
        if (section.image) normalized.image = String(section.image);
        if (section.imageAlt != null) normalized.imageAlt = String(section.imageAlt || '');
        if (Array.isArray(section.buttons) && section.buttons.length) {
            normalized.buttons = section.buttons
                .slice(0, 2)
                .map(function (button, index) {
                    return {
                        text: String((button && button.text) || ''),
                        href: String((button && button.href) || ''),
                        variant: String((button && button.variant) || (index === 0 ? 'primary' : 'secondary'))
                    };
                })
                .filter(function (button) {
                    return button.text.trim() || button.href.trim();
                });
        }
        if (Array.isArray(section.images) && section.images.length) {
            normalized.images = cloneJson(section.images, []);
        }
        if (Array.isArray(section.media) && section.media.length) {
            normalized.media = cloneJson(section.media, []);
        }
        if (Array.isArray(section.mediaItems)) {
            normalized.mediaItems = cloneJson(section.mediaItems, []);
        }
        if (section.video && typeof section.video === 'object') {
            normalized.video = {
                url: String(section.video.url || ''),
                title: String(section.video.title || '')
            };
        }
        if (section.imageData) {
            normalized.imageData = cloneJson(section.imageData, null);
        }
        var targetField = isHeroSection(section) ? 'hero_image' : 'section_image';
        var draftMedia = normalizeDraftMedia(section.draftMedia, targetField);
        if (draftMedia) {
            normalized.draftMedia = draftMedia;
        }
        if (Array.isArray(section.draftMediaItems)) {
            normalized.draftMediaItems = section.draftMediaItems
                .map(function (item) { return normalizeDraftMedia(item, 'section_images'); })
                .filter(Boolean);
        }
        return normalized;
    }

    function cloneSections(items) {
        return (items || []).map(function (section) {
            return normalizeDraftSection(section);
        }).filter(Boolean);
    }

    function buildComparableSections(items) {
        return cloneSections(items).map(function (section) {
            return {
                id: section.id,
                pwId: section.pwId || null,
                title: section.title || '',
                text: section.text || '',
                layout: section.layout || 'rich_text',
                theme: section.theme || 'default',
                eyebrow: section.eyebrow || '',
                component: section.component || '',
                config: cloneJson(section.config || {}, {}),
                bgColor: section.bgColor || '',
                imageOverlay: section.imageOverlay || '',
                image: section.image || '',
                imageAlt: section.imageAlt || '',
                imageBrightness: section.imageBrightness == null ? null : section.imageBrightness,
                imageContrast: section.imageContrast == null ? null : section.imageContrast,
                imageSaturate: section.imageSaturate == null ? null : section.imageSaturate,
                video: cloneJson(section.video || null, null),
                media: cloneJson(section.media || [], []),
                mediaItems: cloneJson(section.mediaItems || [], []),
                buttons: cloneJson(section.buttons || [], []),
                draftMedia: cloneJson(section.draftMedia || null, null),
                draftMediaItems: cloneJson(section.draftMediaItems || [], [])
            };
        });
    }

    function hasDraftChanges() {
        return JSON.stringify(buildComparableSections(sections)) !== JSON.stringify(buildComparableSections(canonicalSections));
    }

    function recomputeDirtySections() {
        var nextDirty = {};
        var canonicalById = {};
        var canonicalOrder = canonicalSections.map(function (section) { return section.id; }).join('|');
        var currentOrder = sections.map(function (section) { return section.id; }).join('|');

        canonicalSections.forEach(function (section) {
            canonicalById[section.id] = JSON.stringify(buildComparableSections([section])[0] || {});
        });

        sections.forEach(function (section) {
            var comparable = JSON.stringify(buildComparableSections([section])[0] || {});
            if (!canonicalById[section.id] || canonicalById[section.id] !== comparable) {
                nextDirty[section.id] = true;
            }
        });

        if (canonicalOrder !== currentOrder) {
            sections.forEach(function (section) {
                nextDirty[section.id] = true;
            });
        }

        dirtySectionIds = nextDirty;
    }

    function clearCurrentDraftStorage() {
        var key = getDraftStorageKey(currentPageId, currentPath);
        if (!key) return;
        try {
            window.sessionStorage.removeItem(key);
        } catch (error) {
            // ignore storage errors
        }
    }

    function readServerDraft(pageId, path) {
        if (!pageId || !path) return Promise.resolve(null);
        var query = '?pageId=' + encodeURIComponent(String(pageId)) + '&path=' + encodeURIComponent(String(path));
        return fetch(API_ROOT + 'content/draft' + query, { credentials: 'include' })
            .then(function (response) {
                return parseJson(response).then(function (data) {
                    if (!response.ok || !data.success) return null;
                    return data.draft || null;
                });
            })
            .catch(function () { return null; });
    }

    function saveServerDraft(payload) {
        if (!payload || !payload.pageId || !payload.path || !payload.baseFingerprint || !Array.isArray(payload.sections)) {
            return Promise.resolve();
        }
        return postJson(API_ROOT + 'content/draft', payload, 'Entwurf konnte nicht gespeichert werden').catch(function () {});
    }

    function deleteServerDraft(pageId, path) {
        if (!pageId || !path) return Promise.resolve();
        var query = '?pageId=' + encodeURIComponent(String(pageId)) + '&path=' + encodeURIComponent(String(path));
        return fetch(API_ROOT + 'content/draft' + query, {
            method: 'DELETE',
            credentials: 'include',
        }).catch(function () {});
    }

    function buildCurrentDraftPayload() {
        return {
            pageId: currentPageId,
            path: currentPath,
            baseFingerprint: canonicalFingerprint || '',
            baseSections: cloneSections(canonicalSections),
            sections: cloneSections(sections),
        };
    }

    function persistCurrentDraftNow() {
        if (!currentPageId || !currentPath) return;
        if (draftAutosaveTimer) {
            clearTimeout(draftAutosaveTimer);
            draftAutosaveTimer = null;
        }
        if (!hasDraftChanges()) {
            clearCurrentDraftStorage();
            deleteServerDraft(currentPageId, currentPath);
            draftSavedAt = 0;
            return;
        }
        var key = getDraftStorageKey(currentPageId, currentPath);
        if (!key) return;
        draftSavedAt = Date.now();
        try {
            window.sessionStorage.setItem(key, JSON.stringify({
                pageId: currentPageId,
                path: currentPath,
                baseFingerprint: canonicalFingerprint || '',
                savedAt: draftSavedAt,
                sections: cloneSections(sections),
                activeSectionId: activeSectionId,
                activeField: cloneJson(activeField, null)
            }));
        } catch (error) {
            // ignore storage errors
        }
        saveServerDraft(buildCurrentDraftPayload());
    }

    function scheduleDraftAutosave() {
        if (!currentPageId || !currentPath) return;
        if (draftAutosaveTimer) {
            clearTimeout(draftAutosaveTimer);
        }
        draftAutosaveTimer = window.setTimeout(function () {
            persistCurrentDraftNow();
            if (!isSaving && !isBusy() && hasDraftChanges()) {
                setStatus('Entwurf lokal gespeichert', 'is-loading');
                clearStatusLater();
            }
        }, DRAFT_AUTOSAVE_DELAY);
    }

    function resetHistory() {
        historyPast = [];
        historyFuture = [];
    }

    function pushHistorySnapshot() {
        if (applyingHistory) return;
        historyPast.push(cloneSections(sections));
        if (historyPast.length > HISTORY_LIMIT) {
            historyPast.shift();
        }
        historyFuture = [];
    }

    function undoChange() {
        if (!historyPast.length || isBusy()) return;
        applyingHistory = true;
        historyFuture.push(cloneSections(sections));
        sections = cloneSections(historyPast.pop());
        refreshDraftUi({ persist: true, message: 'Rückgängig' });
        applyingHistory = false;
    }

    function redoChange() {
        if (!historyFuture.length || isBusy()) return;
        applyingHistory = true;
        historyPast.push(cloneSections(sections));
        sections = cloneSections(historyFuture.pop());
        refreshDraftUi({ persist: true, message: 'Wiederhergestellt' });
        applyingHistory = false;
    }

    function readStoredDraft(pageId, path) {
        var key = getDraftStorageKey(pageId, path);
        if (!key) return null;
        try {
            var raw = window.sessionStorage.getItem(key);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function restoreStoredDraft(nextSections, fingerprint) {
        var stored = readStoredDraft(currentPageId, currentPath);
        if (!stored || !Array.isArray(stored.sections)) {
            return {
                sections: nextSections,
                restored: false,
                message: ''
            };
        }
        if ((stored.baseFingerprint || '') !== (fingerprint || '')) {
            clearCurrentDraftStorage();
            return {
                sections: nextSections,
                restored: false,
                message: 'Veralteter Entwurf verworfen, weil die Seite inzwischen geändert wurde.'
            };
        }
        var restoredSections = cloneSections(stored.sections);
        if (!restoredSections.length) {
            clearCurrentDraftStorage();
            return {
                sections: nextSections,
                restored: false,
                message: ''
            };
        }
        activeSectionId = stored.activeSectionId && restoredSections.some(function (section) {
            return section.id === stored.activeSectionId;
        }) ? stored.activeSectionId : activeSectionId;
        if (stored.activeField && activeSectionId === stored.activeField.sectionId) {
            activeField = stored.activeField;
        } else if (activeField && !restoredSections.some(function (section) { return section.id === activeField.sectionId; })) {
            activeField = null;
        }
        draftSavedAt = Number(stored.savedAt || 0);
        return {
            sections: restoredSections,
            restored: true,
            message: 'Lokaler Entwurf wiederhergestellt.'
        };
    }

    function restoreServerDraft(draft, nextSections, fingerprint) {
        if (!draft || !Array.isArray(draft.sections)) {
            return {
                sections: nextSections,
                restored: false,
                message: ''
            };
        }
        if ((draft.baseFingerprint || '') !== (fingerprint || '')) {
            deleteServerDraft(currentPageId, currentPath);
            return {
                sections: nextSections,
                restored: false,
                message: 'Server-Entwurf veraltet und verworfen.'
            };
        }
        var restoredSections = cloneSections(draft.sections);
        if (!restoredSections.length) {
            return {
                sections: nextSections,
                restored: false,
                message: ''
            };
        }
        return {
            sections: restoredSections,
            restored: true,
            message: 'Server-Entwurf wiederhergestellt.'
        };
    }

    function setCanonicalSnapshot(nextSections, nextFingerprint) {
        canonicalSections = cloneSections(nextSections);
        canonicalFingerprint = String(nextFingerprint || '');
    }

    function reconcileSelection() {
        if (activeSectionId && !getSectionById(activeSectionId)) {
            activeSectionId = null;
            activeField = null;
        }
        if (activeField && activeSectionId !== activeField.sectionId) {
            activeField = null;
        }
    }

    function refreshDraftUi(options) {
        options = options || {};
        recomputeDirtySections();
        reconcileSelection();
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
        }
        syncIframeState(options.message || '');
        if (options.persist !== false) {
            scheduleDraftAutosave();
        }
    }

    function applyDraftMedia(section, file, targetField) {
        var mediaRef = {
            assetId: file.assetId,
            assetTitle: file.assetTitle || '',
            fileField: file.fileField,
            fileName: file.fileName,
            targetField: targetField,
            url: file.url
        };
        section.draftMedia = mediaRef;
        section.draftMediaItems = [cloneJson(mediaRef, null)];
        section.image = file.url;
        section.imageAlt = section.imageAlt || file.assetTitle || section.title || '';
        section.imageData = {
            url: file.url,
            description: section.imageAlt || '',
        };
        section.images = [{
            url: file.url,
            alt: section.imageAlt || ''
        }];
        section.media = [{
            url: file.url,
            alt: section.imageAlt || '',
            type: 'image'
        }];
    }

    function appendDraftMedia(section, file, targetField) {
        var mediaRef = {
            assetId: file.assetId,
            assetTitle: file.assetTitle || '',
            fileField: file.fileField,
            fileName: file.fileName,
            targetField: targetField || 'section_images',
            url: file.url
        };
        var alt = file.assetTitle || section.imageAlt || section.title || '';
        var mediaItems = Array.isArray(section.media) ? section.media.slice() : [];
        mediaItems.push({ url: file.url, alt: alt, type: 'image' });
        section.media = mediaItems;
        section.images = mediaItems.map(function (item) {
            return { url: item.url, alt: item.alt || '' };
        });
        if (!section.image && section.images.length) {
            section.image = section.images[0].url;
            section.imageAlt = section.images[0].alt || '';
        }
        if (!Array.isArray(section.draftMediaItems)) {
            section.draftMediaItems = [];
        }
        section.draftMediaItems.push(mediaRef);
    }

    function buildDraftPayloadSections() {
        return cloneSections(sections);
    }

    function normalizeComponentLookupKey(value) {
        return String(value || '').trim().toLowerCase();
    }

    function resolveComponentMeta(rawKey) {
        var lookup = normalizeComponentLookupKey(rawKey);
        if (!lookup) return null;
        for (var i = 0; i < COMPONENT_REGISTRY.length; i++) {
            var entry = COMPONENT_REGISTRY[i] || {};
            if (normalizeComponentLookupKey(entry.key) === lookup) return entry;
            if (Array.isArray(entry.aliases)) {
                for (var j = 0; j < entry.aliases.length; j++) {
                    if (normalizeComponentLookupKey(entry.aliases[j]) === lookup) return entry;
                }
            }
        }
        return null;
    }

    function formatComponentLabel(rawKey) {
        var raw = String(rawKey || '').trim();
        if (!raw) return '';
        var meta = resolveComponentMeta(raw);
        if (!meta) return raw;
        return raw === meta.key ? meta.label + ' (' + meta.key + ')' : meta.label + ' (' + raw + ')';
    }

    function formatSectionListTitle(section) {
        var title = String(section.title || '(kein Titel)');
        if (!section.component) return title;
        var componentLabel = resolveComponentMeta(section.component);
        return (componentLabel ? componentLabel.label : section.component) + ' · ' + title;
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

    function isBusy() {
        return busyDepth > 0;
    }

    function renderBusyOverlay() {
        busyOverlay.classList.toggle('is-visible', busyVisible);
        busyOverlay.setAttribute('aria-hidden', busyVisible ? 'false' : 'true');
        busyLabel.textContent = busyText || 'Bitte warten…';
    }

    function beginBusy(label) {
        busyDepth += 1;
        busyText = label || busyText || 'Bitte warten…';
        updateActions();
        if (busyVisible) {
            renderBusyOverlay();
            syncIframeState();
            return;
        }
        if (busyTimer) {
            clearTimeout(busyTimer);
        }
        busyTimer = window.setTimeout(function () {
            busyVisible = true;
            renderBusyOverlay();
            syncIframeState();
        }, BUSY_DELAY);
        syncIframeState();
    }

    function endBusy() {
        if (busyDepth > 0) {
            busyDepth -= 1;
        }
        updateActions();
        if (busyDepth > 0) {
            syncIframeState();
            return;
        }
        if (busyTimer) {
            clearTimeout(busyTimer);
            busyTimer = null;
        }
        busyVisible = false;
        busyText = '';
        renderBusyOverlay();
        syncIframeState();
    }

    function runWithBusy(label, task) {
        beginBusy(label);
        return Promise.resolve()
            .then(task)
            .finally(function () {
                endBusy();
            });
    }

    function clearIframeReadyTimeout() {
        if (iframeReadyTimer) {
            clearTimeout(iframeReadyTimer);
            iframeReadyTimer = null;
        }
    }

    function scheduleIframeReadyTimeout() {
        clearIframeReadyTimeout();
        iframeReadyTimer = window.setTimeout(function () {
            if (!waitingForIframeReady || iframeReady) return;
            waitingForIframeReady = false;
            busyDepth = 0;
            busyVisible = false;
            busyText = '';
            if (busyTimer) {
                clearTimeout(busyTimer);
                busyTimer = null;
            }
            renderBusyOverlay();
            updateActions();
            syncIframeState('Vorschau konnte nicht verbunden werden');
            setStatus('Vorschau konnte nicht verbunden werden', 'is-error');
        }, IFRAME_READY_TIMEOUT);
    }

    function updateModeButtons() {
        btnModeEdit.classList.toggle('is-active', editorMode === 'edit');
        btnModeBrowse.classList.toggle('is-active', editorMode === 'browse');
    }

    function syncIframeState(message) {
        sendToIframe('save-state', {
            mode: editorMode,
            dirty: hasDraftChanges(),
            saving: isSaving,
            busy: isBusy(),
            busyLabel: busyText || '',
            message: message || '',
            selectedSectionId: activeSectionId,
            presetTagsByComponent: presetTagsByComponent
        });
    }

    function updateActions() {
        var hasActive = !!getSectionById(activeSectionId);
        btnAdd.disabled = !currentPageId || isSaving || isBusy();
        btnPw.disabled = !currentPageId || isBusy();
        btnRefresh.disabled = !currentPageId || isBusy();
        btnPresets.disabled = isBusy();
        btnSave.disabled = !hasDraftChanges() || isSaving || isBusy();
        btnReset.disabled = !hasDraftChanges() || isSaving || isBusy();
        btnModeEdit.disabled = isBusy();
        btnModeBrowse.disabled = isBusy();
        pageSelect.disabled = isBusy();
        if (isSaving) {
            btnSave.textContent = 'Publiziert...';
        } else {
            btnSave.textContent = 'Publizieren';
        }
        if (!hasActive) {
            btnReset.disabled = !hasDraftChanges() || isSaving;
        }
        updateModeButtons();
    }

    function confirmDiscardChanges() {
        if (!hasDraftChanges()) return true;
        return window.confirm('Lokalen Entwurf wirklich verwerfen?');
    }

    function blockWhileDirty(actionLabel) {
        if (!hasDraftChanges()) return false;
        var guarded = {
            'Seitenwechsel': true,
            'Neu laden': true
        };
        if (!guarded[actionLabel || '']) return false;
        return !window.confirm('Ungespeicherte Änderungen vorhanden. "' + (actionLabel || 'Weiter') + '" trotzdem ausführen?');
    }

    function blockWhileBusy() {
        return isBusy();
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
            case 'video': return 'Video';
            case 'videoTitle': return 'Video Titel';
            default: return field.field;
        }
    }

    function fieldHint(field) {
        if (!field) return 'Klicke ein Feld in der Vorschau an, um inline zu bearbeiten.';
        switch (field.field) {
            case 'text':
                return 'Rich Text wird direkt im iframe bearbeitet. Änderungen bleiben lokal, bis du publizierst.';
            case 'media':
                return 'Alt-Text und Medienauswahl laufen über das Overlay direkt im iframe.';
            case 'component':
                return 'Komponentenname wird inline geändert. Komponentenspezifische Optionen sind in V1 noch begrenzt.';
            case 'video':
            case 'videoTitle':
                return 'Video-URL und Titel können direkt im Overlay bearbeitet werden.';
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
        var hasDraft = hasDraftChanges();

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
                    '<p>' + (hasDraft ? 'Lokaler Entwurf gespeichert und noch nicht publiziert.' : 'Keine offenen Entwürfe.') + '</p>' +
                '</div>';
            updateActions();
            return;
        }

        fieldEditor.innerHTML =
            '<div class="ve-info-card">' +
                '<strong>Aktiver Abschnitt</strong>' +
                '<p>' + escapeHtml(section.title || '(kein Titel)') + '</p>' +
                '<p>Layout: ' + escapeHtml(LAYOUT_LABELS[section.layout] || section.layout || 'Abschnitt') + (section.component ? ' · Komponente: ' + escapeHtml(formatComponentLabel(section.component)) : '') + '</p>' +
            '</div>' +
            '<div class="ve-info-card">' +
                '<strong>Aktives Feld</strong>' +
                '<p>' + escapeHtml(fieldLabel(activeField)) + '</p>' +
                '<p>' + escapeHtml(fieldHint(activeField)) + '</p>' +
            '</div>' +
            '<div class="ve-info-card">' +
                '<strong>Entwurfsstatus</strong>' +
                '<p>' + (hasDraft ? 'Lokaler Entwurf aktiv' + (dirtyCount ? ' · ' + dirtyCount + ' markierte Abschnitt(e).' : '') : 'Kein unveröffentlichter Entwurf.') + '</p>' +
            '</div>' +
            '<div class="ve-info-card">' +
                '<strong>Hinweis</strong>' +
                '<p>Alle Änderungen bleiben lokal im Browser, bis du "Publizieren" klickst.</p>' +
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

        return runWithBusy(options.busyLabel || 'Abschnitte laden…', function () {
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
                var nextSections = Array.isArray(data.sections) ? data.sections.slice() : [];
                if (currentPath === '/' && data.hero) {
                    nextSections.unshift(buildHomepageHeroSection(data.hero));
                }
                setCanonicalSnapshot(nextSections, data.fingerprint || '');
                return readServerDraft(currentPageId, currentPath)
                    .then(function (serverDraft) {
                        var restore = restoreServerDraft(serverDraft, nextSections, canonicalFingerprint);
                        if (!restore.restored) {
                            restore = restoreStoredDraft(nextSections, canonicalFingerprint);
                        }
                        sections = cloneSections(restore.sections);
                        if (pendingSelectId && getSectionById(pendingSelectId)) {
                            activeSectionId = pendingSelectId;
                        } else if (activeSectionId && !getSectionById(activeSectionId)) {
                            activeSectionId = null;
                            activeField = null;
                        }
                        pendingSelectId = null;
                        resetHistory();
                        refreshDraftUi({ persist: false, message: restore.message || '' });
                        if (!options.keepStatus) {
                            setStatus(restore.message || (hasDraftChanges() ? 'Entwurf lokal gespeichert' : 'Verbunden'), hasDraftChanges() ? 'is-loading' : 'is-ready');
                        } else if (restore.message) {
                            setTransientStatus(restore.message, 'is-loading');
                        }
                    });
            })
            .catch(function (error) {
                setStatus(error.message || 'Fehler beim Laden', 'is-error');
                throw error;
            });
        });
    }

    function loadPage(pageId, path, options) {
        options = options || {};
        currentPageId = pageId;
        currentPath = path;
        iframeReady = false;
        sections = [];
        canonicalSections = [];
        canonicalFingerprint = '';
        pendingSelectId = null;
        activeSectionId = null;
        activeField = null;
        clearDirtySections();
        resetHistory();
        draftSavedAt = 0;
        isSaving = false;

        pageSelect.value = pageId + '|' + path;
        renderSectionList();
        renderFieldEditor();
        updateActions();
        setStatus('Vorschau laden...', 'is-loading');
        waitingForIframeReady = true;
        beginBusy('Vorschau laden…');
        scheduleIframeReadyTimeout();

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
        if (isBusy()) return;
        var section = getSectionById(payload.sectionId);
        if (!section) return;
        var historyFields = {
            layout: true,
            theme: true,
            bgColor: true,
            imageOverlay: true,
            component: true,
            config: true,
            mediaItems: true,
            videoUrl: true,
            videoTitle: true
        };
        if (payload.__commit || historyFields[payload.field]) {
            pushHistorySnapshot();
        }

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
                section.config = mergeSectionConfig(section.component, section.config);
                notifySectionUpdate(section.id, 'component', section.component);
                notifySectionUpdate(section.id, 'config', section.config);
                break;
            case 'config':
                section.config = mergeSectionConfig(section.component, section.config);
                if (payload.configKey) {
                    section.config[payload.configKey] = payload.value;
                } else if (payload.value && typeof payload.value === 'object') {
                    section.config = mergeSectionConfig(section.component, payload.value);
                }
                notifySectionUpdate(section.id, 'config', section.config);
                break;
            case 'imageAlt':
                section.imageAlt = payload.value || '';
                if (section.imageData) {
                    section.imageData.description = section.imageAlt || '';
                }
                if (Array.isArray(section.images)) {
                    section.images = section.images.map(function (image) {
                        image.alt = section.imageAlt || '';
                        return image;
                    });
                }
                if (Array.isArray(section.media)) {
                    section.media = section.media.map(function (item) {
                        if ((item.type || 'image') === 'image') {
                            item.alt = section.imageAlt || '';
                        }
                        return item;
                    });
                }
                notifySectionUpdate(section.id, 'imageAlt', section.imageAlt);
                break;
            case 'videoUrl':
                section.video = cloneJson(section.video || {}, {});
                section.video.url = payload.value || '';
                notifySectionUpdate(section.id, 'video', section.video);
                break;
            case 'videoTitle':
                section.video = cloneJson(section.video || {}, {});
                section.video.title = payload.value || '';
                notifySectionUpdate(section.id, 'video', section.video);
                notifySectionUpdate(section.id, 'videoTitle', section.video.title || '');
                break;
            case 'mediaItems':
                if (!Array.isArray(payload.value)) return;
                section.media = payload.value
                    .map(function (item) {
                        if (!item || !item.url) return null;
                        return {
                            url: String(item.url),
                            alt: String(item.alt || ''),
                            type: String(item.type || 'image')
                        };
                    })
                    .filter(Boolean);
                section.mediaItems = cloneJson(section.media, []);
                section.images = section.media
                    .filter(function (item) { return (item.type || 'image') === 'image'; })
                    .map(function (item) {
                        return {
                            url: item.url,
                            alt: item.alt || ''
                        };
                    });
                if (section.images.length) {
                    section.image = section.images[0].url;
                    section.imageAlt = section.images[0].alt || section.imageAlt || '';
                    section.imageData = {
                        url: section.image,
                        description: section.imageAlt || ''
                    };
                } else {
                    section.image = '';
                    section.imageData = null;
                }
                if (Array.isArray(section.draftMediaItems) && section.draftMediaItems.length) {
                    var refsByUrl = {};
                    section.draftMediaItems.forEach(function (ref) {
                        if (!ref || !ref.url) return;
                        if (!refsByUrl[ref.url]) refsByUrl[ref.url] = [];
                        refsByUrl[ref.url].push(ref);
                    });
                    section.draftMediaItems = section.media.map(function (item) {
                        if (!refsByUrl[item.url] || !refsByUrl[item.url].length) return null;
                        return refsByUrl[item.url].shift();
                    }).filter(Boolean);
                } else {
                    section.draftMediaItems = [];
                }
                notifySectionUpdate(section.id, 'media', section.media);
                notifySectionUpdate(section.id, 'images', section.images);
                notifySectionUpdate(section.id, 'image', section.image || '');
                break;
            case 'imageBrightness':
                section.imageBrightness = Number(payload.value || 1);
                notifySectionUpdate(section.id, 'imageBrightness', section.imageBrightness);
                break;
            case 'imageContrast':
                section.imageContrast = Number(payload.value || 1);
                notifySectionUpdate(section.id, 'imageContrast', section.imageContrast);
                break;
            case 'imageSaturate':
                section.imageSaturate = Number(payload.value || 1);
                notifySectionUpdate(section.id, 'imageSaturate', section.imageSaturate);
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
        scheduleDraftAutosave();
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
            var isHero = isHeroSection(section);
            var item = document.createElement('li');
            item.className = 've-section-item' + (section.id === activeSectionId ? ' is-active' : '');
            item.draggable = !isHero;

            var drag = document.createElement('span');
            drag.className = 've-section-drag';
            drag.textContent = isHero ? '★' : '⠿';

            var info = document.createElement('div');
            info.className = 've-section-info';

            var title = document.createElement('div');
            title.className = 've-section-title';
            title.textContent = formatSectionListTitle(section);
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
                component.textContent = formatComponentLabel(section.component);
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

            if (!isHero) {
                var duplicateBtn = document.createElement('button');
                duplicateBtn.className = 've-icon-btn';
                duplicateBtn.type = 'button';
                duplicateBtn.title = 'Abschnitt kopieren';
                duplicateBtn.textContent = '⧉';
                duplicateBtn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    if (blockWhileBusy()) return;
                    if (blockWhileDirty('Kopieren')) return;
                    duplicateSection(section);
                });

                var deleteBtn = document.createElement('button');
                deleteBtn.className = 've-icon-btn';
                deleteBtn.type = 'button';
                deleteBtn.title = 'Abschnitt löschen';
                deleteBtn.textContent = '✕';
                deleteBtn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    if (blockWhileBusy()) return;
                    if (blockWhileDirty('Löschen')) return;
                    if (!window.confirm('Abschnitt "' + (section.title || '') + '" wirklich löschen?')) return;
                    deleteSection(section);
                });

                actions.appendChild(duplicateBtn);
                actions.appendChild(deleteBtn);
            }

            item.appendChild(drag);
            item.appendChild(info);
            item.appendChild(actions);

            item.addEventListener('click', function () {
                if (blockWhileBusy()) return;
                selectSection(section.id);
            });

            item.addEventListener('dragstart', function (event) {
                if (isHero) {
                    event.preventDefault();
                    return;
                }
                if (blockWhileBusy()) {
                    event.preventDefault();
                    return;
                }
                if (blockWhileDirty('Sortieren')) {
                    event.preventDefault();
                    return;
                }
                event.dataTransfer.setData('text/plain', section.id);
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
                if (isHero) return;
                var fromSectionId = event.dataTransfer.getData('text/plain');
                if (!fromSectionId || fromSectionId === section.id) return;
                reorderSectionsById(fromSectionId, section.id);
            });

            sectionList.appendChild(item);
        });
    }

    function buildSavePayload(section) {
        if (isHeroSection(section)) {
            return {
                hero_headline: section.title || '',
                hero_subtitle: section.eyebrow || '',
                image_alt: section.imageAlt || ''
            };
        }
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
            button2_variant: button2.variant || 'secondary',
            section_config: cloneJson(section.config || {}, {})
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
                        var error = new Error((data && data.error) || fallbackError);
                        error.data = data || {};
                        throw error;
                    }
                    return data;
                });
            });
    }

    function valueEquals(a, b) {
        return JSON.stringify(a == null ? null : a) === JSON.stringify(b == null ? null : b);
    }

    function indexSectionsById(items) {
        var map = {};
        (items || []).forEach(function (section) {
            if (!section || !section.id) return;
            map[section.id] = cloneJson(section, {});
        });
        return map;
    }

    function resolvePublishConflict(baseSections, serverSections, localSections) {
        var baseById = indexSectionsById(baseSections);
        var serverById = indexSectionsById(serverSections);
        var localById = indexSectionsById(localSections);
        var mergedById = {};
        var ids = {};
        Object.keys(baseById).forEach(function (id) { ids[id] = true; });
        Object.keys(serverById).forEach(function (id) { ids[id] = true; });
        Object.keys(localById).forEach(function (id) { ids[id] = true; });
        var idList = Object.keys(ids);
        var conflicts = [];

        idList.forEach(function (id) {
            var base = baseById[id] || null;
            var server = serverById[id] || null;
            var local = localById[id] || null;

            if (!server && local) {
                mergedById[id] = cloneJson(local, {});
                return;
            }
            if (server && !local) {
                mergedById[id] = cloneJson(server, {});
                return;
            }
            if (!server && !local) return;

            var merged = cloneJson(local, {});
            var keys = {};
            Object.keys(base || {}).forEach(function (k) { keys[k] = true; });
            Object.keys(server || {}).forEach(function (k) { keys[k] = true; });
            Object.keys(local || {}).forEach(function (k) { keys[k] = true; });

            Object.keys(keys).forEach(function (key) {
                var baseVal = base ? base[key] : undefined;
                var serverVal = server ? server[key] : undefined;
                var localVal = local ? local[key] : undefined;
                if (valueEquals(localVal, serverVal)) {
                    merged[key] = cloneJson(localVal, localVal);
                    return;
                }
                if (valueEquals(baseVal, serverVal)) {
                    merged[key] = cloneJson(localVal, localVal);
                    return;
                }
                if (valueEquals(baseVal, localVal)) {
                    merged[key] = cloneJson(serverVal, serverVal);
                    return;
                }
                var useLocal = window.confirm('Konflikt in Abschnitt "' + id + '" Feld "' + key + '". OK = lokal behalten, Abbrechen = Server übernehmen.');
                merged[key] = useLocal ? cloneJson(localVal, localVal) : cloneJson(serverVal, serverVal);
                conflicts.push({ sectionId: id, field: key, keep: useLocal ? 'local' : 'server' });
            });
            mergedById[id] = normalizeDraftSection(merged);
        });

        var baseOrder = (baseSections || []).map(function (section) { return section.id; }).join('|');
        var serverOrderList = (serverSections || []).map(function (section) { return section.id; });
        var localOrderList = (localSections || []).map(function (section) { return section.id; });
        var serverOrder = serverOrderList.join('|');
        var localOrder = localOrderList.join('|');
        var keepLocalOrder = true;
        if (baseOrder !== serverOrder && baseOrder !== localOrder && serverOrder !== localOrder) {
            keepLocalOrder = window.confirm('Abschnittsreihenfolge-Konflikt. OK = lokale Reihenfolge behalten, Abbrechen = Server-Reihenfolge übernehmen.');
        } else if (baseOrder === localOrder && baseOrder !== serverOrder) {
            keepLocalOrder = false;
        }

        var orderedIds = keepLocalOrder ? localOrderList.slice() : serverOrderList.slice();
        Object.keys(mergedById).forEach(function (id) {
            if (orderedIds.indexOf(id) === -1) orderedIds.push(id);
        });
        var mergedSections = orderedIds.map(function (id) {
            return mergedById[id];
        }).filter(Boolean);

        return {
            mergedSections: cloneSections(mergedSections),
            conflicts: conflicts,
            keepLocalOrder: keepLocalOrder
        };
    }

    function saveDirtySections() {
        if (isSaving || !hasDraftChanges() || isBusy()) return;
        var baseSectionsSnapshot = cloneSections(canonicalSections);

        isSaving = true;
        updateActions();
        setStatus('Publiziert...', 'is-loading');
        syncIframeState();

        runWithBusy('Änderungen publizieren…', function () {
            return postJson(API_ROOT + 'content-publish', {
                pageId: currentPageId,
                path: currentPath,
                baseFingerprint: canonicalFingerprint,
                sections: buildDraftPayloadSections()
            }, 'Publizieren fehlgeschlagen');
        })
            .then(function (data) {
                var nextSections = Array.isArray(data.sections) ? data.sections.slice() : [];
                if (currentPath === '/' && data.hero) {
                    nextSections.unshift(buildHomepageHeroSection(data.hero));
                }
                setCanonicalSnapshot(nextSections, data.fingerprint || '');
                sections = cloneSections(nextSections);
                resetHistory();
                clearDirtySections();
                clearCurrentDraftStorage();
                deleteServerDraft(currentPageId, currentPath);
                draftSavedAt = 0;
                refreshDraftUi({ persist: false, message: 'Publiziert' });
                setStatus('Publiziert', 'is-ready');
                sendToIframe('save-result', { success: true });
            })
            .catch(function (error) {
                var resolvedConflict = false;
                if (error && error.data && (Array.isArray(error.data.sections) || error.data.hero)) {
                    var canonicalFromError = Array.isArray(error.data.sections) ? error.data.sections.slice() : [];
                    if (currentPath === '/' && error.data.hero) {
                        canonicalFromError.unshift(buildHomepageHeroSection(error.data.hero));
                    }
                    if (error.data && error.data.fingerprint) {
                        var resolved = resolvePublishConflict(baseSectionsSnapshot, canonicalFromError, sections);
                        setCanonicalSnapshot(canonicalFromError, error.data.fingerprint || '');
                        sections = cloneSections(resolved.mergedSections);
                        clearDirtySections();
                        refreshDraftUi({
                            message: resolved.conflicts.length
                                ? 'Konflikte gelöst. Bitte prüfen und erneut publizieren.'
                                : 'Serveränderungen übernommen. Bitte erneut publizieren.'
                        });
                        resolvedConflict = true;
                    } else {
                        setCanonicalSnapshot(canonicalFromError, error.data.fingerprint || '');
                    }
                }
                if (resolvedConflict) {
                    setStatus('Konflikte gelöst. Erneut publizieren.', 'is-loading');
                } else {
                    setStatus(error.message || 'Publizieren fehlgeschlagen', 'is-error');
                }
                sendToIframe('save-result', {
                    success: false,
                    error: error.message || 'Publizieren fehlgeschlagen'
                });
            })
            .finally(function () {
                isSaving = false;
                updateActions();
                syncIframeState();
            });
    }

    function resetChanges() {
        if (isBusy()) return;
        if (!confirmDiscardChanges()) return;
        clearCurrentDraftStorage();
        deleteServerDraft(currentPageId, currentPath);
        sections = cloneSections(canonicalSections);
        resetHistory();
        clearDirtySections();
        activeField = null;
        if (activeSectionId && !getSectionById(activeSectionId)) {
            activeSectionId = null;
        }
        refreshDraftUi({ persist: false, message: 'Entwurf verworfen' });
        setStatus('Entwurf verworfen', 'is-ready');
    }

    function reorderSectionsById(fromSectionId, toSectionId) {
        if (!currentPageId || isSaving || isBusy() || blockWhileDirty('Sortieren')) return;
        pushHistorySnapshot();
        var hero = sections.filter(function (section) { return isHeroSection(section); });
        var order = getSortableSections().slice();
        var fromIndex = order.findIndex(function (section) { return section.id === fromSectionId; });
        var toIndex = order.findIndex(function (section) { return section.id === toSectionId; });
        if (fromIndex === -1 || toIndex === -1 || fromIndex === toIndex) return;
        var moved = order.splice(fromIndex, 1)[0];
        order.splice(toIndex, 0, moved);
        sections = hero.concat(order);
        refreshDraftUi();
        setTransientStatus('Reihenfolge im Entwurf aktualisiert', 'is-loading');
    }

    function addSection(layout) {
        if (!currentPageId || isSaving || isBusy() || blockWhileDirty('Hinzufügen')) return;
        pushHistorySnapshot();
        var newSection = normalizeDraftSection({
            id: createDraftId(),
            title: 'Neuer Abschnitt',
            text: '<p></p>',
            layout: layout || 'rich_text',
            theme: 'default',
            buttons: []
        });
        sections = cloneSections(sections);
        sections.push(newSection);
        activeSectionId = newSection.id;
        activeField = null;
        refreshDraftUi();
        setTransientStatus('Abschnitt zum Entwurf hinzugefügt', 'is-loading');
    }

    function deleteSection(section) {
        if (!currentPageId || !section || isHeroSection(section) || isSaving || isBusy()) return;
        pushHistorySnapshot();
        sections = sections.filter(function (item) { return item.id !== section.id; });
        if (activeSectionId === section.id) {
            activeSectionId = null;
            activeField = null;
        }
        refreshDraftUi();
        setTransientStatus('Abschnitt im Entwurf gelöscht', 'is-loading');
    }

    function moveSection(sectionId, direction) {
        if (!currentPageId || isSaving || isBusy() || blockWhileDirty('Verschieben')) return;
        var order = getSortableSections();
        var index = order.findIndex(function (section) { return section.id === sectionId; });
        if (index === -1) return;
        var targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= order.length) return;
        reorderSectionsById(order[index].id, order[targetIndex].id);
    }

    function duplicateSection(section) {
        if (!section || isHeroSection(section) || !currentPageId || isSaving || isBusy() || blockWhileDirty('Duplizieren')) return;
        pushHistorySnapshot();
        var newSection = cloneSections([section])[0];
        if (!newSection) return;
        var sourceIndex = sections.findIndex(function (item) { return item.id === section.id; });
        newSection.id = createDraftId();
        delete newSection.pwId;
        newSection.title = (section.title || 'Neuer Abschnitt') + ' (Kopie)';
        if (newSection.draftMedia) {
            newSection.draftMedia = cloneJson(newSection.draftMedia, null);
        }
        if (sourceIndex === -1) {
            sections.push(newSection);
        } else {
            sections.splice(sourceIndex + 1, 0, newSection);
        }
        activeSectionId = newSection.id;
        activeField = null;
        refreshDraftUi();
        setTransientStatus('Abschnitt im Entwurf dupliziert. Medien prüfen.', 'is-loading');
    }

    function openMediaModal(request) {
        if (!request || !request.sectionId || isBusy()) return;
        mediaRequest = {
            sectionId: request.sectionId,
            targetField: request.targetField || (request.sectionId === '__hero__' ? 'hero_image' : 'section_image')
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
        if (isBusy()) return;
        var section = mediaRequest ? getSectionById(mediaRequest.sectionId) : null;
        if (!section || !mediaRequest) return;

        pushHistorySnapshot();
        var targetField = mediaRequest.targetField || (isHeroSection(section) ? 'hero_image' : 'section_image');
        if (targetField === 'section_images') {
            appendDraftMedia(section, file, targetField);
        } else {
            applyDraftMedia(section, file, targetField);
        }
        closeMediaModal();
        refreshDraftUi();
        if (activeField) {
            selectField(activeField, { scroll: false });
        } else if (section.id) {
            selectSection(section.id, { scroll: false });
        }
        setTransientStatus('Medium im Entwurf ausgewählt', 'is-loading');
    }

    function handleSectionAction(sectionId, action) {
        var section = getSectionById(sectionId);
        if (!section || isHeroSection(section)) return;
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

    function rebuildPresetTagsMap() {
        presetTagsByComponent = {};
        presetItems.forEach(function (preset) {
            if (!preset || !preset.payload || !preset.payload.component) return;
            var key = String(preset.payload.component);
            if (!presetTagsByComponent[key]) presetTagsByComponent[key] = [];
            if (preset.category && presetTagsByComponent[key].indexOf(String(preset.category)) === -1) {
                presetTagsByComponent[key].push(String(preset.category));
            }
            if (preset.name && presetTagsByComponent[key].indexOf(String(preset.name)) === -1) {
                presetTagsByComponent[key].push(String(preset.name));
            }
        });
    }

    function renderPresetCategories() {
        var current = presetCategory.value || '';
        var categories = {};
        presetItems.forEach(function (preset) {
            if (!preset || !preset.category) return;
            categories[preset.category] = true;
        });
        presetCategory.innerHTML = '<option value="">Alle Kategorien</option>';
        Object.keys(categories).sort().forEach(function (name) {
            var option = document.createElement('option');
            option.value = name;
            option.textContent = name;
            presetCategory.appendChild(option);
        });
        presetCategory.value = current;
    }

    function filteredPresets() {
        var query = String(presetSearch.value || '').trim().toLowerCase();
        var category = String(presetCategory.value || '');
        return presetItems.filter(function (preset) {
            if (category && preset.category !== category) return false;
            if (!query) return true;
            var haystack = [preset.name, preset.description, preset.category, preset.payload && preset.payload.component]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            return haystack.indexOf(query) !== -1;
        });
    }

    function renderPresetList() {
        presetList.innerHTML = '';
        var items = filteredPresets();
        if (!items.length) {
            presetEmpty.textContent = 'Keine Vorlagen gefunden.';
            presetEmpty.style.display = 'block';
            return;
        }
        presetEmpty.style.display = 'none';
        items.forEach(function (preset) {
            var card = document.createElement('div');
            card.className = 've-preset-item';
            card.innerHTML =
                '<strong>' + escapeHtml(preset.name || 'Vorlage') + '</strong>' +
                (preset.category ? '<span class="ve-layout-badge">' + escapeHtml(preset.category) + '</span>' : '') +
                '<p>' + escapeHtml(preset.description || '') + '</p>';
            var actions = document.createElement('div');
            actions.className = 've-inline-actions';
            var insertBtn = document.createElement('button');
            insertBtn.type = 'button';
            insertBtn.className = 've-btn ve-btn-primary';
            insertBtn.textContent = 'Einfügen';
            insertBtn.addEventListener('click', function () {
                insertPreset(preset);
            });
            actions.appendChild(insertBtn);
            card.appendChild(actions);
            presetList.appendChild(card);
        });
    }

    function loadPresets() {
        return fetch(API_ROOT + 'content/presets', { credentials: 'include' })
            .then(function (response) {
                return parseJson(response).then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error((data && data.error) || 'Vorlagen konnten nicht geladen werden');
                    }
                    return data;
                });
            })
            .then(function (data) {
                presetItems = Array.isArray(data.presets) ? data.presets : [];
                rebuildPresetTagsMap();
                renderPresetCategories();
                renderPresetList();
                syncIframeState();
            })
            .catch(function (error) {
                presetItems = [];
                presetEmpty.textContent = error.message || 'Vorlagen konnten nicht geladen werden';
                presetEmpty.style.display = 'block';
            });
    }

    function openPresetModal() {
        if (isBusy()) return;
        if (!presetItems.length) {
            loadPresets().then(function () {
                renderPresetList();
            });
        } else {
            renderPresetList();
        }
        presetModal.classList.add('is-open');
    }

    function closePresetModal() {
        presetModal.classList.remove('is-open');
    }

    function insertPreset(preset) {
        if (!preset || !preset.payload) return;
        if (isBusy() || !currentPageId) return;
        pushHistorySnapshot();
        var payload = cloneJson(preset.payload, {}) || {};
        var section = normalizeDraftSection(Object.assign({
            id: createDraftId(),
            title: preset.name || 'Neuer Abschnitt',
            text: '<p></p>',
            layout: 'rich_text',
            theme: 'default',
            buttons: [],
        }, payload));
        if (!section) return;
        sections = cloneSections(sections);
        sections.push(section);
        activeSectionId = section.id;
        activeField = null;
        closePresetModal();
        refreshDraftUi();
        setTransientStatus('Vorlage eingefügt', 'is-loading');
    }

    pageSelect.addEventListener('change', function () {
        if (isBusy()) return;
        if (!this.value) return;
        var parts = this.value.split('|');
        var nextPageId = parseInt(parts[0], 10);
        var nextPath = parts[1];
        if (!nextPageId || !nextPath) return;
        if (currentPageId === nextPageId && currentPath === nextPath) return;
        if (blockWhileDirty('Seitenwechsel')) {
            this.value = currentPageId && currentPath ? (currentPageId + '|' + currentPath) : '';
            return;
        }
        persistCurrentDraftNow();
        loadPage(nextPageId, nextPath);
    });

    btnRefresh.addEventListener('click', function () {
        if (isBusy()) return;
        if (!currentPageId || !currentPath) return;
        if (blockWhileDirty('Neu laden')) return;
        persistCurrentDraftNow();
        loadPage(currentPageId, currentPath, { force: true });
    });

    btnPresets.addEventListener('click', function () {
        openPresetModal();
    });

    btnPw.addEventListener('click', function () {
        if (isBusy()) return;
        if (!currentPageId) return;
        window.open('<?= $config->urls->admin ?>page/edit/?id=' + currentPageId, '_blank');
    });

    btnAdd.addEventListener('click', function () {
        if (isBusy()) return;
        if (!currentPageId) return;
        if (blockWhileDirty('Hinzufügen')) return;
        var activeSection = getSectionById(activeSectionId);
        var nextLayout = activeSection && activeSection.layout ? activeSection.layout : 'rich_text';
        addSection(nextLayout);
    });

    btnSave.addEventListener('click', function () {
        saveDirtySections();
    });

    btnReset.addEventListener('click', function () {
        resetChanges();
    });

    btnModeEdit.addEventListener('click', function () {
        if (isBusy()) return;
        editorMode = 'edit';
        updateActions();
        syncIframeState();
    });

    btnModeBrowse.addEventListener('click', function () {
        if (isBusy()) return;
        editorMode = 'browse';
        updateActions();
        syncIframeState();
    });

    mediaClose.addEventListener('click', closeMediaModal);
    mediaModal.addEventListener('click', function (event) {
        if (isBusy()) return;
        if (event.target === mediaModal) {
            closeMediaModal();
        }
    });

    presetClose.addEventListener('click', closePresetModal);
    presetModal.addEventListener('click', function (event) {
        if (isBusy()) return;
        if (event.target === presetModal) {
            closePresetModal();
        }
    });
    presetSearch.addEventListener('input', renderPresetList);
    presetCategory.addEventListener('change', renderPresetList);

    window.addEventListener('keydown', function (event) {
        if (isBusy()) return;
        var isMeta = !!(event.metaKey || event.ctrlKey);
        if (isMeta && event.key.toLowerCase() === 's') {
            event.preventDefault();
            saveDirtySections();
            return;
        }
        if (isMeta && event.key.toLowerCase() === 'z') {
            event.preventDefault();
            if (event.shiftKey) {
                redoChange();
            } else {
                undoChange();
            }
            return;
        }
        if (event.key === 'Escape') {
            if (mediaModal.classList.contains('is-open')) closeMediaModal();
            if (presetModal.classList.contains('is-open')) closePresetModal();
        }
    });

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || typeof data.type !== 'string' || data.type.indexOf(PREFIX) !== 0) return;

        var action = data.type.slice(PREFIX.length);

        if (action === 'ready') {
            iframeReady = true;
            clearIframeReadyTimeout();
            setStatus('Verbunden', 'is-ready');
            syncIframeState();
            if (currentPath) {
                fetchSections({ busyLabel: 'Abschnitte laden…' })
                    .catch(function () {})
                    .finally(function () {
                        if (waitingForIframeReady) {
                            waitingForIframeReady = false;
                            endBusy();
                        }
                    });
            } else if (waitingForIframeReady) {
                waitingForIframeReady = false;
                endBusy();
            }
            return;
        }

        if (isBusy()) {
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
            var nextPayload = Object.assign({}, data, { __commit: action === 'field-commit' });
            applyFieldChange(nextPayload);
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
        persistCurrentDraftNow();
        if (!hasDraftChanges()) return;
        event.preventDefault();
        event.returnValue = '';
    });

    renderBusyOverlay();
    updateActions();
    loadPresets();
})();
</script>
</body>
</html>
<?php exit;
