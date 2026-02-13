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

        <div class="form-group">
            <label>Tipo</label>
            <input type="hidden" name="type" id="type" value="Entrada" data-type-value>
            <div class="btn-group d-flex flex-wrap" role="group" data-type-toggle>
                <button type="button" class="btn btn-primary active" data-type-option="Entrada" aria-pressed="true">Entrada</button>
                <button type="button" class="btn btn-outline-primary" data-type-option="Página" aria-pressed="false">Página</button>
                <button type="button" class="btn btn-outline-primary" data-type-option="Newsletter" aria-pressed="false">Newsletter</button>
                <button type="button" class="btn btn-outline-primary" data-type-option="Podcast" aria-pressed="false">Podcast</button>
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

        <div class="form-group entry-podcast-only">
            <label for="related_slugs">Entradas o itinerarios relacionados (2 a 6 slugs)</label>
            <textarea name="related_slugs" id="related_slugs" class="form-control" rows="3" placeholder="slug-entrada-1&#10;slug-entrada-2&#10;itinerarios/mi-itinerario"></textarea>
            <small class="form-text text-muted">Solo para entradas y podcasts. Escribe un slug por línea (o separados por coma).</small>
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

            <textarea name="content" id="content_publish" class="form-control" rows="15" data-markdown-editor="1"></textarea>

            <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#content_publish">Insertar recurso</button>
            <small class="form-text text-muted mt-1">Inserta en la entrada imágenes, vídeos o archivos PDF.</small>

        </div>

        <div class="form-group non-podcast">

            <label for="filename" data-podcast-label="Slug del episodio (opcional)" data-post-label="Slug del post (nombre de archivo sin .md)">Slug del post (nombre de archivo sin .md)</label>

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
            <button type="submit" name="publish" class="btn btn-primary mr-2" data-confirm-publish="1" data-podcast-label="Emitir" data-post-label="Publicar" data-publish-button="1">Publicar</button>
            <button type="submit" name="save_draft" value="1" class="btn btn-outline-secondary" data-confirm-publish="1">Guardar como borrador</button>
            <?php if ($mailingNewsletterEnabled): ?>
                <button type="submit" name="send_newsletter" value="1" class="btn btn-primary mr-2 d-none" data-confirm-publish="1" data-newsletter-button="1">Enviar</button>
            <?php endif; ?>
        </div>

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var typeToggle = document.querySelector('[data-type-toggle]');
    var typeValueInput = document.querySelector('[data-type-value]');
    if (!typeToggle || !typeValueInput) {
        return;
    }
    var podcastOnly = document.querySelectorAll('.podcast-only');
    var nonPodcast = document.querySelectorAll('.non-podcast');
    var entryOnly = document.querySelectorAll('.entry-only');
    var pageOnly = document.querySelectorAll('.page-only');
    var entryPodcastOnly = document.querySelectorAll('.entry-podcast-only');
    var titleLabel = document.querySelector('label[for="title"]');
    var descriptionLabel = document.querySelector('label[for="description"]');
    var imageLabel = document.querySelector('label[for="image"]');
    var slugLabel = document.querySelector('label[for="filename"]');
    var publishButton = document.querySelector('[data-publish-button]');
    var audioInput = document.getElementById('audio');
    var durationInput = document.getElementById('audio_duration');
    var lengthInput = document.getElementById('audio_length');
    var newsletterButton = document.querySelector('[data-newsletter-button="1"]');

    function formatDuration(seconds) {
        if (!Number.isFinite(seconds) || seconds <= 0) {
            return '';
        }
        var total = Math.floor(seconds);
        var hours = Math.floor(total / 3600);
        var minutes = Math.floor((total % 3600) / 60);
        var secs = total % 60;
        return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }

    function trimLeadingSlashes(value) {
        var output = value || '';
        while (output.charAt(0) === '/' || output.charAt(0) === '\\') {
            output = output.slice(1);
        }
        return output;
    }

    function resolveAudioUrl(value) {
        if (!value) {
            return '';
        }
        if (value.indexOf('http://') === 0 || value.indexOf('https://') === 0) {
            return value;
        }
        if (value.indexOf('assets/') === 0) {
            return '/' + trimLeadingSlashes(value);
        }
        return '/assets/' + trimLeadingSlashes(value);
    }

    function updateAudioMetadata() {
        if (!audioInput || !audioInput.value) {
            return;
        }
        var url = resolveAudioUrl(audioInput.value.trim());
        if (!url) {
            return;
        }
        if (lengthInput && !lengthInput.value) {
            fetch(url, { method: 'HEAD' })
                .then(function(response) {
                    var length = response.headers.get('content-length');
                    if (length && lengthInput && !lengthInput.value) {
                        lengthInput.value = length;
                    }
                })
                .catch(function() {});
        }
        if (durationInput && !durationInput.value) {
            var audioProbe = new Audio();
            audioProbe.preload = 'metadata';
            audioProbe.addEventListener('loadedmetadata', function() {
                var formatted = formatDuration(audioProbe.duration);
                if (formatted && durationInput && !durationInput.value) {
                    durationInput.value = formatted;
                }
                audioProbe.src = '';
            });
            audioProbe.addEventListener('error', function() {
                audioProbe.src = '';
            });
            audioProbe.src = url;
        }
    }

    function togglePodcastFields() {
        var typeValue = typeValueInput.value || 'Entrada';
        var isPodcast = typeValue === 'Podcast';
        var isEntry = typeValue === 'Entrada';
        var isPage = typeValue === 'Página';
        var isNewsletter = typeValue === 'Newsletter';
        podcastOnly.forEach(function(el) {
            el.classList.toggle('d-none', !isPodcast);
        });
        nonPodcast.forEach(function(el) {
            el.classList.toggle('d-none', isPodcast);
        });
        entryOnly.forEach(function(el) {
            el.classList.toggle('d-none', !isEntry);
        });
        pageOnly.forEach(function(el) {
            el.classList.toggle('d-none', !isPage);
        });
        entryPodcastOnly.forEach(function(el) {
            el.classList.toggle('d-none', !(isEntry || isPodcast));
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
        if (slugLabel && slugLabel.dataset.podcastLabel && slugLabel.dataset.postLabel) {
            slugLabel.textContent = isPodcast ? slugLabel.dataset.podcastLabel : slugLabel.dataset.postLabel;
        }
        if (publishButton && publishButton.dataset.podcastLabel && publishButton.dataset.postLabel) {
            publishButton.textContent = isPodcast ? publishButton.dataset.podcastLabel : publishButton.dataset.postLabel;
        }
        if (publishButton) {
            publishButton.classList.toggle('d-none', isNewsletter);
        }
        if (newsletterButton) {
            newsletterButton.classList.toggle('d-none', !isNewsletter);
        }
        if (audioInput) {
            audioInput.required = isPodcast;
        }
        if (durationInput) {
            durationInput.required = isPodcast;
        }
    }

    var typeButtons = Array.prototype.slice.call(typeToggle.querySelectorAll('[data-type-option]'));

    function setTypeFromButton(button) {
        var value = button.getAttribute('data-type-option') || 'Entrada';
        typeValueInput.value = value;
        typeButtons.forEach(function(other) {
            var isActive = other === button;
            other.classList.toggle('active', isActive);
            other.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            if (isActive) {
                other.classList.add('btn-primary');
                other.classList.remove('btn-outline-primary');
            } else {
                other.classList.remove('btn-primary');
                other.classList.add('btn-outline-primary');
            }
        });
        togglePodcastFields();
    }

    typeToggle.addEventListener('click', function(event) {
        var target = event.target;
        if (target && target.closest) {
            var button = target.closest('[data-type-option]');
            if (button) {
                event.preventDefault();
                setTypeFromButton(button);
            }
        }
    });

    if (typeButtons.length) {
        var activeButton = typeButtons.find(function(button) {
            return button.classList.contains('active');
        }) || typeButtons[0];
        setTypeFromButton(activeButton);
    }
    if (audioInput) {
        audioInput.addEventListener('change', updateAudioMetadata);
    }
    togglePodcastFields();
    updateAudioMetadata();
});
</script>
