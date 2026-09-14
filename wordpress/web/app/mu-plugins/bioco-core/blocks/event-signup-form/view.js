/**
 * Event-signup form view script (W10, issue #97 → shared lifecycle #181).
 * Thin adapter: the shared submit engine lives in
 * assets/bioco-forms-lifecycle.js (registered as this script's dependency
 * in bioco-core.php). This file only carries the adapter contract —
 * selectors, localized config object name and the informal German copy —
 * and hides the form inline on success. POST goes to the event-signup
 * endpoint (see bioco-forms mu-plugin).
 */
(function () {
  'use strict';

  var SCOPE = '.cms-event-signup-form';
  var FORM_SELECTOR = 'form[data-form="event-signup"]';

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
      window.console.error('bioco forms: lifecycle runtime missing, event-signup form stays inert');
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
    config: 'biocoEventSignupFormConfig',
    success: 'Anmeldung erfolgreich! Vielen Dank für deine Anmeldung. Wir melden uns bei dir.',
    error: 'Die Anmeldung konnte nicht gesendet werden. Bitte versuche es erneut oder kontaktiere uns direkt.',
    captcha: 'Bitte bestätigen Sie, dass Sie kein Roboter sind.'
  });
})();
