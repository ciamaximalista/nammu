// Nammu admin — modal de confirmación de borrado de contenidos.
        document.addEventListener('DOMContentLoaded', function() {
            var deleteModal = $('#deletePostModal');
            if (!deleteModal.length) {
                return;
            }
            deleteModal.on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var filename = button.data('delete-file') || '';
                var title = button.data('delete-title') || filename;
                var type = button.data('delete-type') || 'single';
                var modal = $(this);
                modal.find('[data-delete-post-title]').text(title || '(sin título)');
                modal.find('[data-delete-post-file]').text(filename || '');
                modal.find('#delete-post-filename').val(filename);
                if (['single', 'page', 'draft', 'newsletter', 'podcast'].indexOf(type) === -1) {
                    type = 'single';
                }
                modal.find('#delete-post-template').val(type);
            });
        });
