/** Six-step membership wizard with stable retry identity. */
(function () {
  'use strict';

  var SCOPE = '.cms-membership-form';
  var FORM_SELECTOR = 'form[data-form="membership"]';
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

  function prepareWizard(form) {
    applyCalculatorSelection(form);
    var steps = form.querySelectorAll ? Array.from(form.querySelectorAll('.form-step')) : [];
    if (steps.length !== 6) return;
    var current = 0;
    var back = form.querySelector('[data-wizard-back]');
    var next = form.querySelector('[data-wizard-next]');
    var submit = form.querySelector('[type="submit"]');
    var progress = form.querySelector('[data-wizard-progress]');
    var identity = form.querySelector('[name="submissionId"]');
    if (identity && !identity.value) {
      var bytes = new Uint8Array(16);
      window.crypto.getRandomValues(bytes);
      identity.value = Array.from(bytes).map(function (n) {return n.toString(16).padStart(2, '0');}).join('');
    }

    function summary() {
      var target = form.querySelector('[data-membership-summary]');
      if (!target) return;
      target.replaceChildren();
      steps.slice(0, 5).forEach(function (step) {
        step.querySelectorAll('input,select,textarea').forEach(function (input) {
          if (input.type === 'hidden' || ((input.type === 'checkbox' || input.type === 'radio') && !input.checked) || !input.value) return;
          var label = input.labels && input.labels[0];
          if (!label) return;
          var term = document.createElement('dt');
          var value = document.createElement('dd');
          term.textContent = label.textContent.trim();
          value.textContent = input.type === 'checkbox' || input.type === 'radio' ? '✓' : input.tagName === 'SELECT' ? input.selectedOptions[0].textContent : input.value;
          target.append(term, value);
        });
      });
    }

    function show(index, focus) {
      current = Math.max(0, Math.min(steps.length - 1, index));
      steps.forEach(function (step, i) {step.hidden = i !== current;});
      back.hidden = current === 0;
      next.hidden = current === steps.length - 1;
      submit.hidden = current !== steps.length - 1;
      progress.textContent = (progress.getAttribute('data-label') || '').replace('{current}', current + 1).replace('{total}', steps.length);
      if (current === steps.length - 1) summary();
      if (focus) {
        var heading = steps[current].querySelector('h3') || steps[current];
        heading.setAttribute('tabindex', '-1');
        heading.focus();
      }
    }

    function advance() {
      var fields = Array.from(steps[current].querySelectorAll('input,select,textarea'));
      for (var i = 0; i < fields.length; i++) {
        if (!fields[i].reportValidity()) return;
      }
      show(current + 1, true);
    }
    back.addEventListener('click', function () {show(current - 1, true);});
    next.addEventListener('click', advance);
    form.addEventListener('submit', function (event) {
      if (current < steps.length - 1) {
        event.preventDefault();
        event.stopImmediatePropagation();
        advance();
      }
    }, true);
    // Reveal a previously completed step if browser or server validation fails.
    form.addEventListener('invalid', function (event) {
      var index = steps.findIndex(function (step) {return step.contains(event.target);});
      if (index >= 0 && index !== current) show(index, false);
    }, true);
    form.biocoShowFieldErrors = function (errors) {
      var names = Object.keys(errors || {});
      var index = steps.findIndex(function (step) {
        return Array.from(step.querySelectorAll('[name]')).some(function (field) {return names.indexOf(field.name.replace(/\[\]$/, '')) >= 0;});
      });
      if (index >= 0) show(index, true);
    };
    show(0, false);
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

  // The renderers ship novalidate; without the shared runtime nothing would
  // intercept the submit event and the form would submit personal data via
  // a native GET. Block native submission so a failed runtime leaves the
  // form inert (fail closed: no request, no mail), never leaky.
  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  if (!window.BiocoForms) {
    if (window.console && window.console.error) {
      window.console.error('bioco forms: lifecycle runtime missing, membership form stays inert');
    }
    onReady(function () {
      var forms = document.querySelectorAll(SCOPE + ' ' + FORM_SELECTOR);
      for (var i = 0; i < forms.length; i++) {
        forms[i].addEventListener('submit', function (event) {
          event.preventDefault();
        });
      }
    });
    return;
  }

  window.BiocoForms.mount({
    scope: SCOPE,
    form: FORM_SELECTOR,
    config: 'biocoMembershipFormConfig',
    onPrepare: prepareWizard,
    onError: function (form, json) {
      if (form.biocoShowFieldErrors) form.biocoShowFieldErrors(json && json.fieldErrors);
    },
    onValid: function () {
      window.location.href = THANK_YOU_URL;
    },
    errorText: function (json, fallback) {
      var message = (json && json.error) || fallback;
      var fieldText = json ? fieldErrorsToText(json.fieldErrors) : '';
      return fieldText ? message + ' ' + fieldText : message;
    }
  });
})();
