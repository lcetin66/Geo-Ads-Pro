/* Plugin Name: Geo Ads Pro - admin.js */
/* Date: 20260606 */
/* Author: Levent Cetin - 3CCS.com */

jQuery(function($){
    $('.gap-dropzone').each(function(){
        const dropzone = $(this);
        const input = dropzone.find('.gap-dropzone-input');
        const form = dropzone.closest('.gap-upload-form');
        const list = form.find('.gap-upload-file-list');

        function renderFiles(files) {
            list.empty();

            Array.from(files || []).forEach(function(file){
                $('<li/>').text(file.name).appendTo(list);
            });
        }

        dropzone.on('click keydown', function(event){
            if (event.type === 'click' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.trigger('click');
            }
        });

        input.on('click', function(event){
            event.stopPropagation();
        });

        input.on('change', function(){
            renderFiles(this.files);
        });

        dropzone.on('dragenter dragover', function(event){
            event.preventDefault();
            event.stopPropagation();
            dropzone.addClass('is-dragover');
        });

        dropzone.on('dragleave dragend drop', function(event){
            event.preventDefault();
            event.stopPropagation();
            dropzone.removeClass('is-dragover');
        });

        dropzone.on('drop', function(event){
            const files = event.originalEvent.dataTransfer.files;
            if (!files || !files.length) return;

            input[0].files = files;
            renderFiles(files);
        });
    });
});
