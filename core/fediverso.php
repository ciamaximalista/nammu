<?php

declare(strict_types=1);

function nammu_fediverse_following_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-following.json';
}

function nammu_fediverse_timeline_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-timeline.json';
}

function nammu_fediverse_inbox_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-inbox.json';
}

function nammu_fediverse_followers_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-followers.json';
}

function nammu_fediverse_deliveries_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-deliveries.json';
}

function nammu_fediverse_messages_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-messages.json';
}

function nammu_fediverse_actions_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-actions.json';
}

function nammu_fediverse_deleted_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-deleted.json';
}

function nammu_fediverse_keys_file(): string
{
    return dirname(__DIR__) . '/config/activitypub-keys.json';
}

function nammu_fediverse_load_json_store(string $file, array $default = []): array
{
    if (!is_file($file)) {
        return $default;
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $default;
}

function nammu_fediverse_save_json_store(string $file, array $payload): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        nammu_ensure_directory($dir);
    }
    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function nammu_fediverse_base_url(array $config): string
{
    $base = trim((string) ($config['site_url'] ?? ''));
    if ($base === '') {
        $base = function_exists('nammu_base_url') ? trim((string) nammu_base_url()) : '';
    }
    return rtrim($base, '/');
}

function nammu_fediverse_site_host(array $config): string
{
    $base = nammu_fediverse_base_url($config);
    $host = parse_url($base, PHP_URL_HOST);
    return is_string($host) ? strtolower($host) : '';
}

function nammu_fediverse_preferred_username(array $config): string
{
    $configured = trim((string) ($config['fediverse']['username'] ?? ''));
    if ($configured !== '') {
        $configured = preg_replace('/[^a-z0-9_.-]+/i', '', strtolower($configured)) ?? '';
        if ($configured !== '') {
            return $configured;
        }
    }
    $host = nammu_fediverse_site_host($config);
    if ($host !== '') {
        $label = explode('.', $host)[0] ?? '';
        $label = preg_replace('/[^a-z0-9_.-]+/i', '', strtolower($label)) ?? '';
        if ($label !== '') {
            return $label;
        }
    }
    $siteName = trim((string) (($config['site_name'] ?? '') ?: 'blog'));
    $slug = preg_replace('/[^a-z0-9_.-]+/i', '-', strtolower($siteName)) ?? 'blog';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'blog';
}

function nammu_fediverse_actor_url(array $config): string
{
    return nammu_fediverse_base_url($config) . '/ap/actor';
}

function nammu_fediverse_key_url(array $config): string
{
    return nammu_fediverse_base_url($config) . '/ap/key';
}

function nammu_fediverse_outbox_url(array $config): string
{
    return nammu_fediverse_base_url($config) . '/ap/outbox';
}

function nammu_fediverse_followers_url(array $config): string
{
    return nammu_fediverse_base_url($config) . '/ap/followers';
}

function nammu_fediverse_following_url(array $config): string
{
    return nammu_fediverse_base_url($config) . '/ap/following';
}

function nammu_fediverse_inbox_url(array $config): string
{
    return nammu_fediverse_base_url($config) . '/ap/inbox';
}

function nammu_fediverse_acct_uri(array $config): string
{
    $host = nammu_fediverse_site_host($config);
    return 'acct:' . nammu_fediverse_preferred_username($config) . '@' . $host;
}

function nammu_fediverse_avatar_url(array $config): string
{
    $baseUrl = nammu_fediverse_base_url($config);
    $avatar = '';
    if (function_exists('nammu_template_settings')) {
        $theme = nammu_template_settings();
        $avatar = trim((string) (($theme['logo_url'] ?? '') ?: ''));
        if ($avatar === '' && !empty($theme['images']['logo'])) {
            $avatar = nammu_fediverse_asset_url((string) $theme['images']['logo'], $baseUrl);
        }
    }
    if ($avatar === '' && !empty($config['social']['home_image'])) {
        $avatar = nammu_fediverse_asset_url((string) $config['social']['home_image'], $baseUrl);
    }
    return trim($avatar);
}

function nammu_fediverse_keypair(): array
{
    $default = ['private_key' => '', 'public_key' => ''];
    $stored = nammu_fediverse_load_json_store(nammu_fediverse_keys_file(), $default);
    if (!empty($stored['private_key']) && !empty($stored['public_key'])) {
        return $stored;
    }
    if (!function_exists('openssl_pkey_new')) {
        return $default;
    }
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    if ($resource === false) {
        return $default;
    }
    $privateKey = '';
    openssl_pkey_export($resource, $privateKey);
    $details = openssl_pkey_get_details($resource);
    $publicKey = is_array($details) ? (string) ($details['key'] ?? '') : '';
    $result = ['private_key' => $privateKey, 'public_key' => $publicKey];
    nammu_fediverse_save_json_store(nammu_fediverse_keys_file(), $result);
    return $result;
}

function nammu_fediverse_signature_key_id(array $config): string
{
    return nammu_fediverse_key_url($config);
}

function nammu_fediverse_private_key_resource(array $config)
{
    $keys = nammu_fediverse_keypair();
    $private = trim((string) ($keys['private_key'] ?? ''));
    if ($private === '' || !function_exists('openssl_pkey_get_private')) {
        return null;
    }
    $resource = openssl_pkey_get_private($private);
    return $resource === false ? null : $resource;
}

function nammu_fediverse_http_date(): string
{
    return gmdate('D, d M Y H:i:s \G\M\T');
}

function nammu_fediverse_digest_header(string $body): string
{
    return 'SHA-256=' . base64_encode(hash('sha256', $body, true));
}

function nammu_fediverse_signature_header(string $method, string $url, array $config, string $body = '', array $extraHeaders = []): ?array
{
    $privateKey = nammu_fediverse_private_key_resource($config);
    if ($privateKey === null) {
        return null;
    }
    $parts = parse_url($url);
    $path = (string) ($parts['path'] ?? '/');
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $target = strtolower($method) . ' ' . $path . $query;
    $host = (string) ($parts['host'] ?? '');
    if ($host === '') {
        return null;
    }
    if (isset($parts['port'])) {
        $host .= ':' . $parts['port'];
    }
    $headers = [
        '(request-target)' => $target,
        'host' => $host,
        'date' => nammu_fediverse_http_date(),
    ];
    if ($body !== '') {
        $headers['digest'] = nammu_fediverse_digest_header($body);
        $headers['content-type'] = 'application/activity+json';
    }
    foreach ($extraHeaders as $name => $value) {
        $headers[strtolower((string) $name)] = (string) $value;
    }
    $signing = [];
    foreach ($headers as $name => $value) {
        $signing[] = $name . ': ' . $value;
    }
    $signingString = implode("\n", $signing);
    $signature = '';
    $ok = openssl_sign($signingString, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$ok) {
        return null;
    }
    $headerNames = implode(' ', array_keys($headers));
    $signatureHeader = sprintf(
        'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
        nammu_fediverse_signature_key_id($config),
        $headerNames,
        base64_encode($signature)
    );
    $result = [
        'Date: ' . $headers['date'],
        'Host: ' . $host,
        'Signature: ' . $signatureHeader,
        'Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/json;q=0.9',
        'User-Agent: Nammu Fediverso',
    ];
    if ($body !== '') {
        $result[] = 'Digest: ' . $headers['digest'];
        $result[] = 'Content-Type: application/activity+json';
    }
    return $result;
}

function nammu_fediverse_signed_fetch(string $url, array $config, string $method = 'GET', string $body = ''): array
{
    $headers = nammu_fediverse_signature_header($method, $url, $config, $body) ?? [
        'User-Agent: Nammu Fediverso',
        'Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/json;q=0.9',
    ];
    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $body,
        ],
    ]);
    $rawHeaders = [];
    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $line, $m)) {
            $status = (int) $m[1];
            continue;
        }
        if (!is_string($line) || !str_contains($line, ':')) {
            continue;
        }
        [$name, $value] = explode(':', $line, 2);
        $rawHeaders[strtolower(trim($name))] = trim($value);
    }
    return [
        'status' => $status,
        'headers' => $rawHeaders,
        'body' => is_string($responseBody) ? $responseBody : '',
    ];
}

function nammu_fediverse_signed_fetch_json(string $url, array $config, string $method = 'GET', string $body = ''): ?array
{
    $response = nammu_fediverse_signed_fetch($url, $config, $method, $body);
    if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 400) {
        if (strtoupper($method) === 'GET' && $body === '') {
            return nammu_fediverse_fetch_json($url);
        }
        return null;
    }
    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    return is_array($decoded) ? $decoded : null;
}

function nammu_fediverse_fetch(string $url, string $accept = 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/json;q=0.9'): array
{
    $headers = [];
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "User-Agent: Nammu Fediverso\r\nAccept: {$accept}\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $headerLine) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $headerLine, $m)) {
            $status = (int) $m[1];
            continue;
        }
        if (!is_string($headerLine) || !str_contains($headerLine, ':')) {
            continue;
        }
        [$name, $value] = explode(':', $headerLine, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }
    return [
        'status' => $status,
        'headers' => $headers,
        'body' => is_string($body) ? $body : '',
    ];
}

function nammu_fediverse_fetch_json(string $url, string $accept = 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/json;q=0.9'): ?array
{
    $response = nammu_fediverse_fetch($url, $accept);
    if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 400) {
        return null;
    }
    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    return is_array($decoded) ? $decoded : null;
}

function nammu_fediverse_extract_url($value): string
{
    if (is_string($value)) {
        return trim($value);
    }
    if (!is_array($value)) {
        return '';
    }
    foreach (['url', 'href', 'src'] as $key) {
        if (array_key_exists($key, $value)) {
            $resolved = nammu_fediverse_extract_url($value[$key]);
            if ($resolved !== '') {
                return $resolved;
            }
        }
    }
    foreach ($value as $candidate) {
        $resolved = nammu_fediverse_extract_url($candidate);
        if ($resolved !== '') {
            return $resolved;
        }
    }
    return '';
}

function nammu_fediverse_extract_actor_icon(array $actor): string
{
    foreach (['icon', 'image'] as $field) {
        if (!array_key_exists($field, $actor)) {
            continue;
        }
        $resolved = nammu_fediverse_extract_url($actor[$field]);
        if ($resolved !== '') {
            return $resolved;
        }
    }
    return '';
}

function nammu_fediverse_extract_html_image_urls(string $html): array
{
    $html = trim($html);
    if ($html === '') {
        return [];
    }
    $matches = [];
    preg_match_all('/<img\b[^>]*\bsrc=(["\'])(https?:\/\/[^"\']+)\1/i', $html, $matches);
    $urls = array_values(array_unique(array_filter(array_map('trim', $matches[2] ?? []), static function ($url): bool {
        return is_string($url) && $url !== '';
    })));
    return $urls;
}

function nammu_fediverse_html_to_text(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html) ?? $html;
    $html = preg_replace('#</\s*(p|div|blockquote|pre|li|ul|ol|h[1-6])\s*>#i', "\n", $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\r\n|\r/u", "\n", $text) ?? $text;
    $text = preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text;
    $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
    return trim($text);
}

function nammu_fediverse_parse_digest_value(string $header): array
{
    $header = trim($header);
    if ($header === '') {
        return ['', ''];
    }
    foreach (preg_split('/\s*,\s*/', $header) ?: [] as $part) {
        if (!str_contains($part, '=')) {
            continue;
        }
        [$algo, $value] = explode('=', $part, 2);
        $algo = strtolower(trim($algo));
        $value = trim($value);
        if ($algo !== '' && $value !== '') {
            return [$algo, $value];
        }
    }
    return ['', ''];
}

function nammu_fediverse_request_headers(): array
{
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (!is_string($value)) {
            continue;
        }
        if (str_starts_with($key, 'HTTP_')) {
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = $value;
            continue;
        }
        if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_DIGEST', 'DIGEST'], true)) {
            $name = strtolower(str_replace('_', '-', $key));
            $headers[$name] = $value;
        }
    }
    return $headers;
}

function nammu_fediverse_parse_signature_header(string $header): array
{
    $result = [];
    foreach (preg_split('/\s*,\s*/', trim($header)) ?: [] as $part) {
        if (!str_contains($part, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $part, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"");
        if ($key !== '') {
            $result[$key] = $value;
        }
    }
    return $result;
}

function nammu_fediverse_build_incoming_signed_string(array $signatureData, array $headers, string $requestTarget): ?string
{
    $headerList = trim((string) ($signatureData['headers'] ?? ''));
    if ($headerList === '') {
        $headerList = '(request-target)';
    }
    $lines = [];
    foreach (preg_split('/\s+/', $headerList) ?: [] as $headerName) {
        $headerName = strtolower(trim($headerName));
        if ($headerName === '') {
            continue;
        }
        if ($headerName === '(request-target)') {
            $lines[] = '(request-target): ' . $requestTarget;
            continue;
        }
        if (!array_key_exists($headerName, $headers)) {
            return null;
        }
        $lines[] = $headerName . ': ' . trim((string) $headers[$headerName]);
    }
    return implode("\n", $lines);
}

function nammu_fediverse_resolve_actor(string $input, ?array $config = null): ?array
{
    $trimmed = trim($input);
    if ($trimmed === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $trimmed)) {
        $actor = is_array($config)
            ? nammu_fediverse_signed_fetch_json($trimmed, $config)
            : nammu_fediverse_fetch_json($trimmed);
        if (!is_array($actor)) {
            return null;
        }
        $sharedInbox = '';
        if (is_array($actor['endpoints'] ?? null)) {
            $sharedInbox = trim((string) ($actor['endpoints']['sharedInbox'] ?? ''));
        }
        return [
            'id' => (string) ($actor['id'] ?? $trimmed),
            'preferredUsername' => (string) ($actor['preferredUsername'] ?? ''),
            'name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? ''))),
            'inbox' => (string) ($actor['inbox'] ?? ''),
            'sharedInbox' => $sharedInbox,
            'outbox' => (string) ($actor['outbox'] ?? ''),
            'url' => (string) ($actor['url'] ?? ($actor['id'] ?? $trimmed)),
            'icon' => nammu_fediverse_extract_actor_icon($actor),
            'public_key_id' => is_array($actor['publicKey'] ?? null) ? (string) (($actor['publicKey']['id'] ?? '') ?: '') : '',
            'public_key_pem' => is_array($actor['publicKey'] ?? null)
                ? (string) (($actor['publicKey']['publicKeyPem'] ?? '') ?: '')
                : trim((string) ($actor['publicKeyPem'] ?? '')),
        ];
    }

    $acct = $trimmed;
    if (!str_starts_with($acct, 'acct:')) {
        $acct = 'acct:' . ltrim($acct, '@');
    }
    if (!preg_match('/^acct:([^@]+)@(.+)$/i', $acct, $matches)) {
        return null;
    }
    $resource = rawurlencode($acct);
    $domain = strtolower(trim((string) ($matches[2] ?? '')));
    if ($domain === '') {
        return null;
    }
    $webfinger = nammu_fediverse_fetch_json(
        'https://' . $domain . '/.well-known/webfinger?resource=' . $resource,
        'application/jrd+json, application/json;q=0.9'
    );
    if (!is_array($webfinger)) {
        return null;
    }
    $actorUrl = '';
    foreach ((array) ($webfinger['links'] ?? []) as $link) {
        if (!is_array($link)) {
            continue;
        }
        $rel = (string) ($link['rel'] ?? '');
        $type = (string) ($link['type'] ?? '');
        if ($rel === 'self' && str_contains($type, 'activity+json')) {
            $actorUrl = (string) ($link['href'] ?? '');
            break;
        }
    }
    if ($actorUrl === '') {
        return null;
    }
    return nammu_fediverse_resolve_actor($actorUrl, $config);
}

function nammu_fediverse_following_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_following_file(), ['actors' => []]);
    $actors = is_array($store['actors'] ?? null) ? $store['actors'] : [];
    return ['actors' => array_values($actors)];
}

function nammu_fediverse_save_following_store(array $actors): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_following_file(), ['actors' => array_values($actors)]);
}

function nammu_fediverse_timeline_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_timeline_file(), ['items' => []]);
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    return ['items' => array_values($items)];
}

function nammu_fediverse_save_timeline_store(array $items): void
{
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
    });
    nammu_fediverse_save_json_store(nammu_fediverse_timeline_file(), ['items' => array_slice(array_values($items), 0, 200)]);
}

function nammu_fediverse_followers_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_followers_file(), ['followers' => []]);
    $followers = is_array($store['followers'] ?? null) ? $store['followers'] : [];
    return ['followers' => array_values($followers)];
}

function nammu_fediverse_save_followers_store(array $followers): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_followers_file(), ['followers' => array_values($followers)]);
}

function nammu_fediverse_deliveries_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_deliveries_file(), ['followers' => []]);
    $followers = is_array($store['followers'] ?? null) ? $store['followers'] : [];
    return ['followers' => $followers];
}

function nammu_fediverse_save_deliveries_store(array $followers): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_deliveries_file(), ['followers' => $followers]);
}

function nammu_fediverse_messages_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_messages_file(), ['items' => []]);
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    return ['items' => array_values($items)];
}

function nammu_fediverse_save_messages_store(array $items): void
{
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
    });
    nammu_fediverse_save_json_store(nammu_fediverse_messages_file(), ['items' => array_slice(array_values($items), 0, 500)]);
}

function nammu_fediverse_actions_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_actions_file(), ['items' => []]);
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    return ['items' => array_values($items)];
}

function nammu_fediverse_deleted_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_deleted_file(), ['ids' => []]);
    $ids = is_array($store['ids'] ?? null) ? array_values(array_unique(array_map('strval', $store['ids']))) : [];
    return ['ids' => $ids];
}

function nammu_fediverse_save_deleted_store(array $ids): void
{
    $normalized = array_values(array_unique(array_filter(array_map('strval', $ids), static function (string $value): bool {
        return trim($value) !== '';
    })));
    nammu_fediverse_save_json_store(nammu_fediverse_deleted_file(), ['ids' => $normalized]);
}

function nammu_fediverse_save_actions_store(array $items): void
{
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
    });
    nammu_fediverse_save_json_store(nammu_fediverse_actions_file(), ['items' => array_slice(array_values($items), 0, 1000)]);
}

function nammu_fediverse_record_action(string $type, string $actorId, string $objectUrl, array $meta = []): void
{
    $type = strtolower(trim($type));
    $actorId = trim($actorId);
    $objectUrl = trim($objectUrl);
    if ($type === '' || $objectUrl === '') {
        return;
    }
    $items = nammu_fediverse_actions_store()['items'];
    $record = [
        'id' => substr(sha1($type . '|' . $actorId . '|' . $objectUrl . '|' . json_encode($meta) . '|' . microtime(true)), 0, 24),
        'type' => $type,
        'actor_id' => $actorId,
        'object_url' => $objectUrl,
        'published' => gmdate(DATE_ATOM),
    ];
    foreach ($meta as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $record[(string) $key] = $value;
        }
    }
    $items[] = $record;
    nammu_fediverse_save_actions_store($items);
}

function nammu_fediverse_actions_by_object(): array
{
    $items = nammu_fediverse_actions_store()['items'];
    $grouped = [];
    foreach ($items as $item) {
        $objectUrl = trim((string) ($item['object_url'] ?? ''));
        if ($objectUrl === '') {
            continue;
        }
        if (!isset($grouped[$objectUrl])) {
            $grouped[$objectUrl] = [
                'liked' => false,
                'replied' => false,
                'shared' => false,
                'reply_count' => 0,
                'share_count' => 0,
            ];
        }
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        if ($type === 'like') {
            $grouped[$objectUrl]['liked'] = true;
        } elseif ($type === 'boost') {
            $grouped[$objectUrl]['boosted'] = true;
            $grouped[$objectUrl]['boost_count']++;
        } elseif ($type === 'reply') {
            $grouped[$objectUrl]['replied'] = true;
            $grouped[$objectUrl]['reply_count']++;
        } elseif ($type === 'share') {
            $grouped[$objectUrl]['shared'] = true;
            $grouped[$objectUrl]['share_count']++;
        }
    }
    return $grouped;
}

function nammu_fediverse_action_state_for_item(array $item): array
{
    $states = nammu_fediverse_actions_by_object();
    $candidates = [];
    foreach (['object_id', 'url', 'id'] as $field) {
        $value = trim((string) ($item[$field] ?? ''));
        if ($value !== '') {
            $candidates[] = $value;
        }
    }
    foreach ($candidates as $candidate) {
        if (isset($states[$candidate])) {
            return $states[$candidate];
        }
    }
    return [
        'liked' => false,
        'boosted' => false,
        'replied' => false,
        'shared' => false,
        'boost_count' => 0,
        'reply_count' => 0,
        'share_count' => 0,
    ];
}

function nammu_fediverse_remote_boost_summary(): array
{
    $items = nammu_fediverse_timeline_store()['items'];
    $summary = [];
    foreach ($items as $item) {
        if (strtolower(trim((string) ($item['type'] ?? ''))) !== 'announce') {
            continue;
        }
        $actorId = trim((string) ($item['actor_id'] ?? ''));
        $identifiers = [];
        foreach (['object_id', 'url', 'id'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                $identifiers[] = $value;
            }
        }
        foreach (array_unique($identifiers) as $identifier) {
            if (!isset($summary[$identifier])) {
                $summary[$identifier] = [
                    'count' => 0,
                    'actors' => [],
                ];
            }
            if ($actorId !== '' && isset($summary[$identifier]['actors'][$actorId])) {
                continue;
            }
            $summary[$identifier]['count']++;
            if ($actorId !== '') {
                $summary[$identifier]['actors'][$actorId] = true;
            }
        }
    }
    return $summary;
}

function nammu_fediverse_remote_reply_summary(): array
{
    $items = nammu_fediverse_timeline_store()['items'];
    $summary = [];
    foreach ($items as $item) {
        $target = trim((string) ($item['target_url'] ?? ''));
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        $actorId = trim((string) ($item['actor_id'] ?? ''));
        if ($target === '' || $type === 'announce' || $type === 'like' || $type === 'delete') {
            continue;
        }
        $isReply = trim((string) ($item['content'] ?? '')) !== '' && trim((string) ($item['object_id'] ?? '')) !== '' && $target !== trim((string) ($item['object_id'] ?? ''));
        if (!$isReply) {
            continue;
        }
        if (!isset($summary[$target])) {
            $summary[$target] = [
                'count' => 0,
                'actors' => [],
            ];
        }
        if ($actorId !== '' && isset($summary[$target]['actors'][$actorId])) {
            continue;
        }
        $summary[$target]['count']++;
        if ($actorId !== '') {
            $summary[$target]['actors'][$actorId] = true;
        }
    }
    return $summary;
}

function nammu_fediverse_public_replies_by_object(): array
{
    $items = nammu_fediverse_actions_store()['items'];
    $grouped = [];
    foreach ($items as $item) {
        if (strtolower(trim((string) ($item['type'] ?? ''))) !== 'reply') {
            continue;
        }
        $objectUrl = trim((string) ($item['object_url'] ?? ''));
        $replyText = trim((string) ($item['reply_text'] ?? ''));
        if ($objectUrl === '' || $replyText === '') {
            continue;
        }
        if (!isset($grouped[$objectUrl])) {
            $grouped[$objectUrl] = [];
        }
        $grouped[$objectUrl][] = [
            'id' => trim((string) ($item['id'] ?? '')),
            'note_id' => trim((string) ($item['note_id'] ?? '')),
            'activity_id' => trim((string) ($item['activity_id'] ?? '')),
            'actor_id' => trim((string) ($item['actor_id'] ?? '')),
            'published' => trim((string) ($item['published'] ?? '')),
            'reply_text' => $replyText,
        ];
    }
    foreach ($grouped as &$replies) {
        usort($replies, static function (array $a, array $b): int {
            return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
        });
    }
    unset($replies);
    return $grouped;
}

function nammu_fediverse_public_action_activities(array $config): array
{
    $actorUrl = nammu_fediverse_actor_url($config);
    $actions = nammu_fediverse_actions_store()['items'];
    $activities = [];
    foreach ($actions as $action) {
        $type = strtolower(trim((string) ($action['type'] ?? '')));
        if ($type === 'boost') {
            $objectUrl = trim((string) ($action['object_url'] ?? ''));
            if ($objectUrl === '') {
                continue;
            }
            $published = trim((string) ($action['published'] ?? ''));
            if ($published === '') {
                $published = gmdate(DATE_ATOM);
            }
            $activityId = trim((string) ($action['activity_id'] ?? ''));
            if ($activityId === '') {
                $activityId = $actorUrl . '/announces/' . trim((string) ($action['id'] ?? substr(sha1($objectUrl . '|' . $published), 0, 24)));
            }
            $activities[] = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $activityId,
                'type' => 'Announce',
                'actor' => $actorUrl,
                'object' => $objectUrl,
                'to' => ['https://www.w3.org/ns/activitystreams#Public'],
                'published' => $published,
            ];
            continue;
        }
        if ($type === 'resend') {
            $resendItem = nammu_fediverse_resend_item_from_action($action);
            if (is_array($resendItem)) {
                $activities[] = nammu_fediverse_activity_for_local_item($resendItem, $config);
            }
        }
    }
    usort($activities, static function (array $a, array $b): int {
        return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
    });
    return $activities;
}

function nammu_fediverse_resend_item_from_action(array $action): ?array
{
    if (strtolower(trim((string) ($action['type'] ?? ''))) !== 'resend') {
        return null;
    }
    $objectId = trim((string) ($action['resend_object_id'] ?? ''));
    $objectUrl = trim((string) ($action['object_url'] ?? ''));
    if ($objectId === '' || $objectUrl === '') {
        return null;
    }
    return [
        'id' => $objectId,
        'url' => $objectUrl,
        'title' => trim((string) ($action['title'] ?? '')),
        'content' => trim((string) ($action['content'] ?? '')),
        'summary' => trim((string) ($action['summary'] ?? '')),
        'published' => trim((string) ($action['published'] ?? gmdate(DATE_ATOM))),
        'type' => trim((string) ($action['object_type'] ?? 'Article')) ?: 'Article',
        'image' => trim((string) ($action['image'] ?? '')),
    ];
}

function nammu_fediverse_replies_for_item(array $item): array
{
    $repliesByObject = nammu_fediverse_public_replies_by_object();
    $candidates = [];
    foreach (['object_id', 'url', 'id'] as $field) {
        $value = trim((string) ($item[$field] ?? ''));
        if ($value !== '') {
            $candidates[] = $value;
        }
    }
    foreach ($candidates as $candidate) {
        if (isset($repliesByObject[$candidate])) {
            return $repliesByObject[$candidate];
        }
    }
    return [];
}

function nammu_fediverse_public_replies_for_targets(array $targets): array
{
    $repliesByObject = nammu_fediverse_public_replies_by_object();
    $matches = [];
    foreach ($targets as $target) {
        $target = trim((string) $target);
        if ($target === '' || empty($repliesByObject[$target])) {
            continue;
        }
        foreach ($repliesByObject[$target] as $reply) {
            $replyId = trim((string) ($reply['id'] ?? ''));
            $key = $replyId !== '' ? $replyId : sha1(json_encode($reply));
            $matches[$key] = $reply;
        }
    }
    uasort($matches, static function (array $a, array $b): int {
        return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
    });
    return array_values($matches);
}

function nammu_fediverse_follow_actor(string $input): array
{
    $config = function_exists('nammu_load_config') ? nammu_load_config() : [];
    $actor = nammu_fediverse_resolve_actor($input, $config);
    if (!is_array($actor) || trim((string) ($actor['id'] ?? '')) === '') {
        return ['ok' => false, 'message' => 'No se pudo descubrir ese actor ActivityPub.'];
    }
    $actorId = trim((string) ($actor['id'] ?? ''));
    $inboxUrl = nammu_fediverse_remote_inbox_for_actor($actor);
    if ($inboxUrl === '') {
        return ['ok' => false, 'message' => 'Ese actor no expone un inbox usable.'];
    }
    $actorUrl = nammu_fediverse_actor_url($config);
    $followActivityId = $actorUrl . '/follows/' . substr(sha1($actorId . '|' . microtime(true) . '|' . random_int(0, PHP_INT_MAX)), 0, 24);
    $followActivity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $followActivityId,
        'type' => 'Follow',
        'actor' => $actorUrl,
        'object' => $actorId,
        'to' => [$actorId],
        'published' => gmdate(DATE_ATOM),
    ];

    $store = nammu_fediverse_following_store();
    $actors = $store['actors'];
    foreach ($actors as &$existing) {
        if ((string) ($existing['id'] ?? '') !== $actorId) {
            continue;
        }
        $existingFollowId = trim((string) ($existing['follow_activity_id'] ?? ''));
        if ($existingFollowId === '') {
            $delivery = nammu_fediverse_post_activity_response($inboxUrl, $followActivity, $config);
            if (empty($delivery['ok'])) {
                return ['ok' => false, 'message' => 'No se pudo enviar el seguimiento. ' . trim((string) ($delivery['message'] ?? ''))];
            }
            $existingFollowId = $followActivityId;
        }
        $existing = array_merge($existing, $actor, [
            'followed_at' => $existing['followed_at'] ?? gmdate(DATE_ATOM),
            'follow_activity_id' => $existingFollowId,
            'last_error' => '',
        ]);
        nammu_fediverse_save_following_store($actors);
        return ['ok' => true, 'message' => 'Ese actor ya estaba seguido y se ha actualizado su ficha.'];
    }
    unset($existing);

    $delivery = nammu_fediverse_post_activity_response($inboxUrl, $followActivity, $config);
    if (empty($delivery['ok'])) {
        return ['ok' => false, 'message' => 'No se pudo enviar el seguimiento. ' . trim((string) ($delivery['message'] ?? ''))];
    }

    $actor['followed_at'] = gmdate(DATE_ATOM);
    $actor['follow_activity_id'] = $followActivityId;
    $actor['last_checked_at'] = '';
    $actor['last_error'] = '';
    $actors[] = $actor;
    nammu_fediverse_save_following_store($actors);
    return ['ok' => true, 'message' => 'Actor seguido correctamente en el Fediverso.'];
}

function nammu_fediverse_unfollow_actor(string $actorId): bool
{
    $config = function_exists('nammu_load_config') ? nammu_load_config() : [];
    $actors = nammu_fediverse_following_store()['actors'];
    $before = count($actors);
    $remaining = [];
    foreach ($actors as $actor) {
        if ((string) ($actor['id'] ?? '') !== $actorId) {
            $remaining[] = $actor;
            continue;
        }
        $inboxUrl = nammu_fediverse_remote_inbox_for_actor($actor);
        $followActivityId = trim((string) ($actor['follow_activity_id'] ?? ''));
        if ($inboxUrl !== '' && $followActivityId !== '') {
            $localActorUrl = nammu_fediverse_actor_url($config);
            $undoActivity = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $localActorUrl . '/undo/' . substr(sha1($followActivityId . '|' . microtime(true) . '|' . random_int(0, PHP_INT_MAX)), 0, 24),
                'type' => 'Undo',
                'actor' => $localActorUrl,
                'object' => [
                    'id' => $followActivityId,
                    'type' => 'Follow',
                    'actor' => $localActorUrl,
                    'object' => $actorId,
                ],
                'to' => [$actorId],
                'published' => gmdate(DATE_ATOM),
            ];
            nammu_fediverse_post_activity($inboxUrl, $undoActivity, $config);
        }
    }
    if ($before === count($remaining)) {
        return false;
    }
    nammu_fediverse_save_following_store($remaining);
    return true;
}

function nammu_fediverse_restart_follow_actor(string $actorId): array
{
    $actorId = trim($actorId);
    if ($actorId === '') {
        return ['ok' => false, 'message' => 'No se recibió el actor a reiniciar.'];
    }
    foreach (nammu_fediverse_following_store()['actors'] as $actor) {
        if ((string) ($actor['id'] ?? '') !== $actorId) {
            continue;
        }
        return nammu_fediverse_follow_actor($actorId);
    }
    return ['ok' => false, 'message' => 'Ese actor ya no figura entre los seguidos.'];
}

function nammu_fediverse_normalize_remote_item(array $activity, array $actor, array $config): ?array
{
    $activityId = trim((string) ($activity['id'] ?? ''));
    $type = strtolower((string) ($activity['type'] ?? ''));
    if ($type === 'like') {
        return null;
    }
    $object = $activity;
    if ($type === 'create' && is_array($activity['object'] ?? null)) {
        $object = $activity['object'];
        $type = strtolower((string) ($object['type'] ?? 'note'));
    }
    if ($type === 'like') {
        return null;
    }
    $objectActorId = '';
    $objectActorName = '';
    $objectActorIcon = '';
    if ($type === 'announce') {
        $announcedObject = $activity['object'] ?? null;
        $objectId = '';
        if (is_string($announcedObject)) {
            $objectId = trim($announcedObject);
            $resolvedObject = nammu_fediverse_signed_fetch_json($objectId, $config);
            if (!is_array($resolvedObject)) {
                $resolvedObject = nammu_fediverse_fetch_json($objectId);
            }
            if (is_array($resolvedObject)) {
                $object = $resolvedObject;
            }
        } elseif (is_array($announcedObject)) {
            $object = $announcedObject;
            $objectId = trim((string) ($announcedObject['id'] ?? ''));
        }
        if ($objectId !== '' && trim((string) ($object['id'] ?? '')) === '') {
            $object['id'] = $objectId;
        }
        if (trim((string) ($object['url'] ?? '')) === '' && $objectId !== '') {
            $object['url'] = $objectId;
        }
        if (
            trim((string) ($object['content'] ?? '')) === ''
            && trim((string) ($object['summary'] ?? '')) === ''
            && trim((string) ($object['name'] ?? '')) === ''
        ) {
            $object['summary'] = 'Impulsó una publicación.';
        }
        $objectActorId = trim((string) (($object['attributedTo'] ?? '') ?: ($object['actor'] ?? '')));
        if ($objectActorId !== '') {
            $objectActor = nammu_fediverse_resolve_actor($objectActorId, $config);
            if (is_array($objectActor)) {
                $objectActorName = trim((string) (($objectActor['name'] ?? '') ?: ($objectActor['preferredUsername'] ?? '') ?: $objectActorId));
                $objectActorIcon = trim((string) ($objectActor['icon'] ?? ''));
            }
        }
    }
    $objectId = trim((string) (($object['id'] ?? '') ?: $activityId));
    $id = trim((string) ($activityId !== '' ? $activityId : $objectId));
    if ($id === '') {
        return null;
    }
    $url = nammu_fediverse_extract_url($object['url'] ?? '');
    $contentHtml = trim((string) ($object['content'] ?? ''));
    $published = trim((string) (($object['published'] ?? '') ?: ($activity['published'] ?? '')));
    $summaryText = trim((string) ($object['summary'] ?? ''));
    $content = $contentHtml !== '' ? nammu_fediverse_html_to_text($contentHtml) : $summaryText;
    $name = trim((string) (($object['name'] ?? '') ?: ''));
    $image = nammu_fediverse_extract_url($object['image'] ?? '');
    $attachments = [];
    $attachmentList = $object['attachment'] ?? [];
    if (is_array($attachmentList) && array_key_exists('type', $attachmentList)) {
        $attachmentList = [$attachmentList];
    }
    foreach ((array) $attachmentList as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $attachmentType = strtolower(trim((string) ($attachment['type'] ?? '')));
        $attachmentUrl = nammu_fediverse_extract_url($attachment['url'] ?? ($attachment['href'] ?? ''));
        if ($attachmentUrl === '') {
            continue;
        }
        $attachments[] = [
            'type' => $attachmentType !== '' ? $attachmentType : 'document',
            'url' => $attachmentUrl,
            'name' => trim((string) ($attachment['name'] ?? '')),
            'media_type' => trim((string) (($attachment['mediaType'] ?? '') ?: ($attachment['mimeType'] ?? ''))),
            'image' => nammu_fediverse_extract_url($attachment['image'] ?? ''),
            'summary' => trim((string) ($attachment['summary'] ?? '')),
        ];
    }
    $htmlImages = nammu_fediverse_extract_html_image_urls($contentHtml);
    foreach ($htmlImages as $htmlImageUrl) {
        $alreadyPresent = false;
        foreach ($attachments as $existingAttachment) {
            if (trim((string) ($existingAttachment['url'] ?? '')) === $htmlImageUrl) {
                $alreadyPresent = true;
                break;
            }
        }
        if (!$alreadyPresent) {
            $attachments[] = [
                'type' => 'image',
                'url' => $htmlImageUrl,
                'name' => '',
                'media_type' => 'image/*',
            ];
        }
        if ($image === '') {
            $image = $htmlImageUrl;
        }
    }
    if ($image !== '' && empty($attachments)) {
        $attachments[] = [
            'type' => 'image',
            'url' => $image,
            'name' => '',
            'media_type' => '',
        ];
    }
    if ($content === '' && $name === '' && empty($attachments)) {
        return null;
    }
    return [
        'id' => $id,
        'activity_id' => $activityId !== '' ? $activityId : $id,
        'object_id' => $objectId !== '' ? $objectId : $id,
        'url' => $url !== '' ? $url : $id,
        'title' => $name,
        'content' => $content,
        'content_html' => $contentHtml,
        'published' => $published !== '' ? $published : gmdate(DATE_ATOM),
        'type' => $type !== '' ? $type : 'note',
        'image' => $image,
        'attachments' => $attachments,
        'actor_id' => (string) ($actor['id'] ?? ''),
        'actor_name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? ''))),
        'actor_username' => trim((string) ($actor['preferredUsername'] ?? '')),
        'actor_url' => trim((string) (($actor['url'] ?? '') ?: ($actor['id'] ?? ''))),
        'actor_icon' => trim((string) ($actor['icon'] ?? '')),
        'target_actor_id' => $objectActorId !== '' ? $objectActorId : (string) ($actor['id'] ?? ''),
        'target_actor_name' => $objectActorName !== '' ? $objectActorName : trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? ''))),
        'target_actor_icon' => $objectActorIcon !== '' ? $objectActorIcon : trim((string) ($actor['icon'] ?? '')),
    ];
}

function nammu_fediverse_extract_outbox_items(array $outbox, array $actor, array $config): array
{
    $payload = $outbox;
    if (!empty($outbox['first']) && is_string($outbox['first'])) {
        $first = nammu_fediverse_fetch_json($outbox['first']);
        if (is_array($first)) {
            $payload = $first;
        }
    }
    $rawItems = [];
    if (is_array($payload['orderedItems'] ?? null)) {
        $rawItems = $payload['orderedItems'];
    } elseif (is_array($payload['items'] ?? null)) {
        $rawItems = $payload['items'];
    }
    $items = [];
    foreach ($rawItems as $rawItem) {
        if (is_string($rawItem) && preg_match('#^https?://#i', $rawItem)) {
            $rawItemPayload = nammu_fediverse_signed_fetch_json($rawItem, $config);
            if (!is_array($rawItemPayload)) {
                $rawItemPayload = nammu_fediverse_fetch_json($rawItem);
            }
            if (is_array($rawItemPayload)) {
                $rawItem = $rawItemPayload;
            }
        }
        if (!is_array($rawItem)) {
            continue;
        }
        $normalized = nammu_fediverse_normalize_remote_item($rawItem, $actor, $config);
        if ($normalized !== null) {
            $items[] = $normalized;
        }
    }
    return $items;
}

function nammu_fediverse_refresh_following(): array
{
    $config = function_exists('nammu_load_config') ? nammu_load_config() : [];
    $store = nammu_fediverse_following_store();
    $actors = $store['actors'];
    $timeline = nammu_fediverse_timeline_store()['items'];
    $timelineById = [];
    foreach ($timeline as $item) {
        $timelineById[(string) ($item['id'] ?? '')] = $item;
    }
    $checked = 0;
    $newItems = 0;
    foreach ($actors as &$actor) {
        $checked++;
        $actor['last_checked_at'] = gmdate(DATE_ATOM);
        $actorDoc = nammu_fediverse_resolve_actor((string) ($actor['id'] ?? ''), $config);
        if (!is_array($actorDoc) || trim((string) ($actorDoc['outbox'] ?? '')) === '') {
            $actor['last_error'] = 'No se pudo refrescar el actor remoto.';
            continue;
        }
        $actor = array_merge($actor, $actorDoc);
        $outbox = nammu_fediverse_signed_fetch_json((string) $actor['outbox'], $config);
        if (!is_array($outbox)) {
            $actor['last_error'] = 'No se pudo leer su outbox.';
            continue;
        }
        $actor['last_error'] = '';
        foreach (nammu_fediverse_extract_outbox_items($outbox, $actor, $config) as $item) {
            if (isset($timelineById[$item['id']])) {
                continue;
            }
            $timelineById[$item['id']] = $item;
            $newItems++;
        }
    }
    unset($actor);
    nammu_fediverse_save_following_store($actors);
    nammu_fediverse_save_timeline_store(array_values($timelineById));
    return ['checked' => $checked, 'new' => $newItems];
}

function nammu_fediverse_rebuild_timeline(): array
{
    nammu_fediverse_save_timeline_store([]);
    return nammu_fediverse_refresh_following();
}

function nammu_fediverse_strip_front_matter(string $content): string
{
    return (string) preg_replace('/^---\s*\R.*?\R---\s*\R?/s', '', $content);
}

function nammu_fediverse_parse_front_matter(string $content): array
{
    $metadata = [];
    if (preg_match('/^---\s*\R(.*?)\R---\s*\R?/s', $content, $matches) !== 1) {
        return $metadata;
    }
    $yaml = (string) ($matches[1] ?? '');
    if (class_exists('\Symfony\Component\Yaml\Yaml')) {
        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parse($yaml);
            return is_array($parsed) ? $parsed : [];
        } catch (Throwable $e) {
        }
    }
    foreach (preg_split('/\R/', $yaml) ?: [] as $line) {
        if (!str_contains((string) $line, ':')) {
            continue;
        }
        [$key, $value] = explode(':', (string) $line, 2);
        $metadata[trim($key)] = trim($value);
    }
    return $metadata;
}

function nammu_fediverse_plain_excerpt(string $content, int $max = 320): string
{
    $text = strip_tags($content);
    $text = preg_replace('/!\[[^\]]*]\([^)]+\)/', ' ', $text) ?? $text;
    $text = preg_replace('/\[[^\]]+]\([^)]+\)/', '$1', $text) ?? $text;
    $text = preg_replace('/[#>*_`~-]+/', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $max) {
        $text = mb_substr($text, 0, $max, 'UTF-8');
        $cut = max((int) mb_strrpos($text, ' ', 0, 'UTF-8'), 0);
        if ($cut > 0) {
            $text = mb_substr($text, 0, $cut, 'UTF-8');
        }
        $text = rtrim($text, " \t\n\r\0\x0B.,;:") . '…';
    }
    return $text;
}

function nammu_fediverse_first_markdown_image(string $content, string $baseUrl): string
{
    if (preg_match('/!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/u', $content, $matches)) {
        return nammu_fediverse_asset_url((string) ($matches[1] ?? ''), $baseUrl);
    }
    return '';
}

function nammu_fediverse_social_image_for_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (function_exists('nammu_actuality_extract_social_image')) {
        return trim((string) nammu_actuality_extract_social_image($url));
    }
    $response = nammu_fediverse_fetch($url, 'text/html,application/xhtml+xml');
    if ((int) ($response['status'] ?? 0) < 200 || (int) ($response['status'] ?? 0) >= 400) {
        return '';
    }
    $html = trim((string) ($response['body'] ?? ''));
    if ($html === '') {
        return '';
    }
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    foreach ([
        '//meta[@property="og:image"]/@content',
        '//meta[@property="twitter:image"]/@content',
        '//meta[@property="twitter:image:src"]/@content',
        '//meta[@name="twitter:image"]/@content',
        '//meta[@name="twitter:image:src"]/@content',
    ] as $query) {
        $nodes = @$xpath->query($query);
        if (!$nodes instanceof DOMNodeList || $nodes->length === 0) {
            continue;
        }
        $value = trim((string) $nodes->item(0)?->nodeValue);
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function nammu_fediverse_asset_url(string $image, string $baseUrl): string
{
    $value = trim($image);
    if ($value === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    $normalized = ltrim($value, '/');
    if (!str_starts_with($normalized, 'assets/')) {
        $normalized = 'assets/' . $normalized;
    }
    return rtrim($baseUrl, '/') . '/' . $normalized;
}

function nammu_fediverse_parse_date(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return gmdate(DATE_ATOM);
    }
    $timestamp = strtotime($value);
    return $timestamp ? gmdate(DATE_ATOM, $timestamp) : gmdate(DATE_ATOM);
}

function nammu_fediverse_parse_date_with_fallback(string $value, ?string $filePath = null): string
{
    $value = trim($value);
    if ($value !== '') {
        $timestamp = strtotime($value);
        if ($timestamp) {
            if (
                is_string($filePath)
                && $filePath !== ''
                && is_file($filePath)
                && preg_match('/^\d{4}[\/-]\d{2}[\/-]\d{2}$|^\d{2}[\/-]\d{2}[\/-]\d{4}$/', $value)
            ) {
                $fileTimestamp = @filemtime($filePath);
                if ($fileTimestamp) {
                    return gmdate(DATE_ATOM, $fileTimestamp);
                }
            }
            return gmdate(DATE_ATOM, $timestamp);
        }
    }
    if (is_string($filePath) && $filePath !== '' && is_file($filePath)) {
        $timestamp = @filemtime($filePath);
        if ($timestamp) {
            return gmdate(DATE_ATOM, $timestamp);
        }
    }
    return gmdate(DATE_ATOM);
}

function nammu_fediverse_meta_date(array $meta, ?string $filePath = null): string
{
    $candidates = [
        'Date', 'date',
        'Published', 'published',
        'Fecha', 'fecha',
        'Created', 'created',
        'Updated', 'updated',
    ];
    foreach ($candidates as $key) {
        if (!array_key_exists($key, $meta)) {
            continue;
        }
        $resolved = nammu_fediverse_parse_date_with_fallback((string) $meta[$key], $filePath);
        if ($resolved !== '') {
            return $resolved;
        }
    }
    return nammu_fediverse_parse_date_with_fallback('', $filePath);
}

function nammu_fediverse_local_content_items(array $config): array
{
    $baseUrl = nammu_fediverse_base_url($config);
    $deletedIds = array_fill_keys(nammu_fediverse_deleted_store()['ids'], true);
    $items = [];
    foreach (glob(dirname(__DIR__) . '/content/*.md') ?: [] as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $raw = (string) @file_get_contents($file);
        $meta = nammu_fediverse_parse_front_matter($raw);
        $status = strtolower(trim((string) ($meta['Status'] ?? $meta['status'] ?? 'published')));
        $template = strtolower(trim((string) ($meta['Template'] ?? $meta['template'] ?? 'post')));
        $visibility = strtolower(trim((string) ($meta['Visibility'] ?? $meta['visibility'] ?? 'public')));
        if ($status !== 'published' || $template === 'newsletter' || $visibility === 'private') {
            continue;
        }
        $slug = basename($file, '.md');
        $title = trim((string) (($meta['Title'] ?? $meta['title'] ?? '') ?: $slug));
        $description = trim((string) (($meta['Description'] ?? $meta['description'] ?? '') ?: ''));
        $content = nammu_fediverse_strip_front_matter($raw);
        $image = nammu_fediverse_asset_url((string) (($meta['Image'] ?? $meta['image'] ?? '') ?: ''), $baseUrl);
        $url = $template === 'podcast'
            ? $baseUrl . '/podcast/' . rawurlencode($slug)
            : $baseUrl . '/' . rawurlencode($slug);
        if ($image === '') {
            $image = nammu_fediverse_first_markdown_image($content, $baseUrl);
        }
        if ($image === '' && !empty($config['social']['home_image'])) {
            $image = nammu_fediverse_asset_url((string) $config['social']['home_image'], $baseUrl);
        }
        if ($image === '') {
            $image = nammu_fediverse_social_image_for_url($url);
        }
        $objectType = $template === 'podcast' ? 'Article' : 'Article';
        $itemId = $baseUrl . '/ap/objects/' . rawurlencode($template) . '-' . rawurlencode($slug);
        if (isset($deletedIds[$itemId])) {
            continue;
        }
        $items[] = [
            'id' => $itemId,
            'url' => $url,
            'title' => $title,
            'content' => $description !== '' ? $description : nammu_fediverse_plain_excerpt($content),
            'summary' => $description,
            'published' => nammu_fediverse_meta_date($meta, $file),
            'type' => $objectType,
            'image' => $image,
        ];
    }
    foreach (glob(dirname(__DIR__) . '/itinerarios/*/index.md') ?: [] as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $raw = (string) @file_get_contents($file);
        $meta = nammu_fediverse_parse_front_matter($raw);
        $status = strtolower(trim((string) ($meta['Status'] ?? $meta['status'] ?? 'published')));
        if ($status !== 'published') {
            continue;
        }
        $slug = basename(dirname($file));
        $title = trim((string) (($meta['Title'] ?? $meta['title'] ?? '') ?: $slug));
        $description = trim((string) (($meta['Description'] ?? $meta['description'] ?? '') ?: ''));
        $content = nammu_fediverse_strip_front_matter($raw);
        $image = nammu_fediverse_asset_url((string) (($meta['Image'] ?? $meta['image'] ?? '') ?: ''), $baseUrl);
        if ($image === '') {
            $image = nammu_fediverse_first_markdown_image($content, $baseUrl);
        }
        if ($image === '' && !empty($config['social']['home_image'])) {
            $image = nammu_fediverse_asset_url((string) $config['social']['home_image'], $baseUrl);
        }
        if ($image === '') {
            $image = nammu_fediverse_social_image_for_url($baseUrl . '/itinerarios/' . rawurlencode($slug));
        }
        $itemId = $baseUrl . '/ap/objects/itinerary-' . rawurlencode($slug);
        if (isset($deletedIds[$itemId])) {
            continue;
        }
        $items[] = [
            'id' => $itemId,
            'url' => $baseUrl . '/itinerarios/' . rawurlencode($slug),
            'title' => $title,
            'content' => $description !== '' ? $description : nammu_fediverse_plain_excerpt($content),
            'summary' => $description,
            'published' => nammu_fediverse_meta_date($meta, $file),
            'type' => 'Article',
            'image' => $image,
        ];
    }
    $actualityStore = nammu_fediverse_load_json_store(dirname(__DIR__) . '/config/actualidad-items.json', ['items' => []]);
    foreach ((array) ($actualityStore['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!empty($item['is_manual']) && strtolower(trim((string) ($item['via'] ?? ''))) === 'boost') {
            continue;
        }
        $id = trim((string) (($item['id'] ?? '') ?: sha1(json_encode($item))));
        $isManual = !empty($item['is_manual']);
        $itemId = $baseUrl . '/ap/objects/actualidad-' . rawurlencode($id);
        if (isset($deletedIds[$itemId])) {
            continue;
        }
        $title = trim((string) ($item['title'] ?? ''));
        $content = trim((string) (($item['raw_text'] ?? '') ?: ($item['description'] ?? '')));
        $items[] = [
            'id' => $itemId,
            'url' => trim((string) (($item['link'] ?? '') ?: ($baseUrl . '/actualidad.php'))),
            'title' => $title !== '' ? $title : ($isManual ? '' : 'Noticia'),
            'content' => $content,
            'summary' => trim((string) ($item['description'] ?? '')),
            'published' => gmdate(DATE_ATOM, (int) (($item['timestamp'] ?? 0) ?: time())),
            'type' => $isManual ? 'Note' : 'Article',
            'image' => trim((string) (($item['source_image'] ?? '') ?: ($item['image'] ?? ''))),
        ];
    }
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
    });
    return $items;
}

function nammu_fediverse_local_items_index(array $config): array
{
    $items = nammu_fediverse_local_content_items($config);
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        $resendItem = nammu_fediverse_resend_item_from_action($action);
        if (is_array($resendItem)) {
            $items[] = $resendItem;
        }
    }
    $byIdentifier = [];
    foreach ($items as $item) {
        $identifiers = [];
        foreach (['id', 'url'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                $identifiers[] = $value;
            }
        }
        foreach (array_unique($identifiers) as $identifier) {
            $byIdentifier[$identifier] = $item;
        }
    }
    return $byIdentifier;
}

function nammu_fediverse_local_reaction_summary(array $config): array
{
    $index = nammu_fediverse_local_items_index($config);
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    $summary = [];
    foreach ($activities as $entry) {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        $object = $payload['object'] ?? null;
        $target = '';
        if (in_array($type, ['like', 'announce'], true)) {
            if (is_string($object)) {
                $target = trim($object);
            } elseif (is_array($object)) {
                $target = trim((string) (($object['id'] ?? '') ?: ($object['url'] ?? '')));
            }
        } elseif ($type === 'create' && is_array($object) && strtolower(trim((string) ($object['type'] ?? ''))) === 'note') {
            $target = trim((string) ($object['inReplyTo'] ?? ''));
        }
        if ($target === '' || !isset($index[$target])) {
            continue;
        }
        $localItem = $index[$target];
        $localId = trim((string) ($localItem['id'] ?? ''));
        if ($localId === '') {
            continue;
        }
        if (!isset($summary[$localId])) {
            $summary[$localId] = [
                'item' => $localItem,
                'likes' => 0,
                'shares' => 0,
                'replies' => 0,
            ];
        }
        if ($type === 'like') {
            $summary[$localId]['likes']++;
        } elseif ($type === 'announce') {
            $summary[$localId]['shares']++;
        } elseif ($type === 'create') {
            $summary[$localId]['replies']++;
        }
    }
    return $summary;
}

function nammu_fediverse_local_reaction_details(array $config): array
{
    $index = nammu_fediverse_local_items_index($config);
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    $details = [];
    foreach ($activities as $entry) {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        $object = $payload['object'] ?? null;
        $target = '';
        if (in_array($type, ['like', 'announce'], true)) {
            if (is_string($object)) {
                $target = trim($object);
            } elseif (is_array($object)) {
                $target = trim((string) (($object['id'] ?? '') ?: ($object['url'] ?? '')));
            }
        } elseif ($type === 'create' && is_array($object) && strtolower(trim((string) ($object['type'] ?? ''))) === 'note') {
            $target = trim((string) ($object['inReplyTo'] ?? ''));
        }
        if ($target === '' || !isset($index[$target])) {
            continue;
        }
        $localId = trim((string) (($index[$target]['id'] ?? '')));
        if ($localId === '') {
            continue;
        }
        if (!isset($details[$localId])) {
            $details[$localId] = [
                'likes' => [],
                'shares' => [],
                'replies' => [],
            ];
        }
        $actorId = trim((string) ($payload['actor'] ?? ''));
        $actor = $actorId !== '' ? nammu_fediverse_resolve_actor($actorId, $config) : [];
        $actorEntry = [
            'id' => $actorId,
            'name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? '') ?: $actorId)),
            'icon' => trim((string) ($actor['icon'] ?? '')),
            'url' => trim((string) (($actor['url'] ?? '') ?: ($actor['id'] ?? ''))),
            'published' => trim((string) (($payload['published'] ?? '') ?: ($entry['received_at'] ?? ''))),
        ];
        $bucket = $type === 'like' ? 'likes' : ($type === 'announce' ? 'shares' : 'replies');
        $alreadyPresent = false;
        foreach ($details[$localId][$bucket] as $existingActor) {
            if (trim((string) ($existingActor['id'] ?? '')) !== '' && trim((string) ($existingActor['id'] ?? '')) === $actorId) {
                $alreadyPresent = true;
                break;
            }
        }
        if (!$alreadyPresent) {
            $details[$localId][$bucket][] = $actorEntry;
        }
    }
    return $details;
}

function nammu_fediverse_incoming_public_replies_by_object(array $config): array
{
    $index = nammu_fediverse_local_items_index($config);
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    usort($activities, static function (array $a, array $b): int {
        $payloadA = is_array($a['payload'] ?? null) ? $a['payload'] : [];
        $payloadB = is_array($b['payload'] ?? null) ? $b['payload'] : [];
        $objectA = is_array($payloadA['object'] ?? null) ? $payloadA['object'] : [];
        $objectB = is_array($payloadB['object'] ?? null) ? $payloadB['object'] : [];
        $publishedA = (string) (($objectA['published'] ?? '') ?: ($payloadA['published'] ?? '') ?: ($a['received_at'] ?? ''));
        $publishedB = (string) (($objectB['published'] ?? '') ?: ($payloadB['published'] ?? '') ?: ($b['received_at'] ?? ''));
        return strcmp($publishedA, $publishedB);
    });
    $localTargetMap = [];
    foreach ($index as $identifier => $localItem) {
        $localId = trim((string) ($localItem['id'] ?? ''));
        if ($localId !== '') {
            $localTargetMap[(string) $identifier] = $localId;
        }
    }
    $replyRootMap = [];
    $pendingReplies = [];
    $grouped = [];
    foreach ($activities as $entry) {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        if (strtolower(trim((string) ($payload['type'] ?? ''))) !== 'create') {
            continue;
        }
        $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
        if (strtolower(trim((string) ($object['type'] ?? ''))) !== 'note') {
            continue;
        }
        $target = trim((string) ($object['inReplyTo'] ?? ''));
        if ($target === '') {
            continue;
        }
        $actorId = trim((string) ($payload['actor'] ?? ''));
        $actor = $actorId !== '' ? nammu_fediverse_resolve_actor($actorId, $config) : [];
        $contentHtml = trim((string) ($object['content'] ?? ''));
        $contentText = trim((string) (function_exists('nammu_fediverse_html_to_text') ? nammu_fediverse_html_to_text($contentHtml) : strip_tags($contentHtml)));
        if ($contentText === '') {
            continue;
        }
        $replyId = trim((string) (($object['id'] ?? '') ?: ($payload['id'] ?? '')));
        $replyUrl = trim((string) (($object['url'] ?? '') ?: ''));
        $pendingReplies[] = [
            'target' => $target,
            'reply_id' => $replyId,
            'reply_url' => $replyUrl,
            'entry' => [
                'id' => $replyId,
                'url' => $replyUrl,
                'target_url' => $target,
                'published' => trim((string) (($object['published'] ?? '') ?: ($payload['published'] ?? '') ?: ($entry['received_at'] ?? ''))),
                'reply_text' => $contentText,
                'actor_id' => $actorId,
                'actor_name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? '') ?: $actorId)),
                'actor_icon' => trim((string) ($actor['icon'] ?? '')),
                'verified' => !empty($entry['verified']),
                'source' => 'incoming',
            ],
        ];
    }
    $maxPasses = max(1, count($pendingReplies) + 1);
    for ($pass = 0; $pass < $maxPasses; $pass++) {
        $resolvedThisPass = 0;
        foreach ($pendingReplies as $pendingIndex => $pendingReply) {
            $target = (string) ($pendingReply['target'] ?? '');
            $localId = $localTargetMap[$target] ?? $replyRootMap[$target] ?? null;
            if (!is_string($localId) || $localId === '') {
                continue;
            }
            if (!isset($grouped[$localId])) {
                $grouped[$localId] = [];
            }
            $grouped[$localId][] = $pendingReply['entry'];
            foreach (['reply_id', 'reply_url'] as $replyField) {
                $replyIdentifier = trim((string) ($pendingReply[$replyField] ?? ''));
                if ($replyIdentifier !== '') {
                    $replyRootMap[$replyIdentifier] = $localId;
                }
            }
            unset($pendingReplies[$pendingIndex]);
            $resolvedThisPass++;
        }
        if ($resolvedThisPass === 0) {
            break;
        }
    }
    foreach ($grouped as &$replies) {
        usort($replies, static function (array $a, array $b): int {
            return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
        });
    }
    unset($replies);
    return $grouped;
}

function nammu_fediverse_public_reply_message_entries(array $config): array
{
    $groupedReplies = nammu_fediverse_incoming_public_replies_by_object($config);
    $messages = [];
    foreach ($groupedReplies as $localId => $replies) {
        foreach ($replies as $reply) {
            $actorId = trim((string) ($reply['actor_id'] ?? ''));
            if ($actorId === '') {
                continue;
            }
            $messages[] = [
                'id' => trim((string) ($reply['id'] ?? '')),
                'activity_id' => '',
                'actor_id' => $actorId,
                'actor_name' => trim((string) ($reply['actor_name'] ?? $actorId)),
                'actor_icon' => trim((string) ($reply['actor_icon'] ?? '')),
                'direction' => 'incoming',
                'content' => trim((string) ($reply['reply_text'] ?? '')),
                'published' => trim((string) ($reply['published'] ?? '')),
                'url' => trim((string) (($reply['url'] ?? '') ?: ($reply['id'] ?? ''))),
                'delivery_status' => '',
                'verified' => !empty($reply['verified']),
                'visibility' => 'public',
                'reply_target_url' => trim((string) ($localId !== '' ? $localId : ($reply['target_url'] ?? ''))),
            ];
        }
    }
    return $messages;
}

function nammu_fediverse_outgoing_public_reply_message_entries(array $config): array
{
    $items = nammu_fediverse_actions_store()['items'];
    $messages = [];
    foreach ($items as $item) {
        if (strtolower(trim((string) ($item['type'] ?? ''))) !== 'reply') {
            continue;
        }
        $actorId = trim((string) ($item['actor_id'] ?? ''));
        $content = trim((string) ($item['reply_text'] ?? ''));
        if ($actorId === '' || $content === '') {
            continue;
        }
        $messages[] = [
            'id' => 'outgoing-reply-' . trim((string) ($item['id'] ?? sha1(json_encode($item)))),
            'activity_id' => '',
            'actor_id' => $actorId,
            'actor_name' => '',
            'actor_icon' => '',
            'direction' => 'outgoing',
            'content' => $content,
            'published' => trim((string) ($item['published'] ?? '')),
            'url' => '',
            'delivery_status' => 'delivered',
            'visibility' => 'public',
            'reply_target_url' => trim((string) ($item['object_url'] ?? '')),
        ];
    }
    return $messages;
}

function nammu_fediverse_public_thread_root_message_entries(array $config): array
{
    $localIndex = nammu_fediverse_local_items_index($config);
    $incoming = nammu_fediverse_public_reply_message_entries($config);
    $messages = [];
    $seen = [];
    foreach ($incoming as $message) {
        $actorId = trim((string) ($message['actor_id'] ?? ''));
        $target = trim((string) ($message['reply_target_url'] ?? ''));
        if ($actorId === '' || $target === '' || !isset($localIndex[$target])) {
            continue;
        }
        $localItem = $localIndex[$target];
        $localId = trim((string) ($localItem['id'] ?? ''));
        if ($localId === '') {
            continue;
        }
        $key = $actorId . '|' . $localId;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $messages[] = [
            'id' => 'local-root-' . substr(sha1($key), 0, 24),
            'activity_id' => '',
            'actor_id' => $actorId,
            'actor_name' => '',
            'actor_icon' => '',
            'direction' => 'outgoing',
            'content' => trim((string) (($localItem['content'] ?? '') ?: ($localItem['title'] ?? ''))),
            'published' => trim((string) ($localItem['published'] ?? '')),
            'url' => trim((string) ($localItem['url'] ?? '')),
            'delivery_status' => '',
            'visibility' => 'public',
            'reply_target_url' => $target,
            'is_thread_root' => true,
            'content_type' => trim((string) ($localItem['type'] ?? '')),
            'title' => trim((string) ($localItem['title'] ?? '')),
        ];
    }
    return $messages;
}

function nammu_fediverse_activity_for_local_item(array $item, array $config): array
{
    $actorUrl = nammu_fediverse_actor_url($config);
    $avatarUrl = nammu_fediverse_avatar_url($config);
    $objectType = (string) ($item['type'] ?? 'Article');
    $objectUrl = (string) ($item['url'] ?? '');
    $plainContent = trim((string) ($item['content'] ?? ''));
    if (strcasecmp($objectType, 'Note') === 0) {
        $contentHtml = nl2br(htmlspecialchars($plainContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    } else {
        $contentParts = [];
        if ($objectUrl !== '') {
            $escapedUrl = htmlspecialchars($objectUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $contentParts[] = '<p><a href="' . $escapedUrl . '">' . $escapedUrl . '</a></p>';
        }
        if ($plainContent !== '') {
            $contentParts[] = '<p>' . nl2br(htmlspecialchars($plainContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
        }
        $contentHtml = implode('', $contentParts);
    }
    $object = [
        'id' => (string) ($item['id'] ?? ''),
        'type' => $objectType,
        'attributedTo' => $actorUrl,
        'url' => $objectUrl,
        'name' => (string) ($item['title'] ?? ''),
        'content' => $contentHtml,
        'published' => (string) ($item['published'] ?? gmdate(DATE_ATOM)),
    ];
    $summary = trim((string) ($item['summary'] ?? ''));
    if ($summary !== '') {
        $object['summary'] = htmlspecialchars($summary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    $image = trim((string) ($item['image'] ?? ''));
    if ($image !== '') {
        $object['image'] = ['type' => 'Image', 'url' => $image];
    }
    if (strcasecmp($objectType, 'Note') !== 0 && $objectUrl !== '') {
        $linkAttachment = [
            'type' => 'Link',
            'href' => $objectUrl,
            'mediaType' => 'text/html',
            'name' => trim((string) (($item['title'] ?? '') ?: $objectUrl)),
        ];
        if ($image !== '') {
            $linkAttachment['image'] = ['type' => 'Image', 'url' => $image];
        }
        if ($summary !== '') {
            $linkAttachment['summary'] = htmlspecialchars($summary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $object['attachment'] = [$linkAttachment];
    } elseif ($image !== '') {
        $object['attachment'] = [[
            'type' => 'Image',
            'mediaType' => 'image/*',
            'url' => $image,
            'name' => trim((string) ($item['title'] ?? '')),
        ]];
    }
    if ($avatarUrl !== '') {
        $object['icon'] = ['type' => 'Image', 'url' => $avatarUrl];
    }
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => (string) ($item['id'] ?? '') . '/activity',
        'type' => 'Create',
        'actor' => $actorUrl,
        'published' => (string) ($item['published'] ?? gmdate(DATE_ATOM)),
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'object' => $object,
    ];
}

function nammu_fediverse_actor_document(array $config): array
{
    $actorUrl = nammu_fediverse_actor_url($config);
    $baseUrl = nammu_fediverse_base_url($config);
    $siteName = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
    $siteDescription = trim((string) ($config['site_description'] ?? ''));
    $keys = nammu_fediverse_keypair();
    $avatarUrl = nammu_fediverse_avatar_url($config);
    $document = [
        '@context' => [
            'https://www.w3.org/ns/activitystreams',
            'https://w3id.org/security/v1',
        ],
        'id' => $actorUrl,
        'type' => 'Person',
        'preferredUsername' => nammu_fediverse_preferred_username($config),
        'name' => $siteName,
        'summary' => $siteDescription !== '' ? $siteDescription : $siteName,
        'url' => $baseUrl,
        'inbox' => nammu_fediverse_inbox_url($config),
        'outbox' => nammu_fediverse_outbox_url($config),
        'followers' => nammu_fediverse_followers_url($config),
        'following' => nammu_fediverse_following_url($config),
        'discoverable' => true,
        'manuallyApprovesFollowers' => false,
        'published' => gmdate(DATE_ATOM, is_file(dirname(__DIR__) . '/index.php') ? ((int) @filemtime(dirname(__DIR__) . '/index.php') ?: time()) : time()),
    ];
    if ($avatarUrl !== '') {
        $document['icon'] = [
            'type' => 'Image',
            'url' => $avatarUrl,
        ];
        $document['image'] = [
            'type' => 'Image',
            'url' => $avatarUrl,
        ];
    }
    if (!empty($keys['public_key'])) {
        $document['publicKey'] = [
            'id' => nammu_fediverse_key_url($config),
            'owner' => $actorUrl,
            'publicKeyPem' => $keys['public_key'],
        ];
    }
    return $document;
}

function nammu_fediverse_public_key_document(array $config): array
{
    $keys = nammu_fediverse_keypair();
    return [
        '@context' => [
            'https://www.w3.org/ns/activitystreams',
            'https://w3id.org/security/v1',
        ],
        'id' => nammu_fediverse_key_url($config),
        'type' => 'Key',
        'owner' => nammu_fediverse_actor_url($config),
        'publicKeyPem' => (string) ($keys['public_key'] ?? ''),
    ];
}

function nammu_fediverse_webfinger_document(array $config): array
{
    $actorUrl = nammu_fediverse_actor_url($config);
    return [
        'subject' => nammu_fediverse_acct_uri($config),
        'links' => [
            [
                'rel' => 'self',
                'type' => 'application/activity+json',
                'href' => $actorUrl,
            ],
        ],
    ];
}

function nammu_fediverse_followers_collection(array $config): array
{
    $followers = array_values(array_filter(array_map(static function (array $follower): string {
        return (string) ($follower['id'] ?? '');
    }, nammu_fediverse_followers_store()['followers'])));
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => nammu_fediverse_followers_url($config),
        'type' => 'OrderedCollection',
        'totalItems' => count($followers),
        'orderedItems' => $followers,
    ];
}

function nammu_fediverse_following_collection(array $config): array
{
    $actors = nammu_fediverse_following_store()['actors'];
    $ids = array_values(array_filter(array_map(static function (array $actor): string {
        return (string) ($actor['id'] ?? '');
    }, $actors)));
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => nammu_fediverse_following_url($config),
        'type' => 'OrderedCollection',
        'totalItems' => count($ids),
        'orderedItems' => $ids,
    ];
}

function nammu_fediverse_outbox_document(array $config): array
{
    $activities = array_map(static function (array $item) use ($config): array {
        return nammu_fediverse_activity_for_local_item($item, $config);
    }, nammu_fediverse_local_content_items($config));
    $activities = array_merge($activities, nammu_fediverse_public_action_activities($config));
    usort($activities, static function (array $a, array $b): int {
        return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
    });
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => nammu_fediverse_outbox_url($config),
        'type' => 'OrderedCollection',
        'totalItems' => count($activities),
        'orderedItems' => $activities,
    ];
}

function nammu_fediverse_object_document(string $routePath, array $config): ?array
{
    $routePath = trim($routePath);
    if ($routePath === '') {
        return null;
    }
    $baseUrl = rtrim(nammu_fediverse_base_url($config), '/');
    foreach (nammu_fediverse_local_content_items($config) as $item) {
        $itemId = trim((string) ($item['id'] ?? ''));
        if ($itemId === '') {
            continue;
        }
        $itemPath = trim((string) (parse_url($itemId, PHP_URL_PATH) ?? ''));
        if ($itemPath !== $routePath) {
            continue;
        }
        $activity = nammu_fediverse_activity_for_local_item($item, $config);
        $object = is_array($activity['object'] ?? null) ? $activity['object'] : null;
        if (!is_array($object)) {
            return null;
        }
        if (trim((string) ($object['id'] ?? '')) === '') {
            $object['id'] = $baseUrl . $routePath;
        }
        return $object;
    }
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        $resendItem = nammu_fediverse_resend_item_from_action($action);
        if (!is_array($resendItem)) {
            continue;
        }
        $itemPath = trim((string) (parse_url((string) ($resendItem['id'] ?? ''), PHP_URL_PATH) ?? ''));
        if ($itemPath !== $routePath) {
            continue;
        }
        $activity = nammu_fediverse_activity_for_local_item($resendItem, $config);
        $object = is_array($activity['object'] ?? null) ? $activity['object'] : null;
        if (is_array($object)) {
            return $object;
        }
    }
    return null;
}

function nammu_fediverse_store_inbox_activity(array $payload, array $meta = []): void
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    $activities[] = [
        'received_at' => gmdate(DATE_ATOM),
        'payload' => $payload,
        'verified' => !empty($meta['verified']),
        'verification_error' => trim((string) ($meta['verification_error'] ?? '')),
        'signature_key_id' => trim((string) ($meta['signature_key_id'] ?? '')),
        'signed_headers' => trim((string) ($meta['signed_headers'] ?? '')),
    ];
    $store['activities'] = array_slice($activities, -50);
    nammu_fediverse_save_json_store(nammu_fediverse_inbox_file(), $store);
}

function nammu_fediverse_remove_timeline_items(array $identifiers): int
{
    $identifiers = array_values(array_filter(array_map(static function ($value): string {
        return trim((string) $value);
    }, $identifiers)));
    if (empty($identifiers)) {
        return 0;
    }
    $timeline = nammu_fediverse_timeline_store()['items'];
    $before = count($timeline);
    $timeline = array_values(array_filter($timeline, static function (array $item) use ($identifiers): bool {
        $id = trim((string) ($item['id'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        return !in_array($id, $identifiers, true) && !in_array($url, $identifiers, true);
    }));
    if ($before !== count($timeline)) {
        nammu_fediverse_save_timeline_store($timeline);
    }
    return $before - count($timeline);
}

function nammu_fediverse_followers_add_or_update(array $actor): void
{
    $followers = nammu_fediverse_followers_store()['followers'];
    $id = trim((string) ($actor['id'] ?? ''));
    if ($id === '') {
        return;
    }
    foreach ($followers as &$existing) {
        if ((string) ($existing['id'] ?? '') !== $id) {
            continue;
        }
        $existing = array_merge($existing, $actor, ['followed_at' => $existing['followed_at'] ?? gmdate(DATE_ATOM)]);
        nammu_fediverse_save_followers_store($followers);
        return;
    }
    unset($existing);
    $actor['followed_at'] = gmdate(DATE_ATOM);
    $followers[] = $actor;
    nammu_fediverse_save_followers_store($followers);
}

function nammu_fediverse_followers_remove(string $actorId): void
{
    $followers = array_values(array_filter(
        nammu_fediverse_followers_store()['followers'],
        static fn(array $follower): bool => (string) ($follower['id'] ?? '') !== $actorId
    ));
    nammu_fediverse_save_followers_store($followers);
}

function nammu_fediverse_remote_inbox_for_actor(array $actor): string
{
    $shared = trim((string) ($actor['sharedInbox'] ?? ''));
    if ($shared !== '') {
        return $shared;
    }
    return trim((string) ($actor['inbox'] ?? ''));
}

function nammu_fediverse_known_actors(): array
{
    $actors = [];
    foreach (nammu_fediverse_following_store()['actors'] as $actor) {
        $id = trim((string) ($actor['id'] ?? ''));
        if ($id !== '') {
            $actors[$id] = $actor;
        }
    }
    foreach (nammu_fediverse_followers_store()['followers'] as $actor) {
        $id = trim((string) ($actor['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $actors[$id] = array_merge($actors[$id] ?? [], $actor);
    }
    ksort($actors);
    return $actors;
}

function nammu_fediverse_message_recipients(): array
{
    $actors = nammu_fediverse_known_actors();
    uasort($actors, static function (array $a, array $b): int {
        $nameA = strtolower(trim((string) (($a['name'] ?? '') ?: ($a['preferredUsername'] ?? ''))));
        $nameB = strtolower(trim((string) (($b['name'] ?? '') ?: ($b['preferredUsername'] ?? ''))));
        return $nameA <=> $nameB;
    });
    return $actors;
}

function nammu_fediverse_store_message(array $message): void
{
    $items = nammu_fediverse_messages_store()['items'];
    $id = trim((string) ($message['id'] ?? ''));
    if ($id === '') {
        return;
    }
    foreach ($items as $index => $existing) {
        if ((string) ($existing['id'] ?? '') === $id) {
            $items[$index] = array_merge($existing, $message);
            nammu_fediverse_save_messages_store($items);
            return;
        }
    }
    $items[] = $message;
    nammu_fediverse_save_messages_store($items);
}

function nammu_fediverse_grouped_messages(): array
{
    $items = nammu_fediverse_messages_store()['items'];
    $groups = [];
    foreach ($items as $item) {
        $actorId = trim((string) ($item['actor_id'] ?? ''));
        if ($actorId === '') {
            continue;
        }
        if (!isset($groups[$actorId])) {
            $groups[$actorId] = [];
        }
        $groups[$actorId][] = $item;
    }
    uasort($groups, static function (array $a, array $b): int {
        $publishedA = (string) (($a[0]['published'] ?? '') ?: '');
        $publishedB = (string) (($b[0]['published'] ?? '') ?: '');
        return strcmp($publishedB, $publishedA);
    });
    return $groups;
}

function nammu_fediverse_is_direct_message_activity(array $payload, array $config): bool
{
    if (strtolower((string) ($payload['type'] ?? '')) !== 'create' || !is_array($payload['object'] ?? null)) {
        return false;
    }
    $object = $payload['object'];
    if (strtolower((string) ($object['type'] ?? '')) !== 'note') {
        return false;
    }
    $actorUrl = nammu_fediverse_actor_url($config);
    $recipients = [];
    foreach ((array) ($object['to'] ?? []) as $value) {
        if (is_string($value)) {
            $recipients[] = trim($value);
        }
    }
    foreach ((array) ($payload['to'] ?? []) as $value) {
        if (is_string($value)) {
            $recipients[] = trim($value);
        }
    }
    return in_array($actorUrl, $recipients, true);
}

function nammu_fediverse_notification_entries(array $config): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    $filtered = array_values(array_filter($activities, static function (array $entry) use ($config): bool {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        if ($type === 'delete') {
            return false;
        }
        return !nammu_fediverse_is_direct_message_activity($payload, $config);
    }));
    return array_values(array_reverse($filtered));
}

function nammu_fediverse_post_activity(string $inboxUrl, array $activity, array $config): bool
{
    $result = nammu_fediverse_post_activity_response($inboxUrl, $activity, $config);
    return !empty($result['ok']);
}

function nammu_fediverse_post_activity_response(string $inboxUrl, array $activity, array $config): array
{
    $body = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($body) || $body === '') {
        return ['ok' => false, 'status' => 0, 'body' => '', 'message' => 'No se pudo serializar la actividad.'];
    }
    $response = nammu_fediverse_signed_fetch($inboxUrl, $config, 'POST', $body);
    $status = (int) ($response['status'] ?? 0);
    $responseBody = trim((string) ($response['body'] ?? ''));
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'status' => $status, 'body' => $responseBody, 'message' => ''];
    }
    return [
        'ok' => false,
        'status' => $status,
        'body' => $responseBody,
        'message' => 'HTTP ' . $status . ($responseBody !== '' ? ': ' . $responseBody : ''),
    ];
}

function nammu_fediverse_resolve_object_reference(string $candidate, array $config): string
{
    $candidate = trim($candidate);
    if ($candidate === '' || !preg_match('#^https?://#i', $candidate)) {
        return $candidate;
    }
    $payload = nammu_fediverse_signed_fetch_json($candidate, $config);
    if (!is_array($payload)) {
        $payload = nammu_fediverse_fetch_json($candidate);
    }
    if (!is_array($payload)) {
        return $candidate;
    }
    $type = strtolower(trim((string) ($payload['type'] ?? '')));
    if (in_array($type, ['create', 'update', 'announce'], true)) {
        $object = $payload['object'] ?? null;
        if (is_string($object) && trim($object) !== '') {
            return trim($object);
        }
        if (is_array($object)) {
            $objectId = trim((string) (($object['id'] ?? '') ?: ''));
            if ($objectId !== '') {
                return $objectId;
            }
        }
    }
    $payloadId = trim((string) (($payload['id'] ?? '') ?: ''));
    return $payloadId !== '' ? $payloadId : $candidate;
}

function nammu_fediverse_verify_inbox_request(array $payload, array $headers, string $rawBody, array $config): array
{
    $actorId = trim((string) ($payload['actor'] ?? ''));
    if ($actorId === '') {
        return ['verified' => false, 'error' => 'La actividad no incluye actor.', 'key_id' => '', 'signed_headers' => ''];
    }
    $signatureHeader = trim((string) (($headers['signature'] ?? '') ?: ($headers['authorization'] ?? '')));
    if ($signatureHeader === '') {
        return ['verified' => false, 'error' => 'Falta la cabecera Signature.', 'key_id' => '', 'signed_headers' => ''];
    }
    if (str_starts_with(strtolower($signatureHeader), 'signature ')) {
        $signatureHeader = trim(substr($signatureHeader, 10));
    }
    $signatureData = nammu_fediverse_parse_signature_header($signatureHeader);
    $signature = trim((string) ($signatureData['signature'] ?? ''));
    $keyId = trim((string) ($signatureData['keyId'] ?? $signatureData['keyid'] ?? ''));
    $signedHeaders = trim((string) ($signatureData['headers'] ?? ''));
    if ($signature === '') {
        return ['verified' => false, 'error' => 'La cabecera Signature no contiene firma.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
    }
    if ($rawBody !== '') {
        $digestHeader = trim((string) (($headers['digest'] ?? '') ?: ($headers['content-digest'] ?? '')));
        if ($digestHeader === '') {
            return ['verified' => false, 'error' => 'Falta la cabecera Digest.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
        }
        [$digestAlgo, $digestValue] = nammu_fediverse_parse_digest_value($digestHeader);
        $expectedDigest = base64_encode(hash('sha256', $rawBody, true));
        $digestMatches = false;
        if (($digestAlgo === 'sha-256' || $digestAlgo === 'sha256') && $digestValue !== '') {
            $digestMatches = hash_equals($expectedDigest, $digestValue);
        } elseif (hash_equals(nammu_fediverse_digest_header($rawBody), $digestHeader)) {
            $digestMatches = true;
        }
        if (!$digestMatches) {
            return ['verified' => false, 'error' => 'Digest inválido.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
        }
    }
    $method = strtolower((string) ($_SERVER['REQUEST_METHOD'] ?? 'post'));
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/ap/inbox');
    $requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/ap/inbox';
    $requestQuery = parse_url($requestUri, PHP_URL_QUERY);
    $requestTarget = $method . ' ' . $requestPath . ($requestQuery ? '?' . $requestQuery : '');
    $signingString = nammu_fediverse_build_incoming_signed_string($signatureData, $headers, $requestTarget);
    if ($signingString === null || $signingString === '') {
        return ['verified' => false, 'error' => 'No se pudo reconstruir la firma.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
    }
    $actor = nammu_fediverse_resolve_actor($actorId, $config);
    $publicKeyPem = trim((string) ($actor['public_key_pem'] ?? ''));
    $publicKeyId = trim((string) ($actor['public_key_id'] ?? ''));
    if ($keyId !== '' && $publicKeyId !== '' && $keyId !== $publicKeyId) {
        $keyDocument = nammu_fediverse_fetch_json($keyId);
        if (is_array($keyDocument)) {
            $fetchedPem = trim((string) (($keyDocument['publicKeyPem'] ?? '') ?: ''));
            $fetchedOwner = trim((string) (($keyDocument['owner'] ?? '') ?: ($keyDocument['id'] ?? '')));
            if ($fetchedPem !== '' && ($fetchedOwner === '' || $fetchedOwner === $actorId || $fetchedOwner === $keyId)) {
                $publicKeyPem = $fetchedPem;
                $publicKeyId = $keyId;
            } else {
                return ['verified' => false, 'error' => 'El keyId de la firma no coincide con la clave pública del actor remoto.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
            }
        } else {
            return ['verified' => false, 'error' => 'El keyId de la firma no coincide con la clave pública del actor remoto.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
        }
    }
    if ($publicKeyPem === '') {
        return ['verified' => false, 'error' => 'No se pudo obtener la clave pública del actor remoto.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
    }
    $publicKey = openssl_pkey_get_public($publicKeyPem);
    if ($publicKey === false) {
        return ['verified' => false, 'error' => 'Clave pública remota inválida.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
    }
    $ok = openssl_verify($signingString, base64_decode($signature, true) ?: '', $publicKey, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        return ['verified' => false, 'error' => 'Firma HTTP inválida.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
    }
    return ['verified' => true, 'error' => '', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
}

function nammu_fediverse_private_message_activity(array $recipient, string $text, array $config): array
{
    $actorUrl = nammu_fediverse_actor_url($config);
    $messageId = $actorUrl . '/messages/' . substr(sha1($recipient['id'] . '|' . $text . '|' . microtime(true) . '|' . random_int(0, PHP_INT_MAX)), 0, 24);
    $published = gmdate(DATE_ATOM);
    $recipientId = trim((string) ($recipient['id'] ?? ''));
    $recipientName = trim((string) ($recipient['preferredUsername'] ?? ''));
    $recipientHost = parse_url($recipientId, PHP_URL_HOST);
    $recipientHandle = $recipientName !== '' ? '@' . $recipientName . ($recipientHost ? '@' . $recipientHost : '') : $recipientId;
    $mentionHtml = $recipientId !== '' && $recipientHandle !== ''
        ? '<a href="' . htmlspecialchars($recipientId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="mention">@' . htmlspecialchars(ltrim($recipientHandle, '@'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>'
        : '';
    $plainText = trim($text);
    $content = trim($mentionHtml . ($plainText !== '' ? ' ' . nl2br(htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) : ''));
    $tag = [];
    if ($recipientId !== '' && $recipientHandle !== '') {
        $tag[] = [
            'type' => 'Mention',
            'href' => $recipientId,
            'name' => $recipientHandle,
        ];
    }
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $messageId . '/activity',
        'type' => 'Create',
        'actor' => $actorUrl,
        'to' => [$recipientId],
        'cc' => [],
        'object' => [
            'id' => $messageId,
            'type' => 'Note',
            'attributedTo' => $actorUrl,
            'to' => [$recipientId],
            'cc' => [],
            'content' => $content,
            'tag' => $tag,
            'url' => $messageId,
            'published' => $published,
        ],
    ];
}

function nammu_fediverse_send_private_message(string $recipientId, string $text, array $config): array
{
    $recipientId = trim($recipientId);
    $plainText = trim($text);
    if ($recipientId === '' || $plainText === '') {
        return ['ok' => false, 'message' => 'Falta el destinatario o el texto del mensaje.'];
    }
    $recipients = nammu_fediverse_message_recipients();
    $recipient = $recipients[$recipientId] ?? null;
    if (!is_array($recipient)) {
        $recipient = nammu_fediverse_resolve_actor($recipientId, $config);
    }
    if (!is_array($recipient)) {
        return ['ok' => false, 'message' => 'No se pudo resolver el destinatario.'];
    }
    $inboxUrl = nammu_fediverse_remote_inbox_for_actor($recipient);
    if ($inboxUrl === '') {
        return ['ok' => false, 'message' => 'Ese actor no expone un inbox usable.'];
    }
    $activity = nammu_fediverse_private_message_activity($recipient, $plainText, $config);
    $messageRecord = [
        'id' => (string) ($activity['object']['id'] ?? ''),
        'activity_id' => (string) ($activity['id'] ?? ''),
        'actor_id' => (string) ($recipient['id'] ?? ''),
        'actor_name' => trim((string) (($recipient['name'] ?? '') ?: ($recipient['preferredUsername'] ?? ''))),
        'actor_icon' => trim((string) ($recipient['icon'] ?? '')),
        'direction' => 'outgoing',
        'content' => $plainText,
        'published' => (string) ($activity['object']['published'] ?? gmdate(DATE_ATOM)),
        'url' => '',
        'delivery_status' => 'pending',
    ];
    if (!nammu_fediverse_post_activity($inboxUrl, $activity, $config)) {
        $messageRecord['delivery_status'] = 'failed';
        nammu_fediverse_store_message($messageRecord);
        return ['ok' => false, 'message' => 'No se pudo entregar el mensaje privado.'];
    }
    $messageRecord['delivery_status'] = 'delivered';
    nammu_fediverse_store_message($messageRecord);
    return ['ok' => true, 'message' => 'Mensaje privado enviado.'];
}

function nammu_fediverse_send_like(string $recipientId, string $objectUrl, array $config): array
{
    $recipientId = trim($recipientId);
    $objectUrl = nammu_fediverse_resolve_object_reference(trim($objectUrl), $config);
    if ($recipientId === '' || $objectUrl === '') {
        return ['ok' => false, 'message' => 'Falta el destinatario o la publicación a marcar como favorita.'];
    }
    $recipient = nammu_fediverse_resolve_actor($recipientId, $config);
    if (!is_array($recipient)) {
        return ['ok' => false, 'message' => 'No se pudo resolver el actor remoto.'];
    }
    $inboxUrl = nammu_fediverse_remote_inbox_for_actor($recipient);
    if ($inboxUrl === '') {
        return ['ok' => false, 'message' => 'Ese actor no expone un inbox usable.'];
    }
    $actorUrl = nammu_fediverse_actor_url($config);
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $actorUrl . '/likes/' . substr(sha1($recipientId . '|' . $objectUrl . '|' . microtime(true)), 0, 24),
        'type' => 'Like',
        'actor' => $actorUrl,
        'object' => $objectUrl,
        'to' => [$recipientId],
        'published' => gmdate(DATE_ATOM),
    ];
    $delivery = nammu_fediverse_post_activity_response($inboxUrl, $activity, $config);
    if (empty($delivery['ok'])) {
        return ['ok' => false, 'message' => 'No se pudo enviar el favorito. ' . trim((string) ($delivery['message'] ?? ''))];
    }
    nammu_fediverse_record_action('like', $recipientId, $objectUrl);
    return ['ok' => true, 'message' => 'Favorito enviado.'];
}

function nammu_fediverse_send_announce(string $recipientId, string $objectUrl, array $config): array
{
    $recipientId = trim($recipientId);
    $objectUrl = nammu_fediverse_resolve_object_reference(trim($objectUrl), $config);
    if ($recipientId === '' || $objectUrl === '') {
        return ['ok' => false, 'message' => 'Falta el destinatario o la publicación a impulsar.'];
    }
    $recipient = nammu_fediverse_resolve_actor($recipientId, $config);
    if (!is_array($recipient)) {
        return ['ok' => false, 'message' => 'No se pudo resolver el actor remoto.'];
    }
    $inboxUrl = nammu_fediverse_remote_inbox_for_actor($recipient);
    if ($inboxUrl === '') {
        return ['ok' => false, 'message' => 'Ese actor no expone un inbox usable.'];
    }
    $actorUrl = nammu_fediverse_actor_url($config);
    $activityId = $actorUrl . '/announces/' . substr(sha1($recipientId . '|' . $objectUrl . '|' . microtime(true)), 0, 24);
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $activityId,
        'type' => 'Announce',
        'actor' => $actorUrl,
        'object' => $objectUrl,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [$recipientId],
        'published' => gmdate(DATE_ATOM),
    ];
    $deliveries = [];
    $deliveries[$inboxUrl] = nammu_fediverse_post_activity_response($inboxUrl, $activity, $config);
    foreach (nammu_fediverse_followers_store()['followers'] as $follower) {
        $followerInbox = nammu_fediverse_remote_inbox_for_actor($follower);
        if ($followerInbox === '' || isset($deliveries[$followerInbox])) {
            continue;
        }
        $deliveries[$followerInbox] = nammu_fediverse_post_activity_response($followerInbox, $activity, $config);
    }
    $successfulDeliveries = 0;
    $lastError = '';
    foreach ($deliveries as $deliveryResult) {
        if (!empty($deliveryResult['ok'])) {
            $successfulDeliveries++;
            continue;
        }
        $lastError = trim((string) ($deliveryResult['message'] ?? ''));
    }
    if ($successfulDeliveries === 0) {
        return ['ok' => false, 'message' => 'No se pudo enviar el impulso. ' . $lastError];
    }
    nammu_fediverse_record_action('boost', $recipientId, $objectUrl, [
        'activity_id' => $activityId,
        'published' => (string) ($activity['published'] ?? gmdate(DATE_ATOM)),
    ]);
    return ['ok' => true, 'message' => 'Impulso enviado. Entregas: ' . $successfulDeliveries . '.'];
}

function nammu_fediverse_send_reply(string $recipientId, string $objectUrl, string $text, array $config): array
{
    $recipientId = trim($recipientId);
    $objectUrl = nammu_fediverse_resolve_object_reference(trim($objectUrl), $config);
    $plainText = trim($text);
    if ($recipientId === '' || $objectUrl === '' || $plainText === '') {
        return ['ok' => false, 'message' => 'Falta el destinatario, la publicación o el texto de la respuesta.'];
    }
    $recipient = nammu_fediverse_resolve_actor($recipientId, $config);
    if (!is_array($recipient)) {
        return ['ok' => false, 'message' => 'No se pudo resolver el actor remoto.'];
    }
    $inboxUrl = nammu_fediverse_remote_inbox_for_actor($recipient);
    if ($inboxUrl === '') {
        return ['ok' => false, 'message' => 'Ese actor no expone un inbox usable.'];
    }
    $actorUrl = nammu_fediverse_actor_url($config);
    $recipientName = trim((string) ($recipient['preferredUsername'] ?? ''));
    $recipientHost = parse_url($recipientId, PHP_URL_HOST);
    $recipientHandle = $recipientName !== '' ? '@' . $recipientName . ($recipientHost ? '@' . $recipientHost : '') : '';
    $mentionHtml = $recipientId !== '' && $recipientHandle !== ''
        ? '<a href="' . htmlspecialchars($recipientId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="mention">' . htmlspecialchars($recipientHandle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>'
        : '';
    $contentHtml = trim($mentionHtml . ($plainText !== '' ? ' ' . nl2br(htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) : ''));
    $tag = [];
    if ($recipientId !== '' && $recipientHandle !== '') {
        $tag[] = [
            'type' => 'Mention',
            'href' => $recipientId,
            'name' => $recipientHandle,
        ];
    }
    $cc = [$recipientId];
    $recipientFollowers = trim((string) ($recipient['followers'] ?? ''));
    if ($recipientFollowers !== '' && !in_array($recipientFollowers, $cc, true)) {
        $cc[] = $recipientFollowers;
    }
    $noteId = $actorUrl . '/replies/' . substr(sha1($recipientId . '|' . $objectUrl . '|' . $plainText . '|' . microtime(true)), 0, 24);
    $published = gmdate(DATE_ATOM);
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $noteId . '/activity',
        'type' => 'Create',
        'actor' => $actorUrl,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => $cc,
        'published' => $published,
        'object' => [
            'id' => $noteId,
            'type' => 'Note',
            'attributedTo' => $actorUrl,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => $cc,
            'content' => $contentHtml,
            'tag' => $tag,
            'published' => $published,
            'inReplyTo' => $objectUrl,
        ],
    ];
    $delivery = nammu_fediverse_post_activity_response($inboxUrl, $activity, $config);
    if (empty($delivery['ok'])) {
        return ['ok' => false, 'message' => 'No se pudo enviar la respuesta. ' . trim((string) ($delivery['message'] ?? ''))];
    }
    nammu_fediverse_record_action('reply', $recipientId, $objectUrl, [
        'reply_text' => $plainText,
        'note_id' => $noteId,
        'activity_id' => (string) ($activity['id'] ?? ''),
    ]);
    return ['ok' => true, 'message' => 'Respuesta enviada.'];
}

function nammu_fediverse_delete_public_reply(string $actionId, array $config): array
{
    $actionId = trim($actionId);
    if ($actionId === '') {
        return ['ok' => false, 'message' => 'No se recibió la respuesta a borrar.'];
    }
    $items = nammu_fediverse_actions_store()['items'];
    $targetAction = null;
    $remaining = [];
    foreach ($items as $item) {
        if ($targetAction === null && strtolower(trim((string) ($item['type'] ?? ''))) === 'reply' && trim((string) ($item['id'] ?? '')) === $actionId) {
            $targetAction = $item;
            continue;
        }
        $remaining[] = $item;
    }
    if (!is_array($targetAction)) {
        return ['ok' => false, 'message' => 'No se encontró esa respuesta pública.'];
    }

    $noteId = trim((string) ($targetAction['note_id'] ?? ''));
    $recipientId = trim((string) ($targetAction['actor_id'] ?? ''));
    if ($noteId === '' || $recipientId === '') {
        nammu_fediverse_save_actions_store($remaining);
        return [
            'ok' => true,
            'message' => 'La respuesta se retiró del timeline local, pero no pudo enviarse el borrado federado porque esa respuesta antigua no guardaba su identificador remoto.',
        ];
    }

    $actorUrl = nammu_fediverse_actor_url($config);
    $deleteActivity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $actorUrl . '/delete-reply/' . substr(sha1($actionId . '|' . microtime(true)), 0, 24),
        'type' => 'Delete',
        'actor' => $actorUrl,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [$recipientId],
        'object' => $noteId,
        'published' => gmdate(DATE_ATOM),
    ];
    $deliveries = [];
    $recipient = nammu_fediverse_resolve_actor($recipientId, $config);
    $recipientInbox = is_array($recipient) ? nammu_fediverse_remote_inbox_for_actor($recipient) : '';
    if ($recipientInbox !== '') {
        $deliveries[$recipientInbox] = nammu_fediverse_post_activity_response($recipientInbox, $deleteActivity, $config);
    }
    foreach (nammu_fediverse_followers_store()['followers'] as $follower) {
        $followerInbox = nammu_fediverse_remote_inbox_for_actor($follower);
        if ($followerInbox === '' || isset($deliveries[$followerInbox])) {
            continue;
        }
        $deliveries[$followerInbox] = nammu_fediverse_post_activity_response($followerInbox, $deleteActivity, $config);
    }
    $successes = 0;
    foreach ($deliveries as $delivery) {
        if (!empty($delivery['ok'])) {
            $successes++;
        }
    }
    nammu_fediverse_save_actions_store($remaining);
    return ['ok' => true, 'message' => 'Respuesta retirada del Fediverso. Entregas de borrado: ' . $successes . '.'];
}

function nammu_fediverse_accept_follow(array $payload, array $config): bool
{
    $actorId = trim((string) ($payload['actor'] ?? ''));
    $followId = trim((string) ($payload['id'] ?? ''));
    if ($actorId === '' || $followId === '') {
        return false;
    }
    $actor = nammu_fediverse_resolve_actor($actorId, $config);
    if (!is_array($actor)) {
        return false;
    }
    nammu_fediverse_followers_add_or_update($actor);
    $inboxUrl = nammu_fediverse_remote_inbox_for_actor($actor);
    if ($inboxUrl === '') {
        return false;
    }
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => nammu_fediverse_actor_url($config) . '#accept-' . sha1($followId . '|' . microtime(true)),
        'type' => 'Accept',
        'actor' => nammu_fediverse_actor_url($config),
        'object' => $payload,
    ];
    return nammu_fediverse_post_activity($inboxUrl, $activity, $config);
}

function nammu_fediverse_handle_inbox_payload(array $payload, array $config, array $headers = [], string $rawBody = ''): array
{
    $verification = nammu_fediverse_verify_inbox_request($payload, $headers, $rawBody, $config);
    nammu_fediverse_store_inbox_activity($payload, [
        'verified' => !empty($verification['verified']),
        'verification_error' => (string) ($verification['error'] ?? ''),
        'signature_key_id' => (string) ($verification['key_id'] ?? ''),
        'signed_headers' => (string) ($verification['signed_headers'] ?? ''),
    ]);
    if (empty($verification['verified'])) {
        return ['accepted' => false, 'type' => 'unauthorized', 'verified' => false, 'error' => (string) ($verification['error'] ?? '')];
    }
    $type = strtolower((string) ($payload['type'] ?? ''));
    $actorId = trim((string) ($payload['actor'] ?? ''));
    $accepted = false;
    if ($type === 'follow' && $actorId !== '') {
        $accepted = nammu_fediverse_accept_follow($payload, $config);
        return ['accepted' => $accepted, 'type' => 'follow', 'verified' => true];
    }
    if ($type === 'undo' && is_array($payload['object'] ?? null)) {
        $object = $payload['object'];
        if (strtolower((string) ($object['type'] ?? '')) === 'follow' && $actorId !== '') {
            nammu_fediverse_followers_remove($actorId);
            return ['accepted' => true, 'type' => 'undo', 'verified' => true];
        }
    }
    if ($type === 'delete') {
        $object = $payload['object'] ?? null;
        $targets = [];
        if (is_string($object)) {
            $targets[] = $object;
        } elseif (is_array($object)) {
            $targets[] = trim((string) (($object['id'] ?? '') ?: ''));
            $targets[] = trim((string) (($object['url'] ?? '') ?: ''));
            if (!empty($object['atomUri'])) {
                $targets[] = trim((string) $object['atomUri']);
            }
        }
        nammu_fediverse_remove_timeline_items($targets);
        return ['accepted' => true, 'type' => 'delete', 'verified' => true];
    }
    if ($type === 'create' && is_array($payload['object'] ?? null)) {
        $object = $payload['object'];
        if ($actorId !== '' && nammu_fediverse_is_direct_message_activity($payload, $config)) {
            $remoteActor = nammu_fediverse_resolve_actor($actorId, $config);
            nammu_fediverse_store_message([
                'id' => trim((string) (($object['id'] ?? '') ?: ($payload['id'] ?? sha1(json_encode($payload))))),
                'activity_id' => trim((string) ($payload['id'] ?? '')),
                'actor_id' => $actorId,
                'actor_name' => trim((string) (($remoteActor['name'] ?? '') ?: ($remoteActor['preferredUsername'] ?? '') ?: $actorId)),
                'actor_icon' => trim((string) ($remoteActor['icon'] ?? '')),
                'direction' => 'incoming',
                'content' => trim(strip_tags((string) ($object['content'] ?? ''))),
                'published' => trim((string) (($object['published'] ?? '') ?: ($payload['published'] ?? gmdate(DATE_ATOM)))),
                'url' => trim((string) ($object['url'] ?? '')),
                'delivery_status' => 'received',
                'verified' => true,
            ]);
            return ['accepted' => true, 'type' => 'message', 'verified' => true];
        }
    }
    return ['accepted' => true, 'type' => $type !== '' ? $type : 'unknown', 'verified' => true];
}

function nammu_fediverse_should_deliver_item_to_follower(array $item, array $follower, array $deliveryState): bool
{
    $itemId = trim((string) ($item['id'] ?? ''));
    if ($itemId === '') {
        return false;
    }
    $followedAt = trim((string) ($follower['followed_at'] ?? ''));
    $published = trim((string) ($item['published'] ?? ''));
    if ($followedAt !== '' && $published !== '' && strcmp($published, $followedAt) < 0) {
        return false;
    }
    $sent = is_array($deliveryState['sent_ids'] ?? null) ? $deliveryState['sent_ids'] : [];
    return !in_array($itemId, array_map('strval', $sent), true);
}

function nammu_fediverse_deliver_local_items(array $config): array
{
    $followers = nammu_fediverse_followers_store()['followers'];
    if (empty($followers)) {
        return ['followers' => 0, 'delivered' => 0];
    }
    $items = nammu_fediverse_local_content_items($config);
    $deliveryStore = nammu_fediverse_deliveries_store();
    $deliveryFollowers = is_array($deliveryStore['followers'] ?? null) ? $deliveryStore['followers'] : [];
    $delivered = 0;
    foreach ($followers as $follower) {
        $followerId = trim((string) ($follower['id'] ?? ''));
        $inboxUrl = nammu_fediverse_remote_inbox_for_actor($follower);
        if ($followerId === '' || $inboxUrl === '') {
            continue;
        }
        $state = is_array($deliveryFollowers[$followerId] ?? null) ? $deliveryFollowers[$followerId] : ['sent_ids' => []];
        foreach ($items as $item) {
            if (!nammu_fediverse_should_deliver_item_to_follower($item, $follower, $state)) {
                continue;
            }
            $activity = nammu_fediverse_activity_for_local_item($item, $config);
            if (nammu_fediverse_post_activity($inboxUrl, $activity, $config)) {
                $state['sent_ids'][] = (string) $item['id'];
                $state['last_success_at'] = gmdate(DATE_ATOM);
                $delivered++;
            } else {
                $state['last_error_at'] = gmdate(DATE_ATOM);
                break;
            }
        }
        $state['sent_ids'] = array_slice(array_values(array_unique(array_map('strval', is_array($state['sent_ids'] ?? null) ? $state['sent_ids'] : []))), -300);
        $deliveryFollowers[$followerId] = $state;
    }
    nammu_fediverse_save_deliveries_store($deliveryFollowers);
    return ['followers' => count($followers), 'delivered' => $delivered];
}

function nammu_fediverse_deliver_named_local_item(string $slug, string $template, array $config): array
{
    $slug = trim($slug);
    $template = strtolower(trim($template));
    if ($slug === '') {
        return ['ok' => false, 'message' => 'No se pudo identificar el contenido a reenviar.'];
    }
    if ($template === 'single') {
        $template = 'post';
    } elseif ($template === 'draft') {
        $template = 'post';
    }
    $supportedTemplates = ['post', 'podcast'];
    if (!in_array($template, $supportedTemplates, true)) {
        return ['ok' => false, 'message' => 'Solo las entradas y podcasts pueden reenviarse al Fediverso desde Editar.'];
    }
    $followers = nammu_fediverse_followers_store()['followers'];
    if (empty($followers)) {
        return ['ok' => false, 'message' => 'No hay seguidores en el Fediverso a los que enviar este contenido.'];
    }

    $targetUrl = $template === 'podcast'
        ? nammu_fediverse_base_url($config) . '/podcast/' . rawurlencode($slug)
        : nammu_fediverse_base_url($config) . '/' . rawurlencode($slug);
    $targetId = nammu_fediverse_base_url($config) . '/ap/objects/' . rawurlencode($template) . '-' . rawurlencode($slug);
    $targetObjectSuffix = '-' . rawurlencode($slug);

    $matchedItem = null;
    foreach (nammu_fediverse_local_content_items($config) as $item) {
        $itemId = trim((string) ($item['id'] ?? ''));
        $itemUrl = trim((string) ($item['url'] ?? ''));
        $itemPath = trim((string) (parse_url($itemUrl, PHP_URL_PATH) ?? ''));
        $targetPath = trim((string) (parse_url($targetUrl, PHP_URL_PATH) ?? ''));
        if (
            $itemId === $targetId
            || $itemUrl === $targetUrl
            || ($itemPath !== '' && $targetPath !== '' && rtrim($itemPath, '/') === rtrim($targetPath, '/'))
            || str_ends_with($itemId, $targetObjectSuffix)
        ) {
            $matchedItem = $item;
            break;
        }
    }
    if (!is_array($matchedItem)) {
        $contentFile = dirname(__DIR__) . '/content/' . $slug . '.md';
        if (is_file($contentFile) && is_readable($contentFile)) {
            $raw = (string) @file_get_contents($contentFile);
            $meta = nammu_fediverse_parse_front_matter($raw);
            $status = strtolower(trim((string) ($meta['Status'] ?? $meta['status'] ?? 'published')));
            $rawTemplate = strtolower(trim((string) ($meta['Template'] ?? $meta['template'] ?? 'post')));
            $visibility = strtolower(trim((string) ($meta['Visibility'] ?? $meta['visibility'] ?? 'public')));
            $normalizedTemplate = $rawTemplate === 'single' ? 'post' : $rawTemplate;
            if (
                $status === 'published'
                && $visibility !== 'private'
                && in_array($normalizedTemplate, ['post', 'podcast'], true)
                && $normalizedTemplate === $template
            ) {
                $title = trim((string) (($meta['Title'] ?? $meta['title'] ?? '') ?: $slug));
                $description = trim((string) (($meta['Description'] ?? $meta['description'] ?? '') ?: ''));
                $content = nammu_fediverse_strip_front_matter($raw);
                $matchedItem = [
                    'id' => $targetId,
                    'url' => $targetUrl,
                    'title' => $title,
                    'content' => $description !== '' ? $description : nammu_fediverse_plain_excerpt($content),
                    'summary' => $description,
                    'published' => nammu_fediverse_meta_date($meta, $contentFile),
                    'type' => 'Article',
                    'image' => nammu_fediverse_asset_url((string) (($meta['Image'] ?? $meta['image'] ?? '') ?: ''), nammu_fediverse_base_url($config)),
                ];
            }
        }
    }
    if (!is_array($matchedItem)) {
        return ['ok' => false, 'message' => 'No se encontró ese contenido publicado para enviarlo al Fediverso.'];
    }
    $deletedIds = array_values(array_filter(nammu_fediverse_deleted_store()['ids'], static function (string $deletedId) use ($matchedItem): bool {
        return $deletedId !== trim((string) ($matchedItem['id'] ?? ''));
    }));
    nammu_fediverse_save_deleted_store($deletedIds);

    $deliveryStore = nammu_fediverse_deliveries_store();
    $deliveryFollowers = is_array($deliveryStore['followers'] ?? null) ? $deliveryStore['followers'] : [];
    $resendPublished = gmdate(DATE_ATOM);
    $resendId = nammu_fediverse_base_url($config) . '/ap/objects/resend-' . substr(sha1($slug . '|' . $template . '|' . microtime(true)), 0, 24);
    $resendItem = $matchedItem;
    $resendItem['id'] = $resendId;
    $resendItem['published'] = $resendPublished;
    $activity = nammu_fediverse_activity_for_local_item($resendItem, $config);
    $delivered = 0;
    foreach ($followers as $follower) {
        $followerId = trim((string) ($follower['id'] ?? ''));
        $inboxUrl = nammu_fediverse_remote_inbox_for_actor($follower);
        if ($followerId === '' || $inboxUrl === '') {
            continue;
        }
        $state = is_array($deliveryFollowers[$followerId] ?? null) ? $deliveryFollowers[$followerId] : ['sent_ids' => []];
        if (nammu_fediverse_post_activity($inboxUrl, $activity, $config)) {
            $state['sent_ids'][] = (string) ($matchedItem['id'] ?? '');
            $state['last_success_at'] = gmdate(DATE_ATOM);
            $delivered++;
        } else {
            $state['last_error_at'] = gmdate(DATE_ATOM);
        }
        $state['sent_ids'] = array_slice(array_values(array_unique(array_map('strval', is_array($state['sent_ids'] ?? null) ? $state['sent_ids'] : []))), -300);
        $deliveryFollowers[$followerId] = $state;
    }
    nammu_fediverse_save_deliveries_store($deliveryFollowers);
    nammu_fediverse_record_action('resend', '', (string) ($matchedItem['url'] ?? ''), [
        'resend_object_id' => $resendId,
        'activity_id' => (string) ($activity['id'] ?? ''),
        'title' => trim((string) ($matchedItem['title'] ?? '')),
        'content' => trim((string) ($matchedItem['content'] ?? '')),
        'summary' => trim((string) ($matchedItem['summary'] ?? '')),
        'image' => trim((string) ($matchedItem['image'] ?? '')),
        'object_type' => trim((string) ($matchedItem['type'] ?? 'Article')),
        'published' => $resendPublished,
    ]);

    if ($delivered === 0) {
        return ['ok' => false, 'message' => 'No se pudo reenviar el contenido al Fediverso.'];
    }
    return [
        'ok' => true,
        'message' => 'El contenido se reenvió al Fediverso. Entregas: ' . $delivered . '.',
    ];
}

function nammu_fediverse_delete_local_item(string $itemId, array $config): array
{
    $itemId = trim($itemId);
    if ($itemId === '') {
        return ['ok' => false, 'message' => 'No se recibió la publicación a borrar del Fediverso.'];
    }
    $matchedItem = null;
    foreach (nammu_fediverse_local_content_items($config) as $item) {
        if (trim((string) ($item['id'] ?? '')) === $itemId) {
            $matchedItem = $item;
            break;
        }
    }
    if (!is_array($matchedItem)) {
        return ['ok' => false, 'message' => 'No se encontró esa publicación local en el Fediverso.'];
    }
    $deletedIds = nammu_fediverse_deleted_store()['ids'];
    $deletedIds[] = $itemId;
    nammu_fediverse_save_deleted_store($deletedIds);

    $actorUrl = nammu_fediverse_actor_url($config);
    $deleteActivity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $actorUrl . '/delete/' . substr(sha1($itemId . '|' . microtime(true)), 0, 24),
        'type' => 'Delete',
        'actor' => $actorUrl,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'object' => $itemId,
        'published' => gmdate(DATE_ATOM),
    ];
    $followers = nammu_fediverse_followers_store()['followers'];
    $delivered = 0;
    foreach ($followers as $follower) {
        $inboxUrl = nammu_fediverse_remote_inbox_for_actor($follower);
        if ($inboxUrl === '') {
            continue;
        }
        if (nammu_fediverse_post_activity($inboxUrl, $deleteActivity, $config)) {
            $delivered++;
        }
    }
    return ['ok' => true, 'message' => 'Publicación retirada del Fediverso. Entregas de borrado: ' . $delivered . '.'];
}
