/**
 * Admin UI enhancements
 * Adds "Browse Media Library" button to image upload fields
 */

$(document).ready(function() {
    // Add Media Library button to all image input fields
    function addMediaLibraryButtons() {
        $('.InputfieldImage').each(function() {
            var $imageField = $(this);

            // Check if button already exists
            if ($imageField.find('.browse-media-library-btn').length > 0) {
                return;
            }

            // Find the upload button area
            var $uploadBtn = $imageField.find('.InputfieldFileUpload');

            if ($uploadBtn.length > 0) {
                // Create button
                var $browseBtn = $('<button type="button" class="ui-button ui-widget ui-corner-all browse-media-library-btn">')
                    .css({
                        'margin-left': '10px'
                    })
                    .html('<i class="fa fa-folder-open"></i> Medienbibliothek durchsuchen');

                // Add click handler
                $browseBtn.on('click', function(e) {
                    e.preventDefault();

                    // Open media library in new window
                    var mediaUrl = ProcessWire.config.urls.admin + 'media/';
                    window.open(mediaUrl, 'MediaLibrary', 'width=1200,height=800,scrollbars=yes,resizable=yes,toolbar=no,menubar=no');
                });

                // Insert button after upload button
                $uploadBtn.after($browseBtn);
            }
        });
    }

    // Initial run
    addMediaLibraryButtons();

    // Re-run when repeater items are added/reloaded
    $(document).on('reloaded wiretabclick', '.InputfieldRepeater, .Inputfield', function() {
        setTimeout(addMediaLibraryButtons, 100);
    });
});
