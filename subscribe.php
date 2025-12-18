<?php
declare(strict_types=1);

define('MAILING_SUBSCRIBERS_FILE', __DIR__ . '/config/mailing-subscribers.json');

function subscription_normalize_email(string $email): string {
    return strtolower(trim($email));
}

function subscription_load_subscribers(): array {
    if (!is_file(MAILING_SUBSCRIBERS_FILE)) {
        return [];
    }
    $raw = file_get_contents(MAILING_SUBSCRIBERS_FILE);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function subscription_save_subscribers(array $list): void {
    $dir = dirname(MAILING_SUBSCRIBERS_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $payload = json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo preparar la lista de suscriptores.');
    }
    file_put_contents(MAILING_SUBSCRIBERS_FILE, $payload, LOCK_EX);
    @chmod(MAILING_SUBSCRIBERS_FILE, 0664);
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    subscription_redirect($back, ['sub_error' => 1]);
}

$email = subscription_normalize_email($_POST['subscriber_email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    subscription_redirect($back, ['sub_error' => 1]);
}

$list = subscription_load_subscribers();
if (!in_array($email, $list, true)) {
    $list[] = $email;
    try {
        subscription_save_subscribers($list);
    } catch (Throwable $e) {
        subscription_redirect($back, ['sub_error' => 1]);
    }
}

subscription_redirect($back, ['subscribed' => 1]);
