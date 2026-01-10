<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';

use Nammu\Core\ContentRepository;
use Nammu\Core\Itinerary;
use Nammu\Core\ItineraryRepository;
use Nammu\Core\ItineraryTopic;
use Nammu\Core\MarkdownConverter;
use Nammu\Core\Post;
use Nammu\Core\RssGenerator;
use Nammu\Core\TemplateRenderer;
use Nammu\Core\SitemapGenerator;

if (function_exists('nammu_publish_scheduled_posts')) {
    nammu_publish_scheduled_posts(__DIR__ . '/content');
}
if (function_exists('nammu_process_scheduled_notifications_queue')) {
    nammu_process_scheduled_notifications_queue();
}

$contentRepository = new ContentRepository(__DIR__ . '/content');
$itinerariesBaseDir = __DIR__ . '/itinerarios';
nammu_ensure_directory($itinerariesBaseDir);
$itineraryRepository = new ItineraryRepository($itinerariesBaseDir);
$markdown = new MarkdownConverter();
$itineraryListing = array_values(array_filter(
    $itineraryRepository->all(),
    static function ($itinerary): bool {
        return $itinerary instanceof \Nammu\Core\Itinerary ? $itinerary->isPublished() : false;
    }
));

$siteDocument = $contentRepository->getDocument('index');
$siteTitle = $siteDocument['metadata']['Title'] ?? 'Nammu Blog';
$siteDescription = $siteDocument['metadata']['Description'] ?? '';
$siteBio = $siteDocument['content'] ?? '';

$config = nammu_load_config();
$configBaseUrl = $config['site_url'] ?? '';
if (is_string($configBaseUrl)) {
    $configBaseUrl = rtrim(trim($configBaseUrl), '/');
}
$baseUrl = $configBaseUrl !== '' ? $configBaseUrl : nammu_base_url();
$publicBaseUrl = $baseUrl;
$homeUrl = $publicBaseUrl !== '' ? $publicBaseUrl : '/';
$rssUrl = ($publicBaseUrl !== '' ? $publicBaseUrl : '') . '/rss.xml';
$podcastIndexUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/podcast';
$podcastItems = nammu_collect_podcast_items(__DIR__ . '/content', $publicBaseUrl);
$hasPodcast = !empty($podcastItems);

$theme = nammu_template_settings();
$footerRaw = $theme['footer'] ?? '';
$theme['footer_html'] = '';
if ($footerRaw !== '') {
    if (str_contains($footerRaw, '<')) {
        $theme['footer_html'] = preg_replace('/>\s+</u', '><', trim($footerRaw));
    } else {
        $theme['footer_html'] = $markdown->toHtml($footerRaw);
    }
}
$theme['logo_url'] = nammu_resolve_asset($theme['images']['logo'] ?? '', $publicBaseUrl);
$theme['favicon_url'] = null;
if (!empty($theme['logo_url'])) {
    $logoPath = parse_url($theme['logo_url'], PHP_URL_PATH) ?? '';
    if ($logoPath && preg_match('/\.(png|ico)$/i', $logoPath)) {
        $theme['favicon_url'] = $theme['logo_url'];
    }
}

$socialConfig = nammu_social_settings();
$siteNameForMeta = $theme['blog'] !== '' ? $theme['blog'] : $siteTitle;
$homeDescription = $socialConfig['default_description'] !== '' ? $socialConfig['default_description'] : $siteDescription;
$homeImage = nammu_resolve_asset($socialConfig['home_image'] ?? '', $publicBaseUrl);

$displaySiteTitle = $theme['blog'] !== '' ? $theme['blog'] : $siteTitle;

$configData = $config;
$siteLang = $configData['site_lang'] ?? 'es';
if (!is_string($siteLang) || $siteLang === '') {
    $siteLang = 'es';
}
$sortOrderValue = strtolower((string) ($configData['pages_order_by'] ?? 'date'));
$sortOrder = in_array($sortOrderValue, ['date', 'alpha'], true) ? $sortOrderValue : 'date';
$isAlphabeticalOrder = ($sortOrder === 'alpha');
$lettersIndexUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/letras';
$postalEnabled = ($configData['postal']['enabled'] ?? 'off') === 'on';
$postalUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/correos.php';
$postalLogoSvg = nammu_postal_icon_svg();
$footerLinks = nammu_build_footer_links($configData, $theme, $homeUrl, $postalUrl, !empty($itineraryListing), $hasPodcast);
$logoForJsonLd = $theme['logo_url'] ?? '';
$orgJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $siteNameForMeta,
    'url' => $publicBaseUrl !== '' ? $publicBaseUrl : $homeUrl,
];
if (!empty($logoForJsonLd)) {
    $orgJsonLd['logo'] = $logoForJsonLd;
}
$siteJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => $siteNameForMeta,
    'url' => $publicBaseUrl !== '' ? $publicBaseUrl : $homeUrl,
    'description' => $homeDescription,
    'inLanguage' => $siteLang,
];
if ($publicBaseUrl !== '') {
    $siteJsonLd['potentialAction'] = [
        '@type' => 'SearchAction',
        'target' => rtrim($publicBaseUrl, '/') . '/buscar.php?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ];
}

$renderer = new TemplateRenderer(__DIR__ . '/template', [
    'siteTitle' => $displaySiteTitle,
    'siteDescription' => $siteDescription,
    'rssUrl' => $rssUrl !== '' ? $rssUrl : '/rss.xml',
    'baseUrl' => $homeUrl,
    'theme' => $theme,
]);
$allPostsForCategories = $contentRepository->all();
$categoryMapAll = nammu_collect_categories_from_posts($allPostsForCategories);
$uncategorizedSlug = nammu_slugify_label('Sin Categoría');
$hasCategories = false;
foreach ($categoryMapAll as $slugKey => $data) {
    if ($slugKey !== $uncategorizedSlug) {
        $hasCategories = true;
        break;
    }
}
$renderer->setGlobal('lettersIndexUrl', $isAlphabeticalOrder ? $lettersIndexUrl : null);
$renderer->setGlobal('showLetterIndexButton', $isAlphabeticalOrder);
$renderer->setGlobal('postalEnabled', $postalEnabled);
$renderer->setGlobal('postalUrl', $postalUrl);
$renderer->setGlobal('postalLogoSvg', $postalLogoSvg);
$renderer->setGlobal('footerLinks', $footerLinks);
$renderer->setGlobal('hasCategories', $hasCategories);
$renderer->setGlobal('hasPodcast', $hasPodcast);
$renderer->setGlobal('podcastIndexUrl', $podcastIndexUrl);
$renderer->setGlobal('pageLang', $siteLang);

$renderer->setGlobal('resolveImage', function (?string $image) use ($publicBaseUrl): ?string {
    if ($image === null || $image === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }

    $normalized = ltrim($image, '/');
    if (str_starts_with($normalized, 'assets/')) {
        $normalized = substr($normalized, 7);
    }
    $path = 'assets/' . ltrim($normalized, '/');
    return $publicBaseUrl !== '' ? $publicBaseUrl . '/' . $path : '/' . $path;
});

$buildItineraryUrl = static function (Itinerary|string $itinerary) use ($publicBaseUrl): string {
    $slug = $itinerary instanceof Itinerary ? $itinerary->getSlug() : (string) $itinerary;
    $path = '/itinerarios/' . rawurlencode($slug);
    return $publicBaseUrl !== '' ? $publicBaseUrl . $path : $path;
};
$buildItineraryTopicUrl = static function (Itinerary|string $itinerary, ItineraryTopic|string $topic) use ($publicBaseUrl): string {
    $itinerarySlug = $itinerary instanceof Itinerary ? $itinerary->getSlug() : (string) $itinerary;
    $topicSlug = $topic instanceof ItineraryTopic ? $topic->getSlug() : (string) $topic;
    $path = '/itinerarios/' . rawurlencode($itinerarySlug) . '/' . rawurlencode($topicSlug);
    return $publicBaseUrl !== '' ? $publicBaseUrl . $path : $path;
};

$renderer->setGlobal('postUrl', function (Post|string $post) use ($publicBaseUrl): string {
    $slug = $post instanceof Post ? $post->getSlug() : (string) $post;
    $path = '/' . rawurlencode($slug);
    return $publicBaseUrl !== '' ? $publicBaseUrl . $path : $path;
});
$renderer->setGlobal('paginationUrl', function (int $page) use ($publicBaseUrl): string {
    $target = $page <= 1 ? '/' : '/pagina/' . $page;
    return $publicBaseUrl !== '' ? $publicBaseUrl . $target : $target;
});
$renderer->setGlobal('itineraryUrl', $buildItineraryUrl);
$renderer->setGlobal('itineraryTopicUrl', $buildItineraryTopicUrl);
$renderer->setGlobal('itinerariesIndexUrl', $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') . '/itinerarios' : '/itinerarios');
$renderer->setGlobal('hasItineraries', !empty($itineraryListing));

$renderer->setGlobal('markdownToHtml', function (string $markdownText) use ($markdown): string {
    return $markdown->toHtml($markdownText);
});
$renderer->setGlobal('baseUrl', $publicBaseUrl !== '' ? $publicBaseUrl : '/');
$renderer->setGlobal('socialConfig', $socialConfig);
$renderer->setGlobal('isAlphabeticalOrder', $isAlphabeticalOrder);

$routePath = nammu_route_path();
$alphabeticalSorter = static function (Post $a, Post $b): int {
    $letterA = nammu_letter_key_from_title($a->getTitle());
    $letterB = nammu_letter_key_from_title($b->getTitle());
    $weightA = nammu_letter_sort_weight($letterA);
    $weightB = nammu_letter_sort_weight($letterB);
    if ($weightA !== $weightB) {
        return $weightA <=> $weightB;
    }
    $cmp = strcasecmp($a->getTitle(), $b->getTitle());
    if ($cmp !== 0) {
        return $cmp;
    }
    return strcmp($a->getSlug(), $b->getSlug());
};
$postToViewArray = static function (Post $post): array {
    $date = $post->getDate();
    $rawDate = $post->getRawDate();
    return [
        'slug' => $post->getSlug(),
        'title' => $post->getTitle(),
        'description' => $post->getDescription(),
        'date' => nammu_format_date_spanish($date, $rawDate ?? ''),
        'category' => $post->getCategory(),
        'image' => $post->getImage(),
    ];
};
$isLettersIndex = (bool) preg_match('#^/letras/?$#i', $routePath);
$letterSlugRequest = null;
if (!$isLettersIndex && preg_match('#^/letra/([^/]+)/?$#i', $routePath, $matchLetter)) {
    $letterSlugRequest = strtolower($matchLetter[1]);
}
$buildSitemapEntries = static function (array $posts, array $theme, string $publicBaseUrl) use ($itineraryListing, $buildItineraryUrl, $buildItineraryTopicUrl, $isAlphabeticalOrder): array {
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

    $homeSettings = $theme['home'] ?? [];
    $perPageSetting = $homeSettings['per_page'] ?? 'all';
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
            if (!$itineraryItem instanceof \Nammu\Core\Itinerary) {
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
                if (!$topicItem instanceof \Nammu\Core\ItineraryTopic) {
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
};
$renderNotFound = static function (string $title, string $description, string $path) use (
    $renderer,
    $siteTitle,
    $homeDescription,
    $socialConfig,
    $publicBaseUrl,
    $homeImage,
    $siteNameForMeta,
    $siteJsonLd,
    $orgJsonLd,
    $siteLang
): void {
    http_response_code(404);
    $content = $renderer->render('404', [
        'pageTitle' => $title,
    ]);
    $social = nammu_build_social_meta([
        'type' => 'website',
        'title' => $title . ' — ' . $siteTitle,
        'description' => $description !== '' ? $description : $homeDescription,
        'url' => ($publicBaseUrl !== '' ? $publicBaseUrl : '') . $path,
        'image' => $homeImage,
        'site_name' => $siteNameForMeta,
    ], $socialConfig);
    echo $renderer->render('layout', [
        'pageTitle' => $title,
        'metaDescription' => $description !== '' ? $description : 'La página solicitada no se encuentra disponible',
        'content' => $content,
        'socialMeta' => $social,
        'jsonLd' => [$siteJsonLd, $orgJsonLd],
        'pageLang' => $siteLang,
        'showLogo' => false,
    ]);
    exit;
};
$isCategoriesIndex = (bool) preg_match('#^/categorias/?$#i', $routePath);
$categorySlugRequest = null;
if (!$isCategoriesIndex && preg_match('#^/categoria/([^/]+)/?$#i', $routePath, $matchCategory)) {
    $categorySlugRequest = strtolower($matchCategory[1]);
} elseif (!$isCategoriesIndex && isset($_GET['categoria'])) {
    $candidate = trim((string) $_GET['categoria']);
    if ($candidate !== '') {
        $categorySlugRequest = strtolower($candidate);
    }
}
$currentPage = 1;
$isPaginationRoute = false;
if ($routePath !== '/' && $routePath !== '/index.php') {
    if (preg_match('#^/(?:pagina|page)/([1-9][0-9]*)$#i', $routePath, $matches)) {
        $currentPage = (int) $matches[1];
        if ($currentPage < 1) {
            $currentPage = 1;
        }
        $isPaginationRoute = true;
    }
}

if ($routePath === '/rss.xml') {
    $posts = $contentRepository->all();
    $rssGenerator = new RssGenerator($publicBaseUrl, $siteTitle, $siteDescription, $homeUrl, $rssUrl, $siteLang);
    $rss = $rssGenerator->generate(
        $posts,
        static fn (Post $post): string => '/' . rawurlencode($post->getSlug()),
        $markdown
    );

    header('Content-Type: application/rss+xml; charset=UTF-8');
    echo $rss;

    // Store a fresh copy for direct access as static file
    if ($publicBaseUrl !== '') {
        @file_put_contents(__DIR__ . '/rss.xml', $rss);
    }
    exit;
}

if ($routePath === '/podcast.xml') {
    $podcastFeed = nammu_generate_podcast_feed($publicBaseUrl, $config);
    header('Content-Type: application/rss+xml; charset=UTF-8');
    echo $podcastFeed;
    if ($publicBaseUrl !== '') {
        @file_put_contents(__DIR__ . '/podcast.xml', $podcastFeed);
    }
    exit;
}

if ($routePath === '/sitemap.xml') {
    $posts = $contentRepository->all();
    $sitemapGenerator = new SitemapGenerator($publicBaseUrl);
    $entries = $buildSitemapEntries($posts, $theme, $publicBaseUrl);
    $sitemapXml = $sitemapGenerator->generate($entries);
    header('Content-Type: application/xml; charset=UTF-8');
    echo $sitemapXml;
    @file_put_contents(__DIR__ . '/sitemap.xml', $sitemapXml);
    exit;
}

if ($routePath === '/itinerarios.xml') {
    $itinerariesIndexUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/itinerarios';
    $itinerariesFeedUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/itinerarios.xml';
    $itineraryFeedPosts = [];
    $itineraryPostUrls = [];
    foreach ($itineraryListing as $itineraryItem) {
        if (method_exists($itineraryItem, 'isDraft') && $itineraryItem->isDraft()) {
            continue;
        }
        $itineraryMetadata = $itineraryItem->getMetadata();
        $itineraryDescription = $itineraryItem->getDescription();
        if ($itineraryDescription === '') {
            $convertedDocument = $markdown->convertDocument($itineraryItem->getContent());
            $itineraryDescription = nammu_excerpt_text($convertedDocument['html'], 220);
        }
        $dateString = $itineraryMetadata['Date'] ?? ($itineraryMetadata['Updated'] ?? '');
        if (trim((string) $dateString) === '') {
            $indexPath = $itineraryItem->getDirectory() . '/index.md';
            $mtime = is_file($indexPath) ? @filemtime($indexPath) : false;
            if ($mtime !== false) {
                $dateString = gmdate('Y-m-d', $mtime);
            } else {
                $dateString = gmdate('Y-m-d');
            }
        }
        $virtualMeta = [
            'Title' => $itineraryItem->getTitle(),
            'Description' => $itineraryDescription,
            'Image' => $itineraryItem->getImage() ?? '',
            'Date' => $dateString,
        ];
        $virtualSlug = 'itinerary-feed-' . $itineraryItem->getSlug();
        $status = method_exists($itineraryItem, 'isDraft') && $itineraryItem->isDraft() ? 'draft' : 'published';
        $virtualPost = new Post($virtualSlug, $virtualMeta, $itineraryItem->getContent(), $status);
        $itineraryFeedPosts[] = $virtualPost;
        $itineraryPostUrls[$virtualSlug] = $buildItineraryUrl($itineraryItem);
    }
    usort($itineraryFeedPosts, static function (Post $a, Post $b): int {
        $dateA = $a->getDate();
        $dateB = $b->getDate();
        if ($dateA && $dateB) {
            return $dateB <=> $dateA;
        }
        if ($dateA) {
            return -1;
        }
        if ($dateB) {
            return 1;
        }
        return strcmp($a->getSlug(), $b->getSlug());
    });
    $itineraryFeedContent = (new RssGenerator(
        $publicBaseUrl,
        $siteTitle . ' — Itinerarios',
        'Itinerarios recientes',
        $itinerariesIndexUrl,
        $itinerariesFeedUrl,
        $siteLang
    ))->generate(
        $itineraryFeedPosts,
        static function (Post $post) use ($itineraryPostUrls): string {
            return $itineraryPostUrls[$post->getSlug()] ?? '/';
        },
        $markdown,
        false
    );
    header('Content-Type: application/rss+xml; charset=UTF-8');
    echo $itineraryFeedContent;
    @file_put_contents(__DIR__ . '/itinerarios.xml', $itineraryFeedContent);
    exit;
}

if (preg_match('#^/podcast/?$#i', $routePath)) {
    $rawEpisodes = nammu_collect_podcast_items(__DIR__ . '/content', $publicBaseUrl);
    $episodes = [];
    foreach ($rawEpisodes as $episode) {
        $timestamp = (int) ($episode['timestamp'] ?? 0);
        $episodes[] = [
            'title' => (string) ($episode['title'] ?? ''),
            'description' => (string) ($episode['description'] ?? ''),
            'date' => $timestamp > 0 ? date('d/m/y', $timestamp) : '',
            'image' => $episode['image'] ?? null,
            'audio' => (string) ($episode['audio'] ?? ''),
            'duration' => (string) ($episode['audio_duration'] ?? ''),
            'timestamp' => $timestamp,
        ];
    }
    usort($episodes, static function (array $a, array $b): int {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });
    $count = count($episodes);
    $content = $renderer->render('podcast', [
        'episodes' => $episodes,
        'count' => $count,
        'hasItineraries' => !empty($itineraryListing),
    ]);
    $canon = $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') . '/podcast' : '/podcast';
    $description = 'Episodios de nuestro podcast.';
    if (function_exists('nammu_record_pageview')) {
        nammu_record_pageview('pages', 'podcast', 'Podcast');
    }
    $podcastMeta = nammu_build_social_meta([
        'type' => 'website',
        'title' => 'Podcast — ' . $siteNameForMeta,
        'description' => $description,
        'url' => $canon,
        'image' => $homeImage,
        'site_name' => $siteNameForMeta,
    ], $socialConfig);
    echo $renderer->render('layout', [
        'pageTitle' => 'Podcast',
        'metaDescription' => $description,
        'content' => $content,
        'socialMeta' => $podcastMeta,
        'jsonLd' => [$siteJsonLd, $orgJsonLd],
        'pageLang' => $siteLang,
        'showLogo' => true,
    ]);
    exit;
}

if ($isLettersIndex) {
    if (!$isAlphabeticalOrder) {
        $renderNotFound('Índice alfabético no disponible', 'Activa la ordenación alfabética para acceder a esta vista.', $routePath);
    }
    $allPosts = $contentRepository->all();
    if ($isAlphabeticalOrder) {
        usort($allPosts, $alphabeticalSorter);
    }
    $letterGroups = nammu_group_items_by_letter($allPosts);
    $lettersData = [];
    foreach ($letterGroups as $letter => $groupPosts) {
        $lettersData[] = [
            'letter' => $letter,
            'display' => nammu_letter_display_name($letter),
            'slug' => nammu_letter_slug($letter),
            'count' => count($groupPosts),
            'url' => ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/letra/' . rawurlencode(nammu_letter_slug($letter)),
        ];
    }

    $content = $renderer->render('letter-index', [
        'letters' => $lettersData,
        'total' => count($lettersData),
    ]);

    $canonical = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/letras';
    if (function_exists('nammu_record_pageview')) {
        nammu_record_pageview('pages', 'letras', 'Índice alfabético');
    }
    $socialMeta = nammu_build_social_meta([
        'type' => 'website',
        'title' => 'Índice alfabético — ' . $siteNameForMeta,
        'description' => 'Explora todas las entradas agrupadas por la letra inicial de su título.',
        'url' => $canonical,
        'image' => $homeImage,
        'site_name' => $siteNameForMeta,
    ], $socialConfig);

    echo $renderer->render('layout', [
        'pageTitle' => 'Índice alfabético',
        'metaDescription' => 'Listado completo de letras iniciales disponibles en el sitio.',
        'content' => $content,
        'socialMeta' => $socialMeta,
        'jsonLd' => [$siteJsonLd, $orgJsonLd],
        'pageLang' => $siteLang,
        'showLogo' => true,
    ]);
    exit;
}

if ($letterSlugRequest !== null) {
    if (!$isAlphabeticalOrder) {
        $renderNotFound('Letra no disponible', 'Activa la ordenación alfabética para acceder a esta vista.', $routePath);
    }
    $targetLetter = nammu_letter_from_slug($letterSlugRequest);
    $allPosts = $contentRepository->all();
    if ($isAlphabeticalOrder) {
        usort($allPosts, $alphabeticalSorter);
    }
    $letterGroups = nammu_group_items_by_letter($allPosts);
    if (!isset($letterGroups[$targetLetter])) {
        $renderNotFound('Letra no encontrada', 'No hay publicaciones que comiencen con esta letra.', $routePath);
    }
    $postsForLetter = array_map($postToViewArray, $letterGroups[$targetLetter]);
    $letterDisplay = nammu_letter_display_name($targetLetter);

    $content = $renderer->render('letter', [
        'letter' => $targetLetter,
        'letterDisplay' => $letterDisplay,
        'posts' => $postsForLetter,
        'count' => count($postsForLetter),
        'hideMetaBand' => true,
    ]);

    $canonical = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/letra/' . rawurlencode(nammu_letter_slug($targetLetter));
    if (function_exists('nammu_record_pageview')) {
        nammu_record_pageview('pages', 'letra/' . nammu_letter_slug($targetLetter), 'Letra: ' . $letterDisplay);
    }
    $socialMeta = nammu_build_social_meta([
        'type' => 'website',
        'title' => 'Entradas que empiezan por ' . $letterDisplay . ' — ' . $siteNameForMeta,
        'description' => 'Artículos cuyo título comienza por la letra ' . $letterDisplay . '.',
        'url' => $canonical,
        'image' => $homeImage,
        'site_name' => $siteNameForMeta,
    ], $socialConfig);

    echo $renderer->render('layout', [
        'pageTitle' => 'Letra: ' . $letterDisplay,
        'metaDescription' => 'Listado de publicaciones que comienzan por la letra ' . $letterDisplay . '.',
        'content' => $content,
        'socialMeta' => $socialMeta,
        'jsonLd' => [$siteJsonLd, $orgJsonLd],
        'pageLang' => $siteLang,
        'showLogo' => true,
    ]);
    exit;
}

$slug = null;

if ($isCategoriesIndex) {
    $categoryMap = $categoryMapAll;
    $categoriesList = [];
    foreach ($categoryMap as $slugKey => $data) {
        if ($slugKey === $uncategorizedSlug) {
            continue;
        }
        $categoriesList[] = [
            'slug' => $slugKey,
            'name' => $data['name'],
            'count' => $data['count'],
            'url' => ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/categoria/' . rawurlencode($slugKey),
            'image' => $data['latest_image'] ?? null,
        ];
    }
    usort($categoriesList, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));

    $content = $renderer->render('category-index', [
        'categories' => $categoriesList,
        'total' => count($categoriesList),
    ]);

    $canonical = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/categorias';
    if (function_exists('nammu_record_pageview')) {
        nammu_record_pageview('pages', 'categorias', 'Categorías');
    }
    $socialMeta = nammu_build_social_meta([
        'type' => 'website',
        'title' => 'Categorías — ' . $siteNameForMeta,
        'description' => 'Explora todas las categorías publicadas en ' . $siteNameForMeta,
        'url' => $canonical,
        'image' => $homeImage,
        'site_name' => $siteNameForMeta,
    ], $socialConfig);

    echo $renderer->render('layout', [
        'pageTitle' => 'Categorías',
        'metaDescription' => 'Listado completo de categorías disponibles en el sitio.',
        'content' => $content,
        'socialMeta' => $socialMeta,
        'jsonLd' => [$siteJsonLd, $orgJsonLd],
        'pageLang' => $siteLang,
        'showLogo' => true,
    ]);
    exit;
}

if ($categorySlugRequest !== null) {
    $categoryMap = $categoryMapAll;
    $slugKey = strtolower($categorySlugRequest);
    if (!isset($categoryMap[$slugKey])) {
        $renderNotFound('Categoría no encontrada', 'No existe ninguna publicación asociada a esta categoría.', $routePath);
    }
    $categoryData = $categoryMap[$slugKey];
    $categoryPosts = $categoryData['posts'];
    usort($categoryPosts, static function (Post $a, Post $b): int {
        $dateA = $a->getDate();
        $dateB = $b->getDate();
        if ($dateA && $dateB) {
            return $dateA < $dateB ? 1 : -1;
        }
        if ($dateA) {
            return -1;
        }
        if ($dateB) {
            return 1;
        }
        return strcmp($a->getSlug(), $b->getSlug());
    });
    $postsForCategory = array_map(
        static function (Post $post): array {
            $date = $post->getDate();
            $rawDate = $post->getRawDate();
            return [
                'slug' => $post->getSlug(),
                'title' => $post->getTitle(),
                'description' => $post->getDescription(),
                'date' => nammu_format_date_spanish($date, $rawDate ?? ''),
                'category' => $post->getCategory(),
                'image' => $post->getImage(),
            ];
        },
        $categoryPosts
    );

    $categoryTitle = $categoryData['name'];
    $countPosts = count($categoryPosts);
    $content = $renderer->render('category', [
        'category' => $categoryTitle,
        'count' => $countPosts,
        'posts' => $postsForCategory,
        'hideMetaBand' => $isAlphabeticalOrder,
    ]);

    $canonical = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/categoria/' . rawurlencode($slugKey);
    if (function_exists('nammu_record_pageview')) {
        nammu_record_pageview('pages', 'categoria/' . $slugKey, 'Categoría: ' . $categoryTitle);
    }
    $socialMeta = nammu_build_social_meta([
        'type' => 'website',
        'title' => 'Categoría: ' . $categoryTitle . ' — ' . $siteNameForMeta,
        'description' => 'Artículos publicados dentro de la categoría ' . $categoryTitle,
        'url' => $canonical,
        'image' => $homeImage,
        'site_name' => $siteNameForMeta,
    ], $socialConfig);

    echo $renderer->render('layout', [
        'pageTitle' => 'Categoría: ' . $categoryTitle,
        'metaDescription' => 'Listado de artículos publicados en la categoría ' . $categoryTitle,
        'content' => $content,
        'socialMeta' => $socialMeta,
        'jsonLd' => [$siteJsonLd, $orgJsonLd],
        'pageLang' => $siteLang,
        'showLogo' => true,
    ]);
    exit;
}

if (preg_match('#^/itinerarios/([^/]+)/([^/]+)/?$#i', $routePath, $matchItineraryTopic)) {
    $itinerarySlug = ItineraryRepository::normalizeSlug(rawurldecode($matchItineraryTopic[1]));
    $topicSlug = ItineraryRepository::normalizeSlug(rawurldecode($matchItineraryTopic[2]));
    $previewMode = isset($_GET['preview']) && $_GET['preview'] === '1';
    $itinerary = $itineraryRepository->find($itinerarySlug);
    if ($itinerary === null) {
        $renderNotFound('Itinerario no encontrado', 'El itinerario solicitado no se encuentra disponible.', $routePath);
    }
    $itineraryStatus = method_exists($itinerary, 'isDraft') && $itinerary->isDraft() ? 'draft' : 'published';
    $topic = $itineraryRepository->findTopic($itinerarySlug, $topicSlug);
    if ($topic === null) {
        $renderNotFound('Tema no encontrado', 'Este tema no se encuentra disponible dentro del itinerario.', $routePath);
    }
    $usageLogic = $itinerary->getUsageLogic();
    if ($previewMode) {
        $progressData = ['visited' => [], 'passed' => []];
        $usageLogic = Itinerary::USAGE_LOGIC_FREE;
    } else {
        $progressData = nammu_get_itinerary_progress($itinerary->getSlug());
        $hadPresentation = in_array('__presentation', $progressData['visited'], true);
        $alreadyVisited = in_array($topic->getSlug(), $progressData['visited'], true);
        if (!$alreadyVisited) {
            $progressData['visited'][] = $topic->getSlug();
            nammu_set_itinerary_progress($itinerary->getSlug(), $progressData);
            if ($hadPresentation) {
                $incrementStart = $topic->getNumber() === 1;
                try {
                    $itineraryRepository->recordTopicStat($itinerary->getSlug(), $topic, $incrementStart);
                } catch (Throwable $e) {
                    // Ignore write failures to keep navigation smooth
                }
            }
        }
        if ($hadPresentation) {
            $allVisited = true;
            foreach ($itinerary->getTopics() as $topicItem) {
                if (!in_array($topicItem->getSlug(), $progressData['visited'], true)) {
                    $allVisited = false;
                    break;
                }
            }
            if ($allVisited) {
                nammu_record_itinerary_event($itinerary->getSlug(), 'complete');
            }
        }
    }
    $documentData = $markdown->convertDocument($topic->getContent());
    $topicHtml = $documentData['html'];
    $topicsList = $itinerary->getTopics();
    $nextTopic = null;
    $previousTopic = null;
    foreach ($topicsList as $index => $candidate) {
        if ($candidate->getSlug() === $topic->getSlug()) {
            $previousTopic = $topicsList[$index - 1] ?? null;
            $nextTopic = $topicsList[$index + 1] ?? null;
            break;
        }
    }
    $nextStep = null;
    if ($nextTopic !== null) {
        $nextStep = [
            'url' => $buildItineraryTopicUrl($itinerary, $nextTopic),
            'label' => 'Pasar al siguiente tema',
        ];
    }
    $previousStep = null;
    if ($previousTopic !== null) {
        $previousStep = [
            'url' => $buildItineraryTopicUrl($itinerary, $previousTopic),
            'label' => 'Volver al tema anterior',
        ];
    }
    $topicImage = nammu_resolve_asset($topic->getImage(), $publicBaseUrl);
    if ($topicImage === null) {
        $topicImage = nammu_resolve_asset($itinerary->getImage(), $publicBaseUrl);
    }
    if ($topicImage === null) {
        $topicImage = $homeImage;
    }
    $topicDescription = $topic->getDescription();
    if ($topicDescription === '') {
        $topicDescription = nammu_excerpt_text($topicHtml, 200);
    }
    $topicCanonical = $buildItineraryTopicUrl($itinerary, $topic);
    if (function_exists('nammu_record_pageview')) {
        nammu_record_pageview('pages', 'itinerarios/' . $itinerarySlug . '/' . $topicSlug, $topic->getTitle());
    }
    $entryTocConfig = $theme['entry']['toc'] ?? ['auto' => 'off', 'min_headings' => 3];
    $autoTocEnabled = ($entryTocConfig['auto'] ?? 'off') === 'on';
    $entryMinHeadings = (int) ($entryTocConfig['min_headings'] ?? 3);
    if (!in_array($entryMinHeadings, [2, 3, 4], true)) {
        $entryMinHeadings = 3;
    }
    $renderableHeadings = array_filter($documentData['headings'], static function (array $heading): bool {
        return isset($heading['id'], $heading['text'], $heading['level'])
            && $heading['id'] !== ''
            && $heading['text'] !== ''
            && (int) $heading['level'] >= 1
            && (int) $heading['level'] <= 4;
    });
    $autoTocHtml = '';
    if ($autoTocEnabled && !$documentData['has_manual_toc'] && count($renderableHeadings) >= $entryMinHeadings) {
        $generatedToc = $markdown->buildToc($documentData['headings']);
        if ($generatedToc !== '') {
            $autoTocHtml = $generatedToc;
        }
    }
    $topicSocialMeta = nammu_build_social_meta([
        'type' => 'article',
        'title' => $topic->getTitle() . ' — ' . $itinerary->getTitle(),
        'description' => $topicDescription,
        'url' => $topicCanonical,
        'image' => $topicImage,
        'site_name' => $siteNameForMeta,
    ], $socialConfig);
    $topicMetadata = $topic->getMetadata();
    $itineraryMetadata = method_exists($itinerary, 'getMetadata') ? $itinerary->getMetadata() : [];
    $virtualPostMetadata = [
        'Title' => $topic->getTitle(),
        'Description' => $topicDescription,
        'Image' => $topicMetadata['Image'] ?? ($itinerary->getImage() ?? ''),
        'Template' => 'post',
    ];
    if (!empty($topicMetadata['Date'])) {
        $virtualPostMetadata['Date'] = $topicMetadata['Date'];
    } elseif (!empty($itineraryMetadata['Date'])) {
        $virtualPostMetadata['Date'] = $itineraryMetadata['Date'];
    }
    $topicStatus = method_exists($itinerary, 'isDraft') && $itinerary->isDraft() ? 'draft' : 'published';
    $virtualPost = new Post(
        'itinerario-' . $itinerary->getSlug() . '-' . $topic->getSlug(),
        $virtualPostMetadata,
        $topic->getContent(),
        $topicStatus
    );
    $virtualPostMetadata = [
        'Title' => $topic->getTitle(),
        'Description' => $topicDescription,
        'Image' => $topicMetadata['Image'] ?? ($itinerary->getImage() ?? ''),
        'Template' => 'post',
        'Category' => 'Tema ' . $topic->getNumber() . ' del itinerario «' . $itinerary->getTitle() . '»',
    ];
    $topicMainContent = $renderer->render('single', [
        'pageTitle' => $topic->getTitle(),
        'post' => $virtualPost,
        'htmlContent' => $topicHtml,
        'postFilePath' => $topic->getFilePath(),
        'autoTocHtml' => $autoTocHtml,
        'hideCategory' => true,
        'customMetaBand' => 'Tema ' . $topic->getNumber() . ' del itinerario «' . $itinerary->getTitle() . '»',
        'suppressSingleSearchTop' => true,
        'suppressSingleSearchBottom' => true,
    ]);
    $topicMainContent = preg_replace(
        '#<(?:div|section)\s+class="site-search-block placement-bottom"[^>]*>.*?</(?:div|section)>#si',
        '',
        $topicMainContent
    ) ?? $topicMainContent;
    $topicMainContent = '<div class="itinerary-single-content">' . $topicMainContent . '</div>';
    $topicExtras = $renderer->render('itinerary-topic', [
        'itinerary' => $itinerary,
        'topic' => $topic,
        'quiz' => $previewMode ? [] : $topic->getQuiz(),
        'usageLogic' => $usageLogic,
        'progress' => $progressData,
        'nextStep' => $nextStep,
        'previousStep' => $previousStep,
    ]);
    $content = $topicMainContent . $topicExtras;
    echo $renderer->render('layout', [
        'pageTitle' => $topic->getTitle() . ' — ' . $itinerary->getTitle(),
        'metaDescription' => $topicDescription,
        'content' => $content,
        'socialMeta' => $topicSocialMeta,
        'jsonLd' => [$siteJsonLd, $orgJsonLd],
        'pageLang' => $siteLang,
        'showLogo' => true,
    ]);
    exit;
}

if (preg_match('#^/itinerarios/([^/]+)/?$#i', $routePath, $matchItinerary)) {
    $itinerarySlug = ItineraryRepository::normalizeSlug(rawurldecode($matchItinerary[1]));
    $itinerary = $itineraryRepository->find($itinerarySlug);
    if ($itinerary === null) {
        $renderNotFound('Itinerario no encontrado', 'El itinerario solicitado no se encuentra disponible.', $routePath);
    }
    $itineraryStatus = method_exists($itinerary, 'isDraft') && $itinerary->isDraft() ? 'draft' : 'published';
    $itineraryProgress = nammu_get_itinerary_progress($itinerary->getSlug());
    $hadPresentation = in_array('__presentation', $itineraryProgress['visited'], true);
    if (!$hadPresentation) {
        $itineraryProgress['visited'][] = '__presentation';
        nammu_set_itinerary_progress($itinerary->getSlug(), $itineraryProgress);
        nammu_record_itinerary_event($itinerary->getSlug(), 'start');
    }
    $documentData = $markdown->convertDocument($itinerary->getContent());
    $itineraryHtml = $documentData['html'];
    $topics = $itinerary->getTopics();
    $firstTopic = $itinerary->getFirstTopic();
    $firstTopicUrl = $firstTopic ? $buildItineraryTopicUrl($itinerary, $firstTopic) : null;
    $topicSummaries = array_map(function (ItineraryTopic $topic) use ($itinerary, $buildItineraryTopicUrl): array {
        return [
            'slug' => $topic->getSlug(),
            'title' => $topic->getTitle(),
            'description' => $topic->getDescription(),
            'number' => $topic->getNumber(),
            'url' => $buildItineraryTopicUrl($itinerary, $topic),
            'image' => $topic->getImage(),
            'meta' => 'Tema ' . $topic->getNumber() . ' del itinerario «' . $itinerary->getTitle() . '»',
        ];
    }, $topics);
    $itineraryImage = nammu_resolve_asset($itinerary->getImage(), $publicBaseUrl) ?? $homeImage;
    $itineraryDescription = $itinerary->getDescription() !== '' ? $itinerary->getDescription() : nammu_excerpt_text($itineraryHtml, 220);
    $canonical = $buildItineraryUrl($itinerary);
    if (function_exists('nammu_record_pageview')) {
        nammu_record_pageview('pages', 'itinerarios/' . $itinerarySlug, $itinerary->getTitle());
    }
    $itinerariesIndexUrlLocal = $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') . '/itinerarios' : '/itinerarios';
    $itinerarySocialMeta = nammu_build_social_meta([
        'type' => 'website',
        'title' => $itinerary->getTitle(),
        'description' => $itineraryDescription,
        'url' => $canonical,
        'image' => $itineraryImage,
        'site_name' => $siteNameForMeta,
    ], $socialConfig);
    $entryTocConfig = $theme['entry']['toc'] ?? ['auto' => 'off', 'min_headings' => 3];
    $autoTocEnabled = ($entryTocConfig['auto'] ?? 'off') === 'on';
    $entryMinHeadings = (int) ($entryTocConfig['min_headings'] ?? 3);
    if (!in_array($entryMinHeadings, [2, 3, 4], true)) {
        $entryMinHeadings = 3;
    }
    $virtualItineraryPost = new Post(
        'itinerario-' . $itinerary->getSlug(),
        [
            'Title' => $itinerary->getTitle(),
            'Description' => $itineraryDescription,
            'Image' => $itinerary->getImage() ?? '',
            'Template' => 'post',
        ],
        $itinerary->getContent(),
        $itineraryStatus
    );
    $autoTocHtml = '';
    $renderableHeadings = array_filter($documentData['headings'], static function (array $heading): bool {
        return isset($heading['id'], $heading['text'], $heading['level'])
            && $heading['id'] !== ''
            && $heading['text'] !== ''
            && (int) $heading['level'] >= 1
            && (int) $heading['level'] <= 4;
    });
    if ($autoTocEnabled && !$documentData['has_manual_toc'] && count($renderableHeadings) >= $entryMinHeadings) {
        $generatedToc = $markdown->buildToc($documentData['headings']);
        if ($generatedToc !== '') {
            $autoTocHtml = $generatedToc;
        }
    }
    $itineraryBody = $renderer->render('single', [
        'pageTitle' => $itinerary->getTitle(),
        'post' => $virtualItineraryPost,
        'htmlContent' => $itineraryHtml,
        'postFilePath' => $itinerary->getDirectory() . '/index.md',
        'autoTocHtml' => $autoTocHtml,
        'hideCategory' => true,
        'customMetaBand' => $itinerary->getClassLabel(),
        'suppressSingleSearchTop' => true,
        'suppressSingleSearchBottom' => true,
    ]);
    $itineraryBody = preg_replace(
        '#<(?:div|section)\s+class="site-search-block placement-bottom"[^>]*>.*?</(?:div|section)>#si',
        '',
        $itineraryBody
    ) ?? $itineraryBody;
    $itineraryBody = preg_replace(
        '#<(?:div|section)\s+class="site-search-block placement-top"[^>]*>.*?</(?:div|section)>#si',
        '',
        $itineraryBody
    ) ?? $itineraryBody;
    $itineraryBody = '<div class="itinerary-single-content">' . $itineraryBody . '</div>';
    $usageLogic = $itinerary->getUsageLogic();
    $usageNotice = '';
    if ($usageLogic === Itinerary::USAGE_LOGIC_SEQUENTIAL) {
        $usageNotice = 'Este itinerario usa cookies para asegurar que sigues el orden de temas creado por su autor. La información guardada en esas cookies se usa exclusivamenente para ese fin. Al iniciar el itinerario aceptas su uso.';
    } elseif ($usageLogic === Itinerary::USAGE_LOGIC_ASSESSMENT) {
        $usageNotice = 'Este itinerario usa cookies para asegurar que sigues el orden de temas creado por su autor y que  pasas las autoevaluaciones entre temas. La información guardada en esas cookies se usa exclusivamenente para esos fines. Al iniciar el itinerario aceptas su uso.';
    }
    $presentationQuizHtml = '';
    $presentationQuizData = $itinerary->getQuiz();
    if ($itinerary->hasQuiz()) {
        $presentationQuestions = $presentationQuizData['questions'] ?? [];
        if (!empty($presentationQuestions)) {
            $presentationQuestionCount = count($presentationQuestions);
            $presentationMinimum = $itinerary->getQuizMinimumCorrect();
            $shuffledPresentationQuestions = $presentationQuestions;
            shuffle($shuffledPresentationQuestions);
            ob_start(); ?>
            <section
                class="itinerary-quiz"
                data-itinerary-quiz
                data-itinerary-slug="<?= htmlspecialchars($itinerary->getSlug(), ENT_QUOTES, 'UTF-8') ?>"
                data-topic-slug="__presentation"
                data-min-correct="<?= (int) $presentationMinimum ?>"
                data-usage-logic="<?= htmlspecialchars($usageLogic, ENT_QUOTES, 'UTF-8') ?>"
            >
                <div class="itinerary-quiz__header">
                    <h2>Autoevaluación de la presentación del itinerario</h2>
                    <p>Debes acertar al menos <?= (int) $presentationMinimum ?> de <?= (int) $presentationQuestionCount ?> preguntas para avanzar.</p>
                </div>
                <div class="itinerary-quiz__body">
                    <?php foreach ($shuffledPresentationQuestions as $index => $question): ?>
                        <?php
                        $answers = $question['answers'] ?? [];
                        shuffle($answers);
                        ?>
                        <article class="itinerary-quiz__question" data-quiz-question>
                            <h3>Pregunta <?= $index + 1 ?></h3>
                            <p><?= htmlspecialchars($question['text'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            <ul class="itinerary-quiz__answers">
                                <?php foreach ($answers as $answer): ?>
                                    <li>
                                        <label>
                                            <input
                                                type="checkbox"
                                                data-quiz-answer
                                                data-correct="<?= !empty($answer['correct']) ? '1' : '0' ?>"
                                                value="1"
                                            >
                                            <span><?= htmlspecialchars($answer['text'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="itinerary-quiz__actions">
                    <button type="button" class="button button-primary" data-quiz-submit>Comprobar respuestas</button>
                    <div class="itinerary-quiz__result" data-quiz-result aria-live="polite"></div>
                </div>
            </section>
            <?php
            $presentationQuizHtml = (string) ob_get_clean();
        }
    }
    $topicsHtml = '';
    if (!empty($topicSummaries)) {
        ob_start(); ?>
        <section
            class="itinerary-topics"
            data-itinerary-topics
            data-itinerary-slug="<?= htmlspecialchars($itinerary->getSlug(), ENT_QUOTES, 'UTF-8') ?>"
            data-usage-logic="<?= htmlspecialchars($usageLogic, ENT_QUOTES, 'UTF-8') ?>"
            data-presentation-quiz="<?= $itinerary->hasQuiz() ? '1' : '0' ?>"
        >
            <h2>Temas del itinerario</h2>
            <div class="itinerary-topics__list">
                <?php foreach ($topicSummaries as $topic): ?>
                    <?php
                        $topicImageUrl = null;
                        if (!empty($topic['image'])) {
                            $topicImageUrl = nammu_resolve_asset($topic['image'], $publicBaseUrl);
                        } elseif (!empty($itinerary->getImage())) {
                            $topicImageUrl = nammu_resolve_asset($itinerary->getImage(), $publicBaseUrl);
                        }
                    ?>
                    <article
                        class="itinerary-topic-card"
                        data-itinerary-topic
                        data-topic-slug="<?= htmlspecialchars($topic['slug'], ENT_QUOTES, 'UTF-8') ?>"
                        data-topic-number="<?= (int) $topic['number'] ?>"
                    >
                        <?php if ($topicImageUrl): ?>
                            <figure class="itinerary-topic-card__media">
                                <a href="<?= htmlspecialchars($topic['url'], ENT_QUOTES, 'UTF-8') ?>" data-topic-link>
                                    <img src="<?= htmlspecialchars($topicImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($topic['title'], ENT_QUOTES, 'UTF-8') ?>">
                                </a>
                            </figure>
                        <?php endif; ?>
                        <div class="itinerary-topic-card__number">
                            Tema <?= (int) $topic['number'] ?>
                        </div>
                        <div class="itinerary-topic-card__body">
                            <h3>
                                <a href="<?= htmlspecialchars($topic['url'], ENT_QUOTES, 'UTF-8') ?>" data-topic-link>
                                    <?= htmlspecialchars($topic['title'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </h3>
                            <?php if ($topic['description'] !== ''): ?>
                                <p class="itinerary-topic-card__description"><?= htmlspecialchars($topic['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <p class="itinerary-topic-card__lock" data-topic-lock-message style="display:none;">
                                Completa el paso anterior para desbloquear este tema.
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($firstTopicUrl): ?>
                <div class="itinerary-topics__cta">
                    <a
                        class="button button-primary"
                        href="<?= htmlspecialchars($firstTopicUrl, ENT_QUOTES, 'UTF-8') ?>"
                        data-first-topic-link="1"
                        data-topic-link
                        data-topic-slug="<?= htmlspecialchars($firstTopic !== null ? $firstTopic->getSlug() : '', ENT_QUOTES, 'UTF-8') ?>"
                        data-topic-number="<?= (int) ($firstTopic !== null ? $firstTopic->getNumber() : 1) ?>"
                    >Comenzar itinerario</a>
                </div>
            <?php endif; ?>
            <?php if ($usageNotice !== ''): ?>
                <div class="itinerary-usage-alert" role="note">
                    <?= htmlspecialchars($usageNotice, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        $topicsHtml = (string) ob_get_clean();
    }
    $content = $itineraryBody . $presentationQuizHtml . $topicsHtml;
    echo $renderer->render('layout', [
        'pageTitle' => $itinerary->getTitle(),
        'metaDescription' => $itineraryDescription,
        'content' => $content,
        'socialMeta' => $itinerarySocialMeta,
        'jsonLd' => [$siteJsonLd, $orgJsonLd],
        'pageLang' => $siteLang,
        'showLogo' => true,
    ]);
    exit;
}

if (preg_match('#^/itinerarios/?$#i', $routePath)) {
    $itineraries = $itineraryListing;
    $content = $renderer->render('itineraries', [
        'itineraries' => $itineraries,
    ]);
    $canon = $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') . '/itinerarios' : '/itinerarios';
    $description = 'Selección de itinerarios temáticos para seguir paso a paso.';
    if (function_exists('nammu_record_pageview')) {
        nammu_record_pageview('pages', 'itinerarios', 'Itinerarios');
    }
    $itineraryIndexMeta = nammu_build_social_meta([
        'type' => 'website',
        'title' => 'Itinerarios — ' . $siteNameForMeta,
        'description' => $description,
        'url' => $canon,
        'image' => $homeImage,
        'site_name' => $siteNameForMeta,
    ], $socialConfig);
    echo $renderer->render('layout', [
        'pageTitle' => 'Itinerarios',
        'metaDescription' => $description,
        'content' => $content,
        'socialMeta' => $itineraryIndexMeta,
        'jsonLd' => [$siteJsonLd, $orgJsonLd],
        'pageLang' => $siteLang,
        'showLogo' => true,
    ]);
    exit;
}

if (isset($_GET['post'])) {
    $candidateSlug = trim((string) $_GET['post']);
    if ($candidateSlug !== '') {
        $slug = $candidateSlug;
    }
}
if ($slug === null && !$isPaginationRoute && $routePath !== '/' && $routePath !== '/index.php') {
    $candidate = trim($routePath, '/');
    if ($candidate !== '') {
        $slug = $candidate;
    }
}

if ($slug === null && !$isPaginationRoute && isset($_GET['pagina'])) {
    $pageCandidate = filter_var($_GET['pagina'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($pageCandidate !== false) {
        $currentPage = (int) $pageCandidate;
    }
}

if ($currentPage < 1) {
    $currentPage = 1;
}

if ($slug !== null && $slug !== '') {
    $post = $contentRepository->findBySlug($slug);
    if (!$post) {
        $renderNotFound('Contenido no encontrado', 'La página solicitada no se encuentra disponible.', $routePath);
    }

    $documentData = $markdown->convertDocument($post->getContent());
    $converted = $documentData['html'];
    $autoTocHtml = '';
    $entryTocConfig = $theme['entry']['toc'] ?? ['auto' => 'off', 'min_headings' => 3];
    $autoTocEnabled = ($entryTocConfig['auto'] ?? 'off') === 'on';
    $entryMinHeadings = (int) ($entryTocConfig['min_headings'] ?? 3);
    if (!in_array($entryMinHeadings, [2, 3, 4], true)) {
        $entryMinHeadings = 3;
    }
    $postTemplateName = strtolower($post->getTemplate());
    if ($postTemplateName === 'page') {
        nammu_record_pageview('pages', $post->getSlug(), $post->getTitle());
    } else {
        nammu_record_pageview('posts', $post->getSlug(), $post->getTitle());
    }
    $renderableHeadings = array_filter($documentData['headings'], static function (array $heading): bool {
        return isset($heading['id'], $heading['text'], $heading['level'])
            && $heading['id'] !== ''
            && $heading['text'] !== ''
            && (int) $heading['level'] >= 1
            && (int) $heading['level'] <= 4;
    });
    if (
        $autoTocEnabled
        && !$documentData['has_manual_toc']
        && $postTemplateName !== 'page'
        && count($renderableHeadings) >= $entryMinHeadings
    ) {
        $generatedToc = $markdown->buildToc($documentData['headings']);
        if ($generatedToc !== '') {
            $autoTocHtml = $generatedToc;
        }
    }
    $postFilePath = __DIR__ . '/content/' . $post->getSlug() . '.md';
    $content = $renderer->render('single', [
        'pageTitle' => $post->getTitle(),
        'post' => $post,
        'htmlContent' => $converted,
        'postFilePath' => $postFilePath,
        'autoTocHtml' => $autoTocHtml,
    ]);

    $postImage = nammu_resolve_asset($post->getImage(), $publicBaseUrl);
    if ($postImage === null) {
        $postImage = $homeImage;
    }
    $postDescription = $post->getDescription();
    if ($postDescription === '') {
        $postDescription = nammu_excerpt_text($converted, 220);
    }
    $postCanonical = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/' . rawurlencode($post->getSlug());
    $postLang = trim((string) ($post->getMetadata()['Lang'] ?? ''));
    if ($postLang === '') {
        $postLang = $siteLang;
    }
    $publishedTime = $post->getDate() ? $post->getDate()->format('c') : '';
    $modifiedTime = is_file($postFilePath) ? gmdate('c', filemtime($postFilePath)) : '';
    $authorName = trim((string) ($configData['site_author'] ?? $siteNameForMeta));
    $postJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post->getTitle(),
        'description' => $postDescription,
        'mainEntityOfPage' => $postCanonical,
        'inLanguage' => $postLang,
    ];
    if ($publishedTime !== '') {
        $postJsonLd['datePublished'] = $publishedTime;
    }
    if ($modifiedTime !== '') {
        $postJsonLd['dateModified'] = $modifiedTime;
    }
    if ($postImage !== null && $postImage !== '') {
        $postJsonLd['image'] = $postImage;
    }
    if ($authorName !== '') {
        $postJsonLd['author'] = [
            '@type' => 'Person',
            'name' => $authorName,
        ];
    }
    if (!empty($orgJsonLd)) {
        $postJsonLd['publisher'] = $orgJsonLd;
    }
    $postSocialMeta = nammu_build_social_meta([
        'type' => 'article',
        'title' => $post->getTitle(),
        'description' => $postDescription,
        'url' => $postCanonical,
        'image' => $postImage,
        'site_name' => $siteNameForMeta,
        'published_time' => $publishedTime,
        'modified_time' => $modifiedTime,
        'author' => $authorName,
    ], $socialConfig);

    echo $renderer->render('layout', [
        'pageTitle' => $post->getTitle(),
        'metaDescription' => $post->getDescription() !== '' ? $post->getDescription() : $siteDescription,
        'content' => $content,
        'socialMeta' => $postSocialMeta,
        'jsonLd' => [$siteJsonLd, $orgJsonLd, $postJsonLd],
        'pageLang' => $postLang,
        'showLogo' => true,
    ]);
    exit;
}

$posts = $contentRepository->all();
if ($isAlphabeticalOrder) {
    usort($posts, $alphabeticalSorter);
}

$homeSettings = $theme['home'] ?? [];
$homeColumns = (int) ($homeSettings['columns'] ?? 2);
if ($homeColumns < 1 || $homeColumns > 3) {
    $homeColumns = 2;
}
$homeFirstRowEnabled = (($homeSettings['first_row_enabled'] ?? 'off') === 'on');
$homeFirstRowColumns = (int) ($homeSettings['first_row_columns'] ?? $homeColumns);
if ($homeFirstRowColumns < 1 || $homeFirstRowColumns > 3) {
    $homeFirstRowColumns = $homeColumns;
}
$homeFirstRowFill = (($homeSettings['first_row_fill'] ?? 'off') === 'on');
$perPageSetting = $homeSettings['per_page'] ?? 'all';
$perPage = null;
if (is_string($perPageSetting) && strtolower($perPageSetting) === 'all') {
    $perPage = null;
} elseif (is_numeric($perPageSetting)) {
    $perPage = (int) $perPageSetting;
} else {
    $perPageCandidate = filter_var($perPageSetting, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($perPageCandidate !== false) {
        $perPage = (int) $perPageCandidate;
    }
}
if ($perPage !== null && $perPage < 1) {
    $perPage = null;
}

$totalPosts = count($posts);
$totalPages = 1;
$paginatedPosts = $posts;
if ($perPage !== null) {
    $firstPageExtra = 0;
    if ($homeFirstRowEnabled && $homeFirstRowFill && $totalPosts > $perPage) {
        $remainder = max(0, $perPage - $homeFirstRowColumns);
        if ($homeColumns > 0) {
            $mod = $remainder % $homeColumns;
            if ($mod !== 0) {
                $firstPageExtra = min($homeColumns - $mod, max(0, $totalPosts - $perPage));
            }
        }
    }
    $firstPageTake = $perPage + $firstPageExtra;
    $remainingAfterFirst = max(0, $totalPosts - $firstPageTake);
    $totalPages = 1 + ($remainingAfterFirst > 0 ? (int) ceil($remainingAfterFirst / $perPage) : 0);
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    if ($currentPage === 1) {
        $paginatedPosts = array_slice($posts, 0, $firstPageTake);
    } else {
        $offset = $firstPageTake + ($currentPage - 2) * $perPage;
        $paginatedPosts = array_slice($posts, $offset, $perPage);
    }
} else {
    $currentPage = 1;
}

$paginationData = null;
if ($perPage !== null && $totalPages > 1) {
    $paginationData = [
        'current' => $currentPage,
        'total' => $totalPages,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
    ];
}

$postsForView = array_map($postToViewArray, $paginatedPosts);
$letterGroupsForView = [];
$letterGroupUrls = [];
if ($isAlphabeticalOrder) {
    $letterGroupsForView = nammu_group_items_by_letter($postsForView);
    foreach ($letterGroupsForView as $letter => $_group) {
        $letterGroupUrls[$letter] = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/letra/' . rawurlencode(nammu_letter_slug($letter));
    }
}

$bioHtml = $siteBio !== '' ? $markdown->toHtml($siteBio) : '';

$homeCanonicalPath = ($perPage !== null && $currentPage > 1) ? '/pagina/' . $currentPage : '/';
if ($publicBaseUrl !== '') {
    $baseForCanonical = rtrim($publicBaseUrl, '/');
    $homeCanonical = $homeCanonicalPath === '/' ? $baseForCanonical . '/' : $baseForCanonical . $homeCanonicalPath;
} else {
    $homeCanonical = $homeCanonicalPath;
}

$homeSocialMeta = nammu_build_social_meta([
    'type' => 'website',
    'title' => $displaySiteTitle,
    'description' => $homeDescription,
    'url' => $homeCanonical,
    'image' => $homeImage,
    'site_name' => $siteNameForMeta,
], $socialConfig);

$blogOwner = $theme['author'] ?? '';
if ($blogOwner === '') {
    $blogOwner = $siteTitle;
}
$homePageTitle = $displaySiteTitle . ' por ' . $blogOwner;
if ($perPage !== null && $currentPage > 1) {
    $homePageTitle .= ' - Página ' . $currentPage;
}

$content = $renderer->render('home', [
    'posts' => $postsForView,
    'bioHtml' => $bioHtml,
    'pagination' => $paginationData,
    'letterGroups' => $letterGroupsForView,
    'isAlphabetical' => $isAlphabeticalOrder,
    'letterGroupUrls' => $letterGroupUrls,
]);

echo $renderer->render('layout', [
    'pageTitle' => $homePageTitle,
    'metaDescription' => $siteDescription,
    'content' => $content,
    'socialMeta' => $homeSocialMeta,
    'jsonLd' => [$siteJsonLd, $orgJsonLd],
    'pageLang' => $siteLang,
    'showLogo' => ($routePath !== '/' && $routePath !== '/index.php'),
]);

if ($publicBaseUrl !== '') {
    @file_put_contents(__DIR__ . '/rss.xml', (new RssGenerator($publicBaseUrl, $siteTitle, $siteDescription, $homeUrl, $rssUrl, $siteLang))->generate(
        $posts,
        static fn (Post $post): string => '/' . rawurlencode($post->getSlug()),
        $markdown
    ));
    $itineraryFeedPosts = [];
    $itineraryPostUrls = [];
    foreach ($itineraryListing as $itineraryItem) {
        $itineraryMetadata = $itineraryItem->getMetadata();
        $itineraryDescription = $itineraryItem->getDescription();
        if ($itineraryDescription === '') {
            $convertedDocument = $markdown->convertDocument($itineraryItem->getContent());
            $itineraryDescription = nammu_excerpt_text($convertedDocument['html'], 220);
        }
        $dateString = $itineraryMetadata['Date'] ?? ($itineraryMetadata['Updated'] ?? '');
        if (trim((string) $dateString) === '') {
            $indexPath = $itineraryItem->getDirectory() . '/index.md';
            $mtime = is_file($indexPath) ? @filemtime($indexPath) : false;
            if ($mtime !== false) {
                $dateString = gmdate('Y-m-d', $mtime);
            } else {
                $dateString = gmdate('Y-m-d');
            }
        }
        $virtualMeta = [
            'Title' => $itineraryItem->getTitle(),
            'Description' => $itineraryDescription,
            'Image' => $itineraryItem->getImage() ?? '',
            'Date' => $dateString,
        ];
        $virtualSlug = 'itinerary-feed-' . $itineraryItem->getSlug();
        $virtualPost = new Post($virtualSlug, $virtualMeta, $itineraryItem->getContent());
        $itineraryFeedPosts[] = $virtualPost;
        $itineraryPostUrls[$virtualSlug] = $buildItineraryUrl($itineraryItem);
    }
    usort($itineraryFeedPosts, static function (Post $a, Post $b): int {
        $dateA = $a->getDate();
        $dateB = $b->getDate();
        if ($dateA && $dateB) {
            return $dateB <=> $dateA;
        }
        if ($dateA) {
            return -1;
        }
        if ($dateB) {
            return 1;
        }
        return strcmp($a->getSlug(), $b->getSlug());
    });
    $itinerariesIndexUrlLocal = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/itinerarios';
    $itinerariesFeedUrlLocal = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/itinerarios.xml';
    $itineraryFeedContent = (new RssGenerator(
        $publicBaseUrl,
        $siteTitle . ' — Itinerarios',
        'Itinerarios recientes',
        $itinerariesIndexUrlLocal,
        $itinerariesFeedUrlLocal,
        $siteLang
    ))->generate(
        $itineraryFeedPosts,
        static function (Post $post) use ($itineraryPostUrls): string {
            return $itineraryPostUrls[$post->getSlug()] ?? '/';
        },
        $markdown,
        false
    );
    @file_put_contents(__DIR__ . '/itinerarios.xml', $itineraryFeedContent);
    @file_put_contents(__DIR__ . '/podcast.xml', nammu_generate_podcast_feed($publicBaseUrl, $config));
}
$sitemapGenerator = new SitemapGenerator($publicBaseUrl);
$sitemapXml = $sitemapGenerator->generate($buildSitemapEntries($posts, $theme, $publicBaseUrl));
@file_put_contents(__DIR__ . '/sitemap.xml', $sitemapXml);
