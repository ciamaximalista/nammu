<?php
/**
 * @var string $pageTitle
 */
?>
<article class="not-found">
    <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
    <p>Lo sentimos, la página solicitada no se encuentra disponible.</p>
    <p><a href="./">Volver a la portada</a></p>
</article>

<style>
    .not-found {
        text-align: center;
        padding: 4rem 0;
    }
    .not-found h2 {
        font-size: 2rem;
        margin-bottom: 1rem;
    }
</style>

