"""Real PHP enqueue hook and real native JS queue. See tests/README.md."""
import json
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]
CORE = ROOT / 'wordpress/web/app/mu-plugins/bioco-core'


def enqueue(environment='production', host='bioco.ch', url='https://matomo.bioco.ch', site_id='1', edit_posts=False, logged_in=False, query=None, headers=None, preview=False, customize=False):
    php = r'''
    define('ABSPATH', __DIR__); $scenario=json_decode($argv[1],true); $scripts=[];$inline=[];$actions=[];
    putenv('BIOCO_MATOMO_URL='.$scenario['url']);putenv('BIOCO_MATOMO_SITE_ID='.$scenario['site_id']);
    $_GET=$scenario['query'];$_SERVER=$scenario['headers'];
    function current_user_can($capability) {return $GLOBALS['scenario']['edit_posts'];}
    function is_user_logged_in() {return $GLOBALS['scenario']['logged_in'];}
    function is_preview() {return $GLOBALS['scenario']['preview'];}
    function is_customize_preview() {return $GLOBALS['scenario']['customize'];}
    function add_action($hook,$callback) {$GLOBALS['actions'][$hook]=$callback;}
    function wp_get_environment_type() {return $GLOBALS['scenario']['environment'];}
    function home_url($path) {return 'https://'.$GLOBALS['scenario']['host'].$path;}
    function plugin_dir_url($path) {return 'https://bioco.ch/wp-content/mu-plugins/bioco-core/';}
    function wp_enqueue_script(...$args) {$GLOBALS['scripts'][]=$args;}
    function wp_add_inline_script(...$args) {$GLOBALS['inline'][]=$args;}
    function wp_json_encode(...$args) {return json_encode(...$args);}
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/matomo.php';
    $actions['wp_enqueue_scripts']();
    echo json_encode(['scripts'=>$scripts,'inline'=>$inline]);
    '''
    result = subprocess.run(['php', '-r', php, json.dumps(dict(environment=environment, host=host, url=url, site_id=site_id, edit_posts=edit_posts, logged_in=logged_in, query=query or {}, headers=headers or {}, preview=preview, customize=customize))], cwd=ROOT, check=True, capture_output=True, text=True)
    return json.loads(result.stdout)


@pytest.mark.parametrize('changes', [dict(environment='staging'), dict(host='staging.bioco.ch'), dict(host='www.bioco.ch'), dict(url=''), dict(site_id=''), dict(site_id='0'), dict(url='http://matomo.bioco.ch'), dict(url='https://user:password@matomo.bioco.ch'), dict(url='https://matomo.bioco.ch/?secret=test')])
def test_tracking_is_off_outside_configured_production(changes):
    assert enqueue(**changes) == dict(scripts=[], inline=[])


def test_enabled_tracking_uses_core_native_asset_and_safe_inline_config():
    result = enqueue(url='https://matomo.bioco.ch/</script>')
    assert result['scripts'][0][0:3] == ['bioco-matomo', 'https://bioco.ch/wp-content/mu-plugins/bioco-core/assets/bioco-matomo.js', ['bioco-consent']]
    assert result['scripts'][0][4] is True
    assert result['inline'][0][0] == 'bioco-matomo'
    assert result['inline'][0][2] == 'before'
    assert '</script>' not in result['inline'][0][1]
    assert '\\u003C' in result['inline'][0][1]
    assert "require_once BIOCO_CORE_DIR . '/includes/matomo.php';" in (CORE / 'bioco-core.php').read_text()


@pytest.mark.parametrize('changes', [
    dict(edit_posts=True, logged_in=True), dict(preview=True), dict(customize=True),
    *[dict(query={key: value}) for key, value in [
        ('et_fb', '1'), ('et_fb', '0'), ('et_pb_preview', 'true'),
        ('preview', 'true'), ('preview', '1'), ('preview_id', '123'),
        ('preview_nonce', 'synthetic'), ('customize_changeset_uuid', 'synthetic'),
        ('customize_theme', 'Divi'), ('customize_messenger_channel', 'preview-1'),
        ('bioco_qa', '1'), ('release_check', 'synthetic'), ('release-check', 'synthetic')]],
    dict(headers={'HTTP_X_BIOCO_QA': '1'}),
])
def test_editor_builder_preview_and_marked_qa_do_not_enqueue(changes):
    assert enqueue(**changes) == dict(scripts=[], inline=[])


@pytest.mark.parametrize('changes', [dict(), dict(logged_in=True),
    dict(query={'preview': 'false'}), dict(query={'bioco_qa': '0'}),
    dict(query={'abo': 'kein', 'shares': '3', 'utm_source': 'newsletter'})])
def test_public_visitors_including_members_still_enqueue(changes):
    assert len(enqueue(**changes)['scripts']) == 1


def browser_commands(url='https://bioco.ch/newsletter-bestaetigen/', referrer='', consent=True,
                     changes=(), tracker_ready=False, tracker_url='https://matomo.example.test/'):
    node = r"""
    const fs=require('fs'),vm=require('vm');
    const scenario=JSON.parse(process.argv[1]),appended=[],events={},sent=[];
    let consent=scenario.consent;
    const window={location:{href:scenario.url},BiocoConsent:{has:()=>consent},
      biocoMatomoConfig:{url:scenario.tracker_url,siteId:'1'},
      addEventListener:(name,fn)=>events[name]=fn};
    const context=vm.createContext({window,URL,document:{referrer:scenario.referrer,
      createElement:tag=>({tag}),head:{appendChild:s=>appended.push({...s,
        queue:JSON.parse(JSON.stringify(window._paq || []))})}}});
    vm.runInContext(fs.readFileSync(process.argv[2],'utf8'),context);
    if(scenario.tracker_ready && window._paq) {
      sent.push(...window._paq);window._paq={push:row=>sent.push(row)};
    }
    for(const choice of scenario.changes) {
      consent=choice;if(events['bioco:consent-change'])events['bioco:consent-change']();
    }
    process.stdout.write(JSON.stringify({queue:window._paq || [],appended,sent}));
    """
    output = subprocess.run(['node', '-e', node, json.dumps(dict(
        url=url, referrer=referrer, consent=consent, changes=changes, tracker_ready=tracker_ready,
        tracker_url=tracker_url)),
        str(CORE / 'assets/bioco-matomo.js')], check=True, capture_output=True, text=True)
    return json.loads(output.stdout)


def expected_commands(url, referrer='', tracker_url='https://matomo.example.test/'):
    return [['setTrackerUrl', tracker_url + 'matomo.php'], ['setSiteId', '1'],
            ['requireConsent'], ['disableCookies'], ['setCustomUrl', url],
            ['setReferrerUrl', referrer], ['setConsentGiven'], ['trackPageView']]


@pytest.mark.parametrize('tracker_url', ['https://matomo.example.test/', 'https://bioco.ch/matomo/'])
def test_synthetic_confirmation_secrets_never_enter_tracker_commands_or_loader(tracker_url):
    behavior = browser_commands(
        url='https://bioco.ch/newsletter-bestaetigen?token=SYNTHETIC_DOI_SECRET&email=person%40example.test&utm_source=newsletter&unknown=SYNTHETIC_UNKNOWN#SYNTHETIC_FRAGMENT',
        referrer='https://mail.example.test/message/?unsubscribe_token=SYNTHETIC_UNSUBSCRIBE&email=person%40example.test&utm_campaign=autumn',
        tracker_url=tracker_url)
    expected = expected_commands('https://bioco.ch/newsletter-bestaetigen/?utm_source=newsletter',
                                 'https://mail.example.test/message/?utm_campaign=autumn', tracker_url)
    assert behavior['queue'] == expected
    elements = [dict(tag='script', **{'async': True}, referrerPolicy='no-referrer',
                     src=tracker_url + 'matomo.js', queue=expected)]
    if tracker_url == 'https://bioco.ch/matomo/':
        elements.insert(0, dict(tag='meta', name='referrer', content='no-referrer', queue=expected))
    assert behavior['appended'] == elements
    assert 'SYNTHETIC' not in json.dumps(behavior)
    assert 'person' not in json.dumps(behavior)


@pytest.mark.parametrize(('url', 'safe'), [
    ('https://bioco.ch/abos', 'https://bioco.ch/abos/'),
    ('https://bioco.ch/abos///#fragment', 'https://bioco.ch/abos/'),
    ('https://bioco.ch/', 'https://bioco.ch/'),
    ('https://bioco.ch/anmeldung?shares=03&abo=kein&additional=2',
     'https://bioco.ch/anmeldung/?abo=kein&additional=2&shares=3'),
    *[(f'https://bioco.ch/anmeldung/?abo={abo}', f'https://bioco.ch/anmeldung/?abo={abo}')
      for abo in ['halb-1-person', 'standard-2-3-personen', 'doppel-4-6-personen']],
    ('https://bioco.ch/anmeldung/?abo=secret&shares=101&additional=-1', 'https://bioco.ch/anmeldung/'),
    ('https://bioco.ch/?utm_email=person&mtm_token=secret&utm_source=person%40example.test&utm_content=person%2540example.test', 'https://bioco.ch/'),
    ('https://bioco.ch/?utm_campaign=autumn&mtm_source=public&utm_source=newsletter&utm_source=other&token=secret',
     'https://bioco.ch/?mtm_source=public&utm_campaign=autumn'),
])
def test_only_reviewed_public_values_survive_and_variants_are_canonical(url, safe):
    assert browser_commands(url=url)['queue'] == expected_commands(safe)


@pytest.mark.parametrize('query', ['et_fb=1', 'et_pb_preview=true', 'preview=true', 'preview_id=1',
    'preview_nonce=synthetic', 'customize_changeset_uuid=synthetic', 'customize_theme=Divi',
    'customize_messenger_channel=preview-1', 'bioco_qa=1', 'release_check=synthetic', 'release-check=synthetic'])
def test_browser_gate_also_blocks_cached_builder_and_qa_pages(query):
    assert browser_commands(url='https://bioco.ch/abos/?' + query, changes=[False, True]) == dict(queue=[], appended=[], sent=[])


def test_default_off_and_repeated_consent_notifications_do_not_duplicate_pageview():
    assert browser_commands(consent=False) == dict(queue=[], appended=[], sent=[])
    result = browser_commands(consent=False, changes=[True, True, True])
    assert result['queue'] == expected_commands('https://bioco.ch/newsletter-bestaetigen/')
    assert len(result['appended']) == 1


def test_pending_withdrawal_cancels_grant_and_pageview_then_regrant_can_send_once():
    result = browser_commands(changes=[False])
    assert result['queue'] == expected_commands('https://bioco.ch/newsletter-bestaetigen/')[:-2] + [['forgetConsentGiven']]
    result = browser_commands(changes=[False, True, True])
    assert sum(row[0] == 'trackPageView' for row in result['queue']) == 1


def test_withdrawal_after_tracker_drains_and_regrant_keep_one_pageview_per_navigation():
    result = browser_commands(tracker_ready=True, changes=[True, False, False, True, True])
    assert result['sent'] == expected_commands('https://bioco.ch/newsletter-bestaetigen/') + [
        ['forgetConsentGiven'], ['setConsentGiven']]


@pytest.mark.parametrize('referrer', ['javascript:alert(1)', 'not a URL', ''])
def test_invalid_referrer_is_explicitly_empty(referrer):
    assert browser_commands(referrer=referrer)['queue'] == expected_commands('https://bioco.ch/newsletter-bestaetigen/')


@pytest.mark.parametrize(('url', 'safe'), [
    ('https://user:synthetic-password@bioco.ch/abos/?utm_medium=email',
     'https://bioco.ch/abos/?utm_medium=email'),
    ('https://bioco.ch/person%40example.test/?token=synthetic', 'https://bioco.ch/'),
    ('https://bioco.ch/person%2540example.test/', 'https://bioco.ch/'),
    ('https://bioco.ch/abos/%E0%A4%A', 'https://bioco.ch/'),
    ('https://bioco.ch/%C3%BCber-uns', 'https://bioco.ch/%C3%BCber-uns/'),
    ('https://bioco.ch/?utm_campaign=' + 'a' * 81, 'https://bioco.ch/'),
    ('https://bioco.ch/?utm_medium=public%20label', 'https://bioco.ch/'),
])
def test_sensitive_url_components_and_unreviewed_campaign_values_are_dropped(url, safe):
    assert browser_commands(url=url)['queue'] == expected_commands(safe)


@pytest.mark.parametrize('query', ['bioco_qa=0&bioco_qa=1', 'preview=false&preview=true'])
def test_repeated_query_keys_cannot_hide_active_exclusion_marker(query):
    assert browser_commands(url='https://bioco.ch/?' + query) == dict(queue=[], appended=[], sent=[])


def test_real_consent_storage_events_gate_tracker_and_cross_tab_withdrawal():
    node = r'''
    const fs=require('fs'),vm=require('vm'),events={},appended=[],sent=[];
    const window={location:{href:'https://bioco.ch/abos/'},
      biocoConsentTexts:JSON.parse(process.argv[1]),
      biocoMatomoConfig:{url:'https://matomo.example.test/',siteId:'1'},
      addEventListener:(name,fn)=>(events[name] ||= []).push(fn),
      dispatchEvent:event=>(events[event.type] || []).forEach(fn=>fn(event))};
    const context=vm.createContext({window,URL,Date,
      CustomEvent:function(type,options){this.type=type;this.detail=options.detail;},
      localStorage:{getItem:()=>null,setItem:()=>{}},
      document:{readyState:'loading',referrer:'',addEventListener:()=>{},
        createElement:tag=>({tag}),head:{appendChild:s=>appended.push(s)}}});
    vm.runInContext(fs.readFileSync(process.argv[2],'utf8'),context);
    vm.runInContext(fs.readFileSync(process.argv[3],'utf8'),context);
    const initiallyOff=!window._paq && !window.BiocoConsent.has('analytics');
    const storage=analytics=>window.dispatchEvent({type:'storage',key:'bioco-consent-v1',
      newValue:JSON.stringify({version:1,at:Date.now(),analytics,maps:false})});
    storage(true);sent.push(...window._paq);window._paq={push:row=>sent.push(row)};
    storage(true);storage(false);storage(false);storage(true);
    window.dispatchEvent({type:'storage',key:'bioco-consent-v1',newValue:'invalid'});
    const invalidRevokes=!window.BiocoConsent.has('analytics');
    storage(true);window.dispatchEvent({type:'storage',key:null,newValue:null});
    process.stdout.write(JSON.stringify({initiallyOff,invalidRevokes,
      clearedRevokes:!window.BiocoConsent.has('analytics'),sent,appended}));
    '''
    output = subprocess.run(['node', '-e', node, (CORE / 'content/consent-texts.json').read_text(),
        str(CORE / 'assets/bioco-consent.js'), str(CORE / 'assets/bioco-matomo.js')],
        check=True, capture_output=True, text=True)
    behavior = json.loads(output.stdout)
    assert behavior['initiallyOff'] and behavior['invalidRevokes'] and behavior['clearedRevokes']
    assert behavior['sent'] == expected_commands('https://bioco.ch/abos/') + [
        ['forgetConsentGiven'], ['setConsentGiven'], ['forgetConsentGiven'],
        ['setConsentGiven'], ['forgetConsentGiven']]
    assert len(behavior['appended']) == 1
