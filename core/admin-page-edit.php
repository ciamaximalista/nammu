<?php
$editFeedback = $_SESSION['edit_feedback'] ?? null;
if ($editFeedback !== null) {
    unset($_SESSION['edit_feedback']);
}
?>

<?php if ($page === 'edit'): ?>

    <div class="tab-pane active">

        <?php
        $templateFilter = $_GET['template'] ?? 'single';
        $allowedFilters = ['single', 'page', 'draft'];
        if (!in_array($templateFilter, $allowedFilters, true)) {
            $templateFilter = 'single';
        }
        $currentTypeLabel = [
            'single' => 'Entradas',
            'page' => 'Páginas',
            'draft' => 'Borradores',
        ][$templateFilter];
        $searchQuery = trim($_GET['q'] ?? '');
        $searchQueryParam = $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '';
        ?>

        <h2>Editar</h2>
        <?php if ($socialFeedback !== null): ?>
            <div class="alert alert-<?= $socialFeedback['type'] === 'success' ? 'success' : 'warning' ?>">
                <?= htmlspecialchars($socialFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form class="form-inline mb-3 edit-search-form" method="get">
            <input type="hidden" name="page" value="edit">
            <input type="hidden" name="template" value="<?= htmlspecialchars($templateFilter, ENT_QUOTES, 'UTF-8') ?>">
            <label for="edit-search-input" class="sr-only">Buscar</label>
            <input type="search"
                class="form-control form-control-sm mr-2"
                id="edit-search-input"
                name="q"
                placeholder="Buscar por título, descripción o archivo"
                value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
                style="min-width: 220px;">
            <button type="submit" class="btn btn-sm btn-outline-secondary mr-2">Buscar</button>
            <?php if ($searchQuery !== ''): ?>
                <a class="btn btn-sm btn-link" href="?page=edit&template=<?= htmlspecialchars($templateFilter, ENT_QUOTES, 'UTF-8') ?>">Limpiar</a>
            <?php endif; ?>
        </form>

        <div class="btn-group mb-3" role="group" aria-label="Filtrar por tipo">
            <a href="?page=edit&template=single<?= $searchQueryParam ?>" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'single' ? 'active' : '' ?>">Entradas</a>
            <a href="?page=edit&template=page<?= $searchQueryParam ?>" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'page' ? 'active' : '' ?>">Páginas</a>
            <a href="?page=edit&template=draft<?= $searchQueryParam ?>" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'draft' ? 'active' : '' ?>">Borradores</a>
        </div>

        <p class="text-muted">Mostrando <?= strtolower($currentTypeLabel) ?>.</p>
        <?php
        $networkConfigs = [
            'telegram' => $settings['telegram'] ?? [],
            'whatsapp' => $settings['whatsapp'] ?? [],
            'facebook' => $settings['facebook'] ?? [],
            'twitter' => $settings['twitter'] ?? [],
        ];
        $networkLabels = [
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            'facebook' => 'Facebook',
            'twitter' => 'X',
        ];
        $mailingReady = admin_is_mailing_ready($settings);
        ?>

        <table class="table table-striped">

            <thead>

                <tr>

                    <th>Título</th>

                    <th>Descripción</th>

                    <th>Fecha</th>

                    <th>Nombre de archivo</th>

                    <th class="text-center">Redes</th>

                    <th></th>

                </tr>

            </thead>

            <tbody>

                <?php

                $current_page = $_GET['p'] ?? 1;

                $posts_data = get_posts($current_page, 16, $templateFilter, $searchQuery);

                if (empty($posts_data['posts'])):
                ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No hay <?= strtolower($currentTypeLabel) ?> disponibles.</td>
                    </tr>
                <?php
                else:
                    foreach ($posts_data['posts'] as $post):
                ?>

                    <tr>

                        <td><?= htmlspecialchars($post['title']) ?></td>

                        <td><?= htmlspecialchars($post['description']) ?></td>

                        <td><?= htmlspecialchars($post['date']) ?></td>

                        <?php
                        $postSlug = pathinfo($post['filename'], PATHINFO_FILENAME);
                        $postLink = admin_public_post_url($postSlug);
                        ?>
                        <td><a href="<?= htmlspecialchars($postLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($post['filename']) ?></a></td>

                        <td class="text-center">
                            <?php
                            $availableNetworks = [];
                            foreach ($networkConfigs as $key => $cfg) {
                                if (admin_is_social_network_configured($key, $cfg)) {
                                    $availableNetworks[] = $key;
                                }
                            }
                            ?>
                            <?php if ((!empty($availableNetworks) && in_array($templateFilter, ['single', 'draft'], true)) || $mailingReady): ?>
                                <?php foreach ($availableNetworks as $networkKey): ?>
                                    <form method="post" class="d-inline-block mb-1">
                                        <input type="hidden" name="social_network" value="<?= htmlspecialchars($networkKey, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="social_filename" value="<?= htmlspecialchars($post['filename'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="social_template" value="<?= htmlspecialchars($templateFilter, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" name="send_social_post" class="btn btn-sm btn-outline-primary">
                                            <?= htmlspecialchars($networkLabels[$networkKey] ?? ucfirst($networkKey), ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                                <?php if ($mailingReady && in_array($templateFilter, ['single', 'draft'], true)): ?>
                                    <form method="post" class="d-inline-block mb-1">
                                        <input type="hidden" name="mailing_filename" value="<?= htmlspecialchars($post['filename'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="mailing_template" value="<?= htmlspecialchars($templateFilter, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" name="send_mailing_post" class="btn btn-sm btn-outline-success">Lista</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-right">
                            <div class="d-flex flex-column align-items-end">
                                <a href="?page=edit-post&file=<?= urlencode($post['filename']) ?>" class="btn btn-sm btn-primary mb-2">Editar</a>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        data-delete-file="<?= htmlspecialchars($post['filename'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-delete-title="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-delete-type="<?= htmlspecialchars($templateFilter, ENT_QUOTES, 'UTF-8') ?>"
                                        data-toggle="modal"
                                        data-target="#deletePostModal">
                                    Borrar
                                </button>
                            </div>
                        </td>

                    </tr>

                <?php
                    endforeach;
                endif;
                ?>

            </tbody>

        </table>


        <nav aria-label="Page navigation">

            <ul class="pagination pagination-break">

                <?php
                $pageGroupSize = 16;
                for ($i = 1; $i <= $posts_data['pages']; $i++): ?>

                    <li class="page-item <?= $i == $posts_data['current_page'] ? 'active' : '' ?>">

                        <a class="page-link" href="?page=edit&template=<?= urlencode($templateFilter) ?>&p=<?= $i ?><?= $searchQueryParam ?>"><?= $i ?></a>

                    </li>

                    <?php if ($i % $pageGroupSize === 0 && $i < $posts_data['pages']): ?>
                        <li class="page-break"></li>
                    <?php endif; ?>

                <?php endfor; ?>

            </ul>

        </nav>

    </div>

<?php elseif ($page === 'edit-post'): ?>

    <div class="tab-pane active">

        <?php

        $requestedFile = $_GET['file'] ?? '';
        $safeEditFilename = nammu_normalize_filename($requestedFile);

        $post_data = $safeEditFilename !== '' ? get_post_content($safeEditFilename) : null;

        if ($post_data):
            $currentTemplateValue = strtolower($post_data['metadata']['Template'] ?? 'post');
            $currentTypeValue = $currentTemplateValue === 'page' ? 'Página' : 'Entrada';
            $editHeading = $currentTypeValue === 'Página' ? 'Editar Página' : 'Editar Entrada';
            $currentStatusValue = strtolower($post_data['metadata']['Status'] ?? 'published');
            if (!in_array($currentStatusValue, ['draft', 'published'], true)) {
                $currentStatusValue = 'published';
            }
            $isDraftEditing = $currentStatusValue === 'draft';
        ?>

        <?php if ($editFeedback !== null): ?>
            <div class="alert alert-<?= $editFeedback['type'] === 'success' ? 'success' : 'warning' ?>">
                <?= htmlspecialchars($editFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <h2><?= $editHeading ?></h2>

            <form method="post">

            <input type="hidden" name="filename" value="<?= htmlspecialchars($safeEditFilename, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($currentStatusValue, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">

                <label for="title">Título</label>

                <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars($post_data['metadata']['Title'] ?? '') ?>" required>

            </div>

            <div class="form-group">

                <label for="type">Tipo</label>

                <select name="type" id="type" class="form-control">

                    <option value="Entrada" <?= $currentTypeValue === 'Entrada' ? 'selected' : '' ?>>Entrada</option>

                    <option value="Página" <?= $currentTypeValue === 'Página' ? 'selected' : '' ?>>Página</option>

                </select>

            </div>

            <div class="form-group">

                <label for="category">Categoría</label>

                <input type="text" name="category" id="category" class="form-control" value="<?= htmlspecialchars($post_data['metadata']['Category'] ?? '') ?>">

            </div>

            <div class="form-group">

                <label for="date">Fecha</label>

                <input type="date" name="date" id="date" class="form-control" value="<?= htmlspecialchars(format_date_for_input($post_data['metadata']['Date'] ?? null), ENT_QUOTES, 'UTF-8') ?>" required>

            </div>

            <div class="form-group">

                <label for="image">Imagen</label>

                <div class="input-group">

                    <input type="text" name="image" id="image" class="form-control" value="<?= htmlspecialchars($post_data['metadata']['Image'] ?? '') ?>" readonly>

                    <div class="input-group-append">

                        <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="image" data-target-prefix="">Seleccionar imagen</button>

                    </div>

                </div>

            </div>

            <div class="form-group">

                <label for="description">Descripción</label>

                <textarea name="description" id="description" class="form-control" rows="3"><?= htmlspecialchars($post_data['metadata']['Description'] ?? '') ?></textarea>

            </div>

            <div class="form-group">

                <label for="content_edit">Contenido (Markdown)</label>
                <div class="btn-toolbar markdown-toolbar mb-2 flex-wrap" role="toolbar" aria-label="Atajos de Markdown" data-markdown-toolbar data-target="#content_edit">
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

                <textarea name="content" id="content_edit" class="form-control" rows="15" data-markdown-editor="1"><?= htmlspecialchars($post_data['content']) ?></textarea>

                <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#content_edit">Insertar recurso</button>
                <small class="form-text text-muted mt-1">Inserta en la entrada imágenes, vídeos o archivos PDF.</small>

            </div>

            <div class="form-group">
                <label for="new_filename">Slug del post (nombre de archivo sin .md)</label>
                <input type="text"
                       name="new_filename"
                       id="new_filename"
                       class="form-control"
                       pattern="[a-z0-9-]+"
                       inputmode="text"
                       autocomplete="off"
                       autocapitalize="none"
                       spellcheck="false"
                       data-slug-input="1"
                       value="<?= htmlspecialchars(pathinfo($safeEditFilename, PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8') ?>">
                <small class="form-text text-muted">Opcional. Déjalo vacío para recalcular el slug a partir del título.</small>
            </div>

            <div class="mt-3">
                <button type="submit" name="update" class="btn btn-primary">Actualizar</button>
                <?php if ($isDraftEditing): ?>
                    <button type="submit" name="publish_draft_entry" value="1" class="btn btn-success ml-2">Publicar como entrada</button>
                    <button type="submit" name="publish_draft_page" value="1" class="btn btn-success ml-2">Publicar como página</button>
                <?php elseif ($currentTypeValue === 'Entrada' && !$isDraftEditing): ?>
                    <button type="submit" name="convert_to_draft" value="1" class="btn btn-outline-secondary ml-2">Pasar a borrador</button>
                <?php endif; ?>
            </div>

            </form>

        <?php else: ?>

        <div class="alert alert-warning">

            <p>No se pudo cargar el contenido. Puede que el archivo no exista o el nombre no sea válido.</p>

        </div>

        <?php endif; ?>

    </div>

<?php endif; ?>
