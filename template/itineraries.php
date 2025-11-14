<?php
/**
 * @var \Nammu\Core\Itinerary[] $itineraries
 * @var callable $itineraryUrl
 * @var callable $resolveImage
 * @var array $theme
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
$blogOwner = trim((string) ($theme['author'] ?? ''));
if ($blogOwner === '') {
    $blogOwner = $siteTitle ?? '';
}
$blogOwner = htmlspecialchars($blogOwner, ENT_QUOTES, 'UTF-8');
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
?>

<section class="itinerary-archive-hero">
    <div>
        <p class="itinerary-archive-label"><?= $blogOwner ?></p>
        <h1>Itinerarios</h1>
    </div>
</section>

<?php if (empty($itineraries)): ?>
    <p>No hay itinerarios publicados todavía.</p>
<?php else: ?>
    <section class="itinerary-grid columns-<?= $columns ?> blocks-<?= htmlspecialchars($blocksMode, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($itineraries as $itinerary): ?>
            <?php
            $url = $itineraryUrl($itinerary);
            $cover = $resolveImage($itinerary->getImage());
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
                $thumbClassParts[] = $cardStyle === 'square-right' ? 'thumb-square' : 'thumb-circle';
            }
            $thumbClass = implode(' ', $thumbClassParts);
            $topicCount = $itinerary->getTopicCount();
            $metaText = $topicCount === 1
                ? 'Incluye 1 tema'
                : 'Incluye ' . $topicCount . ' temas';
            ?>
            <article class="<?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($cover): ?>
                    <a class="<?= htmlspecialchars($thumbClass, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($itinerary->getTitle(), ENT_QUOTES, 'UTF-8') ?>">
                    </a>
                <?php endif; ?>
                <div class="itinerary-card__body">
                    <h2>
                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($itinerary->getTitle(), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </h2>
                    <p class="itinerary-card__meta"><?= htmlspecialchars($metaText, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($itinerary->getDescription() !== ''): ?>
                        <p class="itinerary-card__description">
                            <?= htmlspecialchars($itinerary->getDescription(), ENT_QUOTES, 'UTF-8') ?>
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
    .itinerary-card {
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: var(--nammu-radius-md);
        padding: 1.15rem;
        background: <?= $highlight ?>;
        color: <?= $textColor ?>;
        position: relative;
        overflow: hidden;
    }
    .itinerary-card::after {
        content: '';
        display: table;
        clear: both;
    }
    .itinerary-thumb {
        display: block;
    }
    .itinerary-thumb img {
        width: 100%;
        display: block;
        object-fit: cover;
        border-radius: var(--nammu-radius-md);
    }
    .itinerary-card__body {
        display: block;
    }
    .itinerary-card__body > *:not(:last-child) {
        margin-bottom: 0.6rem;
    }
    .itinerary-card.style-media-right .itinerary-thumb {
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
    .itinerary-card.style-media-right .itinerary-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        border-radius: inherit;
    }
    .itinerary-card.style-circle-right .itinerary-thumb {
        border-radius: 50%;
        shape-outside: circle();
        -webkit-shape-outside: circle();
    }
    @media (max-width: 720px) {
        .itinerary-card.style-media-right {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .itinerary-card.style-media-right .itinerary-card__body {
            width: 100%;
            text-align: left;
        }
        .itinerary-card.style-media-right .itinerary-thumb {
            float: none;
            width: min(240px, 75vw);
            margin: 0 auto 0.85rem auto;
            shape-outside: none;
            -webkit-shape-outside: none;
        }
        .itinerary-card.style-media-right .itinerary-thumb img {
            border-radius: var(--nammu-radius-md);
        }
        .itinerary-card.style-circle-right .itinerary-thumb {
            width: min(200px, 60vw);
        }
        .itinerary-grid.columns-1 .itinerary-card.style-media-right {
            display: block;
        }
        .itinerary-grid.columns-1 .itinerary-card.style-media-right .itinerary-thumb {
            float: none;
            width: 100%;
            margin: 0 0 0.85rem 0;
            shape-outside: none;
            -webkit-shape-outside: none;
            border-radius: var(--nammu-radius-lg);
        }
        .itinerary-grid.columns-1 .itinerary-card.style-media-right .itinerary-thumb img {
            border-radius: inherit;
        }
        .itinerary-grid.columns-1 .itinerary-card.style-circle-right .itinerary-thumb {
            width: 100%;
            border-radius: 50%;
        }
        .itinerary-grid.columns-1 .itinerary-card.style-media-right {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .itinerary-grid.columns-1 .itinerary-card.style-media-right .itinerary-thumb {
            float: none;
            width: min(240px, 70%);
            margin: 0 auto 0.85rem auto;
            shape-outside: none;
            -webkit-shape-outside: none;
        }
        .itinerary-grid.columns-1 .itinerary-card.style-circle-right .itinerary-thumb {
            width: min(200px, 55%);
        }
    }
    .itinerary-card.style-full .itinerary-thumb {
        width: 100%;
        overflow: hidden;
    }
    .itinerary-card.style-full .itinerary-thumb img {
        border-radius: var(--nammu-radius-md);
    }
    .itinerary-card.style-full.full-mode-natural .itinerary-thumb img {
        height: auto;
        width: 100%;
    }
    .itinerary-card.style-full.full-mode-crop .itinerary-thumb {
        aspect-ratio: 16 / 9;
    }
    .itinerary-card.style-full.full-mode-crop .itinerary-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .itinerary-card.style-full .itinerary-card__body {
        margin-top: 0.9rem;
    }
    .itinerary-card h2 {
        margin: 0;
        font-size: 1.6rem;
        color: <?= $headingSecondaryColor ?>;
    }
    .itinerary-card h2 a {
        color: inherit;
    }
    .itinerary-card__meta {
        margin: 0;
        display: inline-flex;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
        letter-spacing: 0.012em;
        background: <?= htmlspecialchars($accentBackground, ENT_QUOTES, 'UTF-8') ?>;
        border: 1px solid <?= htmlspecialchars($accentBorder, ENT_QUOTES, 'UTF-8') ?>;
        color: <?= $accentColor ?>;
        border-radius: var(--nammu-radius-sm);
    }
    .itinerary-card__description {
        margin: 0;
    }
    .itinerary-grid.blocks-flat {
        gap: 2.25rem 1.75rem;
    }
    .itinerary-grid.blocks-flat .itinerary-card {
        background: transparent;
        border: none;
        padding: 0;
    }
    @media (min-width: 721px) {
        .itinerary-grid.blocks-flat:not(.columns-1) .itinerary-card.style-media-right .itinerary-thumb {
            margin-left: clamp(1rem, 3vw, 1.5rem);
        }
    }
    .itinerary-grid.blocks-flat .itinerary-card.style-full .itinerary-card__body {
        margin-top: 1.15rem;
    }
</style>
