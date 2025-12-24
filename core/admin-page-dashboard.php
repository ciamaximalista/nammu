<?php if ($page === 'dashboard'): ?>
    <?php
    $postsMetadata = get_all_posts_metadata();
    $postCount = 0;
    $pageCount = 0;
    foreach ($postsMetadata as $item) {
        $template = strtolower($item['metadata']['Template'] ?? 'post');
        if ($template === 'page') {
            $pageCount++;
        } elseif (in_array($template, ['post', 'single'], true)) {
            $postCount++;
        }
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

    $today = new DateTimeImmutable('today');
    $last30Start = $today->modify('-29 days');
    $last7Start = $today->modify('-6 days');

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

    $unique30 = [];
    $startKey = $last30Start->format('Y-m-d');
    foreach ($visitorsDaily as $day => $payload) {
        if (!is_string($day) || $day < $startKey) {
            continue;
        }
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
        foreach ($uids as $uid => $flag) {
            $unique30[$uid] = true;
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

    $monthlyUids = [];
    $monthlyTotals = [];
    foreach ($visitorsDaily as $day => $payload) {
        if (!is_string($day) || strlen($day) < 7) {
            continue;
        }
        $month = substr($day, 0, 7);
        if (!isset($monthlyUids[$month])) {
            $monthlyUids[$month] = [];
        }
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
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
    for ($i = 29; $i >= 0; $i--) {
        $dayKey = $today->modify('-' . $i . ' days')->format('Y-m-d');
        $payload = $visitorsDaily[$dayKey] ?? [];
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
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
        $coords = [];
        $maxValue = 0;
        $maxIndex = null;
        $index = 0;
        foreach ($series as $value) {
            $x = $step * $index;
            $ratio = $max > 0 ? ($value / $max) : 0;
            $y = $bottom - ($ratio * $height);
            $points[] = sprintf('%.2f,%.2f', $x, $y);
            $coords[] = ['x' => $x, 'y' => $y, 'value' => (int) $value];
            if ($value >= $maxValue) {
                $maxValue = (int) $value;
                $maxIndex = $index;
            }
            $index++;
        }
        return [
            'points' => implode(' ', $points),
            'coords' => $coords,
            'max' => $maxValue,
            'maxIndex' => $maxIndex,
        ];
    };

    $last30Line = $buildLinePoints($last30Daily, $last30DailyMax, $chartTop, $chartBottom);
    $last12Line = $buildLinePoints($last12Months, $last12MonthsMax, $chartTop, $chartBottom);

    $yearlyUids = [];
    $yearlyTotals = [];
    foreach ($visitorsDaily as $day => $payload) {
        if (!is_string($day) || strlen($day) < 4) {
            continue;
        }
        $yearKey = substr($day, 0, 4);
        if (!isset($yearlyUids[$yearKey])) {
            $yearlyUids[$yearKey] = [];
        }
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
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
    $todayPayload = $visitorsDaily[$todayKey] ?? [];
    $todayUids = is_array($todayPayload) ? ($todayPayload['uids'] ?? []) : [];
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
    $dailyKeys = array_keys($visitorsDaily);
    sort($dailyKeys);
    $firstDayKey = $dailyKeys[0] ?? '';
    for ($i = 0; $i < 7; $i++) {
        $day = $today->modify('-' . $i . ' days');
        $dayKey = $day->format('Y-m-d');
        if ($firstDayKey !== '' && $dayKey < $firstDayKey) {
            break;
        }
        $payload = $visitorsDaily[$dayKey] ?? [];
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
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
        $count = $monthlyTotals[$monthKey] ?? 0;
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
            'count' => $yearlyTotals[$year] ?? 0,
        ];
    }

    $allPosts = [];
    foreach ($postsStats as $slug => $item) {
        $total = (int) ($item['total'] ?? 0);
        if ($total <= 0) {
            continue;
        }
        $allPosts[] = [
            'slug' => $slug,
            'title' => $item['title'] ?? $slug,
            'count' => $total,
            'unique' => $uniqueAll($item['daily'] ?? []),
        ];
    }
    $topPosts = $allPosts;
    usort($topPosts, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topPosts = array_slice($topPosts, 0, 10);
    $topPostsByUnique = array_values(array_filter($allPosts, static function (array $item): bool {
        return (int) ($item['unique'] ?? 0) > 0;
    }));
    usort($topPostsByUnique, static function (array $a, array $b): int {
        return $b['unique'] <=> $a['unique'];
    });
    $topPostsByUnique = array_slice($topPostsByUnique, 0, 10);

    $topPostsWeek = [];
    $topPostsMonth = [];
    foreach ($postsStats as $slug => $item) {
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

    $allPages = [];
    foreach ($pagesStats as $slug => $item) {
        $total = (int) ($item['total'] ?? 0);
        if ($total <= 0) {
            continue;
        }
        $allPages[] = [
            'slug' => $slug,
            'title' => $item['title'] ?? $slug,
            'count' => $total,
            'unique' => $uniqueAll($item['daily'] ?? []),
        ];
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

    $topItineraryStarts = [];
    $topItineraryCompletes = [];
    foreach ($itineraries ?? [] as $itineraryItem) {
        $slug = $itineraryItem->getSlug();
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
        if ($topicOneStarters > 0 || $presentationReaders > 0) {
            $topItineraryStarts[] = [
                'slug' => $slug,
                'title' => $itineraryTitleMap[$slug] ?? $slug,
                'count' => $topicOneStarters > 0 ? $topicOneStarters : $presentationReaders,
            ];
        }
        if ($lastTopicCount > 0) {
            $topItineraryCompletes[] = [
                'slug' => $slug,
                'title' => $itineraryTitleMap[$slug] ?? $slug,
                'count' => $lastTopicCount,
            ];
        }
    }
    usort($topItineraryStarts, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    usort($topItineraryCompletes, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topItineraryStarts = array_slice($topItineraryStarts, 0, 10);
    $topItineraryCompletes = array_slice($topItineraryCompletes, 0, 10);

    $resourceCounts = [
        'images' => 0,
        'videos' => 0,
        'audios' => 0,
        'pdfs' => 0,
        'epubs' => 0,
        'docs' => 0,
        'others' => 0,
    ];
    if (function_exists('get_media_items')) {
        $mediaItems = get_media_items(1, 0);
        foreach ($mediaItems['items'] ?? [] as $item) {
            $ext = strtolower((string) ($item['extension'] ?? ''));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
                $resourceCounts['images']++;
            } elseif (in_array($ext, ['mp4', 'webm', 'mov', 'm4v', 'ogv', 'ogg'], true)) {
                $resourceCounts['videos']++;
            } elseif (in_array($ext, ['mp3', 'wav', 'flac', 'm4a', 'aac', 'oga'], true)) {
                $resourceCounts['audios']++;
            } elseif ($ext === 'pdf') {
                $resourceCounts['pdfs']++;
            } elseif ($ext === 'epub') {
                $resourceCounts['epubs']++;
            } elseif (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'md', 'txt', 'rtf'], true)) {
                $resourceCounts['docs']++;
            } else {
                $resourceCounts['others']++;
            }
        }
    }

    $subscriberCount = 0;
    if (function_exists('admin_load_mailing_subscribers')) {
        try {
            $subscriberCount = count(admin_load_mailing_subscribers());
        } catch (Throwable $e) {
            $subscriberCount = 0;
        }
    }
    $postalSubscriberCount = 0;
    if (function_exists('postal_load_entries')) {
        try {
            $postalSubscriberCount = count(postal_load_entries());
        } catch (Throwable $e) {
            $postalSubscriberCount = 0;
        }
    }
    $pushSubscriberCount = 0;
    $pushEnabled = false;
    $socialCounts = [];
    if (function_exists('get_settings')) {
        $settings = get_settings();
        $pushEnabled = (($settings['ads']['push_enabled'] ?? 'off') === 'on');
        if ($pushEnabled && function_exists('nammu_push_subscriber_count')) {
            $pushSubscriberCount = nammu_push_subscriber_count();
        }
        $telegramCount = admin_get_telegram_follower_count($settings['telegram'] ?? []);
        if ($telegramCount !== null) {
            $socialCounts['Telegram'] = $telegramCount;
        }
        $facebookCount = admin_get_facebook_follower_count($settings['facebook'] ?? []);
        if ($facebookCount !== null) {
            $socialCounts['Facebook'] = $facebookCount;
        }
        $twitterCount = admin_get_twitter_follower_count($settings['twitter'] ?? []);
        if ($twitterCount !== null) {
            $socialCounts['Twitter/X'] = $twitterCount;
        }
    }
    ?>

    <div class="tab-pane active">
        <style>
            .dashboard-card-title {
                color: #1b8eed !important;
            }
            .dashboard-section-title {
                color: #ea2f28 !important;
            }
            .dashboard-links a {
                color: #7fa7d9 !important;
            }
            .dashboard-links a:hover {
                color: #7fa7d9 !important;
            }
            .dashboard-links li:first-child {
                background: #1b8eed;
                border-radius: 8px;
                padding: 0.2rem 0.4rem;
            }
            .dashboard-links li:first-child a,
            .dashboard-links li:first-child span {
                color: #ffffff !important;
            }
            .dashboard-toggle .btn {
                color: #1b8eed;
                border-color: #1b8eed;
            }
            .dashboard-toggle .btn.active {
                background: #1b8eed;
                color: #ffffff;
                border-color: #1b8eed;
            }
        </style>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
            <div>
                <h2 class="mb-1">Escritorio Nammu</h2>
                <p class="text-muted mb-0">Resumen general de publicaciones y estadisticas del sitio.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Usuarios únicos (últimos 30 días)</h4>
                        <?php if ($last30Line['points'] === ''): ?>
                            <p class="text-muted mb-0">Sin datos todavia.</p>
                        <?php else: ?>
                            <svg width="100%" height="170" viewBox="0 0 320 170" preserveAspectRatio="none" aria-hidden="true">
                                <line x1="30" y1="<?= (int) $chartTop ?>" x2="30" y2="<?= (int) $chartBottom ?>" stroke="#ccd6e0" stroke-width="1"></line>
                                <line x1="30" y1="<?= (int) $chartBottom ?>" x2="300" y2="<?= (int) $chartBottom ?>" stroke="#ccd6e0" stroke-width="1"></line>
                                <text x="4" y="<?= (int) $chartTop ?>" font-size="10" fill="#6c757d"><?= (int) $last30DailyMax ?></text>
                                <text x="12" y="<?= (int) $chartBottom ?>" font-size="10" fill="#6c757d">0</text>
                                <text x="30" y="166" font-size="10" text-anchor="start" fill="#6c757d"><?= htmlspecialchars($formatDayMonthEs($last30LabelStart), ENT_QUOTES, 'UTF-8') ?></text>
                                <text x="165" y="166" font-size="10" text-anchor="middle" fill="#6c757d"><?= htmlspecialchars($formatDayMonthEs($last30LabelMid), ENT_QUOTES, 'UTF-8') ?></text>
                                <text x="300" y="166" font-size="10" text-anchor="end" fill="#6c757d"><?= htmlspecialchars($formatDayMonthEs($last30LabelEnd), ENT_QUOTES, 'UTF-8') ?></text>
                                <g transform="translate(30,0)">
                                    <polyline fill="none" stroke="#1b8eed" stroke-width="2" points="<?= htmlspecialchars($last30Line['points'], ENT_QUOTES, 'UTF-8') ?>"></polyline>
                                    <?php foreach ($last30Line['coords'] as $point): ?>
                                        <circle cx="<?= htmlspecialchars((string) $point['x'], ENT_QUOTES, 'UTF-8') ?>" cy="<?= htmlspecialchars((string) $point['y'], ENT_QUOTES, 'UTF-8') ?>" r="2.5" fill="#1b8eed"></circle>
                                    <?php endforeach; ?>
                                </g>
                            </svg>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Usuarios unicos (ultimo ano)</h4>
                        <?php if ($last12Line['points'] === ''): ?>
                            <p class="text-muted mb-0">Sin datos todavia.</p>
                        <?php else: ?>
                            <svg width="100%" height="170" viewBox="0 0 320 170" preserveAspectRatio="none" aria-hidden="true">
                                <line x1="30" y1="<?= (int) $chartTop ?>" x2="30" y2="<?= (int) $chartBottom ?>" stroke="#ccd6e0" stroke-width="1"></line>
                                <line x1="30" y1="<?= (int) $chartBottom ?>" x2="300" y2="<?= (int) $chartBottom ?>" stroke="#ccd6e0" stroke-width="1"></line>
                                <text x="4" y="<?= (int) $chartTop ?>" font-size="10" fill="#6c757d"><?= (int) $last12MonthsMax ?></text>
                                <text x="12" y="<?= (int) $chartBottom ?>" font-size="10" fill="#6c757d">0</text>
                                <text x="30" y="166" font-size="10" text-anchor="start" fill="#6c757d"><?= htmlspecialchars($formatMonthEs($last12LabelStart . '-01'), ENT_QUOTES, 'UTF-8') ?></text>
                                <text x="165" y="166" font-size="10" text-anchor="middle" fill="#6c757d"><?= htmlspecialchars($formatMonthEs($last12LabelMid . '-01'), ENT_QUOTES, 'UTF-8') ?></text>
                                <text x="300" y="166" font-size="10" text-anchor="end" fill="#6c757d"><?= htmlspecialchars($formatMonthEs($last12LabelEnd . '-01'), ENT_QUOTES, 'UTF-8') ?></text>
                                <g transform="translate(30,0)">
                                    <polyline fill="none" stroke="#0a4c8a" stroke-width="2" points="<?= htmlspecialchars($last12Line['points'], ENT_QUOTES, 'UTF-8') ?>"></polyline>
                                    <?php foreach ($last12Line['coords'] as $point): ?>
                                        <circle cx="<?= htmlspecialchars((string) $point['x'], ENT_QUOTES, 'UTF-8') ?>" cy="<?= htmlspecialchars((string) $point['y'], ENT_QUOTES, 'UTF-8') ?>" r="2.5" fill="#0a4c8a"></circle>
                                    <?php endforeach; ?>
                                </g>
                            </svg>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Publicaciones</h4>
                        <p class="mb-2"><strong>Entradas:</strong> <?= (int) $postCount ?></p>
                        <p class="mb-2"><strong>Paginas:</strong> <?= (int) $pageCount ?></p>
                        <p class="mb-0"><strong>Itinerarios:</strong> <?= (int) $itineraryCount ?></p>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Recursos</h4>
                        <?php if ($resourceCounts['images'] > 0): ?>
                            <p class="mb-2"><strong>Imagenes:</strong> <?= (int) $resourceCounts['images'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['videos'] > 0): ?>
                            <p class="mb-2"><strong>Videos:</strong> <?= (int) $resourceCounts['videos'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['audios'] > 0): ?>
                            <p class="mb-2"><strong>Audios:</strong> <?= (int) $resourceCounts['audios'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['pdfs'] > 0): ?>
                            <p class="mb-2"><strong>PDFs:</strong> <?= (int) $resourceCounts['pdfs'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['epubs'] > 0): ?>
                            <p class="mb-2"><strong>EPUBs:</strong> <?= (int) $resourceCounts['epubs'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['docs'] > 0): ?>
                            <p class="mb-2"><strong>Documentos:</strong> <?= (int) $resourceCounts['docs'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['others'] > 0): ?>
                            <p class="mb-0"><strong>Otros:</strong> <?= (int) $resourceCounts['others'] ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Suscriptores</h4>
                        <p class="mb-2"><strong>Lista de correo:</strong> <?= (int) $subscriberCount ?></p>
                        <?php if ($postalSubscriberCount > 0): ?>
                            <p class="mb-2"><strong>Correo postal:</strong> <?= (int) $postalSubscriberCount ?></p>
                        <?php endif; ?>
                        <?php if ($pushEnabled && $pushSubscriberCount > 0): ?>
                            <p class="mb-2"><strong>Notificaciones Push:</strong> <?= (int) $pushSubscriberCount ?></p>
                        <?php endif; ?>
                        <?php if (!empty($socialCounts)): ?>
                            <?php foreach ($socialCounts as $label => $count): ?>
                                <p class="mb-2"><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>:</strong> <?= (int) $count ?></p>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Usuarios unicos</h4>
                        <p class="mb-3"><strong>Hoy:</strong> <?= (int) $todayCount ?></p>

                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Ultimos 7 dias</p>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($last7DailyList as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-right"><?= (int) $item['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Ultimos 12 meses</p>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($last12MonthsList as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-right"><?= (int) $item['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Años</p>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php if (empty($yearList)): ?>
                                        <tr>
                                            <td colspan="2" class="text-muted">Sin datos todavia.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($yearList as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-right"><?= (int) $item['count'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <?php
                $hasPostStats = !empty($topPosts) || !empty($topPostsByUnique)
                    || !empty($topPostsWeek) || !empty($topPostsWeekByUnique)
                    || !empty($topPostsMonth) || !empty($topPostsMonthByUnique);
                ?>
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Entradas mas leidas</h4>
                            <div class="d-flex flex-column align-items-start">
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="posts" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="views">Vistas</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="users">Usuarios</button>
                                </div>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="posts" data-stat-toggle-type="period">
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="week">Ultimos 7 dias</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="month">Ultimos 30 dias</button>
                                    <button type="button" class="btn btn-outline-primary active" data-stat-period="all">Desde el comienzo del blog</button>
                                </div>
                            </div>
                        </div>
                        <?php if (!$hasPostStats): ?>
                            <p class="text-muted mb-0">Sin datos todavia.</p>
                        <?php else: ?>
                            <ol class="mb-0 dashboard-links" data-stat-list="posts" data-stat-mode="views" data-stat-period="all">
                                <?php foreach ($topPosts as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="users" data-stat-period="all">
                                <?php foreach ($topPostsByUnique as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="views" data-stat-period="week">
                                <?php foreach ($topPostsWeek as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="users" data-stat-period="week">
                                <?php foreach ($topPostsWeekByUnique as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="views" data-stat-period="month">
                                <?php foreach ($topPostsMonth as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="users" data-stat-period="month">
                                <?php foreach ($topPostsMonthByUnique as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($pageCount > 0): ?>
                    <div class="card mb-4 dashboard-stat-block">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Paginas mas leidas</h4>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle" role="group" data-stat-toggle="pages-all" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="views">Vistas</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="users">Usuarios</button>
                                </div>
                            </div>
                            <?php if (empty($topPages) && empty($topPagesByUnique)): ?>
                                <p class="text-muted mb-0">Sin datos todavia.</p>
                            <?php else: ?>
                                <ol class="mb-0 dashboard-links" data-stat-list="pages-all" data-stat-mode="views">
                                    <?php foreach ($topPages as $item): ?>
                                        <li>
                                            <?php $url = admin_public_post_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="pages-all" data-stat-mode="users">
                                    <?php foreach ($topPagesByUnique as $item): ?>
                                        <li>
                                            <?php $url = admin_public_post_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($itineraryCount > 0): ?>
                    <div class="card mb-4 dashboard-stat-block">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Itinerarios (usuarios unicos)</h4>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle" role="group" data-stat-toggle="itineraries" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="starts">Comenzaron</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="completes">Completaron</button>
                                </div>
                            </div>
                            <?php if (empty($topItineraryStarts) && empty($topItineraryCompletes)): ?>
                                <p class="text-muted mb-0">Sin datos todavia.</p>
                            <?php else: ?>
                                <ol class="mb-0 dashboard-links" data-stat-list="itineraries" data-stat-mode="starts">
                                    <?php foreach ($topItineraryStarts as $item): ?>
                                        <li>
                                            <?php $url = admin_public_itinerary_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="itineraries" data-stat-mode="completes">
                                    <?php foreach ($topItineraryCompletes as $item): ?>
                                        <li>
                                            <?php $url = admin_public_itinerary_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Plataforma (ultimos 30 dias)</h4>
                        <?php if (empty($deviceList) && empty($browserList) && empty($systemList) && empty($languageList)): ?>
                            <p class="text-muted mb-0">Sin datos todavia.</p>
                        <?php else: ?>
                            <?php if (!empty($deviceList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Dispositivo</p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($deviceList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($browserList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Navegador</p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($browserList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($systemList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Sistema (escritorio)</p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($systemList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($languageList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Lengua</p>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($languageList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('.dashboard-stat-block').forEach(function(block) {
            function updateLists() {
                var modeBtn = block.querySelector('[data-stat-toggle-type="mode"] .active');
                var periodBtn = block.querySelector('[data-stat-toggle-type="period"] .active');
                var mode = modeBtn ? modeBtn.getAttribute('data-stat-mode') : null;
                var period = periodBtn ? periodBtn.getAttribute('data-stat-period') : null;

                block.querySelectorAll('[data-stat-list]').forEach(function(list) {
                    var match = true;
                    if (mode && list.getAttribute('data-stat-mode') !== mode) {
                        match = false;
                    }
                    if (period && list.getAttribute('data-stat-period') !== period) {
                        match = false;
                    }
                    list.classList.toggle('d-none', !match);
                });
            }

            block.querySelectorAll('[data-stat-toggle]').forEach(function(group) {
                group.addEventListener('click', function(event) {
                    var btn = event.target.closest('[data-stat-mode], [data-stat-period]');
                    if (!btn) {
                        return;
                    }
                    group.querySelectorAll('[data-stat-mode], [data-stat-period]').forEach(function(item) {
                        item.classList.toggle('active', item === btn);
                    });
                    updateLists();
                });
            });

            updateLists();
        });
    </script>
<?php endif; ?>
