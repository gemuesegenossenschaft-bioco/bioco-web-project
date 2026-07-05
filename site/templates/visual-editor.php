<?php namespace ProcessWire;

/**
 * Visual Editor bootstrap (G.2 rebuild).
 *
 * This file is intentionally small: auth gate, PW-side data assembly, one
 * JSON config blob, the static HTML skeleton and the <script> tag for the
 * committed bundle site/templates/visual-editor-app.js (built locally via
 * `npm run build:ve-shell` from frontend/visual-editor-shell/ — the server
 * cannot build). All behavior lives in the TypeScript shell app; all UI
 * copy lives in frontend/visual-editor-shell/strings.ts.
 *
 * Outputs a standalone HTML page and exits before ProcessWire admin chrome
 * renders (ob_end_clean at top / exit at bottom are required).
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

/* ---------------------------------------------------------------- */
/* Data assembly                                                     */
/* ---------------------------------------------------------------- */

$siteUrl = rtrim(getenv('NEXT_PUBLIC_SITE_URL') ?: 'https://bioco.ch', '/');
$draftSecret = getenv('PW_PREVIEW_TOKEN') ?: '';
$apiRoot = $config->urls->root . 'api/';
$adminUrl = $config->urls->admin;
$visualEditorUrl = $config->urls->root . 'visual-editor/';
$pageEditUrl = $config->urls->admin . 'page/edit/';

// Focus-field map shared with admin.js focused edit mode (passthrough).
$focusFieldConfig = [];
$focusFieldConfigPath = __DIR__ . '/visual-editor-focus-fields.json';
if (is_file($focusFieldConfigPath)) {
    $decodedFocusFieldConfig = json_decode((string) file_get_contents($focusFieldConfigPath), true);
    if (is_array($decodedFocusFieldConfig)) {
        $focusFieldConfig = $decodedFocusFieldConfig;
    }
}

// Editable pages: templates with a content_sections field. NOTE: `has_field`
// is NOT a valid PW selector — iterate templates and use hasField().
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

// Collection pages (events/blog) are not section-based: they are page lists
// edited directly in ProcessWire. Add new collections here plus a matching
// `collection-create` type branch in api.php.
$collections = [
    '/aktuelles' => [
        'type' => 'event',
        'root' => '/aktuelles',
        'label' => 'Events',
        'listEndpoint' => 'content/events',
        'addLabel' => 'Neuen Event erstellen',
    ],
];

// Extra postMessage origins (besides site + www twin), e.g. local previews.
$allowedOrigins = is_array($config->veAllowedOrigins) ? array_values($config->veAllowedOrigins) : [];

$veConfig = [
    'siteUrl' => $siteUrl,
    'apiRoot' => $apiRoot,
    'adminUrl' => $adminUrl,
    'pageEditUrl' => $pageEditUrl,
    'visualEditorUrl' => $visualEditorUrl,
    'draftSecret' => $draftSecret,
    'pages' => $contentPages,
    'collections' => $collections,
    'componentRegistry' => biocoComponentRegistryEntries(),
    'focusFields' => $focusFieldConfig,
    'allowedOrigins' => $allowedOrigins,
];
// JSON_HEX_TAG keeps "</script>" sequences from breaking out of the blob.
$veConfigJson = json_encode($veConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?: '{}';

$appFile = __DIR__ . '/visual-editor-app.js';
$appVersion = is_file($appFile) ? substr((string) md5_file($appFile), 0, 10) : (string) time();
$appUrl = $config->urls->templates . 'visual-editor-app.js?v=' . $appVersion;

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Visual Editor</title>
</head>
<body>

<div class="ve-toolbar">
    <span class="ve-toolbar-logo">bioco Visual Editor</span>
    <span class="ve-toolbar-spacer" aria-hidden="true"></span>
    <span id="ve-status" class="ve-status">Nicht verbunden</span>
    <div class="ve-mode-switch">
        <button class="ve-btn ve-mode-btn is-active" id="ve-mode-edit" type="button">Edit</button>
        <button class="ve-btn ve-mode-btn" id="ve-mode-browse" type="button">Browse</button>
    </div>
    <div class="ve-toolbar-actions">
        <button class="ve-btn" id="ve-btn-refresh" type="button">Neu laden</button>
        <button class="ve-btn" id="ve-btn-presets" type="button">Vorlagen</button>
        <button class="ve-btn" id="ve-btn-pw" type="button">PW Admin</button>
        <a href="<?= $adminUrl ?>" class="ve-btn">Zurück</a>
    </div>
</div>

<div class="ve-main">
    <aside class="ve-sidebar">
        <div class="ve-sidebar-header">
            <div class="ve-sidebar-page">
                <span class="ve-sidebar-kicker">Seite</span>
                <span class="ve-sidebar-title" id="ve-current-page-title">Startseite</span>
                <span class="ve-sidebar-path" id="ve-current-page-path">In der Vorschau navigieren, um eine Seite zu bearbeiten.</span>
            </div>
            <button class="ve-btn ve-btn-primary" id="ve-btn-add" type="button" disabled>Abschnitt hinzufügen</button>
        </div>

        <div class="ve-section-list-wrap">
            <div class="ve-empty-state" id="ve-empty-list">In der Vorschau navigieren, um eine Seite zu bearbeiten.</div>
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

<div class="ve-add-modal" id="ve-add-modal">
    <div class="ve-add-panel">
        <div class="ve-add-header">
            <strong>Abschnitt hinzufügen</strong>
            <button class="ve-btn" id="ve-add-close" type="button">Schliessen</button>
        </div>
        <div class="ve-add-controls">
            <input id="ve-add-search" type="text" placeholder="Typ suchen...">
            <select id="ve-add-filter">
                <option value="">Alle</option>
            </select>
        </div>
        <div class="ve-add-scroll" id="ve-add-scroll"></div>
    </div>
</div>

<div class="ve-busy-overlay" id="ve-busy-overlay" aria-hidden="true">
    <div class="ve-busy-dialog">
        <div class="ve-busy-spinner"></div>
        <strong id="ve-busy-label">Bitte warten…</strong>
        <p>Der Editor verarbeitet gerade deine Aktion. Andere Interaktionen sind kurz gesperrt.</p>
    </div>
</div>

<script type="application/json" id="ve-config"><?= $veConfigJson ?></script>
<script src="<?= $appUrl ?>"></script>
</body>
</html>
<?php exit;
