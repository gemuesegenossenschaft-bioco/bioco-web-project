/**
 * Gallery view script (W9 interactive blocks, issue #96).
 * Reimplements Gallery.tsx's category filter + "Mehr sehen" (show more than
 * 4) toggle in vanilla JS against the server-rendered grid (render.php).
 * Lightbox/click-to-enlarge is deferred — see render.php's docblock.
 * Plain ES5-safe vanilla JS: the theme has no build step.
 */
(function () {
  'use strict';

  function initGallery(container) {
    var grid = container.querySelector('[data-gallery-grid]');
    if (!grid) return;

    var items = grid.querySelectorAll('.gallery-item');
    var filterButtons = container.querySelectorAll('[data-gallery-filter]');
    var select = container.querySelector('[data-gallery-select]');
    var toggleButton = container.querySelector('[data-gallery-toggle]');

    var activeFilter = 'all';
    var showAll = false;

    function render() {
      var matching = [];
      for (var i = 0; i < items.length; i++) {
        var category = items[i].getAttribute('data-gallery-category');
        if (activeFilter === 'all' || category === activeFilter) matching.push(items[i]);
        else items[i].style.display = 'none';
      }

      for (var j = 0; j < matching.length; j++) {
        matching[j].style.display = showAll || j < 4 ? '' : 'none';
      }

      if (toggleButton) {
        var hasMore = matching.length > 4;
        toggleButton.parentNode.style.display = hasMore ? '' : 'none';
        toggleButton.textContent = showAll ? 'Weniger anzeigen' : 'Mehr sehen';
      }
    }

    for (var b = 0; b < filterButtons.length; b++) {
      filterButtons[b].addEventListener('click', function (event) {
        activeFilter = event.currentTarget.getAttribute('data-gallery-filter');
        showAll = false;
        for (var k = 0; k < filterButtons.length; k++) {
          filterButtons[k].className = filterButtons[k].className.replace(/\s*active\b/, '');
        }
        event.currentTarget.className += ' active';
        if (select) select.value = activeFilter;
        render();
      });
    }

    if (select) {
      select.addEventListener('change', function () {
        activeFilter = select.value;
        showAll = false;
        for (var k = 0; k < filterButtons.length; k++) {
          var isActive = filterButtons[k].getAttribute('data-gallery-filter') === activeFilter;
          filterButtons[k].className = filterButtons[k].className.replace(/\s*active\b/, '') + (isActive ? ' active' : '');
        }
        render();
      });
    }

    if (toggleButton) {
      toggleButton.addEventListener('click', function () {
        showAll = !showAll;
        render();
      });
    }

    render();
  }

  function init() {
    var containers = document.querySelectorAll('.cms-gallery .gallery-container');
    for (var i = 0; i < containers.length; i++) {
      initGallery(containers[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
