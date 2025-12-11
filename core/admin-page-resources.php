<?php if ($page === 'resources'):
    $resourceSearchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    if ($resourceSearchTerm === '' && isset($_GET['tag'])) {
        $resourceSearchTerm = trim((string) $_GET['tag']);
    }
    $current_page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;

    $media_tags_map = load_media_tags();
    $all_media = get_media_items(1, 0); // traer todo y paginar manualmente para poder filtrar por tags/nombre
    $resourceSearchNormalized = $resourceSearchTerm !== ''
        ? (function_exists('mb_strtolower') ? mb_strtolower($resourceSearchTerm, 'UTF-8') : strtolower($resourceSearchTerm))
        : '';

    $filteredItems = [];
    foreach ($all_media['items'] as $media) {
        $relative_path = $media['relative'] ?? '';
        $tags_for_item = $media_tags_map[$relative_path] ?? [];
        if ($resourceSearchNormalized !== '') {
            $haystackParts = [
                $media['name'] ?? '',
                $relative_path,
                implode(' ', $tags_for_item),
            ];
            $haystack = function_exists('mb_strtolower')
                ? mb_strtolower(implode(' ', $haystackParts), 'UTF-8')
                : strtolower(implode(' ', $haystackParts));
            if (strpos($haystack, $resourceSearchNormalized) === false) {
                continue;
            }
        }
        $filteredItems[] = $media;
    }

    $perPage = 40;
    $totalItems = count($filteredItems);
    $pages = max(1, (int) ceil($totalItems / $perPage));
    $current_page = max(1, min($current_page, $pages));
    $offset = ($current_page - 1) * $perPage;
    $pageItems = array_slice($filteredItems, $offset, $perPage);
    $media_data = [
        'items' => $pageItems,
        'total' => $totalItems,
        'pages' => $pages,
        'current_page' => $current_page,
    ];

    $tagCloud = [];
    foreach ($media_tags_map as $tagsList) {
        if (!is_array($tagsList)) {
            continue;
        }
        foreach ($tagsList as $tag) {
            $normalized = nammu_normalize_tag((string) $tag);
            if ($normalized === '') {
                continue;
            }
            $tagCloud[$normalized] = ($tagCloud[$normalized] ?? 0) + 1;
        }
    }
    ksort($tagCloud, SORT_NATURAL | SORT_FLAG_CASE);
?>

    <div class="tab-pane active">

        <h2>Recursos</h2>

        <?php if ($assetFeedback !== null): ?>
            <div class="alert alert-<?= $assetFeedback['type'] === 'success' ? 'success' : 'warning' ?>">
                <?= htmlspecialchars($assetFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form class="form-inline mb-0" method="get">
                    <input type="hidden" name="page" value="resources">
                    <label for="resource-search" class="sr-only">Buscar</label>
                    <input
                        type="search"
                        class="form-control form-control-sm mr-2"
                        id="resource-search"
                        name="search"
                        placeholder="Buscar por nombre o etiqueta"
                        value="<?= htmlspecialchars($resourceSearchTerm ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        style="min-width: 220px;">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Buscar</button>
                    <?php if (!empty($resourceSearchTerm)): ?>
                        <a class="btn btn-sm btn-link" href="?page=resources">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#uploadModal">Subir nuevo recurso</button>
        </div>

        <?php if (!empty($resourceSearchTerm)): ?>
            <p class="text-muted">Resultados de búsqueda para “<?= htmlspecialchars($resourceSearchTerm, ENT_QUOTES, 'UTF-8') ?>”.</p>
        <?php endif; ?>

        <?php if (empty($media_data['items'])): ?>

            <p class="text-muted">No se encontraron recursos. Sube tu primer archivo.</p>

        <?php else: ?>

            <div class="row">
                <?php foreach ($media_data['items'] as $resource): ?>
                    <?php
                    $ext = strtolower(pathinfo($resource['name'], PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true);
                    $relativePath = $resource['relative'] ?? $resource['name'];
                    $resourceUrl = 'assets/' . rawurlencode($relativePath);
                    $resourceTags = $media_tags_map[$relativePath] ?? [];
                    ?>
                    <div class="col-sm-6 col-md-4 col-lg-3 mb-4">
                        <div class="card h-100 resource-card" data-resource-name="<?= htmlspecialchars($resource['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($isImage): ?>
                                <img src="assets/<?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8') ?>" class="card-img-top" style="height: 150px; object-fit: cover;" alt="<?= htmlspecialchars($resource['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light text-muted" style="height: 160px; font-size: 0.9rem;">
                                    <?= htmlspecialchars(strtoupper($ext), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <div class="px-3 pt-2">
                                <?php if (!empty($resourceTags)): ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($resourceTags as $tag): ?>
                                            <a class="badge badge-pill badge-info mb-1" href="?page=resources&search=<?= urlencode($tag) ?>" style="font-size: 0.75rem;">
                                                <?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted d-block mb-1">Sin etiquetas</small>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <small class="text-muted d-block mb-2"><?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8') ?></small>
                                <?php if (!empty($resource['size_readable'])): ?>
                                    <p class="card-text text-muted small mb-2"><?= htmlspecialchars($resource['size_readable'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer d-flex align-items-center flex-wrap" style="gap: 0.4rem 0.6rem;">
                                <?php if ($isImage): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary edit-image-btn"
                                        data-image-path="assets/<?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8') ?>"
                                        data-image-name="<?= htmlspecialchars(pathinfo($resource['name'], PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8') ?>"
                                        data-image-relative="<?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8') ?>"
                                        data-image-tags="<?= htmlspecialchars(implode(', ', $resourceTags), ENT_QUOTES, 'UTF-8') ?>"
                                        style="margin-right: 4px;"
                                    >
                                        Editar
                                    </button>
                                <?php endif; ?>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-info edit-tags-btn"
                                    data-tag-list="<?= htmlspecialchars(implode(', ', $resourceTags), ENT_QUOTES, 'UTF-8') ?>"
                                    data-tag-target="<?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8') ?>"
                                    style="margin-right: 4px;"
                                >
                                    Etiquetas
                                </button>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($resourceUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="margin-right: 4px;">Ver</a>
                                <form method="post" class="mb-0" onsubmit="return confirm('¿Seguro que deseas borrar este recurso?');">
                                    <input type="hidden" name="delete_asset" value="<?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="redirect_p" value="<?= (int) $current_page ?>">
                                    <input type="hidden" name="redirect_search" value="<?= htmlspecialchars($resourceSearchTerm, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Borrar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($tagCloud)): ?>
                <div class="mt-3 mb-4">
                    <h5>Nube de etiquetas</h5>
                    <div>
                        <?php
                        $minCount = min($tagCloud);
                        $maxCount = max($tagCloud);
                        $minSize = 0.75; // rem
                        $maxSize = 1.5; // rem
                        $range = max(1, $maxCount - $minCount);
                        foreach ($tagCloud as $tag => $count):
                            $size = $minSize + (($count - $minCount) / $range) * ($maxSize - $minSize);
                        ?>
                            <a href="?page=resources&search=<?= urlencode($tag) ?>" class="badge badge-secondary mr-2 mb-2" style="font-size: <?= htmlspecialchars(number_format($size, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>rem;">
                                <?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="form-inline mt-3">
                        <input type="hidden" name="delete_tag_global" value="1">
                        <input type="hidden" name="redirect_p" value="<?= (int) $current_page ?>">
                        <input type="hidden" name="redirect_search" value="<?= htmlspecialchars($resourceSearchTerm, ENT_QUOTES, 'UTF-8') ?>">
                        <label class="mr-2" for="delete_tag_choice">Borrar etiqueta</label>
                        <select name="delete_tag_choice" id="delete_tag_choice" class="form-control mr-2">
                            <option value="">Selecciona etiqueta</option>
                            <?php foreach (array_keys($tagCloud) as $tagOption): ?>
                                <option value="<?= htmlspecialchars($tagOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tagOption, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-danger">Borrar</button>
                    </form>
                </div>
            <?php endif; ?>

            <nav aria-label="Page navigation">

                <ul class="pagination pagination-break">

                    <?php
                    $pageGroupSize = 16;
                    for ($i = 1; $i <= $media_data['pages']; $i++): ?>

                        <li class="page-item <?= $i == $media_data['current_page'] ? 'active' : '' ?>">

                            <a class="page-link" href="?page=resources&p=<?= $i ?><?= $resourceSearchTerm !== '' ? '&search=' . urlencode($resourceSearchTerm) : '' ?>"><?= $i ?></a>

                        </li>

                        <?php if ($i % $pageGroupSize === 0 && $i < $media_data['pages']): ?>
                            <li class="page-break"></li>
                        <?php endif; ?>

                    <?php endfor; ?>

                </ul>

            </nav>

        <?php endif; ?>

    </div>

<?php endif; ?>
