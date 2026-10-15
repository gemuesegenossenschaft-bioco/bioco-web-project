/* Native Image Content controls and selected-attachment metadata bindings. */
(function () {
  'use strict';
  const hooks = window.vendor?.wp?.hooks;
  if (typeof hooks?.addFilter !== 'function') return;

  function sourceField(field, label, emptyMode, emptyLabel, priority) {
    return {
      groupSlug: 'contentImage', attrName: 'image.advanced.biocoMetadata',
      subName: field + 'Source', label: label, category: 'basic_option',
      description: 'Mediathek verwendet die aktuellen Angaben des ausgewählten Bildes. Bestehenden Modultext beibehalten erhält eigene Angaben.',
      defaultAttr: {desktop: {value: {altSource: 'module', titleSource: 'module'}}},
      features: {responsive: false, hover: false, sticky: false, preset: 'content'},
      render: true, priority: priority,
      component: {type: 'field', name: 'divi/select', props: {options: {
        module: {label: 'Bestehenden Modultext beibehalten'},
        library: {label: 'Mediathek'}, [emptyMode]: {label: emptyLabel},
      }}},
    };
  }

  function metadataControls(attributes) {
    if (!attributes?.image?.settings?.advanced) return attributes;
    return {...attributes, image: {...attributes.image, settings: {...attributes.image.settings,
      advanced: {...attributes.image.settings.advanced, biocoMetadata: {
        groupType: 'group-items', items: {
          altSource: sourceField('alt', 'Alternativtext: Quelle', 'decorative', 'Dekorativ (leerer Alternativtext)', 15),
          titleSource: sourceField('title', 'Bildtitel: Quelle', 'none', 'Kein Bildtitel', 16),
        },
      }},
    }}};
  }

  function bindingToken(field, image) {
    return '$variable(' + JSON.stringify({type: 'content', value: {
      name: 'bioco_media_' + field, settings: {image: JSON.stringify(image)},
    }}) + ')$';
  }

  function runtimeAttrs(attrs, context) {
    if (context.name !== 'divi/image') return attrs;
    const sources = attrs.image?.advanced?.biocoMetadata?.desktop?.value || {};
    const selected = Object.fromEntries(['alt', 'title'].map(field => [field, sources[field + 'Source']])
      .filter(([field, mode]) => mode === 'library' || mode === (field === 'alt' ? 'decorative' : 'none')));
    if (!Object.keys(selected).length) return attrs;
    const content = attrs.image?.innerContent || {};
    if (typeof content !== 'object' || Array.isArray(content)) return attrs;
    const base = content.desktop?.value || {};
    const projected = {};
    for (const [device, states] of Object.entries(content)) {
      if (!states || typeof states !== 'object' || Array.isArray(states)) {projected[device] = states; continue;}
      projected[device] = {};
      const normal = {...base, ...states.value};
      for (const [state, value] of Object.entries(states)) {
        if (!value || typeof value !== 'object' || Array.isArray(value)) {projected[device][state] = value; continue;}
        const next = {...value};
        projected[device][state] = next;
        if (!(device === 'desktop' && state === 'value') && value.src === undefined && value.id === undefined) {
          for (const field of Object.keys(selected)) delete next[field === 'title' ? 'titleText' : 'alt'];
          continue;
        }
        const image = {...normal, ...value};
        if (value.src !== undefined && value.src !== normal.src && value.id === undefined) delete image.id;
        if (state === 'value' && device !== 'desktop' && value.src !== undefined && value.src !== base.src && value.id === undefined) delete image.id;
        const id = (typeof image.id === 'string' || Number.isInteger(image.id)) && /^[1-9][0-9]*$/.test(String(image.id)) ? String(image.id) : '0';
        for (const [field, mode] of Object.entries(selected)) next[field === 'title' ? 'titleText' : 'alt'] = bindingToken(field,
          {id: mode === 'library' ? id : '0', src: typeof image.src === 'string' ? image.src : ''});
      }
    }
    const result = {...attrs, image: {...attrs.image, innerContent: projected}};
    const attributes = attrs.module?.decoration?.attributes;
    if (attributes) {
      const remove = row => row?.targetElement !== 'image' || !Object.hasOwn(selected, row.name);
      const nextAttributes = {...attributes};
      if (Array.isArray(attributes.attributes)) nextAttributes.attributes = attributes.attributes.filter(remove);
      for (const [device, states] of Object.entries(attributes)) {
        if (device === 'attributes') continue;
        nextAttributes[device] = {...states};
        for (const [state, value] of Object.entries(states)) if (Array.isArray(value?.attributes))
          nextAttributes[device][state] = {...value, attributes: value.attributes.filter(remove)};
      }
      result.module = {...attrs.module, decoration: {...attrs.module.decoration, attributes: nextAttributes}};
    }
    return result;
  }

  hooks.addFilter('divi.moduleLibrary.moduleAttributes.divi.image', 'bioco/media-metadata-controls', metadataControls);
  hooks.addFilter('divi.moduleLibrary.registerModule.moduleAttrs', 'bioco/media-metadata-bindings', runtimeAttrs, 20);
})();
