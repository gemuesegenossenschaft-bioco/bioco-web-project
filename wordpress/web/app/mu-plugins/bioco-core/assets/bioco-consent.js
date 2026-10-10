/** Local consent storage and accessible controls. Consumers fail closed without this API. */
(function () {
  'use strict';
  var key = 'bioco-consent-v1';
  var state = {analytics: false, maps: false};
  var saved = false;
  try {
    var stored = JSON.parse(localStorage.getItem(key));
    if (stored && stored.version === 1 && typeof stored.analytics === 'boolean' && typeof stored.maps === 'boolean' &&
        typeof stored.at === 'number' && stored.at <= Date.now() && Date.now() - stored.at < 180 * 86400000) {
      state = {analytics: stored.analytics, maps: stored.maps}; saved = true;
    }
  } catch (error) { /* A blocked store leaves consent off. */ }
  var panel, settings, analytics, maps, returnFocus;
  var texts = window.biocoConsentTexts || {};
  var configured = ['title','text','analytics','maps','accept','reject','save','settings','map_prompt'].every(function (key) {return typeof texts[key] === 'string' && texts[key].trim() !== '';});
  if (!configured) state = {analytics: false, maps: false};

  function notify() {
    window.dispatchEvent(new CustomEvent('bioco:consent-change', {detail: {analytics: state.analytics, maps: state.maps}}));
  }
  function open() {
    if (!panel) return;
    returnFocus = document.activeElement;
    analytics.checked = state.analytics; maps.checked = state.maps;
    panel.hidden = false; panel.querySelector('button').focus();
  }
  function choose(allowAnalytics, allowMaps) {
    state = {analytics: allowAnalytics === true, maps: allowMaps === true};
    try { localStorage.setItem(key, JSON.stringify({version: 1, at: Date.now(), analytics: state.analytics, maps: state.maps})); } catch (error) { /* Current-page choice still works. */ }
    if (panel) panel.hidden = true;
    notify();
    if (returnFocus && returnFocus.isConnected) returnFocus.focus();
    else if (settings) settings.focus();
  }
  window.BiocoConsent = {has: function (kind) {return state[kind] === true;}, open: open,
    revoke: function () {choose(false, false);}, mountMap: function (wrapper, start) {
      if (!configured) return;
      if (wrapper.biocoConsentMounted) return;
      wrapper.biocoConsentMounted = true;
      var map;
      function updateMap() {
        if (state.maps) {
          if (!map) {wrapper.innerHTML = ''; map = start(wrapper);}
        } else {
          if (map) {map.remove(); map = null;}
          wrapper.innerHTML = '';
          var button = element('button', texts.map_prompt, wrapper);
          button.type = 'button'; button.className = 'bioco-map-consent'; button.addEventListener('click', open);
        }
      }
      window.addEventListener('bioco:consent-change', updateMap);
      updateMap();
    }};

  function element(tag, text, parent) {
    var node = document.createElement(tag); node.textContent = text || ''; parent.appendChild(node); return node;
  }
  function init() {
    if (!configured) return;
    settings = element('button', texts.settings, document.body);
    settings.type = 'button'; settings.className = 'bioco-consent-settings'; settings.addEventListener('click', open);
    panel = element('section', '', document.body); panel.className = 'bioco-consent-panel'; panel.hidden = saved;
    panel.setAttribute('role', 'region'); panel.setAttribute('aria-labelledby', 'bioco-consent-title');
    element('h2', texts.title, panel).id = 'bioco-consent-title';
    element('p', texts.text, panel);
    [['analytics', texts.analytics], ['maps', texts.maps]].forEach(function (item) {
      var label = element('label', '', panel); var input = document.createElement('input'); input.type = 'checkbox';
      input.checked = state[item[0]]; label.appendChild(input); label.appendChild(document.createTextNode(' ' + item[1]));
      if (item[0] === 'analytics') analytics = input; else maps = input;
    });
    [[texts.reject, function () {choose(false, false);}], [texts.accept, function () {choose(true, true);}],
      [texts.save, function () {choose(analytics.checked, maps.checked);}]].forEach(function (item) {
      var button = element('button', item[0], panel); button.type = 'button'; button.addEventListener('click', item[1]);
    });
    notify();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
