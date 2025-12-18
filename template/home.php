<?php
/**
 * @var array<int, array{slug:string,title:string,description:string,date:string,image:?string}> $posts
 * @var string $bioHtml
 * @var callable $resolveImage
 * @var callable $postUrl
 * @var array|null $pagination
 * @var callable $paginationUrl
 * @var array $theme
 * @var array<string, string> $letterGroupUrls
 * @var bool $isAlphabetical
 */
$colors = $theme['colors'] ?? [];
$highlight = htmlspecialchars($colors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$textColor = htmlspecialchars($colors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
$metaColor = htmlspecialchars($colors['h3'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$accentColor = htmlspecialchars($colors['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8');
$accentRaw = $colors['accent'] ?? '#0a4c8a';
$accentBackground = 'rgba(0, 0, 0, 0.08)';
$accentBorder = 'rgba(0, 0, 0, 0.18)';
if (is_string($accentRaw)) {
    if (preg_match('/^#([0-9a-f]{6})$/i', $accentRaw, $matches)) {
        $hex = $matches[1];
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $accentBackground = sprintf('rgba(%d, %d, %d, 0.12)', $r, $g, $b);
        $accentBorder = sprintf('rgba(%d, %d, %d, 0.28)', $r, $g, $b);
    } elseif (preg_match('/^#([0-9a-f]{3})$/i', $accentRaw, $matches)) {
        $hex = $matches[1];
        $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
        $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
        $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        $accentBackground = sprintf('rgba(%d, %d, %d, 0.12)', $r, $g, $b);
        $accentBorder = sprintf('rgba(%d, %d, %d, 0.28)', $r, $g, $b);
    }
}
$brandColor = htmlspecialchars($colors['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$headingColor = htmlspecialchars($colors['h1'] ?? '#1b8eed', ENT_QUOTES, 'UTF-8');
$headingSecondaryColor = htmlspecialchars($colors['h2'] ?? '#ea2f28', ENT_QUOTES, 'UTF-8');
$homeSettings = $theme['home'] ?? [];
$columns = (int) ($homeSettings['columns'] ?? 2);
if ($columns < 1 || $columns > 3) {
    $columns = 2;
}
$homeFirstRowEnabled = (($homeSettings['first_row_enabled'] ?? 'off') === 'on');
$homeFirstRowColumns = (int) ($homeSettings['first_row_columns'] ?? $columns);
if ($homeFirstRowColumns < 1 || $homeFirstRowColumns > 3) {
    $homeFirstRowColumns = $columns;
}
$homeFirstRowFill = (($homeSettings['first_row_fill'] ?? 'off') === 'on');
$homeFirstRowAlign = $homeSettings['first_row_align'] ?? 'left';
if (!in_array($homeFirstRowAlign, ['left', 'center'], true)) {
    $homeFirstRowAlign = 'left';
}
$homeFirstRowStyle = $homeSettings['first_row_style'] ?? 'inherit';
if (!in_array($homeFirstRowStyle, ['inherit', 'boxed', 'flat'], true)) {
    $homeFirstRowStyle = 'inherit';
}
$pagination = $pagination ?? null;
$hasPagination = is_array($pagination) && ($pagination['total'] ?? 1) > 1;
$currentPage = 1;
if (is_array($pagination) && isset($pagination['current']) && (int) $pagination['current'] > 0) {
    $currentPage = (int) $pagination['current'];
}
$baseHref = $baseUrl ?? '/';
$cardStyle = $homeSettings['card_style'] ?? 'full';
$cardStyle = in_array($cardStyle, ['full', 'square-right', 'square-tall-right', 'circle-right'], true) ? $cardStyle : 'full';
$fullImageMode = $homeSettings['full_image_mode'] ?? 'natural';
if (!in_array($fullImageMode, ['natural', 'crop'], true)) {
    $fullImageMode = 'natural';
}
$blocksMode = $homeSettings['blocks'] ?? 'boxed';
if (!in_array($blocksMode, ['boxed', 'flat'], true)) {
    $blocksMode = 'boxed';
}
$searchSettings = $theme['search'] ?? [];
$searchMode = in_array($searchSettings['mode'] ?? 'none', ['none', 'home', 'single', 'both'], true) ? $searchSettings['mode'] : 'none';
$searchPositionSetting = in_array($searchSettings['position'] ?? 'title', ['title', 'footer'], true) ? $searchSettings['position'] : 'title';
$showHomeSearch = in_array($searchMode, ['home', 'both'], true);
$homeSearchTop = $showHomeSearch && $searchPositionSetting === 'title';
$homeSearchBottom = $showHomeSearch && $searchPositionSetting === 'footer';
$subscriptionSettings = is_array($theme['subscription'] ?? null) ? $theme['subscription'] : [];
$subscriptionModeValue = $subscriptionSettings['mode'] ?? 'none';
$subscriptionPositionValue = $subscriptionSettings['position'] ?? 'footer';
$subscriptionMode = in_array($subscriptionModeValue, ['none', 'home', 'single', 'both'], true) ? $subscriptionModeValue : 'none';
$subscriptionPositionSetting = in_array($subscriptionPositionValue, ['title', 'footer'], true) ? $subscriptionPositionValue : 'footer';
$showHomeSubscription = in_array($subscriptionMode, ['home', 'both'], true);
$homeSubscriptionTop = $showHomeSubscription && $subscriptionPositionSetting === 'title';
$homeSubscriptionBottom = $showHomeSubscription && $subscriptionPositionSetting === 'footer';
$searchActionBase = $baseHref ?? '/';
$searchAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/buscar.php';
$subscriptionAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/subscribe.php';
$letterIndexUrlValue = $lettersIndexUrl ?? null;
$itinerariesIndexUrl = $itinerariesIndexUrl ?? (($baseHref ?? '/') !== '' ? rtrim($baseHref ?? '/', '/') . '/itinerarios' : '/itinerarios');
$hasItineraries = !empty($hasItineraries);
$showLetterButton = !empty($showLetterIndexButton) && !empty($letterIndexUrlValue);
$isAlphabetical = !empty($isAlphabetical);
$letterGroups = $letterGroups ?? [];
$letterGroupUrls = $letterGroupUrls ?? [];
$currentUrl = ($baseHref ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
$subscriptionSuccess = isset($_GET['subscribed']) && $_GET['subscribed'] === '1';
$subscriptionSent = isset($_GET['sub_sent']) && $_GET['sub_sent'] === '1';
$subscriptionError = isset($_GET['sub_error']) && $_GET['sub_error'] === '1';
$subscriptionMessage = '';
if ($subscriptionSuccess) {
    $subscriptionMessage = 'Suscripción confirmada. ¡Gracias!';
} elseif ($subscriptionSent) {
    $subscriptionMessage = 'Hemos enviado un email de confirmación. Revisa tu correo.';
} elseif ($subscriptionError) {
    $subscriptionMessage = 'No pudimos procesar ese correo. Revisa la dirección e inténtalo de nuevo.';
}
$renderSearchBox = static function (string $variant) use ($searchAction, $accentColor, $highlight, $textColor, $searchActionBase, $letterIndexUrlValue, $showLetterButton, $hasItineraries, $itinerariesIndexUrl): string {
    ob_start(); ?>
    <div class="site-search-box <?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?>">
        <form class="site-search-form" method="get" action="<?= htmlspecialchars($searchAction, ENT_QUOTES, 'UTF-8') ?>">
            <span class="search-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="6" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <line x1="12.5" y1="12.5" x2="17" y2="17" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </span>
            <input type="text" name="q" placeholder="Buscar en el sitio..." required>
            <button type="submit" aria-label="Buscar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 4L9 16L4 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <a class="search-categories-link" href="<?= htmlspecialchars(rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/categorias', ENT_QUOTES, 'UTF-8') ?>" aria-label="Índice de categorías">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <line x1="8" y1="9" x2="16" y2="9" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <line x1="8" y1="13" x2="16" y2="13" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                </svg>
            </a>
            <?php if ($showLetterButton && $letterIndexUrlValue): ?>
                <a class="search-letters-link" href="<?= htmlspecialchars($letterIndexUrlValue, ENT_QUOTES, 'UTF-8') ?>" aria-label="Índice alfabético">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 18L9 6L13 18" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="6.5" y1="13" x2="11.5" y2="13" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                        <path d="M15 6H20L15 18H20" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (!empty($hasItineraries) && !empty($itinerariesIndexUrl)): ?>
                <a class="search-itineraries-link" href="<?= htmlspecialchars($itinerariesIndexUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Itinerarios">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 5H10C11.1046 5 12 5.89543 12 7V19H4C2.89543 19 2 18.1046 2 17V7C2 5.89543 2.89543 5 4 5Z" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M20 5H14C12.8954 5 12 5.89543 12 7V19H20C21.1046 19 22 18.1046 22 17V7C22 5.89543 21.1046 5 20 5Z" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round"/>
                        <line x1="12" y1="7" x2="12" y2="19" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </a>
            <?php endif; ?>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
};
$renderSubscriptionBox = static function (string $variant) use ($subscriptionAction, $accentColor, $highlight, $textColor, $subscriptionMessage, $currentUrl): string {
    ob_start(); ?>
    <div class="site-search-box <?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?> site-subscription-box">
        <form class="site-search-form subscription-form" method="post" action="<?= htmlspecialchars($subscriptionAction, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="back" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>">
            <span class="search-icon subscription-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <polyline points="3,7 12,13 21,7" fill="none" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <input type="email" name="subscriber_email" placeholder="email@dominio.com" required>
            <button type="submit" aria-label="Suscribirme" title="Suscribirme" style="background: <?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>; color:#fff;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="6" width="16" height="12" rx="2" fill="none" stroke="white" stroke-width="2"/>
                    <polyline points="4,8 12,14 20,8" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>
        <?php if ($subscriptionMessage !== ''): ?>
            <div class="subscription-feedback" style="color: <?= htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8') ?>; background: <?= htmlspecialchars($highlight, ENT_QUOTES, 'UTF-8') ?>; border-radius: 10px; padding:10px; margin-top:10px; font-size:14px;">
                <?= htmlspecialchars($subscriptionMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
};
$renderPostCards = static function (array $subset, bool $hideMeta = false) use ($postUrl, $resolveImage, $cardStyle, $fullImageMode, $blocksMode, $baseHref, $headingSecondaryColor, $accentColor, $accentBackground, $accentBorder): string {
    ob_start();
    foreach ($subset as $post) {
        $link = $postUrl($post['slug']);
        $imageUrl = $resolveImage($post['image']);
        $cardClassParts = ['post-card', 'style-' . $cardStyle];
        if ($cardStyle === 'full') {
            $cardClassParts[] = 'full-mode-' . $fullImageMode;
        } elseif (in_array($cardStyle, ['square-right', 'circle-right'], true)) {
            $cardClassParts[] = 'style-media-right';
        }
        $cardClass = implode(' ', $cardClassParts);
        $thumbClassParts = ['post-thumb'];
        if ($cardStyle === 'full') {
            $thumbClassParts[] = 'thumb-wide';
        } else {
            $thumbClassParts[] = 'thumb-right';
            if ($cardStyle === 'square-right') {
                $thumbClassParts[] = 'thumb-square';
            } elseif ($cardStyle === 'square-tall-right') {
                $thumbClassParts[] = 'thumb-vertical';
            } else {
                $thumbClassParts[] = 'thumb-circle';
            }
        }
        $thumbClass = implode(' ', $thumbClassParts);
        $category = $post['category'] ?? '';
        $metaText = '';
        if (($post['date'] ?? '') !== '') {
            $metaText = 'Publicado el ' . $post['date'];
        }
        $categoryLinkHtml = '';
        if ($category !== '') {
            $categorySlug = nammu_slugify_label($category);
            $categoryUrl = ($baseHref ?? '/') !== '' ? rtrim($baseHref, '/') . '/categoria/' . rawurlencode($categorySlug) : '/categoria/' . rawurlencode($categorySlug);
            $categoryLinkHtml = '<a class="category-tag-link" href="' . htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . '</a>';
            $metaText .= $metaText !== '' ? ' · ' . $categoryLinkHtml : $categoryLinkHtml;
        }
        ?>
        <article class="<?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($imageUrl): ?>
                <a class="<?= htmlspecialchars($thumbClass, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
                </a>
            <?php endif; ?>
            <div class="post-body">
                <h2><a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
                <?php if (!$hideMeta && $metaText !== ''): ?>
                    <p class="post-meta"><?= $metaText ?></p>
                <?php endif; ?>
                <?php if (($post['description'] ?? '') !== ''): ?>
                    <p class="post-description"><?= htmlspecialchars($post['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }
    return (string) ob_get_clean();
};
$headerConfig = $homeSettings['header'] ?? [];
$headerTypes = ['none', 'graphic', 'text', 'mixed'];
$headerType = in_array($headerConfig['type'] ?? 'none', $headerTypes, true) ? $headerConfig['type'] : 'none';
$headerImageSetting = trim($headerConfig['image'] ?? '');
$headerMode = in_array($headerConfig['mode'] ?? 'contain', ['contain', 'cover'], true) ? $headerConfig['mode'] : 'contain';
$textHeaderStyle = $headerConfig['text_style'] ?? 'boxed';
if (!in_array($textHeaderStyle, ['boxed', 'plain'], true)) {
    $textHeaderStyle = 'boxed';
}
$headerOrder = $headerConfig['order'] ?? 'image-text';
if (!in_array($headerOrder, ['image-text', 'text-image'], true)) {
    $headerOrder = 'image-text';
}

$headerImageUrl = null;
if ($headerImageSetting !== '') {
    $resolvedImage = $resolveImage($headerImageSetting);
    if ($resolvedImage !== null) {
        $headerImageUrl = $resolvedImage;
    }
}
$hasHeaderImage = $headerImageUrl !== null;

$homeBrandTitle = $theme['author'] ?? '';
$homeHeroTitle = $theme['blog'] ?? $siteTitle ?? '';
$defaultDescription = $socialConfig['default_description'] ?? '';
$homeHeroTagline = $defaultDescription !== '' ? $defaultDescription : ($siteDescription ?? '');
$hasTextHeaderContent = ($homeBrandTitle !== '' || $homeHeroTitle !== '' || $homeHeroTagline !== '');

if ($headerType === 'mixed' && !$hasHeaderImage && !$hasTextHeaderContent) {
    $headerType = 'none';
} elseif ($headerType === 'mixed' && !$hasHeaderImage) {
    $headerType = 'text';
} elseif ($headerType === 'mixed' && !$hasTextHeaderContent) {
    $headerType = 'graphic';
}

if ($headerType === 'graphic' && !$hasHeaderImage) {
    $headerType = 'none';
}

if (($headerType === 'text' || $headerType === 'mixed') && !$hasTextHeaderContent) {
    if ($headerType === 'mixed' && $hasHeaderImage) {
        $headerType = 'graphic';
    } else {
        $headerType = 'none';
    }
}
$mixedBoxedHeader = $headerType === 'mixed' && $textHeaderStyle === 'boxed';
$mixedHasImage = $headerType === 'mixed' && $hasHeaderImage;
$mixedBoxedFullWidth = $mixedBoxedHeader && $headerMode === 'cover';
$renderHomeHeroText = static function () use ($homeBrandTitle, $homeHeroTitle, $homeHeroTagline): void {
    if ($homeBrandTitle !== '') {
        ?>
        <div class="home-brand">
            <span class="home-brand-title"><?= htmlspecialchars($homeBrandTitle, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php
    }
    if ($homeHeroTitle !== '') {
        ?>
        <h1><?= htmlspecialchars($homeHeroTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <?php
    }
    if ($homeHeroTagline !== '') {
        ?>
        <p class="home-hero-tagline"><?= htmlspecialchars($homeHeroTagline, ENT_QUOTES, 'UTF-8') ?></p>
        <?php
    }
};
$buildPageUrl = (isset($paginationUrl) && is_callable($paginationUrl))
    ? $paginationUrl
    : static function (int $page) use ($baseHref): string {
        $normalizedBase = rtrim((string) $baseHref, '/');
        if ($normalizedBase === '/') {
            $normalizedBase = '';
        }
        if ($page <= 1) {
            return $normalizedBase === '' ? '/' : $normalizedBase . '/';
        }
        $path = '/pagina/' . $page;
        return $normalizedBase === '' ? $path : $normalizedBase . $path;
    };
?>
<?php if ($headerType === 'graphic' && $headerImageUrl): ?>
    <section class="home-hero home-hero-graphic mode-<?= htmlspecialchars($headerMode, ENT_QUOTES, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars($headerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cabecera del sitio">
    </section>
<?php elseif ($headerType === 'mixed'): ?>
    <?php
    $mixedLayoutClass = $mixedBoxedHeader ? 'mixed-boxed' : 'mixed-split';
    $mixedOrderClass = 'order-' . $headerOrder;
    $mixedSectionClasses = trim($mixedLayoutClass . ' ' . $mixedOrderClass);
    $textClassParts = [
        'home-hero-text',
        'variant-' . $textHeaderStyle,
    ];
    if ($mixedBoxedHeader && $mixedHasImage) {
        $textClassParts[] = 'mixed-has-image';
    }
    if ($mixedBoxedFullWidth) {
        $textClassParts[] = 'full-width';
    }
    $textClasses = implode(' ', $textClassParts);
    ?>
    <section class="home-hero home-hero-mixed <?= htmlspecialchars($mixedSectionClasses, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($mixedBoxedHeader): ?>
            <div class="<?= htmlspecialchars($textClasses, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($headerOrder === 'image-text' && $hasHeaderImage): ?>
                    <?php
                    $insideGraphicClass = 'home-hero-graphic inside mode-' . $headerMode;
                    if ($mixedBoxedFullWidth) {
                        $insideGraphicClass .= ' full';
                    }
                    ?>
                    <div class="<?= htmlspecialchars($insideGraphicClass, ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= htmlspecialchars($headerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cabecera del sitio">
                    </div>
                <?php endif; ?>
                <?php $renderHomeHeroText(); ?>
                <?php if ($headerOrder === 'text-image' && $hasHeaderImage): ?>
                    <?php
                    $insideGraphicClass = 'home-hero-graphic inside mode-' . $headerMode;
                    if ($mixedBoxedFullWidth) {
                        $insideGraphicClass .= ' full';
                    }
                    ?>
                    <div class="<?= htmlspecialchars($insideGraphicClass, ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= htmlspecialchars($headerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cabecera del sitio">
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if ($headerOrder === 'image-text' && $hasHeaderImage): ?>
                <div class="home-hero-graphic mode-<?= htmlspecialchars($headerMode, ENT_QUOTES, 'UTF-8') ?> full">
                    <img src="<?= htmlspecialchars($headerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cabecera del sitio">
                </div>
            <?php endif; ?>
            <div class="<?= htmlspecialchars($textClasses, ENT_QUOTES, 'UTF-8') ?>">
                <?php $renderHomeHeroText(); ?>
            </div>
            <?php if ($headerOrder === 'text-image' && $hasHeaderImage): ?>
                <div class="home-hero-graphic mode-<?= htmlspecialchars($headerMode, ENT_QUOTES, 'UTF-8') ?> full">
                    <img src="<?= htmlspecialchars($headerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cabecera del sitio">
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php elseif ($headerType === 'text'): ?>
    <section class="home-hero home-hero-text variant-<?= htmlspecialchars($textHeaderStyle, ENT_QUOTES, 'UTF-8') ?>">
        <?php $renderHomeHeroText(); ?>
    </section>
<?php endif; ?>

<?php if ($homeSearchTop): ?>
    <section class="site-search-block placement-top">
        <?= $renderSearchBox('variant-inline minimal') ?>
    </section>
<?php endif; ?>
<?php if ($homeSubscriptionTop): ?>
    <section class="site-search-block placement-top site-subscription-block">
        <?= $renderSubscriptionBox('variant-inline minimal') ?>
    </section>
<?php endif; ?>

<?php if ($bioHtml !== ''): ?>
    <section class="site-bio">
        <?= $bioHtml ?>
    </section>
<?php endif; ?>

<?php if ($isAlphabetical && !empty($letterGroups)): ?>
    <?php foreach ($letterGroups as $letter => $groupPosts): ?>
        <?php $letterHeadingUrl = $letterGroupUrls[$letter] ?? null; ?>
        <section class="letter-block">
            <div class="letter-heading-wrapper">
                <?php if ($letterHeadingUrl): ?>
                    <a class="letter-heading-link" href="<?= htmlspecialchars($letterHeadingUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars(nammu_letter_display_name($letter), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php else: ?>
                    <span class="letter-heading-link">
                        <?= htmlspecialchars(nammu_letter_display_name($letter), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>
            <section class="post-grid columns-<?= $columns ?> blocks-<?= htmlspecialchars($blocksMode, ENT_QUOTES, 'UTF-8') ?>">
                <?= $renderPostCards($groupPosts, true) ?>
            </section>
        </section>
    <?php endforeach; ?>
<?php elseif (!empty($posts)): ?>
    <?php
        $firstRowPosts = [];
        $remainingPosts = $posts;
        $allowFirstRow = $homeFirstRowEnabled && $currentPage === 1;
        if ($allowFirstRow && !empty($posts)) {
            $firstRowPosts = array_slice($posts, 0, $homeFirstRowColumns);
            $remainingPosts = array_slice($posts, $homeFirstRowColumns);
            if ($homeFirstRowFill && !empty($remainingPosts)) {
                $mod = count($remainingPosts) % $columns;
                if ($columns > 0 && $mod !== 0) {
                    $needed = $columns - $mod;
                    $sourceCount = count($remainingPosts);
                    for ($i = 0; $i < $needed && $sourceCount > 0; $i++) {
                        // Usa los más antiguos de la página para completar sin alterar el orden cronológico
                        $index = $sourceCount - 1 - ($i % $sourceCount);
                        $remainingPosts[] = $remainingPosts[$index];
                    }
                }
            }
        }
    ?>
    <?php if (!empty($firstRowPosts)): ?>
        <?php
            $firstRowClasses = [];
            if ($homeFirstRowColumns === 1 && $homeFirstRowAlign === 'center') {
                $firstRowClasses[] = 'first-row-center';
            }
            if ($homeFirstRowStyle === 'flat') {
                $firstRowClasses[] = 'first-row-flat';
            } elseif ($homeFirstRowStyle === 'boxed') {
                $firstRowClasses[] = 'first-row-boxed';
            }
            $firstRowClassAttr = count($firstRowClasses) ? ' ' . htmlspecialchars(implode(' ', $firstRowClasses), ENT_QUOTES, 'UTF-8') : '';
            $firstRowBlocksMode = $homeFirstRowStyle === 'inherit' ? $blocksMode : ($homeFirstRowStyle === 'flat' ? 'flat' : 'boxed');
        ?>
        <section class="post-grid columns-<?= $homeFirstRowColumns ?> blocks-<?= htmlspecialchars($firstRowBlocksMode, ENT_QUOTES, 'UTF-8') ?><?= $firstRowClassAttr ?>">
            <?= $renderPostCards($firstRowPosts, false) ?>
        </section>
    <?php endif; ?>
    <?php if (!empty($remainingPosts)): ?>
        <section class="post-grid columns-<?= $columns ?> blocks-<?= htmlspecialchars($blocksMode, ENT_QUOTES, 'UTF-8') ?>">
            <?= $renderPostCards($remainingPosts, false) ?>
        </section>
    <?php endif; ?>
    <?php if (empty($firstRowPosts) && empty($remainingPosts)): ?>
        <p>No hay publicaciones disponibles todavía.</p>
    <?php endif; ?>
<?php else: ?>
    <p>No hay publicaciones disponibles todavía.</p>
<?php endif; ?>

<?php if ($hasPagination): ?>
    <?php
    $totalPages = (int) ($pagination['total'] ?? 1);
    $hasPrev = !empty($pagination['has_prev']) && $currentPage > 1;
    $hasNext = !empty($pagination['has_next']) && $currentPage < $totalPages;
    ?>
    <nav class="home-pagination" aria-label="Paginación">
        <?php if ($hasPrev): ?>
            <a class="page-link prev" href="<?= htmlspecialchars($buildPageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') ?>">&laquo; Anteriores</a>
        <?php else: ?>
            <span class="page-link prev disabled">&laquo; Anteriores</span>
        <?php endif; ?>

        <span class="page-status">Página <?= htmlspecialchars((string) $currentPage, ENT_QUOTES, 'UTF-8') ?> de <?= htmlspecialchars((string) $totalPages, ENT_QUOTES, 'UTF-8') ?></span>

        <?php if ($hasNext): ?>
            <a class="page-link next" href="<?= htmlspecialchars($buildPageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') ?>">Siguientes &raquo;</a>
        <?php else: ?>
            <span class="page-link next disabled">Siguientes &raquo;</span>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php if ($homeSearchBottom): ?>
    <section class="site-search-block placement-bottom">
        <?= $renderSearchBox('variant-panel') ?>
    </section>
<?php endif; ?>
<?php if ($homeSubscriptionBottom): ?>
    <section class="site-search-block placement-bottom site-subscription-block">
        <?= $renderSubscriptionBox('variant-panel') ?>
    </section>
<?php endif; ?>

<style>
    .home-hero {
        margin-bottom: 2rem;
    }
    .site-search-block {
        margin: 1.5rem auto 2rem;
        max-width: min(760px, 100%);
    }
    .site-search-block.placement-top {
        margin: 0.75rem auto 1.25rem;
    }
    .site-search-box {
        background: <?= $highlight ?>;
        border-radius: var(--nammu-radius-lg);
        padding: 1rem 1.25rem;
        border: 1px solid rgba(0,0,0,0.05);
    }
    .site-search-box.variant-panel {
        background: #fff;
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    }
    .site-search-box.minimal {
        background: transparent;
        border: none;
        padding: 0;
    }
    .site-search-form {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .site-search-form input[type="text"],
    .site-search-form input[type="email"] {
        flex: 1;
        padding: 0.75rem 1rem;
        border-radius: var(--nammu-radius-md);
        border: 1px solid rgba(0,0,0,0.1);
        font-size: 1rem;
    }
    .site-search-form button {
        border: none;
        background: <?= $accentColor ?>;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .search-categories-link,
    .search-letters-link,
    .search-itineraries-link {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(0,0,0,0.05);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: background 0.2s ease;
    }
    .search-categories-link:hover,
    .search-letters-link:hover {
        background: rgba(0,0,0,0.1);
    }
    .site-search-form button svg {
        display: block;
    }
    .site-search-form .search-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(0,0,0,0.04);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .site-search-form input:focus {
        outline: 2px solid <?= $accentColor ?>;
        border-color: <?= $accentColor ?>;
    }
    .site-subscription-box .site-search-form {
        flex-wrap: nowrap;
    }
    .site-subscription-box .site-search-form input[type="email"] {
        flex: 1 1 240px;
        padding: 0.75rem 1rem;
        border-radius: var(--nammu-radius-md);
        border: 1px solid rgba(0,0,0,0.1);
        font-size: 1rem;
    }
    .site-subscription-box .site-search-form button {
        padding: 0;
        height: 44px;
        width: 44px;
    }
    .site-subscription-box .subscription-feedback {
        border: 1px solid rgba(0,0,0,0.05);
    }
    .search-categories-link,
    .search-letters-link {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(0,0,0,0.05);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: background 0.2s ease;
    }
    .search-categories-link:hover,
    .search-letters-link:hover,
    .search-itineraries-link:hover {
        background: rgba(0,0,0,0.12);
    }
    .home-hero-graphic {
        min-height: 160px;
        border-radius: var(--nammu-radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .home-hero-graphic.full {
        width: 100%;
    }
    .home-hero-graphic.full img {
        width: 100%;
    }
    .home-hero-graphic img {
        display: block;
        border-radius: inherit;
    }
    .home-hero-graphic.mode-contain img {
        max-height: 160px;
        width: auto;
        max-width: 100%;
    }
    .home-hero-graphic.mode-cover img {
        width: 100%;
        height: 160px;
        object-fit: cover;
    }

    .home-hero-mixed {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        align-items: stretch;
    }
    .home-hero-mixed.mixed-split .home-hero-graphic {
        margin: 0 auto;
        width: 100%;
        max-width: min(960px, 100%);
    }
    .home-hero-mixed .home-hero-text {
        width: 100%;
    }
    .home-hero-text {
        display: grid;
        gap: 0.75rem;
        text-align: center;
        border-radius: var(--nammu-radius-lg);
        padding: 2rem clamp(1.5rem, 4vw, 3rem);
        border: 1px solid transparent;
        max-width: min(760px, 100%);
        margin: 0 auto;
        box-sizing: border-box;
    }
    .home-hero-text.variant-boxed {
        background: <?= $highlight ?>;
        border-color: rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .home-hero-text.variant-plain {
        background: transparent;
        border: none;
    }
    .home-hero-text.variant-boxed .home-hero-graphic {
        border-radius: 0;
    }
    .home-hero-text.mixed-has-image {
        gap: 1.25rem;
    }
    .home-hero-text.mixed-has-image .home-hero-graphic {
        margin: 0;
        width: 100%;
    }
    .home-hero-text.mixed-has-image .home-hero-graphic img {
        display: block;
        max-width: 100%;
        height: auto;
    }
    .home-hero-text.full-width {
        max-width: none;
        width: 100%;
        margin-left: auto;
        margin-right: auto;
    }
    .home-brand {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        justify-content: center;
        align-items: center;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        font-size: 0.85rem;
        color: <?= $brandColor ?>;
    }
    .home-brand-title {
        font-weight: 700;
    }
    .home-brand-subtitle {
        font-weight: 400;
    }
    .home-hero-text h1 {
        margin: 0;
        font-size: clamp(2rem, 5vw, 3rem);
        line-height: 1.1;
        color: <?= $headingColor ?>;
    }
    .home-hero-tagline {
        margin: 0;
        font-size: 1.05rem;
        color: <?= $brandColor ?>;
        max-width: 720px;
        margin-left: auto;
        margin-right: auto;
    }
    .site-bio p {
        margin: 0 0 1rem 0;
    }
    .letter-block {
        margin-bottom: 2.5rem;
    }
    .letter-heading-wrapper {
        text-align: center;
        margin-bottom: 1rem;
    }
    .letter-heading-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: clamp(2.2rem, 6vw, 3.6rem);
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: <?= $headingSecondaryColor ?>;
        text-decoration: none;
        border-bottom: 2px solid transparent;
    }
    .letter-heading-link:hover {
        border-color: <?= $accentColor ?>;
    }
    .post-grid {
        display: grid;
        gap: 1.6rem;
        margin-bottom: 1.6rem;
    }
    .post-grid.first-row-center .post-card,
    .post-grid.first-row-center .post-card h2,
    .post-grid.first-row-center .post-meta,
    .post-grid.first-row-center .post-description {
        text-align: center;
    }
    .post-grid.first-row-center .post-card .post-thumb {
        margin-left: auto;
        margin-right: auto;
    }
    .post-grid.first-row-center.columns-1 .post-card h2 {
        font-size: clamp(1.8rem, 3.6vw, 2.4rem);
    }
    .post-grid.columns-1 {
        grid-template-columns: minmax(0, 1fr);
    }
    .post-grid.columns-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .post-grid.columns-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    @media (max-width: 900px) {
        .post-grid.columns-3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 640px) {
        .post-grid.columns-2,
        .post-grid.columns-3 {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    .post-card {
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: var(--nammu-radius-md);
        padding: 1.15rem;
        background: <?= $highlight ?>;
        color: <?= $textColor ?>;
        position: relative;
        overflow: hidden;
        box-sizing: border-box;
    }
    .post-card::after {
        content: '';
        display: table;
        clear: both;
    }
    .post-card .post-thumb {
        display: block;
    }
    .post-card .post-thumb img {
        width: 100%;
        display: block;
        object-fit: cover;
        border-radius: var(--nammu-radius-md);
    }
    .post-card .post-body {
        display: block;
    }
    .post-card .post-body > *:not(:last-child) {
        margin-bottom: 0.6rem;
    }
    .post-card.style-full .post-thumb {
        width: 100%;
        overflow: hidden;
    }
    .post-card.style-full .post-thumb img {
        border-radius: var(--nammu-radius-md);
    }
    .post-card.style-full.full-mode-natural .post-thumb img {
        height: auto;
        max-height: none;
    }
    .post-card.style-full.full-mode-crop .post-thumb {
        aspect-ratio: 16 / 9;
    }
    .post-card.style-full.full-mode-crop .post-thumb img {
        height: 100%;
        width: 100%;
        object-fit: cover;
    }
    .post-card.style-full .post-body {
        margin-top: 0.9rem;
    }
    .post-card.style-media-right {
        display: block;
    }
    .post-card.style-media-right .post-thumb {
        float: right;
        width: clamp(110px, 34%, 170px);
        aspect-ratio: 1 / 1;
        margin-left: 1.25rem;
        margin-bottom: 0.5rem;
        shape-outside: inset(0 round var(--nammu-radius-lg));
        -webkit-shape-outside: inset(0 round var(--nammu-radius-lg));
        overflow: hidden;
        border-radius: var(--nammu-radius-lg);
    }
    .post-card.style-media-right .post-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        border-radius: inherit;
    }
    .post-card.style-square-tall-right {
        display: grid;
        grid-template-columns: 1fr clamp(110px, 34%, 170px);
        grid-template-rows: 1fr;
        gap: 1rem;
        align-items: stretch;
        min-height: 220px;
    }
    .post-card.style-square-tall-right .post-thumb {
        grid-column: 2;
        grid-row: 1;
        width: 100%;
        height: 100%;
        margin: 0;
        overflow: hidden;
        border-radius: var(--nammu-radius-lg);
        align-self: stretch;
        justify-self: center;
    }
    .post-card.style-square-tall-right .post-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: inherit;
        display: block;
    }
    .post-card.style-square-tall-right .post-body {
        grid-column: 1;
        grid-row: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 0.6rem;
    }
    .post-card.style-circle-right .post-thumb {
        border-radius: 50%;
        shape-outside: circle();
        -webkit-shape-outside: circle();
    }
    .post-card h2 {
        margin: 0;
        font-size: 1.3rem;
        line-height: 1.25;
        color: <?= $headingSecondaryColor ?>;
    }
    .post-grid.columns-1 .post-card h2 {
        font-size: clamp(1.6rem, 3.2vw, 2.05rem);
    }
    .post-grid.columns-2 .post-card h2 {
        font-size: clamp(1.45rem, 2.6vw, 1.75rem);
    }
    .post-grid.columns-3 .post-card h2 {
        font-size: clamp(1.25rem, 2vw, 1.45rem);
        word-break: break-word;
        hyphens: auto;
        overflow-wrap: anywhere;
    }
    .post-card h2 a {
        color: <?= $headingSecondaryColor ?>;
        transition: color 0.2s ease;
    }
    .post-card h2 a:hover {
        text-decoration: underline;
        color: <?= $accentColor ?>;
    }
    .post-meta {
        margin: 0;
        display: block;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
        letter-spacing: 0.012em;
        background: <?= $accentBackground ?>;
        border: 1px solid <?= $accentBorder ?>;
        color: <?= $accentColor ?>;
        border-radius: var(--nammu-radius-sm);
    }
    .category-tag-link {
        color: <?= $accentColor ?>;
        text-decoration: none;
        border-bottom: 1px dotted rgba(0,0,0,0.5);
        padding-bottom: 0.05rem;
    }
    .category-tag-link {
        color: <?= $accentColor ?>;
        text-decoration: none;
        border-bottom: 1px dotted rgba(0,0,0,0.5);
        padding-bottom: 0.05rem;
    }

    .post-description {
        margin: 0;
        font-size: 1.02rem;
        line-height: 1.65;
    }
    .home-pagination {
        margin-top: 2rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
    }
    .home-pagination .page-link {
        padding: 0.5rem 0.9rem;
        border-radius: var(--nammu-radius-pill);
        background: <?= $highlight ?>;
        color: <?= $accentColor ?>;
        border: 1px solid rgba(0,0,0,0.08);
        transition: background-color 0.2s ease, color 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .home-pagination .page-link:hover {
        text-decoration: none;
        background: <?= $accentColor ?>;
        color: #ffffff;
    }
    .home-pagination .page-link.disabled {
        opacity: 0.45;
        pointer-events: none;
    }
    .home-pagination .page-status {
        font-weight: 600;
        color: <?= $metaColor ?>;
    }
    @media (max-width: 720px) {
        .post-card.style-media-right {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .post-card.style-media-right .post-body {
            width: 100%;
            text-align: left;
        }
        .post-card.style-media-right .post-thumb {
            float: none;
            width: min(240px, 75vw);
            margin: 0 auto 0.85rem auto;
            shape-outside: none;
            -webkit-shape-outside: none;
        }
        .post-card.style-media-right .post-thumb img {
            border-radius: var(--nammu-radius-md);
        }
        .post-card.style-square-tall-right {
            grid-template-columns: 1fr;
            grid-template-rows: 1fr;
            min-height: 220px;
        }
        .post-card.style-square-tall-right .post-thumb {
            grid-column: 1;
            width: 100%;
            height: 100%;
        }
        .post-card.style-square-tall-right .post-thumb img {
            height: 100%;
        }
        .post-card.style-square-tall-right .post-body {
            grid-column: 1;
            grid-row: auto;
        }
        .post-card.style-circle-right .post-thumb {
            width: min(200px, 60vw);
        }
        .post-grid.columns-1 .post-card.style-media-right {
            display: block;
        }
        .post-grid.columns-1 .post-card.style-media-right .post-thumb {
            float: none;
            width: 100%;
            margin: 0 0 0.85rem 0;
            shape-outside: none;
            -webkit-shape-outside: none;
            border-radius: var(--nammu-radius-lg);
        }
        .post-grid.columns-1 .post-card.style-media-right .post-thumb img {
            border-radius: inherit;
        }
        .post-grid.columns-1 .post-card.style-circle-right .post-thumb {
            width: 100%;
            border-radius: 50%;
        }
    }
    @media (max-width: 640px) {
        .site-search-form {
            flex-direction: column;
        }
        .site-search-form input[type="text"],
        .site-search-form input[type="email"],
        .site-search-form button,
        .search-categories-link,
        .search-letters-link {
            width: 100%;
        }
    }
    .post-grid.blocks-flat {
        gap: 2.25rem 1.75rem;
    }
    .post-grid.blocks-flat .post-card {
        background: transparent;
        border: none;
        padding: 0;
    }
    @media (min-width: 721px) {
        .post-grid.blocks-flat:not(.columns-1) .post-card.style-media-right .post-thumb {
            margin-left: clamp(1rem, 3vw, 1.5rem);
        }
    }
    .post-grid.blocks-flat .post-card.style-full .post-body {
        margin-top: 1.15rem;
    }
</style>
