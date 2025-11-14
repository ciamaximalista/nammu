<?php
/**
 * @var array<int, array{letter:string,display:string,slug:string,count:int,url:string}> $letters
 * @var int $total
 */
$colors = $theme['colors'] ?? [];
$highlight = htmlspecialchars($colors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$textColor = htmlspecialchars($colors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
$accentColor = htmlspecialchars($colors['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8');
$brandColor = htmlspecialchars($colors['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$h1Color = htmlspecialchars($colors['h1'] ?? '#1b8eed', ENT_QUOTES, 'UTF-8');
$searchSettings = $theme['search'] ?? [];
$searchMode = in_array($searchSettings['mode'] ?? 'none', ['none', 'home', 'single', 'both'], true) ? $searchSettings['mode'] : 'none';
$searchPositionSetting = in_array($searchSettings['position'] ?? 'title', ['title', 'footer'], true) ? $searchSettings['position'] : 'title';
$shouldShowSearch = in_array($searchMode, ['home', 'both'], true);
$searchTop = $shouldShowSearch && $searchPositionSetting === 'title';
$searchBottom = $shouldShowSearch && $searchPositionSetting === 'footer';
$searchActionBase = $baseUrl ?? '/';
$searchAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/buscar.php';
$letterIndexUrlValue = $lettersIndexUrl ?? null;
$itinerariesIndexUrl = $itinerariesIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl ?? '/', '/') . '/itinerarios' : '/itinerarios');
$hasItineraries = !empty($hasItineraries);
$showLetterButton = !empty($showLetterIndexButton) && !empty($letterIndexUrlValue);
$renderSearchBox = static function (string $variant) use ($searchAction, $searchActionBase, $accentColor, $letterIndexUrlValue, $showLetterButton, $hasItineraries, $itinerariesIndexUrl): string {
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
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 10L12 6L20 10L12 14L4 10Z" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round" fill="none"/>
                        <path d="M6 12V16C6 17.6569 9.58172 19 12 19C14.4183 19 18 17.6569 18 16V12" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </a>
            <?php endif; ?>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
};
?>
<section class="letters-hero">
    <div class="letters-hero-inner">
        <h1>Entradas por letra</h1>
        <p>Explora <?= htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8') ?> bloque<?= $total === 1 ? '' : 's' ?> ordenados alfabéticamente.</p>
    </div>
</section>

<?php if ($searchTop): ?>
    <section class="site-search-block placement-top">
        <?= $renderSearchBox('variant-inline minimal') ?>
    </section>
<?php endif; ?>

<?php if (empty($letters)): ?>
    <section class="letters-empty">
        <p>Todavía no hay publicaciones disponibles para construir el índice alfabético.</p>
    </section>
<?php else: ?>
    <section class="letters-grid">
        <?php foreach ($letters as $letter): ?>
            <article class="letter-card">
                <div class="letter-symbol"><?= htmlspecialchars($letter['display'], ENT_QUOTES, 'UTF-8') ?></div>
                <p class="letter-count"><?= htmlspecialchars((string) $letter['count'], ENT_QUOTES, 'UTF-8') ?> <?= $letter['count'] === 1 ? 'entrada' : 'entradas' ?></p>
                <a class="letter-link" href="<?= htmlspecialchars($letter['url'], ENT_QUOTES, 'UTF-8') ?>">Ver publicaciones</a>
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
    .letters-hero {
        margin-bottom: 2rem;
    }
    .letters-hero-inner {
        background: <?= $highlight ?>;
        border-radius: var(--nammu-radius-lg);
        padding: 1.5rem 2rem;
        border: 1px solid rgba(0,0,0,0.05);
        text-align: center;
    }
    .letters-hero-inner h1 {
        margin: 0 0 0.5rem 0;
        color: <?= $h1Color ?>;
    }
    .letters-hero-inner p {
        margin: 0;
        color: <?= $textColor ?>;
    }
    .letters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }
    .letter-card {
        border: 1px solid rgba(0,0,0,0.06);
        border-radius: var(--nammu-radius-md);
        padding: 1.25rem;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        align-items: center;
        text-align: center;
    }
    .letter-symbol {
        font-size: clamp(2.3rem, 6vw, 3.2rem);
        font-weight: 700;
        color: <?= $brandColor ?>;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .letter-count {
        margin: 0;
        color: <?= $textColor ?>;
    }
    .letter-link {
        margin-top: auto;
        color: <?= $accentColor ?>;
        font-weight: 600;
    }
    .letters-empty {
        padding: 1.5rem;
        border-radius: var(--nammu-radius-md);
        border: 1px dashed rgba(0,0,0,0.4);
        text-align: center;
        color: <?= $textColor ?>;
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
        background: rgba(0,0,0,0.12);
    }
    @media (max-width: 640px) {
        .site-search-form {
            flex-direction: column;
        }
        .site-search-form input[type="text"],
        .site-search-form button,
        .search-categories-link,
        .search-letters-link,
        .search-itineraries-link {
            width: 100%;
        }
    }
</style>
