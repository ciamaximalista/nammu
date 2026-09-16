<?php
/**
 * Nammu — panel de administración. Datos del Escritorio: Recuento de contenidos (entradas, páginas, itinerarios) y estado de las colas de envío: IndexNow, correo, RRSS y
 * Fediverso. Carga también las estadísticas en bruto que usan las demás piezas.
 * Lo incluye core/admin-view-dashboard.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
if (!function_exists('admin_social_rss_feed_urls_from_settings') && is_file(__DIR__ . '/admin-redes.php')) {
    require_once __DIR__ . '/admin-redes.php';
}
if (!function_exists('nammu_actuality_feed_urls') && is_file(__DIR__ . '/actualidad.php')) {
    require_once __DIR__ . '/actualidad.php';
}
$postsMetadata = get_all_posts_metadata();
$postCount = 0;
$pageCount = 0;
$postCountsByYear = [];
$newsletterCount = 0;
$podcastCount = 0;
$parsePublishedDate = static function (array $metadata): ?DateTimeImmutable {
    $dateValue = trim((string) ($metadata['Date'] ?? ''));
    if ($dateValue === '') {
        return null;
    }
    foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $dateValue);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }
    }
    $timestamp = strtotime($dateValue);
    if ($timestamp === false) {
        return null;
    }
    return (new DateTimeImmutable())->setTimestamp($timestamp);
};
foreach ($postsMetadata as $item) {
    $status = strtolower((string) ($item['metadata']['Status'] ?? 'published'));
    if ($status === 'draft') {
        continue;
    }
    $template = strtolower($item['metadata']['Template'] ?? 'post');
    if ($template === 'page') {
        $pageCount++;
    } elseif ($template === 'newsletter') {
        $newsletterCount++;
    } elseif ($template === 'podcast') {
        $podcastCount++;
    } elseif (in_array($template, ['post', 'single'], true)) {
        $postCount++;
        $publishedDate = $parsePublishedDate(is_array($item['metadata'] ?? null) ? $item['metadata'] : []);
        if ($publishedDate instanceof DateTimeImmutable) {
            $year = (int) $publishedDate->format('Y');
            if ($year > 0) {
                $postCountsByYear[$year] = ($postCountsByYear[$year] ?? 0) + 1;
            }
        }
    }
}
if (!empty($postCountsByYear)) {
    krsort($postCountsByYear);
}
$itineraryCount = 0;
$itineraryTitleMap = [];
try {
    $itineraries = admin_itinerary_repository()->all();
    $itineraryCount = count($itineraries);
    foreach ($itineraries as $itinerary) {
        if (method_exists($itinerary, 'getSlug') && method_exists($itinerary, 'getTitle')) {
            $itineraryTitleMap[$itinerary->getSlug()] = $itinerary->getTitle();
        }
    }
} catch (Throwable $e) {
    $itineraryCount = 0;
    $itineraries = [];
}

$analytics = function_exists('nammu_load_analytics') ? nammu_load_analytics() : [];
$visitorsDaily = $analytics['visitors']['daily'] ?? [];
$postsStats = $analytics['content']['posts'] ?? [];
$pagesStats = $analytics['content']['pages'] ?? [];
$platformDaily = $analytics['platform']['daily'] ?? [];
$sourcesDaily = $analytics['sources']['daily'] ?? [];
$searchesDaily = $analytics['searches']['daily'] ?? [];
$botsDaily = $analytics['bots']['daily'] ?? [];
$indexnowLog = function_exists('admin_indexnow_load_log') ? admin_indexnow_load_log() : [];
$indexnowErrors = [];
if (is_array($indexnowLog['errors'] ?? null)) {
    $indexnowErrors = array_values(array_filter($indexnowLog['errors'], static function ($item) {
        return is_array($item) && !empty($item['endpoint']);
    }));
}
$indexnowUrls = is_array($indexnowLog['urls'] ?? null) ? $indexnowLog['urls'] : [];
$indexnowResponses = is_array($indexnowLog['responses'] ?? null) ? $indexnowLog['responses'] : [];
$indexnowTimestamp = isset($indexnowLog['timestamp']) ? (int) $indexnowLog['timestamp'] : 0;
$indexnowHasErrors = !empty($indexnowErrors);
$indexnowHasLog = $indexnowTimestamp > 0;
$mailingDashboardStatus = function_exists('admin_mailing_dashboard_status') ? admin_mailing_dashboard_status() : ['alerts' => null, 'newsletter' => null];
$mailingDashboardItems = array_values(array_filter([
    is_array($mailingDashboardStatus['alerts'] ?? null) ? $mailingDashboardStatus['alerts'] : null,
    is_array($mailingDashboardStatus['newsletter'] ?? null) ? $mailingDashboardStatus['newsletter'] : null,
]));
$mailingStatusLabel = static function (array $row): string {
    $status = (string) ($row['status'] ?? '');
    return match ($status) {
        'pending' => 'En curso',
        'error' => 'Con errores',
        'partial' => 'Parcial',
        'sent' => 'Completado',
        default => 'Sin datos',
    };
};
$mailingStatusColor = static function (array $row): string {
    $status = (string) ($row['status'] ?? '');
    return match ($status) {
        'error' => '#ea2f28',
        'partial', 'pending' => '#b36b00',
        'sent' => '#167a3a',
        default => '#6c757d',
    };
};
$mailingDateLabel = static function (int $timestamp): string {
    return $timestamp > 0 ? date('d/m/y H:i', $timestamp) : 'Sin intentos registrados';
};
$dashboardReadJsonFile = static function (string $path): array {
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
};
$dashboardConfigDir = dirname(__DIR__) . '/config';
$socialQueuePath = $dashboardConfigDir . '/social-broadcast-queue.json';
$socialQueue = $dashboardReadJsonFile($socialQueuePath);
$socialQueueItems = is_array($socialQueue['items'] ?? null) ? array_values(array_filter($socialQueue['items'], 'is_array')) : [];
$socialPendingNetworks = 0;
$socialFailedItems = 0;
$socialLastTimestamp = is_file($socialQueuePath) ? (int) @filemtime($socialQueuePath) : 0;
foreach ($socialQueueItems as $socialItem) {
    $socialPendingNetworks += count(array_values(array_filter(array_map('strval', $socialItem['networks'] ?? []))));
    if ((int) ($socialItem['attempts'] ?? 0) > 0 || trim((string) ($socialItem['last_error'] ?? '')) !== '') {
        $socialFailedItems++;
    }
    $createdAt = strtotime((string) ($socialItem['created_at'] ?? '')) ?: 0;
    $attemptAt = strtotime((string) ($socialItem['last_attempt_at'] ?? '')) ?: 0;
    $socialLastTimestamp = max($socialLastTimestamp, $createdAt, $attemptAt);
}
$fediverseQueueFiles = [
    'announce' => $dashboardConfigDir . '/fediverso-announce-queue.json',
    'delete' => $dashboardConfigDir . '/fediverso-delete-queue.json',
    'undo' => $dashboardConfigDir . '/fediverso-undo-announce-queue.json',
];
$fediversePending = 0;
$fediverseLastTimestamp = 0;
foreach ($fediverseQueueFiles as $fediverseQueueFile) {
    $queueStore = $dashboardReadJsonFile($fediverseQueueFile);
    $fediversePending += count(is_array($queueStore['items'] ?? null) ? $queueStore['items'] : []);
    if (is_file($fediverseQueueFile)) {
        $fediverseLastTimestamp = max($fediverseLastTimestamp, (int) @filemtime($fediverseQueueFile));
    }
}
$fediverseDeliveriesPath = $dashboardConfigDir . '/fediverso-deliveries.json';
if (is_file($fediverseDeliveriesPath)) {
    $fediverseLastTimestamp = max($fediverseLastTimestamp, (int) @filemtime($fediverseDeliveriesPath));
}
$socialFediverseStatus = [
    'social_pending_items' => count($socialQueueItems),
    'social_pending_networks' => $socialPendingNetworks,
    'social_failed_items' => $socialFailedItems,
    'social_last_at' => $socialLastTimestamp,
    'fediverse_pending' => $fediversePending,
    'fediverse_last_at' => $fediverseLastTimestamp,
    'rss_unprocessed' => 0,
    'rss_unprocessed_items' => [],
    'rss_fetch_failed' => 0,
    'rss_fetch_failed_items' => [],
];
if (
    function_exists('admin_social_rss_feed_urls_from_settings')
    && function_exists('admin_fetch_social_rss_items')
    && function_exists('admin_load_social_rss_state')
) {
    $rssFeeds = admin_social_rss_feed_urls_from_settings($settings);
    if (function_exists('nammu_actuality_feed_urls')) {
        $rssFeeds = array_merge($rssFeeds, nammu_actuality_feed_urls($settings));
    }
    $rssFeeds = array_values(array_unique(array_filter(array_map(static function ($url): string {
        return trim((string) $url);
    }, $rssFeeds), static function (string $url): bool {
        return $url !== '' && preg_match('#^https?://#i', $url);
    })));
    $rssState = admin_load_social_rss_state();
    $rssStateFeeds = is_array($rssState['feeds'] ?? null) ? $rssState['feeds'] : [];
    $newsStore = function_exists('nammu_actuality_load_news_store') ? nammu_actuality_load_news_store() : ['items' => []];
    $storedByFeedKey = [];
    foreach ((array) ($newsStore['items'] ?? []) as $storedNewsItem) {
        if (!is_array($storedNewsItem)) {
            continue;
        }
        $storedKey = trim((string) ($storedNewsItem['feed_item_key'] ?? ''));
        if ($storedKey !== '') {
            $storedByFeedKey[$storedKey] = $storedNewsItem;
        }
    }
    $rssUnprocessedItems = [];
    $rssFetchFailedItems = [];
    $recentRssCutoff = time() - (72 * 3600);
    foreach ($rssFeeds as $rssFeedUrl) {
        $rssFeedUrl = trim((string) $rssFeedUrl);
        if ($rssFeedUrl === '') {
            continue;
        }
        $rssFeedKey = sha1($rssFeedUrl);
        $rssFetchError = null;
        $rssItems = admin_fetch_social_rss_items($rssFeedUrl, $rssFetchError);
        if (is_string($rssFetchError) && trim($rssFetchError) !== '') {
            $rssFetchFailedItems[] = [
                'feed' => $rssFeedUrl,
                'error' => trim($rssFetchError),
            ];
            continue;
        }
        foreach ($rssItems as $rssItem) {
            if (!is_array($rssItem)) {
                continue;
            }
            $rssItemKey = trim((string) ($rssItem['key'] ?? ''));
            if ($rssItemKey === '') {
                continue;
            }
            $rssTimestamp = (int) ($rssItem['timestamp'] ?? 0);
            if ($rssTimestamp > 0 && $rssTimestamp < $recentRssCutoff) {
                continue;
            }
            $storedNewsItem = is_array($storedByFeedKey[$rssItemKey] ?? null) ? $storedByFeedKey[$rssItemKey] : null;
            $rssPendingReason = '';
            if ($storedNewsItem === null) {
                $rssPendingReason = 'sin almacenar';
            } else {
                $broadcastEnqueuedAt = (int) ($storedNewsItem['social_broadcast_enqueued_at'] ?? 0);
                $linkCardStage = trim((string) ($storedNewsItem['link_card_stage'] ?? ''));
                $shouldBroadcast = !empty($storedNewsItem['social_broadcast_pending'])
                    || ($broadcastEnqueuedAt <= 0 && ($linkCardStage === '' || $linkCardStage === 'discovered'));
                if ($shouldBroadcast) {
                    $rssPendingReason = 'pendiente de RRSS';
                }
            }
            if ($rssPendingReason === '') {
                continue;
            }
            $rssUnprocessedItems[] = [
                'feed' => $rssFeedUrl,
                'title' => trim((string) ($rssItem['title'] ?? '')),
                'timestamp' => $rssTimestamp,
                'reason' => $rssPendingReason,
            ];
        }
    }
    usort($rssUnprocessedItems, static function (array $a, array $b): int {
        return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
    });
    $socialFediverseStatus['rss_unprocessed'] = count($rssUnprocessedItems);
    $socialFediverseStatus['rss_unprocessed_items'] = array_slice($rssUnprocessedItems, 0, 5);
    $socialFediverseStatus['rss_fetch_failed'] = count($rssFetchFailedItems);
    $socialFediverseStatus['rss_fetch_failed_items'] = array_slice($rssFetchFailedItems, 0, 5);
}
$socialFediverseHasErrors = $socialFailedItems > 0 || (int) ($socialFediverseStatus['rss_fetch_failed'] ?? 0) > 0;
$socialFediverseHasPending = count($socialQueueItems) > 0 || $fediversePending > 0 || (int) ($socialFediverseStatus['rss_unprocessed'] ?? 0) > 0;
$socialFediverseColor = $socialFediverseHasErrors ? '#ea2f28' : ($socialFediverseHasPending ? '#b36b00' : '#1b8eed');
$socialFediverseLabel = $socialFediverseHasErrors ? 'Con errores' : ($socialFediverseHasPending ? 'En curso' : 'Sin pendientes');
