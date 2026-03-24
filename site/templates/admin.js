/**
 * Admin UI enhancements
 * - "Browse Media Library" button with import callback
 * - "Edit Image" button with Filerobot Image Editor
 * - Media usage rendering in library UI
 */

$(document).ready(function() {
    var currentImportContext = null;

    function parseQuery() {
        var out = {};
        var q = window.location.search.replace(/^\?/, '');
        if (!q) return out;
        q.split('&').forEach(function(pair) {
            if (!pair) return;
            var parts = pair.split('=');
            out[decodeURIComponent(parts[0])] = decodeURIComponent(parts[1] || '');
        });
        return out;
    }

    function isMediaPage() {
        return /\/processwire\/media\/?$/.test(window.location.pathname) || window.location.pathname.indexOf('/processwire/media/') >= 0;
    }

    function getTargetFieldName($field) {
        var id = $field.attr('id') || '';
        var m = id.match(/wrap_Inputfield_([^ ]+)/);
        if (m && m[1]) return m[1];
        var fieldClass = ($field.attr('class') || '').match(/Inputfield_([A-Za-z0-9_]+)/);
        if (fieldClass && fieldClass[1]) return fieldClass[1];
        var dataName = $field.attr('data-name') || '';
        if (dataName) return dataName;
        var inputName = $field.find('input[name]').first().attr('name') || '';
        if (inputName) return inputName.replace(/\[\]$/, '');
        return '';
    }

    function getPageEditId() {
        if (ProcessWire && ProcessWire.config && ProcessWire.config.ProcessPageEdit && ProcessWire.config.ProcessPageEdit.id) {
            return parseInt(ProcessWire.config.ProcessPageEdit.id, 10) || null;
        }
        var q = parseQuery();
        if (q.id) return parseInt(q.id, 10) || null;
        var m = (window.location.search || '').match(/[?&]id=(\d+)/);
        if (m) return parseInt(m[1], 10) || null;
        return null;
    }

    function isPageEditProcess() {
        if (window.ProcessWire && ProcessWire.config && ProcessWire.config.ProcessPageEdit) return true;
        var path = String(window.location.pathname || '');
        if (/\/processwire\/page\/edit\/?$/.test(path) || path.indexOf('/processwire/page/edit/') >= 0) return true;
        if (path.indexOf('/processwire/page/') >= 0 && /[?&]id=\d+/.test(window.location.search || '')) return true;
        return false;
    }

    function getEditedTemplateName() {
        if (!isPageEditProcess()) return '';
        var ppe = ProcessWire.config.ProcessPageEdit || {};
        var tpl = ppe.templateName || ppe.template || '';
        if (tpl && typeof tpl === 'object') {
            tpl = tpl.name || tpl.label || tpl.value || '';
        }
        tpl = String(tpl || '').trim();
        if (tpl) return tpl;

        // Fallback for MediaLibrary edit screens where template config is not exposed.
        if ($('#wrap_Inputfield_MediaImages, #wrap_Inputfield_MediaFiles').length) return 'MediaLibrary';
        return '';
    }

    function shouldEnforceLibraryOnly() {
        if (!isPageEditProcess()) return false;
        if (isMediaPage()) return false;
        var tpl = String(getEditedTemplateName() || '').toLowerCase();
        if (tpl === 'medialibrary') return false;
        return true;
    }

    function enforceLibraryOnlyOnPageFields() {
        if (!shouldEnforceLibraryOnly()) return;

        $('.InputfieldImage, .InputfieldFile').each(function() {
            var $field = $(this);

            $field.find('.InputfieldFileUpload, .uk-form-custom, input[type="file"], .InputfieldFileDropZone, .InputfieldFileDrop, .InputfieldFileDropArea, .gridImage__drop').each(function() {
                var $el = $(this);
                if ($el.hasClass('bioco-library-only-hidden')) return;
                $el.addClass('bioco-library-only-hidden').hide();
            });

            // Extra safety for themes that keep "Choose File" visible in custom wrappers.
            $field.find('button, a, label, span, div').filter(function() {
                var t = String($(this).text() || '').trim().toLowerCase();
                return t === 'choose file' || t === 'datei wählen';
            }).each(function() {
                var $container = $(this).closest('.uk-form-custom, .InputfieldFileUpload, label, button');
                if (!$container.length) $container = $(this);
                if ($container.hasClass('browse-media-library-btn')) return;
                $container.addClass('bioco-library-only-hidden').hide();
            });

            if ($field.find('.bioco-library-only-note').length) return;
            var $note = $('<div class="bioco-library-only-note uk-text-small" style="margin-top:6px;color:#666">Upload in Media Library, then select here.</div>');
            var $anchor = $field.find('.bioco-media-library-btn-wrap').first();
            if ($anchor.length) {
                $anchor.after($note);
            } else {
                $field.find('.InputfieldContent').first().append($note);
            }
        });
    }

    function getRepeaterItemId($field) {
        var $item = $field.closest('.InputfieldRepeaterItem');
        if (!$item.length) return null;
        var direct = parseInt($item.attr('data-page') || $item.attr('data-id') || $item.attr('data-pageid') || '', 10);
        if (direct) return direct;
        var idAttr = $item.attr('id') || '';
        var m = idAttr.match(/(\d{2,})/);
        if (m) {
            var num = parseInt(m[1], 10);
            if (num) return num;
        }
        return null;
    }

    function findClosestCard($node) {
        var $card = $node.closest('.PageListItem');
        if ($card.length) return $card;
        $card = $node.closest('.uk-card');
        if ($card.length) return $card;
        $card = $node.closest('li');
        if ($card.length) return $card;
        return $node.closest('tr');
    }

    function isLikelyImage(fileName) {
        var n = String(fileName || '').toLowerCase();
        return /\.(jpg|jpeg|png|webp|gif|avif|svg)$/.test(n);
    }

    function openVisualFileChooser(files, onSelect) {
        if (!files || !files.length) {
            alert('No files available.');
            return;
        }

        $('.bioco-file-picker-overlay').remove();

        var $overlay = $('<div class="bioco-file-picker-overlay"></div>').css({
            position: 'fixed',
            inset: 0,
            background: 'rgba(0,0,0,0.55)',
            zIndex: 999999
        });

        var $modal = $('<div class="bioco-file-picker-modal"></div>').css({
            width: 'min(980px, 92vw)',
            maxHeight: '86vh',
            overflow: 'hidden',
            background: '#fff',
            borderRadius: '10px',
            boxShadow: '0 20px 60px rgba(0,0,0,0.35)',
            margin: '6vh auto 0 auto',
            display: 'flex',
            flexDirection: 'column'
        });

        var $head = $('<div><strong>Select media file(s)</strong><div style="font-size:12px;color:#666">Select one or more thumbnails, then import.</div></div>').css({
            padding: '14px 16px',
            borderBottom: '1px solid #e6e6e6'
        });

        var $grid = $('<div class="bioco-file-picker-grid"></div>').css({
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(140px, 1fr))',
            gap: '12px',
            padding: '14px',
            overflow: 'auto'
        });

        var selected = {};
        function selectedCount() {
            return Object.keys(selected).length;
        }

        files.forEach(function(file) {
            var image = isLikelyImage(file.fileName);
            var key = [file.assetId, file.fileField, file.fileName].join('|');
            var $card = $('<button type="button" class="bioco-file-card"></button>').css({
                border: '1px solid #ddd',
                background: '#fff',
                borderRadius: '8px',
                textAlign: 'left',
                padding: '8px',
                cursor: 'pointer',
                position: 'relative'
            });

            var $preview;
            if (image) {
                $preview = $('<img alt="">').attr('src', file.url).css({
                    width: '100%',
                    height: '110px',
                    objectFit: 'cover',
                    borderRadius: '6px',
                    display: 'block',
                    background: '#f3f3f3'
                });
            } else {
                $preview = $('<div>FILE</div>').css({
                    width: '100%',
                    height: '110px',
                    borderRadius: '6px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '12px',
                    fontWeight: 700,
                    letterSpacing: '0.08em',
                    color: '#666',
                    background: '#f3f3f3'
                });
            }

            var $name = $('<div></div>').text(file.fileName).css({
                marginTop: '8px',
                fontSize: '12px',
                lineHeight: '1.25',
                wordBreak: 'break-all'
            });

            var $check = $('<span>Selected</span>').css({
                position: 'absolute',
                top: '8px',
                right: '8px',
                fontSize: '11px',
                background: '#0a7d2b',
                color: '#fff',
                borderRadius: '999px',
                padding: '2px 8px',
                display: 'none'
            });

            $card.append($preview, $name, $check);
            $card.on('click', function() {
                if (selected[key]) {
                    delete selected[key];
                    $card.css({ borderColor: '#ddd', boxShadow: 'none' });
                    $check.hide();
                } else {
                    selected[key] = file;
                    $card.css({ borderColor: '#0a7d2b', boxShadow: '0 0 0 2px rgba(10,125,43,0.18)' });
                    $check.show();
                }
                $import.prop('disabled', selectedCount() < 1).text('Import selected (' + selectedCount() + ')');
            });

            $grid.append($card);
        });

        var $foot = $('<div></div>').css({
            borderTop: '1px solid #e6e6e6',
            padding: '10px 14px',
            textAlign: 'right'
        });
        var $import = $('<button type="button" class="ui-button ui-priority-primary" disabled>Import selected (0)</button>').css({ marginRight: '8px' });
        var $cancel = $('<button type="button" class="ui-button">Cancel</button>');
        function closePicker() {
            $overlay.remove();
            $(document).off('keydown.biocoFilePicker');
        }
        $import.on('click', function() {
            var picks = Object.keys(selected).map(function(k) { return selected[k]; });
            if (!picks.length) return;
            closePicker();
            onSelect(picks);
        });
        $cancel.on('click', function() { closePicker(); });
        $foot.append($import, $cancel);

        $modal.append($head, $grid, $foot);
        $overlay.append($modal);
        $('body').append($overlay);

        $overlay.on('click', function(e) {
            if (e.target === this) closePicker();
        });
        $(document).on('keydown.biocoFilePicker', function(e) {
            if (e.key === 'Escape') {
                closePicker();
            }
        });
    }

    // ================================================================
    // Media Library browse button
    // ================================================================
    function addMediaLibraryButtons() {
        $('.InputfieldImage, .InputfieldFile').each(function() {
            var $mediaField = $(this);
            if ($mediaField.find('.browse-media-library-btn').length > 0) return;

            var $insertAfter = $mediaField.find('.InputfieldFileUpload').first();
            if ($insertAfter.length === 0) {
                // UIkit/modern admin themes: upload button is wrapped by .uk-form-custom.
                $insertAfter = $mediaField.find('.uk-form-custom').first();
            }
            if ($insertAfter.length === 0) {
                var $fileInput = $mediaField.find('input[type="file"]').first();
                if ($fileInput.length) {
                    $insertAfter = $fileInput.closest('.uk-form-custom');
                    if ($insertAfter.length === 0) {
                        $insertAfter = $fileInput;
                    }
                }
            }
            if ($insertAfter.length === 0) return;

            var $browseBtn = $('<button type="button" class="ui-button ui-widget ui-corner-all browse-media-library-btn">')
                .css({ 'margin-left': '10px', 'position': 'relative', 'z-index': '20' })
                .html('<i class="fa fa-folder-open"></i> Media Library');

            $browseBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $field = $(this).closest('.InputfieldImage, .InputfieldFile');
                var targetField = getTargetFieldName($field);
                var repeaterItemId = getRepeaterItemId($field);
                var pageId = getPageEditId();
                currentImportContext = {
                    targetField: targetField,
                    targetPageId: pageId,
                    repeaterItemId: repeaterItemId,
                    $field: $field
                };

                // Fetch all media files directly, show inline picker
                $.getJSON(ProcessWire.config.urls.root + 'api/media-files')
                    .done(function(res) {
                        if (!res || !res.success || !res.files || !res.files.length) {
                            alert('No files in media library.');
                            return;
                        }
                        openVisualFileChooser(res.files, function(chosen) {
                            importSelectedMediaBatch(chosen);
                        });
                    })
                    .fail(function() {
                        alert('Failed loading media library.');
                    });
            });

            var $wrap = $('<span class="bioco-media-library-btn-wrap" style="display:inline-block;vertical-align:middle;position:relative;z-index:20"></span>');
            $wrap.append($browseBtn);
            $insertAfter.after($wrap);
        });
    }

    function importSelectedMedia(payload, options) {
        options = options || {};
        var reloadOnSuccess = options.reloadOnSuccess !== false;

        if (
            !currentImportContext
            || (!currentImportContext.targetPageId && !currentImportContext.repeaterItemId)
            || !currentImportContext.targetField
        ) {
            alert('Import context missing. Please reopen media library from the image field.');
            return $.Deferred().reject('missing_context').promise();
        }
        return $.ajax({
            url: ProcessWire.config.urls.root + 'api/media-import',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                targetPageId: currentImportContext.targetPageId,
                repeaterItemId: currentImportContext.repeaterItemId,
                targetField: currentImportContext.targetField,
                assetId: payload.assetId,
                fileField: payload.fileField,
                fileName: payload.fileName
            }),
        }).then(function(response) {
            if (!response || !response.success) {
                var reason = (response && response.error) || 'unknown error';
                alert('Import failed: ' + reason);
                return $.Deferred().reject(reason).promise();
            }
            if (reloadOnSuccess) {
                window.location.reload();
            }
            return response;
        }, function(xhr) {
            var msg = (xhr && xhr.responseJSON && xhr.responseJSON.error) || 'Import request failed';
            alert(msg);
            return $.Deferred().reject(msg).promise();
        });
    }

    function importSelectedMediaBatch(items) {
        if (!Array.isArray(items) || !items.length) return;
        if (
            !currentImportContext
            || (!currentImportContext.targetPageId && !currentImportContext.repeaterItemId)
            || !currentImportContext.targetField
        ) {
            alert('Import context missing. Please reopen media library from the image field.');
            return;
        }

        function formatBatchFailures(response, fallback) {
            var failed = response && Array.isArray(response.failed) ? response.failed : [];
            if (!failed.length) return fallback;
            var parts = failed.slice(0, 3).map(function(f) {
                var name = (f.item && f.item.fileName) ? f.item.fileName : 'unknown';
                var err = f.error || 'failed';
                return name + ': ' + err;
            });
            var extra = failed.length > 3 ? ' (+' + (failed.length - 3) + ' more)' : '';
            return parts.join(' | ') + extra;
        }

        $.ajax({
            url: ProcessWire.config.urls.root + 'api/media-import-batch',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                targetPageId: currentImportContext.targetPageId,
                repeaterItemId: currentImportContext.repeaterItemId,
                targetField: currentImportContext.targetField,
                items: items
            })
        }).done(function(response) {
            if (!response || !response.success) {
                var msg = formatBatchFailures(response, (response && response.error) || 'Batch import failed');
                alert(msg);
                return;
            }
            if (response.failedCount) {
                var details = (response.failed || []).map(function(f) {
                    return (f.item && f.item.fileName ? f.item.fileName : 'unknown') + ': ' + (f.error || 'failed');
                }).join(' | ');
                alert('Imported ' + response.importedCount + ', failed ' + response.failedCount + '. ' + details);
            }
            window.location.reload();
        }).fail(function(xhr) {
            var res = xhr && xhr.responseJSON;
            var msg = formatBatchFailures(res, (res && res.error) || 'Batch import request failed');
            alert(msg);
        });
    }

    function renderUsageBlocksInMediaLibrary() {
        if (!isMediaPage()) return;
        var seen = {};
        $('a[href*="page/edit/?id="]').each(function() {
            var $a = $(this);
            var href = $a.attr('href') || '';
            var match = href.match(/[?&]id=(\d+)/);
            if (!match) return;
            var assetId = parseInt(match[1], 10);
            if (!assetId || seen[assetId]) return;
            seen[assetId] = true;

            var $card = findClosestCard($a);
            if (!$card.length || $card.find('.bioco-media-usage').length) return;
            var $usage = $('<div class="bioco-media-usage uk-text-small" style="margin-top:6px;color:#666">Used in: loading...</div>');
            $card.append($usage);

            $.getJSON(ProcessWire.config.urls.root + 'api/media-usage', { assetId: assetId })
                .done(function(res) {
                    if (!res || !res.success) {
                        $usage.text('Used in: unavailable');
                        return;
                    }
                    if (!res.count) {
                        $usage.text('Used in: none');
                        return;
                    }
                    var list = res.items.map(function(item) {
                        var page = item.pagePath || item.pageTitle || ('#' + item.pageId);
                        return page + ' (' + item.field + (item.repeaterItemId ? ', repeater #' + item.repeaterItemId : '') + ')';
                    });
                    $usage.text('Used in: ' + list.join(' | '));
                })
                .fail(function() {
                    $usage.text('Used in: unavailable');
                });
        });
    }

    // ================================================================
    // Image editor buttons (Filerobot)
    // ================================================================
    function addImageEditorButtons() {
        $('.InputfieldImage .InputfieldFileItem').each(function() {
            if ($(this).find('.edit-image-btn').length) return;
            var $thumb = $(this).find('.InputfieldFileLink img, .gridImage__overflow img');
            if (!$thumb.length) return;

            // Resolve original image URL: strip PW variation suffix (e.g. .200x0.jpg -> .jpg)
            var thumbSrc = $thumb.attr('src');
            var imgUrl = thumbSrc.replace(/\.\d+x\d+(\.\w+)$/, '$1');
            var $editBtn = $('<button type="button" class="edit-image-btn ui-button" style="margin-left:5px">')
                .html('<i class="fa fa-pencil"></i> Bearbeiten');

            $editBtn.on('click', function(e) {
                e.preventDefault();
                openFilerobotEditor(imgUrl, $(this).closest('.InputfieldFileItem'));
            });

            var $actions = $(this).find('.InputfieldFileActions, .gridImage__hover');
            if ($actions.length) {
                $actions.append($editBtn);
            }
        });
    }

    // ================================================================
    // Filerobot Image Editor wrapper
    // ================================================================
    var filerobotLoaded = false;
    var filerobotLoadCallbacks = [];

    function loadFilerobot(callback) {
        if (filerobotLoaded) { callback(); return; }
        filerobotLoadCallbacks.push(callback);
        if (filerobotLoadCallbacks.length > 1) return; // already loading

        var script = document.createElement('script');
        script.src = 'https://unpkg.com/filerobot-image-editor@4.7.0/filerobot-image-editor.min.js';
        script.crossOrigin = 'anonymous';
        script.onload = function() {
            filerobotLoaded = true;
            filerobotLoadCallbacks.forEach(function(cb) { cb(); });
            filerobotLoadCallbacks = [];
        };
        document.head.appendChild(script);
    }

    function openFilerobotEditor(imageUrl, $fileItem) {
        loadFilerobot(function() {
            var container = document.createElement('div');
            container.id = 'filerobot-editor-container';
            container.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999;background:rgba(0,0,0,0.8)';
            document.body.appendChild(container);

            var FilerobotImageEditor = window.FilerobotImageEditor;
            if (!FilerobotImageEditor) {
                alert('Filerobot konnte nicht geladen werden.');
                container.remove();
                return;
            }

            var config = {
                source: imageUrl,
                onSave: function(editedImageObject, designState) {
                    // Upload edited image back to PW
                    saveEditedImage(editedImageObject.imageBase64, imageUrl, $fileItem);
                    editor.terminate();
                    container.remove();
                },
                onClose: function() {
                    editor.terminate();
                    container.remove();
                },
                annotationsCommon: {
                    fill: '#4a7c59' // bioco green
                },
                Crop: { presetsItems: [
                    { titleKey: '16:9', ratio: 16/9 },
                    { titleKey: '4:3', ratio: 4/3 },
                    { titleKey: '1:1', ratio: 1 },
                    { titleKey: '3:4', ratio: 3/4 },
                ]},
                tabsIds: [
                    'Adjust', 'Annotate', 'Filters', 'Finetune', 'Resize'
                ],
                defaultTabId: 'Adjust',
                defaultToolId: 'Crop',
            };

            var editor = new FilerobotImageEditor(container, config);
            editor.render();
        });
    }

    function saveEditedImage(base64Data, originalUrl, $fileItem) {
        $.ajax({
            url: ProcessWire.config.urls.root + 'api/image-edit',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                image: base64Data,
                originalUrl: originalUrl,
                pageId: ProcessWire.config.ProcessPageEdit ? ProcessWire.config.ProcessPageEdit.id : null
            }),
            success: function(response) {
                if (response.success) {
                    // Refresh the image thumbnail
                    var $img = $fileItem.find('img');
                    $img.attr('src', response.url + '?t=' + Date.now());
                } else {
                    alert('Fehler beim Speichern: ' + (response.error || 'Unbekannt'));
                }
            },
            error: function() {
                alert('Fehler beim Speichern des bearbeiteten Bildes.');
            }
        });
    }

    // ================================================================
    // Initialize
    // ================================================================
    addMediaLibraryButtons();
    enforceLibraryOnlyOnPageFields();
    addImageEditorButtons();
    renderUsageBlocksInMediaLibrary();

    $(document).on('reloaded wiretabclick', '.InputfieldRepeater, .Inputfield', function() {
        setTimeout(function() {
            addMediaLibraryButtons();
            enforceLibraryOnlyOnPageFields();
            addImageEditorButtons();
            renderUsageBlocksInMediaLibrary();
        }, 100);
    });

    // ================================================================
    // Preview button on page edit screens
    // ================================================================
    function addPreviewButton() {
        if (!isPageEditProcess()) return;
        if ($('.bioco-preview-btn').length) return;
        var pageId = getPageEditId();
        if (!pageId) return;

        // Fetch page path from API
        $.getJSON(ProcessWire.config.urls.root + 'api/content/page-path?id=' + pageId)
            .done(function(data) {
                if (!data || !data.path) return;
                var draftSecret = data.draftSecret || '';
                if (!draftSecret) return;
                var $btn = $('<button type="button" class="ui-button ui-priority-secondary bioco-preview-btn" style="margin-left:10px">')
                    .html('<i class="fa fa-eye"></i> Vorschau');
                $btn.on('click', function(e) {
                    e.preventDefault();
                    window.open(
                        data.siteUrl + '/api/draft?secret=' + encodeURIComponent(draftSecret) + '&path=' + encodeURIComponent(data.path),
                        '_blank'
                    );
                });
                var $header = $('#ProcessPageEditHeader, #pw-content-head, .PageEditHeader');
                if ($header.length) {
                    $header.first().find('h1, .pw-content-head-title').first().after($btn);
                } else {
                    $('#ProcessPageEdit .Inputfields').first().before($btn);
                }
            });
    }
    addPreviewButton();
    addRecapButton();
    addVisualEditorLink();

    // ================================================================
    // "Rückblick erstellen" button for upcoming events
    // ================================================================
    function addRecapButton() {
        if (!isPageEditProcess()) return;
        if ($('.bioco-recap-btn').length) return;
        var tpl = getEditedTemplateName();
        if (tpl !== 'event') return;

        // Check if event_status is 'upcoming'
        var $statusField = $('[name="event_status"]');
        if (!$statusField.length) return;
        var currentStatus = $statusField.val();
        if (currentStatus !== 'upcoming') return;

        var pageId = getPageEditId();
        if (!pageId) return;

        var $btn = $('<button type="button" class="ui-button ui-priority-secondary bioco-recap-btn" style="margin-left:10px">')
            .html('<i class="fa fa-history"></i> Rückblick erstellen');
        $btn.on('click', function(e) {
            e.preventDefault();
            if (!confirm('Event als vergangen markieren und Rückblick vorbereiten?')) return;
            $btn.prop('disabled', true).text('Wird umgestellt…');
            $.ajax({
                url: ProcessWire.config.urls.root + 'api/content/event-to-recap',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ pageId: pageId })
            }).done(function(res) {
                if (res && res.success) {
                    window.location.reload();
                } else {
                    alert('Fehler: ' + ((res && res.error) || 'Unbekannt'));
                    $btn.prop('disabled', false).html('<i class="fa fa-history"></i> Rückblick erstellen');
                }
            }).fail(function() {
                alert('Anfrage fehlgeschlagen.');
                $btn.prop('disabled', false).html('<i class="fa fa-history"></i> Rückblick erstellen');
            });
        });

        var $header = $('#ProcessPageEditHeader, #pw-content-head, .PageEditHeader');
        if ($header.length) {
            $header.first().find('h1, .pw-content-head-title').first().after($btn);
        } else {
            $('#ProcessPageEdit .Inputfields').first().before($btn);
        }
    }

    // ================================================================
    // Visual Editor link in admin navigation
    // ================================================================
    function addVisualEditorLink() {
        if ($('.bioco-visual-editor-link').length) return;
        var siteRoot = ProcessWire.config.urls.root || '/';
        var veUrl = siteRoot + 'visual-editor/';
        var $link = $('<a class="bioco-visual-editor-link">')
            .attr('href', veUrl)
            .attr('target', '_blank')
            .css({
                display: 'inline-flex',
                alignItems: 'center',
                gap: '6px',
                padding: '6px 14px',
                background: '#4a7c59',
                color: '#fff',
                borderRadius: '6px',
                fontSize: '13px',
                fontWeight: '600',
                textDecoration: 'none',
                marginLeft: '10px',
                cursor: 'pointer',
                transition: 'background 0.15s',
            })
            .html('<i class="fa fa-eye"></i> Visual Editor')
            .on('mouseenter', function() { $(this).css('background', '#3a6c49'); })
            .on('mouseleave', function() { $(this).css('background', '#4a7c59'); });

        // Try multiple PW admin nav selectors
        var placed = false;
        var navSelectors = [
            '#pw-masthead-links',
            '#pw-masthead .pw-masthead-nav',
            '#topnav',
            '.uk-navbar-right',
            '.uk-navbar-nav',
            '#masthead .container',
        ];
        for (var i = 0; i < navSelectors.length; i++) {
            var $nav = $(navSelectors[i]).first();
            if ($nav.length) {
                $nav.append($link);
                placed = true;
                break;
            }
        }
        // Fallback: prepend to page content area
        if (!placed) {
            var $content = $('#pw-content-body, #content, .pw-container').first();
            if ($content.length) {
                $link.css({ marginBottom: '12px', display: 'inline-flex' });
                $content.prepend($link);
            }
        }
    }
});
