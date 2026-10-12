"""Gallery editing, typed saved values and browser behavior for #211.

Runs the real shared editor controls, PHP validation/rendering, block parser,
CSS and gallery script with synthetic media. Hosted Divi save/reopen remains
an operator check on an unpublished, unassigned copy.
"""
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_native_divi_modules import _preamble, _render_stubs
from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
CORE = ROOT / 'wordpress/web/app/mu-plugins/bioco-core'


def values():
    return {
        'items': [{'image': index, 'category': category, 'caption': 'Caption ' + str(index)}
                  for index, category in enumerate(['feld', 'ernte_2026', 'koerbe', 'ernte_2026', 'feld', 'ernte_2026'], 1)],
        'filters': [{'key': 'feld', 'label': 'Feld'}, {'key': 'all', 'label': 'Alle Bilder'},
                    {'key': 'ernte_2026', 'label': 'Ernte & Früchte'},
                    {'key': 'leer_2027', 'label': 'Noch nicht vorhanden'}],
        'show_more_label': 'Auswahl erweitern', 'show_less_label': 'Auswahl verkleinern',
        'empty_filter_label': 'Keine Bilder in dieser Kategorie', 'filter_select_label': 'Galeriekategorie',
        'initial_count': 2, 'columns_desktop': '4', 'columns_tablet': '3', 'columns_mobile': '2',
        'grid_gap': 13, 'image_fit': 'contain', 'image_ratio': '1_1', 'image_radius': '24',
        'loading_title': 'Noch keine Bilder', 'loading_text': 'Die Galerie bleibt leer.',
    }


def execute_php(code, payload):
    result = subprocess.run(['php', '-r', code], cwd=ROOT, input=json.dumps(payload),
                            text=True, capture_output=True, check=True)
    assert result.stderr == ''
    return json.loads(result.stdout)


def render(saved, *, attrs=False):
    stubs = _render_stubs().replace(
        "function get_post_meta($post_id, $key, $single = false) { return ''; }",
        "function get_post_meta($post_id, $key, $single = false) { return 'Media alt ' . $post_id; }")
    code = _preamble(stubs=stubs, with_seam=True) + r'''
$input = json_decode(stream_get_contents(STDIN), true);
$values = $input['attrs'] ? bioco_native_extract_values('gallery', $input['saved']) : $input['saved'];
if (!($values instanceof WP_Error)) $values = bioco_native_validate_values('gallery', $values);
if ($values instanceof WP_Error) echo json_encode(['error' => $values->get_error_message(), 'status' => $values->status]);
else echo json_encode(['values' => $values, 'html' => bioco_render_dynamic_component('gallery', $values)]);
'''
    return execute_php(code, {'saved': saved, 'attrs': attrs})


def test_arbitrary_categories_captions_order_and_media_metadata_survive_rendering():
    saved = values()
    saved['items'][1]['caption'] = '<script>alert(1)</script> & Früchte'
    result = render(saved)
    assert result['values'] == saved
    html = result['html']
    assert 'data-gallery-filter="ernte_2026"' in html and 'Ernte &amp; Früchte' in html
    assert '&lt;script&gt;alert(1)&lt;/script&gt; &amp; Früchte' in html and '<script>' not in html
    assert html.index('img-1.jpg') < html.index('img-2.jpg') < html.index('img-3.jpg')
    assert 'alt="Media alt 2"' in html and '<figcaption>' in html
    assert 'aria-label="Galeriekategorie"' in html
    assert 'data-gallery-more="Auswahl erweitern"' in html
    assert 'data-gallery-less="Auswahl verkleinern"' in html


@pytest.mark.parametrize('field,bad', [
    ('initial_count', -1), ('initial_count', 1.5), ('initial_count', 501),
    ('grid_gap', -1), ('grid_gap', 1.5), ('grid_gap', 97),
    ('columns_desktop', '7'), ('columns_tablet', '5'), ('columns_mobile', '3'),
    ('image_fit', 'javascript:bad'), ('image_ratio', 'unsafe'), ('image_radius', 'calc(10px)'),
])
def test_invalid_saved_layout_controls_fail_closed(field, bad):
    saved = values() | {field: bad}
    assert render(saved)['status'] == 400


@pytest.mark.parametrize('category', ['all', 'Bad Key', 'ernte/2026', ''])
def test_reserved_or_invalid_category_keys_are_rejected(category):
    saved = values()
    saved['items'][0]['category'] = category
    assert render(saved)['status'] == 400


def test_media_missing_from_the_library_is_skipped_without_fabricated_content():
    saved = values() | {'items': [{'image': 0, 'category': 'feld', 'caption': 'Unresolved'}]}
    result = render(saved)
    assert result['values']['items'] == saved['items']
    assert 'gallery-item' not in result['html'] and '<img' not in result['html']
    assert 'Noch keine Bilder' in result['html']


def test_gallery_controls_add_media_caption_category_and_reorder_readonly_rows():
    code = r'''
const fs=require('fs'), vm=require('vm'), registered={};
const fields=JSON.parse(fs.readFileSync(process.argv[1],'utf8')).gallery;
const React={createElement:(type,props,...children)=>({type,props:props||{},children:children.flat(Infinity)})};
const Upload=()=>null;
const window={React,BiocoNativeModulesData:{modules:[{component:'gallery',metadata:{name:'bioco-divi/gallery'},fields}]},
 divi:{moduleLibrary:{registerModule(){}},fieldLibrary:{Upload,registerFieldComponent(f){registered[f.name]=f.component;}}}};
vm.runInNewContext(fs.readFileSync(process.argv[2],'utf8'),{window,setTimeout(){}});
const original=[Object.freeze({image:25,category:'feld',caption:'Original caption'})];Object.freeze(original);
let items=original,filters=[{key:'all',label:'Alle'}];
function tree(name,value,save){return registered['bioco/gallery-'+name]({value,onChange:p=>save(p.inputValue)});}
let editor=tree('items',items,v=>items=v);
editor.children.find(c=>c.type==='button').props.onClick();
editor=tree('items',items,v=>items=v);
const upload=editor.children[1].children.find(c=>c.type==='label'&&c.children[0]==='Bild').children[1];
upload.props.onChange({inputValue:133});
editor=tree('items',items,v=>items=v);
editor.children[1].children.find(c=>c.type==='label'&&c.children[0]==='Kategorie').children[1].props.onChange({target:{value:'ernte_2026'}});
editor=tree('items',items,v=>items=v);
editor.children[1].children.find(c=>c.type==='label'&&c.children[0]==='Bildunterschrift').children[1].props.onChange({target:{value:'Neue Ernte & Früchte'}});
editor=tree('items',Object.freeze(items.map(Object.freeze)),v=>items=v);
editor.children[1].children.find(c=>c.type==='button'&&c.children[0]==='Nach oben').props.onClick();
editor=tree('filters',filters,v=>filters=v);
editor.children.find(c=>c.type==='button').props.onClick();
editor=tree('filters',filters,v=>filters=v);
editor.children[1].children.find(c=>c.type==='label'&&c.children[0]==='Technischer Schlüssel').children[1].props.onChange({target:{value:'ernte_2026'}});
editor=tree('filters',filters,v=>filters=v);
editor.children[1].children.find(c=>c.type==='label'&&c.children[0]==='Angezeigter Text').children[1].props.onChange({target:{value:'Unsere Ernte'}});
console.log(JSON.stringify({items,filters,original,registered:Object.keys(registered),upload:{attachmentId:upload.props.attachmentId,dataType:upload.props.dataType}}));
'''
    result = subprocess.run(['node', '-e', code, str(CORE / 'native-modules/fields.json'),
                             str(CORE / 'native-modules/editor.js')], cwd=ROOT, capture_output=True, text=True, check=True)
    saved = json.loads(result.stdout)
    assert saved['upload'] == {'attachmentId': True, 'dataType': 'image'}
    assert saved['original'] == [{'image': 25, 'category': 'feld', 'caption': 'Original caption'}]
    assert saved['items'] == [{'image': 133, 'category': 'ernte_2026', 'caption': 'Neue Ernte & Früchte'}, saved['original'][0]]
    assert render({'items': saved['items'], 'filters': saved['filters']})['values']['items'] == saved['items']
    for field in ['items', 'filters', 'initial_count', 'columns_desktop', 'columns_tablet', 'columns_mobile', 'grid_gap', 'image_fit', 'image_ratio', 'image_radius']:
        assert 'bioco/gallery-' + field in saved['registered']


def test_saved_native_gallery_reopens_with_values_and_authored_wrapper_design_intact():
    attrs = {key: {'innerContent': {'desktop': {'value': value}}} for key, value in values().items()}
    attrs['module'] = {'decoration': {'spacing': {'phone': {'value': {'padding': {'left': '17px'}}}}},
                       'advanced': {'htmlAttributes': {'desktop': {'value': {'class': 'authored-gallery', 'id': 'saved-gallery'}}}}}
    attrs['modulePreset'] = ['saved-native-preset-id']
    code = "require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';\n" + WP_SERIALIZER_STUB + r'''
$attrs=json_decode(stream_get_contents(STDIN),true);
$content=serialize_blocks([['blockName'=>'bioco-divi/gallery','attrs'=>$attrs,'innerBlocks'=>[],'innerHTML'=>'\n','innerContent'=>['\n']]]);
$reopened=(new WP_Block_Parser())->parse($content);
echo json_encode(['attrs'=>$reopened[0]['attrs'],'stable'=>serialize_blocks($reopened)===$content]);
'''
    result = execute_php(code, attrs)
    assert result['stable'] and result['attrs'] == attrs
    assert render(result['attrs'], attrs=True)['values'] == values()


@pytest.fixture(scope='module')
def browser():
    from playwright.sync_api import sync_playwright
    with sync_playwright() as playwright:
        instance = playwright.chromium.launch()
        yield instance
        instance.close()


def page_with_gallery(browser, saved, width=1280):
    page = browser.new_page(viewport={'width': width, 'height': 900})
    page.route('**/*', lambda route: route.fulfill(content_type='image/svg+xml', body='<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="600" height="400" fill="green"/></svg>'))
    page.set_content('<div class="et-l"><main>' + render(saved)['html'] + '</main></div>')
    # Divi's observed reset suppresses the browser's default keyboard outline.
    page.add_style_tag(content=':focus{outline:0}')
    page.add_style_tag(content=(CORE / 'assets/bioco-tokens.css').read_text())
    page.add_style_tag(content=(CORE / 'assets/bioco-blocks.css').read_text())
    page.add_script_tag(content=(CORE / 'blocks/gallery/view.js').read_text())
    return page


@pytest.mark.parametrize('width,columns', [(390, 2), (768, 3), (1280, 4)])
def test_responsive_grid_filters_empty_categories_and_authored_more_less_actions(browser, width, columns):
    page = page_with_gallery(browser, values(), width)
    try:
        assert page.locator('.gallery-item:visible').count() == 2
        assert page.get_by_role('button', name='Auswahl erweitern').get_attribute('aria-expanded') == 'false'
        page.get_by_role('button', name='Auswahl erweitern').click()
        assert page.locator('.gallery-item:visible').count() == 6
        page.get_by_role('button', name='Auswahl verkleinern').click()
        assert page.locator('.gallery-item:visible').count() == 2
        if width <= 640:
            page.get_by_role('combobox', name='Galeriekategorie').select_option('ernte_2026')
        else:
            page.get_by_role('button', name='Ernte & Früchte').click()
        assert page.locator('.gallery-item:visible').count() == 2
        assert page.locator('[data-gallery-filter="ernte_2026"]').get_attribute('aria-pressed') == 'true'
        page.get_by_role('button', name='Auswahl erweitern').click()
        assert page.locator('.gallery-item:visible').count() == 3
        if width <= 640:
            page.get_by_role('combobox', name='Galeriekategorie').select_option('leer_2027')
        else:
            page.get_by_role('button', name='Noch nicht vorhanden').click()
        assert page.locator('.gallery-item:visible').count() == 0
        assert page.get_by_role('status').inner_text() == 'Keine Bilder in dieser Kategorie'
        assert page.get_by_role('status').is_visible()
        assert not page.locator('[data-gallery-toggle]').is_visible()
        if width <= 640:
            page.get_by_role('combobox', name='Galeriekategorie').select_option('all')
        else:
            page.get_by_role('button', name='Alle Bilder').click()
        layout = page.locator('[data-gallery-grid]').evaluate('''grid=>({
          columns:getComputedStyle(grid).gridTemplateColumns.split(' ').length,
          gap:getComputedStyle(grid).gap,fit:getComputedStyle(grid.querySelector('img')).objectFit,
          radius:getComputedStyle(grid.querySelector('.gallery-image')).borderRadius,
          overflow:document.documentElement.scrollWidth>innerWidth})''')
        assert layout == {'columns': columns, 'gap': '13px', 'fit': 'contain', 'radius': '24px', 'overflow': False}
        assert not page.get_by_role('status').is_visible()
        assert page.locator('.gallery-item:visible').count() == 2
        assert page.get_by_alt_text('Media alt 1').is_visible()
    finally:
        page.close()


@pytest.mark.parametrize('change', [{'initial_count': 0}, {'show_more_label': ''}, {'filters': []}])
def test_zero_count_and_intentionally_cleared_controls_do_not_leave_inaccessible_images(browser, change):
    page = page_with_gallery(browser, values() | change)
    try:
        expected = 2 if change == {'filters': []} else 6
        assert page.locator('.gallery-item:visible').count() == expected
        if 'filters' in change:
            assert page.locator('[data-gallery-select]').count() == page.locator('[data-gallery-filter]').count() == 0
        else:
            assert page.locator('[data-gallery-toggle]').count() == 0
    finally:
        page.close()


def test_explicit_empty_less_label_omits_collapse_action_without_replacing_editor_text(browser):
    page = page_with_gallery(browser, values() | {'show_less_label': ''})
    try:
        page.get_by_role('button', name='Auswahl erweitern').click()
        assert page.locator('.gallery-item:visible').count() == 6
        assert not page.locator('[data-gallery-toggle]').is_visible()
        assert 'Weniger anzeigen' not in page.locator('.gallery-container').inner_text()
    finally:
        page.close()


@pytest.mark.parametrize('width', [390, 1280])
def test_gallery_filter_controls_keep_visible_keyboard_focus_with_divis_reset(browser, width):
    page = page_with_gallery(browser, values(), width)
    try:
        page.keyboard.press('Tab')
        focused = page.locator(':focus')
        assert focused.get_attribute('class') in ['gallery-select', 'gallery-filter']
        assert focused.evaluate("element=>getComputedStyle(element).outline") == 'rgb(255, 140, 0) solid 3px'
        assert focused.evaluate("element=>getComputedStyle(element).outlineOffset") == '4px'
    finally:
        page.close()
