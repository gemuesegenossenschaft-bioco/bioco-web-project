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
                var url = ProcessWire.config.urls.admin + 'media/?biocoPicker=1';
                var targetField = getTargetFieldName($field);
                var repeaterItemId = getRepeaterItemId($field);
                var pageId = ProcessWire.config.ProcessPageEdit ? ProcessWire.config.ProcessPageEdit.id : null;
                currentImportContext = {
                    targetField: targetField,
                    targetPageId: pageId,
                    repeaterItemId: repeaterItemId,
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

            var $wrap = $('<span class="bioco-media-library-btn-wrap" style="display:inline-block;vertical-align:middle;position:relative;z-index:20"></span>');
            $wrap.append($browseBtn);
            $insertAfter.after($wrap);
        });
    }

    function importSelectedMedia(payload) {
        if (
            !currentImportContext
            || (!currentImportContext.targetPageId && !currentImportContext.repeaterItemId)
            || !currentImportContext.targetField
        ) {
            alert('Import context missing. Please reopen media library from the image field.');
            return;
        }
        $.ajax({
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

        function isLikelyImage(fileName) {
            var n = String(fileName || '').toLowerCase();
            return /\.(jpg|jpeg|png|webp|gif|avif|svg)$/.test(n);
        }

        function openVisualFileChooser(files, onSelect) {
            if (!files || !files.length) {
                alert('No files available.');
                return;
            }
            if (files.length === 1) {
                onSelect(files[0]);
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

            var $head = $('<div><strong>Select media file</strong><div style="font-size:12px;color:#666">Click a thumbnail to import</div></div>').css({
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

            files.forEach(function(file) {
                var image = isLikelyImage(file.fileName);
                var $card = $('<button type="button" class="bioco-file-card"></button>').css({
                    border: '1px solid #ddd',
                    background: '#fff',
                    borderRadius: '8px',
                    textAlign: 'left',
                    padding: '8px',
                    cursor: 'pointer'
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

                $card.append($preview, $name);
                $card.on('click', function() {
                    $(document).off('keydown.biocoFilePicker');
                    $overlay.remove();
                    onSelect(file);
                });

                $grid.append($card);
            });

            var $foot = $('<div></div>').css({
                borderTop: '1px solid #e6e6e6',
                padding: '10px 14px',
                textAlign: 'right'
            });
            var $cancel = $('<button type="button" class="ui-button">Cancel</button>');
            function closePicker() {
                $overlay.remove();
                $(document).off('keydown.biocoFilePicker');
            }
            $cancel.on('click', function() { closePicker(); });
            $foot.append($cancel);

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

        function sendSelection(chosen) {
            var payload = {
                type: 'bioco-media-selected',
                assetId: chosen.assetId,
                fileField: chosen.fileField,
                fileName: chosen.fileName
            };
            if (window.opener) {
                window.opener.postMessage(payload, window.location.origin);
                window.close();
                return;
            }
            if (window.parent && window.parent !== window) {
                window.parent.postMessage(payload, window.location.origin);
                try {
                    var $parent = window.parent.jQuery;
                    if ($parent) {
                        $parent('.pw-modal-window .pw-modal-close, .ui-dialog-titlebar-close').first().trigger('click');
                    }
                } catch (e) {
                    // no-op
                }
                return;
            }
            alert('Picker parent window not found.');
        }

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
                    openVisualFileChooser(res.files, function(chosen) {
                        sendSelection(chosen);
                    });
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
