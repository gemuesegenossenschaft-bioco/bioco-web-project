"""Explicit installed-Divi frontend parser/style regression; never a CI mock.

BIOCO_DIVI_NATIVE_WP_PATH and BIOCO_DIVI_NATIVE_SSH are required. The candidate
helpers are evaluated in memory alongside the installed WordPress/Divi APIs.
No render, enqueue, database update, CSS cache flush or hosted file write occurs.
Set BIOCO_NUMERIC_SOURCE_REF to a git ref to reproduce the old helper behavior.
"""
import base64
import json
import os
import shlex
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[2]
INCLUDES = 'wordpress/web/app/mu-plugins/bioco-import/includes/'


def source(name):
    ref = os.environ.get('BIOCO_NUMERIC_SOURCE_REF')
    if ref:
        return subprocess.run(['git', 'show', f'{ref}:{INCLUDES}{name}'], cwd=ROOT,
                              text=True, capture_output=True, check=True).stdout
    return (ROOT / INCLUDES / name).read_text()


def installed_probe():
    host = os.environ.get('BIOCO_DIVI_NATIVE_SSH')
    path = os.environ.get('BIOCO_DIVI_NATIVE_WP_PATH')
    assert host and path, 'The explicit operator gate requires an installed WordPress/Divi host and path.'
    article = source('article-back-button.php').removeprefix('<?php')
    article = article.replace("require_once __DIR__ . '/divi-blocks.php';", '')
    article = article.replace('bioco_import_article_back_button', 'bioco_numeric_probe_article')
    forms = source('form-adoption.php')
    forms = forms[forms.index('function bioco_import_form_design('):forms.index('\nfunction bioco_import_form_layout(')]
    forms = forms.replace('bioco_import_form_design', 'bioco_numeric_probe_forms')
    candidate = base64.b64encode((article + '\n' + forms).encode()).decode()
    code = "eval(base64_decode('" + candidate + "'));" + r'''
$styler='\\ET\\Builder\\Packages\\Module\\Options\\Element\\ElementStyle';
if(!class_exists($styler)||!class_exists('ET\\Builder\\FrontEnd\\BlockParser\\BlockParser'))throw new RuntimeException('Installed Divi 5 APIs required.');
$field=bioco_form_layout_part('form-field',['field'=>'contact.name','label'=>'Native name']);
$action=bioco_form_layout_part('form-action',['action'=>'submit','label'=>'Native submit']);
$input=[bioco_numeric_probe_article(),...bioco_numeric_probe_forms([$field,$action])];
global $wp_current_filter;
$wp_current_filter[]='et_builder_render_layout';
try{$parsed=parse_blocks(serialize_blocks($input));}finally{array_pop($wp_current_filter);}
$output=[];
foreach($parsed as$i=>$block){
 $key=$i===0?'button':'control';
 $decoration=$block['attrs'][$key]['decoration'];
 $module=$block['attrs']['module']['decoration']??[];
 $output[]=['radius'=>$decoration['border']['desktop']['value']['radius']??[],
  'control_css'=>$styler::style(['selector'=>'.native-'.$i,'type'=>$i===0?'button':'element','attrs'=>$decoration,'returnType'=>'string']),
  'module_css'=>$styler::style(['selector'=>'.native-module-'.$i,'attrs'=>$module,'returnType'=>'string'])];
}
echo wp_json_encode(['parser'=>get_class(new ET\Builder\FrontEnd\BlockParser\BlockParser()),'parts'=>$output]);
'''
    # cPanel's PHP launcher treats multiline arguments as filenames before WP-CLI.
    code = "eval(base64_decode('" + base64.b64encode(code.encode()).decode() + "'));"
    command = '/home/bioco/bin/wp --path=' + shlex.quote(path) + ' --user=1 eval ' + shlex.quote(code)
    result = subprocess.run(['ssh', host, command], text=True, capture_output=True, timeout=60)
    assert result.returncode == 0, result.stderr
    assert not result.stderr.strip(), result.stderr
    return json.loads(result.stdout)


@pytest.fixture(scope='module')
def installed():
    return installed_probe()


def test_native_article_radius_survives_frontend_parse_and_real_style_generation(installed):
    part = installed['parts'][0]
    token = 'var(--gvid-bioco-185ab2cb8778665c)'
    assert set(part['radius']) == {'topLeft', 'topRight', 'bottomLeft', 'bottomRight'}, part
    assert set(part['radius'].values()) == {token}, part
    for corner in ['top-left', 'top-right', 'bottom-left', 'bottom-right']:
        assert f'border-{corner}-radius: {token}' in part['control_css'], part
    assert 'var(--gcid-bioco-d3856b51b53d792d)' in part['control_css'], part
    assert 'min-height: 44px' in part['control_css'], part


def test_native_form_numeric_defaults_survive_frontend_parse_and_real_style_generation(installed):
    field, action = installed['parts'][1:]
    for part in [field, action]:
        assert set(part['radius']) == {'topLeft', 'topRight', 'bottomLeft', 'bottomRight'}, part
        for key, corner in [('topLeft','top-left'), ('topRight','top-right'),
                            ('bottomLeft','bottom-left'), ('bottomRight','bottom-right')]:
            token = part['radius'][key]
            assert token.startswith('var(--gvid-bioco-') and token.endswith(')'), part
            assert f'border-{corner}-radius: {token}' in part['control_css'], part
        assert 'padding-left: var(--gvid-bioco-' in part['control_css'], part
        assert 'padding-right: var(--gvid-bioco-' in part['control_css'], part
        assert 'var(--gcid-bioco-' in part['control_css'], part
    assert 'font-size: var(--gvid-bioco-c414983b8148bf2f)' in field['control_css'], field
    assert 'margin-bottom: var(--gvid-bioco-' in field['module_css'], field
