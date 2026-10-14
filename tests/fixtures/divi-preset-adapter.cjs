const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
// Exact captured exports from Divi 5.11.0 (staging) and 5.15.0 (production).
const nativeSources = {
  staging: "({moduleName:e,moduleLabel:t,selectStore:r=a.select})=>{let i=r(\"divi/global-data\").getModulePresetsList({moduleName:e});if(!(i&&Object.keys(i?.items??{}).length>0)){const n=Date.now(),o=r(\"divi/settings\").getSetting([\"version\"]);(0,a.dispatch)(\"divi/global-data\").addDefaultModulePreset({moduleName:e,moduleLabel:`${t} Preset`,presetType:\"module\",version:o,created:n,updated:n}),i=r(\"divi/global-data\").getModulePresetsList({moduleName:e})}return i}",
  production: "({moduleName:t,moduleLabel:r,selectStore:n=e.select})=>{let i=n(\"divi/global-data\").getModulePresetsList({moduleName:t});if(!(i&&Object.keys(i?.items??{}).length>0)){const o=Date.now(),a=n(\"divi/settings\").getSetting([\"version\"]);(0,e.dispatch)(\"divi/global-data\").addDefaultModulePreset({moduleName:t,moduleLabel:`${r} Preset`,presetType:\"module\",version:a,created:o,updated:o}),i=n(\"divi/global-data\").getModulePresetsList({moduleName:t})}return i}",
};
const build = process.argv[3] || "staging";
assert.ok(Object.hasOwn(nativeSources, build));
const nativeSource = nativeSources[build];
let cases = 0;
function fixture(items, options = {}) {
  if (arguments.length === 0) items = {existing: {id: 'existing'}};
  let calls = 0, selections = 0, live = 1;
  const select = () => { selections++; return {
    getModulePresetsList: () => items,
    getSetting: () => 'native-version',
  }; };
  const native = new Function(build === 'production' ? 'e' : 'a', `return (${nativeSource});`)({
    select, dispatch: () => ({addDefaultModulePreset: () => { calls++; }}),
  });
  const original = {};
  Object.defineProperty(original, 'ensureDefaultModulePresetExists', {
    get: () => native, enumerable: true,
  });
  Object.defineProperty(original, 'liveExport', {
    get: () => live, enumerable: true,
  });
  const symbol = Symbol('private-export');
  Object.defineProperty(original, symbol, {value: 42});
  const divi = {moduleUtils: original, data: {select}, ...options};
  const warnings = [];
  const window = {divi, console: {warn: (...args) => warnings.push(args)}};
  const context = {window, console: window.console};
  vm.runInNewContext(fs.readFileSync(process.argv[2], 'utf8'), context);
  return {divi, original, symbol, warnings, native,
    calls: () => calls, selections: () => selections, changeLive: () => {live++;}};
}
function check(label, callback) { callback(); cases++; }
check('native bug is reproduced', () => {
  const f = fixture(); f.native({moduleName:'divi/heading',moduleLabel:'Heading'});
  assert.equal(f.calls(), 1);
});
check('populated items map preserves empty default', () => {
  const items = {existing:{id:'existing'}}, f = fixture(items);
  assert.equal(f.divi.moduleUtils.ensureDefaultModulePresetExists({moduleName:'divi/heading'}), items);
  assert.equal(f.calls(), 0);
});
check('populated map preserves nonempty default too', () => {
  const f=fixture(); f.divi.moduleUtils.ensureDefaultModulePresetExists({moduleName:'divi/heading'});
  assert.equal(f.calls(),0);
});
check('live export getter descriptor remains identical', () => {
  const f=fixture(); assert.deepEqual(Object.getOwnPropertyDescriptor(f.divi.moduleUtils,'liveExport'),
    Object.getOwnPropertyDescriptor(f.original,'liveExport'));
  f.changeLive(); assert.equal(f.divi.moduleUtils.liveExport,2);
});
check('symbol and nonenumerable export survive', () => {
  const f=fixture(); assert.equal(f.divi.moduleUtils[f.symbol],42);
  assert.deepEqual(Object.getOwnPropertyDescriptor(f.divi.moduleUtils,f.symbol),
    Object.getOwnPropertyDescriptor(f.original,f.symbol));
});
for (const items of [{},null,undefined]) check('empty map delegates native default creation', () => {
  const f=fixture(items); const result=f.divi.moduleUtils.ensureDefaultModulePresetExists({moduleName:'divi/heading',moduleLabel:'Heading'});
  assert.equal(result,items);
  assert.equal(f.calls(),1);
});
check('injected selectStore remains supported', () => {
  const f=fixture(); const map={custom:{id:'custom'}}; let requested;
  const result=f.divi.moduleUtils.ensureDefaultModulePresetExists({moduleName:'divi/text',
    selectStore:name=>{requested=name;return {getModulePresetsList:({moduleName})=>{
      assert.equal(moduleName,'divi/text');return map;}};}});
  assert.equal(requested,'divi/global-data'); assert.equal(result,map);assert.equal(f.calls(),0);
});
for (const consumer of ['module','modalLibrary']) check('late consumer binding refuses adapter', () => {
  const f=fixture(undefined,{[consumer]:{}}); assert.equal(f.divi.moduleUtils,f.original);
  assert.ok(f.warnings.length); assert.equal(f.calls(),0);
});
check('unknown function remains untouched', () => {
  const f=fixture(); const custom=()=> 'future';const namespace={ensureDefaultModulePresetExists:custom};
  const window={divi:{moduleUtils:namespace,data:{}},console:{warn(){}}};
  vm.runInNewContext(fs.readFileSync(process.argv[2],'utf8'),{window,console:window.console});
  assert.equal(window.divi.moduleUtils,namespace);assert.equal(custom(),'future');
});
check('adapter reports installation and does not install twice', () => {
  const f=fixture();const namespace=f.divi.moduleUtils;
  assert.equal(f.divi.biocoPresetAdapter.status,'installed');
  vm.runInNewContext(fs.readFileSync(process.argv[2],'utf8'),{window:{divi:f.divi,console:{warn(){}}}});
  assert.equal(f.divi.moduleUtils,namespace);
});
check('readonly namespace refuses without mutation', () => {
  const f=fixture();const namespace=f.original;
  const divi={data:f.divi.data};Object.defineProperty(divi,'moduleUtils',{value:namespace});
  vm.runInNewContext(fs.readFileSync(process.argv[2],'utf8'),{window:{divi,console:{warn(){}}}});
  assert.equal(divi.moduleUtils,namespace);assert.equal(divi.biocoPresetAdapter.reason,'readonly-namespace');
});
check('invalid explicit selector keeps native error behavior', () => {
  const f=fixture();assert.throws(()=>f.divi.moduleUtils.ensureDefaultModulePresetExists({moduleName:'divi/heading',selectStore:null}));
  assert.equal(f.calls(),0);
});
console.log(JSON.stringify({build, cases}));
