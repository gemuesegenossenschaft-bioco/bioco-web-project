"""Timeline composition through PHP; local Divi-style layout in Chromium.

Keep/Replace/Remove map: tests/README.md.

The browser adapter covers the native attributes used by this section. It is
not a WordPress/Visual Builder installation; unpublished-copy parity is separate.
"""
import base64
import copy
import hashlib
import html
import json
import re
import subprocess
from pathlib import Path

import pytest
from playwright.sync_api import sync_playwright

from test_wordpress_divi_static_layouts import _compose, _class
from test_wordpress_seed_warning_fixes import _wir_plan

ROOT = Path(__file__).parents[1]
CORE = ROOT / 'wordpress/web/app/mu-plugins/bioco-core'
THEME = ROOT / 'wordpress/web/app/themes/bioco-divi/style.css'


def timeline():
    return next(item for item in _wir_plan() if item['block'] == 'timeline')


def walk(block):
    yield block
    for child in block['innerBlocks']:
        yield from walk(child)


def value(block, group, breakpoint='desktop'):
    return block['attrs']['module']['decoration'][group][breakpoint]['value']


def preset(title):
    return 'bioco-' + hashlib.sha256(('module' + title).encode()).hexdigest()[:16]


def test_timeline_uses_native_responsive_controls_and_seeded_presets():
    tree = _compose(timeline())
    assert _class(tree) == 'bioco-timeline-section'
    assert tree['attrs']['modulePreset'] == [preset('BIOCO Standard Section')]
    rows = tree['innerBlocks']
    assert len(rows) == 7
    for row in rows:
        assert row['attrs']['modulePreset'] == [preset('BIOCO Content Row')]
        assert value(row, 'sizing')['maxWidth'] == '1040px'
        assert value(row, 'sizing')['width'] == '100%'
    for row in rows[1:]:
        assert value(row, 'layout')['display'] == 'grid'
        assert value(row, 'layout')['gridTemplateColumns'] == '120px minmax(0, 1fr)'
        for breakpoint in ('tablet', 'phone'):
            assert value(row, 'layout', breakpoint)['gridTemplateColumns'] == 'minmax(0, 1fr)'
            assert value(row, 'layout', breakpoint)['rowGap'] == '12px'
        assert value(row, 'spacing')['margin']['bottom'] == '28px'
        badge_col, content_col = row['innerBlocks']
        assert value(badge_col, 'layout')['alignItems'] == 'flex-end'
        assert value(badge_col, 'layout', 'phone')['alignItems'] == 'flex-start'
        assert value(content_col, 'sizing')['minWidth'] == '0px'
        assert value(content_col, 'spacing')['padding']['left'] == '20px'
        assert value(content_col, 'border')['styles']['left']['width'] == '2px'
    # Retired theme classes must not override the saved Divi controls.
    assert not any('bioco-divi-timeline' in _class(b) for b in walk(tree))


def test_timeline_preserves_every_seed_event_body_and_anchor():
    item = timeline()
    tree = _compose(item)
    assert tree['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] == 'timeline'
    for event, row in zip(item['values']['items'], tree['innerBlocks'][1:], strict=True):
        assert row['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] == event['anchor']
        badge, content = row['innerBlocks']
        assert badge['innerBlocks'][0]['attrs']['content']['innerContent']['desktop']['value'] == html.escape(event['year_eyebrow'], quote=True)
        heading, body = content['innerBlocks']
        assert heading['attrs']['title']['innerContent']['desktop']['value'] == html.escape(event['title'], quote=True)
        assert body['attrs']['content']['innerContent']['desktop']['value'] == event['text']


def test_timeline_keeps_badge_only_and_zero_items_and_escapes_plain_labels():
    tree = _compose({'block': 'timeline', 'values': {'items': [
        {'year_eyebrow': '0'}, {}, {'year_eyebrow': '<b>2026</b>', 'title': '<img src=x>', 'text': '<p>Body <a href="/wir/">link</a></p>'}
    ]}})
    assert len(tree['innerBlocks']) == 2
    first, second = tree['innerBlocks']
    assert first['innerBlocks'][0]['innerBlocks'][0]['attrs']['content']['innerContent']['desktop']['value'] == '0'
    assert second['innerBlocks'][0]['innerBlocks'][0]['attrs']['content']['innerContent']['desktop']['value'] == '&lt;b&gt;2026&lt;/b&gt;'
    assert second['innerBlocks'][1]['innerBlocks'][0]['attrs']['title']['innerContent']['desktop']['value'] == '&lt;img src=x&gt;'


def legacy_render(values):
    payload = base64.b64encode(json.dumps(values).encode()).decode()
    php = f'''
    define('ABSPATH', __DIR__);
    $fields = json_decode(base64_decode('{payload}'), true);
    function get_field($name) {{ global $fields; return $fields[$name] ?? null; }}
    function esc_attr($v) {{ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }}
    function esc_html($v) {{ return esc_attr($v); }}
    function bioco_text_has_heading_html($v) {{ return preg_match('/<h[1-6]\\b/i', (string)$v); }}
    function bioco_kses_rich_text($v) {{ return strip_tags((string)$v, '<p><a><strong><em><h2><h3><br>'); }}
    $is_preview = false; $block = [];
    require 'wordpress/web/app/mu-plugins/bioco-core/blocks/timeline/render.php';
    '''
    return subprocess.run(['php', '-r', php], cwd=ROOT, text=True, capture_output=True, check=True).stdout


def test_legacy_acf_text_field_and_render_keep_plain_text_contract():
    fields = json.loads((CORE / 'acf-json/group_bioco_block_timeline.json').read_text())['fields']
    items = next(field for field in fields if field['name'] == 'items')
    text_field = next(field for field in items['sub_fields'] if field['name'] == 'text')
    assert text_field['type'] == 'text'
    body = '<p>Body <a href="/wir/">link</a></p>'
    rendered = legacy_render({'items': [{'year_eyebrow': '2013', 'title': 'Gründung', 'text': body}]})
    assert '>2013</span>' in rendered
    assert '<h3>Gründung</h3>' in rendered
    assert '<p>' + html.escape(body, quote=True) + '</p>' in rendered
    assert '<a href="/wir/">' not in rendered


# Small adapter for the external Divi rendering boundary. Actual PHP composition
# is used above; these names match Divi 5.11's decoration schema and breakpoints.
def declarations(decoration, breakpoint):
    result = []
    for group, responsive in decoration.items():
        v = responsive.get(breakpoint, {}).get('value', {})
        if group == 'layout':
            names = {'display': 'display', 'gridTemplateColumns': 'grid-template-columns', 'columnGap': 'column-gap', 'rowGap': 'row-gap', 'alignItems': 'align-items', 'flexDirection': 'flex-direction'}
            result += [f'{css}:{v[key]}' for key, css in names.items() if key in v]
        elif group == 'sizing':
            names = {'width': 'width', 'maxWidth': 'max-width', 'minWidth': 'min-width'}
            result += [f'{css}:{v[key]}' for key, css in names.items() if key in v]
            if v.get('alignment') == 'center':
                result += ['margin-left:auto', 'margin-right:auto']
        elif group == 'spacing':
            for kind in ('margin', 'padding'):
                result += [f'{kind}-{side}:{size}' for side, size in v.get(kind, {}).items()]
        elif group == 'border':
            for side, border in v.get('styles', {}).items():
                prop = 'border' if side == 'all' else f'border-{side}'
                result.append(f'{prop}:{border["width"]} {border["style"]} {border["color"]}')
        elif group == 'background' and 'color' in v:
            result.append('background-color:' + v['color'])
    return ';'.join(result)


def render_native(tree):
    rules = []
    def render(block):
        index = len(rules)
        attrs = block['attrs']
        module = attrs.get('module', {})
        props = module.get('advanced', {}).get('htmlAttributes', {}).get('desktop', {}).get('value', {})
        selector = f'.fixture-{index}'
        decoration = module.get('decoration', {})
        rule = selector + '{' + declarations(decoration, 'desktop') + '}'
        for breakpoint, width in [('tablet', 980), ('phone', 767)]:
            rule += f'@media(max-width:{width}px){{{selector}{{{declarations(decoration, breakpoint)}}}}}'
        rules.append(rule)
        name = block['blockName'].split('/')[1]
        body = ''.join(render(child) for child in block['innerBlocks'])
        if name == 'text':
            body = '<div class="et_pb_text_inner">' + attrs['content']['innerContent']['desktop']['value'] + '</div>'
        elif name == 'heading':
            level = attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel']
            body = f'<{level}>' + attrs['title']['innerContent']['desktop']['value'] + f'</{level}>'
        return f'<div class="fixture-{index} et_pb_{name} {props.get("class", "")}" id="{html.escape(props.get("id", ""))}">{body}</div>'
    markup = render(tree)
    return '<style>' + ''.join(rules) + '</style>' + markup


@pytest.fixture
def browser_page():
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        page = browser.new_page()
        page.route('**/*', lambda route: route.abort())
        yield page
        browser.close()


def install(page, tree, width):
    page.set_viewport_size({'width': width, 'height': 900})
    # Divi base box model and row/column defaults; presets are covered via the
    # checked-in export and PHP composition tests, not emulated by this adapter.
    page.set_content('<style>*{box-sizing:border-box}body{margin:0;font-family:sans-serif}.et_pb_column{display:flex;flex-direction:column}.et_pb_row{margin:auto}.et_pb_text{max-width:100%}</style>' + render_native(tree))
    page.add_style_tag(path=str(THEME))
    page.add_style_tag(path=str(CORE / 'assets/bioco-blocks.css'))


@pytest.mark.parametrize('width', [390, 1440])
def test_timeline_layout_no_overflow_and_editor_changes_work(browser_page, width):
    page = browser_page
    tree = _compose(timeline())
    # Simulate saved Visual Builder edits: duplicate/add, reorder whole event
    # rows, then change responsive spacing/alignment without rerunning importer.
    added = copy.deepcopy(tree['innerBlocks'][-1])
    added['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = 'added'
    added['innerBlocks'][0]['innerBlocks'][0]['attrs']['content']['innerContent']['desktop']['value'] = '2026-' * 40
    added['innerBlocks'][1]['innerBlocks'][0]['attrs']['title']['innerContent']['desktop']['value'] = 'Genossenschaftsgeschichte' * 10
    added['innerBlocks'][1]['innerBlocks'][-1]['attrs']['content']['innerContent']['desktop']['value'] = '<p>Neuer Eintrag <a href="/mitmachen/">Mitmachen</a></p>'
    tree['innerBlocks'].insert(1, added)
    tree['innerBlocks'][2:4] = reversed(tree['innerBlocks'][2:4])
    added['attrs']['module']['decoration']['layout']['phone']['value']['rowGap'] = '18px'
    added['innerBlocks'][0]['attrs']['module']['decoration']['layout']['phone']['value']['alignItems'] = 'center'
    install(page, tree, width)
    assert page.evaluate('document.documentElement.scrollWidth') == width
    assert page.locator('.bioco-timeline-item-row').count() == 7
    assert page.locator('.bioco-timeline-item-row').first.get_attribute('id') == 'added'
    for event in timeline()['values']['items']:
        assert page.get_by_role('heading', name=event['title'], exact=True).count() >= 1
        assert page.locator('#' + event['anchor']).inner_text().endswith(html.unescape(re.sub('<[^>]+>', '', event['text'])))
    badge, content = page.locator('#timeline_2013 > .et_pb_column').all()
    b, c = badge.bounding_box(), content.bounding_box()
    if width == 390:
        assert c['y'] > b['y'] + b['height']
        assert page.locator('#added').evaluate('(e)=>getComputedStyle(e).rowGap') == '18px'
        assert page.locator('#added > .et_pb_column').first.evaluate('(e)=>getComputedStyle(e).alignItems') == 'center'
    else:
        assert abs(b['y'] - c['y']) <= 1
        assert c['x'] > b['x']
        assert page.locator('#timeline_2013').bounding_box()['width'] == 1040
    page.keyboard.press('Tab')
    assert page.get_by_role('link', name='Mitmachen', exact=True).evaluate('(e)=>e===document.activeElement')
    assert page.get_by_role('link', name='Mitmachen', exact=True).evaluate('(e)=>getComputedStyle(e).outlineStyle') != 'none'


def install_saved_divi_timeline(page, width, before_fix=False):
    # Saved old-class rows are independent of the current native composer.
    rows = []
    for event in timeline()['values']['items']:
        rows.append(f'''
        <div class="et_pb_row bioco-divi-row bioco-divi-timeline-item-row" id="{event['anchor']}">
          <div class="et_pb_column et_pb_column_1_4 bioco-divi-timeline-badge-col">
            <div class="et_pb_text bioco-divi-timeline-badge"><div class="et_pb_text_inner">{html.escape(event['year_eyebrow'])}</div></div>
          </div>
          <div class="et_pb_column et_pb_column_3_4 et-last-child bioco-divi-timeline-item-content">
            <div class="et_pb_heading bioco-divi-timeline-item-title"><h3>{html.escape(event['title'])}</h3></div>
            <div class="et_pb_text bioco-divi-timeline-item-text"><div class="et_pb_text_inner">{event['text']}</div></div>
          </div>
        </div>''')
    divi_defaults = '''
        * {box-sizing:border-box} body {margin:0;font-family:sans-serif}
        .et_pb_row {margin:auto;padding:20px 0}
        .et_pb_column {float:left}
        .et_pb_gutters3 .et_pb_row .et_pb_column {margin-right:5.5%}
        .et_pb_gutters3 .et_pb_row .et_pb_column_1_4 {width:20.875%}
        .et_pb_gutters3 .et_pb_row .et_pb_column_3_4 {width:73.625%}
        .et_pb_gutters3 .et_pb_row .et_pb_column.et-last-child {margin-right:0}
        @media(max-width:980px) {
            .et_pb_gutters3 .et_pb_row .et_pb_column {width:100%;margin-bottom:30px}
        }
    '''
    css = THEME.read_text()
    if before_fix:
        # Reconstruct the old stylesheet: 120px 1fr, no grid-column reset,
        # no phone stacking. Keep the existing generic Divi-row reset.
        css = css.replace('grid-template-columns: 120px minmax(0, 1fr);', 'grid-template-columns: 120px 1fr;')
        for selector in (
            '.bioco-divi-timeline .bioco-divi-timeline-item-row > .et_pb_column',
            '.bioco-divi-timeline-item-row > .bioco-divi-timeline-badge-col',
        ):
            css, count = re.subn(re.escape(selector) + r'\s*\{[^}]*\}', '', css)
            assert count == 1
        css, count = re.subn(
            r'\.bioco-divi-timeline-item-row\s*\{\s*grid-template-columns: minmax\(0, 1fr\);[^}]*\}', '', css,
        )
        assert count == 1
    page.set_viewport_size({'width': width, 'height': 900})
    page.set_content('<style>' + divi_defaults + '</style><div class="et_pb_gutters3"><section class="et_pb_section bioco-divi-section bioco-divi-timeline bioco-divi-width-lg bioco-divi-align-left">' + ''.join(rows) + '</section></div>')
    page.add_style_tag(content=css)


def test_saved_divi_timeline_old_rule_overflows_at_390(browser_page):
    install_saved_divi_timeline(browser_page, 390, before_fix=True)
    old_width = browser_page.evaluate('document.documentElement.scrollWidth')
    assert old_width > 390, f'Old 120px 1fr rule must reproduce overflow: {old_width}px'
    install_saved_divi_timeline(browser_page, 390)
    assert browser_page.evaluate('document.documentElement.scrollWidth') <= 390


@pytest.mark.parametrize('width', [390, 767, 768, 1440])
def test_saved_divi_timeline_compatibility_layout(browser_page, width):
    page = browser_page
    install_saved_divi_timeline(page, width)
    assert page.evaluate('document.documentElement.scrollWidth') <= width
    assert page.locator('.bioco-divi-timeline-item-row').count() == 6
    for row in page.locator('.bioco-divi-timeline-item-row').all():
        badge, content = row.locator(':scope > .et_pb_column').all()
        b, c = badge.bounding_box(), content.bounding_box()
        for column in (badge, content):
            assert column.evaluate('(e)=>getComputedStyle(e).minWidth') == '0px'
            assert column.evaluate('(e)=>getComputedStyle(e).margin') == '0px'
        if width <= 767:
            assert c['y'] >= b['y'] + b['height'] + 12
            assert c['x'] == b['x']
            assert badge.evaluate('(e)=>getComputedStyle(e).textAlign') == 'left'
        else:
            assert abs(b['y'] - c['y']) <= 1
            assert b['width'] == 120
            assert c['x'] == b['x'] + 144
            assert badge.evaluate('(e)=>getComputedStyle(e).textAlign') == 'right'
        if width == 1440:
            assert row.bounding_box()['width'] == 1040
            assert c['width'] == 896
            assert content.evaluate('(e)=>getComputedStyle(e).paddingLeft') == '20px'
            assert content.evaluate('(e)=>getComputedStyle(e,"::before").width') == '2px'


@pytest.mark.parametrize('width', [390, 1440])
def test_legacy_timeline_normal_flow_has_no_overflow(browser_page, width):
    page = browser_page
    page.set_viewport_size({'width': width, 'height': 900})
    page.set_content('<style>*{box-sizing:border-box}body{margin:0}</style>' + legacy_render(timeline()['values']))
    page.add_style_tag(path=str(CORE / 'assets/bioco-blocks.css'))
    assert page.evaluate('document.documentElement.scrollWidth') == width
    assert page.locator('.cms-timeline-item').count() == 6


def test_timeline_presets_exist_in_the_real_portability_export():
    exports = json.loads((ROOT / 'wordpress/design-system/exports/presets.json').read_text())['presets']
    for block in walk(_compose(timeline())):
        for preset_id in block['attrs'].get('modulePreset', []):
            assert preset_id in exports['module'][block['blockName']]['items']
        for ref in block['attrs'].get('groupPreset', {}).values():
            for preset_id in ref['presetId']:
                assert preset_id in exports['group'][ref['groupName']]['items']


@pytest.mark.parametrize('width', [390, 1440])
def test_adjacent_editorial_section_and_keyboard_focus(browser_page, width):
    tree = _compose({'block': 'rich-text', 'values': {
        'title': 'Solidarische Landwirtschaft',
        'text': '<p>Wir teilen Arbeit und Ernte. <a href="/solawi/">Solawi</a></p>',
    }})
    install(browser_page, tree, width)
    assert browser_page.evaluate('document.documentElement.scrollWidth') == width
    assert browser_page.get_by_role('heading', name='Solidarische Landwirtschaft').is_visible()
    browser_page.keyboard.press('Tab')
    link = browser_page.get_by_role('link', name='Solawi', exact=True)
    assert link.evaluate('(e)=>e===document.activeElement')
    assert link.evaluate('(e)=>getComputedStyle(e).outlineStyle') != 'none'
