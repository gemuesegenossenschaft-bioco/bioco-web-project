"""Render saved shell attributes through the real native adapter and renderer."""
import json
import os
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]
CORE = 'wordpress/web/app/mu-plugins/bioco-core'
IMPORT = 'wordpress/web/app/mu-plugins/bioco-import/includes'
HARNESS = r'''
define('ABSPATH', '/');
$state = json_decode(getenv('BIOCO_SHELL_STATE'), true);
function add_filter(...$args) {}
function add_action(...$args) {}
function get_the_ID() { return 5; }
function is_page($name) { return $GLOBALS['state']['page'] === $name; }
function is_singular($type) { return false; }
function is_post_type_archive($type) { return false; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return (string) $value; }
function home_url($path) { return 'https://example.test' . $path; }
function plugins_url($path, $file) { return 'https://example.test/wp-content/mu-plugins/bioco-core/' . $path; }
function wp_get_attachment_image_url($id, $size) { return 'https://example.test/media/' . $id . '.png'; }
function wp_kses_post($value) { return strip_tags($value, '<p><strong><br><a>'); }
function wp_enqueue_style(...$args) {}
class WP_Error { public function __construct(public $code, public $message, public $data=[]) {} }
require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-modules.php';
require getenv('BIOCO_SHELL_NAVIGATION');
require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/shell-layouts.php';
$block = bioco_import_shell_block($state['slot']);
foreach ($state['values'] as $field => $value) $block['attrs'][$field] = ['innerContent' => ['desktop' => ['value' => $value]]];
$component = $state['slot'] === 'header' ? 'navigation_shell' : 'footer_shell';
$values = bioco_native_extract_values($component, $block['attrs']);
$validated = $values instanceof WP_Error ? $values : bioco_native_validate_values($component, $values);
if ($validated instanceof WP_Error) { echo json_encode(['error' => $validated->message]); }
else { echo json_encode(['html' => bioco_render_dynamic_component($component, $validated, ['mode'=>'inert']), 'values'=>$validated]); }
'''


def render(slot='header', page='', navigation_path=None, **values):
    result = subprocess.run(['php', '-r', HARNESS], cwd=ROOT, check=True, capture_output=True, text=True,
                            env=os.environ | {'BIOCO_SHELL_STATE': json.dumps(dict(slot=slot, page=page, values=values)),
                                              'BIOCO_SHELL_NAVIGATION': str(navigation_path or ROOT / CORE / 'includes/navigation.php')})
    return json.loads(result.stdout)


def test_header_seed_keeps_confirmed_links_and_logo():
    html = render()['html']
    assert 'https://example.test/aktuelles/' in html
    assert 'assets/bioco-logo.png' in html
    assert html.startswith('<header ') and html.endswith('</header>')
    assert 'BIOCÒ WERDEN' in html


def test_missing_logo_seed_keeps_the_shared_renderer_default(tmp_path):
    seed = json.loads((ROOT / CORE / 'content/navigation.json').read_text())
    del seed['site']['logo']
    (tmp_path / 'content').mkdir()
    (tmp_path / 'content/navigation.json').write_text(json.dumps(seed))
    (tmp_path / 'includes').mkdir()
    navigation = tmp_path / 'includes/navigation.php'
    navigation.write_bytes((ROOT / CORE / 'includes/navigation.php').read_bytes())
    assert 'src="https://example.test/wp-content/mu-plugins/bioco-core/assets/bioco-logo.png"' in render(navigation_path=navigation)['html']


def test_header_fields_change_labels_logo_destinations_and_row_order():
    html = render(logo=42, primary=[{'label':'Second', 'url':'/kontakt/'}, {'label':'First', 'url':'/wir/'}],
                  utility=[], cta_label='Join', cta_url='/anmeldung/', menu_open_label='Open menu')['html']
    assert '/media/42.png' in html
    assert html.index('Second') < html.index('First')
    assert 'href="https://example.test/anmeldung/">Join' in html
    assert 'aria-label="Open menu"' in html
    assert '>Aktuelles<' not in html and '>Intranet<' not in html


def test_empty_lists_and_label_escaping_survive_native_extraction():
    html = render(primary=[], utility=[], cta_label='<script>bad</script>', cta_url='/kontakt/')['html']
    assert '&lt;script&gt;bad&lt;/script&gt;' in html
    assert '<script>' not in html and '>Wir<' not in html


def test_footer_columns_and_nested_links_are_addable_removable_and_ordered():
    columns = [{'heading':'Extra', 'text':'<p>Visible text</p>', 'links':[{'label':'Visit','url':'/kontakt/'}]},
               {'heading':'Second', 'text':'', 'links':[]}]
    result = render('footer', columns=columns, partners=[], region_text='Edited region')
    html = result['html']
    assert result['values']['columns'] == columns
    assert html.index('Extra') < html.index('Second')
    assert 'Visible text' in html and '>Visit<' in html and 'Edited region' in html
    assert 'Instagram' not in html and 'Demeter' not in html
    assert html.startswith('<footer ') and html.endswith('</footer>')


def test_footer_rejects_malformed_nested_link_values():
    result = render('footer', columns=[{'heading':'Invalid', 'links':'not a list'}])
    assert 'Rows must be a list' in result['error']


def test_presentation_fields_change_only_safe_instance_variables():
    html = render(gap=0, logo_width=144, background_color='#abcdef', text_color='red;display:none')['html']
    assert '--bioco-header-gap:0px' in html
    assert '--bioco-header-logo-width:144px' in html
    assert '--bioco-header-background-color:#abcdef' in html
    assert 'display:none' not in html


def test_membership_route_keeps_logo_only_header_and_no_footer():
    html = render(page='anmeldung')['html']
    assert 'bioco-logo' in html
    assert 'bioco-menu-toggle' not in html and '>Wir<' not in html
    assert render('footer', page='anmeldung')['html'] == ''


def migrate(content):
    from test_wordpress_divi_block_serialization import WP_SERIALIZER_STUB
    parser = '\n'.join("require 'tests/fixtures/wp-block-parser/" + name + "';" for name in (
        'class-wp-block-parser-block.php', 'class-wp-block-parser-frame.php', 'class-wp-block-parser.php'))
    code = r'''
define('ABSPATH', '/');
function is_wp_error($value) { return false; }
function add_filter(...$args) {}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_attr($value) { return esc_html($value); }
function plugins_url($path, $file) { return 'https://example.test/wp-content/mu-plugins/bioco-core/' . $path; }
require 'wordpress/web/app/mu-plugins/bioco-core/includes/navigation.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/shell-layouts.php';
function parse_blocks($value) { return (new WP_Block_Parser())->parse($value); }
try {
    [$after, $count] = bioco_import_shell_content(getenv('BIOCO_SHELL_SOURCE'));
    [$second, $second_count] = bioco_import_shell_content($after);
    echo json_encode(['blocks'=>parse_blocks($after), 'count'=>$count, 'second_count'=>$second_count, 'idempotent'=>$after === $second]);
} catch (RuntimeException $e) { echo json_encode(['error'=>$e->getMessage()]); }
'''
    result = subprocess.run(['php', '-r', parser + WP_SERIALIZER_STUB + code], cwd=ROOT,
                            check=True, text=True, capture_output=True,
                            env=os.environ | {'BIOCO_SHELL_SOURCE': content})
    return json.loads(result.stdout)


def text_block(content, **attrs):
    values = {'content': {'innerContent': {'desktop': {'value': content}}}, **attrs}
    return '<!-- wp:divi/text ' + json.dumps(values) + ' -->\n<!-- /wp:divi/text -->'


def test_shell_migration_preserves_wrapper_design_and_editorial_siblings():
    wrapper = {'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {'class': 'keep-shell'}}}}}}
    text_design = {'module': {'decoration': {'spacing': {'desktop': {'value': {'padding': {'top': '17px'}}}}}}}
    source = '<!-- wp:divi/section ' + json.dumps(wrapper) + ' -->\n'
    source += text_block('[bioco_global_header]', **text_design)
    source += text_block('<p>Keep editorial prose &amp; destinations.</p>')
    source += '\n<!-- /wp:divi/section -->'
    result = migrate(source)
    section = next(block for block in result['blocks'] if block['blockName'] == 'divi/section')
    assert section['attrs'] == wrapper
    header, sibling = section['innerBlocks']
    assert header['blockName'] == 'bioco-divi/navigation-shell'
    assert header['attrs']['module'] == text_design['module']
    assert header['attrs']['primary']['innerContent']['desktop']['value'][0]['label'] == 'Wir'
    assert sibling['attrs']['content']['innerContent']['desktop']['value'] == '<p>Keep editorial prose &amp; destinations.</p>'
    assert result['count'] == 1 and result['second_count'] == 0 and result['idempotent']


def test_footer_migration_captures_existing_contact_links_and_partners():
    result = migrate(text_block('[bioco_global_footer]'))
    block = next(block for block in result['blocks'] if block['blockName'])
    assert block['blockName'] == 'bioco-divi/footer-shell'
    columns = block['attrs']['columns']['innerContent']['desktop']['value']
    assert columns[1]['heading'] == 'Kontakt'
    assert 'info@bioco.ch' in columns[1]['text']
    assert columns[2]['links'][0]['url'] == 'https://www.instagram.com/bioco.ch'
    assert result['count'] == 1 and result['idempotent']


def test_migration_rejects_mixed_editorial_text_instead_of_dropping_it():
    result = migrate(text_block('<p>Preserve me</p>[bioco_global_header]'))
    assert 'editorial content' in result['error']


def test_nested_row_editor_initializes_a_valid_empty_link_list():
    code = r'''
const fs = require('fs');
const vm = require('vm');
const registered = {};
const fields = JSON.parse(fs.readFileSync(process.argv[1], 'utf8')).footer_shell;
const React = {createElement: (type, props, ...children) => ({type, props: props || {}, children: children.flat(Infinity)})};
const window = {React, BiocoNativeModulesData: {modules: [{component:'footer_shell', metadata:{name:'bioco-divi/footer-shell'}, fields}]}, divi:{moduleLibrary:{registerModule(){}}, fieldLibrary:{registerFieldComponent(field){registered[field.name]=field.component;}}}};
vm.runInNewContext(fs.readFileSync(process.argv[2], 'utf8'), {window, setTimeout(){}});
let saved;
const field = registered['bioco/footer_shell-columns'];
const tree = field({value:[], onChange: value => {saved=value.inputValue;}});
tree.children.find(child => child.type === 'button').props.onClick();
console.log(JSON.stringify(saved));
'''
    result = subprocess.run(['node', '-e', code, CORE + '/native-modules/fields.json', CORE + '/native-modules/editor.js'],
                            cwd=ROOT, check=True, text=True, capture_output=True)
    columns = json.loads(result.stdout)
    assert columns == [{'heading':'', 'text':'', 'links':[], 'link_gap':0}]
    assert 'error' not in render('footer', columns=columns)


def test_scoped_metadata_rebuild_preserves_other_component_fields(tmp_path):
    import importlib.util
    spec = importlib.util.spec_from_file_location('shell_metadata', ROOT / 'wordpress/scripts/build-native-modules.py')
    generator = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(generator)
    original = json.loads((ROOT / CORE / 'native-modules/fields.json').read_text())
    (tmp_path / 'fields.json').write_text(json.dumps(original))
    generator.OUT = tmp_path
    generator.build(['navigation_shell', 'footer_shell'])
    after = json.loads((tmp_path / 'fields.json').read_text())
    for component in set(original) - {'navigation_shell', 'footer_shell'}:
        assert after[component] == original[component]
    assert after['navigation_shell']['toggle_size']['type'] == 'number'
    assert not (tmp_path / 'events-feed').exists()


def test_shell_acf_keys_are_unique_across_parent_and_nested_fields():
    def keys(fields):
        for field in fields:
            yield field['key']
            yield from keys(field.get('sub_fields', []))
    all_keys = []
    for component in ('navigation_shell', 'footer_shell'):
        group = json.loads((ROOT / CORE / 'acf-json' / ('group_bioco_block_' + component + '.json')).read_text())
        all_keys.extend(keys(group['fields']))
    assert len(all_keys) == len(set(all_keys))
