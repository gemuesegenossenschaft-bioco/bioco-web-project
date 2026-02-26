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
        var inputName = $field.find('input[name]').first().attr('name') || '';
        if (inputName) return inputName.replace(/\[\]$/, '');
        return '';
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

    // ================================================================
    // Media Library browse button
    // ================================================================
    function addMediaLibraryButtons() {
        $('.InputfieldImage').each(function() {
            var $imageField = $(this);
            if ($imageField.find('.browse-media-library-btn').length > 0) return;

            var $uploadBtn = $imageField.find('.InputfieldFileUpload');
            if ($uploadBtn.length === 0) return;

            var $browseBtn = $('<button type="button" class="ui-button ui-widget ui-corner-all browse-media-library-btn">')
                .css({ 'margin-left': '10px' })
                .html('<i class="fa fa-folder-open"></i> Medienbibliothek durchsuchen');

            $browseBtn.on('click', function(e) {
                e.preventDefault();
                var $field = $(this).closest('.InputfieldImage');
                var url = ProcessWire.config.urls.admin + 'media/?biocoPicker=1';
                var targetField = getTargetFieldName($field);
                var pageId = ProcessWire.config.ProcessPageEdit ? ProcessWire.config.ProcessPageEdit.id : null;
                currentImportContext = {
                    targetField: targetField,
                    targetPageId: pageId,
                    $field: $field
                };

                // Use PW modal if available, else fallback to window.open
                if (typeof pwModalWindow === 'function') {
                    var $iframe = pwModalWindow(url, {}, 'large');
                    $iframe.on('pw-modal-closed', function() {
                        $field.find('.InputfieldFileUpload').trigger('reload');
                    });
                } else {
                    window.open(url, 'MediaLibrary', 'width=1200,height=800,scrollbars=yes,resizable=yes');
                }
            });

            $uploadBtn.after($browseBtn);
        });
    }

    function importSelectedMedia(payload) {
        if (!currentImportContext || !currentImportContext.targetPageId || !currentImportContext.targetField) {
            alert('Import context missing. Please reopen media library from the image field.');
            return;
        }
        $.ajax({
            url: ProcessWire.config.urls.root + 'api/media-import',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                targetPageId: currentImportContext.targetPageId,
                targetField: currentImportContext.targetField,
                assetId: payload.assetId,
                fileField: payload.fileField,
                fileName: payload.fileName
            }),
            success: function(response) {
                if (!response || !response.success) {
                    alert('Import failed: ' + ((response && response.error) || 'unknown error'));
                    return;
                }
                // Full reload is the most reliable way to make ProcessWire re-render file items.
                window.location.reload();
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Import request failed';
                alert(msg);
            }
        });
    }

    function bindParentMessageListener() {
        window.addEventListener('message', function(evt) {
            var data = evt.data || {};
            if (data.type !== 'bioco-media-selected') return;
            importSelectedMedia(data);
        });
    }

    function initPickerWindowMode() {
        var query = parseQuery();
        if (!query.biocoPicker) return false;
        if (!isMediaPage()) return false;

        $('body').addClass('bioco-media-picker-mode');
        $(document).on('click', 'a[href*="page/edit/?id="]', function(e) {
            var href = $(this).attr('href') || '';
            var match = href.match(/[?&]id=(\d+)/);
            if (!match) return;
            e.preventDefault();
            var assetId = parseInt(match[1], 10);
            $.getJSON(ProcessWire.config.urls.root + 'api/media-files', { assetId: assetId })
                .done(function(res) {
                    if (!res || !res.success || !res.files || !res.files.length) {
                        alert('No files found in selected media item.');
                        return;
                    }
                    var chosen = res.files[0];
                    if (res.files.length > 1) {
                        var names = res.files.map(function(f, i) { return (i + 1) + ': ' + f.fileName; }).join('\n');
                        var selected = prompt('Select file number:\n' + names, '1');
                        var idx = Math.max(1, parseInt(selected || '1', 10)) - 1;
                        chosen = res.files[idx] || res.files[0];
                    }
                    if (window.opener) {
                        window.opener.postMessage({
                            type: 'bioco-media-selected',
                            assetId: chosen.assetId,
                            fileField: chosen.fileField,
                            fileName: chosen.fileName
                        }, window.location.origin);
                        window.close();
                    } else {
                        alert('Picker opener not found.');
                    }
                })
                .fail(function() {
                    alert('Failed loading media files.');
                });
        });
        return true;
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
    if (initPickerWindowMode()) {
        renderUsageBlocksInMediaLibrary();
        return;
    }

    bindParentMessageListener();
    addMediaLibraryButtons();
    addImageEditorButtons();
    renderUsageBlocksInMediaLibrary();

    $(document).on('reloaded wiretabclick', '.InputfieldRepeater, .Inputfield', function() {
        setTimeout(function() {
            addMediaLibraryButtons();
            addImageEditorButtons();
            renderUsageBlocksInMediaLibrary();
        }, 100);
    });
});
