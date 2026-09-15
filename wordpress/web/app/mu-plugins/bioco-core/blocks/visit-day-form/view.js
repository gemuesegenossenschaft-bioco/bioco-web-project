/**
 * Visit-day form view script (W10, issue #97 → shared lifecycle #181). Thin
 * adapter: the shared submit engine lives in assets/bioco-forms-lifecycle.js
 * (registered as this script's dependency in bioco-core.php). This file only
 * carries the adapter contract — selectors, localized config object name and
 * the German copy — and hides the form inline on success. POST goes to the
 * immediate-mail visit-day endpoint (no double-opt-in; see bioco-forms
 * mu-plugin), so the success copy below reflects that instead of repeating
 * the reference's confirm-by-email promise.
 */
(function () {
  'use strict';

  var SCOPE = '.cms-visit-day-form';
  var FORM_SELECTOR = 'form[data-form="visit-day"]';

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
      window.console.error('bioco forms: lifecycle runtime missing, visit-day form stays inert');
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
    config: 'biocoVisitDayFormConfig',
  });
})();
