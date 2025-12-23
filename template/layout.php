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
$footerLogoPosition = $theme['footer_logo'] ?? 'none';
if (!in_array($footerLogoPosition, ['none', 'top', 'bottom'], true)) {
    $footerLogoPosition = 'none';
}
$footerLinks = is_array($footerLinks ?? null) ? $footerLinks : [];
$logoUrl = $theme['logo_url'] ?? null;
$faviconUrl = $theme['favicon_url'] ?? null;
$showLogo = $showLogo ?? false;
$socialMeta = $socialMeta ?? [];
$themeGlobal = $theme['global'] ?? [];
$cornerStyle = $theme['corners'] ?? ($themeGlobal['corners'] ?? 'rounded');
$cornerClass = $cornerStyle === 'square' ? 'corners-square' : 'corners-rounded';
$searchSettings = $theme['search'] ?? [];
$searchFloatingEnabled = ($searchSettings['floating'] ?? 'off') === 'on';
$subscriptionSettings = $theme['subscription'] ?? [];
$subscriptionFloatingEnabled = ($subscriptionSettings['floating'] ?? 'off') === 'on';
$baseHref = $baseUrl ?? '/';
$searchBaseNormalized = rtrim($baseHref === '' ? '/' : $baseHref, '/');
$floatingSearchAction = $searchBaseNormalized === '' ? '/buscar.php' : $searchBaseNormalized . '/buscar.php';
$floatingCategoriesUrl = $searchBaseNormalized === '' ? '/categorias' : $searchBaseNormalized . '/categorias';
$showFloatingSearch = $searchFloatingEnabled;
$floatingSubscriptionAction = $searchBaseNormalized === '' ? '/subscribe.php' : $searchBaseNormalized . '/subscribe.php';
$avisosUrl = $searchBaseNormalized === '' ? '/avisos.php' : $searchBaseNormalized . '/avisos.php';
$showFloatingSubscription = $subscriptionFloatingEnabled;
$postalEnabled = $postalEnabled ?? false;
$postalUrl = $postalUrl ?? '/correos.php';
$postalLogoSvg = $postalLogoSvg ?? '';
$hasItineraries = $hasItineraries ?? false;
$itinerariesIndexUrl = $itinerariesIndexUrl ?? ($searchBaseNormalized === '' ? '/itinerarios' : $searchBaseNormalized . '/itinerarios');
if ($postalLogoSvg === '' && function_exists('nammu_postal_icon_svg')) {
    $postalLogoSvg = nammu_postal_icon_svg();
}
$hasFooterLogo = $footerLogoPosition !== 'none' && !empty($logoUrl);
$showFooterBlock = ($footerHtml !== '') || $hasFooterLogo;
$currentUrl = ($baseHref ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isCrawler = $userAgent !== '' && preg_match('/(bot|crawl|spider|slurp|bingpreview|facebookexternalhit|facebot|linkedinbot|twitterbot|pinterest|whatsapp|telegram|yandex|baiduspider|duckduckbot|sogou|ia_archiver)/i', $userAgent);
$statsConsentGiven = $isCrawler || (function_exists('nammu_has_stats_consent') ? nammu_has_stats_consent() : false);
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
$isHome = ($normalizedRequest === $normalizedBase) || ($normalizedRequest === $normalizedBase . '/index.php');
$adsSettings = is_array($adsSettings ?? null) ? $adsSettings : (function_exists('nammu_ads_settings') ? nammu_ads_settings() : []);
$adsEnabled = ($adsSettings['enabled'] ?? 'off') === 'on';
$adsScope = $adsSettings['scope'] ?? 'home';
$adsText = trim((string) ($adsSettings['text'] ?? ''));
$adsImage = trim((string) ($adsSettings['image'] ?? ''));
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
$adsClosedToday = ($_COOKIE['nammu_ad_closed'] ?? '') === date('Y-m-d');
$showAdsBanner = $adsEnabled && $adsHtml !== '' && !$adsClosedToday && !$isCrawler && $statsConsentGiven;
if ($adsScope === 'home' && !$isHome) {
    $showAdsBanner = false;
}
if (function_exists('nammu_record_visit')) {
    nammu_record_visit();
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle !== '' ? "{$pageTitle} — {$siteTitle}" : $siteTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if (($metaDescription ?? '') !== ''): ?>
        <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($rssUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($fontLink): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($fontLink, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($faviconUrl): ?>
        <link rel="icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (!empty($socialMeta['canonical'])): ?>
        <link rel="canonical" href="<?= htmlspecialchars($socialMeta['canonical'], ENT_QUOTES, 'UTF-8') ?>">
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
        body.nammu-cookie-locked {
            overflow: hidden;
        }
        body.nammu-cookie-locked .wrapper {
            filter: blur(3px);
            pointer-events: none;
            user-select: none;
        }
        .nammu-cookie-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10, 16, 24, 0.55);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .nammu-cookie-overlay.is-visible {
            display: flex;
        }
        .nammu-cookie-card {
            width: min(720px, 92vw);
            background: #ffffff;
            color: #222;
            border-radius: 18px;
            padding: 2rem;
            box-shadow: 0 24px 60px rgba(0,0,0,0.35);
        }
        .nammu-cookie-logo {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        .nammu-cookie-card h2 {
            margin-top: 0;
            font-size: 1.6rem;
        }
        .nammu-cookie-card p {
            margin: 0 0 1rem;
        }
        .nammu-cookie-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        .nammu-cookie-actions button {
            border: none;
            border-radius: 999px;
            padding: 0.75rem 1.5rem;
            font-weight: 700;
            cursor: pointer;
        }
        .nammu-cookie-accept {
            background: #1b8eed;
            color: #fff;
        }
        .nammu-cookie-decline {
            background: #e6e9ef;
            color: #1b1b1b;
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
            background: #000;
            padding: 0.5rem;
            box-sizing: border-box;
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
            background: #000;
            margin: 0 auto;
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
        .floating-search-itineraries {
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
        .floating-search-itineraries:hover {
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
<?php $cookieLogo = $logoUrl !== null && $logoUrl !== '' ? $logoUrl : 'nammu.png'; ?>
<?php
$baseHost = '';
if (!empty($baseUrl)) {
    $baseHost = parse_url((string) $baseUrl, PHP_URL_HOST) ?? '';
}
?>
<body class="<?= htmlspecialchars($cornerClass, ENT_QUOTES, 'UTF-8') ?><?= $statsConsentGiven ? '' : ' nammu-cookie-locked' ?>">
    <div class="nammu-cookie-overlay<?= $statsConsentGiven ? '' : ' is-visible' ?>" data-cookie-overlay aria-hidden="<?= $statsConsentGiven ? 'true' : 'false' ?>">
        <div class="nammu-cookie-card" role="dialog" aria-modal="true" aria-labelledby="cookieNoticeTitle">
            <img class="nammu-cookie-logo" src="<?= htmlspecialchars($cookieLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo del blog">
            <h2 id="cookieNoticeTitle">Uso de cookies para estadisticas</h2>
            <p>Para cumplir con la RGPD, necesitamos tu consentimiento para usar cookies de estadistica.</p>
            <p>Los datos se usan exclusivamente para medir visitas y mejorar el contenido. No se comparten con terceros.</p>
            <p>Debes aceptar para continuar la lectura.</p>
            <div class="nammu-cookie-actions">
                <button type="button" class="nammu-cookie-accept" data-cookie-accept>Aceptar y continuar</button>
                <button type="button" class="nammu-cookie-decline" data-cookie-decline>Salir</button>
            </div>
        </div>
    </div>
    <div class="wrapper">
        <main>
            <?= $content ?>
        </main>
        <?php if ($showFooterBlock): ?>
            <footer>
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
                                    ?>
                                    <a class="footer-social-link" href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $isExternal ? ' target="_blank" rel="noopener"' : '' ?> aria-label="<?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= $link['svg'] ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($footerHtml !== ''): ?>
                        <div class="footer-html-content">
                            <?= $footerHtml ?>
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
                                    ?>
                                    <a class="footer-social-link" href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $isExternal ? ' target="_blank" rel="noopener"' : '' ?> aria-label="<?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= $link['svg'] ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </footer>
        <?php endif; ?>
    </div>
    <?php if (!empty($showLogo) && !empty($logoUrl)): ?>
        <a class="floating-logo" href="<?= htmlspecialchars($baseUrl ?? '/', ENT_QUOTES, 'UTF-8') ?>" aria-label="Ir a la portada">
            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo del blog">
        </a>
    <?php endif; ?>
    <?php if ($showFloatingSearch || $showFloatingSubscription): ?>
        <div class="floating-stack">
            <?php if ($showFloatingSubscription): ?>
                <div class="floating-search floating-subscription" data-floating-subscription>
                    <form class="floating-search-form subscription-form" method="get" action="<?= htmlspecialchars($avisosUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="floating-search-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="5" width="18" height="14" rx="2" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                                <polyline points="3,7 12,13 21,7" fill="none" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                <input type="email" name="subscriber_email" placeholder="Pon tu email para suscribirte" required>
                        <a class="floating-avisos-link" href="<?= htmlspecialchars($avisosUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Avisos por email">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                                <rect x="4" y="6" width="16" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                                <polyline points="4,8 12,14 20,8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <?php if ($postalEnabled && $postalLogoSvg !== ''): ?>
                            <a class="floating-postal-link" href="<?= htmlspecialchars($postalUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Suscripción postal">
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
                        <input type="text" name="q" placeholder="Buscar en el sitio..." required>
                        <button type="submit" aria-label="Buscar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 4L9 16L4 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <a class="floating-search-categories" href="<?= htmlspecialchars($floatingCategoriesUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Índice de categorías">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                                <rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                                <line x1="8" y1="9" x2="16" y2="9" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                                <line x1="8" y1="13" x2="16" y2="13" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                            </svg>
                        </a>
                        <?php if (!empty($hasItineraries) && !empty($itinerariesIndexUrl)): ?>
                            <a class="floating-search-itineraries" href="<?= htmlspecialchars($itinerariesIndexUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Itinerarios">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 5H10C11.1046 5 12 5.89543 12 7V19H4C2.89543 19 2 18.1046 2 17V7C2 5.89543 2.89543 5 4 5Z" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M20 5H14C12.8954 5 12 5.89543 12 7V19H20C21.1046 19 22 18.1046 22 17V7C22 5.89543 21.1046 5 20 5Z" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round"/>
                                    <line x1="12" y1="7" x2="12" y2="19" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($showAdsBanner && !$isCrawler): ?>
        <div class="nammu-ad-banner" data-ad-banner>
            <button class="nammu-ad-close" type="button" aria-label="Cerrar anuncio" data-ad-close>
                <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
            <div class="nammu-ad-content">
                <div class="nammu-ad-text"><?= $adsHtml ?></div>
            </div>
            <?php if ($adsImageUrl !== ''): ?>
                <div class="nammu-ad-image">
                    <img src="<?= htmlspecialchars($adsImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Imagen del anuncio">
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <script>
    (function() {
        var overlay = document.querySelector('[data-cookie-overlay]');
        var acceptBtn = document.querySelector('[data-cookie-accept]');
        var declineBtn = document.querySelector('[data-cookie-decline]');
        if (!overlay || !acceptBtn) {
            return;
        }
        function hasConsent() {
            return document.cookie.split(';').some(function(part) {
                return part.trim().indexOf('nammu_stats_consent=1') === 0;
            });
        }
        function setCookie(name, value) {
            document.cookie = name + '=' + value + ';path=/;max-age=31536000;samesite=lax';
        }
        function generateUid() {
            if (window.crypto && window.crypto.getRandomValues) {
                var bytes = new Uint8Array(16);
                window.crypto.getRandomValues(bytes);
                return Array.prototype.map.call(bytes, function(b) {
                    return ('0' + b.toString(16)).slice(-2);
                }).join('');
            }
            return Math.random().toString(16).slice(2) + Math.random().toString(16).slice(2);
        }
        if (hasConsent()) {
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
            return;
        }
        acceptBtn.addEventListener('click', function() {
            setCookie('nammu_stats_consent', '1');
            if (!document.cookie.split(';').some(function(part) { return part.trim().indexOf('nammu_stats_uid=') === 0; })) {
                setCookie('nammu_stats_uid', generateUid());
            }
            window.location.reload();
        });
        if (declineBtn) {
            declineBtn.addEventListener('click', function() {
                window.location.href = 'about:blank';
            });
        }
    })();
    </script>
    <script>
    (function() {
        var banner = document.querySelector('[data-ad-banner]');
        var closeBtn = document.querySelector('[data-ad-close]');
        if (!banner || !closeBtn) {
            return;
        }
        function hasConsent() {
            return document.cookie.split(';').some(function(part) {
                return part.trim().indexOf('nammu_stats_consent=1') === 0;
            });
        }
        closeBtn.addEventListener('click', function() {
            banner.style.display = 'none';
            if (!hasConsent()) {
                return;
            }
            var now = new Date();
            var y = now.getFullYear();
            var m = String(now.getMonth() + 1).padStart(2, '0');
            var d = String(now.getDate()).padStart(2, '0');
            var value = y + '-' + m + '-' + d;
            var expiry = new Date();
            expiry.setHours(23, 59, 59, 999);
            document.cookie = 'nammu_ad_closed=' + value + ';path=/;expires=' + expiry.toUTCString() + ';samesite=lax';
        });
    })();
    </script>
    <script>
    (function() {
        function buildCookieName(slug) {
            var normalized = (slug || '').toString().toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '');
            if (!normalized) {
                normalized = 'general';
            }
            return 'nammu_itinerary_progress_' + normalized;
        }
        function readProgress(slug) {
            var name = buildCookieName(slug) + '=';
            var decoded = '';
            document.cookie.split(';').forEach(function(part) {
                var trimmed = part.trim();
                if (trimmed.indexOf(name) === 0) {
                    decoded = decodeURIComponent(trimmed.substring(name.length));
                }
            });
            if (!decoded) {
                return {visited: [], passed: []};
            }
            try {
                var parsed = JSON.parse(decoded);
                return {
                    visited: Array.isArray(parsed.visited) ? parsed.visited : [],
                    passed: Array.isArray(parsed.passed) ? parsed.passed : []
                };
            } catch (e) {
                return {visited: [], passed: []};
            }
        }
        function writeProgress(slug, data) {
            var name = buildCookieName(slug);
            var payload = encodeURIComponent(JSON.stringify(data));
            document.cookie = name + '=' + payload + ';path=/;max-age=31536000;samesite=lax';
        }
        function ensureStructure(slug) {
            var progress = readProgress(slug);
            if (!Array.isArray(progress.visited)) {
                progress.visited = [];
            }
            if (!Array.isArray(progress.passed)) {
                progress.passed = [];
            }
            return progress;
        }
        function markVisited(slug, topic) {
            if (!slug || !topic) {
                return;
            }
            var progress = ensureStructure(slug);
            if (progress.visited.indexOf(topic) === -1) {
                progress.visited.push(topic);
                writeProgress(slug, progress);
            }
        }
        function markPassed(slug, topic) {
            if (!slug || !topic) {
                return;
            }
            var progress = ensureStructure(slug);
            if (progress.passed.indexOf(topic) === -1) {
                progress.passed.push(topic);
                writeProgress(slug, progress);
            }
            return progress;
        }

        function hasVisited(progress, topic) {
            if (!topic) {
                return true;
            }
            return progress.visited.indexOf(topic) !== -1;
        }

        function hasPassed(progress, topic) {
            if (!topic) {
                return true;
            }
            return progress.passed.indexOf(topic) !== -1;
        }

        function setLinkState(link, unlocked, disabledClass) {
            if (!link) {
                return;
            }
            if (unlocked) {
                var original = link.getAttribute('data-original-href');
                if (original) {
                    link.setAttribute('href', original);
                } else if (link.dataset.originalHref) {
                    link.setAttribute('href', link.dataset.originalHref);
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
            var links = card.querySelectorAll('[data-topic-link]');
            if (unlocked) {
                card.classList.remove('itinerary-topic-card--locked');
                links.forEach(function(link) {
                    setLinkState(link, true);
                });
                if (lockMessage) {
                    lockMessage.style.display = 'none';
                }
            } else {
                card.classList.add('itinerary-topic-card--locked');
                links.forEach(function(link) {
                    setLinkState(link, false);
                });
                if (lockMessage) {
                    lockMessage.style.display = '';
                }
            }
        }

        function applyTopicLocks(container) {
            var slug = container.getAttribute('data-itinerary-slug') || '';
            if (!slug) {
                return;
            }
            var usageLogic = container.getAttribute('data-usage-logic') || 'free';
            if (usageLogic === 'free') {
                return;
            }
            var progress = ensureStructure(slug);
            var cards = container.querySelectorAll('[data-itinerary-topic]');
            if (!cards.length) {
                return;
            }
            var usesAssessment = usageLogic === 'assessment';
            var baseUnlocked = usesAssessment ? hasPassed(progress, '__presentation') : hasVisited(progress, '__presentation');
            var highestCompletedIndex = -1;
            var cardStates = [];
            cards.forEach(function(card, index) {
                var topicSlug = card.getAttribute('data-topic-slug') || '';
                var completed = usesAssessment ? hasPassed(progress, topicSlug) : hasVisited(progress, topicSlug);
                if (completed && index > highestCompletedIndex) {
                    highestCompletedIndex = index;
                }
                cardStates.push({element: card, completed: completed});
            });
            if (!baseUnlocked && highestCompletedIndex >= 0) {
                baseUnlocked = true;
            }
            var maxUnlockedIndex = baseUnlocked ? Math.min(cards.length - 1, highestCompletedIndex + 1) : -1;
            cardStates.forEach(function(entry, index) {
                var unlocked = false;
                if (entry.completed) {
                    unlocked = true;
                } else if (baseUnlocked && index === (highestCompletedIndex + 1) && index <= maxUnlockedIndex) {
                    unlocked = true;
                }
                toggleTopicCard(entry.element, unlocked);
            });
            var startLink = container.querySelector('[data-first-topic-link]');
            if (startLink) {
                var firstUnlocked = baseUnlocked && maxUnlockedIndex >= 0;
                setLinkState(startLink, firstUnlocked, 'button-disabled');
            }
        }

        var quizBlocks = document.querySelectorAll('[data-itinerary-quiz]');
        quizBlocks.forEach(function(quizBlock) {
            var slug = quizBlock.getAttribute('data-itinerary-slug');
            var topic = quizBlock.getAttribute('data-topic-slug');
            var minCorrect = parseInt(quizBlock.getAttribute('data-min-correct'), 10) || 1;
            var submitBtn = quizBlock.querySelector('[data-quiz-submit]');
            var resultBox = quizBlock.querySelector('[data-quiz-result]');
            if (!slug || !topic || !submitBtn || !resultBox) {
                return;
            }
            submitBtn.addEventListener('click', function() {
                var questions = quizBlock.querySelectorAll('[data-quiz-question]');
                if (!questions.length) {
                    return;
                }
                var correctCount = 0;
                questions.forEach(function(question) {
                    var answers = question.querySelectorAll('[data-quiz-answer]');
                    var isCorrect = true;
                    answers.forEach(function(answer) {
                        var shouldBeChecked = answer.getAttribute('data-correct') === '1';
                        var checked = answer.checked;
                        if (shouldBeChecked !== checked) {
                            isCorrect = false;
                        }
                    });
                    if (isCorrect) {
                        correctCount += 1;
                    }
                });
                var totalQuestions = questions.length;
                var percentage = Math.round((correctCount / totalQuestions) * 100);
                var passed = correctCount >= minCorrect;
                var message = 'Has respondido correctamente el ' + percentage + '% (' + correctCount + ' de ' + totalQuestions + ' preguntas). ';
                message += passed
                    ? 'Has superado el mínimo establecido.'
                    : 'No alcanzas el mínimo de ' + minCorrect + ' preguntas.';
                resultBox.textContent = message;
                resultBox.classList.toggle('text-success', passed);
                resultBox.classList.toggle('text-danger', !passed);
                if (passed) {
                    markPassed(slug, topic);
                    document.dispatchEvent(new CustomEvent('itineraryQuizPassed', {
                        detail: {slug: slug, topic: topic}
                    }));
                }
            });
        });

        var ctaBlock = document.querySelector('[data-itinerary-topic-cta]');
        if (ctaBlock) {
            var slug = ctaBlock.getAttribute('data-itinerary-slug');
            var topic = ctaBlock.getAttribute('data-topic-slug');
            var usageLogic = ctaBlock.getAttribute('data-usage-logic') || 'free';
            var requiresQuiz = ctaBlock.getAttribute('data-requires-quiz') === '1';
            var initialPassed = ctaBlock.getAttribute('data-initial-passed') === '1';
            var nextLink = ctaBlock.querySelector('[data-next-link]');
            var lockedNotice = ctaBlock.querySelector('[data-next-locked]');

            markVisited(slug, topic);

            function setLocked(state) {
                if (!nextLink) {
                    return;
                }
                if (state) {
                    nextLink.classList.add('button-disabled');
                    nextLink.setAttribute('aria-disabled', 'true');
                    nextLink.setAttribute('tabindex', '-1');
                    if (lockedNotice) {
                        lockedNotice.style.display = '';
                    }
                } else {
                    nextLink.classList.remove('button-disabled');
                    nextLink.removeAttribute('aria-disabled');
                    nextLink.removeAttribute('tabindex');
                    if (lockedNotice) {
                        lockedNotice.style.display = 'none';
                    }
                }
            }

            var locked = requiresQuiz && !initialPassed;
            setLocked(locked);

            if (nextLink) {
                nextLink.addEventListener('click', function(event) {
                    if (requiresQuiz && locked) {
                        event.preventDefault();
                    }
                });
            }

            document.addEventListener('itineraryQuizPassed', function(event) {
                if (!event.detail || event.detail.slug !== slug || event.detail.topic !== topic) {
                    return;
                }
                locked = false;
                setLocked(false);
            });
        }

        var topicContainers = document.querySelectorAll('[data-itinerary-topics]');
        topicContainers.forEach(function(container) {
            var slug = container.getAttribute('data-itinerary-slug');
            if (!slug) {
                return;
            }
            var usageLogic = container.getAttribute('data-usage-logic') || 'free';
            var presentationQuiz = container.getAttribute('data-presentation-quiz') === '1';
            markVisited(slug, '__presentation');
            if (usageLogic === 'assessment' && !presentationQuiz) {
                markPassed(slug, '__presentation');
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

            syncHeight();
            if (typeof ResizeObserver !== 'undefined') {
                var resizeObserver = new ResizeObserver(function() {
                    syncHeight();
                });
                resizeObserver.observe(block);
            } else {
                window.addEventListener('resize', syncHeight);
            }

            block.dataset.pdfEnhanced = '1';
        });
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
        function restack() {
            var isMobile = window.matchMedia('(max-width: 720px)').matches;
            var offset = isMobile ? 16 : (parseInt(getComputedStyle(document.documentElement).fontSize, 10) * 2.5);
            var gap = isMobile ? 12 : 14;
            stack.forEach(function(el) {
                el.style.bottom = offset + 'px';
                offset += el.offsetHeight + gap;
            });
        }
        window.addEventListener('resize', restack);
        restack();
    })();
    </script>
</body>
</html>
