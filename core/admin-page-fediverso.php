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
    ?>
    <div class="tab-pane active">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div>
                <h2 class="mb-1">Fediverso</h2>
                <p class="text-muted mb-0">Sigue actores ActivityPub y consulta sus actualizaciones desde Nammu. Esta primera fase usa lectura pública del outbox remoto y expone el actor del blog.</p>
            </div>
        </div>

        <?php if (!empty($fediverseFeedback)): ?>
            <div class="alert alert-<?= htmlspecialchars($fediverseFeedback['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($fediverseFeedback['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

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
                <h3 class="h5 mb-3">Actualizaciones recibidas</h3>
                <?php if (empty($fediverseTimeline)): ?>
                    <p class="text-muted mb-0">Aún no hay actividades remotas guardadas.</p>
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
    </div>
<?php endif; ?>
