#!/usr/bin/env python3
"""Compile Divi editorial metadata from the shared ACF field definitions."""
import copy
import json
import re
import subprocess
from pathlib import Path

CORE = Path(__file__).resolve().parents[1] / 'web/app/mu-plugins/bioco-core'
OUT = CORE / 'native-modules'
TEMPLATE = json.loads((OUT / 'contact-form/module.json').read_text())
GROUPS = [json.loads(p.read_text()) for p in (CORE / 'acf-json').glob('*.json')]
FIELDS = {f['key']: f for group in GROUPS for f in group['fields']}
COMPONENTS = re.findall(r"'([a-z_]+)' => 'bioco/([a-z-]+)'", (CORE / 'includes/dynamic-sections.php').read_text().split('function bioco_dynamic_marker_html')[0])


def fields_of(fields):
    for field in fields:
        if field['type'] == 'clone':
            yield from fields_of([FIELDS[key] for key in field['clone']])
        else:
            yield field


def specification(field):
    kind = field['type']
    spec = {'type': {'wysiwyg': 'richtext', 'textarea': 'textarea', 'number': 'number', 'image': 'int', 'true_false': 'toggle', 'repeater': 'rows'}.get(kind, 'text'), 'label': field['label']}
    if field.get('required'):
        spec['required'] = True
    if kind == 'select':
        spec['choices'] = field['choices']
        if field.get('multiple'):
            spec['type'] = 'choices'
    if kind == 'repeater':
        spec['row'] = {f['name']: specification(f) for f in fields_of(field['sub_fields'])}
    return spec


def build():
    message_schema = json.loads(subprocess.check_output(['php', '-r', "define('ABSPATH', __DIR__); function add_action(...$args) {} require '" + str(CORE.parent / 'bioco-forms/messages.php') + "'; echo json_encode(bioco_forms_message_schema());"]))
    schema = {}
    for component, slug in COMPONENTS:
        group = next(g for g in GROUPS if g['key'] == 'group_bioco_block_' + component)
        schema[component] = {f['name']: specification(f) for f in fields_of(group['fields'])}
        meta = {'name': 'bioco-divi/' + slug, 'title': group['title'], 'titles': group['title'], 'category': 'module', 'moduleIcon': TEMPLATE['moduleIcon'], 'moduleClassName': 'bioco_' + component, 'moduleOrderClassName': 'bioco_' + component, 'attributes': {'module': copy.deepcopy(TEMPLATE['attributes']['module'])}, 'settings': {'content': 'auto', 'design': 'auto', 'advanced': 'auto', 'groups': {'contentFields': {'panel': 'content', 'priority': 10, 'groupName': 'contentFields', 'multiElements': True, 'component': {'name': 'divi/composite', 'props': {'groupLabel': 'Inhalt', 'preset': 'content'}}}}}, 'customCssFields': {}}
        for priority, (name, spec) in enumerate(schema[component].items(), 1):
            shared_message = name in message_schema.get(slug, [])
            control = 'divi/richtext' if spec['type'] == 'richtext' else 'divi/text' if spec['type'] == 'text' and not spec.get('choices') else 'bioco/' + component + '-' + name
            item = {'groupSlug': 'contentFields', 'attrName': name + '.innerContent', 'label': spec['label'], 'category': 'basic_option', 'priority': priority * 10, 'render': True, 'features': {'responsive': False, 'hover': False, 'sticky': False, 'preset': 'content'}, 'component': {'name': control, 'type': 'field'}}
            meta['attributes'][name] = {'type': 'object', 'selector': '{{selector}}', 'settings': {'innerContent': {'groupType': 'group-item', 'item': item}}}
            if shared_message:
                del meta['attributes'][name]['settings']
            if spec['type'] == 'richtext':
                meta['attributes'][name]['allowHtml'] = True
        if slug in message_schema:
            meta['attributes']['messageSettings'] = {'type': 'object', 'selector': '{{selector}}', 'settings': {'innerContent': {'groupType': 'group-item', 'item': {'groupSlug': 'contentFields', 'attrName': 'messageSettings.innerContent', 'label': 'Formularmeldungen', 'priority': 999, 'render': True, 'component': {'name': 'bioco/form-messages', 'type': 'field'}}}}}
        path = OUT / slug / 'module.json'
        path.parent.mkdir(exist_ok=True)
        path.write_text(json.dumps(meta, ensure_ascii=False, indent=2) + '\n')
    (OUT / 'fields.json').write_text(json.dumps(schema, ensure_ascii=False, indent=2) + '\n')


if __name__ == '__main__':
    build()
