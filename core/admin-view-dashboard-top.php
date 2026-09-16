<?php
/**
 * Nammu — panel de administración. Datos del Escritorio: Contenidos más vistos: entradas, páginas, páginas de sistema, objetos del Fediverso e itinerarios, por periodo.
 * Lo incluye core/admin-view-dashboard.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
$entryStats = is_array($postsStats) ? $postsStats : [];
foreach ($pagesStats as $slug => $item) {
    if (!is_string($slug)) {
        continue;
    }
    $isPodcastEpisodePage = str_starts_with($slug, 'podcast/');
    if (!$isPodcastEpisodePage) {
        continue;
    }
    if (!isset($entryStats[$slug])) {
        $entryStats[$slug] = $item;
    }
}

$allPosts = [];
foreach ($entryStats as $slug => $item) {
    $daily = $item['daily'] ?? [];
    $total = (int) ($item['total'] ?? 0);
    $totalFromDaily = $sumAllViews(is_array($daily) ? $daily : []);
    if ($totalFromDaily > $total) {
        $total = $totalFromDaily;
    }
    if ($total <= 0) {
        continue;
    }
    $allPosts[] = [
        'slug' => $slug,
        'title' => $item['title'] ?? $slug,
        'count' => $total,
        'unique' => $uniqueAll(is_array($daily) ? $daily : []),
    ];
}
$topPosts = $allPosts;
usort($topPosts, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
$topPosts = array_slice($topPosts, 0, 10);

$templateBySlug = [];
foreach ($postsMetadata as $item) {
    $filename = (string) ($item['filename'] ?? '');
    if ($filename === '' || !str_ends_with($filename, '.md')) {
        continue;
    }
    $slug = basename($filename, '.md');
    $status = strtolower((string) ($item['metadata']['Status'] ?? 'published'));
    if ($status === 'draft') {
        continue;
    }
    $templateBySlug[$slug] = strtolower((string) ($item['metadata']['Template'] ?? 'post'));
}

$image30ViewsPosts = 0;
$image30ViewsNewsletter = 0;
$image30ViewsPodcast = 0;
foreach ($postsStats as $slug => $item) {
    if (!is_array($item)) {
        continue;
    }
    $daily = is_array($item['daily'] ?? null) ? $item['daily'] : [];
    $views30 = $sumRange($daily, $last30Start, $today);
    if ($views30 <= 0) {
        continue;
    }
    $template = $templateBySlug[(string) $slug] ?? 'post';
    if ($template === 'newsletter') {
        $image30ViewsNewsletter += $views30;
    } elseif ($template === 'podcast') {
        $image30ViewsPodcast += $views30;
    } else {
        $image30ViewsPosts += $views30;
    }
}

$image30ViewsPages = 0;
$image30ViewsItineraries = 0;
$image30ViewsFediverseObjects = 0;
$image30FediverseObjectUids = [];
foreach ($pagesStats as $slug => $item) {
    if (!is_array($item)) {
        continue;
    }
    $daily = is_array($item['daily'] ?? null) ? $item['daily'] : [];
    $views30 = $sumRange($daily, $last30Start, $today);
    if ($views30 <= 0) {
        continue;
    }
    if (is_string($slug) && preg_match('#^fediverso/[a-f0-9]{24}$#i', $slug) === 1) {
        $image30ViewsFediverseObjects += $views30;
        foreach ($daily as $day => $payload) {
            if (!is_string($day) || $day < $startKey || $day > $endKey || !is_array($payload)) {
                continue;
            }
            $uids = is_array($payload['uids'] ?? null) ? $payload['uids'] : [];
            foreach ($uids as $uid => $flag) {
                if ($uid !== '') {
                    $image30FediverseObjectUids[$uid] = true;
                }
            }
        }
    } elseif (is_string($slug) && str_starts_with($slug, 'itinerarios/')) {
        $image30ViewsItineraries += $views30;
    } else {
        $image30ViewsPages += $views30;
    }
}
$image30FediverseObjectUnique = count($image30FediverseObjectUids);

$image30TotalViews = $image30ViewsPosts + $image30ViewsPages + $image30ViewsItineraries + $image30ViewsNewsletter + $image30ViewsPodcast + $image30ViewsFediverseObjects;
$image30PagesPerUser = $unique30Count > 0 ? ($image30TotalViews / $unique30Count) : 0.0;

$image30UserDays = [];
foreach ($last30Daily as $dayKey => $_count) {
    $uids = is_array($combinedDailyUids[$dayKey] ?? null) ? $combinedDailyUids[$dayKey] : [];
    if (!is_array($uids)) {
        continue;
    }
    foreach ($uids as $uid => $flag) {
        $image30UserDays[$uid] = (int) ($image30UserDays[$uid] ?? 0) + 1;
    }
}
$image30RecurringUsers = 0;
foreach ($image30UserDays as $daysSeen) {
    if ((int) $daysSeen >= 2) {
        $image30RecurringUsers++;
    }
}
$image30RecurringRate = $unique30Count > 0 ? (($image30RecurringUsers / $unique30Count) * 100) : 0.0;

$image30DailyValues = array_values($last30Daily);
$image30DailyAverage = !empty($image30DailyValues) ? (array_sum($image30DailyValues) / count($image30DailyValues)) : 0.0;
if (!empty($image30DailyValues)) {
    sort($image30DailyValues);
    $mid = (int) floor(count($image30DailyValues) / 2);
    if (count($image30DailyValues) % 2 === 0) {
        $image30DailyMedian = ($image30DailyValues[$mid - 1] + $image30DailyValues[$mid]) / 2;
    } else {
        $image30DailyMedian = $image30DailyValues[$mid];
    }
    $image30DailyPeak = max($image30DailyValues);
} else {
    $image30DailyMedian = 0;
    $image30DailyPeak = 0;
}
$topPostsByUnique = array_values(array_filter($allPosts, static function (array $item): bool {
    return (int) ($item['unique'] ?? 0) > 0;
}));
usort($topPostsByUnique, static function (array $a, array $b): int {
    return $b['unique'] <=> $a['unique'];
});
$topPostsByUnique = array_slice($topPostsByUnique, 0, 10);

$topPostsWeek = [];
$topPostsMonth = [];
foreach ($entryStats as $slug => $item) {
    $daily = $item['daily'] ?? [];
    $countWeek = $sumRange($daily, $last7Start, $today);
    $countMonth = $sumRange($daily, $last30Start, $today);
    if ($countWeek > 0) {
        $topPostsWeek[] = [
            'slug' => $slug,
            'title' => $item['title'] ?? $slug,
            'count' => $countWeek,
            'unique' => $uniqueRange($daily, $last7Start, $today),
        ];
    }
    if ($countMonth > 0) {
        $topPostsMonth[] = [
            'slug' => $slug,
            'title' => $item['title'] ?? $slug,
            'count' => $countMonth,
            'unique' => $uniqueRange($daily, $last30Start, $today),
        ];
    }
}
$topPostsWeekByUnique = $topPostsWeek;
usort($topPostsWeekByUnique, static function (array $a, array $b): int {
    return $b['unique'] <=> $a['unique'];
});
$topPostsWeekByUnique = array_values(array_filter($topPostsWeekByUnique, static function (array $item): bool {
    return (int) ($item['unique'] ?? 0) > 0;
}));
$topPostsWeekByUnique = array_slice($topPostsWeekByUnique, 0, 10);
$topPostsMonthByUnique = $topPostsMonth;
usort($topPostsMonthByUnique, static function (array $a, array $b): int {
    return $b['unique'] <=> $a['unique'];
});
$topPostsMonthByUnique = array_values(array_filter($topPostsMonthByUnique, static function (array $item): bool {
    return (int) ($item['unique'] ?? 0) > 0;
}));
$topPostsMonthByUnique = array_slice($topPostsMonthByUnique, 0, 10);
usort($topPostsWeek, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
usort($topPostsMonth, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
$topPostsWeek = array_slice($topPostsWeek, 0, 10);
$topPostsMonth = array_slice($topPostsMonth, 0, 10);

$isSystemPageSlug = static function (string $slug): bool {
    if ($slug === 'index' || $slug === 'podcast' || $slug === 'categorias' || $slug === 'letras' || $slug === 'itinerarios' || $slug === 'buscar' || $slug === 'avisos' || $slug === 'correos' || $slug === 'actualidad' || $slug === 'newsletters') {
        return true;
    }
    if (preg_match('#^pagina/([1-9][0-9]*)$#', $slug)) {
        return true;
    }
    return str_starts_with($slug, 'categoria/')
        || str_starts_with($slug, 'letra/')
        || str_starts_with($slug, 'newsletters/');
};
$isFediverseObjectPageSlug = static function (string $slug): bool {
    return preg_match('#^fediverso/[a-f0-9]{24}$#i', $slug) === 1;
};
$allPages = [];
$allSystemPages = [];
$allFediverseObjectPages = [];
foreach ($pagesStats as $slug => $item) {
    if (is_string($slug) && str_starts_with($slug, 'podcast/')) {
        continue;
    }
    $daily = $item['daily'] ?? [];
    $total = (int) ($item['total'] ?? 0);
    $totalFromDaily = $sumAllViews(is_array($daily) ? $daily : []);
    if ($totalFromDaily > $total) {
        $total = $totalFromDaily;
    }
    if ($total <= 0) {
        continue;
    }
    $title = $item['title'] ?? $slug;
    if ($slug === 'index') {
        $title = 'Portada';
    } elseif (preg_match('#^pagina/([1-9][0-9]*)$#', $slug, $pageMatch)) {
        $title = 'Página ' . $pageMatch[1];
    }
    $entry = [
        'slug' => $slug,
        'title' => $title,
        'count' => $total,
        'unique' => $uniqueAll(is_array($daily) ? $daily : []),
        'daily' => is_array($daily) ? $daily : [],
    ];
    if ($isFediverseObjectPageSlug($slug)) {
        $allFediverseObjectPages[] = $entry;
    } elseif ($isSystemPageSlug($slug)) {
        $allSystemPages[] = $entry;
    } else {
        $allPages[] = $entry;
    }
}
$topPages = $allPages;
usort($topPages, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
$topPages = array_slice($topPages, 0, 10);
$topPagesByUnique = array_values(array_filter($allPages, static function (array $item): bool {
    return (int) ($item['unique'] ?? 0) > 0;
}));
usort($topPagesByUnique, static function (array $a, array $b): int {
    return $b['unique'] <=> $a['unique'];
});
$topPagesByUnique = array_slice($topPagesByUnique, 0, 10);
$topPagesWeek = [];
$topPagesMonth = [];
foreach ($allPages as $item) {
    $daily = $item['daily'] ?? [];
    $countWeek = $sumRange($daily, $last7Start, $today);
    $countMonth = $sumRange($daily, $last30Start, $today);
    if ($countWeek > 0) {
        $topPagesWeek[] = [
            'slug' => $item['slug'],
            'title' => $item['title'],
            'count' => $countWeek,
            'unique' => $uniqueRange($daily, $last7Start, $today),
        ];
    }
    if ($countMonth > 0) {
        $topPagesMonth[] = [
            'slug' => $item['slug'],
            'title' => $item['title'],
            'count' => $countMonth,
            'unique' => $uniqueRange($daily, $last30Start, $today),
        ];
    }
}
$topPagesWeekByUnique = $topPagesWeek;
usort($topPagesWeekByUnique, static function (array $a, array $b): int {
    return $b['unique'] <=> $a['unique'];
});
$topPagesWeekByUnique = array_values(array_filter($topPagesWeekByUnique, static function (array $item): bool {
    return (int) ($item['unique'] ?? 0) > 0;
}));
$topPagesWeekByUnique = array_slice($topPagesWeekByUnique, 0, 10);
$topPagesMonthByUnique = $topPagesMonth;
usort($topPagesMonthByUnique, static function (array $a, array $b): int {
    return $b['unique'] <=> $a['unique'];
});
$topPagesMonthByUnique = array_values(array_filter($topPagesMonthByUnique, static function (array $item): bool {
    return (int) ($item['unique'] ?? 0) > 0;
}));
$topPagesMonthByUnique = array_slice($topPagesMonthByUnique, 0, 10);
usort($topPagesWeek, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
usort($topPagesMonth, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
$topPagesWeek = array_slice($topPagesWeek, 0, 10);
$topPagesMonth = array_slice($topPagesMonth, 0, 10);

$topSystemPages = $allSystemPages;
usort($topSystemPages, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
$topSystemPages = array_slice($topSystemPages, 0, 10);
$topSystemPagesByUnique = array_values(array_filter($allSystemPages, static function (array $item): bool {
    return (int) ($item['unique'] ?? 0) > 0;
}));
usort($topSystemPagesByUnique, static function (array $a, array $b): int {
    return $b['unique'] <=> $a['unique'];
});
$topSystemPagesByUnique = array_slice($topSystemPagesByUnique, 0, 10);
$topSystemPagesWeek = [];
$topSystemPagesMonth = [];
foreach ($allSystemPages as $item) {
    $daily = $item['daily'] ?? [];
    $countWeek = $sumRange($daily, $last7Start, $today);
    $countMonth = $sumRange($daily, $last30Start, $today);
    if ($countWeek > 0) {
        $topSystemPagesWeek[] = [
            'slug' => $item['slug'],
            'title' => $item['title'],
            'count' => $countWeek,
            'unique' => $uniqueRange($daily, $last7Start, $today),
        ];
    }
    if ($countMonth > 0) {
        $topSystemPagesMonth[] = [
            'slug' => $item['slug'],
            'title' => $item['title'],
            'count' => $countMonth,
            'unique' => $uniqueRange($daily, $last30Start, $today),
        ];
    }
}
$topSystemPagesWeekByUnique = $topSystemPagesWeek;
usort($topSystemPagesWeekByUnique, static function (array $a, array $b): int {
    return $b['unique'] <=> $a['unique'];
});
$topSystemPagesWeekByUnique = array_values(array_filter($topSystemPagesWeekByUnique, static function (array $item): bool {
    return (int) ($item['unique'] ?? 0) > 0;
}));
$topSystemPagesWeekByUnique = array_slice($topSystemPagesWeekByUnique, 0, 10);
$topSystemPagesMonthByUnique = $topSystemPagesMonth;
usort($topSystemPagesMonthByUnique, static function (array $a, array $b): int {
    return $b['unique'] <=> $a['unique'];
});
$topSystemPagesMonthByUnique = array_values(array_filter($topSystemPagesMonthByUnique, static function (array $item): bool {
    return (int) ($item['unique'] ?? 0) > 0;
}));
$topSystemPagesMonthByUnique = array_slice($topSystemPagesMonthByUnique, 0, 10);
usort($topSystemPagesWeek, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
usort($topSystemPagesMonth, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
$topSystemPagesWeek = array_slice($topSystemPagesWeek, 0, 10);
$topSystemPagesMonth = array_slice($topSystemPagesMonth, 0, 10);

$topFediverseObjectPagesToday = [];
$topFediverseObjectPagesWeek = [];
$topFediverseObjectPagesMonth = [];
foreach ($allFediverseObjectPages as $item) {
    $daily = $item['daily'] ?? [];
    $countToday = $sumRange($daily, $today, $today);
    $countWeek = $sumRange($daily, $last7Start, $today);
    $countMonth = $sumRange($daily, $last30Start, $today);
    if ($countToday > 0) {
        $topFediverseObjectPagesToday[] = [
            'slug' => $item['slug'],
            'title' => $item['title'],
            'count' => $countToday,
        ];
    }
    if ($countWeek > 0) {
        $topFediverseObjectPagesWeek[] = [
            'slug' => $item['slug'],
            'title' => $item['title'],
            'count' => $countWeek,
        ];
    }
    if ($countMonth > 0) {
        $topFediverseObjectPagesMonth[] = [
            'slug' => $item['slug'],
            'title' => $item['title'],
            'count' => $countMonth,
        ];
    }
}
usort($topFediverseObjectPagesToday, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
usort($topFediverseObjectPagesWeek, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
usort($topFediverseObjectPagesMonth, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
$topFediverseObjectPagesToday = array_slice($topFediverseObjectPagesToday, 0, 10);
$topFediverseObjectPagesWeek = array_slice($topFediverseObjectPagesWeek, 0, 10);
$topFediverseObjectPagesMonth = array_slice($topFediverseObjectPagesMonth, 0, 10);

$buildSystemPageUrl = static function (string $slug): string {
    if ($slug === 'index') {
        return '/';
    }
    if ($slug === 'buscar') {
        return '/buscar.php';
    }
    if ($slug === 'avisos') {
        return '/avisos';
    }
    if ($slug === 'correos') {
        return '/correos';
    }
    if ($slug === 'podcast') {
        return '/podcast';
    }
    if ($slug === 'categorias') {
        return '/categorias';
    }
    if ($slug === 'letras') {
        return '/letras';
    }
    if ($slug === 'itinerarios') {
        return '/itinerarios';
    }
    if ($slug === 'newsletters') {
        return '/newsletters';
    }
    if (preg_match('#^pagina/([1-9][0-9]*)$#', $slug, $pageMatch)) {
        return '/pagina/' . $pageMatch[1];
    }
    if (str_starts_with($slug, 'categoria/')) {
        return '/' . $slug;
    }
    if (str_starts_with($slug, 'letra/')) {
        return '/' . $slug;
    }
    if (str_starts_with($slug, 'newsletters/')) {
        return '/' . $slug;
    }
    return '/' . $slug;
};
$buildFediverseObjectPageUrl = static function (string $slug): string {
    return '/' . ltrim($slug, '/');
};

$itineraryPageUniques = [];
foreach ($pagesStats as $slug => $item) {
    if (!str_starts_with($slug, 'itinerarios/')) {
        continue;
    }
    $parts = explode('/', $slug);
    if (count($parts) !== 2 || $parts[1] === '') {
        continue;
    }
    $daily = is_array($item['daily'] ?? null) ? $item['daily'] : [];
    $unique = $uniqueAll($daily);
    if ($unique > 0) {
        $itineraryPageUniques[$parts[1]] = max($itineraryPageUniques[$parts[1]] ?? 0, $unique);
    }
}

$topItineraryViews = [];
$topItineraryStarts = [];
$topItineraryCompletes = [];
foreach ($itineraries ?? [] as $itineraryItem) {
    $slug = $itineraryItem->getSlug();
    $viewCount = $itineraryPageUniques[$slug] ?? 0;
    $topItineraryViews[] = [
        'slug' => $slug,
        'title' => $itineraryTitleMap[$slug] ?? $slug,
        'count' => $viewCount,
    ];
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
    $lastTopicCount = 0;
    if (!empty($topicStatsList)) {
        $lastTopic = $topicStatsList[count($topicStatsList) - 1];
        $lastTopicCount = (int) ($lastTopic['count'] ?? 0);
    }
    $topItineraryStarts[] = [
        'slug' => $slug,
        'title' => $itineraryTitleMap[$slug] ?? $slug,
        'count' => $topicOneStarters > 0 ? $topicOneStarters : $presentationReaders,
    ];
    $topItineraryCompletes[] = [
        'slug' => $slug,
        'title' => $itineraryTitleMap[$slug] ?? $slug,
        'count' => $lastTopicCount,
    ];
}
usort($topItineraryStarts, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
usort($topItineraryCompletes, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
usort($topItineraryViews, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
