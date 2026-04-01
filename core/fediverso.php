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

function nammu_fediverse_blocked_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-blocked.json';
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

function nammu_fediverse_legacy_actuality_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-legacy-actualidad.json';
}

function nammu_fediverse_legacy_actuality_aliases_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-legacy-actualidad-aliases.json';
}

function nammu_fediverse_delete_queue_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-delete-queue.json';
}

function nammu_fediverse_undo_announce_queue_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-undo-announce-queue.json';
}

function nammu_fediverse_announce_queue_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-announce-queue.json';
}

function nammu_fediverse_shared_queue_dir(array $config): string
{
    $multi = nammu_fediverse_multi_instance_config($config);
    if (($multi['enabled'] ?? 'off') !== 'on') {
        return '';
    }
    $path = trim((string) ($multi['shared_queue_dir'] ?? ''));
    if ($path === '') {
        return '';
    }
    return rtrim($path, '/');
}

function nammu_fediverse_effective_config(?array $config = null): array
{
    if (is_array($config)) {
        return $config;
    }
    if (function_exists('nammu_load_config')) {
        $loaded = nammu_load_config();
        return is_array($loaded) ? $loaded : [];
    }
    return [];
}

function nammu_fediverse_instance_queue_suffix(array $config): string
{
    $base = nammu_fediverse_base_url($config);
    if ($base === '') {
        $base = trim((string) ($config['site_name'] ?? 'nammu'));
    }
    return sha1($base);
}

function nammu_fediverse_queue_file_for(array $config, string $basename, string $fallback): string
{
    $sharedDir = nammu_fediverse_shared_queue_dir($config);
    if ($sharedDir === '') {
        return $fallback;
    }
    return $sharedDir . '/' . $basename . '-' . nammu_fediverse_instance_queue_suffix($config) . '.json';
}

function nammu_fediverse_threads_cache_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-threads-cache.json';
}

function nammu_fediverse_hidden_replies_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-hidden-replies.json';
}

function nammu_fediverse_fragments_cache_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-fragments-cache.json';
}

function nammu_fediverse_home_snapshot_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-home.json';
}

function nammu_fediverse_messages_snapshot_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-threads.json';
}

function nammu_fediverse_notifications_snapshot_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-notifications.json';
}

function nammu_fediverse_link_cards_file(): string
{
    return dirname(__DIR__) . '/config/fediverso-link-cards.json';
}

function nammu_fediverse_keys_file(): string
{
    return dirname(__DIR__) . '/config/activitypub-keys.json';
}

function nammu_fediverse_multi_instance_config(array $config): array
{
    $settings = is_array($config['multi_instance'] ?? null) ? $config['multi_instance'] : [];
    $normalized = [
        'enabled' => (($settings['enabled'] ?? 'off') === 'on') ? 'on' : 'off',
        'cluster' => trim((string) ($settings['cluster'] ?? '')),
        'shared_cache_dir' => trim((string) ($settings['shared_cache_dir'] ?? '')),
        'shared_queue_dir' => trim((string) ($settings['shared_queue_dir'] ?? '')),
        'scheduler_mode' => trim((string) ($settings['scheduler_mode'] ?? 'standalone')),
    ];
    if (!in_array($normalized['scheduler_mode'], ['standalone', 'central'], true)) {
        $normalized['scheduler_mode'] = 'standalone';
    }
    return $normalized;
}

function nammu_fediverse_shared_cache_dir(array $config): string
{
    $multi = nammu_fediverse_multi_instance_config($config);
    if (($multi['enabled'] ?? 'off') !== 'on') {
        return '';
    }
    $path = trim((string) ($multi['shared_cache_dir'] ?? ''));
    if ($path === '') {
        return '';
    }
    return rtrim($path, '/');
}

function nammu_fediverse_shared_cache_file(array $config, string $bucket, string $key): string
{
    $base = nammu_fediverse_shared_cache_dir($config);
    if ($base === '') {
        return '';
    }
    $bucket = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower(trim($bucket))) ?? '';
    if ($bucket === '') {
        $bucket = 'misc';
    }
    $hash = sha1($key);
    return $base . '/fediverse/' . $bucket . '/' . $hash . '.json';
}

function nammu_fediverse_shared_cache_read(array $config, string $bucket, string $key, int $ttl): ?array
{
    $file = nammu_fediverse_shared_cache_file($config, $bucket, $key);
    if ($file === '' || !is_file($file)) {
        return null;
    }
    try {
        $payload = nammu_fediverse_load_json_store($file, []);
    } catch (Throwable $e) {
        return null;
    }
    if (!is_array($payload) || empty($payload)) {
        return null;
    }
    $fetchedAt = (int) ($payload['fetched_at'] ?? 0);
    if ($fetchedAt <= 0 || (time() - $fetchedAt) > max(1, $ttl)) {
        return null;
    }
    return $payload;
}

function nammu_fediverse_shared_cache_write(array $config, string $bucket, string $key, array $payload): void
{
    $file = nammu_fediverse_shared_cache_file($config, $bucket, $key);
    if ($file === '') {
        return;
    }
    try {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            nammu_ensure_directory($dir);
        }
        nammu_fediverse_save_json_store($file, $payload);
    } catch (Throwable $e) {
        return;
    }
}

function nammu_fediverse_should_shared_cache_remote_url(string $url, ?array $config = null): bool
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return false;
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '') {
        return false;
    }
    if (is_array($config)) {
        $localHost = nammu_fediverse_site_host($config);
        if ($localHost !== '' && $host === $localHost) {
            return false;
        }
        return nammu_fediverse_shared_cache_dir($config) !== '';
    }
    return true;
}

function nammu_fediverse_load_json_store(string $file, array $default = []): array
{
    if (!isset($GLOBALS['nammu_fediverse_json_store_cache']) || !is_array($GLOBALS['nammu_fediverse_json_store_cache'])) {
        $GLOBALS['nammu_fediverse_json_store_cache'] = [];
    }
    $cache = &$GLOBALS['nammu_fediverse_json_store_cache'];
    if (array_key_exists($file, $cache)) {
        $cached = $cache[$file];
        return is_array($cached) ? $cached : $default;
    }
    if (!is_file($file)) {
        $cache[$file] = null;
        return $default;
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    $cache[$file] = is_array($decoded) ? $decoded : null;
    return is_array($cache[$file]) ? $cache[$file] : $default;
}

function nammu_fediverse_save_json_store(string $file, array $payload): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        nammu_ensure_directory($dir);
    }
    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    if (!isset($GLOBALS['nammu_fediverse_json_store_cache']) || !is_array($GLOBALS['nammu_fediverse_json_store_cache'])) {
        $GLOBALS['nammu_fediverse_json_store_cache'] = [];
    }
    $GLOBALS['nammu_fediverse_json_store_cache'][$file] = $payload;
}

function nammu_fediverse_link_cards_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_link_cards_file(), ['items' => []]);
    $store['items'] = is_array($store['items'] ?? null) ? $store['items'] : [];
    return $store;
}

function nammu_fediverse_save_link_cards_store(array $items): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_link_cards_file(), ['items' => $items]);
}

function nammu_fediverse_fragments_cache_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_fragments_cache_file(), ['items' => []]);
    $store['items'] = is_array($store['items'] ?? null) ? $store['items'] : [];
    return $store;
}

function nammu_fediverse_save_fragments_cache_store(array $items): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_fragments_cache_file(), ['items' => $items]);
}

function nammu_fediverse_home_snapshot_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_home_snapshot_file(), ['generated_at' => '', 'data' => []]);
    $store['data'] = is_array($store['data'] ?? null) ? $store['data'] : [];
    return $store;
}

function nammu_fediverse_messages_snapshot_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_messages_snapshot_file(), ['generated_at' => '', 'data' => []]);
    $store['data'] = is_array($store['data'] ?? null) ? $store['data'] : [];
    return $store;
}

function nammu_fediverse_notifications_snapshot_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_notifications_snapshot_file(), ['generated_at' => '', 'data' => []]);
    $store['data'] = is_array($store['data'] ?? null) ? $store['data'] : [];
    return $store;
}

function nammu_fediverse_save_home_snapshot_store(array $data): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_home_snapshot_file(), [
        'generated_at' => gmdate(DATE_ATOM),
        'data' => $data,
    ]);
}

function nammu_fediverse_save_messages_snapshot_store(array $data): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_messages_snapshot_file(), [
        'generated_at' => gmdate(DATE_ATOM),
        'data' => $data,
    ]);
}

function nammu_fediverse_save_notifications_snapshot_store(array $data): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_notifications_snapshot_file(), [
        'generated_at' => gmdate(DATE_ATOM),
        'data' => $data,
    ]);
}

function nammu_fediverse_remove_local_item_from_home_snapshot(string $itemId): void
{
    $itemId = trim($itemId);
    if ($itemId === '') {
        return;
    }
    $store = nammu_fediverse_home_snapshot_store();
    $data = is_array($store['data'] ?? null) ? $store['data'] : [];

    $localItems = is_array($data['local_items'] ?? null) ? $data['local_items'] : [];
    $data['local_items'] = array_values(array_filter($localItems, static function ($item) use ($itemId): bool {
        return !is_array($item) || trim((string) ($item['id'] ?? '')) !== $itemId;
    }));

    foreach (['local_reaction_summary', 'local_reaction_details', 'incoming_replies', 'thread_payloads'] as $key) {
        if (isset($data[$key][$itemId])) {
            unset($data[$key][$itemId]);
        }
    }

    nammu_fediverse_save_home_snapshot_store($data);
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

function nammu_fediverse_profile_page_url(array $config): string
{
    $baseUrl = rtrim(nammu_fediverse_base_url($config), '/');
    if (function_exists('nammu_fediverse_profile_alias_path')) {
        $path = (string) nammu_fediverse_profile_alias_path($config, $baseUrl);
        if ($path !== '') {
            return $baseUrl . $path;
        }
    }
    return $baseUrl . '/actualidad.php';
}

function nammu_fediverse_key_url(array $config): string
{
    return nammu_fediverse_actor_url($config) . '#main-key';
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

function nammu_fediverse_reply_note_url(string $hash, array $config): string
{
    return rtrim(nammu_fediverse_base_url($config), '/') . '/ap/notes/' . $hash;
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

function nammu_fediverse_signed_fetch(string $url, array $config, string $method = 'GET', string $body = '', ?int $timeoutOverride = null): array
{
    $method = strtoupper($method);
    if (function_exists('nammu_multi_instance_remote_host_before_request')) {
        nammu_multi_instance_remote_host_before_request($url, $config);
    }
    $headers = nammu_fediverse_signature_header($method, $url, $config, $body) ?? [
        'User-Agent: Nammu Fediverso',
        'Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/json;q=0.9',
    ];
    $timeout = $timeoutOverride ?? ($method === 'POST' ? 6 : 15);
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => $timeout,
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
    $result = [
        'status' => $status,
        'headers' => $rawHeaders,
        'body' => is_string($responseBody) ? $responseBody : '',
    ];
    if (function_exists('nammu_multi_instance_remote_host_after_request')) {
        nammu_multi_instance_remote_host_after_request($url, $config, (int) ($result['status'] ?? 0));
    }
    return $result;
}

function nammu_fediverse_signed_fetch_json(string $url, array $config, string $method = 'GET', string $body = ''): ?array
{
    $url = trim($url);
    if (
        strtoupper($method) === 'GET'
        && $body === ''
        && nammu_fediverse_should_shared_cache_remote_url($url, $config)
    ) {
        $cached = nammu_fediverse_shared_cache_read($config, 'activitypub-json', $url, 300);
        $payload = is_array($cached['payload'] ?? null) ? $cached['payload'] : null;
        if (is_array($payload)) {
            return $payload;
        }
    }
    $response = nammu_fediverse_signed_fetch($url, $config, $method, $body);
    if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 400) {
        if (strtoupper($method) === 'GET' && $body === '') {
            return nammu_fediverse_fetch_json($url);
        }
        return null;
    }
    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    if (
        is_array($decoded)
        && strtoupper($method) === 'GET'
        && $body === ''
        && nammu_fediverse_should_shared_cache_remote_url($url, $config)
    ) {
        nammu_fediverse_shared_cache_write($config, 'activitypub-json', $url, [
            'fetched_at' => time(),
            'payload' => $decoded,
        ]);
    }
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
    $url = trim($url);
    $response = nammu_fediverse_fetch($url, $accept);
    if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 400) {
        return null;
    }
    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    return is_array($decoded) ? $decoded : null;
}

function nammu_fediverse_fetch_actor_document_status(string $url, array $config): array
{
    $url = trim($url);
    if ($url === '') {
        return ['status' => 0, 'body' => null];
    }
    if (nammu_fediverse_should_shared_cache_remote_url($url, $config)) {
        $cached = nammu_fediverse_shared_cache_read($config, 'actor-status', $url, 300);
        $cachedStatus = (int) ($cached['status'] ?? 0);
        $cachedBody = is_array($cached['body'] ?? null) ? $cached['body'] : null;
        if ($cachedStatus > 0 && is_array($cachedBody)) {
            return ['status' => $cachedStatus, 'body' => $cachedBody];
        }
    }
    $response = nammu_fediverse_signed_fetch($url, $config);
    $status = (int) ($response['status'] ?? 0);
    $body = json_decode((string) ($response['body'] ?? ''), true);
    if ($status >= 200 && $status < 400 && is_array($body)) {
        if (nammu_fediverse_should_shared_cache_remote_url($url, $config)) {
            nammu_fediverse_shared_cache_write($config, 'actor-status', $url, [
                'fetched_at' => time(),
                'status' => $status,
                'body' => $body,
            ]);
        }
        return ['status' => $status, 'body' => $body];
    }
    $fallback = nammu_fediverse_fetch($url);
    $fallbackStatus = (int) ($fallback['status'] ?? 0);
    $fallbackBody = json_decode((string) ($fallback['body'] ?? ''), true);
    $result = [
        'status' => $fallbackStatus > 0 ? $fallbackStatus : $status,
        'body' => is_array($fallbackBody) ? $fallbackBody : (is_array($body) ? $body : null),
    ];
    if (
        nammu_fediverse_should_shared_cache_remote_url($url, $config)
        && (int) ($result['status'] ?? 0) >= 200
        && (int) ($result['status'] ?? 0) < 400
        && is_array($result['body'] ?? null)
    ) {
        nammu_fediverse_shared_cache_write($config, 'actor-status', $url, [
            'fetched_at' => time(),
            'status' => (int) $result['status'],
            'body' => $result['body'],
        ]);
    }
    return $result;
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
            'url' => nammu_fediverse_extract_url($actor['url'] ?? ($actor['id'] ?? $trimmed)),
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
    $webfingerUrl = 'https://' . $domain . '/.well-known/webfinger?resource=' . $resource;
    $webfinger = null;
    if (is_array($config) && nammu_fediverse_should_shared_cache_remote_url($webfingerUrl, $config)) {
        $cached = nammu_fediverse_shared_cache_read($config, 'webfinger', $webfingerUrl, 21600);
        $webfinger = is_array($cached['payload'] ?? null) ? $cached['payload'] : null;
    }
    if (!is_array($webfinger)) {
        $webfinger = nammu_fediverse_fetch_json(
            $webfingerUrl,
            'application/jrd+json, application/json;q=0.9'
        );
        if (is_array($config) && is_array($webfinger) && nammu_fediverse_should_shared_cache_remote_url($webfingerUrl, $config)) {
            nammu_fediverse_shared_cache_write($config, 'webfinger', $webfingerUrl, [
                'fetched_at' => time(),
                'payload' => $webfinger,
            ]);
        }
    }
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

function nammu_fediverse_blocked_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_blocked_file(), ['actors' => []]);
    $actors = is_array($store['actors'] ?? null) ? $store['actors'] : [];
    return ['actors' => array_values($actors)];
}

function nammu_fediverse_save_followers_store(array $followers): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_followers_file(), ['followers' => array_values($followers)]);
}

function nammu_fediverse_save_blocked_store(array $actors): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_blocked_file(), ['actors' => array_values($actors)]);
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
    $store = nammu_fediverse_load_json_store(nammu_fediverse_deleted_file(), ['ids' => [], 'items' => []]);
    $normalized = [];
    foreach ((array) ($store['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = trim((string) ($item['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $normalized[$id] = [
            'id' => $id,
            'deleted_at' => trim((string) ($item['deleted_at'] ?? '')),
        ];
    }
    foreach ((array) ($store['ids'] ?? []) as $id) {
        $id = trim((string) $id);
        if ($id === '') {
            continue;
        }
        if (!isset($normalized[$id])) {
            $normalized[$id] = [
                'id' => $id,
                'deleted_at' => '',
            ];
        }
    }
    return [
        'ids' => array_keys($normalized),
        'items' => array_values($normalized),
    ];
}

function nammu_fediverse_legacy_actuality_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_legacy_actuality_file(), ['items' => []]);
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemId = trim((string) ($item['id'] ?? ''));
        if ($itemId === '') {
            continue;
        }
        $normalized[] = [
            'id' => $itemId,
            'published' => trim((string) ($item['published'] ?? '')),
            'source' => trim((string) ($item['source'] ?? '')),
        ];
    }
    return ['items' => $normalized];
}

function nammu_fediverse_delete_queue_store(?array $config = null): array
{
    $resolvedConfig = nammu_fediverse_effective_config($config);
    $file = nammu_fediverse_queue_file_for($resolvedConfig, 'fediverso-delete-queue', nammu_fediverse_delete_queue_file());
    $store = nammu_fediverse_load_json_store($file, ['items' => []]);
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemId = trim((string) ($item['item_id'] ?? ''));
        if ($itemId === '') {
            continue;
        }
        $normalized[] = [
            'item_id' => $itemId,
            'created_at' => trim((string) ($item['created_at'] ?? '')) ?: gmdate(DATE_ATOM),
            'attempts' => (int) ($item['attempts'] ?? 0),
        ];
    }
    return ['items' => $normalized];
}

function nammu_fediverse_undo_announce_queue_store(?array $config = null): array
{
    $resolvedConfig = nammu_fediverse_effective_config($config);
    $file = nammu_fediverse_queue_file_for($resolvedConfig, 'fediverso-undo-announce-queue', nammu_fediverse_undo_announce_queue_file());
    $store = nammu_fediverse_load_json_store($file, ['items' => []]);
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $activityId = trim((string) ($item['activity_id'] ?? ''));
        $recipientId = trim((string) ($item['recipient_id'] ?? ''));
        $objectUrl = trim((string) ($item['object_url'] ?? ''));
        if ($recipientId === '' || $objectUrl === '') {
            continue;
        }
        $normalized[] = [
            'activity_id' => $activityId,
            'recipient_id' => $recipientId,
            'object_url' => $objectUrl,
            'created_at' => trim((string) ($item['created_at'] ?? '')) ?: gmdate(DATE_ATOM),
            'attempts' => (int) ($item['attempts'] ?? 0),
        ];
    }
    return ['items' => $normalized];
}

function nammu_fediverse_announce_queue_store(?array $config = null): array
{
    $resolvedConfig = nammu_fediverse_effective_config($config);
    $file = nammu_fediverse_queue_file_for($resolvedConfig, 'fediverso-announce-queue', nammu_fediverse_announce_queue_file());
    $store = nammu_fediverse_load_json_store($file, ['items' => []]);
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $activityId = trim((string) ($item['activity_id'] ?? ''));
        $recipientId = trim((string) ($item['recipient_id'] ?? ''));
        $objectUrl = trim((string) ($item['object_url'] ?? ''));
        if ($activityId === '' || $recipientId === '' || $objectUrl === '') {
            continue;
        }
        $normalized[] = [
            'activity_id' => $activityId,
            'recipient_id' => $recipientId,
            'object_url' => $objectUrl,
            'published' => trim((string) ($item['published'] ?? '')) ?: gmdate(DATE_ATOM),
            'created_at' => trim((string) ($item['created_at'] ?? '')) ?: gmdate(DATE_ATOM),
            'attempts' => (int) ($item['attempts'] ?? 0),
        ];
    }
    return ['items' => $normalized];
}

function nammu_fediverse_threads_cache_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_threads_cache_file(), ['items' => []]);
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    return ['items' => $items];
}

function nammu_fediverse_hidden_replies_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_hidden_replies_file(), ['items' => []]);
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    return ['items' => array_values($items)];
}

function nammu_fediverse_save_threads_cache_store(array $items): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_threads_cache_file(), ['items' => $items]);
}

function nammu_fediverse_clear_threads_cache(): void
{
    nammu_fediverse_save_threads_cache_store([]);
}

function nammu_fediverse_save_hidden_replies_store(array $items): void
{
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['hidden_at'] ?? ''), (string) ($a['hidden_at'] ?? ''));
    });
    nammu_fediverse_save_json_store(nammu_fediverse_hidden_replies_file(), ['items' => array_slice(array_values($items), 0, 1000)]);
}

function nammu_fediverse_save_deleted_store(array $ids): void
{
    $normalized = [];
    foreach ($ids as $entry) {
        if (is_array($entry)) {
            $id = trim((string) ($entry['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $normalized[$id] = [
                'id' => $id,
                'deleted_at' => trim((string) ($entry['deleted_at'] ?? '')),
            ];
            continue;
        }
        $id = trim((string) $entry);
        if ($id === '') {
            continue;
        }
        if (!isset($normalized[$id])) {
            $normalized[$id] = [
                'id' => $id,
                'deleted_at' => '',
            ];
        }
    }
    nammu_fediverse_save_json_store(nammu_fediverse_deleted_file(), [
        'ids' => array_keys($normalized),
        'items' => array_values($normalized),
    ]);
}

function nammu_fediverse_deleted_entry(string $itemId): ?array
{
    $itemId = trim($itemId);
    if ($itemId === '') {
        return null;
    }
    foreach ((array) (nammu_fediverse_deleted_store()['items'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (trim((string) ($entry['id'] ?? '')) === $itemId) {
            return $entry;
        }
    }
    return null;
}

function nammu_fediverse_save_legacy_actuality_store(array $items): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_legacy_actuality_file(), ['items' => array_values($items)]);
}

function nammu_fediverse_legacy_actuality_aliases_store(): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_legacy_actuality_aliases_file(), ['map' => []]);
    $store['map'] = is_array($store['map'] ?? null) ? $store['map'] : [];
    return $store;
}

function nammu_fediverse_save_legacy_actuality_aliases_store(array $map): void
{
    nammu_fediverse_save_json_store(nammu_fediverse_legacy_actuality_aliases_file(), ['updated_at' => time(), 'map' => $map]);
}

function nammu_fediverse_alias_score_tokens(string $text): array
{
    $text = trim(mb_strtolower(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8'));
    if ($text === '') {
        return [];
    }
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if (is_string($ascii) && $ascii !== '') {
        $text = $ascii;
    }
    $text = preg_replace('/[^a-z0-9]+/i', ' ', $text) ?? '';
    $parts = preg_split('/\s+/', trim($text)) ?: [];
    $stop = [
        'para' => true, 'como' => true, 'pero' => true, 'esta' => true, 'este' => true, 'estos' => true,
        'estas' => true, 'desde' => true, 'sobre' => true, 'hasta' => true, 'donde' => true, 'porque' => true,
        'entre' => true, 'cuando' => true, 'puede' => true, 'pueden' => true, 'mucho' => true, 'muchos' => true,
        'muchas' => true, 'parte' => true, 'tiene' => true, 'tienen' => true, 'haber' => true, 'hacia' => true,
        'ellos' => true, 'ellas' => true, 'nuestro' => true, 'nuestra' => true, 'maximalismo' => true,
        'maximalistas' => true, 'estudio' => true,
    ];
    $tokens = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || strlen($part) < 5 || isset($stop[$part])) {
            continue;
        }
        $tokens[$part] = true;
    }
    return array_keys($tokens);
}

function nammu_fediverse_refresh_legacy_actuality_aliases(array $config): array
{
    $baseUrl = rtrim(nammu_fediverse_base_url($config), '/');
    if ($baseUrl === '') {
        return [];
    }

    $actualityStore = nammu_fediverse_load_json_store(dirname(__DIR__) . '/config/actualidad-items.json', ['items' => []]);
    $currentNews = [];
    foreach ((array) ($actualityStore['items'] ?? []) as $item) {
        if (!is_array($item) || trim((string) ($item['source_kind'] ?? '')) !== 'news') {
            continue;
        }
        $shortId = trim((string) ($item['id'] ?? ''));
        if ($shortId === '') {
            continue;
        }
        $objectId = $baseUrl . '/ap/objects/actualidad-' . $shortId;
        $currentNews[$objectId] = [
            'object_id' => $objectId,
            'published_day' => gmdate('Y-m-d', (int) ($item['timestamp'] ?? time())),
            'tokens' => nammu_fediverse_alias_score_tokens(trim((string) ($item['title'] ?? '')) . "\n" . trim((string) (($item['raw_text'] ?? '') ?: ($item['description'] ?? '')))),
        ];
    }

    $timeline = nammu_fediverse_timeline_store();
    $legacyTexts = [];
    foreach ((array) ($timeline['items'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $targetUrl = trim((string) ($entry['target_url'] ?? ''));
        if (!str_starts_with($targetUrl, $baseUrl . '/ap/objects/actualidad-')) {
            continue;
        }
        $content = trim((string) (($entry['content'] ?? '') ?: ($entry['content_html'] ?? '')));
        if ($content === '') {
            continue;
        }
        $legacyTexts[$targetUrl][] = $content;
    }

    $legacyStore = nammu_fediverse_legacy_actuality_store();
    $map = [];
    foreach ((array) ($legacyStore['items'] ?? []) as $legacyItem) {
        if (!is_array($legacyItem)) {
            continue;
        }
        $legacyId = trim((string) ($legacyItem['id'] ?? ''));
        if ($legacyId === '' || isset($currentNews[$legacyId])) {
            continue;
        }
        $texts = $legacyTexts[$legacyId] ?? [];
        if ($texts === []) {
            continue;
        }
        $legacyTokens = nammu_fediverse_alias_score_tokens(implode("\n", $texts));
        if ($legacyTokens === []) {
            continue;
        }
        $legacyDay = substr(trim((string) ($legacyItem['published'] ?? '')), 0, 10);
        $bestId = '';
        $bestScore = 0;
        $secondScore = 0;
        foreach ($currentNews as $currentId => $candidate) {
            $candidateDay = trim((string) ($candidate['published_day'] ?? ''));
            if ($legacyDay !== '' && $candidateDay !== '' && $legacyDay !== $candidateDay) {
                continue;
            }
            $score = count(array_intersect($legacyTokens, (array) ($candidate['tokens'] ?? [])));
            if ($score > $bestScore) {
                $secondScore = $bestScore;
                $bestScore = $score;
                $bestId = $currentId;
            } elseif ($score > $secondScore) {
                $secondScore = $score;
            }
        }
        if ($bestId !== '' && $bestScore >= 2 && $bestScore > $secondScore) {
            $map[$legacyId] = $bestId;
        }
    }

    nammu_fediverse_save_legacy_actuality_aliases_store($map);
    return $map;
}

function nammu_fediverse_save_delete_queue_store(array $items, ?array $config = null): void
{
    $resolvedConfig = nammu_fediverse_effective_config($config);
    $file = nammu_fediverse_queue_file_for($resolvedConfig, 'fediverso-delete-queue', nammu_fediverse_delete_queue_file());
    nammu_fediverse_save_json_store($file, ['items' => array_values($items)]);
}

function nammu_fediverse_save_undo_announce_queue_store(array $items, ?array $config = null): void
{
    $resolvedConfig = nammu_fediverse_effective_config($config);
    $file = nammu_fediverse_queue_file_for($resolvedConfig, 'fediverso-undo-announce-queue', nammu_fediverse_undo_announce_queue_file());
    nammu_fediverse_save_json_store($file, ['items' => array_values($items)]);
}

function nammu_fediverse_save_announce_queue_store(array $items, ?array $config = null): void
{
    $resolvedConfig = nammu_fediverse_effective_config($config);
    $file = nammu_fediverse_queue_file_for($resolvedConfig, 'fediverso-announce-queue', nammu_fediverse_announce_queue_file());
    nammu_fediverse_save_json_store($file, ['items' => array_values($items)]);
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

function nammu_fediverse_remove_action_by_id(string $actionId): bool
{
    $actionId = trim($actionId);
    if ($actionId === '') {
        return false;
    }
    $items = nammu_fediverse_actions_store()['items'];
    $before = count($items);
    $items = array_values(array_filter($items, static function (array $item) use ($actionId): bool {
        return trim((string) ($item['id'] ?? '')) !== $actionId;
    }));
    if (count($items) === $before) {
        return false;
    }
    nammu_fediverse_save_actions_store($items);
    return true;
}

function nammu_fediverse_item_action_candidates(array $item): array
{
    return array_values(array_unique(array_filter([
        trim((string) ($item['object_id'] ?? '')),
        trim((string) ($item['url'] ?? '')),
        trim((string) ($item['id'] ?? '')),
    ], static fn(string $value): bool => $value !== '')));
}

function nammu_fediverse_latest_action_for_item(array $item, string $type): ?array
{
    $type = strtolower(trim($type));
    if ($type === '') {
        return null;
    }
    $candidates = nammu_fediverse_item_action_candidates($item);
    if (empty($candidates)) {
        return null;
    }
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        if (strtolower(trim((string) ($action['type'] ?? ''))) !== $type) {
            continue;
        }
        if (in_array(trim((string) ($action['object_url'] ?? '')), $candidates, true)) {
            return $action;
        }
    }
    return null;
}

function nammu_fediverse_find_related_boost_share_action(array $boostAction): ?array
{
    $objectUrl = trim((string) ($boostAction['object_url'] ?? ''));
    if ($objectUrl === '') {
        return null;
    }
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        if (strtolower(trim((string) ($action['type'] ?? ''))) !== 'share') {
            continue;
        }
        if (strtolower(trim((string) ($action['via'] ?? ''))) !== 'boost') {
            continue;
        }
        if (trim((string) ($action['object_url'] ?? '')) !== $objectUrl) {
            continue;
        }
        return $action;
    }
    return null;
}

function nammu_fediverse_find_manual_boost_item(array $boostAction): ?array
{
    $manualId = trim((string) ($boostAction['manual_item_id'] ?? ''));
    if ($manualId !== '' && function_exists('nammu_actuality_get_manual_item')) {
        $manualItem = nammu_actuality_get_manual_item($manualId);
        if (is_array($manualItem)) {
            return $manualItem;
        }
    }
    $shareAction = nammu_fediverse_find_related_boost_share_action($boostAction);
    if (is_array($shareAction)) {
        $shareManualId = trim((string) ($shareAction['manual_item_id'] ?? ''));
        if ($shareManualId !== '' && function_exists('nammu_actuality_get_manual_item')) {
            $manualItem = nammu_actuality_get_manual_item($shareManualId);
            if (is_array($manualItem)) {
                return $manualItem;
            }
        }
    }
    if (!function_exists('nammu_actuality_list_manual_items')) {
        return null;
    }
    $objectUrl = trim((string) ($boostAction['object_url'] ?? ''));
    $publicUrl = trim((string) ($boostAction['public_url'] ?? ''));
    foreach (nammu_actuality_list_manual_items() as $manualItem) {
        if (strtolower(trim((string) ($manualItem['via'] ?? ''))) !== 'boost') {
            continue;
        }
        $links = array_map('strval', is_array($manualItem['links'] ?? null) ? $manualItem['links'] : []);
        if (($objectUrl !== '' && in_array($objectUrl, $links, true)) || ($publicUrl !== '' && in_array($publicUrl, $links, true))) {
            return $manualItem;
        }
    }
    return null;
}

function nammu_fediverse_hidden_reply_keys(array $reply): array
{
    $keys = [];
    foreach (['id', 'url', 'note_id'] as $field) {
        $value = trim((string) ($reply[$field] ?? ''));
        if ($value !== '') {
            $keys[] = 'id:' . $value;
        }
    }
    $fallback = strtolower(trim((string) ($reply['actor_id'] ?? ''))) . '|' .
        trim((string) ($reply['published'] ?? '')) . '|' .
        trim((string) (($reply['reply_text'] ?? '') ?: ($reply['content'] ?? '')));
    if ($fallback !== '||') {
        $keys[] = 'fallback:' . $fallback;
    }
    return array_values(array_unique(array_filter($keys)));
}

function nammu_fediverse_hidden_reply_lookup(): array
{
    $lookup = [];
    foreach ((array) (nammu_fediverse_hidden_replies_store()['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach ((array) ($item['keys'] ?? []) as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $lookup[$key] = true;
            }
        }
    }
    return $lookup;
}

function nammu_fediverse_is_hidden_reply(array $reply, ?array $lookup = null): bool
{
    $lookup = is_array($lookup) ? $lookup : nammu_fediverse_hidden_reply_lookup();
    foreach (nammu_fediverse_hidden_reply_keys($reply) as $key) {
        if (isset($lookup[$key])) {
            return true;
        }
    }
    return false;
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
                'boosted' => false,
                'boost_count' => 0,
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

function nammu_fediverse_remote_boost_details(array $config): array
{
    $items = nammu_fediverse_timeline_store()['items'];
    $details = [];
    foreach ($items as $item) {
        if (strtolower(trim((string) ($item['type'] ?? ''))) !== 'announce') {
            continue;
        }
        $actorId = trim((string) ($item['actor_id'] ?? ''));
        $actorEntry = [
            'id' => $actorId,
            'name' => trim((string) (($item['actor_name'] ?? '') ?: $actorId)),
            'icon' => trim((string) ($item['actor_icon'] ?? '')),
            'url' => trim((string) (($item['actor_url'] ?? '') ?: $actorId)),
        ];
        if ($actorId !== '' && ($actorEntry['name'] === $actorId || $actorEntry['icon'] === '' || $actorEntry['url'] === '')) {
            $actor = nammu_fediverse_resolve_actor($actorId, $config);
            if (is_array($actor)) {
                $actorEntry['name'] = trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? '') ?: $actorId));
                $actorEntry['icon'] = trim((string) (($actor['icon'] ?? '') ?: $actorEntry['icon']));
                $actorEntry['url'] = trim((string) (($actor['url'] ?? '') ?: ($actor['id'] ?? '') ?: $actorEntry['url']));
            }
        }
        $identifiers = [];
        foreach (['object_id', 'url', 'id'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                $identifiers[] = $value;
            }
        }
        foreach (array_unique($identifiers) as $identifier) {
            if (!isset($details[$identifier])) {
                $details[$identifier] = [];
            }
            $actorKey = $actorId !== '' ? $actorId : sha1(json_encode($actorEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $details[$identifier][$actorKey] = $actorEntry;
        }
    }
    foreach ($details as $identifier => $actors) {
        $details[$identifier] = array_values($actors);
    }
    return $details;
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

function nammu_fediverse_timeline_entries_targeting_local_items(array $config): array
{
    $index = nammu_fediverse_local_items_index($config);
    $entries = [];
    foreach (nammu_fediverse_timeline_store()['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        $target = '';
        if ($type === 'announce') {
            foreach (['object_id', 'url', 'id'] as $field) {
                $value = trim((string) ($item[$field] ?? ''));
                if ($value !== '' && isset($index[$value])) {
                    $target = $value;
                    break;
                }
            }
        } else {
            $target = trim((string) ($item['target_url'] ?? ''));
        }
        if ($target === '' || !isset($index[$target])) {
            continue;
        }
        $entries[] = [
            'target' => $target,
            'item' => $item,
            'canonical_item' => nammu_fediverse_canonical_local_item($index[$target], $config),
        ];
    }
    return $entries;
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
        if ($type === 'reply_announce') {
            $objectUrl = trim((string) ($action['reply_object_id'] ?? (($action['object_url'] ?? '') ?: '')));
            if ($objectUrl === '') {
                continue;
            }
            $published = trim((string) ($action['published'] ?? ''));
            if ($published === '') {
                $published = gmdate(DATE_ATOM);
            }
            $activityId = trim((string) ($action['activity_id'] ?? ''));
            if ($activityId === '') {
                $activityId = $actorUrl . '/reply-announces/' . trim((string) ($action['id'] ?? substr(sha1($objectUrl . '|' . $published), 0, 24)));
            }
            $cc = array_values(array_filter([
                trim((string) ($action['reply_actor_id'] ?? '')),
                trim((string) ($action['target_actor_id'] ?? '')),
            ]));
            $activities[] = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $activityId,
                'type' => 'Announce',
                'actor' => $actorUrl,
                'object' => $objectUrl,
                'to' => ['https://www.w3.org/ns/activitystreams#Public'],
                'cc' => $cc,
                'published' => $published,
            ];
            continue;
        }
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
            continue;
        }
        if ($type === 'reply') {
            $noteId = trim((string) ($action['note_id'] ?? ''));
            $objectUrl = trim((string) ($action['object_url'] ?? ''));
            $replyText = trim((string) ($action['reply_text'] ?? ''));
            $published = trim((string) ($action['published'] ?? ''));
            if ($noteId === '' || $objectUrl === '' || $replyText === '') {
                continue;
            }
            if ($published === '') {
                $published = gmdate(DATE_ATOM);
            }
            $activityId = trim((string) ($action['activity_id'] ?? ''));
            if ($activityId === '') {
                $activityId = $noteId . '/activity';
            }
            $targetActorId = trim((string) ($action['actor_id'] ?? ''));
            $mentionActor = !isset($action['mention_actor']) || (string) ($action['mention_actor'] ?? '1') !== '0';
            $targetActor = $targetActorId !== '' ? nammu_fediverse_resolve_actor($targetActorId, $config) : [];
            $targetName = trim((string) ($targetActor['preferredUsername'] ?? ''));
            $targetHost = is_string(parse_url($targetActorId, PHP_URL_HOST)) ? (string) parse_url($targetActorId, PHP_URL_HOST) : '';
            $targetHandle = $targetName !== '' ? '@' . $targetName . ($targetHost !== '' ? '@' . $targetHost : '') : '';
            $mentionHtml = $mentionActor && $targetActorId !== '' && $targetHandle !== ''
                ? '<a href="' . htmlspecialchars($targetActorId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="mention">' . htmlspecialchars($targetHandle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>'
                : '';
            $contentHtml = trim($mentionHtml . ($replyText !== '' ? ' ' . nl2br(htmlspecialchars($replyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) : ''));
            $cc = [];
            if ($mentionActor && $targetActorId !== '') {
                $cc[] = $targetActorId;
            }
            $targetFollowers = trim((string) ($targetActor['followers'] ?? ''));
            if ($targetFollowers !== '') {
                $cc[] = $targetFollowers;
            }
            $cc = array_values(array_unique(array_filter($cc)));
            $tag = [];
            if ($mentionActor && $targetActorId !== '' && $targetHandle !== '') {
                $tag[] = [
                    'type' => 'Mention',
                    'href' => $targetActorId,
                    'name' => $targetHandle,
                ];
            }
            $activities[] = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $activityId,
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
                    'context' => $objectUrl,
                    'conversation' => $objectUrl,
                    'url' => $noteId,
                ],
            ];
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
        'images' => array_values(array_filter(array_map('strval', is_array($action['images'] ?? null) ? $action['images'] : []))),
    ];
}

function nammu_fediverse_replies_for_item(array $item): array
{
    $config = function_exists('nammu_load_config') ? nammu_load_config() : [];
    return nammu_fediverse_public_replies_for_targets(nammu_fediverse_item_identifiers_with_canonical($item, $config));
}

function nammu_fediverse_remote_replies_for_item(array $item, array $config): array
{
    static $cache = [];
    $objectId = trim((string) (($item['object_id'] ?? '') ?: ($item['id'] ?? '')));
    if ($objectId === '') {
        return [];
    }
    if (array_key_exists($objectId, $cache)) {
        return $cache[$objectId];
    }

    $objectDocument = nammu_fediverse_signed_fetch_json($objectId, $config);
    if (!is_array($objectDocument)) {
        $cache[$objectId] = [];
        return [];
    }

    $replies = [];
    $seenObjects = [];
    $seenReplies = [];
    $fetchObject = static function (string $url, array $config): ?array {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $fetched = nammu_fediverse_signed_fetch_json($url, $config);
        if (!is_array($fetched)) {
            $fetched = nammu_fediverse_fetch_json($url);
        }
        return is_array($fetched) ? $fetched : null;
    };
    $extractOrderedItems = static function (array $collection, array $config, ?array $firstPage = null) use ($fetchObject): array {
        $orderedItems = $collection['orderedItems'] ?? ($collection['items'] ?? []);
        if (empty($orderedItems) && is_array($collection['first'] ?? null)) {
            $firstObject = $collection['first'];
            $orderedItems = $firstObject['orderedItems'] ?? ($firstObject['items'] ?? []);
            if (empty($orderedItems) || !empty($firstPage)) {
                $firstId = trim((string) ($firstObject['id'] ?? ''));
                if ($firstId !== '') {
                    $firstFetched = $fetchObject($firstId, $config);
                    if (is_array($firstFetched)) {
                        $orderedItems = $firstFetched['orderedItems'] ?? ($firstFetched['items'] ?? []);
                    }
                }
            }
        } elseif (empty($orderedItems) && is_array($firstPage)) {
            $firstId = trim((string) ($firstPage['id'] ?? ''));
            if ($firstId !== '') {
                $firstFetched = $fetchObject($firstId, $config);
                if (is_array($firstFetched)) {
                    $orderedItems = $firstFetched['orderedItems'] ?? ($firstFetched['items'] ?? []);
                }
            }
        } elseif (empty($orderedItems) && is_string($collection['first'] ?? null)) {
            $firstId = trim((string) $collection['first']);
            if ($firstId !== '') {
                $firstFetched = $fetchObject($firstId, $config);
                if (is_array($firstFetched)) {
                    $orderedItems = $firstFetched['orderedItems'] ?? ($firstFetched['items'] ?? []);
                }
            }
        }
        if (is_array($orderedItems) && array_key_exists('id', $orderedItems)) {
            $orderedItems = [$orderedItems];
        }
        return is_array($orderedItems) ? $orderedItems : [];
    };
    $walkReplies = function (array $sourceObject, int $depth) use (&$walkReplies, &$replies, &$seenObjects, &$seenReplies, $config, $fetchObject, $extractOrderedItems): void {
        if ($depth > 4) {
            return;
        }
        $repliesRef = $sourceObject['replies'] ?? null;
        $collectionUrl = '';
        $firstPage = null;
        if (is_string($repliesRef)) {
            $collectionUrl = trim($repliesRef);
        } elseif (is_array($repliesRef)) {
            $firstRef = $repliesRef['first'] ?? null;
            if (is_array($firstRef)) {
                $firstPage = $firstRef;
                $collectionUrl = trim((string) (($repliesRef['id'] ?? '') ?: ($firstRef['partOf'] ?? '') ?: ($firstRef['id'] ?? '')));
            } elseif (is_string($firstRef)) {
                $collectionUrl = trim((string) (($repliesRef['id'] ?? '') ?: $firstRef));
            } else {
                $collectionUrl = trim((string) ($repliesRef['id'] ?? ''));
            }
        }
        if ($collectionUrl === '') {
            return;
        }
        if (isset($seenObjects['collection:' . $collectionUrl])) {
            return;
        }
        $seenObjects['collection:' . $collectionUrl] = true;
        $collection = $fetchObject($collectionUrl, $config);
        if (!is_array($collection) && is_array($firstPage)) {
            $collection = $firstPage;
        }
        if (!is_array($collection)) {
            return;
        }
        foreach ($extractOrderedItems($collection, $config, $firstPage) as $rawReply) {
            $replyObject = null;
            if (is_string($rawReply)) {
                $replyObject = $fetchObject(trim($rawReply), $config);
            } elseif (is_array($rawReply)) {
                $replyObject = $rawReply;
                $replyObjectId = trim((string) ($replyObject['id'] ?? ''));
                if ($replyObjectId !== '') {
                    $hydrated = $fetchObject($replyObjectId, $config);
                    if (is_array($hydrated)) {
                        $replyObject = $hydrated;
                    }
                }
            }
            if (!is_array($replyObject)) {
                continue;
            }
            if (strtolower(trim((string) ($replyObject['type'] ?? ''))) !== 'note') {
                continue;
            }
            $replyHtml = trim((string) ($replyObject['content'] ?? ''));
            $replyText = trim((string) (function_exists('nammu_fediverse_html_to_text') ? nammu_fediverse_html_to_text($replyHtml) : strip_tags($replyHtml)));
            if ($replyText === '') {
                continue;
            }
            $replyId = trim((string) (($replyObject['id'] ?? '') ?: sha1(json_encode($replyObject))));
            $replyUrl = trim((string) (($replyObject['url'] ?? '') ?: ''));
            $dedupKeys = array_filter([
                $replyId !== '' ? 'id:' . $replyId : '',
                $replyUrl !== '' ? 'url:' . $replyUrl : '',
            ]);
            $alreadySeen = false;
            foreach ($dedupKeys as $dedupKey) {
                if (isset($seenReplies[$dedupKey])) {
                    $alreadySeen = true;
                    break;
                }
            }
            if (!$alreadySeen) {
                foreach ($dedupKeys as $dedupKey) {
                    $seenReplies[$dedupKey] = true;
                }
                $actorId = trim((string) ($replyObject['attributedTo'] ?? ''));
                $actor = $actorId !== '' ? nammu_fediverse_resolve_actor($actorId, $config) : [];
                $attachments = [];
                $attachmentList = $replyObject['attachment'] ?? [];
                if (is_array($attachmentList) && array_key_exists('type', $attachmentList)) {
                    $attachmentList = [$attachmentList];
                }
                foreach ((array) $attachmentList as $attachment) {
                    if (!is_array($attachment)) {
                        continue;
                    }
                    $attachmentUrl = nammu_fediverse_extract_url($attachment['url'] ?? ($attachment['href'] ?? ''));
                    if ($attachmentUrl === '') {
                        continue;
                    }
                    $attachments[] = [
                        'type' => strtolower(trim((string) ($attachment['type'] ?? ''))),
                        'url' => $attachmentUrl,
                        'name' => trim((string) ($attachment['name'] ?? '')),
                        'media_type' => trim((string) (($attachment['mediaType'] ?? '') ?: ($attachment['mimeType'] ?? ''))),
                        'image' => nammu_fediverse_extract_url($attachment['image'] ?? ''),
                        'summary' => trim((string) ($attachment['summary'] ?? '')),
                    ];
                }
                $replies[] = [
                    'id' => $replyId,
                    'note_id' => trim((string) (($replyObject['id'] ?? '') ?: '')),
                    'url' => $replyUrl,
                    'published' => trim((string) ($replyObject['published'] ?? '')),
                    'reply_text' => $replyText,
                    'actor_id' => $actorId,
                    'actor_name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? '') ?: $actorId)),
                    'actor_username' => trim((string) ($actor['preferredUsername'] ?? '')),
                    'actor_icon' => trim((string) ($actor['icon'] ?? '')),
                    'attachments' => $attachments,
                    'link_card' => nammu_fediverse_reply_link_card_from_attachments($attachments),
                    'source' => 'incoming-remote',
                ];
            }
            $walkReplies($replyObject, $depth + 1);
        }
    };
    $walkReplies($objectDocument, 0);
    usort($replies, static function (array $a, array $b): int {
        return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
    });
    $cache[$objectId] = $replies;
    return $replies;
}

function nammu_fediverse_cached_remote_replies_for_item(array $item, array $config, int $maxAge = 300): array
{
    $objectId = trim((string) (($item['object_id'] ?? '') ?: ($item['id'] ?? '')));
    if ($objectId === '') {
        return [];
    }
    $cacheStore = nammu_fediverse_threads_cache_store();
    $cacheItems = is_array($cacheStore['items'] ?? null) ? $cacheStore['items'] : [];
    $now = time();
    $cached = is_array($cacheItems[$objectId] ?? null) ? $cacheItems[$objectId] : null;
    $cachedReplies = is_array($cached['replies'] ?? null) ? $cached['replies'] : [];
    if (is_array($cached)) {
        $fetchedAt = (int) ($cached['fetched_at'] ?? 0);
        if ($fetchedAt > 0 && ($now - $fetchedAt) <= $maxAge) {
            return $cachedReplies;
        }
    }
    $replies = nammu_fediverse_stable_reply_list(
        $cachedReplies,
        nammu_fediverse_remote_replies_for_item($item, $config)
    );
    $cacheItems[$objectId] = [
        'fetched_at' => $now,
        'replies' => $replies,
    ];
    if (count($cacheItems) > 300) {
        uasort($cacheItems, static function (array $a, array $b): int {
            return ((int) ($b['fetched_at'] ?? 0)) <=> ((int) ($a['fetched_at'] ?? 0));
        });
        $cacheItems = array_slice($cacheItems, 0, 300, true);
    }
    nammu_fediverse_save_threads_cache_store($cacheItems);
    return $replies;
}

function nammu_fediverse_cached_remote_replies_snapshot_for_item(array $item): array
{
    $objectId = trim((string) (($item['object_id'] ?? '') ?: ($item['id'] ?? '')));
    if ($objectId === '') {
        return [];
    }
    $cacheStore = nammu_fediverse_threads_cache_store();
    $cacheItems = is_array($cacheStore['items'] ?? null) ? $cacheStore['items'] : [];
    $cached = is_array($cacheItems[$objectId] ?? null) ? $cacheItems[$objectId] : null;
    return is_array($cached['replies'] ?? null) ? $cached['replies'] : [];
}

function nammu_fediverse_warm_threads_cache(array $config, int $limit = 20): int
{
    $timelineItems = nammu_fediverse_timeline_store()['items'];
    $warmed = 0;
    foreach ($timelineItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        if (in_array($type, ['like', 'delete'], true)) {
            continue;
        }
        $objectId = trim((string) (($item['object_id'] ?? '') ?: ($item['id'] ?? '')));
        if ($objectId === '') {
            continue;
        }
        nammu_fediverse_cached_remote_replies_for_item($item, $config);
        $warmed++;
        if ($warmed >= max(1, $limit)) {
            break;
        }
    }
    return $warmed;
}

function nammu_fediverse_warm_recent_threads_cache(array $config, int $limit = 8): int
{
    $timelineItems = nammu_fediverse_timeline_store()['items'];
    $warmed = 0;
    $seenObjects = [];
    foreach ($timelineItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        if (in_array($type, ['like', 'delete'], true)) {
            continue;
        }
        $objectId = trim((string) (($item['object_id'] ?? '') ?: ($item['id'] ?? '')));
        if ($objectId === '' || isset($seenObjects[$objectId])) {
            continue;
        }
        $seenObjects[$objectId] = true;
        nammu_fediverse_cached_remote_replies_for_item($item, $config);
        $warmed++;
        if ($warmed >= max(1, $limit)) {
            break;
        }
    }
    return $warmed;
}

function nammu_fediverse_merge_thread_replies(array ...$replyGroups): array
{
    $merged = [];
    $seen = [];
    foreach ($replyGroups as $replyGroup) {
        foreach ($replyGroup as $reply) {
            if (!is_array($reply)) {
                continue;
            }
            $dedupKeys = array_filter([
                trim((string) ($reply['id'] ?? '')) !== '' ? 'id:' . trim((string) ($reply['id'] ?? '')) : '',
                trim((string) ($reply['url'] ?? '')) !== '' ? 'url:' . trim((string) ($reply['url'] ?? '')) : '',
                trim((string) ($reply['note_id'] ?? '')) !== '' ? 'note:' . trim((string) ($reply['note_id'] ?? '')) : '',
            ]);
            $fallback = strtolower(trim((string) ($reply['actor_id'] ?? ''))) . '|' .
                trim((string) ($reply['published'] ?? '')) . '|' .
                trim((string) ($reply['reply_text'] ?? ''));
            if ($fallback !== '||') {
                $dedupKeys[] = 'fallback:' . $fallback;
            }
            $alreadySeen = false;
            foreach ($dedupKeys as $dedupKey) {
                if (isset($seen[$dedupKey])) {
                    $alreadySeen = true;
                    break;
                }
            }
            if ($alreadySeen) {
                continue;
            }
            foreach ($dedupKeys as $dedupKey) {
                $seen[$dedupKey] = true;
            }
            $merged[] = $reply;
        }
    }
    usort($merged, static function (array $a, array $b): int {
        return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
    });
    return $merged;
}

function nammu_fediverse_reply_list_score(array $replies): int
{
    $score = 0;
    foreach ($replies as $reply) {
        if (!is_array($reply)) {
            continue;
        }
        $score += 4;
        foreach (['id', 'url', 'note_id', 'reply_text', 'actor_id', 'published'] as $field) {
            if (trim((string) ($reply[$field] ?? '')) !== '') {
                $score++;
            }
        }
        $summary = is_array($reply['summary'] ?? null) ? $reply['summary'] : [];
        $details = is_array($reply['details'] ?? null) ? $reply['details'] : [];
        $score += max(0, (int) ($summary['likes'] ?? 0))
            + max(0, (int) ($summary['shares'] ?? 0))
            + max(0, (int) ($summary['replies'] ?? 0));
        $score += count((array) ($details['likes'] ?? []))
            + count((array) ($details['shares'] ?? []))
            + count((array) ($details['replies'] ?? []));
    }
    return $score;
}

function nammu_fediverse_stable_reply_list(array ...$replyGroups): array
{
    $merged = nammu_fediverse_merge_thread_replies(...$replyGroups);
    $bestReplies = $merged;
    $bestScore = nammu_fediverse_reply_list_score($merged);
    foreach ($replyGroups as $replyGroup) {
        $score = nammu_fediverse_reply_list_score($replyGroup);
        if ($score > $bestScore) {
            $bestReplies = $replyGroup;
            $bestScore = $score;
        }
    }
    return $bestReplies;
}

function nammu_fediverse_collect_recursive_replies(array $replyIndex, array $rootTargets): array
{
    $queue = array_values(array_filter(array_map(static fn($target): string => trim((string) $target), $rootTargets)));
    $visitedTargets = [];
    $collected = [];
    $seenReplies = [];

    while (!empty($queue)) {
        $target = array_shift($queue);
        if ($target === '' || isset($visitedTargets[$target])) {
            continue;
        }
        $visitedTargets[$target] = true;
        foreach ((array) ($replyIndex[$target] ?? []) as $reply) {
            if (!is_array($reply)) {
                continue;
            }
            $dedupKeys = array_filter([
                trim((string) ($reply['id'] ?? '')) !== '' ? 'id:' . trim((string) ($reply['id'] ?? '')) : '',
                trim((string) ($reply['url'] ?? '')) !== '' ? 'url:' . trim((string) ($reply['url'] ?? '')) : '',
                trim((string) ($reply['note_id'] ?? '')) !== '' ? 'note:' . trim((string) ($reply['note_id'] ?? '')) : '',
            ]);
            $fallback = strtolower(trim((string) ($reply['actor_id'] ?? ''))) . '|' .
                trim((string) ($reply['published'] ?? '')) . '|' .
                trim((string) ($reply['reply_text'] ?? ''));
            if ($fallback !== '||') {
                $dedupKeys[] = 'fallback:' . $fallback;
            }
            $alreadySeen = false;
            foreach ($dedupKeys as $dedupKey) {
                if (isset($seenReplies[$dedupKey])) {
                    $alreadySeen = true;
                    break;
                }
            }
            if (!$alreadySeen) {
                foreach ($dedupKeys as $dedupKey) {
                    $seenReplies[$dedupKey] = true;
                }
                $collected[] = $reply;
                foreach (['id', 'url', 'note_id'] as $field) {
                    $identifier = trim((string) ($reply[$field] ?? ''));
                    if ($identifier !== '' && !isset($visitedTargets[$identifier])) {
                        $queue[] = $identifier;
                    }
                }
            }
        }
    }

    usort($collected, static function (array $a, array $b): int {
        return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
    });

    return $collected;
}

function nammu_fediverse_local_public_replies_by_object(array $config): array
{
    $grouped = [];
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        if (strtolower(trim((string) ($action['type'] ?? ''))) !== 'reply') {
            continue;
        }
        $target = trim((string) ($action['object_url'] ?? ''));
        if ($target === '') {
            continue;
        }
        $canonicalTarget = nammu_fediverse_canonical_local_id_for_identifier($target, $config);
        if ($canonicalTarget === '') {
            continue;
        }
        if (!isset($grouped[$canonicalTarget])) {
            $grouped[$canonicalTarget] = [];
        }
        $grouped[$canonicalTarget][] = [
            'id' => trim((string) ($action['id'] ?? '')),
            'note_id' => trim((string) ($action['note_id'] ?? '')),
            'activity_id' => trim((string) ($action['activity_id'] ?? '')),
            'url' => trim((string) ($action['note_id'] ?? '')),
            'target_url' => $target,
            'published' => trim((string) ($action['published'] ?? '')),
            'reply_text' => trim((string) ($action['reply_text'] ?? '')),
            'actor_id' => trim((string) ($action['actor_id'] ?? '')),
            'actor_name' => '',
            'actor_username' => '',
            'actor_icon' => '',
            'source' => 'local',
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

function nammu_fediverse_build_home_thread_payloads(array $localItems, array $config, array $reactionSummary, array $reactionDetails, array $incomingReplies, array $existingThreadPayloads = []): array
{
    $threadPayloads = [];
    $localRepliesByObject = nammu_fediverse_local_public_replies_by_object($config);
    foreach ($localItems as $localItem) {
        if (!is_array($localItem)) {
            continue;
        }
        $canonicalItem = nammu_fediverse_canonical_local_item($localItem, $config);
        $localId = trim((string) ($canonicalItem['id'] ?? ($localItem['id'] ?? '')));
        if ($localId === '') {
            continue;
        }
        $targetIdentifiers = nammu_fediverse_item_identifiers_with_canonical($canonicalItem, $config);
        $existingPayload = is_array($existingThreadPayloads[$localId] ?? null) ? $existingThreadPayloads[$localId] : null;
        $existingReplies = is_array($existingPayload['replies'] ?? null) ? $existingPayload['replies'] : [];
        $remoteReplies = nammu_fediverse_cached_remote_replies_snapshot_for_item($canonicalItem);
        if (empty($remoteReplies) && nammu_fediverse_is_named_local_object_id($localId, $config)) {
            $remoteReplies = nammu_fediverse_cached_remote_replies_for_item($canonicalItem, $config, 86400);
        }
        $mergedReplies = nammu_fediverse_stable_reply_list(
            nammu_fediverse_collect_recursive_replies($localRepliesByObject, $targetIdentifiers),
            nammu_fediverse_collect_recursive_replies($incomingReplies, $targetIdentifiers),
            $remoteReplies,
            $existingReplies
        );
        $summary = $reactionSummary[$localId] ?? ['likes' => 0, 'shares' => 0, 'replies' => 0];
        $summary['replies'] = count($mergedReplies);
        $details = $reactionDetails[$localId] ?? ['likes' => [], 'shares' => [], 'replies' => []];
        $replyActors = [];
        foreach ($mergedReplies as $reply) {
            if (!is_array($reply)) {
                continue;
            }
            $actorId = trim((string) ($reply['actor_id'] ?? ''));
            $actorKey = $actorId !== '' ? $actorId : sha1(json_encode($reply, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            if (isset($replyActors[$actorKey])) {
                continue;
            }
            $replyActors[$actorKey] = [
                'id' => $actorId,
                'name' => trim((string) (($reply['actor_name'] ?? '') ?: ($reply['actor_username'] ?? '') ?: $actorId)),
                'icon' => trim((string) ($reply['actor_icon'] ?? '')),
                'url' => trim((string) (($reply['url'] ?? '') ?: $actorId)),
                'published' => trim((string) ($reply['published'] ?? '')),
            ];
        }
        $details['replies'] = array_values($replyActors);
        $candidatePayload = [
            'item' => $canonicalItem,
            'thread_url' => nammu_fediverse_thread_page_url($localId, $config),
            'original_url' => trim((string) ($canonicalItem['url'] ?? ($localItem['url'] ?? ''))),
            'summary' => $summary,
            'details' => $details,
            'replies' => $mergedReplies,
        ];
        $threadPayloads[$localId] = is_array($existingPayload)
            && nammu_fediverse_thread_payload_score($existingPayload) > nammu_fediverse_thread_payload_score($candidatePayload)
            ? $existingPayload
            : $candidatePayload;
    }
    return $threadPayloads;
}

function nammu_fediverse_promote_thread_payloads_to_threads_cache(array $threadPayloads, array $config): void
{
    $cacheStore = nammu_fediverse_threads_cache_store();
    $cacheItems = is_array($cacheStore['items'] ?? null) ? $cacheStore['items'] : [];
    $now = time();
    $changed = false;

    foreach ($threadPayloads as $payload) {
        if (!is_array($payload)) {
            continue;
        }
        $item = is_array($payload['item'] ?? null) ? $payload['item'] : [];
        $objectId = trim((string) (($item['object_id'] ?? '') ?: ($item['id'] ?? '')));
        if ($objectId === '' || !nammu_fediverse_is_named_local_object_id($objectId, $config)) {
            continue;
        }
        $payloadReplies = is_array($payload['replies'] ?? null) ? $payload['replies'] : [];
        if ($payloadReplies === []) {
            continue;
        }
        $existing = is_array($cacheItems[$objectId] ?? null) ? $cacheItems[$objectId] : [];
        $existingReplies = is_array($existing['replies'] ?? null) ? $existing['replies'] : [];
        $stableReplies = nammu_fediverse_stable_reply_list($existingReplies, $payloadReplies);
        if (nammu_fediverse_reply_list_score($stableReplies) <= nammu_fediverse_reply_list_score($existingReplies)) {
            continue;
        }
        $cacheItems[$objectId] = [
            'fetched_at' => max($now, (int) ($existing['fetched_at'] ?? 0)),
            'replies' => $stableReplies,
        ];
        $changed = true;
    }

    if ($changed) {
        if (count($cacheItems) > 300) {
            uasort($cacheItems, static function (array $a, array $b): int {
                return ((int) ($b['fetched_at'] ?? 0)) <=> ((int) ($a['fetched_at'] ?? 0));
            });
            $cacheItems = array_slice($cacheItems, 0, 300, true);
        }
        nammu_fediverse_save_threads_cache_store($cacheItems);
    }
}

function nammu_fediverse_store_files_for_tab(string $tab): array
{
    $tab = strtolower(trim($tab));
    $files = match ($tab) {
        'home' => [
            nammu_fediverse_home_snapshot_file(),
            nammu_fediverse_timeline_file(),
            nammu_fediverse_actions_file(),
            nammu_fediverse_inbox_file(),
            nammu_fediverse_threads_cache_file(),
            nammu_fediverse_deleted_file(),
            nammu_fediverse_hidden_replies_file(),
            nammu_fediverse_followers_file(),
        ],
        'notifications' => [
            nammu_fediverse_notifications_snapshot_file(),
            nammu_fediverse_inbox_file(),
            nammu_fediverse_hidden_replies_file(),
        ],
        'messages' => [
            nammu_fediverse_messages_snapshot_file(),
            nammu_fediverse_messages_file(),
            nammu_fediverse_actions_file(),
            nammu_fediverse_inbox_file(),
            nammu_fediverse_threads_cache_file(),
            nammu_fediverse_following_file(),
            nammu_fediverse_followers_file(),
            nammu_fediverse_hidden_replies_file(),
        ],
        'mentions' => [
            dirname(__DIR__) . '/config/webmentions.json',
        ],
        'network' => [
            nammu_fediverse_following_file(),
            nammu_fediverse_followers_file(),
            nammu_fediverse_blocked_file(),
        ],
        'settings' => [
            nammu_fediverse_followers_file(),
            nammu_fediverse_keys_file(),
            nammu_fediverse_actions_file(),
        ],
        default => [
            nammu_fediverse_timeline_file(),
            nammu_fediverse_inbox_file(),
            nammu_fediverse_messages_file(),
            nammu_fediverse_following_file(),
            nammu_fediverse_followers_file(),
            nammu_fediverse_actions_file(),
            nammu_fediverse_threads_cache_file(),
            nammu_fediverse_hidden_replies_file(),
            nammu_fediverse_blocked_file(),
        ],
    };
    return array_values(array_unique($files));
}

function nammu_fediverse_tab_version(string $tab): string
{
    $parts = [strtolower(trim($tab))];
    foreach (nammu_fediverse_store_files_for_tab($tab) as $file) {
        $mtime = is_file($file) ? (string) ((int) @filemtime($file)) : '0';
        $size = is_file($file) ? (string) ((int) @filesize($file)) : '0';
        $parts[] = basename($file) . ':' . $mtime . ':' . $size;
    }
    foreach ([__FILE__, dirname(__DIR__) . '/core/admin-page-fediverso.php', dirname(__DIR__) . '/admin.php'] as $codeFile) {
        $mtime = is_file($codeFile) ? (string) ((int) @filemtime($codeFile)) : '0';
        $size = is_file($codeFile) ? (string) ((int) @filesize($codeFile)) : '0';
        $parts[] = basename($codeFile) . ':' . $mtime . ':' . $size;
    }
    return substr(sha1(implode('|', $parts)), 0, 20);
}

function nammu_fediverse_rebuild_home_snapshot(array $config): array
{
    $existingSnapshot = nammu_fediverse_home_snapshot_store();
    $existingData = is_array($existingSnapshot['data'] ?? null) ? $existingSnapshot['data'] : [];
    $existingThreadPayloads = is_array($existingData['thread_payloads'] ?? null) ? $existingData['thread_payloads'] : [];
    $knownActors = nammu_fediverse_known_actors();
    $actorsById = [];
    foreach ($knownActors as $actor) {
        $actorId = trim((string) ($actor['id'] ?? ''));
        if ($actorId !== '') {
            $actorsById[$actorId] = $actor;
        }
    }
    $localItems = nammu_fediverse_local_content_items($config);
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        $resendItem = nammu_fediverse_resend_item_from_action($action);
        if (!is_array($resendItem)) {
            continue;
        }
        $resendUrl = trim((string) ($resendItem['url'] ?? ''));
        $duplicate = false;
        foreach ($localItems as $existingLocalItem) {
            if ($resendUrl !== '' && $resendUrl === trim((string) ($existingLocalItem['url'] ?? ''))) {
                $duplicate = true;
                break;
            }
        }
        if (!$duplicate) {
            $localItems[] = $resendItem;
        }
    }
    $localReactionSummary = nammu_fediverse_local_reaction_summary($config);
    $localReactionDetails = nammu_fediverse_local_reaction_details($config);
    $incomingReplies = nammu_fediverse_incoming_public_replies_by_object($config);
    $data = [
        'actors_by_id' => $actorsById,
        'timeline' => nammu_fediverse_timeline_store()['items'],
        'local_items' => $localItems,
        'local_reaction_summary' => $localReactionSummary,
        'local_reaction_details' => $localReactionDetails,
        'remote_boost_summary' => nammu_fediverse_remote_boost_summary(),
        'remote_boost_details' => nammu_fediverse_remote_boost_details($config),
        'remote_reply_summary' => nammu_fediverse_remote_reply_summary(),
        'incoming_replies' => $incomingReplies,
    ];
    $data['thread_payloads'] = nammu_fediverse_build_home_thread_payloads(
        $localItems,
        $config,
        $localReactionSummary,
        $localReactionDetails,
        $incomingReplies,
        $existingThreadPayloads
    );
    nammu_fediverse_promote_thread_payloads_to_threads_cache($data['thread_payloads'], $config);
    nammu_fediverse_save_home_snapshot_store($data);
    return $data;
}

function nammu_fediverse_rebuild_messages_snapshot(array $config): array
{
    $knownActors = nammu_fediverse_known_actors();
    $actorsById = [];
    foreach ($knownActors as $actor) {
        $actorId = trim((string) ($actor['id'] ?? ''));
        if ($actorId !== '') {
            $actorsById[$actorId] = $actor;
        }
    }
    $messages = nammu_fediverse_grouped_messages();
    $flatMessages = [];
    $flatMessageKeys = [];
    foreach ($messages as $groupItems) {
        foreach ((array) $groupItems as $messageItem) {
            $messageId = trim((string) ($messageItem['id'] ?? ''));
            $messageKey = $messageId !== '' ? 'id:' . $messageId : 'hash:' . sha1(json_encode($messageItem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            if (isset($flatMessageKeys[$messageKey])) {
                continue;
            }
            $flatMessageKeys[$messageKey] = true;
            $flatMessages[] = $messageItem;
        }
    }
    $data = [
        'actors_by_id' => $actorsById,
        'recipients' => nammu_fediverse_message_recipients(),
        'message_threads' => nammu_fediverse_thread_grouped_messages($flatMessages),
    ];
    nammu_fediverse_save_messages_snapshot_store($data);
    return $data;
}

function nammu_fediverse_rebuild_notifications_snapshot(array $config): array
{
    $knownActors = nammu_fediverse_known_actors();
    $actorsById = [];
    foreach ($knownActors as $actor) {
        $actorId = trim((string) ($actor['id'] ?? ''));
        if ($actorId !== '') {
            $actorsById[$actorId] = $actor;
        }
    }
    $data = [
        'actors_by_id' => $actorsById,
        'notifications' => nammu_fediverse_notification_entries($config),
    ];
    nammu_fediverse_save_notifications_snapshot_store($data);
    return $data;
}

function nammu_fediverse_rebuild_snapshots(array $config): array
{
    nammu_fediverse_refresh_legacy_actuality_aliases($config);
    $home = nammu_fediverse_rebuild_home_snapshot($config);
    $messages = nammu_fediverse_rebuild_messages_snapshot($config);
    $notifications = nammu_fediverse_rebuild_notifications_snapshot($config);
    return ['home' => $home, 'messages' => $messages, 'notifications' => $notifications];
}

function nammu_fediverse_rebuild_light_snapshots(array $config): array
{
    nammu_fediverse_refresh_legacy_actuality_aliases($config);
    $home = nammu_fediverse_rebuild_home_snapshot($config);
    $notifications = nammu_fediverse_rebuild_notifications_snapshot($config);
    return ['home' => $home, 'notifications' => $notifications];
}

function nammu_fediverse_stream_state(array $tabs = ['home', 'notifications', 'messages', 'network', 'settings']): array
{
    $state = [];
    foreach ($tabs as $tab) {
        $tabKey = strtolower(trim((string) $tab));
        if ($tabKey === '') {
            continue;
        }
        $state[$tabKey] = nammu_fediverse_tab_version($tabKey);
    }
    return $state;
}

function nammu_fediverse_fragment_cache_key(string $tab, array $context = []): string
{
    $tab = strtolower(trim($tab));
    ksort($context);
    return $tab . ':' . sha1(json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function nammu_fediverse_get_cached_fragment(string $tab, string $version, array $context = [], int $ttl = 15): string
{
    $key = nammu_fediverse_fragment_cache_key($tab, $context);
    $store = nammu_fediverse_fragments_cache_store()['items'];
    $item = is_array($store[$key] ?? null) ? $store[$key] : null;
    if (!is_array($item)) {
        return '';
    }
    $cachedVersion = trim((string) ($item['version'] ?? ''));
    $cachedHtml = (string) ($item['html'] ?? '');
    $cachedAt = (int) ($item['cached_at'] ?? 0);
    if ($cachedVersion === '' || $cachedHtml === '' || $cachedVersion !== $version) {
        return '';
    }
    if ($cachedAt <= 0 || (time() - $cachedAt) > max(1, $ttl)) {
        return '';
    }
    return $cachedHtml;
}

function nammu_fediverse_store_cached_fragment(string $tab, string $version, array $context, string $html, int $maxItems = 60): void
{
    $html = trim($html);
    if ($html === '' || $version === '') {
        return;
    }
    $key = nammu_fediverse_fragment_cache_key($tab, $context);
    $store = nammu_fediverse_fragments_cache_store()['items'];
    $store[$key] = [
        'version' => $version,
        'cached_at' => time(),
        'html' => $html,
    ];
    uasort($store, static function (array $a, array $b): int {
        return ((int) ($b['cached_at'] ?? 0)) <=> ((int) ($a['cached_at'] ?? 0));
    });
    if (count($store) > $maxItems) {
        $store = array_slice($store, 0, $maxItems, true);
    }
    nammu_fediverse_save_fragments_cache_store($store);
}

function nammu_fediverse_reply_collection_hash(string $objectId): string
{
    return substr(sha1(trim($objectId)), 0, 24);
}

function nammu_fediverse_thread_page_hash(string $objectId): string
{
    return substr(sha1(trim($objectId)), 0, 24);
}

function nammu_fediverse_thread_page_url(string $objectId, array $config): string
{
    return rtrim(nammu_fediverse_base_url($config), '/') . '/fediverso/' . nammu_fediverse_thread_page_hash($objectId);
}

function nammu_fediverse_extract_legacy_actuality_ids_from_payload(array $payload, array $config): array
{
    $baseUrl = rtrim(nammu_fediverse_base_url($config), '/');
    if ($baseUrl === '') {
        return [];
    }
    $prefix = $baseUrl . '/ap/objects/actualidad-';
    $candidates = [];
    $object = $payload['object'] ?? null;

    if (is_string($payload['object'] ?? null) || is_numeric($payload['object'] ?? null)) {
        $candidates[] = trim((string) $payload['object']);
    }
    if (is_array($object)) {
        $candidates[] = trim((string) ($object['id'] ?? ''));
        if (is_string($object['url'] ?? null) || is_numeric($object['url'] ?? null)) {
            $candidates[] = trim((string) $object['url']);
        }
        $candidates[] = trim((string) ($object['atomUri'] ?? ''));
        $candidates[] = trim((string) ($object['inReplyTo'] ?? ''));
    }

    $ids = [];
    foreach ($candidates as $candidate) {
        if ($candidate === '' || !str_starts_with($candidate, $prefix)) {
            continue;
        }
        $ids[] = $candidate;
    }
    return array_values(array_unique(array_filter($ids)));
}

function nammu_fediverse_record_legacy_actuality_payload(array $payload, array $config): void
{
    $ids = nammu_fediverse_extract_legacy_actuality_ids_from_payload($payload, $config);
    if (empty($ids)) {
        return;
    }
    $store = nammu_fediverse_legacy_actuality_store();
    $items = is_array($store['items'] ?? null) ? $store['items'] : [];
    $byId = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemId = trim((string) ($item['id'] ?? ''));
        if ($itemId === '') {
            continue;
        }
        $byId[$itemId] = $item;
    }
    $published = trim((string) ($payload['published'] ?? ''));
    $source = trim((string) ($payload['id'] ?? ''));
    $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
    $legacyTitle = trim((string) ($object['name'] ?? $object['title'] ?? ''));
    $legacyContent = trim((string) strip_tags((string) ($object['content'] ?? $object['summary'] ?? '')));
    $legacyUrl = '';
    if (is_string($object['url'] ?? null) || is_numeric($object['url'] ?? null)) {
        $legacyUrl = trim((string) $object['url']);
    } elseif (is_array($object['url'] ?? null)) {
        foreach ((array) $object['url'] as $candidateUrl) {
            if (is_string($candidateUrl) || is_numeric($candidateUrl)) {
                $legacyUrl = trim((string) $candidateUrl);
                if ($legacyUrl !== '') {
                    break;
                }
            } elseif (is_array($candidateUrl)) {
                $legacyUrl = trim((string) (($candidateUrl['href'] ?? '') ?: ($candidateUrl['url'] ?? '')));
                if ($legacyUrl !== '') {
                    break;
                }
            }
        }
    }
    $legacyImage = '';
    foreach ((array) ($object['attachment'] ?? []) as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $attachmentType = strtolower(trim((string) ($attachment['type'] ?? '')));
        $attachmentMediaType = strtolower(trim((string) ($attachment['mediaType'] ?? $attachment['media_type'] ?? '')));
        $attachmentUrl = trim((string) ($attachment['url'] ?? ''));
        if ($attachmentUrl !== '' && ($attachmentType === 'image' || str_starts_with($attachmentMediaType, 'image/'))) {
            $legacyImage = $attachmentUrl;
            break;
        }
    }
    foreach ($ids as $id) {
        if (!isset($byId[$id])) {
            $byId[$id] = [
                'id' => $id,
                'published' => $published,
                'source' => $source,
                'title' => $legacyTitle,
                'content' => $legacyContent,
                'url' => $legacyUrl,
                'image' => $legacyImage,
            ];
            continue;
        }
        if ($published !== '' && trim((string) ($byId[$id]['published'] ?? '')) === '') {
            $byId[$id]['published'] = $published;
        }
        if ($source !== '' && trim((string) ($byId[$id]['source'] ?? '')) === '') {
            $byId[$id]['source'] = $source;
        }
        if ($legacyTitle !== '' && trim((string) ($byId[$id]['title'] ?? '')) === '') {
            $byId[$id]['title'] = $legacyTitle;
        }
        if ($legacyContent !== '' && trim((string) ($byId[$id]['content'] ?? '')) === '') {
            $byId[$id]['content'] = $legacyContent;
        }
        if ($legacyUrl !== '' && trim((string) ($byId[$id]['url'] ?? '')) === '') {
            $byId[$id]['url'] = $legacyUrl;
        }
        if ($legacyImage !== '' && trim((string) ($byId[$id]['image'] ?? '')) === '') {
            $byId[$id]['image'] = $legacyImage;
        }
    }
    nammu_fediverse_save_legacy_actuality_store(array_values($byId));
}

function nammu_fediverse_sync_legacy_actuality_from_inbox(array $config): void
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    foreach ($activities as $entry) {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        if (empty($payload)) {
            continue;
        }
        nammu_fediverse_record_legacy_actuality_payload($payload, $config);
    }
}

function nammu_fediverse_local_item_alias_identifiers(array $item, array $config): array
{
    $aliases = [];
    $baseUrl = rtrim(nammu_fediverse_base_url($config), '/');
    $itemId = trim((string) ($item['id'] ?? ''));
    $itemUrl = trim((string) ($item['url'] ?? ''));
    $aliasIds = array_values(array_filter(array_map('strval', is_array($item['alias_ids'] ?? null) ? $item['alias_ids'] : [])));

    if ($itemId !== '') {
        $aliases[] = nammu_fediverse_thread_page_url($itemId, $config);
        $aliases[] = $baseUrl . '/fediverso/' . nammu_fediverse_thread_page_hash($itemId . '/activity');
    }
    if ($itemUrl !== '') {
        $aliases[] = $baseUrl . '/fediverso/' . nammu_fediverse_thread_page_hash($itemUrl);
    }
    foreach ($aliasIds as $aliasId) {
        $aliasId = trim($aliasId);
        if ($aliasId === '') {
            continue;
        }
        $aliases[] = $aliasId;
        $aliases[] = nammu_fediverse_thread_page_url($aliasId, $config);
        $aliases[] = $baseUrl . '/fediverso/' . nammu_fediverse_thread_page_hash($aliasId . '/activity');
    }

    return array_values(array_unique(array_filter(array_map('trim', $aliases))));
}

function nammu_fediverse_legacy_actuality_alias_ids(array $item, string $baseUrl): array
{
    $isNews = trim((string) ($item['source_kind'] ?? '')) === 'news';
    if (!$isNews) {
        return [];
    }

    $legacyPayload = [
        'title' => trim((string) ($item['title'] ?? '')),
        'link' => trim((string) ($item['link'] ?? '')),
        'image' => trim((string) ($item['image'] ?? '')),
        'description' => (string) ($item['description'] ?? ''),
        'timestamp' => (int) ($item['timestamp'] ?? 0),
        'source' => (string) (parse_url((string) ($item['link'] ?? ''), PHP_URL_HOST) ?: trim((string) ($item['source'] ?? ''))),
    ];

    if ($legacyPayload['title'] === '' && $legacyPayload['link'] === '') {
        return [];
    }

    $legacyId = sha1(json_encode($legacyPayload));
    if ($legacyId === '') {
        return [];
    }

    return [rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . $legacyId];
}

function nammu_fediverse_remote_thread_page_url(string $objectId): string
{
    $objectId = trim($objectId);
    if ($objectId === '') {
        return '';
    }
    $parts = @parse_url($objectId);
    if (!is_array($parts)) {
        return '';
    }
    $scheme = trim((string) ($parts['scheme'] ?? ''));
    $host = trim((string) ($parts['host'] ?? ''));
    $path = trim((string) ($parts['path'] ?? ''));
    if ($scheme === '' || $host === '' || $path === '') {
        return '';
    }
    if (!preg_match('#^/ap/objects/#', $path)) {
        return '';
    }
    $baseUrl = $scheme . '://' . $host;
    $port = (int) ($parts['port'] ?? 0);
    if ($port > 0) {
        $baseUrl .= ':' . $port;
    }
    return rtrim($baseUrl, '/') . '/fediverso/' . nammu_fediverse_thread_page_hash($objectId);
}

function nammu_fediverse_find_local_item_for_thread_hash(string $hash, array $config): ?array
{
    $hash = trim(strtolower($hash));
    if ($hash === '' || !preg_match('/^[a-f0-9]{24}$/', $hash)) {
        return null;
    }
    $baseUrl = rtrim(nammu_fediverse_base_url($config), '/');
    $deletedIds = array_fill_keys(nammu_fediverse_deleted_store()['ids'], true);
    $legacyAliases = is_array((nammu_fediverse_legacy_actuality_aliases_store()['map'] ?? null)) ? nammu_fediverse_legacy_actuality_aliases_store()['map'] : [];
    $actualityStore = nammu_fediverse_load_json_store(dirname(__DIR__) . '/config/actualidad-items.json', ['items' => []]);
    foreach ((array) ($actualityStore['items'] ?? []) as $actualityItem) {
        if (!is_array($actualityItem)) {
            continue;
        }
        if (!empty($actualityItem['is_manual']) && strtolower(trim((string) ($actualityItem['via'] ?? ''))) === 'boost') {
            continue;
        }
        $shortId = trim((string) ($actualityItem['id'] ?? ''));
        if ($shortId === '' && function_exists('nammu_actuality_news_item_id')) {
            $shortId = trim((string) nammu_actuality_news_item_id($actualityItem));
        }
        if ($shortId === '') {
            continue;
        }
        $itemId = $baseUrl . '/ap/objects/actualidad-' . rawurlencode($shortId);
        if (isset($deletedIds[$itemId])) {
            continue;
        }
        $aliasIds = nammu_fediverse_legacy_actuality_alias_ids($actualityItem, $baseUrl);
        foreach ($legacyAliases as $legacyId => $currentId) {
            if (trim((string) $currentId) === $itemId) {
                $aliasIds[] = trim((string) $legacyId);
            }
        }
        $localItem = [
            'id' => $itemId,
            'url' => trim((string) (($actualityItem['link'] ?? '') ?: ($baseUrl . '/actualidad.php'))),
            'title' => trim((string) ($actualityItem['title'] ?? '')),
            'content' => trim((string) (($actualityItem['raw_text'] ?? '') ?: ($actualityItem['description'] ?? ''))),
            'summary' => trim((string) ($actualityItem['description'] ?? '')),
            'published' => gmdate(DATE_ATOM, (int) (($actualityItem['timestamp'] ?? 0) ?: time())),
            'type' => !empty($actualityItem['is_manual']) ? 'Note' : 'Article',
            'image' => trim((string) (($actualityItem['source_image'] ?? '') ?: ($actualityItem['image'] ?? ''))),
            'images' => array_values(array_filter(array_map('strval', is_array($actualityItem['images'] ?? null) ? $actualityItem['images'] : []))),
            'alias_ids' => array_values(array_unique(array_filter(array_map('trim', $aliasIds)))),
        ];
        $candidateHashes = [nammu_fediverse_thread_page_hash($itemId)];
        foreach (nammu_fediverse_local_item_alias_identifiers($localItem, $config) as $aliasUrl) {
            $candidateHashes[] = nammu_fediverse_thread_page_hash($aliasUrl);
            $aliasPath = trim((string) (parse_url($aliasUrl, PHP_URL_PATH) ?? ''));
            if (preg_match('#/fediverso/([a-f0-9]{24})/?$#', $aliasPath, $matches) === 1) {
                $candidateHashes[] = strtolower((string) ($matches[1] ?? ''));
            }
        }
        if (in_array($hash, array_values(array_unique(array_filter($candidateHashes))), true)) {
            return $localItem;
        }
    }
    foreach (nammu_fediverse_local_content_items($config) as $item) {
        $itemId = trim((string) ($item['id'] ?? ''));
        if ($itemId === '') {
            continue;
        }
        $candidateHashes = [nammu_fediverse_thread_page_hash($itemId)];
        foreach (nammu_fediverse_local_item_alias_identifiers($item, $config) as $aliasUrl) {
            $candidateHashes[] = nammu_fediverse_thread_page_hash($aliasUrl);
            $aliasPath = trim((string) (parse_url($aliasUrl, PHP_URL_PATH) ?? ''));
            if (preg_match('#/fediverso/([a-f0-9]{24})/?$#', $aliasPath, $matches) === 1) {
                $candidateHashes[] = strtolower((string) ($matches[1] ?? ''));
            }
        }
        if (in_array($hash, array_values(array_unique(array_filter($candidateHashes))), true)) {
            return $item;
        }
    }
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        $resendItem = nammu_fediverse_resend_item_from_action($action);
        if (!is_array($resendItem)) {
            continue;
        }
        $itemId = trim((string) ($resendItem['id'] ?? ''));
        if ($itemId === '') {
            continue;
        }
        $candidateHashes = [nammu_fediverse_thread_page_hash($itemId)];
        foreach (nammu_fediverse_local_item_alias_identifiers($resendItem, $config) as $aliasUrl) {
            $candidateHashes[] = nammu_fediverse_thread_page_hash($aliasUrl);
            $aliasPath = trim((string) (parse_url($aliasUrl, PHP_URL_PATH) ?? ''));
            if (preg_match('#/fediverso/([a-f0-9]{24})/?$#', $aliasPath, $matches) === 1) {
                $candidateHashes[] = strtolower((string) ($matches[1] ?? ''));
            }
        }
        if (in_array($hash, array_values(array_unique(array_filter($candidateHashes))), true)) {
            return $resendItem;
        }
    }

    $resolveSnapshotCandidate = static function (array $candidateItem) use ($config): ?array {
        $candidateId = trim((string) ($candidateItem['id'] ?? ''));
        if ($candidateId !== '') {
            $resolvedItem = nammu_fediverse_find_local_item_for_identifier($candidateId, $config);
            if (is_array($resolvedItem)) {
                return nammu_fediverse_canonical_local_item($resolvedItem, $config);
            }
        }

        $candidateUrl = nammu_fediverse_extract_url($candidateItem['url'] ?? '');
        if ($candidateUrl !== '') {
            $resolvedItem = nammu_fediverse_find_local_item_for_identifier($candidateUrl, $config);
            if (is_array($resolvedItem)) {
                return nammu_fediverse_canonical_local_item($resolvedItem, $config);
            }
        }

        return !empty($candidateItem) ? $candidateItem : null;
    };
    $snapshot = nammu_fediverse_home_snapshot_store();
    $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
    foreach ((array) ($data['thread_payloads'] ?? []) as $payload) {
        if (!is_array($payload)) {
            continue;
        }
        $threadUrl = trim((string) ($payload['thread_url'] ?? ''));
        $threadPath = trim((string) (parse_url($threadUrl, PHP_URL_PATH) ?? ''));
        if ($threadPath === '' || preg_match('#/fediverso/([a-f0-9]{24})/?$#', $threadPath, $matches) !== 1) {
            continue;
        }
        if (strtolower((string) ($matches[1] ?? '')) !== $hash) {
            continue;
        }
        $candidateItems = [
            is_array($payload['item'] ?? null) ? $payload['item'] : null,
            is_array($payload['summary']['item'] ?? null) ? $payload['summary']['item'] : null,
        ];
        foreach ($candidateItems as $candidateItem) {
            if (!is_array($candidateItem)) {
                continue;
            }
            $resolvedItem = $resolveSnapshotCandidate($candidateItem);
            if (is_array($resolvedItem)) {
                return $resolvedItem;
            }
        }
    }
    foreach ((array) ($data['local_items'] ?? []) as $candidateItem) {
        if (!is_array($candidateItem)) {
            continue;
        }
        $candidateHashes = [];
        foreach (array_filter([
            trim((string) ($candidateItem['id'] ?? '')),
            nammu_fediverse_extract_url($candidateItem['url'] ?? ''),
            nammu_fediverse_extract_url($candidateItem['original_url'] ?? ''),
        ]) as $identifier) {
            $candidateHashes[] = nammu_fediverse_thread_page_hash($identifier);
            $candidatePath = trim((string) (parse_url($identifier, PHP_URL_PATH) ?? ''));
            if (preg_match('#/fediverso/([a-f0-9]{24})/?$#', $candidatePath, $matches) === 1) {
                $candidateHashes[] = strtolower((string) ($matches[1] ?? ''));
            }
        }
        if (!in_array($hash, array_values(array_unique(array_filter($candidateHashes))), true)) {
            continue;
        }
        $resolvedItem = $resolveSnapshotCandidate($candidateItem);
        if (is_array($resolvedItem)) {
            return $resolvedItem;
        }
    }
    return null;
}

function nammu_fediverse_thread_page_payload(array $item, array $config): array
{
    $canonicalItem = nammu_fediverse_canonical_local_item($item, $config);
    $itemId = trim((string) ($canonicalItem['id'] ?? ($item['id'] ?? '')));
    $targetIdentifiers = nammu_fediverse_item_identifiers_with_canonical($item, $config);
    $localReplies = nammu_fediverse_collect_recursive_replies(
        nammu_fediverse_public_replies_by_object(),
        $targetIdentifiers
    );
    $incomingReplies = nammu_fediverse_incoming_public_replies_by_object($config);
    $reactionSummary = nammu_fediverse_local_reaction_summary($config);
    $reactionDetails = nammu_fediverse_local_reaction_details($config);
    $mergedReplies = nammu_fediverse_merge_thread_replies(
        $localReplies,
        nammu_fediverse_collect_recursive_replies($incomingReplies, $targetIdentifiers),
        nammu_fediverse_cached_remote_replies_snapshot_for_item($canonicalItem)
    );
    $replyReactionTargetMap = [];
    $replyReactionKeyMap = [];
    foreach ($mergedReplies as $replyIndex => $reply) {
        if (!is_array($reply)) {
            continue;
        }
        $replyIdentifiers = array_values(array_unique(array_filter([
            trim((string) ($reply['id'] ?? '')),
            trim((string) ($reply['url'] ?? '')),
            trim((string) ($reply['note_id'] ?? '')),
        ])));
        $replyKey = 'reply:' . $replyIndex;
        foreach ($replyIdentifiers as $replyIdentifier) {
            $replyReactionTargetMap[$replyIdentifier] = $replyKey;
        }
        $replyReactionKeyMap[$replyKey] = $replyIdentifiers;
    }
    $replyReactionMap = nammu_fediverse_reaction_snapshot_for_targets($replyReactionTargetMap, $config);
    foreach ($mergedReplies as $replyIndex => &$reply) {
        if (!is_array($reply)) {
            continue;
        }
        $replyKey = 'reply:' . $replyIndex;
        $replyReaction = is_array($replyReactionMap[$replyKey] ?? null) ? $replyReactionMap[$replyKey] : [
            'summary' => ['likes' => 0, 'shares' => 0],
            'details' => ['likes' => [], 'shares' => []],
        ];
        $reply['summary'] = is_array($replyReaction['summary'] ?? null) ? $replyReaction['summary'] : ['likes' => 0, 'shares' => 0];
        $reply['details'] = is_array($replyReaction['details'] ?? null) ? $replyReaction['details'] : ['likes' => [], 'shares' => []];
    }
    unset($reply);
    usort($mergedReplies, static function (array $a, array $b): int {
        return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
    });
    $summary = $reactionSummary[$itemId] ?? ['likes' => 0, 'shares' => 0, 'replies' => 0];
    $summary['replies'] = count($mergedReplies);
    $details = $reactionDetails[$itemId] ?? ['likes' => [], 'shares' => [], 'replies' => []];
    $replyActors = [];
    foreach ($mergedReplies as $reply) {
        if (!is_array($reply)) {
            continue;
        }
        $actorId = trim((string) ($reply['actor_id'] ?? ''));
        $actorKey = $actorId !== '' ? $actorId : sha1(json_encode($reply, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if (isset($replyActors[$actorKey])) {
            continue;
        }
        $replyActors[$actorKey] = [
            'id' => $actorId,
            'name' => trim((string) (($reply['actor_name'] ?? '') ?: ($reply['actor_username'] ?? '') ?: $actorId)),
            'icon' => trim((string) ($reply['actor_icon'] ?? '')),
            'url' => trim((string) (($reply['url'] ?? '') ?: $actorId)),
            'published' => trim((string) ($reply['published'] ?? '')),
        ];
    }
    $details['replies'] = array_values($replyActors);
    return [
        'item' => $canonicalItem,
        'thread_url' => nammu_fediverse_thread_page_url($itemId, $config),
        'original_url' => trim((string) ($canonicalItem['url'] ?? ($item['url'] ?? ''))),
        'summary' => $summary,
        'details' => $details,
        'replies' => $mergedReplies,
    ];
}

function nammu_fediverse_reaction_snapshot_for_targets(array $targetMap, array $config): array
{
    $normalizedTargetMap = [];
    foreach ($targetMap as $identifier => $targetKey) {
        $identifier = trim((string) $identifier);
        $targetKey = trim((string) $targetKey);
        if ($identifier === '' || $targetKey === '') {
            continue;
        }
        $normalizedTargetMap[$identifier] = $targetKey;
    }
    $targetMap = $normalizedTargetMap;
    if ($targetMap === []) {
        return [];
    }
    $result = [];
    $seen = [];
    $ensureTarget = static function (string $key) use (&$result): void {
        if (!isset($result[$key])) {
            $result[$key] = [
                'summary' => ['likes' => 0, 'shares' => 0],
                'details' => ['likes' => [], 'shares' => []],
            ];
        }
    };
    $addActor = static function (string $targetKey, string $bucket, array $actorEntry) use (&$result, &$seen, $ensureTarget): void {
        $ensureTarget($targetKey);
        $actorId = trim((string) ($actorEntry['id'] ?? ''));
        $dedupeKey = $bucket . '|' . ($actorId !== '' ? $actorId : sha1(json_encode($actorEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''));
        if (isset($seen[$targetKey][$dedupeKey])) {
            return;
        }
        $seen[$targetKey][$dedupeKey] = true;
        $result[$targetKey]['summary'][$bucket]++;
        $result[$targetKey]['details'][$bucket][] = $actorEntry;
    };

    $inboxStore = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    foreach ((array) ($inboxStore['activities'] ?? []) as $entry) {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        if (!in_array($type, ['like', 'announce'], true)) {
            continue;
        }
        $object = $payload['object'] ?? null;
        $targetCandidates = [];
        if (is_string($object)) {
            $targetCandidates[] = trim($object);
        } elseif (is_array($object)) {
            foreach (['id', 'url'] as $field) {
                $candidate = nammu_fediverse_extract_url($object[$field] ?? '');
                if ($candidate !== '') {
                    $targetCandidates[] = $candidate;
                }
            }
        }
        $targetKey = '';
        foreach (array_values(array_unique(array_filter($targetCandidates))) as $targetCandidate) {
            if (isset($targetMap[$targetCandidate])) {
                $targetKey = $targetMap[$targetCandidate];
                break;
            }
        }
        if ($targetKey === '') {
            continue;
        }
        $actorId = trim((string) ($payload['actor'] ?? ''));
        $actor = $actorId !== '' ? nammu_fediverse_resolve_actor($actorId, $config) : [];
        $addActor($targetKey, $type === 'like' ? 'likes' : 'shares', [
            'id' => $actorId,
            'name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? '') ?: $actorId)),
            'icon' => trim((string) ($actor['icon'] ?? '')),
            'url' => trim((string) (($actor['url'] ?? '') ?: ($actor['id'] ?? '') ?: $actorId)),
            'published' => trim((string) (($payload['published'] ?? '') ?: ($entry['received_at'] ?? ''))),
        ]);
    }

    $localActorId = nammu_fediverse_actor_url($config);
    $localActorEntry = [
        'id' => $localActorId,
        'name' => trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog')),
        'icon' => trim((string) nammu_fediverse_avatar_url($config)),
        'url' => trim((string) nammu_fediverse_profile_page_url($config)),
        'published' => '',
    ];
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        $type = strtolower(trim((string) ($action['type'] ?? '')));
        if (!in_array($type, ['like', 'share', 'boost'], true)) {
            continue;
        }
        $target = trim((string) ($action['object_url'] ?? ''));
        $targetKey = $targetMap[$target] ?? '';
        if ($targetKey === '') {
            continue;
        }
        $entry = $localActorEntry;
        $entry['published'] = trim((string) ($action['published'] ?? ''));
        $addActor($targetKey, $type === 'like' ? 'likes' : 'shares', $entry);
    }

    foreach (nammu_fediverse_timeline_store()['items'] as $timelineItem) {
        if (!is_array($timelineItem)) {
            continue;
        }
        $type = strtolower(trim((string) ($timelineItem['type'] ?? '')));
        if (!in_array($type, ['like', 'announce'], true)) {
            continue;
        }
        $targetKey = '';
        foreach (['object_id', 'target_url'] as $field) {
            $value = trim((string) ($timelineItem[$field] ?? ''));
            if ($value !== '' && isset($targetMap[$value])) {
                $targetKey = $targetMap[$value];
                break;
            }
        }
        if ($targetKey === '') {
            continue;
        }
        $addActor($targetKey, $type === 'like' ? 'likes' : 'shares', [
            'id' => trim((string) ($timelineItem['actor_id'] ?? '')),
            'name' => trim((string) (($timelineItem['actor_name'] ?? '') ?: ($timelineItem['actor_username'] ?? '') ?: ($timelineItem['actor_id'] ?? ''))),
            'icon' => trim((string) ($timelineItem['actor_icon'] ?? '')),
            'url' => trim((string) (($timelineItem['actor_url'] ?? '') ?: ($timelineItem['actor_id'] ?? ''))),
            'published' => trim((string) ($timelineItem['published'] ?? '')),
        ]);
    }

    return $result;
}

function nammu_fediverse_thread_page_snapshot_payload(array $item, array $config): ?array
{
    $canonicalItem = nammu_fediverse_canonical_local_item($item, $config);
    $itemId = trim((string) ($canonicalItem['id'] ?? ($item['id'] ?? '')));
    if ($itemId === '') {
        return null;
    }
    $snapshot = nammu_fediverse_home_snapshot_store();
    $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
    $threadPayloads = is_array($data['thread_payloads'] ?? null) ? $data['thread_payloads'] : [];
    $payload = is_array($threadPayloads[$itemId] ?? null) ? $threadPayloads[$itemId] : null;
    if (!is_array($payload)) {
        return null;
    }
    return $payload;
}

function nammu_fediverse_best_thread_page_payload(array $item, array $config): array
{
    $snapshotPayload = nammu_fediverse_thread_page_snapshot_payload($item, $config);
    $livePayload = nammu_fediverse_thread_page_payload($item, $config);
    if (!is_array($snapshotPayload)) {
        return $livePayload;
    }
    if (nammu_fediverse_thread_payload_score($livePayload) > nammu_fediverse_thread_payload_score($snapshotPayload)) {
        return $livePayload;
    }
    return $snapshotPayload;
}

function nammu_fediverse_extract_first_url_from_text(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (preg_match('#https?://[^\s<>"\')]+#iu', $text, $matches)) {
        return trim((string) ($matches[0] ?? ''));
    }
    return '';
}

function nammu_fediverse_reply_link_card_from_attachments(array $attachments): ?array
{
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $type = strtolower(trim((string) ($attachment['type'] ?? '')));
        $mediaType = strtolower(trim((string) ($attachment['media_type'] ?? '')));
        if ($type !== 'link' && $mediaType !== 'text/html') {
            continue;
        }
        $url = trim((string) ($attachment['url'] ?? ''));
        if ($url === '') {
            continue;
        }
        return [
            'url' => $url,
            'title' => trim((string) ($attachment['name'] ?? '')),
            'description' => trim((string) ($attachment['summary'] ?? '')),
            'image' => trim((string) ($attachment['image'] ?? '')),
        ];
    }
    return null;
}

function nammu_fediverse_resolve_url_like_actuality(string $candidate, string $baseUrl): string
{
    $candidate = trim($candidate);
    if ($candidate === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $candidate)) {
        return $candidate;
    }
    if (function_exists('nammu_actuality_resolve_url')) {
        return trim((string) nammu_actuality_resolve_url($candidate, $baseUrl));
    }
    return $candidate;
}

function nammu_fediverse_fetch_link_card(string $url, array $config, int $maxAge = 86400): ?array
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    $cacheStore = nammu_fediverse_link_cards_store();
    $cacheItems = is_array($cacheStore['items'] ?? null) ? $cacheStore['items'] : [];
    $cacheKey = sha1($url);
    $cached = is_array($cacheItems[$cacheKey] ?? null) ? $cacheItems[$cacheKey] : null;
    if (is_array($cached)) {
        $fetchedAt = (int) ($cached['fetched_at'] ?? 0);
        $card = is_array($cached['card'] ?? null) ? $cached['card'] : [];
        if ($fetchedAt > 0 && (time() - $fetchedAt) <= $maxAge && !empty($card)) {
            return $card;
        }
    }
    if (nammu_fediverse_should_shared_cache_remote_url($url, $config)) {
        $shared = nammu_fediverse_shared_cache_read($config, 'link-card', $url, $maxAge);
        $sharedCard = is_array($shared['card'] ?? null) ? $shared['card'] : [];
        if (!empty($sharedCard)) {
            $cacheItems[$cacheKey] = [
                'fetched_at' => (int) ($shared['fetched_at'] ?? time()),
                'card' => $sharedCard,
            ];
            nammu_fediverse_save_link_cards_store($cacheItems);
            return $sharedCard;
        }
    }

    if (!function_exists('nammu_actuality_fetch_url') && is_file(dirname(__DIR__) . '/core/actualidad.php')) {
        require_once dirname(__DIR__) . '/core/actualidad.php';
    }

    $response = function_exists('nammu_actuality_fetch_url')
        ? nammu_actuality_fetch_url($url, 'text/html,application/xhtml+xml', 8, $config)
        : ['body' => @file_get_contents($url) ?: '', 'headers' => []];
    $html = trim((string) ($response['body'] ?? ''));
    if ($html === '') {
        return null;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!@$dom->loadHTML($html)) {
        return null;
    }
    $xpath = new DOMXPath($dom);
    $readMeta = static function (DOMXPath $xpath, array $queries): string {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes instanceof DOMNodeList && $nodes->length > 0) {
                $value = trim((string) $nodes->item(0)?->nodeValue);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    };

    $title = $readMeta($xpath, [
        '//meta[@property="og:title"]/@content',
        '//meta[@name="twitter:title"]/@content',
        '//title/text()',
    ]);
    $description = $readMeta($xpath, [
        '//meta[@property="og:description"]/@content',
        '//meta[@name="description"]/@content',
        '//meta[@name="twitter:description"]/@content',
    ]);
    $image = $readMeta($xpath, [
        '//meta[@property="og:image"]/@content',
        '//meta[@name="twitter:image"]/@content',
        '//meta[@name="twitter:image:src"]/@content',
    ]);
    $image = nammu_fediverse_resolve_url_like_actuality($image, $url);

    if ($title === '' && $description === '' && $image === '') {
        return null;
    }
    $card = [
        'url' => $url,
        'title' => $title,
        'description' => $description,
        'image' => $image,
    ];
    $cacheItems[$cacheKey] = [
        'fetched_at' => time(),
        'card' => $card,
    ];
    if (count($cacheItems) > 500) {
        uasort($cacheItems, static function (array $a, array $b): int {
            return ((int) ($b['fetched_at'] ?? 0)) <=> ((int) ($a['fetched_at'] ?? 0));
        });
        $cacheItems = array_slice($cacheItems, 0, 500, true);
    }
    nammu_fediverse_save_link_cards_store($cacheItems);
    if (nammu_fediverse_should_shared_cache_remote_url($url, $config)) {
        nammu_fediverse_shared_cache_write($config, 'link-card', $url, [
            'fetched_at' => time(),
            'card' => $card,
        ]);
    }
    return $card;
}

function nammu_fediverse_reply_link_card(array $reply, array $config): ?array
{
    $existing = is_array($reply['link_card'] ?? null) ? $reply['link_card'] : null;
    if (is_array($existing) && trim((string) ($existing['url'] ?? '')) !== '') {
        return $existing;
    }
    $fromAttachments = nammu_fediverse_reply_link_card_from_attachments((array) ($reply['attachments'] ?? []));
    if ($fromAttachments !== null) {
        return $fromAttachments;
    }
    $textUrl = nammu_fediverse_extract_first_url_from_text((string) ($reply['reply_text'] ?? ''));
    if ($textUrl === '') {
        return null;
    }
    return nammu_fediverse_fetch_link_card($textUrl, $config);
}

function nammu_fediverse_reply_collection_url(string $objectId, array $config): string
{
    return rtrim(nammu_fediverse_base_url($config), '/') . '/ap/replies/' . nammu_fediverse_reply_collection_hash($objectId);
}

function nammu_fediverse_reply_collection_page_url(string $objectId, array $config): string
{
    return nammu_fediverse_reply_collection_url($objectId, $config) . '?page=true';
}

function nammu_fediverse_reply_collection_summary(string $objectId, array $config): array
{
    $collectionUrl = nammu_fediverse_reply_collection_url($objectId, $config);
    $pageUrl = nammu_fediverse_reply_collection_page_url($objectId, $config);
    $targetIdentifiers = [$objectId];
    $localItem = nammu_fediverse_find_local_item_for_identifier($objectId, $config);
    if (is_array($localItem)) {
        foreach (['id', 'url'] as $field) {
            $value = trim((string) ($localItem[$field] ?? ''));
            if ($value !== '') {
                $targetIdentifiers[] = $value;
            }
        }
    }
    $targetIdentifiers = array_values(array_unique($targetIdentifiers));
    $replyIds = [];
    foreach (nammu_fediverse_public_replies_for_targets($targetIdentifiers) as $reply) {
        $replyId = trim((string) (($reply['note_id'] ?? '') ?: ($reply['id'] ?? '')));
        if ($replyId !== '') {
            $replyIds[$replyId] = true;
        }
    }
    foreach (nammu_fediverse_incoming_public_replies_by_object($config) as $localId => $replies) {
        foreach ((array) $replies as $reply) {
            $targetUrl = trim((string) ($reply['target_url'] ?? ''));
            if ($targetUrl === '' || !in_array($targetUrl, $targetIdentifiers, true)) {
                continue;
            }
            $replyId = trim((string) (($reply['url'] ?? '') ?: ($reply['id'] ?? '')));
            if ($replyId !== '') {
                $replyIds[$replyId] = true;
            }
        }
    }
    $totalItems = count($replyIds);
    return [
        'id' => $collectionUrl,
        'type' => 'Collection',
        'totalItems' => $totalItems,
        'first' => [
            'id' => $pageUrl,
            'type' => 'CollectionPage',
            'partOf' => $collectionUrl,
            'items' => [],
        ],
    ];
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
    $objectActorUsername = '';
    $objectActorIcon = '';
    $canDeriveThreadUrl = true;
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
        if ($objectId !== '') {
            $objectHost = parse_url($objectId, PHP_URL_HOST);
            $localHost = parse_url(nammu_fediverse_base_url($config), PHP_URL_HOST);
            $objectPath = trim((string) (parse_url($objectId, PHP_URL_PATH) ?? ''));
            if (
                is_string($objectHost)
                && is_string($localHost)
                && $objectHost !== ''
                && $localHost !== ''
                && strcasecmp($objectHost, $localHost) === 0
                && preg_match('#^/ap/objects/#', $objectPath)
                && !is_array(nammu_fediverse_find_local_item_for_identifier($objectId, $config))
            ) {
                $canDeriveThreadUrl = false;
            }
        }
        $objectUrl = nammu_fediverse_extract_url($object['url'] ?? '');
        if ($objectUrl === '' && $objectId !== '') {
            $object['url'] = $canDeriveThreadUrl ? nammu_fediverse_remote_thread_page_url($objectId) : '';
            if (nammu_fediverse_extract_url($object['url'] ?? '') === '') {
                $object['url'] = $objectId;
            }
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
                $objectActorUsername = trim((string) ($objectActor['preferredUsername'] ?? ''));
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
    if (($url === '' || $url === $objectId) && $objectId !== '') {
        $threadUrl = $canDeriveThreadUrl ? nammu_fediverse_remote_thread_page_url($objectId) : '';
        if ($threadUrl !== '') {
            $url = $threadUrl;
        }
    }
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
        'target_url' => trim((string) ($object['inReplyTo'] ?? '')),
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
        'target_actor_username' => $objectActorUsername !== '' ? $objectActorUsername : trim((string) ($actor['preferredUsername'] ?? '')),
        'target_actor_icon' => $objectActorIcon !== '' ? $objectActorIcon : trim((string) ($actor['icon'] ?? '')),
    ];
}

function nammu_fediverse_extract_outbox_items(
    array $outbox,
    array $actor,
    array $config,
    int $limit = 8,
    array $knownTimelineIds = [],
    ?int $inspectLimit = null
): array
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
    $topLevelItems = [];
    $otherItems = [];
    $knownTimelineIds = array_fill_keys(array_values(array_filter(array_map('strval', $knownTimelineIds))), true);
    $inspectLimit = $inspectLimit !== null ? max(1, $inspectLimit) : max($limit * 6, 24);
    $inspected = 0;
    foreach ($rawItems as $rawItem) {
        if ($inspected >= $inspectLimit) {
            break;
        }
        $inspected++;
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
            $normalizedId = trim((string) ($normalized['id'] ?? ''));
            if ($normalizedId !== '' && isset($knownTimelineIds[$normalizedId])) {
                continue;
            }
            $isTopLevelPublication = trim((string) ($normalized['target_url'] ?? '')) === ''
                && strtolower(trim((string) ($normalized['type'] ?? ''))) !== 'announce';
            if ($isTopLevelPublication) {
                $topLevelItems[] = $normalized;
            } else {
                $otherItems[] = $normalized;
            }
        }
    }
    if (!empty($topLevelItems)) {
        $items[] = array_shift($topLevelItems);
    }
    foreach ($topLevelItems as $candidate) {
        if (count($items) >= max(1, $limit)) {
            break;
        }
        $items[] = $candidate;
    }
    foreach ($otherItems as $candidate) {
        if (count($items) >= max(1, $limit)) {
            break;
        }
        $items[] = $candidate;
    }
    return $items;
}

function nammu_fediverse_refresh_following(array $options = []): array
{
    $config = function_exists('nammu_load_config') ? nammu_load_config() : [];
    $store = nammu_fediverse_following_store();
    $actors = $store['actors'];
    $timeline = nammu_fediverse_timeline_store()['items'];
    $timelineById = [];
    foreach ($timeline as $item) {
        $timelineById[(string) ($item['id'] ?? '')] = $item;
    }
    usort($actors, static function (array $a, array $b): int {
        $checkedA = trim((string) ($a['last_checked_at'] ?? ''));
        $checkedB = trim((string) ($b['last_checked_at'] ?? ''));
        return strcmp($checkedA, $checkedB);
    });
    $actorLimit = max(1, (int) ($options['actor_limit'] ?? count($actors)));
    $outboxLimit = max(1, (int) ($options['outbox_limit'] ?? 8));
    $outboxInspectLimit = max($outboxLimit, (int) ($options['outbox_inspect_limit'] ?? max($outboxLimit * 6, 24)));
    $refreshFollowers = !array_key_exists('refresh_followers', $options) || !empty($options['refresh_followers']);
    $resolveActorTtl = max(0, (int) ($options['resolve_actor_ttl'] ?? 3600));
    $checked = 0;
    $newItems = 0;
    foreach ($actors as $actorIndex => &$actor) {
        if ($checked >= $actorLimit) {
            break;
        }
        $checked++;
        $actor['last_checked_at'] = gmdate(DATE_ATOM);
        $actorDoc = [];
        $lastResolvedAt = strtotime((string) ($actor['resolved_at'] ?? '')) ?: 0;
        $storedOutbox = trim((string) ($actor['outbox'] ?? ''));
        if ($storedOutbox !== '' && $resolveActorTtl > 0 && $lastResolvedAt > 0 && (time() - $lastResolvedAt) < $resolveActorTtl) {
            $actorDoc = $actor;
        } else {
            $actorDoc = nammu_fediverse_resolve_actor((string) ($actor['id'] ?? ''), $config);
        }
        if (!is_array($actorDoc) || trim((string) ($actorDoc['outbox'] ?? '')) === '') {
            $actor['last_error'] = 'No se pudo refrescar el actor remoto.';
            continue;
        }
        $actor = array_merge($actor, $actorDoc);
        $actor['resolved_at'] = gmdate(DATE_ATOM);
        $outbox = nammu_fediverse_signed_fetch_json((string) $actor['outbox'], $config);
        if (!is_array($outbox)) {
            $actor['last_error'] = 'No se pudo leer su outbox.';
            continue;
        }
        $actor['last_error'] = '';
        foreach (nammu_fediverse_extract_outbox_items($outbox, $actor, $config, $outboxLimit, array_keys($timelineById), $outboxInspectLimit) as $item) {
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
    $followerStats = $refreshFollowers ? nammu_fediverse_refresh_followers($config) : ['checked' => 0, 'removed' => 0];
    return [
        'checked' => $checked,
        'new' => $newItems,
        'followers_checked' => (int) ($followerStats['checked'] ?? 0),
        'followers_removed' => (int) ($followerStats['removed'] ?? 0),
    ];
}

function nammu_fediverse_sync_recent_followed_inbox_items(array $config, int $limit = 6, int $scanLimit = 60): array
{
    $limit = max(1, $limit);
    $scanLimit = max($limit, $scanLimit);
    $followingActors = nammu_fediverse_following_store()['actors'];
    $followingIds = [];
    foreach ($followingActors as $actor) {
        $actorId = trim((string) ($actor['id'] ?? ''));
        if ($actorId !== '') {
            $followingIds[$actorId] = $actor;
        }
    }
    if (empty($followingIds)) {
        return ['scanned' => 0, 'new' => 0];
    }

    $timelineStore = nammu_fediverse_timeline_store();
    $timelineItems = is_array($timelineStore['items'] ?? null) ? $timelineStore['items'] : [];
    $timelineById = [];
    foreach ($timelineItems as $item) {
        $itemId = trim((string) ($item['id'] ?? ''));
        if ($itemId !== '') {
            $timelineById[$itemId] = $item;
        }
    }

    $inboxStore = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($inboxStore['activities'] ?? null) ? $inboxStore['activities'] : [];
    $activities = array_reverse($activities);
    $scanned = 0;
    $newItems = 0;

    foreach ($activities as $entry) {
        if ($scanned >= $scanLimit || $newItems >= $limit) {
            break;
        }
        $scanned++;
        if (empty($entry['verified'])) {
            continue;
        }
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $actorId = trim((string) ($payload['actor'] ?? ''));
        if ($actorId === '' || !isset($followingIds[$actorId])) {
            continue;
        }
        if (nammu_fediverse_is_direct_message_activity($payload, $config)) {
            continue;
        }
        $normalized = nammu_fediverse_normalize_remote_item($payload, $followingIds[$actorId], $config);
        if ($normalized === null) {
            continue;
        }
        $normalizedId = trim((string) ($normalized['id'] ?? ''));
        if ($normalizedId === '' || isset($timelineById[$normalizedId])) {
            continue;
        }
        $timelineById[$normalizedId] = $normalized;
        $newItems++;
    }

    if ($newItems > 0) {
        nammu_fediverse_save_timeline_store(array_values($timelineById));
    }

    return ['scanned' => $scanned, 'new' => $newItems];
}

function nammu_fediverse_refresh_followers(array $config): array
{
    $followers = nammu_fediverse_followers_store()['followers'];
    $checked = 0;
    $removed = 0;
    $kept = [];
    foreach ($followers as $follower) {
        $actorId = trim((string) ($follower['id'] ?? ''));
        if ($actorId === '') {
            continue;
        }
        $checked++;
        $follower['last_checked_at'] = gmdate(DATE_ATOM);
        $actorStatus = nammu_fediverse_fetch_actor_document_status($actorId, $config);
        $status = (int) ($actorStatus['status'] ?? 0);
        $body = is_array($actorStatus['body'] ?? null) ? $actorStatus['body'] : null;
        $bodyType = strtolower(trim((string) ($body['type'] ?? '')));
        if (in_array($status, [404, 410], true) || $bodyType === 'tombstone') {
            $removed++;
            continue;
        }
        $actorDoc = nammu_fediverse_resolve_actor($actorId, $config);
        if (!is_array($actorDoc)) {
            $failureCount = (int) ($follower['refresh_failures'] ?? 0) + 1;
            $follower['refresh_failures'] = $failureCount;
            $follower['last_error'] = 'No se pudo refrescar el seguidor remoto.';
            if ($failureCount >= 3) {
                $removed++;
                continue;
            }
            $kept[] = $follower;
            continue;
        }
        $follower = array_merge($follower, $actorDoc);
        $follower['refresh_failures'] = 0;
        $follower['last_error'] = '';
        $kept[] = $follower;
    }
    nammu_fediverse_save_followers_store($kept);
    return ['checked' => $checked, 'removed' => $removed];
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
    static $cache = [];
    $cacheKey = md5(nammu_fediverse_base_url($config));
    if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    $baseUrl = nammu_fediverse_base_url($config);
    $deletedIds = array_fill_keys(nammu_fediverse_deleted_store()['ids'], true);
    $legacyStore = nammu_fediverse_legacy_actuality_store();
    if (empty((array) ($legacyStore['items'] ?? []))) {
        nammu_fediverse_sync_legacy_actuality_from_inbox($config);
        $legacyStore = nammu_fediverse_legacy_actuality_store();
    }
    $legacyAliases = is_array((nammu_fediverse_legacy_actuality_aliases_store()['map'] ?? null)) ? nammu_fediverse_legacy_actuality_aliases_store()['map'] : [];
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
        $aliasIds = nammu_fediverse_legacy_actuality_alias_ids($item, $baseUrl);
        foreach ($legacyAliases as $legacyId => $currentId) {
            if (trim((string) $currentId) === $itemId) {
                $aliasIds[] = trim((string) $legacyId);
            }
        }
        $items[] = [
            'id' => $itemId,
            'url' => trim((string) (($item['link'] ?? '') ?: ($baseUrl . '/actualidad.php'))),
            'title' => $title !== '' ? $title : ($isManual ? '' : 'Noticia'),
            'content' => $content,
            'summary' => trim((string) ($item['description'] ?? '')),
            'published' => gmdate(DATE_ATOM, (int) (($item['timestamp'] ?? 0) ?: time())),
            'type' => $isManual ? 'Note' : 'Article',
            'image' => trim((string) (($item['source_image'] ?? '') ?: ($item['image'] ?? ''))),
            'images' => array_values(array_filter(array_map('strval', is_array($item['images'] ?? null) ? $item['images'] : []))),
            'alias_ids' => $aliasIds,
        ];
    }
    $knownIds = [];
    foreach ($items as $item) {
        $itemId = trim((string) ($item['id'] ?? ''));
        if ($itemId !== '') {
            $knownIds[$itemId] = true;
        }
        foreach (array_values(array_filter(array_map('strval', is_array($item['alias_ids'] ?? null) ? $item['alias_ids'] : []))) as $aliasId) {
            $aliasId = trim($aliasId);
            if ($aliasId !== '') {
                $knownIds[$aliasId] = true;
            }
        }
    }
    foreach ((array) ($legacyStore['items'] ?? []) as $legacyItem) {
        if (!is_array($legacyItem)) {
            continue;
        }
        $itemId = trim((string) ($legacyItem['id'] ?? ''));
        if ($itemId === '' || isset($legacyAliases[$itemId]) || isset($knownIds[$itemId]) || isset($deletedIds[$itemId])) {
            continue;
        }
        $legacyTitle = trim((string) ($legacyItem['title'] ?? ''));
        $legacyContent = trim((string) ($legacyItem['content'] ?? ''));
        $legacyUrl = trim((string) ($legacyItem['url'] ?? ''));
        $legacyImage = trim((string) ($legacyItem['image'] ?? ''));
        if ($legacyTitle === '' && $legacyContent === '' && $legacyUrl === '' && $legacyImage === '') {
            continue;
        }
        $knownIds[$itemId] = true;
        $items[] = [
            'id' => $itemId,
            'url' => $legacyUrl !== '' ? $legacyUrl : nammu_fediverse_thread_page_url($itemId, $config),
            'title' => $legacyTitle !== '' ? $legacyTitle : 'Noticia',
            'content' => $legacyContent,
            'summary' => $legacyContent,
            'published' => trim((string) ($legacyItem['published'] ?? '')) ?: gmdate(DATE_ATOM),
            'type' => 'Article',
            'image' => $legacyImage,
            'images' => [],
        ];
    }
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
    });
    $cache[$cacheKey] = $items;
    return $items;
}

function nammu_fediverse_local_items_index(array $config): array
{
    $byIdentifier = [];
    $registerItem = static function (array $item, bool $overwrite = false) use (&$byIdentifier, $config): void {
        $identifiers = [];
        foreach (['id', 'url'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                $identifiers[] = $value;
            }
        }
        foreach (nammu_fediverse_local_item_alias_identifiers($item, $config) as $aliasIdentifier) {
            $identifiers[] = $aliasIdentifier;
        }
        foreach (array_unique($identifiers) as $identifier) {
            if (!$overwrite && isset($byIdentifier[$identifier])) {
                continue;
            }
            $byIdentifier[$identifier] = $item;
        }
    };
    foreach (nammu_fediverse_local_content_items($config) as $item) {
        $registerItem($item, true);
    }
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        $resendItem = nammu_fediverse_resend_item_from_action($action);
        if (is_array($resendItem)) {
            $registerItem($resendItem, false);
        }
    }
    return $byIdentifier;
}

function nammu_fediverse_primary_local_item_by_url(string $url, array $config): ?array
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    foreach (nammu_fediverse_local_content_items($config) as $item) {
        if (trim((string) ($item['url'] ?? '')) === $url) {
            return $item;
        }
    }
    return null;
}

function nammu_fediverse_equivalent_local_items_by_url(string $url, array $config): array
{
    $url = trim($url);
    if ($url === '') {
        return [];
    }
    $items = [];
    foreach (nammu_fediverse_local_content_items($config) as $item) {
        if (trim((string) ($item['url'] ?? '')) === $url) {
            $items[] = $item;
        }
    }
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        $resendItem = nammu_fediverse_resend_item_from_action($action);
        if (!is_array($resendItem)) {
            continue;
        }
        if (trim((string) ($resendItem['url'] ?? '')) === $url) {
            $items[] = $resendItem;
        }
    }
    $unique = [];
    foreach ($items as $item) {
        $itemId = trim((string) ($item['id'] ?? ''));
        $key = $itemId !== '' ? $itemId : sha1(json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $unique[$key] = $item;
    }
    return array_values($unique);
}

function nammu_fediverse_canonical_local_item(array $item, array $config): array
{
    $url = trim((string) ($item['url'] ?? ''));
    if ($url !== '') {
        $primary = nammu_fediverse_primary_local_item_by_url($url, $config);
        if (is_array($primary) && trim((string) ($primary['id'] ?? '')) !== '') {
            return $primary;
        }
    }
    return $item;
}

function nammu_fediverse_item_identifiers(array $item): array
{
    $identifiers = [];
    foreach (['id', 'url'] as $field) {
        $value = trim((string) ($item[$field] ?? ''));
        if ($value !== '') {
            $identifiers[] = $value;
        }
    }
    return array_values(array_unique($identifiers));
}

function nammu_fediverse_item_identifiers_with_canonical(array $item, array $config): array
{
    $identifiers = nammu_fediverse_item_identifiers($item);
    $canonicalItem = nammu_fediverse_canonical_local_item($item, $config);
    foreach (nammu_fediverse_item_identifiers($canonicalItem) as $identifier) {
        $identifiers[] = $identifier;
    }
    $url = trim((string) (($canonicalItem['url'] ?? '') ?: ($item['url'] ?? '')));
    foreach (nammu_fediverse_equivalent_local_items_by_url($url, $config) as $equivalentItem) {
        foreach (nammu_fediverse_item_identifiers($equivalentItem) as $identifier) {
            $identifiers[] = $identifier;
        }
    }
    return array_values(array_unique($identifiers));
}

function nammu_fediverse_canonical_local_id_for_identifier(string $identifier, array $config): string
{
    $item = nammu_fediverse_find_local_item_for_identifier($identifier, $config);
    if (!is_array($item)) {
        return '';
    }
    $canonicalItem = nammu_fediverse_canonical_local_item($item, $config);
    return trim((string) ($canonicalItem['id'] ?? ''));
}

function nammu_fediverse_find_local_item_for_identifier(string $identifier, array $config): ?array
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }
    $index = nammu_fediverse_local_items_index($config);
    $item = $index[$identifier] ?? null;
    return is_array($item) ? $item : null;
}

function nammu_fediverse_public_url_for_local_identifier(string $identifier, array $config): string
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return '';
    }
    $canonicalId = nammu_fediverse_canonical_local_id_for_identifier($identifier, $config);
    if ($canonicalId === '') {
        $canonicalId = $identifier;
    }
    $item = nammu_fediverse_find_local_item_for_identifier($canonicalId, $config);
    if (!is_array($item)) {
        $item = nammu_fediverse_find_local_item_for_identifier($identifier, $config);
    }
    if (preg_match('#/ap/objects/(post|podcast|itinerary)-#', $canonicalId) === 1) {
        $itemUrl = trim((string) ($item['url'] ?? ''));
        if ($itemUrl !== '') {
            return $itemUrl;
        }
    }
    if (str_contains($canonicalId, '/ap/objects/')) {
        return nammu_fediverse_thread_page_url($canonicalId, $config);
    }
    return trim((string) ($item['url'] ?? ''));
}

function nammu_fediverse_local_reaction_summary(array $config): array
{
    $index = nammu_fediverse_local_items_index($config);
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    $hiddenLookup = nammu_fediverse_hidden_reply_lookup();
    $summary = [];
    $seenActors = [];
    $seenReplies = [];
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
        if ($type === 'create' && is_array($object)) {
            $replyEntry = [
                'id' => trim((string) (($object['id'] ?? '') ?: ($payload['id'] ?? ''))),
                'url' => trim((string) ($object['url'] ?? '')),
                'published' => trim((string) (($object['published'] ?? '') ?: ($payload['published'] ?? '') ?: ($entry['received_at'] ?? ''))),
                'reply_text' => trim((string) (function_exists('nammu_fediverse_html_to_text') ? nammu_fediverse_html_to_text((string) ($object['content'] ?? '')) : strip_tags((string) ($object['content'] ?? '')))),
                'actor_id' => trim((string) ($payload['actor'] ?? '')),
            ];
            if (nammu_fediverse_is_hidden_reply($replyEntry, $hiddenLookup)) {
                continue;
            }
        }
        $localItem = nammu_fediverse_canonical_local_item($index[$target], $config);
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
        $actorId = trim((string) ($payload['actor'] ?? ''));
        if ($type === 'like') {
            $key = 'likes|' . ($actorId !== '' ? $actorId : sha1(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''));
            if (!isset($seenActors[$localId][$key])) {
                $seenActors[$localId][$key] = true;
                $summary[$localId]['likes']++;
            }
        } elseif ($type === 'announce') {
            $key = 'shares|' . ($actorId !== '' ? $actorId : sha1(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''));
            if (!isset($seenActors[$localId][$key])) {
                $seenActors[$localId][$key] = true;
                $summary[$localId]['shares']++;
            }
        } elseif ($type === 'create') {
            $replyKeys = array_filter([
                trim((string) ($replyEntry['id'] ?? '')) !== '' ? 'id:' . trim((string) $replyEntry['id']) : '',
                trim((string) ($replyEntry['url'] ?? '')) !== '' ? 'id:' . trim((string) $replyEntry['url']) : '',
            ]);
            $replyFallback = strtolower(trim((string) ($replyEntry['actor_id'] ?? ''))) . '|' .
                trim((string) ($replyEntry['published'] ?? '')) . '|' .
                trim((string) ($replyEntry['reply_text'] ?? ''));
            if ($replyFallback !== '||') {
                $replyKeys[] = 'fallback:' . $replyFallback;
            }
            $alreadySeenReply = false;
            foreach ($replyKeys as $replyKey) {
                if (isset($seenReplies[$localId][$replyKey])) {
                    $alreadySeenReply = true;
                    break;
                }
            }
            if (!$alreadySeenReply) {
                foreach ($replyKeys as $replyKey) {
                    $seenReplies[$localId][$replyKey] = true;
                }
                $summary[$localId]['replies']++;
            }
        }
    }
    foreach (nammu_fediverse_timeline_entries_targeting_local_items($config) as $timelineEntry) {
        $item = is_array($timelineEntry['item'] ?? null) ? $timelineEntry['item'] : [];
        $localItem = is_array($timelineEntry['canonical_item'] ?? null) ? $timelineEntry['canonical_item'] : [];
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
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        if ($type === 'announce') {
            $actorId = trim((string) ($item['actor_id'] ?? ''));
            $key = 'shares|' . ($actorId !== '' ? $actorId : sha1(json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''));
            if (!isset($seenActors[$localId][$key])) {
                $seenActors[$localId][$key] = true;
                $summary[$localId]['shares']++;
            }
            continue;
        }
        $replyEntry = [
            'id' => trim((string) (($item['id'] ?? '') ?: '')),
            'url' => trim((string) (($item['url'] ?? '') ?: '')),
            'published' => trim((string) (($item['published'] ?? '') ?: '')),
            'reply_text' => trim((string) (($item['content'] ?? '') ?: '')),
            'actor_id' => trim((string) (($item['actor_id'] ?? '') ?: '')),
        ];
        if ($replyEntry['reply_text'] === '' || nammu_fediverse_is_hidden_reply($replyEntry, $hiddenLookup)) {
            continue;
        }
        $replyKeys = array_filter([
            trim((string) ($replyEntry['id'] ?? '')) !== '' ? 'id:' . trim((string) $replyEntry['id']) : '',
            trim((string) ($replyEntry['url'] ?? '')) !== '' ? 'id:' . trim((string) $replyEntry['url']) : '',
        ]);
        $replyFallback = strtolower(trim((string) ($replyEntry['actor_id'] ?? ''))) . '|' .
            trim((string) ($replyEntry['published'] ?? '')) . '|' .
            trim((string) ($replyEntry['reply_text'] ?? ''));
        if ($replyFallback !== '||') {
            $replyKeys[] = 'fallback:' . $replyFallback;
        }
        $alreadySeenReply = false;
        foreach ($replyKeys as $replyKey) {
            if (isset($seenReplies[$localId][$replyKey])) {
                $alreadySeenReply = true;
                break;
            }
        }
        if ($alreadySeenReply) {
            continue;
        }
        foreach ($replyKeys as $replyKey) {
            $seenReplies[$localId][$replyKey] = true;
        }
        $summary[$localId]['replies']++;
    }
    $localActorId = nammu_fediverse_actor_url($config);
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        $type = strtolower(trim((string) ($action['type'] ?? '')));
        if (!in_array($type, ['like', 'share', 'boost'], true)) {
            continue;
        }
        $target = trim((string) ($action['object_url'] ?? ''));
        if ($target === '') {
            continue;
        }
        if (!isset($index[$target])) {
            $canonicalTarget = nammu_fediverse_canonical_local_id_for_identifier($target, $config);
            if ($canonicalTarget !== '' && isset($index[$canonicalTarget])) {
                $target = $canonicalTarget;
            }
        }
        if (!isset($index[$target])) {
            continue;
        }
        $localItem = nammu_fediverse_canonical_local_item($index[$target], $config);
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
        $bucket = $type === 'like' ? 'likes' : 'shares';
        $key = $bucket . '|' . $localActorId;
        if (isset($seenActors[$localId][$key])) {
            continue;
        }
        $seenActors[$localId][$key] = true;
        $summary[$localId][$bucket]++;
    }
    return $summary;
}

function nammu_fediverse_local_reaction_details(array $config): array
{
    $index = nammu_fediverse_local_items_index($config);
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    $hiddenLookup = nammu_fediverse_hidden_reply_lookup();
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
        if ($type === 'create' && is_array($object)) {
            $replyEntry = [
                'id' => trim((string) (($object['id'] ?? '') ?: ($payload['id'] ?? ''))),
                'url' => trim((string) ($object['url'] ?? '')),
                'published' => trim((string) (($object['published'] ?? '') ?: ($payload['published'] ?? '') ?: ($entry['received_at'] ?? ''))),
                'reply_text' => trim((string) (function_exists('nammu_fediverse_html_to_text') ? nammu_fediverse_html_to_text((string) ($object['content'] ?? '')) : strip_tags((string) ($object['content'] ?? '')))),
                'actor_id' => trim((string) ($payload['actor'] ?? '')),
            ];
            if (nammu_fediverse_is_hidden_reply($replyEntry, $hiddenLookup)) {
                continue;
            }
        }
        $canonicalItem = nammu_fediverse_canonical_local_item($index[$target], $config);
        $localId = trim((string) (($canonicalItem['id'] ?? '')));
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
    $localActorId = nammu_fediverse_actor_url($config);
    $localActorEntry = [
        'id' => $localActorId,
        'name' => trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog')),
        'icon' => trim((string) nammu_fediverse_avatar_url($config)),
        'url' => trim((string) nammu_fediverse_profile_page_url($config)),
        'published' => '',
    ];
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        $type = strtolower(trim((string) ($action['type'] ?? '')));
        if (!in_array($type, ['like', 'share', 'boost'], true)) {
            continue;
        }
        $target = trim((string) ($action['object_url'] ?? ''));
        if ($target === '') {
            continue;
        }
        if (!isset($index[$target])) {
            $canonicalTarget = nammu_fediverse_canonical_local_id_for_identifier($target, $config);
            if ($canonicalTarget !== '' && isset($index[$canonicalTarget])) {
                $target = $canonicalTarget;
            }
        }
        if (!isset($index[$target])) {
            continue;
        }
        $canonicalItem = nammu_fediverse_canonical_local_item($index[$target], $config);
        $localId = trim((string) ($canonicalItem['id'] ?? ''));
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
        $bucket = $type === 'like' ? 'likes' : 'shares';
        $alreadyPresent = false;
        foreach ($details[$localId][$bucket] as $existingActor) {
            if (trim((string) ($existingActor['id'] ?? '')) === $localActorId) {
                $alreadyPresent = true;
                break;
            }
        }
        if ($alreadyPresent) {
            continue;
        }
        $entry = $localActorEntry;
        $entry['published'] = trim((string) ($action['published'] ?? ''));
        $details[$localId][$bucket][] = $entry;
    }
    foreach (nammu_fediverse_timeline_entries_targeting_local_items($config) as $timelineEntry) {
        $item = is_array($timelineEntry['item'] ?? null) ? $timelineEntry['item'] : [];
        $canonicalItem = is_array($timelineEntry['canonical_item'] ?? null) ? $timelineEntry['canonical_item'] : [];
        $localId = trim((string) ($canonicalItem['id'] ?? ''));
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
        $actorId = trim((string) ($item['actor_id'] ?? ''));
        $actorEntry = [
            'id' => $actorId,
            'name' => trim((string) (($item['actor_name'] ?? '') ?: ($item['actor_username'] ?? '') ?: $actorId)),
            'icon' => trim((string) ($item['actor_icon'] ?? '')),
            'url' => trim((string) (($item['actor_url'] ?? '') ?: $actorId)),
            'published' => trim((string) ($item['published'] ?? '')),
        ];
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        if ($type === 'announce') {
            $bucket = 'shares';
        } else {
            $replyEntry = [
                'id' => trim((string) (($item['id'] ?? '') ?: '')),
                'url' => trim((string) (($item['url'] ?? '') ?: '')),
                'published' => trim((string) (($item['published'] ?? '') ?: '')),
                'reply_text' => trim((string) (($item['content'] ?? '') ?: '')),
                'actor_id' => $actorId,
            ];
            if ($replyEntry['reply_text'] === '' || nammu_fediverse_is_hidden_reply($replyEntry, $hiddenLookup)) {
                continue;
            }
            $bucket = 'replies';
        }
        $alreadyPresent = false;
        foreach ($details[$localId][$bucket] as $existingActor) {
            if (
                trim((string) ($existingActor['id'] ?? '')) !== ''
                && trim((string) ($existingActor['id'] ?? '')) === $actorId
            ) {
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
    $hiddenLookup = nammu_fediverse_hidden_reply_lookup();
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
        $canonicalItem = nammu_fediverse_canonical_local_item($localItem, $config);
        $localId = trim((string) ($canonicalItem['id'] ?? ''));
        if ($localId !== '') {
            $localTargetMap[(string) $identifier] = $localId;
        }
    }
    $replyRootMap = [];
    $pendingReplies = [];
    $grouped = [];
    $seenReplies = [];
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
        $attachments = [];
        $attachmentList = $object['attachment'] ?? [];
        if (is_array($attachmentList) && array_key_exists('type', $attachmentList)) {
            $attachmentList = [$attachmentList];
        }
        foreach ((array) $attachmentList as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $attachmentUrl = nammu_fediverse_extract_url($attachment['url'] ?? ($attachment['href'] ?? ''));
            if ($attachmentUrl === '') {
                continue;
            }
            $attachments[] = [
                'type' => strtolower(trim((string) ($attachment['type'] ?? ''))),
                'url' => $attachmentUrl,
                'name' => trim((string) ($attachment['name'] ?? '')),
                'media_type' => trim((string) (($attachment['mediaType'] ?? '') ?: ($attachment['mimeType'] ?? ''))),
                'image' => nammu_fediverse_extract_url($attachment['image'] ?? ''),
                'summary' => trim((string) ($attachment['summary'] ?? '')),
            ];
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
                'actor_username' => trim((string) ($actor['preferredUsername'] ?? '')),
                'actor_icon' => trim((string) ($actor['icon'] ?? '')),
                'attachments' => $attachments,
                'link_card' => nammu_fediverse_reply_link_card_from_attachments($attachments),
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
            $replyEntry = is_array($pendingReply['entry'] ?? null) ? $pendingReply['entry'] : [];
            if (nammu_fediverse_is_hidden_reply($replyEntry, $hiddenLookup)) {
                unset($pendingReplies[$pendingIndex]);
                continue;
            }
            $replyIdKey = trim((string) ($replyEntry['id'] ?? ''));
            $replyUrlKey = trim((string) ($replyEntry['url'] ?? ''));
            $replyFallbackKey = strtolower(trim((string) ($replyEntry['actor_id'] ?? ''))) . '|' .
                trim((string) ($replyEntry['published'] ?? '')) . '|' .
                trim((string) ($replyEntry['reply_text'] ?? ''));
            $dedupKeys = array_filter([
                $replyIdKey !== '' ? 'id:' . $replyIdKey : '',
                $replyUrlKey !== '' ? 'url:' . $replyUrlKey : '',
                $replyFallbackKey !== '||' ? 'fallback:' . $replyFallbackKey : '',
            ]);
            $alreadyPresent = false;
            foreach ($dedupKeys as $dedupKey) {
                if (isset($seenReplies[$localId][$dedupKey])) {
                    $alreadyPresent = true;
                    break;
                }
            }
            if (!$alreadyPresent) {
                $grouped[$localId][] = $replyEntry;
                foreach ($dedupKeys as $dedupKey) {
                    $seenReplies[$localId][$dedupKey] = true;
                }
            }
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
    foreach (nammu_fediverse_timeline_entries_targeting_local_items($config) as $timelineEntry) {
        $item = is_array($timelineEntry['item'] ?? null) ? $timelineEntry['item'] : [];
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        if ($type === 'announce') {
            continue;
        }
        $localItem = is_array($timelineEntry['canonical_item'] ?? null) ? $timelineEntry['canonical_item'] : [];
        $localId = trim((string) ($localItem['id'] ?? ''));
        if ($localId === '') {
            continue;
        }
        $replyEntry = [
            'id' => trim((string) (($item['id'] ?? '') ?: '')),
            'url' => trim((string) (($item['url'] ?? '') ?: '')),
            'target_url' => trim((string) (($timelineEntry['target'] ?? '') ?: ($item['target_url'] ?? ''))),
            'published' => trim((string) (($item['published'] ?? '') ?: '')),
            'reply_text' => trim((string) (($item['content'] ?? '') ?: '')),
            'actor_id' => trim((string) (($item['actor_id'] ?? '') ?: '')),
            'actor_name' => trim((string) (($item['actor_name'] ?? '') ?: ($item['actor_username'] ?? '') ?: ($item['actor_id'] ?? ''))),
            'actor_username' => trim((string) ($item['actor_username'] ?? '')),
            'actor_icon' => trim((string) ($item['actor_icon'] ?? '')),
            'attachments' => is_array($item['attachments'] ?? null) ? $item['attachments'] : [],
            'link_card' => nammu_fediverse_reply_link_card_from_attachments((array) ($item['attachments'] ?? [])),
            'verified' => true,
            'source' => 'incoming-remote',
        ];
        if ($replyEntry['reply_text'] === '' || nammu_fediverse_is_hidden_reply($replyEntry, $hiddenLookup)) {
            continue;
        }
        if (!isset($grouped[$localId])) {
            $grouped[$localId] = [];
        }
        $replyIdKey = trim((string) ($replyEntry['id'] ?? ''));
        $replyUrlKey = trim((string) ($replyEntry['url'] ?? ''));
        $replyFallbackKey = strtolower(trim((string) ($replyEntry['actor_id'] ?? ''))) . '|' .
            trim((string) ($replyEntry['published'] ?? '')) . '|' .
            trim((string) ($replyEntry['reply_text'] ?? ''));
        $dedupKeys = array_filter([
            $replyIdKey !== '' ? 'id:' . $replyIdKey : '',
            $replyUrlKey !== '' ? 'url:' . $replyUrlKey : '',
            $replyFallbackKey !== '||' ? 'fallback:' . $replyFallbackKey : '',
        ]);
        $alreadyPresent = false;
        foreach ($dedupKeys as $dedupKey) {
            if (isset($seenReplies[$localId][$dedupKey])) {
                $alreadyPresent = true;
                break;
            }
        }
        if ($alreadyPresent) {
            continue;
        }
        $grouped[$localId][] = $replyEntry;
        foreach ($dedupKeys as $dedupKey) {
            $seenReplies[$localId][$dedupKey] = true;
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
                'actor_username' => trim((string) ($reply['actor_username'] ?? '')),
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
            'actor_username' => nammu_fediverse_preferred_username($config),
            'actor_icon' => '',
            'direction' => 'outgoing',
            'content' => $content,
            'published' => trim((string) ($item['published'] ?? '')),
            'url' => '',
            'delivery_status' => 'delivered',
            'visibility' => 'public',
            'reply_target_url' => (function () use ($item, $config): string {
                $target = trim((string) ($item['object_url'] ?? ''));
                $canonical = $target !== '' ? nammu_fediverse_canonical_local_id_for_identifier($target, $config) : '';
                return $canonical !== '' ? $canonical : $target;
            })(),
        ];
    }
    return $messages;
}

function nammu_fediverse_remote_public_reply_message_entries(array $config): array
{
    $timelineItems = nammu_fediverse_timeline_store()['items'];
    $timelineIndex = [];
    foreach ($timelineItems as $timelineItem) {
        if (!is_array($timelineItem)) {
            continue;
        }
        foreach (['id', 'object_id', 'url'] as $field) {
            $value = trim((string) ($timelineItem[$field] ?? ''));
            if ($value !== '') {
                $timelineIndex[$value] = $timelineItem;
            }
        }
    }

    $messages = [];
    $seen = [];
    foreach (nammu_fediverse_outgoing_public_reply_message_entries($config) as $message) {
        $target = trim((string) ($message['reply_target_url'] ?? ''));
        if ($target === '' || !isset($timelineIndex[$target])) {
            continue;
        }
        $rootItem = $timelineIndex[$target];
        $replies = function_exists('nammu_fediverse_cached_remote_replies_snapshot_for_item')
            ? nammu_fediverse_cached_remote_replies_snapshot_for_item($rootItem)
            : [];
        if (empty($replies) && function_exists('nammu_fediverse_cached_remote_replies_for_item')) {
            $replies = nammu_fediverse_cached_remote_replies_for_item($rootItem, $config);
        }
        foreach ($replies as $reply) {
            if (!is_array($reply)) {
                continue;
            }
            $replyId = trim((string) ($reply['id'] ?? ''));
            $replyUrl = trim((string) ($reply['url'] ?? ''));
            $replyNoteId = trim((string) ($reply['note_id'] ?? ''));
            $replyFallback = strtolower(trim((string) ($reply['actor_id'] ?? ''))) . '|' .
                trim((string) ($reply['published'] ?? '')) . '|' .
                trim((string) ($reply['reply_text'] ?? ''));
            $replyKeys = array_filter([
                $replyId !== '' ? 'id:' . $replyId : '',
                $replyUrl !== '' ? 'url:' . $replyUrl : '',
                $replyNoteId !== '' ? 'note:' . $replyNoteId : '',
                $replyFallback !== '||' ? 'fallback:' . $replyFallback : '',
            ]);
            $alreadySeen = false;
            foreach ($replyKeys as $replyKey) {
                if (isset($seen[$target][$replyKey])) {
                    $alreadySeen = true;
                    break;
                }
            }
            if ($alreadySeen) {
                continue;
            }
            foreach ($replyKeys as $replyKey) {
                $seen[$target][$replyKey] = true;
            }
            $messages[] = [
                'id' => $replyId,
                'activity_id' => '',
                'actor_id' => trim((string) ($reply['actor_id'] ?? '')),
                'actor_name' => trim((string) ($reply['actor_name'] ?? ($reply['actor_id'] ?? ''))),
                'actor_username' => trim((string) ($reply['actor_username'] ?? '')),
                'actor_icon' => trim((string) ($reply['actor_icon'] ?? '')),
                'direction' => 'incoming',
                'content' => trim((string) ($reply['reply_text'] ?? '')),
                'published' => trim((string) ($reply['published'] ?? '')),
                'url' => trim((string) (($reply['url'] ?? '') ?: ($reply['id'] ?? ''))),
                'delivery_status' => '',
                'verified' => !empty($reply['verified']),
                'visibility' => 'public',
                'reply_target_url' => $target,
            ];
        }
    }

    return $messages;
}

function nammu_fediverse_remote_thread_root_message_entries(array $config): array
{
    $timelineItems = nammu_fediverse_timeline_store()['items'];
    $timelineIndex = [];
    foreach ($timelineItems as $timelineItem) {
        if (!is_array($timelineItem)) {
            continue;
        }
        foreach (['id', 'object_id', 'url'] as $field) {
            $value = trim((string) ($timelineItem[$field] ?? ''));
            if ($value !== '') {
                $timelineIndex[$value] = $timelineItem;
            }
        }
    }

    $messages = [];
    $seen = [];
    foreach (nammu_fediverse_outgoing_public_reply_message_entries($config) as $message) {
        $target = trim((string) ($message['reply_target_url'] ?? ''));
        if ($target === '' || !isset($timelineIndex[$target])) {
            continue;
        }
        $rootItem = $timelineIndex[$target];
        if (isset($seen[$target])) {
            continue;
        }
        $seen[$target] = true;
        $messages[] = [
            'id' => 'remote-root-' . substr(sha1($target), 0, 24),
            'activity_id' => '',
            'actor_id' => trim((string) (($rootItem['actor_id'] ?? '') ?: '')),
            'actor_name' => trim((string) (($rootItem['actor_name'] ?? '') ?: '')),
            'actor_username' => trim((string) ($rootItem['actor_username'] ?? '')),
            'actor_icon' => trim((string) (($rootItem['actor_icon'] ?? '') ?: '')),
            'direction' => 'incoming',
            'content' => trim((string) (($rootItem['content'] ?? '') ?: ($rootItem['title'] ?? ''))),
            'published' => trim((string) ($rootItem['published'] ?? '')),
            'url' => trim((string) (($rootItem['url'] ?? '') ?: ($rootItem['id'] ?? ''))),
            'delivery_status' => '',
            'verified' => true,
            'visibility' => 'public',
            'reply_target_url' => $target,
            'is_thread_root' => true,
            'content_type' => trim((string) ($rootItem['type'] ?? '')),
            'title' => trim((string) ($rootItem['title'] ?? '')),
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
        $target = trim((string) ($message['reply_target_url'] ?? ''));
        if ($target === '' || !isset($localIndex[$target])) {
            continue;
        }
        $localItem = $localIndex[$target];
        $localId = trim((string) ($localItem['id'] ?? ''));
        if ($localId === '') {
            continue;
        }
        if (isset($seen[$localId])) {
            continue;
        }
        $seen[$localId] = true;
        $messages[] = [
            'id' => 'local-root-' . substr(sha1($localId), 0, 24),
            'activity_id' => '',
            'actor_id' => trim((string) (($localItem['actor_id'] ?? '') ?: nammu_fediverse_actor_url($config))),
            'actor_name' => trim((string) (($config['site_name'] ?? '') ?: '')),
            'actor_username' => nammu_fediverse_preferred_username($config),
            'actor_icon' => trim((string) nammu_fediverse_avatar_url($config)),
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
    $followersUrl = nammu_fediverse_followers_url($config);
    $avatarUrl = nammu_fediverse_avatar_url($config);
    $objectType = (string) ($item['type'] ?? 'Article');
    $originalObjectUrl = (string) ($item['url'] ?? '');
    $objectUrl = nammu_fediverse_thread_page_url((string) ($item['id'] ?? ''), $config);
    $plainContent = trim((string) ($item['content'] ?? ''));
    if (strcasecmp($objectType, 'Note') === 0) {
        $contentHtml = nl2br(htmlspecialchars($plainContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    } else {
        $contentParts = [];
        if ($originalObjectUrl !== '') {
            $escapedUrl = htmlspecialchars($originalObjectUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [$followersUrl],
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
    $images = array_values(array_unique(array_filter(array_map('strval', is_array($item['images'] ?? null) ? $item['images'] : []))));
    if ($image !== '' && !in_array($image, $images, true)) {
        array_unshift($images, $image);
    }
    if ($image !== '') {
        $object['image'] = ['type' => 'Image', 'url' => $image];
    }
    if (strcasecmp($objectType, 'Note') !== 0 && $objectUrl !== '') {
        $linkAttachment = [
            'type' => 'Link',
            'href' => $originalObjectUrl !== '' ? $originalObjectUrl : $objectUrl,
            'mediaType' => 'text/html',
            'name' => trim((string) (($item['title'] ?? '') ?: ($originalObjectUrl !== '' ? $originalObjectUrl : $objectUrl))),
        ];
        if ($image !== '') {
            $linkAttachment['image'] = ['type' => 'Image', 'url' => $image];
        }
        if ($summary !== '') {
            $linkAttachment['summary'] = htmlspecialchars($summary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $attachments = [$linkAttachment];
        foreach ($images as $imageUrl) {
            $attachments[] = [
                'type' => 'Image',
                'mediaType' => 'image/*',
                'url' => $imageUrl,
                'name' => trim((string) ($item['title'] ?? '')),
            ];
        }
        $object['attachment'] = $attachments;
    } elseif (!empty($images)) {
        $object['attachment'] = array_map(static function (string $imageUrl) use ($item): array {
            return [
                'type' => 'Image',
                'mediaType' => 'image/*',
                'url' => $imageUrl,
                'name' => trim((string) ($item['title'] ?? '')),
            ];
        }, $images);
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
        'cc' => [$followersUrl],
        'object' => $object,
    ];
}

function nammu_fediverse_deliver_activity_to_followers(array $activity, array $config): int
{
    $followers = nammu_fediverse_followers_store()['followers'];
    if (empty($followers)) {
        return 0;
    }
    $delivered = 0;
    foreach ($followers as $follower) {
        $followerId = trim((string) ($follower['id'] ?? ''));
        if ($followerId !== '' && nammu_fediverse_is_blocked_actor($followerId)) {
            continue;
        }
        $inboxUrl = nammu_fediverse_remote_inbox_for_actor($follower);
        if ($inboxUrl === '') {
            continue;
        }
        if (nammu_fediverse_post_activity($inboxUrl, $activity, $config)) {
            $delivered++;
        }
    }
    return $delivered;
}

function nammu_fediverse_actor_document(array $config): array
{
    $actorUrl = nammu_fediverse_actor_url($config);
    $profilePageUrl = nammu_fediverse_profile_page_url($config);
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
        'url' => $profilePageUrl,
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
        'type' => 'CryptographicKey',
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
    foreach ((array) (nammu_fediverse_deleted_store()['items'] ?? []) as $deletedItem) {
        if (!is_array($deletedItem)) {
            continue;
        }
        $deletedId = trim((string) ($deletedItem['id'] ?? ''));
        if ($deletedId === '') {
            continue;
        }
        $activities[] = nammu_fediverse_build_delete_activity(
            $deletedId,
            $config,
            trim((string) ($deletedItem['deleted_at'] ?? ''))
        );
    }
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
    $deletedItemId = $baseUrl . $routePath;
    $deletedEntry = nammu_fediverse_deleted_entry($deletedItemId);
    if (is_array($deletedEntry)) {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $deletedItemId,
            'type' => 'Tombstone',
            'formerType' => 'Article',
            'deleted' => trim((string) ($deletedEntry['deleted_at'] ?? '')) ?: gmdate(DATE_ATOM),
        ];
    }
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
        $object['replies'] = nammu_fediverse_reply_collection_summary((string) $object['id'], $config);
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
            if (trim((string) ($object['id'] ?? '')) !== '') {
                $object['replies'] = nammu_fediverse_reply_collection_summary((string) $object['id'], $config);
            }
            return $object;
        }
    }
    return null;
}

function nammu_fediverse_reply_note_document(string $routePath, array $config): ?array
{
    if (!preg_match('#^/ap/notes/([a-f0-9]{24})$#', $routePath, $matches)) {
        return null;
    }
    $noteHash = trim((string) ($matches[1] ?? ''));
    if ($noteHash === '') {
        return null;
    }
    foreach (nammu_fediverse_public_action_activities($config) as $activity) {
        $object = is_array($activity['object'] ?? null) ? $activity['object'] : null;
        if (!is_array($object)) {
            continue;
        }
        $objectId = trim((string) ($object['id'] ?? ''));
        if ($objectId === '') {
            continue;
        }
        $objectPath = trim((string) (parse_url($objectId, PHP_URL_PATH) ?? ''));
        if ($objectPath === $routePath) {
            return $object;
        }
        if (substr(sha1($objectId), 0, 24) === $noteHash) {
            return $object;
        }
    }
    return null;
}

function nammu_fediverse_notify_followers_of_object_update(array $item, array $config): int
{
    $itemId = trim((string) ($item['id'] ?? ''));
    if ($itemId === '') {
        return 0;
    }
    $followers = nammu_fediverse_followers_store()['followers'];
    if (empty($followers)) {
        return 0;
    }
    $activity = nammu_fediverse_activity_for_local_item($item, $config);
    $object = is_array($activity['object'] ?? null) ? $activity['object'] : null;
    if (!is_array($object)) {
        return 0;
    }
    $object['updated'] = gmdate(DATE_ATOM);
    if (trim((string) ($object['id'] ?? '')) !== '') {
        $object['replies'] = nammu_fediverse_reply_collection_summary((string) $object['id'], $config);
    }
    $updateActivity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => nammu_fediverse_actor_url($config) . '/updates/' . substr(sha1($itemId . '|' . microtime(true)), 0, 24),
        'type' => 'Update',
        'actor' => nammu_fediverse_actor_url($config),
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [nammu_fediverse_followers_url($config)],
        'published' => gmdate(DATE_ATOM),
        'object' => $object,
    ];
    $delivered = 0;
    foreach ($followers as $follower) {
        $inboxUrl = nammu_fediverse_remote_inbox_for_actor($follower);
        if ($inboxUrl === '') {
            continue;
        }
        if (nammu_fediverse_post_activity($inboxUrl, $updateActivity, $config)) {
            $delivered++;
        }
    }
    return $delivered;
}

function nammu_fediverse_relay_public_reply_to_followers(array $payload, array $localTarget, array $config): int
{
    $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
    $replyObjectId = trim((string) (($object['id'] ?? '') ?: ''));
    if ($replyObjectId === '') {
        return 0;
    }
    $localTargetId = trim((string) (($localTarget['id'] ?? '') ?: ''));
    if ($localTargetId === '') {
        return 0;
    }
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        if (strtolower(trim((string) ($action['type'] ?? ''))) !== 'reply_announce') {
            continue;
        }
        if (
            trim((string) ($action['reply_object_id'] ?? '')) === $replyObjectId
            && trim((string) ($action['object_url'] ?? '')) === $localTargetId
        ) {
            return 0;
        }
    }
    $followers = nammu_fediverse_followers_store()['followers'];
    if (empty($followers)) {
        return 0;
    }
    $actorUrl = nammu_fediverse_actor_url($config);
    $replyActorId = trim((string) ($payload['actor'] ?? ''));
    $announceActivity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $actorUrl . '/reply-announces/' . substr(sha1($replyObjectId . '|' . microtime(true)), 0, 24),
        'type' => 'Announce',
        'actor' => $actorUrl,
        'object' => $replyObjectId,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => array_values(array_filter([
            $replyActorId,
            trim((string) ($localTarget['actor_id'] ?? '')),
        ])),
        'published' => gmdate(DATE_ATOM),
    ];
    $delivered = 0;
    foreach ($followers as $follower) {
        $inboxUrl = nammu_fediverse_remote_inbox_for_actor($follower);
        if ($inboxUrl === '') {
            continue;
        }
        if (nammu_fediverse_post_activity($inboxUrl, $announceActivity, $config)) {
            $delivered++;
        }
    }
    if ($delivered > 0) {
        nammu_fediverse_record_action('reply_announce', trim((string) ($localTarget['actor_id'] ?? '')), $localTargetId, [
            'activity_id' => (string) ($announceActivity['id'] ?? ''),
            'published' => (string) ($announceActivity['published'] ?? gmdate(DATE_ATOM)),
            'reply_object_id' => $replyObjectId,
            'reply_actor_id' => $replyActorId,
            'target_actor_id' => trim((string) ($localTarget['actor_id'] ?? '')),
        ]);
    }
    return $delivered;
}

function nammu_fediverse_replies_collection_document(string $routePath, array $config): ?array
{
    $routePath = trim($routePath);
    if (!preg_match('#^/ap/replies/([a-f0-9]{24})$#', $routePath, $matches)) {
        return null;
    }
    $targetHash = trim((string) ($matches[1] ?? ''));
    if ($targetHash === '') {
        return null;
    }
    $targetItem = null;
    foreach (nammu_fediverse_local_content_items($config) as $item) {
        $itemId = trim((string) ($item['id'] ?? ''));
        if ($itemId !== '' && nammu_fediverse_reply_collection_hash($itemId) === $targetHash) {
            $targetItem = $item;
            break;
        }
    }
    if (!is_array($targetItem)) {
        foreach (nammu_fediverse_actions_store()['items'] as $action) {
            $resendItem = nammu_fediverse_resend_item_from_action($action);
            if (!is_array($resendItem)) {
                continue;
            }
            $itemId = trim((string) ($resendItem['id'] ?? ''));
            if ($itemId !== '' && nammu_fediverse_reply_collection_hash($itemId) === $targetHash) {
                $targetItem = $resendItem;
                break;
            }
        }
    }
    if (!is_array($targetItem)) {
        return null;
    }
    $targetIdentifiers = [];
    foreach (['id', 'url'] as $field) {
        $value = trim((string) ($targetItem[$field] ?? ''));
        if ($value !== '') {
            $targetIdentifiers[] = $value;
        }
    }
    $replyObjects = [];
    $inboxStore = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    foreach ((array) ($inboxStore['activities'] ?? []) as $entry) {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        if (strtolower(trim((string) ($payload['type'] ?? ''))) !== 'create') {
            continue;
        }
        $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
        if (strtolower(trim((string) ($object['type'] ?? ''))) !== 'note') {
            continue;
        }
        $target = trim((string) ($object['inReplyTo'] ?? ''));
        if ($target === '' || !in_array($target, $targetIdentifiers, true)) {
            continue;
        }
        $replyId = trim((string) (($object['id'] ?? '') ?: ($payload['id'] ?? '')));
        if ($replyId === '') {
            continue;
        }
        $replyObject = $object;
        if (trim((string) ($replyObject['id'] ?? '')) === '') {
            $replyObject['id'] = $replyId;
        }
        if (trim((string) ($replyObject['url'] ?? '')) === '') {
            $replyObject['url'] = $replyId;
        }
        if (trim((string) ($replyObject['attributedTo'] ?? '')) === '') {
            $replyObject['attributedTo'] = trim((string) ($payload['actor'] ?? ''));
        }
        if (trim((string) ($replyObject['published'] ?? '')) === '') {
            $replyObject['published'] = trim((string) (($payload['published'] ?? '') ?: ($entry['received_at'] ?? gmdate(DATE_ATOM))));
        }
        if (trim((string) ($replyObject['context'] ?? '')) === '') {
            $replyObject['context'] = $target;
        }
        if (trim((string) ($replyObject['conversation'] ?? '')) === '') {
            $replyObject['conversation'] = $target;
        }
        $replyObjects[$replyId] = $replyObject;
    }
    $replyObjects = array_values($replyObjects);
    usort($replyObjects, static function (array $a, array $b): int {
        return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
    });
    $collectionUrl = rtrim(nammu_fediverse_base_url($config), '/') . $routePath;
    $pageUrl = $collectionUrl . '?page=true';
    $isPage = strtolower(trim((string) ($_GET['page'] ?? ''))) === 'true';
    if ($isPage) {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $pageUrl,
            'type' => 'CollectionPage',
            'partOf' => $collectionUrl,
            'totalItems' => count($replyObjects),
            'items' => $replyObjects,
        ];
    }
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $collectionUrl,
        'type' => 'Collection',
        'totalItems' => count($replyObjects),
        'first' => [
            'id' => $pageUrl,
            'type' => 'CollectionPage',
            'partOf' => $collectionUrl,
            'items' => [],
        ],
    ];
}

function nammu_fediverse_inspect_object(string $objectUrl, array $config): array
{
    $objectUrl = trim($objectUrl);
    if ($objectUrl === '') {
        return ['ok' => false, 'message' => 'Falta la URL del objeto ActivityPub.'];
    }
    $object = nammu_fediverse_signed_fetch_json($objectUrl, $config);
    if (!is_array($object)) {
        return ['ok' => false, 'message' => 'No se pudo obtener el objeto ActivityPub.'];
    }
    $repliesRef = $object['replies'] ?? null;
    $replies = null;
    $repliesPage = null;
    $collectionUrl = '';
    $pageUrl = '';
    if (is_string($repliesRef)) {
        $collectionUrl = trim($repliesRef);
    } elseif (is_array($repliesRef)) {
        $collectionUrl = trim((string) (($repliesRef['id'] ?? '') ?: ''));
        $pageUrl = trim((string) (($repliesRef['first'] ?? '') ?: ''));
    }
    if ($collectionUrl !== '') {
        $replies = nammu_fediverse_signed_fetch_json($collectionUrl, $config);
    }
    if ($pageUrl !== '') {
        $repliesPage = nammu_fediverse_signed_fetch_json($pageUrl, $config);
    }
    return [
        'ok' => true,
        'object_url' => $objectUrl,
        'object' => $object,
        'replies' => $replies,
        'replies_page' => $repliesPage,
    ];
}

function nammu_fediverse_store_inbox_activity(array $payload, array $meta = [], ?array $config = null): void
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
    $store['activities'] = array_slice($activities, -1000);
    nammu_fediverse_save_json_store(nammu_fediverse_inbox_file(), $store);
    if (is_array($config)) {
        nammu_fediverse_record_legacy_actuality_payload($payload, $config);
    }
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

function nammu_fediverse_remove_inbox_activities(array $identifiers): int
{
    $identifiers = array_values(array_filter(array_map(static function ($value): string {
        return trim((string) $value);
    }, $identifiers)));
    if (empty($identifiers)) {
        return 0;
    }
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    $before = count($activities);
    $activities = array_values(array_filter($activities, static function (array $entry) use ($identifiers): bool {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $object = $payload['object'] ?? null;
        $candidates = [
            trim((string) ($payload['id'] ?? '')),
        ];
        if (is_string($object)) {
            $candidates[] = trim($object);
        } elseif (is_array($object)) {
            $candidates[] = trim((string) (($object['id'] ?? '') ?: ''));
            $candidates[] = trim((string) (($object['url'] ?? '') ?: ''));
            $candidates[] = trim((string) (($object['atomUri'] ?? '') ?: ''));
        }
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && in_array($candidate, $identifiers, true)) {
                return false;
            }
        }
        return true;
    }));
    if ($before !== count($activities)) {
        $store['activities'] = $activities;
        nammu_fediverse_save_json_store(nammu_fediverse_inbox_file(), $store);
    }
    return $before - count($activities);
}

function nammu_fediverse_local_targets_for_deleted_reply(array $identifiers, array $config): array
{
    $identifiers = array_values(array_filter(array_map(static function ($value): string {
        return trim((string) $value);
    }, $identifiers)));
    if (empty($identifiers)) {
        return [];
    }
    $targets = [];
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    foreach ($activities as $entry) {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        if (strtolower(trim((string) ($payload['type'] ?? ''))) !== 'create') {
            continue;
        }
        $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
        if (strtolower(trim((string) ($object['type'] ?? ''))) !== 'note') {
            continue;
        }
        $candidates = array_filter([
            trim((string) ($payload['id'] ?? '')),
            trim((string) ($object['id'] ?? '')),
            trim((string) ($object['url'] ?? '')),
            trim((string) ($object['atomUri'] ?? '')),
        ]);
        if (empty(array_intersect($candidates, $identifiers))) {
            continue;
        }
        $target = trim((string) ($object['inReplyTo'] ?? ''));
        if ($target === '') {
            continue;
        }
        $localTarget = nammu_fediverse_find_local_item_for_identifier($target, $config);
        if (!is_array($localTarget)) {
            continue;
        }
        $localId = trim((string) ($localTarget['id'] ?? ''));
        if ($localId !== '') {
            $targets[$localId] = $localTarget;
        }
    }
    return array_values($targets);
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

function nammu_fediverse_pending_follow_payloads(array $config): array
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    $followers = nammu_fediverse_followers_store()['followers'];
    $followersById = [];
    foreach ($followers as $follower) {
        $followerId = trim((string) ($follower['id'] ?? ''));
        if ($followerId !== '') {
            $followersById[$followerId] = $follower;
        }
    }
    $pendingByActor = [];
    foreach ($activities as $entry) {
        if (empty($entry['verified'])) {
            continue;
        }
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        if ($type === 'follow') {
            $actorId = trim((string) ($payload['actor'] ?? ''));
            $followId = trim((string) ($payload['id'] ?? ''));
            if ($actorId === '' || $followId === '' || nammu_fediverse_is_blocked_actor($actorId)) {
                continue;
            }
            $pendingByActor[$actorId] = $payload;
            continue;
        }
        if ($type !== 'undo' || !is_array($payload['object'] ?? null)) {
            continue;
        }
        $actorId = trim((string) ($payload['actor'] ?? ''));
        $object = $payload['object'];
        if (strtolower(trim((string) ($object['type'] ?? ''))) !== 'follow' || $actorId === '') {
            continue;
        }
        $followId = trim((string) ($object['id'] ?? ''));
        if (!isset($pendingByActor[$actorId])) {
            continue;
        }
        $pendingFollowId = trim((string) ($pendingByActor[$actorId]['id'] ?? ''));
        if ($followId === '' || $pendingFollowId === '' || $followId === $pendingFollowId) {
            unset($pendingByActor[$actorId]);
        }
    }
    $pending = [];
    foreach ($pendingByActor as $actorId => $payload) {
        $followId = trim((string) ($payload['id'] ?? ''));
        if ($followId === '') {
            continue;
        }
        $follower = is_array($followersById[$actorId] ?? null) ? $followersById[$actorId] : [];
        $acceptedFollowId = trim((string) ($follower['follow_activity_id'] ?? ''));
        $acceptSentAt = trim((string) ($follower['accept_sent_at'] ?? ''));
        $acceptPending = !empty($follower['accept_pending']);
        if ($acceptedFollowId === $followId && $acceptSentAt !== '' && !$acceptPending) {
            continue;
        }
        $pending[] = $payload;
    }
    return $pending;
}

function nammu_fediverse_accept_follow_response(array $payload, array $config): array
{
    $actorId = trim((string) ($payload['actor'] ?? ''));
    $followId = trim((string) ($payload['id'] ?? ''));
    if ($actorId === '' || $followId === '') {
        return ['ok' => false, 'message' => 'La actividad Follow no trae actor o id.'];
    }
    if (nammu_fediverse_is_blocked_actor($actorId)) {
        return ['ok' => false, 'message' => 'El actor está bloqueado.', 'actor_id' => $actorId, 'follow_id' => $followId];
    }
    $attemptAt = gmdate(DATE_ATOM);
    $actor = nammu_fediverse_resolve_actor($actorId, $config);
    if (!is_array($actor)) {
        nammu_fediverse_followers_add_or_update([
            'id' => $actorId,
            'follow_activity_id' => $followId,
            'accept_pending' => true,
            'accept_last_attempt_at' => $attemptAt,
            'accept_last_error' => 'No se pudo resolver el actor remoto.',
        ]);
        return [
            'ok' => false,
            'message' => 'No se pudo resolver el actor remoto.',
            'actor_id' => $actorId,
            'follow_id' => $followId,
        ];
    }
    nammu_fediverse_followers_add_or_update(array_merge($actor, [
        'follow_activity_id' => $followId,
        'accept_pending' => true,
        'accept_last_attempt_at' => $attemptAt,
        'accept_last_error' => '',
    ]));
    $inboxUrl = nammu_fediverse_remote_inbox_for_actor($actor);
    if ($inboxUrl === '') {
        nammu_fediverse_followers_add_or_update([
            'id' => $actorId,
            'follow_activity_id' => $followId,
            'accept_pending' => true,
            'accept_last_attempt_at' => $attemptAt,
            'accept_last_error' => 'El actor remoto no publica inbox.',
        ]);
        return [
            'ok' => false,
            'message' => 'El actor remoto no publica inbox.',
            'actor_id' => $actorId,
            'follow_id' => $followId,
        ];
    }
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => nammu_fediverse_actor_url($config) . '#accept-' . sha1($followId . '|' . microtime(true)),
        'type' => 'Accept',
        'actor' => nammu_fediverse_actor_url($config),
        'object' => $payload,
    ];
    $result = nammu_fediverse_post_activity_response($inboxUrl, $activity, $config);
    $updates = [
        'id' => $actorId,
        'follow_activity_id' => $followId,
        'accept_activity_id' => trim((string) ($activity['id'] ?? '')),
        'accept_pending' => empty($result['ok']),
        'accept_last_attempt_at' => $attemptAt,
        'accept_last_error' => trim((string) ($result['message'] ?? '')),
    ];
    if (!empty($result['ok'])) {
        $updates['accept_sent_at'] = $attemptAt;
        $updates['accept_last_error'] = '';
    }
    nammu_fediverse_followers_add_or_update($updates);
    $result['actor_id'] = $actorId;
    $result['follow_id'] = $followId;
    $result['accept_activity_id'] = (string) ($activity['id'] ?? '');
    return $result;
}

function nammu_fediverse_retry_pending_follower_accepts(array $config, int $limit = 100): array
{
    $pending = nammu_fediverse_pending_follow_payloads($config);
    if ($limit > 0) {
        $pending = array_slice($pending, 0, $limit);
    }
    $stats = ['checked' => count($pending), 'accepted' => 0, 'failed' => 0];
    foreach ($pending as $payload) {
        $result = nammu_fediverse_accept_follow_response($payload, $config);
        if (!empty($result['ok'])) {
            $stats['accepted']++;
        } else {
            $stats['failed']++;
        }
    }
    return $stats;
}

function nammu_fediverse_followers_remove(string $actorId): void
{
    $followers = array_values(array_filter(
        nammu_fediverse_followers_store()['followers'],
        static fn(array $follower): bool => (string) ($follower['id'] ?? '') !== $actorId
    ));
    nammu_fediverse_save_followers_store($followers);
}

function nammu_fediverse_is_blocked_actor(string $actorId): bool
{
    $actorId = trim($actorId);
    if ($actorId === '') {
        return false;
    }
    foreach (nammu_fediverse_blocked_store()['actors'] as $actor) {
        if (trim((string) ($actor['id'] ?? '')) === $actorId) {
            return true;
        }
    }
    return false;
}

function nammu_fediverse_block_actor(string $actorId, array $config): array
{
    $actorId = trim($actorId);
    if ($actorId === '') {
        return ['ok' => false, 'message' => 'No se recibió el actor a bloquear.'];
    }
    $blocked = nammu_fediverse_blocked_store()['actors'];
    foreach ($blocked as $actor) {
        if (trim((string) ($actor['id'] ?? '')) === $actorId) {
            nammu_fediverse_followers_remove($actorId);
            return ['ok' => true, 'message' => 'Ese actor ya estaba bloqueado.'];
        }
    }
    $actor = nammu_fediverse_resolve_actor($actorId, $config);
    if (!is_array($actor)) {
        $actor = ['id' => $actorId];
    }
    $blocked[] = [
        'id' => trim((string) ($actor['id'] ?? $actorId)),
        'preferredUsername' => trim((string) ($actor['preferredUsername'] ?? '')),
        'name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? '') ?: $actorId)),
        'url' => trim((string) (($actor['url'] ?? '') ?: ($actor['id'] ?? $actorId))),
        'icon' => trim((string) ($actor['icon'] ?? '')),
        'blocked_at' => gmdate(DATE_ATOM),
    ];
    nammu_fediverse_save_blocked_store($blocked);
    nammu_fediverse_followers_remove($actorId);
    $deliveries = nammu_fediverse_deliveries_store()['followers'];
    unset($deliveries[$actorId]);
    nammu_fediverse_save_deliveries_store($deliveries);
    return ['ok' => true, 'message' => 'Actor bloqueado en el Fediverso.'];
}

function nammu_fediverse_unblock_actor(string $actorId): array
{
    $actorId = trim($actorId);
    if ($actorId === '') {
        return ['ok' => false, 'message' => 'No se recibió el actor a desbloquear.'];
    }
    $blocked = array_values(array_filter(
        nammu_fediverse_blocked_store()['actors'],
        static fn(array $item): bool => trim((string) ($item['id'] ?? '')) !== $actorId
    ));
    nammu_fediverse_save_blocked_store($blocked);
    return ['ok' => true, 'message' => 'Actor desbloqueado.'];
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
    if (!array_key_exists('visibility', $message) || trim((string) ($message['visibility'] ?? '')) === '') {
        $message['visibility'] = 'private';
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
        $visibility = strtolower(trim((string) ($item['visibility'] ?? 'private')));
        if ($visibility === 'public') {
            continue;
        }
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

function nammu_fediverse_thread_grouped_messages(array $messages): array
{
    $groups = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $visibility = strtolower(trim((string) ($message['visibility'] ?? 'private')));
        $threadKey = '';
        if ($visibility === 'public') {
            $threadKey = trim((string) ($message['reply_target_url'] ?? ''));
            if ($threadKey === '') {
                $threadKey = 'public:' . trim((string) (($message['actor_id'] ?? '') ?: ($message['id'] ?? '')));
            }
        } else {
            $threadKey = 'private:' . trim((string) ($message['actor_id'] ?? ''));
        }
        if ($threadKey === '') {
            continue;
        }
        if (!isset($groups[$threadKey])) {
            $groups[$threadKey] = [];
        }
        $groups[$threadKey][] = $message;
    }
    foreach ($groups as &$threadMessages) {
        $rootMessages = [];
        $nonRootMessages = [];
        foreach ($threadMessages as $threadMessage) {
            if (!empty($threadMessage['is_thread_root'])) {
                $rootMessages[] = $threadMessage;
            } else {
                $nonRootMessages[] = $threadMessage;
            }
        }
        usort($rootMessages, static function (array $a, array $b): int {
            $aOutgoing = (($a['direction'] ?? '') === 'outgoing');
            $bOutgoing = (($b['direction'] ?? '') === 'outgoing');
            if ($aOutgoing !== $bOutgoing) {
                return $aOutgoing ? -1 : 1;
            }
            return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
        });
        usort($nonRootMessages, static function (array $a, array $b): int {
            return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
        });
        $threadMessages = array_merge($rootMessages, $nonRootMessages);
    }
    unset($threadMessages);
    uasort($groups, static function (array $a, array $b): int {
        $lastA = '';
        foreach ($a as $message) {
            $published = (string) ($message['published'] ?? '');
            if ($published > $lastA) {
                $lastA = $published;
            }
        }
        $lastB = '';
        foreach ($b as $message) {
            $published = (string) ($message['published'] ?? '');
            if ($published > $lastB) {
                $lastB = $published;
            }
        }
        return strcmp($lastB, $lastA);
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
        if (nammu_fediverse_is_direct_message_activity($payload, $config)) {
            return false;
        }
        return nammu_fediverse_notification_is_relevant($payload, $config);
    }));
    return array_values(array_reverse($filtered));
}

function nammu_fediverse_notification_is_relevant(array $payload, array $config): bool
{
    $type = strtolower(trim((string) ($payload['type'] ?? '')));
    if (in_array($type, ['follow', 'undo', 'accept'], true)) {
        return true;
    }
    if (nammu_fediverse_notification_mentions_local_actor($payload, $config)) {
        return true;
    }
    $targets = nammu_fediverse_notification_target_identifiers($payload);
    if (empty($targets)) {
        return false;
    }
    $watched = nammu_fediverse_notification_watched_identifiers($config);
    foreach ($targets as $target) {
        if ($target !== '' && isset($watched[$target])) {
            return true;
        }
    }
    return false;
}

function nammu_fediverse_notification_mentions_local_actor(array $payload, array $config): bool
{
    $actorUrl = trim((string) nammu_fediverse_actor_url($config));
    $actorAcct = trim((string) nammu_fediverse_acct_uri($config));
    $preferredUsername = trim((string) nammu_fediverse_preferred_username($config));
    $profileAliasPath = function_exists('nammu_fediverse_profile_alias_path')
        ? trim((string) nammu_fediverse_profile_alias_path($config, nammu_fediverse_base_url($config)))
        : '';
    $profileAliasUrl = $profileAliasPath !== '' ? rtrim(nammu_fediverse_base_url($config), '/') . $profileAliasPath : '';
    $needles = array_values(array_filter([
        $actorUrl,
        $actorAcct,
        $profileAliasUrl,
        $preferredUsername !== '' ? '@' . $preferredUsername : '',
    ]));

    $haystacks = [];
    foreach (['to', 'cc'] as $field) {
        $values = $payload[$field] ?? [];
        if (is_string($values)) {
            $haystacks[] = trim($values);
        } elseif (is_array($values)) {
            foreach ($values as $value) {
                $haystacks[] = trim((string) $value);
            }
        }
    }
    $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
    foreach (['to', 'cc', 'attributedTo'] as $field) {
        $values = $object[$field] ?? [];
        if (is_string($values)) {
            $haystacks[] = trim($values);
        } elseif (is_array($values)) {
            foreach ($values as $value) {
                $haystacks[] = trim((string) $value);
            }
        }
    }
    foreach ((array) ($object['tag'] ?? []) as $tag) {
        if (!is_array($tag)) {
            continue;
        }
        $haystacks[] = trim((string) ($tag['href'] ?? ''));
        $haystacks[] = trim((string) ($tag['name'] ?? ''));
    }
    $content = trim((string) (($object['content'] ?? '') ?: ''));
    if ($content !== '') {
        $haystacks[] = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    foreach ($haystacks as $haystack) {
        if ($haystack === '') {
            continue;
        }
        foreach ($needles as $needle) {
            if ($needle === '') {
                continue;
            }
            if ($haystack === $needle || str_contains($haystack, $needle)) {
                return true;
            }
        }
    }
    return false;
}

function nammu_fediverse_notification_target_identifiers(array $payload): array
{
    $targets = [];
    $type = strtolower(trim((string) ($payload['type'] ?? '')));
    $object = $payload['object'] ?? null;

    if (in_array($type, ['like', 'announce'], true) && is_string($object)) {
        $targets[] = trim($object);
    } elseif ($type === 'create' && is_array($object)) {
        foreach (['inReplyTo', 'context', 'conversation', 'id', 'url'] as $field) {
            $targets[] = trim((string) ($object[$field] ?? ''));
        }
    } elseif (is_string($object)) {
        $targets[] = trim($object);
    } elseif (is_array($object)) {
        foreach (['id', 'url', 'inReplyTo', 'context', 'conversation'] as $field) {
            $targets[] = trim((string) ($object[$field] ?? ''));
        }
    }

    return array_values(array_unique(array_filter($targets, static fn(string $value): bool => $value !== '')));
}

function nammu_fediverse_notification_watched_identifiers(array $config): array
{
    $watched = [];
    foreach (nammu_fediverse_local_content_items($config) as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach (['id', 'url'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                $watched[$value] = true;
            }
        }
    }
    foreach (nammu_fediverse_actions_store()['items'] as $action) {
        if (!is_array($action)) {
            continue;
        }
        if (strtolower(trim((string) ($action['type'] ?? ''))) !== 'reply') {
            continue;
        }
        foreach (['object_url', 'note_id', 'activity_id'] as $field) {
            $value = trim((string) ($action[$field] ?? ''));
            if ($value !== '') {
                $watched[$value] = true;
            }
        }
    }
    return $watched;
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
    $lastPhpError = null;
    set_error_handler(static function (int $severity, string $message) use (&$lastPhpError): bool {
        $lastPhpError = $message;
        return false;
    });
    try {
        $response = nammu_fediverse_signed_fetch($inboxUrl, $config, 'POST', $body);
    } finally {
        restore_error_handler();
    }
    if ((int) ($response['status'] ?? 0) === 0) {
        $retryError = null;
        set_error_handler(static function (int $severity, string $message) use (&$retryError): bool {
            $retryError = $message;
            return false;
        });
        try {
            $retryResponse = nammu_fediverse_signed_fetch($inboxUrl, $config, 'POST', $body, 12);
        } finally {
            restore_error_handler();
        }
        if ((int) ($retryResponse['status'] ?? 0) > 0) {
            $response = $retryResponse;
            $lastPhpError = $retryError;
        } elseif ($retryError !== null && $retryError !== '') {
            $lastPhpError = $retryError;
        }
    }
    $status = (int) ($response['status'] ?? 0);
    $responseBody = trim((string) ($response['body'] ?? ''));
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'status' => $status, 'body' => $responseBody, 'message' => ''];
    }
    return [
        'ok' => false,
        'status' => $status,
        'body' => $responseBody,
        'message' => 'HTTP ' . $status . ($responseBody !== '' ? ': ' . $responseBody : '') . ($lastPhpError ? ' (' . trim($lastPhpError) . ')' : ''),
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
    nammu_fediverse_record_action('like', $recipientId, $objectUrl, [
        'activity_id' => (string) ($activity['id'] ?? ''),
        'published' => (string) ($activity['published'] ?? gmdate(DATE_ATOM)),
    ]);
    return ['ok' => true, 'message' => 'Favorito enviado.'];
}

function nammu_fediverse_send_announce(string $recipientId, string $objectUrl, array $config): array
{
    $recipientId = trim($recipientId);
    $objectUrl = nammu_fediverse_resolve_object_reference(trim($objectUrl), $config);
    if ($recipientId === '' || $objectUrl === '') {
        return ['ok' => false, 'message' => 'Falta el destinatario o la publicación a impulsar.'];
    }
    $actorUrl = nammu_fediverse_actor_url($config);
    $activityId = $actorUrl . '/announces/' . substr(sha1($recipientId . '|' . $objectUrl . '|' . microtime(true)), 0, 24);
    $published = gmdate(DATE_ATOM);
    $queue = nammu_fediverse_announce_queue_store();
    $items = is_array($queue['items'] ?? null) ? $queue['items'] : [];
    foreach ($items as $queued) {
        if (
            trim((string) ($queued['recipient_id'] ?? '')) === $recipientId
            && trim((string) ($queued['object_url'] ?? '')) === $objectUrl
        ) {
            nammu_fediverse_record_action('boost', $recipientId, $objectUrl, [
                'activity_id' => trim((string) ($queued['activity_id'] ?? '')),
                'published' => trim((string) ($queued['published'] ?? $published)),
            ]);
            return ['ok' => true, 'message' => 'Impulso encolado.'];
        }
    }
    $items[] = [
        'activity_id' => $activityId,
        'recipient_id' => $recipientId,
        'object_url' => $objectUrl,
        'published' => $published,
        'created_at' => $published,
        'attempts' => 0,
    ];
    nammu_fediverse_save_announce_queue_store($items, $config);
    nammu_fediverse_record_action('boost', $recipientId, $objectUrl, [
        'activity_id' => $activityId,
        'published' => $published,
    ]);
    return ['ok' => true, 'message' => 'Impulso encolado.'];
}

function nammu_fediverse_process_announce_queue(array $config, int $maxJobs = 2): array
{
    $queue = nammu_fediverse_announce_queue_store($config);
    $items = is_array($queue['items'] ?? null) ? array_values($queue['items']) : [];
    $processed = 0;
    $sent = 0;
    $failed = 0;
    $remaining = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ($processed >= max(1, $maxJobs)) {
            $remaining[] = $item;
            continue;
        }
        $recipientId = trim((string) ($item['recipient_id'] ?? ''));
        $objectUrl = trim((string) ($item['object_url'] ?? ''));
        $activityId = trim((string) ($item['activity_id'] ?? ''));
        $published = trim((string) ($item['published'] ?? '')) ?: gmdate(DATE_ATOM);
        if ($recipientId === '' || $objectUrl === '' || $activityId === '') {
            continue;
        }
        $processed++;
        $recipient = nammu_fediverse_resolve_actor($recipientId, $config);
        if (!is_array($recipient)) {
            $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;
            $failed++;
            if ((int) $item['attempts'] < 5) {
                $remaining[] = $item;
            }
            continue;
        }
        $inboxTargets = [];
        $inboxUrl = nammu_fediverse_remote_inbox_for_actor($recipient);
        if ($inboxUrl !== '') {
            $inboxTargets[$inboxUrl] = true;
        }
        foreach (nammu_fediverse_followers_store()['followers'] as $follower) {
            $followerInbox = nammu_fediverse_remote_inbox_for_actor($follower);
            if ($followerInbox !== '') {
                $inboxTargets[$followerInbox] = true;
            }
        }
        if (empty($inboxTargets)) {
            $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;
            $failed++;
            if ((int) $item['attempts'] < 5) {
                $remaining[] = $item;
            }
            continue;
        }
        $actorUrl = nammu_fediverse_actor_url($config);
        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Announce',
            'actor' => $actorUrl,
            'object' => $objectUrl,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$recipientId],
            'published' => $published,
        ];
        $successfulDeliveries = 0;
        foreach (array_keys($inboxTargets) as $targetInbox) {
            $delivery = nammu_fediverse_post_activity_response($targetInbox, $activity, $config);
            if (!empty($delivery['ok'])) {
                $successfulDeliveries++;
            }
        }
        if ($successfulDeliveries > 0) {
            $sent++;
            continue;
        }
        $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;
        $failed++;
        if ((int) $item['attempts'] < 5) {
            $remaining[] = $item;
        }
    }
    nammu_fediverse_save_announce_queue_store($remaining, $config);
    return [
        'processed' => $processed,
        'sent' => $sent,
        'failed' => $failed,
        'remaining' => count($remaining),
    ];
}

function nammu_fediverse_send_undo_like_for_item(array $item, array $config): array
{
    $likeAction = nammu_fediverse_latest_action_for_item($item, 'like');
    if (!is_array($likeAction)) {
        return ['ok' => false, 'message' => 'No se encontró un favorito local para esa publicación.'];
    }
    $recipientId = trim((string) ($likeAction['actor_id'] ?? ''));
    $objectUrl = nammu_fediverse_resolve_object_reference(trim((string) ($likeAction['object_url'] ?? '')), $config);
    if ($recipientId === '' || $objectUrl === '') {
        return ['ok' => false, 'message' => 'Faltan datos para retirar ese favorito.'];
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
    $likeObject = [
        'id' => trim((string) ($likeAction['activity_id'] ?? '')),
        'type' => 'Like',
        'actor' => $actorUrl,
        'object' => $objectUrl,
        'to' => [$recipientId],
    ];
    if ($likeObject['id'] === '') {
        unset($likeObject['id']);
    }
    $undoActivity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $actorUrl . '/undo-like/' . substr(sha1($objectUrl . '|' . microtime(true)), 0, 24),
        'type' => 'Undo',
        'actor' => $actorUrl,
        'to' => [$recipientId],
        'object' => $likeObject,
        'published' => gmdate(DATE_ATOM),
    ];
    $delivery = nammu_fediverse_post_activity_response($inboxUrl, $undoActivity, $config);
    if (empty($delivery['ok'])) {
        return ['ok' => false, 'message' => 'No se pudo retirar el favorito. ' . trim((string) ($delivery['message'] ?? ''))];
    }
    nammu_fediverse_remove_action_by_id((string) ($likeAction['id'] ?? ''));
    return ['ok' => true, 'message' => 'Favorito retirado.'];
}

function nammu_fediverse_send_undo_announce_for_item(array $item, array $config): array
{
    $queued = nammu_fediverse_enqueue_undo_announce_for_item($item, $config);
    if (empty($queued['ok'])) {
        return $queued;
    }
    return [
        'ok' => true,
        'message' => (string) ($queued['message'] ?? 'Impulso retirado localmente y encolado para deshacerlo en el Fediverso.'),
    ];
}

function nammu_fediverse_enqueue_undo_announce_for_item(array $item, array $config): array
{
    if (!function_exists('nammu_actuality_delete_manual_item') && is_file(dirname(__DIR__) . '/core/actualidad.php')) {
        require_once dirname(__DIR__) . '/core/actualidad.php';
    }
    $boostAction = nammu_fediverse_latest_action_for_item($item, 'boost');
    if (!is_array($boostAction)) {
        return ['ok' => false, 'message' => 'No se encontró un impulso local para esa publicación.'];
    }
    $recipientId = trim((string) ($boostAction['actor_id'] ?? ''));
    $objectUrl = nammu_fediverse_resolve_object_reference(trim((string) ($boostAction['object_url'] ?? '')), $config);
    if ($recipientId === '' || $objectUrl === '') {
        return ['ok' => false, 'message' => 'Faltan datos para retirar ese impulso.'];
    }
    $queue = nammu_fediverse_undo_announce_queue_store($config);
    $queuedItems = is_array($queue['items'] ?? null) ? $queue['items'] : [];
    $activityId = trim((string) ($boostAction['activity_id'] ?? ''));
    $alreadyQueued = false;
    foreach ($queuedItems as $queuedItem) {
        if (!is_array($queuedItem)) {
            continue;
        }
        if (
            trim((string) ($queuedItem['recipient_id'] ?? '')) === $recipientId
            && trim((string) ($queuedItem['object_url'] ?? '')) === $objectUrl
        ) {
            $alreadyQueued = true;
            break;
        }
    }
    if (!$alreadyQueued) {
        $queuedItems[] = [
            'activity_id' => $activityId,
            'recipient_id' => $recipientId,
            'object_url' => $objectUrl,
            'created_at' => gmdate(DATE_ATOM),
            'attempts' => 0,
        ];
        nammu_fediverse_save_undo_announce_queue_store($queuedItems, $config);
    }

    $messageParts = ['Impulso retirado localmente y encolado para deshacerlo en el Fediverso.'];
    $manualItem = nammu_fediverse_find_manual_boost_item($boostAction);
    if (is_array($manualItem)) {
        $manualId = trim((string) ($manualItem['id'] ?? ''));
        $manualObjectId = '';
        if ($manualId !== '') {
            $manualIdentifier = nammu_fediverse_base_url($config) . '/ap/objects/actualidad-' . rawurlencode($manualId);
            $localItem = nammu_fediverse_find_local_item_for_identifier($manualIdentifier, $config);
            if (is_array($localItem)) {
                $manualObjectId = trim((string) ($localItem['id'] ?? ''));
            }
        }
        if ($manualObjectId !== '') {
            $deleteResult = nammu_fediverse_enqueue_delete_local_item($manualObjectId, $config);
            if (!empty($deleteResult['ok'])) {
                $messageParts[] = trim((string) ($deleteResult['message'] ?? ''));
            }
            if (function_exists('nammu_fediverse_remove_local_item_from_home_snapshot')) {
                nammu_fediverse_remove_local_item_from_home_snapshot($manualObjectId);
            }
        }
        if ($manualId !== '' && function_exists('nammu_actuality_delete_manual_item')) {
            nammu_actuality_delete_manual_item($manualId);
            if (function_exists('nammu_actuality_remove_manual_item_from_snapshots')) {
                nammu_actuality_remove_manual_item_from_snapshots($manualId);
            }
        }
    }

    nammu_fediverse_remove_action_by_id((string) ($boostAction['id'] ?? ''));
    $shareAction = nammu_fediverse_find_related_boost_share_action($boostAction);
    if (is_array($shareAction)) {
        nammu_fediverse_remove_action_by_id((string) ($shareAction['id'] ?? ''));
    }
    return ['ok' => true, 'message' => implode(' ', array_filter($messageParts, static fn(string $part): bool => trim($part) !== ''))];
}

function nammu_fediverse_process_undo_announce_queue(array $config, int $maxJobs = 2): array
{
    $queue = nammu_fediverse_undo_announce_queue_store($config);
    $items = is_array($queue['items'] ?? null) ? array_values($queue['items']) : [];
    $processed = 0;
    $sent = 0;
    $failed = 0;
    $remaining = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ($processed >= max(1, $maxJobs)) {
            $remaining[] = $item;
            continue;
        }
        $recipientId = trim((string) ($item['recipient_id'] ?? ''));
        $objectUrl = trim((string) ($item['object_url'] ?? ''));
        if ($recipientId === '' || $objectUrl === '') {
            continue;
        }
        $processed++;
        $recipient = nammu_fediverse_resolve_actor($recipientId, $config);
        if (!is_array($recipient)) {
            $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;
            $failed++;
            if ((int) $item['attempts'] < 5) {
                $remaining[] = $item;
            }
            continue;
        }
        $inboxTargets = [];
        $recipientInbox = nammu_fediverse_remote_inbox_for_actor($recipient);
        if ($recipientInbox !== '') {
            $inboxTargets[$recipientInbox] = true;
        }
        foreach (nammu_fediverse_followers_store()['followers'] as $follower) {
            $followerInbox = nammu_fediverse_remote_inbox_for_actor($follower);
            if ($followerInbox !== '') {
                $inboxTargets[$followerInbox] = true;
            }
        }
        if (empty($inboxTargets)) {
            $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;
            $failed++;
            if ((int) $item['attempts'] < 5) {
                $remaining[] = $item;
            }
            continue;
        }
        $actorUrl = nammu_fediverse_actor_url($config);
        $announceObject = [
            'id' => trim((string) ($item['activity_id'] ?? '')),
            'type' => 'Announce',
            'actor' => $actorUrl,
            'object' => $objectUrl,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$recipientId],
        ];
        if ($announceObject['id'] === '') {
            unset($announceObject['id']);
        }
        $undoActivity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actorUrl . '/undo-announce/' . substr(sha1($objectUrl . '|' . microtime(true)), 0, 24),
            'type' => 'Undo',
            'actor' => $actorUrl,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$recipientId],
            'object' => $announceObject,
            'published' => gmdate(DATE_ATOM),
        ];
        $successfulDeliveries = 0;
        foreach (array_keys($inboxTargets) as $inboxUrl) {
            $delivery = nammu_fediverse_post_activity_response($inboxUrl, $undoActivity, $config);
            if (!empty($delivery['ok'])) {
                $successfulDeliveries++;
            }
        }
        if ($successfulDeliveries > 0) {
            $sent++;
            continue;
        }
        $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;
        $failed++;
        if ((int) $item['attempts'] < 5) {
            $remaining[] = $item;
        }
    }
    nammu_fediverse_save_undo_announce_queue_store($remaining, $config);
    return [
        'processed' => $processed,
        'sent' => $sent,
        'failed' => $failed,
        'remaining' => count($remaining),
    ];
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
    $noteHash = substr(sha1($recipientId . '|' . $objectUrl . '|' . $plainText . '|' . microtime(true)), 0, 24);
    $noteId = nammu_fediverse_reply_note_url($noteHash, $config);
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
            'context' => $objectUrl,
            'conversation' => $objectUrl,
            'url' => $noteId,
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
        'mention_actor' => 1,
    ]);
    return ['ok' => true, 'message' => 'Respuesta enviada.'];
}

function nammu_fediverse_send_local_reply(string $objectUrl, string $text, array $config): array
{
    $objectUrl = nammu_fediverse_resolve_object_reference(trim($objectUrl), $config);
    $plainText = trim($text);
    if ($objectUrl === '' || $plainText === '') {
        return ['ok' => false, 'message' => 'Falta la publicación o el texto de la respuesta.'];
    }
    $localTarget = nammu_fediverse_find_local_item_for_identifier($objectUrl, $config);
    if (!is_array($localTarget)) {
        return ['ok' => false, 'message' => 'No se encontró la publicación local a la que responder.'];
    }
    $actorUrl = nammu_fediverse_actor_url($config);
    $followersUrl = nammu_fediverse_followers_url($config);
    $noteHash = substr(sha1($actorUrl . '|' . $objectUrl . '|' . $plainText . '|' . microtime(true)), 0, 24);
    $noteId = nammu_fediverse_reply_note_url($noteHash, $config);
    $published = gmdate(DATE_ATOM);
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $noteId . '/activity',
        'type' => 'Create',
        'actor' => $actorUrl,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [$followersUrl],
        'published' => $published,
        'object' => [
            'id' => $noteId,
            'type' => 'Note',
            'attributedTo' => $actorUrl,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$followersUrl],
            'content' => nl2br(htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
            'published' => $published,
            'inReplyTo' => (string) ($localTarget['id'] ?? $objectUrl),
            'context' => (string) ($localTarget['id'] ?? $objectUrl),
            'conversation' => (string) ($localTarget['id'] ?? $objectUrl),
            'url' => $noteId,
        ],
    ];
    $delivered = nammu_fediverse_deliver_activity_to_followers($activity, $config);
    nammu_fediverse_record_action('reply', $actorUrl, (string) ($localTarget['id'] ?? $objectUrl), [
        'reply_text' => $plainText,
        'note_id' => $noteId,
        'activity_id' => (string) ($activity['id'] ?? ''),
        'mention_actor' => 0,
        'is_local_root' => 1,
        'published' => $published,
    ]);
    return ['ok' => true, 'message' => 'Respuesta enviada. Entregas: ' . $delivered . '.'];
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

function nammu_fediverse_hide_incoming_reply(array $reply, array $config): array
{
    $keys = nammu_fediverse_hidden_reply_keys($reply);
    if (empty($keys)) {
        return ['ok' => false, 'message' => 'No se pudo identificar esa respuesta para ocultarla.'];
    }
    $items = nammu_fediverse_hidden_replies_store()['items'];
    foreach ($items as $existing) {
        $existingKeys = array_values(array_filter(array_map('strval', (array) ($existing['keys'] ?? []))));
        if (!array_diff($keys, $existingKeys)) {
            return ['ok' => true, 'message' => 'La respuesta ya estaba oculta.'];
        }
    }
    $items[] = [
        'id' => substr(sha1(implode('|', $keys)), 0, 24),
        'keys' => $keys,
        'target_url' => trim((string) ($reply['target_url'] ?? '')),
        'actor_id' => trim((string) ($reply['actor_id'] ?? '')),
        'reply_text' => trim((string) ($reply['reply_text'] ?? '')),
        'published' => trim((string) ($reply['published'] ?? '')),
        'hidden_at' => gmdate(DATE_ATOM),
    ];
    nammu_fediverse_save_hidden_replies_store($items);
    nammu_fediverse_clear_threads_cache();
    $target = trim((string) ($reply['target_url'] ?? ''));
    if ($target !== '') {
        $localTarget = nammu_fediverse_find_local_item_for_identifier($target, $config);
        if (is_array($localTarget)) {
            nammu_fediverse_notify_followers_of_object_update($localTarget, $config);
        }
    }
    return ['ok' => true, 'message' => 'Respuesta oculta localmente.'];
}

function nammu_fediverse_accept_follow(array $payload, array $config): bool
{
    $result = nammu_fediverse_accept_follow_response($payload, $config);
    return !empty($result['ok']);
}

function nammu_fediverse_handle_inbox_payload(array $payload, array $config, array $headers = [], string $rawBody = ''): array
{
    $verification = nammu_fediverse_verify_inbox_request($payload, $headers, $rawBody, $config);
    nammu_fediverse_store_inbox_activity($payload, [
        'verified' => !empty($verification['verified']),
        'verification_error' => (string) ($verification['error'] ?? ''),
        'signature_key_id' => (string) ($verification['key_id'] ?? ''),
        'signed_headers' => (string) ($verification['signed_headers'] ?? ''),
    ], $config);
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
        $affectedLocalTargets = nammu_fediverse_local_targets_for_deleted_reply($targets, $config);
        nammu_fediverse_remove_timeline_items($targets);
        nammu_fediverse_remove_inbox_activities($targets);
        foreach ($affectedLocalTargets as $affectedLocalTarget) {
            nammu_fediverse_notify_followers_of_object_update($affectedLocalTarget, $config);
        }
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
        $objectType = strtolower(trim((string) ($object['type'] ?? '')));
        $replyTarget = trim((string) ($object['inReplyTo'] ?? ''));
        if ($objectType === 'note' && $replyTarget !== '') {
            $localTarget = nammu_fediverse_find_local_item_for_identifier($replyTarget, $config);
            if (is_array($localTarget)) {
                nammu_fediverse_notify_followers_of_object_update($localTarget, $config);
                nammu_fediverse_relay_public_reply_to_followers($payload, $localTarget, $config);
            }
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
        if ($followerId === '' || $inboxUrl === '' || nammu_fediverse_is_blocked_actor($followerId)) {
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
        if ($followerId === '' || $inboxUrl === '' || nammu_fediverse_is_blocked_actor($followerId)) {
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
        'images' => array_values(array_filter(array_map('strval', is_array($matchedItem['images'] ?? null) ? $matchedItem['images'] : []))),
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

function nammu_fediverse_normalize_named_template(string $template): string
{
    $template = strtolower(trim($template));
    return match ($template) {
        'single', 'draft' => 'post',
        'itinerario' => 'itinerary',
        default => $template,
    };
}

function nammu_fediverse_find_named_local_item(string $slug, string $template, array $config): ?array
{
    $slug = trim($slug);
    $template = nammu_fediverse_normalize_named_template($template);
    if ($slug === '') {
        return null;
    }
    $baseUrl = nammu_fediverse_base_url($config);
    $targetUrl = match ($template) {
        'podcast' => $baseUrl . '/podcast/' . rawurlencode($slug),
        'itinerary' => $baseUrl . '/itinerarios/' . rawurlencode($slug),
        default => $baseUrl . '/' . rawurlencode($slug),
    };
    $targetId = match ($template) {
        'podcast' => $baseUrl . '/ap/objects/podcast-' . rawurlencode($slug),
        'itinerary' => $baseUrl . '/ap/objects/itinerary-' . rawurlencode($slug),
        default => $baseUrl . '/ap/objects/post-' . rawurlencode($slug),
    };
    $targetObjectSuffix = '-' . rawurlencode($slug);
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
            return $item;
        }
    }
    return null;
}

function nammu_fediverse_public_thread_url_for_named_local_item(string $slug, string $template, array $config): string
{
    $item = nammu_fediverse_find_named_local_item($slug, $template, $config);
    $baseUrl = nammu_fediverse_base_url($config);
    $normalizedTemplate = nammu_fediverse_normalize_named_template($template);
    $fallbackUrl = match ($normalizedTemplate) {
        'podcast' => $baseUrl . '/podcast/' . rawurlencode(trim($slug)),
        'itinerary' => $baseUrl . '/itinerarios/' . rawurlencode(trim($slug)),
        default => $baseUrl . '/' . rawurlencode(trim($slug)),
    };
    $fallbackObjectId = match ($normalizedTemplate) {
        'podcast' => $baseUrl . '/ap/objects/podcast-' . rawurlencode(trim($slug)),
        'itinerary' => $baseUrl . '/ap/objects/itinerary-' . rawurlencode(trim($slug)),
        default => $baseUrl . '/ap/objects/post-' . rawurlencode(trim($slug)),
    };
    $itemId = is_array($item) ? trim((string) ($item['id'] ?? '')) : $fallbackObjectId;
    if ($itemId === '' || in_array($itemId, nammu_fediverse_deleted_store()['ids'], true)) {
        return '';
    }
    return nammu_fediverse_thread_page_url($itemId, $config);
}

function nammu_fediverse_thread_meta_score(array $meta): int
{
    $summary = is_array($meta['summary'] ?? null) ? $meta['summary'] : [];
    return max(0, (int) ($summary['likes'] ?? 0))
        + max(0, (int) ($summary['shares'] ?? 0))
        + max(0, (int) ($summary['replies'] ?? 0));
}

function nammu_fediverse_is_named_local_object_id(string $itemId, array $config): bool
{
    $itemId = trim($itemId);
    if ($itemId === '') {
        return false;
    }
    $baseUrl = rtrim(nammu_fediverse_base_url($config), '/');
    return preg_match('#^' . preg_quote($baseUrl, '#') . '/ap/objects/(post|podcast|itinerary)-#', $itemId) === 1;
}

function nammu_fediverse_thread_payload_score(array $payload): int
{
    $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
    $details = is_array($payload['details'] ?? null) ? $payload['details'] : [];
    $score = max(0, (int) ($summary['likes'] ?? 0))
        + max(0, (int) ($summary['shares'] ?? 0))
        + max(0, (int) ($summary['replies'] ?? 0));
    $score += count((array) ($details['likes'] ?? []))
        + count((array) ($details['shares'] ?? []))
        + count((array) ($details['replies'] ?? []));

    foreach ((array) ($payload['replies'] ?? []) as $reply) {
        if (!is_array($reply)) {
            continue;
        }
        $replySummary = is_array($reply['summary'] ?? null) ? $reply['summary'] : [];
        $replyDetails = is_array($reply['details'] ?? null) ? $reply['details'] : [];
        $score += max(0, (int) ($replySummary['likes'] ?? 0))
            + max(0, (int) ($replySummary['shares'] ?? 0))
            + max(0, (int) ($replySummary['replies'] ?? 0));
        $score += count((array) ($replyDetails['likes'] ?? []))
            + count((array) ($replyDetails['shares'] ?? []))
            + count((array) ($replyDetails['replies'] ?? []));
    }

    return $score;
}

function nammu_fediverse_best_snapshot_meta_for_items(array $items, array $config): ?array
{
    $bestMeta = null;
    $bestScore = -1;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemId = trim((string) ($item['id'] ?? ''));
        if ($itemId === '' || in_array($itemId, nammu_fediverse_deleted_store()['ids'], true)) {
            continue;
        }
        $payload = nammu_fediverse_thread_page_snapshot_payload($item, $config);
        $meta = [
            'thread_url' => nammu_fediverse_thread_page_url($itemId, $config),
            'summary' => is_array($payload['summary'] ?? null)
                ? $payload['summary']
                : ['likes' => 0, 'shares' => 0, 'replies' => 0],
            'details' => is_array($payload['details'] ?? null)
                ? $payload['details']
                : ['likes' => [], 'shares' => [], 'replies' => []],
        ];
        $score = nammu_fediverse_thread_meta_score($meta);
        if ($bestMeta === null || $score >= $bestScore) {
            $bestMeta = $meta;
            $bestScore = $score;
        }
    }
    return $bestMeta;
}

function nammu_fediverse_home_snapshot_meta_for_local_identifiers(array $candidateIdentifiers, array $config): ?array
{
    $candidateIdentifiers = array_values(array_unique(array_filter(array_map(static function ($value): string {
        return trim((string) $value);
    }, $candidateIdentifiers))));
    if (empty($candidateIdentifiers)) {
        return null;
    }

    $candidatePaths = [];
    foreach ($candidateIdentifiers as $identifier) {
        $path = trim((string) (parse_url($identifier, PHP_URL_PATH) ?? ''));
        if ($path !== '') {
            $candidatePaths[rtrim('/' . ltrim($path, '/'), '/')] = true;
        }
    }

    $snapshot = nammu_fediverse_home_snapshot_store();
    $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
    $localItems = is_array($data['local_items'] ?? null) ? $data['local_items'] : [];
    $threadPayloads = is_array($data['thread_payloads'] ?? null) ? $data['thread_payloads'] : [];
    $summaryMap = is_array($data['local_reaction_summary'] ?? null) ? $data['local_reaction_summary'] : [];
    $detailsMap = is_array($data['local_reaction_details'] ?? null) ? $data['local_reaction_details'] : [];

    foreach ($localItems as $localItem) {
        if (!is_array($localItem)) {
            continue;
        }
        $itemId = trim((string) ($localItem['id'] ?? ''));
        $itemUrl = trim((string) ($localItem['url'] ?? ''));
        if ($itemId === '') {
            continue;
        }
        $itemIdentifiers = [$itemId];
        if ($itemUrl !== '') {
            $itemIdentifiers[] = $itemUrl;
        }
        foreach (nammu_fediverse_local_item_alias_identifiers($localItem, $config) as $aliasIdentifier) {
            $itemIdentifiers[] = $aliasIdentifier;
        }
        $itemIdentifiers = array_values(array_unique(array_filter(array_map('trim', $itemIdentifiers))));

        $matched = false;
        foreach ($itemIdentifiers as $itemIdentifier) {
            if (in_array($itemIdentifier, $candidateIdentifiers, true)) {
                $matched = true;
                break;
            }
            $itemPath = trim((string) (parse_url($itemIdentifier, PHP_URL_PATH) ?? ''));
            if ($itemPath !== '' && isset($candidatePaths[rtrim('/' . ltrim($itemPath, '/'), '/')])) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            continue;
        }

        $payload = is_array($threadPayloads[$itemId] ?? null) ? $threadPayloads[$itemId] : null;
        return [
            'thread_url' => nammu_fediverse_thread_page_url($itemId, $config),
            'summary' => is_array($payload['summary'] ?? null)
                ? $payload['summary']
                : (is_array($summaryMap[$itemId] ?? null) ? $summaryMap[$itemId] : ['likes' => 0, 'shares' => 0, 'replies' => 0]),
            'details' => is_array($payload['details'] ?? null)
                ? $payload['details']
                : (is_array($detailsMap[$itemId] ?? null) ? $detailsMap[$itemId] : ['likes' => [], 'shares' => [], 'replies' => []]),
        ];
    }

    return null;
}

function nammu_fediverse_home_snapshot_meta_for_local_id(string $itemId, array $config): ?array
{
    $itemId = trim($itemId);
    if ($itemId === '') {
        return null;
    }
    $snapshot = nammu_fediverse_home_snapshot_store();
    $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
    $threadPayloads = is_array($data['thread_payloads'] ?? null) ? $data['thread_payloads'] : [];
    $summaryMap = is_array($data['local_reaction_summary'] ?? null) ? $data['local_reaction_summary'] : [];
    $detailsMap = is_array($data['local_reaction_details'] ?? null) ? $data['local_reaction_details'] : [];
    $payload = is_array($threadPayloads[$itemId] ?? null) ? $threadPayloads[$itemId] : null;
    $summary = is_array($payload['summary'] ?? null)
        ? $payload['summary']
        : (is_array($summaryMap[$itemId] ?? null) ? $summaryMap[$itemId] : null);
    $details = is_array($payload['details'] ?? null)
        ? $payload['details']
        : (is_array($detailsMap[$itemId] ?? null) ? $detailsMap[$itemId] : null);
    if (!is_array($summary) && !is_array($details)) {
        return null;
    }
    return [
        'thread_url' => nammu_fediverse_thread_page_url($itemId, $config),
        'summary' => is_array($summary) ? $summary : ['likes' => 0, 'shares' => 0, 'replies' => 0],
        'details' => is_array($details) ? $details : ['likes' => [], 'shares' => [], 'replies' => []],
    ];
}

function nammu_fediverse_public_thread_meta_for_named_local_item(string $slug, string $template, array $config): array
{
    static $snapshotMaps = [];
    $normalizedSlug = trim($slug);
    $normalizedTemplate = nammu_fediverse_normalize_named_template($template);
    $cacheKey = md5(nammu_fediverse_base_url($config));

    if (!isset($snapshotMaps[$cacheKey])) {
        $snapshotMaps[$cacheKey] = [];
        $snapshot = nammu_fediverse_home_snapshot_store();
        $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
        $localItems = is_array($data['local_items'] ?? null) ? $data['local_items'] : [];
        $threadPayloads = is_array($data['thread_payloads'] ?? null) ? $data['thread_payloads'] : [];
        foreach ($localItems as $localItem) {
            if (!is_array($localItem)) {
                continue;
            }
            $itemId = trim((string) ($localItem['id'] ?? ''));
            $itemUrl = trim((string) ($localItem['url'] ?? ''));
            if ($itemId === '' || $itemUrl === '') {
                continue;
            }
            $path = trim((string) (parse_url($itemUrl, PHP_URL_PATH) ?? ''));
            if ($path === '') {
                continue;
            }
            $path = '/' . ltrim($path, '/');
            $key = null;
            if (preg_match('#^/podcast/([^/]+)/?$#', $path, $matches) === 1) {
                $key = 'podcast|' . rawurldecode((string) ($matches[1] ?? ''));
            } elseif (preg_match('#^/itinerarios/([^/]+)/?$#', $path, $matches) === 1) {
                $key = 'itinerary|' . rawurldecode((string) ($matches[1] ?? ''));
            } elseif (preg_match('#^/([^/]+)$#', $path, $matches) === 1) {
                $candidateSlug = rawurldecode((string) ($matches[1] ?? ''));
                if ($candidateSlug !== '' && !in_array($candidateSlug, ['actualidad.php', 'podcast', 'itinerarios', 'categorias', 'buscar.php'], true) && !str_starts_with($candidateSlug, '@')) {
                    $key = 'post|' . $candidateSlug;
                }
            }
            if ($key === null) {
                continue;
            }
            $payload = is_array($threadPayloads[$itemId] ?? null) ? $threadPayloads[$itemId] : null;
            $candidate = [
                'thread_url' => nammu_fediverse_thread_page_url($itemId, $config),
                'summary' => is_array($payload['summary'] ?? null)
                    ? $payload['summary']
                    : ['likes' => 0, 'shares' => 0, 'replies' => 0],
                'details' => is_array($payload['details'] ?? null)
                    ? $payload['details']
                    : ['likes' => [], 'shares' => [], 'replies' => []],
            ];
            $candidateScore = (int) ($candidate['summary']['likes'] ?? 0)
                + (int) ($candidate['summary']['shares'] ?? 0)
                + (int) ($candidate['summary']['replies'] ?? 0);
            $existing = is_array($snapshotMaps[$cacheKey][$key] ?? null) ? $snapshotMaps[$cacheKey][$key] : null;
            $existingScore = is_array($existing)
                ? ((int) ($existing['summary']['likes'] ?? 0) + (int) ($existing['summary']['shares'] ?? 0) + (int) ($existing['summary']['replies'] ?? 0))
                : -1;
            if (!is_array($existing) || $candidateScore >= $existingScore) {
                $snapshotMaps[$cacheKey][$key] = $candidate;
            }
        }
    }

    $snapshotKey = $normalizedTemplate . '|' . $normalizedSlug;
    if ($normalizedSlug !== '' && isset($snapshotMaps[$cacheKey][$snapshotKey])) {
        return $snapshotMaps[$cacheKey][$snapshotKey];
    }

    $baseUrl = nammu_fediverse_base_url($config);
    $candidateUrl = match ($normalizedTemplate) {
        'podcast' => $baseUrl . '/podcast/' . rawurlencode($normalizedSlug),
        'itinerary' => $baseUrl . '/itinerarios/' . rawurlencode($normalizedSlug),
        default => $baseUrl . '/' . rawurlencode($normalizedSlug),
    };
    $candidateId = match ($normalizedTemplate) {
        'podcast' => $baseUrl . '/ap/objects/podcast-' . rawurlencode($normalizedSlug),
        'itinerary' => $baseUrl . '/ap/objects/itinerary-' . rawurlencode($normalizedSlug),
        default => $baseUrl . '/ap/objects/post-' . rawurlencode($normalizedSlug),
    };
    $snapshotMeta = nammu_fediverse_home_snapshot_meta_for_local_identifiers([$candidateId, $candidateUrl], $config);
    if (is_array($snapshotMeta)) {
        return $snapshotMeta;
    }

    $item = nammu_fediverse_find_named_local_item($normalizedSlug, $normalizedTemplate, $config);
    if (is_array($item)) {
        $equivalentItems = nammu_fediverse_equivalent_local_items_by_url((string) ($item['url'] ?? ''), $config);
        $bestMeta = nammu_fediverse_best_snapshot_meta_for_items($equivalentItems, $config);
        if (is_array($bestMeta) && nammu_fediverse_thread_meta_score($bestMeta) > 0) {
            return $bestMeta;
        }
    }
    $threadUrl = nammu_fediverse_public_thread_url_for_named_local_item($normalizedSlug, $normalizedTemplate, $config);
    $payload = null;
    if (is_array($item)) {
        $payload = nammu_fediverse_thread_page_snapshot_payload($item, $config);
        $itemId = trim((string) ($item['id'] ?? ''));
        if (nammu_fediverse_is_named_local_object_id($itemId, $config)
            && (!is_array($payload) || nammu_fediverse_thread_payload_score($payload) <= 0)
        ) {
            $bestPayload = nammu_fediverse_best_thread_page_payload($item, $config);
            if (nammu_fediverse_thread_payload_score($bestPayload) > nammu_fediverse_thread_payload_score((array) $payload)) {
                $payload = $bestPayload;
            }
        }
    }

    return [
        'thread_url' => $threadUrl,
        'summary' => is_array($payload['summary'] ?? null)
            ? $payload['summary']
            : ['likes' => 0, 'shares' => 0, 'replies' => 0],
        'details' => is_array($payload['details'] ?? null)
            ? $payload['details']
            : ['likes' => [], 'shares' => [], 'replies' => []],
    ];
}

function nammu_fediverse_public_thread_url_for_actuality_item(array $actualityItem, array $config): string
{
    $manualId = trim((string) ($actualityItem['id'] ?? ''));
    if ($manualId === '' && function_exists('nammu_actuality_news_item_id')) {
        $manualId = trim((string) nammu_actuality_news_item_id($actualityItem));
    }
    if ($manualId !== '') {
        $itemId = nammu_fediverse_base_url($config) . '/ap/objects/actualidad-' . rawurlencode($manualId);
        if (!in_array($itemId, nammu_fediverse_deleted_store()['ids'], true)) {
            return nammu_fediverse_thread_page_url($itemId, $config);
        }
    }
    return '';
}

function nammu_fediverse_public_thread_meta_for_actuality_item(array $actualityItem, array $config): array
{
    $manualId = trim((string) ($actualityItem['id'] ?? ''));
    if ($manualId === '' && function_exists('nammu_actuality_news_item_id')) {
        $manualId = trim((string) nammu_actuality_news_item_id($actualityItem));
    }
    if ($manualId !== '') {
        $itemId = nammu_fediverse_base_url($config) . '/ap/objects/actualidad-' . rawurlencode($manualId);
        $exactSnapshotMeta = nammu_fediverse_home_snapshot_meta_for_local_id($itemId, $config);
        if (is_array($exactSnapshotMeta)) {
            return $exactSnapshotMeta;
        }
        $item = nammu_fediverse_find_local_item_for_identifier($itemId, $config);
        if (is_array($item)) {
            $payload = nammu_fediverse_thread_page_snapshot_payload($item, $config);
            return [
                'thread_url' => nammu_fediverse_thread_page_url($itemId, $config),
                'summary' => is_array($payload['summary'] ?? null)
                    ? $payload['summary']
                    : ['likes' => 0, 'shares' => 0, 'replies' => 0],
                'details' => is_array($payload['details'] ?? null)
                    ? $payload['details']
                    : ['likes' => [], 'shares' => [], 'replies' => []],
            ];
        }
    }
    return [
        'thread_url' => '',
        'summary' => ['likes' => 0, 'shares' => 0, 'replies' => 0],
        'details' => ['likes' => [], 'shares' => [], 'replies' => []],
    ];
}

function nammu_fediverse_delete_local_item(string $itemId, array $config): array
{
    $mark = nammu_fediverse_mark_local_item_deleted($itemId, $config);
    if (empty($mark['ok'])) {
        return $mark;
    }
    return nammu_fediverse_deliver_delete_activity($itemId, $config);
}

function nammu_fediverse_mark_local_item_deleted(string $itemId, array $config): array
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
    if (!in_array($itemId, $deletedIds, true)) {
        $deletedEntries = (array) (nammu_fediverse_deleted_store()['items'] ?? []);
        $deletedEntries[] = [
            'id' => $itemId,
            'deleted_at' => gmdate(DATE_ATOM),
        ];
        nammu_fediverse_save_deleted_store($deletedEntries);
    }
    return ['ok' => true, 'message' => 'Publicación marcada para borrado en el Fediverso.'];
}

function nammu_fediverse_build_delete_activity(string $itemId, array $config, string $published = ''): array
{
    $actorUrl = nammu_fediverse_actor_url($config);
    $published = trim($published);
    if ($published === '') {
        $published = gmdate(DATE_ATOM);
    }
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $actorUrl . '/delete/' . substr(sha1($itemId . '|' . $published), 0, 24),
        'type' => 'Delete',
        'actor' => $actorUrl,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'object' => $itemId,
        'published' => $published,
    ];
}

function nammu_fediverse_deliver_delete_activity(string $itemId, array $config): array
{
    $itemId = trim($itemId);
    if ($itemId === '') {
        return ['ok' => false, 'delivered' => 0, 'message' => 'No se recibió la publicación a borrar del Fediverso.'];
    }
    $deleteActivity = nammu_fediverse_build_delete_activity($itemId, $config);
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
    return ['ok' => true, 'delivered' => $delivered, 'message' => 'Publicación retirada del Fediverso. Entregas de borrado: ' . $delivered . '.'];
}

function nammu_fediverse_enqueue_delete_local_item(string $itemId, array $config): array
{
    $mark = nammu_fediverse_mark_local_item_deleted($itemId, $config);
    if (empty($mark['ok'])) {
        return $mark;
    }
    $queue = nammu_fediverse_delete_queue_store($config);
    $items = is_array($queue['items'] ?? null) ? $queue['items'] : [];
    foreach ($items as $queued) {
        if (trim((string) ($queued['item_id'] ?? '')) === $itemId) {
            return ['ok' => true, 'queued' => count($items), 'message' => 'Publicación borrada localmente y ya en cola de borrado federado.'];
        }
    }
    $items[] = [
        'item_id' => $itemId,
        'created_at' => gmdate(DATE_ATOM),
        'attempts' => 0,
    ];
    nammu_fediverse_save_delete_queue_store($items, $config);
    return ['ok' => true, 'queued' => count($items), 'message' => 'Publicación borrada localmente y encolada para borrado federado.'];
}

function nammu_fediverse_process_delete_queue(array $config, int $maxJobs = 3): array
{
    $queue = nammu_fediverse_delete_queue_store($config);
    $items = is_array($queue['items'] ?? null) ? array_values($queue['items']) : [];
    if ($maxJobs < 1) {
        $maxJobs = 1;
    }
    $processed = 0;
    $sent = 0;
    $failed = 0;
    $remaining = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemId = trim((string) ($item['item_id'] ?? ''));
        if ($itemId === '') {
            continue;
        }
        if ($processed >= $maxJobs) {
            $remaining[] = $item;
            continue;
        }
        $processed++;
        $result = nammu_fediverse_deliver_delete_activity($itemId, $config);
        if (!empty($result['ok'])) {
            $sent++;
            continue;
        }
        $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;
        $failed++;
        if ((int) $item['attempts'] < 5) {
            $remaining[] = $item;
        }
    }
    nammu_fediverse_save_delete_queue_store($remaining, $config);
    return [
        'processed' => $processed,
        'sent' => $sent,
        'failed' => $failed,
        'remaining' => count($remaining),
    ];
}

function nammu_fediverse_replay_all_deletes(array $config): array
{
    $deletedItems = array_values(array_filter((array) (nammu_fediverse_deleted_store()['items'] ?? []), static function ($item): bool {
        return is_array($item) && trim((string) ($item['id'] ?? '')) !== '';
    }));
    if (empty($deletedItems)) {
        return [
            'requeued' => 0,
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'remaining' => 0,
        ];
    }
    $queue = nammu_fediverse_delete_queue_store($config);
    $queuedById = [];
    foreach ((array) ($queue['items'] ?? []) as $queued) {
        if (!is_array($queued)) {
            continue;
        }
        $queuedId = trim((string) ($queued['item_id'] ?? ''));
        if ($queuedId !== '') {
            $queuedById[$queuedId] = $queued;
        }
    }
    $requeued = 0;
    foreach ($deletedItems as $deletedItem) {
        $itemId = trim((string) ($deletedItem['id'] ?? ''));
        if ($itemId === '') {
            continue;
        }
        if (!isset($queuedById[$itemId])) {
            $queuedById[$itemId] = [
                'item_id' => $itemId,
                'created_at' => trim((string) ($deletedItem['deleted_at'] ?? '')) ?: gmdate(DATE_ATOM),
                'attempts' => 0,
            ];
            $requeued++;
        }
    }
    nammu_fediverse_save_delete_queue_store(array_values($queuedById), $config);
    $processed = nammu_fediverse_process_delete_queue($config, max(1, count($deletedItems)));
    $processed['requeued'] = $requeued;
    return $processed;
}
