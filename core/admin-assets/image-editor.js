// Nammu admin — editor de imágenes (recorte y ajustes sobre canvas).
        document.addEventListener('DOMContentLoaded', function() {
            var editorModal = document.getElementById('imageEditorModal');
            var canvas = document.getElementById('imageCanvas');
            var saveBtn = document.getElementById('save-image-btn');
            var saveTagsBtn = document.getElementById('save-tags-only');
            if (!editorModal || !canvas || !saveBtn) {
                return;
            }
            var ctx = canvas.getContext ? canvas.getContext('2d') : null;
            if (!ctx) {
                return;
            }

            var brightnessSlider = document.getElementById('brightness');
            var contrastSlider = document.getElementById('contrast');
            var saturationSlider = document.getElementById('saturation');
            var cropBtn = document.getElementById('cropBtn');
            var pixelateBtn = document.getElementById('pixelateBtn');
            var resetFiltersBtn = document.getElementById('resetFiltersBtn');
            var nameInput = document.getElementById('new-image-name');
            var tagsInput = document.getElementById('image_tags');
            var tagsTargetInput = document.getElementById('image-tags-target');
            var originalImage = new Image();
            var selection = null;
            var isSelecting = false;

            originalImage.crossOrigin = 'Anonymous';

            function showModal(targetModal) {
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                    window.jQuery(targetModal).modal('show');
                    return;
                }
                targetModal.style.display = 'block';
                targetModal.classList.add('show');
                targetModal.removeAttribute('aria-hidden');
                document.body.classList.add('modal-open');
            }

            function currentQueryParam(name) {
                try {
                    return new URLSearchParams(window.location.search || '').get(name) || '';
                } catch (err) {
                    return '';
                }
            }

            function currentResourcesPage() {
                var page = parseInt(currentQueryParam('p') || '1', 10);
                return Number.isFinite(page) && page > 0 ? page : 1;
            }

            function currentResourcesSearch() {
                return currentQueryParam('search') || '';
            }

            function appendHidden(form, name, value) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value || '';
                form.appendChild(input);
            }

            function submitResourceForm(fields) {
                var form = document.createElement('form');
                form.action = 'admin.php?page=resources';
                form.method = 'post';
                if (window.NAMMU_CSRF_TOKEN) {
                    appendHidden(form, '_nammu_csrf', window.NAMMU_CSRF_TOKEN);
                }
                Object.keys(fields).forEach(function(name) {
                    appendHidden(form, name, fields[name]);
                });
                appendHidden(form, 'redirect_p', String(currentResourcesPage()));
                appendHidden(form, 'redirect_search', currentResourcesSearch());
                document.body.appendChild(form);
                form.submit();
            }

            function drawSelection() {
                if (!selection) {
                    return;
                }
                ctx.save();
                ctx.filter = 'none';
                ctx.setLineDash([5, 5]);
                ctx.strokeStyle = 'red';
                ctx.strokeRect(selection.startX, selection.startY, selection.endX - selection.startX, selection.endY - selection.startY);
                ctx.restore();
            }

            function draw() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                var brightness = brightnessSlider ? brightnessSlider.value : '100';
                var contrast = contrastSlider ? contrastSlider.value : '100';
                var saturation = saturationSlider ? saturationSlider.value : '100';
                ctx.filter = 'brightness(' + brightness + '%) contrast(' + contrast + '%) saturate(' + saturation + '%)';
                if (originalImage.complete && originalImage.naturalWidth > 0) {
                    ctx.drawImage(originalImage, 0, 0, canvas.width, canvas.height);
                }
                drawSelection();
            }

            function resetFilters() {
                if (brightnessSlider) brightnessSlider.value = 100;
                if (contrastSlider) contrastSlider.value = 100;
                if (saturationSlider) saturationSlider.value = 100;
                draw();
            }

            function canvasPoint(event) {
                var rect = canvas.getBoundingClientRect();
                var scaleX = rect.width > 0 ? canvas.width / rect.width : 1;
                var scaleY = rect.height > 0 ? canvas.height / rect.height : 1;
                return {
                    x: (event.clientX - rect.left) * scaleX,
                    y: (event.clientY - rect.top) * scaleY
                };
            }

            function openEditor(button) {
                var imagePath = button.getAttribute('data-image-path') || '';
                var imageName = button.getAttribute('data-image-name') || 'imagen';
                var imageRelative = button.getAttribute('data-image-relative') || '';
                var imageTags = button.getAttribute('data-image-tags') || '';
                if (nameInput) {
                    nameInput.value = imageName + '-edited.png';
                }
                if (tagsInput) {
                    tagsInput.value = imageTags;
                }
                if (tagsTargetInput) {
                    tagsTargetInput.value = imageRelative;
                }
                originalImage.onload = function() {
                    canvas.width = originalImage.naturalWidth || 1;
                    canvas.height = originalImage.naturalHeight || 1;
                    selection = null;
                    resetFilters();
                };
                originalImage.onerror = function() {
                    alert('No se pudo cargar la imagen para editarla.');
                };
                originalImage.src = imagePath + (imagePath.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
                showModal(editorModal);
            }

            document.addEventListener('click', function(event) {
                var button = event.target.closest ? event.target.closest('.edit-image-btn') : null;
                if (!button) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }
                openEditor(button);
            }, true);

            canvas.addEventListener('mousedown', function(event) {
                isSelecting = true;
                var point = canvasPoint(event);
                selection = { startX: point.x, startY: point.y, endX: point.x, endY: point.y };
            });
            canvas.addEventListener('mousemove', function(event) {
                if (!isSelecting || !selection) {
                    return;
                }
                var point = canvasPoint(event);
                selection.endX = point.x;
                selection.endY = point.y;
                draw();
            });
            canvas.addEventListener('mouseup', function() {
                isSelecting = false;
                draw();
            });

            if (cropBtn) {
                cropBtn.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (!selection) {
                        alert('Por favor, selecciona un área primero.');
                        return;
                    }
                    var left = Math.min(selection.startX, selection.endX);
                    var top = Math.min(selection.startY, selection.endY);
                    var width = Math.abs(selection.endX - selection.startX);
                    var height = Math.abs(selection.endY - selection.startY);
                    if (width <= 0 || height <= 0) {
                        selection = null;
                        draw();
                        return;
                    }
                    var temp = document.createElement('canvas');
                    temp.width = width;
                    temp.height = height;
                    var tempCtx = temp.getContext('2d');
                    tempCtx.drawImage(originalImage, left, top, width, height, 0, 0, width, height);
                    var cropped = new Image();
                    cropped.onload = function() {
                        originalImage = cropped;
                        canvas.width = width;
                        canvas.height = height;
                        selection = null;
                        resetFilters();
                    };
                    cropped.src = temp.toDataURL('image/png');
                }, true);
            }

            if (pixelateBtn) {
                pixelateBtn.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (!selection) {
                        alert('Por favor, selecciona un área primero.');
                        return;
                    }
                    var pixelSize = 10;
                    var left = Math.min(selection.startX, selection.endX);
                    var top = Math.min(selection.startY, selection.endY);
                    var width = Math.abs(selection.endX - selection.startX);
                    var height = Math.abs(selection.endY - selection.startY);
                    ctx.filter = 'none';
                    ctx.drawImage(originalImage, 0, 0, canvas.width, canvas.height);
                    for (var y = top; y < top + height; y += pixelSize) {
                        for (var x = left; x < left + width; x += pixelSize) {
                            var blockWidth = Math.min(pixelSize, left + width - x);
                            var blockHeight = Math.min(pixelSize, top + height - y);
                            var imageData = ctx.getImageData(x, y, blockWidth, blockHeight);
                            var data = imageData.data;
                            var red = 0;
                            var green = 0;
                            var blue = 0;
                            var count = data.length / 4;
                            for (var i = 0; i < data.length; i += 4) {
                                red += data[i];
                                green += data[i + 1];
                                blue += data[i + 2];
                            }
                            ctx.fillStyle = 'rgb(' + Math.floor(red / count) + ', ' + Math.floor(green / count) + ', ' + Math.floor(blue / count) + ')';
                            ctx.fillRect(x, y, blockWidth, blockHeight);
                        }
                    }
                    var pixelated = new Image();
                    pixelated.onload = function() {
                        originalImage = pixelated;
                        selection = null;
                        draw();
                    };
                    pixelated.src = canvas.toDataURL('image/png');
                }, true);
            }

            [brightnessSlider, contrastSlider, saturationSlider].forEach(function(input) {
                if (input) {
                    input.addEventListener('input', draw);
                }
            });
            if (resetFiltersBtn) {
                resetFiltersBtn.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    resetFilters();
                }, true);
            }

            saveBtn.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }
                var imageName = nameInput ? nameInput.value.trim() : '';
                if (!imageName) {
                    alert('Por favor, introduce un nombre para el archivo.');
                    return;
                }
                draw();
                submitResourceForm({
                    save_edited_image: '1',
                    image_name: imageName,
                    image_data: canvas.toDataURL('image/png'),
                    image_tags: tagsInput ? tagsInput.value : ''
                });
            }, true);

            if (saveTagsBtn) {
                saveTagsBtn.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') {
                        event.stopImmediatePropagation();
                    }
                    var target = tagsTargetInput ? tagsTargetInput.value.trim() : '';
                    if (!target) {
                        alert('Selecciona una imagen para poder guardar sus etiquetas.');
                        return;
                    }
                    submitResourceForm({
                        update_image_tags: '1',
                        original_image: target,
                        image_tags: tagsInput ? tagsInput.value : ''
                    });
                }, true);
            }
        });
