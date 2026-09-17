<?php
/**
 * Nammu — panel de administración. Fediverso: datos y cierres compartidos por todas las pestañas (config del actor, seguidos, seguidores, bloqueados,\n * timeline, hilos y respuestas, mensajes, notificaciones, menciones y ayudantes de formato).
 * Lo incluye core/admin-page-fediverso.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
if (!function_exists('nammu_fediverse_actor_url')) {
    require_once __DIR__ . '/fediverso.php';
}
if (!function_exists('nammu_actuality_is_local_image_url') && is_file(__DIR__ . '/actualidad.php')) {
    require_once __DIR__ . '/actualidad.php';
}
$fediverseConfig = load_config_file();
$fediverseBaseUrl = nammu_fediverse_base_url($fediverseConfig);
$fediverseActorUrl = nammu_fediverse_actor_url($fediverseConfig);
$fediverseAcct = nammu_fediverse_acct_uri($fediverseConfig);
$fediverseLocalName = trim((string) (($fediverseConfig['site_name'] ?? '') ?: ($siteTitle ?? 'Blog')));
$fediverseLocalAvatar = function_exists('nammu_fediverse_avatar_url') ? nammu_fediverse_avatar_url($fediverseConfig) : '';
$fediverseValidAvatarUrl = static function (string $avatarUrl) use ($fediverseBaseUrl): string {
    $avatarUrl = trim($avatarUrl);
    if ($avatarUrl === '') {
        return '';
    }
    if (function_exists('nammu_actuality_is_local_image_url') && nammu_actuality_is_local_image_url($avatarUrl, $fediverseBaseUrl)) {
        $imagePath = trim((string) (parse_url($avatarUrl, PHP_URL_PATH) ?? ''));
        if ($imagePath === '' && !preg_match('#^https?://#i', $avatarUrl)) {
            $imagePath = '/' . ltrim($avatarUrl, '/');
        }
        $localImagePath = $imagePath !== '' ? dirname(__DIR__) . $imagePath : '';
        return ($localImagePath !== '' && is_file($localImagePath)) ? $avatarUrl : '';
    }
    return $avatarUrl;
};
$fediverseLocalAvatar = $fediverseValidAvatarUrl($fediverseLocalAvatar);
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
if (!in_array($fediverseTab, ['home', 'notifications', 'messages', 'mentions', 'network', 'settings'], true)) {
    $fediverseTab = 'home';
}
$fediverseTabs = [
    'home' => 'Inicio',
    'notifications' => 'Notificaciones',
    'messages' => 'Mensajes',
    'mentions' => 'Menciones',
    'network' => 'Red',
    'settings' => 'Configuración',
];
$isFediverseHomeTab = $fediverseTab === 'home';
$isFediverseNotificationsTab = $fediverseTab === 'notifications';
$isFediverseMessagesTab = $fediverseTab === 'messages';
$isFediverseMentionsTab = $fediverseTab === 'mentions';
$isFediverseNetworkTab = $fediverseTab === 'network';
$isFediverseSettingsTab = $fediverseTab === 'settings';
$fediverseTimelinePage = max(1, (int) ($_GET['timeline_page'] ?? 1));
$fediverseTimelinePerPage = 20;
$fediverseFragmentContext = [];
if ($fediverseTab === 'home' && $fediverseTimelinePage > 1) {
    $fediverseFragmentContext['timeline_page'] = $fediverseTimelinePage;
}
$fediverseFastVersion = function_exists('nammu_fediverse_tab_version') ? nammu_fediverse_tab_version($fediverseTab) : '';
$fediverseCachedPanelHtml = '';
$fediverseCanUseCachedPanel = $_SERVER['REQUEST_METHOD'] === 'GET'
    && empty($fediverseFeedback)
    && empty($fediverseInspectResult)
    && empty($fediverseActorInput)
    && empty($fediverseMessageText)
    && empty($fediverseMessageRecipient)
    && function_exists('nammu_fediverse_get_cached_fragment');
if ($fediverseCanUseCachedPanel) {
    $fediverseCachedPanelHtml = nammu_fediverse_get_cached_fragment($fediverseTab, $fediverseFastVersion, $fediverseFragmentContext);
}
$fediverseNeedsLivePanel = $fediverseCachedPanelHtml === '';

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
$fediverseBlocked = function_exists('nammu_fediverse_blocked_store') ? nammu_fediverse_blocked_store()['actors'] : [];
$fediverseBlockedIds = [];
foreach ($fediverseBlocked as $fediverseBlockedActor) {
    $fediverseBlockedActorId = trim((string) ($fediverseBlockedActor['id'] ?? ''));
    if ($fediverseBlockedActorId !== '') {
        $fediverseBlockedIds[$fediverseBlockedActorId] = true;
    }
}
$fediverseHomeSnapshot = ($isFediverseHomeTab && $fediverseNeedsLivePanel && function_exists('nammu_fediverse_home_snapshot_store'))
    ? (nammu_fediverse_home_snapshot_store()['data'] ?? [])
    : [];
$fediverseMessagesSnapshot = ($isFediverseMessagesTab && $fediverseNeedsLivePanel && function_exists('nammu_fediverse_messages_snapshot_store'))
    ? (nammu_fediverse_messages_snapshot_store()['data'] ?? [])
    : [];
$fediverseNotificationsSnapshot = ($isFediverseNotificationsTab && $fediverseNeedsLivePanel && function_exists('nammu_fediverse_notifications_snapshot_store'))
    ? (nammu_fediverse_notifications_snapshot_store()['data'] ?? [])
    : [];
$fediverseTimeline = ($isFediverseHomeTab && $fediverseNeedsLivePanel)
    ? (is_array($fediverseHomeSnapshot['timeline'] ?? null) ? $fediverseHomeSnapshot['timeline'] : nammu_fediverse_timeline_store()['items'])
    : [];
$fediverseRecipients = ($isFediverseMessagesTab || $isFediverseNetworkTab)
    && $fediverseNeedsLivePanel
    ? (($isFediverseMessagesTab && is_array($fediverseMessagesSnapshot['recipients'] ?? null))
        ? $fediverseMessagesSnapshot['recipients']
        : [])
    : [];
$fediverseMessages = [];
$fediversePublicReplyMessages = [];
$fediverseOutgoingPublicReplyMessages = [];
$fediverseRemotePublicReplyMessages = [];
$fediversePublicThreadRootMessages = [];
$fediverseRemoteThreadRootMessages = [];
$fediverseNotifications = $isFediverseNotificationsTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseNotificationsSnapshot['notifications'] ?? null) ? $fediverseNotificationsSnapshot['notifications'] : [])
    : [];
$fediverseNotifications = array_values(array_filter($fediverseNotifications, static function ($entry): bool {
    return is_array($entry) && !empty($entry['verified']);
}));
$fediverseWebmentions = ($isFediverseMentionsTab && $fediverseNeedsLivePanel && function_exists('nammu_webmention_list'))
    ? nammu_webmention_list()
    : [];
$fediverseLocalReactionDetails = $isFediverseHomeTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseHomeSnapshot['local_reaction_details'] ?? null) ? $fediverseHomeSnapshot['local_reaction_details'] : (function_exists('nammu_fediverse_local_reaction_details') ? nammu_fediverse_local_reaction_details($fediverseConfig) : []))
    : [];
$fediverseRemoteBoostSummary = $isFediverseHomeTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseHomeSnapshot['remote_boost_summary'] ?? null) ? $fediverseHomeSnapshot['remote_boost_summary'] : (function_exists('nammu_fediverse_remote_boost_summary') ? nammu_fediverse_remote_boost_summary() : []))
    : [];
$fediverseRemoteBoostDetails = $isFediverseHomeTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseHomeSnapshot['remote_boost_details'] ?? null) ? $fediverseHomeSnapshot['remote_boost_details'] : (function_exists('nammu_fediverse_remote_boost_details') ? nammu_fediverse_remote_boost_details($fediverseConfig) : []))
    : [];
$fediverseRemoteReplySummary = $isFediverseHomeTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseHomeSnapshot['remote_reply_summary'] ?? null) ? $fediverseHomeSnapshot['remote_reply_summary'] : (function_exists('nammu_fediverse_remote_reply_summary') ? nammu_fediverse_remote_reply_summary() : []))
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
$fediverseActorsById = [];
$fediverseActorHandleFor = static function (array $item) use (&$fediverseActorsById, $fediverseActorUrl, $fediverseLocalHandle): string {
    $actorId = trim((string) ($item['actor_id'] ?? ''));
    if ($actorId !== '' && $actorId === $fediverseActorUrl) {
        return $fediverseLocalHandle;
    }
    $username = trim((string) ($item['actor_username'] ?? ''));
    if ($username === '' && $actorId !== '' && isset($fediverseActorsById[$actorId])) {
        $username = trim((string) ($fediverseActorsById[$actorId]['preferredUsername'] ?? ''));
    }
    if ($username !== '') {
        $host = parse_url($actorId, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return '@' . $username . '@' . $host;
        }
        return '@' . $username;
    }
    return $actorId;
};
$fediverseFormatDate = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (function_exists('nammu_format_date_spanish')) {
        try {
            $date = new DateTimeImmutable($value);
            $dateLabel = nammu_format_date_spanish($date, $value);
            return trim($dateLabel . ', ' . $date->format('H:i'));
        } catch (Throwable $exception) {
        }
    }
    return $value;
};
$fediverseRenderCardDescription = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $value = str_replace(["\r\n", "\r"], "\n", strip_tags($value));
    $paragraphs = preg_split("/\n{2,}/", $value) ?: [];
    $html = [];
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim((string) $paragraph);
        if ($paragraph === '') {
            continue;
        }
        $html[] = '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    return implode('', $html);
};
$fediverseKnownActors = [];
if (($isFediverseHomeTab || $isFediverseNotificationsTab || $isFediverseMessagesTab) && $fediverseNeedsLivePanel) {
    if ($isFediverseHomeTab && is_array($fediverseHomeSnapshot['actors_by_id'] ?? null)) {
        $fediverseKnownActors = array_values($fediverseHomeSnapshot['actors_by_id']);
    } elseif ($isFediverseMessagesTab && is_array($fediverseMessagesSnapshot['actors_by_id'] ?? null)) {
        $fediverseKnownActors = array_values($fediverseMessagesSnapshot['actors_by_id']);
    } elseif ($isFediverseNotificationsTab && is_array($fediverseNotificationsSnapshot['actors_by_id'] ?? null)) {
        $fediverseKnownActors = array_values($fediverseNotificationsSnapshot['actors_by_id']);
    }
}
foreach ($fediverseKnownActors as $fediverseKnownActor) {
    $fediverseKnownActorId = trim((string) ($fediverseKnownActor['id'] ?? ''));
    if ($fediverseKnownActorId !== '') {
        $fediverseActorsById[$fediverseKnownActorId] = $fediverseKnownActor;
    }
}
foreach ($fediverseMessages as &$fediverseMessageGroup) {
    usort($fediverseMessageGroup, static function (array $a, array $b): int {
        return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
    });
}
unset($fediverseMessageGroup);
$fediverseFlatMessages = [];
$fediverseFlatMessageKeys = [];
foreach ($fediverseMessages as $fediverseMessageGroupItems) {
    foreach ((array) $fediverseMessageGroupItems as $fediverseMessageItem) {
        $fediverseMessageId = trim((string) ($fediverseMessageItem['id'] ?? ''));
        $fediverseMessageKey = $fediverseMessageId !== '' ? 'id:' . $fediverseMessageId : 'hash:' . sha1(json_encode($fediverseMessageItem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if (isset($fediverseFlatMessageKeys[$fediverseMessageKey])) {
            continue;
        }
        $fediverseFlatMessageKeys[$fediverseMessageKey] = true;
        $fediverseFlatMessages[] = $fediverseMessageItem;
    }
}
foreach (array_merge(
    $fediversePublicReplyMessages,
    $fediverseOutgoingPublicReplyMessages,
    $fediverseRemotePublicReplyMessages,
    $fediversePublicThreadRootMessages,
    $fediverseRemoteThreadRootMessages
) as $publicConversationMessage) {
    if (!is_array($publicConversationMessage)) {
        continue;
    }
    $fediverseMessageId = trim((string) ($publicConversationMessage['id'] ?? ''));
    $fediverseMessageKey = $fediverseMessageId !== '' ? 'id:' . $fediverseMessageId : 'hash:' . sha1(json_encode($publicConversationMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    if (isset($fediverseFlatMessageKeys[$fediverseMessageKey])) {
        continue;
    }
    $fediverseFlatMessageKeys[$fediverseMessageKey] = true;
    $fediverseFlatMessages[] = $publicConversationMessage;
}
$fediverseMessageThreads = $isFediverseMessagesTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseMessagesSnapshot['message_threads'] ?? null)
        ? $fediverseMessagesSnapshot['message_threads']
        : [])
    : [];
$fediverseLocalItems = $isFediverseHomeTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseHomeSnapshot['local_items'] ?? null) ? $fediverseHomeSnapshot['local_items'] : [])
    : [];
if ($isFediverseHomeTab && $fediverseNeedsLivePanel && function_exists('nammu_fediverse_actions_store') && function_exists('nammu_fediverse_resend_item_from_action')) {
    $fediverseLocalUrls = [];
    foreach ($fediverseLocalItems as $fediverseLocalBaseItem) {
        $fediverseLocalBaseUrl = trim((string) ($fediverseLocalBaseItem['url'] ?? ''));
        if ($fediverseLocalBaseUrl !== '') {
            $fediverseLocalUrls[$fediverseLocalBaseUrl] = true;
        }
    }
    foreach (nammu_fediverse_actions_store()['items'] as $fediverseAction) {
        $fediverseResendItem = nammu_fediverse_resend_item_from_action($fediverseAction);
        if (is_array($fediverseResendItem)) {
            $fediverseResendUrl = trim((string) ($fediverseResendItem['url'] ?? ''));
            if ($fediverseResendUrl !== '' && isset($fediverseLocalUrls[$fediverseResendUrl])) {
                continue;
            }
            $fediverseLocalItems[] = $fediverseResendItem;
        }
    }
}
$fediverseLocalReactionSummary = $isFediverseHomeTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseHomeSnapshot['local_reaction_summary'] ?? null) ? $fediverseHomeSnapshot['local_reaction_summary'] : [])
    : [];
$fediverseIncomingReplies = $isFediverseHomeTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseHomeSnapshot['incoming_replies'] ?? null) ? $fediverseHomeSnapshot['incoming_replies'] : [])
    : [];
$fediverseEquivalentIdentifiers = static function (string $identifier): array {
    $identifier = trim($identifier);
    if ($identifier === '') {
        return [];
    }
    $variants = [$identifier];
    $path = trim((string) (parse_url($identifier, PHP_URL_PATH) ?? ''));
    if ($path !== '') {
        if (str_ends_with($path, '/activity')) {
            $variants[] = preg_replace('#/activity$#', '', $identifier) ?? $identifier;
        } elseif (preg_match('#^/ap/(objects|notes|replies)/[^/]+$#', $path) === 1) {
            $variants[] = rtrim($identifier, '/') . '/activity';
        }
    }
    return array_values(array_unique(array_filter(array_map('strval', $variants))));
};
$fediverseReplyActorAvatar = static function (array $reply, string $fallback = '') use ($fediverseActorsById, $fediverseConfig, $fediverseValidAvatarUrl): string {
    $actorId = trim((string) ($reply['actor_id'] ?? ''));
    $avatarCandidates = [
        trim((string) ($reply['actor_icon'] ?? '')),
    ];
    if ($actorId !== '' && is_array($fediverseActorsById[$actorId] ?? null)) {
        $actor = $fediverseActorsById[$actorId];
        $avatarCandidates[] = trim((string) (($actor['avatar_remote_url'] ?? '') ?: ''));
        $avatarCandidates[] = trim((string) (($actor['icon'] ?? '') ?: ''));
    }
    if ($actorId !== '' && function_exists('nammu_fediverse_cached_actor_avatar_for_reference')) {
        $avatarCandidates[] = trim((string) nammu_fediverse_cached_actor_avatar_for_reference($actorId, $fediverseConfig));
    }
    $replyReference = trim((string) (($reply['url'] ?? '') ?: ($reply['id'] ?? '')));
    if ($replyReference !== '' && function_exists('nammu_fediverse_cached_actor_avatar_for_reference')) {
        $avatarCandidates[] = trim((string) nammu_fediverse_cached_actor_avatar_for_reference($replyReference, $fediverseConfig));
    }
    $avatarCandidates[] = trim($fallback);
    foreach ($avatarCandidates as $avatarCandidate) {
        $avatarUrl = $fediverseValidAvatarUrl((string) $avatarCandidate);
        if ($avatarUrl !== '') {
            return $avatarUrl;
        }
    }
    return '';
};
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
        $fediverseTimelineReplyActorId = trim((string) ($fediverseTimelineReplyCandidate['actor_id'] ?? ''));
        $fediverseRemoteReplyPayload = [
            'id' => trim((string) ($fediverseTimelineReplyCandidate['id'] ?? '')),
            'note_id' => trim((string) (($fediverseTimelineReplyCandidate['object_id'] ?? '') ?: ($fediverseTimelineReplyCandidate['id'] ?? ''))),
            'url' => trim((string) ($fediverseTimelineReplyCandidate['url'] ?? '')),
            'published' => trim((string) ($fediverseTimelineReplyCandidate['published'] ?? '')),
            'reply_text' => $fediverseTimelineReplyText,
            'actor_id' => $fediverseTimelineReplyActorId,
            'actor_name' => trim((string) ($fediverseTimelineReplyCandidate['actor_name'] ?? '')),
            'actor_icon' => $fediverseReplyActorAvatar([
                'actor_id' => $fediverseTimelineReplyActorId,
                'actor_icon' => trim((string) ($fediverseTimelineReplyCandidate['actor_icon'] ?? '')),
                'url' => trim((string) ($fediverseTimelineReplyCandidate['url'] ?? '')),
                'id' => trim((string) (($fediverseTimelineReplyCandidate['object_id'] ?? '') ?: ($fediverseTimelineReplyCandidate['id'] ?? ''))),
            ]),
            'source' => 'incoming-remote',
        ];
        foreach ($fediverseEquivalentIdentifiers($fediverseTimelineReplyTarget) as $fediverseTimelineReplyTargetIdentifier) {
            if (!isset($fediverseRemoteRepliesByTarget[$fediverseTimelineReplyTargetIdentifier])) {
                $fediverseRemoteRepliesByTarget[$fediverseTimelineReplyTargetIdentifier] = [];
            }
            $fediverseRemoteRepliesByTarget[$fediverseTimelineReplyTargetIdentifier][] = $fediverseRemoteReplyPayload;
        }
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
$fediverseLocalRootByIdentifier = [];
foreach ($fediverseLocalItems as $fediverseLocalItem) {
    if (!is_array($fediverseLocalItem)) {
        continue;
    }
    $fediverseLocalRootId = trim((string) ($fediverseLocalItem['id'] ?? ''));
    if ($fediverseLocalRootId === '') {
        continue;
    }
    foreach (['id', 'object_id', 'url'] as $fediverseLocalRootField) {
        $fediverseLocalRootIdentifier = trim((string) ($fediverseLocalItem[$fediverseLocalRootField] ?? ''));
        foreach ($fediverseEquivalentIdentifiers($fediverseLocalRootIdentifier) as $fediverseLocalRootVariant) {
            $fediverseLocalRootByIdentifier[$fediverseLocalRootVariant] = $fediverseLocalRootId;
        }
    }
    if (function_exists('nammu_fediverse_item_identifiers_with_canonical')) {
        foreach (nammu_fediverse_item_identifiers_with_canonical($fediverseLocalItem, $fediverseConfig) as $fediverseLocalRootIdentifier) {
            foreach ($fediverseEquivalentIdentifiers((string) $fediverseLocalRootIdentifier) as $fediverseLocalRootVariant) {
                $fediverseLocalRootByIdentifier[$fediverseLocalRootVariant] = $fediverseLocalRootId;
            }
        }
    }
}
$fediverseThreadLatestReplyActivity = [];
$rememberFediverseThreadReplyActivity = static function (string $targetIdentifier, string $published) use (&$fediverseThreadLatestReplyActivity, $fediverseEquivalentIdentifiers): void {
    $targetIdentifier = trim($targetIdentifier);
    $published = trim($published);
    if ($targetIdentifier === '' || $published === '') {
        return;
    }
    foreach ($fediverseEquivalentIdentifiers($targetIdentifier) as $targetVariant) {
        if (!isset($fediverseThreadLatestReplyActivity[$targetVariant]) || strcmp($published, (string) $fediverseThreadLatestReplyActivity[$targetVariant]) > 0) {
            $fediverseThreadLatestReplyActivity[$targetVariant] = $published;
        }
    }
};
$fediverseOutgoingReplyRootByIdentifier = [];
$rememberFediverseOutgoingReplyRoot = static function (string $replyIdentifier, string $rootIdentifier) use (&$fediverseOutgoingReplyRootByIdentifier, $fediverseEquivalentIdentifiers): void {
    $replyIdentifier = trim($replyIdentifier);
    $rootIdentifier = trim($rootIdentifier);
    if ($replyIdentifier === '' || $rootIdentifier === '') {
        return;
    }
    foreach ($fediverseEquivalentIdentifiers($replyIdentifier) as $replyVariant) {
        $fediverseOutgoingReplyRootByIdentifier[$replyVariant] = $rootIdentifier;
    }
};
foreach (nammu_fediverse_actions_store()['items'] as $fediverseActionItem) {
    if (!is_array($fediverseActionItem) || strtolower(trim((string) ($fediverseActionItem['type'] ?? ''))) !== 'reply') {
        continue;
    }
    $fediverseReplyRootIdentifier = trim((string) ($fediverseActionItem['object_url'] ?? ''));
    if ($fediverseReplyRootIdentifier === '') {
        continue;
    }
    $rememberFediverseOutgoingReplyRoot((string) ($fediverseActionItem['note_id'] ?? ''), $fediverseReplyRootIdentifier);
    $rememberFediverseOutgoingReplyRoot((string) ($fediverseActionItem['activity_id'] ?? ''), $fediverseReplyRootIdentifier);
}
$fediverseInboxStoreForReplies = function_exists('nammu_fediverse_load_json_store') && function_exists('nammu_fediverse_inbox_file')
    ? nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []])
    : ['activities' => []];
$fediverseReplyRootByIdentifier = $fediverseLocalRootByIdentifier;
$fediverseInboxActivityEntriesForReplies = array_values((array) ($fediverseInboxStoreForReplies['activities'] ?? []));
usort($fediverseInboxActivityEntriesForReplies, static function (array $a, array $b): int {
    $payloadA = is_array($a['payload'] ?? null) ? $a['payload'] : [];
    $payloadB = is_array($b['payload'] ?? null) ? $b['payload'] : [];
    $objectA = is_array($payloadA['object'] ?? null) ? $payloadA['object'] : [];
    $objectB = is_array($payloadB['object'] ?? null) ? $payloadB['object'] : [];
    $publishedA = (string) (($objectA['published'] ?? '') ?: ($payloadA['published'] ?? '') ?: ($a['received_at'] ?? ''));
    $publishedB = (string) (($objectB['published'] ?? '') ?: ($payloadB['published'] ?? '') ?: ($b['received_at'] ?? ''));
    return strcmp($publishedA, $publishedB);
});
foreach ($fediverseInboxActivityEntriesForReplies as $fediverseInboxActivityEntry) {
    if (!is_array($fediverseInboxActivityEntry)) {
        continue;
    }
    $fediverseInboxPayload = is_array($fediverseInboxActivityEntry['payload'] ?? null) ? $fediverseInboxActivityEntry['payload'] : [];
    if (strtolower(trim((string) ($fediverseInboxPayload['type'] ?? ''))) !== 'create') {
        continue;
    }
    $fediverseInboxObject = is_array($fediverseInboxPayload['object'] ?? null) ? $fediverseInboxPayload['object'] : [];
    if (strtolower(trim((string) ($fediverseInboxObject['type'] ?? ''))) !== 'note') {
        continue;
    }
    $fediverseInboxReplyTarget = trim((string) ($fediverseInboxObject['inReplyTo'] ?? ''));
    if ($fediverseInboxReplyTarget === '') {
        continue;
    }
    $fediverseCanonicalInboxReplyRoot = function_exists('nammu_fediverse_canonical_local_id_for_identifier')
        ? trim((string) nammu_fediverse_canonical_local_id_for_identifier($fediverseInboxReplyTarget, $fediverseConfig))
        : '';
    if ($fediverseCanonicalInboxReplyRoot !== '') {
        $fediverseReplyPublished = trim((string) (($fediverseInboxObject['published'] ?? '') ?: ($fediverseInboxPayload['published'] ?? '') ?: ($fediverseInboxActivityEntry['received_at'] ?? '')));
        $rememberFediverseThreadReplyActivity($fediverseCanonicalInboxReplyRoot, $fediverseReplyPublished);
        foreach (['id', 'url'] as $fediverseInboxReplyIdentifierField) {
            $fediverseInboxReplyIdentifier = trim((string) ($fediverseInboxObject[$fediverseInboxReplyIdentifierField] ?? ''));
            foreach ($fediverseEquivalentIdentifiers($fediverseInboxReplyIdentifier) as $fediverseInboxReplyIdentifierVariant) {
                $fediverseReplyRootByIdentifier[$fediverseInboxReplyIdentifierVariant] = $fediverseCanonicalInboxReplyRoot;
            }
        }
        continue;
    }
    foreach ($fediverseEquivalentIdentifiers($fediverseInboxReplyTarget) as $fediverseInboxReplyTargetVariant) {
        $fediverseReplyRootIdentifier = (string) (($fediverseReplyRootByIdentifier[$fediverseInboxReplyTargetVariant] ?? '') ?: ($fediverseOutgoingReplyRootByIdentifier[$fediverseInboxReplyTargetVariant] ?? ''));
        if ($fediverseReplyRootIdentifier === '') {
            continue;
        }
        $fediverseReplyPublished = trim((string) (($fediverseInboxObject['published'] ?? '') ?: ($fediverseInboxPayload['published'] ?? '') ?: ($fediverseInboxActivityEntry['received_at'] ?? '')));
        $rememberFediverseThreadReplyActivity($fediverseReplyRootIdentifier, $fediverseReplyPublished);
        foreach (['id', 'url'] as $fediverseInboxReplyIdentifierField) {
            $fediverseInboxReplyIdentifier = trim((string) ($fediverseInboxObject[$fediverseInboxReplyIdentifierField] ?? ''));
            foreach ($fediverseEquivalentIdentifiers($fediverseInboxReplyIdentifier) as $fediverseInboxReplyIdentifierVariant) {
                $fediverseReplyRootByIdentifier[$fediverseInboxReplyIdentifierVariant] = $fediverseReplyRootIdentifier;
            }
        }
    }
}
foreach ($fediverseIncomingReplies as $fediverseIncomingLocalId => $fediverseIncomingReplyGroup) {
    foreach ((array) $fediverseIncomingReplyGroup as $fediverseIncomingReply) {
        if (!is_array($fediverseIncomingReply)) {
            continue;
        }
        $rememberFediverseThreadReplyActivity((string) $fediverseIncomingLocalId, (string) ($fediverseIncomingReply['published'] ?? ''));
    }
}
foreach ($fediverseRemoteRepliesByTarget as $fediverseRemoteReplyTarget => $fediverseRemoteReplyGroup) {
    foreach ((array) $fediverseRemoteReplyGroup as $fediverseRemoteReply) {
        if (!is_array($fediverseRemoteReply)) {
            continue;
        }
        $rememberFediverseThreadReplyActivity((string) $fediverseRemoteReplyTarget, (string) ($fediverseRemoteReply['published'] ?? ''));
    }
}
$fediverseTimelineRootByIdentifier = $fediverseLocalRootByIdentifier;
$fediverseTimelineReplyItemsForActivity = array_values(array_filter($fediverseTimeline, static function ($item): bool {
    return is_array($item)
        && strtolower(trim((string) ($item['type'] ?? ''))) !== 'announce'
        && trim((string) ($item['target_url'] ?? '')) !== '';
}));
usort($fediverseTimelineReplyItemsForActivity, static function (array $a, array $b): int {
    return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
});
foreach ($fediverseTimelineReplyItemsForActivity as $fediverseTimelineReplyItemForActivity) {
    $fediverseTimelineReplyTarget = trim((string) ($fediverseTimelineReplyItemForActivity['target_url'] ?? ''));
    $fediverseTimelineReplyRootIdentifier = function_exists('nammu_fediverse_canonical_local_id_for_identifier')
        ? trim((string) nammu_fediverse_canonical_local_id_for_identifier($fediverseTimelineReplyTarget, $fediverseConfig))
        : '';
    foreach ($fediverseEquivalentIdentifiers($fediverseTimelineReplyTarget) as $fediverseTimelineReplyTargetVariant) {
        if ($fediverseTimelineReplyRootIdentifier !== '') {
            break;
        }
        $fediverseTimelineReplyRootIdentifier = (string) ($fediverseTimelineRootByIdentifier[$fediverseTimelineReplyTargetVariant] ?? '');
        if ($fediverseTimelineReplyRootIdentifier !== '') {
            break;
        }
    }
    if ($fediverseTimelineReplyRootIdentifier === '') {
        continue;
    }
    $rememberFediverseThreadReplyActivity($fediverseTimelineReplyRootIdentifier, (string) ($fediverseTimelineReplyItemForActivity['published'] ?? ''));
    foreach (['id', 'activity_id', 'object_id', 'url'] as $fediverseTimelineReplyIdentifierField) {
        $fediverseTimelineReplyIdentifier = trim((string) ($fediverseTimelineReplyItemForActivity[$fediverseTimelineReplyIdentifierField] ?? ''));
        foreach ($fediverseEquivalentIdentifiers($fediverseTimelineReplyIdentifier) as $fediverseTimelineReplyIdentifierVariant) {
            $fediverseTimelineRootByIdentifier[$fediverseTimelineReplyIdentifierVariant] = $fediverseTimelineReplyRootIdentifier;
        }
    }
}
if (is_array($fediverseHomeSnapshot['thread_payloads'] ?? null)) {
    foreach ((array) $fediverseHomeSnapshot['thread_payloads'] as $fediverseThreadPayloadItemId => $fediverseThreadPayload) {
        if (!is_array($fediverseThreadPayload)) {
            continue;
        }
        foreach ((array) ($fediverseThreadPayload['replies'] ?? []) as $fediverseThreadPayloadReply) {
            if (!is_array($fediverseThreadPayloadReply)) {
                continue;
            }
            $rememberFediverseThreadReplyActivity((string) $fediverseThreadPayloadItemId, (string) ($fediverseThreadPayloadReply['published'] ?? ''));
        }
    }
}
$fediverseTimelineEntries = [];
foreach ($fediverseLocalItems as $fediverseLocalItem) {
    $fediverseLocalId = trim((string) ($fediverseLocalItem['id'] ?? ''));
    $fediverseLocalSortKey = (string) ($fediverseLocalItem['published'] ?? '');
    $fediverseLocalSortIdentifiers = [];
    foreach (['id', 'object_id', 'url'] as $fediverseLocalSortField) {
        $fediverseLocalSortIdentifiers[] = trim((string) ($fediverseLocalItem[$fediverseLocalSortField] ?? ''));
    }
    if (function_exists('nammu_fediverse_item_identifiers_with_canonical')) {
        foreach (nammu_fediverse_item_identifiers_with_canonical($fediverseLocalItem, $fediverseConfig) as $fediverseLocalSortIdentifier) {
            $fediverseLocalSortIdentifiers[] = (string) $fediverseLocalSortIdentifier;
        }
    }
    foreach (array_unique(array_filter($fediverseLocalSortIdentifiers)) as $fediverseLocalSortIdentifier) {
        foreach ($fediverseEquivalentIdentifiers($fediverseLocalSortIdentifier) as $fediverseLocalSortVariant) {
            $fediverseLocalReplyActivity = (string) ($fediverseThreadLatestReplyActivity[$fediverseLocalSortVariant] ?? '');
            if ($fediverseLocalReplyActivity !== '' && strcmp($fediverseLocalReplyActivity, $fediverseLocalSortKey) > 0) {
                $fediverseLocalSortKey = $fediverseLocalReplyActivity;
            }
        }
    }
    $fediverseTimelineEntries[] = [
        'kind' => 'local',
        'published' => (string) ($fediverseLocalItem['published'] ?? ''),
        'sort_key' => $fediverseLocalSortKey,
        'item' => $fediverseLocalItem,
    ];
}
$fediverseExistingRemoteObjectIds = [];
foreach ($fediverseTimeline as $fediverseTimelineCandidate) {
    if (!is_array($fediverseTimelineCandidate)) {
        continue;
    }
    if (strtolower(trim((string) ($fediverseTimelineCandidate['type'] ?? ''))) === 'announce') {
        continue;
    }
    $fediverseExistingRemoteObjectId = trim((string) (($fediverseTimelineCandidate['object_id'] ?? '') ?: ($fediverseTimelineCandidate['id'] ?? '')));
    if ($fediverseExistingRemoteObjectId !== '') {
        $fediverseExistingRemoteObjectIds[$fediverseExistingRemoteObjectId] = true;
    }
}
$fediverseSyntheticRemoteItems = [];
if ($isFediverseHomeTab && function_exists('nammu_fediverse_cluster_actuality_item_for_object_id')) {
    foreach ($fediverseTimeline as $fediverseTimelineCandidate) {
        if (!is_array($fediverseTimelineCandidate)) {
            continue;
        }
        if (strtolower(trim((string) ($fediverseTimelineCandidate['type'] ?? ''))) !== 'announce') {
            continue;
        }
        $fediverseSyntheticObjectId = trim((string) ($fediverseTimelineCandidate['object_id'] ?? ''));
        if ($fediverseSyntheticObjectId === '' || isset($fediverseExistingRemoteObjectIds[$fediverseSyntheticObjectId]) || isset($fediverseSyntheticRemoteItems[$fediverseSyntheticObjectId])) {
            continue;
        }
        $fediverseClusterActualityItem = nammu_fediverse_cluster_actuality_item_for_object_id($fediverseSyntheticObjectId, $fediverseConfig);
        if (!is_array($fediverseClusterActualityItem)) {
            continue;
        }
        $fediverseSyntheticPublished = (int) (($fediverseClusterActualityItem['timestamp'] ?? 0) ?: 0);
        $fediverseSyntheticUrl = trim((string) ($fediverseClusterActualityItem['link'] ?? ''));
        $fediverseSyntheticTitle = trim((string) ($fediverseClusterActualityItem['title'] ?? ''));
        $fediverseSyntheticContent = trim((string) (($fediverseClusterActualityItem['raw_text'] ?? '') ?: ($fediverseClusterActualityItem['description'] ?? '')));
        $fediverseSyntheticSummary = trim((string) ($fediverseClusterActualityItem['description'] ?? ''));
        $fediverseSyntheticImage = trim((string) (($fediverseClusterActualityItem['source_image'] ?? '') ?: ($fediverseClusterActualityItem['image'] ?? '')));
        $fediverseSyntheticHost = strtolower(trim((string) parse_url($fediverseSyntheticObjectId, PHP_URL_HOST)));
        $fediverseSyntheticActorName = $fediverseSyntheticHost !== '' ? preg_replace('/^www\./', '', $fediverseSyntheticHost) : 'Sitio remoto';
        $fediverseSyntheticAttachments = [];
        if ($fediverseSyntheticUrl !== '') {
            $fediverseSyntheticAttachments[] = [
                'type' => 'link',
                'url' => $fediverseSyntheticUrl,
                'name' => $fediverseSyntheticTitle !== '' ? $fediverseSyntheticTitle : 'Abrir publicación',
                'media_type' => 'text/html',
                'image' => $fediverseSyntheticImage,
                'summary' => $fediverseSyntheticSummary !== '' ? $fediverseSyntheticSummary : $fediverseSyntheticContent,
            ];
        }
        if ($fediverseSyntheticImage !== '') {
            $fediverseSyntheticAttachments[] = [
                'type' => 'image',
                'url' => $fediverseSyntheticImage,
                'name' => $fediverseSyntheticTitle,
                'media_type' => 'image/*',
            ];
        }
        $fediverseSyntheticRemoteItems[$fediverseSyntheticObjectId] = [
            'id' => $fediverseSyntheticObjectId,
            'activity_id' => $fediverseSyntheticObjectId,
            'object_id' => $fediverseSyntheticObjectId,
            'url' => $fediverseSyntheticUrl,
            'target_url' => '',
            'title' => $fediverseSyntheticTitle,
            'content' => $fediverseSyntheticContent,
            'content_html' => '',
            'summary' => $fediverseSyntheticSummary,
            'published' => $fediverseSyntheticPublished > 0 ? gmdate(DATE_ATOM, $fediverseSyntheticPublished) : trim((string) ($fediverseTimelineCandidate['published'] ?? '')),
            'type' => 'article',
            'image' => $fediverseSyntheticImage,
            'attachments' => $fediverseSyntheticAttachments,
            'actor_id' => $fediverseSyntheticHost !== '' ? ('https://' . $fediverseSyntheticHost) : '',
            'actor_name' => $fediverseSyntheticActorName,
            'actor_username' => '',
            'actor_url' => $fediverseSyntheticUrl !== '' ? $fediverseSyntheticUrl : ($fediverseSyntheticHost !== '' ? ('https://' . $fediverseSyntheticHost) : ''),
            'actor_icon' => '',
        ];
    }
}
if (!empty($fediverseSyntheticRemoteItems)) {
    $fediverseTimeline = array_merge(array_values($fediverseSyntheticRemoteItems), $fediverseTimeline);
}
$fediverseTimelineDisplay = [];
$fediverseAnnounceEntryIndexes = [];
$fediverseRemoteCanonicalItems = [];
foreach ($fediverseTimeline as $fediverseTimelineCandidate) {
    if (!is_array($fediverseTimelineCandidate)) {
        continue;
    }
    if (strtolower(trim((string) ($fediverseTimelineCandidate['type'] ?? ''))) === 'announce') {
        continue;
    }
    foreach (['object_id', 'url', 'id'] as $fediverseCanonicalField) {
        $fediverseCanonicalValue = trim((string) ($fediverseTimelineCandidate[$fediverseCanonicalField] ?? ''));
        foreach ($fediverseEquivalentIdentifiers($fediverseCanonicalValue) as $fediverseCanonicalIdentifier) {
            $fediverseRemoteCanonicalItems[$fediverseCanonicalIdentifier] = true;
        }
        if ($fediverseCanonicalValue !== '') {
            $fediverseRemoteCanonicalItems[$fediverseCanonicalValue] = true;
        }
    }
}
foreach ($fediverseTimeline as $fediverseTimelineItem) {
    $fediverseTimelineType = strtolower(trim((string) ($fediverseTimelineItem['type'] ?? '')));
    $fediverseAnnounceGroupKey = '';
    if (in_array($fediverseTimelineType, ['like', 'delete'], true)) {
        continue;
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
    if ($fediverseTimelineType === 'announce') {
        $fediverseAnnounceTargetsLocal = false;
        $fediverseAnnounceTargetsClusterContent = false;
        foreach ($fediverseTimelineIdentifiers as $fediverseTimelineIdentifier) {
            if ($fediverseTimelineIdentifier !== '' && function_exists('nammu_fediverse_canonical_local_id_for_identifier')) {
                $fediverseCanonicalLocalId = nammu_fediverse_canonical_local_id_for_identifier($fediverseTimelineIdentifier, $fediverseConfig);
                if ($fediverseCanonicalLocalId !== '') {
                    $fediverseAnnounceTargetsLocal = true;
                    break;
                }
            }
            $fediverseTimelineIdentifierHost = strtolower(trim((string) parse_url($fediverseTimelineIdentifier, PHP_URL_HOST)));
            $fediverseTimelineIdentifierPath = trim((string) (parse_url($fediverseTimelineIdentifier, PHP_URL_PATH) ?? ''));
            $fediverseLocalHostForAnnounce = strtolower(trim((string) parse_url($fediverseBaseUrl, PHP_URL_HOST)));
            if (
                $fediverseTimelineIdentifierHost !== ''
                && $fediverseTimelineIdentifierHost !== $fediverseLocalHostForAnnounce
                && preg_match('#^/ap/objects/(actualidad|post|podcast|itinerary)-[^/]+$#', $fediverseTimelineIdentifierPath) === 1
                && function_exists('nammu_fediverse_cluster_site_dir_for_host')
                && nammu_fediverse_cluster_site_dir_for_host($fediverseTimelineIdentifierHost, $fediverseConfig) !== ''
            ) {
                $fediverseAnnounceTargetsClusterContent = true;
            }
        }
        if (!$fediverseAnnounceTargetsLocal) {
            $fediverseTimelineItemUrl = trim((string) ($fediverseTimelineItem['url'] ?? ''));
            if ($fediverseTimelineItemUrl !== '' && function_exists('nammu_fediverse_equivalent_local_items_by_url')) {
                $fediverseAnnounceTargetsLocal = !empty(nammu_fediverse_equivalent_local_items_by_url($fediverseTimelineItemUrl, $fediverseConfig));
            }
        }
        if ($fediverseAnnounceTargetsLocal || $fediverseAnnounceTargetsClusterContent) {
            continue;
        }
        $fediverseAnnounceDuplicatesExisting = false;
        foreach ($fediverseTimelineIdentifiers as $fediverseTimelineIdentifier) {
            if (isset($fediverseRemoteCanonicalItems[$fediverseTimelineIdentifier])) {
                $fediverseAnnounceDuplicatesExisting = true;
                break;
            }
        }
        if ($fediverseAnnounceDuplicatesExisting) {
            continue;
        }
    }
    if ($fediverseTimelineTargetUrl !== '' && $fediverseTimelineType !== 'announce') {
        $fediverseTimelineTargetsLocal = false;
        if (function_exists('nammu_fediverse_canonical_local_id_for_identifier')) {
            $fediverseTimelineTargetsLocal = trim((string) nammu_fediverse_canonical_local_id_for_identifier($fediverseTimelineTargetUrl, $fediverseConfig)) !== '';
        }
        if ($fediverseTimelineTargetsLocal) {
            continue;
        }
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
    $fediverseTimelineHadLinkCard = false;
    $fediverseTimelineRenderableAttachments = [];
    foreach ($fediverseTimelineAttachments as $fediverseTimelineAttachmentIndex => $fediverseTimelineAttachment) {
        if (!is_array($fediverseTimelineAttachment)) {
            continue;
        }
        $fediverseTimelineAttachmentType = strtolower(trim((string) ($fediverseTimelineAttachment['type'] ?? '')));
        $fediverseTimelineAttachmentMediaType = strtolower(trim((string) ($fediverseTimelineAttachment['media_type'] ?? '')));
        if ($fediverseTimelineAttachmentType === 'link' || $fediverseTimelineAttachmentMediaType === 'text/html' || str_starts_with($fediverseTimelineAttachmentMediaType, 'text/html')) {
            $fediverseTimelineHadLinkCard = true;
            continue;
        }
        $fediverseTimelineRenderableAttachments[] = $fediverseTimelineAttachment;
    }
    $fediverseTimelineAttachments = $fediverseTimelineRenderableAttachments;
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
    if (empty($fediverseTimelineAttachments) && !$fediverseTimelineHadLinkCard && trim((string) ($fediverseTimelineItem['image'] ?? '')) !== '') {
        $fediverseTimelineAttachments[] = [
            'type' => 'image',
            'url' => trim((string) $fediverseTimelineItem['image']),
            'name' => '',
            'media_type' => 'image/*',
        ];
    }
    if ($fediverseTimelineType === 'announce'
        && trim((string) ($fediverseTimelineItem['title'] ?? '')) === ''
        && trim((string) ($fediverseTimelineItem['content'] ?? '')) === ''
        && trim((string) ($fediverseTimelineItem['content_html'] ?? '')) === ''
        && empty($fediverseTimelineAttachments)
    ) {
        $fediverseBoostTargetUrl = '';
        foreach (['object_id', 'url', 'id'] as $fediverseBoostField) {
            $fediverseBoostValue = trim((string) ($fediverseTimelineItem[$fediverseBoostField] ?? ''));
            if ($fediverseBoostValue !== '') {
                $fediverseBoostTargetUrl = $fediverseBoostValue;
                break;
            }
        }
        if ($fediverseBoostTargetUrl !== '') {
            $fediverseTimelineItem['content'] = 'Ha impulsado una publicación';
        }
    }
    if (trim((string) ($fediverseTimelineItem['content'] ?? '')) === '' && trim((string) ($fediverseTimelineItem['content_html'] ?? '')) !== '' && function_exists('nammu_fediverse_html_to_text')) {
        $fediverseTimelineItem['content'] = nammu_fediverse_html_to_text((string) $fediverseTimelineItem['content_html']);
    }
    $fediverseTimelineItem['attachments'] = $fediverseTimelineAttachments;
    if (trim((string) ($fediverseTimelineItem['title'] ?? '')) === '' && trim((string) ($fediverseTimelineItem['content'] ?? '')) === '' && empty($fediverseTimelineAttachments)) {
        continue;
    }
    if ($fediverseTimelineType === 'announce') {
        $fediverseAnnounceGroupKey = '';
        foreach (['object_id', 'url', 'id'] as $fediverseAnnounceField) {
            $fediverseAnnounceValue = trim((string) ($fediverseTimelineItem[$fediverseAnnounceField] ?? ''));
            if ($fediverseAnnounceValue === '') {
                continue;
            }
            $fediverseAnnounceVariants = $fediverseEquivalentIdentifiers($fediverseAnnounceValue);
            if (!empty($fediverseAnnounceVariants)) {
                usort($fediverseAnnounceVariants, static function (string $a, string $b): int {
                    return strlen($a) <=> strlen($b);
                });
                $fediverseAnnounceGroupKey = (string) ($fediverseAnnounceVariants[0] ?? $fediverseAnnounceValue);
            } else {
                $fediverseAnnounceGroupKey = $fediverseAnnounceValue;
            }
            if ($fediverseAnnounceGroupKey !== '') {
                break;
            }
        }
        if ($fediverseAnnounceGroupKey !== '' && isset($fediverseAnnounceEntryIndexes[$fediverseAnnounceGroupKey])) {
            $fediverseExistingEntryIndex = (int) $fediverseAnnounceEntryIndexes[$fediverseAnnounceGroupKey];
            $fediverseExistingEntry = is_array($fediverseTimelineEntries[$fediverseExistingEntryIndex] ?? null) ? $fediverseTimelineEntries[$fediverseExistingEntryIndex] : null;
            $fediverseExistingItem = is_array($fediverseExistingEntry['item'] ?? null) ? $fediverseExistingEntry['item'] : [];
            $fediverseAnnounceActors = is_array($fediverseExistingItem['announce_actors'] ?? null) ? $fediverseExistingItem['announce_actors'] : [];
            $fediverseAnnounceActorId = trim((string) ($fediverseTimelineItem['actor_id'] ?? ''));
            $fediverseAnnounceActorKey = $fediverseAnnounceActorId !== ''
                ? $fediverseAnnounceActorId
                : sha1(json_encode([
                    'name' => (string) ($fediverseTimelineItem['actor_name'] ?? ''),
                    'username' => (string) ($fediverseTimelineItem['actor_username'] ?? ''),
                    'url' => (string) ($fediverseTimelineItem['actor_url'] ?? ''),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $fediverseAnnounceActors[$fediverseAnnounceActorKey] = [
                'id' => $fediverseAnnounceActorId,
                'name' => trim((string) ($fediverseTimelineItem['actor_name'] ?? '')),
                'username' => trim((string) ($fediverseTimelineItem['actor_username'] ?? '')),
                'url' => trim((string) (($fediverseTimelineItem['actor_url'] ?? '') ?: $fediverseAnnounceActorId)),
                'icon' => trim((string) ($fediverseTimelineItem['actor_icon'] ?? '')),
                'published' => trim((string) ($fediverseTimelineItem['published'] ?? '')),
            ];
            $fediverseExistingItem['announce_actors'] = $fediverseAnnounceActors;
            if (strcmp((string) ($fediverseTimelineItem['published'] ?? ''), (string) ($fediverseExistingEntry['sort_key'] ?? '')) > 0) {
                $fediverseExistingEntry['sort_key'] = (string) ($fediverseTimelineItem['published'] ?? '');
            }
            $fediverseExistingEntry['item'] = $fediverseExistingItem;
            $fediverseTimelineEntries[$fediverseExistingEntryIndex] = $fediverseExistingEntry;
            continue;
        }
        $fediverseTimelineItem['announce_actors'] = [[
            'id' => trim((string) ($fediverseTimelineItem['actor_id'] ?? '')),
            'name' => trim((string) ($fediverseTimelineItem['actor_name'] ?? '')),
            'username' => trim((string) ($fediverseTimelineItem['actor_username'] ?? '')),
            'url' => trim((string) (($fediverseTimelineItem['actor_url'] ?? '') ?: ($fediverseTimelineItem['actor_id'] ?? ''))),
            'icon' => trim((string) ($fediverseTimelineItem['actor_icon'] ?? '')),
            'published' => trim((string) ($fediverseTimelineItem['published'] ?? '')),
        ]];
    }
    $fediverseTimelineDisplay[] = $fediverseTimelineItem;
    $fediverseRemoteSortKey = (string) ($fediverseTimelineItem['published'] ?? '');
    foreach (['id', 'object_id', 'url'] as $fediverseRemoteSortField) {
        $fediverseRemoteSortIdentifier = trim((string) ($fediverseTimelineItem[$fediverseRemoteSortField] ?? ''));
        foreach ($fediverseEquivalentIdentifiers($fediverseRemoteSortIdentifier) as $fediverseRemoteSortVariant) {
            $fediverseRemoteReplyActivity = (string) ($fediverseThreadLatestReplyActivity[$fediverseRemoteSortVariant] ?? '');
            if ($fediverseRemoteReplyActivity !== '' && strcmp($fediverseRemoteReplyActivity, $fediverseRemoteSortKey) > 0) {
                $fediverseRemoteSortKey = $fediverseRemoteReplyActivity;
            }
        }
    }
    $fediverseTimelineEntries[] = [
        'kind' => 'remote',
        'published' => (string) ($fediverseTimelineItem['published'] ?? ''),
        'sort_key' => $fediverseRemoteSortKey,
        'item' => $fediverseTimelineItem,
    ];
    if ($fediverseTimelineType === 'announce' && !empty($fediverseAnnounceGroupKey)) {
        $fediverseAnnounceEntryIndexes[$fediverseAnnounceGroupKey] = count($fediverseTimelineEntries) - 1;
    }
}
usort($fediverseTimelineEntries, static function (array $a, array $b): int {
    $sortCompare = strcmp((string) ($b['sort_key'] ?? ''), (string) ($a['sort_key'] ?? ''));
    if ($sortCompare !== 0) {
        return $sortCompare;
    }
    return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
});
$fediverseTimelineRootIdentifiers = [];
foreach ($fediverseTimelineEntries as $fediverseRootEntry) {
    $fediverseRootItem = is_array($fediverseRootEntry['item'] ?? null) ? $fediverseRootEntry['item'] : [];
    if (empty($fediverseRootItem)) {
        continue;
    }
    $fediverseRootKind = trim((string) ($fediverseRootEntry['kind'] ?? ''));
    $fediverseRootType = strtolower(trim((string) ($fediverseRootItem['type'] ?? '')));
    $fediverseRootTarget = trim((string) ($fediverseRootItem['target_url'] ?? ''));
    $fediverseRootIsReply = $fediverseRootKind === 'remote'
        && $fediverseRootType !== 'announce'
        && $fediverseRootTarget !== ''
        && trim((string) ($fediverseRootItem['content'] ?? '')) !== ''
        && trim((string) ($fediverseRootItem['object_id'] ?? '')) !== ''
        && $fediverseRootTarget !== trim((string) ($fediverseRootItem['object_id'] ?? ''));
    if ($fediverseRootIsReply) {
        continue;
    }
    foreach (['id', 'object_id', 'url'] as $fediverseRootField) {
        $fediverseRootValue = trim((string) ($fediverseRootItem[$fediverseRootField] ?? ''));
        foreach ($fediverseEquivalentIdentifiers($fediverseRootValue) as $fediverseRootIdentifier) {
            $fediverseTimelineRootIdentifiers[$fediverseRootIdentifier] = true;
        }
    }
}
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
$notificationContext = static function (array $entry) use ($fediverseActorsById, $fediverseConfig, $fediverseLocalLinks, $fediverseActorUrl, $fediverseLocalHandle): array {
    $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
    $actorId = trim((string) ($payload['actor'] ?? ''));
    $actor = $actorId !== '' ? ($fediverseActorsById[$actorId] ?? null) : null;
    $type = strtolower(trim((string) ($payload['type'] ?? '')));
    $object = $payload['object'] ?? null;
    $targetUrl = '';
    $isUndoFollow = $type === 'undo'
        && is_array($object)
        && strtolower(trim((string) ($object['type'] ?? ''))) === 'follow';
    if ($type === 'follow' || $isUndoFollow) {
        $targetUrl = '';
    } elseif (in_array($type, ['like', 'announce'], true) && is_string($object)) {
        $targetUrl = trim($object);
    } elseif ($type === 'create' && is_array($object)) {
        $targetUrl = trim((string) (($object['inReplyTo'] ?? '') ?: ($object['url'] ?? '') ?: ($object['id'] ?? '')));
    } elseif (is_string($object)) {
        $targetUrl = trim($object);
    } elseif (is_array($object)) {
        $targetUrl = trim((string) (($object['url'] ?? '') ?: ($object['id'] ?? '')));
    }
    if ($targetUrl !== '') {
        $targetPublicUrl = function_exists('nammu_fediverse_public_url_for_local_identifier')
            ? trim((string) nammu_fediverse_public_url_for_local_identifier($targetUrl, $fediverseConfig))
            : '';
        if ($targetPublicUrl !== '') {
            $targetUrl = $targetPublicUrl;
        } elseif (isset($fediverseLocalLinks[$targetUrl])) {
            $targetUrl = $fediverseLocalLinks[$targetUrl];
        }
    }
    $actorUsername = trim((string) (($actor['preferredUsername'] ?? '') ?: ''));
    $actorHandle = $actorId;
    if ($actorId !== '' && $actorId === $fediverseActorUrl) {
        $actorHandle = $fediverseLocalHandle;
    } elseif ($actorUsername !== '') {
        $actorHost = parse_url($actorId, PHP_URL_HOST);
        if (is_string($actorHost) && $actorHost !== '') {
            $actorHandle = '@' . $actorUsername . '@' . $actorHost;
        } else {
            $actorHandle = '@' . $actorUsername;
        }
    }
    return [
        'actor_id' => $actorId,
        'actor_name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? '') ?: $actorId)),
        'actor_handle' => $actorHandle,
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
    return (string) ($context['actor_handle'] ?? ($context['actor_id'] ?? ''));
};
