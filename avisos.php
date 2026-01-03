<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';

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
define('MAILING_SECRET_FILE', __DIR__ . '/config/mailing-secret.key');

function mailing_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function mailing_available_prefs(array $config): array
{
    $mailing = $config['mailing'] ?? [];
    $autoPodcast = $mailing['auto_podcast'] ?? null;
    if ($autoPodcast === null && !array_key_exists('auto_podcast', $mailing)) {
        $autoPodcast = 'on';
    }
    return [
        'posts' => ($mailing['auto_posts'] ?? 'off') === 'on',
        'itineraries' => ($mailing['auto_itineraries'] ?? 'off') === 'on',
        'podcast' => $autoPodcast === 'on',
        'newsletter' => ($mailing['auto_newsletter'] ?? 'off') === 'on',
    ];
}

function mailing_default_prefs(array $available): array
{
    $hasAny = false;
    $prefs = [
        'posts' => false,
        'itineraries' => false,
        'podcast' => false,
        'newsletter' => false,
    ];
    foreach ($prefs as $key => $value) {
        if (!empty($available[$key])) {
            $prefs[$key] = true;
            $hasAny = true;
        }
    }
    if (!$hasAny) {
        return [
            'posts' => true,
            'itineraries' => true,
            'podcast' => true,
            'newsletter' => true,
        ];
    }
    return $prefs;
}

function mailing_prefs_from_selection(array $selected, array $available): array
{
    $prefs = [
        'posts' => false,
        'itineraries' => false,
        'podcast' => false,
        'newsletter' => false,
    ];
    $wantsAvisos = in_array('avisos', $selected, true);
    $wantsPodcast = in_array('podcast', $selected, true);
    $wantsNewsletter = in_array('newsletter', $selected, true);
    if (!empty($available['posts']) && $wantsAvisos) {
        $prefs['posts'] = true;
    }
    if (!empty($available['itineraries']) && $wantsAvisos) {
        $prefs['itineraries'] = true;
    }
    if (!empty($available['podcast']) && $wantsPodcast) {
        $prefs['podcast'] = true;
    }
    if (!empty($available['newsletter']) && $wantsNewsletter) {
        $prefs['newsletter'] = true;
    }
    return $prefs;
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
    $body = "Hola,\n\nConfirma tu baja de las comunicaciones de {$siteLabel} haciendo clic en el enlace:\n{$link}\n\nSi no solicitaste esta baja, ignora este mensaje.";
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

$availablePrefs = mailing_available_prefs($config);
$siteLang = $config['site_lang'] ?? 'es';
if (!is_string($siteLang) || $siteLang === '') {
    $siteLang = 'es';
}
$prefsDefault = mailing_default_prefs($availablePrefs);
$prefsAvailableKeys = [];
$hasBlogAlerts = !empty($availablePrefs['posts']) || !empty($availablePrefs['itineraries']);
$hasPodcastAlerts = !empty($availablePrefs['podcast']);
$hasAlerts = $hasBlogAlerts || $hasPodcastAlerts;
if ($hasBlogAlerts) {
    $prefsAvailableKeys[] = 'avisos';
}
if ($hasPodcastAlerts) {
    $prefsAvailableKeys[] = 'podcast';
}
if (!empty($availablePrefs['newsletter'])) {
    $prefsAvailableKeys[] = 'newsletter';
}
$hasAvisos = $hasAlerts;
$hasNewsletter = in_array('newsletter', $prefsAvailableKeys, true);
$hasAnySubscription = $hasAvisos || $hasNewsletter;
$alertsLabel = '';
if ($hasAlerts) {
    $alertsParts = [];
    if ($hasBlogAlerts) {
        $alertsParts[] = 'nuevas publicaciones';
    }
    if ($hasPodcastAlerts) {
        $alertsParts[] = 'nuevos episodios de podcast';
    }
    $alertsLabel = implode(' y ', $alertsParts);
}
$pageLabel = $hasAvisos && $hasNewsletter ? 'Avisos por email y newsletters' : ($hasNewsletter ? 'Newsletter' : 'Avisos');
if (!$hasAnySubscription) {
    $pageIntro = 'El administrador del blog no ha configurado todavia ni el sistema de avisos ni las newsletters ni los avisos de podcast.';
} else {
    $pageIntro = $hasAvisos && $hasNewsletter
        ? 'Suscribete para recibir avisos de ' . $alertsLabel . ' y newsletters o confirma la baja si ya no quieres recibirlos.'
        : ($hasNewsletter
            ? 'Suscribete para recibir la newsletter o confirma la baja si ya no quieres recibirla.'
            : 'Suscribete para recibir avisos de ' . $alertsLabel . ' o confirma la baja si ya no quieres recibirlos.');
}
$badgeLabel = $hasAvisos && $hasNewsletter ? 'Avisos y newsletters' : ($hasNewsletter ? 'Newsletter' : 'Avisos por email');
$subscribeTitle = $hasAvisos && $hasNewsletter ? 'Alta en avisos y newsletters' : ($hasNewsletter ? 'Alta en newsletter' : 'Alta en avisos');
$subscribeCopy = $hasAvisos && $hasNewsletter
    ? 'Recibe un email de confirmacion para activar tus preferencias.'
    : ($hasNewsletter
        ? 'Recibe un email de confirmacion para activar la newsletter.'
        : 'Recibe un email de confirmacion para activar los avisos.');
$updateTitle = $hasAvisos && $hasNewsletter ? 'Modificar preferencias' : ($hasNewsletter ? 'Actualizar newsletter' : 'Actualizar avisos');
$updateCopy = $hasAvisos && $hasNewsletter
    ? 'Actualiza tus opciones y confirma el cambio desde el email.'
    : ($hasNewsletter
        ? 'Actualiza tu suscripcion y confirma el cambio desde el email.'
        : 'Actualiza tus avisos y confirma el cambio desde el email.');
$unsubscribeTitle = $hasAvisos && $hasNewsletter ? 'Baja' : ($hasNewsletter ? 'Baja de newsletter' : 'Baja de avisos');
$unsubscribeCopy = $hasAvisos && $hasNewsletter
    ? 'Recibe un email para confirmar la baja.'
    : ($hasNewsletter
        ? 'Recibe un email para confirmar la baja de la newsletter.'
        : 'Recibe un email para confirmar la baja de los avisos.');

$postalUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/correos.php';
$postalLogoSvg = nammu_postal_icon_svg();
$itineraryItems = is_dir(__DIR__ . '/itinerarios') ? glob(__DIR__ . '/itinerarios/*') : [];
$hasItineraries = !empty($itineraryItems);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mailing_normalize_email($_POST['subscriber_email'] ?? '');
    $action = $_POST['subscription_action'] ?? '';
    $prefsSelected = $_POST['subscription_prefs'] ?? [];
    $prefsSelected = is_array($prefsSelected) ? $prefsSelected : [];
    $prefsSelected = array_values(array_intersect($prefsSelected, $prefsAvailableKeys));
    $prefs = mailing_prefs_from_selection($prefsSelected, $availablePrefs);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Introduce un email valido.';
        $messageType = 'danger';
    } elseif (!empty($prefsAvailableKeys) && empty($prefsSelected) && in_array($action, ['subscribe', 'update'], true)) {
        $message = 'Selecciona al menos una opcion de envio.';
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
            'prefs' => !empty($prefsAvailableKeys) ? $prefs : $prefsDefault,
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
    } elseif ($action === 'update') {
        $pending = mailing_load_pending();
        $token = bin2hex(random_bytes(16));
        $pending = array_values(array_filter($pending, static function ($item) use ($email) {
            return !is_array($item) || ($item['email'] ?? '') !== $email;
        }));
        $pending[] = [
            'email' => $email,
            'token' => $token,
            'created_at' => time(),
            'prefs' => !empty($prefsAvailableKeys) ? $prefs : $prefsDefault,
        ];
        try {
            mailing_save_pending($pending);
            mailing_send_subscribe_confirmation($config, $email, $token, $publicBaseUrl);
            $message = 'Hemos enviado un email para confirmar tus cambios.';
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

$prefsOptions = [
    'avisos' => 'Avisos',
    'podcast' => 'Podcast',
    'newsletter' => 'Newsletter',
];

ob_start();
?>
<section class="postal-page">
    <div class="postal-hero">
        <div class="postal-hero__content">
            <span class="postal-hero__badge"><?= htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <h1><?= htmlspecialchars($pageLabel, ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars($pageIntro, ENT_QUOTES, 'UTF-8') ?></p>
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

    <?php if ($hasAnySubscription): ?>
        <div class="postal-grid-shell">
            <div class="postal-card">
                <h2><?= htmlspecialchars($subscribeTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($subscribeCopy, ENT_QUOTES, 'UTF-8') ?></p>
                <form method="post" class="postal-form">
                    <input type="hidden" name="subscription_action" value="subscribe">
                    <label>
                        <span>Email</span>
                        <input type="email" name="subscriber_email" required>
                    </label>
                    <?php if (!empty($prefsAvailableKeys)): ?>
                        <div class="postal-form__choices">
                            <?php foreach ($prefsOptions as $key => $label): ?>
                                <?php if (in_array($key, $prefsAvailableKeys, true)): ?>
                                    <?php
                                    $isChecked = $key === 'avisos'
                                        ? (!empty($prefsDefault['posts']) || !empty($prefsDefault['itineraries']))
                                        : ($key === 'podcast' ? !empty($prefsDefault['podcast']) : !empty($prefsDefault['newsletter']));
                                    ?>
                                    <label class="postal-check">
                                        <input type="checkbox" name="subscription_prefs[]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $isChecked ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="postal-btn postal-btn--primary">Suscribirme</button>
                </form>
            </div>
            <div class="postal-card">
                <h2><?= htmlspecialchars($updateTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($updateCopy, ENT_QUOTES, 'UTF-8') ?></p>
                <form method="post" class="postal-form">
                    <input type="hidden" name="subscription_action" value="update">
                    <label>
                        <span>Email</span>
                        <input type="email" name="subscriber_email" required>
                    </label>
                    <?php if (!empty($prefsAvailableKeys)): ?>
                        <div class="postal-form__choices">
                            <?php foreach ($prefsOptions as $key => $label): ?>
                                <?php if (in_array($key, $prefsAvailableKeys, true)): ?>
                                    <?php
                                    $isChecked = $key === 'avisos'
                                        ? (!empty($prefsDefault['posts']) || !empty($prefsDefault['itineraries']))
                                        : ($key === 'podcast' ? !empty($prefsDefault['podcast']) : !empty($prefsDefault['newsletter']));
                                    ?>
                                    <label class="postal-check">
                                        <input type="checkbox" name="subscription_prefs[]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $isChecked ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="postal-btn postal-btn--primary">Actualizar preferencias</button>
                </form>
            </div>
            <div class="postal-card postal-card--aside">
                <h2><?= htmlspecialchars($unsubscribeTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($unsubscribeCopy, ENT_QUOTES, 'UTF-8') ?></p>
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
    <?php else: ?>
        <div class="postal-card">
            <h2>Avisos y newsletters no configurados</h2>
            <p>El administrador del blog no ha configurado todavia ni el sistema de avisos ni las newsletters ni los avisos de podcast.</p>
        </div>
    <?php endif; ?>
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
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
    .postal-form__choices {
        display: grid;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }
    .postal-check {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-weight: 600;
        color: <?= $theme['colors']['text'] ?? '#222222' ?>;
    }
    .postal-check input {
        width: 16px;
        height: 16px;
        accent-color: <?= $theme['colors']['accent'] ?? '#0a4c8a' ?>;
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
    'Title' => $pageLabel,
    'Description' => $pageIntro,
    'Template' => 'page',
], '');

$pageContent = $renderer->render('single', [
    'pageTitle' => $pageLabel,
    'post' => $postalPost,
    'htmlContent' => $content,
    'autoTocHtml' => '',
]);

echo $renderer->render('layout', [
    'pageTitle' => $pageLabel,
    'metaDescription' => $pageIntro,
    'content' => $pageContent,
    'jsonLd' => [$siteJsonLd, $orgJsonLd],
    'pageLang' => $siteLang,
    'showLogo' => true,
]);
