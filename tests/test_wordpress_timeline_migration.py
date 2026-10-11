"""Saved timeline conversion; no seed reads, no Visual Builder claims.

Keep/Replace/Remove evidence map: tests/README.md.
"""
import copy

import pytest

from test_wordpress_editorial_layouts import migrate, normalize_saved_attributes, walk
from test_wordpress_divi_static_layouts import _compose


def leaf(kind, text, css):
    key = 'title' if kind == 'divi/heading' else 'content'
    attrs = {key: {'innerContent': {'desktop': {'value': text}}},
             'module': {'advanced': {'htmlAttributes': {'desktop': {'value': {'class': css}}}}}}
    if kind == 'divi/heading':
        attrs[key]['decoration'] = {'font': {'font': {'desktop': {'value': {'headingLevel': 'h3'}}}}}
    return {'blockName': kind, 'attrs': attrs, 'innerBlocks': [], 'innerHTML': '', 'innerContent': ['\n']}


def container(kind, css, children, type=None, anchor=''):
    advanced = {'htmlAttributes': {'desktop': {'value': {'class': css, 'id': anchor}}}}
    if type:
        advanced['columnStructure' if kind == 'divi/row' else 'type'] = {'desktop': {'value': type}}
    return {'blockName': kind, 'attrs': {'module': {'advanced': advanced}},
            'innerBlocks': children, 'innerHTML': '', 'innerContent': [None] * len(children)}


def legacy():
    header = container('divi/row', 'bioco-divi-row bioco-divi-timeline-header', [
        container('divi/column', 'bioco-divi-content bioco-divi-text-narrow bioco-divi-align-left', [
            leaf('divi/heading', 'Eigene Geschichte', 'bioco-divi-title'),
            leaf('divi/text', '<p>Der gespeicherte Text.</p>', 'bioco-divi-text'),
        ], '4_4')
    ], '4_4')
    rows = [header]
    for year, emphasis in [('0', 'highlight'), ('2026', 'normal')]:
        badge = leaf('divi/text', year, 'bioco-divi-timeline-badge')
        heading = leaf('divi/heading', 'Editor &amp; Team', 'bioco-divi-timeline-item-title')
        heading['attrs']['title']['decoration']['font']['font']['phone'] = {'value': {'size': '19px'}}
        body = leaf('divi/text', '<p>Eigener Inhalt <a href="/documents/statuten.pdf">PDF</a></p>', 'bioco-divi-timeline-item-text')
        rows.append(container('divi/row', 'bioco-divi-row bioco-divi-timeline-item-row bioco-divi-timeline-item--' + emphasis, [
            container('divi/column', 'bioco-divi-timeline-badge-col', [badge], '1_4'),
            container('divi/column', 'bioco-divi-timeline-item-content', [heading, body], '3_4'),
        ], '1_4,3_4', 'editor-' + year))
    return container('divi/section', 'bioco-divi-section bioco-divi-timeline bioco-divi-width-xl bioco-divi-align-center', rows, anchor='geschichte')


def test_saved_timeline_keeps_authored_values_order_ids_and_responsive_typography():
    before = _compose({'block': 'rich-text', 'values': {'text': '<p>Vorher</p>'}})
    after = _compose({'block': 'rich-text', 'values': {'text': '<p>Nachher</p>'}})
    saved = legacy()
    expected = [b['attrs'].get('title', b['attrs'].get('content')) for b in walk([saved])
                if b['blockName'] in ['divi/text', 'divi/heading']]
    out = migrate([before, saved, after])
    assert out['count'] == 1 and out['second'] == 0 and out['stable']
    assert out['blocks'][0]['attrs'] == before['attrs']
    assert out['blocks'][2]['attrs'] == after['attrs']
    timeline = out['blocks'][1]
    sizing = timeline['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['desktop']['value']
    assert sizing['maxWidth'] == '42rem' and sizing['alignment'] == 'left'
    modules = [b for b in walk([timeline]) if b['blockName'] in ['divi/text', 'divi/heading']]
    for original, migrated in zip(expected, modules, strict=True):
        key = 'title' if migrated['blockName'] == 'divi/heading' else 'content'
        assert migrated['attrs'][key]['innerContent'] == original['innerContent']
    headings = [b for b in modules if b['blockName'] == 'divi/heading']
    assert headings[1]['attrs']['title']['decoration']['font']['font']['phone']['value']['size'] == '19px'
    assert timeline['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] == 'geschichte'
    for year, row in zip(['0', '2026'], timeline['innerBlocks'][1:], strict=True):
        assert row['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] == 'editor-' + year
        decoration = row['attrs']['module']['decoration']
        assert decoration['layout']['phone']['value']['gridTemplateColumns'] == 'minmax(0, 1fr)'
        assert decoration['sizing']['desktop']['value']['maxWidth'] == '1280px'
    assert not any('bioco-divi-timeline' in str(b['attrs']) for b in walk([timeline]))


def test_normalized_divi_attribute_storage_has_no_conflicting_classes_or_anchors():
    saved = legacy()
    normalize_saved_attributes(saved)
    out = migrate([saved])
    assert out['count'] == 1 and out['stable']
    for block in walk(out['blocks']):
        module = block['attrs']['module']
        direct = module['advanced']['htmlAttributes']['desktop']['value']
        stored = {a['name']: a['value'] for a in module.get('decoration', {}).get('attributes', {}).get('desktop', {}).get('value', {}).get('attributes', [])}
        assert not direct['class'] or direct['class'] == stored['class']
        if stored.get('id'):
            assert not direct['id'] or direct['id'] == stored['id']


@pytest.mark.parametrize('change', ['design', 'classes', 'column', 'module', 'header-order'])
def test_custom_or_ambiguous_timeline_geometry_is_refused(change):
    saved = legacy()
    row = saved['innerBlocks'][1]
    if change == 'design':
        row['attrs']['module']['decoration'] = {'spacing': {'phone': {'value': {'padding': {'left': '6px'}}}}}
    elif change == 'classes':
        row['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] += ' editor-custom'
    elif change == 'column':
        row['innerBlocks'].append(copy.deepcopy(row['innerBlocks'][0]))
        row['innerContent'].append(None)
    elif change == 'module':
        row['innerBlocks'][1]['innerBlocks'][0]['blockName'] = 'divi/image'
    else:
        saved['innerBlocks'].append(saved['innerBlocks'].pop(0))
    out = migrate([saved])
    assert 'error' in out


def test_reordered_saved_events_migrate_in_their_current_editorial_order():
    saved = legacy()
    saved['innerBlocks'][1:] = list(reversed(saved['innerBlocks'][1:]))
    out = migrate([saved])
    assert out['count'] == 1
    rows = out['blocks'][0]['innerBlocks'][1:]
    assert [r['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] for r in rows] == ['editor-2026', 'editor-0']
