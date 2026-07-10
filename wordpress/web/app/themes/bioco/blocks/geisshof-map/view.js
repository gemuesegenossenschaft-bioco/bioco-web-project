/**
 * Geisshof-map view script (W9 interactive blocks, issue #96).
 * Same progressive-enhancement approach as bioco/depot-map/view.js (see that
 * file for the Leaflet-deferred-to-W11 note) — duplicated rather than shared
 * because the theme has no build step to import a common module from.
 */
(function () {
  'use strict';

  function initMap(wrapper) {
    if (typeof window.L === 'undefined') return;

    var lat = parseFloat(wrapper.getAttribute('data-center-lat'));
    var lng = parseFloat(wrapper.getAttribute('data-center-lng'));
    var zoom = parseInt(wrapper.getAttribute('data-zoom'), 10) || 14;
    var raw = wrapper.getAttribute('data-locations');
    var locations = [];
    try {
      locations = JSON.parse(raw) || [];
    } catch (e) {
      locations = [];
    }

    var map = window.L.map(wrapper, {
      dragging: false,
      touchZoom: false,
      doubleClickZoom: false,
      scrollWheelZoom: false,
      boxZoom: false,
      keyboard: false,
      zoomControl: false,
    }).setView([lat, lng], zoom);

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(map);

    var firstMarker = null;
    for (var i = 0; i < locations.length; i++) {
      var loc = locations[i];
      var marker = window.L.marker([loc.lat, loc.lng]).addTo(map);
      var popup = '<strong>' + escapeHtml(loc.name) + '</strong>';
      if (loc.description) {
        popup += '<br>' + escapeHtml(loc.description).replace(/\n/g, '<br>');
      }
      marker.bindPopup(popup);
      if (!firstMarker) firstMarker = marker;
    }

    if (firstMarker) firstMarker.openPopup();
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
  }

  function init() {
    var wrappers = document.querySelectorAll('.cms-geisshof-map .map-wrapper');
    for (var i = 0; i < wrappers.length; i++) {
      initMap(wrappers[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
