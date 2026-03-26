<?php

function admin_social_broadcast_runtime_settings(): array
{
    if (function_exists('get_settings')) {
        return get_settings();
    }

    $config = function_exists('nammu_load_config') ? nammu_load_config() : [];
    $telegram = is_array($config['telegram'] ?? null) ? $config['telegram'] : [];
    $channel = trim((string) ($telegram['channel'] ?? ''));
    if ($channel !== '' && $channel[0] !== '@' && !preg_match('/^-?\d+$/', $channel)) {
        $channel = '@' . ltrim($channel, '@');
    }

    return [
        'telegram' => [
            'token' => trim((string) ($telegram['token'] ?? '')),
            'channel' => $channel,
            'recipient' => trim((string) ($telegram['recipient'] ?? '')),
            'auto_post' => trim((string) ($telegram['auto_post'] ?? 'off')),
        ],
        'facebook' => is_array($config['facebook'] ?? null) ? $config['facebook'] : [],
        'twitter' => is_array($config['twitter'] ?? null) ? $config['twitter'] : [],
        'bluesky' => is_array($config['bluesky'] ?? null) ? $config['bluesky'] : [],
        'instagram' => is_array($config['instagram'] ?? null) ? $config['instagram'] : [],
        'linkedin' => is_array($config['linkedin'] ?? null) ? $config['linkedin'] : [],
    ];
}

function admin_social_broadcast_network_is_configured(string $network, array $settings): bool
{
    if (function_exists('admin_is_social_network_configured')) {
        return admin_is_social_network_configured($network, $settings);
    }

    switch ($network) {
        case 'telegram':
            return ($settings['token'] ?? '') !== '' && ($settings['channel'] ?? '') !== '';
        case 'facebook':
            return ($settings['token'] ?? '') !== '' && ($settings['channel'] ?? '') !== '';
        case 'twitter':
            return ($settings['api_key'] ?? '') !== ''
                && ($settings['api_secret'] ?? '') !== ''
                && ($settings['access_token'] ?? '') !== ''
                && ($settings['access_secret'] ?? '') !== '';
        case 'bluesky':
            return ($settings['identifier'] ?? '') !== '' && ($settings['app_password'] ?? '') !== '';
        case 'instagram':
            return ($settings['token'] ?? '') !== '' && ($settings['channel'] ?? '') !== '';
        case 'linkedin':
            return ($settings['token'] ?? '') !== '' && ($settings['author'] ?? '') !== '';
        default:
            return false;
    }
}

function admin_social_broadcast_limits(): array
{
    return [
        'telegram' => 4096,
        'facebook' => 63206,
        'twitter' => 280,
        'bluesky' => 300,
        'linkedin' => 3000,
        'instagram' => 2200,
    ];
}

function admin_social_broadcast_max_images(): int
{
    return 4;
}

function admin_social_broadcast_network_image_limits(): array
{
    return [
        'telegram' => 4,
        'facebook' => 4,
        'twitter' => 4,
        'bluesky' => 4,
        'linkedin' => 0,
        'instagram' => 1,
    ];
}

function admin_social_broadcast_guidance(): array
{
    return [
        'telegram' => 'Máximo: 4096 caracteres',
        'facebook' => 'Máximo: 63206 caracteres',
        'twitter' => 'Máximo: 280 caracteres',
        'bluesky' => 'Máximo: 300 caracteres',
        'linkedin' => 'Máximo: 3000 caracteres',
        'instagram' => 'Nammu la adaptará a 1080x1080 para Instagram',
    ];
}

function admin_social_rss_state_file(): string
{
    return __DIR__ . '/../config/social-rss-state.json';
}

function admin_social_broadcast_queue_file(): string
{
    return __DIR__ . '/../config/social-broadcast-queue.json';
}

function admin_social_rss_settings(array $settings): array
{
    $feeds = admin_social_rss_feed_urls_from_settings($settings);
    $availableNetworks = admin_social_broadcast_available_networks($settings);
    return [
        'feeds' => implode("\n", $feeds),
        'networks' => array_keys($availableNetworks),
    ];
}

function admin_social_rss_feed_urls_from_settings(array $settings): array
{
    $feeds = [];

    $nisaba = is_array($settings['nisaba'] ?? null) ? $settings['nisaba'] : [];
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

    $telex = is_array($settings['telex'] ?? null) ? $settings['telex'] : [];
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

function admin_load_social_broadcast_queue(): array
{
    $file = admin_social_broadcast_queue_file();
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

function admin_save_social_broadcast_queue(array $queue): void
{
    $file = admin_social_broadcast_queue_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($file, json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($file, 0664);
}

function admin_enqueue_social_broadcast(string $text, $images, array $networks, string $fediverseUrl = ''): array
{
    $text = trim($text);
    $imageItems = admin_social_broadcast_parse_images($images);
    $networks = array_values(array_unique(array_filter(array_map('strval', $networks))));
    if ($text === '' || empty($networks)) {
        return ['ok' => false, 'message' => 'No hay envío a redes que encolar.'];
    }
    $queue = admin_load_social_broadcast_queue();
    $items = is_array($queue['items'] ?? null) ? $queue['items'] : [];
    $signature = sha1(json_encode([
        'text' => $text,
        'images' => $imageItems,
        'networks' => $networks,
        'fediverse_url' => trim($fediverseUrl),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    foreach ($items as $queuedItem) {
        if (!is_array($queuedItem)) {
            continue;
        }
        $queuedSignature = trim((string) ($queuedItem['signature'] ?? ''));
        if ($queuedSignature !== '' && hash_equals($queuedSignature, $signature)) {
            return [
                'ok' => true,
                'id' => (string) ($queuedItem['id'] ?? ''),
                'queued' => count($items),
                'duplicate' => true,
            ];
        }
    }
    $job = [
        'id' => substr(sha1($text . '|' . json_encode($imageItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '|' . microtime(true)), 0, 24),
        'text' => $text,
        'images' => $imageItems,
        'networks' => $networks,
        'fediverse_url' => trim($fediverseUrl),
        'signature' => $signature,
        'created_at' => gmdate(DATE_ATOM),
        'attempts' => 0,
    ];
    $items[] = $job;
    $queue['items'] = $items;
    admin_save_social_broadcast_queue($queue);
    return ['ok' => true, 'id' => $job['id'], 'queued' => count($items)];
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
        $baseParts[] = '**' . $title . '**';
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

function admin_social_broadcast_fediverse_url_for_actuality_item(array $item): string
{
    if (!function_exists('nammu_actuality_news_item_id') && is_file(__DIR__ . '/actualidad.php')) {
        require_once __DIR__ . '/actualidad.php';
    }
    if (!function_exists('nammu_fediverse_public_thread_url_for_actuality_item') && is_file(__DIR__ . '/fediverso.php')) {
        require_once __DIR__ . '/fediverso.php';
    }
    if (!function_exists('nammu_fediverse_public_thread_url_for_actuality_item')) {
        return '';
    }
    $config = load_config_file();
    $shortId = trim((string) ($item['id'] ?? ''));
    if ($shortId === '' && function_exists('nammu_actuality_news_item_id')) {
        $shortId = trim((string) nammu_actuality_news_item_id($item));
    }
    if ($shortId !== '' && function_exists('nammu_fediverse_thread_page_url') && function_exists('nammu_fediverse_base_url')) {
        $itemId = rtrim((string) nammu_fediverse_base_url($config), '/') . '/ap/objects/actualidad-' . rawurlencode($shortId);
        return trim((string) nammu_fediverse_thread_page_url($itemId, $config));
    }
    return trim((string) nammu_fediverse_public_thread_url_for_actuality_item($item, $config));
}

function admin_social_broadcast_append_fediverse_link(string $text, string $fediverseUrl): string
{
    $text = trim($text);
    $fediverseUrl = trim($fediverseUrl);
    if ($fediverseUrl === '') {
        return $text;
    }
    $appendix = 'Fediverso: ' . $fediverseUrl;
    if ($text === '') {
        return $appendix;
    }
    return $text . "\n\n" . $appendix;
}

function admin_social_broadcast_parse_images($images): array
{
    if (is_array($images)) {
        $rawItems = $images;
    } else {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim((string) $images));
        $rawItems = preg_split('/[\n,]+/', $normalized) ?: [];
    }
    $items = [];
    foreach ($rawItems as $item) {
        $value = trim((string) $item);
        if ($value === '') {
            continue;
        }
        $items[] = $value;
    }
    $items = array_values(array_unique($items));
    $max = admin_social_broadcast_max_images();
    if (count($items) > $max) {
        $items = array_slice($items, 0, $max);
    }
    return $items;
}

function admin_social_broadcast_primary_image($images): string
{
    $parsed = admin_social_broadcast_parse_images($images);
    return $parsed[0] ?? '';
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
    $feeds = admin_social_rss_feed_urls_from_settings($settings);
    $availableNetworks = admin_social_broadcast_available_networks($settings);
    $networks = array_keys($availableNetworks);
    if (empty($feeds) || empty($networks)) {
        return ['sent' => 0, 'checked' => 0];
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
            $fediverseUrl = admin_social_broadcast_fediverse_url_for_actuality_item($item);
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
                $message = admin_social_broadcast_append_fediverse_link($message, $fediverseUrl);
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
        if (!admin_social_broadcast_network_is_configured($network, $networkSettings)) {
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

function admin_send_social_broadcast_to_configured_networks(string $text, $images = '', ?array $settings = null, string $fediverseUrl = ''): array
{
    $text = trim($text);
    $imageItems = admin_social_broadcast_parse_images($images);
    $text = admin_social_broadcast_append_fediverse_link($text, $fediverseUrl);
    $settings = is_array($settings) ? $settings : admin_social_broadcast_runtime_settings();
    $available = admin_social_broadcast_available_networks($settings);
    $limits = admin_social_broadcast_limits();
    $imageLimits = admin_social_broadcast_network_image_limits();
    $sent = [];
    $failed = [];

    if ($text === '') {
        return [
            'sent' => [],
            'failed' => ['Mensaje vacío.'],
        ];
    }

    foreach ($available as $network => $networkMeta) {
        $limit = (int) ($limits[$network] ?? 0);
        if ($network === 'telegram' && !empty($imageItems)) {
            $limit = 1024;
        }
        $networkImages = $imageItems;
        $allowedImages = (int) ($imageLimits[$network] ?? 0);
        if ($allowedImages <= 0) {
            $networkImages = [];
        } elseif (count($networkImages) > $allowedImages) {
            $networkImages = array_slice($networkImages, 0, $allowedImages);
        }
        $measureText = $network === 'telegram'
            ? admin_social_broadcast_plain_without_markup($text)
            : admin_social_broadcast_plain_text($text);
        $length = function_exists('mb_strlen') ? mb_strlen($measureText, 'UTF-8') : strlen($measureText);
        if ($limit > 0 && $length > $limit) {
            $failed[] = $networkMeta['label'] . ': el mensaje supera el máximo de ' . $limit . ' caracteres.';
            continue;
        }
        if ($network === 'instagram' && empty($networkImages)) {
            $failed[] = 'Instagram: debes elegir una imagen.';
            continue;
        }
        $error = null;
        $ok = admin_send_social_broadcast_message($network, $text, $networkMeta['settings'], $networkImages, $error);
        if ($ok) {
            $sent[] = $networkMeta['label'];
        } else {
            $failed[] = $networkMeta['label'] . ': ' . ($error ?: 'no se pudo enviar.');
        }
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
    ];
}

function admin_process_social_broadcast_queue(int $maxJobs = 1): array
{
    $queue = admin_load_social_broadcast_queue();
    $items = is_array($queue['items'] ?? null) ? array_values($queue['items']) : [];
    if (empty($items) || $maxJobs < 1) {
        return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'remaining' => count($items)];
    }

    $processed = 0;
    $sent = 0;
    $failed = 0;
    $remaining = [];
    $settings = admin_social_broadcast_runtime_settings();

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ($processed >= $maxJobs) {
            $remaining[] = $item;
            continue;
        }
        $processed++;
        $networks = array_values(array_unique(array_filter(array_map('strval', $item['networks'] ?? []))));
        if (empty($networks)) {
            continue;
        }
        $selectedSettings = [];
        $available = admin_social_broadcast_available_networks($settings);
        foreach ($networks as $network) {
            if (isset($available[$network])) {
                $selectedSettings[$network] = $settings[$network] ?? [];
            }
        }
        if (empty($selectedSettings)) {
            $failed++;
            continue;
        }
        $result = admin_send_social_broadcast_to_configured_networks(
            (string) ($item['text'] ?? ''),
            (array) ($item['images'] ?? []),
            $selectedSettings,
            (string) ($item['fediverse_url'] ?? '')
        );
        if (!empty($result['sent'])) {
            $sent++;
        }
        if (empty($result['sent']) || !empty($result['failed'])) {
            $failed++;
        }
    }

    $queue['items'] = $remaining;
    admin_save_social_broadcast_queue($queue);

    return [
        'processed' => $processed,
        'sent' => $sent,
        'failed' => $failed,
        'remaining' => count($remaining),
    ];
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

function admin_social_broadcast_image_urls(array $images): array
{
    $urls = [];
    foreach (admin_social_broadcast_parse_images($images) as $image) {
        $url = admin_social_broadcast_image_url($image);
        if ($url !== '') {
            $urls[] = $url;
        }
    }
    return $urls;
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

function admin_send_twitter_text_with_images(string $text, array $images, array $settings, ?string &$error = null): bool
{
    $mediaIds = [];
    foreach (array_slice(admin_social_broadcast_parse_images($images), 0, 4) as $imageRef) {
        $imageUrl = admin_social_broadcast_image_url($imageRef);
        if ($imageUrl === '') {
            continue;
        }
        $mediaId = admin_twitter_upload_media($imageRef, $imageUrl, $settings, $error);
        if ($mediaId === null || $mediaId === '') {
            return false;
        }
        $mediaIds[] = $mediaId;
    }
    if (empty($mediaIds)) {
        return admin_send_twitter_text($text, $settings, $error);
    }
    $payload = [
        'text' => $text,
        'media' => [
            'media_ids' => $mediaIds,
        ],
    ];
    return admin_send_twitter_api_request('https://api.twitter.com/2/tweets', $payload, $settings, $error);
}

function admin_send_facebook_text_with_images(string $text, array $images, array $settings, ?string &$error = null): bool
{
    $token = trim((string) ($settings['token'] ?? ''));
    $pageId = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $pageId === '') {
        $error = 'Faltan credenciales de Facebook.';
        return false;
    }
    $attached = [];
    foreach (admin_social_broadcast_image_urls(array_slice(admin_social_broadcast_parse_images($images), 0, 4)) as $imageUrl) {
        $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($pageId) . '/photos';
        $body = http_build_query([
            'url' => $imageUrl,
            'published' => 'false',
            'access_token' => $token,
        ]);
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($body),
        ];
        $httpCode = null;
        $response = admin_http_post_body_response($endpoint, $body, $headers, $httpCode);
        $decoded = is_string($response) ? json_decode($response, true) : null;
        $mediaId = is_array($decoded) ? trim((string) ($decoded['id'] ?? '')) : '';
        if ($mediaId === '') {
            $error = 'Facebook: no se pudo subir una de las imágenes.';
            if (is_array($decoded) && isset($decoded['error']['message'])) {
                $error = 'Facebook: ' . (string) $decoded['error']['message'];
            }
            return false;
        }
        $attached[] = ['media_fbid' => $mediaId];
    }
    if (empty($attached)) {
        return admin_send_facebook_text($text, $settings, $error);
    }
    $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($pageId) . '/feed';
    $payload = [
        'message' => $text,
        'access_token' => $token,
    ];
    foreach ($attached as $index => $media) {
        $payload['attached_media[' . $index . ']'] = json_encode($media, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $body = http_build_query($payload);
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($body),
    ];
    $httpCode = null;
    $response = admin_http_post_body_response($endpoint, $body, $headers, $httpCode);
    if ($response !== null && $httpCode !== null && $httpCode >= 200 && $httpCode < 300) {
        return true;
    }
    $decoded = is_string($response) ? json_decode($response, true) : null;
    $error = 'Facebook: no se pudo publicar el álbum.';
    if (is_array($decoded) && isset($decoded['error']['message'])) {
        $error = 'Facebook: ' . (string) $decoded['error']['message'];
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

function admin_send_bluesky_broadcast(string $text, array $settings, $images = '', ?string &$error = null): bool
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
    $embedImages = [];
    foreach (array_slice(admin_social_broadcast_image_urls(admin_social_broadcast_parse_images($images)), 0, 4) as $imageUrl) {
        if ($imageUrl === '' || !preg_match('#^https?://#i', $imageUrl)) {
            continue;
        }
        $blob = admin_bluesky_upload_blob($service, $session['accessJwt'], $imageUrl);
        if ($blob !== null) {
            $embedImages[] = [
                'alt' => '',
                'image' => $blob,
            ];
        }
    }
    if (!empty($embedImages)) {
        $record['embed'] = [
            '$type' => 'app.bsky.embed.images',
            'images' => $embedImages,
        ];
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

function admin_send_telegram_media_group(string $token, string $chatId, array $photoUrls, string $caption, ?string &$error = null): bool
{
    $photoUrls = array_values(array_filter(array_map('strval', $photoUrls)));
    if (count($photoUrls) < 2) {
        return admin_send_telegram_photo($token, $chatId, $photoUrls[0] ?? '', $caption, $error);
    }
    $media = [];
    foreach ($photoUrls as $index => $photoUrl) {
        $item = [
            'type' => 'photo',
            'media' => $photoUrl,
        ];
        if ($index === 0) {
            $item['caption'] = $caption;
            $item['parse_mode'] = 'HTML';
        }
        $media[] = $item;
    }
    $endpoint = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMediaGroup';
    $payload = [
        'chat_id' => $chatId,
        'media' => json_encode($media, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];
    $response = admin_http_post_form_json($endpoint, $payload);
    if (is_array($response) && isset($response['ok'])) {
        if ((bool) $response['ok']) {
            return true;
        }
        $error = 'Telegram: ' . (string) ($response['description'] ?? 'error desconocido');
        return false;
    }
    $error = 'Telegram: no se pudo enviar el álbum.';
    return false;
}

function admin_send_social_broadcast_message(string $network, string $text, array $settings, $images = '', ?string &$error = null): bool
{
    $plainText = admin_social_broadcast_plain_text($text);
    $telegramHtml = admin_social_broadcast_telegram_html($text);
    $imageItems = admin_social_broadcast_parse_images($images);
    $primaryImage = $imageItems[0] ?? '';
    $imageUrl = admin_social_broadcast_image_url($primaryImage);

    switch ($network) {
        case 'telegram':
            $token = (string) ($settings['token'] ?? '');
            $channel = (string) ($settings['channel'] ?? '');
            if ($token === '' || $channel === '') {
                $error = 'Faltan credenciales de Telegram.';
                return false;
            }
            if (count($imageItems) > 1) {
                return admin_send_telegram_media_group($token, $channel, admin_social_broadcast_image_urls($imageItems), $telegramHtml, $error);
            }
            if ($imageUrl !== '') {
                return admin_send_telegram_photo($token, $channel, $imageUrl, $telegramHtml, $error);
            }
            return admin_send_telegram_message($token, $channel, $telegramHtml, 'HTML', $error);
        case 'facebook':
            if (count($imageItems) > 1) {
                return admin_send_facebook_text_with_images($plainText, $imageItems, $settings, $error);
            }
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
            if (!empty($imageItems)) {
                return admin_send_twitter_text_with_images($plainText, $imageItems, $settings, $error);
            }
            return admin_send_twitter_text($plainText, $settings, $error);
        case 'bluesky':
            return admin_send_bluesky_broadcast($plainText, $settings, $imageItems, $error);
        case 'linkedin':
            return admin_send_linkedin_text($plainText, $settings, $error);
        case 'instagram':
            return admin_send_instagram_broadcast($text, $primaryImage, $settings, $error);
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
        'actuality' => false,
        'networks' => [],
    ];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['send_social_broadcast'])) {
        return $result;
    }

    $text = trim((string) ($_POST['social_broadcast_text'] ?? ''));
    $image = trim((string) ($_POST['social_broadcast_image'] ?? ''));
    return admin_handle_social_broadcast_submission($settings, $text, $image);
}

function admin_handle_social_broadcast_submission(array $settings, string $text, string $image = ''): array
{
    $result = [
        'feedback' => null,
        'message_text' => trim($text),
        'image' => trim($image),
        'actuality' => false,
        'networks' => [],
    ];

    $imageItems = admin_social_broadcast_parse_images($image);
    $primaryImage = $imageItems[0] ?? '';
    $sendToActuality = true;
    $available = admin_social_broadcast_available_networks($settings);
    $result['actuality'] = $sendToActuality;
    $result['networks'] = array_keys($available);

    if ($text === '') {
        $result['feedback'] = ['type' => 'danger', 'message' => 'Escribe un mensaje antes de enviarlo.'];
        return $result;
    }
    $labels = admin_social_broadcast_labels();
    $sent = [];
    $failed = [];
    $fediverseUrl = '';
    if ($sendToActuality) {
        if (!function_exists('nammu_actuality_add_manual_item') && is_file(__DIR__ . '/actualidad.php')) {
            require_once __DIR__ . '/actualidad.php';
        }
        if (!function_exists('nammu_fediverse_deliver_local_items') && is_file(__DIR__ . '/fediverso.php')) {
            require_once __DIR__ . '/fediverso.php';
        }
        if (function_exists('nammu_actuality_add_manual_item')) {
            $config = load_config_file();
            $siteTitle = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
            $baseUrl = trim((string) ($config['site_url'] ?? ''));
            if ($baseUrl === '') {
                $baseUrl = function_exists('nammu_base_url') ? nammu_base_url() : '';
            }
            $manualItem = nammu_actuality_add_manual_item($text, $baseUrl, $siteTitle, $primaryImage, [
                'images' => $imageItems,
            ]);
            if (!empty($manualItem)) {
                if (function_exists('nammu_actuality_add_item_to_snapshots')) {
                    nammu_actuality_add_item_to_snapshots($manualItem);
                }
                if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
                    nammu_fediverse_save_fragments_cache_store([]);
                }
                $fediverseUrl = admin_social_broadcast_fediverse_url_for_actuality_item($manualItem);
                $sent[] = 'Perfil del Fediverso';
                $sent[] = 'Fediverso';
            } else {
                $failed[] = 'Perfil del Fediverso: no se pudo guardar la nota.';
            }
        } else {
            $failed[] = 'Perfil del Fediverso: no está disponible.';
        }
    }
    $allConfiguredNetworks = array_keys($available);
    if (!empty($allConfiguredNetworks)) {
        $queueResult = admin_enqueue_social_broadcast($text, $image, $allConfiguredNetworks, $fediverseUrl);
        if (!empty($queueResult['ok'])) {
            $sent[] = 'Cola de redes';
        } else {
            $failed[] = 'Cola de redes: ' . (string) ($queueResult['message'] ?? 'no se pudo encolar.');
        }
    }

    if (!empty($sent) && empty($failed)) {
        $result['feedback'] = [
            'type' => 'success',
            'message' => 'Mensaje guardado/encolado en: ' . implode(', ', $sent) . '.',
        ];
        $result['message_text'] = '';
        $result['image'] = '';
        $result['actuality'] = false;
        $result['networks'] = [];
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
