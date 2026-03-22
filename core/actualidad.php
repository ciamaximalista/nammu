<?php

declare(strict_types=1);

function nammu_actuality_cache_file(): string
{
    return dirname(__DIR__) . '/config/actualidad-cache.json';
}

function nammu_actuality_items_file(): string
{
    return dirname(__DIR__) . '/config/actualidad-items.json';
}

function nammu_actuality_manual_items_file(): string
{
    return dirname(__DIR__) . '/config/actualidad-manual.json';
}

function nammu_actuality_news_overrides_file(): string
{
    return dirname(__DIR__) . '/config/actualidad-news-overrides.json';
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

function nammu_actuality_load_items_snapshot(): array
{
    $file = nammu_actuality_items_file();
    if (!is_file($file)) {
        return ['updated_at' => 0, 'items' => []];
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return ['updated_at' => 0, 'items' => []];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['updated_at' => 0, 'items' => []];
    }
    $decoded['items'] = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
    $decoded['updated_at'] = (int) ($decoded['updated_at'] ?? 0);
    return $decoded;
}

function nammu_actuality_save_items_snapshot(array $items): void
{
    $file = nammu_actuality_items_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $payload = [
        'updated_at' => time(),
        'items' => array_values($items),
    ];
    @file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @chmod($file, 0664);
}

function nammu_actuality_load_manual_items(): array
{
    $file = nammu_actuality_manual_items_file();
    if (!is_file($file)) {
        return ['items' => []];
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return ['items' => []];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['items' => []];
    }
    $decoded['items'] = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
    return $decoded;
}

function nammu_actuality_save_manual_items(array $items): void
{
    $file = nammu_actuality_manual_items_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $payload = [
        'updated_at' => time(),
        'items' => array_values($items),
    ];
    @file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @chmod($file, 0664);
}

function nammu_actuality_load_news_overrides(): array
{
    $file = nammu_actuality_news_overrides_file();
    if (!is_file($file)) {
        return ['items' => []];
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return ['items' => []];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['items' => []];
    }
    $decoded['items'] = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
    return $decoded;
}

function nammu_actuality_save_news_overrides(array $items): void
{
    $file = nammu_actuality_news_overrides_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $payload = [
        'updated_at' => time(),
        'items' => array_values($items),
    ];
    @file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @chmod($file, 0664);
}

function nammu_actuality_news_item_id(array $item): string
{
    $base = trim((string) ($item['feed_item_key'] ?? ''));
    if ($base === '') {
        $base = trim((string) ($item['key'] ?? ''));
    }
    if ($base === '') {
        $base = trim((string) ($item['link'] ?? '')) . '|' . trim((string) ($item['title'] ?? ''));
    }
    if ($base === '') {
        return '';
    }
    return substr(sha1('news|' . $base), 0, 16);
}

function nammu_actuality_list_news_items(): array
{
    $snapshot = nammu_actuality_load_items_snapshot();
    $items = [];
    foreach ((array) ($snapshot['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (trim((string) ($item['source_kind'] ?? '')) !== 'news') {
            continue;
        }
        $items[] = $item;
    }
    usort($items, static function (array $a, array $b): int {
        return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
    });
    return $items;
}

function nammu_actuality_get_news_item(string $id): ?array
{
    $normalizedId = preg_replace('/[^a-f0-9]/i', '', trim($id)) ?? '';
    if ($normalizedId === '') {
        return null;
    }
    foreach (nammu_actuality_list_news_items() as $item) {
        if ((string) ($item['id'] ?? '') === $normalizedId) {
            return $item;
        }
    }
    return null;
}

function nammu_actuality_has_persisted_news_items(): bool
{
    $snapshot = nammu_actuality_load_items_snapshot();
    foreach ((array) ($snapshot['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (trim((string) ($item['source_kind'] ?? '')) === 'news') {
            return true;
        }
    }
    return false;
}

function nammu_actuality_update_news_item(string $id, string $title, string $description, string $link, string $baseUrl, ?string $image = null, ?array $images = null): bool
{
    $normalizedId = preg_replace('/[^a-f0-9]/i', '', trim($id)) ?? '';
    if ($normalizedId === '') {
        return false;
    }
    $current = nammu_actuality_get_news_item($normalizedId);
    if ($current === null) {
        return false;
    }
    $title = trim($title);
    $description = nammu_actuality_manual_plain_text($description);
    $link = trim($link);
    if ($title === '' || $link === '') {
        return false;
    }
    $store = nammu_actuality_load_news_overrides();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $updated = false;
    foreach ($items as &$item) {
        if ((string) ($item['id'] ?? '') !== $normalizedId) {
            continue;
        }
        $normalizedImages = $images !== null
            ? nammu_actuality_manual_images($images, $baseUrl)
            : nammu_actuality_manual_images((array) ($item['images'] ?? ($current['images'] ?? [])), $baseUrl);
        $primaryImage = $image !== null ? nammu_actuality_manual_image_url(trim($image), $baseUrl) : '';
        if ($primaryImage !== '') {
            array_unshift($normalizedImages, $primaryImage);
        }
        $normalizedImages = array_values(array_unique(array_filter($normalizedImages)));
        $item['title'] = $title;
        $item['description'] = $description;
        $item['raw_text'] = $description;
        $item['link'] = $link;
        $item['image'] = $normalizedImages[0] ?? '';
        $item['images'] = $normalizedImages;
        $item['deleted'] = false;
        $item['updated_at'] = time();
        $updated = true;
        break;
    }
    unset($item);
    if (!$updated) {
        $normalizedImages = $images !== null
            ? nammu_actuality_manual_images($images, $baseUrl)
            : nammu_actuality_manual_images((array) ($current['images'] ?? []), $baseUrl);
        $primaryImage = $image !== null ? nammu_actuality_manual_image_url(trim($image), $baseUrl) : '';
        if ($primaryImage !== '') {
            array_unshift($normalizedImages, $primaryImage);
        }
        $normalizedImages = array_values(array_unique(array_filter($normalizedImages)));
        $items[] = [
            'id' => $normalizedId,
            'title' => $title,
            'description' => $description,
            'raw_text' => $description,
            'link' => $link,
            'image' => $normalizedImages[0] ?? '',
            'images' => $normalizedImages,
            'deleted' => false,
            'updated_at' => time(),
        ];
    }
    nammu_actuality_save_news_overrides($items);
    return true;
}

function nammu_actuality_delete_news_item(string $id): bool
{
    $normalizedId = preg_replace('/[^a-f0-9]/i', '', trim($id)) ?? '';
    if ($normalizedId === '') {
        return false;
    }
    if (nammu_actuality_get_news_item($normalizedId) === null) {
        return false;
    }
    $store = nammu_actuality_load_news_overrides();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $updated = false;
    foreach ($items as &$item) {
        if ((string) ($item['id'] ?? '') !== $normalizedId) {
            continue;
        }
        $item['deleted'] = true;
        $item['updated_at'] = time();
        $updated = true;
        break;
    }
    unset($item);
    if (!$updated) {
        $items[] = [
            'id' => $normalizedId,
            'deleted' => true,
            'updated_at' => time(),
        ];
    }
    nammu_actuality_save_news_overrides($items);
    return true;
}

function nammu_actuality_list_manual_items(): array
{
    $store = nammu_actuality_load_manual_items();
    $items = nammu_actuality_prune_manual_items(is_array($store['items'] ?? null) ? $store['items'] : []);
    usort($items, static function (array $a, array $b): int {
        return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
    });
    if (count($items) !== count((array) ($store['items'] ?? []))) {
        nammu_actuality_save_manual_items($items);
    }
    return $items;
}

function nammu_actuality_get_manual_item(string $id): ?array
{
    $normalizedId = preg_replace('/[^a-f0-9]/i', '', trim($id)) ?? '';
    if ($normalizedId === '') {
        return null;
    }
    foreach (nammu_actuality_list_manual_items() as $item) {
        if ((string) ($item['id'] ?? '') === $normalizedId) {
            return $item;
        }
    }
    return null;
}

function nammu_actuality_update_manual_item(string $id, string $text, string $baseUrl, string $siteTitle, ?string $image = null, ?array $images = null): bool
{
    $normalizedId = preg_replace('/[^a-f0-9]/i', '', trim($id)) ?? '';
    if ($normalizedId === '') {
        return false;
    }
    $parts = nammu_actuality_manual_content($text);
    if ($parts['title'] === '') {
        return false;
    }
    $store = nammu_actuality_load_manual_items();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $updated = false;
    foreach ($items as &$item) {
        if ((string) ($item['id'] ?? '') !== $normalizedId) {
            continue;
        }
        $timestamp = (int) ($item['timestamp'] ?? time());
        $item['title'] = $parts['title'];
        $item['description'] = $parts['content'];
        $item['raw_text'] = $parts['raw_text'];
        $item['links'] = $parts['links'];
        $item['link'] = nammu_actuality_manual_anchor_url($baseUrl, $normalizedId);
        $item['source'] = $siteTitle !== '' ? $siteTitle : ((string) ($item['source'] ?? 'Actualidad'));
        $item['timestamp'] = $timestamp > 0 ? $timestamp : time();
        if ($image !== null || $images !== null) {
            $normalizedImages = $images !== null
                ? nammu_actuality_manual_images($images, $baseUrl)
                : nammu_actuality_manual_images((array) ($item['images'] ?? []), $baseUrl);
            $primaryImage = $image !== null ? nammu_actuality_manual_image_url(trim($image), $baseUrl) : '';
            if ($primaryImage !== '') {
                array_unshift($normalizedImages, $primaryImage);
            }
            $normalizedImages = array_values(array_unique(array_filter($normalizedImages)));
            $item['images'] = $normalizedImages;
            $item['image'] = $normalizedImages[0] ?? '';
        }
        $item['is_manual'] = true;
        $updated = true;
        break;
    }
    unset($item);
    if (!$updated) {
        return false;
    }
    $items = nammu_actuality_prune_manual_items($items);
    nammu_actuality_save_manual_items($items);
    return true;
}

function nammu_actuality_delete_manual_item(string $id): bool
{
    $normalizedId = preg_replace('/[^a-f0-9]/i', '', trim($id)) ?? '';
    if ($normalizedId === '') {
        return false;
    }
    $store = nammu_actuality_load_manual_items();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $before = count($items);
    $items = array_values(array_filter($items, static function (array $item) use ($normalizedId): bool {
        return (string) ($item['id'] ?? '') !== $normalizedId;
    }));
    if (count($items) === $before) {
        return false;
    }
    nammu_actuality_save_manual_items($items);
    return true;
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

function nammu_actuality_rss_settings(array $config): array
{
    return [
        'feeds' => implode("\n", nammu_actuality_feed_urls($config)),
    ];
}

function nammu_actuality_feed_urls(array $config): array
{
    $feeds = [];

    $nisaba = is_array($config['nisaba'] ?? null) ? $config['nisaba'] : [];
    $nisabaUrls = is_array($nisaba['urls'] ?? null) ? $nisaba['urls'] : [];
    $legacyNisabaUrl = trim((string) ($nisaba['url'] ?? ''));
    if ($legacyNisabaUrl !== '') {
        array_unshift($nisabaUrls, $legacyNisabaUrl);
    }
    foreach ($nisabaUrls as $nisabaUrl) {
        $candidate = trim((string) $nisabaUrl);
        if ($candidate === '') {
            continue;
        }
        if (function_exists('admin_nisaba_feed_url')) {
            $feeds[] = trim((string) admin_nisaba_feed_url($candidate));
        } else {
            $feeds[] = $candidate;
        }
    }

    $telex = is_array($config['telex'] ?? null) ? $config['telex'] : [];
    $telexUrls = is_array($telex['urls'] ?? null) ? $telex['urls'] : [];
    foreach ($telexUrls as $url) {
        $candidate = trim((string) $url);
        if ($candidate === '') {
            continue;
        }
        if (function_exists('admin_telex_normalize_feed_url')) {
            $candidate = trim((string) admin_telex_normalize_feed_url($candidate));
        }
        if ($candidate !== '' && preg_match('#^https?://#i', $candidate)) {
            $feeds[] = $candidate;
        }
    }

    return array_values(array_unique(array_filter($feeds, static function (string $url): bool {
        return $url !== '' && preg_match('#^https?://#i', $url);
    })));
}

function nammu_actuality_rss_feed_list(string $feedsRaw): array
{
    $lines = preg_split('/\R+/', $feedsRaw) ?: [];
    $feeds = [];
    foreach ($lines as $line) {
        $url = trim($line);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            continue;
        }
        $feeds[] = $url;
    }
    return array_values(array_unique($feeds));
}

function nammu_actuality_fetch_rss_items(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => "User-Agent: Nammu RSS Fetcher\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml instanceof SimpleXMLElement) {
        return [];
    }

    $items = [];
    if (isset($xml->channel->item)) {
        $namespaces = $xml->getNamespaces(true);
        foreach ($xml->channel->item as $item) {
            $title = trim((string) ($item->title ?? ''));
            $link = trim((string) ($item->link ?? ''));
            $guid = trim((string) ($item->guid ?? ''));
            $pubDate = trim((string) ($item->pubDate ?? ''));
            $description = trim((string) ($item->description ?? ''));
            $image = '';
            if (isset($item->enclosure)) {
                foreach ($item->enclosure as $enclosure) {
                    $type = strtolower(trim((string) ($enclosure['type'] ?? '')));
                    $candidate = trim((string) ($enclosure['url'] ?? ''));
                    if ($candidate !== '' && ($type === '' || str_starts_with($type, 'image/'))) {
                        $image = $candidate;
                        break;
                    }
                }
            }
            if ($image === '' && isset($namespaces['media'])) {
                $media = $item->children($namespaces['media']);
                if (isset($media->content)) {
                    foreach ($media->content as $mediaContent) {
                        $candidate = trim((string) ($mediaContent['url'] ?? ''));
                        $type = strtolower(trim((string) ($mediaContent['type'] ?? '')));
                        if ($candidate !== '' && ($type === '' || str_starts_with($type, 'image/'))) {
                            $image = $candidate;
                            break;
                        }
                    }
                }
                if ($image === '' && isset($media->thumbnail)) {
                    foreach ($media->thumbnail as $thumbnail) {
                        $candidate = trim((string) ($thumbnail['url'] ?? ''));
                        if ($candidate !== '') {
                            $image = $candidate;
                            break;
                        }
                    }
                }
            }
            if ($image === '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $imageMatch)) {
                $image = trim((string) ($imageMatch[1] ?? ''));
            }
            $keyBase = $guid !== '' ? $guid : ($link !== '' ? $link : $title);
            if ($keyBase === '' || $link === '') {
                continue;
            }
            $items[] = [
                'key' => sha1($keyBase),
                'title' => $title,
                'link' => $link,
                'description' => $description,
                'image' => $image,
                'timestamp' => $pubDate !== '' ? (int) (strtotime($pubDate) ?: 0) : 0,
            ];
        }
    } elseif (isset($xml->entry)) {
        foreach ($xml->entry as $entry) {
            $title = trim((string) ($entry->title ?? ''));
            $link = '';
            if (isset($entry->link)) {
                foreach ($entry->link as $linkNode) {
                    $href = trim((string) ($linkNode['href'] ?? ''));
                    if ($href !== '') {
                        $link = $href;
                        break;
                    }
                }
            }
            $guid = trim((string) ($entry->id ?? ''));
            $updated = trim((string) ($entry->updated ?? ''));
            $summary = trim((string) ($entry->summary ?? ''));
            if ($summary === '') {
                $summary = trim((string) ($entry->content ?? ''));
            }
            $keyBase = $guid !== '' ? $guid : ($link !== '' ? $link : $title);
            if ($keyBase === '' || $link === '') {
                continue;
            }
            $items[] = [
                'key' => sha1($keyBase),
                'title' => $title,
                'link' => $link,
                'description' => $summary,
                'image' => '',
                'timestamp' => $updated !== '' ? (int) (strtotime($updated) ?: 0) : 0,
            ];
        }
    }

    usort($items, static function (array $a, array $b): int {
        return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
    });
    return $items;
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

function nammu_actuality_html_to_text_preserving_breaks(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $normalized = preg_replace('/<\s*br\s*\/?>/iu', "\n", $html) ?? $html;
    $normalized = preg_replace('/<\/\s*(p|div|section|article|li|blockquote|h[1-6])\s*>/iu', "\n\n", $normalized) ?? $normalized;
    $normalized = preg_replace('/<\s*li\b[^>]*>/iu', '- ', $normalized) ?? $normalized;
    $text = html_entity_decode(strip_tags($normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
    $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

    return trim($text);
}

function nammu_actuality_text_to_html(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $paragraphs = preg_split("/\n{2,}/", $text) ?: [];
    $htmlParts = [];
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $htmlParts[] = '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
    }

    return implode('', $htmlParts);
}

function nammu_actuality_manual_plain_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/\*\*(.+?)\*\*/su', '$1', $text) ?? $text;
    $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    return trim($text);
}

function nammu_actuality_manual_extract_links(string $text): array
{
    preg_match_all('#https?://[^\s<>"\')]+#iu', $text, $matches);
    $links = array_values(array_unique(array_filter(array_map(static function (string $url): string {
        $trimmed = trim($url);
        $trimmed = rtrim($trimmed, ".,;:!?)");
        return filter_var($trimmed, FILTER_VALIDATE_URL) ? $trimmed : '';
    }, $matches[0] ?? []))));
    return $links;
}

function nammu_actuality_manual_strip_links(string $text): string
{
    $stripped = preg_replace('#https?://[^\s<>"\')]+#iu', '', $text) ?? $text;
    $stripped = preg_replace("/[ \t]+\n/", "\n", $stripped) ?? $stripped;
    $stripped = preg_replace("/\n{3,}/", "\n\n", $stripped) ?? $stripped;
    return trim($stripped);
}

function nammu_actuality_manual_title_from_content(string $content): string
{
    $content = trim($content);
    if ($content === '') {
        return '';
    }

    $singleLine = preg_replace('/\s+/u', ' ', $content) ?? $content;
    $title = trim($singleLine);
    $titleChars = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
    if ($titleChars > 120) {
        $title = function_exists('mb_substr') ? mb_substr($title, 0, 117, 'UTF-8') . '…' : substr($title, 0, 117) . '...';
    }
    return $title;
}

function nammu_actuality_manual_content(string $text): array
{
    $normalized = nammu_actuality_manual_plain_text($text);
    if ($normalized === '') {
        return ['title' => '', 'content' => '', 'links' => [], 'raw_text' => ''];
    }

    $links = nammu_actuality_manual_extract_links($normalized);
    $content = nammu_actuality_manual_strip_links($normalized);
    return [
        'title' => nammu_actuality_manual_title_from_content($content !== '' ? $content : $normalized),
        'content' => $content,
        'links' => $links,
        'raw_text' => $normalized,
    ];
}

function nammu_actuality_manual_anchor_url(string $baseUrl, string $id): string
{
    $base = rtrim($baseUrl, '/');
    return ($base !== '' ? $base : '') . '/actualidad.php#manual-' . rawurlencode($id);
}

function nammu_actuality_manual_image_url(string $image, string $baseUrl): string
{
    $value = trim($image);
    if ($value === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    $base = rtrim($baseUrl, '/');
    if ($base === '') {
        return $value;
    }
    $normalized = ltrim($value, '/');
    if (!str_starts_with($normalized, 'assets/')) {
        $normalized = 'assets/' . $normalized;
    }
    return $base . '/' . $normalized;
}

function nammu_actuality_manual_images($images, string $baseUrl): array
{
    $rawItems = is_array($images) ? $images : preg_split('/[\n,]+/', (string) $images);
    $normalized = [];
    foreach ((array) $rawItems as $image) {
        $url = nammu_actuality_manual_image_url((string) $image, $baseUrl);
        if ($url !== '') {
            $normalized[] = $url;
        }
    }
    return array_values(array_unique($normalized));
}

function nammu_actuality_prune_manual_items(array $items): array
{
    $cutoff = time() - (60 * 86400);
    return array_values(array_filter($items, static function (array $item) use ($cutoff): bool {
        $timestamp = (int) ($item['timestamp'] ?? 0);
        return $timestamp <= 0 || $timestamp >= $cutoff;
    }));
}

function nammu_actuality_images_from_fediverse_attachments(array $attachments): array
{
    $images = [];
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $url = trim((string) ($attachment['url'] ?? ''));
        $type = strtolower(trim((string) ($attachment['type'] ?? '')));
        $mediaType = strtolower(trim((string) ($attachment['media_type'] ?? ($attachment['mediaType'] ?? ''))));
        if ($url === '') {
            continue;
        }
        if ($type === 'image' || str_starts_with($mediaType, 'image/')) {
            $images[] = $url;
        }
    }
    return array_values(array_unique(array_filter($images)));
}

function nammu_actuality_enrich_manual_boost_item_images(array $item): array
{
    if (strtolower(trim((string) ($item['via'] ?? ''))) !== 'boost') {
        return $item;
    }
    if (!function_exists('nammu_fediverse_actions_store') && is_file(__DIR__ . '/fediverso.php')) {
        require_once __DIR__ . '/fediverso.php';
    }
    if (!function_exists('nammu_fediverse_actions_store') || !function_exists('nammu_fediverse_timeline_store')) {
        return $item;
    }

    $images = array_values(array_unique(array_filter(array_map('strval', is_array($item['images'] ?? null) ? $item['images'] : []))));
    $primaryImage = trim((string) ($item['image'] ?? ''));
    if ($primaryImage !== '' && !in_array($primaryImage, $images, true)) {
        array_unshift($images, $primaryImage);
    }

    $manualId = trim((string) ($item['id'] ?? ''));
    $candidateIdentifiers = array_values(array_filter(array_map('strval', array_merge(
        [(string) ($item['link'] ?? '')],
        is_array($item['links'] ?? null) ? $item['links'] : []
    ))));
    foreach ((array) (nammu_fediverse_actions_store()['items'] ?? []) as $action) {
        if (!is_array($action)) {
            continue;
        }
        if (strtolower(trim((string) ($action['type'] ?? ''))) !== 'share') {
            continue;
        }
        if (strtolower(trim((string) ($action['via'] ?? ''))) !== 'boost') {
            continue;
        }
        $actionManualId = trim((string) ($action['manual_item_id'] ?? ''));
        $actionObjectUrl = trim((string) ($action['object_url'] ?? ''));
        $actionPublicUrl = trim((string) ($action['public_url'] ?? ''));
        if (
            ($manualId !== '' && $actionManualId === $manualId)
            || ($actionObjectUrl !== '' && in_array($actionObjectUrl, $candidateIdentifiers, true))
            || ($actionPublicUrl !== '' && in_array($actionPublicUrl, $candidateIdentifiers, true))
        ) {
            foreach (array_filter([
                trim((string) ($action['image'] ?? '')),
                ...array_map('strval', is_array($action['images'] ?? null) ? $action['images'] : []),
            ]) as $imageUrl) {
                if (!in_array($imageUrl, $images, true)) {
                    $images[] = $imageUrl;
                }
            }
            foreach (array_filter([$actionObjectUrl, $actionPublicUrl]) as $identifier) {
                if (!in_array($identifier, $candidateIdentifiers, true)) {
                    $candidateIdentifiers[] = $identifier;
                }
            }
        }
    }

    foreach ((array) (nammu_fediverse_timeline_store()['items'] ?? []) as $timelineItem) {
        if (!is_array($timelineItem)) {
            continue;
        }
        $matchesCandidate = false;
        foreach (['id', 'url', 'object_id'] as $field) {
            $value = trim((string) ($timelineItem[$field] ?? ''));
            if ($value !== '' && in_array($value, $candidateIdentifiers, true)) {
                $matchesCandidate = true;
                break;
            }
        }
        if (!$matchesCandidate) {
            continue;
        }
        foreach (nammu_actuality_images_from_fediverse_attachments((array) ($timelineItem['attachments'] ?? [])) as $imageUrl) {
            if (!in_array($imageUrl, $images, true)) {
                $images[] = $imageUrl;
            }
        }
        $timelineImage = trim((string) ($timelineItem['image'] ?? ''));
        if ($timelineImage !== '' && !in_array($timelineImage, $images, true)) {
            $images[] = $timelineImage;
        }
    }

    if (!empty($images)) {
        $item['images'] = array_values($images);
        $item['image'] = (string) ($images[0] ?? '');
    }
    return $item;
}

function nammu_actuality_add_manual_item(string $text, string $baseUrl, string $siteTitle, string $image = '', array $meta = []): array
{
    $parts = nammu_actuality_manual_content($text);
    if ($parts['title'] === '') {
        return [];
    }
    $store = nammu_actuality_load_manual_items();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $id = substr(sha1($parts['title'] . '|' . $text . '|' . microtime(true) . '|' . random_int(0, PHP_INT_MAX)), 0, 16);
    $timestamp = time();
    $metaImages = nammu_actuality_manual_images((array) ($meta['images'] ?? []), $baseUrl);
    $primaryImage = nammu_actuality_manual_image_url($image, $baseUrl);
    $images = $metaImages;
    if ($primaryImage !== '') {
        array_unshift($images, $primaryImage);
    }
    $images = array_values(array_unique(array_filter($images)));
    $item = [
        'id' => $id,
        'title' => $parts['title'],
        'description' => $parts['content'],
        'raw_text' => $parts['raw_text'],
        'links' => $parts['links'],
        'timestamp' => $timestamp,
        'link' => nammu_actuality_manual_anchor_url($baseUrl, $id),
        'image' => $images[0] ?? $primaryImage,
        'images' => $images,
        'source' => $siteTitle !== '' ? $siteTitle : 'Actualidad',
        'is_manual' => true,
    ];
    foreach ($meta as $metaKey => $metaValue) {
        if ($metaKey === 'images') {
            continue;
        }
        if (is_scalar($metaValue) || $metaValue === null || is_array($metaValue)) {
            $item[(string) $metaKey] = $metaValue;
        }
    }
    $items[] = $item;
    $items = nammu_actuality_prune_manual_items($items);
    usort($items, static function (array $a, array $b): int {
        return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
    });
    nammu_actuality_save_manual_items($items);
    return $item;
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
        '//meta[@property="twitter:image"]/@content',
        '//meta[@property="twitter:image:src"]/@content',
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

    $imageQueries = [
        '//article//img',
        '//main//img',
        '//body//img',
    ];
    foreach ($imageQueries as $query) {
        $nodes = $xpath->query($query);
        if (!$nodes instanceof DOMNodeList || $nodes->length === 0) {
            continue;
        }
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $candidate = trim((string) ($node->getAttribute('src') ?: $node->getAttribute('data-src') ?: $node->getAttribute('srcset')));
            if ($candidate === '') {
                continue;
            }
            if (str_contains($candidate, ' ')) {
                $candidate = trim((string) preg_split('/\s+/', $candidate)[0]);
            }
            $resolved = nammu_actuality_resolve_url($candidate, $pageUrl);
            if ($resolved === '') {
                continue;
            }
            $resolvedLower = strtolower($resolved);
            if (str_starts_with($resolvedLower, 'data:')) {
                continue;
            }
            if (preg_match('/\.svg(?:[?#].*)?$/i', $resolvedLower)) {
                continue;
            }
            if (preg_match('/(?:sprite|icon|logo|avatar|pixel|spacer)/i', $resolvedLower)) {
                continue;
            }
            return $resolved;
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
        $isManual = !empty($item['is_manual']);
        $manualLinks = array_values(array_filter(array_map('strval', is_array($item['links'] ?? null) ? $item['links'] : [])));
        $targetLink = ($isManual && !empty($manualLinks))
            ? trim((string) ($manualLinks[0] ?? ''))
            : trim((string) ($item['link'] ?? ''));
        if ($targetLink === '') {
            continue;
        }
        $key = sha1($targetLink);
        $activeKeys[] = $key;
        $currentImage = trim((string) ($item['image'] ?? ''));
        if ($currentImage !== '') {
            continue;
        }
        $entry = is_array($cache['items'][$key] ?? null) ? $cache['items'][$key] : [];
        $localPath = (string) ($entry['local_path'] ?? '');
        $cachedUrl = (string) ($entry['public_url'] ?? '');
        $sourceImage = trim((string) ($entry['source_image'] ?? ''));
        if ($sourceImage !== '') {
            $items[$index]['source_image'] = $sourceImage;
        }
        if ($localPath !== '' && is_file($localPath) && $cachedUrl !== '') {
            $items[$index]['image'] = $cachedUrl;
            $cache['items'][$key]['last_used'] = time();
            if ($sourceImage === '') {
                $refreshedSourceImage = nammu_actuality_extract_social_image($targetLink);
                if ($refreshedSourceImage !== '') {
                    $items[$index]['source_image'] = $refreshedSourceImage;
                    $cache['items'][$key]['source_image'] = $refreshedSourceImage;
                }
            }
            continue;
        }
        $socialImage = nammu_actuality_extract_social_image($targetLink);
        if ($socialImage === '') {
            if ($currentImage !== '') {
                $items[$index]['image'] = $currentImage;
            }
            continue;
        }
        $cachedPublicUrl = nammu_actuality_cache_social_image($targetLink, $socialImage, $publicBaseUrl);
        if ($cachedPublicUrl === '') {
            if ($currentImage !== '') {
                $items[$index]['image'] = $currentImage;
            }
            continue;
        }
        $path = parse_url($cachedPublicUrl, PHP_URL_PATH);
        $localCachedPath = $path !== null && $path !== '' ? dirname(__DIR__) . $path : '';
        $items[$index]['image'] = $cachedPublicUrl;
        $items[$index]['source_image'] = $socialImage;
        $cache['items'][$key] = [
            'page_url' => $targetLink,
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

function nammu_actuality_has_feeds(array $config): bool
{
    return !empty(nammu_actuality_feed_urls($config));
}

function nammu_actuality_has_manual_items(): bool
{
    $store = nammu_actuality_load_manual_items();
    $items = nammu_actuality_prune_manual_items(is_array($store['items'] ?? null) ? $store['items'] : []);
    if (count($items) !== count((array) ($store['items'] ?? []))) {
        nammu_actuality_save_manual_items($items);
    }
    return !empty($items);
}

function nammu_actuality_has_content(array $config): bool
{
    return nammu_actuality_has_feeds($config) || nammu_actuality_has_manual_items() || nammu_actuality_has_persisted_news_items();
}

function nammu_actuality_has_published_site_items(string $contentDir, string $itinerariesDir): bool
{
    if (is_dir($contentDir)) {
        $repository = new \Nammu\Core\ContentRepository($contentDir);
        foreach ($repository->all() as $post) {
            if ($post instanceof \Nammu\Core\Post && !$post->isDraft()) {
                return true;
            }
        }
        foreach (nammu_collect_podcast_items($contentDir, '') as $episode) {
            if (is_array($episode) && trim((string) ($episode['slug'] ?? '')) !== '') {
                return true;
            }
        }
    }
    if (is_dir($itinerariesDir) && class_exists(\Nammu\Core\ItineraryRepository::class)) {
        $itineraryRepository = new \Nammu\Core\ItineraryRepository($itinerariesDir);
        foreach ($itineraryRepository->all() as $itinerary) {
            if ($itinerary instanceof \Nammu\Core\Itinerary && $itinerary->isPublished()) {
                return true;
            }
        }
    }
    return false;
}

function nammu_actuality_collect_published_site_items(string $contentDir, string $itinerariesDir, string $publicBaseUrl, string $siteTitle): array
{
    $items = [];
    $seen = [];
    $base = rtrim($publicBaseUrl, '/');
    $markdown = class_exists(\Nammu\Core\MarkdownConverter::class) ? new \Nammu\Core\MarkdownConverter() : null;
    $repository = new \Nammu\Core\ContentRepository($contentDir);
    foreach ($repository->all() as $post) {
        if (!$post instanceof \Nammu\Core\Post || $post->isDraft()) {
            continue;
        }
        $slug = trim((string) $post->getSlug());
        if ($slug === '') {
            continue;
        }
        $url = ($base !== '' ? $base : '') . '/' . rawurlencode($slug);
        $key = 'post:' . $slug;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $description = trim((string) $post->getDescription());
        if ($description === '' && $markdown instanceof \Nammu\Core\MarkdownConverter) {
            $document = $markdown->convertDocument($post->getContent());
            $description = nammu_excerpt_text((string) ($document['html'] ?? ''), 260);
        }
        $timestamp = $post->getDate() instanceof \DateTimeImmutable ? $post->getDate()->getTimestamp() : 0;
        if ($timestamp <= 0) {
            $file = rtrim($contentDir, '/') . '/' . $slug . '.md';
            $timestamp = is_file($file) ? ((int) @filemtime($file) ?: 0) : 0;
        }
        $items[] = [
            'title' => trim((string) $post->getTitle()),
            'link' => $url,
            'image' => (string) (nammu_resolve_asset($post->getImage(), $publicBaseUrl) ?? ''),
            'description' => $description,
            'timestamp' => $timestamp,
            'source' => 'Entrada',
            'is_site_content' => true,
            'site_content_type' => 'post',
            'site_content_label' => $siteTitle !== '' ? $siteTitle : 'Blog',
        ];
    }

    foreach (nammu_collect_podcast_items($contentDir, $publicBaseUrl) as $episode) {
        $slug = trim((string) ($episode['slug'] ?? ''));
        $url = trim((string) ($episode['page_url'] ?? ''));
        if ($slug === '' || $url === '') {
            continue;
        }
        $key = 'podcast:' . $slug;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $items[] = [
            'title' => trim((string) ($episode['title'] ?? '')),
            'link' => $url,
            'image' => trim((string) ($episode['image'] ?? '')),
            'description' => trim((string) ($episode['description'] ?? '')),
            'timestamp' => (int) ($episode['timestamp'] ?? 0),
            'source' => 'Podcast',
            'is_site_content' => true,
            'site_content_type' => 'podcast',
            'site_content_label' => $siteTitle !== '' ? $siteTitle : 'Podcast',
        ];
    }

    if (is_dir($itinerariesDir) && class_exists(\Nammu\Core\ItineraryRepository::class)) {
        $itineraryRepository = new \Nammu\Core\ItineraryRepository($itinerariesDir);
        foreach ($itineraryRepository->all() as $itinerary) {
            if (!$itinerary instanceof \Nammu\Core\Itinerary || !$itinerary->isPublished()) {
                continue;
            }
            $slug = trim((string) $itinerary->getSlug());
            if ($slug === '') {
                continue;
            }
            $key = 'itinerary:' . $slug;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $url = ($base !== '' ? $base : '') . '/itinerarios/' . rawurlencode($slug);
            $description = trim((string) $itinerary->getDescription());
            if ($description === '' && $markdown instanceof \Nammu\Core\MarkdownConverter) {
                $document = $markdown->convertDocument($itinerary->getContent());
                $description = nammu_excerpt_text((string) ($document['html'] ?? ''), 260);
            }
            $metadata = method_exists($itinerary, 'getMetadata') ? $itinerary->getMetadata() : [];
            $timestamp = 0;
            $dateRaw = trim((string) ($metadata['Date'] ?? $metadata['Updated'] ?? ''));
            if ($dateRaw !== '') {
                $parsed = strtotime($dateRaw);
                if ($parsed !== false) {
                    $timestamp = (int) $parsed;
                }
            }
            if ($timestamp <= 0) {
                $indexFile = rtrim($itinerariesDir, '/') . '/' . $slug . '/index.md';
                $timestamp = is_file($indexFile) ? ((int) @filemtime($indexFile) ?: 0) : 0;
            }
            $items[] = [
                'title' => trim((string) $itinerary->getTitle()),
                'link' => $url,
                'image' => (string) (nammu_resolve_asset($itinerary->getImage(), $publicBaseUrl) ?? ''),
                'description' => $description,
                'timestamp' => $timestamp,
                'source' => 'Itinerario',
                'is_site_content' => true,
                'site_content_type' => 'itinerary',
                'site_content_label' => $siteTitle !== '' ? $siteTitle : 'Itinerario',
            ];
        }
    }

    usort($items, static function (array $a, array $b): int {
        return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
    });

    return $items;
}

function nammu_actuality_page_items(array $config, string $contentDir, string $itinerariesDir, string $publicBaseUrl, string $siteTitle, string $siteDescription = '', string $siteLang = 'es'): array
{
    $snapshot = nammu_actuality_load_items_snapshot();
    $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
    if (empty($items) && function_exists('nammu_actuality_has_content') && nammu_actuality_has_content($config)) {
        $rebuilt = nammu_actuality_rebuild_snapshot($publicBaseUrl, $config, $siteTitle, $siteDescription, $siteLang);
        $items = is_array($rebuilt['items'] ?? null) ? $rebuilt['items'] : [];
    }

    $publishedSiteItems = nammu_actuality_collect_published_site_items($contentDir, $itinerariesDir, $publicBaseUrl, $siteTitle);
    if (empty($publishedSiteItems)) {
        return $items;
    }

    $merged = [];
    $seenActualityKeys = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = trim((string) ($item['link'] ?? ''));
        if ($key === '') {
            $key = 'manual:' . trim((string) ($item['id'] ?? ''));
        }
        if ($key !== '') {
            $seenActualityKeys[$key] = true;
        }
        $merged[] = $item;
    }
    foreach ($publishedSiteItems as $item) {
        $key = trim((string) ($item['link'] ?? ''));
        if ($key !== '' && isset($seenActualityKeys[$key])) {
            continue;
        }
        $merged[] = $item;
    }
    usort($merged, static function (array $a, array $b): int {
        return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
    });
    return $merged;
}

function nammu_actuality_collect_items(array $config, string $publicBaseUrl): array
{
    $feeds = nammu_actuality_feed_urls($config);
    $items = [];
    $seen = [];
    $seenNewsIds = [];
    $persistedNewsById = [];
    $snapshot = nammu_actuality_load_items_snapshot();
    foreach ((array) ($snapshot['items'] ?? []) as $snapshotItem) {
        if (!is_array($snapshotItem)) {
            continue;
        }
        if (trim((string) ($snapshotItem['source_kind'] ?? '')) !== 'news') {
            continue;
        }
        $snapshotId = trim((string) ($snapshotItem['id'] ?? ''));
        if ($snapshotId === '') {
            continue;
        }
        $persistedNewsById[$snapshotId] = $snapshotItem;
    }
    $newsOverridesStore = nammu_actuality_load_news_overrides();
    $newsOverrides = [];
    foreach ((array) ($newsOverridesStore['items'] ?? []) as $override) {
        if (!is_array($override)) {
            continue;
        }
        $overrideId = preg_replace('/[^a-f0-9]/i', '', (string) ($override['id'] ?? '')) ?? '';
        if ($overrideId === '') {
            continue;
        }
        $newsOverrides[$overrideId] = $override;
    }
    $normalizeNewsItem = static function (array $newsItem) use ($newsOverrides, $publicBaseUrl): ?array {
        $newsId = trim((string) ($newsItem['id'] ?? ''));
        if ($newsId !== '' && isset($newsOverrides[$newsId])) {
            $override = $newsOverrides[$newsId];
            if (!empty($override['deleted'])) {
                return null;
            }
            foreach (['title', 'description', 'raw_text', 'link', 'image'] as $field) {
                if (array_key_exists($field, $override)) {
                    $newsItem[$field] = is_string($override[$field]) ? trim((string) $override[$field]) : (string) $override[$field];
                }
            }
            if (array_key_exists('images', $override)) {
                $newsItem['images'] = nammu_actuality_manual_images((array) ($override['images'] ?? []), $publicBaseUrl);
            } else {
                $newsItem['images'] = nammu_actuality_manual_images((array) ($newsItem['images'] ?? []), $publicBaseUrl);
            }
            if (array_key_exists('image', $override)) {
                $newsItem['image'] = nammu_actuality_manual_image_url((string) ($override['image'] ?? ''), $publicBaseUrl);
            } elseif (empty($newsItem['image']) && !empty($newsItem['images'][0])) {
                $newsItem['image'] = (string) $newsItem['images'][0];
            }
        } else {
            $newsItem['images'] = nammu_actuality_manual_images((array) ($newsItem['images'] ?? []), $publicBaseUrl);
        }
        if (trim((string) ($newsItem['raw_text'] ?? '')) === '') {
            $newsItem['raw_text'] = trim((string) ($newsItem['description'] ?? ''));
        }
        return $newsItem;
    };
    foreach ($feeds as $feedUrl) {
        foreach (nammu_actuality_fetch_rss_items($feedUrl) as $item) {
            $key = (string) ($item['key'] ?? sha1(($item['link'] ?? '') . '|' . ($item['title'] ?? '')));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $descriptionHtml = (string) ($item['description'] ?? '');
            $descriptionText = nammu_actuality_html_to_text_preserving_breaks($descriptionHtml);
            $newsItem = [
                'id' => nammu_actuality_news_item_id([
                    'feed_item_key' => $key,
                    'link' => (string) ($item['link'] ?? ''),
                    'title' => (string) ($item['title'] ?? ''),
                ]),
                'feed_item_key' => $key,
                'feed_url' => $feedUrl,
                'title' => trim((string) ($item['title'] ?? '')),
                'link' => trim((string) ($item['link'] ?? '')),
                'image' => trim((string) ($item['image'] ?? '')),
                'images' => array_values(array_filter([trim((string) ($item['image'] ?? ''))])),
                'description' => $descriptionText,
                'raw_text' => $descriptionText,
                'timestamp' => (int) ($item['timestamp'] ?? 0),
                'source' => parse_url((string) ($item['link'] ?? ''), PHP_URL_HOST) ?: '',
                'source_kind' => 'news',
            ];
            $newsId = trim((string) ($newsItem['id'] ?? ''));
            if ($newsId !== '') {
                $persistedNewsById[$newsId] = $newsItem;
            }
            $newsItem = $normalizeNewsItem($newsItem);
            if (!is_array($newsItem)) {
                continue;
            }
            if ($newsId !== '') {
                $seenNewsIds[$newsId] = true;
            }
            $items[] = $newsItem;
        }
    }
    foreach ($persistedNewsById as $newsId => $persistedNewsItem) {
        $newsId = trim((string) $newsId);
        if ($newsId === '' || isset($seenNewsIds[$newsId])) {
            continue;
        }
        $persistedNewsItem = $normalizeNewsItem($persistedNewsItem);
        if (!is_array($persistedNewsItem)) {
            continue;
        }
        $items[] = $persistedNewsItem;
    }
    $manualStore = nammu_actuality_load_manual_items();
    $manualItems = nammu_actuality_prune_manual_items(is_array($manualStore['items'] ?? null) ? $manualStore['items'] : []);
    if (count($manualItems) !== count((array) ($manualStore['items'] ?? []))) {
        nammu_actuality_save_manual_items($manualItems);
    }
    foreach ($manualItems as $item) {
        $manualId = trim((string) ($item['id'] ?? ''));
        if ($manualId === '') {
            continue;
        }
        $item = nammu_actuality_enrich_manual_boost_item_images($item);
        $seen['manual:' . $manualId] = true;
        $items[] = [
            'id' => $manualId,
            'title' => trim((string) ($item['title'] ?? '')),
            'link' => trim((string) ($item['link'] ?? nammu_actuality_manual_anchor_url($publicBaseUrl, $manualId))),
            'image' => nammu_actuality_manual_image_url((string) ($item['image'] ?? ''), $publicBaseUrl),
            'images' => nammu_actuality_manual_images((array) ($item['images'] ?? []), $publicBaseUrl),
            'description' => nammu_actuality_manual_plain_text((string) ($item['description'] ?? '')),
            'raw_text' => nammu_actuality_manual_plain_text((string) ($item['raw_text'] ?? '')),
            'links' => array_values(array_filter(array_map('strval', is_array($item['links'] ?? null) ? $item['links'] : []))),
            'timestamp' => (int) ($item['timestamp'] ?? 0),
            'source' => trim((string) ($item['source'] ?? '')),
            'via' => trim((string) ($item['via'] ?? '')),
            'is_manual' => true,
        ];
    }
    if (empty($items)) {
        return [];
    }
    usort($items, static function (array $a, array $b): int {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });
    return nammu_actuality_enrich_items($items, $publicBaseUrl);
}

function nammu_generate_actuality_feed(string $baseUrl, array $config, string $siteTitle, string $siteDescription, string $siteLang = 'es'): string
{
    $baseUrl = rtrim($baseUrl, '/');
    $feedUrl = ($baseUrl !== '' ? $baseUrl : '') . '/noticias.xml';
    $pageUrl = ($baseUrl !== '' ? $baseUrl : '') . (function_exists('nammu_fediverse_profile_alias_path') ? nammu_fediverse_profile_alias_path($config, $baseUrl) : '/actualidad.php');
    $items = nammu_actuality_collect_items($config, $baseUrl);
    $lastBuild = gmdate(DATE_RSS, !empty($items) ? (int) ($items[0]['timestamp'] ?: time()) : time());
    $titleEsc = htmlspecialchars('Actualidad — ' . ($siteTitle !== '' ? $siteTitle : 'Nammu Blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $descEsc = htmlspecialchars($siteDescription !== '' ? $siteDescription : 'Selección de fuentes agregadas.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $langEsc = htmlspecialchars($siteLang !== '' ? $siteLang : 'es', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $pageUrlEsc = htmlspecialchars($pageUrl !== '' ? $pageUrl : '/actualidad.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $feedUrlEsc = htmlspecialchars($feedUrl !== '' ? $feedUrl : '/noticias.xml', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $itemsXml = [];
    foreach ($items as $item) {
        $itemTitle = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $itemLink = htmlspecialchars((string) ($item['link'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $itemGuid = htmlspecialchars((string) ($item['link'] ?? sha1((string) ($item['title'] ?? ''))), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pubDate = gmdate(DATE_RSS, (int) (($item['timestamp'] ?? 0) > 0 ? $item['timestamp'] : time()));
        $description = trim((string) ($item['description'] ?? ''));
        $itemLinks = array_values(array_filter(array_map('strval', is_array($item['links'] ?? null) ? $item['links'] : [])));
        $image = trim((string) ($item['source_image'] ?? ''));
        if ($image === '') {
            $image = trim((string) ($item['image'] ?? ''));
        }
        $descriptionHtml = nammu_actuality_text_to_html($description);
        if (!empty($itemLinks)) {
            $linkBits = [];
            foreach ($itemLinks as $index => $url) {
                $label = 'Enlace ' . ($index + 1);
                $linkBits[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
            }
            $descriptionHtml .= '<p>' . implode(' · ', $linkBits) . '</p>';
        }
        if ($image !== '') {
            $descriptionHtml .= '<p><img src="' . htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="' . $itemTitle . '" /></p>';
        }
        $descriptionCdata = '<![CDATA[' . $descriptionHtml . ']]>';
        $itemsXml[] = <<<XML
    <item>
      <title>{$itemTitle}</title>
      <link>{$itemLink}</link>
      <guid>{$itemGuid}</guid>
      <pubDate>{$pubDate}</pubDate>
      <description>{$descriptionCdata}</description>
    </item>
XML;
    }
    $itemsBlock = implode("\n", $itemsXml);

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>{$titleEsc}</title>
    <description>{$descEsc}</description>
    <link>{$pageUrlEsc}</link>
    <language>{$langEsc}</language>
    <lastBuildDate>{$lastBuild}</lastBuildDate>
    <atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="{$feedUrlEsc}" rel="self" type="application/rss+xml" />
{$itemsBlock}
  </channel>
</rss>
XML;
}

function nammu_generate_fediverse_threads_feed(string $baseUrl, array $config, string $siteTitle, string $siteDescription, string $siteLang = 'es'): string
{
    $items = nammu_actuality_page_items($config, dirname(__DIR__) . '/content', dirname(__DIR__) . '/itinerarios', $baseUrl, $siteTitle, $siteDescription, $siteLang);
    $feedItems = [];
    foreach ($items as $item) {
        if (!is_array($item) || !function_exists('nammu_fediverse_public_thread_url_for_actuality_item')) {
            continue;
        }
        $threadUrl = trim((string) nammu_fediverse_public_thread_url_for_actuality_item($item, $config));
        if ($threadUrl === '') {
            continue;
        }
        $title = trim((string) ($item['title'] ?? ''));
        $description = trim((string) (($item['raw_text'] ?? '') ?: ($item['description'] ?? '')));
        $timestamp = (int) ($item['timestamp'] ?? 0);
        $feedItems[] = [
            'title' => $title !== '' ? $title : nammu_excerpt_text($description, 120),
            'link' => $threadUrl,
            'description' => $description,
            'timestamp' => $timestamp > 0 ? $timestamp : time(),
        ];
    }

    usort($feedItems, static function (array $a, array $b): int {
        return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
    });

    $lastBuild = gmdate(DATE_RSS, !empty($feedItems) ? (int) $feedItems[0]['timestamp'] : time());
    $feedTitle = htmlspecialchars(trim($siteTitle) !== '' ? ($siteTitle . ' — Fediverso') : 'Fediverso', ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $feedDescription = htmlspecialchars(trim($siteDescription) !== '' ? $siteDescription : 'Páginas públicas de hilo Fediverso asociadas al perfil del blog.', ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $feedLink = htmlspecialchars(rtrim($baseUrl, '/') . (function_exists('nammu_fediverse_profile_alias_path') ? nammu_fediverse_profile_alias_path($config, $baseUrl) : '/actualidad.php'), ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $selfUrl = htmlspecialchars(rtrim($baseUrl, '/') . '/fediverso.xml', ENT_XML1 | ENT_COMPAT, 'UTF-8');

    $itemsXml = [];
    foreach ($feedItems as $item) {
        $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $link = htmlspecialchars((string) ($item['link'] ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $guid = $link;
        $descriptionHtml = nammu_actuality_text_to_html((string) ($item['description'] ?? ''));
        $descriptionEscaped = htmlspecialchars($descriptionHtml, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $pubDate = gmdate(DATE_RSS, (int) ($item['timestamp'] ?? time()));
        $itemsXml[] = <<<XML
<item>
  <title>{$title}</title>
  <link>{$link}</link>
  <guid isPermaLink="true">{$guid}</guid>
  <pubDate>{$pubDate}</pubDate>
  <description>{$descriptionEscaped}</description>
</item>
XML;
    }
    $itemsBlock = implode("\n", $itemsXml);

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title>{$feedTitle}</title>
  <link>{$feedLink}</link>
  <description>{$feedDescription}</description>
  <language>{$siteLang}</language>
  <lastBuildDate>{$lastBuild}</lastBuildDate>
  <atom:link href="{$selfUrl}" rel="self" type="application/rss+xml" />
{$itemsBlock}
</channel>
</rss>
XML;
}

function nammu_actuality_clear_snapshot(): void
{
    $itemsFile = nammu_actuality_items_file();
    if (is_file($itemsFile)) {
        @unlink($itemsFile);
    }
    $feedFile = dirname(__DIR__) . '/noticias.xml';
    if (is_file($feedFile)) {
        @unlink($feedFile);
    }
    $cache = nammu_actuality_load_cache();
    nammu_actuality_prune_cache($cache, []);
    nammu_actuality_save_cache(['items' => []]);
}

function nammu_actuality_rebuild_snapshot(string $baseUrl, array $config, string $siteTitle, string $siteDescription, string $siteLang = 'es'): array
{
    $contentDir = dirname(__DIR__) . '/content';
    $itinerariesDir = dirname(__DIR__) . '/itinerarios';
    if (!nammu_actuality_has_content($config) && !nammu_actuality_has_published_site_items($contentDir, $itinerariesDir)) {
        nammu_actuality_clear_snapshot();
        return ['updated_at' => 0, 'items' => []];
    }
    $items = nammu_actuality_collect_items($config, $baseUrl);
    nammu_actuality_save_items_snapshot($items);
    $feed = nammu_generate_actuality_feed($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
    if ($baseUrl !== '') {
        @file_put_contents(dirname(__DIR__) . '/noticias.xml', $feed);
    }
    return [
        'updated_at' => time(),
        'items' => $items,
    ];
}
