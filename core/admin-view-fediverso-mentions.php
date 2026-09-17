<?php
/**
 * Nammu — panel de administración. Fediverso: pestaña Menciones (webmentions recibidas).
 * Lo incluye core/admin-page-fediverso.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
?>
<div class="card">
    <div class="card-body">
        <h3 class="h5 mb-3">Menciones en otros blogs</h3>
        <?php if (empty($fediverseWebmentions)): ?>
            <p class="text-muted mb-0">Todavía no hay Webmentions verificadas guardadas.</p>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($fediverseWebmentions as $mention): ?>
                    <?php
                    $mentionSignature = trim((string) ($mention['signature'] ?? ''));
                    $mentionBlogName = trim((string) (($mention['blog_name'] ?? '') ?: ((string) (parse_url((string) ($mention['source'] ?? ''), PHP_URL_HOST) ?? ''))));
                    $mentionBlogIcon = $fediverseValidAvatarUrl(trim((string) ($mention['blog_icon'] ?? '')));
                    $mentionSource = trim((string) ($mention['source'] ?? ''));
                    $mentionTarget = trim((string) ($mention['target'] ?? ''));
                    $mentionTitle = trim((string) (($mention['source_title'] ?? '') ?: $mentionSource));
                    $mentionExcerpt = trim((string) ($mention['source_excerpt'] ?? ''));
                    ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div class="fediverse-notification" style="flex:1 1 32rem;">
                                <div class="fediverse-notification__avatar">
                                    <?php if ($mentionBlogIcon !== ''): ?>
                                        <img src="<?= htmlspecialchars($mentionBlogIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <div class="fediverse-notification__avatar-fallback"><?= htmlspecialchars(mb_substr($mentionBlogName !== '' ? $mentionBlogName : 'B', 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="fediverse-notification__body">
                                    <strong><?= htmlspecialchars($mentionBlogName !== '' ? $mentionBlogName : 'Blog externo', ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if ($mentionSource !== ''): ?>
                                        <div class="small mt-1"><a href="<?= htmlspecialchars($mentionSource, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($mentionTitle, ENT_QUOTES, 'UTF-8') ?></a></div>
                                    <?php endif; ?>
                                    <?php if ($mentionTarget !== ''): ?>
                                        <div class="small text-muted mt-1">Menciona: <a href="<?= htmlspecialchars($mentionTarget, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">publicación local</a></div>
                                    <?php endif; ?>
                                    <?php if ($mentionExcerpt !== ''): ?>
                                        <div class="small text-muted mt-2"><?= htmlspecialchars($mentionExcerpt, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="post" onsubmit="return confirm('¿Eliminar esta mención guardada?');">
                                <input type="hidden" name="fediverse_tab" value="mentions">
                                <input type="hidden" name="webmention_signature" value="<?= htmlspecialchars($mentionSignature, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" name="delete_webmention" class="btn btn-outline-danger btn-sm">Borrar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
