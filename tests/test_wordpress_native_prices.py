"""Native price starters and the real browser arithmetic controller.

The WordPress parser is real. The local native-DOM harness replaces Divi render
and query execution, so it does not prove Visual Builder save/reopen or styling.
Keep/Replace/Remove evidence map: tests/README.md.
"""
import html
import json
from pathlib import Path

from playwright.sync_api import sync_playwright

from test_wordpress_prices import records
from test_wordpress_catalog import catalog
from test_wordpress_utility_layouts import php

LOAD = "function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}require 'wordpress/web/app/mu-plugins/bioco-import/includes/price-layouts.php';"
ROOT = Path(__file__).parents[1]


def layouts():
    return php(LOAD + "echo json_encode(array_map(fn($v)=>parse_blocks($v['content']),bioco_price_layout_definitions()));")


def walk(blocks):
    for block in blocks:
        yield block
        yield from walk(block['innerBlocks'])


def test_library_import_is_opt_in_missing_only_and_preserves_authored_layouts():
    out = php(LOAD + r'''
$preview=bioco_import_price_layouts();$before=$writes;
$first=bioco_import_price_layouts(true);$posts[1]->post_content.='<p>Redaktion</p>';
$authored=$posts[1]->post_content;$writes=[];$again=bioco_import_price_layouts(true);
($commands['bioco price-layouts'])([],[]);
echo json_encode(compact('preview','before','first','again','writes','authored','posts'));
''')
    assert out['before'] == [] and out['writes'] == []
    assert [row['action'] for row in out['preview']] == ['would-create', 'would-create']
    assert [row['action'] for row in out['first']] == ['created', 'created']
    assert [row['action'] for row in out['again']] == ['preserve-existing', 'preserve-existing']
    assert out['posts']['1']['post_content'] == out['authored']
    assert {post['post_type'] for post in out['posts'].values()} == {'et_pb_layout'}


def test_starters_use_native_modules_and_bound_prices_without_literal_duplicate_amounts():
    definitions = layouts()
    for key, blocks in definitions.items():
        nodes = list(walk(blocks))
        assert {node['blockName'] for node in nodes} <= {'divi/section', 'divi/row', 'divi/column', 'divi/text', 'divi/heading', 'divi/button'}
        loops = [node for node in nodes if 'loop' in node['attrs'].get('module', {}).get('advanced', {})]
        assert len(loops) == 1
        settings = loops[0]['attrs']['module']['advanced']['loop']['desktop']['value']
        assert settings['subTypes'] == [{'value': 'bioco_price', 'label': 'Preise'}]
        assert settings['enable'] == 'on' and settings['loopId'] == 'bioco-price-' + key
        assert any('bioco-loop-empty' in classes(node) for node in nodes)
    table = json.dumps(definitions['table'])
    for field in ['name', 'price', 'shares', 'share_cost', 'work', 'persons']:
        assert 'loop_bioco_price_' + field in table
    assert not any(str(amount) in table for amount in [750, 1280, 2350])


def classes(block):
    return block['attrs'].get('module', {}).get('advanced', {}).get('htmlAttributes', {}).get('desktop', {}).get('value', {}).get('class', '')


def native_dom(blocks, source):
    """Only Divi rendering/query transport is replaced; no arithmetic in this adapter."""
    def variable(value, tier):
        if not isinstance(value, str) or not value.startswith('$variable('):
            return value
        name = json.loads(value[len('$variable('):-2])['value']['name']
        field = name.removeprefix('loop_bioco_price_')
        return '#bioco-price-' + tier['key'] if field == 'select_url' else str(tier[field])

    def render(block, tier=None):
        cls = classes(block)
        if 'bioco-loop-empty' in cls:
            return '' if source['tiers'] else '<div class="et_pb_text ' + cls + '"><div class="et_pb_text_inner">Empty</div></div>'
        attrs = block['attrs']
        if tier is None and 'loop' in attrs.get('module', {}).get('advanced', {}):
            return ''.join(render(block, record) for record in source['tiers'])
        content = ''.join(render(child, tier) for child in block['innerBlocks'])
        if block['blockName'] == 'divi/text':
            content = '<div class="et_pb_text_inner">' + variable(attrs['content']['innerContent']['desktop']['value'], tier) + '</div>'
        elif block['blockName'] == 'divi/heading':
            content = '<h3>' + html.escape(variable(attrs['title']['innerContent']['desktop']['value'], tier)) + '</h3>'
        elif block['blockName'] == 'divi/button':
            value = attrs['button']['innerContent']['desktop']['value']
            content = '<a href="' + html.escape(variable(value['linkUrl'], tier), quote=True) + '">' + html.escape(variable(value['text'], tier)) + '</a>'
        data = ' data-bioco-prices="' + html.escape(json.dumps(source), quote=True) + '"' if 'bioco-price-calculator' in cls else ''
        return '<div class="' + cls + '"' + data + '>' + content + '</div>'
    return ''.join(render(block) for block in blocks)


def test_native_calculator_all_totals_new_tier_zero_none_and_editable_copy():
    source = catalog(records() + "$options['bioco_pricing_enabled']=true;echo json_encode(bioco_price_catalog());")
    # Adding an ordinary price record must expand choices without adding JS branches.
    source['tiers'].append(dict(key='add', name='Neue Stufe', price=900, shares=2, work=12, persons=3))
    blocks = layouts()['calculator']
    dom = native_dom(blocks, source).replace('Jetzt mitmachen', 'Mein geänderter Button').replace('/anmeldung/', '/anmeldung/?campaign=site#step')
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        page = browser.new_page()
        page.set_content('<base href="https://example.test/">' + dom + dom)
        page.add_script_tag(path=str(ROOT / 'wordpress/web/app/mu-plugins/bioco-core/assets/native-pricing.js'))
        root = page.locator('[data-bioco-prices]').first
        for key, amount in [('halb-1-person', "1'000"), ('standard-2-3-personen', "1'780"), ('doppel-4-6-personen', "3'350"), ('add', "1'400")]:
            root.locator('a[href="#bioco-price-' + key + '"]').click()
            assert root.locator('.bioco-price-value-total').inner_text() == amount
        root.locator('a[href="#bioco-share-add"]').click()
        assert root.locator('.bioco-price-value-shares').inner_text() == '3'
        href = root.locator('.bioco-price-signup a').get_attribute('href')
        assert 'abo=add' in href and 'shares=3' in href and 'additional=1' in href
        assert 'campaign=site' in href and href.endswith('#step')
        assert root.locator('.bioco-price-signup a').inner_text() == 'Mein geänderter Button'
        root.locator('a[href="#bioco-share-remove"]').click()
        assert root.locator('.bioco-price-value-shares').inner_text() == '2'
        root.locator('a[href="#bioco-price-kein"]').click()
        assert root.locator('.bioco-price-signup').is_hidden()
        assert root.locator('.bioco-price-value-total').inner_text() == '0'
        root.locator('a[href="#bioco-share-add"]').click()
        assert root.locator('.bioco-price-signup').is_visible()
        assert root.locator('.bioco-price-value-total').inner_text() == '250'
        assert page.locator('[data-bioco-prices]').nth(1).locator('.bioco-price-value-total').inner_text() == "1'000"
        # Missing/deleted optional controls must not prevent amounts from updating.
        root.locator('.bioco-price-signup').evaluate('(el)=>el.remove()')
        root.locator('a[href="#bioco-price-add"]').click()
        assert root.locator('.bioco-price-value-total').inner_text() == "1'400"
        page.set_content('<base href="https://example.test/">' + native_dom(blocks, {**source, 'share_price': 0}))
        page.add_script_tag(path=str(ROOT / 'wordpress/web/app/mu-plugins/bioco-core/assets/native-pricing.js'))
        root = page.locator('[data-bioco-prices]')
        root.locator('a[href="#bioco-price-standard-2-3-personen"]').click()
        root.locator('a[href="#bioco-share-add"]').click()
        assert root.locator('.bioco-price-value-total').inner_text() == "1'280"
        browser.close()


def test_native_query_is_scoped_and_invalid_catalog_never_lists_partial_prices():
    out = catalog(records() + r'''
function add_filter(...$args){}function bioco_native_extract_wrapper($attrs){return ['className'=>$attrs['class']??''];}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/price-runtime.php';
$options['bioco_pricing_enabled']=true;$data=['query_args'=>['meta_query'=>[['key'=>'stale']]],'query_type'=>'post_types'];
$other=bioco_price_native_query($data,['class'=>'other']);
$valid=bioco_price_native_query($data,['class'=>'bioco-price-loop']);
$types=bioco_price_native_types(['existing','bioco_price']);
update_post_meta(2,'price_price','invalid');$invalid=bioco_price_native_query($data,['class'=>'bioco-price-loop']);
echo json_encode(compact('other','valid','invalid','types'));
''')
    assert out['other']['query_args'] == {'meta_query': [{'key': 'stale'}]}
    assert out['valid']['query_args']['post__in'] == [1, 2, 3]
    assert 'meta_query' not in out['valid']['query_args']
    assert out['invalid']['query_args']['post__in'] == [0]
    assert out['types'] == ['existing', 'bioco_price']
