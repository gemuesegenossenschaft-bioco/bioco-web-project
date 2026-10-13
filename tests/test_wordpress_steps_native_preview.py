"""Strict three-item steps split, preserving text, links and imported IDs."""
import copy
import json
import subprocess
import pytest

from test_wordpress_presentation_adoption import ROOT, PHP, WP_SERIALIZER_STUB, block, classes, tokens


def run(blocks):
    script = PHP.replace('WP_SERIALIZER', WP_SERIALIZER_STUB).replace(
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/presentation-adoption.php';",
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/steps-native-preview.php';")
    script = script.replace(
        "$result['blocks'] = bioco_import_presentation_tree($input['blocks'], $result['report']);",
        "[$result['blocks'], $result['report']] = bioco_steps_native_preview($input['blocks']);")
    process = subprocess.run(['php', '-r', script], input=json.dumps({'blocks': blocks}),
                             text=True, capture_output=True, cwd=ROOT, check=True)
    assert process.stderr == ''
    return json.loads(process.stdout)


def item(number, title, paragraph):
    return f'<div class="step-item"><div class="step-number">{number}</div><div><h3>{title}</h3><p>{paragraph}</p></div></div>'


def fixture():
    source = block('divi/text', classes('bioco-divi-steps-body') | {'content': {'innerContent': {'desktop': {'value':
        '<div class="next-steps">' + item(1, 'Ä E-Mail &amp; Bestätigung', 'Keep &quot;quotes&quot; and <a href="https://example.org/confirm?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">first link</a>.')
        + item(2, 'Rechnung', 'Second <a href="/invoice/#id">link</a>.') + item(3, 'Start', 'Third paragraph.') + '</div>'}}}})
    source['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'].append({
        'id': 'authored-anchor-control-id', 'name': 'id', 'value': 'steps-original-anchor'})
    source['id'] = 'original-parser-module-id'
    column = block('divi/column', classes('bioco-divi-content'), [source])
    row = block('divi/row', classes('bioco-divi-row'), [column])
    section = block('divi/section', classes('bioco-divi-section', 'bioco-divi-steps'), [row])
    section['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'].append({
        'id': 'section-anchor-control-id', 'name': 'id', 'value': 'naechste-schritte'})
    for native in [section, row, column, source]:
        native['attrs']['module']['decoration']['layout'] = {'desktop': {'value': {'display': 'block'}}}
        native['attrs']['builderVersion'] = '5.0.0-public-beta.1'
    return section


def cards(section):
    return section['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks']


def test_split_exposes_native_per_item_controls_without_changing_text_or_link_order():
    before = fixture()
    result = run([before])
    assert result['report'] == [{'path': [0], 'action': 'steps'}]
    after = result['blocks'][0]
    assert tokens(after) == ['bioco-native-steps-section']
    actual = cards(after)
    assert len(actual) == 3
    expected = [('1', 'Ä E-Mail &amp; Bestätigung', '<p>Keep &quot;quotes&quot; and <a href="https://example.org/confirm?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">first link</a>.</p>'),
                ('2', 'Rechnung', '<p>Second <a href="/invoice/#id">link</a>.</p>'),
                ('3', 'Start', '<p>Third paragraph.</p>')]
    for card, (number, title, paragraph) in zip(actual, expected):
        assert card['blockName'] == 'divi/row'
        assert card['attrs']['module']['decoration']['layout']['desktop']['value']['gridTemplateColumns'] == '32px minmax(0, 1fr)'
        number_module = card['innerBlocks'][0]['innerBlocks'][0]
        heading, body = card['innerBlocks'][1]['innerBlocks']
        assert number_module['blockName'] == 'divi/text'
        assert number_module['attrs']['content']['innerContent']['desktop']['value'] == number
        assert heading['blockName'] == 'divi/heading'
        assert heading['attrs']['title']['innerContent']['desktop']['value'] == title
        assert body['blockName'] == 'divi/text'
        assert body['attrs']['content']['innerContent']['desktop']['value'] == paragraph
        assert number_module['attrs']['module']['decoration']['sizing']['desktop']['value'] == {'width': '32px', 'maxWidth': '32px', 'height': '32px'}
    assert run([after]) == {'blocks': [after], 'report': []}


def test_original_ids_and_wrapper_positions_remain_with_the_native_list():
    before = fixture()
    after = run([before])['blocks'][0]
    source = before['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    converted = after['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    assert converted['blockName'] == 'divi/row'
    assert converted['id'] == source['id']
    for old, new in [(before, after), (source, converted)]:
        old_attrs = old['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes']
        new_attrs = new['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes']
        assert [a for a in old_attrs if a['name'] != 'class'] == [a for a in new_attrs if a['name'] != 'class']
        assert [a['id'] for a in old_attrs] == [a['id'] for a in new_attrs]
    assert before['innerBlocks'][0]['innerContent'] == after['innerBlocks'][0]['innerContent']
    assert before['innerBlocks'][0]['innerBlocks'][0]['innerContent'] == after['innerBlocks'][0]['innerBlocks'][0]['innerContent']


@pytest.mark.parametrize('change', ['wrong-count', 'wrong-number', 'extra-markup', 'styled-item',
                                  'unsafe-link', 'other-wrapper', 'nested-heading', 'extra-module',
                                  'extra-column', 'authored-css', 'native-design', 'responsive-content',
                                  'state-class', 'nested-preset'])
def test_unmeasured_or_authored_variant_refuses_the_whole_steps_section(change):
    before = fixture()
    source = before['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    values = source['attrs']['content']['innerContent']
    html = values['desktop']['value']
    if change == 'wrong-count': html = html.replace(item(3, 'Start', 'Third paragraph.'), '')
    elif change == 'wrong-number': html = html.replace('step-number">2', 'step-number">9')
    elif change == 'extra-markup': html = html.replace('Third paragraph.', '<img src="/unmeasured.jpg">')
    elif change == 'styled-item': html = html.replace('class="step-item"', 'class="step-item" style="color:red"', 1)
    elif change == 'unsafe-link': html = html.replace('/invoice/#id', 'javascript:alert(1)')
    elif change == 'other-wrapper': html = html.replace('class="next-steps"', 'class="other-steps"')
    elif change == 'nested-heading': html = html.replace('<h3>Rechnung</h3>', '<h3><em>Rechnung</em></h3>')
    elif change == 'extra-module': before['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'].append(block('divi/text', {'authored': 'keep'}))
    elif change == 'extra-column': before['innerBlocks'][0]['innerBlocks'].append(block('divi/column', {'authored': 'keep'}))
    elif change == 'authored-css': source['attrs']['css'] = {'desktop': {'value': {'mainElement': 'color:red;'}}}
    elif change == 'native-design': source['attrs']['module']['decoration']['spacing'] = {'desktop': {'value': {'padding': {'top': '7px'}}}}
    elif change == 'responsive-content': values['phone'] = {'value': 'Authored other phone content'}
    elif change == 'state-class': source['attrs']['module']['advanced']['htmlAttributes']['desktop']['hover'] = {'class': 'bioco-divi-steps-body'}
    else: source['attrs']['module']['deep'] = {'presetId': 'shared-17'}
    values['desktop']['value'] = html
    result = run([before])
    assert result['blocks'] == [before]
    assert result['report'][0]['action'] == 'preserve'


def test_existing_native_steps_and_unrelated_functional_modules_are_unchanged():
    section = fixture()
    native = run([section])['blocks'][0]
    functional = block('bioco-divi/doi-confirm', {'tokenSource': 'preserve'})
    assert run([native, functional]) == {'blocks': [native, functional], 'report': []}
