<?php // Sección de pestaña: Publicar ?>
<div class="tab-pane active">

    <h2>Publicar</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php
    $mailingNewsletterEnabled = isset($settings['mailing'])
        && (($settings['mailing']['auto_newsletter'] ?? (($settings['mailing']['gmail_address'] ?? '') !== '' ? 'on' : 'off')) === 'on');
    ?>

    <form method="post">

        <div class="form-group">

            <label for="title">Título</label>

            <input type="text" name="title" id="title" class="form-control" required>

        </div>

        <div class="form-group">

            <label for="type">Tipo</label>

            <select name="type" id="type" class="form-control">

                <option value="Entrada">Entrada</option>

                <option value="Página">Página</option>

            </select>

        </div>

        <div class="form-group">

            <label for="category">Categoría</label>

            <input type="text" name="category" id="category" class="form-control">

        </div>

        <div class="form-group">

            <label for="date">Fecha</label>

            <input type="date" name="date" id="date" class="form-control" value="<?= date('Y-m-d') ?>" required>

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

            <label for="image">Imagen</label>

            <div class="input-group">

                <input type="text" name="image" id="image" class="form-control" readonly>

                <div class="input-group-append">

                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="image" data-target-prefix="">Seleccionar imagen</button>

                </div>

            </div>

        </div>

        <div class="form-group">

            <label for="description">Entradilla</label>

            <textarea name="description" id="description" class="form-control" rows="3"></textarea>

        </div>

        <div class="form-group">

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
