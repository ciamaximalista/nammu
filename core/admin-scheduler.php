<?php
/**
 * Nammu — panel de administración.
 * Tareas programadas (light, maintenance, heavy, link cards) y planificador central multiinstancia.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Ejecuta tareas programadas fuera del ciclo de petición web.
 *
 * @return array{published:int,notifications_processed:int,notifications_remaining:int}
 */
function admin_run_scheduled_tasks(): array {
    $config = nammu_load_config();
    if (!function_exists('admin_process_social_rss_feeds') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
        require_once NAMMU_ROOT . '/core/admin-redes.php';
    }
    if (!function_exists('nammu_fediverse_refresh_following') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
        require_once NAMMU_ROOT . '/core/fediverso.php';
    }
    $fediverseStats = ['checked' => 0, 'new' => 0, 'followers_checked' => 0, 'followers_removed' => 0];
    $fediverseInboxSyncStats = ['scanned' => 0, 'new' => 0];
    $fediverseRecentThreadsWarmed = 0;
    $published = function_exists('nammu_publish_scheduled_posts')
        ? (int) nammu_publish_scheduled_posts(CONTENT_DIR)
        : 0;
    $scheduledDeliveryStats = ['followers' => 0, 'delivered' => 0];
    if ($published > 0 && function_exists('nammu_fediverse_deliver_local_items')) {
        $scheduledDeliveryStats = nammu_fediverse_deliver_local_items($config);
    }
    if (function_exists('nammu_fediverse_process_inbox_queue')) {
        $stepStartedAt = microtime(true);
        $fediverseInboxQueueStats = nammu_fediverse_process_inbox_queue($config, 50, 40);
        admin_cli_timing_log('scheduled', 'process_inbox_queue', $stepStartedAt, [
            'processed' => (int) ($fediverseInboxQueueStats['processed'] ?? 0),
            'failed' => (int) ($fediverseInboxQueueStats['failed'] ?? 0),
            'remaining' => (int) ($fediverseInboxQueueStats['remaining'] ?? 0),
        ]);
    }
    if (function_exists('nammu_fediverse_refresh_following')) {
        $stepStartedAt = microtime(true);
        $fediverseStats = nammu_fediverse_refresh_following([
            'actor_limit' => 4,
            'outbox_limit' => 3,
            'outbox_inspect_limit' => 18,
            'refresh_followers' => false,
            'resolve_actor_ttl' => 21600,
        ]);
        admin_cli_timing_log('scheduled', 'refresh_following', $stepStartedAt, [
            'checked' => (int) ($fediverseStats['checked'] ?? 0),
            'new' => (int) ($fediverseStats['new'] ?? 0),
        ]);
        if (function_exists('nammu_fediverse_sync_recent_followed_inbox_items')) {
            $stepStartedAt = microtime(true);
            $fediverseInboxSyncStats = nammu_fediverse_sync_recent_followed_inbox_items($config, 8, 400);
            admin_cli_timing_log('scheduled', 'sync_recent_followed_inbox_items', $stepStartedAt, [
                'scanned' => (int) ($fediverseInboxSyncStats['scanned'] ?? 0),
                'new' => (int) ($fediverseInboxSyncStats['new'] ?? 0),
            ]);
        }
        if (function_exists('nammu_fediverse_repair_unresolved_announces')) {
            $stepStartedAt = microtime(true);
            $fediverseAnnounceRepairStats = nammu_fediverse_repair_unresolved_announces($config, 5);
            admin_cli_timing_log('scheduled', 'repair_unresolved_announces', $stepStartedAt, [
                'checked' => (int) ($fediverseAnnounceRepairStats['checked'] ?? 0),
                'repaired' => (int) ($fediverseAnnounceRepairStats['repaired'] ?? 0),
            ]);
        }
        if (function_exists('nammu_fediverse_warm_recent_threads_cache')) {
            $stepStartedAt = microtime(true);
            $fediverseRecentThreadsWarmed = (int) nammu_fediverse_warm_recent_threads_cache($config, 8);
            admin_cli_timing_log('scheduled', 'warm_recent_threads_cache', $stepStartedAt, [
                'warmed' => $fediverseRecentThreadsWarmed,
            ]);
        }
        if (function_exists('nammu_fediverse_rebuild_light_snapshots')) {
            $stepStartedAt = microtime(true);
            nammu_fediverse_rebuild_light_snapshots($config);
            admin_cli_timing_log('scheduled', 'rebuild_light_snapshots', $stepStartedAt);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            $stepStartedAt = microtime(true);
            nammu_fediverse_save_fragments_cache_store([]);
            admin_cli_timing_log('scheduled', 'clear_fragments_cache', $stepStartedAt);
        }
    }
    return [
        'published' => $published,
        'notifications_processed' => 0,
        'notifications_remaining' => 0,
        'social_rss_sent' => 0,
        'social_rss_checked' => 0,
        'social_broadcast_queue_processed' => 0,
        'social_broadcast_queue_sent' => 0,
        'social_broadcast_queue_failed' => 0,
        'social_broadcast_queue_remaining' => 0,
        'push_queue_sent' => 0,
        'push_queue_failed' => 0,
        'push_queue_skipped' => 1,
        'indexnow_queue_processed' => 0,
        'indexnow_queue_remaining' => 0,
        'public_artifacts_queue_processed' => 0,
        'public_artifacts_queue_remaining' => 0,
        'public_artifacts_queue_reasons' => 0,
        'fediverse_checked' => (int) ($fediverseStats['checked'] ?? 0),
        'fediverse_new' => (int) ($fediverseStats['new'] ?? 0) + (int) ($fediverseInboxSyncStats['new'] ?? 0),
        'fediverse_followers' => (int) ($scheduledDeliveryStats['followers'] ?? 0),
        'fediverse_followers_checked' => (int) ($fediverseStats['followers_checked'] ?? 0),
        'fediverse_followers_removed' => (int) ($fediverseStats['followers_removed'] ?? 0),
        'fediverse_delivered' => (int) ($scheduledDeliveryStats['delivered'] ?? 0),
        'fediverse_delivery_error' => (string) ($scheduledDeliveryStats['error'] ?? ''),
        'fediverse_delivery_save_failed' => !empty($scheduledDeliveryStats['save_failed']),
        'fediverse_recent_threads_warmed' => $fediverseRecentThreadsWarmed,
        'fediverse_follow_accepts_checked' => 0,
        'fediverse_follow_accepts_sent' => 0,
        'fediverse_follow_accepts_failed' => 0,
        'fediverse_delete_queue_processed' => 0,
        'fediverse_delete_queue_sent' => 0,
        'fediverse_delete_queue_failed' => 0,
        'fediverse_delete_queue_remaining' => 0,
    ];
}

function admin_run_scheduled_maintenance_tasks(): array {
    admin_maintenance_trace([
        'scope' => 'maintenance',
        'event' => 'enter',
    ]);
    $traceStep = static function (string $step, callable $callback) {
        $startedAt = microtime(true);
        admin_maintenance_trace([
            'scope' => 'maintenance',
            'event' => 'step_start',
            'step' => $step,
        ]);
        $result = $callback();
        admin_maintenance_trace([
            'scope' => 'maintenance',
            'event' => 'step_finish',
            'step' => $step,
            'duration_ms' => (int) round(max(0, microtime(true) - $startedAt) * 1000),
        ]);
        return $result;
    };
    $config = nammu_load_config();
    admin_maintenance_trace([
        'scope' => 'maintenance',
        'event' => 'after_load_config',
    ]);
    if (!function_exists('admin_process_social_rss_feeds') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
        require_once NAMMU_ROOT . '/core/admin-redes.php';
        admin_maintenance_trace([
            'scope' => 'maintenance',
            'event' => 'after_require_admin_redes',
        ]);
    }
    if (!function_exists('nammu_actuality_rebuild_snapshot') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
        require_once NAMMU_ROOT . '/core/actualidad.php';
        admin_maintenance_trace([
            'scope' => 'maintenance',
            'event' => 'after_require_actualidad',
        ]);
    }
    if (!function_exists('nammu_fediverse_rebuild_light_snapshots') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
        require_once NAMMU_ROOT . '/core/fediverso.php';
        admin_maintenance_trace([
            'scope' => 'maintenance',
            'event' => 'after_require_fediverso',
        ]);
    }
    $published = function_exists('nammu_publish_scheduled_posts')
        ? (int) $traceStep('publish_scheduled_posts', static function () {
            return nammu_publish_scheduled_posts(CONTENT_DIR);
        })
        : 0;
    $queueStats = function_exists('nammu_process_scheduled_notifications_queue')
        ? $traceStep('notifications_queue', static function () {
            return nammu_process_scheduled_notifications_queue();
        })
        : ['processed' => 0, 'remaining' => 0];
    $mailingBounceStats = function_exists('admin_process_mailing_bounces')
        ? $traceStep('mailing_bounces', static function () {
            return admin_process_mailing_bounces(12);
        })
        : ['scanned' => 0, 'suppressed' => 0];
    $rssStats = ['sent' => 0, 'checked' => 0];
    $linkCardRefreshStats = ['processed' => 0, 'updated' => 0, 'failed' => 0, 'remaining' => 0];
    $avatarCacheQueueStats = ['processed' => 0, 'updated' => 0, 'failed' => 0, 'remaining' => 0];
    $socialRssBroadcastQueueStats = ['queued' => 0, 'remaining' => 0];
    $socialBroadcastQueueStats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'remaining' => 0];
    $pushQueueStats = ['sent' => 0, 'failed' => 0, 'skipped' => true];
    $indexnowQueueStats = ['processed' => 0, 'remaining' => 0];
    $publicArtifactsQueueStats = ['processed' => 0, 'remaining' => 0, 'reasons' => 0];
    $webmentionSyncStats = ['scanned' => 0, 'enqueued' => 0, 'remaining' => 0];
    $webmentionQueueStats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'remaining' => 0];
    $fediverseDeleteQueueStats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'remaining' => 0];
    $fediverseUndoAnnounceQueueStats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'remaining' => 0];
    $fediverseAnnounceQueueStats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'remaining' => 0];
    $fediverseUpdateQueueStats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'remaining' => 0];
    $fediverseThreadRefreshStats = ['processed' => 0, 'updated' => 0, 'failed' => 0, 'remaining' => 0];
    $deliveryStats = ['followers' => 0, 'delivered' => 0];
    $acceptStats = ['checked' => 0, 'accepted' => 0, 'failed' => 0];
    $actualityChanged = false;
    $snapshotSignature = static function (array $snapshot): string {
        $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
        return sha1(json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]');
    };
    $actualityBefore = function_exists('nammu_actuality_load_items_snapshot')
        ? nammu_actuality_load_items_snapshot()
        : ['items' => []];
    $actualityBeforeSignature = $snapshotSignature($actualityBefore);

    $siteName = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
    $siteDescription = trim((string) (($config['site_description'] ?? '') ?: ''));
    $siteLang = trim((string) (($config['site_lang'] ?? '') ?: 'es'));
    $baseUrl = trim((string) ($config['site_url'] ?? ''));
    if ($baseUrl === '') {
        $baseUrl = nammu_base_url();
    }
    if (function_exists('admin_process_social_rss_feeds')) {
        $rssStats = $traceStep('social_rss_feeds', static function () {
            return admin_process_social_rss_feeds();
        });
    }
    if (function_exists('nammu_fediverse_refresh_link_card_queue')) {
        $linkCardRefreshStats = $traceStep('fediverse_link_card_refresh', static function () use ($config) {
            // Si el cron de link cards está procesando la cola, este paso se salta en vez de esperar.
            return admin_run_with_scheduled_lock(static function () use ($config): array {
                return nammu_fediverse_refresh_link_card_queue($config, 8, 259200);
            }, admin_fediverse_link_card_lock_file());
        });
    }
    if (function_exists('nammu_fediverse_process_avatar_cache_queue')) {
        $avatarCacheQueueStats = $traceStep('fediverse_avatar_cache_queue', static function () use ($config) {
            return nammu_fediverse_process_avatar_cache_queue($config, 3);
        });
    }
    $pendingSocialRssLinkCardsAwaitingAttempt = 0;
    if (function_exists('nammu_fediverse_link_card_queue_store')) {
        $linkCardQueueStore = nammu_fediverse_link_card_queue_store();
        foreach ((array) ($linkCardQueueStore['items'] ?? []) as $queueEntry) {
            if (!is_array($queueEntry)) {
                continue;
            }
            $reason = trim((string) ($queueEntry['reason'] ?? ''));
            if ($reason === 'social-rss-news' && (int) ($queueEntry['attempts'] ?? 0) <= 0) {
                $pendingSocialRssLinkCardsAwaitingAttempt++;
            }
        }
    }
    $shouldDeferActualityPublish = (int) ($rssStats['discovered'] ?? 0) > 0
        || $pendingSocialRssLinkCardsAwaitingAttempt > 0;
    if (!$shouldDeferActualityPublish && function_exists('nammu_actuality_rebuild_snapshot')) {
        $rebuiltActuality = $traceStep('actuality_rebuild_snapshot', static function () use ($baseUrl, $config, $siteName, $siteDescription, $siteLang) {
            return nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteName, $siteDescription, $siteLang);
        });
        $actualityChanged = $snapshotSignature(is_array($rebuiltActuality) ? $rebuiltActuality : ['items' => []]) !== $actualityBeforeSignature;
        if (function_exists('admin_social_rss_enqueue_pending_broadcasts')) {
            $socialRssBroadcastQueueStats = $traceStep('social_rss_enqueue_pending_broadcasts', static function () {
                return admin_social_rss_enqueue_pending_broadcasts(12);
            });
        }
    }
    if (function_exists('admin_process_social_broadcast_queue')) {
        $socialBroadcastMaxJobs = max(12, (int) ($socialRssBroadcastQueueStats['queued'] ?? 0));
        $socialBroadcastQueueStats = $traceStep('social_broadcast_queue', static function () use ($socialBroadcastMaxJobs) {
            return admin_process_social_broadcast_queue($socialBroadcastMaxJobs);
        });
    }
    if (function_exists('nammu_dispatch_push_queue')) {
        $pushQueueStats = $traceStep('push_queue', static function () {
            return nammu_dispatch_push_queue();
        });
    }
    if (function_exists('admin_process_indexnow_queue')) {
        $indexnowQueueStats = $traceStep('indexnow_queue', static function () {
            return admin_process_indexnow_queue(50);
        });
    }
    if (function_exists('admin_process_public_artifacts_refresh_queue')) {
        $publicArtifactsQueueStats = $traceStep('public_artifacts_refresh_queue', static function () {
            return admin_process_public_artifacts_refresh_queue();
        });
    }
    if (function_exists('nammu_webmention_sync_sources')) {
        $webmentionSyncStats = $traceStep('webmention_sync_sources', static function () use ($config) {
            return nammu_webmention_sync_sources($config, 4);
        });
    }
    if (function_exists('nammu_webmention_process_queue')) {
        $webmentionQueueStats = $traceStep('webmention_process_queue', static function () use ($config) {
            return nammu_webmention_process_queue($config, 4);
        });
    }
    if (function_exists('nammu_fediverse_process_delete_queue')) {
        $fediverseDeleteQueueStats = $traceStep('fediverse_delete_queue', static function () use ($config) {
            return nammu_fediverse_process_delete_queue($config, 2);
        });
    }
    if (function_exists('nammu_fediverse_process_announce_queue')) {
        $fediverseAnnounceQueueStats = $traceStep('fediverse_announce_queue', static function () use ($config) {
            return nammu_fediverse_process_announce_queue($config, 2);
        });
    }
    if (function_exists('nammu_fediverse_process_undo_announce_queue')) {
        $fediverseUndoAnnounceQueueStats = $traceStep('fediverse_undo_announce_queue', static function () use ($config) {
            return nammu_fediverse_process_undo_announce_queue($config, 2);
        });
    }
    if (function_exists('nammu_fediverse_process_update_queue')) {
        $fediverseUpdateQueueStats = $traceStep('fediverse_update_queue', static function () use ($config) {
            return nammu_fediverse_process_update_queue($config, 3);
        });
    }
    if (function_exists('nammu_fediverse_process_thread_refresh_queue')) {
        $fediverseThreadRefreshStats = $traceStep('fediverse_thread_refresh_queue', static function () use ($config) {
            return nammu_fediverse_process_thread_refresh_queue($config, 3);
        });
    }
    if (function_exists('nammu_fediverse_retry_pending_follower_accepts')) {
        $acceptStats = $traceStep('fediverse_retry_pending_follower_accepts', static function () use ($config) {
            return nammu_fediverse_retry_pending_follower_accepts($config);
        });
    }
    if (function_exists('nammu_fediverse_deliver_local_items')) {
        $deliveryStats = $traceStep('fediverse_deliver_local_items', static function () use ($config) {
            return nammu_fediverse_deliver_local_items($config);
        });
    }
    if (
        $published > 0
        || $actualityChanged
        || (int) ($rssStats['sent'] ?? 0) > 0
        || (int) ($deliveryStats['delivered'] ?? 0) > 0
    ) {
        if (function_exists('nammu_fediverse_rebuild_light_snapshots')) {
            $traceStep('fediverse_rebuild_light_snapshots', static function () use ($config) {
                nammu_fediverse_rebuild_light_snapshots($config);
                return null;
            });
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            $traceStep('fediverse_clear_fragments_cache', static function () {
                nammu_fediverse_save_fragments_cache_store([]);
                return null;
            });
        }
    }

    return [
        'published' => $published,
        'notifications_processed' => (int) ($queueStats['processed'] ?? 0),
        'notifications_remaining' => (int) ($queueStats['remaining'] ?? 0),
        'notifications_locked' => !empty($queueStats['locked']) ? 1 : 0,
        'mailing_bounces_scanned' => (int) ($mailingBounceStats['scanned'] ?? 0),
        'mailing_bounces_suppressed' => (int) ($mailingBounceStats['suppressed'] ?? 0),
        'actuality_changed' => $actualityChanged ? 1 : 0,
        'social_rss_sent' => (int) ($rssStats['sent'] ?? 0),
        'social_rss_checked' => (int) ($rssStats['checked'] ?? 0),
        'social_broadcast_queue_processed' => (int) ($socialBroadcastQueueStats['processed'] ?? 0),
        'social_broadcast_queue_sent' => (int) ($socialBroadcastQueueStats['sent'] ?? 0),
        'social_broadcast_queue_failed' => (int) ($socialBroadcastQueueStats['failed'] ?? 0),
        'social_broadcast_queue_remaining' => (int) ($socialBroadcastQueueStats['remaining'] ?? 0),
        'push_queue_sent' => (int) ($pushQueueStats['sent'] ?? 0),
        'push_queue_failed' => (int) ($pushQueueStats['failed'] ?? 0),
        'push_queue_skipped' => !empty($pushQueueStats['skipped']) ? 1 : 0,
        'indexnow_queue_processed' => (int) ($indexnowQueueStats['processed'] ?? 0),
        'indexnow_queue_remaining' => (int) ($indexnowQueueStats['remaining'] ?? 0),
        'public_artifacts_queue_processed' => (int) ($publicArtifactsQueueStats['processed'] ?? 0),
        'public_artifacts_queue_remaining' => (int) ($publicArtifactsQueueStats['remaining'] ?? 0),
        'public_artifacts_queue_reasons' => (int) ($publicArtifactsQueueStats['reasons'] ?? 0),
        'webmention_scanned' => (int) ($webmentionSyncStats['scanned'] ?? 0),
        'webmention_enqueued' => (int) ($webmentionSyncStats['enqueued'] ?? 0),
        'webmention_queue_processed' => (int) ($webmentionQueueStats['processed'] ?? 0),
        'webmention_queue_sent' => (int) ($webmentionQueueStats['sent'] ?? 0),
        'webmention_queue_failed' => (int) ($webmentionQueueStats['failed'] ?? 0),
        'webmention_queue_remaining' => (int) (($webmentionQueueStats['remaining'] ?? 0) ?: ($webmentionSyncStats['remaining'] ?? 0)),
        'fediverse_checked' => 0,
        'fediverse_new' => 0,
        'fediverse_followers' => (int) ($deliveryStats['followers'] ?? 0),
        'fediverse_followers_checked' => 0,
        'fediverse_followers_removed' => 0,
        'fediverse_delivered' => (int) ($deliveryStats['delivered'] ?? 0),
        'fediverse_delivery_error' => (string) ($deliveryStats['error'] ?? ''),
        'fediverse_delivery_save_failed' => !empty($deliveryStats['save_failed']),
        'fediverse_avatar_cache_processed' => (int) ($avatarCacheQueueStats['processed'] ?? 0),
        'fediverse_avatar_cache_updated' => (int) ($avatarCacheQueueStats['updated'] ?? 0),
        'fediverse_avatar_cache_failed' => (int) ($avatarCacheQueueStats['failed'] ?? 0),
        'fediverse_avatar_cache_remaining' => (int) ($avatarCacheQueueStats['remaining'] ?? 0),
        'fediverse_recent_threads_warmed' => 0,
        'fediverse_follow_accepts_checked' => (int) ($acceptStats['checked'] ?? 0),
        'fediverse_follow_accepts_sent' => (int) ($acceptStats['accepted'] ?? 0),
        'fediverse_follow_accepts_failed' => (int) ($acceptStats['failed'] ?? 0),
        'fediverse_update_queue_processed' => (int) ($fediverseUpdateQueueStats['processed'] ?? 0),
        'fediverse_update_queue_sent' => (int) ($fediverseUpdateQueueStats['sent'] ?? 0),
        'fediverse_update_queue_failed' => (int) ($fediverseUpdateQueueStats['failed'] ?? 0),
        'fediverse_update_queue_remaining' => (int) ($fediverseUpdateQueueStats['remaining'] ?? 0),
        'fediverse_thread_refresh_processed' => (int) ($fediverseThreadRefreshStats['processed'] ?? 0),
        'fediverse_thread_refresh_updated' => (int) ($fediverseThreadRefreshStats['updated'] ?? 0),
        'fediverse_thread_refresh_failed' => (int) ($fediverseThreadRefreshStats['failed'] ?? 0),
        'fediverse_thread_refresh_remaining' => (int) ($fediverseThreadRefreshStats['remaining'] ?? 0),
        'fediverse_delete_queue_processed' => (int) ($fediverseDeleteQueueStats['processed'] ?? 0) + (int) ($fediverseUndoAnnounceQueueStats['processed'] ?? 0) + (int) ($fediverseAnnounceQueueStats['processed'] ?? 0) + (int) ($fediverseUpdateQueueStats['processed'] ?? 0),
        'fediverse_delete_queue_sent' => (int) ($fediverseDeleteQueueStats['sent'] ?? 0) + (int) ($fediverseUndoAnnounceQueueStats['sent'] ?? 0) + (int) ($fediverseAnnounceQueueStats['sent'] ?? 0) + (int) ($fediverseUpdateQueueStats['sent'] ?? 0),
        'fediverse_delete_queue_failed' => (int) ($fediverseDeleteQueueStats['failed'] ?? 0) + (int) ($fediverseUndoAnnounceQueueStats['failed'] ?? 0) + (int) ($fediverseAnnounceQueueStats['failed'] ?? 0) + (int) ($fediverseUpdateQueueStats['failed'] ?? 0),
        'fediverse_delete_queue_remaining' => (int) ($fediverseDeleteQueueStats['remaining'] ?? 0) + (int) ($fediverseUndoAnnounceQueueStats['remaining'] ?? 0) + (int) ($fediverseAnnounceQueueStats['remaining'] ?? 0) + (int) ($fediverseUpdateQueueStats['remaining'] ?? 0),
    ];
}

function admin_run_scheduled_heavy_tasks(): array {
    admin_heavy_trace([
        'scope' => 'heavy',
        'event' => 'enter',
    ]);
    $traceStep = static function (string $step, callable $callback) {
        $startedAt = microtime(true);
        admin_heavy_trace([
            'scope' => 'heavy',
            'event' => 'step_start',
            'step' => $step,
        ]);
        $result = $callback();
        admin_heavy_trace([
            'scope' => 'heavy',
            'event' => 'step_finish',
            'step' => $step,
            'duration_ms' => (int) round(max(0, microtime(true) - $startedAt) * 1000),
        ]);
        return $result;
    };
    $config = nammu_load_config();
    admin_heavy_trace([
        'scope' => 'heavy',
        'event' => 'after_load_config',
    ]);
    if (!function_exists('nammu_actuality_rebuild_snapshot') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
        require_once NAMMU_ROOT . '/core/actualidad.php';
        admin_heavy_trace([
            'scope' => 'heavy',
            'event' => 'after_require_actualidad',
        ]);
    }
    if (!function_exists('nammu_fediverse_warm_threads_cache') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
        require_once NAMMU_ROOT . '/core/fediverso.php';
        admin_heavy_trace([
            'scope' => 'heavy',
            'event' => 'after_require_fediverso',
        ]);
    }
    $siteName = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
    $siteDescription = trim((string) (($config['site_description'] ?? '') ?: ''));
    $siteLang = trim((string) (($config['site_lang'] ?? '') ?: 'es'));
    $baseUrl = trim((string) ($config['site_url'] ?? ''));
    if ($baseUrl === '') {
        $baseUrl = nammu_base_url();
    }
    if (function_exists('nammu_actuality_rebuild_snapshot')) {
        $stepStartedAt = microtime(true);
        $traceStep('actuality_rebuild_snapshot', static function () use ($baseUrl, $config, $siteName, $siteDescription, $siteLang) {
            nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteName, $siteDescription, $siteLang);
            return null;
        });
        admin_cli_timing_log('heavy', 'actuality_rebuild_snapshot', $stepStartedAt);
    }
    $threadsWarmed = 0;
    if (function_exists('nammu_fediverse_warm_threads_cache')) {
        $stepStartedAt = microtime(true);
        $threadsWarmed = (int) $traceStep('warm_threads_cache', static function () use ($config) {
            return nammu_fediverse_warm_threads_cache($config, 20);
        });
        admin_cli_timing_log('heavy', 'warm_threads_cache', $stepStartedAt, [
            'warmed' => $threadsWarmed,
        ]);
    }
    if (function_exists('nammu_fediverse_rebuild_snapshots')) {
        $stepStartedAt = microtime(true);
        $traceStep('rebuild_snapshots', static function () use ($config) {
            nammu_fediverse_rebuild_snapshots($config);
            return null;
        });
        admin_cli_timing_log('heavy', 'rebuild_snapshots', $stepStartedAt);
    }
    if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
        $stepStartedAt = microtime(true);
        $traceStep('clear_fragments_cache', static function () {
            nammu_fediverse_save_fragments_cache_store([]);
            return null;
        });
        admin_cli_timing_log('heavy', 'clear_fragments_cache', $stepStartedAt);
    }
    return [
        'actuality_rebuilt' => 1,
        'fediverse_threads_warmed' => $threadsWarmed,
        'fediverse_snapshots_rebuilt' => 1,
    ];
}

function admin_run_fediverse_link_card_refresh_tasks(): array
{
    $config = nammu_load_config();
    if (!function_exists('nammu_fediverse_refresh_link_card_queue') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
        require_once NAMMU_ROOT . '/core/fediverso.php';
    }
    if (!function_exists('nammu_fediverse_refresh_link_card_queue')) {
        return ['processed' => 0, 'updated' => 0, 'failed' => 0, 'remaining' => 0, 'skipped' => 1];
    }
    $stats = nammu_fediverse_refresh_link_card_queue($config, 8, 259200);
    $stats['skipped'] = 0;
    return $stats;
}

function admin_refresh_fediverse_threads(array $config, int $limit = 20): array
{
    if (!function_exists('admin_process_social_broadcast_queue') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
        require_once NAMMU_ROOT . '/core/admin-redes.php';
    }
    if (!function_exists('nammu_fediverse_warm_threads_cache') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
        require_once NAMMU_ROOT . '/core/fediverso.php';
    }
    $stats = [
        'threads_warmed' => 0,
        'follow_accepts_checked' => 0,
        'follow_accepts_sent' => 0,
        'social_broadcast_queue_processed' => 0,
        'social_broadcast_queue_sent' => 0,
        'social_broadcast_queue_failed' => 0,
        'social_broadcast_queue_remaining' => 0,
    ];
    if (function_exists('admin_process_social_broadcast_queue')) {
        $queueStats = admin_process_social_broadcast_queue(3);
        $stats['social_broadcast_queue_processed'] = (int) ($queueStats['processed'] ?? 0);
        $stats['social_broadcast_queue_sent'] = (int) ($queueStats['sent'] ?? 0);
        $stats['social_broadcast_queue_failed'] = (int) ($queueStats['failed'] ?? 0);
        $stats['social_broadcast_queue_remaining'] = (int) ($queueStats['remaining'] ?? 0);
    }
    if (function_exists('nammu_fediverse_retry_pending_follower_accepts')) {
        $acceptStats = nammu_fediverse_retry_pending_follower_accepts($config);
        $stats['follow_accepts_checked'] = (int) ($acceptStats['checked'] ?? 0);
        $stats['follow_accepts_sent'] = (int) ($acceptStats['accepted'] ?? 0);
    }
    if (function_exists('nammu_fediverse_warm_threads_cache')) {
        $stats['threads_warmed'] = (int) nammu_fediverse_warm_threads_cache($config, $limit);
    }
    if (function_exists('nammu_fediverse_rebuild_light_snapshots')) {
        nammu_fediverse_rebuild_light_snapshots($config);
    }
    if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
        nammu_fediverse_save_fragments_cache_store([]);
    }
    return $stats;
}

function admin_rebuild_fediverse_timeline(array $config): array
{
    if (!function_exists('nammu_fediverse_rebuild_timeline') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
        require_once NAMMU_ROOT . '/core/fediverso.php';
    }
    $stats = function_exists('nammu_fediverse_rebuild_timeline')
        ? nammu_fediverse_rebuild_timeline()
        : ['checked' => 0, 'new' => 0];
    if (function_exists('nammu_fediverse_sync_recent_followed_inbox_items')) {
        $inboxStats = nammu_fediverse_sync_recent_followed_inbox_items($config, 8, 400);
        $stats['fediverse_inbox_sync_scanned'] = (int) ($inboxStats['scanned'] ?? 0);
        $stats['fediverse_inbox_sync_new'] = (int) ($inboxStats['new'] ?? 0);
    }
    if (function_exists('nammu_fediverse_repair_unresolved_announces')) {
        $repairStats = nammu_fediverse_repair_unresolved_announces($config, 20);
        $stats['fediverse_announces_repaired'] = (int) ($repairStats['repaired'] ?? 0);
    }
    $threadStats = admin_refresh_fediverse_threads($config, 20);
    $stats['threads_warmed'] = (int) ($threadStats['threads_warmed'] ?? 0);
    $stats['follow_accepts_checked'] = (int) ($threadStats['follow_accepts_checked'] ?? 0);
    $stats['follow_accepts_sent'] = (int) ($threadStats['follow_accepts_sent'] ?? 0);
    return $stats;
}

function admin_multi_instance_settings(array $config): array
{
    $settings = is_array($config['multi_instance'] ?? null) ? $config['multi_instance'] : [];
    $normalized = [
        'enabled' => (($settings['enabled'] ?? 'off') === 'on') ? 'on' : 'off',
        'cluster' => trim((string) ($settings['cluster'] ?? '')),
        'shared_cache_dir' => trim((string) ($settings['shared_cache_dir'] ?? '')),
        'shared_queue_dir' => trim((string) ($settings['shared_queue_dir'] ?? '')),
        'instances_root_dir' => trim((string) ($settings['instances_root_dir'] ?? '')),
        'scheduler_mode' => trim((string) ($settings['scheduler_mode'] ?? 'standalone')),
        'scheduler_strategy' => trim((string) ($settings['scheduler_strategy'] ?? 'fixed')),
    ];
    if (!in_array($normalized['scheduler_mode'], ['standalone', 'central'], true)) {
        $normalized['scheduler_mode'] = 'standalone';
    }
    if (!in_array($normalized['scheduler_strategy'], ['fixed', 'activity'], true)) {
        $normalized['scheduler_strategy'] = 'fixed';
    }
    return $normalized;
}

function admin_multi_instance_instances_root_dir(array $config): string
{
    $settings = admin_multi_instance_settings($config);
    $configured = trim((string) ($settings['instances_root_dir'] ?? ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    return rtrim(dirname(NAMMU_ROOT), '/');
}

function admin_multi_instance_shared_queue_dir(array $config): string
{
    $settings = admin_multi_instance_settings($config);
    if (($settings['enabled'] ?? 'off') !== 'on') {
        return '';
    }
    $configured = trim((string) ($settings['shared_queue_dir'] ?? ''));
    if ($configured === '') {
        return '';
    }
    return rtrim($configured, '/');
}

function admin_multi_instance_cluster_name(array $config): string
{
    $settings = admin_multi_instance_settings($config);
    return trim((string) ($settings['cluster'] ?? ''));
}

function admin_multi_instance_load_config_at(string $siteDir): array
{
    $configFile = rtrim($siteDir, '/') . '/config/config.yml';
    if (!is_file($configFile)) {
        return [];
    }
    $raw = @file_get_contents($configFile);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    if (class_exists(Yaml::class)) {
        try {
            $parsed = Yaml::parse($raw);
            return is_array($parsed) ? $parsed : [];
        } catch (Throwable $e) {
        }
    }
    $parsed = simple_yaml_parse($raw);
    return is_array($parsed) ? $parsed : [];
}

function admin_multi_instance_discover_cluster_sites(array $config): array
{
    $settings = admin_multi_instance_settings($config);
    $cluster = trim((string) ($settings['cluster'] ?? ''));
    $rootDir = admin_multi_instance_instances_root_dir($config);
    $sharedQueueDir = admin_multi_instance_shared_queue_dir($config);
    if (($settings['enabled'] ?? 'off') !== 'on' || ($settings['scheduler_mode'] ?? 'standalone') !== 'central' || $cluster === '' || $sharedQueueDir === '' || !is_dir($rootDir)) {
        return [];
    }
    $items = [];
    foreach (glob($rootDir . '/*', GLOB_ONLYDIR) ?: [] as $siteDir) {
        $siteDir = rtrim((string) $siteDir, '/');
        $siteConfig = admin_multi_instance_load_config_at($siteDir);
        if (empty($siteConfig)) {
            continue;
        }
        $siteMulti = admin_multi_instance_settings($siteConfig);
        if (($siteMulti['enabled'] ?? 'off') !== 'on' || ($siteMulti['scheduler_mode'] ?? 'standalone') !== 'central') {
            continue;
        }
        if (trim((string) ($siteMulti['cluster'] ?? '')) !== $cluster) {
            continue;
        }
        $siteSharedQueueDir = admin_multi_instance_shared_queue_dir($siteConfig);
        if ($siteSharedQueueDir === '' || rtrim($siteSharedQueueDir, '/') !== rtrim($sharedQueueDir, '/')) {
            continue;
        }
        $adminFile = $siteDir . '/admin.php';
        if (!is_file($adminFile)) {
            continue;
        }
        $items[] = [
            'site_dir' => $siteDir,
            'admin_file' => $adminFile,
            'site_name' => trim((string) ($siteConfig['site_name'] ?? basename($siteDir))),
            'site_url' => trim((string) ($siteConfig['site_url'] ?? '')),
            'slug' => basename($siteDir),
            'last_local_activity_at' => admin_multi_instance_site_last_local_activity_at($siteDir),
        ];
    }
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($a['site_dir'] ?? ''), (string) ($b['site_dir'] ?? ''));
    });
    return $items;
}

function admin_multi_instance_scheduler_state_file(array $config): string
{
    $queueDir = admin_multi_instance_shared_queue_dir($config);
    $cluster = preg_replace('/[^a-z0-9._-]+/i', '-', admin_multi_instance_cluster_name($config)) ?? '';
    if ($queueDir === '' || $cluster === '') {
        return '';
    }
    return $queueDir . '/scheduler-' . strtolower($cluster) . '.json';
}

function admin_multi_instance_scheduler_lock_file(array $config): string
{
    $queueDir = admin_multi_instance_shared_queue_dir($config);
    $cluster = preg_replace('/[^a-z0-9._-]+/i', '-', admin_multi_instance_cluster_name($config)) ?? '';
    if ($queueDir === '' || $cluster === '') {
        return '';
    }
    return $queueDir . '/scheduler-' . strtolower($cluster) . '.lock';
}

function admin_multi_instance_trace_file(array $config): string
{
    $backupDir = nammu_backup_dir($config, NAMMU_ROOT);
    if (!is_dir($backupDir)) {
        nammu_ensure_directory($backupDir);
    }
    return $backupDir . '/cluster-trace.log';
}

function admin_multi_instance_trace(array $config, array $entry): void
{
    if (PHP_SAPI !== 'cli' || !admin_cli_debug_enabled()) {
        return;
    }
    $file = admin_multi_instance_trace_file($config);
    $entry['at'] = date(DATE_ATOM);
    @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function admin_multi_instance_scheduler_state_load(array $config): array
{
    $file = admin_multi_instance_scheduler_state_file($config);
    if ($file === '' || !is_file($file)) {
        return ['runs' => []];
    }
    $raw = @file_get_contents($file);
    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return ['runs' => []];
    }
    $decoded['runs'] = is_array($decoded['runs'] ?? null) ? $decoded['runs'] : [];
    return $decoded;
}

function admin_multi_instance_scheduler_state_save(array $config, array $state): void
{
    $file = admin_multi_instance_scheduler_state_file($config);
    if ($file === '') {
        return;
    }
    $dir = dirname($file);
    nammu_ensure_directory($dir);
    $payload = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($payload)) {
        nammu_atomic_write_file($file, $payload);
    }
}

function admin_multi_instance_site_last_local_activity_at(string $siteDir): int
{
    $timestamps = [];
    $siteDir = rtrim($siteDir, '/');

    $publishedContentFiles = glob($siteDir . '/content/*.md') ?: [];
    foreach ($publishedContentFiles as $path) {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        $metadata = parse_yaml_front_matter($raw);
        $status = strtolower(trim((string) ($metadata['Status'] ?? $metadata['status'] ?? 'published')));
        if ($status === 'draft') {
            continue;
        }
        if (($time = @filemtime($path)) !== false) {
            $timestamps[] = (int) $time;
        }
    }

    foreach ([
        $siteDir . '/config/actualidad-manual.json',
        $siteDir . '/config/actualidad-items.json',
        $siteDir . '/config/actualidad-news-store.json',
    ] as $path) {
        if (($time = @filemtime($path)) !== false) {
            $timestamps[] = (int) $time;
        }
    }
    return empty($timestamps) ? 0 : max($timestamps);
}

function admin_multi_instance_site_activity_profile(array $site, int $now): array
{
    $lastActivityAt = (int) ($site['last_local_activity_at'] ?? 0);
    if ($lastActivityAt <= 0) {
        return [
            'name' => 'idle',
            'priority' => 100,
            'light_interval' => 3600,
            'maintenance_interval' => 10800,
            'heavy_interval' => 43200,
        ];
    }
    $age = max(0, $now - $lastActivityAt);
    if ($age <= 129600) {
        return [
            'name' => 'fresh',
            'priority' => 300,
            'light_interval' => 600,
            'maintenance_interval' => 1800,
            'heavy_interval' => 3600,
        ];
    }
    if ($age <= 604800) {
        return [
            'name' => 'warm',
            'priority' => 200,
            'light_interval' => 1200,
            'maintenance_interval' => 3600,
            'heavy_interval' => 10800,
        ];
    }
    return [
        'name' => 'idle',
        'priority' => 100,
        'light_interval' => 3600,
        'maintenance_interval' => 10800,
        'heavy_interval' => 43200,
    ];
}

function admin_multi_instance_phase_last_run_timestamp(array $state, string $siteKey, string $phase): int
{
    $siteState = is_array($state['runs'][$siteKey] ?? null) ? $state['runs'][$siteKey] : [];
    return (int) ($siteState[$phase . '_last_at'] ?? 0);
}

function admin_multi_instance_phase_interval_activity(string $phase, array $site, int $now): int
{
    $profile = admin_multi_instance_site_activity_profile($site, $now);
    if ($phase === 'light') {
        return (int) ($profile['light_interval'] ?? 3600);
    }
    if ($phase === 'maintenance') {
        return (int) ($profile['maintenance_interval'] ?? 10800);
    }
    if ($phase === 'heavy') {
        return (int) ($profile['heavy_interval'] ?? 43200);
    }
    return 3600;
}

function admin_multi_instance_phase_due_activity(string $phase, array $site, array $state, string $siteKey, int $now): bool
{
    $interval = admin_multi_instance_phase_interval_activity($phase, $site, $now);
    $lastRunAt = admin_multi_instance_phase_last_run_timestamp($state, $siteKey, $phase);
    if ($lastRunAt <= 0) {
        return true;
    }
    return ($now - $lastRunAt) >= $interval;
}

function admin_multi_instance_pick_due_site_activity(string $phase, array $sites, array $state, int $now): ?array
{
    $eligible = [];
    foreach ($sites as $site) {
        $siteKey = (string) ($site['site_dir'] ?? '');
        if ($siteKey === '' || !admin_multi_instance_phase_due_activity($phase, $site, $state, $siteKey, $now)) {
            continue;
        }
        $profile = admin_multi_instance_site_activity_profile($site, $now);
        $lastRunAt = admin_multi_instance_phase_last_run_timestamp($state, $siteKey, $phase);
        $eligible[] = [
            'site' => $site,
            'site_key' => $siteKey,
            'profile' => $profile,
            'last_run_at' => $lastRunAt,
            'last_local_activity_at' => (int) ($site['last_local_activity_at'] ?? 0),
        ];
    }
    if (empty($eligible)) {
        return null;
    }
    usort($eligible, static function (array $a, array $b): int {
        $priorityCompare = ((int) ($b['profile']['priority'] ?? 0)) <=> ((int) ($a['profile']['priority'] ?? 0));
        if ($priorityCompare !== 0) {
            return $priorityCompare;
        }
        $runCompare = ((int) ($a['last_run_at'] ?? 0)) <=> ((int) ($b['last_run_at'] ?? 0));
        if ($runCompare !== 0) {
            return $runCompare;
        }
        $activityCompare = ((int) ($b['last_local_activity_at'] ?? 0)) <=> ((int) ($a['last_local_activity_at'] ?? 0));
        if ($activityCompare !== 0) {
            return $activityCompare;
        }
        return strcmp((string) (($a['site']['site_dir'] ?? '')), (string) (($b['site']['site_dir'] ?? '')));
    });
    return $eligible[0];
}

function admin_multi_instance_due_slots(int $index): array
{
    return [
        'light' => $index % 10,
        'maintenance' => (12 + ($index * 2)) % 30,
        'heavy' => (13 + ($index * 10)) % 60,
    ];
}

function admin_multi_instance_phase_due(string $phase, int $index, array $state, string $siteKey, int $now): bool
{
    $minute = (int) date('i', $now);
    $hourKey = date('Y-m-d-H', $now);
    $slotKey = date('Y-m-d-H:i', $now);
    $slots = admin_multi_instance_due_slots($index);
    $siteState = is_array($state['runs'][$siteKey] ?? null) ? $state['runs'][$siteKey] : [];
    if ($phase === 'light') {
        if (($minute % 10) !== (int) $slots['light']) {
            return false;
        }
        return trim((string) ($siteState['light_slot'] ?? '')) !== $slotKey;
    }
    if ($phase === 'maintenance') {
        if (($minute % 30) !== (int) $slots['maintenance']) {
            return false;
        }
        return trim((string) ($siteState['maintenance_slot'] ?? '')) !== $slotKey;
    }
    if ($phase === 'heavy') {
        if ($minute !== (int) $slots['heavy']) {
            return false;
        }
        return trim((string) ($siteState['heavy_hour'] ?? '')) !== $hourKey;
    }
    return false;
}

function admin_multi_instance_mark_phase_run(array &$state, string $siteKey, string $phase, int $now): void
{
    if (!isset($state['runs']) || !is_array($state['runs'])) {
        $state['runs'] = [];
    }
    if (!isset($state['runs'][$siteKey]) || !is_array($state['runs'][$siteKey])) {
        $state['runs'][$siteKey] = [];
    }
    if ($phase === 'heavy') {
        $state['runs'][$siteKey]['heavy_hour'] = date('Y-m-d-H', $now);
    } else {
        $state['runs'][$siteKey][$phase . '_slot'] = date('Y-m-d-H:i', $now);
    }
    $state['runs'][$siteKey][$phase . '_last_at'] = $now;
}

function admin_multi_instance_run_completed(array $run): bool
{
    if ((int) ($run['exit_code'] ?? 1) !== 0) {
        return false;
    }
    $result = is_array($run['result'] ?? null) ? $run['result'] : null;
    if (is_array($result) && (int) ($result['skipped'] ?? 0) > 0) {
        return false;
    }
    return true;
}

function admin_multi_instance_run_consumed_slot(array $run): bool
{
    if (admin_multi_instance_run_completed($run)) {
        return true;
    }
    $result = is_array($run['result'] ?? null) ? $run['result'] : null;
    $reason = is_array($result) ? trim((string) ($result['reason'] ?? '')) : '';
    return $reason === 'already_running';
}

function admin_multi_instance_run_site_phase(string $adminFile, string $phase): array
{
    $map = [
        'light' => '--run-scheduled',
        'maintenance' => '--run-scheduled-maintenance',
        'heavy' => '--run-scheduled-heavy',
    ];
    $flag = $map[$phase] ?? '';
    if ($flag === '') {
        return ['ok' => false, 'phase' => $phase, 'reason' => 'invalid_phase'];
    }
    $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($adminFile) . ' ' . $flag . ' 2>&1';
    $startedAt = microtime(true);
    exec($cmd, $output, $code);
    $finishedAt = microtime(true);
    $raw = trim(implode("\n", $output));
    $decoded = json_decode($raw, true);
    return [
        'ok' => $code === 0,
        'phase' => $phase,
        'exit_code' => $code,
        'started_at' => gmdate(DATE_ATOM, (int) $startedAt),
        'finished_at' => gmdate(DATE_ATOM, (int) $finishedAt),
        'duration_ms' => (int) round(max(0, $finishedAt - $startedAt) * 1000),
        'result' => is_array($decoded) ? $decoded : null,
        'raw' => $raw,
    ];
}

function admin_run_cluster_scheduled_tasks(): array
{
    $clusterStartedAt = microtime(true);
    $clusterBudgetMs = 45000;
    $phaseMinimumRemainingMs = [
        'light' => 10000,
        'maintenance' => 25000,
        'heavy' => 30000,
    ];
    $config = nammu_load_config();
    $settings = admin_multi_instance_settings($config);
    if (($settings['enabled'] ?? 'off') !== 'on') {
        return ['ok' => true, 'skipped' => 1, 'reason' => 'multi_instance_disabled'];
    }
    if (($settings['scheduler_mode'] ?? 'standalone') !== 'central') {
        return ['ok' => true, 'skipped' => 1, 'reason' => 'scheduler_mode_not_central'];
    }
    $queueDir = admin_multi_instance_shared_queue_dir($config);
    if ($queueDir === '') {
        return ['ok' => true, 'skipped' => 1, 'reason' => 'shared_queue_dir_missing'];
    }
    if (!is_dir($queueDir)) {
        nammu_ensure_directory($queueDir);
    }
    $lockFile = admin_multi_instance_scheduler_lock_file($config);
    if ($lockFile === '') {
        return ['ok' => true, 'skipped' => 1, 'reason' => 'cluster_lock_unavailable'];
    }
    $lockHandle = @fopen($lockFile, 'c+');
    if (!is_resource($lockHandle)) {
        return ['ok' => true, 'skipped' => 1, 'reason' => 'cluster_lock_unavailable'];
    }
    if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
        fclose($lockHandle);
        return ['ok' => true, 'skipped' => 1, 'reason' => 'cluster_already_running'];
    }
    try {
        $sites = admin_multi_instance_discover_cluster_sites($config);
        if (empty($sites)) {
            return ['ok' => true, 'skipped' => 1, 'reason' => 'no_cluster_sites'];
        }
        $state = admin_multi_instance_scheduler_state_load($config);
        $strategy = trim((string) ($settings['scheduler_strategy'] ?? 'fixed'));
        $now = time();
        $state['last_runner'] = [
            'site_dir' => NAMMU_ROOT,
            'slug' => basename(NAMMU_ROOT),
            'site_name' => trim((string) ($config['site_name'] ?? basename(NAMMU_ROOT))),
            'site_url' => trim((string) ($config['site_url'] ?? '')),
            'ran_at' => gmdate(DATE_ATOM, $now),
            'timestamp' => $now,
        ];
        $runs = [];
        if ($strategy === 'activity') {
            foreach (['maintenance', 'light', 'heavy'] as $phase) {
                $elapsedMs = (int) round(max(0, microtime(true) - $clusterStartedAt) * 1000);
                if ($elapsedMs >= $clusterBudgetMs) {
                    admin_multi_instance_trace($config, [
                        'scope' => 'cluster',
                        'event' => 'phase_skip',
                        'strategy' => 'activity',
                        'phase' => $phase,
                        'reason' => 'cluster_time_budget_exhausted',
                        'elapsed_ms' => $elapsedMs,
                    ]);
                    break;
                }
                $remainingMs = $clusterBudgetMs - $elapsedMs;
                $minimumRemainingMs = (int) ($phaseMinimumRemainingMs[$phase] ?? 0);
                if ($remainingMs < $minimumRemainingMs) {
                    admin_multi_instance_trace($config, [
                        'scope' => 'cluster',
                        'event' => 'phase_skip',
                        'strategy' => 'activity',
                        'phase' => $phase,
                        'reason' => 'insufficient_time_remaining',
                        'elapsed_ms' => $elapsedMs,
                        'remaining_ms' => $remainingMs,
                        'required_remaining_ms' => $minimumRemainingMs,
                    ]);
                    continue;
                }
                $pick = admin_multi_instance_pick_due_site_activity($phase, $sites, $state, $now);
                if (!is_array($pick)) {
                    admin_multi_instance_trace($config, [
                        'scope' => 'cluster',
                        'event' => 'phase_skip',
                        'strategy' => 'activity',
                        'phase' => $phase,
                        'reason' => 'no_due_site',
                    ]);
                    continue;
                }
                $site = is_array($pick['site'] ?? null) ? $pick['site'] : [];
                $siteKey = (string) ($pick['site_key'] ?? ($site['site_dir'] ?? ''));
                if ($siteKey === '' || empty($site['admin_file'])) {
                    admin_multi_instance_trace($config, [
                        'scope' => 'cluster',
                        'event' => 'phase_skip',
                        'strategy' => 'activity',
                        'phase' => $phase,
                        'reason' => 'invalid_pick',
                        'site' => $site['slug'] ?? basename((string) ($site['site_dir'] ?? '')),
                    ]);
                    continue;
                }
                admin_multi_instance_trace($config, [
                    'scope' => 'cluster',
                    'event' => 'phase_start',
                    'strategy' => 'activity',
                    'phase' => $phase,
                    'site' => $site['slug'] ?? basename((string) ($site['site_dir'] ?? '')),
                    'activity_profile' => (string) (($pick['profile']['name'] ?? 'idle')),
                    'last_local_activity_at' => (int) ($site['last_local_activity_at'] ?? 0),
                ]);
                $run = admin_multi_instance_run_site_phase((string) $site['admin_file'], $phase);
                admin_multi_instance_trace($config, [
                    'scope' => 'cluster',
                    'event' => 'phase_finish',
                    'strategy' => 'activity',
                    'phase' => $phase,
                    'site' => $site['slug'] ?? basename((string) ($site['site_dir'] ?? '')),
                    'duration_ms' => (int) ($run['duration_ms'] ?? 0),
                    'exit_code' => (int) ($run['exit_code'] ?? 1),
                    'result_keys' => array_values(array_keys(is_array($run['result'] ?? null) ? $run['result'] : [])),
                    'raw_prefix' => substr((string) ($run['raw'] ?? ''), 0, 300),
                ]);
                $runs[] = [
                    'site' => $site['slug'] ?? basename((string) ($site['site_dir'] ?? '')),
                    'phase' => $phase,
                    'strategy' => 'activity',
                    'activity_profile' => (string) (($pick['profile']['name'] ?? 'idle')),
                    'started_at' => (string) ($run['started_at'] ?? ''),
                    'finished_at' => (string) ($run['finished_at'] ?? ''),
                    'duration_ms' => (int) ($run['duration_ms'] ?? 0),
                    'exit_code' => (int) ($run['exit_code'] ?? 1),
                    'result' => $run['result'] ?? null,
                ];
                if (admin_multi_instance_run_consumed_slot($run)) {
                    admin_multi_instance_mark_phase_run($state, $siteKey, $phase, $now);
                    admin_multi_instance_scheduler_state_save($config, $state);
                }
            }
        } else {
            foreach ($sites as $index => $site) {
                $siteKey = (string) ($site['site_dir'] ?? ('site-' . $index));
                foreach (['maintenance', 'light', 'heavy'] as $phase) {
                    $elapsedMs = (int) round(max(0, microtime(true) - $clusterStartedAt) * 1000);
                    if ($elapsedMs >= $clusterBudgetMs) {
                        admin_multi_instance_trace($config, [
                            'scope' => 'cluster',
                            'event' => 'phase_skip',
                            'strategy' => 'fixed',
                            'phase' => $phase,
                            'site' => $site['slug'] ?? basename((string) ($site['site_dir'] ?? '')),
                            'reason' => 'cluster_time_budget_exhausted',
                            'elapsed_ms' => $elapsedMs,
                        ]);
                        break 2;
                    }
                    $remainingMs = $clusterBudgetMs - $elapsedMs;
                    $minimumRemainingMs = (int) ($phaseMinimumRemainingMs[$phase] ?? 0);
                    if ($remainingMs < $minimumRemainingMs) {
                        admin_multi_instance_trace($config, [
                            'scope' => 'cluster',
                            'event' => 'phase_skip',
                            'strategy' => 'fixed',
                            'phase' => $phase,
                            'site' => $site['slug'] ?? basename((string) ($site['site_dir'] ?? '')),
                            'reason' => 'insufficient_time_remaining',
                            'elapsed_ms' => $elapsedMs,
                            'remaining_ms' => $remainingMs,
                            'required_remaining_ms' => $minimumRemainingMs,
                        ]);
                        continue;
                    }
                    if (!admin_multi_instance_phase_due($phase, $index, $state, $siteKey, $now)) {
                        continue;
                    }
                    admin_multi_instance_trace($config, [
                        'scope' => 'cluster',
                        'event' => 'phase_start',
                        'strategy' => 'fixed',
                        'phase' => $phase,
                        'site' => $site['slug'] ?? basename((string) ($site['site_dir'] ?? '')),
                        'slot_index' => $index,
                    ]);
                    $run = admin_multi_instance_run_site_phase((string) $site['admin_file'], $phase);
                    admin_multi_instance_trace($config, [
                        'scope' => 'cluster',
                        'event' => 'phase_finish',
                        'strategy' => 'fixed',
                        'phase' => $phase,
                        'site' => $site['slug'] ?? basename((string) ($site['site_dir'] ?? '')),
                        'duration_ms' => (int) ($run['duration_ms'] ?? 0),
                        'exit_code' => (int) ($run['exit_code'] ?? 1),
                        'result_keys' => array_values(array_keys(is_array($run['result'] ?? null) ? $run['result'] : [])),
                        'raw_prefix' => substr((string) ($run['raw'] ?? ''), 0, 300),
                    ]);
                    $runs[] = [
                        'site' => $site['slug'] ?? basename((string) $site['site_dir']),
                        'phase' => $phase,
                        'strategy' => 'fixed',
                        'started_at' => (string) ($run['started_at'] ?? ''),
                        'finished_at' => (string) ($run['finished_at'] ?? ''),
                        'duration_ms' => (int) ($run['duration_ms'] ?? 0),
                        'exit_code' => (int) ($run['exit_code'] ?? 1),
                        'result' => $run['result'] ?? null,
                    ];
                    if (admin_multi_instance_run_consumed_slot($run)) {
                        admin_multi_instance_mark_phase_run($state, $siteKey, $phase, $now);
                        admin_multi_instance_scheduler_state_save($config, $state);
                    }
                }
            }
        }
        admin_multi_instance_scheduler_state_save($config, $state);
        $clusterFinishedAt = microtime(true);
        return [
            'ok' => true,
            'skipped' => 0,
            'cluster' => admin_multi_instance_cluster_name($config),
            'strategy' => $strategy,
            'started_at' => gmdate(DATE_ATOM, (int) $clusterStartedAt),
            'finished_at' => gmdate(DATE_ATOM, (int) $clusterFinishedAt),
            'duration_ms' => (int) round(max(0, $clusterFinishedAt - $clusterStartedAt) * 1000),
            'sites' => count($sites),
            'executed' => count($runs),
            'runs' => $runs,
        ];
    } finally {
        @flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function admin_scheduled_lock_file(): string
{
    return NAMMU_ROOT . '/config/.scheduled-run.lock';
}

/**
 * Lock propio del refresco de link cards: lo comparten el cron --run-fediverse-link-card-refresh
 * y el paso equivalente de maintenance, de modo que la cola nunca se procese dos veces a la vez
 * sin que ese trabajo (lento, de red) bloquee las fases light/maintenance/heavy.
 */
function admin_fediverse_link_card_lock_file(): string
{
    return NAMMU_ROOT . '/config/.fediverse-link-cards.lock';
}

function admin_run_with_scheduled_lock(callable $callback, string $lockFile = ''): array
{
    $lockFile = $lockFile !== '' ? $lockFile : admin_scheduled_lock_file();
    $handle = @fopen($lockFile, 'c+');
    if (!is_resource($handle)) {
        return ['ok' => false, 'skipped' => 1, 'reason' => 'lock_unavailable'];
    }
    if (!@flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        return ['ok' => true, 'skipped' => 1, 'reason' => 'already_running'];
    }
    try {
        $result = $callback();
        if (!is_array($result)) {
            $result = ['ok' => true];
        }
        $result['skipped'] = (int) ($result['skipped'] ?? 0);
        return $result;
    } finally {
        @flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function admin_maintenance_trace(array $entry): void
{
    if (PHP_SAPI !== 'cli' || !admin_cli_debug_enabled()) {
        return;
    }
    $backupDir = admin_stats_backup_dir();
    if (!is_dir($backupDir)) {
        nammu_ensure_directory($backupDir);
    }
    $entry['at'] = date(DATE_ATOM);
    $entry['site'] = basename(NAMMU_ROOT);
    @file_put_contents($backupDir . '/maintenance-trace.log', json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function admin_heavy_trace(array $entry): void
{
    if (PHP_SAPI !== 'cli' || !admin_cli_debug_enabled()) {
        return;
    }
    $backupDir = admin_stats_backup_dir();
    if (!is_dir($backupDir)) {
        nammu_ensure_directory($backupDir);
    }
    $entry['at'] = date(DATE_ATOM);
    $entry['site'] = basename(NAMMU_ROOT);
    @file_put_contents($backupDir . '/heavy-trace.log', json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}
