// Nammu admin — modal de recursos: selección, subida, inserción de imágenes/vídeo/audio en el editor y galerías.

        $(document).ready(function() {

            var imageTargetMode = '';
            var imageTargetInput = '';
            var imageTargetPrefix = '';
            var imageTargetEditor = '';
            var imageTargetAccept = '';
            var imageTargetMulti = '';
            var imageTargetMaxItems = 0;
            var lastImageTrigger = null;
            var imageTargetSelection = null;
            var imageTargetTextarea = null;
            var skipImageModalSelectionCapture = false;
            var modalSearchInput = $('#modal-image-search');
            var tagsInput = $('#image_tags');
            var tagsTargetInput = $('#image-tags-target');
            var tagsModal = $('#tagsModal');
            var tagsModalInput = $('#tagsModalInput');
            var tagsModalTarget = $('#tagsModalTarget');
            var tagsModalRedirect = $('#tagsModalRedirect');
            var insertActions = $('#image-insert-actions');
            var insertActionGroups = insertActions.find('[data-insert-group]');
            var modalEmpty = $('#image-modal-empty');
            var modalCount = $('#image-modal-count');
            var modalSelectedHelp = $('#image-modal-selected-help');
            var galleryActions = $('#image-gallery-actions');
            var galleryCount = $('#image-gallery-count');
            var galleryInsertBtn = $('#image-gallery-insert');
            var galleryClearBtn = $('#image-gallery-clear');
            var galleryOrderInputs = $('[name="image_gallery_order"]');
            var galleryOrderLabels = $('[data-gallery-order]');
            var pendingInsert = null;
            var pendingGalleryItems = [];
            function getQueryParam(name) {
                var params = new URLSearchParams(window.location.search);
                return params.get(name);
            }

            var isResourcesPage = window.location.search.indexOf('page=resources') !== -1 || window.location.href.indexOf('admin.php') !== -1 && !window.location.search;
            var resourceScrollKey = 'nammuResourceScroll';
            var resourcesPageFromUrl = parseInt(getQueryParam('p'), 10);
            resourcesPageFromUrl = isNaN(resourcesPageFromUrl) ? null : resourcesPageFromUrl;
            var currentResourcesPage = resourcesPageFromUrl || (parseInt($('#resource-gallery').data('resources-page'), 10) || 1);
            var resourcesSearchFromUrl = getQueryParam('search') || '';
            var currentResourcesSearch = resourcesSearchFromUrl !== '' ? resourcesSearchFromUrl : (($('#resource-gallery').data('resources-search') || '').toString());
            var calloutModal = $('#calloutModal');
            var calloutTitleInput = $('#calloutTitle');
            var calloutBodyInput = $('#calloutBody');
            var calloutInsertBtn = $('#calloutInsert');
            var calloutTarget = null;
            var calloutTargetSelector = '';
            var lastFocusedTextarea = null;
            var calloutModalEl = document.getElementById('calloutModal');
            var autosavePayloadInput = document.getElementById('imageUploadAutosavePayload');
            var imageUploadForm = document.querySelector('#imageModal form');
            var uploadTargetTypeInput = document.getElementById('imageUploadTargetType');
            var uploadTargetInputInput = document.getElementById('imageUploadTargetInput');
            var uploadTargetEditorInput = document.getElementById('imageUploadTargetEditor');
            var uploadTargetPrefixInput = document.getElementById('imageUploadTargetPrefix');
            var uploadTargetSelectionStart = document.getElementById('imageUploadSelectionStart');
            var uploadTargetSelectionEnd = document.getElementById('imageUploadSelectionEnd');
            var uploadTargetSelectionScroll = document.getElementById('imageUploadSelectionScroll');
            var assetApply = window.nammuAssetApply || null;

            function collectEditorForm() {
                if (lastImageTrigger) {
                    var triggerForm = $(lastImageTrigger).closest('form');
                    if (triggerForm.length) {
                        return { context: detectContext(triggerForm), form: triggerForm };
                    }
                }
                var editForm = $('#content_edit').closest('form');
                if (editForm.length) {
                    return { context: 'edit', form: editForm };
                }
                var publishForm = $('#content_publish').closest('form');
                if (publishForm.length) {
                    return { context: 'publish', form: publishForm };
                }
                var itineraryForm = $('#itinerary-form form');
                if (itineraryForm.length) {
                    return { context: 'itinerary', form: itineraryForm };
                }
                var topicForm = $('#topic-form form');
                if (topicForm.length) {
                    return { context: 'topic', form: topicForm };
                }
                return null;
            }

            function resetGallerySelection() {
                pendingGalleryItems = [];
                $('[data-media-gallery-target]').removeClass('border-primary shadow').attr('aria-pressed', 'false');
                galleryOrderInputs.filter('[value="manual"]').prop('checked', true);
                galleryOrderLabels.removeClass('active');
                galleryOrderLabels.filter('[data-gallery-order="manual"]').addClass('active');
                if (galleryCount.length) {
                    galleryCount.text('0');
                }
                if (galleryActions.length) {
                    galleryActions.addClass('d-none');
                }
            }

            function syncGallerySelectionUi() {
                if (galleryCount.length) {
                    galleryCount.text(String(pendingGalleryItems.length));
                }
                if (!galleryActions.length) {
                    return;
                }
                if (imageTargetMode === 'editor-gallery' && pendingGalleryItems.length > 0) {
                    galleryActions.removeClass('d-none');
                } else {
                    galleryActions.addClass('d-none');
                }
            }

            function toggleGalleryItemSelection($media) {
                if (!$media || !$media.length) {
                    return;
                }
                var mediaType = mediaData($media, 'mediaType', 'data-media-type', '').toString().toLowerCase();
                if (mediaType !== 'image') {
                    alert('La galería solo admite imágenes guardadas en Recursos.');
                    return;
                }
                var mediaSrc = mediaData($media, 'mediaSrc', 'data-media-src', '').toString();
                if (!mediaSrc) {
                    return;
                }
                var existingIndex = -1;
                pendingGalleryItems.forEach(function(item, index) {
                    if (item.src === mediaSrc) {
                        existingIndex = index;
                    }
                });
                if (existingIndex >= 0) {
                    pendingGalleryItems.splice(existingIndex, 1);
                    $media.removeClass('border-primary shadow').attr('aria-pressed', 'false');
                } else {
                    pendingGalleryItems.push({
                        src: mediaSrc,
                        name: mediaData($media, 'mediaName', 'data-media-name', '').toString(),
                        tags: mediaData($media, 'mediaTags', 'data-media-tags', '').toString()
                    });
                    $media.addClass('border-primary shadow').attr('aria-pressed', 'true');
                }
                syncGallerySelectionUi();
            }

            function galleryItemSortLabel(item) {
                return (item && (item.name || item.src) ? (item.name || item.src) : '').toString().toLocaleLowerCase();
            }

            function orderedGalleryItems(items) {
                var order = (galleryOrderInputs.filter(':checked').val() || 'manual').toString();
                var ordered = (items || []).slice();
                if (order === 'asc' || order === 'desc') {
                    ordered.sort(function(a, b) {
                        var result = galleryItemSortLabel(a).localeCompare(galleryItemSortLabel(b), undefined, { numeric: true, sensitivity: 'base' });
                        return order === 'desc' ? -result : result;
                    });
                }
                return ordered;
            }

            function buildGallerySnippet(items) {
                if (!items || !items.length) {
                    return '';
                }
                var blocks = items.map(function(item) {
                    var imageText = resolveImageText(item.tags || item.name || '');
                    var safeAlt = escapeHtmlAttr(imageText || '');
                    var safeTitle = escapeHtmlAttr(imageText || '');
                    var safeSrc = escapeHtmlAttr(item.src || '');
                    return '        <a class="nammu-inline-gallery__link" href="' + safeSrc + '" target="_blank" rel="noopener">\n'
                        + '            <img src="' + safeSrc + '" alt="' + safeAlt + '"' + (safeTitle ? ' title="' + safeTitle + '"' : '') + ' class="nammu-inline-gallery__image" />\n'
                        + '        </a>';
                });
                return '\n\n<details class="nammu-inline-gallery">\n'
                    + '    <summary>Galería</summary>\n'
                    + '    <div class="nammu-inline-gallery__grid">\n'
                    + blocks.join('\n')
                    + '\n    </div>\n'
                    + '</details>\n\n';
            }

            function detectContext($form) {
                if ($form.find('#content_edit').length) {
                    return 'edit';
                }
                if ($form.find('#content_publish').length) {
                    return 'publish';
                }
                if ($form.find('#itinerary_content').length) {
                    return 'itinerary';
                }
                if ($form.find('#topic_content').length) {
                    return 'topic';
                }
                return 'generic';
            }

            function buildAutosavePayload() {
                var ctx = collectEditorForm();
                if (!ctx) {
                    return '';
                }
                var form = ctx.form;
                var fields = {};
                if (ctx.context === 'itinerary') {
                    fields = {
                        itinerary_title: form.find('[name="itinerary_title"]').val() || '',
                        itinerary_description: form.find('[name="itinerary_description"]').val() || '',
                        itinerary_content: form.find('[name="itinerary_content"]').val() || '',
                        itinerary_image: form.find('[name="itinerary_image"]').val() || '',
                        itinerary_status: form.find('[name="itinerary_status"]').val() || '',
                        itinerary_slug: form.find('[name="itinerary_slug"]').val() || '',
                        itinerary_class: form.find('[name="itinerary_class"]').val() || '',
                        itinerary_class_custom: form.find('[name="itinerary_class_custom"]').val() || '',
                        itinerary_usage_logic: form.find('[name="itinerary_usage_logic"]:checked').val() || '',
                        itinerary_quiz_payload: form.find('[name="itinerary_quiz_payload"]').val() || ''
                    };
                } else if (ctx.context === 'topic') {
                    fields = {
                        topic_title: form.find('[name="topic_title"]').val() || '',
                        topic_description: form.find('[name="topic_description"]').val() || '',
                        topic_content: form.find('[name="topic_content"]').val() || '',
                        topic_image: form.find('[name="topic_image"]').val() || '',
                        topic_slug: form.find('[name="topic_slug"]').val() || '',
                        topic_number: form.find('[name="topic_number"]').val() || '',
                        topic_quiz_payload: form.find('[name="topic_quiz_payload"]').val() || '',
                        topic_itinerary_slug: form.find('[name="topic_itinerary_slug"]').val() || ''
                    };
                } else {
                    fields = {
                        title: form.find('[name="title"]').val() || '',
                        category: form.find('[name="category"]').val() || '',
                        date: form.find('[name="date"]').val() || '',
                        image: form.find('[name="image"]').val() || '',
                        description: form.find('[name="description"]').val() || '',
                        content: form.find('[name="content"]').val() || '',
                        type: form.find('[name="type"]').val() || '',
                        status: form.find('[name="status"]').val() || '',
                        filename: form.find('[name="filename"]').val() || '',
                        new_filename: form.find('[name="new_filename"]').val() || '',
                        related_slugs: form.find('[name="related_slugs"]').val() || '',
                        lang: form.find('[name="lang"]').val() || '',
                        page_visibility: form.find('[name="page_visibility"]').val() || '',
                        audio: form.find('[name="audio"]').val() || '',
                        audio_length: form.find('[name="audio_length"]').val() || '',
                        audio_duration: form.find('[name="audio_duration"]').val() || ''
                    };
                }
                var hasContent = Object.keys(fields).some(function(key) {
                    var value = fields[key];
                    return value && value.toString().trim() !== '';
                });
                if (!hasContent) {
                    return '';
                }
                return JSON.stringify({
                    context: ctx.context,
                    fields: fields
                });
            }

            if (imageUploadForm) {
                imageUploadForm.addEventListener('submit', function() {
                    if (!autosavePayloadInput) {
                        return;
                    }
                    autosavePayloadInput.value = buildAutosavePayload();
                    if (uploadTargetTypeInput) uploadTargetTypeInput.value = imageTargetMode || '';
                    if (uploadTargetInputInput) uploadTargetInputInput.value = imageTargetInput || '';
                    if (uploadTargetEditorInput) uploadTargetEditorInput.value = imageTargetEditor || '';
                    if (uploadTargetPrefixInput) uploadTargetPrefixInput.value = imageTargetPrefix || '';
                    if (uploadTargetSelectionStart) {
                        uploadTargetSelectionStart.value = imageTargetSelection ? String(imageTargetSelection.start || 0) : '';
                    }
                    if (uploadTargetSelectionEnd) {
                        uploadTargetSelectionEnd.value = imageTargetSelection ? String(imageTargetSelection.end || 0) : '';
                    }
                    if (uploadTargetSelectionScroll) {
                        uploadTargetSelectionScroll.value = imageTargetSelection ? String(imageTargetSelection.scrollTop || 0) : '';
                    }
                });
            }

            $(document).on('focusin', 'textarea', function() {
                lastFocusedTextarea = this;
            });

            function fallbackTextarea() {
                var active = document.activeElement;
                if (active && active.tagName === 'TEXTAREA' && active.id !== 'calloutBody') {
                    return active;
                }
                if (lastFocusedTextarea && lastFocusedTextarea.tagName === 'TEXTAREA' && lastFocusedTextarea.id !== 'calloutBody') {
                    return lastFocusedTextarea;
                }
                var editorTextarea = document.querySelector('[data-markdown-editor]');
                if (editorTextarea && editorTextarea.tagName === 'TEXTAREA') {
                    return editorTextarea;
                }
                var anyTextarea = document.querySelector('textarea');
                return anyTextarea || null;
            }

            function resolveImageTargetTextarea() {
                var target = null;
                if (imageTargetEditor) {
                    try {
                        target = document.querySelector(imageTargetEditor);
                    } catch (selectorError) {
                        target = null;
                    }
                }
                if (!target && document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                    target = document.activeElement;
                }
                if (!target && lastFocusedTextarea && lastFocusedTextarea.tagName === 'TEXTAREA') {
                    target = lastFocusedTextarea;
                }
                if (!target) {
                    target = fallbackTextarea();
                }
                if (target && target.id === 'calloutBody') {
                    return null;
                }
                return target;
            }

            $(document).on('click', '[data-md-action="callout"]', function(evt) {
                evt.preventDefault();
                evt.stopPropagation();
                if (typeof evt.stopImmediatePropagation === 'function') {
                    evt.stopImmediatePropagation();
                }
                var toolbar = this.closest('[data-markdown-toolbar]');
                var target = null;
                if (toolbar) {
                    var selector = toolbar.getAttribute('data-target');
                    if (selector) {
                        try {
                            target = document.querySelector(selector);
                        } catch (e) {
                            target = null;
                        }
                    }
                    if (!target) {
                        var sib = toolbar.nextElementSibling;
                        if (sib && sib.tagName === 'TEXTAREA') {
                            target = sib;
                        }
                    }
                }
                if (!target || target.tagName !== 'TEXTAREA' || target.id === 'calloutBody') {
                    target = fallbackTextarea();
                }
                if (target && target.id === 'calloutBody') {
                    target = document.querySelector('[data-markdown-editor]') || document.querySelector('#content_edit, #content_publish, #itinerary_content, #topic_content');
                }
                calloutTarget = target && target.tagName === 'TEXTAREA' ? target : null;
                ensureCalloutModal();
                if (calloutTitleInput.length && !calloutTitleInput.val()) {
                    calloutTitleInput.val('Aviso');
                }
                if (calloutBodyInput.length) {
                    calloutBodyInput.val('');
                }
                if (calloutModal.length && typeof calloutModal.modal === 'function') {
                    calloutModal.modal('show');
                } else if (calloutModal.length) {
                    calloutModal.addClass('show').css('display', 'block').attr('aria-hidden', 'false');
                }
            });
            function ensureCalloutModal() {
                calloutModal = $('#calloutModal');
                calloutTitleInput = $('#calloutTitle');
                calloutBodyInput = $('#calloutBody');
                calloutInsertBtn = $('#calloutInsert');
                if (!calloutModal.length) {
                    var html = [
                        '<div class="modal fade" id="calloutModal" tabindex="-1" role="dialog" aria-labelledby="calloutModalLabel" aria-hidden="true">',
                        '  <div class="modal-dialog" role="document">',
                        '    <div class="modal-content">',
                        '      <div class="modal-header">',
                        '        <h5 class="modal-title" id="calloutModalLabel">Caja destacada</h5>',
                        '        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">',
                        '          <span aria-hidden="true">&times;</span>',
                        '        </button>',
                        '      </div>',
                        '      <div class="modal-body">',
                        '        <div class="form-group">',
                        '          <label for="calloutTitle">Título</label>',
                        '          <input type="text" id="calloutTitle" class="form-control" value="Aviso">',
                        '        </div>',
                        '        <div class="form-group">',
                        '          <label for="calloutBody">Contenido del aviso</label>',
                        '          <textarea id="calloutBody" class="form-control" rows="4" placeholder="Añade aquí bibliografía, enlaces o notas."></textarea>',
                        '        </div>',
                        '      </div>',
                        '      <div class="modal-footer">',
                        '        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>',
                        '        <button type="button" class="btn btn-primary" id="calloutInsert">Insertar</button>',
                        '      </div>',
                        '    </div>',
                        '  </div>',
                        '</div>'
                    ].join('');
                    $('body').append(html);
                    calloutModal = $('#calloutModal');
                    calloutTitleInput = $('#calloutTitle');
                    calloutBodyInput = $('#calloutBody');
                    calloutInsertBtn = $('#calloutInsert');
                }
            }

        

            $('#imageModal').on('show.bs.modal', function (event) {

                var button = $(event.relatedTarget);
                lastImageTrigger = button && button.length ? button[0] : null;

                if (button && button.length) {
                    imageTargetMode = button.data('target-type') || '';
                    imageTargetInput = button.data('target-input') || '';
                    imageTargetPrefix = button.data('target-prefix') || '';
                    imageTargetEditor = button.data('target-editor') || '';
                    imageTargetAccept = button.data('target-accept') || '';
                    imageTargetMulti = button.data('target-multi') || '';
                    imageTargetMaxItems = parseInt(button.data('target-max-items') || '0', 10) || 0;
                }
                resetGallerySelection();
                clearSelectedMedia();
                refreshGalleryItems();
                if (!skipImageModalSelectionCapture) {
                    imageTargetTextarea = resolveImageTargetTextarea();
                    if (imageTargetTextarea && typeof imageTargetTextarea.selectionStart === 'number') {
                        imageTargetSelection = {
                            start: imageTargetTextarea.selectionStart,
                            end: typeof imageTargetTextarea.selectionEnd === 'number' ? imageTargetTextarea.selectionEnd : imageTargetTextarea.selectionStart,
                            scrollTop: imageTargetTextarea.scrollTop
                        };
                    } else {
                        imageTargetSelection = null;
                    }
                } else {
                    skipImageModalSelectionCapture = false;
                }
                var anchorInput = document.getElementById('imageUploadRedirectAnchor');
                if (anchorInput) {
                    var anchorVal = '';
                    if (button && button.length) {
                        anchorVal = button.data('redirect-anchor') || '';
                    }
                    anchorInput.value = anchorVal || '';
                }
                if (modalSearchInput.length) {
                    modalSearchInput.val('');
                    applyModalFilter('');
                }
                pendingInsert = null;
                if (insertActions.length) {
                    insertActions.addClass('d-none');
                }
                if (galleryActions.length) {
                    galleryActions.addClass('d-none');
                }
                if (imageTargetMode === 'uploader') {
                    // Nada especial, solo aseguramos que la búsqueda queda limpia
                    return;
                }

            });

            $('#imageModal').on('hidden.bs.modal', function () {
                imageTargetSelection = null;
                imageTargetTextarea = null;
                clearSelectedMedia();
                resetGallerySelection();
            });

        

            var currentPage = 1;

            var itemsPerPage = 8;

            var galleryItems = $();

            var filteredGalleryItems = $();

            var totalItems = 0;

            var totalPages = 1;

            function refreshGalleryItems() {
                galleryItems = $('.image-gallery .gallery-item');
                filteredGalleryItems = galleryItems;
                totalItems = filteredGalleryItems.length;
                totalPages = Math.max(1, Math.ceil(Math.max(totalItems, 1) / itemsPerPage));
            }

            function clearSelectedMedia() {
                $('.image-gallery .gallery-item').removeClass('is-selected');
                $('.image-gallery [data-media-name]').removeClass('is-selected').attr('aria-pressed', 'false');
                if (modalSelectedHelp.length) {
                    modalSelectedHelp.addClass('d-none');
                }
            }

            function mediaData($media, camelName, attrName, fallback) {
                var value = $media.data(camelName);
                if (value === undefined || value === null || value === '') {
                    value = $media.attr(attrName);
                }
                if (value === undefined || value === null || value === '') {
                    return fallback || '';
                }
                return value;
            }

        

            function showPage(page) {
                page = parseInt(page, 10) || 1;
                page = Math.max(1, Math.min(page, totalPages));

                galleryItems.hide();

                if (!filteredGalleryItems.length) {
                    currentPage = 1;
                    if (modalEmpty.length) {
                        modalEmpty.removeClass('d-none');
                    }
                    if (modalCount.length) {
                        modalCount.text('0 recursos');
                    }
                    return;
                }

                if (modalEmpty.length) {
                    modalEmpty.addClass('d-none');
                }

                filteredGalleryItems.slice((page - 1) * itemsPerPage, page * itemsPerPage).show();

                currentPage = page;

                if (modalCount.length) {
                    var first = ((currentPage - 1) * itemsPerPage) + 1;
                    var last = Math.min(currentPage * itemsPerPage, totalItems);
                    modalCount.text(first + '-' + last + ' de ' + totalItems + ' recursos');
                }

            }

        

            function setupPagination() {

                var pagination = $('#image-pagination');

                pagination.empty();

                var groupSize = 16;

                if (!filteredGalleryItems.length || totalPages <= 1) {
                    return;
                }

                for (var i = 1; i <= totalPages; i++) {

                    var li = $('<li class="page-item"><a class="page-link" href="#">' + i + '</a></li>');

                    if (i === currentPage) {

                        li.addClass('active');

                    }

                    li.on('click', function(e) {

                        e.preventDefault();

                        currentPage = parseInt($(this).text(), 10) || 1;

                        showPage(currentPage);

                        setupPagination();

                    });

                    pagination.append(li);

                    if (i % groupSize === 0 && i !== totalPages) {

                        pagination.append('<li class="page-break"></li>');

                    }

                }

            }

            function normalizeModalSearch(value) {
                var normalized = (value || '').toString().toLowerCase().trim();
                if (typeof normalized.normalize === 'function') {
                    normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                }
                return normalized;
            }

            function applyModalFilter(term) {
                refreshGalleryItems();

                var normalized = normalizeModalSearch(term);

                if (!normalized.length) {

                    filteredGalleryItems = galleryItems;

                } else {

                    filteredGalleryItems = galleryItems.filter(function() {

                        var haystack = normalizeModalSearch($(this).attr('data-media-search') || '');

                        return haystack.indexOf(normalized) !== -1;

                    });

                }

                totalItems = filteredGalleryItems.length;

                totalPages = Math.max(1, Math.ceil(Math.max(totalItems, 1) / itemsPerPage));

                showPage(1);

                setupPagination();

            }

            if (modalSearchInput.length) {

                modalSearchInput.on('input', function() {

                    applyModalFilter($(this).val());

                });

            }

            refreshGalleryItems();
            applyModalFilter('');

            var resourceSearchInput = $('#resource-search-input');

            var resourceItems = $('[data-resource-search-value]');

            if (resourceSearchInput.length && resourceItems.length) {

                resourceSearchInput.on('input', function() {

                    var normalized = ($(this).val() || '').toString().toLowerCase().trim();

                    currentResourcesSearch = ($(this).val() || '').toString();

                    resourceItems.each(function() {

                        var haystack = ($(this).attr('data-resource-search-value') || '').toString().toLowerCase();

                        var matches = !normalized.length || haystack.indexOf(normalized) !== -1;

                        $(this).closest('[class*="col-"]').toggle(matches);

                    });

                });

            }

            $(document).on('click', '[data-tag-filter]', function(e) {
                e.preventDefault();
                var tag = ($(this).data('tag-filter') || '').toString();
                var scope = ($(this).data('tag-scope') || '').toString();
                if (!tag.length) {
                    return;
                }
                if (scope === 'modal') {
                    if (modalSearchInput.length) {
                        modalSearchInput.val(tag);
                        applyModalFilter(tag);
                    }
                } else {
                    var url = 'admin.php?page=resources&search=' + encodeURIComponent(tag);
                    window.location = url;
                }
            });

            function saveResourceScroll() {
                if (!isResourcesPage) {
                    return;
                }
                try {
                    var scroll = window.pageYOffset || document.documentElement.scrollTop || 0;
                    localStorage.setItem(resourceScrollKey, JSON.stringify({ scroll: scroll, page: currentResourcesPage, search: currentResourcesSearch }));
                } catch (err) {
                    // ignore
                }
            }

            if (isResourcesPage) {
                try {
                    var storedScroll = localStorage.getItem(resourceScrollKey);
                    if (storedScroll) {
                        var parsed = JSON.parse(storedScroll);
                        var value = parsed && typeof parsed.scroll === 'number' ? parsed.scroll : 0;
                        var storedPage = parsed && typeof parsed.page === 'number' ? parsed.page : null;
                        if ((storedPage && storedPage !== currentResourcesPage) || (parsed && parsed.search !== undefined && parsed.search !== currentResourcesSearch)) {
                            // Do not restore scroll if landing on a different page or search
                            value = 0;
                        }
                        setTimeout(function() {
                            window.scrollTo(0, value);
                        }, 50);
                    }
                    localStorage.removeItem(resourceScrollKey);
                } catch (err) {
                    // ignore
                }
            }

            function showInsertActions(mediaName, mediaType, mediaSrc, mediaMime, mediaTags) {
                if (!insertActions.length) {
                    return;
                }
                pendingInsert = {
                    name: mediaName,
                    type: mediaType,
                    src: mediaSrc,
                    mime: mediaMime,
                    tags: mediaTags || ''
                };
                if (insertActionGroups.length) {
                    var groupKey = 'image';
                    if (mediaType === 'pdf') {
                        groupKey = 'pdf';
                    } else if (mediaType === 'video') {
                        groupKey = 'video';
                    }
                    insertActionGroups.addClass('d-none');
                    insertActionGroups.filter('[data-insert-group="' + groupKey + '"]').removeClass('d-none');
                }
                insertActions.removeClass('d-none');
                if (modalSelectedHelp.length) {
                    modalSelectedHelp.removeClass('d-none');
                }
            }

            $('.edit-tags-btn').on('click', function() {
                var currentTags = $(this).data('tag-list') || '';
                var target = $(this).data('tag-target') || '';
                tagsModalInput.val(currentTags);
                tagsModalTarget.val(target);
                var isModalContext = $('#imageModal').hasClass('show');
                var searchParams = new URLSearchParams(window.location.search || '');
                var currentPageParam = searchParams.get('page') || '';
                var currentFileParam = searchParams.get('file') || '';
                var queryString = (window.location.search || '').replace(/^\?/, '');
                var anchorVal = '';
                if (lastImageTrigger && typeof lastImageTrigger.getAttribute === 'function') {
                    anchorVal = lastImageTrigger.getAttribute('data-redirect-anchor') || '';
                }
                $('#tagsModalRedirectPage').val(currentPageParam);
                $('#tagsModalRedirectUrl').val(queryString);
                $('#tagsModalRedirectFile').val(currentFileParam);
                $('#tagsModalRedirectAnchor').val(anchorVal);
                $('#tagsModalReturnToModal').val(isModalContext ? '1' : '');
                $('#tagsModalTargetType').val(imageTargetMode || '');
                $('#tagsModalTargetInput').val(imageTargetInput || '');
                $('#tagsModalTargetEditor').val(imageTargetEditor || '');
                $('#tagsModalTargetPrefix').val(imageTargetPrefix || '');
                if (imageTargetSelection) {
                    $('#tagsModalSelectionStart').val(imageTargetSelection.start || '');
                    $('#tagsModalSelectionEnd').val(imageTargetSelection.end || '');
                    $('#tagsModalSelectionScroll').val(imageTargetSelection.scrollTop || '');
                } else {
                    $('#tagsModalSelectionStart').val('');
                    $('#tagsModalSelectionEnd').val('');
                    $('#tagsModalSelectionScroll').val('');
                }
                tagsModal.modal('show');
            });

            $('#tagsModalSave').on('click', function() {
                if (!tagsModalTarget.val()) {
                    tagsModal.modal('hide');
                    return;
                }
                saveResourceScroll();
                $('#tagsModalForm').trigger('submit');
            });

            $('#tagsModalForm').on('submit', function() {
                saveResourceScroll();
                if (tagsModalRedirect.length) {
                    tagsModalRedirect.val(currentResourcesPage);
                }
                var redirectSearchInput = document.getElementById('tagsModalRedirectSearch');
                if (redirectSearchInput) {
                    redirectSearchInput.value = currentResourcesSearch;
                }
            });

        

            showPage(1);

            setupPagination();

        

            $('.image-gallery').on('click', '[data-media-name]', function() {

                var $media = $(this);
                var $galleryItem = $media.closest('.gallery-item');
                var mediaName = mediaData($media, 'mediaName', 'data-media-name', '');
                var mediaType = mediaData($media, 'mediaType', 'data-media-type', 'image');
                var mediaSrc = mediaData($media, 'mediaSrc', 'data-media-src', '');
                var mediaMime = mediaData($media, 'mediaMime', 'data-media-mime', '');
                var mediaTags = mediaData($media, 'mediaTags', 'data-media-tags', '');

                if (!mediaName) {
                    return;
                }

                if (imageTargetMode === 'editor-gallery') {
                    toggleGalleryItemSelection($media);
                    return;
                }

                clearSelectedMedia();
                $media.addClass('is-selected').attr('aria-pressed', 'true');
                $galleryItem.addClass('is-selected');

                if (!imageTargetMode && resolveImageTargetTextarea()) {
                    imageTargetMode = 'editor';
                }

                if (imageTargetMode === 'field') {
                    var acceptList = (imageTargetAccept || 'image').toString().split(',').map(function(value) {
                        return value.trim().toLowerCase();
                    }).filter(Boolean);
                    if (!acceptList.length) {
                        acceptList = ['image'];
                    }
                    if (acceptList.indexOf(mediaType.toLowerCase()) === -1) {
                        var acceptLabel = acceptList.join(', ');
                        alert('Solo puedes seleccionar archivos de tipo ' + acceptLabel + ' para este campo.');
                        return;
                    }
                    var targetId = imageTargetInput || 'image';
                    var $input = $('#' + targetId);
                    if ($input.length) {
                        var prefix = imageTargetPrefix || '';
                        var nextValue = prefix + mediaName;
                        if (imageTargetMulti) {
                            var currentItems = ($input.val() || '').toString().split(/\r?\n/).map(function (value) {
                                return value.trim();
                            }).filter(Boolean);
                            if (currentItems.indexOf(nextValue) === -1) {
                                if (imageTargetMaxItems > 0 && currentItems.length >= imageTargetMaxItems) {
                                    alert('Solo puedes añadir hasta ' + imageTargetMaxItems + ' adjuntos.');
                                    return;
                                }
                                currentItems.push(nextValue);
                            }
                            $input.val(currentItems.join("\n"));
                        } else {
                            $input.val(nextValue);
                        }
                        $input.trigger('change');
                    }

                } else if (imageTargetMode === 'editor') {

                    if (mediaType === 'image' || mediaType === 'pdf' || mediaType === 'video') {
                        showInsertActions(mediaName, mediaType, mediaSrc, mediaMime, mediaTags);
                        if (insertActions.length && insertActions[0].scrollIntoView) {
                            insertActions[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        }
                        return;
                    }

                    insertMediaInContent(mediaType, mediaSrc, mediaMime, 'full');
                    $('#imageModal').modal('hide');

                }

                $('#imageModal').modal('hide');

            });

            function resolvePostTitle() {
                var titleValue = '';
                if (lastImageTrigger && typeof lastImageTrigger.closest === 'function') {
                    var form = lastImageTrigger.closest('form');
                    if (form) {
                        var titleInput = form.querySelector('[name="title"]');
                        if (titleInput && titleInput.value) {
                            titleValue = titleInput.value;
                        }
                    }
                }
                if (!titleValue) {
                    var fallback = document.querySelector('input[name="title"]');
                    if (fallback && fallback.value) {
                        titleValue = fallback.value;
                    }
                }
                return (titleValue || '').toString().trim();
            }

            function resolveImageText(tagsText) {
                var tags = (tagsText || '').toString().split(',').map(function(tag) {
                    return tag.trim();
                }).filter(Boolean);
                if (tags.length) {
                    return tags.join(', ');
                }
                return resolvePostTitle();
            }

            function escapeHtmlAttr(value) {
                return (value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function escapeMarkdownAlt(value) {
                return (value || '').replace(/]/g, '\\]');
            }

            function escapeMarkdownTitle(value) {
                return (value || '').replace(/"/g, '\\"');
            }

            function insertMediaInContent(type, source, mime, mode, tagsText) {
                if (!source) {
                    source = '';
                }
                var contentTextArea = null;
                if (imageTargetEditor) {
                    try {
                        contentTextArea = document.querySelector(imageTargetEditor);
                    } catch (selectorError) {
                        contentTextArea = null;
                    }
                }
                if (!contentTextArea && document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                    contentTextArea = document.activeElement;
                }
                if (!contentTextArea) {
                    contentTextArea = document.querySelector('[data-markdown-editor]') || document.getElementById('content');
                }
                if (!contentTextArea) {
                    return;
                }
                var snippet = '';
                if (type === 'video') {
                    if (mode === 'link') {
                        snippet = '[' + (source.split('/').pop() || 'Video') + '](' + source + ')';
                    } else {
                        var safeSource = source;
                        var sourceTag = mime ? '        <source src="' + safeSource + '" type="' + mime + '">' : '        <source src="' + safeSource + '">';
                        snippet = '\n\n<div class="embedded-video">\n    <video controls preload="metadata">\n' + sourceTag + '\n    </video>\n</div>\n\n';
                    }
                } else if (type === 'pdf') {
                    if (mode === 'link') {
                        snippet = '[' + (source.split('/').pop() || 'Documento') + '](' + source + ')';
                    } else {
                        var hasHash = source.indexOf('#') !== -1;
                        var pdfBase = source.split('#')[0];
                        var defaultParams = 'page=1&zoom=page-fit&spread=0&toolbar=0&navpanes=0&scrollbar=0&statusbar=0&pagemode=none';
                        var pdfSrc = hasHash ? source : pdfBase + '#' + defaultParams;
                        var pdfHref = pdfBase;
                        snippet = '\n\n<div class="embedded-pdf">\n    <iframe src="' + pdfSrc + '" title="Documento PDF" loading="lazy" allowfullscreen></iframe>\n    <div class="embedded-pdf__actions" aria-label="Acciones del PDF">\n        <a class="embedded-pdf__action" href="' + pdfHref + '" download>Descargar PDF</a>\n        <a class="embedded-pdf__action" href="' + pdfHref + '" target="_blank" rel="noopener">Ver a pantalla completa</a>\n    </div>\n</div>\n\n';
                    }
                } else if (type === 'audio') {
                    var audioSource = source;
                    var audioTag = mime ? '        <source src="' + audioSource + '" type="' + mime + '">' : '        <source src="' + audioSource + '">';
                    snippet = '\n\n<audio class="embedded-audio" controls preload="metadata">\n' + audioTag + '\n</audio>\n\n';
                } else if (type === 'document') {
                    snippet = '[' + (source.split('/').pop() || 'Documento') + '](' + source + ')';
                } else {
                    var imageText = resolveImageText(tagsText);
                    var altText = escapeHtmlAttr(imageText || '');
                    var titleText = escapeHtmlAttr(imageText || '');
                    if (mode === 'vignette') {
                        var altAttr = altText;
                        var titleAttr = titleText;
                        snippet = '\n\n<img src="' + source + '" alt="' + altAttr + '"' + (titleAttr ? ' title="' + titleAttr + '"' : '') + ' class="nammu-image-vignette" />\n\n';
                    } else {
                        snippet = '\n\n<img src="' + source + '" alt="' + altText + '"' + (titleText ? ' title="' + titleText + '"' : '') + ' />\n\n';
                    }
                }
                if (imageTargetSelection && imageTargetTextarea === contentTextArea) {
                    insertTextAtRange(contentTextArea, snippet, imageTargetSelection);
                    imageTargetSelection = null;
                    imageTargetTextarea = null;
                } else {
                    insertTextAtCursor(contentTextArea, snippet);
                }
            }

            function applyUploadedMediaIfNeeded() {
                if (!assetApply) {
                    return;
                }
                if (assetApply.restore_payload) {
                    try {
                        var restoreData = JSON.parse(assetApply.restore_payload);
                        restoreFormFields(restoreData);
                    } catch (err) {
                        // ignore restore errors
                    }
                }
                if (assetApply.return_to_modal) {
                    imageTargetMode = assetApply.mode || '';
                    imageTargetInput = assetApply.input || '';
                    imageTargetEditor = assetApply.editor || '';
                    imageTargetPrefix = assetApply.prefix || '';
                    if (assetApply.editor) {
                        try {
                            imageTargetTextarea = document.querySelector(assetApply.editor);
                        } catch (selectorError) {
                            imageTargetTextarea = null;
                        }
                    }
                    if (assetApply.selection) {
                        imageTargetSelection = assetApply.selection;
                    }
                    if (modalSearchInput.length && assetApply.files && assetApply.files.length) {
                        var filterName = assetApply.files[0] && assetApply.files[0].name ? assetApply.files[0].name : '';
                        if (filterName) {
                            modalSearchInput.val(filterName);
                            applyModalFilter(filterName);
                        }
                    }
                    if (assetApply.anchor) {
                        window.location.hash = assetApply.anchor.replace('#', '');
                    }
                    skipImageModalSelectionCapture = true;
                    $('#imageModal').modal('show');
                    window.nammuAssetApply = null;
                    return;
                }
                if (!assetApply.files || !assetApply.files.length) {
                    return;
                }
                var firstFile = assetApply.files[0];
                var targetValue = (assetApply.prefix || '') + (firstFile.name || '');
                var targetSrc = firstFile.src || targetValue;
                if (assetApply.mode === 'field' && targetValue) {
                    if (assetApply.input) {
                        var $fieldInput = $('#' + assetApply.input);
                        if ($fieldInput.length) {
                            $fieldInput.val(targetValue);
                        }
                    }
                } else if (assetApply.mode === 'editor' && targetSrc) {
                    insertMediaInContent('image', targetSrc, firstFile.mime || '', 'full');
                }
                if (assetApply.anchor) {
                    window.location.hash = assetApply.anchor.replace('#', '');
                }
                window.nammuAssetApply = null;
            }

            function restoreFormFields(payload) {
                if (!payload || typeof payload !== 'object' || !payload.fields) {
                    return;
                }
                var fields = payload.fields;
                Object.keys(fields).forEach(function(key) {
                    var value = fields[key];
                    var $input = $('[name="' + key + '"]');
                    if (!$input.length) {
                        return;
                    }
                    if ($input.is(':radio')) {
                        $input.filter('[value="' + value + '"]').prop('checked', true);
                    } else {
                        $input.val(value);
                    }
                });
            }

            function insertTextAtRange(textarea, text, range) {
                if (!textarea || !range) {
                    insertTextAtCursor(textarea, text);
                    return;
                }
                var start = typeof range.start === 'number' ? range.start : textarea.value.length;
                var end = typeof range.end === 'number' ? range.end : start;
                var value = textarea.value;
                textarea.value = value.substring(0, start) + text + value.substring(end);
                var cursorPosition = start + text.length;
                if (typeof setSelection === 'function') {
                    setSelection(textarea, cursorPosition, cursorPosition, range.scrollTop);
                    return;
                }
                textarea.focus();
                if (typeof textarea.setSelectionRange === 'function') {
                    textarea.setSelectionRange(cursorPosition, cursorPosition);
                }
                if (typeof range.scrollTop === 'number') {
                    textarea.scrollTop = range.scrollTop;
                }
                try {
                    var event = new Event('input', { bubbles: true });
                    textarea.dispatchEvent(event);
                } catch (evtError) {
                    var legacy = document.createEvent('Event');
                    legacy.initEvent('input', true, true);
                    textarea.dispatchEvent(legacy);
                }
            }

            function insertTextAtCursor(textarea, text) {
                if (!textarea) {
                    return;
                }
                var start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
                var end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : start;
                var value = textarea.value;
                textarea.value = value.substring(0, start) + text + value.substring(end);
                var cursorPosition = start + text.length;
                if (typeof textarea.setSelectionRange === 'function') {
                    textarea.focus();
                    textarea.setSelectionRange(cursorPosition, cursorPosition);
                }
                try {
                    var event = new Event('input', { bubbles: true });
                    textarea.dispatchEvent(event);
                } catch (evtError) {
                    var legacy = document.createEvent('Event');
                    legacy.initEvent('input', true, true);
                    textarea.dispatchEvent(legacy);
                }
            }

            insertActions.on('click', '[data-insert-mode]', function() {
                if (!pendingInsert) {
                    return;
                }
                var mode = $(this).data('insert-mode') || 'full';
                insertMediaInContent(pendingInsert.type, pendingInsert.src, pendingInsert.mime, mode, pendingInsert.tags);
                $('#imageModal').modal('hide');
                pendingInsert = null;
                insertActions.addClass('d-none');
            });

            galleryInsertBtn.on('click', function() {
                if (imageTargetMode !== 'editor-gallery' || !pendingGalleryItems.length) {
                    return;
                }
                var snippet = buildGallerySnippet(orderedGalleryItems(pendingGalleryItems));
                if (!snippet) {
                    return;
                }
                var contentTextArea = null;
                if (imageTargetEditor) {
                    try {
                        contentTextArea = document.querySelector(imageTargetEditor);
                    } catch (selectorError) {
                        contentTextArea = null;
                    }
                }
                if (!contentTextArea) {
                    contentTextArea = document.querySelector('[data-markdown-editor]') || document.getElementById('content');
                }
                if (!contentTextArea) {
                    return;
                }
                if (imageTargetSelection && imageTargetTextarea === contentTextArea) {
                    insertTextAtRange(contentTextArea, snippet, imageTargetSelection);
                    imageTargetSelection = null;
                    imageTargetTextarea = null;
                } else {
                    insertTextAtCursor(contentTextArea, snippet);
                }
                $('#imageModal').modal('hide');
            });

            galleryClearBtn.on('click', function() {
                resetGallerySelection();
            });

            galleryOrderLabels.on('click', function() {
                var order = ($(this).data('gallery-order') || 'manual').toString();
                galleryOrderInputs.filter('[value="' + order + '"]').prop('checked', true);
                galleryOrderLabels.removeClass('active');
                $(this).addClass('active');
            });

            applyUploadedMediaIfNeeded();

            $('[data-delete-tag-form]').on('submit', function() {
                saveResourceScroll();
                $(this).find('[name="redirect_p"]').val(currentResourcesPage);
                $(this).find('[name="redirect_search"]').val(currentResourcesSearch);
            });

            function resolveCalloutTarget() {
                if (calloutTarget && calloutTarget.tagName === 'TEXTAREA' && calloutTarget.id !== 'calloutBody') {
                    return calloutTarget;
                }
                var candidates = document.querySelectorAll('[data-markdown-editor], #content_edit, #content_publish, #itinerary_content, #topic_content, textarea');
                for (var i = 0; i < candidates.length; i++) {
                    if (candidates[i] && candidates[i].tagName === 'TEXTAREA' && candidates[i].id !== 'calloutBody') {
                        return candidates[i];
                    }
                }
                var fb = fallbackTextarea();
                if (fb && fb.id === 'calloutBody') {
                    return null;
                }
                return fb;
            }

            function handleCalloutInsert() {
                ensureCalloutModal();
                calloutTarget = resolveCalloutTarget();
                if (!calloutTarget || calloutTarget.id === 'calloutBody') {
                    calloutTarget = fallbackTextarea();
                }
                if (!calloutTarget || calloutTarget.id === 'calloutBody') {
                    var allTextareas = document.querySelectorAll('textarea[data-markdown-editor], textarea');
                    allTextareas.forEach(function(el) {
                        if (!calloutTarget && el.id !== 'calloutBody' && el.tagName === 'TEXTAREA') {
                            calloutTarget = el;
                        }
                    });
                }
                if (!calloutTarget) {
                    if (calloutModal.length && typeof calloutModal.modal === 'function') {
                        calloutModal.modal('hide');
                    } else if (calloutModal.length) {
                        calloutModal.removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
                    }
                    return;
                }
                var title = (calloutTitleInput.val() || 'Aviso').toString().trim();
                if (title === '') {
                    title = 'Aviso';
                }
                var bodyRaw = (calloutBodyInput.val() || '').toString();
                var lines = bodyRaw.split(/\n+/).map(function(line) { return line.trim(); }).filter(function(line) { return line !== ''; });
                if (!lines.length) {
                    lines = ['Contenido del aviso.'];
                }
                var bodyHtml = lines.map(function(line) {
                    return '  <p>' + line + '</p>';
                }).join('\n');
                var callout = '\n\n<div class="callout-box">\n  <h4>' + title + '</h4>\n' + bodyHtml + '\n</div>\n\n';
                if (calloutTarget && typeof calloutTarget.value === 'string') {
                    try {
                        replaceSelection(calloutTarget, callout, callout.length, callout.length);
                    } catch (errInsert) {
                        try {
                            insertTextAtCursor(calloutTarget, callout);
                        } catch (errFallback) {
                            // ignore
                        }
                    }
                }
                if (typeof calloutTarget.focus === 'function') {
                    calloutTarget.focus();
                }
                calloutTarget = null;
                calloutTargetSelector = '';
                if (calloutModal.length && typeof calloutModal.modal === 'function') {
                    calloutModal.modal('hide');
                } else if (calloutModal.length) {
                    calloutModal.removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
                }
            }

            $(document).on('click', '#calloutInsert', function(e) {
                e.preventDefault();
                handleCalloutInsert();
            });

        

                // --- New Custom Image Editor Logic (v2 - Fixed Selection) ---

        

                const canvas = document.getElementById('imageCanvas');

        

                const ctx = canvas.getContext('2d');

        

                const brightnessSlider = document.getElementById('brightness');

        

                const contrastSlider = document.getElementById('contrast');

        

                const saturationSlider = document.getElementById('saturation');

        

                const cropBtn = document.getElementById('cropBtn');

        

                const pixelateBtn = document.getElementById('pixelateBtn');

        

                const resetFiltersBtn = document.getElementById('resetFiltersBtn');

        

                const saveBtn = document.getElementById('save-image-btn');

        

            

        

                let originalImage = new Image();

        

                let selection = null;

        

                let isSelecting = false;

        

                

        

                originalImage.crossOrigin = "Anonymous";

        

            

        

                // --- Drawing Functions ---

        

            

        

                function draw() {

        

                    // Clear canvas

        

                    ctx.clearRect(0, 0, canvas.width, canvas.height);

        

            

        

                    // Apply filters

        

                    const brightness = brightnessSlider.value;

        

                    const contrast = contrastSlider.value;

        

                    const saturation = saturationSlider.value;

        

                    ctx.filter = `brightness(${brightness}%) contrast(${contrast}%) saturate(${saturation}%)`;

        

                    

        

                    // Draw the image

        

                    ctx.drawImage(originalImage, 0, 0, canvas.width, canvas.height);

        

            

        

                    // Draw the selection rectangle (if it exists)

        

                    if (selection) {

        

                        drawSelection();

        

                    }

        

                }

        

            

        

                function drawSelection() {

        

                    ctx.save();

        

                    ctx.filter = 'none'; // Ensure selection rect is not filtered

        

                    ctx.setLineDash([5, 5]);

        

                    ctx.strokeStyle = 'red';

        

                    const { startX, startY, endX, endY } = selection;

        

                    ctx.strokeRect(startX, startY, endX - startX, endY - startY);

        

                    ctx.restore();

        

                }

        

            

        

                function resetFilters() {

        

                    brightnessSlider.value = 100;

        

                    contrastSlider.value = 100;

        

                    saturationSlider.value = 100;

        

                    draw();

        

                }

        

            

        

                // --- Mouse Coordinate Handling ---

        

                

        

                function getCanvasMousePos(e) {

        

                    const rect = canvas.getBoundingClientRect();

        

                    const scaleX = canvas.width / rect.width;

        

                    const scaleY = canvas.height / rect.height;

        

                    return {

        

                        x: (e.clientX - rect.left) * scaleX,

        

                        y: (e.clientY - rect.top) * scaleY

        

                    };

        

                }

        

            

        

                canvas.addEventListener('mousedown', (e) => {

        

                    isSelecting = true;

        

                    const pos = getCanvasMousePos(e);

        

                    selection = {

        

                        startX: pos.x,

        

                        startY: pos.y,

        

                        endX: pos.x,

        

                        endY: pos.y

        

                    };

        

                });

        

            

        

                canvas.addEventListener('mousemove', (e) => {

        

                    if (isSelecting) {

        

                        const pos = getCanvasMousePos(e);

        

                        selection.endX = pos.x;

        

                        selection.endY = pos.y;

        

                        draw();

        

                    }

        

                });

        

            

        

                canvas.addEventListener('mouseup', (e) => {

        

                    isSelecting = false;

        

                    draw();

        

                });

        

                

        

                // --- Editor Button Functions ---

        

            

        

                cropBtn.addEventListener('click', () => {

        

                    if (!selection) {

        

                        alert("Por favor, selecciona un área primero.");

        

                        return;

        

                    }

        

                    const { startX, startY, endX, endY } = selection;

        

                    const width = Math.abs(endX - startX);

        

                    const height = Math.abs(endY - startY);

        

                    const left = Math.min(startX, endX);

        

                    const top = Math.min(startY, endY);

        

            

        

                    if (width === 0 || height === 0) {

        

                        selection = null;

        

                        draw();

        

                        return;

        

                    }

        

            

        

                    const croppedImage = new Image();

        

                    croppedImage.onload = () => {

        

                        canvas.width = width;

        

                        canvas.height = height;

        

                        originalImage = croppedImage;

        

                        selection = null;

        

                        resetFilters();

        

                    }

        

                    

        

                    const tempCanvas = document.createElement('canvas');

        

                    tempCanvas.width = canvas.width;

        

                    tempCanvas.height = canvas.height;

        

                    const tempCtx = tempCanvas.getContext('2d');

        

                    // Draw the UNFILTERED original image to the temp canvas

        

                    tempCtx.drawImage(originalImage, 0, 0, canvas.width, canvas.height);

        

                    

        

                    const tempCanvas2 = document.createElement('canvas');

        

                    tempCanvas2.width = width;

        

                    tempCanvas2.height = height;

        

                    const tempCtx2 = tempCanvas2.getContext('2d');

        

                    tempCtx2.drawImage(tempCanvas, left, top, width, height, 0, 0, width, height);

        

            

        

                    croppedImage.src = tempCanvas2.toDataURL();

        

                });

        

            

        

                pixelateBtn.addEventListener('click', () => {

        

                    if (!selection) {

        

                        alert("Por favor, selecciona un área primero.");

        

                        return;

        

                    }

        

                    const pixelSize = 10;

        

                    const { startX, startY, endX, endY } = selection;

        

                    const left = Math.min(startX, endX);

        

                    const top = Math.min(startY, endY);

        

                    const width = Math.abs(endX - startX);

        

                    const height = Math.abs(endY - startY);

        

            

        

                    // Draw on the unfiltered version

        

                    ctx.filter = 'none';

        

                    ctx.drawImage(originalImage, 0, 0, canvas.width, canvas.height);

        

            

        

                    for (let y = top; y < top + height; y += pixelSize) {

        

                        for (let x = left; x < left + width; x += pixelSize) {

        

                            const blockWidth = Math.min(pixelSize, left + width - x);

        

                            const blockHeight = Math.min(pixelSize, top + height - y);

        

                            const imageData = ctx.getImageData(x, y, blockWidth, blockHeight);

        

                            const data = imageData.data;

        

                            let r = 0, g = 0, b = 0;

        

                            let count = data.length / 4;

        

                            for (let i = 0; i < data.length; i += 4) {

        

                                r += data[i];

        

                                g += data[i+1];

        

                                b += data[i+2];

        

                            }

        

                            

        

                            ctx.fillStyle = `rgb(${Math.floor(r/count)}, ${Math.floor(g/count)}, ${Math.floor(b/count)})`;

        

                            ctx.fillRect(x, y, pixelSize, pixelSize);

        

                        }

        

                    }

        

                    originalImage.src = canvas.toDataURL(); // Update the base image

        

                    selection = null;

        

                    draw(); // Redraw with filters

        

                });

        

            

        

                // --- Load & Save ---

        

            

        

                $(document).on('click', '.edit-image-btn', function() {

        

                    var imagePath = $(this).data('image-path');

        

                    var imageName = $(this).data('image-name');

        

                    var imageRelative = $(this).data('image-relative') || '';

        

                    var imageTags = $(this).data('image-tags') || '';

        

                    $('#new-image-name').val(imageName + '-edited.png');

        

                    tagsInput.val(imageTags);

        

                    tagsTargetInput.val(imageRelative);

        

                    originalImage.onload = () => {

        

                        canvas.width = originalImage.naturalWidth;

        

                        canvas.height = originalImage.naturalHeight;

        

                        selection = null;

        

                        resetFilters();

        

                    };

        

                    originalImage.src = imagePath + '?t=' + new Date().getTime(); // Prevent caching

        

                    $('#imageEditorModal').modal('show');

        

                });

        

            

        

                saveBtn.addEventListener('click', function() {

        

                    var imageName = $('#new-image-name').val();

        

                    if (!imageName) {

        

                        alert('Por favor, introduce un nombre para el archivo.');

        

                        return;

        

                    }

        

                    

        

                    // Draw final image with filters before saving

        

                    draw();

        

            

        

                    var imageData = canvas.toDataURL('image/png');

        

            

        

                    var form = $('<form action="admin.php?page=resources" method="post"></form>');

                    if (window.NAMMU_CSRF_TOKEN) {
                        form.append($('<input type="hidden" name="_nammu_csrf">').val(window.NAMMU_CSRF_TOKEN));
                    }

        

                    form.append('<input type="hidden" name="save_edited_image" value="1">');

        

                    form.append($('<input type="hidden" name="image_name">').val(imageName));

        

                    form.append($('<input type="hidden" name="image_data">').val(imageData));

        

                    form.append($('<input type="hidden" name="image_tags">').val(tagsInput.val()));

        

                    form.append($('<input type="hidden" name="redirect_p">').val(currentResourcesPage));

        

                    form.append($('<input type="hidden" name="redirect_search">').val(currentResourcesSearch));

        

                    

        

                    $('body').append(form);

        

                    saveResourceScroll();

        

                    form.submit();

        

                });

        

                $('#save-tags-only').on('click', function() {

        

                    var target = tagsTargetInput.val();

        

                    if (!target) {

        

                        alert('Selecciona una imagen para poder guardar sus etiquetas.');

        

                        return;

        

                    }

        

                    var form = $('<form action="admin.php?page=resources" method="post"></form>');

                    if (window.NAMMU_CSRF_TOKEN) {
                        form.append($('<input type="hidden" name="_nammu_csrf">').val(window.NAMMU_CSRF_TOKEN));
                    }

        

                    form.append('<input type="hidden" name="update_image_tags" value="1">');

        

                    form.append($('<input type="hidden" name="original_image">').val(target));

        

                    form.append($('<input type="hidden" name="image_tags">').val(tagsInput.val()));

        

                    form.append($('<input type="hidden" name="redirect_p">').val(currentResourcesPage));

        

                    form.append($('<input type="hidden" name="redirect_search">').val(currentResourcesSearch));

        

                    $('body').append(form);

        

                    saveResourceScroll();

        

                    form.submit();

        

                });

        

            

        

                brightnessSlider.addEventListener('input', draw);

        

                contrastSlider.addEventListener('input', draw);

        

                saturationSlider.addEventListener('input', draw);

        

                resetFiltersBtn.addEventListener('click', resetFilters);

        });

