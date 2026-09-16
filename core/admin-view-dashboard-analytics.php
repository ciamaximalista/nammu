<?php
/**
 * Nammu — panel de administración. Datos del Escritorio: Analítica de visitas: rangos, visitantes únicos, plataformas, fuentes, búsquedas, series mensuales y anuales y los
 * puntos de las gráficas.
 * Lo incluye core/admin-view-dashboard.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
$sumRange = static function (array $daily, DateTimeImmutable $start, DateTimeImmutable $end): int {
    $total = 0;
    $startKey = $start->format('Y-m-d');
    $endKey = $end->format('Y-m-d');
    foreach ($daily as $day => $count) {
        if (!is_string($day) || $day < $startKey || $day > $endKey) {
            continue;
        }
        if (is_array($count)) {
            $total += (int) ($count['views'] ?? 0);
        } else {
            $total += (int) $count;
        }
    }
    return $total;
};
$sumAllViews = static function (array $daily): int {
    $total = 0;
    foreach ($daily as $count) {
        if (is_array($count)) {
            $total += (int) ($count['views'] ?? 0);
        } else {
            $total += (int) $count;
        }
    }
    return $total;
};

$uniqueRange = static function (array $daily, DateTimeImmutable $start, DateTimeImmutable $end): int {
    $uids = [];
    $startKey = $start->format('Y-m-d');
    $endKey = $end->format('Y-m-d');
    foreach ($daily as $day => $payload) {
        if (!is_string($day) || $day < $startKey || $day > $endKey) {
            continue;
        }
        if (!is_array($payload)) {
            continue;
        }
        $dayUids = $payload['uids'] ?? [];
        if (!is_array($dayUids)) {
            continue;
        }
        foreach ($dayUids as $uid => $flag) {
            $uids[$uid] = true;
        }
    }
    return count($uids);
};

$uniqueAll = static function (array $daily): int {
    $uids = [];
    foreach ($daily as $payload) {
        if (!is_array($payload)) {
            continue;
        }
        $dayUids = $payload['uids'] ?? [];
        if (!is_array($dayUids)) {
            continue;
        }
        foreach ($dayUids as $uid => $flag) {
            $uids[$uid] = true;
        }
    }
    return count($uids);
};

$collectDailyUidsFromStats = static function (array $stats): array {
    $result = [];
    foreach ($stats as $item) {
        if (!is_array($item)) {
            continue;
        }
        $daily = is_array($item['daily'] ?? null) ? $item['daily'] : [];
        foreach ($daily as $day => $payload) {
            if (!is_string($day) || !is_array($payload)) {
                continue;
            }
            $uids = is_array($payload['uids'] ?? null) ? $payload['uids'] : [];
            if (!isset($result[$day])) {
                $result[$day] = [];
            }
            foreach ($uids as $uid => $flag) {
                if ($uid === '') {
                    continue;
                }
                $result[$day][$uid] = true;
            }
        }
    }
    return $result;
};
$combinedDailyUids = [];
foreach ($visitorsDaily as $day => $payload) {
    if (!is_string($day) || !is_array($payload)) {
        continue;
    }
    $uids = is_array($payload['uids'] ?? null) ? $payload['uids'] : [];
    if (!isset($combinedDailyUids[$day])) {
        $combinedDailyUids[$day] = [];
    }
    foreach ($uids as $uid => $flag) {
        if ($uid === '') {
            continue;
        }
        $combinedDailyUids[$day][$uid] = true;
    }
}
foreach ($collectDailyUidsFromStats($pagesStats) as $day => $uids) {
    if (!isset($combinedDailyUids[$day])) {
        $combinedDailyUids[$day] = [];
    }
    foreach ($uids as $uid => $flag) {
        $combinedDailyUids[$day][$uid] = true;
    }
}
foreach ($collectDailyUidsFromStats($postsStats) as $day => $uids) {
    if (!isset($combinedDailyUids[$day])) {
        $combinedDailyUids[$day] = [];
    }
    foreach ($uids as $uid => $flag) {
        $combinedDailyUids[$day][$uid] = true;
    }
}

$dailyUniqueUidSeries = static function (array $dailyUids, DateTimeImmutable $start, DateTimeImmutable $end): array {
    $series = [];
    $cursor = $start;
    while ($cursor <= $end) {
        $dayKey = $cursor->format('Y-m-d');
        $series[$dayKey] = is_array($dailyUids[$dayKey] ?? null) ? $dailyUids[$dayKey] : [];
        $cursor = $cursor->modify('+1 day');
    }
    return $series;
};

$last30UidSeries = $dailyUniqueUidSeries($combinedDailyUids, $last30Start, $today);
$unique30 = [];
$startKey = $last30Start->format('Y-m-d');
$endKey = $today->format('Y-m-d');
foreach ($last30UidSeries as $uids) {
    foreach ($uids as $uid => $flag) {
        if ($uid !== '') {
            $unique30[$uid] = true;
        }
    }
}
$unique30Count = count($unique30);

$collectPlatformUids = static function (string $category) use ($platformDaily, $startKey): array {
    $result = [];
    foreach ($platformDaily as $day => $payload) {
        if (!is_string($day) || $day < $startKey) {
            continue;
        }
        $bucket = is_array($payload) ? ($payload[$category] ?? []) : [];
        foreach ($bucket as $label => $data) {
            $uids = is_array($data) ? ($data['uids'] ?? []) : [];
            foreach ($uids as $uid => $flag) {
                $result[$label][$uid] = true;
            }
        }
    }
    return $result;
};

$platformDevices = $collectPlatformUids('device');
$platformBrowsers = $collectPlatformUids('browser');
$platformSystems = $collectPlatformUids('os');
$platformLanguages = $collectPlatformUids('language');

$emailDetailLabels = ['Suscriptores', 'Newsletter', 'Reenvios', 'Lista de correo'];
$emailBreakdownLabels = ['Suscriptores', 'Reenvios', 'Lista de correo'];
$collectSourceUids = static function (string $category) use ($sourcesDaily, $startKey, $emailDetailLabels): array {
    $result = [];
    foreach ($sourcesDaily as $day => $payload) {
        if (!is_string($day) || $day < $startKey) {
            continue;
        }
        $bucket = is_array($payload) ? ($payload[$category] ?? []) : [];
        if (!is_array($bucket)) {
            continue;
        }
        $details = $bucket['detail'] ?? [];
        if (is_array($details)) {
            foreach ($details as $label => $detailPayload) {
                if ($category === 'other' && in_array((string) $label, $emailDetailLabels, true)) {
                    continue;
                }
                $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
                foreach ($detailUids as $uid => $flag) {
                    $result[$label][$uid] = true;
                }
            }
        }
    }
    return $result;
};

$ensureDeviceBuckets = static function (array $map, array $days): array {
    if (empty($days)) {
        return $map;
    }
    foreach (['desktop', 'mobile', 'tablet'] as $label) {
        if (!isset($map[$label])) {
            $map[$label] = [];
        }
    }
    return $map;
};
$platformDevices = $ensureDeviceBuckets($platformDevices, $platformDaily);

if (!isset($platformBrowsers['otros'])) {
    $platformBrowsers['otros'] = [];
}
if (!isset($platformLanguages['otros'])) {
    $platformLanguages['otros'] = [];
}
$desktopUids = $platformDevices['desktop'] ?? [];
$desktopCount = count($desktopUids);

$botCounts = [];
$botTotal = 0;
foreach ($botsDaily as $day => $payload) {
    if (!is_string($day) || $day < $startKey) {
        continue;
    }
    if (!is_array($payload)) {
        continue;
    }
    foreach ($payload as $botLabel => $botData) {
        $count = is_array($botData) ? (int) ($botData['count'] ?? 0) : (int) $botData;
        if ($count <= 0) {
            continue;
        }
        $botCounts[$botLabel] = ($botCounts[$botLabel] ?? 0) + $count;
        $botTotal += $count;
    }
}
if (!empty($botCounts)) {
    arsort($botCounts);
}

$buildPercentTable = static function (array $map, array $labelMap): array {
    $counts = [];
    foreach ($map as $label => $uids) {
        $count = is_array($uids) ? count($uids) : 0;
        if ($count > 0) {
            $counts[$label] = $count;
        }
    }
    $total = array_sum($counts);
    if ($total <= 0) {
        return [];
    }
    $items = [];
    foreach ($counts as $label => $count) {
        $raw = ($count / $total) * 100;
        $percent = (int) round($raw);
        if ($percent === 0 && $count > 0) {
            $percent = 1;
        }
        $items[] = [
            'label' => $label,
            'count' => $count,
            'percent' => $percent,
            'remainder' => $raw - floor($raw),
        ];
    }
    $sum = array_sum(array_column($items, 'percent'));
    $diff = 100 - $sum;
    if ($diff !== 0) {
        usort($items, static function (array $a, array $b): int {
            return $b['remainder'] <=> $a['remainder'];
        });
        if ($diff > 0) {
            $i = 0;
            while ($diff > 0) {
                $items[$i % count($items)]['percent']++;
                $diff--;
                $i++;
            }
        } else {
            $diff = abs($diff);
            $i = 0;
            while ($diff > 0) {
                $index = $i % count($items);
                if ($items[$index]['percent'] > 1) {
                    $items[$index]['percent']--;
                    $diff--;
                }
                $i++;
            }
        }
    }
    $rows = [];
    foreach ($items as $item) {
        if ($item['percent'] <= 0) {
            continue;
        }
    $rows[] = [
        'label' => $labelMap[$item['label']] ?? ucfirst((string) $item['label']),
        'percent' => $item['percent'],
        'count' => $item['count'],
    ];
    }
    usort($rows, static function (array $a, array $b): int {
        return $b['percent'] <=> $a['percent'];
    });
    return $rows;
};

$languageLabel = static function (string $code): string {
    $map = [
        'es' => 'Espanol',
        'en' => 'Ingles',
        'fr' => 'Frances',
        'de' => 'Aleman',
        'it' => 'Italiano',
        'pt' => 'Portugues',
        'ca' => 'Catalan',
        'eu' => 'Euskera',
        'gl' => 'Gallego',
    ];
    if (isset($map[$code])) {
        return $map[$code];
    }
    return strtoupper($code);
};

$deviceList = $buildPercentTable($platformDevices, [
    'desktop' => 'Escritorio',
    'mobile' => 'Movil',
    'tablet' => 'Tablet',
]);
$browserList = $buildPercentTable($platformBrowsers, [
    'chrome' => 'Chrome',
    'firefox' => 'Firefox',
    'edge' => 'Edge',
    'safari' => 'Safari',
    'opera' => 'Opera',
    'otros' => 'Otros',
]);
$systemList = $buildPercentTable($platformSystems, [
    'windows' => 'Windows',
    'macos' => 'macOS',
    'linux' => 'Linux',
    'chromeos' => 'ChromeOS',
    'otros' => 'Otros',
]);
$languageLabelMap = [];
foreach ($platformLanguages as $code => $uids) {
    $languageLabelMap[$code] = $languageLabel((string) $code);
}
$languageList = $buildPercentTable($platformLanguages, $languageLabelMap);

$sourceMainLabels = [
    'direct' => 'Entrada directa',
    'search' => 'Buscadores',
    'social' => 'Redes sociales',
    'email' => 'Lista de correo',
    'push' => 'Notificaciones push',
    'other' => 'Sitios web',
];
$isFediverseSourceLabel = static function (string $label, string $url = ''): bool {
    $label = strtolower(trim($label));
    $url = strtolower(trim($url));
    if ($label === 'fediverso' || $label === 'mastodon') {
        return true;
    }
    $haystack = $label . ' ' . $url;
    foreach ([
        'maximalismo.red',
        'mastodon.social',
        'mastodon.',
        'mstdn.',
        '.social',
        '.masto.host',
        '/@',
        '/users/',
        '/statuses/',
        '/notes/',
    ] as $needle) {
        if ($needle !== '' && str_contains($haystack, strtolower($needle))) {
            return true;
        }
    }
    return false;
};
$normalizeSocialSourceLabel = static function (string $label, string $url = '') use ($isFediverseSourceLabel): string {
    if ($isFediverseSourceLabel($label, $url)) {
        return 'Fediverso';
    }
    if (function_exists('nammu_normalize_stats_social_detail_label')) {
        return nammu_normalize_stats_social_detail_label($label, $url);
    }
    return trim($label);
};
$collectEmailDetails = static function (string $fromKey, ?string $toKey = null) use ($sourcesDaily, $emailBreakdownLabels): array {
    $details = [];
    $mailNeedles = [
        'mail.google.com',
        'gmail.com',
        'outlook.live.com',
        'outlook.com',
        'hotmail.com',
        'live.com',
        'protection.outlook.com',
        'mail.yahoo.com',
        'yahoo.com',
        'mail.aol.com',
        'aol.com',
        'icloud.com',
        'mail.icloud.com',
        'proton.me',
        'protonmail.com',
        'tutanota.com',
        'mail.com',
        'gmx.',
        'zoho.',
    ];
    foreach ($sourcesDaily as $day => $payload) {
        if (!is_string($day) || $day < $fromKey || ($toKey !== null && $day > $toKey)) {
            continue;
        }
        $bucketData = is_array($payload) ? ($payload['email'] ?? []) : [];
        $detailData = is_array($bucketData) ? ($bucketData['detail'] ?? []) : [];
        if (!is_array($detailData)) {
            continue;
        }
        foreach ($detailData as $label => $detailPayload) {
            if (!in_array((string) $label, $emailBreakdownLabels, true)) {
                continue;
            }
            if ((string) $label === 'Lista de correo') {
                $label = 'Suscriptores';
            }
            if (!isset($details[$label])) {
                $details[$label] = ['uids' => []];
            }
            $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
            if (is_array($detailUids)) {
                foreach ($detailUids as $uid => $flag) {
                    $details[$label]['uids'][$uid] = true;
                }
            }
        }
        $otherBucket = is_array($payload) ? ($payload['other'] ?? []) : [];
        $otherDetail = is_array($otherBucket) ? ($otherBucket['detail'] ?? []) : [];
        if (is_array($otherDetail)) {
            foreach ($otherDetail as $label => $detailPayload) {
                $key = strtolower(trim((string) $label));
                $isMail = false;
                foreach ($mailNeedles as $needle) {
                    if ($key === $needle || str_contains($key, $needle)) {
                        $isMail = true;
                        break;
                    }
                }
                if (!$isMail) {
                    continue;
                }
                if (!isset($details['Reenvios'])) {
                    $details['Reenvios'] = ['uids' => []];
                }
                $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
                if (is_array($detailUids)) {
                    foreach ($detailUids as $uid => $flag) {
                        $details['Reenvios']['uids'][$uid] = true;
                    }
                }
            }
        }
    }
    return $details;
};
$collectOtherDetails = static function (string $fromKey, ?string $toKey = null) use ($sourcesDaily, $emailDetailLabels, $normalizeSocialSourceLabel): array {
    $details = [];
    foreach ($sourcesDaily as $day => $payload) {
        if (!is_string($day) || $day < $fromKey || ($toKey !== null && $day > $toKey)) {
            continue;
        }
        $bucketData = is_array($payload) ? ($payload['other'] ?? []) : [];
        $detailData = is_array($bucketData) ? ($bucketData['detail'] ?? []) : [];
        if (!is_array($detailData)) {
            continue;
        }
        foreach ($detailData as $label => $detailPayload) {
            if (is_array($emailDetailLabels) && in_array((string) $label, $emailDetailLabels, true)) {
                continue;
            }
            $detailUrl = is_array($detailPayload) ? (string) ($detailPayload['url'] ?? '') : '';
            $normalizedSocialLabel = $normalizeSocialSourceLabel((string) $label, $detailUrl);
            if ($normalizedSocialLabel !== trim((string) $label)) {
                continue;
            }
            if (!isset($details[$label])) {
                $details[$label] = ['uids' => [], 'url' => ''];
            }
            $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
            if (is_array($detailUids)) {
                foreach ($detailUids as $uid => $flag) {
                    $details[$label]['uids'][$uid] = true;
                }
            }
            if ($detailUrl !== '') {
                $details[$label]['url'] = $detailUrl;
            }
        }
    }
    return $details;
};
$buildSourceRowsByPeriod = static function (string $fromKey, ?string $toKey = null) use (
    $sourcesDaily,
    $emailDetailLabels,
    $sourceMainLabels,
    $buildPercentTable,
    $collectEmailDetails,
    $collectOtherDetails,
    $isFediverseSourceLabel,
    $normalizeSocialSourceLabel
): array {
    $sourceMain = [];
    foreach (['direct', 'search', 'social', 'email', 'push', 'other'] as $bucket) {
        $sourceMain[$bucket] = [];
    }
    $socialDetailUids = [];
    $otherDetailUids = [];
    $otherUrls = [];
    foreach ($sourcesDaily as $day => $payload) {
        if (!is_string($day) || $day < $fromKey || ($toKey !== null && $day > $toKey)) {
            continue;
        }
        foreach (['direct', 'search', 'social', 'email', 'push', 'other'] as $bucket) {
            $bucketData = is_array($payload) ? ($payload[$bucket] ?? []) : [];
            $uids = is_array($bucketData) ? ($bucketData['uids'] ?? []) : [];
            foreach ($uids as $uid => $flag) {
                $sourceMain[$bucket][$uid] = true;
            }
            if ($bucket === 'other') {
                $details = is_array($bucketData) ? ($bucketData['detail'] ?? []) : [];
                if (is_array($details)) {
                    foreach ($details as $label => $detailPayload) {
                        if (!in_array((string) $label, $emailDetailLabels, true)) {
                            $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
                            $detailUrl = is_array($detailPayload) ? (string) ($detailPayload['url'] ?? '') : '';
                            $normalizedSocialLabel = $normalizeSocialSourceLabel((string) $label, $detailUrl);
                            if ($normalizedSocialLabel !== trim((string) $label)) {
                                foreach ($detailUids as $uid => $flag) {
                                    $sourceMain['social'][$uid] = true;
                                    $socialDetailUids[$normalizedSocialLabel][$uid] = true;
                                }
                                foreach ($detailUids as $uid => $flag) {
                                    unset($sourceMain['other'][$uid]);
                                }
                                continue;
                            }
                            foreach ($detailUids as $uid => $flag) {
                                $otherDetailUids[$label][$uid] = true;
                            }
                            if ($detailUrl !== '') {
                                $otherUrls[$label] = $detailUrl;
                            }
                            continue;
                        }
                        $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
                        foreach ($detailUids as $uid => $flag) {
                            $sourceMain['email'][$uid] = true;
                        }
                    }
                }
            }
        }
    }

    $collectSourceUidsRange = static function (string $category) use ($sourcesDaily, $fromKey, $toKey, $emailDetailLabels, $normalizeSocialSourceLabel): array {
        $result = [];
        foreach ($sourcesDaily as $day => $payload) {
            if (!is_string($day) || $day < $fromKey || ($toKey !== null && $day > $toKey)) {
                continue;
            }
            $bucket = is_array($payload) ? ($payload[$category] ?? []) : [];
            if (!is_array($bucket)) {
                continue;
            }
            $details = $bucket['detail'] ?? [];
            if (!is_array($details)) {
                continue;
            }
            foreach ($details as $label => $detailPayload) {
                if ($category === 'other' && in_array((string) $label, $emailDetailLabels, true)) {
                    continue;
                }
                if ($category === 'social') {
                    $detailUrl = is_array($detailPayload) ? (string) ($detailPayload['url'] ?? '') : '';
                    $label = $normalizeSocialSourceLabel((string) $label, $detailUrl);
                }
                $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
                foreach ($detailUids as $uid => $flag) {
                    $result[$label][$uid] = true;
                }
            }
        }
        return $result;
    };

    $sourceMainRowsLocal = $buildPercentTable($sourceMain, $sourceMainLabels);
    $searchDetailRowsLocal = $buildPercentTable($collectSourceUidsRange('search'), []);
    $socialDetailsMerged = $collectSourceUidsRange('social');
    foreach ($socialDetailUids as $label => $uids) {
        foreach ($uids as $uid => $flag) {
            $socialDetailsMerged[$label][$uid] = true;
        }
    }
    $socialDetailRowsLocal = $buildPercentTable($socialDetailsMerged, []);
    $pushDetailRowsLocal = $buildPercentTable($collectSourceUidsRange('push'), []);

    $emailDetails = $collectEmailDetails($fromKey, $toKey);
    $emailUids = [];
    foreach ($emailDetails as $label => $detailPayload) {
        $emailUids[$label] = $detailPayload['uids'] ?? [];
    }
    $emailDetailRowsLocal = $buildPercentTable($emailUids, []);
    $emailTotalUids = [];
    foreach ($emailUids as $uids) {
        if (!is_array($uids)) {
            continue;
        }
        foreach ($uids as $uid => $flag) {
            $emailTotalUids[$uid] = true;
        }
    }
    if (!empty($emailTotalUids)) {
        $sourceMain['email'] = $emailTotalUids;
        $sourceMainRowsLocal = $buildPercentTable($sourceMain, $sourceMainLabels);
    }

    $otherDetails = $collectOtherDetails($fromKey, $toKey);
    $otherUids = [];
    foreach ($otherDetails as $label => $detailPayload) {
        $otherUids[$label] = $detailPayload['uids'] ?? [];
        if (!empty($detailPayload['url'])) {
            $otherUrls[$label] = $detailPayload['url'];
        }
    }
    foreach ($otherDetailUids as $label => $uids) {
        foreach ($uids as $uid => $flag) {
            $otherUids[$label][$uid] = true;
        }
    }
    $otherDetailRowsLocal = $buildPercentTable($otherUids, []);
    foreach ($otherDetailRowsLocal as &$row) {
        $label = $row['label'] ?? '';
        if ($label !== '' && isset($otherUrls[$label])) {
            $row['url'] = $otherUrls[$label];
        }
    }
    unset($row);

    return [
        'main' => $sourceMainRowsLocal,
        'search' => $searchDetailRowsLocal,
        'social' => $socialDetailRowsLocal,
        'email' => $emailDetailRowsLocal,
        'push' => $pushDetailRowsLocal,
        'other' => $otherDetailRowsLocal,
    ];
};
$todayKeyForSources = $today->format('Y-m-d');
$sourceRows30 = $buildSourceRowsByPeriod($startKey, null);
$sourceRowsToday = $buildSourceRowsByPeriod($todayKeyForSources, $todayKeyForSources);
$sourceMainRows = $sourceRows30['main'];
$searchDetailRows = $sourceRows30['search'];
$socialDetailRows = $sourceRows30['social'];
$emailDetailRows = $sourceRows30['email'];
$pushDetailRows = $sourceRows30['push'];
$otherDetailRows = $sourceRows30['other'];

$searchTermCounts = [];
$searchTermUids = [];
foreach ($searchesDaily as $day => $payload) {
    if (!is_string($day) || $day < $startKey) {
        continue;
    }
    if (!is_array($payload)) {
        continue;
    }
    foreach ($payload as $term => $termData) {
        $termKey = trim((string) $term);
        if ($termKey === '') {
            continue;
        }
        $count = 0;
        $uids = [];
        if (is_array($termData)) {
            $count = (int) ($termData['count'] ?? 0);
            $uids = is_array($termData['uids'] ?? null) ? $termData['uids'] : [];
        } else {
            $count = (int) $termData;
        }
        if ($count > 0) {
            $searchTermCounts[$termKey] = ($searchTermCounts[$termKey] ?? 0) + $count;
        }
        if (!empty($uids)) {
            if (!isset($searchTermUids[$termKey])) {
                $searchTermUids[$termKey] = [];
            }
            foreach ($uids as $uid => $flag) {
                $searchTermUids[$termKey][$uid] = true;
            }
        }
    }
}
$searchCountsList = [];
foreach ($searchTermCounts as $term => $count) {
    $searchCountsList[] = ['term' => $term, 'count' => (int) $count];
}
usort($searchCountsList, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
$searchCountsList = array_slice($searchCountsList, 0, 10);

$searchUsersList = [];
foreach ($searchTermUids as $term => $uids) {
    $uniqueCount = is_array($uids) ? count($uids) : 0;
    if ($uniqueCount > 0) {
        $searchUsersList[] = ['term' => $term, 'count' => $uniqueCount];
    }
}
usort($searchUsersList, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
$searchUsersList = array_slice($searchUsersList, 0, 10);

$monthlyUids = [];
$monthlyTotals = [];
foreach ($combinedDailyUids as $day => $uids) {
    if (!is_string($day) || strlen($day) < 7) {
        continue;
    }
    $month = substr($day, 0, 7);
    if (!isset($monthlyUids[$month])) {
        $monthlyUids[$month] = [];
    }
    foreach ($uids as $uid => $flag) {
        $monthlyUids[$month][$uid] = true;
    }
    $monthlyTotals[$month] = ($monthlyTotals[$month] ?? 0) + count($uids);
}
$currentMonthKey = $today->format('Y-m');
if (!isset($monthlyUids[$currentMonthKey])) {
    $monthlyUids[$currentMonthKey] = [];
}
if (!isset($monthlyTotals[$currentMonthKey])) {
    $monthlyTotals[$currentMonthKey] = 0;
}
ksort($monthlyUids);

$last30Daily = [];
foreach ($last30UidSeries as $dayKey => $uids) {
    $last30Daily[$dayKey] = count($uids);
}
$last30DailyMax = max(1, max($last30Daily));
$last30Keys = array_keys($last30Daily);
$last30LabelStart = $last30Keys[0] ?? '';
$last30LabelMid = $last30Keys[(int) floor(count($last30Keys) / 2)] ?? '';
$last30LabelEnd = $last30Keys[count($last30Keys) - 1] ?? '';

$last12Months = [];
$monthCursor = $today->modify('first day of this month');
for ($i = 11; $i >= 0; $i--) {
    $monthKey = $monthCursor->modify('-' . $i . ' months')->format('Y-m');
    $last12Months[$monthKey] = isset($monthlyUids[$monthKey]) ? count($monthlyUids[$monthKey]) : 0;
}
$last12MonthsMax = max(1, max($last12Months));
$last12Keys = array_keys($last12Months);
$last12LabelStart = $last12Keys[0] ?? '';
$last12LabelMid = $last12Keys[(int) floor(count($last12Keys) / 2)] ?? '';
$last12LabelEnd = $last12Keys[count($last12Keys) - 1] ?? '';

$formatDateEs = static function (string $date): string {
    try {
        $dt = new DateTimeImmutable($date);
        return $dt->format('d/m/y');
    } catch (Throwable $e) {
        return $date;
    }
};
$formatDayMonthEs = static function (string $date): string {
    try {
        $dt = new DateTimeImmutable($date);
        return $dt->format('d/m');
    } catch (Throwable $e) {
        return $date;
    }
};
$formatMonthEs = static function (string $date): string {
    try {
        $dt = new DateTimeImmutable($date);
        return $dt->format('m/y');
    } catch (Throwable $e) {
        return $date;
    }
};

$chartTop = 30;
$chartBottom = 150;
$buildLinePoints = static function (array $series, int $max, int $top, int $bottom): array {
    $count = count($series);
    if ($count === 0) {
        return ['points' => '', 'coords' => [], 'max' => 0, 'maxIndex' => null];
    }
    $width = 270;
    $height = $bottom - $top;
    $step = $count > 1 ? ($width / ($count - 1)) : 0;
    $points = [];
    $pathCommands = [];
    $coords = [];
    $maxValue = 0;
    $maxIndex = null;
    $index = 0;
    foreach ($series as $value) {
        $x = $step * $index;
        $ratio = $max > 0 ? ($value / $max) : 0;
        $y = $bottom - ($ratio * $height);
        $xFormatted = number_format($x, 2, '.', '');
        $yFormatted = number_format($y, 2, '.', '');
        $points[] = $xFormatted . ',' . $yFormatted;
        $pathCommands[] = ($index === 0 ? 'M ' : 'L ') . $xFormatted . ' ' . $yFormatted;
        $coords[] = ['x' => $xFormatted, 'y' => $yFormatted, 'value' => (int) $value];
        if ($value >= $maxValue) {
            $maxValue = (int) $value;
            $maxIndex = $index;
        }
        $index++;
    }
    return [
        'points' => implode(' ', $points),
        'path' => implode(' ', $pathCommands),
        'coords' => $coords,
        'max' => $maxValue,
        'maxIndex' => $maxIndex,
    ];
};

$last30Line = $buildLinePoints($last30Daily, $last30DailyMax, $chartTop, $chartBottom);
$last12Line = $buildLinePoints($last12Months, $last12MonthsMax, $chartTop, $chartBottom);

$yearlyUids = [];
$yearlyTotals = [];
foreach ($combinedDailyUids as $day => $uids) {
    if (!is_string($day) || strlen($day) < 4) {
        continue;
    }
    $yearKey = substr($day, 0, 4);
    if (!isset($yearlyUids[$yearKey])) {
        $yearlyUids[$yearKey] = [];
    }
    foreach ($uids as $uid => $flag) {
        $yearlyUids[$yearKey][$uid] = true;
    }
    $yearlyTotals[$yearKey] = ($yearlyTotals[$yearKey] ?? 0) + count($uids);
}
$currentYearKey = $today->format('Y');
if (!isset($yearlyUids[$currentYearKey])) {
    $yearlyUids[$currentYearKey] = [];
}
if (!isset($yearlyTotals[$currentYearKey])) {
    $yearlyTotals[$currentYearKey] = 0;
}
ksort($yearlyUids);

$todayKey = $today->format('Y-m-d');
$todayUids = is_array($combinedDailyUids[$todayKey] ?? null) ? $combinedDailyUids[$todayKey] : [];
$todayCount = count($todayUids);

$dayNames = [
    'sun' => 'Domingo',
    'mon' => 'Lunes',
    'tue' => 'Martes',
    'wed' => 'Miercoles',
    'thu' => 'Jueves',
    'fri' => 'Viernes',
    'sat' => 'Sabado',
];
$monthNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
$last7DailyList = [];
$dailyKeys = array_keys($combinedDailyUids);
sort($dailyKeys);
$firstDayKey = $dailyKeys[0] ?? '';
for ($i = 0; $i < 7; $i++) {
    $day = $today->modify('-' . $i . ' days');
    $dayKey = $day->format('Y-m-d');
    if ($firstDayKey !== '' && $dayKey < $firstDayKey) {
        break;
    }
    $uids = is_array($combinedDailyUids[$dayKey] ?? null) ? $combinedDailyUids[$dayKey] : [];
    $dayLabelKey = strtolower($day->format('D'));
    $last7DailyList[] = [
        'label' => $dayNames[$dayLabelKey] ?? $dayKey,
        'count' => count($uids),
    ];
}

$last12MonthsList = [];
$monthlyKeys = array_keys($monthlyUids);
sort($monthlyKeys);
$firstMonthKey = $monthlyKeys[0] ?? '';
$monthCursor = $today->modify('first day of this month');
for ($i = 0; $i < 12; $i++) {
    $month = $monthCursor->modify('-' . $i . ' months');
    $monthKey = $month->format('Y-m');
    if ($firstMonthKey !== '' && $monthKey < $firstMonthKey) {
        break;
    }
    $count = isset($monthlyUids[$monthKey]) ? count($monthlyUids[$monthKey]) : 0;
    $monthNum = (int) $month->format('n');
    $last12MonthsList[] = [
        'label' => ($monthNames[$monthNum] ?? $month->format('m')) . ' ' . $month->format('Y'),
        'count' => $count,
    ];
}

$yearList = [];
$yearKeys = array_keys($yearlyUids);
rsort($yearKeys);
foreach ($yearKeys as $year) {
    if ($year === '') {
        continue;
    }
    $yearList[] = [
        'label' => $year,
        'count' => isset($yearlyUids[$year]) ? count($yearlyUids[$year]) : 0,
    ];
}
