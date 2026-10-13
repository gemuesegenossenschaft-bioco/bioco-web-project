"""Read-only installed Divi proof for a privately captured saved-shell plan.

Requires BIOCO_DIVI_NATIVE_SSH, BIOCO_DIVI_NATIVE_WP_PATH and
BIOCO_SHELL_WRAPPER_SNAPSHOT. Candidate PHP runs in memory. There are no renders,
enqueues, hosted files, cache changes, content writes or template assignments.
"""
import base64
import json
import os
import shlex
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[2]
HELPER = ROOT / 'wordpress/web/app/mu-plugins/bioco-import/includes/shell-wrapper-adoption.php'


def installed_probe():
    host = os.environ.get('BIOCO_DIVI_NATIVE_SSH')
    path = os.environ.get('BIOCO_DIVI_NATIVE_WP_PATH')
    snapshot_path = os.environ.get('BIOCO_SHELL_WRAPPER_SNAPSHOT')
    assert host and path and snapshot_path, 'An installed host/path and private snapshot are required.'
    snapshot = json.loads(Path(snapshot_path).read_text())
    candidate = HELPER.read_text().removeprefix('<?php').replace('bioco_shell_wrapper_', 'bioco_wrapper_probe_')
    code = "<?php\neval(base64_decode('" + base64.b64encode(candidate.encode()).decode() + "'));\n"
    code += "$snapshot=json_decode(base64_decode('" + base64.b64encode(json.dumps(snapshot).encode()).decode() + "'),true);\n"
    code += r'''
$styler='\ET\Builder\Packages\Module\Options\Element\ElementStyle';
if(!class_exists($styler)||!class_exists('ET\Builder\FrontEnd\BlockParser\BlockParser'))throw new RuntimeException('Installed Divi 5 APIs required.');
if(home_url('/')!==$snapshot['site']||ET\Builder\Packages\GlobalData\GlobalPreset::get_data()!==$snapshot['presets'])throw new RuntimeException('Live site or preset snapshot changed.');
if(get_option('bioco_release_marker')!==$snapshot['marker'])throw new RuntimeException('Held release marker changed.');
global $wpdb;
if($wpdb->get_row("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name='bioco_release_marker'",ARRAY_A)!==$snapshot['raw_release_marker'])throw new RuntimeException('Raw release marker changed.');
$templates=get_posts(['post_type'=>['et_template','et_theme_builder'],'post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']);
$assignments=[];foreach($templates as$post)$assignments[]=['ID'=>(int)$post->ID,'post_type'=>$post->post_type,'post_status'=>$post->post_status,'post_content'=>$post->post_content,'postmeta'=>get_post_meta($post->ID),'raw_post'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d",$post->ID),ARRAY_A)];
if($assignments!==$snapshot['assignments'])throw new RuntimeException('Live global assignments changed.');
$options=$wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ('et_divi_builder_global_presets_d5','et_divi_builder_global_presets_ng','et_divi_global_variables') ORDER BY option_name",ARRAY_A);
if($options!==$snapshot['guard_options'])throw new RuntimeException('Live preset option snapshot changed.');
function bioco_wrapper_probe_frontend($content){
 global $wp_current_filter;$wp_current_filter[]='et_builder_render_layout';
 try{return parse_blocks($content);}finally{array_pop($wp_current_filter);}
}
function bioco_wrapper_probe_nodes($blocks){return [$blocks[0],$blocks[0]['innerBlocks'][0],$blocks[0]['innerBlocks'][0]['innerBlocks'][0]];}
function bioco_wrapper_probe_saved($blocks){
 // Divi adds per-parse top-level storeInstance/orderIndex IDs. They are not
 // serialized attributes; retain every native ID inside attrs in this proof.
 foreach($blocks as&$block){
  $block=array_intersect_key($block,array_flip(['blockName','attrs','innerBlocks','innerHTML','innerContent']));
  $block['innerBlocks']=bioco_wrapper_probe_saved($block['innerBlocks']);
 }unset($block);return$blocks;
}
function bioco_wrapper_probe_css($blocks,$styler){
 $out=[];foreach(bioco_wrapper_probe_nodes($blocks)as$i=>$node)$out[]=$styler::style(['selector'=>'.wrapper-'.$i,'attrs'=>$node['attrs']['module']['decoration']??[],'returnType'=>'string']);return$out;
}
$out=[];
foreach($snapshot['records']as$source){
 $record=$source['record'];
 $live=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d",$record['ID']),ARRAY_A);
 if($live!==$source['raw_post'])throw new RuntimeException('Live complete raw source record changed.');
 $meta=$wpdb->get_results($wpdb->prepare("SELECT meta_id,post_id,meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$record['ID']),ARRAY_A);
 $stable=static fn(array $rows):array=>array_values(array_filter($rows,static fn(array $row):bool=>!in_array($row['meta_key'],bioco_wrapper_probe_volatile_meta(),true)));
 if($stable($meta)!==$stable($source['postmeta']))throw new RuntimeException('Live source metadata changed.');
 $context=['site'=>$snapshot['site'],'presets'=>$snapshot['presets'],'options'=>$snapshot['guard_options'],
  'meta'=>$source['postmeta'],'raw_post'=>$source['raw_post'],'assignments_sha256'=>hash('sha256',wp_json_encode($snapshot['assignments']))];
 $plan=bioco_wrapper_probe_plan($record,$snapshot['site'],$context);
 $preserved=parse_blocks($plan['after_content']);
 foreach($plan['report']as$change){
  if($change['action']!=='adopt')continue;
  $node=&$preserved[$change['path'][0]];
  foreach(array_slice($change['path'],1)as$index)$node=&$node['innerBlocks'][$index];
  unset($node['attrs']['module']['decoration'][$change['domain']]);
  if($node['attrs']['module']['decoration']===[])unset($node['attrs']['module']['decoration']);
  unset($node);
 }
 $parsed=bioco_wrapper_probe_frontend($plan['after_content']);
 $after_record=array_replace($record,['post_content'=>$plan['after_content']]);
 $after_context=$context;$after_context['raw_post']['post_content']=$after_record['post_content'];
 $again=bioco_wrapper_probe_plan($after_record,$snapshot['site'],$after_context);
 $authored=parse_blocks($record['post_content']);
 $authored[0]['innerBlocks'][0]['attrs']['module']['decoration']=[
  'sizing'=>['desktop'=>['value'=>['width'=>'75%','maxWidth'=>'900px']],'phone'=>['value'=>['width'=>'62%']]],
  'spacing'=>['desktop'=>['value'=>['padding'=>['left'=>'17px']]],'phone'=>['value'=>['padding'=>['left'=>'9px']]]]];
 $authored_record=array_replace($record,['post_content'=>serialize_blocks($authored)]);
 $authored_context=$context;$authored_context['raw_post']['post_content']=$authored_record['post_content'];
 $authored_plan=bioco_wrapper_probe_plan($authored_record,$snapshot['site'],$authored_context);
 $author_before=bioco_wrapper_probe_frontend($authored_record['post_content']);
 $author_after=bioco_wrapper_probe_frontend($authored_plan['after_content']);
 $preset=parse_blocks($record['post_content']);
 $preset[0]['innerBlocks'][0]['attrs']['modulePreset']=['bioco-aec435bda315c889'];
 $preset_record=array_replace($record,['post_content'=>serialize_blocks($preset)]);
 $preset_context=$context;$preset_context['raw_post']['post_content']=$preset_record['post_content'];
 $preset_plan=bioco_wrapper_probe_plan($preset_record,$snapshot['site'],$preset_context);
 $preset_before=bioco_wrapper_probe_frontend($preset_record['post_content']);
 $preset_after=bioco_wrapper_probe_frontend($preset_plan['after_content']);
 $out[]=['id'=>$record['ID'],'plan'=>$plan,'apply'=>bioco_wrapper_probe_sql($plan,$plan['fingerprint']),
  'restore'=>bioco_wrapper_probe_sql($plan,$plan['fingerprint'],true),'repeat_changed'=>$again['changed'],
  'all_source_attributes_content_and_structure_preserved'=>bioco_wrapper_probe_saved($preserved)===bioco_wrapper_probe_saved(parse_blocks($record['post_content'])),
  'parsed_wrappers'=>array_column(bioco_wrapper_probe_nodes($parsed),'attrs'),'css'=>bioco_wrapper_probe_css($parsed,$styler),
  'authored'=>['changed'=>$authored_plan['changed'],'before'=>bioco_wrapper_probe_nodes($author_before)[1]['attrs'],
   'after'=>bioco_wrapper_probe_nodes($author_after)[1]['attrs'],
   'before_css'=>bioco_wrapper_probe_css($author_before,$styler)[1],'after_css'=>bioco_wrapper_probe_css($author_after,$styler)[1]],
  'preset'=>['changed'=>$preset_plan['changed'],'before'=>bioco_wrapper_probe_nodes($preset_before)[1]['attrs'],
   'after'=>bioco_wrapper_probe_nodes($preset_after)[1]['attrs']]];
}
echo wp_json_encode(['parser'=>get_class(new ET\Builder\FrontEnd\BlockParser\BlockParser()),'layouts'=>$out]);
'''
    command = '/home/bioco/bin/wp --path=' + shlex.quote(path) + ' --user=1 eval ' + shlex.quote('eval("?>" . stream_get_contents(STDIN));')
    result = subprocess.run(['ssh', '-o', 'BatchMode=yes', host, command], input=code,
                            text=True, capture_output=True, timeout=60)
    assert result.returncode == 0, result.stderr
    assert not result.stderr.strip(), result.stderr
    proof = json.loads(result.stdout)
    # Private receipt contains authored content/SQL. Never write it into the repo.
    receipt = os.environ.get('BIOCO_SHELL_WRAPPER_RECEIPT')
    if receipt:
        destination = Path(receipt).resolve()
        assert not destination.is_relative_to(ROOT), 'The receipt must stay outside the public repository.'
        descriptor = os.open(destination, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
        with os.fdopen(descriptor, 'w') as stream:
            json.dump(proof, stream, ensure_ascii=False, indent=2)
    return proof


@pytest.fixture(scope='module')
def installed():
    return installed_probe()


def test_installed_parser_and_style_generator_accept_all_missing_wrapper_controls(installed):
    assert installed['parser'] == 'ET\\Builder\\FrontEnd\\BlockParser\\BlockParser'
    assert {row['id'] for row in installed['layouts']} == {299, 303}
    for row in installed['layouts']:
        assert row['plan']['changed'] == 6 and row['repeat_changed'] == 0
        assert row['all_source_attributes_content_and_structure_preserved']
        for attrs, css in zip(row['parsed_wrappers'], row['css']):
            decoration = attrs['module']['decoration']
            assert decoration['sizing']['desktop']['value'] == {'width': '100%', 'maxWidth': 'none'}
            assert 'width: 100%' in css and 'max-width: none' in css
            for side in ['top', 'right', 'bottom', 'left']:
                assert f'margin-{side}: 0px' in css and f'padding-{side}: 0px' in css
        assert row['plan']['copy_proposal']['post_status'] == 'draft'
        assert row['plan']['copy_proposal']['assignments'] == []


def test_installed_parser_preserves_authored_responsive_values_and_native_preset_ids(installed):
    for row in installed['layouts']:
        author = row['authored']
        assert author['changed'] == 4 and author['before'] == author['after']
        assert author['before_css'] == author['after_css']
        assert 'width: 75%' in author['after_css'] and 'width: 62%' in author['after_css']
        assert 'padding-left: 17px' in author['after_css'] and 'padding-left: 9px' in author['after_css']
        preset = row['preset']
        assert preset['changed'] == 4 and preset['before'] == preset['after']
        assert preset['after']['modulePreset'] == ['bioco-aec435bda315c889']
