<?php

function admin_social_broadcast_limits(): array
{
    return [
        'telegram' => 4096,
        'facebook' => 63206,
        'twitter' => 280,
        'bluesky' => 300,
        'mastodon' => 2000,
        'linkedin' => 3000,
    ];
}

function admin_social_rss_state_file(): string
{
    return __DIR__ . '/../config/social-rss-state.json';
}

function admin_social_rss_settings(array $settings): array
{
    $rss = is_array($settings['social_rss'] ?? null) ? $settings['social_rss'] : [];
    $feedsRaw = trim((string) ($rss['feeds'] ?? ''));
    $networks = array_values(array_unique(array_filter(array_map('strval', $rss['networks'] ?? []))));
    return [
        'feeds' => $feedsRaw,
        'networks' => $networks,
    ];
}

function admin_social_rss_feed_list(string $feedsRaw): array
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

function admin_load_social_rss_state(): array
{
    $file = admin_social_rss_state_file();
    if (!is_file($file)) {
        return ['feeds' => []];
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return ['feeds' => []];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['feeds' => []];
}

function admin_save_social_rss_state(array $state): void
{
    $file = admin_social_rss_state_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @chmod($file, 0664);
}

function admin_fetch_social_rss_items(string $url): array
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
        foreach ($xml->channel->item as $item) {
            $title = trim((string) ($item->title ?? ''));
            $link = trim((string) ($item->link ?? ''));
            $guid = trim((string) ($item->guid ?? ''));
            $pubDate = trim((string) ($item->pubDate ?? ''));
            $keyBase = $guid !== '' ? $guid : ($link !== '' ? $link : $title);
            if ($keyBase === '' || $link === '') {
                continue;
            }
            $items[] = [
                'key' => sha1($keyBase),
                'title' => $title,
                'link' => $link,
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
            $keyBase = $guid !== '' ? $guid : ($link !== '' ? $link : $title);
            if ($keyBase === '' || $link === '') {
                continue;
            }
            $items[] = [
                'key' => sha1($keyBase),
                'title' => $title,
                'link' => $link,
                'timestamp' => $updated !== '' ? (int) (strtotime($updated) ?: 0) : 0,
            ];
        }
    }
    usort($items, static function (array $a, array $b): int {
        return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
    });
    return $items;
}

function admin_handle_social_rss_settings_request(array $settings): array
{
    $result = [
        'feedback' => null,
        'feeds_raw' => admin_social_rss_settings($settings)['feeds'],
        'networks' => admin_social_rss_settings($settings)['networks'],
    ];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['save_social_rss_settings'])) {
        return $result;
    }
    $feedsRaw = trim((string) ($_POST['social_rss_feeds'] ?? ''));
    $selected = array_values(array_unique(array_filter(array_map('strval', $_POST['social_rss_networks'] ?? []))));
    $available = admin_social_broadcast_available_networks($settings);
    $validSelected = [];
    foreach ($selected as $network) {
        if (isset($available[$network])) {
            $validSelected[] = $network;
        }
    }
    $feeds = admin_social_rss_feed_list($feedsRaw);
    $config = load_config_file();
    $config['social_rss'] = [
        'feeds' => implode("\n", $feeds),
        'networks' => $validSelected,
    ];
    save_config_file($config);
    $result['feeds_raw'] = implode("\n", $feeds);
    $result['networks'] = $validSelected;
    $result['feedback'] = [
        'type' => 'success',
        'message' => 'Configuración de RSS guardada.',
    ];
    return $result;
}

function admin_process_social_rss_feeds(): array
{
    $settings = get_settings();
    $rssSettings = admin_social_rss_settings($settings);
    $feeds = admin_social_rss_feed_list($rssSettings['feeds']);
    $networks = $rssSettings['networks'];
    if (empty($feeds) || empty($networks)) {
        return ['sent' => 0, 'checked' => 0];
    }
    $availableNetworks = admin_social_broadcast_available_networks($settings);
    $networks = array_values(array_filter($networks, static fn(string $network): bool => isset($availableNetworks[$network])));
    if (empty($networks)) {
        return ['sent' => 0, 'checked' => count($feeds)];
    }
    $state = admin_load_social_rss_state();
    $state['feeds'] = is_array($state['feeds'] ?? null) ? $state['feeds'] : [];
    $sentCount = 0;
    foreach ($feeds as $feedUrl) {
        $feedKey = sha1($feedUrl);
        $items = admin_fetch_social_rss_items($feedUrl);
        if (!isset($state['feeds'][$feedKey]) || !is_array($state['feeds'][$feedKey])) {
            $state['feeds'][$feedKey] = [
                'url' => $feedUrl,
                'seen' => [],
                'initialized' => false,
                'last_checked' => 0,
            ];
        }
        $feedState = $state['feeds'][$feedKey];
        $feedState['seen'] = is_array($feedState['seen'] ?? null) ? $feedState['seen'] : [];
        $feedState['url'] = $feedUrl;
        $feedState['last_checked'] = time();
        if (empty($items)) {
            $state['feeds'][$feedKey] = $feedState;
            continue;
        }
        if (empty($feedState['initialized'])) {
            foreach ($items as $item) {
                $feedState['seen'][$item['key']] = time();
            }
            $feedState['initialized'] = true;
            $state['feeds'][$feedKey] = $feedState;
            continue;
        }
        foreach ($items as $item) {
            if (isset($feedState['seen'][$item['key']])) {
                continue;
            }
            $message = trim(($item['title'] !== '' ? $item['title'] : 'Nuevo contenido') . "\n\n" . $item['link']);
            foreach ($networks as $network) {
                $error = null;
                admin_send_social_broadcast_message($network, $message, $availableNetworks[$network]['settings'], $error);
            }
            $feedState['seen'][$item['key']] = time();
            $sentCount++;
        }
        $cutoff = time() - (30 * 86400);
        foreach ($feedState['seen'] as $itemKey => $seenAt) {
            if ((int) $seenAt < $cutoff) {
                unset($feedState['seen'][$itemKey]);
            }
        }
        $state['feeds'][$feedKey] = $feedState;
    }
    admin_save_social_rss_state($state);
    return ['sent' => $sentCount, 'checked' => count($feeds)];
}

function admin_social_broadcast_labels(): array
{
    return [
        'telegram' => 'Telegram',
        'facebook' => 'Facebook',
        'twitter' => 'Twitter / X',
        'bluesky' => 'Bluesky',
        'mastodon' => 'Mastodon',
        'linkedin' => 'LinkedIn',
    ];
}

function admin_social_broadcast_available_networks(array $settings): array
{
    $labels = admin_social_broadcast_labels();
    $limits = admin_social_broadcast_limits();
    $available = [];
    foreach ($labels as $network => $label) {
        $networkSettings = is_array($settings[$network] ?? null) ? $settings[$network] : [];
        if (!admin_is_social_network_configured($network, $networkSettings)) {
            continue;
        }
        $available[$network] = [
            'label' => $label,
            'limit' => (int) ($limits[$network] ?? 0),
            'settings' => $networkSettings,
        ];
    }
    return $available;
}

function admin_send_facebook_text(string $text, array $settings, ?string &$error = null): bool
{
    $token = trim((string) ($settings['token'] ?? ''));
    $pageId = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $pageId === '') {
        $error = 'Faltan credenciales de Facebook.';
        return false;
    }
    $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($pageId) . '/feed';
    $body = http_build_query([
        'message' => $text,
        'access_token' => $token,
    ]);
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($body),
    ];
    $httpCode = null;
    $response = admin_http_post_body_response($endpoint, $body, $headers, $httpCode);
    if ($response !== null && $httpCode !== null && $httpCode >= 200 && $httpCode < 300) {
        return true;
    }
    $error = 'No se pudo enviar el mensaje a Facebook.';
    if ($response !== null) {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $error = 'Facebook: ' . (string) $decoded['error']['message'];
        }
    }
    return false;
}

function admin_send_twitter_text(string $text, array $settings, ?string &$error = null): bool
{
    $token = trim((string) ($settings['token'] ?? ''));
    if ($token === '') {
        $error = 'Faltan credenciales de Twitter / X.';
        return false;
    }
    $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || $payload === '') {
        $error = 'No se pudo codificar el mensaje para Twitter / X.';
        return false;
    }
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload),
    ];
    $httpCode = null;
    $response = admin_http_post_body_response('https://api.twitter.com/2/tweets', $payload, $headers, $httpCode);
    if ($response !== null && $httpCode !== null && $httpCode >= 200 && $httpCode < 300) {
        return true;
    }
    $error = 'No se pudo enviar el mensaje a Twitter / X.';
    if ($response !== null) {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $message = (string) ($decoded['detail'] ?? $decoded['title'] ?? '');
            if ($message !== '') {
                $error = 'Twitter / X: ' . $message;
            }
        }
    }
    return false;
}

function admin_send_bluesky_text(string $text, array $settings, ?string &$error = null): bool
{
    $service = trim((string) ($settings['service'] ?? ''));
    if ($service === '') {
        $service = 'https://bsky.social';
    }
    $service = rtrim($service, '/');
    $identifier = trim((string) ($settings['identifier'] ?? ''));
    $identifier = ltrim($identifier, '@');
    $identifier = preg_replace('/[\p{Cf}\p{Z}\s]+/u', '', $identifier);
    $appPassword = trim((string) ($settings['app_password'] ?? ''));
    $appPassword = preg_replace('/\s+/', '', $appPassword);
    if ($identifier === '' || $appPassword === '') {
        $error = 'Faltan credenciales de Bluesky.';
        return false;
    }
    $sessionPayload = json_encode([
        'identifier' => $identifier,
        'password' => $appPassword,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sessionHeaders = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen((string) $sessionPayload),
    ];
    $sessionCode = null;
    $sessionResponse = admin_http_post_body_response($service . '/xrpc/com.atproto.server.createSession', (string) $sessionPayload, $sessionHeaders, $sessionCode);
    if ($sessionResponse === null || $sessionCode === null || $sessionCode < 200 || $sessionCode >= 300) {
        $error = 'No se pudo crear sesión en Bluesky.';
        if ($sessionResponse !== null) {
            $decoded = json_decode($sessionResponse, true);
            if (is_array($decoded) && isset($decoded['error'])) {
                $error = 'Bluesky: ' . (string) $decoded['error'];
            }
        }
        return false;
    }
    $session = json_decode($sessionResponse, true);
    if (!is_array($session) || empty($session['accessJwt']) || empty($session['did'])) {
        $error = 'Respuesta inválida de Bluesky.';
        return false;
    }
    $payload = json_encode([
        'repo' => $session['did'],
        'collection' => 'app.bsky.feed.post',
        'record' => [
            '$type' => 'app.bsky.feed.post',
            'text' => $text,
            'createdAt' => gmdate('Y-m-d\\TH:i:s\\Z'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers = [
        'Authorization: Bearer ' . $session['accessJwt'],
        'Content-Type: application/json',
        'Content-Length: ' . strlen((string) $payload),
    ];
    $httpCode = null;
    $response = admin_http_post_body_response($service . '/xrpc/com.atproto.repo.createRecord', (string) $payload, $headers, $httpCode);
    if ($response !== null && $httpCode !== null && $httpCode >= 200 && $httpCode < 300) {
        return true;
    }
    $error = 'No se pudo enviar el mensaje a Bluesky.';
    if ($response !== null) {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['error'])) {
            $error = 'Bluesky: ' . (string) $decoded['error'];
        }
    }
    return false;
}

function admin_send_mastodon_text(string $text, array $settings, ?string &$error = null): bool
{
    $instance = admin_mastodon_base_url((string) ($settings['instance'] ?? ''));
    if ($instance === '') {
        $profileUrl = trim((string) ($settings['profile'] ?? ''));
        if ($profileUrl !== '') {
            $profileUrl = preg_match('#^https?://#i', $profileUrl) ? $profileUrl : 'https://' . ltrim($profileUrl, '/');
            $profileParts = parse_url($profileUrl);
            if (!empty($profileParts['scheme']) && !empty($profileParts['host'])) {
                $instance = admin_mastodon_base_url($profileParts['scheme'] . '://' . $profileParts['host']);
            }
        }
    }
    if ($instance === '') {
        $handle = trim((string) ($settings['handle'] ?? ''));
        if (str_contains($handle, '@')) {
            $parts = explode('@', ltrim($handle, '@'));
            if (count($parts) >= 2) {
                $instance = admin_mastodon_base_url('https://' . $parts[1]);
            }
        }
    }
    $token = trim((string) ($settings['access_token'] ?? ''));
    if ($instance === '' || $token === '') {
        $error = 'Faltan credenciales de Mastodon.';
        return false;
    }
    $payload = json_encode(['status' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Content-Length: ' . strlen((string) $payload),
    ];
    $httpCode = null;
    $response = admin_http_post_body_response($instance . '/api/v1/statuses', (string) $payload, $headers, $httpCode);
    if ($response !== null && $httpCode !== null && $httpCode >= 200 && $httpCode < 300) {
        return true;
    }
    $error = 'No se pudo enviar el mensaje a Mastodon.';
    if ($response !== null) {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['error'])) {
            $error = 'Mastodon: ' . (string) $decoded['error'];
        }
    }
    return false;
}

function admin_send_linkedin_text(string $text, array $settings, ?string &$error = null): bool
{
    $token = trim((string) ($settings['token'] ?? ''));
    $author = trim((string) ($settings['author'] ?? ''));
    if ($token === '' || $author === '') {
        $error = 'Faltan credenciales de LinkedIn.';
        return false;
    }
    $normalizedAuthor = $author;
    if (preg_match('/^urn:li:member:(.+)$/', $normalizedAuthor, $match) === 1) {
        $normalizedAuthor = 'urn:li:person:' . $match[1];
    } elseif (preg_match('/^urn:li:company:(.+)$/', $normalizedAuthor, $match) === 1) {
        $normalizedAuthor = 'urn:li:organization:' . $match[1];
    }
    if (!preg_match('/^urn:li:(person|organization):[A-Za-z0-9_-]+$/', $normalizedAuthor)) {
        $error = 'LinkedIn: el Author URN debe ser urn:li:person:ID o urn:li:organization:ID.';
        return false;
    }
    $payload = [
        'author' => $normalizedAuthor,
        'lifecycleState' => 'PUBLISHED',
        'visibility' => 'PUBLIC',
        'distribution' => [
            'feedDistribution' => 'MAIN_FEED',
            'targetEntities' => [],
            'thirdPartyDistributionChannels' => [],
        ],
        'commentary' => $text,
        'isReshareDisabledByAuthor' => false,
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body) || $body === '') {
        $error = 'LinkedIn: no se pudo codificar el mensaje.';
        return false;
    }
    $versionCandidates = [];
    $cursor = new DateTimeImmutable('first day of this month');
    for ($i = 0; $i < 24; $i++) {
        $versionCandidates[] = $cursor->format('Ym');
        $cursor = $cursor->modify('-1 month');
    }
    $baseHeaders = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'X-Restli-Protocol-Version: 2.0.0',
    ];
    $lastHttpCode = null;
    $lastResponse = null;
    foreach ($versionCandidates as $version) {
        $headers = $baseHeaders;
        $headers[] = 'LinkedIn-Version: ' . $version;
        $headers[] = 'Content-Length: ' . strlen($body);
        $httpCode = null;
        $response = admin_http_post_body_response('https://api.linkedin.com/rest/posts', $body, $headers, $httpCode);
        $lastHttpCode = $httpCode;
        $lastResponse = $response;
        if ($response !== null && $httpCode !== null && $httpCode >= 200 && $httpCode < 300) {
            return true;
        }
        $decoded = is_string($response) ? json_decode($response, true) : null;
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? '') : '';
        if ($message !== '' && stripos($message, 'Requested version') === false) {
            $error = 'LinkedIn: ' . $message;
            return false;
        }
    }
    $legacyAuthor = $normalizedAuthor;
    if (preg_match('/^urn:li:person:(.+)$/', $legacyAuthor, $match) === 1) {
        $legacyAuthor = 'urn:li:member:' . $match[1];
    } elseif (preg_match('/^urn:li:organization:(.+)$/', $legacyAuthor, $match) === 1) {
        $legacyAuthor = 'urn:li:company:' . $match[1];
    }
    $legacyPayload = [
        'author' => $legacyAuthor,
        'lifecycleState' => 'PUBLISHED',
        'specificContent' => [
            'com.linkedin.ugc.ShareContent' => [
                'shareCommentary' => [
                    'text' => $text,
                ],
                'shareMediaCategory' => 'NONE',
            ],
        ],
        'visibility' => [
            'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
        ],
    ];
    $legacyBody = json_encode($legacyPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($legacyBody) && $legacyBody !== '') {
        $legacyHeaders = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'X-Restli-Protocol-Version: 2.0.0',
            'Content-Length: ' . strlen($legacyBody),
        ];
        $legacyHttpCode = null;
        $legacyResponse = admin_http_post_body_response('https://api.linkedin.com/v2/ugcPosts', $legacyBody, $legacyHeaders, $legacyHttpCode);
        if ($legacyResponse !== null && $legacyHttpCode !== null && $legacyHttpCode >= 200 && $legacyHttpCode < 300) {
            return true;
        }
        $decoded = is_string($legacyResponse) ? json_decode($legacyResponse, true) : null;
        if (is_array($decoded)) {
            $message = (string) ($decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? '');
            if ($message !== '') {
                $error = 'LinkedIn: ' . $message;
            }
        }
    }
    if ($error === null || $error === '') {
        $decoded = is_string($lastResponse) ? json_decode($lastResponse, true) : null;
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? '') : '';
        $error = $message !== '' ? 'LinkedIn: ' . $message : 'LinkedIn: error al publicar.';
    }
    return false;
}

function admin_send_social_broadcast_message(string $network, string $text, array $settings, ?string &$error = null): bool
{
    switch ($network) {
        case 'telegram':
            return admin_send_telegram_message((string) ($settings['token'] ?? ''), (string) ($settings['channel'] ?? ''), $text);
        case 'facebook':
            return admin_send_facebook_text($text, $settings, $error);
        case 'twitter':
            return admin_send_twitter_text($text, $settings, $error);
        case 'bluesky':
            return admin_send_bluesky_text($text, $settings, $error);
        case 'mastodon':
            return admin_send_mastodon_text($text, $settings, $error);
        case 'linkedin':
            return admin_send_linkedin_text($text, $settings, $error);
        default:
            $error = 'Red no soportada.';
            return false;
    }
}

function admin_handle_social_broadcast_request(array $settings): array
{
    $result = [
        'feedback' => null,
        'message_text' => '',
    ];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['send_social_broadcast'])) {
        return $result;
    }

    $text = trim((string) ($_POST['social_broadcast_text'] ?? ''));
    $selected = array_values(array_unique(array_filter(array_map('strval', $_POST['social_networks'] ?? []))));
    $result['message_text'] = $text;
    $available = admin_social_broadcast_available_networks($settings);

    if ($text === '') {
        $result['feedback'] = ['type' => 'danger', 'message' => 'Escribe un mensaje antes de enviarlo.'];
        return $result;
    }
    if (empty($selected)) {
        $result['feedback'] = ['type' => 'danger', 'message' => 'Marca al menos una red social configurada.'];
        return $result;
    }

    $limits = admin_social_broadcast_limits();
    $labels = admin_social_broadcast_labels();
    $sent = [];
    $failed = [];
    foreach ($selected as $network) {
        if (!isset($available[$network])) {
            $failed[] = ($labels[$network] ?? $network) . ': no está configurada.';
            continue;
        }
        $limit = (int) ($limits[$network] ?? 0);
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($limit > 0 && $length > $limit) {
            $failed[] = $available[$network]['label'] . ': el mensaje supera el máximo de ' . $limit . ' caracteres.';
            continue;
        }
        $error = null;
        $ok = admin_send_social_broadcast_message($network, $text, $available[$network]['settings'], $error);
        if ($ok) {
            $sent[] = $available[$network]['label'];
        } else {
            $failed[] = $available[$network]['label'] . ': ' . ($error ?: 'no se pudo enviar.');
        }
    }

    if (!empty($sent) && empty($failed)) {
        $result['feedback'] = [
            'type' => 'success',
            'message' => 'Mensaje enviado a: ' . implode(', ', $sent) . '.',
        ];
        $result['message_text'] = '';
        return $result;
    }
    if (!empty($sent) && !empty($failed)) {
        $result['feedback'] = [
            'type' => 'warning',
            'message' => 'Enviado a: ' . implode(', ', $sent) . '. Errores: ' . implode(' | ', $failed),
        ];
        return $result;
    }
    $result['feedback'] = [
        'type' => 'danger',
        'message' => implode(' | ', $failed),
    ];
    return $result;
}
