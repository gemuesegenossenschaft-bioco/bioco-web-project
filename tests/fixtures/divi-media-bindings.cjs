const fs = require('node:fs');
const vm = require('node:vm');
const hooks = {};
const context = {window: {vendor: {wp: {hooks: {
  addFilter(name, namespace, fn) {hooks[name] = fn;},
}}}}};
vm.runInNewContext(fs.readFileSync(process.argv[2], 'utf8'), context);
const request = JSON.parse(fs.readFileSync(0, 'utf8'));
const before = JSON.stringify(request.attrs);
const value = request.mode === 'controls'
  ? hooks['divi.moduleLibrary.moduleAttributes.divi.image'](request.attrs)
  : hooks['divi.moduleLibrary.registerModule.moduleAttrs'](request.attrs, {name: request.name || 'divi/image'});
process.stdout.write(JSON.stringify({value, unchanged: before === JSON.stringify(request.attrs), hooks: Object.keys(hooks)}));
