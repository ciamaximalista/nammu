<?php
$threadItem = is_array($threadItem ?? null) ? $threadItem : [];
$themeFonts = is_array($theme['fonts'] ?? null) ? $theme['fonts'] : [];
$noteFont = htmlspecialchars((string) ($themeFonts['note'] ?? ($themeFonts['body'] ?? 'Roboto')), ENT_QUOTES, 'UTF-8');
$threadPayload = is_array($threadPayload ?? null) ? $threadPayload : [];
$threadSummary = is_array($threadPayload['summary'] ?? null) ? $threadPayload['summary'] : ['likes' => 0, 'shares' => 0, 'replies' => 0];
$threadDetails = is_array($threadPayload['details'] ?? null) ? $threadPayload['details'] : ['likes' => [], 'shares' => [], 'replies' => []];
$threadReplies = is_array($threadPayload['replies'] ?? null) ? $threadPayload['replies'] : [];
$threadReplyActors = [];
foreach ($threadReplies as $threadReplyEntry) {
    if (!is_array($threadReplyEntry)) {
        continue;
    }
    $replyActorId = trim((string) ($threadReplyEntry['actor_id'] ?? ''));
    $replyActorUsername = trim((string) ($threadReplyEntry['actor_username'] ?? ''));
    $replyActorName = trim((string) (($threadReplyEntry['actor_name'] ?? '') ?: $replyActorUsername ?: $replyActorId));
    $replyActorUrl = trim((string) (($threadReplyEntry['url'] ?? '') ?: $replyActorId));
    $replyActorIcon = trim((string) ($threadReplyEntry['actor_icon'] ?? ''));
    $replyActorKey = $replyActorId !== ''
        ? $replyActorId
        : strtolower($replyActorUsername . '|' . $replyActorName . '|' . $replyActorUrl);
    if ($replyActorKey === '' || isset($threadReplyActors[$replyActorKey])) {
        continue;
    }
    $threadReplyActors[$replyActorKey] = [
        'id' => $replyActorId,
        'name' => $replyActorName,
        'icon' => $replyActorIcon,
        'url' => $replyActorUrl,
    ];
}
if (empty($threadReplyActors) && !empty($threadDetails['replies']) && is_array($threadDetails['replies'])) {
    foreach ($threadDetails['replies'] as $replyActor) {
        if (!is_array($replyActor)) {
            continue;
        }
        $replyActorId = trim((string) ($replyActor['id'] ?? ''));
        $replyActorName = trim((string) (($replyActor['name'] ?? '') ?: $replyActorId));
        $replyActorUrl = trim((string) (($replyActor['url'] ?? '') ?: $replyActorId));
        $replyActorIcon = trim((string) ($replyActor['icon'] ?? ''));
        $replyActorKey = $replyActorId !== ''
            ? $replyActorId
            : strtolower($replyActorName . '|' . $replyActorUrl);
        if ($replyActorKey === '' || isset($threadReplyActors[$replyActorKey])) {
            continue;
        }
        $threadReplyActors[$replyActorKey] = [
            'id' => $replyActorId,
            'name' => $replyActorName,
            'icon' => $replyActorIcon,
            'url' => $replyActorUrl,
        ];
    }
}
$threadReplyActors = array_values($threadReplyActors);
$threadOriginalUrl = trim((string) ($threadPayload['original_url'] ?? ''));
$threadUrl = trim((string) ($threadPayload['thread_url'] ?? ''));
$threadItemUrl = trim((string) ($threadItem['url'] ?? ''));
$threadIsNote = strcasecmp((string) ($threadItem['type'] ?? ''), 'Note') === 0;
$threadOriginalUrlIsOwnItem = $threadOriginalUrl !== '' && $threadItemUrl !== '' && rtrim($threadOriginalUrl, '/') === rtrim($threadItemUrl, '/');
$threadIsBoostNote = $threadIsNote && $threadOriginalUrl !== '' && !$threadOriginalUrlIsOwnItem;
$threadIsOwnNote = $threadIsNote && !$threadIsBoostNote;
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
$threadOwnNoteFontStyle = $threadIsOwnNote
    ? ' style="font-family:&quot;' . $noteFont . '&quot;, system-ui, -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, sans-serif !important;"'
    : '';
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
$filterFediverseReplyText = static function (string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') {
        return '';
    }
    $mentionBlock = '@[A-Za-z0-9._-]+(?:@[A-Za-z0-9.-]+)?';
    $text = preg_replace('/^\s*CC:\s*(?:' . $mentionBlock . '(?:[\s,;:]+|$))+/iu', '', $text) ?? $text;
    $text = preg_replace('/^\s*(?:' . $mentionBlock . '(?:[\s,;:]+|$))+(?=\S)/u', '', $text) ?? $text;
    $text = preg_replace('/(?:\n|\A)\s*CC:\s*(?:' . $mentionBlock . '(?:[\s,;:]+|$))+\s*(?=\n|\z)/iu', "\n", $text) ?? $text;
    $text = preg_replace('/(?:\n|\A)\s*(?:' . $mentionBlock . '(?:[\s,;:]+|$))+\s*(?=\n|\z)/u', "\n", $text) ?? $text;
    $text = preg_replace('/\s+CC:\s*(?:' . $mentionBlock . '(?:[\s,;:]+|$))+\s*$/iu', '', $text) ?? $text;
    $text = preg_replace('/\s+(?:' . $mentionBlock . '(?:[\s,;:]+|$))+\s*$/u', '', $text) ?? $text;
    $text = trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    return $text;
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
.fediverse-public-reply__header-metrics { display: inline-flex; align-items: center; gap: .55rem; margin-left: auto; }
.fediverse-public-reply__header-metric { display: inline-flex; align-items: center; gap: .22rem; color: rgba(0,0,0,.62); font-size: .9rem; line-height: 1; white-space: nowrap; }
.fediverse-public-reply__header-metric svg { width: 1rem; height: 1rem; display: block; }
.fediverse-public-status--note-own,
.fediverse-public-status--note-own .fediverse-public-status__text,
.fediverse-public-status--note-own .fediverse-public-status__text p,
.fediverse-public-status--note-own .fediverse-public-status__header,
.fediverse-public-status--note-own .fediverse-public-status__footer,
.fediverse-public-status--note-own .fediverse-public-status__metrics,
.fediverse-public-status--note-own a,
.fediverse-public-status--note-own span,
.fediverse-public-status--note-own strong,
.fediverse-public-status--note-own div {
    font-family: "<?= $noteFont ?>", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
}
.fediverse-public-status__text--note { font-size: 1.22rem; line-height: 1.82; }
.fediverse-public-status--note-own .fediverse-public-status__header strong { font-size: 1.08rem; }
.fediverse-public-status--note-own .fediverse-public-status__handle { font-size: 1rem; }
.fediverse-public-status--note-own .fediverse-public-status__metrics span,
.fediverse-public-status--note-own .fediverse-public-status__metric-group {
    font-size: 1rem;
}
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

    <article class="fediverse-public-status<?= $threadIsOwnNote ? ' fediverse-public-status--note-own' : '' ?>"<?= $threadOwnNoteFontStyle ?>>
        <div class="fediverse-public-status__top">
            <div class="fediverse-public-status__avatar">
                <?php if ($fediverseLocalAvatar !== ''): ?>
                    <img src="<?= htmlspecialchars($fediverseLocalAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                <?php else: ?>
                    <?= htmlspecialchars(mb_substr($fediverseLocalName !== '' ? $fediverseLocalName : 'B', 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </div>
            <div class="fediverse-public-status__main">
                <div class="fediverse-public-status__header"<?= $threadOwnNoteFontStyle ?>>
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
                    <div class="fediverse-public-status__text<?= !$threadIsBoostNote ? ' fediverse-public-status__text--note' : '' ?>"<?= $threadOwnNoteFontStyle ?>><?= nl2br(htmlspecialchars($threadContent, ENT_QUOTES, 'UTF-8')) ?></div>
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
                    <div class="fediverse-public-status__text<?= !$threadIsBoostNote ? ' fediverse-public-status__text--note' : '' ?>"<?= $threadOwnNoteFontStyle ?>><?= nl2br(htmlspecialchars($threadContent, ENT_QUOTES, 'UTF-8')) ?></div>
                <?php endif; ?>

                <div class="fediverse-public-status__footer"<?= $threadOwnNoteFontStyle ?>>
                    <?php if ($threadOriginalUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($threadOriginalUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir contenido original</a>
                    <?php endif; ?>
                    <?php if ($threadUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($threadUrl, ENT_QUOTES, 'UTF-8') ?>">Enlace permanente del hilo</a>
                    <?php endif; ?>
                </div>

                <div class="fediverse-public-status__metrics"<?= $threadOwnNoteFontStyle ?>>
                    <div class="fediverse-public-status__metric-group">
                        <span><?= (int) ($threadSummary['replies'] ?? 0) ?> respuesta<?= ((int) ($threadSummary['replies'] ?? 0) === 1) ? '' : 's' ?></span>
                        <?php if (!empty($threadReplyActors)): ?>
                            <span class="fediverse-public-status__actor-icons">
                                <?php foreach ($threadReplyActors as $replyActor): ?>
                                    <?php $replyActorUrl = trim((string) (($replyActor['url'] ?? '') ?: '#')); ?>
                                    <a href="<?= htmlspecialchars($replyActorUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars((string) ($replyActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if (!empty($replyActor['icon'])): ?>
                                            <img src="<?= htmlspecialchars((string) $replyActor['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($replyActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                        <?php else: ?>
                                            <?= htmlspecialchars(mb_substr((string) (($replyActor['name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
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
                    $replySummary = is_array($reply['summary'] ?? null) ? $reply['summary'] : ['likes' => 0, 'shares' => 0];
                    $replyText = $filterFediverseReplyText((string) ($reply['reply_text'] ?? ''));
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
                                    <?php if ((int) ($replySummary['likes'] ?? 0) > 0 || (int) ($replySummary['shares'] ?? 0) > 0): ?>
                                        <span class="fediverse-public-reply__header-metrics">
                                            <?php if ((int) ($replySummary['likes'] ?? 0) > 0): ?>
                                                <span class="fediverse-public-reply__header-metric" title="<?= (int) ($replySummary['likes'] ?? 0) ?> favorito<?= ((int) ($replySummary['likes'] ?? 0) === 1) ? '' : 's' ?>">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="m12 21-1.45-1.32C5.4 15.02 2 11.93 2 8.14 2 5.05 4.42 3 7.2 3c1.57 0 3.08.74 4.05 1.91A5.26 5.26 0 0 1 15.3 3C18.08 3 20.5 5.05 20.5 8.14c0 3.79-3.4 6.88-8.55 11.54Z"/></svg>
                                                    <span><?= (int) ($replySummary['likes'] ?? 0) ?></span>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ((int) ($replySummary['shares'] ?? 0) > 0): ?>
                                                <span class="fediverse-public-reply__header-metric" title="<?= (int) ($replySummary['shares'] ?? 0) ?> impulso<?= ((int) ($replySummary['shares'] ?? 0) === 1) ? '' : 's' ?>">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17 8V5l5 5-5 5v-3h-4a7 7 0 0 0-7 7v1H4v-1a9 9 0 0 1 9-9h4Z"/><path fill="currentColor" d="M7 4h6v2H7a3 3 0 0 0-3 3v4H2V9a5 5 0 0 1 5-5Z"/></svg>
                                                    <span><?= (int) ($replySummary['shares'] ?? 0) ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="fediverse-public-reply__text"><?= nl2br(htmlspecialchars($replyText, ENT_QUOTES, 'UTF-8')) ?></div>
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
