<?php
/**
 * Nammu — panel de administración.
 * Regeneración de artefactos públicos: rss.xml, blog.xml, podcast.xml, itinerarios.xml, sitemap.xml y su cola.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

use Nammu\Core\Itinerary;
use Nammu\Core\ItineraryTopic;
use Nammu\Core\Post;
use Nammu\Core\SitemapGenerator;

function admin_regenerate_itinerary_feed(): void {
    try {
        $repository = admin_itinerary_repository();
        $itineraries = $repository->all();
        $baseUrl = nammu_base_url();
        $config = load_config_file();
        $siteTitle = trim((string) ($config['site_name'] ?? 'Nammu Blog'));
        $siteDescription = trim((string) ($config['social']['default_description'] ?? ''));
        $siteLang = $config['site_lang'] ?? 'es';
        if (!is_string($siteLang) || $siteLang === '') {
            $siteLang = 'es';
        }
        $feedContent = nammu_generate_itineraries_rss_feed($baseUrl, $itineraries, $siteTitle, $siteDescription, $siteLang);
        admin_write_public_artifact(NAMMU_ROOT . '/itinerarios.xml', $feedContent);
    } catch (Throwable $e) {
        error_log('No se pudo regenerar itinerarios.xml: ' . $e->getMessage());
    }
}

function admin_write_public_artifact(string $path, string $payload): bool {
    if (function_exists('nammu_atomic_write_file')) {
        return nammu_atomic_write_file($path, $payload);
    }
    $saved = @file_put_contents($path, $payload, LOCK_EX) !== false;
    if ($saved) {
        @chmod($path, 0664);
    }
    return $saved;
}

function admin_build_sitemap_entries(array $posts, array $theme, string $publicBaseUrl): array {
    $itineraryListing = admin_list_itineraries();
    $base = $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '';
    $buildItineraryUrl = static function (Itinerary $itinerary) use ($base): string {
        $path = '/itinerarios/' . rawurlencode($itinerary->getSlug());
        return $base !== '' ? $base . $path : $path;
    };
    $buildItineraryTopicUrl = static function (Itinerary $itinerary, ItineraryTopic $topic) use ($base): string {
        $path = '/itinerarios/' . rawurlencode($itinerary->getSlug()) . '/' . rawurlencode($topic->getSlug());
        return $base !== '' ? $base . $path : $path;
    };
    $settings = get_settings();
    $sortOrder = strtolower(trim((string) ($settings['sort_order'] ?? 'date')));
    $isAlphabeticalOrder = $sortOrder === 'alphabetical';
    $podcastItems = nammu_collect_podcast_items(NAMMU_ROOT . '/content', $publicBaseUrl);
    $hasPodcast = !empty($podcastItems);

    $entries = [];
    $timestampFromPost = static function (Post $post): ?int {
        $date = $post->getDate();
        if ($date) {
            return $date->setTime(0, 0)->getTimestamp();
        }
        $raw = $post->getRawDate();
        if ($raw) {
            $ts = strtotime($raw);
            if ($ts !== false) {
                return $ts;
            }
        }
        return null;
    };

    $latestTimestamp = null;
    foreach ($posts as $post) {
        $ts = $timestampFromPost($post);
        if ($ts !== null) {
            $latestTimestamp = $latestTimestamp === null ? $ts : max($latestTimestamp, $ts);
        }
    }

    $entries[] = [
        'loc' => '/',
        'lastmod' => $latestTimestamp !== null ? gmdate('c', $latestTimestamp) : null,
        'changefreq' => 'daily',
        'priority' => 1.0,
    ];

    foreach ($posts as $post) {
        $postTemplate = strtolower($post->getTemplate());
        if ($postTemplate === 'podcast') {
            continue;
        }
        $postVisibility = strtolower(trim((string) ($post->getMetadata()['Visibility'] ?? $post->getMetadata()['visibility'] ?? 'public')));
        if ($postTemplate === 'page' && in_array($postVisibility, ['private', 'privada', '1', 'true', 'yes', 'on'], true)) {
            continue;
        }
        $postTimestamp = $timestampFromPost($post);
        $imageUrl = nammu_resolve_asset($post->getImage(), $publicBaseUrl);
        $entries[] = [
            'loc' => '/' . rawurlencode($post->getSlug()),
            'lastmod' => $postTimestamp !== null ? gmdate('c', $postTimestamp) : null,
            'changefreq' => 'weekly',
            'priority' => 0.8,
            'image' => $imageUrl ?: null,
        ];
    }

    $categories = nammu_collect_categories_from_posts($posts);
    $latestCategoryTimestamp = null;
    foreach ($categories as $slug => $data) {
        $categoryTimestamp = null;
        foreach ($data['posts'] as $categoryPost) {
            if (!$categoryPost instanceof Post) {
                continue;
            }
            $ts = $timestampFromPost($categoryPost);
            if ($ts !== null) {
                $categoryTimestamp = $categoryTimestamp === null ? $ts : max($categoryTimestamp, $ts);
            }
        }
        if ($categoryTimestamp !== null) {
            $latestCategoryTimestamp = $latestCategoryTimestamp === null ? $categoryTimestamp : max($latestCategoryTimestamp, $categoryTimestamp);
        }
        $entries[] = [
            'loc' => '/categoria/' . rawurlencode($slug),
            'lastmod' => $categoryTimestamp !== null ? gmdate('c', $categoryTimestamp) : null,
            'changefreq' => 'weekly',
            'priority' => 0.7,
        ];
    }
    if (!empty($categories)) {
        $entries[] = [
            'loc' => '/categorias',
            'lastmod' => $latestCategoryTimestamp !== null ? gmdate('c', $latestCategoryTimestamp) : ($latestTimestamp !== null ? gmdate('c', $latestTimestamp) : null),
            'changefreq' => 'weekly',
            'priority' => 0.7,
        ];
    }

    $themeHome = is_array($theme['home'] ?? null) ? $theme['home'] : [];
    $perPageSetting = $themeHome['per_page'] ?? 'all';
    $perPage = null;
    if (is_string($perPageSetting)) {
        if (strtolower($perPageSetting) !== 'all') {
            $intCandidate = filter_var($perPageSetting, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($intCandidate !== false) {
                $perPage = (int) $intCandidate;
            }
        }
    } elseif (is_int($perPageSetting)) {
        $perPage = $perPageSetting;
    }
    if ($perPage !== null && $perPage > 0) {
        $totalPages = max(1, (int) ceil(count($posts) / $perPage));
        for ($page = 2; $page <= $totalPages; $page++) {
            $entries[] = [
                'loc' => '/pagina/' . $page,
                'lastmod' => $latestTimestamp !== null ? gmdate('c', $latestTimestamp) : null,
                'changefreq' => 'weekly',
                'priority' => 0.6,
            ];
        }
    }

    if (!empty($itineraryListing)) {
        $latestItineraryTimestamp = null;
        foreach ($itineraryListing as $itineraryItem) {
            if (!$itineraryItem instanceof Itinerary) {
                continue;
            }
            $meta = $itineraryItem->getMetadata();
            $dateValue = $meta['Updated'] ?? ($meta['Date'] ?? '');
            $ts = $dateValue !== '' ? strtotime($dateValue) : null;
            if ($ts !== false && $ts !== null) {
                $latestItineraryTimestamp = $latestItineraryTimestamp === null ? $ts : max($latestItineraryTimestamp, $ts);
            }
            $imageUrl = nammu_resolve_asset($itineraryItem->getImage(), $publicBaseUrl);
            $entries[] = [
                'loc' => $buildItineraryUrl($itineraryItem),
                'lastmod' => $ts !== false && $ts !== null ? gmdate('c', $ts) : null,
                'changefreq' => 'weekly',
                'priority' => 0.7,
                'image' => $imageUrl ?: null,
            ];
            foreach ($itineraryItem->getTopics() as $topicItem) {
                if (!$topicItem instanceof ItineraryTopic) {
                    continue;
                }
                $entries[] = [
                    'loc' => $buildItineraryTopicUrl($itineraryItem, $topicItem),
                    'lastmod' => $ts !== false && $ts !== null ? gmdate('c', $ts) : null,
                    'changefreq' => 'weekly',
                    'priority' => 0.6,
                ];
            }
        }
        $entries[] = [
            'loc' => '/itinerarios',
            'lastmod' => $latestItineraryTimestamp !== null ? gmdate('c', $latestItineraryTimestamp) : null,
            'changefreq' => 'weekly',
            'priority' => 0.7,
        ];
    }

    if ($hasPodcast) {
        $latestPodcastTimestamp = null;
        foreach ($podcastItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $ts = isset($item['timestamp']) ? (int) $item['timestamp'] : null;
            if ($ts !== null && $ts > 0) {
                $latestPodcastTimestamp = $latestPodcastTimestamp === null ? $ts : max($latestPodcastTimestamp, $ts);
            }
            $episodeUrl = trim((string) ($item['page_url'] ?? ''));
            if ($episodeUrl !== '') {
                $entries[] = [
                    'loc' => $episodeUrl,
                    'lastmod' => $ts !== null && $ts > 0 ? gmdate('c', $ts) : null,
                    'changefreq' => 'weekly',
                    'priority' => 0.7,
                    'image' => !empty($item['image']) ? (string) $item['image'] : null,
                ];
            }
        }
        $entries[] = [
            'loc' => '/podcast',
            'lastmod' => $latestPodcastTimestamp !== null ? gmdate('c', $latestPodcastTimestamp) : ($latestTimestamp !== null ? gmdate('c', $latestTimestamp) : null),
            'changefreq' => 'weekly',
            'priority' => 0.7,
        ];
    }

    $analytics = function_exists('nammu_load_analytics') ? nammu_load_analytics() : [];
    $searchesDaily = $analytics['searches']['daily'] ?? [];
    $searchPageLastMod = $analytics['updated_at'] ?? null;
    if (is_int($searchPageLastMod) && $searchPageLastMod > 0) {
        $searchPageLastMod = gmdate('c', $searchPageLastMod);
    } else {
        $searchPageLastMod = $latestTimestamp !== null ? gmdate('c', $latestTimestamp) : null;
    }
    $entries[] = [
        'loc' => '/buscar.php',
        'lastmod' => $searchPageLastMod,
        'changefreq' => 'weekly',
        'priority' => 0.6,
    ];

    $searchTermCounts = [];
    $searchTermLatest = [];
    $today = new DateTimeImmutable('now');
    $startKey = $today->modify('-29 days')->format('Y-m-d');
    foreach ($searchesDaily as $day => $payload) {
        if (!is_string($day) || $day < $startKey || !is_array($payload)) {
            continue;
        }
        foreach ($payload as $term => $termData) {
            $termKey = trim((string) $term);
            if ($termKey === '') {
                continue;
            }
            $count = is_array($termData) ? (int) ($termData['count'] ?? 0) : (int) $termData;
            if ($count <= 0) {
                continue;
            }
            $searchTermCounts[$termKey] = ($searchTermCounts[$termKey] ?? 0) + $count;
            $dayTs = strtotime($day);
            if ($dayTs !== false) {
                $searchTermLatest[$termKey] = isset($searchTermLatest[$termKey])
                    ? max($searchTermLatest[$termKey], $dayTs)
                    : $dayTs;
            }
        }
    }
    if (!empty($searchTermCounts)) {
        $searchList = [];
        foreach ($searchTermCounts as $term => $count) {
            $searchList[] = ['term' => $term, 'count' => $count];
        }
        usort($searchList, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
        $searchList = array_slice($searchList, 0, 10);
        foreach ($searchList as $item) {
            $term = $item['term'];
            $termLast = $searchTermLatest[$term] ?? null;
            $entries[] = [
                'loc' => '/buscar.php?q=' . rawurlencode($term),
                'lastmod' => $termLast !== null ? gmdate('c', $termLast) : $searchPageLastMod,
                'changefreq' => 'weekly',
                'priority' => 0.5,
            ];
        }
    }

    if ($isAlphabeticalOrder) {
        $letterGroups = nammu_group_items_by_letter($posts);
        $entries[] = [
            'loc' => '/letras',
            'lastmod' => $latestTimestamp !== null ? gmdate('c', $latestTimestamp) : null,
            'changefreq' => 'weekly',
            'priority' => 0.6,
        ];
        foreach ($letterGroups as $letter => $groupPosts) {
            $entries[] = [
                'loc' => '/letra/' . rawurlencode(nammu_letter_slug($letter)),
                'lastmod' => $latestTimestamp !== null ? gmdate('c', $latestTimestamp) : null,
                'changefreq' => 'weekly',
                'priority' => 0.5,
            ];
        }
    }

    return $entries;
}

function admin_regenerate_podcast_feed(): void {
    try {
        $baseUrl = nammu_base_url();
        $podcastItems = nammu_collect_podcast_items(NAMMU_ROOT . '/content', $baseUrl);
        if (empty($podcastItems)) {
            @unlink(NAMMU_ROOT . '/podcast.xml');
            return;
        }
        $config = load_config_file();
        $feed = nammu_generate_podcast_feed($baseUrl, $config);
        admin_write_public_artifact(NAMMU_ROOT . '/podcast.xml', $feed);
    } catch (Throwable $e) {
        error_log('No se pudo regenerar podcast.xml: ' . $e->getMessage());
    }
}

function admin_regenerate_rss_feed(): void {
    try {
        $baseUrl = nammu_base_url();
        if ($baseUrl === '') {
            return;
        }
        $config = load_config_file();
        $siteTitle = trim((string) ($config['site_name'] ?? 'Nammu Blog'));
        $siteDescription = trim((string) ($config['social']['default_description'] ?? ''));
        $siteLang = $config['site_lang'] ?? 'es';
        if (!is_string($siteLang) || $siteLang === '') {
            $siteLang = 'es';
        }
        $theme = nammu_template_settings();
        $itineraries = admin_itinerary_repository()->all();
        $podcastItems = nammu_collect_podcast_items(NAMMU_ROOT . '/content', $baseUrl);
        $homeMode = nammu_home_content_mode($theme, !empty($itineraries), !empty($podcastItems));
        if ($homeMode === 'podcast') {
            $rss = nammu_generate_podcast_feed($baseUrl, $config, '/rss.xml');
        } elseif ($homeMode === 'fediverse' && function_exists('nammu_generate_fediverse_threads_feed')) {
            $rss = nammu_generate_fediverse_threads_feed($baseUrl, $config, $siteTitle, $siteDescription, $siteLang, '/rss.xml');
        } elseif ($homeMode === 'itineraries') {
            $rss = nammu_generate_itineraries_rss_feed($baseUrl, $itineraries, $siteTitle, $siteDescription, $siteLang, '/rss.xml', '/itinerarios');
        } else {
            $rss = nammu_generate_blog_rss_feed($baseUrl, $siteTitle, $siteDescription, $siteLang, '/rss.xml', '/');
        }
        admin_write_public_artifact(NAMMU_ROOT . '/rss.xml', $rss);
        admin_write_public_artifact(NAMMU_ROOT . '/blog.xml', nammu_generate_blog_rss_feed($baseUrl, $siteTitle, $siteDescription, $siteLang));
    } catch (Throwable $e) {
        error_log('No se pudo regenerar rss.xml: ' . $e->getMessage());
    }
}

function admin_regenerate_sitemap(): void {
    try {
        $baseUrl = nammu_base_url();
        $repository = new \Nammu\Core\ContentRepository(CONTENT_DIR);
        $posts = $repository->all();
        $config = load_config_file();
        $theme = nammu_template_settings();
        $entries = admin_build_sitemap_entries($posts, $theme, $baseUrl);
        $generator = new SitemapGenerator($baseUrl);
        $sitemapXml = $generator->generate($entries);
        admin_write_public_artifact(NAMMU_ROOT . '/sitemap.xml', $sitemapXml);
    } catch (Throwable $e) {
        error_log('No se pudo regenerar sitemap.xml: ' . $e->getMessage());
    }
}

function admin_public_artifacts_refresh_queue_file(): string
{
    return NAMMU_ROOT . '/config/public-artifacts-refresh.json';
}

function admin_load_public_artifacts_refresh_queue(): array
{
    $file = admin_public_artifacts_refresh_queue_file();
    if (!is_file($file)) {
        return ['pending' => false, 'reasons' => []];
    }
    $decoded = json_decode((string) @file_get_contents($file), true);
    if (!is_array($decoded)) {
        return ['pending' => false, 'reasons' => []];
    }
    $decoded['pending'] = !empty($decoded['pending']);
    $decoded['reasons'] = is_array($decoded['reasons'] ?? null)
        ? array_values(array_filter(array_map('strval', $decoded['reasons'])))
        : [];
    return $decoded;
}

function admin_save_public_artifacts_refresh_queue(array $queue): void
{
    $file = admin_public_artifacts_refresh_queue_file();
    $dir = dirname($file);
    nammu_ensure_directory($dir);
    $payload = [
        'pending' => !empty($queue['pending']),
        'updated_at' => time(),
        'reasons' => is_array($queue['reasons'] ?? null)
            ? array_values(array_unique(array_filter(array_map('strval', $queue['reasons']))))
            : [],
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_string($json)) {
        if (function_exists('nammu_atomic_write_file')) {
            nammu_atomic_write_file($file, $json);
            nammu_apply_shared_permissions($file, 0664, $dir);
        } else {
            @file_put_contents($file, $json, LOCK_EX);
            @chmod($file, 0664);
        }
    }
}

function admin_enqueue_public_artifacts_refresh(string $reason = ''): void
{
    $queue = admin_load_public_artifacts_refresh_queue();
    $reasons = is_array($queue['reasons'] ?? null) ? $queue['reasons'] : [];
    $reason = trim($reason);
    if ($reason !== '') {
        $reasons[] = $reason;
    }
    admin_save_public_artifacts_refresh_queue([
        'pending' => true,
        'reasons' => $reasons,
    ]);
}

function admin_regenerate_public_artifacts_now(): void {
    $lockPath = NAMMU_ROOT . '/config/public-artifacts-refresh.lock';
    $fp = @fopen($lockPath, 'c');
    if ($fp === false) {
        return;
    }
    if (!@flock($fp, LOCK_EX | LOCK_NB)) {
        @fclose($fp);
        return;
    }
    try {
        admin_regenerate_rss_feed();
        admin_regenerate_itinerary_feed();
        admin_regenerate_podcast_feed();
        admin_regenerate_sitemap();
    } finally {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

function admin_regenerate_public_artifacts(string $reason = ''): void {
    if (PHP_SAPI !== 'cli') {
        admin_enqueue_public_artifacts_refresh($reason);
        return;
    }
    admin_regenerate_public_artifacts_now();
}

function admin_process_public_artifacts_refresh_queue(): array
{
    $queue = admin_load_public_artifacts_refresh_queue();
    if (empty($queue['pending'])) {
        return ['processed' => 0, 'remaining' => 0, 'reasons' => 0];
    }
    admin_regenerate_public_artifacts_now();
    $reasons = is_array($queue['reasons'] ?? null) ? count($queue['reasons']) : 0;
    admin_save_public_artifacts_refresh_queue(['pending' => false, 'reasons' => []]);
    return ['processed' => 1, 'remaining' => 0, 'reasons' => $reasons];
}
