<?php
/**
 * Nammu — panel de administración. Fediverso: pestaña Inicio (timeline federado, reacciones, hilos y respuestas).
 * Lo incluye core/admin-page-fediverso.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
?>
<div class="card fediverse-home-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h3 class="h5 mb-0">Timeline</h3>
            <div class="d-flex flex-wrap gap-2">
                <form method="post" class="mb-0">
                    <input type="hidden" name="fediverse_tab" value="home">
                    <button type="submit" name="refresh_fediverse_timeline" class="btn btn-outline-secondary btn-sm">Refrescar ahora</button>
                </form>
                <form method="post" class="mb-0">
                    <input type="hidden" name="fediverse_tab" value="home">
                    <button type="submit" name="refresh_fediverse_threads" class="btn btn-outline-secondary btn-sm">Actualizar hilos</button>
                </form>
                <form method="post" class="mb-0" onsubmit="return confirm('Esto vaciará y reconstruirá la timeline remota guardada. ¿Continuar?');">
                    <input type="hidden" name="fediverse_tab" value="home">
                    <button type="submit" name="rebuild_fediverse_timeline" class="btn btn-outline-secondary btn-sm">Reconstruir timeline</button>
                </form>
            </div>
        </div>
        <?php if (empty($fediverseTimelinePageEntries)): ?>
            <p class="text-muted mb-0">Aún no hay publicaciones remotas recibidas. Sigue actores en la pestaña de configuración y luego refresca.</p>
        <?php else: ?>
            <div class="fediverse-timeline">
                <?php foreach ($fediverseTimelinePageEntries as $timelineEntry): ?>
                    <?php if (($timelineEntry['kind'] ?? '') === 'local'): ?>
                    <?php $localItem = is_array($timelineEntry['item'] ?? null) ? $timelineEntry['item'] : []; ?>
                    <?php
                    $localId = trim((string) ($localItem['id'] ?? ''));
                    if ($localId === '') { continue; }
                    $localAnchor = 'local-' . substr(sha1($localId), 0, 12);
                    $localThreadPayload = is_array($fediverseHomeSnapshot['thread_payloads'][$localId] ?? null)
                        ? $fediverseHomeSnapshot['thread_payloads'][$localId]
                        : ['summary' => [], 'details' => [], 'replies' => []];
                    $localSummary = is_array($localThreadPayload['summary'] ?? null)
                        ? $localThreadPayload['summary']
                        : ($fediverseLocalReactionSummary[$localId] ?? ['likes' => 0, 'shares' => 0, 'replies' => 0]);
                    $localReactionDetails = is_array($localThreadPayload['details'] ?? null)
                        ? $localThreadPayload['details']
                        : ($fediverseLocalReactionDetails[$localId] ?? ['likes' => [], 'shares' => [], 'replies' => []]);
                    $threadReplies = [];
                    foreach ((array) ($localThreadPayload['replies'] ?? []) as $reply) {
                        if (!is_array($reply)) {
                            continue;
                        }
                        $replySource = (string) ($reply['source'] ?? 'local');
                        $replyActorId = trim((string) ($reply['actor_id'] ?? ''));
                        $replyActorHandle = $replySource === 'local'
                            ? $fediverseLocalHandle
                            : $fediverseActorHandleFor([
                                'actor_id' => $replyActorId,
                                'actor_username' => trim((string) ($reply['actor_username'] ?? '')),
                            ]);
                        $threadReplies[] = [
                            'id' => (string) ($reply['id'] ?? ''),
                            'url' => (string) ($reply['url'] ?? ''),
                            'target_url' => (string) ($reply['target_url'] ?? $localId),
                            'published' => (string) ($reply['published'] ?? ''),
                            'reply_text' => (string) ($reply['reply_text'] ?? ''),
                            'actor_id' => $replyActorId,
                            'actor_name' => (string) (($reply['actor_name'] ?? '') ?: ($replySource === 'local' ? $fediverseLocalName : 'Actor remoto')),
                            'actor_handle' => $replyActorHandle,
                            'actor_icon' => $fediverseReplyActorAvatar($reply, $replySource === 'local' ? $fediverseLocalAvatar : ''),
                            'source' => $replySource,
                        ];
                    }
                    usort($threadReplies, static function (array $a, array $b): int {
                        return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
                    });
                    $shareModalId = 'fediverse-share-modal-' . preg_replace('/[^a-z0-9_-]+/i', '-', $localAnchor);
                    $localIsNote = strcasecmp((string) ($localItem['type'] ?? ''), 'Note') === 0;
                    $localContent = trim((string) (($localItem['content'] ?? '') ?: ($localItem['summary'] ?? '')));
                    $localImages = array_values(array_filter(array_map('strval', is_array($localItem['images'] ?? null) ? $localItem['images'] : [])));
                    if (empty($localImages) && !empty($localItem['image'])) {
                        $localImages[] = (string) $localItem['image'];
                    }
                    ?>
                    <article class="fediverse-status fediverse-status--local" id="<?= htmlspecialchars($localAnchor, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="fediverse-status__avatar">
                            <?php if ($fediverseLocalAvatar !== ''): ?>
                                <img src="<?= htmlspecialchars($fediverseLocalAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <div class="fediverse-status__avatar-fallback"><?= htmlspecialchars(mb_substr((string) ($siteTitle ?? 'B'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="fediverse-status__body">
                            <div class="fediverse-status__header">
                                <div class="fediverse-status__identity">
                                    <strong><?= htmlspecialchars($fediverseLocalName, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="fediverse-status__handle"><?= htmlspecialchars($fediverseLocalHandle, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="fediverse-status__meta">
                                    <time datetime="<?= htmlspecialchars((string) ($localItem['published'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fediverseFormatDate((string) ($localItem['published'] ?? '')), ENT_QUOTES, 'UTF-8') ?></time>
                                </div>
                            </div>
                            <?php if (!empty($localItem['title']) && !$localIsNote): ?>
                                <div class="fediverse-status__title"><?= htmlspecialchars((string) ($localItem['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                            <?php if ($localContent !== ''): ?>
                                <div class="fediverse-status__content"><?= nl2br(htmlspecialchars($localContent, ENT_QUOTES, 'UTF-8')) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($localImages)): ?>
                                <div class="fediverse-status__attachments">
                                    <?php foreach ($localImages as $localImage): ?>
                                        <a class="fediverse-status__media" href="<?= htmlspecialchars($localImage, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <img src="<?= htmlspecialchars($localImage, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="fediverse-status__footer">
                                <a href="<?= htmlspecialchars((string) ($localItem['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir publicación emitida</a>
                                <?php if (!empty($localId)): ?>
                                    <?php if (function_exists('nammu_fediverse_thread_page_url')): ?>
                                        <span aria-hidden="true"> · </span>
                                        <a href="<?= htmlspecialchars((string) nammu_fediverse_thread_page_url((string) $localId, $fediverseConfig), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Enlace a la página pública</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <form method="post" class="d-inline-block ml-2" onsubmit="return confirm('¿Retirar esta publicación del Fediverso y enviar el borrado a otros nodos?');">
                                    <input type="hidden" name="fediverse_tab" value="home">
                                    <input type="hidden" name="fediverse_local_item_id" value="<?= htmlspecialchars($localId, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" name="fediverse_delete_local_item" class="btn btn-outline-danger btn-sm">Borrar</button>
                                </form>
                            </div>
                            <?php if (($localSummary['likes'] ?? 0) > 0 || ($localSummary['shares'] ?? 0) > 0 || ($localSummary['replies'] ?? 0) > 0): ?>
                                <div class="fediverse-status__history">
                                    <?php if (($localSummary['likes'] ?? 0) > 0): ?><span><?= (int) $localSummary['likes'] ?> favorito<?= ((int) $localSummary['likes'] === 1) ? '' : 's' ?></span><?php endif; ?>
                                    <?php if (($localSummary['shares'] ?? 0) > 0): ?>
                                        <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-toggle="modal" data-target="#<?= htmlspecialchars($shareModalId, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= (int) $localSummary['shares'] ?> impulso<?= ((int) $localSummary['shares'] === 1) ? '' : 's' ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (($localSummary['replies'] ?? 0) > 0): ?><span><?= (int) $localSummary['replies'] ?> respuesta<?= ((int) $localSummary['replies'] === 1) ? '' : 's' ?></span><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($localReactionDetails['shares'])): ?>
                                <div class="modal fade" id="<?= htmlspecialchars($shareModalId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" role="dialog" aria-labelledby="<?= htmlspecialchars($shareModalId, ENT_QUOTES, 'UTF-8') ?>-label" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="<?= htmlspecialchars($shareModalId, ENT_QUOTES, 'UTF-8') ?>-label">Impulsaron esta publicación</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="list-group list-group-flush">
                                                    <?php foreach ($localReactionDetails['shares'] as $shareActor): ?>
                                                        <?php
                                                        $shareActorUrl = trim((string) (($shareActor['url'] ?? '') ?: '#'));
                                                        $shareActorIcon = $fediverseReplyActorAvatar(['actor_id' => trim((string) ($shareActor['id'] ?? '')), 'actor_icon' => trim((string) ($shareActor['icon'] ?? ''))]);
                                                        ?>
                                                        <a class="list-group-item list-group-item-action d-flex align-items-center" href="<?= htmlspecialchars($shareActorUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                            <?php if ($shareActorIcon !== ''): ?>
                                                                <img src="<?= htmlspecialchars($shareActorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" style="width:40px;height:40px;border-radius:999px;object-fit:cover;margin-right:0.75rem;">
                                                            <?php else: ?>
                                                                <span class="d-inline-flex align-items-center justify-content-center mr-3" style="width:40px;height:40px;border-radius:999px;background:#e9ecef;font-weight:700;">
                                                                    <?= htmlspecialchars(mb_substr((string) (($shareActor['name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <span><?= htmlspecialchars((string) ($shareActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="fediverse-status__actions mt-3">
                                <details class="fediverse-inline-form">
                                    <summary class="fediverse-inline-form__summary-button">Responder</summary>
                                    <form method="post">
                                        <input type="hidden" name="fediverse_tab" value="home">
                                        <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars((string) $localId, ENT_QUOTES, 'UTF-8') ?>">
                                        <textarea name="fediverse_reply_text" class="form-control form-control-sm" rows="3" placeholder="Escribe tu respuesta"></textarea>
                                        <label class="fediverse-inline-check">
                                            <input type="checkbox" name="fediverse_reply_as_note" value="1">
                                            Publicar también como nota en Actualidad
                                        </label>
                                        <button type="submit" name="fediverse_reply_item" class="btn btn-primary btn-sm mt-2">Enviar respuesta</button>
                                    </form>
                                </details>
                            </div>
                            <?php if (!empty($threadReplies)): ?>
                                <div class="fediverse-thread">
                                    <?php foreach ($threadReplies as $reply): ?>
                                        <?php
                                        $replyObjectUrl = trim((string) (($reply['id'] ?? '') ?: ($reply['url'] ?? '')));
                                        $replyPublicUrl = trim((string) ($reply['url'] ?? ''));
                                        $replyActorId = trim((string) ($reply['actor_id'] ?? ''));
                                        $replyActorName = trim((string) ($reply['actor_name'] ?? ''));
                                        $replyActorIcon = $fediverseReplyActorAvatar($reply);
                                        $replyBoostImages = [];
                                        $replyActionState = function_exists('nammu_fediverse_action_state_for_item')
                                            ? nammu_fediverse_action_state_for_item($reply)
                                            : ['liked' => false, 'boosted' => false, 'replied' => false, 'shared' => false, 'boost_count' => 0, 'reply_count' => 0, 'share_count' => 0];
                                        ?>
                                        <div class="fediverse-thread__reply">
                                            <div class="fediverse-thread__avatar">
                                                <?php if ($replyActorIcon !== ''): ?>
                                                    <img src="<?= htmlspecialchars($replyActorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                                <?php else: ?>
                                                    <div class="fediverse-thread__avatar-fallback"><?= htmlspecialchars(mb_substr((string) (($reply['actor_name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fediverse-thread__body">
                                                <div class="fediverse-thread__header">
                                                    <strong><?= htmlspecialchars((string) ($reply['actor_name'] ?? $fediverseLocalName), ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <?php if (!empty($reply['actor_handle'])): ?>
                                                        <span><?= htmlspecialchars((string) $reply['actor_handle'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($reply['published'])): ?>
                                                        <time datetime="<?= htmlspecialchars((string) $reply['published'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fediverseFormatDate((string) $reply['published']), ENT_QUOTES, 'UTF-8') ?></time>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="fediverse-thread__content"><?= nl2br(htmlspecialchars((string) ($reply['reply_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                                <?php if ($replyObjectUrl !== ''): ?>
                                                    <div class="fediverse-status__actions mt-2">
                                                        <?php if ($replyActorId !== ''): ?>
                                                            <form method="post" class="mb-0">
                                                                <input type="hidden" name="fediverse_tab" value="home">
                                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($replyActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($replyObjectUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_public_url" value="<?= htmlspecialchars($replyPublicUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_item_id" value="<?= htmlspecialchars((string) ($reply['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <button type="submit" name="<?= !empty($replyActionState['liked']) ? 'fediverse_unlike_item' : 'fediverse_like_item' ?>" class="btn btn-outline-secondary btn-sm"><?= !empty($replyActionState['liked']) ? 'Quitar favorito' : 'Favorito' ?></button>
                                                            </form>
                                                            <form method="post" class="mb-0">
                                                                <input type="hidden" name="fediverse_tab" value="home">
                                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($replyActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($replyObjectUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_public_url" value="<?= htmlspecialchars($replyPublicUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_item_id" value="<?= htmlspecialchars((string) ($reply['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_object_title" value="">
                                                                <input type="hidden" name="fediverse_object_content" value="<?= htmlspecialchars((string) ($reply['reply_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_actor_name" value="<?= htmlspecialchars($replyActorName, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_actor_icon" value="<?= htmlspecialchars($replyActorIcon, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_actor_url" value="<?= htmlspecialchars($replyActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_object_image" value="">
                                                                <input type="hidden" name="fediverse_object_images" value="<?= htmlspecialchars(json_encode($replyBoostImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                                                                <button type="submit" name="<?= !empty($replyActionState['boosted']) ? 'fediverse_unboost_item' : 'fediverse_boost_item' ?>" class="btn btn-outline-secondary btn-sm"<?= !empty($replyActionState['boosted']) ? ' onclick="return confirm(\'¿Quitar este impulso y borrar la nota local asociada?\');"' : '' ?>><?= !empty($replyActionState['boosted']) ? 'Quitar impulso' : 'Impulsar' ?></button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <details class="fediverse-inline-form">
                                                            <summary class="fediverse-inline-form__summary-button">Responder</summary>
                                                            <form method="post">
                                                                <input type="hidden" name="fediverse_tab" value="home">
                                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($replyActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($replyObjectUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                                <textarea name="fediverse_reply_text" class="form-control form-control-sm" rows="3" placeholder="Escribe tu respuesta"></textarea>
                                                                <label class="fediverse-inline-check">
                                                                    <input type="checkbox" name="fediverse_reply_as_note" value="1">
                                                                    Publicar también como nota en Actualidad
                                                                </label>
                                                                <button type="submit" name="fediverse_reply_item" class="btn btn-primary btn-sm mt-2">Enviar respuesta</button>
                                                            </form>
                                                        </details>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (($reply['source'] ?? '') === 'local' && !empty($reply['id'])): ?>
                                                    <form method="post" class="mt-2" onsubmit="return confirm('¿Borrar esta respuesta del Fediverso?');">
                                                        <input type="hidden" name="fediverse_tab" value="home">
                                                        <input type="hidden" name="fediverse_reply_action_id" value="<?= htmlspecialchars((string) $reply['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" name="fediverse_delete_reply_item" class="btn btn-outline-danger btn-sm">Borrar</button>
                                                    </form>
                                                <?php elseif (in_array((string) ($reply['source'] ?? ''), ['incoming', 'incoming-remote'], true)): ?>
                                                    <form method="post" class="mt-2" onsubmit="return confirm('¿Ocultar esta respuesta en el blog y dejar de mostrarla públicamente?');">
                                                        <input type="hidden" name="fediverse_tab" value="home">
                                                        <input type="hidden" name="fediverse_incoming_reply_id" value="<?= htmlspecialchars((string) ($reply['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="fediverse_incoming_reply_url" value="<?= htmlspecialchars((string) ($reply['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="fediverse_incoming_reply_target" value="<?= htmlspecialchars((string) ($reply['target_url'] ?? $localId), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="fediverse_incoming_reply_published" value="<?= htmlspecialchars((string) ($reply['published'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="fediverse_incoming_reply_actor" value="<?= htmlspecialchars((string) ($reply['actor_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="fediverse_incoming_reply_text" value="<?= htmlspecialchars((string) ($reply['reply_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" name="fediverse_hide_incoming_reply" class="btn btn-outline-danger btn-sm">Eliminar respuesta</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php else: ?>
                    <?php $item = is_array($timelineEntry['item'] ?? null) ? $timelineEntry['item'] : []; ?>
                    <?php
                    $itemTypeForPage = strtolower(trim((string) ($item['type'] ?? '')));
                    $itemTargetForPage = trim((string) ($item['target_url'] ?? ''));
                    $itemObjectIdForPage = trim((string) ($item['object_id'] ?? ''));
                    $itemIsReplyForPage = $itemTypeForPage !== 'announce'
                        && $itemTargetForPage !== ''
                        && trim((string) ($item['content'] ?? '')) !== ''
                        && $itemObjectIdForPage !== ''
                        && $itemTargetForPage !== $itemObjectIdForPage;
                    $itemAttachedToRootForPage = false;
                    if ($itemIsReplyForPage) {
                        // Se oculta la copia suelta si cuelga (directamente o vía context/conversation) de un hilo del timeline.
                        foreach (array_unique(array_filter([$itemTargetForPage, trim((string) ($item['context'] ?? '')), trim((string) ($item['conversation'] ?? ''))])) as $itemThreadKeyForPage) {
                            if (isset($fediverseTimelineRootIdentifiers[$itemThreadKeyForPage])) {
                                $itemAttachedToRootForPage = true;
                                break;
                            }
                        }
                    }
                    if ($itemAttachedToRootForPage) { continue; }
                    ?>
                    <?php $itemObjectId = (string) (($item['object_id'] ?? '') ?: (($item['url'] ?? '') ?: ($item['id'] ?? ''))); ?>
                    <?php $itemTargetActorId = (string) (($item['target_actor_id'] ?? '') ?: ($item['actor_id'] ?? '')); ?>
                    <?php $itemActionState = function_exists('nammu_fediverse_action_state_for_item') ? nammu_fediverse_action_state_for_item($item) : ['liked' => false, 'boosted' => false, 'replied' => false, 'shared' => false, 'boost_count' => 0, 'reply_count' => 0, 'share_count' => 0]; ?>
                    <?php $itemReplies = function_exists('nammu_fediverse_replies_for_item') ? nammu_fediverse_replies_for_item($item) : []; ?>
                    <?php $remoteItemReplies = function_exists('nammu_fediverse_cached_remote_replies_snapshot_for_item') ? nammu_fediverse_cached_remote_replies_snapshot_for_item($item) : []; ?>
                    <?php
                    $itemTargetIdentifiers = function_exists('nammu_fediverse_item_identifiers_with_canonical')
                        ? nammu_fediverse_item_identifiers_with_canonical($item, $fediverseConfig)
                        : array_values(array_filter([
                            trim((string) ($item['object_id'] ?? '')),
                            trim((string) ($item['url'] ?? '')),
                            trim((string) ($item['id'] ?? '')),
                        ]));
                    $itemTargetIdentifiersExpanded = [];
                    foreach ($itemTargetIdentifiers as $itemTargetIdentifier) {
                        foreach ($fediverseEquivalentIdentifiers((string) $itemTargetIdentifier) as $itemTargetIdentifierVariant) {
                            $itemTargetIdentifiersExpanded[] = $itemTargetIdentifierVariant;
                        }
                    }
                    $itemTargetIdentifiers = array_values(array_unique(array_filter(array_map('strval', $itemTargetIdentifiersExpanded))));
                    ?>
                    <?php
                    $replyDedupKeys = [];
                    $registerReplyKey = static function (array $reply) use (&$replyDedupKeys): void {
                        $identityCandidates = array_filter([
                            trim((string) ($reply['id'] ?? '')),
                            trim((string) ($reply['url'] ?? '')),
                            trim((string) ($reply['note_id'] ?? '')),
                        ]);
                        foreach ($identityCandidates as $identityCandidate) {
                            $replyDedupKeys['id:' . $identityCandidate] = true;
                        }
                        $fallbackKey = strtolower(trim((string) ($reply['actor_id'] ?? ''))) . '|' .
                            trim((string) ($reply['published'] ?? '')) . '|' .
                            trim((string) ($reply['reply_text'] ?? ''));
                        if ($fallbackKey !== '||') {
                            $replyDedupKeys['fallback:' . $fallbackKey] = true;
                        }
                    };
                    foreach ($itemReplies as $existingReply) {
                        $registerReplyKey($existingReply);
                    }
                    $storedRemoteReplies = [];
                    foreach ($itemTargetIdentifiers as $itemReplyTargetIdentifier) {
                        foreach ((array) ($fediverseRemoteRepliesByTarget[$itemReplyTargetIdentifier] ?? []) as $storedRemoteReply) {
                            $storedRemoteReplies[] = $storedRemoteReply;
                        }
                    }
                    foreach ($storedRemoteReplies as $remoteItemReply) {
                        $remoteReplyFallbackKey = strtolower(trim((string) ($remoteItemReply['actor_id'] ?? ''))) . '|' .
                            trim((string) ($remoteItemReply['published'] ?? '')) . '|' .
                            trim((string) ($remoteItemReply['reply_text'] ?? ''));
                        $remoteReplyIdentifiers = array_filter([
                            'fallback:' . $remoteReplyFallbackKey,
                            'id:' . trim((string) ($remoteItemReply['id'] ?? '')),
                            'id:' . trim((string) ($remoteItemReply['url'] ?? '')),
                            'id:' . trim((string) ($remoteItemReply['note_id'] ?? '')),
                        ], static fn(string $value): bool => $value !== 'id:' && $value !== 'fallback:||');
                        if (empty(array_intersect_key(array_flip($remoteReplyIdentifiers), $replyDedupKeys))) {
                            $itemReplies[] = $remoteItemReply;
                            $registerReplyKey($remoteItemReply);
                        }
                    }
                    foreach ($remoteItemReplies as $remoteItemReply) {
                        $remoteReplyFallbackKey = strtolower(trim((string) ($remoteItemReply['actor_id'] ?? ''))) . '|' .
                            trim((string) ($remoteItemReply['published'] ?? '')) . '|' .
                            trim((string) ($remoteItemReply['reply_text'] ?? ''));
                        $remoteReplyIdentifiers = array_filter([
                            'fallback:' . $remoteReplyFallbackKey,
                            'id:' . trim((string) ($remoteItemReply['id'] ?? '')),
                            'id:' . trim((string) ($remoteItemReply['url'] ?? '')),
                            'id:' . trim((string) ($remoteItemReply['note_id'] ?? '')),
                        ], static fn(string $value): bool => $value !== 'id:' && $value !== 'fallback:||');
                        if (empty(array_intersect_key(array_flip($remoteReplyIdentifiers), $replyDedupKeys))) {
                            $itemReplies[] = $remoteItemReply;
                            $registerReplyKey($remoteItemReply);
                        }
                    }
                    usort($itemReplies, static function (array $a, array $b): int {
                        return strcmp((string) ($a['published'] ?? ''), (string) ($b['published'] ?? ''));
                    });
                    ?>
                    <?php
                    $remoteBoostMeta = ['count' => 0];
                    $remoteBoostActorMap = [];
                    foreach ($itemTargetIdentifiers as $itemTargetIdentifier) {
                        $candidateMeta = $fediverseRemoteBoostSummary[$itemTargetIdentifier] ?? null;
                        if (is_array($candidateMeta)) {
                            $remoteBoostMeta['count'] = max($remoteBoostMeta['count'], (int) ($candidateMeta['count'] ?? 0));
                        }
                        foreach ((array) ($fediverseRemoteBoostDetails[$itemTargetIdentifier] ?? []) as $boostActor) {
                            if (!is_array($boostActor)) {
                                continue;
                            }
                            $boostActorId = trim((string) (($boostActor['id'] ?? '') ?: sha1(json_encode($boostActor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))));
                            $remoteBoostActorMap[$boostActorId] = $boostActor;
                        }
                    }
                    $remoteBoostActors = array_values($remoteBoostActorMap);
                    $remoteReplyMeta = ['count' => 0];
                    foreach ($itemTargetIdentifiers as $itemTargetIdentifier) {
                        $candidateMeta = $fediverseRemoteReplySummary[$itemTargetIdentifier] ?? null;
                        if (is_array($candidateMeta)) {
                            $remoteReplyMeta['count'] = max($remoteReplyMeta['count'], (int) ($candidateMeta['count'] ?? 0));
                        }
                    }
                    if (!empty($remoteItemReplies)) {
                        $remoteReplyActors = [];
                        foreach ($remoteItemReplies as $remoteItemReply) {
                            $remoteReplyActorId = trim((string) ($remoteItemReply['actor_id'] ?? ''));
                            if ($remoteReplyActorId !== '') {
                                $remoteReplyActors[$remoteReplyActorId] = true;
                            }
                        }
                        $remoteReplyMeta['count'] = max((int) ($remoteReplyMeta['count'] ?? 0), count($remoteReplyActors) ?: count($remoteItemReplies));
                    }
                    $isRemoteAnnounce = strcasecmp((string) ($item['type'] ?? ''), 'announce') === 0;
                    $displayActorName = trim((string) ($item['actor_name'] ?? ''));
                    $displayActorIcon = trim((string) ($item['actor_icon'] ?? ''));
                    $displayActorId = trim((string) ($item['actor_id'] ?? ''));
                    $displayActorUsername = trim((string) ($item['actor_username'] ?? ''));
                    if ($isRemoteAnnounce && trim((string) ($item['target_actor_name'] ?? '')) !== '') {
                        $displayActorName = trim((string) $item['target_actor_name']);
                        $displayActorIcon = trim((string) (($item['target_actor_icon'] ?? '') ?: $displayActorIcon));
                        $displayActorId = trim((string) (($item['object_actor_id'] ?? '') ?: ($item['target_actor_id'] ?? '') ?: $displayActorId));
                        $displayActorUsername = trim((string) (($item['target_actor_username'] ?? '') ?: $displayActorUsername));
                        if ($displayActorUsername === '' && $displayActorId !== '') {
                            $displayActorPath = trim((string) (parse_url($displayActorId, PHP_URL_PATH) ?? ''));
                            if ($displayActorPath !== '') {
                                if (preg_match('#/@([^/@]+)(?:@[^/]+)?/?$#', $displayActorPath, $matches) === 1) {
                                    $displayActorUsername = trim((string) ($matches[1] ?? ''));
                                } elseif (preg_match('#/(?:users|accounts)/([^/]+)/?$#', $displayActorPath, $matches) === 1) {
                                    $displayActorUsername = trim((string) ($matches[1] ?? ''));
                                } else {
                                    $displayActorBasename = basename($displayActorPath);
                                    if ($displayActorBasename !== '' && $displayActorBasename !== 'actor') {
                                        $displayActorUsername = trim((string) $displayActorBasename);
                                    }
                                }
                            }
                        }
                    }
                    if ($isRemoteAnnounce && $displayActorId === trim((string) ($item['actor_id'] ?? '')) && function_exists('nammu_fediverse_timeline_item_for_identifier')) {
                        // Impulso cuyo objeto no se pudo leer: si la publicación impulsada está en el timeline, se toma su autor
                        // para el nombre y el avatar en vez de mostrar a quien impulsa como si fuera el autor.
                        foreach (array_unique(array_filter([trim((string) ($item['url'] ?? '')), trim((string) ($item['object_id'] ?? ''))])) as $announcedIdentifier) {
                            if ($announcedIdentifier === trim((string) ($item['id'] ?? ''))) {
                                continue;
                            }
                            $announcedTimelineItem = nammu_fediverse_timeline_item_for_identifier($announcedIdentifier);
                            $announcedActorId = is_array($announcedTimelineItem) ? trim((string) ($announcedTimelineItem['actor_id'] ?? '')) : '';
                            if ($announcedActorId === '' || $announcedActorId === $displayActorId) {
                                continue;
                            }
                            $displayActorId = $announcedActorId;
                            $displayActorName = trim((string) (($announcedTimelineItem['actor_name'] ?? '') ?: $displayActorName));
                            $displayActorIcon = trim((string) ($announcedTimelineItem['actor_icon'] ?? ''));
                            $displayActorUsername = trim((string) ($announcedTimelineItem['actor_username'] ?? ''));
                            break;
                        }
                    }
                    if ($displayActorIcon === '' && $displayActorId !== '' && isset($fediverseActorsById[$displayActorId])) {
                        $displayActorIcon = trim((string) ($fediverseActorsById[$displayActorId]['icon'] ?? ''));
                    }
                    $displayActorIcon = $fediverseValidAvatarUrl($displayActorIcon);
                    if ($displayActorIcon === '' && $displayActorId !== '' && isset($fediverseActorsById[$displayActorId])) {
                        $displayActorIcon = $fediverseValidAvatarUrl(trim((string) ($fediverseActorsById[$displayActorId]['avatar_remote_url'] ?? '')));
                    }
                    $displayActorHandle = $fediverseHandle([
                        'actor_id' => $displayActorId,
                        'actor_username' => $displayActorUsername,
                    ]);
                    $boostedByHandle = $fediverseHandle($item);
                    $announceActors = is_array($item['announce_actors'] ?? null) ? array_values($item['announce_actors']) : [];
                    $announceActorCount = count($announceActors);
                    ?>
                    <article class="fediverse-status">
                        <div class="fediverse-status__avatar">
                            <?php if ($displayActorIcon !== ''): ?>
                                <img src="<?= htmlspecialchars($displayActorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <div class="fediverse-status__avatar-fallback"><?= htmlspecialchars(mb_substr($displayActorName !== '' ? $displayActorName : 'A', 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="fediverse-status__body">
                            <div class="fediverse-status__header">
                                <div class="fediverse-status__identity">
                                    <strong><?= htmlspecialchars($displayActorName !== '' ? $displayActorName : 'Actor remoto', ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="fediverse-status__handle"><?= htmlspecialchars($displayActorHandle, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($isRemoteAnnounce): ?>
                                        <span class="fediverse-status__handle">
                                            <?php if ($announceActorCount > 1): ?>
                                                impulsado por varias cuentas
                                            <?php else: ?>
                                                impulsado por <?= htmlspecialchars($boostedByHandle, ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="fediverse-status__meta">
                                    <?php if (!empty($item['published'])): ?>
                                        <time datetime="<?= htmlspecialchars((string) $item['published'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fediverseFormatDate((string) $item['published']), ENT_QUOTES, 'UTF-8') ?></time>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($item['title']) && strcasecmp((string) ($item['type'] ?? ''), 'Note') !== 0): ?>
                                <div class="fediverse-status__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                            <?php
                            $statusHtml = $sanitizeFediverseHtml((string) ($item['content_html'] ?? ''));
                            $statusText = trim((string) ($item['content'] ?? ''));
                            ?>
                            <?php if ($statusHtml !== ''): ?>
                                <div class="fediverse-status__content fediverse-status__content--html"><?= $statusHtml ?></div>
                            <?php elseif ($statusText !== ''): ?>
                                <div class="fediverse-status__content"><?= nl2br(htmlspecialchars(strip_tags($statusText), ENT_QUOTES, 'UTF-8')) ?></div>
                            <?php endif; ?>
                            <?php $attachments = is_array($item['attachments'] ?? null) ? $item['attachments'] : []; ?>
                            <?php if (!empty($attachments)): ?>
                                <div class="fediverse-status__attachments">
                                    <?php foreach ($attachments as $attachment): ?>
                                        <?php $attachmentUrl = trim((string) ($attachment['url'] ?? '')); ?>
                                        <?php if ($attachmentUrl === '') { continue; } ?>
                                        <?php
                                        $attachmentType = strtolower(trim((string) ($attachment['type'] ?? '')));
                                        $attachmentMediaType = strtolower(trim((string) ($attachment['media_type'] ?? '')));
                                        $isImage = $attachmentType === 'image' || str_starts_with($attachmentMediaType, 'image/');
                                        $isVideo = $attachmentType === 'video' || str_starts_with($attachmentMediaType, 'video/');
                                        $isAudio = $attachmentType === 'audio' || str_starts_with($attachmentMediaType, 'audio/');
                                        $isLinkCard = $attachmentType === 'link' || $attachmentMediaType === 'text/html' || str_starts_with($attachmentMediaType, 'text/html');
                                        ?>
                                        <?php if ($isLinkCard): ?>
                                            <?php continue; ?>
                                        <?php elseif ($isImage): ?>
                                            <a class="fediverse-status__media" href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <img src="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($attachment['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                            </a>
                                        <?php elseif ($isVideo): ?>
                                            <div class="fediverse-status__media fediverse-status__media--video">
                                                <video controls preload="metadata">
                                                    <source src="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"<?= $attachmentMediaType !== '' ? ' type="' . htmlspecialchars($attachmentMediaType, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                </video>
                                            </div>
                                        <?php elseif ($isAudio): ?>
                                            <div class="fediverse-status__file">
                                                <div class="fediverse-status__file-name"><?= htmlspecialchars((string) (($attachment['name'] ?? '') ?: 'Audio adjunto'), ENT_QUOTES, 'UTF-8') ?></div>
                                                <audio controls preload="none">
                                                    <source src="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"<?= $attachmentMediaType !== '' ? ' type="' . htmlspecialchars($attachmentMediaType, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                </audio>
                                            </div>
                                        <?php else: ?>
                                            <a class="fediverse-status__file" href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <span class="fediverse-status__file-name"><?= htmlspecialchars((string) (($attachment['name'] ?? '') ?: 'Abrir adjunto'), ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="fediverse-status__file-meta"><?= htmlspecialchars((string) (($attachment['media_type'] ?? '') ?: strtoupper((string) ($attachment['type'] ?? 'archivo'))), ENT_QUOTES, 'UTF-8') ?></span>
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="fediverse-status__footer">
                                <a href="<?= htmlspecialchars((string) (($item['url'] ?? '') ?: ($item['id'] ?? '#')), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir publicación</a>
                                <?php if ($itemObjectId !== ''): ?>
                                    <?php if (!empty($item['url'])): ?>
                                        <span aria-hidden="true"> · </span>
                                        <a href="<?= htmlspecialchars((string) $item['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Enlace a la página pública</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php
                            $historyBoostCount = (int) ($remoteBoostMeta['count'] ?? 0) + (!empty($itemActionState['boosted']) ? 1 : 0);
                            $historyBoostActors = $remoteBoostActors;
                            if (!empty($itemActionState['boosted'])) {
                                $historyBoostActors[] = [
                                    'name' => $fediverseLocalName,
                                    'icon' => $fediverseLocalAvatar,
                                    'url' => $fediverseBaseUrl,
                                ];
                            }
                            $historyFavoriteCount = !empty($itemActionState['liked']) ? 1 : 0;
                            $historyFavoriteActors = [];
                            if (!empty($itemActionState['liked'])) {
                                $historyFavoriteActors[] = [
                                    'name' => $fediverseLocalName,
                                    'icon' => $fediverseLocalAvatar,
                                    'url' => $fediverseBaseUrl,
                                ];
                            }
                            ?>
                            <?php if ($historyBoostCount > 0 || ($remoteReplyMeta['count'] ?? 0) > 0 || $historyFavoriteCount > 0 || !empty($itemActionState['replied'])): ?>
                                <div class="fediverse-status__history">
                                    <?php if ($historyBoostCount > 0): ?>
                                        <span><?= (int) $historyBoostCount ?> impulso<?= ((int) $historyBoostCount === 1) ? '' : 's' ?></span>
                                        <?php if (!empty($historyBoostActors)): ?>
                                            <span class="fediverse-status__actor-icons">
                                                <?php foreach ($historyBoostActors as $remoteBoostActor): ?>
                                                    <?php
                                                    $remoteBoostActorUrl = trim((string) (($remoteBoostActor['url'] ?? '') ?: '#'));
                                                    $remoteBoostActorIcon = $fediverseReplyActorAvatar(['actor_id' => trim((string) ($remoteBoostActor['id'] ?? '')), 'actor_icon' => trim((string) ($remoteBoostActor['icon'] ?? ''))]);
                                                    ?>
                                                    <a href="<?= htmlspecialchars($remoteBoostActorUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars((string) ($remoteBoostActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <?php if ($remoteBoostActorIcon !== ''): ?>
                                                            <img src="<?= htmlspecialchars($remoteBoostActorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($remoteBoostActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                                        <?php else: ?>
                                                            <?= htmlspecialchars(mb_substr((string) (($remoteBoostActor['name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($historyFavoriteCount > 0): ?>
                                        <span><?= (int) $historyFavoriteCount ?> favorito<?= ((int) $historyFavoriteCount === 1) ? '' : 's' ?></span>
                                        <?php if (!empty($historyFavoriteActors)): ?>
                                            <span class="fediverse-status__actor-icons">
                                                <?php foreach ($historyFavoriteActors as $historyFavoriteActor): ?>
                                                    <?php
                                                    $historyFavoriteActorUrl = trim((string) (($historyFavoriteActor['url'] ?? '') ?: '#'));
                                                    $historyFavoriteActorIcon = $fediverseReplyActorAvatar(['actor_id' => trim((string) ($historyFavoriteActor['id'] ?? '')), 'actor_icon' => trim((string) ($historyFavoriteActor['icon'] ?? ''))]);
                                                    ?>
                                                    <a href="<?= htmlspecialchars($historyFavoriteActorUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars((string) ($historyFavoriteActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <?php if ($historyFavoriteActorIcon !== ''): ?>
                                                            <img src="<?= htmlspecialchars($historyFavoriteActorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($historyFavoriteActor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                                        <?php else: ?>
                                                            <?= htmlspecialchars(mb_substr((string) (($historyFavoriteActor['name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (($remoteReplyMeta['count'] ?? 0) > 0): ?><span><?= (int) ($remoteReplyMeta['count'] ?? 0) ?> respuesta<?= ((int) ($remoteReplyMeta['count'] ?? 0) === 1) ? '' : 's' ?></span><?php endif; ?>
                                    <?php if (!empty($itemActionState['replied'])): ?><span><?= (int) ($itemActionState['reply_count'] ?? 0) ?> respuesta<?= ((int) ($itemActionState['reply_count'] ?? 0) === 1) ? '' : 's' ?></span><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="fediverse-status__actions">
                                <form method="post" class="mb-0">
                                    <input type="hidden" name="fediverse_tab" value="home">
                                    <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($itemTargetActorId, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($itemObjectId, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_public_url" value="<?= htmlspecialchars((string) (($item['url'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_item_id" value="<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" name="<?= !empty($itemActionState['liked']) ? 'fediverse_unlike_item' : 'fediverse_like_item' ?>" class="btn btn-outline-secondary btn-sm"><?= !empty($itemActionState['liked']) ? 'Quitar favorito' : 'Favorito' ?></button>
                                </form>
                                <form method="post" class="mb-0">
                                    <input type="hidden" name="fediverse_tab" value="home">
                                    <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($itemTargetActorId, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($itemObjectId, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_public_url" value="<?= htmlspecialchars((string) (($item['url'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_item_id" value="<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_object_title" value="<?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_object_content" value="<?= htmlspecialchars((string) ($item['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_actor_name" value="<?= htmlspecialchars((string) (($item['target_actor_name'] ?? '') ?: ($item['actor_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_actor_icon" value="<?= htmlspecialchars((string) (($item['target_actor_icon'] ?? '') ?: ($item['actor_icon'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_actor_url" value="<?= htmlspecialchars((string) (($item['target_actor_url'] ?? '') ?: ($item['target_actor_id'] ?? '') ?: ($item['actor_url'] ?? '') ?: ($item['actor_id'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php
                                    $boostImageUrl = trim((string) ($item['image'] ?? ''));
                                    $boostImageUrls = array_values(array_unique(array_filter(array_map('strval', is_array($item['images'] ?? null) ? $item['images'] : []))));
                                    if ($boostImageUrl !== '' && !in_array($boostImageUrl, $boostImageUrls, true)) {
                                        array_unshift($boostImageUrls, $boostImageUrl);
                                    }
                                    if ($boostImageUrl === '') {
                                        $boostImageUrl = trim((string) ($item['image'] ?? ''));
                                    }
                                    if ($boostImageUrl === '') {
                                        foreach ($attachments as $attachment) {
                                            $attachmentUrl = trim((string) ($attachment['url'] ?? ''));
                                            $attachmentImage = trim((string) ($attachment['image'] ?? ''));
                                            $attachmentType = strtolower(trim((string) ($attachment['type'] ?? '')));
                                            $attachmentMediaType = strtolower(trim((string) ($attachment['media_type'] ?? '')));
                                            if ($attachmentUrl !== '' && ($attachmentType === 'image' || str_starts_with($attachmentMediaType, 'image/'))) {
                                                $boostImageUrl = $attachmentUrl;
                                                break;
                                            }
                                            if ($attachmentImage !== '' && ($attachmentType === 'link' || $attachmentMediaType === 'text/html' || str_starts_with($attachmentMediaType, 'text/html'))) {
                                                $boostImageUrl = $attachmentImage;
                                                break;
                                            }
                                        }
                                    }
                                    foreach ($attachments as $attachment) {
                                        $attachmentUrl = trim((string) ($attachment['url'] ?? ''));
                                        $attachmentImage = trim((string) ($attachment['image'] ?? ''));
                                        $attachmentType = strtolower(trim((string) ($attachment['type'] ?? '')));
                                        $attachmentMediaType = strtolower(trim((string) ($attachment['media_type'] ?? '')));
                                        if ($attachmentUrl !== '' && ($attachmentType === 'image' || str_starts_with($attachmentMediaType, 'image/')) && !in_array($attachmentUrl, $boostImageUrls, true)) {
                                            $boostImageUrls[] = $attachmentUrl;
                                        }
                                        if ($attachmentImage !== '' && ($attachmentType === 'link' || $attachmentMediaType === 'text/html' || str_starts_with($attachmentMediaType, 'text/html')) && !in_array($attachmentImage, $boostImageUrls, true)) {
                                            $boostImageUrls[] = $attachmentImage;
                                        }
                                    }
                                    ?>
                                    <input type="hidden" name="fediverse_object_image" value="<?= htmlspecialchars($boostImageUrl, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_object_images" value="<?= htmlspecialchars(json_encode($boostImageUrls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fediverse_object_attachments" value="<?= htmlspecialchars(json_encode($attachments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" name="<?= !empty($itemActionState['boosted']) ? 'fediverse_unboost_item' : 'fediverse_boost_item' ?>" class="btn btn-outline-secondary btn-sm"<?= !empty($itemActionState['boosted']) ? ' onclick="return confirm(\'¿Quitar este impulso y borrar la nota local asociada?\');"' : '' ?>><?= !empty($itemActionState['boosted']) ? 'Quitar impulso' : 'Impulsar' ?></button>
                                </form>
                                <details class="fediverse-inline-form">
                                    <summary class="fediverse-inline-form__summary-button">Responder</summary>
                                    <form method="post">
                                        <input type="hidden" name="fediverse_tab" value="home">
                                        <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($itemTargetActorId, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($itemObjectId, ENT_QUOTES, 'UTF-8') ?>">
                                        <textarea name="fediverse_reply_text" class="form-control form-control-sm" rows="3" placeholder="Escribe tu respuesta"></textarea>
                                        <label class="fediverse-inline-check">
                                            <input type="checkbox" name="fediverse_reply_as_note" value="1">
                                            Publicar también como nota en Actualidad
                                        </label>
                                        <button type="submit" name="fediverse_reply_item" class="btn btn-primary btn-sm mt-2">Enviar respuesta</button>
                                    </form>
                                </details>
                            </div>
                            <?php if (!empty($itemReplies)): ?>
                                <div class="fediverse-thread">
                                    <?php foreach ($itemReplies as $reply): ?>
                                        <?php
                                        $replyObjectUrl = trim((string) (($reply['id'] ?? '') ?: ($reply['url'] ?? '')));
                                        $replyPublicUrl = trim((string) ($reply['url'] ?? ''));
                                        $replyActorId = trim((string) ($reply['actor_id'] ?? ''));
                                        $replyActorName = trim((string) ($reply['actor_name'] ?? ''));
                                        $replyActorIcon = $fediverseReplyActorAvatar($reply, (($reply['source'] ?? '') === 'incoming-remote') ? '' : $fediverseLocalAvatar);
                                        $replyBoostImages = [];
                                        $replyActionState = function_exists('nammu_fediverse_action_state_for_item')
                                            ? nammu_fediverse_action_state_for_item($reply)
                                            : ['liked' => false, 'boosted' => false, 'replied' => false, 'shared' => false, 'boost_count' => 0, 'reply_count' => 0, 'share_count' => 0];
                                        ?>
                                        <div class="fediverse-thread__reply">
                                            <div class="fediverse-thread__avatar">
                                                <?php if ($replyActorIcon !== ''): ?>
                                                    <img src="<?= htmlspecialchars($replyActorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                                <?php elseif (($reply['source'] ?? '') === 'incoming-remote'): ?>
                                                    <div class="fediverse-thread__avatar-fallback"><?= htmlspecialchars(mb_substr((string) (($reply['actor_name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php elseif ($fediverseLocalAvatar !== ''): ?>
                                                    <img src="<?= htmlspecialchars($fediverseLocalAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                                <?php else: ?>
                                                    <div class="fediverse-thread__avatar-fallback"><?= htmlspecialchars(mb_substr($fediverseLocalHandle, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fediverse-thread__body">
                                                <div class="fediverse-thread__header">
                                                    <strong><?= htmlspecialchars((string) (($reply['source'] ?? '') === 'incoming-remote' ? (($reply['actor_name'] ?? '') ?: 'Actor remoto') : $fediverseLocalHandle), ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <?php if (!empty($reply['published'])): ?>
                                                        <time datetime="<?= htmlspecialchars((string) $reply['published'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fediverseFormatDate((string) $reply['published']), ENT_QUOTES, 'UTF-8') ?></time>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="fediverse-thread__content"><?= nl2br(htmlspecialchars((string) ($reply['reply_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                                <?php if ($replyObjectUrl !== ''): ?>
                                                    <div class="fediverse-status__actions mt-2">
                                                        <?php if ($replyActorId !== ''): ?>
                                                            <form method="post" class="mb-0">
                                                                <input type="hidden" name="fediverse_tab" value="home">
                                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($replyActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($replyObjectUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_public_url" value="<?= htmlspecialchars($replyPublicUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_item_id" value="<?= htmlspecialchars((string) ($reply['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <button type="submit" name="<?= !empty($replyActionState['liked']) ? 'fediverse_unlike_item' : 'fediverse_like_item' ?>" class="btn btn-outline-secondary btn-sm"><?= !empty($replyActionState['liked']) ? 'Quitar favorito' : 'Favorito' ?></button>
                                                            </form>
                                                            <form method="post" class="mb-0">
                                                                <input type="hidden" name="fediverse_tab" value="home">
                                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($replyActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($replyObjectUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_public_url" value="<?= htmlspecialchars($replyPublicUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_item_id" value="<?= htmlspecialchars((string) ($reply['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_object_title" value="">
                                                                <input type="hidden" name="fediverse_object_content" value="<?= htmlspecialchars((string) ($reply['reply_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_actor_name" value="<?= htmlspecialchars($replyActorName, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_actor_icon" value="<?= htmlspecialchars($replyActorIcon, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_actor_url" value="<?= htmlspecialchars($replyActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_object_image" value="">
                                                                <input type="hidden" name="fediverse_object_images" value="<?= htmlspecialchars(json_encode($replyBoostImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                                                                <button type="submit" name="<?= !empty($replyActionState['boosted']) ? 'fediverse_unboost_item' : 'fediverse_boost_item' ?>" class="btn btn-outline-secondary btn-sm"<?= !empty($replyActionState['boosted']) ? ' onclick="return confirm(\'¿Quitar este impulso y borrar la nota local asociada?\');"' : '' ?>><?= !empty($replyActionState['boosted']) ? 'Quitar impulso' : 'Impulsar' ?></button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <details class="fediverse-inline-form">
                                                            <summary class="fediverse-inline-form__summary-button">Responder</summary>
                                                            <form method="post">
                                                                <input type="hidden" name="fediverse_tab" value="home">
                                                                <input type="hidden" name="fediverse_actor_id" value="<?= htmlspecialchars($replyActorId, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="fediverse_object_url" value="<?= htmlspecialchars($replyObjectUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                                <textarea name="fediverse_reply_text" class="form-control form-control-sm" rows="3" placeholder="Escribe tu respuesta"></textarea>
                                                                <label class="fediverse-inline-check">
                                                                    <input type="checkbox" name="fediverse_reply_as_note" value="1">
                                                                    Publicar también como nota en Actualidad
                                                                </label>
                                                                <button type="submit" name="fediverse_reply_item" class="btn btn-primary btn-sm mt-2">Enviar respuesta</button>
                                                            </form>
                                                        </details>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (($reply['source'] ?? '') !== 'incoming-remote' && !empty($reply['id'])): ?>
                                                    <form method="post" class="mt-2" onsubmit="return confirm('¿Borrar esta respuesta del Fediverso?');">
                                                        <input type="hidden" name="fediverse_tab" value="home">
                                                        <input type="hidden" name="fediverse_reply_action_id" value="<?= htmlspecialchars((string) $reply['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" name="fediverse_delete_reply_item" class="btn btn-outline-danger btn-sm">Borrar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php if ($fediverseTimelineTotalPages > 1): ?>
                <nav class="fediverse-pagination" aria-label="Paginación del timeline">
                    <?php if ($fediverseTimelinePage > 1): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($buildTimelinePageUrl($fediverseTimelinePage - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a>
                    <?php endif; ?>
                    <span class="fediverse-pagination__status">Página <?= (int) $fediverseTimelinePage ?> de <?= (int) $fediverseTimelineTotalPages ?></span>
                    <?php if ($fediverseTimelinePage < $fediverseTimelineTotalPages): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($buildTimelinePageUrl($fediverseTimelinePage + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
