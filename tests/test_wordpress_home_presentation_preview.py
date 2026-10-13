"""Pure homepage presentation preservation and actual WordPress block grammar."""
import copy
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
HELPER = 'wordpress/web/app/mu-plugins/bioco-import/includes/home-presentation-preview.php'
PHP = r'''
define('ABSPATH', __DIR__);
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
function wp_json_encode($value,...$args){return json_encode($value,...$args);}
WP_SERIALIZER
require 'HELPER';
$before=json_decode(stream_get_contents(STDIN),true);
[$after,$report]=bioco_home_presentation_preview($before);
[$repeat,$repeatReport]=bioco_home_presentation_preview($after);
echo json_encode(['after'=>$after,'report'=>$report,'repeat'=>$repeat,'repeatReport'=>$repeatReport,
 'parsed'=>(new WP_Block_Parser())->parse(serialize_blocks($after))]);
'''.replace('WP_SERIALIZER', WP_SERIALIZER_STUB).replace('HELPER', HELPER)


def run(blocks):
    result = subprocess.run(['php', '-r', PHP], cwd=ROOT, input=json.dumps(blocks),
                            text=True, capture_output=True, check=True)
    assert not result.stderr, result.stderr
    return json.loads(result.stdout)


def block(name, classes='', children=None, attrs=None):
    children = children or []
    attrs = copy.deepcopy(attrs or {})
    attrs.setdefault('module', {}).setdefault('decoration', {})['attributes'] = {
        'desktop': {'value': {'attributes': [
            {'id': 'saved-class-id', 'name': 'class', 'value': classes + ' author-class', 'adminLabel': 'CSS Class'},
            {'id': 'saved-anchor-id', 'name': 'id', 'value': 'saved-anchor', 'adminLabel': 'CSS ID'},
        ]}}}
    attrs['module']['decoration'].setdefault('layout', {'desktop': {'value': {'display': 'block'}}})
    return {'blockName': name, 'attrs': attrs, 'innerBlocks': children, 'innerHTML': '',
            'innerContent': [None] * len(children) if children else ['\n']}


def heading(classes, text='Saved ä <br>heading', level='h2'):
    return block('divi/heading', classes, attrs={'title': {
        'innerContent': {'desktop': {'value': text}},
        'decoration': {'font': {'font': {'desktop': {'value': {'headingLevel': level}}}}}}})


def text(classes):
    return block('divi/text', classes, attrs={'content': {'innerContent': {
        'desktop': {'value': '<p>Saved <strong>copy</strong> <a href="/same?blank=&zero=0">link</a></p>'}}}})


def image(classes):
    return block('divi/image', classes, attrs={'image': {'innerContent': {'desktop': {'value': {
        'src': 'https://example.test/saved.jpg', 'id': 113, 'alt': 'Saved "ä" alt',
        'linkUrl': '/saved-photo', 'linkTarget': 'on'}}}}})


def button(secondary=False):
    return block('divi/button', 'bioco-home-button bioco-home-button--' + ('secondary' if secondary else 'primary'),
                 attrs={'button': {'innerContent': {'desktop': {'value': {
                     'text': 'Saved action', 'linkUrl': '/saved-link', 'linkTarget': 'off'}}}}})


def hero():
    column = block('divi/column', 'bioco-home-hero-column', [image('bioco-home-hero-image'),
        heading('bioco-home-hero-title', level='h1'), text('bioco-home-hero-subtitle')])
    column['innerContent'] = ['opaque start', None, '<!-- saved between -->', None, 'opaque end', None]
    return block('divi/section', 'bioco-home-hero', [block('divi/row', 'bioco-home-hero-row', [column])])


def feature(media_first=False):
    content = block('divi/column', 'bioco-home-feature-content', [heading('bioco-home-feature-title'),
        text('bioco-home-feature-text'), button(media_first), block('divi/text', 'unrelated-added-module')])
    media = block('divi/column', 'bioco-home-feature-media', [image('bioco-home-feature-image')])
    columns = [media, content] if media_first else [content, media]
    return block('divi/section', 'bioco-home-feature', [block('divi/row', 'bioco-home-feature-row', columns)])


def cta():
    return block('divi/section', 'bioco-home-cta', [block('divi/row', '', [block('divi/column',
        'bioco-home-cta-content', [heading('bioco-home-cta-title'), text('bioco-home-cta-text'), button(), button(True)])])])


def fixture():
    return [block('divi/placeholder', '', [hero(), feature(), feature(True),
        block('divi/section', 'bioco-home-live', [block('bioco-divi/schnuppertage', 'functional')]), cta(),
        block('divi/section', 'bioco-dynamic-section', [block('bioco-divi/events-feed', 'functional')])])]


def sections(result):
    return result['after'][0]['innerBlocks']


def column(section, index=0):
    return section['innerBlocks'][0]['innerBlocks'][index]


def content_projection(blocks):
    """Capture editorial data and block order without treating new style values as evidence."""
    out = []
    for b in blocks:
        attrs = b.get('attrs') or {}
        # Generated decorative module has no editorial content.
        if attrs.get('module', {}).get('meta', {}).get('adminLabel', {}).get('desktop', {}).get('value') == 'Hero Verlauf':
            continue
        out.append((b['blockName'], {key: copy.deepcopy(value.get('innerContent')) for key, value in attrs.items()
                    if isinstance(value, dict) and 'innerContent' in value}, b['innerHTML']))
        out.extend(content_projection(b.get('innerBlocks', [])))
    return out


def test_saved_content_links_image_identity_and_functional_sections_stay_exact():
    before = fixture()
    result = run(before)
    assert result['report'] == {'hero': 1, 'feature': 2, 'cta': 1, 'refused': []}
    assert content_projection(result['after']) == content_projection(before)
    assert sections(result)[3] == before[0]['innerBlocks'][3]
    assert sections(result)[5] == before[0]['innerBlocks'][5]
    assert column(sections(result)[1])['innerBlocks'][3] == column(before[0]['innerBlocks'][1])['innerBlocks'][3]
    assert result['repeat'] == result['after']
    assert result['repeatReport'] == {'hero': 0, 'feature': 0, 'cta': 0, 'refused': []}


def test_overlay_insertion_keeps_every_existing_fragment_and_child_order():
    before = [hero()]
    result = run(before)
    after = column(result['after'][0])
    original = column(before[0])
    assert [v for v in after['innerContent'] if v is not None] == [v for v in original['innerContent'] if v is not None]
    assert after['innerContent'] == ['opaque start', None, None, '<!-- saved between -->', None, 'opaque end', None]
    assert [b['blockName'] for b in after['innerBlocks']] == ['divi/image', 'divi/text', 'divi/heading', 'divi/text']
    overlay = after['innerBlocks'][1]
    assert overlay['attrs']['content']['innerContent']['desktop']['value'] == '<span aria-hidden="true"></span>'
    assert overlay['attrs']['module']['decoration']['zIndex']['desktop']['value'] == '1'
    assert result['parsed'][0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][1]['attrs'] == overlay['attrs']


def test_hero_geometry_matches_feature_effective_container_and_native_crop_is_saved():
    result = run(fixture())
    hero_section, feature_section = sections(result)[:2]
    for section in [hero_section, feature_section]:
        controls = section['attrs']['module']['decoration']
        assert controls['sizing']['desktop']['value']['width'] == '100%'
        assert controls['spacing']['desktop']['value']['padding']['left'] == '6vw'
        row = section['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['desktop']['value']
        assert row['width'] == '100%'
        assert row['maxWidth'] == 'var(--gvid-bioco-1ca8a232395ae962)'
        assert row['alignment'] == 'center'
    caption = column(hero_section)['attrs']['module']['decoration']['spacing']
    assert caption['desktop']['value']['padding'] == {'top': '48px', 'right': '184.4px', 'bottom': '128px', 'left': '184.4px'}
    assert caption['phone']['value']['padding'] == {key: '24px' for key in ['top', 'right', 'bottom', 'left']}
    photo = column(hero_section)['innerBlocks'][0]['attrs']
    assert photo['module']['advanced']['sizing']['desktop']['value']['height'] == '585px'
    assert photo['module']['advanced']['sizing']['phone']['value']['height'] == '552px'
    assert photo['image']['decoration']['fit']['desktop']['value']['objectFit'] == 'cover'
    assert photo['image']['innerContent'] == column(fixture()[0]['innerBlocks'][0])['innerBlocks'][0]['attrs']['image']['innerContent']


def test_authored_shared_container_controls_override_defaults_without_replacement():
    before = [hero(), feature()]
    for section in before:
        section['attrs']['module']['decoration']['spacing'] = {'desktop': {'value': {'padding': {'left': '2vw'}}}}
        section['innerBlocks'][0]['attrs']['module']['decoration']['sizing'] = {
            'desktop': {'value': {'width': '91%', 'maxWidth': 'var(--authored-width)'}},
            'phone': {'value': {'width': '84%'}}}
    result = run(before)
    for section in result['after']:
        assert section['attrs']['module']['decoration']['spacing']['desktop']['value']['padding']['left'] == '2vw'
        row = section['innerBlocks'][0]['attrs']['module']['decoration']['sizing']
        assert row['desktop']['value']['width'] == '91%'
        assert row['desktop']['value']['maxWidth'] == 'var(--authored-width)'
        assert row['phone']['value']['width'] == '84%'


@pytest.mark.parametrize('value', ['', None, False, 0, '17px', 'var(--saved-token)'])
def test_authored_desktop_values_are_preserved_and_not_masked_on_phone(value):
    before = [feature()]
    title = column(before[0])['innerBlocks'][0]
    title['attrs']['title']['decoration']['font']['font']['desktop']['value']['size'] = value
    result = run(before)
    font = column(result['after'][0])['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']
    assert font['desktop']['value']['size'] == value
    assert 'size' not in font.get('phone', {}).get('value', {})


def test_authored_responsive_hover_id_class_and_width_controls_remain_native():
    before = [hero()]
    c = column(before[0])
    c['attrs']['module']['decoration']['layout']['desktop']['value'] = {'display': 'grid', 'columnGap': '17px'}
    photo = c['innerBlocks'][0]
    photo['attrs']['module']['advanced'] = {'sizing': {'desktop': {'value': {'width': '75%'}, 'hover': {'width': '90%'}},
                                                    'tablet': {'value': {'height': '444px'}}}}
    result = run(before)
    out = column(result['after'][0])
    assert out['attrs']['module']['decoration']['layout']['desktop']['value']['display'] == 'grid'
    controls = out['innerBlocks'][0]['attrs']['module']['advanced']['sizing']
    assert controls['desktop']['value']['width'] == '75%'
    assert controls['desktop']['hover'] == {'width': '90%'}
    assert controls['tablet']['value'] == {'height': '444px'}
    assert 'height' not in controls.get('phone', {}).get('value', {})
    assert 'forceFullwidth' not in controls['desktop']['value']
    attributes = out['innerBlocks'][0]['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes']
    assert attributes[0] == {'id': 'saved-class-id', 'name': 'class', 'value': 'author-class', 'adminLabel': 'CSS Class'}
    assert attributes[1] == photo['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][1]


@pytest.mark.parametrize('where', ['modulePreset', 'groupPreset', 'nestedPresetId', 'badPlaceholder', 'missingImage', 'responsiveClass'])
def test_uncaptured_presets_or_unsupported_structures_refuse_atomically(where):
    before = [hero()]
    c = column(before[0])
    if where == 'nestedPresetId':
        c['innerBlocks'][2]['attrs']['content']['decoration'] = {'bodyFont': {'body': {'presetId': ['shared-font']}}}
    elif where in ['modulePreset', 'groupPreset']:
        c['innerBlocks'][0]['attrs'][where] = ['shared-preset']
    elif where == 'badPlaceholder':
        c['innerContent'] = ['opaque', None]
    elif where == 'missingImage':
        c['innerBlocks'][0]['blockName'] = 'divi/blurb'
    else:
        c['attrs']['module']['advanced'] = {'htmlAttributes': {'phone': {'value': {'class': 'bioco-home-hero-column'}}}}
    result = run(before)
    assert result['after'] == before
    assert result['report']['hero'] == 0
    assert len(result['report']['refused']) == 1
    assert result['report']['refused'][0]['path'] == [0]


def test_repeat_wp_parse_retains_native_fonts_crop_and_secondary_cta_contract():
    result = run(fixture())
    parsed = result['parsed'][0]['innerBlocks']
    title = column(parsed[1])['innerBlocks'][0]['attrs']['title']
    assert title['decoration']['font']['font']['desktop']['value']['size'] == '26px'
    assert title['decoration']['font']['font']['phone']['value']['size'] == '24px'
    photo = column(parsed[1], 1)['innerBlocks'][0]['attrs']
    assert photo['module']['advanced']['sizing']['desktop']['value']['aspectRatio'] == {'width': '4', 'height': '3'}
    assert photo['module']['advanced']['sizing']['phone']['value']['height'] == '300px'
    secondary = column(parsed[4])['innerBlocks'][3]['attrs']['button']
    assert secondary['innerContent']['desktop']['value']['linkUrl'] == '/saved-link'
    assert secondary['decoration']['background']['desktop']['value']['color'] == '#FFFFFF'
    assert secondary['decoration']['border']['desktop']['value']['styles']['all']['color'] == 'transparent'


def test_button_padding_uses_native_module_spacing_and_cta_phone_uses_flex_stack():
    result = run(fixture())
    cta_column = column(result['parsed'][0]['innerBlocks'][4])
    phone = cta_column['attrs']['module']['decoration']['layout']['phone']['value']
    assert phone == {'flexDirection': 'column', 'flexWrap': 'nowrap', 'alignItems': 'flex-start', 'columnGap': '0px', 'rowGap': '0px'}
    for section in [result['parsed'][0]['innerBlocks'][1], result['parsed'][0]['innerBlocks'][4]]:
        attrs = column(section)['innerBlocks'][2]['attrs']
        assert 'spacing' not in attrs['button']['decoration']
        assert attrs['module']['decoration']['spacing']['desktop']['value']['padding'] == {'top': '12px', 'right': '24px', 'bottom': '12px', 'left': '24px'}


def test_authored_native_button_padding_and_phone_flow_are_preserved():
    before = [cta()]
    c = column(before[0])
    c['attrs']['module']['decoration']['layout'] = {'desktop': {'value': {'display': 'flex', 'flexDirection': 'row'}}, 'phone': {'value': {'flexDirection': 'row', 'columnGap': '7px'}}}
    c['innerBlocks'][2]['attrs']['module']['decoration']['spacing'] = {'desktop': {'value': {'padding': {'left': '0px', 'right': '17px'}}}, 'phone': {'value': {'padding': {'left': '8px'}}}}
    result = run(before)
    c = column(result['after'][0])
    assert c['attrs']['module']['decoration']['layout']['phone']['value']['flexDirection'] == 'row'
    assert c['attrs']['module']['decoration']['layout']['phone']['value']['columnGap'] == '7px'
    saved = c['innerBlocks'][2]['attrs']['module']['decoration']['spacing']
    assert saved['desktop']['value']['padding']['left'] == '0px'
    assert saved['desktop']['value']['padding']['right'] == '17px'
    assert saved['phone']['value']['padding']['left'] == '8px'
