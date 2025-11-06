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
$logoUrl = $theme['logo_url'] ?? null;
$faviconUrl = $theme['favicon_url'] ?? null;
$showLogo = $showLogo ?? false;
$socialMeta = $socialMeta ?? [];
$themeGlobal = $theme['global'] ?? [];
$cornerStyle = $theme['corners'] ?? ($themeGlobal['corners'] ?? 'rounded');
$cornerClass = $cornerStyle === 'square' ? 'corners-square' : 'corners-rounded';
$searchSettings = $theme['search'] ?? [];
$searchFloatingEnabled = ($searchSettings['floating'] ?? 'off') === 'on';
$baseHref = $baseUrl ?? '/';
$searchBaseNormalized = rtrim($baseHref === '' ? '/' : $baseHref, '/');
$floatingSearchAction = $searchBaseNormalized === '' ? '/buscar.php' : $searchBaseNormalized . '/buscar.php';
$floatingCategoriesUrl = $searchBaseNormalized === '' ? '/categorias' : $searchBaseNormalized . '/categorias';
$showFloatingSearch = $searchFloatingEnabled && !empty($showLogo) && !empty($logoUrl);
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
            margin: 0;
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
        .floating-search {
            position: fixed;
            top: calc(2.5rem + 48px + 0.9rem);
            right: clamp(1.5rem, 5vw, 2.5rem);
            width: clamp(210px, 24vw, 250px);
            background: rgba(255, 255, 255, 0.96);
            border-radius: var(--nammu-radius-md);
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 18px 32px rgba(0,0,0,0.12);
            padding: 0.6rem 0.75rem;
            z-index: 1099;
            backdrop-filter: blur(6px);
        }
        .floating-search-form {
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }
        .floating-search-icon {
            width: 34px;
            height: 34px;
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
        .floating-search-form input[type="text"] {
            flex: 1 1 auto;
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.12);
            padding: 0.35rem 0.25rem;
            font-size: 0.95rem;
            background: transparent;
            color: <?= $colorText ?>;
        }
        .floating-search-form input[type="text"]::placeholder {
            color: rgba(0,0,0,0.5);
        }
        .floating-search-form input[type="text"]:focus {
            outline: none;
            border-color: <?= $colorAccent ?>;
        }
        .floating-search-form button {
            border: none;
            background: <?= $colorAccent ?>;
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex: 0 0 auto;
        }
        .floating-search-form button svg {
            display: block;
        }
        .floating-search-categories {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: <?= $colorHighlight ?>;
            border: 1px solid rgba(0,0,0,0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.2s ease, border-color 0.2s ease;
            flex: 0 0 auto;
        }
        .floating-search-categories:hover {
            background: rgba(0,0,0,0.08);
            border-color: rgba(0,0,0,0.12);
        }
        @media (max-width: 1024px) {
            .floating-logo {
                display: none;
            }
            .floating-search {
                display: none;
            }
        }
    </style>
</head>
<body class="<?= htmlspecialchars($cornerClass, ENT_QUOTES, 'UTF-8') ?>">
    <div class="wrapper">
        <main>
            <?= $content ?>
        </main>
        <?php if ($footerHtml !== ''): ?>
            <footer>
                <div class="site-footer-block">
                    <?= $footerHtml ?>
                </div>
            </footer>
        <?php endif; ?>
    </div>
    <?php if (!empty($showLogo) && !empty($logoUrl)): ?>
        <a class="floating-logo" href="<?= htmlspecialchars($baseUrl ?? '/', ENT_QUOTES, 'UTF-8') ?>" aria-label="Ir a la portada">
            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo del blog">
        </a>
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
            </form>
        </div>
    <?php endif; ?>
</body>
</html>
