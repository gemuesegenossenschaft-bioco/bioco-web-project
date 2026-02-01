<?php
// Run from project root: php scripts/setup-newsletter.php
// Bootstraps ProcessWire and installs newsletter essentials (template/page/module).

chdir(__DIR__ . '/..');

$pw = include 'index.php'; // boot ProcessWire

/** @var ProcessWire\ProcessWire $pw */
$modules   = $pw->modules;
$templates = $pw->templates;
$fields    = $pw->fields;
$pages     = $pw->pages;

echo "== Installing ProcessNewsletter module ==\n";
$modules->refresh();
if (!$modules->isInstalled('ProcessNewsletter')) {
    $modules->install('ProcessNewsletter');
    echo "Installed ProcessNewsletter\n";
} else {
    echo "ProcessNewsletter already installed\n";
}

echo "== Ensuring unsubscribe template ==\n";
$tpl = $templates->get('unsubscribe');
if (!$tpl->id) {
    $tpl = new ProcessWire\Template();
    $tpl->name = 'unsubscribe';
    $tpl->label = 'Unsubscribe';
    $tpl->noChildren = 1;
    $tpl->noParents = 0;
    $tpl->filename = 'unsubscribe'; // uses site/templates/unsubscribe.php
    $templates->save($tpl);
    echo "Created template 'unsubscribe'\n";
} else {
    echo "Template 'unsubscribe' already exists\n";
}

echo "== Ensuring unsubscribe page ==\n";
$page = $pages->get('/unsubscribe/');
if (!$page->id) {
    $page = new ProcessWire\Page();
    $page->template = $tpl;
    $page->parent = $pages->get('/');
    $page->title = 'Newsletter Abmeldung';
    $page->name = 'unsubscribe';
    $pages->save($page);
    echo "Created /unsubscribe/ page\n";
} else {
    echo "/unsubscribe/ page already exists\n";
}

echo "All done.\n";
