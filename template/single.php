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
$siteAuthor = htmlspecialchars($theme['author'] !== '' ? $theme['author'] : ($siteTitle ?? ''), ENT_QUOTES, 'UTF-8');
$siteBlog = htmlspecialchars($theme['blog'] !== '' ? $theme['blog'] : ($siteDescription ?? ''), ENT_QUOTES, 'UTF-8');
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
$category = $post->getMetadata()['Category'] ?? '';
$rawDate = $post->getRawDate();
$formattedDate = nammu_format_date_es($rawDate);
$metaTextParts = [];
if ($formattedDate !== '') {
    $metaTextParts[] = 'Publicado el ' . htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8');
}
if ($category !== '') {
    $metaTextParts[] = 'en la sección ' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
}
$metaText = implode(' ', $metaTextParts);
?>
<article class="post">
    <div class="post-header">
        <div class="post-brand">
            <?php if ($siteAuthor !== ''): ?>
                <span class="post-brand-title"><?= $siteAuthor ?></span>
            <?php endif; ?>
            <?php if ($siteBlog !== ''): ?>
                <span class="post-brand-sub"><?= $siteBlog ?></span>
            <?php endif; ?>
        </div>
        <h1><?= htmlspecialchars($post->getTitle(), ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if ($metaText !== ''): ?>
            <div class="post-meta-band"><?= $metaText ?></div>
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
        <?= $htmlContent ?>
    </div>
</article>

<style>
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
        display: inline-block;
        margin: 0 auto;
        padding: 0.5rem 1rem;
        border-radius: var(--nammu-radius-pill);
        background: <?= $colorHighlight ?>;
        color: <?= $colorAccent ?>;
        font-size: 0.9rem;
    }
    .post-intro {
        max-width: min(760px, 100%);
        margin: 0 auto;
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
        margin: 0 auto;
        color: <?= $colorText ?>;
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
        overflow: auto;
        margin: 2rem 0;
        font-size: 0.95rem;
        line-height: 1.5;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04);
        font-family: "<?= $codeFont ?>", "Fira Code", "Source Code Pro", "Courier New", monospace;
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
        font-style: italic;
        color: <?= $colorText ?>;
    }
    .post-body blockquote p {
        margin: 0 0 0.9rem 0;
    }
    .post-body blockquote p:last-child {
        margin-bottom: 0;
    }
</style>
