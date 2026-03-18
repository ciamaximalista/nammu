<?php
/**
 * @var array<int, array{title:string,link:string,image:string,description:string,timestamp:int,source:string,is_manual?:bool,id?:string,links?:array<int,string>,raw_text?:string}> $items
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
$fediverseIcon = function_exists('nammu_footer_icon_svgs') ? (string) (nammu_footer_icon_svgs()['fediverse'] ?? '') : '';
$actualityFediverseLink = static function (array $item) use ($config): string {
    if (!function_exists('nammu_fediverse_public_thread_url_for_actuality_item')) {
        return '';
    }
    return trim((string) nammu_fediverse_public_thread_url_for_actuality_item($item, is_array($config ?? null) ? $config : []));
};
$renderActualityText = static function (string $text, array $item) use ($fediverseIcon, $actualityFediverseLink): string {
    $html = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    $fediverseUrl = $actualityFediverseLink($item);
    if ($fediverseUrl !== '' && $fediverseIcon !== '') {
        $html .= ' <a class="actuality-fediverse-inline" href="' . htmlspecialchars($fediverseUrl, ENT_QUOTES, 'UTF-8') . '" title="En el Fediverso" aria-label="En el Fediverso">' . $fediverseIcon . '</a>';
    }
    return $html;
};
$formatDate = static function (int $timestamp): string {
    if ($timestamp <= 0) {
        return '';
    }
    return nammu_format_date_spanish((new DateTimeImmutable())->setTimestamp($timestamp), date('Y-m-d', $timestamp));
};
$groupedItems = [];
foreach ($items as $item) {
    $timestamp = (int) ($item['timestamp'] ?? 0);
    $groupKey = $timestamp > 0 ? date('Y-m-d', $timestamp) : 'sin-fecha';
    if (!isset($groupedItems[$groupKey])) {
        $groupedItems[$groupKey] = [
            'label' => $timestamp > 0 ? $formatDate($timestamp) : 'Sin fecha',
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
$renderLinks = static function (array $links): string {
    $links = array_values(array_filter(array_map('strval', $links)));
    if (empty($links)) {
        return '';
    }
    $bits = [];
    foreach ($links as $index => $url) {
        $label = count($links) === 1 ? 'Enlace' : ('Enlace ' . ($index + 1));
        $bits[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . $label . '</a>';
    }
    return implode(', ', $bits);
};
$currentPage = max(1, (int) ($currentPage ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$prevPageUrl = trim((string) ($prevPageUrl ?? ''));
$nextPageUrl = trim((string) ($nextPageUrl ?? ''));
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
<section class="actuality-hero">
    <div class="actuality-hero-inner">
        <h1>Fediverso</h1>
        <p>
            <?php if ($feedsCount > 0): ?>
                Página de perfil de @<?= htmlspecialchars((string) ($config['fediverse']['preferred_username'] ?? $config['blog_slug'] ?? 'blog'), ENT_QUOTES, 'UTF-8') ?>@<?= htmlspecialchars((string) preg_replace('#^https?://#i', '', rtrim((string) ($baseUrl ?? ''), '/')), ENT_QUOTES, 'UTF-8') ?>
            <?php elseif ($hasActuality): ?>
                Notas y fuentes compartidas
            <?php else: ?>
                No hay feeds automáticas configuradas todavía.
            <?php endif; ?>
        </p>
        <?= $headerButtonsHtml ?>
    </div>
</section>

<?php if (empty($items)): ?>
    <section class="actuality-empty">
        <p>Cuando configures feeds RSS automáticas o añadas notas manuales desde Redes, aquí aparecerán sus novedades agregadas.</p>
    </section>
<?php else: ?>
    <?php foreach ($groupedItems as $group): ?>
        <?php [$leftColumnItems, $rightColumnItems] = $splitColumns($group['items']); ?>
        <?php $singleItem = count($group['items']) === 1 ? $group['items'][0] : null; ?>
        <?php $singleItemIsManual = is_array($singleItem) && !empty($singleItem['is_manual']); ?>
        <section class="actuality-day">
            <h2 class="actuality-day-heading"><?= htmlspecialchars((string) $group['label'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="actuality-grid<?= (count($group['items']) === 1 && !$singleItemIsManual) ? ' is-single-item' : '' ?>">
                <?php if (count($group['items']) === 1 && !$singleItemIsManual): ?>
                    <?php $item = $group['items'][0]; ?>
                    <?php $isManual = !empty($item['is_manual']); ?>
                    <?php $isSiteContent = !empty($item['is_site_content']); ?>
                    <?php $articleId = $isManual && !empty($item['id']) ? 'manual-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $item['id']) : ''; ?>
                    <article class="actuality-card actuality-card--full<?= $isManual ? ' actuality-card--manual' : '' ?><?= $isSiteContent ? ' actuality-card--site' : '' ?>"<?= $articleId !== '' ? ' id="' . htmlspecialchars($articleId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <div class="actuality-card-body">
                            <?php if (!$isManual): ?>
                                <h3><a href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>"<?= $isSiteContent ? '' : ' target="_blank" rel="noopener"' ?>><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></a></h3>
                            <?php endif; ?>
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
                                <a class="actuality-image-link" href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>"<?= $isSiteContent ? '' : ' target="_blank" rel="noopener"' ?>>
                                    <img src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                </a>
                            <?php endif; ?>
                            <?php if ($item['description'] !== ''): ?>
                                <div class="actuality-description"><?= $renderActualityText((string) $item['description'], $item) ?></div>
                            <?php endif; ?>
                            <?php if ($isManual && !empty($item['links'])): ?>
                                <p class="actuality-manual-links"><?= $renderLinks(is_array($item['links']) ? $item['links'] : []) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php else: ?>
                <div class="actuality-column">
                    <?php foreach ($leftColumnItems as $item): ?>
                        <?php $isManual = !empty($item['is_manual']); ?>
                        <?php $isSiteContent = !empty($item['is_site_content']); ?>
                        <?php $articleId = $isManual && !empty($item['id']) ? 'manual-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $item['id']) : ''; ?>
                        <?php $manualBody = $isManual ? $manualDisplayText($item) : ''; ?>
                        <article class="actuality-card<?= $isManual ? ' actuality-card--manual' : '' ?><?= $isSiteContent ? ' actuality-card--site' : '' ?>"<?= $articleId !== '' ? ' id="' . htmlspecialchars($articleId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <div class="actuality-card-body">
                                <?php if (!$isManual): ?>
                                    <h3><a href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>"<?= $isSiteContent ? '' : ' target="_blank" rel="noopener"' ?>><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></a></h3>
                                <?php endif; ?>
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
                                    <a class="actuality-image-link" href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>"<?= $isSiteContent ? '' : ' target="_blank" rel="noopener"' ?>>
                                        <img src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                    </a>
                                <?php endif; ?>
                                <?php if ((!$isManual && $item['description'] !== '') || ($isManual && $manualBody !== '')): ?>
                                    <?php $descriptionText = $isManual ? $manualBody : (string) $item['description']; ?>
                                    <div class="actuality-description"><?= $renderActualityText($descriptionText, $item) ?></div>
                                <?php endif; ?>
                                <?php if ($isManual && !empty($item['links'])): ?>
                                    <p class="actuality-manual-links"><?= $renderLinks(is_array($item['links']) ? $item['links'] : []) ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="actuality-column">
                    <?php foreach ($rightColumnItems as $item): ?>
                        <?php $isManual = !empty($item['is_manual']); ?>
                        <?php $isSiteContent = !empty($item['is_site_content']); ?>
                        <?php $articleId = $isManual && !empty($item['id']) ? 'manual-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $item['id']) : ''; ?>
                        <?php $manualBody = $isManual ? $manualDisplayText($item) : ''; ?>
                        <article class="actuality-card<?= $isManual ? ' actuality-card--manual' : '' ?><?= $isSiteContent ? ' actuality-card--site' : '' ?>"<?= $articleId !== '' ? ' id="' . htmlspecialchars($articleId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <div class="actuality-card-body">
                                <?php if (!$isManual): ?>
                                    <h3><a href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>"<?= $isSiteContent ? '' : ' target="_blank" rel="noopener"' ?>><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></a></h3>
                                <?php endif; ?>
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
                                    <a class="actuality-image-link" href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>"<?= $isSiteContent ? '' : ' target="_blank" rel="noopener"' ?>>
                                        <img src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                    </a>
                                <?php endif; ?>
                                <?php if ((!$isManual && $item['description'] !== '') || ($isManual && $manualBody !== '')): ?>
                                    <?php $descriptionText = $isManual ? $manualBody : (string) $item['description']; ?>
                                    <div class="actuality-description"><?= $renderActualityText($descriptionText, $item) ?></div>
                                <?php endif; ?>
                                <?php if ($isManual && !empty($item['links'])): ?>
                                    <p class="actuality-manual-links"><?= $renderLinks(is_array($item['links']) ? $item['links'] : []) ?></p>
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
                    <a class="actuality-pagination__link" href="<?= htmlspecialchars($prevPageUrl, ENT_QUOTES, 'UTF-8') ?>">&larr; Días anteriores</a>
                <?php else: ?>
                    <span class="actuality-pagination__link actuality-pagination__link--disabled">&larr; Días anteriores</span>
                <?php endif; ?>
                <span class="actuality-pagination__status">Página <?= $currentPage ?> de <?= $totalPages ?></span>
                <?php if ($nextPageUrl !== ''): ?>
                    <a class="actuality-pagination__link" href="<?= htmlspecialchars($nextPageUrl, ENT_QUOTES, 'UTF-8') ?>">Días posteriores &rarr;</a>
                <?php else: ?>
                    <span class="actuality-pagination__link actuality-pagination__link--disabled">Días posteriores &rarr;</span>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
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
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.5rem;
    }
    .actuality-day {
        margin-bottom: 2rem;
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
    .actuality-day-heading {
        margin: 0 0 1.1rem 0;
        text-align: center;
        color: <?= $brandColor ?>;
        font-size: clamp(1.2rem, 2.5vw, 1.5rem);
    }
    .actuality-card {
        background: #fff;
        border: 1px solid rgba(0,0,0,0.08);
        border-radius: var(--nammu-radius-lg);
        overflow: hidden;
        box-shadow: 0 16px 36px rgba(0,0,0,0.06);
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
    .actuality-card--manual .actuality-meta {
        color: #7a5300;
    }
    .actuality-card--manual::before {
        content: "";
        display: block;
        width: 64px;
        height: 22px;
        margin: 0.7rem auto -0.25rem;
        border-radius: 4px;
        background: rgba(255,255,255,0.42);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.38);
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
    .actuality-card h3 a:hover {
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
    .actuality-fediverse-inline {
        display: inline-flex;
        width: 0.95rem;
        height: 0.95rem;
        vertical-align: text-bottom;
        color: <?= $accentColor ?>;
    }
    .actuality-fediverse-inline svg {
        width: 100%;
        height: 100%;
        display: block;
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
