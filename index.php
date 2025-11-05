<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/core/bootstrap.php';

use Nammu\Core\ContentRepository;
use Nammu\Core\MarkdownConverter;
use Nammu\Core\Post;
use Nammu\Core\RssGenerator;
use Nammu\Core\TemplateRenderer;

$contentRepository = new ContentRepository(__DIR__ . '/content');
$markdown = new MarkdownConverter();

$siteDocument = $contentRepository->getDocument('index');
$siteTitle = $siteDocument['metadata']['Title'] ?? 'Nammu Blog';
$siteDescription = $siteDocument['metadata']['Description'] ?? '';
$siteBio = $siteDocument['content'] ?? '';

$baseUrl = nammu_base_url();
$publicBaseUrl = $baseUrl;
$homeUrl = $publicBaseUrl !== '' ? $publicBaseUrl : '/';
$rssUrl = ($publicBaseUrl !== '' ? $publicBaseUrl : '') . '/rss.xml';

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

$renderer = new TemplateRenderer(__DIR__ . '/template', [
    'siteTitle' => $displaySiteTitle,
    'siteDescription' => $siteDescription,
    'rssUrl' => $rssUrl !== '' ? $rssUrl : '/rss.xml',
    'baseUrl' => $homeUrl,
    'theme' => $theme,
]);

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

$renderer->setGlobal('postUrl', function (Post|string $post) use ($publicBaseUrl): string {
    $slug = $post instanceof Post ? $post->getSlug() : (string) $post;
    $path = '/' . rawurlencode($slug);
    return $publicBaseUrl !== '' ? $publicBaseUrl . $path : $path;
});
$renderer->setGlobal('paginationUrl', function (int $page) use ($publicBaseUrl): string {
    $target = $page <= 1 ? '/' : '/pagina/' . $page;
    return $publicBaseUrl !== '' ? $publicBaseUrl . $target : $target;
});

$renderer->setGlobal('markdownToHtml', function (string $markdownText) use ($markdown): string {
    return $markdown->toHtml($markdownText);
});
$renderer->setGlobal('baseUrl', $publicBaseUrl !== '' ? $publicBaseUrl : '/');
$renderer->setGlobal('socialConfig', $socialConfig);

$routePath = nammu_route_path();
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
    $rssGenerator = new RssGenerator($publicBaseUrl, $siteTitle, $siteDescription);
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

$slug = null;
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
        http_response_code(404);
        $content = $renderer->render('404', [
            'pageTitle' => 'Contenido no encontrado',
        ]);
        echo $renderer->render('layout', [
            'pageTitle' => '404',
            'metaDescription' => 'La página solicitada no se encuentra disponible',
            'content' => $content,
            'socialMeta' => nammu_build_social_meta([
                'type' => 'website',
                'title' => '404 — ' . $siteTitle,
                'description' => $homeDescription,
                'url' => ($publicBaseUrl !== '' ? $publicBaseUrl : '') . $routePath,
                'image' => $homeImage,
                'site_name' => $siteNameForMeta,
            ], $socialConfig),
            'showLogo' => false,
        ]);
        exit;
    }

    $converted = $markdown->toHtml($post->getContent());
    $content = $renderer->render('single', [
        'pageTitle' => $post->getTitle(),
        'post' => $post,
        'htmlContent' => $converted,
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
    $postSocialMeta = nammu_build_social_meta([
        'type' => 'article',
        'title' => $post->getTitle(),
        'description' => $postDescription,
        'url' => $postCanonical,
        'image' => $postImage,
        'site_name' => $siteNameForMeta,
    ], $socialConfig);

    echo $renderer->render('layout', [
        'pageTitle' => $post->getTitle(),
        'metaDescription' => $post->getDescription() !== '' ? $post->getDescription() : $siteDescription,
        'content' => $content,
        'socialMeta' => $postSocialMeta,
        'showLogo' => true,
    ]);
    exit;
}

$posts = $contentRepository->all();

$homeSettings = $theme['home'] ?? [];
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
    $totalPages = max(1, (int) ceil($totalPosts / $perPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    $offset = max(0, ($currentPage - 1) * $perPage);
    $paginatedPosts = array_slice($posts, $offset, $perPage);
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

$postsForView = array_map(
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
    $paginatedPosts
);

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

$homePageTitle = $displaySiteTitle;
if ($perPage !== null && $currentPage > 1) {
    $homePageTitle .= ' - Página ' . $currentPage;
}

$content = $renderer->render('home', [
    'posts' => $postsForView,
    'bioHtml' => $bioHtml,
    'pagination' => $paginationData,
]);

echo $renderer->render('layout', [
    'pageTitle' => $homePageTitle,
    'metaDescription' => $siteDescription,
    'content' => $content,
    'socialMeta' => $homeSocialMeta,
    'showLogo' => false,
]);

if ($publicBaseUrl !== '') {
    @file_put_contents(__DIR__ . '/rss.xml', (new RssGenerator($publicBaseUrl, $siteTitle, $siteDescription))->generate(
        $posts,
        static fn (Post $post): string => '/' . rawurlencode($post->getSlug()),
        $markdown
    ));
}

function nammu_base_url(): string
{
    $explicit = getenv('NAMMU_BASE_URL');
    if ($explicit !== false && $explicit !== '') {
        return rtrim($explicit, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $host = trim($host);
    if ($host === '') {
        return '';
    }

    $scheme = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $schemeCandidate = strtolower(trim($forwarded[0]));
        if (in_array($schemeCandidate, ['http', 'https'], true)) {
            $scheme = $schemeCandidate;
        }
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
        $schemeCandidate = strtolower((string) $_SERVER['REQUEST_SCHEME']);
        if (in_array($schemeCandidate, ['http', 'https'], true)) {
            $scheme = $schemeCandidate;
        }
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName !== '' ? $scriptName : '/'));
    if ($dir === '/' || $dir === '.') {
        $dir = '';
    } else {
        $dir = rtrim($dir, '/');
    }

    $portSuffix = '';
    if (isset($_SERVER['SERVER_PORT']) && !str_contains($host, ':')) {
        $port = (int) $_SERVER['SERVER_PORT'];
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $portSuffix = ':' . $port;
        }
    }

    return rtrim($scheme . '://' . $host . $portSuffix . $dir, '/');
}

function nammu_route_path(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseDir = str_replace('\\', '/', dirname($scriptName));
    $baseDir = $baseDir === '/' ? '' : rtrim($baseDir, '/');

    if ($baseDir !== '' && str_starts_with($path, $baseDir)) {
        $path = substr($path, strlen($baseDir));
        if ($path === false || $path === '') {
            $path = '/';
        }
    }

    return $path === '' ? '/' : $path;
}

function nammu_template_settings(): array
{
    $defaults = nammu_default_template_settings();
    $config = nammu_load_config();

    $template = $config['template'] ?? [];
    $fonts = array_merge($defaults['fonts'], $template['fonts'] ?? []);
    $colors = array_merge($defaults['colors'], $template['colors'] ?? []);
    $images = array_merge($defaults['images'], $template['images'] ?? []);
    $footer = $template['footer'] ?? ($defaults['footer'] ?? '');
    $globalConfig = $template['global'] ?? [];
    $global = array_merge($defaults['global'], $globalConfig);
    $cornerStyle = $global['corners'] ?? $defaults['global']['corners'];
    if (!in_array($cornerStyle, ['rounded', 'square'], true)) {
        $cornerStyle = $defaults['global']['corners'];
    }
    $global['corners'] = $cornerStyle;

    $homeConfig = $template['home'] ?? [];
    $home = array_merge($defaults['home'], $homeConfig);
    $homeBlocks = $home['blocks'] ?? $defaults['home']['blocks'];
    if (!in_array($homeBlocks, ['boxed', 'flat'], true)) {
        $homeBlocks = $defaults['home']['blocks'];
    }
    $home['blocks'] = $homeBlocks;
    $fullImageMode = $home['full_image_mode'] ?? $defaults['home']['full_image_mode'];
    if (!in_array($fullImageMode, ['natural', 'crop'], true)) {
        $fullImageMode = $defaults['home']['full_image_mode'];
    }
    $home['full_image_mode'] = $fullImageMode;
    $homeHeaderDefaults = $defaults['home']['header'];
    $homeHeader = array_merge($homeHeaderDefaults, $homeConfig['header'] ?? []);
    $headerTypes = ['none', 'graphic', 'text', 'mixed'];
    if (!in_array($homeHeader['type'], $headerTypes, true)) {
        $homeHeader['type'] = $homeHeaderDefaults['type'];
    }
    $headerModes = ['contain', 'cover'];
    if (!in_array($homeHeader['mode'], $headerModes, true)) {
        $homeHeader['mode'] = $homeHeaderDefaults['mode'];
    }
    $textHeaderStyles = ['boxed', 'plain'];
    if (!in_array($homeHeader['text_style'], $textHeaderStyles, true)) {
        $homeHeader['text_style'] = $homeHeaderDefaults['text_style'];
    }
    if (in_array($homeHeader['type'], ['graphic', 'mixed'], true) && trim((string) $homeHeader['image']) === '') {
        $homeHeader['type'] = $homeHeader['type'] === 'mixed' ? 'text' : 'none';
    }
    if ($homeHeader['type'] !== 'graphic' && $homeHeader['type'] !== 'mixed') {
        $homeHeader['image'] = '';
        $homeHeader['mode'] = $homeHeaderDefaults['mode'];
    }
    if ($homeHeader['type'] !== 'text' && $homeHeader['type'] !== 'mixed') {
        $homeHeader['text_style'] = $homeHeaderDefaults['text_style'];
    }
    $home['header'] = $homeHeader;
    $author = $config['site_author'] ?? '';
    $blog = $config['site_name'] ?? '';

    $fontRequests = [];
    $titleFont = $fonts['title'] ?? '';
    $bodyFont = $fonts['body'] ?? '';
    $codeFont = $fonts['code'] ?? '';
    $quoteFont = $fonts['quote'] ?? '';

    if ($titleFont !== '') {
        $fontRequests[$titleFont] = 'wght@400;700';
    }
    if ($bodyFont !== '') {
        $fontRequests[$bodyFont] = 'wght@400;700';
    }
    if ($quoteFont !== '') {
        if (!isset($fontRequests[$quoteFont])) {
            $fontRequests[$quoteFont] = 'wght@400;700';
        }
    }
    if ($codeFont !== '') {
        if (!isset($fontRequests[$codeFont])) {
            $fontRequests[$codeFont] = 'wght@400';
        }
    }

    $families = [];
    foreach ($fontRequests as $fontName => $variant) {
        if ($fontName === '' || $fontName === null) {
            continue;
        }
        $family = str_replace(' ', '+', $fontName);
        if ($variant !== '') {
            $family .= ':' . $variant;
        }
        $families[] = $family;
    }

    $fontUrl = null;
    if (!empty($families)) {
        $fontUrl = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families) . '&display=swap';
    }

    return [
        'fonts' => $fonts,
        'colors' => $colors,
        'images' => $images,
        'fontUrl' => $fontUrl,
        'footer' => $footer,
        'global' => $global,
        'corners' => $cornerStyle,
        'home' => $home,
        'author' => $author,
        'blog' => $blog,
    ];
}

function nammu_default_template_settings(): array
{
    return [
        'fonts' => [
            'title' => 'Gabarito',
            'body' => 'Roboto',
            'code' => 'VT323',
            'quote' => 'Castoro',
        ],
        'colors' => [
            'h1' => '#1b8eed',
            'h2' => '#ea2f28',
            'h3' => '#1b1b1b',
            'intro' => '#f6f6f6',
            'text' => '#222222',
            'background' => '#ffffff',
            'highlight' => '#f3f6f9',
            'accent' => '#0a4c8a',
            'brand' => '#1b1b1b',
            'code_background' => '#000000',
            'code_text' => '#90ee90',
        ],
        'footer' => '',
        'images' => [
            'logo' => '',
        ],
        'global' => [
            'corners' => 'rounded',
        ],
        'home' => [
            'columns' => 2,
            'per_page' => 'all',
            'card_style' => 'full',
            'blocks' => 'boxed',
            'full_image_mode' => 'natural',
            'header' => [
                'type' => 'none',
                'image' => '',
                'mode' => 'contain',
                'text_style' => 'boxed',
                'order' => 'image-text',
            ],
        ],
    ];
}

function nammu_load_config(): array
{
    $configFile = __DIR__ . '/config/config.yml';
    if (!is_file($configFile)) {
        return [];
    }

    if (function_exists('yaml_parse_file')) {
        $parsed = @yaml_parse_file($configFile);
        return is_array($parsed) ? $parsed : [];
    }

    $raw = file_get_contents($configFile);
    if ($raw === false) {
        return [];
    }

    $parsed = nammu_simple_yaml_parse($raw);
    return is_array($parsed) ? $parsed : [];
}

function nammu_simple_yaml_parse(string $yaml): array
{
    $lines = preg_split("/\r?\n/", $yaml);
    $result = [];
    $stack = [&$result];
    $indentStack = [0];

    foreach ($lines as $line) {
        if ($line === '' || trim($line) === '' || preg_match('/^\s*#/', $line)) {
            continue;
        }
        $indent = strlen($line) - strlen(ltrim($line, ' '));
        $trimmed = trim($line);
        if (!str_contains($trimmed, ':')) {
            continue;
        }
        [$rawKey, $rawValue] = explode(':', $trimmed, 2);
        $key = trim($rawKey);
        $value = ltrim($rawValue, " \t");

        while ($indent < end($indentStack)) {
            array_pop($stack);
            array_pop($indentStack);
        }

        $current = &$stack[count($stack) - 1];
        if ($value === '') {
            $current[$key] = [];
            $stack[] = &$current[$key];
            $indentStack[] = $indent + 2;
        } else {
            $current[$key] = nammu_simple_yaml_unescape($value);
        }
    }

    return $result;
}

function nammu_simple_yaml_unescape(string $value): string
{
    $value = trim($value);
    if ($value === "''" || $value === '""') {
        return '';
    }
    if ($value !== '' && substr($value, -1) === $value[0]) {
        if ($value[0] === '"') {
            $inner = substr($value, 1, -1);
            $decoded = stripcslashes($inner);
            return str_replace('\\n', "\n", $decoded);
        }
        if ($value[0] === "'") {
            $inner = substr($value, 1, -1);
            $decoded = str_replace("''", "'", $inner);
            return str_replace('\\n', "\n", $decoded);
        }
    }
    return str_replace('\\n', "\n", $value);
}

function nammu_social_settings(): array
{
    $config = nammu_load_config();
    $defaults = [
        'default_description' => '',
        'home_image' => '',
        'twitter' => '',
        'facebook_app_id' => '',
    ];
    $social = $config['social'] ?? [];
    if (!is_array($social)) {
        $social = [];
    }
    return array_merge($defaults, $social);
}

function nammu_build_social_meta(array $data, array $socialConfig): array
{
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    if ($description !== '') {
        $description = preg_replace('/\s+/u', ' ', $description);
    }
    $url = $data['url'] ?? '';
    $image = $data['image'] ?? '';
    $siteName = trim($data['site_name'] ?? '');

    $properties = [
        'og:type' => $data['type'] ?? 'website',
        'og:title' => $title,
        'og:description' => $description,
        'og:url' => $url,
    ];

    if ($image !== '') {
        $properties['og:image'] = $image;
    }
    if ($siteName !== '') {
        $properties['og:site_name'] = $siteName;
    }
    if (!empty($socialConfig['facebook_app_id'])) {
        $properties['fb:app_id'] = $socialConfig['facebook_app_id'];
    }

    $names = [
        'twitter:card' => 'summary_large_image',
        'twitter:title' => $title,
        'twitter:description' => $description,
    ];

    if ($url !== '') {
        $names['twitter:url'] = $url;
    }
    if ($image !== '') {
        $names['twitter:image'] = $image;
    }
    if (!empty($socialConfig['twitter'])) {
        $handle = $socialConfig['twitter'];
        if ($handle !== '' && $handle[0] !== '@') {
            $handle = '@' . $handle;
        }
        $names['twitter:site'] = $handle;
    }

    return [
        'canonical' => $url,
        'properties' => $properties,
        'names' => $names,
    ];
}

function nammu_excerpt_text(string $html, int $length = 200): string
{
    $text = trim(strip_tags($html));
    $text = preg_replace('/\s+/u', ' ', $text);
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

function nammu_format_date_spanish(?DateTimeImmutable $date, ?string $fallback = ''): string
{
    if ($date instanceof DateTimeImmutable) {
        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];
        $monthIndex = (int) $date->format('n');
        $monthName = $months[$monthIndex] ?? $date->format('m');
        return ltrim($date->format('j')) . ' de ' . $monthName . ' de ' . $date->format('Y');
    }

    $fallback = $fallback !== null ? trim($fallback) : '';
    if ($fallback === '') {
        return '';
    }

    $knownFormats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
    foreach ($knownFormats as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $fallback);
        if ($parsed instanceof DateTimeImmutable) {
            return nammu_format_date_spanish($parsed, '');
        }
    }

    $timestamp = strtotime($fallback);
    if ($timestamp !== false) {
        $parsed = (new DateTimeImmutable())->setTimestamp($timestamp);
        return nammu_format_date_spanish($parsed, '');
    }

    return $fallback;
}

function nammu_resolve_asset(?string $path, string $baseUrl): ?string
{
    if ($path === null) {
        return null;
    }

    $path = trim($path);
    if ($path === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $normalized = ltrim($path, '/');
    if (!str_starts_with($normalized, 'assets/')) {
        $normalized = 'assets/' . $normalized;
    }

    if ($baseUrl !== '') {
        return rtrim($baseUrl, '/') . '/' . $normalized;
    }

    return '/' . $normalized;
}
