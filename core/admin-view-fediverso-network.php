<?php
/**
 * Nammu — panel de administración. Fediverso: pestaña Red (seguir actores, seguidores y bloqueos).
 * Lo incluye core/admin-page-fediverso.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
?>
<form method="post" class="mb-4">
    <div class="card">
        <div class="card-body">
            <h3 class="h5 mb-3">Seguir un actor</h3>
            <div class="form-group">
                <label for="fediverse_actor_input">Cuenta o URL del actor</label>
                <input type="text" id="fediverse_actor_input" name="fediverse_actor_input" class="form-control" placeholder="@usuario@servidor.tld o https://servidor.tld/users/usuario" value="<?= htmlspecialchars($fediverseActorInput ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <small class="form-text text-muted">Nammu intentará resolver primero WebFinger si escribes una cuenta y, si pegas una URL, leerá el actor directamente.</small>
            </div>
            <input type="hidden" name="fediverse_tab" value="network">
            <button type="submit" name="follow_fediverse_actor" class="btn btn-primary">Seguir actor</button>
            <button type="submit" name="refresh_fediverse_timeline" class="btn btn-outline-secondary ml-2">Refrescar ahora</button>
            <button type="submit" name="refresh_fediverse_threads" class="btn btn-outline-secondary ml-2">Actualizar hilos</button>
        </div>
    </div>
</form>

<form method="post" class="mb-4">
    <div class="card border-info">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between" style="gap:0.75rem;">
            <div>
                <h3 class="h5 mb-1">Avatares de la red</h3>
                <p class="text-muted mb-0">Consulta de nuevo los servidores de todos los seguidos y seguidores y guarda copias locales de sus avatares.</p>
            </div>
            <div>
                <input type="hidden" name="fediverse_tab" value="network">
                <button type="submit" name="recache_all_fediverse_actor_avatars" class="btn btn-info" onclick="return confirm('¿Recachear desde cero los avatares de todos los seguidos y seguidores?');">Recachear todos los avatares</button>
            </div>
        </div>
    </div>
</form>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="h5 mb-3">Actores seguidos (<?= count($fediverseFollowing) ?>)</h3>
                <?php if (empty($fediverseFollowing)): ?>
                    <p class="text-muted mb-0">Todavía no sigues ningún actor.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($fediverseFollowing as $actor): ?>
                            <?php
                            $actorName = trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? 'Actor')));
                            $actorId = trim((string) ($actor['id'] ?? ''));
                            $actorIcon = $fediverseValidAvatarUrl(trim((string) ($actor['icon'] ?? '')));
                            if ($actorIcon === '') {
                                $actorIcon = $fediverseValidAvatarUrl(trim((string) ($actor['avatar_remote_url'] ?? '')));
                            }
                            $actorUsername = trim((string) ($actor['preferredUsername'] ?? ''));
                            $actorHost = is_string(parse_url($actorId, PHP_URL_HOST)) ? (string) parse_url($actorId, PHP_URL_HOST) : '';
                            $actorHandle = $actorUsername !== '' ? '@' . $actorUsername . ($actorHost !== '' ? '@' . $actorHost : '') : $actorId;
                            ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex align-items-center justify-content-between" style="gap:0.75rem;">
                                    <div class="d-flex align-items-center" style="gap:0.75rem; min-width:0;">
                                        <?php if ($actorIcon !== ''): ?>
                                            <img src="<?= htmlspecialchars($actorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:40px;height:40px;border-radius:999px;object-fit:cover;flex:0 0 40px;">
                                        <?php else: ?>
                                            <div style="width:40px;height:40px;border-radius:999px;background:#e9ecef;flex:0 0 40px;"></div>
                                        <?php endif; ?>
                                        <div style="min-width:0;">
                                            <div class="font-weight-bold text-truncate"><?= htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted text-truncate"><?= htmlspecialchars($actorHandle, ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                    </div>
                                    <?php if ($actorId !== ''): ?>
                                        <div class="d-flex align-items-center" style="gap:0.5rem;">
                                            <form method="post">
                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($actorId, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="fediverse_tab" value="network">
                                                <button type="submit" name="recache_fediverse_actor_avatar" class="btn btn-outline-secondary btn-sm">Recachear avatar</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('¿Dejar de seguir este actor?');">
                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($actorId, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="fediverse_tab" value="network">
                                                <button type="submit" name="unfollow_fediverse_actor" class="btn btn-outline-danger btn-sm">Dejar de seguir</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="h5 mb-3">Seguidores (<?= count($fediverseFollowers) ?>)</h3>
                <?php if (empty($fediverseFollowers)): ?>
                    <p class="text-muted mb-0">Todavía nadie sigue este actor federado.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($fediverseFollowers as $follower): ?>
                            <?php
                            $followerName = trim((string) (($follower['name'] ?? '') ?: ($follower['preferredUsername'] ?? 'Actor remoto')));
                            $followerId = trim((string) ($follower['id'] ?? ''));
                            $followerIcon = $fediverseValidAvatarUrl(trim((string) ($follower['icon'] ?? '')));
                            if ($followerIcon === '') {
                                $followerIcon = $fediverseValidAvatarUrl(trim((string) ($follower['avatar_remote_url'] ?? '')));
                            }
                            $followerUsername = trim((string) ($follower['preferredUsername'] ?? ''));
                            $followerHost = is_string(parse_url($followerId, PHP_URL_HOST)) ? (string) parse_url($followerId, PHP_URL_HOST) : '';
                            $followerHandle = $followerUsername !== '' ? '@' . $followerUsername . ($followerHost !== '' ? '@' . $followerHost : '') : $followerId;
                            $isMutualFollow = $followerId !== '' && isset($fediverseFollowingIds[$followerId]);
                            ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex align-items-center justify-content-between" style="gap:0.75rem;">
                                    <div class="d-flex align-items-center" style="gap:0.75rem; min-width:0;">
                                        <?php if ($followerIcon !== ''): ?>
                                            <img src="<?= htmlspecialchars($followerIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:40px;height:40px;border-radius:999px;object-fit:cover;flex:0 0 40px;">
                                        <?php else: ?>
                                            <div style="width:40px;height:40px;border-radius:999px;background:#e9ecef;flex:0 0 40px;"></div>
                                        <?php endif; ?>
                                        <div style="min-width:0;">
                                            <div class="font-weight-bold text-truncate"><?= htmlspecialchars($followerName, ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted text-truncate"><?= htmlspecialchars($followerHandle, ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                    </div>
                                    <?php if ($followerId !== ''): ?>
                                        <div class="d-flex align-items-center" style="gap:0.5rem;">
                                            <form method="post">
                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($followerId, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="fediverse_tab" value="network">
                                                <button type="submit" name="recache_fediverse_actor_avatar" class="btn btn-outline-secondary btn-sm">Recachear avatar</button>
                                            </form>
                                            <?php if ($isMutualFollow): ?>
                                                <form method="post" onsubmit="return confirm('¿Dejar de seguir este actor?');">
                                                    <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($followerId, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="fediverse_tab" value="network">
                                                    <button type="submit" name="unfollow_fediverse_actor" class="btn btn-outline-danger btn-sm">Dejar de seguir</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post">
                                                    <input type="hidden" name="fediverse_actor_input" value="<?= htmlspecialchars($followerId, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="fediverse_tab" value="network">
                                                    <button type="submit" name="follow_fediverse_actor" class="btn btn-outline-primary btn-sm">Seguir</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" onsubmit="return confirm('¿Bloquear este seguidor? No recibirá actualizaciones futuras.');">
                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($followerId, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="fediverse_tab" value="network">
                                                <button type="submit" name="block_fediverse_follower" class="btn btn-outline-dark btn-sm">Bloquear</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h3 class="h5 mb-3">Bloqueados</h3>
        <?php if (empty($fediverseBlocked)): ?>
            <p class="text-muted mb-0">No hay actores bloqueados.</p>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($fediverseBlocked as $blockedActor): ?>
                    <?php
                    $blockedName = trim((string) (($blockedActor['name'] ?? '') ?: ($blockedActor['preferredUsername'] ?? 'Actor remoto')));
                    $blockedId = trim((string) ($blockedActor['id'] ?? ''));
                    $blockedIcon = $fediverseValidAvatarUrl(trim((string) ($blockedActor['icon'] ?? '')));
                    if ($blockedIcon === '') {
                        $blockedIcon = $fediverseValidAvatarUrl(trim((string) ($blockedActor['avatar_remote_url'] ?? '')));
                    }
                    $blockedUsername = trim((string) ($blockedActor['preferredUsername'] ?? ''));
                    $blockedHost = is_string(parse_url($blockedId, PHP_URL_HOST)) ? (string) parse_url($blockedId, PHP_URL_HOST) : '';
                    $blockedHandle = $blockedUsername !== '' ? '@' . $blockedUsername . ($blockedHost !== '' ? '@' . $blockedHost : '') : $blockedId;
                    ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex align-items-center justify-content-between" style="gap:0.75rem;">
                            <div class="d-flex align-items-center" style="gap:0.75rem; min-width:0;">
                                <?php if ($blockedIcon !== ''): ?>
                                    <img src="<?= htmlspecialchars($blockedIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:40px;height:40px;border-radius:999px;object-fit:cover;flex:0 0 40px;">
                                <?php else: ?>
                                    <div style="width:40px;height:40px;border-radius:999px;background:#e9ecef;flex:0 0 40px;"></div>
                                <?php endif; ?>
                                <div style="min-width:0;">
                                    <div class="font-weight-bold text-truncate"><?= htmlspecialchars($blockedName, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="small text-muted text-truncate"><?= htmlspecialchars($blockedHandle, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>
                            <?php if ($blockedId !== ''): ?>
                                <form method="post">
                                    <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($blockedId, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_tab" value="network">
                                    <button type="submit" name="unblock_fediverse_actor" class="btn btn-outline-secondary btn-sm">Desbloquear</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
