const fs = require('node:fs');
const vm = require('node:vm');
const hooks = {};
const context = {window: {vendor: {wp: {hooks: {
  addFilter(name, namespace, fn) {hooks[name] = fn;},
}}}}};
vm.runInNewContext(fs.readFileSync(process.argv[2], 'utf8'), context);
const request = JSON.parse(fs.readFileSync(0, 'utf8'));
const before = JSON.stringify(request.attrs);
if (request.mode === 'render') {
  const calls = [];
  context.window.vendor.React = {
    createElement(type, props) {return type(props);},
    cloneElement(element, props) {return {...element, props: {...element.props, ...props}};},
  };
  context.window.divi = {dynamicData: {useDynamicData(value) {
    const token = JSON.parse(value.slice(10, -2)).value;
    const id = JSON.parse(token.settings.image).id;
    calls.push({id, name: token.name});
    return {resolvedValue: request.metadata?.[id]?.[token.name], ...request.state};
  }}};
  const projected = hooks['divi.moduleLibrary.registerModule.moduleAttrs'](request.attrs, {name: 'divi/image'});
  const element = {type: 'native-image-element', props: {
    moduleAttrs: projected, runtimeModuleAttrs: projected, keep: 'native-controls',
  }};
  const value = hooks['divi.module.elements.render'](element, {attrName: request.attrName || 'image'},
    {moduleMetadata: {name: request.name || 'divi/image'}});
  process.stdout.write(JSON.stringify({value, calls, unchanged: before === JSON.stringify(request.attrs)}));
  process.exit(0);
}
const value = request.mode === 'controls'
  ? hooks['divi.moduleLibrary.moduleAttributes.divi.image'](request.attrs)
  : hooks['divi.moduleLibrary.registerModule.moduleAttrs'](request.attrs, {name: request.name || 'divi/image'});
process.stdout.write(JSON.stringify({value, unchanged: before === JSON.stringify(request.attrs), hooks: Object.keys(hooks)}));
