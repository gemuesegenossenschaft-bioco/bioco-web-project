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

    var markers = [];
    for (var i = 0; i < locations.length; i++) {
      var loc = locations[i];
      var marker = window.L.marker([loc.lat, loc.lng]).addTo(map);
      var popup = '<strong>' + escapeHtml(loc.name) + '</strong>';
      if (loc.description) {
        popup += '<br>' + descriptionHtml(loc.description).replace(/\n/g, '<br>');
      }
      if (loc.website) {
        try {
          var website = new URL(loc.website);
          if (website.protocol === 'https:' || website.protocol === 'http:') {
            popup += '<br><a href="' + escapeAttribute(website.href) + '" target="_blank" rel="noopener noreferrer">' +
              escapeHtml(loc.website_label || loc.website) + '</a>';
          }
        } catch (error) { /* Invalid public website fields do not create links. */ }
      }
      marker.bindPopup(popup);
      markers.push(marker);
    }

    if (markers.length > 1 && wrapper.getAttribute('data-map-view') !== 'fixed') {
      var group = window.L.featureGroup(markers);
      map.fitBounds(group.getBounds().pad(0.1));
    }
    return map;
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
  }

  function escapeAttribute(value) {
    return escapeHtml(value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function descriptionHtml(value) {
    var parts = String(value).split(/(https?:\/\/[^\s<>"']+)/gi);
    return parts.map(function (part, index) {
      var url = part.replace(/[.,;!?)]+$/, '');
      if (index % 2 === 1) {
        try {
          var parsed = new URL(url);
          if ((parsed.protocol === 'http:' || parsed.protocol === 'https:') && parsed.hostname) {
            return '<a href="' + escapeAttribute(parsed.href) + '" target="_blank" rel="noopener noreferrer">' +
              escapeHtml(url) + '</a>' + escapeHtml(part.slice(url.length));
          }
        } catch (error) { /* Keep malformed URLs as text. */ }
      }
      return escapeHtml(part);
    }).join('');
  }

  function init() {
    var wrappers = document.querySelectorAll('.cms-depot-map .map-wrapper');
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
