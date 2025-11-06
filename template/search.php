<?php
/**
 * @var string $query
 * @var array<int, array<string, mixed>> $results
 * @var bool $didSearch
 * @var array<string, mixed> $summary
 * @var string $typeFilter
 * @var array<string, string> $filters
 * @var array<int, string> $tips
 */
$colors = $theme['colors'] ?? [];
$highlight = htmlspecialchars($colors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$textColor = htmlspecialchars($colors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
$accentColor = htmlspecialchars($colors['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8');
$brandColor = htmlspecialchars($colors['brand'] ?? '#1b1b1b', ENT_QUOTES, 'UTF-8');
$h1Color = htmlspecialchars($colors['h1'] ?? '#1b8eed', ENT_QUOTES, 'UTF-8');
$codeBg = htmlspecialchars($colors['code_background'] ?? '#000000', ENT_QUOTES, 'UTF-8');
$codeText = htmlspecialchars($colors['code_text'] ?? '#90ee90', ENT_QUOTES, 'UTF-8');
$searchActionBase = $baseUrl ?? '/';
$searchAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/buscar.php';
$hasResults = !empty($results);
$queryEscaped = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
?>
<section class="search-hero">
    <div class="search-hero-inner">
        <h1>Buscar en <?= htmlspecialchars($theme['blog'] !== '' ? $theme['blog'] : $siteTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <p>Explora entradas, páginas y recursos usando filtros avanzados.</p>
        <form method="get" action="<?= htmlspecialchars($searchAction, ENT_QUOTES, 'UTF-8') ?>" class="search-form">
            <div class="search-form-group">
                <input type="text" name="q" value="<?= $queryEscaped ?>" placeholder="Palabra clave, frase exacta o filtros como title:bosque" autocomplete="off">
                <select name="tipo">
                    <?php foreach ($filters as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $typeFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Buscar</button>
                <a class="search-categories-link" href="<?= htmlspecialchars(rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/categorias', ENT_QUOTES, 'UTF-8') ?>" aria-label="Índice de categorías">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                        <line x1="8" y1="9" x2="16" y2="9" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                        <line x1="8" y1="13" x2="16" y2="13" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    </svg>
                </a>
            </div>
            <div class="search-hints">
                Frases exactas entre comillas (“bosque mediterráneo”), excluye con <code>-urbano</code>, filtra por campo (<code>title:plantación</code>) o tipo (<code>tipo:página</code>).
            </div>
        </form>
    </div>
</section>

<?php if ($didSearch): ?>
    <section class="search-meta-bar">
        <?php if ($summary['total'] > 0): ?>
            <div class="search-meta">
                <strong><?= $summary['total'] ?></strong> resultados en <?= $summary['took'] ?> ms · Filtro: <?= htmlspecialchars($filters[$typeFilter] ?? 'Todo', ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php elseif ($query === ''): ?>
            <div class="search-meta muted">Escribe al menos 2 caracteres para buscar.</div>
        <?php else: ?>
            <div class="search-meta muted">No encontramos nada para “<?= $queryEscaped ?>”. Revisa la ortografía o prueba con menos términos.</div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($hasResults): ?>
    <section class="search-results">
        <?php foreach ($results as $item): ?>
            <article class="search-result-card">
                <header>
                    <span class="result-pill"><?= htmlspecialchars($item['type_label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($item['category'] !== ''): ?>
                        <span class="result-pill faded"><?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if ($item['date'] !== ''): ?>
                        <time datetime="<?= htmlspecialchars($item['date'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['date'], ENT_QUOTES, 'UTF-8') ?></time>
                    <?php endif; ?>
                </header>
                <h2><a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><?= $item['title'] ?></a></h2>
                <?php if ($item['description'] !== ''): ?>
                    <p class="result-description"><?= $item['description'] ?></p>
                <?php endif; ?>
                <?php if ($item['snippet'] !== ''): ?>
                    <p class="result-snippet"><?= $item['snippet'] ?></p>
                <?php endif; ?>
                <footer>
                    <a class="result-link" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>">Abrir contenido</a>
                    <span class="result-score">Relevancia <?= number_format((float) $item['score'], 1) ?></span>
                </footer>
            </article>
        <?php endforeach; ?>
    </section>
<?php elseif (!$didSearch): ?>
    <section class="search-empty">
        <p>Empieza con una palabra clave o prueba filtros como <code>category:educación</code>, <code>title:"cambio climático"</code> o <code>tipo:página</code>.</p>
    </section>
<?php else: ?>
    <section class="search-empty">
        <p>No encontramos coincidencias. Asegúrate de que las palabras estén completas o intenta con sinónimos.</p>
    </section>
<?php endif; ?>

<?php if (!empty($tips)): ?>
    <section class="search-tips">
        <h3>Consejos rápidos</h3>
        <ul>
            <?php foreach ($tips as $tip): ?>
                <li><?= htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<style>
    .search-hero {
        margin-bottom: 2rem;
    }
    .search-hero-inner {
        background: <?= $highlight ?>;
        border-radius: var(--nammu-radius-lg);
        padding: 2rem;
        text-align: center;
        border: 1px solid rgba(0,0,0,0.05);
    }
    .search-hero-inner h1 {
        margin: 0 0 0.5rem 0;
        color: <?= $h1Color ?>;
    }
    .search-hero-inner p {
        margin: 0 0 1.5rem 0;
        color: <?= $textColor ?>;
    }
    .search-form-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
    }
    .search-form input[type="text"] {
        flex: 1 1 300px;
        padding: 0.85rem 1rem;
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: var(--nammu-radius-md);
        font-size: 1rem;
    }
    .search-form select {
        flex: 0 0 180px;
        padding: 0.75rem;
        border-radius: var(--nammu-radius-md);
        border: 1px solid rgba(0,0,0,0.1);
        font-size: 0.95rem;
        background: #fff;
    }
    .search-form button {
        flex: 0 0 auto;
        padding: 0.85rem 1.5rem;
        border: none;
        border-radius: var(--nammu-radius-md);
        background: <?= $accentColor ?>;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
    }
    .search-categories-link {
        flex: 0 0 auto;
        width: 48px;
        height: 48px;
        border-radius: var(--nammu-radius-md);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.05);
        text-decoration: none;
        transition: background 0.2s ease;
    }
    .search-categories-link:hover {
        background: rgba(0,0,0,0.12);
    }
    .search-hints {
        margin-top: 0.75rem;
        font-size: 0.9rem;
        color: <?= $textColor ?>;
    }
    .search-hints code,
    .search-empty code,
    .search-tips code {
        background: <?= $codeBg ?>;
        color: <?= $codeText ?>;
        padding: 0.15rem 0.35rem;
        border-radius: var(--nammu-radius-sm);
    }
    .search-meta-bar {
        margin-bottom: 1.5rem;
    }
    .search-meta {
        background: <?= $highlight ?>;
        padding: 0.75rem 1rem;
        border-radius: var(--nammu-radius-md);
        display: inline-flex;
        gap: 0.5rem;
        align-items: center;
    }
    .search-meta.muted {
        color: <?= $textColor ?>;
        opacity: 0.8;
    }
    .search-results {
        display: grid;
        gap: 1rem;
    }
    .search-result-card {
        border: 1px solid rgba(0,0,0,0.06);
        border-radius: var(--nammu-radius-md);
        padding: 1.25rem;
        background: #fff;
        box-shadow: 0 3px 10px rgba(0,0,0,0.04);
    }
    .search-result-card header {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    .result-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.65rem;
        border-radius: var(--nammu-radius-pill);
        background: <?= $accentColor ?>;
        color: #fff;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .result-pill.faded {
        background: rgba(0,0,0,0.08);
        color: <?= $brandColor ?>;
    }
    .search-result-card h2 {
        margin: 0 0 0.35rem 0;
        font-size: 1.35rem;
        color: <?= $brandColor ?>;
    }
    .search-result-card h2 a {
        color: inherit;
    }
    .result-description {
        margin: 0 0 0.5rem 0;
        color: <?= $textColor ?>;
    }
    .result-snippet {
        margin: 0;
        color: <?= $textColor ?>;
        font-size: 0.95rem;
    }
    .result-snippet mark,
    .result-description mark,
    .search-result-card h2 mark {
        background: rgba(253, 224, 130, 0.8);
    }
    .search-result-card footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 0.75rem;
        font-size: 0.9rem;
    }
    .result-link {
        color: <?= $accentColor ?>;
        font-weight: 600;
    }
    .result-score {
        color: rgba(0,0,0,0.45);
    }
    .search-empty {
        padding: 1.5rem;
        border-radius: var(--nammu-radius-md);
        border: 1px dashed rgba(0,0,0,0.2);
        color: <?= $textColor ?>;
        margin-bottom: 2rem;
    }
    .search-empty code {
        background: rgba(0,0,0,0.05);
        padding: 0.2rem 0.4rem;
        border-radius: var(--nammu-radius-sm);
    }
    .search-tips {
        margin-top: 2.5rem;
        background: <?= $highlight ?>;
        padding: 1.5rem;
        border-radius: var(--nammu-radius-md);
    }
    .search-tips h3 {
        margin-top: 0;
    }
    .search-tips ul {
        margin: 0;
        padding-left: 1.2rem;
    }
    @media (max-width: 640px) {
        .search-form-group {
            flex-direction: column;
        }
        .search-form input,
        .search-form select,
        .search-form button,
        .search-categories-link {
            width: 100%;
        }
        .search-result-card footer {
            flex-direction: column;
            gap: 0.35rem;
            align-items: flex-start;
        }
    }
</style>
