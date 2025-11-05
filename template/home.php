<?php
/**
 * @var array<int, array{slug:string,title:string,description:string,date:string,image:?string}> $posts
 * @var string $bioHtml
 * @var callable $resolveImage
 * @var callable $postUrl
 * @var array|null $pagination
 * @var callable $paginationUrl
 * @var array $theme
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
$homeSettings = $theme['home'] ?? [];
$columns = (int) ($homeSettings['columns'] ?? 2);
if ($columns < 1 || $columns > 3) {
    $columns = 2;
}
$pagination = $pagination ?? null;
$hasPagination = is_array($pagination) && ($pagination['total'] ?? 1) > 1;
$baseHref = $baseUrl ?? '/';
$cardStyle = $homeSettings['card_style'] ?? 'full';
$cardStyle = in_array($cardStyle, ['full', 'square-right', 'circle-right'], true) ? $cardStyle : 'full';
$blocksMode = $homeSettings['blocks'] ?? 'boxed';
if (!in_array($blocksMode, ['boxed', 'flat'], true)) {
    $blocksMode = 'boxed';
}
$headerConfig = $homeSettings['header'] ?? [];
$headerTypes = ['none', 'graphic', 'text'];
$headerType = in_array($headerConfig['type'] ?? 'none', $headerTypes, true) ? $headerConfig['type'] : 'none';
$headerImageSetting = trim($headerConfig['image'] ?? '');
$headerMode = in_array($headerConfig['mode'] ?? 'contain', ['contain', 'cover'], true) ? $headerConfig['mode'] : 'contain';
$headerImageUrl = null;
if ($headerType === 'graphic' && $headerImageSetting !== '') {
    $headerImageUrl = $resolveImage($headerImageSetting);
    if ($headerImageUrl === null) {
        $headerType = 'none';
    }
}
$homeBrandTitle = $theme['author'] ?? '';
$homeHeroTitle = $theme['blog'] ?? $siteTitle ?? '';

$defaultDescription = $socialConfig['default_description'] ?? '';
$homeHeroTagline = $defaultDescription !== '' ? $defaultDescription : ($siteDescription ?? '');
$hasTextHeaderContent = ($homeBrandTitle !== '' || $homeHeroTitle !== '' || $homeHeroTagline !== '');
if ($headerType === 'text' && !$hasTextHeaderContent) {
    $headerType = 'none';
}
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
<?php elseif ($headerType === 'text'): ?>
    <section class="home-hero home-hero-text">
        <?php if ($homeBrandTitle !== ''): ?>
            <div class="home-brand">
                <span class="home-brand-title"><?= htmlspecialchars($homeBrandTitle, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>
        <?php if ($homeHeroTitle !== ''): ?>
            <h1><?= htmlspecialchars($homeHeroTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <?php endif; ?>
        <?php if ($homeHeroTagline !== ''): ?>
            <p class="home-hero-tagline"><?= htmlspecialchars($homeHeroTagline, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($bioHtml !== ''): ?>
    <section class="site-bio">
        <?= $bioHtml ?>
    </section>
<?php endif; ?>

<?php if (!empty($posts)): ?>
    <section class="post-grid columns-<?= $columns ?> blocks-<?= htmlspecialchars($blocksMode, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($posts as $post): ?>
            <?php
            $link = $postUrl($post['slug']);
            $imageUrl = $resolveImage($post['image']);
            $cardClassParts = ['post-card', 'style-' . $cardStyle];
            if (in_array($cardStyle, ['square-right', 'circle-right'], true)) {
                $cardClassParts[] = 'style-media-right';
            }
            $cardClass = implode(' ', $cardClassParts);
            $thumbClassParts = ['post-thumb'];
            if ($cardStyle === 'full') {
                $thumbClassParts[] = 'thumb-wide';
            } else {
                $thumbClassParts[] = 'thumb-right';
                $thumbClassParts[] = $cardStyle === 'square-right' ? 'thumb-square' : 'thumb-circle';
            }
            $thumbClass = implode(' ', $thumbClassParts);
            $category = $post['category'] ?? '';
            $metaText = '';
            if (($post['date'] ?? '') !== '') {
                $metaText = 'Publicado el ' . $post['date'];
            }
            if ($category !== '') {
                $metaText .= $metaText !== '' ? ' · ' . $category : $category;
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
                    <?php if ($metaText !== ''): ?>
                        <p class="post-meta"><?= htmlspecialchars($metaText, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ($post['description'] !== ''): ?>
                        <p class="post-description"><?= htmlspecialchars($post['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php else: ?>
    <p>No hay publicaciones disponibles todavía.</p>
<?php endif; ?>

<?php if ($hasPagination): ?>
    <?php
    $currentPage = (int) ($pagination['current'] ?? 1);
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

<style>
    .home-hero {
        margin-bottom: 2rem;
    }
    .home-hero-graphic {
        min-height: 160px;
        border-radius: var(--nammu-radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
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
    .home-hero-text {
        display: grid;
        gap: 0.75rem;
        text-align: center;
        border-radius: var(--nammu-radius-lg);
        padding: 2rem clamp(1.5rem, 4vw, 3rem);
        background: <?= $highlight ?>;
        border: 1px solid rgba(0,0,0,0.05);
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
        color: <?= $textColor ?>;
        max-width: 720px;
        margin-left: auto;
        margin-right: auto;
    }
    .site-bio p {
        margin: 0 0 1rem 0;
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
    }
    .post-card.style-full .post-thumb img {
        height: auto;
        border-radius: var(--nammu-radius-md);
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
    }
    .post-card.style-media-right .post-thumb img {
        height: 100%;
        border-radius: var(--nammu-radius-lg);
    }
    .post-card.style-circle-right .post-thumb img {
        border-radius: 50%;
    }
    .post-card.style-circle-right .post-thumb {
        shape-outside: circle();
        -webkit-shape-outside: circle();
    }
    .post-card h2 {
        margin: 0;
        font-size: 1.3rem;
        line-height: 1.25;
    }
    .post-grid.columns-1 .post-card h2 {
        font-size: clamp(1.6rem, 3.2vw, 2.05rem);
    }
    .post-grid.columns-2 .post-card h2 {
        font-size: clamp(1.45rem, 2.6vw, 1.75rem);
    }
    .post-grid.columns-3 .post-card h2 {
        font-size: clamp(1.25rem, 2vw, 1.45rem);
    }
    .post-card h2 a {
        color: <?= $accentColor ?>;
    }
    .post-card h2 a:hover {
        text-decoration: underline;
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
        }
        .post-card.style-media-right .post-thumb {
            float: none;
            width: 100%;
            margin: 0 0 0.75rem 0;
            shape-outside: none;
            -webkit-shape-outside: none;
        }
        .post-card.style-media-right .post-thumb img {
            border-radius: var(--nammu-radius-md);
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
    .post-grid.blocks-flat .post-card.style-media-right .post-thumb {
        margin-left: clamp(1rem, 3vw, 1.5rem);
    }
    .post-grid.blocks-flat .post-card.style-full .post-body {
        margin-top: 1.15rem;
    }
</style>
