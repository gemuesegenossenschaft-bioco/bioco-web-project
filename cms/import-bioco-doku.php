<?php
/**
 * One-time import: all .md files under bioco-doku/docs/ into ProcessWire /internal-docs/ tree.
 *
 * Run: php cms/import-bioco-doku.php /path/to/bioco-doku
 * Default path: ../../bioco-doku (sibling of bioco-web-project)
 */

namespace ProcessWire;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

if (!function_exists(__NAMESPACE__ . '\\wire')) {
    require_once dirname(__DIR__) . '/index.php';
}

$dokuRoot = $argv[1] ?? dirname(__DIR__, 2) . '/bioco-doku';
$docsDir = $dokuRoot . '/docs';
if (!is_dir($docsDir)) {
    fwrite(STDERR, "Docs directory not found: {$docsDir}\n");
    exit(1);
}

$pages = wire('pages');
$templates = wire('templates');
$sanitizer = wire('sanitizer');

$root = $pages->get('/internal-docs/');
if (!$root->id) {
    fwrite(STDERR, "Run cms/setup-internal-docs.php first.\n");
    exit(1);
}

$tContainer = $templates->get('internal_docs_container');
$tLeaf = $templates->get('internal-doc');
if (!$tContainer || !$tLeaf) {
    fwrite(STDERR, "Templates internal_docs_container or internal-doc missing.\n");
    exit(1);
}

function guessTitleFromMarkdown(string $md): string {
    if (preg_match('/^#\s+(.+)$/m', $md, $m)) {
        return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return '';
}

function ensureChildPage(Page $parent, string $name, string $title, Template $template): Page {
    $name = wire('sanitizer')->pageName($name);
    if ($name === '') {
        $name = 'page-' . time();
    }
    $existing = $parent->child("name=$name, include=all");
    if ($existing && $existing->id) {
        return $existing;
    }
    $p = new Page();
    $p->template = $template;
    $p->parent = $parent;
    $p->name = $name;
    $p->title = $title ?: $name;
    $p->addStatus(Page::statusUnpublished);
    $p->save();
    return $p;
}

$rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($docsDir, \FilesystemIterator::SKIP_DOTS));
$imported = 0;
foreach ($rii as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
        continue;
    }
    $full = $file->getPathname();
    $rel = substr($full, strlen($docsDir) + 1);
    $rel = str_replace('\\', '/', $rel);

    $parts = explode('/', $rel);
    $filename = array_pop($parts);
    $parent = $root;

    foreach ($parts as $dir) {
        $label = ucfirst(str_replace(['-', '_'], ' ', $dir));
        $parent = ensureChildPage($parent, $dir, $label, $tContainer);
    }

    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $md = file_get_contents($full);
    if ($md === false) {
        continue;
    }
    $title = guessTitleFromMarkdown($md);
    if ($title === '') {
        $title = ucfirst(str_replace(['-', '_'], ' ', $baseName));
    }

    $leaf = ensureChildPage($parent, $baseName, $title, $tLeaf);
    $leaf->of(false);
    $leaf->title = $title;
    $leaf->body = $md;
    if ($leaf->hasField('doc_section') && count($parts)) {
        $leaf->doc_section = $parts[0];
    }
    $leaf->save();
    $imported++;
    fwrite(STDOUT, "Imported {$rel} -> {$leaf->path}\n");
}

fwrite(STDOUT, "Done. {$imported} pages.\n");
