/** Production-only config is supplied by bioco-core. No form/personal-data events. */
(function () {
  'use strict';
  var config = window.biocoMatomoConfig;
  if (!config) return;
  var loaded = false, allowed = false;
  function update() {
    var consent = window.BiocoConsent && window.BiocoConsent.has('analytics');
    if (!consent) {
      if (loaded && allowed) {
        // Matomo replaces this array with a proxy after initialization. Until
        // then, remove pending grants/page views before the tracker can drain it.
        if (Array.isArray(window._paq)) {
          for (var i = window._paq.length - 1; i >= 0; i--) {
            var command = window._paq[i];
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
    }
    queue.push(['setConsentGiven']);
    queue.push(['trackPageView']);
    if (!loaded) {
      queue.push(['enableLinkTracking']);
      var script = document.createElement('script');
      script.async = true; script.src = config.url + 'matomo.js'; document.head.appendChild(script);
      loaded = true;
    }
    allowed = true;
  }
  window.addEventListener('bioco:consent-change', update);
  update();
})();
