"""Saved price conversion, parser round trips and one-page conditional SQL."""
import copy
import json
import os
import re
import sqlite3
import subprocess
import sys
from pathlib import Path
from xml.etree import ElementTree as ET

import pytest
from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
PUBLIC = json.loads((ROOT / 'tests/fixtures/price-adoption-public-copy.json').read_text())
PHP = r'''
define('ABSPATH', '/');
$state = json_decode(stream_get_contents(STDIN), true);
function home_url($path) { return $GLOBALS['state']['site'] . ltrim($path, '/'); }
function wp_json_encode($v, $options=0) { return json_encode($v, $options); }
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function add_filter(...$args) {}
function add_action(...$args) {}
class Database {
    public $posts = 'posts';
    function prepare($sql, ...$args) {
        foreach ($args as $arg) $sql = preg_replace_callback('/%s/', fn()=>"'" . str_replace("'", "''", (string)$arg) . "'", $sql, 1);
        return str_replace('%', '{wpdb-placeholder-test}', $sql);
    }
    function remove_placeholder_escape($sql) { return str_replace('{wpdb-placeholder-test}', '%', $sql); }
}
$wpdb = new Database();
function parse_blocks($value) { return (new WP_Block_Parser())->parse($value); }
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
WP_SERIALIZER
require 'wordpress/web/app/mu-plugins/bioco-import/includes/price-adoption.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-modules.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/price-runtime.php';
try {
    if (($state['mode'] ?? '') === 'sql') {
        echo json_encode(['sql'=>bioco_price_adoption_sql($state['plan'], $state['expected'], $state['restore'] ?? false)]);
    } else {
        $source=$state['source'];
        if (isset($state['blocks'])) $source['post_content']=serialize_blocks($state['blocks']);
        $plan=bioco_price_adoption_plan($source, $state['kind'], $state['snapshot_site'] ?? $state['site'], $state['body_hash'] ?? hash('sha256', $source['post_content']));
        echo json_encode(['plan'=>$plan, 'before_blocks'=>parse_blocks($source['post_content']),
            'blocks'=>parse_blocks($plan['after_content']),
            'wrappers'=>array_map(static fn($section)=>bioco_native_extract_wrapper($section['attrs']) +
                ['calculator'=>bioco_price_native_has_class($section['attrs'], 'bioco-price-calculator')],
                parse_blocks($plan['after_content'])[0]['innerBlocks']),
            'roundtrip'=>serialize_blocks(parse_blocks($plan['after_content'])) === $plan['after_content'],
            'apply'=>bioco_price_adoption_sql($plan,$plan['fingerprint']),
            'restore'=>bioco_price_adoption_sql($plan,$plan['fingerprint'],true)]);
    }
} catch (Throwable $e) { echo json_encode(['error'=>$e->getMessage()]); }
'''


def run(kind='table', source=None, blocks=None, **extra):
    source = source or {'ID': 6 if kind == 'table' else 10, 'post_type': 'page',
        'post_status': 'publish', 'post_title': 'Abos' if kind == 'table' else 'Rechner',
        'post_content': '', 'post_modified_gmt': '2026-10-12 06:00:00'}
    data = {'site': 'https://example.test/', 'kind': kind, 'source': source, **extra}
    if blocks is not None: data['blocks'] = blocks
    proc = subprocess.run(['php', '-r', PHP.replace('WP_SERIALIZER', WP_SERIALIZER_STUB)],
        cwd=ROOT, input=json.dumps(data), text=True, capture_output=True, check=True)
    assert proc.stderr == ''
    return json.loads(proc.stdout)


def b(name, attrs=None, children=None):
    children = children or []
    return {'blockName': name, 'attrs': attrs or {}, 'innerBlocks': children,
        'innerHTML': '', 'innerContent': [None] * len(children) if children else ['\n']}


def text(value):
    return b('divi/text', {'content': {'innerContent': {'desktop': {'value': value}}}})


def section(modules):
    return b('divi/section', {}, [b('divi/row', {}, [b('divi/column', {}, modules)])])


def fixture(kind):
    if kind == 'table':
        blocks = [section([text('<p>Einleitung</p>'), text(PUBLIC['table_html']), b('divi/button')])]
        blocks += [section([text(item)]) for item in PUBLIC['narratives']]
    else:
        blocks = [section([b('bioco-divi/pricing-calculator', PUBLIC['calculator_attrs'])])]
    return [b('divi/placeholder', {}, blocks)]


def names(blocks):
    return [node['blockName'] for node in blocks for node in [node] +
            [desc for child in node.get('innerBlocks', []) for desc in walk(child)]]


def walk(node):
    yield node
    for child in node.get('innerBlocks', []): yield from walk(child)


def native_class(node):
    attrs = node['attrs'] if isinstance(node['attrs'], dict) else {}
    return attrs.get('module', {}).get('advanced', {}).get('htmlAttributes', {}).get('desktop', {}).get('value', {}).get('class', '')


def native_role(node):
    attrs = node['attrs'] if isinstance(node['attrs'], dict) else {}
    return next((item['value'] for item in attrs.get('module', {}).get('decoration', {}).get('attributes', {})
        .get('desktop', {}).get('value', {}).get('attributes', []) if item.get('name') == 'role'), None)


@pytest.mark.parametrize('kind', ['table', 'calculator'])
def test_saved_public_copy_converts_to_native_parser_roundtrip(kind):
    result = run(kind, blocks=fixture(kind))
    assert 'error' not in result, result
    assert result['roundtrip']
    after = list(walk(result['blocks'][0]))
    assert not any(node['blockName'] == 'bioco-divi/pricing-calculator' for node in after)
    assert result['plan']['before']['post_content'] != result['plan']['after_content']
    assert 'BINARY `post_content` = BINARY' in result['apply']
    if kind == 'table':
        assert result['plan']['report']['table']['headers'] == [
            'Gemüsekorb', 'Personen', 'Jahrespreis', 'Anteilsscheine Kosten', 'Mitarbeit pro Jahr']
        assert sum(node['blockName'] == 'divi/image' for node in after) == 1
        assert "CHF 1&#039;280.-" in PUBLIC['table_html']
        assert 'loop_bioco_price_price_table' in result['plan']['after_content']
        assert 'loop_bioco_price_share_cost_table' in result['plan']['after_content']
        assert all('CHF 250.-' not in str(node['attrs']) for node in after if node['blockName'] == 'divi/text')
    else:
        copy = PUBLIC['calculator_attrs']
        output = json.dumps([node['attrs'] for node in after], ensure_ascii=False)
        for field in ['cta_label','cta_note','shares_only_info','no_subscription_label','work_suffix','total_label']:
            assert copy[field]['innerContent']['desktop']['value'] in output
        assert copy['signup_url']['innerContent']['desktop']['value'] in output


def test_saved_price_table_keeps_five_native_columns_and_authored_cells():
    result = run(blocks=fixture('table'))
    assert result['roundtrip']
    table = next(node for node in walk(result['blocks'][0]) if 'bioco-price-table' in native_class(node).split()
                 and node['blockName'] == 'divi/section')
    assert native_role(table) == 'table'
    assert table['attrs']['module']['decoration']['overflow']['desktop']['value']['x'] == 'auto'
    header, data = table['innerBlocks']
    assert native_role(header) == native_role(data) == 'row'
    assert len(header['innerBlocks']) == len(data['innerBlocks']) == 5
    assert [native_role(col) for col in header['innerBlocks']] == ['columnheader'] * 5
    assert [native_role(col) for col in data['innerBlocks']] == ['cell'] * 5
    for row in (header, data):
        attrs = row['attrs']['module']
        assert attrs['advanced']['columnStructure']['desktop']['value'] == '4_4,4_4,4_4,4_4,4_4'
        assert attrs['decoration']['layout']['desktop']['value']['display'] == 'grid'
        assert attrs['decoration']['sizing']['desktop']['value']['minWidth'] == '914px'
    assert 'loop' not in header['attrs']['module']['advanced']
    assert data['attrs']['module']['advanced']['loop']['desktop']['value']['loopId'] == 'bioco-price-table'
    header_values = [col['innerBlocks'][0]['attrs']['title']['innerContent']['desktop']['value'] for col in header['innerBlocks']]
    assert header_values == ['Gemüsekorb', 'Personen', 'Jahrespreis', 'Anteilsscheine Kosten', 'Mitarbeit pro Jahr']
    cells = data['innerBlocks']
    assert ['loop_bioco_price_table_label', 'loop_bioco_price_shares_label'] == [
        json.loads(module['attrs'][key]['innerContent']['desktop']['value'][len('$variable('):-2])['value']['name']
        for module, key in [(cells[0]['innerBlocks'][0], 'title'), (cells[0]['innerBlocks'][1], 'content')]]
    image = cells[1]['innerBlocks'][0]
    assert image['blockName'] == 'divi/image'
    assert 'loop_bioco_price_persons_icon' in json.dumps(image)
    assert 'loop_bioco_price_persons_accessible' in json.dumps(image)
    for cell, binding in [(cells[2], 'loop_bioco_price_price_table'), (cells[3], 'loop_bioco_price_share_cost_table')]:
        contents = [module['attrs']['content']['innerContent']['desktop']['value'] for module in cell['innerBlocks']]
        assert len(contents) == 1 and contents[0].startswith('CHF $variable(') and contents[0].endswith(')$.-')
        assert binding in contents[0]
    work = [module['attrs']['content']['innerContent']['desktop']['value'] for module in cells[4]['innerBlocks']]
    assert len(work) == 1 and 'loop_bioco_price_work' in work[0]
    assert work[0].endswith(' Arbeitseinsätze<br>à 2 Stunden')
    assert 'loop_bioco_price_persons_range' not in json.dumps(table)
    assert [row['icons'] for row in result['plan']['report']['table']['rows']] == [1, 2, 4]
    assert [row['price'] for row in result['plan']['report']['table']['rows']] == ['CHF 750.-', "CHF 1'280.-", "CHF 2'350.-"]


def test_native_price_table_grid_controls_contain_mobile_overflow_in_browser():
    from playwright.sync_api import sync_playwright
    result = run(blocks=fixture('table'))
    table = next(node for node in walk(result['blocks'][0]) if node['blockName'] == 'divi/section'
                 and 'bioco-price-table' in native_class(node).split())
    rows = table['innerBlocks']
    grid = rows[0]['attrs']['module']['decoration']['layout']['desktop']['value']
    min_width = rows[0]['attrs']['module']['decoration']['sizing']['desktop']['value']['minWidth']
    markup = '<div id="table">' + ''.join(
        '<div class="row">' + ''.join(f'<div class="cell">{index}</div>' for index in range(5)) + '</div>'
        for _ in rows) + '</div>'
    style = (f'html,body{{margin:0}}body{{width:100%}}#table{{width:100%;max-width:1100px;overflow-x:auto}}'
             f'.row{{display:{grid["display"]};grid-template-columns:{grid["gridTemplateColumns"]};'
             f'column-gap:{grid["columnGap"]};min-width:{min_width};box-sizing:border-box}}'
             '.cell{box-sizing:border-box;padding:12px}')
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        for width in (1280, 390):
            page = browser.new_page(viewport={'width': width, 'height': 700})
            page.set_content(f'<style>{style}</style>{markup}')
            measurements = page.evaluate('''() => {
              const table = document.querySelector('#table');
              const rows = [...table.querySelectorAll('.row')];
              return {pageWidth: document.documentElement.scrollWidth,
                tableWidth: table.clientWidth, contentWidth: table.scrollWidth,
                columns: rows.map(row => [...row.children].map(cell => Math.round(cell.getBoundingClientRect().left)))};
            }''')
            assert measurements['columns'][0] == measurements['columns'][1]
            assert measurements['pageWidth'] == width
            if width == 390:
                assert measurements['contentWidth'] > measurements['tableWidth']
                page.locator('#table').evaluate('(node) => node.scrollLeft = node.scrollWidth')
                assert page.locator('#table').evaluate('(node) => node.scrollLeft') > 0
            page.close()
        browser.close()


def test_saved_calculator_has_native_four_choice_grid_and_invoice_columns():
    result = run('calculator', blocks=fixture('calculator'))
    assert result['roundtrip']
    section = next(node for node in walk(result['blocks'][0]) if node['blockName'] == 'divi/section'
                   and 'bioco-price-calculator' in native_class(node).split())
    rows = section['innerBlocks']
    assert len(rows) == 8
    grid = section['attrs']['module']['decoration']['layout']
    assert grid['desktop']['value']['gridTemplateColumns'] == 'repeat(4, minmax(0, 1fr))'
    assert grid['phone']['value']['gridTemplateColumns'] == 'minmax(0, 1fr)'
    assert 'bioco-price-loop' in native_class(rows[0]).split()
    assert rows[0]['attrs']['module']['advanced']['loop']['desktop']['value']['loopId'] == 'bioco-price-calculator'
    copy = PUBLIC['calculator_attrs']
    assert 'loop_bioco_price_name' in json.dumps(rows[0])
    assert 'loop_bioco_price_price_table' in json.dumps(rows[0])
    badge = next(node for node in walk(rows[0]) if 'bioco-price-recommended-badge' in native_class(node).split())
    assert badge['blockName'] == 'divi/text'
    assert 'loop_bioco_price_recommended_marker' in badge['attrs']['content']['innerContent']['desktop']['value']
    assert badge['attrs']['content']['innerContent']['desktop']['value'].endswith(
        copy['recommended_label']['innerContent']['desktop']['value'])
    assert badge['attrs']['module']['decoration']['background']['desktop']['value']['color'] == 'var(--wp--preset--color--bioco-carrot)'
    assert badge['attrs']['module']['decoration']['sizing']['desktop']['value']['width'] == 'fit-content'
    assert 'bioco-price-none-card' in native_class(rows[1]).split()
    assert '#bioco-price-kein' in json.dumps(rows[1])
    assert [len(row['innerBlocks']) for row in rows[2:6]] == [5, 5, 5, 5]
    for row in rows[2:]:
        for device in ('desktop', 'tablet', 'phone'):
            decoration = row['attrs']['module']['decoration']
            sizing = decoration['sizing'][device]['value']
            assert sizing['gridColumnStart'] == '1'
            assert sizing['gridColumnEnd'] == '-1'
            assert 'gridColumn' not in decoration.get('layout', {}).get(device, {}).get('value', {})
    for row in rows[2:6]:
        assert row['attrs']['module']['decoration']['sizing']['desktop']['value']['minWidth'] == '914px'
        for device in ('tablet', 'phone'):
            decoration = row['attrs']['module']['decoration']
            assert decoration['sizing'][device]['value']['minWidth'] == '0px'
            assert decoration['layout'][device]['value']['gridTemplateColumns'] == 'repeat(2, minmax(0, 1fr))'
            for index, column in enumerate(row['innerBlocks']):
                responsive = column['attrs']['module']['decoration']
                assert responsive['spacing']['phone']['value']['padding'] == {
                    'top': '8px', 'bottom': '8px', 'left': '16px', 'right': '16px'}
                if row is rows[2]:
                    assert responsive['spacing']['desktop']['value']['padding'] == {
                        'top': '16px', 'bottom': '16px', 'left': '24px', 'right': '24px'}
                blank_text = (len(column['innerBlocks']) == 1
                    and column['innerBlocks'][0]['blockName'] == 'divi/text'
                    and column['innerBlocks'][0]['attrs']['content']['innerContent']['desktop']['value'] == '')
                if not column['innerBlocks'] or blank_text:
                    assert responsive['layout'][device]['value']['display'] == 'none'
                else:
                    sizing = responsive['sizing'][device]['value']
                    assert (sizing['gridColumnStart'], sizing['gridColumnEnd']) == (
                        (('1', '2') if index % 2 == 0 else ('2', '3')) if row is rows[2]
                        else ('1', '2') if index == 1 else ('2', '3') if index == 2 else ('1', '-1'))
    assert rows[2]['innerBlocks'][4]['innerBlocks'] == []
    assert rows[3]['innerBlocks'][4]['innerBlocks'] == []
    header = [col['innerBlocks'][0]['attrs']['title']['innerContent']['desktop']['value']
              for col in rows[2]['innerBlocks'][:4]]
    assert rows[2]['innerBlocks'][0]['attrs']['module']['decoration']['spacing']['desktop']['value']['padding']['top'] == '16px'
    assert header == [copy[key]['innerContent']['desktop']['value']
        for key in ('article_label', 'quantity_label', 'unit_price_label', 'total_label')]
    for column in rows[2]['innerBlocks'][:4]:
        font_attrs = column['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']
        font = font_attrs['desktop']['value']
        assert font == {'headingLevel': 'h3', 'size': '16px', 'lineHeight': '1.6em',
                        'weight': '500', 'color': '#ffffff'}
        assert font_attrs['phone']['value'] == {'size': '14px', 'lineHeight': '1.6em'}
    for row_index, column_index in [(3, 2), (3, 3), (4, 3), (5, 3)]:
        column = rows[row_index]['innerBlocks'][column_index]
        assert len(column['innerBlocks']) == 3
        for device in ('desktop', 'tablet', 'phone'):
            layout = column['attrs']['module']['decoration']['layout'][device]['value']
            assert {key: layout[key] for key in ('display', 'flexDirection', 'alignItems', 'columnGap', 'rowGap')} == {
                'display': 'flex', 'flexDirection': 'row', 'alignItems': 'baseline',
                'columnGap': '0px', 'rowGap': '0px'}
        prefix, value, suffix = column['innerBlocks']
        assert prefix['attrs']['content']['innerContent']['desktop']['value'] == 'CHF'
        assert prefix['attrs']['module']['decoration']['spacing']['desktop']['value']['margin']['right'] == '4px'
        assert 'bioco-price-value-' in native_class(value)
        assert suffix['attrs']['content']['innerContent']['desktop']['value'] == '.-'
        for module in (prefix, value, suffix):
            font = module['attrs']['content']['decoration']['bodyFont']['body']['font']
            assert font['desktop']['value']['size'] == '16px'
            assert font['desktop']['value']['weight'] == '700'
            assert font['phone']['value'] == {'size': '14px', 'lineHeight': '1.6em'}
    article = rows[3]['innerBlocks'][0]
    shares_description = rows[4]['innerBlocks'][0]
    for device in ('desktop', 'tablet', 'phone'):
        article_layout = article['attrs']['module']['decoration']['layout'][device]['value']
        assert {key: article_layout[key] for key in ('display','flexDirection','flexWrap','columnGap','rowGap')} == {
            'display':'flex','flexDirection':'row','flexWrap':'wrap','columnGap':'4px','rowGap':'4px'}
        shares_layout = shares_description['attrs']['module']['decoration']['layout'][device]['value']
        assert (shares_layout['display'], shares_layout['flexDirection'], shares_layout['rowGap']) == ('flex','column','4px')
        assert rows[4]['innerBlocks'][4]['attrs']['module']['decoration']['layout'][device]['value']['rowGap'] == '4px'
    assert len(article['innerBlocks']) == 5 and len(shares_description['innerBlocks']) == 3
    for index, module in enumerate(article['innerBlocks']):
        font = module['attrs']['content']['decoration']['bodyFont']['body']['font']
        assert font['desktop']['value']['size'] == ('16px' if index == 0 else '14px')
        assert font['desktop']['value']['weight'] == '700'
        assert font['phone']['value'] == {'size': '14px', 'lineHeight': '1.6em'}
        if index < 2:
            assert all(module['attrs']['module']['decoration']['sizing'][device]['value']['width'] == '100%'
                for device in ('desktop','tablet','phone'))
    assert article['innerBlocks'][2]['attrs']['module']['decoration']['spacing']['desktop']['value']['margin']['top'] == '4px'
    assert article['innerBlocks'][1]['attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] == 'var(--wp--preset--color--bioco-green)'
    assert shares_description['innerBlocks'][1]['attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] == 'var(--wp--preset--color--bioco-beet)'
    actions = rows[4]['innerBlocks'][4]['innerBlocks']
    assert [m['blockName'] for m in actions] == ['divi/text','divi/button','divi/button','divi/text']
    label = actions[0]['attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']
    count = actions[3]['attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']
    assert (label['size'], label['lineHeight'], label['weight'], label['color']) == ('12px','1.6em','600','#4A4A4A')
    assert (count['size'], count['lineHeight']) == ('14px','1.6em')
    for action in actions[1:3]:
        font = action['attrs']['button']['decoration']['font']['font']['desktop']['value']
        padding = action['attrs']['module']['decoration']['spacing']['desktop']['value']['padding']
        assert (font['size'], font['lineHeight']) == ('14px','1.6em')
        assert padding == {'top':'11px','bottom':'11px','left':'16px','right':'16px'}
    total_font = rows[5]['innerBlocks'][0]['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']
    assert total_font['desktop']['value'] == {'headingLevel':'h3','size':'16px','lineHeight':'1.6em','weight':'700','color':'#1A1A1A'}
    assert total_font['phone']['value'] == {'size':'14px','lineHeight':'1.6em'}
    for marker in ('bioco-price-value-name', 'bioco-price-value-price', 'bioco-price-value-work',
                   'bioco-price-value-shares', 'bioco-price-value-share-cost', 'bioco-price-value-total',
                   'bioco-price-signup', 'bioco-price-no-selection'):
        assert marker in json.dumps(rows)


def test_native_invoice_header_defaults_fit_each_cell_in_browser():
    from playwright.sync_api import sync_playwright
    result = run('calculator', blocks=fixture('calculator'))
    section = next(node for node in walk(result['blocks'][0]) if node['blockName'] == 'divi/section'
                   and 'bioco-price-calculator' in native_class(node).split())
    header = section['innerBlocks'][2]
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        for width, device in [(1440, 'desktop'), (390, 'phone')]:
            row_layout = header['attrs']['module']['decoration']['layout'][device]['value']
            cell_markup = ''
            cell_css = ''
            for index, column in enumerate(header['innerBlocks'][:4], 1):
                heading = column['innerBlocks'][0]
                label = heading['attrs']['title']['innerContent']['desktop']['value']
                font = heading['attrs']['title']['decoration']['font']['font']['desktop']['value']
                font = {**font, **heading['attrs']['title']['decoration']['font']['font'].get(device, {}).get('value', {})}
                responsive = column['attrs']['module']['decoration']['sizing'].get(device, {}).get('value', {})
                span = (f'grid-column:{responsive["gridColumnStart"]}/{responsive["gridColumnEnd"]};'
                        if 'gridColumnStart' in responsive else '')
                cell_css += (f'.cell:nth-child({index}){{{span}}}'
                             f'.cell:nth-child({index}) h3{{font-size:{font.get("size", "40px")};'
                             f'line-height:{font.get("lineHeight", "1.2em")};'
                             f'font-weight:{font.get("weight", "700")};color:{font.get("color", "#1a1a1a")}}}')
                cell_markup += f'<div class="cell"><h3>{label}</h3></div>'
            page = browser.new_page(viewport={'width': width, 'height': 700})
            pad = '8px 16px' if device == 'phone' else '16px 24px'
            page.set_content('<style>html,body{margin:0}*{box-sizing:border-box}'
                f'.row{{display:grid;width:100%;grid-template-columns:{row_layout["gridTemplateColumns"]};'
                f'column-gap:{row_layout["columnGap"]};row-gap:{row_layout.get("rowGap", "0px")}}}'
                f'.cell{{padding:{pad};min-width:0}}h3{{margin:0;padding-bottom:10px}}'
                + cell_css + '</style><div class="row">' + cell_markup + '</div>')
            bounds = page.evaluate('''() => [...document.querySelectorAll('.cell')].map(cell => {
                const range = document.createRange(); range.selectNodeContents(cell.querySelector('h3'));
                const text = range.getBoundingClientRect(), box = cell.getBoundingClientRect();
                return {label: cell.textContent, left: text.left, right: text.right,
                    cellLeft: box.left, cellRight: box.right};
              })''')
            assert [item['label'] for item in bounds] == ['Artikel', 'Anzahl', 'Einzelpreis', 'Total']
            assert all(item['left'] >= item['cellLeft'] and item['right'] <= item['cellRight'] for item in bounds)
            assert page.evaluate('document.documentElement.scrollWidth') == width
            page.close()
        browser.close()


def test_native_invoice_amount_fragments_share_one_line_in_browser():
    from playwright.sync_api import sync_playwright
    result = run('calculator', blocks=fixture('calculator'))
    section = next(node for node in walk(result['blocks'][0]) if node['blockName'] == 'divi/section'
                   and 'bioco-price-calculator' in native_class(node).split())
    column = section['innerBlocks'][3]['innerBlocks'][2]
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        for width, device in [(1440, 'desktop'), (390, 'phone')]:
            layout = column['attrs']['module']['decoration']['layout'][device]['value']
            pad = column['attrs']['module']['decoration']['spacing'][device]['value']['padding']
            prefix_margin = column['innerBlocks'][0]['attrs']['module']['decoration']['spacing']['desktop']['value']['margin']['right']
            page = browser.new_page(viewport={'width': width, 'height': 700})
            page.set_content('<style>html,body{margin:0}*{box-sizing:border-box}'
                f'.amount{{width:{"150px" if device == "desktop" else "189px"};display:{layout["display"]};'
                f'flex-direction:{layout["flexDirection"]};align-items:{layout["alignItems"]};'
                f'column-gap:{layout["columnGap"]};row-gap:{layout["rowGap"]};'
                f'padding:{pad["top"]} {pad["right"]} {pad["bottom"]} {pad["left"]}}}'
                f'.part:first-child{{margin-right:{prefix_margin}}}p{{margin:0}}</style>'
                '<div class="amount"><div class="part"><p>CHF</p></div><div class="part">750</div>'
                '<div class="part"><p>.-</p></div></div>')
            positions = page.evaluate('''() => {
                const cell = document.querySelector('.amount').getBoundingClientRect();
                return {cellRight: cell.right, parts: [...document.querySelectorAll('.part')]
                    .map(el => ({left: el.getBoundingClientRect().left,
                        right: el.getBoundingClientRect().right,
                        top: el.getBoundingClientRect().top}))};
              }''')
            assert len({round(part['top']) for part in positions['parts']}) == 1
            assert positions['parts'][0]['right'] < positions['parts'][1]['left']
            assert positions['parts'][1]['right'] == positions['parts'][2]['left']
            assert positions['parts'][-1]['right'] <= positions['cellRight']
            page.close()
        browser.close()


@pytest.mark.parametrize('saved_class', ['', 'bioco-dynamic-section'])
def test_saved_decoration_class_keeps_calculator_runtime_marker(saved_class):
    blocks = fixture('calculator')
    section = blocks[0]['innerBlocks'][0]
    section['attrs'] = {'module': {
        'advanced': {'htmlAttributes': {'desktop': {'value': {'class': 'stale-class'}}}},
        'decoration': {
            'attributes': {'desktop': {'value': {'attributes': [
                {'name': 'id', 'value': 'authored-calculator'},
                {'name': 'class', 'value': saved_class}]} }},
            'spacing': {'phone': {'value': {'padding': {'top': '12px'}}}}}}}
    result = run('calculator', blocks=blocks)
    assert result['roundtrip']
    wrapper = result['wrappers'][0]
    assert wrapper['calculator'], 'The real runtime must recognize the converted saved Section.'
    assert wrapper['anchor'] == 'authored-calculator'
    assert wrapper['className'].split() == saved_class.split() + ['bioco-price-calculator']
    converted = result['blocks'][0]['innerBlocks'][0]
    assert converted['attrs']['module']['decoration']['spacing'] == section['attrs']['module']['decoration']['spacing']


def test_native_calculator_grid_controls_keep_invoice_below_choices_in_browser():
    from playwright.sync_api import sync_playwright
    result = run('calculator', blocks=fixture('calculator'))
    section = next(node for node in walk(result['blocks'][0]) if node['blockName'] == 'divi/section'
                   and 'bioco-price-calculator' in native_class(node).split())
    layout = section['attrs']['module']['decoration']['layout']
    invoice = section['innerBlocks'][2]
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        for width in (1280, 390):
            page = browser.new_page(viewport={'width': width, 'height': 700})
            device = 'phone' if width < 640 else 'desktop'
            columns = layout[device]['value']['gridTemplateColumns']
            sizing = invoice['attrs']['module']['decoration']['sizing'][device]['value']
            # Divi's Sizing declaration emits grid-column from these two fields.
            span = sizing['gridColumnStart'] + '/' + sizing['gridColumnEnd']
            min_width = sizing.get('minWidth', invoice['attrs']['module']['decoration']['sizing']['desktop']['value']['minWidth'])
            invoice_grid = invoice['attrs']['module']['decoration']['layout'][device]['value']['gridTemplateColumns']
            cell_css = ''
            for index, column in enumerate(invoice['innerBlocks'], 1):
                decoration = column['attrs']['module']['decoration']
                cell_layout = decoration.get('layout', {}).get(device, {}).get('value', {})
                cell_sizing = decoration.get('sizing', {}).get(device, {}).get('value', {})
                if cell_layout.get('display') == 'none' or not column['innerBlocks']:
                    cell_css += f'.invoice>div:nth-child({index}){{display:none}}'
                elif 'gridColumnStart' in cell_sizing:
                    cell_css += f'.invoice>div:nth-child({index}){{grid-column:{cell_sizing["gridColumnStart"]}/{cell_sizing["gridColumnEnd"]}}}'
            page.set_content('<style>html,body{margin:0}.calculator{display:grid;grid-template-columns:' + columns
                + ';gap:16px;width:100%;box-sizing:border-box;overflow-x:auto}.card{min-height:80px}.invoice{grid-column:' + span
                + ';min-width:' + min_width + ';display:grid;grid-template-columns:' + invoice_grid + ';gap:12px;min-height:80px}'
                + cell_css + '</style>'
                + '<div class="calculator"><div class="card">1</div><div class="card">2</div><div class="card">3</div>'
                + '<div class="card">Kein Abo</div><div class="invoice"><div>Artikel</div>'
                + '<div>Anzahl</div><div>Einzelpreis</div><div>Total</div><div></div></div></div>')
            positions = page.evaluate('''() => {
              const cards = [...document.querySelectorAll('.card')].map(el => el.getBoundingClientRect());
              const invoice = document.querySelector('.invoice').getBoundingClientRect();
              const box = document.querySelector('.calculator');
              return {cards: cards.map(rect => [Math.round(rect.left), Math.round(rect.top)]),
                invoiceTop: Math.round(invoice.top), invoiceLeft: Math.round(invoice.left),
                invoiceWidth: Math.round(invoice.width), pageWidth: document.documentElement.scrollWidth,
                boxWidth: box.clientWidth, contentWidth: box.scrollWidth};
            }''')
            assert positions['invoiceTop'] > max(card[1] for card in positions['cards'])
            assert positions['invoiceLeft'] == positions['cards'][0][0]
            assert positions['pageWidth'] == width
            if width == 1280:
                assert len({card[1] for card in positions['cards']}) == 1
                assert positions['invoiceWidth'] == positions['boxWidth']
            else:
                assert len({card[1] for card in positions['cards']}) == 4
                assert positions['invoiceWidth'] == positions['boxWidth']
                assert positions['contentWidth'] == positions['boxWidth']
                assert page.locator('.invoice').evaluate('(node) => [...node.children].every(cell => cell.getBoundingClientRect().right <= innerWidth)')
            page.close()
        browser.close()


def test_changed_content_site_and_fingerprint_refused():
    blocks = fixture('table')
    assert 'changed' in run(blocks=blocks, body_hash='0'*64)['error']
    assert 'another site' in run(blocks=blocks, snapshot_site='https://other.test/')['error']
    result = run(blocks=blocks)
    plan = result['plan']
    plan['before']['post_title'] += ' edited'
    denied = run(mode='sql', plan=plan, expected=result['plan']['fingerprint'])
    assert 'changed' in denied['error']


def test_apply_restore_sql_guard_all_saved_columns_and_percent():
    blocks = fixture('table')
    blocks[0]['innerBlocks'].append(section([text('<p>100% lokal</p>')]))
    result = run(blocks=blocks)
    plan = result['plan']
    for sql in [result['apply'], result['restore']]:
        assert '100%' in sql and '{wpdb-placeholder-test}' not in sql
        for column in plan['before']:
            assert f'BINARY `{column}` = BINARY' in sql
    assert 'BINARY `post_content` = BINARY' in result['restore']
    db = sqlite3.connect(':memory:')
    db.execute('CREATE TABLE posts (ID INTEGER PRIMARY KEY, post_type TEXT, post_status TEXT, post_title TEXT, post_content TEXT, post_modified_gmt TEXT)')
    db.execute('INSERT INTO posts VALUES (?,?,?,?,?,?)', tuple(plan['before'].values()))
    def sqlite_sql(sql):
        return re.sub(r'BINARY (`[^`]+`) = BINARY (\'[^\']*(?:\'\'[^\']*)*\')',
            r'CAST(\1 AS BLOB) = CAST(\2 AS BLOB)', sql)
    db.execute(sqlite_sql(result['apply']))
    assert db.execute('SELECT post_content FROM posts').fetchone()[0] == plan['after_content']
    db.execute(sqlite_sql(result['apply']))
    assert db.execute('SELECT post_content FROM posts').fetchone()[0] == plan['after_content']
    db.execute(sqlite_sql(result['restore']))
    assert db.execute('SELECT post_content FROM posts').fetchone()[0] == plan['before']['post_content']
    db.execute("UPDATE posts SET post_title = 'Editor changed title'")
    db.execute(sqlite_sql(result['apply']))
    assert db.execute('SELECT post_content FROM posts').fetchone()[0] == plan['before']['post_content']


def test_private_capture_operator_requires_both_snapshots(tmp_path):
    report = tmp_path / 'operator.xml'
    result = subprocess.run([
        sys.executable, '-m', 'pytest', 'tests/operators/wordpress_price_captures.py',
        '-q', f'--junitxml={report}',
    ], cwd=ROOT, text=True, capture_output=True,
        env=os.environ | {'BIOCO_PRICE_CAPTURE_DIR': str(tmp_path / 'missing-captures')})
    assert result.returncode == 1, result.stdout + result.stderr
    cases = list(ET.parse(report).iter('testcase'))
    assert len(cases) == 4
    assert all(case.find('failure') is not None and case.find('skipped') is None for case in cases)
    for environment in ['staging', 'production']:
        assert f'{environment}-price-pages.json' in result.stdout
    assert 'Required private saved page snapshot missing' in result.stdout


def test_saved_calculator_browser_handoff_uses_authored_cta_and_new_tier():
    from playwright.sync_api import sync_playwright
    from test_wordpress_native_prices import native_dom
    from test_wordpress_prices import records
    from test_wordpress_catalog import catalog
    source = catalog(records() + "$options['bioco_pricing_enabled']=true;echo json_encode(bioco_price_catalog());")
    source['tiers'].append(dict(key='neu', name='Neues Abo', price=900, shares=2, work=12, persons=3))
    converted = run('calculator', blocks=fixture('calculator'))
    dom = native_dom(converted['blocks'][0]['innerBlocks'], source)
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        page = browser.new_page()
        page.set_content('<base href="https://example.test/">' + dom)
        page.add_script_tag(path=str(ROOT / 'wordpress/web/app/mu-plugins/bioco-core/assets/native-pricing.js'))
        root = page.locator('[data-bioco-prices]')
        root.locator('a[href="#bioco-price-neu"]').click()
        assert root.locator('.bioco-price-value-total').inner_text() == "1'400"
        assert root.locator('a.bioco-price-signup').inner_text() == 'Jetzt mitmachen!'
        assert 'abo=neu' in root.locator('a.bioco-price-signup').get_attribute('href')
        root.locator('a[href="#bioco-price-kein"]').click()
        assert root.locator('.bioco-price-signup').is_hidden()
        root.locator('a[href="#bioco-share-add"]').click()
        assert root.locator('.bioco-price-value-total').inner_text() == '250'
        browser.close()


def test_table_bindings_keep_saved_apostrophe_format_and_person_range():
    from test_wordpress_catalog import catalog
    from test_wordpress_prices import records
    out = catalog(records() + r'''
function add_filter(...$args){}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_attr($v){return esc_html($v);}
function esc_url_raw($v){return $v;}
function number_format_i18n($v){return number_format($v,0,'.','.');}
function get_post_type($id){return $GLOBALS['posts'][$id]->post_type??'';}
function get_post_status($id){return $GLOBALS['posts'][$id]->post_status??'';}
function current_user_can(...$args){return false;}
function get_the_title($id){return $GLOBALS['posts'][$id]->post_title;}
function get_queried_object_id(){return 0;}
function plugins_url($path,$base){return 'https://example.test/plugins/bioco-core/'.$path;}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/price-bindings.php';
$options['bioco_pricing_enabled']=true;
$names=['price_table','share_cost_table','persons_range','persons_accessible','shares_label','persons_icon'];$values=[];
foreach($names as $name)$values[$name]=bioco_price_binding_value('', ['name'=>'loop_bioco_price_'.$name,'loop_id'=>2]);
$values['large_share_cost']=bioco_price_binding_value('', ['name'=>'loop_bioco_price_share_cost_table','loop_id'=>3]);
echo json_encode($values);
''')
    assert out['price_table'] == '1&#039;280'
    assert out['share_cost_table'] == '500'
    assert out['large_share_cost'] == '1&#039;000'
    assert out['persons_range'] == '2-3 Personen'
    assert out['persons_accessible'] == '2 Personen'
    assert out['shares_label'] == '2 Anteilsscheine'
    assert out['persons_icon'].endswith('/persons-2.svg')
    for count in [1, 2, 4]:
        svg = (ROOT / f'wordpress/web/app/mu-plugins/bioco-core/assets/price-person-icons/persons-{count}.svg').read_text()
        assert svg.count('<circle') == count and svg.count('<path') == count


def test_editable_recommended_badge_uses_record_context_and_public_visibility():
    from test_wordpress_catalog import catalog
    from test_wordpress_prices import records
    out = catalog(records() + r'''
function add_filter(...$args){}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_attr($v){return esc_html($v);}
function esc_url_raw($v){return $v;}
function number_format_i18n($v){return number_format($v);}
function get_post_type($id){return $GLOBALS['posts'][$id]->post_type??'';}
function get_post_status($id){return $GLOBALS['posts'][$id]->post_status??'';}
function current_user_can(...$args){return false;}
function get_the_title($id){return $GLOBALS['posts'][$id]->post_title;}
function get_queried_object_id(){return 0;}
function bioco_native_extract_wrapper($attrs){return ['className'=>$attrs['module']['advanced']['htmlAttributes']['desktop']['value']['class']??''];}
function bioco_native_render_mode(){return $GLOBALS['mode'];}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/price-bindings.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/price-runtime.php';
$options['bioco_pricing_enabled']=true;
$attrs=['module'=>['advanced'=>['htmlAttributes'=>['desktop'=>['value'=>['class'=>'bioco-price-recommended-badge']]]]]];
$results=[];
foreach([1,2,3] as $id){
  $marker=bioco_price_binding_value('', ['name'=>'loop_bioco_price_recommended_marker','loop_id'=>$id]);
  $html='<div>'.$marker.'Empfohlen</div>';
  $GLOBALS['mode']='public';
  $public=bioco_price_native_wrapper($html,['name'=>'divi/text','attrs'=>$attrs]);
  $GLOBALS['mode']='inert';
  $editor=bioco_price_native_wrapper($html,['name'=>'divi/text','attrs'=>$attrs]);
  $results[]=compact('id','marker','public','editor');
}
echo json_encode($results);
''')
    assert [row['marker'] for row in out] == ['', 'BIOCO_PRICE_RECOMMENDED_OK', '']
    assert [row['public'] for row in out] == ['', '<div>Empfohlen</div>', '']
    assert all(row['editor'].endswith('Empfohlen</div>') for row in out)
