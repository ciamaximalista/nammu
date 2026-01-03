<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/postal.php';

use Nammu\Core\ContentRepository;
use Nammu\Core\MarkdownConverter;
use Nammu\Core\TemplateRenderer;

if (function_exists('nammu_publish_scheduled_posts')) {
    nammu_publish_scheduled_posts(__DIR__ . '/content');
}
if (function_exists('nammu_process_scheduled_notifications_queue')) {
    nammu_process_scheduled_notifications_queue();
}

define('MAILING_SUBSCRIBERS_FILE', __DIR__ . '/config/mailing-subscribers.json');
define('MAILING_PENDING_FILE', __DIR__ . '/config/mailing-pending.json');
define('MAILING_TOKENS_FILE', __DIR__ . '/config/mailing-tokens.json');

function postal_normalize_mailing_email(string $email): string
{
    return strtolower(trim($email));
}

function postal_load_mailing_subscribers(): array
{
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

function postal_save_mailing_subscribers(array $list): void
{
    $dir = dirname(MAILING_SUBSCRIBERS_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $payload = json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return;
    }
    file_put_contents(MAILING_SUBSCRIBERS_FILE, $payload, LOCK_EX);
    @chmod(MAILING_SUBSCRIBERS_FILE, 0664);
}

function postal_sync_mailing_subscriber(string $email): void
{
    $normalized = postal_normalize_mailing_email($email);
    if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $subscribers = postal_load_mailing_subscribers();
    foreach ($subscribers as $existing) {
        if (postal_normalize_mailing_email((string) $existing) === $normalized) {
            return;
        }
    }
    $subscribers[] = $normalized;
    postal_save_mailing_subscribers($subscribers);
}

function postal_load_mailing_pending(): array
{
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

function postal_save_mailing_pending(array $pending): void
{
    $dir = dirname(MAILING_PENDING_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $payload = json_encode(array_values($pending), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo guardar la lista de pendientes.');
    }
    file_put_contents(MAILING_PENDING_FILE, $payload, LOCK_EX);
    @chmod(MAILING_PENDING_FILE, 0664);
}

function postal_load_mailing_tokens(): array
{
    if (!is_file(MAILING_TOKENS_FILE)) {
        return [];
    }
    $raw = file_get_contents(MAILING_TOKENS_FILE);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function postal_save_mailing_tokens(array $tokens): void
{
    $dir = dirname(MAILING_TOKENS_FILE);
    nammu_ensure_directory($dir);
    $payload = json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo guardar tokens de mailing.');
    }
    file_put_contents(MAILING_TOKENS_FILE, $payload, LOCK_EX);
    @chmod(MAILING_TOKENS_FILE, 0660);
}

function postal_google_refresh_access_token(string $clientId, string $clientSecret, string $refreshToken): array
{
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
    $raw = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create($opts));
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || isset($decoded['error'])) {
        throw new RuntimeException('No se pudo refrescar el token de Gmail');
    }
    if (isset($decoded['expires_in'])) {
        $decoded['expires_at'] = time() + (int) $decoded['expires_in'];
    }
    return $decoded;
}

function postal_gmail_send(string $fromEmail, string $fromName, string $to, string $subject, string $body, string $accessToken): bool
{
    $boundary = '=_NammuPostal_' . bin2hex(random_bytes(6));
    $encodedName = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($fromName, 'UTF-8', 'Q', "\r\n")
        : '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $fromHeader = $encodedName . ' <' . $fromEmail . '>';
    $headers = [
        'From: ' . $fromHeader,
        'To: ' . $to,
        'Subject: ' . (function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($subject, 'UTF-8', 'Q', "\r\n") : '=?UTF-8?B?' . base64_encode($subject) . '?='),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $messageParts = [
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 7bit',
        '',
        $body,
        '--' . $boundary . '--',
    ];
    $raw = implode("\r\n", array_merge($headers, [''], $messageParts));
    $payload = json_encode(['raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=')]);
    if ($payload === false) {
        return false;
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
    $resp = @file_get_contents('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', false, stream_context_create($opts));
    if ($resp === false) {
        return false;
    }
    $decoded = json_decode($resp, true);
    return is_array($decoded) && isset($decoded['id']);
}

function postal_send_mailing_confirmation(string $email, string $token, string $siteTitle): void
{
    $base = nammu_base_url();
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }
    $link = rtrim($base, '/') . '/subscribe_confirm.php?email=' . urlencode($email) . '&token=' . urlencode($token);
    $siteLabel = $siteTitle !== '' ? $siteTitle : 'tu sitio';
    $subject = 'Confirma tu suscripción a ' . $siteLabel;
    $bodyLines = [
        "Hola,",
        "",
        "Confirma tu suscripción a {$siteLabel} haciendo clic en el enlace:",
        $link,
        "",
        "Si no solicitaste esta suscripción, ignora este mensaje.",
    ];
    $body = implode("\n", $bodyLines);

    $config = nammu_load_config();
    $mailing = $config['mailing'] ?? [];
    $clientId = $mailing['client_id'] ?? '';
    $clientSecret = $mailing['client_secret'] ?? '';
    $fromEmail = $mailing['gmail_address'] ?? '';
    $tokens = postal_load_mailing_tokens();
    $refresh = $tokens['refresh_token'] ?? '';

    $sent = false;
    if ($clientId !== '' && $clientSecret !== '' && $fromEmail !== '' && $refresh !== '') {
        try {
            $refreshed = postal_google_refresh_access_token($clientId, $clientSecret, $refresh);
            $accessToken = $refreshed['access_token'] ?? '';
            if ($accessToken !== '') {
                $fromName = $config['site_author'] ?? ($config['site_name'] ?? 'Nammu');
                $sent = postal_gmail_send($fromEmail, $fromName, $email, $subject, $body, $accessToken);
                $tokens['access_token'] = $accessToken;
                if (isset($refreshed['expires_at'])) {
                    $tokens['expires_at'] = $refreshed['expires_at'];
                }
                postal_save_mailing_tokens($tokens);
            }
        } catch (Throwable $e) {
            $sent = false;
        }
    }

    if ($sent) {
        return;
    }

    $headers = [];
    $fromName = $siteTitle !== '' ? $siteTitle : 'Nammu';
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers[] = 'From: ' . $encodedName . ' <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    @mail($email, $subject, $body, implode("\r\n", $headers));
}

function postal_send_password_reset(string $email, string $token, string $siteTitle): void
{
    $base = nammu_base_url();
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }
    $link = rtrim($base, '/') . '/correos-reset.php?email=' . urlencode($email) . '&token=' . urlencode($token);
    $siteLabel = $siteTitle !== '' ? $siteTitle : 'tu sitio';
    $subject = 'Restablece tu contrasena en ' . $siteLabel;
    $bodyLines = [
        "Hola,",
        "",
        "Puedes restablecer tu contrasena de {$siteLabel} desde este enlace:",
        $link,
        "",
        "Si no solicitaste este cambio, ignora este mensaje.",
    ];
    $body = implode("\n", $bodyLines);

    $config = nammu_load_config();
    $mailing = $config['mailing'] ?? [];
    $clientId = $mailing['client_id'] ?? '';
    $clientSecret = $mailing['client_secret'] ?? '';
    $fromEmail = $mailing['gmail_address'] ?? '';
    $tokens = postal_load_mailing_tokens();
    $refresh = $tokens['refresh_token'] ?? '';

    $sent = false;
    if ($clientId !== '' && $clientSecret !== '' && $fromEmail !== '' && $refresh !== '') {
        try {
            $refreshed = postal_google_refresh_access_token($clientId, $clientSecret, $refresh);
            $accessToken = $refreshed['access_token'] ?? '';
            if ($accessToken !== '') {
                $fromName = $config['site_author'] ?? ($config['site_name'] ?? 'Nammu');
                $sent = postal_gmail_send($fromEmail, $fromName, $email, $subject, $body, $accessToken);
                $tokens['access_token'] = $accessToken;
                if (isset($refreshed['expires_at'])) {
                    $tokens['expires_at'] = $refreshed['expires_at'];
                }
                postal_save_mailing_tokens($tokens);
            }
        } catch (Throwable $e) {
            $sent = false;
        }
    }

    if ($sent) {
        return;
    }

    $headers = [];
    $fromName = $siteTitle !== '' ? $siteTitle : 'Nammu';
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers[] = 'From: ' . $encodedName . ' <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    @mail($email, $subject, $body, implode("\r\n", $headers));
}

$config = nammu_load_config();
$contentRepository = new ContentRepository(__DIR__ . '/content');
$markdown = new MarkdownConverter();
$siteDocument = $contentRepository->getDocument('index');
$siteTitle = $siteDocument['metadata']['Title'] ?? ($config['site_name'] ?? 'Nammu');
$siteDescription = $siteDocument['metadata']['Description'] ?? ($config['site_description'] ?? '');
$configBaseUrl = $config['site_url'] ?? '';
if (is_string($configBaseUrl)) {
    $configBaseUrl = rtrim(trim($configBaseUrl), '/');
}
$publicBaseUrl = $configBaseUrl !== '' ? $configBaseUrl : nammu_base_url();
$homeUrl = $publicBaseUrl !== '' ? $publicBaseUrl : '/';
$rssUrl = ($publicBaseUrl !== '' ? $publicBaseUrl : '') . '/rss.xml';

$theme = nammu_template_settings();
$footerRaw = $theme['footer'] ?? '';
$theme['footer_html'] = '';
if ($footerRaw !== '') {
    if (str_contains($footerRaw, '<')) {
        $theme['footer_html'] = preg_replace('/>\s+</u', '><', trim($footerRaw));
    } else {
        $theme['footer_html'] = $markdown->toHtml($footerRaw);
    }
}
$theme['logo_url'] = nammu_resolve_asset($theme['images']['logo'] ?? '', $publicBaseUrl);
$theme['favicon_url'] = null;
if (!empty($theme['logo_url'])) {
    $logoPath = parse_url($theme['logo_url'], PHP_URL_PATH) ?? '';
    if ($logoPath && preg_match('/\.(png|ico)$/i', $logoPath)) {
        $theme['favicon_url'] = $theme['logo_url'];
    }
}
$displaySiteTitle = $theme['blog'] !== '' ? $theme['blog'] : $siteTitle;
$siteLang = $config['site_lang'] ?? 'es';
if (!is_string($siteLang) || $siteLang === '') {
    $siteLang = 'es';
}

$postalEnabled = ($config['postal']['enabled'] ?? 'off') === 'on';
$postalUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/correos.php';
$postalLogoSvg = nammu_postal_icon_svg();
$hasPodcast = !empty(nammu_collect_podcast_items(__DIR__ . '/content', $publicBaseUrl));
$footerLinks = nammu_build_footer_links($config, $theme, $homeUrl, $postalUrl, $hasItineraries, $hasPodcast);
$logoForJsonLd = $theme['logo_url'] ?? '';
$orgJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $displaySiteTitle,
    'url' => $homeUrl,
];
if (!empty($logoForJsonLd)) {
    $orgJsonLd['logo'] = $logoForJsonLd;
}
$siteJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => $displaySiteTitle,
    'url' => $homeUrl,
    'description' => $siteDescription,
    'inLanguage' => $siteLang,
];
$categoryMapAll = nammu_collect_categories_from_posts($contentRepository->all());
$uncategorizedSlug = nammu_slugify_label('Sin Categoría');
$hasCategories = false;
foreach ($categoryMapAll as $slugKey => $data) {
    if ($slugKey !== $uncategorizedSlug) {
        $hasCategories = true;
        break;
    }
}
$message = '';
$messageType = 'info';
$colors = $theme['colors'] ?? [];
$accentColor = htmlspecialchars($colors['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8');
$highlight = htmlspecialchars($colors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$textColor = htmlspecialchars($colors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
$brandColor = htmlspecialchars($colors['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');

$entries = postal_load_entries();
$loggedEmail = $_SESSION['postal_user_email'] ?? '';
$loggedEntry = $loggedEmail !== '' ? postal_get_entry($loggedEmail, $entries) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['postal_login'])) {
        $email = postal_normalize_email($_POST['login_email'] ?? '');
        $password = $_POST['login_password'] ?? '';
        $entry = postal_get_entry($email, $entries);
        if (!$entry || $password === '' || !password_verify($password, (string) ($entry['password_hash'] ?? ''))) {
            $message = 'Correo o contraseña incorrectos.';
            $messageType = 'danger';
        } else {
            $_SESSION['postal_user_email'] = $email;
            $loggedEmail = $email;
            $loggedEntry = $entry;
            $message = 'Sesión iniciada correctamente.';
            $messageType = 'success';
        }
    } elseif (isset($_POST['postal_logout'])) {
        unset($_SESSION['postal_user_email']);
        $loggedEmail = '';
        $loggedEntry = null;
    } elseif (isset($_POST['postal_forgot'])) {
        $email = postal_normalize_email($_POST['forgot_email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Introduce un email valido para recuperar tu contrasena.';
            $messageType = 'danger';
        } else {
            $entry = postal_get_entry($email, $entries);
            if ($entry) {
                try {
                    $tokens = postal_prune_reset_tokens(postal_load_reset_tokens());
                    $tokens = array_values(array_filter($tokens, static function ($item) use ($email) {
                        return is_array($item) && ($item['email'] ?? '') !== $email;
                    }));
                    $token = bin2hex(random_bytes(16));
                    $tokens[] = [
                        'email' => $email,
                        'token' => $token,
                        'expires_at' => time() + 86400,
                    ];
                    postal_save_reset_tokens($tokens);
                    postal_send_password_reset($email, $token, $siteTitle);
                } catch (Throwable $e) {
                    $message = 'No pudimos enviar el email de recuperacion.';
                    $messageType = 'danger';
                    $entry = null;
                }
            }
            if ($message === '') {
                $message = 'Si el email existe, te hemos enviado un enlace para restablecer la contrasena.';
                $messageType = 'success';
            }
        }
    } elseif (isset($_POST['postal_register'])) {
        if (!$postalEnabled) {
            $message = 'La lista de correo postal no está activa.';
            $messageType = 'warning';
        } else {
            $email = postal_normalize_email($_POST['postal_email'] ?? '');
            $password = trim((string) ($_POST['postal_password'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                $message = 'Email y contraseña son obligatorios.';
                $messageType = 'danger';
            } elseif (postal_get_entry($email, $entries)) {
                $message = 'Ese email ya está registrado. Inicia sesión para editarlo.';
                $messageType = 'warning';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $entries = postal_upsert_entry([
                    'email' => $email,
                    'name' => $_POST['postal_name'] ?? '',
                    'address' => $_POST['postal_address'] ?? '',
                    'city' => $_POST['postal_city'] ?? '',
                    'postal_code' => $_POST['postal_postal_code'] ?? '',
                    'region' => $_POST['postal_region'] ?? '',
                    'country' => $_POST['postal_country'] ?? '',
                ], $hash, $entries);
                try {
                    postal_save_entries($entries);
                    $alreadySubscribed = false;
                    $mailingSubscribers = postal_load_mailing_subscribers();
                    foreach ($mailingSubscribers as $existing) {
                        if (postal_normalize_mailing_email((string) $existing) === $email) {
                            $alreadySubscribed = true;
                            break;
                        }
                    }
                    if (!$alreadySubscribed) {
                        $pending = postal_load_mailing_pending();
                        $token = bin2hex(random_bytes(16));
                        $pending = array_values(array_filter($pending, static function ($item) use ($email) {
                            return is_array($item) && ($item['email'] ?? '') !== $email;
                        }));
                        $pending[] = [
                            'email' => $email,
                            'token' => $token,
                            'created_at' => date('c'),
                        ];
                        postal_save_mailing_pending($pending);
                        postal_send_mailing_confirmation($email, $token, $siteTitle);
                    }
                    $_SESSION['postal_user_email'] = $email;
                    $loggedEmail = $email;
                    $loggedEntry = $entries[$email] ?? null;
                    $message = $alreadySubscribed
                        ? 'Registro completado. Puedes editar tus datos cuando quieras.'
                        : 'Registro completado. Te enviamos un email para confirmar los avisos por correo.';
                    $messageType = 'success';
                } catch (Throwable $e) {
                    $message = 'No se pudo guardar tu dirección.';
                    $messageType = 'danger';
                }
            }
        }
    } elseif (isset($_POST['postal_update'])) {
        if (!$loggedEmail || !$loggedEntry) {
            $message = 'Debes iniciar sesión para editar.';
            $messageType = 'danger';
        } else {
            $passwordRaw = trim((string) ($_POST['postal_password'] ?? ''));
            $passwordHash = $passwordRaw !== '' ? password_hash($passwordRaw, PASSWORD_DEFAULT) : null;
            $entries = postal_upsert_entry([
                'email' => $loggedEmail,
                'name' => $_POST['postal_name'] ?? '',
                'address' => $_POST['postal_address'] ?? '',
                'city' => $_POST['postal_city'] ?? '',
                'postal_code' => $_POST['postal_postal_code'] ?? '',
                'region' => $_POST['postal_region'] ?? '',
                'country' => $_POST['postal_country'] ?? '',
            ], $passwordHash, $entries);
            try {
                postal_save_entries($entries);
                $loggedEntry = $entries[$loggedEmail] ?? $loggedEntry;
                $message = 'Datos actualizados.';
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = 'No se pudieron guardar los cambios.';
                $messageType = 'danger';
            }
        }
    } elseif (isset($_POST['postal_delete'])) {
        if (!$loggedEmail) {
            $message = 'Debes iniciar sesión para borrar.';
            $messageType = 'danger';
        } else {
            $entries = postal_delete_entry($loggedEmail, $entries);
            try {
                postal_save_entries($entries);
                unset($_SESSION['postal_user_email']);
                $loggedEmail = '';
                $loggedEntry = null;
                $message = 'Tus datos han sido eliminados.';
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = 'No se pudo borrar tu dirección.';
                $messageType = 'danger';
            }
        }
    }
}

$renderer = new TemplateRenderer(__DIR__ . '/template', [
    'siteTitle' => $displaySiteTitle,
    'siteDescription' => $siteDescription,
    'rssUrl' => $rssUrl !== '' ? $rssUrl : '/rss.xml',
    'baseUrl' => $homeUrl,
    'theme' => $theme,
    'postalEnabled' => $postalEnabled,
    'postalUrl' => $postalUrl,
    'postalLogoSvg' => $postalLogoSvg,
    'footerLinks' => $footerLinks,
]);
$renderer->setGlobal('hasCategories', $hasCategories);
    
$sortOrderValue = strtolower((string) ($config['pages_order_by'] ?? 'date'));
$sortOrder = in_array($sortOrderValue, ['date', 'alpha'], true) ? $sortOrderValue : 'date';
$isAlphabeticalOrder = ($sortOrder === 'alpha');
$lettersIndexUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/letras';
$renderer->setGlobal('lettersIndexUrl', $isAlphabeticalOrder ? $lettersIndexUrl : null);
$renderer->setGlobal('showLetterIndexButton', $isAlphabeticalOrder);
$itinerariesIndexUrl = $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') . '/itinerarios' : '/itinerarios';
$renderer->setGlobal('itinerariesIndexUrl', $itinerariesIndexUrl);
$renderer->setGlobal('hasItineraries', $hasItineraries);
$renderer->setGlobal('resolveImage', function (?string $image) use ($publicBaseUrl): ?string {
    if ($image === null || $image === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }
    $normalized = ltrim($image, '/');
    if (str_starts_with($normalized, 'assets/')) {
        $normalized = substr($normalized, 7);
    }
    $path = 'assets/' . ltrim($normalized, '/');
    return $publicBaseUrl !== '' ? $publicBaseUrl . '/' . $path : '/' . $path;
});

ob_start();
?>
<section class="postal-page">
    <div class="postal-hero">
        <div class="postal-hero__content">
            <span class="postal-hero__badge">Correo postal</span>
            <h1>Recibe envios fisicos en casa</h1>
            <p>Suscribete para recibir correspondencia fisica y manten tus datos actualizados cuando lo necesites.</p>
        </div>
        <div class="postal-hero__logo" aria-hidden="true">
            <?= $postalLogoSvg ?>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="postal-feedback postal-feedback--<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$postalEnabled): ?>
        <div class="postal-card postal-card--muted">
            <h2>Lista temporalmente desactivada</h2>
            <p>La lista de correo postal no esta activa en este momento. Vuelve pronto.</p>
        </div>
    <?php else: ?>
        <?php if ($loggedEntry): ?>
            <div class="postal-card">
                <h2>Tu libreta postal</h2>
                <p>Actualiza tu direccion y tus datos cuando lo necesites.</p>
                <form method="post" class="postal-form">
                    <div class="postal-grid">
                        <label>
                            <span>Nombre y apellidos</span>
                            <input type="text" name="postal_name" value="<?= htmlspecialchars($loggedEntry['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        <label class="postal-span-2">
                            <span>Direccion</span>
                            <input type="text" name="postal_address" value="<?= htmlspecialchars($loggedEntry['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        <label>
                            <span>Poblacion</span>
                            <input type="text" name="postal_city" value="<?= htmlspecialchars($loggedEntry['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        <label>
                            <span>Codigo Postal</span>
                            <input type="text" name="postal_postal_code" value="<?= htmlspecialchars($loggedEntry['postal_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        <label>
                            <span>Provincia/Region</span>
                            <input type="text" name="postal_region" value="<?= htmlspecialchars($loggedEntry['region'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        <label>
                            <span>Pais</span>
                            <input type="text" name="postal_country" value="<?= htmlspecialchars($loggedEntry['country'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        <label>
                            <span>Email</span>
                            <input type="email" value="<?= htmlspecialchars($loggedEntry['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled>
                        </label>
                        <label class="postal-span-2">
                            <span>Contrasena (opcional)</span>
                            <input type="password" name="postal_password" placeholder="Nueva contrasena">
                        </label>
                    </div>
                    <div class="postal-actions">
                        <button type="submit" name="postal_update" class="postal-btn postal-btn--primary">Guardar cambios</button>
                        <button type="submit" name="postal_delete" class="postal-btn postal-btn--danger" onclick="return confirm('¿Eliminar tus datos postales?');">Borrar datos</button>
                        <button type="submit" name="postal_logout" class="postal-btn postal-btn--ghost">Cerrar sesion</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="postal-grid-shell">
                <div class="postal-card">
                    <h2>Suscripcion postal</h2>
                    <p>Completa tus datos y crea una contrasena para gestionarlos despues.</p>
                    <form method="post" class="postal-form">
                        <div class="postal-grid">
                            <label>
                                <span>Nombre y apellidos</span>
                                <input type="text" name="postal_name" required>
                            </label>
                            <label class="postal-span-2">
                                <span>Direccion</span>
                                <input type="text" name="postal_address" required>
                            </label>
                            <label>
                                <span>Poblacion</span>
                                <input type="text" name="postal_city" required>
                            </label>
                            <label>
                                <span>Codigo Postal</span>
                                <input type="text" name="postal_postal_code" required>
                            </label>
                            <label>
                                <span>Provincia/Region</span>
                                <input type="text" name="postal_region" required>
                            </label>
                            <label>
                                <span>Pais</span>
                                <input type="text" name="postal_country" required>
                            </label>
                            <label>
                                <span>Email</span>
                                <input type="email" name="postal_email" required>
                            </label>
                            <label class="postal-span-2">
                                <span>Contrasena</span>
                                <input type="password" name="postal_password" required>
                            </label>
                        </div>
                        <button type="submit" name="postal_register" class="postal-btn postal-btn--primary">Suscribirme</button>
                    </form>
                </div>
                <div class="postal-card postal-card--aside">
                    <h2>Ya tengo cuenta</h2>
                    <p>Entra con tu email y contrasena para editar tus datos.</p>
                    <form method="post" class="postal-form">
                        <label>
                            <span>Email</span>
                            <input type="email" name="login_email" required>
                        </label>
                        <label>
                            <span>Contrasena</span>
                            <input type="password" name="login_password" required>
                        </label>
                        <button type="submit" name="postal_login" class="postal-btn postal-btn--ghost">Entrar</button>
                    </form>
                    <div class="postal-divider"></div>
                    <p class="postal-mini">¿Olvidaste tu contrasena?</p>
                    <form method="post" class="postal-form postal-form--compact">
                        <label>
                            <span>Email</span>
                            <input type="email" name="forgot_email" required>
                        </label>
                        <button type="submit" name="postal_forgot" class="postal-btn postal-btn--ghost">Enviar enlace</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<style>
    .postal-page {
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
    }
    .postal-hero {
        background: <?= $highlight ?>;
        border-radius: 24px;
        padding: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        border: 1px solid rgba(0,0,0,0.05);
    }
    .postal-hero__badge {
        display: inline-flex;
        align-items: center;
        padding: 0.35rem 0.8rem;
        border-radius: 999px;
        background: rgba(0,0,0,0.06);
        color: <?= $brandColor ?>;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .postal-hero h1 {
        margin: 0.75rem 0 0.5rem;
        color: <?= $brandColor ?>;
        font-size: clamp(2rem, 4vw, 2.6rem);
    }
    .postal-hero p {
        margin: 0;
        color: <?= $textColor ?>;
        max-width: 42ch;
    }
    .postal-hero__logo {
        width: 86px;
        height: 86px;
        border-radius: 22px;
        background: #fff;
        display: grid;
        place-items: center;
        box-shadow: 0 16px 30px rgba(0,0,0,0.12);
    }
    .postal-hero__logo svg {
        width: 46px;
        height: 46px;
    }
    .postal-feedback {
        padding: 1rem 1.2rem;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,0.08);
        background: #fff;
        font-weight: 600;
    }
    .postal-feedback--success {
        border-color: rgba(35, 173, 98, 0.35);
        background: rgba(35, 173, 98, 0.12);
        color: #1b6a42;
    }
    .postal-feedback--danger {
        border-color: rgba(213, 64, 64, 0.35);
        background: rgba(213, 64, 64, 0.12);
        color: #7b1d1d;
    }
    .postal-feedback--warning {
        border-color: rgba(212, 137, 0, 0.35);
        background: rgba(212, 137, 0, 0.12);
        color: #7a4d00;
    }
    .postal-card {
        background: #fff;
        border-radius: 20px;
        padding: 1.6rem;
        border: 1px solid rgba(0,0,0,0.08);
        box-shadow: 0 12px 30px rgba(0,0,0,0.06);
    }
    .postal-card--aside {
        background: <?= $highlight ?>;
    }
    .postal-card--muted {
        background: <?= $highlight ?>;
        color: <?= $textColor ?>;
    }
    .postal-card h2 {
        margin-top: 0;
        color: <?= $brandColor ?>;
    }
    .postal-card p {
        margin: 0 0 1.25rem;
        color: <?= $textColor ?>;
    }
    .postal-grid-shell {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
        gap: 1.5rem;
    }
    .postal-form label {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        font-weight: 600;
        color: <?= $brandColor ?>;
        font-size: 0.95rem;
    }
    .postal-form input {
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,0.12);
        padding: 0.75rem 0.9rem;
        font-size: 1rem;
        background: #fff;
        color: <?= $textColor ?>;
    }
    .postal-form input:focus {
        outline: 2px solid <?= $accentColor ?>;
        border-color: <?= $accentColor ?>;
    }
    .postal-divider {
        height: 1px;
        background: rgba(0,0,0,0.08);
        margin: 1.2rem 0 1rem;
    }
    .postal-mini {
        margin: 0 0 0.75rem;
        font-size: 0.92rem;
        color: <?= $textColor ?>;
    }
    .postal-form--compact label {
        font-size: 0.9rem;
    }
    .postal-form--compact input {
        padding: 0.6rem 0.75rem;
    }
    .postal-form--compact .postal-btn {
        margin-top: 0.75rem;
    }
    .postal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem 1.25rem;
        margin-bottom: 1.5rem;
    }
    .postal-span-2 {
        grid-column: span 2;
    }
    .postal-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .postal-btn {
        border: none;
        border-radius: 999px;
        padding: 0.7rem 1.6rem;
        font-weight: 700;
        cursor: pointer;
        font-size: 0.95rem;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .postal-btn--primary {
        background: <?= $accentColor ?>;
        color: #fff;
        box-shadow: 0 10px 22px rgba(0,0,0,0.12);
    }
    .postal-btn--primary:hover {
        transform: translateY(-1px);
    }
    .postal-btn--ghost {
        background: <?= $highlight ?>;
        color: <?= $brandColor ?>;
        border: 1px solid rgba(0,0,0,0.12);
    }
    .postal-btn--danger {
        background: rgba(213, 64, 64, 0.12);
        color: #7b1d1d;
        border: 1px solid rgba(213, 64, 64, 0.3);
    }
    @media (max-width: 900px) {
        .postal-grid-shell {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 720px) {
        .postal-hero {
            flex-direction: column;
            align-items: flex-start;
        }
        .postal-hero__logo {
            align-self: center;
        }
        .postal-grid {
            grid-template-columns: 1fr;
        }
        .postal-span-2 {
            grid-column: span 1;
        }
        .postal-actions {
            flex-direction: column;
        }
        .postal-btn {
            width: 100%;
        }
    }
</style>
<?php
$content = ob_get_clean();

$postalPost = new Nammu\Core\Post('correo-postal', [
    'Title' => 'Correo Postal',
    'Description' => 'Suscríbete para recibir envíos físicos y actualiza tus datos cuando lo necesites.',
    'Template' => 'page',
], '');

$pageContent = $renderer->render('single', [
    'pageTitle' => 'Correo Postal',
    'post' => $postalPost,
    'htmlContent' => $content,
    'autoTocHtml' => '',
]);

echo $renderer->render('layout', [
    'pageTitle' => 'Correo Postal',
    'metaDescription' => 'Suscripción postal y gestión de direcciones.',
    'content' => $pageContent,
    'jsonLd' => [$siteJsonLd, $orgJsonLd],
    'pageLang' => $siteLang,
    'showLogo' => true,
]);
