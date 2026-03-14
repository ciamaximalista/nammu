<?php
/**
 * @var array<int, array{title:string,link:string,image:string,description:string,timestamp:int,source:string}> $items
 * @var int $feedsCount
 * @var bool $hasActuality
 */
$colors = $theme['colors'] ?? [];
$highlight = htmlspecialchars($colors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$textColor = htmlspecialchars($colors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
$accentColor = htmlspecialchars($colors['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8');
$brandColor = htmlspecialchars($colors['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$h1Color = htmlspecialchars($colors['h1'] ?? '#1b8eed', ENT_QUOTES, 'UTF-8');
$headingSecondaryColor = htmlspecialchars($colors['h2'] ?? '#ea2f28', ENT_QUOTES, 'UTF-8');
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
$formatDate = static function (int $timestamp): string {
    if ($timestamp <= 0) {
        return '';
    }
    return nammu_format_date_spanish((new DateTimeImmutable())->setTimestamp($timestamp), date('Y-m-d', $timestamp));
};
?>
<section class="actuality-hero">
    <div class="actuality-hero-inner">
        <h1>Actualidad</h1>
        <p>
            <?php if ($feedsCount > 0): ?>
                Ultimas fuentes compartidas
            <?php else: ?>
                No hay feeds automáticas configuradas todavía.
            <?php endif; ?>
        </p>
        <?= $headerButtonsHtml ?>
    </div>
</section>

<?php if (empty($items)): ?>
    <section class="actuality-empty">
        <p>Cuando configures feeds RSS automáticas en Redes, aquí aparecerán sus novedades agregadas.</p>
    </section>
<?php else: ?>
    <section class="actuality-grid">
        <?php foreach ($items as $item): ?>
            <article class="actuality-card">
                <div class="actuality-card-body">
                    <h2><a href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
                    <?php if ($item['timestamp'] > 0 || $item['source'] !== ''): ?>
                        <p class="actuality-meta">
                            <?php if ($item['timestamp'] > 0): ?>
                                <span><?= htmlspecialchars($formatDate($item['timestamp']), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if ($item['source'] !== ''): ?>
                                <span><?= htmlspecialchars(preg_replace('/^www\./i', '', $item['source']), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($item['image'] !== ''): ?>
                        <a class="actuality-image-link" href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                            <img src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                        </a>
                    <?php endif; ?>
                    <?php if ($item['description'] !== ''): ?>
                        <div class="actuality-description"><?= nl2br(htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8')) ?></div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<style>
    .actuality-hero {
        margin-bottom: 2rem;
    }
    .actuality-hero-inner {
        background: <?= $actualityHeroBackgroundEsc !== '' ? "linear-gradient(rgba(0,0,0,0.48), rgba(0,0,0,0.48)), url('{$actualityHeroBackgroundEsc}')" : $highlight ?>;
        background-size: cover;
        background-position: center;
        border-radius: var(--nammu-radius-lg);
        padding: 2rem;
        text-align: center;
        border: 1px solid rgba(0,0,0,0.05);
    }
    .actuality-hero-inner h1 {
        margin: 0 0 0.5rem 0;
        color: <?= $actualityHeroBackgroundEsc !== '' ? '#ffffff' : $h1Color ?>;
    }
    .actuality-hero-inner p {
        margin: 0 0 1.25rem 0;
        color: <?= $actualityHeroBackgroundEsc !== '' ? 'rgba(255,255,255,0.92)' : $textColor ?>;
    }
    .actuality-grid {
        column-count: 2;
        column-gap: 1.5rem;
    }
    .actuality-card {
        display: inline-block;
        width: 100%;
        margin: 0 0 1.5rem 0;
        break-inside: avoid;
        -webkit-column-break-inside: avoid;
        page-break-inside: avoid;
        background: #fff;
        border: 1px solid rgba(0,0,0,0.08);
        border-radius: var(--nammu-radius-lg);
        overflow: hidden;
        box-shadow: 0 16px 36px rgba(0,0,0,0.06);
    }
    .actuality-image-link {
        display: block;
        background: <?= $highlight ?>;
        margin-bottom: 0.95rem;
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
    .actuality-card h2 {
        margin: 0 0 0.55rem 0;
        font-size: 1.15rem;
        color: <?= $brandColor ?>;
        line-height: 1.25;
    }
    .actuality-card h2 a {
        color: <?= $headingSecondaryColor ?>;
        text-decoration: none;
        transition: color 0.2s ease;
    }
    .actuality-card h2 a:hover {
        color: <?= $accentColor ?>;
        text-decoration: underline;
    }
    .actuality-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin: 0 0 0.8rem 0;
        font-size: 0.82rem;
        color: <?= $accentColor ?>;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .actuality-description {
        margin: 0;
        color: <?= $textColor ?>;
        line-height: 1.6;
    }
    .actuality-empty {
        background: <?= $highlight ?>;
        border-radius: var(--nammu-radius-lg);
        padding: 1.5rem;
        color: <?= $textColor ?>;
    }
    @media (max-width: 760px) {
        .actuality-grid {
            column-count: 1;
        }
    }
</style>
