<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/postal.php';

use Nammu\Core\ContentRepository;
use Nammu\Core\MarkdownConverter;
use Nammu\Core\TemplateRenderer;

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

$postalEnabled = ($config['postal']['enabled'] ?? 'off') === 'on';
$postalUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/correos.php';
$postalLogoSvg = nammu_postal_icon_svg();
$itineraryItems = is_dir(__DIR__ . '/itinerarios') ? glob(__DIR__ . '/itinerarios/*') : [];
$hasItineraries = !empty($itineraryItems);
$hasPodcast = !empty(nammu_collect_podcast_items(__DIR__ . '/content', $publicBaseUrl));
$footerLinks = nammu_build_footer_links($config, $theme, $homeUrl, $postalUrl, $hasItineraries, $hasPodcast);

$entries = postal_load_entries();
$tokens = postal_prune_reset_tokens(postal_load_reset_tokens());
try {
    postal_save_reset_tokens($tokens);
} catch (Throwable $e) {
    // ignore cleanup errors
}

$email = postal_normalize_email($_GET['email'] ?? '');
$token = trim((string) ($_GET['token'] ?? ''));
$message = '';
$messageType = 'info';
$validToken = false;
$resetDone = false;

if ($email === '' || $token === '') {
    $message = 'El enlace de recuperacion no es valido.';
    $messageType = 'danger';
} else {
    foreach ($tokens as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (($item['email'] ?? '') === $email && ($item['token'] ?? '') === $token) {
            $validToken = true;
            break;
        }
    }
    if (!$validToken) {
        $message = 'El enlace de recuperacion no es valido o ya ha caducado.';
        $messageType = 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['postal_reset_confirm'])) {
    $email = postal_normalize_email($_POST['reset_email'] ?? '');
    $token = trim((string) ($_POST['reset_token'] ?? ''));
    $password = trim((string) ($_POST['reset_password'] ?? ''));
    $confirm = trim((string) ($_POST['reset_confirm'] ?? ''));

    if ($email === '' || $token === '') {
        $message = 'La solicitud no es valida.';
        $messageType = 'danger';
    } elseif ($password === '' || $confirm === '') {
        $message = 'Debes escribir la nueva contrasena.';
        $messageType = 'danger';
    } elseif ($password !== $confirm) {
        $message = 'Las contrasenas no coinciden.';
        $messageType = 'danger';
    } else {
        $validToken = false;
        foreach ($tokens as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item['email'] ?? '') === $email && ($item['token'] ?? '') === $token) {
                $validToken = true;
                break;
            }
        }
        $entry = postal_get_entry($email, $entries);
        if (!$validToken || !$entry) {
            $message = 'El enlace de recuperacion no es valido o ya ha caducado.';
            $messageType = 'danger';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $entries = postal_upsert_entry([
                    'email' => $email,
                    'name' => $entry['name'] ?? '',
                    'address' => $entry['address'] ?? '',
                    'city' => $entry['city'] ?? '',
                    'postal_code' => $entry['postal_code'] ?? '',
                    'region' => $entry['region'] ?? '',
                    'country' => $entry['country'] ?? '',
                ], $hash, $entries);
                postal_save_entries($entries);
                $tokens = array_values(array_filter($tokens, static function ($item) use ($email, $token) {
                    return !is_array($item) || ($item['email'] ?? '') !== $email || ($item['token'] ?? '') !== $token;
                }));
                postal_save_reset_tokens($tokens);
                $message = 'Contrasena actualizada correctamente. Ya puedes iniciar sesion.';
                $messageType = 'success';
                $resetDone = true;
            } catch (Throwable $e) {
                $message = 'No pudimos actualizar la contrasena.';
                $messageType = 'danger';
            }
        }
    }
}

ob_start();
?>
<section class="postal-reset">
    <div class="postal-reset-card">
        <h1>Restablecer contrasena</h1>
        <?php if ($message !== ''): ?>
            <div class="postal-reset-message postal-reset-message--<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if ($validToken && !$resetDone): ?>
            <form method="post" class="postal-reset-form">
                <input type="hidden" name="reset_email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="reset_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <label>
                    <span>Nueva contrasena</span>
                    <input type="password" name="reset_password" required>
                </label>
                <label>
                    <span>Confirmar contrasena</span>
                    <input type="password" name="reset_confirm" required>
                </label>
                <button type="submit" name="postal_reset_confirm" class="postal-reset-btn">Guardar contrasena</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<style>
    .postal-reset {
        display: flex;
        justify-content: center;
        padding: 1.5rem 0;
    }
    .postal-reset-card {
        width: min(520px, 100%);
        background: #fff;
        border-radius: 18px;
        border: 1px solid rgba(0,0,0,0.08);
        box-shadow: 0 18px 30px rgba(0,0,0,0.08);
        padding: 2rem;
    }
    .postal-reset-card h1 {
        margin: 0 0 1rem;
        color: <?= htmlspecialchars($theme['colors']['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8') ?>;
        font-size: 1.6rem;
    }
    .postal-reset-message {
        padding: 0.75rem 1rem;
        border-radius: 12px;
        margin-bottom: 1rem;
        font-size: 0.95rem;
    }
    .postal-reset-message--success {
        background: #ecfdf3;
        color: #18794e;
        border: 1px solid rgba(24,121,78,0.2);
    }
    .postal-reset-message--danger {
        background: #fff1f1;
        color: #b42318;
        border: 1px solid rgba(180,35,24,0.2);
    }
    .postal-reset-message--info {
        background: <?= htmlspecialchars($theme['colors']['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8') ?>;
        color: <?= htmlspecialchars($theme['colors']['text'] ?? '#222222', ENT_QUOTES, 'UTF-8') ?>;
        border: 1px solid rgba(0,0,0,0.08);
    }
    .postal-reset-form label {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        font-weight: 600;
        margin-bottom: 0.9rem;
        color: <?= htmlspecialchars($theme['colors']['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8') ?>;
    }
    .postal-reset-form input {
        border-radius: 12px;
        border: 1px solid rgba(0,0,0,0.12);
        padding: 0.75rem 0.9rem;
        font-size: 1rem;
    }
    .postal-reset-btn {
        width: 100%;
        border: none;
        border-radius: 14px;
        padding: 0.85rem 1rem;
        font-weight: 700;
        background: <?= htmlspecialchars($theme['colors']['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8') ?>;
        color: #fff;
        cursor: pointer;
    }
    .postal-reset-btn:hover {
        filter: brightness(0.95);
    }
</style>
<?php
$content = ob_get_clean();

$renderer = new TemplateRenderer(__DIR__ . '/template', [
    'siteTitle' => $displaySiteTitle,
    'siteDescription' => $siteDescription,
    'rssUrl' => $rssUrl !== '' ? $rssUrl : '/rss.xml',
    'baseUrl' => $homeUrl,
    'theme' => $theme,
]);
$renderer->setGlobal('postalEnabled', $postalEnabled);
$renderer->setGlobal('postalUrl', $postalUrl);
$renderer->setGlobal('postalLogoSvg', $postalLogoSvg);
$renderer->setGlobal('footerLinks', $footerLinks);
$renderer->setGlobal('hasItineraries', false);

$resetPost = new Nammu\Core\Post('correo-postal-reset', [
    'Title' => 'Restablecer contrasena',
    'Description' => 'Restablece la contrasena de tu cuenta postal.',
    'Template' => 'page',
], '');

$pageContent = $renderer->render('single', [
    'pageTitle' => 'Restablecer contrasena',
    'post' => $resetPost,
    'htmlContent' => $content,
    'autoTocHtml' => '',
]);

echo $renderer->render('layout', [
    'pageTitle' => 'Restablecer contrasena',
    'metaDescription' => 'Restablecer contrasena de correo postal.',
    'content' => $pageContent,
    'showLogo' => true,
]);
