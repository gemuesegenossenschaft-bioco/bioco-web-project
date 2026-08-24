/**
 * Depot-map view script (W9 interactive blocks, issue #96).
 * Draws a non-interactive map with the locally vendored Leaflet dependency.
 * The server-rendered address list remains available if JavaScript fails.
 * Plain ES5-safe vanilla JS: the theme has no build step.
 */
(function () {
  'use strict';

  function initMap(wrapper) {
    if (typeof window.L === 'undefined') return;

    var lat = parseFloat(wrapper.getAttribute('data-center-lat'));
    var lng = parseFloat(wrapper.getAttribute('data-center-lng'));
    var zoom = parseInt(wrapper.getAttribute('data-zoom'), 10) || 12;
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

    var markers = [];
    for (var i = 0; i < locations.length; i++) {
      var loc = locations[i];
      var marker = window.L.marker([loc.lat, loc.lng]).addTo(map);
      var popup = '<strong>' + escapeHtml(loc.name) + '</strong>';
      if (loc.description) {
        popup += '<br>' + escapeHtml(loc.description).replace(/\n/g, '<br>');
      }
      marker.bindPopup(popup);
      markers.push(marker);
    }

    if (markers.length > 1) {
      var group = window.L.featureGroup(markers);
      map.fitBounds(group.getBounds().pad(0.1));
    }
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
  }

  function init() {
    var wrappers = document.querySelectorAll('.cms-depot-map .map-wrapper');
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
