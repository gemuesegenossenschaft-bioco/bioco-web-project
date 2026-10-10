/** Consent-gated, cookieless pageviews. No form or automatic link events. */
(function () {
  'use strict';
  var config = window.biocoMatomoConfig;
  if (!config) return;
  var page;
  try { page = new URL(window.location.href); } catch (error) { return; }
  var excluded = ['et_fb', 'et_pb_preview', 'preview_id', 'preview_nonce',
    'customize_changeset_uuid', 'customize_theme', 'customize_messenger_channel',
    'release_check', 'release-check'];
  if (excluded.some(function (key) {return page.searchParams.has(key);}) ||
      page.searchParams.getAll('preview').some(function (value) {return value === 'true' || value === '1';}) ||
      page.searchParams.getAll('bioco_qa').indexOf('1') !== -1) return;

  // Explicit keys, never a wildcard. Campaign values must be public slug labels.
  var campaigns = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
    'mtm_source', 'mtm_medium', 'mtm_campaign', 'mtm_keyword', 'mtm_kwd', 'mtm_content', 'mtm_cid',
    'mtm_group', 'mtm_placement'];
  var tiers = ['kein', 'halb-1-person', 'standard-2-3-personen', 'doppel-4-6-personen'];
  function safeUrl(value) {
    if (!value) return '';
    try {
      var url = new URL(value);
      if (url.protocol !== 'https:' && url.protocol !== 'http:') return '';
      // Email-bearing, nested or malformed percent-encoded paths cannot identify
      // a public page safely. Valid UTF-8 slugs such as umlauts stay intact.
      var path;
      try { path = decodeURIComponent(url.pathname); } catch (error) { return url.origin + '/'; }
      if (/[@%]/.test(path)) return url.origin + '/';
      var kept = [];
      campaigns.concat(['abo', 'shares', 'additional']).sort().forEach(function (key) {
        var values = url.searchParams.getAll(key);
        if (values.length !== 1) return;
        var value = values[0];
        if (key === 'abo') {
          if (tiers.indexOf(value) === -1) return;
        } else if (key === 'shares' || key === 'additional') {
          if (!/^\d{1,3}$/.test(value) || Number(value) > 100) return;
          value = String(Number(value));
        } else if (!/^[A-Za-z0-9][A-Za-z0-9._~-]{0,79}$/.test(value)) return;
        kept.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
      });
      // Drop credentials, fragments and every unreviewed query key/value.
      return url.origin + url.pathname.replace(/\/+$/, '') + '/' + (kept.length ? '?' + kept.join('&') : '');
    } catch (error) { return ''; }
  }
  var customUrl = safeUrl(page.href), referrerUrl = safeUrl(document.referrer);
  if (!customUrl) return;
  var loaded = false, allowed = false, pageview = false;
  function update() {
    var consent = window.BiocoConsent && window.BiocoConsent.has('analytics');
    if (!consent) {
      if (loaded && allowed) {
        // Before Matomo drains its array, withdrawal cancels the pending view.
        if (Array.isArray(window._paq)) {
          for (var i = window._paq.length - 1; i >= 0; i--) {
            var command = window._paq[i];
            if (Array.isArray(command) && command[0] === 'trackPageView') pageview = false;
            if (Array.isArray(command) && (command[0] === 'setConsentGiven' || command[0] === 'trackPageView')) window._paq.splice(i, 1);
          }
        }
        window._paq.push(['forgetConsentGiven']);
      }
      allowed = false; return;
    }
    if (allowed) return;
    var queue = window._paq = window._paq || [];
    if (!loaded) {
      queue.push(['setTrackerUrl', config.url + 'matomo.php']);
      queue.push(['setSiteId', config.siteId]);
      queue.push(['requireConsent']);
      queue.push(['disableCookies']);
      queue.push(['setCustomUrl', customUrl]);
      queue.push(['setReferrerUrl', referrerUrl]);
    }
    queue.push(['setConsentGiven']);
    if (!pageview) {queue.push(['trackPageView']); pageview = true;}
    if (!loaded) {
      // Also protect the HTTP Referer on same-origin tracker transports.
      if (new URL(config.url).origin === page.origin) {
        var policy = document.createElement('meta');
        policy.name = 'referrer'; policy.content = 'no-referrer'; document.head.appendChild(policy);
      }
      var script = document.createElement('script');
      script.async = true; script.referrerPolicy = 'no-referrer';
      script.src = config.url + 'matomo.js'; document.head.appendChild(script);
      loaded = true;
    }
    allowed = true;
  }
  window.addEventListener('bioco:consent-change', update);
  update();
})();
