"""Explicit read-only installed Divi FE parse/style gate for the cards preview.

Requires BIOCO_DIVI_NATIVE_SSH and BIOCO_DIVI_NATIVE_WP_PATH. Candidate helper
is evaluated in memory. No rendering, hosted file upload, SQL update or cache
invalidation. This is schema/style evidence; GUI save/reopen remains a separate
root-owned private-copy acceptance gate.
"""
import base64
import json
import os
import shlex
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[2]
HELPER = ROOT / 'wordpress/web/app/mu-plugins/bioco-import/includes/editorial-card-presentation-preview.php'


def probe():
    host = os.environ.get('BIOCO_DIVI_NATIVE_SSH')
    path = os.environ.get('BIOCO_DIVI_NATIVE_WP_PATH')
    assert host and path, 'Installed WordPress/Divi SSH host and path are mandatory for this explicit gate.'
    candidate = HELPER.read_text().removeprefix('<?php').replace("require_once __DIR__ . '/editorial-native-preview-values.php';", '')
    shared = (HELPER.parent / 'editorial-native-preview-values.php').read_text().removeprefix('<?php').replace("require_once __DIR__ . '/editorial-layouts.php';", '')
    candidate = shared + '\n' + candidate
    code = r'''
if(!function_exists('bioco_import_editorial_class'))require WPMU_PLUGIN_DIR.'/bioco-import/includes/editorial-layouts.php';
''' + "eval(base64_decode('" + base64.b64encode(candidate.encode()).decode() + "'));" + r'''
$post=get_page_by_path('wir',OBJECT,'page');
if(!$post || $post->post_status!=='publish')throw new RuntimeException('Published Wir capture required.');
[$candidate,$report]=bioco_editorial_cards_preview(parse_blocks($post->post_content));
global $wp_current_filter;
$wp_current_filter[]='et_builder_render_layout';
try{$parsed=parse_blocks(serialize_blocks($candidate));}finally{array_pop($wp_current_filter);}
$parts=[];
$visit=function($blocks,$owned=false)use(&$visit,&$parts){foreach($blocks as$b){
 if(($b['blockName']??'')==='divi/section')$owned=in_array(bioco_import_editorial_attribute($b,'id'),['hof_team','geisshof'],true);
 $attrs=$b['attrs']??[];
 if($owned && ($b['blockName']??'')==='divi/row' && ($attrs['module']['decoration']['layout']['desktop']['value']['display']??'')==='grid'){
  $parts[]=['kind'=>'grid','layout'=>$attrs['module']['decoration']['layout'],
   'css'=>ET\Builder\Packages\Module\Options\Layout\LayoutStyle::style(['selector'=>'.preview-grid','attr'=>$attrs['module']['decoration']['layout'],'render'=>['display'=>true],'returnType'=>'string'])];
 }
 if($owned && ($b['blockName']??'')==='divi/column' && ($attrs['module']['decoration']['layout']['desktop']['value']['display']??'')==='flex'){
  $parts[]=['kind'=>'card','css'=>ET\Builder\Packages\Module\Options\Layout\LayoutStyle::style(['selector'=>'.preview-card','attr'=>$attrs['module']['decoration']['layout'],'returnType'=>'string'])];
 }
 if($owned && ($b['blockName']??'')==='divi/heading'){
  $parts[]=['kind'=>'heading','css'=>ET\Builder\Packages\Module\Options\Element\ElementStyle::style(['selector'=>'.preview-heading h3','type'=>'element','attrs'=>$attrs['title']['decoration'],'returnType'=>'string'])];
 }
 if($owned && ($b['blockName']??'')==='divi/image' && isset($attrs['module']['advanced']['sizing']['desktop']['value']['aspectRatio'])){
  $parts[]=['kind'=>'image','sizing'=>$attrs['module']['advanced']['sizing'],
   'sizing_css'=>ET\Builder\Packages\ModuleLibrary\Image\Styles\Sizing\SizingStyle::style(['selector'=>'.preview-image','imageSelector'=>'.preview-image .et_pb_image_wrap img','attr'=>$attrs['module']['advanced']['sizing'],'returnType'=>'string']),
   'fit_css'=>ET\Builder\Packages\Module\Options\Fit\FitStyle::style(['selector'=>'.preview-image .et_pb_image_wrap img','attr'=>$attrs['image']['decoration']['fit'],'returnType'=>'string']),
   'border_css'=>ET\Builder\Packages\Module\Options\Border\BorderStyle::style(['selector'=>'.preview-image .et_pb_image_wrap','attr'=>$attrs['image']['decoration']['border'],'returnType'=>'string']),
   'spacing_css'=>ET\Builder\Packages\ModuleLibrary\Image\Styles\Spacing\SpacingStyle::style(['selector'=>'.preview-image','attr'=>$attrs['module']['advanced']['spacing'],'important'=>['desktop'=>['value'=>['margin'=>true]]],'returnType'=>'string']),
   'fullwidth_css'=>ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle::style(['selector'=>'.preview-image img, .preview-image .et_pb_image_wrap','attr'=>$attrs['module']['advanced']['sizing'],'declarationFunction'=>[ET\Builder\Packages\ModuleLibrary\Image\ImageModule::class,'fullwidth_image_style_declaration'],'returnType'=>'string'])];
 }
 $visit($b['innerBlocks']??[],$owned);
}};$visit($parsed);
echo wp_json_encode(['site'=>home_url('/'),'report'=>$report,'parts'=>$parts]);
'''
    command = '/home/bioco/bin/wp --path=' + shlex.quote(path) + ' --user=1 eval ' + shlex.quote(
        "eval(base64_decode('" + base64.b64encode(code.encode()).decode() + "'));")
    result = subprocess.run(['ssh', host, command], text=True, capture_output=True, timeout=60)
    assert result.returncode == 0, result.stderr
    assert not result.stderr.strip(), result.stderr
    return json.loads(result.stdout)


@pytest.fixture(scope='module')
def installed():
    return probe()


def test_actual_saved_wir_preview_has_two_native_grids(installed):
    assert installed['report'] == {'cards-grid': 1, 'gallery-strip': 1, 'refused': []}
    grids = [p for p in installed['parts'] if p['kind'] == 'grid']
    assert len(grids) == 2
    for part, count in zip(grids, ['3', '4']):
        assert part['layout']['desktop']['value']['gridColumnCount'] == count
        assert part['layout']['phone']['value']['gridColumnCount'] == '1'
        assert 'display: grid' in part['css']
        assert 'grid-template-columns: repeat(var(--column-count), minmax(0, 1fr))' in part['css']
        assert '--horizontal-gap: 32px' in part['css'] and '--vertical-gap: 32px' in part['css']
        assert '@media' in part['css'] and '--column-count: 1' in part['css']


def test_image_crop_fit_radius_survive_real_frontend_parse_and_image_styles(installed):
    images = [p for p in installed['parts'] if p['kind'] == 'image']
    assert len(images) == 7
    for index, part in enumerate(images):
        assert part['sizing']['desktop']['value']['aspectRatio'] == {'width': '4', 'height': '3'}
        assert '.preview-image .et_pb_image_wrap img' in part['sizing_css']
        assert 'aspect-ratio: 4 / 3' in part['sizing_css']
        assert 'height: auto' in part['sizing_css']
        assert 'object-fit: cover' in part['fit_css']
        assert 'width: 100%' in part['fullwidth_css']
        assert f'margin-bottom: {"9.27%" if index < 3 else "0px"} !important' in part['spacing_css']
        if index < 3:
            assert 'margin-bottom: 30px !important' in part['spacing_css']
        for corner in ['top-left', 'top-right', 'bottom-left', 'bottom-right']:
            assert f'border-{corner}-radius: {"16px" if index < 3 else "24px"}' in part['border_css']


def test_native_flex_card_does_not_add_divi_module_gutter(installed):
    cards = [p for p in installed['parts'] if p['kind'] == 'card']
    assert len(cards) == 3
    for part in cards:
        assert '--horizontal-gap: 0px' in part['css']
        assert '--vertical-gap: 0px' in part['css']
        assert 'flex-direction: column' in part['css']


def test_actual_heading_native_font_attributes_emit_the_measured_baseline(installed):
    headings = [p for p in installed['parts'] if p['kind'] == 'heading']
    assert len(headings) == 3
    for part in headings:
        assert 'font-size: 20px' in part['css']
        assert 'font-weight: 500' in part['css']
        assert 'line-height: 1em' in part['css']
        assert 'color: #333333' in part['css']
