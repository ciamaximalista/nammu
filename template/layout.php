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
$fontLink = $theme['fontUrl'] ?? null;
$colorBackground = htmlspecialchars($themeColors['background'] ?? '#ffffff', ENT_QUOTES, 'UTF-8');
$colorText = htmlspecialchars($themeColors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
$colorH1 = htmlspecialchars($themeColors['h1'] ?? '#1b8eed', ENT_QUOTES, 'UTF-8');
$colorH2 = htmlspecialchars($themeColors['h2'] ?? '#ea2f28', ENT_QUOTES, 'UTF-8');
$colorH3 = htmlspecialchars($themeColors['h3'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$colorHighlight = htmlspecialchars($themeColors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$colorAccent = htmlspecialchars($themeColors['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8');
$colorBrand = htmlspecialchars($themeColors['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$footerHtml = $theme['footer_html'] ?? '';
$logoUrl = $theme['logo_url'] ?? null;
$faviconUrl = $theme['favicon_url'] ?? null;
$showLogo = $showLogo ?? false;
$socialMeta = $socialMeta ?? [];
$themeGlobal = $theme['global'] ?? [];
$cornerStyle = $theme['corners'] ?? ($themeGlobal['corners'] ?? 'rounded');
$cornerClass = $cornerStyle === 'square' ? 'corners-square' : 'corners-rounded';
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
        @media (max-width: 1024px) {
            .floating-logo {
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
</body>
</html>
