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
$threadPublished = trim((string) ($threadItem['published'] ?? ''));
$fediverseLocalName = trim((string) ($fediverseLocalName ?? 'Blog'));
$fediverseLocalHandle = trim((string) ($fediverseLocalHandle ?? ''));
$fediverseLocalAvatar = trim((string) ($fediverseLocalAvatar ?? ''));
?>
<style>
.fediverse-public-page { max-width: 860px; margin: 0 auto; }
.fediverse-public-page__eyebrow { margin: 0 0 1rem; font-size: .85rem; letter-spacing: .08em; text-transform: uppercase; opacity: .7; }
.fediverse-public-status,
.fediverse-public-section { background: #f7f7f7; border: 1px solid rgba(0,0,0,.08); border-radius: 18px; padding: 1.25rem; margin-bottom: 1rem; }
.fediverse-public-status__top,
.fediverse-public-reply__top,
.fediverse-public-actor { display: flex; gap: .9rem; align-items: flex-start; }
.fediverse-public-status__avatar,
.fediverse-public-reply__avatar,
.fediverse-public-actor__avatar { width: 52px; height: 52px; border-radius: 999px; overflow: hidden; background: #dfe7ef; flex: 0 0 52px; display: flex; align-items: center; justify-content: center; font-weight: 700; }
.fediverse-public-status__avatar img,
.fediverse-public-reply__avatar img,
.fediverse-public-actor__avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.fediverse-public-status__main,
.fediverse-public-reply__main { flex: 1 1 auto; min-width: 0; }
.fediverse-public-status__header,
.fediverse-public-reply__header { display: flex; flex-wrap: wrap; gap: .5rem .75rem; align-items: baseline; margin-bottom: .75rem; }
.fediverse-public-status__header strong,
.fediverse-public-reply__header strong { font-size: 1rem; }
.fediverse-public-status__handle,
.fediverse-public-reply__meta { color: rgba(0,0,0,.6); font-size: .92rem; }
.fediverse-public-status__title { font-size: 1.35rem; font-weight: 700; margin: 0 0 .85rem; line-height: 1.15; }
.fediverse-public-status__text,
.fediverse-public-reply__text { font-size: 1rem; line-height: 1.6; }
.fediverse-public-status__card { display: block; margin-top: .9rem; border-radius: 16px; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.08); color: inherit; text-decoration: none; }
.fediverse-public-status__card img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; }
.fediverse-public-status__card-body { padding: 1rem; }
.fediverse-public-status__card-title { display: block; font-weight: 700; margin-bottom: .45rem; }
.fediverse-public-status__card-description { display: block; color: rgba(0,0,0,.7); line-height: 1.5; }
.fediverse-public-status__footer { margin-top: .9rem; display: flex; flex-wrap: wrap; gap: .6rem 1rem; font-size: .95rem; }
.fediverse-public-status__metrics { margin-top: 1rem; display: flex; flex-wrap: wrap; gap: .65rem; }
.fediverse-public-status__metrics span { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 999px; padding: .38rem .7rem; font-size: .92rem; }
.fediverse-public-section h2 { margin: 0 0 1rem; font-size: 1.15rem; }
.fediverse-public-thread { display: grid; gap: .9rem; }
.fediverse-public-reply { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 16px; padding: 1rem; }
.fediverse-public-actors { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .8rem; }
.fediverse-public-actor { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 16px; padding: .9rem; color: inherit; text-decoration: none; }
.fediverse-public-actor__body { min-width: 0; }
.fediverse-public-actor__body strong { display: block; }
.fediverse-public-actor__body span { display: block; color: rgba(0,0,0,.6); font-size: .92rem; line-height: 1.45; }
@media (max-width: 640px) {
  .fediverse-public-status,
  .fediverse-public-section { padding: 1rem; border-radius: 14px; }
  .fediverse-public-status__avatar,
  .fediverse-public-reply__avatar,
  .fediverse-public-actor__avatar { width: 44px; height: 44px; flex-basis: 44px; }
}
</style>

<div class="fediverse-public-page">
    <p class="fediverse-public-page__eyebrow">Hilo del Fediverso</p>

    <article class="fediverse-public-status">
        <div class="fediverse-public-status__top">
            <div class="fediverse-public-status__avatar">
                <?php if ($fediverseLocalAvatar !== ''): ?>
                    <img src="<?= htmlspecialchars($fediverseLocalAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                <?php else: ?>
                    <?= htmlspecialchars(mb_substr($fediverseLocalName !== '' ? $fediverseLocalName : 'B', 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </div>
            <div class="fediverse-public-status__main">
                <div class="fediverse-public-status__header">
                    <strong><?= htmlspecialchars($fediverseLocalName, ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if ($fediverseLocalHandle !== ''): ?>
                        <span class="fediverse-public-status__handle"><?= htmlspecialchars($fediverseLocalHandle, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if ($threadPublished !== ''): ?>
                        <span class="fediverse-public-status__handle"><?= htmlspecialchars($threadPublished, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($threadTitle !== '' && !$threadIsNote): ?>
                    <div class="fediverse-public-status__title"><?= htmlspecialchars($threadTitle, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php if ($threadIsNote): ?>
                    <div class="fediverse-public-status__text"><?= nl2br(htmlspecialchars($threadContent, ENT_QUOTES, 'UTF-8')) ?></div>
                <?php elseif ($threadOriginalUrl !== ''): ?>
                    <a class="fediverse-public-status__card" href="<?= htmlspecialchars($threadOriginalUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <?php if (!empty($threadItem['image'])): ?>
                            <img src="<?= htmlspecialchars((string) $threadItem['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($threadTitle, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                        <?php endif; ?>
                        <div class="fediverse-public-status__card-body">
                            <span class="fediverse-public-status__card-title"><?= htmlspecialchars((string) ($threadTitle !== '' ? $threadTitle : $threadOriginalUrl), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($threadSummaryText !== ''): ?>
                                <span class="fediverse-public-status__card-description"><?= htmlspecialchars($threadSummaryText, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php elseif ($threadContent !== ''): ?>
                                <span class="fediverse-public-status__card-description"><?= htmlspecialchars($threadContent, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php else: ?>
                    <div class="fediverse-public-status__text"><?= nl2br(htmlspecialchars($threadContent, ENT_QUOTES, 'UTF-8')) ?></div>
                <?php endif; ?>

                <div class="fediverse-public-status__footer">
                    <?php if ($threadOriginalUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($threadOriginalUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir contenido original</a>
                    <?php endif; ?>
                    <?php if ($threadUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($threadUrl, ENT_QUOTES, 'UTF-8') ?>">Enlace permanente del hilo</a>
                    <?php endif; ?>
                </div>

                <div class="fediverse-public-status__metrics">
                    <span><?= (int) ($threadSummary['replies'] ?? 0) ?> respuesta<?= ((int) ($threadSummary['replies'] ?? 0) === 1) ? '' : 's' ?></span>
                    <span><?= (int) ($threadSummary['shares'] ?? 0) ?> impulso<?= ((int) ($threadSummary['shares'] ?? 0) === 1) ? '' : 's' ?></span>
                    <span><?= (int) ($threadSummary['likes'] ?? 0) ?> favorito<?= ((int) ($threadSummary['likes'] ?? 0) === 1) ? '' : 's' ?></span>
                </div>
            </div>
        </div>
    </article>

    <?php if (!empty($threadReplies)): ?>
        <section class="fediverse-public-section">
            <h2>Respuestas</h2>
            <div class="fediverse-public-thread">
                <?php foreach ($threadReplies as $reply): ?>
                    <?php
                    $replyIsRemote = ($reply['source'] ?? '') === 'incoming-remote';
                    $replyName = trim((string) ($replyIsRemote ? (($reply['actor_name'] ?? '') ?: 'Actor remoto') : $fediverseLocalName));
                    $replyHandle = trim((string) ($reply['actor_handle'] ?? ($replyIsRemote ? ($reply['actor_id'] ?? '') : $fediverseLocalHandle)));
                    ?>
                    <article class="fediverse-public-reply">
                        <div class="fediverse-public-reply__top">
                            <div class="fediverse-public-reply__avatar">
                                <?php if (!empty($reply['actor_icon'])): ?>
                                    <img src="<?= htmlspecialchars((string) $reply['actor_icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <?= htmlspecialchars(mb_substr($replyName !== '' ? $replyName : 'A', 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                            <div class="fediverse-public-reply__main">
                                <div class="fediverse-public-reply__header">
                                    <strong><?= htmlspecialchars($replyName, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if ($replyHandle !== ''): ?>
                                        <span class="fediverse-public-reply__meta"><?= htmlspecialchars($replyHandle, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($reply['published'])): ?>
                                        <span class="fediverse-public-reply__meta"><?= htmlspecialchars((string) $reply['published'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="fediverse-public-reply__text"><?= nl2br(htmlspecialchars((string) ($reply['reply_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($threadDetails['shares'])): ?>
        <section class="fediverse-public-section">
            <h2>Impulsos recibidos</h2>
            <div class="fediverse-public-actors">
                <?php foreach ($threadDetails['shares'] as $shareActor): ?>
                    <?php $shareActorUrl = trim((string) (($shareActor['url'] ?? '') ?: '#')); ?>
                    <a class="fediverse-public-actor" href="<?= htmlspecialchars($shareActorUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <div class="fediverse-public-actor__avatar">
                            <?php if (!empty($shareActor['icon'])): ?>
                                <img src="<?= htmlspecialchars((string) $shareActor['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <?= htmlspecialchars(mb_substr((string) (($shareActor['name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </div>
                        <div class="fediverse-public-actor__body">
                            <strong><?= htmlspecialchars((string) ($shareActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if (!empty($shareActor['published'])): ?>
                                <span><?= htmlspecialchars((string) $shareActor['published'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
