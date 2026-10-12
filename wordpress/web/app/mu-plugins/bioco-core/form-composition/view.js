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
      var text = errors && errors[input.name.replace(/\[\]$/, '')];
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
  function syncValues(form) {
    var scope = scopeFor(form);
    var type = form.getAttribute('data-form');
    own(scope, '[data-bioco-review-field]').forEach(function (output) {
      var identity = output.getAttribute('data-bioco-review-field').split('.');
      var contract = configFor(form).fields || {};
      if (identity[0] !== type || !Object.prototype.hasOwnProperty.call(contract, identity[1])) return;
      var inputs = Array.from(form.elements).filter(function (input) {return input.getAttribute('data-bioco-field') === identity.join('.');});
      var values = inputs.map(function (input) {
        if (input.type === 'checkbox' || input.type === 'radio') return input.checked ? (contract[identity[1]].array && !contract[identity[1]].boolean && input.labels[0] ? input.labels[0].textContent.trim() : '✓') : '';
        return input.tagName === 'SELECT' ? (input.value && input.selectedOptions[0] ? input.selectedOptions[0].textContent : '') : input.value;
      }).filter(Boolean);
      output.textContent = values.join(', ');
    });
  }
  function stateFor(form, state, json) {
    var scope = scopeFor(form);
    scope.setAttribute('data-bioco-form-state', state);
    syncValues(form);
    own(scope, '.bioco-form-state-success').forEach(function (element) {element.hidden = state !== 'success';});
    own(scope, '.bioco-form-state-error').forEach(function (element) {element.hidden = state !== 'error';});
    own(scope, '.bioco-form-state-pending').forEach(function (element) {element.hidden = state !== 'pending';});
    own(scope, '.bioco-form-fields').forEach(function (element) {element.hidden = state === 'success';});
    if (state === 'error') own(scope, '[data-bioco-form-error-detail]').forEach(function (detail) {detail.textContent = (json && json.error) || configFor(form).fallbackError || '';});
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
    if (!contract || !configFor(form).restUrl || (type === 'membership' && !window.BiocoMembership)) return;
    var fields = own(scope, '[data-bioco-field]');
    var seen = {};
    var choiceGroups = {};
    var valid = fields.every(function (input) {
      var identity = input.getAttribute('data-bioco-field').split('.');
      var name = identity[1];
      if (!Object.prototype.hasOwnProperty.call(contract, name)) return false;
      var spec = contract[name];
      var inputType = ['TEXTAREA','SELECT'].indexOf(input.tagName) >= 0 ? input.tagName.toLowerCase() : input.type;
      var group = inputType === 'radio' && input.closest('[data-bioco-choice-group]');
      var radio = group && group.getAttribute('data-bioco-choice-group') === identity.join('.') && (spec.presentations || []).indexOf('radio') >= 0;
      if (radio && choiceGroups[name] && choiceGroups[name] !== group) return false;
      if (!input.hasAttribute('data-native-form-control') || identity[0] !== type || (seen[name] && !spec.array && !(radio && choiceGroups[name] === group)) || input.name !== name + (spec.array ? '[]' : '') || input.required !== spec.required || (inputType !== spec.type && !radio) || !!spec.boolean !== input.hasAttribute('data-bool-array')) return false;
      if (radio) choiceGroups[name] = group;
      seen[name] = (seen[name] || 0) + 1;
      return true;
    });
    Object.keys(contract).forEach(function (name) {if (contract[name].required && !seen[name]) valid = false;});
    if (type === 'membership') {
      var count = form.querySelector('[name="commitmentCount"]');
      if (!count || seen.commitmentAccepted !== Number(count.value)) valid = false;
      ['[data-wizard-next]', '[data-wizard-back]', '[data-wizard-progress]'].forEach(function (selector) {
        if (own(scope, selector).length !== 1) valid = false;
      });
      var steps = own(scope, '.form-step, .bioco-form-step');
      if (steps.length === 0 || steps.some(function (step) {return steps.some(function (other) {return other !== step && other.contains(step);});})) valid = false;
      if (fields.some(function (input) {return !steps.some(function (step) {return step.contains(input);});})) valid = false;
    }
    var controls = own(scope, '[data-native-form-control]');
    var buttons = controls.filter(function (input) {return input.type === 'submit';});
    if (!valid || buttons.length !== 1) return;
    var id;
    do { id = 'bioco-form-active-' + (++nextId); } while (document.getElementById(id));
    form.id = id;
    controls.forEach(function (input) {input.setAttribute('form', id);});
    form.biocoFormScope = scope;
    scope.addEventListener('input', function (event) {if (event.target.form === form) syncValues(form);});
    stateFor(form, 'idle');
    window.BiocoForms.mount({
      scope: '.bioco-form-scope', form: '#' + id,
      configFor: configFor,
      submitButtonFor: function () {return buttons[0];},
      onState: stateFor,
      messageText: function (target, text, error) {return error && own(scope, '.bioco-form-state-error').length ? '' : text;},
      onPrepare: type === 'membership' ? window.BiocoMembership.prepare : undefined,
      onError: function (target, json) {
        fieldErrors(target, json && json.fieldErrors);
        if (target.biocoShowFieldErrors) target.biocoShowFieldErrors(json && json.fieldErrors);
      },
      onValid: function (target, json, message) {
        if (type === 'membership') {
          var url = configFor(target).thankYouUrl;
          if (typeof url === 'string' && url.trim()) {
            try {
              var destination = new URL(url, window.location.href);
              if (['http:', 'https:'].indexOf(destination.protocol) >= 0 && destination.origin === window.location.origin) {
                window.location.assign(destination.href);
              }
            } catch (error) { /* Keep the native success state for an invalid destination. */ }
          }
        }
        target.hidden = true;
        if (own(scope, '.bioco-form-state-success').length === 0 && message) {
          message.textContent = configFor(target).successMessage;
          message.hidden = !message.textContent;
        }
      }
    });
    if (form.biocoFormsReady) controls.forEach(function (input) {input.disabled = false;});
  }
  function connectDoi(scope) {
    var controllers = ownDoi(scope, '[data-bioco-doi-controller]');
    var forms = ownDoi(scope, '[data-bioco-doi-form]');
    var buttons = ownDoi(scope, '[data-bioco-doi-confirm]');
    if (controllers.length !== 1 || forms.length !== 1 || buttons.length !== 1) return;
    if (controllers[0].getAttribute('data-bioco-doi-state') !== 'prompt') return;
    var id;
    do {id = 'bioco-doi-active-' + (++nextId);} while (document.getElementById(id));
    forms[0].id = id;
    buttons[0].setAttribute('form', id);
    buttons[0].disabled = false;
  }
  function ownDoi(scope, selector) {
    return Array.from(scope.querySelectorAll(selector)).filter(function (element) {return element.closest('.bioco-doi-scope') === scope;});
  }
  function boot() {
    document.querySelectorAll('.bioco-form-scope').forEach(connect);
    document.querySelectorAll('.bioco-doi-scope').forEach(connectDoi);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
