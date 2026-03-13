<?php

declare(strict_types=1);

function nammu_actuality_cache_file(): string
{
    return dirname(__DIR__) . '/config/actualidad-cache.json';
}

function nammu_actuality_cache_dir(): string
{
    return dirname(__DIR__) . '/assets/actualidad-cache';
}

function nammu_actuality_load_cache(): array
{
    $file = nammu_actuality_cache_file();
    if (!is_file($file)) {
        return ['items' => []];
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return ['items' => []];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['items' => []];
}

function nammu_actuality_save_cache(array $cache): void
{
    $file = nammu_actuality_cache_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($file, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @chmod($file, 0664);
}

function nammu_actuality_fetch_url(string $url, string $accept = 'text/html,application/xhtml+xml', int $timeout = 8): array
{
    $headers = [];
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "User-Agent: Nammu Actualidad\r\nAccept: {$accept}\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    foreach (($http_response_header ?? []) as $headerLine) {
        if (!is_string($headerLine) || !str_contains($headerLine, ':')) {
            continue;
        }
        [$name, $value] = explode(':', $headerLine, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }
    return [
        'body' => is_string($body) ? $body : '',
        'headers' => $headers,
    ];
}

function nammu_actuality_resolve_url(string $candidate, string $baseUrl): string
{
    $candidate = trim($candidate);
    if ($candidate === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $candidate)) {
        return $candidate;
    }
    $base = parse_url($baseUrl);
    if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
        return $candidate;
    }
    $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
    if (str_starts_with($candidate, '//')) {
        return $base['scheme'] . ':' . $candidate;
    }
    if (str_starts_with($candidate, '/')) {
        return $origin . $candidate;
    }
    $path = $base['path'] ?? '/';
    $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
    return $origin . ($dir !== '' ? $dir . '/' : '/') . $candidate;
}

function nammu_actuality_extract_social_image(string $pageUrl): string
{
    $response = nammu_actuality_fetch_url($pageUrl);
    $html = trim((string) ($response['body'] ?? ''));
    if ($html === '') {
        return '';
    }
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!@$dom->loadHTML($html)) {
        return '';
    }
    $xpath = new DOMXPath($dom);
    $queries = [
        '//meta[@property="og:image"]/@content',
        '//meta[@name="twitter:image"]/@content',
        '//meta[@name="twitter:image:src"]/@content',
    ];
    foreach ($queries as $query) {
        $nodes = $xpath->query($query);
        if ($nodes instanceof DOMNodeList && $nodes->length > 0) {
            $value = trim((string) $nodes->item(0)?->nodeValue);
            if ($value !== '') {
                return nammu_actuality_resolve_url($value, $pageUrl);
            }
        }
    }
    return '';
}

function nammu_actuality_extension_from_headers(string $url, array $headers): string
{
    $contentType = strtolower((string) ($headers['content-type'] ?? ''));
    if (str_contains($contentType, 'image/webp')) {
        return 'webp';
    }
    if (str_contains($contentType, 'image/png')) {
        return 'png';
    }
    if (str_contains($contentType, 'image/gif')) {
        return 'gif';
    }
    if (str_contains($contentType, 'image/svg')) {
        return 'svg';
    }
    if (str_contains($contentType, 'image/jpeg') || str_contains($contentType, 'image/jpg')) {
        return 'jpg';
    }
    $path = parse_url($url, PHP_URL_PATH);
    $ext = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'jpg';
}

function nammu_actuality_cache_social_image(string $pageUrl, string $imageUrl, string $publicBaseUrl): string
{
    $response = nammu_actuality_fetch_url($imageUrl, 'image/*', 10);
    $body = $response['body'] ?? '';
    if (!is_string($body) || $body === '') {
        return '';
    }
    $headers = is_array($response['headers'] ?? null) ? $response['headers'] : [];
    $contentType = strtolower((string) ($headers['content-type'] ?? ''));
    if ($contentType !== '' && !str_starts_with($contentType, 'image/')) {
        return '';
    }
    $ext = nammu_actuality_extension_from_headers($imageUrl, $headers);
    $dir = nammu_actuality_cache_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $filename = sha1($pageUrl) . '.' . $ext;
    $path = $dir . '/' . $filename;
    if (@file_put_contents($path, $body) === false) {
        return '';
    }
    @chmod($path, 0664);
    $base = rtrim($publicBaseUrl, '/');
    return ($base !== '' ? $base : '') . '/assets/actualidad-cache/' . $filename;
}

function nammu_actuality_prune_cache(array &$cache, array $activeKeys): void
{
    $items = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $dir = nammu_actuality_cache_dir();
    foreach ($items as $key => $entry) {
        $localPath = (string) ($entry['local_path'] ?? '');
        if (!in_array($key, $activeKeys, true)) {
            if ($localPath !== '' && is_file($localPath)) {
                @unlink($localPath);
            }
            unset($items[$key]);
            continue;
        }
        if ($localPath !== '' && !is_file($localPath)) {
            unset($items[$key]);
        }
    }
    if (is_dir($dir)) {
        $files = glob($dir . '/*') ?: [];
        $usedPaths = [];
        foreach ($items as $entry) {
            $localPath = (string) ($entry['local_path'] ?? '');
            if ($localPath !== '') {
                $usedPaths[] = $localPath;
            }
        }
        foreach ($files as $file) {
            if (!in_array($file, $usedPaths, true)) {
                @unlink($file);
            }
        }
    }
    $cache['items'] = $items;
}

function nammu_actuality_enrich_items(array $items, string $publicBaseUrl): array
{
    $cache = nammu_actuality_load_cache();
    $cache['items'] = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $activeKeys = [];
    foreach ($items as $index => $item) {
        $link = trim((string) ($item['link'] ?? ''));
        if ($link === '') {
            continue;
        }
        $key = sha1($link);
        $activeKeys[] = $key;
        if (trim((string) ($item['image'] ?? '')) !== '') {
            continue;
        }
        $entry = is_array($cache['items'][$key] ?? null) ? $cache['items'][$key] : [];
        $localPath = (string) ($entry['local_path'] ?? '');
        $cachedUrl = (string) ($entry['public_url'] ?? '');
        if ($localPath !== '' && is_file($localPath) && $cachedUrl !== '') {
            $items[$index]['image'] = $cachedUrl;
            $cache['items'][$key]['last_used'] = time();
            continue;
        }
        $socialImage = nammu_actuality_extract_social_image($link);
        if ($socialImage === '') {
            continue;
        }
        $cachedPublicUrl = nammu_actuality_cache_social_image($link, $socialImage, $publicBaseUrl);
        if ($cachedPublicUrl === '') {
            continue;
        }
        $path = parse_url($cachedPublicUrl, PHP_URL_PATH);
        $localCachedPath = $path !== null && $path !== '' ? dirname(__DIR__) . $path : '';
        $items[$index]['image'] = $cachedPublicUrl;
        $cache['items'][$key] = [
            'page_url' => $link,
            'source_image' => $socialImage,
            'public_url' => $cachedPublicUrl,
            'local_path' => $localCachedPath,
            'last_used' => time(),
        ];
    }
    nammu_actuality_prune_cache($cache, array_values(array_unique($activeKeys)));
    nammu_actuality_save_cache($cache);
    return $items;
}
