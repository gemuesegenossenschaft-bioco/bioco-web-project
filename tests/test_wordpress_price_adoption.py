"""Saved price conversion, parser round trips and one-page conditional SQL."""
import copy
import hashlib
import json
import re
import sqlite3
import subprocess
from pathlib import Path

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
try {
    if (($state['mode'] ?? '') === 'sql') {
        echo json_encode(['sql'=>bioco_price_adoption_sql($state['plan'], $state['expected'], $state['restore'] ?? false)]);
    } else {
        $source=$state['source'];
        if (isset($state['blocks'])) $source['post_content']=serialize_blocks($state['blocks']);
        $plan=bioco_price_adoption_plan($source, $state['kind'], $state['snapshot_site'] ?? $state['site'], $state['body_hash'] ?? hash('sha256', $source['post_content']));
        echo json_encode(['plan'=>$plan, 'before_blocks'=>parse_blocks($source['post_content']),
            'blocks'=>parse_blocks($plan['after_content']),
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
    assert 'loop_bioco_price_name' in json.dumps(rows[0])
    assert 'loop_bioco_price_price_table' in json.dumps(rows[0])
    assert 'bioco-price-none-card' in native_class(rows[1]).split()
    assert '#bioco-price-kein' in json.dumps(rows[1])
    assert [len(row['innerBlocks']) for row in rows[2:6]] == [5, 5, 5, 5]
    for row in rows[2:]:
        for device in ('desktop', 'tablet', 'phone'):
            assert row['attrs']['module']['decoration']['layout'][device]['value']['gridColumn'] == '1 / -1'
    header = [col['innerBlocks'][0]['attrs']['title']['innerContent']['desktop']['value']
              for col in rows[2]['innerBlocks'][:4]]
    copy = PUBLIC['calculator_attrs']
    assert header == [copy[key]['innerContent']['desktop']['value']
        for key in ('article_label', 'quantity_label', 'unit_price_label', 'total_label')]
    for marker in ('bioco-price-value-name', 'bioco-price-value-price', 'bioco-price-value-work',
                   'bioco-price-value-shares', 'bioco-price-value-share-cost', 'bioco-price-value-total',
                   'bioco-price-signup', 'bioco-price-no-selection'):
        assert marker in json.dumps(rows)


def test_native_calculator_grid_controls_keep_invoice_below_choices_in_browser():
    from playwright.sync_api import sync_playwright
    result = run('calculator', blocks=fixture('calculator'))
    section = next(node for node in walk(result['blocks'][0]) if node['blockName'] == 'divi/section'
                   and 'bioco-price-calculator' in native_class(node).split())
    layout = section['attrs']['module']['decoration']['layout']
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        for width in (1280, 390):
            page = browser.new_page(viewport={'width': width, 'height': 700})
            columns = layout['phone' if width < 640 else 'desktop']['value']['gridTemplateColumns']
            page.set_content('<style>html,body{margin:0}.calculator{display:grid;grid-template-columns:' + columns
                + ';gap:16px;width:100%;box-sizing:border-box;overflow-x:auto}.card{min-height:80px}.invoice{grid-column:1/-1;min-width:914px;min-height:80px}</style>'
                + '<div class="calculator"><div class="card">1</div><div class="card">2</div><div class="card">3</div>'
                + '<div class="card">Kein Abo</div><div class="invoice">Rechnung</div></div>')
            positions = page.evaluate('''() => {
              const cards = [...document.querySelectorAll('.card')].map(el => el.getBoundingClientRect());
              const invoice = document.querySelector('.invoice').getBoundingClientRect();
              const box = document.querySelector('.calculator');
              return {cards: cards.map(rect => [Math.round(rect.left), Math.round(rect.top)]),
                invoiceTop: Math.round(invoice.top), pageWidth: document.documentElement.scrollWidth,
                boxWidth: box.clientWidth, contentWidth: box.scrollWidth};
            }''')
            assert positions['invoiceTop'] > max(card[1] for card in positions['cards'])
            assert positions['pageWidth'] == width
            if width == 1280:
                assert len({card[1] for card in positions['cards']}) == 1
            else:
                assert len({card[1] for card in positions['cards']}) == 4
                assert positions['contentWidth'] > positions['boxWidth']
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


@pytest.mark.parametrize('environment', ['staging', 'production'])
@pytest.mark.parametrize('kind,sha', [('table','d2c2b7dbc54c38295e698de0e4e0d5e4a34c8571fef5b3ec5e2855a4c7bd7d3c'),
    ('calculator','b02c4e235128e159438277ac2601e5944cb0d7c64af521fd84fa9f478fec39e2')])
def test_captured_page_when_private_snapshot_available(environment, kind, sha):
    path = Path.home()/f'.cache/bioco-editor-202/{environment}-price-pages.json'
    if not path.exists(): pytest.skip('Private saved page snapshot unavailable')
    records = json.loads(path.read_text())
    record = next(row for row in records if row['ID'] == (6 if kind == 'table' else 10))
    result = run(kind, source=record, body_hash=sha,
                 site='https://bioco.ch/' if environment == 'production' else 'https://staging.bioco.ch/')
    assert 'error' not in result, result
    assert result['roundtrip']
    assert hashlib.sha256(record['post_content'].encode()).hexdigest() == sha


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
        assert root.locator('.bioco-price-signup a').inner_text() == 'Jetzt mitmachen!'
        assert 'abo=neu' in root.locator('.bioco-price-signup a').get_attribute('href')
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
