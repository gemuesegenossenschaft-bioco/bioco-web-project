/**
 * Geisshof-map view script (W9 interactive blocks, issue #96).
 * Same local Leaflet dependency as bioco/depot-map/view.js. The small script
 * stays separate because the theme has no build step for a shared module.
 */
(function () {
  'use strict';

  function initMap(wrapper) {
    if (typeof window.L === 'undefined') return;

    var lat = parseFloat(wrapper.getAttribute('data-center-lat'));
    var lng = parseFloat(wrapper.getAttribute('data-center-lng'));
    var zoom = parseInt(wrapper.getAttribute('data-zoom'), 10) || 14;
    var interaction = wrapper.getAttribute('data-map-interaction') || 'static';
    var canDrag = interaction === 'drag' || interaction === 'full';
    var full = interaction === 'full';
    var raw = wrapper.getAttribute('data-locations');
    var locations = [];
    try {
      locations = JSON.parse(raw) || [];
    } catch (e) {
      locations = [];
    }

    var map = window.L.map(wrapper, {
      dragging: canDrag,
      touchZoom: canDrag,
      doubleClickZoom: full,
      scrollWheelZoom: full,
      boxZoom: full,
      keyboard: full,
      zoomControl: canDrag,
    }).setView([lat, lng], zoom);

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(map);

    var firstMarker = null;
    var markers = [];
    for (var i = 0; i < locations.length; i++) {
      var loc = locations[i];
      var marker = window.L.marker([loc.lat, loc.lng]).addTo(map);
      var popup = '<strong>' + escapeHtml(loc.name) + '</strong>';
      if (loc.description) {
        popup += '<br>' + escapeHtml(loc.description).replace(/\n/g, '<br>');
      }
      marker.bindPopup(popup);
      if (!firstMarker) firstMarker = marker;
      markers.push(marker);
    }

    if (markers.length > 1 && wrapper.getAttribute('data-map-view') === 'fit') {
      map.fitBounds(window.L.featureGroup(markers).getBounds().pad(0.1));
    }
    if (firstMarker) firstMarker.openPopup();
    return map;
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
  }

  function init() {
    var wrappers = document.querySelectorAll('.cms-geisshof-map .map-wrapper');
    for (var i = 0; i < wrappers.length; i++) {
      if (window.BiocoConsent) window.BiocoConsent.mountMap(wrappers[i], initMap);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
