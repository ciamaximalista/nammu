<?php
/**
 * Nammu — panel de administración. Fediverso: pestaña Notificaciones.
 * Lo incluye core/admin-page-fediverso.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
?>
<div class="card">
    <div class="card-body">
        <h3 class="h5 mb-3">Notificaciones</h3>
        <?php if (empty($fediverseNotifications)): ?>
            <p class="text-muted mb-0">Aún no hay notificaciones ActivityPub registradas en el inbox del blog.</p>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($fediverseNotifications as $entry): ?>
                    <?php
                    $notificationMeta = $notificationContext($entry);
                    $notificationAvatar = $fediverseValidAvatarUrl(trim((string) ($notificationMeta['actor_icon'] ?? '')));
                    $notificationActorName = trim((string) ($notificationMeta['actor_name'] ?? ''));
                    $notificationTargetUrl = trim((string) ($notificationMeta['target_url'] ?? ''));
                    ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="fediverse-notification">
                                    <div class="fediverse-notification__avatar">
                                        <?php if ($notificationAvatar !== ''): ?>
                                            <img src="<?= htmlspecialchars($notificationAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <div class="fediverse-notification__avatar-fallback"><?= htmlspecialchars(mb_substr($notificationActorName !== '' ? $notificationActorName : 'A', 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fediverse-notification__body">
                                        <strong><?= htmlspecialchars($notificationLabel($entry), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ($notificationActorName !== ''): ?>
                                            <div class="small text-muted mt-1"><?= htmlspecialchars($notificationActorName, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php if ($notificationTargetUrl !== ''): ?>
                                            <div class="small mt-1"><a href="<?= htmlspecialchars($notificationTargetUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Ver publicación afectada</a></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php $actorValue = $notificationActor($entry); ?>
                                <?php if ($actorValue !== ''): ?>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($actorValue, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                                <?php if (array_key_exists('verified', $entry)): ?>
                                    <div class="small mt-1 <?= !empty($entry['verified']) ? 'text-success' : 'text-danger' ?>">
                                        <?= !empty($entry['verified']) ? 'Firma verificada' : 'Firma no verificada' ?>
                                        <?php if (empty($entry['verified']) && !empty($entry['verification_error'])): ?>
                                            · <?= htmlspecialchars((string) $entry['verification_error'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($entry['signature_key_id'])): ?>
                                        <div class="small text-muted mt-1">keyId: <?= htmlspecialchars((string) $entry['signature_key_id'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['signed_headers'])): ?>
                                        <div class="small text-muted mt-1">headers: <?= htmlspecialchars((string) $entry['signed_headers'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
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
