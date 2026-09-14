/**
 * Contact form view script (W10, issue #97 → shared lifecycle #181). Thin
 * adapter: the shared submit engine (Turnstile loading, serialization,
 * native validity gate, pending state, response handling, message display,
 * captcha token lifecycle) lives in assets/bioco-forms-lifecycle.js,
 * registered as this script's dependency in bioco-core.php. This file only
 * carries the adapter contract — selectors, localized config object name
 * and the German copy mirroring .wp-refs/ContactForm.tsx — and hides the
 * form inline on success. POST goes to the REST endpoint from
 * biocoContactFormConfig (localized in render.php).
 */
(function () {
  'use strict';

  var SCOPE = '.cms-contact-form';
  var FORM_SELECTOR = 'form[data-form="contact"]';

  // The renderers ship novalidate; without the shared runtime nothing would
  // intercept the submit event and the form would submit personal data via
  // a native GET. Block native submission so a failed runtime leaves the
  // form inert (fail closed: no request, no mail), never leaky.
  function blockNativeSubmit() {
    var forms = document.querySelectorAll(SCOPE + ' ' + FORM_SELECTOR);
    for (var i = 0; i < forms.length; i++) {
      forms[i].addEventListener('submit', function (event) {
        event.preventDefault();
      });
    }
  }

  if (!window.BiocoForms) {
    // Missing runtime: stay inert (fail closed, no request, no mail).
    if (window.console && window.console.error) {
      window.console.error('bioco forms: lifecycle runtime missing, contact form stays inert');
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', blockNativeSubmit);
    } else {
      blockNativeSubmit();
    }
    return;
  }

  window.BiocoForms.mount({
    scope: SCOPE,
    form: FORM_SELECTOR,
    config: 'biocoContactFormConfig',
    success: 'Vielen Dank für Ihre Nachricht! Wir melden uns so schnell wie möglich bei Ihnen.',
    error: 'Ihre Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es erneut oder senden Sie uns eine E-Mail direkt an info@bioco.ch',
    captcha: 'Bitte bestätigen Sie, dass Sie kein Roboter sind.'
  });
})();
