#!/usr/bin/env python3
"""Compile Divi editorial metadata from the shared ACF field definitions."""
import argparse
import copy
import json
import re
import subprocess
import sys
from pathlib import Path

CORE = Path(__file__).resolve().parents[1] / 'web/app/mu-plugins/bioco-core'
OUT = CORE / 'native-modules'
TEMPLATE = json.loads((OUT / 'contact-form/module.json').read_text())
GROUPS = [json.loads(p.read_text()) for p in (CORE / 'acf-json').glob('*.json')]
FIELDS = {f['key']: f for group in GROUPS for f in group['fields']}
COMPONENTS = re.findall(r"'([a-z_]+)' => 'bioco/([a-z-]+)'", (CORE / 'includes/dynamic-sections.php').read_text().split('function bioco_dynamic_marker_html')[0])
EVENT_COMPONENTS = {'events_feed', 'schnuppertage'}


def fields_of(fields):
    for field in fields:
        if field['type'] == 'clone':
            yield from fields_of([FIELDS[key] for key in field['clone']])
        else:
            yield field


def specification(field, component=None):
    kind = field['type']
    if component and kind not in {'wysiwyg', 'textarea', 'number', 'image', 'true_false', 'repeater', 'select', 'text'}:
        raise ValueError(f"Unsupported field type in {component}.{field['name']}: {kind}")
    spec = {'type': {'wysiwyg': 'richtext', 'textarea': 'textarea', 'number': 'number', 'image': 'int', 'true_false': 'toggle', 'repeater': 'rows'}.get(kind, 'text'), 'label': field['label']}
    if field.get('required'):
        spec['required'] = True
    if kind == 'select':
        spec['choices'] = field['choices']
        if field.get('multiple'):
            spec['type'] = 'choices'
    if kind == 'repeater':
        spec['row'] = field_schema(field['sub_fields'], component)
    return spec


def field_schema(fields, component=None):
    result = {}
    for field in fields_of(fields):
        name = field['name']
        if component and (not name or name == 'module' or name in result):
            raise ValueError(f'Duplicate or reserved field in {component}: {name}')
        result[name] = specification(field, component)
    return result


def check_event_compatibility(component, before, after):
    for name, spec in before.items():
        if name not in after:
            raise ValueError(f'Removed supported field in {component}: {name}')
        replacement = after[name]
        if (spec['type'] != replacement['type']
                or ('choices' not in spec and 'choices' in replacement)
                or set(spec.get('choices', {})) - set(replacement.get('choices', {}))
                or (not spec.get('required') and replacement.get('required'))):
            raise ValueError(f'Conflicting field contract in {component}: {name}')
        if spec['type'] == 'rows':
            check_event_compatibility(f'{component}.{name}', spec['row'], replacement['row'])
    for name in after.keys() - before.keys():
        if after[name].get('required'):
            raise ValueError(f'Conflicting new required field in {component}: {name}')


def build(components=None, *, check=False):
    if components is not None:
        unknown = set(components) - {component for component, _ in COMPONENTS}
        if unknown:
            raise ValueError('Unknown components: ' + ', '.join(sorted(unknown)))
    message_schema = json.loads(subprocess.check_output(['php', '-r', "define('ABSPATH', __DIR__); function add_action(...$args) {} require '" + str(CORE.parent / 'bioco-forms/messages.php') + "'; echo json_encode(bioco_forms_message_schema());"]))
    previous = json.loads((OUT / 'fields.json').read_text())
    schema = copy.deepcopy(previous) if components is not None else {}
    outputs = {}
    for component, slug in COMPONENTS:
        if components is not None and component not in components:
            continue
        group = next(g for g in GROUPS if g['key'] == 'group_bioco_block_' + component)
        strict_component = component if component in EVENT_COMPONENTS else None
        schema[component] = field_schema(group['fields'], strict_component)
        if strict_component:
            check_event_compatibility(component, previous.get(component, {}), schema[component])
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
        outputs[path] = json.dumps(meta, ensure_ascii=False, indent=2) + '\n'
    outputs[OUT / 'fields.json'] = json.dumps(schema, ensure_ascii=False, indent=2) + '\n'
    if check:
        drift = [str(path.relative_to(OUT)) for path, content in outputs.items()
                 if not path.is_file() or path.read_bytes() != content.encode('utf-8')]
        if drift:
            raise ValueError('Generated native metadata drift: ' + ', '.join(drift))
        return
    for path, content in outputs.items():
        path.parent.mkdir(exist_ok=True)
        path.write_text(content)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--components', nargs='+', help='Only rebuild these component names; retain other metadata')
    parser.add_argument('--check', action='store_true', help='Report generated drift without writing files')
    args = parser.parse_args()
    try:
        build(args.components, check=args.check)
    except ValueError as error:
        print(error, file=sys.stderr)
        sys.exit(1)
