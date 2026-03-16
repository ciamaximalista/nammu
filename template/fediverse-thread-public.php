<?php
$threadItem = is_array($threadItem ?? null) ? $threadItem : [];
$threadPayload = is_array($threadPayload ?? null) ? $threadPayload : [];
$threadSummary = is_array($threadPayload['summary'] ?? null) ? $threadPayload['summary'] : ['likes' => 0, 'shares' => 0, 'replies' => 0];
$threadDetails = is_array($threadPayload['details'] ?? null) ? $threadPayload['details'] : ['likes' => [], 'shares' => [], 'replies' => []];
$threadReplies = is_array($threadPayload['replies'] ?? null) ? $threadPayload['replies'] : [];
$threadOriginalUrl = trim((string) ($threadPayload['original_url'] ?? ''));
$threadUrl = trim((string) ($threadPayload['thread_url'] ?? ''));
$threadIsNote = strcasecmp((string) ($threadItem['type'] ?? ''), 'Note') === 0;
$threadTitle = trim((string) ($threadItem['title'] ?? ''));
$threadContent = trim((string) ($threadItem['content'] ?? ''));
$threadSummaryText = trim((string) ($threadItem['summary'] ?? ''));
$fediverseLocalName = trim((string) ($fediverseLocalName ?? 'Blog'));
$fediverseLocalHandle = trim((string) ($fediverseLocalHandle ?? ''));
$fediverseLocalAvatar = trim((string) ($fediverseLocalAvatar ?? ''));
?>
<article class="fediverse-public-thread">
    <header class="fediverse-public-thread__hero">
        <p class="fediverse-public-thread__eyebrow">Fediverso</p>
        <div class="fediverse-public-thread__identity">
            <?php if ($fediverseLocalAvatar !== ''): ?>
                <img src="<?= htmlspecialchars($fediverseLocalAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
            <?php endif; ?>
            <div>
                <strong><?= htmlspecialchars($fediverseLocalName, ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if ($fediverseLocalHandle !== ''): ?>
                    <div><?= htmlspecialchars($fediverseLocalHandle, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($threadTitle !== '' && !$threadIsNote): ?>
            <h1><?= htmlspecialchars($threadTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <?php else: ?>
            <h1>Hilo federado</h1>
        <?php endif; ?>
        <?php if (!empty($threadItem['published'])): ?>
            <time datetime="<?= htmlspecialchars((string) $threadItem['published'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $threadItem['published'], ENT_QUOTES, 'UTF-8') ?></time>
        <?php endif; ?>
    </header>

    <section class="fediverse-public-thread__root">
        <?php if ($threadIsNote): ?>
            <div class="fediverse-public-thread__text"><?= nl2br(htmlspecialchars($threadContent, ENT_QUOTES, 'UTF-8')) ?></div>
        <?php else: ?>
            <?php if (!empty($threadItem['image'])): ?>
                <figure class="fediverse-public-thread__media">
                    <img src="<?= htmlspecialchars((string) $threadItem['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($threadTitle, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                </figure>
            <?php endif; ?>
            <?php if ($threadSummaryText !== ''): ?>
                <p class="fediverse-public-thread__summary"><?= htmlspecialchars($threadSummaryText, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($threadContent !== ''): ?>
                <p class="fediverse-public-thread__summary"><?= htmlspecialchars($threadContent, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if ($threadOriginalUrl !== ''): ?>
                <p><a href="<?= htmlspecialchars($threadOriginalUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir contenido original</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="fediverse-public-thread__metrics">
        <span><?= (int) ($threadSummary['replies'] ?? 0) ?> respuesta<?= ((int) ($threadSummary['replies'] ?? 0) === 1) ? '' : 's' ?></span>
        <span><?= (int) ($threadSummary['shares'] ?? 0) ?> impulso<?= ((int) ($threadSummary['shares'] ?? 0) === 1) ? '' : 's' ?></span>
        <span><?= (int) ($threadSummary['likes'] ?? 0) ?> favorito<?= ((int) ($threadSummary['likes'] ?? 0) === 1) ? '' : 's' ?></span>
    </section>

    <?php if (!empty($threadReplies)): ?>
        <section class="fediverse-public-thread__section">
            <h2>Respuestas</h2>
            <div class="fediverse-thread">
                <?php foreach ($threadReplies as $reply): ?>
                    <article class="fediverse-thread__reply">
                        <div class="fediverse-thread__avatar">
                            <?php if (!empty($reply['actor_icon'])): ?>
                                <img src="<?= htmlspecialchars((string) $reply['actor_icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <div class="fediverse-thread__avatar-fallback"><?= htmlspecialchars(mb_substr((string) (($reply['actor_name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="fediverse-thread__body">
                            <div class="fediverse-thread__header">
                                <strong><?= htmlspecialchars((string) (($reply['source'] ?? '') === 'incoming-remote' ? (($reply['actor_name'] ?? '') ?: 'Actor remoto') : $fediverseLocalName), ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if (!empty($reply['actor_handle'])): ?>
                                    <span><?= htmlspecialchars((string) $reply['actor_handle'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <?php if (!empty($reply['published'])): ?>
                                    <time datetime="<?= htmlspecialchars((string) $reply['published'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $reply['published'], ENT_QUOTES, 'UTF-8') ?></time>
                                <?php endif; ?>
                            </div>
                            <div class="fediverse-thread__content"><?= nl2br(htmlspecialchars((string) ($reply['reply_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($threadDetails['shares'])): ?>
        <section class="fediverse-public-thread__section">
            <h2>Impulsos recibidos</h2>
            <div class="fediverse-public-thread__actors">
                <?php foreach ($threadDetails['shares'] as $shareActor): ?>
                    <a class="fediverse-public-thread__actor" href="<?= htmlspecialchars((string) (($shareActor['url'] ?? '') ?: '#'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <?php if (!empty($shareActor['icon'])): ?>
                            <img src="<?= htmlspecialchars((string) $shareActor['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span><?= htmlspecialchars(mb_substr((string) (($shareActor['name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <strong><?= htmlspecialchars((string) ($shareActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($threadUrl !== ''): ?>
        <footer class="fediverse-public-thread__footer">
            <a href="<?= htmlspecialchars($threadUrl, ENT_QUOTES, 'UTF-8') ?>">Enlace permanente del hilo federado</a>
        </footer>
    <?php endif; ?>
</article>
