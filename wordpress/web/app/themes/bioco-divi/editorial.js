/* Native Divi retains content, design and toggle behavior. This adds keyboard access. */
(() => {
  function init() {
    // The Visual Builder owns keyboard interaction while editors change modules.
    if (document.body.classList.contains('et-fb')) return;
    let serial = 0;
    const uniqueId = (prefix) => {
      let id;
      do { id = prefix + (++serial); } while (document.getElementById(id));
      return id;
    };
    document.querySelectorAll('.bioco-editorial-toggle').forEach((module) => {
      const title = module.querySelector(':scope > .et_pb_toggle_title');
      const content = module.querySelector(':scope > .et_pb_toggle_content');
      if (!title || !content || title.querySelector('.bioco-editorial-toggle-trigger')) return;
      const trigger = document.createElement('button');
      trigger.type = 'button';
      trigger.className = 'bioco-editorial-toggle-trigger';
      trigger.id = uniqueId('bioco-editorial-toggle-trigger-');
      if (!content.id) content.id = uniqueId('bioco-editorial-toggle-content-');
      trigger.setAttribute('aria-controls', content.id);
      if (title.querySelector('a, button, input, select, textarea, [contenteditable]')) {
        // Keep authored actions separate. The transparent button sits over Divi's icon.
        const label = title.textContent.trim();
        trigger.classList.add('bioco-editorial-toggle-trigger--icon');
        title.classList.add('bioco-editorial-toggle-title--linked');
        trigger.setAttribute('aria-label', title.getAttribute('aria-label') || label);
        if (!title.hasAttribute('aria-label') && !title.hasAttribute('aria-labelledby')) title.setAttribute('aria-label', label);
      } else {
        while (title.firstChild) trigger.appendChild(title.firstChild);
      }
      title.appendChild(trigger);
      if (!content.hasAttribute('role')) content.setAttribute('role', 'region');
      content.setAttribute('aria-labelledby', trigger.id);
      const sync = () => trigger.setAttribute('aria-expanded', String(module.classList.contains('et_pb_toggle_open')));
      sync();
      // Clicks, including native Enter/Space activation, bubble to Divi's title handler.
      new MutationObserver(sync).observe(module, { attributes: true, attributeFilter: ['class'] });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
