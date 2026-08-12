/**
 * Membership form view script (W10, issue #97). Plain ES5-safe vanilla JS
 * (the theme has no build step). Same generic submit engine as
 * blocks/contact-form/view.js, extended to surface fieldErrors returned by
 * POST /wp-json/bioco/v1/membership (see bioco_forms_validate_membership()
 * in the bioco-forms mu-plugin, ported from .wp-refs/membership.ts).
 *
 * This is the single-page long-form variant — see the deferral note at the
 * top of render.php. Redirect-on-success targets the imported WordPress
 * thank-you page at /anmeldung-danke/.
 */
(function () {
  'use strict';

  var FALLBACK_ERROR = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.';
  var CAPTCHA_MISSING_ERROR = 'Bitte bestätigen Sie, dass Sie kein Roboter sind.';
  var THANK_YOU_URL = '/anmeldung-danke/';

  function nonNegativeInteger(value) {
    if (!/^\d+$/.test(value || '')) return 0;
    return Math.min(100, parseInt(value, 10));
  }

  function setHiddenValue(form, name, value) {
    var input = form.querySelector('[name="' + name + '"]');
    if (input) input.value = String(value);
  }

  function applyCalculatorSelection(form) {
    var params = new URLSearchParams(window.location.search || '');
    var selected = params.get('abo');
    var aboTypes = {
      'halb-1-person': 'halb',
      'standard-2-3-personen': 'standard',
      'doppel-4-6-personen': 'doppel'
    };

    if (selected === 'kein') {
      setHiddenValue(form, 'membershipType', 'shares-only');
      setHiddenValue(form, 'aboType', 'none');
      setHiddenValue(form, 'additionalShares', 0);
      setHiddenValue(form, 'sharesOnly', Math.max(1, nonNegativeInteger(params.get('shares'))));
      return;
    }

    if (!aboTypes[selected]) return;
    setHiddenValue(form, 'membershipType', 'abo');
    setHiddenValue(form, 'aboType', aboTypes[selected]);
    setHiddenValue(form, 'additionalShares', nonNegativeInteger(params.get('additional')));
    setHiddenValue(form, 'sharesOnly', 0);
  }

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function loadTurnstile(cb) {
    if (window.turnstile) {
      cb();
      return;
    }
    var existing = document.getElementById('bioco-cf-turnstile-js');
    if (existing) {
      existing.addEventListener('load', cb);
      return;
    }
    var script = document.createElement('script');
    script.id = 'bioco-cf-turnstile-js';
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    script.async = true;
    script.defer = true;
    script.addEventListener('load', cb);
    document.head.appendChild(script);
  }

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
    container.textContent = text;
    container.hidden = false;
    container.className = 'form-message bento-card ' + (isError ? 'form-error' : 'form-success');
  }

  function fieldErrorsToText(fieldErrors) {
    if (!fieldErrors) return '';
    var parts = [];
    for (var key in fieldErrors) {
      if (Object.prototype.hasOwnProperty.call(fieldErrors, key)) {
        parts.push(fieldErrors[key]);
      }
    }
    return parts.join(' ');
  }

  function initForm(form) {
    applyCalculatorSelection(form);

    var configName = form.getAttribute('data-config') || 'biocoMembershipFormConfig';
    var config = window[configName] || {};
    var messageBox = form.parentNode.querySelector('.form-message');
    var submitBtn = form.querySelector('[type="submit"]');
    var captchaContainer = form.querySelector('[data-form-captcha]');
    var captchaToken = '';
    var widgetId = null;

    function renderCaptcha() {
      if (!captchaContainer || !config.turnstileSiteKey) return;
      loadTurnstile(function () {
        widgetId = window.turnstile.render(captchaContainer, {
          sitekey: config.turnstileSiteKey,
          callback: function (token) {
            captchaToken = token;
          },
          'expired-callback': function () {
            captchaToken = '';
          }
        });
      });
    }

    renderCaptcha();

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      if (config.turnstileSiteKey && !captchaToken) {
        if (messageBox) showMessage(messageBox, CAPTCHA_MISSING_ERROR, true);
        return;
      }

      var data = serializeForm(form);
      data.captchaToken = captchaToken;

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = submitBtn.getAttribute('data-submitting-label') || submitBtn.textContent;
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
            return { success: false, error: 'Server error: ' + response.status };
          }).then(function (json) {
            return { ok: response.ok, json: json };
          });
        })
        .then(function (result) {
          if (result.ok && result.json && result.json.success) {
            window.location.href = THANK_YOU_URL;
          } else {
            var errorMessage = (result.json && result.json.error) || FALLBACK_ERROR;
            var fieldText = result.json ? fieldErrorsToText(result.json.fieldErrors) : '';
            if (fieldText) errorMessage += ' ' + fieldText;
            if (messageBox) showMessage(messageBox, errorMessage, true);
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = submitBtn.getAttribute('data-submit-label') || submitBtn.textContent;
            }
            captchaToken = '';
            if (window.turnstile && widgetId !== null) {
              window.turnstile.reset(widgetId);
            }
          }
        })
        .catch(function () {
          if (messageBox) showMessage(messageBox, FALLBACK_ERROR, true);
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtn.getAttribute('data-submit-label') || submitBtn.textContent;
          }
          captchaToken = '';
          if (window.turnstile && widgetId !== null) {
            window.turnstile.reset(widgetId);
          }
        });
    });
  }

  function init() {
    var forms = document.querySelectorAll('.cms-membership-form form[data-form="membership"]');
    for (var i = 0; i < forms.length; i++) {
      initForm(forms[i]);
    }
  }

  ready(init);
})();
