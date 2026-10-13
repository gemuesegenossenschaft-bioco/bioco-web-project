"""Native CTA/topic previews preserve saved blocks and refuse unknown styles."""
import copy
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
PHP = r'''
define('ABSPATH', __DIR__);
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
function wp_json_encode($v,...$args){return json_encode($v,...$args);}
WP_SERIALIZER
require 'wordpress/web/app/mu-plugins/bioco-import/includes/editorial-cta-presentation-preview.php';
$input=json_decode(stream_get_contents(STDIN),true);
try {
 [$after,$report]=bioco_editorial_cta_native_preview($input);
 [$repeat,$repeatReport]=bioco_editorial_cta_native_preview($after);
 echo json_encode(['after'=>$after,'report'=>$report,'repeat'=>$repeat,
  'repeatReport'=>$repeatReport,'parsed'=>(new WP_Block_Parser())->parse(serialize_blocks($after))]);
} catch(Throwable $e) {echo json_encode(['error'=>$e->getMessage(),'input'=>$input]);}
'''.replace('WP_SERIALIZER', WP_SERIALIZER_STUB)


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
            {'id': 'kept-class-id', 'name': 'class', 'value': classes},
            {'id': 'kept-anchor-id', 'name': 'id', 'value': 'kept-anchor'},
        ]}}}
    return {'blockName': name, 'attrs': attrs, 'innerBlocks': children, 'innerHTML': '' if children else '\n',
            'innerContent': [None] * len(children) + [''] if children else ['\n']}


def fixture(topics=False):
    heading = block('divi/heading', 'bioco-divi-title author-title', attrs={
        'title': {'innerContent': {'desktop': {'value': 'Kept ä heading'}}}})
    text = block('divi/text', 'bioco-divi-text', attrs={
        'content': {'innerContent': {'desktop': {'value': '<p>Kept <a href="/saved">link</a>.</p>'}}}})
    button = block('divi/button', 'bioco-divi-button', attrs={
        'button': {'innerContent': {'desktop': {'value': {'text': 'Kept CTA', 'linkUrl': '/saved'}}}}})
    column = block('divi/column', 'bioco-divi-content', [heading, text, button])
    rows = [block('divi/row', 'bioco-divi-row', [column])]
    if topics:
        native_columns = [block('divi/column', '', [block('divi/blurb', '', attrs={
            'content': {'innerContent': {'desktop': {'value': f'<p>Native topic {i}/{j}.</p>'}}}})
            for j in range(2)]) for i in range(2)]
        rows = [block('divi/row', '', native_columns)]
        image = block('divi/image', 'bioco-divi-text-columns-image', attrs={
            'image': {'innerContent': {'desktop': {'value': {
                'src': 'https://example.test/kept.jpg', 'alt': 'Kept alt', 'id': 12, 'linkUrl': '/kept-image'}}}}})
        rows.append(block('divi/row', 'bioco-divi-row', [block('divi/column', 'bioco-divi-content', [image, button])]))
    classes = ('bioco-divi-section bioco-divi-text-columns bioco-divi-width-lg' if topics else
               'bioco-divi-section bioco-divi-cta bioco-divi-align-left bioco-divi-theme-soft bioco-divi-rounded-xl')
    section = block('divi/section', classes + ' author-section', rows)
    section['innerContent'].pop()  # The core parser omits an empty top-level tail.
    return [section]


def leaves(blocks):
    return blocks[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks']


@pytest.mark.parametrize('topics', [False, True])
def test_round_trip_preserves_content_ids_and_unrelated_blocks(topics):
    source = fixture(topics)
    unrelated = block('divi/code', 'untouched', attrs={'content': {'value': 'Kept code'}})
    source.append(unrelated)
    result = run(source)
    assert result['report'] == {'cta': int(not topics), 'topics': int(topics), 'images': int(topics)}
    assert result['after'] == result['repeat'] == result['parsed']
    assert result['repeatReport'] == {'cta': 0, 'topics': 0, 'images': 0}
    assert result['after'][1] == unrelated
    old, new = leaves(source), leaves(result['after'])
    if not topics:
        assert new[0]['attrs']['title']['innerContent'] == old[0]['attrs']['title']['innerContent']
        assert new[1]['attrs']['content']['innerContent'] == old[1]['attrs']['content']['innerContent']
        assert new[2:] == old[2:]
    else:
        assert new == old  # Native topic Blurbs stay fully authored.
    for item in [result['after'][0], *new[:2]]:
        attrs = item['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes']
        assert attrs[0]['id'] == 'kept-class-id'
        assert attrs[1] == {'id': 'kept-anchor-id', 'name': 'id', 'value': 'kept-anchor'}
    if topics:
        old_image = source[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]
        new_image = result['after'][0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]
        assert new_image['attrs']['image']['innerContent'] == old_image['attrs']['image']['innerContent']
        assert new_image['attrs']['module']['advanced']['spacing']['desktop']['value']['margin']['top'] == '24px'


@pytest.mark.parametrize('value', ['', '0px', 0, False, None])
def test_authored_value_and_phone_inheritance_are_preserved(value):
    source = fixture()
    source[0]['attrs']['module']['decoration']['spacing'] = {
        'desktop': {'value': {'padding': {'top': value}}},
        'tablet': {'value': {'padding': {'bottom': '19px'}}},
        'desktopHover': {'value': {'padding': {'left': '9px'}}},
    }
    result = run(source)['after'][0]['attrs']['module']['decoration']['spacing']
    assert result['desktop']['value']['padding']['top'] == value
    assert result['tablet'] == source[0]['attrs']['module']['decoration']['spacing']['tablet']
    assert result['desktopHover'] == source[0]['attrs']['module']['decoration']['spacing']['desktopHover']
    assert 'top' not in result['phone']['value']['padding']
    assert 'bottom' not in result['phone']['value']['padding']
    assert result['phone']['value']['padding']['left'] == '6vw'


@pytest.mark.parametrize('key', ['modulePreset', 'groupPreset'])
def test_unresolved_nested_preset_refuses_conversion(key):
    source = fixture()
    leaves(source)[0]['attrs'][key] = {'presetId': 'authored-preset'}
    result = run(source)
    assert 'preset capture' in result['error']
    assert result['input'] == source


@pytest.mark.parametrize('key', ['presetId', 'modulePreset', 'groupPreset'])
def test_nested_control_preset_refuses_defaults_and_retains_original_tree(key):
    source = fixture()
    leaves(source)[1]['attrs']['content']['decoration'] = {
        'bodyFont': {'body': {key: 'shared-font-preset'}}}
    result = run(source)
    assert 'preset capture' in result['error']
    assert result['input'] == source


def test_unknown_structure_and_variant_refuse_without_partial_change():
    source = fixture(True)
    source[0]['innerBlocks'][1]['innerBlocks'][0]['blockName'] = 'divi/code'
    result = run(source)
    assert result['error'] == 'Expected native column.'
    assert result['input'] == source
    source = fixture()
    source[0]['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][0]['value'] += ' bioco-divi-rounded-md'
    assert run(source)['error'] == 'Unmeasured CTA/text-column variant.'


@pytest.mark.parametrize('topics', [False, True])
def test_extra_editor_column_requires_a_separate_measured_preview(topics):
    source = fixture(topics)
    source[0]['innerBlocks'][-1]['innerBlocks'].append(block('divi/column', 'author-column'))
    result = run(source)
    assert result['error'] == 'Expected one measured content column.'
    assert result['input'] == source


def test_phone_legacy_class_and_authored_heading_css_require_review():
    source = fixture()
    heading = leaves(source)[0]
    heading['attrs']['css'] = {'desktop': {'value': {'mainElement': 'color:red;'}}}
    result = run(source)
    assert result['error'] == 'Authored heading CSS requires a measured preview.'
    assert result['input'] == source
    del heading['attrs']['css']
    heading['attrs']['module']['advanced'] = {'htmlAttributes': {'phone': {'value': {'class': 'bioco-divi-title'}}}}
    assert 'separate review' in run(source)['error']
