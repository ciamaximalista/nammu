<?php
/**
 * Nammu — panel de administración.
 * IndexNow: clave, endpoints, registro, cola y envío de URLs.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

function admin_indexnow_searchengines_cache_path(): string {
    return NAMMU_ROOT . '/config/indexnow-searchengines.json';
}

function admin_indexnow_fetch_searchengines(): array {
    $url = 'https://www.indexnow.org/searchengines.json';
    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
        ],
        'https' => [
            'timeout' => 2,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $endpoints = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $endpoint = trim((string) ($entry['endpoint'] ?? ''));
        if ($endpoint === '' || !str_starts_with($endpoint, 'https://')) {
            continue;
        }
        $endpoints[] = $endpoint;
    }
    return array_values(array_unique($endpoints));
}

function admin_indexnow_load_searchengines_cache(): array {
    $path = admin_indexnow_searchengines_cache_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function admin_indexnow_refresh_searchengines_cache_if_needed(): array {
    $cache = admin_indexnow_load_searchengines_cache();
    $updatedAt = (int) ($cache['updated_at'] ?? 0);
    $endpoints = is_array($cache['endpoints'] ?? null) ? $cache['endpoints'] : [];
    $isFresh = $updatedAt > 0 && (time() - $updatedAt) < (30 * 24 * 60 * 60);
    if ($isFresh && !empty($endpoints)) {
        return $endpoints;
    }
    $fetched = admin_indexnow_fetch_searchengines();
    if (!empty($fetched)) {
        $payload = [
            'updated_at' => time(),
            'endpoints' => $fetched,
        ];
        $cacheJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (is_string($cacheJson)) {
            nammu_atomic_write_file(admin_indexnow_searchengines_cache_path(), $cacheJson);
        }
        return $fetched;
    }
    return $endpoints;
}

function admin_indexnow_endpoints(): array {
    $defaults = [
        'https://api.indexnow.org/indexnow',
        'https://indexnow.amazonbot.amazon/indexnow',
        'https://www.bing.com/indexnow',
        'https://searchadvisor.naver.com/indexnow',
        'https://search.seznam.cz/indexnow',
        'https://yandex.com/indexnow',
        'https://indexnow.yep.com/indexnow',
    ];
    $remote = admin_indexnow_refresh_searchengines_cache_if_needed();
    if (empty($remote)) {
        return $defaults;
    }
    $merged = array_values(array_unique(array_merge($defaults, $remote)));
    return $merged;
}

function admin_indexnow_key_filename(string $key): string {
    return 'indexnow-' . $key . '.txt';
}

function admin_indexnow_key_path(string $key, string $filename = ''): string {
    $filename = $filename !== '' ? $filename : admin_indexnow_key_filename($key);
    return NAMMU_ROOT . '/' . $filename;
}

function admin_indexnow_normalize_site_base(string $base): string {
    $base = trim($base);
    if ($base === '') {
        return '';
    }
    $parts = parse_url($base);
    if (!is_array($parts)) {
        return rtrim($base, '/');
    }
    $scheme = $parts['scheme'] ?? '';
    $host = $parts['host'] ?? '';
    if ($scheme === '' || $host === '') {
        return rtrim($base, '/');
    }
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    return rtrim($scheme . '://' . $host . $port, '/');
}

function admin_indexnow_key_url(string $filename, string $baseOverride = ''): string {
    $base = trim($baseOverride);
    if ($base === '') {
        $base = admin_base_url();
    }
    $base = admin_indexnow_normalize_site_base($base);
    $path = '/' . ltrim($filename, '/');
    return $base === '' ? $path : $base . $path;
}

function admin_indexnow_log_path(): string {
    return NAMMU_ROOT . '/config/indexnow-log.json';
}

function admin_indexnow_load_log(): array {
    $path = admin_indexnow_log_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function admin_indexnow_save_log(array $payload): void {
    $path = admin_indexnow_log_path();
    nammu_ensure_directory(dirname($path));
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return;
    }
    nammu_atomic_write_file($path, $json);
}

function admin_indexnow_prepare_config(array &$config): array {
    if (!isset($config['indexnow']) || !is_array($config['indexnow'])) {
        $config['indexnow'] = [];
    }
    $indexnow = $config['indexnow'];
    $updated = false;

    $key = trim((string) ($indexnow['key'] ?? ''));
    if ($key === '') {
        try {
            $key = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $key = '';
        }
        if ($key !== '') {
            $indexnow['key'] = $key;
            $updated = true;
        }
    }

    $keyFile = trim((string) ($indexnow['key_file'] ?? ''));
    if ($keyFile === '' && $key !== '') {
        $keyFile = admin_indexnow_key_filename($key);
        $indexnow['key_file'] = $keyFile;
        $updated = true;
    }

    $config['indexnow'] = $indexnow;

    $keyPath = $key !== '' && $keyFile !== '' ? admin_indexnow_key_path($key, $keyFile) : '';
    $fileOk = false;
    if ($key !== '' && $keyFile !== '') {
        $current = is_file($keyPath) ? trim((string) file_get_contents($keyPath)) : '';
        if ($current === $key) {
            $fileOk = true;
        } elseif (@file_put_contents($keyPath, $key, LOCK_EX) !== false) {
            nammu_apply_shared_permissions($keyPath, 0664, dirname($keyPath));
            $fileOk = true;
        }
    }

    $siteBase = admin_indexnow_normalize_site_base(trim((string) ($config['site_url'] ?? '')));
    $keyUrl = $keyFile !== '' ? admin_indexnow_key_url($keyFile, $siteBase) : '';

    return [
        'key' => $key,
        'key_file' => $keyFile,
        'key_path' => $keyPath,
        'key_url' => $keyUrl,
        'file_ok' => $fileOk,
        'updated' => $updated,
    ];
}

function admin_indexnow_status(): array {
    $config = load_config_file();
    $enabled = (($config['indexnow']['enabled'] ?? 'off') === 'on');
    $key = trim((string) ($config['indexnow']['key'] ?? ''));
    $keyFile = trim((string) ($config['indexnow']['key_file'] ?? ''));
    if ($keyFile === '' && $key !== '') {
        $keyFile = admin_indexnow_key_filename($key);
    }
    $keyPath = $key !== '' && $keyFile !== '' ? admin_indexnow_key_path($key, $keyFile) : '';
    $fileOk = $key !== '' && $keyFile !== '' && is_file($keyPath)
        && trim((string) file_get_contents($keyPath)) === $key;
    $siteBase = admin_indexnow_normalize_site_base(trim((string) ($config['site_url'] ?? '')));
    $keyUrl = $keyFile !== '' ? admin_indexnow_key_url($keyFile, $siteBase) : '';

    return [
        'enabled' => $enabled,
        'key' => $key,
        'key_file' => $keyFile,
        'key_path' => $keyPath,
        'key_url' => $keyUrl,
        'file_ok' => $fileOk,
    ];
}

function admin_indexnow_queue_file(): string
{
    return NAMMU_ROOT . '/config/indexnow-queue.json';
}

function admin_load_indexnow_queue(): array
{
    $file = admin_indexnow_queue_file();
    if (!is_file($file)) {
        return ['urls' => []];
    }
    $decoded = json_decode((string) @file_get_contents($file), true);
    if (!is_array($decoded)) {
        return ['urls' => []];
    }
    $decoded['urls'] = is_array($decoded['urls'] ?? null) ? array_values(array_filter(array_map('strval', $decoded['urls']))) : [];
    return $decoded;
}

function admin_save_indexnow_queue(array $urls): void
{
    $file = admin_indexnow_queue_file();
    $dir = dirname($file);
    nammu_ensure_directory($dir);
    $payload = [
        'updated_at' => time(),
        'urls' => array_values(array_unique(array_filter(array_map('strval', $urls)))),
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

function admin_enqueue_indexnow_urls(array $urls): void
{
    $urls = array_values(array_unique(array_filter(array_map(static function ($url) {
        $url = trim((string) $url);
        return preg_match('#^https?://#i', $url) ? $url : '';
    }, $urls))));
    if (empty($urls)) {
        return;
    }
    $queue = admin_load_indexnow_queue();
    $queuedUrls = is_array($queue['urls'] ?? null) ? $queue['urls'] : [];
    admin_save_indexnow_queue(array_merge($queuedUrls, $urls));
}

function admin_process_indexnow_queue(int $maxUrls = 50): array
{
    $queue = admin_load_indexnow_queue();
    $urls = array_values(array_unique(array_filter(array_map('strval', $queue['urls'] ?? []))));
    if (empty($urls)) {
        return ['processed' => 0, 'remaining' => 0];
    }
    $batch = array_slice($urls, 0, max(1, $maxUrls));
    $remaining = array_slice($urls, count($batch));
    admin_maybe_send_indexnow($batch);
    admin_save_indexnow_queue($remaining);
    return ['processed' => count($batch), 'remaining' => count($remaining)];
}

function admin_maybe_send_indexnow(array $urls): void {
    $urls = array_values(array_unique(array_filter(array_map(static function ($url) {
        $url = trim((string) $url);
        return preg_match('#^https?://#i', $url) ? $url : '';
    }, $urls))));
    if (empty($urls)) {
        return;
    }
    if (PHP_SAPI !== 'cli') {
        admin_enqueue_indexnow_urls($urls);
        return;
    }

    $config = load_config_file();
    if (($config['indexnow']['enabled'] ?? 'off') !== 'on') {
        return;
    }

    $status = admin_indexnow_prepare_config($config);
    if (!empty($status['updated'])) {
        save_config_file($config);
    }

    $key = $status['key'] ?? '';
    $keyUrl = $status['key_url'] ?? '';
    if ($key === '') {
        return;
    }

    $host = '';
    $siteBase = admin_indexnow_normalize_site_base(trim((string) ($config['site_url'] ?? '')));
    $base = $siteBase !== '' ? rtrim($siteBase, '/') : admin_base_url();
    if ($base !== '') {
        $host = parse_url($base, PHP_URL_HOST) ?: '';
    }
    if ($host === '' && !empty($urls[0])) {
        $host = parse_url($urls[0], PHP_URL_HOST) ?: '';
    }
    if ($host === '') {
        return;
    }
    if ($siteBase !== '' && $status['key_file'] ?? '' !== '') {
        $keyUrl = admin_indexnow_key_url($status['key_file'], $siteBase);
    }

    $siteHost = $host !== '' ? strtolower($host) : '';
    $normalizedUrls = [];
    foreach ($urls as $url) {
        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            continue;
        }
        $urlHost = strtolower((string) ($parsed['host'] ?? ''));
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        if ($siteHost !== '' && $urlHost !== '' && $urlHost !== $siteHost) {
            $url = rtrim($base, '/') . $path . $query;
        } elseif ($siteHost !== '' && $urlHost === '') {
            $url = rtrim($base, '/') . $path . $query;
        }
        $normalizedUrls[] = $url;
    }
    if (!empty($normalizedUrls)) {
        $urls = $normalizedUrls;
    }

    $headers = ['Content-Type: application/json; charset=UTF-8'];
    $errors = [];
    $responses = [];
    $keyLocationHost = $keyUrl !== '' ? parse_url($keyUrl, PHP_URL_HOST) : '';
    $payloadHost = $keyLocationHost !== '' ? $keyLocationHost : $host;
    $payloadBase = [
        'host' => $payloadHost,
        'key' => $key,
        'keyLocation' => $keyUrl,
        'urlList' => $urls,
    ];
    foreach (admin_indexnow_endpoints() as $endpoint) {
        $payload = $payloadBase;
        $body = json_encode($payload);
        if ($body === false) {
            continue;
        }
        $httpCode = 0;
        $responseBody = admin_http_post_body_response($endpoint, $body, $headers, $httpCode);
        $responseText = is_string($responseBody) ? trim($responseBody) : '';
        $responseSnippet = $responseText !== '' ? mb_substr($responseText, 0, 240, 'UTF-8') : '';
        $decoded = null;
        if ($responseText !== '') {
            $decoded = json_decode($responseText, true);
        }
        $hasErrorPayload = is_array($decoded) && (isset($decoded['error']) || isset($decoded['errors']));
        $ok = $httpCode >= 200 && $httpCode < 300 && !$hasErrorPayload;

        if (!$ok && $endpoint === 'https://indexnow.amazonbot.amazon/indexnow' && $keyUrl !== '') {
            $altKeyUrl = $keyUrl;
            if (str_starts_with($altKeyUrl, 'https://')) {
                $altKeyUrl = 'http://' . substr($altKeyUrl, strlen('https://'));
            } elseif (str_starts_with($altKeyUrl, 'http://')) {
                $altKeyUrl = 'https://' . substr($altKeyUrl, strlen('http://'));
            }
            if ($altKeyUrl !== $keyUrl) {
                $altHost = parse_url($altKeyUrl, PHP_URL_HOST) ?: $payloadHost;
                $altPayload = [
                    'host' => $altHost,
                    'key' => $key,
                    'keyLocation' => $altKeyUrl,
                    'urlList' => $urls,
                ];
                $altBody = json_encode($altPayload);
                if ($altBody !== false) {
                    $altCode = 0;
                    $altResp = admin_http_post_body_response($endpoint, $altBody, $headers, $altCode);
                    $altText = is_string($altResp) ? trim($altResp) : '';
                    $altDecoded = $altText !== '' ? json_decode($altText, true) : null;
                    $altHasError = is_array($altDecoded) && (isset($altDecoded['error']) || isset($altDecoded['errors']));
                    if ($altCode >= 200 && $altCode < 300 && !$altHasError) {
                        $httpCode = $altCode;
                        $responseText = $altText;
                        $responseSnippet = $altText !== '' ? mb_substr($altText, 0, 240, 'UTF-8') : '';
                        $decoded = $altDecoded;
                        $hasErrorPayload = $altHasError;
                        $ok = true;
                    }
                }
            }
        }
        if (!$ok && $endpoint === 'https://indexnow.amazonbot.amazon/indexnow') {
            $firstUrl = $urls[0] ?? '';
            if ($firstUrl !== '' && $key !== '') {
                $fallbackUrl = $endpoint . '?url=' . rawurlencode($firstUrl) . '&key=' . rawurlencode($key);
                $fallbackCode = 0;
                $fallbackResp = admin_http_post_body_response($fallbackUrl, '', [], $fallbackCode, 'GET');
                $fallbackText = is_string($fallbackResp) ? trim($fallbackResp) : '';
                $fallbackDecoded = $fallbackText !== '' ? json_decode($fallbackText, true) : null;
                $fallbackHasError = is_array($fallbackDecoded) && (isset($fallbackDecoded['error']) || isset($fallbackDecoded['errors']));
                if ($fallbackCode >= 200 && $fallbackCode < 300 && !$fallbackHasError) {
                    $httpCode = $fallbackCode;
                    $responseText = $fallbackText;
                    $responseSnippet = $fallbackText !== '' ? mb_substr($fallbackText, 0, 240, 'UTF-8') : '';
                    $decoded = $fallbackDecoded;
                    $hasErrorPayload = $fallbackHasError;
                    $ok = true;
                }
            }
        }

        $responses[] = [
            'endpoint' => $endpoint,
            'status' => (int) $httpCode,
            'ok' => $ok,
        ];
        if (!$ok) {
            $message = '';
            if (is_array($decoded)) {
                if (isset($decoded['error'])) {
                    $message = is_array($decoded['error']) ? (string) ($decoded['error']['message'] ?? '') : (string) $decoded['error'];
                } elseif (isset($decoded['errors']) && is_array($decoded['errors'])) {
                    $firstError = $decoded['errors'][0] ?? null;
                    if (is_array($firstError)) {
                        $message = (string) ($firstError['message'] ?? '');
                    }
                }
            }
            if ($message === '' && $responseSnippet !== '') {
                $message = $responseSnippet;
            }
            $errors[] = [
                'endpoint' => $endpoint,
                'status' => (int) $httpCode,
                'message' => $message,
            ];
        }
    }
    admin_indexnow_save_log([
        'timestamp' => time(),
        'errors' => $errors,
        'urls' => $urls,
        'responses' => $responses,
    ]);
}
