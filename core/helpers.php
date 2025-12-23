<?php

declare(strict_types=1);

use Nammu\Core\Post;

function nammu_ensure_directory(string $directory, int $permissions = 0775): bool
{
    $directory = rtrim($directory, '/');
    if ($directory === '') {
        return false;
    }

    clearstatcache(true, $directory);
    if (is_dir($directory)) {
        @chmod($directory, $permissions);
        return true;
    }

    if (@mkdir($directory, $permissions, true) || is_dir($directory)) {
        @chmod($directory, $permissions);
        return true;
    }

    return false;
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

function nammu_stats_consent_cookie_name(): string
{
    return 'nammu_stats_consent';
}

function nammu_stats_uid_cookie_name(): string
{
    return 'nammu_stats_uid';
}

function nammu_has_stats_consent(): bool
{
    return ($_COOKIE[nammu_stats_consent_cookie_name()] ?? '') === '1';
}

function nammu_stats_uid(): ?string
{
    if (!nammu_has_stats_consent()) {
        return null;
    }
    $cookieName = nammu_stats_uid_cookie_name();
    $existing = trim((string) ($_COOKIE[$cookieName] ?? ''));
    if ($existing !== '') {
        return $existing;
    }
    try {
        $uid = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $uid = bin2hex(pack('N', time())) . bin2hex(pack('N', mt_rand(1, PHP_INT_MAX)));
    }
    setcookie($cookieName, $uid, time() + 31536000, '/', '', false, false);
    $_COOKIE[$cookieName] = $uid;
    return $uid;
}

function nammu_analytics_file_path(): string
{
    return dirname(__DIR__) . '/config/analytics.json';
}

function nammu_load_analytics(): array
{
    $file = nammu_analytics_file_path();
    if (!is_file($file)) {
        return [
            'visitors' => ['daily' => []],
            'content' => ['posts' => [], 'pages' => []],
            'itineraries' => ['items' => []],
            'updated_at' => 0,
        ];
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return [
            'visitors' => ['daily' => []],
            'content' => ['posts' => [], 'pages' => []],
            'itineraries' => ['items' => []],
            'updated_at' => 0,
        ];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'visitors' => ['daily' => []],
            'content' => ['posts' => [], 'pages' => []],
            'itineraries' => ['items' => []],
            'platform' => ['daily' => []],
            'updated_at' => 0,
        ];
    }
    $decoded['visitors'] = $decoded['visitors'] ?? ['daily' => []];
    $decoded['content'] = $decoded['content'] ?? ['posts' => [], 'pages' => []];
    $decoded['itineraries'] = $decoded['itineraries'] ?? ['items' => []];
    $decoded['platform'] = $decoded['platform'] ?? ['daily' => []];
    $decoded['updated_at'] = (int) ($decoded['updated_at'] ?? 0);
    return $decoded;
}

function nammu_save_analytics(array $data): void
{
    $file = nammu_analytics_file_path();
    $dir = dirname($file);
    nammu_ensure_directory($dir, 0775);
    $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return;
    }
    @file_put_contents($file, $payload);
}

function nammu_analytics_touch_visit(array &$data, string $uid, string $date): bool
{
    if (!isset($data['visitors']['daily'][$date])) {
        $data['visitors']['daily'][$date] = ['uids' => []];
    }
    if (!isset($data['visitors']['daily'][$date]['uids'])) {
        $data['visitors']['daily'][$date]['uids'] = [];
    }
    if (isset($data['visitors']['daily'][$date]['uids'][$uid])) {
        return false;
    }
    $data['visitors']['daily'][$date]['uids'][$uid] = 1;
    return true;
}

function nammu_detect_device_type(string $userAgent): string
{
    $ua = strtolower($userAgent);
    if ($ua === '') {
        return 'desktop';
    }
    if (preg_match('/tablet|ipad|playbook|silk|android(?!.*mobile)/i', $ua)) {
        return 'tablet';
    }
    if (preg_match('/mobi|iphone|ipod|android|blackberry|phone|iemobile|opera mini|mobile/i', $ua)) {
        return 'mobile';
    }
    return 'desktop';
}

function nammu_detect_browser(string $userAgent): string
{
    $ua = strtolower($userAgent);
    if ($ua === '') {
        return 'otros';
    }
    if (preg_match('/edg\//', $ua)) {
        return 'edge';
    }
    if (preg_match('/opr\/|opera/', $ua)) {
        return 'opera';
    }
    if (preg_match('/firefox\/|fxios/', $ua)) {
        return 'firefox';
    }
    if (preg_match('/chrome\/|crios|chromium/', $ua) && !preg_match('/edg\//', $ua) && !preg_match('/opr\//', $ua)) {
        return 'chrome';
    }
    if (preg_match('/safari/', $ua) && !preg_match('/chrome|crios|chromium|edg\//', $ua)) {
        return 'safari';
    }
    return 'otros';
}

function nammu_detect_os(string $userAgent): string
{
    $ua = strtolower($userAgent);
    if ($ua === '') {
        return 'otros';
    }
    if (preg_match('/windows nt/', $ua)) {
        return 'windows';
    }
    if (preg_match('/cros/', $ua)) {
        return 'chromeos';
    }
    if (preg_match('/mac os x/', $ua)) {
        return 'macos';
    }
    if (preg_match('/linux/', $ua)) {
        return 'linux';
    }
    return 'otros';
}

function nammu_detect_language(string $acceptLanguage): string
{
    $raw = trim($acceptLanguage);
    if ($raw === '') {
        return '';
    }
    $first = explode(',', $raw)[0] ?? '';
    $first = trim(explode(';', $first)[0] ?? '');
    $first = strtolower($first);
    if ($first === '') {
        return '';
    }
    $parts = explode('-', $first);
    return $parts[0] ?? $first;
}

function nammu_record_platform_visit(array &$data, string $uid, string $date): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device = nammu_detect_device_type($ua);
    $browser = nammu_detect_browser($ua);
    $language = nammu_detect_language($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $changed = false;

    if (!isset($data['platform']['daily'][$date])) {
        $data['platform']['daily'][$date] = [];
    }
    $bucket = &$data['platform']['daily'][$date];

    $setUid = static function (array &$target, string $value, string $uid) use (&$changed): void {
        if ($value === '') {
            return;
        }
        if (!isset($target[$value])) {
            $target[$value] = ['uids' => []];
        }
        if (!isset($target[$value]['uids'])) {
            $target[$value]['uids'] = [];
        }
        if (!isset($target[$value]['uids'][$uid])) {
            $target[$value]['uids'][$uid] = 1;
            $changed = true;
        }
    };

    if (!isset($bucket['device'])) {
        $bucket['device'] = [];
    }
    $setUid($bucket['device'], $device, $uid);

    if (!isset($bucket['browser'])) {
        $bucket['browser'] = [];
    }
    $setUid($bucket['browser'], $browser, $uid);

    if ($language !== '') {
        if (!isset($bucket['language'])) {
            $bucket['language'] = [];
        }
        $setUid($bucket['language'], $language, $uid);
    }

    if ($device === 'desktop') {
        $os = nammu_detect_os($ua);
        if (!isset($bucket['os'])) {
            $bucket['os'] = [];
        }
        $setUid($bucket['os'], $os, $uid);
    }

    return $changed;
}

function nammu_record_visit(): void
{
    if (!nammu_has_stats_consent()) {
        return;
    }
    if (!empty($GLOBALS['nammu_analytics_visit_recorded'])) {
        return;
    }
    $uid = nammu_stats_uid();
    if ($uid === null) {
        return;
    }
    $data = nammu_load_analytics();
    $date = date('Y-m-d');
    $changed = nammu_analytics_touch_visit($data, $uid, $date);
    if (nammu_record_platform_visit($data, $uid, $date)) {
        $changed = true;
    }
    if ($changed) {
        $data['updated_at'] = time();
        nammu_save_analytics($data);
    }
    $GLOBALS['nammu_analytics_visit_recorded'] = true;
}

function nammu_record_pageview(string $type, string $slug, string $title = ''): void
{
    if (!nammu_has_stats_consent()) {
        return;
    }
    $uid = nammu_stats_uid();
    if ($uid === null) {
        return;
    }
    $data = nammu_load_analytics();
    $date = date('Y-m-d');
    $changed = nammu_analytics_touch_visit($data, $uid, $date);
    $bucket = $type === 'pages' ? 'pages' : 'posts';
    if (!isset($data['content'][$bucket])) {
        $data['content'][$bucket] = [];
    }
    if (!isset($data['content'][$bucket][$slug])) {
        $data['content'][$bucket][$slug] = [
            'title' => $title,
            'total' => 0,
            'daily' => [],
        ];
    }
    if ($title !== '') {
        $data['content'][$bucket][$slug]['title'] = $title;
    }
    $data['content'][$bucket][$slug]['total'] = (int) ($data['content'][$bucket][$slug]['total'] ?? 0) + 1;
    if (!isset($data['content'][$bucket][$slug]['daily'][$date])) {
        $data['content'][$bucket][$slug]['daily'][$date] = 0;
    }
    $data['content'][$bucket][$slug]['daily'][$date] = (int) ($data['content'][$bucket][$slug]['daily'][$date] ?? 0) + 1;
    $data['updated_at'] = time();
    nammu_save_analytics($data);
    $GLOBALS['nammu_analytics_visit_recorded'] = true;
}

function nammu_record_itinerary_event(string $slug, string $event): void
{
    if (!nammu_has_stats_consent()) {
        return;
    }
    $uid = nammu_stats_uid();
    if ($uid === null) {
        return;
    }
    $data = nammu_load_analytics();
    $date = date('Y-m-d');
    $changed = nammu_analytics_touch_visit($data, $uid, $date);
    if (!isset($data['itineraries']['items'])) {
        $data['itineraries']['items'] = [];
    }
    if (!isset($data['itineraries']['items'][$slug])) {
        $data['itineraries']['items'][$slug] = [
            'started_daily' => [],
            'completed_daily' => [],
        ];
    }
    $bucket = $event === 'complete' ? 'completed_daily' : 'started_daily';
    if (!isset($data['itineraries']['items'][$slug][$bucket][$date])) {
        $data['itineraries']['items'][$slug][$bucket][$date] = ['uids' => []];
    }
    if (!isset($data['itineraries']['items'][$slug][$bucket][$date]['uids'])) {
        $data['itineraries']['items'][$slug][$bucket][$date]['uids'] = [];
    }
    if (!isset($data['itineraries']['items'][$slug][$bucket][$date]['uids'][$uid])) {
        $data['itineraries']['items'][$slug][$bucket][$date]['uids'][$uid] = 1;
        $changed = true;
    }
    if ($changed) {
        $data['updated_at'] = time();
        nammu_save_analytics($data);
    }
    $GLOBALS['nammu_analytics_visit_recorded'] = true;
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
    $footerLogo = $template['footer_logo'] ?? ($defaults['footer_logo'] ?? 'none');
    if (!in_array($footerLogo, ['none', 'top', 'bottom'], true)) {
        $footerLogo = $defaults['footer_logo'] ?? 'none';
    }
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

    $searchDefaults = $defaults['search'] ?? [
        'mode' => 'none',
        'position' => 'title',
        'floating' => 'off',
    ];
    $searchConfig = array_merge($searchDefaults, $template['search'] ?? []);
    if (!in_array($searchConfig['mode'], ['none', 'home', 'single', 'both'], true)) {
        $searchConfig['mode'] = $searchDefaults['mode'];
    }
    if (!in_array($searchConfig['position'], ['title', 'footer'], true)) {
        $searchConfig['position'] = $searchDefaults['position'];
    }
    if (!in_array($searchConfig['floating'], ['off', 'on'], true)) {
        $searchConfig['floating'] = $searchDefaults['floating'];
    }
    $subscriptionDefaults = $defaults['subscription'] ?? [
        'mode' => 'none',
        'position' => 'footer',
        'floating' => 'off',
    ];
    $subscriptionConfig = array_merge($subscriptionDefaults, $template['subscription'] ?? []);
    if (!in_array($subscriptionConfig['mode'], ['none', 'home', 'single', 'both'], true)) {
        $subscriptionConfig['mode'] = $subscriptionDefaults['mode'];
    }
    if (!in_array($subscriptionConfig['position'], ['title', 'footer'], true)) {
        $subscriptionConfig['position'] = $subscriptionDefaults['position'];
    }
    if (!in_array($subscriptionConfig['floating'], ['off', 'on'], true)) {
        $subscriptionConfig['floating'] = $subscriptionDefaults['floating'];
    }
    $entryDefaults = $defaults['entry']['toc'] ?? ['auto' => 'off', 'min_headings' => 3];
    $entryTemplateConfig = $template['entry']['toc'] ?? [];
    $entryAuto = $entryTemplateConfig['auto'] ?? $entryDefaults['auto'];
    if (!in_array($entryAuto, ['on', 'off'], true)) {
        $entryAuto = $entryDefaults['auto'];
    }
    $entryMin = (int) ($entryTemplateConfig['min_headings'] ?? $entryDefaults['min_headings']);
    if (!in_array($entryMin, [2, 3, 4], true)) {
        $entryMin = $entryDefaults['min_headings'];
    }
    $entryConfig = [
        'toc' => [
            'auto' => $entryAuto,
            'min_headings' => $entryMin,
        ],
    ];

    return [
        'fonts' => $fonts,
        'colors' => $colors,
        'images' => $images,
        'fontUrl' => $fontUrl,
        'footer' => $footer,
        'footer_logo' => $footerLogo,
        'global' => $global,
        'corners' => $cornerStyle,
        'home' => $home,
        'author' => $author,
        'blog' => $blog,
        'search' => $searchConfig,
        'subscription' => $subscriptionConfig,
        'entry' => $entryConfig,
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
        'footer_logo' => 'top',
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
        'search' => [
            'mode' => 'single',
            'position' => 'footer',
            'floating' => 'off',
        ],
        'subscription' => [
            'mode' => 'none',
            'position' => 'footer',
            'floating' => 'off',
        ],
        'entry' => [
            'toc' => [
                'auto' => 'off',
                'min_headings' => 3,
            ],
        ],
    ];
}

function nammu_load_config(): array
{
    $configFile = __DIR__ . '/../config/config.yml';
    if (!is_file($configFile)) {
        return [];
    }

    if (function_exists('yaml_parse_file')) {
        $parsed = @yaml_parse_file($configFile);
        return is_array($parsed) ? $parsed : [];
    }

    $contents = file_get_contents($configFile);
    if ($contents === false) {
        return [];
    }

    return nammu_simple_yaml_parse($contents);
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
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $stack[] = &$current[$key];
            $indentStack[] = $indent + 2;
            continue;
        }

        $current[$key] = nammu_simple_yaml_unescape($value);
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

    $social = array_merge($defaults, $config['social'] ?? []);

    if ($social['home_image'] !== '') {
        $social['home_image'] = nammu_resolve_asset($social['home_image'], '');
    }

    return $social;
}

function nammu_ads_settings(): array
{
    $config = nammu_load_config();
    $defaults = [
        'enabled' => 'off',
        'scope' => 'home',
        'text' => '',
        'image' => '',
    ];
    $ads = array_merge($defaults, $config['ads'] ?? []);
    if (!in_array($ads['scope'], ['home', 'all'], true)) {
        $ads['scope'] = $defaults['scope'];
    }
    return $ads;
}

function nammu_postal_icon_svg(): string
{
    return '<svg width="24" height="24" viewBox="0 0 297 297" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="currentColor" d="M149.999,162.915v120.952c0,7.253,5.74,13.133,12.993,13.133c7.253,0,12.993-5.88,12.993-13.133V162.915h100.813c7.253,0,13.128-6.401,13.128-13.654V74.254c0-19.599-7.78-38.348-21.912-52.364C253.934,7.926,235.386,0,215.783,0H80.675C40.091,0,7.074,33.626,7.074,74.026v75.236c0,7.253,5.88,13.654,13.133,13.654H149.999z M33.06,135.929V74.026c0-25.918,21.376-47.003,47.476-47.003c26.1,0,47.474,21.188,47.474,47.231v61.675H33.06z M263.94,135.929H154.997V74.254c0-18.05-7.285-35.274-18.135-48.267h78.922c25.955,0,48.156,22.51,48.156,48.267V135.929z"/><path fill="currentColor" d="M80.036,58.311c-7.253,0-12.993,5.88-12.993,13.133v1.052c0,7.253,5.74,13.133,12.993,13.133c7.253,0,12.993-5.88,12.993-13.133v-1.052C93.029,64.19,87.289,58.311,80.036,58.311z"/></svg>';
}

function nammu_footer_icon_svgs(): array
{
    return [
        'telegram' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M21.7 5.2a1 1 0 0 0-1.1-.1L3.5 12.1a1 1 0 0 0 .1 1.9l4.7 1.7 1.9 5.1a1 1 0 0 0 1.7.3l2.8-3.2 4.6 3.4a1 1 0 0 0 1.6-.6l2-12.4a1 1 0 0 0-.2-0.7zM9.5 14.8l8-6.4-6.2 7.6-.2 2.8-1.2-3.1-3.5-1.3 11.7-4.6-10.6 5z"/></svg>',
        'whatsapp' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M12 3a9 9 0 0 0-7.7 13.6L3 21l4.6-1.2A9 9 0 1 0 12 3zm4.9 12.4c-.2.5-1 1-1.6 1.1-.4 0-.8.1-2.9-.9-2.3-1.1-3.8-3.3-4-3.6-.3-.4-1-1.3-1-2.2 0-.9.5-1.4.8-1.6.2-.2.5-.2.7-.2h.5c.2 0 .5-.1.8.6.2.5.8 2.2.8 2.3.1.2.1.4 0 .6-.1.2-.2.4-.3.5-.2.2-.3.3-.5.6-.2.2-.3.4-.1.7.2.3.9 1.5 2 2.4 1.4 1.2 2.5 1.5 2.9 1.6.3.1.5.1.7-.1.2-.2.8-.9 1-1.2.2-.3.4-.2.7-.1.2.1 1.6.7 1.9.8.2.1.4.2.4.3.1.1.1.6-.1 1.1z"/></svg>',
        'facebook' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M14 8h3V5h-3c-2.2 0-4 1.8-4 4v3H7v3h3v7h3v-7h3l1-3h-4V9c0-.6.4-1 1-1z"/></svg>',
        'twitter' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M4 4h3.7l4.3 5.2L17 4h3l-6.4 7.4L20 20h-3.7l-4.8-5.9L6.2 20H3l6.9-7.9L4 4z"/></svg>',
        'email' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M4 6h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm0 2v.3l8 5.2 8-5.2V8H4z"/></svg>',
        'postal' => nammu_postal_icon_svg(),
    ];
}

function nammu_build_footer_links(array $config, array $theme, string $baseUrl, string $postalUrl): array
{
    $icons = nammu_footer_icon_svgs();
    $links = [];

    $telegramChannel = trim((string) ($config['telegram']['channel'] ?? ''));
    if ($telegramChannel !== '') {
        $slug = ltrim($telegramChannel, '@');
        if ($slug !== '') {
            $links[] = [
                'label' => 'Telegram',
                'href' => 'https://t.me/' . rawurlencode($slug),
                'svg' => $icons['telegram'],
            ];
        }
    }

    $whatsappTarget = trim((string) ($config['whatsapp']['recipient'] ?? ''));
    if ($whatsappTarget === '') {
        $whatsappTarget = trim((string) ($config['whatsapp']['channel'] ?? ''));
    }
    if ($whatsappTarget !== '') {
        $clean = preg_replace('/\D+/', '', $whatsappTarget);
        if ($clean !== '') {
            $links[] = [
                'label' => 'WhatsApp',
                'href' => 'https://wa.me/' . $clean,
                'svg' => $icons['whatsapp'],
            ];
        }
    }

    $facebookPage = trim((string) ($config['facebook']['channel'] ?? ''));
    if ($facebookPage !== '') {
        $links[] = [
            'label' => 'Facebook',
            'href' => 'https://www.facebook.com/' . rawurlencode($facebookPage),
            'svg' => $icons['facebook'],
        ];
    }

    $twitterHandle = trim((string) ($config['twitter']['channel'] ?? ''));
    if ($twitterHandle !== '') {
        $handle = ltrim($twitterHandle, '@');
        if ($handle !== '') {
            $links[] = [
                'label' => 'X',
                'href' => 'https://twitter.com/' . rawurlencode($handle),
                'svg' => $icons['twitter'],
            ];
        }
    }

    $subscriptionMode = $theme['subscription']['mode'] ?? 'none';
    if (in_array($subscriptionMode, ['home', 'single', 'both'], true)) {
        $links[] = [
            'label' => 'Lista de correo',
            'href' => ($baseUrl !== '' ? rtrim($baseUrl, '/') : '') . '/avisos.php',
            'svg' => $icons['email'],
        ];
    }

    $postalEnabled = ($config['postal']['enabled'] ?? 'off') === 'on';
    if ($postalEnabled) {
        $links[] = [
            'label' => 'Correo postal',
            'href' => $postalUrl !== '' ? $postalUrl : '/correos.php',
            'svg' => $icons['postal'],
        ];
    }

    return $links;
}

function nammu_build_social_meta(array $data, array $socialConfig): array
{
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    if ($description === '') {
        $description = $socialConfig['default_description'] ?? '';
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

function nammu_slugify_label(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    $text = mb_strtolower($text, 'UTF-8');
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text;
}

/**
 * @param Post[] $posts
 * @return array<string, array{name:string, posts:Post[], count:int}>
 */
function nammu_collect_categories_from_posts(array $posts): array
{
    $categories = [];
    foreach ($posts as $post) {
        if (!$post instanceof Post) {
            continue;
        }
        $category = trim($post->getCategory());
        if ($category === '') {
            continue;
        }
        $slug = nammu_slugify_label($category);
        if ($slug === '') {
            continue;
        }
        if (!isset($categories[$slug])) {
            $categories[$slug] = [
                'name' => $category,
                'posts' => [],
                'count' => 0,
                'latest_timestamp' => null,
                'latest_image' => null,
            ];
        }
        $categories[$slug]['posts'][] = $post;
        $categories[$slug]['count']++;

        $date = $post->getDate();
        $timestamp = $date ? $date->getTimestamp() : null;
        if ($timestamp === null) {
            $raw = $post->getRawDate();
            if ($raw) {
                $timestamp = strtotime($raw) ?: null;
            }
        }
        $timestamp ??= 0;
        if ($categories[$slug]['latest_timestamp'] === null || $timestamp >= $categories[$slug]['latest_timestamp']) {
            $categories[$slug]['latest_timestamp'] = $timestamp;
            $categories[$slug]['latest_image'] = $post->getImage();
        }
    }

    foreach ($categories as $slug => &$data) {
        unset($data['latest_timestamp']);
    }
    unset($data);

    return $categories;
}

function nammu_letter_key_from_title(string $title): string
{
    $title = trim($title);
    if ($title === '') {
        return '#';
    }
    $firstChar = mb_substr($title, 0, 1, 'UTF-8');
    $upper = mb_strtoupper($firstChar, 'UTF-8');
    $mapping = [
        'Á' => 'A',
        'À' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'É' => 'E',
        'È' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Í' => 'I',
        'Ì' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ó' => 'O',
        'Ò' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'Ú' => 'U',
        'Ù' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ý' => 'Y',
    ];
    $upper = strtr($upper, $mapping);
    if ($upper === 'Ñ') {
        return 'Ñ';
    }
    if (preg_match('/^[A-Z]$/u', $upper)) {
        return $upper;
    }
    return '#';
}

function nammu_letter_slug(string $letter): string
{
    return $letter === '#' ? 'otros' : strtolower($letter);
}

function nammu_letter_from_slug(string $slug): string
{
    $slug = strtolower($slug);
    if ($slug === 'otros') {
        return '#';
    }
    $char = mb_substr($slug, 0, 1, 'UTF-8');
    $upper = mb_strtoupper($char, 'UTF-8');
    return $upper !== '' ? $upper : '#';
}

function nammu_letter_display_name(string $letter): string
{
    return $letter === '#' ? 'Otros' : $letter;
}

/**
 * @template T
 * @param array<int, T> $items
 * @param callable|null $formatter
 * @return array<string, array<mixed>>
 */
function nammu_group_items_by_letter(array $items, ?callable $formatter = null): array
{
    $groups = [];
    foreach ($items as $item) {
        $title = '';
        if ($item instanceof Post) {
            $title = $item->getTitle();
        } elseif (is_array($item) && isset($item['title'])) {
            $title = (string) $item['title'];
        } elseif (is_object($item) && method_exists($item, 'getTitle')) {
            $title = (string) $item->getTitle();
        }
        $letter = nammu_letter_key_from_title($title);
        if (!isset($groups[$letter])) {
            $groups[$letter] = [];
        }
        $groups[$letter][] = $formatter ? $formatter($item) : $item;
    }

    return nammu_sort_letter_groups($groups);
}

/**
 * @param array<string, array<mixed>> $groups
 * @return array<string, array<mixed>>
 */
function nammu_sort_letter_groups(array $groups): array
{
    $keys = array_keys($groups);
    usort($keys, static function (string $a, string $b): int {
        return nammu_letter_sort_weight($a) <=> nammu_letter_sort_weight($b);
    });
    $sorted = [];
    foreach ($keys as $key) {
        $sorted[$key] = $groups[$key];
    }
    return $sorted;
}

function nammu_letter_sort_weight(string $letter): int
{
    if ($letter === '#') {
        return 1000;
    }
    if ($letter === 'Ñ') {
        return ord('N') + 1;
    }
    $char = $letter[0] ?? 'Z';
    $ord = ord($char);
    if ($ord < ord('A') || $ord > ord('Z')) {
        return 900;
    }
    return $ord;
}

function nammu_itinerary_progress_cookie_name(string $slug): string
{
    $normalized = strtolower($slug);
    $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized);
    $normalized = trim((string) $normalized, '-');
    if ($normalized === '') {
        $normalized = 'general';
    }
    return 'nammu_itinerary_progress_' . $normalized;
}

function nammu_get_itinerary_progress(string $slug): array
{
    $cookieName = nammu_itinerary_progress_cookie_name($slug);
    $raw = $_COOKIE[$cookieName] ?? '';
    $default = [
        'visited' => [],
        'passed' => [],
    ];
    if (!is_string($raw) || trim($raw) === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $default;
    }
    $visited = [];
    foreach ($decoded['visited'] ?? [] as $value) {
        $item = trim((string) $value);
        if ($item !== '') {
            $visited[$item] = true;
        }
    }
    $passed = [];
    foreach ($decoded['passed'] ?? [] as $value) {
        $item = trim((string) $value);
        if ($item !== '') {
            $passed[$item] = true;
        }
    }
    return [
        'visited' => array_keys($visited),
        'passed' => array_keys($passed),
    ];
}

function nammu_set_itinerary_progress(string $slug, array $progress): void
{
    $visited = [];
    foreach ($progress['visited'] ?? [] as $value) {
        $item = trim((string) $value);
        if ($item !== '') {
            $visited[$item] = true;
        }
    }
    $passed = [];
    foreach ($progress['passed'] ?? [] as $value) {
        $item = trim((string) $value);
        if ($item !== '') {
            $passed[$item] = true;
        }
    }
    $payload = json_encode([
        'visited' => array_keys($visited),
        'passed' => array_keys($passed),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return;
    }
    $cookieName = nammu_itinerary_progress_cookie_name($slug);
    setcookie($cookieName, $payload, time() + 31536000, '/', '', false, false);
    $_COOKIE[$cookieName] = $payload;
}
