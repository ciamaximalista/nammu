<?php if ($page === 'itinerario-tema'): ?>

    <div class="tab-pane active">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div>
                <h2 class="mb-1"><?= $topicFormData['mode'] === 'existing' ? 'Editar tema' : 'Nuevo tema' ?></h2>
                <?php if ($selectedItinerary): ?>
                    <p class="text-muted mb-0">Itinerario: <?= htmlspecialchars($selectedItinerary->getTitle(), ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <p class="text-muted mb-0">Selecciona un itinerario para crear o editar temas.</p>
                <?php endif; ?>
            </div>
            <div class="btn-group">
                <a class="btn btn-outline-secondary" href="?page=itinerarios">Volver al listado</a>
                <?php if ($selectedItinerary): ?>
                    <a class="btn btn-primary" href="?page=itinerario&itinerary=<?= urlencode($selectedItinerary->getSlug()) ?>">Volver al itinerario</a>
                <?php else: ?>
                    <a class="btn btn-primary" href="?page=itinerario">Volver al itinerario</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($itineraryFeedback): ?>
            <div class="alert alert-<?= htmlspecialchars($itineraryFeedback['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($itineraryFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!$selectedItinerary): ?>
            <div class="alert alert-info">Elige primero un itinerario desde el listado para poder crear o editar temas.</div>
        <?php else: ?>
            <div class="card mb-4" id="topic-form">
                <div class="card-body itinerary-topics-card">
                    <form method="post">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h3 class="h5 mb-0"><?= $topicFormData['mode'] === 'existing' ? 'Editar tema' : 'Nuevo tema' ?></h3>
                            <a class="btn btn-outline-secondary" href="?page=itinerario&itinerary=<?= urlencode($selectedItinerary->getSlug()) ?>">Volver al itinerario</a>
                        </div>
                        <input type="hidden" name="topic_itinerary_slug" value="<?= htmlspecialchars($selectedItinerary->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="topic_original_slug" value="<?= htmlspecialchars($topicFormData['slug'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="topic_mode" value="<?= htmlspecialchars($topicFormData['mode'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-group">
                            <label for="topic_title">Título del tema</label>
                            <input type="text" name="topic_title" id="topic_title" class="form-control" value="<?= htmlspecialchars($topicFormData['title'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="topic_description">Descripción</label>
                            <textarea name="topic_description" id="topic_description" class="form-control" rows="3"><?= htmlspecialchars($topicFormData['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="topic_slug">Slug</label>
                            <input type="text" name="topic_slug" id="topic_slug" class="form-control" data-slug-input="1" pattern="[a-z0-9-]+" title="Usa solo letras minúsculas, números y guiones (-)" value="<?= htmlspecialchars($topicFormData['slug'], ENT_QUOTES, 'UTF-8') ?>" placeholder="tema-1">
                            <small class="form-text text-muted">Si lo dejas vacío se generará automáticamente a partir del título.</small>
                        </div>
                        <div class="form-group">
                            <label for="topic_number">Tema nº</label>
                            <select name="topic_number" id="topic_number" class="form-control">
                                <?php foreach ($topicNumberOptions as $option): ?>
                                    <option value="<?= $option ?>" <?= $option == $topicFormData['number'] ? 'selected' : '' ?>><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="topic_image">Imagen asociada</label>
                            <div class="input-group">
                                <input type="text" name="topic_image" id="topic_image" class="form-control" readonly value="<?= htmlspecialchars($topicFormData['image'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="topic_image" data-target-prefix="" data-redirect-anchor="topic-form">Seleccionar imagen</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="topic_content">Contenido del tema (Markdown)</label>
                            <div class="btn-toolbar markdown-toolbar mb-2 flex-wrap" role="toolbar" aria-label="Atajos de Markdown" data-markdown-toolbar data-target="#topic_content">
                                <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="bold"><strong>B</strong></button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="italic"><em>I</em></button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="strike">S̶</button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="code">&lt;/&gt;</button>
                                </div>
                                <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="link">Link</button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="quote">&gt;</button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="sup">x<sup>2</sup></button>
                                </div>
                                <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="ul">•</button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="ol">1.</button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="heading">H2</button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="code-block">{ }</button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="hr">—</button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="table">Tbl</button>
                                    <button type="button" class="btn btn-outline-secondary" data-md-action="callout" data-toggle="modal" data-target="#calloutModal">Aviso</button>
                                    <?php if (!empty($nisabaEnabled)): ?>
                                        <button type="button" class="btn btn-outline-secondary" data-md-action="nisaba" title="Nisaba" aria-label="Nisaba">
                                            <img src="nisaba.png" alt="" class="nisaba-icon">
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!empty($ideasEnabled)): ?>
                                        <button type="button" class="btn btn-outline-secondary" data-md-action="ideas" title="Ideas" aria-label="Ideas">Ideas</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <textarea name="topic_content" id="topic_content" class="form-control" rows="10" data-markdown-editor="itinerary-topic"><?= htmlspecialchars($topicFormData['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-2 topic-quiz-controls">
                                <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#topic_content" data-redirect-anchor="topic-form">Nuevo recurso</button>
                                <input type="hidden" name="topic_quiz_payload" id="topic_quiz_payload" value="<?= htmlspecialchars($topicFormData['quiz'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php $quizHasData = $topicFormData['quiz'] !== ''; ?>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    data-quiz-trigger
                                    data-quiz-target="#topic_quiz_payload"
                                    data-quiz-summary="#topic_quiz_summary"
                                    data-quiz-title="Autoevaluación del tema"
                                >
                                    <?= $quizHasData ? 'Editar autoevaluación' : 'Añadir autoevaluación' ?>
                                </button>
                                <small class="text-muted mb-0" id="topic_quiz_summary" data-quiz-summary="topic_quiz_payload"><?= htmlspecialchars($topicFormData['quiz_summary'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" name="save_itinerary_topic" class="btn btn-primary">Guardar tema</button>
                            <button type="submit" name="save_itinerary_topic_add" class="btn btn-secondary ml-2">Añadir nuevo tema</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>
