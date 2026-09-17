<?php
/**
 * Nammu — panel de administración. Fediverso: pestaña Mensajes (conversaciones privadas y respuestas públicas).
 * Lo incluye core/admin-page-fediverso.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
?>
<div class="card mb-4">
    <div class="card-body">
        <h3 class="h5 mb-3">Enviar mensaje privado</h3>
        <?php if (empty($fediverseRecipients)): ?>
            <div class="alert alert-secondary mb-0">Necesitas al menos un actor seguido o un seguidor federado para poder enviar mensajes privados.</div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="fediverse_tab" value="messages">
                <div class="form-group">
                    <label for="fediverse_message_recipient">Destinatario</label>
                    <select name="fediverse_message_recipient" id="fediverse_message_recipient" class="form-control fediverse-recipient-select" size="<?= htmlspecialchars((string) min(10, max(4, count($fediverseRecipients))), ENT_QUOTES, 'UTF-8') ?>">
                        <?php foreach ($fediverseRecipients as $recipient): ?>
                            <?php $recipientId = (string) ($recipient['id'] ?? ''); ?>
                            <?php
                            $recipientLabel = (string) (($recipient['name'] ?? '') ?: ($recipient['preferredUsername'] ?? $recipientId));
                            $recipientHandle = $fediverseActorHandleFor([
                                'actor_id' => $recipientId,
                                'actor_username' => trim((string) ($recipient['preferredUsername'] ?? '')),
                            ]);
                            $recipientMeta = [];
                            if ($recipientId !== '' && isset($fediverseFollowingIds[$recipientId])) {
                                $recipientMeta[] = 'seguido';
                            }
                            if ($recipientId !== '' && isset($fediverseFollowerIds[$recipientId])) {
                                $recipientMeta[] = 'seguidor';
                            }
                            $recipientSuffix = empty($recipientMeta) ? '' : ' (' . implode(', ', array_unique($recipientMeta)) . ')';
                            ?>
                            <option value="<?= htmlspecialchars($recipientId, ENT_QUOTES, 'UTF-8') ?>" <?= ($fediverseMessageRecipient ?? '') === $recipientId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($recipientLabel . ' · ' . $recipientHandle . $recipientSuffix, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fediverse_message_text">Mensaje</label>
                    <textarea name="fediverse_message_text" id="fediverse_message_text" class="form-control" rows="6" placeholder="Escribe aquí el mensaje privado."><?= htmlspecialchars($fediverseMessageText ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <button type="submit" name="send_fediverse_message" class="btn btn-primary">Enviar mensaje</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h3 class="h5 mb-3">Conversaciones</h3>
        <?php if (empty($fediverseMessageThreads)): ?>
            <p class="text-muted mb-0">Todavía no hay conversaciones guardadas.</p>
        <?php else: ?>
            <?php foreach ($fediverseMessageThreads as $threadKey => $messages): ?>
                <?php $firstMessage = $messages[0] ?? []; ?>
                <?php
                $threadDisplayMessages = array_values(array_filter($messages, static function (array $message): bool {
                    return strtolower(trim((string) ($message['visibility'] ?? 'private'))) !== 'public';
                }));
                if (empty($threadDisplayMessages)) {
                    continue;
                }
                $threadHeaderMessage = $threadDisplayMessages[0] ?? $firstMessage;
                $lastThreadMessage = $threadDisplayMessages[count($threadDisplayMessages) - 1] ?? $threadHeaderMessage;
                $actorId = trim((string) ($threadHeaderMessage['actor_id'] ?? ''));
                $conversationActor = $fediverseActorsById[$actorId] ?? null;
                $conversationActorName = trim((string) (($threadHeaderMessage['actor_name'] ?? '') ?: ($conversationActor['name'] ?? '') ?: ($conversationActor['preferredUsername'] ?? '') ?: $actorId));
                $conversationActorIcon = $fediverseValidAvatarUrl(trim((string) (($threadHeaderMessage['actor_icon'] ?? '') ?: ($conversationActor['icon'] ?? ''))));
                $conversationActorHandle = $fediverseActorHandleFor($threadHeaderMessage);
                ?>
                <div class="border rounded p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div class="fediverse-message__header mb-0">
                            <div class="fediverse-message__avatar">
                                <?php if ($conversationActorIcon !== ''): ?>
                                    <img src="<?= htmlspecialchars($conversationActorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="fediverse-message__avatar-fallback"><?= htmlspecialchars(mb_substr($conversationActorName !== '' ? $conversationActorName : 'A', 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="fediverse-message__identity">
                                <strong><?= htmlspecialchars($conversationActorName, ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="small text-muted mt-1"><?= htmlspecialchars($conversationActorHandle, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-muted mt-1">Conversación privada</div>
                            </div>
                        </div>
                    </div>
                    <?php foreach ($threadDisplayMessages as $message): ?>
                        <?php $isOutgoing = (($message['direction'] ?? '') === 'outgoing'); ?>
                        <?php
                        $messageActorId = trim((string) ($message['actor_id'] ?? ''));
                        $messageActor = $messageActorId !== '' ? ($fediverseActorsById[$messageActorId] ?? null) : null;
                        $messageActorName = trim((string) (($message['actor_name'] ?? '') ?: ($messageActor['name'] ?? '') ?: ($messageActor['preferredUsername'] ?? '') ?: $messageActorId));
                        $messageActorHandle = $fediverseActorHandleFor([
                            'actor_id' => $messageActorId,
                            'actor_username' => trim((string) (($message['actor_username'] ?? '') ?: ($messageActor['preferredUsername'] ?? ''))),
                        ]);
                        $messageActorIcon = trim((string) ($message['actor_icon'] ?? ''));
                        if (!$isOutgoing && $messageActorIcon === '') {
                            $messageActorIcon = trim((string) (($messageActor['icon'] ?? '') ?: ($conversationActor['icon'] ?? '')));
                        }
                        $messageActorIcon = $fediverseValidAvatarUrl($messageActorIcon);
                        $messageClasses = 'mb-3 p-3 rounded';
                        ?>
                        <div class="<?= htmlspecialchars($messageClasses, ENT_QUOTES, 'UTF-8') ?>" style="background: <?= $isOutgoing ? '#eef6ff' : '#f7f7f7' ?>; border-left: 4px solid <?= $isOutgoing ? '#1b8eed' : '#999' ?>;">
                            <div class="fediverse-message__visibility">
                                <span class="fediverse-visibility-badge fediverse-visibility-badge--private">
                                    Privada
                                </span>
                            </div>
                            <div class="fediverse-message__header">
                                <div class="fediverse-message__avatar">
                                    <?php if ($isOutgoing && $fediverseLocalAvatar !== ''): ?>
                                        <img src="<?= htmlspecialchars($fediverseLocalAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                    <?php elseif (!$isOutgoing && $messageActorIcon !== ''): ?>
                                        <img src="<?= htmlspecialchars($messageActorIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <div class="fediverse-message__avatar-fallback"><?= htmlspecialchars(mb_substr($isOutgoing ? $fediverseLocalHandle : (string) (($message['actor_name'] ?? '') ?: 'A'), 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="fediverse-message__identity">
                                    <strong><?= htmlspecialchars($isOutgoing ? $fediverseLocalName : $messageActorName, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if ($isOutgoing): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($fediverseLocalHandle, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (!$isOutgoing): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($messageActorHandle, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="small text-muted mb-2">
                                <?= $isOutgoing ? 'Enviado' : 'Recibido' ?> · <?= htmlspecialchars($fediverseFormatDate((string) ($message['published'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($message['delivery_status'])): ?>
                                    · <?= htmlspecialchars((string) ($message['delivery_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                                <?php if (!$isOutgoing && array_key_exists('verified', $message)): ?>
                                    · <?= !empty($message['verified']) ? 'verificado' : 'no verificado' ?>
                                <?php endif; ?>
                            </div>
                            <div><?= nl2br(htmlspecialchars((string) ($message['content'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <details class="fediverse-inline-form mt-3">
                        <summary>Responder</summary>
                        <form method="post">
                            <input type="hidden" name="fediverse_tab" value="messages">
                            <input type="hidden" name="fediverse_message_actor_id" value="<?= htmlspecialchars((string) $actorId, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="fediverse_reply_to_message_id" value="<?= htmlspecialchars((string) ($lastThreadMessage['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <textarea name="fediverse_private_reply_text" class="form-control form-control-sm" rows="3" placeholder="Escribe tu respuesta privada"></textarea>
                            <button type="submit" name="send_fediverse_private_reply" class="btn btn-primary btn-sm mt-2">Responder</button>
                        </form>
                    </details>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
