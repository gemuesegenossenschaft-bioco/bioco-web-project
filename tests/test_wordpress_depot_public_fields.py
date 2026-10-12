"""Anonymous depot output respects the confirmed field policy.

Keep/Replace/Remove evidence map: tests/README.md. WordPress storage and Leaflet
are replaced at their boundaries; the actual PHP renderer and popup script run.
"""
import json
from html.parser import HTMLParser
from pathlib import Path

import pytest
from playwright.sync_api import sync_playwright

from test_wordpress_catalog import catalog

ROOT = Path(__file__).parents[1]


class MapMarkup(HTMLParser):
    def __init__(self, markup):
        super().__init__()
        self.points = []
        self.links = []
        self.feed(markup)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if 'data-locations' in attrs:
            self.points = json.loads(attrs['data-locations'])
        if tag == 'a':
            self.links.append(attrs)


def render(public_fields=False, legacy=False):
    return catalog(r'''
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_attr($v){return esc_html($v);}
function esc_url($v){return esc_html($v);}
function wp_json_encode($v){return json_encode($v);}
function bioco_field($key){return $GLOBALS['fields'][$key]??'';}
require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';
$fields=['locations_heading'=>'Depots','route_label'=>'Route planen','locations'=>[
    ['name'=>'Local depot','lat'=>47.4,'lng'=>8.3,'description'=>'PRIVATE LOCAL DESCRIPTION']]];
if (!%s) {
    $values=['depot_lat'=>47.4,'depot_lng'=>8.3,'depot_description'=>'PRIVATE STORED CONTACT AND DESCRIPTION'];
    if (%s) $values+=['depot_public_address'=>'Approved public address',
        'depot_website'=>'https://example.test/depot','depot_website_label'=>'Depot & website'];
    wp_insert_post(['post_type'=>'bioco_depot','post_status'=>'publish','post_title'=>'Existing depot','meta_input'=>$values]);
}
$before=serialize([$posts,$meta]);$block=[];
ob_start();require 'wordpress/web/app/mu-plugins/bioco-core/blocks/depot-map/render.php';$html=ob_get_clean();
echo json_encode(['html'=>$html,'preserved'=>$before===serialize([$posts,$meta]),'meta'=>$meta]);
''' % (str(legacy).lower(), str(public_fields).lower()))


@pytest.mark.parametrize('legacy', [False, True])
def test_private_details_never_reach_html_embedded_json_or_links(legacy):
    out = render(legacy=legacy)
    assert out['preserved']
    assert 'PRIVATE' not in out['html']
    parsed = MapMarkup(out['html'])
    assert len(parsed.points) == 1
    assert parsed.points[0]['lat'] == 47.4 and parsed.points[0]['lng'] == 8.3
    assert parsed.points[0]['description'] == parsed.points[0]['website'] == ''
    if not legacy:
        assert out['meta']['1']['depot_description'] == 'PRIVATE STORED CONTACT AND DESCRIPTION'


def test_approved_public_address_and_website_render_without_private_storage():
    out = render(public_fields=True)
    assert out['preserved'] and 'PRIVATE' not in out['html']
    assert 'Approved public address' in out['html']
    parsed = MapMarkup(out['html'])
    link = next(a for a in parsed.links if a['href'] == 'https://example.test/depot')
    assert set(link['rel'].split()) >= {'noopener', 'noreferrer'}
    assert parsed.points[0]['website_label'] == 'Depot & website'


def test_actual_popup_script_uses_only_the_projected_public_values():
    out = render(public_fields=True)
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        page = browser.new_page()
        page.set_content(out['html'] + '<div id="popups"></div>')
        page.evaluate("""() => {
          window.BiocoConsent = {mountMap: (wrapper, start) => start(wrapper)};
          window.L = {
            map: () => ({setView() {return this;}, fitBounds() {}}),
            tileLayer: () => ({addTo() {}}),
            marker: () => ({addTo() {return this;}, bindPopup(html) {
              const popup = document.createElement('div'); popup.innerHTML = html;
              document.querySelector('#popups').appendChild(popup);
            }}),
            featureGroup: () => ({getBounds: () => ({pad() {}})})
          };
        }""")
        page.add_script_tag(path=str(ROOT / 'wordpress/web/app/mu-plugins/bioco-core/blocks/depot-map/view.js'))
        assert 'PRIVATE' not in page.locator('#popups').inner_text()
        assert 'Approved public address' in page.locator('#popups').inner_text()
        assert page.locator('#popups a').get_attribute('href') == 'https://example.test/depot'
        assert page.locator('#popups a').inner_text() == 'Depot & website'
        browser.close()


def test_popup_links_use_normalized_http_urls_without_attribute_injection():
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        page = browser.new_page()
        for url in ['https://example.test/" onclick="alert(1)', 'https://example.test/a/../depot?q=1&next=2',
                    'https://example.test/\npath', 'javascript:alert(1)', 'data:text/html,hi', '/relative']:
            page.set_content('<div class="cms-depot-map"><div class="map-wrapper"></div></div><div id="popups"></div>')
            page.evaluate("""url => {
              document.querySelector('.map-wrapper').dataset.locations = JSON.stringify([
                {name: 'Depot',lat:47.4,lng:8.3,website:url,website_label:'Public depot'}]);
              window.BiocoConsent = {mountMap:(wrapper,start)=>start(wrapper)};
              window.L = {map:()=>({setView(){return this;}}),tileLayer:()=>({addTo(){}}),
                marker:()=>({addTo(){return this;},bindPopup(html){document.querySelector('#popups').innerHTML=html;}})};
            }""", url)
            page.add_script_tag(path=str(ROOT / 'wordpress/web/app/mu-plugins/bioco-core/blocks/depot-map/view.js'))
            links = page.locator('#popups a')
            if url.startswith('https:'):
                assert links.count() == 1
                expected = page.evaluate('url => new URL(url).href', url)
                assert links.get_attribute('href') == expected
                assert links.get_attribute('onclick') is None
                assert links.inner_text() == 'Public depot'
            else:
                assert links.count() == 0
        browser.close()
