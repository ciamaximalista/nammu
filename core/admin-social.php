<?php
/**
 * Nammu — panel de administración.
 * Redes sociales: configuración, contadores de seguidores, construcción de mensajes, auto-publicación y push.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

function admin_extract_social_settings(string $key, array $defaults, ?array $config = null): array {
    if ($config === null) {
        $config = load_config_file();
    }
    $stored = [];
    if (isset($config[$key]) && is_array($config[$key])) {
        $stored = $config[$key];
    }
    $values = array_merge($defaults, $stored);
    $values['token'] = trim((string) ($values['token'] ?? ''));
    $values['channel'] = trim((string) ($values['channel'] ?? ''));
    $values['recipient'] = trim((string) ($values['recipient'] ?? ''));
    if (isset($values['api_key'])) {
        $values['api_key'] = trim((string) $values['api_key']);
    }
    if (isset($values['api_secret'])) {
        $values['api_secret'] = trim((string) $values['api_secret']);
    }
    if (isset($values['access_token'])) {
        $values['access_token'] = trim((string) $values['access_token']);
    }
    if (isset($values['access_secret'])) {
        $values['access_secret'] = trim((string) $values['access_secret']);
    }
    if (isset($values['identifier'])) {
        $values['identifier'] = trim((string) $values['identifier']);
    }
    if (isset($values['app_password'])) {
        $values['app_password'] = trim((string) $values['app_password']);
    }
    if (isset($values['handle'])) {
        $values['handle'] = trim((string) $values['handle']);
    }
    if (isset($values['service'])) {
        $values['service'] = trim((string) $values['service']);
    }
    if (isset($values['instance'])) {
        $values['instance'] = trim((string) $values['instance']);
    }
    if (isset($values['profile'])) {
        $values['profile'] = trim((string) $values['profile']);
    }
    $values['auto_post'] = ($values['auto_post'] ?? 'off') === 'on' ? 'on' : 'off';
    return $values;
}

function admin_extract_telegram_settings(?array $config = null): array {
    $defaults = [
        'token' => '',
        'channel' => '',
        'recipient' => '',
        'auto_post' => 'off',
    ];
    $telegram = admin_extract_social_settings('telegram', $defaults, $config);
    if ($telegram['channel'] !== '' && $telegram['channel'][0] !== '@' && !preg_match('/^-?\d+$/', $telegram['channel'])) {
        $telegram['channel'] = '@' . ltrim($telegram['channel'], '@');
    }
    return $telegram;
}

function admin_refresh_facebook_token_if_needed(array $config): array {
    $facebook = $config['facebook'] ?? [];
    $appId = trim((string) ($config['social']['facebook_app_id'] ?? ''));
    $appSecret = trim((string) ($facebook['app_secret'] ?? ''));
    $token = trim((string) ($facebook['token'] ?? ''));
    if ($appId === '' || $appSecret === '' || $token === '') {
        return $config;
    }
    $lastAttempt = (int) ($facebook['token_refresh_attempted_at'] ?? 0);
    if ($lastAttempt > 0 && (time() - $lastAttempt) < 86400) {
        return $config;
    }
    $facebook['token_refresh_attempted_at'] = time();
    $endpoint = 'https://graph.facebook.com/v17.0/oauth/access_token?grant_type=fb_exchange_token'
        . '&client_id=' . rawurlencode($appId)
        . '&client_secret=' . rawurlencode($appSecret)
        . '&fb_exchange_token=' . rawurlencode($token);
    $response = admin_http_get_json($endpoint);
    if (is_array($response) && isset($response['access_token'])) {
        $facebook['token'] = (string) $response['access_token'];
        $facebook['token_refreshed_at'] = time();
    } else {
        error_log('Facebook token refresh failed: ' . json_encode($response));
    }
    $config['facebook'] = $facebook;
    save_config_file($config);
    return $config;
}

function admin_cached_social_settings(?string $key = null): array {
    static $cache = null;
    if ($cache === null) {
        $config = load_config_file();
        $config = admin_refresh_facebook_token_if_needed($config);
        $cache = [
            'telegram' => admin_extract_telegram_settings($config),
            'facebook' => admin_extract_social_settings('facebook', [
                'token' => '',
                'channel' => '',
                'recipient' => '',
                'auto_post' => 'off',
                'app_secret' => '',
                'token_refreshed_at' => 0,
                'token_refresh_attempted_at' => 0,
            ], $config),
            'twitter' => admin_extract_social_settings('twitter', [
                'token' => '',
                'channel' => '',
                'recipient' => '',
                'auto_post' => 'off',
                'api_key' => '',
                'api_secret' => '',
                'access_token' => '',
                'access_secret' => '',
            ], $config),
            'bluesky' => admin_extract_social_settings('bluesky', [
                'service' => 'https://bsky.social',
                'identifier' => '',
                'app_password' => '',
                'auto_post' => 'off',
            ], $config),
            'instagram' => admin_extract_social_settings('instagram', [
                'token' => '',
                'channel' => '',
                'profile' => '',
                'recipient' => '',
                'auto_post' => 'off',
            ], $config),
            'linkedin' => admin_extract_social_settings('linkedin', [
                'token' => '',
                'author' => '',
                'auto_post' => 'off',
            ], $config),
        ];
    }
    if ($key === null) {
        return $cache;
    }
    return $cache[$key] ?? [];
}

function admin_cached_telegram_settings(): array {
    return admin_cached_social_settings('telegram');
}

function admin_is_social_network_configured(string $network, array $settings): bool {
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

function admin_get_telegram_follower_count(array $settings): ?int {
    $token = trim((string) ($settings['token'] ?? ''));
    $channel = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $channel === '') {
        return null;
    }
    $endpoint = 'https://api.telegram.org/bot' . $token . '/getChatMemberCount?chat_id=' . rawurlencode($channel);
    $payload = admin_http_get_json($endpoint);
    if (!is_array($payload) || empty($payload['ok'])) {
        return null;
    }
    return isset($payload['result']) ? (int) $payload['result'] : null;
}

function admin_get_facebook_follower_count(array $settings): ?int {
    $token = trim((string) ($settings['token'] ?? ''));
    $channel = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $channel === '') {
        return null;
    }
    $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($channel)
        . '?fields=followers_count&access_token=' . rawurlencode($token);
    $payload = admin_http_get_json($endpoint);
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['followers_count'])) {
        return (int) $payload['followers_count'];
    }
    return null;
}

function admin_facebook_debug_token(string $token, string $appId, string $appSecret): ?array {
    if ($token === '' || $appId === '' || $appSecret === '') {
        return null;
    }
    $endpoint = 'https://graph.facebook.com/debug_token?input_token=' . rawurlencode($token)
        . '&access_token=' . rawurlencode($appId . '|' . $appSecret);
    $payload = admin_http_get_json($endpoint);
    return is_array($payload) ? $payload : null;
}

function admin_get_twitter_follower_count(array $settings): ?int {
    $channelRaw = trim((string) ($settings['channel'] ?? ''));
    $apiKey = trim((string) ($settings['api_key'] ?? ''));
    $apiSecret = trim((string) ($settings['api_secret'] ?? ''));
    $accessToken = trim((string) ($settings['access_token'] ?? ''));
    $accessSecret = trim((string) ($settings['access_secret'] ?? ''));
    if ($channelRaw === '' || $apiKey === '' || $apiSecret === '' || $accessToken === '' || $accessSecret === '') {
        return null;
    }
    $channel = ltrim($channelRaw, '@');
    $query = ['user.fields' => 'public_metrics'];
    if (preg_match('/^\d+$/', $channel)) {
        $endpointBase = 'https://api.twitter.com/2/users/' . rawurlencode($channel);
    } else {
        $endpointBase = 'https://api.twitter.com/2/users/by/username/' . rawurlencode($channel);
    }
    $error = null;
    $authorization = admin_twitter_build_oauth_header('GET', $endpointBase, $settings, $error, $query);
    if ($authorization === null || $authorization === '') {
        return null;
    }
    $endpoint = $endpointBase . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $payload = admin_http_get_json($endpoint, ['Authorization: ' . $authorization]);
    if (!is_array($payload) || !isset($payload['data']['public_metrics']['followers_count'])) {
        return null;
    }
    return (int) $payload['data']['public_metrics']['followers_count'];
}

function admin_get_instagram_follower_count(array $settings): ?int {
    $token = trim((string) ($settings['token'] ?? ''));
    $channel = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $channel === '') {
        return null;
    }
    $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($channel)
        . '?fields=followers_count&access_token=' . rawurlencode($token);
    $payload = admin_http_get_json($endpoint);
    if (!is_array($payload) || !isset($payload['followers_count'])) {
        return null;
    }
    return (int) $payload['followers_count'];
}

function admin_get_bluesky_follower_count(array $settings): ?int {
    $service = trim((string) ($settings['service'] ?? ''));
    if ($service === '') {
        $service = 'https://bsky.social';
    }
    $service = rtrim($service, '/');
    $identifier = trim((string) ($settings['identifier'] ?? ''));
    $identifier = ltrim($identifier, '@');
    $identifier = preg_replace('/[\p{Cf}\p{Z}\s]+/u', '', $identifier);
    if ($identifier === '') {
        return null;
    }
    $profilePayload = admin_http_get_json($service . '/xrpc/app.bsky.actor.getProfile?actor=' . rawurlencode($identifier));
    if (is_array($profilePayload) && isset($profilePayload['followersCount'])) {
        return (int) $profilePayload['followersCount'];
    }

    $appPassword = trim((string) ($settings['app_password'] ?? ''));
    $appPassword = preg_replace('/\s+/', '', $appPassword);
    if ($appPassword === '') {
        return null;
    }
    $sessionPayload = json_encode([
        'identifier' => $identifier,
        'password' => $appPassword,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($sessionPayload) || $sessionPayload === '') {
        return null;
    }
    $sessionHeaders = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($sessionPayload),
    ];
    $sessionCode = null;
    $sessionResponse = admin_http_post_body_response($service . '/xrpc/com.atproto.server.createSession', $sessionPayload, $sessionHeaders, $sessionCode);
    if ($sessionResponse === null || $sessionCode === null || $sessionCode < 200 || $sessionCode >= 300) {
        return null;
    }
    $session = json_decode($sessionResponse, true);
    if (!is_array($session) || empty($session['did']) || empty($session['accessJwt'])) {
        return null;
    }
    $did = trim((string) $session['did']);
    if ($did === '') {
        return null;
    }
    $authHeaders = [
        'Authorization: Bearer ' . $session['accessJwt'],
    ];
    $profilePayload = admin_http_get_json($service . '/xrpc/app.bsky.actor.getProfile?actor=' . rawurlencode($did), $authHeaders);
    if (!is_array($profilePayload) || !isset($profilePayload['followersCount'])) {
        return null;
    }
    return (int) $profilePayload['followersCount'];
}

function admin_send_post_to_telegram(string $slug, string $title, string $description, array $telegramSettings, string $urlOverride = '', string $imageUrl = ''): bool {
    $token = $telegramSettings['token'] ?? '';
    $channel = $telegramSettings['channel'] ?? '';
    if ($token === '' || $channel === '') {
        return false;
    }
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $trackedUrl = admin_add_utm_params($targetUrl, [
        'utm_source' => 'telegram',
        'utm_medium' => 'social',
    ]);
    $fediverseUrl = admin_social_fediverse_thread_url($slug, admin_social_fediverse_template($slug, $urlOverride));
    $message = admin_build_telegram_message($slug, $title, $description, $trackedUrl, $fediverseUrl);
    $imageUrl = trim($imageUrl);
    if ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl)) {
        $sentWithPhoto = admin_send_telegram_photo($token, $channel, $imageUrl, $message);
        if ($sentWithPhoto) {
            return true;
        }
        // Fallback: keep delivery even if the image is rejected/unavailable.
        return admin_send_telegram_message($token, $channel, $message, 'HTML');
    }
    return admin_send_telegram_message($token, $channel, $message, 'HTML');
}

function admin_social_fediverse_thread_url(string $slug, string $template = 'post'): string
{
    if (!function_exists('nammu_fediverse_public_thread_url_for_named_local_item') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
        require_once NAMMU_ROOT . '/core/fediverso.php';
    }
    if (!function_exists('nammu_fediverse_public_thread_url_for_named_local_item')) {
        return '';
    }
    $config = load_config_file();
    return trim((string) nammu_fediverse_public_thread_url_for_named_local_item($slug, $template, $config));
}

function admin_social_fediverse_template(string $slug, string $urlOverride = ''): string
{
    $path = strtolower(trim((string) parse_url($urlOverride, PHP_URL_PATH)));
    if ($path !== '') {
        if (str_contains($path, '/podcast/')) {
            return 'podcast';
        }
        if (str_contains($path, '/itinerarios/')) {
            return 'itinerary';
        }
        return 'post';
    }
    return 'post';
}

function admin_social_appendix_text(string $fediverseUrl): string
{
    $fediverseUrl = trim($fediverseUrl);
    if ($fediverseUrl === '') {
        return '';
    }
    return 'Fediverso: ' . $fediverseUrl;
}

function admin_social_appendix_html(string $fediverseUrl): string
{
    $fediverseUrl = trim($fediverseUrl);
    if ($fediverseUrl === '') {
        return '';
    }
    $safeUrl = admin_telegram_escape($fediverseUrl);
    return '<a href="' . $safeUrl . '">Comentarios y reacciones en el Fediverso</a>';
}

function admin_telegram_escape(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_build_post_message(string $slug, string $title, string $description, string $urlOverride = '', string $imageUrl = ''): string {
    $parts = [];
    $title = trim($title);
    $description = trim($description);
    if ($title !== '') {
        $parts[] = $title;
    }
    if ($description !== '') {
        $parts[] = $description;
    }
    $url = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    if ($url !== '') {
        $parts[] = 'Lee el artículo: ' . $url;
    }
    $imageUrl = trim($imageUrl);
    if ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl)) {
        $parts[] = $imageUrl;
    }
    if (empty($parts)) {
        $parts[] = 'Nueva publicación disponible';
    }
    return implode("\n\n", $parts);
}

function admin_social_sentences_from_text(string $text): array {
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    if ($text === '') {
        return [];
    }
    $parts = preg_split('/(?<=[.!?…])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts) || $parts === []) {
        return [$text];
    }
    return array_values(array_filter(array_map('trim', $parts), static fn($item) => $item !== ''));
}

function admin_social_clean_description_for_message(string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $lines = explode("\n", $text);
    $lines = array_map(static function (string $line): string {
        return trim(preg_replace('/[ \t]+/u', ' ', $line) ?? $line);
    }, $lines);
    $text = implode("\n", $lines);
    $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
    return trim($text);
}

function admin_unicode_bold_char(string $char): string {
    $codepoint = mb_ord($char, 'UTF-8');
    if ($codepoint >= 65 && $codepoint <= 90) {
        return mb_chr(0x1D5D4 + ($codepoint - 65), 'UTF-8');
    }
    if ($codepoint >= 97 && $codepoint <= 122) {
        return mb_chr(0x1D5EE + ($codepoint - 97), 'UTF-8');
    }
    if ($codepoint >= 48 && $codepoint <= 57) {
        return mb_chr(0x1D7EC + ($codepoint - 48), 'UTF-8');
    }
    return $char;
}

function admin_bold_unicode_text(string $text): string {
    if (!function_exists('mb_str_split') || !function_exists('mb_ord') || !function_exists('mb_chr')) {
        return $text;
    }
    $chars = mb_str_split($text);
    $mapped = array_map('admin_unicode_bold_char', $chars);
    return implode('', $mapped);
}

function admin_build_sentence_limited_social_message(
    string $title,
    string $description,
    string $url,
    int $maxLen,
    ?callable $titleFormatter = null,
    string $appendix = ''
): string {
    $title = trim($title);
    $description = admin_social_clean_description_for_message($description);
    $url = trim($url);

    $baseParts = [];
    if ($title !== '') {
        $baseParts[] = $titleFormatter !== null ? (string) $titleFormatter($title) : $title;
    }
    if ($description !== '') {
        $baseParts[] = $description;
    }
    $base = $baseParts !== [] ? implode("\n\n", $baseParts) : 'Nueva publicación disponible';
    $appendix = trim($appendix);
    $suffixParts = [];
    if ($url !== '') {
        $suffixParts[] = $url;
    }
    if ($appendix !== '') {
        $suffixParts[] = $appendix;
    }
    $suffix = $suffixParts !== [] ? ("\n\n" . implode("\n\n", $suffixParts)) : '';

    $candidate = $base . $suffix;
    if (function_exists('mb_strlen') && mb_strlen($candidate, 'UTF-8') > $maxLen && $description !== '') {
        $requiredTitle = $title !== '' ? ($titleFormatter !== null ? (string) $titleFormatter($title) : $title) : 'Nueva publicación disponible';
        $reserved = mb_strlen($requiredTitle . $suffix . "\n\n", 'UTF-8');
        $available = max(0, $maxLen - $reserved);
        $description = $available > 0 ? admin_social_truncate_preserving_text($description, $available) : '';
        $base = trim(implode("\n\n", array_filter([$requiredTitle, $description], static fn(string $part): bool => trim($part) !== '')));
        $candidate = $base . $suffix;
    } elseif (!function_exists('mb_strlen') && strlen($candidate) > $maxLen && $description !== '') {
        $requiredTitle = $title !== '' ? ($titleFormatter !== null ? (string) $titleFormatter($title) : $title) : 'Nueva publicación disponible';
        $reserved = strlen($requiredTitle . $suffix . "\n\n");
        $available = max(0, $maxLen - $reserved);
        $description = $available > 0 ? admin_social_truncate_preserving_text($description, $available) : '';
        $base = trim(implode("\n\n", array_filter([$requiredTitle, $description], static fn(string $part): bool => trim($part) !== '')));
        $candidate = $base . $suffix;
    }

    if (function_exists('mb_strlen')) {
        if (mb_strlen($candidate, 'UTF-8') > $maxLen) {
            return mb_substr($candidate, 0, max(0, $maxLen - 1), 'UTF-8') . '…';
        }
        return $candidate;
    }
    if (strlen($candidate) > $maxLen) {
        return substr($candidate, 0, max(0, $maxLen - 1)) . '…';
    }
    return $candidate;
}

function admin_social_truncate_preserving_text(string $text, int $maxLen): string {
    $text = trim($text);
    if ($text === '' || $maxLen <= 0) {
        return '';
    }
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($length <= $maxLen) {
        return $text;
    }
    $cutLen = max(1, $maxLen - 1);
    $cut = function_exists('mb_substr') ? mb_substr($text, 0, $cutLen, 'UTF-8') : substr($text, 0, $cutLen);
    $spacePos = function_exists('mb_strrpos') ? mb_strrpos($cut, ' ', 0, 'UTF-8') : strrpos($cut, ' ');
    if ($spacePos !== false && $spacePos > 0) {
        $cut = function_exists('mb_substr') ? mb_substr($cut, 0, $spacePos, 'UTF-8') : substr($cut, 0, $spacePos);
    }
    return rtrim($cut, " \t\n\r\0\x0B.,;:") . '…';
}

function admin_build_twitter_post_message(string $title, string $description, string $url): string {
    return admin_build_sentence_limited_social_message($title, $description, $url, 280, 'admin_bold_unicode_text');
}

function admin_build_telegram_message(string $slug, string $title, string $description, string $urlOverride = '', string $fediverseUrl = ''): string {
    $parts = [];
    $titleTrim = trim($title);
    if ($titleTrim !== '') {
        $parts[] = '<b>' . admin_telegram_escape($titleTrim) . '</b>';
    }
    $descriptionTrim = trim($description);
    if ($descriptionTrim !== '') {
        $parts[] = admin_telegram_escape($descriptionTrim);
    }
    $url = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    if ($url !== '') {
        $safeUrl = admin_telegram_escape($url);
        $parts[] = '<a href="' . $safeUrl . '">Lee el artículo</a>';
    }
    $fediverseAppendix = admin_social_appendix_html($fediverseUrl);
    if ($fediverseAppendix !== '') {
        $parts[] = $fediverseAppendix;
    }
    if (empty($parts)) {
        $parts[] = admin_telegram_escape('Nueva publicación disponible');
    }
    return implode("\n\n", $parts);
}

function admin_social_network_labels(bool $includeFediverse = false): array
{
    $labels = [
        'telegram' => 'Telegram',
        'facebook' => 'Facebook',
        'twitter' => 'X',
        'linkedin' => 'LinkedIn',
        'bluesky' => 'Bluesky',
        'instagram' => 'Instagram',
    ];
    if ($includeFediverse) {
        $labels['fediverse'] = 'Fediverso';
    }
    return $labels;
}

function admin_send_content_to_social_network(
    string $networkKey,
    string $slug,
    string $title,
    string $description,
    string $image,
    array $networkSettings,
    string $urlOverride = '',
    string $imageUrl = '',
    string $contentLabel = 'publicación'
): array {
    $labels = admin_social_network_labels();
    $label = $labels[$networkKey] ?? ucfirst($networkKey);
    if (!isset($labels[$networkKey])) {
        return ['ok' => false, 'message' => 'Red social no válida.'];
    }
    if (!admin_is_social_network_configured($networkKey, $networkSettings)) {
        return ['ok' => false, 'message' => 'Configura correctamente ' . $label . ' en la pestaña Difusión antes de enviar.'];
    }

    $sent = false;
    $customError = null;
    switch ($networkKey) {
        case 'telegram':
            $sent = admin_send_post_to_telegram($slug, $title, $description, $networkSettings, $urlOverride, $imageUrl);
            break;
        case 'facebook':
            error_log('Facebook manual send: slug=' . $slug . ' pageId=' . ($networkSettings['channel'] ?? ''));
            $sent = admin_send_facebook_post($slug, $title, $description, $networkSettings, $urlOverride, $imageUrl);
            if (!$sent) {
                error_log('Facebook manual send failed for slug=' . $slug);
            }
            break;
        case 'twitter':
            $sent = admin_send_twitter_post($slug, $title, $description, $networkSettings, $urlOverride, $imageUrl, $customError);
            break;
        case 'linkedin':
            $sent = admin_send_linkedin_post($slug, $title, $description, $networkSettings, $urlOverride, $imageUrl, $customError);
            break;
        case 'bluesky':
            $sent = admin_send_bluesky_post($slug, $title, $description, $networkSettings, $urlOverride, $imageUrl, $customError);
            break;
        case 'instagram':
            if (trim($image) === '') {
                $customError = 'Instagram requiere una imagen destacada para enviar la publicación.';
                break;
            }
            $sent = admin_send_instagram_post($slug, $title, $image, $networkSettings, $description, $urlOverride, $customError);
            break;
    }

    if ($sent) {
        return [
            'ok' => true,
            'message' => ucfirst($contentLabel) . ' enviada correctamente a ' . $label . '.',
        ];
    }
    if ($customError !== null) {
        return ['ok' => false, 'message' => $customError];
    }
    return ['ok' => false, 'message' => 'No se pudo enviar ' . $contentLabel . ' a ' . $label . '. Comprueba las credenciales.'];
}

function admin_maybe_auto_post_to_social_networks(string $filename, string $title, string $description, string $image = '', string $urlOverride = '', string $imageUrl = ''): void {
    $slug = pathinfo($filename, PATHINFO_FILENAME);
    if ($slug === '') {
        $slug = $filename;
    }
    $settings = admin_cached_social_settings();
    $networks = [];
    foreach (['telegram', 'facebook', 'twitter', 'linkedin', 'bluesky', 'instagram'] as $network) {
        $networkSettings = is_array($settings[$network] ?? null) ? $settings[$network] : [];
        if (($networkSettings['auto_post'] ?? 'off') !== 'on' || !admin_is_social_network_configured($network, $networkSettings)) {
            continue;
        }
        if ($network === 'instagram' && trim($image) === '') {
            continue;
        }
        $networks[] = $network;
    }
    if (empty($networks)) {
        return;
    }
    if (!function_exists('admin_enqueue_social_broadcast') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
        require_once NAMMU_ROOT . '/core/admin-redes.php';
    }
    if (!function_exists('admin_enqueue_social_broadcast')) {
        return;
    }
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $fediverseTemplate = admin_social_fediverse_template($slug, $urlOverride);
    $fediverseUrl = admin_social_fediverse_thread_url($slug, $fediverseTemplate);
    $messageTitle = trim($title) !== '' ? '**' . trim($title) . '**' : '';
    $message = admin_build_sentence_limited_social_message($messageTitle, $description, $targetUrl, 1200);
    $imageRef = trim($image) !== '' ? $image : $imageUrl;
    admin_enqueue_social_broadcast($message, $imageRef, $networks, $fediverseUrl, [
        'source' => 'site-content',
        'source_id' => $fediverseTemplate . '|' . $slug,
    ]);
}

function admin_maybe_enqueue_push_notification(string $type, string $title, string $description, string $url, string $image = ''): void {
    if (!function_exists('nammu_enqueue_push_notification')) {
        return;
    }
    $settings = get_settings();
    $push = $settings['ads'] ?? [];
    if (($push['push_enabled'] ?? 'off') !== 'on') {
        return;
    }
    if ($type === 'post' && ($push['push_posts'] ?? 'off') !== 'on') {
        return;
    }
    if ($type === 'itinerary' && ($push['push_itineraries'] ?? 'off') !== 'on') {
        return;
    }
    $trackedUrl = function_exists('admin_add_utm_params')
        ? admin_add_utm_params($url, [
            'utm_source' => 'push',
            'utm_medium' => 'push',
        ])
        : $url;
    $payload = [
        'title' => $title,
        'body' => $description,
        'url' => $trackedUrl,
        'icon' => $image,
    ];
    nammu_enqueue_push_notification($payload);
}
