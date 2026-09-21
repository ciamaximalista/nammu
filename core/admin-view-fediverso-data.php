<?php
/**
 * Nammu — panel de administración. Fediverso: datos y cierres compartidos por todas las subpestañas (config del actor,
 * seguidos, seguidores, bloqueados, instantáneas, actores conocidos, mensajes, notificaciones, menciones y ayudantes de formato).
 * Lo incluye core/admin-page-fediverso.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 * Lo que sólo necesita la subpestaña Inicio (timeline, hilos, respuestas, paginación) está en admin-view-fediverso-home-data.php.
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
$fediverseRecipients = ($isFediverseMessagesTab || $isFediverseNetworkTab)
    && $fediverseNeedsLivePanel
    ? (($isFediverseMessagesTab && is_array($fediverseMessagesSnapshot['recipients'] ?? null))
        ? $fediverseMessagesSnapshot['recipients']
        : [])
    : [];
$fediverseNotifications = $isFediverseNotificationsTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseNotificationsSnapshot['notifications'] ?? null) ? $fediverseNotificationsSnapshot['notifications'] : [])
    : [];
$fediverseNotifications = array_values(array_filter($fediverseNotifications, static function ($entry): bool {
    return is_array($entry) && !empty($entry['verified']);
}));
$fediverseWebmentions = ($isFediverseMentionsTab && $fediverseNeedsLivePanel && function_exists('nammu_webmention_list'))
    ? nammu_webmention_list()
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
$fediverseMessageThreads = $isFediverseMessagesTab && $fediverseNeedsLivePanel
    ? (is_array($fediverseMessagesSnapshot['message_threads'] ?? null)
        ? $fediverseMessagesSnapshot['message_threads']
        : [])
    : [];
$fediverseLocalLinks = []; // enlaces al panel de cada publicación local; los rellena admin-view-fediverso-home-data.php.
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
