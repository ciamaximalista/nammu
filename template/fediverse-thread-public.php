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
$formatFediversePublicDateTime = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    try {
        $date = new DateTimeImmutable($value);
        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];
        $monthIndex = (int) $date->format('n');
        $monthName = $months[$monthIndex] ?? $date->format('m');
        return $date->format('j') . ' de ' . $monthName . ' de ' . $date->format('Y') . ', ' . $date->format('H:i');
    } catch (Throwable $exception) {
        return $value;
    }
};
$threadPublishedLabel = $formatFediversePublicDateTime($threadPublished);
$fediverseLocalName = trim((string) ($fediverseLocalName ?? 'Blog'));
$fediverseLocalHandle = trim((string) ($fediverseLocalHandle ?? ''));
$fediverseLocalAvatar = trim((string) ($fediverseLocalAvatar ?? ''));
$baseUrlValue = trim((string) ($baseUrl ?? ''));
$fediverseProfileUrl = '';
if ($fediverseLocalHandle !== '') {
    $profilePath = '/' . ltrim($fediverseLocalHandle, '/');
    $fediverseProfileUrl = ($baseUrlValue !== '' && $baseUrlValue !== '/')
        ? rtrim($baseUrlValue, '/') . $profilePath
        : $profilePath;
}
$renderFediversePublicText = static function (string $text, string $className = ''): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    $paragraphs = preg_split("/(?:\r?\n){2,}/", $text) ?: [];
    $classAttr = $className !== '' ? ' class="' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '"' : '';
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $html .= '<p' . $classAttr . '>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    return $html;
};
$threadImageAttachments = array_values(array_filter((array) ($threadItem['attachments'] ?? []), static function ($attachment): bool {
    if (!is_array($attachment)) {
        return false;
    }
    $type = strtolower(trim((string) ($attachment['type'] ?? '')));
    $mediaType = strtolower(trim((string) ($attachment['media_type'] ?? '')));
    return ($type === 'image' || str_starts_with($mediaType, 'image/')) && trim((string) ($attachment['url'] ?? '')) !== '';
}));
foreach (array_values(array_unique(array_filter(array_map('strval', is_array($threadItem['images'] ?? null) ? $threadItem['images'] : [])))) as $threadImageUrl) {
    $alreadyPresent = false;
    foreach ($threadImageAttachments as $existingThreadImageAttachment) {
        if (trim((string) ($existingThreadImageAttachment['url'] ?? '')) === $threadImageUrl) {
            $alreadyPresent = true;
            break;
        }
    }
    if (!$alreadyPresent) {
        $threadImageAttachments[] = ['url' => $threadImageUrl];
    }
}
if (empty($threadImageAttachments) && !empty($threadItem['image'])) {
    $threadImageAttachments[] = ['url' => (string) $threadItem['image']];
}
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
.fediverse-public-status__media,
.fediverse-public-reply__media { margin-top: .9rem; }
.fediverse-public-status__media-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; margin-top: .9rem; }
.fediverse-public-status__media img,
.fediverse-public-reply__media img { width: 100%; max-height: 720px; object-fit: cover; display: block; border-radius: 16px; background: #fff; border: 1px solid rgba(0,0,0,.08); }
.fediverse-public-status__media-grid img { width: 100%; height: 100%; max-height: 420px; object-fit: cover; display: block; border-radius: 16px; background: #fff; border: 1px solid rgba(0,0,0,.08); }
.fediverse-public-status__card { display: block; margin-top: .9rem; border-radius: 16px; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.08); color: inherit; text-decoration: none; }
.fediverse-public-status__card img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; }
.fediverse-public-status__card-body { padding: 1rem; }
.fediverse-public-status__card-title { display: block; font-weight: 700; margin-bottom: .45rem; }
.fediverse-public-status__card-description { display: block; color: rgba(0,0,0,.7); line-height: 1.5; }
.fediverse-public-status__card-description p { margin: 0 0 .7rem; }
.fediverse-public-status__card-description p:last-child { margin-bottom: 0; }
.fediverse-public-reply__card { display: block; margin-top: .9rem; border-radius: 16px; overflow: hidden; background: #f7f7f7; border: 1px solid rgba(0,0,0,.08); color: inherit; text-decoration: none; }
.fediverse-public-reply__card img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; }
.fediverse-public-reply__card-body { padding: .9rem 1rem; }
.fediverse-public-reply__card-title { display: block; font-weight: 700; margin-bottom: .35rem; }
.fediverse-public-reply__card-description { display: block; color: rgba(0,0,0,.7); line-height: 1.5; }
.fediverse-public-reply__card-description p { margin: 0 0 .6rem; }
.fediverse-public-reply__card-description p:last-child { margin-bottom: 0; }
.fediverse-public-status__footer { margin-top: .9rem; display: flex; flex-wrap: wrap; gap: .6rem 1rem; font-size: .95rem; }
.fediverse-public-status__metrics { margin-top: 1rem; display: flex; flex-wrap: wrap; gap: .65rem; }
.fediverse-public-status__metrics span { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 999px; padding: .38rem .7rem; font-size: .92rem; }
.fediverse-public-status__metric-group { display: inline-flex; align-items: center; gap: .5rem; background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 999px; padding: .3rem .45rem .3rem .7rem; font-size: .92rem; }
.fediverse-public-status__metric-group > span { background: transparent; border: 0; padding: 0; }
.fediverse-public-status__actor-icons { display: inline-flex; align-items: center; gap: .25rem; }
.fediverse-public-status__actor-icons a { width: 28px; height: 28px; border-radius: 999px; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; background: #dfe7ef; text-decoration: none; color: inherit; border: 1px solid rgba(0,0,0,.08); }
.fediverse-public-status__actor-icons img { width: 100%; height: 100%; object-fit: cover; display: block; }
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
    <p class="fediverse-public-page__eyebrow">
        Hilo del Fediverso
        <?php if ($fediverseProfileUrl !== '' && $fediverseLocalHandle !== ''): ?>
            · publicado por <a href="<?= htmlspecialchars($fediverseProfileUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fediverseLocalHandle, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
    </p>

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
                    <?php if ($threadPublishedLabel !== ''): ?>
                        <span class="fediverse-public-status__handle"><?= htmlspecialchars($threadPublishedLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($threadTitle !== '' && !$threadIsNote): ?>
                    <div class="fediverse-public-status__title"><?= htmlspecialchars($threadTitle, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php if ($threadIsNote): ?>
                    <div class="fediverse-public-status__text"><?= nl2br(htmlspecialchars($threadContent, ENT_QUOTES, 'UTF-8')) ?></div>
                    <?php if (!empty($threadImageAttachments)): ?>
                        <div class="<?= count($threadImageAttachments) > 1 ? 'fediverse-public-status__media-grid' : 'fediverse-public-status__media' ?>">
                            <?php foreach ($threadImageAttachments as $imageIndex => $imageAttachment): ?>
                                <img src="<?= htmlspecialchars((string) ($imageAttachment['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($threadTitle !== '' ? $threadTitle : ('Imagen adjunta ' . ($imageIndex + 1))), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php elseif ($threadOriginalUrl !== ''): ?>
                    <a class="fediverse-public-status__card" href="<?= htmlspecialchars($threadOriginalUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <?php if (!empty($threadItem['image'])): ?>
                            <img src="<?= htmlspecialchars((string) $threadItem['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($threadTitle, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                        <?php endif; ?>
                        <div class="fediverse-public-status__card-body">
                            <span class="fediverse-public-status__card-title"><?= htmlspecialchars((string) ($threadTitle !== '' ? $threadTitle : $threadOriginalUrl), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($threadSummaryText !== ''): ?>
                                <div class="fediverse-public-status__card-description"><?= $renderFediversePublicText($threadSummaryText) ?></div>
                            <?php elseif ($threadContent !== ''): ?>
                                <div class="fediverse-public-status__card-description"><?= $renderFediversePublicText($threadContent) ?></div>
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
                    <div class="fediverse-public-status__metric-group">
                        <span><?= (int) ($threadSummary['likes'] ?? 0) ?> favorito<?= ((int) ($threadSummary['likes'] ?? 0) === 1) ? '' : 's' ?></span>
                        <?php if (!empty($threadDetails['likes'])): ?>
                            <span class="fediverse-public-status__actor-icons">
                                <?php foreach ($threadDetails['likes'] as $likeActor): ?>
                                    <?php $likeActorUrl = trim((string) (($likeActor['url'] ?? '') ?: '#')); ?>
                                    <a href="<?= htmlspecialchars($likeActorUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars((string) ($likeActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if (!empty($likeActor['icon'])): ?>
                                            <img src="<?= htmlspecialchars((string) $likeActor['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($likeActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                        <?php else: ?>
                                            <?= htmlspecialchars(mb_substr((string) (($likeActor['name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="fediverse-public-status__metric-group">
                        <span><?= (int) ($threadSummary['shares'] ?? 0) ?> impulso<?= ((int) ($threadSummary['shares'] ?? 0) === 1) ? '' : 's' ?></span>
                        <?php if (!empty($threadDetails['shares'])): ?>
                            <span class="fediverse-public-status__actor-icons">
                                <?php foreach ($threadDetails['shares'] as $shareActor): ?>
                                    <?php $shareActorUrl = trim((string) (($shareActor['url'] ?? '') ?: '#')); ?>
                                    <a href="<?= htmlspecialchars($shareActorUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars((string) ($shareActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if (!empty($shareActor['icon'])): ?>
                                            <img src="<?= htmlspecialchars((string) $shareActor['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($shareActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                        <?php else: ?>
                                            <?= htmlspecialchars(mb_substr((string) (($shareActor['name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
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
                    $replyActorId = trim((string) ($reply['actor_id'] ?? ''));
                    $replyActorUsername = trim((string) ($reply['actor_username'] ?? ''));
                    $replyName = trim((string) ($reply['actor_name'] ?? ''));
                    if ($replyName === '') {
                        if ($replyActorUsername !== '') {
                            $replyName = $replyActorUsername;
                        } elseif ($replyActorId !== '') {
                            $replyName = $replyActorId;
                        } else {
                            $replyName = $fediverseLocalName;
                        }
                    }
                    $replyHandle = trim((string) ($reply['actor_handle'] ?? ''));
                    if ($replyHandle === '') {
                        if ($replyActorUsername !== '') {
                            $actorHost = trim((string) (parse_url($replyActorId, PHP_URL_HOST) ?? ''));
                            $replyHandle = '@' . ltrim($replyActorUsername, '@');
                            if ($actorHost !== '') {
                                $replyHandle .= '@' . $actorHost;
                            }
                        } elseif ($replyActorId !== '') {
                            $replyHandle = $replyActorId;
                        } else {
                            $replyHandle = $fediverseLocalHandle;
                        }
                    }
                    $replyAvatar = trim((string) ($reply['actor_icon'] ?? ''));
                    $replyCard = is_array($reply['link_card'] ?? null) ? $reply['link_card'] : null;
                    if ($replyActorId === '' && $replyAvatar === '') {
                        $replyAvatar = $fediverseLocalAvatar;
                    }
                    ?>
                    <article class="fediverse-public-reply">
                        <div class="fediverse-public-reply__top">
                            <div class="fediverse-public-reply__avatar">
                                <?php if ($replyAvatar !== ''): ?>
                                    <img src="<?= htmlspecialchars($replyAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
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
                                    <?php $replyPublishedLabel = $formatFediversePublicDateTime((string) ($reply['published'] ?? '')); ?>
                                    <?php if ($replyPublishedLabel !== ''): ?>
                                        <span class="fediverse-public-reply__meta"><?= htmlspecialchars($replyPublishedLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="fediverse-public-reply__text"><?= nl2br(htmlspecialchars((string) ($reply['reply_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                <?php if (is_array($replyCard) && trim((string) ($replyCard['url'] ?? '')) !== ''): ?>
                                    <a class="fediverse-public-reply__card" href="<?= htmlspecialchars((string) $replyCard['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                        <?php if (!empty($replyCard['image'])): ?>
                                            <img src="<?= htmlspecialchars((string) $replyCard['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) (($replyCard['title'] ?? '') ?: 'Vista previa del enlace'), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                        <?php endif; ?>
                                        <div class="fediverse-public-reply__card-body">
                                            <span class="fediverse-public-reply__card-title"><?= htmlspecialchars((string) (($replyCard['title'] ?? '') ?: ($replyCard['url'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($replyCard['description'])): ?>
                                                <div class="fediverse-public-reply__card-description"><?= $renderFediversePublicText((string) $replyCard['description']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($reply['image'])): ?>
                                    <div class="fediverse-public-reply__media">
                                        <img src="<?= htmlspecialchars((string) $reply['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Imagen adjunta" loading="lazy">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</div>
