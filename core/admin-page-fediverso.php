<?php if ($page === 'fediverso'): ?>
    <?php
    if (!function_exists('nammu_fediverse_actor_url')) {
        require_once __DIR__ . '/fediverso.php';
    }
    $fediverseConfig = load_config_file();
    $fediverseBaseUrl = nammu_fediverse_base_url($fediverseConfig);
    $fediverseActorUrl = nammu_fediverse_actor_url($fediverseConfig);
    $fediverseAcct = nammu_fediverse_acct_uri($fediverseConfig);
    $fediverseLocalName = trim((string) (($fediverseConfig['site_name'] ?? '') ?: ($siteTitle ?? 'Blog')));
    $fediverseLocalAvatar = function_exists('nammu_fediverse_avatar_url') ? nammu_fediverse_avatar_url($fediverseConfig) : '';
    $fediverseLocalHandle = '';
    if (str_starts_with($fediverseAcct, 'acct:')) {
        $fediverseLocalHandle = '@' . substr($fediverseAcct, 5);
    } else {
        $fediverseLocalHandle = trim((string) $fediverseAcct);
    }
    if ($fediverseLocalHandle === '') {
        $fediverseLocalHost = parse_url($fediverseBaseUrl, PHP_URL_HOST);
        $fediverseLocalUsername = function_exists('nammu_fediverse_preferred_username')
            ? trim((string) nammu_fediverse_preferred_username($fediverseConfig))
            : 'blog';
        $fediverseLocalHandle = '@' . $fediverseLocalUsername . ($fediverseLocalHost ? '@' . $fediverseLocalHost : '');
    }
    $fediverseTab = strtolower(trim((string) ($_GET['tab'] ?? 'home')));
    if (!in_array($fediverseTab, ['home', 'notifications', 'messages', 'network', 'settings'], true)) {
        $fediverseTab = 'home';
    }
    $fediverseTabs = [
        'home' => 'Inicio',
        'notifications' => 'Notificaciones',
        'messages' => 'Mensajes',
        'network' => 'Red',
        'settings' => 'Configuración',
    ];
    $isFediverseHomeTab = $fediverseTab === 'home';
    $isFediverseNotificationsTab = $fediverseTab === 'notifications';
    $isFediverseMessagesTab = $fediverseTab === 'messages';
    $isFediverseNetworkTab = $fediverseTab === 'network';
    $isFediverseSettingsTab = $fediverseTab === 'settings';

    $fediverseFollowing = nammu_fediverse_following_store()['actors'];
    $fediverseFollowingIds = [];
    foreach ($fediverseFollowing as $fediverseFollowingActor) {
        $fediverseFollowingActorId = trim((string) ($fediverseFollowingActor['id'] ?? ''));
        if ($fediverseFollowingActorId !== '') {
            $fediverseFollowingIds[$fediverseFollowingActorId] = true;
        }
    }
    $fediverseFollowers = function_exists('nammu_fediverse_followers_store') ? nammu_fediverse_followers_store()['followers'] : [];
    $fediverseFollowerIds = [];
    foreach ($fediverseFollowers as $fediverseFollowerActor) {
        $fediverseFollowerActorId = trim((string) ($fediverseFollowerActor['id'] ?? ''));
        if ($fediverseFollowerActorId !== '') {
            $fediverseFollowerIds[$fediverseFollowerActorId] = true;
        }
    }
    $fediverseTimeline = $isFediverseHomeTab ? nammu_fediverse_timeline_store()['items'] : [];
    $fediverseRecipients = ($isFediverseMessagesTab || $isFediverseNetworkTab)
        && function_exists('nammu_fediverse_message_recipients')
        ? nammu_fediverse_message_recipients()
        : [];
    $fediverseMessages = $isFediverseMessagesTab && function_exists('nammu_fediverse_grouped_messages')
        ? nammu_fediverse_grouped_messages()
        : [];
    $fediversePublicReplyMessages = $isFediverseMessagesTab && function_exists('nammu_fediverse_public_reply_message_entries')
        ? nammu_fediverse_public_reply_message_entries($fediverseConfig)
        : [];
    $fediverseOutgoingPublicReplyMessages = $isFediverseMessagesTab && function_exists('nammu_fediverse_outgoing_public_reply_message_entries')
        ? nammu_fediverse_outgoing_public_reply_message_entries($fediverseConfig)
        : [];
    $fediversePublicThreadRootMessages = $isFediverseMessagesTab && function_exists('nammu_fediverse_public_thread_root_message_entries')
        ? nammu_fediverse_public_thread_root_message_entries($fediverseConfig)
        : [];
    $fediverseRemoteThreadRootMessages = $isFediverseMessagesTab && function_exists('nammu_fediverse_remote_thread_root_message_entries')
        ? nammu_fediverse_remote_thread_root_message_entries($fediverseConfig)
        : [];
    $fediverseNotifications = $isFediverseNotificationsTab && function_exists('nammu_fediverse_notification_entries')
        ? nammu_fediverse_notification_entries($fediverseConfig)
        : [];
    $fediverseTimelinePage = max(1, (int) ($_GET['timeline_page'] ?? 1));
    $fediverseTimelinePerPage = 40;
    $fediverseLocalReactionDetails = $isFediverseHomeTab && function_exists('nammu_fediverse_local_reaction_details')
        ? nammu_fediverse_local_reaction_details($fediverseConfig)
        : [];
    $fediverseRemoteBoostSummary = $isFediverseHomeTab && function_exists('nammu_fediverse_remote_boost_summary')
        ? nammu_fediverse_remote_boost_summary()
        : [];
    $fediverseRemoteReplySummary = $isFediverseHomeTab && function_exists('nammu_fediverse_remote_reply_summary')
        ? nammu_fediverse_remote_reply_summary()
        : [];
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
    $fediverseKnownActors = ($isFediverseHomeTab || $isFediverseNotificationsTab || $isFediverseMessagesTab)
        && function_exists('nammu_fediverse_known_actors')
        ? nammu_fediverse_known_actors()
        : [];
    $fediverseActorsById = [];
    foreach ($fediverseKnownActors as $fediverseKnownActor) {
        $fediverseKnownActorId = trim((string) ($fediverseKnownActor['id'] ?? ''));
        if ($fediverseKnownActorId !== '') {
            $fediverseActorsById[$fediverseKnownActorId] = $fediverseKnownActor;
        }
    }
    foreach ($fediversePublicReplyMessages as $publicReplyMessage) {
        $actorId = trim((string) ($publicReplyMessage['actor_id'] ?? ''));
        if ($actorId === '') {
            continue;
        }
        if (!isset($fediverseMessages[$actorId])) {
            $fediverseMessages[$actorId] = [];
        }
        $alreadyPresent = false;
        foreach ($fediverseMessages[$actorId] as $existingMessage) {
            if ((string) ($existingMessage['id'] ?? '') === (string) ($publicReplyMessage['id'] ?? '')) {
                $alreadyPresent = true;
                break;
            }
        }
        if (!$alreadyPresent) {
            $fediverseMessages[$actorId][] = $publicReplyMessage;
        }
    }
    foreach (array_merge($fediverseOutgoingPublicReplyMessages, $fediversePublicThreadRootMessages, $fediverseRemoteThreadRootMessages) as $publicConversationMessage) {
        $actorId = trim((string) ($publicConversationMessage['actor_id'] ?? ''));
        if ($actorId === '') {
            continue;
        }
        if (!isset($fediverseMessages[$actorId])) {
            $fediverseMessages[$actorId] = [];
        }
        $alreadyPresent = false;
        foreach ($fediverseMessages[$actorId] as $existingMessage) {
            if ((string) ($existingMessage['id'] ?? '') === (string) ($publicConversationMessage['id'] ?? '')) {
                $alreadyPresent = true;
                break;
            }
        }
        if (!$alreadyPresent) {
            $fediverseMessages[$actorId][] = $publicConversationMessage;
        }
    }
    foreach ($fediverseMessages as &$fediverseMessageGroup) {
        usort($fediverseMessageGroup, static function (array $a, array $b): int {
            return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
        });
    }
    unset($fediverseMessageGroup);
    $fediverseFlatMessages = [];
    foreach ($fediverseMessages as $fediverseMessageGroupItems) {
        foreach ((array) $fediverseMessageGroupItems as $fediverseMessageItem) {
            $fediverseFlatMessages[] = $fediverseMessageItem;
        }
    }
    $fediverseMessageThreads = $isFediverseMessagesTab && function_exists('nammu_fediverse_thread_grouped_messages')
        ? nammu_fediverse_thread_grouped_messages($fediverseFlatMessages)
        : [];
    $fediverseLocalItems = $isFediverseHomeTab && function_exists('nammu_fediverse_local_content_items')
        ? nammu_fediverse_local_content_items($fediverseConfig)
        : [];
    if ($isFediverseHomeTab && function_exists('nammu_fediverse_actions_store') && function_exists('nammu_fediverse_resend_item_from_action')) {
        foreach (nammu_fediverse_actions_store()['items'] as $fediverseAction) {
            $fediverseResendItem = nammu_fediverse_resend_item_from_action($fediverseAction);
            if (is_array($fediverseResendItem)) {
                $fediverseLocalItems[] = $fediverseResendItem;
            }
        }
    }
    $fediverseLocalReactionSummary = $isFediverseHomeTab && function_exists('nammu_fediverse_local_reaction_summary') ? nammu_fediverse_local_reaction_summary($fediverseConfig) : [];
    $fediverseIncomingReplies = $isFediverseHomeTab && function_exists('nammu_fediverse_incoming_public_replies_by_object') ? nammu_fediverse_incoming_public_replies_by_object($fediverseConfig) : [];
    $fediverseIncomingReplyIds = [];
    $fediverseIncomingReplyRoots = [];
    $fediverseRemoteRepliesByTarget = [];
    foreach ($fediverseIncomingReplies as $fediverseIncomingLocalId => $fediverseIncomingReplyGroup) {
        foreach ((array) $fediverseIncomingReplyGroup as $fediverseIncomingReply) {
            foreach (['id', 'url'] as $fediverseIncomingReplyField) {
                $fediverseIncomingReplyValue = trim((string) ($fediverseIncomingReply[$fediverseIncomingReplyField] ?? ''));
                if ($fediverseIncomingReplyValue === '') {
                    continue;
                }
                $fediverseIncomingReplyIds[$fediverseIncomingReplyValue] = true;
                $fediverseIncomingReplyRoots[$fediverseIncomingReplyValue] = (string) $fediverseIncomingLocalId;
            }
        }
    }
    if ($isFediverseHomeTab) {
        foreach ($fediverseTimeline as $fediverseTimelineReplyCandidate) {
            if (!is_array($fediverseTimelineReplyCandidate)) {
                continue;
            }
            $fediverseTimelineReplyTarget = trim((string) ($fediverseTimelineReplyCandidate['target_url'] ?? ''));
            if ($fediverseTimelineReplyTarget === '') {
                continue;
            }
            $fediverseTimelineReplyType = strtolower(trim((string) ($fediverseTimelineReplyCandidate['type'] ?? '')));
            if (!in_array($fediverseTimelineReplyType, ['note', 'create'], true)) {
                continue;
            }
            $fediverseTimelineReplyText = trim((string) ($fediverseTimelineReplyCandidate['content'] ?? ''));
            if ($fediverseTimelineReplyText === '' && trim((string) ($fediverseTimelineReplyCandidate['content_html'] ?? '')) !== '' && function_exists('nammu_fediverse_html_to_text')) {
                $fediverseTimelineReplyText = nammu_fediverse_html_to_text((string) $fediverseTimelineReplyCandidate['content_html']);
            }
            if ($fediverseTimelineReplyText === '') {
                continue;
            }
            if (!isset($fediverseRemoteRepliesByTarget[$fediverseTimelineReplyTarget])) {
                $fediverseRemoteRepliesByTarget[$fediverseTimelineReplyTarget] = [];
            }
            $fediverseRemoteRepliesByTarget[$fediverseTimelineReplyTarget][] = [
                'id' => trim((string) ($fediverseTimelineReplyCandidate['id'] ?? '')),
                'note_id' => trim((string) (($fediverseTimelineReplyCandidate['object_id'] ?? '') ?: ($fediverseTimelineReplyCandidate['id'] ?? ''))),
                'url' => trim((string) ($fediverseTimelineReplyCandidate['url'] ?? '')),
                'published' => trim((string) ($fediverseTimelineReplyCandidate['published'] ?? '')),
                'reply_text' => $fediverseTimelineReplyText,
                'actor_id' => trim((string) ($fediverseTimelineReplyCandidate['actor_id'] ?? '')),
                'actor_name' => trim((string) ($fediverseTimelineReplyCandidate['actor_name'] ?? '')),
                'actor_icon' => trim((string) ($fediverseTimelineReplyCandidate['actor_icon'] ?? '')),
                'source' => 'incoming-remote',
            ];
        }
    }
    $fediverseLocalLinks = [];
    foreach ($fediverseLocalItems as $fediverseLocalItem) {
        $fediverseLocalId = trim((string) ($fediverseLocalItem['id'] ?? ''));
        if ($fediverseLocalId === '') {
            continue;
        }
        $fediverseLocalAnchor = 'local-' . substr(sha1($fediverseLocalId), 0, 12);
        $fediverseLocalLinks[$fediverseLocalId] = 'admin.php?page=fediverso&tab=home#' . $fediverseLocalAnchor;
        $fediverseLocalUrl = trim((string) ($fediverseLocalItem['url'] ?? ''));
        if ($fediverseLocalUrl !== '') {
            $fediverseLocalLinks[$fediverseLocalUrl] = 'admin.php?page=fediverso&tab=home#' . $fediverseLocalAnchor;
        }
    }
    $fediverseTimelineEntries = [];
    foreach ($fediverseLocalItems as $fediverseLocalItem) {
        $fediverseTimelineEntries[] = [
            'kind' => 'local',
            'published' => (string) ($fediverseLocalItem['published'] ?? ''),
            'item' => $fediverseLocalItem,
        ];
    }
    $fediverseTimelineDisplay = [];
    foreach ($fediverseTimeline as $fediverseTimelineItem) {
        $fediverseTimelineType = strtolower(trim((string) ($fediverseTimelineItem['type'] ?? '')));
        if (in_array($fediverseTimelineType, ['like', 'delete'], true)) {
            continue;
        }
        if ($fediverseTimelineType === 'announce') {
            $announceHasContent = trim((string) ($fediverseTimelineItem['content'] ?? '')) !== ''
                || trim((string) ($fediverseTimelineItem['content_html'] ?? '')) !== ''
                || trim((string) ($fediverseTimelineItem['title'] ?? '')) !== ''
                || trim((string) ($fediverseTimelineItem['image'] ?? '')) !== ''
                || !empty($fediverseTimelineItem['attachments']);
            if (!$announceHasContent) {
                continue;
            }
        }
        $fediverseTimelineIdentifiers = [];
        foreach (['id', 'object_id', 'url'] as $fediverseTimelineField) {
            $fediverseTimelineFieldValue = trim((string) ($fediverseTimelineItem[$fediverseTimelineField] ?? ''));
            if ($fediverseTimelineFieldValue !== '') {
                $fediverseTimelineIdentifiers[] = $fediverseTimelineFieldValue;
            }
        }
        $fediverseTimelineTargetUrl = trim((string) ($fediverseTimelineItem['target_url'] ?? ''));
        if ($fediverseTimelineTargetUrl !== '') {
            $fediverseTimelineIdentifiers[] = $fediverseTimelineTargetUrl;
        }
        if ($fediverseTimelineTargetUrl !== '') {
            continue;
        }
        $fediverseSkipTimelineItem = false;
        foreach ($fediverseTimelineIdentifiers as $fediverseTimelineIdentifier) {
            if (isset($fediverseIncomingReplyIds[$fediverseTimelineIdentifier])) {
                $fediverseSkipTimelineItem = true;
                break;
            }
        }
        if ($fediverseSkipTimelineItem) {
            continue;
        }
        $fediverseTimelineActorId = trim((string) ($fediverseTimelineItem['actor_id'] ?? ''));
        $fediverseTimelineActor = $fediverseTimelineActorId !== '' ? ($fediverseActorsById[$fediverseTimelineActorId] ?? null) : null;
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
        $fediversePrimaryLinkAttachment = null;
        foreach ($fediverseTimelineAttachments as $fediverseTimelineAttachmentIndex => $fediverseTimelineAttachment) {
            if (!is_array($fediverseTimelineAttachment)) {
                continue;
            }
            $fediverseTimelineAttachmentType = strtolower(trim((string) ($fediverseTimelineAttachment['type'] ?? '')));
            $fediverseTimelineAttachmentMediaType = strtolower(trim((string) ($fediverseTimelineAttachment['media_type'] ?? '')));
            if ($fediverseTimelineAttachmentType === 'link' || $fediverseTimelineAttachmentMediaType === 'text/html' || str_starts_with($fediverseTimelineAttachmentMediaType, 'text/html')) {
                $fediversePrimaryLinkAttachment = [
                    'index' => $fediverseTimelineAttachmentIndex,
                    'item' => $fediverseTimelineAttachment,
                ];
                break;
            }
        }
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
        $fediverseTimelineEntries[] = [
            'kind' => 'remote',
            'published' => (string) ($fediverseTimelineItem['published'] ?? ''),
            'item' => $fediverseTimelineItem,
        ];
    }
    usort($fediverseTimelineEntries, static function (array $a, array $b): int {
        return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
    });
    $fediverseTimelineTotal = count($fediverseTimelineEntries);
    $fediverseTimelineTotalPages = max(1, (int) ceil($fediverseTimelineTotal / $fediverseTimelinePerPage));
    if ($fediverseTimelinePage > $fediverseTimelineTotalPages) {
        $fediverseTimelinePage = $fediverseTimelineTotalPages;
    }
    $fediverseTimelineOffset = ($fediverseTimelinePage - 1) * $fediverseTimelinePerPage;
    $fediverseTimelinePageEntries = array_slice($fediverseTimelineEntries, $fediverseTimelineOffset, $fediverseTimelinePerPage);
    $buildTimelinePageUrl = static function (int $pageNumber): string {
        return 'admin.php?page=fediverso&tab=home&timeline_page=' . max(1, $pageNumber);
    };
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
    $notificationContext = static function (array $entry) use ($fediverseActorsById, $fediverseConfig, $fediverseLocalLinks): array {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $actorId = trim((string) ($payload['actor'] ?? ''));
        $actor = $actorId !== '' ? ($fediverseActorsById[$actorId] ?? null) : null;
        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        $object = $payload['object'] ?? null;
        $targetUrl = '';
        if (in_array($type, ['like', 'announce'], true) && is_string($object)) {
            $targetUrl = trim($object);
        } elseif ($type === 'create' && is_array($object)) {
            $targetUrl = trim((string) (($object['inReplyTo'] ?? '') ?: ($object['url'] ?? '') ?: ($object['id'] ?? '')));
        } elseif (is_string($object)) {
            $targetUrl = trim($object);
        } elseif (is_array($object)) {
            $targetUrl = trim((string) (($object['url'] ?? '') ?: ($object['id'] ?? '')));
        }
        if ($targetUrl !== '' && isset($fediverseLocalLinks[$targetUrl])) {
            $targetUrl = $fediverseLocalLinks[$targetUrl];
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
            'create' => 'Respondió a una publicación',
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
                        <div class="d-flex flex-wrap gap-2">
                            <form method="post" class="mb-0">
                                <input type="hidden" name="fediverse_tab" value="home">
                                <button type="submit" name="refresh_fediverse_timeline" class="btn btn-outline-secondary btn-sm">Refrescar ahora</button>
                            </form>
                            <form method="post" class="mb-0" onsubmit="return confirm('Esto vaciará y reconstruirá la timeline remota guardada. ¿Continuar?');">
                                <input type="hidden" name="fediverse_tab" value="home">
                                <button type="submit" name="rebuild_fediverse_timeline" class="btn btn-outline-secondary btn-sm">Reconstruir timeline</button>
                            </form>
                        </div>
                    </div>
                    <?php if (empty($fediverseTimelinePageEntries)): ?>
                        <p class="text-muted mb-0">Aún no hay publicaciones remotas recibidas. Sigue actores en la pestaña de configuración y luego refresca.</p>
                    <?php else: ?>
                        <div class="fediverse-timeline">
                            <?php foreach ($fediverseTimelinePageEntries as $timelineEntry): ?>
                                <?php if (($timelineEntry['kind'] ?? '') === 'local'): ?>
                                <?php $localItem = is_array($timelineEntry['item'] ?? null) ? $timelineEntry['item'] : []; ?>
                                <?php
                                $localId = trim((string) ($localItem['id'] ?? ''));
                                if ($localId === '') { continue; }
                                $localAnchor = 'local-' . substr(sha1($localId), 0, 12);
                                $localSummary = $fediverseLocalReactionSummary[$localId] ?? ['likes' => 0, 'shares' => 0, 'replies' => 0];
                                $localReactionDetails = $fediverseLocalReactionDetails[$localId] ?? ['likes' => [], 'shares' => [], 'replies' => []];
                                $localReplyTargets = [];
                                foreach (['id', 'url'] as $localReplyField) {
                                    $localReplyValue = trim((string) ($localItem[$localReplyField] ?? ''));
                                    if ($localReplyValue !== '') {
                                        $localReplyTargets[] = $localReplyValue;
                                    }
                                }
                                $incomingReplies = is_array($fediverseIncomingReplies[$localId] ?? null) ? $fediverseIncomingReplies[$localId] : [];
                                foreach ($incomingReplies as $incomingReply) {
                                    foreach (['id', 'url'] as $incomingReplyField) {
                                        $incomingReplyValue = trim((string) ($incomingReply[$incomingReplyField] ?? ''));
                                        if ($incomingReplyValue !== '') {
                                            $localReplyTargets[] = $incomingReplyValue;
                                        }
                                    }
                                }
                                $localReplies = function_exists('nammu_fediverse_public_replies_for_targets') ? nammu_fediverse_public_replies_for_targets($localReplyTargets) : [];
                                $threadReplies = [];
                                foreach ($localReplies as $reply) {
                                    $threadReplies[] = [
                                        'id' => (string) ($reply['id'] ?? ''),
                                        'published' => (string) ($reply['published'] ?? ''),
                                        'reply_text' => (string) ($reply['reply_text'] ?? ''),
                                        'actor_name' => $fediverseLocalName,
                                        'actor_handle' => $fediverseLocalHandle,
                                        'actor_icon' => $fediverseLocalAvatar,
                                        'source' => 'local',
                                    ];
                                }
                                foreach ($incomingReplies as $reply) {
                                    $threadReplies[] = $reply;
                                }
                                usort($threadReplies, static function (array $a, array $b): int {
                                    return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
                                });
                                $shareModalId = 'fediverse-share-modal-' . preg_replace('/[^a-z0-9_-]+/i', '-', $localAnchor);
                                $localIsNote = strcasecmp((string) ($localItem['type'] ?? ''), 'Note') === 0;
                                $localCardDescription = trim((string) (($localItem['summary'] ?? '') ?: ($localItem['content'] ?? '')));
                                $localCardDescription = preg_replace('/\s+/', ' ', strip_tags($localCardDescription)) ?? '';
                                ?>
                                <article class="fediverse-status fediverse-status--local" id="<?= htmlspecialchars($localAnchor, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="fediverse-status__avatar">
                                        <?php if ($fediverseLocalAvatar !== ''): ?>
                                            <img src="<?= htmlspecialchars($fediverseLocalAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <div class="fediverse-status__avatar-fallback"><?= htmlspecialchars(mb_substr((string) ($siteTitle ?? 'B'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fediverse-status__body">
                                        <div class="fediverse-status__header">
                                            <div class="fediverse-status__identity">
                                                <strong><?= htmlspecialchars($fediverseLocalName, ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span class="fediverse-status__handle"><?= htmlspecialchars($fediverseLocalHandle, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div class="fediverse-status__meta">
                                                <time datetime="<?= htmlspecialchars((string) ($localItem['published'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($localItem['published'] ?? ''), ENT_QUOTES, 'UTF-8') ?></time>
                                            </div>
                                        </div>
                                        <?php if (!empty($localItem['title']) && !$localIsNote): ?>
                                            <div class="fediverse-status__title"><?= htmlspecialchars((string) ($localItem['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php if ($localIsNote && !empty($localItem['content'])): ?>
                                            <div class="fediverse-status__content"><?= nl2br(htmlspecialchars((string) ($localItem['content'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                        <?php endif; ?>
                                        <?php if (!$localIsNote && !empty($localItem['url'])): ?>
                                            <div class="fediverse-status__attachments">
                                                <a class="fediverse-status__file fediverse-status__file--linkcard" href="<?= htmlspecialchars((string) $localItem['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                    <?php if (!empty($localItem['image'])): ?>
                                                        <img class="fediverse-status__file-cover" src="<?= htmlspecialchars((string) $localItem['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($localItem['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                                    <?php endif; ?>
                                                    <span class="fediverse-status__file-name"><?= htmlspecialchars((string) (($localItem['title'] ?? '') ?: ($localItem['url'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php if ($localCardDescription !== ''): ?>
                                                        <span class="fediverse-status__file-meta fediverse-status__file-meta--description"><?= htmlspecialchars($localCardDescription, ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </div>
                                        <?php elseif (!empty($localItem['image'])): ?>
                                            <div class="fediverse-status__attachments">
                                                <a class="fediverse-status__media" href="<?= htmlspecialchars((string) $localItem['image'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                    <img src="<?= htmlspecialchars((string) $localItem['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <div class="fediverse-status__footer">
                                            <a href="<?= htmlspecialchars((string) ($localItem['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir publicación emitida</a>
                                            <?php if (!empty($localId)): ?>
                                                <span aria-hidden="true"> · </span>
                                                <a href="<?= htmlspecialchars((string) $localId, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Objeto ActivityPub</a>
                                            <?php endif; ?>
                                            <form method="post" class="d-inline-block ml-2" onsubmit="return confirm('¿Retirar esta publicación del Fediverso y enviar el borrado a otros nodos?');">
                                                <input type="hidden" name="fediverse_tab" value="home">
                                                <input type="hidden" name="fediverse_local_item_id" value="<?= htmlspecialchars($localId, ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" name="fediverse_delete_local_item" class="btn btn-outline-danger btn-sm">Borrar</button>
                                            </form>
                                        </div>
                                        <?php if (($localSummary['likes'] ?? 0) > 0 || ($localSummary['shares'] ?? 0) > 0 || ($localSummary['replies'] ?? 0) > 0): ?>
                                            <div class="fediverse-status__history">
                                                <?php if (($localSummary['likes'] ?? 0) > 0): ?><span><?= (int) $localSummary['likes'] ?> favorito<?= ((int) $localSummary['likes'] === 1) ? '' : 's' ?></span><?php endif; ?>
                                                <?php if (($localSummary['shares'] ?? 0) > 0): ?>
                                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-toggle="modal" data-target="#<?= htmlspecialchars($shareModalId, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= (int) $localSummary['shares'] ?> impulso<?= ((int) $localSummary['shares'] === 1) ? '' : 's' ?>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (($localSummary['replies'] ?? 0) > 0): ?><span><?= (int) $localSummary['replies'] ?> respuesta<?= ((int) $localSummary['replies'] === 1) ? '' : 's' ?></span><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($localReactionDetails['shares'])): ?>
                                            <div class="modal fade" id="<?= htmlspecialchars($shareModalId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" role="dialog" aria-labelledby="<?= htmlspecialchars($shareModalId, ENT_QUOTES, 'UTF-8') ?>-label" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="<?= htmlspecialchars($shareModalId, ENT_QUOTES, 'UTF-8') ?>-label">Impulsaron esta publicación</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="list-group list-group-flush">
                                                                <?php foreach ($localReactionDetails['shares'] as $shareActor): ?>
                                                                    <?php $shareActorUrl = trim((string) (($shareActor['url'] ?? '') ?: '#')); ?>
                                                                    <a class="list-group-item list-group-item-action d-flex align-items-center" href="<?= htmlspecialchars($shareActorUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                        <?php if (!empty($shareActor['icon'])): ?>
                                                                            <img src="<?= htmlspecialchars((string) $shareActor['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" style="width:40px;height:40px;border-radius:999px;object-fit:cover;margin-right:0.75rem;">
                                                                        <?php else: ?>
                                                                            <span class="d-inline-flex align-items-center justify-content-center mr-3" style="width:40px;height:40px;border-radius:999px;background:#e9ecef;font-weight:700;">
                                                                                <?= htmlspecialchars(mb_substr((string) (($shareActor['name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        <span><?= htmlspecialchars((string) ($shareActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                                    </a>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($threadReplies)): ?>
                                            <div class="fediverse-thread">
                                                <?php foreach ($threadReplies as $reply): ?>
                                                    <div class="fediverse-thread__reply">
                                                        <div class="fediverse-thread__avatar">
                                                            <?php if (!empty($reply['actor_icon'])): ?>
                                                                <img src="<?= htmlspecialchars((string) $reply['actor_icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                                            <?php else: ?>
                                                                <div class="fediverse-thread__avatar-fallback"><?= htmlspecialchars(mb_substr((string) (($reply['actor_name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="fediverse-thread__body">
                                                            <div class="fediverse-thread__header">
                                                                <strong><?= htmlspecialchars((string) ($reply['actor_name'] ?? $fediverseLocalName), ENT_QUOTES, 'UTF-8') ?></strong>
                                                                <?php if (!empty($reply['actor_handle'])): ?>
                                                                    <span><?= htmlspecialchars((string) $reply['actor_handle'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($reply['published'])): ?>
                                                                    <time datetime="<?= htmlspecialchars((string) $reply['published'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $reply['published'], ENT_QUOTES, 'UTF-8') ?></time>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="fediverse-thread__content"><?= nl2br(htmlspecialchars((string) ($reply['reply_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                                            <?php if (($reply['source'] ?? '') === 'local' && !empty($reply['id'])): ?>
                                                                <form method="post" class="mt-2" onsubmit="return confirm('¿Borrar esta respuesta del Fediverso?');">
                                                                    <input type="hidden" name="fediverse_tab" value="home">
                                                                    <input type="hidden" name="fediverse_reply_action_id" value="<?= htmlspecialchars((string) $reply['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                                    <button type="submit" name="fediverse_delete_reply_item" class="btn btn-outline-danger btn-sm">Borrar</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                                <?php else: ?>
                                <?php $item = is_array($timelineEntry['item'] ?? null) ? $timelineEntry['item'] : []; ?>
                                <?php $itemObjectId = (string) (($item['object_id'] ?? '') ?: (($item['url'] ?? '') ?: ($item['id'] ?? ''))); ?>
                                <?php $itemTargetActorId = (string) (($item['target_actor_id'] ?? '') ?: ($item['actor_id'] ?? '')); ?>
                                <?php $itemActionState = function_exists('nammu_fediverse_action_state_for_item') ? nammu_fediverse_action_state_for_item($item) : ['liked' => false, 'boosted' => false, 'replied' => false, 'shared' => false, 'boost_count' => 0, 'reply_count' => 0, 'share_count' => 0]; ?>
                                <?php $itemReplies = function_exists('nammu_fediverse_replies_for_item') ? nammu_fediverse_replies_for_item($item) : []; ?>
                                <?php $remoteItemReplies = function_exists('nammu_fediverse_cached_remote_replies_for_item') ? nammu_fediverse_cached_remote_replies_for_item($item, $fediverseConfig) : []; ?>
                                <?php
                                $replyDedupKeys = [];
                                $registerReplyKey = static function (array $reply) use (&$replyDedupKeys): void {
                                    $identityCandidates = array_filter([
                                        trim((string) ($reply['id'] ?? '')),
                                        trim((string) ($reply['url'] ?? '')),
                                        trim((string) ($reply['note_id'] ?? '')),
                                    ]);
                                    foreach ($identityCandidates as $identityCandidate) {
                                        $replyDedupKeys['id:' . $identityCandidate] = true;
                                    }
                                    $fallbackKey = strtolower(trim((string) ($reply['actor_id'] ?? ''))) . '|' .
                                        trim((string) ($reply['published'] ?? '')) . '|' .
                                        trim((string) ($reply['reply_text'] ?? ''));
                                    if ($fallbackKey !== '||') {
                                        $replyDedupKeys['fallback:' . $fallbackKey] = true;
                                    }
                                };
                                foreach ($itemReplies as $existingReply) {
                                    $registerReplyKey($existingReply);
                                }
                                $storedRemoteReplies = [];
                                foreach (array_filter([
                                    trim((string) ($item['object_id'] ?? '')),
                                    trim((string) ($item['url'] ?? '')),
                                    trim((string) ($item['id'] ?? '')),
                                ]) as $itemReplyTargetIdentifier) {
                                    foreach ((array) ($fediverseRemoteRepliesByTarget[$itemReplyTargetIdentifier] ?? []) as $storedRemoteReply) {
                                        $storedRemoteReplies[] = $storedRemoteReply;
                                    }
                                }
                                foreach ($storedRemoteReplies as $remoteItemReply) {
                                    $remoteReplyFallbackKey = strtolower(trim((string) ($remoteItemReply['actor_id'] ?? ''))) . '|' .
                                        trim((string) ($remoteItemReply['published'] ?? '')) . '|' .
                                        trim((string) ($remoteItemReply['reply_text'] ?? ''));
                                    $remoteReplyIdentifiers = array_filter([
                                        'fallback:' . $remoteReplyFallbackKey,
                                        'id:' . trim((string) ($remoteItemReply['id'] ?? '')),
                                        'id:' . trim((string) ($remoteItemReply['url'] ?? '')),
                                        'id:' . trim((string) ($remoteItemReply['note_id'] ?? '')),
                                    ], static fn(string $value): bool => $value !== 'id:' && $value !== 'fallback:||');
                                    if (empty(array_intersect_key(array_flip($remoteReplyIdentifiers), $replyDedupKeys))) {
                                        $itemReplies[] = $remoteItemReply;
                                        $registerReplyKey($remoteItemReply);
                                    }
                                }
                                foreach ($remoteItemReplies as $remoteItemReply) {
                                    $remoteReplyFallbackKey = strtolower(trim((string) ($remoteItemReply['actor_id'] ?? ''))) . '|' .
                                        trim((string) ($remoteItemReply['published'] ?? '')) . '|' .
                                        trim((string) ($remoteItemReply['reply_text'] ?? ''));
                                    $remoteReplyIdentifiers = array_filter([
                                        'fallback:' . $remoteReplyFallbackKey,
                                        'id:' . trim((string) ($remoteItemReply['id'] ?? '')),
                                        'id:' . trim((string) ($remoteItemReply['url'] ?? '')),
                                        'id:' . trim((string) ($remoteItemReply['note_id'] ?? '')),
                                    ], static fn(string $value): bool => $value !== 'id:' && $value !== 'fallback:||');
                                    if (empty(array_intersect_key(array_flip($remoteReplyIdentifiers), $replyDedupKeys))) {
                                        $itemReplies[] = $remoteItemReply;
                                        $registerReplyKey($remoteItemReply);
                                    }
                                }
                                usort($itemReplies, static function (array $a, array $b): int {
                                    return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
                                });
                                ?>
                                <?php $remoteBoostMeta = $fediverseRemoteBoostSummary[$itemObjectId] ?? ['count' => 0]; ?>
                                <?php
                                $remoteReplyMeta = $fediverseRemoteReplySummary[$itemObjectId] ?? ['count' => 0];
                                if (!empty($remoteItemReplies)) {
                                    $remoteReplyActors = [];
                                    foreach ($remoteItemReplies as $remoteItemReply) {
                                        $remoteReplyActorId = trim((string) ($remoteItemReply['actor_id'] ?? ''));
                                        if ($remoteReplyActorId !== '') {
                                            $remoteReplyActors[$remoteReplyActorId] = true;
                                        }
                                    }
                                    $remoteReplyMeta['count'] = max((int) ($remoteReplyMeta['count'] ?? 0), count($remoteReplyActors) ?: count($remoteItemReplies));
                                }
                                ?>
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
                                                <?php if (strcasecmp((string) ($item['type'] ?? ''), 'announce') === 0): ?>
                                                    <span class="fediverse-status__handle">impulsó</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fediverse-status__meta">
                                                <?php if (!empty($item['published'])): ?>
                                                    <time datetime="<?= htmlspecialchars((string) $item['published'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['published'], ENT_QUOTES, 'UTF-8') ?></time>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($item['title']) && strcasecmp((string) ($item['type'] ?? ''), 'Note') !== 0): ?>
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
                                                    $isLinkCard = $attachmentType === 'link' || $attachmentMediaType === 'text/html' || str_starts_with($attachmentMediaType, 'text/html');
                                                    $linkCardTitle = trim((string) (($attachment['name'] ?? '') ?: ($item['title'] ?? '') ?: 'Abrir enlace'));
                                                    $linkCardImage = trim((string) (($attachment['image'] ?? '') ?: ($item['image'] ?? '')));
                                                    $linkCardDescription = trim((string) (($attachment['summary'] ?? '') ?: ($item['summary'] ?? '') ?: ($item['content'] ?? '')));
                                                    $linkCardDescription = preg_replace('#^\s*https?://\S+\s*#iu', '', $linkCardDescription) ?? $linkCardDescription;
                                                    $linkCardDescription = preg_replace('/\s+/', ' ', strip_tags($linkCardDescription)) ?? '';
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
                                                    <?php elseif ($isLinkCard): ?>
                                                        <a class="fediverse-status__file fediverse-status__file--linkcard" href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                            <?php if ($linkCardImage !== ''): ?>
                                                                <img class="fediverse-status__file-cover" src="<?= htmlspecialchars($linkCardImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($linkCardTitle, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                                            <?php endif; ?>
                                                            <span class="fediverse-status__file-name"><?= htmlspecialchars($linkCardTitle, ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php if ($linkCardDescription !== ''): ?>
                                                                <span class="fediverse-status__file-meta fediverse-status__file-meta--description"><?= htmlspecialchars($linkCardDescription, ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php endif; ?>
                                                        </a>
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
                                            <?php if ($itemObjectId !== ''): ?>
                                                <span aria-hidden="true"> · </span>
                                                <a href="<?= htmlspecialchars($itemObjectId, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Objeto ActivityPub</a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (($remoteBoostMeta['count'] ?? 0) > 0 || ($remoteReplyMeta['count'] ?? 0) > 0 || !empty($itemActionState['liked']) || !empty($itemActionState['boosted']) || !empty($itemActionState['replied']) || !empty($itemActionState['shared'])): ?>
                                            <div class="fediverse-status__history">
                                                <?php if (($remoteBoostMeta['count'] ?? 0) > 0): ?><span><?= (int) ($remoteBoostMeta['count'] ?? 0) ?> impulso<?= ((int) ($remoteBoostMeta['count'] ?? 0) === 1) ? '' : 's' ?></span><?php endif; ?>
                                                <?php if (($remoteReplyMeta['count'] ?? 0) > 0): ?><span><?= (int) ($remoteReplyMeta['count'] ?? 0) ?> respuesta<?= ((int) ($remoteReplyMeta['count'] ?? 0) === 1) ? '' : 's' ?></span><?php endif; ?>
                                                <?php if (!empty($itemActionState['liked'])): ?><span>Favorito enviado</span><?php endif; ?>
                                                <?php if (!empty($itemActionState['boosted'])): ?><span><?= (int) ($itemActionState['boost_count'] ?? 0) ?> impulso<?= ((int) ($itemActionState['boost_count'] ?? 0) === 1) ? '' : 's' ?></span><?php endif; ?>
                                                <?php if (!empty($itemActionState['replied'])): ?><span><?= (int) ($itemActionState['reply_count'] ?? 0) ?> respuesta<?= ((int) ($itemActionState['reply_count'] ?? 0) === 1) ? '' : 's' ?></span><?php endif; ?>
                                                <?php if (!empty($itemActionState['shared'])): ?><span><?= (int) ($itemActionState['share_count'] ?? 0) ?> reenvío<?= ((int) ($itemActionState['share_count'] ?? 0) === 1) ? '' : 's' ?> como nota</span><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($itemReplies)): ?>
                                            <div class="fediverse-thread">
                                                <?php foreach ($itemReplies as $reply): ?>
                                                    <div class="fediverse-thread__reply">
                                                        <div class="fediverse-thread__avatar">
                                                            <?php if (!empty($reply['actor_icon'])): ?>
                                                                <img src="<?= htmlspecialchars((string) $reply['actor_icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                                            <?php elseif (($reply['source'] ?? '') === 'incoming-remote'): ?>
                                                                <div class="fediverse-thread__avatar-fallback"><?= htmlspecialchars(mb_substr((string) (($reply['actor_name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                                            <?php elseif ($fediverseLocalAvatar !== ''): ?>
                                                                <img src="<?= htmlspecialchars($fediverseLocalAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                                            <?php else: ?>
                                                                <div class="fediverse-thread__avatar-fallback"><?= htmlspecialchars(mb_substr($fediverseLocalHandle, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="fediverse-thread__body">
                                                            <div class="fediverse-thread__header">
                                                                <strong><?= htmlspecialchars((string) (($reply['source'] ?? '') === 'incoming-remote' ? (($reply['actor_name'] ?? '') ?: 'Actor remoto') : $fediverseLocalHandle), ENT_QUOTES, 'UTF-8') ?></strong>
                                                                <?php if (!empty($reply['published'])): ?>
                                                                    <time datetime="<?= htmlspecialchars((string) $reply['published'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $reply['published'], ENT_QUOTES, 'UTF-8') ?></time>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="fediverse-thread__content"><?= nl2br(htmlspecialchars((string) ($reply['reply_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                                            <?php if (($reply['source'] ?? '') !== 'incoming-remote' && !empty($reply['id'])): ?>
                                                                <form method="post" class="mt-2" onsubmit="return confirm('¿Borrar esta respuesta del Fediverso?');">
                                                                    <input type="hidden" name="fediverse_tab" value="home">
                                                                    <input type="hidden" name="fediverse_reply_action_id" value="<?= htmlspecialchars((string) $reply['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                                    <button type="submit" name="fediverse_delete_reply_item" class="btn btn-outline-danger btn-sm">Borrar</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="fediverse-status__actions">
                                            <form method="post" class="mb-0">
                                                <input type="hidden" name="fediverse_tab" value="home">
                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($itemTargetActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($itemObjectId, ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" name="fediverse_like_item" class="btn btn-outline-secondary btn-sm"<?= !empty($itemActionState['liked']) ? ' disabled' : '' ?>><?= !empty($itemActionState['liked']) ? 'Favorito enviado' : 'Favorito' ?></button>
                                            </form>
                                            <form method="post" class="mb-0">
                                                <input type="hidden" name="fediverse_tab" value="home">
                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($itemTargetActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($itemObjectId, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="fediverse_object_title" value="<?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="fediverse_object_content" value="<?= htmlspecialchars((string) ($item['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <?php
                                                $boostImageUrl = trim((string) ($item['image'] ?? ''));
                                                if ($boostImageUrl === '') {
                                                    foreach ($attachments as $attachment) {
                                                        $attachmentUrl = trim((string) ($attachment['url'] ?? ''));
                                                        $attachmentType = strtolower(trim((string) ($attachment['type'] ?? '')));
                                                        $attachmentMediaType = strtolower(trim((string) ($attachment['media_type'] ?? '')));
                                                        if ($attachmentUrl !== '' && ($attachmentType === 'image' || str_starts_with($attachmentMediaType, 'image/'))) {
                                                            $boostImageUrl = $attachmentUrl;
                                                            break;
                                                        }
                                                    }
                                                }
                                                ?>
                                                <input type="hidden" name="fediverse_object_image" value="<?= htmlspecialchars($boostImageUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" name="fediverse_boost_item" class="btn btn-outline-secondary btn-sm"<?= !empty($itemActionState['boosted']) ? ' disabled' : '' ?>><?= !empty($itemActionState['boosted']) ? 'Impulsado' : 'Impulsar' ?></button>
                                            </form>
                                            <details class="fediverse-inline-form">
                                                <summary>Responder</summary>
                                                <form method="post">
                                                    <input type="hidden" name="fediverse_tab" value="home">
                                                    <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($itemTargetActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($itemObjectId, ENT_QUOTES, 'UTF-8') ?>">
                                                    <textarea name="fediverse_reply_text" class="form-control form-control-sm" rows="3" placeholder="Escribe tu respuesta"></textarea>
                                                    <label class="fediverse-inline-check">
                                                        <input type="checkbox" name="fediverse_reply_as_note" value="1">
                                                        Publicar también como nota en Actualidad
                                                    </label>
                                                    <button type="submit" name="fediverse_reply_item" class="btn btn-primary btn-sm mt-2">Enviar respuesta</button>
                                                </form>
                                            </details>
                                            <details class="fediverse-inline-form">
                                                <summary>Reenviar como nota</summary>
                                                <form method="post">
                                                    <input type="hidden" name="fediverse_tab" value="home">
                                                    <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($itemObjectId, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="fediverse_object_title" value="<?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <textarea name="fediverse_share_text" class="form-control form-control-sm" rows="3" placeholder="Añade un comentario opcional para tu nota"><?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                    <button type="submit" name="fediverse_share_note" class="btn btn-primary btn-sm mt-2">Publicar nota</button>
                                                </form>
                                            </details>
                                        </div>
                                    </div>
                                </article>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($fediverseTimelineTotalPages > 1): ?>
                            <nav class="fediverse-pagination" aria-label="Paginación del timeline">
                                <?php if ($fediverseTimelinePage > 1): ?>
                                    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($buildTimelinePageUrl($fediverseTimelinePage - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a>
                                <?php endif; ?>
                                <span class="fediverse-pagination__status">Página <?= (int) $fediverseTimelinePage ?> de <?= (int) $fediverseTimelineTotalPages ?></span>
                                <?php if ($fediverseTimelinePage < $fediverseTimelineTotalPages): ?>
                                    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($buildTimelinePageUrl($fediverseTimelinePage + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
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
                                <select name="fediverse_message_recipient" id="fediverse_message_recipient" class="form-control fediverse-recipient-select" size="<?= htmlspecialchars((string) min(10, max(4, count($fediverseRecipients))), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($fediverseRecipients as $recipient): ?>
                                        <?php $recipientId = (string) ($recipient['id'] ?? ''); ?>
                                        <?php
                                        $recipientLabel = (string) (($recipient['name'] ?? '') ?: ($recipient['preferredUsername'] ?? $recipientId));
                                        $recipientMeta = [];
                                        if ($recipientId !== '' && isset($fediverseFollowingIds[$recipientId])) {
                                            $recipientMeta[] = 'seguido';
                                        }
                                        if ($recipientId !== '' && isset($fediverseFollowerIds[$recipientId])) {
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
                    <?php if (empty($fediverseMessageThreads)): ?>
                        <p class="text-muted mb-0">Todavía no hay conversaciones guardadas.</p>
                    <?php else: ?>
                        <?php foreach ($fediverseMessageThreads as $threadKey => $messages): ?>
                            <?php $firstMessage = $messages[0] ?? []; ?>
                            <?php
                            $actorId = trim((string) ($firstMessage['actor_id'] ?? ''));
                            $conversationActor = $fediverseActorsById[$actorId] ?? null;
                            $conversationActorName = trim((string) (($firstMessage['actor_name'] ?? '') ?: ($conversationActor['name'] ?? '') ?: ($conversationActor['preferredUsername'] ?? '') ?: $actorId));
                            $conversationActorIcon = trim((string) (($firstMessage['actor_icon'] ?? '') ?: ($conversationActor['icon'] ?? '')));
                            $conversationIsPublic = strtolower(trim((string) ($firstMessage['visibility'] ?? 'private'))) === 'public';
                            ?>
                            <div class="border rounded p-3 mb-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div class="fediverse-message__header mb-0">
                                        <div class="fediverse-message__avatar">
                                            <?php if ($conversationActorIcon !== ''): ?>
                                                <img src="<?= htmlspecialchars($conversationActorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                            <?php else: ?>
                                                <div class="fediverse-message__avatar-fallback"><?= htmlspecialchars(mb_substr($conversationActorName !== '' ? $conversationActorName : 'A', 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fediverse-message__identity">
                                            <strong><?= htmlspecialchars($conversationActorName, ENT_QUOTES, 'UTF-8') ?></strong>
                                            <div class="small text-muted mt-1"><?= htmlspecialchars((string) $actorId, ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted mt-1"><?= $conversationIsPublic ? 'Hilo público' : 'Conversación privada' ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php foreach ($messages as $message): ?>
                                    <?php $isOutgoing = (($message['direction'] ?? '') === 'outgoing'); ?>
                                    <?php
                                    $isPublicMessage = (($message['visibility'] ?? '') === 'public');
                                    $isThreadRoot = !empty($message['is_thread_root']);
                                    $messageActorIcon = trim((string) ($message['actor_icon'] ?? ''));
                                    if (!$isOutgoing && $messageActorIcon === '') {
                                        $messageActorIcon = trim((string) ($conversationActor['icon'] ?? ''));
                                    }
                                    $messageClasses = 'mb-3 p-3 rounded';
                                    if ($isPublicMessage) {
                                        $messageClasses .= $isThreadRoot ? ' fediverse-conversation__root' : ' fediverse-conversation__reply';
                                    }
                                    ?>
                                    <div class="<?= htmlspecialchars($messageClasses, ENT_QUOTES, 'UTF-8') ?>" style="background: <?= $isOutgoing ? '#eef6ff' : '#f7f7f7' ?>; border-left: 4px solid <?= $isOutgoing ? '#1b8eed' : '#999' ?>;">
                                        <div class="fediverse-message__visibility">
                                            <span class="fediverse-visibility-badge fediverse-visibility-badge--<?= $isPublicMessage ? 'public' : 'private' ?>">
                                                <?= $isPublicMessage ? 'Pública' : 'Privada' ?>
                                            </span>
                                        </div>
                                        <div class="fediverse-message__header">
                                            <div class="fediverse-message__avatar">
                                                <?php if ($isOutgoing && $fediverseLocalAvatar !== ''): ?>
                                                    <img src="<?= htmlspecialchars($fediverseLocalAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                                <?php elseif (!$isOutgoing && $messageActorIcon !== ''): ?>
                                                    <img src="<?= htmlspecialchars($messageActorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                                <?php else: ?>
                                                    <div class="fediverse-message__avatar-fallback"><?= htmlspecialchars(mb_substr($isOutgoing ? $fediverseLocalHandle : (string) (($message['actor_name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fediverse-message__identity">
                                                <strong><?= htmlspecialchars($isOutgoing ? $fediverseLocalName : (string) (($message['actor_name'] ?? '') ?: $actorId), ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php if ($isOutgoing): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($fediverseLocalHandle, ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                                <?php if (!$isOutgoing): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars((string) $actorId, ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="small text-muted mb-2">
                                            <?= $isOutgoing ? 'Enviado' : 'Recibido' ?> · <?= htmlspecialchars((string) ($message['published'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($message['is_thread_root'])): ?>
                                                · Publicación original
                                            <?php endif; ?>
                                            <?php if (!empty($message['delivery_status'])): ?>
                                                · <?= htmlspecialchars((string) ($message['delivery_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                            <?php if (!$isOutgoing && array_key_exists('verified', $message)): ?>
                                                · <?= !empty($message['verified']) ? 'verificado' : 'no verificado' ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($message['title']) && $isThreadRoot && strcasecmp((string) ($message['content_type'] ?? ''), 'Note') !== 0): ?>
                                            <div class="font-weight-bold mb-2"><?= htmlspecialchars((string) $message['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <div><?= nl2br(htmlspecialchars((string) ($message['content'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                        <?php if (!$isOutgoing && !$isPublicMessage): ?>
                                            <details class="fediverse-inline-form mt-3">
                                                <summary>Responder en privado</summary>
                                                <form method="post">
                                                    <input type="hidden" name="fediverse_tab" value="messages">
                                                    <input type="hidden" name="fediverse_message_actor_id" value="<?= htmlspecialchars((string) ($message['actor_id'] ?? $actorId), ENT_QUOTES, 'UTF-8') ?>">
                                                    <textarea name="fediverse_private_reply_text" class="form-control form-control-sm" rows="3" placeholder="Escribe tu respuesta privada"></textarea>
                                                    <button type="submit" name="send_fediverse_private_reply" class="btn btn-primary btn-sm mt-2">Responder</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                        <?php if (!$isOutgoing && (($message['visibility'] ?? '') === 'public') && !empty($message['reply_target_url'])): ?>
                                            <details class="fediverse-inline-form mt-3">
                                                <summary>Responder como nota</summary>
                                                <form method="post">
                                                    <input type="hidden" name="fediverse_tab" value="home">
                                                    <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars((string) ($message['actor_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars((string) ($message['reply_target_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="fediverse_reply_as_note" value="1">
                                                    <textarea name="fediverse_reply_text" class="form-control form-control-sm" rows="3" placeholder="Escribe tu respuesta pública"></textarea>
                                                    <button type="submit" name="fediverse_reply_item" class="btn btn-primary btn-sm mt-2">Responder</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($fediverseTab === 'network'): ?>
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
                    </div>
                </div>
            </form>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h5 mb-3">Actores seguidos</h3>
                            <?php if (empty($fediverseFollowing)): ?>
                                <p class="text-muted mb-0">Todavía no sigues ningún actor.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($fediverseFollowing as $actor): ?>
                                        <?php
                                        $actorName = trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? 'Actor')));
                                        $actorId = trim((string) ($actor['id'] ?? ''));
                                        $actorIcon = trim((string) ($actor['icon'] ?? ''));
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
                                                <form method="post" onsubmit="return confirm('¿Dejar de seguir este actor?');">
                                                    <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($actorId, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="fediverse_tab" value="network">
                                                    <button type="submit" name="unfollow_fediverse_actor" class="btn btn-outline-danger btn-sm">Dejar de seguir</button>
                                                </form>
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
                            <h3 class="h5 mb-3">Seguidores</h3>
                            <?php if (empty($fediverseFollowers)): ?>
                                <p class="text-muted mb-0">Todavía nadie sigue este actor federado.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($fediverseFollowers as $follower): ?>
                                        <?php
                                        $followerName = trim((string) (($follower['name'] ?? '') ?: ($follower['preferredUsername'] ?? 'Actor remoto')));
                                        $followerId = trim((string) ($follower['id'] ?? ''));
                                        $followerIcon = trim((string) ($follower['icon'] ?? ''));
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

        <?php elseif ($fediverseTab === 'settings'): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="h5 mb-3">Actor del blog</h3>
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label class="font-weight-bold d-block mb-1">Cuenta ActivityPub</label>
                            <code><?= htmlspecialchars($fediverseLocalHandle, ENT_QUOTES, 'UTF-8') ?></code>
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
            <div class="card">
                <div class="card-body">
                    <h3 class="h5 mb-3">Inspector ActivityPub</h3>
                    <form method="post" class="mb-3">
                        <input type="hidden" name="fediverse_tab" value="settings">
                        <label for="fediverse_inspect_url">URL del objeto</label>
                        <div class="input-group">
                            <input type="url" class="form-control" id="fediverse_inspect_url" name="fediverse_inspect_url" value="<?= htmlspecialchars((string) ($fediverseInspectUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="https://ejemplo.org/ap/objects/post-slug">
                            <div class="input-group-append">
                                <button type="submit" name="inspect_fediverse_object" class="btn btn-outline-secondary">Inspeccionar</button>
                            </div>
                        </div>
                    </form>
                    <?php if (is_array($fediverseInspectResult ?? null) && !empty($fediverseInspectResult['ok'])): ?>
                        <div class="mb-3">
                            <h4 class="h6">Objeto</h4>
                            <pre class="fediverse-debug"><?= htmlspecialchars(json_encode($fediverseInspectResult['object'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                        <div class="mb-3">
                            <h4 class="h6">Replies</h4>
                            <pre class="fediverse-debug"><?= htmlspecialchars(json_encode(($fediverseInspectResult['object']['replies'] ?? null), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                        <div class="mb-3">
                            <h4 class="h6">Colección</h4>
                            <pre class="fediverse-debug"><?= htmlspecialchars(json_encode($fediverseInspectResult['replies'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                        <div>
                            <h4 class="h6">Primera página</h4>
                            <pre class="fediverse-debug"><?= htmlspecialchars(json_encode($fediverseInspectResult['replies_page'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
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
        .fediverse-status--local {
            background: #fbfdff;
            border-top-color: #d7e6f3;
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
        .fediverse-status__content--html p,
        .fediverse-status__content--html ul,
        .fediverse-status__content--html ol,
        .fediverse-status__content--html blockquote,
        .fediverse-status__content--html pre {
            margin: 0 0 0.8rem;
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
        .fediverse-status__file--linkcard {
            gap: 0.7rem;
            overflow: hidden;
            padding: 0;
        }
        .fediverse-status__file-cover {
            display: block;
            width: 100%;
            max-height: 220px;
            object-fit: cover;
            background: #d7dee5;
        }
        .fediverse-status__file--linkcard .fediverse-status__file-name,
        .fediverse-status__file--linkcard .fediverse-status__file-meta {
            padding-inline: 1rem;
        }
        .fediverse-status__file--linkcard .fediverse-status__file-meta:last-child {
            padding-bottom: 1rem;
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
        .fediverse-status__file-meta--description {
            line-height: 1.45;
        }
        .fediverse-status__footer {
            margin-top: 0.85rem;
        }
        .fediverse-status__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            align-items: flex-start;
            margin-top: 0.9rem;
        }
        .fediverse-status__history {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.7rem;
            color: #607487;
            font-size: 0.88rem;
        }
        .fediverse-status__history span {
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background: #edf4fb;
        }
        .fediverse-inline-form {
            min-width: min(100%, 320px);
        }
        .fediverse-inline-form summary {
            cursor: pointer;
            color: #375b7d;
            font-size: 0.92rem;
            font-weight: 600;
            list-style: none;
        }
        .fediverse-inline-form summary::-webkit-details-marker {
            display: none;
        }
        .fediverse-inline-form[open] {
            flex-basis: 100%;
            max-width: 560px;
            padding: 0.8rem 0.9rem;
            border: 1px solid #d8e3ef;
            border-radius: 12px;
            background: #f8fbff;
        }
        .fediverse-inline-form form {
            margin-top: 0.7rem;
        }
        .fediverse-pagination {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            justify-content: center;
            margin-top: 1.2rem;
        }
        .fediverse-pagination__status {
            color: #607487;
            font-size: 0.92rem;
        }
        .fediverse-inline-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.7rem;
            font-size: 0.92rem;
            color: #516677;
        }
        .fediverse-thread {
            margin-top: 1rem;
            padding-left: 1rem;
            border-left: 2px solid #d8e3ef;
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        .fediverse-thread__reply {
            display: grid;
            grid-template-columns: 36px minmax(0, 1fr);
            gap: 0.65rem;
            align-items: start;
        }
        .fediverse-thread__avatar img,
        .fediverse-thread__avatar-fallback {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: block;
            object-fit: cover;
        }
        .fediverse-thread__avatar-fallback {
            background: #e7eef6;
            color: #294d6d;
            font-weight: 700;
            line-height: 36px;
            text-align: center;
        }
        .fediverse-thread__header {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: baseline;
            margin-bottom: 0.25rem;
            color: #587084;
            font-size: 0.92rem;
        }
        .fediverse-thread__content {
            white-space: pre-wrap;
            color: #1f2d39;
        }
        .fediverse-debug {
            max-height: 420px;
            overflow: auto;
            padding: 0.9rem 1rem;
            border-radius: 12px;
            background: #111827;
            color: #e5eef7;
            font-size: 0.84rem;
            line-height: 1.45;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
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
        .fediverse-message__header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .fediverse-recipient-select {
            min-height: 12.5rem;
        }
        .fediverse-message__avatar img,
        .fediverse-message__avatar-fallback {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            display: block;
            object-fit: cover;
        }
        .fediverse-message__avatar-fallback {
            background: #e7eef6;
            color: #294d6d;
            font-weight: 700;
            line-height: 42px;
            text-align: center;
        }
        .fediverse-message__identity {
            min-width: 0;
        }
        .fediverse-message__visibility {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 0.55rem;
        }
        .fediverse-visibility-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .fediverse-visibility-badge--public {
            background: #e8f6ee;
            color: #226943;
        }
        .fediverse-visibility-badge--private {
            background: #fbeceb;
            color: #8b2d2a;
        }
        .fediverse-conversation__root {
            margin-bottom: 1rem;
        }
        .fediverse-conversation__reply {
            margin-left: 2.2rem;
            border-left-width: 3px !important;
            box-shadow: inset 0 0 0 1px rgba(27, 142, 237, 0.08);
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
            .fediverse-conversation__reply {
                margin-left: 0.8rem;
            }
        }
    </style>
<?php endif; ?>
