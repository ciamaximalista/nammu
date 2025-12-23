<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';

use Nammu\Core\ContentRepository;
use Nammu\Core\MarkdownConverter;
use Nammu\Core\TemplateRenderer;

define('MAILING_SUBSCRIBERS_FILE', __DIR__ . '/config/mailing-subscribers.json');
define('MAILING_PENDING_FILE', __DIR__ . '/config/mailing-pending.json');
define('MAILING_TOKENS_FILE', __DIR__ . '/config/mailing-tokens.json');
define('MAILING_SECRET_FILE', __DIR__ . '/config/mailing-secret.key');

function mailing_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function mailing_load_subscribers(): array
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

function mailing_load_pending(): array
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

function mailing_save_pending(array $pending): void
{
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

function mailing_load_tokens(): array
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

function mailing_save_tokens(array $tokens): void
{
    $dir = dirname(MAILING_TOKENS_FILE);
    nammu_ensure_directory($dir);
    $payload = json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return;
    }
    file_put_contents(MAILING_TOKENS_FILE, $payload, LOCK_EX);
    @chmod(MAILING_TOKENS_FILE, 0660);
}

function mailing_secret(): string
{
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

function mailing_unsubscribe_token(string $email): string
{
    return hash_hmac('sha256', mailing_normalize_email($email), mailing_secret());
}

function mailing_google_refresh_access_token(string $clientId, string $clientSecret, string $refreshToken): array
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

function mailing_gmail_send(string $fromEmail, string $fromName, string $to, string $subject, string $body, string $accessToken): bool
{
    $boundary = '=_NammuAvisos_' . bin2hex(random_bytes(6));
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

function mailing_send_message(array $config, string $email, string $subject, string $body): void
{
    $mailing = $config['mailing'] ?? [];
    $clientId = $mailing['client_id'] ?? '';
    $clientSecret = $mailing['client_secret'] ?? '';
    $fromEmail = $mailing['gmail_address'] ?? '';
    $tokens = mailing_load_tokens();
    $refresh = $tokens['refresh_token'] ?? '';

    $sent = false;
    if ($clientId !== '' && $clientSecret !== '' && $fromEmail !== '' && $refresh !== '') {
        try {
            $refreshed = mailing_google_refresh_access_token($clientId, $clientSecret, $refresh);
            $accessToken = $refreshed['access_token'] ?? '';
            if ($accessToken !== '') {
                $fromName = $config['site_author'] ?? ($config['site_name'] ?? 'Nammu');
                $sent = mailing_gmail_send($fromEmail, $fromName, $email, $subject, $body, $accessToken);
                $tokens['access_token'] = $accessToken;
                if (isset($refreshed['expires_at'])) {
                    $tokens['expires_at'] = $refreshed['expires_at'];
                }
                mailing_save_tokens($tokens);
            }
        } catch (Throwable $e) {
            $sent = false;
        }
    }

    if ($sent) {
        return;
    }

    $fromName = $config['site_name'] ?? 'Nammu';
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers = [
        'From: ' . $encodedName . ' <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    @mail($email, $subject, $body, implode("\r\n", $headers));
}

function mailing_send_subscribe_confirmation(array $config, string $email, string $token, string $baseUrl): void
{
    $siteLabel = trim((string) ($config['site_name'] ?? 'tu sitio'));
    $link = rtrim($baseUrl !== '' ? $baseUrl : nammu_base_url(), '/') . '/subscribe_confirm.php?email=' . urlencode($email) . '&token=' . urlencode($token);
    $subject = 'Confirma tu suscripcion a ' . $siteLabel;
    $body = "Hola,\n\nConfirma tu suscripcion a {$siteLabel} haciendo clic en el enlace:\n{$link}\n\nSi no solicitaste esta suscripcion, ignora este mensaje.";
    mailing_send_message($config, $email, $subject, $body);
}

function mailing_send_unsubscribe_confirmation(array $config, string $email, string $baseUrl): void
{
    $siteLabel = trim((string) ($config['site_name'] ?? 'tu sitio'));
    $token = mailing_unsubscribe_token($email);
    $link = rtrim($baseUrl !== '' ? $baseUrl : nammu_base_url(), '/') . '/unsubscribe.php?email=' . urlencode($email) . '&token=' . urlencode($token);
    $subject = 'Confirma tu baja en ' . $siteLabel;
    $body = "Hola,\n\nConfirma tu baja de los avisos de {$siteLabel} haciendo clic en el enlace:\n{$link}\n\nSi no solicitaste esta baja, ignora este mensaje.";
    mailing_send_message($config, $email, $subject, $body);
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

$postalUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/correos.php';
$postalLogoSvg = nammu_postal_icon_svg();
$footerLinks = nammu_build_footer_links($config, $theme, $homeUrl, $postalUrl);

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mailing_normalize_email($_POST['subscriber_email'] ?? '');
    $action = $_POST['subscription_action'] ?? '';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Introduce un email valido.';
        $messageType = 'danger';
    } elseif ($action === 'subscribe') {
        $pending = mailing_load_pending();
        $token = bin2hex(random_bytes(16));
        $pending = array_values(array_filter($pending, static function ($item) use ($email) {
            return !is_array($item) || ($item['email'] ?? '') !== $email;
        }));
        $pending[] = [
            'email' => $email,
            'token' => $token,
            'created_at' => time(),
        ];
        try {
            mailing_save_pending($pending);
            mailing_send_subscribe_confirmation($config, $email, $token, $publicBaseUrl);
            $message = 'Hemos enviado un email de confirmacion. Revisa tu correo.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = 'No pudimos procesar la solicitud. Intentalo de nuevo.';
            $messageType = 'danger';
        }
    } elseif ($action === 'unsubscribe') {
        try {
            mailing_send_unsubscribe_confirmation($config, $email, $publicBaseUrl);
            $message = 'Te hemos enviado un email para confirmar la baja.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = 'No pudimos procesar la solicitud. Intentalo de nuevo.';
            $messageType = 'danger';
        }
    }
}

$renderer = new TemplateRenderer(__DIR__ . '/template', [
    'siteTitle' => $displaySiteTitle,
    'siteDescription' => $siteDescription,
    'rssUrl' => $rssUrl !== '' ? $rssUrl : '/rss.xml',
    'baseUrl' => $homeUrl,
    'theme' => $theme,
    'postalEnabled' => ($config['postal']['enabled'] ?? 'off') === 'on',
    'postalUrl' => $postalUrl,
    'postalLogoSvg' => $postalLogoSvg,
    'footerLinks' => $footerLinks,
]);

$sortOrderValue = strtolower((string) ($config['pages_order_by'] ?? 'date'));
$sortOrder = in_array($sortOrderValue, ['date', 'alpha'], true) ? $sortOrderValue : 'date';
$isAlphabeticalOrder = ($sortOrder === 'alpha');
$lettersIndexUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/letras';
$renderer->setGlobal('lettersIndexUrl', $isAlphabeticalOrder ? $lettersIndexUrl : null);
$renderer->setGlobal('showLetterIndexButton', $isAlphabeticalOrder);
$itinerariesIndexUrl = $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') . '/itinerarios' : '/itinerarios';
$itineraryItems = is_dir(__DIR__ . '/itinerarios') ? glob(__DIR__ . '/itinerarios/*') : [];
$renderer->setGlobal('itinerariesIndexUrl', $itinerariesIndexUrl);
$renderer->setGlobal('hasItineraries', !empty($itineraryItems));
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
            <span class="postal-hero__badge">Avisos por email</span>
            <h1>Recibe avisos en tu correo</h1>
            <p>Suscribete para recibir avisos de nuevas publicaciones o confirma la baja si ya no quieres recibirlos.</p>
        </div>
        <div class="postal-hero__logo" aria-hidden="true">
            <svg width="44" height="44" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path fill="currentColor" d="M4 6h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm0 2v.3l8 5.2 8-5.2V8H4z"/>
            </svg>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="postal-feedback postal-feedback--<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="postal-grid-shell">
        <div class="postal-card">
            <h2>Alta en avisos</h2>
            <p>Recibe un email de confirmacion para activar tu suscripcion.</p>
            <form method="post" class="postal-form">
                <input type="hidden" name="subscription_action" value="subscribe">
                <label>
                    <span>Email</span>
                    <input type="email" name="subscriber_email" required>
                </label>
                <button type="submit" class="postal-btn postal-btn--primary">Suscribirme</button>
            </form>
        </div>
        <div class="postal-card postal-card--aside">
            <h2>Baja en avisos</h2>
            <p>Recibe un email para confirmar la baja de la lista.</p>
            <form method="post" class="postal-form">
                <input type="hidden" name="subscription_action" value="unsubscribe">
                <label>
                    <span>Email</span>
                    <input type="email" name="subscriber_email" required>
                </label>
                <button type="submit" class="postal-btn postal-btn--ghost">Darme de baja</button>
            </form>
        </div>
    </div>
</section>

<style>
    .postal-page {
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
    }
    .postal-hero {
        background: <?= $theme['colors']['highlight'] ?? '#f3f6f9' ?>;
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
        color: <?= $theme['colors']['brand'] ?? '#1b1b1b' ?>;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .postal-hero h1 {
        margin: 0.75rem 0 0.5rem;
        color: <?= $theme['colors']['brand'] ?? '#1b1b1b' ?>;
        font-size: clamp(2rem, 4vw, 2.6rem);
    }
    .postal-hero p {
        margin: 0;
        color: <?= $theme['colors']['text'] ?? '#222222' ?>;
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
        color: <?= $theme['colors']['accent'] ?? '#0a4c8a' ?>;
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
    .postal-card {
        background: #fff;
        border-radius: 20px;
        padding: 1.6rem;
        border: 1px solid rgba(0,0,0,0.08);
        box-shadow: 0 12px 30px rgba(0,0,0,0.06);
    }
    .postal-card--aside {
        background: <?= $theme['colors']['highlight'] ?? '#f3f6f9' ?>;
    }
    .postal-card h2 {
        margin-top: 0;
        color: <?= $theme['colors']['brand'] ?? '#1b1b1b' ?>;
    }
    .postal-card p {
        margin: 0 0 1.25rem;
        color: <?= $theme['colors']['text'] ?? '#222222' ?>;
    }
    .postal-grid-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 1.5rem;
    }
    .postal-form label {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        font-weight: 600;
        color: <?= $theme['colors']['brand'] ?? '#1b1b1b' ?>;
        font-size: 0.95rem;
    }
    .postal-form input {
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,0.12);
        padding: 0.75rem 0.9rem;
        font-size: 1rem;
        background: #fff;
        color: <?= $theme['colors']['text'] ?? '#222222' ?>;
    }
    .postal-form input:focus {
        outline: 2px solid <?= $theme['colors']['accent'] ?? '#0a4c8a' ?>;
        border-color: <?= $theme['colors']['accent'] ?? '#0a4c8a' ?>;
    }
    .postal-btn {
        border: none;
        border-radius: 999px;
        padding: 0.7rem 1.6rem;
        font-weight: 700;
        cursor: pointer;
        font-size: 0.95rem;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        margin-top: 1rem;
    }
    .postal-btn--primary {
        background: <?= $theme['colors']['accent'] ?? '#0a4c8a' ?>;
        color: #fff;
        box-shadow: 0 10px 22px rgba(0,0,0,0.12);
    }
    .postal-btn--primary:hover {
        transform: translateY(-1px);
    }
    .postal-btn--ghost {
        background: <?= $theme['colors']['highlight'] ?? '#f3f6f9' ?>;
        color: <?= $theme['colors']['brand'] ?? '#1b1b1b' ?>;
        border: 1px solid rgba(0,0,0,0.12);
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
        .postal-btn {
            width: 100%;
        }
    }
</style>
<?php
$content = ob_get_clean();

$postalPost = new Nammu\Core\Post('avisos', [
    'Title' => 'Avisos por email',
    'Description' => 'Suscríbete para recibir avisos de nuevas publicaciones.',
    'Template' => 'page',
], '');

$pageContent = $renderer->render('single', [
    'pageTitle' => 'Avisos por email',
    'post' => $postalPost,
    'htmlContent' => $content,
    'autoTocHtml' => '',
]);

echo $renderer->render('layout', [
    'pageTitle' => 'Avisos por email',
    'metaDescription' => 'Gestión de avisos por email.',
    'content' => $pageContent,
    'showLogo' => true,
]);
