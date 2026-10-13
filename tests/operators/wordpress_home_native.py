"""Explicit read-only installed Divi frontend parser/style test.

Requires SSH host/path and exact accepted SHA. Candidate helper is evaluated in
memory. No hosted file upload, rendering, save, SQL, option or cache writes.
Native GUI save/reopen is a separate root-owned draft gate.
"""
import base64
import hashlib
import json
import os
import shlex
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[2]
HELPER = ROOT / 'wordpress/web/app/mu-plugins/bioco-import/includes/home-presentation-preview.php'


def probe():
    host = os.environ.get('BIOCO_DIVI_NATIVE_SSH')
    path = os.environ.get('BIOCO_DIVI_NATIVE_WP_PATH')
    expected = os.environ.get('BIOCO_HOME_ACCEPTED_SHA')
    assert host and path and expected and len(expected) == 40, 'Explicit installed SSH host/path and accepted SHA are required.'
    candidate = HELPER.read_text().removeprefix('<?php').replace("require_once __DIR__ . '/editorial-layouts.php';", '')
    code = "eval(base64_decode('" + base64.b64encode(candidate.encode()).decode() + "'));" + r'''
if(home_url('/')!=='https://staging.bioco.ch/' || get_current_user_id()!==1)throw new RuntimeException('Wrong readonly site/actor.');
$marker=get_option('bioco_release_marker');if(is_string($marker))$marker=json_decode($marker,true);
if(($marker['commit']??'')!=='EXPECTED_SHA'||($marker['verify']??'')!=='passed'||($marker['smoke']??'')!=='passed')throw new RuntimeException('Accepted marker changed.');
if(!function_exists('bioco_import_editorial_class'))require WPMU_PLUGIN_DIR.'/bioco-import/includes/editorial-layouts.php';
global $wpdb;
$before=$wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE ID=5",ARRAY_A);
$meta=$wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id=5 ORDER BY meta_id",ARRAY_A);
if(!$before||$before['post_status']!=='publish'||(int)get_option('page_on_front')!==5)throw new RuntimeException('Published home required.');
$blocks=parse_blocks($before['post_content']);[$candidate,$report]=bioco_home_presentation_preview($blocks);
global $wp_current_filter;$wp_current_filter[]='et_builder_render_layout';
try{$parsed=parse_blocks(serialize_blocks($candidate));}finally{array_pop($wp_current_filter);}
[$repeat,$repeatReport]=bioco_home_presentation_preview($candidate);
$expectedBlocks=$blocks;foreach([0,1,2,4]as$i)$expectedBlocks[0]['innerBlocks'][$i]=$candidate[0]['innerBlocks'][$i];
if($candidate!==$expectedBlocks||$repeat!==$candidate)throw new RuntimeException('Scope or repeat preservation failed.');
$elements=static fn(array $attrs,string $selector,array $extra=[]):string=>ET\Builder\Packages\Module\Options\Element\ElementStyle::style(array_merge(['selector'=>$selector,'type'=>'element','attrs'=>$attrs,'returnType'=>'string'],$extra));
$parts=[];$visit=function(array $items,array $path=[],array $parentLayout=[])use(&$visit,&$parts,$elements){foreach($items as$i=>$b){
 $p=array_merge($path,[$i]);$a=$b['attrs']??[];$part=['path'=>$p,'name'=>$b['blockName'],'attrs'=>$a];
 if(count($p)>1&&in_array($p[1],[0,1,2,4],true)){
  $parentDisplay=$parentLayout['desktop']['value']['display']??'block';
  $part['module_css']=$elements($a['module']['decoration']??[],'.native-module',['isParentFlexLayout'=>$parentDisplay==='flex','isParentGridLayout'=>$parentDisplay==='grid']);
  $part['layout_css']=ET\Builder\Packages\Module\Options\Layout\LayoutStyle::style(['selector'=>'.native-layout','attr'=>$a['module']['decoration']['layout']??[],'render'=>['display'=>true],'returnType'=>'string']);
  if(($b['blockName']??'')==='divi/heading'){
   $part['heading_css']=$elements($a['title']['decoration']??[],'.native-heading h2');
   $part['custom_css']=ET\Builder\Packages\Module\Options\Css\CssStyle::style(['selector'=>'.native-heading','attr'=>$a['css']??[],'cssFields'=>['mainElement'=>['selectorSuffix'=>' h1, h2, h3, h4, h5, h6']],'returnType'=>'string']);
  }
  if(($b['blockName']??'')==='divi/text'){
   $part['text_css']=$elements($a['content']['decoration']??[],'.native-text');
   $part['custom_css']=ET\Builder\Packages\Module\Options\Css\CssStyle::style(['selector'=>'.native-text','attr'=>$a['css']??[],'returnType'=>'string']);
  }
  if(($b['blockName']??'')==='divi/button')$part['button_css']=$elements($a['button']['decoration']??[],'.native-button');
  if(($b['blockName']??'')==='divi/image'){
   $part['sizing_css']=ET\Builder\Packages\ModuleLibrary\Image\Styles\Sizing\SizingStyle::style(['selector'=>'.native-image','imageSelector'=>'.native-image img','attr'=>$a['module']['advanced']['sizing']??[],'returnType'=>'string']);
   $part['fit_css']=ET\Builder\Packages\Module\Options\Fit\FitStyle::style(['selector'=>'.native-image img','attr'=>$a['image']['decoration']['fit']??[],'returnType'=>'string']);
   $part['border_css']=ET\Builder\Packages\Module\Options\Border\BorderStyle::style(['selector'=>'.native-image .et_pb_image_wrap','attr'=>$a['image']['decoration']['border']??[],'returnType'=>'string']);
   $part['fullwidth_css']=ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle::style(['selector'=>'.native-image img,.native-image .et_pb_image_wrap','attr'=>$a['module']['advanced']['sizing']??[],'declarationFunction'=>[ET\Builder\Packages\ModuleLibrary\Image\ImageModule::class,'fullwidth_image_style_declaration'],'returnType'=>'string']);
  }
  $parts[]=$part;
 }
 $visit($b['innerBlocks']??[],$p,$a['module']['decoration']['layout']??[]);
}};$visit($parsed);
$nativeModuleStyle=static function(string $kind,array $attrs):string{
 $name='divi/'.strtolower($kind);$metadata=ET\Builder\Packages\ModuleLibrary\ModuleRegistration::get_core_module_metadata($name);
 if(!is_array($metadata))throw new RuntimeException('Installed native metadata is unavailable.');
 $style='ET\\Builder\\FrontEnd\\Module\\Style';$style::reset();$style::set_group_style('module');
 $elements=new ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements(['id'=>'home-native-style-proof','name'=>$name,'moduleAttrs'=>$attrs,'moduleMetadata'=>$metadata,'orderIndex'=>0,'storeInstance'=>0]);
 $elements->set_style_group('module');$elements->set_order_class('.native-module');$elements->set_base_order_class('.native-module');$elements->set_wrapper_order_class('.native-wrapper');$elements->set_base_wrapper_order_class('.native-wrapper');
 $class='ET\\Builder\\Packages\\ModuleLibrary\\'.$kind.'\\'.$kind.'Module';
 $class::module_styles(['attrs'=>$attrs,'elements'=>$elements,'styleGroup'=>'module','id'=>'home-native-style-proof','name'=>$name,'orderIndex'=>0,'storeInstance'=>0,'orderClass'=>'.native-module','baseOrderClass'=>'.native-module','wrapperOrderClass'=>'.native-wrapper']);
 $data=$style::get_style_array();return $style::render_by_styles_data($data,'default').$style::render_by_styles_data($data,'critical');
};
foreach($parts as&$part){
 if($part['name']==='divi/button'){
  $part['native_button_module_css']=$nativeModuleStyle('Button',$part['attrs']);
  $unsupported=$part['attrs'];$spacing=$unsupported['module']['decoration']['spacing']['desktop']['value']['padding']??[];unset($unsupported['module']['decoration']['spacing']['desktop']['value']['padding']);$unsupported['button']['decoration']['spacing']['desktop']['value']['padding']=$spacing;
  $part['unsupported_button_spacing_css']=$nativeModuleStyle('Button',$unsupported);
 }
 if($part['path']===[0,4,0,0])$part['native_cta_column_css']=$nativeModuleStyle('Column',$part['attrs']);
}unset($part);
$again=$wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE ID=5",ARRAY_A);
$againMeta=$wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id=5 ORDER BY meta_id",ARRAY_A);
if($before!==$again||$meta!==$againMeta)throw new RuntimeException('Readonly source changed.');
echo wp_json_encode(['site'=>home_url('/'),'marker'=>$marker,'report'=>$report,'repeat_report'=>$repeatReport,'before_sha256'=>hash('sha256',$before['post_content']),'after_sha256'=>hash('sha256',serialize_blocks($candidate)),'scope_preserved'=>true,'source_preserved'=>true,'parts'=>$parts]);
'''.replace('EXPECTED_SHA', expected)
    command = '/home/bioco/bin/wp --path=' + shlex.quote(path) + ' --user=1 eval ' + shlex.quote(
        "eval(base64_decode('" + base64.b64encode(code.encode()).decode() + "'));")
    result = subprocess.run(['ssh', host, command], capture_output=True, text=True, timeout=60)
    assert result.returncode == 0, result.stderr
    assert not result.stderr.strip(), result.stderr
    value = json.loads(result.stdout)
    value['helper_sha256'] = hashlib.sha256(HELPER.read_bytes()).hexdigest()
    return value


@pytest.fixture(scope='module')
def installed():
    return probe()


def part(installed, path):
    return next(p for p in installed['parts'] if p['path'] == path)


def test_actual_home_native_preview_scope_and_repeat(installed):
    assert installed['report'] == {'hero': 1, 'feature': 2, 'cta': 1, 'refused': []}
    assert installed['repeat_report'] == {'hero': 0, 'feature': 0, 'cta': 0, 'refused': []}
    assert installed['scope_preserved'] and installed['source_preserved']


def test_hero_and_feature_native_container_have_matching_effective_edges(installed):
    for path in [[0, 0], [0, 1], [0, 2]]:
        css = part(installed, path)['module_css']
        assert 'width: 100%' in css
        assert 'padding-left: 6vw' in css
        row = part(installed, path + [0])['module_css']
        assert 'width: 100%' in row
        assert 'max-width: var(--gvid-bioco-1ca8a232395ae962)' in row
    caption = part(installed, [0, 0, 0, 0])['module_css']
    assert 'padding-left: 184.4px' in caption and 'padding-right: 184.4px' in caption
    assert 'padding-left: 24px' in caption and 'padding-right: 24px' in caption


def test_normal_image_native_crop_is_not_lost_in_frontend_parse(installed):
    hero = part(installed, [0, 0, 0, 0, 0])
    assert 'height: 585px' in hero['sizing_css'] and 'height: 552px' in hero['sizing_css']
    assert 'object-fit: cover' in hero['fit_css']
    assert 'width: 100%' in hero['fullwidth_css']
    for path in [[0, 1, 0, 1, 0], [0, 2, 0, 0, 0]]:
        image = part(installed, path)
        assert 'aspect-ratio: 4 / 3' in image['sizing_css']
        assert 'height: 300px' in image['sizing_css']
        assert 'object-fit: cover' in image['fit_css']
        assert 'border-top-left-radius: 24px' in image['border_css']


def test_decorative_native_gradient_and_visible_heading_font_emit(installed):
    overlay = part(installed, [0, 0, 0, 0, 1])
    assert 'linear-gradient(180deg' in overlay['module_css']
    assert 'rgba(0,0,0,0.32) 0%' in overlay['module_css']
    assert 'rgba(0,0,0,0) 65%' in overlay['module_css']
    assert 'position: absolute' in overlay['module_css'] and 'z-index: 1' in overlay['module_css']
    assert 'pointer-events:none' in overlay['custom_css'].replace(' ', '')
    for path in [[0, 1, 0, 0, 0], [0, 2, 0, 1, 0]]:
        title = part(installed, path)
        assert 'font-size: 26px' in title['heading_css'] and 'font-size: 24px' in title['heading_css']
        assert 'font-weight: 500' in title['heading_css']
        assert 'padding-bottom:0px' in title['custom_css'].replace(' ', '')


def test_native_order_grid_and_button_border_spacing_emit(installed):
    grid = part(installed, [0, 1, 0])
    assert 'display: grid' in grid['layout_css']
    assert '--column-count: 2' in grid['layout_css'] and '--column-count: 1' in grid['layout_css']
    assert '--horizontal-gap: 64px' in grid['layout_css'] and '--horizontal-gap: 24px' in grid['layout_css']
    reordered = part(installed, [0, 2, 0, 1])
    assert 'order: -1' in reordered['module_css']
    for path in [[0, 1, 0, 0, 2], [0, 4, 0, 0, 2], [0, 4, 0, 0, 3]]:
        css = part(installed, path)['button_css']
        assert 'font-size: 16px' in css
        assert 'border-top-left-radius: 28px' in css
        native_css = part(installed, path)['native_button_module_css']
        assert 'padding-top:12px!important' in native_css.replace(' ', '')
        assert 'padding-left:24px!important' in native_css.replace(' ', '')
        assert 'min-height: 52px' in css


def test_real_button_module_rejects_unsupported_group_and_column_emits_phone_stack(installed):
    for path in [[0, 1, 0, 0, 2], [0, 4, 0, 0, 2], [0, 4, 0, 0, 3]]:
        button = part(installed, path)
        css = button['native_button_module_css'].replace(' ', '')
        assert '.native-wrapper.native-module' in css or '.native-wrapper .native-module' in button['native_button_module_css']
        assert 'padding-left:24px!important' in css and 'padding-top:12px!important' in css
        unsupported = button['unsupported_button_spacing_css'].replace(' ', '')
        assert 'padding-left:24px' not in unsupported and 'padding-top:12px' not in unsupported
    css = part(installed, [0, 4, 0, 0])['native_cta_column_css']
    assert 'flex-direction: column' in css
    assert 'flex-wrap: nowrap' in css
    assert 'align-items: flex-start' in css
    assert '--vertical-gap: 0px' in css or 'row-gap: 0px' in css
