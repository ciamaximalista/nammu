<?php
/**
 * @var string $siteTitle
 * @var string $siteDescription
 * @var string $pageTitle
 * @var string $metaDescription
 * @var string $content
 * @var string $rssUrl
 * @var array $theme
 */
$themeFonts = $theme['fonts'] ?? [];
$themeColors = $theme['colors'] ?? [];
$titleFont = htmlspecialchars($themeFonts['title'] ?? 'Gabarito', ENT_QUOTES, 'UTF-8');
$bodyFont = htmlspecialchars($themeFonts['body'] ?? 'Roboto', ENT_QUOTES, 'UTF-8');
$codeFont = htmlspecialchars($themeFonts['code'] ?? 'VT323', ENT_QUOTES, 'UTF-8');
$quoteFont = htmlspecialchars($themeFonts['quote'] ?? 'Castoro', ENT_QUOTES, 'UTF-8');
$fontLink = $theme['fontUrl'] ?? null;
$colorBackground = htmlspecialchars($themeColors['background'] ?? '#ffffff', ENT_QUOTES, 'UTF-8');
$colorText = htmlspecialchars($themeColors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
$colorH1 = htmlspecialchars($themeColors['h1'] ?? '#1b8eed', ENT_QUOTES, 'UTF-8');
$colorH2 = htmlspecialchars($themeColors['h2'] ?? '#ea2f28', ENT_QUOTES, 'UTF-8');
$colorH3 = htmlspecialchars($themeColors['h3'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$colorHighlight = htmlspecialchars($themeColors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$colorAccent = htmlspecialchars($themeColors['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8');
$colorBrand = htmlspecialchars($themeColors['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$colorCodeBackground = htmlspecialchars($themeColors['code_background'] ?? '#000000', ENT_QUOTES, 'UTF-8');
$colorCodeText = htmlspecialchars($themeColors['code_text'] ?? '#90ee90', ENT_QUOTES, 'UTF-8');
$footerHtml = $theme['footer_html'] ?? '';
$footerNammuEnabled = ($theme['footer_nammu'] ?? 'on') === 'on';
$layoutConfig = function_exists('nammu_load_config') ? nammu_load_config() : [];
if (!function_exists('nammu_webmention_endpoint_url') && is_file(dirname(__DIR__) . '/core/webmention.php')) {
    require_once dirname(__DIR__) . '/core/webmention.php';
}
$footerEuplEnabled = (($layoutConfig['eupl_notice'] ?? 'on') === 'on');
$footerLogoPosition = $theme['footer_logo'] ?? 'none';
if (!in_array($footerLogoPosition, ['none', 'top', 'bottom'], true)) {
    $footerLogoPosition = 'none';
}
$footerLinks = is_array($footerLinks ?? null) ? $footerLinks : [];
$logoUrl = $theme['logo_url'] ?? null;
$faviconUrl = $theme['favicon_url'] ?? null;
$showLogo = $showLogo ?? false;
$socialMeta = $socialMeta ?? [];
$jsonLd = $jsonLd ?? [];
$webmentionEndpoint = function_exists('nammu_webmention_endpoint_url') ? nammu_webmention_endpoint_url($layoutConfig) : '';
if ($webmentionEndpoint !== '' && !headers_sent()) {
    header('Link: <' . $webmentionEndpoint . '>; rel="webmention"', false);
}
$metaRobots = $metaRobots ?? '';
$themeGlobal = $theme['global'] ?? [];
$cornerStyle = $theme['corners'] ?? ($themeGlobal['corners'] ?? 'rounded');
$cornerClass = $cornerStyle === 'square' ? 'corners-square' : 'corners-rounded';
$searchSettings = $theme['search'] ?? [];
$searchFloatingEnabled = ($searchSettings['floating'] ?? 'off') === 'on';
$fediverseFloatingCtaEnabled = ($searchSettings['fediverse_floating_cta'] ?? 'on') === 'on';
$subscriptionSettings = $theme['subscription'] ?? [];
$subscriptionFloatingEnabled = ($subscriptionSettings['floating'] ?? 'off') === 'on';
$baseHref = $baseUrl ?? '/';
$searchBaseNormalized = rtrim($baseHref === '' ? '/' : $baseHref, '/');
$floatingSearchAction = $searchBaseNormalized === '' ? '/buscar.php' : $searchBaseNormalized . '/buscar.php';
$floatingCategoriesUrl = $searchBaseNormalized === '' ? '/categorias' : $searchBaseNormalized . '/categorias';
$showFloatingSearch = $searchFloatingEnabled;
$floatingSubscriptionAction = $searchBaseNormalized === '' ? '/subscribe.php' : $searchBaseNormalized . '/subscribe.php';
$avisosUrl = $searchBaseNormalized === '' ? '/avisos' : $searchBaseNormalized . '/avisos';
$showFloatingSubscription = $subscriptionFloatingEnabled;
$hasNewsletters = !empty($hasNewsletters ?? ($GLOBALS['hasNewsletters'] ?? false));
$subscriptionMenuLabel = function_exists('nammu_public_subscription_menu_label')
    ? nammu_public_subscription_menu_label($hasNewsletters)
    : ($hasNewsletters ? 'Suscripción a Avisos y Newsletter' : 'Suscripción a Avisos');
$postalEnabled = $postalEnabled ?? false;
$postalUrl = $postalUrl ?? '/correos';
$postalLogoSvg = $postalLogoSvg ?? '';
$hasCategories = !empty($hasCategories);
$hasItineraries = $hasItineraries ?? false;
$itinerariesIndexUrl = $itinerariesIndexUrl ?? ($searchBaseNormalized === '' ? '/itinerarios' : $searchBaseNormalized . '/itinerarios');
$hasPodcast = !empty($hasPodcast);
$podcastIndexUrl = $podcastIndexUrl ?? ($searchBaseNormalized === '' ? '/podcast' : $searchBaseNormalized . '/podcast');
if ($postalLogoSvg === '' && function_exists('nammu_postal_icon_svg')) {
    $postalLogoSvg = nammu_postal_icon_svg();
}
$contactSettings = function_exists('nammu_contact_settings') ? nammu_contact_settings() : [];
$contactFooterItems = [];
if (!empty($contactSettings['footer']) && function_exists('nammu_contact_footer_items')) {
    $contactFooterItems = nammu_contact_footer_items($contactSettings);
}
$hasFooterLogo = $footerLogoPosition !== 'none' && !empty($logoUrl);
$showFooterBlock = ($footerHtml !== '') || $hasFooterLogo || $footerNammuEnabled || !empty($contactFooterItems);
$currentUrl = ($baseHref ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
$defaultMetaDescription = '';
if (function_exists('nammu_social_settings')) {
    $socialSettings = nammu_social_settings();
    $defaultMetaDescription = trim((string) ($socialSettings['default_description'] ?? ''));
}
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isCrawler = $userAgent !== '' && (
    function_exists('nammu_is_crawler_user_agent')
        ? nammu_is_crawler_user_agent($userAgent)
        : (bool) preg_match('/\b(bot|crawl(?:er)?|spider)\b/i', $userAgent)
);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$basePath = parse_url($baseHref, PHP_URL_PATH) ?? '/';
$normalizedBase = rtrim($basePath, '/');
$normalizedRequest = rtrim($requestPath, '/');
if ($normalizedBase === '') {
    $normalizedBase = '/';
}
if ($normalizedRequest === '') {
    $normalizedRequest = '/';
}
$canonicalHref = '';
if (!empty($socialMeta['canonical'])) {
    $canonicalHref = (string) $socialMeta['canonical'];
} else {
    $baseForCanonical = $baseHref !== '' ? rtrim($baseHref, '/') : '';
    $canonicalHref = $baseForCanonical . $requestPath;
}
$isHome = ($normalizedRequest === $normalizedBase) || ($normalizedRequest === $normalizedBase . '/index.php');
$adsSettings = is_array($adsSettings ?? null) ? $adsSettings : (function_exists('nammu_ads_settings') ? nammu_ads_settings() : []);
$adsEnabled = ($adsSettings['enabled'] ?? 'off') === 'on';
$adsScope = $adsSettings['scope'] ?? 'home';
$adsText = trim((string) ($adsSettings['text'] ?? ''));
$adsImage = trim((string) ($adsSettings['image'] ?? ''));
$adsLink = trim((string) ($adsSettings['link'] ?? ''));
$adsLinkLabel = trim((string) ($adsSettings['link_label'] ?? ''));
$adsImageUrl = $adsImage !== '' && function_exists('nammu_resolve_asset')
    ? (nammu_resolve_asset($adsImage, $baseHref) ?? '')
    : '';
$adsHtml = '';
if ($adsText !== '') {
    if (strpos($adsText, '<') !== false) {
        $adsHtml = $adsText;
    } else {
        $adsHtml = htmlspecialchars($adsText, ENT_QUOTES, 'UTF-8');
    }
}
$adsTitleHtml = '';
$adsFooterLinkHtml = '';
$adsLinkHref = '';
if ($adsLink !== '' && $adsLinkLabel !== '') {
    $adsLinkHref = htmlspecialchars($adsLink, ENT_QUOTES, 'UTF-8');
    $adsTitleHtml = '<a href="' . $adsLinkHref . '" class="nammu-ad-link" data-ad-link>' . htmlspecialchars($adsLinkLabel, ENT_QUOTES, 'UTF-8') . '</a>';
    $adsFooterLinkHtml = '<a href="' . $adsLinkHref . '" class="nammu-ad-link" data-ad-link>Visita ' . htmlspecialchars($adsLinkLabel, ENT_QUOTES, 'UTF-8') . '</a>';
}
$adsClosedToday = false; // El cierre del anuncio se recuerda en localStorage, desde el JS del banner.
$showAdsBanner = $adsEnabled && $adsHtml !== '' && !$adsClosedToday && !$isCrawler;
if ($adsScope === 'home' && !$isHome) {
    $showAdsBanner = false;
}
$pushEnabled = ($adsSettings['push_enabled'] ?? 'off') === 'on';
$pushPublicKey = function_exists('nammu_push_public_key') ? nammu_push_public_key() : '';
$pushSubscribeUrl = $searchBaseNormalized === '' ? '/push-subscribe.php' : $searchBaseNormalized . '/push-subscribe.php';
$pushUnsubscribeUrl = $searchBaseNormalized === '' ? '/push-unsubscribe.php' : $searchBaseNormalized . '/push-unsubscribe.php';
$showPushPrompt = $pushEnabled && $pushPublicKey !== '' && !$isCrawler;
$fediverseCtaHandle = '';
$fediverseCtaUrl = '';
$fediverseProfileUrl = '';
$fediverseCtaIcon = function_exists('nammu_fediverse_glyph_svg') ? nammu_fediverse_glyph_svg(24) : '';
if ($fediverseFloatingCtaEnabled && function_exists('nammu_fediverse_actor_url') && function_exists('nammu_fediverse_acct_uri')) {
    $fediverseResolvedConfig = $fediverseConfig ?? (function_exists('nammu_load_config') ? nammu_load_config() : []);
    $fediverseCtaUrl = nammu_fediverse_actor_url($fediverseResolvedConfig);
    $fediverseAcctUri = nammu_fediverse_acct_uri($fediverseResolvedConfig);
    $fediverseCtaHandle = str_starts_with($fediverseAcctUri, 'acct:') ? '@' . substr($fediverseAcctUri, 5) : $fediverseAcctUri;
    if (function_exists('nammu_fediverse_profile_page_url')) {
        $fediverseProfileUrl = nammu_fediverse_profile_page_url($fediverseResolvedConfig);
    }
}
$serverDay = date('Y-m-d');
$serverDayExpires = date(DATE_RFC2822, strtotime('today 23:59:59'));
$contentOutput = $content;
// Estadísticas sin cookies: los bots se cuentan aquí por su User-Agent; las visitas humanas las envía el
// navegador por el beacon (ver el script al final) con un descriptor firmado de la página vista.
$statsBeaconDescriptor = '';
$statsBeaconUrl = '';
if ($isCrawler) {
    if (function_exists('nammu_record_bot_visit')) {
        nammu_record_bot_visit($userAgent);
    }
} elseif (function_exists('nammu_stats_beacon_descriptor')) {
    $pendingPageview = function_exists('nammu_stats_pending_pageview') ? nammu_stats_pending_pageview() : null;
    $statsBeaconDescriptor = nammu_stats_beacon_descriptor(
        (string) ($pendingPageview['type'] ?? ''),
        (string) ($pendingPageview['slug'] ?? ''),
        (string) ($pendingPageview['title'] ?? '')
    );
    $statsBeaconUrl = ($searchBaseNormalized === '' ? '' : $searchBaseNormalized) . nammu_stats_beacon_path();
}
if (function_exists('nammu_expire_legacy_cookies')) {
    nammu_expire_legacy_cookies();
}
if (!empty($theme['lang'])) {
    $pageLang = $theme['lang'];
}
$pageLang = $pageLang ?? 'es';
$pageLang = htmlspecialchars($pageLang, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="<?= $pageLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle !== '' ? "{$pageTitle} — {$siteTitle}" : $siteTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?php
        $finalMetaDescription = $defaultMetaDescription !== '' ? $defaultMetaDescription : ($siteDescription ?? '');
        if ($finalMetaDescription === '') {
            $finalMetaDescription = $metaDescription ?? '';
        }
    ?>
    <?php if ($finalMetaDescription !== ''): ?>
        <meta name="description" content="<?= htmlspecialchars($finalMetaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($metaRobots !== ''): ?>
        <meta name="robots" content="<?= htmlspecialchars($metaRobots, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php $headRssLinks = is_array($rssLinks ?? null) ? $rssLinks : [[
        'title' => $siteTitle . ' — RSS del sitio',
        'href' => $rssUrl,
    ]]; ?>
    <?php foreach ($headRssLinks as $headRssLink): ?>
        <?php
            $headRssHref = trim((string) ($headRssLink['href'] ?? ''));
            $headRssTitle = trim((string) (($headRssLink['title'] ?? '') ?: (($headRssLink['label'] ?? '') ?: 'RSS')));
        ?>
        <?php if ($headRssHref !== ''): ?>
            <link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars($headRssTitle, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($headRssHref, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($webmentionEndpoint !== ''): ?>
        <link rel="webmention" href="<?= htmlspecialchars($webmentionEndpoint, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (function_exists('nammu_fediverse_actor_url') && function_exists('nammu_fediverse_acct_uri') && function_exists('nammu_fediverse_base_url')): ?>
        <?php $fediverseConfig = function_exists('nammu_load_config') ? nammu_load_config() : []; ?>
        <link rel="alternate" type="application/activity+json" title="<?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?> — ActivityPub" href="<?= htmlspecialchars(nammu_fediverse_actor_url($fediverseConfig), ENT_QUOTES, 'UTF-8') ?>">
        <link rel="alternate" type="application/jrd+json" title="<?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?> — WebFinger" href="<?= htmlspecialchars(nammu_fediverse_base_url($fediverseConfig) . '/.well-known/webfinger?resource=' . rawurlencode(nammu_fediverse_acct_uri($fediverseConfig)), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($fontLink): ?>
        <?php if (strpos($fontLink, 'fonts.googleapis.com') !== false): ?>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <?php endif; ?>
        <link rel="preload" as="style" href="<?= htmlspecialchars($fontLink, ENT_QUOTES, 'UTF-8') ?>">
        <link rel="stylesheet" href="<?= htmlspecialchars($fontLink, ENT_QUOTES, 'UTF-8') ?>" media="print" onload="this.media='all'">
        <noscript><link rel="stylesheet" href="<?= htmlspecialchars($fontLink, ENT_QUOTES, 'UTF-8') ?>"></noscript>
    <?php endif; ?>
    <?php if ($faviconUrl): ?>
        <link rel="icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($canonicalHref !== ''): ?>
        <link rel="canonical" href="<?= htmlspecialchars($canonicalHref, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php foreach (($socialMeta['properties'] ?? []) as $property => $value): ?>
        <?php if ($value !== '' && $value !== null): ?>
            <meta property="<?= htmlspecialchars($property, ENT_QUOTES, 'UTF-8') ?>" content="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <?php foreach (($socialMeta['names'] ?? []) as $name => $value): ?>
        <?php if ($value !== '' && $value !== null): ?>
            <meta name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" content="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!empty($jsonLd)): ?>
        <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <style>
        :root {
            --nammu-radius-lg: 18px;
            --nammu-radius-md: 12px;
            --nammu-radius-sm: 8px;
            --nammu-radius-pill: 999px;
        }
        body.corners-square {
            --nammu-radius-lg: 0;
            --nammu-radius-md: 0;
            --nammu-radius-sm: 0;
            --nammu-radius-pill: 0;
        }
        body {
            margin: 8px 0;
            padding: 0;
            background-color: <?= $colorBackground ?>;
            color: <?= $colorText ?>;
            font-family: "<?= $bodyFont ?>", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.6;
        }
        .wrapper {
            max-width: min(960px, 92vw);
            margin: 0 auto;
            background: #fff;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
            border-radius: var(--nammu-radius-lg);
        }
        main {
            flex: 1 1 auto;
        }
        a {
            color: <?= $colorAccent ?>;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
            color: <?= $colorAccent ?>;
        }
        .nammu-image-vignette {
            float: right;
            width: 33%;
            max-width: 33%;
            height: auto;
            margin: 0 0 1rem 1.25rem;
            display: block;
        }
        .nammu-inline-gallery {
            margin: 2rem 0;
            border: 1px solid <?= $colorHighlight ?>;
            border-radius: var(--nammu-radius-lg);
            background: <?= $colorBackground ?>;
            overflow: hidden;
        }
        .nammu-inline-gallery summary {
            cursor: pointer;
            list-style: none;
            padding: 0.95rem 1.15rem;
            font-weight: 700;
            color: <?= $colorAccent ?>;
            background: <?= $colorHighlight ?>;
        }
        .nammu-inline-gallery summary::-webkit-details-marker {
            display: none;
        }
        .nammu-inline-gallery summary::after {
            content: '▾';
            float: right;
            transition: transform 0.2s ease;
        }
        .nammu-inline-gallery[open] summary::after {
            transform: rotate(180deg);
        }
        .nammu-inline-gallery__grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.85rem;
            padding: 1rem;
        }
        .nammu-inline-gallery__link {
            display: block;
            border-radius: var(--nammu-radius-md);
            overflow: hidden;
            background: <?= $colorHighlight ?>;
        }
        .nammu-inline-gallery__link:hover {
            text-decoration: none;
        }
        .nammu-inline-gallery__image {
            display: block;
            width: 100%;
            height: 210px;
            object-fit: cover;
        }
        @media (max-width: 640px) {
            .nammu-image-vignette {
                float: none;
                width: 100%;
                max-width: 100%;
                margin: 0 0 1rem 0;
            }
            .nammu-inline-gallery__grid {
                grid-template-columns: 1fr;
            }
            .nammu-inline-gallery__image {
                height: 190px;
            }
        }
        @media (min-width: 641px) and (max-width: 860px) {
            .nammu-inline-gallery__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .site-header-buttons {
            --nammu-header-button-accent: <?= $colorAccent ?>;
            display: flex;
            flex-wrap: nowrap;
            gap: 6px;
            align-items: center;
            justify-content: center;
            margin: 0.5rem auto 0.9rem;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            padding: 8px 0;
        }
        .site-header-button-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: var(--nammu-header-button-accent);
            border: 1px solid var(--nammu-header-button-accent);
            color: #fff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .site-header-button-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
            color: #fff;
            text-decoration: none;
        }
        .site-header-button-link svg {
            width: 14px;
            height: 14px;
        }
        .site-header-button-link--fediverse svg {
            width: 22px;
            height: 22px;
            color: #ffffff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            overflow: hidden;
            border-radius: var(--nammu-radius-md);
        }
        table thead th {
            background: <?= $colorAccent ?>;
            color: #fff;
            font-weight: 600;
        }
        table th,
        table td {
            padding: 0.75rem 0.85rem;
            border: 1px solid <?= $colorHighlight ?>;
            font-size: 0.8rem;
        }
        table tbody tr:nth-child(odd) {
            background: <?= $colorBackground ?>;
        }
        table tbody tr:nth-child(even) {
            background: <?= $colorHighlight ?>;
        }
        table tbody tr:hover {
            background: <?= $colorHighlight ?>;
        }
        .callout-box {
            background: linear-gradient(135deg, <?= $colorHighlight ?> 0%, <?= $colorBackground ?> 100%);
            border: 1px solid <?= $colorAccent ?>33;
            border-radius: var(--nammu-radius-lg);
            padding: 1.4rem 1.6rem;
            margin: 1.75rem auto;
            max-width: 860px;
            box-shadow: 0 16px 40px rgba(0,0,0,0.07);
            position: relative;
            overflow: hidden;
        }
        .callout-box::before {
            content: '';
            position: absolute;
            inset: 0;
            border: 2px solid <?= $colorAccent ?>33;
            border-radius: var(--nammu-radius-lg);
            pointer-events: none;
        }
        .callout-box h4 {
            margin: 0 0 0.5rem;
            color: <?= $colorAccent ?>;
            font-weight: 800;
            letter-spacing: 0.01em;
        }
        .callout-box p {
            margin: 0 0 0.4rem;
            color: <?= $colorText ?>;
        }
        .callout-box p:last-child {
            margin-bottom: 0;
        }
        .embedded-video,
        .embedded-pdf {
            margin: 2rem auto;
            text-align: center;
            width: 100%;
            max-width: 1200px;
            position: relative;
            --pdf-aspect: 1.414;
            overflow: hidden;
            box-sizing: border-box;
        }
        .embedded-video {
            background: transparent;
            padding: 0;
        }
        .embedded-pdf {
            background: #000;
            padding: 0.5rem;
        }
        .embedded-pdf__actions {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.82rem;
        }
        .embedded-pdf__action {
            color: <?= $colorAccent ?>;
            text-decoration: none;
            font-weight: 600;
        }
        .embedded-pdf__action + .embedded-pdf__action::before {
            content: '|';
            margin: 0 0.35rem 0 0;
            color: <?= $colorAccent ?>;
            opacity: 0.8;
        }
        .embedded-pdf__action:hover {
            text-decoration: underline;
        }
        .embedded-video video,
        .embedded-video iframe,
        .embedded-pdf iframe {
            display: block;
            width: 100%;
            max-width: 100%;
            border: none;
            border-radius: var(--nammu-radius-md);
            margin: 0 auto;
        }
        .embedded-video video,
        .embedded-video iframe {
            background: transparent;
        }
        .embedded-video video,
        .embedded-video iframe {
            aspect-ratio: 16 / 9;
        }
        .embedded-pdf iframe {
            background: <?= $colorHighlight ?>;
            height: auto;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: "<?= $titleFont ?>", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        h2 {
            color: <?= $colorH2 ?>;
        }
        h3 {
            color: <?= $colorH3 ?>;
        }
        strong,
        b {
            color: <?= $colorAccent ?>;
        }
        pre,
        code {
            background-color: <?= $colorCodeBackground ?>;
            color: <?= $colorCodeText ?>;
            font-family: "<?= $codeFont ?>", "Fira Code", "Source Code Pro", "Courier New", monospace;
        }
        pre {
            padding: 1rem 1.25rem;
            border-radius: var(--nammu-radius-md);
            overflow: auto;
            line-height: 1.45;
            margin: 1.5rem 0;
        }
        code {
            padding: 0.1rem 0.35rem;
            border-radius: var(--nammu-radius-sm);
            font-size: 0.95em;
        }
        pre code {
            background: transparent;
            color: inherit;
            padding: 0;
        }
        blockquote {
            margin: 2rem auto;
            padding: 1.5rem 1.75rem;
            border-left: 4px solid <?= $colorAccent ?>;
            background: <?= $colorHighlight ?>;
            border-radius: var(--nammu-radius-md);
            font-family: "<?= $quoteFont ?>", "Georgia", serif;
            font-style: italic;
            color: <?= $colorText ?>;
        }
        blockquote p {
            margin: 0 0 0.85rem 0;
        }
        blockquote p:last-child {
            margin-bottom: 0;
        }
        .post-brand {
            color: <?= $colorBrand ?>;
        }
        footer {
            margin-top: 3rem;
            font-size: 0.9rem;
            color: <?= $colorText ?>;
        }
        .highlight-block {
            background-color: <?= $colorHighlight ?>;
        }
        .site-footer-block {
            margin: 0 0 1.5rem 0;
            padding: 1.25rem 1.5rem;
            border-radius: var(--nammu-radius-md);
            background: <?= $colorHighlight ?>;
            color: <?= $colorText ?>;
            font-size: 0.85rem;
            text-align: center;
            gap: 0.5rem;
        }
        .site-footer-block p {
            margin: 0;
        }
        .site-footer-block a {
            color: <?= $colorAccent ?>;
        }
        .footer-logo-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .footer-logo-wrapper.footer-logo-top {
            margin-bottom: 1.2rem;
        }
        .footer-logo-wrapper.footer-logo-bottom {
            margin-top: 1.2rem;
        }
        .footer-social-links {
            margin-top: 2rem;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .footer-social-link {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: #ffffff;
            color: <?= $colorAccent ?>;
            border: 1px solid rgba(0,0,0,0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .footer-social-link:hover {
            background: <?= $colorHighlight ?>;
            transform: translateY(-1px);
            text-decoration: none;
        }
        .footer-social-link svg {
            width: 18px;
            height: 18px;
            display: block;
        }
        .footer-social-link--fediverse svg {
            --nammu-fediverse-bg: #ffffff;
            --nammu-fediverse-fg: <?= $colorAccent ?>;
            width: 24px;
            height: 24px;
        }
        .footer-contact-block {
            margin: 0 0 1.5rem 0;
            padding: 1rem 1.5rem;
            border-radius: var(--nammu-radius-md);
            background: <?= $colorHighlight ?>;
            color: <?= $colorText ?>;
            text-align: center;
        }
        .footer-contact-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            color: <?= $colorH2 ?>;
            margin-bottom: 0.75rem;
        }
        .footer-contact-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .footer-contact-link {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: #ffffff;
            color: <?= $colorAccent ?>;
            border: 1px solid rgba(0,0,0,0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .footer-contact-link:hover {
            background: <?= $colorHighlight ?>;
            transform: translateY(-1px);
            text-decoration: none;
        }
        .footer-contact-link svg {
            width: 18px;
            height: 18px;
            display: block;
        }
        .footer-logo-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .footer-logo-link img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .footer-logo-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(0,0,0,0.18);
        }
        .footer-html-content {
            max-width: min(760px, 100%);
            margin: 0 auto;
        }
        .footer-html-content p:last-child {
            margin-bottom: 0;
        }
        .footer-nammu-link {
            margin: 0.6rem 0 0.2rem;
            font-size: 0.7rem;
            color: <?= $colorText ?>;
            opacity: 0.75;
            max-width: min(760px, 100%);
            margin-left: auto;
            margin-right: auto;
            overflow: hidden;
            line-height: 1.55;
            text-align: center;
        }
        .footer-nammu-link a {
            color: <?= $colorAccent ?>;
        }
        .footer-nammu-link__eupl {
            width: 68px;
            max-width: 22vw;
            margin: 0.45rem auto 0;
            display: block;
        }
        .footer-nammu-link__eupl img {
            width: 100%;
            height: auto;
            display: block;
        }
        .floating-logo {
            position: fixed;
            top: 2.5rem;
            right: clamp(1.5rem, 5vw, 2.5rem);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
            background: #ffffff;
            display: block;
            z-index: 1100;
        }
        .floating-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .floating-stack {
            position: fixed;
            top: calc(2.5rem + 48px + 0.9rem);
            right: clamp(1.2rem, 4vw, 2rem);
            width: clamp(190px, 20vw, 230px);
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            pointer-events: auto;
        }
        .floating-search {
            position: static;
            width: 100%;
            background: rgba(255, 255, 255, 0.96);
            border-radius: var(--nammu-radius-md);
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 14px 26px rgba(0,0,0,0.12);
            padding: 0.3rem 0.4rem;
            backdrop-filter: blur(6px);
        }
        .floating-fediverse {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            background: linear-gradient(135deg, rgba(255,255,255,0.98), <?= $colorHighlight ?>);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: var(--nammu-radius-md);
            box-shadow: 0 14px 26px rgba(0,0,0,0.12);
            padding: 0.7rem 0.8rem;
            backdrop-filter: blur(6px);
        }
        .floating-fediverse__button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            min-height: 38px;
            padding: 0.55rem 0.8rem;
            border-radius: 12px;
            background: <?= $colorAccent ?>;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            line-height: 1.2;
        }
        .floating-fediverse__button svg {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            color: #ffffff;
        }
        .floating-fediverse__button:hover {
            color: #fff;
            text-decoration: none;
            filter: brightness(0.96);
        }
        .fediverse-follow-dialog {
            border: none;
            border-radius: 18px;
            padding: 0;
            width: min(92vw, 420px);
            max-width: 420px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.22);
        }
        .fediverse-follow-dialog::backdrop {
            background: rgba(0, 0, 0, 0.45);
        }
        .fediverse-follow-dialog__card {
            padding: 1.1rem 1.15rem 1rem 1.15rem;
            background: #fff;
        }
        .fediverse-follow-dialog__card h2 {
            margin: 0 0 0.55rem 0;
            font-size: 1.15rem;
            color: <?= $colorAccent ?>;
        }
        .fediverse-follow-dialog__card p {
            margin: 0 0 0.7rem 0;
            line-height: 1.5;
            color: <?= $colorText ?>;
        }
        .fediverse-follow-dialog__handle {
            display: block;
            margin: 0.4rem 0 0.8rem 0;
            padding: 0.75rem 0.85rem;
            border-radius: 12px;
            background: <?= $colorCodeBackground ?>;
            border: 1px solid rgba(0,0,0,0.12);
            color: <?= $colorCodeText ?>;
            font-weight: 700;
            text-align: center;
            word-break: break-word;
            font-size: 1rem;
            line-height: 1.45;
        }
        .fediverse-follow-dialog__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            justify-content: flex-end;
            margin-top: 0.9rem;
        }
        .fediverse-follow-dialog__actions a,
        .fediverse-follow-dialog__actions button {
            min-height: 36px;
            padding: 0.5rem 0.8rem;
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.08);
            background: #fff;
            color: <?= $colorAccent ?>;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        .fediverse-follow-dialog__actions button[data-fediverse-follow-copy] {
            background: <?= $colorAccent ?>;
            color: #fff;
            border-color: <?= $colorAccent ?>;
            font-weight: 700;
        }
        .fediverse-follow-dialog__actions a.fediverse-follow-dialog__primary {
            background: <?= $colorAccent ?>;
            color: #fff;
            border-color: <?= $colorAccent ?>;
        }
        .floating-search-form {
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }
        .floating-search-icon {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: <?= $colorHighlight ?>;
            border: 1px solid rgba(0,0,0,0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }
        .floating-search-icon svg {
            display: block;
        }
        .floating-search-form input[type="text"],
        .floating-search-form input[type="email"] {
            flex: 1 1 120px;
            min-width: 0;
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.12);
            padding: 0.1rem 0.2rem;
            font-size: 0.82rem;
            height: 26px;
            line-height: 1.2;
            background: transparent;
            color: <?= $colorText ?>;
        }
        .floating-search-form input[type="text"]::placeholder,
        .floating-search-form input[type="email"]::placeholder {
            color: rgba(0,0,0,0.5);
        }
        .floating-search-form input[type="text"]:focus,
        .floating-search-form input[type="email"]:focus {
            outline: none;
            border-color: <?= $colorAccent ?>;
        }
        .floating-search-form button {
            border: none;
            background: <?= $colorAccent ?>;
            width: 26px;
            height: 26px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex: 0 0 auto;
        }
        .floating-avisos-link {
            width: 26px;
            height: 26px;
            border-radius: 9px;
            background: <?= $colorHighlight ?>;
            border: 1px solid rgba(0,0,0,0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.2s ease, border-color 0.2s ease;
            flex: 0 0 auto;
            color: <?= $colorAccent ?>;
        }
        .floating-avisos-link:hover {
            background: rgba(0,0,0,0.08);
            border-color: rgba(0,0,0,0.12);
        }
        .floating-avisos-link svg {
            width: 13px;
            height: 13px;
            display: block;
        }
        .floating-search-form button svg {
            display: block;
        }
        .floating-search-categories,
        .floating-search-itineraries,
        .floating-search-podcast {
            width: 26px;
            height: 26px;
            border-radius: 9px;
            background: <?= $colorHighlight ?>;
            border: 1px solid rgba(0,0,0,0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.2s ease, border-color 0.2s ease;
            flex: 0 0 auto;
        }
        .floating-search-categories:hover,
        .floating-search-itineraries:hover,
        .floating-search-podcast:hover {
            background: rgba(0,0,0,0.08);
            border-color: rgba(0,0,0,0.12);
        }
        .floating-postal-link {
            width: 26px;
            height: 26px;
            border-radius: 9px;
            background: <?= $colorHighlight ?>;
            border: 1px solid rgba(0,0,0,0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.2s ease, border-color 0.2s ease;
            flex: 0 0 auto;
            color: <?= $colorAccent ?>;
        }
        .floating-postal-link:hover {
            background: rgba(0,0,0,0.08);
            border-color: rgba(0,0,0,0.12);
        }
        .floating-postal-link svg {
            width: 13px;
            height: 13px;
            display: block;
        }
        @media (max-width: 720px) {
            .wrapper {
                max-width: none;
                width: auto;
                margin-left: 8px;
                margin-right: 8px;
                padding: 1rem 0.75rem;
                box-sizing: border-box;
            }
            .floating-stack {
                top: auto;
                bottom: 1.2rem;
                right: 1rem;
                left: 1rem;
                width: auto;
                max-width: none;
            }
            .floating-logo {
                display: none;
            }
        }
        .floating-subscription input[type="email"] {
            flex: 1 1 auto;
            min-width: 0;
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.12);
            padding: 0.1rem 0.2rem;
            font-size: 0.82rem;
            height: 26px;
            line-height: 1.2;
            background: transparent;
            color: <?= $colorText ?>;
        }
        .floating-subscription input[type="email"]::placeholder {
            color: rgba(0,0,0,0.45);
        }
        .floating-subscription input[type="email"]:focus {
            outline: none;
            border-color: <?= $colorAccent ?>;
        }
        .floating-subscription .subscription-feedback {
            margin-top: 0.4rem;
            font-size: 0.85rem;
            background: <?= $colorHighlight ?>;
            color: <?= $colorText ?>;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: var(--nammu-radius-md);
            padding: 0.5rem 0.65rem;
        }
        .nammu-ad-banner {
            position: fixed;
            left: 1.5rem;
            right: 1.5rem;
            bottom: 1.5rem;
            margin: 0 auto;
            max-width: 980px;
            display: flex;
            align-items: stretch;
            gap: 0;
            background: linear-gradient(120deg, rgba(255,255,255,0.96), <?= $colorHighlight ?>);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 20px;
            box-shadow: 0 18px 30px rgba(0,0,0,0.18);
            overflow: hidden;
            z-index: 2050;
            backdrop-filter: blur(8px);
        }
        .nammu-ad-content {
            padding: 1.2rem 1.6rem;
            flex: 1 1 65%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.4rem;
        }
        .nammu-ad-text {
            font-size: 1rem;
            color: <?= $colorText ?>;
            line-height: 1.5;
        }
        .nammu-ad-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: <?= $colorAccent ?>;
        }
        .nammu-ad-cta {
            font-weight: 600;
            color: <?= $colorAccent ?>;
        }
        .nammu-ad-title a,
        .nammu-ad-cta a {
            color: inherit;
            text-decoration: underline;
        }
        .nammu-ad-text p {
            margin: 0;
        }
        .nammu-ad-text h1,
        .nammu-ad-text h2,
        .nammu-ad-text h3,
        .nammu-ad-text h4,
        .nammu-ad-text h5,
        .nammu-ad-text h6 {
            margin: 0;
        }
        .nammu-ad-text strong {
            color: <?= $colorAccent ?>;
        }
        .nammu-ad-image {
            flex: 0 0 32%;
            min-width: 150px;
            position: relative;
        }
        .nammu-ad-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .nammu-ad-close {
            position: absolute;
            top: 0.6rem;
            right: 0.6rem;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(0,0,0,0.65);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 2;
        }
        .nammu-ad-close svg {
            width: 14px;
            height: 14px;
        }
        .nammu-push-banner {
            position: fixed;
            left: 50%;
            transform: translateX(-50%);
            bottom: 1.5rem;
            width: min(560px, calc(100% - 32px));
            background: #ffffff;
            border-radius: 18px;
            padding: 16px 20px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18);
            display: none;
            align-items: center;
            gap: 16px;
            z-index: 3000;
        }
        .nammu-push-banner.is-visible {
            display: flex;
        }
        .nammu-push-banner.has-ad {
            bottom: 7.2rem;
        }
        .nammu-push-banner h3 {
            font-size: 1rem;
            margin: 0 0 6px;
            color: #1b1b1b;
        }
        .nammu-push-banner p {
            margin: 0;
            color: #5a6470;
            font-size: 0.9rem;
        }
        .nammu-push-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-left: auto;
        }
        .nammu-push-actions button {
            border-radius: 999px;
            padding: 6px 16px;
            border: 1px solid #1b8eed;
            background: #1b8eed;
            color: #ffffff;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .nammu-push-actions button.secondary {
            background: transparent;
            color: #1b8eed;
        }
        @media (max-width: 720px) {
            .nammu-ad-banner {
                left: 0.9rem;
                right: 0.9rem;
                bottom: 5.4rem;
                flex-direction: column;
            }
            .nammu-ad-image {
                width: 100%;
                min-height: 140px;
            }
            .nammu-push-banner {
                bottom: 5.4rem;
                border-radius: 16px;
                flex-direction: column;
                align-items: flex-start;
            }
            .nammu-push-banner.has-ad {
                bottom: 10.2rem;
            }
            .nammu-push-actions {
                margin-left: 0;
                width: 100%;
            }
        }
        .itinerary-single-content .post {
            gap: 1.5rem;
        }
        .itinerary-single-content .post-header {
            text-align: center;
        }
        .itinerary-single-content .post-brand {
            align-items: center;
            text-align: center;
        }
        .itinerary-single-content .post-intro,
        .itinerary-single-content .post-body {
            max-width: 100%;
            margin-left: 0;
            margin-right: 0;
        }
        .itinerary-single-content .post-body {
            margin: 0 0 1.5rem 0;
        }
        .itinerary-single-content .post-meta-band {
            margin: 0 auto 1rem;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.85rem 1.75rem;
            border-radius: var(--nammu-radius-pill);
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
            border: 1px solid transparent;
        }
        .button-primary {
            background: <?= $colorAccent ?>;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .button-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.18);
            text-decoration: none;
        }
        .button-secondary {
            background: transparent;
            color: <?= $colorAccent ?>;
            border-color: rgba(0,0,0,0.15);
        }
        .button-secondary:hover {
            background: <?= $colorHighlight ?>;
            text-decoration: none;
        }
        .itinerary-single-content .post-intro {
            margin: 1.5rem 0;
        }
        .itinerary-topics {
            margin: 3.5rem auto;
            max-width: min(960px, 100%);
        }
        .itinerary-topics__list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            column-gap: 1.5rem;
            row-gap: 3.5rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .itinerary-topic-card {
            background: <?= $colorHighlight ?>;
            border-radius: var(--nammu-radius-md);
            padding: 1.25rem;
            box-shadow: 0 6px 18px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .itinerary-topic-card__media {
            margin: -1.25rem -1.25rem 0.85rem;
            border-top-left-radius: var(--nammu-radius-md);
            border-top-right-radius: var(--nammu-radius-md);
            overflow: hidden;
        }
        .itinerary-topic-card__media img {
            width: 100%;
            height: 190px;
            object-fit: cover;
            display: block;
        }
        .itinerary-topic-card__number {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: <?= $colorBackground ?>;
            background: <?= $colorH1 ?>;
            display: block;
            padding: 0.35rem 1rem;
            margin: -2rem -1.25rem 0.65rem;
            box-shadow: 0 6px 14px rgba(0,0,0,0.18);
            border-radius: 0;
        }
        .itinerary-topic-card .post-meta-band {
            margin: 0 0 0.65rem 0;
        }
        .itinerary-topic-card__body h3 {
            margin: 0 0 0.65rem 0;
            font-size: 1.05rem;
        }
        .itinerary-topic-card__body p {
            margin: 0;
            color: <?= $colorText ?>;
        }
        .itinerary-topic-card__description {
            margin: 0;
            font-size: 0.95rem;
            color: <?= $colorText ?>;
        }
        .itinerary-topics__cta {
            margin-top: 4rem;
            text-align: center;
        }
        .itinerary-topics__cta .button {
            min-width: 220px;
        }
        .itinerary-usage-alert {
            margin-top: 2rem;
            padding: 1rem 1.25rem;
            border-radius: var(--nammu-radius-md);
            background: rgba(234, 47, 40, 0.08);
            border-left: 4px solid <?= $colorH2 ?>;
            font-size: 0.95rem;
            text-align: left;
            color: <?= $colorText ?>;
        }
        .itinerary-quiz {
            margin: 2.5rem auto;
            padding: 1.5rem;
            border-radius: var(--nammu-radius-md);
            border: 1px solid rgba(0,0,0,0.08);
            background: #fff;
            max-width: min(900px, 100%);
            box-shadow: 0 8px 24px rgba(0,0,0,0.05);
        }
        .itinerary-quiz__header h2 {
            margin-bottom: 0.5rem;
        }
        .itinerary-quiz__question {
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: var(--nammu-radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
            background: <?= $colorHighlight ?>;
        }
        .itinerary-quiz__answers {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .itinerary-quiz__answers li {
            margin-bottom: 0.5rem;
        }
        .itinerary-quiz__answers label {
            display: flex;
            gap: 0.6rem;
            cursor: pointer;
            align-items: flex-start;
        }
        .itinerary-quiz__answers input[type="checkbox"] {
            margin-top: 0.25rem;
        }
        .itinerary-quiz__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
        }
        .itinerary-quiz__result {
            font-weight: 600;
        }
        .itinerary-topic-cta__alert {
            margin-top: 1rem;
            color: <?= $colorH2 ?>;
            font-weight: 600;
        }
        .button-disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        .itinerary-topic-card__lock {
            display: none;
            margin-top: 0.5rem;
            font-size: 0.92rem;
            color: <?= $colorH2 ?>;
            font-weight: 600;
        }
        .itinerary-topic-card--locked {
            opacity: 0.85;
        }
        .itinerary-topic-card--locked .itinerary-topic-card__lock {
            display: block;
        }
        .itinerary-topic-card--locked [data-topic-link] {
            cursor: not-allowed;
        }
        [data-topic-link].is-disabled {
            pointer-events: none;
        }
        @media (max-width: 1024px) {
            .floating-logo {
                display: none;
            }
        }
    </style>
</head>
<?php
$baseHost = '';
if (!empty($baseUrl)) {
    $baseHost = parse_url((string) $baseUrl, PHP_URL_HOST) ?? '';
}
?>
<body class="<?= htmlspecialchars($cornerClass, ENT_QUOTES, 'UTF-8') ?>">
    <div class="wrapper">
        <main>
            <?= $contentOutput ?>
        </main>
        <?php if ($showFooterBlock): ?>
            <footer>
                <?php if ($footerHtml !== '' || $hasFooterLogo): ?>
                    <div class="site-footer-block">
                        <?php if ($hasFooterLogo && $footerLogoPosition === 'top'): ?>
                            <div class="footer-logo-wrapper footer-logo-top">
                                <a class="footer-logo-link" href="<?= htmlspecialchars($baseUrl ?? '/', ENT_QUOTES, 'UTF-8') ?>" aria-label="Ir a la portada">
                                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo del blog">
                                </a>
                            </div>
                            <?php if (!empty($footerLinks)): ?>
                                <div class="footer-social-links">
                                    <?php foreach ($footerLinks as $link): ?>
                                        <?php
                                        $linkHost = parse_url((string) $link['href'], PHP_URL_HOST) ?? '';
                                        $isExternal = $linkHost !== '' && $baseHost !== '' && $linkHost !== $baseHost;
                                        $linkLabel = trim((string) ($link['label'] ?? ''));
                                        $linkHref = trim((string) ($link['href'] ?? ''));
                                        $footerLinkClass = trim((string) ($link['class'] ?? ''));
                                        $isFediverseFollowLink = (!empty($link['modal']) && $link['modal'] === 'fediverse-follow')
                                            || $linkHref === '#fediverse-follow';
                                        ?>
                                        <a class="footer-social-link<?= $footerLinkClass !== '' ? ' ' . htmlspecialchars($footerLinkClass, ENT_QUOTES, 'UTF-8') : '' ?>" href="<?= htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') ?>"<?= $isFediverseFollowLink ? ' data-fediverse-follow-open' : '' ?><?= ($isExternal && !$isFediverseFollowLink) ? ' target="_blank" rel="noopener"' : '' ?> aria-label="<?= htmlspecialchars($linkLabel, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($linkLabel, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $link['svg'] ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($contactFooterItems) && $footerLogoPosition === 'bottom'): ?>
                            <div class="footer-contact-block">
                                <div class="footer-contact-title">Contacto</div>
                                <div class="footer-contact-links">
                                    <?php foreach ($contactFooterItems as $item): ?>
                                        <a class="footer-contact-link" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $item['svg'] ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($footerHtml !== ''): ?>
                            <div class="footer-html-content">
                                <?= $footerHtml ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($contactFooterItems) && $footerLogoPosition !== 'bottom'): ?>
                            <div class="footer-contact-block">
                                <div class="footer-contact-title">Contacto</div>
                                <div class="footer-contact-links">
                                    <?php foreach ($contactFooterItems as $item): ?>
                                        <a class="footer-contact-link" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $item['svg'] ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($hasFooterLogo && $footerLogoPosition === 'bottom'): ?>
                            <div class="footer-logo-wrapper footer-logo-bottom">
                                <a class="footer-logo-link" href="<?= htmlspecialchars($baseUrl ?? '/', ENT_QUOTES, 'UTF-8') ?>" aria-label="Ir a la portada">
                                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo del blog">
                                </a>
                            </div>
                            <?php if (!empty($footerLinks)): ?>
                                <div class="footer-social-links">
                                    <?php foreach ($footerLinks as $link): ?>
                                        <?php
                                        $linkHost = parse_url((string) $link['href'], PHP_URL_HOST) ?? '';
                                        $isExternal = $linkHost !== '' && $baseHost !== '' && $linkHost !== $baseHost;
                                        $linkLabel = trim((string) ($link['label'] ?? ''));
                                        $linkHref = trim((string) ($link['href'] ?? ''));
                                        $footerLinkClass = trim((string) ($link['class'] ?? ''));
                                        $isFediverseFollowLink = (!empty($link['modal']) && $link['modal'] === 'fediverse-follow')
                                            || $linkHref === '#fediverse-follow';
                                        ?>
                                        <a class="footer-social-link<?= $footerLinkClass !== '' ? ' ' . htmlspecialchars($footerLinkClass, ENT_QUOTES, 'UTF-8') : '' ?>" href="<?= htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') ?>"<?= $isFediverseFollowLink ? ' data-fediverse-follow-open' : '' ?><?= ($isExternal && !$isFediverseFollowLink) ? ' target="_blank" rel="noopener"' : '' ?> aria-label="<?= htmlspecialchars($linkLabel, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($linkLabel, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $link['svg'] ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($footerNammuEnabled): ?>
                    <div class="footer-nammu-link">
                        <div>
                            <?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?> utiliza <a href="https://ruralnext.org/nammu" target="_blank" rel="noopener">Nammu</a>, un CMS libre desarrollado en <a href="https://ruralnext.org" target="_blank" rel="noopener">RuralNEXT</a> por <a href="https://maximalista.coop/" target="_blank" rel="noopener">Compañía Maximalista S.Coop.</a>
                            <?php if ($footerEuplEnabled): ?>
                                <br>
                                Licencia Pública de la Unión Europea: <a href="https://interoperable-europe.ec.europa.eu/collection/eupl/eupl-text-eupl-12" target="_blank" rel="noopener">EUPL 1.2</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($footerEuplEnabled): ?>
                            <a class="footer-nammu-link__eupl" href="https://interoperable-europe.ec.europa.eu/collection/eupl/eupl-text-eupl-12" target="_blank" rel="noopener" title="Licencia Pública de la Unión Europea: EUPL 1.2">
                                <img src="<?= htmlspecialchars(rtrim((string) ($baseUrl ?? '/'), '/') . '/EUPL.png', ENT_QUOTES, 'UTF-8') ?>" alt="Licencia Pública de la Unión Europea: EUPL 1.2" loading="lazy">
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </footer>
        <?php endif; ?>
    </div>
    <?php if (!empty($showLogo) && !empty($logoUrl)): ?>
        <a class="floating-logo" href="<?= htmlspecialchars($baseUrl ?? '/', ENT_QUOTES, 'UTF-8') ?>" aria-label="Ir a la portada">
            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo del blog">
        </a>
    <?php endif; ?>
    <?php $showFediverseFollowCta = !$isCrawler && $fediverseCtaUrl !== '' && $fediverseCtaHandle !== ''; ?>
    <?php if ($showFediverseFollowCta || $showFloatingSearch || $showFloatingSubscription): ?>
        <div class="floating-stack">
            <?php if ($showFediverseFollowCta): ?>
                <div class="floating-fediverse" data-nosnippet>
                    <button type="button" class="floating-fediverse__button" data-fediverse-follow-open title="Sigue esta cuenta en el Fediverso desde tu servidor">
                        Síguenos en el Fediverso
                        <?= $fediverseCtaIcon ?>
                    </button>
                </div>
                <dialog class="fediverse-follow-dialog" data-fediverse-follow-dialog data-nosnippet>
                    <div class="fediverse-follow-dialog__card">
                        <h2>Síguenos en el Fediverso</h2>
                        <p>Si tienes un blog en Nammu o cuenta en un servidor Mastodon, Smac2, Akkoma o compatible con Activity Pub, pega el siguiente nombre de cuenta en el buscador de usuarios o perfiles y síguela como a cualquier otro perfil.</p>
                        <code class="fediverse-follow-dialog__handle"><?= htmlspecialchars($fediverseCtaHandle, ENT_QUOTES, 'UTF-8') ?></code>
                        <p>Si tu servidor no encuentra la cuenta enseguida, pega el identificador completo en ese mismo buscador.</p>
                        <div class="fediverse-follow-dialog__actions">
                            <button type="button" data-fediverse-follow-copy>Copiar cuenta</button>
                            <button type="button" data-fediverse-follow-copy-full>Copiar identificador completo</button>
                            <?php if ($fediverseProfileUrl !== ''): ?>
                                <a href="<?= htmlspecialchars($fediverseProfileUrl, ENT_QUOTES, 'UTF-8') ?>">Visita nuestra página de perfil</a>
                            <?php endif; ?>
                            <button type="button" data-fediverse-follow-close>Cerrar</button>
                        </div>
                    </div>
                </dialog>
            <?php endif; ?>
            <?php if ($showFloatingSubscription): ?>
                <div class="floating-search floating-subscription" data-floating-subscription>
                    <form class="floating-search-form subscription-form" method="post" action="<?= htmlspecialchars($floatingSubscriptionAction, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="floating-search-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="5" width="18" height="14" rx="2" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                                <polyline points="3,7 12,13 21,7" fill="none" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <input type="hidden" name="back" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="force_all" value="1">
                        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;">
                        <?= function_exists('nammu_public_subscription_hidden_fields_html') ? nammu_public_subscription_hidden_fields_html('subscribe') : '' ?>
                        <input type="email" name="subscriber_email" placeholder="Suscríbete" required>
                        <button type="submit" aria-label="Enviar">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 4L9 16L4 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <a class="floating-avisos-link" href="<?= htmlspecialchars($avisosUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($subscriptionMenuLabel, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($subscriptionMenuLabel, ENT_QUOTES, 'UTF-8') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                                <rect x="4" y="6" width="16" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                                <polyline points="4,8 12,14 20,8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <?php if ($postalEnabled && $postalLogoSvg !== ''): ?>
                            <a class="floating-postal-link" href="<?= htmlspecialchars($postalUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Suscripción postal" title="Suscripción postal">
                                <?= $postalLogoSvg ?>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
            <?php if ($showFloatingSearch): ?>
                <div class="floating-search">
                    <form class="floating-search-form" method="get" action="<?= htmlspecialchars($floatingSearchAction, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="floating-search-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="8" cy="8" r="6" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                                <line x1="12.5" y1="12.5" x2="17" y2="17" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <input type="text" name="q" placeholder="Busca" required>
                        <button type="submit" aria-label="Buscar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 4L9 16L4 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <?php if ($hasCategories): ?>
                            <a class="floating-search-categories" href="<?= htmlspecialchars($floatingCategoriesUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Índice de categorías" title="Categorías">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                                    <line x1="8" y1="9" x2="16" y2="9" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                                    <line x1="8" y1="13" x2="16" y2="13" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($hasItineraries) && !empty($itinerariesIndexUrl)): ?>
                            <a class="floating-search-itineraries" href="<?= htmlspecialchars($itinerariesIndexUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Itinerarios" title="Itinerarios">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 5H10C11.1046 5 12 5.89543 12 7V19H4C2.89543 19 2 18.1046 2 17V7C2 5.89543 2.89543 5 4 5Z" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M20 5H14C12.8954 5 12 5.89543 12 7V19H20C21.1046 19 22 18.1046 22 17V7C22 5.89543 21.1046 5 20 5Z" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round"/>
                                    <line x1="12" y1="7" x2="12" y2="19" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($hasPodcast) && !empty($podcastIndexUrl)): ?>
                            <a class="floating-search-podcast" href="<?= htmlspecialchars($podcastIndexUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Podcast" title="Podcast">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="9" y="3" width="6" height="10" rx="3" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                                    <path d="M5 11C5 14.866 8.134 18 12 18C15.866 18 19 14.866 19 11" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="12" y1="18" x2="12" y2="22" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="8" y1="22" x2="16" y2="22" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($showAdsBanner && !$isCrawler): ?>
        <div class="nammu-ad-banner" data-ad-banner data-server-date="<?= htmlspecialchars($serverDay, ENT_QUOTES, 'UTF-8') ?>" data-server-expires="<?= htmlspecialchars($serverDayExpires, ENT_QUOTES, 'UTF-8') ?>"<?= $adsLinkHref !== '' ? ' data-ad-link-target="' . $adsLinkHref . '"' : '' ?>>
            <button class="nammu-ad-close" type="button" aria-label="Cerrar anuncio" data-ad-close>
                <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
            <div class="nammu-ad-content">
                <?php if ($adsTitleHtml !== ''): ?>
                    <div class="nammu-ad-title"><?= $adsTitleHtml ?></div>
                <?php endif; ?>
                <div class="nammu-ad-text"><?= $adsHtml ?></div>
                <?php if ($adsFooterLinkHtml !== ''): ?>
                    <div class="nammu-ad-cta"><?= $adsFooterLinkHtml ?></div>
                <?php endif; ?>
            </div>
            <?php if ($adsImageUrl !== ''): ?>
                <div class="nammu-ad-image">
                    <img src="<?= htmlspecialchars($adsImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Imagen del anuncio" loading="lazy" decoding="async">
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <script>
    (function() {
        var banner = document.querySelector('[data-ad-banner]');
        var closeBtn = document.querySelector('[data-ad-close]');
        if (!banner || !closeBtn) {
            return;
        }
        var serverDate = banner.getAttribute('data-server-date') || '';
        var adLinkTarget = banner.getAttribute('data-ad-link-target') || '';
        var closedKey = 'nammu_ad_closed';
        function todayValue() {
            if (serverDate !== '') {
                return serverDate;
            }
            var now = new Date();
            return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
        }
        try {
            if (window.localStorage.getItem(closedKey) === todayValue()) {
                banner.style.display = 'none';
            }
        } catch (e) {
        }
        function closeBanner() {
            banner.style.display = 'none';
            try {
                window.localStorage.setItem(closedKey, todayValue());
            } catch (e) {
            }
        }
        closeBtn.addEventListener('click', function() {
            closeBanner();
        });
        banner.querySelectorAll('[data-ad-link]').forEach(function(link) {
            link.addEventListener('click', function() {
                closeBanner();
            });
        });
        if (adLinkTarget !== '') {
            try {
                var targetUrl = new URL(adLinkTarget, window.location.href);
                var currentUrl = new URL(window.location.href);
                var normalizePath = function(path) {
                    if (path.length > 1 && path.endsWith('/')) {
                        return path.slice(0, -1);
                    }
                    return path;
                };
                var targetKey = targetUrl.origin + normalizePath(targetUrl.pathname);
                var currentKey = currentUrl.origin + normalizePath(currentUrl.pathname);
                if (targetKey === currentKey) {
                    closeBanner();
                }
            } catch (e) {
                // ignore
            }
        }
    })();
    </script>
    <script>
    (function() {
        var pushEnabled = <?= $showPushPrompt ? 'true' : 'false' ?>;
        if (!pushEnabled) {
            return;
        }
        if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            return;
        }
        if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
            return;
        }
        var publicKey = <?= json_encode($pushPublicKey, JSON_UNESCAPED_SLASHES) ?> || '';
        var subscribeUrl = <?= json_encode($pushSubscribeUrl, JSON_UNESCAPED_SLASHES) ?> || '';
        var unsubscribeUrl = <?= json_encode($pushUnsubscribeUrl, JSON_UNESCAPED_SLASHES) ?> || '';
        if (!publicKey || !subscribeUrl) {
            return;
        }
        var promptedKey = 'nammu_push_prompted';
        var promptCooldownDays = 16;

        function wasPrompted() {
            try {
                var stored = localStorage.getItem(promptedKey);
                if (!stored) {
                    return false;
                }
                var lastPrompt = new Date(stored);
                if (isNaN(lastPrompt.getTime())) {
                    return false;
                }
                var diffMs = Date.now() - lastPrompt.getTime();
                return diffMs < (promptCooldownDays * 24 * 60 * 60 * 1000);
            } catch (e) {
                return false;
            }
        }

        function setPrompted() {
            try {
                localStorage.setItem(promptedKey, new Date().toISOString());
            } catch (e) {
                return;
            }
        }

        function urlBase64ToUint8Array(base64String) {
            var padding = '='.repeat((4 - base64String.length % 4) % 4);
            var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            var rawData = window.atob(base64);
            var outputArray = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        function syncSubscription(subscription) {
            var payload = subscription;
            if (subscription && typeof subscription.toJSON === 'function') {
                payload = subscription.toJSON();
            }
            return fetch(subscribeUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
        }

        function ensureSubscribed() {
            return navigator.serviceWorker.register('<?= htmlspecialchars($searchBaseNormalized === '' ? '/push-sw.js' : $searchBaseNormalized . '/push-sw.js', ENT_QUOTES, 'UTF-8') ?>')
                .then(function(registration) {
                    return registration.pushManager.getSubscription().then(function(subscription) {
                        if (subscription) {
                            return syncSubscription(subscription).then(function(response) {
                                if (!response || !response.ok) {
                                    return null;
                                }
                                return subscription;
                            });
                        }
                        return registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array(publicKey)
                        }).then(function(newSub) {
                            return syncSubscription(newSub).then(function(response) {
                                if (!response || !response.ok) {
                                    return null;
                                }
                                return newSub;
                            });
                        });
                    });
                });
        }

        function trySubscribe() {
            return ensureSubscribed().then(function() {
                return true;
            }).catch(function() {
                return false;
            });
        }

        if (Notification.permission === 'granted') {
            trySubscribe();
            return;
        }
        if (Notification.permission === 'denied') {
            return;
        }
        if (wasPrompted()) {
            return;
        }
        setPrompted();
        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                trySubscribe();
            }
        });
    })();
    </script>
    <script>
    (function() {
        // Progreso de itinerarios sin cookies: el servidor emite un token firmado (?p=) en cada página y en los
        // enlaces entre temas; aquí sólo se guarda en localStorage para retomar otro día, se reescriben los
        // enlaces con el token más completo y se envía la autoevaluación al servidor para corregirla.
        var PARAM = 'p';
        function normalizeSlug(slug) {
            var normalized = (slug || '').toString().toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '');
            return normalized || 'general';
        }
        function storageKey(slug) {
            return 'nammu_itinerary_token_' + normalizeSlug(slug);
        }
        function readStored(slug) {
            try {
                return window.localStorage.getItem(storageKey(slug)) || '';
            } catch (e) {
                return '';
            }
        }
        function store(slug, token) {
            if (!slug || !token) {
                return;
            }
            try {
                window.localStorage.setItem(storageKey(slug), token);
            } catch (e) {
            }
        }
        function decodePayload(token) {
            if (!token || token.indexOf('.') === -1) {
                return null;
            }
            try {
                var base64 = token.split('.')[0].replace(/-/g, '+').replace(/_/g, '/');
                var binary = window.atob(base64);
                var json = decodeURIComponent(Array.prototype.map.call(binary, function(c) {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
                var parsed = JSON.parse(json);
                return {
                    slug: parsed.i || '',
                    visited: Array.isArray(parsed.v) ? parsed.v : [],
                    passed: Array.isArray(parsed.p) ? parsed.p : []
                };
            } catch (e) {
                return null;
            }
        }
        function score(token) {
            var payload = decodePayload(token);
            return payload ? payload.visited.length + payload.passed.length : -1;
        }
        function urlToken() {
            try {
                return new URL(window.location.href).searchParams.get(PARAM) || '';
            } catch (e) {
                return '';
            }
        }
        function withToken(url, token) {
            if (!url || !token) {
                return url;
            }
            try {
                var parsed = new URL(url, window.location.href);
                parsed.searchParams.set(PARAM, token);
                return parsed.toString();
            } catch (e) {
                return url + (url.indexOf('?') === -1 ? '?' : '&') + PARAM + '=' + encodeURIComponent(token);
            }
        }
        function cleanAddressBar() {
            try {
                var parsed = new URL(window.location.href);
                if (parsed.searchParams.has(PARAM)) {
                    parsed.searchParams.delete(PARAM);
                    window.history.replaceState(null, '', parsed.toString());
                }
            } catch (e) {
            }
        }
        function bestToken(slug, pageToken) {
            var stored = readStored(slug);
            var fromUrl = urlToken();
            var candidates = [pageToken || '', fromUrl, stored];
            var best = '';
            candidates.forEach(function(candidate) {
                var payload = decodePayload(candidate);
                if (!payload || (payload.slug && payload.slug !== slug)) {
                    return;
                }
                if (best === '' || score(candidate) > score(best)) {
                    best = candidate;
                }
            });
            return best;
        }
        var currentTokens = {};
        function rewriteLinks(slug, token) {
            if (!token) {
                return;
            }
            document.querySelectorAll('[data-topic-link], [data-next-link]').forEach(function(link) {
                var href = link.getAttribute('href') || link.getAttribute('data-original-href') || '';
                if (!href || href.charAt(0) === '#') {
                    return;
                }
                if (href.indexOf('/itinerarios/') === -1) {
                    return;
                }
                var updated = withToken(href, token);
                if (link.getAttribute('href')) {
                    link.setAttribute('href', updated);
                } else {
                    link.setAttribute('data-original-href', updated);
                }
            });
        }
        function adoptToken(slug, token) {
            if (!slug || !token) {
                return;
            }
            currentTokens[slug] = token;
            store(slug, token);
            rewriteLinks(slug, token);
        }
        function progressFor(slug) {
            var payload = decodePayload(currentTokens[slug] || readStored(slug));
            return payload || {visited: [], passed: []};
        }
        function hasVisited(progress, topic) {
            return !topic || progress.visited.indexOf(topic) !== -1;
        }
        function hasPassed(progress, topic) {
            return !topic || progress.passed.indexOf(topic) !== -1;
        }
        function setLinkState(link, unlocked, disabledClass) {
            if (!link) {
                return;
            }
            if (unlocked) {
                var original = link.getAttribute('data-original-href');
                if (original) {
                    link.setAttribute('href', original);
                }
                link.classList.remove('is-disabled');
                if (disabledClass) {
                    link.classList.remove(disabledClass);
                }
                link.removeAttribute('aria-disabled');
                link.removeAttribute('tabindex');
            } else {
                if (!link.getAttribute('data-original-href') && link.getAttribute('href')) {
                    link.setAttribute('data-original-href', link.getAttribute('href'));
                }
                if (link.getAttribute('href')) {
                    link.removeAttribute('href');
                }
                link.classList.add('is-disabled');
                if (disabledClass) {
                    link.classList.add(disabledClass);
                }
                link.setAttribute('aria-disabled', 'true');
                link.setAttribute('tabindex', '-1');
            }
        }
        function toggleTopicCard(card, unlocked) {
            var lockMessage = card.querySelector('[data-topic-lock-message]');
            card.classList.toggle('itinerary-topic-card--locked', !unlocked);
            card.querySelectorAll('[data-topic-link]').forEach(function(link) {
                setLinkState(link, unlocked);
            });
            if (lockMessage) {
                lockMessage.style.display = unlocked ? 'none' : '';
            }
        }
        function applyTopicLocks(container) {
            var slug = container.getAttribute('data-itinerary-slug') || '';
            var usageLogic = container.getAttribute('data-usage-logic') || 'free';
            if (!slug || usageLogic === 'free') {
                return;
            }
            var progress = progressFor(slug);
            var cards = container.querySelectorAll('[data-itinerary-topic]');
            if (!cards.length) {
                return;
            }
            var usesAssessment = usageLogic === 'assessment';
            var baseUnlocked = usesAssessment ? hasPassed(progress, '__presentation') : hasVisited(progress, '__presentation');
            var highestCompletedIndex = -1;
            var states = [];
            cards.forEach(function(card, index) {
                var topicSlug = card.getAttribute('data-topic-slug') || '';
                var completed = usesAssessment ? hasPassed(progress, topicSlug) : hasVisited(progress, topicSlug);
                if (completed && index > highestCompletedIndex) {
                    highestCompletedIndex = index;
                }
                states.push({element: card, completed: completed});
            });
            if (!baseUnlocked && highestCompletedIndex >= 0) {
                baseUnlocked = true;
            }
            var maxUnlockedIndex = baseUnlocked ? Math.min(cards.length - 1, highestCompletedIndex + 1) : -1;
            states.forEach(function(entry, index) {
                var unlocked = entry.completed || (baseUnlocked && index === highestCompletedIndex + 1 && index <= maxUnlockedIndex);
                toggleTopicCard(entry.element, unlocked);
            });
            var startLink = container.querySelector('[data-first-topic-link]');
            if (startLink) {
                setLinkState(startLink, baseUnlocked && maxUnlockedIndex >= 0, 'button-disabled');
            }
        }
        // Adopta el mejor token disponible para cada itinerario presente en la página.
        var hadTokenInUrl = urlToken() !== '';
        document.querySelectorAll('[data-itinerary-quiz], [data-itinerary-topic-cta], [data-itinerary-topics], [data-itinerary-locked]').forEach(function(block) {
            var slug = block.getAttribute('data-itinerary-slug') || '';
            if (!slug || currentTokens[slug]) {
                return;
            }
            var token = bestToken(slug, block.getAttribute('data-progress-token') || '');
            if (token) {
                adoptToken(slug, token);
            }
        });
        if (hadTokenInUrl) {
            cleanAddressBar();
        }
        document.querySelectorAll('[data-itinerary-quiz]').forEach(function(quizBlock) {
            var slug = quizBlock.getAttribute('data-itinerary-slug');
            var topic = quizBlock.getAttribute('data-topic-slug');
            var endpoint = quizBlock.getAttribute('data-quiz-endpoint') || '';
            var minCorrect = parseInt(quizBlock.getAttribute('data-min-correct'), 10) || 1;
            var submitBtn = quizBlock.querySelector('[data-quiz-submit]');
            var resultBox = quizBlock.querySelector('[data-quiz-result]');
            if (!slug || !topic || !endpoint || !submitBtn || !resultBox) {
                return;
            }
            submitBtn.addEventListener('click', function() {
                var answers = {};
                quizBlock.querySelectorAll('[data-quiz-question]').forEach(function(question) {
                    var questionIndex = question.getAttribute('data-question-index');
                    if (questionIndex === null) {
                        return;
                    }
                    var checked = [];
                    question.querySelectorAll('[data-quiz-answer]').forEach(function(answer) {
                        if (answer.checked) {
                            checked.push(parseInt(answer.getAttribute('data-answer-index'), 10));
                        }
                    });
                    answers[questionIndex] = checked;
                });
                submitBtn.disabled = true;
                resultBox.textContent = 'Corrigiendo…';
                resultBox.classList.remove('text-success', 'text-danger');
                var body = {answers: answers};
                body[PARAM] = currentTokens[slug] || readStored(slug) || '';
                window.fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify(body)
                }).then(function(response) {
                    return response.json();
                }).then(function(data) {
                    submitBtn.disabled = false;
                    if (!data || !data.ok) {
                        resultBox.textContent = 'No se ha podido corregir la autoevaluación. Inténtalo de nuevo.';
                        resultBox.classList.add('text-danger');
                        return;
                    }
                    var total = data.total || 0;
                    var percentage = total > 0 ? Math.round((data.correct / total) * 100) : 0;
                    var message = 'Has respondido correctamente el ' + percentage + '% (' + data.correct + ' de ' + total + ' preguntas). ';
                    message += data.passed
                        ? 'Has superado el mínimo establecido.'
                        : 'No alcanzas el mínimo de ' + (data.minimum || minCorrect) + ' preguntas.';
                    resultBox.textContent = message;
                    resultBox.classList.toggle('text-success', !!data.passed);
                    resultBox.classList.toggle('text-danger', !data.passed);
                    if (data.token) {
                        adoptToken(slug, data.token);
                    }
                    if (data.passed) {
                        document.dispatchEvent(new CustomEvent('itineraryQuizPassed', {
                            detail: {slug: slug, topic: topic}
                        }));
                    }
                }).catch(function() {
                    submitBtn.disabled = false;
                    resultBox.textContent = 'No se ha podido corregir la autoevaluación. Comprueba la conexión e inténtalo de nuevo.';
                    resultBox.classList.add('text-danger');
                });
            });
        });
        var ctaBlock = document.querySelector('[data-itinerary-topic-cta]');
        if (ctaBlock) {
            var ctaSlug = ctaBlock.getAttribute('data-itinerary-slug');
            var ctaTopic = ctaBlock.getAttribute('data-topic-slug');
            var requiresQuiz = ctaBlock.getAttribute('data-requires-quiz') === '1';
            var initialPassed = ctaBlock.getAttribute('data-initial-passed') === '1' || hasPassed(progressFor(ctaSlug), ctaTopic);
            var nextLink = ctaBlock.querySelector('[data-next-link]');
            var lockedNotice = ctaBlock.querySelector('[data-next-locked]');
            var locked = requiresQuiz && !initialPassed;
            function setLocked(state) {
                if (!nextLink) {
                    return;
                }
                nextLink.classList.toggle('button-disabled', state);
                if (state) {
                    nextLink.setAttribute('aria-disabled', 'true');
                    nextLink.setAttribute('tabindex', '-1');
                } else {
                    nextLink.removeAttribute('aria-disabled');
                    nextLink.removeAttribute('tabindex');
                }
                if (lockedNotice) {
                    lockedNotice.style.display = state ? '' : 'none';
                }
            }
            setLocked(locked);
            if (nextLink) {
                nextLink.addEventListener('click', function(event) {
                    if (locked) {
                        event.preventDefault();
                    }
                });
            }
            document.addEventListener('itineraryQuizPassed', function(event) {
                if (!event.detail || event.detail.slug !== ctaSlug || event.detail.topic !== ctaTopic) {
                    return;
                }
                locked = false;
                setLocked(false);
            });
        }
        document.querySelectorAll('[data-itinerary-topics]').forEach(function(container) {
            var slug = container.getAttribute('data-itinerary-slug');
            if (!slug) {
                return;
            }
            applyTopicLocks(container);
            document.addEventListener('itineraryQuizPassed', function(event) {
                if (!event.detail || event.detail.slug !== slug) {
                    return;
                }
                applyTopicLocks(container);
            });
        });
    })();
    </script>
    <script>
    (function() {
        var pdfBlocks = document.querySelectorAll('.embedded-pdf');
        if (!pdfBlocks.length) {
            return;
        }
        function buildPdfSrc(baseHref, params) {
            var search = new URLSearchParams(params);
            return baseHref + '#' + search.toString();
        }

        function normalizeParams(fragment) {
            var search = new URLSearchParams((fragment || '').replace(/^#+/, ''));
            var defaults = {
                toolbar: '0',
                navpanes: '0',
                scrollbar: '0',
                statusbar: '0',
                zoom: 'page-fit',
                spread: '0',
                view: 'Fit',
                pagemode: 'none'
            };
            Object.keys(defaults).forEach(function(key) {
                search.set(key, defaults[key]);
            });
            return search;
        }

        pdfBlocks.forEach(function(block) {
            if (block.dataset.pdfEnhanced === '1') {
                return;
            }
            var iframe = block.querySelector('iframe');
            if (!iframe) {
                return;
            }
            block.setAttribute('data-pdf-orientation', 'landscape');
            var srcValue = iframe.getAttribute('src') || '';
            var parts = srcValue.split('#');
            var baseHref = parts[0];
            var fragment = parts[1] || '';
            var params = normalizeParams(fragment);
            var pageMatch = params.get('page');
            var currentPage = pageMatch ? parseInt(pageMatch, 10) || 1 : 1;
            params.set('page', Math.max(1, currentPage));
            iframe.setAttribute('scrolling', 'no');
            iframe.setAttribute('allowfullscreen', 'true');
            var targetSrc = buildPdfSrc(baseHref, params);
            if (iframe.getAttribute('src') !== targetSrc) {
                iframe.setAttribute('src', targetSrc);
            }

            if (!block.querySelector('.embedded-pdf__actions')) {
                var actions = document.createElement('div');
                actions.className = 'embedded-pdf__actions';
                actions.setAttribute('aria-label', 'Acciones del PDF');

                var downloadLink = document.createElement('a');
                downloadLink.className = 'embedded-pdf__action';
                downloadLink.href = baseHref;
                downloadLink.setAttribute('download', '');
                downloadLink.textContent = 'Descargar PDF';

                var fullscreenLink = document.createElement('a');
                fullscreenLink.className = 'embedded-pdf__action';
                fullscreenLink.href = baseHref;
                fullscreenLink.target = '_blank';
                fullscreenLink.rel = 'noopener';
                fullscreenLink.textContent = 'Ver a pantalla completa';

                actions.appendChild(downloadLink);
                actions.appendChild(fullscreenLink);
                block.appendChild(actions);
            }

            var syncPending = false;
            function syncHeight() {
                var styles = getComputedStyle(block);
                var aspectValue = parseFloat(styles.getPropertyValue('--pdf-aspect')) || 1.414;
                var paddingLeft = parseFloat(styles.paddingLeft) || 0;
                var paddingRight = parseFloat(styles.paddingRight) || 0;
                var availableWidth = block.clientWidth - paddingLeft - paddingRight;
                if (availableWidth <= 0) {
                    availableWidth = block.clientWidth;
                }
                var height = (availableWidth / aspectValue) * 1.02; // small buffer to avoid scrollbars
                iframe.style.height = height + 'px';
            }
            function scheduleSync() {
                if (syncPending) {
                    return;
                }
                syncPending = true;
                requestAnimationFrame(function() {
                    syncPending = false;
                    syncHeight();
                });
            }

            scheduleSync();
            if (typeof ResizeObserver !== 'undefined') {
                var resizeObserver = new ResizeObserver(function() {
                    scheduleSync();
                });
                resizeObserver.observe(block);
            } else {
                window.addEventListener('resize', scheduleSync);
            }

            block.dataset.pdfEnhanced = '1';
        });
    })();
    </script>
    <script>
    (function() {
        var openButtons = document.querySelectorAll('[data-fediverse-follow-open]');
        var dialog = document.querySelector('[data-fediverse-follow-dialog]');
        if (!openButtons.length || !dialog || typeof dialog.showModal !== 'function') return;
        var closeButton = dialog.querySelector('[data-fediverse-follow-close]');
        var copyButton = dialog.querySelector('[data-fediverse-follow-copy]');
        var copyFullButton = dialog.querySelector('[data-fediverse-follow-copy-full]');
        var handle = dialog.querySelector('.fediverse-follow-dialog__handle');
        var actorUrl = <?= json_encode((string) $fediverseCtaUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        openButtons.forEach(function(openButton) {
            openButton.addEventListener('click', function(ev) {
                ev.preventDefault();
                dialog.showModal();
            });
        });
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                dialog.close();
            });
        }
        dialog.addEventListener('click', function(ev) {
            var rect = dialog.getBoundingClientRect();
            var inside = ev.clientX >= rect.left && ev.clientX <= rect.right && ev.clientY >= rect.top && ev.clientY <= rect.bottom;
            if (!inside) {
                dialog.close();
            }
        });
        function bindCopyButton(button, text, idleLabel, doneLabel) {
            if (!button || !text || !navigator.clipboard || !navigator.clipboard.writeText) return;
            button.addEventListener('click', function() {
                navigator.clipboard.writeText(text).then(function() {
                    button.textContent = doneLabel;
                    setTimeout(function() {
                        button.textContent = idleLabel;
                    }, 1600);
                });
            });
        }
        bindCopyButton(copyButton, handle ? (handle.textContent || '') : '', 'Copiar cuenta', 'Cuenta copiada');
        bindCopyButton(copyFullButton, actorUrl, 'Copiar identificador completo', 'Identificador copiado');
    })();
    </script>
    <script>
    (function() {
        var params = new URLSearchParams(window.location.search || '');
        var messages = {
            subscribed: 'Suscripción confirmada. ¡Gracias!',
            sub_sent: 'Hemos enviado un email de confirmación. Revisa tu correo.',
            sub_error: 'No pudimos procesar ese correo. Revisa la dirección e inténtalo de nuevo.'
        };
        var target = document.querySelector('[data-floating-subscription]');
        if (!target) return;
        var msg = '';
        if (params.get('subscribed') === '1') {
            msg = messages.subscribed;
        } else if (params.get('sub_sent') === '1') {
            msg = messages.sub_sent;
        } else if (params.get('sub_error') === '1') {
            msg = messages.sub_error;
        }
        if (!msg) return;
        var box = document.createElement('div');
        box.className = 'subscription-feedback';
        box.textContent = msg;
        target.appendChild(box);
    })();
    </script>
    <script>
    (function() {
        var stack = Array.prototype.slice.call(document.querySelectorAll('.floating-search'));
        if (!stack.length) return;
        var pending = false;
        function restack() {
            var isMobile = window.matchMedia('(max-width: 720px)').matches;
            var offset = isMobile ? 16 : (parseInt(getComputedStyle(document.documentElement).fontSize, 10) * 2.5);
            var gap = isMobile ? 12 : 14;
            var heights = stack.map(function(el) { return el.offsetHeight; });
            stack.forEach(function(el, index) {
                el.style.bottom = offset + 'px';
                offset += (heights[index] || 0) + gap;
            });
        }
        function scheduleRestack() {
            if (pending) {
                return;
            }
            pending = true;
            requestAnimationFrame(function() {
                pending = false;
                restack();
            });
        }
        window.addEventListener('resize', scheduleRestack);
        scheduleRestack();
    })();
    </script>
<?php if ($statsBeaconDescriptor !== '' && $statsBeaconUrl !== ''): ?>
    <script>
    (function() {
        // Estadísticas sin cookies: una única petición con la página vista (descriptor firmado por el servidor),
        // el referer y la URL. No se guarda nada en el navegador.
        var payload = JSON.stringify({
            pv: <?= json_encode($statsBeaconDescriptor, JSON_UNESCAPED_SLASHES) ?>,
            ref: document.referrer || '',
            url: window.location.href
        });
        var url = <?= json_encode($statsBeaconUrl, JSON_UNESCAPED_SLASHES) ?>;
        try {
            if (navigator.sendBeacon && navigator.sendBeacon(url, new Blob([payload], {type: 'application/json'}))) {
                return;
            }
        } catch (e) {
        }
        if (window.fetch) {
            window.fetch(url, {method: 'POST', keepalive: true, credentials: 'omit', headers: {'Content-Type': 'application/json'}, body: payload}).catch(function() {});
        }
    })();
    </script>
<?php endif; ?>
</body>
</html>
