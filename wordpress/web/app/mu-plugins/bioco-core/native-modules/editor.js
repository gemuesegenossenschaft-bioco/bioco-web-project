/**
 * bioco native Divi modules — shared editor bundle (#146).
 *
 * Registers the bioco native modules (metadata from BiocoNativeModulesData,
 * sourced from each module's module.json) into Divi's module library store,
 * after the store itself is registered. Each module's canvas view renders the
 * server-rendered inert HTML from the project preview endpoint
 * (POST /bioco/v1/native-preview, component + editable target post + typed
 * values) and refetches on editorial attribute changes.
 *
 * The implementation follows Divi's documented third-party module APIs
 * (registerModule, ModuleContainer, useFetch); no Divi source code is
 * reproduced here. Public rendering never runs this bundle.
 */
(function () {
    'use strict';

    window.__biocoNativeEditorBundleLoaded = true;


    var React = (window.vendor && window.vendor.React) || window.React;

    if (!React || typeof React.createElement !== 'function') {

        return;
    }

    /**
     * Read the canonical innerContent value of one Divi attribute.
     */
    function attrValue(attrs, field) {
        var attr = attrs && attrs[field];
        if (attr && typeof attr === 'object' && attr.innerContent) {
            var desktop = attr.innerContent.desktop;
            if (desktop && Object.prototype.hasOwnProperty.call(desktop, 'value')) {
                return desktop.value;
            }
            return null;
        }
        return typeof attr === 'string' ? attr : null;
    }

    /**
     * Editor bundle data localized by PHP: one entry per native module with
     * its module.json metadata and request component key.
     */
    function nativeData() {
        var data = window.BiocoNativeModulesData || {};
        return {
            modules: Array.isArray(data.modules) ? data.modules : [],
        };
    }

    function editorialValues(attrs, fields) {
        var values = {};
        Object.keys(fields).forEach(function (field) {
            var value = attrValue(attrs, field);
            if (value !== null && value !== undefined) values[field] = value;
        });
        return values;
    }

    function EditorialControl(spec, value, change) {
        var kind = spec.type;
        if (kind === 'rows') {
            var rows = Array.isArray(value) ? value : [];
            return React.createElement('div', null,
                rows.map(function (row, index) {
                    return React.createElement('fieldset', {key: index, style: {border: '1px solid #ccc', padding: '8px', marginBottom: '8px'}},
                        Object.keys(spec.row).map(function (key) {
                            return React.createElement('label', {key: key, style: {display: 'block'}}, spec.row[key].label,
                                EditorialControl(spec.row[key], row[key], function (next) {
                                    change(rows.map(function (r, i) { return i === index ? Object.assign({}, r, {[key]: next}) : r; }));
                                }));
                        }),
                        React.createElement('button', {type: 'button', disabled: index === 0, onClick: function () {change(rowsMove(rows, index, -1));}}, 'Nach oben'),
                        React.createElement('button', {type: 'button', disabled: index === rows.length - 1, onClick: function () {change(rowsMove(rows, index, 1));}}, 'Nach unten'),
                        React.createElement('button', {type: 'button', onClick: function () {change(rows.filter(function (_, i) {return i !== index;}));}}, 'Entfernen'));
                }),
                React.createElement('button', {type: 'button', onClick: function () {
                    var row = {};
                    Object.keys(spec.row).forEach(function (key) {
                        var field = spec.row[key];
                        row[key] = field.type === 'choices' ? [] : field.type === 'toggle' ? false : ['int','number'].indexOf(field.type) !== -1 ? 0 : '';
                    });
                    change(rows.concat([row]));
                }}, 'Eintrag hinzufügen'));
        }
        if (kind === 'int') {
            return React.createElement(window.divi.fieldLibrary.Upload, {value: value || 0, attachmentId: true, dataType: 'image', onChange: function (payload) {change(Number(payload.inputValue) || 0);}});
        }
        if (spec.choices) {
            return React.createElement('select', {multiple: kind === 'choices', value: value === undefined ? (kind === 'choices' ? [] : '') : value, onChange: function (event) {
                change(kind === 'choices' ? Array.from(event.target.selectedOptions).map(function (o) {return o.value;}) : event.target.value);
            }}, kind !== 'choices' ? React.createElement('option', {value: ''}, 'Bitte wählen') : null,
            Object.keys(spec.choices).map(function (key) {return React.createElement('option', {key: key, value: key}, spec.choices[key]);}));
        }
        if (kind === 'toggle') return React.createElement('input', {type: 'checkbox', checked: value === true || value === 'on', onChange: function (event) {change(event.target.checked);}});
        if (kind === 'textarea' || kind === 'richtext') return React.createElement('textarea', {value: value || '', onChange: function (event) {change(event.target.value);}, style: {width: '100%'}});
        return React.createElement('input', {type: kind === 'number' ? 'number' : 'text', step: 'any', value: value === undefined || value === null ? '' : value, style: {width: '100%'}, onChange: function (event) {change(kind === 'number' ? Number(event.target.value) : event.target.value);}});
    }

    function makeEditorialField(spec) {
        return function (props) {
            return EditorialControl(spec, props.value, function (next) {props.onChange({inputValue: next});});
        };
    }

    function rowsMove(rows, index, direction) {
        var target = index + direction;
        if (target < 0 || target >= rows.length) {
            return rows;
        }
        var next = rows.slice();
        var tmp = next[index];
        next[index] = next[target];
        next[target] = tmp;
        return next;
    }

    function propsLikeArray(value) {
        return Array.isArray(value) || (value && typeof value.length === 'number' && typeof value.map === 'function');
    }

    function targetPostId() {
        try {
            return window.divi.data.select('divi/settings').getSetting(['post', 'id']) || 0;
        } catch (error) {
            return 0;
        }
    }

    /**
     * Style component: module decoration styles through the generic style
     * machinery, exactly like modules without own advanced styles.
     */
    var stylesComponent = function (_ref) {
        var attrs = _ref.attrs;
        var elements = _ref.elements;
        var mode = _ref.mode;
        var state = _ref.state;
        var noStyleTag = _ref.noStyleTag;

        return React.createElement(window.divi.module.StyleContainer, {
            mode: mode,
            state: state,
            noStyleTag: noStyleTag,
        }, elements.style({ attrName: 'module' }));
    };

    var scriptDataComponent = function (_ref) {
        var elements = _ref.elements;
        return React.createElement(React.Fragment, null, elements.scriptData({ attrName: 'module' }));
    };

    var classnamesFunction = function (_ref) {
        var classnamesInstance = _ref.classnamesInstance;
        var attrs = _ref.attrs;
        var breakpoint = _ref.breakpoint;
        var state = _ref.state;

        var decoration = (attrs && attrs.module && attrs.module.decoration) || {};
        classnamesInstance.add(
            window.divi.module.elementClassnames({
                attrs: decoration,
                breakpoint: breakpoint,
                state: state,
            })
        );
    };

    function makeEditRenderer(componentKey, valuesOf) {
        return function (props) {
            var moduleLib = window.divi.module;
            var rest = window.divi.rest;
            var domRef = React.useRef(null);
            var fetched = rest.useFetch({ html: '' });
            var fetch = fetched.fetch;
            var response = fetched.response;
            var isLoading = fetched.isLoading;
            var loadFailed = React.useState(false);
            var error = loadFailed[0];
            var setError = loadFailed[1];

            var signature = React.useMemo(function () {
                return JSON.stringify(valuesOf(props.attrs));
            }, [props.attrs]);

            React.useEffect(function () {
                var cancelled = false;
                setError(false);
                fetch({
                    method: 'POST',
                    restRoute: '/bioco/v1/native-preview',
                    data: {
                        component: componentKey,
                        post_id: targetPostId(),
                        values: valuesOf(props.attrs),
                    },
                }).then(function () {}, function () {
                    if (!cancelled) setError(true);
                });
                return function () {
                    cancelled = true;
                };
            }, [signature]);

            var html = response && response.data ? response.data : '';
            var children = [];
            children.push(props.elements.styleComponents({ attrName: 'module' }));

            if (!html && isLoading) {
                children.push(React.createElement('div', { className: 'bioco-native-preview-loading' }));
            } else if (error) {
                children.push(React.createElement('div', { className: 'bioco-native-preview-error' },
                    'Vorschau nicht verfügbar (Berechtigung oder ungültige Werte).'));
            } else if (html) {
                children.push(React.createElement('div', {
                    className: 'bioco-native-preview',
                    dangerouslySetInnerHTML: { __html: html },
                }));
            } else {
                children.push(React.createElement('div', { className: 'bioco-native-preview-empty' }, 'Inhalt in den Moduleinstellungen ergänzen.'));
            }

            return React.createElement(moduleLib.ModuleContainer, {
                attrs: props.attrs,
                defaultPrintedStyleAttrs: props.defaultPrintedStyleAttrs,
                domRef: domRef,
                elements: props.elements,
                id: props.id,
                isFirst: props.isFirst,
                isLast: props.isLast,
                name: props.name,
                stylesComponent: stylesComponent,
                scriptDataComponent: scriptDataComponent,
                classnamesFunction: classnamesFunction,
                isLooped: props.isLooped,
                loopIndex: props.loopIndex,
            }, children);
        };
    }

    function registerModules() {
        if (window.__biocoNativeModulesRegistered) {
            return;
        }
        var library = window.divi && window.divi.moduleLibrary;
        if (!library || typeof library.registerModule !== 'function') {

            return;
        }

        var modules = nativeData().modules;
        if (!modules.length) {

            return;
        }

        // Custom row editors are registered with Divi's field library so the
        // automatic settings machinery resolves them by component name.
        var fieldLibrary = window.divi.fieldLibrary;
        if (fieldLibrary && typeof fieldLibrary.registerFieldComponent === 'function') {
            fieldLibrary.registerFieldComponent({name: 'bioco/form-messages', component: function () {
                return React.createElement('a', {href: window.BiocoNativeModulesData.messageEditorUrl, target: '_blank', rel: 'noopener'}, 'Meldungen zentral unter Formulartexte bearbeiten');
            }});
        }

        window.__biocoNativeModulesRegistered = true;

        modules.forEach(function (module) {
            if (!module.metadata || !module.component) {
                return;
            }
            Object.keys(module.fields).forEach(function (name) {
                if (!fieldLibrary || typeof fieldLibrary.registerFieldComponent !== 'function') return;
                fieldLibrary.registerFieldComponent({name: 'bioco/' + module.component + '-' + name, component: makeEditorialField(module.fields[name])});
            });
            library.registerModule(module.metadata, {
                active: true,
                renderers: {
                    edit: makeEditRenderer(module.component, function (attrs) {return editorialValues(attrs, module.fields);}),
                },
                placeholderContent: {
                    module: {},
                },
            });
        });
    }

    function boot() {
        registerModules();
    }

    // Register after Divi's module library store exists (the app window fires
    // this action through its own hooks instance), plus immediate/deferred
    // attempts for bundles that load after the store registered.
    var hooks = (window.vendor && window.vendor.wp && window.vendor.wp.hooks)
        || window.wp && window.wp.hooks;
    if (hooks && typeof hooks.addAction === 'function') {
        hooks.addAction(
            'divi.moduleLibrary.registerModuleLibraryStore.after',
            'bioco/native-modules',
            boot,
            20
        );
    }
    [0, 400, 1200, 3000].forEach(function (delay) {
        setTimeout(boot, delay);
    });
    boot();
})();
