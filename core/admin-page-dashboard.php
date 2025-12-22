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
                                                <td><?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?></td>
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
            </div>
        </div>
    </div>
<?php endif; ?>
