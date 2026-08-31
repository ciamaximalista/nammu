<?php
$colors = is_array($theme['colors'] ?? null) ? $theme['colors'] : [];
$h1ColorRaw = trim((string) ($colors['h1'] ?? '#1b8eed'));
$fediverseThreadUrl = trim((string) ($fediverseThreadUrl ?? ''));
$fediverseThreadMeta = is_array($fediverseThreadMeta ?? null) ? $fediverseThreadMeta : [];
$fediverseThreadSummary = is_array($fediverseThreadMeta['summary'] ?? null) ? $fediverseThreadMeta['summary'] : ['likes' => 0, 'shares' => 0, 'replies' => 0];
$fediverseThreadDetails = is_array($fediverseThreadMeta['details'] ?? null) ? $fediverseThreadMeta['details'] : ['likes' => [], 'shares' => [], 'replies' => []];
$fediverseThreadReplies = is_array($fediverseThreadReplies ?? null) ? $fediverseThreadReplies : [];
$fediverseIcon = $fediverseIcon ?? (function_exists('nammu_footer_icon_svgs') ? (string) (nammu_footer_icon_svgs()['fediverse'] ?? '') : '');
$fediverseButtonTextColor = $fediverseButtonTextColor ?? htmlspecialchars($h1ColorRaw, ENT_QUOTES, 'UTF-8');
$fediverseMetricLinkColor = $h1ColorRaw !== '' ? $h1ColorRaw : '#8fbbe8';
if (preg_match('/^#([a-f0-9]{6})$/i', $h1ColorRaw, $m) === 1) {
    $hex = $m[1];
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $metricMix = static function (int $channel): int {
        return (int) round(($channel * 0.22) + (255 * 0.78));
    };
    $fediverseMetricLinkColor = sprintf('#%02x%02x%02x', $metricMix($r), $metricMix($g), $metricMix($b));
}
$fediverseMetricLinkColorEsc = $fediverseMetricLinkColorEsc ?? htmlspecialchars($fediverseMetricLinkColor, ENT_QUOTES, 'UTF-8');
$singleFediverseConfig = $singleFediverseConfig ?? (function_exists('nammu_load_config') ? nammu_load_config() : []);
$singleFediverseConfig = is_array($singleFediverseConfig) ? $singleFediverseConfig : [];
$singleValidFediverseAvatarUrl = $singleValidFediverseAvatarUrl ?? static function (string $avatarUrl, string $actorReference = '') use ($singleFediverseConfig): string {
    $avatarUrl = trim($avatarUrl);
    $baseUrl = function_exists('nammu_fediverse_base_url') ? nammu_fediverse_base_url($singleFediverseConfig) : '';
    if ($avatarUrl !== '' && function_exists('nammu_actuality_is_local_image_url') && nammu_actuality_is_local_image_url($avatarUrl, $baseUrl)) {
        $imagePath = trim((string) (parse_url($avatarUrl, PHP_URL_PATH) ?? ''));
        if ($imagePath === '' && !preg_match('#^https?://#i', $avatarUrl)) {
            $imagePath = '/' . ltrim($avatarUrl, '/');
        }
        $localImagePath = $imagePath !== '' ? dirname(__DIR__) . $imagePath : '';
        $avatarUrl = ($localImagePath !== '' && is_file($localImagePath)) ? $avatarUrl : '';
    }
    if ($actorReference !== '' && function_exists('nammu_fediverse_cached_actor_avatar_for_reference')) {
        $knownAvatarUrl = trim((string) nammu_fediverse_cached_actor_avatar_for_reference($actorReference, $singleFediverseConfig));
        if ($knownAvatarUrl !== '') {
            return $knownAvatarUrl;
        }
    }
    return $avatarUrl;
};
$formatSingleFediverseDateTime = $formatSingleFediverseDateTime ?? static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    try {
        $date = new DateTimeImmutable($value);
        return $date->format('d/m/Y H:i');
    } catch (Throwable $exception) {
        return $value;
    }
};
$renderSingleFediverseText = $renderSingleFediverseText ?? static function (string $text): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escaped = preg_replace_callback('#https?://[^\s<]+#iu', static function (array $matches): string {
        $url = rtrim((string) ($matches[0] ?? ''), '.,;:)');
        $suffix = substr((string) ($matches[0] ?? ''), strlen($url));
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" rel="noopener">' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>' . htmlspecialchars($suffix, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }, $escaped) ?? $escaped;
    $paragraphs = preg_split('/\n{2,}/u', $escaped) ?: [$escaped];
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim((string) $paragraph);
        if ($paragraph === '') {
            continue;
        }
        $html .= '<p>' . nl2br($paragraph) . '</p>';
    }
    return $html;
};
$renderSingleFediverseAttachments = $renderSingleFediverseAttachments ?? static function (array $attachments): string {
    $html = '';
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $url = trim((string) (($attachment['url'] ?? '') ?: ($attachment['href'] ?? '')));
        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            continue;
        }
        $mime = strtolower(trim((string) (($attachment['mediaType'] ?? '') ?: ($attachment['type'] ?? ''))));
        $name = trim((string) (($attachment['name'] ?? '') ?: 'Adjunto'));
        if (str_starts_with($mime, 'video/')) {
            $html .= '<div class="fediverse-public-reply__media"><video controls preload="metadata"><source src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" type="' . htmlspecialchars($mime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></video></div>';
        } elseif (str_starts_with($mime, 'audio/')) {
            $html .= '<div class="fediverse-public-reply__media"><audio controls preload="none"><source src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" type="' . htmlspecialchars($mime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></audio></div>';
        } elseif (str_starts_with($mime, 'image/') || preg_match('#\.(?:jpe?g|png|gif|webp|avif)(?:\?|$)#i', $url) === 1) {
            $html .= '<div class="fediverse-public-reply__media"><img src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" loading="lazy"></div>';
        }
    }
    return $html;
};
$isItineraryTemplate = !empty($isItineraryTemplate);
$isPodcastTemplate = !empty($isPodcastTemplate);
$fediverseRepliesCount = max(0, (int) ($fediverseThreadSummary['replies'] ?? 0));
$fediverseLikesCount = max(0, (int) ($fediverseThreadSummary['likes'] ?? 0));
$fediverseSharesCount = max(0, (int) ($fediverseThreadSummary['shares'] ?? 0));
$hasFediverseMetrics = ($fediverseRepliesCount + $fediverseLikesCount + $fediverseSharesCount) > 0;
$fediverseEmptyLabel = $isItineraryTemplate
    ? 'Este itinerario en el Fediverso'
    : ($isPodcastTemplate ? 'Este episodio en el Fediverso' : 'Esta entrada en el Fediverso');
$fediverseButtonLabel = $isItineraryTemplate
    ? 'Comentarios y reacciones a este itinerario en el Fediverso'
    : ($isPodcastTemplate
        ? 'Comentarios y reacciones a este episodio en el Fediverso'
        : 'Comentarios y reacciones a esta entrada en el Fediverso');
?>
<?php if ($fediverseThreadUrl !== ''): ?>
    <?php if ($hasFediverseMetrics): ?>
        <div class="post-related-heading fediverse-object-heading">
            <a class="fediverse-object-heading-link" href="<?= htmlspecialchars($fediverseThreadUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($fediverseButtonLabel, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($fediverseButtonLabel, ENT_QUOTES, 'UTF-8') ?>">
                <span class="fediverse-object-cta-label"><?= htmlspecialchars($fediverseButtonLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($fediverseIcon !== ''): ?>
                    <span class="fediverse-object-cta-icon" aria-hidden="true"><?= $fediverseIcon ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="fediverse-public-status__metrics fediverse-public-status__metrics--single">
            <?php foreach ([['replies', $fediverseRepliesCount, 'respuesta'], ['likes', $fediverseLikesCount, 'favorito'], ['shares', $fediverseSharesCount, 'impulso']] as $metric): ?>
                <?php [$metricKey, $metricCount, $metricLabel] = $metric; ?>
                <?php if ($metricCount > 0): ?>
                    <div class="fediverse-public-status__metric-group">
                        <a class="fediverse-public-status__metric-label" href="<?= htmlspecialchars($fediverseThreadUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $metricCount ?> <?= $metricLabel ?><?= ($metricCount === 1) ? '' : 's' ?></a>
                        <?php if (!empty($fediverseThreadDetails[$metricKey])): ?>
                            <span class="fediverse-public-status__actor-icons">
                                <?php foreach ((array) $fediverseThreadDetails[$metricKey] as $actor): ?>
                                    <?php
                                    $actorUrl = trim((string) (($actor['url'] ?? '') ?: $fediverseThreadUrl));
                                    $actorIcon = $singleValidFediverseAvatarUrl(trim((string) ($actor['icon'] ?? '')), trim((string) (($actor['id'] ?? '') ?: $actorUrl)));
                                    ?>
                                    <a href="<?= htmlspecialchars($actorUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars((string) ($actor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if ($actorIcon !== ''): ?>
                                            <img src="<?= htmlspecialchars($actorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($actor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                        <?php else: ?>
                                            <?= htmlspecialchars(mb_substr((string) (($actor['name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($fediverseThreadReplies)): ?>
            <section class="fediverse-public-section fediverse-public-section--single-replies" aria-label="Respuestas en el Fediverso">
                <h2>Respuestas</h2>
                <div class="fediverse-public-thread">
                    <?php foreach ($fediverseThreadReplies as $reply): ?>
                        <?php
                        if (!is_array($reply)) {
                            continue;
                        }
                        $replyActorId = trim((string) ($reply['actor_id'] ?? ''));
                        $replyActorUsername = trim((string) ($reply['actor_username'] ?? ''));
                        $replyName = trim((string) ($reply['actor_name'] ?? ''));
                        if ($replyName === '') {
                            $replyName = $replyActorUsername !== '' ? $replyActorUsername : ($replyActorId !== '' ? $replyActorId : 'Autor');
                        }
                        $replyHandle = trim((string) ($reply['actor_handle'] ?? ''));
                        if ($replyHandle === '') {
                            $actorHost = trim((string) (parse_url($replyActorId, PHP_URL_HOST) ?? ''));
                            if ($replyActorUsername !== '') {
                                $replyHandle = '@' . ltrim($replyActorUsername, '@') . ($actorHost !== '' ? '@' . $actorHost : '');
                            } elseif ($replyActorId !== '') {
                                $replyHandle = $replyActorId;
                            }
                        }
                        $replyAvatar = $singleValidFediverseAvatarUrl(trim((string) ($reply['actor_icon'] ?? '')), $replyActorId !== '' ? $replyActorId : trim((string) ($reply['url'] ?? '')));
                        $replySummary = is_array($reply['summary'] ?? null) ? $reply['summary'] : ['likes' => 0, 'shares' => 0];
                        $replyText = trim((string) ($reply['reply_text'] ?? ''));
                        $replyCard = is_array($reply['link_card'] ?? null) ? $reply['link_card'] : null;
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
                                        <?php $replyPublishedLabel = $formatSingleFediverseDateTime((string) ($reply['published'] ?? '')); ?>
                                        <?php if ($replyPublishedLabel !== ''): ?>
                                            <span class="fediverse-public-reply__meta"><?= htmlspecialchars($replyPublishedLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php if ((int) ($replySummary['likes'] ?? 0) > 0 || (int) ($replySummary['shares'] ?? 0) > 0): ?>
                                            <span class="fediverse-public-reply__header-metrics">
                                                <?php if ((int) ($replySummary['likes'] ?? 0) > 0): ?>
                                                    <span class="fediverse-public-reply__header-metric" title="<?= (int) ($replySummary['likes'] ?? 0) ?> favorito<?= ((int) ($replySummary['likes'] ?? 0) === 1) ? '' : 's' ?>">
                                                        <span aria-hidden="true">♥</span><span><?= (int) ($replySummary['likes'] ?? 0) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ((int) ($replySummary['shares'] ?? 0) > 0): ?>
                                                    <span class="fediverse-public-reply__header-metric" title="<?= (int) ($replySummary['shares'] ?? 0) ?> impulso<?= ((int) ($replySummary['shares'] ?? 0) === 1) ? '' : 's' ?>">
                                                        <span aria-hidden="true">📣</span><span><?= (int) ($replySummary['shares'] ?? 0) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($replyText !== ''): ?>
                                        <div class="fediverse-public-reply__text"><?= $renderSingleFediverseText($replyText) ?></div>
                                    <?php endif; ?>
                                    <?php if (is_array($replyCard) && trim((string) ($replyCard['url'] ?? '')) !== ''): ?>
                                        <a class="fediverse-public-reply__card" href="<?= htmlspecialchars((string) $replyCard['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?php if (!empty($replyCard['image'])): ?>
                                                <img src="<?= htmlspecialchars((string) $replyCard['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) (($replyCard['title'] ?? '') ?: 'Vista previa del enlace'), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                            <?php endif; ?>
                                            <div class="fediverse-public-reply__card-body">
                                                <span class="fediverse-public-reply__card-title"><?= htmlspecialchars((string) (($replyCard['title'] ?? '') ?: ($replyCard['url'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (!empty($replyCard['description'])): ?>
                                                    <div class="fediverse-public-reply__card-description"><?= $renderSingleFediverseText((string) $replyCard['description']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($reply['image'])): ?>
                                        <div class="fediverse-public-reply__media">
                                            <img src="<?= htmlspecialchars((string) $reply['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Imagen adjunta" loading="lazy">
                                        </div>
                                    <?php endif; ?>
                                    <?= $renderSingleFediverseAttachments((array) ($reply['attachments'] ?? [])) ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <a class="fediverse-object-empty-btn" href="<?= htmlspecialchars($fediverseThreadUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($fediverseEmptyLabel, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($fediverseEmptyLabel, ENT_QUOTES, 'UTF-8') ?>">
            <span><?= htmlspecialchars($fediverseEmptyLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($fediverseIcon !== ''): ?>
                <span class="fediverse-object-cta-icon" aria-hidden="true"><?= $fediverseIcon ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
<?php endif; ?>
