<?php // Sección de pestaña: Publicar ?>
<div class="tab-pane active">

    <h2>Publicar</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($socialBroadcastFeedback)): ?>
        <div class="alert alert-<?= htmlspecialchars($socialBroadcastFeedback['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($socialBroadcastFeedback['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php
    $mailingNewsletterEnabled = isset($settings['mailing'])
        && (($settings['mailing']['auto_newsletter'] ?? (($settings['mailing']['gmail_address'] ?? '') !== '' ? 'on' : 'off')) === 'on');
    $siteLang = $settings['site_lang'] ?? 'es';
    $languageOptions = [
        'es' => 'Español',
        'ca' => 'Català',
        'eu' => 'Euskera',
        'gl' => 'Galego',
        'en' => 'English',
        'fr' => 'Français',
        'it' => 'Italiano',
        'pt' => 'Português',
        'de' => 'Deutsch',
    ];
    $baseUrl = function_exists('nammu_base_url') ? rtrim((string) nammu_base_url(), '/') : '';
    $fediverseProfileUrl = function_exists('nammu_fediverse_profile_page_url') ? nammu_fediverse_profile_page_url($settings) : ($baseUrl . '/actualidad.php');
    $newsFeedUrl = ($baseUrl !== '' ? $baseUrl : '') . '/noticias.xml';
    $fediverseIcon = function_exists('nammu_footer_icon_svgs') ? (string) (nammu_footer_icon_svgs()['fediverse'] ?? '') : '';
    $socialBroadcastMaxImages = function_exists('admin_social_broadcast_max_images') ? (int) admin_social_broadcast_max_images() : 4;
    ?>

    <form method="post">

        <div class="form-group title-group">

            <label for="title" data-podcast-label="Título del episodio" data-post-label="Título">Título</label>

            <input type="text" name="title" id="title" class="form-control" required>

        </div>

        <div class="form-group">
            <label>Tipo</label>
            <input type="hidden" name="type" id="type" value="Entrada" data-type-value>
            <div class="btn-group d-flex flex-wrap" role="group" data-type-toggle>
                <button type="button" class="btn btn-primary active" data-type-option="Entrada" aria-pressed="true">Entrada</button>
                <button type="button" class="btn btn-outline-primary" data-type-option="Página" aria-pressed="false">Página</button>
                <button type="button" class="btn btn-outline-primary" data-type-option="Newsletter" aria-pressed="false">Newsletter</button>
                <button type="button" class="btn btn-outline-primary" data-type-option="Podcast" aria-pressed="false">Podcast</button>
                <button type="button" class="btn btn-outline-primary" data-type-option="Nota" aria-pressed="false">Nota</button>
            </div>
        </div>

        <div class="form-group podcast-only d-none">
            <label for="audio">Archivo de audio (mp3)</label>
            <div class="input-group">
                <input type="text" name="audio" id="audio" class="form-control" readonly>
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="audio" data-target-prefix="" data-target-accept="audio">Seleccionar audio</button>
                </div>
            </div>
            <small class="form-text text-muted">Selecciona un archivo mp3 desde Recursos.</small>
        </div>

        <div class="form-group podcast-only d-none">
            <label for="video">Vídeo del episodio (mp4, opcional)</label>
            <div class="input-group">
                <input type="text" name="video" id="video" class="form-control" readonly>
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="video" data-target-prefix="" data-target-accept="video">Seleccionar vídeo</button>
                </div>
            </div>
            <small class="form-text text-muted">Opcional. Selecciona un archivo mp4 desde Recursos.</small>
        </div>

        <div class="form-group podcast-only d-none">
            <label for="audio_length">Longitud del archivo (bytes)</label>
            <input type="text" name="audio_length" id="audio_length" class="form-control" placeholder="Se calcula automáticamente si es posible">
        </div>

        <div class="form-group podcast-only d-none">
            <label for="audio_duration">Duración (hh:mm:ss)</label>
            <input type="text" name="audio_duration" id="audio_duration" class="form-control" placeholder="00:45:00">
        </div>

        <div class="form-group entry-only">

            <label for="category">Categoría</label>

            <input type="text" name="category" id="category" class="form-control">

        </div>

        <div class="form-group page-only d-none">
            <label for="page_visibility">Visibilidad de la página</label>
            <select name="page_visibility" id="page_visibility" class="form-control">
                <option value="public" selected>Pública</option>
                <option value="private">Privada</option>
            </select>
        </div>

        <div class="form-group date-group">

            <label for="date">Fecha</label>

            <input type="date" name="date" id="date" class="form-control" value="<?= date('Y-m-d') ?>" required>

        </div>

        <div class="form-group lang-group">
            <label for="lang">Lengua de la entrada</label>
            <select name="lang" id="lang" class="form-control">
                <?php foreach ($languageOptions as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $siteLang === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group schedule-group">
            <label>Programar publicación (borradores)</label>
            <div class="form-row">
                <div class="col-md-6">
                    <input type="date" name="publish_at_date" class="form-control" placeholder="Fecha">
                </div>
                <div class="col-md-6">
                    <input type="time" name="publish_at_time" class="form-control" placeholder="Hora">
                </div>
            </div>
            <small class="form-text text-muted">Si guardas como borrador, se publicará automáticamente en esa fecha y hora.</small>
        </div>

        <div class="form-group image-group">

            <label for="image" data-podcast-label="Imagen asociada al episodio" data-post-label="Imagen">Imagen</label>

            <div class="input-group">

                <input type="text" name="image" id="image" class="form-control" readonly>

                <div class="input-group-append">

                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="image" data-target-prefix="">Seleccionar imagen</button>

                </div>

            </div>

        </div>

        <div class="form-group message-images-group d-none">
            <label for="social_broadcast_image">Adjuntos opcionales</label>
            <div class="input-group">
                <textarea name="social_broadcast_image" id="social_broadcast_image" class="form-control" rows="4" readonly><?= htmlspecialchars($socialBroadcastImage ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="social_broadcast_image" data-target-prefix="" data-target-multi="1" data-target-max-items="<?= $socialBroadcastMaxImages ?>" data-target-accept="image,video">Añadir adjunto</button>
                </div>
            </div>
            <small class="form-text text-muted">Puedes añadir hasta <?= $socialBroadcastMaxImages ?> adjuntos, una ruta por línea. Las imágenes podrán salir también en redes; los vídeos se conservarán para el Fediverso y la página de perfil.</small>
        </div>

        <div class="form-group description-group">

            <label for="description" data-podcast-label="Descripción" data-post-label="Entradilla" data-newsletter-label="Descripción">Entradilla</label>

            <textarea name="description" id="description" class="form-control" rows="3"></textarea>

        </div>

        <div class="form-group entry-podcast-only">
            <label for="related_slugs">Entradas o itinerarios relacionados (2 a 8 slugs)</label>
            <textarea name="related_slugs" id="related_slugs" class="form-control" rows="3" placeholder="slug-entrada-1&#10;podcast/mi-episodio&#10;itinerarios/mi-itinerario"></textarea>
            <small class="form-text text-muted">Solo para entradas y podcasts. Escribe un slug por línea (o separados por coma).</small>
        </div>

        <div class="form-group non-podcast">

            <label for="content_publish" data-message-label="Nota" data-default-label="Contenido (Markdown)">Contenido (Markdown)</label>
            <p class="text-muted small mb-3 message-help d-none">Este mensaje se enviará al <strong>Fediverso</strong> <span class="align-middle d-inline-block social-fediverse-inline-icon"><?= $fediverseIcon ?></span> y aparecerá en <a href="<?= htmlspecialchars($newsFeedUrl, ENT_QUOTES, 'UTF-8') ?>"><code>noticias.xml</code></a> y en <a href="<?= htmlspecialchars($fediverseProfileUrl, ENT_QUOTES, 'UTF-8') ?>">tu página de perfil del Fediverso</a> como una nota tipo post-it.</p>
            <style>
<?php admin_inline_asset('publish.css'); ?>
            </style>
            <div class="btn-toolbar markdown-toolbar mb-2 flex-wrap" role="toolbar" aria-label="Atajos de Markdown" data-markdown-toolbar data-target="#content_publish">
                <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                    <button type="button" class="btn btn-outline-secondary" data-md-action="bold" title="Negrita" aria-label="Negrita"><strong>B</strong></button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="italic" title="Cursiva" aria-label="Cursiva"><em>I</em></button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="strike" title="Tachado" aria-label="Tachado">S̶</button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="code" title="Código en línea" aria-label="Código en línea">&lt;/&gt;</button>
                </div>
                <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                    <button type="button" class="btn btn-outline-secondary" data-md-action="link" title="Enlace" aria-label="Enlace">Link</button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="quote" title="Cita" aria-label="Cita">&gt;</button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="sup" title="Superíndice" aria-label="Superíndice">x<sup>2</sup></button>
                </div>
                <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                    <button type="button" class="btn btn-outline-secondary" data-md-action="ul" title="Lista" aria-label="Lista">•</button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="ol" title="Lista numerada" aria-label="Lista numerada">1.</button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="heading" title="Encabezado" aria-label="Encabezado">H2</button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="code-block" title="Bloque de código" aria-label="Bloque de código">{ }</button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="hr" title="Separador" aria-label="Separador">—</button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="table" title="Tabla" aria-label="Tabla">Tbl</button>
                    <button type="button" class="btn btn-outline-secondary" data-md-action="callout" title="Caja destacada" aria-label="Caja destacada">Aviso</button>
                    <?php if (!empty($nisabaEnabled)): ?>
                        <button type="button" class="btn btn-outline-secondary" data-md-action="nisaba" title="Nisaba" aria-label="Nisaba">
                            <img src="nisaba.png" alt="" class="nisaba-icon">
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($telexEnabled)): ?>
                        <button type="button" class="btn btn-outline-secondary" data-md-action="telex" title="Telex" aria-label="Telex">
                            <img src="telex.png" alt="" class="telex-icon">
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($ideasEnabled)): ?>
                        <button type="button" class="btn btn-outline-secondary" data-md-action="ideas" title="Ideas" aria-label="Ideas">Ideas</button>
                    <?php endif; ?>
                </div>
            </div>

            <textarea name="content" id="content_publish" class="form-control" rows="15" data-markdown-editor="1"><?= htmlspecialchars($socialBroadcastText ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

            <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                <button type="button" class="btn btn-secondary mr-2 mb-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#content_publish">Insertar recurso</button>
                <button type="button" class="btn btn-outline-secondary mb-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor-gallery" data-target-editor="#content_publish">Añadir galería</button>
            </div>
            <small class="form-text text-muted mt-1">Inserta en la entrada imágenes, vídeos o archivos PDF, o una galería de imágenes desde Recursos.</small>

        </div>

        <div class="form-group slug-group">

            <label for="filename" data-podcast-label="Slug" data-post-label="Slug del post (nombre de archivo sin .md)">Slug del post (nombre de archivo sin .md)</label>

            <input type="text"
                   name="filename"
                   id="filename"
                   class="form-control"
                   pattern="[a-z0-9-]+"
                   inputmode="text"
                   autocomplete="off"
                   autocapitalize="none"
                   spellcheck="false"
                   data-slug-input="1"
                   placeholder="mi-slug">
            <small class="form-text text-muted">Si lo dejas vacío se generará automáticamente a partir del título.</small>

        </div>

        <div class="mt-3">
            <div class="alert alert-warning d-none" data-publish-cancelled>Los cambios no se han guardado.</div>
            <button type="submit" name="publish" class="btn btn-primary mr-2" data-confirm-publish="1" data-podcast-label="Emitir" data-post-label="Publicar" data-message-label="Enviar" data-publish-button="1">Publicar</button>
            <button type="submit" name="save_draft" value="1" class="btn btn-outline-secondary" data-confirm-publish="1" data-draft-button="1">Guardar como borrador</button>
            <button type="submit" name="publish_and_view" value="1" class="btn btn-outline-primary ml-2" data-confirm-publish="1" data-view-button="1">Ver en la web</button>
            <?php if ($mailingNewsletterEnabled): ?>
                <button type="submit" name="send_newsletter" value="1" class="btn btn-primary mr-2 d-none" data-confirm-publish="1" data-newsletter-button="1">Enviar</button>
            <?php endif; ?>
        </div>

    </form>

</div>

<script>
<?php admin_inline_asset('publish.js'); ?>
</script>
