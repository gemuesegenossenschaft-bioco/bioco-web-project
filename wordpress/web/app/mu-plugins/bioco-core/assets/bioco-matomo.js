/** Production-only config is supplied by bioco-core. No form/personal-data events. */
(function () {
  'use strict';
  var config = window.biocoMatomoConfig;
  if (!config) return;
  var queue = window._paq = window._paq || [];
  queue.push(['setTrackerUrl', config.url + 'matomo.php']);
  queue.push(['setSiteId', config.siteId]);
  queue.push(['disableCookies']);
  queue.push(['trackPageView']);
  queue.push(['enableLinkTracking']);
  var script = document.createElement('script');
  script.async = true;
  script.src = config.url + 'matomo.js';
  document.head.appendChild(script);
})();
