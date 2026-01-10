<?php if ($page === 'itinerario'): ?>

    <div class="tab-pane active">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div>
                <h2 class="mb-1"><?= $isNewItinerary ? 'Nuevo itinerario' : 'Editar itinerario' ?></h2>
                <p class="text-muted mb-0"><?= $selectedItinerary ? htmlspecialchars($selectedItinerary->getTitle(), ENT_QUOTES, 'UTF-8') : 'Define aquí la presentación del itinerario.' ?></p>
            </div>
            <div class="btn-group">
                <a class="btn btn-outline-secondary" href="?page=itinerarios">Volver al listado</a>
                <a class="btn btn-primary" href="?page=itinerario&new=1">Nuevo itinerario</a>
            </div>
        </div>

        <?php if ($itineraryFeedback): ?>
            <div class="alert alert-<?= htmlspecialchars($itineraryFeedback['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($itineraryFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!$selectedItinerary && !$isNewItinerary): ?>
            <div class="alert alert-info">Selecciona un itinerario desde el listado para editarlo o crea uno nuevo.</div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div id="itinerary-form" class="card mb-4">
                    <div class="card-body itinerary-form-card">
                        <form method="post">
                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                                <h3 class="h5 mb-0"><?= $itineraryFormData['mode'] === 'existing' ? 'Editar itinerario' : 'Nuevo itinerario' ?></h3>
                                <button type="submit" name="save_itinerary" class="btn btn-primary">Guardar itinerario</button>
                            </div>
                            <input type="hidden" name="itinerary_original_slug" value="<?= htmlspecialchars($itineraryFormData['slug'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="itinerary_mode" value="<?= htmlspecialchars($itineraryFormData['mode'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="itinerary_order" value="<?= (int) ($itineraryFormData['order'] ?? 0) ?>">
                            <div class="form-group">
                                <label for="itinerary_title">Título</label>
                                <input type="text" name="itinerary_title" id="itinerary_title" class="form-control" value="<?= htmlspecialchars($itineraryFormData['title'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="itinerary_description">Descripción</label>
                                <textarea name="itinerary_description" id="itinerary_description" class="form-control" rows="3"><?= htmlspecialchars($itineraryFormData['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="itinerary_status">Estado</label>
                                <?php $itineraryStatus = $itineraryFormData['status'] ?? 'draft'; ?>
                                <select name="itinerary_status" id="itinerary_status" class="form-control">
                                    <option value="published" <?= $itineraryStatus === 'published' ? 'selected' : '' ?>>Publicado</option>
                                    <option value="draft" <?= $itineraryStatus === 'draft' ? 'selected' : '' ?>>Borrador</option>
                                </select>
                                <small class="form-text text-muted">Los borradores no aparecerán en la portada pública de itinerarios ni en sus feeds.</small>
                            </div>
                            <?php
                                $itineraryUsageLogic = $itineraryFormData['usage_logic'] ?? 'free';
                                $usageOptions = admin_itinerary_usage_logic_options();
                            ?>
                            <div class="form-group">
                                <label class="d-block">Lógica de uso</label>
                                <p class="text-muted small mb-2">Define cómo debe avanzar el lector a través de los temas de este itinerario.</p>
                                <?php foreach ($usageOptions as $value => $label): ?>
                                    <?php $usageFieldId = 'itinerary_usage_logic_' . $value; ?>
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="itinerary_usage_logic"
                                            id="<?= htmlspecialchars($usageFieldId, ENT_QUOTES, 'UTF-8') ?>"
                                            value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $itineraryUsageLogic === $value ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label" for="<?= htmlspecialchars($usageFieldId, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <small class="form-text text-muted">Si eliges las dos últimas opciones, informaremos al lector de que se usarán cookies.</small>
                            </div>
                            <?php $itineraryClassChoice = $itineraryFormData['class_choice'] ?? ''; ?>
                            <div class="form-group">
                                <label for="itinerary_class">Clase de itinerario</label>
                                <select name="itinerary_class" id="itinerary_class" class="form-control" data-itinerary-class-select>
                                    <option value="" <?= $itineraryClassChoice === '' ? 'selected' : '' ?>>Selecciona una opción</option>
                                    <?php foreach (admin_itinerary_class_options() as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $itineraryClassChoice === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Este texto aparecerá como subtítulo del itinerario.</small>
                            </div>
                            <div class="form-group <?= $itineraryClassChoice === 'Otros' ? '' : 'd-none' ?>" data-itinerary-class-custom-wrapper>
                                <label for="itinerary_class_custom">Especifica la clase</label>
                                <input type="text" name="itinerary_class_custom" id="itinerary_class_custom" class="form-control" value="<?= htmlspecialchars($itineraryFormData['class_custom'], ENT_QUOTES, 'UTF-8') ?>" maxlength="80" placeholder="Ej. Programa especial" <?= $itineraryClassChoice === 'Otros' ? 'required' : '' ?>>
                                <small class="form-text text-muted">Texto corto (máx. 80 caracteres) que sustituye la etiqueta “Itinerario”.</small>
                            </div>
                            <div class="form-group">
                                <label for="itinerary_slug">Slug</label>
                                <input type="text" name="itinerary_slug" id="itinerary_slug" class="form-control" data-slug-input="1" pattern="[a-z0-9-]+" title="Usa solo letras minúsculas, números y guiones (-)" value="<?= htmlspecialchars($itineraryFormData['slug'], ENT_QUOTES, 'UTF-8') ?>" placeholder="mi-itinerario">
                                <small class="form-text text-muted">Si lo dejas vacío lo generaremos automáticamente a partir del título.</small>
                            </div>
                            <div class="form-group">
                                <label for="itinerary_image">Imagen destacada</label>
                                <div class="input-group">
                                    <input type="text" name="itinerary_image" id="itinerary_image" class="form-control" readonly value="<?= htmlspecialchars($itineraryFormData['image'], ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="itinerary_image" data-target-prefix="">Seleccionar imagen</button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="itinerary_content">Presentación del itinerario</label>
                                <div class="btn-toolbar markdown-toolbar mb-2 flex-wrap" role="toolbar" aria-label="Atajos de Markdown" data-markdown-toolbar data-target="#itinerary_content">
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
                                    </div>
                                </div>
                                <textarea name="itinerary_content" id="itinerary_content" class="form-control" rows="10" data-markdown-editor="itinerary"><?= htmlspecialchars($itineraryFormData['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#itinerary_content">Insertar recurso</button>
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 topic-quiz-controls">
                                    <input type="hidden" name="itinerary_quiz_payload" id="itinerary_quiz_payload" value="<?= htmlspecialchars($itineraryFormData['quiz'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        data-quiz-trigger
                                        data-quiz-target="#itinerary_quiz_payload"
                                        data-quiz-summary="#itinerary_quiz_summary"
                                        data-quiz-title="Autoevaluación de la presentación"
                                    >
                                        <?= ($itineraryFormData['quiz'] ?? '') !== '' ? 'Editar autoevaluación' : 'Añadir autoevaluación' ?>
                                    </button>
                                    <small class="text-muted mb-0" id="itinerary_quiz_summary" data-quiz-summary="itinerary_quiz_payload">
                                        <?= htmlspecialchars($itineraryFormData['quiz_summary'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="sticky-save">
                                <button type="submit" name="save_itinerary" class="btn btn-primary">Guardar itinerario</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-12 mt-4 mt-lg-0">
                <div class="card mb-4">
                    <div class="card-body itinerary-topics-card">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h3 class="h5 mb-0">
                                <?php if ($selectedItinerary): ?>
                                    Temas de <?= htmlspecialchars($selectedItinerary->getTitle(), ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    Temas del itinerario
                                <?php endif; ?>
                            </h3>
                            <?php if ($selectedItinerary): ?>
                                <a class="btn btn-outline-primary" href="?page=itinerario-tema&itinerary=<?= urlencode($selectedItinerary->getSlug()) ?>&topic=new#topic-form">Nuevo tema</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-primary disabled" disabled title="Guarda el itinerario para poder añadir temas">Nuevo tema</button>
                            <?php endif; ?>
                        </div>
                        <?php if (!$selectedItinerary): ?>
                            <p class="text-muted mb-0">Selecciona o crea un itinerario para gestionar sus temas.</p>
                        <?php elseif (empty($selectedItinerary->getTopics())): ?>
                            <p class="text-muted mb-0">Añade tu primer tema para comenzar el itinerario.</p>
                        <?php else: ?>
                            <?php foreach ($selectedItinerary->getTopics() as $topicItem): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <span class="badge badge-secondary mb-2">Tema <?= (int) $topicItem->getNumber() ?></span>
                                                <h4 class="h6 mb-1"><?= htmlspecialchars($topicItem->getTitle(), ENT_QUOTES, 'UTF-8') ?></h4>
                                                <?php if ($topicItem->getDescription() !== ''): ?>
                                                    <p class="mb-2 text-muted"><?= htmlspecialchars($topicItem->getDescription(), ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-right">
                                                <div class="mb-2">
                                                    <?php
                                                    $topicPreviewUrl = admin_public_itinerary_url($selectedItinerary->getSlug()) . '/' . rawurlencode($topicItem->getSlug()) . '?preview=1';
                                                    ?>
                                                    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($topicPreviewUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Ver</a>
                                                </div>
                                                <div class="mb-2">
                                                    <a class="btn btn-sm btn-outline-primary" href="?page=itinerario-tema&itinerary=<?= urlencode($selectedItinerary->getSlug()) ?>&topic=<?= urlencode($topicItem->getSlug()) ?>#topic-form">Editar</a>
                                                </div>
                                                <form method="post" class="d-inline-block" onsubmit="return confirm('¿Seguro que deseas borrar este tema del itinerario?');">
                                                    <input type="hidden" name="delete_topic_itinerary_slug" value="<?= htmlspecialchars($selectedItinerary->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="delete_topic_slug" value="<?= htmlspecialchars($topicItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" name="delete_itinerary_topic" class="btn btn-sm btn-outline-danger mt-1">Borrar</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>
