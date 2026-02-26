/**
 * Admin UI enhancements
 * - "Browse Media Library" button on image fields (uses PW modal)
 * - "Edit Image" button with Filerobot Image Editor
 */

$(document).ready(function() {

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
                var url = ProcessWire.config.urls.admin + 'media/';

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
    addImageEditorButtons();

    $(document).on('reloaded wiretabclick', '.InputfieldRepeater, .Inputfield', function() {
        setTimeout(function() {
            addMediaLibraryButtons();
            addImageEditorButtons();
        }, 100);
    });
});
