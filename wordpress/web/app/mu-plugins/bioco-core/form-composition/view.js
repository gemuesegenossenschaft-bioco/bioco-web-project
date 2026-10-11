/** Bind independently arranged Divi controls to the existing form lifecycle. */
(function () {
  'use strict';
  var nextId = 0;

  function scopeFor(form) { return form.closest('.bioco-form-scope'); }
  function own(scope, selector) {
    return Array.from(scope.querySelectorAll(selector)).filter(function (element) {
      return element.closest('.bioco-form-scope') === scope;
    });
  }
  function configFor(form) {
    try { return JSON.parse(form.getAttribute('data-bioco-instance-config') || '{}'); }
    catch (error) { return {}; }
  }
  function fieldErrors(form, errors) {
    var scope = scopeFor(form);
    own(scope, '[data-bioco-field]').forEach(function (input) {
      var errorId = input.id + '-error';
      var previous = document.getElementById(errorId);
      if (previous) previous.remove();
      var descriptions = (input.getAttribute('aria-describedby') || '').split(' ').filter(function (id) {return id && id !== errorId;});
      input.removeAttribute('aria-invalid');
      var text = errors && errors[input.name];
      if (text) {
        var message = document.createElement('p');
        message.id = errorId;
        message.textContent = text;
        message.className = 'bioco-field-error';
        input.parentNode.appendChild(message);
        descriptions.push(errorId);
        input.setAttribute('aria-invalid', 'true');
      }
      if (descriptions.length) input.setAttribute('aria-describedby', descriptions.join(' '));
      else input.removeAttribute('aria-describedby');
    });
  }
  function stateFor(form, state) {
    var scope = scopeFor(form);
    scope.setAttribute('data-bioco-form-state', state);
    own(scope, '.bioco-form-state-success').forEach(function (element) {element.hidden = state !== 'success';});
    own(scope, '.bioco-form-state-error').forEach(function (element) {element.hidden = state !== 'error';});
    own(scope, '.bioco-form-state-pending').forEach(function (element) {element.hidden = state !== 'pending';});
    own(scope, '.bioco-form-fields').forEach(function (element) {element.hidden = state === 'success';});
    if (state === 'pending') fieldErrors(form, null);
    if (state === 'success') {
      own(scope, '[data-native-form-control]').forEach(function (input) {input.disabled = true;});
    }
  }
  function connect(scope) {
    var forms = own(scope, 'form[data-native-controller]');
    if (forms.length !== 1 || !window.BiocoForms) return;
    var form = forms[0];
    if (form.biocoFormsMounted) return;
    var type = form.getAttribute('data-form');
    var contract = configFor(form).fields;
    if (!contract || !configFor(form).restUrl) return;
    var fields = own(scope, '[data-bioco-field]');
    var seen = {};
    var valid = fields.every(function (input) {
      var identity = input.getAttribute('data-bioco-field').split('.');
      var name = identity[1];
      if (!input.hasAttribute('data-native-form-control') || identity[0] !== type || !Object.prototype.hasOwnProperty.call(contract, name) || seen[name] || input.name !== name || input.required !== contract[name].required || (input.tagName === 'TEXTAREA' ? 'textarea' : input.type) !== contract[name].type) return false;
      seen[name] = true;
      return true;
    });
    Object.keys(contract).forEach(function (name) {if (contract[name].required && !seen[name]) valid = false;});
    var controls = own(scope, '[data-native-form-control]');
    var buttons = controls.filter(function (input) {return input.type === 'submit';});
    if (!valid || buttons.length !== 1) return;
    var id;
    do { id = 'bioco-form-active-' + (++nextId); } while (document.getElementById(id));
    form.id = id;
    controls.forEach(function (input) {input.setAttribute('form', id);});
    stateFor(form, 'idle');
    window.BiocoForms.mount({
      scope: '.bioco-form-scope', form: '#' + id,
      configFor: configFor,
      submitButtonFor: function () {return buttons[0];},
      onState: stateFor,
      onError: function (target, json) {fieldErrors(target, json && json.fieldErrors);},
      onValid: function (target, json, message) {
        target.hidden = true;
        if (own(scope, '.bioco-form-state-success').length === 0 && message) {
          message.textContent = configFor(target).successMessage;
          message.hidden = !message.textContent;
        }
      }
    });
    if (form.biocoFormsReady) controls.forEach(function (input) {input.disabled = false;});
  }
  function boot() {
    document.querySelectorAll('.bioco-form-scope').forEach(connect);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
