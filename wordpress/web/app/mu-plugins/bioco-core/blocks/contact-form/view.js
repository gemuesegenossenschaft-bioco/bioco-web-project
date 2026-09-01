/**
 * Contact form view script (W10, issue #97). Plain ES5-safe vanilla JS
 * (the theme has no build step): renders the Turnstile widget, serializes
 * the form generically, POSTs to the REST endpoint from
 * biocoContactFormConfig (localized in render.php). Erfolg- und Fehlermeldungen
 * kommen aus editierbaren ACF-Feldern (Issue #158) — nie hart kodiert.
 */
(function () {
  'use strict';


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
    if (!text) return;
    container.textContent = text;
    container.hidden = false;
    container.className = 'form-message bento-card ' + (isError ? 'form-error' : 'form-success');
  }

  function initForm(form) {
    var configName = form.getAttribute('data-config') || 'biocoContactFormConfig';
    var config = window[configName] || {};
    var successMessage = config.successMessage || '';
    var fallbackError = config.fallbackError || '';
    var captchaError = config.captchaError || '';
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
        if (messageBox) showMessage(messageBox, captchaError, true);
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
            if (messageBox) showMessage(messageBox, successMessage, false);
          } else {
            var errorMessage = (result.json && result.json.error) || fallbackError;
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
          if (messageBox) showMessage(messageBox, fallbackError, true);
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
    var forms = document.querySelectorAll('.cms-contact-form form[data-form="contact"]');
    for (var i = 0; i < forms.length; i++) {
      initForm(forms[i]);
    }
  }

  ready(init);
})();
