#!/usr/bin/env python3
"""Fail closed when required PR coverage is absent, skipped or failed."""

import argparse
import json
from pathlib import Path
from xml.etree import ElementTree as ET


def check(policy, reports):
    required = {path.removesuffix('.py').replace('/', '.'): path
                for path in policy['required_modules']}
    exemptions = set(policy['operator_only_nodes'])
    outcomes = {}
    errors = []
    for report in reports:
        try:
            tree = ET.parse(report)
        except (OSError, ET.ParseError) as error:
            errors.append(f'missing or malformed report {report}: {error}')
            continue
        for case in tree.iter('testcase'):
            classname = case.get('classname', '')
            for module, path in required.items():
                if classname != module and not classname.startswith(module + '.'):
                    continue
                class_suffix = classname[len(module):].lstrip('.')
                name = case.get('name', '')
                node = path + '::' + (class_suffix.replace('.', '::') + '::' if class_suffix else '') + name
                status = ('failed' if case.find('failure') is not None or case.find('error') is not None
                          else 'skipped' if case.find('skipped') is not None else 'passed')
                outcomes.setdefault(module, {}).setdefault(node, set()).add(status)
    for module, path in required.items():
        cases = {node: statuses for node, statuses in outcomes.get(module, {}).items()
                 if node not in exemptions}
        if not cases:
            errors.append(f'missing required module: {path}')
        elif not any('passed' in statuses for statuses in cases.values()):
            errors.append(f'entire required module skipped or failed: {path}')
        for node, statuses in cases.items():
            if 'failed' in statuses:
                errors.append(f'failed required test: {node}')
            elif 'passed' not in statuses:
                errors.append(f'skipped required test: {node}')
    for node in sorted(exemptions):
        print(f'operator follow-up (not CI evidence): {node}')
    if errors:
        for error in errors:
            print(f'ERROR: {error}')
        return 1
    print(f'Required coverage passed: {len(required)} modules, {len(reports)} reports')
    return 0


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--policy', type=Path, default=Path(__file__).with_name('wordpress-required-tests.json'))
    parser.add_argument('reports', type=Path, nargs='+')
    args = parser.parse_args()
    return check(json.loads(args.policy.read_text()), args.reports)


if __name__ == '__main__':
    raise SystemExit(main())
