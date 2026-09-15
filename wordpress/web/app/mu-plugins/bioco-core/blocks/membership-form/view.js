/**
 * Membership form view script (W10, issue #97 → shared lifecycle #181).
 * Thin adapter: the shared submit engine lives in
 * assets/bioco-forms-lifecycle.js (registered as this script's dependency
 * in bioco-core.php). This file carries the membership-specific contract:
 * the pricing-calculator URL selection (?abo=&shares=&additional=) applied
 * before wiring, the flattened fieldErrors display, and the success policy
 * — navigate to the imported WordPress thank-you page at
 * /anmeldung-danke/ (no inline success replacement). PHP sends
 * administrator mail first and treats intranet forwarding as best effort,
 * so HTTP 200 {success:true,forwarded:false} is still a completed signup
 * and must redirect exactly once.
 */
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
    onPrepare: applyCalculatorSelection,
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
