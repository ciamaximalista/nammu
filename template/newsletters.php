<?php
$theme = $theme ?? [];
$colors = $theme['colors'] ?? [];
$accentColor = htmlspecialchars($colors['accent'] ?? '#1b8eed', ENT_QUOTES, 'UTF-8');
$highlight = htmlspecialchars($colors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$accentBackground = 'rgba(27, 142, 237, 0.12)';
$accentBorder = 'rgba(27, 142, 237, 0.28)';
$accentRaw = $colors['accent'] ?? '';
if ($accentRaw !== '') {
    $hex = ltrim($accentRaw, '#');
    if (preg_match('/^[0-9a-f]{6}$/i', $hex)) {
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
$blogOwner = trim((string) ($theme['author'] ?? ''));
if ($blogOwner === '') {
    $blogOwner = $siteTitle ?? '';
}
$blogOwner = htmlspecialchars($blogOwner, ENT_QUOTES, 'UTF-8');
$homeSettings = $theme['home'] ?? [];
$headerButtonsMode = $homeSettings['header_buttons'] ?? 'none';
$showHeaderButtons = in_array($headerButtonsMode, ['home', 'both'], true);
$subscriptionSettings = is_array($theme['subscription'] ?? null) ? $theme['subscription'] : [];
$subscriptionModeForButtons = $subscriptionSettings['mode'] ?? 'none';
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
$searchActionBase = $baseUrl ?? '/';
$searchAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/buscar.php';
$categoriesIndexUrl = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/categorias';
$itinerariesIndexUrl = $itinerariesIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl, '/') . '/itinerarios' : '/itinerarios');
$podcastIndexUrl = $podcastIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl, '/') . '/podcast' : '/podcast');
$newslettersIndexUrl = $newslettersIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl, '/') . '/newsletters' : '/newsletters');
$hasCategories = !empty($hasCategories);
$hasPodcast = !empty($hasPodcast);
$hasNewsletters = !empty($hasNewsletters);
$postalEnabled = $postalEnabled ?? false;
$postalUrl = $postalUrl ?? '/correos.php';
$postalLogoSvg = $postalLogoSvg ?? '';
$headerButtonsHtml = '';
if ($showHeaderButtons && function_exists('nammu_render_header_buttons')) {
    $headerButtonsHtml = nammu_render_header_buttons([
        'accent' => $colors['accent'] ?? '#0a4c8a',
        'search_url' => $searchAction,
        'categories_url' => $categoriesIndexUrl,
        'itineraries_url' => $itinerariesIndexUrl,
        'podcast_url' => $podcastIndexUrl,
        'newsletters_url' => $newslettersIndexUrl,
        'avisos_url' => rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/avisos.php',
        'postal_url' => $postalUrl,
        'postal_svg' => $postalLogoSvg,
        'has_categories' => $hasCategories,
        'has_itineraries' => !empty($hasItineraries),
        'has_podcast' => $hasPodcast,
        'has_newsletters' => $hasNewsletters,
        'subscription_enabled' => $subscriptionModeForButtons !== 'none',
        'postal_enabled' => $postalEnabled,
    ]);
}
?>

<section class="itinerary-archive-hero">
    <div>
        <p class="itinerary-archive-label"><?= $blogOwner ?></p>
        <h1>Newsletters</h1>
        <?= $headerButtonsHtml ?>
    </div>
</section>

<?php if (empty($newsletters ?? [])): ?>
    <p>No hay newsletters enviadas todavía.</p>
<?php else: ?>
    <section class="itinerary-grid columns-<?= $columns ?> blocks-<?= htmlspecialchars($blocksMode, ENT_QUOTES, 'UTF-8') ?>">
<?php $imageIndex = 0; ?>
<?php foreach ($newsletters as $newsletter): ?>
            <?php
            $url = $newslettersIndexUrl . '/' . rawurlencode((string) ($newsletter['slug'] ?? ''));
            $cover = $newsletter['image'] ?? '';
            $cardClassParts = ['itinerary-card', 'style-' . $cardStyle];
            if ($cardStyle === 'full') {
                $cardClassParts[] = 'full-mode-' . $fullImageMode;
            } elseif (in_array($cardStyle, ['square-right', 'circle-right'], true)) {
                $cardClassParts[] = 'style-media-right';
            }
            $cardClass = implode(' ', $cardClassParts);
            $thumbClassParts = ['itinerary-thumb'];
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
            $dateLabel = $newsletter['date'] ?? '';
            $metaText = $dateLabel !== '' ? $dateLabel : '';
            ?>
            <article class="<?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($cover): ?>
                    <a class="<?= htmlspecialchars($thumbClass, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                        <?php $priorityAttrs = $imageIndex === 0 ? ' decoding="async" fetchpriority="high"' : ' loading="lazy" decoding="async"'; ?>
                        <?php $imageIndex++; ?>
                        <img src="<?= htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($newsletter['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"<?= $priorityAttrs ?>>
                    </a>
                <?php endif; ?>
                <div class="itinerary-card__body">
                    <h2>
                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($newsletter['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </h2>
                    <?php if ($metaText !== ''): ?>
                        <p class="itinerary-card__meta"><?= htmlspecialchars($metaText, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (($newsletter['description'] ?? '') !== ''): ?>
                        <p class="itinerary-card__description">
                            <?= htmlspecialchars($newsletter['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<style>
    .itinerary-archive-hero {
        margin-bottom: 2rem;
        background: <?= $highlight ?>;
        border-radius: var(--nammu-radius-lg);
        padding: 1.7rem 2rem;
        border: 1px solid rgba(0,0,0,0.05);
        text-align: center;
    }
    .itinerary-archive-label {
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 0.85rem;
        color: <?= $accentColor ?>;
    }
    .itinerary-archive-hero h1 {
        margin: 0.3rem 0 0;
        font-size: clamp(2rem, 5vw, 2.8rem);
        color: <?= $brandColor ?>;
    }
    .itinerary-grid {
        display: grid;
        gap: 1.5rem;
    }
    .itinerary-grid.columns-1 {
        grid-template-columns: minmax(0, 1fr);
    }
    .itinerary-grid.columns-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .itinerary-grid.columns-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    @media (max-width: 900px) {
        .itinerary-grid.columns-3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 640px) {
        .itinerary-grid.columns-2,
        .itinerary-grid.columns-3 {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    .itinerary-grid.blocks-flat .itinerary-card {
        border: none;
        background: transparent;
    }
    .itinerary-grid.blocks-flat .itinerary-card__body {
        padding: 0.8rem 0 0 0;
    }
    .itinerary-card {
        background: #fff;
        border-radius: var(--nammu-radius-lg);
        border: 1px solid rgba(0,0,0,0.08);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 100%;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .itinerary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 30px rgba(0,0,0,0.08);
    }
    .itinerary-card.style-media-right {
        flex-direction: row;
        align-items: stretch;
    }
    .itinerary-card.style-media-right .itinerary-card__body {
        flex: 1;
    }
    .itinerary-card.style-media-right .itinerary-thumb {
        flex: 0 0 auto;
    }
    .itinerary-thumb {
        display: block;
        width: 100%;
        background: <?= $accentBackground ?>;
    }
    .itinerary-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .itinerary-thumb.thumb-wide {
        height: 200px;
    }
    .itinerary-thumb.thumb-right {
        width: 38%;
    }
    .itinerary-thumb.thumb-square img {
        aspect-ratio: 1 / 1;
    }
    .itinerary-thumb.thumb-vertical img {
        aspect-ratio: 3 / 4;
    }
    .itinerary-thumb.thumb-circle img {
        aspect-ratio: 1 / 1;
        border-radius: 0 0 0 0;
    }
    .itinerary-card__body {
        padding: 1.2rem 1.3rem 1.4rem 1.3rem;
    }
    .itinerary-card__body h2 {
        margin: 0 0 0.6rem 0;
        font-size: 1.3rem;
        color: <?= $headingSecondaryColor ?>;
    }
    .itinerary-card__body h2 a {
        color: inherit;
        text-decoration: none;
    }
    .itinerary-card__meta {
        margin: 0 0 0.6rem 0;
        font-size: 0.85rem;
        color: <?= $accentColor ?>;
        font-weight: 600;
    }
    .itinerary-card__description {
        margin: 0;
        color: <?= $brandColor ?>;
        opacity: 0.8;
        font-size: 0.95rem;
        line-height: 1.6;
    }
</style>
