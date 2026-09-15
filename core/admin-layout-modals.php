<?php
/**
 * Nammu — panel de administración. Logotipo flotante y modales compartidos por las pestañas: Nisaba, Telex, Ideas, editor de
 * imágenes, recursos, etiquetas, callouts, borrado de contenidos, cuestionarios y estadísticas de itinerarios.
 */
?>
        <?php if ($adminLogoUrl !== ''): ?>
            <a class="admin-floating-logo" href="<?= htmlspecialchars($adminLogoLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" aria-label="Ir al blog">
                <img src="<?= htmlspecialchars($adminLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo del blog">
            </a>
        <?php endif; ?>
        <?php if (!empty($nisabaModalEnabled)): ?>
            <div class="modal fade" id="nisabaModal" tabindex="-1" role="dialog" aria-labelledby="nisabaModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="nisabaModalLabel">Notas recientes de Nisaba</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <?php if (!empty($nisabaNotes)): ?>
                                <p class="text-muted mb-3">Selecciona las notas de los últimos 14 días que quieres insertar.</p>
                                <?php foreach ($nisabaNotes as $index => $note): ?>
                                    <?php
                                    $noteId = 'nisaba-note-' . $index;
                                    $noteTitle = $note['title'] ?? '';
                                    $noteLink = $note['link'] ?? '';
                                    $noteContent = $note['insert_content'] ?? '';
                                    if (trim($noteContent) === '') {
                                        $noteContent = $note['content'] ?? '';
                                    }
                                    if (trim($noteContent) === '') {
                                        $noteContent = $note['display_content'] ?? '';
                                    }
                                    $noteDisplay = $note['display_content'] ?? '';
                                    $noteDateLabel = isset($note['timestamp']) ? date('d/m/y', (int) $note['timestamp']) : '';
                                    $noteDomain = '';
                                    if ($noteLink !== '') {
                                        $host = parse_url($noteLink, PHP_URL_HOST);
                                        if (is_string($host)) {
                                            $noteDomain = preg_replace('/^www\\./i', '', $host);
                                        }
                                    }
                                    ?>
                                    <div class="border rounded p-3 mb-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input nisaba-note-toggle"
                                                   id="<?= htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-nisaba-item="1"
                                                   data-note-title="<?= htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-link="<?= htmlspecialchars($noteLink, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-domain="<?= htmlspecialchars($noteDomain, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-content="<?= htmlspecialchars(base64_encode($noteContent), ENT_QUOTES, 'UTF-8') ?>">
                                            <label class="custom-control-label" for="<?= htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                        </div>
                                        <?php if ($noteDisplay !== ''): ?>
                                            <div class="mt-2 text-muted nisaba-note-preview"><?= $noteDisplay ?></div>
                                        <?php endif; ?>
                                        <?php if ($noteDateLabel !== ''): ?>
                                            <small class="text-muted d-block mt-2"><?= htmlspecialchars($noteDateLabel, ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No hay notas recientes en <strong><?= htmlspecialchars($nisabaFeedUrl, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="nisabaInsert" <?= empty($nisabaNotes) ? 'disabled' : '' ?>>Insertar notas</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($telexModalEnabled)): ?>
            <div class="modal fade" id="telexModal" tabindex="-1" role="dialog" aria-labelledby="telexModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="telexModalLabel">Notas recientes de Telex</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <?php if (!empty($telexNotes)): ?>
                                <p class="text-muted mb-3">Selecciona las notas de los últimos 14 días que quieres insertar.</p>
                                <?php foreach ($telexNotes as $index => $note): ?>
                                    <?php
                                    $noteId = 'telex-note-' . $index;
                                    $noteTitle = $note['title'] ?? '';
                                    $noteLink = $note['link'] ?? '';
                                    $noteContent = $note['insert_content'] ?? ($note['content'] ?? '');
                                    $noteDisplay = $note['display_content'] ?? '';
                                    $noteDateLabel = isset($note['timestamp']) ? date('d/m/y', (int) $note['timestamp']) : '';
                                    $noteDomain = '';
                                    if ($noteLink !== '') {
                                        $host = parse_url($noteLink, PHP_URL_HOST);
                                        if (is_string($host)) {
                                            $noteDomain = preg_replace('/^www\\./i', '', $host);
                                        }
                                    }
                                    ?>
                                    <div class="border rounded p-3 mb-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input telex-note-toggle"
                                                   id="<?= htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-telex-item="1"
                                                   data-note-title="<?= htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-link="<?= htmlspecialchars($noteLink, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-domain="<?= htmlspecialchars($noteDomain, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-content="<?= htmlspecialchars(base64_encode($noteContent), ENT_QUOTES, 'UTF-8') ?>">
                                            <label class="custom-control-label" for="<?= htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                        </div>
                                        <?php if ($noteDisplay !== ''): ?>
                                            <div class="mt-2 text-muted telex-note-preview"><?= $noteDisplay ?></div>
                                        <?php endif; ?>
                                        <?php if ($noteDateLabel !== ''): ?>
                                            <small class="text-muted d-block mt-2"><?= htmlspecialchars($noteDateLabel, ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No hay notas recientes en las feeds configuradas.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="telexInsert" <?= empty($telexNotes) ? 'disabled' : '' ?>>Insertar notas</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($ideasModalEnabled)): ?>
            <div class="modal fade" id="ideasModal" tabindex="-1" role="dialog" aria-labelledby="ideasModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="ideasModalLabel">Ideas para nuevas publicaciones</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <?php if (!empty($ideasSuggestions)): ?>
                                <ul class="mb-0">
                                    <?php foreach ($ideasSuggestions as $idea): ?>
                                        <li><?= htmlspecialchars($idea, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">Todavía no hay suficientes datos para generar sugerencias.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        

        <div class="modal fade" id="imageEditorModal" tabindex="-1" role="dialog" aria-labelledby="imageEditorModalLabel" aria-hidden="true">

            <div class="modal-dialog modal-xl" role="document">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title" id="imageEditorModalLabel">Editar Imagen</h5>

                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">

                            <span aria-hidden="true">&times;</span>

                        </button>

                    </div>

                    <div class="modal-body">

                        <div class="row">

                            <div class="col-md-9">

                                <canvas id="imageCanvas" style="max-width: 100%;"></canvas>

                            </div>

                            <div class="col-md-3">

                                <h5>Controles</h5>

                                <button id="cropBtn" class="btn btn-secondary btn-block mb-2">Recortar a la selección</button>

                                <button id="pixelateBtn" class="btn btn-secondary btn-block mb-2">Pixelar selección</button>

                                <hr>

                                <div class="form-group">

                                    <label for="brightness">Brillo</label>

                                    <input type="range" class="form-control-range" id="brightness" min="0" max="200" value="100">

                                </div>

                                <div class="form-group">

                                    <label for="contrast">Contraste</label>

                                    <input type="range" class="form-control-range" id="contrast" min="0" max="200" value="100">

                                </div>

                                <div class="form-group">

                                    <label for="saturation">Intensidad</label>

                                    <input type="range" class="form-control-range" id="saturation" min="0" max="200" value="100">

                                </div>

                                <button id="resetFiltersBtn" class="btn btn-info btn-block">Reiniciar filtros</button>

                                <hr>

                                <div class="form-group">

                                    <label for="image_tags">Etiquetas</label>

                                    <input type="text" class="form-control" id="image_tags" placeholder="Ej. portada, equipo">

                                    <small class="form-text text-muted">Escribe etiquetas separadas por comas.</small>

                                </div>

                                <button type="button" class="btn btn-outline-primary btn-block mb-2" id="save-tags-only">Guardar etiquetas</button>

                                <input type="hidden" id="image-tags-target" value="">

                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <div class="form-inline">

                            <label for="new-image-name" class="mr-2">Guardar como:</label>

                            <input type="text" id="new-image-name" class="form-control mr-2" placeholder="nuevo-nombre.png">

                            <button type="button" id="save-image-btn" class="btn btn-primary">Guardar</button>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        

        <div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel" aria-hidden="true">

            <div class="modal-dialog modal-lg" role="document">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title" id="imageModalLabel">Seleccionar recurso</h5>

                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">

                            <span aria-hidden="true">&times;</span>

                        </button>

                    </div>

                    <div class="modal-body">

                        <form action="admin.php" method="post" enctype="multipart/form-data" class="mb-3">
                            <input type="hidden" name="upload_asset" value="1">
                            <input type="hidden" name="redirect_page" value="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="redirect_p" value="<?= isset($_GET['p']) ? (int) $_GET['p'] : 1 ?>">
                            <input type="hidden" name="redirect_search" value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="redirect_file" value="<?= ($page === 'edit-post' && isset($safeEditFilename)) ? htmlspecialchars($safeEditFilename, ENT_QUOTES, 'UTF-8') : '' ?>">
                            <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="redirect_anchor" id="imageUploadRedirectAnchor" value="">
                            <input type="hidden" name="autosave_payload" id="imageUploadAutosavePayload" value="">
                            <input type="hidden" name="target_type" id="imageUploadTargetType" value="">
                            <input type="hidden" name="target_input" id="imageUploadTargetInput" value="">
                            <input type="hidden" name="target_editor" id="imageUploadTargetEditor" value="">
                            <input type="hidden" name="target_prefix" id="imageUploadTargetPrefix" value="">
                            <input type="hidden" name="target_selection_start" id="imageUploadSelectionStart" value="">
                            <input type="hidden" name="target_selection_end" id="imageUploadSelectionEnd" value="">
                            <input type="hidden" name="target_selection_scroll" id="imageUploadSelectionScroll" value="">
                            <div class="form-group mb-2">
                                <label class="d-block">Subir nuevo archivo</label>
                                <input type="file" name="asset_files[]" class="form-control-file" multiple>
                                <small class="form-text text-muted">Formatos permitidos: imágenes, audio, vídeo, documentos y Markdown.</small>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Subir</button>
                        </form>

                        <div class="form-group">
                            <label for="modal-image-search">Buscar recursos</label>
                            <input type="search" class="form-control" id="modal-image-search" placeholder="Filtra por nombre o etiqueta">
                            <small class="form-text text-muted">La galería mostrará solo los elementos que coincidan con tu búsqueda.</small>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted" id="image-modal-count"></small>
                            <small class="text-muted d-none" id="image-modal-selected-help">Recurso seleccionado. Elige abajo cómo insertarlo.</small>
                        </div>
                        <div class="alert alert-light border d-none" id="image-modal-empty" role="status">No hay recursos que coincidan con la búsqueda.</div>

                        <div class="row image-gallery">

                            <?php

                            $media_data = get_media_items(1, 0); // Load all media for modal filtering and pagination
                            $modal_media_tags = load_media_tags();

                            foreach ($media_data['items'] as $media):

                                $media_name = $media['name'];
                                $media_relative = $media['relative'];
                                $media_type = $media['type'];
                                $media_extension = $media['extension'] ?? '';
                                $media_mime = $media['mime'];
                                $media_src = 'assets/' . $media_relative;
                                $media_tags_list = $modal_media_tags[$media_relative] ?? [];
                                $media_tags_text = implode(', ', $media_tags_list);
                                $media_search = trim($media_name . ' ' . $media_relative . ' ' . $media_tags_text);

                            ?>

                                <div class="col-md-3 mb-3 gallery-item" data-media-search="<?= htmlspecialchars($media_search, ENT_QUOTES, 'UTF-8') ?>">

                                    <?php if ($media_type === 'image'): ?>
                                        <img src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover; cursor: pointer;" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="image" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" data-media-tags="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>" data-media-gallery-target="1">
                                    <?php elseif ($media_type === 'video'): ?>
                                        <div class="video-thumb-wrapper" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="video" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" data-media-tags="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>" style="cursor: pointer; position: relative;">
                                            <video class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover; pointer-events: none;" muted preload="metadata">
                                                <source src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" type="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>">
                                            </video>
                                            <span class="badge badge-dark video-badge" style="position: absolute; bottom: 8px; right: 12px;">Video</span>
                                        </div>
                                    <?php elseif ($media_type === 'audio'): ?>
                                        <div class="doc-thumb-wrapper" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="audio" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" data-media-tags="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>" style="cursor: pointer; border: 1px dashed rgba(0,0,0,0.2); border-radius: var(--nammu-radius-md, 12px); padding: 2.5rem 1rem; text-align: center;">
                                            <i class="fas fa-music" style="font-size: 3rem; color: #1e88e5;"></i>
                                            <div class="small mt-2 text-muted">Audio</div>
                                        </div>
                                    <?php else: ?>
                                        <?php $isPdf = strtolower($media_extension) === 'pdf'; ?>
                                        <div class="doc-thumb-wrapper" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="<?= $isPdf ? 'pdf' : 'document' ?>" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" data-media-tags="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>" style="cursor: pointer; border: 1px dashed rgba(0,0,0,0.2); border-radius: var(--nammu-radius-md, 12px); padding: 2.5rem 1rem; text-align: center;">
                                            <i class="fas fa-file-alt" style="font-size: 3rem; color: #5f6368;"></i>
                                            <div class="small mt-2 text-muted"><?= $isPdf ? 'PDF' : 'Documento' ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($media_tags_text !== ''): ?>
                                        <div class="mt-1">
                                            <?php foreach ($media_tags_list as $tag): ?>
                                                <a href="#" class="badge badge-primary badge-pill mr-1 mb-1" data-tag-filter="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>" data-tag-scope="modal" style="font-size: 0.7rem;">&num;<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <small class="d-block text-muted text-truncate mt-1">Sin etiquetas</small>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-info edit-tags-btn"
                                                data-tag-list="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>"
                                                data-tag-target="<?= htmlspecialchars($media_relative, ENT_QUOTES, 'UTF-8') ?>">
                                            Etiquetas
                                        </button>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div class="modal-footer">
                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between w-100">
                            <div id="image-insert-actions" class="d-none mb-3 mb-md-0">
                                <div class="d-flex flex-column align-items-start">
                                    <span class="mb-1">Insertar como:</span>
                                    <div class="btn-group mb-2" role="group" data-insert-group="image">
                                        <button type="button" class="btn btn-sm btn-primary" data-insert-mode="full">Imagen completa</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-insert-mode="vignette">Viñeta</button>
                                    </div>
                                    <div class="btn-group mb-2 d-none" role="group" data-insert-group="pdf">
                                        <button type="button" class="btn btn-sm btn-primary" data-insert-mode="embed">PDF incrustado</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-insert-mode="link">Enlace</button>
                                    </div>
                                    <div class="btn-group mb-2 d-none" role="group" data-insert-group="video">
                                        <button type="button" class="btn btn-sm btn-primary" data-insert-mode="embed">Vídeo incrustado</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-insert-mode="link">Enlace</button>
                                    </div>
                                </div>
                            </div>
                            <div id="image-gallery-actions" class="d-none mb-3 mb-md-0">
                                <div class="d-flex flex-column align-items-start">
                                    <span class="mb-1">Galería seleccionada: <strong id="image-gallery-count">0</strong></span>
                                    <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Orden de galería">
                                        <input type="radio" class="btn-check d-none" name="image_gallery_order" id="image-gallery-order-manual" value="manual" checked>
                                        <label class="btn btn-outline-secondary active" for="image-gallery-order-manual" data-gallery-order="manual">Orden manual</label>
                                        <input type="radio" class="btn-check d-none" name="image_gallery_order" id="image-gallery-order-asc" value="asc">
                                        <label class="btn btn-outline-secondary" for="image-gallery-order-asc" data-gallery-order="asc">A-Z</label>
                                        <input type="radio" class="btn-check d-none" name="image_gallery_order" id="image-gallery-order-desc" value="desc">
                                        <label class="btn btn-outline-secondary" for="image-gallery-order-desc" data-gallery-order="desc">Z-A</label>
                                    </div>
                                    <div class="btn-group" role="group" aria-label="Acciones de galería">
                                        <button type="button" class="btn btn-sm btn-primary" id="image-gallery-insert">Insertar galería</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="image-gallery-clear">Vaciar</button>
                                    </div>
                                </div>
                            </div>

                            <nav aria-label="Page navigation" class="ml-md-auto">
                                <ul class="pagination pagination-break" id="image-pagination"></ul>
                            </nav>
                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="modal fade" id="tagsModal" tabindex="-1" role="dialog" aria-labelledby="tagsModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tagsModalLabel">Editar etiquetas</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="tagsModalForm" method="post">
                            <input type="hidden" name="update_image_tags" value="1">
                            <input type="hidden" name="original_image" id="tagsModalTarget" value="">
                            <input type="hidden" name="redirect_p" id="tagsModalRedirect" value="<?= isset($current_page) ? (int) $current_page : 1 ?>">
                            <input type="hidden" name="redirect_search" id="tagsModalRedirectSearch" value="<?= htmlspecialchars($resourceSearchTerm ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="redirect_page" id="tagsModalRedirectPage" value="">
                            <input type="hidden" name="redirect_url" id="tagsModalRedirectUrl" value="">
                            <input type="hidden" name="redirect_file" id="tagsModalRedirectFile" value="">
                            <input type="hidden" name="redirect_anchor" id="tagsModalRedirectAnchor" value="">
                            <input type="hidden" name="return_to_modal" id="tagsModalReturnToModal" value="">
                            <input type="hidden" name="target_type" id="tagsModalTargetType" value="">
                            <input type="hidden" name="target_input" id="tagsModalTargetInput" value="">
                            <input type="hidden" name="target_editor" id="tagsModalTargetEditor" value="">
                            <input type="hidden" name="target_prefix" id="tagsModalTargetPrefix" value="">
                            <input type="hidden" name="target_selection_start" id="tagsModalSelectionStart" value="">
                            <input type="hidden" name="target_selection_end" id="tagsModalSelectionEnd" value="">
                            <input type="hidden" name="target_selection_scroll" id="tagsModalSelectionScroll" value="">
                            <div class="form-group">
                                <label for="tagsModalInput">Etiquetas</label>
                                <input type="text" class="form-control" name="image_tags" id="tagsModalInput" placeholder="Ej. portada, dossier, pdf">
                                <small class="form-text text-muted">Separa las etiquetas con comas.</small>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="tagsModalSave">Guardar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="calloutModal" tabindex="-1" role="dialog" aria-labelledby="calloutModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="calloutModalLabel">Caja destacada</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="calloutTitle">Título</label>
                            <input type="text" id="calloutTitle" class="form-control" value="Aviso">
                        </div>
                        <div class="form-group">
                            <label for="calloutBody">Contenido del aviso (texto o enlaces)</label>
                            <textarea id="calloutBody" class="form-control" rows="4" placeholder="Añade aquí bibliografía, enlaces o notas. Usa Enter para nueva línea."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="calloutInsert">Insertar</button>
                    </div>
                </div>
            </div>
        </div>

        

        <div class="modal fade" id="deletePostModal" tabindex="-1" role="dialog" aria-labelledby="deletePostModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deletePostModalLabel">Borrar contenido</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Vas a borrar <strong data-delete-post-title></strong>.</p>
                            <p class="text-muted small mb-3">Archivo: <span data-delete-post-file></span></p>
                            <p class="mb-0">Esta acción no se puede deshacer.</p>
                            <input type="hidden" name="delete_filename" id="delete-post-filename">
                            <input type="hidden" name="delete_template" id="delete-post-template" value="single">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="submit" name="delete_post" class="btn btn-danger">Borrar definitivamente</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="topic-quiz-modal-backdrop d-none" data-topic-quiz-backdrop></div>
        <div class="topic-quiz-modal d-none" data-topic-quiz-modal aria-hidden="true">
            <div class="topic-quiz-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="topicQuizModalTitle">
                <div class="topic-quiz-modal__header">
                    <h4 id="topicQuizModalTitle" class="mb-0">Autoevaluación del tema</h4>
                    <button type="button" class="close" aria-label="Cerrar" data-topic-quiz-close>&times;</button>
                </div>
                <div class="topic-quiz-modal__body">
                    <div class="form-group">
                        <label for="topic_quiz_minimum">Preguntas mínimas correctas para aprobar</label>
                        <input type="number" min="1" value="1" class="form-control" id="topic_quiz_minimum" data-topic-quiz-min>
                        <small class="form-text text-muted">Debe ser un número entre 1 y el total de preguntas configuradas.</small>
                    </div>
                    <div class="topic-quiz-modal__questions" data-topic-quiz-questions></div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-topic-quiz-add-question>Añadir pregunta</button>
                </div>
                <div class="topic-quiz-modal__footer">
                    <button type="button" class="btn btn-link text-danger mr-auto" data-topic-quiz-clear>Eliminar autoevaluación</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-topic-quiz-close>Cancelar</button>
                        <button type="button" class="btn btn-primary" data-topic-quiz-save>Guardar autoevaluación</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="itineraryStatsModal" tabindex="-1" role="dialog" aria-labelledby="itineraryStatsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="itineraryStatsModalLabel">Estadísticas del itinerario</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="font-weight-bold" data-stats-title></p>
                        <p data-stats-started class="mb-3 text-muted"></p>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Tema</th>
                                        <th>Usuarios</th>
                                        <th>% sobre quienes iniciaron</th>
                                    </tr>
                                </thead>
                                <tbody data-stats-table-body>
                                    <tr>
                                        <td colspan="3" class="text-muted">Cargando...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center">
                        <small class="text-muted mb-2 mb-sm-0" data-stats-note></small>
                        <form method="post" class="mb-0" data-reset-stats-form>
                            <input type="hidden" name="reset_stats_slug" value="" data-reset-stats-slug>
                            <button type="submit" name="reset_itinerary_stats" class="btn btn-sm btn-outline-danger" data-reset-stats-button>
                                Poner estadísticas a cero
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
