<?php
declare(strict_types=1);

define('MAILING_SUBSCRIBERS_FILE', __DIR__ . '/config/mailing-subscribers.json');
define('MAILING_PENDING_FILE', __DIR__ . '/config/mailing-pending.json');

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

function subscription_load_pending(): array {
    if (!is_file(MAILING_PENDING_FILE)) {
        return [];
    }
    $raw = file_get_contents(MAILING_PENDING_FILE);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function subscription_save_pending(array $pending): void {
    $dir = dirname(MAILING_PENDING_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $payload = json_encode(array_values($pending), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo preparar la lista de pendientes.');
    }
    file_put_contents(MAILING_PENDING_FILE, $payload, LOCK_EX);
    @chmod(MAILING_PENDING_FILE, 0664);
}

function subscription_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function subscription_send_confirmation(string $email, string $token, string $siteTitle): void {
    $base = subscription_base_url();
    $link = rtrim($base, '/') . '/subscribe_confirm.php?email=' . urlencode($email) . '&token=' . urlencode($token);
    $subject = 'Confirma tu suscripción a ' . ($siteTitle !== '' ? $siteTitle : 'nuestro sitio');
    $bodyLines = [
        "Hola,",
        "",
        "Confirma tu suscripción haciendo clic en el enlace:",
        $link,
        "",
        "Si no solicitaste esta suscripción, ignora este mensaje.",
    ];
    $body = implode("\n", $bodyLines);
    $headers = [];
    $fromName = $siteTitle !== '' ? $siteTitle : 'Nammu';
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers[] = 'From: ' . $encodedName . ' <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    @mail($email, $subject, $body, implode("\r\n", $headers));
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
$postedBack = $_POST['back'] ?? '';
$backRaw = $postedBack !== '' ? $postedBack : $referer;
$back = filter_var($backRaw, FILTER_VALIDATE_URL) ? $backRaw : '/';
// Evita redirecciones externas
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
$parsedBack = parse_url($back);
if (!empty($parsedBack['host']) && $currentHost !== '' && $parsedBack['host'] !== $currentHost) {
    $back = '/';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    subscription_redirect($back, ['sub_error' => 1]);
}

$email = subscription_normalize_email($_POST['subscriber_email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    subscription_redirect($back, ['sub_error' => 1]);
}

$pending = subscription_load_pending();
$token = bin2hex(random_bytes(16));
$siteTitle = '';
$configFile = __DIR__ . '/config/config.yml';
if (is_file($configFile)) {
    $yaml = file_get_contents($configFile);
    if ($yaml !== false && $yaml !== '') {
        if (function_exists('yaml_parse')) {
            $parsed = @yaml_parse($yaml);
        } else {
            $parsed = null;
        }
        if (is_array($parsed) && isset($parsed['site_name']) && is_string($parsed['site_name'])) {
            $siteTitle = trim($parsed['site_name']);
        }
    }
}
$pendingEntry = [
    'email' => $email,
    'token' => $token,
    'created_at' => time(),
];
$pending = array_values(array_filter($pending, static function ($item) use ($email) {
    return !is_array($item) || ($item['email'] ?? '') !== $email;
}));
$pending[] = $pendingEntry;
try {
    subscription_save_pending($pending);
    subscription_send_confirmation($email, $token, $siteTitle);
} catch (Throwable $e) {
    subscription_redirect($back, ['sub_error' => 1]);
}

subscription_redirect($back, ['sub_sent' => 1]);
