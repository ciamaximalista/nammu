<?php

declare(strict_types=1);

function nammu_webmention_store_file(): string
{
    return dirname(__DIR__) . '/config/webmentions.json';
}

function nammu_webmention_queue_file(): string
{
    return dirname(__DIR__) . '/config/webmention-queue.json';
}

function nammu_webmention_state_file(): string
{
    return dirname(__DIR__) . '/config/webmention-state.json';
}

function nammu_webmention_load_store(string $file, array $default): array
{
    if (!is_file($file)) {
        return $default;
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $default;
}

function nammu_webmention_save_store(string $file, array $data): void
{
    $directory = dirname($file);
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function nammu_webmention_store(): array
{
    $store = nammu_webmention_load_store(nammu_webmention_store_file(), ['items' => []]);
    $store['items'] = is_array($store['items'] ?? null) ? $store['items'] : [];
    return $store;
}

function nammu_webmention_queue_store(): array
{
    $store = nammu_webmention_load_store(nammu_webmention_queue_file(), ['items' => []]);
    $store['items'] = is_array($store['items'] ?? null) ? $store['items'] : [];
    return $store;
}

function nammu_webmention_state_store(): array
{
    $store = nammu_webmention_load_store(nammu_webmention_state_file(), ['sources' => []]);
    $store['sources'] = is_array($store['sources'] ?? null) ? $store['sources'] : [];
    return $store;
}

function nammu_webmention_save_queue_store(array $items): void
{
    nammu_webmention_save_store(nammu_webmention_queue_file(), ['items' => array_values($items)]);
}

function nammu_webmention_save_state_store(array $sources): void
{
    nammu_webmention_save_store(nammu_webmention_state_file(), ['sources' => $sources]);
}

function nammu_webmention_endpoint_url(array $config): string
{
    $baseUrl = trim((string) ($config['site_url'] ?? ''));
    if ($baseUrl === '') {
        $baseUrl = nammu_base_url();
    }
    return rtrim($baseUrl, '/') . '/webmention';
}

function nammu_webmention_normalize_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $parts = @parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }
    $scheme = strtolower(trim((string) ($parts['scheme'] ?? '')));
    $host = strtolower(trim((string) ($parts['host'] ?? '')));
    $path = (string) ($parts['path'] ?? '');
    $query = trim((string) ($parts['query'] ?? ''));
    if ($scheme === '' || $host === '') {
        return $url;
    }
    $normalized = $scheme . '://' . $host;
    $port = (int) ($parts['port'] ?? 0);
    if ($port > 0 && !in_array([$scheme, $port], [['http', 80], ['https', 443]], true)) {
        $normalized .= ':' . $port;
    }
    $normalized .= $path !== '' ? $path : '/';
    if ($query !== '') {
        $normalized .= '?' . $query;
    }
    return $normalized;
}

function nammu_webmention_same_url(string $a, string $b): bool
{
    return nammu_webmention_normalize_url($a) === nammu_webmention_normalize_url($b);
}

function nammu_webmention_is_external_url(string $url, array $config): bool
{
    $url = nammu_webmention_normalize_url($url);
    if ($url === '') {
        return false;
    }
    $baseUrl = nammu_webmention_normalize_url(trim((string) (($config['site_url'] ?? '') ?: nammu_base_url())));
    if ($baseUrl === '') {
        return true;
    }
    return !str_starts_with($url, rtrim($baseUrl, '/'));
}

function nammu_webmention_target_is_local(string $target, array $config): bool
{
    $target = nammu_webmention_normalize_url($target);
    if ($target === '') {
        return false;
    }
    $baseUrl = nammu_webmention_normalize_url(trim((string) (($config['site_url'] ?? '') ?: nammu_base_url())));
    if ($baseUrl === '') {
        return false;
    }
    if (!str_starts_with($target, rtrim($baseUrl, '/'))) {
        return false;
    }
    $path = (string) (parse_url($target, PHP_URL_PATH) ?? '/');
    if ($path === '/admin.php' || str_starts_with($path, '/admin.php')) {
        return false;
    }
    return true;
}

function nammu_webmention_http_request(string $url, string $method = 'GET', array $headers = [], string $body = '', int $timeout = 10): array
{
    $method = strtoupper(trim($method));
    $responseHeaders = [];
    $status = 0;
    $responseBody = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($ch, string $header) use (&$responseHeaders): int {
            $trimmed = trim($header);
            if ($trimmed !== '') {
                $responseHeaders[] = $trimmed;
            }
            return strlen($header);
        });
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $responseBody = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $responseBody];
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'timeout' => $timeout,
            'header' => implode("\r\n", $headers),
            'content' => $body,
        ],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $rawHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
    foreach ($rawHeaders as $header) {
        $responseHeaders[] = trim((string) $header);
    }
    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $matches) === 1) {
            $status = (int) ($matches[1] ?? 0);
        }
    }
    return ['status' => $status, 'headers' => $responseHeaders, 'body' => is_string($responseBody) ? $responseBody : ''];
}

function nammu_webmention_discover_endpoint_from_headers(array $headers): string
{
    foreach ($headers as $headerLine) {
        if (stripos((string) $headerLine, 'Link:') !== 0) {
            continue;
        }
        $value = trim(substr((string) $headerLine, 5));
        foreach (explode(',', $value) as $part) {
            if (preg_match('#<([^>]+)>\s*;\s*rel="?([^";]+)"?#i', trim($part), $matches) !== 1) {
                continue;
            }
            $href = trim((string) ($matches[1] ?? ''));
            $rels = preg_split('/\s+/', strtolower(trim((string) ($matches[2] ?? '')))) ?: [];
            if ($href !== '' && in_array('webmention', $rels, true)) {
                return $href;
            }
        }
    }
    return '';
}

function nammu_webmention_discover_endpoint_from_html(string $html, string $targetUrl): string
{
    if (trim($html) === '') {
        return '';
    }
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    foreach (['//link[@href]', '//a[@href]'] as $query) {
        foreach ($xpath->query($query) ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $rel = strtolower(trim((string) $node->getAttribute('rel')));
            if ($rel === '' || !in_array('webmention', preg_split('/\s+/', $rel) ?: [], true)) {
                continue;
            }
            $href = trim((string) $node->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            return nammu_webmention_resolve_relative_url($href, $targetUrl);
        }
    }
    return '';
}

function nammu_webmention_resolve_relative_url(string $href, string $baseUrl): string
{
    $href = trim($href);
    if ($href === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $href)) {
        return $href;
    }
    $parts = @parse_url($baseUrl);
    if (!is_array($parts)) {
        return $href;
    }
    $scheme = trim((string) ($parts['scheme'] ?? 'https'));
    $host = trim((string) ($parts['host'] ?? ''));
    if ($host === '') {
        return $href;
    }
    $root = $scheme . '://' . $host;
    $port = (int) ($parts['port'] ?? 0);
    if ($port > 0) {
        $root .= ':' . $port;
    }
    if (str_starts_with($href, '/')) {
        return $root . $href;
    }
    $path = (string) ($parts['path'] ?? '/');
    $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');
    return $root . ($directory !== '' ? $directory : '') . '/' . ltrim($href, '/');
}

function nammu_webmention_discover_endpoint(string $targetUrl): string
{
    $head = nammu_webmention_http_request($targetUrl, 'HEAD', ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
    $endpoint = nammu_webmention_discover_endpoint_from_headers((array) ($head['headers'] ?? []));
    if ($endpoint !== '') {
        return $endpoint;
    }
    $get = nammu_webmention_http_request($targetUrl, 'GET', ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
    $endpoint = nammu_webmention_discover_endpoint_from_headers((array) ($get['headers'] ?? []));
    if ($endpoint !== '') {
        return $endpoint;
    }
    return nammu_webmention_discover_endpoint_from_html((string) ($get['body'] ?? ''), $targetUrl);
}

function nammu_webmention_extract_links_from_html(string $html, string $baseUrl = ''): array
{
    if (trim($html) === '') {
        return [];
    }
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $links = [];
    foreach ($xpath->query('//a[@href]') ?: [] as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }
        $href = trim((string) $node->getAttribute('href'));
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            continue;
        }
        if ($baseUrl !== '') {
            $href = nammu_webmention_resolve_relative_url($href, $baseUrl);
        }
        $links[] = nammu_webmention_normalize_url($href);
    }
    return array_values(array_unique(array_filter($links)));
}

function nammu_webmention_content_sources(array $config): array
{
    $baseUrl = rtrim(trim((string) (($config['site_url'] ?? '') ?: nammu_base_url())), '/');
    $contentDir = dirname(__DIR__) . '/content';
    $itinerariesDir = dirname(__DIR__) . '/itinerarios';
    $sources = [];

    if (is_dir($contentDir) && class_exists(\Nammu\Core\ContentRepository::class)) {
        $repository = new \Nammu\Core\ContentRepository($contentDir);
        foreach ($repository->all() as $post) {
            if (!$post instanceof \Nammu\Core\Post || $post->isDraft()) {
                continue;
            }
            $slug = trim((string) $post->getSlug());
            if ($slug === '') {
                continue;
            }
            $file = $contentDir . '/' . $slug . '.md';
            $sources[] = [
                'url' => $baseUrl . '/' . rawurlencode($slug),
                'fingerprint' => $slug . '|' . ((string) (@filemtime($file) ?: 0)),
            ];
        }
        foreach (nammu_collect_podcast_items($contentDir, $baseUrl) as $episode) {
            $url = trim((string) ($episode['page_url'] ?? ''));
            $slug = trim((string) ($episode['slug'] ?? ''));
            if ($url === '' || $slug === '') {
                continue;
            }
            $file = $contentDir . '/' . $slug . '.md';
            $sources[] = [
                'url' => $url,
                'fingerprint' => 'podcast|' . $slug . '|' . ((string) (@filemtime($file) ?: 0)),
            ];
        }
    }

    if (is_dir($itinerariesDir) && class_exists(\Nammu\Core\ItineraryRepository::class)) {
        $repository = new \Nammu\Core\ItineraryRepository($itinerariesDir);
        foreach ($repository->all() as $itinerary) {
            if (!$itinerary instanceof \Nammu\Core\Itinerary || !$itinerary->isPublished()) {
                continue;
            }
            $slug = trim((string) $itinerary->getSlug());
            if ($slug === '') {
                continue;
            }
            $file = $itinerariesDir . '/' . $slug . '/index.md';
            $sources[] = [
                'url' => $baseUrl . '/itinerarios/' . rawurlencode($slug),
                'fingerprint' => 'itinerary|' . $slug . '|' . ((string) (@filemtime($file) ?: 0)),
            ];
        }
    }

    if (!function_exists('nammu_actuality_items_file') && is_file(dirname(__DIR__) . '/core/actualidad.php')) {
        require_once dirname(__DIR__) . '/core/actualidad.php';
    }
    if (!function_exists('nammu_fediverse_public_thread_url_for_actuality_item') && is_file(dirname(__DIR__) . '/core/fediverso.php')) {
        require_once dirname(__DIR__) . '/core/fediverso.php';
    }
    if (function_exists('nammu_actuality_items_file') && function_exists('nammu_fediverse_public_thread_url_for_actuality_item')) {
        $actualityStore = nammu_webmention_load_store(nammu_actuality_items_file(), ['items' => []]);
        foreach ((array) ($actualityStore['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (!empty($item['is_site_content'])) {
                continue;
            }
            if (!empty($item['is_manual']) && strtolower(trim((string) ($item['via'] ?? ''))) === 'boost') {
                continue;
            }
            $isNews = trim((string) ($item['source_kind'] ?? '')) === 'news';
            $isManualNote = !empty($item['is_manual']);
            if (!$isNews && !$isManualNote) {
                continue;
            }
            $sourceUrl = trim((string) nammu_fediverse_public_thread_url_for_actuality_item($item, $config));
            $itemId = trim((string) ($item['id'] ?? ''));
            if ($sourceUrl === '' || $itemId === '') {
                continue;
            }
            $timestamp = (int) ($item['timestamp'] ?? 0);
            $sources[] = [
                'url' => $sourceUrl,
                'fingerprint' => 'actuality|' . $itemId . '|' . $timestamp,
            ];
        }
    }

    return $sources;
}

function nammu_webmention_enqueue_item(string $sourceUrl, string $targetUrl, string $endpointUrl): bool
{
    $sourceUrl = nammu_webmention_normalize_url($sourceUrl);
    $targetUrl = nammu_webmention_normalize_url($targetUrl);
    $endpointUrl = nammu_webmention_normalize_url($endpointUrl);
    if ($sourceUrl === '' || $targetUrl === '' || $endpointUrl === '') {
        return false;
    }
    $store = nammu_webmention_queue_store();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $signature = sha1($sourceUrl . '|' . $targetUrl . '|' . $endpointUrl);
    foreach ($items as $item) {
        if (trim((string) ($item['signature'] ?? '')) === $signature) {
            return false;
        }
    }
    $items[] = [
        'signature' => $signature,
        'source' => $sourceUrl,
        'target' => $targetUrl,
        'endpoint' => $endpointUrl,
        'created_at' => gmdate(DATE_ATOM),
        'attempts' => 0,
    ];
    nammu_webmention_save_queue_store($items);
    return true;
}

function nammu_webmention_sync_sources(array $config, int $limit = 4): array
{
    $sources = nammu_webmention_content_sources($config);
    $stateStore = nammu_webmention_state_store();
    $state = is_array($stateStore['sources'] ?? null) ? $stateStore['sources'] : [];
    usort($sources, static function (array $a, array $b) use ($state): int {
        $checkedA = trim((string) (($state[$a['url']]['checked_at'] ?? '')));
        $checkedB = trim((string) (($state[$b['url']]['checked_at'] ?? '')));
        return strcmp($checkedA, $checkedB);
    });

    $scanned = 0;
    $enqueued = 0;
    foreach (array_slice($sources, 0, max(1, $limit)) as $source) {
        $sourceUrl = trim((string) ($source['url'] ?? ''));
        $fingerprint = trim((string) ($source['fingerprint'] ?? ''));
        if ($sourceUrl === '' || $fingerprint === '') {
            continue;
        }
        $scanned++;
        $existing = is_array($state[$sourceUrl] ?? null) ? $state[$sourceUrl] : [];
        $state[$sourceUrl] = [
            'fingerprint' => $fingerprint,
            'checked_at' => gmdate(DATE_ATOM),
            'last_error' => '',
        ];
        if (trim((string) ($existing['fingerprint'] ?? '')) === $fingerprint) {
            continue;
        }
        $response = nammu_webmention_http_request($sourceUrl, 'GET', ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
        $body = (string) ($response['body'] ?? '');
        $status = (int) ($response['status'] ?? 0);
        if ($status < 200 || $status >= 300 || trim($body) === '') {
            $state[$sourceUrl]['last_error'] = 'No se pudo leer la página fuente.';
            continue;
        }
        $externalLinks = [];
        foreach (nammu_webmention_extract_links_from_html($body, $sourceUrl) as $targetUrl) {
            if (!nammu_webmention_is_external_url($targetUrl, $config)) {
                continue;
            }
            $externalLinks[] = $targetUrl;
        }
        $externalLinks = array_values(array_unique($externalLinks));
        foreach ($externalLinks as $targetUrl) {
            $endpoint = nammu_webmention_discover_endpoint($targetUrl);
            if ($endpoint === '') {
                continue;
            }
            if (nammu_webmention_enqueue_item($sourceUrl, $targetUrl, $endpoint)) {
                $enqueued++;
            }
        }
    }
    nammu_webmention_save_state_store($state);
    $queueStore = nammu_webmention_queue_store();
    return [
        'scanned' => $scanned,
        'enqueued' => $enqueued,
        'remaining' => count((array) ($queueStore['items'] ?? [])),
    ];
}

function nammu_webmention_process_queue(array $config, int $limit = 4): array
{
    $store = nammu_webmention_queue_store();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $processed = 0;
    $sent = 0;
    $failed = 0;
    foreach ($items as $index => $item) {
        if ($processed >= max(1, $limit)) {
            break;
        }
        $source = trim((string) ($item['source'] ?? ''));
        $target = trim((string) ($item['target'] ?? ''));
        $endpoint = trim((string) ($item['endpoint'] ?? ''));
        if ($source === '' || $target === '' || $endpoint === '') {
            unset($items[$index]);
            continue;
        }
        $processed++;
        $response = nammu_webmention_http_request(
            $endpoint,
            'POST',
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['source' => $source, 'target' => $target], '', '&', PHP_QUERY_RFC3986)
        );
        $status = (int) ($response['status'] ?? 0);
        if ($status >= 200 && $status < 300) {
            unset($items[$index]);
            $sent++;
            continue;
        }
        $items[$index]['attempts'] = (int) (($items[$index]['attempts'] ?? 0)) + 1;
        $items[$index]['last_error'] = 'HTTP ' . $status;
        $items[$index]['last_attempt_at'] = gmdate(DATE_ATOM);
        if ((int) ($items[$index]['attempts'] ?? 0) >= 5) {
            unset($items[$index]);
        }
        $failed++;
    }
    nammu_webmention_save_queue_store(array_values($items));
    return [
        'processed' => $processed,
        'sent' => $sent,
        'failed' => $failed,
        'remaining' => count(array_values($items)),
    ];
}

function nammu_webmention_extract_title(string $html): string
{
    if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $matches) === 1) {
        return trim(html_entity_decode(strip_tags((string) ($matches[1] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return '';
}

function nammu_webmention_extract_excerpt(string $html): string
{
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '') {
        return '';
    }
    return mb_substr(preg_replace('/\s+/u', ' ', $text) ?? $text, 0, 280, 'UTF-8');
}

function nammu_webmention_extract_blog_name(string $html, string $sourceUrl): string
{
    if (preg_match('#<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $matches) === 1) {
        return trim(html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('#<meta[^>]+name=["\']application-name["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $matches) === 1) {
        return trim(html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    $host = (string) (parse_url($sourceUrl, PHP_URL_HOST) ?? '');
    return $host !== '' ? $host : $sourceUrl;
}

function nammu_webmention_extract_blog_icon(string $html, string $sourceUrl): string
{
    if (preg_match('#<link[^>]+rel=["\'][^"\']*icon[^"\']*["\'][^>]+href=["\']([^"\']+)["\']#i', $html, $matches) === 1) {
        return nammu_webmention_resolve_relative_url((string) ($matches[1] ?? ''), $sourceUrl);
    }
    if (preg_match('#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $matches) === 1) {
        return nammu_webmention_resolve_relative_url((string) ($matches[1] ?? ''), $sourceUrl);
    }
    return '';
}

function nammu_webmention_list(): array
{
    $store = nammu_webmention_store();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['received_at'] ?? ''), (string) ($a['received_at'] ?? ''));
    });
    return $items;
}

function nammu_webmention_mentions_for_target(string $targetUrl): array
{
    $targetUrl = nammu_webmention_normalize_url($targetUrl);
    if ($targetUrl === '') {
        return [];
    }
    $matches = [];
    foreach (nammu_webmention_list() as $mention) {
        if (nammu_webmention_same_url((string) ($mention['target'] ?? ''), $targetUrl)) {
            $matches[] = $mention;
        }
    }
    return $matches;
}

function nammu_webmention_delete(string $signature): bool
{
    $signature = trim($signature);
    if ($signature === '') {
        return false;
    }
    $store = nammu_webmention_store();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $filtered = array_values(array_filter($items, static function (array $item) use ($signature): bool {
        return trim((string) ($item['signature'] ?? '')) !== $signature;
    }));
    if (count($filtered) === count($items)) {
        return false;
    }
    nammu_webmention_save_store(nammu_webmention_store_file(), ['items' => $filtered]);
    return true;
}

function nammu_webmention_receive(string $sourceUrl, string $targetUrl, array $config): array
{
    $sourceUrl = nammu_webmention_normalize_url($sourceUrl);
    $targetUrl = nammu_webmention_normalize_url($targetUrl);
    if ($sourceUrl === '' || $targetUrl === '') {
        return ['ok' => false, 'status' => 400, 'message' => 'Faltan source o target.'];
    }
    if (!nammu_webmention_target_is_local($targetUrl, $config)) {
        return ['ok' => false, 'status' => 400, 'message' => 'El target no pertenece a este sitio.'];
    }
    $response = nammu_webmention_http_request($sourceUrl, 'GET', ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
    $status = (int) ($response['status'] ?? 0);
    $html = (string) ($response['body'] ?? '');
    if ($status < 200 || $status >= 300 || trim($html) === '') {
        return ['ok' => false, 'status' => 400, 'message' => 'No se pudo verificar la página fuente.'];
    }
    $links = nammu_webmention_extract_links_from_html($html, $sourceUrl);
    $linked = false;
    foreach ($links as $link) {
        if (nammu_webmention_same_url($link, $targetUrl)) {
            $linked = true;
            break;
        }
    }
    if (!$linked) {
        return ['ok' => false, 'status' => 400, 'message' => 'La página fuente no enlaza al target indicado.'];
    }
    $store = nammu_webmention_store();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $signature = sha1($sourceUrl . '|' . $targetUrl);
    $record = [
        'signature' => $signature,
        'source' => $sourceUrl,
        'target' => $targetUrl,
        'received_at' => gmdate(DATE_ATOM),
        'blog_name' => nammu_webmention_extract_blog_name($html, $sourceUrl),
        'blog_icon' => nammu_webmention_extract_blog_icon($html, $sourceUrl),
        'source_title' => nammu_webmention_extract_title($html),
        'source_excerpt' => nammu_webmention_extract_excerpt($html),
        'verified' => true,
    ];
    $updated = false;
    foreach ($items as $index => $item) {
        if (trim((string) ($item['signature'] ?? '')) !== $signature) {
            continue;
        }
        $items[$index] = array_merge($item, $record);
        $updated = true;
        break;
    }
    if (!$updated) {
        $items[] = $record;
    }
    nammu_webmention_save_store(nammu_webmention_store_file(), ['items' => array_values($items)]);
    return ['ok' => true, 'status' => 202, 'message' => 'Webmention aceptado y verificado.'];
}
