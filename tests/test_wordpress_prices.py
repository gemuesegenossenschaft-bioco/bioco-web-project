"""Shared price data and real PHP consumers.

Keep/Replace/Remove evidence map: tests/README.md. Storage is replaced at the
WordPress boundary. This does not establish native Divi table migration or QA.
"""
import json

import pytest
from pathlib import Path
from playwright.sync_api import sync_playwright

from test_wordpress_catalog import catalog


TIERS = [
    ('halb-1-person', 'halb', 'Halb (1 Person)', 750, 1, 10, 1),
    ('standard-2-3-personen', 'standard', 'Standard (2-3 Personen)', 1280, 2, 20, 2),
    ('doppel-4-6-personen', 'doppel', 'Doppel (4-6 Personen)', 2350, 4, 40, 4),
]


def records():
    return r'''
foreach (json_decode(%s, true) as $index=>$tier) {
    [$key,$type,$name,$price,$shares,$work,$persons]=$tier;
    wp_insert_post(['post_type'=>'bioco_price','post_status'=>'publish','post_title'=>$name,'menu_order'=>$index,
        'meta_input'=>['price_key'=>$key,'price_abo_type'=>$type,'price_price'=>$price,'price_shares'=>$shares,
            'price_work'=>$work,'price_persons'=>$persons,'price_recommended'=>$index===1]]);
}
''' % json.dumps(json.dumps(TIERS))


def test_legacy_remains_until_explicit_activation_and_empty_catalog_remains_empty():
    out = catalog(records() + r'''
$legacy=bioco_price_catalog();$options['bioco_pricing_enabled']=true;
$catalog=bioco_price_catalog();$before=serialize([$posts,$meta,$options]);
bioco_price_catalog();$readonly=$before===serialize([$posts,$meta,$options]);
foreach($posts as $post)$post->post_status='trash';$empty=bioco_price_catalog();
echo json_encode(compact('legacy','catalog','readonly','empty','types'));
''')
    assert out['legacy'] is None and out['readonly']
    assert out['empty'] == {'share_price': 250, 'tiers': []}
    assert out['types']['bioco_price']['show_ui'] and not out['types']['bioco_price']['public']
    assert [t['price'] + t['shares'] * out['catalog']['share_price'] for t in out['catalog']['tiers']] == [1000, 1780, 3350]


def test_stored_zero_share_price_and_zero_tier_values_are_preserved():
    out = catalog(records() + r'''
$options['bioco_pricing_enabled']=true;$options['bioco_share_price']='0';
foreach(['price','shares','work','persons'] as $key)update_post_meta(1,'price_'.$key,'0');
echo json_encode(bioco_price_catalog());
''')
    assert out['share_price'] == 0
    assert all(out['tiers'][0][field] == 0 for field in ['price', 'shares', 'work', 'persons'])


def test_tier_keys_survive_renaming_and_new_tiers_have_explicit_membership_mapping():
    out = catalog(records() + r'''
$options['bioco_pricing_enabled']=true;$posts[1]->post_title='Neuer Anzeigename';
wp_insert_post(['post_type'=>'bioco_price','post_status'=>'publish','post_title'=>'Neue Stufe','menu_order'=>3,
    'meta_input'=>['price_key'=>'new-tier','price_abo_type'=>'standard','price_price'=>900,
        'price_shares'=>2,'price_work'=>12,'price_persons'=>3]]);
echo json_encode(['catalog'=>bioco_price_catalog(),'existing'=>bioco_price_abo_type('halb-1-person'),
    'new'=>bioco_price_abo_type('new-tier'),'unknown'=>bioco_price_abo_type('missing')]);
''')
    assert out['catalog']['tiers'][0]['key'] == 'halb-1-person'
    assert out['existing'] == 'halb' and out['new'] == 'standard' and out['unknown'] is None
    assert len(out['catalog']['tiers']) == 4


@pytest.mark.parametrize('mutation,error', [
    ("$options['bioco_share_price']='-1';", 'invalid-share-price'),
    ("update_post_meta(1,'price_price','1.5');", 'invalid-tier-amount'),
    ("update_post_meta(1,'price_key','invalid/key');", 'invalid-tier-key'),
    ("update_post_meta(1,'price_key','kein');", 'invalid-tier-key'),
    ("update_post_meta(1,'price_abo_type','invented');", 'invalid-tier-key'),
    ("update_post_meta(2,'price_key','halb-1-person');", 'invalid-tier-key'),
])
def test_invalid_or_duplicate_catalog_does_not_offer_inconsistent_partial_prices(mutation, error):
    out = catalog(records() + "$options['bioco_pricing_enabled']=true;" + mutation + "echo json_encode(bioco_price_catalog());")
    assert out['tiers'] == [] and out['error'] == error


def consumer_source():
    return r'''
function get_field($key,...$args){return $GLOBALS['fields'][$key]??'';}
function bioco_field($key){return get_field($key);}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_attr($v){return esc_html($v);}
function esc_url($v){return esc_html($v);}
function sanitize_title($v){return strtolower(str_replace(' ','-',str_replace(['(',')'],'',$v)));}
function number_format_i18n($v){return number_format($v,0,'.',"'");}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function bioco_text_has_heading_html($v){return false;}
function bioco_kses_rich_text($v){return $v;}
function bioco_render_person_icons($n){echo '<span>'.$n.'</span>';}
function bioco_link_target_attributes($url){return '';}
$fields=['tiers'=>[['name'=>'Stale local tier','price'=>1,'shares'=>8,'work'=>99]],
    'share_price'=>999,'signup_url'=>'/anmeldung','cta_label'=>'Anmelden','total_label'=>'Total'];
$block=[];$is_preview=false;
ob_start();require 'wordpress/web/app/mu-plugins/bioco-core/blocks/pricing-calculator/render.php';$calculator=ob_get_clean();
ob_start();require 'wordpress/web/app/mu-plugins/bioco-core/blocks/pricing-table/render.php';$table=ob_get_clean();
echo json_encode(compact('calculator','table'));
'''


def test_both_consumers_ignore_divergent_local_amounts_after_activation():
    out = catalog(records() + r'''
$options['bioco_pricing_enabled']=true;$options['bioco_share_price']=0;
update_post_meta(1,'price_price',870);
''' + consumer_source())
    assert 'data-share-price="0"' in out['calculator']
    assert 'data-tier-price="870"' in out['calculator']
    assert 'CHF 870.-' in out['table']
    assert 'Stale local tier' not in out['calculator'] + out['table']
    assert 'abo=halb-1-person' in out['calculator']
    assert 'data-tier-price="1280"' in out['calculator'] and "CHF 1&#039;280.-" in out['table']


def test_legacy_calculator_preserves_an_editor_set_zero_before_activation():
    source = consumer_source().replace("'share_price'=>999", "'share_price'=>0")
    out = catalog(source)
    assert 'data-share-price="0"' in out['calculator']
    assert 'data-tier-price="1"' in out['calculator']


def test_calculator_uses_shared_prices_for_all_totals_increments_none_and_handoff():
    source = consumer_source().replace("'total_label'=>'Total'", "'total_label'=>'Total','no_subscription_label'=>'Kein Abo'")
    out = catalog(records() + "$options['bioco_pricing_enabled']=true;$options['bioco_share_price']=250;" + source)
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        page = browser.new_page()
        page.set_content(out['calculator'])
        page.add_script_tag(path=str(Path(__file__).parents[1] / 'wordpress/web/app/mu-plugins/bioco-core/blocks/pricing-calculator/view.js'))
        for key, amount in [('halb-1-person', 1000), ('standard-2-3-personen', 1780), ('doppel-4-6-personen', 3350)]:
            page.locator('[data-tier-slug="' + key + '"]').click()
            assert str(amount) == page.locator('[data-pc-field="total"]').inner_text().replace('CHF ', '').replace('.-', '').replace('’', '').replace("'", '')
        page.locator('[data-pc-action="add-share"]').click()
        assert page.locator('[data-pc-field="shares-count"]').inner_text() == '5'
        assert 'shares=5' in page.locator('[data-pc-field="cta"]').get_attribute('href')
        assert 'additional=1' in page.locator('[data-pc-field="cta"]').get_attribute('href')
        page.locator('[data-pc-action="remove-share"]').click()
        assert page.locator('[data-pc-field="shares-count"]').inner_text() == '4'
        page.locator('[data-tier-slug="kein"]').click()
        assert page.locator('[data-pc-role="cta-block"]').is_hidden()
        assert page.locator('[data-pc-field="shares-count"]').inner_text() == '0'
        assert page.locator('[data-pc-field="total"]').inner_text() == 'CHF 0.-'
        browser.close()
