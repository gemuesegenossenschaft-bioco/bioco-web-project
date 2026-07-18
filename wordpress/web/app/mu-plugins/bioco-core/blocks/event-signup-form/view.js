/**
 * Event-signup form view script (W10, issue #97). Plain ES5-safe vanilla
 * JS (the theme has no build step). Same generic submit engine as
 * blocks/contact-form/view.js — see that file for the shared shape.
 */
(function () {
  'use strict';

  var SUCCESS_MESSAGE = 'Anmeldung erfolgreich! Vielen Dank für deine Anmeldung. Wir melden uns bei dir.';
  var FALLBACK_ERROR = 'Die Anmeldung konnte nicht gesendet werden. Bitte versuche es erneut oder kontaktiere uns direkt.';
  var CAPTCHA_MISSING_ERROR = 'Bitte bestätigen Sie, dass Sie kein Roboter sind.';

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

  function initForm(form) {
    var configName = form.getAttribute('data-config') || 'biocoEventSignupFormConfig';
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
            form.hidden = true;
            if (messageBox) showMessage(messageBox, SUCCESS_MESSAGE, false);
          } else {
            var errorMessage = (result.json && result.json.error) || FALLBACK_ERROR;
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
    var forms = document.querySelectorAll('.cms-event-signup-form form[data-form="event-signup"]');
    for (var i = 0; i < forms.length; i++) {
      initForm(forms[i]);
    }
  }

  ready(init);
})();
