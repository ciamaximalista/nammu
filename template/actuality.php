<?php
/**
 * @var array<int, array{title:string,link:string,image:string,images?:array<int,string>,description:string,timestamp:int,source:string,is_manual?:bool,id?:string,links?:array<int,string>,raw_text?:string}> $items
 * @var int $feedsCount
 * @var bool $hasActuality
 * @var int $currentPage
 * @var int $totalPages
 * @var string $prevPageUrl
 * @var string $nextPageUrl
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
$h1Color = htmlspecialchars($colors['h1'] ?? '#1b8eed', ENT_QUOTES, 'UTF-8');
$headingSecondaryColor = htmlspecialchars($colors['h2'] ?? '#ea2f28', ENT_QUOTES, 'UTF-8');
$themeFonts = $theme['fonts'] ?? [];
$noteFont = htmlspecialchars($themeFonts['note'] ?? ($themeFonts['body'] ?? 'Roboto'), ENT_QUOTES, 'UTF-8');
$actualityHeroBackground = trim((string) ($actualityHeroBackground ?? ''));
$actualityHeroBackgroundEsc = htmlspecialchars($actualityHeroBackground, ENT_QUOTES, 'UTF-8');
$searchActionBase = $baseUrl ?? '/';
$searchAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/buscar.php';
$categoriesIndexUrl = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/categorias';
$letterIndexUrlValue = $lettersIndexUrl ?? null;
$showLetterButton = !empty($showLetterIndexButton) && !empty($letterIndexUrlValue);
$itinerariesIndexUrl = $itinerariesIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl ?? '/', '/') . '/itinerarios' : '/itinerarios');
$podcastIndexUrl = $podcastIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl ?? '/', '/') . '/podcast' : '/podcast');
$newslettersIndexUrl = $newslettersIndexUrl ?? ($GLOBALS['newslettersIndexUrl'] ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl ?? '/', '/') . '/newsletters' : '/newsletters'));
$hasItineraries = !empty($hasItineraries);
$hasPodcast = !empty($hasPodcast);
$hasCategories = !empty($hasCategories);
$hasNewsletters = !empty($hasNewsletters ?? ($GLOBALS['hasNewsletters'] ?? false));
$homeSettings = $theme['home'] ?? [];
$headerButtonsMode = $homeSettings['header_buttons'] ?? 'none';
$showHeaderButtons = in_array($headerButtonsMode, ['home', 'both'], true);
$subscriptionSettings = is_array($theme['subscription'] ?? null) ? $theme['subscription'] : [];
$subscriptionModeForButtons = $subscriptionSettings['mode'] ?? 'none';
$postalEnabled = $postalEnabled ?? false;
$postalUrl = $postalUrl ?? '/correos.php';
$postalLogoSvg = $postalLogoSvg ?? '';
$headerButtonsHtml = '';
if ($showHeaderButtons && function_exists('nammu_render_standard_header_buttons')) {
    $headerButtonsHtml = nammu_render_standard_header_buttons(get_defined_vars());
}
$fediverseIcon = function_exists('nammu_footer_icon_svgs') ? (string) (nammu_footer_icon_svgs()['fediverse'] ?? '') : '';
$fediverseConfig = function_exists('nammu_load_config') ? nammu_load_config() : [];
$fediverseConfig = is_array($fediverseConfig) ? $fediverseConfig : [];
$actualityFediverseMeta = static function (array $item) use ($fediverseConfig): array {
    if (!function_exists('nammu_fediverse_public_thread_meta_for_actuality_item')) {
        return [
            'thread_url' => '',
            'summary' => ['likes' => 0, 'shares' => 0, 'replies' => 0],
            'details' => ['likes' => [], 'shares' => [], 'replies' => []],
        ];
    }
    if (!empty($item['is_site_content']) && function_exists('nammu_fediverse_public_thread_meta_for_named_local_item')) {
        $type = trim((string) ($item['site_content_type'] ?? 'post'));
        $path = trim((string) (parse_url((string) ($item['link'] ?? ''), PHP_URL_PATH) ?? ''));
        $slug = '';
        if ($path !== '') {
            $path = '/' . ltrim($path, '/');
            if ($type === 'podcast' && preg_match('#^/podcast/([^/]+)/?$#', $path, $matches) === 1) {
                $slug = rawurldecode((string) ($matches[1] ?? ''));
            } elseif ($type === 'itinerary' && preg_match('#^/itinerarios/([^/]+)/?$#', $path, $matches) === 1) {
                $slug = rawurldecode((string) ($matches[1] ?? ''));
            } elseif ($type === 'post' && preg_match('#^/([^/]+)$#', $path, $matches) === 1) {
                $slug = rawurldecode((string) ($matches[1] ?? ''));
            }
        }
        if ($slug !== '') {
            return nammu_fediverse_public_thread_meta_for_named_local_item($slug, $type, $fediverseConfig);
        }
    }
    return nammu_fediverse_public_thread_meta_for_actuality_item($item, $fediverseConfig);
};
$actualityMetricIcon = static function (string $type): string {
    return match ($type) {
        'reply' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v14l4-4h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Z"/></svg>',
        'like' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="m12 21-1.45-1.32C5.4 15.02 2 11.93 2 8.14 2 5.05 4.42 3 7.2 3c1.57 0 3.08.74 4.05 1.91A5.26 5.26 0 0 1 15.3 3C18.08 3 20.5 5.05 20.5 8.14c0 3.79-3.4 6.88-8.55 11.54Z"/></svg>',
        'share' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17 8V5l5 5-5 5v-3h-4a7 7 0 0 0-7 7v1H4v-1a9 9 0 0 1 9-9h4Z"/><path fill="currentColor" d="M7 4h6v2H7a3 3 0 0 0-3 3v4H2V9a5 5 0 0 1 5-5Z"/></svg>',
        default => '',
    };
};
$renderActualityText = static function (string $text, array $item) use ($fediverseIcon, $actualityFediverseMeta, $actualityMetricIcon): string {
    $html = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    if (strtolower(trim((string) ($item['via'] ?? ''))) === 'boost') {
        return $html;
    }
    $fediverseMeta = $actualityFediverseMeta($item);
    $fediverseUrl = trim((string) ($fediverseMeta['thread_url'] ?? ''));
    if ($fediverseUrl !== '' && $fediverseIcon !== '') {
        $summary = is_array($fediverseMeta['summary'] ?? null) ? $fediverseMeta['summary'] : [];
        $replyCount = max(0, (int) ($summary['replies'] ?? 0));
        $likeCount = max(0, (int) ($summary['likes'] ?? 0));
        $shareCount = max(0, (int) ($summary['shares'] ?? 0));
        $metricsHtml = '';
        if ($replyCount > 0) {
            $metricsHtml .= '<span class="actuality-fediverse-inline-count">' . $replyCount . '</span><span class="actuality-fediverse-inline-icon actuality-fediverse-inline-icon--metric">' . $actualityMetricIcon('reply') . '</span>';
        }
        if ($likeCount > 0) {
            $metricsHtml .= '<span class="actuality-fediverse-inline-count">' . $likeCount . '</span><span class="actuality-fediverse-inline-icon actuality-fediverse-inline-icon--metric">' . $actualityMetricIcon('like') . '</span>';
        }
        if ($shareCount > 0) {
            $metricsHtml .= '<span class="actuality-fediverse-inline-count">' . $shareCount . '</span><span class="actuality-fediverse-inline-icon actuality-fediverse-inline-icon--metric">' . $actualityMetricIcon('share') . '</span>';
        }
        $html .= ' <a class="actuality-fediverse-inline" href="' . htmlspecialchars($fediverseUrl, ENT_QUOTES, 'UTF-8') . '" title="En el Fediverso" aria-label="En el Fediverso"><span class="actuality-fediverse-inline-icon">' . $fediverseIcon . '</span>' . $metricsHtml . '</a>';
    }
    return $html;
};
$renderBoostHeader = static function (array $item): string {
    if (strtolower(trim((string) ($item['via'] ?? ''))) !== 'boost') {
        return '';
    }
    $originalUrl = trim((string) ($item['boost_original_url'] ?? ''));
    if ($originalUrl === '') {
        $boostLinks = array_values(array_filter(array_map('strval', is_array($item['links'] ?? null) ? $item['links'] : [])));
        if (!empty($boostLinks)) {
            $originalUrl = trim((string) end($boostLinks));
        }
    }
    if ($originalUrl === '') {
        return '';
    }
    $actorName = trim((string) ($item['boost_actor_name'] ?? ''));
    $actorIcon = trim((string) ($item['boost_actor_icon'] ?? ''));
    $fallback = mb_substr($actorName !== '' ? $actorName : 'F', 0, 1, 'UTF-8');
    $avatarHtml = $actorIcon !== ''
        ? '<img class="actuality-boost-origin__avatar" src="' . htmlspecialchars($actorIcon, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($actorName !== '' ? $actorName : 'Autor original', ENT_QUOTES, 'UTF-8') . '" loading="lazy">'
        : '<span class="actuality-boost-origin__avatar actuality-boost-origin__avatar--fallback">' . htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8') . '</span>';
    return '<div class="actuality-boost-origin"><a class="actuality-boost-origin__link" href="' . htmlspecialchars($originalUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" title="' . htmlspecialchars($actorName !== '' ? $actorName : 'Publicación original', ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($actorName !== '' ? ('Publicación original de ' . $actorName) : 'Publicación original', ENT_QUOTES, 'UTF-8') . '">' . $avatarHtml . '</a></div>';
};
$formatDate = static function (int $timestamp): string {
    if ($timestamp <= 0) {
        return '';
    }
    return nammu_format_date_spanish((new DateTimeImmutable())->setTimestamp($timestamp), date('Y-m-d', $timestamp));
};
$actualitySourceLabel = static function (array $item): string {
    $isBoost = strtolower(trim((string) ($item['via'] ?? ''))) === 'boost';
    if ($isBoost) {
        $actorUrl = trim((string) ($item['boost_actor_url'] ?? ''));
        $actorName = trim((string) ($item['boost_actor_name'] ?? ''));
        $candidates = [];
        if ($actorUrl !== '') {
            $path = trim((string) (parse_url($actorUrl, PHP_URL_PATH) ?? ''));
            if ($path !== '') {
                if (preg_match('#/@([^/@]+)(?:@[^/]+)?/?$#', $path, $matches) === 1) {
                    $candidates[] = (string) ($matches[1] ?? '');
                }
                if (preg_match('#/(?:users|accounts)/([^/]+)/?$#', $path, $matches) === 1) {
                    $candidates[] = (string) ($matches[1] ?? '');
                }
                if (preg_match('#/ap/actor/?$#', $path) !== 1) {
                    $basename = basename($path);
                    if ($basename !== '' && $basename !== 'actor') {
                        $candidates[] = $basename;
                    }
                }
            }
        }
        if ($actorName !== '') {
            if (preg_match('/@([A-Za-z0-9._-]+)/u', $actorName, $matches) === 1) {
                $candidates[] = (string) ($matches[1] ?? '');
            } elseif (!preg_match('/\s/u', $actorName)) {
                $candidates[] = $actorName;
            }
        }
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            $candidate = ltrim($candidate, '@');
            if ($candidate === '') {
                continue;
            }
            $candidate = preg_replace('/@.+$/u', '', $candidate) ?? $candidate;
            if ($candidate !== '') {
                return '@' . $candidate;
            }
        }
    }
    return trim((string) preg_replace('/^www\./i', '', (string) ($item['source'] ?? '')));
};
$groupedItems = [];
foreach ($items as $item) {
    $timestamp = (int) ($item['timestamp'] ?? 0);
    $groupKey = $timestamp > 0 ? date('Y-m-d', $timestamp) : 'sin-fecha';
    if (!isset($groupedItems[$groupKey])) {
        $groupedItems[$groupKey] = [
            'label' => $timestamp > 0 ? $formatDate($timestamp) : 'Sin fecha',
            'short_label' => $timestamp > 0 ? date('d/m/Y', $timestamp) : '',
            'items' => [],
        ];
    }
    $groupedItems[$groupKey]['items'][] = $item;
}
$splitColumns = static function (array $dayItems): array {
    $left = [];
    $right = [];
    foreach (array_values($dayItems) as $index => $dayItem) {
        if ($index % 2 === 0) {
            $left[] = $dayItem;
        } else {
            $right[] = $dayItem;
        }
    }
    return [$left, $right];
};
$renderLinks = static function (array $links, array $item = []) use ($fediverseIcon): string {
    $links = array_values(array_filter(array_map('strval', $links)));
    if (empty($links)) {
        return '';
    }
    $isBoost = strtolower(trim((string) ($item['via'] ?? ''))) === 'boost';
    $bits = [];
    if ($isBoost) {
        $originalUrl = array_pop($links);
        if ($originalUrl !== null && trim($originalUrl) !== '') {
            // The original boosted post is rendered inline next to the text.
        }
        foreach ($links as $index => $url) {
            $label = count($links) === 1 ? 'Enlace relacionado' : ('Enlace relacionado ' . ($index + 1));
            $bits[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . $label . '</a>';
        }
        return implode(', ', array_values(array_filter($bits)));
    }
    foreach ($links as $index => $url) {
        $label = count($links) === 1 ? 'Enlace' : ('Enlace ' . ($index + 1));
        $bits[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . $label . '</a>';
    }
    return implode(', ', $bits);
};
$renderImages = static function (array $item, bool $isSiteContent = false): string {
    $allImages = array_values(array_unique(array_filter(array_map('strval', is_array($item['images'] ?? null) ? $item['images'] : []))));
    $primaryImage = trim((string) ($item['image'] ?? ''));
    if ($primaryImage === '') {
        $primaryImage = trim((string) ($item['source_image'] ?? ''));
    }
    if ($primaryImage !== '' && !in_array($primaryImage, $allImages, true)) {
        array_unshift($allImages, $primaryImage);
    }
    if (empty($allImages)) {
        return '';
    }
    $target = htmlspecialchars((string) ($item['link'] ?? '#'), ENT_QUOTES, 'UTF-8');
    $attrs = $isSiteContent ? '' : ' target="_blank" rel="noopener"';
    if (count($allImages) === 1) {
        return '<a class="actuality-image-link" href="' . $target . '"' . $attrs . '><img src="' . htmlspecialchars($allImages[0], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string) ($item['title'] ?? 'Imagen'), ENT_QUOTES, 'UTF-8') . '" loading="lazy"></a>';
    }
    $html = '<div class="actuality-image-gallery">';
    foreach ($allImages as $index => $imageUrl) {
        $alt = $index === 0 ? (string) ($item['title'] ?? 'Imagen') : ('Imagen ' . ($index + 1));
        $html .= '<a class="actuality-image-link actuality-image-link--gallery" href="' . $target . '"' . $attrs . '><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" loading="lazy"></a>';
    }
    $html .= '</div>';
    return $html;
};
$currentPage = max(1, (int) ($currentPage ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$prevPageUrl = trim((string) ($prevPageUrl ?? ''));
$nextPageUrl = trim((string) ($nextPageUrl ?? ''));
$fediverseUsername = trim((string) ($fediverseUsername ?? ''));
if ($fediverseUsername === '') {
    $fediverseUsername = trim((string) (explode('.', strtolower((string) parse_url((string) ($baseUrl ?? ''), PHP_URL_HOST)))[0] ?? 'blog'));
}
$defaultDescription = '';
if (function_exists('nammu_social_settings')) {
    $socialSettings = nammu_social_settings();
    $defaultDescription = trim((string) ($socialSettings['default_description'] ?? ''));
}
$headerConfig = is_array($homeSettings['header'] ?? null) ? $homeSettings['header'] : [];
$headerTypes = ['none', 'graphic', 'text', 'mixed'];
$headerType = in_array($headerConfig['type'] ?? 'none', $headerTypes, true) ? $headerConfig['type'] : 'none';
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
$headerImage = trim((string) ($headerConfig['image'] ?? ''));
if ($headerImage !== '' && function_exists('nammu_resolve_asset')) {
    $resolvedImage = nammu_resolve_asset($headerImage, (string) ($baseUrl ?? '/'));
    if (is_string($resolvedImage) && $resolvedImage !== '') {
        $headerImageUrl = $resolvedImage;
    }
}
$hasHeaderImage = $headerImageUrl !== null;
$profileHost = trim((string) preg_replace('#^https?://#i', '', rtrim((string) ($baseUrl ?? ''), '/')));
$profileFediverseHandle = '@' . $fediverseUsername . ($profileHost !== '' ? '@' . $profileHost : '');
$homeBrandTitle = $profileFediverseHandle;
$homeHeroTitle = trim((string) ($theme['blog'] ?? ($siteTitle ?? '')));
$homeHeroTagline = $defaultDescription !== '' ? $defaultDescription : trim((string) ($siteDescription ?? ''));
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
$renderProfileHeroText = static function () use ($homeBrandTitle, $homeHeroTitle, $homeHeroTagline): void {
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
$manualDisplayText = static function (array $item): string {
    $rawText = trim((string) ($item['raw_text'] ?? ''));
    if ($rawText !== '') {
        $text = preg_replace('#https?://[^\s<>"\')]+#iu', '', $rawText) ?? $rawText;
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);
        if ($text !== '') {
            return $text;
        }
    }
    $description = trim((string) ($item['description'] ?? ''));
    if ($description !== '') {
        return $description;
    }
    return trim((string) ($item['title'] ?? ''));
};
?>
<?php if ($headerType === 'graphic' && $headerImageUrl): ?>
    <section class="home-hero home-hero-graphic mode-<?= htmlspecialchars($headerMode, ENT_QUOTES, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars($headerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cabecera del sitio" decoding="async" fetchpriority="high">
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
                    <?php $insideGraphicClass = 'home-hero-graphic inside mode-' . $headerMode . ($mixedBoxedFullWidth ? ' full' : ''); ?>
                    <div class="<?= htmlspecialchars($insideGraphicClass, ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= htmlspecialchars($headerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cabecera del sitio" decoding="async" fetchpriority="high">
                    </div>
                <?php endif; ?>
                <?php $renderProfileHeroText(); ?>
                <?php if ($headerOrder === 'text-image' && $hasHeaderImage): ?>
                    <?php $insideGraphicClass = 'home-hero-graphic inside mode-' . $headerMode . ($mixedBoxedFullWidth ? ' full' : ''); ?>
                    <div class="<?= htmlspecialchars($insideGraphicClass, ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= htmlspecialchars($headerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cabecera del sitio" decoding="async" fetchpriority="high">
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if ($headerOrder === 'image-text' && $hasHeaderImage): ?>
                <div class="home-hero-graphic mode-<?= htmlspecialchars($headerMode, ENT_QUOTES, 'UTF-8') ?> full">
                    <img src="<?= htmlspecialchars($headerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cabecera del sitio" decoding="async" fetchpriority="high">
                </div>
            <?php endif; ?>
            <div class="<?= htmlspecialchars($textClasses, ENT_QUOTES, 'UTF-8') ?>">
                <?php $renderProfileHeroText(); ?>
            </div>
            <?php if ($headerOrder === 'text-image' && $hasHeaderImage): ?>
                <div class="home-hero-graphic mode-<?= htmlspecialchars($headerMode, ENT_QUOTES, 'UTF-8') ?> full">
                    <img src="<?= htmlspecialchars($headerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cabecera del sitio" decoding="async" fetchpriority="high">
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php elseif ($headerType === 'text'): ?>
    <section class="home-hero home-hero-text variant-<?= htmlspecialchars($textHeaderStyle, ENT_QUOTES, 'UTF-8') ?>">
        <?php $renderProfileHeroText(); ?>
    </section>
<?php endif; ?>

<?php if ($showHeaderButtons): ?>
    <?= $headerButtonsHtml ?>
<?php endif; ?>

<?php if (empty($items)): ?>
    <section class="actuality-empty">
        <p>Cuando configures feeds RSS automáticas o añadas notas manuales desde Redes, aquí aparecerán sus novedades agregadas.</p>
    </section>
<?php else: ?>
    <?php foreach (array_values($groupedItems) as $groupIndex => $group): ?>
        <?php [$leftColumnItems, $rightColumnItems] = $splitColumns($group['items']); ?>
        <?php $singleItem = count($group['items']) === 1 ? $group['items'][0] : null; ?>
        <?php $singleItemIsManual = is_array($singleItem) && !empty($singleItem['is_manual']); ?>
        <section class="actuality-day">
            <?php if ($groupIndex > 0 && !empty($group['short_label'])): ?>
                <div class="actuality-day-divider" aria-hidden="true">
                    <span><?= htmlspecialchars((string) $group['short_label'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            <div class="actuality-grid<?= (count($group['items']) === 1 && !$singleItemIsManual) ? ' is-single-item' : '' ?>">
                <?php if (count($group['items']) === 1 && !$singleItemIsManual): ?>
                    <?php $item = $group['items'][0]; ?>
                    <?php $isManual = !empty($item['is_manual']); ?>
                    <?php $isSiteContent = !empty($item['is_site_content']); ?>
                    <?php $siteContentTypeClass = $isSiteContent ? ' actuality-card--type-' . preg_replace('/[^a-z0-9_-]/i', '', strtolower((string) ($item['site_content_type'] ?? 'post'))) : ''; ?>
                    <?php $articleId = $isManual && !empty($item['id']) ? 'manual-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $item['id']) : ''; ?>
                    <article class="actuality-card actuality-card--full<?= $isManual ? ' actuality-card--manual' : '' ?><?= strtolower(trim((string) ($item['via'] ?? ''))) === 'boost' ? ' actuality-card--boost' : '' ?><?= $isSiteContent ? ' actuality-card--site' : '' ?><?= $siteContentTypeClass ?>"<?= $articleId !== '' ? ' id="' . htmlspecialchars($articleId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <?php if ($isManual): ?>
                            <?php $boostHeaderHtml = $renderBoostHeader($item); ?>
                            <?php if ($boostHeaderHtml !== ''): ?>
                                <?= $boostHeaderHtml ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="actuality-card-body">
                            <?php $imagesHtml = $renderImages($item, $isSiteContent); ?>
                            <?php if ($isSiteContent && $imagesHtml !== ''): ?>
                                <?= $imagesHtml ?>
                            <?php endif; ?>
                            <?php if (!$isManual): ?>
                                <h3><a href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>"<?= $isSiteContent ? '' : ' target="_blank" rel="noopener"' ?>><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></a></h3>
                            <?php endif; ?>
                            <?php if ($item['timestamp'] > 0 || $item['source'] !== ''): ?>
                                <p class="actuality-meta<?= $isSiteContent ? ' actuality-meta--site' : '' ?>">
                                    <?php if ($item['timestamp'] > 0): ?>
                                        <span><?= htmlspecialchars($formatDate($item['timestamp']), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <?php $sourceLabel = $actualitySourceLabel($item); ?>
                                    <?php if ($sourceLabel !== ''): ?>
                                        <span><?= htmlspecialchars($sourceLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!$isSiteContent && $imagesHtml !== ''): ?>
                                <?= $imagesHtml ?>
                            <?php endif; ?>
                            <?php if ($item['description'] !== ''): ?>
                                <div class="actuality-description"><?= $renderActualityText((string) $item['description'], $item) ?></div>
                            <?php endif; ?>
                            <?php if ($isManual && !empty($item['links'])): ?>
                                <p class="actuality-manual-links"><?= $renderLinks(is_array($item['links']) ? $item['links'] : [], $item) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php else: ?>
                <div class="actuality-column">
                    <?php foreach ($leftColumnItems as $item): ?>
                        <?php $isManual = !empty($item['is_manual']); ?>
                        <?php $isSiteContent = !empty($item['is_site_content']); ?>
                        <?php $siteContentTypeClass = $isSiteContent ? ' actuality-card--type-' . preg_replace('/[^a-z0-9_-]/i', '', strtolower((string) ($item['site_content_type'] ?? 'post'))) : ''; ?>
                        <?php $articleId = $isManual && !empty($item['id']) ? 'manual-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $item['id']) : ''; ?>
                        <?php $manualBody = $isManual ? $manualDisplayText($item) : ''; ?>
                        <article class="actuality-card<?= $isManual ? ' actuality-card--manual' : '' ?><?= strtolower(trim((string) ($item['via'] ?? ''))) === 'boost' ? ' actuality-card--boost' : '' ?><?= $isSiteContent ? ' actuality-card--site' : '' ?><?= $siteContentTypeClass ?>"<?= $articleId !== '' ? ' id="' . htmlspecialchars($articleId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <?php if ($isManual): ?>
                                <?php $boostHeaderHtml = $renderBoostHeader($item); ?>
                                <?php if ($boostHeaderHtml !== ''): ?>
                                    <?= $boostHeaderHtml ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="actuality-card-body">
                                <?php $imagesHtml = $renderImages($item, $isSiteContent); ?>
                                <?php if ($isSiteContent && $imagesHtml !== ''): ?>
                                    <?= $imagesHtml ?>
                                <?php endif; ?>
                                <?php if (!$isManual): ?>
                                    <h3><a href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>"<?= $isSiteContent ? '' : ' target="_blank" rel="noopener"' ?>><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></a></h3>
                                <?php endif; ?>
                                <?php if ($item['timestamp'] > 0 || $item['source'] !== ''): ?>
                                    <p class="actuality-meta<?= $isSiteContent ? ' actuality-meta--site' : '' ?>">
                                        <?php if ($item['timestamp'] > 0): ?>
                                            <span><?= htmlspecialchars($formatDate($item['timestamp']), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php $sourceLabel = $actualitySourceLabel($item); ?>
                                        <?php if ($sourceLabel !== ''): ?>
                                            <span><?= htmlspecialchars($sourceLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!$isSiteContent && $imagesHtml !== ''): ?>
                                    <?= $imagesHtml ?>
                                <?php endif; ?>
                                <?php if ((!$isManual && $item['description'] !== '') || ($isManual && $manualBody !== '')): ?>
                                    <?php $descriptionText = $isManual ? $manualBody : (string) $item['description']; ?>
                                    <div class="actuality-description"><?= $renderActualityText($descriptionText, $item) ?></div>
                                <?php endif; ?>
                                <?php if ($isManual && !empty($item['links'])): ?>
                                    <p class="actuality-manual-links"><?= $renderLinks(is_array($item['links']) ? $item['links'] : [], $item) ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="actuality-column">
                    <?php foreach ($rightColumnItems as $item): ?>
                        <?php $isManual = !empty($item['is_manual']); ?>
                        <?php $isSiteContent = !empty($item['is_site_content']); ?>
                        <?php $siteContentTypeClass = $isSiteContent ? ' actuality-card--type-' . preg_replace('/[^a-z0-9_-]/i', '', strtolower((string) ($item['site_content_type'] ?? 'post'))) : ''; ?>
                        <?php $articleId = $isManual && !empty($item['id']) ? 'manual-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $item['id']) : ''; ?>
                        <?php $manualBody = $isManual ? $manualDisplayText($item) : ''; ?>
                        <article class="actuality-card<?= $isManual ? ' actuality-card--manual' : '' ?><?= strtolower(trim((string) ($item['via'] ?? ''))) === 'boost' ? ' actuality-card--boost' : '' ?><?= $isSiteContent ? ' actuality-card--site' : '' ?><?= $siteContentTypeClass ?>"<?= $articleId !== '' ? ' id="' . htmlspecialchars($articleId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <?php if ($isManual): ?>
                                <?php $boostHeaderHtml = $renderBoostHeader($item); ?>
                                <?php if ($boostHeaderHtml !== ''): ?>
                                    <?= $boostHeaderHtml ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="actuality-card-body">
                                <?php $imagesHtml = $renderImages($item, $isSiteContent); ?>
                                <?php if ($isSiteContent && $imagesHtml !== ''): ?>
                                    <?= $imagesHtml ?>
                                <?php endif; ?>
                                <?php if (!$isManual): ?>
                                    <h3><a href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>"<?= $isSiteContent ? '' : ' target="_blank" rel="noopener"' ?>><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></a></h3>
                                <?php endif; ?>
                                <?php if ($item['timestamp'] > 0 || $item['source'] !== ''): ?>
                                    <p class="actuality-meta<?= $isSiteContent ? ' actuality-meta--site' : '' ?>">
                                        <?php if ($item['timestamp'] > 0): ?>
                                            <span><?= htmlspecialchars($formatDate($item['timestamp']), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php $sourceLabel = $actualitySourceLabel($item); ?>
                                        <?php if ($sourceLabel !== ''): ?>
                                            <span><?= htmlspecialchars($sourceLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!$isSiteContent && $imagesHtml !== ''): ?>
                                    <?= $imagesHtml ?>
                                <?php endif; ?>
                                <?php if ((!$isManual && $item['description'] !== '') || ($isManual && $manualBody !== '')): ?>
                                    <?php $descriptionText = $isManual ? $manualBody : (string) $item['description']; ?>
                                    <div class="actuality-description"><?= $renderActualityText($descriptionText, $item) ?></div>
                                <?php endif; ?>
                                <?php if ($isManual && !empty($item['links'])): ?>
                                    <p class="actuality-manual-links"><?= $renderLinks(is_array($item['links']) ? $item['links'] : [], $item) ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <?php if ($totalPages > 1): ?>
        <nav class="actuality-pagination" aria-label="Paginación de Fediverso">
            <div class="actuality-pagination__inner">
                <?php if ($prevPageUrl !== ''): ?>
                    <a class="actuality-pagination__link" href="<?= htmlspecialchars($prevPageUrl, ENT_QUOTES, 'UTF-8') ?>">&larr; Días posteriores</a>
                <?php else: ?>
                    <span class="actuality-pagination__link actuality-pagination__link--disabled">&larr; Días posteriores</span>
                <?php endif; ?>
                <span class="actuality-pagination__status">Página <?= $currentPage ?> de <?= $totalPages ?></span>
                <?php if ($nextPageUrl !== ''): ?>
                    <a class="actuality-pagination__link" href="<?= htmlspecialchars($nextPageUrl, ENT_QUOTES, 'UTF-8') ?>">Días anteriores &rarr;</a>
                <?php else: ?>
                    <span class="actuality-pagination__link actuality-pagination__link--disabled">Días anteriores &rarr;</span>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<style>
    .home-hero {
        margin-bottom: 0;
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
        margin-bottom: 0;
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
        padding: 0 clamp(1.5rem, 4vw, 3rem) 0.25rem;
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
    .home-hero-text h1 {
        margin: 0;
        font-size: clamp(2rem, 5vw, 3rem);
        line-height: 1.1;
        color: <?= $h1Color ?>;
    }
    .home-hero-tagline {
        margin: 0;
        font-size: 1.05rem;
        color: <?= $brandColor ?>;
        max-width: 720px;
        margin-left: auto;
        margin-right: auto;
    }
    .home-hero-profile-handle {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: <?= $accentColor ?>;
    }
    .actuality-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.5rem;
    }
    .actuality-day {
        margin-bottom: 2rem;
    }
    .actuality-day + .actuality-day {
        padding-top: 2rem;
    }
    .actuality-day-divider {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        margin: 0 0 1.2rem 0;
        color: rgba(0, 0, 0, 0.14);
    }
    .actuality-day-divider::before {
        content: "";
        flex: 1;
        border-top: 2px dashed currentColor;
    }
    .actuality-day-divider span {
        flex: 0 0 auto;
        font-size: 0.78rem;
        line-height: 1;
        letter-spacing: 0.04em;
        color: currentColor;
    }
    .actuality-column {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        min-width: 0;
    }
    .actuality-grid.is-single-item {
        grid-template-columns: minmax(0, 1fr);
    }
    .actuality-card--full {
        width: 100%;
    }
    .actuality-card {
        background: #fff;
        border: 1px solid rgba(0,0,0,0.08);
        border-radius: var(--nammu-radius-lg);
        overflow: hidden;
        box-shadow: 0 16px 36px rgba(0,0,0,0.06);
    }
    .actuality-card--site {
        background: <?= $highlight ?>;
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: var(--nammu-radius-md);
        color: <?= $textColor ?>;
        position: relative;
        overflow: hidden;
        box-sizing: border-box;
    }
    .actuality-card--site .actuality-card-body {
        padding: 1.15rem;
    }
    .actuality-card--manual {
        background: #fff6a8;
        border: 1px solid rgba(124, 92, 5, 0.24);
        box-shadow: 0 20px 30px rgba(91, 74, 12, 0.16);
        transform: rotate(-0.55deg);
    }
    .actuality-card--manual:nth-child(even) {
        transform: rotate(0.55deg);
    }
    .actuality-card--manual .actuality-card-body {
        padding-top: 1.35rem;
    }
    .actuality-card--manual,
    .actuality-card--manual .actuality-card-body,
    .actuality-card--manual .actuality-description,
    .actuality-card--manual .actuality-manual-links,
    .actuality-card--manual .actuality-meta,
    .actuality-card--manual h3,
    .actuality-card--manual p,
    .actuality-card--manual a,
    .actuality-card--manual span {
        font-family: "<?= $noteFont ?>", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    .actuality-card--manual .actuality-meta {
        color: #7a5300;
    }
    .actuality-card--manual::before {
        display: none;
    }
    .actuality-card--manual.actuality-card--boost {
        background: #dff4c2;
        border: 1px solid rgba(76, 122, 34, 0.24);
        box-shadow: 0 20px 30px rgba(62, 105, 28, 0.16);
    }
    .actuality-card--manual.actuality-card--boost .actuality-meta {
        color: #3f6f1f;
    }
    .actuality-card--boost::before {
        display: none;
    }
    .actuality-boost-origin {
        display: flex;
        justify-content: center;
        padding-top: 0.7rem;
        margin-bottom: -0.25rem;
    }
    .actuality-boost-origin__link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    .actuality-boost-origin__avatar {
        width: 56px;
        height: 56px;
        border-radius: 999px;
        object-fit: cover;
        display: block;
        border: 2px solid rgba(255,255,255,0.82);
        box-shadow: 0 8px 18px rgba(91, 74, 12, 0.18);
        background: rgba(255,255,255,0.72);
    }
    .actuality-boost-origin__avatar--fallback {
        align-items: center;
        background: rgba(255,255,255,0.72);
        color: #7a5300;
        display: inline-flex;
        font-size: 1.15rem;
        font-weight: 700;
        justify-content: center;
    }
    .actuality-image-link {
        display: block;
        background: <?= $highlight ?>;
        margin-bottom: 0.95rem;
    }
    .actuality-image-gallery {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
        margin-bottom: 0.95rem;
    }
    .actuality-image-link--gallery {
        margin-bottom: 0;
    }
    .actuality-image-link img {
        display: block;
        width: 100%;
        height: auto;
        aspect-ratio: 16 / 9;
        object-fit: cover;
    }
    .actuality-card-body {
        padding: 1.15rem 1.2rem 1.3rem;
    }
    .actuality-card h3 {
        margin: 0 0 0.55rem 0;
        font-size: 1.32rem;
        color: <?= $brandColor ?>;
        line-height: 1.25;
    }
    .actuality-card h3 a {
        color: <?= $headingSecondaryColor ?>;
        text-decoration: none;
        transition: color 0.2s ease;
    }
    .actuality-grid.is-single-item .actuality-card--site.actuality-card--type-post h3,
    .actuality-grid.is-single-item .actuality-card--site.actuality-card--type-podcast h3,
    .actuality-grid.is-single-item .actuality-card--site.actuality-card--type-itinerary h3 {
        font-size: clamp(1.8rem, 3.6vw, 2.4rem);
        line-height: 1.15;
        text-align: center;
    }
    .actuality-card--site .actuality-image-link {
        background: transparent;
        display: block;
        margin-bottom: 0.9rem;
    }
    .actuality-card--site .actuality-image-link img,
    .actuality-card--site .actuality-image-gallery img {
        border-radius: var(--nammu-radius-md);
    }
    .actuality-card--site .actuality-image-gallery {
        margin-bottom: 0.9rem;
    }
    .actuality-card h3 a:hover {
        color: <?= $accentColor ?>;
        text-decoration: underline;
    }
    .actuality-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0;
        margin: 0 0 0.8rem 0;
        width: 100%;
        font-size: 0.82rem;
        color: <?= $accentColor ?>;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        box-sizing: border-box;
    }
    .actuality-meta > span + span::before {
        content: "·";
        display: inline-block;
        margin: 0 0.45rem;
    }
    .actuality-meta--site {
        display: inline-flex;
        width: 100%;
        max-width: none;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
        letter-spacing: 0.012em;
        background: <?= $accentBackground ?>;
        border: 1px solid <?= $accentBorder ?>;
        color: <?= $accentColor ?>;
        border-radius: var(--nammu-radius-sm);
        text-transform: none;
        margin: 0 0 0.8rem 0;
    }
    .actuality-description {
        margin: 0;
        color: <?= $textColor ?>;
        line-height: 1.6;
    }
    .actuality-card--boost .actuality-description,
    .actuality-card:not(.actuality-card--manual):not(.actuality-card--site) .actuality-description {
        text-align: justify;
    }
    .actuality-fediverse-inline {
        display: inline-flex;
        align-items: center;
        gap: .22rem;
        vertical-align: text-bottom;
        color: <?= $accentColor ?>;
        text-decoration: none;
    }
    .actuality-fediverse-inline-icon {
        display: inline-flex;
        width: 0.95rem;
        height: 0.95rem;
    }
    .actuality-fediverse-inline-icon--metric {
        width: 0.9rem;
        height: 0.9rem;
    }
    .actuality-fediverse-inline svg {
        width: 100%;
        height: 100%;
        display: block;
    }
    .actuality-fediverse-inline-count {
        font-size: .92rem;
        line-height: 1;
        color: <?= $accentColor ?>;
    }
    .actuality-manual-links {
        margin: 1rem 0 0 0;
        font-size: 0.95rem;
        line-height: 1.5;
    }
    .actuality-manual-links a {
        color: <?= $accentColor ?>;
        text-decoration: underline;
    }
    .actuality-empty {
        background: <?= $highlight ?>;
        border-radius: var(--nammu-radius-lg);
        padding: 1.5rem;
        color: <?= $textColor ?>;
    }
    .actuality-pagination {
        margin-top: 2rem;
    }
    .actuality-pagination__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-radius: var(--nammu-radius-lg);
        background: <?= $highlight ?>;
    }
    .actuality-pagination__link {
        color: <?= $accentColor ?>;
        text-decoration: none;
        font-weight: 700;
    }
    .actuality-pagination__link--disabled {
        color: rgba(0, 0, 0, 0.35);
        pointer-events: none;
    }
    .actuality-pagination__status {
        color: <?= $textColor ?>;
        font-weight: 600;
    }
    @media (max-width: 760px) {
        .actuality-grid {
            grid-template-columns: minmax(0, 1fr);
        }
        .actuality-pagination__inner {
            flex-direction: column;
            text-align: center;
        }
    }
</style>
