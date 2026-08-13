(() => {
  const initMenus = () => document.querySelectorAll('.bioco-primary-nav').forEach((nav) => {
    const toggle = nav.querySelector('.bioco-menu-toggle');
    if (!toggle) return;
    const close = () => {
      nav.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', toggle.dataset.openLabel);
    };
    toggle.addEventListener('click', () => {
      const open = !nav.classList.contains('is-open');
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? toggle.dataset.closeLabel : toggle.dataset.openLabel);
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
    // The templates render exactly one navigation shell per document, and only
    // that shell is sticky — so a single controller over the first match is the
    // whole contract. Anything further down the document is decorative markup.
    const shell = document.querySelector('.bioco-navigation-shell');
    if (!shell) return;
    const utility = shell.querySelector('.bioco-utility-nav');
    const TOP_ZONE = 24;
    const DELTA = 4;
    // Collapsing the utility row shrinks the sticky header, so the browser
    // shifts scrollY by the same amount while the height transition runs.
    // Tracking the shell height lets us subtract that layout-induced shift and
    // keep only the part of the delta the user actually caused — without it the
    // collapse reads as a scroll-up and the row oscillates.
    let shellHeight = shell.offsetHeight;
    let travel = 0;
    let anchorY = window.scrollY;
    let ticking = false;
    const sync = () => {
      ticking = false;
      const currentY = window.scrollY;
      const height = shell.offsetHeight;
      const delta = (currentY - anchorY) + (shellHeight - height);
      anchorY = currentY;
      shellHeight = height;
      const focused = utility && utility.contains(document.activeElement);
      if (currentY <= TOP_ZONE || focused) {
        travel = 0;
        shell.classList.remove('is-utility-hidden');
        return;
      }
      if (!delta) return;
      // Reset on direction change so slow, same-direction scrolling still
      // accumulates past the threshold instead of being discarded.
      if (delta > 0 !== travel > 0) travel = 0;
      travel += delta;
      if (Math.abs(travel) < DELTA) return;
      // Only collapse once we are deeper than the header itself: closer to the
      // top, the collapse would drag scrollY back into TOP_ZONE and the row
      // would immediately reveal again.
      if (travel > 0 && currentY <= TOP_ZONE + height) return;
      shell.classList.toggle('is-utility-hidden', travel > 0);
      travel = 0;
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
