"""Pure native presentation conversion; no licensed renderer is mocked here."""
import copy
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
HELPER = 'wordpress/web/app/mu-plugins/bioco-import/includes/editorial-card-presentation-preview.php'
PHP = r'''
define('ABSPATH', __DIR__);
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
function wp_json_encode($v,...$args){return json_encode($v,...$args);}
WP_SERIALIZER
require 'HELPER';
$blocks=json_decode(stream_get_contents(STDIN),true);
$before=$blocks;
try{
 [$after,$report]=bioco_editorial_cards_preview($blocks);
 [$repeat,$repeatReport]=bioco_editorial_cards_preview($after);
 $serialized=serialize_blocks($after);
 echo json_encode(['before'=>$before,'after'=>$after,'report'=>$report,'repeat'=>$repeat,
  'repeatReport'=>$repeatReport,'parsed'=>(new WP_Block_Parser())->parse($serialized)]);
}catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]);}
'''.replace('WP_SERIALIZER', WP_SERIALIZER_STUB).replace('HELPER', HELPER)


def run(blocks):
    result = subprocess.run(['php', '-r', PHP], cwd=ROOT, input=json.dumps(blocks),
                            text=True, capture_output=True, check=True)
    assert result.stderr == ''
    return json.loads(result.stdout)


def block(name, classes='', children=None, attrs=None):
    children = children or []
    attrs = copy.deepcopy(attrs or {})
    attrs.setdefault('module', {}).setdefault('decoration', {})['attributes'] = {
        'desktop': {'value': {'attributes': [
            {'id': 'class-editor-id', 'name': 'class', 'value': classes, 'adminLabel': 'CSS Class'},
            {'id': 'anchor-editor-id', 'name': 'id', 'value': 'author-anchor', 'adminLabel': 'CSS ID'},
        ]}}}
    attrs['module']['decoration'].setdefault('layout', {'desktop': {'value': {'display': 'block'}}})
    return {'blockName': name, 'attrs': attrs, 'innerBlocks': children, 'innerHTML': '',
            'innerContent': [None] * len(children) if children else ['\n']}


def fixture(family='cards-grid', desktop=3, phone=1, ratio='4_3', rounded='md', fit='cover'):
    base = 'bioco-divi-' + family
    header = block('divi/row', 'bioco-divi-row ' + base + '-header', [
        block('divi/column', 'bioco-divi-content', [block('divi/text', 'bioco-divi-text', attrs={
            'content': {'innerContent': {'desktop': {'value': '<h2>Saved 100% heading</h2><p>Ä intro</p>'}}}})])])
    columns = []
    for index in range(desktop):
        card = family == 'cards-grid'
        image = block('divi/image', 'bioco-divi-card-image custom-image' if card else base + '-image custom-image', attrs={
            'image': {'innerContent': {'desktop': {'value': {'src': f'https://example.test/image{index}.jpg', 'id': index,
                'alt': 'Saved ä "alt"', 'linkUrl': '/saved-link?foo=0', 'linkTarget': 'on'}}}}})
        modules = [image]
        if card:
            modules += [block('divi/heading', 'bioco-divi-card-title author-title', attrs={
                'title': {'innerContent': {'desktop': {'value': f'Person {index}'}},
                          'decoration': {'font': {'font': {'desktop': {'value': {'headingLevel': 'h3'}}}}}}}),
                block('divi/text', 'bioco-divi-card-text', attrs={'content': {'innerContent': {'desktop': {'value': '<p>Saved <a href="/foo">link</a></p>'}}}}),
                block('divi/button', 'custom-button', attrs={'button': {'innerContent': {'desktop': {'value': {'text': 'Keep', 'linkUrl': '/unchanged'}}}}})]
        columns.append(block('divi/column', base + ('-card ' + base + '-card--soft' if card else '-frame') + ' custom-column', modules))
    grid = block('divi/row', f'bioco-divi-row {base}-row {base}-row--desktop-{desktop}--mobile-{phone} '
                 f'{base}-row--ratio-{ratio} {base}-row--fit-{fit} {base}-row--rounded-{rounded} bioco-divi-gap-lg custom-row', columns)
    action = block('divi/row', 'bioco-divi-row ' + base + '-actions', [
        block('divi/column', 'bioco-divi-content', [block('divi/button', 'keep-button', attrs={'button': {'innerContent': {'desktop': {'value': {'text': 'Saved action', 'linkUrl': '/standorte-depots'}}}}})])])
    section = block('divi/section', 'bioco-divi-section ' + base + ' bioco-divi-width-xl custom-section', [header, grid, action])
    # Opaque inter-child HTML must not be rebuilt or lost.
    section['innerContent'] = ['opaque before', None, '<!-- saved gap -->', None, 'opaque after', None]
    return section


def classes(block):
    return block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][0]['value'].split()


@pytest.mark.parametrize('family,desktop,radius', [('cards-grid', 3, '16px'), ('gallery-strip', 4, '24px')])
def test_preview_preserves_content_order_siblings_ids_and_native_round_trip(family, desktop, radius):
    saved = fixture(family, desktop, rounded='md' if family == 'cards-grid' else 'lg')
    unrelated = block('divi/section', 'bioco-divi-cta', [block('divi/text', 'other', attrs={'saved': 'opaque'})])
    wrapper = block('divi/placeholder', 'wrapper', [unrelated, saved, unrelated])
    result = run([wrapper])
    assert 'error' not in result
    assert result['before'] == [wrapper]
    after = result['after'][0]
    assert after['attrs'] == wrapper['attrs'] and after['innerContent'] == wrapper['innerContent']
    assert after['innerBlocks'][0] == unrelated == after['innerBlocks'][2]
    target = after['innerBlocks'][1]
    assert target['innerContent'] == saved['innerContent']
    assert target['innerHTML'] == saved['innerHTML']
    assert target['innerBlocks'][2]['innerBlocks'] == saved['innerBlocks'][2]['innerBlocks']  # CTA leaves unchanged.
    assert target['innerBlocks'][0]['innerBlocks'] == saved['innerBlocks'][0]['innerBlocks']
    grid = target['innerBlocks'][1]
    native = grid['attrs']['module']['decoration']['layout']
    assert native['desktop']['value']['display'] == 'grid'
    assert native['desktop']['value']['gridColumnCount'] == str(desktop)
    assert native['phone']['value']['gridColumnCount'] == '1'
    assert native['tablet']['value']['gridColumnCount'] == str(desktop)
    assert 'custom-row' in classes(grid) and 'bioco-divi-row' not in classes(grid)
    for index, column in enumerate(grid['innerBlocks']):
        assert column['innerContent'] == saved['innerBlocks'][1]['innerBlocks'][index]['innerContent']
        image = column['innerBlocks'][0]
        assert image['attrs']['image']['innerContent'] == saved['innerBlocks'][1]['innerBlocks'][index]['innerBlocks'][0]['attrs']['image']['innerContent']
        assert image['attrs']['module']['advanced']['sizing']['desktop']['value']['aspectRatio'] == {'width': '4', 'height': '3'}
        assert set(image['attrs']['image']['decoration']['border']['desktop']['value']['radius'].values()) == {radius}
        assert image['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][1] == saved['innerBlocks'][1]['innerBlocks'][index]['innerBlocks'][0]['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][1]
        assert 'custom-image' in classes(image)
        if family == 'cards-grid':
            assert column['innerBlocks'][3] == saved['innerBlocks'][1]['innerBlocks'][index]['innerBlocks'][3]
    assert result['repeat'] == result['after']
    assert result['repeatReport'] == {'cards-grid': 0, 'gallery-strip': 0, 'refused': []}
    def outline(value):
        return value['blockName'], value['attrs'], [outline(child) for child in value['innerBlocks']]
    reopened = result['parsed'][0]['innerBlocks'][1]
    assert outline(reopened) == outline(target)
    assert reopened['innerHTML'] == 'opaque before<!-- saved gap -->opaque after'


def test_authored_blank_zero_hover_and_inherited_responsive_values_are_preserved():
    saved = fixture()
    grid = saved['innerBlocks'][1]
    grid['attrs']['module']['decoration']['layout'] = {
        'desktop': {'value': {'display': 'grid', 'gridColumnCount': '2', 'columnGap': '0px'}},
        'phone': {'value': {'gridColumnCount': '', 'rowGap': '12px'}},
        'tablet': {'hover': {'columnGap': '99px'}},
    }
    image = grid['innerBlocks'][0]['innerBlocks'][0]
    image['attrs']['image']['decoration'] = {
        'border': {'desktop': {'value': {'radius': {'topLeft': '0px', 'bottomRight': ''}}}},
        'fit': {'desktop': {'value': {'objectFit': ''}}, 'phone': {'value': {'objectFit': 'contain'}}},
    }
    heading = grid['innerBlocks'][0]['innerBlocks'][1]
    heading['attrs']['title']['decoration']['font']['font']['desktop']['value'].update({'size': '', 'weight': 0, 'color': None})
    image['attrs']['module']['advanced'] = {'spacing': {'desktop': {'value': {'margin': {'bottom': '17px'}}}}}
    result = run([saved])
    assert result['report']['cards-grid'] == 1
    native = result['after'][0]['innerBlocks'][1]
    layout = native['attrs']['module']['decoration']['layout']
    assert layout['desktop']['value']['gridColumnCount'] == '2'
    assert 'gridColumnCount' not in layout.get('tablet', {}).get('value', {})
    assert layout['tablet']['hover'] == {'columnGap': '99px'}
    assert layout['phone']['value']['gridColumnCount'] == ''
    image = native['innerBlocks'][0]['innerBlocks'][0]
    assert image['attrs']['image']['decoration']['fit'] == saved['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['image']['decoration']['fit']
    assert image['attrs']['module']['advanced']['spacing']['desktop']['value']['margin']['bottom'] == '17px'
    assert 'tablet' not in image['attrs']['module']['advanced']['spacing']
    font = native['innerBlocks'][0]['innerBlocks'][1]['attrs']['title']['decoration']['font']['font']['desktop']['value']
    assert font['size'] == '' and font['weight'] == 0 and font['color'] is None


def test_generated_fullwidth_does_not_override_an_authored_image_width():
    saved = fixture()
    image = saved['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]
    image['attrs']['module']['advanced'] = {'sizing': {
        'desktop': {'value': {'width': '42%', 'height': '0px'}},
        'phone': {'value': {'width': '', 'aspectRatio': {'width': '16', 'height': '9'}}},
    }}
    result = run([saved])
    native = result['after'][0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['advanced']['sizing']
    assert native['desktop']['value']['width'] == '42%'
    assert native['desktop']['value']['height'] == '0px'
    assert 'forceFullwidth' not in native['desktop']['value']
    assert native['phone'] == image['attrs']['module']['advanced']['sizing']['phone']


@pytest.mark.parametrize('alter,reason', [
    (lambda s: s['attrs'].update({'modulePreset': ['shared-editor-preset']}), 'preset'),
    (lambda s: s['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['attrs'].update({'groupPreset': {'image.decoration.fit': {'presetId': ['shared-fit']}}}), 'preset'),
    (lambda s: s['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][2]['attrs']['content'].update({'decoration': {'bodyFont': {'body': {'presetId': ['shared-body-font']}}}}), 'preset'),
    (lambda s: s['innerBlocks'].append(block('divi/row', 'unrecognized')), 'sibling'),
    (lambda s: s['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'].pop(0), 'image'),
    (lambda s: s['innerBlocks'][1]['innerBlocks'][0]['attrs']['module']['decoration'].update({'spacing': {'desktop': {'value': ''}}}), 'value'),
])
def test_refusal_is_atomic_and_retains_whole_section(alter, reason):
    saved = fixture()
    alter(saved)
    result = run([saved])
    assert result['after'] == [saved]
    assert result['report']['cards-grid'] == 0
    assert len(result['report']['refused']) == 1
    assert reason.lower() in result['report']['refused'][0]['reason'].lower()


def test_contain_wrapper_and_conflicting_modifier_are_refused_without_partial_edit():
    saved = fixture(fit='contain')
    assert run([saved])['after'] == [saved]
    saved = fixture()
    attrs = saved['innerBlocks'][1]['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes']
    attrs[0]['value'] += ' bioco-divi-cards-grid-row--ratio-1_1'
    result = run([saved])
    assert result['after'] == [saved] and 'conflicting' in result['report']['refused'][0]['reason']


def test_responsive_class_retirement_is_refused_and_unrelated_classes_are_exact():
    saved = fixture()
    grid = saved['innerBlocks'][1]
    grid['attrs']['module']['advanced'] = {'htmlAttributes': {'phone': {'value': {'class': 'bioco-divi-row author-phone-class'}}}}
    result = run([saved])
    assert result['after'] == [saved]
    assert 'state-specific' in result['report']['refused'][0]['reason']


def test_unrelated_editorial_families_and_class_substrings_are_noops():
    saved = fixture()
    saved['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][0]['value'] = 'custom-bioco-divi-cards-grid bioco-divi-cta'
    result = run([saved])
    assert result['after'] == [saved]
    assert result['report'] == {'cards-grid': 0, 'gallery-strip': 0, 'refused': []}


def test_helper_has_no_runtime_registration_or_mutation_entry_point():
    source = (ROOT / HELPER).read_text()
    for name in ['add_action(', 'add_filter(', 'update_post', 'update_option', 'wp_insert_post', '->query(', 'wp_enqueue']:
        assert name not in source
