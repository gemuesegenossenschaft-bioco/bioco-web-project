(() => {
  const initMenus = () => document.querySelectorAll('.bioco-primary-nav').forEach((nav) => {
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

  // Utility row hides on scroll down, returns on scroll up and at page top.
  // Wired independently of the mobile toggle so it survives menu markup changes.
  const initUtilityAutoHide = () => {
    const shells = document.querySelectorAll('.bioco-navigation-shell');
    if (!shells.length) return;
    const TOP_ZONE = 24;
    const DELTA = 4;
    let anchorY = window.scrollY;
    let ticking = false;
    const sync = () => {
      const currentY = window.scrollY;
      const delta = currentY - anchorY;
      shells.forEach((shell) => {
        const utility = shell.querySelector('.bioco-utility-nav');
        const focused = utility && utility.contains(document.activeElement);
        if (currentY <= TOP_ZONE || focused) {
          shell.classList.remove('is-utility-hidden');
        } else if (Math.abs(delta) >= DELTA) {
          shell.classList.toggle('is-utility-hidden', delta > 0);
        }
      });
      if (currentY <= TOP_ZONE || Math.abs(delta) >= DELTA) {
        anchorY = currentY;
      }
      ticking = false;
    };
    window.addEventListener('scroll', () => {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(sync);
    }, { passive: true });
  };

  const init = () => {
    initMenus();
    initUtilityAutoHide();
  };
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
