"""Isolated HTTP requests from the real consent/adapter to a tracker transport double.

Every browser request is fulfilled locally. No production host or Matomo service
is contacted. The double consumes Matomo's command API and uses browser fetch so
URL fields and the actual HTTP Referer header are both observable.
"""
import json
from pathlib import Path
from urllib.parse import parse_qs, urlsplit

import pytest
from playwright.sync_api import sync_playwright

CORE = Path(__file__).parents[1] / 'wordpress/web/app/mu-plugins/bioco-core'
TEXTS = json.loads((CORE / 'content/consent-texts.json').read_text())
TRACKER = r'''
(() => {
  let trackerUrl, siteId, url, referrer, consent=false;
  const execute = row => {
    switch(row[0]) {
      case 'setTrackerUrl': trackerUrl=row[1]; break;
      case 'setSiteId': siteId=row[1]; break;
      case 'setCustomUrl': url=row[1]; break;
      case 'setReferrerUrl': referrer=row[1]; break;
      case 'setConsentGiven': consent=true; break;
      case 'forgetConsentGiven': consent=false; break;
      case 'trackPageView':
        if(consent) fetch(trackerUrl + '?' + new URLSearchParams({
          idsite:siteId, rec:'1', url, urlref:referrer
        }));
    }
  };
  window._paq.forEach(execute);
  window._paq={push:execute};
  window.testTrackerReady=true;
})();
'''


@pytest.mark.parametrize('tracker_origin', ['https://public.example.test', 'https://analytics.example.test'])
def test_synthetic_newsletter_url_is_safe_in_http_request_and_regrant_does_not_repeat(tracker_origin):
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        context = browser.new_context()
        requests = []

        def fulfill(route):
            request = route.request
            path = urlsplit(request.url).path
            if path.startswith('/matomo/'):
                requests.append(dict(url=request.url, headers=request.all_headers()))
            if path == '/matomo/matomo.js':
                route.fulfill(body=TRACKER, content_type='text/javascript')
            elif path == '/matomo/matomo.php':
                route.fulfill(body='', headers={'Access-Control-Allow-Origin': '*'})
            else:
                route.fulfill(body='<html><head></head><body></body></html>', content_type='text/html',
                              headers={'Referrer-Policy': 'strict-origin-when-cross-origin'})

        context.route('**/*', fulfill)
        page = context.new_page()
        # Same-origin referrer deliberately contains synthetic sensitive values.
        page.goto('https://public.example.test/mail/?unsubscribe_token=SYNTHETIC_UNSUBSCRIBE&email=person%40example.test')
        target = ('https://public.example.test/newsletter-bestaetigen?token=SYNTHETIC_DOI_SECRET'
                  '&email=person%40example.test&utm_source=newsletter#SYNTHETIC_FRAGMENT')
        page.evaluate('(url)=>location.href=url', target)
        page.wait_for_url(target)
        assert 'SYNTHETIC_UNSUBSCRIBE' in page.evaluate('document.referrer')
        page.evaluate('(texts)=>window.biocoConsentTexts=texts', TEXTS)
        page.add_script_tag(path=str(CORE / 'assets/bioco-consent.js'))
        page.evaluate('(origin)=>window.biocoMatomoConfig={url:origin+"/matomo/",siteId:"1"}', tracker_origin)
        page.add_script_tag(path=str(CORE / 'assets/bioco-matomo.js'))
        assert requests == []
        page.get_by_role('button', name=TEXTS['accept'], exact=True).click()
        page.wait_for_function('window.testTrackerReady === true')
        page.wait_for_timeout(100)
        assert len(requests) == 2
        fields = parse_qs(urlsplit(requests[1]['url']).query, keep_blank_values=True)
        assert fields == dict(idsite=['1'], rec=['1'],
            url=['https://public.example.test/newsletter-bestaetigen/?utm_source=newsletter'],
            urlref=['https://public.example.test/mail/'])
        assert 'referer' not in requests[0]['headers']
        if tracker_origin == 'https://public.example.test':
            assert 'referer' not in requests[1]['headers']
            assert page.locator('meta[name="referrer"]').get_attribute('content') == 'no-referrer'
        else:
            assert requests[1]['headers']['referer'] == 'https://public.example.test/'
            assert page.locator('meta[name="referrer"]').count() == 0
        assert 'SYNTHETIC' not in json.dumps(requests)
        assert 'person' not in json.dumps(requests)
        page.evaluate('window.BiocoConsent.revoke()')
        page.get_by_role('button', name=TEXTS['settings'], exact=True).click()
        page.get_by_role('button', name=TEXTS['accept'], exact=True).click()
        page.wait_for_timeout(100)
        assert len(requests) == 2
        browser.close()
