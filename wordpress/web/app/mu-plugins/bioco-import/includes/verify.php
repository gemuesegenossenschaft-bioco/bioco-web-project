<?php
/**
 * `wp bioco verify`: re-reads each seeded page's post_content, parses the
 * native Divi block comments back out with WordPress core's parse_blocks(),
 * and compares each recovered tree against what the SAME plan builder
 * (section-map.php) says the seed should have produced — so import and
 * verify can never silently drift apart from each other.
 *
 * Blocks are matched back to seed section_id(s) via a plain HTML comment
 * marker ("<!-- bioco:section id1,id2 -->") written immediately before every
 * block by bioco_import_build_desired_content() — not by position, so
 * verify still works if a human reordered content in the block editor.
 * parse_blocks() itself does the HTML-comment/JSON parsing; this file never
 * hand-parses raw post_content.
 */

if (!defined('ABSPATH')) exit;

// section-label ("id" or "id1,id2") => ordered list of matching blocks.
function bioco_import_parse_marked_blocks($content) {
    $blocks = parse_blocks((string) $content);
    $result = [];
    $pendingLabel = null;

    foreach ($blocks as $block) {
        if ($block['blockName'] === null) {
            if (preg_match('/<!--\s*bioco:section\s+([^\s>]+)\s*-->/', (string) $block['innerHTML'], $m)) {
                $pendingLabel = $m[1];
            }
            continue;
        }
        if ($pendingLabel !== null) {
            $result[$pendingLabel][] = [
                'blockName' => (string) $block['blockName'],
                'data' => is_array($block['attrs']['data'] ?? null) ? $block['attrs']['data'] : [],
                'block' => $block,
            ];
            $pendingLabel = null;
        }
    }
    return $result;
}

function bioco_import_take_marked_block(array &$blocks, $sectionLabel) {
    if (empty($blocks[$sectionLabel])) return null;
    return array_shift($blocks[$sectionLabel]);
}

function bioco_import_verify_seed(array $seed, array &$report) {
    $slug = (string) $seed['slug'];
    $post = bioco_import_find_page($slug);
    if (!$post) {
        bioco_import_report_row($report, $slug, '', '', 'verify-missing', 'Seite existiert nicht in WordPress.');
        return;
    }

    $actualBlocks = bioco_import_parse_marked_blocks((string) $post->post_content);
    $plan = bioco_import_build_page_plan($seed);

    foreach ($plan as $item) {
        if ($item['type'] === 'skip') continue; // already reported as a warn during import; nothing was ever written

        $sectionLabel = implode(',', $item['section_ids']);
        $values = $item['values'];
        $imageWarnings = [];
        // 'verify' is not 'apply', so this only ever reuses an already
        // sideloaded attachment (by source URL) or resolves to nothing —
        // it never downloads during verify.
        bioco_import_resolve_pending_images($values, 'verify', $imageWarnings);

        $found = bioco_import_take_marked_block($actualBlocks, $sectionLabel);
        if ($found === null) {
            bioco_import_report_row($report, $slug, $sectionLabel, $item['block'], 'verify-missing', 'Kein bioco:section-Marker + Block dafür in post_content gefunden.');
            continue;
        }

        if ($found['blockName'] !== 'divi/section') {
            bioco_import_report_row($report, $slug, $sectionLabel, $item['block'], 'verify-mismatch', "Block-Name: erwartet 'divi/section', gefunden '{$found['blockName']}'.");
            continue;
        }

        $composerItem = $item;
        $composerItem['values'] = $values;
        $expectedMarkup = serialize_block(Bioco_Import_Divi_Composer::section($composerItem));
        $actualMarkup = serialize_block($found['block']);
        if ($expectedMarkup === $actualMarkup) {
            bioco_import_report_row($report, $slug, $sectionLabel, $item['block'], 'verify-match', 'Native Divi-Struktur stimmt überein.');
        } else {
            bioco_import_report_row(
                $report, $slug, $sectionLabel, $item['block'], 'verify-mismatch',
                'Native Divi-Struktur weicht ab — erwartet: ' . bioco_import_excerpt($expectedMarkup) . ' — gefunden: ' . bioco_import_excerpt($actualMarkup) . '.'
            );
        }
    }
}

// Entry point used by the CLI command for `wp bioco verify`.
function bioco_import_run_verify(array $seeds, array &$report) {
    foreach ($seeds as $seed) {
        bioco_import_verify_seed($seed, $report);
    }
}

// ---------------------------------------------------------------------------
// Runtime verification (`wp bioco verify --runtime`): the gate for normal
// code releases. It must accept editorially changed text/layout, so it never
// compares against seed values and does NOT whitelist seed-composer module
// names — any module the active renderer produces output for is legitimate.
//
// Two DIFFERENT kinds of proof are deliberately kept apart:
//  - delimiter/structure validation of the SAVED markup on the real
//    WP_Block_Parser tokenizer (below), and
//  - output proof via the REAL render_block(): the required PAGE must render
//    meaningful HTML in total (visible text or media/form/table/iframe/svg
//    markup; scripts, styles and comments never count). The judgment is
//    deliberately PER PAGE, not per section: real Divi decorative modules
//    (divider, spacer) render an EMPTY element — their visible line/height
//    lives in CSS (:before, spacer height) — so a text section plus a
//    decorative divider/spacer section is legitimate editorial layout and
//    must not fail. Structural defects (malformed delimiters, stray block
//    comments, markers without a section, sections without a row) and
//    renderer exceptions fail wherever they occur. Read-only CLI context:
//    no request token, no mail, no form submission, no persistence. The
//    22-route HTTP render smoke gate remains separate external evidence.
// ---------------------------------------------------------------------------

// Renders one saved section under the ACTUAL page context: native/dynamic
// modules call get_the_ID()/get_permalink() and must not render for ID 0 or
// a previous page. The context is set up and restored around the render.
function bioco_import_runtime_render_section(array $block, $post) {
    $previousPost = $GLOBALS['post'] ?? null;
    if ($post) {
        $GLOBALS['post'] = $post;
        if (function_exists('setup_postdata')) setup_postdata($post);
    }
    try {
        return (string) render_block($block);
    } finally {
        if (function_exists('wp_reset_postdata')) wp_reset_postdata();
        if ($previousPost === null) {
            unset($GLOBALS['post']);
        } else {
            $GLOBALS['post'] = $previousPost;
        }
    }
}

// Output proof for one saved section: renders it with the real render
// callbacks under the actual page context; a renderer exception is recorded
// and the page check continues instead of crashing the gate. Returns the
// rendered HTML, or null when the renderer failed.
function bioco_import_runtime_render_section_html(array $block, $post, array &$corrupt) {
    if (!function_exists('render_block')) {
        $corrupt[] = 'render_block() ist nicht verfuegbar — Ausgabefaehigkeit kann nicht geprueft werden.';
        return null;
    }
    try {
        return (string) bioco_import_runtime_render_section($block, $post);
    } catch (Throwable $e) {
        $corrupt[] = 'Renderer-Fehler im Abschnitt: ' . $e->getMessage();
        return null;
    }
}

// Judges whether rendered HTML carries anything a visitor could see: visible
// text or visual markup (media, iframes, visible controls). Scripts, styles,
// <template> content and comments never count. Tags are stripped BEFORE
// entities are decoded (so escaped text like &lt;p&gt; still counts as
// visible text); entities like &nbsp;/&#160; (U+00A0) and &#8203; (zero-width
// space) are decoded and all Unicode whitespace plus invisible zero-width
// characters are normalized away before the text check. Element evidence is
// judged by the REAL built-in DOM parser (ext-dom): hidden form controls
// (first actual type attribute = hidden, raw value not trimmed) are
// invisible; structural wrapper tags (form, table, picture, source) carry
// nothing visible themselves and are absent from the positive tag list —
// only their actual child text/media/visible controls count. No CSS
// visibility simulation, no module allowlist. Requires PHP ext-dom
// (verified on every deployed runtime); absent ext-dom fails closed.
function bioco_import_runtime_html_is_meaningful($html) {
    $meaningful = preg_replace('/<(script|style|template)\b[^>]*>.*?<\/\1\s*>/is', '', (string) $html);
    $meaningful = preg_replace('/<!--.*?-->/s', '', (string) $meaningful);
    $text = html_entity_decode(strip_tags((string) $meaningful), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $text);
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    if ($text !== '') return true;
    // Element evidence via the REAL built-in DOM parser (ext-dom), not a
    // handwritten lexer: quoted values, '>' inside attributes and unknown tag
    // soup are handled by the actual parser. Only element nodes with media/
    // control tag names count (empty wrapper nodes carry nothing); an <input>
    // counts only when its FIRST actual type attribute (raw value, no
    // trimming — browsers treat " hidden " as an unknown, visible type) is
    // not case-insensitively "hidden". Fail-closed when ext-dom is absent:
    // "cannot judge" must not pass as "meaningful". No network/entity
    // loading (LIBXML_NONET), diagnostics suppressed per call, no global
    // libxml state leak.
    if (!class_exists('DOMDocument')) {
        throw new RuntimeException('PHP-Erweiterung ext-dom fehlt — HTML-Ausgabefaehigkeit kann nicht geprueft werden (fail-closed).');
    }
    $dom = new DOMDocument();
    $internalErrors = libxml_use_internal_errors(true);
    try {
        // Wrap in a root element so multiple top-level nodes survive; the
        // wrapper itself is not inspected. loadHTML with LIBXML_NONET: no
        // external entity/network loading. Encoding: the fragment is UTF-8;
        // declare it via the XML prolog hack so DOMDocument decodes correctly.
        $ok = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!DOCTYPE html><html><body>' . (string) $meaningful . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        if (!$ok) {
            throw new RuntimeException('HTML-Fragment konnte nicht geparst werden (fail-closed).');
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
    }
    $mediaTags = ['img', 'video', 'audio', 'iframe', 'svg', 'canvas', 'object', 'embed', 'textarea', 'select', 'button'];
    foreach ($dom->getElementsByTagName('*') as $element) {
        $tag = strtolower($element->tagName);
        if ($tag === 'input') {
            $type = $element->attributes->getNamedItem('type');
            $typeValue = $type ? $type->nodeValue : null;
            if ($typeValue !== null && strtolower($typeValue) === 'hidden') continue;
            return true;
        }
        if (in_array($tag, $mediaTags, true)) return true;
    }
    return false;
}

// Delimiter validation on the REAL tokenizer (WP_Block_Parser::next_token):
// every opener needs a later closer for the SAME name, in stack order — raw
// counts miss mismatched nesting like "close section" before "close row".
function bioco_import_runtime_validate_block_delimiters($content, array &$corrupt) {
    $parser = new WP_Block_Parser();
    $parser->document = (string) $content;
    $parser->offset = 0;
    $stack = [];
    while (true) {
        $token = $parser->next_token();
        $type = $token[0];
        if ($type === 'no-more-tokens') break;
        // next_token() does NOT advance the offset; do it here or the loop
        // repeats the first token forever.
        $parser->offset = $token[3] + $token[4];
        if ($type === 'block-opener') {
            $stack[] = (string) $token[1];
            continue;
        }
        if ($type !== 'block-closer') continue; // void-block needs no closer
        if (!$stack) {
            $corrupt[] = 'Block-Schliesser ohne passenden Oeffner: ' . bioco_import_excerpt((string) $token[1]);
            continue;
        }
        $expected = array_pop($stack);
        if ($expected !== (string) $token[1]) {
            $corrupt[] = 'Block-Schliesser in falscher Reihenfolge: "' . $token[1] . '" schliesst "' . $expected . '".';
        }
    }
    if ($stack) {
        $corrupt[] = sprintf('%d Block-Oeffner ohne Schliesser: %s.', count($stack), implode(', ', $stack));
    }
}

// Residual block-comment fragments must fail wherever they sit: the real
// parser does NOT tokenize a malformed wp: comment nested INSIDE a column —
// it leaves it as raw innerHTML there (only top-level fragments become a
// freeform block). Walks EVERY parsed block recursively. bioco:section
// markers are stripped first; the marker bookkeeping stays top-level (a
// marker promises the following top-level divi/section).
function bioco_import_runtime_inspect_inner_html(array $block, array &$corrupt) {
    $residual = preg_replace(
        '/<!--\s*bioco:section\s+[^\s>]+\s*-->/',
        '',
        (string) ($block['innerHTML'] ?? '')
    );
    if (preg_match('/<!--\s*\/?wp:/', (string) $residual)) {
        $corrupt[] = 'Block-Kommentar-Rest ausserhalb eines Blocks: ' . bioco_import_excerpt($residual);
    }
    foreach ($block['innerBlocks'] ?? [] as $inner) {
        bioco_import_runtime_inspect_inner_html($inner, $corrupt);
    }
}

function bioco_import_runtime_scan_content($content, $post = null) {
    $scan = ['markers' => [], 'sections' => 0, 'corrupt' => [], 'pageHtml' => ''];

    bioco_import_runtime_validate_block_delimiters($content, $scan['corrupt']);

    $pendingMarkers = [];
    foreach (parse_blocks((string) $content) as $block) {
        if (($block['blockName'] ?? null) === null) {
            // The real parser merges marker comments, stray block-comment
            // fragments and plain text into ONE freeform block — collect the
            // markers, then inspect the residual for stray wp: fragments.
            if (preg_match_all('/<!--\s*bioco:section\s+([^\s>]+)\s*-->/', (string) $block['innerHTML'], $m)) {
                $scan['markers'] = array_merge($scan['markers'], $m[1]);
                $pendingMarkers = array_merge($pendingMarkers, $m[1]);
            }
        }
        // Stray wp: fragments are flagged recursively for EVERY block, at any
        // nesting depth — the same check the top-level freeform branch did.
        bioco_import_runtime_inspect_inner_html($block, $scan['corrupt']);
        if ($block['blockName'] === 'divi/section') {
            if ($pendingMarkers) array_shift($pendingMarkers); // the marker's promised block arrived
            $scan['sections']++;
            if (empty($block['innerBlocks'])) {
                $scan['corrupt'][] = 'Divi-Abschnitt ohne Row/Modul — keine gueltige Divi-Struktur.';
            } else {
                // Every section is rendered (an empty/decorative section is
                // allowed); the page-level judgment happens below.
                $html = bioco_import_runtime_render_section_html($block, $post, $scan['corrupt']);
                if ($html !== null) $scan['pageHtml'] .= $html;
            }
            continue;
        }
        // A non-section block with a pending marker: the marker waits for its
        // own divi/section; nothing to consume here.
    }
    foreach ($pendingMarkers as $label) {
        $scan['corrupt'][] = 'bioco:section-Marker "' . $label . '" ohne Divi-Abschnitt.';
    }
    return $scan;
}

function bioco_import_verify_runtime_page(array $seed, array &$report) {
    $slug = (string) $seed['slug'];
    $post = bioco_import_find_page($slug);
    if (!$post) {
        bioco_import_report_row($report, $slug, '', '', 'runtime-missing', 'Benötigte Seite existiert nicht in WordPress.');
        return;
    }

    $content = (string) $post->post_content;
    if (trim($content) === '') {
        bioco_import_report_row($report, $slug, '', '', 'runtime-empty', 'Seiteninhalt ist leer.');
        return;
    }

    $scan = bioco_import_runtime_scan_content($content, $post);
    if (!$scan['sections']) {
        $scan['corrupt'][] = 'Kein Divi-Abschnitt im Seiteninhalt gefunden.';
    }
    if (!$scan['corrupt'] && !bioco_import_runtime_html_is_meaningful($scan['pageHtml'])) {
        // Per-PAGE judgment: decorative divider/spacer sections may render
        // nothing individually; the required page as a whole must still show
        // something a visitor could see.
        $scan['corrupt'][] = 'Benötigte Seite rendert insgesamt ohne sichtbaren Inhalt (alle Abschnitte leer).';
    }
    if ($scan['corrupt']) {
        foreach ($scan['corrupt'] as $detail) {
            bioco_import_report_row($report, $slug, '', '', 'runtime-corrupt', $detail);
        }
        return;
    }
    bioco_import_report_row(
        $report,
        $slug,
        '',
        '',
        'runtime-ok',
        sprintf('Benötigte Seite vorhanden: %d Divi-Abschnitt(e), %d bioco:section-Marker.', $scan['sections'], count($scan['markers']))
    );
}

function bioco_import_run_runtime_verify(array $seeds, array &$report) {
    foreach ($seeds as $seed) {
        bioco_import_verify_runtime_page($seed, $report);
    }
}
