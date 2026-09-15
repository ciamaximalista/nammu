<?php
/**
 * Nammu — panel de administración. Acciones POST del módulo Fediverso: seguir/dejar de seguir y bloquear
 * actores, refrescar y reconstruir el timeline, inspeccionar objetos, mensajes privados, favoritos,
 * impulsos, respuestas, borrados, ocultar respuestas, Webmentions y compartir notas.
 *
 * Se incluye desde admin.php (ámbito global) solo en peticiones POST con token CSRF válido sobre
 * page=fediverso. Antes del troceado estas acciones quedaban fuera de la comprobación CSRF.
 */

    $refreshFediverseAvatarSnapshots = static function (array $config): void {
        if (!function_exists('nammu_actuality_rebuild_snapshot') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        $baseUrl = trim((string) (($config['site_url'] ?? '') ?: nammu_base_url()));
        $siteTitle = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
        $siteDescription = trim((string) (($config['site_description'] ?? '') ?: ''));
        $siteLang = trim((string) (($config['site_lang'] ?? '') ?: 'es'));
        if (function_exists('nammu_actuality_rebuild_snapshot')) {
            nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
        }
        if (function_exists('nammu_fediverse_rebuild_light_snapshots')) {
            nammu_fediverse_rebuild_light_snapshots($config);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
    };
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['follow_fediverse_actor'])) {
        $fediverseActorInput = trim((string) ($_POST['fediverse_actor_input'] ?? ''));
        $followResult = nammu_fediverse_follow_actor($fediverseActorInput);
        $fediverseFeedback = [
            'type' => !empty($followResult['ok']) ? 'success' : 'danger',
            'message' => (string) ($followResult['message'] ?? ''),
        ];
        $fediverseRedirect = true;
        $fediverseRedirectState = ['actor_input' => empty($followResult['ok']) ? $fediverseActorInput : ''];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restart_fediverse_actor'])) {
        $actorId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $restartResult = nammu_fediverse_restart_follow_actor($actorId);
        $fediverseFeedback = [
            'type' => !empty($restartResult['ok']) ? 'success' : 'danger',
            'message' => (string) ($restartResult['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recache_fediverse_actor_avatar'])) {
        $actorId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $config = load_config_file();
        $recacheResult = function_exists('nammu_fediverse_enqueue_actor_avatar_recache')
            ? nammu_fediverse_enqueue_actor_avatar_recache($actorId, $config, true)
            : nammu_fediverse_recache_actor_avatar($actorId, $config);
        $fediverseFeedback = [
            'type' => !empty($recacheResult['ok']) ? 'success' : 'danger',
            'message' => (string) ($recacheResult['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recache_all_fediverse_actor_avatars'])) {
        $config = load_config_file();
        $recacheResult = function_exists('nammu_fediverse_enqueue_all_actor_avatar_recaches')
            ? nammu_fediverse_enqueue_all_actor_avatar_recaches($config)
            : nammu_fediverse_recache_all_actor_avatars($config);
        $fediverseFeedback = [
            'type' => !empty($recacheResult['ok']) ? 'success' : 'danger',
            'message' => (string) ($recacheResult['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unfollow_fediverse_actor'])) {
        $actorId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $ok = $actorId !== '' && nammu_fediverse_unfollow_actor($actorId);
        $fediverseFeedback = [
            'type' => $ok ? 'success' : 'danger',
            'message' => $ok ? 'Actor eliminado del Fediverso.' : 'No se pudo quitar ese actor.',
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_fediverse_follower'])) {
        $actorId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $config = load_config_file();
        $result = nammu_fediverse_block_actor($actorId, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_fediverse_actor'])) {
        $actorId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $result = nammu_fediverse_unblock_actor($actorId);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_fediverse_timeline'])) {
        $config = load_config_file();
        $stats = admin_run_scheduled_tasks();
        if (function_exists('nammu_fediverse_retry_pending_follower_accepts')) {
            $acceptStats = nammu_fediverse_retry_pending_follower_accepts($config);
            $stats['follow_accepts_checked'] = (int) ($acceptStats['checked'] ?? 0);
            $stats['follow_accepts_sent'] = (int) ($acceptStats['accepted'] ?? 0);
        }
        $fediverseFeedback = [
            'type' => 'info',
            'message' => 'Fediverso refrescado. Actores revisados: ' . (int) ($stats['fediverse_checked'] ?? 0) . '. Actividades nuevas: ' . (int) (($stats['fediverse_new'] ?? 0) + ($stats['fediverse_inbox_sync_new'] ?? 0)) . '. Hilos recientes actualizados: ' . (int) ($stats['fediverse_recent_threads_warmed'] ?? 0) . '. Seguidores revisados: ' . (int) ($stats['fediverse_followers_checked'] ?? 0) . '. Seguidores eliminados: ' . (int) ($stats['fediverse_followers_removed'] ?? 0) . '. Accept enviados: ' . (int) ($stats['follow_accepts_sent'] ?? 0) . '.',
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_fediverse_threads'])) {
        $config = load_config_file();
        $stats = admin_refresh_fediverse_threads($config, 20);
        $fediverseFeedback = [
            'type' => 'info',
            'message' => 'Hilos del Fediverso actualizados. Hilos precalentados: ' . (int) ($stats['threads_warmed'] ?? 0) . '. Accept enviados: ' . (int) ($stats['follow_accepts_sent'] ?? 0) . '.',
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rebuild_fediverse_timeline'])) {
        $config = load_config_file();
        $stats = admin_rebuild_fediverse_timeline($config);
        $fediverseFeedback = [
            'type' => 'info',
            'message' => 'Timeline del Fediverso reconstruido, incluyendo la parte local. Actores revisados: ' . (int) ($stats['checked'] ?? 0) . '. Actividades importadas: ' . (int) (($stats['new'] ?? 0) + ($stats['fediverse_inbox_sync_new'] ?? 0)) . '. Hilos precalentados: ' . (int) ($stats['threads_warmed'] ?? 0) . '. Accept enviados: ' . (int) ($stats['follow_accepts_sent'] ?? 0) . '.',
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inspect_fediverse_object'])) {
        $fediverseInspectUrl = trim((string) ($_POST['fediverse_inspect_url'] ?? ''));
        $config = load_config_file();
        $fediverseInspectResult = nammu_fediverse_inspect_object($fediverseInspectUrl, $config);
        $fediverseFeedback = [
            'type' => !empty($fediverseInspectResult['ok']) ? 'info' : 'danger',
            'message' => !empty($fediverseInspectResult['ok']) ? 'Inspección ActivityPub completada.' : (string) ($fediverseInspectResult['message'] ?? ''),
        ];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_fediverse_message'])) {
        $recipientId = trim((string) ($_POST['fediverse_message_recipient'] ?? ''));
        $messageText = trim((string) ($_POST['fediverse_message_text'] ?? ''));
        $fediverseMessageRecipient = $recipientId;
        $fediverseMessageText = $messageText;
        $config = load_config_file();
        $result = nammu_fediverse_send_private_message($recipientId, $messageText, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
        $fediverseRedirectState = [
            'message_recipient' => $fediverseMessageRecipient,
            'message_text' => !empty($result['ok']) ? '' : $messageText,
        ];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_fediverse_private_reply'])) {
        $recipientId = trim((string) ($_POST['fediverse_message_actor_id'] ?? ''));
        $messageText = trim((string) ($_POST['fediverse_private_reply_text'] ?? ''));
        $replyToMessageId = trim((string) ($_POST['fediverse_reply_to_message_id'] ?? ''));
        $fediverseMessageRecipient = $recipientId;
        $fediverseMessageText = '';
        $config = load_config_file();
        $result = nammu_fediverse_send_private_message($recipientId, $messageText, $config, $replyToMessageId);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
        $fediverseRedirectState = [
            'message_recipient' => $fediverseMessageRecipient,
            'message_text' => !empty($result['ok']) ? '' : $messageText,
        ];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_like_item'])) {
        $recipientId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $objectUrl = trim((string) ($_POST['fediverse_object_url'] ?? ''));
        $config = load_config_file();
        $result = nammu_fediverse_send_like($recipientId, $objectUrl, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_unlike_item'])) {
        $item = [
            'object_id' => trim((string) ($_POST['fediverse_object_url'] ?? '')),
            'url' => trim((string) ($_POST['fediverse_public_url'] ?? '')),
            'id' => trim((string) ($_POST['fediverse_item_id'] ?? '')),
        ];
        $config = load_config_file();
        $result = nammu_fediverse_send_undo_like_for_item($item, $config);
        if (!empty($result['ok']) && function_exists('nammu_fediverse_rebuild_snapshots')) {
            nammu_fediverse_rebuild_snapshots($config);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_boost_item'])) {
        $recipientId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $objectUrl = trim((string) ($_POST['fediverse_object_url'] ?? ''));
        $publicUrl = trim((string) ($_POST['fediverse_public_url'] ?? ''));
        $objectTitle = trim((string) ($_POST['fediverse_object_title'] ?? ''));
        $objectContent = trim((string) ($_POST['fediverse_object_content'] ?? ''));
        $objectImage = trim((string) ($_POST['fediverse_object_image'] ?? ''));
        $objectActorName = trim((string) ($_POST['fediverse_actor_name'] ?? ''));
        $objectActorIcon = trim((string) ($_POST['fediverse_actor_icon'] ?? ''));
        $objectActorUrl = trim((string) ($_POST['fediverse_actor_url'] ?? ''));
        $objectImages = json_decode((string) ($_POST['fediverse_object_images'] ?? '[]'), true);
        $objectImages = array_values(array_unique(array_filter(array_map('strval', is_array($objectImages) ? $objectImages : []))));
        $objectAttachments = json_decode((string) ($_POST['fediverse_object_attachments'] ?? '[]'), true);
        $objectAttachments = array_values(array_filter(array_map(static function ($attachment): ?array {
            if (!is_array($attachment)) {
                return null;
            }
            $url = trim((string) ($attachment['url'] ?? ''));
            if ($url === '') {
                return null;
            }
            return [
                'type' => strtolower(trim((string) ($attachment['type'] ?? 'document'))),
                'url' => $url,
                'name' => trim((string) ($attachment['name'] ?? '')),
                'media_type' => trim((string) ($attachment['media_type'] ?? ($attachment['mediaType'] ?? ''))),
                'image' => trim((string) ($attachment['image'] ?? '')),
                'summary' => trim((string) ($attachment['summary'] ?? '')),
            ];
        }, is_array($objectAttachments) ? $objectAttachments : [])));
        if ($objectImage !== '' && !in_array($objectImage, $objectImages, true)) {
            array_unshift($objectImages, $objectImage);
        }
        $resolvedObjectType = '';
        $config = load_config_file();
        if (!function_exists('nammu_fediverse_signed_fetch_json') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        if (function_exists('nammu_fediverse_signed_fetch_json') && function_exists('nammu_fediverse_resolve_actor')) {
            $resolvedObject = nammu_fediverse_signed_fetch_json($objectUrl, $config);
            if (!is_array($resolvedObject)) {
                $resolvedObject = nammu_fediverse_fetch_json($objectUrl);
            }
            if (is_array($resolvedObject)) {
                $resolvedObjectType = strtolower(trim((string) ($resolvedObject['type'] ?? '')));
                $resolvedActorId = trim((string) (($resolvedObject['attributedTo'] ?? '') ?: ($resolvedObject['actor'] ?? '')));
                if ($resolvedActorId !== '') {
                    $resolvedActor = nammu_fediverse_resolve_actor($resolvedActorId, $config);
                    if (is_array($resolvedActor)) {
                        $resolvedActorName = trim((string) (($resolvedActor['name'] ?? '') ?: ($resolvedActor['preferredUsername'] ?? '') ?: ''));
                        $resolvedActorIcon = trim((string) ($resolvedActor['icon'] ?? ''));
                        $resolvedActorUrl = trim((string) (($resolvedActor['url'] ?? '') ?: ($resolvedActor['id'] ?? '')));
                        if ($resolvedActorName !== '') {
                            $objectActorName = $resolvedActorName;
                        }
                        if ($resolvedActorIcon !== '') {
                            $objectActorIcon = $resolvedActorIcon;
                        }
                        if ($resolvedActorUrl !== '') {
                            $objectActorUrl = $resolvedActorUrl;
                        }
                    }
                }
            }
        }
        if ($resolvedObjectType === 'note') {
            $objectTitle = '';
        }
        $result = nammu_fediverse_send_announce($recipientId, $objectUrl, $config);
        if (!empty($result['ok'])) {
            if (!function_exists('admin_send_social_broadcast_to_configured_networks') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
                require_once NAMMU_ROOT . '/core/admin-redes.php';
            }
            if (!function_exists('nammu_actuality_add_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
                require_once NAMMU_ROOT . '/core/actualidad.php';
            }
            $baseUrl = rtrim((string) (($config['site_url'] ?? '') ?: nammu_base_url()), '/');
            $siteTitle = trim((string) (($config['site_name'] ?? '') ?: ''));
            $siteDescription = trim((string) ($config['site_description'] ?? ''));
            $siteLang = trim((string) ($config['site_lang'] ?? 'es'));
            $noteParts = [];
            if ($objectTitle !== '') {
                $noteParts[] = $objectTitle;
            }
            if ($objectContent !== '' && $objectContent !== $objectTitle) {
                $noteParts[] = $objectContent;
            }
            $noteText = trim(implode("\n\n", $noteParts));
            $displayUrl = function_exists('nammu_fediverse_canonical_public_object_url')
                ? nammu_fediverse_canonical_public_object_url($objectUrl, $publicUrl, $config)
                : ($publicUrl !== '' ? $publicUrl : $objectUrl);
            $originalPublicUrl = trim((string) ($displayUrl ?: ($publicUrl ?: $objectUrl)));
            if ($displayUrl !== '' && !str_contains($noteText, $displayUrl)) {
                $noteText = trim($noteText . "\n\n" . $displayUrl);
            }
            if ($noteText !== '' && function_exists('nammu_actuality_add_manual_item')) {
                $manualItem = nammu_actuality_add_manual_item($noteText, $baseUrl, $siteTitle, $objectImage, [
                    'via' => 'boost',
                    'images' => $objectImages,
                    'attachments' => $objectAttachments,
                    'boost_original_url' => $originalPublicUrl,
                    'boost_actor_name' => $objectActorName,
                    'boost_actor_icon' => $objectActorIcon,
                    'boost_actor_url' => $objectActorUrl,
                ]);
                if (function_exists('nammu_actuality_add_item_to_snapshots')) {
                    nammu_actuality_add_item_to_snapshots($manualItem);
                }
                nammu_fediverse_record_action('share', '', $objectUrl, [
                    'share_text' => $objectContent,
                    'title' => $objectTitle,
                    'via' => 'boost',
                    'image' => $objectImage,
                    'images' => $objectImages,
                    'attachments' => $objectAttachments,
                    'manual_item_id' => (string) ($manualItem['id'] ?? ''),
                    'public_url' => $originalPublicUrl,
                    'boost_actor_name' => $objectActorName,
                    'boost_actor_icon' => $objectActorIcon,
                    'boost_actor_url' => $objectActorUrl,
                ]);
                $result['message'] = rtrim((string) ($result['message'] ?? '')) . ' También publicada como nota.';
                if (function_exists('admin_enqueue_social_broadcast')) {
                    $socialTextParts = [];
                    if ($objectTitle !== '') {
                        $socialTextParts[] = '**' . $objectTitle . '**';
                    }
                    if ($objectContent !== '' && $objectContent !== $objectTitle) {
                        $socialTextParts[] = $objectContent;
                    }
                    if ($originalPublicUrl !== '') {
                        $socialTextParts[] = 'Via: ' . $originalPublicUrl;
                    }
                    $socialText = trim(implode("\n\n", array_filter($socialTextParts, static fn(string $part): bool => trim($part) !== '')));
                    $allConfiguredNetworks = function_exists('admin_social_broadcast_available_networks')
                        ? array_keys(admin_social_broadcast_available_networks(get_settings()))
                        : [];
                    // For boosts, external socials should point only to the original publication URL.
                    $queueResult = admin_enqueue_social_broadcast($socialText !== '' ? $socialText : $noteText, $objectImages, $allConfiguredNetworks, '');
                    if (!empty($queueResult['ok'])) {
                        $result['message'] .= ' Encolada para redes.';
                    }
                }
            }
        }
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_unboost_item'])) {
        $item = [
            'object_id' => trim((string) ($_POST['fediverse_object_url'] ?? '')),
            'url' => trim((string) ($_POST['fediverse_public_url'] ?? '')),
            'id' => trim((string) ($_POST['fediverse_item_id'] ?? '')),
        ];
        $config = load_config_file();
        $result = nammu_fediverse_send_undo_announce_for_item($item, $config);
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_reply_item'])) {
        $recipientId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $objectUrl = trim((string) ($_POST['fediverse_object_url'] ?? ''));
        $replyText = trim((string) ($_POST['fediverse_reply_text'] ?? ''));
        $replyAlsoAsNote = !empty($_POST['fediverse_reply_as_note']);
        $replyObjectImage = trim((string) ($_POST['fediverse_object_image'] ?? ''));
        $replyObjectImages = json_decode((string) ($_POST['fediverse_object_images'] ?? '[]'), true);
        $replyObjectImages = array_values(array_unique(array_filter(array_map('strval', is_array($replyObjectImages) ? $replyObjectImages : []))));
        $replyObjectAttachments = json_decode((string) ($_POST['fediverse_object_attachments'] ?? '[]'), true);
        $replyObjectAttachments = array_values(array_filter(array_map(static function ($attachment): ?array {
            if (!is_array($attachment)) {
                return null;
            }
            $url = trim((string) ($attachment['url'] ?? ''));
            if ($url === '') {
                return null;
            }
            return [
                'type' => strtolower(trim((string) ($attachment['type'] ?? 'document'))),
                'url' => $url,
                'name' => trim((string) ($attachment['name'] ?? '')),
                'media_type' => trim((string) ($attachment['media_type'] ?? ($attachment['mediaType'] ?? ''))),
                'image' => trim((string) ($attachment['image'] ?? '')),
                'summary' => trim((string) ($attachment['summary'] ?? '')),
            ];
        }, is_array($replyObjectAttachments) ? $replyObjectAttachments : [])));
        if ($replyObjectImage !== '' && !in_array($replyObjectImage, $replyObjectImages, true)) {
            array_unshift($replyObjectImages, $replyObjectImage);
        }
        $config = load_config_file();
        if ($recipientId === '') {
            $result = nammu_fediverse_send_local_reply($objectUrl, $replyText, $config);
        } else {
            $result = nammu_fediverse_send_reply($recipientId, $objectUrl, $replyText, $config);
        }
        if (!empty($result['ok']) && $replyAlsoAsNote) {
            if (!function_exists('nammu_actuality_add_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
                require_once NAMMU_ROOT . '/core/actualidad.php';
            }
            $baseUrl = rtrim((string) (($config['site_url'] ?? '') ?: nammu_base_url()), '/');
            $siteTitle = trim((string) (($config['site_name'] ?? '') ?: ''));
            $siteDescription = trim((string) ($config['site_description'] ?? ''));
            $siteLang = trim((string) ($config['site_lang'] ?? 'es'));
            $noteText = $replyText;
            if ($objectUrl !== '' && !str_contains($noteText, $objectUrl)) {
                $noteText = trim($noteText . "\n\n" . $objectUrl);
            }
            if (function_exists('nammu_actuality_add_manual_item')) {
                $manualItem = nammu_actuality_add_manual_item($noteText, $baseUrl, $siteTitle, $replyObjectImage, [
                    'via' => 'reply',
                    'images' => $replyObjectImages,
                    'attachments' => $replyObjectAttachments,
                    'reply_target_url' => (string) (($result['object_url'] ?? '') ?: $objectUrl),
                    'reply_note_id' => (string) ($result['note_id'] ?? ''),
                    'reply_activity_id' => (string) ($result['activity_id'] ?? ''),
                ]);
                if (function_exists('nammu_actuality_rebuild_snapshot')) {
                    nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
                }
                if (!function_exists('admin_enqueue_social_broadcast') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
                    require_once NAMMU_ROOT . '/core/admin-redes.php';
                }
                if (is_array($manualItem) && function_exists('admin_enqueue_social_broadcast') && function_exists('admin_social_broadcast_available_networks')) {
                    $allConfiguredNetworks = array_keys(admin_social_broadcast_available_networks(get_settings()));
                    if (!empty($allConfiguredNetworks)) {
                        $fediverseUrl = function_exists('admin_social_broadcast_fediverse_url_for_actuality_item')
                            ? admin_social_broadcast_fediverse_url_for_actuality_item($manualItem)
                            : '';
                        admin_enqueue_social_broadcast($noteText, $replyObjectImages, $allConfiguredNetworks, $fediverseUrl);
                    }
                }
                $deliveryStats = nammu_fediverse_deliver_local_items($config);
                $result['message'] = rtrim((string) ($result['message'] ?? '')) . ' También publicada como nota. Entregas federadas: ' . (int) ($deliveryStats['delivered'] ?? 0) . '.';
            }
        }
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_delete_local_item'])) {
        $itemId = trim((string) ($_POST['fediverse_local_item_id'] ?? ''));
        $config = load_config_file();
        $result = nammu_fediverse_delete_local_item($itemId, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_delete_reply_item'])) {
        $replyActionId = trim((string) ($_POST['fediverse_reply_action_id'] ?? ''));
        $config = load_config_file();
        $result = nammu_fediverse_delete_public_reply($replyActionId, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_hide_incoming_reply'])) {
        $config = load_config_file();
        $reply = [
            'id' => trim((string) ($_POST['fediverse_incoming_reply_id'] ?? '')),
            'url' => trim((string) ($_POST['fediverse_incoming_reply_url'] ?? '')),
            'target_url' => trim((string) ($_POST['fediverse_incoming_reply_target'] ?? '')),
            'published' => trim((string) ($_POST['fediverse_incoming_reply_published'] ?? '')),
            'reply_text' => trim((string) ($_POST['fediverse_incoming_reply_text'] ?? '')),
            'actor_id' => trim((string) ($_POST['fediverse_incoming_reply_actor'] ?? '')),
        ];
        $result = nammu_fediverse_hide_incoming_reply($reply, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_webmention'])) {
        $signature = trim((string) ($_POST['webmention_signature'] ?? ''));
        $deleted = function_exists('nammu_webmention_delete') ? nammu_webmention_delete($signature) : false;
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $fediverseFeedback = [
            'type' => $deleted ? 'success' : 'danger',
            'message' => $deleted ? 'Mención eliminada.' : 'No se pudo eliminar la mención.',
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_share_note'])) {
        if (!function_exists('nammu_actuality_add_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        $objectUrl = trim((string) ($_POST['fediverse_object_url'] ?? ''));
        $shareText = trim((string) ($_POST['fediverse_share_text'] ?? ''));
        $shareTitle = trim((string) ($_POST['fediverse_object_title'] ?? ''));
        $shareObjectImage = trim((string) ($_POST['fediverse_object_image'] ?? ''));
        $shareObjectImages = json_decode((string) ($_POST['fediverse_object_images'] ?? '[]'), true);
        $shareObjectImages = array_values(array_unique(array_filter(array_map('strval', is_array($shareObjectImages) ? $shareObjectImages : []))));
        $shareObjectAttachments = json_decode((string) ($_POST['fediverse_object_attachments'] ?? '[]'), true);
        $shareObjectAttachments = array_values(array_filter(array_map(static function ($attachment): ?array {
            if (!is_array($attachment)) {
                return null;
            }
            $url = trim((string) ($attachment['url'] ?? ''));
            if ($url === '') {
                return null;
            }
            return [
                'type' => strtolower(trim((string) ($attachment['type'] ?? 'document'))),
                'url' => $url,
                'name' => trim((string) ($attachment['name'] ?? '')),
                'media_type' => trim((string) ($attachment['media_type'] ?? ($attachment['mediaType'] ?? ''))),
                'image' => trim((string) ($attachment['image'] ?? '')),
                'summary' => trim((string) ($attachment['summary'] ?? '')),
            ];
        }, is_array($shareObjectAttachments) ? $shareObjectAttachments : [])));
        if ($shareObjectImage !== '' && !in_array($shareObjectImage, $shareObjectImages, true)) {
            array_unshift($shareObjectImages, $shareObjectImage);
        }
        $config = load_config_file();
        $baseUrl = rtrim((string) (($config['site_url'] ?? '') ?: nammu_base_url()), '/');
        $siteTitle = trim((string) (($config['site_name'] ?? '') ?: ''));
        $siteDescription = trim((string) ($config['site_description'] ?? ''));
        $siteLang = trim((string) ($config['site_lang'] ?? 'es'));
        $noteText = $shareText !== '' ? $shareText : $shareTitle;
        if ($objectUrl !== '' && !str_contains($noteText, $objectUrl)) {
            $noteText = trim($noteText . "\n\n" . $objectUrl);
        }
        if ($noteText === '' || !function_exists('nammu_actuality_add_manual_item')) {
            $fediverseFeedback = [
                'type' => 'danger',
                'message' => 'No se pudo crear la nota compartida.',
            ];
            $fediverseRedirect = true;
        } else {
            $manualItem = nammu_actuality_add_manual_item($noteText, $baseUrl, $siteTitle, $shareObjectImage, [
                'images' => $shareObjectImages,
                'attachments' => $shareObjectAttachments,
            ]);
            if (function_exists('nammu_actuality_rebuild_snapshot')) {
                nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
            }
            if (function_exists('nammu_fediverse_record_action')) {
                nammu_fediverse_record_action('share', '', $objectUrl, ['share_text' => $shareText, 'title' => $shareTitle]);
            }
            if (!function_exists('admin_enqueue_social_broadcast') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
                require_once NAMMU_ROOT . '/core/admin-redes.php';
            }
            if (is_array($manualItem) && function_exists('admin_enqueue_social_broadcast') && function_exists('admin_social_broadcast_available_networks')) {
                $allConfiguredNetworks = array_keys(admin_social_broadcast_available_networks(get_settings()));
                if (!empty($allConfiguredNetworks)) {
                    $fediverseUrl = function_exists('admin_social_broadcast_fediverse_url_for_actuality_item')
                        ? admin_social_broadcast_fediverse_url_for_actuality_item($manualItem)
                        : '';
                    admin_enqueue_social_broadcast($noteText, $shareObjectImages, $allConfiguredNetworks, $fediverseUrl);
                }
            }
            $deliveryStats = nammu_fediverse_deliver_local_items($config);
            $fediverseFeedback = [
                'type' => 'success',
                'message' => 'Nota compartida. Entregas federadas: ' . (int) ($deliveryStats['delivered'] ?? 0) . '.',
            ];
            $fediverseRedirect = true;
        }
    }
    if ($fediverseRedirect) {
        $_SESSION['fediverse_feedback'] = $fediverseFeedback;
        $_SESSION['fediverse_state'] = $fediverseRedirectState;
        $redirectTab = strtolower(trim((string) ($_POST['fediverse_tab'] ?? ($_GET['tab'] ?? 'home'))));
        if (!in_array($redirectTab, ['home', 'notifications', 'messages', 'mentions', 'network', 'settings'], true)) {
            $redirectTab = 'home';
        }
        $redirectUrl = 'admin.php?page=fediverso&tab=' . rawurlencode($redirectTab);
        if ($redirectTab === 'home') {
            $timelinePage = max(1, (int) ($_POST['timeline_page'] ?? ($_GET['timeline_page'] ?? 1)));
            if ($timelinePage > 1) {
                $redirectUrl .= '&timeline_page=' . $timelinePage;
            }
        }
        header('Location: ' . $redirectUrl);
        exit;
    }
