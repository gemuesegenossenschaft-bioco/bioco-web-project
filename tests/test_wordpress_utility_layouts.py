"""Issue #212: PHP execution with WordPress's real block parser.

Keep/Replace/Remove map: tests/README.md.

The content-filter harness renders native attributes and shortcodes, not licensed
Divi CSS. Visual Builder persistence/appearance still needs unpublished-copy QA.
"""
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_divi_block_serialization import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
IMPORT = 'wordpress/web/app/mu-plugins/bioco-import/includes/utility-layouts.php'
RUNTIME = 'wordpress/web/app/mu-plugins/bioco-core/includes/utility-queries.php'
HARNESS = r'''
define('ABSPATH', __DIR__);
define('OBJECT', 'OBJECT');
define('WP_CLI', true);
$input = json_decode(stream_get_contents(STDIN), true);
$posts = []; $meta = []; $writes = []; $hooks = []; $shortcodes = []; $commands = [];
$wp_query = (object) ['posts' => array_map(fn($p) => (object)$p, $input['results'] ?? []), 'max_num_pages' => 3];
function add_filter($name, $fn, $priority = 10, $argc = 1) { $GLOBALS['hooks'][$name][] = $fn; }
function add_action($name, $fn, $priority = 10, $argc = 1) { add_filter($name, $fn, $priority, $argc); }
function add_shortcode($name, $fn) { $GLOBALS['shortcodes'][$name] = $fn; }
function get_header() { require 'wordpress/web/app/themes/bioco-divi/header.php'; }
function get_footer() { require 'wordpress/web/app/themes/bioco-divi/footer.php'; }
function language_attributes() { echo 'lang="de"'; }
function bloginfo($key) { echo 'UTF-8'; }
function wp_head() {}
function body_class() {}
function wp_body_open() {}
function wp_footer() {}
function is_page_template($template) { return false; }
function bioco_render_primary_navigation() { return '<nav>Navigation</nav>'; }
function bioco_render_site_footer() { return '<footer>Footer</footer>'; }
function is_search() { return ($GLOBALS['input']['request'] ?? '') === 'search'; }
function is_404() { return ($GLOBALS['input']['request'] ?? '') === '404' || ($GLOBALS['input']['out_of_range'] ?? false); }
function is_singular($types) { return false; }
function have_posts() { return count($GLOBALS['wp_query']->posts) > 0; }
function current_user_can($cap) { return $GLOBALS['input']['admin'] ?? true; }
function post_type_exists($type) { return true; }
function taxonomy_exists($type) { return true; }
function get_post_stati() { return ['publish', 'draft', 'private', 'trash']; }
function get_posts($args) { return array_values(array_filter($GLOBALS['posts'], fn($p) => $p->post_type === $args['post_type'] && $p->post_name === $args['name'])); }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function wp_insert_post($data, $error = false) {
    $id = count($GLOBALS['posts']) + 1;
    $GLOBALS['posts'][$id] = (object)(['ID' => $id] + $data);
    $GLOBALS['writes'][] = ['insert', $data];
    foreach ($data['meta_input'] ?? [] as $key => $value) $GLOBALS['meta'][$id][$key] = $value;
    return $id;
}
function wp_update_post($data, $error = false) {
    $id = $data['ID']; foreach ($data as $k => $v) $GLOBALS['posts'][$id]->$k = $v;
    $GLOBALS['writes'][] = ['update', $data]; return $id;
}
class WP_Error { function get_error_message() { return 'Library taxonomy failed'; } }
function wp_delete_post($id, $force) { unset($GLOBALS['posts'][$id], $GLOBALS['meta'][$id]); $GLOBALS['writes'][] = ['rollback', $id]; return true; }
function wp_set_object_terms($id, $terms, $taxonomy) { if ($GLOBALS['input']['term_error'] ?? false) return new WP_Error(); $GLOBALS['writes'][] = ['terms', $id, $terms, $taxonomy]; return [1]; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_slash($value) { return $value; }
function home_url($path = '/') { return 'https://example.test' . $path; }
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function esc_url_raw($v) { return str_starts_with($v, 'https://') || str_starts_with($v, '/') ? $v : ''; }
function esc_url($v) { return str_starts_with($v, 'https://') || str_starts_with($v, '/') ? esc_attr($v) : ''; }
function wp_strip_all_tags($v) { return strip_tags($v); }
function wp_trim_words($v, $n) { return implode(' ', array_slice(explode(' ', $v), 0, $n)); }
function get_the_title($p) { return $p->post_title; }
function get_the_excerpt($p) { return $p->post_excerpt ?? ''; }
function get_permalink($p) { return $p->url; }
function get_search_query($escaped = true) { return $escaped ? esc_attr($GLOBALS['input']['query'] ?? '') : ($GLOBALS['input']['query'] ?? ''); }
function get_query_var($key, $default = '') { return $key === 'paged' ? 2 : $default; }
function paginate_links($args) { $GLOBALS['pagination'] = $args; return '<a href="https://example.test/?s=korb&paged=3">' . esc_html($args['next_text']) . '</a>'; }
function shortcode_atts($defaults, $attrs, $name = '') { return array_intersect_key($attrs, $defaults) + $defaults; }
function status_header($code) { $GLOBALS['status'] = $code; }
function nocache_headers() { $GLOBALS['nocache'] = true; }
function parse_blocks($content) { return (new WP_Block_Parser())->parse($content); }
function do_shortcode($value) {
    return preg_replace_callback('/\[(bioco_utility_[a-z_]+)([^\]]*)\]/', function($m) {
        preg_match_all('/(\w+)="([^"]*)"/', $m[2], $pairs, PREG_SET_ORDER); $attrs = [];
        foreach ($pairs as $pair) $attrs[$pair[1]] = $pair[2];
        return isset($GLOBALS['shortcodes'][$m[1]]) ? ($GLOBALS['shortcodes'][$m[1]])($attrs) : $m[0];
    }, $value);
}
function render_native($blocks) {
    $html = '';
    foreach ($blocks as $b) {
        $a = $b['attrs']; $n = $b['blockName'];
        if ($n === 'divi/text') $html .= do_shortcode($a['content']['innerContent']['desktop']['value']);
        elseif ($n === 'divi/heading') {
            $level = $a['title']['decoration']['font']['font']['desktop']['value']['headingLevel'];
            $html .= '<'.$level.'>'.do_shortcode($a['title']['innerContent']['desktop']['value']).'</'.$level.'>';
        } elseif ($n === 'divi/button') {
            $v = $a['button']['innerContent']['desktop']['value'];
            $html .= '<a href="'.esc_url($v['linkUrl']).'">'.esc_html($v['text']).'</a>';
        } elseif ($n === 'divi/search') {
            $html .= '<form method="get" action="https://example.test/"><input name="s" placeholder="'.esc_attr($a['searchPlaceholder']['innerContent']['desktop']['value']).'"></form>';
        } else $html .= render_native($b['innerBlocks']);
    } return $html;
}
function apply_filters($hook, $value) {
    if ($hook === 'the_content') { $GLOBALS['render_inputs'][] = $value; return render_native(parse_blocks($value)); }
    foreach ($GLOBALS['hooks'][$hook] ?? [] as $fn) $value = $fn($value);
    return $value;
}
class WP_CLI {
    static function add_command($name, $fn, $args = []) { $GLOBALS['commands'][$name] = $fn; }
    static function success($message) { $GLOBALS['cli_output'] = $message; }
    static function error($message) { throw new RuntimeException($message); }
}
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
'''


def php(code, **input):
    proc = subprocess.run(['php', '-r', WP_SERIALIZER_STUB + HARNESS + code],
                          input=json.dumps(input), cwd=ROOT, text=True, capture_output=True)
    assert proc.returncode == 0, proc.stderr
    assert proc.stderr == ''
    return json.loads(proc.stdout)


LOAD = f"require '{IMPORT}'; require '{RUNTIME}';"
INIT = "foreach ($hooks['init'] ?? [] as $fn) $fn();"


def test_preview_apply_repeat_and_cli_default_do_not_modify_pages():
    out = php(LOAD + r'''
    $preview = bioco_import_utility_layouts(); $preview_writes = $writes;
    $report = bioco_import_utility_layouts(true); $first = $writes;
    $writes = []; $again = bioco_import_utility_layouts(true);
    ($commands['bioco utility-layouts'])([], []);
    echo json_encode(compact('preview', 'preview_writes', 'report', 'first', 'again', 'writes', 'posts', 'commands'));
    ''')
    assert out['preview_writes'] == []
    assert all(row['action'] == 'would-create' for row in out['preview'])
    assert len(out['report']) == 7
    assert all(row['action'] == 'created' for row in out['report'])
    assert out['writes'] == []
    assert all(row['action'] == 'unchanged' for row in out['again'])
    inserts = [w[1] for w in out['first'] if w[0] == 'insert']
    assert len(inserts) == 7
    assert {p['post_type'] for p in inserts} == {'et_pb_layout'}
    assert all(p['meta_input']['_et_pb_use_builder'] == 'on' for p in inserts)
    assert all(p['meta_input']['_et_pb_built_for_post_type'] == 'page' for p in inserts)
    assert all(w[2:] == [['layout'], 'layout_type'] for w in out['first'] if w[0] == 'terms')


@pytest.mark.parametrize('change', ['content', 'title', 'unmanaged', 'trash'])
def test_import_preserves_editor_layouts(change):
    out = php(LOAD + r'''
    bioco_import_utility_layouts(true);
    $id = 1;
    if ($input['change'] === 'content') $posts[$id]->post_content .= '<p>Redaktion</p>';
    if ($input['change'] === 'title') $posts[$id]->post_title = 'Eigene Suche';
    if ($input['change'] === 'unmanaged') unset($meta[$id]['_bioco_utility_fingerprint']);
    if ($input['change'] === 'trash') $posts[$id]->post_status = 'trash';
    $before = serialize([$posts, $meta]); $writes = [];
    $report = bioco_import_utility_layouts(true);
    echo json_encode(['report' => $report, 'writes' => $writes, 'preserved' => $before === serialize([$posts, $meta])]);
    ''', change=change)
    assert out['writes'] == [] and out['preserved']
    assert out['report'][0]['action'] == 'preserve-editor'


def test_apply_requires_administrator_before_any_write():
    out = php(LOAD + r'''
    try { bioco_import_utility_layouts(true); } catch (RuntimeException $e) { $error = $e->getMessage(); }
    echo json_encode(compact('error', 'writes'));
    ''', admin=False)
    assert 'administrator' in out['error'].lower() and out['writes'] == []


@pytest.mark.parametrize('request_kind,results,heading,status', [
    ('search', [], 'Keine Treffer gefunden', 200),
    ('404', [], 'Seite nicht gefunden', 404),
    ('search', [{'post_title': 'Gemüse "<script>x</script>"', 'post_excerpt': '<b>Bio</b>',
                 'url': 'https://example.test/gemuese/'}], 'Suchergebnisse', 200),
])
def test_php_render_templates_query_data_status_and_seo(request_kind, results, heading, status):
    out = php(LOAD + INIT + r'''
    $status = 200;
    foreach ($hooks['template_redirect'] ?? [] as $fn) $fn();
    $before = serialize($wp_query);
    ob_start();
    require 'wordpress/web/app/themes/bioco-divi/' . (is_404() ? '404.php' : 'search.php');
    $html = ob_get_clean();
    $robots = apply_filters('rank_math/frontend/robots', ['index' => 'index', 'follow' => 'follow', 'max-snippet' => '-1']);
    echo json_encode(['html' => $html, 'status' => $status, 'robots' => $robots, 'writes' => $writes,
        'query_preserved' => $before === serialize($wp_query), 'inputs' => $render_inputs, 'pagination' => $pagination ?? null,
        'hooks' => array_keys($hooks)]);
    ''', request=request_kind, results=results, query='korb "<script>x</script>"')
    assert heading in out['html']
    assert 'name="s"' in out['html'] and 'method="get"' in out['html']
    for label, url in [('Startseite', '/'), ('Aktuelles', '/aktuelles/'), ('Kontakt', '/kontakt/')]:
        assert f'href="https://example.test{url}">{label}</a>' in out['html']
    assert out['status'] == status
    assert out['robots'] == {'index': 'noindex', 'follow': 'follow', 'max-snippet': '-1'}
    assert '<script>' not in out['html'] and out['writes'] == [] and out['query_preserved']
    assert 'wp_head' not in out['hooks'] and 'wp_robots' not in out['hooks']
    assert '<!-- wp:divi/section' in out['inputs'][0]
    if results:
        assert 'Gemüse &quot;&lt;script&gt;x&lt;/script&gt;&quot;' in out['html']
        assert '<p>Bio</p>' in out['html']
        assert out['pagination']['current'] == 2 and out['pagination']['total'] == 3


def test_saved_layout_changes_are_rendered_without_reseeding():
    out = php(LOAD + INIT + r'''
    bioco_import_utility_layouts(true);
    $layout = get_posts(['post_type' => 'et_pb_layout', 'name' => 'bioco-utility-search-empty'])[0];
    $layout->post_content = str_replace('Keine Treffer gefunden', 'Andere Suche versuchen', $layout->post_content);
    $writes = [];
    echo json_encode(['html' => bioco_utility_render(), 'writes' => $writes]);
    ''', request='search')
    assert 'Andere Suche versuchen' in out['html'] and out['writes'] == []


def test_reusable_page_modules_can_be_changed_reordered_saved_and_reopened():
    out = php(LOAD + r'''
    bioco_import_utility_layouts(true);
    $library = get_posts(['post_type' => 'et_pb_layout', 'name' => 'bioco-utility-page-documents'])[0];
    $blocks = parse_blocks($library->post_content);
    $blocks[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['title']['innerContent']['desktop']['value'] = 'Neue Dokumente';
    $blocks = array_reverse($blocks);
    $blocks[] = bioco_import_divi_block('divi/text', ['content' => ['innerContent' => ['desktop' => ['value' => '<p>Zusatz</p>']]]]);
    $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_name' => 'qa-copy', 'post_content' => serialize_blocks($blocks)]);
    $saved = get_post($id)->post_content;
    wp_update_post(['ID' => $id, 'post_content' => $saved]);
    echo json_encode(['saved' => $saved, 'reopened' => parse_blocks(get_post($id)->post_content), 'library' => $library->post_content]);
    ''')
    assert 'Neue Dokumente' in out['saved'] and 'Zusatz' in out['saved']
    assert 'Neue Dokumente' not in out['library']
    assert out['reopened'][-1]['blockName'] == 'divi/text'
    assert '/kontakt/' in json.dumps(out['reopened']) and '.pdf' in json.dumps(out['reopened'])


def test_native_layouts_use_shared_presets_and_preserve_nonutility_robots():
    out = php(LOAD + r'''
    echo json_encode(['layouts' => bioco_utility_layout_definitions(),
        'robots' => apply_filters('rank_math/frontend/robots', ['index' => 'index'])]);
    ''', request='page')
    assert out['robots'] == {'index': 'index'}
    content = json.dumps(out['layouts'])
    presets = json.loads((ROOT / 'wordpress/design-system/exports/presets.json').read_text())['presets']
    for module in ['divi/section', 'divi/row', 'divi/heading', 'divi/button']:
        assert any(preset_id in content for preset_id in presets['module'][module]['items'])
    assert next(iter(presets['group']['divi/font-body']['items'])) in content


def test_theme_bootstrap_registers_command_and_retains_custom_theme_builder_body():
    out = php(r'''
    require 'wordpress/web/app/themes/bioco-divi/functions.php';
    foreach ($hooks['after_setup_theme'] ?? [] as $fn) $fn();
    $default = (object)['ID'=>100, 'post_type'=>'et_body_layout', 'post_name'=>'bioco-global-body', 'post_content'=>''];
    $posts[100] = $default;
    $export = json_decode(file_get_contents('wordpress/design-system/exports/theme-builder.json'), true);
    $default->post_content = $export['layouts']['301']['data']['301'];
    // Divi keys layouts by post type (theme-builder.php, et_theme_builder_get_template_layouts).
    $layouts = ['et_template'=>false, 'et_header_layout'=>['id'=>90,'enabled'=>true,'override'=>true],
        'et_body_layout'=>['id'=>100,'enabled'=>true,'override'=>true],
        'et_footer_layout'=>['id'=>110,'enabled'=>true,'override'=>true]];
    $fallback = apply_filters('et_theme_builder_template_layouts', $layouts);
    $default->post_content .= '<p>Editor body</p>';
    $custom = apply_filters('et_theme_builder_template_layouts', $layouts);
    echo json_encode(['fallback'=>$fallback, 'custom'=>$custom, 'original'=>$layouts, 'commands'=>array_keys($commands)]);
    ''', request='search')
    assert out['fallback'] == []
    assert out['custom'] == out['original']
    assert 'bioco utility-layouts' in out['commands']



def test_taxonomy_failure_rolls_back_only_the_new_library_post():
    out = php(LOAD + r"""
    try { bioco_import_utility_layouts(true); } catch (RuntimeException $e) { $error = $e->getMessage(); }
    $remaining = count($posts); $input['term_error'] = false;
    $report = bioco_import_utility_layouts(true);
    echo json_encode(compact('remaining', 'report', 'error', 'writes'));
    """, term_error=True)
    assert out['remaining'] == 0
    assert out['error'] == 'Library taxonomy failed'
    assert all(row['action'] == 'created' for row in out['report'])
    assert ['rollback', 1] in out['writes']


def test_duplicate_layout_conflict_is_detected_before_any_write():
    out = php(LOAD + r"""
    $posts = [98 => (object)['ID'=>98, 'post_type'=>'et_pb_layout', 'post_name'=>'bioco-utility-page-intranet'],
              99 => (object)['ID'=>99, 'post_type'=>'et_pb_layout', 'post_name'=>'bioco-utility-page-intranet']];
    try { bioco_import_utility_layouts(true); } catch (RuntimeException $e) { $error = $e->getMessage(); }
    echo json_encode(compact('error', 'writes'));
    """)
    assert 'Duplicate' in out['error'] and out['writes'] == []


def test_result_data_cannot_execute_shortcodes_or_break_block_comments():
    out = php(LOAD + INIT + r"""
    add_shortcode('bioco_utility_exploit', function () { return '<script>bad</script>'; });
    echo json_encode(['html'=>bioco_utility_render()]);
    """, request='search', results=[{'post_title': '[bioco_utility_exploit] --> "',
                                    'post_excerpt': '[bioco_utility_exploit]',
                                    'url': 'javascript:alert(1)'}])
    assert '<script>' not in out['html']
    assert '&#91;bioco_utility_exploit&#93;' in out['html']
    assert 'javascript:' not in out['html']


@pytest.mark.parametrize('slug', ['datenschutz', 'impressum', 'statuten', 'intranet'])
def test_existing_legal_document_intranet_seed_content_stays_native_and_editable(slug):
    seed = json.loads((ROOT / 'wordpress/content-seed' / f'{slug}.json').read_text())
    out = php(LOAD + r"""
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';
    $blocks = [];
    foreach (bioco_import_build_page_plan($input['seed']) as $item) {
        if ($item['type'] === 'block') $blocks[] = Bioco_Import_Divi_Composer::section($item);
    }
    echo json_encode(['blocks'=>$blocks, 'html'=>render_native($blocks)]);
    """, seed=seed)
    assert out['blocks']
    assert out['html'].count('<h1>') == 1
    content = json.dumps(out['blocks'], ensure_ascii=False)
    for section in seed['sections']:
        if section.get('section_title'):
            assert section['section_title'] in content
        if section.get('section_text'):
            assert section['section_text'] in content.replace('\\"', '"')
        for button in section.get('buttons', []):
            assert button['text'] in content and button['href'] in content



def test_missing_search_page_uses_404_layout_and_status():
    out = php(LOAD + INIT + r"""
    foreach ($hooks['template_redirect'] ?? [] as $fn) $fn();
    echo json_encode(['html'=>bioco_utility_render(), 'status'=>$status, 'cache'=>$nocache]);
    """, request='search', out_of_range=True)
    assert out['status'] == 404 and out['cache']
    assert 'Seite nicht gefunden' in out['html']
    assert 'Keine Treffer gefunden' not in out['html']


def test_editor_modified_default_body_container_is_not_bypassed():
    out = php(LOAD + r"""
    $export = json_decode(file_get_contents('wordpress/design-system/exports/theme-builder.json'), true);
    $blocks = parse_blocks($export['layouts']['301']['data']['301']);
    $original = bioco_utility_is_passthrough($blocks);
    $blocks[0]['attrs']['module']['decoration']['spacing']['desktop']['value']['padding']['top'] = '80px';
    $edited = bioco_utility_is_passthrough($blocks);
    echo json_encode(compact('original', 'edited'));
    """)
    assert out['original'] and not out['edited']


def test_passthrough_ignores_divi_parser_runtime_metadata():
    # Divi's BlockParser adds per-parse counters (index, id, orderIndex, storeInstance).
    # Observed on staging 2026-10-10: two parses of the same body never compare equal.
    out = php(LOAD + r"""
    $export = json_decode(file_get_contents('wordpress/design-system/exports/theme-builder.json'), true);
    $tag = function (array $blocks, int $run) use (&$tag) {
        foreach ($blocks as $i => &$block) {
            $block += ['orderIndex' => $run, 'index' => $run * 10 + $i, 'id' => $block['blockName'] . '-' . $run,
                'storeInstance' => $run, 'layout_type' => 'default'];
            $block['innerBlocks'] = $tag($block['innerBlocks'], $run);
        }
        return $blocks;
    };
    $blocks = $tag(parse_blocks($export['layouts']['301']['data']['301']), 7);
    $original = bioco_utility_is_passthrough($blocks);
    $blocks[0]['attrs']['module']['decoration']['spacing']['desktop']['value']['padding']['top'] = '80px';
    $edited = bioco_utility_is_passthrough($blocks);
    echo json_encode(compact('original', 'edited'));
    """)
    assert out['original'] and not out['edited']


@pytest.mark.parametrize('post_status', ['draft', 'private', 'trash'])
def test_nonpublished_library_content_never_leaks_to_search(post_status):
    out = php(LOAD + INIT + r"""
    bioco_import_utility_layouts(true);
    $layout = get_posts(['post_type'=>'et_pb_layout', 'name'=>'bioco-utility-search-empty'])[0];
    $layout->post_status = $input['post_status'];
    $layout->post_content = str_replace('Keine Treffer gefunden', 'Private Redaktionsnotiz', $layout->post_content);
    $writes = [];
    echo json_encode(['html'=>bioco_utility_render(), 'writes'=>$writes]);
    """, request='search', post_status=post_status)
    assert 'Private Redaktionsnotiz' not in out['html']
    assert 'Keine Treffer gefunden' in out['html'] and out['writes'] == []
