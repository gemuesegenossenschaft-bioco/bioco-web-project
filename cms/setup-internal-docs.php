<?php
/**
 * Create internal handbook templates, fields, and /internal-docs/ root in ProcessWire.
 *
 * Run: php cms/setup-internal-docs.php
 * Or from web (localhost only): same guard as other cms scripts.
 */

namespace ProcessWire;

if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Forbidden: run from CLI or localhost');
}

if (!function_exists(__NAMESPACE__ . '\\wire')) {
    require_once dirname(__DIR__) . '/index.php';
}

$fields = wire('fields');
$templates = wire('templates');
$fieldgroups = wire('fieldgroups');
$pages = wire('pages');
$modules = wire('modules');

$log = [];

try {
    $log[] = '=== Setup internal docs ===';

    $field = $fields->get('doc_section');
    if (!$field) {
        $field = new Field();
        $field->type = $modules->get('FieldtypeText');
        $field->name = 'doc_section';
        $field->label = 'Dokumentations-Bereich';
        $field->description = 'Optional: Kapitel-Label (z.B. Redaktion, Technisch)';
        $fields->save($field);
        $log[] = 'Created field doc_section';
    } else {
        $log[] = 'Field doc_section exists';
    }

    $bodyField = $fields->get('body');
    if (!$bodyField) {
        throw new \RuntimeException('Field body is required; create it in ProcessWire first.');
    }

    $ensureTemplate = function (string $name, string $label, array $fieldNames, int $noChildren) use ($fields, $templates, $fieldgroups, &$log) {
        $template = $templates->get($name);
        if (!$template) {
            $fg = new Fieldgroup();
            $fg->name = $name;
            $fieldgroups->save($fg);

            $template = new Template();
            $template->name = $name;
            $template->label = $label;
            $template->fieldgroup = $fg;
            $template->noChildren = $noChildren;
            $templates->save($template);
            $log[] = "Created template {$name}";
        } else {
            $log[] = "Template exists {$name}";
        }

        $fg = $template->fieldgroup;
        foreach ($fieldNames as $fn) {
            $f = $fields->get($fn);
            if (!$f) {
                continue;
            }
            if (!$fg->hasField($f)) {
                $fg->add($f);
            }
        }
        $fieldgroups->save($fg);
        return $template;
    };

    $ensureTemplate('internal_docs_root', 'Interne Doku (Wurzel)', ['title'], 0);
    $ensureTemplate('internal_docs_container', 'Interne Doku (Ordner)', ['title'], 0);
    $ensureTemplate('internal-doc', 'Interne Doku (Seite)', ['title', 'doc_section', 'body'], 1);

    $root = $pages->get('/internal-docs/');
    if (!$root->id) {
        $parent = $pages->get('/');
        if (!$parent->id) {
            throw new \RuntimeException('Site root page not found.');
        }
        $root = new Page();
        $root->template = $templates->get('internal_docs_root');
        $root->parent = $parent;
        $root->name = 'internal-docs';
        $root->title = 'Interne Dokumentation';
        $root->addStatus(Page::statusUnpublished);
        $root->save();
        $log[] = 'Created page /internal-docs/ (unpublished)';
    } else {
        $log[] = 'Page /internal-docs/ exists';
        if (!$root->isUnpublished()) {
            $root->of(false);
            $root->addStatus(Page::statusUnpublished);
            $root->save();
            $log[] = 'Set /internal-docs/ to unpublished';
        }
    }

    echo implode("\n", $log) . "\nDone.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
