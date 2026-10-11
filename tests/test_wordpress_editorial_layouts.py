"""Saved editorial migration behavior. See the Keep/Replace/Remove map in tests/README.md."""
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_divi_static_layouts import _compose
from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
HARNESS = r'''
define('ABSPATH', '/');
require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
function parse_blocks($s) { return (new WP_Block_Parser())->parse($s); }
WP_SERIALIZER
try {
    $original = serialize_blocks(json_decode(stream_get_contents(STDIN), true));
    [$content, $count] = bioco_import_editorial_content($original);
    [$again, $second] = bioco_import_editorial_content($content);
    echo json_encode(['blocks'=>parse_blocks($content), 'count'=>$count, 'second'=>$second, 'stable'=>$content===$again]);
} catch (Throwable $error) { echo json_encode(['error'=>$error->getMessage()]); }
'''


def migrate(blocks):
    result = subprocess.run(['php', '-r', HARNESS.replace('WP_SERIALIZER', WP_SERIALIZER_STUB)], cwd=ROOT, input=json.dumps(blocks),
                            text=True, capture_output=True, check=True)
    assert not result.stderr
    return json.loads(result.stdout)


def walk(blocks):
    for block in blocks:
        yield block
        yield from walk(block.get('innerBlocks', []))


def legacy(html, css):
    tree = _compose({'block': 'rich-text', 'values': {'text': html}})
    leaf = tree['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    leaf['attrs']['module']['advanced']['htmlAttributes']['desktop']['value'].update({'class': css, 'id': 'saved-anchor'})
    return tree


def test_accordion_preserves_rich_body_document_anchor_and_custom_design():
    body = '<p>0 und <strong>Demeter</strong><a href="/documents/statuten.pdf">PDF</a></p>'
    tree = legacy('<details><summary>A &amp; B</summary><div>' + body + '</div></details>', 'bioco-divi-accordion-item')
    leaf = tree['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    leaf['attrs']['module']['decoration'] = {'spacing': {'phone': {'value': {'padding': {'left': '9px'}}}}}
    result = migrate([tree])
    toggle = next(b for b in walk(result['blocks']) if b['blockName'] == 'divi/toggle')
    assert toggle['attrs']['title']['innerContent']['desktop']['value'] == 'A &amp; B'
    assert toggle['attrs']['content']['innerContent']['desktop']['value'] == body
    assert toggle['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] == 'saved-anchor'
    assert toggle['attrs']['module']['decoration']['spacing']['phone']['value']['padding']['left'] == '9px'
    assert result['count'] == 1 and result['second'] == 0 and result['stable']


def test_portal_uses_only_confirmed_blank_destinations_and_preserves_other_links():
    html = '<div class="portal-gateway">'
    for title in ['Mitglieder-Portal', 'Einsatzplanung', 'Other']:
        html += '<div class="portal-tile"><div class="portal-icon">🦆</div><h3>' + title + '</h3><p>Extern</p></div>'
    html += '<a class="portal-tile" href="/kontakt"><h3>Kontakt</h3></a></div>'
    tree = legacy(html, 'bioco-divi-link-tiles-body')
    children = tree['innerBlocks'][0]['innerBlocks'][0]['innerBlocks']
    children.insert(0, _compose({'block':'rich-text', 'values':{'text':'<p>Before</p>'}})['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0])
    children.append(_compose({'block':'rich-text', 'values':{'text':'<p>After</p>'}})['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0])
    tree['innerBlocks'][0]['innerBlocks'][0]['innerContent'] = [None] * len(children)
    result = migrate([tree])
    blocks = list(walk(result['blocks']))
    cards = [b for b in blocks if b['blockName'] == 'divi/blurb']
    assert [b['attrs']['title']['innerContent']['desktop']['value']['url'] for b in cards] == ['https://intranet.bioco.ch', 'https://intranet.bioco.ch', '', '/kontakt']
    assert [b['attrs']['title']['innerContent']['desktop']['value']['text'] for b in cards] == ['Mitglieder-Portal', 'Einsatzplanung', 'Other', 'Kontakt']
    texts = [b['attrs']['content']['innerContent']['desktop']['value'] for b in blocks if b['blockName'] == 'divi/text']
    assert texts == ['<p>Before</p>', '🦆', '🦆', '🦆', '<p>After</p>']
    assert result['second'] == 0 and result['stable']


def test_topics_preserve_column_major_order_bodies_and_contained_mobile_layout():
    html = ''.join('<h3>' + title + '</h3><p><a href="/doc-' + title + '.pdf">Text</a></p>' for title in ['A', 'B', 'C', 'D', 'E'])
    result = migrate([legacy(html, 'bioco-divi-text-columns-body bioco-divi-columns-2 bioco-divi-gap-lg')])
    row = result['blocks'][0]['innerBlocks'][0]
    assert [len(c['innerBlocks']) for c in row['innerBlocks']] == [3, 2]
    cards = [b for b in walk(result['blocks']) if b['blockName'] == 'divi/blurb']
    assert [c['attrs']['title']['innerContent']['desktop']['value']['text'] for c in cards] == ['A', 'B', 'C', 'D', 'E']
    for title, card in zip(['A', 'B', 'C', 'D', 'E'], cards):
        assert card['attrs']['content']['innerContent']['desktop']['value'] == '<p><a href="/doc-' + title + '.pdf">Text</a></p>'
    assert row['attrs']['module']['decoration']['layout']['phone']['value']['gridTemplateColumns'] == 'minmax(0, 1fr)'
    assert result['second'] == 0 and result['stable']


@pytest.mark.parametrize('items,columns', [(1, 4), (2, 4), (5, 4), (4, 2)])
def test_topic_grid_does_not_allocate_empty_columns(items, columns):
    tree = _compose({'block':'text-columns', 'values':{
        'text': ''.join('<h3>Topic ' + str(i) + '</h3><p>Body</p>' for i in range(items)),
        'columns': str(columns),
    }})
    row = tree['innerBlocks'][0]
    actual = len(row['innerBlocks'])
    assert 1 <= actual <= columns
    assert row['attrs']['module']['decoration']['layout']['desktop']['value']['gridTemplateColumns'] == 'repeat(' + str(actual) + ', minmax(0, 1fr))'
    assert sum(len(c['innerBlocks']) for c in row['innerBlocks']) == items


@pytest.mark.parametrize('html,css', [
    ('<p>Before</p><details><summary>A</summary></details>', 'bioco-divi-accordion-item'),
    ('<details><summary><strong>A</strong></summary></details>', 'bioco-divi-accordion-item'),
    ('<div class="portal-gateway"><div class="portal-tile"><h3>A</h3><p><strong>Extra</strong></p></div></div>', 'bioco-divi-link-tiles-body'),
    ('<div class="portal-gateway"><a class="portal-tile" href="javascript:alert(1)"><h3>A</h3></a></div>', 'bioco-divi-link-tiles-body'),
])
def test_mixed_styled_or_unsafe_saved_markup_is_refused(html, css):
    assert 'error' in migrate([legacy(html, css)])


def test_responsive_content_and_shared_columns_are_refused():
    tree = legacy('<details><summary>A</summary></details>', 'bioco-divi-accordion-item')
    tree['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['content']['innerContent']['phone'] = {'value':'<p>Phone-only text</p>'}
    assert 'responsive' in migrate([tree])['error']
    tree = legacy('<div class="portal-gateway"><div class="portal-tile"><h3>A</h3></div></div>', 'bioco-divi-link-tiles-body')
    tree['innerBlocks'][0]['innerBlocks'].append(tree['innerBlocks'][0]['innerBlocks'][0].copy())
    tree['innerBlocks'][0]['innerContent'] = [None] * 2
    assert 'error' in migrate([tree])


def test_free_form_text_columns_cannot_be_reported_as_a_completed_migration():
    tree = legacy('<p>Intro</p><h3>Styled topic</h3><p>Body</p>', 'bioco-divi-text-columns-body')
    result = migrate([tree])
    assert 'unrecognized editorial topic markup' in result['error']


def normalize_saved_attributes(block):
    attrs = block['attrs']
    html = attrs['module']['advanced'].get('htmlAttributes', {}).get('desktop', {}).get('value', {})
    attrs['module']['decoration'] = {
        'layout': {'desktop': {'value': {'display': 'block'}}},
        'attributes': {'desktop': {'value': {'attributes': [
            {'id': name + '-uuid', 'name': name, 'value': value, 'adminLabel': name}
            for name, value in html.items()
        ]}}},
    }
    attrs['module']['advanced']['htmlAttributes'] = {'desktop': {'value': {'class':'', 'id':''}}}
    attrs['builderVersion'] = '5.0.0-public-beta.1'
    for child in block.get('innerBlocks', []):
        normalize_saved_attributes(child)


def test_real_divi_attribute_storage_converts_and_preserves_each_anchor_once():
    tree = legacy('<div class="portal-gateway"><div class="portal-tile"><h3>Mitglieder-Portal</h3></div></div>', 'bioco-divi-link-tiles-body')
    row = tree['innerBlocks'][0]
    row['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = 'row-anchor'
    row['innerBlocks'][0]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = 'column-anchor'
    normalize_saved_attributes(tree)
    result = migrate([tree])
    assert result['count'] == 1 and result['second'] == 0 and result['stable']
    ids = [b['attrs'].get('module', {}).get('advanced', {}).get('htmlAttributes', {}).get('desktop', {}).get('value', {}).get('id') for b in walk(result['blocks'])]
    assert ids.count('row-anchor') == ids.count('column-anchor') == ids.count('saved-anchor') == 1
    toggle = legacy('<details><summary>Native</summary><div><p>Body</p></div></details>', 'bioco-divi-accordion-item')
    normalize_saved_attributes(toggle)
    result = migrate([toggle])
    assert result['count'] == 1 and result['second'] == 0
    leaf = next(b for b in walk(result['blocks']) if b['blockName'] == 'divi/toggle')
    stored = leaf['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes']
    assert next(a['value'] for a in stored if a['name'] == 'class') == 'bioco-editorial-toggle'
    assert next(a['value'] for a in stored if a['name'] == 'id') == 'saved-anchor'


def test_custom_split_design_is_refused_and_sibling_ids_do_not_duplicate():
    tree = legacy('<h3>A</h3><p>Body</p>', 'bioco-divi-text-columns-body')
    row = tree['innerBlocks'][0]
    row['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = 'row-anchor'
    row['innerBlocks'][0]['innerBlocks'].insert(0, _compose({'block':'rich-text', 'values':{'text':'<p>Before</p>'}})['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0])
    row['innerBlocks'][0]['innerBlocks'].append(_compose({'block':'rich-text', 'values':{'text':'<p>After</p>'}})['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0])
    row['innerBlocks'][0]['innerContent'] = [None] * len(row['innerBlocks'][0]['innerBlocks'])
    normalize_saved_attributes(tree)
    result = migrate([tree])
    stored_ids = [a['value'] for b in walk(result['blocks']) for a in b['attrs'].get('module', {}).get('decoration', {}).get('attributes', {}).get('desktop', {}).get('value', {}).get('attributes', []) if a['name'] == 'id']
    assert stored_ids.count('row-anchor') == 1
    row['attrs']['module']['decoration']['background'] = {'desktop': {'value': {'color':'#FF0000'}}}
    assert 'custom saved design' in migrate([tree])['error']
