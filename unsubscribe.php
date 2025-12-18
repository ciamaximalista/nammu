<?php

declare(strict_types=1);

define('MAILING_SUBSCRIBERS_FILE', __DIR__ . '/config/mailing-subscribers.json');
define('MAILING_SECRET_FILE', __DIR__ . '/config/mailing-secret.key');
define('CONFIG_FILE', __DIR__ . '/config/config.yml');

function mailing_normalize_email(string $email): string {
    return strtolower(trim($email));
}

function mailing_secret(): string {
    if (!is_file(MAILING_SECRET_FILE)) {
        $dir = dirname(MAILING_SECRET_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $secret = bin2hex(random_bytes(32));
        file_put_contents(MAILING_SECRET_FILE, $secret);
        @chmod(MAILING_SECRET_FILE, 0640);
        return $secret;
    }
    $content = trim((string) file_get_contents(MAILING_SECRET_FILE));
    if ($content === '') {
        $content = bin2hex(random_bytes(32));
        file_put_contents(MAILING_SECRET_FILE, $content);
    }
    return $content;
}

function mailing_unsubscribe_token(string $email): string {
    return hash_hmac('sha256', mailing_normalize_email($email), mailing_secret());
}

function mailing_load_subscribers(): array {
    if (!is_file(MAILING_SUBSCRIBERS_FILE)) {
        return [];
    }
    $raw = file_get_contents(MAILING_SUBSCRIBERS_FILE);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $unique = [];
    foreach ($decoded as $email) {
        $norm = mailing_normalize_email((string) $email);
        if ($norm !== '' && filter_var($norm, FILTER_VALIDATE_EMAIL)) {
            $unique[$norm] = true;
        }
    }
    return array_keys($unique);
}

function mailing_save_subscribers(array $subscribers): void {
    $unique = [];
    foreach ($subscribers as $email) {
        $norm = mailing_normalize_email((string) $email);
        if ($norm !== '' && filter_var($norm, FILTER_VALIDATE_EMAIL)) {
            $unique[$norm] = true;
        }
    }
    $payload = json_encode(array_keys($unique), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar la lista.');
    }
    $dir = dirname(MAILING_SUBSCRIBERS_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    file_put_contents(MAILING_SUBSCRIBERS_FILE, $payload, LOCK_EX);
    @chmod(MAILING_SUBSCRIBERS_FILE, 0664);
}

function mailing_site_name(): string {
    if (!is_file(CONFIG_FILE)) {
        return 'este sitio';
    }
    $raw = file_get_contents(CONFIG_FILE);
    if ($raw === false || $raw === '') {
        return 'este sitio';
    }
    if (preg_match('/^site_name:\s*[\'"]?([^\r\n\'"]+)/mi', $raw, $m)) {
        return trim($m[1]);
    }
    return 'este sitio';
}

$email = mailing_normalize_email($_GET['email'] ?? '');
$token = $_GET['token'] ?? '';
$valid = $email !== '' && $token !== '' && hash_equals(mailing_unsubscribe_token($email), $token);
$removed = false;
$error = null;
$blogName = mailing_site_name();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsubscribe_confirm'])) {
    $emailPost = mailing_normalize_email($_POST['email'] ?? '');
    $tokenPost = $_POST['token'] ?? '';
    if ($emailPost === '' || $tokenPost === '' || !hash_equals(mailing_unsubscribe_token($emailPost), $tokenPost)) {
        $valid = false;
        $error = 'El enlace de baja no es válido o ha caducado.';
    } else {
        try {
            $list = mailing_load_subscribers();
            $filtered = array_values(array_filter($list, static function ($item) use ($emailPost) {
                return mailing_normalize_email((string) $item) !== $emailPost;
            }));
            mailing_save_subscribers($filtered);
            $removed = true;
            $valid = true;
        } catch (Throwable $e) {
            $error = 'No se pudo procesar la baja. Inténtalo de nuevo.';
        }
    }
}

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Baja de suscripción</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fb; color: #1f2933; margin: 0; padding: 0; }
        .wrap { max-width: 720px; margin: 40px auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .header { background: #1b8eed; color: #fff; padding: 16px 20px; }
        .content { padding: 22px; }
        .footer { padding: 14px 20px; background: #f9fafb; border-top: 1px solid #e5e7eb; font-size: 13px; color: #4b5563; }
        .btn { display: inline-block; background: #d11a2a; color: #fff; padding: 12px 18px; border-radius: 10px; text-decoration: none; font-weight: 700; border: none; cursor: pointer; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 12px; }
        .alert.success { background: #e6f4ea; color: #1e7b34; border: 1px solid #cde6d5; }
        .alert.error { background: #fdecea; color: #b3261e; border: 1px solid #f9d3ce; }
        .alert.info { background: #e8f1fb; color: #1b4f9c; border: 1px solid #d4e3f7; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="header">
            <div style="font-size:14px; opacity:0.9;">Suscripciones</div>
            <div style="font-size:20px; font-weight:700;"><?= htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="content">
            <?php if ($error !== null): ?>
                <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($removed): ?>
                <div class="alert success">Hemos dado de baja <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></strong> de los avisos de <?= htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') ?>.</div>
                <p>Gracias por haber estado suscrito.</p>
            <?php elseif ($valid): ?>
                <p>Vas a darte de baja de los avisos de <?= htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') ?>.</p>
                <p>Confirma pulsando el botón.</p>
                <form method="post">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" name="unsubscribe_confirm" class="btn">Darme de baja</button>
                </form>
            <?php else: ?>
                <div class="alert info">El enlace de baja no es válido o ya ha sido usado.</div>
            <?php endif; ?>
        </div>
        <div class="footer">
            Este enlace es personal y único para tu suscripción.
        </div>
    </div>
</body>
</html>
