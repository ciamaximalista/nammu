<?php
$colors = $theme['colors'] ?? [];
$colorMeta = htmlspecialchars($colors['h3'] ?? '#777777', ENT_QUOTES, 'UTF-8');
$colorText = htmlspecialchars($colors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
$colorIntroBg = htmlspecialchars($colors['intro'] ?? '#f6f6f6', ENT_QUOTES, 'UTF-8');
$colorHighlight = htmlspecialchars($colors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$colorAccent = htmlspecialchars($colors['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8');
$colorBrand = htmlspecialchars($colors['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$colorCodeBg = htmlspecialchars($colors['code_background'] ?? '#000000', ENT_QUOTES, 'UTF-8');
$colorCodeText = htmlspecialchars($colors['code_text'] ?? '#90ee90', ENT_QUOTES, 'UTF-8');
$fonts = $theme['fonts'] ?? [];
$codeFont = htmlspecialchars($fonts['code'] ?? 'VT323', ENT_QUOTES, 'UTF-8');
$quoteFont = htmlspecialchars($fonts['quote'] ?? 'Castoro', ENT_QUOTES, 'UTF-8');
$searchSettings = $theme['search'] ?? [];
$searchMode = in_array($searchSettings['mode'] ?? 'none', ['none', 'home', 'single', 'both'], true) ? $searchSettings['mode'] : 'none';
$searchPositionSetting = in_array($searchSettings['position'] ?? 'title', ['title', 'footer'], true) ? $searchSettings['position'] : 'title';
$showSingleSearch = in_array($searchMode, ['single', 'both'], true);
$singleSearchTop = $showSingleSearch && $searchPositionSetting === 'title';
$singleSearchBottom = $showSingleSearch && $searchPositionSetting === 'footer';
$subscriptionSettings = is_array($theme['subscription'] ?? null) ? $theme['subscription'] : [];
$subscriptionModeValue = $subscriptionSettings['mode'] ?? 'none';
$subscriptionPositionValue = $subscriptionSettings['position'] ?? 'footer';
$subscriptionMode = in_array($subscriptionModeValue, ['none', 'home', 'single', 'both'], true) ? $subscriptionModeValue : 'none';
$subscriptionPositionSetting = in_array($subscriptionPositionValue, ['title', 'footer'], true) ? $subscriptionPositionValue : 'footer';
$showSingleSubscription = in_array($subscriptionMode, ['single', 'both'], true);
$singleSubscriptionTop = $showSingleSubscription && $subscriptionPositionSetting === 'title';
$singleSubscriptionBottom = $showSingleSubscription && $subscriptionPositionSetting === 'footer';
$suppressSingleSearchTop = !empty($suppressSingleSearchTop);
$suppressSingleSearchBottom = !empty($suppressSingleSearchBottom);
if ($suppressSingleSearchTop) {
    $singleSearchTop = false;
}
if ($suppressSingleSearchBottom) {
    $singleSearchBottom = false;
}
$searchActionBase = $baseUrl ?? '/';
$searchAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/buscar.php';
$subscriptionAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/subscribe.php';
$letterIndexUrlValue = $lettersIndexUrl ?? null;
$itinerariesIndexUrl = $itinerariesIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl ?? '/', '/') . '/itinerarios' : '/itinerarios');
$hasItineraries = !empty($hasItineraries);
$showLetterButton = !empty($showLetterIndexButton) && !empty($letterIndexUrlValue);
$currentUrl = ($baseUrl ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
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
$autoTocHtml = isset($autoTocHtml) ? trim((string) $autoTocHtml) : '';
$customMetaBand = isset($customMetaBand) ? trim((string) $customMetaBand) : '';
$renderSearchBox = static function (string $variant) use ($searchAction, $colorHighlight, $colorAccent, $colorText, $searchActionBase, $letterIndexUrlValue, $showLetterButton, $hasItineraries, $itinerariesIndexUrl): string {
    ob_start(); ?>
    <div class="site-search-box <?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?>">
        <form class="site-search-form" method="get" action="<?= htmlspecialchars($searchAction, ENT_QUOTES, 'UTF-8') ?>">
            <span class="search-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="6" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <line x1="12.5" y1="12.5" x2="17" y2="17" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </span>
            <input type="text" name="q" placeholder="Busca en este sitio..." required>
            <button type="submit" aria-label="Buscar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 4L9 16L4 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <a class="search-categories-link" href="<?= htmlspecialchars(rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/categorias', ENT_QUOTES, 'UTF-8') ?>" aria-label="Índice de categorías">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <line x1="8" y1="9" x2="16" y2="9" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <line x1="8" y1="13" x2="16" y2="13" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                </svg>
            </a>
            <?php if ($showLetterButton && $letterIndexUrlValue): ?>
                <a class="search-letters-link" href="<?= htmlspecialchars($letterIndexUrlValue, ENT_QUOTES, 'UTF-8') ?>" aria-label="Índice alfabético">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 18L9 6L13 18" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="6.5" y1="13" x2="11.5" y2="13" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                        <path d="M15 6H20L15 18H20" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (!empty($hasItineraries) && !empty($itinerariesIndexUrl)): ?>
                <a class="search-itineraries-link" href="<?= htmlspecialchars($itinerariesIndexUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Itinerarios">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 5H10C11.1046 5 12 5.89543 12 7V19H4C2.89543 19 2 18.1046 2 17V7C2 5.89543 2.89543 5 4 5Z" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M20 5H14C12.8954 5 12 5.89543 12 7V19H20C21.1046 19 22 18.1046 22 17V7C22 5.89543 21.1046 5 20 5Z" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round"/>
                        <line x1="12" y1="7" x2="12" y2="19" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </a>
            <?php endif; ?>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
};
$renderSubscriptionBox = static function (string $variant) use ($subscriptionAction, $colorAccent, $colorHighlight, $colorText, $subscriptionMessage, $currentUrl): string {
    ob_start(); ?>
    <div class="site-search-box <?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?> site-subscription-box">
        <form class="site-search-form subscription-form" method="post" action="<?= htmlspecialchars($subscriptionAction, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="back" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>">
            <span class="search-icon subscription-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <polyline points="3,7 12,13 21,7" fill="none" stroke="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <input type="email" name="subscriber_email" placeholder="Pon tu email para suscribirte" required>
            <button type="submit" aria-label="Suscribirme" title="Suscribirme" style="background: <?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>; color:#fff;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($colorAccent, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="6" width="16" height="12" rx="2" fill="none" stroke="white" stroke-width="2"/>
                    <polyline points="4,8 12,14 20,8" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>
        <?php if ($subscriptionMessage !== ''): ?>
            <div class="subscription-feedback" style="color: <?= htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') ?>; background: <?= htmlspecialchars($colorHighlight, ENT_QUOTES, 'UTF-8') ?>; border-radius: 10px; padding:10px; margin-top:10px; font-size:14px;">
                <?= htmlspecialchars($subscriptionMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
};
$siteAuthor = htmlspecialchars($theme['author'] !== '' ? $theme['author'] : ($siteTitle ?? ''), ENT_QUOTES, 'UTF-8');
$siteBlog = htmlspecialchars($theme['blog'] !== '' ? $theme['blog'] : ($siteDescription ?? ''), ENT_QUOTES, 'UTF-8');
$isAlphabeticalMode = !empty($isAlphabeticalOrder);
function nammu_format_date_es(?string $date): string {
    if ($date === null || $date === '') {
        return '';
    }
    $date = str_replace('/', '-', $date);
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    $meses = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];
    $dia = date('j', $timestamp);
    $mes = (int) date('n', $timestamp);
    $anio = date('Y', $timestamp);
    return $dia . ' de ' . $meses[$mes - 1] . ' de ' . $anio;
}

function nammu_page_date_fallback(array $metadata, ?string $filePath): ?string {
    $candidates = ['updated', 'ultimaactualizacion', 'últimaactualización', 'fecha', 'lastmod', 'modified'];
    foreach ($metadata as $key => $value) {
        $normalizedKey = strtolower(trim((string) $key));
        if (in_array($normalizedKey, $candidates, true)) {
            $cleanValue = trim((string) $value);
            if ($cleanValue !== '') {
                return $cleanValue;
            }
        }
    }
    if ($filePath && is_file($filePath)) {
        $timestamp = filemtime($filePath);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
    }
    return null;
}
$postTemplate = method_exists($post, 'getTemplate') ? $post->getTemplate() : strtolower($post->getMetadata()['Template'] ?? '');
$isPageTemplate = ($postTemplate === 'page');
$isDraftPost = method_exists($post, 'isDraft') ? $post->isDraft() : false;
$category = $post->getMetadata()['Category'] ?? '';
$postFilePath = $postFilePath ?? null;
$rawDate = $post->getRawDate();
if ($isPageTemplate && (trim((string) $rawDate) === '')) {
    $rawDate = nammu_page_date_fallback($post->getMetadata(), $postFilePath);
}
$formattedDate = nammu_format_date_es($rawDate);
if ($formattedDate === '' && is_string($rawDate) && trim($rawDate) !== '') {
    $formattedDate = trim($rawDate);
}
$categoryLinkHtml = '';
if ($category !== '') {
    $categorySlug = nammu_slugify_label($category);
    $categoryUrl = ($baseUrl ?? '/') !== '' ? rtrim($baseUrl, '/') . '/categoria/' . rawurlencode($categorySlug) : '/categoria/' . rawurlencode($categorySlug);
    $categoryLinkHtml = '<a class="category-tag-link" href="' . htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . '</a>';
}
$topMetaText = '';
if (!$isPageTemplate) {
    if ($isAlphabeticalMode) {
        if ($categoryLinkHtml !== '') {
            $topMetaText = $categoryLinkHtml;
        }
    } else {
        if ($formattedDate !== '') {
            $topMetaText = 'Publicado el ' . htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8');
            if ($categoryLinkHtml !== '') {
                // Non-breaking space ensures the category label doesn't stick to "en"
                $topMetaText .= ' en&nbsp;' . $categoryLinkHtml;
            }
        } elseif ($categoryLinkHtml !== '') {
            $topMetaText = $categoryLinkHtml;
        }
    }
}
$bottomMetaText = '';
if ($isPageTemplate && $formattedDate !== '') {
    $bottomMetaText = 'Actualizado por última vez el ' . htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') . '.';
} elseif (!$isPageTemplate && $isAlphabeticalMode && $formattedDate !== '') {
    $bottomMetaText = 'Actualizado por última vez el ' . htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') . '.';
}
?>
<article class="post<?= $isDraftPost ? ' post-draft' : '' ?>">
    <?php if ($isDraftPost): ?>
        <div class="draft-stamp" aria-hidden="true">Borrador</div>
    <?php endif; ?>
    <div class="post-header">
        <div class="post-brand">
            <?php if ($siteAuthor !== ''): ?>
                <span class="post-brand-title"><?= $siteAuthor ?></span>
            <?php endif; ?>
            <?php if ($siteBlog !== ''): ?>
                <span class="post-brand-sub"><?= $siteBlog ?></span>
            <?php endif; ?>
            <?php if ($singleSearchTop): ?>
                <div class="site-search-block placement-top within-brand">
                    <?= $renderSearchBox('variant-inline minimal') ?>
                </div>
            <?php endif; ?>
        </div>
        <h1><?= htmlspecialchars($post->getTitle(), ENT_QUOTES, 'UTF-8') ?></h1>
<?php if (!$isPageTemplate && $topMetaText !== '' && empty($hideCategory)): ?>
    <div class="post-meta-band"><?= $topMetaText ?></div>
<?php endif; ?>
<?php if ($customMetaBand !== ''): ?>
    <div class="post-meta-band post-meta-band--custom"><?= htmlspecialchars($customMetaBand, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
        <?php if ($post->getDescription() !== ''): ?>
            <div class="post-intro">
                <p><?= htmlspecialchars($post->getDescription(), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $imageUrl = $resolveImage($post->getImage());
    if ($imageUrl):
    ?>
        <figure class="post-hero">
            <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post->getTitle(), ENT_QUOTES, 'UTF-8') ?>">
        </figure>
    <?php endif; ?>
    <div class="post-body">
        <?php if ($singleSubscriptionTop): ?>
            <div class="site-search-block placement-top site-subscription-block">
                <?= $renderSubscriptionBox('variant-panel') ?>
            </div>
        <?php endif; ?>
        <?php if ($autoTocHtml !== ''): ?>
            <section class="post-toc-block" aria-label="Índice de contenidos">
                <div class="post-toc-heading">Contenido</div>
                <?= $autoTocHtml ?>
            </section>
        <?php endif; ?>
        <?= $htmlContent ?>
    </div>
    <?php if ($bottomMetaText !== ''): ?>
        <div class="post-meta-update"><?= $bottomMetaText ?></div>
    <?php endif; ?>
    <?php if ($singleSubscriptionBottom): ?>
        <div class="site-search-block placement-bottom site-subscription-block">
            <?= $renderSubscriptionBox('variant-panel') ?>
        </div>
    <?php endif; ?>
    <?php if ($singleSearchBottom && empty($suppressSingleSearchBottom)): ?>
        <div class="site-search-block placement-bottom">
            <?= $renderSearchBox('variant-panel') ?>
        </div>
    <?php endif; ?>
</article>

<style>
    .site-search-block {
        margin: 1.5rem auto;
        max-width: min(720px, 100%);
    }
    .site-search-block.placement-top {
        margin: 0.75rem auto 1rem;
    }
    .site-search-block.within-brand {
        margin: 0.4rem 0 0;
        width: 100%;
    }
    .post-brand .site-search-form input[type="text"] {
        padding: 0.6rem 0.8rem;
        font-size: 0.95rem;
    }
    .post-brand .site-search-form button {
        width: 40px;
        height: 40px;
        border-radius: 10px;
    }
    .site-search-box {
        border-radius: var(--nammu-radius-lg);
        padding: 1rem 1.25rem;
        border: 1px solid rgba(0,0,0,0.05);
        background: <?= $colorHighlight ?>;
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
    .site-search-form input:focus {
        outline: 2px solid <?= $colorAccent ?>;
        border-color: <?= $colorAccent ?>;
    }
    .site-subscription-box .site-search-form {
        flex-wrap: nowrap;
    }
    .site-subscription-box .site-search-form input[type="email"] {
        flex: 1 1 240px;
        padding: 0.7rem 0.9rem;
        border-radius: 12px;
        border: 1px solid rgba(0,0,0,0.1);
        font-size: 1rem;
    }
    .site-subscription-box .site-search-form button {
        padding: 0;
        height: 42px;
        width: 42px;
    }
    .site-subscription-box .subscription-feedback {
        border: 1px solid rgba(0,0,0,0.05);
    }
    @media (max-width: 640px) {
        .site-search-form {
            flex-direction: column;
        }
        .site-search-form input[type="text"],
        .site-search-form button,
        .search-categories-link,
        .search-letters-link {
            width: 100%;
        }
    }
    .site-search-form button {
        border: none;
        background: <?= $colorAccent ?>;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .site-search-form .search-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(0,0,0,0.05);
        display: inline-flex;
        align-items: center;
        justify-content: center;
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
    .search-letters-link:hover,
    .search-itineraries-link:hover {
        background: rgba(0,0,0,0.1);
    }
    .post {
        display: grid;
        gap: 1.5rem;
    }
    .post-header {
        display: grid;
        gap: 1rem;
        text-align: center;
    }
    .post-brand {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        justify-content: center;
        align-items: center;
        font-size: 0.85rem;
        color: <?= $colorBrand ?>;
        text-transform: uppercase;
        letter-spacing: 0.12em;
    }
    .post-brand-title {
        font-weight: 700;
    }
    .post-brand-sub {
        font-weight: 400;
    }
    .post h1 {
        margin: 0;
        font-size: clamp(2.4rem, 6vw, 3.4rem);
        line-height: 1.1;
        color: <?= htmlspecialchars($colors['h1'] ?? '#1b8eed', ENT_QUOTES, 'UTF-8') ?>;
    }
    .post-meta-band {
        display: inline-flex;
        margin: 0 auto;
        padding: 0.35rem 1rem;
        border-radius: var(--nammu-radius-pill);
        background: <?= $colorHighlight ?>;
        color: <?= $colorAccent ?>;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 600;
    }
    .post-intro {
        max-width: min(760px, 100%);
        margin: 0 auto 1.5rem;
        padding: 1.25rem 1.5rem;
        border-radius: var(--nammu-radius-md);
        background: <?= $colorIntroBg ?>;
        color: <?= $colorText ?>;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,0.03);
    }
    .post-intro p {
        margin: 0;
    }
    .post-hero {
        margin: 0;
        display: flex;
        justify-content: center;
    }
    .post-hero img {
        width: min(960px, 100%);
        border-radius: var(--nammu-radius-md);
        height: auto;
    }
    .post-body {
        max-width: min(760px, 100%);
        margin: 0 auto 1.5rem;
        color: <?= $colorText ?>;
    }
    .post-body h1 {
        margin: 2.8rem 0 1.35rem;
        line-height: 1.15;
    }
    .post-body p {
        margin-top: 0;
    }
    .post-body ul {
        padding-left: 1.2rem;
    }
    .post-body img {
        max-width: 100%;
        height: auto;
        border-radius: var(--nammu-radius-md);
        display: block;
        margin: 1.5rem auto;
    }
    .post-body pre {
        background: <?= $colorCodeBg ?>;
        color: <?= $colorCodeText ?>;
        padding: 1.25rem 1.5rem;
        border-radius: var(--nammu-radius-md);
        overflow-x: auto;
        overflow-y: auto;
        margin: 2rem 0;
        font-size: 0.95rem;
        line-height: 1.5;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04);
        font-family: "<?= $codeFont ?>", "Fira Code", "Source Code Pro", "Courier New", monospace;
        box-sizing: border-box;
        max-width: 100%;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .post-body code {
        background: <?= $colorCodeBg ?>;
        color: <?= $colorCodeText ?>;
        padding: 0.15rem 0.35rem;
        border-radius: var(--nammu-radius-sm);
        font-size: 0.95em;
        font-family: "<?= $codeFont ?>", "Fira Code", "Source Code Pro", "Courier New", monospace;
    }
    .post-body pre code {
        background: transparent;
        color: inherit;
        padding: 0;
        font-size: inherit;
    }
    .post-body blockquote {
        margin: 2rem auto;
        padding: 1.5rem 1.75rem;
        background: <?= $colorHighlight ?>;
        border-left: 4px solid <?= $colorAccent ?>;
        border-radius: var(--nammu-radius-md);
        font-family: "<?= $quoteFont ?>", "Georgia", serif;
        font-style: normal;
        color: <?= $colorText ?>;
    }
    .post-body blockquote p {
        margin: 0 0 0.9rem 0;
    }
    .post-body blockquote p:last-child {
        margin-bottom: 0;
    }
    .category-tag-link {
        color: <?= $colorAccent ?>;
        text-decoration: none;
        border-bottom: 1px dotted rgba(0,0,0,0.5);
        padding-bottom: 0.05rem;
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
        margin: 0 auto;
        background: #000;
    }
    .embedded-video video,
    .embedded-video iframe {
        aspect-ratio: 16 / 9;
    }
    .embedded-pdf iframe {
        background: <?= $colorHighlight ?>;
        height: auto;
    }
    .post-toc-block {
        margin: 0 auto 2rem;
        max-width: min(760px, 100%);
        padding: 1.25rem 1.5rem;
        border-radius: var(--nammu-radius-md);
        background: <?= $colorHighlight ?>;
        border: 1px solid rgba(0,0,0,0.05);
    }
    .post-toc-heading {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 600;
        color: <?= $colorAccent ?>;
        margin-bottom: 0.65rem;
    }
    .nammu-toc {
        margin: 0;
        padding-left: 0;
    }
    .nammu-toc .toc-level {
        list-style: none;
        margin: 0;
        padding-left: 1.25rem;
    }
    .nammu-toc .toc-level-1 {
        padding-left: 0;
    }
    .nammu-toc li {
        margin: 0.3rem 0;
        line-height: 1.4;
    }
    .nammu-toc a {
        color: <?= $colorAccent ?>;
        text-decoration: none;
    }
    .nammu-toc a:hover {
        text-decoration: underline;
    }
    .post-meta-update {
        margin: 2rem auto 0;
        max-width: min(760px, 100%);
        padding: 0.85rem 1.25rem;
        border-radius: var(--nammu-radius-md);
        background: <?= $colorHighlight ?>;
        color: <?= $colorText ?>;
        font-size: 0.95rem;
    }
    .post.post-draft {
        position: relative;
        overflow: hidden;
    }
    .post.post-draft .draft-stamp {
        position: absolute;
        top: 1.5rem;
        right: -3rem;
        padding: 0.5rem 3.5rem;
        font-size: 1.2rem;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.9);
        background: rgba(184, 28, 28, 0.9);
        text-transform: uppercase;
        letter-spacing: 0.2rem;
        transform: rotate(-18deg);
        box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        pointer-events: none;
        z-index: 2;
    }
    @media (max-width: 640px) {
        .post.post-draft .draft-stamp {
            top: 0.75rem;
            font-size: 1rem;
            right: -2rem;
        }
    }
</style>
