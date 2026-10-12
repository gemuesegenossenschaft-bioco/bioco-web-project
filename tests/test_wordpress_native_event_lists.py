"""Native saved event trees and shared query semantics; hosted Divi editing is separate.

Keep/Replace/Remove evidence map: tests/README.md.
"""
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_native_divi_modules import _preamble
from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
HARNESS = _preamble(with_seam=True, stubs=r'''
function bioco_dynamic_components() { return ['events_feed'=>'bioco/events-feed','schnuppertage'=>'bioco/schnuppertage']; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function current_time($format) { return '2026-10-12 08:00:00'; }
function wp_json_encode($v,$flags=0) { return json_encode($v,$flags); }
function wp_generate_uuid4() { static $n=0; return 'unique-'.++$n; }
function esc_html($v) { return htmlspecialchars($v,ENT_QUOTES,'UTF-8'); }
function bioco_navigation_url($v) { return $v; }
require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-loop-empty.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/event-loop.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/event-lists.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/event-list-migration.php';
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
function parse_blocks($html) { return (new WP_Block_Parser())->parse($html); }
''') + WP_SERIALIZER_STUB + r'''
$state=json_decode(stream_get_contents(STDIN),true);
try {
    if (isset($state['order_options'])) {
        echo json_encode(bioco_event_loop_order_options($state['order_options']));
    } elseif (isset($state['query'])) {
        $attrs=['module'=>['advanced'=>['htmlAttributes'=>['desktop'=>['value'=>['class'=>$state['classes']]]],
            'loop'=>['desktop'=>['value'=>['postPerPage'=>$state['count'] ?? 3]]]]]];
        echo json_encode(bioco_event_loop_query($state['query'],$attrs));
    } else {
        $component=$state['component'] ?? 'events_feed';
        $values=$state['values'] ?? [];
        $attrs=[];
        foreach($values as $field=>$value) $attrs[$field]=['innerContent'=>['desktop'=>['value'=>$value]]];
        $attrs['module']['advanced']['htmlAttributes']['desktop']['value']=['id'=>'module-id','class'=>'bioco-native-component'];
        if ($state['default_anchor'] ?? false) $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['id']='';
        if($state['responsive'] ?? false) $attrs['empty_message']['innerContent']['phone']['value']='different';
        if($state['design'] ?? false) $attrs['module']['decoration']['spacing']['desktop']['value']=['padding'=>['top'=>'73px']];
        if($state['unknown_control'] ?? false) $attrs['module']['advanced']['future_control']=['desktop'=>['value'=>'Keep me']];
        if($state['unknown'] ?? false) $attrs['future_field']=['innerContent'=>['desktop'=>['value'=>'Keep me']]];
        $source=bioco_import_divi_block('bioco-divi/'.str_replace('_','-',$component),$attrs);
        $htmlattrs=fn($class,$id)=>['module'=>['advanced'=>['htmlAttributes'=>['desktop'=>['value'=>['class'=>$class,'id'=>$id]]]]]];
        $column=bioco_import_divi_block('divi/column',$htmlattrs('bioco-divi-content','column-id'),[$source]);
        if($state['shared'] ?? false) { $column['innerBlocks'][]=bioco_import_divi_block('divi/text',[]); $column['innerContent']=[null,null]; }
        $row=bioco_import_divi_block('divi/row',$htmlattrs('bioco-divi-row','row-id'),[$column]);
        $sibling=bioco_import_divi_block('divi/row',['module'=>['decoration'=>['sizing'=>['phone'=>['value'=>['width'=>'73%']]]]]]);
        $section=bioco_import_divi_block('divi/section',['module'=>['decoration'=>['background'=>['desktop'=>['value'=>['color'=>'#aabbcc']]]]]],[$sibling,$row]);
        $unrelated=bioco_import_divi_block('divi/section',[],[$sibling]);
        $unrelated['innerContent']=['<p>KEEP_STATIC</p>',null]; $unrelated['innerHTML']='<p>KEEP_STATIC</p>';
        [$content,$count]=bioco_import_event_lists_content(serialize_blocks([$unrelated,$section]));
        [$again,$repeat]=bioco_import_event_lists_content($content);
        echo json_encode(['blocks'=>parse_blocks($content),'count'=>$count,'repeat'=>$repeat,'stable'=>$again===$content,
            'sibling'=>parse_blocks(serialize_blocks([$sibling]))[0],'values'=>bioco_native_extract_values($component,$attrs)]);
    }
} catch(Throwable $error) { echo json_encode(['error'=>$error->getMessage()]); }
'''


def run(**state):
    result = subprocess.run(['php', '-r', HARNESS], cwd=ROOT, input=json.dumps(state),
                            capture_output=True, text=True, check=True)
    assert result.stderr == ''
    return json.loads(result.stdout)


def walk(blocks):
    for block in blocks:
        yield block
        yield from walk(block['innerBlocks'])


def loops(result):
    return [b for b in walk(result['blocks']) if 'loop' in (b['attrs'] or {}).get('module', {}).get('advanced', {})]


def query(classes='bioco-event-loop bioco-event-upcoming bioco-event-general', **extra):
    return run(classes=classes, query=dict(query_type='post_types', query_args={
        'post_type':['any'],'post_status':['private','publish'],'posts_per_page':7,'order':'DESC','orderby':'title',
        'offset':2,'s':'Vegetables','post__in':[23], 'meta_query':{'relation':'OR','native':{'key':'region','value':'AG'}},
    }), **extra)


def test_native_filters_count_and_sort_remain_owned_by_the_saved_loop():
    result=query()
    q=result['query_args']
    assert q['posts_per_page']==7 and q['order']=='DESC' and q['orderby']=='title'
    assert q['offset']==2 and q['s']=='Vegetables' and q['post__in']==[23]
    assert q['post_type']=='event' and q['post_status']=='publish' and q['meta_key']=='event_date'
    assert q['meta_query']['relation']=='AND'
    assert q['meta_query']['0']['relation']=='AND' and q['meta_query']['1']['native']=={'key':'region','value':'AG'}
    text=json.dumps(q['meta_query']['0'])
    assert '2026-10-12 08:00:00' in text and 'NOT EXISTS' in text and 'general' in text


def test_native_order_selector_retains_other_options_and_exposes_event_date_once():
    original = [{'value': 'date', 'label': 'Published'}, {'value': 'title', 'label': 'Title'}]
    selected = run(order_options=original)
    assert selected == original + [{'value': 'meta_value', 'label': 'Veranstaltungsdatum'}]
    assert run(order_options=selected) == selected


def test_ambiguous_semantics_and_explicit_zero_do_not_fall_back_to_all_records():
    assert query(count=0)['query_args']['post__in']==[-1]
    assert query(classes='bioco-event-loop')['query_args']['post__in']==[-1]
    assert query(classes='bioco-event-loop bioco-event-past bioco-event-upcoming')['query_args']['post__in']==[-1]
    assert query(classes='bioco-event-loop bioco-event-upcoming bioco-event-general bioco-event-schnuppertag')['query_args']['post__in']==[-1]
    original={'query_type':'users','query_args':{'keep':False}}
    assert run(classes='bioco-event-loop',query=original)==original


def test_conversion_is_idempotent_preserves_siblings_wrappers_and_source_anchors():
    result=run(values={'limit':0,'include_schnuppertage':False,'standard_title':'0','empty_message':'','archive_url':'/archiv/','standard_link_label':'Archiv'})
    assert 'error' not in result
    assert result['count']==1 and result['repeat']==0 and result['stable']
    section=result['blocks'][1]
    assert section['attrs']['module']['decoration']['background']['desktop']['value']['color']=='#aabbcc'
    assert section['innerBlocks'][0]==result['sibling']
    assert section['innerBlocks'][1]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id']=='row-id'
    assert section['innerBlocks'][1]['innerBlocks'][0]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id']=='column-id'
    assert section['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id']=='module-id'
    loop=loops(result)[0]
    assert loop['attrs']['module']['advanced']['loop']['desktop']['value']['postPerPage']==0
    assert 'bioco-event-general' in loop['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class']
    blocks=list(walk(result['blocks']))
    assert all(b['blockName'].startswith('divi/') for b in blocks)
    assert any((b['attrs'] or {}).get('button',{}).get('innerContent',{}).get('desktop',{}).get('value',{}).get('linkUrl')=='/archiv/' for b in blocks)
    assert 'loop_bioco_event_url' in json.dumps(blocks)


def test_standard_list_retains_separate_visit_and_recap_states_and_editable_elements():
    result=run(values={'limit':5,'standard_title':'Events','past_title':'Rückblicke','past_description':'Past description',
                       'past_link_label':'Lesen','schnuppertage_title':'Besuch','schnuppertage_empty_message':'Besuche fehlen'})
    configurations=[b['attrs']['module']['advanced']['loop']['desktop']['value'] for b in loops(result)]
    assert [c['postPerPage'] for c in configurations]==[5,5,1,4]
    assert [c['order'] for c in configurations]==['ASC','ASC','DESC','DESC']
    assert len({c['loopId'] for c in configurations})==4
    text=json.dumps(result['blocks'],ensure_ascii=False)
    for value in ['Rückblicke','Past description','Besuch','Besuche fehlen','Lesen']:
        assert value in text
    assert 'gridTemplateColumns' in text and 'phone' in text and 'loop_bioco_event_image_alt' in text


def test_visit_information_labels_and_explicit_blank_values_are_preserved():
    result=run(component='schnuppertage',values={'title':'Besuche','text':'<h3>Text</h3>','items':[{'text':'0'}],
        'closing':'<p>Bis bald</p>','signup_anchor':'#anmelden','signup_label':'Mitmachen','limit':2,'display':'list','empty_message':''})
    assert 'error' not in result
    data=json.dumps(result['blocks'],ensure_ascii=False)
    for value in ['Besuche','<h3>Text</h3>','<ul><li>0</li></ul>','<p>Bis bald</p>','#anmelden','Mitmachen']:
        assert value in data
    assert 'loop_bioco_event_image' not in data


@pytest.mark.parametrize('variant', ['standard', 'banner'])
def test_every_upcoming_feed_retains_native_image_summary_and_more_link(variant):
    result = run(values={'variant': variant, 'title': 'Banner', 'standard_title': 'Events',
                         'schnuppertage_title': 'Besuche'})
    upcoming = [b for b in loops(result) if 'bioco-event-upcoming' in b['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class']]
    assert len(upcoming) == (1 if variant == 'banner' else 2)
    for loop in upcoming:
        data = json.dumps(loop, ensure_ascii=False)
        assert 'loop_bioco_event_image_alt' in data and 'loop_bioco_event_summary' in data
        buttons = [b for b in walk([loop]) if b['blockName'] == 'divi/button']
        assert buttons[0]['attrs']['button']['innerContent']['desktop']['value']['text'] == 'Mehr erfahren →'
        assert 'loop_bioco_event_url' in buttons[0]['attrs']['button']['innerContent']['desktop']['value']['linkUrl']
    if variant == 'banner':
        heading = next(b for b in walk(result['blocks']) if b['blockName'] == 'divi/heading')
        assert heading['attrs']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] == 'h2'


def test_unsupported_negative_count_refuses_conversion_without_truncating_records():
    assert 'error' in run(values={'limit': -1})


@pytest.mark.parametrize('component', ['events_feed', 'schnuppertage'])
def test_legacy_default_fragment_targets_survive_on_the_static_wrapper(component):
    result = run(component=component, default_anchor=True)
    ids = [(b['attrs'] or {}).get('module', {}).get('advanced', {}).get('htmlAttributes', {}).get('desktop', {}).get('value', {}).get('id') for b in walk(result['blocks'])]
    assert ids.count(component.replace('_', '-')) == 1


@pytest.mark.parametrize('state', [{'responsive':True},{'unknown':True},{'unknown_control':True}])
def test_converter_refuses_unknown_values_or_authored_layout_instead_of_overwriting(state):
    result=run(values={'empty_message':'Keep this'},**state)
    assert 'error' in result


def test_shared_rows_authored_design_and_unrelated_html_survive_direct_leaf_replacement():
    result=run(values={'empty_message':'Keep this'},shared=True,design=True)
    assert 'error' not in result and result['count']==1
    assert result['blocks'][0]['innerContent'][0]=='<p>KEEP_STATIC</p>'
    source_column=result['blocks'][1]['innerBlocks'][1]['innerBlocks'][0]
    assert [b['blockName'] for b in source_column['innerBlocks']]==['divi/row','divi/text']
    wrapper=source_column['innerBlocks'][0]
    assert wrapper['attrs']['module']['decoration']['spacing']['desktop']['value']['padding']['top']=='73px'
    ids=[(b['attrs'] or {}).get('module',{}).get('advanced',{}).get('htmlAttributes',{}).get('desktop',{}).get('value',{}).get('id') for b in walk(result['blocks'])]
    assert ids.count('module-id')==1
    assert all((b['attrs'] or {}).get('module',{}).get('advanced',{}).get('htmlAttributes',{}).get('desktop',{}).get('value',{}).get('id')!='module-id' for b in loops(result))
