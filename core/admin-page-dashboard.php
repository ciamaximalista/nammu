<?php if ($page === 'dashboard'): ?>
    <?php include __DIR__ . '/admin-view-dashboard.php'; ?>

    <div class="tab-pane active">
        <style>
<?php admin_inline_asset('dashboard.css'); ?>
        </style>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
            <div>
                <h2 class="mb-1">Escritorio Nammu</h2>
                <p class="text-muted mb-0">Resumen general de publicaciones y estadísticas del sitio.</p>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-lg-6 mb-3 mb-lg-0">
                <div class="dashboard-status-panel dashboard-status-panel--email" style="border:1px solid #d8eadf;background:#f6fbf8;border-radius:12px;padding:16px;height:100%;">
                    <h3 class="h6 text-uppercase mb-3" style="color:#167a3a;">Envíos por email</h3>
                    <?php if (empty($mailingDashboardItems)): ?>
                        <p class="text-muted mb-0">Sin campañas recientes.</p>
                    <?php else: ?>
                        <?php foreach ($mailingDashboardItems as $mailingRow): ?>
                            <?php
                            $mailingTitle = trim((string) ($mailingRow['title'] ?? ''));
                            $mailingLastAttempt = (int) ($mailingRow['last_attempt_at'] ?? 0);
                            $mailingQueuedAt = (int) ($mailingRow['queued_at'] ?? 0);
                            $mailingError = trim((string) ($mailingRow['last_error'] ?? ''));
                            ?>
                            <div class="dashboard-status-item" style="background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:10px;padding:12px;margin-bottom:12px;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong><?= htmlspecialchars((string) ($mailingRow['label'] ?? 'Correo'), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span style="font-size:.8rem;color:<?= htmlspecialchars($mailingStatusColor($mailingRow), ENT_QUOTES, 'UTF-8') ?>;font-weight:700;"><?= htmlspecialchars($mailingStatusLabel($mailingRow), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <?php if ($mailingTitle !== ''): ?>
                                    <p class="mb-2"><?= htmlspecialchars($mailingTitle, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($mailingLastAttempt > 0): ?>
                                    <p class="text-muted mb-2">Último intento: <?= htmlspecialchars($mailingDateLabel($mailingLastAttempt), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php else: ?>
                                    <p class="text-muted mb-2">Encolado: <?= htmlspecialchars($mailingDateLabel($mailingQueuedAt), ENT_QUOTES, 'UTF-8') ?> · Aún sin intentos</p>
                                <?php endif; ?>
                                <p class="mb-2">
                                    <strong>Enviados:</strong> <?= (int) ($mailingRow['sent'] ?? 0) ?>
                                    · <strong>Pendientes:</strong> <?= (int) ($mailingRow['pending'] ?? 0) ?>
                                    · <strong>Total:</strong> <?= (int) ($mailingRow['queued'] ?? 0) ?>
                                </p>
                                <?php if ((int) ($mailingRow['last_failed'] ?? 0) > 0): ?>
                                    <p class="mb-2"><strong>Fallidos en la última tanda:</strong> <?= (int) ($mailingRow['last_failed'] ?? 0) ?></p>
                                <?php endif; ?>
                                <?php if ($mailingError !== ''): ?>
                                    <p class="mb-0" style="color:#ea2f28;"><strong>Error:</strong> <?= htmlspecialchars($mailingError, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="dashboard-status-panel dashboard-status-panel--social" style="border:1px solid #cce2ff;background:#f5f9ff;border-radius:12px;padding:16px;height:100%;">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h3 class="h6 text-uppercase mb-0" style="color:#1b8eed;">Envíos Fediverso y RRSS</h3>
                        <span style="font-size:.8rem;color:<?= htmlspecialchars($socialFediverseColor, ENT_QUOTES, 'UTF-8') ?>;font-weight:700;"><?= htmlspecialchars($socialFediverseLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="dashboard-status-item" style="background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:10px;padding:12px;margin-bottom:12px;">
                        <strong>RRSS</strong>
                        <p class="mb-2 text-muted">Última actividad: <?= htmlspecialchars($mailingDateLabel((int) ($socialFediverseStatus['social_last_at'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mb-0">
                            <strong>Mensajes pendientes:</strong> <?= (int) ($socialFediverseStatus['social_pending_items'] ?? 0) ?>
                            · <strong>Redes pendientes:</strong> <?= (int) ($socialFediverseStatus['social_pending_networks'] ?? 0) ?>
                            · <strong>RSS sin procesar:</strong> <?= (int) ($socialFediverseStatus['rss_unprocessed'] ?? 0) ?>
                            <?php if ((int) ($socialFediverseStatus['rss_fetch_failed'] ?? 0) > 0): ?>
                                · <strong>No comprobadas:</strong> <?= (int) ($socialFediverseStatus['rss_fetch_failed'] ?? 0) ?>
                            <?php endif; ?>
                            <?php if ((int) ($socialFediverseStatus['social_failed_items'] ?? 0) > 0): ?>
                                · <strong>Con error:</strong> <?= (int) ($socialFediverseStatus['social_failed_items'] ?? 0) ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($socialFediverseStatus['rss_unprocessed_items']) && is_array($socialFediverseStatus['rss_unprocessed_items'])): ?>
                            <ul class="mb-0 mt-2 pl-3 text-muted">
                                <?php foreach ($socialFediverseStatus['rss_unprocessed_items'] as $rssPendingItem): ?>
                                    <?php
                                    $rssPendingTitle = trim((string) ($rssPendingItem['title'] ?? ''));
                                    $rssPendingTimestamp = (int) ($rssPendingItem['timestamp'] ?? 0);
                                    $rssPendingReason = trim((string) ($rssPendingItem['reason'] ?? ''));
                                    ?>
                                    <li><?= htmlspecialchars($rssPendingTitle !== '' ? $rssPendingTitle : 'Ítem RSS sin título', ENT_QUOTES, 'UTF-8') ?><?php if ($rssPendingTimestamp > 0): ?> · <?= htmlspecialchars(date('d/m/y H:i', $rssPendingTimestamp), ENT_QUOTES, 'UTF-8') ?><?php endif; ?><?php if ($rssPendingReason !== ''): ?> · <?= htmlspecialchars($rssPendingReason, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($socialFediverseStatus['rss_fetch_failed_items']) && is_array($socialFediverseStatus['rss_fetch_failed_items'])): ?>
                            <ul class="mb-0 mt-2 pl-3" style="color:#ea2f28;">
                                <?php foreach ($socialFediverseStatus['rss_fetch_failed_items'] as $rssFailedItem): ?>
                                    <?php
                                    $rssFailedFeed = trim((string) ($rssFailedItem['feed'] ?? ''));
                                    $rssFailedError = trim((string) ($rssFailedItem['error'] ?? ''));
                                    ?>
                                    <li><?= htmlspecialchars($rssFailedFeed !== '' ? $rssFailedFeed : 'Feed RSS', ENT_QUOTES, 'UTF-8') ?><?php if ($rssFailedError !== ''): ?> · <?= htmlspecialchars($rssFailedError, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div class="dashboard-status-item" style="background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:10px;padding:12px;">
                        <strong>Fediverso</strong>
                        <p class="mb-2 text-muted">Última actividad: <?= htmlspecialchars($mailingDateLabel((int) ($socialFediverseStatus['fediverse_last_at'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mb-0"><strong>Acciones pendientes:</strong> <?= (int) ($socialFediverseStatus['fediverse_pending'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($indexnowHasErrors): ?>
            <div class="mb-4 dashboard-status-panel dashboard-status-panel--indexnow-error" style="border:1px solid #ea2f28;background:#fff5f5;border-radius:12px;padding:16px;">
                <h3 class="h6 text-uppercase mb-2" style="color:#ea2f28;">Errores al enviar IndexNow</h3>
                <?php if ($indexnowTimestamp > 0): ?>
                    <p class="text-muted mb-2">Último intento: <?= htmlspecialchars(date('d/m/y H:i', $indexnowTimestamp), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <ul class="mb-0 pl-3">
                    <?php foreach ($indexnowErrors as $error): ?>
                        <?php
                        $endpoint = (string) ($error['endpoint'] ?? '');
                        $status = (int) ($error['status'] ?? 0);
                        $message = trim((string) ($error['message'] ?? ''));
                        ?>
                        <li>
                            <strong><?= htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($status > 0): ?>
                                (HTTP <?= $status ?>)
                            <?php endif; ?>
                            <?php if ($message !== ''): ?>
                                — <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($indexnowHasLog): ?>
            <div class="mb-4 dashboard-status-panel dashboard-status-panel--indexnow-ok" style="border:1px solid #cce2ff;background:#f5f9ff;border-radius:12px;padding:16px;">
                <h3 class="h6 text-uppercase mb-2" style="color:#1b8eed;">IndexNow enviado correctamente</h3>
                <p class="text-muted mb-2">Último envío: <?= htmlspecialchars(date('d/m/y H:i', $indexnowTimestamp), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($indexnowUrls)): ?>
                    <p class="mb-0 text-muted">URLs enviadas: <?= htmlspecialchars(implode(', ', array_slice($indexnowUrls, 0, 3)), ENT_QUOTES, 'UTF-8') ?><?= count($indexnowUrls) > 3 ? '…' : '' ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Usuarios únicos humanos (últimos 30 días)</h4>
                        <?php if ($last30Line['points'] === ''): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <?php
                            $last30CoordCount = count($last30Line['coords']);
                            $last30TodayPoint = $last30CoordCount > 0 ? $last30Line['coords'][$last30CoordCount - 1] : null;
                            ?>
                            <svg class="dashboard-line-chart" width="320" height="170" viewBox="0 0 320 170" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                <line class="dashboard-line-chart__axis" x1="30" y1="<?= (int) $chartTop ?>" x2="30" y2="<?= (int) $chartBottom ?>"></line>
                                <line class="dashboard-line-chart__axis" x1="30" y1="<?= (int) $chartBottom ?>" x2="300" y2="<?= (int) $chartBottom ?>"></line>
                                <text class="dashboard-line-chart__label" x="4" y="<?= (int) $chartTop ?>"><?= (int) $last30DailyMax ?></text>
                                <text class="dashboard-line-chart__label" x="12" y="<?= (int) $chartBottom ?>">0</text>
                                <text class="dashboard-line-chart__label" x="30" y="166" text-anchor="start"><?= htmlspecialchars($formatDayMonthEs($last30LabelStart), ENT_QUOTES, 'UTF-8') ?></text>
                                <text class="dashboard-line-chart__label" x="165" y="166" text-anchor="middle"><?= htmlspecialchars($formatDayMonthEs($last30LabelMid), ENT_QUOTES, 'UTF-8') ?></text>
                                <text class="dashboard-line-chart__label" x="300" y="166" text-anchor="end"><?= htmlspecialchars($formatDayMonthEs($last30LabelEnd), ENT_QUOTES, 'UTF-8') ?></text>
                                <g transform="translate(30,0)">
                                    <path class="dashboard-line-chart__line" style="stroke:#1b8eed !important;" d="<?= htmlspecialchars((string) ($last30Line['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></path>
                                    <?php foreach ($last30Line['coords'] as $point): ?>
                                        <circle class="dashboard-line-chart__point" cx="<?= htmlspecialchars((string) $point['x'], ENT_QUOTES, 'UTF-8') ?>" cy="<?= htmlspecialchars((string) $point['y'], ENT_QUOTES, 'UTF-8') ?>" r="2.5" style="fill:#1b8eed !important;"></circle>
                                    <?php endforeach; ?>
                                    <?php if ($last30TodayPoint): ?>
                                        <text class="dashboard-line-chart__label" x="<?= htmlspecialchars((string) $last30TodayPoint['x'], ENT_QUOTES, 'UTF-8') ?>" y="<?= (int) max(12, (float) $last30TodayPoint['y'] - 6) ?>" text-anchor="middle" style="fill:#1b8eed !important;">
                                            <?= (int) $last30TodayPoint['value'] ?>
                                        </text>
                                    <?php endif; ?>
                                </g>
                            </svg>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Usuarios únicos humanos (último año)</h4>
                        <?php if ($last12Line['points'] === ''): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <?php
                            $last12CoordCount = count($last12Line['coords']);
                            $last12CurrentPoint = $last12CoordCount > 0 ? $last12Line['coords'][$last12CoordCount - 1] : null;
                            ?>
                            <svg class="dashboard-line-chart" width="320" height="170" viewBox="0 0 320 170" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                <line class="dashboard-line-chart__axis" x1="30" y1="<?= (int) $chartTop ?>" x2="30" y2="<?= (int) $chartBottom ?>"></line>
                                <line class="dashboard-line-chart__axis" x1="30" y1="<?= (int) $chartBottom ?>" x2="300" y2="<?= (int) $chartBottom ?>"></line>
                                <text class="dashboard-line-chart__label" x="4" y="<?= (int) $chartTop ?>"><?= (int) $last12MonthsMax ?></text>
                                <text class="dashboard-line-chart__label" x="12" y="<?= (int) $chartBottom ?>">0</text>
                                <text class="dashboard-line-chart__label" x="30" y="166" text-anchor="start"><?= htmlspecialchars($formatMonthEs($last12LabelStart . '-01'), ENT_QUOTES, 'UTF-8') ?></text>
                                <text class="dashboard-line-chart__label" x="165" y="166" text-anchor="middle"><?= htmlspecialchars($formatMonthEs($last12LabelMid . '-01'), ENT_QUOTES, 'UTF-8') ?></text>
                                <text class="dashboard-line-chart__label" x="300" y="166" text-anchor="end"><?= htmlspecialchars($formatMonthEs($last12LabelEnd . '-01'), ENT_QUOTES, 'UTF-8') ?></text>
                                <g transform="translate(30,0)">
                                    <path class="dashboard-line-chart__line" style="stroke:#0a4c8a !important;" d="<?= htmlspecialchars((string) ($last12Line['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></path>
                                    <?php foreach ($last12Line['coords'] as $point): ?>
                                        <circle class="dashboard-line-chart__point" cx="<?= htmlspecialchars((string) $point['x'], ENT_QUOTES, 'UTF-8') ?>" cy="<?= htmlspecialchars((string) $point['y'], ENT_QUOTES, 'UTF-8') ?>" r="2.5" style="fill:#0a4c8a !important;"></circle>
                                    <?php endforeach; ?>
                                    <?php if ($last12CurrentPoint): ?>
                                        <text class="dashboard-line-chart__label" x="<?= htmlspecialchars((string) $last12CurrentPoint['x'], ENT_QUOTES, 'UTF-8') ?>" y="<?= (int) max(12, (float) $last12CurrentPoint['y'] - 6) ?>" text-anchor="middle" style="fill:#0a4c8a !important;">
                                            <?= (int) $last12CurrentPoint['value'] ?>
                                        </text>
                                    <?php endif; ?>
                                </g>
                            </svg>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 order-lg-2">
                <?php if (!empty($multiInstanceDashboard['enabled'])): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Clúster central</h4>
                            <p class="mb-2"><strong>Clúster:</strong> <?= htmlspecialchars((string) ($multiInstanceDashboard['cluster'] ?: 'sin nombre'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mb-2"><strong>Sitios detectados:</strong> <?= (int) $multiInstanceDashboard['sites'] ?></p>
                            <p class="mb-2"><strong>Estrategia:</strong> <?= htmlspecialchars((string) ($multiInstanceDashboard['scheduler_strategy'] === 'activity' ? 'Activity' : 'Fixed'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mb-2"><strong>Perfiles:</strong> fresh <?= (int) $multiInstanceDashboard['fresh_sites'] ?> · warm <?= (int) $multiInstanceDashboard['warm_sites'] ?> · idle <?= (int) $multiInstanceDashboard['idle_sites'] ?></p>
                            <?php if ($multiInstanceDashboard['runner_label'] !== ''): ?>
                                <p class="mb-2"><strong>Runner activo:</strong>
                                    <?php if ($multiInstanceDashboard['runner_url'] !== ''): ?>
                                        <a href="<?= htmlspecialchars((string) $multiInstanceDashboard['runner_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $multiInstanceDashboard['runner_label'], ENT_QUOTES, 'UTF-8') ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string) $multiInstanceDashboard['runner_label'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                    <?php if ($multiInstanceDashboard['runner_at_label'] !== ''): ?>
                                        · <?= htmlspecialchars((string) $multiInstanceDashboard['runner_at_label'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <p class="mb-2"><strong>Slots esta hora:</strong> light <?= (int) $multiInstanceDashboard['recent_light_runs'] ?> · maintenance <?= (int) $multiInstanceDashboard['recent_maintenance_runs'] ?> · heavy <?= (int) $multiInstanceDashboard['recent_heavy_runs'] ?></p>
                            <p class="mb-2"><strong>Hosts remotos seguidos:</strong> <?= (int) $multiInstanceDashboard['tracked_hosts'] ?></p>
                            <p class="mb-2"><strong>Hosts en backoff:</strong> <?= (int) $multiInstanceDashboard['hosts_in_backoff'] ?></p>
                            <p class="mb-0"><strong>Caché compartida:</strong> <?= $multiInstanceDashboard['shared_cache_dir'] !== '' ? 'activa' : 'no configurada' ?> · <strong>Cola compartida:</strong> <?= $multiInstanceDashboard['shared_queue_dir'] !== '' ? 'activa' : 'no configurada' ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Imagen 30 días</h4>
                        <p class="mb-2"><strong>Usuarios únicos humanos:</strong> <?= (int) $unique30Count ?></p>
                        <p class="mb-2"><strong>Objetos Fediverso:</strong> <?= (int) $image30FediverseObjectUnique ?> usuarios · <?= (int) $image30ViewsFediverseObjects ?> vistas</p>
                        <p class="mb-2"><strong>Recurrentes (2+ días):</strong> <?= (int) $image30RecurringUsers ?> (<?= number_format($image30RecurringRate, 2, ',', '.') ?>%)</p>
                        <p class="mb-2"><strong>Vistas totales (posts + páginas + objetos Fediverso + itinerarios + newsletter + podcast):</strong> <?= (int) $image30TotalViews ?></p>
                        <p class="mb-2"><strong>Páginas por usuario:</strong> <?= number_format($image30PagesPerUser, 2, ',', '.') ?></p>
                        <p class="mb-0"><strong>Promedio diario:</strong> <?= number_format($image30DailyAverage, 2, ',', '.') ?> · <strong>Mediana:</strong> <?= number_format($image30DailyMedian, 2, ',', '.') ?> · <strong>Pico:</strong> <?= (int) $image30DailyPeak ?></p>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Suscriptores</h4>
                        <?php foreach ($subscriberCounts as $subscriberCount): ?>
                            <p class="mb-2"><strong><?= htmlspecialchars((string) $subscriberCount['label'], ENT_QUOTES, 'UTF-8') ?>:</strong> <?= (int) $subscriberCount['count'] ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Publicaciones</h4>
                        <p class="mb-2"><strong>Entradas:</strong> <?= (int) $postCount ?></p>
                        <?php if (!empty($postCountsByYear)): ?>
                            <div class="table-responsive mb-2">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <?php foreach ($postCountsByYear as $year => $count): ?>
                                            <tr>
                                                <td><?= (int) $year ?></td>
                                                <td class="text-right"><?= (int) $count ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <p class="mb-2"><strong>Páginas:</strong> <?= (int) $pageCount ?></p>
                        <p class="mb-2"><strong>Newsletters:</strong> <?= (int) $newsletterCount ?></p>
                        <p class="mb-2"><strong>Podcast:</strong> <?= (int) $podcastCount ?></p>
                        <p class="mb-0"><strong>Itinerarios:</strong> <?= (int) $itineraryCount ?></p>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Recursos</h4>
                        <?php if ($resourceCounts['images'] > 0): ?>
                            <p class="mb-2"><strong>Imagenes:</strong> <?= (int) $resourceCounts['images'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['videos'] > 0): ?>
                            <p class="mb-2"><strong>Videos:</strong> <?= (int) $resourceCounts['videos'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['audios'] > 0): ?>
                            <p class="mb-2"><strong>Audios:</strong> <?= (int) $resourceCounts['audios'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['pdfs'] > 0): ?>
                            <p class="mb-2"><strong>PDFs:</strong> <?= (int) $resourceCounts['pdfs'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['epubs'] > 0): ?>
                            <p class="mb-2"><strong>EPUBs:</strong> <?= (int) $resourceCounts['epubs'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['docs'] > 0): ?>
                            <p class="mb-2"><strong>Documentos:</strong> <?= (int) $resourceCounts['docs'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['others'] > 0): ?>
                            <p class="mb-0"><strong>Otros:</strong> <?= (int) $resourceCounts['others'] ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Bots y crawlers</h4>
                        <?php if ($botTotal === 0): ?>
                            <p class="text-muted mb-0">Sin visitas de bots registradas.</p>
                        <?php else: ?>
                            <p class="mb-2"><strong>Total últimos 30 días:</strong> <?= (int) $botTotal ?></p>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Bot</th>
                                            <th class="text-right">Visitas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($botCounts as $botLabel => $count): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string) $botLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-right"><?= (int) $count ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Plataforma (últimos 30 días)</h4>
                        <?php if (empty($deviceList) && empty($browserList) && empty($systemList) && empty($languageList)): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <?php if (!empty($deviceList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Dispositivo</p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($deviceList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($browserList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Navegador</p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($browserList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($systemList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Sistema (escritorio)</p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($systemList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($languageList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Lengua</p>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($languageList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 order-lg-1">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Usuarios únicos humanos</h4>
                        <p class="mb-3"><strong>Hoy:</strong> <?= (int) $todayCount ?></p>

                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Últimos 7 días</p>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($last7DailyList as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-right"><?= (int) $item['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Últimos 12 meses</p>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($last12MonthsList as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-right"><?= (int) $item['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Años</p>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php if (empty($yearList)): ?>
                                        <tr>
                                            <td colspan="2" class="text-muted">Sin datos todavía.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($yearList as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-right"><?= (int) $item['count'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php
                $hasPostStats = !empty($topPosts) || !empty($topPostsByUnique)
                    || !empty($topPostsWeek) || !empty($topPostsWeekByUnique)
                    || !empty($topPostsMonth) || !empty($topPostsMonthByUnique);
                ?>
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Entradas más leídas</h4>
                            <div class="d-flex flex-column align-items-start">
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="posts" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="views">Vistas</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="users">Usuarios</button>
                                </div>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="posts" data-stat-toggle-type="period">
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="week">Últimos 7 días</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="month">Últimos 30 días</button>
                                    <button type="button" class="btn btn-outline-primary active" data-stat-period="all">Desde el comienzo del blog</button>
                                </div>
                            </div>
                        </div>
                        <?php if (!$hasPostStats): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <ol class="mb-0 dashboard-links" data-stat-list="posts" data-stat-mode="views" data-stat-period="all">
                                <?php foreach ($topPosts as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="users" data-stat-period="all">
                                <?php foreach ($topPostsByUnique as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="views" data-stat-period="week">
                                <?php foreach ($topPostsWeek as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="users" data-stat-period="week">
                                <?php foreach ($topPostsWeekByUnique as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="views" data-stat-period="month">
                                <?php foreach ($topPostsMonth as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="users" data-stat-period="month">
                                <?php foreach ($topPostsMonthByUnique as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                $hasSystemPageStats = !empty($topSystemPages) || !empty($topSystemPagesByUnique)
                    || !empty($topSystemPagesWeek) || !empty($topSystemPagesWeekByUnique)
                    || !empty($topSystemPagesMonth) || !empty($topSystemPagesMonthByUnique);
                ?>
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Páginas sistémicas más leídas</h4>
                            <div class="d-flex flex-column align-items-start">
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="system-pages" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="views">Vistas</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="users">Usuarios</button>
                                </div>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="system-pages" data-stat-toggle-type="period">
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="week">Últimos 7 días</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="month">Últimos 30 días</button>
                                    <button type="button" class="btn btn-outline-primary active" data-stat-period="all">Desde el comienzo del blog</button>
                                </div>
                            </div>
                        </div>
                        <?php if (!$hasSystemPageStats): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <ol class="mb-0 dashboard-links" data-stat-list="system-pages" data-stat-mode="views" data-stat-period="all">
                                <?php foreach ($topSystemPages as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="system-pages" data-stat-mode="users" data-stat-period="all">
                                <?php foreach ($topSystemPagesByUnique as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="system-pages" data-stat-mode="views" data-stat-period="week">
                                <?php foreach ($topSystemPagesWeek as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="system-pages" data-stat-mode="users" data-stat-period="week">
                                <?php foreach ($topSystemPagesWeekByUnique as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="system-pages" data-stat-mode="views" data-stat-period="month">
                                <?php foreach ($topSystemPagesMonth as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="system-pages" data-stat-mode="users" data-stat-period="month">
                                <?php foreach ($topSystemPagesMonthByUnique as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Entradas Fediverso</h4>
                            <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="fediverse-objects" data-stat-toggle-type="period">
                                <button type="button" class="btn btn-outline-primary active" data-stat-period="today">Hoy</button>
                                <button type="button" class="btn btn-outline-primary" data-stat-period="week">Últimos 7 días</button>
                                <button type="button" class="btn btn-outline-primary" data-stat-period="month">Últimos 30 días</button>
                            </div>
                        </div>
                        <?php if (empty($topFediverseObjectPagesToday) && empty($topFediverseObjectPagesWeek) && empty($topFediverseObjectPagesMonth)): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <ol class="mb-0 dashboard-links" data-stat-list="fediverse-objects" data-stat-period="today">
                                <?php foreach ($topFediverseObjectPagesToday as $item): ?>
                                    <?php $url = $buildFediverseObjectPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="fediverse-objects" data-stat-period="week">
                                <?php foreach ($topFediverseObjectPagesWeek as $item): ?>
                                    <?php $url = $buildFediverseObjectPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="fediverse-objects" data-stat-period="month">
                                <?php foreach ($topFediverseObjectPagesMonth as $item): ?>
                                    <?php $url = $buildFediverseObjectPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($itineraryCount > 0): ?>
                    <div class="card mb-4 dashboard-stat-block">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Itinerarios (usuarios únicos)</h4>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle" role="group" data-stat-toggle="itineraries" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="views">Vieron</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="starts">Comenzaron</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="completes">Completaron</button>
                                </div>
                            </div>
                            <?php if (empty($topItineraryViews) && empty($topItineraryStarts) && empty($topItineraryCompletes)): ?>
                                <p class="text-muted mb-0">Sin datos todavía.</p>
                            <?php else: ?>
                                <ol class="mb-0 dashboard-links" data-stat-list="itineraries" data-stat-mode="views">
                                    <?php foreach ($topItineraryViews as $item): ?>
                                        <li>
                                            <?php $url = admin_public_itinerary_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="itineraries" data-stat-mode="starts">
                                    <?php foreach ($topItineraryStarts as $item): ?>
                                        <li>
                                            <?php $url = admin_public_itinerary_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="itineraries" data-stat-mode="completes">
                                    <?php foreach ($topItineraryCompletes as $item): ?>
                                        <li>
                                            <?php $url = admin_public_itinerary_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($topPages) || !empty($topPagesByUnique) || !empty($topPagesWeek) || !empty($topPagesWeekByUnique) || !empty($topPagesMonth) || !empty($topPagesMonthByUnique)): ?>
                    <div class="card mb-4 dashboard-stat-block">
                        <div class="card-body">
                            <div class="d-flex flex-column gap-2 mb-3">
                                <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Páginas estáticas y temas de itinerario más leídos</h4>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle align-self-start my-2" role="group" data-stat-toggle="pages-all" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="views">Vistas</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="users">Usuarios</button>
                                </div>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle align-self-start my-2" role="group" data-stat-toggle="pages-all" data-stat-toggle-type="period">
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="week">Últimos 7 días</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="month">Últimos 30 días</button>
                                    <button type="button" class="btn btn-outline-primary active" data-stat-period="all">Desde el comienzo del blog</button>
                                </div>
                            </div>
                            <?php if (empty($topPages) && empty($topPagesByUnique)): ?>
                                <p class="text-muted mb-0">Sin datos todavía.</p>
                            <?php else: ?>
                                <ol class="mb-0 dashboard-links" data-stat-list="pages-all" data-stat-mode="views" data-stat-period="all">
                                    <?php foreach ($topPages as $item): ?>
                                        <li>
                                            <?php $url = admin_public_post_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="pages-all" data-stat-mode="users" data-stat-period="all">
                                    <?php foreach ($topPagesByUnique as $item): ?>
                                        <li>
                                            <?php $url = admin_public_post_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="pages-all" data-stat-mode="views" data-stat-period="week">
                                    <?php foreach ($topPagesWeek as $item): ?>
                                        <li>
                                            <?php $url = admin_public_post_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="pages-all" data-stat-mode="users" data-stat-period="week">
                                    <?php foreach ($topPagesWeekByUnique as $item): ?>
                                        <li>
                                            <?php $url = admin_public_post_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="pages-all" data-stat-mode="views" data-stat-period="month">
                                    <?php foreach ($topPagesMonth as $item): ?>
                                        <li>
                                            <?php $url = admin_public_post_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="pages-all" data-stat-mode="users" data-stat-period="month">
                                    <?php foreach ($topPagesMonthByUnique as $item): ?>
                                        <li>
                                            <?php $url = admin_public_post_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <div class="d-flex flex-column gap-2 mb-3">
                            <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Búsquedas internas más frecuentes (últimos 30 días)</h4>
                            <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle align-self-start" role="group" data-stat-toggle="internal-search" data-stat-toggle-type="mode">
                                <button type="button" class="btn btn-outline-primary active" data-stat-mode="searches">Búsquedas</button>
                                <button type="button" class="btn btn-outline-primary" data-stat-mode="users">Usuarios</button>
                            </div>
                        </div>
                        <?php if (empty($searchCountsList) && empty($searchUsersList)): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <ol class="mb-0 dashboard-links" data-stat-list="internal-search" data-stat-mode="searches">
                                <?php foreach ($searchCountsList as $item): ?>
                                    <li>
                                        <span><?= htmlspecialchars($item['term'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="internal-search" data-stat-mode="users">
                                <?php foreach ($searchUsersList as $item): ?>
                                    <li>
                                        <span><?= htmlspecialchars($item['term'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($sourceRows30['main']) || !empty($sourceRowsToday['main'])): ?>
                    <div class="card mb-4">
                        <div class="card-body dashboard-stat-block">
                            <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Origen de los usuarios únicos</h4>
                            <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle mb-3" role="group" data-stat-toggle data-stat-scope="origin-users" data-stat-toggle-type="period">
                                <button type="button" class="btn btn-outline-primary" data-stat-period="today">Hoy</button>
                                <button type="button" class="btn btn-outline-primary active" data-stat-period="30">Últimos 30 días</button>
                            </div>
                            <?php
                            $originPeriods = [
                                '30' => $sourceRows30,
                                'today' => $sourceRowsToday,
                            ];
                            foreach ($originPeriods as $periodKey => $periodRows):
                                $periodHidden = $periodKey === '30' ? '' : ' d-none';
                            ?>
                                <div class="origin-period<?= $periodHidden ?>" data-stat-list data-stat-scope="origin-users" data-stat-period="<?= htmlspecialchars($periodKey, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if (!empty($periodRows['main'])): ?>
                                        <div class="table-responsive mb-3">
                                            <table class="table table-sm mb-0">
                                                <tbody>
                                                    <?php foreach ($periodRows['main'] as $item): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted mb-3">Sin datos todavía.</p>
                                    <?php endif; ?>
                                    <?php if (!empty($periodRows['search'])): ?>
                                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Buscadores</p>
                                        <div class="table-responsive mb-3">
                                            <table class="table table-sm mb-0">
                                                <tbody>
                                                    <?php foreach ($periodRows['search'] as $item): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($periodRows['social'])): ?>
                                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Redes sociales</p>
                                        <div class="table-responsive mb-3">
                                            <table class="table table-sm mb-0">
                                                <tbody>
                                                    <?php foreach ($periodRows['social'] as $item): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($periodRows['email'])): ?>
                                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Lista de correo</p>
                                        <div class="table-responsive mb-3">
                                            <table class="table table-sm mb-0">
                                                <tbody>
                                                    <?php foreach ($periodRows['email'] as $item): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($periodRows['push'])): ?>
                                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Notificaciones push</p>
                                        <div class="table-responsive mb-3">
                                            <table class="table table-sm mb-0">
                                                <tbody>
                                                    <?php foreach ($periodRows['push'] as $item): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($periodRows['other'])): ?>
                                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Sitios web</p>
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                <tbody>
                                                    <?php foreach ($periodRows['other'] as $item): ?>
                                                        <tr>
                                                            <td>
                                                                <?php if (!empty($item['url'])): ?>
                                                                    <a href="<?= htmlspecialchars((string) $item['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                        <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <?php if (
                    ($gscProperty !== '' && $gscClientId !== '' && $gscClientSecret !== '' && $gscRefreshToken !== '')
                    || ($bingSiteUrl !== '' || $bingApiKey !== '' || $bingHasOauth)
                ): ?>
                    <h3 class="h6 text-uppercase text-muted mb-3">Integración con buscadores</h3>
                <?php endif; ?>
                <?php if ($gscProperty !== '' && $gscClientId !== '' && $gscClientSecret !== '' && $gscRefreshToken !== ''): ?>
                    <div class="card mb-4" id="gsc-dashboard">
                        <div class="card-body">
                            <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Google Search Console</h4>
                            <?php if ($gscError !== ''): ?>
                                <p class="text-muted mb-0"><?= htmlspecialchars($gscError, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php elseif ($gscTotals7 === null || $gscTotals28 === null): ?>
                                <p class="text-muted mb-0">Sin datos disponibles.</p>
                            <?php else: ?>
                                <?php if ($gscUpdatedAtLabel !== ''): ?>
                                    <p class="text-muted mb-2">Datos servidos por Google Search Console API el <?= htmlspecialchars($gscUpdatedAtLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <form method="get" class="mb-2">
                                    <input type="hidden" name="page" value="dashboard">
                                    <input type="hidden" name="gsc_refresh" value="1">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Actualizar datos ahora</button>
                                </form>
                                <input type="radio" name="gsc-period" id="gsc-period-28" class="gsc-period-input" checked>
                                <input type="radio" name="gsc-period" id="gsc-period-7" class="gsc-period-input">
                                <div class="btn-group btn-group-sm mb-3 dashboard-toggle gsc-toggle gsc-buttons" role="group">
                                    <label class="btn btn-outline-secondary gsc-period-label" for="gsc-period-28">Últimos 28 días</label>
                                    <label class="btn btn-outline-secondary gsc-period-label" for="gsc-period-7">Últimos 7 días</label>
                                </div>
                                <div class="gsc-content">
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0 gsc-period-28" data-gsc-period="28" data-stat-list data-stat-period="28" data-stat-scope="gsc-main" data-stat-kind="table">
                                        <tbody>
                                            <tr>
                                                <td>Clicks totales</td>
                                                <td class="text-right"><?= (int) $gscTotals28['clicks'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Impresiones totales</td>
                                                <td class="text-right"><?= (int) $gscTotals28['impressions'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>CTR medio</td>
                                                <td class="text-right"><?= number_format($gscTotals28['ctr'] * 100, 2, ',', '.') ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>Posición media</td>
                                                <td class="text-right"><?= number_format($gscTotals28['position'], 1, ',', '.') ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table class="table table-sm mb-0 gsc-period-7" data-gsc-period="7" data-stat-list data-stat-period="7" data-stat-scope="gsc-main" data-stat-kind="table">
                                        <tbody>
                                            <tr>
                                                <td>Clicks totales</td>
                                                <td class="text-right"><?= (int) $gscTotals7['clicks'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Impresiones totales</td>
                                                <td class="text-right"><?= (int) $gscTotals7['impressions'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>CTR medio</td>
                                                <td class="text-right"><?= number_format($gscTotals7['ctr'] * 100, 2, ',', '.') ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>Posición media</td>
                                                <td class="text-right"><?= number_format($gscTotals7['position'], 1, ',', '.') ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td>Última consulta al sitemap</td>
                                                <td class="text-right"><?= $gscSitemapInfo['last_crawl'] !== '' ? htmlspecialchars($gscSitemapInfo['last_crawl'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($gscQueries28) || !empty($gscQueries7)): ?>
                                    <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Términos más clicados</p>
                                <?php endif; ?>
                                <?php if (!empty($gscQueries28)): ?>
                                    <div class="table-responsive gsc-period-28" data-gsc-period="28" data-stat-list data-stat-period="28" data-stat-scope="gsc-terms" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Término</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscQueries28 as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['term'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscQueries7)): ?>
                                    <div class="table-responsive gsc-period-7" data-gsc-period="7" data-stat-list data-stat-period="7" data-stat-scope="gsc-terms" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Término</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscQueries7 as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['term'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscPages7) || !empty($gscPages28)): ?>
                                    <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Páginas más clicadas</p>
                                <?php endif; ?>
                                <?php if (!empty($gscPages7)): ?>
                                    <div class="table-responsive mb-3 gsc-period-7" data-gsc-period="7" data-stat-list data-stat-period="7" data-stat-scope="gsc-pages" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Página</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscPages7 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $pageUrl = (string) $row['page'];
                                                        $slugValue = $pageUrl;
                                                        if ($pageUrl !== '') {
                                                            $parsed = parse_url($pageUrl);
                                                            if (is_array($parsed) && isset($parsed['path'])) {
                                                                $slugValue = trim((string) $parsed['path'], '/');
                                                            }
                                                        }
                                                        ?>
                                                        <td class="text-truncate">
                                                            <?php if ($pageUrl !== ''): ?>
                                                                <a href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                    <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscPages28)): ?>
                                    <div class="table-responsive mb-3 gsc-period-28" data-gsc-period="28" data-stat-list data-stat-period="28" data-stat-scope="gsc-pages" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Página</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscPages28 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $pageUrl = (string) $row['page'];
                                                        $slugValue = $pageUrl;
                                                        if ($pageUrl !== '') {
                                                            $parsed = parse_url($pageUrl);
                                                            if (is_array($parsed) && isset($parsed['path'])) {
                                                                $slugValue = trim((string) $parsed['path'], '/');
                                                            }
                                                        }
                                                        ?>
                                                        <td class="text-truncate">
                                                            <?php if ($pageUrl !== ''): ?>
                                                                <a href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                    <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscCountries7) || !empty($gscCountries28)): ?>
                                    <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Principales países</p>
                                <?php endif; ?>
                                <?php if (!empty($gscCountries7)): ?>
                                    <div class="table-responsive mb-3 gsc-period-7" data-gsc-period="7" data-stat-list data-stat-period="7" data-stat-scope="gsc-countries" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>País</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscCountries7 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $rawCountry = trim((string) ($row['country'] ?? ''));
                                                        $countryLabel = $gscResolveCountry($rawCountry);
                                                        if ($countryLabel === '') {
                                                            $countryLabel = $rawCountry;
                                                        }
                                                        if ($countryLabel === '') {
                                                            continue;
                                                        }
                                                        ?>
                                                        <td><?= htmlspecialchars($countryLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscCountries28)): ?>
                                    <div class="table-responsive gsc-period-28" data-gsc-period="28" data-stat-list data-stat-period="28" data-stat-scope="gsc-countries" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>País</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscCountries28 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $rawCountry = trim((string) ($row['country'] ?? ''));
                                                        $countryLabel = $gscResolveCountry($rawCountry);
                                                        if ($countryLabel === '') {
                                                            $countryLabel = $rawCountry;
                                                        }
                                                        if ($countryLabel === '') {
                                                            continue;
                                                        }
                                                        ?>
                                                        <td><?= htmlspecialchars($countryLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($bingSiteUrl !== '' || $bingApiKey !== '' || $bingHasOauth): ?>
                    <div class="card mb-4 dashboard-stat-block" id="bing-dashboard">
                        <div class="card-body">
                            <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Microsoft Bing Webmaster Tools</h4>
                            <?php if ($bingSiteUrl === ''): ?>
                                <p class="text-muted mb-0">Define la URL del sitio en Configuración para mostrar los datos de Bing.</p>
                            <?php elseif ($bingError !== ''): ?>
                                <p class="text-muted mb-0"><?= htmlspecialchars($bingError, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php elseif ($bingTotals7 === null || $bingTotals28 === null): ?>
                                <p class="text-muted mb-0">Sin datos disponibles.</p>
                            <?php else: ?>
                                <?php if ($bingUpdatedAtLabel !== ''): ?>
                                    <p class="text-muted mb-2">Datos servidos por Bing Webmaster Tools API el <?= htmlspecialchars($bingUpdatedAtLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <form method="get" class="mb-2">
                                    <input type="hidden" name="page" value="dashboard">
                                    <input type="hidden" name="bing_refresh" value="1">
                                    <?php if ($bingDebug): ?>
                                        <input type="hidden" name="bing_debug" value="1">
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Actualizar datos ahora</button>
                                </form>
                                <?php if ($bingDebug && !empty($GLOBALS['bing_debug_log'])): ?>
                                    <div class="alert alert-warning mb-3">
                                        <div class="small text-muted mb-1">Depuración Bing</div>
                                        <pre class="mb-0 small"><?= htmlspecialchars(json_encode($GLOBALS['bing_debug_log'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                                    </div>
                                <?php endif; ?>
                                <div class="btn-group btn-group-sm mb-3 dashboard-toggle bing-toggle bing-buttons" role="group" data-stat-toggle="bing-period" data-stat-scope="bing-period" data-stat-toggle-type="period">
                                    <button type="button" class="btn btn-outline-secondary bing-period-label active" data-stat-period="28">Últimos 30 días</button>
                                    <button type="button" class="btn btn-outline-secondary bing-period-label" data-stat-period="7">Últimos 7 días</button>
                                </div>
                                <div class="bing-content">
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0 bing-period-28" data-stat-list data-stat-scope="bing-period" data-stat-period="28">
                                        <tbody>
                                            <tr>
                                                <td>Clicks totales</td>
                                                <td class="text-right"><?= (int) $bingTotals28['clicks'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Impresiones totales</td>
                                                <td class="text-right"><?= (int) $bingTotals28['impressions'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>CTR medio</td>
                                                <td class="text-right"><?= number_format($bingTotals28['ctr'] * 100, 2, ',', '.') ?>%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table class="table table-sm mb-0 bing-period-7 d-none" data-stat-list data-stat-scope="bing-period" data-stat-period="7">
                                        <tbody>
                                            <tr>
                                                <td>Clicks totales</td>
                                                <td class="text-right"><?= (int) $bingTotals7['clicks'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Impresiones totales</td>
                                                <td class="text-right"><?= (int) $bingTotals7['impressions'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>CTR medio</td>
                                                <td class="text-right"><?= number_format($bingTotals7['ctr'] * 100, 2, ',', '.') ?>%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($bingQueries28) || !empty($bingQueries7)): ?>
                                    <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Términos más clicados</p>
                                <?php endif; ?>
                                <?php if (!empty($bingQueries28)): ?>
                                    <div class="table-responsive bing-period-28" data-stat-list data-stat-scope="bing-period" data-stat-period="28">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Término</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bingQueries28 as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['term'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($bingQueries7)): ?>
                                    <div class="table-responsive bing-period-7 d-none" data-stat-list data-stat-scope="bing-period" data-stat-period="7">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Término</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bingQueries7 as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['term'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($bingQueries7) && !empty($bingQueries28)): ?>
                                    <p class="text-muted mb-0 bing-period-7 d-none" data-stat-list data-stat-scope="bing-period" data-stat-period="7">Sin términos en los últimos 7 días.</p>
                                <?php endif; ?>
                                <?php if (empty($bingQueries28) && !empty($bingQueries7)): ?>
                                    <p class="text-muted mb-0 bing-period-28" data-stat-list data-stat-scope="bing-period" data-stat-period="28">Sin términos en los últimos 30 días.</p>
                                <?php endif; ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Páginas más clicadas</p>
                                <?php if (!empty($bingPages7)): ?>
                                    <div class="table-responsive mb-3 bing-period-7 d-none" data-stat-list data-stat-scope="bing-period" data-stat-period="7">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Página</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bingPages7 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $pageUrl = (string) $row['page'];
                                                        $slugValue = $pageUrl;
                                                        if ($pageUrl !== '') {
                                                            $parsed = parse_url($pageUrl);
                                                            if (is_array($parsed) && isset($parsed['path'])) {
                                                                $slugValue = trim((string) $parsed['path'], '/');
                                                            }
                                                        }
                                                        ?>
                                                        <td class="text-truncate">
                                                            <?php if ($pageUrl !== ''): ?>
                                                                <a href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                    <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($bingPages28)): ?>
                                    <div class="table-responsive mb-3 bing-period-28" data-stat-list data-stat-scope="bing-period" data-stat-period="28">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Página</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bingPages28 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $pageUrl = (string) $row['page'];
                                                        $slugValue = $pageUrl;
                                                        if ($pageUrl !== '') {
                                                            $parsed = parse_url($pageUrl);
                                                            if (is_array($parsed) && isset($parsed['path'])) {
                                                                $slugValue = trim((string) $parsed['path'], '/');
                                                            }
                                                        }
                                                        ?>
                                                        <td class="text-truncate">
                                                            <?php if ($pageUrl !== ''): ?>
                                                                <a href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                    <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($bingPages7) && empty($bingPages28)): ?>
                                    <p class="text-muted mb-0">Sin datos de páginas todavía.</p>
                                <?php elseif (empty($bingPages7)): ?>
                                    <p class="text-muted mb-0 bing-period-7 d-none" data-stat-list data-stat-scope="bing-period" data-stat-period="7">Sin páginas clicadas en los últimos 7 días.</p>
                                <?php elseif (empty($bingPages28)): ?>
                                    <p class="text-muted mb-0 bing-period-28" data-stat-list data-stat-scope="bing-period" data-stat-period="28">Sin páginas clicadas en los últimos 30 días.</p>
                                <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
<?php admin_inline_asset('dashboard.js'); ?>
    </script>
<?php endif; ?>
