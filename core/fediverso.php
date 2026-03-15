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

function nammu_fediverse_resolve_actor(string $input): ?array
{
    $trimmed = trim($input);
    if ($trimmed === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $trimmed)) {
        $actor = nammu_fediverse_fetch_json($trimmed);
        if (!is_array($actor)) {
            return null;
        }
        return [
            'id' => (string) ($actor['id'] ?? $trimmed),
            'preferredUsername' => (string) ($actor['preferredUsername'] ?? ''),
            'name' => trim((string) (($actor['name'] ?? '') ?: ($actor['preferredUsername'] ?? ''))),
            'inbox' => (string) ($actor['inbox'] ?? ''),
            'outbox' => (string) ($actor['outbox'] ?? ''),
            'url' => (string) ($actor['url'] ?? ($actor['id'] ?? $trimmed)),
            'icon' => is_array($actor['icon'] ?? null) ? (string) (($actor['icon']['url'] ?? '') ?: '') : '',
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
    return nammu_fediverse_resolve_actor($actorUrl);
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

function nammu_fediverse_follow_actor(string $input): array
{
    $actor = nammu_fediverse_resolve_actor($input);
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
        $actorDoc = nammu_fediverse_resolve_actor((string) ($actor['id'] ?? ''));
        if (!is_array($actorDoc) || trim((string) ($actorDoc['outbox'] ?? '')) === '') {
            $actor['last_error'] = 'No se pudo refrescar el actor remoto.';
            continue;
        }
        $actor = array_merge($actor, $actorDoc);
        $outbox = nammu_fediverse_fetch_json((string) $actor['outbox']);
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
    $keys = nammu_fediverse_keypair();
    $document = [
        '@context' => [
            'https://www.w3.org/ns/activitystreams',
            'https://w3id.org/security/v1',
        ],
        'id' => $actorUrl,
        'type' => 'Application',
        'preferredUsername' => nammu_fediverse_preferred_username($config),
        'name' => $siteName,
        'summary' => trim((string) ($config['site_description'] ?? '')),
        'url' => $baseUrl,
        'inbox' => nammu_fediverse_inbox_url($config),
        'outbox' => nammu_fediverse_outbox_url($config),
        'followers' => nammu_fediverse_followers_url($config),
        'following' => nammu_fediverse_following_url($config),
    ];
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
    $inbox = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['followers' => []]);
    $followers = array_values(array_filter(array_map('strval', (array) ($inbox['followers'] ?? []))));
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

function nammu_fediverse_store_inbox_activity(array $payload): void
{
    $store = nammu_fediverse_load_json_store(nammu_fediverse_inbox_file(), ['activities' => [], 'followers' => []]);
    $activities = is_array($store['activities'] ?? null) ? $store['activities'] : [];
    $activities[] = [
        'received_at' => gmdate(DATE_ATOM),
        'payload' => $payload,
    ];
    $store['activities'] = array_slice($activities, -50);
    $type = strtolower((string) ($payload['type'] ?? ''));
    $actor = trim((string) ($payload['actor'] ?? ''));
    if ($type === 'follow' && $actor !== '') {
        $followers = array_values(array_unique(array_filter(array_map('strval', (array) ($store['followers'] ?? [])))));
        $followers[] = $actor;
        $store['followers'] = array_values(array_unique($followers));
    }
    if ($type === 'undo' && is_array($payload['object'] ?? null)) {
        $object = $payload['object'];
        if (strtolower((string) ($object['type'] ?? '')) === 'follow' && $actor !== '') {
            $store['followers'] = array_values(array_filter(
                array_map('strval', (array) ($store['followers'] ?? [])),
                static fn(string $value): bool => $value !== $actor
            ));
        }
    }
    nammu_fediverse_save_json_store(nammu_fediverse_inbox_file(), $store);
}
