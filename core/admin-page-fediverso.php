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
    $fediverseRecipients = function_exists('nammu_fediverse_message_recipients') ? nammu_fediverse_message_recipients() : [];
    $fediverseMessages = function_exists('nammu_fediverse_grouped_messages') ? nammu_fediverse_grouped_messages() : [];
    $fediverseNotifications = function_exists('nammu_fediverse_notification_entries')
        ? nammu_fediverse_notification_entries($fediverseConfig)
        : [];
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
    $fediverseHandle = static function (array $item): string {
        $username = trim((string) ($item['actor_username'] ?? ''));
        if ($username !== '') {
            $actorUrl = trim((string) ($item['actor_id'] ?? ''));
            $host = parse_url($actorUrl, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return '@' . $username . '@' . $host;
            }
            return '@' . $username;
        }
        return trim((string) ($item['actor_id'] ?? ''));
    };
    $fediverseKnownActors = function_exists('nammu_fediverse_known_actors') ? nammu_fediverse_known_actors() : [];
    $fediverseActorsById = [];
    foreach ($fediverseKnownActors as $fediverseKnownActor) {
        $fediverseKnownActorId = trim((string) ($fediverseKnownActor['id'] ?? ''));
        if ($fediverseKnownActorId !== '') {
            $fediverseActorsById[$fediverseKnownActorId] = $fediverseKnownActor;
        }
    }
    $fediverseTimelineDisplay = [];
    foreach ($fediverseTimeline as $fediverseTimelineItem) {
        $fediverseTimelineType = strtolower(trim((string) ($fediverseTimelineItem['type'] ?? '')));
        if (in_array($fediverseTimelineType, ['announce', 'like', 'delete'], true)) {
            continue;
        }
        $fediverseTimelineActorId = trim((string) ($fediverseTimelineItem['actor_id'] ?? ''));
        $fediverseTimelineActor = $fediverseTimelineActorId !== '' ? ($fediverseActorsById[$fediverseTimelineActorId] ?? null) : null;
        if (!is_array($fediverseTimelineActor) && $fediverseTimelineActorId !== '' && function_exists('nammu_fediverse_resolve_actor')) {
            $fediverseTimelineActor = nammu_fediverse_resolve_actor($fediverseTimelineActorId, $fediverseConfig);
            if (is_array($fediverseTimelineActor)) {
                $fediverseActorsById[$fediverseTimelineActorId] = $fediverseTimelineActor;
            }
        }
        if (is_array($fediverseTimelineActor)) {
            if (trim((string) ($fediverseTimelineItem['actor_name'] ?? '')) === '') {
                $fediverseTimelineItem['actor_name'] = trim((string) (($fediverseTimelineActor['name'] ?? '') ?: ($fediverseTimelineActor['preferredUsername'] ?? '')));
            }
            if (trim((string) ($fediverseTimelineItem['actor_username'] ?? '')) === '') {
                $fediverseTimelineItem['actor_username'] = trim((string) ($fediverseTimelineActor['preferredUsername'] ?? ''));
            }
            if (trim((string) ($fediverseTimelineItem['actor_icon'] ?? '')) === '') {
                $fediverseTimelineItem['actor_icon'] = trim((string) ($fediverseTimelineActor['icon'] ?? ''));
            }
            if (trim((string) ($fediverseTimelineItem['actor_url'] ?? '')) === '') {
                $fediverseTimelineItem['actor_url'] = trim((string) (($fediverseTimelineActor['url'] ?? '') ?: ($fediverseTimelineActor['id'] ?? '')));
            }
        }
        $fediverseTimelineAttachments = is_array($fediverseTimelineItem['attachments'] ?? null) ? $fediverseTimelineItem['attachments'] : [];
        if (empty($fediverseTimelineAttachments) && function_exists('nammu_fediverse_extract_html_image_urls')) {
            foreach (nammu_fediverse_extract_html_image_urls((string) ($fediverseTimelineItem['content_html'] ?? '')) as $fediverseTimelineImageUrl) {
                $fediverseTimelineAttachments[] = [
                    'type' => 'image',
                    'url' => $fediverseTimelineImageUrl,
                    'name' => '',
                    'media_type' => 'image/*',
                ];
            }
        }
        if (empty($fediverseTimelineAttachments) && trim((string) ($fediverseTimelineItem['image'] ?? '')) !== '') {
            $fediverseTimelineAttachments[] = [
                'type' => 'image',
                'url' => trim((string) $fediverseTimelineItem['image']),
                'name' => '',
                'media_type' => 'image/*',
            ];
        }
        if (trim((string) ($fediverseTimelineItem['content'] ?? '')) === '' && trim((string) ($fediverseTimelineItem['content_html'] ?? '')) !== '' && function_exists('nammu_fediverse_html_to_text')) {
            $fediverseTimelineItem['content'] = nammu_fediverse_html_to_text((string) $fediverseTimelineItem['content_html']);
        }
        $fediverseTimelineItem['attachments'] = $fediverseTimelineAttachments;
        if (trim((string) ($fediverseTimelineItem['title'] ?? '')) === '' && trim((string) ($fediverseTimelineItem['content'] ?? '')) === '' && empty($fediverseTimelineAttachments)) {
            continue;
        }
        $fediverseTimelineDisplay[] = $fediverseTimelineItem;
    }
    $sanitizeFediverseHtml = static function (string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $allowed = '<p><br><a><strong><b><em><i><span><ul><ol><li><blockquote><code><pre>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('#<a\b([^>]*)href=(["\'])(https?://[^"\']+)\2([^>]*)>#i', '<a$1href="$3"$4 target="_blank" rel="noopener">', $clean) ?? $clean;
        return trim($clean);
    };
    $notificationContext = static function (array $entry) use ($fediverseActorsById, $fediverseConfig): array {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $actorId = trim((string) ($payload['actor'] ?? ''));
        $actor = $actorId !== '' ? ($fediverseActorsById[$actorId] ?? null) : null;
        if (!is_array($actor) && $actorId !== '' && function_exists('nammu_fediverse_resolve_actor')) {
            $actor = nammu_fediverse_resolve_actor($actorId, $fediverseConfig);
        }
        $object = $payload['object'] ?? null;
        $targetUrl = '';
        if (is_string($object)) {
            $targetUrl = trim($object);
        } elseif (is_array($object)) {
            $targetUrl = trim((string) (($object['url'] ?? '') ?: ($object['id'] ?? '')));
        }
        return [
            'actor_id' => $actorId,
            'actor_name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? '') ?: $actorId)),
            'actor_username' => trim((string) ($actor['preferredUsername'] ?? '')),
            'actor_icon' => trim((string) ($actor['icon'] ?? '')),
            'target_url' => $targetUrl,
        ];
    };
    $notificationLabel = static function (array $entry) use ($notificationContext): string {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        return match ($type) {
            'follow' => 'Nuevo seguidor',
            'undo' => 'Dejó de seguir',
            'accept' => 'Accept recibido',
            'message' => 'Mensaje privado',
            'like' => 'Reaccionó a una publicación',
            'announce' => 'Compartió una publicación',
            'create' => 'Actividad remota',
            default => $type !== '' ? ucfirst($type) : 'Notificación',
        };
    };
    $notificationActor = static function (array $entry) use ($notificationContext): string {
        $context = $notificationContext($entry);
        return $context['actor_id'];
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
            <div class="card fediverse-home-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h3 class="h5 mb-0">Timeline</h3>
                        <form method="post" class="mb-0">
                            <input type="hidden" name="fediverse_tab" value="home">
                            <button type="submit" name="refresh_fediverse_timeline" class="btn btn-outline-secondary btn-sm">Refrescar ahora</button>
                        </form>
                    </div>
                    <?php if (empty($fediverseTimelineDisplay)): ?>
                        <p class="text-muted mb-0">Aún no hay publicaciones remotas recibidas. Sigue actores en la pestaña de configuración y luego refresca.</p>
                    <?php else: ?>
                        <div class="fediverse-timeline">
                            <?php foreach ($fediverseTimelineDisplay as $item): ?>
                                <article class="fediverse-status">
                                    <div class="fediverse-status__avatar">
                                        <?php if (!empty($item['actor_icon'])): ?>
                                            <img src="<?= htmlspecialchars((string) $item['actor_icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <div class="fediverse-status__avatar-fallback"><?= htmlspecialchars(mb_substr((string) (($item['actor_name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fediverse-status__body">
                                        <div class="fediverse-status__header">
                                            <div class="fediverse-status__identity">
                                                <strong><?= htmlspecialchars((string) (($item['actor_name'] ?? '') ?: 'Actor remoto'), ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span class="fediverse-status__handle"><?= htmlspecialchars($fediverseHandle($item), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div class="fediverse-status__meta">
                                                <?php if (!empty($item['published'])): ?>
                                                    <time datetime="<?= htmlspecialchars((string) $item['published'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['published'], ENT_QUOTES, 'UTF-8') ?></time>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($item['title'])): ?>
                                            <div class="fediverse-status__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php
                                        $statusHtml = $sanitizeFediverseHtml((string) ($item['content_html'] ?? ''));
                                        $statusText = trim((string) ($item['content'] ?? ''));
                                        ?>
                                        <?php if ($statusHtml !== ''): ?>
                                            <div class="fediverse-status__content fediverse-status__content--html"><?= $statusHtml ?></div>
                                        <?php elseif ($statusText !== ''): ?>
                                            <div class="fediverse-status__content"><?= nl2br(htmlspecialchars(strip_tags($statusText), ENT_QUOTES, 'UTF-8')) ?></div>
                                        <?php endif; ?>
                                        <?php $attachments = is_array($item['attachments'] ?? null) ? $item['attachments'] : []; ?>
                                        <?php if (!empty($attachments)): ?>
                                            <div class="fediverse-status__attachments">
                                                <?php foreach ($attachments as $attachment): ?>
                                                    <?php $attachmentUrl = trim((string) ($attachment['url'] ?? '')); ?>
                                                    <?php if ($attachmentUrl === '') { continue; } ?>
                                                    <?php
                                                    $attachmentType = strtolower(trim((string) ($attachment['type'] ?? '')));
                                                    $attachmentMediaType = strtolower(trim((string) ($attachment['media_type'] ?? '')));
                                                    $isImage = $attachmentType === 'image' || str_starts_with($attachmentMediaType, 'image/');
                                                    $isVideo = $attachmentType === 'video' || str_starts_with($attachmentMediaType, 'video/');
                                                    $isAudio = $attachmentType === 'audio' || str_starts_with($attachmentMediaType, 'audio/');
                                                    ?>
                                                    <?php if ($isImage): ?>
                                                        <a class="fediverse-status__media" href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                            <img src="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($attachment['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                                        </a>
                                                    <?php elseif ($isVideo): ?>
                                                        <div class="fediverse-status__media fediverse-status__media--video">
                                                            <video controls preload="metadata">
                                                                <source src="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"<?= $attachmentMediaType !== '' ? ' type="' . htmlspecialchars($attachmentMediaType, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                            </video>
                                                        </div>
                                                    <?php elseif ($isAudio): ?>
                                                        <div class="fediverse-status__file">
                                                            <div class="fediverse-status__file-name"><?= htmlspecialchars((string) (($attachment['name'] ?? '') ?: 'Audio adjunto'), ENT_QUOTES, 'UTF-8') ?></div>
                                                            <audio controls preload="none">
                                                                <source src="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"<?= $attachmentMediaType !== '' ? ' type="' . htmlspecialchars($attachmentMediaType, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                            </audio>
                                                        </div>
                                                    <?php else: ?>
                                                        <a class="fediverse-status__file" href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                            <span class="fediverse-status__file-name"><?= htmlspecialchars((string) (($attachment['name'] ?? '') ?: 'Abrir adjunto'), ENT_QUOTES, 'UTF-8') ?></span>
                                                            <span class="fediverse-status__file-meta"><?= htmlspecialchars((string) (($attachment['media_type'] ?? '') ?: strtoupper((string) ($attachment['type'] ?? 'archivo'))), ENT_QUOTES, 'UTF-8') ?></span>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="fediverse-status__footer">
                                            <a href="<?= htmlspecialchars((string) (($item['url'] ?? '') ?: ($item['id'] ?? '#')), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir publicación</a>
                                        </div>
                                    </div>
                                </article>
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
                                <?php
                                $notificationMeta = $notificationContext($entry);
                                $notificationAvatar = trim((string) ($notificationMeta['actor_icon'] ?? ''));
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

        <?php elseif ($fediverseTab === 'messages'): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="h5 mb-3">Enviar mensaje privado</h3>
                    <?php if (empty($fediverseRecipients)): ?>
                        <div class="alert alert-secondary mb-0">Necesitas al menos un actor seguido o un seguidor federado para poder enviar mensajes privados.</div>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="fediverse_tab" value="messages">
                            <div class="form-group">
                                <label for="fediverse_message_recipient">Destinatario</label>
                                <select name="fediverse_message_recipient" id="fediverse_message_recipient" class="form-control">
                                    <?php foreach ($fediverseRecipients as $recipient): ?>
                                        <?php $recipientId = (string) ($recipient['id'] ?? ''); ?>
                                        <?php
                                        $recipientLabel = (string) (($recipient['name'] ?? '') ?: ($recipient['preferredUsername'] ?? $recipientId));
                                        $recipientMeta = [];
                                        if (!empty($recipient['followed_at'])) {
                                            $recipientMeta[] = 'seguido';
                                        }
                                        if (!empty($recipient['followed_at']) && !empty($recipient['inbox']) && !empty($recipient['sharedInbox'])) {
                                            $recipientMeta[] = 'seguido';
                                        }
                                        $isFollower = false;
                                        foreach ($fediverseFollowers as $followerItem) {
                                            if ((string) ($followerItem['id'] ?? '') === $recipientId) {
                                                $isFollower = true;
                                                break;
                                            }
                                        }
                                        if ($isFollower) {
                                            $recipientMeta[] = 'seguidor';
                                        }
                                        $recipientSuffix = empty($recipientMeta) ? '' : ' (' . implode(', ', array_unique($recipientMeta)) . ')';
                                        ?>
                                        <option value="<?= htmlspecialchars($recipientId, ENT_QUOTES, 'UTF-8') ?>" <?= ($fediverseMessageRecipient ?? '') === $recipientId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($recipientLabel . $recipientSuffix, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="fediverse_message_text">Mensaje</label>
                                <textarea name="fediverse_message_text" id="fediverse_message_text" class="form-control" rows="6" placeholder="Escribe aquí el mensaje privado."><?= htmlspecialchars($fediverseMessageText ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <button type="submit" name="send_fediverse_message" class="btn btn-primary">Enviar mensaje</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 class="h5 mb-3">Conversaciones</h3>
                    <?php if (empty($fediverseMessages)): ?>
                        <p class="text-muted mb-0">Todavía no hay conversaciones guardadas.</p>
                    <?php else: ?>
                        <?php foreach ($fediverseMessages as $actorId => $messages): ?>
                            <?php $firstMessage = $messages[0] ?? []; ?>
                            <div class="border rounded p-3 mb-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div>
                                        <strong><?= htmlspecialchars((string) (($firstMessage['actor_name'] ?? '') ?: $actorId), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div class="small text-muted mt-1"><?= htmlspecialchars((string) $actorId, ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>
                                <?php foreach (array_reverse($messages) as $message): ?>
                                    <?php $isOutgoing = (($message['direction'] ?? '') === 'outgoing'); ?>
                                    <div class="mb-3 p-3 rounded" style="background: <?= $isOutgoing ? '#eef6ff' : '#f7f7f7' ?>; border-left: 4px solid <?= $isOutgoing ? '#1b8eed' : '#999' ?>;">
                                        <div class="small text-muted mb-2">
                                            <?= $isOutgoing ? 'Enviado' : 'Recibido' ?> · <?= htmlspecialchars((string) ($message['published'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($message['delivery_status'])): ?>
                                                · <?= htmlspecialchars((string) ($message['delivery_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                            <?php if (!$isOutgoing && array_key_exists('verified', $message)): ?>
                                                · <?= !empty($message['verified']) ? 'verificado' : 'no verificado' ?>
                                            <?php endif; ?>
                                        </div>
                                        <div><?= nl2br(htmlspecialchars((string) ($message['content'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
    <style>
        .fediverse-timeline {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .fediverse-status {
            display: grid;
            grid-template-columns: 56px minmax(0, 1fr);
            gap: 0.9rem;
            padding: 1rem 0;
            border-top: 1px solid #e8edf3;
        }
        .fediverse-status:first-child {
            border-top: 0;
            padding-top: 0;
        }
        .fediverse-status__avatar img,
        .fediverse-status__avatar-fallback {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            display: block;
            object-fit: cover;
        }
        .fediverse-status__avatar-fallback {
            background: #dfe9f6;
            color: #244564;
            font-weight: 700;
            font-size: 1.2rem;
            line-height: 56px;
            text-align: center;
        }
        .fediverse-status__body {
            min-width: 0;
        }
        .fediverse-status__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.4rem;
        }
        .fediverse-status__identity {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: baseline;
            min-width: 0;
        }
        .fediverse-status__handle,
        .fediverse-status__meta {
            color: #6c757d;
            font-size: 0.92rem;
        }
        .fediverse-status__title {
            font-weight: 700;
            margin-bottom: 0.45rem;
        }
        .fediverse-status__content {
            color: #1f2933;
            line-height: 1.55;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        .fediverse-status__content--html p:last-child,
        .fediverse-status__content--html ul:last-child,
        .fediverse-status__content--html ol:last-child,
        .fediverse-status__content--html blockquote:last-child,
        .fediverse-status__content--html pre:last-child {
            margin-bottom: 0;
        }
        .fediverse-status__content--html a {
            text-decoration: underline;
        }
        .fediverse-status__attachments {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-top: 0.85rem;
        }
        .fediverse-status__media {
            display: block;
            border-radius: 14px;
            overflow: hidden;
            background: #eef3f8;
        }
        .fediverse-status__media img {
            display: block;
            width: 100%;
            height: 220px;
            object-fit: cover;
        }
        .fediverse-status__media--video video {
            display: block;
            width: 100%;
            max-height: 360px;
            background: #000;
        }
        .fediverse-status__file {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            justify-content: center;
            min-height: 104px;
            padding: 0.95rem 1rem;
            border-radius: 14px;
            background: #f3f7fb;
            color: inherit;
            text-decoration: none;
        }
        .fediverse-status__file audio {
            width: 100%;
        }
        .fediverse-status__file-name {
            font-weight: 600;
        }
        .fediverse-status__file-meta {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .fediverse-status__footer {
            margin-top: 0.85rem;
        }
        .fediverse-notification {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr);
            gap: 0.8rem;
            align-items: start;
        }
        .fediverse-notification__avatar img,
        .fediverse-notification__avatar-fallback {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            display: block;
            object-fit: cover;
        }
        .fediverse-notification__avatar-fallback {
            background: #e7eef6;
            color: #294d6d;
            font-weight: 700;
            line-height: 44px;
            text-align: center;
        }
        @media (max-width: 640px) {
            .fediverse-status {
                grid-template-columns: 44px minmax(0, 1fr);
                gap: 0.75rem;
            }
            .fediverse-status__avatar img,
            .fediverse-status__avatar-fallback {
                width: 44px;
                height: 44px;
                line-height: 44px;
            }
            .fediverse-status__media img {
                height: 180px;
            }
            .fediverse-status__header {
                flex-direction: column;
                gap: 0.2rem;
            }
        }
    </style>
<?php endif; ?>
