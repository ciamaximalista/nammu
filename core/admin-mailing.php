<?php
/**
 * Nammu — panel de administración.
 * Lista de correo: suscriptores, preferencias, supresiones, rebotes (Gmail API) y tokens de baja.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

function admin_normalize_email(string $email): string {
    $email = strtolower(trim($email));
    return $email;
}

function admin_normalize_csv_header(string $header): string {
    $header = trim($header);
    if ($header === '') {
        return '';
    }
    $header = preg_replace('/^\xEF\xBB\xBF/u', '', $header);
    $header = mb_strtolower($header, 'UTF-8');
    $replacements = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
    ];
    $header = strtr($header, $replacements);
    $header = preg_replace('/[^a-z0-9]+/', ' ', $header);
    return trim($header);
}

function admin_postal_csv_column_map(array $headers): array {
    $map = [];
    foreach ($headers as $index => $header) {
        $key = admin_normalize_csv_header((string) $header);
        if ($key === '') {
            continue;
        }
        if (in_array($key, ['email', 'correo', 'correo electronico', 'e mail'], true)) {
            $map['email'] = $index;
        } elseif (in_array($key, ['nombre', 'nombre y apellidos', 'nombre completo'], true)) {
            $map['name'] = $index;
        } elseif (in_array($key, ['direccion', 'direccion postal', 'domicilio'], true)) {
            $map['address'] = $index;
        } elseif (in_array($key, ['poblacion', 'ciudad', 'localidad'], true)) {
            $map['city'] = $index;
        } elseif (in_array($key, ['codigo postal', 'cp', 'postal code', 'codigo'], true)) {
            $map['postal_code'] = $index;
        } elseif (in_array($key, ['provincia', 'region', 'provincia region', 'provincia region'], true)) {
            $map['region'] = $index;
        } elseif (in_array($key, ['pais', 'pais de residencia', 'country'], true)) {
            $map['country'] = $index;
        }
    }
    return $map;
}

function admin_maybe_add_to_mailing_list(string $email): void {
    $normalized = admin_normalize_email($email);
    if ($normalized === '') {
        return;
    }
    $suppressed = admin_mailing_suppressed_map();
    if (isset($suppressed[$normalized])) {
        return;
    }
    try {
        $subscribers = admin_load_mailing_subscriber_entries();
    } catch (Throwable $e) {
        return;
    }
    foreach ($subscribers as $subscriber) {
        if (admin_normalize_email((string) ($subscriber['email'] ?? '')) === $normalized) {
            return;
        }
    }
    $subscribers[] = [
        'email' => $normalized,
        'prefs' => admin_mailing_default_prefs(),
    ];
    try {
        admin_save_mailing_subscriber_entries($subscribers);
    } catch (Throwable $e) {
        return;
    }
}

function admin_parse_hex_color(string $hex, int &$r, int &$g, int &$b): bool {
    $value = ltrim(trim($hex), '#');
    if (strlen($value) === 3) {
        $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
    }
    if (strlen($value) !== 6 || !ctype_xdigit($value)) {
        return false;
    }
    $r = hexdec(substr($value, 0, 2));
    $g = hexdec(substr($value, 2, 2));
    $b = hexdec(substr($value, 4, 2));
    return true;
}

function admin_pick_contrast_color(string $backgroundHex, string $light = '#ffffff', string $dark = '#111111'): string {
    $r = $g = $b = 0;
    if (!admin_parse_hex_color($backgroundHex, $r, $g, $b)) {
        return $dark;
    }
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return $yiq >= 160 ? $dark : $light;
}

function admin_mailing_default_prefs(): array {
    return [
        'posts' => true,
        'itineraries' => true,
        'podcast' => true,
        'newsletter' => true,
    ];
}

function admin_mailing_normalize_prefs(array $prefs): array {
    $defaults = admin_mailing_default_prefs();
    $normalized = [];
    $hasPodcastKey = array_key_exists('podcast', $prefs);
    foreach ($defaults as $key => $default) {
        $value = $prefs[$key] ?? $default;
        if (is_string($value)) {
            $value = strtolower($value) !== 'off' && $value !== '0' && $value !== '';
        } else {
            $value = (bool) $value;
        }
        $normalized[$key] = $value;
    }
    if (!$hasPodcastKey) {
        $normalized['podcast'] = !empty($normalized['posts']) || !empty($normalized['itineraries']);
    }
    return $normalized;
}

function admin_mailing_normalize_entry($entry): ?array {
    if (is_string($entry)) {
        $email = admin_normalize_email($entry);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return [
            'email' => $email,
            'prefs' => admin_mailing_default_prefs(),
        ];
    }
    if (!is_array($entry)) {
        return null;
    }
    $email = admin_normalize_email((string) ($entry['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $prefsRaw = $entry['prefs'] ?? [];
    $prefs = is_array($prefsRaw) ? admin_mailing_normalize_prefs($prefsRaw) : admin_mailing_default_prefs();
    return [
        'email' => $email,
        'prefs' => $prefs,
    ];
}

function admin_load_mailing_suppressed_entries(): array {
    $file = MAILING_SUPPRESSED_FILE;
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $unique = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $email = admin_normalize_email((string) ($entry['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $unique[$email] = [
            'email' => $email,
            'reason' => trim((string) ($entry['reason'] ?? '')),
            'source' => trim((string) ($entry['source'] ?? '')),
            'created_at' => trim((string) ($entry['created_at'] ?? '')),
            'last_error' => trim((string) ($entry['last_error'] ?? '')),
        ];
    }
    return array_values($unique);
}

function admin_save_mailing_suppressed_entries(array $entries): void {
    $file = MAILING_SUPPRESSED_FILE;
    $dir = dirname($file);
    if (!nammu_ensure_directory($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de configuración para la lista de supresión');
    }
    $unique = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $email = admin_normalize_email((string) ($entry['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $unique[$email] = [
            'email' => $email,
            'reason' => trim((string) ($entry['reason'] ?? '')),
            'source' => trim((string) ($entry['source'] ?? '')),
            'created_at' => trim((string) ($entry['created_at'] ?? '')),
            'last_error' => trim((string) ($entry['last_error'] ?? '')),
        ];
    }
    $payload = json_encode(array_values($unique), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar la lista de supresión');
    }
    $saved = function_exists('nammu_atomic_write_file')
        ? nammu_atomic_write_file($file, $payload)
        : file_put_contents($file, $payload, LOCK_EX) !== false;
    if (!$saved) {
        throw new RuntimeException('No se pudo escribir el archivo de supresión');
    }
    nammu_apply_shared_permissions($file, 0664, $dir);
}

function admin_load_mailing_bounces_state(): array {
    $file = MAILING_BOUNCES_STATE_FILE;
    if (!is_file($file)) {
        return ['processed_ids' => [], 'last_checked_at' => ''];
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return ['processed_ids' => [], 'last_checked_at' => ''];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['processed_ids' => [], 'last_checked_at' => ''];
    }
    $ids = is_array($decoded['processed_ids'] ?? null) ? $decoded['processed_ids'] : [];
    $ids = array_values(array_filter(array_map(static function ($value): string {
        return trim((string) $value);
    }, $ids), static function (string $value): bool {
        return $value !== '';
    }));
    return [
        'processed_ids' => $ids,
        'last_checked_at' => trim((string) ($decoded['last_checked_at'] ?? '')),
    ];
}

function admin_save_mailing_bounces_state(array $state): void {
    $file = MAILING_BOUNCES_STATE_FILE;
    $dir = dirname($file);
    if (!nammu_ensure_directory($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de estado de rebotes');
    }
    $payload = [
        'processed_ids' => array_slice(array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, (array) ($state['processed_ids'] ?? [])), static function (string $value): bool {
            return $value !== '';
        })), -500),
        'last_checked_at' => trim((string) ($state['last_checked_at'] ?? '')),
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar el estado de rebotes');
    }
    $saved = function_exists('nammu_atomic_write_file')
        ? nammu_atomic_write_file($file, $json)
        : file_put_contents($file, $json, LOCK_EX) !== false;
    if (!$saved) {
        throw new RuntimeException('No se pudo guardar el estado de rebotes');
    }
    nammu_apply_shared_permissions($file, 0664, $dir);
}

function admin_mailing_suppressed_map(): array {
    $map = [];
    foreach (admin_load_mailing_suppressed_entries() as $entry) {
        $email = admin_normalize_email((string) ($entry['email'] ?? ''));
        if ($email !== '') {
            $map[$email] = $entry;
        }
    }
    return $map;
}

function admin_remove_mailing_subscriber(string $email): void {
    $normalized = admin_normalize_email($email);
    if ($normalized === '') {
        return;
    }
    $entries = admin_load_mailing_subscriber_entries();
    $filtered = array_values(array_filter($entries, static function ($entry) use ($normalized): bool {
        return admin_normalize_email((string) ($entry['email'] ?? '')) !== $normalized;
    }));
    admin_save_mailing_subscriber_entries($filtered);
}

function admin_suppress_mailing_recipient(string $email, string $reason = 'hard_bounce', string $source = 'gmail', string $lastError = ''): void {
    $normalized = admin_normalize_email($email);
    if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $entries = admin_load_mailing_suppressed_entries();
    $updated = false;
    foreach ($entries as &$entry) {
        $entryEmail = admin_normalize_email((string) ($entry['email'] ?? ''));
        if ($entryEmail !== $normalized) {
            continue;
        }
        $entry['reason'] = $reason;
        $entry['source'] = $source;
        $entry['created_at'] = trim((string) ($entry['created_at'] ?? '')) !== '' ? (string) $entry['created_at'] : gmdate(DATE_ATOM);
        $entry['last_error'] = $lastError;
        $updated = true;
        break;
    }
    unset($entry);
    if (!$updated) {
        $entries[] = [
            'email' => $normalized,
            'reason' => $reason,
            'source' => $source,
            'created_at' => gmdate(DATE_ATOM),
            'last_error' => $lastError,
        ];
    }
    admin_save_mailing_suppressed_entries($entries);
    admin_remove_mailing_subscriber($normalized);
}

function admin_is_mailing_hard_bounce_error(?string $error): bool {
    $error = strtolower(trim((string) $error));
    if ($error === '') {
        return false;
    }
    $markers = [
        'invalid to header',
        'recipient address rejected',
        'user unknown',
        'unknown user',
        'no such user',
        'mailbox unavailable',
        'address not found',
        'recipient not found',
        '550 5.1.1',
        '550 5.1.0',
        '551 5.1.1',
        '553 5.1.3',
        'invalid recipient',
        'bad recipient address syntax',
    ];
    foreach ($markers as $marker) {
        if (str_contains($error, $marker)) {
            return true;
        }
    }
    return false;
}

function admin_gmail_api_request_json(string $url, string $accessToken): ?array {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$accessToken}\r\nAccept: application/json\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ];
    $context = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function admin_gmail_message_header_value(array $payload, string $name): string {
    $headers = is_array($payload['headers'] ?? null) ? $payload['headers'] : [];
    foreach ($headers as $header) {
        if (!is_array($header)) {
            continue;
        }
        if (strcasecmp((string) ($header['name'] ?? ''), $name) !== 0) {
            continue;
        }
        return trim((string) ($header['value'] ?? ''));
    }
    return '';
}

function admin_gmail_decode_body_data(string $data): string {
    $data = trim($data);
    if ($data === '') {
        return '';
    }
    $decoded = base64_decode(strtr($data, '-_', '+/'), true);
    return is_string($decoded) ? $decoded : '';
}

function admin_gmail_message_text_parts(array $payload): array {
    $texts = [];
    $mimeType = strtolower(trim((string) ($payload['mimeType'] ?? '')));
    $bodyData = trim((string) (($payload['body']['data'] ?? '') ?: ''));
    if (($mimeType === 'text/plain' || $mimeType === 'message/delivery-status' || $mimeType === 'message/rfc822') && $bodyData !== '') {
        $decoded = admin_gmail_decode_body_data($bodyData);
        if ($decoded !== '') {
            $texts[] = $decoded;
        }
    }
    $parts = is_array($payload['parts'] ?? null) ? $payload['parts'] : [];
    foreach ($parts as $part) {
        if (is_array($part)) {
            $texts = array_merge($texts, admin_gmail_message_text_parts($part));
        }
    }
    return $texts;
}

function admin_extract_bounced_emails_from_text(string $text, string $senderEmail = ''): array {
    $text = trim($text);
    if ($text === '') {
        return [];
    }
    $candidates = [];
    $patterns = [
        '/Final-Recipient:\s*rfc822;\s*([^\s<>]+)/i',
        '/Original-Recipient:\s*rfc822;\s*([^\s<>]+)/i',
        '/X-Failed-Recipients:\s*([^\r\n]+)/i',
        '/Diagnostic-Code:.*?<([^>\s]+@[^>\s]+)>/i',
        '/\b([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})\b/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ((array) ($matches[1] ?? []) as $match) {
                $email = admin_normalize_email((string) $match);
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $candidates[$email] = true;
            }
        }
    }
    $senderEmail = admin_normalize_email($senderEmail);
    if ($senderEmail !== '' && isset($candidates[$senderEmail])) {
        unset($candidates[$senderEmail]);
    }
    $filtered = [];
    foreach (array_keys($candidates) as $email) {
        if (str_contains($email, 'mailer-daemon') || str_contains($email, 'postmaster')) {
            continue;
        }
        $filtered[$email] = true;
    }
    return array_keys($filtered);
}

function admin_process_mailing_bounces(int $maxMessages = 12): array {
    $settings = get_settings();
    if (!admin_is_mailing_ready($settings)) {
        return ['scanned' => 0, 'suppressed' => 0];
    }
    $mailing = $settings['mailing'] ?? [];
    $gmail = trim((string) ($mailing['gmail_address'] ?? ''));
    $clientId = trim((string) ($mailing['client_id'] ?? ''));
    $clientSecret = trim((string) ($mailing['client_secret'] ?? ''));
    $tokens = admin_load_mailing_tokens();
    $refresh = trim((string) ($tokens['refresh_token'] ?? ''));
    if ($gmail === '' || $clientId === '' || $clientSecret === '' || $refresh === '') {
        return ['scanned' => 0, 'suppressed' => 0];
    }
    $refreshed = admin_google_refresh_access_token($clientId, $clientSecret, $refresh);
    $accessToken = trim((string) ($refreshed['access_token'] ?? ''));
    if ($accessToken === '') {
        return ['scanned' => 0, 'suppressed' => 0];
    }
    $state = admin_load_mailing_bounces_state();
    $processedMap = [];
    foreach ((array) ($state['processed_ids'] ?? []) as $id) {
        $id = trim((string) $id);
        if ($id !== '') {
            $processedMap[$id] = true;
        }
    }
    $query = rawurlencode('from:(mailer-daemon OR postmaster) newer_than:30d');
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages?q=' . $query . '&maxResults=' . max(1, $maxMessages);
    $list = admin_gmail_api_request_json($url, $accessToken);
    $messages = is_array($list['messages'] ?? null) ? $list['messages'] : [];
    $scanned = 0;
    $suppressed = 0;
    foreach ($messages as $message) {
        $messageId = trim((string) ($message['id'] ?? ''));
        if ($messageId === '' || isset($processedMap[$messageId])) {
            continue;
        }
        $detail = admin_gmail_api_request_json('https://gmail.googleapis.com/gmail/v1/users/me/messages/' . rawurlencode($messageId) . '?format=full', $accessToken);
        $payload = is_array($detail['payload'] ?? null) ? $detail['payload'] : [];
        $from = admin_gmail_message_header_value($payload, 'From');
        $fromEmail = '';
        if (preg_match('/<([^>]+)>/', $from, $match) === 1) {
            $fromEmail = admin_normalize_email((string) ($match[1] ?? ''));
        }
        if ($fromEmail === '') {
            $fromEmail = admin_normalize_email($from);
        }
        $text = implode("\n", admin_gmail_message_text_parts($payload));
        if ($text === '') {
            $text = (string) ($detail['snippet'] ?? '');
        }
        $emails = admin_extract_bounced_emails_from_text($text, $gmail);
        foreach ($emails as $email) {
            try {
                admin_suppress_mailing_recipient($email, 'hard_bounce_async', 'gmail_bounce', 'DSN');
                $suppressed++;
            } catch (Throwable $e) {
                // Ignore individual suppression failures.
            }
        }
        $processedMap[$messageId] = true;
        $scanned++;
    }
    admin_save_mailing_bounces_state([
        'processed_ids' => array_keys($processedMap),
        'last_checked_at' => gmdate(DATE_ATOM),
    ]);
    return ['scanned' => $scanned, 'suppressed' => $suppressed];
}

function admin_load_mailing_subscriber_entries(): array {
    $file = MAILING_SUBSCRIBERS_FILE;
    if (!is_file($file)) {
        try {
            admin_save_mailing_subscriber_entries([]);
        } catch (Throwable $e) {
            return [];
        }
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $unique = [];
    foreach ($decoded as $entry) {
        $normalized = admin_mailing_normalize_entry($entry);
        if ($normalized === null) {
            continue;
        }
        $email = $normalized['email'];
        if (!isset($unique[$email])) {
            $unique[$email] = $normalized;
            continue;
        }
        $existingPrefs = $unique[$email]['prefs'] ?? admin_mailing_default_prefs();
        $incomingPrefs = $normalized['prefs'] ?? admin_mailing_default_prefs();
        foreach ($incomingPrefs as $key => $value) {
            $existingPrefs[$key] = ($existingPrefs[$key] ?? false) || $value;
        }
        $unique[$email]['prefs'] = $existingPrefs;
    }
    return array_values($unique);
}

function admin_save_mailing_subscriber_entries(array $entries): void {
    $file = MAILING_SUBSCRIBERS_FILE;
    $dir = dirname($file);
    if (!nammu_ensure_directory($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de configuración para la lista de correo');
    }
    $suppressed = admin_mailing_suppressed_map();
    $unique = [];
    foreach ($entries as $entry) {
        $normalized = admin_mailing_normalize_entry($entry);
        if ($normalized === null) {
            continue;
        }
        $email = $normalized['email'];
        if (isset($suppressed[$email])) {
            continue;
        }
        $unique[$email] = $normalized;
    }
    $payload = json_encode(array_values($unique), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar la lista de suscriptores');
    }
    $saved = function_exists('nammu_atomic_write_file')
        ? nammu_atomic_write_file($file, $payload)
        : file_put_contents($file, $payload, LOCK_EX) !== false;
    if (!$saved) {
        throw new RuntimeException('No se pudo escribir el archivo de suscriptores');
    }
    nammu_apply_shared_permissions($file, 0664, $dir);
}

function admin_load_mailing_subscribers(): array {
    $entries = admin_load_mailing_subscriber_entries();
    $emails = [];
    foreach ($entries as $entry) {
        $email = admin_normalize_email((string) ($entry['email'] ?? ''));
        if ($email !== '') {
            $emails[] = $email;
        }
    }
    return $emails;
}

function admin_save_mailing_subscribers(array $subscribers): void {
    $entries = [];
    foreach ($subscribers as $subscriber) {
        $normalized = admin_mailing_normalize_entry($subscriber);
        if ($normalized === null) {
            continue;
        }
        $entries[] = $normalized;
    }
    admin_save_mailing_subscriber_entries($entries);
}

function admin_mailing_recipients_for_type(string $type, array $settings): array {
    $type = strtolower(trim($type));
    $allowed = ['posts', 'itineraries', 'podcast', 'newsletter'];
    if (!in_array($type, $allowed, true)) {
        return [];
    }
    $entries = admin_load_mailing_subscriber_entries();
    $suppressed = admin_mailing_suppressed_map();
    $recipients = [];
    foreach ($entries as $entry) {
        $email = admin_normalize_email((string) ($entry['email'] ?? ''));
        if ($email === '' || isset($suppressed[$email])) {
            continue;
        }
        $prefs = $entry['prefs'] ?? admin_mailing_default_prefs();
        if (!empty($prefs[$type])) {
            $recipients[] = $email;
        }
    }
    return $recipients;
}

function admin_mailing_type_for_template(string $template): string {
    $template = strtolower(trim($template));
    if ($template === 'itinerario') {
        return 'itineraries';
    }
    if ($template === 'podcast') {
        return 'podcast';
    }
    if ($template === 'newsletter') {
        return 'newsletter';
    }
    return 'posts';
}

function admin_load_mailing_tokens(): array {
    $file = NAMMU_ROOT . '/config/mailing-tokens.json';
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function admin_save_mailing_tokens(array $tokens): void {
    $file = NAMMU_ROOT . '/config/mailing-tokens.json';
    $dir = dirname($file);
    if (!nammu_ensure_directory($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de configuración para tokens de correo');
    }
    $payload = json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar los tokens de correo');
    }
    $saved = function_exists('nammu_atomic_write_file')
        ? nammu_atomic_write_file($file, $payload)
        : file_put_contents($file, $payload, LOCK_EX) !== false;
    if (!$saved) {
        throw new RuntimeException('No se pudo escribir el archivo de tokens de correo');
    }
    nammu_apply_shared_permissions($file, 0660, $dir);
}

function admin_delete_mailing_tokens(): void {
    $file = NAMMU_ROOT . '/config/mailing-tokens.json';
    if (is_file($file)) {
        @unlink($file);
    }
}

function admin_mailing_secret(): string {
    $file = MAILING_SECRET_FILE;
    if (!is_file($file)) {
        $dir = dirname($file);
        nammu_ensure_directory($dir);
        $secret = bin2hex(random_bytes(32));
        nammu_atomic_write_file($file, $secret);
        nammu_apply_shared_permissions($file, 0640, $dir);
        return $secret;
    }
    $secret = trim((string) file_get_contents($file));
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        nammu_atomic_write_file($file, $secret);
        nammu_apply_shared_permissions($file, 0640, dirname($file));
    }
    return $secret;
}

function admin_mailing_unsubscribe_token(string $email): string {
    $secret = admin_mailing_secret();
    return hash_hmac('sha256', strtolower(trim($email)), $secret);
}

function admin_mailing_unsubscribe_link(string $email): string {
    $token = admin_mailing_unsubscribe_token($email);
    return admin_base_url() . '/unsubscribe.php?email=' . urlencode($email) . '&token=' . urlencode($token);
}
