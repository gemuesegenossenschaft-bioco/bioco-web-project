/** Native field/action modules share ordinary Divi decoration and layout controls. */
(function () {
  'use strict';
  var React = (window.vendor && window.vendor.React) || window.React;
  if (!React) return;
  function value(attrs, key) {
    var stored = attrs && attrs[key] && attrs[key].innerContent && attrs[key].innerContent.desktop;
    return stored && typeof stored.value === 'string' ? stored.value : '';
  }
  function data() {return window.BiocoFormCompositionData || {};}
  function choices(identity) {
    var entries = [];
    Object.keys(data().contracts || {}).forEach(function (type) {
      if (identity) Object.keys(data().contracts[type]).forEach(function (name) {entries.push(type + '.' + name);});
      else entries.push(type);
    });
    return function (props) {
      return React.createElement('select', {value: props.value || '', onChange: function (event) {props.onChange({inputValue: event.target.value});}},
        React.createElement('option', {value: ''}, 'Bitte wählen'),
        entries.map(function (entry) {return React.createElement('option', {key: entry, value: entry}, entry);}));
    };
  }
  function styles(props) {
    return React.createElement(window.divi.module.StyleContainer, {mode: props.mode, state: props.state, noStyleTag: props.noStyleTag},
      ['module','control','label','help'].map(function (name) {return props.elements.style({attrName: name});}));
  }
  function edit(kind) {
    return function (props) {
      var domRef = React.useRef(null);
      var label = value(props.attrs, 'label');
      var child;
      if (kind === 'form-controller') {
        child = React.createElement('div', {className: 'bioco-native-controller'},
          'Formular: ' + value(props.attrs, 'form_type'),
          React.createElement('p', null, 'Felder und Buttons in derselben Divi-Sektion mit der CSS-Klasse bioco-form-scope anordnen.'));
      } else if (kind === 'form-action') {
        child = React.createElement('button', {type: 'button', disabled: true, className: 'bioco-form-control bioco-form-action'}, label);
      } else {
        var identity = value(props.attrs, 'field').split('.');
        var spec = ((data().contracts || {})[identity[0]] || {})[identity[1]];
        var type = spec ? spec.type : 'text';
        child = React.createElement('div', {className: 'bioco-form-field'},
          React.createElement('label', {className: 'bioco-form-label'}, label),
          React.createElement(type === 'textarea' ? 'textarea' : 'input', {type: type === 'textarea' ? undefined : type, className: 'bioco-form-control', disabled: true, placeholder: value(props.attrs, 'placeholder')}),
          React.createElement('p', {className: 'bioco-form-help'}, value(props.attrs, 'help')));
      }
      return React.createElement(window.divi.module.ModuleContainer, {
        attrs: props.attrs, defaultPrintedStyleAttrs: props.defaultPrintedStyleAttrs, domRef: domRef, elements: props.elements,
        id: props.id, isFirst: props.isFirst, isLast: props.isLast, name: props.name,
        stylesComponent: styles,
        scriptDataComponent: function (args) {return React.createElement(React.Fragment, null, args.elements.scriptData({attrName: 'module'}));},
        classnamesFunction: function (args) {args.classnamesInstance.add(window.divi.module.elementClassnames({attrs: (args.attrs.module || {}).decoration || {}, breakpoint: args.breakpoint, state: args.state}));},
        isLooped: props.isLooped, loopIndex: props.loopIndex,
      }, props.elements.styleComponents({attrName: 'module'}), child);
    };
  }
  function boot() {
    if (window.__biocoFormCompositionRegistered) return;
    var library = window.divi && window.divi.moduleLibrary;
    var fields = window.divi && window.divi.fieldLibrary;
    if (!library || !library.registerModule || !fields || !fields.registerFieldComponent || !data().modules) return;
    fields.registerFieldComponent({name: 'bioco/form-type', component: choices(false)});
    fields.registerFieldComponent({name: 'bioco/form-identity', component: choices(true)});
    Object.keys(data().modules).forEach(function (kind) {
      library.registerModule(data().modules[kind], {active: true, renderers: {edit: edit(kind)}, placeholderContent: {module: {}}});
    });
    window.__biocoFormCompositionRegistered = true;
  }
  var hooks = (window.vendor && window.vendor.wp && window.vendor.wp.hooks) || window.wp && window.wp.hooks;
  if (hooks && hooks.addAction) hooks.addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'bioco/form-composition', boot, 21);
  [0, 400, 1200, 3000].forEach(function (delay) {setTimeout(boot, delay);});
  boot();
})();
