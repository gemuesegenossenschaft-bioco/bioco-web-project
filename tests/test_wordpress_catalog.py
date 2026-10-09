"""Catalog admin and migration semantics, with only WordPress storage stubbed."""
import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CONTENT = ROOT / 'wordpress/web/app/mu-plugins/bioco-content'


def catalog(scenario):
    source = r'''
define('ABSPATH','/');
$actions=[];$types=[];$posts=[];$meta=[];$options=[];$writes=0;
function add_action($hook,$fn,...$rest){$GLOBALS['actions'][$hook][]=$fn;}
function __($s,...$rest){return $s;}
function register_post_type($key,$config){$GLOBALS['types'][$key]=$config;}
function get_option($key,$fallback=false){return $GLOBALS['options'][$key]??$fallback;}
function add_option($key,$value,...$args){if(array_key_exists($key,$GLOBALS['options']))return false;$GLOBALS['options'][$key]=$value;return true;}
function update_option($key,$value,...$args){$GLOBALS['options'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['options'][$key]);}
function get_posts($args){return array_values(array_filter($GLOBALS['posts'],fn($p)=>$p->post_type===$args['post_type']&&($args['post_status']==='any'||in_array($p->post_status,(array)$args['post_status'],true))));}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;return true;}
function wp_insert_post($data,$error=false){$id=count($GLOBALS['posts'])+1;$GLOBALS['posts'][$id]=(object)($data+['ID'=>$id]);$GLOBALS['writes']++;foreach($data['meta_input']??[] as $key=>$value)update_post_meta($id,$key,$value);return $id;}
function is_wp_error($v){return false;}
require %s;
foreach($actions['init']??[] as $fn)$fn();
%s
'''
    code = source % (json.dumps(str(CONTENT/'bioco-content.php')), scenario)
    result = subprocess.run(['php','-r',code],text=True,capture_output=True,check=True)
    assert not result.stderr, result.stderr
    return json.loads(result.stdout)


def test_catalog_has_dedicated_admin_menus_and_fallback_before_migration():
    result=catalog("echo json_encode(['types'=>$types,'rows'=>bioco_catalog_rows('vegetables')]);")
    assert {'bioco_vegetable','bioco_depot','bioco_group'} <= result['types'].keys()
    for key in ['bioco_vegetable','bioco_depot']:
        assert result['types'][key]['show_ui'] is True
        assert result['types'][key]['public'] is False
    assert result['rows'] is None


def test_catalog_dry_run_creates_nothing():
    result=catalog("$r=bioco_catalog_seed('vegetables',false);echo json_encode(['result'=>$r,'posts'=>$posts,'options'=>$options]);")
    assert result['posts']==[] and result['options']==[]
    assert result['result']['created']==len(json.loads((CONTENT/'seeds/vegetables.json').read_text()))


def test_catalog_edits_and_deletions_survive_repeated_import():
    result=catalog("""
bioco_catalog_seed('vegetables',true);
$count=$writes;
$first=array_key_first($posts);$posts[$first]->post_title='Editor vegetable';
update_post_meta($first,'vegetable_months',[12]);
foreach($posts as $id=>$post)if($id!==$first)$post->post_status='trash';
bioco_catalog_seed('vegetables',true);
$rows=bioco_catalog_rows('vegetables');
$posts[$first]->post_status='trash';
bioco_catalog_seed('vegetables',true);
echo json_encode(['before'=>$count,'after'=>$writes,'rows'=>$rows,'empty'=>bioco_catalog_rows('vegetables')]);
""")
    assert result['before']==result['after']
    assert result['rows']==[{'name':'Editor vegetable','months':[12]}]
    assert result['empty']==[]


def test_depot_catalog_returns_the_existing_complete_rows():
    result=catalog("bioco_catalog_seed('depots',true);echo json_encode(bioco_catalog_rows('depots'));")
    seed=json.loads((ROOT/'wordpress/content-seed/standorte-depots.json').read_text())
    expected=next(s['section_config']['locations'] for s in seed['sections'] if s.get('section_component')=='depot_map')
    assert result==expected


def test_vegetable_snapshot_preserves_every_existing_name_and_month():
    source=(ROOT/'frontend/components/Saisonkalender.tsx').read_text()
    body=source[source.index('const SEASONAL_DATA'):source.index('export function')]
    expected={}
    for month,(_,values) in enumerate(re.findall(r'([^\s:,]+):\s*\[([^]]*)\]',body),1):
        for name in re.findall(r"'([^']+)'",values):expected.setdefault(name,[]).append(month)
    actual=json.loads((CONTENT/'seeds/vegetables.json').read_text())
    assert {row['name']:row['months'] for row in actual}==expected
    assert len(actual)>35


def test_existing_editor_catalog_is_adopted_without_importing_defaults():
    result=catalog("""
wp_insert_post(['post_type'=>'bioco_depot','post_status'=>'draft','post_title'=>'Editor depot']);
$r=bioco_catalog_seed('depots',true);
echo json_encode(['result'=>$r,'writes'=>$writes,'rows'=>bioco_catalog_rows('depots')]);
""")
    assert result['writes']==1
    assert result['result']['adopted']==1 and result['result']['created']==0
    assert result['rows']==[]


def test_partial_catalog_import_resumes_without_duplicate_or_overwrite():
    result=catalog("""
$seed=json_decode(file_get_contents(__DIR__.'/wordpress/web/app/mu-plugins/bioco-content/seeds/vegetables.json'),true);
$options['bioco_catalog_pending_vegetables']=true;
wp_insert_post(['post_type'=>'bioco_vegetable','post_status'=>'trash','post_title'=>'Edited before retry','meta_input'=>['_bioco_catalog_source'=>hash('sha256','vegetables:'.$seed[0]['name'])]]);
$r=bioco_catalog_seed('vegetables',true);
echo json_encode(['result'=>$r,'count'=>count($posts),'first'=>$posts[1]->post_title,'rows'=>bioco_catalog_rows('vegetables'),'options'=>$options]);
""")
    total=len(json.loads((CONTENT/'seeds/vegetables.json').read_text()))
    assert result['count']==total and len(result['rows'])==total-1
    assert result['first']=='Edited before retry' and result['result']['skipped']==1
    assert 'bioco_catalog_pending_vegetables' not in result['options']
    assert 'bioco_catalog_lock_vegetables' not in result['options']


def test_catalog_fields_are_available_independently_of_the_theme():
    root=ROOT/'wordpress/web/app/mu-plugins/bioco-core/acf-json'
    for kind,names in [('vegetable',{'vegetable_months'}),('depot',{'depot_lat','depot_lng','depot_description'})]:
        group=json.loads((root/f'group_bioco_cpt_{kind}.json').read_text())
        assert group['location']==[[{'param':'post_type','operator':'==','value':f'bioco_{kind}'}]]
        assert {f['name'] for f in group['fields']}==names
