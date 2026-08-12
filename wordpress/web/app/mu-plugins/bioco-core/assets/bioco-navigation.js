(() => {
  const init = () => document.querySelectorAll('.bioco-primary-nav').forEach((nav) => {
    const toggle = nav.querySelector('.bioco-menu-toggle');
    if (!toggle) return;
    const close = () => {
      nav.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Menü öffnen');
    };
    toggle.addEventListener('click', () => {
      const open = !nav.classList.contains('is-open');
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Menü schliessen' : 'Menü öffnen');
      if (open) nav.querySelector('#bioco-primary-menu a')?.focus();
    });
    nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
    nav.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        close();
        toggle.focus();
      }
    });
  });
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
