<?php // Sección de pestaña: Publicar ?>
<div class="tab-pane active">

    <h2>Publicar</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
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
    ?>

    <form method="post">

        <div class="form-group">

            <label for="title" data-podcast-label="Título del episodio" data-post-label="Título">Título</label>

            <input type="text" name="title" id="title" class="form-control" required>

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
            <label for="audio_length">Longitud del archivo (bytes)</label>
            <input type="text" name="audio_length" id="audio_length" class="form-control" placeholder="Se calcula automáticamente si es posible">
        </div>

        <div class="form-group podcast-only d-none">
            <label for="audio_duration">Duración (hh:mm:ss)</label>
            <input type="text" name="audio_duration" id="audio_duration" class="form-control" placeholder="00:45:00">
        </div>

        <div class="form-group">

            <label for="type">Tipo</label>

            <select name="type" id="type" class="form-control">

                <option value="Entrada">Entrada</option>

                <option value="Página">Página</option>

                <option value="Podcast">Podcast</option>

            </select>

        </div>

        <div class="form-group post-only">

            <label for="category">Categoría</label>

            <input type="text" name="category" id="category" class="form-control">

        </div>

        <div class="form-group">

            <label for="date">Fecha</label>

            <input type="date" name="date" id="date" class="form-control" value="<?= date('Y-m-d') ?>" required>

        </div>

        <div class="form-group">
            <label for="lang">Lengua de la entrada</label>
            <select name="lang" id="lang" class="form-control">
                <?php foreach ($languageOptions as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $siteLang === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
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

        <div class="form-group">

            <label for="image" data-podcast-label="Imagen asociada al episodio" data-post-label="Imagen">Imagen</label>

            <div class="input-group">

                <input type="text" name="image" id="image" class="form-control" readonly>

                <div class="input-group-append">

                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="image" data-target-prefix="">Seleccionar imagen</button>

                </div>

            </div>

        </div>

        <div class="form-group">

            <label for="description" data-podcast-label="Descripción" data-post-label="Entradilla">Entradilla</label>

            <textarea name="description" id="description" class="form-control" rows="3"></textarea>

        </div>

        <div class="form-group post-only">

            <label for="content_publish">Contenido (Markdown)</label>
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
                    <button type="button" class="btn btn-outline-secondary" data-md-action="callout" data-toggle="modal" data-target="#calloutModal" title="Caja destacada" aria-label="Caja destacada">Aviso</button>
                </div>
            </div>

            <textarea name="content" id="content_publish" class="form-control" rows="15" data-markdown-editor="1"></textarea>

            <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#content_publish">Insertar recurso</button>
            <small class="form-text text-muted mt-1">Inserta en la entrada imágenes, vídeos o archivos PDF.</small>

        </div>

        <div class="form-group">

            <label for="filename">Slug del post (nombre de archivo sin .md)</label>

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
            <button type="submit" name="publish" class="btn btn-primary mr-2" data-confirm-publish="1">Publicar</button>
            <button type="submit" name="save_draft" value="1" class="btn btn-outline-secondary" data-confirm-publish="1">Guardar como borrador</button>
            <?php if ($mailingNewsletterEnabled): ?>
                <button type="submit" name="send_newsletter" value="1" class="btn btn-warning ml-2" data-confirm-publish="1">Enviar como newsletter</button>
            <?php endif; ?>
        </div>

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var typeSelect = document.getElementById('type');
    if (!typeSelect) {
        return;
    }
    var podcastOnly = document.querySelectorAll('.podcast-only');
    var postOnly = document.querySelectorAll('.post-only');
    var titleLabel = document.querySelector('label[for="title"]');
    var descriptionLabel = document.querySelector('label[for="description"]');
    var imageLabel = document.querySelector('label[for="image"]');
    var audioInput = document.getElementById('audio');
    var durationInput = document.getElementById('audio_duration');

    function togglePodcastFields() {
        var isPodcast = typeSelect.value === 'Podcast';
        podcastOnly.forEach(function(el) {
            el.classList.toggle('d-none', !isPodcast);
        });
        postOnly.forEach(function(el) {
            el.classList.toggle('d-none', isPodcast);
        });
        if (titleLabel && titleLabel.dataset.podcastLabel && titleLabel.dataset.postLabel) {
            titleLabel.textContent = isPodcast ? titleLabel.dataset.podcastLabel : titleLabel.dataset.postLabel;
        }
        if (descriptionLabel && descriptionLabel.dataset.podcastLabel && descriptionLabel.dataset.postLabel) {
            descriptionLabel.textContent = isPodcast ? descriptionLabel.dataset.podcastLabel : descriptionLabel.dataset.postLabel;
        }
        if (imageLabel && imageLabel.dataset.podcastLabel && imageLabel.dataset.postLabel) {
            imageLabel.textContent = isPodcast ? imageLabel.dataset.podcastLabel : imageLabel.dataset.postLabel;
        }
        if (audioInput) {
            audioInput.required = isPodcast;
        }
        if (durationInput) {
            durationInput.required = isPodcast;
        }
    }

    typeSelect.addEventListener('change', togglePodcastFields);
    togglePodcastFields();
});
</script>
