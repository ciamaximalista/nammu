<?php
/**
 * Nammu — panel de administración.
 * Envío de correo: Gmail API, campañas, tandas, entregas y construcción de mensajes/newsletters.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */


function admin_google_refresh_access_token(string $clientId, string $clientSecret, string $refreshToken): array {
    $postData = http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ];
    $context = stream_context_create($opts);
    $raw = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
    if ($raw === false) {
        $statusLine = nammu_last_response_headers($http_response_header ?? null)[0] ?? '';
        $status = $statusLine !== '' ? ' (' . $statusLine . ')' : '';
        throw new RuntimeException('No se pudo refrescar el token con Google' . $status);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta inesperada al refrescar token.');
    }
    if (isset($decoded['error'])) {
        $message = is_string($decoded['error']) ? $decoded['error'] : 'Error de OAuth';
        $desc = isset($decoded['error_description']) ? ' (' . $decoded['error_description'] . ')' : '';
        throw new RuntimeException($message . $desc);
    }
    $now = time();
    if (isset($decoded['expires_in'])) {
        $decoded['expires_at'] = $now + (int) $decoded['expires_in'];
    }
    return $decoded;
}

function admin_gmail_send_message(string $from, string $to, string $subject, string $textBody, string $htmlBody, string $accessToken, ?string $fromName = null): array {
    $boundary = '=_NammuMailer_' . bin2hex(random_bytes(8));
    $fromHeader = admin_format_email_mailbox($from, $fromName);
    $subjectHeader = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8', 'Q', "\r\n")
        : '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $unsubscribe = admin_mailing_unsubscribe_link($to);
    $headers = [
        'From: ' . $fromHeader,
        'Reply-To: ' . $fromHeader,
        'To: ' . $to,
        'Subject: ' . $subjectHeader,
        'MIME-Version: 1.0',
        'List-Unsubscribe: <' . $unsubscribe . '>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body = [];
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/plain; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: 7bit';
    $body[] = '';
    $body[] = $textBody;
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/html; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: 7bit';
    $body[] = '';
    $body[] = $htmlBody;
    $body[] = '--' . $boundary . '--';
    $rawMessage = implode("\r\n", array_merge($headers, [''], $body));
    $payload = json_encode(['raw' => rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=')]);
    if ($payload === false) {
        throw new RuntimeException('No se pudo preparar el mensaje.');
    }
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', false, $context);
    if ($response === false) {
        $status = nammu_last_response_headers($http_response_header ?? null)[0] ?? 'sin respuesta';
        return [false, 'HTTP ' . $status];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || !isset($decoded['id'])) {
        if (isset($decoded['error']['message'])) {
            return [false, 'Error Gmail: ' . $decoded['error']['message']];
        }
        return [false, 'Respuesta inesperada al enviar correo'];
    }
    return [true, null];
}

function admin_format_email_mailbox(string $email, ?string $displayName = null): string
{
    $email = admin_normalize_email($email);
    $displayName = trim((string) $displayName);
    $displayName = str_replace(["\r", "\n"], '', $displayName);
    if ($email === '') {
        return '';
    }
    if ($displayName === '') {
        return $email;
    }

    $escapedDisplayName = str_replace(['\\', '"'], ['\\\\', '\\"'], $displayName);
    $isAscii = preg_match('/^[\x20-\x7E]+$/', $displayName) === 1;
    if ($isAscii) {
        return '"' . $escapedDisplayName . '" <' . $email . '>';
    }

    $encodedName = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($displayName, 'UTF-8', 'Q', "\r\n")
        : '=?UTF-8?B?' . base64_encode($displayName) . '?=';
    return $encodedName . ' <' . $email . '>';
}

function admin_is_mailing_ready(array $settings): bool {
    $mailing = $settings['mailing'] ?? [];
    $gmail = $mailing['gmail_address'] ?? '';
    $clientId = $mailing['client_id'] ?? '';
    $clientSecret = $mailing['client_secret'] ?? '';
    $tokens = admin_load_mailing_tokens();
    return $gmail !== '' && $clientId !== '' && $clientSecret !== '' && !empty($tokens['refresh_token']);
}

function admin_send_mailing_broadcast(string $subject, string $textBody, string $htmlBody, array $subscribers, array $mailingConfig, ?callable $bodyBuilder = null, ?string $fromName = null): array {
    $gmail = $mailingConfig['gmail_address'] ?? '';
    $clientId = $mailingConfig['client_id'] ?? '';
    $clientSecret = $mailingConfig['client_secret'] ?? '';
    if ($fromName === null || trim($fromName) === '') {
        $settings = get_settings();
        $authorName = trim((string) ($settings['site_author'] ?? ''));
        $blogName = trim((string) ($settings['site_name'] ?? ''));
        $fromName = $authorName !== '' ? $authorName : $blogName;
    }
    $tokens = admin_load_mailing_tokens();
    $refresh = $tokens['refresh_token'] ?? '';
    if ($gmail === '' || $clientId === '' || $clientSecret === '' || $refresh === '') {
        throw new RuntimeException('Falta configuración o tokens de Gmail.');
    }
    $refreshed = admin_google_refresh_access_token($clientId, $clientSecret, $refresh);
    $accessToken = $refreshed['access_token'] ?? '';
    if ($accessToken === '') {
        throw new RuntimeException('No se obtuvo access_token al refrescar.');
    }
    if ($fromName && $gmail) {
        admin_gmail_update_display_name($gmail, $fromName, $accessToken);
    }
    $tokens['access_token'] = $accessToken;
    if (isset($refreshed['expires_at'])) {
        $tokens['expires_at'] = $refreshed['expires_at'];
    }
    admin_save_mailing_tokens($tokens);
    $ok = 0;
    $fail = 0;
    $lastError = null;
    $failedRecipients = [];
    foreach ($subscribers as $to) {
        $textToSend = $textBody;
        $htmlToSend = $htmlBody;
        if ($bodyBuilder !== null) {
            [$textToSend, $htmlToSend] = $bodyBuilder($to);
        }
        [$sent, $err] = admin_gmail_send_message($gmail, $to, $subject, $textToSend, $htmlToSend, $accessToken, $fromName);
        if ($sent) {
            $ok++;
        } else {
            $fail++;
            $normalizedRecipient = is_string($to) ? admin_normalize_email($to) : '';
            $isHardBounce = admin_is_mailing_hard_bounce_error($err);
            if ($normalizedRecipient !== '') {
                if ($isHardBounce) {
                    try {
                        admin_suppress_mailing_recipient($normalizedRecipient, 'hard_bounce', 'gmail', (string) $err);
                    } catch (Throwable $e) {
                        // Ignore suppression persistence errors and continue classifying the send failure.
                    }
                } else {
                    $failedRecipients[] = $normalizedRecipient;
                }
            }
            if ($err !== null) {
                $lastError = $err;
            }
        }
    }
    return ['sent' => $ok, 'failed' => $fail, 'failed_recipients' => array_values(array_unique($failedRecipients)), 'error' => $lastError];
}

function admin_mailing_batch_size(): int
{
    return 40;
}

function admin_mailing_deliveries_file(): string
{
    return NAMMU_ROOT . '/config/mailing-deliveries.json';
}

function admin_load_mailing_deliveries(): array
{
    $file = admin_mailing_deliveries_file();
    if (!is_file($file)) {
        return ['items' => []];
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return ['items' => []];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['items' => []];
}

function admin_save_mailing_deliveries(array $data): void
{
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $campaigns = is_array($data['campaigns'] ?? null) ? $data['campaigns'] : [];
    $attempts = is_array($data['attempts'] ?? null) ? array_values($data['attempts']) : [];
    if (count($items) > 8000) {
        uasort($items, static function ($a, $b): int {
            return (int) (($a['sent_at'] ?? 0)) <=> (int) (($b['sent_at'] ?? 0));
        });
        $items = array_slice($items, -8000, null, true);
    }
    if (count($attempts) > 80) {
        $attempts = array_slice($attempts, -80);
    }
    $payload = [
        'updated_at' => time(),
        'campaigns' => $campaigns,
        'attempts' => $attempts,
        'items' => $items,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (is_string($json)) {
        if (function_exists('nammu_atomic_write_file')) {
            nammu_atomic_write_file(admin_mailing_deliveries_file(), $json);
        } else {
            file_put_contents(admin_mailing_deliveries_file(), $json, LOCK_EX);
            @chmod(admin_mailing_deliveries_file(), 0664);
        }
    }
}

function admin_mailing_content_key(string $context, array $payload): string
{
    $context = strtolower(trim($context));
    $slug = trim((string) ($payload['slug'] ?? ''));
    if ($slug !== '') {
        return $context . ':slug:' . $slug;
    }
    $filename = trim((string) ($payload['filename'] ?? ''));
    if ($filename !== '') {
        return $context . ':file:' . $filename;
    }
    if ($context === 'newsletter') {
        $title = trim((string) ($payload['title'] ?? ''));
        $html = (string) ($payload['content_html'] ?? '');
        $text = (string) ($payload['content_text'] ?? '');
        return $context . ':body:' . sha1($title . "\n" . $html . "\n" . $text);
    }
    $title = trim((string) ($payload['title'] ?? ''));
    return $context . ':payload:' . sha1(json_encode([$title, $payload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function admin_mailing_delivery_key(string $contentKey, string $email): string
{
    return sha1($contentKey . '|' . admin_normalize_email($email));
}

function admin_mailing_sent_map_for_content(string $contentKey): array
{
    $data = admin_load_mailing_deliveries();
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $map = [];
    foreach ($items as $key => $item) {
        if (($item['content_key'] ?? '') === $contentKey && !empty($item['email'])) {
            $map[admin_normalize_email((string) $item['email'])] = true;
            continue;
        }
        if (is_string($key) && isset($item['sent_at'])) {
            $parts = explode('|', $key, 2);
            if (count($parts) === 2 && $parts[0] === $contentKey) {
                $map[admin_normalize_email($parts[1])] = true;
            }
        }
    }
    return $map;
}

function admin_mailing_campaign_exists(string $contentKey): bool
{
    $data = admin_load_mailing_deliveries();
    $campaigns = is_array($data['campaigns'] ?? null) ? $data['campaigns'] : [];
    if (isset($campaigns[$contentKey])) {
        return true;
    }
    if (!function_exists('nammu_load_scheduled_notifications')) {
        return false;
    }
    foreach (nammu_load_scheduled_notifications() as $payload) {
        if (!is_array($payload)) {
            continue;
        }
        if (strtolower(trim((string) ($payload['notification_kind'] ?? ''))) !== 'mailing_batch') {
            continue;
        }
        $existingContentKey = trim((string) ($payload['mailing_content_key'] ?? ''));
        if ($existingContentKey === '') {
            $existingContentKey = admin_mailing_content_key((string) ($payload['mailing_context'] ?? ''), $payload);
        }
        if ($existingContentKey === $contentKey) {
            return true;
        }
    }
    return false;
}

function admin_mark_mailing_campaign_queued(string $context, array $payload, array $recipients): void
{
    $contentKey = admin_mailing_content_key($context, $payload);
    $recipients = admin_normalize_recipient_batch($recipients);
    if ($contentKey === '' || empty($recipients)) {
        return;
    }
    $data = admin_load_mailing_deliveries();
    $campaigns = is_array($data['campaigns'] ?? null) ? $data['campaigns'] : [];
    $campaigns[$contentKey] = [
        'content_key' => $contentKey,
        'context' => strtolower(trim($context)),
        'slug' => trim((string) ($payload['slug'] ?? '')),
        'filename' => trim((string) ($payload['filename'] ?? '')),
        'title' => trim((string) ($payload['title'] ?? '')),
        'queued_recipients' => count($recipients),
        'queued_at' => time(),
    ];
    $data['campaigns'] = $campaigns;
    admin_save_mailing_deliveries($data);
}

function admin_record_mailing_batch_attempt(string $context, array $payload, array $result, array $attemptedRecipients, array $deliveredRecipients): void
{
    $contentKey = trim((string) ($payload['mailing_content_key'] ?? ''));
    if ($contentKey === '') {
        $contentKey = admin_mailing_content_key($context, $payload);
    }
    if ($contentKey === '') {
        return;
    }
    $attemptedRecipients = admin_normalize_recipient_batch($attemptedRecipients);
    $deliveredRecipients = admin_normalize_recipient_batch($deliveredRecipients);
    $failedRecipients = admin_normalize_recipient_batch((array) ($result['failed_recipients'] ?? []));
    $data = admin_load_mailing_deliveries();
    $campaigns = is_array($data['campaigns'] ?? null) ? $data['campaigns'] : [];
    $attempts = is_array($data['attempts'] ?? null) ? array_values($data['attempts']) : [];
    $now = time();
    if (!isset($campaigns[$contentKey]) || !is_array($campaigns[$contentKey])) {
        $campaigns[$contentKey] = [
            'content_key' => $contentKey,
            'context' => strtolower(trim($context)),
            'slug' => trim((string) ($payload['slug'] ?? '')),
            'filename' => trim((string) ($payload['filename'] ?? '')),
            'title' => trim((string) ($payload['title'] ?? '')),
            'queued_recipients' => count($attemptedRecipients),
            'queued_at' => $now,
        ];
    }
    $campaign = $campaigns[$contentKey];
    $campaign['last_attempt_at'] = $now;
    $campaign['last_batch_index'] = (int) ($payload['batch_index'] ?? 0);
    $campaign['last_batch_total'] = (int) ($payload['batch_total'] ?? 0);
    $campaign['last_attempt_recipients'] = count($attemptedRecipients);
    $campaign['last_sent'] = (int) ($result['sent'] ?? count($deliveredRecipients));
    $campaign['last_failed'] = (int) ($result['failed'] ?? count($failedRecipients));
    $campaign['last_error'] = trim((string) ($result['error'] ?? ''));
    if (empty($campaign['queued_recipients'])) {
        $campaign['queued_recipients'] = count($attemptedRecipients);
    }
    if (!empty($deliveredRecipients)) {
        $campaign['last_sent_at'] = $now;
    }
    $campaigns[$contentKey] = $campaign;
    $attempts[] = [
        'content_key' => $contentKey,
        'context' => strtolower(trim($context)),
        'title' => trim((string) ($payload['title'] ?? '')),
        'batch_index' => (int) ($payload['batch_index'] ?? 0),
        'batch_total' => (int) ($payload['batch_total'] ?? 0),
        'attempted' => count($attemptedRecipients),
        'sent' => (int) ($result['sent'] ?? count($deliveredRecipients)),
        'failed' => (int) ($result['failed'] ?? count($failedRecipients)),
        'error' => trim((string) ($result['error'] ?? '')),
        'at' => $now,
    ];
    $data['campaigns'] = $campaigns;
    $data['attempts'] = $attempts;
    admin_save_mailing_deliveries($data);
}

function admin_mailing_context_label(string $context): string
{
    return match (strtolower(trim($context))) {
        'newsletter' => 'Newsletter',
        'podcast' => 'Podcast',
        'itinerary' => 'Itinerario',
        'post' => 'Post',
        default => 'Correo',
    };
}

function admin_mailing_dashboard_status(): array
{
    $data = admin_load_mailing_deliveries();
    $campaigns = is_array($data['campaigns'] ?? null) ? $data['campaigns'] : [];
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $pendingByContent = [];
    if (function_exists('nammu_load_scheduled_notifications')) {
        foreach (nammu_load_scheduled_notifications() as $payload) {
            if (!is_array($payload)) {
                continue;
            }
            if (strtolower(trim((string) ($payload['notification_kind'] ?? ''))) !== 'mailing_batch') {
                continue;
            }
            $contentKey = trim((string) ($payload['mailing_content_key'] ?? ''));
            if ($contentKey === '') {
                $contentKey = admin_mailing_content_key((string) ($payload['mailing_context'] ?? ''), $payload);
            }
            if ($contentKey === '') {
                continue;
            }
            $recipients = admin_normalize_recipient_batch((array) ($payload['recipients'] ?? []));
            $pendingByContent[$contentKey] = ($pendingByContent[$contentKey] ?? 0) + count($recipients);
            if (!isset($campaigns[$contentKey])) {
                $campaigns[$contentKey] = [
                    'content_key' => $contentKey,
                    'context' => strtolower(trim((string) ($payload['mailing_context'] ?? ''))),
                    'title' => trim((string) ($payload['title'] ?? '')),
                    'queued_recipients' => count($recipients),
                    'queued_at' => 0,
                ];
            }
            if (!empty($payload['last_error'])) {
                $campaigns[$contentKey]['last_error'] = trim((string) $payload['last_error']);
                $campaigns[$contentKey]['last_attempt_at'] = strtotime((string) ($payload['last_attempt_at'] ?? '')) ?: (int) ($campaigns[$contentKey]['last_attempt_at'] ?? 0);
            }
        }
    }
    $sentByContent = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $contentKey = trim((string) ($item['content_key'] ?? ''));
        if ($contentKey === '') {
            continue;
        }
        $sentByContent[$contentKey] = ($sentByContent[$contentKey] ?? 0) + 1;
    }
    $rows = [];
    foreach ($campaigns as $contentKey => $campaign) {
        if (!is_array($campaign)) {
            continue;
        }
        $contentKey = trim((string) (($campaign['content_key'] ?? '') ?: $contentKey));
        if ($contentKey === '') {
            continue;
        }
        $context = strtolower(trim((string) ($campaign['context'] ?? '')));
        $sent = (int) ($sentByContent[$contentKey] ?? 0);
        $pending = (int) ($pendingByContent[$contentKey] ?? 0);
        $queued = max((int) ($campaign['queued_recipients'] ?? 0), $sent + $pending);
        $lastAttempt = (int) ($campaign['last_attempt_at'] ?? 0);
        $queuedAt = (int) ($campaign['queued_at'] ?? 0);
        $lastActivity = max($lastAttempt, (int) ($campaign['last_sent_at'] ?? 0), $queuedAt);
        $lastError = trim((string) ($campaign['last_error'] ?? ''));
        $status = $pending > 0 ? 'pending' : 'sent';
        if ($lastError !== '' && $pending > 0) {
            $status = 'error';
        } elseif ($lastError !== '' && $pending === 0 && (int) ($campaign['last_failed'] ?? 0) > 0) {
            $status = 'partial';
        }
        $rows[] = [
            'content_key' => $contentKey,
            'context' => $context,
            'label' => admin_mailing_context_label($context),
            'title' => trim((string) ($campaign['title'] ?? '')),
            'queued' => $queued,
            'sent' => $sent,
            'pending' => $pending,
            'last_failed' => (int) ($campaign['last_failed'] ?? 0),
            'last_error' => $lastError,
            'last_attempt_at' => $lastAttempt,
            'queued_at' => $queuedAt,
            'last_activity' => $lastActivity,
            'status' => $status,
        ];
    }
    $rowsByActivity = $rows;
    usort($rowsByActivity, static function (array $a, array $b): int {
        return ((int) ($b['last_activity'] ?? 0)) <=> ((int) ($a['last_activity'] ?? 0));
    });
    $rowsByQueuedAt = $rows;
    usort($rowsByQueuedAt, static function (array $a, array $b): int {
        $queuedCompare = ((int) ($b['queued_at'] ?? 0)) <=> ((int) ($a['queued_at'] ?? 0));
        if ($queuedCompare !== 0) {
            return $queuedCompare;
        }
        return ((int) ($b['last_activity'] ?? 0)) <=> ((int) ($a['last_activity'] ?? 0));
    });
    $alerts = array_values(array_filter($rowsByQueuedAt, static function (array $row): bool {
        return in_array((string) ($row['context'] ?? ''), ['post', 'podcast', 'itinerary'], true);
    }));
    $newsletters = array_values(array_filter($rowsByQueuedAt, static function (array $row): bool {
        return (string) ($row['context'] ?? '') === 'newsletter';
    }));
    return [
        'alerts' => $alerts[0] ?? null,
        'newsletter' => $newsletters[0] ?? null,
        'all' => $rowsByActivity,
    ];
}

function admin_mailing_pending_map_for_content(string $contentKey): array
{
    if (!function_exists('nammu_load_scheduled_notifications')) {
        return [];
    }
    $map = [];
    foreach (nammu_load_scheduled_notifications() as $payload) {
        if (!is_array($payload)) {
            continue;
        }
        if (strtolower(trim((string) ($payload['notification_kind'] ?? ''))) !== 'mailing_batch') {
            continue;
        }
        $existingContentKey = trim((string) ($payload['mailing_content_key'] ?? ''));
        if ($existingContentKey === '') {
            $existingContentKey = admin_mailing_content_key((string) ($payload['mailing_context'] ?? ''), $payload);
        }
        if ($existingContentKey !== $contentKey) {
            continue;
        }
        foreach ((array) ($payload['recipients'] ?? []) as $recipient) {
            $email = admin_normalize_email((string) $recipient);
            if ($email !== '') {
                $map[$email] = true;
            }
        }
    }
    return $map;
}

function admin_mark_mailing_delivered(string $context, array $payload, array $recipients): void
{
    $contentKey = trim((string) ($payload['mailing_content_key'] ?? ''));
    if ($contentKey === '') {
        $contentKey = admin_mailing_content_key($context, $payload);
    }
    $recipients = admin_normalize_recipient_batch($recipients);
    if ($contentKey === '' || empty($recipients)) {
        return;
    }
    $data = admin_load_mailing_deliveries();
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $campaigns = is_array($data['campaigns'] ?? null) ? $data['campaigns'] : [];
    $now = time();
    if (!isset($campaigns[$contentKey])) {
        $campaigns[$contentKey] = [
            'content_key' => $contentKey,
            'context' => strtolower(trim($context)),
            'slug' => trim((string) ($payload['slug'] ?? '')),
            'filename' => trim((string) ($payload['filename'] ?? '')),
            'title' => trim((string) ($payload['title'] ?? '')),
            'queued_recipients' => count($recipients),
            'queued_at' => $now,
        ];
    }
    foreach ($recipients as $email) {
        $items[admin_mailing_delivery_key($contentKey, $email)] = [
            'content_key' => $contentKey,
            'context' => strtolower(trim($context)),
            'email' => $email,
            'slug' => trim((string) ($payload['slug'] ?? '')),
            'filename' => trim((string) ($payload['filename'] ?? '')),
            'title' => trim((string) ($payload['title'] ?? '')),
            'sent_at' => $now,
        ];
    }
    $data['campaigns'] = $campaigns;
    $data['items'] = $items;
    admin_save_mailing_deliveries($data);
}

function admin_normalize_recipient_batch(array $recipients): array
{
    $suppressed = function_exists('admin_mailing_suppressed_map') ? admin_mailing_suppressed_map() : [];
    $normalized = [];
    foreach ($recipients as $recipient) {
        $email = admin_normalize_email((string) $recipient);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        if (isset($suppressed[$email])) {
            continue;
        }
        $normalized[$email] = true;
    }
    return array_keys($normalized);
}

function admin_enqueue_mailing_batches(string $context, array $payload, array $recipients): array
{
    if (!function_exists('nammu_enqueue_scheduled_notification')) {
        return ['queued_batches' => 0, 'queued_recipients' => 0];
    }
    $recipients = admin_normalize_recipient_batch($recipients);
    if (empty($recipients)) {
        return ['queued_batches' => 0, 'queued_recipients' => 0];
    }
    $contentKey = admin_mailing_content_key($context, $payload);
    if (admin_mailing_campaign_exists($contentKey)) {
        return ['queued_batches' => 0, 'queued_recipients' => 0, 'skipped_campaign' => true];
    }
    $alreadySent = admin_mailing_sent_map_for_content($contentKey);
    $alreadyPending = admin_mailing_pending_map_for_content($contentKey);
    $recipients = array_values(array_filter($recipients, static function ($email) use ($alreadySent, $alreadyPending): bool {
        return !isset($alreadySent[$email]) && !isset($alreadyPending[$email]);
    }));
    if (empty($recipients)) {
        return ['queued_batches' => 0, 'queued_recipients' => 0, 'skipped_recipients' => count($alreadySent) + count($alreadyPending)];
    }
    admin_mark_mailing_campaign_queued($context, $payload, $recipients);
    $batchSize = admin_mailing_batch_size();
    $chunks = array_chunk($recipients, $batchSize);
    $jobId = substr(sha1($context . '|' . $contentKey), 0, 20);
    foreach ($chunks as $index => $chunk) {
        nammu_enqueue_scheduled_notification([
            'notification_kind' => 'mailing_batch',
            'mailing_context' => $context,
            'mailing_content_key' => $contentKey,
            'mailing_job_id' => $jobId,
            'batch_index' => $index + 1,
            'batch_total' => count($chunks),
            'recipients' => array_values($chunk),
            'dedupe_key' => $jobId . ':' . ($index + 1),
        ] + $payload);
    }
    return ['queued_batches' => count($chunks), 'queued_recipients' => count($recipients)];
}

function admin_build_mailing_batch_delivery(string $context, array $payload): ?array
{
    $settings = get_settings();
    if (!admin_is_mailing_ready($settings)) {
        return null;
    }
    $recipients = admin_normalize_recipient_batch((array) ($payload['recipients'] ?? []));
    if (empty($recipients)) {
        return [
            'subject' => '',
            'mailingConfig' => $settings['mailing'] ?? [],
            'bodyBuilder' => null,
            'fromName' => null,
            'recipients' => [],
        ];
    }

    $title = trim((string) ($payload['title'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $image = trim((string) ($payload['image'] ?? ''));

    if ($context === 'newsletter') {
        $contentHtml = (string) ($payload['content_html'] ?? '');
        $contentText = (string) ($payload['content_text'] ?? '');
        $prepared = admin_prepare_newsletter_payload($settings, $title, $contentHtml, $contentText, $image);
    } else {
        $slug = trim((string) ($payload['slug'] ?? ''));
        $template = trim((string) ($payload['template'] ?? 'single'));
        if ($context === 'podcast') {
            $audio = trim((string) ($payload['audio'] ?? ''));
            $link = admin_public_asset_url($audio);
            if ($link === '') {
                return null;
            }
            $prepared = admin_prepare_mailing_payload('podcast', $settings, $title, $description, $link, $image);
        } elseif ($context === 'itinerary') {
            if ($slug === '') {
                return null;
            }
            $link = admin_public_itinerary_url($slug);
            $prepared = admin_prepare_mailing_payload('itinerario', $settings, $title, $description, $link, $image);
        } else {
            if ($slug === '') {
                return null;
            }
            $link = admin_public_post_url($slug);
            $prepared = admin_prepare_mailing_payload($template, $settings, $title, $description, $link, $image);
        }
    }

    return [
        'subject' => (string) ($prepared['subject'] ?? ''),
        'mailingConfig' => (array) ($prepared['mailingConfig'] ?? []),
        'bodyBuilder' => $prepared['bodyBuilder'] ?? null,
        'fromName' => isset($prepared['fromName']) ? (string) $prepared['fromName'] : null,
        'recipients' => $recipients,
    ];
}

function admin_try_send_queued_mailing_batch(array $payload): array
{
    $context = strtolower(trim((string) ($payload['mailing_context'] ?? '')));
    if (!in_array($context, ['post', 'podcast', 'itinerary', 'newsletter'], true)) {
        return ['ok' => true, 'sent' => 0, 'failed' => 0, 'failed_recipients' => []];
    }
    $delivery = admin_build_mailing_batch_delivery($context, $payload);
    if ($delivery === null) {
        return ['ok' => false, 'sent' => 0, 'failed' => 0, 'failed_recipients' => admin_normalize_recipient_batch((array) ($payload['recipients'] ?? []))];
    }
    $recipients = (array) ($delivery['recipients'] ?? []);
    if (empty($recipients)) {
        return ['ok' => true, 'sent' => 0, 'failed' => 0, 'failed_recipients' => []];
    }
    $contentKey = trim((string) ($payload['mailing_content_key'] ?? ''));
    if ($contentKey === '') {
        $contentKey = admin_mailing_content_key($context, $payload);
    }
    if ($contentKey !== '') {
        $alreadySent = admin_mailing_sent_map_for_content($contentKey);
        $recipients = array_values(array_filter(admin_normalize_recipient_batch($recipients), static function (string $email) use ($alreadySent): bool {
            return !isset($alreadySent[$email]);
        }));
    }
    if (empty($recipients)) {
        return ['ok' => true, 'sent' => 0, 'failed' => 0, 'failed_recipients' => []];
    }
    $result = admin_send_mailing_broadcast(
        (string) ($delivery['subject'] ?? ''),
        '',
        '',
        $recipients,
        (array) ($delivery['mailingConfig'] ?? []),
        is_callable($delivery['bodyBuilder'] ?? null) ? $delivery['bodyBuilder'] : null,
        isset($delivery['fromName']) ? (string) $delivery['fromName'] : null
    );
    $failedRecipients = admin_normalize_recipient_batch((array) ($result['failed_recipients'] ?? []));
    $deliveredRecipients = array_values(array_diff($recipients, $failedRecipients));
    if (!empty($deliveredRecipients)) {
        admin_mark_mailing_delivered($context, $payload, $deliveredRecipients);
    }
    admin_record_mailing_batch_attempt($context, $payload, $result, $recipients, $deliveredRecipients);
    return [
        'ok' => empty($failedRecipients),
        'sent' => (int) ($result['sent'] ?? 0),
        'failed' => (int) ($result['failed'] ?? 0),
        'failed_recipients' => $failedRecipients,
        'error' => $result['error'] ?? null,
    ];
}

function admin_schedule_mailing_broadcast(string $context, array $recipients, array $payload): array
{
    return admin_enqueue_mailing_batches($context, $payload, $recipients);
}

function admin_google_exchange_code(string $code, string $clientId, string $clientSecret, string $redirectUri): array {
    $postData = http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
        'access_type' => 'offline',
        'prompt' => 'consent',
    ]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 12,
        ],
    ];
    $context = stream_context_create($opts);
    $raw = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
    if ($raw === false) {
        throw new RuntimeException('No se pudo contactar con Google OAuth.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta inesperada al intercambiar el código de Google.');
    }
    if (isset($decoded['error'])) {
        $message = is_string($decoded['error']) ? $decoded['error'] : 'Error de OAuth';
        $desc = isset($decoded['error_description']) ? ' (' . $decoded['error_description'] . ')' : '';
        throw new RuntimeException($message . $desc);
    }
    if (empty($decoded['refresh_token'])) {
        throw new RuntimeException('Google no devolvió refresh_token. Vuelve a intentar con “Forzar consentimiento”.');
    }
    $now = time();
    $decoded['received_at'] = $now;
    if (isset($decoded['expires_in'])) {
        $decoded['expires_at'] = $now + (int) $decoded['expires_in'];
    }
    return $decoded;
}

function admin_add_utm_params(string $url, array $params): string {
    if ($url === '') {
        return $url;
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }
    $query = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    foreach ($params as $key => $value) {
        $key = (string) $key;
        $value = (string) $value;
        if ($key === '' || $value === '' || isset($query[$key])) {
            continue;
        }
        $query[$key] = $value;
    }
    $userInfo = '';
    if (!empty($parts['user'])) {
        $userInfo = $parts['user'];
        if (!empty($parts['pass'])) {
            $userInfo .= ':' . $parts['pass'];
        }
        $userInfo .= '@';
    }
    $base = '';
    if (!empty($parts['scheme'])) {
        $base .= $parts['scheme'] . '://';
    }
    if (!empty($parts['host'])) {
        $base .= $userInfo . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }
    }
    $base .= $parts['path'] ?? '';
    $queryString = http_build_query($query);
    $fragment = $parts['fragment'] ?? '';
    $rebuilt = $base;
    if ($queryString !== '') {
        $rebuilt .= '?' . $queryString;
    }
    if ($fragment !== '') {
        $rebuilt .= '#' . $fragment;
    }
    return $rebuilt !== '' ? $rebuilt : $url;
}

function admin_prepare_mailing_payload(string $template, array $settings, string $title, string $description, string $link, string $imagePath): array {
    $mailingConfig = $settings['mailing'] ?? [];
    $format = $mailingConfig['format'] ?? 'html';
    $isHtml = $format !== 'text';
    $subject = $title;
    $blogName = $settings['site_name'] ?? 'Tu blog';
    $authorName = trim((string) ($settings['site_author'] ?? ''));
    $siteBase = rtrim($settings['site_url'] ?? '', '/');
    $baseForAssets = $siteBase !== '' ? $siteBase : rtrim(admin_base_url(), '/');
    $imageUrl = '';
    if ($imagePath !== '') {
        if (preg_match('#^https?://#i', $imagePath)) {
            $imageUrl = $imagePath;
        } else {
            $normalizedImage = ltrim($imagePath, '/');
            $normalizedImage = str_replace(['../', '..\\', './', '.\\'], '', $normalizedImage);
            $candidates = [];
            $candidates[] = $normalizedImage;
            if (!str_starts_with($normalizedImage, 'assets/')) {
                $candidates[] = 'assets/' . $normalizedImage;
            }
            foreach ($candidates as $cand) {
                $local = NAMMU_ROOT . '/' . $cand;
                if (is_file($local) || is_file(NAMMU_ROOT . '/' . ltrim($cand, '/'))) {
                    $imageUrl = $baseForAssets . '/' . $cand;
                    break;
                }
            }
            if ($imageUrl === '' && !empty($candidates)) {
                $imageUrl = $baseForAssets . '/' . $candidates[0];
            }
        }
    }
    $logoPath = $settings['template']['images']['logo'] ?? '';
    $logoUrl = '';
    if ($logoPath !== '') {
        if (preg_match('#^https?://#i', $logoPath)) {
            $logoUrl = $logoPath;
        } else {
            $normalizedLogo = ltrim($logoPath, '/');
            $normalizedLogo = str_replace(['../', '..\\', './', '.\\'], '', $normalizedLogo);
            $logoUrl = $baseForAssets . '/' . $normalizedLogo;
        }
    }
    $colors = $settings['template']['colors'] ?? [];
    $colorBackground = $colors['background'] ?? '#ffffff';
    $colorText = $colors['text'] ?? '#222222';
    $colorHighlight = $colors['highlight'] ?? '#f3f6f9';
    $colorAccent = $colors['accent'] ?? '#0a4c8a';
    $colorH1 = $colors['h1'] ?? $colorAccent;
    $colorH2 = $colors['h2'] ?? $colorText;
    $headerBg = $colorH1;
    $ctaColor = $colorH1;
    $outerBg = $colorHighlight;
    $cardBg = $colorBackground;
    $footerBg = $colorHighlight;
    $border = $colorAccent;
    $headerText = admin_pick_contrast_color($headerBg, '#ffffff', '#111111');
    $ctaText = admin_pick_contrast_color($ctaColor, '#ffffff', '#111111');
    $footerText = admin_pick_contrast_color($footerBg, '#ffffff', $colorText);
    $titleFont = $settings['template']['fonts']['title'] ?? 'Arial';
    $bodyFont = $settings['template']['fonts']['body'] ?? 'Arial';
    $fontsUrl = '';
    $fontFamilies = [];
    foreach ([$titleFont, $bodyFont] as $fontCandidate) {
        $clean = trim((string) $fontCandidate);
        if ($clean !== '') {
            $fontFamilies[] = str_replace(' ', '+', $clean) . ':wght@400;600;700';
        }
    }
    if (!empty($fontFamilies)) {
        $fontsUrl = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', array_unique($fontFamilies)) . '&display=swap';
    }
    $titleFontCss = htmlspecialchars($titleFont, ENT_QUOTES, 'UTF-8');
    $bodyFontCss = htmlspecialchars($bodyFont, ENT_QUOTES, 'UTF-8');
    $ctaLabel = $template === 'itinerario'
        ? 'Comienza este itinerario'
        : ($template === 'page'
            ? 'Ver esta página'
            : ($template === 'podcast' ? 'Escuchar el episodio' : 'Sigue leyendo'));
    $ctaText = $ctaLabel;
    $fromName = $authorName !== '' ? $authorName : $blogName;

    $trackedLink = admin_add_utm_params($link, [
        'utm_source' => 'email',
        'utm_medium' => 'avisos',
        'utm_campaign' => $template,
    ]);
    $buildText = function (string $recipientEmail) use ($authorName, $blogName, $title, $description, $trackedLink, $ctaText) {
        $lines = [];
        $lines[] = '**** ' . ($authorName !== '' ? $authorName : $blogName) . ' ****';
        $lines[] = '**** ' . $blogName . ' ****';
        $lines[] = '';
        $lines[] = '== ' . $title . ' ==';
        $lines[] = '';
        if ($description !== '') {
            $lines[] = $description;
            $lines[] = '';
        }
        $lines[] = $ctaText . ': ' . $trackedLink;
        $lines[] = '';
        $lines[] = '-----------';
        $lines[] = 'Recibes este email porque estás suscrito a las comunicaciones de ' . $blogName . '. Puedes darte de baja pulsando aquí: ' . admin_mailing_unsubscribe_link($recipientEmail);
        return implode("\n", $lines);
    };

    $buildHtml = function (string $recipientEmail) use ($authorName, $blogName, $title, $description, $trackedLink, $imageUrl, $logoUrl, $headerBg, $headerText, $ctaColor, $ctaText, $outerBg, $cardBg, $colorText, $colorH2, $footerBg, $footerText, $border, $ctaLabel, $fontsUrl, $titleFontCss, $bodyFontCss) {
        $safeUnsub = htmlspecialchars(admin_mailing_unsubscribe_link($recipientEmail), ENT_QUOTES, 'UTF-8');
        $html = [];
        if ($fontsUrl !== '') {
            $html[] = '<link rel="stylesheet" href="' . htmlspecialchars($fontsUrl, ENT_QUOTES, 'UTF-8') . '">';
        }
        $html[] = '<style>h1,h2,h3,h4,h5,h6{font-family:' . $titleFontCss . ', Arial, sans-serif;} body,p,a,div,span{font-family:' . $bodyFontCss . ', Arial, sans-serif;}</style>';
        $html[] = '<div style="font-family:' . $bodyFontCss . ', Arial, sans-serif; background:' . htmlspecialchars($outerBg, ENT_QUOTES, 'UTF-8') . '; padding:24px; color:' . htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') . ';">';
        $html[] = '  <div style="max-width:720px; margin:0 auto; background:' . htmlspecialchars($cardBg, ENT_QUOTES, 'UTF-8') . '; border:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33; border-radius:12px; overflow:hidden;">';
        $html[] = '    <div style="background:' . htmlspecialchars($headerBg, ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($headerText, ENT_QUOTES, 'UTF-8') . '; padding:18px 22px; text-align:center;">';
        if ($logoUrl !== '') {
            $html[] = '      <div style="margin-bottom:10px;"><img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:64px; height:64px; object-fit:cover; border-radius:50%; box-shadow:0 4px 12px rgba(0,0,0,0.15); background:' . htmlspecialchars($cardBg, ENT_QUOTES, 'UTF-8') . ';"></div>';
        }
        $html[] = '      <div style="font-size:14px; opacity:0.9; margin-bottom:4px;">' . htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '      <div style="font-size:20px; font-weight:700;">' . htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '    </div>';
        $html[] = '    <div style="padding:22px;">';
        $html[] = '      <h2 style="margin:0 0 20px 0; font-size:38px; line-height:1.15; color:' . htmlspecialchars($colorH2, ENT_QUOTES, 'UTF-8') . '; font-family:' . $titleFontCss . ', Arial, sans-serif;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
        if ($imageUrl !== '') {
            $html[] = '      <div style="margin:0 0 14px 0;"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:100%; display:block; border-radius:12px; border:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33;"></div>';
        }
        if ($description !== '') {
            $html[] = '      <p style="margin:0 0 20px 0; line-height:1.75; font-size:20px; color:' . htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') . '; font-family:' . $bodyFontCss . ', Arial, sans-serif;">' . nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) . '</p>';
        }
        $html[] = '      <p style="margin:0 0 16px 0;">';
        $html[] = '        <a href="' . htmlspecialchars($trackedLink, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; background:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8') . '; padding:14px 18px; border-radius:10px; text-decoration:none; font-weight:600;">' . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') . '</a>';
        $html[] = '      </p>';
        $html[] = '    </div>';
        $html[] = '    <div style="padding:16px 22px; background:' . htmlspecialchars($footerBg, ENT_QUOTES, 'UTF-8') . '; border-top:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33; font-size:13px; color:' . htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') . '; opacity:0.8;">';
        $html[] = '      <p style="margin:0 0 6px 0;">Recibes este email porque estás suscrito a las comunicaciones de ' . htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') . '.</p>';
        $html[] = '      <p style="margin:0;"><a href="' . $safeUnsub . '" style="color:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . ';">Puedes darte de baja pulsando aquí</a>.</p>';
        $html[] = '    </div>';
        $html[] = '  </div>';
        $html[] = '</div>';
        return implode('', $html);
    };

    $bodyBuilder = function (string $recipientEmail) use ($isHtml, $buildText, $buildHtml) {
        if ($isHtml) {
            $text = $buildText($recipientEmail);
            $html = $buildHtml($recipientEmail);
            return [$text, $html];
        }
        $text = $buildText($recipientEmail);
        $html = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        return [$text, $html];
    };

    return [
        'subject' => $subject,
        'bodyBuilder' => $bodyBuilder,
        'fromName' => $fromName,
        'mailingConfig' => $mailingConfig,
    ];
}

function admin_prepare_newsletter_payload(array $settings, string $title, string $contentHtml, string $contentText, string $imagePath): array {
    $mailingConfig = $settings['mailing'] ?? [];
    $format = $mailingConfig['format'] ?? 'html';
    $isHtml = $format !== 'text';
    $subject = $title;
    $blogName = $settings['site_name'] ?? 'Tu blog';
    $authorName = trim((string) ($settings['site_author'] ?? ''));
    $siteBase = rtrim($settings['site_url'] ?? '', '/');
    $baseForAssets = $siteBase !== '' ? $siteBase : rtrim(admin_base_url(), '/');
    $link = $siteBase !== '' ? $siteBase : rtrim(admin_base_url(), '/');
    $contentHtml = admin_newsletter_expand_image_urls($contentHtml, $baseForAssets);
    $imageUrl = '';
    if ($imagePath !== '') {
        if (preg_match('#^https?://#i', $imagePath)) {
            $imageUrl = $imagePath;
        } else {
            $normalizedImage = ltrim($imagePath, '/');
            $normalizedImage = str_replace(['../', '..\\', './', '.\\'], '', $normalizedImage);
            $candidates = [];
            $candidates[] = $normalizedImage;
            if (!str_starts_with($normalizedImage, 'assets/')) {
                $candidates[] = 'assets/' . $normalizedImage;
            }
            foreach ($candidates as $cand) {
                $local = NAMMU_ROOT . '/' . $cand;
                if (is_file($local) || is_file(NAMMU_ROOT . '/' . ltrim($cand, '/'))) {
                    $imageUrl = $baseForAssets . '/' . $cand;
                    break;
                }
            }
            if ($imageUrl === '' && !empty($candidates)) {
                $imageUrl = $baseForAssets . '/' . $candidates[0];
            }
        }
    }
    $logoPath = $settings['template']['images']['logo'] ?? '';
    $logoUrl = '';
    if ($logoPath !== '') {
        if (preg_match('#^https?://#i', $logoPath)) {
            $logoUrl = $logoPath;
        } else {
            $normalizedLogo = ltrim($logoPath, '/');
            $normalizedLogo = str_replace(['../', '..\\', './', '.\\'], '', $normalizedLogo);
            $logoUrl = $baseForAssets . '/' . $normalizedLogo;
        }
    }
    $colors = $settings['template']['colors'] ?? [];
    $colorBackground = $colors['background'] ?? '#ffffff';
    $colorText = $colors['text'] ?? '#222222';
    $colorHighlight = $colors['highlight'] ?? '#f3f6f9';
    $colorAccent = $colors['accent'] ?? '#0a4c8a';
    $colorH1 = $colors['h1'] ?? $colorAccent;
    $colorH2 = $colors['h2'] ?? $colorText;
    $headerBg = $colorH1;
    $ctaColor = $colorH1;
    $outerBg = $colorHighlight;
    $cardBg = $colorBackground;
    $footerBg = $colorHighlight;
    $border = $colorAccent;
    $headerText = admin_pick_contrast_color($headerBg, '#ffffff', '#111111');
    $ctaText = admin_pick_contrast_color($ctaColor, '#ffffff', '#111111');
    $footerText = admin_pick_contrast_color($footerBg, '#ffffff', $colorText);
    $titleFont = $settings['template']['fonts']['title'] ?? 'Arial';
    $bodyFont = $settings['template']['fonts']['body'] ?? 'Arial';
    $fontsUrl = '';
    $fontFamilies = [];
    foreach ([$titleFont, $bodyFont] as $fontCandidate) {
        $clean = trim((string) $fontCandidate);
        if ($clean !== '') {
            $fontFamilies[] = str_replace(' ', '+', $clean) . ':wght@400;600;700';
        }
    }
    if (!empty($fontFamilies)) {
        $fontsUrl = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', array_unique($fontFamilies)) . '&display=swap';
    }
    $titleFontCss = htmlspecialchars($titleFont, ENT_QUOTES, 'UTF-8');
    $bodyFontCss = htmlspecialchars($bodyFont, ENT_QUOTES, 'UTF-8');
    $fromName = $authorName !== '' ? $authorName : $blogName;

    $buildText = function (string $recipientEmail) use ($authorName, $blogName, $title, $contentText, $link) {
        $lines = [];
        $lines[] = '**** ' . ($authorName !== '' ? $authorName : $blogName) . ' ****';
        $lines[] = '**** ' . $blogName . ' ****';
        $lines[] = '';
        $lines[] = '== ' . $title . ' ==';
        $lines[] = '';
        if ($contentText !== '') {
            $lines[] = $contentText;
            $lines[] = '';
        }
        if ($link !== '') {
            $lines[] = 'Visita el sitio: ' . $link;
            $lines[] = '';
        }
        $lines[] = '-----------';
        $lines[] = 'Recibes este email porque estás suscrito a las comunicaciones de ' . $blogName . '. Puedes darte de baja pulsando aquí: ' . admin_mailing_unsubscribe_link($recipientEmail);
        return implode("\n", $lines);
    };

    $buildHtml = function (string $recipientEmail) use ($authorName, $blogName, $title, $contentHtml, $link, $imageUrl, $logoUrl, $headerBg, $headerText, $ctaColor, $ctaText, $outerBg, $cardBg, $colorText, $colorH2, $footerBg, $footerText, $border, $fontsUrl, $titleFontCss, $bodyFontCss) {
        $safeUnsub = htmlspecialchars(admin_mailing_unsubscribe_link($recipientEmail), ENT_QUOTES, 'UTF-8');
        $html = [];
        if ($fontsUrl !== '') {
            $html[] = '<link rel="stylesheet" href="' . htmlspecialchars($fontsUrl, ENT_QUOTES, 'UTF-8') . '">';
        }
        $html[] = '<style>h1,h2,h3,h4,h5,h6{font-family:' . $titleFontCss . ', Arial, sans-serif;} body,p,a,div,span{font-family:' . $bodyFontCss . ', Arial, sans-serif;} a{color:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . ';}</style>';
        $html[] = '<div style="font-family:' . $bodyFontCss . ', Arial, sans-serif; background:' . htmlspecialchars($outerBg, ENT_QUOTES, 'UTF-8') . '; padding:24px; color:' . htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') . ';">';
        $html[] = '  <div style="max-width:720px; margin:0 auto; background:' . htmlspecialchars($cardBg, ENT_QUOTES, 'UTF-8') . '; border:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33; border-radius:12px; overflow:hidden;">';
        $html[] = '    <div style="background:' . htmlspecialchars($headerBg, ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($headerText, ENT_QUOTES, 'UTF-8') . '; padding:18px 22px; text-align:center;">';
        if ($logoUrl !== '') {
            $html[] = '      <div style="margin-bottom:10px;"><img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:64px; height:64px; object-fit:cover; border-radius:50%; box-shadow:0 4px 12px rgba(0,0,0,0.15); background:' . htmlspecialchars($cardBg, ENT_QUOTES, 'UTF-8') . ';"></div>';
        }
        $html[] = '      <div style="font-size:14px; opacity:0.9; margin-bottom:4px;">' . htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '      <div style="font-size:20px; font-weight:700;">' . htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '    </div>';
        $html[] = '    <div style="padding:22px;">';
        $html[] = '      <h2 style="margin:0 0 20px 0; font-size:32px; line-height:1.15; color:' . htmlspecialchars($colorH2, ENT_QUOTES, 'UTF-8') . '; font-family:' . $titleFontCss . ', Arial, sans-serif;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
        if ($imageUrl !== '') {
            $html[] = '      <div style="margin:0 0 14px 0;"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:100%; display:block; border-radius:12px; border:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33;"></div>';
        }
        if ($contentHtml !== '') {
            $html[] = '      <div style="margin:0; line-height:1.75; font-size:18px; color:' . htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') . '; font-family:' . $bodyFontCss . ', Arial, sans-serif;">' . $contentHtml . '</div>';
        }
        if ($link !== '') {
            $html[] = '      <p style="margin:20px 0 0 0;">';
            $html[] = '        <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; background:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8') . '; padding:14px 18px; border-radius:10px; text-decoration:none; font-weight:600;">Visitar el sitio</a>';
            $html[] = '      </p>';
        }
        $html[] = '    </div>';
        $html[] = '    <div style="padding:16px 22px; background:' . htmlspecialchars($footerBg, ENT_QUOTES, 'UTF-8') . '; border-top:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33; font-size:13px; color:' . htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') . '; opacity:0.8;">';
        $html[] = '      <p style="margin:0 0 6px 0;">Recibes este email porque estás suscrito a las comunicaciones de ' . htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') . '.</p>';
        $html[] = '      <p style="margin:0;"><a href="' . $safeUnsub . '" style="color:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . ';">Puedes darte de baja pulsando aquí</a>.</p>';
        $html[] = '    </div>';
        $html[] = '  </div>';
        $html[] = '</div>';
        return implode('', $html);
    };

    $bodyBuilder = function (string $recipientEmail) use ($isHtml, $buildText, $buildHtml) {
        if ($isHtml) {
            $text = $buildText($recipientEmail);
            $html = $buildHtml($recipientEmail);
            return [$text, $html];
        }
        $text = $buildText($recipientEmail);
        $html = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        return [$text, $html];
    };

    return [
        'subject' => $subject,
        'bodyBuilder' => $bodyBuilder,
        'fromName' => $fromName,
        'mailingConfig' => $mailingConfig,
    ];
}

function admin_newsletter_expand_image_urls(string $html, string $baseUrl): string {
    if ($html === '' || $baseUrl === '') {
        return $html;
    }
    $baseUrl = rtrim($baseUrl, '/');
    $singleAttrCallback = static function (array $matches) use ($baseUrl): string {
        $prefix = $matches[1] ?? '';
        $quote = $matches[2] ?? '"';
        $value = $matches[3] ?? '';
        $suffix = $matches[4] ?? '';
        $normalized = trim($value);
        if ($normalized === '' || preg_match('#^(https?:)?//#i', $normalized) || str_starts_with($normalized, 'data:') || str_starts_with($normalized, 'mailto:') || str_starts_with($normalized, 'tel:')) {
            return $matches[0];
        }
        $normalized = ltrim($normalized, '/');
        return $prefix . $quote . $baseUrl . '/' . $normalized . $quote . $suffix;
    };
    $srcsetCallback = static function (array $matches) use ($baseUrl): string {
        $prefix = $matches[1] ?? '';
        $quote = $matches[2] ?? '"';
        $value = $matches[3] ?? '';
        $suffix = $matches[4] ?? '';
        $parts = array_filter(array_map('trim', explode(',', $value)));
        if (empty($parts)) {
            return $matches[0];
        }
        $rebuilt = [];
        foreach ($parts as $part) {
            $segments = preg_split('/\\s+/', $part, 2);
            $urlPart = $segments[0] ?? '';
            $descriptor = $segments[1] ?? '';
            $normalized = trim($urlPart);
            if ($normalized !== '' && !preg_match('#^(https?:)?//#i', $normalized) && !str_starts_with($normalized, 'data:')) {
                $normalized = ltrim($normalized, '/');
                $normalized = $baseUrl . '/' . $normalized;
            }
            $rebuilt[] = trim($normalized . ($descriptor !== '' ? ' ' . $descriptor : ''));
        }
        return $prefix . $quote . implode(', ', $rebuilt) . $quote . $suffix;
    };
    $html = preg_replace_callback('/(<img\\b[^>]*\\bsrc\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<a\\b[^>]*\\bhref\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<img\\b[^>]*\\bsrcset\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $srcsetCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<source\\b[^>]*\\bsrc\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<source\\b[^>]*\\bsrcset\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $srcsetCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<video\\b[^>]*\\bposter\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<audio\\b[^>]*\\bsrc\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    $html = preg_replace_callback('/(<video\\b[^>]*\\bsrc\\s*=\\s*)([\"\'])([^\"\']+)(\\2[^>]*>)/i', $singleAttrCallback, $html) ?? $html;
    return $html;
}

function admin_gmail_update_display_name(string $sendAsEmail, string $displayName, string $accessToken): void {
    $sendAsEmail = trim($sendAsEmail);
    $displayName = trim($displayName);
    if ($sendAsEmail === '' || $displayName === '') {
        return;
    }
    $payload = json_encode([
        'displayName' => $displayName,
        'replyToAddress' => $sendAsEmail,
        'treatAsAlias' => false,
    ]);
    if ($payload === false) {
        return;
    }
    $targets = [$sendAsEmail, 'me'];
    foreach ($targets as $target) {
        $url = 'https://gmail.googleapis.com/gmail/v1/users/me/settings/sendAs/' . rawurlencode($target);
        $opts = [
            'http' => [
                'method' => 'PATCH',
                'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        $statusLine = nammu_last_response_headers($http_response_header ?? null)[0] ?? '';
        if ($result !== false && str_contains($statusLine, '200')) {
            break;
        }
    }
}
