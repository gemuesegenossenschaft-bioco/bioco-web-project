// Deterministic simulator for the utility-row auto-hide scroll handler.
//
// It executes the real `bioco-navigation.js` in a VM against a minimal DOM
// stub that reproduces the live defect: collapsing/expanding the utility row
// changes the document layout, so the browser shifts `scrollY` by the same
// amount over the CSS transition — a layout-induced scroll that must not be
// read as user intent.
//
// Prints one JSON object with the outcome of every scenario; assertions live
// in tests/test_wordpress_navigation_scroll.py.

import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const SOURCE = path.resolve(
  HERE,
  '../../wordpress/web/app/mu-plugins/bioco-core/assets/bioco-navigation.js',
);

const PRIMARY_HEIGHT = 76;
const UTILITY_HEIGHT = 70;
const TRANSITION_STEP = 8; // px per animation frame (~.22s ease, discretised)

function makeEnv({ reducedMotion = false, mobile = false, duplicateShell = false } = {}) {
  const state = {
    scrollY: 0,
    utility: mobile ? 0 : UTILITY_HEIGHT, // mobile CSS: display:none
    hidden: false,
    classChanges: [], // one entry per real hidden-state flip
    frames: 0,
  };

  const rafQueue = [];
  const scrollListeners = [];
  const scrollListenerOptions = [];

  const target = () => {
    if (mobile) return 0;
    if (state.focusWithin) return UTILITY_HEIGHT;
    return state.hidden ? 0 : UTILITY_HEIGHT;
  };

  const setHidden = (next) => {
    if (next === state.hidden) return;
    state.hidden = next;
    state.classChanges.push({ frame: state.frames, hidden: next });
    if (reducedMotion) applyLayout(target() - state.utility); // instant
  };

  // A layout height change in the sticky header shifts the scroll position by
  // the same amount — but only while there is document above the viewport.
  function applyLayout(diff) {
    if (!diff) return false;
    state.utility += diff;
    if (state.scrollY <= 0) return false;
    const before = state.scrollY;
    state.scrollY = Math.max(0, state.scrollY + diff);
    return state.scrollY !== before;
  }

  const classes = new Set();
  const classList = {
    contains: (name) => classes.has(name),
    add: (name) => {
      classes.add(name);
      if (name === 'is-utility-hidden') setHidden(true);
    },
    remove: (name) => {
      classes.delete(name);
      if (name === 'is-utility-hidden') setHidden(false);
    },
    toggle: (name, force) => {
      const next = force === undefined ? !classes.has(name) : !!force;
      if (next) classList.add(name);
      else classList.remove(name);
      return next;
    },
  };

  const utilityEl = {
    contains: () => !!state.focusWithin,
  };

  const shell = {
    classList,
    querySelector: (sel) => (sel === '.bioco-utility-nav' ? utilityEl : null),
    get offsetHeight() {
      return PRIMARY_HEIGHT + state.utility;
    },
    getBoundingClientRect() {
      return { height: PRIMARY_HEIGHT + state.utility, top: 0 };
    },
  };

  // A second, non-authoritative shell: duplicated markup further down the
  // document. Nothing may drive it — only the first match is controlled.
  const decoyClasses = new Set();
  const decoyChanges = [];
  const decoy = {
    classList: {
      contains: (name) => decoyClasses.has(name),
      add: (name) => {
        decoyClasses.add(name);
        decoyChanges.push(['add', name]);
      },
      remove: (name) => {
        decoyClasses.delete(name);
        decoyChanges.push(['remove', name]);
      },
      toggle: (name, force) => {
        const next = force === undefined ? !decoyClasses.has(name) : !!force;
        decoyChanges.push(['toggle', name, next]);
        if (next) decoyClasses.add(name);
        else decoyClasses.delete(name);
        return next;
      },
    },
    querySelector: (sel) => (sel === '.bioco-utility-nav' ? { contains: () => false } : null),
    offsetHeight: PRIMARY_HEIGHT + UTILITY_HEIGHT,
    getBoundingClientRect: () => ({ height: PRIMARY_HEIGHT + UTILITY_HEIGHT, top: 0 }),
  };
  const shells = duplicateShell ? [shell, decoy] : [shell];

  const sandbox = {
    window: {
      get scrollY() {
        return state.scrollY;
      },
      addEventListener: (type, fn, opts) => {
        if (type !== 'scroll') return;
        scrollListeners.push(fn);
        scrollListenerOptions.push(opts);
      },
      requestAnimationFrame: (cb) => rafQueue.push(cb),
      matchMedia: (query) => ({
        matches: reducedMotion && query.includes('reduced-motion'),
        addEventListener: () => {},
        addListener: () => {},
      }),
    },
    document: {
      readyState: 'complete',
      activeElement: null,
      addEventListener: () => {},
      querySelectorAll: (sel) =>
        sel === '.bioco-navigation-shell' ? shells.slice() : [],
      querySelector: (sel) =>
        sel === '.bioco-navigation-shell' ? shells[0] : null,
    },
  };
  sandbox.requestAnimationFrame = sandbox.window.requestAnimationFrame;
  sandbox.globalThis = sandbox;

  vm.createContext(sandbox);
  vm.runInContext(fs.readFileSync(SOURCE, 'utf8'), sandbox, { filename: SOURCE });

  const fireScroll = () => scrollListeners.forEach((fn) => fn({ type: 'scroll' }));
  const flushFrames = () => {
    const due = rafQueue.splice(0, rafQueue.length);
    due.forEach((cb) => cb(state.frames));
  };

  // One animation frame: advance the CSS transition (which drags scrollY with
  // it and fires scroll), then run whatever rAF work the handler queued.
  const frame = () => {
    state.frames += 1;
    const want = target();
    if (state.utility !== want) {
      const step = Math.sign(want - state.utility) *
        Math.min(TRANSITION_STEP, Math.abs(want - state.utility));
      if (applyLayout(step)) fireScroll();
    }
    flushFrames();
  };

  const userScroll = (dy) => {
    state.scrollY = Math.max(0, state.scrollY + dy);
    fireScroll();
    frame();
  };

  const settle = (n = 60) => {
    for (let i = 0; i < n; i += 1) frame();
  };

  return { state, userScroll, settle, frame, scrollListenerOptions, shell, decoyChanges };
}

const scenarios = {};

// 1. The live defect: one wheel-down of 700px on desktop.
{
  const env = makeEnv();
  env.userScroll(700);
  env.settle(60); // > 1.2s worth of frames
  scenarios.wheel_down_700 = {
    hidden: env.state.hidden,
    utility_height: env.state.utility,
    class_changes: env.state.classChanges.length,
  };
}

// 2. Slow scrolling: sub-threshold deltas must accumulate into a hide.
{
  const env = makeEnv();
  // 2px per event — every single delta is below the 4px threshold, so this
  // only ever hides if sub-threshold movement accumulates. Runs deep enough
  // to leave the top zone and the header-height hide guard.
  for (let i = 0; i < 100; i += 1) env.userScroll(2);
  env.settle(40);
  scenarios.slow_accumulates = {
    hidden: env.state.hidden,
    class_changes: env.state.classChanges.length,
  };
}

// 3. Genuine scroll up after the row has collapsed must reveal it again.
{
  const env = makeEnv();
  env.userScroll(700);
  env.settle(60);
  const hidden_after_down = env.state.hidden;
  env.userScroll(-30);
  env.settle(60);
  scenarios.genuine_up_reveals = {
    hidden_after_down,
    hidden: env.state.hidden,
    utility_height: env.state.utility,
  };
}

// 4. Returning to the top zone always reveals.
{
  const env = makeEnv();
  env.userScroll(700);
  env.settle(60);
  env.userScroll(-1000);
  env.settle(60);
  scenarios.top_reveals = {
    hidden: env.state.hidden,
    scroll_y: env.state.scrollY,
    utility_height: env.state.utility,
  };
}

// 5. Focus inside the utility row keeps it visible while scrolling down.
{
  const env = makeEnv();
  env.state.focusWithin = true;
  env.userScroll(700);
  env.settle(60);
  scenarios.focus_within_keeps_visible = {
    hidden: env.state.hidden,
    utility_height: env.state.utility,
  };
}

// 6. Reduced motion (instant height change) must behave identically.
{
  const env = makeEnv({ reducedMotion: true });
  env.userScroll(700);
  env.settle(20);
  const down = { hidden: env.state.hidden, changes: env.state.classChanges.length };
  env.userScroll(-30);
  env.settle(20);
  scenarios.reduced_motion = {
    hidden_after_down: down.hidden,
    class_changes_after_down: down.changes,
    hidden_after_up: env.state.hidden,
  };
}

// 7. Mobile (utility row display:none — no layout shift at all).
{
  const env = makeEnv({ mobile: true });
  env.userScroll(700);
  env.settle(30);
  scenarios.mobile_no_layout_shift = {
    class_changes: env.state.classChanges.length,
    scroll_y: env.state.scrollY,
  };
}

// 8. Listener registration contract.
{
  const env = makeEnv();
  scenarios.listener = {
    passive: env.scrollListenerOptions.every((o) => o && o.passive === true),
    count: env.scrollListenerOptions.length,
  };
}

// 9. Duplicate shell markup: only the first, authoritative shell is controlled,
// there is still exactly one passive listener, and the first shell behaves
// exactly as it does on its own — no second controller, no oscillation.
{
  const env = makeEnv({ duplicateShell: true });
  env.userScroll(700);
  env.settle(60);
  const hidden_after_down = env.state.hidden;
  const changes_after_down = env.state.classChanges.length;
  env.userScroll(-30);
  env.settle(60);
  scenarios.duplicate_shell = {
    listener_count: env.scrollListenerOptions.length,
    listener_passive: env.scrollListenerOptions.every((o) => o && o.passive === true),
    hidden_after_down,
    class_changes_after_down: changes_after_down,
    hidden_after_up: env.state.hidden,
    utility_height: env.state.utility,
    total_class_changes: env.state.classChanges.length,
    decoy_touches: env.decoyChanges.length,
  };
}

process.stdout.write(JSON.stringify(scenarios, null, 2));
