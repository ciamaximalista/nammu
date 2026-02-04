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
        $allowedFilters = ['single', 'page', 'draft', 'newsletter', 'podcast'];
        if (!in_array($templateFilter, $allowedFilters, true)) {
            $templateFilter = 'single';
        }
        $currentTypeLabel = [
            'single' => 'Entradas',
            'page' => 'Páginas',
            'draft' => 'Borradores',
            'newsletter' => 'Newsletters',
            'podcast' => 'Podcasts',
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
        <?php if ($mailingFeedback !== null): ?>
            <div class="alert alert-<?= $mailingFeedback['type'] === 'success' ? 'success' : ($mailingFeedback['type'] === 'warning' ? 'warning' : 'danger') ?>">
                <?= htmlspecialchars($mailingFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
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
            <a href="?page=edit&template=newsletter<?= $searchQueryParam ?>" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'newsletter' ? 'active' : '' ?>">Newsletters</a>
            <a href="?page=edit&template=podcast<?= $searchQueryParam ?>" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'podcast' ? 'active' : '' ?>">Podcasts</a>
            <a href="?page=edit&template=draft<?= $searchQueryParam ?>" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'draft' ? 'active' : '' ?>">Borradores</a>
        </div>

        <p class="text-muted">Mostrando <?= strtolower($currentTypeLabel) ?>.</p>
        <?php
        $networkConfigs = [
            'telegram' => $settings['telegram'] ?? [],
            'whatsapp' => $settings['whatsapp'] ?? [],
            'facebook' => $settings['facebook'] ?? [],
            'twitter' => $settings['twitter'] ?? [],
            'bluesky' => $settings['bluesky'] ?? [],
            'mastodon' => $settings['mastodon'] ?? [],
            'instagram' => $settings['instagram'] ?? [],
        ];
        $networkLabels = [
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            'facebook' => 'Facebook',
            'twitter' => 'X',
            'bluesky' => 'Bluesky',
            'mastodon' => 'Mastodon',
            'instagram' => 'Instagram',
        ];
        $mailingReady = admin_is_mailing_ready($settings);
        $mailingSettings = $settings['mailing'] ?? (function_exists('get_settings') ? (get_settings()['mailing'] ?? []) : []);
        $mailingNewsletterEnabled = (($mailingSettings['auto_newsletter'] ?? (($mailingSettings['gmail_address'] ?? '') !== '' ? 'on' : 'off')) === 'on');
        $monthNames = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];
        $nowTimestamp = time();
        ?>

        <?php
        $showVisibilityColumn = ($templateFilter === 'page');
        $showSocialColumn = ($templateFilter !== 'newsletter' && !$showVisibilityColumn);
        $columnCount = 4;
        if ($templateFilter !== 'podcast') {
            $columnCount++;
        }
        if ($showSocialColumn) {
            $columnCount++;
        }
        ?>

        <table class="table table-striped">

            <thead>

                <tr>

                    <th>Título</th>

                    <th>Descripción</th>

                    <th>Fecha</th>

                    <?php if ($templateFilter !== 'podcast'): ?>
                        <th>Nombre de archivo</th>
                    <?php endif; ?>

                    <?php if ($showVisibilityColumn): ?>
                        <th class="text-center">👁</th>
                    <?php elseif ($showSocialColumn): ?>
                        <th class="text-center">Redes</th>
                    <?php endif; ?>

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
                        <td colspan="<?= $columnCount ?>" class="text-center text-muted">No hay <?= strtolower($currentTypeLabel) ?> disponibles.</td>
                    </tr>
                <?php
                else:
                    foreach ($posts_data['posts'] as $post):
                ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($post['title']) ?>
                            <?php
                            $publishAtRaw = trim((string) ($post['publish_at'] ?? ''));
                            $publishAtTs = $publishAtRaw !== '' ? strtotime($publishAtRaw) : false;
                            ?>
                            <?php if ($templateFilter === 'draft' && $publishAtTs !== false && $publishAtTs > $nowTimestamp): ?>
                                <?php
                                $day = date('j', $publishAtTs);
                                $monthIndex = (int) date('n', $publishAtTs);
                                $year = date('Y', $publishAtTs);
                                $time = date('H:i', $publishAtTs);
                                $monthName = $monthNames[$monthIndex] ?? '';
                                ?>
                                <div class="draft-schedule-badge">
                                    Programado para el <?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?> de <?= htmlspecialchars($monthName, ENT_QUOTES, 'UTF-8') ?> de <?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?> a las <?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($post['description']) ?></td>

                        <td><?= htmlspecialchars($post['date']) ?></td>

                        <?php if ($templateFilter !== 'podcast'): ?>
                            <?php
                            $postSlug = pathinfo($post['filename'], PATHINFO_FILENAME);
                            $postLink = admin_public_post_url($postSlug);
                            ?>
                            <td>
                                <a href="<?= htmlspecialchars($postLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($post['filename']) ?></a>
                            </td>
                        <?php endif; ?>

                        <?php if ($showVisibilityColumn): ?>
                        <td class="text-center">
                            <?php if (($post['visibility'] ?? 'public') === 'private'): ?>
                                <span title="Privada" aria-label="Privada">🔐</span>
                            <?php else: ?>
                                <span title="Pública" aria-label="Pública">👁</span>
                            <?php endif; ?>
                        </td>
                        <?php elseif ($showSocialColumn): ?>
                        <td class="text-center">
                            <?php
                            $availableNetworks = [];
                            foreach ($networkConfigs as $key => $cfg) {
                                if (admin_is_social_network_configured($key, $cfg)) {
                                    $availableNetworks[] = $key;
                                }
                            }
                            ?>
                            <?php if (in_array($templateFilter, ['single', 'draft', 'podcast'], true)): ?>
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
                                <?php if ($mailingReady): ?>
                                    <form method="post" class="d-inline-block mb-1">
                                        <input type="hidden" name="mailing_filename" value="<?= htmlspecialchars($post['filename'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="mailing_template" value="<?= htmlspecialchars($templateFilter, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" name="send_mailing_post" class="btn btn-sm btn-outline-success">Lista</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (empty($availableNetworks) && !$mailingReady): ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>

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

        <style>
            .draft-schedule-badge {
                display: inline-block;
                margin-top: 0.4rem;
                padding: 0.25rem 0.5rem;
                border-radius: 6px;
                background: #ea2f28;
                color: #ffffff;
                font-size: 0.78rem;
                font-weight: 600;
                letter-spacing: 0.01em;
            }
        </style>


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
            if ($currentTemplateValue === 'page') {
                $currentTypeValue = 'Página';
            } elseif ($currentTemplateValue === 'podcast') {
                $currentTypeValue = 'Podcast';
            } elseif ($currentTemplateValue === 'newsletter') {
                $currentTypeValue = 'Newsletter';
            } else {
                $currentTypeValue = 'Entrada';
            }
            if ($currentTypeValue === 'Página') {
                $editHeading = 'Editar Página';
            } elseif ($currentTypeValue === 'Podcast') {
                $editHeading = 'Editar Podcast';
            } elseif ($currentTypeValue === 'Newsletter') {
                $editHeading = 'Editar Newsletter';
            } else {
                $editHeading = 'Editar Entrada';
            }
            $currentStatusValue = strtolower($post_data['metadata']['Status'] ?? 'published');
            if (!in_array($currentStatusValue, ['draft', 'published', 'newsletter'], true)) {
                $currentStatusValue = 'published';
            }
            $isNewsletterType = $currentTypeValue === 'Newsletter';
            $newsletterSent = $isNewsletterType && $currentStatusValue === 'newsletter';
            $isDraftEditing = $currentStatusValue === 'draft';
            $publishAtRaw = trim((string) ($post_data['metadata']['PublishAt'] ?? ''));
            $publishAtDate = '';
            $publishAtTime = '';
            if ($publishAtRaw !== '') {
                $publishAtTimestamp = strtotime($publishAtRaw);
                if ($publishAtTimestamp !== false) {
                    $publishAtDate = date('Y-m-d', $publishAtTimestamp);
                    $publishAtTime = date('H:i', $publishAtTimestamp);
                }
            }
            $mailingSettings = $settings['mailing'] ?? (function_exists('get_settings') ? (get_settings()['mailing'] ?? []) : []);
            $mailingNewsletterEnabled = (($mailingSettings['auto_newsletter'] ?? (($mailingSettings['gmail_address'] ?? '') !== '' ? 'on' : 'off')) === 'on');
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
            $postLang = trim((string) ($post_data['metadata']['Lang'] ?? ''));
            if ($postLang === '') {
                $postLang = $siteLang;
            }
            $audioValue = $post_data['metadata']['Audio'] ?? '';
            $audioLength = $post_data['metadata']['AudioLength'] ?? '';
            $audioDuration = $post_data['metadata']['AudioDuration'] ?? '';
            $pageVisibilityRaw = strtolower(trim((string) ($post_data['metadata']['Visibility'] ?? 'public')));
            $pageVisibility = $pageVisibilityRaw === 'private' ? 'private' : 'public';
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

                <label for="title" data-podcast-label="Título del episodio" data-post-label="Título">Título</label>

                <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars($post_data['metadata']['Title'] ?? '') ?>" required>

            </div>

            <div class="form-group">
                <label>Tipo</label>
                <input type="hidden" name="type" id="type" value="<?= htmlspecialchars($currentTypeValue, ENT_QUOTES, 'UTF-8') ?>" data-type-value>
                <div class="btn-group d-flex flex-wrap" role="group" data-type-toggle>
                    <button type="button" class="btn <?= $currentTypeValue === 'Entrada' ? 'btn-primary active' : 'btn-outline-primary' ?>" data-type-option="Entrada" aria-pressed="<?= $currentTypeValue === 'Entrada' ? 'true' : 'false' ?>">Entrada</button>
                    <button type="button" class="btn <?= $currentTypeValue === 'Página' ? 'btn-primary active' : 'btn-outline-primary' ?>" data-type-option="Página" aria-pressed="<?= $currentTypeValue === 'Página' ? 'true' : 'false' ?>">Página</button>
                    <button type="button" class="btn <?= $currentTypeValue === 'Newsletter' ? 'btn-primary active' : 'btn-outline-primary' ?>" data-type-option="Newsletter" aria-pressed="<?= $currentTypeValue === 'Newsletter' ? 'true' : 'false' ?>">Newsletter</button>
                    <button type="button" class="btn <?= $currentTypeValue === 'Podcast' ? 'btn-primary active' : 'btn-outline-primary' ?>" data-type-option="Podcast" aria-pressed="<?= $currentTypeValue === 'Podcast' ? 'true' : 'false' ?>">Podcast</button>
                </div>
            </div>

            <div class="form-group entry-only">

                <label for="category">Categoría</label>

                <input type="text" name="category" id="category" class="form-control" value="<?= htmlspecialchars($post_data['metadata']['Category'] ?? '') ?>">

            </div>

            <div class="form-group page-only<?= $currentTypeValue === 'Página' ? '' : ' d-none' ?>">
                <label for="page_visibility">Visibilidad de la página</label>
                <select name="page_visibility" id="page_visibility" class="form-control">
                    <option value="public" <?= $pageVisibility === 'public' ? 'selected' : '' ?>>Pública</option>
                    <option value="private" <?= $pageVisibility === 'private' ? 'selected' : '' ?>>Privada</option>
                </select>
            </div>

            <div class="form-group">

                <label for="date">Fecha</label>

                <input type="date" name="date" id="date" class="form-control" value="<?= htmlspecialchars(format_date_for_input($post_data['metadata']['Date'] ?? null), ENT_QUOTES, 'UTF-8') ?>" required>

            </div>

            <div class="form-group">
                <label for="lang">Lengua de la entrada</label>
                <select name="lang" id="lang" class="form-control">
                    <?php foreach ($languageOptions as $code => $label): ?>
                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $postLang === $code ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($isDraftEditing): ?>
                <div class="form-group">
                    <label>Programar publicación</label>
                    <div class="form-row">
                        <div class="col-md-6">
                            <input type="date" name="publish_at_date" class="form-control" value="<?= htmlspecialchars($publishAtDate, ENT_QUOTES, 'UTF-8') ?>" placeholder="Fecha">
                        </div>
                        <div class="col-md-6">
                            <input type="time" name="publish_at_time" class="form-control" value="<?= htmlspecialchars($publishAtTime, ENT_QUOTES, 'UTF-8') ?>" placeholder="Hora">
                        </div>
                    </div>
                    <small class="form-text text-muted">Si defines una fecha y hora, se publicará automáticamente.</small>
                </div>
            <?php endif; ?>

            <div class="form-group">

                <label for="image" data-podcast-label="Imagen asociada al episodio" data-post-label="Imagen">Imagen</label>

                <div class="input-group">

                    <input type="text" name="image" id="image" class="form-control" value="<?= htmlspecialchars($post_data['metadata']['Image'] ?? '') ?>" readonly>

                    <div class="input-group-append">

                        <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="image" data-target-prefix="">Seleccionar imagen</button>

                    </div>

                </div>

            </div>

            <div class="form-group">

                <label for="description" data-podcast-label="Descripción" data-post-label="Descripción">Descripción</label>

                <textarea name="description" id="description" class="form-control" rows="3"><?= htmlspecialchars($post_data['metadata']['Description'] ?? '') ?></textarea>

            </div>

            <div class="form-group non-podcast">

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

                <textarea name="content" id="content_edit" class="form-control" rows="15" data-markdown-editor="1"><?= htmlspecialchars($post_data['content']) ?></textarea>

                <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#content_edit">Insertar recurso</button>
                <small class="form-text text-muted mt-1">Inserta en la entrada imágenes, vídeos o archivos PDF.</small>

            </div>

            <div class="form-group podcast-only d-none">
                <label for="audio">Archivo de audio (mp3)</label>
                <div class="input-group">
                    <input type="text" name="audio" id="audio" class="form-control" value="<?= htmlspecialchars($audioValue, ENT_QUOTES, 'UTF-8') ?>" readonly>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="audio" data-target-prefix="" data-target-accept="audio">Seleccionar audio</button>
                    </div>
                </div>
                <small class="form-text text-muted">Selecciona un archivo mp3 desde Recursos.</small>
            </div>

            <div class="form-group podcast-only d-none">
                <label for="audio_length">Longitud del archivo (bytes)</label>
                <input type="text" name="audio_length" id="audio_length" class="form-control" value="<?= htmlspecialchars($audioLength, ENT_QUOTES, 'UTF-8') ?>" placeholder="Se calcula automáticamente si es posible">
            </div>

            <div class="form-group podcast-only d-none">
                <label for="audio_duration">Duración (hh:mm:ss)</label>
                <input type="text" name="audio_duration" id="audio_duration" class="form-control" value="<?= htmlspecialchars($audioDuration, ENT_QUOTES, 'UTF-8') ?>" placeholder="00:45:00">
            </div>

            <div class="form-group non-podcast">
                <label for="new_filename" data-podcast-label="Slug del episodio (opcional)" data-post-label="Slug del post (nombre de archivo sin .md)">Slug del post (nombre de archivo sin .md)</label>
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
                <div class="alert alert-warning d-none" data-publish-cancelled>Los cambios no se han guardado.</div>
                <?php if (!($isNewsletterType && $newsletterSent)): ?>
                    <button type="submit" name="update" class="btn btn-primary">Actualizar</button>
                <?php endif; ?>
                <?php if ($isNewsletterType && $newsletterSent && $mailingNewsletterEnabled): ?>
                    <button type="submit" name="resend_newsletter_edit" value="1" class="btn btn-primary">Volver a enviar</button>
                <?php endif; ?>
                <?php if ($isDraftEditing): ?>
                    <?php if ($currentTypeValue === 'Podcast'): ?>
                        <button type="submit" name="publish_draft_podcast" value="1" class="btn btn-primary ml-2" data-confirm-publish="1">Emitir</button>
                    <?php elseif ($currentTypeValue === 'Página'): ?>
                        <button type="submit" name="publish_draft_page" value="1" class="btn btn-primary ml-2" data-confirm-publish="1">Publicar</button>
                    <?php elseif ($currentTypeValue === 'Newsletter'): ?>
                        <?php if ($mailingNewsletterEnabled): ?>
                            <button type="submit" name="send_newsletter_edit" value="1" class="btn btn-primary ml-2" data-confirm-publish="1" data-newsletter-button="1">Enviar</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button type="submit" name="publish_draft_entry" value="1" class="btn btn-primary ml-2" data-confirm-publish="1">Publicar</button>
                    <?php endif; ?>
                <?php elseif (in_array($currentTypeValue, ['Entrada', 'Podcast'], true) && !$isDraftEditing): ?>
                    <button type="submit" name="convert_to_draft" value="1" class="btn btn-outline-secondary ml-2">Pasar a borrador</button>
                <?php endif; ?>
            </div>

            </form>

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
                var titleLabel = document.querySelector('label[for="title"]');
                var descriptionLabel = document.querySelector('label[for="description"]');
                var imageLabel = document.querySelector('label[for="image"]');
                var slugLabel = document.querySelector('label[for="new_filename"]');
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
                    if (newsletterButton) {
                        newsletterButton.classList.toggle('d-none', typeValue !== 'Newsletter');
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

        <?php else: ?>

        <div class="alert alert-warning">

            <p>No se pudo cargar el contenido. Puede que el archivo no exista o el nombre no sea válido.</p>

        </div>

        <?php endif; ?>

    </div>

<?php endif; ?>
