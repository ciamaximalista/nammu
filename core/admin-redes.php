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
        'instagram' => 2200,
    ];
}

function admin_social_broadcast_guidance(): array
{
    return [
        'telegram' => 'Máximo: 4096 caracteres',
        'facebook' => 'Máximo: 63206 caracteres',
        'twitter' => 'Máximo: 280 caracteres',
        'bluesky' => 'Máximo: 300 caracteres',
        'mastodon' => 'Máximo: 2000 caracteres',
        'linkedin' => 'Máximo: 3000 caracteres',
        'instagram' => 'Nammu la adaptará a 1080x1080 para Instagram',
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

function admin_social_rss_description_text(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function admin_social_rss_sentences(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }
    $sentences = preg_split('/(?<=[.!?…])\s+/u', $text) ?: [];
    $sentences = array_values(array_filter(array_map(static function (string $sentence): string {
        return trim($sentence);
    }, $sentences), static function (string $sentence): bool {
        return $sentence !== '';
    }));
    if (empty($sentences)) {
        return [$text];
    }
    return $sentences;
}

function admin_build_social_rss_message(string $network, string $title, string $link, string $description = '', bool $hasImage = false): string
{
    $title = trim($title);
    $link = trim($link);
    $description = admin_social_rss_description_text($description);

    $baseParts = [];
    if ($title !== '') {
        $baseParts[] = $title;
    }
    if ($link !== '') {
        $baseParts[] = $link;
    }
    if (empty($baseParts)) {
        $baseParts[] = 'Nuevo contenido';
    }

    $message = implode("\n\n", $baseParts);
    $limit = (int) (admin_social_broadcast_limits()[$network] ?? 0);
    if ($network === 'telegram' && $hasImage) {
        $limit = 1024;
    }
    if ($limit <= 0 || $description === '') {
        return $message;
    }

    $sentences = admin_social_rss_sentences($description);
    if (empty($sentences)) {
        return $message;
    }

    $separator = "\n\n";
    $current = $message;
    foreach ($sentences as $sentence) {
        $candidate = $current . $separator . $sentence;
        $length = function_exists('mb_strlen') ? mb_strlen($candidate, 'UTF-8') : strlen($candidate);
        if ($length > $limit) {
            break;
        }
        $current = $candidate;
    }

    return $current;
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
            foreach ($networks as $network) {
                $error = null;
                $image = trim((string) ($item['image'] ?? ''));
                $message = admin_build_social_rss_message(
                    $network,
                    (string) ($item['title'] ?? ''),
                    (string) ($item['link'] ?? ''),
                    (string) ($item['description'] ?? ''),
                    $image !== ''
                );
                admin_send_social_broadcast_message($network, $message, $availableNetworks[$network]['settings'], $image, $error);
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
        'instagram' => 'Instagram',
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
            'guidance' => (string) (admin_social_broadcast_guidance()[$network] ?? ''),
            'settings' => $networkSettings,
        ];
    }
    return $available;
}

function admin_social_broadcast_unicode_bold_char(string $char): string
{
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $upperBold = ['𝐀','𝐁','𝐂','𝐃','𝐄','𝐅','𝐆','𝐇','𝐈','𝐉','𝐊','𝐋','𝐌','𝐍','𝐎','𝐏','𝐐','𝐑','𝐒','𝐓','𝐔','𝐕','𝐖','𝐗','𝐘','𝐙'];
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $lowerBold = ['𝐚','𝐛','𝐜','𝐝','𝐞','𝐟','𝐠','𝐡','𝐢','𝐣','𝐤','𝐥','𝐦','𝐧','𝐨','𝐩','𝐪','𝐫','𝐬','𝐭','𝐮','𝐯','𝐰','𝐱','𝐲','𝐳'];
    $digits = '0123456789';
    $digitBold = ['𝟎','𝟏','𝟐','𝟑','𝟒','𝟓','𝟔','𝟕','𝟖','𝟗'];

    $index = strpos($upper, $char);
    if ($index !== false) {
        return $upperBold[$index];
    }
    $index = strpos($lower, $char);
    if ($index !== false) {
        return $lowerBold[$index];
    }
    $index = strpos($digits, $char);
    if ($index !== false) {
        return $digitBold[$index];
    }

    return $char;
}

function admin_social_broadcast_bold_unicode(string $text): string
{
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $result = '';
    foreach ($chars as $char) {
        $result .= admin_social_broadcast_unicode_bold_char($char);
    }
    return $result;
}

function admin_social_broadcast_plain_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace_callback('/\*\*(.+?)\*\*/su', static function (array $matches): string {
        return admin_social_broadcast_bold_unicode($matches[1]);
    }, $text) ?? $text;
    return trim($text);
}

function admin_social_broadcast_plain_without_markup(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/\*\*(.+?)\*\*/su', '$1', $text) ?? $text;
    return trim($text);
}

function admin_social_broadcast_telegram_html(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $parts = preg_split('/(\*\*.+?\*\*)/su', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
    $result = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (preg_match('/^\*\*(.+)\*\*$/su', $part, $matches) === 1) {
            $result .= '<b>' . admin_telegram_escape($matches[1]) . '</b>';
        } else {
            $result .= admin_telegram_escape($part);
        }
    }
    return trim($result);
}

function admin_social_broadcast_image_url(string $image): string
{
    $image = trim($image);
    if ($image === '') {
        return '';
    }
    return admin_public_asset_url($image);
}

function admin_social_broadcast_local_asset_path(string $image): string
{
    $image = trim($image);
    if ($image === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $image)) {
        $path = (string) (parse_url($image, PHP_URL_PATH) ?? '');
        if ($path === '') {
            return '';
        }
        $normalized = ltrim($path, '/');
        if (!str_starts_with($normalized, 'assets/')) {
            return '';
        }
        $relative = substr($normalized, 7);
    } else {
        $normalized = ltrim(str_replace('\\', '/', $image), '/');
        $relative = str_starts_with($normalized, 'assets/') ? substr($normalized, 7) : $normalized;
    }
    if ($relative === '' || !defined('ASSETS_DIR')) {
        return '';
    }
    $absolute = rtrim(ASSETS_DIR, '/') . '/' . $relative;
    return is_file($absolute) ? $absolute : '';
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
    $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || $payload === '') {
        $error = 'No se pudo codificar el mensaje para Twitter / X.';
        return false;
    }
    $decodedPayload = json_decode($payload, true);
    if (!is_array($decodedPayload)) {
        $error = 'No se pudo preparar el mensaje para Twitter / X.';
        return false;
    }
    return admin_send_twitter_api_request('https://api.twitter.com/2/tweets', $decodedPayload, $settings, $error);
}

function admin_send_twitter_text_with_image(string $text, string $imageRef, string $imageUrl, array $settings, ?string &$error = null): bool
{
    $mediaId = admin_twitter_upload_media($imageRef, $imageUrl, $settings, $error);
    if ($mediaId === null || $mediaId === '') {
        return false;
    }
    $payload = [
        'text' => $text,
        'media' => [
            'media_ids' => [$mediaId],
        ],
    ];
    return admin_send_twitter_api_request('https://api.twitter.com/2/tweets', $payload, $settings, $error);
}

function admin_mastodon_upload_media_from_url(string $instance, string $token, string $imageRef, string $imageUrl, ?string &$error = null): ?string
{
    if (!function_exists('curl_file_create')) {
        $error = 'Mastodon: el servidor no puede adjuntar imágenes porque falta soporte CURLFile.';
        return null;
    }
    $localPath = admin_social_broadcast_local_asset_path($imageRef);
    $tmpPath = '';
    $pathForCurl = '';
    $mime = 'application/octet-stream';
    $uploadName = 'imagen';

    if ($localPath !== '') {
        $pathForCurl = $localPath;
        $uploadName = basename($localPath);
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($localPath);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
    } else {
        $binary = admin_http_get_binary($imageUrl);
        if ($binary === '') {
            $error = 'Mastodon: no se pudo descargar la imagen pública para adjuntarla.';
            return null;
        }

        $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?? '');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($binary);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
        if ($extension === '') {
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'bin',
            };
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'nammu_mastodon_');
        if ($tmpPath === false || @file_put_contents($tmpPath, $binary) === false) {
            $error = 'Mastodon: no se pudo preparar temporalmente la imagen.';
            return null;
        }
        $finalTmpPath = $tmpPath . '.' . $extension;
        @rename($tmpPath, $finalTmpPath);
        $tmpPath = $finalTmpPath;
        $pathForCurl = $tmpPath;
        $uploadName = basename($path !== '' ? $path : ('imagen.' . $extension));
    }

    try {
        $file = curl_file_create($pathForCurl, $mime, $uploadName);
        $httpCode = null;
        $response = admin_http_post_multipart_json(
            $instance . '/api/v2/media',
            ['file' => $file],
            ['Authorization: Bearer ' . $token],
            $httpCode
        );
    } finally {
        if ($tmpPath !== '') {
            @unlink($tmpPath);
        }
    }

    if ($response === null || $httpCode === null || $httpCode < 200 || $httpCode >= 300) {
        $error = 'Mastodon: no se pudo subir la imagen.';
        if (is_array($response) && isset($response['error'])) {
            $error = 'Mastodon: ' . (string) $response['error'];
        }
        return null;
    }

    $mediaId = (string) ($response['id'] ?? '');
    if ($mediaId === '') {
        $error = 'Mastodon: la API no devolvió un media id.';
        return null;
    }

    return $mediaId;
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

function admin_send_mastodon_text_with_image(string $text, string $imageRef, string $imageUrl, array $settings, ?string &$error = null): bool
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

    $mediaId = admin_mastodon_upload_media_from_url($instance, $token, $imageRef, $imageUrl, $error);
    if ($mediaId === null) {
        return false;
    }

    $payload = json_encode([
        'status' => $text,
        'media_ids' => [$mediaId],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

function admin_send_bluesky_broadcast(string $text, array $settings, string $imageUrl = '', ?string &$error = null): bool
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
        return false;
    }
    $session = json_decode($sessionResponse, true);
    if (!is_array($session) || empty($session['accessJwt']) || empty($session['did'])) {
        $error = 'Respuesta inválida de Bluesky.';
        return false;
    }

    $record = [
        '$type' => 'app.bsky.feed.post',
        'text' => $text,
        'createdAt' => gmdate('Y-m-d\\TH:i:s\\Z'),
    ];
    $imageUrl = trim($imageUrl);
    if ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl)) {
        $blob = admin_bluesky_upload_blob($service, $session['accessJwt'], $imageUrl);
        if ($blob !== null) {
            $record['embed'] = [
                '$type' => 'app.bsky.embed.images',
                'images' => [[
                    'alt' => '',
                    'image' => $blob,
                ]],
            ];
        }
    }

    $payload = json_encode([
        'repo' => $session['did'],
        'collection' => 'app.bsky.feed.post',
        'record' => $record,
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
    $error = 'No se pudo crear el post en Bluesky.';
    if ($response !== null) {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['error'])) {
            $error = 'Bluesky: ' . (string) $decoded['error'];
        }
    }
    return false;
}

function admin_send_instagram_broadcast(string $text, string $image, array $settings, ?string &$error = null): bool
{
    $token = trim((string) ($settings['token'] ?? ''));
    $accountId = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $accountId === '') {
        $error = 'Falta token o ID de cuenta de Instagram.';
        return false;
    }
    $imageTrim = trim($image);
    if ($imageTrim === '') {
        $error = 'Instagram requiere una imagen.';
        return false;
    }
    $baseUrl = admin_base_url();
    $GLOBALS['nammu_instagram_target_side'] = 1080;
    $GLOBALS['nammu_instagram_jpeg_quality'] = 88;
    $imageUrl = admin_prepare_instagram_image_url($imageTrim, $baseUrl, $error);
    unset($GLOBALS['nammu_instagram_target_side'], $GLOBALS['nammu_instagram_jpeg_quality']);
    if ($imageUrl === '') {
        return false;
    }
    $probe = admin_probe_public_url_headers($imageUrl);
    if (!($probe['ok'] ?? false)) {
        $status = (string) ($probe['status'] ?? '');
        $contentType = (string) ($probe['content_type'] ?? '');
        $error = 'Instagram: la URL de imagen no es accesible como imagen pública (HTTP=' . $status . ', Content-Type=' . $contentType . ').';
        return false;
    }

    $createEndpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($accountId) . '/media';
    $createPayload = [
        'image_url' => $imageUrl,
        'caption' => admin_social_broadcast_plain_without_markup($text),
        'access_token' => $token,
    ];
    $createResponse = admin_http_post_form_json($createEndpoint, $createPayload);
    if (!is_array($createResponse) || empty($createResponse['id'])) {
        if (is_array($createResponse) && isset($createResponse['error']['message'])) {
            $error = 'Instagram: ' . (string) $createResponse['error']['message'];
        } else {
            $error = 'Instagram: no se pudo crear el medio.';
        }
        return false;
    }
    $creationId = (string) $createResponse['id'];
    $mediaStatus = null;
    $mediaReady = admin_wait_instagram_media_ready($creationId, $token, 12, 2, $mediaStatus);
    if (!$mediaReady) {
        // No abortamos aún: Meta a veces publica correctamente aunque el polling quede en IN_PROGRESS.
        error_log('Instagram manual broadcast warning (media_not_ready_yet): accountId=' . $accountId . ' creationId=' . $creationId . ' payload=' . json_encode($mediaStatus, JSON_UNESCAPED_UNICODE));
    }
    $publishEndpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($accountId) . '/media_publish';
    $publishResponse = null;
    $publishAttempts = 8;
    for ($attempt = 0; $attempt < $publishAttempts; $attempt++) {
        $publishResponse = admin_http_post_form_json($publishEndpoint, [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);
        if (is_array($publishResponse) && !empty($publishResponse['id'])) {
            return true;
        }
        $publishMessage = is_array($publishResponse) ? (string) ($publishResponse['error']['message'] ?? '') : '';
        $transient = $publishMessage === ''
            || stripos($publishMessage, 'not ready') !== false
            || stripos($publishMessage, 'is not ready') !== false
            || stripos($publishMessage, 'media is not ready') !== false
            || stripos($publishMessage, 'please wait') !== false;
        if (!$transient || $attempt === $publishAttempts - 1) {
            break;
        }
        sleep(2);
    }
    if (is_array($publishResponse) && isset($publishResponse['error']['message'])) {
        $error = 'Instagram: ' . (string) $publishResponse['error']['message'];
    } elseif (!$mediaReady) {
        $statusCode = is_array($mediaStatus) ? (string) ($mediaStatus['status_code'] ?? '') : '';
        $statusMessage = is_array($mediaStatus) ? (string) ($mediaStatus['error']['message'] ?? ($mediaStatus['error_message'] ?? ($mediaStatus['status'] ?? ''))) : '';
        $error = 'Instagram: el medio no quedó listo para publicarse.';
        if ($statusCode !== '' || $statusMessage !== '') {
            $detail = trim($statusCode . ' ' . $statusMessage);
            if ($detail !== '') {
                $error .= ' (' . $detail . ')';
            }
        }
    } else {
        $error = 'Instagram: no se pudo publicar.';
    }
    return false;
}

function admin_send_social_broadcast_message(string $network, string $text, array $settings, string $image = '', ?string &$error = null): bool
{
    $plainText = admin_social_broadcast_plain_text($text);
    $telegramHtml = admin_social_broadcast_telegram_html($text);
    $imageUrl = admin_social_broadcast_image_url($image);

    switch ($network) {
        case 'telegram':
            $token = (string) ($settings['token'] ?? '');
            $channel = (string) ($settings['channel'] ?? '');
            if ($token === '' || $channel === '') {
                $error = 'Faltan credenciales de Telegram.';
                return false;
            }
            if ($imageUrl !== '') {
                return admin_send_telegram_photo($token, $channel, $imageUrl, $telegramHtml, $error);
            }
            return admin_send_telegram_message($token, $channel, $telegramHtml, 'HTML', $error);
        case 'facebook':
            if ($imageUrl !== '') {
                $token = trim((string) ($settings['token'] ?? ''));
                $pageId = trim((string) ($settings['channel'] ?? ''));
                if ($token === '' || $pageId === '') {
                    $error = 'Faltan credenciales de Facebook.';
                    return false;
                }
                $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($pageId) . '/photos';
                $body = http_build_query([
                    'url' => $imageUrl,
                    'caption' => $plainText,
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
            }
            return admin_send_facebook_text($plainText, $settings, $error);
        case 'twitter':
            if ($image !== '' && $imageUrl !== '') {
                return admin_send_twitter_text_with_image($plainText, $image, $imageUrl, $settings, $error);
            }
            return admin_send_twitter_text($plainText, $settings, $error);
        case 'bluesky':
            return admin_send_bluesky_broadcast($plainText, $settings, $imageUrl, $error);
        case 'mastodon':
            if ($image !== '') {
                return admin_send_mastodon_text_with_image($plainText, $image, $imageUrl, $settings, $error);
            }
            return admin_send_mastodon_text($plainText, $settings, $error);
        case 'linkedin':
            return admin_send_linkedin_text($plainText, $settings, $error);
        case 'instagram':
            return admin_send_instagram_broadcast($text, $image, $settings, $error);
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
        'image' => '',
    ];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['send_social_broadcast'])) {
        return $result;
    }

    $text = trim((string) ($_POST['social_broadcast_text'] ?? ''));
    $image = trim((string) ($_POST['social_broadcast_image'] ?? ''));
    $selected = array_values(array_unique(array_filter(array_map('strval', $_POST['social_networks'] ?? []))));
    $result['message_text'] = $text;
    $result['image'] = $image;
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
        if ($network === 'telegram' && $image !== '') {
            $limit = 1024;
        }
        $measureText = $network === 'telegram'
            ? admin_social_broadcast_plain_without_markup($text)
            : admin_social_broadcast_plain_text($text);
        $length = function_exists('mb_strlen') ? mb_strlen($measureText, 'UTF-8') : strlen($measureText);
        if ($limit > 0 && $length > $limit) {
            $failed[] = $available[$network]['label'] . ': el mensaje supera el máximo de ' . $limit . ' caracteres.';
            continue;
        }
        if ($network === 'instagram' && $image === '') {
            $failed[] = 'Instagram: debes elegir una imagen.';
            continue;
        }
        $error = null;
        $ok = admin_send_social_broadcast_message($network, $text, $available[$network]['settings'], $image, $error);
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
        $result['image'] = '';
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
