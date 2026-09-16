/**
 * bioco forms shared lifecycle runtime (#181). Plain ES5-safe vanilla JS
 * (the theme has no build step). Owns everything the six public form blocks
 * (contact, subscribe, visit-day, waiting-list, event-signup, membership)
 * previously duplicated: Turnstile script/widget loading with load-error
 * recovery, generic form serialization, native validity gate, pending
 * state with an in-flight guard, REST POST with strict JSON response
 * handling, message display and per-form captcha token lifecycle.
 *
 * Per-adapter differences stay in each block's thin view.js adapter
 * (selectors, localized config object name, German copy, success policy —
 * see blocks/<form>/view.js). Registered in bioco-core.php as the
 * `bioco-forms-lifecycle` dependency of every form block's view script
 * handle; the per-block localized config (restUrl + turnstileSiteKey)
 * arrives through bioco_forms_localize_block() in the bioco-forms
 * mu-plugin before the adapters mount.
 */
(function () {
  'use strict';

  var TURNSTILE_SCRIPT_ID = 'bioco-cf-turnstile-js';
  var TURNSTILE_SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
  // Bound for a script element whose load event already fired before the
  // runtime could listen (a pre-existing element may be supplied elsewhere)
  // or whose request never settles at all. Overridable per environment.
  var TURNSTILE_LOAD_TIMEOUT = parseInt(window.BIOCO_FORMS_TURNSTILE_TIMEOUT_MS, 10) || 4000;
  // Explicit failure copy for the bounded-timeout case. The canonical api.js
  // URL is the only documented integration path, so an indefinitely hung
  // transport is NOT worked around in the page (Cloudflare 302-redirects
  // every entry-URL variant onto one shared resource, so even distinct URLs
  // coalesce onto the dead request): the honest recovery is the documented
  // manual page reload. Entered values are kept, nothing is reloaded or
  // re-submitted automatically.
  // Set once a bounded attempt settled without a working global (hung
  // transport). While latched, no further script elements are created —
  // browsers coalesce in-flight requests for an identical URL and removing
  // a hung element does not abort the underlying fetch, so further same-URL
  // attempts would silently attach to the dead request forever. The latched
  // transport is still checked against a global on every submit: if the
  // API appears later from any other source, the next submit renders a
  // widget again without our loader touching the network.
  var turnstileLoadPromise = null;
  var turnstileTransportFailed = false;

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function removeScriptElement() {
    var spent = document.getElementById(TURNSTILE_SCRIPT_ID);
    if (spent && spent.parentNode) spent.parentNode.removeChild(spent);
  }

  /**
   * Loads the Turnstile script once per page, always from the canonical
   * documented URL. If a compatible element with the known DOM id already
   * exists (an integration elsewhere may supply one), it is adopted — for
   * such a pre-existing element the load event may already have fired
   * before any listener existed. The wait is bounded in all cases: the
   * promise resolves as soon as the global appears. A COMPLETED failure
   * (error event, or a script that executed without defining the global)
   * drops the spent element and the cached attempt, so a later submit may
   * retry with the canonical URL. An INDEFINITELY hung attempt latches a
   * transport failure: later calls fail closed immediately instead of
   * creating endless dead elements. A global that appears late is picked up
   * automatically on the next call.
   */
  function loadTurnstileScript() {
    if (window.turnstile) return Promise.resolve();
    if (turnstileTransportFailed) {
      return Promise.reject(
        new Error('bioco forms: Turnstile transport failed; page reload required')
      );
    }
    if (!turnstileLoadPromise) {
      // A pre-existing element is adopted instead of duplicated; anything
      // appended here always uses the canonical URL.
      var adoptExisting = !!document.getElementById(TURNSTILE_SCRIPT_ID);
      turnstileLoadPromise = new Promise(function (resolve, reject) {
        var attach = function (element, isPreexisting) {
          var settled = false;
          element.addEventListener('load', function () {
            if (!settled) {
              settled = true;
              resolve();
            }
          });
          element.addEventListener('error', function () {
            if (!settled) {
              settled = true;
              // Completed failure: the request is finished, so a later
              // attempt may retry with the canonical URL (nothing is left
              // in flight to coalesce with).
              if (element.parentNode) element.parentNode.removeChild(element);
              turnstileLoadPromise = null;
              reject(new Error('bioco forms: Turnstile script failed to load'));
            }
          });
          // A bound for BOTH kinds of element: a pre-existing one whose load
          // event already fired before these listeners existed, and a newly
          // appended one whose request never settles at all (stalled
          // network: no load, no error). The wait must not hang forever.
          setTimeout(function () {
            if (settled) return;
            settled = true;
            if (window.turnstile) {
              resolve(); // global appeared; the missed load event does not matter
              return;
            }
            if (element.parentNode) element.parentNode.removeChild(element);
            turnstileLoadPromise = null;
            // Only a self-appended element can be known-hung: for a
            // pre-existing one the missed load event might also mean the
            // script executed without defining the global, which stays
            // retryable with the canonical URL.
            if (!isPreexisting) {
              turnstileTransportFailed = true;
            }
            reject(new Error('bioco forms: Turnstile script did not load in time'));
          }, TURNSTILE_LOAD_TIMEOUT);
        };
        var existing = adoptExisting
          ? document.getElementById(TURNSTILE_SCRIPT_ID)
          : null;
        if (existing) {
          attach(existing, true);
          return;
        }
        var script = document.createElement('script');
        script.id = TURNSTILE_SCRIPT_ID;
        script.src = TURNSTILE_SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        attach(script, false);
        document.head.appendChild(script);
      });
    }
    return turnstileLoadPromise.then(function () {
      if (!window.turnstile) {
        // The script executed but never defined the global (network proxy,
        // broken response): a spent element must not survive — the next
        // attempt would wait on its already-fired load event forever. The
        // request is completed, so this stays retryable.
        removeScriptElement();
        turnstileLoadPromise = null;
        throw new Error('bioco forms: Turnstile global missing after script load');
      }
    });
  }

  /**
   * Generic serializer shared by all six adapters. Ordinary checkbox arrays
   * contain checked VALUES only (kept as [] when everything is unchecked),
   * data-bool-array checkbox arrays carry every entry's BOOLEAN in DOM
   * order, a scalar checkbox becomes true/false, only checked radios
   * contribute, type=number becomes Number(value) except empty stays '',
   * and everything else (including numeric-looking hidden inputs) remains
   * a string so ZIP leading zeros survive.
   */
  function serializeForm(form) {
    var data = {};
    var elements = form.elements;
    for (var i = 0; i < elements.length; i++) {
      var el = elements[i];
      if (!el.name || el.type === 'submit' || el.type === 'button' || el.disabled) continue;

      var isMulti = el.name.slice(-2) === '[]';
      var key = isMulti ? el.name.slice(0, -2) : el.name;

      if (el.type === 'checkbox') {
        if (isMulti) {
          if (!data[key]) data[key] = [];
          if (el.hasAttribute('data-bool-array')) {
            data[key].push(el.checked);
          } else if (el.checked) {
            data[key].push(el.value);
          }
        } else {
          data[key] = el.checked;
        }
        continue;
      }

      if (el.type === 'radio') {
        if (!el.checked) continue;
        data[key] = el.value;
        continue;
      }

      if (el.type === 'number') {
        data[key] = el.value === '' ? '' : Number(el.value);
        continue;
      }

      data[key] = el.value;
    }
    return data;
  }

  function showMessage(container, text, isError) {
    container.textContent = text || '';
    container.hidden = !text;
    container.className = 'form-message bento-card ' + (isError ? 'form-error' : 'form-success');
  }

  function mount(adapter) {
    ready(function () {
      var forms = document.querySelectorAll(adapter.scope + ' ' + adapter.form);
      for (var i = 0; i < forms.length; i++) {
        initForm(forms[i], adapter);
      }
    });
  }

  function initForm(form, adapter) {
    // Idempotent mount: a second mount of the same form must not add a
    // second submit listener, widget or request.
    if (form.biocoFormsMounted) return;
    form.biocoFormsMounted = true;

    if (typeof adapter.onPrepare === 'function') adapter.onPrepare(form);

    var configName = form.getAttribute('data-config') || adapter.config;
    var config = window[configName] || {};
    var messageBox = form.parentNode.querySelector('.form-message');
    var submitBtn = form.querySelector('[type="submit"]');
    var captchaContainer = form.querySelector('[data-form-captcha]');
    var captchaToken = '';
    var widgetId = null;
    var inFlight = false;
    // Captured at mount, before any submission: a form without
    // data-submit-label must not stay stuck on its pending caption.
    var originalLabel = submitBtn ? submitBtn.textContent : '';
    var pendingLabel = submitBtn
      ? (submitBtn.getAttribute('data-submitting-label') || originalLabel)
      : '';
    var restoreLabel = submitBtn
      ? (submitBtn.getAttribute('data-submit-label') || originalLabel)
      : '';

    function renderCaptcha() {
      if (!captchaContainer || !config.turnstileSiteKey) return;
      loadTurnstileScript().then(function () {
        if (widgetId !== null) return;
        widgetId = window.turnstile.render(captchaContainer, {
          sitekey: config.turnstileSiteKey,
          callback: function (token) {
            captchaToken = token;
          },
          'expired-callback': function () {
            captchaToken = '';
          },
          'error-callback': function () {
            // A widget error must leave the form recoverable for THIS widget
            // only: invalidate the token and reset that widget so the next
            // submit can produce a fresh one. Other forms keep theirs.
            captchaToken = '';
            if (window.turnstile && widgetId !== null) {
              window.turnstile.reset(widgetId);
            }
          }
        });
      }).catch(function () {
        // Load failure must not be swallowed: while a bounded attempt is
        // still running the promise simply stays pending; once a bounded
        // attempt settled without a transport, the user gets the explicit
        // reload copy (a completed error stays retryable on the next
        // submit, so no premature copy here).
        if (messageBox && turnstileTransportFailed) {
          showMessage(messageBox, config.transportError, true);
        }
      });
    }

    renderCaptcha();

    function showError(json) {
      if (adapter.onError) adapter.onError(form, json);
      var errorMessage = adapter.errorText
        ? adapter.errorText(json, config.fallbackError)
        : ((json && json.error) || config.fallbackError);
      if (messageBox) showMessage(messageBox, errorMessage, true);
      inFlight = false;
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = restoreLabel;
      }
      // Invalidate the local token immediately — an old token must never be
      // reused while waiting for a fresh callback — then reset this form's
      // widget only.
      captchaToken = '';
      if (window.turnstile && widgetId !== null) {
        window.turnstile.reset(widgetId);
      }
    }

    function showSuccess(json) {
      if (adapter.onValid) {
        adapter.onValid(form, json, messageBox);
        return;
      }
      form.hidden = true;
      if (messageBox) showMessage(messageBox, config.successMessage, false);
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      // In-flight guard first: a pending request must never be duplicated,
      // not even by a manually dispatched submit event.
      if (inFlight) return;

      // Native validity (the renderers ship novalidate; #181 makes the
      // required/email/min attributes real gates). A falsy reportValidity
      // lets the browser surface its own bubbles.
      if (typeof form.reportValidity === 'function' && !form.reportValidity()) return;

      if (config.turnstileSiteKey && !captchaToken) {
        if (widgetId === null && (!turnstileTransportFailed || window.turnstile)) {
          // Normal bounded attempt/retry, or late-global recovery: with a
          // latched transport the loader creates no scripts, but a global
          // that appeared from another source renders a widget without a
          // new request (loadTurnstileScript resolves immediately).
          renderCaptcha();
        }
        if (messageBox) {
          if (turnstileTransportFailed && widgetId === null && !window.turnstile) {
            // The bounded attempt settled without a working transport and
            // no global arrived since: say exactly that instead of
            // pretending a retry exists. Values and caption stay
            // untouched, nothing reloads or re-submits itself.
            showMessage(messageBox, config.transportError, true);
          } else {
            // The widget is rendering or a token is awaited (also the
            // truthful transitional copy right after a late recovery).
            showMessage(messageBox, config.captchaError, true);
          }
        }
        return;
      }

      var data = serializeForm(form);
      data.captchaToken = captchaToken;

      inFlight = true;
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = pendingLabel;
      }
      if (messageBox) {
        messageBox.hidden = true;
      }

      fetch(config.restUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
        .then(function (response) {
          return response.json().catch(function () {
            return { success: false, error: config.fallbackError };
          }).then(function (json) {
            return { ok: response.ok, json: json };
          });
        })
        .then(function (result) {
          if (result.ok && result.json && result.json.success) {
            // A completed signup is terminal: the button stays disabled and
            // the instance never fires a second request — even when the
            // success display or redirect handling throws. A retry after an
            // accepted response would duplicate a real signup, so this
            // branch must never fall into the error path.
            try {
              showSuccess(result.json);
            } catch (successError) {
              if (window.console && window.console.error) {
                window.console.error('bioco forms: success handling failed', successError);
              }
            }
          } else {
            showError(result.json);
          }
        })
        .catch(function () {
          showError(null);
        });
    });
  }

  window.BiocoForms = { mount: mount };
})();
