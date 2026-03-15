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
    return nammu_fediverse_actor_url($config) . '#main-key';
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
            'icon' => is_array($actor['icon'] ?? null) ? (string) (($actor['icon']['url'] ?? '') ?: '') : '',
            'public_key_id' => is_array($actor['publicKey'] ?? null) ? (string) (($actor['publicKey']['id'] ?? '') ?: '') : '',
            'public_key_pem' => is_array($actor['publicKey'] ?? null) ? (string) (($actor['publicKey']['publicKeyPem'] ?? '') ?: '') : '',
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

function nammu_fediverse_follow_actor(string $input): array
{
    $config = function_exists('nammu_load_config') ? nammu_load_config() : [];
    $actor = nammu_fediverse_resolve_actor($input, $config);
    if (!is_array($actor) || trim((string) ($actor['id'] ?? '')) === '') {
        return ['ok' => false, 'message' => 'No se pudo descubrir ese actor ActivityPub.'];
    }
    $store = nammu_fediverse_following_store();
    $actors = $store['actors'];
    foreach ($actors as &$existing) {
        if ((string) ($existing['id'] ?? '') !== (string) $actor['id']) {
            continue;
        }
        $existing = array_merge($existing, $actor, ['followed_at' => $existing['followed_at'] ?? gmdate(DATE_ATOM)]);
        nammu_fediverse_save_following_store($actors);
        return ['ok' => true, 'message' => 'Ese actor ya estaba seguido y se ha actualizado su ficha.'];
    }
    unset($existing);
    $actor['followed_at'] = gmdate(DATE_ATOM);
    $actor['last_checked_at'] = '';
    $actor['last_error'] = '';
    $actors[] = $actor;
    nammu_fediverse_save_following_store($actors);
    return ['ok' => true, 'message' => 'Actor añadido al Fediverso.'];
}

function nammu_fediverse_unfollow_actor(string $actorId): bool
{
    $actors = nammu_fediverse_following_store()['actors'];
    $before = count($actors);
    $actors = array_values(array_filter($actors, static function (array $actor) use ($actorId): bool {
        return (string) ($actor['id'] ?? '') !== $actorId;
    }));
    if ($before === count($actors)) {
        return false;
    }
    nammu_fediverse_save_following_store($actors);
    return true;
}

function nammu_fediverse_normalize_remote_item(array $activity, array $actor): ?array
{
    $type = strtolower((string) ($activity['type'] ?? ''));
    $object = $activity;
    if ($type === 'create' && is_array($activity['object'] ?? null)) {
        $object = $activity['object'];
        $type = strtolower((string) ($object['type'] ?? 'note'));
    }
    $id = trim((string) (($activity['id'] ?? '') ?: ($object['id'] ?? '')));
    if ($id === '') {
        return null;
    }
    $url = '';
    if (is_string($object['url'] ?? null)) {
        $url = trim((string) $object['url']);
    } elseif (is_array($object['url'] ?? null)) {
        foreach ((array) $object['url'] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $url = trim($candidate);
                break;
            }
            if (is_array($candidate) && trim((string) ($candidate['href'] ?? '')) !== '') {
                $url = trim((string) $candidate['href']);
                break;
            }
        }
    }
    $published = trim((string) (($object['published'] ?? '') ?: ($activity['published'] ?? '')));
    $content = trim((string) (($object['content'] ?? '') ?: ($object['summary'] ?? '')));
    $name = trim((string) (($object['name'] ?? '') ?: ''));
    $image = '';
    if (is_array($object['image'] ?? null)) {
        $image = trim((string) (($object['image']['url'] ?? '') ?: ''));
    } elseif (is_string($object['image'] ?? null)) {
        $image = trim((string) $object['image']);
    }
    return [
        'id' => $id,
        'url' => $url !== '' ? $url : $id,
        'title' => $name,
        'content' => $content,
        'published' => $published !== '' ? $published : gmdate(DATE_ATOM),
        'type' => $type !== '' ? $type : 'note',
        'image' => $image,
        'actor_id' => (string) ($actor['id'] ?? ''),
        'actor_name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? ''))),
        'actor_url' => trim((string) (($actor['url'] ?? '') ?: ($actor['id'] ?? ''))),
    ];
}

function nammu_fediverse_extract_outbox_items(array $outbox, array $actor): array
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
        if (!is_array($rawItem)) {
            continue;
        }
        $normalized = nammu_fediverse_normalize_remote_item($rawItem, $actor);
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
        foreach (nammu_fediverse_extract_outbox_items($outbox, $actor) as $item) {
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

function nammu_fediverse_local_content_items(array $config): array
{
    $baseUrl = nammu_fediverse_base_url($config);
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
        $objectType = $template === 'podcast' ? 'Article' : 'Article';
        $items[] = [
            'id' => $baseUrl . '/ap/objects/' . rawurlencode($template) . '-' . rawurlencode($slug),
            'url' => $url,
            'title' => $title,
            'content' => $description !== '' ? $description : nammu_fediverse_plain_excerpt($content),
            'published' => nammu_fediverse_parse_date((string) (($meta['Date'] ?? $meta['date'] ?? '') ?: '')),
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
        $items[] = [
            'id' => $baseUrl . '/ap/objects/itinerary-' . rawurlencode($slug),
            'url' => $baseUrl . '/itinerarios/' . rawurlencode($slug),
            'title' => $title,
            'content' => $description !== '' ? $description : nammu_fediverse_plain_excerpt($content),
            'published' => nammu_fediverse_parse_date((string) (($meta['Date'] ?? $meta['date'] ?? '') ?: '')),
            'type' => 'Article',
            'image' => $image,
        ];
    }
    $actualityStore = nammu_fediverse_load_json_store(dirname(__DIR__) . '/config/actualidad-items.json', ['items' => []]);
    foreach ((array) ($actualityStore['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = trim((string) (($item['id'] ?? '') ?: sha1(json_encode($item))));
        $isManual = !empty($item['is_manual']);
        $title = trim((string) ($item['title'] ?? ''));
        $content = trim((string) (($item['raw_text'] ?? '') ?: ($item['description'] ?? '')));
        $items[] = [
            'id' => $baseUrl . '/ap/objects/actualidad-' . rawurlencode($id),
            'url' => trim((string) (($item['link'] ?? '') ?: ($baseUrl . '/actualidad.php'))),
            'title' => $title !== '' ? $title : ($isManual ? 'Nota' : 'Noticia'),
            'content' => $content,
            'published' => gmdate(DATE_ATOM, (int) (($item['timestamp'] ?? 0) ?: time())),
            'type' => $isManual ? 'Note' : 'Article',
            'image' => trim((string) ($item['image'] ?? '')),
        ];
    }
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
    });
    return array_slice($items, 0, 80);
}

function nammu_fediverse_activity_for_local_item(array $item, array $config): array
{
    $actorUrl = nammu_fediverse_actor_url($config);
    $object = [
        'id' => (string) ($item['id'] ?? ''),
        'type' => (string) ($item['type'] ?? 'Article'),
        'attributedTo' => $actorUrl,
        'url' => (string) ($item['url'] ?? ''),
        'name' => (string) ($item['title'] ?? ''),
        'content' => nl2br(htmlspecialchars((string) ($item['content'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
        'published' => (string) ($item['published'] ?? gmdate(DATE_ATOM)),
    ];
    $image = trim((string) ($item['image'] ?? ''));
    if ($image !== '') {
        $object['image'] = ['type' => 'Image', 'url' => $image];
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
    $document = [
        '@context' => [
            'https://www.w3.org/ns/activitystreams',
            'https://w3id.org/security/v1',
        ],
        'id' => $actorUrl,
        'type' => 'Person',
        'preferredUsername' => nammu_fediverse_preferred_username($config),
        'name' => $siteName,
        'summary' => $siteDescription,
        'url' => $baseUrl,
        'inbox' => nammu_fediverse_inbox_url($config),
        'outbox' => nammu_fediverse_outbox_url($config),
        'followers' => nammu_fediverse_followers_url($config),
        'following' => nammu_fediverse_following_url($config),
        'discoverable' => true,
        'manuallyApprovesFollowers' => false,
        'published' => gmdate(DATE_ATOM, is_file(dirname(__DIR__) . '/index.php') ? ((int) @filemtime(dirname(__DIR__) . '/index.php') ?: time()) : time()),
    ];
    $icon = '';
    if (function_exists('nammu_template_settings')) {
        $theme = nammu_template_settings();
        $icon = trim((string) ($theme['logo_url'] ?? ''));
    }
    if ($icon === '' && !empty($config['social']['home_image'])) {
        $icon = nammu_fediverse_asset_url((string) $config['social']['home_image'], $baseUrl);
    }
    if ($icon !== '') {
        $document['icon'] = [
            'type' => 'Image',
            'url' => $icon,
        ];
    }
    if (!empty($keys['public_key'])) {
        $document['publicKey'] = [
            'id' => $actorUrl . '#main-key',
            'owner' => $actorUrl,
            'publicKeyPem' => $keys['public_key'],
        ];
    }
    return $document;
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
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => nammu_fediverse_outbox_url($config),
        'type' => 'OrderedCollection',
        'totalItems' => count($activities),
        'orderedItems' => $activities,
    ];
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
        return !nammu_fediverse_is_direct_message_activity($payload, $config);
    }));
    return array_values(array_reverse($filtered));
}

function nammu_fediverse_post_activity(string $inboxUrl, array $activity, array $config): bool
{
    $body = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($body) || $body === '') {
        return false;
    }
    $response = nammu_fediverse_signed_fetch($inboxUrl, $config, 'POST', $body);
    $status = (int) ($response['status'] ?? 0);
    return $status >= 200 && $status < 300;
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
        $digestHeader = trim((string) ($headers['digest'] ?? ''));
        if ($digestHeader === '') {
            return ['verified' => false, 'error' => 'Falta la cabecera Digest.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
        }
        $expectedDigest = nammu_fediverse_digest_header($rawBody);
        if (!hash_equals($expectedDigest, $digestHeader)) {
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
        return ['verified' => false, 'error' => 'El keyId de la firma no coincide con la clave pública del actor remoto.', 'key_id' => $keyId, 'signed_headers' => $signedHeaders];
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
    $content = nl2br(htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $messageId . '/activity',
        'type' => 'Create',
        'actor' => $actorUrl,
        'to' => [(string) ($recipient['id'] ?? '')],
        'object' => [
            'id' => $messageId,
            'type' => 'Note',
            'attributedTo' => $actorUrl,
            'to' => [(string) ($recipient['id'] ?? '')],
            'content' => $content,
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
    if ($type === 'create' && is_array($payload['object'] ?? null)) {
        $object = $payload['object'];
        if ($actorId !== '' && nammu_fediverse_is_direct_message_activity($payload, $config)) {
            $remoteActor = nammu_fediverse_resolve_actor($actorId, $config);
            nammu_fediverse_store_message([
                'id' => trim((string) (($object['id'] ?? '') ?: ($payload['id'] ?? sha1(json_encode($payload))))),
                'activity_id' => trim((string) ($payload['id'] ?? '')),
                'actor_id' => $actorId,
                'actor_name' => trim((string) (($remoteActor['name'] ?? '') ?: ($remoteActor['preferredUsername'] ?? '') ?: $actorId)),
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
