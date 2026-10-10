"""Real browser consent and resource gates. Coverage map: tests/README.md."""
import json
from pathlib import Path

import pytest
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / 'wordpress/web/app/mu-plugins/bioco-core'
TEXTS = json.loads((CORE / 'content/consent-texts.json').read_text())


@pytest.fixture
def page():
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        context = browser.new_context(viewport={'width': 390, 'height': 844})
        page = context.new_page()
        page.route('https://consent.example.test/**', lambda route: route.fulfill(body='<html><head></head><body></body></html>', content_type='text/html'))
        page.route('https://matomo.example.test/**', lambda route: route.fulfill(body='', content_type='text/javascript'))
        page.goto('https://consent.example.test/')
        yield page
        browser.close()


def install(page):
    page.evaluate('(texts) => window.biocoConsentTexts=texts', TEXTS)
    page.add_style_tag(path=str(CORE / 'assets/bioco-consent.css'))
    page.add_script_tag(path=str(CORE / 'assets/bioco-consent.js'))
    page.evaluate("window.biocoMatomoConfig={url:'https://matomo.example.test/',siteId:'1'}")
    page.add_script_tag(path=str(CORE / 'assets/bioco-matomo.js'))


def test_no_tracker_or_map_loads_before_consent_and_choice_can_be_withdrawn(page):
    requests = []
    page.on('request', lambda request: requests.append(request.url))
    install(page)
    assert not any('matomo.example.test' in url for url in requests)
    assert page.locator('.bioco-consent-panel').is_visible()
    page.get_by_role('button', name=TEXTS['accept'], exact=True).click()
    page.wait_for_function("document.querySelector('script[src=" + '"https://matomo.example.test/matomo.js"' + "]') !== null")
    assert page.evaluate("window.BiocoConsent.has('analytics')") is True
    assert page.evaluate("window._paq.filter(row=>row[0]==='trackPageView').length") == 1
    page.get_by_role('button', name=TEXTS['settings'], exact=True).click()
    page.get_by_role('button', name=TEXTS['reject'], exact=True).click()
    assert page.evaluate("window.BiocoConsent.has('analytics')") is False
    assert page.evaluate('window._paq.slice(-1)[0]') == ['forgetConsentGiven']
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth') is True
    stored = page.evaluate("JSON.parse(localStorage.getItem('bioco-consent-v1'))")
    assert stored['analytics'] is False and stored['maps'] is False


def test_rejection_survives_reload_and_categories_are_independent(page):
    install(page)
    page.get_by_role('button', name=TEXTS['reject'], exact=True).click()
    page.reload(); install(page)
    assert not page.locator('.bioco-consent-panel').is_visible()
    assert page.locator('script[src*="matomo.example.test"]').count() == 0
    page.get_by_role('button', name=TEXTS['settings'], exact=True).click()
    page.get_by_label(TEXTS['maps'], exact=True).check()
    page.get_by_role('button', name=TEXTS['save'], exact=True).click()
    assert page.evaluate("window.BiocoConsent.has('maps')") is True
    assert page.evaluate("window.BiocoConsent.has('analytics')") is False
    assert page.locator('script[src*="matomo.example.test"]').count() == 0


def test_map_initializes_only_after_opt_in_and_is_removed_on_withdrawal(page):
    page.set_content('<section class="cms-depot-map"><div class="map-wrapper" data-center-lat="47" data-center-lng="8" data-locations="[]"></div><p>Address stays visible</p></section>')
    install(page)
    page.evaluate('''() => {
      window.tiles=0;window.removals=0;
      window.L={map:()=>({setView(){return this;},remove(){window.removals++;}}),
        tileLayer:()=>({addTo(){window.tiles++;}})};
    }''')
    page.add_script_tag(path=str(CORE / 'blocks/depot-map/view.js'))
    assert page.evaluate('window.tiles') == 0
    assert page.get_by_role('button', name=TEXTS['map_prompt'], exact=True).is_visible()
    page.get_by_role('button', name=TEXTS['accept'], exact=True).click()
    assert page.evaluate('window.tiles') == 1
    page.evaluate('window.BiocoConsent.revoke()')
    assert page.evaluate('window.removals') == 1
    assert page.get_by_text('Address stays visible').is_visible()
    assert page.get_by_role('button', name=TEXTS['map_prompt'], exact=True).is_visible()


@pytest.mark.parametrize('stored', ['broken-json', '{"version":1,"analytics":"yes","maps":true}', '{"version":1,"analytics":true,"maps":true,"at":0}'])
def test_invalid_or_expired_storage_fails_closed(page, stored):
    page.evaluate('(value)=>localStorage.setItem("bioco-consent-v1",value)', stored)
    install(page)
    assert page.evaluate("window.BiocoConsent.has('analytics')") is False
    assert page.locator('.bioco-consent-panel').is_visible()


def test_editor_text_is_not_interpreted_as_markup(page):
    page.evaluate('(texts)=>window.biocoConsentTexts=texts', TEXTS | {'text': '<img src=x onerror=alert(1)>'})
    page.add_script_tag(path=str(CORE / 'assets/bioco-consent.js'))
    assert page.locator('.bioco-consent-panel img').count() == 0
    assert '<img src=x onerror=alert(1)>' in page.locator('.bioco-consent-panel').inner_text()


def test_missing_editor_configuration_overrides_old_saved_consent(page):
    page.evaluate("localStorage.setItem('bioco-consent-v1',JSON.stringify({version:1,analytics:true,maps:true,at:Date.now()}))")
    page.add_script_tag(path=str(CORE / 'assets/bioco-consent.js'))
    assert page.evaluate("window.BiocoConsent.has('analytics')") is False
    assert page.evaluate("window.BiocoConsent.has('maps')") is False
    assert page.locator('.bioco-consent-panel').count() == 0


def test_revocation_cancels_pending_tracker_commands(page):
    install(page)
    page.get_by_role('button', name=TEXTS['accept'], exact=True).click()
    assert page.evaluate("window._paq.some(row=>row[0]==='trackPageView')") is True
    page.evaluate('window.BiocoConsent.revoke()')
    assert page.evaluate("window._paq.some(row=>['setConsentGiven','trackPageView'].includes(row[0]))") is False
    assert page.evaluate("window._paq.some(row=>row[0]==='requireConsent')") is True


def test_consent_and_withdrawal_are_synchronized_across_tabs(page):
    install(page)
    other = page.context.new_page()
    other.route('https://consent.example.test/**', lambda route: route.fulfill(body='<html><body></body></html>', content_type='text/html'))
    other.route('https://matomo.example.test/**', lambda route: route.fulfill(body='', content_type='text/javascript'))
    other.goto('https://consent.example.test/')
    install(other)
    page.get_by_role('button', name=TEXTS['accept'], exact=True).click()
    other.wait_for_function("window.BiocoConsent.has('analytics') && window.BiocoConsent.has('maps')")
    page.evaluate('window.BiocoConsent.revoke()')
    other.wait_for_function("!window.BiocoConsent.has('analytics') && !window.BiocoConsent.has('maps')")
    assert other.evaluate("window._paq.some(row=>row[0]==='forgetConsentGiven')") is True
    page.get_by_role('button', name=TEXTS['settings'], exact=True).click()
    page.get_by_role('button', name=TEXTS['accept'], exact=True).click()
    other.wait_for_function("window.BiocoConsent.has('analytics')")
    page.evaluate("localStorage.setItem('bioco-consent-v1','broken')")
    other.wait_for_function("!window.BiocoConsent.has('analytics')")
    assert other.locator('.bioco-consent-panel').is_visible()
