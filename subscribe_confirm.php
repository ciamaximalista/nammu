<?php
declare(strict_types=1);

require_once __DIR__ . '/core/helpers.php';

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

$email = subscription_normalize_email($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');

// Estado de error o éxito
$showError = false;
$errorMessage = '';

if ($email === '' || $token === '') {
    $showError = true;
    $errorMessage = 'Solicitud inválida. Falta email o token.';
} else {
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
        $showError = true;
        $errorMessage = 'Este enlace de confirmación no es válido o ya se utilizó.';
    } else {
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
            $showError = true;
            $errorMessage = 'No pudimos confirmar tu suscripción. Inténtalo de nuevo.';
        }
    }
}

// Página de éxito con cabecera sencilla usando datos del blog
$config = nammu_load_config();
$siteTitle = isset($config['site_name']) && is_string($config['site_name']) ? trim($config['site_name']) : 'el blog';
$siteAuthor = isset($config['site_author']) && is_string($config['site_author']) ? trim($config['site_author']) : '';
$siteUrl = isset($config['site_url']) && is_string($config['site_url']) ? trim($config['site_url']) : '';
if ($siteUrl === '') {
    $siteUrl = nammu_base_url();
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suscripción realizada con éxito</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2933;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }
        .confirm-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 28px 32px;
            max-width: 620px;
            width: 100%;
            box-shadow: 0 12px 40px rgba(0,0,0,0.08);
        }
        .confirm-title {
            margin: 0 0 12px 0;
            font-size: 22px;
            color: #111827;
        }
        .confirm-text {
            margin: 0 0 8px 0;
            line-height: 1.6;
        }
        .confirm-link {
            display: inline-block;
            margin-top: 14px;
            color: #1b8eed;
            text-decoration: none;
            font-weight: 600;
        }
        .confirm-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="confirm-card">
        <?php if ($showError): ?>
            <h1 class="confirm-title">No pudimos confirmar</h1>
            <p class="confirm-text"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($siteUrl !== ''): ?>
                <a class="confirm-link" href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al sitio</a>
            <?php endif; ?>
        <?php else: ?>
            <h1 class="confirm-title">Suscripción realizada con éxito</h1>
            <p class="confirm-text">A partir de ahora recibirás los avisos de publicación de <?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?>.</p>
            <?php if ($siteAuthor !== ''): ?>
                <p class="confirm-text">Enviado por: <?= htmlspecialchars($siteAuthor, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if ($siteUrl !== ''): ?>
                <a class="confirm-link" href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al sitio</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
