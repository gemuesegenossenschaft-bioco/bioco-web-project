/**
 * Pricing-calculator view script (W9 interactive blocks, issue #96).
 * Reimplements PricingCalculator.tsx's tier-select + additional-shares
 * arithmetic in vanilla JS, keeping the server-rendered DOM (render.php) in
 * sync via data-pc-* hooks instead of re-rendering. Writes the selection
 * into the "Jetzt mitmachen" link as ?abo=&shares=&additional=, mirroring
 * how the Next.js calculator hands off to /anmeldung + MembershipForm.
 * Plain ES5-safe vanilla JS: the theme has no build step.
 */
(function () {
  'use strict';

  function formatNumber(value) {
    return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, "'");
  }

  function initCalculator(container) {
    var sharePrice = parseInt(container.getAttribute('data-share-price'), 10) || 0;
    var signupUrl = container.getAttribute('data-signup-url') || '';
    var additionalShares = 0;

    var tierButtons = container.querySelectorAll('[data-pc-action="select-tier"]');
    var addButton = container.querySelector('[data-pc-action="add-share"]');
    var removeButton = container.querySelector('[data-pc-action="remove-share"]');

    function activeTierButton() {
      for (var i = 0; i < tierButtons.length; i++) {
        if (tierButtons[i].className.indexOf('active') !== -1) return tierButtons[i];
      }
      return tierButtons[0] || null;
    }

    function setField(name, value) {
      var el = container.querySelector('[data-pc-field="' + name + '"]');
      if (el) el.textContent = value;
    }

    function render() {
      var tierButton = activeTierButton();
      if (!tierButton) return;

      var slug = tierButton.getAttribute('data-tier-slug');
      var name = tierButton.getAttribute('data-tier-name');
      var price = parseInt(tierButton.getAttribute('data-tier-price'), 10) || 0;
      var shares = parseInt(tierButton.getAttribute('data-tier-shares'), 10) || 0;
      var work = parseInt(tierButton.getAttribute('data-tier-work'), 10) || 0;

      var totalShares = shares + additionalShares;
      var sharesCost = totalShares * sharePrice;
      var totalCost = price + sharesCost;

      var aboRow = container.querySelector('[data-pc-row="abo"]');
      if (aboRow) aboRow.style.display = slug === 'kein' ? 'none' : '';

      setField('abo-name', name);
      setField('abo-work', String(work));
      setField('abo-price', formatNumber(price));
      setField('abo-price-total', formatNumber(price));
      setField('shares-count', String(totalShares));
      setField('shares-required', String(shares));
      setField('shares-total', formatNumber(sharesCost));
      setField('total', 'CHF ' + formatNumber(totalCost) + '.-');

      var sharesNote = container.querySelector('[data-pc-field="shares-note"]');
      if (sharesNote) {
        if (slug === 'kein') {
          sharesNote.textContent = '(ohne Gemüsekorb)';
        } else if (additionalShares > 0) {
          sharesNote.textContent = '(' + shares + ' erforderlich + ' + additionalShares + ' zusätzlich)';
        } else {
          sharesNote.textContent = '(' + shares + ' erforderlich)';
        }
      }

      if (removeButton) removeButton.style.display = additionalShares > 0 ? '' : 'none';

      var keinInfo = container.querySelector('[data-pc-role="kein-info"]');
      var ctaBlock = container.querySelector('[data-pc-role="cta-block"]');
      var showCta = slug !== 'kein' || additionalShares > 0;
      if (ctaBlock) ctaBlock.style.display = showCta ? '' : 'none';
      if (keinInfo) keinInfo.style.display = !showCta ? '' : 'none';

      var cta = container.querySelector('[data-pc-field="cta"]');
      if (cta && signupUrl) {
        var separator = signupUrl.indexOf('?') === -1 ? '?' : '&';
        cta.setAttribute(
          'href',
          signupUrl + separator + 'abo=' + encodeURIComponent(slug) + '&shares=' + totalShares + '&additional=' + additionalShares
        );
      }
    }

    for (var i = 0; i < tierButtons.length; i++) {
      tierButtons[i].addEventListener('click', function (event) {
        for (var j = 0; j < tierButtons.length; j++) {
          tierButtons[j].className = tierButtons[j].className.replace(/\s*active\b/, '');
        }
        event.currentTarget.className += ' active';
        additionalShares = 0;
        render();
      });
    }

    if (addButton) {
      addButton.addEventListener('click', function () {
        additionalShares += 1;
        render();
      });
    }

    if (removeButton) {
      removeButton.addEventListener('click', function () {
        additionalShares = Math.max(0, additionalShares - 1);
        render();
      });
    }

    render();
  }

  function init() {
    var containers = document.querySelectorAll('.cms-pricing-calculator .pricing-calculator');
    for (var i = 0; i < containers.length; i++) {
      initCalculator(containers[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
