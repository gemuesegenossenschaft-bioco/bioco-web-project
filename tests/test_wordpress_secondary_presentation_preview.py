"""Bounded native accordion and portal defaults; opaque steps remain a migration gap."""
import copy
import json
import subprocess
import pytest

from test_wordpress_presentation_adoption import ROOT, PHP, WP_SERIALIZER_STUB, block, classes, tokens


def run(blocks):
    script = PHP.replace('WP_SERIALIZER', WP_SERIALIZER_STUB).replace(
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/presentation-adoption.php';",
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/secondary-presentation-preview.php';")
    script = script.replace(
        "$result['blocks'] = bioco_import_presentation_tree($input['blocks'], $result['report']);",
        "[$result['blocks'], $result['report']] = bioco_secondary_presentation_preview($input['blocks']);")
    process = subprocess.run(['php', '-r', script], input=json.dumps({'blocks': blocks}),
                             text=True, capture_output=True, cwd=ROOT, check=True)
    assert process.stderr == ''
    return json.loads(process.stdout)


def accordion():
    toggles = []
    for i in range(4):
        attrs = classes('bioco-editorial-toggle')
        attrs.update(title={'innerContent': {'desktop': {'value': f'Title Ä {i}'}},
                            'decoration': {'font': {'font': {'desktop': {'value': {
                                'headingLevel': 'h3', 'weight': '600', 'color': '#1A1A1A'}}}}}},
                     content={'innerContent': {'desktop': {'value': f'<p>Body {i} <a href="/keep">link</a></p>'}},
                              'decoration': {'bodyFont': {'body': {'font': {'desktop': {'value': {
                                  'lineHeight': '1.7em', 'color': '#1A1A1A'}}}}}}})
        attrs['module']['advanced']['open'] = {'desktop': {'value': 'off'}}
        attrs['module']['decoration']['spacing'] = {'desktop': {'value': {
            'padding': dict.fromkeys(['top', 'bottom', 'left', 'right'], '24px'),
            'margin': {'bottom': '20px'}}}}
        toggles.append(block('divi/toggle', attrs))
    return block('divi/section', classes('bioco-divi-section', 'bioco-divi-accordion', 'demeter-accordion'),
                 [block('divi/row', classes('bioco-divi-row'),
                        [block('divi/column', classes('bioco-divi-content'), toggles)])])


def portal():
    heading = block('divi/heading', classes('bioco-divi-link-tiles-title') | {
        'title': {'innerContent': {'desktop': {'value': 'Gateway'}},
                  'decoration': {'font': {'font': {'desktop': {'value': {'headingLevel': 'h2'}}}}}}})
    header = block('divi/row', classes('bioco-divi-row'),
                   [block('divi/column', classes('bioco-divi-content'), [heading])])
    tiles = []
    for title in ['Mitglieder-Portal', 'Einsatzplanung']:
        icon = block('divi/text', classes('bioco-editorial-tile-icon') | {
            'content': {'innerContent': {'desktop': {'value': '🦆'}}, 'decoration': {
                'bodyFont': {'body': {'font': {'desktop': {'value': {'size': '2rem'}}}}}}}})
        blurb = block('divi/blurb', classes('bioco-editorial-card') | {
            'title': {'innerContent': {'desktop': {'value': {'text': title, 'url': 'https://intranet.bioco.ch', 'target': 'on'}}},
                      'decoration': {'font': {'font': {'desktop': {'value': {'headingLevel': 'h3', 'weight': '700', 'color': '#333333'}}}}}},
            'content': {'innerContent': {'desktop': {'value': '<p>Keep body</p>'}}, 'decoration': {
                'bodyFont': {'body': {'font': {'desktop': {'value': {'lineHeight': '1.7em', 'color': '#4A4A4A'}}}}}}}})
        tiles.append(block('divi/column', classes('bioco-editorial-tile'), [icon, blurb]))
    grid = block('divi/row', classes('bioco-editorial-tiles-row'), tiles)
    grid['attrs']['module']['decoration']['layout'] = {
        'desktop': {'value': {'display': 'grid', 'gridTemplateColumns': 'repeat(2, minmax(0, 1fr))', 'columnGap': '24px'}},
        'phone': {'value': {'gridTemplateColumns': 'minmax(0, 1fr)'}}}
    grid['attrs']['module']['decoration']['sizing'] = {'desktop': {'value': {'width': '100%', 'maxWidth': '1080px'}}}
    return block('divi/section', classes('bioco-divi-section', 'bioco-divi-link-tiles'), [header, grid])


def test_native_accordion_keeps_toggle_behavior_content_and_saved_design():
    before = accordion()
    result = run([before])
    assert result['report'] == [{'path': [0], 'action': 'accordion'}]
    after = result['blocks'][0]
    assert tokens(after) == ['bioco-native-accordion-section']
    assert after['attrs']['module']['decoration']['spacing']['desktop']['value']['margin']['top'] == '24px'
    assert tokens(after['innerBlocks'][0]) == []
    assert tokens(after['innerBlocks'][0]['innerBlocks'][0]) == []
    for old, new in zip(before['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'],
                        after['innerBlocks'][0]['innerBlocks'][0]['innerBlocks']):
        assert tokens(new) == ['bioco-editorial-toggle']
        assert new['attrs']['title']['innerContent'] == old['attrs']['title']['innerContent']
        assert new['attrs']['content']['innerContent'] == old['attrs']['content']['innerContent']
        assert new['attrs']['module']['advanced'] == old['attrs']['module']['advanced']
        assert new['attrs']['module']['decoration']['spacing'] == old['attrs']['module']['decoration']['spacing']
    assert run(result['blocks'])['blocks'] == result['blocks']
    assert run(result['blocks'])['report'] == []


def test_native_portal_preserves_grid_tile_content_links_icons_and_order():
    before = portal()
    after = run([before])['blocks'][0]
    assert tokens(after) == ['bioco-native-portal-section']
    heading = after['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    assert tokens(heading) == []
    assert heading['attrs']['title']['decoration']['font']['font']['desktop']['value'] == {
        'headingLevel': 'h2', 'family': 'DM Sans', 'size': '26px', 'weight': '500', 'lineHeight': '1em', 'color': '#333333'}
    assert after['innerBlocks'][1]['attrs'] == before['innerBlocks'][1]['attrs']
    for old, new in zip(before['innerBlocks'][1]['innerBlocks'], after['innerBlocks'][1]['innerBlocks']):
        assert new['attrs'] == old['attrs']
        for old_leaf, leaf in zip(old['innerBlocks'], new['innerBlocks']):
            assert leaf['attrs']['content']['innerContent'] == old_leaf['attrs']['content']['innerContent']
            if leaf['blockName'] == 'divi/blurb':
                assert leaf['attrs']['title']['innerContent'] == old_leaf['attrs']['title']['innerContent']
        assert new['innerBlocks'][0]['attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['size'] == '2rem'
    assert run([after]) == {'blocks': [after], 'report': []}


@pytest.mark.parametrize('key', ['modulePreset', 'groupPreset', 'presetId'])
@pytest.mark.parametrize('kind', ['accordion', 'portal'])
def test_nested_shared_presets_refuse_the_whole_family(key, kind):
    before = accordion() if kind == 'accordion' else portal()
    before['innerBlocks'][-1]['innerBlocks'][-1]['innerBlocks'][-1]['attrs']['nested'] = {'deeper': {key: 'unresolved'}}
    result = run([before])
    assert result['blocks'] == [before]
    assert result['report'][0]['action'] == 'preserve'


@pytest.mark.parametrize('kind', ['accordion', 'portal'])
@pytest.mark.parametrize('change', ['extra-row', 'extra-column', 'extra-item', 'wrong-kind', 'css', 'unknown-class'])
def test_unmeasured_structures_and_styles_refuse_atomically(kind, change):
    before = accordion() if kind == 'accordion' else portal()
    if change == 'extra-row': before['innerBlocks'].append(block('divi/row', {'authored': 'keep'}))
    elif change == 'extra-column': before['innerBlocks'][0]['innerBlocks'].append(block('divi/column', {'authored': 'keep'}))
    elif change == 'extra-item': before['innerBlocks'][-1]['innerBlocks'][-1]['innerBlocks'].append(block('divi/text', {'authored': 'keep'}))
    elif change == 'wrong-kind': before['innerBlocks'][-1]['innerBlocks'][-1]['innerBlocks'][-1]['blockName'] = 'divi/image'
    elif change == 'css': before['innerBlocks'][-1]['attrs']['css'] = {'desktop': {'value': {'mainElement': 'width: 4px'}}}
    else: before['innerBlocks'][0]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] = 'authored-other-class'
    result = run([before])
    assert result['blocks'] == [before]
    assert result['report'][0]['action'] == 'preserve'


@pytest.mark.parametrize('device,state', [('phone', 'value'), ('tablet', 'value'), ('desktop', 'hover')])
def test_responsive_legacy_classes_refuse_the_whole_family(device, state):
    before = portal()
    before['innerBlocks'][0]['attrs']['module']['advanced']['htmlAttributes'][device] = {
        state: {'class': 'bioco-divi-row'}}
    assert run([before])['blocks'] == [before]


def test_authored_native_design_and_inherited_spacing_win_over_defaults():
    before = portal()
    before['attrs']['module']['decoration']['spacing'] = {
        'desktop': {'value': {'padding': {'top': '77px'}}},
        'tablet': {'value': {'padding': {'left': '88px'}}}}
    heading = before['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    heading['attrs']['title']['decoration']['font']['font']['desktop']['value']['size'] = '35px'
    heading['attrs']['title']['decoration']['font']['font']['phone'] = {'value': {'color': '#123456'}}
    after = run([before])['blocks'][0]
    spacing = after['attrs']['module']['decoration']['spacing']
    assert spacing['desktop']['value']['padding']['top'] == '77px'
    assert spacing['tablet'] == before['attrs']['module']['decoration']['spacing']['tablet']
    assert spacing['phone']['value']['padding'] == {'bottom': '32px', 'right': '6vw'}
    heading = after['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]
    assert heading['attrs']['title']['decoration']['font']['font']['desktop']['value']['size'] == '35px'
    assert heading['attrs']['title']['decoration']['font']['font']['phone'] == {'value': {'color': '#123456'}}


def test_steps_are_reported_as_an_unresolved_structural_migration():
    before = block('divi/section', classes('bioco-divi-section', 'bioco-divi-steps'),
                   [block('divi/row', classes('bioco-divi-row'), [block('divi/column', classes('bioco-divi-content'),
                    [block('divi/text', classes('bioco-divi-steps-body') | {'content': {'innerContent': {
                        'desktop': {'value': '<div class="next-steps">Keep exact authored HTML</div>'}}}})])])])
    result = run([before])
    assert result['blocks'] == [before]
    assert result['report'][0] == {'path': [0], 'action': 'preserve', 'reason': 'Steps are opaque HTML; native item migration is required.'}


def test_unrelated_dynamic_native_modules_and_opaque_structure_are_unchanged():
    before = portal()
    before['attrs']['nativeId'] = 'stable-section-id'
    before['innerHTML'] = '<!-- exact opaque marker -->'
    before['innerContent'].insert(0, before['innerHTML'])
    dynamic = block('divi/section', classes('bioco-dynamic-section'), [block('bioco-divi/gallery', {'query': 'preserve'})])
    result = run([before, dynamic])
    after = result['blocks'][0]
    assert result['blocks'][1] == dynamic
    assert after['attrs']['nativeId'] == 'stable-section-id'
    assert after['innerHTML'] == before['innerHTML']
    assert after['innerContent'] == before['innerContent']

    def shape(blocks):
        return [{k: shape(v) if k == 'innerBlocks' else v for k, v in b.items() if k != 'attrs'} for b in blocks]

    assert shape([after]) == shape([before])
