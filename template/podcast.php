<?php
/**
 * @var array<int, array{title:string,description:string,date:string,image:?string,audio:string,duration:string}> $episodes
 * @var int $count
 */
$colors = $theme['colors'] ?? [];
$highlight = htmlspecialchars($colors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$textColor = htmlspecialchars($colors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
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
$headingSecondaryColor = htmlspecialchars($colors['h2'] ?? '#ea2f28', ENT_QUOTES, 'UTF-8');
$homeSettings = $theme['home'] ?? [];
$columns = (int) ($homeSettings['columns'] ?? 2);
if ($columns < 1 || $columns > 3) {
    $columns = 2;
}
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
$shouldShowSearch = in_array($searchMode, ['home', 'both'], true);
$searchTop = $shouldShowSearch && $searchPositionSetting === 'title';
$searchBottom = $shouldShowSearch && $searchPositionSetting === 'footer';
$subscriptionSettings = is_array($theme['subscription'] ?? null) ? $theme['subscription'] : [];
$subscriptionModeValue = $subscriptionSettings['mode'] ?? 'none';
$subscriptionPositionValue = $subscriptionSettings['position'] ?? 'footer';
$subscriptionMode = in_array($subscriptionModeValue, ['none', 'home', 'single', 'both'], true) ? $subscriptionModeValue : 'none';
$subscriptionPositionSetting = in_array($subscriptionPositionValue, ['title', 'footer'], true) ? $subscriptionPositionValue : 'footer';
$shouldShowSubscription = in_array($subscriptionMode, ['home', 'both'], true);
$subscriptionTop = $shouldShowSubscription && $subscriptionPositionSetting === 'title';
$subscriptionBottom = $shouldShowSubscription && $subscriptionPositionSetting === 'footer';
$searchActionBase = $baseUrl ?? '/';
$searchAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/buscar.php';
$subscriptionAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/subscribe.php';
$letterIndexUrlValue = $lettersIndexUrl ?? null;
$itinerariesIndexUrl = $itinerariesIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl, '/') . '/itinerarios' : '/itinerarios');
$podcastIndexUrl = $podcastIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl, '/') . '/podcast' : '/podcast');
$hasItineraries = !empty($hasItineraries);
$hasPodcast = !empty($hasPodcast);
$hasCategories = !empty($hasCategories);
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
$postalEnabled = $postalEnabled ?? false;
$postalUrl = $postalUrl ?? '/correos.php';
$postalLogoSvg = $postalLogoSvg ?? '';
$renderSearchBox = static function (string $variant) use ($searchAction, $searchActionBase, $accentColor, $letterIndexUrlValue, $showLetterButton, $hasItineraries, $itinerariesIndexUrl, $hasCategories, $hasPodcast, $podcastIndexUrl): string {
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
            <?php if ($hasCategories): ?>
                <a class="search-categories-link" href="<?= htmlspecialchars(rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/categorias', ENT_QUOTES, 'UTF-8') ?>" aria-label="Índice de categorías">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                        <line x1="8" y1="9" x2="16" y2="9" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                        <line x1="8" y1="13" x2="16" y2="13" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    </svg>
                </a>
            <?php endif; ?>
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
            <?php if (!empty($hasPodcast) && !empty($podcastIndexUrl)): ?>
                <a class="search-podcast-link" href="<?= htmlspecialchars($podcastIndexUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Podcast">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="9" y="3" width="6" height="10" rx="3" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                        <path d="M5 11C5 14.866 8.134 18 12 18C15.866 18 19 14.866 19 11" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                        <line x1="12" y1="18" x2="12" y2="22" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                        <line x1="8" y1="22" x2="16" y2="22" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </a>
            <?php endif; ?>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
};
$renderSubscriptionBox = static function (string $variant) use ($subscriptionAction, $accentColor, $highlight, $textColor, $subscriptionMessage, $currentUrl, $postalEnabled, $postalUrl, $postalLogoSvg): string {
    $avisosUrl = $subscriptionAction !== '' ? str_replace('/subscribe.php', '/avisos.php', $subscriptionAction) : '/avisos.php';
    ob_start(); ?>
    <div class="site-search-box <?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?> site-subscription-box">
        <form class="site-search-form subscription-form" method="post" action="<?= htmlspecialchars($subscriptionAction, ENT_QUOTES, 'UTF-8') ?>">
            <span class="search-icon subscription-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <polyline points="3,7 12,13 21,7" fill="none" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <input type="hidden" name="back" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="force_all" value="1">
            <input type="email" name="subscriber_email" placeholder="Suscríbete" required>
            <button type="submit" aria-label="Enviar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 4L9 16L4 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <a class="subscription-avisos-link" href="<?= htmlspecialchars($avisosUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Avisos por email">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="6" width="16" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                    <polyline points="4,8 12,14 20,8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <?php if ($postalEnabled && $postalLogoSvg !== ''): ?>
                <a class="subscription-postal-link" href="<?= htmlspecialchars($postalUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Suscripción postal">
                    <?= $postalLogoSvg ?>
                </a>
            <?php endif; ?>
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
$renderPostalBox = static function (string $variant) use ($postalEnabled, $postalUrl, $postalLogoSvg): string {
    if (!$postalEnabled || $postalLogoSvg === '') {
        return '';
    }
    ob_start(); ?>
    <div class="site-search-box <?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?> site-subscription-box">
        <div class="postal-only-box">
            <a class="subscription-postal-link" href="<?= htmlspecialchars($postalUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Suscripción postal">
                <?= $postalLogoSvg ?>
            </a>
            <div class="postal-only-text">
                <strong>Correo postal</strong>
                <span>Suscríbete para recibir envíos físicos.</span>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};
?>

<section class="category-detail-hero">
    <div>
        <p class="category-label"><?= htmlspecialchars((string) ($theme['author'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <h1><?= htmlspecialchars((string) ($theme['blog'] ?? $siteTitle ?? 'Nammu Blog'), ENT_QUOTES, 'UTF-8') ?> · Podcast</h1>
        <p class="category-count"><?= htmlspecialchars((string) $count, ENT_QUOTES, 'UTF-8') ?> <?= $count === 1 ? 'episodio publicado' : 'episodios publicados' ?></p>
    </div>
</section>

<?php if ($subscriptionTop): ?>
    <section class="site-search-block placement-top site-subscription-block">
        <?= $renderSubscriptionBox('variant-inline minimal') ?>
    </section>
<?php endif; ?>
<?php if ($searchTop): ?>
    <section class="site-search-block placement-top">
        <?= $renderSearchBox('variant-inline minimal') ?>
    </section>
<?php endif; ?>

<?php if (empty($episodes)): ?>
    <p>No hay episodios publicados todavía.</p>
<?php else: ?>
    <section class="post-grid columns-<?= $columns ?> blocks-<?= htmlspecialchars($blocksMode, ENT_QUOTES, 'UTF-8') ?>">
        <?php $imageIndex = 0; ?>
        <?php foreach ($episodes as $episode): ?>
            <?php
            $audioLink = $episode['audio'] ?? '';
            $imageUrl = $resolveImage($episode['image'] ?? '');
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
            $metaPieces = [];
            if (!empty($episode['date'])) {
                $metaPieces[] = htmlspecialchars($episode['date'], ENT_QUOTES, 'UTF-8');
            }
            if (!empty($episode['duration'])) {
                $metaPieces[] = htmlspecialchars($episode['duration'], ENT_QUOTES, 'UTF-8');
            }
            $metaHtml = implode(' · ', $metaPieces);
            ?>
            <article class="<?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($imageUrl): ?>
                    <a class="<?= htmlspecialchars($thumbClass, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($audioLink, ENT_QUOTES, 'UTF-8') ?>">
                        <?php $priorityAttrs = $imageIndex === 0 ? ' decoding="async" fetchpriority="high"' : ' loading="lazy" decoding="async"'; ?>
                        <?php $imageIndex++; ?>
                        <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($episode['title'], ENT_QUOTES, 'UTF-8') ?>"<?= $priorityAttrs ?>>
                    </a>
                <?php endif; ?>
                <div class="post-body">
                    <h2><a href="<?= htmlspecialchars($audioLink, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($episode['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
                    <?php if ($metaHtml !== ''): ?>
                        <p class="post-meta"><?= $metaHtml ?></p>
                    <?php endif; ?>
                    <?php if (!empty($episode['description'])): ?>
                        <p class="post-description"><?= htmlspecialchars($episode['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($audioLink)): ?>
                        <audio class="podcast-player" controls preload="none">
                            <source src="<?= htmlspecialchars($audioLink, ENT_QUOTES, 'UTF-8') ?>" type="audio/mpeg">
                        </audio>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($subscriptionBottom): ?>
    <section class="site-search-block placement-bottom site-subscription-block">
        <?= $renderSubscriptionBox('variant-panel') ?>
    </section>
<?php endif; ?>
<?php if (!$subscriptionTop && !$subscriptionBottom && $postalEnabled): ?>
    <section class="site-search-block placement-bottom site-subscription-block">
        <?= $renderPostalBox('variant-panel') ?>
    </section>
<?php endif; ?>
<?php if ($searchBottom): ?>
    <section class="site-search-block placement-bottom">
        <?= $renderSearchBox('variant-panel') ?>
    </section>
<?php endif; ?>

<style>
    .category-detail-hero {
        margin-bottom: 2rem;
        background: <?= $highlight ?>;
        border-radius: var(--nammu-radius-lg);
        padding: 1.7rem 2rem;
        border: 1px solid rgba(0,0,0,0.05);
    }
    .category-label {
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 0.85rem;
        color: <?= $accentColor ?>;
    }
    .category-detail-hero h1 {
        margin: 0.3rem 0 0;
        font-size: clamp(2rem, 5vw, 2.8rem);
        color: <?= $brandColor ?>;
    }
    .category-count {
        margin: 0.3rem 0 0;
        color: <?= $textColor ?>;
        opacity: 0.8;
    }
    .site-search-block {
        margin: 1.5rem auto;
        max-width: min(760px, 100%);
    }
    .site-search-block.placement-top {
        margin: 0.75rem auto 1rem;
    }
    .site-search-box {
        border-radius: var(--nammu-radius-lg);
        padding: 1rem 1.25rem;
        border: 1px solid rgba(0,0,0,0.05);
        background: <?= $highlight ?>;
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
    .site-search-form .search-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(0,0,0,0.05);
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
    }
    .site-subscription-box .subscription-icon {
        background: <?= $accentBackground ?>;
        border: 1px solid <?= $accentBorder ?>;
    }
    .site-subscription-box .subscription-avisos-link,
    .site-subscription-box .subscription-postal-link {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        border: 1px solid <?= $accentBorder ?>;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: <?= $accentColor ?>;
        background: <?= $accentBackground ?>;
    }
    .site-search-form .search-categories-link,
    .site-search-form .search-letters-link,
    .site-search-form .search-itineraries-link,
    .site-search-form .search-podcast-link {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 1px solid rgba(0,0,0,0.1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: <?= $accentColor ?>;
        background: rgba(0,0,0,0.04);
    }
    .post-grid {
        display: grid;
        gap: 1.5rem;
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
        display: block;
        border-radius: inherit;
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
        .post-grid.columns-1 .post-card.style-media-right {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .post-grid.columns-1 .post-card.style-media-right .post-thumb {
            float: none;
            width: min(240px, 70%);
            margin: 0 auto 0.85rem auto;
            shape-outside: none;
            -webkit-shape-outside: none;
        }
        .post-grid.columns-1 .post-card.style-circle-right .post-thumb {
            width: min(200px, 55%);
        }
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
        width: 100%;
    }
    .post-card.style-full.full-mode-crop .post-thumb {
        aspect-ratio: 16 / 9;
    }
    .post-card.style-full.full-mode-crop .post-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .post-card.style-full .post-body {
        margin-top: 0.9rem;
    }
    .post-card h2 {
        margin: 0;
        font-size: 1.6rem;
        color: <?= $headingSecondaryColor ?>;
        word-break: break-word;
        hyphens: auto;
        overflow-wrap: anywhere;
    }
    .post-card h2 a {
        color: inherit;
    }
    .post-card .post-meta {
        margin: 0;
        display: block;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
        letter-spacing: 0.012em;
        background: <?= htmlspecialchars($accentBackground, ENT_QUOTES, 'UTF-8') ?>;
        border: 1px solid <?= htmlspecialchars($accentBorder, ENT_QUOTES, 'UTF-8') ?>;
        color: <?= $accentColor ?>;
        border-radius: var(--nammu-radius-sm);
    }
    .post-card .post-description {
        margin: 0;
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
    .podcast-player {
        width: 100%;
        margin-top: 0.6rem;
        border-radius: 999px;
        background: <?= $highlight ?>;
    }
</style>
