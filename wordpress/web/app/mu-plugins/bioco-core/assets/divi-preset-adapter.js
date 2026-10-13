/* Divi 5.11.0 returns an items map, not a wrapper with an items member. */
(function () {
  'use strict';
  const divi = window.divi;
  function diagnostic(reason) {
    if (divi) divi.biocoPresetAdapter = {status: 'disabled', reason: reason};
    window.console?.warn('[bioco Divi preset adapter]', reason);
  }
  if (!divi) return diagnostic('missing-divi');
  if (divi.biocoPresetAdapter?.status === 'installed') return;
  if (divi.module || divi.modalLibrary) return diagnostic('late-consumer-binding');
  const namespace = divi.moduleUtils;
  const original = namespace?.ensureDefaultModulePresetExists;
  const supportedSource = "({moduleName:e,moduleLabel:t,selectStore:r=a.select})=>{let i=r(\"divi/global-data\").getModulePresetsList({moduleName:e});if(!(i&&Object.keys(i?.items??{}).length>0)){const n=Date.now(),o=r(\"divi/settings\").getSetting([\"version\"]);(0,a.dispatch)(\"divi/global-data\").addDefaultModulePreset({moduleName:e,moduleLabel:`${t} Preset`,presetType:\"module\",version:o,created:n,updated:n}),i=r(\"divi/global-data\").getModulePresetsList({moduleName:e})}return i}";
  if (typeof original !== 'function' || Function.prototype.toString.call(original) !== supportedSource) {
    return diagnostic('unsupported-helper');
  }
  const rootDescriptor = Object.getOwnPropertyDescriptor(divi, 'moduleUtils');
  if (!rootDescriptor || !rootDescriptor.writable) return diagnostic('readonly-namespace');
  const descriptors = Object.getOwnPropertyDescriptors(namespace);
  const originalDescriptor = descriptors.ensureDefaultModulePresetExists;
  delete descriptors.ensureDefaultModulePresetExists;
  const adapter = Object.create(Object.getPrototypeOf(namespace), descriptors);
  Object.defineProperty(adapter, 'ensureDefaultModulePresetExists', {
    enumerable: originalDescriptor.enumerable,
    configurable: originalDescriptor.configurable,
    value: function (args) {
      const select = args.selectStore === undefined ? divi.data.select : args.selectStore;
      const items = select('divi/global-data').getModulePresetsList({moduleName: args.moduleName});
      if (items && Object.keys(items).length > 0) return items;
      return original(args);
    },
  });
  divi.moduleUtils = adapter;
  divi.biocoPresetAdapter = {status: 'installed'};
})();
