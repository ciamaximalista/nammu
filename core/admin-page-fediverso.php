<?php if ($page === 'fediverso'): ?>
    <?php
    if (!function_exists('nammu_fediverse_actor_url')) {
        require_once __DIR__ . '/fediverso.php';
    }
    $fediverseConfig = load_config_file();
    $fediverseBaseUrl = nammu_fediverse_base_url($fediverseConfig);
    $fediverseActorUrl = nammu_fediverse_actor_url($fediverseConfig);
    $fediverseAcct = nammu_fediverse_acct_uri($fediverseConfig);
    $fediverseFollowing = nammu_fediverse_following_store()['actors'];
    $fediverseTimeline = nammu_fediverse_timeline_store()['items'];
    $fediverseFollowers = function_exists('nammu_fediverse_followers_store') ? nammu_fediverse_followers_store()['followers'] : [];
    $fediverseInboxStore = function_exists('nammu_fediverse_load_json_store')
        ? nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []])
        : ['activities' => []];
    $fediverseNotifications = array_values(array_reverse(is_array($fediverseInboxStore['activities'] ?? null) ? $fediverseInboxStore['activities'] : []));
    $fediverseTab = strtolower(trim((string) ($_GET['tab'] ?? 'home')));
    if (!in_array($fediverseTab, ['home', 'notifications', 'messages', 'settings'], true)) {
        $fediverseTab = 'home';
    }
    $fediverseTabs = [
        'home' => 'Inicio',
        'notifications' => 'Notificaciones',
        'messages' => 'Mensajes',
        'settings' => 'Configuración',
    ];
    $buildTabUrl = static function (string $tab): string {
        return 'admin.php?page=fediverso&tab=' . rawurlencode($tab);
    };
    $notificationLabel = static function (array $entry): string {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        return match ($type) {
            'follow' => 'Nuevo seguidor',
            'undo' => 'Dejó de seguir',
            'accept' => 'Accept recibido',
            'create' => 'Actividad remota',
            default => $type !== '' ? ucfirst($type) : 'Notificación',
        };
    };
    $notificationActor = static function (array $entry): string {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        return trim((string) ($payload['actor'] ?? ''));
    };
    ?>
    <div class="tab-pane active">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div>
                <h2 class="mb-1">Fediverso</h2>
                <p class="text-muted mb-0">Panel de ActivityPub para seguir actores, revisar el inbox federado del blog y preparar mensajería privada.</p>
            </div>
        </div>

        <?php if (!empty($fediverseFeedback)): ?>
            <div class="alert alert-<?= htmlspecialchars($fediverseFeedback['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($fediverseFeedback['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4">
            <?php foreach ($fediverseTabs as $tabKey => $tabLabel): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $fediverseTab === $tabKey ? 'active' : '' ?>" href="<?= htmlspecialchars($buildTabUrl($tabKey), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($fediverseTab === 'home'): ?>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h3 class="h5 mb-0">Timeline</h3>
                        <form method="post" class="mb-0">
                            <input type="hidden" name="fediverse_tab" value="home">
                            <button type="submit" name="refresh_fediverse_timeline" class="btn btn-outline-secondary btn-sm">Refrescar ahora</button>
                        </form>
                    </div>
                    <?php if (empty($fediverseTimeline)): ?>
                        <p class="text-muted mb-0">Aún no hay publicaciones remotas recibidas. Sigue actores en la pestaña de configuración y luego refresca.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($fediverseTimeline as $item): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div class="pr-3">
                                            <strong><?= htmlspecialchars((string) (($item['actor_name'] ?? '') ?: 'Actor remoto'), ENT_QUOTES, 'UTF-8') ?></strong>
                                            <?php if (!empty($item['published'])): ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars((string) $item['published'], ENT_QUOTES, 'UTF-8') ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge badge-light text-uppercase"><?= htmlspecialchars((string) ($item['type'] ?? 'note'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <?php if (!empty($item['title'])): ?>
                                        <div class="mt-2 font-weight-bold"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['content'])): ?>
                                        <div class="mt-2 small text-body"><?= nl2br(htmlspecialchars(strip_tags((string) $item['content']), ENT_QUOTES, 'UTF-8')) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['image'])): ?>
                                        <div class="mt-3">
                                            <img src="<?= htmlspecialchars((string) $item['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="max-width: 240px; height: auto; border-radius: 10px;">
                                        </div>
                                    <?php endif; ?>
                                    <div class="mt-3">
                                        <a href="<?= htmlspecialchars((string) (($item['url'] ?? '') ?: ($item['id'] ?? '#')), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir publicación</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($fediverseTab === 'notifications'): ?>
            <div class="card">
                <div class="card-body">
                    <h3 class="h5 mb-3">Notificaciones</h3>
                    <?php if (empty($fediverseNotifications)): ?>
                        <p class="text-muted mb-0">Aún no hay notificaciones ActivityPub registradas en el inbox del blog.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($fediverseNotifications as $entry): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <strong><?= htmlspecialchars($notificationLabel($entry), ENT_QUOTES, 'UTF-8') ?></strong>
                                            <?php $actorValue = $notificationActor($entry); ?>
                                            <?php if ($actorValue !== ''): ?>
                                                <div class="small text-muted mt-1"><?= htmlspecialchars($actorValue, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars((string) ($entry['received_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($fediverseTab === 'messages'): ?>
            <div class="card">
                <div class="card-body">
                    <h3 class="h5 mb-3">Mensajes privados</h3>
                    <p class="text-muted">Aquí irá la mensajería privada con otros actores del Fediverso. La estructura queda ya preparada como subpestaña independiente para no mezclarla con el timeline público.</p>
                    <div class="alert alert-secondary mb-0">
                        Próximo paso previsto: selector de destinatario, redacción del mensaje, bandeja de entrada privada y conversaciones.
                    </div>
                </div>
            </div>

        <?php elseif ($fediverseTab === 'settings'): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="h5 mb-3">Actor del blog</h3>
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label class="font-weight-bold d-block mb-1">Cuenta ActivityPub</label>
                            <code><?= htmlspecialchars($fediverseAcct, ENT_QUOTES, 'UTF-8') ?></code>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label class="font-weight-bold d-block mb-1">Actor URL</label>
                            <a href="<?= htmlspecialchars($fediverseActorUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($fediverseActorUrl, ENT_QUOTES, 'UTF-8') ?></a>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label class="font-weight-bold d-block mb-1">WebFinger</label>
                            <code><?= htmlspecialchars($fediverseBaseUrl . '/.well-known/webfinger?resource=' . rawurlencode($fediverseAcct), ENT_QUOTES, 'UTF-8') ?></code>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label class="font-weight-bold d-block mb-1">Outbox</label>
                            <a href="<?= htmlspecialchars(nammu_fediverse_outbox_url($fediverseConfig), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars(nammu_fediverse_outbox_url($fediverseConfig), ENT_QUOTES, 'UTF-8') ?></a>
                        </div>
                        <div class="col-lg-6 mb-0">
                            <label class="font-weight-bold d-block mb-1">Seguidores federados</label>
                            <strong><?= (int) count($fediverseFollowers) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" class="mb-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="h5 mb-3">Seguir un actor</h3>
                        <div class="form-group">
                            <label for="fediverse_actor_input">Cuenta o URL del actor</label>
                            <input type="text" id="fediverse_actor_input" name="fediverse_actor_input" class="form-control" placeholder="@usuario@servidor.tld o https://servidor.tld/users/usuario" value="<?= htmlspecialchars($fediverseActorInput ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <small class="form-text text-muted">Nammu intentará resolver primero WebFinger si escribes una cuenta y, si pegas una URL, leerá el actor directamente.</small>
                        </div>
                        <input type="hidden" name="fediverse_tab" value="settings">
                        <button type="submit" name="follow_fediverse_actor" class="btn btn-primary">Seguir actor</button>
                        <button type="submit" name="refresh_fediverse_timeline" class="btn btn-outline-secondary ml-2">Refrescar ahora</button>
                    </div>
                </div>
            </form>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="h5 mb-3">Actores seguidos</h3>
                    <?php if (empty($fediverseFollowing)): ?>
                        <p class="text-muted mb-0">Todavía no sigues ningún actor.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Actor</th>
                                        <th>Outbox</th>
                                        <th>Última revisión</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fediverseFollowing as $actor): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? 'Actor')), ENT_QUOTES, 'UTF-8') ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars((string) ($actor['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                                <?php if (!empty($actor['last_error'])): ?>
                                                    <div class="small text-danger mt-1"><?= htmlspecialchars((string) $actor['last_error'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><a href="<?= htmlspecialchars((string) ($actor['outbox'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($actor['outbox'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a></small></td>
                                            <td><small><?= htmlspecialchars((string) (($actor['last_checked_at'] ?? '') ?: 'Nunca'), ENT_QUOTES, 'UTF-8') ?></small></td>
                                            <td class="text-right">
                                                <form method="post" onsubmit="return confirm('¿Dejar de seguir este actor?');">
                                                    <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars((string) ($actor['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="fediverse_tab" value="settings">
                                                    <button type="submit" name="unfollow_fediverse_actor" class="btn btn-outline-danger btn-sm">Quitar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 class="h5 mb-3">Seguidores</h3>
                    <?php if (empty($fediverseFollowers)): ?>
                        <p class="text-muted mb-0">Todavía nadie sigue este actor federado.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($fediverseFollowers as $follower): ?>
                                <div class="list-group-item">
                                    <strong><?= htmlspecialchars((string) (($follower['name'] ?? '') ?: ($follower['preferredUsername'] ?? 'Actor remoto')), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars((string) ($follower['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($follower['followed_at'])): ?>
                                        <div class="small text-muted mt-1">Desde <?= htmlspecialchars((string) $follower['followed_at'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
