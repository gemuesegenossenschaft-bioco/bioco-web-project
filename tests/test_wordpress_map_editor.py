"""Public depot data, native card bindings, and saved map behavior for #209."""
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_issue_completion import CORE, ROOT


def php(source, data):
    result = subprocess.run(['php', '-r', source], input=json.dumps(data), text=True,
                            capture_output=True, cwd=ROOT)
    assert result.returncode == 0 and not result.stderr, result.stderr
    return json.loads(result.stdout)


BASE = r'''
define('ABSPATH','/');
$state=json_decode(stream_get_contents(STDIN),true);
function add_filter(...$args){}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_attr($v){return esc_html($v);}
function esc_url($v){return esc_html($v);}
function esc_url_raw($v){return $v;}
function wp_json_encode($v){return json_encode($v);}
function get_post_type($id){return $GLOBALS['state']['records'][$id]['type']??'page';}
function get_post_status($id){return $GLOBALS['state']['records'][$id]['status']??'publish';}
function post_password_required($id){return $GLOBALS['state']['records'][$id]['protected']??false;}
function current_user_can($cap,$id){return false;}
function get_queried_object_id(){return 999;}
function get_the_title($id){return $GLOBALS['state']['records'][$id]['title'];}
function get_post_meta($id,$key,$single=true){return $GLOBALS['state']['records'][$id]['meta'][$key]??'';}
function metadata_exists($type,$id,$key){return array_key_exists($key,$GLOBALS['state']['records'][$id]['meta']??[]);}
function get_posts($args){
  $posts=[];foreach($GLOBALS['state']['records'] as $id=>$record)
    if($record['type']===$args['post_type']&&$record['status']==='publish')
      $posts[]=(object)['ID'=>(int)$id,'post_title'=>$record['title'],'menu_order'=>$record['order']??0];
  usort($posts,fn($a,$b)=>$a->menu_order<=>$b->menu_order?:strcmp($a->post_title,$b->post_title));return $posts;
}
function bioco_native_extract_wrapper($attrs){return ['className'=>$attrs['class']??''];}
function bioco_native_render_mode(){return 'public';}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/record-bindings.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/record-layout-runtime.php';
'''


def records():
    return {
        '1': {'type': 'bioco_depot', 'status': 'publish', 'title': 'Baden', 'order': 2,
              'meta': {'depot_public': 1, 'depot_lat': '47.47', 'depot_lng': '8.30',
                       'depot_description': 'PRIVATE PHONE', 'depot_public_address': 'Bahnhof 1',
                       'depot_website': 'https://example.test/depot', 'depot_website_label': 'Zum Depot'}},
        '2': {'type': 'bioco_depot', 'status': 'publish', 'title': 'Früher', 'order': 1,
              'meta': {'depot_public': 1, 'depot_lat': '47.5', 'depot_lng': '8.2'}},
        '3': {'type': 'bioco_depot', 'status': 'publish', 'title': 'Privat', 'order': 0,
              'meta': {'depot_public': 0, 'depot_description': 'SECRET'}},
        '4': {'type': 'bioco_depot', 'status': 'draft', 'title': 'Entwurf', 'order': 0, 'meta': {}},
    }


def test_depot_loop_uses_public_record_order_and_only_approved_binding_fields():
    code = BASE + r'''
$attrs=['class'=>'bioco-depot-loop'];
$query=bioco_record_native_query(['query_args'=>[]],$attrs);
$fields=['name','address','website','website_label','route_url'];$bound=[];
foreach($fields as $field)$bound[$field]=bioco_record_binding_value('original',['name'=>'loop_bioco_depot_'.$field,'loop_id'=>1]);
$private=bioco_record_binding_value('original',['name'=>'loop_bioco_depot_address','loop_id'=>3]);
$draft=bioco_record_binding_value('original',['name'=>'loop_bioco_depot_name','loop_id'=>4]);
$unrelated=bioco_record_native_query(['query_args'=>[]],['class'=>'ordinary']);
echo json_encode(compact('query','bound','private','draft','unrelated'));
'''
    result = php(code, {'records': records()})
    assert result['query']['query_args']['post__in'] == [2, 1]
    assert result['query']['query_args']['orderby'] == 'post__in'
    assert result['query']['query_args']['posts_per_page'] == -1
    assert result['bound'] == {'name': 'Baden', 'address': 'Bahnhof 1',
                               'website': 'https://example.test/depot', 'website_label': 'Zum Depot',
                               'route_url': 'https://www.google.com/maps/dir/?api=1&destination=47.47%2C8.30'}
    assert result['private'] == result['draft'] == ''
    assert result['unrelated'] == {'query_args': []}
    reordered = records()
    reordered['1']['order'] = 0
    assert php(code, {'records': reordered})['query']['query_args']['post__in'] == [1, 2]


@pytest.mark.parametrize('field', ['name', 'address', 'website', 'website_label', 'route_url'])
def test_password_protected_published_depot_does_not_expose_binding_values(field):
    data = records()
    data['1']['protected'] = True
    code = BASE + "echo json_encode(bioco_record_binding_value('original',['name'=>'loop_bioco_depot_' . $state['field'],'loop_id'=>1]));"
    assert php(code, {'records': data, 'field': field}) == ''
    data['1']['protected'] = False
    assert php(code, {'records': data, 'field': field}) != ''


def test_bad_website_is_not_a_divi_button_destination():
    data = records()
    data['1']['meta']['depot_website'] = 'javascript:alert(1)'
    code = BASE + r'''
echo json_encode(['url'=>bioco_record_binding_value('x',['name'=>'loop_bioco_depot_website','loop_id'=>1]),
 'label'=>bioco_record_binding_value('x',['name'=>'loop_bioco_depot_website_label','loop_id'=>1])]);
'''
    assert php(code, {'records': data}) == {'url': '', 'label': ''}


def test_map_output_preserves_markers_and_lets_divi_replace_fallback_cards():
    code = BASE + r'''
ob_start();bioco_render_map_block($state['locations'],47.4,8.3,15,'Standorte','Route','Leer',
 ['map_view'=>'fixed','interaction'=>'full','map_height'=>460,'arrangement'=>'side_by_side',
  'card_order'=>'address_first','show_address_list'=>false]);$without=ob_get_clean();
ob_start();bioco_render_map_block($state['locations'],47.4,8.3,15,'Standorte','Route','Leer',
 ['map_view'=>'fixed','interaction'=>'full','map_height'=>460,'arrangement'=>'side_by_side',
  'card_order'=>'address_first']);$with=ob_get_clean();
echo json_encode(compact('without','with'));
'''
    result = php(code, {'records': records(), 'locations': [
        {'name': 'Baden', 'lat': 47.47, 'lng': 8.3, 'description': 'Bahnhof 1',
         'website': 'https://example.test/depot', 'website_label': 'Zum Depot'}]})
    assert 'data-center-lat="47.4"' in result['without']
    assert 'data-zoom="15"' in result['without']
    assert 'data-map-interaction="full"' in result['without']
    assert '--bioco-map-height:460px' in result['without']
    assert 'data-locations=' in result['without']
    assert 'location-addresses' not in result['without']
    assert result['with'].index('Bahnhof 1</p>') < result['with'].index('<strong>Baden</strong>')
    assert 'href="https://example.test/depot"' in result['with']


@pytest.mark.parametrize('component', ['depot-map', 'geisshof-map'])
def test_saved_map_interaction_and_view_change_leaflet_options(component):
    from playwright.sync_api import sync_playwright
    with sync_playwright() as browser_api:
        browser = browser_api.chromium.launch()
        page = browser.new_page()
        page.set_content(f'''<section class="cms-{component}"><div class="map-wrapper"
          data-center-lat="47.4" data-center-lng="8.3" data-zoom="15" data-map-view="fixed"
          data-map-interaction="full" data-locations='[{{"name":"A","lat":47.4,"lng":8.3}},{{"name":"B","lat":47.5,"lng":8.4}}]'></div></section>''')
        page.evaluate('''() => { window.settings={}; window.BiocoConsent={mountMap:(el,fn)=>fn(el)};
          window.L={map:(el,options)=>{settings.options=options;return {setView:(point,zoom)=>{settings.center=point;settings.zoom=zoom;return this},fitBounds:()=>{settings.fitted=true}}},
          tileLayer:()=>({addTo(){}}),marker:()=>({addTo(){return this},bindPopup(){return this},openPopup(){}}),
          featureGroup:()=>({getBounds:()=>({pad(){return this}})})}; }''')
        page.add_script_tag(path=str(CORE / 'blocks' / component / 'view.js'))
        assert page.evaluate('settings.zoom') == 15
        assert page.evaluate('settings.center') == [47.4, 8.3]
        assert page.evaluate('settings.options.scrollWheelZoom') is True
        assert not page.evaluate('!!settings.fitted')
        browser.close()


@pytest.mark.parametrize('width,side_by_side', [(390, False), (1280, True)])
def test_map_tiles_clip_inside_map_without_document_overflow(width, side_by_side):
    from playwright.sync_api import sync_playwright
    with sync_playwright() as browser_api:
        browser = browser_api.chromium.launch()
        page = browser.new_page(viewport={'width': width, 'height': 850})
        page.set_content('''<div class="bioco-map-layout bioco-map-layout--side_by_side" style="--bioco-map-height:460px">
          <div class="map-container"><div class="map-wrapper"><div style="width:2000px;height:100px"></div></div></div>
          <div class="location-info-box"><div class="location-addresses"><div class="address-list"><div class="address-item">Depot</div></div></div></div>
          </div>''')
        page.add_style_tag(content='body{margin:0}.bioco-map-layout{max-width:100%;box-sizing:border-box}')
        page.add_style_tag(content=(CORE / 'assets/bioco-tokens.css').read_text())
        page.add_style_tag(content=(CORE / 'assets/bioco-blocks.css').read_text())
        state = page.evaluate('''() => ({columns:getComputedStyle(document.querySelector('.bioco-map-layout')).gridTemplateColumns,
          clipped:getComputedStyle(document.querySelector('.map-container')).overflowX,
          overflow:document.documentElement.scrollWidth>innerWidth,
          height:getComputedStyle(document.querySelector('.map-container')).height})''')
        assert state['clipped'] == 'hidden'
        assert state['overflow'] is False
        assert state['height'] == '460px'
        assert (len(state['columns'].split(' ')) == 2) is side_by_side
        browser.close()
