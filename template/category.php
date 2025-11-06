<?php
/**
 * @var string $category
 * @var int $count
 * @var array<int, array{slug:string,title:string,description:string,date:string,category:string,image:?string}> $posts
 */
$colors = $theme['colors'] ?? [];
$highlight = htmlspecialchars($colors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$textColor = htmlspecialchars($colors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
$accentColor = htmlspecialchars($colors['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8');
$brandColor = htmlspecialchars($colors['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$headingSecondaryColor = htmlspecialchars($colors['h2'] ?? '#ea2f28', ENT_QUOTES, 'UTF-8');
$homeSettings = $theme['home'] ?? [];
$columns = (int) ($homeSettings['columns'] ?? 2);
if ($columns < 1 || $columns > 3) {
    $columns = 2;
}
$cardStyle = $homeSettings['card_style'] ?? 'full';
$cardStyle = in_array($cardStyle, ['full', 'square-right', 'circle-right'], true) ? $cardStyle : 'full';
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
$searchActionBase = $baseUrl ?? '/';
$searchAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/buscar.php';
$renderSearchBox = static function (string $variant) use ($searchAction, $searchActionBase, $accentColor): string {
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
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
};
?>
<section class="category-detail-hero">
    <div>
        <p class="category-label">Categoría</p>
        <h1><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="category-count"><?= htmlspecialchars((string) $count, ENT_QUOTES, 'UTF-8') ?> <?= $count === 1 ? 'entrada publicada' : 'entradas publicadas' ?></p>
    </div>
</section>

<?php if ($searchTop): ?>
    <section class="site-search-block placement-top">
        <?= $renderSearchBox('variant-inline minimal') ?>
    </section>
<?php endif; ?>

<?php if (empty($posts)): ?>
    <p>No hay publicaciones en esta categoría todavía.</p>
<?php else: ?>
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
            $metaPieces = [];
            if ($post['date'] !== '') {
                $metaPieces[] = htmlspecialchars($post['date'], ENT_QUOTES, 'UTF-8');
            }
            if (!empty($post['category'])) {
                $categorySlug = nammu_slugify_label($post['category']);
                $categoryUrl = ($baseUrl ?? '/') !== '' ? rtrim($baseUrl, '/') . '/categoria/' . rawurlencode($categorySlug) : '/categoria/' . rawurlencode($categorySlug);
                $metaPieces[] = '<a class="category-tag-link" href="' . htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($post['category'], ENT_QUOTES, 'UTF-8') . '</a>';
            }
            $metaHtml = implode(' · ', $metaPieces);
            ?>
            <article class="<?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($imageUrl): ?>
                    <a class="<?= htmlspecialchars($thumbClass, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
                    </a>
                <?php endif; ?>
                <div class="post-body">
                    <h2><a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
                    <?php if ($metaHtml !== ''): ?>
                        <p class="post-meta"><?= $metaHtml ?></p>
                    <?php endif; ?>
                    <?php if ($post['description'] !== ''): ?>
                        <p class="post-description"><?= htmlspecialchars($post['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
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
    .site-search-form input[type="text"] {
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
    .search-categories-link {
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
    .search-categories-link:hover {
        background: rgba(0,0,0,0.12);
    }
    @media (max-width: 640px) {
        .site-search-form {
            flex-direction: column;
        }
        .site-search-form input[type="text"],
        .site-search-form button,
        .search-categories-link {
            width: 100%;
        }
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
    .post-card.style-circle-right .post-thumb {
        border-radius: 50%;
        shape-outside: circle();
        -webkit-shape-outside: circle();
    }
    .post-card.style-full .post-thumb {
        width: 100%;
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
    .post-card .post-body > *:not(:last-child) {
        margin-bottom: 0.6rem;
    }
    .post-card h2 {
        margin: 0;
        font-size: 1.6rem;
        color: <?= $headingSecondaryColor ?>;
    }
    .post-card h2 a {
        color: inherit;
    }
    .post-card .post-meta {
        margin: 0;
        font-size: 0.9rem;
        color: <?= $accentColor ?>;
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
    .post-grid.blocks-flat .post-card.style-media-right .post-thumb {
        margin-left: clamp(1rem, 3vw, 1.5rem);
    }
    .post-grid.blocks-flat .post-card.style-full .post-body {
        margin-top: 1.15rem;
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
</style>
