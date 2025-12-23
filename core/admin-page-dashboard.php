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
            $total += (int) $count;
        }
        return $total;
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

    $monthlyUids = [];
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
    }
    ksort($yearlyUids);

    $topPosts = [];
    foreach ($postsStats as $slug => $item) {
        $total = (int) ($item['total'] ?? 0);
        if ($total <= 0) {
            continue;
        }
        $topPosts[] = [
            'slug' => $slug,
            'title' => $item['title'] ?? $slug,
            'count' => $total,
        ];
    }
    usort($topPosts, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topPosts = array_slice($topPosts, 0, 10);

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
            ];
        }
        if ($countMonth > 0) {
            $topPostsMonth[] = [
                'slug' => $slug,
                'title' => $item['title'] ?? $slug,
                'count' => $countMonth,
            ];
        }
    }
    usort($topPostsWeek, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    usort($topPostsMonth, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topPostsWeek = array_slice($topPostsWeek, 0, 10);
    $topPostsMonth = array_slice($topPostsMonth, 0, 10);

    $topPages = [];
    foreach ($pagesStats as $slug => $item) {
        $total = (int) ($item['total'] ?? 0);
        if ($total <= 0) {
            continue;
        }
        $topPages[] = [
            'slug' => $slug,
            'title' => $item['title'] ?? $slug,
            'count' => $total,
        ];
    }
    usort($topPages, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topPages = array_slice($topPages, 0, 10);

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
    $socialCounts = [];
    if (function_exists('get_settings')) {
        $settings = get_settings();
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
                        <h4 class="h6 text-uppercase text-muted mb-3">Publicaciones</h4>
                        <p class="mb-2"><strong>Entradas:</strong> <?= (int) $postCount ?></p>
                        <p class="mb-2"><strong>Paginas:</strong> <?= (int) $pageCount ?></p>
                        <p class="mb-0"><strong>Itinerarios:</strong> <?= (int) $itineraryCount ?></p>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Recursos</h4>
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
                        <h4 class="h6 text-uppercase text-muted mb-3">Suscriptores</h4>
                        <p class="mb-2"><strong>Lista de correo:</strong> <?= (int) $subscriberCount ?></p>
                        <?php if (!empty($socialCounts)): ?>
                            <?php foreach ($socialCounts as $label => $count): ?>
                                <p class="mb-2"><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>:</strong> <?= (int) $count ?></p>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Usuarios unicos</h4>
                        <p class="mb-2"><strong>Ultimos 30 dias:</strong> <?= (int) $unique30Count ?></p>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Mes</th>
                                        <th>Usuarios</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($monthlyUids)): ?>
                                        <tr>
                                            <td colspan="2" class="text-muted">Sin datos todavia.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($monthlyUids as $month => $uids): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($formatDateEs($month . '-01'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= (int) count($uids) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Año</th>
                                        <th>Usuarios</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($yearlyUids)): ?>
                                        <tr>
                                            <td colspan="2" class="text-muted">Sin datos todavia.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($yearlyUids as $year => $uids): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= (int) count($uids) ?></td>
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
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Entradas mas leidas</h4>
                        <?php if (empty($topPosts)): ?>
                            <p class="text-muted mb-0">Sin datos todavia.</p>
                        <?php else: ?>
                            <ol class="mb-0">
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
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Entradas mas leidas (ultima semana)</h4>
                        <?php if (empty($topPostsWeek)): ?>
                            <p class="text-muted mb-0">Sin datos todavia.</p>
                        <?php else: ?>
                            <ol class="mb-0">
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
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Entradas mas leidas (ultimo mes)</h4>
                        <?php if (empty($topPostsMonth)): ?>
                            <p class="text-muted mb-0">Sin datos todavia.</p>
                        <?php else: ?>
                            <ol class="mb-0">
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
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($pageCount > 0): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="h6 text-uppercase text-muted mb-3">Paginas mas leidas</h4>
                            <?php if (empty($topPages)): ?>
                                <p class="text-muted mb-0">Sin datos todavia.</p>
                            <?php else: ?>
                                <ol class="mb-0">
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
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($itineraryCount > 0): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="h6 text-uppercase text-muted mb-3">Itinerarios comenzados (usuarios unicos)</h4>
                            <?php if (empty($topItineraryStarts)): ?>
                                <p class="text-muted mb-0">Sin datos todavia.</p>
                            <?php else: ?>
                                <ol class="mb-0">
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
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="h6 text-uppercase text-muted mb-3">Itinerarios completados (usuarios unicos)</h4>
                            <?php if (empty($topItineraryCompletes)): ?>
                                <p class="text-muted mb-0">Sin datos todavia.</p>
                            <?php else: ?>
                                <ol class="mb-0">
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
            </div>
        </div>
    </div>
<?php endif; ?>
