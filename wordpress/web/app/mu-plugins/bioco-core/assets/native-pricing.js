/* Saved Divi modules own copy and design; this controller changes amounts only. */
(function () {
  'use strict';
  function start(root) {
    if (root.dataset.biocoPriceReady) return;
    var catalog;
    try { catalog = JSON.parse(root.dataset.biocoPrices); } catch (_) { return; }
    if (!catalog || !Array.isArray(catalog.tiers) || !Number.isSafeInteger(catalog.share_price) || catalog.share_price < 0) return;
    if (catalog.tiers.some(function (tier) {
      return ['price', 'shares', 'work', 'persons'].some(function (key) { return !Number.isSafeInteger(tier[key]) || tier[key] < 0; })
        || !Number.isSafeInteger(tier.price + tier.shares * catalog.share_price);
    })) return;
    root.dataset.biocoPriceReady = '1';
    var choices = root.querySelectorAll('a[href^="#bioco-price-"]');
    var selected = catalog.tiers[0] || { key: 'kein', name: '', price: 0, shares: 0, work: 0, persons: 0 };
    var additional = 0;
    var cta = root.querySelector('.bioco-price-signup a');
    var destination = cta && cta.getAttribute('href');
    var display = new WeakMap();
    function visibility(selector, visible) {
      root.querySelectorAll(selector).forEach(function (el) {
        if (!display.has(el)) display.set(el, el.style.display);
        el.hidden = !visible; el.style.display = visible ? display.get(el) : 'none';
      });
    }
    function number(value) { return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, "'"); }
    function field(name, value) {
      root.querySelectorAll('.bioco-price-value-' + name).forEach(function (module) {
        var leaf = module.querySelector('.et_pb_text_inner') || module.querySelector('h1,h2,h3,h4,h5,h6');
        if (leaf) leaf.textContent = value;
      });
    }
    function render() {
      var shares = selected.shares + additional;
      field('name', selected.name); field('work', number(selected.work));
      field('price', number(selected.price)); field('shares', number(shares));
      field('additional', number(additional)); field('share-cost', number(shares * catalog.share_price));
      field('total', number(selected.price + shares * catalog.share_price));
      visibility('.bioco-price-subscription', selected.key !== 'kein');
      visibility('.bioco-price-remove', additional > 0);
      var visible = selected.key !== 'kein' || additional > 0;
      visibility('.bioco-price-signup', visible);
      visibility('.bioco-price-no-selection', !visible);
      choices.forEach(function (el) { el.setAttribute('aria-pressed', String(el.getAttribute('href') === '#bioco-price-' + selected.key)); });
      if (cta && destination) {
        var url = new URL(destination, document.baseURI);
        url.searchParams.set('abo', selected.key); url.searchParams.set('shares', String(shares));
        url.searchParams.set('additional', String(additional)); cta.setAttribute('href', url.href);
      }
    }
    root.addEventListener('click', function (event) {
      var link = event.target.closest('a');
      if (!link || !root.contains(link)) return;
      var href = link.getAttribute('href') || '';
      if (!href.startsWith('#bioco-price-') && href !== '#bioco-share-add' && href !== '#bioco-share-remove') return;
      event.preventDefault();
      if (href === '#bioco-share-add') {
        if (!Number.isSafeInteger(selected.shares + additional + 1)
          || !Number.isSafeInteger(selected.price + (selected.shares + additional + 1) * catalog.share_price)) return;
        additional += 1;
      }
      else if (href === '#bioco-share-remove') additional = Math.max(0, additional - 1);
      else {
        var key = href.slice('#bioco-price-'.length);
        var tier = key === 'kein' ? { key: 'kein', name: link.textContent.trim(), price: 0, shares: 0, work: 0, persons: 0 }
          : catalog.tiers.find(function (item) { return item.key === key; });
        if (!tier) return;
        selected = tier; additional = 0;
      }
      render();
    });
    render();
  }
  function init() { document.querySelectorAll('[data-bioco-prices]').forEach(start); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
