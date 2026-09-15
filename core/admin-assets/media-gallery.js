// Nammu admin — paginación, búsqueda y etiquetas dentro del modal de recursos.
        document.addEventListener('DOMContentLoaded', function() {
            window.setTimeout(function() {
                var modal = document.getElementById('imageModal');
                var gallery = modal ? modal.querySelector('.image-gallery') : null;
                var pagination = document.getElementById('image-pagination');
                var count = document.getElementById('image-modal-count');
                var search = document.getElementById('modal-image-search');
                var insertActions = document.getElementById('image-insert-actions');
                if (!modal || !gallery || !pagination || !search || !insertActions) {
                    return;
                }
                var allItems = Array.prototype.slice.call(gallery.querySelectorAll('.gallery-item'));
                if (!allItems.length) {
                    return;
                }
                window.nammuMediaModalInitialized = true;
                if (window.jQuery) {
                    window.jQuery(modal).off('show.bs.modal hidden.bs.modal');
                    window.jQuery(search).off('input');
                    window.jQuery(gallery).off('click');
                    window.jQuery(insertActions).off('click');
                    window.jQuery('#image-gallery-insert, #image-gallery-clear, [data-gallery-order], .edit-tags-btn, #tagsModalSave, #tagsModalForm').off('click submit');
                }

                var empty = document.getElementById('image-modal-empty');
                var selectedHelp = document.getElementById('image-modal-selected-help');
                var galleryActions = document.getElementById('image-gallery-actions');
                var galleryCount = document.getElementById('image-gallery-count');
                var galleryInsert = document.getElementById('image-gallery-insert');
                var galleryClear = document.getElementById('image-gallery-clear');
                var galleryOrderInputs = Array.prototype.slice.call(document.querySelectorAll('[name="image_gallery_order"]'));
                var galleryOrderLabels = Array.prototype.slice.call(document.querySelectorAll('[data-gallery-order]'));
                var perPage = 8;
                var currentPage = 1;
                var filteredItems = allItems.slice();
                var targetMode = '';
                var targetInput = '';
                var targetEditor = '';
                var targetPrefix = '';
                var targetAccept = '';
                var targetMulti = '';
                var targetMaxItems = 0;
                var targetSelection = null;
                var pendingInsert = null;
                var pendingGalleryItems = [];

                function normalize(value) {
                    var normalized = (value || '').toString().toLowerCase().trim();
                    if (typeof normalized.normalize === 'function') {
                        normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    }
                    return normalized;
                }

                function closestMedia(node) {
                    while (node && node !== gallery) {
                        if (node.getAttribute && node.getAttribute('data-media-name') !== null) {
                            return node;
                        }
                        node = node.parentNode;
                    }
                    return null;
                }

                function mediaAttr(media, name) {
                    return media ? (media.getAttribute(name) || '') : '';
                }

                function targetTextarea() {
                    if (targetEditor) {
                        try {
                            var explicit = document.querySelector(targetEditor);
                            if (explicit && explicit.tagName === 'TEXTAREA') {
                                return explicit;
                            }
                        } catch (err) {
                            // Ignore invalid selectors coming from old buttons.
                        }
                    }
                    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                        return document.activeElement;
                    }
                    return document.querySelector('[data-markdown-editor]') || document.querySelector('textarea');
                }

                function hideModal() {
                    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                        window.jQuery(modal).modal('hide');
                        return;
                    }
                    hideElementModal(modal);
                }

                function hideElementModal(targetModal) {
                    if (!targetModal) {
                        return;
                    }
                    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                        window.jQuery(targetModal).modal('hide');
                        return;
                    }
                    targetModal.classList.remove('show');
                    targetModal.style.display = 'none';
                    targetModal.setAttribute('aria-hidden', 'true');
                    if (!document.querySelector('.modal.show')) {
                        document.body.classList.remove('modal-open');
                    }
                }

                function ensureModalOpen(targetModal) {
                    if (!targetModal) {
                        return;
                    }
                    if (targetModal.classList.contains('show')) {
                        document.body.classList.add('modal-open');
                        return;
                    }
                    showModal(targetModal);
                }

                function showModalFeedback(message, type) {
                    var body = modal.querySelector('.modal-body');
                    if (!body) {
                        return;
                    }
                    var alert = modal.querySelector('[data-modal-asset-feedback]');
                    if (!alert) {
                        alert = document.createElement('div');
                        alert.setAttribute('data-modal-asset-feedback', '1');
                        body.insertBefore(alert, body.firstChild);
                    }
                    alert.className = 'alert alert-' + (type || 'info') + ' mb-3';
                    alert.textContent = message || '';
                }

                function assetSearchText(asset) {
                    var tags = Array.isArray(asset.tags) ? asset.tags.join(' ') : '';
                    return [asset.name || '', asset.relative || '', tags].join(' ').trim();
                }

                function assetTagsText(asset) {
                    return Array.isArray(asset.tags) ? asset.tags.join(', ') : '';
                }

                function buildAssetNode(asset) {
                    if (!asset || !asset.name || !asset.relative) {
                        return null;
                    }
                    var item = document.createElement('div');
                    item.className = 'col-md-3 mb-3 gallery-item';
                    item.setAttribute('data-media-search', assetSearchText(asset));

                    var type = (asset.type || 'image').toString();
                    var src = asset.src || ('assets/' + asset.relative);
                    var tagsText = assetTagsText(asset);
                    var main;
                    if (type === 'image') {
                        main = document.createElement('img');
                        main.src = src;
                        main.className = 'img-thumbnail';
                        main.style.width = '100%';
                        main.style.height = '150px';
                        main.style.objectFit = 'cover';
                        main.style.cursor = 'pointer';
                        main.setAttribute('data-media-gallery-target', '1');
                    } else {
                        main = document.createElement('div');
                        main.className = type === 'video' ? 'video-thumb-wrapper' : 'doc-thumb-wrapper';
                        main.style.cursor = 'pointer';
                        main.style.border = '1px dashed rgba(0,0,0,0.2)';
                        main.style.borderRadius = 'var(--nammu-radius-md, 12px)';
                        main.style.padding = '2.5rem 1rem';
                        main.style.textAlign = 'center';
                        main.textContent = type === 'video' ? 'Video' : (type === 'audio' ? 'Audio' : 'Documento');
                    }
                    main.setAttribute('data-media-name', asset.name || '');
                    main.setAttribute('data-media-type', type);
                    main.setAttribute('data-media-src', src);
                    main.setAttribute('data-media-mime', asset.mime || '');
                    main.setAttribute('data-media-tags', tagsText);
                    item.appendChild(main);

                    var tagsBlock = document.createElement('div');
                    tagsBlock.className = 'mt-1';
                    tagsBlock.setAttribute('data-modal-tags-list', '1');
                    renderAssetTags(tagsBlock, asset.tags || []);
                    item.appendChild(tagsBlock);

                    var actions = document.createElement('div');
                    actions.className = 'mt-2';
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-sm btn-outline-info edit-tags-btn';
                    button.setAttribute('data-tag-list', tagsText);
                    button.setAttribute('data-tag-target', asset.relative || '');
                    button.textContent = 'Etiquetas';
                    actions.appendChild(button);
                    item.appendChild(actions);
                    return item;
                }

                function renderAssetTags(container, tags) {
                    container.innerHTML = '';
                    tags = Array.isArray(tags) ? tags : [];
                    if (!tags.length) {
                        var emptyTags = document.createElement('small');
                        emptyTags.className = 'd-block text-muted text-truncate mt-1';
                        emptyTags.textContent = 'Sin etiquetas';
                        container.appendChild(emptyTags);
                        return;
                    }
                    tags.forEach(function(tag) {
                        var link = document.createElement('a');
                        link.href = '#';
                        link.className = 'badge badge-primary badge-pill mr-1 mb-1';
                        link.setAttribute('data-tag-filter', tag);
                        link.setAttribute('data-tag-scope', 'modal');
                        link.style.fontSize = '0.7rem';
                        link.textContent = '#' + tag;
                        container.appendChild(link);
                    });
                }

                function syncAssetNode(asset) {
                    if (!asset || !asset.relative) {
                        return null;
                    }
                    var selector = '.edit-tags-btn[data-tag-target="' + cssEscape(asset.relative) + '"]';
                    var button = gallery.querySelector(selector);
                    var item = button ? button.closest('.gallery-item') : null;
                    if (!item) {
                        item = buildAssetNode(asset);
                        if (!item) {
                            return null;
                        }
                        gallery.insertBefore(item, gallery.firstChild);
                    }
                    item.setAttribute('data-media-search', assetSearchText(asset));
                    var tagsText = assetTagsText(asset);
                    var media = item.querySelector('[data-media-name]');
                    if (media) {
                        media.setAttribute('data-media-tags', tagsText);
                    }
                    var tagButton = item.querySelector('.edit-tags-btn');
                    if (tagButton) {
                        tagButton.setAttribute('data-tag-list', tagsText);
                        tagButton.setAttribute('data-tag-target', asset.relative);
                    }
                    var tagsBlock = item.querySelector('[data-modal-tags-list]');
                    if (tagsBlock) {
                        renderAssetTags(tagsBlock, asset.tags || []);
                    }
                    allItems = Array.prototype.slice.call(gallery.querySelectorAll('.gallery-item'));
                    return item;
                }

                function cssEscape(value) {
                    if (window.CSS && typeof window.CSS.escape === 'function') {
                        return window.CSS.escape(value);
                    }
                    return (value || '').toString().replace(/["\\]/g, '\\$&');
                }

                function submitModalForm(form) {
                    var data = new FormData(form);
                    data.set('nammu_ajax', '1');
                    return fetch(form.getAttribute('action') || 'admin.php', {
                        method: 'POST',
                        body: data,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    }).then(function(response) {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.json();
                    });
                }

                function updateUploadHiddenFields() {
                    writeHiddenValue('imageUploadTargetType', targetMode);
                    writeHiddenValue('imageUploadTargetInput', targetInput);
                    writeHiddenValue('imageUploadTargetEditor', targetEditor);
                    writeHiddenValue('imageUploadTargetPrefix', targetPrefix);
                    writeHiddenValue('imageUploadSelectionStart', targetSelection ? String(targetSelection.start || 0) : '');
                    writeHiddenValue('imageUploadSelectionEnd', targetSelection ? String(targetSelection.end || 0) : '');
                    writeHiddenValue('imageUploadSelectionScroll', targetSelection ? String(targetSelection.scrollTop || 0) : '');
                }

                function applyUploadResponse(payload, form) {
                    var feedback = payload && payload.feedback ? payload.feedback : null;
                    if (feedback && feedback.message) {
                        showModalFeedback(feedback.message, feedback.type || (payload.ok ? 'success' : 'warning'));
                    }
                    var assets = payload && Array.isArray(payload.assets) ? payload.assets : [];
                    assets.forEach(syncAssetNode);
                    if (assets.length) {
                        search.value = '';
                        currentPage = 1;
                        form.reset();
                    }
                    applyFilter();
                }

                function applyTagsResponse(payload, tagsModal) {
                    var feedback = payload && payload.feedback ? payload.feedback : null;
                    if (feedback && feedback.message) {
                        showModalFeedback(feedback.message, feedback.type || (payload.ok ? 'success' : 'warning'));
                    }
                    if (payload && payload.asset) {
                        syncAssetNode(payload.asset);
                    }
                    hideElementModal(tagsModal);
                    ensureModalOpen(modal);
                    window.setTimeout(function() {
                        ensureModalOpen(modal);
                    }, 250);
                    applyFilter();
                }

                function showModal(targetModal) {
                    if (!targetModal) {
                        return;
                    }
                    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                        window.jQuery(targetModal).modal('show');
                        return;
                    }
                    targetModal.style.display = 'block';
                    targetModal.classList.add('show');
                    targetModal.removeAttribute('aria-hidden');
                    document.body.classList.add('modal-open');
                }

                function clearSelected() {
                    allItems.forEach(function(item) {
                        item.classList.remove('is-selected');
                        var media = item.querySelector('[data-media-name]');
                        if (media) {
                            media.classList.remove('is-selected');
                            media.setAttribute('aria-pressed', 'false');
                        }
                    });
                    if (selectedHelp) {
                        selectedHelp.classList.add('d-none');
                    }
                }

                function resetGallerySelection() {
                    pendingGalleryItems = [];
                    allItems.forEach(function(item) {
                        var media = item.querySelector('[data-media-gallery-target]');
                        if (media) {
                            media.classList.remove('border-primary', 'shadow');
                            media.setAttribute('aria-pressed', 'false');
                        }
                    });
                    galleryOrderInputs.forEach(function(input) {
                        input.checked = input.value === 'manual';
                    });
                    galleryOrderLabels.forEach(function(label) {
                        label.classList.toggle('active', (label.getAttribute('data-gallery-order') || '') === 'manual');
                    });
                    if (galleryCount) {
                        galleryCount.textContent = '0';
                    }
                    if (galleryActions) {
                        galleryActions.classList.add('d-none');
                    }
                }

                function syncGallerySelectionUi() {
                    if (galleryCount) {
                        galleryCount.textContent = String(pendingGalleryItems.length);
                    }
                    if (galleryActions) {
                        galleryActions.classList.toggle('d-none', !(targetMode === 'editor-gallery' && pendingGalleryItems.length > 0));
                    }
                }

                function toggleGalleryItem(media) {
                    if (!media) {
                        return;
                    }
                    var mediaType = (mediaAttr(media, 'data-media-type') || '').toLowerCase();
                    if (mediaType !== 'image') {
                        alert('La galería solo admite imágenes guardadas en Recursos.');
                        return;
                    }
                    var src = mediaAttr(media, 'data-media-src');
                    if (!src) {
                        return;
                    }
                    var existing = pendingGalleryItems.findIndex(function(item) {
                        return item.src === src;
                    });
                    if (existing >= 0) {
                        pendingGalleryItems.splice(existing, 1);
                        media.classList.remove('border-primary', 'shadow');
                        media.setAttribute('aria-pressed', 'false');
                    } else {
                        pendingGalleryItems.push({
                            src: src,
                            name: mediaAttr(media, 'data-media-name'),
                            tags: mediaAttr(media, 'data-media-tags')
                        });
                        media.classList.add('border-primary', 'shadow');
                        media.setAttribute('aria-pressed', 'true');
                    }
                    syncGallerySelectionUi();
                }

                function render() {
                    var total = filteredItems.length;
                    var totalPages = Math.max(1, Math.ceil(Math.max(total, 1) / perPage));
                    currentPage = Math.max(1, Math.min(currentPage, totalPages));
                    allItems.forEach(function(item) {
                        item.style.display = 'none';
                    });
                    if (!total) {
                        if (empty) {
                            empty.classList.remove('d-none');
                        }
                        if (count) {
                            count.textContent = '0 recursos';
                        }
                        pagination.innerHTML = '';
                        return;
                    }
                    if (empty) {
                        empty.classList.add('d-none');
                    }
                    var start = (currentPage - 1) * perPage;
                    var end = Math.min(start + perPage, total);
                    filteredItems.slice(start, end).forEach(function(item) {
                        item.style.display = '';
                    });
                    if (count) {
                        count.textContent = (start + 1) + '-' + end + ' de ' + total + ' recursos';
                    }
                    pagination.innerHTML = '';
                    if (totalPages <= 1) {
                        return;
                    }
                    for (var i = 1; i <= totalPages; i++) {
                        var li = document.createElement('li');
                        li.className = 'page-item' + (i === currentPage ? ' active' : '');
                        var link = document.createElement('a');
                        link.className = 'page-link';
                        link.href = '#';
                        link.textContent = String(i);
                        link.setAttribute('data-page', String(i));
                        li.appendChild(link);
                        pagination.appendChild(li);
                    }
                }

                function applyFilter() {
                    var term = normalize(search.value || '');
                    filteredItems = allItems.filter(function(item) {
                        return !term || normalize(item.getAttribute('data-media-search') || '').indexOf(term) !== -1;
                    });
                    currentPage = 1;
                    render();
                }

                function captureTarget(button) {
                    targetMode = button.getAttribute('data-target-type') || '';
                    targetInput = button.getAttribute('data-target-input') || '';
                    targetEditor = button.getAttribute('data-target-editor') || '';
                    targetPrefix = button.getAttribute('data-target-prefix') || '';
                    targetAccept = button.getAttribute('data-target-accept') || '';
                    targetMulti = button.getAttribute('data-target-multi') || '';
                    targetMaxItems = parseInt(button.getAttribute('data-target-max-items') || '0', 10) || 0;
                    writeHiddenValue('imageUploadRedirectAnchor', button.getAttribute('data-redirect-anchor') || '');
                    var textarea = targetTextarea();
                    if (textarea && typeof textarea.selectionStart === 'number') {
                        targetSelection = {
                            textarea: textarea,
                            start: textarea.selectionStart,
                            end: typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : textarea.selectionStart,
                            scrollTop: textarea.scrollTop
                        };
                    } else {
                        targetSelection = null;
                    }
                    clearSelected();
                    resetGallerySelection();
                    insertActions.classList.add('d-none');
                    applyFilter();
                }

                function writeHiddenValue(id, value) {
                    var input = document.getElementById(id);
                    if (input) {
                        input.value = value || '';
                    }
                }

                function currentQueryParam(name) {
                    try {
                        return new URLSearchParams(window.location.search || '').get(name) || '';
                    } catch (err) {
                        return '';
                    }
                }

                function openTagsModal(button) {
                    var tagsModal = document.getElementById('tagsModal');
                    var form = document.getElementById('tagsModalForm');
                    if (!tagsModal || !form || !button) {
                        return;
                    }
                    var input = document.getElementById('tagsModalInput');
                    var target = document.getElementById('tagsModalTarget');
                    if (input) {
                        input.value = button.getAttribute('data-tag-list') || '';
                    }
                    if (target) {
                        target.value = button.getAttribute('data-tag-target') || '';
                    }
                    var fields = {
                        tagsModalRedirectPage: currentQueryParam('page'),
                        tagsModalRedirectUrl: (window.location.search || '').replace(/^\?/, ''),
                        tagsModalRedirectFile: currentQueryParam('file'),
                        tagsModalRedirectAnchor: '',
                        tagsModalReturnToModal: modal.classList.contains('show') ? '1' : '',
                        tagsModalTargetType: targetMode || '',
                        tagsModalTargetInput: targetInput || '',
                        tagsModalTargetEditor: targetEditor || '',
                        tagsModalTargetPrefix: targetPrefix || '',
                        tagsModalSelectionStart: targetSelection ? String(targetSelection.start || 0) : '',
                        tagsModalSelectionEnd: targetSelection ? String(targetSelection.end || 0) : '',
                        tagsModalSelectionScroll: targetSelection ? String(targetSelection.scrollTop || 0) : ''
                    };
                    Object.keys(fields).forEach(function(id) {
                        var field = document.getElementById(id);
                        if (field) {
                            field.value = fields[id];
                        }
                    });
                    showModal(tagsModal);
                    if (input) {
                        input.focus();
                    }
                }

                function setFieldValue(media) {
                    var input = targetInput ? document.getElementById(targetInput) : null;
                    if (!input) {
                        return;
                    }
                    var nextValue = targetPrefix + mediaAttr(media, 'data-media-name');
                    if (targetMulti) {
                        var values = (input.value || '').split(/\r?\n/).map(function(value) {
                            return value.trim();
                        }).filter(Boolean);
                        if (values.indexOf(nextValue) === -1) {
                            if (targetMaxItems > 0 && values.length >= targetMaxItems) {
                                alert('Solo puedes añadir hasta ' + targetMaxItems + ' adjuntos.');
                                return;
                            }
                            values.push(nextValue);
                        }
                        input.value = values.join("\n");
                    } else {
                        input.value = nextValue;
                    }
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    hideModal();
                }

                function escapeAttr(value) {
                    return (value || '').toString()
                        .replace(/&/g, '&amp;')
                        .replace(/"/g, '&quot;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');
                }

                function insertText(textarea, text) {
                    if (!textarea) {
                        return;
                    }
                    var start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
                    var end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : start;
                    if (targetSelection && targetSelection.textarea === textarea) {
                        start = targetSelection.start;
                        end = targetSelection.end;
                    }
                    textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
                    var cursor = start + text.length;
                    textarea.focus();
                    if (typeof textarea.setSelectionRange === 'function') {
                        textarea.setSelectionRange(cursor, cursor);
                    }
                    if (targetSelection && typeof targetSelection.scrollTop === 'number') {
                        textarea.scrollTop = targetSelection.scrollTop;
                    }
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                }

                function resolvePostTitle() {
                    var title = '';
                    var titleInput = document.querySelector('input[name="title"]');
                    if (titleInput && titleInput.value) {
                        title = titleInput.value;
                    }
                    return title.trim();
                }

                function galleryLabel(item) {
                    return (item && (item.name || item.src) ? (item.name || item.src) : '').toString().toLocaleLowerCase();
                }

                function selectedGalleryOrder() {
                    var checked = galleryOrderInputs.find(function(input) {
                        return input.checked;
                    });
                    return checked ? checked.value : 'manual';
                }

                function orderedGalleryItems() {
                    var order = selectedGalleryOrder();
                    var items = pendingGalleryItems.slice();
                    if (order === 'asc' || order === 'desc') {
                        items.sort(function(a, b) {
                            var result = galleryLabel(a).localeCompare(galleryLabel(b), undefined, { numeric: true, sensitivity: 'base' });
                            return order === 'desc' ? -result : result;
                        });
                    }
                    return items;
                }

                function buildGallerySnippet() {
                    var items = orderedGalleryItems();
                    if (!items.length) {
                        return '';
                    }
                    var blocks = items.map(function(item) {
                        var label = escapeAttr((item.tags || item.name || resolvePostTitle() || '').toString());
                        var src = escapeAttr(item.src || '');
                        return '        <a class="nammu-inline-gallery__link" href="' + src + '" target="_blank" rel="noopener">\n'
                            + '            <img src="' + src + '" alt="' + label + '"' + (label ? ' title="' + label + '"' : '') + ' class="nammu-inline-gallery__image" />\n'
                            + '        </a>';
                    });
                    return '\n\n<details class="nammu-inline-gallery">\n'
                        + '    <summary>Galería</summary>\n'
                        + '    <div class="nammu-inline-gallery__grid">\n'
                        + blocks.join('\n')
                        + '\n    </div>\n'
                        + '</details>\n\n';
                }

                function insertMedia(mode) {
                    if (!pendingInsert) {
                        return;
                    }
                    var textarea = targetTextarea();
                    if (!textarea) {
                        return;
                    }
                    var type = pendingInsert.type;
                    var source = pendingInsert.src;
                    var mime = pendingInsert.mime;
                    var snippet = '';
                    if (type === 'video') {
                        if (mode === 'link') {
                            snippet = '[' + (source.split('/').pop() || 'Video') + '](' + source + ')';
                        } else {
                            snippet = '\n\n<div class="embedded-video">\n    <video controls preload="metadata">\n        <source src="' + source + '"' + (mime ? ' type="' + mime + '"' : '') + '>\n    </video>\n</div>\n\n';
                        }
                    } else if (type === 'pdf') {
                        if (mode === 'link') {
                            snippet = '[' + (source.split('/').pop() || 'Documento') + '](' + source + ')';
                        } else {
                            snippet = '\n\n<div class="embedded-pdf">\n    <iframe src="' + source + '#page=1&zoom=page-fit&spread=0&toolbar=0&navpanes=0&scrollbar=0&statusbar=0&pagemode=none" title="Documento PDF" loading="lazy" allowfullscreen></iframe>\n    <div class="embedded-pdf__actions" aria-label="Acciones del PDF">\n        <a class="embedded-pdf__action" href="' + source + '" download>Descargar PDF</a>\n        <a class="embedded-pdf__action" href="' + source + '" target="_blank" rel="noopener">Ver a pantalla completa</a>\n    </div>\n</div>\n\n';
                        }
                    } else if (type === 'audio') {
                        snippet = '\n\n<audio class="embedded-audio" controls preload="metadata">\n        <source src="' + source + '"' + (mime ? ' type="' + mime + '"' : '') + '>\n</audio>\n\n';
                    } else if (type === 'document') {
                        snippet = '[' + (source.split('/').pop() || 'Documento') + '](' + source + ')';
                    } else {
                        var label = escapeAttr(pendingInsert.tags || pendingInsert.name || '');
                        var classAttr = mode === 'vignette' ? ' class="nammu-image-vignette"' : '';
                        snippet = '\n\n<img src="' + source + '" alt="' + label + '"' + (label ? ' title="' + label + '"' : '') + classAttr + ' />\n\n';
                    }
                    insertText(textarea, snippet);
                    pendingInsert = null;
                    targetSelection = null;
                    hideModal();
                }

                document.addEventListener('click', function(event) {
                    var trigger = event.target.closest ? event.target.closest('[data-toggle="modal"][data-target="#imageModal"]') : null;
                    if (trigger) {
                        captureTarget(trigger);
                    }
                }, true);

                document.addEventListener('click', function(event) {
                    var tagsButton = event.target.closest ? event.target.closest('.edit-tags-btn') : null;
                    if (!tagsButton) {
                        return;
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') {
                        event.stopImmediatePropagation();
                    }
                    openTagsModal(tagsButton);
                }, true);

                gallery.addEventListener('click', function(event) {
                    var media = closestMedia(event.target);
                    if (!media) {
                        return;
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') {
                        event.stopImmediatePropagation();
                    }
                    if (!targetMode && targetTextarea()) {
                        targetMode = 'editor';
                    }
                    if (targetMode === 'uploader') {
                        return;
                    }
                    var type = mediaAttr(media, 'data-media-type') || 'image';
                    if (targetMode === 'editor-gallery') {
                        toggleGalleryItem(media);
                        return;
                    }
                    if (targetMode === 'field') {
                        var allowed = (targetAccept || 'image').split(',').map(function(value) {
                            return value.trim().toLowerCase();
                        }).filter(Boolean);
                        if (allowed.indexOf(type.toLowerCase()) === -1) {
                            alert('Solo puedes seleccionar archivos de tipo ' + allowed.join(', ') + ' para este campo.');
                            return;
                        }
                        setFieldValue(media);
                        return;
                    }
                    clearSelected();
                    media.classList.add('is-selected');
                    media.setAttribute('aria-pressed', 'true');
                    var item = media.closest ? media.closest('.gallery-item') : null;
                    if (item) {
                        item.classList.add('is-selected');
                    }
                    pendingInsert = {
                        name: mediaAttr(media, 'data-media-name'),
                        type: type,
                        src: mediaAttr(media, 'data-media-src'),
                        mime: mediaAttr(media, 'data-media-mime'),
                        tags: mediaAttr(media, 'data-media-tags')
                    };
                    Array.prototype.forEach.call(insertActions.querySelectorAll('[data-insert-group]'), function(group) {
                        var groupName = group.getAttribute('data-insert-group') || '';
                        var wanted = type === 'pdf' ? 'pdf' : (type === 'video' ? 'video' : 'image');
                        group.classList.toggle('d-none', groupName !== wanted);
                    });
                    insertActions.classList.remove('d-none');
                    if (selectedHelp) {
                        selectedHelp.classList.remove('d-none');
                    }
                }, true);

                insertActions.addEventListener('click', function(event) {
                    var button = event.target.closest ? event.target.closest('[data-insert-mode]') : null;
                    if (!button) {
                        return;
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') {
                        event.stopImmediatePropagation();
                    }
                    insertMedia(button.getAttribute('data-insert-mode') || 'full');
                }, true);

                if (galleryInsert) {
                    galleryInsert.addEventListener('click', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (typeof event.stopImmediatePropagation === 'function') {
                            event.stopImmediatePropagation();
                        }
                        if (targetMode !== 'editor-gallery' || !pendingGalleryItems.length) {
                            return;
                        }
                        var snippet = buildGallerySnippet();
                        if (!snippet) {
                            return;
                        }
                        insertText(targetTextarea(), snippet);
                        targetSelection = null;
                        resetGallerySelection();
                        hideModal();
                    }, true);
                }

                if (galleryClear) {
                    galleryClear.addEventListener('click', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (typeof event.stopImmediatePropagation === 'function') {
                            event.stopImmediatePropagation();
                        }
                        resetGallerySelection();
                    }, true);
                }

                galleryOrderLabels.forEach(function(label) {
                    label.addEventListener('click', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (typeof event.stopImmediatePropagation === 'function') {
                            event.stopImmediatePropagation();
                        }
                        var order = label.getAttribute('data-gallery-order') || 'manual';
                        galleryOrderInputs.forEach(function(input) {
                            input.checked = input.value === order;
                        });
                        galleryOrderLabels.forEach(function(option) {
                            option.classList.toggle('active', option === label);
                        });
                    }, true);
                });

                var tagsSave = document.getElementById('tagsModalSave');
                var tagsForm = document.getElementById('tagsModalForm');
                if (tagsSave && tagsForm) {
                    tagsSave.addEventListener('click', function(event) {
                        var target = document.getElementById('tagsModalTarget');
                        if (!target || !target.value) {
                            hideElementModal(document.getElementById('tagsModal'));
                            return;
                        }
                        event.preventDefault();
                        event.stopPropagation();
                        tagsSave.disabled = true;
                        submitModalForm(tagsForm)
                            .then(function(payload) {
                                applyTagsResponse(payload, document.getElementById('tagsModal'));
                            })
                            .catch(function(error) {
                                showModalFeedback('No se pudieron guardar las etiquetas: ' + error.message, 'danger');
                            })
                            .finally(function() {
                                tagsSave.disabled = false;
                            });
                    });
                    tagsForm.addEventListener('submit', function(event) {
                        event.preventDefault();
                        tagsSave.click();
                    });
                }

                search.addEventListener('input', applyFilter);
                pagination.addEventListener('click', function(event) {
                    var link = event.target.closest ? event.target.closest('[data-page]') : null;
                    if (!link) {
                        return;
                    }
                    event.preventDefault();
                    currentPage = parseInt(link.getAttribute('data-page') || '1', 10) || 1;
                    render();
                });
                var uploadForm = modal.querySelector('form[enctype="multipart/form-data"]');
                if (uploadForm) {
                    uploadForm.addEventListener('submit', function(event) {
                        updateUploadHiddenFields();
                        if ((currentQueryParam('page') || '') === 'resources') {
                            return;
                        }
                        event.preventDefault();
                        var submitButton = uploadForm.querySelector('[type="submit"]');
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                        submitModalForm(uploadForm)
                            .then(function(payload) {
                                applyUploadResponse(payload, uploadForm);
                            })
                            .catch(function(error) {
                                showModalFeedback('No se pudo subir el recurso: ' + error.message, 'danger');
                            })
                            .finally(function() {
                                if (submitButton) {
                                    submitButton.disabled = false;
                                }
                            });
                    });
                }
                applyFilter();
            }, 100);
        });
