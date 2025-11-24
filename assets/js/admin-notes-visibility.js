(function ($) {

    /**
     * When user changes the visibility option, send AJAX request.
     */
    $(document).on('change', '.admin-note-visibility-select', function () {

        let noteId = $(this).closest('.admin-note-visibility-select').data('note-id');
        let visibility = $(this).val();
        
        // let roles = [];
        // // If selected "specific_roles" collect checkboxes
        // if (visibility === 'roles') {
        //     $(this)
        //         .closest('.admin-note')
        //         .find('.admin-note-visibility-roles input[type="checkbox"]:checked')
        //         .each(function () {
        //             roles.push($(this).val());
        //         });
        // }

        $.ajax({
            url: notesVisibility.ajax_url,
            type: 'POST',
            data: {
                action: 'admin_notes_update_visibility',
                note_id: noteId,
                visibility: visibility,
                roles: roles,
                security: notesVisibility.nonce
            },
            success: function (response) {
                console.log('Visibility updated', response);
            },
            error: function (xhr) {
                console.log('Visibility update failed', xhr.responseText);
            }
        });
    });


    /**
     * Save role selections dynamically when a checkbox is clicked.
     */
    // $(document).on('click', '.admin-note-visibility-roles input[type="checkbox"]', function () {

    //     let noteEl = $(this).closest('.admin-note');
    //     let noteId = noteEl.data('id');

    //     let roles = [];
    //     noteEl.find('.admin-note-visibility-roles input[type="checkbox"]:checked').each(function () {
    //         roles.push($(this).val());
    //     });

    //     $.ajax({
    //         url: adminNotes.ajaxUrl,
    //         type: 'POST',
    //         data: {
    //             action: 'admin_notes_update_roles',
    //             note_id: noteId,
    //             roles: roles,
    //             security: adminNotes.nonce
    //         },
    //         success: function (response) {
    //             console.log('Roles updated', response);
    //         }
    //     });

    // });

})(jQuery);
