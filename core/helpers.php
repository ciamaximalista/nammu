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
            'bots' => ['daily' => []],
            'sources' => ['daily' => []],
            'searches' => ['daily' => []],
            'updated_at' => 0,
        ];
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return [
            'visitors' => ['daily' => []],
            'content' => ['posts' => [], 'pages' => []],
            'itineraries' => ['items' => []],
            'bots' => ['daily' => []],
            'searches' => ['daily' => []],
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
            'sources' => ['daily' => []],
            'bots' => ['daily' => []],
            'searches' => ['daily' => []],
            'updated_at' => 0,
        ];
    }
    $decoded['visitors'] = $decoded['visitors'] ?? ['daily' => []];
    $decoded['content'] = $decoded['content'] ?? ['posts' => [], 'pages' => []];
    $decoded['itineraries'] = $decoded['itineraries'] ?? ['items' => []];
    $decoded['platform'] = $decoded['platform'] ?? ['daily' => []];
    $decoded['sources'] = $decoded['sources'] ?? ['daily' => []];
    $decoded['bots'] = $decoded['bots'] ?? ['daily' => []];
    $decoded['searches'] = $decoded['searches'] ?? ['daily' => []];
    $decoded['updated_at'] = (int) ($decoded['updated_at'] ?? 0);
    return $decoded;
}

function nammu_detect_referrer_source(string $referer, string $host): array
{
    $referer = trim($referer);
    if ($referer === '') {
        return ['bucket' => 'direct', 'detail' => ''];
    }
    $refHost = parse_url($referer, PHP_URL_HOST);
    $refHost = $refHost ? strtolower($refHost) : '';
    if ($refHost === '') {
        return ['bucket' => 'direct', 'detail' => ''];
    }
    $host = strtolower($host);
    if ($host !== '' && ($refHost === $host || str_ends_with($refHost, '.' . $host))) {
        return ['bucket' => 'direct', 'detail' => ''];
    }
    $searchEngines = [
        'google.' => 'Google Search',
        'bing.com' => 'Bing',
        'duckduckgo.com' => 'DuckDuckGo',
        'yahoo.' => 'Yahoo',
        'yandex.' => 'Yandex',
        'baidu.' => 'Baidu',
        'ecosia.org' => 'Ecosia',
        'startpage.com' => 'Startpage',
    ];
    foreach ($searchEngines as $needle => $label) {
        if (str_contains($refHost, $needle)) {
            return ['bucket' => 'search', 'detail' => $label];
        }
    }
    $socialDomains = [
        't.me' => 'Telegram',
        'telegram.me' => 'Telegram',
        'facebook.com' => 'Facebook',
        'fb.com' => 'Facebook',
        'instagram.com' => 'Instagram',
        'twitter.com' => 'Twitter/X',
        'x.com' => 'Twitter/X',
        't.co' => 'Twitter/X',
        'bsky.app' => 'Bluesky',
        'go.bsky.app' => 'Bluesky',
        'bsky.social' => 'Bluesky',
        'mastodon.' => 'Mastodon',
        'mstdn.' => 'Mastodon',
        'linkedin.com' => 'LinkedIn',
        'pinterest.' => 'Pinterest',
    ];
    foreach ($socialDomains as $needle => $label) {
        if (str_contains($refHost, $needle)) {
            return ['bucket' => 'social', 'detail' => $label];
        }
    }
    $mailDomains = [
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
    foreach ($mailDomains as $needle) {
        if (str_contains($refHost, $needle)) {
            return ['bucket' => 'email', 'detail' => 'Reenvios'];
        }
    }
    if (str_starts_with($refHost, 'www.')) {
        $refHost = substr($refHost, 4);
    }
    return [
        'bucket' => 'other',
        'detail' => $refHost !== '' ? $refHost : 'Sitios web',
        'url' => $referer,
    ];
}

function nammu_detect_user_agent_source(string $userAgent): array
{
    $userAgent = strtolower(trim($userAgent));
    if ($userAgent === '') {
        return ['bucket' => '', 'detail' => ''];
    }
    $uaSocial = [
        'telegram' => 'Telegram',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'fbav' => 'Facebook',
        'fban' => 'Facebook',
        'twitter' => 'Twitter/X',
        'x.com' => 'Twitter/X',
        'bluesky' => 'Bluesky',
        'bsky' => 'Bluesky',
        'mastodon' => 'Mastodon',
        'mstdn' => 'Mastodon',
    ];
    foreach ($uaSocial as $needle => $label) {
        if (str_contains($userAgent, $needle)) {
            return ['bucket' => 'social', 'detail' => $label];
        }
    }
    return ['bucket' => '', 'detail' => ''];
}

function nammu_detect_bot_name(string $userAgent): string
{
    $ua = strtolower(trim($userAgent));
    if ($ua === '') {
        return '';
    }
    $bots = [
        'googlebot' => 'Googlebot',
        'bingbot' => 'Bingbot',
        'yandexbot' => 'YandexBot',
        'duckduckbot' => 'DuckDuckBot',
        'baiduspider' => 'Baiduspider',
        'sogou' => 'Sogou',
        'slurp' => 'Yahoo Slurp',
        'facebookexternalhit' => 'Facebook',
        'facebot' => 'Facebook',
        'twitterbot' => 'Twitter/X',
        'pinterest' => 'Pinterest',
        'linkedinbot' => 'LinkedIn',
        'telegram' => 'Telegram',
        'bot' => 'Bot',
        'crawl' => 'Crawler',
        'spider' => 'Spider',
    ];
    foreach ($bots as $needle => $label) {
        if (str_contains($ua, $needle)) {
            return $label;
        }
    }
    return '';
}

function nammu_record_bot_visit(string $userAgent): void
{
    $botName = nammu_detect_bot_name($userAgent);
    if ($botName === '') {
        return;
    }
    $data = nammu_load_analytics();
    $date = date('Y-m-d');
    if (!isset($data['bots']['daily'][$date])) {
        $data['bots']['daily'][$date] = [];
    }
    if (!isset($data['bots']['daily'][$date][$botName])) {
        $data['bots']['daily'][$date][$botName] = ['count' => 0];
    }
    $data['bots']['daily'][$date][$botName]['count'] = (int) ($data['bots']['daily'][$date][$botName]['count'] ?? 0) + 1;
    $data['updated_at'] = time();
    nammu_save_analytics($data);
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
    if (!isset($bucket['device'])) {
        $bucket['device'] = [];
    }
    foreach (['desktop', 'mobile', 'tablet'] as $fallbackDevice) {
        if (!isset($bucket['device'][$fallbackDevice])) {
            $bucket['device'][$fallbackDevice] = ['uids' => []];
        }
    }

    if (!isset($bucket['browser'])) {
        $bucket['browser'] = [];
    }
    $setUid($bucket['browser'], $browser, $uid);
    if (!isset($bucket['browser'])) {
        $bucket['browser'] = [];
    }
    if (!isset($bucket['browser']['otros'])) {
        $bucket['browser']['otros'] = ['uids' => []];
    }

    if ($language !== '') {
        if (!isset($bucket['language'])) {
            $bucket['language'] = [];
        }
        $setUid($bucket['language'], $language, $uid);
    } else {
        if (!isset($bucket['language'])) {
            $bucket['language'] = [];
        }
        $setUid($bucket['language'], 'otros', $uid);
    }

    if ($device === 'desktop') {
        $os = nammu_detect_os($ua);
        if (!isset($bucket['os'])) {
            $bucket['os'] = [];
        }
        $setUid($bucket['os'], $os, $uid);
        if (!isset($bucket['os']['otros'])) {
            $bucket['os']['otros'] = ['uids' => []];
        }
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
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isSelfReferrer = false;
    if ($referrer !== '') {
        $refHost = parse_url($referrer, PHP_URL_HOST);
        $refHost = $refHost ? strtolower($refHost) : '';
        $hostLower = strtolower($host);
        if ($refHost !== '' && $hostLower !== '' && ($refHost === $hostLower || str_ends_with($refHost, '.' . $hostLower))) {
            $isSelfReferrer = true;
        }
    }
    if (($referrer === '' || $isSelfReferrer) && isset($_COOKIE['nammu_stats_referrer'])) {
        $storedRef = trim((string) $_COOKIE['nammu_stats_referrer']);
        if ($storedRef !== '') {
            $decodedRef = urldecode($storedRef);
            if ($decodedRef !== '') {
                $referrer = $decodedRef;
            }
        }
        setcookie('nammu_stats_referrer', '', time() - 3600, '/');
        unset($_COOKIE['nammu_stats_referrer']);
    }
    $utmSource = strtolower(trim((string) ($_GET['utm_source'] ?? '')));
    $utmMedium = strtolower(trim((string) ($_GET['utm_medium'] ?? '')));
    $sourceMap = [
        'email' => 'Suscriptores',
        'correo' => 'Suscriptores',
        'mail' => 'Suscriptores',
        'newsletter' => 'Newsletter',
        'avisos' => 'Suscriptores',
        'push' => 'Notificaciones push',
        'webpush' => 'Notificaciones push',
        'push_notification' => 'Notificaciones push',
        'telegram' => 'Telegram',
        't.me' => 'Telegram',
        'tg' => 'Telegram',
        'instagram' => 'Instagram',
        'ig' => 'Instagram',
        'facebook' => 'Facebook',
        'fb' => 'Facebook',
        'facebook.com' => 'Facebook',
        'twitter' => 'Twitter/X',
        'x' => 'Twitter/X',
        't.co' => 'Twitter/X',
        'mastodon' => 'Mastodon',
        'linkedin' => 'LinkedIn',
        'lnkd' => 'LinkedIn',
        'pinterest' => 'Pinterest',
        'pin' => 'Pinterest',
        'reddit' => 'Reddit',
        'tiktok' => 'TikTok',
        'yt' => 'YouTube',
        'youtube' => 'YouTube',
        'google' => 'Google Search',
        'google_search' => 'Google Search',
        'googleorganic' => 'Google Search',
        'bing' => 'Bing',
        'duckduckgo' => 'DuckDuckGo',
        'ddg' => 'DuckDuckGo',
        'yahoo' => 'Yahoo',
        'yandex' => 'Yandex',
        'baidu' => 'Baidu',
        'ecosia' => 'Ecosia',
        'startpage' => 'Startpage',
        'search' => 'Google Search',
        'organic' => 'Google Search',
    ];
    $utmDetail = '';
    foreach ([$utmSource, $utmMedium] as $utmValue) {
        if ($utmValue === '') {
            continue;
        }
        if (isset($sourceMap[$utmValue])) {
            $utmDetail = $sourceMap[$utmValue];
            break;
        }
    }

    if ($utmDetail !== '') {
        $bucket = 'other';
        if (in_array($utmDetail, ['Suscriptores', 'Newsletter', 'Reenvios'], true)) {
            $bucket = 'email';
        } elseif ($utmDetail === 'Notificaciones push') {
            $bucket = 'push';
        } elseif (in_array($utmDetail, ['Google Search', 'Bing', 'DuckDuckGo', 'Yahoo', 'Yandex', 'Baidu', 'Ecosia', 'Startpage'], true)) {
            $bucket = 'search';
        } elseif (in_array($utmDetail, ['Telegram', 'Instagram', 'Facebook', 'Twitter/X', 'Mastodon', 'LinkedIn', 'Pinterest', 'Reddit', 'TikTok', 'YouTube'], true)) {
            $bucket = 'social';
        }
        $source = ['bucket' => $bucket, 'detail' => $utmDetail];
    } else {
        $source = nammu_detect_referrer_source($referrer, $host);
    }
    if (($source['bucket'] ?? '') === 'direct') {
        $uaSource = nammu_detect_user_agent_source($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (($uaSource['bucket'] ?? '') !== '') {
            $source = $uaSource;
        }
    }
    if (!isset($data['sources']['daily'][$date])) {
        $data['sources']['daily'][$date] = [];
    }
    $bucket = $source['bucket'] ?? 'other';
    $detail = $source['detail'] ?? '';
    $detailUrl = '';
    if ($bucket === 'other') {
        $detailUrl = trim((string) ($source['url'] ?? ''));
    }
    if (!isset($data['sources']['daily'][$date][$bucket]['uids'])) {
        $data['sources']['daily'][$date][$bucket]['uids'] = [];
    }
    if (!isset($data['sources']['daily'][$date][$bucket]['uids'][$uid])) {
        $data['sources']['daily'][$date][$bucket]['uids'][$uid] = 1;
        $changed = true;
    }
    if ($detail !== '' && in_array($bucket, ['search', 'social', 'other', 'email'], true)) {
        if (!isset($data['sources']['daily'][$date][$bucket]['detail'])) {
            $data['sources']['daily'][$date][$bucket]['detail'] = [];
        }
        if (!isset($data['sources']['daily'][$date][$bucket]['detail'][$detail]) || !is_array($data['sources']['daily'][$date][$bucket]['detail'][$detail])) {
            $data['sources']['daily'][$date][$bucket]['detail'][$detail] = [];
        }
        if (!isset($data['sources']['daily'][$date][$bucket]['detail'][$detail]['uids'])) {
            $data['sources']['daily'][$date][$bucket]['detail'][$detail]['uids'] = [];
        }
        if ($detailUrl !== '') {
            $data['sources']['daily'][$date][$bucket]['detail'][$detail]['url'] = $detailUrl;
        }
        if (!isset($data['sources']['daily'][$date][$bucket]['detail'][$detail]['uids'][$uid])) {
            $data['sources']['daily'][$date][$bucket]['detail'][$detail]['uids'][$uid] = 1;
            $changed = true;
        }
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
        $data['content'][$bucket][$slug]['daily'][$date] = [
            'views' => 0,
            'uids' => [],
        ];
    }
    $dailyEntry = $data['content'][$bucket][$slug]['daily'][$date];
    if (!is_array($dailyEntry)) {
        $dailyEntry = [
            'views' => (int) $dailyEntry,
            'uids' => [],
        ];
    }
    $dailyEntry['views'] = (int) ($dailyEntry['views'] ?? 0) + 1;
    if (!isset($dailyEntry['uids']) || !is_array($dailyEntry['uids'])) {
        $dailyEntry['uids'] = [];
    }
    $dailyEntry['uids'][$uid] = 1;
    $data['content'][$bucket][$slug]['daily'][$date] = $dailyEntry;
    $data['updated_at'] = time();
    nammu_save_analytics($data);
}

function nammu_record_internal_search(string $query): void
{
    if (!nammu_has_stats_consent()) {
        return;
    }
    $uid = nammu_stats_uid();
    if ($uid === null) {
        return;
    }
    $query = trim($query);
    if ($query === '') {
        return;
    }
    if (function_exists('mb_strtolower')) {
        $query = mb_strtolower($query, 'UTF-8');
    } else {
        $query = strtolower($query);
    }
    $query = preg_replace('/\s+/u', ' ', $query) ?? $query;
    if ($query === '') {
        return;
    }
    if (function_exists('mb_substr')) {
        $query = mb_substr($query, 0, 120, 'UTF-8');
    } else {
        $query = substr($query, 0, 120);
    }
    $data = nammu_load_analytics();
    $date = date('Y-m-d');
    $changed = nammu_analytics_touch_visit($data, $uid, $date);
    if (!isset($data['searches']['daily'][$date])) {
        $data['searches']['daily'][$date] = [];
    }
    if (!isset($data['searches']['daily'][$date][$query])) {
        $data['searches']['daily'][$date][$query] = [
            'count' => 0,
            'uids' => [],
        ];
    }
    $data['searches']['daily'][$date][$query]['count'] = (int) ($data['searches']['daily'][$date][$query]['count'] ?? 0) + 1;
    $changed = true;
    if (!isset($data['searches']['daily'][$date][$query]['uids']) || !is_array($data['searches']['daily'][$date][$query]['uids'])) {
        $data['searches']['daily'][$date][$query]['uids'] = [];
    }
    if (!isset($data['searches']['daily'][$date][$query]['uids'][$uid])) {
        $data['searches']['daily'][$date][$query]['uids'][$uid] = 1;
        $changed = true;
    }
    if ($changed) {
        $data['updated_at'] = time();
        nammu_save_analytics($data);
    }
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
    $footerNammu = $template['footer_nammu'] ?? ($defaults['footer_nammu'] ?? 'on');
    if (!in_array($footerNammu, ['on', 'off'], true)) {
        $footerNammu = $defaults['footer_nammu'] ?? 'on';
    }
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
    $home['dictionary_intro'] = (string) ($home['dictionary_intro'] ?? '');
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
    $lang = $config['site_lang'] ?? 'es';
    if (!is_string($lang) || $lang === '') {
        $lang = 'es';
    }

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
        'footer_nammu' => $footerNammu,
        'global' => $global,
        'corners' => $cornerStyle,
        'home' => $home,
        'author' => $author,
        'blog' => $blog,
        'lang' => $lang,
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
        'footer_nammu' => 'on',
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
            'dictionary_intro' => '',
            'header_buttons' => 'none',
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
        'link' => '',
        'link_label' => '',
        'push_enabled' => 'off',
        'push_posts' => 'off',
        'push_itineraries' => 'off',
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

function nammu_push_vapid_file(): string
{
    return dirname(__DIR__) . '/config/push-vapid.json';
}

function nammu_push_subscriptions_file(): string
{
    return dirname(__DIR__) . '/config/push-subscriptions.json';
}

function nammu_push_queue_file(): string
{
    return dirname(__DIR__) . '/config/push-queue.json';
}

function nammu_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function nammu_base64url_decode(string $data): string
{
    $padded = strtr($data, '-_', '+/');
    $padding = strlen($padded) % 4;
    if ($padding > 0) {
        $padded .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($padded, true);
    return $decoded === false ? '' : $decoded;
}

function nammu_ensure_push_vapid_keys(): array
{
    $file = nammu_push_vapid_file();
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded) && !empty($decoded['public_key'])) {
            return $decoded;
        }
    }

    if (!function_exists('openssl_pkey_new')) {
        return [];
    }

    $resource = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    if ($resource === false) {
        return [];
    }

    $privatePem = '';
    openssl_pkey_export($resource, $privatePem);
    $details = openssl_pkey_get_details($resource);
    $ec = $details['ec'] ?? null;
    if (!is_array($ec) || empty($ec['x']) || empty($ec['y'])) {
        return [];
    }

    $publicRaw = "\x04" . $ec['x'] . $ec['y'];
    $publicKey = nammu_base64url_encode($publicRaw);
    $privateKey = isset($ec['d']) ? nammu_base64url_encode($ec['d']) : '';
    $payload = [
        'public_key' => $publicKey,
        'private_key' => $privateKey,
        'private_key_pem' => $privatePem,
        'created_at' => date('c'),
    ];

    nammu_ensure_directory(dirname($file));
    @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    return $payload;
}

function nammu_push_public_key(): string
{
    $keys = nammu_ensure_push_vapid_keys();
    return is_array($keys) ? (string) ($keys['public_key'] ?? '') : '';
}

function nammu_load_push_subscriptions(): array
{
    $file = nammu_push_subscriptions_file();
    if (!is_file($file)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function nammu_load_push_queue(): array
{
    $file = nammu_push_queue_file();
    if (!is_file($file)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function nammu_push_queue_count(): int
{
    return count(nammu_load_push_queue());
}

function nammu_push_subscriber_count(): int
{
    return count(nammu_load_push_subscriptions());
}

function nammu_push_dependencies_status(): array
{
    $hasWebPush = class_exists('\\Minishlink\\WebPush\\WebPush');
    $hasOpenssl = function_exists('openssl_pkey_new');
    $ok = $hasWebPush && $hasOpenssl;
    $message = '';
    if (!$hasWebPush) {
        $message = 'Faltan dependencias de Web Push (composer).';
    } elseif (!$hasOpenssl) {
        $message = 'Falta la extensión OpenSSL de PHP.';
    }
    return [
        'ok' => $ok,
        'has_webpush' => $hasWebPush,
        'has_openssl' => $hasOpenssl,
        'message' => $message,
    ];
}

function nammu_enqueue_push_notification(array $payload): void
{
    if (empty($payload['title']) || empty($payload['url'])) {
        return;
    }
    $queue = nammu_load_push_queue();
    $queue[] = [
        'title' => (string) $payload['title'],
        'body' => (string) ($payload['body'] ?? ''),
        'url' => (string) $payload['url'],
        'icon' => (string) ($payload['icon'] ?? ''),
        'badge' => (string) ($payload['badge'] ?? ''),
        'created_at' => date('c'),
    ];
    $file = nammu_push_queue_file();
    nammu_ensure_directory(dirname($file));
    file_put_contents($file, json_encode($queue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function nammu_save_push_subscriptions(array $subscriptions): void
{
    $file = nammu_push_subscriptions_file();
    nammu_ensure_directory(dirname($file));
    file_put_contents($file, json_encode($subscriptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function nammu_store_push_subscription(array $subscription): bool
{
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    $keys = $subscription['keys'] ?? [];
    if (!is_array($keys)) {
        $keys = [];
    }
    $p256dh = trim((string) ($keys['p256dh'] ?? ($subscription['p256dh'] ?? '')));
    $auth = trim((string) ($keys['auth'] ?? ($subscription['auth'] ?? '')));
    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        return false;
    }
    $subscriptions = nammu_load_push_subscriptions();
    $id = hash('sha256', $endpoint);
    $subscriptions[$id] = [
        'endpoint' => $endpoint,
        'keys' => [
            'p256dh' => $p256dh,
            'auth' => $auth,
        ],
        'updated_at' => date('c'),
    ];
    nammu_save_push_subscriptions($subscriptions);
    return true;
}

function nammu_webpush_client(): ?object
{
    if (!class_exists('\\Minishlink\\WebPush\\WebPush')) {
        return null;
    }
    $keys = nammu_ensure_push_vapid_keys();
    $publicKey = trim((string) ($keys['public_key'] ?? ''));
    $privateKey = trim((string) ($keys['private_key'] ?? ''));
    if ($privateKey === '' && !empty($keys['private_key_pem'])) {
        $privateKey = (string) $keys['private_key_pem'];
    }
    if ($publicKey === '' || $privateKey === '') {
        return null;
    }
    $config = nammu_load_config();
    $subject = $config['site_url'] ?? '';
    if (!is_string($subject) || $subject === '') {
        $subject = nammu_base_url();
    }
    if (!is_string($subject) || $subject === '') {
        $subject = 'mailto:admin@localhost';
    }
    $auth = [
        'VAPID' => [
            'subject' => $subject,
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ],
    ];

    return new \Minishlink\WebPush\WebPush($auth);
}

function nammu_send_push_notification(array $payload): array
{
    $client = nammu_webpush_client();
    if ($client === null) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true];
    }
    $subscriptions = nammu_load_push_subscriptions();
    if (empty($subscriptions)) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true];
    }

    $title = trim((string) ($payload['title'] ?? ''));
    if ($title === '') {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true];
    }
    $baseUrl = nammu_base_url();
    $url = (string) ($payload['url'] ?? '');
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }
    $icon = (string) ($payload['icon'] ?? '');
    if ($icon !== '' && !preg_match('#^https?://#i', $icon)) {
        $icon = rtrim($baseUrl, '/') . '/' . ltrim($icon, '/');
    }
    if ($icon === '') {
        $icon = rtrim($baseUrl, '/') . '/nammu.png';
    }
    $body = (string) ($payload['body'] ?? '');
    $data = [
        'title' => $title,
        'body' => $body,
        'url' => $url !== '' ? $url : ($baseUrl !== '' ? $baseUrl : '/'),
        'icon' => $icon,
        'badge' => $icon,
    ];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $sent = 0;
    $failed = 0;
    $removed = 0;
    $toRemove = [];

    foreach ($subscriptions as $id => $subscriptionData) {
        $endpoint = (string) ($subscriptionData['endpoint'] ?? '');
        $keys = $subscriptionData['keys'] ?? [];
        $p256dh = (string) ($keys['p256dh'] ?? '');
        $auth = (string) ($keys['auth'] ?? '');
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            continue;
        }
        $subscription = \Minishlink\WebPush\Subscription::create([
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => $p256dh,
                'auth' => $auth,
            ],
        ]);
        $report = $client->sendOneNotification($subscription, $json);
        if ($report->isSuccess()) {
            $sent++;
            continue;
        }
        $failed++;
        $response = $report->getResponse();
        $status = $response ? $response->getStatusCode() : 0;
        if ($status === 404 || $status === 410) {
            $toRemove[] = $id;
        }
    }

    if (!empty($toRemove)) {
        foreach ($toRemove as $id) {
            unset($subscriptions[$id]);
            $removed++;
        }
        nammu_save_push_subscriptions($subscriptions);
    }

    return ['sent' => $sent, 'failed' => $failed, 'removed' => $removed];
}

function nammu_dispatch_push_queue(): array
{
    $queue = nammu_load_push_queue();
    if (empty($queue)) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true];
    }
    $summary = ['sent' => 0, 'failed' => 0, 'skipped' => false];
    foreach ($queue as $payload) {
        $result = nammu_send_push_notification(is_array($payload) ? $payload : []);
        $summary['sent'] += (int) ($result['sent'] ?? 0);
        $summary['failed'] += (int) ($result['failed'] ?? 0);
        if (!empty($result['skipped'])) {
            $summary['skipped'] = true;
        }
    }
    if (empty($summary['skipped'])) {
        $file = nammu_push_queue_file();
        @file_put_contents($file, json_encode([], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    return $summary;
}

function nammu_remove_push_subscription(string $endpoint): void
{
    $endpoint = trim($endpoint);
    if ($endpoint === '') {
        return;
    }
    $subscriptions = nammu_load_push_subscriptions();
    $id = hash('sha256', $endpoint);
    if (isset($subscriptions[$id])) {
        unset($subscriptions[$id]);
        nammu_save_push_subscriptions($subscriptions);
    }
}

function nammu_footer_icon_svgs(): array
{
    return [
        'telegram' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M21.7 5.2a1 1 0 0 0-1.1-.1L3.5 12.1a1 1 0 0 0 .1 1.9l4.7 1.7 1.9 5.1a1 1 0 0 0 1.7.3l2.8-3.2 4.6 3.4a1 1 0 0 0 1.6-.6l2-12.4a1 1 0 0 0-.2-0.7zM9.5 14.8l8-6.4-6.2 7.6-.2 2.8-1.2-3.1-3.5-1.3 11.7-4.6-10.6 5z"/></svg>',
        'facebook' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M14 8h3V5h-3c-2.2 0-4 1.8-4 4v3H7v3h3v7h3v-7h3l1-3h-4V9c0-.6.4-1 1-1z"/></svg>',
        'instagram' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4zm9.75 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>',
        'twitter' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M4 4h3.7l4.3 5.2L17 4h3l-6.4 7.4L20 20h-3.7l-4.8-5.9L6.2 20H3l6.9-7.9L4 4z"/></svg>',
        'bluesky' => '<svg width="20" height="20" viewBox="0 0 600 600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="m135.72 44.03c66.496 49.921 138.02 151.14 164.28 205.46 26.262-54.316 97.782-155.54 164.28-205.46 47.98-36.021 125.72-63.892 125.72 24.795 0 17.712-10.155 148.79-16.111 170.07-20.703 73.984-96.144 92.854-163.25 81.433 117.3 19.964 147.14 86.092 82.697 152.22-122.39 125.59-175.91-31.511-189.63-71.766-2.514-7.3797-3.6904-10.832-3.7077-7.8964-0.0174-2.9357-1.1937 0.51669-3.7077 7.8964-13.714 40.255-67.233 197.36-189.63 71.766-64.444-66.128-34.605-132.26 82.697-152.22-67.108 11.421-142.55-7.4491-163.25-81.433-5.9562-21.282-16.111-152.36-16.111-170.07 0-88.687 77.742-60.816 125.72-24.795z" fill="currentColor"/></svg>',
        'mastodon' => '<svg width="20" height="20" viewBox="-20 -20 520 551.476" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality"><defs><mask id="mastodon-mask"><rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="M396.545 174.981v136.53h-54.104V179.002c0-27.896-11.625-42.124-35.272-42.124-25.996 0-39.017 16.833-39.017 50.074v72.531h-53.777v-72.531c0-33.241-13.044-50.074-39.04-50.074-23.507 0-35.248 14.228-35.248 42.124v132.509H86.006v-136.53c0-27.896 7.123-50.059 21.366-66.488 14.695-16.387 33.97-24.803 57.896-24.803 27.691 0 48.617 10.647 62.568 31.917l13.464 22.597 13.484-22.597c13.951-21.27 34.877-31.917 62.521-31.917 23.902 0 43.177 8.416 57.919 24.803 14.231 16.414 21.336 38.577 21.321 66.488z"/></mask></defs><path fill="currentColor" mask="url(#mastodon-mask)" d="M478.064 113.237c-7.393-54.954-55.29-98.266-112.071-106.656C356.413 5.163 320.121 0 236.045 0h-.628c-84.1 0-102.141 5.163-111.72 6.581C68.498 14.739 18.088 53.655 5.859 109.261c-5.883 27.385-6.51 57.747-5.416 85.596 1.555 39.939 1.859 79.806 5.487 119.581a562.694 562.694 0 0013.089 78.437c11.625 47.654 58.687 87.313 104.793 103.494a281.073 281.073 0 00153.316 8.09 224.345 224.345 0 0016.577-4.533c12.369-3.928 26.856-8.321 37.506-16.042.146-.107.265-.247.348-.407.086-.161.134-.339.14-.521v-38.543a1.187 1.187 0 00-.119-.491 1.122 1.122 0 00-.773-.604 1.139 1.139 0 00-.503 0 424.932 424.932 0 01-99.491 11.626c-57.664 0-73.171-27.361-77.611-38.752a120.09 120.09 0 01-6.745-30.546 1.123 1.123 0 01.877-1.152c.173-.035.349-.032.518.012a416.876 416.876 0 0097.864 11.623c7.929 0 15.834 0 23.763-.211 33.155-.928 68.103-2.626 100.722-8.997.815-.16 1.63-.3 2.326-.508 51.454-9.883 100.422-40.894 105.397-119.42.185-3.093.651-32.385.651-35.591.022-10.903 3.51-77.343-.511-118.165z"/></svg>',
        'email' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M4 6h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm0 2v.3l8 5.2 8-5.2V8H4z"/></svg>',
        'postal' => nammu_postal_icon_svg(),
        'rss' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M6 18a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm-2-8v-3a11 11 0 0 1 11 11h-3a8 8 0 0 0-8-8zm0-6V1a17 17 0 0 1 17 17h-3a14 14 0 0 0-14-14z"/></svg>',
        'spotify' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>',
        'ivoox' => '<svg width="20" height="20" viewBox="0 0 512 512" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><image href="data:image/png;base64,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" width="512" height="512" preserveAspectRatio="xMidYMid meet"/></svg>',
        'apple' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M5.34 0A5.328 5.328 0 000 5.34v13.32A5.328 5.328 0 005.34 24h13.32A5.328 5.328 0 0024 18.66V5.34A5.328 5.328 0 0018.66 0zm6.525 2.568c2.336 0 4.448.902 6.056 2.587 1.224 1.272 1.912 2.619 2.264 4.392.12.59.12 2.2.007 2.864a8.506 8.506 0 01-3.24 5.296c-.608.46-2.096 1.261-2.336 1.261-.088 0-.096-.091-.056-.46.072-.592.144-.715.48-.856.536-.224 1.448-.874 2.008-1.435a7.644 7.644 0 002.008-3.536c.208-.824.184-2.656-.048-3.504-.728-2.696-2.928-4.792-5.624-5.352-.784-.16-2.208-.16-3 0-2.728.56-4.984 2.76-5.672 5.528-.184.752-.184 2.584 0 3.336.456 1.832 1.64 3.512 3.192 4.512.304.2.672.408.824.472.336.144.408.264.472.856.04.36.03.464-.056.464-.056 0-.464-.176-.896-.384l-.04-.03c-2.472-1.216-4.056-3.274-4.632-6.012-.144-.706-.168-2.392-.03-3.04.36-1.74 1.048-3.1 2.192-4.304 1.648-1.737 3.768-2.656 6.128-2.656zm.134 2.81c.409.004.803.04 1.106.106 2.784.62 4.76 3.408 4.376 6.174-.152 1.114-.536 2.03-1.216 2.88-.336.43-1.152 1.15-1.296 1.15-.023 0-.048-.272-.048-.603v-.605l.416-.496c1.568-1.878 1.456-4.502-.256-6.224-.664-.67-1.432-1.064-2.424-1.246-.64-.118-.776-.118-1.448-.008-1.02.167-1.81.562-2.512 1.256-1.72 1.704-1.832 4.342-.264 6.222l.413.496v.608c0 .336-.027.608-.06.608-.03 0-.264-.16-.512-.36l-.034-.011c-.832-.664-1.568-1.842-1.872-2.997-.184-.698-.184-2.024.008-2.72.504-1.878 1.888-3.335 3.808-4.019.41-.145 1.133-.22 1.814-.211zm-.13 2.99c.31 0 .62.06.844.178.488.253.888.745 1.04 1.259.464 1.578-1.208 2.96-2.72 2.254h-.015c-.712-.331-1.096-.956-1.104-1.77 0-.733.408-1.371 1.112-1.745.224-.117.534-.176.844-.176zm-.011 4.728c.988-.004 1.706.349 1.97.97.198.464.124 1.932-.218 4.302-.232 1.656-.36 2.074-.68 2.356-.44.39-1.064.498-1.656.288h-.003c-.716-.257-.87-.605-1.164-2.644-.341-2.37-.416-3.838-.218-4.302.262-.616.974-.966 1.97-.97z"/></svg>',
        'google' => '<svg width="20" height="20" viewBox="0 0 512 512" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" stroke-linecap="round" stroke-linejoin="round" stroke-width="43"><rect width="512" height="512" rx="15%" fill="#fff"/><path stroke="#fab908" d="M256 109v22zM256 381v22zM256 195v122"/><path stroke="#ea4335" d="M181 176v75zM181 315v21z"/><path stroke="#34a853" d="M331 261v75zM331 197v-21z"/><path stroke="#4285f4" d="M405 245v22"/><path stroke="#0066d9" d="M107 245v22"/></svg>',
        'youtube_music' => '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M12 0C5.376 0 0 5.376 0 12s5.376 12 12 12 12-5.376 12-12S18.624 0 12 0zm0 19.104c-3.924 0-7.104-3.18-7.104-7.104S8.076 4.896 12 4.896s7.104 3.18 7.104 7.104-3.18 7.104-7.104 7.104zm0-13.332c-3.432 0-6.228 2.796-6.228 6.228S8.568 18.228 12 18.228s6.228-2.796 6.228-6.228S15.432 5.772 12 5.772zM9.684 15.54V8.46L15.816 12l-6.132 3.54z"/></svg>',
    ];
}

function nammu_build_footer_links(array $config, array $theme, string $baseUrl, string $postalUrl, bool $hasItineraries = false, bool $hasPodcast = false): array
{
    $icons = nammu_footer_icon_svgs();
    $links = [];
    $normalizeExternalUrl = static function (string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        return 'https://' . ltrim($value, '/');
    };
    $baseRoot = $baseUrl !== '' ? rtrim($baseUrl, '/') : '';
    $links[] = [
        'label' => 'RSS',
        'href' => $baseRoot . '/rss.xml',
        'svg' => $icons['rss'],
    ];
    if ($hasItineraries) {
        $links[] = [
            'label' => 'RSS Itinerarios',
            'href' => $baseRoot . '/itinerarios.xml',
            'svg' => $icons['rss'],
        ];
    }
    if ($hasPodcast) {
        $links[] = [
            'label' => 'RSS Podcast',
            'href' => $baseRoot . '/podcast.xml',
            'svg' => $icons['rss'],
        ];
    }

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

    $facebookPage = trim((string) ($config['facebook']['channel'] ?? ''));
    if ($facebookPage !== '') {
        $links[] = [
            'label' => 'Facebook',
            'href' => 'https://www.facebook.com/' . rawurlencode($facebookPage),
            'svg' => $icons['facebook'],
        ];
    }

    $instagramProfile = trim((string) ($config['instagram']['profile'] ?? ''));
    $instagramChannel = trim((string) ($config['instagram']['channel'] ?? ''));
    $instagramToken = trim((string) ($config['instagram']['token'] ?? ''));
    if ($instagramProfile !== '' || $instagramChannel !== '' || $instagramToken !== '') {
        $instagramHref = 'https://www.instagram.com/';
        $instagramLabel = 'Instagram';
        if ($instagramProfile !== '') {
            if (preg_match('#^https?://#i', $instagramProfile)) {
                $instagramHref = $instagramProfile;
            } else {
                $instagramHandle = ltrim($instagramProfile, '@');
                if ($instagramHandle !== '') {
                    $instagramHref = 'https://www.instagram.com/' . rawurlencode($instagramHandle) . '/';
                    $instagramLabel = 'Instagram (@' . $instagramHandle . ')';
                }
            }
        } elseif ($instagramChannel !== '' && preg_match('/^@?[A-Za-z0-9._]+$/', $instagramChannel)) {
            $instagramHandle = ltrim($instagramChannel, '@');
            if ($instagramHandle !== '') {
                $instagramHref = 'https://www.instagram.com/' . rawurlencode($instagramHandle) . '/';
                $instagramLabel = 'Instagram (@' . $instagramHandle . ')';
            }
        }
        $links[] = [
            'label' => $instagramLabel,
            'href' => $instagramHref,
            'svg' => $icons['instagram'],
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

    $blueskyHandle = trim((string) ($config['bluesky']['identifier'] ?? ''));
    if ($blueskyHandle !== '') {
        $handle = ltrim($blueskyHandle, '@');
        $handle = preg_replace('/[\\p{Cf}\\p{Z}\\s]+/u', '', $handle);
        if ($handle !== '') {
            $links[] = [
                'label' => 'Bluesky',
                'href' => 'https://bsky.app/profile/' . rawurlencode($handle),
                'svg' => $icons['bluesky'],
            ];
        }
    }

    $mastodonProfile = trim((string) ($config['mastodon']['profile'] ?? ''));
    $mastodonHandle = trim((string) ($config['mastodon']['handle'] ?? ''));
    $mastodonInstance = trim((string) ($config['mastodon']['instance'] ?? ''));
    $mastodonUrl = '';
    if ($mastodonProfile !== '') {
        $mastodonUrl = $normalizeExternalUrl($mastodonProfile);
    } else {
        if ($mastodonInstance === '' && str_contains($mastodonHandle, '@')) {
            $parts = explode('@', ltrim($mastodonHandle, '@'));
            if (count($parts) >= 2) {
                $mastodonHandle = $parts[0];
                $mastodonInstance = $parts[1];
            }
        }
        $mastodonHandle = ltrim($mastodonHandle, '@');
        if ($mastodonHandle !== '' && $mastodonInstance !== '') {
            $mastodonUrl = rtrim($normalizeExternalUrl($mastodonInstance), '/') . '/@' . rawurlencode($mastodonHandle);
        }
    }
    if ($mastodonUrl !== '') {
        $links[] = [
            'label' => 'Mastodon',
            'href' => $mastodonUrl,
            'svg' => $icons['mastodon'],
        ];
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
            'label' => 'Suscripción a envíos postales',
            'href' => $postalUrl !== '' ? $postalUrl : '/correos.php',
            'svg' => $icons['postal'],
        ];
    }

    $podcastServices = $config['podcast_services'] ?? [];
    $podcastMap = [
        'spotify' => 'Spotify',
        'ivoox' => 'iVoox',
        'apple' => 'Apple Podcasts',
        'google' => 'Google Podcasts',
        'youtube_music' => 'YouTube Music',
    ];
    foreach ($podcastMap as $key => $label) {
        $value = trim((string) ($podcastServices[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        $href = $normalizeExternalUrl($value);
        if ($href === '') {
            continue;
        }
        $links[] = [
            'label' => $label,
            'href' => $href,
            'svg' => $icons[$key] ?? $icons['email'],
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
        if ($title !== '') {
            $properties['og:image:alt'] = $title;
        }
    }
    if ($siteName !== '') {
        $properties['og:site_name'] = $siteName;
    }
    if (!empty($socialConfig['facebook_app_id'])) {
        $properties['fb:app_id'] = $socialConfig['facebook_app_id'];
    }
    if (($data['type'] ?? '') === 'article') {
        if (!empty($data['published_time'])) {
            $properties['article:published_time'] = $data['published_time'];
        }
        if (!empty($data['modified_time'])) {
            $properties['article:modified_time'] = $data['modified_time'];
        }
        if (!empty($data['author'])) {
            $properties['article:author'] = $data['author'];
        }
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
        if ($title !== '') {
            $names['twitter:image:alt'] = $title;
        }
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

function nammu_read_front_matter(string $content): array
{
    $parts = preg_split('/^---\\s*$/m', $content, 3);
    if ($parts === false || count($parts) < 3) {
        return [];
    }
    $yaml = $parts[1];
    $lines = preg_split('/\\r?\\n/', $yaml) ?: [];
    $metadata = [];
    foreach ($lines as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }
        [$key, $value] = explode(':', $line, 2);
        $metadata[trim($key)] = trim($value);
    }
    return $metadata;
}

function nammu_collect_podcast_items(string $contentDir, string $baseUrl = ''): array
{
    $items = [];
    $files = glob(rtrim($contentDir, '/') . '/*.md') ?: [];
    foreach ($files as $file) {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            continue;
        }
        $metadata = nammu_read_front_matter($raw);
        $template = strtolower(trim((string) ($metadata['Template'] ?? '')));
        if ($template !== 'podcast') {
            continue;
        }
        $status = strtolower(trim((string) ($metadata['Status'] ?? 'published')));
        if ($status === 'draft') {
            continue;
        }
        $dateValue = trim((string) ($metadata['Date'] ?? ''));
        $timestamp = $dateValue !== '' ? strtotime($dateValue) : false;
        if ($timestamp === false) {
            $timestamp = @filemtime($file) ?: time();
        }
        $audio = trim((string) ($metadata['Audio'] ?? ''));
        $audioLength = trim((string) ($metadata['AudioLength'] ?? ''));
        $audioDuration = trim((string) ($metadata['AudioDuration'] ?? ''));
        if ($audioLength === '' && $audio !== '') {
            $audioPath = ltrim($audio, '/');
            $audioPath = str_starts_with($audioPath, 'assets/') ? substr($audioPath, strlen('assets/')) : $audioPath;
            $candidate = dirname($contentDir) . '/assets/' . $audioPath;
            if (is_file($candidate)) {
                $audioLength = (string) filesize($candidate);
            }
        }
        $items[] = [
            'filename' => basename($file),
            'title' => (string) ($metadata['Title'] ?? ''),
            'description' => (string) ($metadata['Description'] ?? ''),
            'image' => nammu_resolve_asset((string) ($metadata['Image'] ?? ''), $baseUrl),
            'audio' => nammu_resolve_asset($audio, $baseUrl),
            'audio_length' => $audioLength,
            'audio_duration' => $audioDuration !== '' ? $audioDuration : '00:00:00',
            'timestamp' => $timestamp,
        ];
    }
    usort($items, static function (array $a, array $b): int {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });
    return $items;
}

function nammu_generate_podcast_feed(string $baseUrl, array $config): string
{
    $baseUrl = rtrim($baseUrl, '/');
    $siteTitle = trim((string) ($config['site_name'] ?? 'Nammu Blog'));
    $siteDescription = trim((string) (($config['social']['default_description'] ?? '') ?: ''));
    $siteLang = trim((string) ($config['site_lang'] ?? 'es'));
    $siteAuthor = trim((string) ($config['site_author'] ?? ''));
    $ownerEmail = trim((string) ($config['mailing']['gmail_address'] ?? ''));
    $social = $config['social'] ?? [];
    $homeImage = nammu_resolve_asset((string) ($social['home_image'] ?? ''), $baseUrl);
    $items = nammu_collect_podcast_items(dirname(__DIR__) . '/content', $baseUrl);
    $channelLink = $baseUrl !== '' ? $baseUrl . '/podcast' : '/podcast';
    $lastBuild = gmdate(DATE_RSS, !empty($items) ? (int) $items[0]['timestamp'] : time());
    $titleEsc = htmlspecialchars($siteTitle !== '' ? $siteTitle : 'Podcast', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $descEsc = htmlspecialchars($siteDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $authorEsc = htmlspecialchars($siteAuthor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $langEsc = htmlspecialchars($siteLang !== '' ? $siteLang : 'es', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $ownerNameEsc = $authorEsc !== '' ? $authorEsc : $titleEsc;
    $ownerEmailEsc = htmlspecialchars($ownerEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $itemsXml = [];
    foreach ($items as $item) {
        $itemTitle = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $itemDescription = htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pubDate = gmdate(DATE_RSS, (int) $item['timestamp']);
        $guid = 'podcast:' . md5((string) ($item['filename'] ?? $item['audio']));
        $audioUrl = htmlspecialchars((string) ($item['audio'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $audioLength = htmlspecialchars((string) ($item['audio_length'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $durationEsc = htmlspecialchars((string) ($item['audio_duration'] ?? '00:00:00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $imageUrl = htmlspecialchars((string) ($item['image'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $imageTag = $imageUrl !== '' ? "\n      <itunes:image href=\"{$imageUrl}\" />" : '';
        $itemsXml[] = <<<XML
    <item>
      <title>{$itemTitle}</title>
      <description>{$itemDescription}</description>
      <pubDate>{$pubDate}</pubDate>
      <guid isPermaLink="false">{$guid}</guid>
      <enclosure url="{$audioUrl}" length="{$audioLength}" type="audio/mpeg" />
      <itunes:duration>{$durationEsc}</itunes:duration>
      <itunes:explicit>no</itunes:explicit>{$imageTag}
    </item>
XML;
    }
    $itemsBlock = implode("\n", $itemsXml);
    $itunesImageTag = $homeImage !== '' ? "\n    <itunes:image href=\"" . htmlspecialchars((string) $homeImage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\" />" : '';
    $ownerEmailTag = $ownerEmailEsc !== '' ? "<itunes:email>{$ownerEmailEsc}</itunes:email>" : "<itunes:email></itunes:email>";

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>{$titleEsc}</title>
    <description>{$descEsc}</description>
    <link>{$channelLink}</link>
    <language>{$langEsc}</language>
    <lastBuildDate>{$lastBuild}</lastBuildDate>
    <itunes:author>{$authorEsc}</itunes:author>{$itunesImageTag}
    <itunes:category text="Tecnología" />
    <itunes:explicit>no</itunes:explicit>
    <itunes:owner>
      <itunes:name>{$ownerNameEsc}</itunes:name>
      {$ownerEmailTag}
    </itunes:owner>
{$itemsBlock}
  </channel>
</rss>
XML;
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
 * @return string[]
 */
function nammu_parse_related_slugs_input(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
    $slugs = [];
    foreach ($parts as $part) {
        $candidate = trim((string) $part);
        if ($candidate === '') {
            continue;
        }
        $candidate = ltrim($candidate, '/');
        $normalized = '';
        if (preg_match('#^itinerarios/(.+)$#i', $candidate, $match) === 1) {
            $itinerarySlug = nammu_slugify_label((string) $match[1]);
            if ($itinerarySlug !== '') {
                $normalized = 'itinerarios/' . $itinerarySlug;
            }
        } else {
            $normalized = nammu_slugify_label($candidate);
        }
        if ($normalized === '' || isset($slugs[$normalized])) {
            continue;
        }
        $slugs[$normalized] = true;
    }
    return array_keys($slugs);
}

/**
 * @param Post[] $posts
 * @return array<string, array{name:string, posts:Post[], count:int}>
 */
function nammu_collect_categories_from_posts(array $posts): array
{
    $categories = [];
    $uncategorizedName = 'Sin Categoría';
    foreach ($posts as $post) {
        if (!$post instanceof Post) {
            continue;
        }
        $category = trim($post->getCategory());
        if ($category === '') {
            $category = $uncategorizedName;
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

function nammu_publish_scheduled_posts(string $contentDir): int
{
    $files = glob(rtrim($contentDir, '/') . '/*.md') ?: [];
    $now = time();
    $published = 0;

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            continue;
        }
        if (!preg_match('/^---\\s*\\R(.*?)\\R---\\s*\\R?(.*)$/s', $raw, $matches)) {
            continue;
        }
        $metaRaw = $matches[1];
        $body = $matches[2] ?? '';
        $lines = preg_split('/\\R/', $metaRaw) ?: [];
        $meta = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $meta[trim($key)] = trim($value);
        }
        $status = strtolower($meta['Status'] ?? '');
        $template = strtolower($meta['Template'] ?? 'post');
        $publishAt = $meta['PublishAt'] ?? '';
        if ($status !== 'draft' || $publishAt === '') {
            continue;
        }
        $publishTimestamp = strtotime($publishAt);
        if ($publishTimestamp === false || $publishTimestamp > $now) {
            continue;
        }
        $publishDate = date('Y-m-d', $publishTimestamp);
        $updatedLines = [];
        $statusSet = false;
        $dateSet = false;
        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                $updatedLines[] = $line;
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            if ($key === 'PublishAt') {
                continue;
            }
            if ($key === 'Status') {
                $updatedLines[] = 'Status: published';
                $statusSet = true;
                continue;
            }
            if ($key === 'Date') {
                $updatedLines[] = 'Date: ' . $publishDate;
                $dateSet = true;
                continue;
            }
            $updatedLines[] = $line;
        }
        if (!$statusSet) {
            $updatedLines[] = 'Status: published';
        }
        if (!$dateSet) {
            $updatedLines[] = 'Date: ' . $publishDate;
        }
        $updatedMeta = implode("\n", $updatedLines);
        $newContent = "---\n" . $updatedMeta . "\n---\n" . ltrim($body);
        if ($newContent !== $raw && file_put_contents($file, $newContent) !== false) {
            $published++;
            $slug = basename($file, '.md');
            $payload = [
                'filename' => basename($file),
                'slug' => $slug,
                'title' => $meta['Title'] ?? $slug,
                'description' => $meta['Description'] ?? '',
                'image' => $meta['Image'] ?? '',
                'audio' => $meta['Audio'] ?? '',
                'template' => $template,
                'published_at' => date('Y-m-d H:i', $publishTimestamp),
            ];
            nammu_handle_scheduled_post_notifications($payload);
        }
    }

    return $published;
}

function nammu_scheduled_notifications_file(): string
{
    return __DIR__ . '/../config/scheduled-notifications.json';
}

function nammu_load_scheduled_notifications(): array
{
    $file = nammu_scheduled_notifications_file();
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function nammu_save_scheduled_notifications(array $queue): void
{
    $file = nammu_scheduled_notifications_file();
    nammu_ensure_directory(dirname($file));
    file_put_contents($file, json_encode(array_values($queue), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function nammu_enqueue_scheduled_notification(array $payload): void
{
    $queue = nammu_load_scheduled_notifications();
    $filename = $payload['filename'] ?? '';
    if ($filename !== '') {
        foreach ($queue as $existing) {
            if (($existing['filename'] ?? '') === $filename) {
                return;
            }
        }
    }
    $queue[] = $payload;
    nammu_save_scheduled_notifications($queue);
}

function nammu_handle_scheduled_post_notifications(array $payload): void
{
    if (!nammu_try_send_scheduled_post_notifications($payload)) {
        nammu_enqueue_scheduled_notification($payload);
    }
}

function nammu_try_send_scheduled_post_notifications(array $payload): bool
{
    $template = strtolower((string) ($payload['template'] ?? 'post'));
    $requiredAdmin = function_exists('admin_maybe_auto_post_to_social_networks')
        && function_exists('admin_maybe_enqueue_push_notification')
        && function_exists('get_settings')
        && function_exists('admin_is_mailing_ready')
        && function_exists('admin_mailing_recipients_for_type')
        && function_exists('admin_prepare_mailing_payload')
        && function_exists('admin_send_mailing_broadcast')
        && function_exists('admin_public_post_url')
        && function_exists('admin_public_asset_url');
    if (!$requiredAdmin) {
        return false;
    }

    $filename = (string) ($payload['filename'] ?? '');
    $slug = (string) ($payload['slug'] ?? '');
    $title = (string) ($payload['title'] ?? $slug);
    $description = (string) ($payload['description'] ?? '');
    $image = (string) ($payload['image'] ?? '');
    $audio = (string) ($payload['audio'] ?? '');
    $indexnowUrls = [];
    if ($template === 'podcast') {
        $audioUrl = admin_public_asset_url($audio);
        if ($audioUrl !== '') {
            $indexnowUrls[] = $audioUrl;
        }
    } else {
        $link = $slug !== '' ? admin_public_post_url($slug) : '';
        if ($link !== '') {
            $indexnowUrls[] = $link;
        }
    }
    if (!empty($indexnowUrls) && function_exists('admin_maybe_send_indexnow')) {
        admin_maybe_send_indexnow($indexnowUrls);
    }
    if ($template === 'page') {
        return true;
    }

    if ($filename !== '') {
        if ($template === 'podcast') {
            $audioUrl = admin_public_asset_url($audio);
            $imageUrl = admin_public_asset_url($image);
            if ($audioUrl !== '') {
                admin_maybe_auto_post_to_social_networks($filename, $title, $description, $image, $audioUrl, $imageUrl);
            }
        } else {
            $imageUrl = admin_public_asset_url($image);
            admin_maybe_auto_post_to_social_networks($filename, $title, $description, $image, '', $imageUrl);
        }
    }

    $settings = get_settings();
    $mailing = $settings['mailing'] ?? [];
    if ($template === 'podcast') {
        $audioUrl = admin_public_asset_url($audio);
        if (($mailing['auto_podcast'] ?? 'off') === 'on' && admin_is_mailing_ready($settings)) {
            $subscribers = admin_mailing_recipients_for_type('podcast', $settings);
            if (!empty($subscribers) && $audioUrl !== '') {
                $payloadMail = admin_prepare_mailing_payload('podcast', $settings, $title, $description, $audioUrl, $image);
                try {
                    admin_send_mailing_broadcast($payloadMail['subject'], '', '', $subscribers, $payloadMail['mailingConfig'], $payloadMail['bodyBuilder'], $payloadMail['fromName']);
                } catch (Throwable $e) {
                    // ignore mailing errors on scheduled publish
                }
            }
        }
    } else {
        $link = $slug !== '' ? admin_public_post_url($slug) : '';
        if (($mailing['auto_posts'] ?? 'off') === 'on' && admin_is_mailing_ready($settings)) {
            $subscribers = admin_mailing_recipients_for_type('posts', $settings);
            if (!empty($subscribers) && $link !== '') {
                $payloadMail = admin_prepare_mailing_payload('single', $settings, $title, $description, $link, $image);
                try {
                    admin_send_mailing_broadcast($payloadMail['subject'], '', '', $subscribers, $payloadMail['mailingConfig'], $payloadMail['bodyBuilder'], $payloadMail['fromName']);
                } catch (Throwable $e) {
                    // ignore mailing errors on scheduled publish
                }
            }
        }
        if ($link !== '') {
            admin_maybe_enqueue_push_notification('post', $title, $description, $link, $image);
        }
    }

    return true;
}

function nammu_process_scheduled_notifications_queue(): array
{
    $queue = nammu_load_scheduled_notifications();
    if (empty($queue)) {
        return ['processed' => 0, 'remaining' => 0];
    }
    $remaining = [];
    $processed = 0;
    foreach ($queue as $payload) {
        if (nammu_try_send_scheduled_post_notifications(is_array($payload) ? $payload : [])) {
            $processed++;
            continue;
        }
        $remaining[] = $payload;
    }
    nammu_save_scheduled_notifications($remaining);
    return ['processed' => $processed, 'remaining' => count($remaining)];
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

function nammu_render_header_buttons(array $options): string
{
    $accent = (string) ($options['accent'] ?? '#0a4c8a');
    $searchUrl = (string) ($options['search_url'] ?? '/buscar.php');
    $homeUrl = (string) ($options['home_url'] ?? '');
    if ($homeUrl === '') {
        if ($searchUrl !== '' && str_ends_with($searchUrl, '/buscar.php')) {
            $base = substr($searchUrl, 0, -strlen('/buscar.php'));
            $homeUrl = $base === '' ? '/' : $base . '/';
        } else {
            $homeUrl = '/';
        }
    }
    $categoriesUrl = (string) ($options['categories_url'] ?? '/categorias');
    $itinerariesUrl = (string) ($options['itineraries_url'] ?? '/itinerarios');
    $podcastUrl = (string) ($options['podcast_url'] ?? '/podcast');
    $lettersUrl = (string) ($options['letters_url'] ?? ($GLOBALS['lettersIndexUrl'] ?? '/letras'));
    $newslettersUrl = (string) ($options['newsletters_url'] ?? ($GLOBALS['newslettersIndexUrl'] ?? '/newsletters'));
    $avisosUrl = (string) ($options['avisos_url'] ?? '/avisos.php');
    $postalUrl = (string) ($options['postal_url'] ?? '/correos.php');
    $postalLogoSvg = (string) ($options['postal_svg'] ?? '');
    $hasCategories = !empty($options['has_categories']);
    $hasItineraries = !empty($options['has_itineraries']);
    $hasPodcast = !empty($options['has_podcast']);
    $isDictionaryMode = false;
    if (array_key_exists('is_dictionary_mode', $options)) {
        $isDictionaryMode = !empty($options['is_dictionary_mode']);
    } else {
        $config = nammu_load_config();
        $isDictionaryMode = (($config['sort_order'] ?? 'date') === 'alpha');
    }
    $showLetters = array_key_exists('show_letters', $options)
        ? !empty($options['show_letters'])
        : ($isDictionaryMode && $lettersUrl !== '');
    $hasNewsletters = !empty($options['has_newsletters'] ?? ($GLOBALS['hasNewsletters'] ?? $GLOBALS['has_newsletters'] ?? false));
    $subscriptionEnabled = !empty($options['subscription_enabled']);
    $postalEnabled = !empty($options['postal_enabled']);

    $items = [];
    $items[] = [
        'label' => 'Portada',
        'href' => $homeUrl,
        'svg' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 11.5L12 4L21 11.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-8.5Z" stroke="#fff" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg>',
    ];
    if ($showLetters) {
        $items[] = [
            'label' => 'Letras',
            'href' => $lettersUrl,
            'svg' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 18L9 6H10.5L14.5 18M6.2 14H13.2" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 7H21M16 12H20M16 17H21" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>',
        ];
    }
    if ($hasCategories) {
        $items[] = [
            'label' => 'Categorías',
            'href' => $categoriesUrl,
            'svg' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="#fff" stroke-width="2"/><line x1="8" y1="9" x2="16" y2="9" stroke="#fff" stroke-width="2"/><line x1="8" y1="13" x2="16" y2="13" stroke="#fff" stroke-width="2"/></svg>',
        ];
    }
    if ($hasPodcast) {
        $items[] = [
            'label' => 'Podcast',
            'href' => $podcastUrl,
            'svg' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="9" y="3" width="6" height="10" rx="3" stroke="#fff" stroke-width="2"/><path d="M5 11C5 14.866 8.134 18 12 18C15.866 18 19 14.866 19 11" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="12" y1="18" x2="12" y2="22" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="22" x2="16" y2="22" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>',
        ];
    }
    if ($hasItineraries) {
        $items[] = [
            'label' => 'Itinerarios',
            'href' => $itinerariesUrl,
            'svg' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 5H10C11.1046 5 12 5.89543 12 7V19H4C2.89543 19 2 18.1046 2 17V7C2 5.89543 2.89543 5 4 5Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><path d="M20 5H14C12.8954 5 12 5.89543 12 7V19H20C21.1046 19 22 18.1046 22 17V7C22 5.89543 21.1046 5 20 5Z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><line x1="12" y1="7" x2="12" y2="19" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>',
        ];
    }
    if ($hasNewsletters) {
        $items[] = [
            'label' => 'Newsletters',
            'href' => $newslettersUrl,
            'svg' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3h9l3 3v15a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="#fff" stroke-width="2"/><path d="M15 3v4h4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="8" y1="11" x2="16" y2="11" stroke="#fff" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="15" x2="16" y2="15" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>',
        ];
    }
    if ($subscriptionEnabled) {
        $items[] = [
            'label' => 'Suscripción a Avisos y Newsletter',
            'href' => $avisosUrl,
            'svg' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="14" rx="2" stroke="#fff" stroke-width="2"/><polyline points="3,7 12,13 21,7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];
    }
    if ($postalEnabled && $postalLogoSvg !== '') {
        $items[] = [
            'label' => 'Suscripción a envíos postales',
            'href' => $postalUrl,
            'svg' => $postalLogoSvg,
        ];
    }
    if ($searchUrl !== '') {
        $items[] = [
            'label' => 'Buscar',
            'href' => $searchUrl,
            'svg' => '<svg width="20" height="20" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="6" stroke="#fff" stroke-width="2"/><line x1="12.5" y1="12.5" x2="17" y2="17" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>',
        ];
    }
    if (empty($items)) {
        return '';
    }
    $style = '';
    if ($accent !== '') {
        $style = ' style="--nammu-header-button-accent: ' . htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') . '"';
    }
    ob_start(); ?>
    <div class="site-header-buttons"<?= $style ?>>
        <?php foreach ($items as $item): ?>
            <a class="site-header-button-link" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>">
                <?= $item['svg'] ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function nammu_newsletter_access_file(): string
{
    return __DIR__ . '/../config/newsletters-access.json';
}

function nammu_newsletter_load_access_entries(): array
{
    $file = nammu_newsletter_access_file();
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function nammu_newsletter_save_access_entries(array $entries): void
{
    $file = nammu_newsletter_access_file();
    nammu_ensure_directory(dirname($file));
    $payload = json_encode(array_values($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return;
    }
    file_put_contents($file, $payload, LOCK_EX);
    @chmod($file, 0664);
}

function nammu_newsletter_purge_access_entries(array $entries): array
{
    $now = time();
    $filtered = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $expires = (int) ($entry['expires_at'] ?? 0);
        if ($expires > $now) {
            $filtered[] = $entry;
        }
    }
    return $filtered;
}

function nammu_newsletter_issue_access_token(string $email, int $ttlSeconds = 3600): array
{
    $token = bin2hex(random_bytes(16));
    $expires = time() + $ttlSeconds;
    $entries = nammu_newsletter_load_access_entries();
    $entries = nammu_newsletter_purge_access_entries($entries);
    $entries[] = [
        'email' => strtolower(trim($email)),
        'token' => $token,
        'expires_at' => $expires,
    ];
    nammu_newsletter_save_access_entries($entries);
    return ['token' => $token, 'expires_at' => $expires];
}

function nammu_newsletter_validate_access(string $email, string $token): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || $token === '') {
        return false;
    }
    $now = time();
    $entries = nammu_newsletter_load_access_entries();
    $entries = nammu_newsletter_purge_access_entries($entries);
    $valid = false;
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $expires = (int) ($entry['expires_at'] ?? 0);
        if ($expires <= $now) {
            continue;
        }
        if (($entry['email'] ?? '') === $email && ($entry['token'] ?? '') === $token) {
            $valid = true;
            break;
        }
    }
    if (count($entries) !== count(nammu_newsletter_load_access_entries())) {
        nammu_newsletter_save_access_entries($entries);
    }
    return $valid;
}

function nammu_newsletter_access_cookie_name(): string
{
    return 'nammu_newsletter_access';
}

function nammu_contact_settings_from_config(array $config): array
{
    $contact = is_array($config['contact'] ?? null) ? $config['contact'] : [];
    $telegram = trim((string) ($contact['telegram'] ?? ''));
    $email = trim((string) ($contact['email'] ?? ''));
    $phone = trim((string) ($contact['phone'] ?? ''));
    $footer = ($contact['footer'] ?? 'off') === 'on';
    $signature = ($contact['signature'] ?? 'off') === 'on';
    $fields = $contact['signature_fields'] ?? [];
    if (!is_array($fields)) {
        $fields = [];
    }
    $fields = array_values(array_intersect(['telegram', 'email', 'phone'], $fields));
    return [
        'telegram' => $telegram,
        'email' => $email,
        'phone' => $phone,
        'footer' => $footer,
        'signature' => $signature,
        'signature_fields' => $fields,
    ];
}

function nammu_contact_settings(): array
{
    return nammu_contact_settings_from_config(nammu_load_config());
}

function nammu_contact_signature_lines(array $contact): array
{
    $fields = $contact['signature_fields'] ?? [];
    if (!is_array($fields)) {
        $fields = [];
    }
    $lines = [];
    foreach ($fields as $field) {
        if ($field === 'telegram') {
            $handle = trim((string) ($contact['telegram'] ?? ''));
            if ($handle !== '') {
                $handle = ltrim($handle, '@');
                $lines[] = '@' . $handle;
            }
        } elseif ($field === 'email') {
            $email = trim((string) ($contact['email'] ?? ''));
            if ($email !== '') {
                $lines[] = $email;
            }
        } elseif ($field === 'phone') {
            $phone = trim((string) ($contact['phone'] ?? ''));
            if ($phone !== '') {
                $lines[] = $phone;
            }
        }
    }
    return $lines;
}

function nammu_contact_signature_items(array $contact): array
{
    $fields = $contact['signature_fields'] ?? [];
    if (!is_array($fields)) {
        $fields = [];
    }
    $items = [];
    foreach ($fields as $field) {
        if ($field === 'telegram') {
            $handle = trim((string) ($contact['telegram'] ?? ''));
            if ($handle !== '') {
                $handle = ltrim($handle, '@');
                $items[] = [
                    'label' => '@' . $handle,
                    'href' => 'https://t.me/' . rawurlencode($handle),
                ];
            }
        } elseif ($field === 'email') {
            $email = trim((string) ($contact['email'] ?? ''));
            if ($email !== '') {
                $items[] = [
                    'label' => $email,
                    'href' => 'mailto:' . $email,
                ];
            }
        } elseif ($field === 'phone') {
            $phone = trim((string) ($contact['phone'] ?? ''));
            if ($phone !== '') {
                $tel = preg_replace('/\s+/', '', $phone);
                $items[] = [
                    'label' => $phone,
                    'href' => 'tel:' . $tel,
                ];
            }
        }
    }
    return $items;
}

function nammu_contact_footer_items(array $contact): array
{
    $items = [];
    $telegram = trim((string) ($contact['telegram'] ?? ''));
    if ($telegram !== '') {
        $handle = ltrim($telegram, '@');
        $url = preg_match('#^https?://#i', $telegram) ? $telegram : ('https://t.me/' . rawurlencode($handle));
        $items[] = [
            'label' => '@' . $handle,
            'href' => $url,
            'svg' => '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M21.7 5.2a1 1 0 0 0-1.1-.1L3.5 12.1a1 1 0 0 0 .1 1.9l4.7 1.7 1.9 5.1a1 1 0 0 0 1.7.3l2.8-3.2 4.6 3.4a1 1 0 0 0 1.6-.6l2-12.4a1 1 0 0 0-.2-0.7zM9.5 14.8l8-6.4-6.2 7.6-.2 2.8-1.2-3.1-3.5-1.3 11.7-4.6-10.6 5z"/></svg>',
        ];
    }
    $email = trim((string) ($contact['email'] ?? ''));
    if ($email !== '') {
        $items[] = [
            'label' => $email,
            'href' => 'mailto:' . $email,
            'svg' => '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="3,7 12,13 21,7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];
    }
    $phone = trim((string) ($contact['phone'] ?? ''));
    if ($phone !== '') {
        $tel = preg_replace('/\s+/', '', $phone);
        $items[] = [
            'label' => $phone,
            'href' => 'tel:' . $tel,
            'svg' => '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 .97-.25c1.07.27 2.22.41 3.41.41a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.4 21 3 13.6 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.19.14 2.34.41 3.41a1 1 0 0 1-.25.97l-2.04 2.41z"/></svg>',
        ];
    }
    return $items;
}

function nammu_newsletter_set_access_cookie(string $email, string $token, int $expires): void
{
    $payload = json_encode(['email' => $email, 'token' => $token, 'expires_at' => $expires], JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return;
    }
    $encoded = base64_encode($payload);
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (PHP_VERSION_ID >= 70300) {
        setcookie(nammu_newsletter_access_cookie_name(), $encoded, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie(nammu_newsletter_access_cookie_name(), $encoded, $expires, '/', '', $secure, true);
    }
    $_COOKIE[nammu_newsletter_access_cookie_name()] = $encoded;
}

function nammu_newsletter_get_access_cookie(): ?array
{
    $raw = $_COOKIE[nammu_newsletter_access_cookie_name()] ?? '';
    if ($raw === '') {
        return null;
    }
    $decoded = base64_decode((string) $raw, true);
    if ($decoded === false) {
        return null;
    }
    $data = json_decode($decoded, true);
    if (!is_array($data)) {
        return null;
    }
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $token = trim((string) ($data['token'] ?? ''));
    $expiresAt = array_key_exists('expires_at', $data) ? (int) ($data['expires_at'] ?? 0) : 0;
    if ($email === '' || $token === '') {
        return null;
    }
    if ($expiresAt === 0 || $expiresAt <= time()) {
        setcookie(nammu_newsletter_access_cookie_name(), '', time() - 3600, '/', '', false, true);
        unset($_COOKIE[nammu_newsletter_access_cookie_name()]);
        return null;
    }
    return ['email' => $email, 'token' => $token];
}

function nammu_mailing_default_prefs(): array
{
    return [
        'posts' => true,
        'itineraries' => true,
        'podcast' => true,
        'newsletter' => true,
    ];
}

function nammu_mailing_normalize_entry($entry): ?array
{
    if (is_string($entry)) {
        $email = strtolower(trim($entry));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return [
            'email' => $email,
            'prefs' => nammu_mailing_default_prefs(),
            'newsletter_since' => null,
        ];
    }
    if (!is_array($entry)) {
        return null;
    }
    $email = strtolower(trim((string) ($entry['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $prefsRaw = $entry['prefs'] ?? [];
    $prefs = nammu_mailing_default_prefs();
    if (is_array($prefsRaw)) {
        foreach ($prefs as $key => $default) {
            $value = $prefsRaw[$key] ?? $default;
            if (is_string($value)) {
                $value = strtolower($value) !== 'off' && $value !== '0' && $value !== '';
            } else {
                $value = (bool) $value;
            }
            $prefs[$key] = $value;
        }
    }
    $newsletterSince = null;
    if (array_key_exists('newsletter_since', $entry)) {
        $rawSince = $entry['newsletter_since'];
        if (is_numeric($rawSince)) {
            $newsletterSince = (int) $rawSince;
        } elseif (is_string($rawSince) && trim($rawSince) !== '') {
            $parsed = strtotime($rawSince);
            if ($parsed !== false) {
                $newsletterSince = (int) $parsed;
            }
        }
    }
    return [
        'email' => $email,
        'prefs' => $prefs,
        'newsletter_since' => $newsletterSince,
    ];
}

function nammu_is_newsletter_subscriber(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $file = __DIR__ . '/../config/mailing-subscribers.json';
    if (!is_file($file)) {
        return false;
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return false;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return false;
    }
    foreach ($decoded as $entry) {
        $normalized = nammu_mailing_normalize_entry($entry);
        if ($normalized === null) {
            continue;
        }
        if (($normalized['email'] ?? '') === $email && !empty(($normalized['prefs'] ?? [])['newsletter'])) {
            return true;
        }
    }
    return false;
}

function nammu_get_newsletter_since(string $email): ?int
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $file = __DIR__ . '/../config/mailing-subscribers.json';
    if (!is_file($file)) {
        return null;
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    foreach ($decoded as $entry) {
        $normalized = nammu_mailing_normalize_entry($entry);
        if ($normalized === null) {
            continue;
        }
        if (($normalized['email'] ?? '') === $email && !empty(($normalized['prefs'] ?? [])['newsletter'])) {
            $since = $normalized['newsletter_since'] ?? null;
            return is_int($since) && $since > 0 ? $since : null;
        }
    }
    return null;
}

function nammu_newsletter_collect_items(string $contentDir, string $baseUrl = ''): array
{
    $items = [];
    $files = glob(rtrim($contentDir, '/') . '/*.md') ?: [];
    foreach ($files as $file) {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            continue;
        }
        if (!preg_match('/^---\\s*\\R(.*?)\\R---\\s*\\R?(.*)$/s', $raw, $matches)) {
            continue;
        }
        $metaRaw = $matches[1];
        $content = $matches[2] ?? '';
        $metadata = [];
        foreach (preg_split('/\\R/', $metaRaw) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $metadata[trim($key)] = trim($value);
        }
        $template = strtolower(trim((string) ($metadata['Template'] ?? '')));
        if ($template !== 'newsletter') {
            continue;
        }
        $status = strtolower(trim((string) ($metadata['Status'] ?? '')));
        if ($status !== 'newsletter') {
            continue;
        }
        $dateValue = trim((string) ($metadata['Date'] ?? ''));
        $timestamp = $dateValue !== '' ? strtotime($dateValue) : false;
        if ($timestamp === false) {
            $timestamp = @filemtime($file) ?: time();
        }
        $image = nammu_resolve_asset((string) ($metadata['Image'] ?? ''), $baseUrl);
        $items[] = [
            'slug' => basename($file, '.md'),
            'title' => (string) ($metadata['Title'] ?? ''),
            'description' => (string) ($metadata['Description'] ?? ''),
            'image' => $image,
            'date' => $dateValue,
            'timestamp' => $timestamp,
            'content' => $content,
        ];
    }
    usort($items, static function (array $a, array $b): int {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });
    return $items;
}

function nammu_parse_hex_color(string $hex, int &$r, int &$g, int &$b): bool
{
    $value = ltrim(trim($hex), '#');
    if (strlen($value) === 3) {
        $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
    }
    if (strlen($value) !== 6 || !ctype_xdigit($value)) {
        return false;
    }
    $r = hexdec(substr($value, 0, 2));
    $g = hexdec(substr($value, 2, 2));
    $b = hexdec(substr($value, 4, 2));
    return true;
}

function nammu_pick_contrast_color(string $backgroundHex, string $light = '#ffffff', string $dark = '#111111'): string
{
    $r = $g = $b = 0;
    if (!nammu_parse_hex_color($backgroundHex, $r, $g, $b)) {
        return $dark;
    }
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return $yiq >= 160 ? $dark : $light;
}

function nammu_newsletter_expand_image_urls(string $html, string $baseUrl): string
{
    if ($html === '' || $baseUrl === '') {
        return $html;
    }
    $baseUrl = rtrim($baseUrl, '/');
    $singleAttrCallback = static function (array $matches) use ($baseUrl): string {
        $prefix = $matches[1] ?? '';
        $quote = $matches[2] ?? '"';
        $value = $matches[3] ?? '';
        $suffix = $matches[4] ?? '';
        $normalized = trim($value);
        if ($normalized === '' || preg_match('#^(https?:)?//#i', $normalized) || str_starts_with($normalized, 'data:') || str_starts_with($normalized, 'mailto:') || str_starts_with($normalized, 'tel:')) {
            return $matches[0];
        }
        $normalized = ltrim($normalized, '/');
        return $prefix . $quote . $baseUrl . '/' . $normalized . $quote . $suffix;
    };
    $srcsetCallback = static function (array $matches) use ($baseUrl): string {
        $prefix = $matches[1] ?? '';
        $quote = $matches[2] ?? '"';
        $value = $matches[3] ?? '';
        $suffix = $matches[4] ?? '';
        $parts = array_filter(array_map('trim', explode(',', $value)));
        if (empty($parts)) {
            return $matches[0];
        }
        $rebuilt = [];
        foreach ($parts as $part) {
            $segments = preg_split('/\\s+/', $part, 2);
            $urlPart = $segments[0] ?? '';
            $descriptor = $segments[1] ?? '';
            $normalized = trim($urlPart);
            if ($normalized !== '' && !preg_match('#^(https?:)?//#i', $normalized) && !str_starts_with($normalized, 'data:')) {
                $normalized = ltrim($normalized, '/');
                $normalized = $baseUrl . '/' . $normalized;
            }
            $rebuilt[] = trim($normalized . ($descriptor !== '' ? ' ' . $descriptor : ''));
        }
        return $prefix . $quote . implode(', ', $rebuilt) . $quote . $suffix;
    };
    $html = preg_replace_callback('/(<img\\b[^>]*\\bsrc\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<a\\b[^>]*\\bhref\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<img\\b[^>]*\\bsrcset\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $srcsetCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<source\\b[^>]*\\bsrc\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<source\\b[^>]*\\bsrcset\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $srcsetCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<video\\b[^>]*\\bposter\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<audio\\b[^>]*\\bsrc\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<video\\b[^>]*\\bsrc\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    return $html;
}

function nammu_mailing_secret(): string
{
    $file = __DIR__ . '/../config/mailing-secret.key';
    if (!is_file($file)) {
        nammu_ensure_directory(dirname($file));
        $secret = bin2hex(random_bytes(32));
        file_put_contents($file, $secret);
        @chmod($file, 0640);
        return $secret;
    }
    $secret = trim((string) file_get_contents($file));
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        file_put_contents($file, $secret);
    }
    return $secret;
}

function nammu_mailing_unsubscribe_token(string $email): string
{
    $secret = nammu_mailing_secret();
    return hash_hmac('sha256', strtolower(trim($email)), $secret);
}

function nammu_mailing_unsubscribe_link(string $email): string
{
    $token = nammu_mailing_unsubscribe_token($email);
    $base = nammu_base_url();
    $base = $base !== '' ? rtrim($base, '/') : '';
    return $base . '/unsubscribe.php?email=' . urlencode($email) . '&token=' . urlencode($token);
}

function nammu_load_mailing_tokens(): array
{
    $file = __DIR__ . '/../config/mailing-tokens.json';
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function nammu_google_refresh_access_token(string $clientId, string $clientSecret, string $refreshToken): array
{
    $postData = http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ];
    $context = stream_context_create($opts);
    $raw = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
    if ($raw === false) {
        throw new RuntimeException('No se pudo refrescar el token con Google.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta inesperada al refrescar token.');
    }
    if (isset($decoded['error'])) {
        $message = is_string($decoded['error']) ? $decoded['error'] : 'Error de OAuth';
        $desc = isset($decoded['error_description']) ? ' (' . $decoded['error_description'] . ')' : '';
        throw new RuntimeException($message . $desc);
    }
    return $decoded;
}

function nammu_gmail_send_message(string $from, string $to, string $subject, string $textBody, string $htmlBody, string $accessToken, ?string $fromName = null): array
{
    $boundary = '=_NammuMailer_' . bin2hex(random_bytes(8));
    $displayName = $fromName && trim($fromName) !== '' ? trim($fromName) : '';
    $displayName = str_replace(['"', "\r", "\n"], '', $displayName);
    $encodedName = $displayName !== '' && function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($displayName, 'UTF-8', 'Q', "\r\n")
        : ($displayName !== '' ? '=?UTF-8?B?' . base64_encode($displayName) . '?=' : '');
    $fromHeader = $encodedName !== '' ? $encodedName . ' <' . $from . '>' : $from;
    $subjectHeader = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8', 'Q', "\r\n")
        : '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'From: ' . $fromHeader,
        'Reply-To: ' . $fromHeader,
        'To: ' . $to,
        'Subject: ' . $subjectHeader,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body = [];
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/plain; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: 7bit';
    $body[] = '';
    $body[] = $textBody;
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/html; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: 7bit';
    $body[] = '';
    $body[] = $htmlBody;
    $body[] = '--' . $boundary . '--';
    $rawMessage = implode("\r\n", array_merge($headers, [''], $body));
    $payload = json_encode(['raw' => rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=')]);
    if ($payload === false) {
        throw new RuntimeException('No se pudo preparar el mensaje.');
    }
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', false, $context);
    if ($response === false) {
        $status = isset($http_response_header[0]) ? $http_response_header[0] : 'sin respuesta';
        return [false, 'HTTP ' . $status];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || !isset($decoded['id'])) {
        if (isset($decoded['error']['message'])) {
            return [false, 'Error Gmail: ' . $decoded['error']['message']];
        }
        return [false, 'Respuesta inesperada al enviar correo'];
    }
    return [true, null];
}

function nammu_send_newsletter_access_email(array $settings, string $email, string $token, string $nextPath = ''): void
{
    $mailing = $settings['mailing'] ?? [];
    $clientId = $mailing['client_id'] ?? '';
    $clientSecret = $mailing['client_secret'] ?? '';
    $fromEmail = $mailing['gmail_address'] ?? '';
    $tokens = nammu_load_mailing_tokens();
    $refresh = $tokens['refresh_token'] ?? '';
    $siteLabel = trim((string) ($settings['site_name'] ?? 'tu blog'));
    $authorName = trim((string) ($settings['site_author'] ?? ''));
    $base = nammu_base_url();
    $base = $base !== '' ? rtrim($base, '/') : '';
    $link = $base . '/newsletters?email=' . urlencode($email) . '&token=' . urlencode($token);
    if ($nextPath !== '') {
        $link .= '&next=' . urlencode($nextPath);
    }
    $subject = 'Acceso al archivo de newsletters de ' . $siteLabel;
    $textBody = "Confirma tu acceso al archivo de newsletters de {$siteLabel} haciendo clic en el enlace:\n{$link}\n\nSólo podrás leer los newsletters enviados después de haberte suscrito.\n\nEste enlace caduca en 1 hora.";
    $htmlBody = '<p>Confirma tu acceso al archivo de newsletters de ' . htmlspecialchars($siteLabel, ENT_QUOTES, 'UTF-8') . ':</p><p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Acceder al archivo</a></p><p>Sólo podrás leer los newsletters enviados después de haberte suscrito.</p><p>Este enlace caduca en 1 hora.</p>';
    if ($clientId !== '' && $clientSecret !== '' && $fromEmail !== '' && $refresh !== '') {
        $refreshed = nammu_google_refresh_access_token($clientId, $clientSecret, $refresh);
        $accessToken = $refreshed['access_token'] ?? '';
        if ($accessToken !== '') {
            $fromName = $authorName !== '' ? $authorName : $siteLabel;
            nammu_gmail_send_message($fromEmail, $email, $subject, $textBody, $htmlBody, $accessToken, $fromName);
            return;
        }
    }
    $headers = [];
    $fromName = $authorName !== '' ? $authorName : ($siteLabel !== '' ? $siteLabel : 'Nammu');
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers[] = 'From: ' . $encodedName . ' <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    @mail($email, $subject, $textBody, implode("\r\n", $headers));
}

function nammu_build_newsletter_html(array $settings, string $title, string $contentHtml, string $imagePath, string $recipientEmail): string
{
    $mailingConfig = $settings['mailing'] ?? [];
    $blogName = $settings['site_name'] ?? 'Tu blog';
    $authorName = $settings['site_author'] ?? 'Autor';
    $siteBase = rtrim($settings['site_url'] ?? '', '/');
    $baseForAssets = $siteBase !== '' ? $siteBase : rtrim(nammu_base_url(), '/');
    $link = $siteBase !== '' ? $siteBase : rtrim(nammu_base_url(), '/');
    $imageUrl = '';
    if ($imagePath !== '') {
        if (preg_match('#^https?://#i', $imagePath)) {
            $imageUrl = $imagePath;
        } else {
            $normalizedImage = ltrim($imagePath, '/');
            $normalizedImage = str_replace(['../', '..\\', './', '.\\'], '', $normalizedImage);
            $candidates = [];
            $candidates[] = $normalizedImage;
            if (!str_starts_with($normalizedImage, 'assets/')) {
                $candidates[] = 'assets/' . $normalizedImage;
            }
            foreach ($candidates as $cand) {
                $local = dirname(__DIR__) . '/' . $cand;
                if (is_file($local) || is_file(dirname(__DIR__) . '/' . ltrim($cand, '/'))) {
                    $imageUrl = $baseForAssets . '/' . $cand;
                    break;
                }
            }
            if ($imageUrl === '' && !empty($candidates)) {
                $imageUrl = $baseForAssets . '/' . $candidates[0];
            }
        }
    }
    $logoPath = $settings['template']['images']['logo'] ?? '';
    $logoUrl = '';
    if ($logoPath !== '') {
        if (preg_match('#^https?://#i', $logoPath)) {
            $logoUrl = $logoPath;
        } else {
            $normalizedLogo = ltrim($logoPath, '/');
            $normalizedLogo = str_replace(['../', '..\\', './', '.\\'], '', $normalizedLogo);
            $logoUrl = $baseForAssets . '/' . $normalizedLogo;
        }
    }
    $colors = $settings['template']['colors'] ?? [];
    $colorBackground = $colors['background'] ?? '#ffffff';
    $colorText = $colors['text'] ?? '#222222';
    $colorHighlight = $colors['highlight'] ?? '#f3f6f9';
    $colorAccent = $colors['accent'] ?? '#0a4c8a';
    $colorH2 = $colors['h2'] ?? $colorText;
    $headerBg = $colors['h1'] ?? $colorAccent;
    $ctaColor = $colors['h1'] ?? $colorAccent;
    $signatureColor = $colors['h1'] ?? $colorAccent;
    $outerBg = $colorHighlight;
    $cardBg = $colorBackground;
    $footerBg = $colorHighlight;
    $border = $colorAccent;
    $headerText = nammu_pick_contrast_color($headerBg, '#ffffff', '#111111');
    $ctaText = nammu_pick_contrast_color($ctaColor, '#ffffff', '#111111');
    $footerText = nammu_pick_contrast_color($footerBg, '#ffffff', $colorText);
    $titleFont = $settings['template']['fonts']['title'] ?? 'Arial';
    $bodyFont = $settings['template']['fonts']['body'] ?? 'Arial';
    $fontsUrl = '';
    $fontFamilies = [];
    foreach ([$titleFont, $bodyFont] as $fontCandidate) {
        $clean = trim((string) $fontCandidate);
        if ($clean !== '') {
            $fontFamilies[] = str_replace(' ', '+', $clean) . ':wght@400;600;700';
        }
    }
    if (!empty($fontFamilies)) {
        $fontsUrl = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', array_unique($fontFamilies)) . '&display=swap';
    }
    $titleFontCss = htmlspecialchars($titleFont, ENT_QUOTES, 'UTF-8');
    $bodyFontCss = htmlspecialchars($bodyFont, ENT_QUOTES, 'UTF-8');
    $safeUnsub = htmlspecialchars(nammu_mailing_unsubscribe_link($recipientEmail), ENT_QUOTES, 'UTF-8');
    $contentHtml = nammu_newsletter_expand_image_urls($contentHtml, $baseForAssets);
    $html = [];
    if ($fontsUrl !== '') {
        $html[] = '<link rel="stylesheet" href="' . htmlspecialchars($fontsUrl, ENT_QUOTES, 'UTF-8') . '">';
    }
    $html[] = '<style>h1,h2,h3,h4,h5,h6{font-family:' . $titleFontCss . ', Arial, sans-serif;} body,p,a,div,span{font-family:' . $bodyFontCss . ', Arial, sans-serif;} a{color:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . ';}</style>';
    $html[] = '<div style="font-family:' . $bodyFontCss . ', Arial, sans-serif; background:' . htmlspecialchars($outerBg, ENT_QUOTES, 'UTF-8') . '; padding:24px; color:' . htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') . ';">';
    $html[] = '  <div style="max-width:720px; margin:0 auto; background:' . htmlspecialchars($cardBg, ENT_QUOTES, 'UTF-8') . '; border:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33; border-radius:12px; overflow:hidden;">';
    $html[] = '    <div style="background:' . htmlspecialchars($headerBg, ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($headerText, ENT_QUOTES, 'UTF-8') . '; padding:18px 22px; text-align:center;">';
    if ($logoUrl !== '') {
        $html[] = '      <div style="margin-bottom:10px;"><img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:64px; height:64px; object-fit:cover; border-radius:50%; box-shadow:0 4px 12px rgba(0,0,0,0.15); background:' . htmlspecialchars($cardBg, ENT_QUOTES, 'UTF-8') . ';"></div>';
    }
    $html[] = '      <div style="font-size:14px; opacity:0.9; margin-bottom:4px;">' . htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') . '</div>';
    $html[] = '      <div style="font-size:20px; font-weight:700;">' . htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') . '</div>';
    $html[] = '    </div>';
    $html[] = '    <div style="padding:22px;">';
    $html[] = '      <h2 style="margin:0 0 20px 0; font-size:32px; line-height:1.15; color:' . htmlspecialchars($colorH2, ENT_QUOTES, 'UTF-8') . '; font-family:' . $titleFontCss . ', Arial, sans-serif;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
    if ($imageUrl !== '') {
        $html[] = '      <div style="margin:0 0 14px 0;"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:100%; display:block; border-radius:12px; border:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33;"></div>';
    }
    if ($contentHtml !== '') {
        $html[] = '      <div style="margin:0; line-height:1.75; font-size:18px; color:' . htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') . '; font-family:' . $bodyFontCss . ', Arial, sans-serif;">' . $contentHtml . '</div>';
    }
    $contact = nammu_contact_settings_from_config($settings);
    $signatureItems = (!empty($contact['signature'] ?? false)) ? nammu_contact_signature_items($contact) : [];
    if (!empty($signatureItems)) {
        $html[] = '      <div style="margin:22px 0 0 0; text-align:right; font-family:' . $titleFontCss . ', Arial, sans-serif; font-size:32px; line-height:1.2; font-weight:600; color:' . htmlspecialchars($signatureColor, ENT_QUOTES, 'UTF-8') . ';">';
        foreach ($signatureItems as $item) {
            $html[] = '        <div><a href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '" style="color:' . htmlspecialchars($signatureColor, ENT_QUOTES, 'UTF-8') . '; text-decoration:none;">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a></div>';
        }
        $html[] = '      </div>';
    }
    if ($link !== '') {
        $html[] = '      <p style="margin:20px 0 0 0;">';
        $html[] = '        <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; background:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8') . '; padding:14px 18px; border-radius:10px; text-decoration:none; font-weight:600;">Visitar el sitio</a>';
        $html[] = '      </p>';
    }
    $html[] = '    </div>';
    $html[] = '    <div style="padding:16px 22px; background:' . htmlspecialchars($footerBg, ENT_QUOTES, 'UTF-8') . '; border-top:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33; font-size:13px; color:' . htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') . '; opacity:0.8;">';
    $html[] = '      <p style="margin:0 0 6px 0;">Recibes este email porque estás suscrito a las comunicaciones de ' . htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') . '.</p>';
    $html[] = '      <p style="margin:0;"><a href="' . $safeUnsub . '" style="color:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . ';">Puedes darte de baja pulsando aquí</a>.</p>';
    $html[] = '    </div>';
    $html[] = '  </div>';
    $html[] = '</div>';
    return implode('', $html);
}
