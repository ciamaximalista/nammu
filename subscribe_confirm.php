<?php
declare(strict_types=1);

define('MAILING_SUBSCRIBERS_FILE', __DIR__ . '/config/mailing-subscribers.json');
define('MAILING_PENDING_FILE', __DIR__ . '/config/mailing-pending.json');

function subscription_normalize_email(string $email): string {
    return strtolower(trim($email));
}

function subscription_load(array $fileDef): array {
    [$file, $default] = $fileDef;
    if (!is_file($file)) {
        return $default;
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function subscription_save(string $file, array $data): void {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar los datos');
    }
    file_put_contents($file, $payload, LOCK_EX);
    @chmod($file, 0664);
}

function subscription_redirect(string $to, array $params = []): void {
    $separator = str_contains($to, '?') ? '&' : '?';
    if (!empty($params)) {
        $to .= $separator . http_build_query($params);
    }
    header('Location: ' . $to);
    exit;
}

$referer = $_SERVER['HTTP_REFERER'] ?? '/';
$back = filter_var($referer, FILTER_VALIDATE_URL) ? $referer : '/';

$email = subscription_normalize_email($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');

if ($email === '' || $token === '') {
    subscription_redirect($back, ['sub_error' => 1]);
}

$pending = subscription_load([MAILING_PENDING_FILE, []]);
$matchIndex = null;
foreach ($pending as $idx => $entry) {
    if (!is_array($entry)) {
        continue;
    }
    if (($entry['email'] ?? '') === $email && ($entry['token'] ?? '') === $token) {
        $matchIndex = $idx;
        break;
    }
}

if ($matchIndex === null) {
    subscription_redirect($back, ['sub_error' => 1]);
}

unset($pending[$matchIndex]);
$pending = array_values($pending);

$subscribers = subscription_load([MAILING_SUBSCRIBERS_FILE, []]);
if (!in_array($email, $subscribers, true)) {
    $subscribers[] = $email;
}

try {
    subscription_save(MAILING_PENDING_FILE, $pending);
    subscription_save(MAILING_SUBSCRIBERS_FILE, $subscribers);
} catch (Throwable $e) {
    subscription_redirect($back, ['sub_error' => 1]);
}

subscription_redirect($back, ['subscribed' => 1]);
