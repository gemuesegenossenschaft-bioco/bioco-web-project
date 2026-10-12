/**
 * Gallery view script (W9 interactive blocks, issue #96).
 * Applies saved categories, display count and action labels to the rendered grid.
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
    var emptyMessage = container.querySelector('[data-gallery-empty]');
    var initialCount = Number(container.getAttribute('data-gallery-limit'));
    if (!isFinite(initialCount) || initialCount <= 0 || !toggleButton) initialCount = Infinity;
    var moreLabel = container.getAttribute('data-gallery-more') || (toggleButton && toggleButton.textContent) || '';
    // Existing saved galleries retain their old collapse label until edited.
    // An explicitly empty new label omits the collapse action.
    var lessLabel = container.hasAttribute('data-gallery-less') ? container.getAttribute('data-gallery-less') : 'Weniger anzeigen';

    var activeFilter = select ? select.value : 'all';
    var showAll = false;

    function render() {
      var matching = [];
      for (var i = 0; i < items.length; i++) {
        var category = items[i].getAttribute('data-gallery-category');
        if (activeFilter === 'all' || category === activeFilter) matching.push(items[i]);
        else items[i].style.display = 'none';
      }

      for (var j = 0; j < matching.length; j++) {
        matching[j].style.display = showAll || j < initialCount ? '' : 'none';
      }

      if (toggleButton) {
        var hasMore = matching.length > initialCount;
        toggleButton.parentNode.style.display = hasMore && (!showAll || lessLabel) ? '' : 'none';
        toggleButton.textContent = showAll ? lessLabel : moreLabel;
        toggleButton.setAttribute('aria-expanded', showAll ? 'true' : 'false');
      }
      if (emptyMessage) emptyMessage.hidden = matching.length !== 0;
      for (var b = 0; b < filterButtons.length; b++) {
        var isActive = filterButtons[b].getAttribute('data-gallery-filter') === activeFilter;
        filterButtons[b].setAttribute('aria-pressed', isActive ? 'true' : 'false');
        filterButtons[b].className = filterButtons[b].className.replace(/\s*active\b/, '') + (isActive ? ' active' : '');
      }
    }

    for (var b = 0; b < filterButtons.length; b++) {
      filterButtons[b].addEventListener('click', function (event) {
        activeFilter = event.currentTarget.getAttribute('data-gallery-filter');
        showAll = false;
        if (select) select.value = activeFilter;
        render();
      });
    }

    if (select) {
      select.addEventListener('change', function () {
        activeFilter = select.value;
        showAll = false;
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
