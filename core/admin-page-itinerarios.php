<?php if ($page === 'itinerarios'): ?>

    <div class="tab-pane active">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <h2 class="mb-0">Itinerarios</h2>
            <div class="btn-group">
                <a class="btn btn-outline-secondary" href="?page=itinerarios">Refrescar</a>
                <a class="btn btn-primary" href="?page=itinerario&new=1">Nuevo itinerario</a>
            </div>
        </div>

        <?php if ($itineraryFeedback): ?>
            <div class="alert alert-<?= htmlspecialchars($itineraryFeedback['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($itineraryFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h3 class="h5 mb-3">Listado de itinerarios</h3>
                <?php
                    $networkConfigs = [
                        'telegram' => $settings['telegram'] ?? [],
                        'whatsapp' => $settings['whatsapp'] ?? [],
                        'facebook' => $settings['facebook'] ?? [],
                        'twitter' => $settings['twitter'] ?? [],
                        'instagram' => $settings['instagram'] ?? [],
                    ];
                    $networkLabels = [
                        'telegram' => 'Telegram',
                        'whatsapp' => 'WhatsApp',
                        'facebook' => 'Facebook',
                        'twitter' => 'X',
                        'instagram' => 'Instagram',
                    ];
                    $availableNetworks = [];
                    foreach ($networkConfigs as $key => $cfg) {
                        if (function_exists('admin_is_social_network_configured') && admin_is_social_network_configured($key, $cfg)) {
                            $availableNetworks[] = $key;
                        }
                    }
                ?>
                <?php if (empty($itinerariesList)): ?>
                    <p class="text-muted mb-0">Todavía no hay itinerarios registrados.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="width:80px;">Orden</th>
                                    <th>Título</th>
                                    <th>Descripción</th>
                                    <th>Temas</th>
                                    <th>Estado</th>
                                    <th>Slug público</th>
                                    <th class="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itinerariesList as $itineraryItem): ?>
                                    <?php
                                        $rawStats = admin_get_itinerary_stats($itineraryItem);
                                        $topicStatsList = array_values($rawStats['topics'] ?? []);
                                        usort($topicStatsList, static function (array $a, array $b): int {
                                            return (int) ($a['number'] ?? 0) <=> (int) ($b['number'] ?? 0);
                                        });
                                        $presentationReaders = (int) ($rawStats['started'] ?? 0);
                                        $topicOneStarters = 0;
                                        foreach ($topicStatsList as $topicStat) {
                                            $topicNumber = (int) ($topicStat['number'] ?? 0);
                                            if ($topicNumber === 1) {
                                                $topicOneStarters = (int) ($topicStat['count'] ?? 0);
                                                break;
                                            }
                                        }
                                        if ($topicOneStarters === 0 && !empty($topicStatsList)) {
                                            $topicOneStarters = (int) ($topicStatsList[0]['count'] ?? 0);
                                        }
                                        $statsPayload = [
                                            'started' => $topicOneStarters,
                                            'presentation_readers' => $presentationReaders,
                                            'topics' => array_map(static function (array $topic): array {
                                                return [
                                                    'number' => (int) ($topic['number'] ?? 0),
                                                    'title' => $topic['title'] ?? ($topic['slug'] ?? ''),
                                                    'count' => (int) ($topic['count'] ?? 0),
                                                ];
                                            }, $topicStatsList),
                                        ];
                                        $statsJson = htmlspecialchars(
                                            json_encode($statsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>
                                    <?php $itineraryStatus = method_exists($itineraryItem, 'getStatus') ? $itineraryItem->getStatus() : 'published'; ?>
                                    <tr>
                                        <td class="text-nowrap">
                                            <div class="btn-group-vertical btn-group-sm" role="group" aria-label="Mover itinerario">
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="reorder_itinerary" value="1">
                                                    <input type="hidden" name="itinerary_slug" value="<?= htmlspecialchars($itineraryItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="direction" value="up">
                                                    <button type="submit" class="btn btn-outline-secondary" <?= $itinerariesList[0]->getSlug() === $itineraryItem->getSlug() ? 'disabled' : '' ?> title="Subir">▲</button>
                                                </form>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="reorder_itinerary" value="1">
                                                    <input type="hidden" name="itinerary_slug" value="<?= htmlspecialchars($itineraryItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="direction" value="down">
                                                    <button type="submit" class="btn btn-outline-secondary" <?= $itinerariesList[array_key_last($itinerariesList)]->getSlug() === $itineraryItem->getSlug() ? 'disabled' : '' ?> title="Bajar">▼</button>
                                                </form>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($itineraryItem->getTitle(), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($itineraryItem->getDescription(), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= $itineraryItem->getTopicCount() ?></td>
                                        <td>
                                            <?php if ($itineraryStatus === 'draft'): ?>
                                                <span class="badge badge-secondary">Borrador</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Publicado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= htmlspecialchars(admin_public_itinerary_url($itineraryItem->getSlug()), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                /itinerarios/<?= htmlspecialchars($itineraryItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                        <td class="text-right">
                                            <div class="text-right">
                                                <div class="mb-2">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-info mb-2"
                                                        data-toggle="modal"
                                                        data-target="#itineraryStatsModal"
                                                        data-itinerary-title="<?= htmlspecialchars($itineraryItem->getTitle(), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-itinerary-slug="<?= htmlspecialchars($itineraryItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-itinerary-stats="<?= $statsJson ?>"
                                                    >Estadísticas</button>
                                                </div>
                                                <?php if (!empty($availableNetworks)): ?>
                                                    <?php foreach ($availableNetworks as $networkKey): ?>
                                                        <form method="post" class="d-inline-block mb-2">
                                                            <input type="hidden" name="social_network" value="<?= htmlspecialchars($networkKey, ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="itinerary_slug" value="<?= htmlspecialchars($itineraryItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="submit" name="send_social_itinerary" class="btn btn-sm btn-outline-primary">
                                                                <?= htmlspecialchars($networkLabels[$networkKey] ?? ucfirst($networkKey), ENT_QUOTES, 'UTF-8') ?>
                                                            </button>
                                                        </form>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <div class="mb-2">
                                                    <a class="btn btn-sm btn-outline-primary" href="?page=itinerario&itinerary=<?= urlencode($itineraryItem->getSlug()) ?>#itinerary-form">Editar</a>
                                                </div>
                                                <form method="post" class="d-inline-block" onsubmit="return confirm('¿Seguro que deseas borrar este itinerario? Esta acción eliminará todos sus temas.');">
                                                    <input type="hidden" name="delete_itinerary_slug" value="<?= htmlspecialchars($itineraryItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" name="delete_itinerary" class="btn btn-sm btn-outline-danger mt-1">Borrar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php endif; ?>
